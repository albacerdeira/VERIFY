<?php
/**
 * Migração: Adiciona coluna usuario_id na tabela document_verifications
 * Execute este arquivo UMA VEZ acessando: https://verify2b.com/migrate_document_verifications.php
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migração - Tabelas de Verificação</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        .info { color: #0ff; }
        .section { border: 1px solid #333; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
<h1>🔧 Migração das Tabelas de Verificação</h1>

<?php
try {
    // ============================================
    // MIGRAÇÃO 1: document_verifications
    // ============================================
    echo "<div class='section'>";
    echo "<h2>1. Tabela: document_verifications</h2>";
    echo "<p class='info'>Verificando estrutura da tabela...</p>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM document_verifications LIKE 'usuario_id'");
    $column_exists = $stmt->rowCount() > 0;
    
    if ($column_exists) {
        echo "<p class='ok'>✅ Coluna 'usuario_id' JÁ EXISTE.</p>";
    } else {
        echo "<p class='error'>⚠️ Coluna 'usuario_id' NÃO EXISTE. Adicionando...</p>";
        
        $pdo->exec("
            ALTER TABLE document_verifications 
            ADD COLUMN usuario_id INT(11) NOT NULL AFTER cliente_id,
            ADD INDEX idx_usuario (usuario_id)
        ");
        
        echo "<p class='ok'>✅ Coluna 'usuario_id' adicionada com sucesso!</p>";
    }
    
    // Verifica coluna verification_result
    $stmt = $pdo->query("SHOW COLUMNS FROM document_verifications LIKE 'verification_result'");
    $result_exists = $stmt->rowCount() > 0;
    
    if (!$result_exists) {
        echo "<p class='error'>⚠️ Coluna 'verification_result' NÃO EXISTE. Adicionando...</p>";
        $pdo->exec("
            ALTER TABLE document_verifications 
            ADD COLUMN verification_result ENUM('success','failed') NOT NULL AFTER validations
        ");
        echo "<p class='ok'>✅ Coluna 'verification_result' adicionada!</p>";
    }
    
    echo "</div>";
    
    // ============================================
    // MIGRAÇÃO 2: facial_verifications
    // ============================================
    echo "<div class='section'>";
    echo "<h2>2. Tabela: facial_verifications</h2>";
    echo "<p class='info'>Verificando estrutura da tabela...</p>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM facial_verifications LIKE 'usuario_id'");
    $column_exists = $stmt->rowCount() > 0;
    
    if ($column_exists) {
        echo "<p class='ok'>✅ Coluna 'usuario_id' JÁ EXISTE.</p>";
    } else {
        echo "<p class='error'>⚠️ Coluna 'usuario_id' NÃO EXISTE. Adicionando...</p>";
        
        $pdo->exec("
            ALTER TABLE facial_verifications 
            ADD COLUMN usuario_id INT(11) NOT NULL AFTER cliente_id,
            ADD INDEX idx_usuario_facial (usuario_id)
        ");
        
        echo "<p class='ok'>✅ Coluna 'usuario_id' adicionada com sucesso!</p>";
    }
    
    // Verifica coluna verification_result
    $stmt = $pdo->query("SHOW COLUMNS FROM facial_verifications LIKE 'verification_result'");
    $result_exists = $stmt->rowCount() > 0;
    
    if (!$result_exists) {
        echo "<p class='error'>⚠️ Coluna 'verification_result' NÃO EXISTE. Adicionando...</p>";
        $pdo->exec("
            ALTER TABLE facial_verifications 
            ADD COLUMN verification_result ENUM('success','failed') AFTER similarity_score
        ");
        echo "<p class='ok'>✅ Coluna 'verification_result' adicionada!</p>";
    }
    
    // Verifica coluna user_agent
    $stmt = $pdo->query("SHOW COLUMNS FROM facial_verifications LIKE 'user_agent'");
    $agent_exists = $stmt->rowCount() > 0;
    
    if (!$agent_exists) {
        echo "<p class='error'>⚠️ Coluna 'user_agent' NÃO EXISTE. Adicionando...</p>";
        $pdo->exec("
            ALTER TABLE facial_verifications 
            ADD COLUMN user_agent TEXT NULL AFTER ip_address
        ");
        echo "<p class='ok'>✅ Coluna 'user_agent' adicionada!</p>";
    }
    
    echo "</div>";
    
    // ============================================
    // RESUMO FINAL
    // ============================================
    echo "<div class='section'>";
    echo "<h2>📋 Estrutura Final das Tabelas:</h2>";
    
    echo "<h3>document_verifications:</h3>";
    echo "<pre>";
    $stmt = $pdo->query("DESCRIBE document_verifications");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo sprintf("%-25s %-30s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    echo "</pre>";
    
    echo "<h3>facial_verifications:</h3>";
    echo "<pre>";
    $stmt = $pdo->query("DESCRIBE facial_verifications");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo sprintf("%-25s %-30s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    echo "</pre>";
    
    echo "</div>";
    
    echo "<hr>";
    echo "<p class='ok'><strong>✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!</strong></p>";
    echo "<p class='info'>Agora você pode testar as verificações novamente.</p>";
    echo "<p class='error'><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após executar:</p>";
    echo "<code>rm /home/u640879529/domains/verify2b.com/public_html/migrate_document_verifications.php</code>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>

</body>
</html>
