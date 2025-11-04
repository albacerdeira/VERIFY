<?php
/**
 * FERRAMENTAS DE DIAGNÓSTICO E TESTES
 * 
 * Central de ferramentas para debug, testes e manutenção do sistema
 * Acesso restrito a superadmins
 */

$page_title = 'Ferramentas de Diagnóstico';
require_once 'bootstrap.php';

// SEGURANÇA: Apenas superadmins
if (!$is_superadmin) {
    require_once 'header.php';
    echo "<div class='container'><div class='alert alert-danger mt-4'>Acesso negado. Apenas superadmins podem acessar as ferramentas de diagnóstico.</div></div>";
    require_once 'footer.php';
    exit;
}

require_once 'header.php';

// Define as ferramentas organizadas por categoria
$ferramentas = [
    'Sistema e Infraestrutura' => [
        [
            'nome' => 'Diagnóstico Geral',
            'descricao' => 'Verifica status geral do sistema, banco de dados, arquivos e permissões',
            'arquivo' => 'diagnostico.php',
            'icone' => 'bi-heart-pulse',
            'cor' => 'primary'
        ],
        [
            'nome' => 'Verificar Empresa ID',
            'descricao' => 'Debug de empresa_id e contexto de whitelabel',
            'arquivo' => 'check_empresa_id.php',
            'icone' => 'bi-building-check',
            'cor' => 'info'
        ],
        [
            'nome' => 'Testar Composer/Autoloader',
            'descricao' => 'Verifica instalação do Composer e carregamento de classes',
            'arquivo' => 'test_composer.php',
            'icone' => 'bi-file-earmark-code',
            'cor' => 'secondary'
        ],
        [
            'nome' => 'Debug Autoloader',
            'descricao' => 'Diagnóstico detalhado do sistema de autoload do Composer',
            'arquivo' => 'debug_autoloader.php',
            'icone' => 'bi-gear-fill',
            'cor' => 'secondary'
        ],
    ],
    
    'Banco de Dados e Estrutura' => [
        [
            'nome' => 'Schema Whitelabel',
            'descricao' => 'Mostra estrutura da tabela configuracoes_whitelabel e testa queries',
            'arquivo' => 'debug_whitelabel_schema.php',
            'icone' => 'bi-table',
            'cor' => 'success'
        ],
        [
            'nome' => 'Debug Lead/KYC',
            'descricao' => 'Verifica relacionamento entre leads e clientes KYC',
            'arquivo' => 'debug_lead_kyc.php',
            'icone' => 'bi-diagram-3',
            'cor' => 'success'
        ],
        [
            'nome' => 'Debug Sessão B4U',
            'descricao' => 'Verifica dados de sessão e contexto do usuário',
            'arquivo' => 'debug_session_b4u.php',
            'icone' => 'bi-person-circle',
            'cor' => 'info'
        ],
    ],
    
    'AWS e Integração' => [
        [
            'nome' => 'Testar AWS Credentials',
            'descricao' => 'Valida credenciais AWS S3 e Rekognition',
            'arquivo' => 'test_aws_credentials.php',
            'icone' => 'bi-cloud-check',
            'cor' => 'warning'
        ],
        [
            'nome' => 'Debug OCR Completo',
            'descricao' => 'Testa extração de texto de documentos com AWS Textract',
            'arquivo' => 'debug_ocr_full.php',
            'icone' => 'bi-file-text',
            'cor' => 'warning'
        ],
        [
            'nome' => 'Testar Verificação Facial',
            'descricao' => 'Testa AWS Rekognition para comparação facial',
            'arquivo' => 'test_face_verification.php',
            'icone' => 'bi-person-bounding-box',
            'cor' => 'danger'
        ],
    ],
    
    'Formulários e Captura' => [
        [
            'nome' => 'Captura Universal de Forms',
            'descricao' => 'Testa captura automática de formulários em sites externos',
            'arquivo' => 'test_universal_capture.php',
            'icone' => 'bi-file-earmark-arrow-down',
            'cor' => 'primary'
        ],
        [
            'nome' => 'Testar AJAX Check',
            'descricao' => 'Valida endpoints AJAX e comunicação com servidor',
            'arquivo' => 'test_ajax_check.php',
            'icone' => 'bi-arrow-left-right',
            'cor' => 'info'
        ],
    ],
    
    'Leads e Detalhes' => [
        [
            'nome' => 'Testar Lead Detail',
            'descricao' => 'Visualização detalhada de lead e histórico',
            'arquivo' => 'test_lead_detail.php',
            'icone' => 'bi-person-lines-fill',
            'cor' => 'success'
        ],
    ],
    
    'Backup e Manutenção' => [
        [
            'nome' => 'Testar Backup Simples',
            'descricao' => 'Versão simplificada do sistema de backup para diagnóstico',
            'arquivo' => 'test_backup.php',
            'icone' => 'bi-download',
            'cor' => 'success'
        ],
    ],
];

?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2>
                <i class="bi bi-tools"></i> Ferramentas de Diagnóstico e Testes
            </h2>
            <p class="text-muted">Central de ferramentas para debug, testes e manutenção do sistema</p>
        </div>
    </div>

    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Atenção - Ambiente de Desenvolvimento</h6>
        <p class="mb-0">
            Estas ferramentas são para <strong>diagnóstico e testes</strong>. Algumas podem expor informações sensíveis do sistema.
            Use apenas em <strong>ambiente de desenvolvimento</strong> ou quando necessário para troubleshooting.
        </p>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <?php foreach ($ferramentas as $categoria => $items): ?>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="bi bi-folder"></i> <?= htmlspecialchars($categoria) ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($items as $ferramenta): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-<?= $ferramenta['cor'] ?>">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi <?= $ferramenta['icone'] ?> text-<?= $ferramenta['cor'] ?>"></i>
                                        <?= htmlspecialchars($ferramenta['nome']) ?>
                                    </h6>
                                    <p class="card-text text-muted small">
                                        <?= htmlspecialchars($ferramenta['descricao']) ?>
                                    </p>
                                    <div class="d-grid gap-2">
                                        <a href="<?= htmlspecialchars($ferramenta['arquivo']) ?>" 
                                           class="btn btn-<?= $ferramenta['cor'] ?> btn-sm" 
                                           target="_blank">
                                            <i class="bi bi-box-arrow-up-right"></i> Abrir Ferramenta
                                        </a>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <small class="text-muted">
                                        <i class="bi bi-file-code"></i> 
                                        <code><?= htmlspecialchars($ferramenta['arquivo']) ?></code>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card border-danger mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="bi bi-shield-exclamation"></i> Segurança e Boas Práticas
            </h5>
        </div>
        <div class="card-body">
            <h6>⚠️ Importante:</h6>
            <ul>
                <li><strong>Ambiente de Produção:</strong> Remova ou proteja estas ferramentas em produção</li>
                <li><strong>Dados Sensíveis:</strong> Algumas ferramentas podem exibir senhas, tokens e chaves API</li>
                <li><strong>Performance:</strong> Testes podem consumir recursos do servidor</li>
                <li><strong>Logs:</strong> Todas as ações são registradas no <code>error.log</code></li>
            </ul>
            
            <h6 class="mt-3">🔒 Para Remover Ferramentas em Produção:</h6>
            <div class="bg-dark text-light p-3 rounded">
                <code>
                    # Via terminal SSH:<br>
                    cd /home/u640879529/domains/verify2b.com/public_html<br>
                    rm test_*.php debug_*.php diagnostico.php check_empresa_id.php ferramentas.php
                </code>
            </div>
            
            <h6 class="mt-3">📋 Ou Mova para no_upload/:</h6>
            <div class="bg-dark text-light p-3 rounded">
                <code>
                    mv test_*.php debug_*.php no_upload/tests/<br>
                    mv diagnostico.php check_empresa_id.php no_upload/debug/
                </code>
            </div>
        </div>
    </div>

    <div class="card border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-info-circle"></i> Informações Úteis
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="bi bi-server"></i> Informações do Servidor</h6>
                    <ul class="list-unstyled">
                        <li><strong>PHP:</strong> <?= phpversion() ?></li>
                        <li><strong>Servidor:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido' ?></li>
                        <li><strong>Timezone:</strong> <?= date_default_timezone_get() ?></li>
                        <li><strong>Upload Max:</strong> <?= ini_get('upload_max_filesize') ?></li>
                        <li><strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="bi bi-database"></i> Banco de Dados</h6>
                    <ul class="list-unstyled">
                        <li><strong>Host:</strong> <?= DB_HOST ?></li>
                        <li><strong>Database:</strong> <?= DB_NAME ?></li>
                        <li><strong>Usuário:</strong> <?= DB_USER ?></li>
                        <li>
                            <strong>Status:</strong> 
                            <?php 
                            try {
                                $pdo->query('SELECT 1');
                                echo '<span class="badge bg-success">✓ Conectado</span>';
                            } catch (Exception $e) {
                                echo '<span class="badge bg-danger">✗ Erro</span>';
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php require_once 'footer.php'; ?>
