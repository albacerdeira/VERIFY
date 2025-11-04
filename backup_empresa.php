<?php
/**
 * BACKUP COMPLETO DE EMPRESA
 * 
 * Gera backup JSON de uma empresa e todas suas dependências:
 * - Dados da empresa (empresas)
 * - Configurações whitelabel (configuracoes_whitelabel)
 * - Usuários (usuarios)
 * - Leads (leads + leads_historico)
 * - Clientes (kyc_clientes)
 * - Empresas KYC (kyc_empresas + kyc_avaliacoes)
 * - Logs e históricos relacionados
 * 
 * Uso: backup_empresa.php?empresa_id=123
 * Retorna: JSON para download ou erro em caso de falha
 */

// Desabilita exibição de erros (vão para log)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Inicia buffer de output para evitar problemas com headers
ob_start();

require_once 'bootstrap.php';

// Limpa qualquer output do bootstrap antes de enviar headers
$buffer_content = ob_get_clean();

// Se houver output inesperado, loga para debug
if (!empty(trim($buffer_content))) {
    error_log('[BACKUP WARNING] Output detectado antes dos headers: ' . substr($buffer_content, 0, 200));
}

// SEGURANÇA: Apenas superadmins podem fazer backup
if (!$is_superadmin) {
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'error' => 'Acesso negado. Apenas superadmins podem gerar backups.'
    ]));
}

// Captura ID da empresa
$empresa_id = $_GET['empresa_id'] ?? null;

if (!$empresa_id || !is_numeric($empresa_id)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'ID da empresa inválido ou não fornecido.'
    ]));
}

try {
    // ======================================
    // 1. DADOS DA EMPRESA (tabela principal)
    // ======================================
    $stmt_empresa = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt_empresa->execute([$empresa_id]);
    $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresa) {
        throw new Exception("Empresa com ID {$empresa_id} não encontrada.");
    }
    
    // ======================================
    // 2. CONFIGURAÇÕES WHITELABEL
    // ======================================
    $stmt_whitelabel = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt_whitelabel->execute([$empresa_id]);
    $whitelabel = $stmt_whitelabel->fetch(PDO::FETCH_ASSOC);
    
    // ======================================
    // 3. USUÁRIOS DA EMPRESA
    // ======================================
    $stmt_usuarios = $pdo->prepare("SELECT * FROM usuarios WHERE empresa_id = ?");
    $stmt_usuarios->execute([$empresa_id]);
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
    
    // ======================================
    // 4. LEADS DA EMPRESA
    // ======================================
    $stmt_leads = $pdo->prepare("SELECT * FROM leads WHERE id_empresa_master = ?");
    $stmt_leads->execute([$empresa_id]);
    $leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);
    
    // IDs dos leads para buscar histórico
    $lead_ids = array_column($leads, 'id');
    $leads_historico = [];
    
    if (!empty($lead_ids)) {
        $placeholders = implode(',', array_fill(0, count($lead_ids), '?'));
        $stmt_leads_hist = $pdo->prepare("SELECT * FROM leads_historico WHERE lead_id IN ($placeholders)");
        $stmt_leads_hist->execute($lead_ids);
        $leads_historico = $stmt_leads_hist->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ======================================
    // 5. CLIENTES KYC
    // ======================================
    $stmt_clientes = $pdo->prepare("SELECT * FROM kyc_clientes WHERE id_empresa_master = ?");
    $stmt_clientes->execute([$empresa_id]);
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);
    
    // IDs dos clientes para buscar KYCs de empresas
    $cliente_ids = array_column($clientes, 'id');
    $kyc_empresas = [];
    $kyc_avaliacoes = [];
    
    if (!empty($cliente_ids)) {
        $placeholders_clientes = implode(',', array_fill(0, count($cliente_ids), '?'));
        
        // KYC Empresas
        $stmt_kyc_empresas = $pdo->prepare("SELECT * FROM kyc_empresas WHERE cliente_id IN ($placeholders_clientes)");
        $stmt_kyc_empresas->execute($cliente_ids);
        $kyc_empresas = $stmt_kyc_empresas->fetchAll(PDO::FETCH_ASSOC);
        
        // IDs das empresas KYC para buscar avaliações
        $kyc_empresa_ids = array_column($kyc_empresas, 'id');
        
        if (!empty($kyc_empresa_ids)) {
            $placeholders_kyc = implode(',', array_fill(0, count($kyc_empresa_ids), '?'));
            $stmt_avaliacoes = $pdo->prepare("SELECT * FROM kyc_avaliacoes WHERE empresa_id IN ($placeholders_kyc)");
            $stmt_avaliacoes->execute($kyc_empresa_ids);
            $kyc_avaliacoes = $stmt_avaliacoes->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ======================================
    // 6. TENTATIVAS DE LOGIN (SEGURANÇA)
    // ======================================
    $login_attempts = [];
    if (!empty($cliente_ids)) {
        // Busca tentativas de login dos clientes
        try {
            $stmt_login = $pdo->prepare("SELECT * FROM login_attempts WHERE cliente_id IN ($placeholders_clientes)");
            $stmt_login->execute($cliente_ids);
            $login_attempts = $stmt_login->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Tabela pode não existir em todas as instalações
            $login_attempts = [];
        }
    }
    
    // ======================================
    // 7. VERIFICAÇÕES FACIAIS (se existir)
    // ======================================
    $facial_verifications = [];
    if (!empty($cliente_ids)) {
        try {
            $stmt_facial = $pdo->prepare("SELECT * FROM facial_verifications WHERE cliente_id IN ($placeholders_clientes)");
            $stmt_facial->execute($cliente_ids);
            $facial_verifications = $stmt_facial->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Tabela pode não existir
            $facial_verifications = [];
        }
    }
    
    // ======================================
    // 8. MONTA O BACKUP COMPLETO
    // ======================================
    $backup = [
        'metadata' => [
            'backup_version' => '1.0',
            'backup_date' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['user_name'] ?? 'Sistema',
            'empresa_id' => $empresa_id,
            'empresa_nome' => $empresa['nome'],
        ],
        
        'empresa' => $empresa,
        'whitelabel' => $whitelabel,
        
        'usuarios' => [
            'total' => count($usuarios),
            'data' => $usuarios
        ],
        
        'leads' => [
            'total' => count($leads),
            'data' => $leads,
            'historico' => $leads_historico
        ],
        
        'clientes' => [
            'total' => count($clientes),
            'data' => $clientes
        ],
        
        'kyc_empresas' => [
            'total' => count($kyc_empresas),
            'data' => $kyc_empresas,
            'avaliacoes' => $kyc_avaliacoes
        ],
        
        'security' => [
            'login_attempts' => $login_attempts,
            'facial_verifications' => $facial_verifications
        ],
        
        'statistics' => [
            'total_usuarios' => count($usuarios),
            'total_leads' => count($leads),
            'total_clientes' => count($clientes),
            'total_kyc_empresas' => count($kyc_empresas),
            'total_avaliacoes' => count($kyc_avaliacoes),
        ]
    ];
    
    // ======================================
    // 9. GERA O ARQUIVO PARA DOWNLOAD
    // ======================================
    $filename = sprintf(
        'backup_empresa_%d_%s_%s.json',
        $empresa_id,
        preg_replace('/[^a-z0-9]/i', '_', strtolower($empresa['nome'])),
        date('Y-m-d_His')
    );
    
    // Headers para download
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output do JSON (pretty print para facilitar leitura)
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // ======================================
    // 10. LOG DA AÇÃO
    // ======================================
    error_log(sprintf(
        '[BACKUP] Empresa ID %d (%s) - Backup gerado por %s - %d usuários, %d leads, %d clientes, %d KYCs',
        $empresa_id,
        $empresa['nome'],
        $_SESSION['user_name'] ?? 'Sistema',
        count($usuarios),
        count($leads),
        count($clientes),
        count($kyc_empresas)
    ));
    
    exit; // Garante que nada mais seja enviado após o JSON
    
} catch (Exception $e) {
    // Limpa qualquer output que possa ter sido gerado
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(500);
    error_log('[BACKUP ERROR] Empresa ID ' . $empresa_id . ' - ' . $e->getMessage());
    
    // Retorna erro em JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'empresa_id' => $empresa_id,
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
    exit;
}
