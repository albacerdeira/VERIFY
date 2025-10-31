<?php
// kyc_evaluate_accordion.php

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

// Nova função auxiliar para renderizar um campo com um checkbox de validação
if (!function_exists('display_field_with_check')) {
    function display_field_with_check($label, $value, $check_name, $is_checked) {
        $display_value = !empty($value) ? nl2br(htmlspecialchars($value)) : '<span class="text-muted fst-italic">Não informado</span>';
        $checked_attr = $is_checked ? 'checked' : '';
        return sprintf(
            '<div class="d-flex justify-content-between align-items-start border-bottom py-2">
                <div><p class="text-muted small mb-1">%s</p><strong>%s</strong></div>
                <div class="form-check form-switch ms-3"><input class="form-check-input perfil-negocio-sub-check" type="checkbox" name="%s" value="1" %s></div>
            </div>',
            htmlspecialchars($label), $display_value, htmlspecialchars($check_name), $checked_attr
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

<h5 class="mt-4 mb-3 border-bottom pb-2">Endereço da Empresa</h5>
<div class="row">
    <div class="col-md-3"><?= display_field('CEP', $caso['cep'] ?? null) ?></div>
    <div class="col-md-5"><?= display_field('Logradouro', $caso['logradouro'] ?? null) ?></div>
    <div class="col-md-2"><?= display_field('Número', $caso['numero'] ?? null) ?></div>
    <div class="col-md-2"><?= display_field('UF (Estado)', $caso['uf'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Complemento', $caso['complemento'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Bairro', $caso['bairro'] ?? null) ?></div>
    <div class="col-md-4"><?= display_field('Cidade', $caso['cidade'] ?? null) ?></div>
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
<div class="mt-4 alert alert-secondary">
    <h5 class="mt-4">Documentos da Empresa</h5>
    <div class="form-check form-switch mb-2">
        <input class="form-check-input documentos-sub-check dados-empresa-sub-check" type="checkbox" name="av_check_doc_contrato_social" value="1" <?= ($caso['av_check_doc_contrato_social'] ?? 0) ? 'checked' : '' ?>>
        <label class="form-check-label">Documento Contrato Social Validado</label>
    </div>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input documentos-sub-check dados-empresa-sub-check" type="checkbox" name="av_check_doc_cartao_cnpj" value="1" <?= ($caso['av_check_doc_cartao_cnpj'] ?? 0) ? 'checked' : '' ?>>
        <label class="form-check-label">Documento Cartão CNPJ Validado</label>
    </div>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input dados-empresa-sub-check" type="checkbox" name="av_check_dados_empresa_ok" id="av_check_dados_empresa_ok_sub" value="1" <?= ($caso['av_check_dados_empresa_ok'] ?? 0) ? 'checked' : '' ?>>
        <label class="form-check-label" for="av_check_dados_empresa_ok_sub">Dados da empresa conferem com a API</label>
    </div>
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
<div class="alert alert-secondary">
<h5 class="mt-4 mb-3 border-bottom pb-2">Análise de Compliance</h5>
<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" name="av_check_dados_empresa_ok" id="av_check_dados_empresa_ok" value="1" <?= ($caso['av_check_dados_empresa_ok'] ?? 0) ? 'checked' : '' ?>>
    <label class="form-check-label" for="av_check_dados_empresa_ok">Dados da empresa validados</label>
</div>
<div class="mb-3">
    <label for="av_obs_dados_empresa" class="form-label small">Observações sobre os Dados da Empresa</label>
    <textarea class="form-control observation-field" name="av_obs_dados_empresa" id="av_obs_dados_empresa" rows="3" data-label="Dados da Empresa"><?= htmlspecialchars($caso['av_obs_dados_empresa'] ?? '') ?></textarea>
</div>
</div>
<?php
$content1 = ob_get_clean();

// --- Acordeão 2: Perfil de Negócio e Financeiro ---
ob_start();
?>
<div class=" ps-4 border-start border-3 border-warning bg-gray-100 mb-3">
    <h6>Instruções para o Analista</h6>
    <p>Revise as informações abaixo e marque os checkboxes correspondentes para validar cada aspecto do perfil de negócio e financeiro da empresa. Utilize as observações para registrar quaisquer discrepâncias ou informações adicionais relevantes.</p>
</div>
<?= display_field_with_check('Atividade Principal', $caso['atividade_principal'] ?? null, 'av_check_perfil_atividade', $caso['av_check_perfil_atividade'] ?? 0) ?>
<?= display_field_with_check('Motivo para Abertura da Conta', $caso['motivo_abertura_conta'] ?? null, 'av_check_perfil_motivo', $caso['av_check_perfil_motivo'] ?? 0) ?>
<?= display_field_with_check('Fluxo Financeiro Pretendido', $caso['fluxo_financeiro_pretendido'] ?? null, 'av_check_perfil_fluxo', $caso['av_check_perfil_fluxo'] ?? 0) ?>
<?= display_field_with_check('Volume Mensal Pretendido', $caso['volume_mensal_pretendido'] ?? null, 'av_check_perfil_volume', $caso['av_check_perfil_volume'] ?? 0) ?>
<?= display_field_with_check('Fonte dos Fundos', $caso['origem_fundos'] ?? null, 'av_check_perfil_origem', $caso['av_check_perfil_origem'] ?? 0) ?>
<div class="alert alert-secondary">
<h5 class="mt-4 mb-3 border-bottom pb-2">Validação de Documentos Financeiros</h5>
<div class="form-check form-switch mb-2"><input class="form-check-input documentos-sub-check" type="checkbox" name="av_check_doc_balanco" value="1" <?= ($caso['av_check_doc_balanco'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label">Documento Balanço Validado</label></div>
<div class="form-check form-switch mb-2"><input class="form-check-input documentos-sub-check" type="checkbox" name="av_check_doc_balancete" value="1" <?= ($caso['av_check_doc_balancete'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label">Documento Balancete Validado</label></div>
<div class="form-check form-switch mb-3"><input class="form-check-input documentos-sub-check" type="checkbox" name="av_check_doc_dirpj" value="1" <?= ($caso['av_check_doc_dirpj'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label">Documento DIRPJ Validado</label></div>

<div class="mt-4">
    <label for="av_obs_perfil_negocio" class="form-label small">Observações sobre o Perfil de Negócio</label>
    <textarea class="form-control observation-field" name="av_obs_perfil_negocio" id="av_obs_perfil_negocio" rows="3" data-label="Perfil de Negócio"><?= htmlspecialchars($caso['av_obs_perfil_negocio'] ?? '') ?></textarea>
</div>
</div>
<?php
$content2 = ob_get_clean();

// --- Acordeão 3: Sócios e Administradores (UBOs) ---
ob_start();
$socios_data = $socios->fetchAll(PDO::FETCH_ASSOC);
$docs_por_socio = [];

if (isset($all_documents)) {
    foreach ($all_documents as $doc) {
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
        <div class="alert alert-secondary">
        <h5 class="mt-3">Análise de Compliance (do Analista)</h5>
        <div class="form-check mb-2 bg-light p-3 rounded">
            <input class="form-check-input socio-verificado-checkbox" type="checkbox" name="socio_verificado[<?= $socio_id ?>]" id="verificado_<?= $socio_id ?>" value="1" <?= ($socio['av_socio_verificado'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="verificado_<?= $socio_id ?>">Sócio Verificado</label>
        </div>
        <div class="mb-2">
            <label for="obs_<?= $socio_id ?>" class="form-label small">Observações do Analista sobre este Sócio</label>
            <textarea class="form-control socio-observacoes-textarea" name="socio_observacoes[<?= $socio_id ?>]" id="obs_<?= $socio_id ?>" rows="3" data-socio-nome="<?= htmlspecialchars($socio['nome_completo'] ?? 'Sócio não identificado') ?>"><?= htmlspecialchars($socio['av_socio_observacoes'] ?? '') ?></textarea>
        </div>
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
$content3_existing = ob_get_clean(); // Salva o conteúdo existente
ob_start(); // Inicia um novo buffer para o checkbox
?>
    <div class="alert alert-secondary">
        <h5 class="mt-4 mb-3 border-bottom pb-2">Validação de Documentos Societários</h5>
        <div class="form-check form-switch mb-3">
        <input class="form-check-input documentos-sub-check" type="checkbox" name="av_check_doc_ultima_alteracao" value="1" <?= ($caso['av_check_doc_ultima_alteracao'] ?? 0) ? 'checked' : '' ?>>
        <label class="form-check-label">Documento Última Alteração Societária Validada</label>
    </div>
</div>
<?php
$content3 = $content3_existing . ob_get_clean();
?>

<div class="accordion" id="kycDataAccordion">
    <?= render_accordion_item('One', 'Dados da Empresa', $content1); ?>
    <?= render_accordion_item('Two', 'Perfil de Negócio e Financeiro', $content2); ?>
    <?= render_accordion_item('Three', 'Sócios e Administradores (UBOs)', $content3); ?>
</div>