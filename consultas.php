<?php
$page_title = 'Histórico';
require_once 'bootstrap.php'; // Usa o novo sistema de inicialização
require 'header.php';

// As variáveis $user_role, $user_id, $user_empresa_id já vêm do bootstrap.php

$consultas = [];
$error = null;

try {
    $sql = "";
    $params = [];

    if ($user_role === 'superadmin') {
        // Superadmin vê tudo, unindo consultas de usuários e superadmins
        $sql = "(SELECT c.id, c.cnpj, c.razao_social, c.created_at, u.nome as nome_usuario, e.nome as nome_empresa 
                FROM consultas c 
                JOIN usuarios u ON c.usuario_id = u.id AND c.usuario_id IN (SELECT id FROM usuarios)
                LEFT JOIN empresas e ON u.empresa_id = e.id)
                UNION ALL
                (SELECT c.id, c.cnpj, c.razao_social, c.created_at, sa.nome as nome_usuario, 'N/A (Superadmin)' as nome_empresa
                FROM consultas c 
                JOIN superadmin sa ON c.usuario_id = sa.id AND c.usuario_id IN (SELECT id FROM superadmin))
                ORDER BY id DESC";
    } elseif (in_array($user_role, ['admin', 'administrador'])) {
        // Admin vê todas as consultas da sua empresa
        $sql = "SELECT c.id, c.cnpj, c.razao_social, c.created_at, u.nome as nome_usuario 
                FROM consultas c 
                JOIN usuarios u ON c.usuario_id = u.id 
                WHERE u.empresa_id = :empresa_id 
                ORDER BY c.id DESC";
        $params = [':empresa_id' => $user_empresa_id];
    } else {
        // Usuário comum vê apenas as suas próprias consultas
        $sql = "SELECT id, cnpj, razao_social, created_at 
                FROM consultas 
                WHERE usuario_id = :usuario_id 
                ORDER BY id DESC";
        $params = [':usuario_id' => $user_id];
    }

    if ($sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Erro ao buscar o histórico de consultas.";
    error_log("Erro em consultas.php: " . $e->getMessage());
}
?>

<h2 class="mb-4">Histórico de Consultas de CNPJ</h2>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!empty($consultas)): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th><th>CNPJ</th><th>Razão Social</th><th>Data</th>
                    <?php if ($user_role !== 'usuario') echo '<th>Usuário</th>'; ?>
                    <?php if ($user_role === 'superadmin') echo '<th>Empresa</th>'; ?>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consultas as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['id']) ?></td>
                        <td><?= htmlspecialchars($c['cnpj']) ?></td>
                        <td><?= htmlspecialchars($c['razao_social']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
                        <?php if ($user_role !== 'usuario') echo '<td>'.htmlspecialchars($c['nome_usuario'] ?? 'N/A').'</td>'; ?>
                        <?php if ($user_role === 'superadmin') echo '<td>'.htmlspecialchars($c['nome_empresa'] ?? 'N/A').'</td>'; ?>
                        <td><button class="btn btn-sm btn-info view-btn" data-id="<?= $c['id'] ?>">Visualizar</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info text-center">Nenhuma consulta foi encontrada no histórico.</div>
<?php endif; ?>

<div class="modal fade" id="viewConsultaModal" tabindex="-1" aria-labelledby="viewConsultaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewConsultaModalLabel">Detalhes da Consulta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="consulta-details-content">
                <p class="text-center">Carregando...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewModal = new bootstrap.Modal(document.getElementById('viewConsultaModal'));
    const contentContainer = document.getElementById('consulta-details-content');

    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const consultaId = this.getAttribute('data-id');
            
            contentContainer.innerHTML = '<p class="text-center">Carregando...</p>';
            viewModal.show();

            fetch(`ajax_get_consulta.php?id=${consultaId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Falha na rede ou erro do servidor.');
                    }
                    return response.text();
                })
                .then(html => {
                    contentContainer.innerHTML = html;
                })
                .catch(error => {
                    contentContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                });
        });
    });
});
</script>

<?php require 'footer.php'; ?>
