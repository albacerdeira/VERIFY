<?php
// Reenvia email de confirmação para cliente
require_once 'bootstrap.php';

// Apenas superadmin e admin
if (!$is_superadmin && !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Lê dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$cliente_id = $data['cliente_id'] ?? null;

if (!$cliente_id) {
    echo json_encode(['success' => false, 'message' => 'ID do cliente não fornecido']);
    exit;
}

try {
    // Busca dados do cliente
    $stmt = $pdo->prepare("
        SELECT kc.*, 
               w.slug, 
               w.nome_empresa, 
               w.cor_variavel, 
               w.logo_url
        FROM kyc_clientes kc
        LEFT JOIN configuracoes_whitelabel w ON w.empresa_id = kc.id_empresa_master
        WHERE kc.id = ?
    ");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit;
    }
    
    // Verifica permissão (admin só pode para clientes da sua empresa)
    if ($is_admin && $cliente['id_empresa_master'] != $user_empresa_id) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão para este cliente']);
        exit;
    }
    
    // Verifica se já está verificado
    if ($cliente['email_verificado']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Este cliente já verificou o email'
        ]);
        exit;
    }
    
    // Verifica se tem código de verificação
    if (empty($cliente['codigo_verificacao'])) {
        // Gera novo código se não existir
        $novo_codigo = substr(bin2hex(random_bytes(5)), 0, 10);
        $codigo_expira_em = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');
        
        $stmt_update = $pdo->prepare(
            "UPDATE kyc_clientes SET codigo_verificacao = ?, codigo_expira_em = ? WHERE id = ?"
        );
        $stmt_update->execute([$novo_codigo, $codigo_expira_em, $cliente_id]);
        
        $cliente['codigo_verificacao'] = $novo_codigo;
        $cliente['codigo_expira_em'] = $codigo_expira_em;
    }
    
    // Configurações whitelabel
    $nome_empresa = $cliente['nome_empresa'] ?? 'Verify KYC';
    $cor_variavel = $cliente['cor_variavel'] ?? '#4f46e5';
    $logo_url = $cliente['logo_url'] ?? 'imagens/verify-kyc.png';
    $slug = $cliente['slug'] ?? null;
    
    // Constrói URL de verificação
    $verify_url = SITE_URL . "/cliente_verificacao.php?codigo=" . urlencode($cliente['codigo_verificacao']);
    if ($slug) {
        $verify_url .= "&cliente=" . urlencode($slug);
    }
    
    // Envia email
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
    $mail->addAddress($cliente['email'], $cliente['nome_completo']);

    $mail->isHTML(true);
    $mail->Subject = 'Ative sua conta em ' . $nome_empresa;

    $full_logo_url = SITE_URL . '/' . ltrim($logo_url, '/');

    $body = "<body style='font-family: Arial, sans-serif; background-color: #f4f7f9; padding: 20px;'>";
    $body .= "<div style='max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 8px; padding: 40px;'>";
    $body .= "<div style='text-align: center; margin-bottom: 30px;'><img src='" . $full_logo_url . "' alt='Logo " . htmlspecialchars($nome_empresa) . "' style='max-height: 50px;'></div>";
    $body .= "<h2 style='color: #333333;'>Olá, " . htmlspecialchars($cliente['nome_completo']) . "!</h2>";
    $body .= "<p style='color: #555555; line-height: 1.6;'>Você solicitou o reenvio do link de verificação de email.</p>";
    $body .= "<p style='color: #555555; line-height: 1.6;'>Por favor, clique no botão abaixo para verificar seu endereço de e-mail e ativar sua conta.</p>";
    $body .= "<div style='text-align: center; margin: 30px 0;'><a href='" . $verify_url . "' style='background-color: " . $cor_variavel . "; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Ativar Minha Conta</a></div>";
    $body .= "<p style='color: #777777; font-size: 0.9em;'>Se o botão não funcionar, copie e cole este link no seu navegador:</p>";
    $body .= "<p style='color: #aaaaaa; font-size: 0.8em; word-break: break-all;'>" . $verify_url . "</p>";
    $body .= "<p style='color: #999999; font-size: 0.85em; margin-top: 30px;'>Este link expira em 24 horas.</p>";
    $body .= "<hr style='border: none; border-top: 1px solid #eeeeee; margin: 30px 0;'>";
    $body .= "<p style='color: #999999; font-size: 0.8em;'>Se você não solicitou este email, pode ignorá-lo com segurança.</p>";
    $body .= "</div></body>";

    $mail->Body = $body;
    $mail->AltBody = "Olá " . htmlspecialchars($cliente['nome_completo']) . ". Para ativar sua conta, copie e cole este link no seu navegador: " . $verify_url;

    $mail->send();
    
    echo json_encode([
        'success' => true,
        'message' => 'Email de confirmação reenviado com sucesso para ' . htmlspecialchars($cliente['email'])
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao reenviar confirmação para cliente {$cliente_id}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao enviar email: ' . $e->getMessage()
    ]);
}
