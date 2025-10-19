<?php
// Garante que a sessão seja iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

$error_message = '';

// Se o usuário já está logado, redireciona para o dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['email']) || empty($_POST['password'])) {
        $error_message = "Por favor, preencha o e-mail e a senha.";
    } else {
        $email = $_POST['email'];
        $password = $_POST['password'];

        try {
            // 1. Tenta autenticar como Superadmin primeiro
            $stmt_super = $pdo->prepare('SELECT id, nome, password FROM superadmin WHERE email = :email');
            $stmt_super->execute(['email' => $email]);
            $superadmin = $stmt_super->fetch(PDO::FETCH_ASSOC);

            if ($superadmin && password_verify($password, $superadmin['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $superadmin['id'];
                $_SESSION['user_nome'] = $superadmin['nome'];
                $_SESSION['user_role'] = 'superadmin'; // Role fixa para superadmin
                $_SESSION['empresa_id'] = null; // Superadmin não pertence a uma empresa específica
                
                // Para superadmin, as configurações de whitelabel podem ser genéricas ou não aplicáveis na sessão
                $_SESSION['nome_empresa'] = 'Painel Superadmin';
                $_SESSION['logo_url'] = 'uploads/logos/68df0c7e712ff-verify-kyc.png'; // Logo padrão
                $_SESSION['cor_variavel'] = '#222b5a'; // Cor padrão

                header('Location: dashboard.php');
                exit;
            }

            // 2. Se não for Superadmin, tenta autenticar como usuário normal
            $stmt_user = $pdo->prepare(
                'SELECT u.id, u.nome, u.password, u.role, u.empresa_id, ' .
                'cw.nome_empresa, cw.logo_url, cw.cor_variavel, cw.google_tag_manager_id ' .
                'FROM usuarios u ' .
                'LEFT JOIN configuracoes_whitelabel cw ON u.empresa_id = cw.empresa_id ' .
                'WHERE u.email = :email'
            );
            $stmt_user->execute(['email' => $email]);
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['empresa_id'] = $user['empresa_id']; // Garante que o empresa_id do usuário seja salvo
                $_SESSION['nome_empresa'] = $user['nome_empresa'];
                $_SESSION['logo_url'] = $user['logo_url'];
                $_SESSION['cor_variavel'] = $user['cor_variavel'];
                $_SESSION['google_tag_manager_id'] = $user['google_tag_manager_id'];

                header('Location: dashboard.php');
                exit;
            }

            // Se nenhuma das autenticações funcionar
            $error_message = "Credenciais inválidas.";

        } catch (PDOException $e) {
            error_log("Erro de login: " . $e->getMessage());
            $error_message = "Erro no sistema. Tente novamente.";
        }
    }
}
// Inclui o cabeçalho específico da página de login, que não tem o menu principal.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel de Controle</title>
    <link rel="stylesheet" href="login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-illustration">
                <img src="public/verify-kyc.png" alt="Logo da Empresa" class="logo-illustration">
                 <p>Plataforma de acesso seguro.</p>
            </div>
            <div class="login-form-wrapper">
                <h3>Member Login</h3>
                <?php if ($error_message): ?><div class="error-message"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
                <form action="login.php" method="post" class="login-form">
                    <div class="input-group">
                         <i data-feather="mail"></i>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="input-group">
                        <i data-feather="lock"></i>
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" class="btn-login">LOGIN</button>
                    <div class="form-links">
                        <a href="forgot_password.php" class="forgot-password">Esqueceu a senha?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
      feather.replace();
    </script>
</body>
</html>
