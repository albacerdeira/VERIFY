<?php
// Inicia a sessão como a primeira ação de todas.
session_start();

require_once 'config.php';

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO
// ================================
if (!isset($_SESSION['cliente_id'])) {
    header('Location: cliente_login.php');
    exit;
}

// 2. RECUPERAÇÃO DE DADOS DA SESSÃO
// ==================================
$nome_cliente = $_SESSION['cliente_nome'] ?? 'Cliente';
$cliente_id = $_SESSION['cliente_id'];

// 3. LÓGICA WHITELABEL BASEADA NO SLUG DA URL
// ==============================================

$slug_contexto = $_GET['cliente'] ?? null;
$nome_empresa = 'Sistema KYC';
$cor_variavel = '#4f46e5';
$logo_url = 'imagens/verify-kyc.png';

if ($slug_contexto) {
    try {
        $stmt = $pdo->prepare("SELECT nome_empresa, logo_url, cor_variavel FROM configuracoes_whitelabel WHERE slug = ?");
        $stmt->execute([$slug_contexto]);
        $config = $stmt->fetch();
        if ($config) {
            $nome_empresa = $config['nome_empresa'];
            $cor_variavel = $config['cor_variavel'];
            $logo_url = $config['logo_url'];
        }
    } catch (PDOException $e) {
        error_log("Erro whitelabel no dashboard do cliente: " . $e->getMessage());
    }
}

// 4. VERIFICAÇÃO DO STATUS DO KYC
// =================================

$kyc_status = null;
$kyc_id = null;
$error = '';
try {
    $stmt = $pdo->prepare("SELECT id, status FROM kyc_empresas WHERE cliente_id = ? ORDER BY data_criacao DESC LIMIT 1");
    $stmt->execute([$cliente_id]);
    $kyc = $stmt->fetch();
    if ($kyc) {
        $kyc_status = $kyc['status'];
        $kyc_id = $kyc['id'];
    }
} catch (PDOException $e) {
    $error = "Ocorreu um erro ao verificar o status do seu formulário.";
    error_log($e->getMessage());
}

// Título da página.
$page_title = 'Painel do Cliente';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($nome_empresa) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($cor_variavel) ?>;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .main-header { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header-logo img { max-height: 40px; object-fit: contain; }
        .header-user-menu { display: flex; align-items: center; }
        .header-user-menu span { margin-right: 1.5rem; color: #333; font-weight: 500; }
        .logout-link { color: var(--primary-color); text-decoration: none; font-weight: 500; padding: 0.5rem 1rem; border: 1px solid var(--primary-color); border-radius: 5px; transition: all 0.3s ease; }
        .logout-link:hover { background-color: var(--primary-color); color: #fff; }
        .dashboard-container { max-width: 900px; width: 100%; margin: 2rem auto; padding: 0 1rem; flex-grow: 1; }
        .card { border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-weight: 600; background-color: #f8f9fa; padding: 1rem 1.5rem; }
        .card-body { padding: 1.5rem; }
        .card-title { font-weight: 600; color: #333; }
        .btn-lg { padding: 0.8rem 1.5rem; font-size: 1.1rem; }
        .text-danger { color: #dc3545; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-logo">
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo de <?= htmlspecialchars($nome_empresa) ?>">
        </div>
        <div class="header-user-menu">
            <span>Olá, <strong><?= htmlspecialchars($cliente_nome) ?></strong>!</span>
            <a href="cliente_logout.php" class="logout-link">Sair</a>
        </div>
    </header>

    <main class="dashboard-container">
        <?php if ($error): ?>
            <p class="text-danger"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    Status do seu Cadastro KYC
                </div>
                <div class="card-body text-center">
                    <?php if (!$kyc_status): ?>
                        <h5 class="card-title">Nenhum cadastro iniciado</h5>
                        <p>Você ainda não iniciou seu processo de cadastro. Clique no botão abaixo para começar.</p>
                        <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-primary btn-lg">Iniciar Cadastro KYC</a>
                    <?php elseif ($kyc_status === 'Em Preenchimento'): ?>
                        <h5 class="card-title">Cadastro em Andamento</h5>
                        <p>Você já iniciou seu cadastro. Continue de onde parou.</p>
                        <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-primary btn-lg">Continuar Preenchimento</a>
                    <?php else: ?>
                        <h5 class="card-title">Seu status atual é: <strong><?= htmlspecialchars($kyc_status) ?></strong></h5>
                        <p>Nossa equipe está analisando suas informações. Entraremos em contato em breve.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
