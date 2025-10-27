<?php
// FORÇAR EXIBIÇÃO DE ERROS PARA DIAGNÓSTICO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = 'Revisão de Submissão KYC';
require 'header.php';

// --- 1. VERIFICAÇÕES INICIAIS --- 
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!in_array((isset($_SESSION['user_role']) ? $_SESSION['user_role'] : ''), ['admin', 'administrador', 'superadmin'])) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    require 'footer.php';
    exit;
}
$submission_id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$submission_id) {
    header('Location: kyc.php');
    exit;
}

$error = null;
$success = null;
$company_data = null; // Inicializa a variável

// --- 2. PROCESSAR A DECISÃO (APROVAR/REJEITAR) --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = isset($_POST['decision']) ? $_POST['decision'] : '';
    $notes = isset($_POST['analysis_notes']) ? $_POST['analysis_notes'] : '';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if (in_array($decision, ['aprovado', 'rejeitado'])) {
        if ($decision === 'rejeitado' && empty($notes)) {
            $error = "As notas da análise são obrigatórias para rejeitar uma submissão.";
        } else {
            try {
                $pdo->beginTransaction();
                $sql = "UPDATE kyc_submissions SET status = ?, analyst_id = ?, analysis_notes = ?, completed_at = NOW() WHERE id = ? AND status = 'pendente'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$decision, $user_id, $notes, $submission_id]);
                $pdo->commit();
                $_SESSION['flash_message'] = "A submissão #{$submission_id} foi processada.";
                header('Location: kyc.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Erro no banco de dados ao processar a decisão: " . $e->getMessage();
            }
        }
    }
}

// --- 3. BUSCAR DADOS COMPLETOS DO BANCO --- 
try {
    $stmt_company = $pdo->prepare("SELECT * FROM kyc_company_data WHERE submission_id = ?");
    $stmt_company->execute([$submission_id]);
    $company_data = $stmt_company->fetch(PDO::FETCH_ASSOC);

    if (!$company_data) {
        throw new Exception("Submissão #{$submission_id} não encontrada na tabela kyc_company_data.");
    }

    $stmt_ubos = $pdo->prepare("SELECT * FROM kyc_ubos WHERE submission_id = ?");
    $stmt_ubos->execute([$submission_id]);
    $ubos = $stmt_ubos->fetchAll(PDO::FETCH_ASSOC);

    $stmt_docs = $pdo->prepare("SELECT * FROM kyc_documents WHERE submission_id = ? ORDER BY ubo_id");
    $stmt_docs->execute([$submission_id]);
    $all_documents = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    
    $company_docs = [];
    $ubo_docs = [];
    foreach ($all_documents as $doc) {
        if (is_null($doc['ubo_id'])) {
            $company_docs[] = $doc;
        } else {
            if (!isset($ubo_docs[$doc['ubo_id']])) {
                $ubo_docs[$doc['ubo_id']] = [];
            }
            $ubo_docs[$doc['ubo_id']][] = $doc;
        }
    }
} catch (Exception $e) {
    $error = "Erro ao buscar dados do banco: " . $e->getMessage();
}

// --- 4. CONSULTAR API DA RECEITA FEDERAL --- 
$api_data = null;
$api_error = null;
if ($company_data && !empty($company_data['cnpj'])) {
    $cnpj = preg_replace('/[^0-9]/', '', $company_data['cnpj']);
    // Constrói a URL do proxy de forma segura
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $proxy_url = $protocol . $host . $path . '/cnpj_proxy.php?cnpj=' . urlencode($cnpj);

    $api_response = @file_get_contents($proxy_url);
    if ($api_response) {
        $decoded_response = json_decode($api_response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded_response['status']) && $decoded_response['status'] === 'OK') {
            $api_data = $decoded_response;
        } else {
            $api_error = isset($decoded_response['message']) ? $decoded_response['message'] : "A API da Receita retornou um formato inesperado.";
        }
    } else {
        $api_error = "Não foi possível contatar o serviço de consulta de CNPJ.";
    }
}

// --- 5. FUNÇÕES AUXILIARES DE EXIBIÇÃO (VERSÃO SEGURA) --- 
function compareAndDisplay($title, $submitted_val, $api_val) {
    $submitted_safe = htmlspecialchars(isset($submitted_val) ? $submitted_val : 'N/A');
    $api_safe = htmlspecialchars(isset($api_val) ? $api_val : 'N/A');
    $is_divergent = (isset($submitted_val, $api_val) && $submitted_val !== '' && $api_val !== '' && strtolower(trim($submitted_val)) != strtolower(trim($api_val)));

    echo "<tr class='" . ($is_divergent ? 'table-warning' : '') . "'>";
    echo "<th style='width: 25%;'>" . htmlspecialchars($title) . "</th>";
    echo "<td style='width: 37.5%;'>" . $submitted_safe . "</td>";
    echo "<td style='width: 37.5%;'>" . $api_safe . "</td>";
    echo "</tr>";
}

function displayDetail($title, $value) {
    $display_value = (isset($value) && $value !== '') ? htmlspecialchars($value) : "<span class='text-muted'>Não informado</span>";
    echo "<p class='mb-2'><strong>" . htmlspecialchars($title) . ":</strong> " . $display_value . "</p>";
}

$comparison_fields_api_keys = ['RAZAO SOCIAL', 'NOME FANTASIA', 'CNPJ', 'DATA ABERTURA', 'CNAE PRINCIPAL DESCRICAO', 'LOGRADOURO', 'NUMERO', 'COMPLEMENTO', 'BAIRRO', 'MUNICIPIO', 'UF'];

?>

<h2 class="mb-4">Análise da Submissão de KYC #<?= htmlspecialchars($submission_id) ?></h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php // Só exibe o conteúdo se os dados da empresa foram carregados com sucesso
if ($company_data): ?>

    <!-- SEÇÃO 1: COMPARATIVO PRINCIPAL -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Comparativo: Dados Informados vs. Receita Federal</h5></div>
        <div class="card-body p-0">
            <?php if ($api_error): ?><div class="alert alert-warning m-3"><?= htmlspecialchars($api_error) ?></div><?php endif; ?>
            <div class="table-responsive"><table class="table table-bordered table-striped mb-0">
                <thead class="thead-dark"><tr><th>Campo</th><th><i class="fas fa-pencil-alt mr-2"></i>Dados Informados (Parceiro)</th><th><i class="fas fa-landmark mr-2"></i>Dados Oficiais (Receita Federal)</th></tr></thead>
                <tbody>
                    <?php
                        // Usando isset() para cada campo para máxima segurança
                        compareAndDisplay('Razão Social', isset($company_data['razao_social']) ? $company_data['razao_social'] : null, isset($api_data['RAZAO SOCIAL']) ? $api_data['RAZAO SOCIAL'] : null);
                        compareAndDisplay('Nome Fantasia', isset($company_data['nome_fantasia']) ? $company_data['nome_fantasia'] : null, isset($api_data['NOME FANTASIA']) ? $api_data['NOME FANTASIA'] : null);
                        compareAndDisplay('CNPJ', isset($company_data['cnpj']) ? $company_data['cnpj'] : null, isset($api_data['CNPJ']) ? $api_data['CNPJ'] : null);
                        $api_date = (isset($api_data['DATA ABERTURA'])) ? date('Y-m-d', strtotime(str_replace('/', '-', $api_data['DATA ABERTURA']))) : null;
                        compareAndDisplay('Data de Constituição', isset($company_data['data_constituicao']) ? $company_data['data_constituicao'] : null, $api_date);
                        compareAndDisplay('CNAE Principal', isset($company_data['cnae_principal']) ? $company_data['cnae_principal'] : null, isset($api_data['CNAE PRINCIPAL DESCRICAO']) ? $api_data['CNAE PRINCIPAL DESCRICAO'] : null);
                        
                        $api_address = null;
                        if ($api_data) {
                            $addr_parts = [];
                            if (!empty($api_data['LOGRADOURO'])) $addr_parts[] = $api_data['LOGRADOURO'];
                            if (!empty($api_data['NUMERO'])) $addr_parts[] = $api_data['NUMERO'];
                            if (!empty($api_data['COMPLEMENTO'])) $addr_parts[] = $api_data['COMPLEMENTO'];
                            if (!empty($api_data['BAIRRO'])) $addr_parts[] = $api_data['BAIRRO'];
                            if (!empty($api_data['MUNICIPIO'])) $addr_parts[] = $api_data['MUNICIPIO'];
                            if (!empty($api_data['UF'])) $addr_parts[] = $api_data['UF'];
                            $api_address = implode(', ', $addr_parts);
                        }
                        compareAndDisplay('Endereço', isset($company_data['endereco_completo']) ? $company_data['endereco_completo'] : null, $api_address);
                    ?>
                </tbody>
            </table></div>
        </div>
    </div>

    <!-- SEÇÃO 2: DADOS GERAIS E DE CONTATO -->
    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Dados Gerais e de Contato</h5></div>
        <div class="card-body">
            <?php 
            displayDetail('Site da Empresa', isset($company_data['site_empresa']) ? $company_data['site_empresa'] : null);
            displayDetail('E-mail de Contato', isset($company_data['email_contato']) ? $company_data['email_contato'] : null);
            displayDetail('Telefone de Contato', isset($company_data['telefone_contato']) ? $company_data['telefone_contato'] : null);
            displayDetail('Responsável pelo Preenchimento', isset($company_data['responsavel_preenchimento']) ? $company_data['responsavel_preenchimento'] : null);
            displayDetail('Cargo do Responsável', isset($company_data['cargo_responsavel']) ? $company_data['cargo_responsavel'] : null);
            ?>
        </div>
    </div>

    <!-- SEÇÃO 3: PERFIL DE NEGÓCIO E COMPLIANCE -->
    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Perfil de Negócio e Compliance</h5></div>
        <div class="card-body">
            <?php 
            displayDetail('Descrição dos Produtos/Serviços', isset($company_data['produtos_servicos']) ? $company_data['produtos_servicos'] : null);
            displayDetail('Órgão Regulador', isset($company_data['orgao_regulador']) ? $company_data['orgao_regulador'] : null);
            displayDetail('Países de Atuação', isset($company_data['paises_atuacao']) ? $company_data['paises_atuacao'] : null);
            displayDetail('Motivo da Abertura da Conta', isset($company_data['motivo_abertura_conta']) ? $company_data['motivo_abertura_conta'] : null);
            ?>
        </div>
    </div>

    <!-- SEÇÃO 4: PERFIL FINANCEIRO E TRANSACIONAL -->
    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Perfil Financeiro e Transacional</h5></div>
        <div class="card-body">
            <?php 
            displayDetail('Fluxo Financeiro Pretendido', isset($company_data['fluxo_financeiro']) ? $company_data['fluxo_financeiro'] : null);
            displayDetail('Origem Principal dos Fundos', isset($company_data['origem_fundos']) ? $company_data['origem_fundos'] : null);
            displayDetail('Destino Principal dos Fundos', isset($company_data['destino_fundos']) ? $company_data['destino_fundos'] : null);
            displayDetail('Volume Mensal Pretendido', isset($company_data['volume_mensal_pretendido']) ? $company_data['volume_mensal_pretendido'] : null);
            displayDetail('Ticket Médio por Transação', isset($company_data['ticket_medio']) ? $company_data['ticket_medio'] : null);
            displayDetail('Moedas a Operar', isset($company_data['moedas_operar']) ? $company_data['moedas_operar'] : null);
            displayDetail('Blockchains a Operar', isset($company_data['blockchains_operar']) ? $company_data['blockchains_operar'] : null);
            ?>
        </div>
    </div>

    <!-- SEÇÃO 5: QUADRO DE SÓCIOS E ADMINISTRADORES (UBOs) -->
    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Quadro de Sócios, Administradores e Beneficiários Finais</h5></div>
        <div class="card-body">
            <?php if (!empty($ubos)): foreach($ubos as $ubo): ?>
                <div class="card mb-3">
                    <div class="card-header"><strong><?= htmlspecialchars(isset($ubo['funcao_cargo']) ? $ubo['funcao_cargo'] : 'Cargo não informado') ?>:</strong> <?= htmlspecialchars(isset($ubo['nome_completo']) ? $ubo['nome_completo'] : 'Nome não informado') ?></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <?php 
                                displayDetail('CPF', isset($ubo['cpf']) ? $ubo['cpf'] : null);
                                displayDetail('Data de Nascimento', isset($ubo['data_nascimento']) ? $ubo['data_nascimento'] : null);
                                displayDetail('Endereço Residencial', isset($ubo['endereco_residencial']) ? $ubo['endereco_residencial'] : null);
                                displayDetail('Percentual de Participação', isset($ubo['percentual_participacao']) ? $ubo['percentual_participacao'] : null);
                                displayDetail('Pessoa Politicamente Exposta (PEP)', isset($ubo['is_pep']) ? ($ubo['is_pep'] ? 'Sim' : 'Não') : 'Não informado');
                                ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Documentos do Indivíduo:</strong>
                                <?php if (isset($ubo_docs[$ubo['id']]) && !empty($ubo_docs[$ubo['id']])) : ?>
                                    <ul class="list-unstyled mt-2">
                                        <?php foreach($ubo_docs[$ubo['id']] as $doc): ?>
                                            <li><a href="<?= htmlspecialchars(isset($doc['file_url']) ? $doc['file_url'] : '#') ?>" target="_blank"><i class="fas fa-file-alt mr-2"></i><?= htmlspecialchars(ucfirst(str_replace('_', ' ', isset($doc['document_type']) ? $doc['document_type'] : 'Documento'))) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?><p class="text-muted mt-2">Nenhum documento específico anexado.</p><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?><p>Nenhum sócio ou administrador informado.</p><?php endif; ?>
        </div>
    </div>

    <!-- SEÇÃO 6: DOCUMENTOS DA EMPRESA -->
    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Documentos Gerais da Empresa</h5></div>
        <div class="card-body">
            <?php if (!empty($company_docs)): ?>
                <ul class="list-unstyled">
                <?php foreach($company_docs as $doc): ?>
                    <li><a href="<?= htmlspecialchars(isset($doc['file_url']) ? $doc['file_url'] : '#') ?>" target="_blank"><i class="fas fa-file-pdf mr-2"></i><?= htmlspecialchars(ucfirst(str_replace('_', ' ', isset($doc['document_type']) ? $doc['document_type'] : 'Documento'))) ?></a></li>
                <?php endforeach; ?>
                </ul>
            <?php else: ?><p class="text-muted">Nenhum documento geral da empresa foi anexado.</p><?php endif; ?>
        </div>
    </div>

    <!-- SEÇÃO 7: DOSSIÊ COMPLETO DA RECEITA FEDERAL -->
    <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Dossiê Completo da Receita Federal</h5></div>
        <div class="card-body">
            <?php if ($api_data): ?>
                <div class="table-responsive"><table class="table table-sm table-striped table-bordered">
                    <tbody>
                        <?php foreach($api_data as $key => $value): 
                            if (in_array($key, $comparison_fields_api_keys) || in_array($key, ['status', 'message'])) continue;
                            if (empty($value) || (is_array($value) && empty(array_filter($value)))) continue;
                        ?>
                            <tr>
                                <th style="width: 35%;"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $key)))) ?></th>
                                <td><?php 
                                    if (is_array($value)) {
                                        echo "<ul class='list-unstyled mb-0'>";
                                        foreach ($value as $item) {
                                            if(is_array($item)){
                                                $item_str = [];
                                                foreach($item as $sub_key => $sub_value) $item_str[] = htmlspecialchars(ucwords(strtolower($sub_key))) . ': ' . htmlspecialchars($sub_value);
                                                echo "<li>" . implode(', ', $item_str) . "</li>";
                                            } else { echo "<li>" . htmlspecialchars($item) . "</li>"; }
                                        }
                                        echo "</ul>";
                                    } else { echo htmlspecialchars($value); }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php elseif($api_error): ?><p class="text-danger">Não foi possível obter os dados da Receita Federal: <?= htmlspecialchars($api_error)?></p><?php else: ?><p class="text-muted">Dados da Receita não disponíveis.</p><?php endif; ?>
        </div>
    </div>

    <!-- SEÇÃO 8: AÇÃO FINAL -->
    <div class="card"><div class="card-header"><h5 class="mb-0">Decisão Final da Análise</h5></div>
        <div class="card-body">
            <form method="POST" action="kyc_review.php?id=<?= htmlspecialchars($submission_id) ?>">
                <div class="form-group">
                    <label for="analysis_notes"><b>Notas da Análise</b> (Obrigatório para rejeição)</label>
                    <textarea class="form-control" id="analysis_notes" name="analysis_notes" rows="4" placeholder="Adicione aqui suas observações sobre a decisão..."></textarea>
                </div>
                <div class="mt-3 text-right">
                    <button type="submit" name="decision" value="rejeitado" class="btn btn-danger btn-lg mr-2"><i class="fas fa-times-circle mr-2"></i>Rejeitar</button>
                    <button type="submit" name="decision" value="aprovado" class="btn btn-success btn-lg"><i class="fas fa-check-circle mr-2"></i>Aprovar</button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <?php // Se $company_data for nulo ou falso, exibe o erro que foi capturado no bloco Try-Catch
    if (empty($error)) {
        $error = "A submissão solicitada não foi encontrada ou não pôde ser carregada.";
    }
    ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php require 'footer.php'; ?>
