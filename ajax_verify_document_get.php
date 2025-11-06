<?php
/**
 * Versão alternativa do ajax_verify_document que aceita dados via GET
 * (workaround para Cloudflare bloqueando POST)
 */

// Configuração de erros e logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro no servidor: ' . $errstr,
        'validations' => [],
        'extracted_data' => []
    ]);
    exit;
});

set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'validations' => [],
        'extracted_data' => []
    ]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro fatal no servidor',
            'validations' => [],
            'extracted_data' => []
        ]);
    }
});

ob_start();

// Carregar dependências
require_once __DIR__ . '/config.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new Exception('Composer não instalado. Execute: composer install');
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/FaceValidator.php';
require_once __DIR__ . '/classes/DocumentValidatorAWS.php';

ob_end_clean();

// Iniciar sessão e carregar env
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    throw new Exception('Usuário não autenticado');
}

// Carregar .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    throw new Exception('Arquivo .env não encontrado');
}

$envContent = file_get_contents($envFile);
foreach (explode("\n", $envContent) as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue;
    list($key, $value) = explode('=', $line, 2);
    $_ENV[$key] = trim($value);
    putenv("$key=" . trim($value));
}

try {
    // ACEITA GET OU POST
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Recebe dados via query string
        $cliente_id = $_GET['cliente_id'] ?? null;
        $document_base64 = $_GET['document_photo'] ?? null;
        
        if (!$cliente_id) {
            throw new Exception('ID do cliente não informado');
        }
        
        if (!$document_base64) {
            throw new Exception('Foto do documento não enviada');
        }
        
        // Decodifica base64
        $document_data = base64_decode($document_base64);
        if (!$document_data) {
            throw new Exception('Erro ao decodificar imagem');
        }
        
        // Salva temporariamente
        $temp_path = sys_get_temp_dir() . '/doc_' . uniqid() . '.jpg';
        file_put_contents($temp_path, $document_data);
        
        $document_file = [
            'tmp_name' => $temp_path,
            'name' => 'document.jpg',
            'type' => 'image/jpeg',
            'size' => filesize($temp_path),
            'error' => 0
        ];
        
    } else {
        // POST tradicional
        if (!isset($_POST['cliente_id'])) {
            throw new Exception('ID do cliente não informado');
        }
        
        if (!isset($_FILES['document_photo'])) {
            throw new Exception('Foto do documento não enviada');
        }
        
        $cliente_id = $_POST['cliente_id'];
        $document_file = $_FILES['document_photo'];
    }
    
    // Resto do código igual ao ajax_verify_document.php...
    // Por enquanto só retorna sucesso para testar
    
    echo json_encode([
        'success' => true,
        'message' => 'Método funcionando: ' . $method,
        'cliente_id' => $cliente_id,
        'method_used' => $method
    ]);
    
} catch (Exception $e) {
    throw $e;
}
