<?php
// --- INÍCIO DO CAÇADOR DE ERROS FATAIS ---
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo']->inTransaction()) {
            $GLOBALS['pdo']->rollBack();
        }
        echo json_encode([
            'status' => 'fatal_error',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});
// --- FIM DO CAÇADOR DE ERROS FATAIS ---

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

header('Content-Type: application/json');

function send_json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Método não permitido.'], 405);
}

$pdo->beginTransaction();

try {
    $submission_id = $_POST['submission_id'] ?? null;
    $step = (int)($_POST['step'] ?? 0);

    if ($step === 1) {
        $empresa = $_POST['empresa'] ?? [];
        $cnpj = preg_replace('/[^0-9]/', '', $empresa['cnpj'] ?? '');
        if (strlen($cnpj) !== 14) throw new Exception('O CNPJ é obrigatório para iniciar.');

        // Se não houver um ID de submissão, cria um novo rascunho para obter um ID.
        if (!$submission_id) {
            $id_empresa_master = $_POST['id_empresa_master'] ?: ($_SESSION['empresa_id'] ?? null);
            $cliente_id = $_SESSION['cliente_id'] ?? null;

            // Verifica se já existe um rascunho para este CNPJ e parceiro, para evitar duplicatas
            $stmt_check = $pdo->prepare("SELECT id FROM kyc_empresas WHERE cnpj = ? AND id_empresa_master = ? AND status = 'Em Preenchimento'");
            $stmt_check->execute([$cnpj, $id_empresa_master]);
            $existing = $stmt_check->fetch();

            if ($existing) {
                $submission_id = $existing['id'];
            } else {
                $sql = "INSERT INTO kyc_empresas (id_empresa_master, cliente_id, cnpj, status) VALUES (?, ?, ?, 'Em Preenchimento')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_empresa_master, $cliente_id, $cnpj]);
                $submission_id = $pdo->lastInsertId();
            }
        }

        // Agora, com um submission_id garantido, atualiza todos os campos da Etapa 1.
        $data_constituicao = null;
        if (!empty($empresa['data_constituicao'])) {
            $date_parts = explode('/', $empresa['data_constituicao']);
            if(count($date_parts) == 3) $data_constituicao = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}";
        }

        // Mapeamento seguro de campos do formulário para colunas do banco
        $fields_to_update = [
            'razao_social' => $empresa['razao_social'] ?? null,
            'nome_fantasia' => $empresa['nome_fantasia'] ?? null,
            'data_constituicao' => $data_constituicao,
            'cep' => $empresa['cep'] ?? null,
            'logradouro' => $empresa['logradouro'] ?? null,
            'numero' => $empresa['numero'] ?? null,
            'complemento' => $empresa['complemento'] ?? null,
            'bairro' => $empresa['bairro'] ?? null,
            'cidade' => $empresa['cidade'] ?? null,
            'uf' => $empresa['uf'] ?? null,
            'cnae_fiscal' => $empresa['cnae_principal'] ?? null,
            'cnae_fiscal_descricao' => $empresa['descricao_cnae_principal'] ?? null,
            'identificador_matriz_filial' => $empresa['identificador_matriz_filial'] ?? null,
            'situacao_cadastral' => $empresa['situacao_cadastral'] ?? null,
            'descricao_motivo_situacao_cadastral' => $empresa['descricao_motivo_situacao_cadastral'] ?? null,
            'porte' => $empresa['porte'] ?? null,
            'natureza_juridica' => $empresa['natureza_juridica'] ?? null,
            'opcao_pelo_simples' => $empresa['opcao_pelo_simples'] ?? null,
            'representante_legal' => $empresa['representante_legal'] ?? null,
            'email_contato' => $empresa['email_contato'] ?? null,
            'ddd_telefone_1' => $empresa['telefone_contato'] ?? null,
            'observacoes_empresa' => $empresa['observacoes'] ?? null,
        ];

        $sql_parts = [];
        $params = [];
        foreach ($fields_to_update as $column => $value) {
            $sql_parts[] = "`$column` = ?";
            $params[] = $value;
        }
        $params[] = $submission_id;

        $sql_update = "UPDATE `kyc_empresas` SET " . implode(', ', $sql_parts) . " WHERE `id` = ?";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute($params);
        
        // Salva os CNAEs secundários
        $pdo->prepare("DELETE FROM kyc_cnaes_secundarios WHERE empresa_id = ?")->execute([$submission_id]);
        if (isset($_POST['cnaes']) && is_array($_POST['cnaes'])) {
            $sql_cnae = "INSERT INTO kyc_cnaes_secundarios (empresa_id, cnae, descricao) VALUES (?, ?, ?)";
            $stmt_cnae = $pdo->prepare($sql_cnae);
            foreach ($_POST['cnaes'] as $cnae) {
                if (!empty($cnae['codigo']) && !empty($cnae['descricao'])) {
                    $stmt_cnae->execute([$submission_id, $cnae['codigo'], $cnae['descricao']]);
                }
            }
        }

    } elseif ($step === 2 && $submission_id) {
        $perfil = $_POST['perfil'] ?? [];
        $origem_fundos = isset($perfil['fonte_fundos']) ? implode(', ', $perfil['fonte_fundos']) : null;
        
        $sql = "UPDATE kyc_empresas SET atividade_principal = ?, motivo_abertura_conta = ?, fluxo_financeiro_pretendido = ?, moedas_operar = ?, blockchains_operar = ?, volume_mensal_pretendido = ?, origem_fundos = ?, descricao_fundos_terceiros = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $perfil['atividade_principal'] ?? null, $perfil['motivo_abertura_conta'] ?? null, $perfil['fluxo_financeiro_pretendido'] ?? null,
            $perfil['moedas_operar'] ?? null, $perfil['blockchains_operar'] ?? null, $perfil['volume_mensal_pretendido'] ?? null,
            $origem_fundos, $perfil['descricao_fundos_terceiros'] ?? null,
            $submission_id
        ]);
    } elseif ($step === 3 && $submission_id) {
        $pdo->prepare("DELETE FROM kyc_socios WHERE empresa_id = ?")->execute([$submission_id]);
        
        if (isset($_POST['socios']) && is_array($_POST['socios'])) {
            $sql_socio = "INSERT INTO kyc_socios (empresa_id, nome_completo, data_nascimento, cpf_cnpj, qualificacao_cargo, percentual_participacao, cep, logradouro, numero, complemento, bairro, cidade, uf, observacoes, is_pep, dados_validados) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_socio = $pdo->prepare($sql_socio);
            foreach ($_POST['socios'] as $socio) {
                $data_nascimento_socio = null;
                if (!empty($socio['data_nascimento'])) {
                    $date_parts = explode('/', $socio['data_nascimento']);
                    if(count($date_parts) == 3) $data_nascimento_socio = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}";
                }
                $stmt_socio->execute([
                    $submission_id, $socio['nome_completo'] ?? null, $data_nascimento_socio, $socio['cpf_cnpj'] ?? null, $socio['qualificacao_cargo'] ?? null,
                    empty($socio['percentual_participacao']) ? null : $socio['percentual_participacao'],
                    $socio['cep'] ?? null, $socio['logradouro'] ?? null, $socio['numero'] ?? null, $socio['complemento'] ?? null, $socio['bairro'] ?? null, $socio['cidade'] ?? null, $socio['uf'] ?? null,
                    $socio['observacoes'] ?? null, isset($socio['pep']), isset($socio['dados_validados'])
                ]);
            }
        }
    }

    $pdo->commit();
    send_json_response(['status' => 'success', 'submission_id' => $submission_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro em kyc_save_step.php: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => 'Erro ao salvar progresso: ' . $e->getMessage()], 500);
}
