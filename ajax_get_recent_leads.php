<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

try {
    // Busca pela empresa usando o token da URL
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        
        $sql = "SELECT empresa_id FROM configuracoes_whitelabel WHERE api_token = :token";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['token' => $token]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }
        
        $empresa_id = (int)$config['empresa_id'];
    } 
    // Se não tem token, usa a sessão (para admins logados)
    elseif (isset($_SESSION['empresa_id'])) {
        $empresa_id = (int)$_SESSION['empresa_id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Empresa não identificada']);
        exit;
    }
    
    // Busca os últimos 10 leads da empresa
    $sql = "SELECT 
                id, 
                nome, 
                email, 
                whatsapp, 
                status, 
                DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as data_criacao
            FROM leads 
            WHERE id_empresa_master = :empresa_id 
            ORDER BY created_at DESC 
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'leads' => $leads,
        'count' => count($leads)
    ]);
    
} catch (Exception $e) {
    error_log("Erro em ajax_get_recent_leads.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar leads: ' . $e->getMessage()
    ]);
}
