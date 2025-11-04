<?php
/**
 * Componente: Histórico de Verificações
 * Mostra histórico completo de verificações faciais e documentais
 * 
 * Uso:
 * $cliente_id = 123;
 * include 'includes/verification_history.php';
 */

if (!isset($cliente_id)) {
    echo '<div class="alert alert-danger">Erro: cliente_id não foi definido</div>';
    return;
}

// Debug: Mostra o cliente_id
// echo '<div class="alert alert-info">Debug: Buscando verificações para cliente_id = ' . $cliente_id . '</div>';

// Busca histórico de verificações
$stmt_verifications = $pdo->prepare("
    SELECT 
        'document' as tipo,
        dv.id,
        dv.created_at,
        dv.verification_result as resultado,
        dv.validation_score as score,
        dv.validation_max_score as max_score,
        dv.ocr_confidence,
        dv.face_similarity,
        dv.extracted_data,
        dv.validations,
        u.nome as verificado_por,
        dv.ip_address
    FROM document_verifications dv
    LEFT JOIN usuarios u ON dv.usuario_id = u.id
    WHERE dv.cliente_id = ?
    
    UNION ALL
    
    SELECT 
        'facial' as tipo,
        fv.id,
        fv.created_at,
        fv.verification_result as resultado,
        fv.similarity_score as score,
        NULL as max_score,
        NULL as ocr_confidence,
        NULL as face_similarity,
        NULL as extracted_data,
        NULL as validations,
        u.nome as verificado_por,
        fv.ip_address
    FROM facial_verifications fv
    LEFT JOIN usuarios u ON fv.usuario_id = u.id
    WHERE fv.cliente_id = ?
    
    ORDER BY created_at DESC
");

$stmt_verifications->execute([$cliente_id, $cliente_id]);
$verification_history = $stmt_verifications->fetchAll(PDO::FETCH_ASSOC);

// Debug: Verifica se houve erro
if ($stmt_verifications->errorCode() !== '00000') {
    echo '<div class="alert alert-danger">Erro SQL Verifications: ' . print_r($stmt_verifications->errorInfo(), true) . '</div>';
}

// Debug: Mostra quantidade de registros encontrados
// echo '<div class="alert alert-info">Registros de verificação encontrados: ' . count($verification_history) . '</div>';

// Busca último status de verificação
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

// Debug: Verifica se houve erro
if ($stmt_last->errorCode() !== '00000') {
    echo '<div class="alert alert-danger">Erro SQL Last: ' . print_r($stmt_last->errorInfo(), true) . '</div>';
}
?>

<!-- Status Fixo de Verificação -->
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-shield-check"></i> Status de Verificação</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Verificação Documental -->
            <div class="col-md-6">
                <h6><i class="bi bi-file-earmark-text"></i> Verificação Documental</h6>
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
                            Score: <?php echo number_format($last_verification['score_doc'], 1); ?>/<?php echo number_format($max_score, 1); ?>
                            (<?php echo number_format($score_percent, 1); ?>%)
                        </small>
                        <small class="d-block text-muted">
                            <?php echo date('d/m/Y H:i', strtotime($last_verification['ultima_doc'])); ?>
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

<!-- Histórico de Verificações -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Histórico de Verificações</h5>
    </div>
    <div class="card-body">
        <?php if (empty($verification_history)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2">Nenhuma verificação registrada</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($verification_history as $item): ?>
                    <div class="timeline-item mb-4">
                        <div class="d-flex">
                            <!-- Ícone do tipo -->
                            <div class="me-3">
                                <?php if ($item['tipo'] === 'document'): ?>
                                    <div class="rounded-circle bg-info text-white p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-person-bounding-box"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Conteúdo -->
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php if ($item['tipo'] === 'document'): ?>
                                                <i class="bi bi-file-earmark-text"></i> Verificação Documental
                                            <?php else: ?>
                                                <i class="bi bi-person-bounding-box"></i> Verificação Facial (Selfie)
                                            <?php endif; ?>
                                        </h6>
                                        
                                        <!-- Status -->
                                        <?php if ($item['resultado'] === 'success'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Sucesso
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle"></i> Falhou
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Detalhes -->
                                        <?php if ($item['tipo'] === 'document'): ?>
                                            <?php 
                                            $max_score = $item['max_score'] ?? 12;
                                            $score_percent = $max_score > 0 ? ($item['score'] / $max_score) * 100 : 0;
                                            ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Score: <strong><?php echo number_format($item['score'], 1); ?>/<?php echo number_format($max_score, 1); ?></strong>
                                                    (<?php echo number_format($score_percent, 1); ?>%)
                                                </small>
                                                <?php if (!empty($item['ocr_confidence'])): ?>
                                                    <br><small class="text-muted">OCR Confiança: <?php echo number_format($item['ocr_confidence'], 1); ?>%</small>
                                                <?php endif; ?>
                                                <?php if (!empty($item['face_similarity'])): ?>
                                                    <br><small class="text-muted">Face Similaridade: <?php echo number_format($item['face_similarity'], 1); ?>%</small>
                                                <?php endif; ?>
                                            </div>
                                            <?php 
                                            // Extrai dados do JSON
                                            $extracted_data = !empty($item['extracted_data']) ? json_decode($item['extracted_data'], true) : null;
                                            $validations = !empty($item['validations']) ? json_decode($item['validations'], true) : null;
                                            ?>
                                            <?php if ($extracted_data && !empty($extracted_data['nome'])): ?>
                                                <div class="mt-1">
                                                    <small><strong>Nome extraído:</strong> <?php echo htmlspecialchars(is_array($extracted_data['nome']) ? implode(', ', $extracted_data['nome']) : $extracted_data['nome']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($extracted_data && !empty($extracted_data['cpf'])): ?>
                                                <div>
                                                    <small><strong>CPF extraído:</strong> <?php echo htmlspecialchars(is_array($extracted_data['cpf']) ? implode(', ', $extracted_data['cpf']) : $extracted_data['cpf']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($extracted_data && !empty($extracted_data['rg'])): ?>
                                                <div>
                                                    <small><strong>RG extraído:</strong> <?php echo htmlspecialchars(is_array($extracted_data['rg']) ? implode(', ', $extracted_data['rg']) : $extracted_data['rg']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($validations): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted d-block"><strong>Validações:</strong></small>
                                                    <?php foreach ($validations as $key => $validation): ?>
                                                        <?php if (is_array($validation) && isset($validation['passed'])): ?>
                                                            <small class="d-block">
                                                                <?php if ($validation['passed']): ?>
                                                                    <i class="bi bi-check-circle text-success"></i>
                                                                <?php else: ?>
                                                                    <i class="bi bi-x-circle text-danger"></i>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($validation['label'] ?? $key); ?>
                                                                <?php if (!empty($validation['message'])): ?>
                                                                    - <?php echo htmlspecialchars($validation['message']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    Similaridade: <strong><?php echo number_format($item['score'], 2); ?>%</strong>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Informações adicionais -->
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($item['verificado_por'] ?? 'Sistema'); ?>
                                                &nbsp;|&nbsp;
                                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($item['ip_address']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Data/Hora -->
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($item['created_at'])); ?><br>
                                            <?php echo date('H:i:s', strtotime($item['created_at'])); ?>
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

<style>
.timeline-item {
    border-left: 2px solid #dee2e6;
    padding-left: 20px;
    position: relative;
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
</style>
