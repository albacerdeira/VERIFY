<?php
$page_title = 'Análise de Caso KYC';
require_once 'bootstrap.php'; // Usa o novo sistema de inicialização

// --- Validação de Acesso e Permissões ---
$kyc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$kyc_id) {
    require_once 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>ID do caso KYC inválido.</div></div>";
    require_once 'footer.php';
    exit();
}

// Carrega os dados do caso para verificar a propriedade
$stmt_caso = $pdo->prepare("SELECT id_empresa_master FROM kyc_empresas WHERE id = :id");
$stmt_caso->execute(['id' => $kyc_id]);
$caso_propriedade = $stmt_caso->fetch(PDO::FETCH_ASSOC);

// Verifica se o usuário tem permissão para ver este caso
$permitido = false;
if ($is_superadmin) {
    $permitido = true;
} elseif (($is_admin || $is_analista) && $caso_propriedade && $caso_propriedade['id_empresa_master'] == $user_empresa_id) {
    $permitido = true;
}

if (!$permitido) {
    require_once 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado. Você não tem permissão para visualizar este caso.</div></div>";
    require_once 'footer.php';
    exit();
}

// --- Carregamento Completo dos Dados (se a permissão foi concedida) ---
$stmt_caso = $pdo->prepare("SELECT e.*, a.*, e.id AS kyc_id_real, e.status AS status_caso FROM kyc_empresas e LEFT JOIN kyc_avaliacoes a ON e.id = a.kyc_empresa_id WHERE e.id = :id");
$stmt_caso->execute(['id' => $kyc_id]);
$caso = $stmt_caso->fetch(PDO::FETCH_ASSOC);

// --- Buscar dados do cliente associado ao caso ---
$cliente = null;
if ($caso && !empty($caso['cliente_id'])) {
    $stmt_cliente = $pdo->prepare("SELECT id, nome_completo, cpf, email, status, created_at, updated_at, selfie_path FROM kyc_clientes WHERE id = :cliente_id");
    $stmt_cliente->execute(['cliente_id' => $caso['cliente_id']]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
}

// --- VERIFICAÇÃO CEIS (PJ - Empresa) ---
$sancoes_ceis_confirmadas_pj = []; 
$ceis_match_found_pj = false;       
$sancoes_ceis_confirmadas_pf = [];
$ceis_match_found_pf = false;

// --- VERIFICAÇÃO CNEP ---
$sancoes_cnep_confirmadas_pj = [];
$cnep_match_found_pj = false;
$sancoes_cnep_confirmadas_pf = [];
$cnep_match_found_pf = false;


if ($caso && !empty($caso['cnpj'])) {
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $caso['cnpj']);
    $cnpj_raiz = substr($cnpj_limpo, 0, 5); 
    $razao_social_caso = strtoupper($caso['razao_social']); 
    
    if (strlen($cnpj_raiz) === 5) { 
        // Lógica CEIS PJ
        $stmt_ceis = $pdo->prepare("SELECT * FROM ceis WHERE cpf_cnpj_sancionado COLLATE utf8mb4_general_ci LIKE :cnpj_raiz");
        $stmt_ceis->execute([':cnpj_raiz' => $cnpj_raiz . '%']);
        $potenciais_sancoes = $stmt_ceis->fetchAll(PDO::FETCH_ASSOC);

        foreach ($potenciais_sancoes as $sancao) {
            $similaridade_nome = 0; $similaridade_razao = 0;
            if (!empty($sancao['nome_sancionado'])) { similar_text($razao_social_caso, strtoupper($sancao['nome_sancionado']), $similaridade_nome); }
            if (!empty($sancao['razao_social'])) { similar_text($razao_social_caso, strtoupper($sancao['razao_social']), $similaridade_razao); }

            if ($similaridade_nome >= 85 || $similaridade_razao >= 85) {
                $ceis_match_found_pj = true; 
                $sancoes_ceis_confirmadas_pj[] = $sancao; 
            }
        }

        // LÓGICA CNEP PJ
        $stmt_cnep = $pdo->prepare("SELECT * FROM cnep WHERE cpf_cnpj_sancionado COLLATE utf8mb4_general_ci LIKE :cnpj_raiz");
        $stmt_cnep->execute([':cnpj_raiz' => $cnpj_raiz . '%']);
        $potenciais_sancoes_cnep = $stmt_cnep->fetchAll(PDO::FETCH_ASSOC);

        foreach ($potenciais_sancoes_cnep as $sancao) {
            $similaridade_nome = 0; $similaridade_razao = 0;
            if (!empty($sancao['nome_sancionado'])) { similar_text($razao_social_caso, strtoupper($sancao['nome_sancionado']), $similaridade_nome); }
            if (!empty($sancao['razao_social'])) { similar_text($razao_social_caso, strtoupper($sancao['razao_social']), $similaridade_razao); }

            if ($similaridade_nome >= 85 || $similaridade_razao >= 85) {
                $cnep_match_found_pj = true; 
                $sancoes_cnep_confirmadas_pj[] = $sancao; 
            }
        }
    }
}
// --- FIM DA VERIFICAÇÃO PJ (CEIS e CNEP) ---

// --- INÍCIO DAS VERIFICAÇÕES DE SÓCIOS (PF) ---

// Carrega os CPFs dos sócios
$stmt_socios_cpfs = $pdo->prepare("SELECT id, cpf_cnpj FROM kyc_socios WHERE empresa_id = :id");
$stmt_socios_cpfs->execute(['id' => $kyc_id]);
$socios_cpfs_data = $stmt_socios_cpfs->fetchAll(PDO::FETCH_ASSOC);

$cpfs_limpos_para_buscar = [];
$cpfs_formatados_para_buscar = [];
$map_middle_to_full_cpf = []; // Mapeia a parte do meio para o CPF completo formatado

if (!empty($socios_cpfs_data)) {
    foreach ($socios_cpfs_data as $socio) {
        $cpf_cnpj_formatado = $socio['cpf_cnpj'];
        $cpf_cnpj_limpo = preg_replace('/[^0-9]/', '', $cpf_cnpj_formatado);

        if (strlen($cpf_cnpj_limpo) === 11) { // É um CPF
            $cpfs_limpos_para_buscar[] = $cpf_cnpj_limpo;
            if (strlen($cpf_cnpj_formatado) === 14) { // Verifica se tem o formato XXX.XXX.XXX-XX
                 $cpfs_formatados_para_buscar[] = $cpf_cnpj_formatado;
                 // Extrai a parte do meio XXX.XXX
                 $middle_part = substr($cpf_cnpj_formatado, 4, 7); // Pega a partir do 5º caractere (índice 4), 7 caracteres
                 if (strlen($middle_part) === 7) {
                     $map_middle_to_full_cpf[$middle_part] = $cpf_cnpj_formatado;
                 }
            }
        }
    }
}

$cpfs_limpos_unicos = array_unique($cpfs_limpos_para_buscar);
$cpfs_formatados_unicos = array_unique($cpfs_formatados_para_buscar);
$middle_parts_unicos = array_keys($map_middle_to_full_cpf); // Pega as chaves únicas (partes do meio)

// --- VERIFICAÇÃO CEIS (PF - Sócios) ---
if (!empty($cpfs_limpos_unicos)) {
    $placeholders = rtrim(str_repeat('?,', count($cpfs_limpos_unicos)), ',');
    $sql_ceis_pf = "SELECT * FROM ceis WHERE cpf_cnpj_sancionado IN ($placeholders)";
    
    try {
        $stmt_ceis_pf = $pdo->prepare($sql_ceis_pf);
        $stmt_ceis_pf->execute(array_values($cpfs_limpos_unicos)); 
        $sancoes_encontradas_pf = $stmt_ceis_pf->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($sancoes_encontradas_pf)) {
            $ceis_match_found_pf = true;
            $sancoes_ceis_confirmadas_pf = $sancoes_encontradas_pf;
        }
    } catch (PDOException $e) {
         error_log("Erro ao buscar CEIS para Sócios (PF) kyc_id=$kyc_id: " . $e->getMessage());
    }

    // --- VERIFICAÇÃO CNEP (PF - Sócios) ---
    $sql_cnep_pf = "SELECT * FROM cnep WHERE cpf_cnpj_sancionado IN ($placeholders)";
    try {
        $stmt_cnep_pf = $pdo->prepare($sql_cnep_pf);
        $stmt_cnep_pf->execute(array_values($cpfs_limpos_unicos)); 
        $sancoes_encontradas_cnep_pf = $stmt_cnep_pf->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($sancoes_encontradas_cnep_pf)) {
            $cnep_match_found_pf = true;
            $sancoes_cnep_confirmadas_pf = $sancoes_encontradas_cnep_pf;
        }
    } catch (PDOException $e) {
         error_log("Erro ao buscar CNEP para Sócios (PF) kyc_id=$kyc_id: " . $e->getMessage());
    }
}
// --- FIM DA VERIFICAÇÃO CEIS/CNEP (PF) ---

// --- CORREÇÃO: VERIFICAÇÃO PEP (PF - Sócios) usando LIKE ---
$peps_confirmados_pf = [];
$pep_match_found_pf = false;
$pep_cpfs_found_full = []; // Armazena os CPFs completos encontrados como PEP

if (!empty($middle_parts_unicos)) {
    $sql_pep_conditions = [];
    $sql_pep_params = [];
    foreach ($middle_parts_unicos as $middle_part) {
        // Cria um padrão LIKE para o formato ***.XXX.XXX-**
        $sql_pep_conditions[] = "cpf COLLATE utf8mb4_general_ci LIKE ?";
        $sql_pep_params[] = '***.' . $middle_part . '-**'; 
    }
    
    $sql_pep_pf = "SELECT * FROM peps WHERE " . implode(' OR ', $sql_pep_conditions);
    
    try {
        $stmt_pep_pf = $pdo->prepare($sql_pep_pf);
        $stmt_pep_pf->execute($sql_pep_params);
        $peps_encontrados_pf = $stmt_pep_pf->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($peps_encontrados_pf)) {
            $pep_match_found_pf = true;
            $peps_confirmados_pf = $peps_encontrados_pf;

            // Mapeia de volta para os CPFs completos para a atualização
            foreach ($peps_encontrados_pf as $pep) {
                // Extrai a parte do meio do CPF encontrado na tabela peps
                $found_middle_part = substr($pep['cpf'], 4, 7); 
                if (isset($map_middle_to_full_cpf[$found_middle_part])) {
                    $pep_cpfs_found_full[] = $map_middle_to_full_cpf[$found_middle_part];
                }
            }
            $pep_cpfs_found_full = array_unique($pep_cpfs_found_full); // Garante CPFs únicos

            // ATUALIZA A FLAG is_pep NA TABELA kyc_socios usando os CPFs COMPLETOS
            if (!empty($pep_cpfs_found_full)) {
                $placeholders_update = rtrim(str_repeat('?,', count($pep_cpfs_found_full)), ',');
                $sql_update_pep = "UPDATE kyc_socios SET is_pep = 1 WHERE empresa_id = ? AND cpf_cnpj IN ($placeholders_update)";
                
                try {
                    $stmt_update_pep = $pdo->prepare($sql_update_pep);
                    $params = array_merge([$kyc_id], $pep_cpfs_found_full);
                    $stmt_update_pep->execute($params);
                } catch (PDOException $e) {
                    error_log("Erro ao ATUALIZAR flag PEP para kyc_id=$kyc_id: " . $e->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
         error_log("Erro ao buscar PEPs para Sócios (PF) kyc_id=$kyc_id: " . $e->getMessage());
    }
}
// --- FIM DA VERIFICAÇÃO PEP (PF) ---


if (!$caso) {
    require_once 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Registro KYC não encontrado.</div></div>";
    require_once 'footer.php';
    exit();
}

// Define o ID e nome do analista a partir da sessão
$analista_id = $_SESSION['user_id'];
$analista_nome = $_SESSION['user_nome'] ?? 'Usuário Desconhecido';


// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $av_check_ceis_pj_ok_value = $ceis_match_found_pj ? 0 : 1;
    $av_check_ceis_pf_ok_value = $ceis_match_found_pf ? 0 : 1;
    $av_check_cnep_pj_ok_value = $cnep_match_found_pj ? 0 : 1;
    $av_check_cnep_pf_ok_value = $cnep_match_found_pf ? 0 : 1;

    $pdo->beginTransaction();
    try {
        // 1. Atualiza o status na tabela principal
        $stmt_status = $pdo->prepare("UPDATE kyc_empresas SET status = :status WHERE id = :kyc_id");
        $stmt_status->execute([':status' => $_POST['status_caso'], ':kyc_id' => $kyc_id]);

        // 2. Insere ou atualiza o registro de avaliação geral
        $avaliacao_sql = "
            INSERT INTO kyc_avaliacoes (
                kyc_empresa_id, av_analista_id, 
                av_check_dados_empresa_ok, av_check_perfil_negocio_ok, av_check_socios_ubos_ok, av_check_socios_ubos_origin, av_check_documentos_ok, 
                av_check_ceis_ok, av_check_ceis_pf_ok,
                av_check_cnep_ok, av_check_cnep_pf_ok,
                av_obs_dados_empresa, 
                av_check_perfil_atividade, av_check_perfil_motivo, av_check_perfil_fluxo, av_check_perfil_volume, av_check_perfil_origem, 
                av_obs_perfil_negocio,
                av_check_doc_contrato_social, av_check_doc_ultima_alteracao, av_check_doc_cartao_cnpj, av_check_doc_balanco, av_check_doc_balancete, av_check_doc_dirpj,
                av_obs_documentos,
                av_risco_atividade, av_risco_geografico, av_risco_societario, av_risco_midia_pep, 
                av_risco_final, av_justificativa_risco, av_info_pendencia
            ) VALUES (
                :kyc_id, :analista_id, 
                :c1, :c2, :c3, :c3_origin, :c4,
                :c_ceis_pj, :c_ceis_pf,
                :c_cnep_pj, :c_cnep_pf,
                :obs1, :cp1, :cp2, :cp3, :cp4, :cp5, :obs2,
                :cd1, :cd2, :cd3, :cd4, :cd5, :cd6, :obs3,
                :r1, :r2, :r3, :r4,
                :r_final, :justificativa, :pendencia
            ) ON DUPLICATE KEY UPDATE 
                av_analista_id = VALUES(av_analista_id),
                av_check_dados_empresa_ok = VALUES(av_check_dados_empresa_ok),
                av_check_perfil_negocio_ok = VALUES(av_check_perfil_negocio_ok),
                av_check_socios_ubos_ok = VALUES(av_check_socios_ubos_ok),
                av_check_socios_ubos_origin = VALUES(av_check_socios_ubos_origin),
                av_check_documentos_ok = VALUES(av_check_documentos_ok),
                av_check_ceis_ok = VALUES(av_check_ceis_ok),
                av_check_ceis_pf_ok = VALUES(av_check_ceis_pf_ok),
                av_check_cnep_ok = VALUES(av_check_cnep_ok),
                av_check_cnep_pf_ok = VALUES(av_check_cnep_pf_ok),
                av_obs_dados_empresa = VALUES(av_obs_dados_empresa),
                av_check_perfil_atividade = VALUES(av_check_perfil_atividade),
                av_check_perfil_motivo = VALUES(av_check_perfil_motivo),
                av_check_perfil_fluxo = VALUES(av_check_perfil_fluxo),
                av_check_perfil_volume = VALUES(av_check_perfil_volume),
                av_check_perfil_origem = VALUES(av_check_perfil_origem),
                av_obs_perfil_negocio = VALUES(av_obs_perfil_negocio),
                av_check_doc_contrato_social = VALUES(av_check_doc_contrato_social),
                av_check_doc_ultima_alteracao = VALUES(av_check_doc_ultima_alteracao),
                av_check_doc_cartao_cnpj = VALUES(av_check_doc_cartao_cnpj),
                av_check_doc_balanco = VALUES(av_check_doc_balanco),
                av_check_doc_balancete = VALUES(av_check_doc_balancete),
                av_check_doc_dirpj = VALUES(av_check_doc_dirpj),
                av_obs_documentos = VALUES(av_obs_documentos),
                av_risco_atividade = VALUES(av_risco_atividade),
                av_risco_geografico = VALUES(av_risco_geografico),
                av_risco_societario = VALUES(av_risco_societario),
                av_risco_midia_pep = VALUES(av_risco_midia_pep),
                av_risco_final = VALUES(av_risco_final),
                av_justificativa_risco = VALUES(av_justificativa_risco),
                av_info_pendencia = VALUES(av_info_pendencia)
        ";
        $stmt_aval = $pdo->prepare($avaliacao_sql);
        $stmt_aval->execute([
            ':kyc_id' => $kyc_id, ':analista_id' => $analista_id,
            ':c1' => isset($_POST['av_check_dados_empresa_ok']) ? 1 : 0, 
            ':c2' => isset($_POST['av_check_perfil_negocio_ok']) ? 1 : 0,
            ':c3' => isset($_POST['av_check_socios_ubos_ok']) ? 1 : 0, 
            ':c3_origin' => $_POST['av_check_socios_ubos_origin'] ?? 'analyst',
            ':c4' => isset($_POST['av_check_documentos_ok']) ? 1 : 0,
            ':c_ceis_pj' => $av_check_ceis_pj_ok_value,
            ':c_ceis_pf' => $av_check_ceis_pf_ok_value,
            ':c_cnep_pj' => $av_check_cnep_pj_ok_value,
            ':c_cnep_pf' => $av_check_cnep_pf_ok_value,
            ':obs1' => $_POST['av_obs_dados_empresa'] ?? null,
            ':cp1' => isset($_POST['av_check_perfil_atividade']) ? 1 : 0,
            ':cp2' => isset($_POST['av_check_perfil_motivo']) ? 1 : 0,
            ':cp3' => isset($_POST['av_check_perfil_fluxo']) ? 1 : 0,
            ':cp4' => isset($_POST['av_check_perfil_volume']) ? 1 : 0,
            ':cp5' => isset($_POST['av_check_perfil_origem']) ? 1 : 0,
            ':obs2' => $_POST['av_obs_perfil_negocio'] ?? null,
            ':cd1' => isset($_POST['av_check_doc_contrato_social']) ? 1 : 0,
            ':cd2' => isset($_POST['av_check_doc_ultima_alteracao']) ? 1 : 0,
            ':cd3' => isset($_POST['av_check_doc_cartao_cnpj']) ? 1 : 0,
            ':cd4' => isset($_POST['av_check_doc_balanco']) ? 1 : 0,
            ':cd5' => isset($_POST['av_check_doc_balancete']) ? 1 : 0,
            ':cd6' => isset($_POST['av_check_doc_dirpj']) ? 1 : 0,
            ':obs3' => $_POST['av_obs_documentos'] ?? null,
            ':r1' => $_POST['av_risco_atividade'] ?? null, 
            ':r2' => $_POST['av_risco_geografico'] ?? null,
            ':r3' => $_POST['av_risco_societario'] ?? null, 
            ':r4' => $_POST['av_risco_midia_pep'] ?? null,
            ':r_final' => $_POST['av_risco_final'] ?? null, 
            ':justificativa' => $_POST['av_justificativa_risco'] ?? null,
            ':pendencia' => $_POST['av_info_pendencia'] ?? null
        ]);

        // 3. Atualiza a análise individual dos sócios
        if (isset($_POST['socio_observacoes']) && is_array($_POST['socio_observacoes'])) {
            $socio_update_sql = "UPDATE kyc_socios SET av_socio_verificado = :verificado, av_socio_observacoes = :observacoes WHERE id = :socio_id AND empresa_id = :kyc_id";
            $stmt_socio = $pdo->prepare($socio_update_sql);
            foreach ($_POST['socio_observacoes'] as $socio_id => $observacoes) {
                $socio_id_int = (int)$socio_id;
                $verificado = isset($_POST['socio_verificado'][$socio_id_int]) ? 1 : 0;
                $stmt_socio->execute([
                    ':verificado' => $verificado, ':observacoes' => $observacoes,
                    ':socio_id' => $socio_id_int, ':kyc_id' => $kyc_id
                ]);
            }
        }

        // 4. Prepara e registra a ação no log com o snapshot completo da avaliação
        $log_acao = sprintf("Status alterado para '%s'. Análise de risco final: %s.", htmlspecialchars($_POST['status_caso']), htmlspecialchars($_POST['av_risco_final']));

        // Adiciona todos os novos campos ao array do snapshot
        $snapshot_data = [
            'status_caso' => $_POST['status_caso'],
            'av_check_dados_empresa_ok' => isset($_POST['av_check_dados_empresa_ok']) ? 1 : 0,
            'av_check_perfil_negocio_ok' => isset($_POST['av_check_perfil_negocio_ok']) ? 1 : 0,
            'av_check_socios_ubos_ok' => isset($_POST['av_check_socios_ubos_ok']) ? 1 : 0,
            'av_check_socios_ubos_origin' => $_POST['av_check_socios_ubos_origin'] ?? 'analyst',
            'av_check_documentos_ok' => isset($_POST['av_check_documentos_ok']) ? 1 : 0,
            'av_check_ceis_ok' => $av_check_ceis_pj_ok_value,
            'av_check_ceis_pf_ok' => $av_check_ceis_pf_ok_value,
            'av_check_cnep_ok' => $av_check_cnep_pj_ok_value,
            'av_check_cnep_pf_ok' => $av_check_cnep_pf_ok_value,
            'av_anotacoes_internas' => $_POST['av_anotacoes_internas'] ?? null,
            'av_risco_atividade' => $_POST['av_risco_atividade'] ?? null,
            'av_risco_geografico' => $_POST['av_risco_geografico'] ?? null,
            'av_risco_societario' => $_POST['av_risco_societario'] ?? null,
            'av_risco_midia_pep' => $_POST['av_risco_midia_pep'] ?? null,
            'av_risco_final' => $_POST['av_risco_final'] ?? null,
            'av_justificativa_risco' => $_POST['av_justificativa_risco'] ?? null,
            'av_info_pendencia' => $_POST['av_info_pendencia'] ?? null,
            
            'av_obs_dados_empresa' => $_POST['av_obs_dados_empresa'] ?? null,
            'av_obs_perfil_negocio' => $_POST['av_obs_perfil_negocio'] ?? null,
            'av_obs_documentos' => $_POST['av_obs_documentos'] ?? null,

            'av_check_perfil_atividade' => isset($_POST['av_check_perfil_atividade']) ? 1 : 0,
            'av_check_perfil_motivo' => isset($_POST['av_check_perfil_motivo']) ? 1 : 0,
            'av_check_perfil_fluxo' => isset($_POST['av_check_perfil_fluxo']) ? 1 : 0,
            'av_check_perfil_volume' => isset($_POST['av_check_perfil_volume']) ? 1 : 0,
            'av_check_perfil_origem' => isset($_POST['av_check_perfil_origem']) ? 1 : 0,

            'av_check_doc_contrato_social' => isset($_POST['av_check_doc_contrato_social']) ? 1 : 0,
            'av_check_doc_ultima_alteracao' => isset($_POST['av_check_doc_ultima_alteracao']) ? 1 : 0,
            'av_check_doc_cartao_cnpj' => isset($_POST['av_check_doc_cartao_cnpj']) ? 1 : 0,
            'av_check_doc_balanco' => isset($_POST['av_check_doc_balanco']) ? 1 : 0,
            'av_check_doc_balancete' => isset($_POST['av_check_doc_balancete']) ? 1 : 0,
            'av_check_doc_dirpj' => isset($_POST['av_check_doc_dirpj']) ? 1 : 0,

            'analise_socios' => []
        ];

        if (isset($_POST['socio_observacoes']) && is_array($_POST['socio_observacoes'])) {
            foreach ($_POST['socio_observacoes'] as $socio_id => $observacoes) {
                $snapshot_data['analise_socios'][$socio_id] = [
                    'observacoes' => $observacoes,
                    'verificado' => isset($_POST['socio_verificado'][$socio_id]) ? 1 : 0
                ];
            }
        }

        $stmt_log = $pdo->prepare("INSERT INTO kyc_log_atividades (kyc_empresa_id, usuario_id, usuario_nome, acao, dados_avaliacao_snapshot) VALUES (:kyc_id, :user_id, :user_name, :acao, :snapshot)");
        $stmt_log->execute([
            ':kyc_id' => $kyc_id, 
            ':user_id' => $analista_id,
            ':user_name' => $analista_nome, 
            ':acao' => $log_acao,
            ':snapshot' => json_encode($snapshot_data)
        ]);

        $pdo->commit();
        $_SESSION['flash_message'] = "Análise salva com sucesso!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Erro ao salvar a análise. Detalhes: " . $e->getMessage();
        error_log("Erro ao salvar análise KYC para kyc_id=$kyc_id: " . $e->getMessage());
    }
    header("Location: kyc_list.php");
    exit;
}

// --- CARREGAMENTO DE DADOS (GET) ---

// Carrega dados relacionados
// IMPORTANTE: Esta consulta é FEITA DEPOIS da lógica de atualização da flag PEP.
// Assim, $socio['is_pep'] já virá atualizado do banco.
$socios = $pdo->prepare("
    SELECT 
        id, empresa_id, nome_completo, data_nascimento, cpf_cnpj, qualificacao_cargo, 
        percentual_participacao, cep, logradouro, numero, complemento, bairro, cidade, uf, 
        observacoes, is_pep, dados_validados, av_socio_verificado, av_socio_observacoes 
    FROM kyc_socios 
    WHERE empresa_id = :id
");
$socios->execute(['id' => $kyc_id]);

// Carrega todos os documentos em um array de uma só vez
$stmt_docs = $pdo->prepare("SELECT * FROM kyc_documentos WHERE empresa_id = :id");
$stmt_docs->execute(['id' => $kyc_id]);
$all_documents = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

$cnaes_secundarios = $pdo->prepare("SELECT * FROM kyc_cnaes_secundarios WHERE empresa_id = :id ORDER BY id");
$cnaes_secundarios->execute(['id' => $kyc_id]);

$log_atividades = $pdo->prepare("
    SELECT 
        l.id, l.timestamp, l.acao, l.dados_avaliacao_snapshot,
        COALESCE(u.nome, sa.nome) AS nome_analista
    FROM kyc_log_atividades l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    LEFT JOIN superadmin sa ON l.usuario_id = sa.id
    WHERE l.kyc_empresa_id = :kyc_id 
    ORDER BY l.timestamp DESC
");
$log_atividades->execute(['kyc_id' => $kyc_id]);

$page_title = "Análise KYC - " . htmlspecialchars($caso['razao_social']);
require 'header.php';


// --- FUNÇÕES AUXILIARES DE RENDERIZAÇÃO ---
if (!function_exists('display_field')) {
    function display_field($label, $value, $is_date = false) {
        $display_value = !empty($value) ? ($is_date ? date('d/m/Y H:i:s', strtotime($value)) : nl2br(htmlspecialchars($value))) : '<span class="text-muted fst-italic">Não informado</span>';
        return "<div class='mb-3'><p class='text-muted small mb-1'>$label</p><strong>$display_value</strong></div>";
    }
}
if (!function_exists('render_risk_select')) {
    function render_risk_select($name, $label, $current_value) {
        $options = [
            'Baixo' => ['class' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'],
            'Médio' => ['class' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-triangle-fill'],
            'Alto' => ['class' => 'bg-danger text-white', 'icon' => 'bi-x-octagon-fill']
        ];
        
        // Determina a classe do select baseado no valor atual
        $selectClass = 'form-select';
        if ($current_value && isset($options[$current_value])) {
            $selectClass .= ' ' . $options[$current_value]['class'];
        }
        
        $html = "<div class='mb-3'>
                    <label for='$name' class='form-label fw-semibold'>
                        <i class='bi bi-speedometer2 me-1'></i>$label
                    </label>
                    <select name='$name' id='$name' class='$selectClass' data-risk-select='true'>
                        <option value=''>Selecionar...</option>";
        
        foreach ($options as $option => $config) {
            $selected = ($current_value == $option) ? 'selected' : '';
            $html .= "<option value='$option' $selected data-class='{$config['class']}' data-icon='{$config['icon']}'>
                        $option
                      </option>";
        }
        
        $html .= "</select></div>";
        return $html;
    }
}
function render_checklist_icon($name, $label, $is_checked) {
    $icon_class = $is_checked ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
    $checked_attr = $is_checked ? 'checked' : '';
    return sprintf(
        '<div class="form-check d-flex align-items-center mb-2">
            <input class="form-check-input me-2" type="checkbox" name="%s" id="%s" value="1" %s>
            <i class="%s me-2"></i>
            <label class="form-check-label" for="%s">%s</label>
        </div>',
        $name, $name, $checked_attr, $icon_class, $name, $label
    );
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<style>
    .checklist-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
        padding: 0;
        clear: both;
    }
    .checklist-item-icon {
        width: 24px;
        text-align: center;
        flex-shrink: 0;
        font-size: 1.2rem;
    }
    .checklist-item-label {
        margin-left: 0.75rem;
        font-weight: 500;
    }
    /* Para os modais de ficha (CEIS, CNEP e PEP) */
    .ficha-modal-content dt {
        font-weight: 500;
        color: #555;
    }
    .ficha-modal-content dd {
        font-weight: 400;
    }
    
    /* Sistema de Pin */
    .row {
        display: flex;
        flex-wrap: wrap;
    }
    #rightColumn {
        transition: all 0.3s ease;
    }
    #rightColumn.pinned {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: 20px !important;
        align-self: flex-start !important;
        z-index: 100 !important;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
    /* Scrollbar customizada quando pinado - usa cor da empresa */
    #rightColumn.pinned::-webkit-scrollbar {
        width: 8px;
    }
    #rightColumn.pinned::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    #rightColumn.pinned::-webkit-scrollbar-thumb {
        background: var(--primary-color, #198754);
        border-radius: 10px;
    }
    #rightColumn.pinned::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--primary-color, #198754) 85%, black);
    }
    /* Firefox scrollbar */
    #rightColumn.pinned {
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color, #198754) #f1f1f1;
    }
    #pinDocumentosBtn {
        transition: all 0.3s ease;
    }
    #rightColumn.pinned #pinDocumentosBtn {
        background-color: color-mix(in srgb, var(--primary-color, #198754) 20%, white) !important;
        border-color: var(--primary-color, #198754) !important;
        color: var(--primary-color, #198754) !important;
    }
    #rightColumn.pinned #pinDocumentosBtn:hover {
        background-color: var(--primary-color, #198754) !important;
        color: white !important;
    }
    #pinDocumentosBtn .bi-pin-angle {
        transition: transform 0.3s ease;
    }
    #rightColumn.pinned #pinDocumentosBtn .bi-pin-angle {
        transform: rotate(45deg);
    }
</style>

<form action="kyc_evaluate.php?id=<?= $kyc_id; ?>" method="POST">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-lg-7">

                <!-- Accordion de Alertas de Compliance -->
                <?php if (
                    ($ceis_match_found_pj && !empty($sancoes_ceis_confirmadas_pj)) ||
                    ($ceis_match_found_pf && !empty($sancoes_ceis_confirmadas_pf)) ||
                    ($cnep_match_found_pj && !empty($sancoes_cnep_confirmadas_pj)) ||
                    ($cnep_match_found_pf && !empty($sancoes_cnep_confirmadas_pf)) ||
                    ($pep_match_found_pf && !empty($peps_confirmados_pf))
                ): ?>
                <div class="accordion mb-4" id="accordionAlertas">
                    
                    <?php if ($ceis_match_found_pj && !empty($sancoes_ceis_confirmadas_pj)): ?>
                    <div class="accordion-item border-danger">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCeisPJ" aria-expanded="true">
                                <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><strong>Alerta de Sanção (CEIS - Empresa PJ)</strong>
                                <span class="badge bg-danger text-white ms-2"><?= count($sancoes_ceis_confirmadas_pj) ?></span>
                            </button>
                        </h2>
                        <div id="collapseCeisPJ" class="accordion-collapse collapse show" data-bs-parent="#accordionAlertas">
                            <div class="accordion-body">
                                <p>Atenção: Foram encontradas sanções com alta similaridade de nome no CEIS para o CNPJ raiz desta empresa.</p>
                                <ul class="list-group">
                                    <?php foreach ($sancoes_ceis_confirmadas_pj as $sancao): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div><strong>Sancionado:</strong> <?= htmlspecialchars($sancao['nome_sancionado'] ?: $sancao['razao_social']) ?><br><small><strong>Órgão:</strong> <?= htmlspecialchars($sancao['orgao_sancionador']) ?></small></div>
                                            <button type="button" class="btn btn-outline-danger btn-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#ceisDetailModal" data-sancao-json="<?= htmlspecialchars(json_encode($sancao), ENT_QUOTES, 'UTF-8') ?>">Ver Ficha</button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($ceis_match_found_pf && !empty($sancoes_ceis_confirmadas_pf)): ?>
                    <div class="accordion-item border-danger">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCeisPF" aria-expanded="false">
                                <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><strong>Alerta de Sanção (CEIS - Sócios PF)</strong>
                                <span class="badge bg-danger text-white ms-2"><?= count($sancoes_ceis_confirmadas_pf) ?></span>
                            </button>
                        </h2>
                        <div id="collapseCeisPF" class="accordion-collapse collapse" data-bs-parent="#accordionAlertas">
                            <div class="accordion-body">
                                <p>Atenção: Foram encontradas sanções no CEIS para um ou mais CPFs dos sócios/administradores.</p>
                                <ul class="list-group">
                                    <?php foreach ($sancoes_ceis_confirmadas_pf as $sancao): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div><strong>Sancionado:</strong> <?= htmlspecialchars($sancao['nome_sancionado'] ?: 'Não informado') ?><br><small><strong>CPF:</strong> <?= htmlspecialchars($sancao['cpf_cnpj_sancionado']) ?> | <strong>Órgão:</strong> <?= htmlspecialchars($sancao['orgao_sancionador']) ?></small></div>
                                            <button type="button" class="btn btn-outline-danger btn-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#ceisDetailModal" data-sancao-json="<?= htmlspecialchars(json_encode($sancao), ENT_QUOTES, 'UTF-8') ?>">Ver Ficha</button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($cnep_match_found_pj && !empty($sancoes_cnep_confirmadas_pj)): ?>
                    <div class="accordion-item border-warning">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCnepPJ" aria-expanded="false">
                                <i class="bi bi-exclamation-diamond-fill text-warning me-2"></i><strong>Alerta de Sanção (CNEP - Empresa PJ)</strong>
                                <span class="badge bg-warning text-dark ms-2"><?= count($sancoes_cnep_confirmadas_pj) ?></span>
                            </button>
                        </h2>
                        <div id="collapseCnepPJ" class="accordion-collapse collapse" data-bs-parent="#accordionAlertas">
                            <div class="accordion-body">
                                <p>Atenção: Foram encontradas sanções com alta similaridade de nome no CNEP para o CNPJ raiz desta empresa.</p>
                                <ul class="list-group">
                                    <?php foreach ($sancoes_cnep_confirmadas_pj as $sancao): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div><strong>Sancionado:</strong> <?= htmlspecialchars($sancao['nome_sancionado'] ?: $sancao['razao_social']) ?><br><small><strong>Órgão:</strong> <?= htmlspecialchars($sancao['orgao_sancionador']) ?></small></div>
                                            <button type="button" class="btn btn-outline-warning btn-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#cnepDetailModal" data-sancao-json="<?= htmlspecialchars(json_encode($sancao), ENT_QUOTES, 'UTF-8') ?>">Ver Ficha</button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($cnep_match_found_pf && !empty($sancoes_cnep_confirmadas_pf)): ?>
                    <div class="accordion-item border-warning">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCnepPF" aria-expanded="false">
                                <i class="bi bi-exclamation-diamond-fill text-warning me-2"></i><strong>Alerta de Sanção (CNEP - Sócios PF)</strong>
                                <span class="badge bg-warning text-dark ms-2"><?= count($sancoes_cnep_confirmadas_pf) ?></span>
                            </button>
                        </h2>
                        <div id="collapseCnepPF" class="accordion-collapse collapse" data-bs-parent="#accordionAlertas">
                            <div class="accordion-body">
                                <p>Atenção: Foram encontradas sanções no CNEP para um ou mais CPFs dos sócios/administradores.</p>
                                <ul class="list-group">
                                    <?php foreach ($sancoes_cnep_confirmadas_pf as $sancao): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div><strong>Sancionado:</strong> <?= htmlspecialchars($sancao['nome_sancionado'] ?: 'Não informado') ?><br><small><strong>CPF:</strong> <?= htmlspecialchars($sancao['cpf_cnpj_sancionado']) ?> | <strong>Órgão:</strong> <?= htmlspecialchars($sancao['orgao_sancionador']) ?></small></div>
                                            <button type="button" class="btn btn-outline-warning btn-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#cnepDetailModal" data-sancao-json="<?= htmlspecialchars(json_encode($sancao), ENT_QUOTES, 'UTF-8') ?>">Ver Ficha</button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($pep_match_found_pf && !empty($peps_confirmados_pf)): ?>
                    <div class="accordion-item" style="border-color: #6f42c1;">
                        <h2 class="accordion-header">
                            <button class="accordion-button bg-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePEP" aria-expanded="false">
                                <i class="bi bi-person-fill-exclamation me-2" style="color: #6f42c1;"></i><strong>Alerta de Pessoa Exposta Politicamente (PEP)</strong>
                                <span class="badge ms-2" style="background-color: #6f42c1; color: white;"><?= count($peps_confirmados_pf) ?></span>
                            </button>
                        </h2>
                        <div id="collapsePEP" class="accordion-collapse collapse" data-bs-parent="#accordionAlertas">
                            <div class="accordion-body">
                                <p>Atenção: Um ou mais sócios/administradores foram identificados como PEP.</p>
                                <ul class="list-group">
                                    <?php foreach ($peps_confirmados_pf as $pep): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div><strong>Nome:</strong> <?= htmlspecialchars($pep['nome_pep']) ?><br><small><strong>CPF:</strong> <?= htmlspecialchars($pep['cpf']) ?> | <strong>Função:</strong> <?= htmlspecialchars($pep['descricao_funcao']) ?></small></div>
                                            <button type="button" class="btn btn-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#pepDetailModal" data-pep-json="<?= htmlspecialchars(json_encode($pep), ENT_QUOTES, 'UTF-8') ?>" style="border: 1px solid #6f42c1; color: #6f42c1;">Ver Ficha</button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Análise de Caso KYC: #<?= $caso['kyc_id_real']; ?></h4>
                        <?php
                        // Define a badge baseada no status
                        $status = $caso['status_caso'];
                        $badge_class = 'bg-secondary';
                        $badge_icon = 'bi-circle-fill';
                        
                        switch ($status) {
                            case 'Aprovado':
                                $badge_class = 'bg-success';
                                $badge_icon = 'bi-check-circle-fill';
                                break;
                            case 'Reprovado':
                                $badge_class = 'bg-danger';
                                $badge_icon = 'bi-x-circle-fill';
                                break;
                            case 'Em Análise':
                                $badge_class = 'bg-info';
                                $badge_icon = 'bi-clock-history';
                                break;
                            case 'Pendenciado':
                                $badge_class = 'bg-warning text-dark';
                                $badge_icon = 'bi-exclamation-circle-fill';
                                break;
                            case 'Em Preenchimento':
                                $badge_class = 'bg-secondary';
                                $badge_icon = 'bi-pencil-square';
                                break;
                            case 'Novo Registro':
                                $badge_class = 'bg-primary';
                                $badge_icon = 'bi-file-earmark-plus';
                                break;
                        }
                        ?>
                        <span class="badge <?= $badge_class ?>">
                            <i class="bi <?= $badge_icon ?> me-1"></i><?= htmlspecialchars($status) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="lead text-muted mb-4">Cliente: <?= htmlspecialchars($caso['razao_social']); ?></p>
                        
                        <?php require 'kyc_evaluate_accordion.php'; ?>
                    
                    </div>
                </div>

                <?php if ($cliente): ?>
                <!-- Ficha do Cliente -->
                <div class="card shadow-sm mb-4">
                    <div class="accordion" id="accordionFichaCliente">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFichaCliente" aria-expanded="false">
                                    <i class="bi bi-person-badge me-2"></i><strong>Ficha do Cliente</strong>
                                </button>
                            </h2>
                            <div id="collapseFichaCliente" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong>Nome Completo:</strong><br><?= htmlspecialchars($cliente['nome_completo']) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong>CPF:</strong><br><?= htmlspecialchars($cliente['cpf'] ?? 'Não informado') ?></p>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong>Email:</strong><br><?= htmlspecialchars($cliente['email']) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong>Status da Conta:</strong><br>
                                                <?php
                                                $status_badge = 'secondary';
                                                if ($cliente['status'] == 'ativo') $status_badge = 'success';
                                                elseif ($cliente['status'] == 'inativo') $status_badge = 'danger';
                                                elseif ($cliente['status'] == 'pendente') $status_badge = 'warning';
                                                ?>
                                                <span class="badge bg-<?= $status_badge ?>"><?= ucfirst(htmlspecialchars($cliente['status'])) ?></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong>Data de Cadastro:</strong><br><?= date('d/m/Y H:i', strtotime($cliente['created_at'])) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-2"><strong>Última Atualização:</strong><br><?= date('d/m/Y H:i', strtotime($cliente['updated_at'])) ?></p>
                                        </div>
                                    </div>
                                    <?php if (!empty($cliente['selfie_path'])): ?>
                                    <div class="text-center mt-3">
                                        <p class="mb-2"><strong>Selfie Enviada:</strong></p>
                                        <?php
                                        $path_servidor = $cliente['selfie_path'];
                                        $ext = strtolower(pathinfo($path_servidor, PATHINFO_EXTENSION));
                                        $cache_buster = file_exists($path_servidor) ? '?v=' . filemtime($path_servidor) : '';
                                        $path_web = '/' . ltrim($path_servidor, '/') . $cache_buster;
                                        
                                        if (file_exists($path_servidor)) {
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                echo '<img src="' . htmlspecialchars($path_web) . '" alt="Selfie" class="img-fluid rounded border" style="max-height: 200px;">';
                                            } elseif ($ext == 'pdf') {
                                                echo '<a href="' . htmlspecialchars($path_web) . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-pdf me-1"></i>Ver PDF</a>';
                                            }
                                        } else {
                                            echo '<p class="text-muted small">Arquivo não encontrado</p>';
                                        }
                                        ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info text-center mb-0">
                                        <i class="bi bi-info-circle me-1"></i>Nenhuma selfie foi enviada
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-5" id="rightColumn">
                
                <div class="card shadow-sm mb-4" id="documentosCard">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Documentos Anexados</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="pinDocumentosBtn" title="Fixar painel">
                            <i class="bi bi-pin-angle"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row" id="documentosList">
                            <?php
                            $docs_empresa = array_filter($all_documents, fn($doc) => empty($doc['socio_id']));
                            if (count($docs_empresa) > 0) {
                                foreach ($docs_empresa as $doc) {
                                    $doc_name = htmlspecialchars(ucfirst(str_replace(['doc_', '_'], ['', ' '], $doc['tipo_documento'])));
                                    $doc_path = htmlspecialchars($doc['path_arquivo'] ?? '#');
                                    $doc_ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                                    
                                    // Define ícone baseado no tipo
                                    $icon = 'bi-file-earmark';
                                    if (in_array($doc_ext, ['pdf'])) $icon = 'bi-file-earmark-pdf';
                                    elseif (in_array($doc_ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = 'bi-file-earmark-image';
                                    elseif (in_array($doc_ext, ['doc', 'docx'])) $icon = 'bi-file-earmark-word';
                                    elseif (in_array($doc_ext, ['xls', 'xlsx'])) $icon = 'bi-file-earmark-excel';
                                    
                                    echo "<div class='col-md-6 mb-2'>
                                            <button type='button' class='btn btn-outline-secondary btn-sm w-100 text-truncate doc-preview-btn' data-doc-path='{$doc_path}' data-doc-name='{$doc_name}' data-doc-ext='{$doc_ext}'>
                                                <i class='bi {$icon} me-1'></i>{$doc_name}
                                            </button>
                                          </div>";
                                }
                            } else {
                                echo '<div class="col-12"><p class="text-muted">Nenhum documento da empresa foi enviado.</p></div>';
                            }
                            ?>
                        </div>
                        
                        <!-- Visualizador de Documentos -->
                        <div id="documentViewer" class="mt-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0" id="viewerTitle">Documento</h6>
                                <div>
                                    <a href="#" target="_blank" id="viewerOpenNew" class="btn btn-sm btn-outline-primary me-1" title="Abrir em nova aba">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="viewerClose" title="Fechar">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="viewerContent" class="border rounded p-2 bg-light" style="min-height: 300px; max-height: 500px; overflow: auto;">
                                <!-- Conteúdo do preview será carregado aqui -->
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label for="av_obs_documentos" class="form-label small">Observações sobre os Documentos</label>
                            <textarea class="form-control observation-field" name="av_obs_documentos" id="av_obs_documentos" rows="3" data-label="Documentos"><?= htmlspecialchars($caso['av_obs_documentos'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header"><h5 class="mb-0">Painel de Análise do Compliance</h5></div>
                    <div class="card-body">
                        <!-- Accordion: Checklist de Validação -->
                        <div class="accordion mb-4" id="accordionChecklist">
                            <!-- Item 1: Checklist de Validação -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseChecklist" aria-expanded="true">
                                        <i class="bi bi-list-check me-2"></i><strong>Checklist de Validação</strong>
                                    </button>
                                </h2>
                                <div id="collapseChecklist" class="accordion-collapse collapse show" data-bs-parent="#accordionChecklist">
                                    <div class="accordion-body">
                                        <!-- Hidden checkboxes -->
                                        <input type="checkbox" name="av_check_dados_empresa_ok" id="av_check_dados_empresa_ok" value="1" class="d-none" <?= ($caso['av_check_dados_empresa_ok'] ?? 0) ? 'checked' : '' ?>>
                                        <input type="checkbox" name="av_check_perfil_negocio_ok" id="av_check_perfil_negocio_ok" value="1" class="d-none" <?= ($caso['av_check_perfil_negocio_ok'] ?? 0) ? 'checked' : '' ?>>
                                        <input type="checkbox" name="av_check_socios_ubos_ok" id="av_check_socios_ubos_ok" value="1" class="d-none" <?= ($caso['av_check_socios_ubos_ok'] ?? 0) ? 'checked' : '' ?>>
                                        <input type="checkbox" name="av_check_documentos_ok" id="av_check_documentos_ok" value="1" class="d-none" <?= ($caso['av_check_documentos_ok'] ?? 0) ? 'checked' : '' ?>>
                                        <input type="hidden" name="av_check_socios_ubos_origin" id="av_check_socios_ubos_origin" value="<?= htmlspecialchars($caso['av_check_socios_ubos_origin'] ?? 'analyst') ?>">
                                        
                                        <!-- Container para os ícones dinâmicos -->
                                        <div id="checklist-icons-container"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Item 2: Classificação de Risco -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRisco" aria-expanded="true">
                                        <i class="bi bi-speedometer2 me-2"></i><strong>Classificação de Risco</strong>
                                    </button>
                                </h2>
                                <div id="collapseRisco" class="accordion-collapse collapse show" data-bs-parent="#accordionChecklist">
                                    <div class="accordion-body">
                                        <?= render_risk_select('av_risco_atividade', 'Risco da Atividade', $caso['av_risco_atividade'] ?? ''); ?>
                                        <?= render_risk_select('av_risco_geografico', 'Risco Geográfico', $caso['av_risco_geografico'] ?? ''); ?>
                                        <?= render_risk_select('av_risco_societario', 'Risco da Estrutura Societária', $caso['av_risco_societario'] ?? ''); ?>
                                        <?= render_risk_select('av_risco_midia_pep', 'Risco de Mídia Negativa / PEP', $caso['av_risco_midia_pep'] ?? ''); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3"><label for="anotacoes" class="form-label fw-bold">Anotações Internas</label><textarea name="av_anotacoes_internas" class="form-control" rows="5"><?= htmlspecialchars($caso['av_anotacoes_internas'] ?? ''); ?></textarea></div>
                        <div class="mb-3"><label for="justificativa" class="form-label fw-bold">Justificativa do Risco Final</label><textarea name="av_justificativa_risco" class="form-control" rows="3" required><?= htmlspecialchars($caso['av_justificativa_risco'] ?? ''); ?></textarea></div>
                        <div class="mb-4"><label for="risco_final" class="form-label fw-bold">Nível de Risco Final Consolidado</label><select name="av_risco_final" class="form-select" required><option value="" disabled selected>Selecione...</option><option value="Baixo" <?= (($caso['av_risco_final'] ?? '') == 'Baixo') ? 'selected' : ''; ?>>Baixo</option><option value="Médio" <?= (($caso['av_risco_final'] ?? '') == 'Médio') ? 'selected' : ''; ?>>Médio</option><option value="Alto" <?= (($caso['av_risco_final'] ?? '') == 'Alto') ? 'selected' : ''; ?>>Alto</option></select></div>
                        <fieldset class="mb-3">
                            <legend class="h6 fw-bold border-bottom pb-2 mb-3">Decisão Final</legend>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="status_caso" value="Em Análise" id="decisao_analise" <?= (($caso['status_caso'] ?? '') == 'Em Análise') ? 'checked' : ''; ?> required><label class="btn btn-outline-info" for="decisao_analise">Em Análise</label>
                                <input type="radio" class="btn-check" name="status_caso" value="Aprovado" id="decisao_aprovar" <?= (($caso['status_caso'] ?? '') == 'Aprovado') ? 'checked' : ''; ?> required><label class="btn btn-outline-success" for="decisao_aprovar">Aprovar</label>
                                <input type="radio" class="btn-check" name="status_caso" value="Reprovado" id="decisao_reprovar" <?= (($caso['status_caso'] ?? '') == 'Reprovado') ? 'checked' : ''; ?> required><label class="btn btn-outline-danger" for="decisao_reprovar">Reprovar</label>
                                <input type="radio" class="btn-check" name="status_caso" value="Pendenciado" id="decisao_pendenciar" <?= (($caso['status_caso'] ?? '') == 'Pendenciado') ? 'checked' : ''; ?> required><label class="btn btn-outline-warning" for="decisao_pendenciar">Pendenciar</label>
                            </div>
                        </fieldset>
                        <div id="pendencia_container" class="mb-3" style="display: none;"><label for="info_pendencia" class="form-label">Informações de Pendência</label><textarea name="av_info_pendencia" id="info_pendencia" class="form-control" rows="3"><?= htmlspecialchars($caso['av_info_pendencia'] ?? ''); ?></textarea></div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg mt-3">Salvar Análise</button>
                    </div>
                </div>
                <div class="card shadow-sm mt-4">
                    <div class="card-header"><h5 class="mb-0">Log de Atividades</h5></div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <ul class="list-group list-group-flush">
                            <?php 
                            $logs = $log_atividades->fetchAll(PDO::FETCH_ASSOC);
                            for ($i = 0; $i < count($logs); $i++):
                                $log = $logs[$i];
                                $previous_log_snapshot = isset($logs[$i + 1]) ? $logs[$i + 1]['dados_avaliacao_snapshot'] : 'null';
                            ?>
                                <li class="list-group-item">
                                    <p class="mb-1 small text-muted">
                                        <?= date('d/m/Y H:i', strtotime($log['timestamp'])) ?> por <strong><?= htmlspecialchars($log['nome_analista'] ?? 'Sistema') ?></strong>
                                    </p>
                                    <p class="mb-1"><?= htmlspecialchars($log['acao']) ?></p>
                                    <?php if (!empty($log['dados_avaliacao_snapshot'])): ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logDetailModal" 
                                                data-current-snapshot="<?= htmlspecialchars($log['dados_avaliacao_snapshot'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-previous-snapshot="<?= htmlspecialchars($previous_log_snapshot, ENT_QUOTES, 'UTF-8') ?>">
                                            Ver Detalhes
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logDetailModalLabel">Snapshot da Análise</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Estes eram os valores de todos os campos no momento em que a análise foi salva.</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="log-details-table">
                </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="ceisDetailModal" tabindex="-1" aria-labelledby="ceisDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ceisDetailModalLabel">Ficha de Sanção (CEIS)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Detalhes da sanção encontrada na base de dados CEIS.</p>
        <div id="ceis-modal-content" class="ficha-modal-content"></div> 
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="cnepDetailModal" tabindex="-1" aria-labelledby="cnepDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cnepDetailModalLabel">Ficha de Sanção (CNEP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Detalhes da sanção encontrada na base de dados CNEP.</p>
        <div id="cnep-modal-content" class="ficha-modal-content"></div> 
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pepDetailModal" tabindex="-1" aria-labelledby="pepDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pepDetailModalLabel">Ficha de Pessoa Exposta Politicamente (PEP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Detalhes do registro de PEP encontrado.</p>
        <div id="pep-modal-content" class="ficha-modal-content"></div> 
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Adicionar as variáveis do PHP ---
    const ceisMatchFoundPJ = <?= json_encode($ceis_match_found_pj ?? false); ?>;
    const ceisMatchFoundPF = <?= json_encode($ceis_match_found_pf ?? false); ?>;
    const pepMatchFoundPF = <?= json_encode($pep_match_found_pf ?? false); ?>;
    const cnepMatchFoundPJ = <?= json_encode($cnep_match_found_pj ?? false); ?>;
    const cnepMatchFoundPF = <?= json_encode($cnep_match_found_pf ?? false); ?>;

    const checklistContainer = document.getElementById('checklist-icons-container');
    const checklistConfig = [
        { id: 'av_check_dados_empresa_ok', selector: '.dados-empresa-sub-check', label: 'Dados da Empresa' },
        { id: 'av_check_perfil_negocio_ok', selector: '.perfil-negocio-sub-check', label: 'Perfil de Negócio' },
        { id: 'av_check_socios_ubos_ok', selector: '.socio-verificado-checkbox', label: 'Sócios e Administradores' },
        { id: 'av_check_documentos_ok', selector: '.documentos-sub-check', label: 'Documentos da Empresa' }
    ];

    function htmlspecialchars(str) {
         if (typeof str !== 'string') return str;
         return str.replace(/[&<>"']/g, function(m) {
           return {
             '&': '&amp;',
             '<': '&lt;',
             '>': '&gt;',
             '"': '&quot;',
             "'": '&#039;'
           }[m];
         });
    }

    const fDate = (value) => {
        if (!value || value === '0000-00-00') return '<span class="text-muted">N/A</span>';
        try {
            const date = new Date(value + 'T00:00:00'); 
            if (isNaN(date.getTime())) return htmlspecialchars(value); 
            return date.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
        } catch (e) {
            return htmlspecialchars(value);
        }
    };
    const f = (value) => (value ? htmlspecialchars(value) : '<span class="text-muted">N/A</span>');

    function updateAndRenderChecklist() {
        // Padrão de ícones e cores consistente:
        // CEIS (grave) = exclamation-triangle-fill vermelho
        // CNEP (atenção) = exclamation-diamond-fill amarelo  
        // PEP (info) = person-fill-exclamation purple (#6f42c1)
        // Limpo/OK = check-circle-fill verde
        // Em andamento = clock-fill amarelo
        // Não iniciado = x-circle-fill vermelho
        
        const ceisIconPJ = ceisMatchFoundPJ ? 'bi bi-exclamation-triangle-fill text-danger' : 'bi bi-check-circle-fill text-success';
        const ceisIconPF = ceisMatchFoundPF ? 'bi bi-exclamation-triangle-fill text-danger' : 'bi bi-check-circle-fill text-success';
        const pepIconPF = pepMatchFoundPF ? 'bi bi-person-fill-exclamation' : 'bi bi-check-circle-fill text-success';
        const pepColor = pepMatchFoundPF ? 'color: #6f42c1;' : '';
        const cnepIconPJ = cnepMatchFoundPJ ? 'bi bi-exclamation-diamond-fill text-warning' : 'bi bi-check-circle-fill text-success';
        const cnepIconPF = cnepMatchFoundPF ? 'bi bi-exclamation-diamond-fill text-warning' : 'bi bi-check-circle-fill text-success';

        let html = `<h6 class="mb-3" style="font-size: 0.9rem; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="bi bi-shield-exclamation me-2 text-primary"></i>Sanções e Listas
                    </h6>
                    <div class="row g-2 mb-4">
                        <div class="col-lg-6">
                            <div class="checklist-item">
                                <div class="checklist-item-icon"><i class="${ceisIconPJ}"></i></div>
                                <span class="checklist-item-label" data-bs-toggle="tooltip" title="Cadastro de Empresas Inidôneas e Suspensas">Consulta CEIS <strong>PJ</strong></span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="checklist-item">
                                <div class="checklist-item-icon"><i class="${ceisIconPF}"></i></div>
                                <span class="checklist-item-label" data-bs-toggle="tooltip" title="Cadastro de Empresas Inidôneas e Suspensas">Consulta CEIS <strong>PF</strong></span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="checklist-item">
                                <div class="checklist-item-icon"><i class="${cnepIconPJ}"></i></div>
                                <span class="checklist-item-label" data-bs-toggle="tooltip" title="Cadastro Nacional de Empresas Punidas">Consulta CNEP <strong>PJ</strong></span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="checklist-item">
                                <div class="checklist-item-icon"><i class="${cnepIconPF}"></i></div>
                                <span class="checklist-item-label" data-bs-toggle="tooltip" title="Cadastro Nacional de Empresas Punidas">Consulta CNEP <strong>PF</strong></span>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="checklist-item">
                                <div class="checklist-item-icon"><i class="${pepIconPF}" style="${pepColor}"></i></div>
                                <span class="checklist-item-label" data-bs-toggle="tooltip" title="Pessoa Exposta Politicamente">Consulta Lista <strong>PEP</strong></span>
                            </div>
                        </div>
                    </div>`;

        html += `<h6 class="mb-3 mt-4" style="font-size: 0.9rem; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: 0.5px;">
                     <i class="bi bi-clipboard-check me-2 text-success"></i>Validações internas
                 </h6>
                 <div class="row">`;
        
        let col1Html = '<div class="col-lg-6">';
        let col2Html = '<div class="col-lg-6">';

        const itemsInternos = {};
        checklistConfig.forEach(item => {
            const mainCheck = document.getElementById(item.id);
            const subChecks = document.querySelectorAll(item.selector);
            
            const total = subChecks.length;
            const checkedCount = Array.from(subChecks).filter(cb => cb.checked).length;

            let iconClass = 'bi bi-x-circle-fill text-danger';
            let allChecked = false;

            if (total > 0) {
                if (checkedCount === total) {
                    iconClass = 'bi bi-check-circle-fill text-success';
                    allChecked = true;
                } else if (checkedCount > 0) {
                    iconClass = 'bi bi-clock-fill text-warning';
                }
            } else {
                 if (mainCheck && mainCheck.checked) {
                     iconClass = 'bi bi-check-circle-fill text-success';
                     allChecked = true;
                 }
            }
            
            if (mainCheck) {
                if (total > 0) {
                    mainCheck.checked = allChecked;
                }
            }

            itemsInternos[item.label] = `<div class="checklist-item">
                                            <div class="checklist-item-icon"><i class="${iconClass}"></i></div>
                                            <span class="checklist-item-label">${item.label}</span>
                                            ${total > 0 ? `<span class="checklist-item-count">${checkedCount}/${total}</span>` : ''}
                                         </div>`;
        });

        if (itemsInternos['Dados da Empresa']) col1Html += itemsInternos['Dados da Empresa'];
        if (itemsInternos['Perfil de Negócio']) col1Html += itemsInternos['Perfil de Negócio'];
        if (itemsInternos['Sócios e Administradores']) col2Html += itemsInternos['Sócios e Administradores'];
        if (itemsInternos['Documentos da Empresa']) col2Html += itemsInternos['Documentos da Empresa']; 

        col1Html += '</div>';
        col2Html += '</div>';
        html += col1Html + col2Html + '</div>'; 
        
        if (checklistContainer) {
            checklistContainer.innerHTML = html;
        }
    }

    document.body.addEventListener('change', function(event) {
        if (event.target.matches('.dados-empresa-sub-check, .perfil-negocio-sub-check, .socio-verificado-checkbox, .documentos-sub-check, #av_check_dados_empresa_ok, #av_check_perfil_negocio_ok, #av_check_documentos_ok')) {
            updateAndRenderChecklist();
        }
    });

    const uboCheckOrigin = document.getElementById('av_check_socios_ubos_origin');
    document.body.addEventListener('click', function(event) {
        if (event.target.matches('.socio-verificado-checkbox')) {
            if (uboCheckOrigin) uboCheckOrigin.value = 'analyst'; 
            updateAndRenderChecklist();
        }
    });

    // Renderiza o estado inicial do checklist
    updateAndRenderChecklist();

    // --- LÓGICA DE MUDANÇA DE COR DOS SELECTS DE RISCO ---
    const riskSelects = document.querySelectorAll('select[data-risk-select="true"]');
    riskSelects.forEach(select => {
        select.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const colorClass = selectedOption.getAttribute('data-class');
            
            // Remove todas as classes de cor
            this.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'text-white', 'text-dark');
            
            // Adiciona a nova classe de cor se houver
            if (colorClass) {
                const classes = colorClass.split(' ');
                classes.forEach(cls => this.classList.add(cls));
            }
        });
        
        // Aplica a cor inicial se já houver valor selecionado
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const colorClass = selectedOption.getAttribute('data-class');
            if (colorClass) {
                const classes = colorClass.split(' ');
                classes.forEach(cls => select.classList.add(cls));
            }
        }
    });

    // --- LÓGICA DO MODAL DE DETALHES DO LOG ---
    const logDetailModal = document.getElementById('logDetailModal');
    if (logDetailModal) {
        logDetailModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const currentSnapshotJson = button.getAttribute('data-current-snapshot');
            const previousSnapshotJson = button.getAttribute('data-previous-snapshot');

            const currentSnapshot = JSON.parse(currentSnapshotJson);
            const previousSnapshot = (previousSnapshotJson && previousSnapshotJson !== 'null') ? JSON.parse(previousSnapshotJson) : null;
            
            const table = logDetailModal.querySelector('#log-details-table');
            table.innerHTML = '<thead><tr><th style="width: 30%;">Campo</th><th style="width: 70%;">Valor</th></tr></thead><tbody></tbody>';
            const tbody = table.querySelector('tbody');

            const keyToNameMap = {
                'status_caso': 'Status do Caso',
                'av_check_ceis_ok': 'Check: Consulta CEIS (PJ)',
                'av_check_ceis_pf_ok': 'Check: Consulta CEIS (PF)',
                'av_check_cnep_ok': 'Check: Consulta CNEP (PJ)',
                'av_check_cnep_pf_ok': 'Check: Consulta CNEP (PF)',
                'av_check_dados_empresa_ok': 'Check: Dados da Empresa',
                'av_check_perfil_negocio_ok': 'Check: Perfil de Negócio',
                'av_check_socios_ubos_ok': 'Check: Sócios (UBOs)',
                'av_check_socios_ubos_origin': 'Origem Check Sócios',
                'av_check_documentos_ok': 'Check: Documentos',
                'av_anotacoes_internas': 'Anotações Internas',
                'av_risco_atividade': 'Risco: Atividade',
                'av_risco_geografico': 'Risco: Geográfico',
                'av_risco_societario': 'Risco: Estrutura Societária',
                'av_risco_midia_pep': 'Risco: Mídia / PEP',
                'av_risco_final': 'Risco Final',
                'av_justificativa_risco': 'Justificativa do Risco',
                'av_info_pendencia': 'Informações de Pendência',
                'av_obs_dados_empresa': 'Obs: Dados da Empresa',
                'av_obs_perfil_negocio': 'Obs: Perfil de Negócio',
                'av_obs_documentos': 'Obs: Documentos',
                'av_check_perfil_atividade': 'Sub-Check: Atividade',
                'av_check_perfil_motivo': 'Sub-Check: Motivo',
                'av_check_perfil_fluxo': 'Sub-Check: Fluxo',
                'av_check_perfil_volume': 'Sub-Check: Volume',
                'av_check_perfil_origem': 'Sub-Check: Origem',
                'av_check_doc_contrato_social': 'Sub-Check: Contrato Social',
                'av_check_doc_ultima_alteracao': 'Sub-Check: Última Alteração',
                'av_check_doc_cartao_cnpj': 'Sub-Check: Cartão CNPJ',
                'av_check_doc_balanco': 'Sub-Check: Balanço',
                'av_check_doc_balancete': 'Sub-Check: Balancete',
                'av_check_doc_dirpj': 'Sub-Check: DIRPJ'
            };

            for (const key in keyToNameMap) {
                if (currentSnapshot.hasOwnProperty(key)) {
                    let currentValue = currentSnapshot[key];
                    let previousValue = previousSnapshot ? (previousSnapshot[key] ?? null) : null;
                    
                    let displayValue = currentValue;
                    if (displayValue === 1 || displayValue === '1') displayValue = 'Sim';
                    if (displayValue === 0 || displayValue === '0') displayValue = 'Não';
                    if (displayValue === null || displayValue === '') displayValue = 'N/A';

                    let rowClass = '';
                    if (previousSnapshot) {
                        if (String(currentValue) !== String(previousValue)) {
                            rowClass = 'table-warning';
                        }
                    } else {
                        if (currentValue !== null && currentValue !== '' && currentValue !== 0) {
                            rowClass = 'table-info';
                        }
                    }

                    const displayName = keyToNameMap[key];
                    const row = `<tr class="${rowClass}"><td><strong>${displayName}</strong></td><td>${htmlspecialchars(displayValue)}</td></tr>`;
                    tbody.innerHTML += row;
                }
            }

            if (currentSnapshot.analise_socios) {
                for (const socio_id in currentSnapshot.analise_socios) {
                    const socio_analise = currentSnapshot.analise_socios[socio_id];
                    const prev_socio_analise = (previousSnapshot && previousSnapshot.analise_socios) ? (previousSnapshot.analise_socios[socio_id] || null) : null;

                    let obs_rowClass = '';
                    if (prev_socio_analise && String(socio_analise.observacoes) !== String(prev_socio_analise.observacoes)) {
                        obs_rowClass = 'table-warning';
                    } else if (!prev_socio_analise && socio_analise.observacoes) {
                        obs_rowClass = 'table-info';
                    }
                    const obs_row = `<tr class="${obs_rowClass}"><td><strong>Análise Sócio ${socio_id} - Observações</strong></td><td>${htmlspecialchars(socio_analise.observacoes || 'N/A')}</td></tr>`;
                    tbody.innerHTML += obs_row;

                    let ver_rowClass = '';
                    if (prev_socio_analise && String(socio_analise.verificado) !== String(prev_socio_analise.verificado)) {
                        ver_rowClass = 'table-warning';
                    } else if (!prev_socio_analise && (socio_analise.verificado == 1)) {
                        ver_rowClass = 'table-info';
                    }
                    const ver_row = `<tr class="${ver_rowClass}"><td><strong>Análise Sócio ${socio_id} - Verificado</strong></td><td>${(socio_analise.verificado == 1) ? 'Sim' : 'Não'}</td></tr>`;
                    tbody.innerHTML += ver_row;
                }
            }
        });
    }

    // --- LÓGICA DO MODAL CEIS ---
    const ceisDetailModal = document.getElementById('ceisDetailModal');
    if (ceisDetailModal) {
        ceisDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const sancaoJson = button.getAttribute('data-sancao-json');
            const sancao = JSON.parse(sancaoJson);
            const container = ceisDetailModal.querySelector('#ceis-modal-content');

            const fTipoPessoa = (value) => {
                if (value === 'F') return 'Física';
                if (value === 'J') return 'Jurídica';
                return f(value);
            };

            let html = '<dl class="row">';
            html += `<dt class="col-sm-4">Nome Sancionado</dt><dd class="col-sm-8">${f(sancao.nome_sancionado)}</dd>`;
            html += `<dt class="col-sm-4">Razão Social</dt><dd class="col-sm-8">${f(sancao.razao_social)}</dd>`;
            html += `<dt class="col-sm-4">Nome Fantasia</dt><dd class="col-sm-8">${f(sancao.nome_fantasia)}</dd>`;
            html += `<dt class="col-sm-4">CPF/CNPJ Sancionado</dt><dd class="col-sm-8">${f(sancao.cpf_cnpj_sancionado)}</dd>`;
            html += `<dt class="col-sm-4">Tipo Pessoa</dt><dd class="col-sm-8">${fTipoPessoa(sancao.tipo_pesso)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Categoria da Sanção</dt><dd class="col-sm-8">${f(sancao.categoria_sancao)}</dd>`;
            html += `<dt class="col-sm-4">Data Início Sanção</dt><dd class="col-sm-8">${fDate(sancao.data_inicio_sancao)}</dd>`;
            html += `<dt class="col-sm-4">Data Fim Sanção</dt><dd class="col-sm-8">${fDate(sancao.data_final_sancao)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Órgão Sancionador</dt><dd class="col-sm-8">${f(sancao.orgao_sancionador)}</dd>`;
            html += `<dt class="col-sm-4">UF do Órgão</dt><dd class="col-sm-8">${f(sancao.uf_orgao_sancionador)}</dd>`;
            html += `<dt class="col-sm-4">Esfera do Órgão</dt><dd class="col-sm-8">${f(sancao.esfera_orgao_sancionador)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Nº do Processo</dt><dd class="col-sm-8">${f(sancao.numero_processo)}</dd>`;
            html += `<dt class="col-sm-4">Fundamentação Legal</dt><dd class="col-sm-8"><small>${f(sancao.fundamentacao_legal)}</small></dd>`;
            html += `<dt class="col-sm-4">Observações</dt><dd class="col-sm-8"><small>${f(sancao.observacoes)}</small></dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Data Publicação</dt><dd class="col-sm-8">${fDate(sancao.data_publicacao)}</dd>`;
            html += `<dt class="col-sm-4">Publicação</dt><dd class="col-sm-8">${f(sancao.publicacao)}</dd>`;
            html += `<dt class="col-sm-4">Detalhamento</dt><dd class="col-sm-8">${f(sancao.detalhamento_publicaca)}</dd>`;
            html += `<dt class="col-sm-4">Data Trânsito Julgado</dt><dd class="col-sm-8">${fDate(sancao.data_transito_julgad)}</dd>`;
            html += '</dl>';

            container.innerHTML = html;
        });
    }

    // --- LÓGICA DO MODAL CNEP ---
    const cnepDetailModal = document.getElementById('cnepDetailModal');
    if (cnepDetailModal) {
        cnepDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const sancaoJson = button.getAttribute('data-sancao-json');
            const sancao = JSON.parse(sancaoJson);
            const container = cnepDetailModal.querySelector('#cnep-modal-content');

            const fTipoPessoa = (value) => {
                if (value === 'F') return 'Física';
                if (value === 'J') return 'Jurídica';
                return f(value);
            };
            
            let html = '<dl class="row">';
            html += `<dt class="col-sm-4">Nome Sancionado</dt><dd class="col-sm-8">${f(sancao.nome_sancionado)}</dd>`;
            html += `<dt class="col-sm-4">Razão Social</dt><dd class="col-sm-8">${f(sancao.razao_social)}</dd>`;
            html += `<dt class="col-sm-4">Nome Fantasia</dt><dd class="col-sm-8">${f(sancao.nome_fantasia)}</dd>`;
            html += `<dt class="col-sm-4">CPF/CNPJ Sancionado</dt><dd class="col-sm-8">${f(sancao.cpf_cnpj_sancionado)}</dd>`;
            html += `<dt class="col-sm-4">Tipo Pessoa</dt><dd class="col-sm-8">${fTipoPessoa(sancao.tipo_pesso)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Categoria da Sanção</dt><dd class="col-sm-8">${f(sancao.categoria_sancao)}</dd>`;
            html += `<dt class="col-sm-4">Data Início Sanção</dt><dd class="col-sm-8">${fDate(sancao.data_inicio_sancao)}</dd>`;
            html += `<dt class="col-sm-4">Data Fim Sanção</dt><dd class="col-sm-8">${fDate(sancao.data_final_sancao)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Órgão Sancionador</dt><dd class="col-sm-8">${f(sancao.orgao_sancionador)}</dd>`;
            html += `<dt class="col-sm-4">UF do Órgão</dt><dd class="col-sm-8">${f(sancao.uf_orgao_sancionador)}</dd>`;
            html += `<dt class="col-sm-4">Esfera do Órgão</dt><dd class="col-sm-8">${f(sancao.esfera_orgao_sancionador)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Nº do Processo</dt><dd class="col-sm-8">${f(sancao.numero_processo)}</dd>`;
            html += `<dt class="col-sm-4">Fundamentação Legal</dt><dd class="col-sm-8"><small>${f(sancao.fundamentacao_legal)}</small></dd>`;
            html += `<dt class="col-sm-4">Observações</dt><dd class="col-sm-8"><small>${f(sancao.observacoes)}</small></dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Data Publicação</dt><dd class="col-sm-8">${fDate(sancao.data_publicacao)}</dd>`;
            html += `<dt class="col-sm-4">Publicação</dt><dd class="col-sm-8">${f(sancao.publicacao)}</dd>`;
            html += `<dt class="col-sm-4">Detalhamento</dt><dd class="col-sm-8">${f(sancao.detalhamento_publicaca)}</dd>`;
            html += `<dt class="col-sm-4">Data Trânsito Julgado</dt><dd class="col-sm-8">${fDate(sancao.data_transito_julgad)}</dd>`;
            html += '</dl>';

            container.innerHTML = html;
        });
    }

    // --- LÓGICA DO MODAL PEP ---
    const pepDetailModal = document.getElementById('pepDetailModal');
    if (pepDetailModal) {
        pepDetailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const pepJson = button.getAttribute('data-pep-json');
            const pep = JSON.parse(pepJson);
            const container = pepDetailModal.querySelector('#pep-modal-content');

            let html = '<dl class="row">';
            html += `<dt class="col-sm-4">Nome PEP</dt><dd class="col-sm-8">${f(pep.nome_pep)}</dd>`;
            html += `<dt class="col-sm-4">CPF</dt><dd class="col-sm-8">${f(pep.cpf)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Sigla da Função</dt><dd class="col-sm-8">${f(pep.sigla_funcao)}</dd>`;
            html += `<dt class="col-sm-4">Descrição da Função</dt><dd class="col-sm-8">${f(pep.descricao_funcao)}</dd>`;
            html += `<dt class="col-sm-4">Nível da Função</dt><dd class="col-sm-8">${f(pep.nivel_funcao)}</dd>`;
            html += `<dt class="col-sm-4">Nome do Órgão</dt><dd class="col-sm-8">${f(pep.nome_orgao)}</dd>`;
            html += '<dd class="col-12"><hr class="my-2"></dd>';
            html += `<dt class="col-sm-4">Data Início Exercício</dt><dd class="col-sm-8">${fDate(pep.data_inicio_exercicio)}</dd>`;
            html += `<dt class="col-sm-4">Data Fim Exercício</dt><dd class="col-sm-8">${fDate(pep.data_fim_exercicio)}</dd>`;
            html += `<dt class="col-sm-4">Data Fim Carência</dt><dd class="col-sm-8">${fDate(pep.data_fim_carencia)}</dd>`;
            html += '</dl>';

            container.innerHTML = html;
        });
    }


    // --- LÓGICA DE SINCRONIZAÇÃO DE OBSERVAÇÕES ---
    const mainNotesTextarea = document.querySelector('[name="av_anotacoes_internas"]');
    const allObservationFields = document.querySelectorAll('.observation-field, .socio-observacoes-textarea');

    const AUTO_NOTES_HEADER = "--- OBSERVAÇÕES AUTOMÁTICAS DAS SEÇÕES ---\n";
    const AUTO_NOTES_FOOTER = "\n--- FIM DAS OBSERVAÇÕES AUTOMÁTICAS ---";
    const MANUAL_NOTES_HEADER = "\n\n--- ANOTAÇÕES MANUAIS DO ANALISTA ---";

    function syncAllObservations() {
        if (!mainNotesTextarea) return; 
        let autoNotesContent = "";
        
        allObservationFields.forEach(textarea => {
            if (textarea.value.trim() !== "") {
                const label = textarea.dataset.label || `Sócio "${textarea.dataset.socioNome || 'ID ' + textarea.id}"`;
                autoNotesContent += `${label}: ${textarea.value.trim()}\n`;
            }
        });

        let currentMainNotes = mainNotesTextarea.value;
        let manualNotes = "";

        const manualNotesIndex = currentMainNotes.indexOf(MANUAL_NOTES_HEADER);
        if (manualNotesIndex !== -1) {
            manualNotes = currentMainNotes.substring(manualNotesIndex + MANUAL_NOTES_HEADER.length).trim();
        } else {
            const autoNotesStartIndex = currentMainNotes.indexOf(AUTO_NOTES_HEADER);
            if (autoNotesStartIndex !== -1) {
                const autoNotesEndIndex = currentMainNotes.indexOf(AUTO_NOTES_FOOTER);
                if (autoNotesEndIndex !== -1) {
                    manualNotes = (currentMainNotes.substring(0, autoNotesStartIndex) + currentMainNotes.substring(autoNotesEndIndex + AUTO_NOTES_FOOTER.length)).trim();
                } else {
                     manualNotes = currentMainNotes.substring(0, autoNotesStartIndex).trim();
                }
            } else {
                manualNotes = currentMainNotes;
            }
        }

        let newMainNotes = "";
        if (autoNotesContent) {
            newMainNotes = AUTO_NOTES_HEADER + autoNotesContent.trim() + AUTO_NOTES_FOOTER;
        }

        if (manualNotes) {
            newMainNotes += (newMainNotes ? MANUAL_NOTES_HEADER + "\n" : "") + manualNotes;
        } else if (newMainNotes) {
            newMainNotes += MANUAL_NOTES_HEADER;
        }
        
        mainNotesTextarea.value = newMainNotes;
    }

    allObservationFields.forEach(textarea => {
        textarea.addEventListener('input', syncAllObservations);
    });

    syncAllObservations(); // Sincroniza ao carregar a página

    // --- LÓGICA DE PENDÊNCIA ---
    const pendenciaContainer = document.getElementById('pendencia_container');
    const infoPendenciaTextarea = document.getElementById('info_pendencia');
    const radioDecisao = document.querySelectorAll('input[name="status_caso"]');

    function togglePendencia(status) {
        if (!pendenciaContainer || !infoPendenciaTextarea) return; 

        if (status === 'Pendenciado') {
            pendenciaContainer.style.display = 'block';
            infoPendenciaTextarea.required = true;
        } else {
            pendenciaContainer.style.display = 'none';
            infoPendenciaTextarea.required = false;
        }
    }

    radioDecisao.forEach(radio => {
        radio.addEventListener('change', (e) => {
            togglePendencia(e.target.value);
        });
        if (radio.checked) {
            togglePendencia(radio.value);
        }
    });

    // --- INICIALIZADOR DE TOOLTIPS ---
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // --- SISTEMA DE PIN ---
    const pinBtn = document.getElementById('pinDocumentosBtn');
    const rightColumn = document.getElementById('rightColumn');
    
    if (pinBtn && rightColumn) {
        // Recupera estado salvo do localStorage
        const isPinned = localStorage.getItem('rightColumnPinned') === 'true';
        if (isPinned) {
            rightColumn.classList.add('pinned');
            pinBtn.setAttribute('title', 'Desafixar painel');
        }
        
        pinBtn.addEventListener('click', function(e) {
            e.preventDefault();
            rightColumn.classList.toggle('pinned');
            const pinned = rightColumn.classList.contains('pinned');
            
            console.log('Pin clicked! Pinned:', pinned);
            console.log('Classes:', rightColumn.className);
            
            // Salva estado no localStorage
            localStorage.setItem('rightColumnPinned', pinned);
            
            // Atualiza tooltip
            pinBtn.setAttribute('title', pinned ? 'Desafixar painel' : 'Fixar painel');
            
            // Reinicializa tooltip do Bootstrap
            const tooltip = bootstrap.Tooltip.getInstance(pinBtn);
            if (tooltip) {
                tooltip.dispose();
            }
            new bootstrap.Tooltip(pinBtn);
        });
    }

    // --- VISUALIZADOR DE DOCUMENTOS ---
    const documentViewer = document.getElementById('documentViewer');
    const viewerContent = document.getElementById('viewerContent');
    const viewerTitle = document.getElementById('viewerTitle');
    const viewerOpenNew = document.getElementById('viewerOpenNew');
    const viewerClose = document.getElementById('viewerClose');
    const docPreviewBtns = document.querySelectorAll('.doc-preview-btn');
    
    docPreviewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const docPath = this.getAttribute('data-doc-path');
            const docName = this.getAttribute('data-doc-name');
            const docExt = this.getAttribute('data-doc-ext');
            
            // Atualiza título e link
            viewerTitle.textContent = docName;
            viewerOpenNew.href = docPath;
            
            // Limpa conteúdo anterior
            viewerContent.innerHTML = '';
            
            // Mostra o visualizador
            documentViewer.style.display = 'block';
            
            // Carrega preview baseado na extensão
            if (['jpg', 'jpeg', 'png', 'gif'].includes(docExt)) {
                // Preview de imagem
                viewerContent.innerHTML = `
                    <div class="text-center">
                        <img src="${docPath}" alt="${docName}" class="img-fluid rounded" style="max-height: 450px;">
                    </div>
                `;
            } else if (docExt === 'pdf') {
                // Preview de PDF com múltiplas opções
                const encodedPath = encodeURIComponent(window.location.origin + '/' + docPath.replace(/^\//, ''));
                viewerContent.innerHTML = `
                    <div class="d-flex justify-content-center mb-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="loadPdfEmbed('${docPath}', '${docName}')">
                                <i class="bi bi-window me-1"></i>Embed
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="loadPdfObject('${docPath}', '${docName}')">
                                <i class="bi bi-file-pdf me-1"></i>Object
                            </button>
                            <button type="button" class="btn btn-outline-secondary active" onclick="loadPdfDocs('${docPath}')">
                                <i class="bi bi-google me-1"></i>Google Docs
                            </button>
                        </div>
                    </div>
                    <div id="pdfViewerArea" style="width: 100%; height: 450px; border: none;">
                        <iframe src="https://docs.google.com/viewer?url=${encodedPath}&embedded=true" style="width: 100%; height: 450px; border: none;"></iframe>
                    </div>
                `;
            } else {
                // Outros formatos - apenas link para download
                viewerContent.innerHTML = `
                    <div class="text-center p-5">
                        <i class="bi bi-file-earmark" style="font-size: 4rem; color: #6c757d;"></i>
                        <p class="mt-3 mb-2"><strong>${docName}</strong></p>
                        <p class="text-muted small">Preview não disponível para este tipo de arquivo</p>
                        <a href="${docPath}" target="_blank" class="btn btn-primary mt-2">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                `;
            }
            
            // Scroll suave até o visualizador
            documentViewer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Destaca o botão ativo
            docPreviewBtns.forEach(b => b.classList.remove('active', 'btn-primary'));
            docPreviewBtns.forEach(b => b.classList.add('btn-outline-secondary'));
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary', 'active');
        });
    });
    
    // Fechar visualizador
    if (viewerClose) {
        viewerClose.addEventListener('click', function() {
            documentViewer.style.display = 'none';
            // Remove destaque dos botões
            docPreviewBtns.forEach(b => b.classList.remove('active', 'btn-primary'));
            docPreviewBtns.forEach(b => b.classList.add('btn-outline-secondary'));
        });
    }

    // --- FUNÇÕES PARA ALTERNAR VISUALIZAÇÃO DE PDF ---
    window.loadPdfEmbed = function(path, name) {
        const area = document.getElementById('pdfViewerArea');
        area.innerHTML = `<embed src="${path}#toolbar=1&navpanes=0&scrollbar=1" type="application/pdf" width="100%" height="100%" />`;
        updatePdfButtons(0);
    };
    
    window.loadPdfObject = function(path, name) {
        const area = document.getElementById('pdfViewerArea');
        area.innerHTML = `<object data="${path}#toolbar=1&navpanes=0&scrollbar=1" type="application/pdf" width="100%" height="100%">
            <p class="text-center p-4">
                Navegador não suporta visualização de PDF. 
                <a href="${path}" target="_blank" class="btn btn-sm btn-primary">Abrir PDF</a>
            </p>
        </object>`;
        updatePdfButtons(1);
    };
    
    window.loadPdfDocs = function(path) {
        const area = document.getElementById('pdfViewerArea');
        const encodedPath = encodeURIComponent(window.location.origin + '/' + path.replace(/^\//, ''));
        area.innerHTML = `<iframe src="https://docs.google.com/viewer?url=${encodedPath}&embedded=true" style="width: 100%; height: 450px; border: none;"></iframe>`;
        updatePdfButtons(2);
    };
    
    function updatePdfButtons(activeIndex) {
        const btnGroup = document.querySelector('.btn-group');
        if (btnGroup) {
            const buttons = btnGroup.querySelectorAll('button');
            buttons.forEach((btn, idx) => {
                btn.classList.remove('active');
                if (idx === activeIndex) {
                    btn.classList.add('active');
                }
            });
        }
    }

});
</script>

<?php require 'footer.php'; ?>