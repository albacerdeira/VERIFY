<?php
/**
 * AJAX endpoint para verificação de documento com foto
 * Extrai dados do documento (RG/CNH) e valida contra banco de dados
 * Também compara a face do documento com a selfie original
 */

// Garante que não haja output antes do JSON
ob_start();

// Define manipulador de erro que sempre retorna JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $errstr,
        'debug' => [
            'file' => $errfile,
            'line' => $errline
        ]
    ]);
    exit;
});

// Define manipulador de exceções não capturadas
set_exception_handler(function($exception) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $exception->getMessage(),
        'debug' => [
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]
    ]);
    exit;
});

// Define manipulador de erro fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal: ' . $error['message'],
            'debug' => [
                'file' => $error['file'],
                'line' => $error['line']
            ]
        ]);
    }
});

session_start();

// Carrega autoloader do Composer (CRÍTICO para AWS SDK)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Composer autoloader não encontrado. Execute: composer install'
    ]);
    exit;
}

require_once 'config.php';
require_once 'src/FaceValidator.php';
require_once 'src/DocumentValidatorAWS.php';

// Limpa qualquer output indesejado antes do JSON
ob_end_clean();

header('Content-Type: application/json');

// Verifica autenticação
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Carrega variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

try {
    // Valida requisição
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    if (!isset($_POST['cliente_id'])) {
        throw new Exception('ID do cliente não informado');
    }
    
    if (!isset($_FILES['document_photo'])) {
        throw new Exception('Foto do documento não enviada');
    }
    
    $cliente_id = (int) $_POST['cliente_id'];
    $document_file = $_FILES['document_photo'];
    
    // Valida permissões
    $stmt = $pdo->prepare("
        SELECT 
            kc.*,
            u.empresa_id as user_empresa_id,
            u.role as tipo_usuario
        FROM kyc_clientes kc
        CROSS JOIN usuarios u
        WHERE kc.id = ? AND u.id = ?
    ");
    $stmt->execute([$cliente_id, $_SESSION['user_id']]);
    $data = $stmt->fetch();
    
    if (!$data) {
        throw new Exception('Cliente não encontrado');
    }
    
    // Verifica permissão de acesso
    $is_superadmin = ($data['tipo_usuario'] === 'superadmin');
    $is_admin = ($data['tipo_usuario'] === 'admin');
    $is_analista = ($data['tipo_usuario'] === 'analista');
    
    if (!$is_superadmin && !$is_admin && !$is_analista) {
        throw new Exception('Sem permissão para acessar este cliente');
    }
    
    // Admins e Analistas só podem acessar clientes da sua empresa
    if (($is_admin || $is_analista) && $data['id_empresa_master'] != $data['user_empresa_id']) {
        throw new Exception('Você não tem permissão para este cliente');
    }
    
    // Verifica se o cliente tem selfie original
    if (empty($data['selfie_path']) || !file_exists($data['selfie_path'])) {
        throw new Exception('Cliente não possui selfie original cadastrada para comparação');
    }
    
    // Valida o arquivo enviado
    if ($document_file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload do documento: ' . $document_file['error']);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $document_file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG ou PNG.');
    }
    
    if ($document_file['size'] > 10 * 1024 * 1024) { // 10MB
        throw new Exception('Arquivo muito grande. Tamanho máximo: 10MB');
    }
    
    // Salva arquivo temporário
    $temp_dir = __DIR__ . '/uploads/temp_documents';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_filename = 'doc_' . $cliente_id . '_' . uniqid() . '.jpg';
    $temp_path = $temp_dir . '/' . $temp_filename;
    
    if (!move_uploaded_file($document_file['tmp_name'], $temp_path)) {
        throw new Exception('Erro ao salvar arquivo temporário');
    }
    
    // ============================================
    // ETAPA 1: EXTRAÇÃO DE DADOS (OCR)
    // ============================================
    
    $documentValidator = new \Verify\DocumentValidatorAWS();
    $ocr_result = $documentValidator->extractText($temp_path);
    
    if (!$ocr_result['success']) {
        @unlink($temp_path);
        throw new Exception('Erro ao processar OCR do documento: ' . $ocr_result['error']);
    }
    
    $extracted_text = $ocr_result['text'];
    $ocr_confidence = $ocr_result['confidence'];
    
    // Extrai dados estruturados
    $extracted_data = [
        'nome' => $documentValidator->extractName($extracted_text),
        'cpf' => $documentValidator->extractCPF($extracted_text),
        'rg' => $documentValidator->extractRG($extracted_text),
        'cnh' => $documentValidator->extractCNH($extracted_text),
    ];
    
    // Extrai filiação (nome do pai e mãe)
    $filiacao = extractFiliacao($extracted_text);
    $extracted_data['nome_pai'] = $filiacao['pai'];
    $extracted_data['nome_mae'] = $filiacao['mae'];
    
    // Extrai data de nascimento
    $extracted_data['data_nascimento'] = extractDataNascimento($extracted_text);
    
    // ============================================
    // ETAPA 2: VALIDAÇÃO DOS DADOS
    // ============================================
    
    $validations = [];
    $validation_score = 0;
    $max_score = 0;
    
    // Valida NOME
    if ($extracted_data['nome'] && !empty($data['nome_completo'])) {
        $similarity = similar_text(
            strtoupper($extracted_data['nome']), 
            strtoupper($data['nome_completo']), 
            $percent
        );
        $validations['nome'] = [
            'extracted' => $extracted_data['nome'],
            'database' => $data['nome_completo'],
            'match' => $percent >= 80,
            'similarity' => round($percent, 2)
        ];
        if ($percent >= 80) $validation_score += 3;
        $max_score += 3;
    }
    
    // Valida CPF
    if ($extracted_data['cpf'] && !empty($data['cpf'])) {
        $cpf_extracted = preg_replace('/[^0-9]/', '', $extracted_data['cpf']['raw']);
        $cpf_database = preg_replace('/[^0-9]/', '', $data['cpf']);
        
        $validations['cpf'] = [
            'extracted' => $extracted_data['cpf']['formatted'],
            'database' => $data['cpf'],
            'match' => $cpf_extracted === $cpf_database,
            'valid' => $extracted_data['cpf']['valid']
        ];
        if ($cpf_extracted === $cpf_database) $validation_score += 3;
        $max_score += 3;
    }
    
    // Valida RG (se o cliente tiver RG cadastrado)
    if ($extracted_data['rg']) {
        $rg_extracted = preg_replace('/[^0-9X]/', '', $extracted_data['rg']['raw']);
        
        // Tenta buscar RG do cliente no banco (se existir coluna)
        $rg_database = null;
        try {
            $stmt_rg = $pdo->prepare("SELECT rg FROM kyc_clientes WHERE id = ?");
            $stmt_rg->execute([$cliente_id]);
            $rg_result = $stmt_rg->fetch();
            if ($rg_result) {
                $rg_database = preg_replace('/[^0-9X]/', '', $rg_result['rg']);
            }
        } catch (PDOException $e) {
            // Coluna RG não existe ainda
        }
        
        $validations['rg'] = [
            'extracted' => $extracted_data['rg']['formatted'],
            'database' => $rg_database ?: 'Não cadastrado',
            'match' => $rg_database ? ($rg_extracted === $rg_database) : null
        ];
        
        if ($rg_database && $rg_extracted === $rg_database) {
            $validation_score += 2;
        }
        $max_score += 2;
    }
    
    // Registra filiação (informativo)
    if ($extracted_data['nome_mae']) {
        $validations['nome_mae'] = [
            'extracted' => $extracted_data['nome_mae'],
            'database' => 'Não armazenado no sistema',
            'match' => null
        ];
    }
    
    if ($extracted_data['nome_pai']) {
        $validations['nome_pai'] = [
            'extracted' => $extracted_data['nome_pai'],
            'database' => 'Não armazenado no sistema',
            'match' => null
        ];
    }
    
    // Registra data de nascimento
    if ($extracted_data['data_nascimento']) {
        $validations['data_nascimento'] = [
            'extracted' => $extracted_data['data_nascimento'],
            'database' => 'Não armazenado no sistema',
            'match' => null
        ];
    }
    
    // ============================================
    // ETAPA 3: COMPARAÇÃO FACIAL
    // ============================================
    
    $faceValidator = new \Verify\FaceValidator();
    
    // Detecta face no documento
    $face_detection = $faceValidator->detectFace($temp_path);
    
    if (!$face_detection['success']) {
        @unlink($temp_path);
        throw new Exception('Nenhuma face detectada no documento: ' . $face_detection['error']);
    }
    
    if ($face_detection['face_count'] > 1) {
        @unlink($temp_path);
        throw new Exception('Múltiplas faces detectadas no documento. Por favor, fotografe apenas o documento.');
    }
    
    // Compara face do documento com selfie original
    $face_comparison = $faceValidator->compareFaces($data['selfie_path'], $temp_path);
    
    // Remove arquivo temporário após processamento
    @unlink($temp_path);
    
    if (!$face_comparison['success']) {
        throw new Exception('Erro ao comparar faces: ' . $face_comparison['error']);
    }
    
    // Adiciona validação facial ao score
    if ($face_comparison['match']) {
        $validation_score += 4; // Face tem peso maior
    }
    $max_score += 4;
    
    // ============================================
    // ETAPA 4: ANÁLISE FINAL
    // ============================================
    
    $validation_percent = $max_score > 0 ? round(($validation_score / $max_score) * 100, 2) : 0;
    $verification_passed = $validation_percent >= 70; // 70% dos critérios devem passar
    
    // ============================================
    // ETAPA 5: REGISTRO NO BANCO DE DADOS
    // ============================================
    
    // Cria tabela de verificações de documento se não existir
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS document_verifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                usuario_id INT NOT NULL,
                ocr_confidence DECIMAL(5,2) DEFAULT 0.00,
                face_similarity DECIMAL(5,2) DEFAULT 0.00,
                validation_score INT DEFAULT 0,
                validation_max_score INT DEFAULT 0,
                validation_percent DECIMAL(5,2) DEFAULT 0.00,
                extracted_data JSON,
                validations JSON,
                verification_result ENUM('success', 'failed') NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cliente (cliente_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_result (verification_result),
                INDEX idx_created (created_at),
                FOREIGN KEY (cliente_id) REFERENCES kyc_clientes(id) ON DELETE CASCADE,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Tabela document_verifications já existe ou erro ao criar: " . $e->getMessage());
    }
    
    // Registra verificação
    $stmt = $pdo->prepare("
        INSERT INTO document_verifications 
        (cliente_id, usuario_id, ocr_confidence, face_similarity, validation_score, validation_max_score, 
         validation_percent, extracted_data, validations, verification_result, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $cliente_id,
        $_SESSION['user_id'],
        $ocr_confidence,
        $face_comparison['similarity'],
        $validation_score,
        $max_score,
        $validation_percent,
        json_encode($extracted_data, JSON_UNESCAPED_UNICODE),
        json_encode($validations, JSON_UNESCAPED_UNICODE),
        $verification_passed ? 'success' : 'failed',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Se passou, gera token de verificação
    if ($verification_passed) {
        $verification_token = bin2hex(random_bytes(32));
        $_SESSION['document_verification_token'] = $verification_token;
        $_SESSION['document_verification_cliente_id'] = $cliente_id;
        $_SESSION['document_verification_expires'] = time() + (5 * 60); // 5 minutos
        
        echo json_encode([
            'success' => true,
            'message' => 'Verificação de documento bem-sucedida!',
            'ocr_confidence' => $ocr_confidence,
            'face_similarity' => $face_comparison['similarity'],
            'validation_percent' => $validation_percent,
            'validations' => $validations,
            'verification_token' => $verification_token
        ]);
    } else {
        throw new Exception(
            sprintf(
                'Verificação falhou. Score: %d/%d (%.2f%%). Revise os dados extraídos.',
                $validation_score,
                $max_score,
                $validation_percent
            )
        );
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'validations' => $validations ?? [],
        'extracted_data' => $extracted_data ?? []
    ]);
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Extrai nome do pai e da mãe do texto
 */
function extractFiliacao($text) {
    $result = ['pai' => null, 'mae' => null];
    
    // Padrões para nome da mãe
    $patterns_mae = [
        '/(?:FILIAÇÃO|Filiação|NOME DA MÃE|Nome da Mãe|MÃE|Mãe)[:\s]+([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+)/i',
        '/(?:MÃE|Mãe)[:\s]+([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+)/i'
    ];
    
    foreach ($patterns_mae as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $nome = trim($matches[1]);
            // Remove números e caracteres especiais
            $nome = preg_replace('/[0-9\.\-\/]/', '', $nome);
            if (strlen($nome) > 5 && str_word_count($nome) >= 2) {
                $result['mae'] = trim($nome);
                break;
            }
        }
    }
    
    // Padrões para nome do pai
    $patterns_pai = [
        '/(?:NOME DO PAI|Nome do Pai|PAI|Pai)[:\s]+([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+)/i',
        '/(?:PAI|Pai)[:\s]+([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+)/i'
    ];
    
    foreach ($patterns_pai as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $nome = trim($matches[1]);
            $nome = preg_replace('/[0-9\.\-\/]/', '', $nome);
            if (strlen($nome) > 5 && str_word_count($nome) >= 2) {
                $result['pai'] = trim($nome);
                break;
            }
        }
    }
    
    return $result;
}

/**
 * Extrai data de nascimento do texto
 */
function extractDataNascimento($text) {
    $patterns = [
        '/(?:DATA DE NASCIMENTO|Data de Nascimento|NASCIMENTO|Nascimento|DN)[:\s]+(\d{2}\/\d{2}\/\d{4})/i',
        '/(\d{2}\/\d{2}\/\d{4})/',
        '/(?:DN|D\.N\.)[:\s]+(\d{2}\/\d{2}\/\d{4})/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $data = $matches[1];
            // Valida se é uma data válida
            $parts = explode('/', $data);
            if (count($parts) === 3) {
                $dia = (int)$parts[0];
                $mes = (int)$parts[1];
                $ano = (int)$parts[2];
                
                if (checkdate($mes, $dia, $ano) && $ano >= 1900 && $ano <= date('Y')) {
                    return $data;
                }
            }
        }
    }
    
    return null;
}
