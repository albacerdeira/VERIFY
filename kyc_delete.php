<?php
require_once 'bootstrap.php';

// --- VERIFICAÇÃO DE SEGURANÇA: APENAS SUPERADMIN PODE EXCLUIR ---
if ($user_role !== 'superadmin') {
    $_SESSION['flash_error'] = 'Acesso negado. Você não tem permissão para excluir registros.';
    header('Location: kyc_list.php');
    exit;
}

$submission_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$submission_id) {
    $_SESSION['flash_error'] = 'ID de submissão inválido.';
    header('Location: kyc_list.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. (Opcional, mas recomendado) Apagar arquivos físicos do servidor
    $stmt_docs = $pdo->prepare("SELECT path_arquivo FROM kyc_documentos WHERE empresa_id = ?");
    $stmt_docs->execute([$submission_id]);
    $files_to_delete = $stmt_docs->fetchAll(PDO::FETCH_COLUMN);
    foreach ($files_to_delete as $file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    // Poderíamos adicionar a lógica para remover diretórios vazios aqui.

    // 2. Deletar registros das tabelas relacionadas (em ordem de dependência)
    $pdo->prepare("DELETE FROM kyc_log_atividades WHERE kyc_empresa_id = ?")->execute([$submission_id]);
    $pdo->prepare("DELETE FROM kyc_documentos WHERE empresa_id = ?")->execute([$submission_id]);
    $pdo->prepare("DELETE FROM kyc_socios WHERE empresa_id = ?")->execute([$submission_id]);
    $pdo->prepare("DELETE FROM kyc_cnaes_secundarios WHERE empresa_id = ?")->execute([$submission_id]);
    $pdo->prepare("DELETE FROM kyc_avaliacoes WHERE kyc_empresa_id = ?")->execute([$submission_id]);

    // 3. Finalmente, deletar o registro principal da empresa
    $stmt_empresa = $pdo->prepare("DELETE FROM kyc_empresas WHERE id = ?");
    $stmt_empresa->execute([$submission_id]);

    if ($stmt_empresa->rowCount() > 0) {
        $_SESSION['flash_message'] = "Submissão #{$submission_id} e todos os seus dados foram excluídos com sucesso.";
    } else {
        throw new Exception("Nenhuma submissão encontrada com o ID #{$submission_id}.");
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "Erro ao excluir a submissão: " . $e->getMessage();
    error_log("Erro ao excluir KYC ID {$submission_id}: " . $e->getMessage());
}

header('Location: kyc_list.php');
exit;
