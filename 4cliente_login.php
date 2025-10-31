<?php
session_start();

if (isset($_SESSION['cliente_id'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    session_start();
}

require_once 'config.php';

// A lógica de Whitelabel para exibir a marca na página de login permanece a mesma.
$nome_empresa = 'Verify KYC';
$cor_variavel = '#4f46e5';
$logo_url = 'imagens/verify-kyc.png';
$slug_contexto = $_GET['cliente'] ?? null;
if ($slug_contexto && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT nome_empresa, cor_variavel, logo_url FROM configuracoes_whitelabel WHERE slug = ?");
        $stmt->execute([$slug_contexto]);
        $config = $stmt->fetch();
        if ($config) {
            $nome_empresa = $config['nome_empresa'];
            $cor_variavel = $config['cor_variavel'];
            $logo_url = $config['logo_url'];
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar whitelabel: " . $e->getMessage());
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            throw new Exception('Por favor, preencha todos os campos.');
        }

        // PASSO 1: Buscar o cliente APENAS pelo e-mail. A lógica de portal não importa aqui.
        $stmt = $pdo->prepare("SELECT id, nome_completo, password, email, email_verificado, status, whitelabel_parceiro_id FROM kyc_clientes WHERE email = ?");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();

        // PASSO 2: Verificar a senha.
        if (!$cliente || !password_verify($senha, $cliente['password'])) {
            throw new Exception('Credenciais inválidas.');
        }

        // PASSO 3: Verificar status da conta.
        if (!$cliente['email_verificado']) {
            throw new Exception('Sua conta ainda não foi verificada. Por favor, verifique seu e-mail.');
        }
        if ($cliente['status'] !== 'ativo') {
            throw new Exception('Sua conta está com status \'' . htmlspecialchars($cliente['status']) . '\'. Contate o suporte.');
        }

        // PASSO 4: Autenticação bem-sucedida. Configurar a sessão.
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nome'] = $cliente['nome_completo'];
        $_SESSION['cliente_email'] = $cliente['email'];
        
        // PASSO 5: Redirecionamento Inteligente para o Dashboard CORRETO.
        $redirect_url = 'cliente_dashboard.php';
        if (!empty($cliente['whitelabel_parceiro_id'])) {
            // Se o cliente pertence a um parceiro, buscamos o slug para a URL.
            $stmt_slug = $pdo->prepare("SELECT slug FROM configuracoes_whitelabel WHERE id = ?");
            $stmt_slug->execute([$cliente['whitelabel_parceiro_id']]);
            $parceiro = $stmt_slug->fetch();

            if ($parceiro && !empty($parceiro['slug'])) {
                $redirect_url .= '?cliente=' . $parceiro['slug'];
            }
        }
        
        header('Location: ' . $redirect_url);
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

$page_title = 'Login - ' . htmlspecialchars($nome_empresa);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Adicionado link para Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary-color: <?= htmlspecialchars($cor_variavel) ?>; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; margin: 1rem; }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header img { max-height: 50px; margin-bottom: 1rem; object-fit: contain; }
        .form-group { margin-bottom: 1.25rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        .btn-primary { background-color: var(--primary-color); color: white; padding: 0.85rem; border: none; border-radius: 4px; width: 100%; font-size: 1rem; cursor: pointer; font-weight: 600; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; text-align: center; font-size: 0.9em; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .text-center { text-align: center; }
        .text-muted { color: #6b7280; font-size: 0.9rem; }
        .mt-4 { margin-top: 1.5rem; }
        .link { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .secure-platform-badge { display: flex; align-items: center; justify-content: center; color: #6b7280; font-size: 0.85rem; margin-top: 1.5rem; }
        .secure-platform-badge .bi { font-size: 1.1rem; margin-right: 0.5rem; color: #16a34a; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo de <?= htmlspecialchars($nome_empresa) ?>">
            <h2>Cadastro de cliente</h2>
            <p> Insira suas credenciais </p>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . ($slug_contexto ? "?cliente=" . htmlspecialchars($slug_contexto) : "") ?>" novalidate>
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <div class="form-group" style="text-align: right;">
                <a href="cliente_recuperar_senha.php<?= $slug_contexto ? "?cliente=" . htmlspecialchars($slug_contexto) : "" ?>" class="link">
                    Esqueceu sua senha?
                </a>
            </div>
            <button type="submit" class="btn-primary">Entrar</button>
        </form>

        <div class="text-center mt-4">
            <p class="text-muted">
                Não tem uma conta de cadastro? 
                <a href="cliente_registro.php<?= $slug_contexto ? "?cliente=" . htmlspecialchars($slug_contexto) : "" ?>" class="link">
                    Crie uma agora
                </a>
            </p>
        </div>

        <!-- Ícone e texto "Plataforma segura" adicionados -->
        <div class="secure-platform-badge">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Plataforma segura</span>
        </div>
        
    </div>
</body>
</html>