<?php
$page_title = 'Gerenciamento de Clientes';
require_once 'bootstrap.php';

// Acesso permitido para Superadmin, Admin e Analista
if (!$is_superadmin && !$is_admin && !$is_analista) {
    require 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require 'footer.php';
    exit();
}

require 'header.php';

$clientes = [];
try {
    if ($is_superadmin) {
        // Superadmin vê todos os clientes e a qual empresa pertencem
        $stmt = $pdo->query(
            "SELECT kc.id, kc.nome_completo, kc.email, kc.cpf, kc.status, kc.created_at, e.nome AS empresa_parceira\n             FROM kyc_clientes kc\n             LEFT JOIN empresas e ON kc.id_empresa_master = e.id\n             ORDER BY kc.created_at DESC"
        );
    } else {
        // Admin e Analista veem apenas os clientes da sua empresa
        $stmt = $pdo->prepare(
            "SELECT id, nome_completo, email, cpf, status, created_at \n             FROM kyc_clientes \n             WHERE id_empresa_master = ?\n             ORDER BY created_at DESC"
        );
        $stmt->execute([$user_empresa_id]);
    }
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<div class='container p-4'><div class='alert alert-danger'>Erro ao buscar os dados dos clientes: " . htmlspecialchars($e->getMessage()) . "</div></div>";
}
?>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-3">Gerenciamento de Clientes</h2>
    <p class="lead text-muted mb-4">Lista de clientes externos cadastrados.</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="thead-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Nome Completo</th>
                    <th scope="col">Email</th>
                    <th scope="col">CPF</th>
                    <th scope="col">Status</th>
                    <?php if ($is_superadmin): ?>
                        <th scope="col">Empresa Parceira</th>
                    <?php endif; ?>
                    <th scope="col">Data de Cadastro</th>
                    <th scope="col">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($clientes)): ?>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <th scope="row">#<?= htmlspecialchars($cliente['id']) ?></th>
                            <td><?= htmlspecialchars($cliente['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($cliente['email']) ?></td>
                            <td><?= htmlspecialchars($cliente['cpf'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-<?= ($cliente['status'] == 'ativo') ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst($cliente['status'])) ?></span></td>
                            <?php if ($is_superadmin): ?>
                                <td><?= htmlspecialchars($cliente['empresa_parceira'] ?? 'Nenhuma') ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars(date("d/m/Y H:i", strtotime($cliente['created_at']))) ?></td>
                            <td><a href="cliente_edit.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $is_superadmin ? '8' : '7' ?>" class="text-center py-4 text-muted">Nenhum cliente encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>