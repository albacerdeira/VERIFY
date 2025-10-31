<?php
// filepath: cliente_registro.php

// 1. CARREGAMENTO E CONFIGURAÇÃO INICIAL
// =======================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. FUNÇÃO DE ENVIO DE E-MAIL
// ===========================
function enviarEmailVerificacao($email_cliente, $nome_cliente, $verify_url, $nome_empresa, $cor_variavel, $logo_url): void {
    $mail = new PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM_EMAIL, $nome_empresa);
    $mail->addAddress($email_cliente, $nome_cliente);

    $mail->isHTML(true);
    $mail->Subject = 'Ative sua conta em ' . $nome_empresa;

    // Garante que o logo use uma URL absoluta no corpo do e-mail.
    $full_logo_url = SITE_URL . '/' . ltrim($logo_url, '/');

    $body = "<body style='font-family: Arial, sans-serif; background-color: #f4f7f9; padding: 20px;'>";
    $body .= "<div style='max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 8px; padding: 40px;'>";
    $body .= "<div style='text-align: center; margin-bottom: 30px;'><img src='" . $full_logo_url . "' alt='Logo " . htmlspecialchars($nome_empresa) . "' style='max-height: 50px;'></div>";
    $body .= "<h2 style='color: #333333;'>Seja bem-vindo(a), " . htmlspecialchars($nome_cliente) . "!</h2>";
    $body .= "<p style='color: #555555; line-height: 1.6;'>Falta pouco para você começar a usar a plataforma " . htmlspecialchars($nome_empresa) . ".</p>";
    $body .= "<p style='color: #555555; line-height: 1.6;'>Por favor, clique no botão abaixo para verificar seu endereço de e-mail e ativar sua conta.</p>";
    $body .= "<div style='text-align: center; margin: 30px 0;'><a href='" . $verify_url . "' style='background-color: " . $cor_variavel . "; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Ativar Minha Conta</a></div>";
    $body .= "<p style='color: #777777; font-size: 0.9em;'>Se o botão não funcionar, copie e cole este link no seu navegador:</p>";
    $body .= "<p style='color: #aaaaaa; font-size: 0.8em; word-break: break-all;'>" . $verify_url . "</p>";
    $body .= "<hr style='border: none; border-top: 1px solid #eeeeee; margin: 30px 0;'>";
    $body .= "<p style='color: #999999; font-size: 0.8em;'>Se você não se cadastrou em nosso site, por favor, ignore este e-mail.</p>";
    $body .= "</div></body>";

    $mail->Body = $body;
    $mail->AltBody = "Olá " . htmlspecialchars($nome_cliente) . ". Para ativar sua conta, copie e cole este link no seu navegador: " . $verify_url;

    $mail->send();
}

// 3. LÓGICA WHITELABEL E DE NEGÓCIO
// ===================================

$error = '';
$success = '';
$slug_contexto = $_GET['cliente'] ?? null;
$id_parceiro = null; // ID do parceiro whitelabel

// Lógica Whitelabel inicial (para visualização da página)
$nome_empresa = 'Verify KYC';
$cor_variavel = '#4f46e5';
$logo_url = 'imagens/verify-kyc.png';

if ($slug_contexto && isset($pdo)) {
    try {
        // --- CORREÇÃO APLICADA AQUI ---
        // Usando a tabela 'configuracoes_whitelabel' que existe no seu banco.
        $stmt_wl = $pdo->prepare("SELECT id, nome_empresa, cor_variavel, logo_url FROM configuracoes_whitelabel WHERE slug = ?");
        $stmt_wl->execute([$slug_contexto]);
        $config_wl = $stmt_wl->fetch(PDO::FETCH_ASSOC);
        if ($config_wl) {
            $id_parceiro = $config_wl['id']; // Captura o ID do parceiro para usar na inserção
            $nome_empresa = $config_wl['nome_empresa'];
            $cor_variavel = $config_wl['cor_variavel'];
            $logo_url = $config_wl['logo_url'];
        }
    } catch(PDOException $e) {
        // Não usar die() aqui para não travar a página, apenas registrar o erro.
        error_log("Erro ao buscar whitelabel no registro: " . $e->getMessage());
    }
}

// Processamento do Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($pdo)) {
        die("Falha na conexão com o banco de dados.");
    }

    $pdo->beginTransaction();

    try {
        // Coleta de dados
        $nome = trim($_POST['nome_completo'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $confirma_senha = $_POST['confirma_senha'] ?? '';

        // Validações
        if (empty($nome) || empty($cpf) || empty($email) || empty($senha)) { throw new Exception('Todos os campos marcados com * são obrigatórios.'); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new Exception('Formato de e-mail inválido.'); }
        if (strlen($senha) < 6) { throw new Exception('A senha deve ter pelo menos 6 caracteres.'); }
        if ($senha !== $confirma_senha) { throw new Exception('As senhas não conferem.'); }

        // Lógica para salvar a selfie
        $selfie_path = null;
        if (!empty($_POST['selfie_base64'])) {
            $data = explode(',', $_POST['selfie_base64'])[1];
            $data = base64_decode($data);
            $upload_dir = 'uploads/selfies/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = uniqid('selfie_', true) . '.jpg';
            $selfie_path = $upload_dir . $filename;
            file_put_contents($selfie_path, $data);
        } elseif (isset($_FILES['selfie_upload']) && $_FILES['selfie_upload']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['selfie_upload'];

            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $file_type = mime_content_type($file['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Tipo de arquivo inválido para a selfie. Apenas JPG, PNG e PDF são permitidos.');
            }

            $upload_dir = 'uploads/selfies/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = uniqid('selfie_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $selfie_path = $upload_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $selfie_path)) {
                throw new Exception('Falha ao salvar o arquivo da selfie.');
            }
        }

        if (empty($selfie_path)) {
            throw new Exception('A selfie é obrigatória.');
        }

        // Verifica se o e-mail já existe
        $stmt_check = $pdo->prepare("SELECT id FROM kyc_clientes WHERE email = ?");
        $stmt_check->execute([$email]);
        if ($stmt_check->fetch()) { throw new Exception('Este e-mail já está cadastrado.'); }
        
        // Preparação dos dados para inserção
        $codigo_verificacao = substr(bin2hex(random_bytes(5)), 0, 10);
        $codigo_expira_em = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        $hash_senha = password_hash($senha, PASSWORD_DEFAULT);

        // Inserção com o ID do parceiro whitelabel
        $stmt_insert = $pdo->prepare(
            "INSERT INTO kyc_clientes (nome_completo, cpf, email, password, selfie_path, codigo_verificacao, codigo_expira_em, email_verificado, status, whitelabel_parceiro_id) " .
            "VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pendente', ?)"
        );
        $stmt_insert->execute([$nome, $cpf, $email, $hash_senha, $selfie_path, $codigo_verificacao, $codigo_expira_em, $id_parceiro]);
        
        // Construção da URL de verificação com contexto whitelabel
        $verify_url = SITE_URL . "/cliente_verificacao.php?codigo=" . urlencode($codigo_verificacao);
        if ($slug_contexto) {
            $verify_url .= "&cliente=" . urlencode($slug_contexto);
        }

        // Envio do e-mail de verificação
        enviarEmailVerificacao($email, $nome, $verify_url, $nome_empresa, $cor_variavel, $logo_url);

        $pdo->commit();
        $success = "Cadastro realizado com sucesso! Um e-mail de verificação foi enviado para " . htmlspecialchars($email) . ".";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = "Erro no registro: " . $e->getMessage();
        error_log("Falha no registro de cliente: " . $e->getMessage());
    }
}

$page_title = 'Cadastro - ' . htmlspecialchars($nome_empresa);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary-color: <?= htmlspecialchars($cor_variavel) ?>; }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f9; }
        .register-container { max-width: 500px; }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-color); border-color: var(--primary-color); filter: brightness(0.9); }
        .btn-outline-primary { color: var(--primary-color); border-color: var(--primary-color); }
        .btn-outline-primary:hover { background-color: var(--primary-color); border-color: var(--primary-color); color: white; }
        .link { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .link:hover { text-decoration: underline; }
        .custom-file-input { display: flex; align-items: center; }
        .custom-file-input .file-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: -5px;
            margin-bottom: 20px;
        }
        .secure-badge .bi {
            color: #198754; 
            margin-right: 8px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
    <div class="register-container card p-4 shadow-sm">
        <div class="text-center mb-4">
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars($nome_empresa) ?>" style="max-height: 60px; object-fit: contain;">
            <h2 class="mt-3">Cadastro de Conta</h2>
            <div class="secure-badge">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Cadastro seguro</span>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) . ($slug_contexto ? "?cliente=" . htmlspecialchars($slug_contexto) : "") ?>" novalidate enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nome_completo" class="form-label">Nome Completo *</label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" required>
            </div>
            <div class="mb-3">
                <label for="cpf" class="form-label">CPF *</label>
                <input type="text" class="form-control" id="cpf" name="cpf" required autocomplete="off">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail *</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="senha" class="form-label">Senha (mínimo 6 caracteres) *</label>
                <input type="password" class="form-control" id="senha" name="senha" required>
            </div>
            <div class="mb-3">
                <label for="confirma_senha" class="form-label">Confirmar Senha *</label>
                <input type="password" class="form-control" id="confirma_senha" name="confirma_senha" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Selfie *</label>
                <div id="selfie-container" class="border p-3 rounded bg-light">
                    <p class="text-muted small text-center mb-3">Tire uma selfie nítida segurando seu documento de identidade (RG ou CNH) ao lado do rosto.</p>
                    <div id="camera-view" style="display: none;" class="text-center">
                        <video id="video" width="100%" height="auto" autoplay playsinline class="rounded border"></video>
                        <button type="button" id="snap" class="btn btn-primary mt-2 w-100">Tirar Foto</button>
                    </div>
                    <div id="preview-view" style="display: none;" class="text-center">
                        <canvas id="canvas" style="display:none;"></canvas>
                        <img id="photo-preview" src="#" alt="Prévia da selfie" class="img-fluid rounded border">
                        <button type="button" id="retake" class="btn btn-secondary mt-2 w-100">Tirar Outra Foto</button>
                    </div>
                    <div id="initial-view" class="text-center">
                        <button type="button" id="start-camera" class="btn btn-outline-primary w-75 mb-2">Selfie</button>
                        <p class="text-center text-muted my-2">ou</p>
                        <label for="selfie_upload" class="form-label">Enviar arquivo (imagem ou PDF):</label>
                        <div class="custom-file-input justify-content-center">
                            <label for="selfie_upload" class="btn btn-secondary">Escolher Arquivo</label>
                            <span class="file-name ms-2 text-muted">Nenhum arquivo selecionado</span>
                            <input class="form-control" type="file" id="selfie_upload" name="selfie_upload" accept="image/png, image/jpeg, application/pdf" style="display:none;">
                        </div>
                    </div>
                    <input type="hidden" name="selfie_base64" id="selfie_base64">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Criar conta de cadastro</button>
        </form>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <p class="text-muted">
                Já tem uma conta de cadastro? 
                <a href="cliente_login.php<?= $slug_contexto ? "?cliente=" . htmlspecialchars($slug_contexto) : "" ?>" class="link">
                    Faça login aqui
                </a>
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const cpfInput = document.getElementById('cpf');
        if (cpfInput) {
            cpfInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                value = value.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            });
        }

        const startCameraButton = document.getElementById('start-camera');
        const snapButton = document.getElementById('snap');
        const retakeButton = document.getElementById('retake');
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const photoPreview = document.getElementById('photo-preview');
        const selfieBase64Input = document.getElementById('selfie_base64');
        const selfieUploadInput = document.getElementById('selfie_upload');
        const cameraView = document.getElementById('camera-view');
        const previewView = document.getElementById('preview-view');
        const initialView = document.getElementById('initial-view');
        let stream = null;

        startCameraButton.addEventListener('click', async () => {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                    video.srcObject = stream;
                    initialView.style.display = 'none';
                    previewView.style.display = 'none';
                    cameraView.style.display = 'block';
                    selfieUploadInput.value = '';
                } catch (err) {
                    alert("Não foi possível acessar a câmera. Verifique as permissões ou envie um arquivo.");
                }
            } else {
                alert("Seu navegador não suporta acesso à câmera. Por favor, envie um arquivo.");
            }
        });

        snapButton.addEventListener('click', () => {
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg');
            photoPreview.src = dataUrl;
            selfieBase64Input.value = dataUrl;
            cameraView.style.display = 'none';
            previewView.style.display = 'block';
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });

        retakeButton.addEventListener('click', () => {
            selfieBase64Input.value = '';
            startCameraButton.click();
        });

        selfieUploadInput.addEventListener('change', () => {
            const fileNameDisplay = document.querySelector('#initial-view .file-name');
            if (selfieUploadInput.files.length > 0) {
                fileNameDisplay.textContent = selfieUploadInput.files[0].name;
                fileNameDisplay.classList.remove('text-muted');
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                cameraView.style.display = 'none';
                previewView.style.display = 'none';
                selfieBase64Input.value = '';
            } else {
                fileNameDisplay.textContent = 'Nenhum arquivo selecionado';
                fileNameDisplay.classList.add('text-muted');
            }
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
