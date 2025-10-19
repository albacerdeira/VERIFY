<?php
// Todas as variáveis já foram preparadas pelo bootstrap.php
?>
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title><?= htmlspecialchars($page_title_final) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='<?= $path_prefix ?>style.css'>
    
    <!-- Google Tag Manager (Global - Dono da Aplicação) -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-KXR83VZ6');</script>
    <!-- End Google Tag Manager (Global) -->
    
    <?php if (!empty($_SESSION['google_tag_manager_id'])): 
        $gtm_id_cliente = htmlspecialchars($_SESSION['google_tag_manager_id']);
    ?>
        <!-- Google Tag Manager (Cliente) -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?= $gtm_id_cliente ?>');</script>
        <!-- End Google Tag Manager (Cliente) -->
    <?php endif; ?>

    <style>
        :root { --primary-color: <?= htmlspecialchars($cor_variavel) ?>; }
        .main-header .main-nav a.active {
            background-color: #ffffff;
            color: var(--primary-color);
            font-weight: 600;
            border-radius: 5px;
            padding: 5px 10px;
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) (Global - Dono da Aplicação) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KXR83VZ6"
    height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) (Global) -->
    
    <?php if (!empty($_SESSION['google_tag_manager_id'])): 
        $gtm_id_cliente = htmlspecialchars($_SESSION['google_tag_manager_id']);
    ?>
        <!-- Google Tag Manager (noscript) (Cliente) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $gtm_id_cliente ?>"
        height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager Cliente"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) (Cliente) -->
    <?php endif; ?>

    <?php if ($is_logged_in): // Layout para usuários logados ?>
    <div class='page-container'>
        <header class='main-header'>
            <div class='header-top'>
                 <a href='<?= $path_prefix ?>dashboard.php'><img src='<?= $path_prefix . htmlspecialchars($logo_url) ?>' alt='Logo da Empresa' class='company-logo'></a>
                 <div class='user-info'>
                    <span>Olá, <?= htmlspecialchars($user_nome) ?> (<?= htmlspecialchars(ucfirst($user_role)) ?>)</span>
                </div>
            </div>
            <nav class='main-nav'>
                 <a href='<?= $path_prefix ?>dashboard.php' class='<?= ($current_page_base == 'dashboard.php') ? 'active' : '' ?>'>Dashboard</a>
                 <a href='<?= $path_prefix ?>consultas.php' class='<?= ($current_page_base == 'consultas.php') ? 'active' : '' ?>'>Histórico</a>
                 <a href='<?= $path_prefix ?>kyc_form.php' class='<?= ($current_page_base == 'kyc_form.php') ? 'active' : '' ?>'>Enviar KYC</a>
                
                <?php if ($is_superadmin || $is_admin || $is_analista): ?>
                    <a href='<?= $path_prefix ?>kyc_list.php' class='<?= (in_array($current_page_base, ['kyc_list.php', 'kyc_evaluate.php'])) ? 'active' : '' ?>'>Análise KYC</a>
                <?php endif; ?>

                <?php if ($is_superadmin || $is_admin): ?>
                    <a href='<?= $path_prefix ?>usuarios.php' class='<?= ($current_page_base == 'usuarios.php') ? 'active' : '' ?>'>Usuários</a>
                    <a href='<?= $path_prefix ?>configuracoes.php' class='<?= ($current_page_base == 'configuracoes.php') ? 'active' : '' ?>'>Configurações</a>
                <?php endif; ?>
                
                <?php if ($is_superadmin): ?>
                    <a href='<?= $path_prefix ?>empresas.php' class='<?= ($current_page_base == 'empresas.php') ? 'active' : '' ?>'>Empresas</a>
                <?php endif; ?>
                
                <a href='<?= $path_prefix ?>logout.php'>Sair</a>
            </nav>
        </header>
        <main class='main-content'>

    <?php else: // Layout para páginas públicas ?>
    <div class="public-page-container">
        <header class='public-header'>
            <nav class='navbar navbar-expand-lg navbar-light bg-white border-bottom'>
                <div class="container">
                    <a class='navbar-brand' href="login.php">
                        <img src='<?= $path_prefix . htmlspecialchars($logo_url) ?>' alt='Logo' style='height: 40px;'>
                    </a>
                    <div class="collapse navbar-collapse justify-content-end">
                        <ul class="navbar-nav">
                            <li class="nav-item"><a class='nav-link' href='kyc_form.php'><strong>Enviar KYC</strong></a></li>
                            <li class="nav-item"><a class='nav-link' href='login.php'>Login</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        <main class='public-main-content container mt-4 mb-5'>
            <?php 
            if (isset($_SESSION['flash_message'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
                unset($_SESSION['flash_message']);
            }
            if (isset($_SESSION['flash_error'])) {
                echo '<div class="alert alert-danger">' . $_SESSION['flash_error'] . '</div>';
                unset($_SESSION['flash_error']);
            }
            ?>
        </main>
    </div>
    <?php endif; ?>
</body>
</html>