<?php
// Todas as variáveis já foram preparadas pelo bootstrap.php

// --- INÍCIO DA CORREÇÃO WHITELABEL ---
// O bootstrap.php define os padrões (ex: 'Verify KYC'), mas precisamos
// sobrepor com a configuração específica do admin/analista logado.
if ($is_logged_in && isset($_SESSION['user_empresa_id']) && isset($pdo)) {
    try {
        // Busca a configuração whitelabel vinculada ao 'empresa_id' do usuário logado
        $stmt_wl_admin = $pdo->prepare("SELECT logo_url, cor_variavel FROM configuracoes_whitelabel WHERE empresa_id = ?");
        $stmt_wl_admin->execute([$_SESSION['user_empresa_id']]);
        $config_wl_admin = $stmt_wl_admin->fetch(PDO::FETCH_ASSOC);
        
        if ($config_wl_admin) {
            // Sobrepõe as variáveis padrão vindas do bootstrap
            if (!empty($config_wl_admin['logo_url'])) {
                $logo_url = $config_wl_admin['logo_url'];
            }
            if (!empty($config_wl_admin['cor_variavel'])) {
                $cor_variavel = $config_wl_admin['cor_variavel'];
            }
        }
    } catch (PDOException $e) {
        // Ignora se der erro, apenas usa o padrão
        error_log("Erro ao buscar whitelabel do admin no header.php: " . $e->getMessage());
    }
}
// --- FIM DA CORREÇÃO WHITELABEL ---


// --- CORREÇÃO DE CACHE-BUSTING PARA O LOGO ---
// Garante que o path_prefix (definido no bootstrap) seja aplicado
// Usamos ltrim() para garantir que não tenhamos barras duplas (//)
$logo_path_servidor = $path_prefix . ltrim(htmlspecialchars($logo_url), '/');
$logo_cache_buster = file_exists($logo_path_servidor) ? '?v=' . filemtime($logo_path_servidor) : '';
$logo_url_final = $path_prefix . ltrim(htmlspecialchars($logo_url), '/') . $logo_cache_buster;
// --- FIM DA CORREÇÃO ---

?>
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title><?= htmlspecialchars($page_title_final) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='<?= $path_prefix ?>style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>'>
    
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-KXR83VZ6');</script>
    <?php if (!empty($_SESSION['google_tag_manager_id'])): 
        $gtm_id_cliente = htmlspecialchars($_SESSION['google_tag_manager_id']);
    ?>
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?= $gtm_id_cliente ?>');</script>
        <?php endif; ?>

    <style>
        /* A cor primária agora é definida corretamente com base no login do admin */
        :root { --primary-color: <?= htmlspecialchars($cor_variavel) ?>; } 
        .sidebar-nav-link.active {
            background-color: var(--primary-color);
            color: #ffffff;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KXR83VZ6"
    height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    <?php if (!empty($_SESSION['google_tag_manager_id'])): 
        $gtm_id_cliente = htmlspecialchars($_SESSION['google_tag_manager_id']);
    ?>
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $gtm_id_cliente ?>"
        height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager Cliente"></iframe></noscript>
        <?php endif; ?>

    <?php if ($is_logged_in): // Layout para usuários logados ?>
    <div class='page-container'>
        <!-- Botão toggle para mobile -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <span></span><span></span><span></span>
        </button>
        
        <!-- Overlay para fechar sidebar em mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        <aside class='sidebar' id='sidebar'>
          <div class='sidebar-header'>
              <a href='<?= $path_prefix ?>dashboard_analytics.php'><img src='<?= $logo_url_final ?>' alt='Logo da Empresa' class='sidebar-logo'></a>
                 <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Minimizar Sidebar">
                    <i class="bi bi-arrow-bar-left collapse-icon"></i>
                 </button>
            </div>
            <div class='sidebar-user'>
                <span class='sidebar-user-name'><?= htmlspecialchars($user_nome) ?></span>
                <span class='sidebar-user-role'><?= htmlspecialchars(ucfirst($user_role)) ?></span>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                <?php // --- INÍCIO DA NOVA LÓGICA DE MENU ---

                // Menu do Superadmin
                if ($is_superadmin): ?>
                    <a href="<?= $path_prefix ?>dashboard_analytics.php" class="sidebar-nav-link <?= in_array($current_page_base, ['dashboard.php', 'dashboard_analytics.php']) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard</span>
                    </a>
                    
                        <div class="sidebar-section">
                            <button class="sidebar-section-header">
                                <i class="bi bi-search"></i> <span class="link-text">Consultas</span>
                                <i class="bi bi-chevron-down chevron"></i>
                            </button>
                            <div class="sidebar-section-content">
                                <a href="<?= $path_prefix ?>consulta_cnpj.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consulta_cnpj.php') ? 'active' : '' ?>">
                                    <i class="bi bi-building"></i> <span class="link-text">Consulta CNPJ</span>
                                </a>
                                <a href="<?= $path_prefix ?>consultas.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consultas.php') ? 'active' : '' ?>">
                                    <i class="bi bi-clock-history"></i> <span class="link-text">Histórico</span>
                                </a>
                            </div>
                        </div>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-clipboard-check"></i> <span class="link-text">KYC</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>kyc_form.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'kyc_form.php') ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-plus"></i> <span class="link-text">Enviar KYC</span>
                            </a>
                            <a href="<?= $path_prefix ?>kyc_list.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['kyc_list.php', 'kyc_evaluate.php']) ? 'active' : '' ?>">
                                <i class="bi bi-search"></i> <span class="link-text">Análise KYC</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-folder"></i> <span class="link-text">Gestão</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>empresas.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['empresas.php', 'empresa_edit.php']) ? 'active' : '' ?>">
                                <i class="bi bi-building"></i> <span class="link-text">Empresas</span>
                            </a>
                            <a href="<?= $path_prefix ?>usuarios.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['usuarios.php', 'usuario_edit.php']) ? 'active' : '' ?>">
                                <i class="bi bi-people"></i> <span class="link-text">Usuários</span>
                            </a>
                            <a href="<?= $path_prefix ?>clientes.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['clientes.php', 'cliente_edit.php']) ? 'active' : '' ?>">
                                <i class="bi bi-person-badge"></i> <span class="link-text">Clientes</span>
                            </a>
                        </div>
                    </div>
                    
                    <a href="<?= $path_prefix ?>configuracoes.php" class="sidebar-nav-link <?= ($current_page_base == 'configuracoes.php') ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i> <span class="link-text">Configurações</span>
                    </a>
                    <a href="<?= $path_prefix ?>admin_import.php" class="sidebar-nav-link <?= ($current_page_base == 'admin_import.php') ? 'active' : '' ?>">
                        <i class="bi bi-cloud-upload"></i> <span class="link-text">Importar Listas</span>
                    </a>

                <?php // Menu do Admin
                elseif ($is_admin): ?>
                    <a href="<?= $path_prefix ?>dashboard_analytics.php" class="sidebar-nav-link <?= in_array($current_page_base, ['dashboard.php', 'dashboard_analytics.php']) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard</span>
                    </a>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-search"></i> <span class="link-text">Consultas</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>consulta_cnpj.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consulta_cnpj.php') ? 'active' : '' ?>">
                                <i class="bi bi-building"></i> <span class="link-text">Consulta CNPJ</span>
                            </a>
                            <a href="<?= $path_prefix ?>consultas.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consultas.php') ? 'active' : '' ?>">
                                <i class="bi bi-clock-history"></i> <span class="link-text">Histórico</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-clipboard-check"></i> <span class="link-text">KYC</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>kyc_form.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'kyc_form.php') ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-plus"></i> <span class="link-text">Enviar KYC</span>
                            </a>
                            <a href="<?= $path_prefix ?>kyc_list.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['kyc_list.php', 'kyc_evaluate.php']) ? 'active' : '' ?>">
                                <i class="bi bi-search"></i> <span class="link-text">Análise KYC</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-folder"></i> <span class="link-text">Gestão</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>usuarios.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['usuarios.php', 'usuario_edit.php']) ? 'active' : '' ?>">
                                <i class="bi bi-people"></i> <span class="link-text">Usuários</span>
                            </a>
                            <a href="<?= $path_prefix ?>clientes.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['clientes.php', 'cliente_edit.php']) ? 'active' : '' ?>">
                                <i class="bi bi-person-badge"></i> <span class="link-text">Clientes</span>
                            </a>
                        </div>
                    </div>
                    
                    <a href="<?= $path_prefix ?>configuracoes.php" class="sidebar-nav-link <?= ($current_page_base == 'configuracoes.php') ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i> <span class="link-text">Configurações</span>
                    </a>
                

                <?php // Menu do Analista
                elseif ($is_analista): ?>
                    <a href="<?= $path_prefix ?>dashboard_analytics.php" class="sidebar-nav-link <?= in_array($current_page_base, ['dashboard.php', 'dashboard_analytics.php']) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard</span>
                    </a>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-search"></i> <span class="link-text">Consultas</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>consulta_cnpj.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consulta_cnpj.php') ? 'active' : '' ?>">
                                <i class="bi bi-building"></i> <span class="link-text">Consulta CNPJ</span>
                            </a>
                            <a href="<?= $path_prefix ?>consultas.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consultas.php') ? 'active' : '' ?>">
                                <i class="bi bi-clock-history"></i> <span class="link-text">Histórico</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-clipboard-check"></i> <span class="link-text">KYC</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>kyc_form.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'kyc_form.php') ? 'active' : '' ?>">
                                <i class="bi bi-file-earmark-plus"></i> <span class="link-text">Enviar KYC</span>
                            </a>
                            <a href="<?= $path_prefix ?>kyc_list.php" class="sidebar-nav-link sidebar-sub-link <?= in_array($current_page_base, ['kyc_list.php', 'kyc_evaluate.php']) ? 'active' : '' ?>">
                                <i class="bi bi-search"></i> <span class="link-text">Análise KYC</span>
                            </a>
                        </div>
                    </div>
                    
                    <a href="<?= $path_prefix ?>clientes.php" class="sidebar-nav-link <?= in_array($current_page_base, ['clientes.php', 'cliente_edit.php']) ? 'active' : '' ?>">
                        <i class="bi bi-person-badge"></i> <span class="link-text">Clientes</span>
                    </a>

                <?php // Menu Padrão (outros usuários logados)
                else: ?>
                    <a href="<?= $path_prefix ?>dashboard_analytics.php" class="sidebar-nav-link <?= in_array($current_page_base, ['dashboard.php', 'dashboard_analytics.php']) ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> <span class="link-text">Dashboard</span>
                    </a>
                    
                    <div class="sidebar-section">
                        <button class="sidebar-section-header">
                            <i class="bi bi-search"></i> <span class="link-text">Consultas</span>
                            <i class="bi bi-chevron-down chevron"></i>
                        </button>
                        <div class="sidebar-section-content">
                            <a href="<?= $path_prefix ?>consulta_cnpj.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consulta_cnpj.php') ? 'active' : '' ?>">
                                <i class="bi bi-building"></i> <span class="link-text">Consulta CNPJ</span>
                            </a>
                            <a href="<?= $path_prefix ?>consultas.php" class="sidebar-nav-link sidebar-sub-link <?= ($current_page_base == 'consultas.php') ? 'active' : '' ?>">
                                <i class="bi bi-clock-history"></i> <span class="link-text">Histórico</span>
                            </a>
                        </div>
                    </div>
                    
                    <a href="<?= $path_prefix ?>kyc_form.php" class="sidebar-nav-link <?= ($current_page_base == 'kyc_form.php') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-plus"></i> <span class="link-text">Enviar KYC</span>
                    </a>
                <?php endif; 

                // Link de Sair aparece para todos os usuários logados ?>
                <a href="<?= $path_prefix ?>logout.php" class="sidebar-nav-link logout-link">
                    <i class="bi bi-box-arrow-right icon-logout"></i> <span class="link-text">Sair</span>
                </a>
                <?php // --- FIM DA NOVA LÓGICA DE MENU --- ?>
            </nav>
        </aside>
        <main class='main-content'>

    <?php else: // Layout para páginas públicas (incluindo portal do cliente) ?>
    <div class="public-page-container">
        <header class='public-header'>
            <nav class='navbar navbar-expand-lg navbar-light bg-white border-bottom' aria-label="Navegação pública">
                <div class="container">
                    <a class='navbar-brand' href="cliente_login.php">
                         <img src='<?= $logo_url_final ?>' alt='Logo' style='height: 40px;'>
                    </a>
                    <div class="collapse navbar-collapse justify-content-end">
                        <ul class="navbar-nav">
                            <li class="nav-item"><a class='nav-link' href='cliente_login.php'><strong>Portal do Cliente</strong></a></li>
                            <li class="nav-item"><a class='nav-link' href='login.php'>Login Administrativo</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        <main class='public-main-content container mt-4 mb-5'>
    <?php endif; ?>