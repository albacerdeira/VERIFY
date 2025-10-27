<?php
// Cabeçalho minimalista para clientes externos.
// As variáveis de whitelabel ($page_title, $nome_empresa_contexto, etc.)
// são definidas em bootstrap.php, que é chamado antes em kyc_form.php.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Formulário KYC') ?> - <?= htmlspecialchars($nome_empresa_contexto ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: <?= htmlspecialchars($cor_variavel_contexto ?? '#4f46e5') ?>; 
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f9;
        }
        .form-header-minimal {
            background-color: #fff;
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 2rem;
        }
        .form-header-minimal img {
            max-height: 50px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <header class="form-header-minimal">
        <?php if (!empty($logo_url_contexto)): ?>
            <img src="<?= htmlspecialchars($logo_url_contexto) ?>" alt="Logo de <?= htmlspecialchars($nome_empresa_contexto) ?>">
        <?php endif; ?>
    </header>
    <div class="container">
        <!-- O conteúdo do formulário será inserido aqui -->
    </div>
</body>
</html>