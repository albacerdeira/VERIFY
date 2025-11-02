<?php
// Processa submissão de lead
header('Content-Type: application/json');

require_once 'config.php';

// Valida método de requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    // Valida campos obrigatórios
    $required_fields = ['nome', 'email', 'whatsapp'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo '$field' é obrigatório"]);
            exit;
        }
    }

    // Sanitiza dados
    $nome = trim($_POST['nome']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $whatsapp = preg_replace('/\D/', '', $_POST['whatsapp']); // Remove não-numéricos
    $empresa = !empty($_POST['empresa']) ? trim($_POST['empresa']) : null;
    $mensagem = !empty($_POST['mensagem']) ? trim($_POST['mensagem']) : null;
    
    // Valida email
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido']);
        exit;
    }
    
    // Valida WhatsApp (10 ou 11 dígitos)
    if (strlen($whatsapp) < 10 || strlen($whatsapp) > 11) {
        echo json_encode(['success' => false, 'message' => 'WhatsApp inválido']);
        exit;
    }
    
    // Dados de rastreamento
    $origem = !empty($_POST['origem']) ? trim($_POST['origem']) : null;
    $utm_source = !empty($_POST['utm_source']) ? trim($_POST['utm_source']) : null;
    $utm_medium = !empty($_POST['utm_medium']) ? trim($_POST['utm_medium']) : null;
    $utm_campaign = !empty($_POST['utm_campaign']) ? trim($_POST['utm_campaign']) : null;
    
    // Captura IP e User Agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Identifica empresa parceira (whitelabel) se houver
    $id_empresa_master = null;
    if (isset($_SESSION['whitelabel_empresa_id'])) {
        $id_empresa_master = $_SESSION['whitelabel_empresa_id'];
    }
    
    // Verifica se já existe lead com mesmo email (evita duplicatas)
    $stmt_check = $pdo->prepare("SELECT id FROM leads WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt_check->execute([$email]);
    
    if ($stmt_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Você já enviou uma solicitação recentemente. Aguarde nosso contato!']);
        exit;
    }
    
    // Insere lead no banco
    $sql = "INSERT INTO leads (
        nome, email, whatsapp, empresa, mensagem,
        origem, utm_source, utm_medium, utm_campaign,
        ip_address, user_agent, id_empresa_master
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $nome, $email, $whatsapp, $empresa, $mensagem,
        $origem, $utm_source, $utm_medium, $utm_campaign,
        $ip_address, $user_agent, $id_empresa_master
    ]);
    
    $lead_id = $pdo->lastInsertId();
    
    // Registra no histórico
    $sql_hist = "INSERT INTO leads_historico (lead_id, acao, descricao) VALUES (?, 'capturado', 'Lead capturado via formulário web')";
    $stmt_hist = $pdo->prepare($sql_hist);
    $stmt_hist->execute([$lead_id]);
    
    // Log de sucesso
    error_log("LEAD CAPTURADO - ID: {$lead_id} | Nome: {$nome} | Email: {$email}");
    
    // TODO: Enviar notificação por email para equipe de vendas
    // TODO: Integração futura com CRM
    
    echo json_encode([
        'success' => true,
        'message' => 'Lead registrado com sucesso!',
        'lead_id' => $lead_id
    ]);
    
} catch (PDOException $e) {
    error_log("ERRO AO SALVAR LEAD: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
} catch (Exception $e) {
    error_log("ERRO GERAL NO LEAD: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro inesperado']);
}
