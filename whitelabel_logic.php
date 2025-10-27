<?php
// Define os valores padrão da sua plataforma (Verify KYC).
$nome_empresa_contexto = 'Verify KYC';
$logo_url_contexto = 'imagens/verify-kyc.png'; // Logo padrão do seu aplicativo.
$cor_variavel_contexto = '#4f46e5'; // Cor padrão.
$id_empresa_master_contexto = null;
$slug_contexto = $_GET['cliente'] ?? null;

$empresa_id_para_buscar = null;

// Prioridade 1: Slug na URL (para links públicos/clientes).
if ($slug_contexto) {
    $stmt = $pdo->prepare("SELECT empresa_id FROM configuracoes_whitelabel WHERE slug = ?");
    $stmt->execute([$slug_contexto]);
    $result = $stmt->fetch();
    if ($result) {
        $empresa_id_para_buscar = $result['empresa_id'];
    }
} 
// Prioridade 2: Usuário de parceiro logado (para o painel administrativo).
elseif (isset($_SESSION['empresa_id'])) {
    $empresa_id_para_buscar = $_SESSION['empresa_id'];
}

// Se um ID de empresa foi encontrado, busca as configurações de whitelabel.
if ($empresa_id_para_buscar) {
    $stmt = $pdo->prepare("SELECT nome_empresa, logo_url, cor_variavel, slug FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$empresa_id_para_buscar]);
    $config = $stmt->fetch();

    if ($config) {
        $nome_empresa_contexto = $config['nome_empresa'];
        // Usa o logo padrão se o do parceiro não estiver definido.
        $logo_url_contexto = !empty($config['logo_url']) ? $config['logo_url'] : 'imagens/verify-kyc.png';
        $cor_variavel_contexto = $config['cor_variavel'] ?: $cor_variavel_contexto;
        $id_empresa_master_contexto = $empresa_id_para_buscar;
        // Garante que o slug esteja definido para links de compartilhamento.
        if (!$slug_contexto) { 
            $slug_contexto = $config['slug'];
        }
    }
}

// Verifica se o usuário logado é Superadmin para exibir o seletor no formulário.
if ($logged_in_user_role === 'superadmin') {
    $is_superadmin_on_kyc = true;
}
?>