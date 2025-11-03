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
    // ===== FILTRO POR EMPRESA (Segurança) =====
    $where_empresa = "";
    $params_empresa = [];
    
    if ($user_role !== 'superadmin' && $user_empresa_id) {
        // Admin, Analista e Usuário veem apenas dados da sua empresa
        $where_empresa = " WHERE ke.id_empresa_master = :empresa_id";
        $params_empresa = [':empresa_id' => $user_empresa_id];
    }
    // Superadmin vê tudo (sem WHERE)
    
    // DEBUG: Log para verificar o que está sendo gerado
    error_log("DASHBOARD DEBUG - Role: $user_role | Empresa ID: $user_empresa_id | WHERE: $where_empresa");

    // ===== 1. CLIENTES CADASTRADOS (kyc_clientes) - Filtrados por empresa =====
    if ($user_role === 'superadmin') {
        $sql_total_clientes = "SELECT COUNT(*) as total FROM kyc_clientes";
        $stmt_total_clientes = $pdo->query($sql_total_clientes);
    } else {
        $sql_total_clientes = "SELECT COUNT(*) as total FROM kyc_clientes WHERE id_empresa_master = :empresa_id";
        $stmt_total_clientes = $pdo->prepare($sql_total_clientes);
        $stmt_total_clientes->execute([':empresa_id' => $user_empresa_id]);
    }
    $total_clientes = $stmt_total_clientes->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== 2. KYC STATUS (kyc_empresas) =====
    $sql_kyc_status = "SELECT status, COUNT(*) as total
                       FROM kyc_empresas ke
                       $where_empresa
                       GROUP BY status";
    $stmt_kyc_status = $pdo->prepare($sql_kyc_status);
    $stmt_kyc_status->execute($params_empresa);
    $kyc_por_status = [];
    $total_kyc = 0;
    while ($row = $stmt_kyc_status->fetch(PDO::FETCH_ASSOC)) {
        $kyc_por_status[$row['status']] = $row['total'];
        $total_kyc += $row['total'];
    }

    // Clientes com KYC Aprovado
    $total_clientes_aprovados = $kyc_por_status['Aprovado'] ?? 0;

    // ===== 3. MÉTRICAS DO FUNIL DE CONVERSÃO =====
    
    // 3.1 LEADS POR STATUS - Filtrados por empresa
    $leads_stats = ['novo' => 0, 'contatado' => 0, 'qualificado' => 0, 'convertido' => 0, 'perdido' => 0];
    $total_leads = 0;
    
    if ($user_role === 'superadmin') {
        $sql_leads = "SELECT status, COUNT(*) as total FROM leads GROUP BY status";
        $stmt_leads = $pdo->query($sql_leads);
    } else {
        $sql_leads = "SELECT status, COUNT(*) as total FROM leads WHERE id_empresa_master = :empresa_id GROUP BY status";
        $stmt_leads = $pdo->prepare($sql_leads);
        $stmt_leads->execute([':empresa_id' => $user_empresa_id]);
    }
    
    while ($row = $stmt_leads->fetch(PDO::FETCH_ASSOC)) {
        $leads_stats[$row['status']] = $row['total'];
        $total_leads += $row['total'];
    }
    
    // 3.2 CLIENTES EM REGISTRO (kyc_clientes cadastrados, independente de ter KYC ou não)
    // "Criando Cadastro" = Total de clientes registrados (não conta KYC "Em Preenchimento")
    if ($user_role === 'superadmin') {
        $sql_em_registro = "SELECT COUNT(*) as total FROM kyc_clientes";
        $stmt_em_registro = $pdo->query($sql_em_registro);
    } else {
        $sql_em_registro = "SELECT COUNT(*) as total FROM kyc_clientes WHERE id_empresa_master = :empresa_id";
        $stmt_em_registro = $pdo->prepare($sql_em_registro);
        $stmt_em_registro->execute([':empresa_id' => $user_empresa_id]);
    }
    $total_em_registro = $stmt_em_registro->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 3.3 KYC EM ANÁLISE (apenas "Em Análise" + "Pendenciado")
    // NÃO conta "Em Preenchimento" nem "Novo Registro"
    $total_em_analise = ($kyc_por_status['Em Análise'] ?? 0) + ($kyc_por_status['Pendenciado'] ?? 0);
    
    // DEBUG: Log detalhado do funil
    error_log("FUNIL DEBUG - Total Leads: {$total_leads} | Em Registro: {$total_em_registro} | Em Análise: {$total_em_analise} | Aprovados: {$total_clientes_aprovados}");
    error_log("KYC STATUS DEBUG - " . json_encode($kyc_por_status));
    
    // 3.4 TAXA DE CONVERSÃO (Leads → Clientes Aprovados)
    $taxa_conversao = $total_leads > 0 ? round(($total_clientes_aprovados / $total_leads) * 100, 1) : 0;

    // ===== FIM MÉTRICAS DO FUNIL =====

    // 3. ALERTAS CEIS (usando flags da tabela kyc_avaliacoes) - FILTRADO POR EMPRESA
    // av_check_ceis_ok: 1=OK, 0=Sanção encontrada, NULL=Não verificado
    // av_check_ceis_pf_ok: 1=OK, 0=Sanção em sócio PF
    $sql_ceis = "SELECT COUNT(DISTINCT ka.kyc_empresa_id) as total
                 FROM kyc_avaliacoes ka
                 INNER JOIN kyc_empresas ke ON ka.kyc_empresa_id = ke.id
                 $where_empresa
                 AND (ka.av_check_ceis_ok = 0 OR ka.av_check_ceis_pf_ok = 0)";
    $stmt_ceis = $pdo->prepare($sql_ceis);
    $stmt_ceis->execute($params_empresa);
    $total_alertas_ceis = $stmt_ceis->fetch(PDO::FETCH_ASSOC)['total'];

    // 4. ALERTAS CNEP (usando flags da tabela kyc_avaliacoes) - FILTRADO POR EMPRESA
    // av_check_cnep_ok: 1=OK, 0=Sanção encontrada (PJ)
    // av_check_cnep_pf_ok: 1=OK, 0=Sanção em sócio PF
    $sql_cnep = "SELECT COUNT(DISTINCT ka.kyc_empresa_id) as total
                 FROM kyc_avaliacoes ka
                 INNER JOIN kyc_empresas ke ON ka.kyc_empresa_id = ke.id
                 $where_empresa
                 AND (ka.av_check_cnep_ok = 0 OR ka.av_check_cnep_pf_ok = 0)";
    $stmt_cnep = $pdo->prepare($sql_cnep);
    $stmt_cnep->execute($params_empresa);
    $total_alertas_cnep = $stmt_cnep->fetch(PDO::FETCH_ASSOC)['total'];

    // 5. ALERTAS PEP (usando flag is_pep=1 da tabela kyc_socios) - FILTRADO POR EMPRESA
    $sql_pep = "SELECT COUNT(DISTINCT ks.empresa_id) as total
                FROM kyc_socios ks
                INNER JOIN kyc_empresas ke ON ks.empresa_id = ke.id
                $where_empresa
                AND ks.is_pep = 1";
    $stmt_pep_alertas = $pdo->prepare($sql_pep);
    $stmt_pep_alertas->execute($params_empresa);
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
               COUNT(CASE WHEN DATE(c.created_at) = CURDATE() THEN 1 END) as hoje,
               COUNT(CASE WHEN YEARWEEK(c.created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) as semana,
               COUNT(CASE WHEN YEAR(c.created_at) = YEAR(CURDATE()) AND MONTH(c.created_at) = MONTH(CURDATE()) THEN 1 END) as mes
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

    // 7. KYC RECENTES (kyc_empresas com JOINs + FLAGS DE ALERTA) - FILTRADO POR EMPRESA
    try {
        $sql_kyc_recentes = "SELECT ke.id, ke.razao_social as nome_empresa, ke.cnpj, ke.status, ke.data_criacao as created_at,
                                    e.nome AS nome_empresa_master,
                                    COALESCE(ka.av_check_ceis_ok, 1) as tem_ceis,
                                    COALESCE(ka.av_check_ceis_pf_ok, 1) as tem_ceis_pf,
                                    COALESCE(ka.av_check_cnep_ok, 1) as tem_cnep,
                                    COALESCE(ka.av_check_cnep_pf_ok, 1) as tem_cnep_pf,
                                    (SELECT COUNT(*) FROM kyc_socios ks WHERE ks.empresa_id = ke.id AND ks.is_pep = 1) as tem_pep
                             FROM kyc_empresas ke
                             LEFT JOIN empresas e ON ke.id_empresa_master = e.id
                             LEFT JOIN kyc_avaliacoes ka ON ke.id = ka.kyc_empresa_id
                             $where_empresa
                             ORDER BY ke.data_criacao DESC
                             LIMIT 5";
        $stmt_kyc_recentes = $pdo->prepare($sql_kyc_recentes);
        $stmt_kyc_recentes->execute($params_empresa);
        $kyc_recentes = $stmt_kyc_recentes->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("KYC RECENTES ERROR: " . $e->getMessage() . " | SQL: " . $sql_kyc_recentes);
        $kyc_recentes = [];
    }

    // 8. ÚLTIMAS CONSULTAS CNPJ (tabela consultas) - JÁ FILTRADA ACIMA
    // Reutiliza a mesma lógica de permissão das consultas
    try {
        $consultas_recentes_sql = "SELECT c.cnpj, c.razao_social, c.created_at,
                                          u.nome as nome_usuario
                                   FROM consultas c
                                   LEFT JOIN usuarios u ON c.usuario_id = u.id";
        
        if ($user_role === 'superadmin') {
            $consultas_recentes_sql .= " ORDER BY c.created_at DESC LIMIT 5";
            $stmt_ultimas_consultas = $pdo->query($consultas_recentes_sql);
        } elseif (in_array($user_role, ['admin', 'administrador'])) {
            $consultas_recentes_sql .= " WHERE u.empresa_id = :empresa_id ORDER BY c.created_at DESC LIMIT 5";
            $stmt_ultimas_consultas = $pdo->prepare($consultas_recentes_sql);
            $stmt_ultimas_consultas->execute([':empresa_id' => $user_empresa_id]);
        } else {
            $consultas_recentes_sql .= " WHERE c.usuario_id = :usuario_id ORDER BY c.created_at DESC LIMIT 5";
            $stmt_ultimas_consultas = $pdo->prepare($consultas_recentes_sql);
            $stmt_ultimas_consultas->execute([':usuario_id' => $user_id]);
        }
        $consultas_recentes = $stmt_ultimas_consultas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("CONSULTAS RECENTES ERROR: " . $e->getMessage() . " | SQL: " . $consultas_recentes_sql);
        $consultas_recentes = [];
    }

    // 9. TEMPO MÉDIO DE ANÁLISE (KYC aprovados/reprovados) - FILTRADO POR EMPRESA
    $sql_tempo_medio = "SELECT AVG(TIMESTAMPDIFF(HOUR, data_criacao, data_atualizacao)) as horas_media
                        FROM kyc_empresas ke
                        $where_empresa";
    if ($where_empresa) {
        $sql_tempo_medio .= " AND analise_decisao_final IS NOT NULL AND data_atualizacao IS NOT NULL";
    } else {
        $sql_tempo_medio .= " WHERE analise_decisao_final IS NOT NULL AND data_atualizacao IS NOT NULL";
    }
    $stmt_tempo_medio = $pdo->prepare($sql_tempo_medio);
    $stmt_tempo_medio->execute($params_empresa);
    $tempo_medio_analise = $stmt_tempo_medio->fetch(PDO::FETCH_ASSOC)['horas_media'] ?? 0;

    // 10. CRESCIMENTO MENSAL DE CLIENTES (kyc_clientes) - FILTRADO POR EMPRESA
    try {
        $sql_crescimento = "SELECT DATE_FORMAT(kc.created_at, '%Y-%m') as mes, COUNT(*) as total
                            FROM kyc_clientes kc
                            INNER JOIN kyc_empresas ke ON kc.id = ke.cliente_id";
        
        if ($where_empresa) {
            // Se já tem WHERE, usa AND para adicionar condição de data
            $sql_crescimento .= " $where_empresa AND kc.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        } else {
            // Se não tem WHERE, adiciona WHERE para a condição de data
            $sql_crescimento .= " WHERE kc.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        }
        
        $sql_crescimento .= " GROUP BY DATE_FORMAT(kc.created_at, '%Y-%m') ORDER BY mes DESC";
        
        $stmt_crescimento = $pdo->prepare($sql_crescimento);
        $stmt_crescimento->execute($params_empresa);
        $crescimento_clientes = $stmt_crescimento->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("CRESCIMENTO ERROR: " . $e->getMessage() . " | SQL: " . $sql_crescimento . " | PARAMS: " . print_r($params_empresa, true));
        $crescimento_clientes = [];
    }    // 11. USUÁRIOS MAIS ATIVOS (baseado em kyc_log_atividades) - FILTRADO POR EMPRESA
    if ($is_superadmin || $is_admin) {
        // Superadmin vê empresa do usuário, Admin vê apenas seus usuários
        if ($user_role === 'superadmin') {
            $sql_usuarios_ativos = "SELECT u.nome, e.nome as empresa_nome, COUNT(DISTINCT kla.kyc_empresa_id) as total_kyc
                                    FROM usuarios u
                                    LEFT JOIN kyc_log_atividades kla ON u.id = kla.usuario_id AND kla.timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                    LEFT JOIN kyc_empresas ke ON kla.kyc_empresa_id = ke.id
                                    LEFT JOIN empresas e ON u.empresa_id = e.id
                                    GROUP BY u.id, u.nome, e.nome
                                    HAVING total_kyc > 0
                                    ORDER BY total_kyc DESC
                                    LIMIT 5";
            $stmt_usuarios_ativos = $pdo->query($sql_usuarios_ativos);
        } else {
            // Admin vê apenas usuários da sua empresa (com atividade nos últimos 30 dias)
            $sql_usuarios_ativos = "SELECT u.nome, COUNT(DISTINCT CASE WHEN ke.id_empresa_master = :empresa_id THEN kla.kyc_empresa_id END) as total_kyc
                                    FROM usuarios u
                                    LEFT JOIN kyc_log_atividades kla ON u.id = kla.usuario_id AND kla.timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                    LEFT JOIN kyc_empresas ke ON kla.kyc_empresa_id = ke.id
                                    WHERE u.empresa_id = :empresa_id2
                                    GROUP BY u.id, u.nome
                                    HAVING total_kyc > 0
                                    ORDER BY total_kyc DESC
                                    LIMIT 5";
            $stmt_usuarios_ativos = $pdo->prepare($sql_usuarios_ativos);
            $stmt_usuarios_ativos->execute([
                ':empresa_id' => $user_empresa_id,
                ':empresa_id2' => $user_empresa_id
            ]);
        }
        $usuarios_ativos = $stmt_usuarios_ativos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $erro_carregamento = "Erro ao carregar dados: " . $e->getMessage();
    error_log("Dashboard Analytics Error: " . $e->getMessage());
}
?>

<style>
/* Estilos do Funil de Conversão */
.funnel-step {
    transition: all 0.3s ease;
}

.funnel-step:hover {
    transform: translateY(-5px);
}

.funnel-arrow {
    display: none;
}

@media (min-width: 768px) {
    .funnel-arrow {
        display: block;
        position: absolute;
        right: -20px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .col-md {
        position: relative;
    }
    
    .col-md:last-child .funnel-arrow {
        display: none;
    }
}

.funnel-icon {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

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
                            <small class="text-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <?= number_format($total_clientes_aprovados, 0, ',', '.') ?> com KYC Aprovado
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

    <!-- Funil de Conversão: Lead → Cliente Aprovado -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="mb-0"><i class="bi bi-funnel-fill"></i> Funil de Conversão: Lead → Cliente Aprovado</h5>
                    <small>Jornada completa do lead até se tornar cliente ativo</small>
                </div>
                <div class="card-body p-4">
                    <div class="row text-center">
                        <!-- Etapa 1: Leads Novos -->
                        <div class="col-md">
                            <div class="funnel-step position-relative">
                                <div class="funnel-icon mb-3" style="font-size: 3rem; color: #6c757d;">
                                    <i class="bi bi-person-plus-fill"></i>
                                </div>
                                <h4 class="fw-bold"><?= number_format($leads_stats['novo'], 0, ',', '.') ?></h4>
                                <p class="text-muted mb-2">Leads Novos</p>
                                <span class="badge bg-secondary">Etapa 1</span>
                                <?php if ($total_leads > 0): ?>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-secondary" style="width: 100%"></div>
                                </div>
                                <small class="text-muted">100% do topo</small>
                                <?php endif; ?>
                            </div>
                            <div class="funnel-arrow text-muted mt-3 mb-3">
                                <i class="bi bi-arrow-right" style="font-size: 2rem;"></i>
                            </div>
                        </div>

                        <!-- Etapa 2: Leads Contatados -->
                        <div class="col-md">
                            <div class="funnel-step">
                                <div class="funnel-icon mb-3" style="font-size: 3rem; color: #0dcaf0;">
                                    <i class="bi bi-envelope-check-fill"></i>
                                </div>
                                <h4 class="fw-bold"><?= number_format($leads_stats['contatado'], 0, ',', '.') ?></h4>
                                <p class="text-muted mb-2">Contatados</p>
                                <span class="badge bg-info">Etapa 2</span>
                                <?php if ($total_leads > 0): 
                                    $percent_contatado = ($leads_stats['contatado'] / $total_leads) * 100;
                                ?>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?= $percent_contatado ?>%"></div>
                                </div>
                                <small class="text-muted"><?= number_format($percent_contatado, 1) ?>% do topo</small>
                                <?php endif; ?>
                            </div>
                            <div class="funnel-arrow text-muted mt-3 mb-3">
                                <i class="bi bi-arrow-right" style="font-size: 2rem;"></i>
                            </div>
                        </div>

                        <!-- Etapa 3: Em Registro -->
                        <div class="col-md">
                            <div class="funnel-step">
                                <div class="funnel-icon mb-3" style="font-size: 3rem; color: #fd7e14;">
                                    <i class="bi bi-pencil-square"></i>
                                </div>
                                <h4 class="fw-bold"><?= number_format($total_em_registro, 0, ',', '.') ?></h4>
                                <p class="text-muted mb-2">Criando Cadastro</p>
                                <span class="badge bg-warning text-dark">Etapa 3</span>
                                <?php if ($total_leads > 0): 
                                    $percent_registro = ($total_em_registro / $total_leads) * 100;
                                ?>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?= $percent_registro ?>%"></div>
                                </div>
                                <small class="text-muted"><?= number_format($percent_registro, 1) ?>% do topo</small>
                                <?php endif; ?>
                            </div>
                            <div class="funnel-arrow text-muted mt-3 mb-3">
                                <i class="bi bi-arrow-right" style="font-size: 2rem;"></i>
                            </div>
                        </div>

                        <!-- Etapa 4: KYC em Análise -->
                        <div class="col-md">
                            <div class="funnel-step">
                                <div class="funnel-icon mb-3" style="font-size: 3rem; color: #0d6efd;">
                                    <i class="bi bi-search"></i>
                                </div>
                                <h4 class="fw-bold"><?= number_format($total_em_analise, 0, ',', '.') ?></h4>
                                <p class="text-muted mb-2">KYC em Análise</p>
                                <span class="badge bg-primary">Etapa 4</span>
                                <?php if ($total_leads > 0): 
                                    $percent_analise = ($total_em_analise / $total_leads) * 100;
                                ?>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $percent_analise ?>%"></div>
                                </div>
                                <small class="text-muted"><?= number_format($percent_analise, 1) ?>% do topo</small>
                                <?php endif; ?>
                            </div>
                            <div class="funnel-arrow text-muted mt-3 mb-3">
                                <i class="bi bi-arrow-right" style="font-size: 2rem;"></i>
                            </div>
                        </div>

                        <!-- Etapa 5: Clientes Aprovados -->
                        <div class="col-md">
                            <div class="funnel-step">
                                <div class="funnel-icon mb-3" style="font-size: 3rem; color: #198754;">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <h4 class="fw-bold text-success"><?= number_format($total_clientes_aprovados, 0, ',', '.') ?></h4>
                                <p class="text-muted mb-2">Clientes Aprovados</p>
                                <span class="badge bg-success">✓ Completo</span>
                                <?php if ($total_leads > 0): ?>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?= $taxa_conversao ?>%"></div>
                                </div>
                                <small class="text-success fw-bold">Taxa de conversão: <?= $taxa_conversao ?>%</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo Inferior -->
                    <div class="row mt-4 pt-4 border-top">
                        <div class="col-md-3 text-center">
                            <div class="p-3 bg-light rounded">
                                <h6 class="text-muted mb-2">Total de Leads</h6>
                                <h3 class="mb-0"><?= number_format($total_leads, 0, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 bg-light rounded">
                                <h6 class="text-muted mb-2">Leads Perdidos</h6>
                                <h3 class="mb-0 text-danger"><?= number_format($leads_stats['perdido'], 0, ',', '.') ?></h3>
                                <?php if ($total_leads > 0): 
                                    $percent_perdido = ($leads_stats['perdido'] / $total_leads) * 100;
                                ?>
                                <small class="text-muted"><?= number_format($percent_perdido, 1) ?>%</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 bg-light rounded">
                                <h6 class="text-muted mb-2">Em Processo</h6>
                                <?php $em_processo = $leads_stats['contatado'] + $leads_stats['qualificado'] + $total_em_registro + $total_em_analise; ?>
                                <h3 class="mb-0 text-primary"><?= number_format($em_processo, 0, ',', '.') ?></h3>
                                <?php if ($total_leads > 0): 
                                    $percent_processo = ($em_processo / $total_leads) * 100;
                                ?>
                                <small class="text-muted"><?= number_format($percent_processo, 1) ?>%</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="p-3 bg-success bg-opacity-10 rounded border border-success">
                                <h6 class="text-success mb-2"><i class="bi bi-trophy-fill"></i> Taxa de Sucesso</h6>
                                <h3 class="mb-0 text-success"><?= $taxa_conversao ?>%</h3>
                                <small class="text-muted">Lead → Cliente</small>
                            </div>
                        </div>
                    </div>
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
                                // Status reais do banco: "Aprovado", "Reprovado", "Em Análise", "Em Preenchimento", "Novo Registro", "Pendenciado"
                                $status_labels = [
                                    'Em Preenchimento' => ['label' => 'Em Preenchimento', 'class' => 'secondary'],
                                    'Novo Registro' => ['label' => 'Novo Registro', 'class' => 'primary'],
                                    'Em Análise' => ['label' => 'Em Análise', 'class' => 'info'],
                                    'Pendenciado' => ['label' => 'Pendenciado', 'class' => 'warning text-dark'],
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
                                        // Badges sem ícones (simplificado)
                                        $badge_class = '';
                                        
                                        switch ($kyc['status']) {
                                            case 'Aprovado':
                                                $badge_class = 'bg-success';
                                                break;
                                            case 'Reprovado':
                                                $badge_class = 'bg-danger';
                                                break;
                                            case 'Em Análise':
                                                $badge_class = 'bg-info';
                                                break;
                                            case 'Pendenciado':
                                                $badge_class = 'bg-warning text-dark';
                                                break;
                                            case 'Em Preenchimento':
                                                $badge_class = 'bg-secondary';
                                                break;
                                            case 'Novo Registro':
                                                $badge_class = 'bg-primary';
                                                break;
                                            default:
                                                $badge_class = 'bg-light text-dark';
                                        }
                                        
                                        // Verificar se tem alertas
                                        $tem_alerta_ceis = ($kyc['tem_ceis'] == 0 || $kyc['tem_ceis_pf'] == 0);
                                        $tem_alerta_cnep = ($kyc['tem_cnep'] == 0 || $kyc['tem_cnep_pf'] == 0);
                                        $tem_alerta_pep = ($kyc['tem_pep'] > 0);
                                        ?>
                                        <span class="badge <?= $badge_class ?>">
                                            <?= htmlspecialchars($kyc['status']) ?>
                                        </span>
                                        
                                        <!-- Ícones de Alerta -->
                                        <?php if ($tem_alerta_ceis || $tem_alerta_cnep || $tem_alerta_pep): ?>
                                        <div class="mt-1">
                                            <?php if ($tem_alerta_ceis): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-danger" 
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="top" 
                                               title="Alerta CEIS"></i>
                                            <?php endif; ?>
                                            
                                            <?php if ($tem_alerta_cnep): ?>
                                            <i class="bi bi-exclamation-diamond-fill text-warning" 
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="top" 
                                               title="Alerta CNEP"></i>
                                            <?php endif; ?>
                                            
                                            <?php if ($tem_alerta_pep): ?>
                                            <i class="bi bi-person-fill-exclamation" 
                                               style="color: #6f42c1;"
                                               data-bs-toggle="tooltip" 
                                               data-bs-placement="top" 
                                               title="Pessoa Exposta Politicamente"></i>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
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
                                if (!empty($crescimento_clientes)) {
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
                                <?php 
                                    endforeach;
                                } else {
                                ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Nenhum dado disponível nos últimos 6 meses</td>
                                </tr>
                                <?php } ?>
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
                                    <?php if ($is_superadmin): ?>
                                    <th>Empresa</th>
                                    <?php endif; ?>
                                    <th class="text-end">KYC Criados</th>
                                    <th class="text-end">Gráfico</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($usuarios_ativos)) {
                                    $max_atividade = $usuarios_ativos[0]['total_kyc'] ?? 1;
                                    foreach ($usuarios_ativos as $index => $usuario): 
                                        $percent = ($usuario['total_kyc'] / $max_atividade) * 100;
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($usuario['nome']) ?></td>
                                    <?php if ($is_superadmin): ?>
                                    <td><small class="text-muted"><?= htmlspecialchars($usuario['empresa_nome'] ?? 'N/A') ?></small></td>
                                    <?php endif; ?>
                                    <td class="text-end"><strong><?= $usuario['total_kyc'] ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: <?= $percent ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                } else {
                                ?>
                                <tr>
                                    <td colspan="<?= $is_superadmin ? '5' : '4' ?>" class="text-center text-muted">
                                        Nenhum usuário ativo nos últimos 30 dias
                                    </td>
                                </tr>
                                <?php } ?>
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
