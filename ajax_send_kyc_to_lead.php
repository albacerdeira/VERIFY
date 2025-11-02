<?php
// Envia link do formulário KYC para um lead
require_once 'bootstrap.php';

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Analistas não podem enviar KYC para leads
if ($_SESSION['user_role'] === 'analista') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Lê dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$lead_id = $data['lead_id'] ?? null;

if (!$lead_id) {
    echo json_encode(['success' => false, 'message' => 'Lead ID não fornecido']);
    exit;
}

try {
    // Busca dados do lead
    $stmt = $pdo->prepare("
        SELECT l.*, 
               e.nome as empresa_parceira_nome,
               w.slug as empresa_slug,
               w.nome_empresa as whitelabel_nome
        FROM leads l
        LEFT JOIN empresas e ON l.id_empresa_master = e.id
        LEFT JOIN configuracoes_whitelabel w ON w.empresa_id = l.id_empresa_master
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
        exit;
    }
    
    // Verifica permissão (admin só pode enviar para leads da sua empresa)
    $user_empresa_id = $_SESSION['empresa_id'] ?? null;
    if ($_SESSION['user_role'] === 'administrador' && $lead['id_empresa_master'] != $user_empresa_id) {
        echo json_encode([
            'success' => false, 
            'message' => 'Sem permissão para este lead',
            'debug' => [
                'lead_empresa' => $lead['id_empresa_master'],
                'user_empresa' => $user_empresa_id,
                'user_role' => $_SESSION['user_role']
            ]
        ]);
        exit;
    }
    
    // Cria ou busca cliente KYC existente baseado no email do lead
    $stmt_check = $pdo->prepare("SELECT id, status FROM kyc_clientes WHERE email = ?");
    $stmt_check->execute([$lead['email']]);
    $cliente_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente_existente) {
        $cliente_id = $cliente_existente['id'];
        $mensagem_status = 'Cliente já existente localizado';
        
        // Garante que o status esteja 'ativo'
        if ($cliente_existente['status'] !== 'ativo') {
            $stmt_ativar = $pdo->prepare("UPDATE kyc_clientes SET status = 'ativo' WHERE id = ?");
            $stmt_ativar->execute([$cliente_id]);
        }
    } else {
        // Cria novo cliente KYC vinculado ao lead
        // Senha temporária (lead pode redefinir depois)
        $senha_temporaria = bin2hex(random_bytes(8)); // Gera senha aleatória de 16 caracteres
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);
        
        $stmt_create = $pdo->prepare("
            INSERT INTO kyc_clientes (
                nome_completo,
                email,
                telefone,
                password,
                id_empresa_master,
                origem,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'lead_conversion', 'ativo', NOW())
        ");
        
        $stmt_create->execute([
            $lead['nome'],
            $lead['email'],
            $lead['whatsapp'],
            $senha_hash,
            $lead['id_empresa_master']
        ]);
        
        $cliente_id = $pdo->lastInsertId();
        $mensagem_status = 'Novo cliente criado';
        
        // Atualiza lead com o ID do cliente criado
        $stmt_update = $pdo->prepare("UPDATE leads SET crm_id = ? WHERE id = ?");
        $stmt_update->execute(['cliente_kyc_' . $cliente_id, $lead_id]);
    }
    
    // Gera token único para acesso direto ao formulário
    $token = bin2hex(random_bytes(32));
    
    $stmt_token = $pdo->prepare("
        UPDATE kyc_clientes 
        SET token_acesso = ?,
            token_expiracao = DATE_ADD(NOW(), INTERVAL 30 DAY)
        WHERE id = ?
    ");
    $stmt_token->execute([$token, $cliente_id]);
    
    // Constrói URL do formulário KYC
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $base_url .= dirname($_SERVER['PHP_SELF']);
    
    if ($lead['empresa_slug']) {
        $kyc_url = $base_url . "/kyc_form.php?cliente=" . urlencode($lead['empresa_slug']) . "&token=" . $token;
    } else {
        $kyc_url = $base_url . "/kyc_form.php?token=" . $token;
    }
    
    // Registra no histórico do lead
    $stmt_hist = $pdo->prepare("
        INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
        VALUES (?, ?, 'kyc_enviado', ?, NOW())
    ");
    
    $descricao = "{$mensagem_status}. Link do formulário KYC gerado e enviado para {$lead['email']}. Cliente ID: {$cliente_id}";
    $stmt_hist->execute([$lead_id, $_SESSION['user_id'], $descricao]);
    
    // Atualiza status do lead para "contatado" se ainda estiver como "novo"
    if ($lead['status'] === 'novo') {
        $stmt_status = $pdo->prepare("UPDATE leads SET status = 'contatado', updated_at = NOW() WHERE id = ?");
        $stmt_status->execute([$lead_id]);
        
        $stmt_hist2 = $pdo->prepare("
            INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
            VALUES (?, ?, 'status_alterado', 'Status alterado de \"novo\" para \"contatado\" (envio automático de KYC)', NOW())
        ");
        $stmt_hist2->execute([$lead_id, $_SESSION['user_id']]);
    }
    
    // TODO: Enviar email com o link (integração futura)
    // Pode usar PHPMailer que já existe no projeto
    
    echo json_encode([
        'success' => true,
        'message' => "Formulário KYC preparado com sucesso! {$mensagem_status}.",
        'kyc_url' => $kyc_url,
        'cliente_id' => $cliente_id,
        'token' => $token
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao enviar KYC para lead: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}
