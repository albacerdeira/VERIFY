<?php
$page_title = 'Análise de Caso KYC';
require_once 'bootstrap.php'; // Usa o novo sistema de inicialização

// --- Validação de Acesso e Permissões ---
$kyc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$kyc_id) {
    require_once 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>ID do caso KYC inválido.</div></div>";
    require_once 'footer.php';
    exit();
}

// Carrega os dados do caso para verificar a propriedade
$stmt_caso = $pdo->prepare("SELECT id_empresa_master FROM kyc_empresas WHERE id = :id");
$stmt_caso->execute(['id' => $kyc_id]);
$caso_propriedade = $stmt_caso->fetch(PDO::FETCH_ASSOC);

// Verifica se o usuário tem permissão para ver este caso
$permitido = false;
if ($is_superadmin) {
    $permitido = true;
} elseif (($is_admin || $is_analista) && $caso_propriedade && $caso_propriedade['id_empresa_master'] == $user_empresa_id) {
    $permitido = true;
}

if (!$permitido) {
    require_once 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Acesso negado. Você não tem permissão para visualizar este caso.</div></div>";
    require_once 'footer.php';
    exit();
}

// --- Carregamento Completo dos Dados (se a permissão foi concedida) ---
$stmt_caso = $pdo->prepare("SELECT e.*, a.*, e.id AS kyc_id_real, e.status AS status_caso FROM kyc_empresas e LEFT JOIN kyc_avaliacoes a ON e.id = a.kyc_empresa_id WHERE e.id = :id");
$stmt_caso->execute(['id' => $kyc_id]);
$caso = $stmt_caso->fetch(PDO::FETCH_ASSOC);

if (!$caso) {
    require_once 'header.php';
    echo "<div class='container p-4'><div class='alert alert-danger'>Registro KYC não encontrado.</div></div>";
    require_once 'footer.php';
    exit();
}

// Define o ID e nome do analista a partir da sessão
$analista_id = $_SESSION['user_id'];
$analista_nome = $_SESSION['user_nome'] ?? 'Usuário Desconhecido';


// --- PROCESSAMENTO DO FORMULÁRIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        // 1. Atualiza o status na tabela principal de empresas
        $stmt_status = $pdo->prepare("UPDATE kyc_empresas SET status = :status WHERE id = :kyc_id");
        $stmt_status->execute([':status' => $_POST['status_caso'], ':kyc_id' => $kyc_id]);

        // 2. Insere ou atualiza o registro de avaliação geral com o NOVO CHECKLIST
        $avaliacao_sql = "
            INSERT INTO kyc_avaliacoes (
                kyc_empresa_id, av_analista_id, 
                av_check_dados_empresa_ok, av_check_perfil_negocio_ok, av_check_socios_ubos_ok, av_check_documentos_ok, 
                av_anotacoes_internas, av_risco_atividade, av_risco_geografico, av_risco_societario, 
                av_risco_midia_pep, av_risco_final, av_justificativa_risco, av_info_pendencia
            ) VALUES (
                :kyc_id, :analista_id, 
                :c1, :c2, :c3, :c4, 
                :anotacoes, :r1, :r2, :r3, :r4, :r_final, :justificativa, :pendencia
            ) ON DUPLICATE KEY UPDATE 
                av_analista_id = VALUES(av_analista_id),
                av_check_dados_empresa_ok = VALUES(av_check_dados_empresa_ok),
                av_check_perfil_negocio_ok = VALUES(av_check_perfil_negocio_ok),
                av_check_socios_ubos_ok = VALUES(av_check_socios_ubos_ok),
                av_check_documentos_ok = VALUES(av_check_documentos_ok),
                av_anotacoes_internas = VALUES(av_anotacoes_internas),
                av_risco_atividade = VALUES(av_risco_atividade),
                av_risco_geografico = VALUES(av_risco_geografico),
                av_risco_societario = VALUES(av_risco_societario),
                av_risco_midia_pep = VALUES(av_risco_midia_pep),
                av_risco_final = VALUES(av_risco_final),
                av_justificativa_risco = VALUES(av_justificativa_risco),
                av_info_pendencia = VALUES(av_info_pendencia)
        ";
        $stmt_aval = $pdo->prepare($avaliacao_sql);
        $stmt_aval->execute([
            ':kyc_id' => $kyc_id, ':analista_id' => $analista_id,
            ':c1' => isset($_POST['av_check_dados_empresa_ok']) ? 1 : 0, 
            ':c2' => isset($_POST['av_check_perfil_negocio_ok']) ? 1 : 0,
            ':c3' => isset($_POST['av_check_socios_ubos_ok']) ? 1 : 0, 
            ':c4' => isset($_POST['av_check_documentos_ok']) ? 1 : 0,
            ':anotacoes' => $_POST['av_anotacoes_internas'] ?? null,
            ':r1' => $_POST['av_risco_atividade'] ?? null, ':r2' => $_POST['av_risco_geografico'] ?? null,
            ':r3' => $_POST['av_risco_societario'] ?? null, ':r4' => $_POST['av_risco_midia_pep'] ?? null,
            ':r_final' => $_POST['av_risco_final'] ?? null, ':justificativa' => $_POST['av_justificativa_risco'] ?? null,
            ':pendencia' => $_POST['av_info_pendencia'] ?? null
        ]);

        // 3. Atualiza a análise individual dos sócios (sem alteração aqui)
        if (isset($_POST['socio_observacoes']) && is_array($_POST['socio_observacoes'])) {
            $socio_update_sql = "UPDATE kyc_socios SET av_socio_verificado = :verificado, av_socio_observacoes = :observacoes WHERE id = :socio_id AND empresa_id = :kyc_id";
            $stmt_socio = $pdo->prepare($socio_update_sql);
            foreach ($_POST['socio_observacoes'] as $socio_id => $observacoes) {
                $socio_id_int = (int)$socio_id;
                $verificado = isset($_POST['socio_verificado'][$socio_id_int]) ? 1 : 0;
                $stmt_socio->execute([
                    ':verificado' => $verificado, ':observacoes' => $observacoes,
                    ':socio_id' => $socio_id_int, ':kyc_id' => $kyc_id
                ]);
            }
        }

        // 4. Prepara e registra a ação no log com o snapshot completo da avaliação
        $log_acao = sprintf("Status alterado para '%s'. Análise de risco final: %s.", htmlspecialchars($_POST['status_caso']), htmlspecialchars($_POST['av_risco_final']));

        // Cria um array com todos os dados da avaliação para o snapshot
        $snapshot_data = [
            'status_caso' => $_POST['status_caso'],
            'av_check_dados_empresa_ok' => isset($_POST['av_check_dados_empresa_ok']) ? 1 : 0,
            'av_check_perfil_negocio_ok' => isset($_POST['av_check_perfil_negocio_ok']) ? 1 : 0,
            'av_check_socios_ubos_ok' => isset($_POST['av_check_socios_ubos_ok']) ? 1 : 0,
            'av_check_documentos_ok' => isset($_POST['av_check_documentos_ok']) ? 1 : 0,
            'av_anotacoes_internas' => $_POST['av_anotacoes_internas'] ?? null,
            'av_risco_atividade' => $_POST['av_risco_atividade'] ?? null,
            'av_risco_geografico' => $_POST['av_risco_geografico'] ?? null,
            'av_risco_societario' => $_POST['av_risco_societario'] ?? null,
            'av_risco_midia_pep' => $_POST['av_risco_midia_pep'] ?? null,
            'av_risco_final' => $_POST['av_risco_final'] ?? null,
            'av_justificativa_risco' => $_POST['av_justificativa_risco'] ?? null,
            'av_info_pendencia' => $_POST['av_info_pendencia'] ?? null,
            'analise_socios' => []
        ];

        if (isset($_POST['socio_observacoes']) && is_array($_POST['socio_observacoes'])) {
            foreach ($_POST['socio_observacoes'] as $socio_id => $observacoes) {
                $snapshot_data['analise_socios'][$socio_id] = [
                    'observacoes' => $observacoes,
                    'verificado' => isset($_POST['socio_verificado'][$socio_id]) ? 1 : 0
                ];
            }
        }

        $stmt_log = $pdo->prepare("INSERT INTO kyc_log_atividades (kyc_empresa_id, usuario_id, usuario_nome, acao, dados_avaliacao_snapshot) VALUES (:kyc_id, :user_id, :user_name, :acao, :snapshot)");
        $stmt_log->execute([
            ':kyc_id' => $kyc_id, 
            ':user_id' => $analista_id,
            ':user_name' => $analista_nome, 
            ':acao' => $log_acao,
            ':snapshot' => json_encode($snapshot_data)
        ]);

        $pdo->commit();
        $_SESSION['flash_message'] = "Análise salva com sucesso!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Erro ao salvar a análise. Detalhes: " . $e->getMessage();
        error_log("Erro ao salvar análise KYC para kyc_id=$kyc_id: " . $e->getMessage());
    }
    header("Location: kyc_list.php");
    exit;
}

// --- CARREGAMENTO DE DADOS (GET) ---
// Os dados do $caso já foram carregados antes da checagem de permissão.

// Carrega dados relacionados
$socios = $pdo->prepare("
    SELECT 
        id, empresa_id, nome_completo, data_nascimento, cpf_cnpj, qualificacao_cargo, 
        percentual_participacao, cep, logradouro, numero, complemento, bairro, cidade, uf, 
        observacoes, is_pep, dados_validados, av_socio_verificado, av_socio_observacoes 
    FROM kyc_socios 
    WHERE empresa_id = :id
");
$socios->execute(['id' => $kyc_id]);

$documentos = $pdo->prepare("SELECT * FROM kyc_documentos WHERE empresa_id = :id");
$documentos->execute(['id' => $kyc_id]);

$cnaes_secundarios = $pdo->prepare("SELECT * FROM kyc_cnaes_secundarios WHERE empresa_id = :id ORDER BY id");
$cnaes_secundarios->execute(['id' => $kyc_id]);

$log_atividades = $pdo->prepare("
    SELECT 
        l.id, l.timestamp, l.acao, l.dados_avaliacao_snapshot,
        COALESCE(u.nome, sa.nome) AS nome_analista
    FROM kyc_log_atividades l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    LEFT JOIN superadmin sa ON l.usuario_id = sa.id
    WHERE l.kyc_empresa_id = :kyc_id 
    ORDER BY l.timestamp DESC
");
$log_atividades->execute(['kyc_id' => $kyc_id]);

$page_title = "Análise KYC - " . htmlspecialchars($caso['razao_social']);
require 'header.php';

// --- FUNÇÕES AUXILIARES DE RENDERIZAÇÃO ---
if (!function_exists('display_field')) {
    function display_field($label, $value, $is_date = false) {
        $display_value = !empty($value) ? ($is_date ? date('d/m/Y H:i:s', strtotime($value)) : nl2br(htmlspecialchars($value))) : '<span class="text-muted fst-italic">Não informado</span>';
        return "<div class='mb-3'><p class='text-muted small mb-1'>$label</p><strong>$display_value</strong></div>";
    }
}
if (!function_exists('render_risk_select')) {
    function render_risk_select($name, $label, $current_value) {
        $options = ['Baixo', 'Médio', 'Alto'];
        $html = "<div class='mb-3'><label for='$name' class='form-label'>$label</label><select name='$name' id='$name' class='form-select'>";
        $html .= "<option value='' selected>Selecionar...</option>";
        foreach ($options as $option) {
            $selected = ($current_value == $option) ? 'selected' : '';
            $html .= "<option value='$option' $selected>$option</option>";
        }
        $html .= "</select></div>";
        return $html;
    }
}
?>
<!-- Layout da Página (HTML) -->
<form action="kyc_evaluate.php?id=<?= $kyc_id; ?>" method="POST">
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Coluna Esquerda: Dados do Cliente -->
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Análise de Caso KYC: #<?= $caso['kyc_id_real']; ?></h4>
                        <span class="badge bg-secondary-soft text-secondary"><?= htmlspecialchars($caso['status_caso']); ?></span>
                    </div>
                    <div class="card-body">
                        <p class="lead text-muted mb-4">Cliente: <?= htmlspecialchars($caso['razao_social']); ?></p>
                        <?php require 'kyc_evaluate_accordions.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Painel de Análise e Logs -->
            <div class="col-lg-5" style="position: sticky; top: 20px;">
                <div class="card shadow-sm">
                    <div class="card-header"><h5 class="mb-0">Painel de Análise do Compliance</h5></div>
                    <div class="card-body">
                         <fieldset class="mb-4">
                            <legend class="h6 fw-bold border-bottom pb-2 mb-3">Checklist de Validação</legend>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="av_check_dados_empresa_ok" id="c1" <?= ($caso['av_check_dados_empresa_ok'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="c1">Dados da Empresa</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="av_check_perfil_negocio_ok" id="c2" <?= ($caso['av_check_perfil_negocio_ok'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="c2">Perfil de Negócio e Financeiro</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="av_check_socios_ubos_ok" id="c3" <?= ($caso['av_check_socios_ubos_ok'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="c3">Sócios e Administradores (UBOs)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="av_check_documentos_ok" id="c4" <?= ($caso['av_check_documentos_ok'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="c4">Documentos da Empresa</label>
                            </div>
                        </fieldset>
                        <div class="mb-3"><label for="anotacoes" class="form-label fw-bold">Anotações Internas</label><textarea name="av_anotacoes_internas" class="form-control" rows="5"><?= htmlspecialchars($caso['av_anotacoes_internas'] ?? ''); ?></textarea></div>
                        <fieldset class="mb-4">
                            <legend class="h6 fw-bold border-bottom pb-2 mb-3">Classificação de Risco</legend>
                            <?= render_risk_select('av_risco_atividade', 'Risco da Atividade', $caso['av_risco_atividade'] ?? ''); ?>
                            <?= render_risk_select('av_risco_geografico', 'Risco Geográfico', $caso['av_risco_geografico'] ?? ''); ?>
                            <?= render_risk_select('av_risco_societario', 'Risco da Estrutura Societária', $caso['av_risco_societario'] ?? ''); ?>
                            <?= render_risk_select('av_risco_midia_pep', 'Risco de Mídia Negativa / PEP', $caso['av_risco_midia_pep'] ?? ''); ?>
                        </fieldset>
                        <div class="mb-3"><label for="justificativa" class="form-label fw-bold">Justificativa do Risco Final</label><textarea name="av_justificativa_risco" class="form-control" rows="3" required><?= htmlspecialchars($caso['av_justificativa_risco'] ?? ''); ?></textarea></div>
                        <div class="mb-4"><label for="risco_final" class="form-label fw-bold">Nível de Risco Final Consolidado</label><select name="av_risco_final" class="form-select" required><option value="" disabled selected>Selecione...</option><option value="Baixo" <?= (($caso['av_risco_final'] ?? '') == 'Baixo') ? 'selected' : ''; ?>>Baixo</option><option value="Médio" <?= (($caso['av_risco_final'] ?? '') == 'Médio') ? 'selected' : ''; ?>>Médio</option><option value="Alto" <?= (($caso['av_risco_final'] ?? '') == 'Alto') ? 'selected' : ''; ?>>Alto</option></select></div>
                        <fieldset class="mb-3">
                            <legend class="h6 fw-bold border-bottom pb-2 mb-3">Decisão Final</legend>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="status_caso" value="Aprovado" id="decisao_aprovar" <?= (($caso['status_caso'] ?? '') == 'Aprovado') ? 'checked' : ''; ?> required><label class="btn btn-outline-success" for="decisao_aprovar">Aprovar</label>
                                <input type="radio" class="btn-check" name="status_caso" value="Reprovado" id="decisao_reprovar" <?= (($caso['status_caso'] ?? '') == 'Reprovado') ? 'checked' : ''; ?> required><label class="btn btn-outline-danger" for="decisao_reprovar">Reprovar</label>
                                <input type="radio" class="btn-check" name="status_caso" value="Pendenciado" id="decisao_pendenciar" <?= (($caso['status_caso'] ?? '') == 'Pendenciado') ? 'checked' : ''; ?> required><label class="btn btn-outline-secondary" for="decisao_pendenciar">Pendenciar</label>
                            </div>
                        </fieldset>
                        <div id="pendencia_container" class="mb-3" style="display: none;"><label for="info_pendencia" class="form-label">Informações de Pendência</label><textarea name="av_info_pendencia" id="info_pendencia" class="form-control" rows="3"><?= htmlspecialchars($caso['av_info_pendencia'] ?? ''); ?></textarea></div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg mt-3">Salvar Análise</button>
                    </div>
                </div>
                <!-- Log de Atividades -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header"><h5 class="mb-0">Log de Atividades</h5></div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <ul class="list-group list-group-flush">
                            <?php 
                            $logs = $log_atividades->fetchAll(PDO::FETCH_ASSOC);
                            for ($i = 0; $i < count($logs); $i++):
                                $log = $logs[$i];
                                // O log anterior é o próximo no array, pois a consulta é em ordem decrescente.
                                $previous_log_snapshot = isset($logs[$i + 1]) ? $logs[$i + 1]['dados_avaliacao_snapshot'] : 'null';
                            ?>
                                <li class="list-group-item">
                                    <p class="mb-1 small text-muted">
                                        <?= date('d/m/Y H:i', strtotime($log['timestamp'])) ?> por <strong><?= htmlspecialchars($log['nome_analista'] ?? 'Sistema') ?></strong>
                                    </p>
                                    <p class="mb-1"><?= htmlspecialchars($log['acao']) ?></p>
                                    <?php if (!empty($log['dados_avaliacao_snapshot'])): ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#logDetailModal" 
                                                data-current-snapshot="<?= htmlspecialchars($log['dados_avaliacao_snapshot']) ?>"
                                                data-previous-snapshot="<?= htmlspecialchars($previous_log_snapshot) ?>">
                                            Ver Detalhes
                                        </button>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal para Detalhes do Log -->
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logDetailModalLabel">Snapshot da Análise</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Estes eram os valores de todos os campos no momento em que a análise foi salva.</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="log-details-table">
                <!-- Conteúdo será inserido via JavaScript -->
            </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... (código existente do togglePendencia) ...

    const logDetailModal = document.getElementById('logDetailModal');
    if (logDetailModal) {
        logDetailModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const currentSnapshotJson = button.getAttribute('data-current-snapshot');
            const previousSnapshotJson = button.getAttribute('data-previous-snapshot');

            const currentSnapshot = JSON.parse(currentSnapshotJson);
            const previousSnapshot = (previousSnapshotJson && previousSnapshotJson !== 'null') ? JSON.parse(previousSnapshotJson) : null;
            
            const table = logDetailModal.querySelector('#log-details-table');
            table.innerHTML = '<thead><tr><th style="width: 30%;">Campo</th><th style="width: 70%;">Valor</th></tr></thead><tbody></tbody>';
            const tbody = table.querySelector('tbody');

            for (const key in currentSnapshot) {
                if (key === 'analise_socios') {
                    for (const socio_id in currentSnapshot.analise_socios) {
                        const socio_analise = currentSnapshot.analise_socios[socio_id];
                        const prev_socio_analise = (previousSnapshot && previousSnapshot.analise_socios) ? (previousSnapshot.analise_socios[socio_id] || null) : null;

                        // Observações
                        let obs_rowClass = '';
                        if (prev_socio_analise && socio_analise.observacoes !== prev_socio_analise.observacoes) {
                            obs_rowClass = (!prev_socio_analise.observacoes) ? 'table-info' : 'table-danger';
                        } else if (!prev_socio_analise && socio_analise.observacoes) {
                            obs_rowClass = 'table-info'; // Novo campo
                        }
                        const obs_row = `<tr class="${obs_rowClass}"><td><strong>Análise Sócio ${socio_id} - Observações</strong></td><td>${socio_analise.observacoes || 'N/A'}</td></tr>`;
                        tbody.innerHTML += obs_row;

                        // Verificado
                        let ver_rowClass = '';
                        if (prev_socio_analise && socio_analise.verificado !== prev_socio_analise.verificado) {
                            ver_rowClass = 'table-danger';
                        }
                        const ver_row = `<tr class="${ver_rowClass}"><td><strong>Análise Sócio ${socio_id} - Verificado</strong></td><td>${socio_analise.verificado ? 'Sim' : 'Não'}</td></tr>`;
                        tbody.innerHTML += ver_row;
                    }
                } else {
                    let currentValue = currentSnapshot[key];
                    let previousValue = previousSnapshot ? (previousSnapshot[key] ?? null) : null;
                    
                    let displayValue = currentValue;
                    if (displayValue === 1) displayValue = 'Sim';
                    if (displayValue === 0) displayValue = 'Não';
                    if (displayValue === null || displayValue === '') displayValue = 'N/A';

                    let rowClass = '';
                    if (previousSnapshot && currentValue !== previousValue) {
                        if (previousValue === null || previousValue === '') {
                            rowClass = 'table-info'; // Azul para informação nova
                        } else {
                            rowClass = 'table-danger'; // Vermelho para alteração
                        }
                    } else if (!previousSnapshot && (currentValue !== null && currentValue !== '')) {
                        rowClass = 'table-info'; // Azul se for a primeira análise e tiver valor
                    }

                    // Remove o prefixo "av " e capitaliza o campo
                    let displayName = key.replace(/^av_/, '').replace(/_/g, ' ');
                    displayName = displayName.charAt(0).toUpperCase() + displayName.slice(1);

                    const row = `<tr class="${rowClass}"><td><strong>${displayName}</strong></td><td>${displayValue}</td></tr>`;
                    tbody.innerHTML += row;
                }
            }
        });
    }
});
</script>

<?php require 'footer.php'; ?>
