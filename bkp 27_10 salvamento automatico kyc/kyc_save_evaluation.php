<?php
require 'config.php';

// Normaliza a role para minúsculas para evitar problemas de case-sensitivity.
$user_role = isset($_SESSION['user_role']) ? strtolower($_SESSION['user_role']) : '';

// Apenas analistas e superadmins podem executar esta ação.
if (!isset($_SESSION['user_id']) || !in_array($user_role, ['analista', 'superadmin'])) {
    $_SESSION['flash_error'] = 'Acesso negado. Você não tem permissão para executar esta ação.';
    header('Location: kyc_list.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kyc_list.php');
    exit();
}

$case_id = $_POST['case_id'] ?? null;
$analysis = $_POST['analysis'] ?? [];
$action = $_POST['action'] ?? 'save_draft';

if (!$case_id) {
    $_SESSION['flash_error'] = 'ID do caso não fornecido.';
    header('Location: kyc_list.php');
    exit();
}

// Mapeia os dados do formulário para as colunas do banco
$params = [
    'id' => $case_id,
    'check_listas_restritivas' => isset($analysis['check_listas']) ? 1 : 0,
    'check_midia_negativa' => isset($analysis['check_midia']) ? 1 : 0,
    'check_processos_judiciais' => isset($analysis['check_processos']) ? 1 : 0,
    'analise_anotacoes_internas' => $analysis['anotacoes'] ?? null,
    'analise_risco_final' => $analysis['risco_final'] ?? null,
    'analise_justificativa_risco' => $analysis['justificativa_risco'] ?? null,
    'analise_decisao_final' => $analysis['decisao'] ?? null,
    'analise_info_pendencia' => $analysis['justificativa_pendencia'] ?? null,
    'analista_id' => $_SESSION['user_id'], // Usa o ID do analista logado
    'data_analise' => date('Y-m-d H:i:s'),
];

$new_status = null;
if ($action === 'submit_decision') {
    $new_status = $analysis['decisao'] ?? null;
    if ($new_status) {
        $params['status'] = $new_status;
    }
} else {
    // Se for um rascunho, muda o status para 'Em Análise' se ainda estiver como 'Enviado'
    $stmt_status = $pdo->prepare("SELECT status FROM kyc_empresas WHERE id = ?");
    $stmt_status->execute([$case_id]);
    $current_status = $stmt_status->fetchColumn();
    if ($current_status === 'Enviado') {
        $params['status'] = 'Em Análise';
    }
}

// Constrói a query de UPDATE dinamicamente
$sql_parts = [];
foreach ($params as $key => $value) {
    if ($key !== 'id') {
        $sql_parts[] = "`{$key}` = :{$key}";
    }
}
$sql = "UPDATE kyc_empresas SET " . implode(', ', $sql_parts) . " WHERE id = :id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['flash_message'] = "Análise do caso #{$case_id} foi salva com sucesso.";

    if ($action === 'submit_decision') {
        header('Location: kyc_list.php');
    } else {
        header('Location: kyc_evaluate.php?id=' . $case_id);
    }
    exit();

} catch (PDOException $e) {
    // Em produção, logue o erro em vez de exibi-lo.
    // error_log($e->getMessage());
    $_SESSION['flash_error'] = "Erro ao salvar a análise: " . $e->getMessage();
    header('Location: kyc_evaluate.php?id=' . $case_id);
    exit();
}
