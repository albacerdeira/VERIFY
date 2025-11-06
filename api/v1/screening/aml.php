<?php
/**
 * API Endpoint: POST /api/v1/screening/aml
 * 
 * Triagem AML completa usando bases CEIS, CNEP e PEP do governo brasileiro
 * 
 * Request Body (JSON):
 * {
 *   "cpf": "123.456.789-01",           // Obrigatório para PF
 *   "nome": "João da Silva",           // Obrigatório
 *   "cnpj": "12.345.678/0001-90",     // Obrigatório para PJ
 *   "razao_social": "Empresa LTDA",    // Opcional (melhora matching PJ)
 *   "tipo": "pf" ou "pj"               // Opcional (auto-detecta se omitido)
 * }
 * 
 * Response (JSON):
 * {
 *   "success": true,
 *   "data": {
 *     "risk_score": 40,                // 0-100
 *     "risk_level": "MEDIUM",          // LOW, MEDIUM, HIGH, CRITICAL
 *     "flags": [
 *       {
 *         "type": "PEP",
 *         "severity": "HIGH",
 *         "details": {...}
 *       }
 *     ],
 *     "recommendation": "Aprovado com restrições",
 *     "screened_at": "2025-11-05 14:30:22"
 *   }
 * }
 */

// Configuração de headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Carrega dependências
require_once __DIR__ . '/../../../config.php';

/**
 * Envia resposta JSON e encerra
 */
function sendResponse($success, $data = null, $error = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($error !== null) {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // 1. VALIDA MÉTODO HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, null, 'Método não permitido. Use POST.', 405);
    }
    
    // 2. AUTENTICAÇÃO VIA TOKEN
    $token = null;
    
    // Aceita token em 3 formatos:
    // 1. Query string: ?token=xxx
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    // 2. Header Bearer: Authorization: Bearer xxx
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $matches = [];
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $token = $matches[1];
        }
    }
    // 3. Header custom: X-API-Token: xxx
    else if (isset($_SERVER['HTTP_X_API_TOKEN'])) {
        $token = $_SERVER['HTTP_X_API_TOKEN'];
    }
    
    if (!$token) {
        sendResponse(false, null, 'Token de autenticação não fornecido', 401);
    }
    
    // Valida token no banco
    $stmt = $pdo->prepare("
        SELECT id, api_rate_limit, api_token_ativo 
        FROM configuracoes_whitelabel 
        WHERE api_token = ? AND api_token_ativo = 1
    ");
    $stmt->execute([$token]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresa) {
        sendResponse(false, null, 'Token inválido ou inativo', 401);
    }
    
    $empresa_id = $empresa['id'];
    $rate_limit = $empresa['api_rate_limit'] ?? 100;
    
    // 3. RATE LIMITING
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM aml_screenings 
        WHERE empresa_id = ? 
        AND screened_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$empresa_id]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usage['count'] >= $rate_limit) {
        sendResponse(false, null, "Rate limit excedido. Máximo: {$rate_limit} requisições/hora", 429);
    }
    
    // 4. PARSE DO BODY JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, null, 'JSON inválido: ' . json_last_error_msg(), 400);
    }
    
    // 5. VALIDAÇÃO DE CAMPOS
    $nome = trim($data['nome'] ?? '');
    $cpf = trim($data['cpf'] ?? '');
    $cnpj = trim($data['cnpj'] ?? '');
    $razao_social = trim($data['razao_social'] ?? $nome);
    $tipo = strtolower(trim($data['tipo'] ?? ''));
    
    // Auto-detecta tipo
    if (empty($tipo)) {
        if (!empty($cnpj)) {
            $tipo = 'pj';
        } else if (!empty($cpf)) {
            $tipo = 'pf';
        }
    }
    
    if (empty($nome)) {
        sendResponse(false, null, 'Campo obrigatório: nome', 400);
    }
    
    if ($tipo === 'pf' && empty($cpf)) {
        sendResponse(false, null, 'Campo obrigatório para PF: cpf', 400);
    }
    
    if ($tipo === 'pj' && empty($cnpj)) {
        sendResponse(false, null, 'Campo obrigatório para PJ: cnpj', 400);
    }
    
    // 6. TRIAGEM AML
    $risk_score = 0;
    $flags = [];
    
    // ====================
    // PESSOA JURÍDICA (PJ)
    // ====================
    if ($tipo === 'pj') {
        $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj_limpo) !== 14) {
            sendResponse(false, null, 'CNPJ inválido. Deve conter 14 dígitos.', 400);
        }
        
        $cnpj_raiz = substr($cnpj_limpo, 0, 5);
        $razao_upper = strtoupper($razao_social);
        
        // CEIS - Pessoa Jurídica
        $stmt_ceis = $pdo->prepare("
            SELECT * FROM ceis 
            WHERE cpf_cnpj_sancionado COLLATE utf8mb4_general_ci LIKE :cnpj_raiz
        ");
        $stmt_ceis->execute([':cnpj_raiz' => $cnpj_raiz . '%']);
        $sancoes_ceis = $stmt_ceis->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sancoes_ceis as $sancao) {
            $similaridade_nome = 0;
            $similaridade_razao = 0;
            
            if (!empty($sancao['nome_sancionado'])) {
                similar_text($razao_upper, strtoupper($sancao['nome_sancionado']), $similaridade_nome);
            }
            
            if (!empty($sancao['razao_social'])) {
                similar_text($razao_upper, strtoupper($sancao['razao_social']), $similaridade_razao);
            }
            
            $similarity = max($similaridade_nome, $similaridade_razao);
            
            if ($similarity >= 85) {
                $risk_score += 40;
                $flags[] = [
                    'type' => 'CEIS',
                    'severity' => 'HIGH',
                    'similarity' => round($similarity, 2),
                    'details' => [
                        'nome_sancionado' => $sancao['nome_sancionado'] ?? $sancao['razao_social'] ?? 'N/A',
                        'orgao_sancionador' => $sancao['orgao_sancionador'] ?? 'N/A',
                        'data_inicio' => $sancao['data_inicio_sancao'] ?? 'N/A',
                        'tipo_sancao' => $sancao['tipo_sancao'] ?? 'N/A'
                    ]
                ];
            }
        }
        
        // CNEP - Pessoa Jurídica
        $stmt_cnep = $pdo->prepare("
            SELECT * FROM cnep 
            WHERE cpf_cnpj_sancionado COLLATE utf8mb4_general_ci LIKE :cnpj_raiz
        ");
        $stmt_cnep->execute([':cnpj_raiz' => $cnpj_raiz . '%']);
        $sancoes_cnep = $stmt_cnep->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sancoes_cnep as $sancao) {
            $similaridade_nome = 0;
            $similaridade_razao = 0;
            
            if (!empty($sancao['nome_sancionado'])) {
                similar_text($razao_upper, strtoupper($sancao['nome_sancionado']), $similaridade_nome);
            }
            
            if (!empty($sancao['razao_social'])) {
                similar_text($razao_upper, strtoupper($sancao['razao_social']), $similaridade_razao);
            }
            
            $similarity = max($similaridade_nome, $similaridade_razao);
            
            if ($similarity >= 85) {
                $risk_score += 40;
                $flags[] = [
                    'type' => 'CNEP',
                    'severity' => 'HIGH',
                    'similarity' => round($similarity, 2),
                    'details' => [
                        'nome_sancionado' => $sancao['nome_sancionado'] ?? $sancao['razao_social'] ?? 'N/A',
                        'orgao_sancionador' => $sancao['orgao_sancionador'] ?? 'N/A',
                        'data_inicio' => $sancao['data_inicio_sancao'] ?? 'N/A',
                        'tipo_sancao' => $sancao['tipo_sancao'] ?? 'N/A'
                    ]
                ];
            }
        }
    }
    
    // ====================
    // PESSOA FÍSICA (PF)
    // ====================
    else if ($tipo === 'pf') {
        $cpf_limpo = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf_limpo) !== 11) {
            sendResponse(false, null, 'CPF inválido. Deve conter 11 dígitos.', 400);
        }
        
        // Formata CPF: XXX.XXX.XXX-XX
        $cpf_formatado = substr($cpf_limpo, 0, 3) . '.' . 
                         substr($cpf_limpo, 3, 3) . '.' . 
                         substr($cpf_limpo, 6, 3) . '-' . 
                         substr($cpf_limpo, 9, 2);
        
        // CEIS - Pessoa Física
        $stmt_ceis = $pdo->prepare("SELECT * FROM ceis WHERE cpf_cnpj_sancionado = ?");
        $stmt_ceis->execute([$cpf_limpo]);
        $sancoes_ceis = $stmt_ceis->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($sancoes_ceis)) {
            $risk_score += 50;
            
            foreach ($sancoes_ceis as $sancao) {
                $flags[] = [
                    'type' => 'CEIS',
                    'severity' => 'CRITICAL',
                    'details' => [
                        'nome_sancionado' => $sancao['nome_sancionado'] ?? 'N/A',
                        'orgao_sancionador' => $sancao['orgao_sancionador'] ?? 'N/A',
                        'data_inicio' => $sancao['data_inicio_sancao'] ?? 'N/A',
                        'tipo_sancao' => $sancao['tipo_sancao'] ?? 'N/A'
                    ]
                ];
            }
        }
        
        // CNEP - Pessoa Física
        $stmt_cnep = $pdo->prepare("SELECT * FROM cnep WHERE cpf_cnpj_sancionado = ?");
        $stmt_cnep->execute([$cpf_limpo]);
        $sancoes_cnep = $stmt_cnep->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($sancoes_cnep)) {
            $risk_score += 50;
            
            foreach ($sancoes_cnep as $sancao) {
                $flags[] = [
                    'type' => 'CNEP',
                    'severity' => 'CRITICAL',
                    'details' => [
                        'nome_sancionado' => $sancao['nome_sancionado'] ?? 'N/A',
                        'orgao_sancionador' => $sancao['orgao_sancionador'] ?? 'N/A',
                        'data_inicio' => $sancao['data_inicio_sancao'] ?? 'N/A',
                        'tipo_sancao' => $sancao['tipo_sancao'] ?? 'N/A'
                    ]
                ];
            }
        }
        
        // PEP - Pessoa Política Exposta
        // Usa padrão LIKE: ***.XXX.XXX-**
        $middle_part = substr($cpf_formatado, 4, 7); // XXX.XXX
        
        $stmt_pep = $pdo->prepare("
            SELECT * FROM peps 
            WHERE cpf COLLATE utf8mb4_general_ci LIKE ?
        ");
        $stmt_pep->execute(['***.' . $middle_part . '-**']);
        $peps = $stmt_pep->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($peps)) {
            $risk_score += 40;
            
            foreach ($peps as $pep) {
                $flags[] = [
                    'type' => 'PEP',
                    'severity' => 'HIGH',
                    'details' => [
                        'nome_pep' => $pep['nome_pep'] ?? 'N/A',
                        'cpf' => $pep['cpf'] ?? 'N/A',
                        'sigla_funcao' => $pep['sigla_funcao'] ?? 'N/A',
                        'descricao_funcao' => $pep['descricao_funcao'] ?? 'N/A',
                        'nivel_funcao' => $pep['nivel_funcao'] ?? 'N/A',
                        'orgao' => $pep['nome_orgao'] ?? 'N/A',
                        'data_inicio' => $pep['data_inicio_exercicio'] ?? 'N/A',
                        'data_fim' => $pep['data_fim_exercicio'] ?? 'N/A'
                    ]
                ];
            }
        }
    } else {
        sendResponse(false, null, 'Tipo inválido. Use "pf" ou "pj"', 400);
    }
    
    // 7. CALCULA NÍVEL DE RISCO
    $risk_level = 'LOW';
    $recommendation = 'Aprovado. Monitoramento padrão.';
    
    if ($risk_score >= 80) {
        $risk_level = 'CRITICAL';
        $recommendation = 'BLOQUEADO. Cliente em lista de sanções ou PEP de alto risco.';
    } else if ($risk_score >= 50) {
        $risk_level = 'HIGH';
        $recommendation = 'Aprovação manual necessária. Análise detalhada obrigatória.';
    } else if ($risk_score >= 30) {
        $risk_level = 'MEDIUM';
        $recommendation = 'Aprovado com restrições. Solicitar documentos adicionais.';
    }
    
    // Limita risk_score a 100
    $risk_score = min($risk_score, 100);
    
    // 8. REGISTRA NO BANCO (LOG)
    $stmt = $pdo->prepare("
        INSERT INTO aml_screenings 
        (empresa_id, cpf, cnpj, nome, tipo, risk_score, risk_level, flags, screened_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $empresa_id,
        $cpf_limpo ?? null,
        $cnpj_limpo ?? null,
        $nome,
        $tipo,
        $risk_score,
        $risk_level,
        json_encode($flags, JSON_UNESCAPED_UNICODE)
    ]);
    
    $screening_id = $pdo->lastInsertId();
    
    // 9. RESPOSTA
    sendResponse(true, [
        'screening_id' => $screening_id,
        'tipo' => strtoupper($tipo),
        'risk_score' => $risk_score,
        'risk_level' => $risk_level,
        'flags' => $flags,
        'flags_count' => count($flags),
        'recommendation' => $recommendation,
        'screened_at' => date('Y-m-d H:i:s'),
        'bases_consultadas' => [
            'ceis' => true,
            'cnep' => true,
            'pep' => ($tipo === 'pf')
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('AML API Error: ' . $e->getMessage());
    sendResponse(false, null, 'Erro ao processar triagem AML', 500);
    
} catch (Exception $e) {
    error_log('AML API Error: ' . $e->getMessage());
    sendResponse(false, null, $e->getMessage(), 400);
}
