<?php
// bootstrap.php - O "Cérebro" da Aplicação

// --- PASSO DE DIAGNÓSTICO ---
// Descomente a linha abaixo para testar se este arquivo está sendo executado.
// Se você vir a mensagem "Bootstrap está sendo executado", o arquivo está funcionando.
// die("Bootstrap está sendo executado");

// --- 0. MODO DE DEBUG ---
// Altere para true para exibir erros na tela durante o desenvolvimento.
// NUNCA deixe como true em produção.
define('DEBUG_MODE', true); 

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

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

$public_pages = [
    'login.php', 
    'kyc_form.php', 
    'kyc_submit.php', 
    'kyc_thank_you.php', 
    'api_receive_kyc.php', 
    'forgot_password.php', 
    'reset_password.php',
    // Páginas do Portal do Cliente
    'cliente_login.php',
    'cliente_registro.php',
    'cliente_verificacao.php'
];

// Se a página não for pública e nenhum usuário (admin ou cliente) estiver logado, redireciona.
if (!isset($_SESSION['user_id']) && !isset($_SESSION['cliente_id']) && !in_array($current_page_base, $public_pages)) {
    header('Location: login.php');
    exit;
}

// --- 4. CONTEXTO DO USUÁRIO LOGADO (ADMINISTRATIVO) ---

// Define valores padrão para as variáveis de usuário e branding
$is_logged_in = false;
$user_role = 'guest';
$is_superadmin = false;
$is_admin = false;
$is_analista = false;
$user_nome = 'Visitante';
$user_empresa_id = null;

// Define valores padrão para o branding
$nome_empresa = 'Sua Plataforma';
$logo_url = 'uploads/logos/68df0c7e712ff-verify-kyc.png'; // Logo padrão
$cor_variavel = '#222b5a'; // Cor padrão

// Aplica a hierarquia de branding e contexto do usuário
if (isset($_SESSION['user_id'])) {
    // O usuário administrativo está logado
    $is_logged_in = true;
    $user_id = $_SESSION['user_id'];
    $user_role = trim(strtolower($_SESSION['user_role'] ?? 'usuario'));

    // Busca o empresa_id diretamente do banco a cada requisição para garantir consistência.
    if ($user_role !== 'superadmin') {
        $stmt_user_company = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE id = ?");
        $stmt_user_company->execute([$user_id]);
        $user_company_data = $stmt_user_company->fetch(PDO::FETCH_ASSOC);
        $user_empresa_id = $user_company_data['empresa_id'] ?? null;
        $_SESSION['empresa_id'] = $user_empresa_id; // Atualiza a sessão
    }
    
    // A marca da sessão do usuário logado sempre tem preferência.
    $nome_empresa = $_SESSION['nome_empresa'] ?? $nome_empresa;
    $logo_url = $_SESSION['logo_url'] ?? $logo_url;
    $cor_variavel = $_SESSION['cor_variavel'] ?? $cor_variavel;

    // Define as variáveis de permissão
    $is_superadmin = ($user_role === 'superadmin');
    $is_admin = ($user_role === 'admin' || $user_role === 'administrador');
    $is_analista = ($user_role === 'analista');
    $user_nome = $_SESSION['user_nome'] ?? 'Usuário';

} else if (!empty($logo_url_contexto)) {
    // Usuário não logado, mas existe um contexto de parceiro (vinda do slug).
    $nome_empresa = $nome_empresa_contexto;
    $logo_url = $logo_url_contexto;
    $cor_variavel = $cor_primaria_contexto;
}
// Se nenhuma das condições acima for atendida, as variáveis padrão definidas no início serão usadas.

// --- 5. CONTEXTO DO CLIENTE LOGADO ---
$is_cliente_logged_in = false;
if (isset($_SESSION['cliente_id'])) {
    $is_cliente_logged_in = true;
    $cliente_id = $_SESSION['cliente_id'];
    $cliente_nome = $_SESSION['cliente_nome'];
}

// --- 6. LÓGICA DE CAMINHOS E TÍTULO DA PÁGINA ---
$path_prefix = '';
$script_depth = substr_count(realpath($_SERVER['SCRIPT_FILENAME']), DIRECTORY_SEPARATOR);
$root_depth = substr_count(realpath(__DIR__), DIRECTORY_SEPARATOR);
if ($script_depth > $root_depth) {
    $path_prefix = str_repeat('../', $script_depth - $root_depth);
}
// A variável $page_title deve ser definida na página ANTES de incluir o bootstrap.php
$page_title_final = $page_title ?? $nome_empresa;

?>