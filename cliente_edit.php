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
    if (($is_admin || $is_analista) && (int)$cliente['id_empresa_master'] !== (int)$user_empresa_id) {
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

// Include do sistema de auditoria
require_once 'includes/audit_logger.php';

// Processa o formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Captura dados ANTIGOS para comparação
        $dados_antigos = [
            'nome_completo' => $cliente['nome_completo'],
            'cpf' => $cliente['cpf'],
            'email' => $cliente['email'],
            'telefone' => $cliente['telefone']
        ];
        
        // Captura dados NOVOS do formulário
        $nome = trim($_POST['nome_completo']);
        $cpf = trim($_POST['cpf']);
        $email = trim($_POST['email']);
        $telefone = trim($_POST['telefone']);
        
        $dados_novos = [
            'nome_completo' => $nome,
            'cpf' => $cpf,
            'email' => $email,
            'telefone' => $telefone
        ];
        
        // Admin/Analista pode editar livremente sem verificação facial/documental
        // As verificações existem apenas como histórico/informação
        $params = [$nome, $cpf, $email, $telefone];
        $sql = "UPDATE kyc_clientes SET nome_completo = ?, cpf = ?, email = ?, telefone = ?";

        $sql .= " WHERE id = ?";
        $params[] = $cliente_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // REGISTRA LOG DE AUDITORIA
        logAlteracaoCliente($pdo, $cliente_id, $dados_antigos, $dados_novos);

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
                
                <!-- IDENTIFICAÇÃO BÁSICA -->
                <h5 class="mt-3 mb-2 text-primary" style="font-size: 1.05rem;">📋 Identificação Básica</h5>
                
                <div class="mb-3">
                    <label for="nome_completo" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($cliente['nome_completo']) ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" value="<?= htmlspecialchars($cliente['cpf'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="rg" class="form-label">RG</label>
                        <input type="text" class="form-control" id="rg" name="rg" value="<?= htmlspecialchars($cliente['rg'] ?? '') ?>" readonly>
                        <small class="text-muted">Extraído automaticamente do documento</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($cliente['data_nascimento'] ?? '') ?>">
                </div>

                <!-- CONTATO -->
                <h5 class="mt-4 mb-2 text-primary" style="font-size: 1.05rem;">📞 Contato</h5>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente['email']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="telefone" class="form-label">Telefone / WhatsApp</label>
                    <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>" placeholder="(11) 98765-4321">
                </div>

                <!-- FILIAÇÃO -->
                <h5 class="mt-4 mb-2 text-primary" style="font-size: 1.05rem;">👨‍👩‍👦 Filiação</h5>
                
                <div class="mb-3">
                    <label for="nome_mae" class="form-label">Nome da Mãe</label>
                    <input type="text" class="form-control" id="nome_mae" name="nome_mae" value="<?= htmlspecialchars($cliente['nome_mae'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="nome_pai" class="form-label">Nome do Pai</label>
                    <input type="text" class="form-control" id="nome_pai" name="nome_pai" value="<?= htmlspecialchars($cliente['nome_pai'] ?? '') ?>">
                </div>

                <!-- ENDEREÇO -->
                <h5 class="mt-4 mb-2 text-primary" style="font-size: 1.05rem;">🏠 Endereço</h5>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="endereco_cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="endereco_cep" name="endereco_cep" value="<?= htmlspecialchars($cliente['endereco_cep'] ?? '') ?>" placeholder="12345-678">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="endereco_rua" class="form-label">Logradouro</label>
                        <input type="text" class="form-control" id="endereco_rua" name="endereco_rua" value="<?= htmlspecialchars($cliente['endereco_rua'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="endereco_numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="endereco_numero" name="endereco_numero" value="<?= htmlspecialchars($cliente['endereco_numero'] ?? '') ?>">
                    </div>
                    <div class="col-md-9 mb-3">
                        <label for="endereco_complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="endereco_complemento" name="endereco_complemento" value="<?= htmlspecialchars($cliente['endereco_complemento'] ?? '') ?>" placeholder="Apto, Bloco, etc.">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label for="endereco_bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="endereco_bairro" name="endereco_bairro" value="<?= htmlspecialchars($cliente['endereco_bairro'] ?? '') ?>">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label for="endereco_cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" id="endereco_cidade" name="endereco_cidade" value="<?= htmlspecialchars($cliente['endereco_cidade'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="endereco_estado" class="form-label">UF</label>
                        <input type="text" class="form-control" id="endereco_estado" name="endereco_estado" value="<?= htmlspecialchars($cliente['endereco_estado'] ?? '') ?>" maxlength="2" placeholder="SP">
                    </div>
                </div>

                <hr class="my-4">

                <button type="submit" class="btn btn-primary" id="btn-save-changes">Salvar Alterações</button>
                <a href="clientes.php" class="btn btn-secondary">Voltar para a Lista</a>
            </form>
        </div>

        <!-- COLUNA DIREITA: Documentos e Infos -->
        <div class="col-md-5">
            <div class="p-3 border rounded bg-light">
                <!-- Header com Status -->
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h4 class="mb-0">Documentos e Infos</h4>
                    <?php
                    $status_map = ['ativo' => 'success', 'inativo' => 'secondary', 'pendente' => 'warning'];
                    $status_color = $status_map[$cliente['status']] ?? 'secondary';
                    $status_icon = ($cliente['status'] === 'ativo') ? '🔓' : '🔒';
                    ?>
                    <span class="badge bg-<?= $status_color ?> fs-6"><?= $status_icon ?> <?= ucfirst($cliente['status']) ?></span>
                </div>
                
                <!-- Info Básica -->
                <div class="mb-3">
                    <p class="mb-1"><small class="text-muted">ID do Cliente:</small> <strong>#<?= htmlspecialchars($cliente['id']) ?></strong></p>
                    <p class="mb-1"><small class="text-muted">Data de Cadastro:</small> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cliente['created_at']))) ?></p>
                    <p class="mb-0"><small class="text-muted">Última Atualização:</small> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($cliente['updated_at']))) ?></p>
                </div>

                <!-- Selfie Enviada -->
                <div class="text-center mb-3">
                    <h6 class="text-muted mb-2">Selfie Enviada</h6>
                    <?php
                    if (!empty($cliente['selfie_path'])) {
                        $path_servidor = htmlspecialchars($cliente['selfie_path']);
                        $ext = strtolower(pathinfo($path_servidor, PATHINFO_EXTENSION));
                        $cache_buster = file_exists($path_servidor) ? '?v=' . filemtime($path_servidor) : '';
                        $path_web = '/' . ltrim($path_servidor, '/') . $cache_buster;

                        if (file_exists($path_servidor)) { 
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                echo '<img src="' . $path_web . '" alt="Selfie do Cliente" class="img-fluid rounded border shadow-sm" style="max-height: 250px; max-width: 100%;">';
                            } elseif ($ext == 'pdf') {
                                echo '<div class="alert alert-info text-center mb-0">';
                                echo '<a href="' . $path_web . '" target="_blank" class="alert-link"><i class="bi bi-file-pdf"></i> Visualizar PDF da Selfie</a>';
                                echo '</div>';
                            } else {
                                echo '<p class="text-muted mb-0"><a href="' . $path_web . '" target="_blank"><i class="bi bi-paperclip"></i> ' . basename($path_servidor) . '</a></p>';
                            }
                        } else {
                             echo '<div class="alert alert-danger mb-0 small"><i class="bi bi-exclamation-triangle"></i> Arquivo não encontrado</div>';
                        }
                    } else {
                        echo '<div class="alert alert-warning mb-0 small"><i class="bi bi-camera-fill"></i> Nenhuma selfie enviada</div>';
                    }
                    ?>
                </div>

                <!-- Documento Enviado -->
                <div class="text-center mb-3">
                    <h6 class="text-muted mb-2">Documento Enviado</h6>
                    <?php
                    if (!empty($cliente['documento_foto_path'])) {
                        $doc_path = htmlspecialchars($cliente['documento_foto_path']);
                        $ext = strtolower(pathinfo($doc_path, PATHINFO_EXTENSION));
                        $cache_buster = file_exists($doc_path) ? '?v=' . filemtime($doc_path) : '';
                        $doc_web = '/' . ltrim($doc_path, '/') . $cache_buster;

                        if (file_exists($doc_path)) { 
                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                echo '<img src="' . $doc_web . '" alt="Documento do Cliente" class="img-fluid rounded border shadow-sm" style="max-height: 250px; max-width: 100%;">';
                            } elseif ($ext == 'pdf') {
                                echo '<div class="alert alert-info text-center mb-0">';
                                echo '<a href="' . $doc_web . '" target="_blank" class="alert-link"><i class="bi bi-file-pdf"></i> Visualizar PDF do Documento</a>';
                                echo '</div>';
                            } else {
                                echo '<p class="text-muted mb-0"><a href="' . $doc_web . '" target="_blank"><i class="bi bi-paperclip"></i> ' . basename($doc_path) . '</a></p>';
                            }
                        } else {
                             echo '<div class="alert alert-danger mb-0 small"><i class="bi bi-exclamation-triangle"></i> Arquivo não encontrado</div>';
                        }
                    } else {
                        echo '<div class="alert alert-warning mb-0 small"><i class="bi bi-file-earmark"></i> Nenhum documento enviado</div>';
                    }
                    ?>
                </div>

                <!-- Botões de Verificação -->
                <div class="d-grid gap-2 mb-3">
                    <?php if (!empty($cliente['selfie_path'])): ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="viewDocument('selfie')">
                        <i class="bi bi-eye"></i> Ver Selfie Cadastrada
                    </button>
                    
                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#faceVerificationModal">
                        <i class="bi bi-camera-video"></i> Verificar Identidade (Selfie ao Vivo)
                    </button>
                    <?php endif; ?>
                    
                    <?php if (!empty($cliente['documento_foto_path'])): ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="viewDocument('documento')">
                        <i class="bi bi-eye"></i> Ver Documento (RG/CNH)
                    </button>
                    <?php endif; ?>
                    
                    <?php if (!empty($cliente['selfie_path']) && !empty($cliente['documento_foto_path'])): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="verifyBothDocuments(event)">
                        <i class="bi bi-shield-check"></i> Verificar Documento Completo
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Card de Verificação Documental -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-shield-check"></i> Verificação Documental</h6>
                    </div>
                    <div class="card-body p-2">
                        <?php
                        // Busca última verificação de documento
                        $stmt = $pdo->prepare("
                            SELECT * FROM document_verifications 
                            WHERE cliente_id = ?
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $stmt->execute([$cliente['id']]);
                        $verif_doc = $stmt->fetch();
                        
                        if ($verif_doc):
                            // Campos corretos da tabela document_verifications
                            $face_similarity = $verif_doc['face_similarity'] ?? 0;
                            $ocr_confidence = $verif_doc['ocr_confidence'] ?? 0;
                            $validation_percent = $verif_doc['validation_percent'] ?? 0;
                            $doc_status = $verif_doc['verification_result'];
                            
                            if ($doc_status === 'success'):
                        ?>
                        <div class="alert alert-success mb-2 py-2 small">
                            <strong><i class="bi bi-check-circle-fill"></i> Aprovado</strong><br>
                            <small>
                                Face: <?= number_format($face_similarity, 1) ?>% | 
                                OCR: <?= number_format($ocr_confidence, 1) ?>% | 
                                Validação: <?= number_format($validation_percent, 1) ?>%<br>
                                <?= date('d/m/Y H:i', strtotime($verif_doc['created_at'])) ?>
                            </small>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger mb-2 py-2 small">
                            <strong><i class="bi bi-x-circle-fill"></i> Rejeitado</strong><br>
                            <small>
                                Face: <?= number_format($face_similarity, 1) ?>% | 
                                OCR: <?= number_format($ocr_confidence, 1) ?>% | 
                                Validação: <?= number_format($validation_percent, 1) ?>%<br>
                                <?= date('d/m/Y H:i', strtotime($verif_doc['created_at'])) ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0 py-2 small">
                            <i class="bi bi-exclamation-triangle"></i> Não verificado
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Card de Verificação Facial -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="bi bi-person-check"></i> Verificação Facial</h6>
                    </div>
                    <div class="card-body p-2">
                        <?php
                        // Busca última verificação facial
                        $stmt = $pdo->prepare("
                            SELECT * FROM facial_verifications 
                            WHERE cliente_id = ?
                            ORDER BY created_at DESC LIMIT 1
                        ");
                        $stmt->execute([$cliente['id']]);
                        $verif_face = $stmt->fetch();
                        
                        if ($verif_face):
                            $similaridade = $verif_face['similarity_score'] ?? 0;
                            $face_status = $verif_face['verification_result'];
                            
                            if ($face_status === 'success'):
                        ?>
                        <div class="alert alert-success mb-0 py-2 small">
                            <strong><i class="bi bi-check-circle-fill"></i> Aprovado</strong><br>
                            <small>
                                Similaridade: <?= number_format($similaridade, 1) ?>%<br>
                                <?= date('d/m/Y H:i', strtotime($verif_face['created_at'])) ?>
                            </small>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger mb-0 py-2 small">
                            <strong><i class="bi bi-x-circle-fill"></i> Rejeitado</strong><br>
                            <small>
                                Similaridade: <?= number_format($similaridade, 1) ?>%<br>
                                <?= date('d/m/Y H:i', strtotime($verif_face['created_at'])) ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0 py-2 small">
                            <i class="bi bi-exclamation-triangle"></i> Não verificado
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($empresas_kyc)): ?>
    <!-- Empresas KYC Vinculadas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="accordion" id="accordionEmpresasKYC">
                <div class="accordion-item border-0 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button bg-success text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEmpresas" aria-expanded="true">
                            <i class="bi bi-building-check me-2"></i> 
                            <strong>Empresas Cadastradas (KYC)</strong>
                            <span class="badge bg-light text-success ms-2"><?= count($empresas_kyc) ?></span>
                        </button>
                    </h2>
                    <div id="collapseEmpresas" class="accordion-collapse collapse show" data-bs-parent="#accordionEmpresasKYC">
                        <div class="accordion-body">
                            <?php foreach ($empresas_kyc as $index => $empresa): 
                                $status_colors = [
                                    'aprovado' => 'bg-success',
                                    'novo_registro' => 'bg-primary',
                                    'pendente' => 'bg-warning text-dark',
                                    'em_analise' => 'bg-info',
                                    'reprovado' => 'bg-danger',
                                    'aguardando_documentos' => 'bg-secondary'
                                ];
                                $status_badge = $status_colors[$empresa['status']] ?? 'bg-light text-dark';
                                $empresa_master = $empresa['empresa_master_nome'] ?: 'Verify KYC';
                            ?>
                            <?php if ($index > 0): ?><hr class="my-3"><?php endif; ?>
                            <div class="empresa-kyc-item">
                                <div class="row align-items-center mb-2">
                                    <div class="col-md-6">
                                        <h6 class="mb-1">
                                            <i class="bi bi-building"></i> 
                                            <?= htmlspecialchars($empresa['razao_social']) ?>
                                        </h6>
                                        <p class="mb-0 small text-muted">
                                            <i class="bi bi-hash"></i> CNPJ: <code><?= htmlspecialchars($empresa['cnpj']) ?></code>
                                        </p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted small d-block">Status KYC</label>
                                        <span class="badge <?= $status_badge ?>">
                                            <?= ucfirst(str_replace('_', ' ', $empresa['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <a href="kyc_evaluate.php?id=<?= $empresa['id'] ?>" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="bi bi-eye"></i> Ver KYC
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="row small">
                                    <div class="col-md-4">
                                        <span class="text-muted">Cadastrado:</span> <?= date('d/m/Y H:i', strtotime($empresa['data_criacao'])) ?>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted">Atualizado:</span> <?= date('d/m/Y H:i', strtotime($empresa['data_atualizacao'])) ?>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-shield-check"></i> <?= htmlspecialchars($empresa_master) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($lead_original): ?>
    <!-- Histórico do Lead Original -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="accordion" id="accordionLeadOriginal">
                <div class="accordion-item border-0 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLead" aria-expanded="false">
                            <i class="bi bi-funnel-fill me-2"></i> 
                            <strong>Lead Original</strong>
                            <span class="badge bg-light text-primary ms-2">#<?= $lead_original['id'] ?></span>
                        </button>
                    </h2>
                    <div id="collapseLead" class="accordion-collapse collapse" data-bs-parent="#accordionLeadOriginal">
                        <div class="accordion-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="text-muted small">Status do Lead</label>
                            <p class="mb-0">
                                <?php
                                $badge_classes = [
                                    'novo' => 'bg-primary',
                                    'contatado' => 'bg-info',
                                    'qualificado' => 'bg-warning text-dark',
                                    'convertido' => 'bg-success',
                                    'perdido' => 'bg-secondary'
                                ];
                                $badge_class = $badge_classes[$lead_original['status']] ?? 'bg-light text-dark';
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
                        <a href="lead_detail.php?id=<?= $lead_original['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i> Ver Detalhes Completos do Lead
                        </a>
                    </div>
                        </div>
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

// Dados dos documentos do cliente
const selfiePath = '<?= !empty($cliente['selfie_path']) ? addslashes($cliente['selfie_path']) : '' ?>';
const documentoPath = '<?= !empty($cliente['documento_foto_path']) ? addslashes($cliente['documento_foto_path']) : '' ?>';

// Função para visualizar documento em modal
function viewDocument(type) {
    const modalTitle = type === 'selfie' ? 'Selfie do Cliente' : 'Documento (RG/CNH)';
    const imagePath = type === 'selfie' ? selfiePath : documentoPath;
    
    if (!imagePath) {
        alert('Documento não encontrado');
        return;
    }
    
    // Criar modal de visualização
    const modal = document.createElement('div');
    modal.className = 'modal fade show';
    modal.style.display = 'block';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${modalTitle}</h5>
                    <button type="button" class="btn-close" onclick="this.closest('.modal').remove();document.body.classList.remove('modal-open')"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="/${imagePath}?v=${Date.now()}" class="img-fluid" alt="${modalTitle}" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove();document.body.classList.remove('modal-open')">Fechar</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.classList.add('modal-open');
}

// Função para verificar ambos os documentos
async function verifyBothDocuments(event) {
    if (!selfiePath || !documentoPath) {
        alert('É necessário ter selfie e documento enviados');
        return;
    }
    
    const confirmMsg = 'Deseja verificar a identidade do cliente comparando a selfie com o documento (RG/CNH)?';
    if (!confirm(confirmMsg)) return;
    
    try {
        // Criar FormData com ambos os documentos
        const formData = new FormData();
        
        // Buscar as imagens
        const selfieBlob = await fetch(`/${selfiePath}?v=${Date.now()}`).then(r => r.blob());
        const documentoBlob = await fetch(`/${documentoPath}?v=${Date.now()}`).then(r => r.blob());
        
        // IMPORTANTE: ajax_verify_document.php espera 'document_photo' (não 'document')
        formData.append('document_photo', documentoBlob, 'document.jpg');
        formData.append('cliente_id', clienteId);
        
        // Mostrar loader
        const btn = event ? event.target : null;
        let originalText = '';
        if (btn) {
            originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verificando...';
        }
        
        // Enviar para verificação
        const response = await fetch('ajax_verify_document.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
        
        if (result.success) {
            // Os campos corretos retornados pelo ajax_verify_document.php
            const faceSimilarity = result.face_similarity || 0;
            const ocrConfidence = result.ocr_confidence || 0;
            const validationPercent = result.validation_percent || 0;
            
            alert(`✅ Verificação Concluída!\n\nSimilaridade Facial: ${faceSimilarity.toFixed(2)}%\nConfiança OCR: ${ocrConfidence.toFixed(2)}%\nValidação: ${validationPercent.toFixed(2)}%\n\nStatus: APROVADO`);
            
            // Recarregar página para mostrar novo status
            location.reload();
        } else {
            alert(`❌ Erro na verificação: ${result.message || 'Erro desconhecido'}`);
        }
        
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao processar verificação: ' + error.message);
    }
}

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
    // Event listeners para o modal de verificação facial
    const faceModalEl = document.getElementById('faceVerificationModal');
    if (faceModalEl) {
        faceModalEl.addEventListener('shown.bs.modal', function() {
            console.log('Modal de verificação facial aberto - Iniciando câmera...');
            startCamera();
        });
        
        faceModalEl.addEventListener('hidden.bs.modal', function() {
            console.log('Modal de verificação facial fechado - Parando câmera...');
            stopCamera();
            // Reseta o estado do modal
            document.getElementById('camera-container').style.display = 'block';
            document.getElementById('preview-container').style.display = 'none';
            document.getElementById('verification-loader').style.display = 'none';
            document.getElementById('verification-status').classList.add('d-none');
        });
    }
});

function openFaceVerificationModal() {
    const modal = new bootstrap.Modal(document.getElementById('faceVerificationModal'));
    modal.show();
    // Câmera será iniciada pelo event listener 'shown.bs.modal'
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
                // Mostra sucesso
                const similarity = data.similarity_score || data.similarity || 0;
                showStatus('success', `✅ Verificação Facial Aprovada!\n\nSimilaridade: ${similarity.toFixed(2)}%\n\n${data.message || 'Identidade confirmada!'}`);
                
                // Fecha modal e recarrega após 3 segundos
                setTimeout(function() {
                    const modalEl = document.getElementById('faceVerificationModal');
                    if (modalEl) {
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
                    // Recarrega página para mostrar verificação atualizada
                    location.reload();
                }, 3000);
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
// FUNÇÕES PARA VERIFICAÇÃO POR DOCUMENTO (DIRETO, SEM MODAL)
// ============================================

let docCanvasDirect = null;

function handleDocumentUploadDirect(event) {
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
    
    // Lê o arquivo e exibe preview
    const reader = new FileReader();
    reader.onload = function(e) {
        // Cria canvas se não existir
        if (!docCanvasDirect) {
            docCanvasDirect = document.createElement('canvas');
        }
        
        const preview = document.getElementById('doc-image-preview');
        const img = new Image();
        
        img.onload = function() {
            // Desenha imagem no canvas
            docCanvasDirect.width = img.width;
            docCanvasDirect.height = img.height;
            const ctx = docCanvasDirect.getContext('2d');
            ctx.drawImage(img, 0, 0);
            
            // Mostra preview
            preview.src = e.target.result;
            document.getElementById('doc-preview-direct').style.display = 'block';
        };
        
        img.src = e.target.result;
    };
    
    reader.readAsDataURL(file);
    event.target.value = ''; // Limpa input
}

function resetDocumentDirect() {
    document.getElementById('doc-preview-direct').style.display = 'none';
    document.getElementById('doc-verification-status').classList.add('d-none');
    document.getElementById('doc-validation-results-direct').style.display = 'none';
    document.getElementById('document-upload-direct').value = '';
    docCanvasDirect = null;
}

function verifyDocumentDirect() {
    if (!docCanvasDirect) {
        alert('Nenhum documento foi carregado!');
        return;
    }
    
    const loader = document.getElementById('doc-verification-loader-direct');
    const statusDiv = document.getElementById('doc-verification-status');
    
    loader.style.display = 'block';
    document.getElementById('doc-preview-direct').style.display = 'none';
    statusDiv.classList.add('d-none');
    
    docCanvasDirect.toBlob(function(blob) {
        const formData = new FormData();
        formData.append('document_photo', blob, 'document.jpg');
        formData.append('cliente_id', clienteId);
        
        fetch('ajax_verify_document.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            
            // SEMPRE tenta parsear como JSON, mesmo em caso de erro HTTP
            return response.text().then(text => {
                console.log('Response text:', text);
                
                try {
                    const data = JSON.parse(text);
                    return { data, status: response.status };
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    throw new Error('Resposta inválida do servidor (status ' + response.status + ')');
                }
            });
        })
        .then(({ data, status }) => {
            loader.style.display = 'none';
            
            // DEBUG: Mostra todos os dados retornados
            console.log('=== DADOS RETORNADOS ===');
            console.log('Success:', data.success);
            console.log('Message:', data.message);
            console.log('OCR Confidence:', data.ocr_confidence);
            console.log('Face Similarity:', data.face_similarity);
            console.log('Validation Percent:', data.validation_percent);
            console.log('Validations:', data.validations);
            console.log('Extracted Data:', data.extracted_data);
            
            if (data.success) {
                // Salva token no campo oculto
                document.getElementById('verification_token').value = data.verification_token;
                
                // Mostra sucesso
                showDocStatusDirect('success', '<strong>✅ Verificação Aprovada!</strong><br>' + data.message);
                
                // Exibe tabela de validação
                if (data.validations) {
                    displayValidationResultsDirect(data);
                }
                
                // Mostra badge de verificado
                const verifiedBadge = document.getElementById('face-verified-badge');
                
                if (verifiedBadge) {
                    verifiedBadge.classList.remove('d-none');
                }
                
            } else {
                // FALHA NA VALIDAÇÃO (HTTP 400 com JSON)
                let errorHtml = '<strong>❌ DADOS NÃO CONFEREM!</strong><br>';
                errorHtml += '<p class="mb-2">' + (data.message || 'Erro desconhecido') + '</p>';
                
                // Barra de progresso do score
                if (data.validation_percent !== undefined) {
                    const scoreColor = data.validation_percent >= 75 ? 'success' : 
                                     data.validation_percent >= 50 ? 'warning' : 'danger';
                    errorHtml += '<div class="mt-3">';
                    errorHtml += '<small class="text-muted d-block mb-1">Score de Validação:</small>';
                    errorHtml += '<div class="progress" style="height: 25px;">';
                    errorHtml += '<div class="progress-bar bg-' + scoreColor + '" style="width: ' + data.validation_percent + '%">';
                    errorHtml += '<strong>' + data.validation_percent + '%</strong>';
                    errorHtml += '</div></div>';
                    errorHtml += '</div>';
                }
                
                showDocStatusDirect('error', errorHtml);
                
                // Exibe tabela de validação mesmo quando falha (IMPORTANTE!)
                if (data.validations && Object.keys(data.validations).length > 0) {
                    displayValidationResultsDirect(data);
                }
                
                document.getElementById('doc-preview-direct').style.display = 'block';
            }
        })
        .catch(error => {
            loader.style.display = 'none';
            console.error('Erro capturado:', error);
            showDocStatusDirect('error', 'Erro na requisição: ' + error.message);
            document.getElementById('doc-preview-direct').style.display = 'block';
        });
    }, 'image/jpeg', 0.95);
}

function showDocStatusDirect(type, message) {
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

function displayValidationResultsDirect(data) {
    const resultsDiv = document.getElementById('doc-validation-results-direct');
    
    // Usa a mesma lógica de displayValidationResults mas em outro container
    let passed = 0;
    let failed = 0;
    for (const validation of Object.values(data.validations)) {
        if (validation.match === true) passed++;
        else if (validation.match === false) failed++;
    }
    
    let html = '<div class="alert alert-light border">';
    
    html += '<div class="d-flex justify-content-between align-items-center mb-3">';
    html += '<h6 class="mb-0"><i class="bi bi-clipboard-data"></i> Resultado da Validação</h6>';
    html += '<div>';
    if (passed > 0) {
        html += '<span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> ' + passed + ' Correto(s)</span>';
    }
    if (failed > 0) {
        html += '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> ' + failed + ' Divergente(s)</span>';
    }
    html += '</div></div>';
    
    html += '<div class="row mb-3">';
    html += '<div class="col-md-4 text-center">';
    html += '<div class="border rounded p-2 bg-white">';
    html += '<small class="text-muted d-block">OCR Confiança</small>';
    html += '<strong class="fs-5">' + (data.ocr_confidence || '0') + '%</strong>';
    html += '</div></div>';
    html += '<div class="col-md-4 text-center">';
    html += '<div class="border rounded p-2 bg-white">';
    html += '<small class="text-muted d-block">Similaridade Facial</small>';
    html += '<strong class="fs-5">' + (data.face_similarity || '0') + '%</strong>';
    html += '</div></div>';
    html += '<div class="col-md-4 text-center">';
    html += '<div class="border rounded p-2 bg-white">';
    html += '<small class="text-muted d-block">Score Total</small>';
    html += '<strong class="fs-5">' + (data.validation_percent || '0') + '%</strong>';
    html += '</div></div>';
    html += '</div>';
    
    html += '<div class="table-responsive">';
    html += '<table class="table table-sm table-bordered mb-0">';
    html += '<thead class="table-light">';
    html += '<tr><th width="20%">Campo</th><th width="35%">Extraído</th><th width="35%">Cadastro</th><th width="10%" class="text-center">Status</th></tr>';
    html += '</thead><tbody>';
    
    for (const [field, validation] of Object.entries(data.validations)) {
        const fieldName = field.replace(/_/g, ' ').toUpperCase();
        let statusIcon, rowClass;
        
        if (validation.match === true) {
            statusIcon = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
            rowClass = 'table-success';
        } else if (validation.match === false) {
            statusIcon = '<i class="bi bi-x-circle-fill text-danger fs-5"></i>';
            rowClass = 'table-danger';
        } else {
            statusIcon = '<i class="bi bi-dash-circle text-muted fs-5"></i>';
            rowClass = '';
        }
        
        html += '<tr class="' + rowClass + '">';
        html += '<td class="fw-bold">' + fieldName + '</td>';
        html += '<td><code>' + (validation.extracted || '<span class="text-muted">-</span>') + '</code></td>';
        html += '<td><code>' + (validation.database || '<span class="text-muted">-</span>') + '</code></td>';
        html += '<td class="text-center">' + statusIcon;
        if (validation.similarity) {
            html += '<br><small class="text-muted">' + validation.similarity + '%</small>';
        }
        html += '</td></tr>';
    }
    
    html += '</tbody></table></div>';
    html += '</div>';
    
    resultsDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}


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
                // Mostra erro detalhado com score
                let errorHtml = '<strong>❌ DADOS NÃO CONFEREM!</strong><br>';
                errorHtml += '<p class="mb-2">' + data.message + '</p>';
                
                // Barra de progresso do score
                if (data.validation_percent !== undefined) {
                    const scoreColor = data.validation_percent >= 75 ? 'success' : 
                                     data.validation_percent >= 50 ? 'warning' : 'danger';
                    errorHtml += '<div class="mt-3">';
                    errorHtml += '<small class="text-muted d-block mb-1">Score de Validação:</small>';
                    errorHtml += '<div class="progress" style="height: 25px;">';
                    errorHtml += '<div class="progress-bar bg-' + scoreColor + '" style="width: ' + data.validation_percent + '%">';
                    errorHtml += '<strong>' + data.validation_percent + '%</strong>';
                    errorHtml += '</div></div>';
                    errorHtml += '</div>';
                }
                
                showDocStatus('error', errorHtml);
                
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
    
    // Conta quantos campos passaram e falharam
    let passed = 0;
    let failed = 0;
    for (const validation of Object.values(data.validations)) {
        if (validation.match === true) passed++;
        else if (validation.match === false) failed++;
    }
    
    let html = '<div class="alert alert-light border">';
    
    // Cabeçalho com resumo
    html += '<div class="d-flex justify-content-between align-items-center mb-3">';
    html += '<h6 class="mb-0"><i class="bi bi-clipboard-data"></i> Resultado da Validação</h6>';
    html += '<div>';
    if (passed > 0) {
        html += '<span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> ' + passed + ' Correto(s)</span>';
    }
    if (failed > 0) {
        html += '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> ' + failed + ' Divergente(s)</span>';
    }
    html += '</div></div>';
    
    // Métricas resumidas
    html += '<div class="row mb-3">';
    html += '<div class="col-md-4 text-center">';
    html += '<div class="border rounded p-2 bg-white">';
    html += '<small class="text-muted d-block">OCR Confiança</small>';
    html += '<strong class="fs-5">' + (data.ocr_confidence || '0') + '%</strong>';
    html += '</div></div>';
    html += '<div class="col-md-4 text-center">';
    html += '<div class="border rounded p-2 bg-white">';
    html += '<small class="text-muted d-block">Similaridade Facial</small>';
    html += '<strong class="fs-5">' + (data.face_similarity || '0') + '%</strong>';
    html += '</div></div>';
    html += '<div class="col-md-4 text-center">';
    html += '<div class="border rounded p-2 bg-white">';
    html += '<small class="text-muted d-block">Score Total</small>';
    html += '<strong class="fs-5">' + (data.validation_percent || '0') + '%</strong>';
    html += '</div></div>';
    html += '</div>';
    
    // Tabela de campos
    html += '<div class="table-responsive">';
    html += '<table class="table table-sm table-bordered mb-0">';
    html += '<thead class="table-light">';
    html += '<tr><th width="20%">Campo</th><th width="35%">Extraído do Documento</th><th width="35%">Cadastro no Sistema</th><th width="10%" class="text-center">Status</th></tr>';
    html += '</thead><tbody>';
    
    for (const [field, validation] of Object.entries(data.validations)) {
        const fieldName = field.replace(/_/g, ' ').toUpperCase();
        let statusIcon, rowClass;
        
        if (validation.match === true) {
            statusIcon = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
            rowClass = 'table-success';
        } else if (validation.match === false) {
            statusIcon = '<i class="bi bi-x-circle-fill text-danger fs-5"></i>';
            rowClass = 'table-danger';
        } else {
            statusIcon = '<i class="bi bi-dash-circle text-muted fs-5"></i>';
            rowClass = '';
        }
        
        html += '<tr class="' + rowClass + '">';
        html += '<td class="fw-bold">' + fieldName + '</td>';
        html += '<td><code>' + (validation.extracted || '<span class="text-muted">Não detectado</span>') + '</code></td>';
        html += '<td><code>' + (validation.database || '<span class="text-muted">Não cadastrado</span>') + '</code></td>';
        html += '<td class="text-center">';
        html += statusIcon;
        
        if (validation.similarity) {
            html += '<br><small class="text-muted">' + validation.similarity + '%</small>';
        }
        
        html += '</td></tr>';
    }
    
    html += '</tbody></table></div>';
    html += '</div>';
    
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