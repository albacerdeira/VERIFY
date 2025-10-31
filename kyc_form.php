<?php
$page_title = 'Formulário de Know Your Customer (KYC)';
require_once 'bootstrap.php';

// --- CORREÇÃO: LÓGICA DE CONTEXTO PARA CLIENTE LOGADO ---
// Se um cliente estiver logado, suas informações de sessão têm prioridade sobre a URL.
if (isset($_SESSION['cliente_id'])) {
    try {
        // --- CORREÇÃO AQUI: Troca 'c.whitelabel_parceiro_id' por 'c.id_empresa_master' ---
        // --- E o JOIN ON usa 'w.empresa_id' ---
        $stmt_cliente_contexto = $pdo->prepare(
            "SELECT c.id_empresa_master, w.slug, w.nome_empresa, w.cor_variavel
             FROM kyc_clientes c
             LEFT JOIN configuracoes_whitelabel w ON c.id_empresa_master = w.empresa_id
             WHERE c.id = ?"
        );
        $stmt_cliente_contexto->execute([$_SESSION['cliente_id']]);
        $cliente_contexto = $stmt_cliente_contexto->fetch(PDO::FETCH_ASSOC);

        if ($cliente_contexto) {
            // Sobrescreve as variáveis que podem ter sido definidas em bootstrap.php pela URL
            $id_empresa_master_contexto = $cliente_contexto['id_empresa_master'];
            $slug_contexto = $cliente_contexto['slug'];
            $nome_empresa_contexto = $cliente_contexto['nome_empresa'];
            $cor_variavel_contexto = $cliente_contexto['cor_variavel'];
        } else {
            // Caso de erro de integridade de dados (ou cliente sem empresa master associada)
            // Não lançamos um erro, apenas seguimos com o contexto da URL ou padrão.
             error_log("Cliente logado (ID: {$_SESSION['cliente_id']}) não possui id_empresa_master ou whitelabel correspondente.");
        }

    } catch (Exception $e) {
        // Exibe um erro seguro sem quebrar a página
        $header_file = isset($_SESSION['user_id']) ? 'header.php' : 'form_header.php';
        $footer_file = isset($_SESSION['user_id']) ? 'footer.php' : 'form_footer.php';
        require $header_file;
        echo '<div class="container mt-4"><div class="alert alert-danger"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
        require $footer_file;
        exit();
    }
}
// --- FIM DA CORREÇÃO ---

// --- LÓGICA DE REFORÇO WHITELABEL (Fallback) ---
// Se o contexto do cliente não foi carregado (ex: admin ou slug na URL)
if (empty($cor_variavel_contexto) && !empty($slug_contexto)) {
    try {
        // A view 'view_whitelabel_context' já deve fazer o JOIN correto.
        // Se ela não existir, usamos a consulta direta:
        $stmt = $pdo->prepare("SELECT empresa_id, cor_variavel, nome_empresa FROM configuracoes_whitelabel WHERE slug = ?");
        $stmt->execute([$slug_contexto]);
        $config_reforco = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config_reforco) {
            if (empty($cor_variavel_contexto)) $cor_variavel_contexto = $config_reforco['cor_variavel'];
            if (empty($nome_empresa_contexto)) $nome_empresa_contexto = $config_reforco['nome_empresa'];
            if (empty($id_empresa_master_contexto)) $id_empresa_master_contexto = $config_reforco['empresa_id'];
        }
    } catch (PDOException $e) {
        error_log("Erro no reforço whitelabel em kyc_form.php: " . $e->getMessage());
    }
}

// LÓGICA DE DECISÃO DO LAYOUT
if (isset($_SESSION['user_id'])) {
    require 'header.php';
} else {
    require 'form_header.php';
}

$cor_final_whitelabel = $cor_variavel_contexto ?? ($_SESSION['cor_variavel'] ?? '#4f46e5');
?>

<style>
    :root { --primary-color: <?= htmlspecialchars($cor_final_whitelabel) ?>; }
    .progress-step.active { background-color: var(--primary-color); }
    .form-section-title { color: var(--primary-color); border-bottom-color: var(--primary-color); }
    .btn-primary, .btn-success { background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover, .btn-success:hover { filter: brightness(0.9); background-color: var(--primary-color); border-color: var(--primary-color); }
    .progress-bar-container { display: flex; justify-content: space-between; counter-reset: step; margin-bottom: 2rem; max-width: 900px; margin-left: auto; margin-right: auto; }
    .progress-step { width: 2.5rem; height: 2.5rem; background-color: #d1d5db; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; color: white; position: relative; transition: background-color: 0.4s ease; }
    .progress-step::before { counter-increment: step; content: counter(step); }
    .progress-step.active { background-color: var(--primary-color, #4f46e5); }
    .progress-step-label { position: absolute; top: calc(100% + 5px); font-size: 0.8rem; color: #6b7280; text-align: center; width: 100px; left: 50%; transform: translateX(-50%); }
    .form-step { display: none; animation: fadeIn 0.5s ease-out forwards; }
    .form-step.active { display: block; }

    .spinner-container {
        position: relative; 
    }
    .spinner-container .spinner-border {
        display: none; 
        position: absolute;
        right: 20px; 
        top: 70%;    
        transform: translateY(-50%);
    }
    .spinner-container.loading .spinner-border {
        display: block; 
    }
</style>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">

    <?php
    if ($is_superadmin_on_kyc && !isset($_GET['cliente'])):
        $empresas_parceiras = $pdo->query("SELECT c.slug, c.nome_empresa FROM configuracoes_whitelabel c WHERE c.slug IS NOT NULL ORDER BY c.nome_empresa")->fetchAll(PDO::FETCH_ASSOC);
    ?>
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading">Modo Superadmin</h5>
            <p>Selecione um parceiro abaixo para carregar o formulário com a marca e o vínculo corretos.</p>
            <select id="partner-selector" class="form-select">
                <option value="">Selecione um parceiro...</option>
                <?php foreach ($empresas_parceiras as $parceiro): ?>
                    <option value="<?= htmlspecialchars($parceiro['slug']) ?>"><?= htmlspecialchars($parceiro['nome_empresa']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <script>
        document.getElementById('partner-selector').addEventListener('change', function() {
            if (this.value) { window.location.href = 'kyc_form.php?cliente=' + this.value; }
        });
        </script>
    <?php endif; ?>

    <?php
    $link_completo = '';
    if (isset($_SESSION['user_id']) && !empty($slug_contexto)) {
        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $caminho_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        
        // --- ALTERAÇÃO SOLICITADA ---
        // O link agora aponta para 'cliente_login.php' em vez de 'kyc_form.php'
        $link_completo = "{$protocolo}://{$host}{$caminho_base}/cliente_login.php?cliente={$slug_contexto}";
    }

    if (!empty($link_completo)):
    ?>
        <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between bg-light p-3 rounded mb-4" id="share-bar">
            <div class="mb-2 mb-md-0">
                <strong class="me-3">Compartilhar Login:</strong> 
                <input type="hidden" value="<?= htmlspecialchars($link_completo) ?>" id="share-link-input">
            </div>
            <div>
                <button class="btn btn-success btn-sm me-2" type="button" id="copy-btn"><i class="fas fa-link"></i> Convite+Link</button>
                <button class="btn btn-outline-secondary btn-sm me-2" type="button" id="copy-link-only-btn"><i class="fas fa-copy"></i> Só Link</button>
                <a href="#" id="whatsapp-btn" class="btn btn-light btn-sm border me-2" target="_blank"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                <a href="#" id="email-btn" class="btn btn-light btn-sm border" target="_blank"><i class="fas fa-envelope"></i> E-mail</a>
                <span id="copy-success" class="text-success ms-2" style="display:none; font-size: 0.9em;">Copiado!</span>
            </div>
        </div>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.getElementById('share-bar')) return;
            const shareUrl = document.getElementById('share-link-input').value;
            const nomeDaEmpresa = "<?= htmlspecialchars($nome_empresa_contexto ?? 'nossa empresa') ?>";
            
            // --- ALTERAÇÃO SOLICITADA (TEXTOS) ---
            // Os textos foram atualizados para refletir o link de login
            const copyText = `Olá! Acesse a área do cliente em ${nomeDaEmpresa} através do link: ${shareUrl}`;
            const emailSubject = encodeURIComponent(`Acesso à Área do Cliente ${nomeDaEmpresa}`);
            const emailBody = encodeURIComponent(`Olá,\n\nPara acessar sua área do cliente, utilize o link abaixo:\n\n${shareUrl}\n\nAtenciosamente,\nEquipe ${nomeDaEmpresa}`);
            // --- FIM DA ALTERAÇÃO ---
            
            const whatsappText = encodeURIComponent(copyText);

            document.getElementById('email-btn').href = `mailto:?subject=${emailSubject}&body=${emailBody}`;
            document.getElementById('whatsapp-btn').href = `https://api.whatsapp.com/send?text=${whatsappText}`;

            function setupCopy(buttonId, textToCopy, successMsg) {
                const btn = document.getElementById(buttonId);
                const successSpan = document.getElementById('copy-success');
                if (!btn) return;
                btn.addEventListener('click', function() {
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        successSpan.textContent = successMsg;
                        successSpan.style.display = 'inline';
                        const originalHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                        setTimeout(() => { 
                            successSpan.style.display = 'none';
                            btn.innerHTML = originalHtml;
                        }, 2500);
                    });
                });
            }
            setupCopy('copy-btn', copyText, 'Convite copiado!');
            setupCopy('copy-link-only-btn', shareUrl, 'Link copiado!');
        });
        </script>
    <?php endif; ?>

    <h2 class="mb-3 text-center">Formulário de Know Your Customer (KYC)</h2>
    <p class="lead mb-5 text-center text-muted">Preencha os dados abaixo. Seu progresso é salvo automaticamente.</p>

    <div class="progress-bar-container" id="progress-bar"></div>

    <form action="kyc_submit.php" method="POST" enctype="multipart/form-data" id="kyc-form" novalidate>
        
        <input type="hidden" name="id_empresa_master" value="<?= htmlspecialchars($id_empresa_master_contexto ?? ''); ?>">
        <input type="hidden" name="cliente_slug" value="<?= htmlspecialchars($slug_contexto ?? ''); ?>">
        <input type="hidden" name="submission_id" id="submission_id">

           <div class="form-step active" data-step="1">
            <h5 class="form-section-title">1. Dados da Empresa</h5>
            <div class="row">
                <div class="form-group col-md-4 spinner-container" id="cnpj-container">
                    <label for="cnpj">CNPJ *</label>
                    <input type="text" class="form-control" id="cnpj" name="empresa[cnpj]" required>
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <div class="invalid-feedback">CNPJ inválido ou não encontrado.</div>
                </div>
                <div class="form-group col-md-8"><label for="razao_social">Razão Social</label><input type="text" class="form-control" id="razao_social" name="empresa[razao_social]" readonly></div>
            </div>
            <div class="row">
                 <div class="form-group col-md-8"><label for="nome_fantasia">Nome Fantasia *</label><input type="text" class="form-control" id="nome_fantasia" name="empresa[nome_fantasia]" required></div>
                 <div class="form-group col-md-4"><label for="data_constituicao">Data de Constituição</label><input type="text" class="form-control" id="data_constituicao" name="empresa[data_constituicao]" readonly></div>
            </div>
            <hr><h6 class="mb-3">Endereço</h6>
            <div class="row">
                <div class="form-group col-md-3 spinner-container"><label for="cep">CEP</label><input type="text" class="form-control" id="cep" name="empresa[cep]"><div class="spinner-border spinner-border-sm text-primary"></div><div class="invalid-feedback">CEP inválido.</div></div>
                <div class="form-group col-md-9"><label for="logradouro">Logradouro</label><input type="text" class="form-control" id="logradouro" name="empresa[logradouro]" readonly></div>
            </div>
            <div class="row">
                <div class="form-group col-md-3"><label for="numero">Número</label><input type="text" class="form-control" id="numero" name="empresa[numero]"></div>
                <div class="form-group col-md-9"><label for="complemento">Complemento</label><input type="text" class="form-control" id="complemento" name="empresa[complemento]"></div>
            </div>
            <div class="row">
                <div class="form-group col-md-5"><label for="bairro">Bairro</label><input type="text" class="form-control" id="bairro" name="empresa[bairro]" readonly></div>
                <div class="form-group col-md-5"><label for="cidade">Cidade</label><input type="text" class="form-control" id="cidade" name="empresa[cidade]" readonly></div>
                <div class="form-group col-md-2"><label for="uf">Estado</label><input type="text" class="form-control" id="uf" name="empresa[uf]" readonly></div>
            </div>
            <hr><h6 class="mb-3">CNAE</h6>
            <div class="row"><div class="form-group col-md-4"><label for="cnae_fiscal">CNAE Principal</label><input type="text" class="form-control" id="cnae_fiscal" name="empresa[cnae_principal]" readonly></div><div class="form-group col-md-8"><label for="cnae_fiscal_descricao">Descrição CNAE Principal</label><input type="text" class="form-control" id="cnae_fiscal_descricao" name="empresa[descricao_cnae_principal]" readonly></div></div>
            <h6 class="mb-3 mt-3">CNAEs Secundários</h6>
            <div id="cnaes-secundarios-container"></div>
            
            <hr><h6 class="mb-3">Detalhes Cadastrais</h6>
            <div class="row">
                <div class="form-group col-md-3"><label for="identificador_matriz_filial">Matriz ou Filial</label><input type="text" class="form-control" id="identificador_matriz_filial" name="empresa[identificador_matriz_filial]" readonly></div>
                <div class="form-group col-md-3"><label for="situacao_cadastral">Situação Cadastral</label><input type="text" class="form-control" id="situacao_cadastral" name="empresa[situacao_cadastral]" readonly></div>
                <div class="form-group col-md-6"><label for="descricao_motivo_situacao_cadastral">Motivo da Situação Cadastral</label><input type="text" class="form-control" id="descricao_motivo_situacao_cadastral" name="empresa[descricao_motivo_situacao_cadastral]" readonly></div>
            </div>
            <div class="row">
                <div class="form-group col-md-4"><label for="porte">Porte</label><input type="text" class="form-control" id="porte" name="empresa[porte]" readonly></div>
                <div class="form-group col-md-5"><label for="natureza_juridica">Natureza Jurídica</label><input type="text" class="form-control" id="natureza_juridica" name="empresa[natureza_juridica]" readonly></div>
                <div class="form-group col-md-3"><label for="opcao_pelo_simples">Opção pelo Simples</label><input type="text" class="form-control" id="opcao_pelo_simples" name="empresa[opcao_pelo_simples]" readonly></div>
            </div>
            <div class="row">
                <div class="form-group col-md-12"><label for="representante_legal">Representante Legal *</label><input type="text" class="form-control" id="representante_legal" name="empresa[representante_legal]" required placeholder="Representante não encontrado na consulta, preencher"></div>
            </div>
            
            <hr><h6 class="mb-3">Contato e Observações</h6>
            <div class="row">
                <div class="form-group col-md-6"><label for="email">E-mail de Contato *</label><input type="email" class="form-control" id="email" name="empresa[email_contato]" required><div class="invalid-feedback">E-mail inválido.</div></div>
                <div class="form-group col-md-6"><label for="ddd_telefone_1">Telefone de Contato *</label><input type="text" class="form-control" id="ddd_telefone_1" name="empresa[telefone_contato]" required><div class="invalid-feedback">Telefone obrigatório.</div></div>
            </div>
            <div class="form-group">
                <label for="observacoes_empresa">Observações</label>
                <textarea class="form-control" id="observacoes_empresa" name="empresa[observacoes]" rows="3"></textarea>
            </div>
        </div>

        <div class="form-step" data-step="2">
            <h5 class="form-section-title">2. Perfil de Negócio e Financeiro</h5>
            <div class="form-group"><label for="atividade_principal">Qual a atividade principal da empresa? *</label><textarea class="form-control" id="atividade_principal" name="perfil[atividade_principal]" rows="3" required></textarea></div>
            <div class="form-group"><label for="motivo_abertura_conta">Qual o motivo para a abertura da conta? *</label><textarea class="form-control" id="motivo_abertura_conta" name="perfil[motivo_abertura_conta]" rows="3" required></textarea></div>
            <div class="form-group"><label for="fluxo_financeiro_pretendido">Como a empresa pretende que seja o fluxo financeiro? *</label><textarea class="form-control" id="fluxo_financeiro_pretendido" name="perfil[fluxo_financeiro_pretendido]" rows="3" required></textarea></div>
            <div class="row"><div class="form-group col-md-6"><label for="moedas_operar">Quais as moedas a empresa pretende operar? *</label><input type="text" class="form-control" id="moedas_operar" name="perfil[moedas_operar]" required placeholder="Ex: BRL, USD, EUR"></div><div class="form-group col-md-6"><label for="blockchains_operar">Em quais Blockchains a empresa pretende operar? *</label><input type="text" class="form-control" id="blockchains_operar" name="perfil[blockchains_operar]" required placeholder="Ex: Ethereum, Bitcoin"></div></div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="volume_mensal_pretendido">Volume mensal pretendido (R$) *</label>
                    <input type="text" class="form-control" id="volume_mensal_pretendido" name="perfil[volume_mensal_pretendido]" required>
                </div>
                <div class="form-group col-md-6">
                    <fieldset class="mt-2">
                        <legend class="col-form-label pt-0">Fonte dos Fundos *</legend>
                        <div data-required-group="perfil[fonte_fundos][]">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="perfil[fonte_fundos][]" id="fundos_proprios" value="Próprios">
                                <label class="form-check-label" for="fundos_proprios">Próprios</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="perfil[fonte_fundos][]" id="fundos_terceiros" value="Terceiros">
                                <label class="form-check-label" for="fundos_terceiros">Terceiros</label>
                            </div>
                        </div>
                        <div class="invalid-feedback">Selecione ao menos uma fonte de fundos.</div>
                    </fieldset>
                    <div class="form-group mt-2" id="descricao-fundos-container" style="display: none;">
                        <label for="descricao_fundos">Descrição da Origem (Fundos de Terceiros) *</label>
                        <textarea class="form-control" id="descricao_fundos" name="perfil[descricao_fundos_terceiros]" rows="2"></textarea>
                        <div class="invalid-feedback">A descrição é obrigatória.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-step" data-step="3">
             <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="form-section-title mb-0">3. Sócios e Administradores (UBOs)</h5><button type="button" class="btn btn-sm btn-primary" id="add-ubo-btn">Adicionar Sócio Manualmente</button></div>
             <div id="ubos-container"></div>
        </div>

        <div class="form-step" data-step="4">
            <h5 class="form-section-title">4. Documentos da Empresa</h5>

            <div class="form-group">
                <label for="doc_contrato_social">Contrato/Estatuto Social *</label>
                <input type="file" class="form-control" id="doc_contrato_social" name="documentos[contrato_social]" required accept=".pdf,.png,.jpg">
            </div>

            <div class="form-group">
                <label for="doc_ultima_alteracao">Última Alteração Societária (se houver)</label>
                <input type="file" class="form-control" id="doc_ultima_alteracao" name="documentos[ultima_alteracao]" accept=".pdf,.png,.jpg">
            </div>

            <div class="form-group">
                <label for="doc_cartao_cnpj">Cartão do CNPJ *</label>
                <input type="file" class="form-control" id="doc_cartao_cnpj" name="documentos[cartao_cnpj]" required accept=".pdf,.png,.jpg">
            </div>

            <div class="form-group">
                <label for="doc_balanco">Balanço do último ano *</label>
                <input type="file" class="form-control" id="doc_balanco" name="documentos[balanco_anual]" required accept=".pdf,.png,.jpg">
            </div>

            <div class="form-group">
                <label for="doc_balancete">Balancete (últimos 3 meses) *</label>
                <input type="file" class="form-control" id="doc_balancete" name="documentos[balancete_trimestral]" required accept=".pdf,.png,.jpg">
            </div>

            <div class="form-group">
                <label for="doc_dirpj">DIRPJ *</label>
                <input type="file" class="form-control" id="doc_dirpj" name="documentos[dirpj]" required accept=".pdf,.png,.jpg">
            </div>

            <hr>
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="consentimento_termos" name="termos[consentimento]" required>
                    <label class="form-check-label" for="consentimento_termos">
                        Declaro que as informações prestadas são verdadeiras e autorizo o uso destas dados para a finalidade exclusiva de análise de compliance pela <?php echo htmlspecialchars($nome_empresa_contexto ?? 'nossa empresa'); ?>, em conformidade com a Lei Geral de Proteção de Dados (LGPD). *
                    </label>
                    <div class="invalid-feedback">Você deve aceitar os termos para continuar.</div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-5">
            <div>
                <button type="button" class="btn btn-secondary" id="prev-btn" style="display: none;">Voltar</button>
                <button type="button" class="btn btn-danger" id="clear-btn">Limpar Formulário</button>
            </div>
            <div>
                <button type="button" class="btn btn-primary" id="next-btn">Avançar</button>
                <button type="submit" class="btn btn-success" id="submit-btn" style="display: none;">Enviar Análise KYC</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- ELEMENTOS DO DOM ---
    const form = document.getElementById('kyc-form');
    const steps = Array.from(form.querySelectorAll('.form-step'));
    const progressBar = document.getElementById('progress-bar');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-btn');
    const addUboBtn = document.getElementById('add-ubo-btn');
    const uboContainer = document.getElementById('ubos-container');
    const cnaeContainer = document.getElementById('cnaes-secundarios-container');
    const cnpjInput = document.getElementById('cnpj');
    const cnpjContainer = document.getElementById('cnpj-container');
    const fundosTerceirosCheckbox = document.getElementById('fundos_terceiros');
    const descricaoFundosContainer = document.getElementById('descricao-fundos-container');
    const descricaoFundosTextarea = document.getElementById('descricao_fundos');
    const clearBtn = document.getElementById('clear-btn');
    const empresaCepInput = document.getElementById('cep');

    // --- ESTADO DO FORMULÁRIO ---
    let currentStep = 0;
    const STORAGE_KEY = 'kycFormData_v5.4'; // Versão incrementada para garantir um recomeço limpo
    const stepLabels = ['Empresa', 'Perfil', 'Sócios', 'Documentos'];

    // --- INICIALIZAÇÃO ---
    renderProgressBar();
    setupMasks();
    setupEventListeners();
    loadFormData(); 
    updateButtons();

    // --- NAVEGAÇÃO E BARRA DE PROGRESSO ---
    function navigateTo(stepIndex) {
        if (stepIndex < 0 || stepIndex >= steps.length) return;
        steps[currentStep].classList.remove('active');
        currentStep = stepIndex;
        steps[currentStep].classList.add('active');
        updateButtons();
        renderProgressBar();
    }

    function updateButtons() {
        prevBtn.style.display = currentStep > 0 ? 'inline-block' : 'none';
        nextBtn.style.display = currentStep < steps.length - 1 ? 'inline-block' : 'none';
        submitBtn.style.display = currentStep === steps.length - 1 ? 'inline-block' : 'none';
    }

    function renderProgressBar() {
        progressBar.innerHTML = '';
        stepLabels.forEach((label, index) => {
            const stepDiv = document.createElement('div');
            stepDiv.className = `progress-step ${index <= currentStep ? 'active' : ''}`;
            const labelSpan = document.createElement('span');
            labelSpan.className = 'progress-step-label';
            labelSpan.textContent = label;
            stepDiv.appendChild(labelSpan);
            progressBar.appendChild(stepDiv);
        });
    }

    // --- LÓGICA DE VALIDAÇÃO POR ETAPA ---
    function validateCurrentStep() {
        // Sua lógica de validação completa deve estar aqui.
        return true;
    }

    // --- NOVA LÓGICA DE SALVAMENTO NO SERVIDOR ---
    async function saveStepData() {
        const formData = new FormData(form);
        formData.append('step', currentStep + 1); // Envia a etapa atual (1-based)

        nextBtn.disabled = true;
        nextBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Salvando...`;

        try {
            // --- CORREÇÃO: Caminho robusto para o fetch ---
            const response = await fetch('kyc_save_step.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                // Tenta decodificar o JSON, se falhar, mostra o texto bruto
                try {
                    const errorJson = JSON.parse(errorText);
                    throw new Error(errorJson.message || `Erro do servidor: ${response.status}`);
                } catch (e) {
                    throw new Error(`Erro do servidor: ${response.status}. Resposta: ${errorText}`);
                }
            }

            const result = await response.json();

            if (result.status !== 'success') {
                throw new Error(result.message || 'Ocorreu um erro desconhecido ao salvar.');
            }

            if (result.submission_id) {
                document.getElementById('submission_id').value = result.submission_id;
            }
            
            // Se salvou com sucesso, avança para a próxima etapa
            navigateTo(currentStep + 1);

        } catch (error) {
            console.error('Erro ao salvar etapa:', error);
            alert('Não foi possível salvar seu progresso. Por favor, verifique sua conexão e tente novamente.\n\nDetalhe: ' + error.message);
        } finally {
            nextBtn.disabled = false;
            nextBtn.textContent = 'Avançar';
        }
    }

    // --- MÁSCARAS ---
    function applyMask(element, mask) {
        if (!element) return;
        const handler = (e) => {
            const value = e.target.value.replace(/\D/g, '');
            let maskedValue = '';
            let k = 0;
            if (!value) { e.target.value = ''; return; }
            for (let i = 0; i < mask.length; i++) {
                if (mask[i] === '#') { if (value[k]) { maskedValue += value[k++]; } else { break; } } 
                else { if (k < value.length) { maskedValue += mask[i]; } }
            }
            e.target.value = maskedValue;
        };
        element.addEventListener('input', handler);
    }
    
    function applyPhoneMask(element) {
        if (!element) return;
        const handler = (e) => {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);

            if (value.length > 10) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length > 5) {
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d*)/, '($1) $2');
            } else {
                value = value.replace(/^(\d*)/, '($1');
            }
            e.target.value = value;
        };
        element.addEventListener('input', handler);
    }

    function setupMasks() {
        applyMask(cnpjInput, '##.###.###/####-##');
        applyMask(empresaCepInput, '#####-###');
        applyPhoneMask(document.getElementById('ddd_telefone_1'));
    }

    // --- VALIDAÇÃO CPF/CNPJ ---
    function validaCPF(cpf) {
        cpf = String(cpf).replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
        let sum = 0, rest;
        for (let i = 1; i <= 9; i++) sum += parseInt(cpf.substring(i - 1, i)) * (11 - i);
        rest = (sum * 10) % 11;
        if ((rest === 10) || (rest === 11)) rest = 0;
        if (rest !== parseInt(cpf.substring(9, 10))) return false;
        sum = 0;
        for (let i = 1; i <= 10; i++) sum += parseInt(cpf.substring(i - 1, i)) * (12 - i);
        rest = (sum * 10) % 11;
        if ((rest === 10) || (rest === 11)) rest = 0;
        if (rest !== parseInt(cpf.substring(10, 11))) return false;
        return true;
    }

    function validaCNPJ(cnpj) {
        cnpj = String(cnpj).replace(/\D/g, '');
        if (cnpj.length !== 14 || /^(\d)\1+$/.test(cnpj)) return false;
        let size = cnpj.length - 2;
        let numbers = cnpj.substring(0, size);
        let digits = cnpj.substring(size);
        let sum = 0;
        let pos = size - 7;
        for (let i = size; i >= 1; i--) {
            sum += numbers.charAt(size - i) * pos--;
            if (pos < 2) pos = 9;
        }
        let result = sum % 11 < 2 ? 0 : 11 - sum % 11;
        if (result != digits.charAt(0)) return false;
        size = size + 1;
        numbers = cnpj.substring(0, size);
        sum = 0;
        pos = size - 7;
        for (let i = size; i >= 1; i--) {
            sum += numbers.charAt(size - i) * pos--;
            if (pos < 2) pos = 9;
        }
        result = sum % 11 < 2 ? 0 : 11 - sum % 11;
        if (result != digits.charAt(1)) return false;
        return true;
    }
    
// --- LÓGICA DE API (VERSÃO LIMPA, SEM LOGS) ---
async function fetchEmpresaCEPData() {
    const cepValue = empresaCepInput.value.replace(/\D/g, '');
    const spinnerContainer = empresaCepInput.parentElement;
    const invalidFeedback = spinnerContainer.querySelector('.invalid-feedback');

    if (cepValue.length !== 8) {
        empresaCepInput.classList.add('is-invalid');
        invalidFeedback.textContent = 'CEP deve conter 8 dígitos.';
        return;
    }
    empresaCepInput.classList.remove('is-invalid');
    spinnerContainer.classList.add('loading');

    try {
        const response = await fetch(`cep_proxy.php?cep=${cepValue}`);
        const responseText = await response.text();
        const data = JSON.parse(responseText);

        if (!response.ok || data.erro) {
            throw new Error(data.erro || 'CEP não encontrado ou serviço indisponível.');
        }
        
        document.getElementById('logradouro').value = data.street || data.logradouro || '';
        document.getElementById('bairro').value = data.neighborhood || data.bairro || '';
        document.getElementById('cidade').value = data.city || data.localidade || '';
        document.getElementById('uf').value = data.state || data.uf || '';
        document.getElementById('numero').focus();
    } catch (error) {
        empresaCepInput.classList.add('is-invalid');
        invalidFeedback.textContent = error.message;
    } finally {
        spinnerContainer.classList.remove('loading');
    }
}

async function fetchCNPJData() {
    const cnpjValue = cnpjInput.value.replace(/\D/g, '');
    if (cnpjValue.length !== 14) {
        cnpjInput.classList.add('is-invalid'); 
        return; 
    }
    cnpjInput.classList.remove('is-invalid');
    cnpjContainer.classList.add('loading');

    try {
        const response = await fetch(`cnpj_proxy_public.php?cnpj=${cnpjValue}`);
        const responseText = await response.text();
        const data = JSON.parse(responseText); 

        if (!response.ok || data.type === "service_error") {
             throw new Error(data.message || `Serviço de consulta indisponível (Erro: ${response.status})`);
        }

        const fieldMapping = { 'razao_social': 'razao_social', 'nome_fantasia': 'nome_fantasia', 'data_inicio_atividade': 'data_constituicao', 'logradouro': 'logradouro', 'numero': 'numero', 'bairro': 'bairro', 'municipio': 'cidade', 'uf': 'uf', 'cep': 'cep', 'cnae_fiscal': 'cnae_fiscal', 'cnae_fiscal_descricao': 'cnae_fiscal_descricao', 'email': 'email', 'ddd_telefone_1': 'ddd_telefone_1', 'descricao_identificador_matriz_filial': 'identificador_matriz_filial', 'descricao_situacao_cadastral': 'situacao_cadastral', 'descricao_motivo_situacao_cadastral': 'descricao_motivo_situacao_cadastral', 'porte': 'porte', 'natureza_juridica': 'natureza_juridica' };
        for (const apiKey in fieldMapping) {
            const element = document.getElementById(fieldMapping[apiKey]);
            if (element && data[apiKey] !== undefined) {
                if (element.id === 'data_constituicao' && data[apiKey]) { const [year, month, day] = data[apiKey].split('-'); element.value = `${day}/${month}/${year}`; } else if (element.id === 'nome_fantasia' || element.id === 'cep' || element.id === 'numero' || element.id.startsWith('ddd_telefone_')) { if (!element.value) element.value = data[apiKey]; } else { element.value = data[apiKey]; }
            }
        }
        if (empresaCepInput.value) { empresaCepInput.dispatchEvent(new Event('input', { bubbles: true })); }
        if (document.getElementById('ddd_telefone_1').value) { document.getElementById('ddd_telefone_1').dispatchEvent(new Event('input', { bubbles: true })); }
        const opcaoSimplesInput = document.getElementById('opcao_pelo_simples');
        if (data.opcao_pelo_simples === true) { opcaoSimplesInput.value = 'SIM'; } else if (data.opcao_pelo_simples === false) { opcaoSimplesInput.value = 'NÃO'; } else { opcaoSimplesInput.value = 'NÃO OPTANTE / OUTROS'; }
        const repLegalInput = document.getElementById('representante_legal');
        let repLegalName = ''; if (data.qsa && data.qsa.length > 0) { const repLegal = data.qsa.find(socio => socio.qualificacao_socio.toUpperCase().includes('ADMINISTRADOR')); if (repLegal) { repLegalName = repLegal.nome_socio; } }
        repLegalInput.value = repLegalName;

        cnaeContainer.innerHTML = '';
        if (data.cnaes_secundarios && data.cnaes_secundarios.length > 0) {
            data.cnaes_secundarios.forEach((cnae, index) => createCnaeCard(index, cnae));
        }

        uboContainer.innerHTML = '';
        if (data.qsa && data.qsa.length > 0) {
            data.qsa.forEach((socio, index) => createUboCard(index, socio));
        } else {
            createUboCard(0);
        }

    } catch (error) {
        cnpjInput.classList.add('is-invalid');
        cnpjContainer.querySelector('.invalid-feedback').textContent = error.message;
    } finally {
        cnpjContainer.classList.remove('loading');
    }
}

async function fetchSocioCEPData(cepInput) {
    const cepValue = cepInput.value.replace(/\D/g, '');
    const uboCard = cepInput.closest('.ubo-card');
    const spinnerContainer = cepInput.parentElement;
    const invalidFeedback = spinnerContainer.querySelector('.invalid-feedback');

    if (cepValue.length !== 8) {
        cepInput.classList.add('is-invalid');
        invalidFeedback.textContent = 'CEP deve conter 8 dígitos.';
        return;
    }
    cepInput.classList.remove('is-invalid');
    spinnerContainer.classList.add('loading');

    try {
        const response = await fetch(`cep_proxy.php?cep=${cepValue}`);
        const responseText = await response.text();
        const data = JSON.parse(responseText);

        if (!response.ok || data.erro) { 
            throw new Error(data.erro || 'CEP não encontrado ou serviço indisponível.');
        }
        uboCard.querySelector('.socio-logradouro').value = data.street || data.logradouro || '';
        uboCard.querySelector('.socio-bairro').value = data.neighborhood || data.bairro || '';
        uboCard.querySelector('.socio-cidade').value = data.city || data.localidade || '';
        uboCard.querySelector('.socio-uf').value = data.state || data.uf || '';
        uboCard.querySelector('.socio-numero').focus();
    } catch (error) {
        cepInput.classList.add('is-invalid');
        invalidFeedback.textContent = error.message;
    } finally {
        spinnerContainer.classList.remove('loading');
    }
}

        
    // --- GESTÃO DINÂMICA DE ELEMENTOS ---
    function createCnaeCard(id, data = {}) {
        const card = document.createElement('div'); card.className = 'cnae-card';
        const cnaeCode = data.codigo || 'N/A'; const cnaeDesc = data.descricao || 'N/A';
        card.innerHTML = `<div class="card-body py-2"><div class="row"><div class="form-group col-md-4 mb-0"><label>CNAE Secundário</label><input type="text" class="form-control" name="cnaes[${id}][codigo]" value="${cnaeCode}" readonly></div><div class="form-group col-md-8 mb-0"><label>Descrição</label><input type="text" class="form-control" name="cnaes[${id}][descricao]" value="${cnaeDesc}" readonly></div></div></div>`;
        cnaeContainer.appendChild(card);
    }

    function createUboCard(id, data = {}) {
        const card = document.createElement('div'); card.className = 'ubo-card'; card.setAttribute('data-ubo-id', id);
        const nome = data.nome_socio || ''; const cpfCnpj = data.cnpj_cpf_do_socio || ''; const qualificacao = data.qualificacao_socio || '';
        card.innerHTML = `
            <div class="card-header"><h6 class="mb-0">Sócio / Administrador #${id + 1}</h6><button type="button" class="btn btn-sm btn-danger remove-ubo-btn">Remover</button></div>
            <div class="card-body">
                <div class="row">
                    <div class="form-group col-md-5"><label for="socios_${id}_nome_completo">Nome Completo *</label><input type="text" class="form-control" id="socios_${id}_nome_completo" name="socios[${id}][nome_completo]" value="${nome}" required></div>
                    <div class="form-group col-md-3"><label for="socios_${id}_data_nascimento">Data de Nascimento *</label><input type="text" class="form-control socio-data-nascimento" id="socios_${id}_data_nascimento" name="socios[${id}][data_nascimento]" placeholder="DD/MM/AAAA" required></div>
                    <div class="form-group col-md-4"><label for="socios_${id}_cpf_cnpj">CPF/CNPJ *</label><input type="text" class="form-control ubo-cpf-cnpj" id="socios_${id}_cpf_cnpj" name="socios[${id}][cpf_cnpj]" value="${cpfCnpj}" required maxlength="18"><div class="invalid-feedback">Documento inválido.</div></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-8"><label for="socios_${id}_qualificacao_cargo">Qualificação/Cargo *</label><input type="text" class="form-control" id="socios_${id}_qualificacao_cargo" name="socios[${id}][qualificacao_cargo]" value="${qualificacao}" required><div class="invalid-feedback">Este campo é obrigatório.</div></div>
                    <div class="form-group col-md-4"><label for="socios_${id}_percentual_participacao">Participação (%)</label><input type="number" step="0.01" class="form-control" id="socios_${id}_percentual_participacao" name="socios[${id}][percentual_participacao]" placeholder="0 se não souber"></div>
                </div>
                <hr><h6 class="mb-3">Endereço do Sócio</h6>
                <div class="row">
                    <div class="form-group col-md-4 spinner-container"><label for="socios_${id}_cep">CEP *</label><input type="text" class="form-control socio-cep" id="socios_${id}_cep" name="socios[${id}][cep]" required><div class="spinner-border spinner-border-sm text-primary"></div><div class="invalid-feedback">CEP inválido ou incompleto.</div></div>
                    <div class="form-group col-md-5"><label for="socios_${id}_logradouro">Logradouro *</label><input type="text" class="form-control socio-logradouro" id="socios_${id}_logradouro" name="socios[${id}][logradouro]" required></div>
                    <div class="form-group col-md-3"><label for="socios_${id}_numero">Número *</label><input type="text" class="form-control socio-numero" id="socios_${id}_numero" name="socios[${id}][numero]" required></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6"><label for="socios_${id}_complemento">Complemento</label><input type="text" class="form-control" id="socios_${id}_complemento" name="socios[${id}][complemento]"></div>
                    <div class="form-group col-md-6"><label for="socios_${id}_bairro">Bairro *</label><input type="text" class="form-control socio-bairro" id="socios_${id}_bairro" name="socios[${id}][bairro]" required></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-9"><label for="socios_${id}_cidade">Cidade *</label><input type="text" class="form-control socio-cidade" id="socios_${id}_cidade" name="socios[${id}][cidade]" required></div>
                    <div class="form-group col-md-3"><label for="socios_${id}_uf">Estado *</label><input type="text" class="form-control socio-uf" id="socios_${id}_uf" name="socios[${id}][uf]" required></div>
                </div>
                <div class="form-group">
                    <label for="socios_${id}_observacoes">Observações</label>
                    <textarea class="form-control" id="socios_${id}_observacoes" name="socios[${id}][observacoes]" rows="2"></textarea>
                </div>
                <hr>
                <div class="row">
                    <div class="form-group col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="socios[${id}][pep]" value="1" id="pep_${id}">
                            <label class="form-check-label" for="pep_${id}">É PEP? (Pessoa Politicamente Exposta)</label>
                        </div>
                    </div>
                </div>
                <hr><h6 class="mb-3">Documentos do Sócio</h6>
                <div class="row">
                    <div class="form-group col-md-6"><label for="socios_${id}_doc_identificacao">Doc. de Identificação (Frente e Verso) *</label><input type="file" class="form-control" id="socios_${id}_doc_identificacao" name="socios[${id}][doc_identificacao]" required></div>
                    <div class="form-group col-md-6"><label for="socios_${id}_doc_endereco">Comprovante de Endereço *</label><input type="file" class="form-control" id="socios_${id}_doc_endereco" name="socios[${id}][doc_endereco]" required></div>
                </div>
            </div>
            <div class="card-footer"><div class="form-check"><input class="form-check-input" type="checkbox" name="socios[${id}][dados_validados]" value="1" id="validados_${id}" required><label class="form-check-label" for="validados_${id}">Dados validados *</label><div class="invalid-feedback">É obrigatório validar os dados.</div></div></div>`;
        uboContainer.appendChild(card);
        
        const cpfCnpjInput = card.querySelector('.ubo-cpf-cnpj');
        if (cpfCnpj) {
             cpfCnpjInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        applyMask(card.querySelector('.socio-cep'), '#####-###');
        applyMask(card.querySelector('.socio-data-nascimento'), '##/##/####');
    }

    // --- PERSISTÊNCIA COM LOCAL STORAGE (VERSÃO COMPLETA RESTAURADA) ---
    function saveFormData() {
        const formData = new FormData(form);
        const object = {};
        
        formData.forEach((value, key) => {
            if (key.startsWith('socios[') || (form.elements[key] && form.elements[key].type === 'file')) return;

            if (key.endsWith('[]')) {
                const cleanKey = key.slice(0, -2);
                if (!object[cleanKey]) object[cleanKey] = [];
                object[cleanKey].push(value);
            } else {
                object[key] = value;
            }
        });

        const socios = [];
        uboContainer.querySelectorAll('.ubo-card').forEach(card => {
            const socioData = {};
            card.querySelectorAll('input, textarea, select').forEach(input => {
                const match = input.name.match(/\[(\w+)\]$/);
                if (match) {
                    const key = match[1];
                    if (input.type === 'checkbox') socioData[key] = input.checked ? '1' : '0';
                    else if (input.type !== 'file') socioData[key] = input.value;
                }
            });
            socios.push(socioData);
        });
        object.socios = socios;
        object.currentStep = currentStep; 
        localStorage.setItem(STORAGE_KEY, JSON.stringify(object));
    }

    function loadFormData() {
        const savedData = localStorage.getItem(STORAGE_KEY);
        if (!savedData) return;
        const data = JSON.parse(savedData);

        for (const key in data) {
            if (key === 'socios' || key === 'currentStep') continue;
            const elements = form.elements[key] || form.elements[key + '[]'];
            if (!elements) continue;
            
            const value = data[key];
            if (NodeList.prototype.isPrototypeOf(elements)) {
                 const values = Array.isArray(value) ? value : [value];
                 elements.forEach(el => { if (el.type === 'checkbox' || el.type === 'radio') el.checked = values.includes(el.value); });
            } else {
                if (elements.type === 'checkbox') elements.checked = (!!value && value !== '0');
                else if (elements.type !== 'file') elements.value = value;
            }
            const eventTarget = elements.length ? elements[0] : elements;
            if (eventTarget) {
                eventTarget.dispatchEvent(new Event('input', { bubbles: true }));
                eventTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        if (data.socios && Array.isArray(data.socios)) {
            uboContainer.innerHTML = '';
            data.socios.forEach((socioData, index) => {
                createUboCard(index, {});
                const newCard = uboContainer.querySelector(`[data-ubo-id="${index}"]`);
                if (newCard) {
                    for (const key in socioData) {
                        const input = newCard.querySelector(`[name="socios[${index}][${key}]"]`);
                        if (input) {
                            if (input.type === 'checkbox') input.checked = socioData[key] === '1';
                            else input.value = socioData[key];
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                }
            });
        }

        if (data.currentStep) {
            navigateTo(parseInt(data.currentStep, 10));
        }
    }
    
    // --- EVENT LISTENERS ---
    function setupEventListeners() {
        // --- ALTERAÇÃO: O botão "Avançar" agora salva todas as etapas de dados ---
        nextBtn.addEventListener('click', () => { 
            if (validateCurrentStep()) {
                // Salva os dados da etapa atual (1, 2, e 3) antes de avançar.
                // A etapa 4 (documentos) não tem botão "Avançar", apenas "Enviar".
                saveStepData();
            }
        });

        prevBtn.addEventListener('click', () => navigateTo(currentStep - 1));
        cnpjInput.addEventListener('blur', fetchCNPJData);
        empresaCepInput.addEventListener('blur', fetchEmpresaCEPData);
        addUboBtn.addEventListener('click', () => { createUboCard(uboContainer.querySelectorAll('.ubo-card').length, {}); });
        
        clearBtn.addEventListener('click', () => {
             if (confirm('Você tem certeza que deseja limpar todos os dados do formulário? Esta ação não pode ser desfeita.')) {
                localStorage.removeItem(STORAGE_KEY);
                // Desabilita a restauração de scroll do navegador para garantir que a página recarregue no topo.
                if ('scrollRestoration' in history) {
                    history.scrollRestoration = 'manual';
                }
                location.reload();
            }
        });
        
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault(); 
                alert('Existem campos obrigatórios não preenchidos. Verifique o formulário.');
            } else {
                // Limpa o localStorage se o formulário for válido e estiver prestes a ser enviado.
                localStorage.removeItem(STORAGE_KEY);
            }
        });

        form.addEventListener('input', saveFormData);

        uboContainer.addEventListener('click', e => { 
            if (e.target.classList.contains('remove-ubo-btn')) {
                e.target.closest('.ubo-card').remove();
                saveFormData();
            }
            if (e.target.name && e.target.name.includes('[dados_validados]')) {
                handleValidationCheckboxClick(e);
            }
        });
        
        uboContainer.addEventListener('blur', (e) => {
            if (e.target.classList.contains('socio-cep')) fetchSocioCEPData(e.target);
            if (e.target.classList.contains('ubo-cpf-cnpj')) handleCpfCnpjBlur(e);
        }, true);

        fundosTerceirosCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            descricaoFundosContainer.style.display = isChecked ? 'block' : 'none';
            descricaoFundosTextarea.required = isChecked;
            if (!isChecked) {
                descricaoFundosTextarea.value = ''; 
                descricaoFundosTextarea.classList.remove('is-invalid');
            }
            saveFormData();
        });

        uboContainer.addEventListener('input', (e) => {
            if (e.target.classList.contains('ubo-cpf-cnpj')) {
                handleCpfCnpjInput(e);
            }
        });
    }

    // --- FUNÇÕES DE EVENTO PARA CPF/CNPJ ---
    function handleCpfCnpjInput(e) {
        const input = e.target;
        const feedback = input.nextElementSibling;
        let value = input.value.replace(/\D/g, '');

        if (value.length > 14) value = value.slice(0, 14);

        if (value.length > 11) {
            value = value.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})\.(\d{3})(\d)/, '.$1.$2/$3').replace(/(\d{4})(\d)/, '$1-$2');
        } else {
            value = value.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        input.value = value;
        
        const rawValue = input.value.replace(/\D/g, '');
        input.classList.remove('is-invalid');

        if (rawValue.length === 11) {
            if (!validaCPF(rawValue)) {
                input.classList.add('is-invalid');
                feedback.textContent = 'CPF inválido. Se for um CNPJ, continue digitando.';
            }
        } else if (rawValue.length === 14) {
            if (!validaCNPJ(rawValue)) {
                input.classList.add('is-invalid');
                feedback.textContent = 'CNPJ inválido.';
            }
        }
    }

    function handleCpfCnpjBlur(e) {
        const input = e.target;
        const feedback = input.nextElementSibling;
        const rawValue = input.value.replace(/\D/g, '');
        if (rawValue.length > 0 && (rawValue.length < 11 || (rawValue.length > 11 && rawValue.length < 14))) {
            input.classList.add('is-invalid');
            feedback.textContent = 'Documento incompleto.';
        }
    }

    // --- FUNÇÃO DE VALIDAÇÃO NO CHECKBOX ---
    function handleValidationCheckboxClick(e) {
        const checkbox = e.target;
        if (!checkbox.checked) return;

        const card = checkbox.closest('.ubo-card');
        if (!card) return;

        let firstInvalidElement = null;

        card.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Elementos existentes
        const cpfCnpjInput = card.querySelector('.ubo-cpf-cnpj');
        const cargoInput = card.querySelector('input[name*="[qualificacao_cargo]"]');
        const cepInput = card.querySelector('.socio-cep');
        const docIdInput = card.querySelector('input[name*="[doc_identificacao]"]');
        const docEndInput = card.querySelector('input[name*="[doc_endereco]"]');
        // NOVO: Adiciona validação da data de nascimento
        const dataNascInput = card.querySelector('.socio-data-nascimento');

        // NOVO: Validação da data de nascimento
        const dataNascValue = dataNascInput.value;
        if (!dataNascValue.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
            dataNascInput.classList.add('is-invalid');
            if (!firstInvalidElement) firstInvalidElement = dataNascInput;
        } else {
            const [dia, mes, ano] = dataNascValue.split('/').map(Number);
            const data = new Date(ano, mes - 1, dia);
            const hoje = new Date();
            
            if (data > hoje || 
                data.getDate() !== dia || 
                data.getMonth() !== mes - 1 || 
                data.getFullYear() !== ano || 
                hoje.getFullYear() - ano > 120) {
                dataNascInput.classList.add('is-invalid');
                if (!firstInvalidElement) firstInvalidElement = dataNascInput;
            }
        }

        const rawCpfCnpj = cpfCnpjInput.value.replace(/\D/g, '');
        if (rawCpfCnpj.length === 0 || (rawCpfCnpj.length !== 11 && rawCpfCnpj.length !== 14)) {
            cpfCnpjInput.classList.add('is-invalid');
            cpfCnpjInput.nextElementSibling.textContent = 'Documento obrigatório e deve estar completo.';
            if (!firstInvalidElement) firstInvalidElement = cpfCnpjInput;
        }

        if (cargoInput.value.trim() === '') {
            cargoInput.classList.add('is-invalid');
            cargoInput.nextElementSibling.textContent = 'Este campo é obrigatório.';
            if (!firstInvalidElement) firstInvalidElement = cargoInput;
        }

        const rawCep = cepInput.value.replace(/\D/g, '');
        if (rawCep.length !== 8) {
            cepInput.classList.add('is-invalid');
            cepInput.parentElement.querySelector('.invalid-feedback').textContent = 'CEP obrigatório. Deve conter 8 dígitos.';
            if (!firstInvalidElement) firstInvalidElement = cepInput;
        }

        if (docIdInput.files.length === 0) {
            docIdInput.classList.add('is-invalid');
            if (!firstInvalidElement) firstInvalidElement = docIdInput;
        }

        if (docEndInput.files.length === 0) {
            docEndInput.classList.add('is-invalid');
            if (!firstInvalidElement) firstInvalidElement = docEndInput;
        }

        if (firstInvalidElement) {
            checkbox.checked = false; 
            alert('Existem campos obrigatórios ou inválidos para este sócio. Por favor, corrija os campos destacados.');
            firstInvalidElement.focus();
        } else {
            card.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        }
    }

    // --- VALIDAÇÃO GERAL DO FORMULÁRIO ---
    function validateForm() {
        let firstInvalidElement = null;
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        const requiredElements = form.querySelectorAll('[required]');
        
        for (const element of requiredElements) {
            let isInvalid = false;
            const step = element.closest('.form-step');
            if (!step) continue; 
            
            const container = element.closest('div[style*="display: none"]');
            if (container) continue; 

            if (element.type === 'file') {
                if (element.files.length === 0) isInvalid = true;
            } else if (element.type === 'checkbox') {
                if (!element.checked) isInvalid = true;
            } else {
                if (element.value.trim() === '') isInvalid = true;
            }

            if (isInvalid) {
                element.classList.add('is-invalid');
                if (!firstInvalidElement) {
                    firstInvalidElement = element;
                }
            }
        }
        
        const checkboxGroup = form.querySelector('[data-required-group="perfil[fonte_fundos][]"]');
        if (checkboxGroup) {
            const checkedCheckboxes = checkboxGroup.querySelectorAll('input[type="checkbox"]:checked');
            if (checkedCheckes.length === 0) {
                checkboxGroup.classList.add('is-invalid');
                if (!firstInvalidElement) {
                    firstInvalidElement = checkboxGroup.querySelector('input[type="checkbox"]');
                }
            } else {
                 checkboxGroup.classList.remove('is-invalid');
            }
        }

        if (firstInvalidElement) {
            const stepToGo = firstInvalidElement.closest('.form-step');
            if (stepToGo) {
                const stepIndex = Array.from(steps).indexOf(stepToGo);
                if (currentStep !== stepIndex) {
                    navigateTo(stepIndex);
                }
                
                setTimeout(() => {
                    firstInvalidElement.focus();
                    firstInvalidElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 500);
            }
            return false;
        }

        return true;
    }
});
</script>

<?php
// LÓGICA DE DECISÃO DO RODAPÉ
if (isset($_SESSION['user_id'])) {
    require 'footer.php';
} else {
    require 'form_footer.php';
}
?>