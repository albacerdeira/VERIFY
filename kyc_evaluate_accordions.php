<?php
// kyc_evaluate_accordions.php

if (!function_exists('render_accordion_item')) {
    function render_accordion_item($id, $title, $content, $parentId = 'kycDataAccordion') {
        $safe_content = $content ?? '<p class="text-muted">Nenhuma informação disponível.</p>';
        return sprintf(
            '<div class="accordion-item">
                <h2 class="accordion-header" id="heading%s">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse%s" aria-expanded="false" aria-controls="collapse%s">%s</button>
                </h2>
                <div id="collapse%s" class="accordion-collapse collapse" aria-labelledby="heading%s" data-bs-parent="#%s"><div class="accordion-body">%s</div></div>
            </div>',
            $id, $id, $id, htmlspecialchars($title), $id, $id, $parentId, $safe_content
        );
    }
}

// --- Acordeão 1: Dados da Empresa ---
ob_start();
?>
<div class="row">
    <div class="col-md-4"><?= display_field('CNPJ', $caso['cnpj'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Razão Social', $caso['razao_social'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Nome Fantasia', $caso['nome_fantasia'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Matriz ou Filial', $caso['identificador_matriz_filial'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Data de Constituição', $caso['data_constituicao'] ?? null, true) ?></div>
    <div class="col-md-4"><?= display_field('Porte', $caso['porte'] ?? null) ?></div>
    <div class="col-md-3"><?= display_field('Opção pelo Simples', $caso['opcao_pelo_simples'] ?? null) ?></div>
</div>
<h5 class="mt-4 mb-3 border-bottom pb-2">Detalhes Fiscais</h5>
<div class="row">
    <div class="col-md-4"><?= display_field('CNAE Principal', $caso['cnae_fiscal'] ?? null) ?></div>
    <div class="col-md-8"><?= display_field('Descrição CNAE Principal', $caso['cnae_fiscal_descricao'] ?? null) ?></div>
</div>
<div class="mt-3">
    <h6>CNAEs Secundários</h6>
    <?php
    $cnaes_secundarios_data = $cnaes_secundarios->fetchAll(PDO::FETCH_ASSOC);
    if (count($cnaes_secundarios_data) > 0) {
        echo '<ul class="list-group list-group-flush">';
        foreach ($cnaes_secundarios_data as $cnae) {
            echo '<li class="list-group-item">' . htmlspecialchars($cnae['cnae'] . ' - ' . $cnae['descricao']) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="text-muted">Nenhum CNAE secundário informado.</p>';
    }
    ?>
</div>
<h5 class="mt-4 mb-3 border-bottom pb-2">Contato e Representante</h5>
<div class="row">
    <div class="col-md-4"><?= display_field('Representante Legal', $caso['representante_legal'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('E-mail de Contato', $caso['email_contato'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Telefone de Contato', $caso['ddd_telefone_1'] ?? null) ?></div>
</div>
<h5 class="mt-4 mb-3 border-bottom pb-2">Outras Informações</h5>
<div class="row">
    <div class="col-md-4"><?= display_field('Observações do Cliente', $caso['observacoes_empresa'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Data de Criação do Registro', $caso['data_criacao'] ?? null, true) ?></div>
    <div class="col-md-4"><?= display_field('Última Atualização', $caso['data_atualizacao'] ?? null, true) ?></div>
</div>
<?php
$content1 = ob_get_clean();

// --- Acordeão 2: Perfil de Negócio e Financeiro ---
ob_start();
?>
<div class="row">
    <div class="col-md-6"><?= display_field('Atividade Principal da Empresa', $caso['atividade_principal'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Motivo para Abertura da Conta', $caso['motivo_abertura_conta'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Fluxo Financeiro Pretendido', $caso['fluxo_financeiro_pretendido'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Volume Mensal Pretendido', $caso['volume_mensal_pretendido'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Moedas que Pretende Operar', $caso['moedas_operar'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Blockchains que Pretende Operar', $caso['blockchains_operar'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Fonte dos Fundos', $caso['origem_fundos'] ?? null) ?></div>
    <div class="col-md-6"><?= display_field('Descrição da Origem (se terceiros)', $caso['descricao_fundos_terceiros'] ?? null) ?></div>
</div>
<?php
$content2 = ob_get_clean();

// --- Acordeão 3: Sócios e Administradores (UBOs) ---
ob_start();
$socios_data = $socios->fetchAll(PDO::FETCH_ASSOC);
$docs_por_socio = [];
if (isset($documentos)) {
    $documentos_data = $documentos->fetchAll(PDO::FETCH_ASSOC);
    foreach ($documentos_data as $doc) {
        if (!empty($doc['socio_id'])) {
            $docs_por_socio[$doc['socio_id']][] = $doc;
        }
    }
}

if (count($socios_data) > 0) {
    echo '<div class="accordion" id="sociosAccordion">';
    foreach ($socios_data as $socio) {
        $socio_id = $socio['id'];
        $accordion_id = 'socio' . $socio_id;

        ob_start(); // Inicia o buffer para o conteúdo de cada sócio
        ?>
        <h6>Dados Cadastrais</h6>
        <div class="row">
            <div class="col-md-4"><?= display_field('CPF/CNPJ', $socio['cpf_cnpj'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Data de Nascimento', $socio['data_nascimento'] ? date('d/m/Y', strtotime($socio['data_nascimento'])) : null) ?></div>
            <div class="col-md-4"><?= display_field('Qualificação/Cargo', $socio['qualificacao_cargo'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Participação (%)', $socio['percentual_participacao'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('É PEP?', ($socio['is_pep'] ?? 0) ? 'Sim' : 'Não') ?></div>
        </div>
        
        <hr>
        <h6>Endereço do Sócio</h6>
        <div class="row">
            <div class="col-md-4"><?= display_field('CEP', $socio['cep'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Logradouro', $socio['logradouro'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Número', $socio['numero'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Complemento', $socio['complemento'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Bairro', $socio['bairro'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Cidade', $socio['cidade'] ?? null) ?></div>
            <div class="col-md-4"><?= display_field('Estado', $socio['uf'] ?? null) ?></div>
            <div class="col-md-12"><?= display_field('Observações', $socio['observacoes'] ?? null) ?></div>
        </div>

        <hr>
        <h6>Documentos do Sócio</h6>
        <?php if (!empty($docs_por_socio[$socio_id])): ?>
            <ul class="list-group list-group-flush">
            <?php foreach ($docs_por_socio[$socio_id] as $doc): ?>
                <li class="list-group-item"><a href="<?= htmlspecialchars($doc['path_arquivo'] ?? '#') ?>" target="_blank"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo_documento'] ?? 'Documento'))) ?></a></li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">Nenhum documento específico encontrado para este sócio.</p>
        <?php endif; ?>
        
        <hr>
        <h6 class="mt-3">Análise de Compliance (do Analista)</h6>
        <div class="form-check mb-2 bg-light p-3 rounded">
            <input class="form-check-input socio-verificado-checkbox" type="checkbox" name="socio_verificado[<?= $socio_id ?>]" id="verificado_<?= $socio_id ?>" value="1" <?= ($socio['av_socio_verificado'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="verificado_<?= $socio_id ?>">Sócio Verificado</label>
        </div>
        <div class="mb-2">
            <label for="obs_<?= $socio_id ?>" class="form-label small">Observações do Analista sobre este Sócio</label>
            <textarea class="form-control socio-observacoes-textarea" name="socio_observacoes[<?= $socio_id ?>]" id="obs_<?= $socio_id ?>" rows="3" data-socio-nome="<?= htmlspecialchars($socio['nome_completo'] ?? 'Sócio não identificado') ?>"><?= htmlspecialchars($socio['av_socio_observacoes'] ?? '') ?></textarea>
        </div>
        <?php
        $socio_content = ob_get_clean();
        $socio_title = ($socio['nome_completo'] ?? 'Sócio não identificado');
        echo render_accordion_item($accordion_id, $socio_title, $socio_content, 'sociosAccordion');
    }
    echo '</div>';
} else {
    echo '<p class="text-muted">Nenhum sócio foi cadastrado.</p>';
}
$content3 = ob_get_clean();

// --- Acordeão 4: Documentos da Empresa ---
ob_start();
$docs_empresa = [];
if (isset($documentos_data)) {
    foreach ($documentos_data as $doc) {
        if (empty($doc['socio_id'])) {
            $docs_empresa[] = $doc;
        }
    }
}
if (count($docs_empresa) > 0) {
    echo '<ul class="list-group">';
    foreach ($docs_empresa as $doc) {
        $doc_name = htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['tipo_documento'] ?? 'Documento')));
        $doc_path = htmlspecialchars($doc['path_arquivo'] ?? '#');
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>{$doc_name}<a href='{$doc_path}' target='_blank' class='btn btn-sm btn-outline-primary'>Ver Documento</a></li>";
    }
    echo '</ul>';
} else {
    echo '<p class="text-muted">Nenhum documento da empresa foi enviado.</p>';
}
$content4 = ob_get_clean();

?>

<div class="accordion" id="kycDataAccordion">
    <?= render_accordion_item('One', 'Dados da Empresa', $content1); ?>
    <?= render_accordion_item('Two', 'Perfil de Negócio e Financeiro', $content2); ?>
    <?= render_accordion_item('Three', 'Sócios e Administradores (UBOs)', $content3); ?>
    <?= render_accordion_item('Four', 'Documentos da Empresa', $content4); ?>
</div>