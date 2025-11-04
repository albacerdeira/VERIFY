<?php
/**
 * TESTE SIMPLES DE BACKUP
 * Usa para diagnosticar problemas
 */

// Inicia buffer
ob_start();

require_once 'config.php';
session_start();

// Limpa buffer
ob_end_clean();

// Verifica se está logado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não está logado']);
    exit;
}

// Verifica se é superadmin
$stmt = $pdo->prepare("SELECT role FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não é superadmin', 'role' => $user['role'] ?? 'desconhecido']);
    exit;
}

// Pega ID da empresa
$empresa_id = $_GET['empresa_id'] ?? null;

if (!$empresa_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'empresa_id não fornecido']);
    exit;
}

try {
    // Busca empresa
    $stmt_empresa = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt_empresa->execute([$empresa_id]);
    $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresa) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Empresa não encontrada', 'id' => $empresa_id]);
        exit;
    }
    
    // Monta backup simplificado
    $backup = [
        'teste' => true,
        'empresa_id' => $empresa_id,
        'empresa_nome' => $empresa['nome'],
        'data' => date('Y-m-d H:i:s'),
        'usuario' => $_SESSION['user_name'] ?? 'Desconhecido'
    ];
    
    // Gera arquivo
    $filename = "teste_backup_{$empresa_id}_" . date('YmdHis') . ".json";
    
    // Headers
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Output
    echo json_encode($backup, JSON_PRETTY_PRINT);
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}
