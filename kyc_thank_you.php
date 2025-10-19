<?php
session_start();
require 'config.php'; // Inclui a conexão com o banco de dados

// --- LÓGICA DE BRANDING PARA A PÁGINA DE AGRADECIMENTO ---

// Pega o ID do parceiro que foi salvo na sessão pelo kyc_submit.php
$partner_id = $_SESSION['thank_you_partner_id'] ?? null;

// Valores padrão
$logo_url = 'imagens/fdbank.svg'; // Seu logo principal como fallback
$nome_empresa_parceira = 'Nossa Plataforma';
$cor_primaria = '#4f46e5';

// Se um ID de parceiro foi passado, busca as configurações de branding dele
if ($partner_id) {
    $stmt = $pdo->prepare("SELECT nome_empresa, logo_url, cor_variavel FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$partner_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $logo_url = $config['logo_url'] ?: $logo_url;
        $nome_empresa_parceira = $config['nome_empresa'];
        $cor_primaria = $config['cor_variavel'] ?: $cor_primaria;
    }
}

// Prepara as variáveis que o header.php irá usar
$page_title = 'Obrigado por seu Envio';
$cor_variavel = $cor_primaria;
// A variável $logo_url já está definida com o logo correto (do parceiro ou o padrão)
$nome_empresa = $nome_empresa_parceira;

// Inclui o header, que agora exibirá a marca correta
require 'header.php';

// --- CONTEÚDO DA PÁGINA ---

// Busca o ID da submissão e a mensagem da sessão
$submission_id = $_SESSION['submission_id'] ?? null;
$flash_message = $_SESSION['flash_message'] ?? 'Seu formulário foi enviado com sucesso e está em análise.';

// Limpa as variáveis da sessão para não persistirem mais
unset($_SESSION['submission_id']);
unset($_SESSION['flash_message']);
unset($_SESSION['thank_you_partner_id']); // Limpa o ID do parceiro
?>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm text-center">
    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo da Empresa" style="max-width: 150px; margin-bottom: 2rem; background-color: #f8f9fa; padding: 10px; border-radius: 5px;">
    
    <h2 class="mb-3" style="color: <?= htmlspecialchars($cor_primaria); ?>;">Formulário Enviado com Sucesso!</h2>
    
    <p class="lead text-muted"><?php echo htmlspecialchars($flash_message); ?></p>
    
    <?php if ($submission_id): ?>
        <p><strong>Número da sua submissão: #<?php echo htmlspecialchars($submission_id); ?></strong></p>
    <?php endif; ?>
    
    <hr>
    
    <p>Nossa equipe de compliance irá analisar as informações e os documentos enviados.</p>
    <p>Entraremos em contato em breve pelo e-mail cadastrado no formulário.</p>
    
    <div class="mt-5">
        <?php if (isset($_SESSION['user_id'])): // Se o usuário estiver logado, o botão leva para o dashboard ?>
            <a href="dashboard.php" class="btn btn-primary">Voltar ao Painel</a>
        <?php else: // Se não estiver logado, o botão pode levar para a página de login ou outra página pública ?>
            <a href="login.php" class="btn btn-primary">Ir para o Login</a>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
