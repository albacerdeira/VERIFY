<?php
// Habilita o header para responder em formato JSON
header('Content-Type: application/json');

require 'config.php'; // Garante a conexão com o banco ($pdo)

$response = ['exists' => false];
$cnpj = $_GET['cnpj'] ?? null;

if ($cnpj) {
    // Limpa a máscara do CNPJ para consistência na consulta
    $cnpj_limpo = preg_replace('/[^0-9]/', '', $cnpj);

    try {
        // A consulta é feita no CNPJ limpo, assumindo que ele está salvo sem máscara no banco
        $stmt = $pdo->prepare("SELECT 1 FROM kyc_empresas WHERE cnpj = ?");
        $stmt->execute([$cnpj_limpo]);
        
        if ($stmt->fetch()) {
            $response['exists'] = true;
        }

    } catch (PDOException $e) {
        // Em caso de erro no banco, é mais seguro não travar o usuário,
        // mas você pode querer logar este erro.
        // error_log('Erro ao verificar CNPJ: ' . $e->getMessage());
    }
}

echo json_encode($response);
?>