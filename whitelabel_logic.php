<?php
// whitelabel_logic.php (VERSÃO FINAL E ROBUSTA)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

// --- INICIALIZAÇÃO DAS VARIÁVEIS DE CONTEXTO ---
// Garante que as variáveis sempre existam para evitar erros.
$nome_empresa_contexto = null;
$logo_url_contexto = null;
$cor_primaria_contexto = null;
$id_empresa_master_contexto = null;
$slug_contexto = null;
$is_superadmin_on_kyc = false; // Flag para o seletor do superadmin.

// --- LÓGICA DE DECISÃO DE MARCA ---
$slug_na_url = $_GET['cliente'] ?? null;
$logged_in_user_role = $_SESSION['user_role'] ?? null;

// CENÁRIO 1: HÁ UM SLUG NA URL (Acesso público de cliente final ou Superadmin selecionou um parceiro)
if (!empty($slug_na_url)) {
    $stmt = $pdo->prepare(
        "SELECT c.empresa_id, c.slug, c.nome_empresa, c.logo_url, c.cor_variavel 
         FROM configuracoes_whitelabel c 
         WHERE c.slug = :slug LIMIT 1"
    );
    $stmt->execute([':slug' => $slug_na_url]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $id_empresa_master_contexto = $config['empresa_id']; // CORREÇÃO APLICADA AQUI
        $slug_contexto = $config['slug'];
        $nome_empresa_contexto = $config['nome_empresa'];
        $logo_url_contexto = $config['logo_url'];
        $cor_primaria_contexto = $config['cor_variavel'];
    }
} 
// CENÁRIO 2: NÃO HÁ SLUG NA URL, MAS UM USUÁRIO ESTÁ LOGADO
else if (isset($_SESSION['empresa_id'])) {
    $stmt = $pdo->prepare(
        "SELECT c.empresa_id, c.slug, c.nome_empresa, c.logo_url, c.cor_variavel 
         FROM configuracoes_whitelabel c 
         WHERE c.empresa_id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $_SESSION['empresa_id']]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $id_empresa_master_contexto = $config['empresa_id']; // CORREÇÃO APLICADA AQUI
        $slug_contexto = $config['slug'];
        $nome_empresa_contexto = $config['nome_empresa'];
        $logo_url_contexto = $config['logo_url'];
        $cor_primaria_contexto = $config['cor_variavel'];
    }
}

// Verifica se o usuário logado é Superadmin para exibir o seletor no formulário.
if ($logged_in_user_role === 'superadmin') {
    $is_superadmin_on_kyc = true;
}
?>