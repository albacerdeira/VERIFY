<?php
// Debug para verificar qual empresa está na sessão
require_once 'bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "===== DEBUG DE SESSÃO =====\n\n";
echo "Usuário logado: " . ($_SESSION['nome'] ?? 'NÃO LOGADO') . "\n";
echo "Role: " . ($user_role ?? 'N/A') . "\n";
echo "Empresa ID (SESSION): " . ($_SESSION['empresa_id'] ?? 'NÃO DEFINIDO') . "\n";
echo "Empresa ID (variável): " . ($user_empresa_id ?? 'NÃO DEFINIDO') . "\n\n";

echo "===== DADOS DA EMPRESA NA SESSÃO =====\n\n";

if (!empty($_SESSION['empresa_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa) {
        echo "Nome: " . $empresa['nome'] . "\n";
        echo "CNPJ: " . $empresa['cnpj'] . "\n";
        echo "ID: " . $empresa['id'] . "\n\n";
    } else {
        echo "❌ Empresa não encontrada no banco!\n\n";
    }
    
    echo "===== CONFIGURAÇÃO WHITELABEL =====\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo "Config ID: " . $config['id'] . "\n";
        echo "Empresa ID: " . $config['empresa_id'] . "\n";
        echo "Nome Empresa: " . $config['nome_empresa'] . "\n";
        echo "Slug: " . ($config['slug'] ?? 'NÃO DEFINIDO') . "\n";
        echo "Token: " . (empty($config['api_token']) ? '❌ VAZIO/NULL' : '✅ CONFIGURADO (' . substr($config['api_token'], 0, 20) . '...)') . "\n";
        echo "Token Ativo: " . ($config['api_token_ativo'] ? '✅ SIM' : '❌ NÃO') . "\n";
    } else {
        echo "❌ Não há registro em configuracoes_whitelabel para esta empresa!\n";
    }
} else {
    echo "❌ Nenhuma empresa definida na sessão!\n";
}

echo "\n===== TODAS AS CONFIGURAÇÕES =====\n\n";

$stmt = $pdo->query("SELECT id, empresa_id, nome_empresa, slug, 
    CASE 
        WHEN api_token IS NULL THEN 'NULL'
        WHEN api_token = '' THEN 'VAZIO'
        ELSE CONCAT(SUBSTRING(api_token, 1, 20), '...')
    END as token_preview,
    api_token_ativo
FROM configuracoes_whitelabel 
ORDER BY empresa_id");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "Config #%d | Empresa %d | %s | Token: %s | Ativo: %s\n",
        $row['id'],
        $row['empresa_id'],
        $row['nome_empresa'],
        $row['token_preview'],
        $row['api_token_ativo'] ? 'SIM' : 'NÃO'
    );
}
