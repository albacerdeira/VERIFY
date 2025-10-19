<?php
require 'config.php';

try {
    // Primeiro, adiciona a coluna 'role' com um valor padrão
    $sql_add_column = "ALTER TABLE usuarios ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'usuario'";
    $pdo->exec($sql_add_column);
    echo "<p>Coluna 'role' adicionada à tabela 'usuarios' com sucesso.</p>";

    // Em seguida, atualiza o usuário com id = 1 para ter a função de 'admin'
    $sql_update_user = "UPDATE usuarios SET role = 'admin' WHERE id = 1";
    $count = $pdo->exec($sql_update_user);
    echo "<p>Usuário com ID 1 atualizado para a função de 'admin'. Linhas afetadas: $count.</p>";

} catch (PDOException $e) {
    die("<p>Erro ao modificar a tabela: " . $e->getMessage() . "</p>");
}
