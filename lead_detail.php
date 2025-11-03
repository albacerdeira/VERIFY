<?php
// Página de detalhes de um lead
$page_title = 'Detalhes do Lead';
require_once 'bootstrap.php';

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Analistas não têm acesso
if ($user_role === 'analista') {
    header('Location: dashboard.php');
    exit;
}

$lead_id = $_GET['id'] ?? null;

if (!$lead_id) {
    header('Location: leads.php');
    exit;
}

try {
    // Busca dados do lead
    $stmt = $pdo->prepare("
        SELECT l.*, 
               e.nome AS empresa_parceira_nome
        FROM leads l
        LEFT JOIN empresas e ON l.id_empresa_master = e.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        header('Location: leads.php');
        exit;
    }
    
    // Verifica permissão (admin só pode ver leads da sua empresa)
    if ($user_role === 'administrador' && $lead['id_empresa_master'] != $user_empresa_id) {
        header('Location: leads.php');
        exit;
    }
    
    // Busca histórico do lead + eventos do processo KYC do cliente
    $stmt = $pdo->prepare("
        SELECT 
            h.created_at,
            h.acao COLLATE utf8mb4_general_ci as acao,
            h.descricao COLLATE utf8mb4_general_ci as descricao,
            COALESCE(u.nome, 'Sistema') COLLATE utf8mb4_general_ci AS usuario_nome,
            'lead' COLLATE utf8mb4_general_ci as tipo_evento
        FROM leads_historico h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.lead_id = ?
        
        UNION ALL
        
        SELECT 
            kc.created_at,
            'cliente_registrado' COLLATE utf8mb4_general_ci as acao,
            CONCAT('Cliente se registrou: ', kc.nome_completo) COLLATE utf8mb4_general_ci as descricao,
            'Sistema' COLLATE utf8mb4_general_ci as usuario_nome,
            'cliente' COLLATE utf8mb4_general_ci as tipo_evento
        FROM kyc_clientes kc
        WHERE kc.lead_id = ?
        
        UNION ALL
        
        SELECT 
            ke.data_criacao as created_at,
            'kyc_iniciado' COLLATE utf8mb4_general_ci as acao,
            CONCAT('KYC iniciado: ', ke.razao_social, ' (', ke.cnpj, ')') COLLATE utf8mb4_general_ci as descricao,
            'Cliente' COLLATE utf8mb4_general_ci as usuario_nome,
            'kyc' COLLATE utf8mb4_general_ci as tipo_evento
        FROM kyc_empresas ke
        INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
        WHERE kc.lead_id = ?
        
        UNION ALL
        
        SELECT 
            ke.data_atualizacao as created_at,
            CONCAT('kyc_status_', ke.status) COLLATE utf8mb4_general_ci as acao,
            CONCAT('Status do KYC alterado para: ', ke.status) COLLATE utf8mb4_general_ci as descricao,
            'Sistema' COLLATE utf8mb4_general_ci as usuario_nome,
            'kyc_status' COLLATE utf8mb4_general_ci as tipo_evento
        FROM kyc_empresas ke
        INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
        WHERE kc.lead_id = ?
        
        ORDER BY created_at DESC
    ");
    $stmt->execute([$lead_id, $lead_id, $lead_id, $lead_id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar detalhes do lead: " . $e->getMessage());
    header('Location: leads.php');
    exit;
}

require_once 'header.php';

// Badge de status
$badge_class = match($lead['status']) {
    'novo' => 'bg-primary',
    'contatado' => 'bg-info',
    'qualificado' => 'bg-warning text-dark',
    'convertido' => 'bg-success',
    'perdido' => 'bg-secondary',
    default => 'bg-light text-dark'
};
?>

<div class="container-fluid py-4">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="bi bi-person-circle"></i> Detalhes do Lead</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="leads.php">Leads</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($lead['nome']) ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="leads.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Coluna Principal -->
        <div class="col-md-8">
            <!-- Informações do Lead -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-person-fill"></i> Informações de Contato</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Nome Completo</label>
                            <p class="mb-0"><strong><?= htmlspecialchars($lead['nome']) ?></strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Status</label>
                            <p class="mb-0">
                                <span class="badge <?= $badge_class ?> fs-6">
                                    <?= ucfirst($lead['status']) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">E-mail</label>
                            <p class="mb-0">
                                <a href="mailto:<?= htmlspecialchars($lead['email']) ?>">
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($lead['email']) ?>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">WhatsApp</label>
                            <p class="mb-0">
                                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" 
                                   target="_blank" class="text-success">
                                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($lead['whatsapp']) ?>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="text-muted small">Empresa</label>
                            <p class="mb-0"><?= htmlspecialchars($lead['empresa'] ?: '-') ?></p>
                        </div>
                        <?php if ($lead['mensagem']): ?>
                        <div class="col-md-12">
                            <label class="text-muted small">Mensagem</label>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($lead['mensagem'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Informações de Rastreamento -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Rastreamento</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="text-muted small">Origem</label>
                            <p class="mb-0"><?= htmlspecialchars($lead['origem'] ?: '-') ?></p>
                        </div>
                        <?php if ($lead['utm_source'] || $lead['utm_medium'] || $lead['utm_campaign']): ?>
                        <div class="col-md-12">
                            <label class="text-muted small">Parâmetros UTM</label>
                            <p class="mb-0">
                                <?php if ($lead['utm_source']): ?>
                                <span class="badge bg-secondary">Source: <?= htmlspecialchars($lead['utm_source']) ?></span>
                                <?php endif; ?>
                                <?php if ($lead['utm_medium']): ?>
                                <span class="badge bg-secondary">Medium: <?= htmlspecialchars($lead['utm_medium']) ?></span>
                                <?php endif; ?>
                                <?php if ($lead['utm_campaign']): ?>
                                <span class="badge bg-secondary">Campaign: <?= htmlspecialchars($lead['utm_campaign']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">IP</label>
                            <p class="mb-0"><code><?= htmlspecialchars($lead['ip_address'] ?: '-') ?></code></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">User Agent</label>
                            <p class="mb-0"><small><?= htmlspecialchars($lead['user_agent'] ?: '-') ?></small></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Histórico -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Histórico</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($historico)): ?>
                    <p class="text-muted text-center py-3">
                        <i class="bi bi-inbox"></i> Nenhum histórico registrado
                    </p>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($historico as $item): 
                            // Define ícone e cor baseado no tipo de evento
                            $icone = 'bi-circle-fill';
                            $cor = 'text-primary';
                            
                            switch($item['tipo_evento']) {
                                case 'cliente':
                                    $icone = 'bi-person-check-fill';
                                    $cor = 'text-success';
                                    break;
                                case 'kyc':
                                    $icone = 'bi-file-earmark-text-fill';
                                    $cor = 'text-info';
                                    break;
                                case 'kyc_status':
                                    $icone = 'bi-arrow-repeat';
                                    $cor = 'text-warning';
                                    break;
                                case 'lead':
                                default:
                                    if (strpos($item['acao'], 'email_') === 0) {
                                        $icone = 'bi-envelope-fill';
                                        $cor = 'text-primary';
                                    } elseif (strpos($item['acao'], 'status_') === 0) {
                                        $icone = 'bi-arrow-repeat';
                                        $cor = 'text-info';
                                    } elseif ($item['acao'] === 'registro_completado') {
                                        $icone = 'bi-check-circle-fill';
                                        $cor = 'text-success';
                                    }
                                    break;
                            }
                        ?>
                        <div class="d-flex mb-3 align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi <?= $icone ?> <?= $cor ?>" style="font-size: 1.2rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong><?= htmlspecialchars($item['acao']) ?></strong>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></small>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($item['descricao']) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($item['usuario_nome']) ?>
                                    <?php if ($item['tipo_evento'] !== 'lead'): ?>
                                        <span class="badge bg-<?= $item['tipo_evento'] === 'cliente' ? 'success' : ($item['tipo_evento'] === 'kyc' ? 'info' : 'warning') ?> ms-2">
                                            <?= strtoupper($item['tipo_evento']) ?>
                                        </span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna Lateral -->
        <div class="col-md-4">
            <!-- Ações -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-lightning-fill"></i> Ações Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <!-- Botão principal com dropdown -->
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" onclick="abrirModalEnvioKYC(<?= $lead_id ?>)">
                                <i class="bi bi-file-earmark-check-fill"></i> Enviar Formulário de Cadastro
                            </button>
                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="visually-hidden">Opções</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="enviarKYCPorEmail(<?= $lead_id ?>); return false;">
                                    <i class="bi bi-envelope"></i> Enviar por Email
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="enviarKYCPorWhatsApp(<?= $lead_id ?>); return false;">
                                    <i class="bi bi-whatsapp"></i> Enviar por WhatsApp
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" onclick="apenasGerarLink(<?= $lead_id ?>); return false;">
                                    <i class="bi bi-link-45deg"></i> Apenas gerar link
                                </a></li>
                            </ul>
                        </div>
                        
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                            <i class="bi bi-arrow-repeat"></i> Alterar Status
                        </button>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" 
                           target="_blank" class="btn btn-success">
                            <i class="bi bi-whatsapp"></i> Contato WhatsApp
                        </a>
                        <a href="mailto:<?= htmlspecialchars($lead['email']) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-envelope"></i> Contato E-mail
                        </a>
                    </div>
                </div>
            </div>

            <!-- Informações Gerais -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informações</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Data de Captura</label>
                        <p class="mb-0"><?= date('d/m/Y \à\s H:i', strtotime($lead['created_at'])) ?></p>
                    </div>
                    <?php if ($lead['empresa_parceira_nome']): ?>
                    <div class="mb-3">
                        <label class="text-muted small">Empresa Parceira</label>
                        <p class="mb-0"><?= htmlspecialchars($lead['empresa_parceira_nome']) ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <label class="text-muted small">Última Atualização</label>
                        <p class="mb-0"><?= date('d/m/Y H:i', strtotime($lead['updated_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Mudança de Status -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Alterar Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Novo Status</label>
                    <select class="form-select" id="newStatus">
                        <option value="novo" <?= $lead['status'] === 'novo' ? 'selected' : '' ?>>Novo</option>
                        <option value="contatado" <?= $lead['status'] === 'contatado' ? 'selected' : '' ?>>Contatado</option>
                        <option value="qualificado" <?= $lead['status'] === 'qualificado' ? 'selected' : '' ?>>Qualificado</option>
                        <option value="convertido" <?= $lead['status'] === 'convertido' ? 'selected' : '' ?>>Convertido</option>
                        <option value="perdido" <?= $lead['status'] === 'perdido' ? 'selected' : '' ?>>Perdido</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Observação (opcional)</label>
                    <textarea class="form-control" id="statusObservacao" rows="3" 
                              placeholder="Adicione notas sobre esta mudança de status..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveStatus()">
                    <i class="bi bi-check-circle"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Envio de Cadastro -->
<div class="modal fade" id="envioKYCModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-send-fill"></i> Enviar Formulário de Cadastro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Lead:</strong> <?= htmlspecialchars($lead['nome']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($lead['email']) ?></p>
                <p><strong>WhatsApp:</strong> <?= htmlspecialchars($lead['whatsapp']) ?></p>
                
                <hr>
                
                <h6 class="mb-3">Escolha o método de envio:</h6>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-success btn-lg" onclick="executarEnvioKYC('email')">
                        <i class="bi bi-envelope-fill"></i> Enviar por Email
                        <small class="d-block">Envio automático do formulário de registro</small>
                    </button>
                    
                    <button class="btn btn-success btn-lg" onclick="executarEnvioKYC('whatsapp')">
                        <i class="bi bi-whatsapp"></i> Enviar por WhatsApp
                        <small class="d-block">Abre WhatsApp com mensagem pronta</small>
                    </button>
                    
                    <button class="btn btn-outline-secondary" onclick="executarEnvioKYC('link')">
                        <i class="bi bi-link-45deg"></i> Apenas gerar link
                        <small class="d-block">Copia link para envio manual</small>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let leadIdAtual = <?= $lead_id ?>;

// Abre modal de envio KYC
function abrirModalEnvioKYC(leadId) {
    leadIdAtual = leadId;
    const modal = new bootstrap.Modal(document.getElementById('envioKYCModal'));
    modal.show();
}

// Envio por email direto
function enviarKYCPorEmail(leadId) {
    executarEnvioKYC('email', leadId);
}

// Envio por WhatsApp direto  
function enviarKYCPorWhatsApp(leadId) {
    executarEnvioKYC('whatsapp', leadId);
}

// Apenas gerar link
function apenasGerarLink(leadId) {
    executarEnvioKYC('link', leadId);
}

// Executa o envio do KYC
function executarEnvioKYC(metodo, leadId = null) {
    const idLead = leadId || leadIdAtual;
    
    // Fecha modal se estiver aberto
    const modal = bootstrap.Modal.getInstance(document.getElementById('envioKYCModal'));
    if (modal) modal.hide();
    
    // Mostra loading
    const loadingMsg = metodo === 'email' ? 'Enviando email...' : 
                       metodo === 'whatsapp' ? 'Preparando WhatsApp...' : 
                       'Gerando link...';
    
    // Você pode adicionar um spinner aqui
    console.log(loadingMsg);
    
    fetch('ajax_send_kyc_to_lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            lead_id: idLead,
            metodo: metodo 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (metodo === 'email') {
                if (data.email_enviado) {
                    alert('✅ ' + data.message);
                } else {
                    alert('⚠️ Email não configurado.\n\nLink gerado:\n' + data.kyc_url);
                    copiarParaClipboard(data.kyc_url);
                }
            } else if (metodo === 'whatsapp') {
                // Abre WhatsApp em nova aba
                window.open(data.whatsapp_url, '_blank');
                alert('📱 WhatsApp aberto! Envie a mensagem para o lead.');
            } else {
                // Apenas link - copia para clipboard
                copiarParaClipboard(data.kyc_url);
                alert('✅ Link copiado para a área de transferência!\n\n' + data.kyc_url);
            }
            
            // Recarrega página para atualizar status
            setTimeout(() => location.reload(), 1500);
        } else {
            let errorMsg = '❌ Erro: ' + data.message;
            if (data.debug) {
                errorMsg += '\n\nDebug:';
                errorMsg += '\n- Lead pertence à empresa ID: ' + data.debug.lead_empresa;
                errorMsg += '\n- Você está na empresa ID: ' + data.debug.user_empresa;
                errorMsg += '\n- Seu papel: ' + data.debug.user_role;
            }
            alert(errorMsg);
            console.error('Erro detalhado:', data);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro de conexão ao enviar formulário KYC');
    });
}

// Copia texto para clipboard
function copiarParaClipboard(texto) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texto);
    } else {
        // Fallback para navegadores antigos
        const textarea = document.createElement('textarea');
        textarea.value = texto;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}

// Função antiga mantida para compatibilidade
function enviarFormularioKYC(leadId) {
    abrirModalEnvioKYC(leadId);
}

// Alterar status do lead
function saveStatus() {
    const newStatus = document.getElementById('newStatus').value;
    const observacao = document.getElementById('statusObservacao').value;
    
    fetch('ajax_update_lead_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            lead_id: <?= $lead_id ?>, 
            status: newStatus, 
            observacao: observacao 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao atualizar status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro de conexão ao atualizar status');
    });
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 8px;
    bottom: 8px;
    width: 1px;
    background-color: #dee2e6;
}
</style>

<?php require_once 'footer.php'; ?>
