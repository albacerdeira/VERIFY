<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$cpf = '27227747808'; // CPF de teste
$apiKey = 'd32d86454c1c6d5a531710d04df3f5c5'; // Sua chave
$url = "https://api.portaldatransparencia.gov.br/api-de-dados/pessoa-fisica?cpf=" . $cpf;

echo "<h1>Teste de API - Portal da Transparência</h1>";
echo "<p><strong>URL:</strong> " . htmlspecialchars($url) . "</p>";
echo "<p><strong>Chave API:</strong> " . htmlspecialchars($apiKey) . "</p>";
echo "<hr>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: */*',
    'chave-api-dados: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Simple-PHP-Test/1.0');
curl_setopt($ch, CURLOPT_VERBOSE, true); // Habilita modo verboso

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

if ($curl_error) {
    echo "<h2>Erro cURL</h2>";
    echo "<pre>" . htmlspecialchars($curl_error) . "</pre>";
} else {
    echo "<h2>Informações da Requisição</h2>";
    echo "<p><strong>Código HTTP:</strong> " . $http_code . "</p>";
    
    echo "<h2>Resposta Bruta (Response Body)</h2>";
    if (empty($response)) {
        echo "<p style='color: red;'><strong>A resposta veio vazia!</strong></p>";
    } else {
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
    
    echo "<h2>Log Detalhado (Verbose)</h2>";
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";
}

curl_close($ch);
fclose($verbose);
?>
