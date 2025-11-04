<?php
/**
 * Endpoint AJAX para validação de documentos (apenas OCR - sem AWS)
 * Valida: RG, CNH, CPF, Comprovantes de Residência
 */

session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/DocumentValidatorAWS.php';

use Verify\DocumentValidatorAWS;

header('Content-Type: application/json');

// Verifica se é requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido'
    ]);
    exit;
}

// Verifica se usuário está autenticado (ajuste conforme seu sistema)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['cliente_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado'
    ]);
    exit;
}

// Verifica se arquivo foi enviado
if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'error' => 'Nenhum arquivo foi enviado ou erro no upload'
    ]);
    exit;
}

$file = $_FILES['documento'];

// Validações básicas
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
$maxSize = 10 * 1024 * 1024; // 10MB

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode([
        'success' => false,
        'error' => 'Tipo de arquivo não permitido. Use JPG, PNG ou PDF'
    ]);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode([
        'success' => false,
        'error' => 'Arquivo muito grande. Máximo 10MB'
    ]);
    exit;
}

// Move arquivo para diretório temporário
$uploadDir = __DIR__ . '/uploads/temp/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = uniqid('doc_') . '_' . basename($file['name']);
$filePath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao salvar arquivo'
    ]);
    exit;
}

// Carrega configurações do .env ANTES de tudo
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora comentários e linhas vazias
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }
        
        // Processa linha KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

try {
    $validator = new DocumentValidatorAWS();
    
    // Extrai texto do documento
    $ocrResult = $validator->extractText($filePath);
    
    if (!$ocrResult['success']) {
        // Remove arquivo
        unlink($filePath);
        
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível extrair texto do documento: ' . $ocrResult['error']
        ]);
        exit;
    }
    
    $text = $ocrResult['text'];
    $confidence = $ocrResult['confidence'];
    
    // Extrai informações específicas
    $extractedData = [];
    
    // CPF
    $cpf = $validator->extractCPF($text);
    if ($cpf) {
        $extractedData['cpf'] = [
            'value' => $cpf['formatted'],
            'raw' => $cpf['raw'],
            'valid' => $cpf['valid']
        ];
    }
    
    // CNPJ
    $cnpj = $validator->extractCNPJ($text);
    if ($cnpj) {
        $extractedData['cnpj'] = [
            'value' => $cnpj['formatted'],
            'raw' => $cnpj['raw'],
            'valid' => $cnpj['valid']
        ];
    }
    
    // RG
    $rg = $validator->extractRG($text);
    if ($rg) {
        $extractedData['rg'] = [
            'value' => $rg['formatted'],
            'raw' => $rg['raw']
        ];
    }
    
    // CNH
    $cnh = $validator->extractCNH($text);
    if ($cnh) {
        $extractedData['cnh'] = [
            'value' => $cnh['formatted'],
            'raw' => $cnh['raw']
        ];
    }
    
    // Nome
    $nome = $validator->extractName($text);
    if ($nome) {
        $extractedData['nome'] = $nome;
    }
    
    // Move arquivo para pasta permanente se solicitado
    $permanentPath = null;
    if (isset($_POST['save_file']) && $_POST['save_file'] === 'true') {
        $permanentDir = __DIR__ . '/uploads/documentos/';
        if (!is_dir($permanentDir)) {
            mkdir($permanentDir, 0755, true);
        }
        $permanentPath = $permanentDir . $fileName;
        rename($filePath, $permanentPath);
    } else {
        // Remove arquivo temporário
        unlink($filePath);
    }
    
    // Prepara resposta
    $response = [
        'success' => true,
        'confidence' => $confidence,
        'extracted_data' => $extractedData,
        'text_preview' => substr($text, 0, 500),
        'warnings' => []
    ];
    
    // Adiciona avisos
    if ($confidence < 70) {
        $response['warnings'][] = 'Confiança abaixo de 70%. Revise manualmente os dados extraídos.';
    }
    
    if (empty($extractedData)) {
        $response['warnings'][] = 'Nenhum dado específico foi identificado no documento.';
    }
    
    if ($permanentPath) {
        $response['file_path'] = str_replace(__DIR__, '', $permanentPath);
    }
    
    // Log da validação (opcional - adicione ao banco se necessário)
    if (isset($_SESSION['cliente_id'])) {
        // TODO: Salvar log no banco de dados
        // INSERT INTO document_validations (cliente_id, confidence, extracted_data, created_at)
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Remove arquivo em caso de erro
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao processar documento: ' . $e->getMessage()
    ]);
}
