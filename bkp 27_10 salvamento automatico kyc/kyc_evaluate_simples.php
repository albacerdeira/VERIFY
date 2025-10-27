<?php
require 'config.php';
session_start();

// --- Validação de Acesso ---
$user_role = isset($_SESSION['user_role']) ? trim(strtolower($_SESSION['user_role'])) : '';
if (!isset($_SESSION['user_id']) || !in_array($user_role, ['analista', 'superadmin'])) {
    http_response_code(403);
    exit('Acesso Negado');
}

$analista_id = $_SESSION['user_id'];
$kyc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- Processamento do Formulário de Análise ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $kyc_id) {
    $status = $_POST['analise_decisao_final'];
    $update_sql = "UPDATE kyc_empresas SET status = :status, analise_anotacoes_internas = :anotacoes, analise_risco_final = :risco, analise_justificativa_risco = :justificativa, analise_info_pendencia = :pendencia, analista_id = :analista_id, data_analise = NOW() WHERE id = :kyc_id";
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([
        ':status' => $status,
        ':anotacoes' => $_POST['analise_anotacoes_internas'],
        ':risco' => $_POST['analise_risco_final'],
        ':justificativa' => $_POST['analise_justificativa_risco'],
        ':pendencia' => $_POST['analise_info_pendencia'],
        ':analista_id' => $analista_id,
        ':kyc_id' => $kyc_id
    ]);
    $_SESSION['flash_message'] = "Análise salva com sucesso.";
    header('Location: kyc_list.php');
    exit;
}

// --- Busca de Dados ---
if (!$kyc_id) exit('ID inválido.');
$stmt = $pdo->prepare("SELECT * FROM kyc_empresas WHERE id = :id");
$stmt->execute(['id' => $kyc_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$empresa) exit('Registro não encontrado.');

// --- Busca de Sócios ---
$socios_stmt = $pdo->prepare("SELECT * FROM kyc_socios WHERE empresa_id = :empresa_id ORDER BY id");
$socios_stmt->execute(['empresa_id' => $kyc_id]);
$socios = $socios_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Busca de Documentos ---
$docs_stmt = $pdo->prepare("SELECT * FROM kyc_documentos WHERE empresa_id = :empresa_id ORDER BY tipo_documento");
$docs_stmt->execute(['empresa_id' => $kyc_id]);
$documentos = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);

$documentos_empresa = array_filter($documentos, function($doc) { return is_null($doc['socio_id']); });
$documentos_por_socio = [];
$docs_de_socios = array_filter($documentos, function($doc) { return !is_null($doc['socio_id']); });
foreach ($docs_de_socios as $doc) {
    $documentos_por_socio[$doc['socio_id']][] = $doc;
}

$page_title = "Análise KYC - " . htmlspecialchars($empresa['razao_social']);
require 'header.php';

function display_field($label, $value, $is_date = false, $full_width = false) {
    $col_class = $full_width ? 'col-md-12' : 'col-md-4';
    $display_value = !empty($value) ? ($is_date ? date('d/m/Y', strtotime($value)) : nl2br(htmlspecialchars($value))) : '<span class="text-muted fst-italic">Não informado</span>';
    echo "<div class='{$col_class} mb-3'><p class='text-muted small mb-1'>$label</p><strong>$display_value</strong></div>";
}

function render_accordion($title, $content, $is_open = false) {
    $details_attr = $is_open ? 'open' : '';
    return "<details class='accordion-custom' {$details_attr}><summary class='accordion-custom-summary'>{$title}<span class='accordion-arrow'>▼</span></summary><div class='accordion-custom-content'>{$content}</div></details>";
}

// --- ETAPA 1: DADOS DA EMPRESA ---
ob_start();
echo "<h5 class='mb-3 border-bottom pb-2'>Dados Principais</h5><div class='row'>";
display_field('CNPJ', $empresa['cnpj']);
display_field('Razão Social', $empresa['razao_social']);
display_field('Nome Fantasia', $empresa['nome_fantasia']);
display_field('Data de Constituição', $empresa['data_constituicao'], true);
echo "</div><h5 class='mt-3 mb-3 border-bottom pb-2'>Endereço</h5><div class='row'>";
display_field('CEP', $empresa['cep']);
display_field('Logradouro', $empresa['logradouro']);
display_field('Número', $empresa['numero']);
display_field('Complemento', $empresa['complemento']);
display_field('Bairro', $empresa['bairro']);
display_field('Cidade', $empresa['cidade']);
display_field('Estado', $empresa['uf']);
echo "</div><h5 class='mt-3 mb-3 border-bottom pb-2'>CNAE</h5><div class='row'>";
display_field('CNAE Principal', $empresa['cnae_fiscal']);
display_field('Descrição CNAE Principal', $empresa['cnae_fiscal_descricao'], false, true);
echo "<div class='col-md-12'><p class='text-muted small mb-1'>CNAEs Secundários</p><strong><span class='text-muted fst-italic'>Visualização de CNAEs secundários pendente.</span></strong></div>";
echo "</div><h5 class='mt-3 mb-3 border-bottom pb-2'>Detalhes Cadastrais</h5><div class='row'>";
display_field('Matriz ou Filial', $empresa['identificador_matriz_filial']);
display_field('Situação Cadastral', $empresa['situacao_cadastral']);
display_field('Motivo da Situação', $empresa['descricao_motivo_situacao_cadastral'], false, true);
display_field('Porte', $empresa['porte']);
display_field('Natureza Jurídica', $empresa['natureza_juridica']);
display_field('Opção pelo Simples', $empresa['opcao_pelo_simples']);
display_field('Representante Legal (API)', $empresa['representante_legal']);
echo "</div><h5 class='mt-3 mb-3 border-bottom pb-2'>Contato e Observações</h5><div class='row'>";
display_field('E-mail de Contato', $empresa['email_contato']);
display_field('Telefone de Contato', $empresa['ddd_telefone_1']);
display_field('Observações do Cliente', $empresa['observacoes_empresa'], false, true);
echo "</div>";
$etapa1_content = ob_get_clean();

// --- ETAPA 2: PERFIL DE NEGÓCIO E FINANCEIRO ---
ob_start();
echo "<div class='row'>";
display_field('Atividade Principal da Empresa', $empresa['atividade_principal'], false, true);
display_field('Motivo para Abertura da Conta', $empresa['motivo_abertura_conta'], false, true);
display_field('Fluxo Financeiro Pretendido', $empresa['fluxo_financeiro_pretendido'], false, true);
display_field('Moedas que Pretende Operar', $empresa['moedas_operar']);
display_field('Blockchains que Pretende Operar', $empresa['blockchains_operar']);
display_field('Volume Mensal Pretendido (R$)', $empresa['volume_mensal_pretendido']);
display_field('Fonte dos Fundos', $empresa['origem_fundos']);
display_field('Descrição da Origem dos Fundos', $empresa['descricao_fundos_terceiros'], false, true);
echo "</div>";
$etapa2_content = ob_get_clean();

// --- ETAPA 3: SÓCIOS E ADMINISTRADORES (UBOs) ---
ob_start();
if (count($socios) > 0) {
    foreach ($socios as $index => $socio) {
        echo '<div class="card mb-3"><div class="card-header"><strong>Sócio/Administrador #' . ($index + 1) . ': ' . htmlspecialchars($socio['nome_completo']) . '</strong></div><div class="card-body"><div class="row">';
        display_field('Nome Completo', $socio['nome_completo']);
        display_field('Data de Nascimento', $socio['data_nascimento'], true);
        display_field('CPF/CNPJ', $socio['cpf_cnpj']);
        display_field('Qualificação/Cargo', $socio['qualificacao_cargo']);
        display_field('Participação (%)', $socio['percentual_participacao']);
        display_field('É PEP?', $socio['is_pep'] ? 'Sim' : 'Não');
        echo '<div class="col-12 mt-2"><p class="text-muted small mb-1">Endereço</p><strong>' . htmlspecialchars("{$socio['logradouro']}, {$socio['numero']} - {$socio['bairro']}, {$socio['cidade']}-{$socio['uf']}") . '</strong></div>';
        echo '<div class="col-12 mt-3"><p class="fw-bold">Documentos do Sócio</p>';
        $socio_docs = isset($documentos_por_socio[$socio['id']]) ? $documentos_por_socio[$socio['id']] : [];
        if (count($socio_docs) > 0) {
            echo '<ul class="list-group list-group-flush">';
            foreach ($socio_docs as $doc) {
                echo '<li class="list-group-item"><a href="' . htmlspecialchars($doc['path_arquivo']) . '" target="_blank">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo_documento']))) . '</a></li>';
            }
            echo '</ul>';
        } else { echo '<span class="text-muted fst-italic">Nenhum documento encontrado para este sócio.</span>'; }
        echo '</div></div></div></div>';
    }
} else {
    echo '<div class="alert alert-light">Nenhum sócio ou administrador foi cadastrado.</div>';
}
$etapa3_content = ob_get_clean();

// --- ETAPA 4: DOCUMENTOS DA EMPRESA ---
ob_start();
if (count($documentos_empresa) > 0) {
    echo '<ul class="list-group">';
    foreach ($documentos_empresa as $doc) {
        $doc_name = ucfirst(str_replace('_', ' ', str_replace('doc_', '', $doc['tipo_documento'])));
        echo '<li class="list-group-item d-flex justify-content-between align-items-center">' . htmlspecialchars($doc_name) . '<a href="' . htmlspecialchars($doc['path_arquivo']) . '" target="_blank" class="btn btn-sm btn-outline-primary">Ver Documento</a></li>';
    }
    echo '</ul>';
} else {
    echo '<div class="alert alert-light">Nenhum documento da empresa foi enviado.</div>';
}
echo "<div class='mt-4 border-top pt-3'>";
display_field('Consentimento dos Termos', $empresa['consentimento_termos'] ? 'Aceito' : 'Não Aceito');
echo "</div>";
$etapa4_content = ob_get_clean();

// --- PAINEL DE ANÁLISE ---
ob_start();
?>
<form action="kyc_evaluate.php?id=<?php echo $kyc_id; ?>" method="POST">
    <div class="row">
        <div class="col-md-6 mb-4"><label class="form-label fw-bold">Anotações Internas</label><textarea name="analise_anotacoes_internas" class="form-control" rows="4" placeholder="Observações sobre a análise, pontos de atenção, etc."><?php echo htmlspecialchars(isset($empresa['analise_anotacoes_internas']) ? $empresa['analise_anotacoes_internas'] : ''); ?></textarea></div>
        <div class="col-md-6 mb-4"><label class="form-label fw-bold">Justificativa do Risco</label><textarea name="analise_justificativa_risco" class="form-control" rows="4" placeholder="Detalhar por que o risco foi classificado como baixo, médio ou alto."><?php echo htmlspecialchars(isset($empresa['analise_justificativa_risco']) ? $empresa['analise_justificativa_risco'] : ''); ?></textarea></div>
    </div>
    <div class="row align-items-end">
        <div class="col-md-4 mb-3"><label for="analise_risco_final" class="form-label fw-bold">Nível de Risco</label><select name="analise_risco_final" class="form-select"><option value="Baixo" <?php echo (isset($empresa['analise_risco_final']) && $empresa['analise_risco_final'] == 'Baixo') ? 'selected' : ''; ?>>Baixo</option><option value="Medio" <?php echo (isset($empresa['analise_risco_final']) && $empresa['analise_risco_final'] == 'Medio') ? 'selected' : ''; ?>>Médio</option><option value="Alto" <?php echo (isset($empresa['analise_risco_final']) && $empresa['analise_risco_final'] == 'Alto') ? 'selected' : ''; ?>>Alto</option></select></div>
        <div class="col-md-8 mb-3"><label class="form-label fw-bold">Decisão Final</label><div class="btn-group w-100"><input type="radio" class="btn-check" name="analise_decisao_final" value="Aprovado" id="decisao_aprovar" <?php echo (isset($empresa['status']) && $empresa['status'] == 'Aprovado') ? 'checked' : ''; ?> required><label class="btn btn-outline-success" for="decisao_aprovar">Aprovar</label><input type="radio" class="btn-check" name="analise_decisao_final" value="Reprovado" id="decisao_reprovar" <?php echo (isset($empresa['status']) && $empresa['status'] == 'Reprovado') ? 'checked' : ''; ?> required><label class="btn btn-outline-danger" for="decisao_reprovar">Reprovar</label><input type="radio" class="btn-check" name="analise_decisao_final" value="Pendenciado" id="decisao_pendenciar" <?php echo (isset($empresa['status']) && $empresa['status'] == 'Pendenciado') ? 'checked' : ''; ?> required><label class="btn btn-outline-secondary" for="decisao_pendenciar">Pendenciar</label></div></div>
    </div>
    <div class="mb-3 mt-3"><label class="form-label fw-bold">Informações de Pendência</label><textarea name="analise_info_pendencia" class="form-control" rows="3" placeholder="Se pendente, descreva as informações necessárias."><?php echo htmlspecialchars(isset($empresa['analise_info_pendencia']) ? $empresa['analise_info_pendencia'] : ''); ?></textarea></div>
    <button type="submit" class="btn btn-primary w-100 mt-3">Salvar Análise</button>
</form>
<?php
$analysis_form_content = ob_get_clean();
?>

<style>
    .accordion-custom { border: 1px solid #e2e8f0; border-radius: 0.5rem; margin-bottom: 1rem; background-color: #fff; overflow: hidden;}
    .accordion-custom-summary { display: flex; justify-content: space-between; align-items: center; padding: 1rem; font-size: 1.125rem; font-weight: 500; color: #4a5568; cursor: pointer; list-style: none; background-color: #f7fafc; }
    .accordion-custom-summary::-webkit-details-marker { display: none; }
    .accordion-custom-content { padding: 1.5rem; border-top: 1px solid #e2e8f0; }
    .accordion-arrow { transition: transform 0.2s; }
    details[open] > summary > .accordion-arrow { transform: rotate(180deg); }
    details[open] > summary { background-color: var(--primary-color); color: white; }
</style>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-3">Análise de Caso KYC: #<?php echo $empresa['id']; ?></h2>
    <p class="lead text-muted mb-4">Cliente: <?php echo htmlspecialchars($empresa['razao_social']); ?></p>

    <?php echo render_accordion('Etapa 1: Dados da Empresa', $etapa1_content); ?>
    <?php echo render_accordion('Etapa 2: Perfil de Negócio e Financeiro', $etapa2_content); ?>
    <?php echo render_accordion('Etapa 3: Sócios e Administradores (UBOs)', $etapa3_content); ?>
    <?php echo render_accordion('Etapa 4: Documentos da Empresa', $etapa4_content); ?>
    <?php echo render_accordion('Painel de Análise do Compliance', $analysis_form_content, true); ?>

</div>

<?php require 'footer.php'; ?>
