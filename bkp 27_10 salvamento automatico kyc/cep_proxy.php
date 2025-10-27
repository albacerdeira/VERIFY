<?php
// cep_proxy.php
header('Content-Type: application/json');

$cep = isset($_GET['cep']) ? preg_replace("/[^0-9]/", "", $_GET['cep']) : '';

if (empty($cep) || strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode(['erro' => 'CEP inválido.']);
    exit;
}

$url = "https://brasilapi.com.br/api/cep/v1/" . $cep;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpcode);
echo $response;
?>