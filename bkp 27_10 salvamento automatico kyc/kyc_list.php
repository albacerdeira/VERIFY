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

$casos = [];
try {
    if ($user_role === 'superadmin') {
        $stmt = $pdo->query(
            "SELECT 
                ke.id, ke.razao_social, ke.cnpj, ke.data_criacao, ke.status, 
                e.nome AS nome_empresa_master,
                kc.nome_completo AS nome_cliente
             FROM kyc_empresas ke
             LEFT JOIN empresas e ON ke.id_empresa_master = e.id
             LEFT JOIN kyc_clientes kc ON ke.cliente_id = kc.id
             ORDER BY ke.data_criacao DESC"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT 
                ke.id, ke.razao_social, ke.cnpj, ke.data_criacao, ke.status,
                kc.nome_completo AS nome_cliente
             FROM kyc_empresas ke
             LEFT JOIN kyc_clientes kc ON ke.cliente_id = kc.id
             WHERE ke.id_empresa_master = :empresa_id 
             ORDER BY ke.data_criacao DESC"
        );
        $stmt->execute([':empresa_id' => $user_empresa_id]);
    }
    $casos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<div class='container p-4'><div class='alert alert-danger'>Erro ao buscar os dados: " . htmlspecialchars($e->getMessage()) . "</div></div>";
}

?>

<style>
    /* Estilos (sem alteração) */
</style>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-3">Painel de Análise KYC</h2>
    <p class="lead text-muted mb-4">Lista de submissões de clientes para análise da equipe de compliance.</p>

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
                    <th scope="col">Status</th>
                    <th scope="col">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($casos)): ?>
                    <?php foreach ($casos as $caso): ?>
                        <tr>
                            <th scope="row">#<?php echo htmlspecialchars($caso['id']); ?></th>
                            <td><?php echo htmlspecialchars($caso['nome_cliente'] ?? 'Usuário Interno'); ?></td>
                            <td><?php echo htmlspecialchars($caso['razao_social']); ?></td>
                            <td><?php echo htmlspecialchars($caso['cnpj'] ?? 'N/A'); ?></td>
                            <?php if ($user_role === 'superadmin'): ?>
                                <td><?php echo htmlspecialchars($caso['nome_empresa_master'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($caso['data_criacao']))); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($caso['status']))); ?>">
                                    <?php echo htmlspecialchars($caso['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="kyc_evaluate.php?id=<?php echo $caso['id']; ?>" class="btn btn-sm btn-primary">Analisar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo ($user_role === 'superadmin') ? '8' : '7'; ?>" class="text-center py-4 text-muted">Nenhuma submissão encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>
