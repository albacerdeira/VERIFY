<?php
/**
 * API Webhook - Recebimento de Leads
 * Endpoint público para captura de leads via POST
 * 
 * URL: https://seudominio.com/api_lead_webhook.php
 * Método: POST
 * Content-Type: application/json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responde OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

require_once 'config.php';

// AUTENTICAÇÃO POR TOKEN DE API
$api_token = null;
$empresa_id = null;
$empresa_slug = null;

// Busca token em várias fontes (ordem de prioridade)

// 1. Query parameter ?token= (mais fácil para WordPress/Elementor)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $api_token = $_GET['token'];
}

// 2. Header Authorization: Bearer {token}
if (empty($api_token)) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            $api_token = $matches[1];
        }
    } elseif (isset($headers['X-API-Token'])) {
        // 3. Header customizado X-API-Token
        $api_token = $headers['X-API-Token'];
    }
}

if (empty($api_token)) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Token de API obrigatório',
        'message' => 'Envie o token via: 1) Query string ?token=SEU_TOKEN ou 2) Header Authorization: Bearer {token}'
    ]);
    exit;
}

// Valida token no banco de dados
$stmt = $pdo->prepare("
    SELECT id, empresa_id, slug, nome_empresa, api_token_ativo, api_rate_limit 
    FROM configuracoes_whitelabel 
    WHERE api_token = ? 
    LIMIT 1
");
$stmt->execute([$api_token]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Token inválido',
        'message' => 'Token de API não encontrado ou inativo'
    ]);
    exit;
}

if (!$empresa['api_token_ativo']) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Token desativado',
        'message' => 'Este token de API foi desativado. Entre em contato com o suporte.'
    ]);
    exit;
}

// Armazena dados da empresa autenticada ANTES do rate limiting
$config_id = $empresa['id']; // ID da tabela configuracoes_whitelabel
$empresa_id = $empresa['empresa_id']; // ID da tabela empresas (CORRETO para FK)
$empresa_slug = $empresa['slug'];

// Validação: empresa_id deve existir
if (empty($empresa_id)) {
    error_log("Erro: configuracoes_whitelabel.empresa_id está vazio para token. Config ID: $config_id");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Configuração incorreta',
        'message' => 'A configuração da empresa está incompleta. Entre em contato com o suporte.',
        'debug' => [
            'config_id' => $config_id,
            'empresa_id' => $empresa_id,
            'hint' => 'O campo empresa_id na tabela configuracoes_whitelabel está vazio ou NULL'
        ]
    ]);
    exit;
}

// Rate Limiting (verifica últimos usos)
$rate_limit = $empresa['api_rate_limit'] ?? 100;
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM leads_webhook_log 
    WHERE empresa_id = ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute([$empresa_id]); // Usa empresa_id correto
$usage = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usage['total'] >= $rate_limit) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Rate limit excedido',
        'message' => "Limite de {$rate_limit} requisições por hora atingido"
    ]);
    exit;
}

// Atualiza último uso do token (usa o ID da config)
$pdo->prepare("UPDATE configuracoes_whitelabel SET api_ultimo_uso = NOW() WHERE id = ?")
    ->execute([$config_id]);

// Função para enviar evento ao Google Analytics
function sendGAEvent($lead_data) {
    // Se tiver Google Tag Manager ID na sessão ou configuração
    if (!empty($_SESSION['google_tag_manager_id'])) {
        // GA4 Event via Measurement Protocol (implementar se necessário)
        return true;
    }
    return false;
}

// Função para enviar para webhook externo (CRM futuro)
function sendToExternalWebhook($lead_id, $lead_data) {
    global $pdo;
    
    // Busca URL do webhook nas configurações (implementar tabela de config se necessário)
    $webhook_url = null; // TODO: buscar de configuracoes_sistema
    
    if (empty($webhook_url)) {
        return ['success' => false, 'message' => 'Webhook não configurado'];
    }
    
    $payload = json_encode($lead_data);
    $start_time = microtime(true);
    
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $tempo_resposta_ms = round((microtime(true) - $start_time) * 1000);
    $success = ($http_code >= 200 && $http_code < 300);
    
    // Log do webhook
    $stmt = $pdo->prepare("
        INSERT INTO leads_webhook_log
        (lead_id, empresa_id, webhook_url, payload_enviado, response_code, response_body, success, error_message, tempo_resposta_ms)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $lead_id,
        $GLOBALS['empresa_id'] ?? null,
        $webhook_url,
        $payload,
        $http_code,
        $response,
        $success ? 1 : 0,
        $error ?: null,
        $tempo_resposta_ms
    ]);
    
    return [
        'success' => $success,
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error
    ];
}

try {
    // Lê o JSON do body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // LOG: Registra payload recebido para debug
    error_log("=== WEBHOOK RD STATION RECEBIDO ===");
    error_log("Empresa ID: $empresa_id");
    error_log("Payload raw: " . substr($input, 0, 500)); // Primeiros 500 caracteres
    error_log("Campos recebidos: " . implode(', ', array_keys($data ?: [])));
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => 'JSON inválido',
            'json_error' => json_last_error_msg()
        ]);
        exit;
    }
    
    // MAPEAMENTO DE CAMPOS DO RD STATION
    // O RD Station envia campos com nomes diferentes, vamos normalizá-los
    $field_mapping = [
        // Nome
        'name' => 'nome',
        'full_name' => 'nome',
        'lead_name' => 'nome',
        
        // Telefone/WhatsApp
        'mobile_phone' => 'whatsapp',
        'phone' => 'whatsapp',
        'telefone' => 'whatsapp',
        'celular' => 'whatsapp',
        
        // Empresa
        'company' => 'empresa',
        'company_name' => 'empresa',
        
        // Mensagem
        'message' => 'mensagem',
        'comments' => 'mensagem',
        'comment' => 'mensagem',
        
        // UTM
        'traffic_source' => 'utm_source',
        'traffic_medium' => 'utm_medium',
        'traffic_campaign' => 'utm_campaign'
    ];
    
    // Aplica o mapeamento
    foreach ($field_mapping as $rd_field => $our_field) {
        if (isset($data[$rd_field]) && !isset($data[$our_field])) {
            $data[$our_field] = $data[$rd_field];
        }
    }
    
    // Validação dos campos obrigatórios
    $required_fields = ['nome', 'email', 'whatsapp'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'error' => "Campo obrigatório: {$field}",
                'hint' => 'Certifique-se de que o RD Station está enviando: nome (ou name), email, whatsapp (ou mobile_phone)',
                'received_fields' => array_keys($data)
            ]);
            exit;
        }
    }
    
    // Validação do email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email inválido']);
        exit;
    }
    
    // Validação do WhatsApp (apenas números)
    $whatsapp = preg_replace('/[^0-9]/', '', $data['whatsapp']);
    if (strlen($whatsapp) < 10 || strlen($whatsapp) > 15) {
        http_response_code(400);
        echo json_encode(['error' => 'WhatsApp inválido. Use formato: (XX) XXXXX-XXXX']);
        exit;
    }
    
    // Captura informações adicionais
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Usa a empresa autenticada pelo token
    $id_empresa_master = $empresa_id;
    
    // Verifica se já existe lead com este email (últimos 30 dias)
    $stmt = $pdo->prepare("
        SELECT id FROM leads 
        WHERE email = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        LIMIT 1
    ");
    $stmt->execute([$data['email']]);
    $lead_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lead_existente) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Lead já registrado recentemente',
            'lead_id' => $lead_existente['id']
        ]);
        exit;
    }
    
    // Insere o lead
    $stmt = $pdo->prepare("
        INSERT INTO leads (
            nome, email, whatsapp, empresa, mensagem,
            origem, utm_source, utm_medium, utm_campaign,
            ip_address, user_agent, id_empresa_master,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'novo')
    ");
    
    $stmt->execute([
        $data['nome'],
        $data['email'],
        $whatsapp,
        $data['empresa'] ?? null,
        $data['mensagem'] ?? null,
        $data['origem'] ?? null,
        $data['utm_source'] ?? null,
        $data['utm_medium'] ?? null,
        $data['utm_campaign'] ?? null,
        $ip_address,
        $user_agent,
        $id_empresa_master
    ]);
    
    $lead_id = $pdo->lastInsertId();
    
    // Registra histórico
    $stmt = $pdo->prepare("
        INSERT INTO leads_historico (lead_id, acao, descricao)
        VALUES (?, 'criado', 'Lead capturado via webhook')
    ");
    $stmt->execute([$lead_id]);
    
    // Envia para Google Analytics
    sendGAEvent($data);
    
    // Tenta enviar para webhook externo (async - não bloqueia resposta)
    // sendToExternalWebhook($lead_id, array_merge($data, ['lead_id' => $lead_id]));
    
    // Resposta de sucesso
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Lead registrado com sucesso!',
        'lead_id' => $lead_id,
        'data' => [
            'nome' => $data['nome'],
            'email' => $data['email']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erro PDO ao registrar lead: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar lead',
        'message' => 'Erro de banco de dados. Verifique os logs do servidor.',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
} catch (Exception $e) {
    error_log("Erro inesperado ao registrar lead: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro inesperado',
        'message' => 'Ocorreu um erro ao processar o lead. Tente novamente.',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
