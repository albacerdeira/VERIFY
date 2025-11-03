<?php
// Deleta registro de cliente
require_once 'bootstrap.php';

// Apenas superadmin e admin
if (!$is_superadmin && !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Lê dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$cliente_id = $data['cliente_id'] ?? null;

if (!$cliente_id) {
    echo json_encode(['success' => false, 'message' => 'ID do cliente não fornecido']);
    exit;
}

try {
    // Busca dados do cliente
    $stmt = $pdo->prepare("SELECT id, nome_completo, email, id_empresa_master FROM kyc_clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }
    
    // Verifica permissão (admin só pode deletar clientes da sua empresa)
    if ($is_admin && $cliente['id_empresa_master'] != $user_empresa_id) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para deletar este cliente']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Deleta arquivos associados (selfie)
    $stmt_files = $pdo->prepare("SELECT selfie_path FROM kyc_clientes WHERE id = ?");
    $stmt_files->execute([$cliente_id]);
    $files = $stmt_files->fetch(PDO::FETCH_ASSOC);
    
    if ($files && !empty($files['selfie_path']) && file_exists($files['selfie_path'])) {
        @unlink($files['selfie_path']);
    }
    
    // Deleta KYC empresas relacionados
    $stmt_del_kyc = $pdo->prepare("DELETE FROM kyc_empresas WHERE cliente_id = ?");
    $stmt_del_kyc->execute([$cliente_id]);
    $kyc_deletados = $stmt_del_kyc->rowCount();
    
    // Deleta o cliente
    $stmt_del = $pdo->prepare("DELETE FROM kyc_clientes WHERE id = ?");
    $stmt_del->execute([$cliente_id]);
    
    $pdo->commit();
    
    // Log da ação
    error_log("Cliente deletado: ID={$cliente_id}, Nome={$cliente['nome_completo']}, Email={$cliente['email']}, Usuario={$_SESSION['user_name']}, KYCs_deletados={$kyc_deletados}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Cliente deletado com sucesso' . ($kyc_deletados > 0 ? " (incluindo {$kyc_deletados} formulário(s) KYC)" : '')
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro ao deletar cliente {$cliente_id}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar cliente: ' . $e->getMessage()
    ]);
}
