<?php
$page_title = 'Leads Capturados';
require_once 'bootstrap.php';

// Verifica permissões - APENAS analistas NÃO podem acessar
if ($user_role === 'analista') {
    require_once 'header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'><i class='bi bi-ban'></i> Você não tem permissão para acessar esta página.</div></div>";
    require_once 'footer.php';
    exit;
}

require_once 'header.php';

// ===== FILTROS =====
$filter_status = $_GET['status'] ?? '';
$filter_empresa = ($user_role === 'superadmin' && isset($_GET['empresa_id'])) ? $_GET['empresa_id'] : '';
$search = $_GET['search'] ?? '';

// ===== CONSTRUÇÃO DA QUERY =====
$params = [];
$where_clauses = [];

// Filtro de Status
if ($filter_status) {
    $where_clauses[] = "l.status = :status";
    $params[':status'] = $filter_status;
}

// Filtro de Empresa (apenas para Superadmin)
if ($filter_empresa) {
    $where_clauses[] = "l.id_empresa_master = :empresa_id_filter";
    $params[':empresa_id_filter'] = $filter_empresa;
}

// Busca por nome, email ou empresa
if ($search) {
    $where_clauses[] = "(l.nome LIKE :search OR l.email LIKE :search OR l.empresa LIKE :search OR l.whatsapp LIKE :search)";
    $params[':search'] = "%{$search}%";
}

// Restrição por empresa (Admin vê apenas leads da sua empresa)
if ($user_role === 'administrador' && $user_empresa_id) {
    $where_clauses[] = "l.id_empresa_master = :user_empresa_id";
    $params[':user_empresa_id'] = $user_empresa_id;
}

// Monta WHERE final
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// ===== BUSCA LEADS =====
try {
    $sql = "SELECT l.*, e.nome AS nome_empresa_parceira
            FROM leads l
            LEFT JOIN empresas e ON l.id_empresa_master = e.id
            {$where_sql}
            ORDER BY l.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas rápidas
    $sql_stats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'novo' THEN 1 ELSE 0 END) as novos,
                    SUM(CASE WHEN status = 'contatado' THEN 1 ELSE 0 END) as contatados,
                    SUM(CASE WHEN status = 'qualificado' THEN 1 ELSE 0 END) as qualificados,
                    SUM(CASE WHEN status = 'convertido' THEN 1 ELSE 0 END) as convertidos
                  FROM leads l
                  " . ($user_role === 'administrador' && $user_empresa_id ? "WHERE l.id_empresa_master = :empresa_id" : "");
    
    $stmt_stats = $pdo->prepare($sql_stats);
    if ($user_role === 'administrador' && $user_empresa_id) {
        $stmt_stats->execute([':empresa_id' => $user_empresa_id]);
    } else {
        $stmt_stats->execute();
    }
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar leads: " . $e->getMessage());
    $leads = [];
    $stats = ['total' => 0, 'novos' => 0, 'contatados' => 0, 'qualificados' => 0, 'convertidos' => 0];
}
?>

<div class="container-fluid py-4">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="bi bi-people-fill"></i> Leads Capturados</h1>
            <p class="text-muted">Gerenciamento de interessados na plataforma</p>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total</h6>
                    <h3 class="mb-0"><?= $stats['total'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Novos</h6>
                    <h3 class="mb-0 text-primary"><?= $stats['novos'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Contatados</h6>
                    <h3 class="mb-0 text-info"><?= $stats['contatados'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Qualificados</h6>
                    <h3 class="mb-0 text-warning"><?= $stats['qualificados'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Convertidos</h6>
                    <h3 class="mb-0 text-success"><?= $stats['convertidos'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" action="leads.php" class="card p-3 mb-4 bg-light">
        <div class="row g-3 align-items-end">
            <!-- Busca -->
            <div class="col-md-4">
                <label for="search" class="form-label">Buscar</label>
                <input type="text" name="search" id="search" class="form-control" 
                       placeholder="Nome, email, empresa..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <!-- Status -->
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="novo" <?= ($filter_status == 'novo') ? 'selected' : '' ?>>Novo</option>
                    <option value="contatado" <?= ($filter_status == 'contatado') ? 'selected' : '' ?>>Contatado</option>
                    <option value="qualificado" <?= ($filter_status == 'qualificado') ? 'selected' : '' ?>>Qualificado</option>
                    <option value="convertido" <?= ($filter_status == 'convertido') ? 'selected' : '' ?>>Convertido</option>
                    <option value="perdido" <?= ($filter_status == 'perdido') ? 'selected' : '' ?>>Perdido</option>
                </select>
            </div>

            <?php if ($user_role === 'superadmin'): ?>
            <!-- Empresa (só para superadmin) -->
            <div class="col-md-3">
                <label for="empresa_id" class="form-label">Empresa Parceira</label>
                <select name="empresa_id" id="empresa_id" class="form-select">
                    <option value="">Todas</option>
                    <?php
                    $empresas = $pdo->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll();
                    foreach ($empresas as $empresa) {
                        $selected = ($filter_empresa == $empresa['id']) ? 'selected' : '';
                        echo "<option value='{$empresa['id']}' {$selected}>" . htmlspecialchars($empresa['nome']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Botões -->
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Buscar
                </button>
            </div>
        </div>
    </form>

    <!-- Tabela de Leads -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Contato</th>
                            <th>Empresa do Lead</th>
                            <?php if ($user_role === 'superadmin'): ?>
                            <th>Empresa Parceira</th>
                            <th>UTM Source</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                        <tr>
                            <td colspan="<?= $user_role === 'superadmin' ? '8' : '6' ?>" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">Nenhum lead encontrado</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($leads as $lead): 
                            // Define classe do badge por status
                            $badge_class = match($lead['status']) {
                                'novo' => 'bg-primary',
                                'contatado' => 'bg-info',
                                'qualificado' => 'bg-warning text-dark',
                                'convertido' => 'bg-success',
                                'perdido' => 'bg-secondary',
                                default => 'bg-light text-dark'
                            };
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($lead['nome']) ?></strong>
                                <?php if ($lead['mensagem']): ?>
                                <br><small class="text-muted" title="<?= htmlspecialchars($lead['mensagem']) ?>">
                                    <i class="bi bi-chat-left-text"></i> Tem mensagem
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($lead['email']) ?>" class="text-decoration-none">
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($lead['email']) ?>
                                </a>
                                <br>
                                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" 
                                   target="_blank" class="text-decoration-none text-success">
                                    <i class="bi bi-whatsapp"></i> <?= htmlspecialchars($lead['whatsapp']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($lead['empresa'] ?? '-') ?></td>
                            <?php if ($user_role === 'superadmin'): ?>
                            <td>
                                <strong class="text-primary">
                                    <?= htmlspecialchars($lead['nome_empresa_parceira'] ?? 'Direto') ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($lead['utm_source']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($lead['utm_source']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst($lead['status']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($lead['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="lead_detail.php?id=<?= $lead['id'] ?>" class="btn btn-outline-primary" title="Ver detalhes">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($lead['status'] !== 'convertido'): ?>
                                    <button onclick="enviarKYCRapido(<?= $lead['id'] ?>)" 
                                            class="btn btn-outline-success" 
                                            title="Enviar Formulário KYC">
                                        <i class="bi bi-file-earmark-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Informações -->
    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle"></i> <strong>Sobre os Leads:</strong>
        Leads são registrados automaticamente quando visitantes preenchem o formulário de contato.
        Os dados são enviados ao Google Analytics como evento de conversão.
        <?php if ($user_role === 'superadmin'): ?>
        <br><em>Integração com CRM será configurada em breve.</em>
        <?php endif; ?>
    </div>
</div>

<script>
// Função rápida para enviar KYC diretamente da lista
function enviarKYCRapido(leadId) {
    if (!confirm('Deseja enviar o formulário KYC para este lead?')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    fetch('ajax_send_kyc_to_lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lead_id: leadId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message + '\n\nLink gerado com sucesso!');
            // Mostra o link copiável
            prompt('Link do formulário KYC (Ctrl+C para copiar):', data.kyc_url);
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
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro de conexão');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}
</script>

<?php require_once 'footer.php'; ?>
