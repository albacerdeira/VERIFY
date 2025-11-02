<?php
// Formulário de Captura de Leads Público
// Carrega whitelabel via parâmetro ?slug= ou ?cliente= ou usa padrão

require_once 'bootstrap.php';

// Lógica de Whitelabel (mesma do cliente_login.php)
$nome_empresa = 'Verify KYC';
$cor_primaria = '#6f42c1';
$logo_url = 'imagens/verify-kyc.png';
$api_token = null; // Token para envio

// Aceita tanto ?slug= quanto ?cliente= (compatibilidade)
$slug_contexto = $_GET['slug'] ?? $_GET['cliente'] ?? null;

if ($slug_contexto) {
    try {
        $stmt = $pdo->prepare("SELECT nome_empresa, cor_variavel, logo_url, api_token FROM configuracoes_whitelabel WHERE slug = ?");
        $stmt->execute([$slug_contexto]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config) {
            $nome_empresa = $config['nome_empresa'];
            $cor_primaria = $config['cor_variavel'];
            $logo_url = $config['logo_url'];
            $api_token = $config['api_token']; // Pega o token
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar whitelabel: " . $e->getMessage());
    }
}

// Pega a URL atual para rastreamento
$current_page = $_SERVER['REQUEST_URI'] ?? 'unknown';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quero conhecer a plataforma - <?= htmlspecialchars($nome_empresa) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: <?= $cor_primaria ?>;
        }
        
        body {
            background: linear-gradient(135deg, <?= $cor_primaria ?>15 0%, <?= $cor_primaria ?>05 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .lead-capture-container {
            width: 100%;
            max-width: 600px;
            padding: 2rem 1rem;
        }
        
        .lead-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .lead-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, <?= $cor_primaria ?>dd 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .lead-header img {
            max-height: 60px;
            margin-bottom: 1rem;
            filter: brightness(0) invert(1);
        }
        
        .lead-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .lead-header p {
            margin: 0;
            opacity: 0.95;
            font-size: 1rem;
        }
        
        .lead-body {
            padding: 2.5rem 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-label i {
            color: var(--primary-color);
            margin-right: 0.25rem;
        }
        
        .form-control,
        .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem <?= $cor_primaria ?>25;
        }
        
        .btn-submit {
            background: var(--primary-color);
            border: none;
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            background: <?= $cor_primaria ?>dd;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px <?= $cor_primaria ?>40;
        }
        
        .privacy-text {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .success-message {
            text-align: center;
            padding: 2rem;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 576px) {
            .lead-capture-container {
                padding: 1rem 0.5rem;
            }
            
            .lead-header {
                padding: 2rem 1.5rem;
            }
            
            .lead-body {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="lead-capture-container" id="leadCaptureForm">
    <div class="lead-card">
        <!-- Header -->
        <div class="lead-header">
            <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="<?= htmlspecialchars($nome_empresa) ?>">
            <?php endif; ?>
            <h3><i class="bi bi-rocket-takeoff"></i> Quero conhecer a plataforma!</h3>
            <p>Preencha o formulário e nossa equipe entrará em contato</p>
        </div>


        <!-- Body -->
        <div class="lead-body">
            <form id="leadForm">
                <!-- Nome -->
                <div class="mb-3">
                    <label for="lead_nome" class="form-label">
                        <i class="bi bi-person-fill"></i> Nome Completo <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="lead_nome" name="nome" required placeholder="Seu nome completo">
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="lead_email" class="form-label">
                        <i class="bi bi-envelope-fill"></i> E-mail <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" id="lead_email" name="email" required placeholder="seu@email.com">
                </div>

                <!-- WhatsApp -->
                <div class="mb-3">
                    <label for="lead_whatsapp" class="form-label">
                        <i class="bi bi-whatsapp"></i> WhatsApp <span class="text-danger">*</span>
                    </label>
                    <input type="tel" class="form-control" id="lead_whatsapp" name="whatsapp" required placeholder="(00) 00000-0000">
                    <small class="text-muted">Formato: (00) 00000-0000</small>
                </div>

                <!-- Empresa (opcional) -->
                <div class="mb-3">
                    <label for="lead_empresa" class="form-label">
                        <i class="bi bi-building-fill"></i> Empresa <small class="text-muted">(opcional)</small>
                    </label>
                    <input type="text" class="form-control" id="lead_empresa" name="empresa" placeholder="Nome da sua empresa">
                </div>

                <!-- Mensagem (opcional) -->
                <div class="mb-3">
                    <label for="lead_mensagem" class="form-label">
                        <i class="bi bi-chat-left-text-fill"></i> Mensagem <small class="text-muted">(opcional)</small>
                    </label>
                    <textarea class="form-control" id="lead_mensagem" name="mensagem" rows="3" placeholder="Conte-nos um pouco sobre seu interesse"></textarea>
                </div>

                <!-- Campos ocultos para rastreamento -->
                <input type="hidden" name="origem" value="<?= htmlspecialchars($current_page) ?>">
                <input type="hidden" name="referer" value="<?= htmlspecialchars($referer) ?>">
                <input type="hidden" name="utm_source" id="utm_source">
                <input type="hidden" name="utm_medium" id="utm_medium">
                <input type="hidden" name="utm_campaign" id="utm_campaign">

                <!-- Botão de envio -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-submit" id="leadSubmitBtn">
                        <i class="bi bi-send-fill"></i> Enviar Solicitação
                    </button>
                </div>

                <!-- Mensagem de privacidade -->
                <div class="privacy-text text-center">
                    <i class="bi bi-shield-check"></i> Seus dados estão protegidos e não serão compartilhados.
                </div>
            </form>

            <!-- Mensagem de sucesso (oculta inicialmente) -->
            <div id="leadSuccessMessage" class="d-none success-message">
                <div class="success-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h4>Obrigado!</h4>
                <p class="text-muted">Recebemos sua solicitação. Nossa equipe entrará em contato em breve!</p>
            </div>

            <!-- Mensagem de erro -->
            <div id="leadErrorMessage" class="alert alert-danger d-none mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Erro:</strong> <span id="leadErrorText"></span>
            </div>
        </div>
    </div>
</div>

<script>
// Captura parâmetros UTM da URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    document.getElementById('utm_source').value = urlParams.get('utm_source') || '';
    document.getElementById('utm_medium').value = urlParams.get('utm_medium') || '';
    document.getElementById('utm_campaign').value = urlParams.get('utm_campaign') || '';
    
    // Máscara de telefone WhatsApp
    const whatsappInput = document.getElementById('lead_whatsapp');
    whatsappInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substr(0, 11);
        
        if (value.length > 6) {
            value = value.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        } else if (value.length > 2) {
            value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        } else if (value.length > 0) {
            value = value.replace(/^(\d*)/, '($1');
        }
        
        e.target.value = value;
    });

    // Submissão do formulário via Webhook API
    document.getElementById('leadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('leadSubmitBtn');
        const errorMessage = document.getElementById('leadErrorMessage');
        const formData = new FormData(this);
        
        // Esconde mensagem de erro anterior
        errorMessage.classList.add('d-none');
        
        // Converte FormData para JSON
        const leadData = {
            nome: formData.get('nome'),
            email: formData.get('email'),
            whatsapp: formData.get('whatsapp').replace(/\D/g, ''), // Remove formatação
            empresa: formData.get('empresa') || null,
            mensagem: formData.get('mensagem') || null,
            origem: formData.get('origem'),
            referer: formData.get('referer'),
            utm_source: formData.get('utm_source') || null,
            utm_medium: formData.get('utm_medium') || null,
            utm_campaign: formData.get('utm_campaign') || null
        };
        
        // Desabilita botão durante envio
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        
        // Token da empresa (via PHP)
        const apiToken = '<?php echo $api_token ?? ""; ?>';
        const apiUrl = apiToken ? `api_lead_webhook.php?token=${apiToken}` : 'api_lead_webhook.php';
        
        // Envia para webhook API
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(leadData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Esconde formulário e mostra mensagem de sucesso
                document.getElementById('leadForm').classList.add('d-none');
                document.getElementById('leadSuccessMessage').classList.remove('d-none');
                
                // Envia evento para Google Analytics (GA4)
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'generate_lead', {
                        'event_category': 'Lead',
                        'event_label': leadData.origem,
                        'value': 1
                    });
                }
                
                // Envia evento para Google Tag Manager
                if (typeof dataLayer !== 'undefined') {
                    dataLayer.push({
                        'event': 'lead_submitted',
                        'lead_id': data.lead_id,
                        'lead_name': leadData.nome,
                        'lead_email': leadData.email,
                        'lead_source': leadData.origem,
                        'lead_utm_source': leadData.utm_source,
                        'lead_utm_medium': leadData.utm_medium,
                        'lead_utm_campaign': leadData.utm_campaign
                    });
                }
                
                // Rola para a mensagem de sucesso
                document.getElementById('leadSuccessMessage').scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                // Mostra mensagem de erro
                document.getElementById('leadErrorText').textContent = data.message || 'Erro ao enviar solicitação. Tente novamente.';
                errorMessage.classList.remove('d-none');
                
                // Re-habilita botão
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Enviar Solicitação';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            document.getElementById('leadErrorText').textContent = 'Erro de conexão. Tente novamente.';
            errorMessage.classList.remove('d-none');
            
            // Re-habilita botão
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send-fill"></i> Enviar Solicitação';
        });
    });
});

// Funções de compartilhamento
function copyLinkWithMessage() {
    const link = document.getElementById('share-link-input').value;
    const empresa = '<?= htmlspecialchars($nome_empresa) ?>';
    const message = `Olá! Conheça a plataforma ${empresa}.\n\nPreencha o formulário e nossa equipe entrará em contato:\n${link}`;
    
    navigator.clipboard.writeText(message).then(() => {
        showCopySuccess();
    });
}

function copyLinkOnly() {
    const link = document.getElementById('share-link-input').value;
    navigator.clipboard.writeText(link).then(() => {
        showCopySuccess();
    });
}

function showCopySuccess() {
    const successMsg = document.getElementById('copy-success');
    successMsg.style.display = 'inline';
    setTimeout(() => {
        successMsg.style.display = 'none';
    }, 2000);
}

// WhatsApp share
<?php if ($slug_contexto): ?>
document.addEventListener('DOMContentLoaded', function() {
    const shareUrl = document.getElementById('share-link-input').value;
    const empresa = '<?= htmlspecialchars($nome_empresa) ?>';
    const whatsappMessage = `Olá! Conheça a plataforma ${empresa}.\n\nPreencha o formulário e nossa equipe entrará em contato:\n${shareUrl}`;
    const whatsappBtn = document.getElementById('whatsapp-share-btn');
    whatsappBtn.href = `https://wa.me/?text=${encodeURIComponent(whatsappMessage)}`;
});
<?php endif; ?>
</script>

</body>
</html>
