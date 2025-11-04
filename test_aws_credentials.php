<?php
/**
 * Script de teste rápido das credenciais AWS
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Teste de Credenciais AWS</h2>";

// 1. Verifica se .env existe
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ Arquivo .env encontrado<br>";
    
    // Carrega .env
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
    echo "✅ Arquivo .env carregado<br>";
} else {
    echo "❌ Arquivo .env NÃO encontrado<br>";
}

// 2. Verifica variáveis
$key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
$secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');
$region = $_ENV['AWS_REGION'] ?? getenv('AWS_REGION') ?: 'us-east-1';

echo "<br><strong>Credenciais carregadas:</strong><br>";
echo "AWS_ACCESS_KEY_ID: " . (empty($key) ? "❌ VAZIO" : "✅ " . substr($key, 0, 8) . "...") . "<br>";
echo "AWS_SECRET_ACCESS_KEY: " . (empty($secret) ? "❌ VAZIO" : "✅ " . substr($secret, 0, 8) . "...") . "<br>";
echo "AWS_REGION: " . (empty($region) ? "❌ VAZIO" : "✅ " . $region) . "<br>";

// 3. Verifica autoload do Composer
echo "<br><strong>Composer:</strong><br>";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ vendor/autoload.php encontrado<br>";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ Autoload carregado<br>";
    
    // Verifica se AWS SDK está instalado
    if (class_exists('Aws\Textract\TextractClient')) {
        echo "✅ AWS Textract Client disponível<br>";
    } else {
        echo "❌ AWS Textract Client NÃO encontrado (rode: composer install)<br>";
    }
} else {
    echo "❌ vendor/autoload.php NÃO encontrado<br>";
    echo "⚠️ Você precisa rodar 'composer install' no servidor<br>";
}

// 4. Tenta criar cliente AWS
if (!empty($key) && !empty($secret)) {
    echo "<br><strong>Teste de conexão AWS:</strong><br>";
    try {
        require_once __DIR__ . '/src/DocumentValidatorAWS.php';
        $validator = new Verify\DocumentValidatorAWS();
        echo "✅ DocumentValidatorAWS criado com sucesso!<br>";
        echo "✅ Credenciais AWS válidas!<br>";
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "<br>";
    }
}

echo "<br><hr>";
echo "<strong>Próximo passo:</strong> Se tudo OK acima, teste o upload de documento em test_document_upload.php";
