<?php
// Atualiza status de um lead
require_once 'bootstrap.php';

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Analistas não podem alterar status de leads
if ($user_role === 'analista') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Lê dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$lead_id = $data['lead_id'] ?? null;
$new_status = $data['status'] ?? null;
$observacao = $data['observacao'] ?? '';

// Validação
if (!$lead_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Valida status
$valid_statuses = ['novo', 'contatado', 'qualificado', 'convertido', 'perdido'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Status inválido']);
    exit;
}

try {
    // Busca lead para verificar permissão
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode([
            'success' => false,
            'message' => 'Lead não encontrado',
            'debug' => [
                'lead_id' => $lead_id
            ]
        ]);
        exit;
    }

    // Verifica se usuário tem permissão (admin só pode alterar leads da sua empresa)
    if ($user_role === 'administrador' && $lead['id_empresa_master'] != $user_empresa_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Sem permissão para este lead',
            'debug' => [
                'user_role' => $user_role,
                'user_empresa_id' => $user_empresa_id,
                'lead_empresa_master' => $lead['id_empresa_master']
            ]
        ]);
        exit;
    }

    // Atualiza status
    $stmt = $pdo->prepare("
        UPDATE leads
        SET status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$new_status, $lead_id]);
    
    // Registra no histórico
    $acao = "Status alterado de '{$lead['status']}' para '{$new_status}'";
    if (!empty($observacao)) {
        $acao .= " - Observação: {$observacao}";
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
        VALUES (?, ?, 'status_alterado', ?, NOW())
    ");
    $stmt->execute([$lead_id, $_SESSION['user_id'], $acao]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Status atualizado com sucesso'
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao atualizar status do lead: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar status: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
