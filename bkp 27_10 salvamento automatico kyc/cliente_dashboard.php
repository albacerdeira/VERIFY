<?php
// Inicia a sessão como a primeira ação de todas.
session_start();

require_once 'config.php';

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO
// ================================
if (!isset($_SESSION['cliente_id'])) {
    header('Location: cliente_login.php');
    exit;
}

// 2. RECUPERAÇÃO DE DADOS DA SESSÃO
// ==================================
$nome_cliente = $_SESSION['cliente_nome'] ?? 'Cliente';
$cliente_id = $_SESSION['cliente_id'];

// 3. LÓGICA WHITELABEL PERSISTENTE E APRIMORADA
// ==============================================

// Define a identidade visual padrão da plataforma.
$nome_empresa_padrao = 'Verify KYC';
$cor_variavel_padrao = '#4f46e5'; 
$logo_url_padrao = 'imagens/verify-kyc.png';

// Inicia com os valores padrão.
$nome_empresa = $nome_empresa_padrao;
$cor_variavel = $cor_variavel_padrao;
$logo_url = $logo_url_padrao;
$slug_contexto = null; // Inicia sem slug.

if (isset($pdo)) {
    try {
        // PASSO 1: Tenta encontrar um parceiro associado PERMANENTEMENTE ao cliente.
        $stmt_parceiro = $pdo->prepare(
            'SELECT cw.nome_empresa, cw.cor_variavel, cw.logo_url, cw.slug '
            . 'FROM kyc_clientes kc ' 
            . 'JOIN configuracoes_whitelabel cw ON kc.whitelabel_parceiro_id = cw.id ' 
            . 'WHERE kc.id = ?'
        );
        $stmt_parceiro->execute([$cliente_id]);
        $parceiro_associado = $stmt_parceiro->fetch(PDO::FETCH_ASSOC);

        if ($parceiro_associado) {
            // Cliente tem um parceiro. Usa a identidade visual do parceiro.
            $nome_empresa = $parceiro_associado['nome_empresa'];
            $cor_variavel = $parceiro_associado['cor_variavel'];
            $logo_url = $parceiro_associado['logo_url'];
            $slug_contexto = $parceiro_associado['slug'];

            // Força a URL a ser consistente, se necessário.
            if (!isset($_GET['cliente']) || $_GET['cliente'] !== $slug_contexto) {
                header('Location: cliente_dashboard.php?cliente=' . urlencode($slug_contexto));
                exit;
            }

        } else {
            // PASSO 2: Se não tem parceiro associado, usa o slug da URL (comportamento antigo).
            $slug_url = $_GET['cliente'] ?? null;
            if ($slug_url) {
                $stmt_slug = $pdo->prepare("SELECT nome_empresa, cor_variavel, logo_url FROM configuracoes_whitelabel WHERE slug = ?");
                $stmt_slug->execute([$slug_url]);
                $config_slug = $stmt_slug->fetch();
                if ($config_slug) {
                    $nome_empresa = $config_slug['nome_empresa'];
                    $cor_variavel = $config_slug['cor_variavel'];
                    $logo_url = $config_slug['logo_url'];
                    $slug_contexto = $slug_url; // Mantém o slug da URL no contexto
                }
            }
        }
    } catch (PDOException $e) {
        // Em caso de erro de banco, registra e segue com a identidade padrão.
        error_log("Erro ao carregar whitelabel no dashboard: " . $e->getMessage());
    }
}

// Título da página.
$page_title = 'Painel do Cliente - ' . htmlspecialchars($nome_empresa);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($cor_variavel) ?>;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7f9; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .main-header { background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header-logo img { max-height: 40px; object-fit: contain; }
        .header-user-menu { display: flex; align-items: center; }
        .header-user-menu span { margin-right: 1.5rem; color: #333; font-weight: 500; }
        .logout-link { color: var(--primary-color); text-decoration: none; font-weight: 500; padding: 0.5rem 1rem; border: 1px solid var(--primary-color); border-radius: 5px; transition: all 0.3s ease; }
        .logout-link:hover { background-color: var(--primary-color); color: #fff; }
        .dashboard-container { max-width: 900px; width: 100%; margin: 2rem auto; padding: 0 1rem; flex-grow: 1; }
        .card { border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .card-header { font-weight: 600; background-color: #f8f9fa; padding: 1rem 1.5rem; }
        .card-body { padding: 1.5rem; }
        .card-title { font-weight: 600; color: #333; }
        .btn-lg { padding: 0.8rem 1.5rem; font-size: 1.1rem; }
        .text-danger { color: #dc3545; }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-logo">
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo de <?= htmlspecialchars($nome_empresa) ?>">
        </div>
        <div class="header-user-menu">
            <span>Olá, <strong><?= htmlspecialchars(explode(' ', $nome_cliente)[0]) ?></strong>!</span>
            <a href="cliente_logout.php" class="logout-link">Sair</a>
        </div>
    </header>

    <main class="dashboard-container">
        <?php
        // Inclui o conteúdo dinâmico do painel (status do KYC ou botão para iniciar)
        include 'kyc_dashboard_content.php';
        ?>
    </main>

</body>
</html>
