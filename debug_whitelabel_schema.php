<?php
/**
 * DEBUG: Verificar estrutura da tabela configuracoes_whitelabel
 */

require_once 'config.php';

try {
    // Mostra estrutura da tabela
    $stmt = $pdo->query("DESCRIBE configuracoes_whitelabel");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Estrutura da tabela configuracoes_whitelabel:</h2>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Testa query com empresa_id
    echo "<h2>Teste de Query:</h2>";
    
    try {
        $test_stmt = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ? LIMIT 1");
        $test_stmt->execute([1]);
        echo "<p style='color: green;'>✅ Query com 'empresa_id' funciona!</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Query com 'empresa_id' falhou: " . $e->getMessage() . "</p>";
        
        // Tenta com id
        try {
            $test_stmt2 = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE id = ? LIMIT 1");
            $test_stmt2->execute([1]);
            echo "<p style='color: orange;'>⚠️ A coluna correta pode ser 'id' em vez de 'empresa_id'</p>";
        } catch (PDOException $e2) {
            echo "<p style='color: red;'>❌ Query com 'id' também falhou: " . $e2->getMessage() . "</p>";
        }
    }
    
    // Mostra alguns registros
    echo "<h2>Registros existentes:</h2>";
    $stmt_all = $pdo->query("SELECT * FROM configuracoes_whitelabel LIMIT 5");
    $rows = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    
    if ($rows) {
        echo "<pre>";
        print_r($rows);
        echo "</pre>";
    } else {
        echo "<p>Nenhum registro encontrado.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>ERRO:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
