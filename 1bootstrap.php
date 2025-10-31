<?php
// bootstrap.php - O "Cérebro" da Aplicação

// --- 1. CONFIGURAÇÃO ESSENCIAL ---

// Garante que a sessão seja iniciada apenas uma vez, de forma segura.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carrega as configurações do banco de dados e outras constantes.
require_once __DIR__ . '/config.php';
// Assumindo que a lógica de whitelabel está em um arquivo separado, conforme prompts anteriores.
if (file_exists('whitelabel_logic.php')) {
    require_once 'whitelabel_logic.php';
}

// --- LÓGICA DE USUÁRIO LOGADO E PERMISSÕES ---
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_role = strtolower($_SESSION['user_role'] ?? '');
$user_nome = $_SESSION['nome'] ?? 'Usuário';
$user_empresa_id = $_SESSION['empresa_id'] ?? null;

$is_superadmin = ($user_role === 'superadmin');
$is_admin = in_array($user_role, ['admin', 'administrador']);
$is_analista = ($user_role === 'analista');

// --- LÓGICA DE PÁGINA ATUAL E TÍTULO ---
$current_page_base = basename($_SERVER['PHP_SELF']);
$page_title_final = ($page_title ?? 'Painel') . ' - ' . ($nome_empresa_contexto ?? 'Verify KYC');

// --- LÓGICA DE BRANDING (da sessão para o header) ---
$logo_url = $_SESSION['logo_url'] ?? ($logo_url_contexto ?? 'imagens/verify-kyc.png');
$cor_variavel = $_SESSION['cor_variavel'] ?? ($cor_variavel_contexto ?? '#4f46e5');

// --- LÓGICA DE PREFIXO DE CAMINHO ---
// Define um prefixo para os links, garantindo que funcionem em qualquer lugar.
$path_prefix = ''; // Deixe em branco se estiver na raiz do servidor web.
                   // Se estiver em uma subpasta, ex: '/consulta_cnpj/', ajuste aqui.
?>