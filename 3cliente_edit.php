<?php
$page_title = 'Editar Cliente';
require_once 'bootstrap.php';

// Acesso permitido para Superadmin e Admin
if (!$is_superadmin && !$is_admin) {
    require 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require 'footer.php';
    exit();
}

$cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$cliente_id) {
    header('Location: clientes.php');
    exit;
}

$error = '';
$success = '';
$cliente = null;

// Carrega dados do cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM kyc_clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    if (!$cliente) {
        throw new Exception("Cliente não encontrado.");
    }

    // --- VERIFICAÇÃO DE SEGURANÇA PARA ADMIN ---
    if ($is_admin && $cliente['id_empresa_master'] != $user_empresa_id) {
        throw new Exception("Você não tem permissão para editar este cliente.");
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Processa o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim($_POST['nome_completo']);
        $email = trim($_POST['email']);
        $cpf = trim($_POST['cpf']);
        $status = $_POST['status'];
        $nova_senha = $_POST['nova_senha'];

        $params = [$nome, $email, $cpf, $status];
        $sql = "UPDATE kyc_clientes SET nome_completo = ?, email = ?, cpf = ?, status = ?";

        if (!empty($nova_senha)) {
            $sql .= ", password = ?";
            $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $cliente_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['flash_message'] = "Dados do cliente atualizados com sucesso!";
        header('Location: clientes.php');
        exit;

    } catch (Exception $e) {
        $error = "Erro ao atualizar: " . $e->getMessage();
    }
}

require 'header.php';
?>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-4">Editar Cliente: <?= htmlspecialchars($cliente['nome_completo'] ?? '') ?></h2>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if ($cliente): ?>
    <form method="POST">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="nome_completo" class="form-label">Nome Completo</label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($cliente['nome_completo']) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="cpf" class="form-label">CPF</label>
                <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($cliente['cpf']) ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label for="status" class="form-label">Status da Conta</label>
                <select name="status" id="status" class="form-select">
                    <option value="ativo" <?= ($cliente['status'] == 'ativo') ? 'selected' : '' ?>>Ativo</option>
                    <option value="pendente" <?= ($cliente['status'] == 'pendente') ? 'selected' : '' ?>>Pendente</option>
                    <option value="inativo" <?= ($cliente['status'] == 'inativo') ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label for="nova_senha" class="form-label">Nova Senha</label>
            <input type="password" class="form-control" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para não alterar">
        </div>
        <hr>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
    </form>
    <?php else: ?>
        <p>O cliente solicitado não pôde ser encontrado.</p>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
