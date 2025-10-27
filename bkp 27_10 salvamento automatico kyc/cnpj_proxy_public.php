<?php
// cnpj_proxy_public.php - Proxy para consulta de CNPJ em formulários públicos.
// Este arquivo NÃO exige autenticação.

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['cnpj'])) {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'Parâmetro CNPJ não fornecido.']);
    exit();
}

$cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj']);

if (strlen($cnpj) !== 14) {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'CNPJ inválido. Deve conter 14 dígitos.']);
    exit();
}

$url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'PublicKYCForm/1.0');
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'Erro ao contatar a API externa: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}

curl_close($ch);

http_response_code($http_code);
echo $response;
?>
