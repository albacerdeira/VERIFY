<?php
$page_title = 'Teste de Captura Universal';
require_once 'bootstrap.php';

// Garante que apenas administradores e superadmins possam acessar
if (!$is_admin && !$is_superadmin) {
    require_once 'header.php';
    echo "<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require_once 'footer.php';
    exit;
}

// Pega o token da empresa
$empresa_id = $is_superadmin && isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : (int)$_SESSION['empresa_id'];

// DEBUG
error_log("TEST_UNIVERSAL_CAPTURE - empresa_id: $empresa_id");
error_log("TEST_UNIVERSAL_CAPTURE - SESSION empresa_id: " . ($_SESSION['empresa_id'] ?? 'NOT SET'));
error_log("TEST_UNIVERSAL_CAPTURE - is_superadmin: " . ($is_superadmin ? 'true' : 'false'));

$stmt = $pdo->prepare("SELECT api_token, nome_empresa, empresa_id, website_url, cor_variavel FROM configuracoes_whitelabel WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

error_log("TEST_UNIVERSAL_CAPTURE - config encontrada: " . json_encode($config));

$api_token = $config['api_token'] ?? null;
$nome_empresa = $config['nome_empresa'] ?? 'Empresa';
$website_url = $config['website_url'] ?? null;
$cor_variavel = $config['cor_variavel'] ?? '#0d6efd';

require_once 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="bi bi-check2-circle text-success"></i> Validação de Instalação do Script</h2>
            <p class="text-muted">Verifique se o script está instalado e funcionando corretamente no seu site - Empresa: <?= htmlspecialchars($nome_empresa) ?></p>
        </div>
    </div>

    <?php if (empty($api_token)): ?>
    <div class="alert alert-danger border-danger">
        <h4><i class="bi bi-exclamation-triangle-fill"></i> Token API não configurado!</h4>
        <hr>
        <p><strong>Empresa:</strong> <?= htmlspecialchars($nome_empresa) ?> (ID: <?= $empresa_id ?>)</p>
        <p class="mb-3">Esta empresa ainda não possui um token API configurado. Sem o token, o script de captura universal não pode funcionar.</p>
        <div class="d-grid gap-2">
            <a href="configuracoes.php<?= $is_superadmin ? '?id=' . $empresa_id : '' ?>" class="btn btn-danger btn-lg">
                <i class="bi bi-gear-fill"></i> Ir para Configurações e Gerar Token
            </a>
        </div>
        <hr class="mt-3">
        <p class="mb-0 small text-muted">
            <i class="bi bi-info-circle"></i> Vá em <strong>Configurações > Sistema de Leads API</strong> e clique em <strong>"Gerar Novo Token"</strong>
        </p>
    </div>
    <?php 
    // Para aqui - não carrega o resto da página
    require_once 'footer.php';
    exit;
    ?>
    <?php endif; ?>

<!--verificação do site-->
<div class="row mb-4">
        <div class="col-md-12">
            <div class="card" style="border-color: <?= htmlspecialchars($cor_variavel) ?>;">
                <div class="card-header text-white" style="background-color: <?= htmlspecialchars($cor_variavel) ?>;">
                    <strong><i class="bi bi-globe"></i> Verificar Instalação no Seu Site</strong>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> <strong>Teste se o script está instalado corretamente no seu site</strong>
                        <p class="mb-0 small mt-2">Informe a URL do seu site e verificaremos se o script de captura está ativo (similar ao Google Tag Assistant).</p>
                    </div>

                    <div class="row">
                        <div class="col-md-9">
                            <div class="mb-3">
                                <label for="website_url_check" class="form-label">URL do seu site:</label>
                                <input type="url" class="form-control form-control-lg" id="website_url_check" 
                                       value="<?= htmlspecialchars($website_url ?? '') ?>"
                                       placeholder="https://seusite.com.br">
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-lg w-100 mb-3 text-white" style="background-color: <?= htmlspecialchars($cor_variavel) ?>;" onclick="checkInstallation()" id="btnCheck">
                                <i class="bi bi-search"></i> Verificar
                            </button>
                        </div>
                    </div>

                    <!-- Resultado da Verificação -->
                    <div id="checkResult" style="display: none;">
                        <hr>
                        <div id="checkResultContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Console de Logs -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-terminal"></i> Console de Logs</strong>
                    <button class="btn btn-sm btn-outline-light" onclick="clearLogs()">
                        <i class="bi bi-trash"></i> Limpar
                    </button>
                </div>
                <div class="card-body bg-dark text-light font-monospace small" style="height: 150px; overflow-y: auto;" id="consoleLog">
                    <div class="text-muted">Aguardando logs...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status do Sistema (menor, recolhido por padrão) -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="accordion" id="accordionSystemStatus">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSystemStatus">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                data-bs-target="#collapseSystemStatus" aria-expanded="false">
                            <i class="bi bi-gear me-2"></i> Status do Sistema
                        </button>
                    </h2>
                    <div id="collapseSystemStatus" class="accordion-collapse collapse" data-bs-parent="#accordionSystemStatus">
                        <div class="accordion-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="p-3">
                                        <div class="spinner-border text-secondary" role="status" id="statusLoading">
                                            <span class="visually-hidden">Verificando...</span>
                                        </div>
                                        <div id="statusScript" style="display: none;">
                                            <i class="bi bi-x-circle-fill text-danger fs-1" id="iconError"></i>
                                            <i class="bi bi-check-circle-fill text-success fs-1" id="iconSuccess"></i>
                                        </div>
                                        <p class="small mt-2 mb-0"><strong>Script Carregado</strong></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3">
                                        <div id="tokenStatus">
                                            <i class="bi bi-question-circle text-muted fs-1"></i>
                                        </div>
                                        <p class="small mt-2 mb-0"><strong>Token Válido</strong></p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3">
                                        <div id="captureCount">
                                            <span class="fs-1 fw-bold text-success">0</span>
                                        </div>
                                        <p class="small mt-2 mb-0"><strong>Leads Capturados</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

    <!-- Formulários de Teste (apenas se não tiver website_url configurado) -->
    <?php if (empty($website_url)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> <strong>Configure a URL do seu site</strong>
        <p class="mb-0">Vá em <a href="configuracoes.php<?= $is_superadmin ? '?id=' . $empresa_id : '' ?>">Configurações</a> e preencha o campo "URL do Site da Empresa" para testar a instalação no site real.</p>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card" style="border-color: <?= htmlspecialchars($cor_variavel) ?>;">
                <div class="card-header text-white" style="background-color: <?= htmlspecialchars($cor_variavel) ?>;">
                    <strong><i class="bi bi-file-earmark-text"></i> Formulário de Teste #1</strong>
                    <span class="badge bg-light text-dark float-end">Completo</span>
                </div>
                <div class="card-body">
                    <form id="testForm1" class="test-form">
                        <div class="mb-3">
                            <label for="nome1" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome1" name="nome" placeholder="João Silva">
                        </div>
                        <div class="mb-3">
                            <label for="email1" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email1" name="email" placeholder="joao@email.com">
                        </div>
                        <div class="mb-3">
                            <label for="whatsapp1" class="form-label">WhatsApp</label>
                            <input type="tel" class="form-control" id="whatsapp1" name="whatsapp" placeholder="11999999999">
                        </div>
                        <div class="mb-3">
                            <label for="empresa1" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="empresa1" name="empresa" placeholder="Empresa XYZ">
                        </div>
                        <button type="submit" class="btn w-100 text-white" style="background-color: <?= htmlspecialchars($cor_variavel) ?>;">
                            <i class="bi bi-send"></i> Enviar Formulário #1
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-secondary">
                <div class="card-header bg-secondary text-white">
                    <strong><i class="bi bi-file-earmark-text"></i> Formulário de Teste #2</strong>
                    <span class="badge bg-light text-dark float-end">Mínimo</span>
                </div>
                <div class="card-body">
                    <form id="testForm2" class="test-form">
                        <div class="mb-3">
                            <label for="your-name" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="your-name" name="your-name" placeholder="Maria Santos">
                        </div>
                        <div class="mb-3">
                            <label for="your-email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="your-email" name="your-email" placeholder="maria@email.com">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="11988888888">
                        </div>
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="bi bi-send"></i> Enviar Formulário #2
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão de Auto-Preencher -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <button class="btn btn-primary btn-lg" onclick="autoFill()">
                        <i class="bi bi-magic"></i> Auto-Preencher e Testar
                    </button>
                    <p class="small text-muted mt-2 mb-0">Preenche automaticamente os formulários e simula o envio</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos Leads Capturados -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card" style="border-color: <?= htmlspecialchars($cor_variavel) ?>;">
                <div class="card-header text-white d-flex justify-content-between align-items-center" style="background-color: <?= htmlspecialchars($cor_variavel) ?>;">
                    <strong><i class="bi bi-list-check"></i> Últimos Leads Capturados (Tempo Real)</strong>
                    <button class="btn btn-sm btn-outline-light" onclick="refreshLeads()">
                        <i class="bi bi-arrow-clockwise"></i> Atualizar
                    </button>
                </div>
                <div class="card-body" id="leadsTable">
                    <div class="text-center text-muted">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando leads...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Script Universal com Token -->
<?php if (!empty($api_token)): 
    $script_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
                  $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . 
                  '/verify-universal-form-capture.js?token=' . urlencode($api_token);
?>
<script src="<?= $script_url ?>"></script>
<?php endif; ?>

<script>
let capturedLeads = 0;
let consoleLogElement = document.getElementById('consoleLog');

// Intercepta console.log do script
const originalLog = console.log;
console.log = function(...args) {
    originalLog.apply(console, args);
    
    const message = args.join(' ');
    if (message.includes('[VERIFY')) {
        addLog(message);
    }
};

// Adiciona log ao console visual
function addLog(message) {
    const time = new Date().toLocaleTimeString();
    const logLine = document.createElement('div');
    logLine.className = 'mb-1';
    
    // Colorir baseado no tipo de mensagem
    let color = 'text-light';
    if (message.includes('✅')) color = 'text-success';
    if (message.includes('❌')) color = 'text-danger';
    if (message.includes('⚠️')) color = 'text-warning';
    if (message.includes('🚀')) color = 'text-primary';
    
    logLine.innerHTML = `<span class="text-muted">[${time}]</span> <span class="${color}">${escapeHtml(message)}</span>`;
    
    // Remove mensagem de "Aguardando logs..." se existir
    if (consoleLogElement.querySelector('.text-muted')) {
        consoleLogElement.innerHTML = '';
    }
    
    consoleLogElement.appendChild(logLine);
    consoleLogElement.scrollTop = consoleLogElement.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function clearLogs() {
    consoleLogElement.innerHTML = '<div class="text-muted">Aguardando logs...</div>';
}

// Copia código para clipboard
function copyCode(elementId) {
    const codeElement = document.getElementById(elementId);
    const code = codeElement.textContent;
    
    navigator.clipboard.writeText(code).then(() => {
        // Feedback visual
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Copiado!';
        btn.classList.remove('btn-outline-success');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-success');
        }, 2000);
        
        addLog('[INFO] ✅ Código copiado para a área de transferência!');
    }).catch(err => {
        alert('Erro ao copiar código. Por favor, copie manualmente.');
        console.error('Erro ao copiar:', err);
    });
}

// Verifica se o script carregou
setTimeout(() => {
    const scriptLoaded = typeof VerifyLeadCapture !== 'undefined';
    document.getElementById('statusLoading').style.display = 'none';
    document.getElementById('statusScript').style.display = 'block';
    
    if (scriptLoaded) {
        document.getElementById('iconSuccess').style.display = 'block';
        document.getElementById('iconError').style.display = 'none';
        
        // Verifica token
        const hasToken = VerifyLeadCapture.config.apiToken && VerifyLeadCapture.config.apiToken !== 'SEU_TOKEN_AQUI';
        document.getElementById('tokenStatus').innerHTML = hasToken 
            ? '<i class="bi bi-check-circle-fill text-success fs-1"></i>'
            : '<i class="bi bi-x-circle-fill text-danger fs-1"></i>';
        
        // Conta formulários
        const formsCount = document.querySelectorAll('form').length;
        document.getElementById('formsCount').innerHTML = `<span class="fs-1 fw-bold text-primary">${formsCount}</span>`;
        
        addLog('[TESTE] ✅ Script carregado com sucesso!');
        addLog(`[TESTE] Token: ${hasToken ? '✅ Configurado' : '❌ Não configurado'}`);
        addLog(`[TESTE] Formulários detectados: ${formsCount}`);
    } else {
        document.getElementById('iconSuccess').style.display = 'none';
        document.getElementById('iconError').style.display = 'block';
        document.getElementById('tokenStatus').innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-1"></i>';
        addLog('[TESTE] ❌ Erro: Script não carregou!');
    }
}, 1000);

// Escuta eventos de captura
window.addEventListener('verifyLeadCaptured', function(e) {
    capturedLeads++;
    document.getElementById('captureCount').innerHTML = `<span class="fs-1 fw-bold text-success">${capturedLeads}</span>`;
    addLog(`[TESTE] ✅ Lead #${capturedLeads} capturado! ID: ${e.detail.lead_id}`);
    
    // Atualiza tabela de leads
    setTimeout(refreshLeads, 1000);
});

// Auto-preenche formulários
function autoFill() {
    // Form 1
    document.getElementById('nome1').value = 'João Silva Teste';
    document.getElementById('email1').value = 'joao.teste@email.com';
    document.getElementById('whatsapp1').value = '11999999999';
    document.getElementById('empresa1').value = 'Empresa de Teste Ltda';
    
    // Form 2
    document.getElementById('your-name').value = 'Maria Santos Teste';
    document.getElementById('your-email').value = 'maria.teste@email.com';
    document.getElementById('phone').value = '11988888888';
    
    addLog('[TESTE] 📝 Formulários preenchidos automaticamente');
    
    // Simula submit do form 1 após 1 segundo
    setTimeout(() => {
        addLog('[TESTE] 📤 Enviando formulário #1...');
        document.getElementById('testForm1').dispatchEvent(new Event('submit'));
    }, 1000);
}

// Verifica instalação no site real
function checkInstallation() {
    const url = document.getElementById('website_url_check').value.trim();
    const btnCheck = document.getElementById('btnCheck');
    const resultDiv = document.getElementById('checkResult');
    const resultContent = document.getElementById('checkResultContent');
    
    if (!url) {
        alert('Por favor, informe a URL do seu site');
        return;
    }
    
    // Mostra loading
    btnCheck.disabled = true;
    btnCheck.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verificando...';
    resultDiv.style.display = 'none';
    
    // Faz requisição AJAX
    fetch('ajax_check_script_installation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin', // IMPORTANTE: Envia cookies de sessão
        body: 'website_url=' + encodeURIComponent(url) + '&empresa_id=<?= $empresa_id ?>'
    })
    .then(response => {
        // Debug: mostra o conteúdo bruto da resposta
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        // Tenta ler como texto primeiro
        return response.text().then(text => {
            console.log('Response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Resposta não é JSON válido: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        btnCheck.disabled = false;
        btnCheck.innerHTML = '<i class="bi bi-search"></i> Verificar';
        resultDiv.style.display = 'block';
        
        if (data.success) {
            let statusClass = 'success';
            let statusIcon = 'check-circle-fill';
            
            if (!data.script_installed) {
                statusClass = 'danger';
                statusIcon = 'x-circle-fill';
            } else if (!data.token_correct) {
                statusClass = 'warning';
                statusIcon = 'exclamation-triangle-fill';
            }
            
            resultContent.innerHTML = `
                <div class="alert alert-${statusClass} border-${statusClass}">
                    <h5><i class="bi bi-${statusIcon}"></i> ${data.message}</h5>
                    <p class="mb-2">${data.details}</p>
                    ${data.action ? `<p class="mb-0"><strong>Ação necessária:</strong> ${data.action}</p>` : ''}
                </div>
                
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded">
                            <i class="bi bi-${data.script_installed ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'} fs-1"></i>
                            <p class="small mb-0 mt-2"><strong>Script Detectado</strong></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded">
                            <i class="bi bi-${data.token_correct ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'} fs-1"></i>
                            <p class="small mb-0 mt-2"><strong>Token Correto</strong></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded">
                            <span class="fs-1 fw-bold text-primary">${data.form_count}</span>
                            <p class="small mb-0 mt-2"><strong>Formulários</strong></p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded">
                            <i class="bi bi-clock-fill text-info fs-1"></i>
                            <p class="small mb-0 mt-2"><strong>${data.timestamp}</strong></p>
                        </div>
                    </div>
                </div>
                
                ${data.script_url ? `
                    <div class="mt-3">
                        <strong>Script encontrado:</strong>
                        <pre class="bg-light p-2 rounded"><code>${data.script_url}</code></pre>
                    </div>
                ` : ''}
                
                ${data.forms_details && data.forms_details.length > 0 ? `
                    <div class="mt-4">
                        <h5><i class="bi bi-list-check"></i> Formulários Detectados</h5>
                        <div class="accordion" id="accordionForms">
                            ${data.forms_details.map((form, idx) => `
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading${idx}">
                                        <button class="accordion-button ${idx > 0 ? 'collapsed' : ''}" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#collapse${idx}">
                                            <i class="bi bi-file-earmark-text me-2"></i>
                                            <strong>Formulário #${form.index}</strong>
                                            ${form.id ? ` - ID: <code>${form.id}</code>` : ''}
                                            ${form.name ? ` - Name: <code>${form.name}</code>` : ''}
                                            <span class="badge bg-primary ms-2">${form.inputs.length} campos</span>
                                        </button>
                                    </h2>
                                    <div id="collapse${idx}" class="accordion-collapse collapse ${idx === 0 ? 'show' : ''}" 
                                         data-bs-parent="#accordionForms">
                                        <div class="accordion-body">
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <strong>Action:</strong> ${form.action || '(mesma página)'}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Method:</strong> <span class="badge bg-secondary">${form.method}</span>
                                                </div>
                                            </div>
                                            <hr>
                                            <strong>Campos detectados:</strong>
                                            <table class="table table-sm table-hover mt-2">
                                                <thead>
                                                    <tr>
                                                        <th>Tag</th>
                                                        <th>Type</th>
                                                        <th>Name</th>
                                                        <th>ID</th>
                                                        <th>Placeholder</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${form.inputs.map(input => `
                                                        <tr>
                                                            <td><span class="badge bg-info">${input.tag}</span></td>
                                                            <td><code>${input.type}</code></td>
                                                            <td><code>${input.name || '-'}</code></td>
                                                            <td><code>${input.id || '-'}</code></td>
                                                            <td class="small">${input.placeholder || '-'}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            `;
            
            addLog(`[VERIFICAÇÃO] ${data.message}`);
            addLog(`[VERIFICAÇÃO] URL: ${url}`);
            addLog(`[VERIFICAÇÃO] Formulários: ${data.form_count}`);
            
            // Log dos formulários encontrados
            if (data.forms_details && data.forms_details.length > 0) {
                data.forms_details.forEach((form, idx) => {
                    addLog(`[FORMULÁRIO #${idx + 1}] ${form.inputs.length} campos: ${form.inputs.map(i => i.name || i.id).join(', ')}`);
                });
            }
        } else {
            resultContent.innerHTML = `
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-triangle-fill"></i> Erro na Verificação</h5>
                    <p class="mb-0">${data.error}</p>
                </div>
            `;
            addLog(`[VERIFICAÇÃO] ❌ Erro: ${data.error}`);
        }
    })
    .catch(error => {
        btnCheck.disabled = false;
        btnCheck.innerHTML = '<i class="bi bi-search"></i> Verificar';
        resultDiv.style.display = 'block';
        resultContent.innerHTML = `
            <div class="alert alert-danger">
                <h5><i class="bi bi-exclamation-triangle-fill"></i> Erro de Conexão</h5>
                <p class="mb-0">Não foi possível verificar o site. Tente novamente.</p>
            </div>
        `;
        addLog(`[VERIFICAÇÃO] ❌ Erro de conexão: ${error}`);
    });
}


// Previne submit real (para não recarregar a página)
document.querySelectorAll('.test-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        addLog(`[TESTE] 📋 Submit interceptado: ${this.id}`);
    });
});

// Carrega últimos leads
function refreshLeads() {
    const token = '<?php echo $api_token; ?>';
    fetch(`ajax_get_recent_leads.php?token=${token}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLeads(data.leads);
            } else {
                document.getElementById('leadsTable').innerHTML = '<div class="alert alert-warning">Erro ao carregar leads: ' + (data.message || 'Desconhecido') + '</div>';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            document.getElementById('leadsTable').innerHTML = '<div class="alert alert-danger">Erro de conexão: ' + error.message + '</div>';
        });
}

function displayLeads(leads) {
    if (leads.length === 0) {
        document.getElementById('leadsTable').innerHTML = '<div class="alert alert-info">Nenhum lead capturado ainda</div>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
    html += '<thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>WhatsApp</th><th>Status</th><th>Data</th></tr></thead><tbody>';
    
    leads.forEach(lead => {
        const statusClass = lead.status === 'Novo' ? 'success' : 'secondary';
        html += `<tr>
            <td><strong>#${lead.id}</strong></td>
            <td>${lead.nome}</td>
            <td>${lead.email}</td>
            <td>${lead.whatsapp || '-'}</td>
            <td><span class="badge bg-${statusClass}">${lead.status}</span></td>
            <td><small>${lead.data_criacao}</small></td>
        </tr>`;
    });
    
    html += '</tbody></table></div>';
    document.getElementById('leadsTable').innerHTML = html;
}

// Carrega leads ao iniciar
setTimeout(refreshLeads, 500);

// Auto-refresh a cada 10 segundos
setInterval(refreshLeads, 10000);
</script>

<?php require_once 'footer.php'; ?>
