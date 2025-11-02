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
if ($_SESSION['user_role'] === 'analista') {
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
               e.nome AS empresa_parceira_nome,
               u.nome AS responsavel_nome
        FROM leads l
        LEFT JOIN empresas e ON l.id_empresa_master = e.id
        LEFT JOIN usuarios u ON l.id_usuario_responsavel = u.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        header('Location: leads.php');
        exit;
    }
    
    // Verifica permissão (admin só pode ver leads da sua empresa)
    if ($_SESSION['user_role'] === 'administrador' && $lead['id_empresa_master'] != $_SESSION['user_empresa_id']) {
        header('Location: leads.php');
        exit;
    }
    
    // Busca histórico
    $stmt = $pdo->prepare("
        SELECT h.*, u.nome AS usuario_nome
        FROM leads_historico h
        LEFT JOIN usuarios u ON h.usuario_id = u.id
        WHERE h.lead_id = ?
        ORDER BY h.created_at DESC
    ");
    $stmt->execute([$lead_id]);
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
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Origem</label>
                            <p class="mb-0"><?= htmlspecialchars($lead['origem'] ?: '-') ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Referer</label>
                            <p class="mb-0"><small><?= htmlspecialchars($lead['referer'] ?: '-') ?></small></p>
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
                        <?php foreach ($historico as $item): ?>
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <i class="bi bi-circle-fill text-primary" style="font-size: 8px;"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-1">
                                    <strong><?= htmlspecialchars($item['acao']) ?></strong>
                                </p>
                                <p class="text-muted mb-0"><?= htmlspecialchars($item['descricao']) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($item['usuario_nome'] ?? 'Sistema') ?> -
                                    <i class="bi bi-clock"></i> <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
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
                        <button class="btn btn-primary" onclick="enviarFormularioKYC(<?= $lead_id ?>)">
                            <i class="bi bi-file-earmark-check-fill"></i> Enviar Formulário KYC
                        </button>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                            <i class="bi bi-arrow-repeat"></i> Alterar Status
                        </button>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" 
                           target="_blank" class="btn btn-success">
                            <i class="bi bi-whatsapp"></i> Enviar WhatsApp
                        </a>
                        <a href="mailto:<?= htmlspecialchars($lead['email']) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-envelope"></i> Enviar E-mail
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
                    <?php if ($lead['responsavel_nome']): ?>
                    <div class="mb-3">
                        <label class="text-muted small">Responsável</label>
                        <p class="mb-0"><?= htmlspecialchars($lead['responsavel_nome']) ?></p>
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

<script>
// Enviar formulário KYC para o lead
function enviarFormularioKYC(leadId) {
    if (!confirm('Deseja enviar o link do formulário KYC para este lead?')) {
        return;
    }
    
    fetch('ajax_send_kyc_to_lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lead_id: leadId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message + '\n\nLink: ' + data.kyc_url);
            // Atualiza status para "contatado" automaticamente
            location.reload();
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
        alert('Erro de conexão ao enviar formulário KYC');
    });
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
