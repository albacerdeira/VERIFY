<?php
$page_title = 'Editar Cliente';
require_once 'bootstrap.php';

// Acesso permitido para Superadmin, Admin e Analista
if (!$is_superadmin && !$is_admin && !$is_analista) {
    require 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require 'footer.php';
    exit();
}

$cliente_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$cliente_id) {
    header('Location: clientes.php');
    exit;
}

$error = '';
$success = '';
$cliente = null;

// Carrega dados do cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM kyc_clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    if (!$cliente) {
        throw new Exception("Cliente não encontrado.");
    }

    // Verificação de segurança para Admin e Analista
    if (($is_admin || $is_analista) && $cliente['id_empresa_master'] != $user_empresa_id) {
        throw new Exception("Você não tem permissão para editar este cliente.");
    }

    // Busca lead original (se existir)
    $lead_original = null;
    if ($cliente['lead_id']) {
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$cliente['lead_id']]);
        $lead_original = $stmt->fetch();
    }

    // Busca histórico do lead (se houver)
    $historico_lead = [];
    if ($cliente['lead_id']) {
        $stmt = $pdo->prepare("
            SELECT 
                h.created_at,
                h.acao,
                h.descricao,
                COALESCE(u.nome, 'Sistema') AS usuario_nome
            FROM leads_historico h
            LEFT JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.lead_id = ?
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([$cliente['lead_id']]);
        $historico_lead = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca empresas (KYC) vinculadas ao cliente
    $empresas_kyc = [];
    $stmt = $pdo->prepare("
        SELECT 
            ke.id,
            ke.razao_social,
            ke.cnpj,
            ke.status,
            ke.data_criacao,
            ke.data_atualizacao,
            e.nome AS empresa_master_nome
        FROM kyc_empresas ke
        LEFT JOIN empresas e ON ke.id_empresa_master = e.id
        WHERE ke.cliente_id = ?
        ORDER BY ke.data_criacao DESC
    ");
    $stmt->execute([$cliente_id]);
    $empresas_kyc = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Processa o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim($_POST['nome_completo']);
        $cpf = trim($_POST['cpf']);
        $email = trim($_POST['email']);
        $status = $_POST['status'];
        $nova_senha = $_POST['nova_senha'];

        $params = [$nome, $cpf, $email, $status];
        $sql = "UPDATE kyc_clientes SET nome_completo = ?, cpf = ?, email = ?, status = ?";

        if (!empty($nova_senha)) {
            $sql .= ", password = ?";
            $params[] = password_hash($nova_senha, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $cliente_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['flash_message'] = "Dados do cliente atualizados com sucesso!";
        header('Location: cliente_edit.php?id=' . $cliente_id); // Recarrega a página para ver as alterações
        exit;

    } catch (Exception $e) {
        $error = "Erro ao atualizar: " . $e->getMessage();
    }
}

require 'header.php';
?>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-4">Editar Ficha do Cliente</h2>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <?php if ($cliente): ?>
    <div class="row">
        <div class="col-md-7">
            <form method="POST">
                <h4 class="mb-3 border-bottom pb-2">Dados Cadastrais</h4>
                
                <div class="mb-3">
                    <label for="nome_completo" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($cliente['nome_completo']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($cliente['cpf'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status da Conta</label>
                    <select name="status" id="status" class="form-select">
                        <option value="ativo" <?= ($cliente['status'] == 'ativo') ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($cliente['status'] == 'inativo') ? 'selected' : '' ?>>Inativo</option>
                        <option value="pendente" <?= ($cliente['status'] == 'pendente') ? 'selected' : '' ?>>Pendente</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="nova_senha" class="form-label">Redefinir Senha</label>
                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para não alterar">
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="clientes.php" class="btn btn-secondary">Voltar para a Lista</a>
            </form>
        </div>

        <div class="col-md-5">
            <div class="p-3 border rounded bg-light">
                <h4 class="mb-3 border-bottom pb-2">Documentos e Infos</h4>
                
                <div class="mb-3">
                    <p class="mb-1"><strong>ID do Cliente:</strong> #<?= htmlspecialchars($cliente['id']) ?></p>
                    <p class="mb-1"><strong>Data de Cadastro:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cliente['created_at']))) ?></p>
                    <p><strong>Última Atualização:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cliente['updated_at']))) ?></p>
                </div>

                <div class="text-center">
                    <h5 class="mb-2">Selfie Enviada</h5>
                    <?php
                    if (!empty($cliente['selfie_path'])) {
                        $path_servidor = htmlspecialchars($cliente['selfie_path']); // ex: "uploads/selfies/foto.jpg"
                        $ext = strtolower(pathinfo($path_servidor, PATHINFO_EXTENSION));

                        // --- CORREÇÃO CACHE BUSTING APLICADA ---
                        // 1. Gera o cache-buster (timestamp) SE o arquivo existir
                        $cache_buster = file_exists($path_servidor) ? '?v=' . filemtime($path_servidor) : '';
                        // 2. Cria o path web absoluto (com / no início) e adiciona o cache-buster
                        $path_web = '/' . ltrim($path_servidor, '/') . $cache_buster;
                        // --- FIM DA CORREÇÃO ---

                        // 3. Verifica a existência do arquivo no servidor (sem o cache-buster)
                        if (file_exists($path_servidor)) { 
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                // 4. Usa o $path_web (com cache-buster) no HTML
                                echo '<img src="' . $path_web . '" alt="Selfie do Cliente" class="img-fluid rounded border" style="max-height: 300px;">';
                            } elseif ($ext == 'pdf') {
                                echo '<div class="alert alert-info text-center">';
                                echo '<a href="' . $path_web . '" target="_blank" class="alert-link">Visualizar PDF da Selfie</a>';
                                echo '</div>';
                            } else {
                                echo '<p class="text-muted">Arquivo de selfie disponível, mas formato não visualizável: <a href="' . $path_web . '" target="_blank">' . basename($path_servidor) . '</a></p>';
                            }
                        } else {
                             echo '<p class="text-danger">Selfie registrada, mas o arquivo (' . $path_servidor . ') não foi encontrado no servidor.</p>';
                        }
                    } else {
                        echo '<p class="text-muted">Nenhuma selfie foi enviada por este cliente.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($empresas_kyc)): ?>
    <!-- Empresas KYC Vinculadas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-building-check"></i> Empresas Cadastradas (KYC)
                        <span class="badge bg-light text-success ms-2"><?= count($empresas_kyc) ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($empresas_kyc as $index => $empresa): 
                        // Define cor do status
                        $status_badge = match($empresa['status']) {
                            'aprovado' => 'bg-success',
                            'pendente' => 'bg-warning text-dark',
                            'em_analise' => 'bg-info',
                            'reprovado' => 'bg-danger',
                            'aguardando_documentos' => 'bg-secondary',
                            default => 'bg-light text-dark'
                        };
                        
                        // Nome da empresa master (whitelabel)
                        $empresa_master = $empresa['empresa_master_nome'] ?: 'Verify KYC';
                    ?>
                    <?php if ($index > 0): ?><hr class="my-3"><?php endif; ?>
                    <div class="empresa-kyc-item">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-6">
                                <h5 class="mb-1">
                                    <i class="bi bi-building"></i> 
                                    <?= htmlspecialchars($empresa['razao_social']) ?>
                                </h5>
                                <p class="mb-0 text-muted">
                                    <i class="bi bi-hash"></i> CNPJ: 
                                    <code><?= htmlspecialchars($empresa['cnpj']) ?></code>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Status KYC</label>
                                <span class="badge <?= $status_badge ?> fs-6">
                                    <?= ucfirst(str_replace('_', ' ', $empresa['status'])) ?>
                                </span>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="kyc_evaluate.php?id=<?= $empresa['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i> Ver KYC
                                </a>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <label class="text-muted small">Cadastrado em</label>
                                <p class="mb-0"><?= date('d/m/Y H:i', strtotime($empresa['data_criacao'])) ?></p>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small">Última Atualização</label>
                                <p class="mb-0"><?= date('d/m/Y H:i', strtotime($empresa['data_atualizacao'])) ?></p>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small">Plataforma</label>
                                <p class="mb-0">
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-shield-check"></i> <?= htmlspecialchars($empresa_master) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($lead_original): ?>
    <!-- Histórico do Lead Original -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-funnel-fill"></i> Lead Original
                        <span class="badge bg-light text-primary ms-2">#<?= $lead_original['id'] ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="text-muted small">Status do Lead</label>
                            <p class="mb-0">
                                <?php
                                $badge_class = match($lead_original['status']) {
                                    'novo' => 'bg-primary',
                                    'contatado' => 'bg-info',
                                    'qualificado' => 'bg-warning text-dark',
                                    'convertido' => 'bg-success',
                                    'perdido' => 'bg-secondary',
                                    default => 'bg-light text-dark'
                                };
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst($lead_original['status']) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">Data de Captura</label>
                            <p class="mb-0"><?= date('d/m/Y H:i', strtotime($lead_original['created_at'])) ?></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">Origem</label>
                            <p class="mb-0"><?= htmlspecialchars($lead_original['origem'] ?: 'Não informado') ?></p>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small">Empresa Informada</label>
                            <p class="mb-0"><?= htmlspecialchars($lead_original['empresa'] ?: '-') ?></p>
                        </div>
                        <?php if ($lead_original['mensagem']): ?>
                        <div class="col-12 mt-3">
                            <label class="text-muted small">Mensagem Original</label>
                            <div class="alert alert-light border">
                                <?= nl2br(htmlspecialchars($lead_original['mensagem'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($lead_original['utm_source'] || $lead_original['utm_medium'] || $lead_original['utm_campaign']): ?>
                        <div class="col-12 mt-2">
                            <label class="text-muted small">Rastreamento (UTM)</label>
                            <p class="mb-0">
                                <?php if ($lead_original['utm_source']): ?>
                                <span class="badge bg-secondary me-1">Source: <?= htmlspecialchars($lead_original['utm_source']) ?></span>
                                <?php endif; ?>
                                <?php if ($lead_original['utm_medium']): ?>
                                <span class="badge bg-secondary me-1">Medium: <?= htmlspecialchars($lead_original['utm_medium']) ?></span>
                                <?php endif; ?>
                                <?php if ($lead_original['utm_campaign']): ?>
                                <span class="badge bg-secondary">Campaign: <?= htmlspecialchars($lead_original['utm_campaign']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($historico_lead)): ?>
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-clock-history"></i> Histórico do Lead</h6>
                    <div class="timeline" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($historico_lead as $item): 
                            $icone = 'bi-circle-fill';
                            $cor = 'text-primary';
                            
                            if (strpos($item['acao'], 'email_') === 0) {
                                $icone = 'bi-envelope-fill';
                                $cor = 'text-primary';
                            } elseif (strpos($item['acao'], 'status_') === 0) {
                                $icone = 'bi-arrow-repeat';
                                $cor = 'text-info';
                            } elseif ($item['acao'] === 'registro_completado') {
                                $icone = 'bi-check-circle-fill';
                                $cor = 'text-success';
                            }
                        ?>
                        <div class="d-flex mb-3 align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi <?= $icone ?> <?= $cor ?>" style="font-size: 1.1rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong class="text-capitalize"><?= htmlspecialchars(str_replace('_', ' ', $item['acao'])) ?></strong>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></small>
                                </div>
                                <p class="mb-1 text-muted"><?= htmlspecialchars($item['descricao']) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($item['usuario_nome']) ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center mb-0">
                        <i class="bi bi-inbox"></i> Nenhuma interação registrada com este lead
                    </p>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="lead_detail.php?id=<?= $lead_original['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right"></i> Ver Detalhes Completos do Lead
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
        <p>O cliente solicitado não pôde ser encontrado.</p>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 8px;
    bottom: 8px;
    width: 1px;
    background-color: #dee2e6;
}
</style>

<?php require 'footer.php'; ?>