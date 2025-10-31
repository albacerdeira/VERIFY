<?php
$page_title = 'Editar Cliente';
require_once 'bootstrap.php';

// Acesso permitido para Superadmin, Admin e Analista
if (!$is_superadmin && !$is_admin && !$is_analista) {
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

    // Verificação de segurança para Admin e Analista
    if (($is_admin || $is_analista) && $cliente['id_empresa_master'] != $user_empresa_id) {
        throw new Exception("Você não tem permissão para editar este cliente.");
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Processa o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim($_POST['nome_completo']);
        $cpf = trim($_POST['cpf']);
        $email = trim($_POST['email']);
        $status = $_POST['status'];
        $nova_senha = $_POST['nova_senha'];

        $params = [$nome, $cpf, $email, $status];
        $sql = "UPDATE kyc_clientes SET nome_completo = ?, cpf = ?, email = ?, status = ?";

        if (!empty($nova_senha)) {
            $sql .= ", password = ?";
            $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $cliente_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['flash_message'] = "Dados do cliente atualizados com sucesso!";
        header('Location: cliente_edit.php?id=' . $cliente_id); // Recarrega a página para ver as alterações
        exit;

    } catch (Exception $e) {
        $error = "Erro ao atualizar: " . $e->getMessage();
    }
}

require 'header.php';
?>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-4">Editar Ficha do Cliente</h2>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <?php if ($cliente): ?>
    <div class="row">
        <!-- Coluna do Formulário -->
        <div class="col-md-7">
            <form method="POST">
                <h4 class="mb-3 border-bottom pb-2">Dados Cadastrais</h4>
                
                <div class="mb-3">
                    <label for="nome_completo" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($cliente['nome_completo']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($cliente['cpf'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status da Conta</label>
                    <select name="status" id="status" class="form-select">
                        <option value="ativo" <?= ($cliente['status'] == 'ativo') ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($cliente['status'] == 'inativo') ? 'selected' : '' ?>>Inativo</option>
                        <option value="pendente" <?= ($cliente['status'] == 'pendente') ? 'selected' : '' ?>>Pendente</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="nova_senha" class="form-label">Redefinir Senha</label>
                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para não alterar">
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="clientes.php" class="btn btn-secondary">Voltar para a Lista</a>
            </form>
        </div>

        <!-- Coluna da Selfie e Informações -->
        <div class="col-md-5">
            <div class="p-3 border rounded bg-light">
                <h4 class="mb-3 border-bottom pb-2">Documentos e Infos</h4>
                
                <div class="mb-3">
                    <p class="mb-1"><strong>ID do Cliente:</strong> #<?= htmlspecialchars($cliente['id']) ?></p>
                    <p class="mb-1"><strong>Data de Cadastro:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cliente['created_at']))) ?></p>
                    <p><strong>Última Atualização:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cliente['updated_at']))) ?></p>
                </div>

                <div class="text-center">
                    <h5 class="mb-2">Selfie Enviada</h5>
                    <?php
                    if (!empty($cliente['selfie_path'])) {
                        $path = htmlspecialchars($cliente['selfie_path']);
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                            echo '<img src="' . $path . '" alt="Selfie do Cliente" class="img-fluid rounded border" style="max-height: 300px;">';
                        } elseif ($ext == 'pdf') {
                            echo '<div class="alert alert-info text-center">';
                            echo '<a href="' . $path . '" target="_blank" class="alert-link">Visualizar PDF da Selfie</a>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-muted">Arquivo de selfie disponível, mas formato não visualizável diretamente: <a href="' . $path . '" target="_blank">' . basename($path) . '</a></p>';
                        }
                    } else {
                        echo '<p class="text-muted">Nenhuma selfie foi enviada por este cliente.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <p>O cliente solicitado não pôde ser encontrado.</p>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
