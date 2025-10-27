<?php
$page_title = 'Recuperar Senha';
require_once 'bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, insira um endereço de e-mail válido.";
    } else {
        try {
            // Verifica se o e-mail existe em alguma das tabelas de usuário
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? UNION SELECT id FROM superadmin WHERE email = ?");
            $stmt->execute([$email, $email]);
            if ($stmt->fetch()) {
                // Gera um token seguro
                $token = bin2hex(random_bytes(50));
                // Calcula a expiração em UTC para evitar problemas de fuso horário
                $expires = new DateTime('NOW', new DateTimeZone('UTC'));
                $expires->add(new DateInterval('PT1H')); // Token expira em 1 hora

                // Salva o token no banco de dados
                $stmt_insert = $pdo->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
                $stmt_insert->execute([$email, $token, $expires->format('Y-m-d H:i:s')]);

                // Monta o link de recuperação
                $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $caminho_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $reset_link = "{$protocolo}://{$host}{$caminho_base}/reset_password.php?token={$token}";

                // Envia o e-mail usando PHPMailer
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Recuperação de Senha';
                $mail->Body    = "Olá,<br><br>Você solicitou a recuperação de sua senha. Clique no link abaixo para redefinir:<br><a href='{$reset_link}'>{$reset_link}</a><br><br>Se você não solicitou isso, por favor, ignore este e-mail.<br><br>Atenciosamente,<br>Equipe " . SMTP_FROM_NAME;
                $mail->AltBody = "Olá,\n\nPara redefinir sua senha, copie e cole o seguinte link no seu navegador:\n{$reset_link}\n\nSe você não solicitou isso, ignore este e-mail.";
                
                $mail->send();
            }
            // Mostra a mensagem de sucesso independentemente de o e-mail existir, para não revelar contas válidas.
            $success = "Se houver uma conta associada a este e-mail, um link de recuperação foi enviado.";

        } catch (Exception $e) {
            error_log("Erro em forgot_password.php: " . $e->getMessage());
            $error = "Não foi possível processar sua solicitação. Tente novamente mais tarde.";
        }
    }
}

require_once 'header.php';
?>

<div class="container" style="max-width: 500px;">
    <div class="card">
        <div class="card-body">
            <h3 class="card-title text-center mb-4">Recuperar Senha</h3>
            <p class="text-muted text-center mb-4">Insira seu e-mail e enviaremos um link para você voltar a acessar sua conta.</p>

            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if (!$success): ?>
            <form action="forgot_password.php" method="post">
                <div class="form-group mb-3">
                    <label for="email" class="form-label">Endereço de e-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Enviar Link de Recuperação</button>
            </form>
            <?php endif; ?>
            <div class="text-center mt-3">
                <a href="login.php">Voltar para o Login</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
