<?php
// Inicia a sessão e carrega toda a configuração, incluindo a verificação de login.
require_once 'bootstrap.php';

// Define o tipo de conteúdo da resposta como JSON.
header('Content-Type: application/json; charset=utf-8');

// --- VALIDAÇÃO DE ACESSO E PARÂMETROS ---

// O bootstrap.php já garante que o usuário esteja logado.
// A verificação abaixo é uma dupla checagem por segurança.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'ERROR', 'message' => 'Acesso negado. Você precisa estar logado.']);
    exit();
}

if (!isset($_GET['cnpj'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'ERROR', 'message' => 'Parâmetro CNPJ não fornecido.']);
    exit();
}

$cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj']);
if (strlen($cnpj) !== 14) {
    http_response_code(400);
    echo json_encode(['status' => 'ERROR', 'message' => 'CNPJ inválido. Deve conter 14 dígitos.']);
    exit();
}

// --- CHAMADA PARA A API EXTERNA (BrasilAPI) ---

$url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'InternalCNPJTool/1.0');
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['status' => 'ERROR', 'message' => 'Erro ao contatar a API externa: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}
curl_close($ch);

// --- LÓGICA DE SALVAMENTO NO BANCO DE DADOS ---

// Se a consulta à API foi bem-sucedida (código 200), salva no banco.
if ($http_code === 200) {
    $data = json_decode($response, true);
    
    // Verifica se o JSON é válido e se não é uma mensagem de erro da API
    if (json_last_error() === JSON_ERROR_NONE && !isset($data['type'])) {
        try {
            $sql = "INSERT INTO consultas (
                        cnpj, dados, usuario_id, uf, cep, qsa, pais, email, porte, bairro, numero, 
                        ddd_fax, municipio, logradouro, cnae_fiscal, complemento, razao_social, 
                        nome_fantasia, capital_social, ddd_telefone_1, ddd_telefone_2, natureza_juridica, 
                        cnaes_secundarios, situacao_cadastral, data_inicio_atividade, 
                        data_situacao_cadastral, descricao_situacao_cadastral, 
                        descricao_tipo_de_logradouro, descricao_identificador_matriz_filial
                    ) VALUES (
                        :cnpj, :dados, :usuario_id, :uf, :cep, :qsa, :pais, :email, :porte, :bairro, :numero,
                        :ddd_fax, :municipio, :logradouro, :cnae_fiscal, :complemento, :razao_social,
                        :nome_fantasia, :capital_social, :ddd_telefone_1, :ddd_telefone_2, :natureza_juridica,
                        :cnaes_secundarios, :situacao_cadastral, :data_inicio_atividade,
                        :data_situacao_cadastral, :descricao_situacao_cadastral,
                        :descricao_tipo_de_logradouro, :descricao_identificador_matriz_filial
                    )";
            
            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':cnpj' => $data['cnpj'] ?? null,
                ':dados' => $response, // Salva o JSON completo
                ':usuario_id' => $_SESSION['user_id'],
                ':uf' => $data['uf'] ?? null,
                ':cep' => $data['cep'] ?? null,
                ':qsa' => isset($data['qsa']) ? json_encode($data['qsa']) : null,
                ':pais' => $data['pais'] ?? null,
                ':email' => $data['email'] ?? null,
                ':porte' => $data['porte'] ?? null,
                ':bairro' => $data['bairro'] ?? null,
                ':numero' => $data['numero'] ?? null,
                ':ddd_fax' => $data['ddd_fax'] ?? null,
                ':municipio' => $data['municipio'] ?? null,
                ':logradouro' => $data['logradouro'] ?? null,
                ':cnae_fiscal' => $data['cnae_fiscal'] ?? null,
                ':complemento' => $data['complemento'] ?? null,
                ':razao_social' => $data['razao_social'] ?? null,
                ':nome_fantasia' => $data['nome_fantasia'] ?? null,
                ':capital_social' => $data['capital_social'] ?? null,
                ':ddd_telefone_1' => $data['ddd_telefone_1'] ?? null,
                ':ddd_telefone_2' => $data['ddd_telefone_2'] ?? null,
                ':natureza_juridica' => $data['natureza_juridica'] ?? null,
                ':cnaes_secundarios' => isset($data['cnaes_secundarios']) ? json_encode($data['cnaes_secundarios']) : null,
                ':situacao_cadastral' => $data['situacao_cadastral'] ?? null,
                ':data_inicio_atividade' => $data['data_inicio_atividade'] ?? null,
                ':data_situacao_cadastral' => $data['data_situacao_cadastral'] ?? null,
                ':descricao_situacao_cadastral' => $data['descricao_situacao_cadastral'] ?? null,
                ':descricao_tipo_de_logradouro' => $data['descricao_tipo_de_logradouro'] ?? null,
                ':descricao_identificador_matriz_filial' => $data['descricao_identificador_matriz_filial'] ?? null
            ]);

        } catch (PDOException $e) {
            // Loga o erro de banco de dados, mas não impede o usuário de ver o resultado.
            error_log("Erro ao salvar consulta no banco de dados: " . $e->getMessage());
        }
    }
}

// --- RETORNO DA RESPOSTA PARA O FORMULÁRIO ---

http_response_code($http_code);
echo $response;
?>
