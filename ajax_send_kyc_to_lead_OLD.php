<?php
// Envia link do formulário KYC para um lead
require_once 'bootstrap.php';

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Analistas não podem enviar KYC para leads
if ($_SESSION['user_role'] === 'analista') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Lê dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$lead_id = $data['lead_id'] ?? null;
$metodo_envio = $data['metodo'] ?? 'link'; // 'link', 'email', 'whatsapp'

if (!$lead_id) {
    echo json_encode(['success' => false, 'message' => 'Lead ID não fornecido']);
    exit;
}

try {
    // Busca dados do lead
    $stmt = $pdo->prepare("
        SELECT l.*,
               e.nome as empresa_parceira_nome,
               w.slug as empresa_slug,
               w.nome_empresa as whitelabel_nome,
               w.cor_variavel as whitelabel_cor,
               w.logo_url as whitelabel_logo
        FROM leads l
        LEFT JOIN empresas e ON l.id_empresa_master = e.id
        LEFT JOIN configuracoes_whitelabel w ON w.empresa_id = l.id_empresa_master
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo json_encode(['success' => false, 'message' => 'Lead não encontrado']);
        exit;
    }
    
    // Verifica permissão (admin só pode enviar para leads da sua empresa)
    $user_empresa_id = $_SESSION['empresa_id'] ?? null;
    if ($_SESSION['user_role'] === 'administrador' && $lead['id_empresa_master'] != $user_empresa_id) {
        echo json_encode([
            'success' => false, 
            'message' => 'Sem permissão para este lead',
            'debug' => [
                'lead_empresa' => $lead['id_empresa_master'],
                'user_empresa' => $user_empresa_id,
                'user_role' => $_SESSION['user_role']
            ]
        ]);
        exit;
    }
    
    // Constrói URL do formulário de Registro do Cliente
    // O cliente_registro.php é onde o lead vai criar sua conta completa
    // (nome, email, senha, CPF, selfie, etc.)
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $base_url .= dirname($_SERVER['PHP_SELF']);
    
    // Usa cliente_registro.php que captura todos os dados do cliente
    // IMPORTANTE: Inclui lead_id para fazer a associação Lead → Cliente
    if ($lead['empresa_slug']) {
        $kyc_url = $base_url . "/cliente_registro.php?cliente=" . urlencode($lead['empresa_slug']) . "&lead_id=" . $lead_id;
    } else {
        $kyc_url = $base_url . "/cliente_registro.php?lead_id=" . $lead_id;
    }
    
    // Registra no histórico do lead
    $stmt_hist = $pdo->prepare("
        INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
        VALUES (?, ?, 'cadastro_enviado', ?, NOW())
    ");
    
    $descricao = "Link do formulário de registro gerado para {$lead['email']}.";
    $stmt_hist->execute([$lead_id, $_SESSION['user_id'], $descricao]);
    
    // Atualiza status do lead para "contatado" se ainda estiver como "novo"
    if ($lead['status'] === 'novo') {
        $stmt_status = $pdo->prepare("UPDATE leads SET status = 'contatado', updated_at = NOW() WHERE id = ?");
        $stmt_status->execute([$lead_id]);
        
        $stmt_hist2 = $pdo->prepare("
            INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
            VALUES (?, ?, 'status_alterado', 'Status alterado de \"novo\" para \"contatado\" (envio automático de KYC)', NOW())
        ");
        $stmt_hist2->execute([$lead_id, $_SESSION['user_id']]);
    }
    
    // ==================================================
    // ENVIO DO LINK CONFORME MÉTODO ESCOLHIDO
    // ==================================================
    
    $envio_realizado = false;
    $metodo_usado = '';
    
    if ($metodo_envio === 'email') {
        // Envia por email usando PHPMailer
        require_once 'PHPMailer/PHPMailer.php';
        require_once 'PHPMailer/SMTP.php';
        require_once 'PHPMailer/Exception.php';
        require_once 'includes/email_template.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Configuração do servidor SMTP (usa config.php)
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';

            // Remetente e destinatário
            $nome_empresa = $lead['whitelabel_nome'] ?? SMTP_FROM_NAME;
            $cor_empresa = $lead['whitelabel_cor'] ?? '#4f46e5';
            $logo_empresa = $lead['whitelabel_logo'] ?? 'imagens/verify-kyc.png';

            // Garante URL absoluta para o logo
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            $logo_url_completa = $base_url . '/' . ltrim($logo_empresa, '/');

            $mail->setFrom(SMTP_FROM_EMAIL, $nome_empresa);
            $mail->addAddress($lead['email'], $lead['nome']);

            // Conteúdo do email
            $mail->isHTML(true);
            $mail->Subject = '📋 Complete seu cadastro - ' . $nome_empresa;

            // Conteúdo principal usando ícones Bootstrap
            $conteudo = "
                <p>Recebemos seu interesse em nossos serviços! Para dar continuidade ao processo, precisamos que você complete seu cadastro em nossa plataforma.</p>

                <div class='info-box'>
                    <p><strong><i class='bi bi-clock'></i> Tempo estimado:</strong> 10-15 minutos</p>
                </div>

                <p><strong><i class='bi bi-list-check'></i> O que você vai precisar:</strong></p>
                <ul>
                    <li><i class='bi bi-building'></i> Dados da empresa (CNPJ, Razão Social)</li>
                    <li><i class='bi bi-person-badge'></i> Informações dos sócios</li>
                    <li><i class='bi bi-file-earmark-text'></i> Documentos para upload</li>
                    <li><i class='bi bi-shield-lock'></i> Criar uma senha de acesso segura</li>
                </ul>

                <div class='divider'></div>

                <p style='color: #6b7280; font-size: 14px;'>
                    <i class='bi bi-info-circle'></i>
                    Após completar o cadastro, você receberá um email de confirmação para ativar sua conta.
                </p>
            ";

            // Gera email com template profissional
            $mail->Body = gerarEmailTemplate([
                'titulo' => 'Complete seu Cadastro',
                'icone_titulo' => '📋',
                'nome_destinatario' => $lead['nome'],
                'conteudo_html' => $conteudo,
                'link_acao' => $kyc_url,
                'texto_botao' => 'Completar Cadastro Agora',
                'cor_primaria' => $cor_empresa,
                'logo_url' => $logo_url_completa,
                'nome_empresa' => $nome_empresa
            ]);

            $mail->AltBody = "Olá {$lead['nome']},\n\nPara completar seu cadastro, acesse: {$kyc_url}\n\nAtenciosamente,\n{$nome_empresa}";

            $mail->send();
            $envio_realizado = true;
            $metodo_usado = 'Email';
            
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #6f42c1; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                    .button { display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>📋 Complete seu Cadastro</h1>
                    </div>
                    <div class='content'>
                        <p>Olá <strong>{$lead['nome']}</strong>,</p>
                        
                        <p>Recebemos seu interesse em nossos serviços! Para dar continuidade ao processo, precisamos que você complete seu cadastro em nossa plataforma.</p>
                        
                        <p><strong>O que você precisa fazer?</strong><br>
                        Preencher o formulário de registro com os dados da sua empresa e criar sua conta de acesso.</p>
                        
                        <p style='text-align: center;'>
                            <a href='{$kyc_url}' class='button'>🚀 Completar Cadastro</a>
                        </p>
                        
                        <p><small>Ou copie e cole este link no seu navegador:<br>
                        <code>{$kyc_url}</code></small></p>
                        
                        <p>O preenchimento leva aproximadamente 10-15 minutos. Tenha em mãos os dados da sua empresa:</p>
                        <ul>
                            <li>📄 Dados da empresa (CNPJ, Razão Social)</li>
                            <li>👤 Informações de contato</li>
                            <li>� Criar uma senha de acesso</li>
                        </ul>
                        
                        <p>Em caso de dúvidas, entre em contato conosco.</p>
                        
                        <p>Atenciosamente,<br>
                        <strong>" . ($lead['whitelabel_nome'] ?? 'Equipe Verify') . "</strong></p>
                    </div>
                    <div class='footer'>
                        <p>Este é um email automático, por favor não responda.<br>
                        © " . date('Y') . " " . ($lead['whitelabel_nome'] ?? 'Verify KYC') . "</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = "Olá {$lead['nome']},\n\nPara completar seu cadastro, acesse: {$kyc_url}\n\nAtenciosamente,\n" . ($lead['whitelabel_nome'] ?? 'Equipe Verify');
            
            $mail->send();
            $envio_realizado = true;
            $metodo_usado = 'Email';
            
            // Registra envio no histórico
            $stmt_hist_envio = $pdo->prepare("
                INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
                VALUES (?, ?, 'email_enviado', 'Email com link de cadastro enviado para {$lead['email']}', NOW())
            ");
            $stmt_hist_envio->execute([$lead_id, $_SESSION['user_id']]);
            
        } catch (Exception $e) {
            error_log("Erro ao enviar email: " . $mail->ErrorInfo);
            // Continua mesmo se o email falhar, retorna o link manualmente
        }
        
    } elseif ($metodo_envio === 'whatsapp') {
        // Prepara mensagem para WhatsApp
        $whatsapp_numero = preg_replace('/\D/', '', $lead['whatsapp']);
        $mensagem_whatsapp = urlencode(
            "Olá *{$lead['nome']}*!\n\n" .
            "Para completar seu cadastro em nossa plataforma, acesse o formulário de registro:\n\n" .
            "{$kyc_url}\n\n" .
            "📝 _O preenchimento leva cerca de 10-15 minutos._\n\n" .
            "Atenciosamente,\n" .
            ($lead['whitelabel_nome'] ?? 'Equipe Verify')
        );
        
        $whatsapp_url = "https://wa.me/55{$whatsapp_numero}?text={$mensagem_whatsapp}";
        $envio_realizado = true;
        $metodo_usado = 'WhatsApp';
        
        // Registra no histórico
        $stmt_hist_envio = $pdo->prepare("
            INSERT INTO leads_historico (lead_id, usuario_id, acao, descricao, created_at)
            VALUES (?, ?, 'whatsapp_preparado', 'Link de WhatsApp gerado para envio de cadastro ao lead', NOW())
        ");
        $stmt_hist_envio->execute([$lead_id, $_SESSION['user_id']]);
    }
    
    // Resposta
    $response = [
        'success' => true,
        'message' => "Link de cadastro gerado com sucesso!",
        'kyc_url' => $kyc_url,
        'metodo_envio' => $metodo_envio
    ];
    
    if ($metodo_envio === 'email' && isset($envio_realizado) && $envio_realizado) {
        $response['email_enviado'] = true;
        $response['message'] = "✅ Email enviado com sucesso para {$lead['email']}!";
    } elseif ($metodo_envio === 'email') {
        $response['email_enviado'] = false;
        $response['message'] = "⚠️ Email configurado mas não enviado. Use o link abaixo:";
    } elseif ($metodo_envio === 'whatsapp' && isset($whatsapp_url)) {
        $response['whatsapp_url'] = $whatsapp_url;
        $response['message'] = "📱 Clique no botão para abrir WhatsApp e enviar a mensagem.";
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erro ao enviar link de cadastro para lead: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}
