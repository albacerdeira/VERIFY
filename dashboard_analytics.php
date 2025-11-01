<?php
$page_title = 'Dashboard Analytics';
require_once 'bootstrap.php';

// Verifica se o usuário está logado
if (!$is_logged_in) {
    header('Location: login.php');
    exit;
}

require_once 'header.php';

// Debug: verifica erros
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir na tela
ini_set('log_errors', 1);

// ===== CONSULTAS DE DADOS =====

// Inicializa variáveis com valores padrão
$total_clientes = 0;
$clientes_por_tipo = ['PF' => 0, 'PJ' => 0];
$kyc_por_status = [];
$total_kyc = 0;
$total_alertas_ceis = 0;
$total_alertas_cnep = 0;
$total_alertas_pep = 0;
$total_registros_ceis = 0;
$total_registros_cnep = 0;
$total_registros_pep = 0;
$consultas_stats = ['total' => 0, 'hoje' => 0, 'semana' => 0, 'mes' => 0];
$tempo_medio_analise = 0;
$kyc_recentes = [];
$consultas_recentes = [];
$crescimento_clientes = [];
$usuarios_ativos = [];
$erro_carregamento = null;

try {
    // 1. CLIENTES (kyc_clientes)
    $stmt_clientes_total = $pdo->query("SELECT COUNT(*) as total FROM kyc_clientes");
    $total_clientes = $stmt_clientes_total->fetch(PDO::FETCH_ASSOC)['total'];

    // Contagem por tipo (PF baseado em CPF preenchido, PJ em CNPJ)
    $stmt_clientes_pf = $pdo->query("SELECT COUNT(*) as total FROM kyc_clientes WHERE cpf IS NOT NULL AND cpf != ''");
    $clientes_por_tipo['PF'] = $stmt_clientes_pf->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_clientes_pj = $pdo->query("SELECT COUNT(*) as total FROM kyc_empresas");
    $clientes_por_tipo['PJ'] = $stmt_clientes_pj->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. KYC STATUS (kyc_empresas)
    $stmt_kyc_status = $pdo->query("
        SELECT status, COUNT(*) as total
        FROM kyc_empresas
        GROUP BY status
    ");
    $kyc_por_status = [];
    $total_kyc = 0;
    while ($row = $stmt_kyc_status->fetch(PDO::FETCH_ASSOC)) {
        $kyc_por_status[$row['status']] = $row['total'];
        $total_kyc += $row['total'];
    }

    // 3. ALERTAS CEIS (usando flags da tabela kyc_avaliacoes)
    // av_check_ceis_ok: 1=OK, 0=Sanção encontrada, NULL=Não verificado
    // av_check_ceis_pf_ok: 1=OK, 0=Sanção em sócio PF
    $stmt_ceis = $pdo->query("
        SELECT COUNT(DISTINCT ka.kyc_empresa_id) as total
        FROM kyc_avaliacoes ka
        WHERE ka.av_check_ceis_ok = 0 OR ka.av_check_ceis_pf_ok = 0
    ");
    $total_alertas_ceis = $stmt_ceis->fetch(PDO::FETCH_ASSOC)['total'];

    // 4. ALERTAS CNEP (usando flags da tabela kyc_avaliacoes)
    // av_check_cnep_ok: 1=OK, 0=Sanção encontrada (PJ)
    // av_check_cnep_pf_ok: 1=OK, 0=Sanção em sócio PF
    $stmt_cnep = $pdo->query("
        SELECT COUNT(DISTINCT ka.kyc_empresa_id) as total
        FROM kyc_avaliacoes ka
        WHERE ka.av_check_cnep_ok = 0 OR ka.av_check_cnep_pf_ok = 0
    ");
    $total_alertas_cnep = $stmt_cnep->fetch(PDO::FETCH_ASSOC)['total'];

    // 5. ALERTAS PEP (usando flag is_pep=1 da tabela kyc_socios)
    $stmt_pep_alertas = $pdo->query("
        SELECT COUNT(DISTINCT ks.empresa_id) as total
        FROM kyc_socios ks
        WHERE ks.is_pep = 1
    ");
    $total_alertas_pep = $stmt_pep_alertas->fetch(PDO::FETCH_ASSOC)['total'];

    // Totais de registros nas bases
    $stmt_ceis_total = $pdo->query("SELECT COUNT(*) as total FROM ceis");
    $total_registros_ceis = $stmt_ceis_total->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_cnep_total = $pdo->query("SELECT COUNT(*) as total FROM cnep");
    $total_registros_cnep = $stmt_cnep_total->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt_pep_total = $pdo->query("SELECT COUNT(*) as total FROM peps");
    $total_registros_pep = $stmt_pep_total->fetch(PDO::FETCH_ASSOC)['total'];

    // 6. CONSULTAS RECENTES (tabela consultas - filtradas por permissão)
    $consultas_sql = "
        SELECT COUNT(*) as total,
               COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as hoje,
               COUNT(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as semana,
               COUNT(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 END) as mes
        FROM consultas c
    ";
    
    if ($user_role === 'superadmin') {
        // Superadmin vê todas as consultas
        $stmt_consultas = $pdo->query($consultas_sql);
    } elseif (in_array($user_role, ['admin', 'administrador'])) {
        // Admin vê consultas da sua empresa
        $consultas_sql .= " JOIN usuarios u ON c.usuario_id = u.id WHERE u.empresa_id = :empresa_id";
        $stmt_consultas = $pdo->prepare($consultas_sql);
        $stmt_consultas->execute([':empresa_id' => $user_empresa_id]);
    } else {
        // Usuário comum vê apenas as suas
        $consultas_sql .= " WHERE c.usuario_id = :usuario_id";
        $stmt_consultas = $pdo->prepare($consultas_sql);
        $stmt_consultas->execute([':usuario_id' => $user_id]);
    }
    $consultas_stats = $stmt_consultas->fetch(PDO::FETCH_ASSOC);

    // 7. KYC RECENTES (kyc_empresas com JOINs)
    $stmt_kyc_recentes = $pdo->query("
        SELECT ke.id, ke.razao_social as nome_empresa, ke.cnpj, ke.status, ke.data_criacao as created_at,
               e.nome AS nome_empresa_master
        FROM kyc_empresas ke
        LEFT JOIN empresas e ON ke.id_empresa_master = e.id
        ORDER BY ke.data_criacao DESC
        LIMIT 5
    ");
    $kyc_recentes = $stmt_kyc_recentes->fetchAll(PDO::FETCH_ASSOC);

    // 7. ÚLTIMAS CONSULTAS CNPJ (tabela consultas)
    $stmt_ultimas_consultas = $pdo->query("
        SELECT c.cnpj, c.razao_social, c.created_at,
               u.nome as nome_usuario
        FROM consultas c
        LEFT JOIN usuarios u ON c.usuario_id = u.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $consultas_recentes = $stmt_ultimas_consultas->fetchAll(PDO::FETCH_ASSOC);

    // 8. TEMPO MÉDIO DE ANÁLISE (KYC aprovados/reprovados)
    $stmt_tempo_medio = $pdo->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, data_criacao, data_atualizacao)) as horas_media
        FROM kyc_empresas
        WHERE analise_decisao_final IS NOT NULL AND data_atualizacao IS NOT NULL
    ");
    $tempo_medio_analise = $stmt_tempo_medio->fetch(PDO::FETCH_ASSOC)['horas_media'] ?? 0;

    // 9. CRESCIMENTO MENSAL DE CLIENTES (kyc_clientes)
    $stmt_crescimento = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as total
        FROM kyc_clientes
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY mes
        ORDER BY mes DESC
    ");
    $crescimento_clientes = $stmt_crescimento->fetchAll(PDO::FETCH_ASSOC);    // 10. USUÁRIOS MAIS ATIVOS (baseado em kyc_log_atividades)
    if ($is_superadmin || $is_admin) {
        $stmt_usuarios_ativos = $pdo->query("
            SELECT u.nome, COUNT(DISTINCT kla.kyc_empresa_id) as total_kyc
            FROM usuarios u
            LEFT JOIN kyc_log_atividades kla ON u.id = kla.usuario_id
            WHERE kla.timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY u.id, u.nome
            HAVING total_kyc > 0
            ORDER BY total_kyc DESC
            LIMIT 5
        ");
        $usuarios_ativos = $stmt_usuarios_ativos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $erro_carregamento = "Erro ao carregar dados: " . $e->getMessage();
    error_log("Dashboard Analytics Error: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <?php if ($erro_carregamento): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Erro:</strong> <?= htmlspecialchars($erro_carregamento) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-graph-up-arrow"></i> Dashboard Analytics</h2>
        <div class="text-muted">
            <i class="bi bi-calendar"></i> <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="row mb-4">
        <!-- Total Clientes -->
        <div class="col-md mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="w-100">
                            <h6 class="text-muted mb-2">Total de Clientes</h6>
                            <h3 class="mb-0"><?= number_format($total_clientes, 0, ',', '.') ?></h3>
                            <small class="text-muted">
                                PF: <?= $clientes_por_tipo['PF'] ?? 0 ?> | 
                                PJ: <?= $clientes_por_tipo['PJ'] ?? 0 ?>
                            </small>
                        </div>
                        <div class="text-primary" style="font-size: 2.5rem;">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total KYC -->
        <div class="col-md mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="w-100">
                            <h6 class="text-muted mb-2">Processos KYC</h6>
                            <h3 class="mb-0"><?= number_format($total_kyc, 0, ',', '.') ?></h3>
                            <small class="text-success">
                                ✓ <?= $kyc_por_status['Aprovado'] ?? 0 ?> aprovados
                            </small>
                        </div>
                        <div class="text-info" style="font-size: 2.5rem;">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas CEIS -->
        <div class="col-md mb-3">
            <div class="card border-0 shadow-sm h-100 border-start border-danger border-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="w-100">
                            <h6 class="text-muted mb-2">
                                Alertas CEIS
                                <i class="bi bi-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   title="Empresas com sanções CEIS detectadas (PJ ou sócios PF)"></i>
                            </h6>
                            <h3 class="mb-0 text-danger"><?= number_format($total_alertas_ceis, 0, ',', '.') ?></h3>
                            <small class="text-muted">
                                Empresas afetadas
                            </small>
                        </div>
                        <div class="text-danger" style="font-size: 2.5rem;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <?php if ($total_kyc > 0): ?>
                    <div class="progress mt-2" style="height: 8px;">
                        <?php $percent_ceis = ($total_alertas_ceis / $total_kyc) * 100; ?>
                        <div class="progress-bar bg-danger" style="width: <?= $percent_ceis ?>%"></div>
                    </div>
                    <small class="text-muted"><?= number_format($percent_ceis, 1) ?>% dos KYC</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alertas CNEP -->
        <div class="col-md mb-3">
            <div class="card border-0 shadow-sm h-100 border-start border-warning border-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="w-100">
                            <h6 class="text-muted mb-2">
                                Alertas CNEP
                                <i class="bi bi-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   title="Empresas com registros CNEP detectados (PJ ou sócios PF)"></i>
                            </h6>
                            <h3 class="mb-0 text-warning"><?= number_format($total_alertas_cnep, 0, ',', '.') ?></h3>
                            <small class="text-muted">
                                Empresas afetadas
                            </small>
                        </div>
                        <div class="text-warning" style="font-size: 2.5rem;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <?php if ($total_kyc > 0): ?>
                    <div class="progress mt-2" style="height: 8px;">
                        <?php $percent_cnep = ($total_alertas_cnep / $total_kyc) * 100; ?>
                        <div class="progress-bar bg-warning" style="width: <?= $percent_cnep ?>%"></div>
                    </div>
                    <small class="text-muted"><?= number_format($percent_cnep, 1) ?>% dos KYC</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alertas PEP -->
        <div class="col-md mb-3">
            <div class="card border-0 shadow-sm h-100 border-start border-info border-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="w-100">
                            <h6 class="text-muted mb-2">
                                Alertas PEP
                                <i class="bi bi-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" 
                                   title="Empresas com sócios identificados como Pessoas Politicamente Expostas"></i>
                            </h6>
                            <h3 class="mb-0 text-info"><?= number_format($total_alertas_pep, 0, ',', '.') ?></h3>
                            <small class="text-muted">
                                Empresas afetadas
                            </small>
                        </div>
                        <div class="text-info" style="font-size: 2.5rem;">
                            <i class="bi bi-person-badge"></i>
                        </div>
                    </div>
                    <?php if ($total_clientes > 0): ?>
                    <div class="progress mt-2" style="height: 8px;">
                        <?php $percent_pep = ($total_alertas_pep / $total_clientes) * 100; ?>
                        <div class="progress-bar bg-info" style="width: <?= $percent_pep ?>%"></div>
                    </div>
                    <small class="text-muted"><?= number_format($percent_pep, 1) ?>% dos clientes</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="row">
        <!-- Status dos KYC -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Status dos KYC</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th class="text-end">Quantidade</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Status reais do banco: "Aprovado", "Reprovado", "Em Análise", "Em Preenchimento", "Enviado", "Pendenciado"
                                $status_labels = [
                                    'Em Preenchimento' => ['label' => 'Em Preenchimento', 'class' => 'warning text-dark'],
                                    'Enviado' => ['label' => 'Enviado', 'class' => 'primary'],
                                    'Em Análise' => ['label' => 'Em Análise', 'class' => 'warning text-dark'],
                                    'Pendenciado' => ['label' => 'Pendenciado', 'class' => 'secondary'],
                                    'Aprovado' => ['label' => 'Aprovado', 'class' => 'success'],
                                    'Reprovado' => ['label' => 'Reprovado', 'class' => 'danger']
                                ];
                                
                                foreach ($status_labels as $status => $info):
                                    $count = $kyc_por_status[$status] ?? 0;
                                    $percent = $total_kyc > 0 ? ($count / $total_kyc) * 100 : 0;
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $info['class'] ?>"><?= $info['label'] ?></span></td>
                                    <td class="text-end"><?= $count ?></td>
                                    <td class="text-end"><?= number_format($percent, 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($tempo_medio_analise !== null && $tempo_medio_analise > 0): ?>
                    <div class="alert alert-light mb-0 mt-3">
                        <small><i class="bi bi-clock"></i> <strong>Tempo médio de análise:</strong>
                        <?= number_format($tempo_medio_analise, 1) ?> horas</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Compliance & Bases de Dados -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Compliance & Bases de Dados do Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-database"></i> Bases de Dados automatizadas
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-danger bg-opacity-10 rounded">
                                    <div class="text-danger fw-bold" style="font-size: 1.5rem;">
                                        <?= number_format($total_registros_ceis, 0, ',', '.') ?>
                                    </div>
                                    <small class="text-muted">CEIS</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                                    <div class="text-warning fw-bold" style="font-size: 1.5rem;">
                                        <?= number_format($total_registros_cnep, 0, ',', '.') ?>
                                    </div>
                                    <small class="text-muted">CNEP</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                                    <div class="text-info fw-bold" style="font-size: 1.5rem;">
                                        <?= number_format($total_registros_pep, 0, ',', '.') ?>
                                    </div>
                                    <small class="text-muted">PEP</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light mb-0">
                        <small class="text-muted">
                            <i class="bi bi-calendar-check"></i> 
                            <strong>Última atualização:</strong> 
                            <?= date('d/m/Y') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KYC Recentes e Últimas Consultas -->
    <div class="row">
        <!-- KYC Recentes -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> KYC Recentes</h5>
                    <a href="kyc_list.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Empresa</th>
                                    <th>CNPJ</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kyc_recentes as $kyc): ?>
                                <tr>
                                    <td><?= htmlspecialchars(substr($kyc['nome_empresa'], 0, 30)) ?></td>
                                    <td><small><?= htmlspecialchars($kyc['cnpj']) ?></small></td>
                                    <td>
                                        <?php
                                        // Mesma lógica de badges do kyc_list.php
                                        $badge_class = match($kyc['status']) {
                                            'Aprovado' => 'bg-success',
                                            'Reprovado' => 'bg-danger',
                                            'Em Preenchimento' => 'bg-warning text-dark',
                                            'Em Análise' => 'bg-warning text-dark',
                                            'Enviado' => 'bg-primary',
                                            'Pendenciado' => 'bg-secondary',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>
                                        <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($kyc['status']) ?></span>
                                    </td>
                                    <td><small><?= date('d/m/Y', strtotime($kyc['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Consultas -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-search"></i> Últimas Consultas CNPJ</h5>
                    <a href="consultas.php" class="btn btn-sm btn-outline-primary">Ver histórico</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Razão Social</th>
                                    <th>CNPJ</th>
                                    <th>Data/Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultas_recentes as $consulta): ?>
                                <tr>
                                    <td><?= htmlspecialchars(substr($consulta['razao_social'] ?? 'N/A', 0, 35)) ?></td>
                                    <td><small><?= htmlspecialchars($consulta['cnpj']) ?></small></td>
                                    <td><small><?= date('d/m/Y H:i', strtotime($consulta['created_at'])) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Crescimento e Usuários Ativos -->
    <div class="row">
        <!-- Crescimento de Clientes -->
        <div class="col-md-<?= ($is_superadmin || $is_admin) ? '6' : '12' ?> mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Crescimento de Clientes (6 meses)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Mês</th>
                                    <th class="text-end">Novos Clientes</th>
                                    <th class="text-end">Gráfico</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_crescimento = max(array_column($crescimento_clientes, 'total'));
                                foreach ($crescimento_clientes as $mes): 
                                    $percent = $max_crescimento > 0 ? ($mes['total'] / $max_crescimento) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= date('M/Y', strtotime($mes['mes'] . '-01')) ?></td>
                                    <td class="text-end"><strong><?= $mes['total'] ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usuários Mais Ativos -->
        <?php if ($is_superadmin || $is_admin): ?>
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Usuários Mais Ativos (30 dias)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nome</th>
                                    <th class="text-end">KYC Criados</th>
                                    <th class="text-end">Gráfico</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_atividade = $usuarios_ativos[0]['total_kyc'] ?? 1;
                                foreach ($usuarios_ativos as $index => $usuario): 
                                    $percent = ($usuario['total_kyc'] / $max_atividade) * 100;
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                    <td class="text-end"><strong><?= $usuario['total_kyc'] ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s;
}
.card:hover {
    transform: translateY(-5px);
}
</style>

<script>
// Inicializa tooltips do Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once 'footer.php'; ?>
