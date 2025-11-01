<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

// --- FUNÇÕES AUXILIARES ---

function sanitize_for_path($name) {
    $name = preg_replace('/[^\pL\pN\s-]/u', '', mb_strtolower($name ?? ''));
    $name = preg_replace('/\s+/', '_', $name);
    return substr($name, 0, 100);
}

function handle_file_upload($pdo, $file_data, $empresa_id, $empresa_nome, $tipo_documento, $socio_id = null, $socio_nome = null) {
    if ($file_data && $file_data['error'] === UPLOAD_ERR_OK) {
        $sanitized_empresa_nome = sanitize_for_path($empresa_nome);
        $upload_dir = 'uploads/kyc/' . $empresa_id . '-' . $sanitized_empresa_nome . '/';

        if ($socio_id && $socio_nome) {
            $sanitized_socio_nome = sanitize_for_path($socio_nome);
            $upload_dir .= 'socios/' . $socio_id . '-' . $sanitized_socio_nome . '/';
        }

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = basename($file_data['name']);
        $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $safe_filename = $tipo_documento . '_' . uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $safe_filename;

        if (move_uploaded_file($file_data['tmp_name'], $target_path)) {
            $stmt = $pdo->prepare('INSERT INTO kyc_documentos (empresa_id, socio_id, tipo_documento, path_arquivo, nome_arquivo) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$empresa_id, $socio_id, $tipo_documento, $target_path, $original_name]);
        } else {
            throw new Exception("Falha ao mover o arquivo de upload para {$target_path}.");
        }
    } else if ($file_data && $file_data['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new Exception("Erro no upload do arquivo '{$tipo_documento}': código de erro " . $file_data['error']);
    }
}

// --- PROCESSAMENTO PRINCIPAL ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kyc_form.php');
    exit();
}

$submission_id = $_POST['submission_id'] ?? null;
if (!$submission_id) {
    $_SESSION['flash_error'] = "Erro: ID da submissão não encontrado. O progresso pode não ter sido salvo. Por favor, preencha o formulário desde o início.";
    header('Location: kyc_form.php' . (!empty($_POST['cliente_slug']) ? '?cliente=' . urlencode($_POST['cliente_slug']) : ''));
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. ATUALIZAR DADOS DA EMPRESA E PERFIL (FINAL)
    $empresa = $_POST['empresa'];
    $perfil = $_POST['perfil'];

    $data_constituicao = null;
    if (!empty($empresa['data_constituicao'])) {
        $date_parts = explode('/', $empresa['data_constituicao']);
        if(count($date_parts) == 3) $data_constituicao = "{$date_parts[2]}-{$date_parts[1]}-{$date_parts[0]}";
    }
    $origem_fundos = isset($perfil['fonte_fundos']) ? implode(', ', $perfil['fonte_fundos']) : null;

    $sql_update = "UPDATE kyc_empresas SET 
        razao_social = ?, nome_fantasia = ?, data_constituicao = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?,
        cnae_fiscal = ?, cnae_fiscal_descricao = ?, identificador_matriz_filial = ?, situacao_cadastral = ?, descricao_motivo_situacao_cadastral = ?,
        porte = ?, natureza_juridica = ?, opcao_pelo_simples = ?, representante_legal = ?, email_contato = ?, ddd_telefone_1 = ?, observacoes_empresa = ?,
        atividade_principal = ?, motivo_abertura_conta = ?, fluxo_financeiro_pretendido = ?, moedas_operar = ?, blockchains_operar = ?,
        volume_mensal_pretendido = ?, origem_fundos = ?, descricao_fundos_terceiros = ?, consentimento_termos = ?, 
        status = 'Novo Registro' 
        WHERE id = ?";
    
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        $empresa['razao_social'] ?? null, $empresa['nome_fantasia'] ?? null, $data_constituicao, $empresa['cep'] ?? null, $empresa['logradouro'] ?? null, $empresa['numero'] ?? null, $empresa['complemento'] ?? null, $empresa['bairro'] ?? null, $empresa['cidade'] ?? null, $empresa['uf'] ?? null,
        $empresa['cnae_principal'] ?? null, $empresa['descricao_cnae_principal'] ?? null, $empresa['identificador_matriz_filial'] ?? null, $empresa['situacao_cadastral'] ?? null, $empresa['descricao_motivo_situacao_cadastral'] ?? null,
        $empresa['porte'] ?? null, $empresa['natureza_juridica'] ?? null, $empresa['opcao_pelo_simples'] ?? null, $empresa['representante_legal'] ?? null, $empresa['email_contato'] ?? null, $empresa['telefone_contato'] ?? null, $empresa['observacoes'] ?? null,
        $perfil['atividade_principal'] ?? null, $perfil['motivo_abertura_conta'] ?? null, $perfil['fluxo_financeiro_pretendido'] ?? null, $perfil['moedas_operar'] ?? null, $perfil['blockchains_operar'] ?? null,
        $perfil['volume_mensal_pretendido'] ?? null, $origem_fundos, $perfil['descricao_fundos_terceiros'] ?? null, isset($_POST['termos']['consentimento']),
        $submission_id
    ]);

    // 2. ATUALIZAR CNAES SECUNDÁRIOS
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

    // 3. ATUALIZAR SÓCIOS E SEUS DOCUMENTOS
    $pdo->prepare("DELETE FROM kyc_socios WHERE empresa_id = ?")->execute([$submission_id]);
    if (isset($_POST['socios']) && is_array($_POST['socios'])) {
        $sql_socio = "INSERT INTO kyc_socios (empresa_id, nome_completo, data_nascimento, cpf_cnpj, qualificacao_cargo, percentual_participacao, cep, logradouro, numero, complemento, bairro, cidade, uf, observacoes, is_pep, dados_validados) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_socio = $pdo->prepare($sql_socio);
        foreach ($_POST['socios'] as $index => $socio) {
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
            $socio_id = $pdo->lastInsertId();

            if (isset($_FILES['socios']['name'][$index])) {
                $doc_id_data = ['name' => $_FILES['socios']['name'][$index]['doc_identificacao'], 'type' => $_FILES['socios']['type'][$index]['doc_identificacao'], 'tmp_name' => $_FILES['socios']['tmp_name'][$index]['doc_identificacao'], 'error' => $_FILES['socios']['error'][$index]['doc_identificacao'], 'size' => $_FILES['socios']['size'][$index]['doc_identificacao']];
                handle_file_upload($pdo, $doc_id_data, $submission_id, $empresa['razao_social'], 'doc_identificacao', $socio_id, $socio['nome_completo']);
                
                $doc_end_data = ['name' => $_FILES['socios']['name'][$index]['doc_endereco'], 'type' => $_FILES['socios']['type'][$index]['doc_endereco'], 'tmp_name' => $_FILES['socios']['tmp_name'][$index]['doc_endereco'], 'error' => $_FILES['socios']['error'][$index]['doc_endereco'], 'size' => $_FILES['socios']['size'][$index]['doc_endereco']];
                handle_file_upload($pdo, $doc_end_data, $submission_id, $empresa['razao_social'], 'doc_endereco', $socio_id, $socio['nome_completo']);
            }
        }
    }
    
    // 4. PROCESSAR UPLOAD DE DOCUMENTOS DA EMPRESA
    if (isset($_FILES['documentos'])) {
        $documentos_empresa = $_FILES['documentos'];
        $tipos_documento = ['contrato_social' => 'doc_contrato_social', 'ultima_alteracao' => 'doc_ultima_alteracao', 'cartao_cnpj' => 'doc_cartao_cnpj', 'balanco_anual' => 'doc_balanco', 'balancete_trimestral' => 'doc_balancete', 'dirpj' => 'doc_dirpj'];
        foreach ($tipos_documento as $form_name => $db_type) {
            if (isset($documentos_empresa['name'][$form_name]) && $documentos_empresa['error'][$form_name] == UPLOAD_ERR_OK) {
                $file_data = ['name' => $documentos_empresa['name'][$form_name], 'type' => $documentos_empresa['type'][$form_name], 'tmp_name' => $documentos_empresa['tmp_name'][$form_name], 'error' => $documentos_empresa['error'][$form_name], 'size' => $documentos_empresa['size'][$form_name]];
                handle_file_upload($pdo, $file_data, $submission_id, $empresa['razao_social'], $db_type);
            }
        }
    }

    // 5. LOG E REDIRECIONAMENTO
    $log_usuario_id = $_SESSION['user_id'] ?? ($_SESSION['cliente_id'] ?? null);
    $log_usuario_nome = $_SESSION['nome'] ?? ($_SESSION['cliente_nome'] ?? 'Cliente Externo');
    $log_acao = 'Submissão KYC para o CNPJ ' . ($empresa['cnpj'] ?? 'N/A') . ' foi finalizada e enviada para análise.';
    $stmt_log = $pdo->prepare("INSERT INTO kyc_log_atividades (kyc_empresa_id, usuario_id, usuario_nome, acao) VALUES (?, ?, ?, ?)");
    $stmt_log->execute([$submission_id, $log_usuario_id, $log_usuario_nome, $log_acao]);
    
    $pdo->commit();

    $_SESSION['flash_message'] = "Recebemos suas informações com sucesso. Nossa equipe de compliance iniciará a análise.";
    $_SESSION['submission_id'] = $submission_id;
    $_SESSION['thank_you_partner_id'] = $_POST['id_empresa_master'] ?? null;

    header("Location: kyc_thank_you.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = "Erro ao processar o formulário: " . $e->getMessage();
    error_log("Erro em kyc_submit.php: " . $e->getMessage() . " na linha " . $e->getLine() . "\nTrace: " . $e->getTraceAsString());
    header('Location: kyc_form.php' . (!empty($_POST['cliente_slug']) ? '?cliente=' . urlencode($_POST['cliente_slug']) : ''));
    exit();
}