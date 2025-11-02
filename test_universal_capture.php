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
$stmt = $pdo->prepare("SELECT api_token, nome_empresa FROM configuracoes_whitelabel WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$api_token = $config['api_token'] ?? null;
$nome_empresa = $config['nome_empresa'] ?? 'Empresa';

require_once 'header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="bi bi-bug-fill text-warning"></i> Teste de Captura Universal de Formulários</h2>
            <p class="text-muted">Teste o funcionamento do script em tempo real</p>
        </div>
    </div>

    <?php if (empty($api_token)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> <strong>Token não encontrado!</strong>
        <p class="mb-0">Você precisa gerar um token API primeiro em <a href="configuracoes.php">Configurações > Sistema de Leads API</a></p>
    </div>
    <?php else: ?>

    <!-- Status do Script -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <strong><i class="bi bi-check-circle"></i> Status do Script</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center p-3">
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
                        <div class="col-md-3">
                            <div class="text-center p-3">
                                <div id="tokenStatus">
                                    <i class="bi bi-question-circle text-muted fs-1"></i>
                                </div>
                                <p class="small mt-2 mb-0"><strong>Token Válido</strong></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3">
                                <div id="formsCount">
                                    <span class="fs-1 fw-bold text-primary">0</span>
                                </div>
                                <p class="small mt-2 mb-0"><strong>Forms Detectados</strong></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3">
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
                <div class="card-body bg-dark text-light font-monospace small" style="height: 300px; overflow-y: auto;" id="consoleLog">
                    <div class="text-muted">Aguardando logs...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulários de Teste -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <strong><i class="bi bi-file-earmark-text"></i> Formulário de Teste #1</strong>
                    <span class="badge bg-light text-success float-end">Completo</span>
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
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-send"></i> Enviar Formulário #1
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <strong><i class="bi bi-file-earmark-text"></i> Formulário de Teste #2</strong>
                    <span class="badge bg-dark float-end">Mínimo</span>
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
                        <button type="submit" class="btn btn-warning w-100">
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
            <div class="card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
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

    <?php endif; ?>
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
