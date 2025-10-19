<?php
// MODO DE DEPURAÇÃO: Garante que qualquer erro seja visível.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>"; // Usar a tag <pre> para formatar a saída

require_once 'config.php';

$nome_usuario = 'Alba Cerdeira';

echo "Iniciando script de sincronização para o usuário: " . htmlspecialchars($nome_usuario) . "\n";

try {
    // CORREÇÃO: Puxar NOME, EMAIL e a SENHA (hash) do usuário.
    $stmt_user = $pdo->prepare("SELECT nome, email, password FROM usuarios WHERE nome = :nome AND role = 'superadmin'");
    $stmt_user->execute(['nome' => $nome_usuario]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        die("ERRO: O usuário '" . htmlspecialchars($nome_usuario) . "' não foi encontrado na tabela `usuarios` ou não tem a permissão 'superadmin'. Nenhuma ação foi tomada.\n");
    }

    $email_usuario = $user_data['email'];
    $nome_usuario_db = $user_data['nome'];
    $password_hash = $user_data['password']; // Hash da senha

    echo "Usuário encontrado na tabela `usuarios` com o e-mail: " . htmlspecialchars($email_usuario) . "\n";

    $stmt_superadmin = $pdo->prepare("SELECT COUNT(*) FROM superadmin WHERE email = :email");
    $stmt_superadmin->execute(['email' => $email_usuario]);
    
    if ($stmt_superadmin->fetchColumn() > 0) {
        echo "AVISO: O e-mail '" . htmlspecialchars($email_usuario) . "' já existe na tabela `superadmin`. Nenhuma ação foi necessária.\n";
    } else {
        echo "Inconsistência encontrada. Inserindo o usuário na tabela `superadmin`...\n";
        // CORREÇÃO FINAL: Inserir nome, email e a senha (hash) para cumprir o NOT NULL.
        $sql_insert = "INSERT INTO superadmin (nome, email, password) VALUES (:nome, :email, :password)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            'nome' => $nome_usuario_db,
            'email' => $email_usuario,
            'password' => $password_hash
        ]);

        if ($stmt_insert->rowCount() > 0) {
            echo "SUCESSO: O usuário foi inserido na tabela `superadmin` com sucesso!\n";
        } else {
            die("FALHA CRÍTICA: A inserção no banco de dados falhou por um motivo desconhecido.\n");
        }
    }

    echo "\nSincronização concluída. O problema de permissão deve estar resolvido.\n";

} catch (PDOException $e) {
    die("ERRO DE BANCO DE DADOS: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("ERRO GERAL: " . $e->getMessage() . "\n");
}

echo "</pre>";

?>