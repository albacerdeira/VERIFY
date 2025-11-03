<?php
$page_title = 'Sistema de Leads - Documentação';
require_once 'bootstrap.php';

if (!$is_admin && !$is_superadmin) {
    require_once 'header.php';
    echo \"<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>\";
    require_once 'footer.php';
    exit;
}

require_once 'header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="bi bi-book text-primary"></i> Sistema de Captura e Conversão de Leads</h2>
                    <p class="text-muted mb-0">Documentação completa sobre Gestão de Leads, Conversão Automática e Integrações</p>
                </div>
                <a href="configuracoes.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Voltar para Configurações
                </a>
            </div>
        </div>
    </div>

    <!-- Visão Geral -->
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Visão Geral do Sistema</h5>
        </div>
        <div class="card-body">
            <p class="lead">Sistema completo para capturar, gerenciar e converter leads em clientes KYC automaticamente.</p>
            <p>O Verify oferece múltiplas formas de captura, centralização em CRM integrado e conversão automática para processo KYC.</p>
        </div>
    </div>

    <!-- Placeholder para conteúdo futuro -->
    <div class="alert alert-info">
        <h5><i class="bi bi-tools"></i> Em construção</h5>
        <p class="mb-0">Esta página está sendo desenvolvida com documentação completa sobre Gestão de Leads, Conversão Automática e Integrações.</p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
