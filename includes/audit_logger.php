<?php
/**
 * AUDIT LOGGER - Sistema de Log de Auditoria
 * 
 * Função helper para registrar TODAS as ações no sistema
 * Usa a tabela kyc_logs (já existente) de forma aprimorada
 * 
 * USO:
 * require_once 'includes/audit_logger.php';
 * logAuditoria($pdo, $cliente_id, 'UPDATE', 'Nome alterado', $dados_antigos, $dados_novos);
 */

/**
 * Registra uma ação de auditoria no sistema
 * 
 * @param PDO $pdo Conexão com banco de dados
 * @param int $entidade_id ID da entidade afetada (cliente_id, empresa_id, etc)
 * @param string $acao Tipo de ação: CREATE, UPDATE, DELETE, VERIFY, etc
 * @param string $descricao Descrição legível da ação
 * @param array|null $dados_antigos Valores antes da alteração
 * @param array|null $dados_novos Valores após a alteração
 * @param string $entidade_tipo Tipo da entidade (default: 'cliente')
 * @return bool Sucesso ou falha
 */
function logAuditoria($pdo, $entidade_id, $acao, $descricao, $dados_antigos = null, $dados_novos = null, $entidade_tipo = 'cliente') {
    try {
        // Captura informações do usuário logado
        // IMPORTANTE: Suporta tanto 'user_id' quanto 'usuario_id' por compatibilidade
        $usuario_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
        $usuario_nome = $_SESSION['nome'] ?? 'Sistema';
        $usuario_tipo = $_SESSION['user_role'] ?? $_SESSION['tipo_usuario'] ?? 'sistema';
        
        // Busca o empresa_id com base no cliente_id
        // IMPORTANTE: empresa_id aqui é kyc_empresas.id, não id_empresa_master!
        $empresa_id = null;
        if ($entidade_tipo === 'cliente' && $entidade_id) {
            // Busca o ID da empresa KYC associada ao cliente
            $stmt_empresa = $pdo->prepare("SELECT id FROM kyc_empresas WHERE cliente_id = ?");
            $stmt_empresa->execute([$entidade_id]);
            $empresa_id = $stmt_empresa->fetchColumn();
        } else {
            $empresa_id = $entidade_id; // Se já for empresa_id
        }
        
        // Captura informações da requisição
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Identifica campos que mudaram
        $campos_alterados = [];
        if ($dados_antigos && $dados_novos) {
            foreach ($dados_novos as $campo => $valor_novo) {
                $valor_antigo = $dados_antigos[$campo] ?? null;
                if ($valor_novo !== $valor_antigo) {
                    $campos_alterados[] = $campo;
                }
            }
        }
        
        // Monta detalhes completos em JSON
        $detalhes_json = json_encode([
            'acao' => $acao,
            'descricao' => $descricao,
            'entidade_tipo' => $entidade_tipo,
            'entidade_id' => $entidade_id,
            'campos_alterados' => $campos_alterados,
            'valores_antigos' => $dados_antigos,
            'valores_novos' => $dados_novos,
            'usuario' => [
                'id' => $usuario_id,
                'nome' => $usuario_nome,
                'tipo' => $usuario_tipo
            ],
            'request' => [
                'ip' => $ip_address,
                'user_agent' => $user_agent,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        // Insere no kyc_logs (tabela existente)
        $stmt = $pdo->prepare("
            INSERT INTO kyc_logs (
                empresa_id,
                usuario_id,
                acao,
                detalhes,
                data_ocorrencia
            ) VALUES (
                :empresa_id,
                :usuario_id,
                :acao,
                :detalhes,
                NOW()
            )
        ");
        
        $stmt->execute([
            ':empresa_id' => $empresa_id,
            ':usuario_id' => $usuario_id,
            ':acao' => $acao . ': ' . $descricao,
            ':detalhes' => $detalhes_json
        ]);
        
        return true;
        
    } catch (Exception $e) {
        // Log de erro sem interromper a aplicação
        error_log("ERRO ao registrar auditoria: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // TEMPORÁRIO: Mostra erro na tela para debug
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<div class='alert alert-danger'>ERRO AUDIT LOG: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        return false;
    }
}

/**
 * Registra alteração de dados do cliente
 * 
 * @param PDO $pdo
 * @param int $cliente_id
 * @param array $dados_antigos
 * @param array $dados_novos
 */
function logAlteracaoCliente($pdo, $cliente_id, $dados_antigos, $dados_novos) {
    // Identifica campos alterados
    $campos_mudados = [];
    foreach ($dados_novos as $campo => $valor_novo) {
        $valor_antigo = $dados_antigos[$campo] ?? '';
        if ($valor_novo != $valor_antigo) {
            $campos_mudados[] = $campo;
        }
    }
    
    if (empty($campos_mudados)) {
        return true; // Nada mudou, mas não é erro
    }
    
    $descricao = 'Campos alterados: ' . implode(', ', $campos_mudados);
    
    return logAuditoria(
        $pdo,
        $cliente_id,
        'UPDATE_CLIENTE',
        $descricao,
        $dados_antigos,
        $dados_novos,
        'cliente'
    );
}

/**
 * Registra criação de novo cliente
 */
function logCriacaoCliente($pdo, $cliente_id, $dados) {
    return logAuditoria(
        $pdo,
        $cliente_id,
        'CREATE_CLIENTE',
        'Novo cliente cadastrado',
        null,
        $dados,
        'cliente'
    );
}

/**
 * Registra exclusão de cliente
 */
function logExclusaoCliente($pdo, $cliente_id, $dados_cliente) {
    return logAuditoria(
        $pdo,
        $cliente_id,
        'DELETE_CLIENTE',
        'Cliente excluído',
        $dados_cliente,
        null,
        'cliente'
    );
}

/**
 * Registra verificação facial ou documental
 */
function logVerificacao($pdo, $cliente_id, $tipo_verificacao, $resultado, $detalhes = []) {
    $descricao = ucfirst($tipo_verificacao) . ' - ' . ($resultado === 'success' ? 'Aprovado' : 'Reprovado');
    
    return logAuditoria(
        $pdo,
        $cliente_id,
        'VERIFICACAO_' . strtoupper($tipo_verificacao),
        $descricao,
        null,
        $detalhes,
        'cliente'
    );
}
