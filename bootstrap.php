<?php
// bootstrap.php - O "Cérebro" da Aplicação

// --- 1. CONFIGURAÇÃO ESSENCIAL ---

// Garante que a sessão seja iniciada apenas uma vez, de forma segura.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega as configurações do banco de dados e outras constantes.
require_once __DIR__ . '/config.php';

// --- 2. CONTEXTO DA PÁGINA E WHITELABEL ---

// Carrega a lógica que define as variáveis de whitelabel (logo, cor, etc.)
require_once __DIR__ . '/whitelabel_logic.php';

// Identifica a página atual para o menu ativo e lógica de acesso
$current_page_base = basename($_SERVER['PHP_SELF']);

// --- 3. AUTENTICAÇÃO E AUTORIZAÇÃO ---

$public_pages = ['login.php', 'kyc_form.php', 'kyc_submit.php', 'kyc_thank_you.php', 'api_receive_kyc.php', 'forgot_password.php', 'reset_password.php'];

// Se a página não for pública e o usuário não estiver logado, redireciona.
if (!isset($_SESSION['user_id']) && !in_array($current_page_base, $public_pages)) {
    header('Location: login.php');
    exit;
}

// --- 4. CONTEXTO DO USUÁRIO LOGADO ---

// Define valores padrão para as variáveis de usuário e branding
$is_logged_in = false;
$user_role = 'guest';
$is_superadmin = false;
$is_admin = false;
$is_analista = false;
$user_nome = 'Visitante';

// Define valores padrão para o branding
$nome_empresa = 'Sua Plataforma';
$logo_url = 'uploads/logos/68df0c7e712ff-verify-kyc.png'; // Logo padrão
$cor_variavel = '#222b5a'; // Cor padrão

// Agora, aplicamos a hierarquia de branding e contexto do usuário
if (isset($_SESSION['user_id'])) {
    // O usuário está logado
    $is_logged_in = true;
    $user_id = $_SESSION['user_id'];
    $user_role = trim(strtolower($_SESSION['user_role'] ?? 'usuario'));

    // --- CORREÇÃO DEFINITIVA: Garante que o empresa_id esteja sempre correto ---
    // Busca o empresa_id diretamente do banco a cada requisição para garantir consistência.
    if ($user_role !== 'superadmin') {
        $stmt_user_company = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
        $stmt_user_company->execute([$user_id]);
        $user_company_data = $stmt_user_company->fetch(PDO::FETCH_ASSOC);
        $user_empresa_id = $user_company_data['empresa_id'] ?? null;
        // Atualiza a sessão para garantir que esteja sempre correta para as próximas páginas.
        $_SESSION['empresa_id'] = $user_empresa_id;
    } else {
        $user_empresa_id = null; // Superadmin não tem empresa_id
    }
    
    // PRIORIDADE 1: A marca da sessão do usuário logado sempre tem preferência.
    $nome_empresa = $_SESSION['nome_empresa'] ?? $nome_empresa;
    $logo_url = $_SESSION['logo_url'] ?? $logo_url;
    $cor_variavel = $_SESSION['cor_variavel'] ?? $cor_variavel;

    // Define as variáveis de permissão
    $is_superadmin = ($user_role === 'superadmin');
    $is_admin = ($user_role === 'admin' || $user_role === 'administrador');
    $is_analista = ($user_role === 'analista');
    $user_nome = $_SESSION['user_nome'] ?? 'Usuário';

} else if (!empty($logo_url_contexto)) {
    // PRIORIDADE 2: Usuário não logado, mas existe um contexto de parceiro (vinda do slug).
    $nome_empresa = $nome_empresa_contexto;
    $logo_url = $logo_url_contexto;
    $cor_variavel = $cor_primaria_contexto;
}
// PRIORIDADE 3 (implícita): Se nenhuma das condições acima for atendida, as variáveis padrão definidas no início serão usadas.

// --- 5. LÓGICA DE CAMINHOS E TÍTULO DA PÁGINA ---
$path_prefix = '';
$script_depth = substr_count(realpath($_SERVER['SCRIPT_FILENAME']), DIRECTORY_SEPARATOR);
$root_depth = substr_count(realpath(__DIR__), DIRECTORY_SEPARATOR);
if ($script_depth > $root_depth) {
    $path_prefix = str_repeat('../', $script_depth - $root_depth);
}
// A variável $page_title deve ser definida na página ANTES de incluir o bootstrap.php
$page_title_final = $page_title ?? $nome_empresa;

?>
