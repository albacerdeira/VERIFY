<?php
require_once 'bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'ERROR', 'message' => 'Acesso negado.']);
    exit();
}

$cpf = preg_replace('/[^0-9]/', '', $_GET['cpf'] ?? '');
if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'CPF inválido. Deve conter 11 dígitos.']);
    exit();
}

// --- ATENÇÃO: A CHAVE ABAIXO PRECISA SER VÁLIDA ---
define('CHAVE_API_TRANSPARENCIA', '3cee319e051b3ef3149ca67e557bb50c');

$url = "https://api.portaldatransparencia.gov.br/api-de-dados/pessoa-fisica?cpf=" . $cpf;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: */*',
    'chave-api-dados: ' . CHAVE_API_TRANSPARENCIA
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'VerifyKYC-CPF-Tool/1.0');
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'Erro de comunicação cURL: ' . $curl_error]);
    exit();
}

if ($http_code === 200 && empty($response)) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'A API retornou uma resposta vazia. Isso pode indicar um problema com a chave da API ou um bloqueio de IP no servidor de destino.']);
    exit();
}

if ($http_code !== 200) {
    $error_data = json_decode($response, true);
    $error_message = 'A API do Portal da Transparência retornou um erro inesperado.';
    if (is_array($error_data) && !empty($error_data[0]['mensagem'])) {
        $error_message = $error_data[0]['mensagem'];
    }
    http_response_code($http_code);
    echo json_encode(['status' => 'ERROR', 'message' => $error_message]);
    exit();
}

if (json_decode($response) === null) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'A API retornou uma resposta que não é um JSON válido.']);
    exit();
}

echo $response;
?>
