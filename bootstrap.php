<?php
// bootstrap.php - O "Cérebro" da Aplicação

// --- 1. CONFIGURAÇÃO ESSENCIAL ---

// Garante que a sessão seja iniciada apenas uma vez, de forma segura.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega as configurações do banco de dados.
require_once __DIR__ . '/config.php';

// --- 2. LÓGICA DE USUÁRIO LOGADO E PERMISSÕES (MOVEMOS PARA CIMA) ---
// Define as variáveis do usuário a partir da sessão ANTES de incluir outros scripts.
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_role = strtolower($_SESSION['user_role'] ?? '');
$user_nome = $_SESSION['nome'] ?? 'Usuário';
$user_empresa_id = $_SESSION['empresa_id'] ?? null;

// Variáveis de permissão para facilitar a verificação.
$is_superadmin = ($user_role === 'superadmin');
$is_admin = in_array($user_role, ['admin', 'administrador']);
$is_analista = ($user_role === 'analista');

// --- 3. LÓGICA DE WHITELABEL ---
// Agora que as variáveis de usuário existem, podemos carregar a lógica de whitelabel.
if (file_exists('whitelabel_logic.php')) {
    require_once 'whitelabel_logic.php';
}

// --- 4. LÓGICA DE PÁGINA E BRANDING ---
$current_page_base = basename($_SERVER['PHP_SELF']);

// Define o título da página, usando o contexto do whitelabel se existir.
$page_title_final = ($page_title ?? 'Painel') . ' - ' . ($nome_empresa_contexto ?? 'Verify KYC');

// Define o logo e a cor para o cabeçalho, usando o contexto do whitelabel como fallback.
$logo_url = $_SESSION['logo_url'] ?? ($logo_url_contexto ?? 'imagens/verify-kyc.png');
$cor_variavel = $_SESSION['cor_variavel'] ?? ($cor_variavel_contexto ?? '#4f46e5');

// --- 5. LÓGICA DE PREFIXO DE CAMINHO ---
$path_prefix = ''; // Ajuste se o projeto estiver em uma subpasta.

?>
