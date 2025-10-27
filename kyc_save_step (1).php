<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

header('Content-Type: application/json');

function send_json_response($data) {
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['status' => 'error', 'message' => 'Método não permitido.']);
}

$pdo->beginTransaction();

try {
    $submission_id = $_POST['submission_id'] ?? null;
    $step = (int)($_POST['step'] ?? 0);

    if ($step === 1) {
        $empresa = $_POST['empresa'] ?? [];
        $cnpj = $empresa['cnpj'] ?? null;
        if (!$cnpj) throw new Exception('O CNPJ é obrigatório para iniciar.');

        $data_constituicao = null;
        if (!empty($empresa['data_constituicao'])) {
            $date_parts = explode('/', $empresa['data_constituicao']);
            if(count($date_parts) == 3) $data_constituicao = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}";
        }

        if ($submission_id) {
            // Atualiza um rascunho existente
            $sql = "UPDATE kyc_empresas SET razao_social = ?, nome_fantasia = ?, data_constituicao = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?, email_contato = ?, ddd_telefone_1 = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa['razao_social'] ?? null, $empresa['nome_fantasia'] ?? null, $data_constituicao,
                $empresa['cep'] ?? null, $empresa['logradouro'] ?? null, $empresa['numero'] ?? null, $empresa['complemento'] ?? null,
                $empresa['bairro'] ?? null, $empresa['cidade'] ?? null, $empresa['uf'] ?? null,
                $empresa['email_contato'] ?? null, $empresa['telefone_contato'] ?? null,
                $submission_id
            ]);
        } else {
            // Cria um novo rascunho
            $id_empresa_master = $_POST['id_empresa_master'] ?: ($_SESSION['empresa_id'] ?? null);
            $cliente_id = $_SESSION['cliente_id'] ?? null;

            $sql = "INSERT INTO kyc_empresas (id_empresa_master, cliente_id, cnpj, status) VALUES (?, ?, ?, 'Em Preenchimento')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_empresa_master, $cliente_id, $cnpj]);
            $submission_id = $pdo->lastInsertId();
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
        // Apaga sócios antigos para simplificar e reinsere os atuais
        $pdo->prepare("DELETE FROM kyc_socios WHERE empresa_id = ?")->execute([$submission_id]);
        
        if (isset($_POST['socios']) && is_array($_POST['socios'])) {
            $sql_socio = "INSERT INTO kyc_socios (empresa_id, nome_completo, data_nascimento, cpf_cnpj, qualificacao_cargo, is_pep) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_socio = $pdo->prepare($sql_socio);
            foreach ($_POST['socios'] as $socio) {
                $data_nascimento_socio = null;
                if (!empty($socio['data_nascimento'])) {
                    $date_parts = explode('/', $socio['data_nascimento']);
                    if(count($date_parts) == 3) $data_nascimento_socio = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}";
                }
                $stmt_socio->execute([
                    $submission_id, $socio['nome_completo'] ?? null, $data_nascimento_socio, $socio['cpf_cnpj'] ?? null,
                    $socio['qualificacao_cargo'] ?? null, isset($socio['pep'])
                ]);
            }
        }
    }

    $pdo->commit();
    send_json_response(['status' => 'success', 'submission_id' => $submission_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Erro em kyc_save_step.php: " . $e->getMessage());
    send_json_response(['status' => 'error', 'message' => $e->getMessage()]);
}
