<?php
/**
 * AJAX endpoint para verificação facial
 * Compara a nova selfie com a selfie original do cliente
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
    
    if (!isset($_FILES['verification_selfie'])) {
        throw new Exception('Selfie de verificação não enviada');
    }
    
    $cliente_id = (int) $_POST['cliente_id'];
    $verification_file = $_FILES['verification_selfie'];
    
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
        throw new Exception('Cliente não possui selfie original cadastrada');
    }
    
    // Valida o arquivo enviado
    if ($verification_file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erro no upload da selfie: ' . $verification_file['error']);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $verification_file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG ou PNG.');
    }
    
    if ($verification_file['size'] > 5 * 1024 * 1024) { // 5MB
        throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB');
    }
    
    // Salva arquivo temporário
    $temp_dir = __DIR__ . '/uploads/temp_verifications';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_filename = 'verify_' . $cliente_id . '_' . uniqid() . '.jpg';
    $temp_path = $temp_dir . '/' . $temp_filename;
    
    if (!move_uploaded_file($verification_file['tmp_name'], $temp_path)) {
        throw new Exception('Erro ao salvar arquivo temporário');
    }
    
    // Inicializa validador facial
    $faceValidator = new \Verify\FaceValidator();
    
    // Primeiro, detecta se há uma face na nova selfie
    $detection = $faceValidator->detectFace($temp_path);
    
    if (!$detection['success']) {
        // Remove arquivo temporário
        @unlink($temp_path);
        throw new Exception($detection['error']);
    }
    
    if ($detection['face_count'] > 1) {
        @unlink($temp_path);
        throw new Exception('Múltiplas faces detectadas. Por favor, tire uma foto com apenas você.');
    }
    
    // Analisa qualidade da selfie
    $quality = $detection['quality'];
    if (!$quality['is_good_quality']) {
        $warnings = implode(', ', $quality['warnings']);
        @unlink($temp_path);
        throw new Exception("Qualidade da selfie insuficiente. Problemas: {$warnings}");
    }
    
    // Compara as faces
    $comparison = $faceValidator->compareFaces($data['selfie_path'], $temp_path);
    
    // Remove arquivo temporário após comparação
    @unlink($temp_path);
    
    if (!$comparison['success']) {
        throw new Exception($comparison['error']);
    }
    
    if (!$comparison['match']) {
        // Registra tentativa de verificação falha
        $stmt = $pdo->prepare("
            INSERT INTO facial_verifications 
            (cliente_id, usuario_id, similarity_score, verification_result, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, 'failed', ?, ?, NOW())
        ");
        $stmt->execute([
            $cliente_id,
            $_SESSION['user_id'],
            $comparison['similarity'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        throw new Exception(
            sprintf(
                'Verificação facial falhou. Similaridade: %.2f%% (mínimo requerido: 90%%)',
                $comparison['similarity']
            )
        );
    }
    
    // Registra verificação bem-sucedida
    $stmt = $pdo->prepare("
        INSERT INTO facial_verifications 
        (cliente_id, usuario_id, similarity_score, verification_result, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, 'success', ?, ?, NOW())
    ");
    $stmt->execute([
        $cliente_id,
        $_SESSION['user_id'],
        $comparison['similarity'],
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Gera token de verificação válido por 5 minutos
    $verification_token = bin2hex(random_bytes(32));
    $_SESSION['face_verification_token'] = $verification_token;
    $_SESSION['face_verification_cliente_id'] = $cliente_id;
    $_SESSION['face_verification_expires'] = time() + (5 * 60); // 5 minutos
    
    echo json_encode([
        'success' => true,
        'message' => sprintf(
            'Verificação facial bem-sucedida! Similaridade: %.2f%% | Confiança: %.2f%%',
            $comparison['similarity'],
            $comparison['confidence']
        ),
        'similarity' => $comparison['similarity'],
        'confidence' => $comparison['confidence'],
        'verification_token' => $verification_token
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
