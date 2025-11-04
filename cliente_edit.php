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
        $status = $_POST['status'] ?? $cliente['status'];
        $nova_senha = trim($_POST['nova_senha']);
        
        // Normaliza CPF para comparação (remove pontos, traços e espaços)
        $cpf_normalizado = preg_replace('/[^0-9]/', '', $cpf);
        $cpf_banco_normalizado = preg_replace('/[^0-9]/', '', $cliente['cpf'] ?? '');
        
        // Normaliza email para comparação (remove espaços e converte para minúsculas)
        $email_normalizado = strtolower(trim($email));
        $email_banco_normalizado = strtolower(trim($cliente['email'] ?? ''));
        
        // Detecta se há mudanças em dados sensíveis (APENAS: nome, email, CPF, senha)
        // STATUS NÃO REQUER VERIFICAÇÃO (ação administrativa)
        $sensitive_data_changed = false;
        $nome_changed = trim($nome) !== trim($cliente['nome_completo']);
        $email_changed = $email_normalizado !== $email_banco_normalizado;
        $cpf_changed = !empty($cpf_normalizado) && $cpf_normalizado !== $cpf_banco_normalizado;
        $senha_changed = !empty($nova_senha);
        // Só exige verificação se algum dos campos sensíveis mudou
        if ($nome_changed || $email_changed || $cpf_changed || $senha_changed) {
            $sensitive_data_changed = true;
        }
        
    // DEBUG: Log para verificar as comparações (REMOVER EM PRODUÇÃO)
    error_log("=== VERIFICAÇÃO DE MUDANÇAS ===");
    error_log("Nome POST: '$nome' vs DB: '{$cliente['nome_completo']}' - Mudou: " . ($nome_changed ? 'SIM' : 'NÃO'));
    error_log("Email POST: '$email_normalizado' vs DB: '$email_banco_normalizado' - Mudou: " . ($email_changed ? 'SIM' : 'NÃO'));
    error_log("CPF POST: '$cpf_normalizado' vs DB: '$cpf_banco_normalizado' - Mudou: " . ($cpf_changed ? 'SIM' : 'NÃO'));
    error_log("Nova senha vazia: " . ($senha_changed ? 'NÃO' : 'SIM'));
    error_log("Dados sensíveis alterados: " . ($sensitive_data_changed ? 'SIM' : 'NÃO'));
        
        // Se dados sensíveis foram alterados, EXIGE verificação facial OU por documento
        if ($sensitive_data_changed) {
            $has_face_token = (
                !empty($_POST['verification_token']) &&
                !empty($_SESSION['face_verification_token']) &&
                $_POST['verification_token'] === $_SESSION['face_verification_token'] &&
                !empty($_SESSION['face_verification_expires']) &&
                time() <= $_SESSION['face_verification_expires'] &&
                $_SESSION['face_verification_cliente_id'] == $cliente_id
            );
            
            $has_document_token = (
                !empty($_POST['verification_token']) &&
                !empty($_SESSION['document_verification_token']) &&
                $_POST['verification_token'] === $_SESSION['document_verification_token'] &&
                !empty($_SESSION['document_verification_expires']) &&
                time() <= $_SESSION['document_verification_expires'] &&
                $_SESSION['document_verification_cliente_id'] == $cliente_id
            );
            
            if (!$has_face_token && !$has_document_token) {
                throw new Exception('Verificação de identidade obrigatória para alteração de dados sensíveis (email, CPF, senha). Use selfie ou documento com foto.');
            }
            
            // Limpa os tokens usados (uso único)
            unset($_SESSION['face_verification_token']);
            unset($_SESSION['face_verification_cliente_id']);
            unset($_SESSION['face_verification_expires']);
            unset($_SESSION['document_verification_token']);
            unset($_SESSION['document_verification_cliente_id']);
            unset($_SESSION['document_verification_expires']);
        }

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

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

                <!-- Campo Status da Conta (fora do bloco de dados sensíveis) -->
                <div class="mb-3 border rounded p-3 bg-light">
                    <label for="status" class="form-label">Status da Conta</label>
                    <select class="form-select" id="status" name="status">
                        <option value="ativo" <?= $cliente['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="pendente" <?= $cliente['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="inativo" <?= $cliente['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        <option value="suspenso" <?= $cliente['status'] === 'suspenso' ? 'selected' : '' ?>>Suspenso</option>
                    </select>
                    <small class="text-muted">Este campo <strong>não exige verificação de identidade</strong> (ação administrativa)</small>
                </div>

                <div class="mb-4">
                    <label for="nova_senha" class="form-label">Redefinir Senha</label>
                    <input type="password" class="form-control" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para não alterar">
                </div>

                <!-- Campo oculto para token de verificação facial -->
                <input type="hidden" name="verification_token" id="verification_token" value="">

                <!-- Alerta de verificação obrigatória -->
                <div id="verification-alert" class="alert alert-warning mb-3" role="alert">
                    <i class="bi bi-shield-exclamation"></i>
                    <strong>Atenção:</strong> Se você alterar <u>Nome</u>, <u>Email</u>, <u>CPF</u> ou <u>Senha</u>, será necessário verificação de identidade.<br>
                    <small class="text-muted">Alteração de <strong>Status da Conta</strong> <u>não exige</u> verificação de identidade.</small>
                    <div class="mt-3">
                        <p class="mb-2 fw-bold">Método de verificação:</p>
                        <button type="button" class="btn btn-primary w-100" id="btn-open-doc-modal">
                            <i class="bi bi-file-earmark-person"></i> Documento com Foto (RG ou CNH)
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> 
                            Envie uma foto do seu documento (RG ou CNH). Validaremos automaticamente nome, CPF, RG e foto.
                        </small>
                    </div>
                </div>

                <!-- Badge de verificação bem-sucedida -->
                <div id="face-verified-badge" class="alert alert-success d-none" role="alert">
                    <i class="bi bi-shield-check"></i>
                    <strong>Identidade verificada!</strong> Você pode salvar as alterações.
                </div>

                <hr>
                <button type="submit" class="btn btn-primary" id="btn-save-changes">Salvar Alterações</button>
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
    
    <!-- Histórico de Verificações (para TODOS os clientes) -->
    <div class="mt-4">
        <?php
        try {
            $cliente_id = $cliente['id'];
            if (file_exists('includes/verification_history.php')) {
                include 'includes/verification_history.php';
            } else {
                echo '<div class="alert alert-danger">Arquivo verification_history.php não encontrado</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Erro ao carregar histórico: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </div>
    
    <?php else: ?>
        <p>O cliente solicitado não pôde ser encontrado.</p>
    <?php endif; ?>
</div>

<?php if ($cliente): ?>
<!-- Modal de Verificação Facial -->
<div class="modal fade" id="faceVerificationModal" tabindex="-1" 
     data-bs-backdrop="static" data-bs-keyboard="false"
     aria-labelledby="faceVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="faceVerificationModalLabel">
                    <i class="bi bi-shield-check"></i> Verificação de Identidade Facial
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Para sua segurança:</strong> Vamos comparar sua face com a selfie cadastrada do cliente.
                    <br>Posicione seu rosto no centro da câmera e clique em "Capturar Selfie".
                </div>

                <!-- Status da verificação -->
                <div id="verification-status" class="alert d-none" role="alert"></div>

                <!-- Container da câmera -->
                <div id="camera-container" class="text-center mb-3">
                    <video id="camera-video" autoplay playsinline class="border rounded" style="width: 100%; max-width: 500px; transform: scaleX(-1);"></video>
                    <canvas id="camera-canvas" style="display:none;"></canvas>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="btn-capture" onclick="captureSelfie()">
                            <i class="bi bi-camera-fill"></i> Capturar Selfie
                        </button>
                        <button type="button" class="btn btn-secondary" id="btn-recapture" style="display:none;" onclick="resetCamera()">
                            <i class="bi bi-arrow-clockwise"></i> Tirar Outra Foto
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <p class="text-muted mb-2">ou</p>
                        <label for="selfie-upload-input" class="btn btn-outline-primary" id="selfie-upload-label">
                            <i class="bi bi-upload"></i> Enviar Selfie do Computador
                        </label>
                        <input type="file" id="selfie-upload-input" accept="image/jpeg,image/jpg,image/png" style="display:none;" onchange="handleSelfieUpload(event)">
                        <div id="selfie-upload-feedback" class="mt-2 text-success" style="display:none;">
                            <i class="bi bi-check-circle"></i> <small>Imagem carregada!</small>
                        </div>
                    </div>
                </div>

                <!-- Preview da selfie capturada -->
                <div id="preview-container" class="text-center" style="display:none;">
                    <h6>Selfie Capturada:</h6>
                    <img id="selfie-preview" src="" alt="Preview" class="img-fluid border rounded" style="max-width: 300px;">
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" id="btn-verify" onclick="verifyFace()">
                            <i class="bi bi-check-circle"></i> Verificar Identidade
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetCamera()">
                            <i class="bi bi-arrow-clockwise"></i> Tirar Outra Foto
                        </button>
                    </div>
                </div>

                <!-- Loader -->
                <div id="verification-loader" class="text-center" style="display:none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Verificando...</span>
                    </div>
                    <p class="mt-2">Comparando faces com AWS Rekognition...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Verificação por Documento -->
<div class="modal fade" id="documentVerificationModal" tabindex="-1" 
     data-bs-backdrop="static" data-bs-keyboard="false"
     aria-labelledby="documentVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="documentVerificationModalLabel">
                    <i class="bi bi-file-earmark-person"></i> Verificação por Documento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Instruções:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Fotografe seu RG ou CNH (com foto visível)</li>
                        <li>Sistema extrairá: Nome, CPF, RG, Filiação, Data Nascimento</li>
                        <li>Comparará a foto do documento com sua selfie original</li>
                        <li>Validará se os dados batem com o cadastro</li>
                    </ol>
                </div>

                <!-- Status da verificação -->
                <div id="doc-verification-status" class="alert d-none" role="alert"></div>

                <!-- Container da câmera para documento -->
                <div id="doc-camera-container" class="text-center mb-3">
                    <video id="doc-camera-video" autoplay playsinline class="border rounded" style="width: 100%; max-width: 600px;"></video>
                    <canvas id="doc-camera-canvas" style="display:none;"></canvas>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="btn-doc-capture" onclick="captureDocument()">
                            <i class="bi bi-camera-fill"></i> Fotografar Documento
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <p class="text-muted mb-2">ou</p>
                        <label for="document-upload-input" class="btn btn-outline-primary" id="document-upload-label">
                            <i class="bi bi-upload"></i> Enviar Foto do Documento
                        </label>
                        <input type="file" id="document-upload-input" accept="image/jpeg,image/jpg,image/png" style="display:none;" onchange="handleDocumentUpload(event)">
                        <div id="document-upload-feedback" class="mt-2 text-success" style="display:none;">
                            <i class="bi bi-check-circle"></i> <small>Documento carregado!</small>
                        </div>
                    </div>
                </div>

                <!-- Preview do documento capturado -->
                <div id="doc-preview-container" class="text-center" style="display:none;">
                    <h6>Documento Capturado:</h6>
                    <img id="doc-preview" src="" alt="Preview" class="img-fluid border rounded" style="max-width: 500px;">
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" id="btn-doc-verify" onclick="verifyDocument()">
                            <i class="bi bi-shield-check"></i> Validar Documento
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetDocumentCamera()">
                            <i class="bi bi-arrow-clockwise"></i> Fotografar Novamente
                        </button>
                    </div>
                </div>

                <!-- Loader -->
                <div id="doc-verification-loader" class="text-center" style="display:none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Verificando...</span>
                    </div>
                    <p class="mt-2">Extraindo dados do documento e validando...</p>
                    <small class="text-muted">Isso pode levar alguns segundos (OCR + Comparação Facial)</small>
                </div>

                <!-- Resultado da validação -->
                <div id="doc-validation-results" style="display:none;">
                    <hr>
                    <h6 class="mb-3"><i class="bi bi-clipboard-data"></i> Dados Extraídos vs. Cadastro:</h6>
                    <div id="doc-validation-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let stream = null;
let clienteId = <?= $cliente_id ?>;
let originalEmail = '<?= addslashes(strtolower(trim($cliente['email']))) ?>';
let originalCpf = '<?= preg_replace('/[^0-9]/', '', $cliente['cpf'] ?? '') ?>';
let originalNome = '<?= addslashes(trim($cliente['nome_completo'])) ?>';

// Função para normalizar CPF (remove pontos, traços e espaços)
function normalizeCpf(cpf) {
    return cpf.replace(/[^0-9]/g, '');
}

// Função para normalizar Email (lowercase e sem espaços)
function normalizeEmail(email) {
    return email.toLowerCase().trim();
}

// Monitora mudanças em campos sensíveis (APENAS: Nome, Email, CPF, Senha)
// STATUS NÃO REQUER VERIFICAÇÃO
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM CARREGADO ===');
    
    // Botão para abrir modal de documento
    const btnOpenDocModal = document.getElementById('btn-open-doc-modal');
    const docModalEl = document.getElementById('documentVerificationModal');
    
    console.log('Botão encontrado:', btnOpenDocModal ? 'SIM' : 'NÃO');
    console.log('Modal encontrado:', docModalEl ? 'SIM' : 'NÃO');
    
    if (!btnOpenDocModal) {
        console.error('ERRO: Botão btn-open-doc-modal NÃO encontrado!');
        return;
    }
    
    if (!docModalEl) {
        console.error('ERRO: Modal documentVerificationModal NÃO encontrado!');
        return;
    }
    
    btnOpenDocModal.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('>>> BOTÃO CLICADO! Tentando abrir modal...');
        
        // Método simples: remove classes que escondem o modal
        docModalEl.classList.add('show', 'd-block');
        docModalEl.style.display = 'block';
        docModalEl.setAttribute('aria-modal', 'true');
        docModalEl.setAttribute('role', 'dialog');
        docModalEl.removeAttribute('aria-hidden');
        
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = '0px';
        
        // Cria backdrop
        let backdrop = document.getElementById('manual-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'manual-backdrop';
            document.body.appendChild(backdrop);
        }
        
        console.log('✅ Modal aberto manualmente!');
    });
    
    // Adiciona botão de fechar no modal
    const closeButtons = docModalEl.querySelectorAll('[data-bs-dismiss="modal"]');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('Fechando modal...');
            docModalEl.classList.remove('show', 'd-block');
            docModalEl.style.display = 'none';
            docModalEl.setAttribute('aria-hidden', 'true');
            docModalEl.removeAttribute('aria-modal');
            docModalEl.removeAttribute('role');
            
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            const backdrop = document.getElementById('manual-backdrop');
            if (backdrop) backdrop.remove();
        });
    });
    
    console.log('✅ Event listeners configurados!');
    
    const nomeInput = document.getElementById('nome_completo');
    const emailInput = document.getElementById('email');
    const cpfInput = document.getElementById('cpf');
    const senhaInput = document.getElementById('nova_senha');
    const alertBox = document.getElementById('verification-alert');
    const verifiedBadge = document.getElementById('face-verified-badge');
    
    // Debug: Mostra valores originais ao carregar
    console.log('=== VALORES ORIGINAIS ===');
    console.log('Nome Original:', originalNome);
    console.log('Email Original:', originalEmail);
    console.log('CPF Original:', originalCpf);
    console.log('Nome Input encontrado:', nomeInput ? 'SIM' : 'NÃO');
    console.log('Email Input encontrado:', emailInput ? 'SIM' : 'NÃO');
    console.log('CPF Input encontrado:', cpfInput ? 'SIM' : 'NÃO');
    console.log('Senha Input encontrado:', senhaInput ? 'SIM' : 'NÃO');
    console.log('Alert Box encontrado:', alertBox ? 'SIM' : 'NÃO');
    console.log('Verified Badge encontrado:', verifiedBadge ? 'SIM' : 'NÃO');
    
    // Se não encontrou o alertBox, procura em toda a página
    if (!alertBox) {
        console.error('ERRO: Alert Box NÃO foi encontrado! ID esperado: verification-alert');
        console.log('Procurando por classe "alert" na página...');
        const alerts = document.querySelectorAll('.alert');
        console.log('Total de elementos com classe "alert":', alerts.length);
        alerts.forEach((alert, index) => {
            console.log(`Alert ${index}:`, alert.id, alert.className);
        });
        
        // Cria o alerta dinamicamente se não existir
        console.log('Tentando criar o alerta dinamicamente...');
        const form = document.querySelector('form');
        if (form) {
            const newAlert = document.createElement('div');
            newAlert.id = 'verification-alert';
            newAlert.className = 'alert alert-warning mb-3';
            newAlert.style.display = 'none';
            newAlert.innerHTML = `
                <i class="bi bi-shield-exclamation"></i>
                <strong>Atenção:</strong> Você está alterando dados sensíveis (Nome, Email, CPF ou Senha).
                <br><strong>Verificação de identidade obrigatória!</strong>
                <br><small class="text-muted">Obs: Mudança de Status NÃO requer verificação (ação administrativa)</small>
                <div class="mt-3">
                    <p class="mb-2 fw-bold">Escolha o método de verificação:</p>
                    <div class="btn-group d-flex gap-2" role="group">
                        <button type="button" class="btn btn-warning flex-fill" onclick="openFaceVerificationModal()">
                            <i class="bi bi-camera-fill"></i> Selfie Simples
                        </button>
                        <button type="button" class="btn btn-primary flex-fill" onclick="openDocumentVerificationModal()">
                            <i class="bi bi-file-earmark-person"></i> Documento com Foto
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Documento com Foto:</strong> Valida nome, CPF, RG e compara a foto do documento (mais seguro)
                    </small>
                </div>
            `;
            
            // Insere antes do botão de submit
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.parentNode.insertBefore(newAlert, submitBtn);
                console.log('✅ Alerta criado dinamicamente e inserido no formulário!');
                // Agora procura o alerta novamente
                const newAlertBox = document.getElementById('verification-alert');
                if (newAlertBox) {
                    console.log('✅ Alerta encontrado após criação!');
                    setupVerificationListeners(newAlertBox);
                }
            }
        }
        return;
    }
    
    setupVerificationListeners(alertBox);
});

function setupVerificationListeners(alertBox) {
    const nomeInput = document.getElementById('nome_completo');
    const emailInput = document.getElementById('email');
    const cpfInput = document.getElementById('cpf');
    const senhaInput = document.getElementById('nova_senha');
    const verifiedBadge = document.getElementById('face-verified-badge');
    
    function checkSensitiveChanges() {
        const nomeChanged = nomeInput.value.trim() !== originalNome;
        const emailChanged = normalizeEmail(emailInput.value) !== originalEmail;
        const cpfChanged = normalizeCpf(cpfInput.value) !== originalCpf;
        const senhaChanged = senhaInput.value.trim() !== '';
        
        const sensitiveChanged = nomeChanged || emailChanged || cpfChanged || senhaChanged;
        
        console.log('=== CHECK SENSITIVE CHANGES (JS) ===');
        console.log('Nome:', nomeInput.value.trim(), 'vs', originalNome, '- Changed:', nomeChanged);
        console.log('Email:', normalizeEmail(emailInput.value), 'vs', originalEmail, '- Changed:', emailChanged);
        console.log('CPF:', normalizeCpf(cpfInput.value), 'vs', originalCpf, '- Changed:', cpfChanged);
        console.log('Senha não vazia:', senhaChanged);
        console.log('Sensitive Changed:', sensitiveChanged);
        console.log('Alert Box classes ANTES:', alertBox.className);
        
        if (sensitiveChanged) {
            console.log('>>> MOSTRANDO ALERTA DE VERIFICAÇÃO');
            alertBox.classList.remove('d-none');
            alertBox.style.display = 'block'; // Força mostrar
            
            // Se ainda não verificou, mostra alerta
            if (!document.getElementById('verification_token').value) {
                if (verifiedBadge) verifiedBadge.classList.add('d-none');
            }
        } else {
            console.log('>>> ESCONDENDO ALERTA DE VERIFICAÇÃO');
            alertBox.classList.add('d-none');
            alertBox.style.display = 'none';
            if (verifiedBadge) verifiedBadge.classList.add('d-none');
        }
        
        console.log('Alert Box classes DEPOIS:', alertBox.className);
        console.log('Alert Box display:', alertBox.style.display);
    }
    
    // Checa imediatamente ao carregar (caso já tenha algo digitado)
    checkSensitiveChanges();
    
    nomeInput.addEventListener('input', checkSensitiveChanges);
    emailInput.addEventListener('input', checkSensitiveChanges);
    cpfInput.addEventListener('input', checkSensitiveChanges);
    senhaInput.addEventListener('input', checkSensitiveChanges);
    
    // Valida formulário antes de enviar
    document.querySelector('form').addEventListener('submit', function(e) {
        const nomeChanged = nomeInput.value.trim() !== originalNome;
        const emailChanged = normalizeEmail(emailInput.value) !== originalEmail;
        const cpfChanged = normalizeCpf(cpfInput.value) !== originalCpf;
        const senhaChanged = senhaInput.value.trim() !== '';
        const sensitiveChanged = nomeChanged || emailChanged || cpfChanged || senhaChanged;
        
        if (sensitiveChanged && !document.getElementById('verification_token').value) {
            e.preventDefault();
            alert('Você precisa verificar sua identidade antes de salvar alterações em dados sensíveis (Nome, Email, CPF ou Senha)!\n\nStatus NÃO requer verificação.');
            openFaceVerificationModal();
            return false;
        }
    });
}

function openFaceVerificationModal() {
    const modal = new bootstrap.Modal(document.getElementById('faceVerificationModal'));
    modal.show();
    
    // Inicia câmera quando o modal abrir
    document.getElementById('faceVerificationModal').addEventListener('shown.bs.modal', function() {
        startCamera();
    });
    
    // Para câmera quando fechar
    document.getElementById('faceVerificationModal').addEventListener('hidden.bs.modal', function() {
        stopCamera();
    });
}

function startCamera() {
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'user',
            width: { ideal: 1280 },
            height: { ideal: 720 }
        } 
    })
    .then(function(mediaStream) {
        stream = mediaStream;
        document.getElementById('camera-video').srcObject = stream;
        document.getElementById('camera-container').style.display = 'block';
        document.getElementById('preview-container').style.display = 'none';
    })
    .catch(function(err) {
        showStatus('error', 'Erro ao acessar câmera: ' + err.message);
    });
}

function stopCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
}

function captureSelfie() {
    const video = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    const preview = document.getElementById('selfie-preview');
    
    // Define dimensões do canvas
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Desenha imagem no canvas (espelhada para corrigir)
    const ctx = canvas.getContext('2d');
    ctx.scale(-1, 1);
    ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
    
    // Converte para imagem
    const imageDataUrl = canvas.toDataURL('image/jpeg', 0.9);
    preview.src = imageDataUrl;
    
    // Mostra preview e esconde câmera
    document.getElementById('camera-container').style.display = 'none';
    document.getElementById('preview-container').style.display = 'block';
    
    stopCamera();
}

function resetCamera() {
    document.getElementById('preview-container').style.display = 'none';
    document.getElementById('verification-status').classList.add('d-none');
    startCamera();
}

function handleSelfieUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Valida tipo de arquivo
    if (!file.type.match('image/(jpeg|jpg|png)')) {
        alert('Por favor, envie apenas imagens JPG ou PNG.');
        event.target.value = '';
        return;
    }
    
    // Valida tamanho (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Imagem muito grande. Tamanho máximo: 5MB');
        event.target.value = '';
        return;
    }
    
    // Mostra feedback
    document.getElementById('selfie-upload-feedback').style.display = 'block';
    
    // Para a câmera se estiver ativa
    stopCamera();
    
    // Lê o arquivo e exibe preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const canvas = document.getElementById('camera-canvas');
        const preview = document.getElementById('selfie-preview');
        const img = new Image();
        
        img.onload = function() {
            // Desenha imagem no canvas
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Mostra preview
            preview.src = e.target.result;
            document.getElementById('camera-container').style.display = 'none';
            document.getElementById('preview-container').style.display = 'block';
            
            // Esconde feedback após mostrar preview
            setTimeout(function() {
                document.getElementById('selfie-upload-feedback').style.display = 'none';
            }, 1000);
        };
        
        img.src = e.target.result;
    };
    
    reader.readAsDataURL(file);
    event.target.value = ''; // Limpa input para permitir selecionar o mesmo arquivo novamente
}

function verifyFace() {
    const canvas = document.getElementById('camera-canvas');
    const loader = document.getElementById('verification-loader');
    const statusDiv = document.getElementById('verification-status');
    
    // Mostra loader
    loader.style.display = 'block';
    document.getElementById('preview-container').style.display = 'none';
    statusDiv.classList.add('d-none');
    
    // Converte canvas para blob
    canvas.toBlob(function(blob) {
        const formData = new FormData();
        formData.append('verification_selfie', blob, 'selfie.jpg');
        formData.append('cliente_id', clienteId);
        
        fetch('ajax_verify_face.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Debug: mostra o que foi retornado
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            // Verifica se a resposta é OK
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Response text:', text);
                    throw new Error('Erro HTTP ' + response.status + ': ' + text);
                });
            }
            
            // Tenta parsear como JSON
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    console.error('Texto recebido:', text);
                    throw new Error('Resposta inválida do servidor. Verifique os logs.');
                }
            });
        })
        .then(data => {
            loader.style.display = 'none';
            
            if (data.success) {
                // Salva token no campo oculto
                document.getElementById('verification_token').value = data.verification_token;
                
                // Mostra sucesso
                showStatus('success', data.message);
                
                // Mostra badge de verificado
                const faceVerifiedBadge = document.getElementById('face-verified-badge');
                const faceVerificationAlert = document.getElementById('face-verification-alert');
                
                if (faceVerifiedBadge) {
                    faceVerifiedBadge.classList.remove('d-none');
                }
                if (faceVerificationAlert) {
                    faceVerificationAlert.classList.add('d-none');
                }
                
                // Fecha modal após 2 segundos
                setTimeout(function() {
                    bootstrap.Modal.getInstance(document.getElementById('faceVerificationModal')).hide();
                }, 2000);
            } else {
                showStatus('error', data.message);
                document.getElementById('preview-container').style.display = 'block';
            }
        })
        .catch(error => {
            loader.style.display = 'none';
            showStatus('error', 'Erro na requisição: ' + error.message);
            document.getElementById('preview-container').style.display = 'block';
        });
    }, 'image/jpeg', 0.9);
}

function showStatus(type, message) {
    const statusDiv = document.getElementById('verification-status');
    
    if (!statusDiv) {
        console.error('Elemento verification-status não encontrado');
        return;
    }
    
    statusDiv.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info');
    
    if (type === 'success') {
        statusDiv.classList.add('alert-success');
        statusDiv.innerHTML = '<i class="bi bi-check-circle"></i> ' + message;
    } else if (type === 'error') {
        statusDiv.classList.add('alert-danger');
        statusDiv.innerHTML = '<i class="bi bi-x-circle"></i> ' + message;
    } else {
        statusDiv.classList.add('alert-info');
        statusDiv.innerHTML = '<i class="bi bi-info-circle"></i> ' + message;
    }
}

// ============================================
// FUNÇÕES PARA VERIFICAÇÃO POR DOCUMENTO
// ============================================

let docStream = null;

function openDocumentVerificationModal() {
    const modal = new bootstrap.Modal(document.getElementById('documentVerificationModal'));
    modal.show();
    
    document.getElementById('documentVerificationModal').addEventListener('shown.bs.modal', function() {
        startDocumentCamera();
    });
    
    document.getElementById('documentVerificationModal').addEventListener('hidden.bs.modal', function() {
        stopDocumentCamera();
    });
}

function startDocumentCamera() {
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            facingMode: 'environment', // Câmera traseira preferencial
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        } 
    })
    .then(function(mediaStream) {
        docStream = mediaStream;
        document.getElementById('doc-camera-video').srcObject = mediaStream;
        document.getElementById('doc-camera-container').style.display = 'block';
        document.getElementById('doc-preview-container').style.display = 'none';
    })
    .catch(function(err) {
        showDocStatus('error', 'Erro ao acessar câmera: ' + err.message);
    });
}

function stopDocumentCamera() {
    if (docStream) {
        docStream.getTracks().forEach(track => track.stop());
        docStream = null;
    }
}

function captureDocument() {
    const video = document.getElementById('doc-camera-video');
    const canvas = document.getElementById('doc-camera-canvas');
    const preview = document.getElementById('doc-preview');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    const imageDataUrl = canvas.toDataURL('image/jpeg', 0.95);
    preview.src = imageDataUrl;
    
    document.getElementById('doc-camera-container').style.display = 'none';
    document.getElementById('doc-preview-container').style.display = 'block';
    
    stopDocumentCamera();
}

function resetDocumentCamera() {
    document.getElementById('doc-preview-container').style.display = 'none';
    document.getElementById('doc-verification-status').classList.add('d-none');
    document.getElementById('doc-validation-results').style.display = 'none';
    startDocumentCamera();
}

function handleDocumentUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Valida tipo de arquivo
    if (!file.type.match('image/(jpeg|jpg|png)')) {
        alert('Por favor, envie apenas imagens JPG ou PNG.');
        event.target.value = '';
        return;
    }
    
    // Valida tamanho (10MB)
    if (file.size > 10 * 1024 * 1024) {
        alert('Imagem muito grande. Tamanho máximo: 10MB');
        event.target.value = '';
        return;
    }
    
    // Mostra feedback
    document.getElementById('document-upload-feedback').style.display = 'block';
    
    // Para a câmera se estiver ativa
    stopDocumentCamera();
    
    // Lê o arquivo e exibe preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const canvas = document.getElementById('doc-camera-canvas');
        const preview = document.getElementById('doc-preview');
        const img = new Image();
        
        img.onload = function() {
            // Desenha imagem no canvas
            canvas.width = img.width;
            canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Mostra preview
            preview.src = e.target.result;
            document.getElementById('doc-camera-container').style.display = 'none';
            document.getElementById('doc-preview-container').style.display = 'block';
            
            // Esconde feedback após mostrar preview
            setTimeout(function() {
                document.getElementById('document-upload-feedback').style.display = 'none';
            }, 1000);
        };
        
        img.src = e.target.result;
    };
    
    reader.readAsDataURL(file);
    event.target.value = ''; // Limpa input
}

function verifyDocument() {
    const canvas = document.getElementById('doc-camera-canvas');
    const loader = document.getElementById('doc-verification-loader');
    const statusDiv = document.getElementById('doc-verification-status');
    
    loader.style.display = 'block';
    document.getElementById('doc-preview-container').style.display = 'none';
    statusDiv.classList.add('d-none');
    
    canvas.toBlob(function(blob) {
        const formData = new FormData();
        formData.append('document_photo', blob, 'document.jpg');
        formData.append('cliente_id', clienteId);
        
        fetch('ajax_verify_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Debug: mostra o que foi retornado
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            // Verifica se a resposta é OK
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Response text:', text);
                    throw new Error('Erro HTTP ' + response.status + ': ' + text);
                });
            }
            
            // Tenta parsear como JSON
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    console.error('Texto recebido:', text);
                    throw new Error('Resposta inválida do servidor. Verifique os logs.');
                }
            });
        })
        .then(data => {
            loader.style.display = 'none';
            
            if (data.success) {
                // Salva token no campo oculto
                document.getElementById('verification_token').value = data.verification_token;
                
                // Mostra resultados da validação
                displayValidationResults(data);
                
                // Mostra sucesso
                showDocStatus('success', data.message);
                
                // Mostra badge de verificado
                const faceVerifiedBadge = document.getElementById('face-verified-badge');
                const verificationAlert = document.getElementById('verification-alert');
                
                if (faceVerifiedBadge) {
                    faceVerifiedBadge.classList.remove('d-none');
                }
                if (verificationAlert) {
                    verificationAlert.classList.add('d-none');
                }
                
                // Fecha modal após 4 segundos
                setTimeout(function() {
                    bootstrap.Modal.getInstance(document.getElementById('documentVerificationModal')).hide();
                }, 4000);
            } else {
                showDocStatus('error', data.message);
                
                // Mostra resultados parciais se houver
                if (data.validations && Object.keys(data.validations).length > 0) {
                    displayValidationResults(data);
                }
                
                document.getElementById('doc-preview-container').style.display = 'block';
            }
        })
        .catch(error => {
            loader.style.display = 'none';
            showDocStatus('error', 'Erro na requisição: ' + error.message);
            document.getElementById('doc-preview-container').style.display = 'block';
        });
    }, 'image/jpeg', 0.95);
}

function showDocStatus(type, message) {
    const statusDiv = document.getElementById('doc-verification-status');
    
    if (!statusDiv) {
        console.error('Elemento doc-verification-status não encontrado');
        return;
    }
    
    statusDiv.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info');
    
    if (type === 'success') {
        statusDiv.classList.add('alert-success');
        statusDiv.innerHTML = '<i class="bi bi-check-circle"></i> ' + message;
    } else if (type === 'error') {
        statusDiv.classList.add('alert-danger');
        statusDiv.innerHTML = '<i class="bi bi-x-circle"></i> ' + message;
    } else {
        statusDiv.classList.add('alert-info');
        statusDiv.innerHTML = '<i class="bi bi-info-circle"></i> ' + message;
    }
}

function displayValidationResults(data) {
    const resultsDiv = document.getElementById('doc-validation-results');
    const tableDiv = document.getElementById('doc-validation-table');
    
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
    html += '<thead class="table-light"><tr><th>Campo</th><th>Extraído do Documento</th><th>Banco de Dados</th><th>Status</th></tr></thead>';
    html += '<tbody>';
    
    // Resumo do OCR e Face
    html += '<tr class="table-info"><td colspan="4" class="fw-bold text-center">';
    html += '<i class="bi bi-info-circle"></i> OCR Confiança: ' + data.ocr_confidence + '% | ';
    html += 'Similaridade Facial: ' + data.face_similarity + '% | ';
    html += 'Score Total: ' + data.validation_percent + '%';
    html += '</td></tr>';
    
    for (const [field, validation] of Object.entries(data.validations)) {
        const fieldName = field.replace('_', ' ').toUpperCase();
        let statusIcon, rowClass;
        
        if (validation.match === true) {
            statusIcon = '<i class="bi bi-check-circle-fill text-success"></i> Válido';
            rowClass = 'table-success';
        } else if (validation.match === false) {
            statusIcon = '<i class="bi bi-x-circle-fill text-danger"></i> Não confere';
            rowClass = 'table-danger';
        } else {
            statusIcon = '<i class="bi bi-info-circle-fill text-muted"></i> N/A';
            rowClass = '';
        }
        
        html += '<tr class="' + rowClass + '">';
        html += '<td class="fw-bold">' + fieldName + '</td>';
        html += '<td>' + (validation.extracted || '-') + '</td>';
        html += '<td>' + (validation.database || '-') + '</td>';
        html += '<td>' + statusIcon;
        
        if (validation.similarity) {
            html += ' (' + validation.similarity + '%)';
        }
        
        html += '</td></tr>';
    }
    
    html += '</tbody></table></div>';
    
    tableDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}
</script>

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

#camera-video {
    border: 3px solid #0d6efd;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

#face-verification-alert {
    border-left: 4px solid #ffc107;
}

#face-verified-badge {
    border-left: 4px solid #198754;
}
</style>
<?php endif; ?>

<?php require 'footer.php'; ?>