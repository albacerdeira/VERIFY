<?php
require_once 'header.php'; // Para acesso ao $pdo e verificação de sessão/permissão

// --- Validação e Segurança ---
if (!$is_admin) {
    // Se não for admin, encerra e mostra um erro.
    // Pode ser melhorado com uma página de erro mais amigável.
    die("Acesso negado. Apenas administradores podem realizar esta ação.");
}

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
$submission_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$submission_id || !$action || !in_array($action, ['approve', 'reject'])) {
    // Se os parâmetros forem inválidos, redireciona de volta para a lista
    header('Location: kyc_review.php');
    exit;
}

// --- Lógica de Atualização no Banco de Dados ---

$new_status = '';
if ($action === 'approve') {
    $new_status = 'aprovado';
} else if ($action === 'reject') {
    $new_status = 'rejeitado';
}

// O ID do revisor é o ID do usuário logado na sessão
$reviewer_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Atualiza o status, a data da revisão e quem revisou.
    $stmt = $pdo->prepare(
        "UPDATE kyc_submissions 
         SET status = ?, reviewed_at = CURRENT_TIMESTAMP, reviewer_id = ? 
         WHERE id = ? AND status = 'pendente'" // Garante que só atualize se ainda estiver pendente
    );
    
    $stmt->execute([$new_status, $reviewer_id, $submission_id]);

    // Verifica se alguma linha foi realmente atualizada
    if ($stmt->rowCount() > 0) {
        // Se a atualização foi bem-sucedida, confirma a transação
        $pdo->commit();
        $_SESSION['flash_message'] = "A submissão #{$submission_id} foi marcada como {$new_status} com sucesso.";
    } else {
        // Se nenhuma linha foi afetada (talvez já tenha sido processada), desfaz e avisa
        $pdo->rollBack();
        $_SESSION['flash_message'] = "A submissão #{$submission_id} não pôde ser atualizada (talvez já tenha sido processada por outro administrador).";
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Erro ao processar ação de KYC: " . $e->getMessage());
    $_SESSION['flash_message'] = "Ocorreu um erro de banco de dados ao tentar processar a submissão. Por favor, tente novamente.";
}

// --- Redirecionamento ---
// Após a ação, redireciona o usuário de volta para a lista de pendências.
header('Location: kyc_review.php');
exit;

?>