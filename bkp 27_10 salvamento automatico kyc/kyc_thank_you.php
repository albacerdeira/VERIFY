<?php
session_start();
require 'config.php'; // Inclui a conexão com o banco de dados

// --- LÓGICA DE BRANDING PARA A PÁGINA DE AGRADECIMENTO ---
$partner_id = $_SESSION['thank_you_partner_id'] ?? null;

// --- CORREÇÃO: Alterado logo padrão ---
$logo_url = 'imagens/verify-kyc.png'; // Seu logo principal como fallback
$nome_empresa_parceira = 'Nossa Plataforma';
$cor_primaria = '#4f46e5';

if ($partner_id) {
    $stmt = $pdo->prepare("SELECT nome_empresa, logo_url, cor_variavel FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$partner_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $logo_url = $config['logo_url'] ?: $logo_url;
        $nome_empresa_parceira = $config['nome_empresa'];
        $cor_primaria = $config['cor_variavel'] ?: $cor_primaria;
    }
}

$page_title = 'Obrigado por seu Envio';
$submission_id = $_SESSION['submission_id'] ?? null;
$flash_message = $_SESSION['flash_message'] ?? 'Seu formulário foi enviado com sucesso e está em análise.';

unset($_SESSION['submission_id'], $_SESSION['flash_message'], $_SESSION['thank_you_partner_id']);
?>

<!-- --- CORREÇÃO: Layout minimalista autônomo --- -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($nome_empresa_parceira) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f9;
        }
        .btn-primary {
            background-color: <?= htmlspecialchars($cor_primaria) ?>;
            border-color: <?= htmlspecialchars($cor_primaria) ?>;
        }
        .btn-primary:hover {
            filter: brightness(0.9);
            background-color: <?= htmlspecialchars($cor_primaria) ?>;
            border-color: <?= htmlspecialchars($cor_primaria) ?>;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm text-center p-4 p-md-5">
                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo da Empresa" style="max-width: 150px; margin: 0 auto 2rem auto; background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
                    
                    <h2 class="mb-3" style="color: <?= htmlspecialchars($cor_primaria); ?>;">Formulário Enviado com Sucesso!</h2>
                    
                    <p class="lead text-muted"><?php echo htmlspecialchars($flash_message); ?></p>
                    
                    <?php if ($submission_id): ?>
                        <p><strong>Número da sua submissão: #<?php echo htmlspecialchars($submission_id); ?></strong></p>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <p>Nossa equipe de compliance irá analisar as informações e os documentos enviados.</p>
                    <p>Entraremos em contato em breve pelo e-mail cadastrado no formulário.</p>
                    
                    <div class="mt-5">
                        <?php if (isset($_SESSION['user_id'])): // Se o usuário estiver logado, o botão leva para o dashboard ?>
                            <a href="dashboard.php" class="btn btn-primary btn-lg">Voltar ao Painel</a>
                        <?php elseif (isset($_SESSION['cliente_id'])): // Se for um cliente logado ?>
                             <a href="cliente_dashboard.php" class="btn btn-primary btn-lg">Voltar ao seu Painel</a>
                        <?php else: // Se não estiver logado, o botão pode levar para a página de login ou outra página pública ?>
                            <p class="text-muted small">Se você já tem uma conta, pode acompanhar o status por lá.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
