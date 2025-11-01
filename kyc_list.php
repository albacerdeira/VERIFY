<?php
$page_title = 'Painel de Análise KYC';
require_once 'bootstrap.php';

// Controle de acesso (sem alteração)
$allowed_roles = ['analista', 'superadmin', 'admin', 'administrador'];
if (!in_array($user_role, $allowed_roles)) {
    require 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require 'footer.php';
    exit();
}

require 'header.php';

// Função helper para os Badges
function getBadgeClass($status) {
    switch ($status) {
        case 'Aprovado':
            return 'bg-success';
        case 'Reprovado':
            return 'bg-danger';
        case 'Em Preenchimento':
            return 'bg-secondary';
        case 'Em Análise':
            return 'bg-info';
        case 'Novo Registro':
            return 'bg-primary';
        case 'Pendenciado':
            return 'bg-warning text-dark';
        default:
            return 'bg-light text-dark';
    }
}

// --- LÓGICA DE FILTRAGEM E CLASSIFICAÇÃO ---
$params = [];
$where_clauses = [];

// Filtro de Status
$filter_status = $_GET['status'] ?? '';
if ($filter_status) {
    $where_clauses[] = "ke.status = :status";
    $params[':status'] = $filter_status;
}

// Filtro de Empresa (apenas para Superadmin)
$filter_empresa = ($user_role === 'superadmin' && isset($_GET['empresa_id'])) ? $_GET['empresa_id'] : '';
if ($filter_empresa) {
    $where_clauses[] = "ke.id_empresa_master = :empresa_id_filter";
    $params[':empresa_id_filter'] = $filter_empresa;
}

// Cláusula WHERE base para não-superadmins
if ($user_role !== 'superadmin' && $user_empresa_id) {
    $where_clauses[] = "ke.id_empresa_master = :empresa_id_session";
    $params[':empresa_id_session'] = $user_empresa_id;
}

// Monta a string da cláusula WHERE
$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

// Lógica de Ordenação
$sort_options = ['data_criacao' => 'ke.data_criacao', 'status' => 'ke.status', 'razao_social' => 'ke.razao_social', 'id' => 'ke.id'];
$sort_by = $_GET['sort_by'] ?? 'data_criacao';
$sort_order = strtoupper($_GET['sort_order'] ?? 'DESC');
if (!array_key_exists($sort_by, $sort_options) || !in_array($sort_order, ['ASC', 'DESC'])) {
    $sort_by = 'data_criacao';
    $sort_order = 'DESC';
}
$order_by_sql = "ORDER BY {$sort_options[$sort_by]} {$sort_order}";

$casos = [];
try {
    // ==========================================================
    // SQL DEFINITIVO (replicando a lógica de JOIN do log)
    // ==========================================================
    $sql = "SELECT 
                ke.id, ke.razao_social, ke.cnpj, ke.data_criacao, ke.status, 
                e.nome AS nome_empresa_master,
                kc.nome_completo AS nome_cliente,
                
                /* FLAGS DE ALERTA CEIS, CNEP, PEP */
                COALESCE(ka.av_check_ceis_ok, 1) as tem_ceis,
                COALESCE(ka.av_check_ceis_pf_ok, 1) as tem_ceis_pf,
                COALESCE(ka.av_check_cnep_ok, 1) as tem_cnep,
                COALESCE(ka.av_check_cnep_pf_ok, 1) as tem_cnep_pf,
                (SELECT COUNT(*) FROM kyc_socios ks WHERE ks.empresa_id = ke.id AND ks.is_pep = 1) as tem_pep,
                
                /* Busca o nome do analista fazendo JOIN com 'usuarios' e 'superadmin'
                   baseado no 'usuario_id' do último log que NÃO pertence ao cliente.
                */
                (SELECT COALESCE(u.nome, sa.nome) 
                 FROM kyc_log_atividades klog
                 LEFT JOIN usuarios u ON klog.usuario_id = u.id
                 LEFT JOIN superadmin sa ON klog.usuario_id = sa.id -- <-- ADICIONADO
                 WHERE klog.kyc_empresa_id = ke.id 
                   AND klog.usuario_id != ke.cliente_id
                 ORDER BY klog.id DESC 
                 LIMIT 1
                ) AS nome_analista
                
             FROM kyc_empresas ke
             LEFT JOIN empresas e ON ke.id_empresa_master = e.id
             LEFT JOIN kyc_clientes kc ON ke.cliente_id = kc.id
             LEFT JOIN kyc_avaliacoes ka ON ke.id = ka.kyc_empresa_id
             {$where_sql}
             {$order_by_sql}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<div class='container p-4'><div class='alert alert-danger'>Erro ao buscar os dados: " . htmlspecialchars($e.getMessage()) . "</div></div>";
}

?>

<style>
    /* Estilos (sem alteração) */
</style>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-3">Painel de Análise KYC</h2>
    <p class="lead text-muted mb-4">Lista de submissões de clientes para análise da equipe de compliance.</p>

    <form method="GET" action="kyc_list.php" class="card p-3 mb-4 bg-light">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="Em Preenchimento" <?= ($filter_status == 'Em Preenchimento') ? 'selected' : '' ?>>Em Preenchimento</option>
                    <option value="Novo Registro" <?= ($filter_status == 'Novo Registro') ? 'selected' : '' ?>>Novo Registro</option>
                    <option value="Em Análise" <?= ($filter_status == 'Em Análise') ? 'selected' : '' ?>>Em Análise</option>
                    <option value="Pendenciado" <?= ($filter_status == 'Pendenciado') ? 'selected' : '' ?>>Pendenciado</option>
                    <option value="Aprovado" <?= ($filter_status == 'Aprovado') ? 'selected' : '' ?>>Aprovado</option>
                    <option value="Reprovado" <?= ($filter_status == 'Reprovado') ? 'selected' : '' ?>>Reprovado</option>
                </select>
            </div>
            <?php if ($user_role === 'superadmin'): ?>
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
            <div class="col-md-2">
                <label for="sort_by" class="form-label">Ordenar por</label>
                <select name="sort_by" id="sort_by" class="form-select">
                    <option value="data_criacao" <?= ($sort_by == 'data_criacao') ? 'selected' : '' ?>>Data</option>
                    <option value="status" <?= ($sort_by == 'status') ? 'selected' : '' ?>>Status</option>
                    <option value="razao_social" <?= ($sort_by == 'razao_social') ? 'selected' : '' ?>>Razão Social</option>
                    <option value="id" <?= ($sort_by == 'id') ? 'selected' : '' ?>>ID</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="sort_order" class="form-label">Ordem</label>
                <select name="sort_order" id="sort_order" class="form-select">
                    <option value="DESC" <?= ($sort_order == 'DESC') ? 'selected' : '' ?>>Decrescente</option>
                    <option value="ASC" <?= ($sort_order == 'ASC') ? 'selected' : '' ?>>Crescente</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="thead-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Razão Social</th>
                    <th scope="col">CNPJ</th>
                    <?php if ($user_role === 'superadmin'): ?>
                        <th scope="col">Empresa Parceira</th>
                    <?php endif; ?>
                    <th scope="col">Data de Envio</th>
                    <th scope="col">Analista</th>
                    <th scope="col">Status</th>
                    <th scope="col">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($casos)): ?>
                    <?php foreach ($casos as $caso): ?>
                        <tr>
                            <th scope="row">#<?= htmlspecialchars($caso['id']) ?></th>
                            <td><?= htmlspecialchars($caso['nome_cliente'] ?? 'Usuário Interno') ?></td>
                            <td><?= htmlspecialchars($caso['razao_social']) ?></td>
                            <td><?= htmlspecialchars($caso['cnpj'] ?? 'N/A') ?></td>
                            <?php if ($user_role === 'superadmin'): ?>
                                <td><?= htmlspecialchars($caso['nome_empresa_master'] ?? 'N/A') ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars(date("d/m/Y H:i", strtotime($caso['data_criacao']))) ?></td>
                            
                            <td><?= htmlspecialchars($caso['nome_analista'] ?? '—') ?></td>
                            <td>
                                <?php 
                                $badge_class = getBadgeClass($caso['status']);
                                
                                // Verificar se tem alertas
                                $tem_alerta_ceis = ($caso['tem_ceis'] == 0 || $caso['tem_ceis_pf'] == 0);
                                $tem_alerta_cnep = ($caso['tem_cnep'] == 0 || $caso['tem_cnep_pf'] == 0);
                                $tem_alerta_pep = ($caso['tem_pep'] > 0);
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= htmlspecialchars($caso['status']) ?>
                                </span>
                                
                                <!-- Ícones de Alerta -->
                                <div class="mt-1">
                                    <?php if ($tem_alerta_ceis): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-danger" 
                                       data-bs-toggle="tooltip" 
                                       data-bs-placement="top" 
                                       title="Alerta CEIS"></i>
                                    <?php endif; ?>
                                    
                                    <?php if ($tem_alerta_cnep): ?>
                                    <i class="bi bi-exclamation-diamond-fill text-warning" 
                                       data-bs-toggle="tooltip" 
                                       data-bs-placement="top" 
                                       title="Alerta CNEP"></i>
                                    <?php endif; ?>
                                    
                                    <?php if ($tem_alerta_pep): ?>
                                    <i class="bi bi-person-fill-exclamation" 
                                       style="color: #6f42c1;"
                                       data-bs-toggle="tooltip" 
                                       data-bs-placement="top" 
                                       title="Pessoa Exposta Politicamente"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="kyc_evaluate.php?id=<?= $caso['id'] ?>" class="btn btn-sm btn-primary">Analisar</a>
                                <?php if ($user_role === 'superadmin'): ?>
                                    <a href="kyc_delete.php?id=<?= $caso['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Atenção! Esta ação é irreversível e apagará todos os dados, documentos e logs associados a este cadastro. Deseja continuar?');">
                                        Excluir
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= ($user_role === 'superadmin') ? '9' : '8' ?>" class="text-center py-4 text-muted">Nenhuma submissão encontrada para os filtros aplicados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>