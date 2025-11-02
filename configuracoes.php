<?php
$page_title = 'Configurações Whitelabel';
require_once 'bootstrap.php';

// Garante que apenas administradores e superadmins possam acessar.
if (!$is_admin && !$is_superadmin) {
    require_once 'header.php'; // Carrega o header para manter o layout
    echo "<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require_once 'footer.php';
    exit;
}

function gerarSlug($texto) {
    $texto = preg_replace('~[^\pL\d]+~u', '-', $texto);
    $texto = iconv('utf-8', 'us-ascii//TRANSLIT', $texto);
    $texto = preg_replace('~[^-\w]+~', '', $texto);
    $texto = trim($texto, '-');
    $texto = preg_replace('~-+~', '-', $texto);
    $texto = strtolower($texto);
    if (empty($texto)) { return 'n-a-' . uniqid(); }
    return $texto;
}

$error = null;
$success = null;

// Lógica de decisão de qual empresa editar (deve vir antes do POST)
$empresa_id_para_editar = null;
if ($is_superadmin && isset($_GET['id'])) {
    $empresa_id_para_editar = (int)$_GET['id'];
} else if ($is_admin) {
    // Admin sempre edita sua própria empresa
    $empresa_id_para_editar = (int)($_SESSION['empresa_id'] ?? 0);
}

// DEBUG: Remover depois
error_log("DEBUG configuracoes.php: empresa_id_para_editar = " . var_export($empresa_id_para_editar, true));
error_log("DEBUG configuracoes.php: SESSION empresa_id = " . var_export($_SESSION['empresa_id'] ?? 'NOT SET', true));
error_log("DEBUG configuracoes.php: is_admin = " . var_export($is_admin, true));
error_log("DEBUG configuracoes.php: is_superadmin = " . var_export($is_superadmin, true));

// Carrega as configurações atuais ANTES de processar o POST para ter o estado pré-mudança
$config_atual = null;
if ($empresa_id_para_editar && $empresa_id_para_editar > 0) {
    $stmt_current = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt_current->execute([$empresa_id_para_editar]);
    $config_atual = $stmt_current->fetch(PDO::FETCH_ASSOC);
    
    // Se não existe registro, cria um novo
    if (!$config_atual) {
        $stmt_empresa = $pdo->prepare("SELECT nome FROM empresas WHERE id = ?");
        $stmt_empresa->execute([$empresa_id_para_editar]);
        $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            // Cria registro inicial
            $stmt_insert = $pdo->prepare(
                "INSERT INTO configuracoes_whitelabel (empresa_id, nome_empresa, logo_url, cor_variavel) 
                 VALUES (?, ?, 'imagens/verify-kyc.png', '#4f46e5')"
            );
            $stmt_insert->execute([$empresa_id_para_editar, $empresa['nome']]);
            
            // Recarrega
            $stmt_current->execute([$empresa_id_para_editar]);
            $config_atual = $stmt_current->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Lógica de processamento do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id_post = $_POST['empresa_id_para_editar'];
    
    if (!$is_superadmin && $empresa_id_post != $_SESSION['empresa_id']) {
        $error = "Acesso negado.";
    } else {
        $nome_empresa = trim($_POST['nome_empresa']);
        $cor_variavel = trim($_POST['cor_variavel']);
        $google_tag_manager_id = trim($_POST['google_tag_manager_id']);
        $logo_path = $_POST['current_logo'] ?? '';
        $slug = $_POST['slug'] ?? ''; 

        try {
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = uniqid() . '-' . basename($_FILES['logo']['name']);
                $target_file = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    $logo_path = $target_file;
                } else { throw new Exception("Falha ao mover o arquivo de logo enviado."); }
            }

            $sql = "UPDATE configuracoes_whitelabel SET nome_empresa = :nome, logo_url = :logo, cor_variavel = :cor, google_tag_manager_id = :gtm_id";
            $params = [':nome' => $nome_empresa, ':logo' => $logo_path, ':cor' => $cor_variavel, ':gtm_id' => $google_tag_manager_id, ':id' => $empresa_id_post];

            if ($is_superadmin || empty($config_atual['slug'])) {
                if (empty($slug)) throw new Exception("O campo Slug é obrigatório.");
                $sql .= ", slug = :slug";
                $params[':slug'] = gerarSlug($slug);
            }

            $sql .= " WHERE empresa_id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($empresa_id_post == ($_SESSION['empresa_id'] ?? null)) {
                $_SESSION['nome_empresa'] = $nome_empresa;
                $_SESSION['logo_url'] = $logo_path;
                $_SESSION['cor_variavel'] = $cor_variavel;
            }

            $success = "Configurações salvas com sucesso!";
            // Recarrega a página para refletir as mudanças na sessão e no formulário
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;

        } catch (Exception $e) {
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// Carrega o header da página
require_once 'header.php';

// --- LÓGICA DE EXIBIÇÃO ---

if ($is_superadmin && !$empresa_id_para_editar) {
    $empresas = $pdo->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>
    <div class="container">
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading">Modo Superadmin</h5>
            <p>Selecione uma empresa para editar suas configurações.</p>
            <select id="empresa-selector" class="form-select">
                <option value="">Selecione...</option>
                <?php foreach ($empresas as $empresa): ?>
                    <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <script>
    document.getElementById('empresa-selector').addEventListener('change', function() {
        if (this.value) {
            window.location.href = 'configuracoes.php?id=' + this.value;
        }
    });
    </script>
<?php
} elseif ($empresa_id_para_editar && $empresa_id_para_editar > 0) {
    // Recarrega os dados da empresa caso tenham sido atualizados
    $stmt = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$empresa_id_para_editar]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou, tenta criar
    if (!$config) {
        $stmt_empresa = $pdo->prepare("SELECT nome FROM empresas WHERE id = ?");
        $stmt_empresa->execute([$empresa_id_para_editar]);
        $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $stmt_insert = $pdo->prepare(
                "INSERT INTO configuracoes_whitelabel (empresa_id, nome_empresa, logo_url, cor_variavel) 
                 VALUES (?, ?, 'imagens/verify-kyc.png', '#4f46e5')"
            );
            $stmt_insert->execute([$empresa_id_para_editar, $empresa['nome']]);
            
            // Recarrega
            $stmt->execute([$empresa_id_para_editar]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
?>
    <div class="container-fluid">
        <h2 class="mb-4">Configurações para: <?= htmlspecialchars($config['nome_empresa'] ?? 'Nova Empresa') ?></h2>

        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="configuracoes.php<?= $is_superadmin ? '?id=' . $empresa_id_para_editar : '' ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="empresa_id_para_editar" value="<?= $empresa_id_para_editar ?>">
                    
                    <div class="form-group mb-3">
                        <label for="nome_empresa">Nome da Empresa</label>
                        <input type="text" class="form-control" id="nome_empresa" name="nome_empresa" value="<?= htmlspecialchars($config['nome_empresa'] ?? '') ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="slug">Slug da URL (identificador para o link do parceiro)</label>
                        <div class="input-group">
                            <span class="input-group-text">cnpj.foconteudo.com.br/kyc_form.php?cliente=</span>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?= htmlspecialchars($config['slug'] ?? '') ?>"
                                   <?php if (!$is_superadmin && !empty($config['slug'])) echo 'readonly'; ?>
                                   placeholder="defina-o-slug-aqui">
                        </div>
                        <small class="form-text text-muted">
                            <?php if (empty($config['slug']) && !$is_superadmin): ?>
                                Defina o identificador único para seu link de KYC. Use apenas letras, números e hífens. **Esta ação só pode ser feita uma vez.**
                            <?php else: ?>
                                Este é o identificador único para seu link de KYC.
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="logo" class="form-label">Logotipo</label>
                        <input type="file" class="form-control" id="logo" name="logo">
                        <small class="form-text text-muted">Envie um novo arquivo para substituir o logo atual.</small>
                        <?php if (!empty($config['logo_url'])): ?>
                            <div class="mt-2">Logo Atual: <img src="<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo Atual" style="max-height: 50px; background-color: #f0f0f0; padding: 5px; border-radius: 4px;"></div>
                            <input type="hidden" name="current_logo" value="<?= htmlspecialchars($config['logo_url']) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="cor_variavel" class="form-label">Cor Principal</label>
                        <input type="color" class="form-control form-control-color" id="cor_variavel" name="cor_variavel" value="<?= htmlspecialchars($config['cor_variavel'] ?? '#4f46e5') ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label for="google_tag_manager_id">ID do Google Tag Manager</label>
                        <input type="text" class="form-control" id="google_tag_manager_id" name="google_tag_manager_id" value="<?= htmlspecialchars($config['google_tag_manager_id'] ?? '') ?>" placeholder="GTM-XXXXXX">
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Salvar Configurações</button>
                </form>
            </div>
        </div>

        <!-- Sistema de Captura e Conversão de Leads -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h4 class="mb-0"><i class="bi bi-funnel-fill text-primary"></i> Sistema de Captura e Conversão de Leads</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <strong>Sobre este sistema:</strong> 
                    Captura leads do seu site e converte automaticamente em clientes KYC com um único clique.
                </div>

                <div class="accordion" id="accordionLeadsSystem">
                    
                    <!-- 1. Formulário de Captura -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingCaptura">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCaptura" aria-expanded="true" aria-controls="collapseCaptura">
                                <i class="bi bi-clipboard-data me-2"></i> 1. Formulário de Captura de Leads
                            </button>
                        </h2>
                        <div id="collapseCaptura" class="accordion-collapse collapse show" aria-labelledby="headingCaptura" data-bs-parent="#accordionLeadsSystem">
                            <div class="accordion-body">
                                <p><strong>Página pública para capturar interessados em sua plataforma.</strong></p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-link-45deg"></i> Link Público:</h6>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control" id="leadFormUrl" 
                                                   value="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/lead_form.php' ?>" 
                                                   readonly>
                                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('leadFormUrl')">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <?php if (!empty($config_atual['slug'])): 
                                            $lead_form_whitelabel = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/lead_form.php?slug=' . urlencode($config_atual['slug']);
                                        ?>
                                        <div class="alert alert-success mb-2">
                                            <small><strong><i class="bi bi-tag-fill"></i> Link com sua marca:</strong></small>
                                            <div class="input-group input-group-sm mt-2">
                                                <input type="text" class="form-control font-monospace" id="leadFormWhitelabel" 
                                                       value="<?= htmlspecialchars($lead_form_whitelabel) ?>" 
                                                       readonly>
                                                <button class="btn btn-success" onclick="copyToClipboard('leadFormWhitelabel')" title="Copiar">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-gear"></i> API Webhook:</h6>
                                        <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/api_lead_webhook.php' ?></code>
                                    </div>
                                </div>

                                <!-- API Token Management Section -->
                                <div class="card mt-4 border-warning">
                                    <div class="card-header bg-warning bg-opacity-10">
                                        <h6 class="mb-0"><i class="bi bi-key-fill"></i> Token de Autenticação da API</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($config_atual && !empty($config_atual['api_token'])): 
                                            $api_url_base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/api_lead_webhook.php';
                                            $api_url_with_token = $api_url_base . '?token=' . urlencode($config_atual['api_token']);
                                        ?>
                                            <!-- URL pronta para usar (com token) -->
                                            <div class="alert alert-success mb-3">
                                                <h6 class="mb-2"><i class="bi bi-link-45deg"></i> URL Pronta para WordPress/Elementor:</h6>
                                                <div class="input-group">
                                                    <input type="text" class="form-control font-monospace small" id="apiUrlWithToken" 
                                                           value="<?= htmlspecialchars($api_url_with_token) ?>" 
                                                           readonly>
                                                    <button class="btn btn-success" onclick="copyToClipboard('apiUrlWithToken')" title="Copiar URL Completa">
                                                        <i class="bi bi-clipboard"></i> Copiar
                                                    </button>
                                                </div>
                                                <small class="text-muted mt-2 d-block">
                                                    <i class="bi bi-info-circle"></i> 
                                                    Use esta URL diretamente em formulários WordPress, Elementor, etc. O token já está incluído!
                                                    <br>
                                                    <a href="WORDPRESS_INTEGRATION.md" target="_blank" class="text-decoration-none">
                                                        <i class="bi bi-book"></i> Ver guia completo de integração WordPress
                                                    </a>
                                                </small>
                                            </div>
                                            
                                            <hr>
                                            
                                            <p class="mb-2"><strong>Seu Token de API (para uso avançado):</strong></p>
                                            <div class="input-group mb-3">
                                                <input type="password" class="form-control font-monospace" id="apiTokenField" 
                                                       value="<?= htmlspecialchars($config_atual['api_token']) ?>" 
                                                       readonly>
                                                <button class="btn btn-outline-secondary" onclick="toggleApiTokenVisibility()" id="toggleApiTokenBtn" title="Mostrar/Ocultar">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="copyToClipboard('apiTokenField')" title="Copiar">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="regenerateApiToken()" title="Gerar Novo Token">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        <i class="bi bi-info-circle"></i> 
                                                        <strong>Status:</strong> 
                                                        <?php if ($config_atual['api_token_ativo']): ?>
                                                            <span class="badge bg-success">Ativo</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Desativado</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        <i class="bi bi-speedometer2"></i> 
                                                        <strong>Rate Limit:</strong> <?= $config_atual['api_rate_limit'] ?? 100 ?> req/hora
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <?php if ($config_atual['api_ultimo_uso']): ?>
                                            <small class="text-muted d-block mt-2">
                                                <i class="bi bi-clock-history"></i> 
                                                Último uso: <strong><?= date('d/m/Y H:i', strtotime($config_atual['api_ultimo_uso'])) ?></strong>
                                            </small>
                                            <?php endif; ?>
                                            
                                            <div class="alert alert-info mt-3 mb-0">
                                                <small>
                                                    <strong>Como usar:</strong> Envie requisições POST para o webhook com este token no header:<br>
                                                    <code>Authorization: Bearer SEU_TOKEN_AQUI</code>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                <i class="bi bi-exclamation-triangle"></i> 
                                                <strong>Token não encontrado.</strong> Execute o script SQL <code>add_api_token_to_whitelabel.sql</code> para adicionar suporte a tokens.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <h6><i class="bi bi-card-checklist"></i> Campos Capturados:</h6>
                                    <ul>
                                        <li>Nome completo</li>
                                        <li>E-mail</li>
                                        <li>WhatsApp (com máscara automática)</li>
                                        <li>Empresa (opcional)</li>
                                        <li>Mensagem (opcional)</li>
                                        <li><strong>Rastreamento:</strong> UTM params, IP, origem</li>
                                    </ul>
                                </div>

                                <!-- API Token Section -->
                                <?php if ($config_atual && isset($config_atual['api_token'])): ?>
                                <div class="alert alert-warning mt-3">
                                    <h6><i class="bi bi-shield-lock"></i> Token de API (Autenticação)</h6>
                                    <p class="mb-2">Use este token para autenticar requisições ao webhook:</p>
                                    <div class="input-group mb-2">
                                        <input type="password" class="form-control font-monospace" id="apiToken" 
                                               value="<?= htmlspecialchars($config_atual['api_token']) ?>" 
                                               readonly>
                                        <button class="btn btn-outline-secondary" onclick="toggleApiToken()" id="toggleTokenBtn">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('apiToken')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Envie no header: <code>Authorization: Bearer SEU_TOKEN</code>
                                        <br>
                                        Rate Limit: <strong><?= $config_atual['api_rate_limit'] ?? 100 ?> requisições/hora</strong>
                                        <?php if ($config_atual['api_ultimo_uso']): ?>
                                        | Último uso: <strong><?= date('d/m/Y H:i', strtotime($config_atual['api_ultimo_uso'])) ?></strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <a href="lead_form.php" class="btn btn-primary btn-sm" target="_blank">
                                        <i class="bi bi-box-arrow-up-right"></i> Visualizar Formulário
                                    </a>
                                    <a href="api_lead_webhook.php" class="btn btn-outline-info btn-sm" target="_blank">
                                        <i class="bi bi-code-square"></i> Documentação API
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Gestão de Leads -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingGestao">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGestao" aria-expanded="false" aria-controls="collapseGestao">
                                <i class="bi bi-people me-2"></i> 2. Gestão de Leads (CRM)
                            </button>
                        </h2>
                        <div id="collapseGestao" class="accordion-collapse collapse" aria-labelledby="headingGestao" data-bs-parent="#accordionLeadsSystem">
                            <div class="accordion-body">
                                <p><strong>Painel administrativo para visualizar e gerenciar todos os leads capturados.</strong></p>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6><i class="bi bi-bar-chart"></i> Dashboard de Leads</h6>
                                                <p class="small mb-2">Estatísticas em tempo real:</p>
                                                <ul class="small mb-0">
                                                    <li>Total de leads</li>
                                                    <li>Por status: Novo, Contatado, Qualificado, Convertido, Perdido</li>
                                                    <li>Filtros: data, status, busca</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6><i class="bi bi-lightning"></i> Ações Rápidas</h6>
                                                <p class="small mb-2">Diretamente da lista:</p>
                                                <ul class="small mb-0">
                                                    <li><strong>Enviar Formulário KYC</strong> (1 clique)</li>
                                                    <li>Contato via WhatsApp</li>
                                                    <li>Enviar email</li>
                                                    <li>Mudar status</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="leads.php" class="btn btn-success btn-sm">
                                        <i class="bi bi-funnel-fill"></i> Acessar Gestão de Leads
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Conversão Lead → Cliente KYC -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingConversao">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConversao" aria-expanded="false" aria-controls="collapseConversao">
                                <i class="bi bi-arrow-left-right me-2"></i> 3. Conversão Automática: Lead → Cliente KYC
                            </button>
                        </h2>
                        <div id="collapseConversao" class="accordion-collapse collapse" aria-labelledby="headingConversao" data-bs-parent="#accordionLeadsSystem">
                            <div class="accordion-body">
                                <p><strong>Sistema automático que transforma um lead em cliente KYC com um único clique.</strong></p>

                                <div class="alert alert-success">
                                    <h6><i class="bi bi-magic"></i> Processo Automático:</h6>
                                    <ol class="mb-0">
                                        <li>Verifica se email já existe como cliente</li>
                                        <li>Cria novo cliente ou reutiliza existente</li>
                                        <li>Gera <strong>token seguro de 64 caracteres</strong> (válido 30 dias)</li>
                                        <li>Cria URL personalizada: <code>kyc_form.php?slug=<?= htmlspecialchars($config['slug'] ?? 'empresa') ?>&token=abc123...</code></li>
                                        <li>Registra ação no histórico</li>
                                        <li>Atualiza status automaticamente: <span class="badge bg-primary">Novo</span> → <span class="badge bg-info">Contatado</span></li>
                                    </ol>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-shield-check"></i> Segurança:</h6>
                                        <ul class="small">
                                            <li>Token: 64 chars hex (2^256 possibilidades)</li>
                                            <li>Impossível de adivinhar por força bruta</li>
                                            <li>Expira automaticamente em 30 dias</li>
                                            <li>Acesso direto sem login/senha</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-clipboard-check"></i> Cliente Preenche:</h6>
                                        <ul class="small">
                                            <li>Dados da empresa (CNPJ automático)</li>
                                            <li>Upload de documentos</li>
                                            <li>Sócios/representantes</li>
                                            <li>Branding personalizado (seu logo/cores)</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="lead_detail.php" class="btn btn-outline-primary btn-sm disabled">
                                        <i class="bi bi-file-earmark-check"></i> Ver exemplo de conversão
                                    </a>
                                    <a href="FLUXO_LEAD_TO_KYC.md" class="btn btn-outline-secondary btn-sm" target="_blank">
                                        <i class="bi bi-book"></i> Documentação Completa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Captura Universal de Formulários -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingUniversalCapture">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUniversalCapture" aria-expanded="false" aria-controls="collapseUniversalCapture">
                                <i class="bi bi-magic me-2"></i> 4. Captura Universal de Formulários
                            </button>
                        </h2>
                        <div id="collapseUniversalCapture" class="accordion-collapse collapse" aria-labelledby="headingUniversalCapture" data-bs-parent="#accordionLeadsSystem">
                            <div class="accordion-body">
                                <div class="alert alert-success">
                                    <i class="bi bi-stars"></i> <strong>Capture TODOS os formulários do seu site com apenas 1 linha de código!</strong>
                                    <p class="mb-0 mt-2 small">O script universal detecta automaticamente qualquer formulário HTML e envia os leads para o sistema Verify.</p>
                                </div>

                                <h6><i class="bi bi-check-circle-fill text-success"></i> Funciona com:</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <ul class="small">
                                            <li>✅ Contact Form 7 (WordPress)</li>
                                            <li>✅ Elementor Pro Forms</li>
                                            <li>✅ WPForms</li>
                                            <li>✅ Gravity Forms</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="small">
                                            <li>✅ HTML puro</li>
                                            <li>✅ Formulários AJAX</li>
                                            <li>✅ Popups e modals</li>
                                            <li>✅ Qualquer formulário HTML!</li>
                                        </ul>
                                    </div>
                                </div>

                                <?php if (!empty($config_atual['api_token'])): 
                                    // Gera a URL completa do script com o token já configurado
                                    $script_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
                                                  $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . 
                                                  '/verify-universal-form-capture.js?token=' . urlencode($config_atual['api_token']);
                                ?>
                                
                                <div class="alert alert-success mb-3">
                                    <strong><i class="bi bi-stars"></i> Instalação Simplificada:</strong>
                                    <p class="mb-2 small">Cole este código no seu site e pronto! O token já está configurado automaticamente.</p>
                                </div>

                                <div class="card mb-3 border-success">
                                    <div class="card-header bg-success text-white">
                                        <strong><i class="bi bi-code-slash"></i> Código Pronto para Usar</strong>
                                    </div>
                                    <div class="card-body">
                                        <p class="small mb-2"><strong>WordPress:</strong> Adicione no <code>header.php</code> do tema ou use plugin "Insert Headers and Footers"</p>
                                        <p class="small mb-2"><strong>HTML/PHP:</strong> Cole antes do <code>&lt;/body&gt;</code> em todas as páginas</p>
                                        
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control font-monospace small" id="universalScriptTag" 
                                                   value='<?= htmlspecialchars('<script src="' . $script_url . '"></script>') ?>' 
                                                   readonly>
                                            <button class="btn btn-success" onclick="copyToClipboard('universalScriptTag')">
                                                <i class="bi bi-clipboard"></i> Copiar
                                            </button>
                                        </div>
                                        
                                        <div class="alert alert-info mb-0 small">
                                            <i class="bi bi-info-circle"></i> <strong>Atenção:</strong> O script carrega diretamente do servidor Verify com seu token já configurado. Não precisa baixar nem editar nada!
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-success">
                                    <strong><i class="bi bi-graph-up"></i> Integração Automática com Analytics:</strong>
                                    <p class="small mb-2">O script detecta automaticamente se o site do cliente já tem Google Analytics (GA4) ou Google Tag Manager instalado e envia os eventos para eles!</p>
                                    <div class="row small">
                                        <div class="col-md-6">
                                            <strong>✅ Google Analytics (GA4):</strong>
                                            <ul class="mb-0 mt-1">
                                                <li>Evento: <code>generate_lead</code></li>
                                                <li>Categoria: Lead</li>
                                                <li>Detecção automática via <code>gtag()</code></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>✅ Google Tag Manager:</strong>
                                            <ul class="mb-0 mt-1">
                                                <li>Evento: <code>lead_captured</code></li>
                                                <li>Variáveis: lead_id, form_url</li>
                                                <li>Detecção automática via <code>dataLayer</code></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="alert alert-light mt-2 mb-0 small">
                                        <i class="bi bi-info-circle-fill"></i> <strong>Importante:</strong> O GTM/Analytics configurado <u>nesta seção</u> (<code><?= !empty($config_atual['google_tag_manager_id']) ? htmlspecialchars($config_atual['google_tag_manager_id']) : 'não configurado' ?></code>) é usado apenas nos <strong>formulários whitelabel</strong> (lead_form.php e kyc_form.php). O script universal usa o GTM/GA4 que já está no site do cliente.
                                    </div>
                                </div>

                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> <strong>Token não encontrado!</strong>
                                    <p class="mb-0 small">Você precisa gerar um token API primeiro na seção <strong>"Sistema de Leads API"</strong> acima.</p>
                                </div>
                                <?php endif; ?>

                                <div class="alert alert-info">
                                    <strong><i class="bi bi-lightbulb"></i> Como funciona:</strong>
                                    <ol class="mb-0 small mt-2">
                                        <li>O script monitora <strong>todos</strong> os formulários da página</li>
                                        <li>Detecta automaticamente os campos (nome, email, telefone, empresa)</li>
                                        <li>Quando o usuário envia o formulário, captura os dados</li>
                                        <li>Envia para o webhook Verify em segundo plano</li>
                                        <li>Envia eventos para GA4/GTM se disponíveis no site</li>
                                        <li>Não interfere no funcionamento normal do formulário</li>
                                    </ol>
                                </div>

                                <?php if (!empty($config_atual['api_token'])): ?>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="test_universal_capture.php" class="btn btn-lg btn-warning" target="_blank">
                                        <i class="bi bi-bug-fill"></i> Testar Captura em Tempo Real
                                    </a>
                                    <small class="text-muted text-center">
                                        <i class="bi bi-info-circle"></i> Abre página interativa para testar o script e ver logs em tempo real
                                    </small>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="small"><i class="bi bi-gear-fill"></i> Configuração Avançada:</h6>
                                        <ul class="small">
                                            <li>Ignorar formulários específicos</li>
                                            <li>Personalizar detecção de campos</li>
                                            <li>Integração com GA4/GTM</li>
                                            <li>Eventos JavaScript customizados</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="small"><i class="bi bi-book"></i> Documentação:</h6>
                                        <a href="UNIVERSAL_FORM_CAPTURE.md" class="btn btn-outline-primary btn-sm" target="_blank">
                                            <i class="bi bi-file-earmark-text"></i> Ver Documentação Completa
                                        </a>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-3 mb-0">
                                    <small><i class="bi bi-exclamation-triangle"></i> <strong>Importante:</strong> Certifique-se de ter um token API ativo antes de usar o script universal.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Integrações -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingIntegracoes">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIntegracoes" aria-expanded="false" aria-controls="collapseIntegracoes">
                                <i class="bi bi-plug me-2"></i> 5. Integrações e Rastreamento
                            </button>
                        </h2>
                        <div id="collapseIntegracoes" class="accordion-collapse collapse" aria-labelledby="headingIntegracoes" data-bs-parent="#accordionLeadsSystem">
                            <div class="accordion-body">
                                <h6><i class="bi bi-check-circle-fill text-success"></i> Integrações Ativas:</h6>
                                <ul>
                                    <li><strong>Google Analytics:</strong> Evento <code>generate_lead</code> enviado automaticamente</li>
                                    <li><strong>Google Tag Manager:</strong> 
                                        <?php if (!empty($config['google_tag_manager_id'])): ?>
                                            <span class="badge bg-success">Configurado: <?= htmlspecialchars($config['google_tag_manager_id']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Não configurado</span>
                                            <small class="text-muted">(Configure acima no formulário)</small>
                                        <?php endif; ?>
                                    </li>
                                    <li><strong>Histórico Completo:</strong> Todas ações registradas em <code>leads_historico</code></li>
                                    <li><strong>Log de Webhooks:</strong> Auditoria completa em <code>leads_webhook_log</code></li>
                                </ul>

                                <h6 class="mt-4"><i class="bi bi-clock-history text-primary"></i> Integrações Futuras:</h6>
                                <ul class="text-muted">
                                    <li>📧 Email automático com PHPMailer</li>
                                    <li>🔗 Webhook para CRM externo (HubSpot, Salesforce, RD Station)</li>
                                    <li>📊 Dashboard de conversão detalhado</li>
                                    <li>🔔 Notificações em tempo real</li>
                                </ul>

                                <div class="alert alert-info mt-3">
                                    <strong>Parâmetros UTM Rastreados:</strong>
                                    <ul class="mb-0 small">
                                        <li><code>utm_source</code> - Fonte do tráfego (ex: google, facebook)</li>
                                        <li><code>utm_medium</code> - Meio (ex: cpc, email, social)</li>
                                        <li><code>utm_campaign</code> - Campanha específica</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Documentação -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingDocs">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDocs" aria-expanded="false" aria-controls="collapseDocs">
                                <i class="bi bi-book me-2"></i> 6. Documentação e Suporte
                            </button>
                        </h2>
                        <div id="collapseDocs" class="accordion-collapse collapse" aria-labelledby="headingDocs" data-bs-parent="#accordionLeadsSystem">
                            <div class="accordion-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-diagram-3 fs-1 text-primary"></i>
                                                <h6 class="mt-2">Fluxo Completo</h6>
                                                <p class="small text-muted">Lead → Cliente KYC</p>
                                                <a href="FLUXO_LEAD_TO_KYC.md" class="btn btn-outline-primary btn-sm" target="_blank">
                                                    <i class="bi bi-box-arrow-up-right"></i> Abrir
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-shield-lock fs-1 text-success"></i>
                                                <h6 class="mt-2">Segurança</h6>
                                                <p class="small text-muted">Tokens e Proteções</p>
                                                <a href="SEGURANCA_TOKENS.md" class="btn btn-outline-success btn-sm" target="_blank">
                                                    <i class="bi bi-box-arrow-up-right"></i> Abrir
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-database-gear fs-1 text-warning"></i>
                                                <h6 class="mt-2">Instalação SQL</h6>
                                                <p class="small text-muted">Script do Banco</p>
                                                <a href="INSTALL_LEADS_SYSTEM.sql" class="btn btn-outline-warning btn-sm" target="_blank" download>
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-light mt-4">
                                    <h6><i class="bi bi-lightbulb"></i> Links Rápidos:</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="small">
                                                <li><a href="leads.php">📊 Painel de Leads</a></li>
                                                <li><a href="lead_form.php" target="_blank">📝 Formulário Público</a></li>
                                                <li><a href="kyc_form.php" target="_blank">📋 Formulário KYC</a></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="small">
                                                <li><a href="kyc_list.php">🔍 Análise KYC</a></li>
                                                <li><a href="dashboard_analytics.php">📈 Dashboard Analytics</a></li>
                                                <li><a href="consulta_cnpj.php">🏢 Consulta CNPJ</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
    function copyToClipboard(elementId) {
        const input = document.getElementById(elementId);
        input.select();
        document.execCommand('copy');
        
        // Feedback visual
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i>';
        setTimeout(() => { btn.innerHTML = originalHTML; }, 1500);
    }
    
    function toggleApiTokenVisibility() {
        const input = document.getElementById('apiTokenField');
        const btn = document.getElementById('toggleApiTokenBtn');
        
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
    }
    
    function regenerateApiToken() {
        if (!confirm('⚠️ ATENÇÃO: Gerar um novo token irá invalidar o token atual.\n\nTodos os sistemas que usam o token antigo vão parar de funcionar até você atualizar com o novo token.\n\nDeseja continuar?')) {
            return;
        }
        
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        fetch('ajax_regenerate_api_token.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('apiTokenField').value = data.new_token;
                alert('✅ Novo token gerado com sucesso!\n\nNão esqueça de atualizar seus sistemas com o novo token.');
                location.reload();
            } else {
                alert('❌ Erro ao gerar novo token: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('❌ Erro ao gerar novo token. Tente novamente.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    }
    
    function toggleApiToken() {
        const input = document.getElementById('apiToken');
        const btn = document.getElementById('toggleTokenBtn');
        
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i class="bi bi-eye"></i>';
        }
    }
    </script>
<?php
} else {
    echo "<div class='container'><div class='alert alert-warning'>Nenhuma empresa associada à sua conta.</div></div>";
}

require_once 'footer.php';
?>