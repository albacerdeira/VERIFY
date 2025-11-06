<?php
// Define os valores padrão da sua plataforma (Verify KYC).
$nome_empresa_contexto = 'Verify KYC';
$logo_url_contexto = 'imagens/verify-kyc.png';
$cor_variavel_contexto = '#4f46e5';
$id_empresa_master_contexto = null;
$slug_contexto = $_GET['cliente'] ?? null;
$is_superadmin_on_kyc = false; // Inicia como falso por padrão

// Inicializa $config com valores padrão caso não seja carregado depois
$config = [
    'nome_empresa' => $nome_empresa_contexto,
    'logo_url' => $logo_url_contexto,
    'cor_variavel' => $cor_variavel_contexto,
    'slug' => null,
    'analise_risco_cnae_ativo' => 0
];

$empresa_id_para_buscar = null;

// Prioridade 1: Slug na URL (para links públicos/clientes).
if ($slug_contexto) {
    try {
        $stmt = $pdo->prepare("SELECT empresa_id FROM configuracoes_whitelabel WHERE slug = ?");
        $stmt->execute([$slug_contexto]);
        $result = $stmt->fetch();
        if ($result) {
            $empresa_id_para_buscar = $result['empresa_id'];
        }
    } catch (PDOException $e) {
        // Lidar com erro de banco de dados, se necessário
        error_log("Erro ao buscar slug: " . $e->getMessage());
    }
}
// Prioridade 2: Usuário de parceiro logado (usa $user_empresa_id de bootstrap.php).
elseif ($user_empresa_id) {
    $empresa_id_para_buscar = $user_empresa_id;
}

// Se um ID de empresa foi encontrado, busca as configurações de whitelabel.
if ($empresa_id_para_buscar) {
    try {
        $stmt = $pdo->prepare("SELECT nome_empresa, logo_url, cor_variavel, slug, analise_risco_cnae_ativo FROM configuracoes_whitelabel WHERE empresa_id = ?");
        $stmt->execute([$empresa_id_para_buscar]);
        $config = $stmt->fetch();

        if ($config) {
            $nome_empresa_contexto = $config['nome_empresa'];
            $logo_url_contexto = !empty($config['logo_url']) ? $config['logo_url'] : 'imagens/verify-kyc.png';
            $cor_variavel_contexto = $config['cor_variavel'] ?: $cor_variavel_contexto;
            $id_empresa_master_contexto = $empresa_id_para_buscar;
            if (!$slug_contexto) { 
                $slug_contexto = $config['slug'];
            }
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar config whitelabel: " . $e->getMessage());
    }
}

// --- CORREÇÃO: Usa a variável global $is_superadmin definida em bootstrap.php ---
// Verifica se o usuário é Superadmin para cenários específicos, como o seletor no kyc_form.
if ($is_superadmin) {
    $is_superadmin_on_kyc = true;
}

?>