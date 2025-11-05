<?php
/**
 * Debug Lead → Cliente → Empresa Linkage
 * Verifica o vínculo completo entre leads, clientes e empresas
 */

require_once 'config.php';

// Captura parâmetros de busca
$search_term = $_GET['search'] ?? null;
$cliente_id = $_GET['cliente_id'] ?? null;
$lead_id = null;
$lead = null;
$cliente = null;

// Se tiver termo de busca, encontra o lead
if ($search_term) {
    // Tenta primeiro como ID numérico
    if (is_numeric($search_term)) {
        $stmt_search = $pdo->prepare("SELECT id FROM leads WHERE id = ?");
        $stmt_search->execute([$search_term]);
        $result = $stmt_search->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lead_id = $result['id'];
        }
    }
    
    // Se não encontrou por ID, busca por nome (LIKE)
    if (!$lead_id) {
        $stmt_search = $pdo->prepare("SELECT id FROM leads WHERE nome LIKE ? ORDER BY created_at DESC LIMIT 1");
        $stmt_search->execute(['%' . $search_term . '%']);
        $result = $stmt_search->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lead_id = $result['id'];
        }
    }
}

// Se tiver cliente_id, busca qual lead está vinculado
if ($cliente_id && !$lead_id) {
    $stmt_cliente_lead = $pdo->prepare("SELECT lead_id FROM kyc_clientes WHERE id = ?");
    $stmt_cliente_lead->execute([$cliente_id]);
    $result_cliente = $stmt_cliente_lead->fetch(PDO::FETCH_ASSOC);
    if ($result_cliente && $result_cliente['lead_id']) {
        $lead_id = $result_cliente['lead_id'];
    }
}

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug: Lead → Cliente → Empresa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 30px; }
        .debug-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .field-row { display: flex; padding: 8px; border-bottom: 1px solid #eee; }
        .field-label { font-weight: 600; min-width: 200px; color: #555; }
        .field-value { flex: 1; color: #000; }
        .null-value { color: #dc3545; font-style: italic; }
        .success-value { color: #28a745; font-weight: 600; }
        h1 { color: #333; margin-bottom: 30px; }
        h2 { color: #4f46e5; font-size: 1.3rem; margin-bottom: 15px; }
        .badge { font-size: 0.9rem; }
        .alert-info { background: #e7f3ff; border-color: #b3d9ff; color: #004085; }
        .search-box { background: white; border-radius: 8px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug: Lead → Cliente → Empresa</h1>

        <!-- Formulário de Busca -->
        <div class="search-box">
            <h2>🔎 Buscar Lead ou Cliente</h2>
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label for="search" class="form-label">ID ou Nome do Lead</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Ex: 29 ou Alba Cerdeira" 
                           value="<?= htmlspecialchars($search_term ?? '') ?>">
                    <small class="text-muted">Digite o ID numérico ou parte do nome</small>
                </div>
                <div class="col-md-1 text-center" style="padding-top: 32px;">
                    <strong>OU</strong>
                </div>
                <div class="col-md-4">
                    <label for="cliente_id" class="form-label">ID do Cliente</label>
                    <input type="number" class="form-control" id="cliente_id" name="cliente_id" 
                           placeholder="Ex: 52" 
                           value="<?= htmlspecialchars($cliente_id ?? '') ?>">
                    <small class="text-muted">Busca o lead vinculado a este cliente</small>
                </div>
                <div class="col-md-2" style="padding-top: 32px;">
                    <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
                </div>
            </form>
            
            <?php if ($search_term && !$lead_id): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    ⚠️ Nenhum lead encontrado com o termo "<strong><?= htmlspecialchars($search_term) ?></strong>"
                </div>
            <?php endif; ?>
            
            <?php if ($cliente_id && !$lead_id && !$search_term): ?>
                <div class="alert alert-warning mt-3 mb-0">
                    ⚠️ Cliente #<?= htmlspecialchars($cliente_id) ?> não está vinculado a nenhum lead
                </div>
            <?php endif; ?>
        </div>

        <?php if ($lead_id): ?>
        
        <?php
        // 1. Buscar dados do Lead
        echo '<div class="debug-section">';
        echo '<h2>📋 Lead #' . $lead_id . '</h2>';
        
        try {
            $stmt_lead = $pdo->prepare("
                SELECT l.*, e.nome as empresa_parceira
                FROM leads l
                LEFT JOIN empresas e ON l.id_empresa_master = e.id
                WHERE l.id = ?
            ");
            $stmt_lead->execute([$lead_id]);
            $lead = $stmt_lead->fetch(PDO::FETCH_ASSOC);
            
            if ($lead) {
                echo '<div class="field-row"><div class="field-label">ID:</div><div class="field-value">' . $lead['id'] . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Nome:</div><div class="field-value">' . (!empty($lead['nome']) && $lead['nome'] !== '' ? htmlspecialchars($lead['nome']) : '<span class="null-value">Não informado</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Email:</div><div class="field-value">' . htmlspecialchars($lead['email']) . '</div></div>';
                echo '<div class="field-row"><div class="field-label">CPF:</div><div class="field-value">' . (!empty($lead['cpf']) && $lead['cpf'] !== '' ? $lead['cpf'] : '<span class="null-value">Não informado</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Empresa Parceira:</div><div class="field-value">' . ($lead['empresa_parceira'] ?: '<span class="null-value">Nenhuma</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Status:</div><div class="field-value"><span class="badge bg-info">' . $lead['status'] . '</span></div></div>';
                echo '<div class="field-row"><div class="field-label">Criado em:</div><div class="field-value">' . ($lead['created_at'] ? date('d/m/Y H:i', strtotime($lead['created_at'])) : '<span class="null-value">Não informado</span>') . '</div></div>';
            } else {
                echo '<div class="alert alert-warning">Lead #' . $lead_id . ' não encontrado!</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Erro ao buscar lead: ' . $e->getMessage() . '</div>';
        }
        
        echo '</div>';

        // 2. Buscar dados do Cliente vinculado a este Lead
        echo '<div class="debug-section">';
        echo '<h2>👤 Clientes Vinculados ao Lead #' . $lead_id . '</h2>';
        
        try {
            $stmt_clientes_vinculados = $pdo->prepare("
                SELECT kc.*, e.nome as empresa_master
                FROM kyc_clientes kc
                LEFT JOIN empresas e ON kc.id_empresa_master = e.id
                WHERE kc.lead_id = ?
            ");
            $stmt_clientes_vinculados->execute([$lead_id]);
            $clientes_vinculados = $stmt_clientes_vinculados->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($clientes_vinculados) > 0) {
                foreach ($clientes_vinculados as $cliente) {
                    echo '<div style="border-left: 4px solid #28a745; padding-left: 15px; margin-bottom: 15px;">';
                    echo '<div class="field-row"><div class="field-label">ID:</div><div class="field-value">' . $cliente['id'] . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Nome:</div><div class="field-value">' . htmlspecialchars($cliente['nome_completo']) . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Email:</div><div class="field-value">' . htmlspecialchars($cliente['email']) . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">CPF:</div><div class="field-value">' . ($cliente['cpf'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Lead ID:</div><div class="field-value success-value">✓ ' . $cliente['lead_id'] . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Origem:</div><div class="field-value">' . ($cliente['origem'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Empresa Master:</div><div class="field-value">' . ($cliente['empresa_master'] ?: '<span class="null-value">Nenhuma</span>') . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Dados Pessoais OK:</div><div class="field-value">' . ($cliente['dados_completos_preenchidos'] ? '<span class="success-value">✓ SIM</span>' : '<span class="null-value">✗ NÃO</span>') . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Status:</div><div class="field-value"><span class="badge bg-secondary">' . $cliente['status'] . '</span></div></div>';
                    echo '<div class="field-row"><div class="field-label">Criado em:</div><div class="field-value">' . date('d/m/Y H:i', strtotime($cliente['created_at'])) . '</div></div>';
                    
                    // Buscar empresas deste cliente
                    $stmt_empresas_cliente = $pdo->prepare("SELECT COUNT(*) as total FROM kyc_empresas WHERE cliente_id = ?");
                    $stmt_empresas_cliente->execute([$cliente['id']]);
                    $count_empresas = $stmt_empresas_cliente->fetch(PDO::FETCH_ASSOC)['total'];
                    echo '<div class="field-row"><div class="field-label">Empresas Cadastradas:</div><div class="field-value"><strong>' . $count_empresas . '</strong></div></div>';
                    
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-warning">Nenhum cliente vinculado a este lead ainda.</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Erro ao buscar clientes: ' . $e->getMessage() . '</div>';
        }
        
        echo '</div>';

        // 3. Buscar dados do Cliente específico (se foi informado)
        if ($cliente_id) {
            echo '<div class="debug-section">';
            echo '<h2>👤 Detalhes do Cliente #' . $cliente_id . '</h2>';
        
        try {
            $stmt_cliente = $pdo->prepare("
                SELECT kc.*, e.nome as empresa_master
                FROM kyc_clientes kc
                LEFT JOIN empresas e ON kc.id_empresa_master = e.id
                WHERE kc.id = ?
            ");
            $stmt_cliente->execute([$cliente_id]);
            $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente) {
                // SEÇÃO 1: IDENTIFICAÇÃO BÁSICA
                echo '<h4 class="mt-3 mb-2" style="color: #4f46e5; font-size: 1.1rem;">📋 Identificação Básica</h4>';
                echo '<div class="field-row"><div class="field-label">ID:</div><div class="field-value">' . $cliente['id'] . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Nome Completo:</div><div class="field-value">' . htmlspecialchars($cliente['nome_completo']) . '</div></div>';
                echo '<div class="field-row"><div class="field-label">CPF:</div><div class="field-value">' . ($cliente['cpf'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">RG:</div><div class="field-value">' . ($cliente['rg'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Data de Nascimento:</div><div class="field-value">' . ($cliente['data_nascimento'] ? date('d/m/Y', strtotime($cliente['data_nascimento'])) : '<span class="null-value">NULL</span>') . '</div></div>';
                
                // SEÇÃO 2: CONTATO
                echo '<h4 class="mt-3 mb-2" style="color: #4f46e5; font-size: 1.1rem;">📞 Contato</h4>';
                echo '<div class="field-row"><div class="field-label">Email:</div><div class="field-value">' . htmlspecialchars($cliente['email']) . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Telefone:</div><div class="field-value">' . ($cliente['telefone'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Email Verificado:</div><div class="field-value">' . ($cliente['email_verificado'] ? '<span class="success-value">✓ SIM</span>' : '<span class="null-value">✗ NÃO</span>') . '</div></div>';
                
                // SEÇÃO 3: FILIAÇÃO
                echo '<h4 class="mt-3 mb-2" style="color: #4f46e5; font-size: 1.1rem;">👨‍👩‍👦 Filiação</h4>';
                echo '<div class="field-row"><div class="field-label">Nome do Pai:</div><div class="field-value">' . ($cliente['nome_pai'] ? htmlspecialchars($cliente['nome_pai']) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Nome da Mãe:</div><div class="field-value">' . ($cliente['nome_mae'] ? htmlspecialchars($cliente['nome_mae']) : '<span class="null-value">NULL</span>') . '</div></div>';
                
                // SEÇÃO 4: ENDEREÇO COMPLETO
                echo '<h4 class="mt-3 mb-2" style="color: #4f46e5; font-size: 1.1rem;">🏠 Endereço Completo</h4>';
                echo '<div class="field-row"><div class="field-label">CEP:</div><div class="field-value">' . ($cliente['endereco_cep'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Logradouro:</div><div class="field-value">' . ($cliente['endereco_rua'] ? htmlspecialchars($cliente['endereco_rua']) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Número:</div><div class="field-value">' . ($cliente['endereco_numero'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Complemento:</div><div class="field-value">' . ($cliente['endereco_complemento'] ? htmlspecialchars($cliente['endereco_complemento']) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Bairro:</div><div class="field-value">' . ($cliente['endereco_bairro'] ? htmlspecialchars($cliente['endereco_bairro']) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Cidade:</div><div class="field-value">' . ($cliente['endereco_cidade'] ? htmlspecialchars($cliente['endereco_cidade']) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Estado (UF):</div><div class="field-value">' . ($cliente['endereco_estado'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                
                // SEÇÃO 5: DOCUMENTOS E SELFIE
                echo '<h4 class="mt-3 mb-2" style="color: #4f46e5; font-size: 1.1rem;">📄 Documentos e Verificação</h4>';
                echo '<div class="field-row"><div class="field-label">Foto do Documento:</div><div class="field-value">';
                if ($cliente['documento_foto_path']) {
                    echo '<a href="' . htmlspecialchars($cliente['documento_foto_path']) . '" target="_blank" class="btn btn-sm btn-outline-primary">📄 Ver Documento</a>';
                } else {
                    echo '<span class="null-value">✗ Não enviado</span>';
                }
                echo '</div></div>';
                
                echo '<div class="field-row"><div class="field-label">Selfie:</div><div class="field-value">';
                if ($cliente['selfie_path']) {
                    echo '<a href="' . htmlspecialchars($cliente['selfie_path']) . '" target="_blank" class="btn btn-sm btn-outline-primary">📷 Ver Selfie</a>';
                } else {
                    echo '<span class="null-value">✗ Não enviado</span>';
                }
                echo '</div></div>';
                
                // VALIDAÇÃO VISUAL INLINE
                if ($cliente['selfie_path'] && $cliente['documento_foto_path']) {
                    echo '<div style="background: #d1ecf1; border: 2px solid #17a2b8; border-radius: 8px; padding: 15px; margin: 15px 0;">';
                    echo '<div style="display: flex; align-items: center; gap: 15px;">';
                    echo '<div style="flex: 1;">';
                    echo '<strong style="color: #0c5460; font-size: 1.05rem;">✓ Pronto para Validação Biométrica</strong><br>';
                    echo '<small style="color: #0c5460;">Selfie e documento enviados. Clique ao lado para validar identidade.</small>';
                    echo '</div>';
                    echo '<div>';
                    echo '<a href="cliente_edit.php?id=' . $cliente['id'] . '#validacao-biometrica" class="btn btn-success" style="white-space: nowrap;">🔍 Validar Identidade</a>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                } elseif ($cliente['selfie_path'] || $cliente['documento_foto_path']) {
                    echo '<div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0;">';
                    echo '<strong style="color: #856404;">⚠️ Validação Incompleta</strong><br>';
                    echo '<small style="color: #856404;">Falta enviar: ';
                    if (!$cliente['selfie_path']) echo '<strong>Selfie</strong>';
                    if (!$cliente['documento_foto_path']) echo '<strong>Documento de Identidade</strong>';
                    echo '</small>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; padding: 15px; margin: 15px 0;">';
                    echo '<strong style="color: #721c24;">✗ Documentos Pendentes</strong><br>';
                    echo '<small style="color: #721c24;">Cliente ainda não enviou selfie nem documento</small>';
                    echo '</div>';
                }
                
                echo '<div class="field-row"><div class="field-label">Dados Pessoais Completos:</div><div class="field-value">' . ($cliente['dados_completos_preenchidos'] ? '<span class="success-value">✓ SIM</span>' : '<span class="null-value">✗ NÃO</span>') . '</div></div>';
                
                // SEÇÃO 6: VÍNCULO E ORIGEM
                echo '<h4 class="mt-3 mb-2" style="color: #4f46e5; font-size: 1.1rem;">🔗 Vínculos e Origem</h4>';
                if (!empty($cliente['lead_id'])) {
                    echo '<div class="field-row"><div class="field-label">Lead ID:</div><div class="field-value success-value">✓ ' . $cliente['lead_id'] . '</div></div>';
                } else {
                    echo '<div class="field-row"><div class="field-label">Lead ID:</div><div class="field-value null-value">✗ NULL (não vinculado!)</div></div>';
                }
                echo '<div class="field-row"><div class="field-label">Origem:</div><div class="field-value">' . ($cliente['origem'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">ID Empresa Master:</div><div class="field-value">' . ($cliente['id_empresa_master'] ? $cliente['id_empresa_master'] : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Empresa Master:</div><div class="field-value">' . ($cliente['empresa_master'] ? htmlspecialchars($cliente['empresa_master']) : '<span class="null-value">Nenhuma</span>') . '</div></div>';
                
                // SEÇÕES 7 e 8: SEGURANÇA E AUDITORIA (colapsável)
                echo '<details style="margin-top: 20px; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">';
                echo '<summary style="cursor: pointer; color: #6c757d; font-weight: 600; padding: 8px; background: #f8f9fa; border-radius: 5px; user-select: none;">🔒 Segurança, Acesso e Auditoria (clique para expandir)</summary>';
                echo '<div style="padding: 15px 10px 5px 10px;">';
                
                // SEÇÃO 7: SEGURANÇA E ACESSO
                echo '<h5 style="color: #6c757d; font-size: 0.95rem; margin-bottom: 10px;">🔐 Segurança e Acesso</h5>';
                echo '<div class="field-row"><div class="field-label">Status:</div><div class="field-value"><span class="badge bg-secondary">' . $cliente['status'] . '</span></div></div>';
                echo '<div class="field-row"><div class="field-label">Token de Acesso:</div><div class="field-value">' . ($cliente['token_acesso'] ? '<span class="success-value">✓ Gerado</span> <small>(' . substr($cliente['token_acesso'], 0, 16) . '...)</small>' : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Token Expira em:</div><div class="field-value">' . ($cliente['token_expiracao'] ? date('d/m/Y H:i', strtotime($cliente['token_expiracao'])) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Código Verificação:</div><div class="field-value">' . ($cliente['codigo_verificacao'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Código Expira em:</div><div class="field-value">' . ($cliente['codigo_expira_em'] ? date('d/m/Y H:i', strtotime($cliente['codigo_expira_em'])) : '<span class="null-value">NULL</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Tentativas Login Falhas:</div><div class="field-value">' . ($cliente['failed_login_attempts'] ?: '0') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Bloqueado até:</div><div class="field-value">' . ($cliente['lockout_until'] ? '<span style="color: red; font-weight: 600;">' . date('d/m/Y H:i', strtotime($cliente['lockout_until'])) . '</span>' : '<span class="success-value">Não bloqueado</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Último Login:</div><div class="field-value">' . ($cliente['ultimo_login'] ? date('d/m/Y H:i', strtotime($cliente['ultimo_login'])) : '<span class="null-value">Nunca</span>') . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Último IP:</div><div class="field-value">' . ($cliente['ultimo_ip'] ?: '<span class="null-value">NULL</span>') . '</div></div>';
                
                // SEÇÃO 8: AUDITORIA
                echo '<h5 style="color: #6c757d; font-size: 0.95rem; margin: 20px 0 10px 0;">📅 Auditoria</h5>';
                echo '<div class="field-row"><div class="field-label">Criado em:</div><div class="field-value">' . date('d/m/Y H:i:s', strtotime($cliente['created_at'])) . '</div></div>';
                echo '<div class="field-row"><div class="field-label">Atualizado em:</div><div class="field-value">' . ($cliente['updated_at'] ? date('d/m/Y H:i:s', strtotime($cliente['updated_at'])) : '<span class="null-value">NULL</span>') . '</div></div>';
                
                echo '</div></details>';
            } else {
                echo '<div class="alert alert-warning">Cliente #' . $cliente_id . ' não encontrado!</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Erro ao buscar cliente: ' . $e->getMessage() . '</div>';
        }
        
        echo '</div>';
        }

        // 4. Buscar empresas vinculadas ao Lead (através dos clientes)
        echo '<div class="debug-section">';
        echo '<h2>🏢 Empresas vinculadas ao Lead #' . $lead_id . '</h2>';
        
        try {
            $stmt_empresas = $pdo->prepare("
                SELECT ke.*, e.nome as empresa_master, kc.nome_completo as cliente_nome
                FROM kyc_empresas ke
                LEFT JOIN empresas e ON ke.id_empresa_master = e.id
                LEFT JOIN kyc_clientes kc ON ke.cliente_id = kc.id
                WHERE kc.lead_id = ?
                ORDER BY ke.data_submissao DESC
            ");
            $stmt_empresas->execute([$lead_id]);
            $empresas = $stmt_empresas->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($empresas) > 0) {
                foreach ($empresas as $empresa) {
                    echo '<div style="border-left: 4px solid #4f46e5; padding-left: 15px; margin-bottom: 15px;">';
                    echo '<div class="field-row"><div class="field-label">ID:</div><div class="field-value">' . $empresa['id'] . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">CNPJ:</div><div class="field-value">' . $empresa['cnpj'] . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Razão Social:</div><div class="field-value">' . ($empresa['razao_social'] ?: '<span class="null-value">Não informado</span>') . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Cliente:</div><div class="field-value">' . htmlspecialchars($empresa['cliente_nome']) . ' (ID: ' . $empresa['cliente_id'] . ')</div></div>';
                    echo '<div class="field-row"><div class="field-label">Empresa Master:</div><div class="field-value">' . ($empresa['empresa_master'] ?: '<span class="null-value">Nenhuma</span>') . '</div></div>';
                    echo '<div class="field-row"><div class="field-label">Status:</div><div class="field-value"><span class="badge bg-primary">' . $empresa['status'] . '</span></div></div>';
                    echo '<div class="field-row"><div class="field-label">Criado em:</div><div class="field-value">' . ($empresa['data_submissao'] ? date('d/m/Y H:i', strtotime($empresa['data_submissao'])) : '<span class="null-value">Não informado</span>') . '</div></div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-info">Nenhuma empresa cadastrada ainda para este lead.</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Erro ao buscar empresas: ' . $e->getMessage() . '</div>';
        }
        
        echo '</div>';

        // 5. Verificar se existem outros clientes com este lead_id
        echo '<div class="debug-section">';
        echo '<h2>🔗 Resumo de Conversão</h2>';
        
        try {
            $stmt_outros = $pdo->prepare("
                SELECT id, nome_completo, email, created_at
                FROM kyc_clientes
                WHERE lead_id = ?
            ");
            $stmt_outros->execute([$lead_id]);
            $outros_clientes = $stmt_outros->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($outros_clientes) > 0) {
                echo '<div class="alert alert-success">';
                echo '✓ <strong>Lead convertido com sucesso!</strong><br>';
                echo 'Total de clientes vinculados: <strong>' . count($outros_clientes) . '</strong>';
                echo '</div>';
                
                foreach ($outros_clientes as $outro) {
                    echo '<div class="field-row">';
                    echo '<div class="field-label">Cliente ID ' . $outro['id'] . ':</div>';
                    echo '<div class="field-value">' . htmlspecialchars($outro['nome_completo']) . ' (' . htmlspecialchars($outro['email']) . ') - ' . date('d/m/Y H:i', strtotime($outro['created_at'])) . '</div>';
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-warning">⚠️ Lead ainda não foi convertido em cliente</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
        
        echo '</div>';

        // 6. Análise e recomendações
        echo '<div class="debug-section">';
        echo '<h2>💡 Análise & Recomendações</h2>';
        
        if ($lead && isset($outros_clientes) && count($outros_clientes) > 0) {
            $primeiro_cliente = $outros_clientes[0];
            
            // Comparar emails
            if (strtolower($lead['email']) === strtolower($primeiro_cliente['email'])) {
                echo '<div class="alert alert-success">✓ O email do Lead #' . $lead_id . ' e Cliente #' . $primeiro_cliente['id'] . ' são iguais!</div>';
                echo '<div class="alert alert-info">';
                echo '<strong>✓ Vínculo correto!</strong><br>';
                echo 'Lead → Cliente → Empresa estão corretamente vinculados.';
                echo '</div>';
            } else {
                echo '<div class="alert alert-info">Os emails são diferentes:<br>';
                echo '• Lead #' . $lead_id . ': ' . htmlspecialchars($lead['email']) . '<br>';
                echo '• Cliente #' . $primeiro_cliente['id'] . ': ' . htmlspecialchars($primeiro_cliente['email']) . '</div>';
            }
        } elseif ($lead) {
            echo '<div class="alert alert-warning">';
            echo '<strong>⚠️ Lead não convertido ainda</strong><br>';
            echo 'Este lead ainda não foi convertido em cliente.<br><br>';
            echo '<strong>Próximos passos:</strong><br>';
            echo '1. Enviar link de cadastro para o lead<br>';
            echo '2. Aguardar o lead se registrar como cliente<br>';
            echo '3. O sistema vinculará automaticamente via email + empresa';
            echo '</div>';
        }
        
        echo '</div>';
        
        ?>
        
        <?php else: ?>
            <div class="alert alert-info">
                👆 Use o formulário acima para buscar um lead por ID, nome, ou buscar o lead vinculado a um cliente específico.
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-primary">← Voltar ao Dashboard</a>
            <a href="leads.php" class="btn btn-secondary">Ver Leads</a>
            <a href="clientes.php" class="btn btn-secondary">Ver Clientes</a>
            <?php if ($lead_id): ?>
                <a href="debug_safe_delete_lead.php?id=<?= $lead_id ?>" class="btn btn-danger">🗑️ Deletar Lead #<?= $lead_id ?></a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
