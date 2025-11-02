<?php
/**
 * Diagnóstico do Sistema de Leads
 * Verifica configurações, banco de dados e conectividade
 */

$page_title = 'Diagnóstico do Sistema';
require_once 'bootstrap.php';

// Garante que apenas administradores e superadmins possam acessar
if (!$is_admin && !$is_superadmin) {
    require_once 'header.php';
    echo "<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require_once 'footer.php';
    exit;
}

$empresa_id = $is_superadmin && isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : (int)$_SESSION['empresa_id'];

require_once 'header.php';
?>

<div class="container mt-4">
    <h2><i class="bi bi-heart-pulse-fill text-danger"></i> Diagnóstico do Sistema</h2>
    <p class="text-muted">Verificação completa de configurações e conectividade</p>

    <?php
    $diagnostics = [];
    $all_ok = true;

    // 1. Verifica tabelas do banco
    try {
        $tables_required = ['leads', 'leads_historico', 'leads_webhook_log', 'configuracoes_whitelabel'];
        foreach ($tables_required as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $diagnostics[] = [
                'category' => 'Banco de Dados',
                'item' => "Tabela: $table",
                'status' => $exists,
                'message' => $exists ? 'Existe' : 'NÃO ENCONTRADA! Execute INSTALL_LEADS_SYSTEM.sql'
            ];
            if (!$exists) $all_ok = false;
        }
    } catch (Exception $e) {
        $diagnostics[] = [
            'category' => 'Banco de Dados',
            'item' => 'Conexão',
            'status' => false,
            'message' => 'Erro: ' . $e->getMessage()
        ];
        $all_ok = false;
    }

    // 2. Verifica configuração da empresa
    try {
        $stmt = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        $diagnostics[] = [
            'category' => 'Configuração',
            'item' => 'Registro da empresa',
            'status' => !empty($config),
            'message' => !empty($config) ? "Encontrado (ID: {$config['id']})" : 'NÃO ENCONTRADO!'
        ];

        if (!empty($config)) {
            $diagnostics[] = [
                'category' => 'Configuração',
                'item' => 'Token API',
                'status' => !empty($config['api_token']),
                'message' => !empty($config['api_token']) 
                    ? 'Configurado (' . substr($config['api_token'], 0, 10) . '...)' 
                    : 'NÃO CONFIGURADO! Gere o token em Configurações.'
            ];

            $diagnostics[] = [
                'category' => 'Configuração',
                'item' => 'Token ativo',
                'status' => !empty($config['api_token_ativo']),
                'message' => !empty($config['api_token_ativo']) ? 'Sim' : 'NÃO! Ative em Configurações.'
            ];

            $diagnostics[] = [
                'category' => 'Configuração',
                'item' => 'Slug (Whitelabel)',
                'status' => !empty($config['slug']),
                'message' => !empty($config['slug']) ? $config['slug'] : 'Não configurado'
            ];
        } else {
            $all_ok = false;
        }
    } catch (Exception $e) {
        $diagnostics[] = [
            'category' => 'Configuração',
            'item' => 'Erro',
            'status' => false,
            'message' => $e->getMessage()
        ];
        $all_ok = false;
    }

    // 3. Verifica permissões de escrita
    $upload_dirs = ['uploads/kyc', 'uploads/logos', 'uploads/selfies'];
    foreach ($upload_dirs as $dir) {
        $writable = is_writable($dir);
        $diagnostics[] = [
            'category' => 'Permissões',
            'item' => "Pasta: $dir",
            'status' => $writable,
            'message' => $writable ? 'Gravável' : 'SEM PERMISSÃO DE ESCRITA!'
        ];
        if (!$writable) $all_ok = false;
    }

    // 4. Verifica URL da API
    $api_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
                $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . 
                '/api_lead_webhook.php';
    
    $diagnostics[] = [
        'category' => 'API',
        'item' => 'URL do Webhook',
        'status' => true,
        'message' => $api_url
    ];

    // 5. Testa conectividade da API (se tiver token)
    if (!empty($config['api_token'])) {
        $test_url = $api_url . '?token=' . urlencode($config['api_token']);
        
        // Tenta fazer requisição de teste
        $ch = curl_init($test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'nome' => 'Teste Diagnóstico',
            'email' => 'teste@diagnostico.com',
            'whatsapp' => '11999999999'
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        $api_ok = in_array($http_code, [200, 201]);
        
        $diagnostics[] = [
            'category' => 'API',
            'item' => 'Teste de conectividade',
            'status' => $api_ok,
            'message' => $api_ok 
                ? "HTTP $http_code - API funcionando!" 
                : "HTTP $http_code - Erro: " . ($curl_error ?: $response)
        ];
        
        if (!$api_ok) $all_ok = false;
    }

    // 6. Verifica contadores
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM leads WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $lead_count = $stmt->fetchColumn();
        
        $diagnostics[] = [
            'category' => 'Estatísticas',
            'item' => 'Total de leads',
            'status' => true,
            'message' => $lead_count . ' leads cadastrados'
        ];
    } catch (Exception $e) {
        $diagnostics[] = [
            'category' => 'Estatísticas',
            'item' => 'Erro',
            'status' => false,
            'message' => $e->getMessage()
        ];
    }

    // 7. Verifica arquivos necessários
    $required_files = [
        'api_lead_webhook.php',
        'verify-universal-form-capture.js',
        'ajax_get_recent_leads.php',
        'ajax_regenerate_api_token.php'
    ];
    
    foreach ($required_files as $file) {
        $exists = file_exists($file);
        $diagnostics[] = [
            'category' => 'Arquivos',
            'item' => $file,
            'status' => $exists,
            'message' => $exists ? 'Existe' : 'NÃO ENCONTRADO!'
        ];
        if (!$exists) $all_ok = false;
    }

    // Agrupa por categoria
    $grouped = [];
    foreach ($diagnostics as $item) {
        $grouped[$item['category']][] = $item;
    }
    ?>

    <!-- Status Geral -->
    <div class="alert alert-<?= $all_ok ? 'success' : 'danger' ?> mb-4">
        <h4>
            <i class="bi bi-<?= $all_ok ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
            <?= $all_ok ? 'Sistema OK!' : 'Problemas Detectados!' ?>
        </h4>
        <p class="mb-0">
            <?= $all_ok 
                ? 'Todas as verificações passaram. O sistema está pronto para uso.' 
                : 'Alguns problemas foram encontrados. Verifique os detalhes abaixo e corrija antes de usar o sistema.' 
            ?>
        </p>
    </div>

    <!-- Resultados por Categoria -->
    <?php foreach ($grouped as $category => $items): ?>
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <strong><i class="bi bi-folder-fill"></i> <?= htmlspecialchars($category) ?></strong>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th width="50">Status</th>
                        <th>Item</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center">
                            <i class="bi bi-<?= $item['status'] ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?> fs-5"></i>
                        </td>
                        <td><strong><?= htmlspecialchars($item['item']) ?></strong></td>
                        <td><code><?= htmlspecialchars($item['message']) ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Ações Recomendadas -->
    <?php if (!$all_ok): ?>
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning">
            <strong><i class="bi bi-exclamation-triangle"></i> Ações Recomendadas</strong>
        </div>
        <div class="card-body">
            <ol>
                <?php if (!empty($config) && empty($config['api_token'])): ?>
                <li>Vá em <a href="configuracoes.php">Configurações</a> e gere um Token API</li>
                <?php endif; ?>
                
                <?php
                $missing_tables = array_filter($diagnostics, function($d) {
                    return $d['category'] === 'Banco de Dados' && !$d['status'];
                });
                if (!empty($missing_tables)): ?>
                <li>Execute o script SQL: <code>INSTALL_LEADS_SYSTEM.sql</code> no seu banco de dados</li>
                <?php endif; ?>
                
                <?php
                $permission_issues = array_filter($diagnostics, function($d) {
                    return $d['category'] === 'Permissões' && !$d['status'];
                });
                if (!empty($permission_issues)): ?>
                <li>Ajuste as permissões das pastas de upload: <code>chmod 755 uploads/*</code></li>
                <?php endif; ?>
            </ol>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center mb-4">
        <a href="configuracoes.php" class="btn btn-primary">
            <i class="bi bi-gear"></i> Ir para Configurações
        </a>
        <a href="test_universal_capture.php" class="btn btn-success">
            <i class="bi bi-bug"></i> Testar Captura
        </a>
        <button onclick="location.reload()" class="btn btn-secondary">
            <i class="bi bi-arrow-clockwise"></i> Executar Diagnóstico Novamente
        </button>
    </div>
</div>

<?php require_once 'footer.php'; ?>
