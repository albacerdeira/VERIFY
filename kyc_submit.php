<?php
// Habilita a exibição de erros para depuração durante o desenvolvimento.
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php'; // Garante que a variável $pdo esteja disponível.

/**
 * Sanitize a string to be used as a directory name.
 * @param string $name The string to sanitize.
 * @return string The sanitized string.
 */
function sanitize_for_path($name) {
    $name = preg_replace('/[^\pL\pN\s-]/u', '', mb_strtolower($name)); // Remove non-alphanumeric chars except for hyphen and space
    $name = preg_replace('/\s+/', '_', $name); // Replace spaces with underscores
    return substr($name, 0, 100); // Truncate to a safe length
}

/**
 * Lida com o upload de um arquivo, movendo-o para um diretório seguro e registrando-o no banco de dados.
 * (função handle_file_upload sem alterações...)
 */
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
            $stmt = $pdo->prepare(
                'INSERT INTO kyc_documentos (empresa_id, socio_id, tipo_documento, path_arquivo, nome_arquivo) VALUES (?, ?, ?, ?, ?)'
            );
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

// **NOVA VERIFICAÇÃO NO SERVIDOR**
if (isset($_POST['empresa']['cnpj'])) {
    $cnpj_submetido = $_POST['empresa']['cnpj'];
    $id_empresa_master = $_POST['id_empresa_master'] ?? $_SESSION['empresa_id'] ?? null;

    // Só executa a verificação se tivermos um parceiro associado
    if ($id_empresa_master) {
        $stmt_check = $pdo->prepare("SELECT 1 FROM kyc_empresas WHERE cnpj = ? AND id_empresa_master = ?");
        $stmt_check->execute([$cnpj_submetido, $id_empresa_master]);

        if ($stmt_check->fetch()) {
            $_SESSION['flash_error'] = "<b>Erro:</b> O CNPJ <strong>{$_POST['empresa']['cnpj']}</strong> já possui um cadastro vinculado a este parceiro. Se precisar atualizar os dados, por favor, entre em contato com o suporte.";
            // Redireciona de volta para o formulário do cliente correto
            $redirect_url = 'kyc_form.php';
            if (isset($_POST['cliente_slug']) && !empty($_POST['cliente_slug'])) {
                $redirect_url .= '?cliente=' . urlencode($_POST['cliente_slug']);
            }
            header('Location: ' . $redirect_url);
            exit();
        }
    }
}


try {
    $pdo->beginTransaction();

    // 1. Inserir Dados da Empresa e Perfil de Negócio (Tabela: kyc_empresas)
    $empresa = $_POST['empresa'];
    $perfil = $_POST['perfil'];

    // --- AÇÃO CHAVE: LÓGICA CORRIGIDA ---
    // Prioriza o ID do parceiro vindo do formulário (cenário white-label).
    // Se não existir, usa o ID do usuário logado (cenário interno).
    if (isset($_POST['id_empresa_master']) && !empty($_POST['id_empresa_master'])) {
        $id_empresa_master = $_POST['id_empresa_master'];
    } else {
        $id_empresa_master = $_SESSION['empresa_id'] ?? null;
    }
    
    // Limpa o CNPJ para salvar no banco
    $cnpj_com_mascara = $empresa['cnpj'] ?? null;

    // Converte a data de DD/MM/YYYY para YYYY-MM-DD para o MySQL, se existir
    $data_constituicao = null;
    if (!empty($empresa['data_constituicao'])) {
        $date_parts = explode('/', $empresa['data_constituicao']);
        if(count($date_parts) == 3) {
            $data_constituicao = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        }
    }

    // Prepara os dados de fundos
    $origem_fundos = isset($perfil['fonte_fundos']) ? implode(', ', $perfil['fonte_fundos']) : null;
    $descricao_fundos = ($origem_fundos && strpos($origem_fundos, 'Terceiros') !== false) ? ($perfil['descricao_fundos_terceiros'] ?? null) : null;
    
    $sql_empresa = "INSERT INTO kyc_empresas (
        id_empresa_master, cnpj, razao_social, nome_fantasia, data_constituicao, cep, logradouro, numero, complemento, bairro, cidade, uf,
        cnae_fiscal, cnae_fiscal_descricao, identificador_matriz_filial, situacao_cadastral, descricao_motivo_situacao_cadastral,
        porte, natureza_juridica, opcao_pelo_simples, representante_legal, email_contato, ddd_telefone_1, observacoes_empresa,
        atividade_principal, motivo_abertura_conta, fluxo_financeiro_pretendido, moedas_operar, blockchains_operar,
        volume_mensal_pretendido, origem_fundos, descricao_fundos_terceiros, consentimento_termos, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Enviado'
    )";
    
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->execute([
        $id_empresa_master,
        $cnpj_com_mascara, $empresa['razao_social'] ?? null, $empresa['nome_fantasia'] ?? null, $data_constituicao,
        $empresa['cep'] ?? null, $empresa['logradouro'] ?? null, $empresa['numero'] ?? null, $empresa['complemento'] ?? null,
        $empresa['bairro'] ?? null, $empresa['cidade'] ?? null, $empresa['uf'] ?? null,
        $empresa['cnae_principal'] ?? null, $empresa['descricao_cnae_principal'] ?? null, $empresa['identificador_matriz_filial'] ?? null,
        $empresa['situacao_cadastral'] ?? null, $empresa['descricao_motivo_situacao_cadastral'] ?? null, $empresa['porte'] ?? null,
        $empresa['natureza_juridica'] ?? null, $empresa['opcao_pelo_simples'] ?? null, $empresa['representante_legal'] ?? null,
        $empresa['email_contato'] ?? null, $empresa['telefone_contato'] ?? null, $empresa['observacoes'] ?? null,
        $perfil['atividade_principal'] ?? null, $perfil['motivo_abertura_conta'] ?? null, $perfil['fluxo_financeiro_pretendido'] ?? null,
        $perfil['moedas_operar'] ?? null, $perfil['blockchains_operar'] ?? null, $perfil['volume_mensal_pretendido'] ?? null,
        $origem_fundos, $descricao_fundos, isset($_POST['termos']['consentimento'])
    ]);

    $empresa_id = $pdo->lastInsertId();

    // O restante do seu script de submissão continua aqui, sem alterações...
    // ... (Lógica de inserir CNAEs, Sócios, Documentos, etc.)

    // 2. Inserir CNAEs Secundários
    if (isset($_POST['cnaes']) && is_array($_POST['cnaes'])) {
        $sql_cnae = "INSERT INTO kyc_cnaes_secundarios (empresa_id, cnae, descricao) VALUES (?, ?, ?)";
        $stmt_cnae = $pdo->prepare($sql_cnae);
        foreach ($_POST['cnaes'] as $cnae) {
            $stmt_cnae->execute([$empresa_id, $cnae['codigo'], $cnae['descricao']]);
        }
    }

    // 3. Inserir Sócios e seus Documentos
    if (isset($_POST['socios']) && is_array($_POST['socios'])) {
        $sql_socio = "INSERT INTO kyc_socios (
            empresa_id, nome_completo, data_nascimento, cpf_cnpj, qualificacao_cargo, percentual_participacao,
            cep, logradouro, numero, complemento, bairro, cidade, uf, observacoes, is_pep, dados_validados
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_socio = $pdo->prepare($sql_socio);

        foreach ($_POST['socios'] as $index => $socio) {
             $data_nascimento_socio = null;
            if (!empty($socio['data_nascimento'])) {
                $date_parts = explode('/', $socio['data_nascimento']);
                if(count($date_parts) == 3) {
                   $data_nascimento_socio = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                }
            }
            
            $socio_documento = $socio['cpf_cnpj'] ?? null;

            $stmt_socio->execute([
                $empresa_id,
                $socio['nome_completo'] ?? null,
                $data_nascimento_socio,
                $socio_documento,
                $socio['qualificacao_cargo'] ?? null,
                empty($socio['percentual_participacao']) ? null : $socio['percentual_participacao'],
                $socio['cep'] ?? null,
                $socio['logradouro'] ?? null,
                $socio['numero'] ?? null,
                $socio['complemento'] ?? null,
                $socio['bairro'] ?? null,
                $socio['cidade'] ?? null,
                $socio['uf'] ?? null,
                $socio['observacoes'] ?? null,
                isset($socio['pep']),
                isset($socio['dados_validados'])
            ]);
            
            $socio_id = $pdo->lastInsertId();

            $files_socios = $_FILES['socios'];
            if (isset($files_socios['name'][$index])) {
                $doc_id_data = [
                    'name' => $files_socios['name'][$index]['doc_identificacao'],
                    'type' => $files_socios['type'][$index]['doc_identificacao'],
                    'tmp_name' => $files_socios['tmp_name'][$index]['doc_identificacao'],
                    'error' => $files_socios['error'][$index]['doc_identificacao'],
                    'size' => $files_socios['size'][$index]['doc_identificacao']
                ];
                handle_file_upload($pdo, $doc_id_data, $empresa_id, $empresa['razao_social'], 'doc_identificacao', $socio_id, $socio['nome_completo']);

                $doc_end_data = [
                    'name' => $files_socios['name'][$index]['doc_endereco'],
                    'type' => $files_socios['type'][$index]['doc_endereco'],
                    'tmp_name' => $files_socios['tmp_name'][$index]['doc_endereco'],
                    'error' => $files_socios['error'][$index]['doc_endereco'],
                    'size' => $files_socios['size'][$index]['doc_endereco']
                ];
                handle_file_upload($pdo, $doc_end_data, $empresa_id, $empresa['razao_social'], 'doc_endereco', $socio_id, $socio['nome_completo']);
            }
        }
    }
    
    // 4. Inserir Documentos da Empresa
    if (isset($_FILES['documentos'])) {
        $documentos_empresa = $_FILES['documentos'];
        $tipos_documento = [
            'contrato_social' => 'doc_contrato_social',
            'ultima_alteracao' => 'doc_ultima_alteracao',
            'cartao_cnpj' => 'doc_cartao_cnpj',
            'balanco_anual' => 'doc_balanco',
            'balancete_trimestral' => 'doc_balancete',
            'dirpj' => 'doc_dirpj'
        ];

        foreach ($tipos_documento as $form_name => $db_type) {
            if (isset($documentos_empresa['name'][$form_name]) && $documentos_empresa['error'][$form_name] == UPLOAD_ERR_OK) {
                $file_data = [
                    'name' => $documentos_empresa['name'][$form_name],
                    'type' => $documentos_empresa['type'][$form_name],
                    'tmp_name' => $documentos_empresa['tmp_name'][$form_name],
                    'error' => $documentos_empresa['error'][$form_name],
                    'size' => $documentos_empresa['size'][$form_name]
                ];
                handle_file_upload($pdo, $file_data, $empresa_id, $empresa['razao_social'], $db_type);
            }
        }
    }

    // 5. Registrar Log
    if (isset($_SESSION['user_id'])) {
        $log_usuario_id = $_SESSION['user_id'];
        $log_usuario_nome = $_SESSION['nome'] ?? 'Usuário do Sistema';
        $log_acao = 'Submissão KYC para o CNPJ ' . ($cnpj_com_mascara ?? 'N/A') . ' foi criada.';
        $stmt_log = $pdo->prepare("INSERT INTO kyc_log_atividades (kyc_empresa_id, usuario_id, usuario_nome, acao) VALUES (?, ?, ?, ?)");
        $stmt_log->execute([$empresa_id, $log_usuario_id, $log_usuario_nome, $log_acao]);
    }
    
   $pdo->commit();

    // 6. Limpa o LocalStorage e redireciona para a página de agradecimento
    echo "<script>localStorage.removeItem('kycFormData_v5.1');</script>";
    $_SESSION['flash_message'] = "Recebemos suas informações com sucesso. Nossa equipe de compliance iniciará a análise.";
    $_SESSION['submission_id'] = $empresa_id;
    
    // LINHA NOVA: Guarda o ID do parceiro para a página de agradecimento usar
    $_SESSION['thank_you_partner_id'] = $id_empresa_master;

    header("Location: kyc_thank_you.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = "Erro ao processar o formulário: " . $e->getMessage() . "<br>Por favor, tente novamente. Se o erro persistir, contate o suporte.";
    header('Location: kyc_form.php');
    exit();
}