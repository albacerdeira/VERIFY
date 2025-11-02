<?php
/**
 * Regenera o token de API da empresa
 * Apenas admins e superadmins podem executar
 */

header('Content-Type: application/json');
require_once 'bootstrap.php';

// Verifica autenticação
if (!isset($_SESSION['usuario_id']) || (!$is_admin && !$is_superadmin)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

try {
    // Define qual empresa será atualizada
    $empresa_id_para_editar = null;
    
    if ($is_superadmin && isset($_GET['empresa_id'])) {
        $empresa_id_para_editar = (int)$_GET['empresa_id'];
    } else if ($is_admin) {
        $empresa_id_para_editar = (int)($_SESSION['empresa_id'] ?? 0);
    }
    
    if (!$empresa_id_para_editar) {
        throw new Exception('Empresa não identificada');
    }
    
    // Gera novo token único (64 caracteres)
    $new_token = bin2hex(random_bytes(32));
    
    // Atualiza no banco
    $stmt = $pdo->prepare("
        UPDATE configuracoes_whitelabel 
        SET api_token = ?,
            api_token_ativo = 1,
            api_ultimo_uso = NULL
        WHERE empresa_id = ?
    ");
    
    $stmt->execute([$new_token, $empresa_id_para_editar]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Nenhuma configuração encontrada para esta empresa');
    }
    
    // Log da ação
    error_log("Token API regenerado para empresa_id={$empresa_id_para_editar} por usuario_id={$_SESSION['usuario_id']}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Token regenerado com sucesso',
        'new_token' => $new_token
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
