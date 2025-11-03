<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste lead_detail.php</h1>";

try {
    require_once 'bootstrap.php';
    echo "✅ Bootstrap carregado<br>";
    
    $lead_id = 54;
    echo "✅ Lead ID: $lead_id<br>";
    
    // Teste query básica
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();
    
    if ($lead) {
        echo "✅ Lead encontrado: " . htmlspecialchars($lead['nome']) . "<br>";
    } else {
        echo "❌ Lead não encontrado<br>";
    }
    
    // Teste query de histórico
    echo "<br><h2>Testando query de histórico...</h2>";
    $stmt = $pdo->prepare("
        SELECT 
            created_at,
            acao,
            descricao,
            usuario_nome,
            'lead' as tipo_evento
        FROM leads_historico
        WHERE lead_id = ?
        
        UNION ALL
        
        SELECT 
            created_at,
            'Cliente Registrado' as acao,
            CONCAT('Cliente ', nome_completo, ' completou o cadastro') as descricao,
            'Sistema' as usuario_nome,
            'cliente' as tipo_evento
        FROM kyc_clientes
        WHERE lead_id = ?
        
        UNION ALL
        
        SELECT 
            ke.data_criacao as created_at,
            'KYC Iniciado' as acao,
            CONCAT('Formulário KYC iniciado para empresa ', ke.razao_social) as descricao,
            'Sistema' as usuario_nome,
            'kyc' as tipo_evento
        FROM kyc_empresas ke
        INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
        WHERE kc.lead_id = ?
        
        UNION ALL
        
        SELECT 
            ke.data_atualizacao as created_at,
            'Status Atualizado' as acao,
            CONCAT('Status do KYC alterado para: ', ke.status) as descricao,
            'Sistema' as usuario_nome,
            'kyc_status' as tipo_evento
        FROM kyc_empresas ke
        INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
        WHERE kc.lead_id = ? AND ke.data_atualizacao IS NOT NULL
        
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$lead_id, $lead_id, $lead_id, $lead_id]);
    $historico = $stmt->fetchAll();
    
    echo "✅ Query executada com sucesso<br>";
    echo "📊 Total de eventos: " . count($historico) . "<br><br>";
    
    if ($historico) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Data</th><th>Tipo</th><th>Ação</th><th>Descrição</th></tr>";
        foreach ($historico as $h) {
            echo "<tr>";
            echo "<td>" . date('d/m/Y H:i', strtotime($h['created_at'])) . "</td>";
            echo "<td><strong>" . htmlspecialchars($h['tipo_evento']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($h['acao']) . "</td>";
            echo "<td>" . htmlspecialchars($h['descricao']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
