<?php
// Esse arquivo é incluído dentro do cliente_dashboard.php

// CORREÇÃO: A verificação agora inclui o 'cliente_email', que é garantido pelo novo 'cliente_login.php'.
if (!isset($pdo) || !isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_email'])) {
    // Esta mensagem de erro agora só aparecerá se houver um problema real de sessão.
    echo "<p class='text-danger'>Erro: Sessão do cliente inválida. Por favor, faça o login novamente.</p>";
    return;
}

// Variáveis para o status do KYC.
$kyc_status = null;
$data_submissao = null;

try {
    // LÓGICA SIMPLIFICADA E CORRETA: Usa o e-mail do cliente diretamente da sessão.
    $cliente_email = $_SESSION['cliente_email'];

    // Busca a submissão mais recente na tabela kyc_empresas que corresponda ao e-mail de contato do cliente logado.
    $stmt_kyc = $pdo->prepare("SELECT status, data_submissao FROM kyc_empresas WHERE email_contato = ? ORDER BY data_submissao DESC LIMIT 1");
    $stmt_kyc->execute([$cliente_email]);
    $submissao = $stmt_kyc->fetch(PDO::FETCH_ASSOC);

    if ($submissao) {
        $kyc_status = $submissao['status'];
        $data_submissao = new DateTime($submissao['data_submissao']);
    }

} catch (Exception $e) {
    error_log("Erro ao buscar status do KYC para o E-MAIL {$cliente_email}: " . $e->getMessage());
    echo "<p class='text-danger'>Ocorreu um erro ao verificar o status do seu formulário. Por favor, tente novamente mais tarde.</p>";
    return; // Para a execução do script se houver erro
}

// A URL para o formulário KYC já é montada no escopo principal (cliente_dashboard.php) e usa a variável $slug_contexto.
// Vamos garantir que ela seja usada aqui corretamente.
$kyc_form_url = 'kyc_form.php';
if (!empty($slug_contexto)) {
    $kyc_form_url .= '?cliente=' . urlencode($slug_contexto);
}
?>

<div class="kyc-status-section">
    <?php if (!$kyc_status): // Caso 1: Nenhuma submissão encontrada ?>
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Ação Requerida</h5>
                <p class="card-text">Para ativar completamente sua conta e ter acesso a todos os nossos recursos, precisamos que você preencha o formulário de "Conheça Seu Cliente" (KYC).</p>
                <p class="card-text"><small class="text-muted">Este é um procedimento de segurança padrão para garantir a conformidade e a segurança de nossos clientes.</small></p>
                <a href="<?= htmlspecialchars($kyc_form_url) ?>" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-file-alt me-2"></i> Iniciar Preenchimento do KYC
                </a>
            </div>
        </div>

    <?php else: // Caso 2: Já existe uma submissão ?>
        <div class="card">
            <div class="card-header">
                Status do seu Formulário KYC
            </div>
            <div class="card-body">
                <h5 class="card-title">Formulário Recebido</h5>
                <p>Recebemos suas informações com sucesso no dia <strong><?= htmlspecialchars($data_submissao->format('d/m/Y \à\s H:i')) ?></strong>.</p>
                <p>O status atual da sua análise é: 
                    <span class="badge bg-info text-white p-2">
                        <strong><?= htmlspecialchars(ucfirst($kyc_status)) ?></strong>
                    </span>
                </p>
                <p class="mt-4">Nossa equipe de compliance está analisando os dados e documentos enviados. Você será notificado por e-mail sobre qualquer atualização ou se informações adicionais forem necessárias.</p>
                <p class="text-muted"><small>Agradecemos a sua colaboração.</small></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Os estilos foram mantidos como antes */
    .kyc-status-section .card {
        border-left: 4px solid var(--primary-color, #4f46e5);
    }
    .btn-primary {
        background-color: var(--primary-color, #4f46e5);
        border-color: var(--primary-color, #4f46e5);
    }
    .btn-primary:hover {
        opacity: 0.9;
        background-color: var(--primary-color, #4f46e5);
        border-color: var(--primary-color, #4f46e5);
    }
    .badge.bg-info {
        font-size: 1em;
    }
</style>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
