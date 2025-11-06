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
$lead_origem = null; // Dados do lead de origem, se houver

if (isset($pdo)) {
    try {
        // Busca informações do cliente incluindo lead de origem (se a coluna existir)
        // Tenta primeiro com lead_id, se falhar usa query sem JOIN
        try {
            $stmt_cliente = $pdo->prepare(
                'SELECT kc.*, l.nome as lead_nome, l.created_at as lead_data_criacao ' .
                'FROM kyc_clientes kc ' .
                'LEFT JOIN leads l ON kc.lead_id = l.id ' .
                'WHERE kc.id = ?'
            );
            $stmt_cliente->execute([$cliente_id]);
            $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente_info && isset($cliente_info['lead_id']) && $cliente_info['lead_id']) {
                $lead_origem = [
                    'id' => $cliente_info['lead_id'],
                    'nome' => $cliente_info['lead_nome'],
                    'data_criacao' => $cliente_info['lead_data_criacao'],
                    'origem' => $cliente_info['origem'] ?? 'lead_conversion'
                ];
            }
        } catch (PDOException $e_lead) {
            // Coluna lead_id não existe ainda - busca dados do cliente sem JOIN
            error_log("INFO: Coluna lead_id não existe em kyc_clientes. Execute add_lead_id_to_kyc_clientes.sql");
            
            // Busca dados do cliente sem o JOIN
            $stmt_cliente = $pdo->prepare('SELECT * FROM kyc_clientes WHERE id = ?');
            $stmt_cliente->execute([$cliente_id]);
            $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
        }
        
        // Busca o status da última submissão do cliente usando o ID do cliente.
        $stmt_status = $pdo->prepare(
            'SELECT status FROM kyc_empresas WHERE cliente_id = ? ORDER BY data_atualizacao DESC LIMIT 1'
        );
        $stmt_status->execute([$cliente_id]);
        $submission = $stmt_status->fetch(PDO::FETCH_ASSOC);

        if ($submission) {
            // Mapeia o status do banco para um texto mais amigável
            switch ($submission['status']) {
                case 'Em Preenchimento':
                    $kyc_status = 'Em Preenchimento';
                    break;
                case 'Novo Registro':
                    $kyc_status = 'Aguardando Análise';
                    break;
                case 'Em Análise':
                    $kyc_status = 'Em Análise';
                    break;
                case 'Pendenciado':
                    $kyc_status = 'Pendências Identificadas';
                    break;
                case 'Aprovado':
                    $kyc_status = 'Aprovado';
                    break;
                case 'Reprovado':
                    $kyc_status = 'Reprovado';
                    break;
                default:
                    $kyc_status = htmlspecialchars($submission['status']);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            <div class="col-md-10">
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['flash_message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php else: ?>
                    
                    <?php if ($lead_origem): ?>
                    <!-- Informação de Origem Lead -->
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle-fill"></i> 
                        <strong>Bem-vindo!</strong> Seu cadastro foi iniciado a partir de um lead registrado em 
                        <?= date('d/m/Y', strtotime($lead_origem['data_criacao'])) ?>.
                        <small class="d-block mt-1 text-muted">ID do Lead: #<?= $lead_origem['id'] ?> | Origem: <?= htmlspecialchars($lead_origem['origem']) ?></small>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Título com Progresso -->
                    <div class="text-center mb-4">
                        <h3 class="mb-3">Complete seu Cadastro</h3>
                        <?php
                        $dados_pessoais_ok = isset($cliente_info) && $cliente_info['dados_completos_preenchidos'];
                        $tem_empresa = !empty($kyc_status);
                        $total_passos = 2;
                        $passos_completos = ($dados_pessoais_ok ? 1 : 0) + ($tem_empresa ? 1 : 0);
                        $progresso_percent = ($passos_completos / $total_passos) * 100;
                        ?>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-success progress-bar-striped <?= $progresso_percent < 100 ? 'progress-bar-animated' : '' ?>" 
                                 role="progressbar" 
                                 style="width: <?= $progresso_percent ?>%"
                                 aria-valuenow="<?= $progresso_percent ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <strong><?= $passos_completos ?> de <?= $total_passos ?> passos concluídos (<?= round($progresso_percent) ?>%)</strong>
                            </div>
                        </div>
                        <?php if ($progresso_percent === 100): ?>
                            <p class="text-success mb-0">
                                <i class="bi bi-trophy-fill"></i> <strong>Parabéns! Você completou todos os passos!</strong> 🎉
                            </p>
                        <?php else: ?>
                            <p class="text-muted mb-0">Complete os passos abaixo para finalizar seu cadastro</p>
                        <?php endif; ?>
                    </div>

                    <!-- Cards de Passos -->
                    <div class="row">
                        <!-- PASSO 1: Dados Pessoais -->
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm h-100 <?= $dados_pessoais_ok ? 'border-success' : 'border-warning' ?>" style="border-width: 2px;">
                                <div class="card-header text-white text-center <?= $dados_pessoais_ok ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark">Passo 1</span>
                                        <?php if ($dados_pessoais_ok): ?>
                                            <i class="bi bi-check-circle-fill fs-4"></i>
                                        <?php else: ?>
                                            <i class="bi bi-exclamation-circle-fill fs-4"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="mb-0 mt-2"><i class="bi bi-person-vcard"></i> Meus Dados Pessoais</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($dados_pessoais_ok): ?>
                                        <div class="alert alert-success mb-3">
                                            <i class="bi bi-check-circle-fill"></i> <strong>Completo!</strong>
                                        </div>
                                        <ul class="list-unstyled mb-3">
                                            <li class="mb-2"><i class="bi bi-check text-success"></i> Documento enviado</li>
                                            <li class="mb-2"><i class="bi bi-check text-success"></i> Filiação cadastrada</li>
                                            <li class="mb-2"><i class="bi bi-check text-success"></i> Data de nascimento</li>
                                            <li class="mb-2"><i class="bi bi-check text-success"></i> Endereço completo</li>
                                        </ul>
                                        <a href="cliente_dados_pessoais.php" class="btn btn-outline-success w-100">
                                            <i class="bi bi-pencil"></i> Atualizar Dados
                                        </a>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-3">
                                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Pendente</strong>
                                        </div>
                                        <p class="text-muted mb-3">Complete seus dados pessoais:</p>
                                        <ul class="list-unstyled mb-3">
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Foto do documento (RG ou CNH)</li>
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Nome do pai e da mãe</li>
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Data de nascimento</li>
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Endereço completo</li>
                                        </ul>
                                        <a href="cliente_dados_pessoais.php" class="btn btn-warning w-100 text-dark">
                                            <i class="bi bi-plus-circle"></i> Completar Agora
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- PASSO 2: Dados da Empresa -->
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm h-100 <?= $tem_empresa ? 'border-success' : 'border-primary' ?>" style="border-width: 2px;">
                                <div class="card-header text-white text-center <?= $tem_empresa ? 'bg-success' : 'bg-primary' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark">Passo 2</span>
                                        <?php if ($tem_empresa): ?>
                                            <i class="bi bi-check-circle-fill fs-4"></i>
                                        <?php else: ?>
                                            <i class="bi bi-building fs-4"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="mb-0 mt-2"><i class="bi bi-building-check"></i> Dados da Empresa</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($tem_empresa): ?>
                                        <div class="alert alert-success mb-3">
                                            <i class="bi bi-check-circle-fill"></i> <strong>Cadastro Iniciado!</strong>
                                        </div>
                                        <p class="mb-2"><strong>Status Atual:</strong></p>
                                        <div class="text-center mb-3">
                                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', htmlspecialchars($kyc_status))) ?> fs-6">
                                                <?= htmlspecialchars($kyc_status) ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($kyc_status === 'Em Preenchimento'): ?>
                                            <p class="text-muted mb-3">Continue preenchendo os dados da sua empresa.</p>
                                            <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-primary w-100 mb-2">
                                                <i class="bi bi-pencil"></i> Continuar Preenchimento
                                            </a>
                                        <?php elseif ($kyc_status === 'Aprovado'): ?>
                                            <p class="text-success mb-3"><i class="bi bi-check-circle-fill"></i> Empresa aprovada!</p>
                                        <?php else: ?>
                                            <p class="text-muted mb-3">Nossa equipe está analisando suas informações.</p>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-plus-circle"></i> Adicionar Outra Empresa
                                        </a>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-3">
                                            <i class="bi bi-info-circle-fill"></i> <strong>Pronto para começar</strong>
                                        </div>
                                        <p class="text-muted mb-3">Cadastre os dados da sua empresa para análise KYC:</p>
                                        <ul class="list-unstyled mb-3">
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Dados da empresa (CNPJ, Razão Social)</li>
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Documentos corporativos</li>
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Informações dos sócios</li>
                                            <li class="mb-2"><i class="bi bi-circle text-muted"></i> Dados bancários</li>
                                        </ul>
                                        <a href="kyc_form.php<?= $slug_contexto ? '?cliente=' . htmlspecialchars($slug_contexto) : '' ?>" class="btn btn-primary w-100">
                                            <i class="bi bi-building-add"></i> Cadastrar Empresa
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Empresas Cadastradas -->
                    <?php
                    // Busca todas as empresas do cliente
                    try {
                        $stmt_empresas = $pdo->prepare("
                            SELECT id, razao_social, cnpj, status, data_criacao, data_atualizacao
                            FROM kyc_empresas 
                            WHERE cliente_id = ? 
                            ORDER BY data_criacao DESC
                        ");
                        $stmt_empresas->execute([$cliente_id]);
                        $empresas = $stmt_empresas->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $empresas = [];
                    }
                    
                    if (!empty($empresas)): ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-building"></i> Minhas Empresas Cadastradas
                                <span class="badge bg-primary"><?= count($empresas) ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Razão Social</th>
                                            <th>CNPJ</th>
                                            <th>Status</th>
                                            <th>Cadastro</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($empresas as $emp): 
                                            // Define classe do badge de status
                                            switch($emp['status']) {
                                                case 'aprovado':
                                                    $status_class = 'success';
                                                    break;
                                                case 'pendente':
                                                    $status_class = 'warning';
                                                    break;
                                                case 'em_analise':
                                                    $status_class = 'info';
                                                    break;
                                                case 'reprovado':
                                                    $status_class = 'danger';
                                                    break;
                                                default:
                                                    $status_class = 'secondary';
                                            }
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($emp['razao_social']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($emp['cnpj']) ?></code></td>
                                            <td>
                                                <span class="badge bg-<?= $status_class ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $emp['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($emp['data_criacao'])) ?></td>
                                            <td>
                                                <a href="kyc_form.php?empresa_id=<?= $emp['id'] ?><?= $slug_contexto ? '&cliente=' . htmlspecialchars($slug_contexto) : '' ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>