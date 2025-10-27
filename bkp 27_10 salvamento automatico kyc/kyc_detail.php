<?php
$page_title = 'Detalhe da Análise KYC';
require_once 'header.php';

if (!$is_admin) {
    echo "<div class='alert alert-danger'>Acesso negado.</div>";
    echo "</main></div></body></html>";
    exit;
}

$submission_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$submission_id) {
    echo "<div class='alert alert-danger'>ID de submissão inválido.</div>";
    echo "</main></div></body></html>";
    exit;
}

// Função para destacar diferenças
function compare_and_display($title, $submitted_value, $api_value) {
    $submitted_display = htmlspecialchars($submitted_value ?: 'Não informado');
    $api_display = htmlspecialchars($api_value ?: 'Não informado');
    $is_different = (trim(strtolower($submitted_value)) != trim(strtolower($api_value)));
    
    $highlight_class = $is_different ? 'bg-warning-light' : '';

    echo "<div class='list-group-item {$highlight_class}'>";
    echo "    <h6 class='mb-1'>{$title}</h6>";
    echo "    <div class='row'>";
    echo "        <div class='col-md-6 border-right'><small class='text-muted'>Informado:</small><p class='mb-0'>{$submitted_display}</p></div>";
    echo "        <div class='col-md-6'><small class='text-muted'>API Oficial:</small><p class='mb-0'>{$api_display}</p></div>";
    echo "    </div>";
    echo "</div>";
}

$submitted_data = [];
$api_data = [];
$error_message = '';

try {
    // 1. Buscar todos os dados da submissão
    $stmt = $pdo->prepare("SELECT * FROM kyc_company_data WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $submitted_data['company'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM kyc_ubos WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $submitted_data['ubos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM kyc_documents WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $submitted_data['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM kyc_crypto_compliance WHERE submission_id = ?");
    $stmt->execute([$submission_id]);
    $submitted_data['crypto'] = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submitted_data['company']) {
        throw new Exception("Submissão não encontrada.");
    }

    // 2. Chamar a API de CNPJ
    $cnpj_to_query = $submitted_data['company']['cnpj'];
    
    // Usando o contexto de stream para simular uma chamada interna ao proxy
    $context = stream_context_create([
        'http' => [ 'ignore_errors' => true ]
    ]);
    $proxy_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/cnpj_proxy.php?cnpj=' . urlencode($cnpj_to_query);
    
    $api_response_json = file_get_contents($proxy_url, false, $context);
    if($api_response_json === false){
        $error_message = "Não foi possível contatar a API de CNPJ interna. Verifique a URL e permissões.";
    } else {
        $api_data = json_decode($api_response_json, true);
        if(isset($api_data['status']) && $api_data['status'] === 'ERROR'){
            $error_message = "API de CNPJ retornou um erro: " . htmlspecialchars($api_data['message']);
            $api_data = []; // Limpa para não dar erro na renderização
        }
    }

} catch (Exception $e) {
    $error_message = "Erro ao buscar dados: " . $e->getMessage();
}

?>
<style>
    .bg-warning-light { background-color: #fff3cd !important; }
    .card-header h5 { font-weight: 600; }
    .list-group-item h6 { font-weight: 500; color: #495057; }
    .border-right { border-right: 1px solid #dee2e6; }
</style>

<div class="container-fluid mt-4">
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php endif; ?>

    <?php if (!empty($submitted_data['company'])): ?>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                 <h4 class="mb-0">Análise Comparativa - <?= htmlspecialchars($submitted_data['company']['razao_social']) ?></h4>
                 <div>
                     <a href="kyc_action.php?action=approve&id=<?= $submission_id ?>" class="btn btn-success" onclick="return confirm('Tem certeza que deseja APROVAR esta submissão?')">Aprovar</a>
                     <a href="kyc_action.php?action=reject&id=<?= $submission_id ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja REJEITAR esta submissão?')">Rejeitar</a>
                 </div>
            </div>
        </div>

        <div class="card-body">
            <!-- Comparação de Dados Principais -->
            <div class="card mb-4">
                <div class="card-header"><h5>Dados da Empresa vs. API Oficial</h5></div>
                <div class="list-group list-group-flush">
                    <?php 
                        compare_and_display('Razão Social', $submitted_data['company']['razao_social'], $api_data['razao_social'] ?? null);
                        compare_and_display('Nome Fantasia', $submitted_data['company']['nome_fantasia'], $api_data['nome_fantasia'] ?? null);
                        compare_and_display('Data de Abertura', $submitted_data['company']['data_constituicao'], $api_data['data_inicio_atividade'] ?? null);
                        compare_and_display('CNAE Principal', $submitted_data['company']['cnae_principal'], $api_data['cnae_fiscal'] ?? null);
                        $submitted_address = $submitted_data['company']['endereco_completo'];
                        $api_address = trim(sprintf('%s, %s %s - %s, %s - %s, %s', $api_data['logradouro'] ?? '', $api_data['numero'] ?? '', $api_data['complemento'] ?? '', $api_data['bairro'] ?? '', $api_data['municipio'] ?? '', $api_data['uf'] ?? '', $api_data['cep'] ?? ''), " ,-");
                        compare_and_display('Endereço Completo', $submitted_address, $api_address);
                    ?>
                </div>
            </div>

            <!-- Outras Informações Enviadas -->
            <div class="row">
                <div class="col-md-6 mb-4">
                     <div class="card h-100">
                        <div class="card-header"><h5>Sócios e Administradores (UBOs)</h5></div>
                        <div class="card-body">
                            <?php foreach($submitted_data['ubos'] as $ubo): ?>
                                <div class="mb-3 border-bottom pb-2">
                                    <strong><?= htmlspecialchars($ubo['nome_completo']) ?></strong> (<?= htmlspecialchars($ubo['funcao_cargo']) ?>)<br>
                                    CPF: <?= htmlspecialchars($ubo['cpf']) ?> | Part.: <?= htmlspecialchars($ubo['percentual_participacao'] ?? 'N/A') ?>%<br>
                                    É Pessoa Exposta Politicamente (PEP)? <?= $ubo['is_pep'] ? 'Sim' : 'Não' ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($submitted_data['ubos'])): ?> <p>Nenhum sócio informado.</p> <?php endif; ?>
                        </div>
                    </div>
                </div>
                 <div class="col-md-6 mb-4">
                     <div class="card h-100">
                        <div class="card-header"><h5>Documentos Anexados</h5></div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                            <?php foreach($submitted_data['documents'] as $doc): ?>
                                <li class="mb-2"><a href="<?= htmlspecialchars($doc['file_url']) ?>" target="_blank"><?= htmlspecialchars(str_replace('_', ' ', $doc['document_type'])) ?></a></li>
                            <?php endforeach; ?>
                             <?php if (empty($submitted_data['documents'])): ?> <p>Nenhum documento anexado.</p> <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>
</div>

</main>
</div>
</body>
</html>
