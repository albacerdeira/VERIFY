<?php
session_start();
require_once 'config.php';

// Função para converter array para CSV em memória
function array_to_csv_string($data, $headers) {
    $mem_stream = fopen('php://memory', 'w+');
    fputcsv($mem_stream, $headers);
    foreach ($data as $row) {
        // Converte sub-arrays (como JSON) para string para o CSV ficar legível
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $row[$key] = json_encode($value);
            }
        }
        fputcsv($mem_stream, $row);
    }
    rewind($mem_stream);
    $csv_string = stream_get_contents($mem_stream);
    fclose($mem_stream);
    return $csv_string;
}

// 1. Segurança: Verifica se o usuário é superadmin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    http_response_code(403);
    die('Acesso negado.');
}

// 2. Validação: Verifica se o ID da empresa foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('ID de empresa inválido.');
}

$empresa_id = intval($_GET['id']);

// 3. Coleta de Dados
try {
    // Dados da Empresa
    $stmt_empresa = $pdo->prepare("SELECT id, nome, email, created_at FROM empresas WHERE id = ?");
    $stmt_empresa->execute([$empresa_id]);
    $empresa = $stmt_empresa->fetchAll(PDO::FETCH_ASSOC);

    if (empty($empresa)) {
        http_response_code(404);
        die('Empresa não encontrada.');
    }

    $nome_empresa_slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($empresa[0]['nome']));

    // Dados dos Usuários da Empresa (Assumindo que a coluna 'role' existe)
    $stmt_usuarios = $pdo->prepare("SELECT id, nome, email, role, created_at FROM usuarios WHERE empresa_id = ?");
    $stmt_usuarios->execute([$empresa_id]);
    $usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);

    // Dados das Consultas (CORRIGIDO)
    $stmt_consultas = $pdo->prepare("SELECT c.id, c.usuario_id, u.nome as usuario_nome, c.cnpj, c.razao_social, c.created_at, c.dados FROM consultas c JOIN usuarios u ON c.usuario_id = u.id WHERE u.empresa_id = ? ORDER BY c.created_at DESC");
    $stmt_consultas->execute([$empresa_id]);
    $consultas = $stmt_consultas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    http_response_code(500);
    die("Erro no banco de dados: " . $e->getMessage());
}

// 4. Geração do ZIP
$zip = new ZipArchive();
$zip_filename = sys_get_temp_dir() . '/backup_' . $nome_empresa_slug . '_' . $empresa_id . '_' . date('YmdHis') . '.zip';

if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    die('Não foi possível criar o arquivo ZIP.');
}

// Adiciona CSV da Empresa
$zip->addFromString('empresa.csv', array_to_csv_string($empresa, ['ID', 'Nome', 'Email', 'Data de Criação']));

// Adiciona CSV dos Usuários
if (!empty($usuarios)) {
    $zip->addFromString('usuarios.csv', array_to_csv_string($usuarios, ['ID', 'Nome', 'Email', 'Perfil', 'Data de Criação']));
}

// Adiciona CSV das Consultas (CORRIGIDO)
if (!empty($consultas)) {
    $zip->addFromString('consultas.csv', array_to_csv_string($consultas, ['ID da Consulta', 'ID do Usuário', 'Nome do Usuário', 'CNPJ Consultado', 'Razão Social', 'Data da Consulta', 'Dados (JSON)']));
}

$zip->close();

// 5. Envio do Arquivo para Download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="backup_' . $nome_empresa_slug . '_' . $empresa_id . '.zip"');
header('Content-Length: ' . filesize($zip_filename));
header('Connection: close');

readfile($zip_filename);

// 6. Limpeza
unlink($zip_filename);
exit;

?>