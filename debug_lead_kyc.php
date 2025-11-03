<?php
$page_title = 'Rastreamento Lead → KYC';
require_once 'bootstrap.php';

// Apenas superadmin e admin
if (!$is_superadmin && !$is_admin) {
    require_once 'header.php';
    echo "<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require_once 'footer.php';
    exit;
}

require_once 'header.php';

// Busca todos os leads com seus respectivos dados de conversão
$where_empresa = "";
$params = [];
if (!$is_superadmin && $user_empresa_id) {
    $where_empresa = " WHERE l.id_empresa_master = :empresa_id";
    $params[':empresa_id'] = $user_empresa_id;
}

$sql = "SELECT 
    l.id as lead_id,
    l.nome as lead_nome,
    l.email as lead_email,
    l.status as lead_status,
    l.data_criacao as lead_criado,
    l.id_empresa_master as lead_empresa_id,
    kc.id as cliente_id,
    kc.nome_completo as cliente_nome,
    kc.email as cliente_email,
    kc.lead_id as cliente_lead_id_ref,
    kc.origem as cliente_origem,
    kc.created_at as cliente_criado,
    kc.id_empresa_master as cliente_empresa_id,
    ke.id as kyc_id,
    ke.razao_social as kyc_razao,
    ke.cnpj as kyc_cnpj,
    ke.status as kyc_status,
    ke.data_criacao as kyc_criado,
    ke.id_empresa_master as kyc_empresa_id
FROM leads l
LEFT JOIN kyc_clientes kc ON kc.lead_id = l.id
LEFT JOIN kyc_empresas ke ON ke.cliente_id = kc.id
$where_empresa
ORDER BY l.data_criacao DESC
LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Análise de problemas
$problemas = [
    'sem_conversao' => 0,
    'sem_kyc' => 0,
    'empresa_diferente' => 0,
    'lead_id_null' => 0
];

foreach ($results as $row) {
    if (!$row['cliente_id']) {
        $problemas['sem_conversao']++;
    } elseif (!$row['kyc_id']) {
        $problemas['sem_kyc']++;
    }
    
    if ($row['cliente_id'] && !$row['cliente_lead_id_ref']) {
        $problemas['lead_id_null']++;
    }
    
    if ($row['cliente_id'] && $row['lead_empresa_id'] != $row['cliente_empresa_id']) {
        $problemas['empresa_diferente']++;
    }
}
?>

<div class="container mt-4">
    <h2><i class="bi bi-diagram-3"></i> Rastreamento: Lead → Cliente → KYC</h2>
    <p class="text-muted">Visualização completa do funil de conversão</p>

    <!-- Métricas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body text-center">
                    <h3><?= count($results) ?></h3>
                    <p class="mb-0">Total Leads</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-<?= $problemas['sem_conversao'] > 0 ? 'warning' : 'success' ?>">
                <div class="card-body text-center">
                    <h3><?= $problemas['sem_conversao'] ?></h3>
                    <p class="mb-0">Sem Conversão</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-<?= $problemas['sem_kyc'] > 0 ? 'info' : 'success' ?>">
                <div class="card-body text-center">
                    <h3><?= $problemas['sem_kyc'] ?></h3>
                    <p class="mb-0">Sem KYC</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-<?= ($problemas['empresa_diferente'] + $problemas['lead_id_null']) > 0 ? 'danger' : 'success' ?>">
                <div class="card-body text-center">
                    <h3><?= $problemas['empresa_diferente'] + $problemas['lead_id_null'] ?></h3>
                    <p class="mb-0">Problemas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-header">
            <strong>Últimos 50 Leads</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Lead</th>
                            <th>Cliente</th>
                            <th>KYC</th>
                            <th>Status</th>
                            <th>Problemas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <?php
                            $alertas = [];
                            if (!$row['cliente_id']) {
                                $alertas[] = 'Não converteu';
                            } else {
                                if (!$row['cliente_lead_id_ref']) {
                                    $alertas[] = 'lead_id NULL';
                                }
                                if ($row['lead_empresa_id'] != $row['cliente_empresa_id']) {
                                    $alertas[] = 'Empresa diferente';
                                }
                                if (!$row['kyc_id']) {
                                    $alertas[] = 'KYC não preenchido';
                                }
                            }
                            ?>
                            <tr class="<?= !empty($alertas) ? 'table-warning' : '' ?>">
                                <td>
                                    <strong>#<?= $row['lead_id'] ?></strong><br>
                                    <small><?= htmlspecialchars($row['lead_nome']) ?></small><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['lead_email']) ?></small>
                                </td>
                                <td>
                                    <?php if ($row['cliente_id']): ?>
                                        ✅ #<?= $row['cliente_id'] ?><br>
                                        <small><?= htmlspecialchars($row['cliente_nome']) ?></small><br>
                                        <small class="text-muted">Origem: <?= htmlspecialchars($row['cliente_origem'] ?? 'NULL') ?></small>
                                    <?php else: ?>
                                        <span class="text-danger">❌ Não converteu</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['kyc_id']): ?>
                                        ✅ #<?= $row['kyc_id'] ?><br>
                                        <small><?= htmlspecialchars($row['kyc_razao']) ?></small><br>
                                        <small class="text-muted"><?= htmlspecialchars($row['kyc_cnpj']) ?></small>
                                    <?php elseif ($row['cliente_id']): ?>
                                        <span class="text-warning">⏳ Pendente</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['lead_status']) ?></span><br>
                                    <?php if ($row['kyc_status']): ?>
                                        <span class="badge bg-primary"><?= htmlspecialchars($row['kyc_status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($alertas)): ?>
                                        <?php foreach ($alertas as $alerta): ?>
                                            <span class="badge bg-danger d-block mb-1"><?= htmlspecialchars($alerta) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-success">✅ OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Explicação -->
    <div class="card mt-4 border-info">
        <div class="card-header bg-info text-white">
            <strong>ℹ️ Como funciona o fluxo correto:</strong>
        </div>
        <div class="card-body">
            <ol>
                <li><strong>Lead criado</strong> - Sistema gera URL com <code>&lead_id=X</code></li>
                <li><strong>Lead se registra</strong> - Cliente criado com <code>lead_id=X</code> e <code>origem='lead_conversion'</code></li>
                <li><strong>Cliente preenche KYC</strong> - Formulário KYC criado vinculado ao <code>cliente_id</code></li>
                <li><strong>Aparece no dashboard</strong> - KYC contabilizado com status correto</li>
            </ol>
            
            <hr>
            
            <h6>Problemas comuns:</h6>
            <ul>
                <li><strong>lead_id NULL:</strong> Cliente registrou mas não foi associado ao lead (execute migração SQL)</li>
                <li><strong>Empresa diferente:</strong> Lead e Cliente em empresas diferentes (inconsistência de dados)</li>
                <li><strong>KYC não preenchido:</strong> Cliente registrou mas não completou o formulário</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
