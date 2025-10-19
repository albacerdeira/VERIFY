<?php
$page_title = 'Empresas';
require_once 'bootstrap.php';

// Garante que apenas superadmins possam acessar
if (!$is_superadmin) {
    require_once 'header.php';
    echo "<div class='container'><div class='alert alert-danger mt-4'>Acesso negado. Você não tem permissão para visualizar esta página.</div></div>";
    require_once 'footer.php';
    exit;
}

// ... (A função gerarSlug e a lógica de processamento do formulário POST continuam aqui, sem alterações) ...
function gerarSlug($texto) {
    $texto = preg_replace('~[^\pL\d]+~u', '-', $texto);
    $texto = iconv('utf-8', 'us-ascii//TRANSLIT', $texto);
    $texto = preg_replace('~[^-\w]+~', '', $texto);
    $texto = trim($texto, '-');
    $texto = preg_replace('~-+~', '-', $texto);
    $texto = strtolower($texto);
    if (empty($texto)) { return 'n-a-' . uniqid(); }
    return $texto;
}

$error = null;
$success = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();
        if (isset($_POST['add_empresa'])) {
            $nome_empresa = trim($_POST['nome_empresa']);
            $email_empresa = trim($_POST['email_empresa']);
            $nome_admin = trim($_POST['nome_admin']);
            $email_admin = trim($_POST['email_admin']);
            $password_admin = $_POST['password_admin'];

            $stmt = $pdo->prepare("INSERT INTO empresas (nome, email, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$nome_empresa, $email_empresa, $_SESSION['user_id']]);
            $empresa_id = $pdo->lastInsertId();

            // Deixa o slug NULO para que o admin do parceiro possa definir na primeira vez
            $stmt_config = $pdo->prepare("INSERT INTO configuracoes_whitelabel (empresa_id, nome_empresa, slug) VALUES (?, ?, NULL)");
            $stmt_config->execute([$empresa_id, $nome_empresa]);

            $stmt_admin = $pdo->prepare("INSERT INTO usuarios (nome, email, password, empresa_id, role) VALUES (?, ?, ?, ?, 'administrador')");
            $stmt_admin->execute([$nome_admin, $email_admin, password_hash($password_admin, PASSWORD_DEFAULT), $empresa_id]);

            $success = "Empresa e administrador criados com sucesso!";
        } elseif (isset($_POST['delete_empresa'])) {
            $empresa_id = $_POST['empresa_id'];
            $pdo->prepare("DELETE FROM configuracoes_whitelabel WHERE empresa_id = ?")->execute([$empresa_id]);
            $pdo->prepare("DELETE FROM usuarios WHERE empresa_id = ?")->execute([$empresa_id]);
            $pdo->prepare("DELETE FROM empresas WHERE id = ?")->execute([$empresa_id]);
            $success = "Empresa e todos os seus dados associados foram excluídos com sucesso.";
        }
        $pdo->commit();
        header("Location: empresas.php?success=" . urlencode($success));
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $error = "Erro: " . $e->getMessage();
}

if (isset($_GET['success'])) {
    $success = htmlspecialchars(urldecode($_GET['success']));
}

$empresas = $pdo->query("SELECT id, nome, email FROM empresas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Carrega o header DEPOIS de toda a lógica de processamento
require_once 'header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Gerenciamento de Empresas</h2>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <section class="card mb-4">
        <div class="card-header"><h5 class="my-1">Adicionar Nova Empresa e Administrador</h5></div>
        <div class="card-body">
            <form action="empresas.php" method="post" class="row g-3">
                <input type="hidden" name="add_empresa" value="1">
                <div class="col-12"><h6 class="mb-0">Dados da Empresa</h6></div>
                <div class="col-md-6"><label for="add-nome-empresa" class="form-label">Nome da Empresa</label><input type="text" class="form-control" id="add-nome-empresa" name="nome_empresa" required></div>
                <div class="col-md-6"><label for="add-email-empresa" class="form-label">Email da Empresa</label><input type="email" class="form-control" id="add-email-empresa" name="email_empresa" required></div>
                <div class="col-12 mt-4"><h6 class="mb-0">Dados do Administrador Principal</h6></div>
                <div class="col-md-4"><label for="add-nome-admin" class="form-label">Nome do Admin</label><input type="text" class="form-control" id="add-nome-admin" name="nome_admin" required></div>
                <div class="col-md-4"><label for="add-email-admin" class="form-label">Email do Admin</label><input type="email" class="form-control" id="add-email-admin" name="email_admin" required></div>
                <div class="col-md-4"><label for="add-password-admin" class="form-label">Senha</label><input type="password" class="form-control" id="add-password-admin" name="password_admin" required></div>
                <div class="col-12"><button type="submit" class="btn btn-primary mt-3">Salvar Empresa</button></div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h5 class="my-1">Empresas Cadastradas</h5></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="thead-dark"><tr><th>ID</th><th>Nome</th><th>Email</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($empresas as $empresa): ?>
                        <tr>
                            <td><?= htmlspecialchars($empresa['id']) ?></td>
                            <td><?= htmlspecialchars($empresa['nome']) ?></td>
                            <td><?= htmlspecialchars($empresa['email']) ?></td>
                            <td class="text-end">
                                <a href="configuracoes.php?id=<?= $empresa['id'] ?>" class="btn btn-sm btn-info">Editar / Configurar</a>
                                <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteEmpresaModal" data-id="<?= $empresa['id'] ?>" data-nome="<?= htmlspecialchars($empresa['nome']) ?>">Excluir</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="deleteEmpresaModal" tabindex="-1" aria-labelledby="deleteEmpresaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="empresas.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteEmpresaModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="delete_empresa" value="1">
                    <input type="hidden" id="delete-empresa-id" name="empresa_id">
                    <p>Você tem certeza que deseja excluir a empresa <strong id="delete-empresa-nome"></strong>?</p>
                    <p class="text-danger"><strong>Atenção:</strong> Esta ação é irreversível.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Excluir Definitivamente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Script para o Modal de Exclusão
    const deleteModal = document.getElementById('deleteEmpresaModal');
    if(deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome');
            
            const modal = this;
            modal.querySelector('#delete-empresa-id').value = id;
            modal.querySelector('#delete-empresa-nome').textContent = nome;
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>