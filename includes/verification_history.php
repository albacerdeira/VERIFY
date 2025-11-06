<?php
/**
 * Componente: Histórico Completo de Auditoria e Verificações
 * Mostra TUDO que aconteceu com o cliente:
 * - Verificações faciais e documentais
 * - Alterações de dados (quem editou, quando, o que mudou)
 * - Atividades KYC
 * - Webhooks enviados
 * - Interdependências entre ações
 * 
 * Uso:
 * $cliente_id = 123;
 * include 'includes/verification_history.php';
 */

if (!isset($cliente_id)) {
    echo '<div class="alert alert-danger">Erro: cliente_id não foi definido</div>';
    return;
}

// ============================================
// BUSCA HISTÓRICO CONSOLIDADO DE TODAS AS FONTES
// ============================================

// 1. VERIFICAÇÕES DOCUMENTAIS
$stmt_doc = $pdo->prepare("
    SELECT 
        'document_verification' as tipo,
        'Verificação Documental' as tipo_legivel,
        dv.id,
        dv.created_at,
        dv.verification_result as resultado,
        dv.validation_score as score,
        dv.validation_max_score as max_score,
        dv.ocr_confidence,
        dv.face_similarity,
        dv.extracted_data,
        dv.validations,
        u.nome as usuario_nome,
        u.email as usuario_email,
        u.role as usuario_tipo,
        dv.ip_address,
        dv.user_agent,
        NULL as detalhes_extras,
        NULL as campos_alterados,
        NULL as valores_antigos,
        NULL as valores_novos
    FROM document_verifications dv
    LEFT JOIN usuarios u ON dv.usuario_id = u.id
    WHERE dv.cliente_id = ?
");

// 2. VERIFICAÇÕES FACIAIS
$stmt_facial = $pdo->prepare("
    SELECT 
        'facial_verification' as tipo,
        'Verificação Facial (Selfie)' as tipo_legivel,
        fv.id,
        fv.created_at,
        fv.verification_result as resultado,
        fv.similarity_score as score,
        NULL as max_score,
        NULL as ocr_confidence,
        NULL as face_similarity,
        NULL as extracted_data,
        NULL as validations,
        u.nome as usuario_nome,
        u.email as usuario_email,
        u.role as usuario_tipo,
        fv.ip_address,
        fv.user_agent,
        NULL as detalhes_extras,
        NULL as campos_alterados,
        NULL as valores_antigos,
        NULL as valores_novos
    FROM facial_verifications fv
    LEFT JOIN usuarios u ON fv.usuario_id = u.id
    WHERE fv.cliente_id = ?
");

// 3. ALTERAÇÕES DE DADOS (kyc_logs)
// Nota: kyc_logs.empresa_id se refere ao kyc_empresas.id (não id_empresa_master)
$stmt_logs = $pdo->prepare("
    SELECT 
        'data_change' as tipo,
        'Alteração de Dados' as tipo_legivel,
        kl.id,
        kl.data_ocorrencia as created_at,
        'info' as resultado,
        NULL as score,
        NULL as max_score,
        NULL as ocr_confidence,
        NULL as face_similarity,
        NULL as extracted_data,
        NULL as validations,
        u.nome as usuario_nome,
        u.email as usuario_email,
        u.role as usuario_tipo,
        NULL as ip_address,
        NULL as user_agent,
        kl.detalhes as detalhes_extras,
        kl.acao as campos_alterados,
        NULL as valores_antigos,
        NULL as valores_novos
    FROM kyc_logs kl
    LEFT JOIN usuarios u ON kl.usuario_id = u.id
    INNER JOIN kyc_empresas ke ON kl.empresa_id = ke.id
    WHERE ke.cliente_id = ?
");

// 4. ATIVIDADES KYC (kyc_log_atividades)
$stmt_atividades = $pdo->prepare("
    SELECT 
        'kyc_activity' as tipo,
        'Atividade KYC' as tipo_legivel,
        kla.id,
        kla.timestamp as created_at,
        'info' as resultado,
        NULL as score,
        NULL as max_score,
        NULL as ocr_confidence,
        NULL as face_similarity,
        NULL as extracted_data,
        NULL as validations,
        COALESCE(u.nome, kla.usuario_nome, 'Sistema') as usuario_nome,
        u.email as usuario_email,
        u.role as usuario_tipo,
        NULL as ip_address,
        NULL as user_agent,
        kla.acao as detalhes_extras,
        NULL as campos_alterados,
        NULL as valores_antigos,
        kla.dados_avaliacao_snapshot as valores_novos
    FROM kyc_log_atividades kla
    LEFT JOIN kyc_empresas ke ON kla.kyc_empresa_id = ke.id
    LEFT JOIN kyc_clientes kc ON ke.cliente_id = kc.id
    LEFT JOIN usuarios u ON kla.usuario_id = u.id
    WHERE kc.id = ?
");

// 5. WEBHOOKS ENVIADOS (leads_webhook_log)
// Nota: lwl.empresa_id se refere ao id_empresa_master, precisamos converter
$stmt_webhooks = $pdo->prepare("
    SELECT 
        'webhook' as tipo,
        'Webhook Enviado' as tipo_legivel,
        lwl.id,
        lwl.created_at,
        CASE WHEN lwl.success = 1 THEN 'success' ELSE 'failed' END as resultado,
        lwl.response_code as score,
        NULL as max_score,
        NULL as ocr_confidence,
        NULL as face_similarity,
        NULL as extracted_data,
        NULL as validations,
        'Sistema Automático' as usuario_nome,
        NULL as usuario_email,
        'sistema' as usuario_tipo,
        NULL as ip_address,
        NULL as user_agent,
        CONCAT('URL: ', lwl.webhook_url, ' | Tempo: ', lwl.tempo_resposta_ms, 'ms') as detalhes_extras,
        lwl.payload_enviado as campos_alterados,
        NULL as valores_antigos,
        lwl.response_body as valores_novos
    FROM leads_webhook_log lwl
    LEFT JOIN leads l ON lwl.lead_id = l.id
    INNER JOIN kyc_clientes kc ON lwl.empresa_id = kc.id_empresa_master
    WHERE kc.id = ?
");

// Executa todas as queries com tratamento de erro
try {
    $stmt_doc->execute([$cliente_id]);
    $doc_records = $stmt_doc->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar document_verifications: " . $e->getMessage());
    $doc_records = [];
}

try {
    $stmt_facial->execute([$cliente_id]);
    $facial_records = $stmt_facial->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar facial_verifications: " . $e->getMessage());
    $facial_records = [];
}

try {
    $stmt_logs->execute([$cliente_id]);
    $log_records = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar kyc_logs: " . $e->getMessage());
    $log_records = [];
}

try {
    $stmt_atividades->execute([$cliente_id]);
    $atividade_records = $stmt_atividades->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar kyc_log_atividades: " . $e->getMessage());
    $atividade_records = [];
}

try {
    $stmt_webhooks->execute([$cliente_id]);
    $webhook_records = $stmt_webhooks->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar leads_webhook_log: " . $e->getMessage());
    $webhook_records = [];
}

// MESCLA TODOS OS REGISTROS EM UMA ÚNICA TIMELINE
$verification_history = array_merge(
    $doc_records,
    $facial_records,
    $log_records,
    $atividade_records,
    $webhook_records
);

// Ordena por data (mais recente primeiro)
usort($verification_history, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Busca último status de verificação
try {
    $stmt_last = $pdo->prepare("
        SELECT 
            MAX(CASE WHEN dv.id IS NOT NULL THEN dv.created_at END) as ultima_doc,
            MAX(CASE WHEN dv.id IS NOT NULL THEN dv.verification_result END) as status_doc,
            MAX(CASE WHEN dv.id IS NOT NULL THEN dv.validation_score END) as score_doc,
            MAX(CASE WHEN dv.id IS NOT NULL THEN dv.validation_max_score END) as max_score_doc,
            MAX(CASE WHEN fv.id IS NOT NULL THEN fv.created_at END) as ultima_facial,
            MAX(CASE WHEN fv.id IS NOT NULL THEN fv.verification_result END) as status_facial,
            MAX(CASE WHEN fv.id IS NOT NULL THEN fv.similarity_score END) as score_facial
        FROM (
            SELECT id, created_at, verification_result, validation_score, validation_max_score, cliente_id
            FROM document_verifications
            WHERE cliente_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ) dv
        LEFT JOIN (
            SELECT id, created_at, verification_result, similarity_score, cliente_id
            FROM facial_verifications
            WHERE cliente_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ) fv ON 1=1
    ");
    
    $stmt_last->execute([$cliente_id, $cliente_id]);
    $last_verification = $stmt_last->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar último status de verificação: " . $e->getMessage());
    $last_verification = [];
}

// Debug: Verifica se houve erro
if ($stmt_last->errorCode() !== '00000') {
    echo '<div class="alert alert-danger">Erro SQL Last: ' . print_r($stmt_last->errorInfo(), true) . '</div>';
}
?>

<!-- Histórico de Verificações em Accordion -->
<div class="accordion" id="accordionHistoricoVerificacoes">
    <div class="accordion-item border-0 shadow-sm">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHistorico" aria-expanded="false">
                <i class="bi bi-clock-history me-2"></i> 
                <strong>Histórico de Verificações</strong>
                <span class="badge bg-light text-dark ms-2"><?= count($verification_history) ?></span>
            </button>
        </h2>
        <div id="collapseHistorico" class="accordion-collapse collapse" data-bs-parent="#accordionHistoricoVerificacoes">
            <div class="accordion-body">
                <!-- Resumo das Verificações -->
                <div class="row mb-4">
                    <!-- Verificação Documental -->
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <h6 class="mb-2"><i class="bi bi-file-earmark-text"></i> Verificação Documental</h6>
                            <?php if (!empty($last_verification['status_doc'])): ?>
                                <?php 
                                $max_score = $last_verification['max_score_doc'] ?? 12;
                                $score_percent = $max_score > 0 ? ($last_verification['score_doc'] / $max_score) * 100 : 0;
                                ?>
                                <?php if ($last_verification['status_doc'] === 'success'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Verificado
                                    </span>
                                    <small class="d-block text-muted mt-1">
                                        Score: <?= number_format($last_verification['score_doc'], 1) ?>/<?= number_format($max_score, 1) ?>
                                        (<?= number_format($score_percent, 1) ?>%)
                                    </small>
                                    <small class="d-block text-muted">
                                        <?= date('d/m/Y H:i', strtotime($last_verification['ultima_doc'])) ?>
                                    </small>
                                <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="bi bi-x-circle"></i> Falhou
                        </span>
                        <small class="d-block text-muted mt-1">
                            Score: <?php echo number_format($last_verification['score_doc'], 1); ?>/<?php echo number_format($max_score, 1); ?>
                            (<?php echo number_format($score_percent, 1); ?>%)
                        </small>
                        <small class="d-block text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($last_verification['ultima_doc'])); ?>
                        </small>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-secondary">
                        <i class="bi bi-dash-circle"></i> Não verificado
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Verificação Facial -->
            <div class="col-md-6">
                <h6><i class="bi bi-person-bounding-box"></i> Verificação Facial</h6>
                <?php if (!empty($last_verification['status_facial'])): ?>
                    <?php if ($last_verification['status_facial'] === 'success'): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle"></i> Verificado
                        </span>
                        <small class="d-block text-muted mt-1">
                            Similaridade: <?php echo number_format($last_verification['score_facial'], 2); ?>%
                        </small>
                        <small class="d-block text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($last_verification['ultima_facial'])); ?>
                        </small>
                    <?php else: ?>
                        <span class="badge bg-danger">
                            <i class="bi bi-x-circle"></i> Falhou
                        </span>
                        <small class="d-block text-muted mt-1">
                            Similaridade: <?php echo number_format($last_verification['score_facial'], 2); ?>%
                        </small>
                        <small class="d-block text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($last_verification['ultima_facial'])); ?>
                        </small>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge bg-secondary">
                        <i class="bi bi-dash-circle"></i> Não verificado
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Histórico Completo de Auditoria -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="bi bi-clock-history"></i> Histórico Completo de Auditoria
            <span class="badge bg-light text-dark ms-2"><?= count($verification_history) ?></span>
        </h5>
        <small>Todas as ações, verificações e alterações</small>
    </div>
    <div class="card-body">
        <?php if (empty($verification_history)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2">Nenhum registro de auditoria</p>
            </div>
        <?php else: ?>
            <!-- Filtros -->
            <div class="mb-3">
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="filterType" id="filterAll" checked>
                    <label class="btn btn-outline-primary" for="filterAll" onclick="filterTimeline('all')">
                        <i class="bi bi-list-ul"></i> Todos (<?= count($verification_history) ?>)
                    </label>
                    
                    <input type="radio" class="btn-check" name="filterType" id="filterVerifications">
                    <label class="btn btn-outline-info" for="filterVerifications" onclick="filterTimeline('verification')">
                        <i class="bi bi-shield-check"></i> Verificações
                    </label>
                    
                    <input type="radio" class="btn-check" name="filterType" id="filterChanges">
                    <label class="btn btn-outline-warning" for="filterChanges" onclick="filterTimeline('data_change')">
                        <i class="bi bi-pencil-square"></i> Alterações
                    </label>
                    
                    <input type="radio" class="btn-check" name="filterType" id="filterKyc">
                    <label class="btn btn-outline-success" for="filterKyc" onclick="filterTimeline('kyc_activity')">
                        <i class="bi bi-building"></i> KYC
                    </label>
                    
                    <input type="radio" class="btn-check" name="filterType" id="filterWebhooks">
                    <label class="btn btn-outline-secondary" for="filterWebhooks" onclick="filterTimeline('webhook')">
                        <i class="bi bi-send"></i> Webhooks
                    </label>
                </div>
            </div>
            
            <div class="timeline">
                <?php foreach ($verification_history as $item): ?>
                    <div class="timeline-item mb-4" data-type="<?= htmlspecialchars($item['tipo']) ?>">
                        <div class="d-flex">
                            <!-- Ícone dinâmico baseado no tipo -->
                            <div class="me-3">
                                <?php
                                $icon_config = [
                                    'document_verification' => ['icon' => 'file-earmark-text', 'color' => 'info'],
                                    'facial_verification' => ['icon' => 'person-bounding-box', 'color' => 'primary'],
                                    'data_change' => ['icon' => 'pencil-square', 'color' => 'warning'],
                                    'kyc_activity' => ['icon' => 'building-check', 'color' => 'success'],
                                    'webhook' => ['icon' => 'send', 'color' => 'secondary']
                                ];
                                $config = $icon_config[$item['tipo']] ?? ['icon' => 'circle', 'color' => 'dark'];
                                ?>
                                <div class="rounded-circle bg-<?= $config['color'] ?> text-white p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-<?= $config['icon'] ?>"></i>
                                </div>
                            </div>
                            
                            <!-- Conteúdo -->
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <i class="bi bi-<?= $config['icon'] ?>"></i> 
                                            <?= htmlspecialchars($item['tipo_legivel']) ?>
                                        </h6>
                                        
                                        <!-- Status Badge -->
                                        <?php if ($item['resultado'] === 'success'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Sucesso
                                            </span>
                                        <?php elseif ($item['resultado'] === 'failed'): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle"></i> Falhou
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-info-circle"></i> Info
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- DETALHES ESPECÍFICOS POR TIPO -->
                                        
                                        <!-- 1. VERIFICAÇÃO DOCUMENTAL -->
                                        <?php if ($item['tipo'] === 'document_verification'): ?>
                                            <?php 
                                            $max_score = $item['max_score'] ?? 12;
                                            $score_percent = $max_score > 0 ? ($item['score'] / $max_score) * 100 : 0;
                                            ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Score: <strong><?= number_format($item['score'], 1) ?>/<?= number_format($max_score, 1) ?></strong>
                                                    (<?= number_format($score_percent, 1) ?>%)
                                                </small>
                                                <?php if (!empty($item['ocr_confidence'])): ?>
                                                    <br><small class="text-muted">OCR Confiança: <?= number_format($item['ocr_confidence'], 1) ?>%</small>
                                                <?php endif; ?>
                                                <?php if (!empty($item['face_similarity'])): ?>
                                                    <br><small class="text-muted">Face Similaridade: <?= number_format($item['face_similarity'], 1) ?>%</small>
                                                <?php endif; ?>
                                            </div>
                                            <?php 
                                            $extracted_data = !empty($item['extracted_data']) ? json_decode($item['extracted_data'], true) : null;
                                            $validations = !empty($item['validations']) ? json_decode($item['validations'], true) : null;
                                            ?>
                                            <?php if ($extracted_data): ?>
                                                <div class="mt-1 p-2 bg-light rounded">
                                                    <?php if (!empty($extracted_data['nome'])): ?>
                                                        <small><strong>Nome:</strong> <?= htmlspecialchars(is_array($extracted_data['nome']) ? implode(', ', $extracted_data['nome']) : $extracted_data['nome']) ?></small><br>
                                                    <?php endif; ?>
                                                    <?php if (!empty($extracted_data['cpf'])): ?>
                                                        <small><strong>CPF:</strong> <?= htmlspecialchars(is_array($extracted_data['cpf']) ? implode(', ', $extracted_data['cpf']) : $extracted_data['cpf']) ?></small><br>
                                                    <?php endif; ?>
                                                    <?php if (!empty($extracted_data['rg'])): ?>
                                                        <small><strong>RG:</strong> <?= htmlspecialchars(is_array($extracted_data['rg']) ? implode(', ', $extracted_data['rg']) : $extracted_data['rg']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        
                                        <!-- 2. VERIFICAÇÃO FACIAL -->
                                        <?php elseif ($item['tipo'] === 'facial_verification'): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Similaridade: <strong><?= number_format($item['score'], 2) ?>%</strong>
                                                </small>
                                            </div>
                                        
                                        <!-- 3. ALTERAÇÃO DE DADOS -->
                                        <?php elseif ($item['tipo'] === 'data_change'): ?>
                                            <div class="mt-2 p-2 bg-warning bg-opacity-10 rounded">
                                                <small class="text-dark">
                                                    <strong>Ação:</strong> <?= htmlspecialchars($item['campos_alterados']) ?>
                                                </small>
                                                <?php 
                                                // Tenta decodificar o JSON de detalhes
                                                $detalhes = !empty($item['detalhes_extras']) ? json_decode($item['detalhes_extras'], true) : null;
                                                
                                                if ($detalhes && is_array($detalhes) && !empty($detalhes['campos_alterados'])): 
                                                ?>
                                                    <div class="mt-2">
                                                        <details>
                                                            <summary style="cursor: pointer;"><small class="text-primary"><i class="bi bi-eye"></i> Ver alterações detalhadas</small></summary>
                                                            <div class="table-responsive mt-2">
                                                                <table class="table table-sm table-bordered mb-0">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th width="30%">Campo</th>
                                                                            <th width="35%">Valor Anterior</th>
                                                                            <th width="35%">Valor Novo</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($detalhes['campos_alterados'] as $campo): ?>
                                                                            <tr>
                                                                                <td><strong><?= ucwords(str_replace('_', ' ', $campo)) ?></strong></td>
                                                                                <td><code><?= htmlspecialchars($detalhes['valores_antigos'][$campo] ?? '-') ?></code></td>
                                                                                <td><code class="text-success"><?= htmlspecialchars($detalhes['valores_novos'][$campo] ?? '-') ?></code></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </details>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        
                                        <!-- 4. ATIVIDADE KYC -->
                                        <?php elseif ($item['tipo'] === 'kyc_activity'): ?>
                                            <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                                <small class="text-dark">
                                                    <strong>Atividade:</strong> <?= htmlspecialchars($item['detalhes_extras']) ?>
                                                </small>
                                                <?php if (!empty($item['valores_novos'])): ?>
                                                    <?php 
                                                    $snapshot = json_decode($item['valores_novos'], true);
                                                    if ($snapshot && is_array($snapshot)): 
                                                    ?>
                                                        <details class="mt-2">
                                                            <summary style="cursor: pointer;"><small class="text-muted">Ver snapshot completo</small></summary>
                                                            <pre class="mt-2 p-2 bg-white rounded" style="font-size: 10px; max-height: 200px; overflow-y: auto;"><?= htmlspecialchars(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                                        </details>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        
                                        <!-- 5. WEBHOOK -->
                                        <?php elseif ($item['tipo'] === 'webhook'): ?>
                                            <div class="mt-2 p-2 bg-secondary bg-opacity-10 rounded">
                                                <small class="text-dark">
                                                    <strong>HTTP <?= htmlspecialchars($item['score']) ?></strong>
                                                </small>
                                                <?php if (!empty($item['detalhes_extras'])): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($item['detalhes_extras']) ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($item['campos_alterados'])): ?>
                                                    <details class="mt-2">
                                                        <summary style="cursor: pointer;"><small class="text-muted">Ver payload enviado</small></summary>
                                                        <pre class="mt-2 p-2 bg-white rounded" style="font-size: 10px; max-height: 200px; overflow-y: auto;"><?= htmlspecialchars($item['campos_alterados']) ?></pre>
                                                    </details>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- INFORMAÇÕES DO USUÁRIO E IP -->
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-person"></i> 
                                                <strong><?= htmlspecialchars(!empty($item['usuario_nome']) ? $item['usuario_nome'] : 'Sistema') ?></strong>
                                                <?php if (!empty($item['usuario_email'])): ?>
                                                    (<?= htmlspecialchars($item['usuario_email']) ?>)
                                                <?php endif; ?>
                                                <?php if (!empty($item['usuario_tipo'])): ?>
                                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($item['usuario_tipo']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['ip_address'])): ?>
                                                    <br><i class="bi bi-geo-alt"></i> IP: <?= htmlspecialchars($item['ip_address']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Data/Hora -->
                                    <div class="text-end ms-3">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($item['created_at'])) ?><br>
                                            <i class="bi bi-clock"></i> <?= date('H:i:s', strtotime($item['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Função para filtrar timeline
function filterTimeline(type) {
    const items = document.querySelectorAll('.timeline-item');
    items.forEach(item => {
        if (type === 'all') {
            item.style.display = 'block';
        } else {
            const itemType = item.getAttribute('data-type');
            if (itemType && itemType.includes(type)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        }
    });
}
</script>

<style>
.timeline-item {
    border-left: 2px solid #dee2e6;
    padding-left: 20px;
    position: relative;
    transition: all 0.3s ease;
}

.timeline-item:last-child {
    border-left: none;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 15px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #dee2e6;
}

.timeline-item:hover {
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-left: -10px;
    padding-left: 30px;
}
</style>
