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
$cliente_email = $_SESSION['cliente_email'] ?? ''; // Garante que a variável exista

// --- LÓGICA CORRIGIDA PARA BUSCAR STATUS DO KYC USANDO cliente_id ---
$kyc_status = null;
$error = null;

if (isset($pdo)) {
    try {
        // Busca o status da última submissão do cliente usando o ID do cliente.
        $stmt_status = $pdo->prepare(
            'SELECT status FROM kyc_empresas WHERE cliente_id = ? ORDER BY data_atualizacao DESC LIMIT 1'
        );
        $stmt_status->execute([$cliente_id]);
        $submission = $stmt_status->fetch(PDO::FETCH_ASSOC);

        if ($submission) {
            // Mapeia o status do banco para um texto mais amigável
            switch ($submission['status']) {
                case 'em_preenchimento':
                    $kyc_status = 'Em Preenchimento';
                    break;
                case 'enviado':
                    $kyc_status = 'Em Análise';
                    break;
                case 'aprovado':
                    $kyc_status = 'Aprovado';
                    break;
                case 'reprovado':
                    $kyc_status = 'Em Análise';/* reprovado */
                    break;
                default:
                    $kyc_status = ucfirst(str_replace('_', ' ', $submission['status']));
                    break;
            }
        }

    } catch (PDOException $e) {
        $error = "Não foi possível carregar o status do seu cadastro. Por favor, tente novamente mais tarde.";
        error_log("Erro ao buscar status do KYC para cliente ID {$cliente_id}: " . $e->getMessage());
    }
}
// --- FIM DA LÓGICA DE STATUS ---


// 3. LÓGICA WHITELABEL PERSISTENTE E CORRIGIDA
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
        // --- CORREÇÃO AQUI ---
        // Junta kc.id_empresa_master com cw.empresa_id
        $stmt_parceiro = $pdo->prepare(
            'SELECT cw.nome_empresa, cw.cor_variavel, cw.logo_url, cw.slug '
            . 'FROM kyc_clientes kc ' 
            . 'JOIN configuracoes_whitelabel cw ON kc.id_empresa_master = cw.empresa_id ' 
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

// --- CORREÇÃO DE CACHE-BUSTING PARA O LOGO ---
$logo_path_servidor = ltrim(htmlspecialchars($logo_url), '/');
$logo_cache_buster = file_exists($logo_path_servidor) ? '?v=' . filemtime($logo_path_servidor) : '';
$logo_url_final = htmlspecialchars($logo_url) . $logo_cache_buster;
// --- FIM DA CORREÇÃO ---
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .btn-sm { padding: 0.8rem 1.5rem;  }
        .text-danger { color: #dc3545; }
        .status-badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            background-color: #586fadff;/*enviado*/
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
        .status-aprovado {
            background-color: #198754; /* Verde (Success) */
        }
        .status-em-análise,
        .status-em-preenchimento {
            background-color: #ffc107; /* Amarelo (Warning) */
            color: #000;
        }
        .status-reprovado-com-pendências {
            background-color: #dc3545; /* Vermelho (Danger) */
        }
    </style>
</head>
<body>

    <header class="main-header">
        <div class="header-logo">
            <img src="<?= $logo_url_final ?>" alt="Logo de <?= htmlspecialchars($nome_empresa) ?>">
        </div>
        <div class="header-user-menu">
            <span>Olá, <strong><?= htmlspecialchars(explode(' ', $nome_cliente)[0]) ?></strong>!</span>
            <a href="cliente_logout.php" class="logout-link">Sair</a>
        </div>
    </header>

    <main class="dashboard-container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-header text-center"><h4 class="mb-0">Status do seu Cadastro KYC</h4></div>
                        <div class="card-body text-center p-4">
                            <?php if (!$kyc_status): ?>
                                <p class="card-title">Nenhum cadastro iniciado</p>
                                <p class="text-muted">Você ainda não iniciou seu processo de cadastro. Clique no botão abaixo para começar.</p>
                                <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-primary btn-sm mt-3">Iniciar Cadastro KYC</a>
                            <?php else: ?>
                                <p class="card-title-centered">Seu status atual é: 
                                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', htmlspecialchars($kyc_status))) ?>">
                                        <?= htmlspecialchars($kyc_status) ?>
                                    </span>
                                </p>
                                <?php if ($kyc_status === 'Em Preenchimento'): ?>
                                    <p class="text-muted mt-3">Você já iniciou seu cadastro. Continue de onde parou.</p>
                                    <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-primary btn-sm mt-3">Continuar Preenchimento</a>
                                <?php else: ?>
                                    <p class="text-muted mt-3">Nossa equipe está analisando suas informações. Qualquer novidade, entraremos em contato por e-mail.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>