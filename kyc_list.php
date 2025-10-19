<?php
$page_title = 'Painel de Análise KYC';
require_once 'bootstrap.php';

// Define os papéis que podem acessar a página.
$allowed_roles = ['analista', 'superadmin', 'admin', 'administrador'];

if (!in_array($user_role, $allowed_roles)) {
    require 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado. Você não tem permissão para visualizar esta página.</div></div>";
    require 'footer.php';
    exit();
}

require 'header.php';

$casos = [];
try {
    // --- LÓGICA DE FILTRAGEM FINAL ---
    // Superadmin vê tudo. Admins e Analistas veem apenas os da sua empresa.
    if ($user_role === 'superadmin') {
        // Superadmin busca todos os casos e junta o nome da empresa parceira.
        $stmt = $pdo->query(
            "SELECT ke.id, ke.razao_social, ke.cnpj, ke.data_criacao, ke.status, e.nome AS nome_empresa_master
             FROM kyc_empresas ke
             LEFT JOIN empresas e ON ke.id_empresa_master = e.id
             ORDER BY ke.data_criacao DESC"
        );
        $casos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Admin e Analista buscam apenas os casos submetidos através do link de sua empresa.
        if ($user_empresa_id) {
            $stmt = $pdo->prepare(
                "SELECT id, razao_social, cnpj, data_criacao, status FROM kyc_empresas WHERE id_empresa_master = :empresa_id ORDER BY data_criacao DESC"
            );
            $stmt->execute([':empresa_id' => $user_empresa_id]);
            $casos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Se for admin/analista mas não tiver um ID de empresa na sessão, a lista fica vazia por segurança.
            $casos = [];
        }
    }
} catch (Exception $e) {
    // Exibe uma mensagem de erro amigável se a consulta falhar.
    echo "<div class='container p-4'><div class='alert alert-danger'>Erro ao buscar os dados: " . htmlspecialchars($e->getMessage()) . "</div></div>";
}

?>

<style>
    .status-badge {
        display: inline-block;
        padding: 0.5em 0.75em;
        border-radius: 0.25rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: white;
        text-transform: capitalize;
        min-width: 90px;
        text-align: center;
    }
    .status-enviado { background-color: #007bff; } 
    .status-em-analise { background-color: #ffc107; color: #212529; } 
    .status-aprovado { background-color: #28a745; } 
    .status-reprovado { background-color: #dc3545; } 
    .status-pendenciado { background-color: #6c757d; } 
</style>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-3">Painel de Análise KYC</h2>
    <p class="lead text-muted mb-4">Lista de submissões de clientes para análise da equipe de compliance.</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="thead-light">
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Razão Social</th>
                    <th scope="col">CNPJ</th>
                    <?php if ($user_role === 'superadmin'): ?>
                        <th scope="col">Empresa</th>
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
                        <td colspan="<?php echo ($user_role === 'superadmin') ? '7' : '6'; ?>" class="text-center py-4 text-muted">Nenhuma submissão encontrada para sua empresa.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'footer.php'; ?>
