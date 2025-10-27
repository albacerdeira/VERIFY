<?php
$page_title = 'Redefinir Senha';
require_once 'bootstrap.php';

$error = null;
$success = null;
$token = $_GET['token'] ?? '';
$token_valido = false;

if (empty($token)) {
    $error = "Token não fornecido ou inválido.";
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires > NOW()");
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch();

        if ($reset_request) {
            $token_valido = true;
        } else {
            $error = "Token inválido ou expirado. Por favor, solicite um novo link de recuperação.";
        }
    } catch (Exception $e) {
        error_log("Erro em reset_password.php: " . $e->getMessage());
        $error = "Ocorreu um erro ao validar seu token.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($password) || strlen($password) < 8) {
        $error = "A senha deve ter no mínimo 8 caracteres.";
    } elseif ($password !== $password_confirm) {
        $error = "As senhas não coincidem.";
    } else {
        try {
            $pdo->beginTransaction();
            
            $email = $reset_request['email'];
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Tenta atualizar na tabela de usuários
            $stmt_user = $pdo->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
            $stmt_user->execute([$hashed_password, $email]);
            $updated = $stmt_user->rowCount() > 0;

            // Se não atualizou, tenta na tabela de superadmin
            if (!$updated) {
                $stmt_super = $pdo->prepare("UPDATE superadmin SET password = ? WHERE email = ?");
                $stmt_super->execute([$hashed_password, $email]);
                $updated = $stmt_super->rowCount() > 0;
            }

            if ($updated) {
                // Deleta o token para não ser reutilizado
                $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt_delete->execute([$email]);
                
                $pdo->commit();
                $success = "Sua senha foi redefinida com sucesso! Você já pode fazer o login.";
                $token_valido = false; // Esconde o formulário após o sucesso
            } else {
                throw new Exception("Não foi possível encontrar a conta para atualizar.");
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Erro ao redefinir senha: " . $e->getMessage());
            $error = "Ocorreu um erro ao redefinir sua senha. Tente novamente.";
        }
    }
}

require_once 'header.php';
?>

<div class="container" style="max-width: 500px;">
    <div class="card">
        <div class="card-body">
            <h3 class="card-title text-center mb-4">Redefinir Senha</h3>

            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if ($token_valido): ?>
            <form action="reset_password.php?token=<?= htmlspecialchars($token) ?>" method="post">
                <div class="form-group mb-3">
                    <label for="password" class="form-label">Nova Senha</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                </div>
                <div class="form-group mb-3">
                    <label for="password_confirm" class="form-label">Confirmar Nova Senha</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Salvar Nova Senha</button>
            </form>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-secondary">Ir para o Login</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
