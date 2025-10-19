<?php
// Define o cabeçalho de resposta como JSON
header('Content-Type: application/json');

// --- CHAVE DE SEGURANÇA DA API ---
// IMPORTANTE: Altere esta chave para uma string longa, complexa e secreta.
// Esta chave deve ser compartilhada com o sistema parceiro e enviada no cabeçalho 'X-API-Key'.
define('EXPECTED_API_KEY', 'SUA_CHAVE_SECRETA_SUPER_FORTE_AQUI');

require_once 'config.php';

// Função para enviar uma resposta de erro JSON e encerrar o script
function send_json_error($statusCode, $message) {
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// 1. VERIFICAR O MÉTODO DA REQUISIÇÃO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error(405, 'Método não permitido. Use POST.');
}

// 2. VERIFICAR A CHAVE DA API
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== EXPECTED_API_KEY) {
    send_json_error(401, 'Acesso não autorizado. Chave de API inválida ou ausente.');
}

// 3. OBTER E DECODIFICAR O JSON DO CORPO DA REQUISIÇÃO
$json_payload = file_get_contents('php://input');
$data = json_decode($json_payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_json_error(400, 'JSON inválido ou mal formatado.');
}

// Validações básicas da estrutura do JSON
if (empty($data['company_data']) || empty($data['ubos']) || empty($data['documents'])) {
    send_json_error(400, 'Estrutura do JSON incompleta. Faltam seções obrigatórias: company_data, ubos, documents.');
}

// 4. PROCESSAR E SALVAR OS DADOS NO BANCO (USANDO UMA TRANSAÇÃO)
try {
    $pdo->beginTransaction();

    // 4.1. Inserir na tabela principal `kyc_submissions`
    $stmt_sub = $pdo->prepare("INSERT INTO kyc_submissions (status) VALUES ('pendente')");
    $stmt_sub->execute();
    $submission_id = $pdo->lastInsertId();

    // 4.2. Inserir os dados da empresa em `kyc_company_data`
    $cd = $data['company_data'];
    $sql_company = "INSERT INTO kyc_company_data (submission_id, razao_social, cnpj, nome_fantasia, data_constituicao, site_empresa, email_contato, telefone_contato, endereco_completo, cnae_principal, produtos_servicos, orgao_regulador, paises_atuacao, motivo_abertura_conta, fluxo_financeiro, origem_fundos, destino_fundos, volume_mensal_pretendido, ticket_medio, moedas_operar, blockchains_operar, responsavel_preenchimento, cargo_responsavel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_company = $pdo->prepare($sql_company);
    $stmt_company->execute([
        $submission_id, $cd['razao_social'], $cd['cnpj'], $cd['nome_fantasia'], $cd['data_constituicao'], $cd['site_empresa'], $cd['email_contato'], $cd['telefone_contato'], $cd['endereco_completo'], $cd['cnae_principal'], $cd['produtos_servicos'], $cd['orgao_regulador'], $cd['paises_atuacao'], $cd['motivo_abertura_conta'], $cd['fluxo_financeiro'], $cd['origem_fundos'], $cd['destino_fundos'], $cd['volume_mensal_pretendido'], $cd['ticket_medio'], $cd['moedas_operar'], $cd['blockchains_operar'], $cd['responsavel_preenchimento'], $cd['cargo_responsavel']
    ]);

    // 4.3. Inserir cada UBO (sócio/administrador) e seus documentos
    foreach ($data['ubos'] as $ubo_data) {
        $sql_ubo = "INSERT INTO kyc_ubos (submission_id, nome_completo, funcao_cargo, cpf, data_nascimento, endereco_residencial, percentual_participacao, is_pep) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ubo = $pdo->prepare($sql_ubo);
        $stmt_ubo->execute([
            $submission_id, $ubo_data['nome_completo'], $ubo_data['funcao_cargo'], $ubo_data['cpf'], $ubo_data['data_nascimento'], $ubo_data['endereco_residencial'], $ubo_data['percentual_participacao'], $ubo_data['is_pep']
        ]);
        $ubo_id = $pdo->lastInsertId();

        // Anexar documentos específicos do UBO
        if (!empty($ubo_data['documentos'])) {
            foreach ($ubo_data['documentos'] as $doc) {
                $stmt_doc = $pdo->prepare("INSERT INTO kyc_documents (submission_id, ubo_id, document_type, file_url) VALUES (?, ?, ?, ?)");
                $stmt_doc->execute([$submission_id, $ubo_id, $doc['tipo'], $doc['url']]);
            }
        }
    }

    // 4.4. Inserir documentos gerais da empresa
    foreach ($data['documents'] as $doc) {
        $stmt_doc = $pdo->prepare("INSERT INTO kyc_documents (submission_id, document_type, file_url) VALUES (?, ?, ?)");
        $stmt_doc->execute([$submission_id, $doc['tipo'], $doc['url']]);
    }

    // Se tudo correu bem, confirma a transação
    $pdo->commit();

    // 5. ENVIAR RESPOSTA DE SUCESSO
    http_response_code(201); // 201 Created
    echo json_encode(['status' => 'success', 'message' => 'Submissão de KYC recebida com sucesso.', 'submission_id' => $submission_id]);

} catch (Exception $e) {
    // Se algo deu errado, desfaz a transação
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Loga o erro para análise interna, sem expor ao cliente
    error_log('Erro na API de KYC: ' . $e->getMessage());
    send_json_error(500, 'Ocorreu um erro interno no servidor ao processar a submissão.');
}

?>