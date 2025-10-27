<?php
$page_title = 'Usuários';
require_once 'bootstrap.php';

// Garante que apenas administradores ou superadmins acessem a página.
if (!$is_admin && !$is_superadmin) {
    header('Location: dashboard.php'); // Redireciona para um local seguro
    exit;
}

$page_title = 'Usuários';
$error = null;
$success = null;
$is_superadmin = ($_SESSION['user_role'] === 'superadmin');
$is_admin = in_array($_SESSION['user_role'], ['admin', 'administrador']);
$empresa_id_sessao = $_SESSION['empresa_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // --- LÓGICA DE EDIÇÃO ---
        if (isset($_POST['edit_user'])) {
            $user_id_to_edit = $_POST['user_id'];
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $password = $_POST['password'];
            $empresa_id = $is_superadmin ? $_POST['empresa_id'] : $empresa_id_sessao;

            if (!$is_superadmin) {
                // Admins não podem definir a role 'superadmin'
                if ($role === 'superadmin') {
                    throw new Exception("Você não tem permissão para definir a função de Superadmin.");
                }
                // Garante que o admin só edite usuários da sua própria empresa
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = :id AND empresa_id = :empresa_id");
                $stmt_check->execute([':id' => $user_id_to_edit, ':empresa_id' => $empresa_id_sessao]);
                if ($stmt_check->fetchColumn() == 0) {
                    throw new Exception("Acesso negado: você só pode editar usuários da sua própria empresa.");
                }
            }
            
            $sql = "UPDATE usuarios SET nome = :nome, email = :email, empresa_id = :empresa_id, role = :role";
            $params = [':nome' => $nome, ':email' => $email, ':empresa_id' => $empresa_id, ':role' => $role, ':id' => $user_id_to_edit];
            if (!empty($password)) {
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = :password";
            }
            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success = "Usuário atualizado com sucesso!";
        }

        // --- LÓGICA DE ADIÇÃO ---
        elseif (isset($_POST['add_user'])) {
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $empresa_id = $is_superadmin ? $_POST['empresa_id'] : $empresa_id_sessao;

            if (empty($nome) || empty($email) || empty($password) || empty($empresa_id) || empty($role)) {
                throw new Exception("Todos os campos são obrigatórios.");
            }
            if (!$is_superadmin && $role === 'superadmin') {
                throw new Exception("Você não tem permissão para criar usuários com a função Superadmin.");
            }

            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, password, empresa_id, role) VALUES (:nome, :email, :password, :empresa_id, :role)");
            $stmt->execute([':nome' => $nome, ':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT), ':empresa_id' => $empresa_id, ':role' => $role]);
            $success = "Usuário adicionado com sucesso!";
        }

        // Lógica de exclusão
        elseif (isset($_POST['delete_user'])) {
             $user_id_to_delete = $_POST['user_id'];
             
             // Adiciona uma verificação para impedir que o usuário se auto-exclua
             if ($user_id_to_delete == $_SESSION['user_id']) {
                throw new Exception("Você não pode excluir sua própria conta.");
             }

             $sql = "DELETE FROM usuarios WHERE id = :id";
             if (!$is_superadmin) {
                 $sql .= " AND empresa_id = :empresa_id";
                 $params = [':id' => $user_id_to_delete, ':empresa_id' => $empresa_id_sessao];
             } else {
                 $params = [':id' => $user_id_to_delete];
             }
             $stmt = $pdo->prepare($sql);
             $stmt->execute($params);
             
             if ($stmt->rowCount() > 0) {
                $success = "Usuário excluído com sucesso!";
             } else {
                throw new Exception("Nenhum usuário foi excluído. O usuário pode não existir ou você não tem permissão.");
             }
        }

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
        if ($e instanceof PDOException && $e->getCode() == '23000') {
            $error = "Este e-mail já está cadastrado.";
        }
    }
}

// Lógica de busca de dados
$usuarios = [];
$empresas = [];
try {
    if ($is_superadmin) {
        $stmt_users = $pdo->query("SELECT u.id, u.nome, u.email, u.role, u.empresa_id, e.nome as nome_empresa FROM usuarios u JOIN empresas e ON u.empresa_id = e.id ORDER BY u.nome");
        $empresas = $pdo->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt_users = $pdo->prepare("SELECT u.id, u.nome, u.email, u.role, u.empresa_id, e.nome as nome_empresa FROM usuarios u JOIN empresas e ON u.empresa_id = e.id WHERE u.empresa_id = :empresa_id AND u.role != 'superadmin' ORDER BY u.nome");
        $stmt_users->execute([':empresa_id' => $empresa_id_sessao]);
    }
    $usuarios = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erro ao buscar dados: " . $e->getMessage();
}

require 'header.php';
?>

<div class="container bg-light p-4 rounded">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gerenciamento de Usuários</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">Adicionar Novo Usuário</button>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="table-responsive"><table class="table table-striped table-bordered table-hover">
        <thead class="thead-dark"><tr><th>Nome</th><th>Email</th><?php if ($is_superadmin) echo '<th>Empresa</th>'; ?><th>Função</th><th>Ações</th></tr></thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                    <?php if ($is_superadmin) echo '<td>'.htmlspecialchars($usuario['nome_empresa']).'</td>'; ?>
                    <td><?php echo htmlspecialchars(ucfirst($usuario['role'])); ?></td>
                    <td>
                        <button class="btn btn-sm btn-info edit-btn" data-bs-toggle="modal" data-bs-target="#editUserModal" data-userid="<?php echo $usuario['id']; ?>" data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>" data-email="<?php echo htmlspecialchars($usuario['email']); ?>" data-empresa_id="<?php echo $usuario['empresa_id']; ?>" data-role="<?php echo $usuario['role']; ?>">Editar</button>
                        <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-userid="<?php echo $usuario['id']; ?>">Excluir</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<!-- Modal Adicionar Usuário -->
<div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form action="usuarios.php" method="post">
    <div class="modal-header"><h5 class="modal-title">Adicionar Usuário</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <input type="hidden" name="add_user" value="1">
        <div class="form-group mb-3"><label>Nome</label><input type="text" class="form-control" name="nome" required></div>
        <div class="form-group mb-3"><label>Email</label><input type="email" class="form-control" name="email" required></div>
        <div class="form-group mb-3"><label>Senha</label><input type="password" class="form-control" name="password" required autocomplete="new-password"></div>
        <?php if ($is_superadmin): ?>
            <div class="form-group mb-3"><label>Empresa</label><select class="form-control" name="empresa_id" required><option value="">Selecione...</option><?php foreach ($empresas as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nome']); ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="form-group mb-3"><label for="add_role">Função</label><select class="form-control" id="add_role" name="role" required>
            <?php if ($is_superadmin): ?>
                <option value="superadmin">Superadmin</option>
            <?php endif; ?>
            <?php if ($is_superadmin || $is_admin): ?>
                <option value="analista">Analista</option>
            <?php endif; ?>
            <option value="administrador">Administrador</option>
            <option value="usuario" selected>Usuário</option>
        </select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Salvar</button></div>
</form></div></div></div>

<!-- Modal Editar Usuário -->
<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form action="usuarios.php" method="post">
    <div class="modal-header"><h5 class="modal-title">Editar Usuário</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
        <input type="hidden" name="edit_user" value="1">
        <input type="hidden" name="user_id" id="edit_user_id">
        <div class="form-group mb-3"><label>Nome</label><input type="text" class="form-control" id="edit_nome" name="nome" required></div>
        <div class="form-group mb-3"><label>Email</label><input type="email" class="form-control" id="edit_email" name="email" required></div>
        <div class="form-group mb-3"><label>Nova Senha</label><input type="password" class="form-control" name="password" placeholder="Deixe em branco para não alterar" autocomplete="new-password"></div>
        <?php if ($is_superadmin): ?>
            <div class="form-group mb-3"><label>Empresa</label><select class="form-control" id="edit_empresa_id" name="empresa_id" required><?php foreach ($empresas as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nome']); ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="form-group mb-3"><label for="edit_role">Função</label><select class="form-control" id="edit_role" name="role" required>
            <?php if ($is_superadmin): ?>
                <option value="superadmin">Superadmin</option>
            <?php endif; ?>
            <?php if ($is_superadmin || $is_admin): ?>
                <option value="analista">Analista</option>
            <?php endif; ?>
            <option value="administrador">Administrador</option>
            <option value="usuario">Usuário</option>
        </select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Salvar</button></div>
</form></div></div></div>

<!-- Modal de Exclusão -->
<div class="modal fade" id="deleteUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form action="usuarios.php" method="post"><div class="modal-header"><h5 class="modal-title">Confirmar Exclusão</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.</p><input type="hidden" name="user_id" id="delete_user_id"></div><div class="modal-footer"><input type="hidden" name="delete_user" value="1"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Sim, Excluir</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteUserModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-userid');
            const modalInput = deleteModal.querySelector('#delete_user_id');
            modalInput.value = userId;
        });
    }

    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const modal = this;
            
            // Pega os dados dos atributos data-* do botão
            const userId = button.getAttribute('data-userid');
            const nome = button.getAttribute('data-nome');
            const email = button.getAttribute('data-email');
            const empresaId = button.getAttribute('data-empresa_id');
            const role = button.getAttribute('data-role');

            // Preenche os campos do modal
            modal.querySelector('#edit_user_id').value = userId;
            modal.querySelector('#edit_nome').value = nome;
            modal.querySelector('#edit_email').value = email;
            modal.querySelector('#edit_role').value = role;

            const empresaSelect = modal.querySelector('#edit_empresa_id');
            if (empresaSelect) {
                empresaSelect.value = empresaId;
            }
        });
    }
});
</script>

<?php require 'footer.php'; ?>
