<?php
require_once 'bootstrap.php';

// --- Validação de Segurança ---

// 1. Garante que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Acesso negado.');
}

// 2. Garante que o parâmetro 'path' foi fornecido
if (!isset($_GET['path'])) {
    http_response_code(400);
    die('Caminho do arquivo não especificado.');
}

$file_path_relative = $_GET['path'];

// 3. Previne ataques de "Directory Traversal"
// Constrói o caminho absoluto esperado e o caminho absoluto real
$base_dir = realpath(__DIR__ . '/uploads');
$requested_file_absolute = realpath(__DIR__ . '/' . $file_path_relative);

// Se o caminho real não começar com o caminho do diretório de uploads, é uma tentativa de acesso indevido.
if (!$requested_file_absolute || strpos($requested_file_absolute, $base_dir) !== 0) {
    http_response_code(403);
    die('Acesso negado ao arquivo solicitado.');
}

// 4. Verifica se o arquivo realmente existe
if (!file_exists($requested_file_absolute)) {
    http_response_code(404);
    die('Arquivo não encontrado.');
}

// --- Entrega do Arquivo ---

// Pega a extensão para definir o tipo de conteúdo (MIME type)
$file_extension = strtolower(pathinfo($requested_file_absolute, PATHINFO_EXTENSION));
$mime_types = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
];
$content_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Define os cabeçalhos para o navegador
header('Content-Type: ' . $content_type);
header('Content-Disposition: inline; filename="' . basename($requested_file_absolute) . '"');
header('Content-Length: ' . filesize($requested_file_absolute));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Limpa qualquer saída de buffer que possa ter ocorrido
ob_clean();
flush();

// Lê e envia o conteúdo do arquivo para o navegador
readfile($requested_file_absolute);
exit;
?>
