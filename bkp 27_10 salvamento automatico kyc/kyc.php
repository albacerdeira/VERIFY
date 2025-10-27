<?php
$page_title = 'Análise de KYC';
require 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Apenas admins e superadmins podem acessar esta página
if (!in_array($_SESSION['user_role'], ['admin', 'administrador', 'superadmin'])) {
    echo "<div class='alert alert-danger'>Você não tem permissão para acessar esta página.</div>";
    require 'footer.php';
    exit;
}

$error = null;
$pending_submissions = [];

try {
    $sql = "SELECT 
                s.id, 
                s.received_at, 
                c.razao_social, 
                c.cnpj
            FROM kyc_submissions s
            JOIN kyc_company_data c ON s.id = c.submission_id
            WHERE s.status = 'pendente'
            ORDER BY s.received_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pending_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erro ao buscar as submissões pendentes de KYC.";
    error_log("Erro em kyc.php: " . $e->getMessage());
}

?>

<h2 class="mb-4">Painel de Análise de KYC</h2>

<p>As submissões de KYC recebidas do sistema parceiro e que precisam de análise estão listadas abaixo.</p>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!empty($pending_submissions)): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>ID da Submissão</th>
                    <th>Data de Recebimento</th>
                    <th>Razão Social</th>
                    <th>CNPJ</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_submissions as $sub): ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['id']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sub['received_at']))) ?></td>
                        <td><?= htmlspecialchars($sub['razao_social']) ?></td>
                        <td><?= htmlspecialchars($sub['cnpj']) ?></td>
                        <td>
                            <a href="kyc_review.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-primary">Analisar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info text-center">
        <i class="fas fa-check-circle mr-2"></i>Não há nenhuma submissão de KYC pendente no momento.
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
