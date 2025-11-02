<?php
/**
 * Verificação Rápida: Qual empresa_id está sendo usado?
 * Cole este arquivo no servidor e acesse via navegador
 */

require_once 'config.php';

// Simula a busca que a API faz
$api_token = $_GET['token'] ?? 'COLE_SEU_TOKEN_AQUI';

echo "<h2>🔍 Verificação de empresa_id</h2>";
echo "<hr>";

// Busca como a API faz
$stmt = $pdo->prepare("
    SELECT id, empresa_id, slug, nome_empresa, api_token_ativo, api_rate_limit 
    FROM configuracoes_whitelabel 
    WHERE api_token = ? 
    LIMIT 1
");
$stmt->execute([$api_token]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    echo "<div style='color: red;'>❌ Token não encontrado!</div>";
    echo "<p>Token usado: <code>$api_token</code></p>";
    exit;
}

echo "<h3>✅ Configuração encontrada:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Campo</th><th>Valor</th><th>O que é?</th></tr>";
echo "<tr><td>id</td><td><strong>{$empresa['id']}</strong></td><td>❌ ID da tabela configuracoes_whitelabel (ERRADO se usar aqui)</td></tr>";
echo "<tr><td>empresa_id</td><td><strong>" . ($empresa['empresa_id'] ?? 'NULL') . "</strong></td><td>✅ ID da tabela empresas (CORRETO!)</td></tr>";
echo "<tr><td>slug</td><td>{$empresa['slug']}</td><td>Slug whitelabel</td></tr>";
echo "<tr><td>nome_empresa</td><td>{$empresa['nome_empresa']}</td><td>Nome</td></tr>";
echo "</table>";

echo "<hr>";
echo "<h3>🔍 Verificação na tabela empresas:</h3>";

$config_id = $empresa['id'];
$empresa_id = $empresa['empresa_id'] ?? null;

if (empty($empresa_id)) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px;'>";
    echo "❌ <strong>PROBLEMA ENCONTRADO!</strong><br>";
    echo "O campo <code>empresa_id</code> está vazio ou NULL na tabela <code>configuracoes_whitelabel</code>!<br>";
    echo "Execute o script <code>fix_empresa_id_quick.sql</code> para corrigir.";
    echo "</div>";
} else {
    // Verifica se o empresa_id existe na tabela empresas
    $stmt_check = $pdo->prepare("SELECT id, nome, email FROM empresas WHERE id = ?");
    $stmt_check->execute([$empresa_id]);
    $empresa_real = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa_real) {
        echo "<div style='color: green; background: #e6ffe6; padding: 10px;'>";
        echo "✅ <strong>CORRETO!</strong> empresa_id aponta para empresa válida:<br>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$empresa_real['id']}</li>";
        echo "<li><strong>Nome:</strong> {$empresa_real['nome']}</li>";
        echo "<li><strong>Email:</strong> {$empresa_real['email']}</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='color: red; background: #ffe6e6; padding: 10px;'>";
        echo "❌ <strong>PROBLEMA!</strong> empresa_id <code>$empresa_id</code> NÃO EXISTE na tabela empresas!<br>";
        echo "Execute o script <code>fix_empresa_id_quick.sql</code> para corrigir.";
        echo "</div>";
    }
}

echo "<hr>";
echo "<h3>📋 Código que DEVE estar no api_lead_webhook.php:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo htmlspecialchars("
// CORRETO ✅
\$config_id = \$empresa['id'];       // ID da config (11, 12, 13, 14)
\$empresa_id = \$empresa['empresa_id']; // ID da empresas (1, 16, 18, 19)

// ERRADO ❌ (se estiver assim, precisa corrigir!)
\$empresa_id = \$empresa['id'];  // Está usando ID da config
");
echo "</pre>";

echo "<hr>";
echo "<h3>🧪 Teste de Inserção (sem executar):</h3>";
echo "<p>Se fosse inserir um lead agora, usaria:</p>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo "id_empresa_master = <strong>$empresa_id</strong>";
echo "</pre>";

if (empty($empresa_id)) {
    echo "<p style='color: red;'>❌ Isso causaria erro de foreign key!</p>";
} else {
    echo "<p style='color: green;'>✅ Isso deveria funcionar!</p>";
}

echo "<hr>";
echo "<h3>🔧 Solução:</h3>";
echo "<ol>";
echo "<li><strong>Baixe o arquivo corrigido:</strong> <code>api_lead_webhook.php</code></li>";
echo "<li><strong>Faça upload</strong> para o servidor (substitua o arquivo antigo)</li>";
echo "<li><strong>Teste novamente</strong> o formulário</li>";
echo "</ol>";

echo "<p><a href='test_universal_capture.php'>➡️ Ir para página de testes</a></p>";
?>
