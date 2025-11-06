<?php
/**
 * Script para executar migration de segurança de login
 * Execute uma vez via navegador: execute_login_security_migration.php
 */

require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>Executando Migration: Login Security</h2>";
echo "<pre>";

try {
    $sql = file_get_contents(__DIR__ . '/migrations/add_login_security.sql');
    
    // Separa comandos SQL por ponto-e-vírgula
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && strpos($stmt, '--') !== 0;
        }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            echo "✅ Executado: " . substr($statement, 0, 100) . "...\n";
            $success_count++;
        } catch (PDOException $e) {
            // Ignora erros de "já existe"
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠️  Já existe: " . substr($statement, 0, 100) . "...\n";
            } else {
                echo "❌ ERRO: " . $e->getMessage() . "\n";
                echo "   SQL: " . substr($statement, 0, 100) . "...\n";
                $error_count++;
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ Sucesso: {$success_count} comandos\n";
    echo "❌ Erros: {$error_count} comandos\n";
    echo "\n<strong>Migration concluída!</strong>\n";
    
    // Verifica se as tabelas foram criadas
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Verificando estrutura:\n\n";
    
    // Verifica colunas adicionadas
    $stmt = $pdo->query("DESCRIBE kyc_clientes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Colunas em kyc_clientes:\n";
    foreach (['ultimo_login', 'ultimo_ip', 'failed_login_attempts', 'lockout_until'] as $col) {
        echo in_array($col, $columns) ? "  ✅ {$col}\n" : "  ❌ {$col} (faltando)\n";
    }
    
    // Verifica tabelas criadas
    echo "\nTabelas criadas:\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'login_attempts'");
    echo $stmt->rowCount() > 0 ? "  ✅ login_attempts\n" : "  ❌ login_attempts (faltando)\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'facial_verifications'");
    echo $stmt->rowCount() > 0 ? "  ✅ facial_verifications\n" : "  ❌ facial_verifications (faltando)\n";
    
    echo "\n<strong>Pronto para usar Rate Limiting e Reconhecimento Facial!</strong>\n";
    
} catch (Exception $e) {
    echo "❌ ERRO FATAL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<hr>";
echo "<p><a href='cliente_login.php'>← Voltar para Login</a> | <a href='cliente_edit.php'>Ir para Edição de Perfil</a></p>";
