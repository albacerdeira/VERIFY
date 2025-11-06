<?php
/**
 * Script para executar migração INLINE (SQL embutido no código)
 * Adiciona campos de dados pessoais completos na tabela kyc_clientes
 * USE ESTE em produção quando não tiver acesso ao arquivo .sql
 */

require_once 'bootstrap.php';

try {
    echo "=== EXECUTANDO MIGRAÇÃO: add_kyc_personal_data (INLINE) ===\n\n";
    
    // SQL embutido diretamente no código
    $sql_statements = [
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS documento_foto_path VARCHAR(500) DEFAULT NULL COMMENT 'Caminho da foto do RG/CNH do cliente'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS rg VARCHAR(20) DEFAULT NULL COMMENT 'Número do RG (extraído do documento)'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS data_nascimento DATE DEFAULT NULL COMMENT 'Data de nascimento do cliente'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS nome_pai VARCHAR(255) DEFAULT NULL COMMENT 'Nome completo do pai'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS nome_mae VARCHAR(255) DEFAULT NULL COMMENT 'Nome completo da mãe'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_rua VARCHAR(255) DEFAULT NULL COMMENT 'Logradouro (rua, avenida, etc)'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_numero VARCHAR(20) DEFAULT NULL COMMENT 'Número da residência'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_complemento VARCHAR(100) DEFAULT NULL COMMENT 'Complemento (apto, bloco, etc)'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_bairro VARCHAR(100) DEFAULT NULL COMMENT 'Bairro'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_cidade VARCHAR(100) DEFAULT NULL COMMENT 'Cidade'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_estado VARCHAR(2) DEFAULT NULL COMMENT 'UF (estado)'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS endereco_cep VARCHAR(10) DEFAULT NULL COMMENT 'CEP'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) DEFAULT NULL COMMENT 'Telefone de contato'",
        "ALTER TABLE kyc_clientes ADD COLUMN IF NOT EXISTS dados_completos_preenchidos BOOLEAN DEFAULT FALSE COMMENT 'Indica se o cliente preencheu todos os dados complementares'"
    ];
    
    echo "Executando " . count($sql_statements) . " comandos ALTER TABLE...\n\n";
    
    $success_count = 0;
    $skip_count = 0;
    
    foreach ($sql_statements as $index => $sql) {
        try {
            $pdo->exec($sql);
            $success_count++;
            echo "✅ Comando " . ($index + 1) . " executado\n";
        } catch (PDOException $e) {
            // Se o erro for "Duplicate column", ignora (coluna já existe)
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                $skip_count++;
                echo "⏭️  Comando " . ($index + 1) . " - coluna já existe\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n📊 Resumo:\n";
    echo "  - Comandos executados: $success_count\n";
    echo "  - Comandos pulados (já existiam): $skip_count\n";
    echo "  - Total: " . count($sql_statements) . "\n\n";
    
    // Cria índices
    echo "Criando índices...\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_kyc_clientes_cpf ON kyc_clientes(cpf)",
        "CREATE INDEX IF NOT EXISTS idx_kyc_clientes_data_nascimento ON kyc_clientes(data_nascimento)",
        "CREATE INDEX IF NOT EXISTS idx_kyc_clientes_endereco_cep ON kyc_clientes(endereco_cep)",
        "CREATE INDEX IF NOT EXISTS idx_kyc_clientes_dados_completos ON kyc_clientes(dados_completos_preenchidos)"
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $pdo->exec($index_sql);
            echo "✅ Índice criado\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⏭️  Índice já existe\n";
            } else {
                echo "⚠️  Aviso: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!\n\n";
    
    // Verifica os novos campos
    echo "=== VERIFICANDO NOVOS CAMPOS ===\n\n";
    $stmt = $pdo->query("DESCRIBE kyc_clientes");
    $fields = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $new_fields = [
        'documento_foto_path',
        'rg',
        'data_nascimento',
        'nome_pai',
        'nome_mae',
        'endereco_rua',
        'endereco_numero',
        'endereco_complemento',
        'endereco_bairro',
        'endereco_cidade',
        'endereco_estado',
        'endereco_cep',
        'telefone',
        'dados_completos_preenchidos'
    ];
    
    echo "Campos verificados:\n";
    $found_count = 0;
    foreach ($new_fields as $field) {
        if (in_array($field, $fields)) {
            echo "✅ $field\n";
            $found_count++;
        } else {
            echo "❌ $field - NÃO ENCONTRADO!\n";
        }
    }
    
    echo "\n📊 Total: $found_count/" . count($new_fields) . " campos presentes\n";
    
    if ($found_count === count($new_fields)) {
        echo "\n🎉 TODOS OS CAMPOS FORAM ADICIONADOS COM SUCESSO!\n";
    } else {
        echo "\n⚠️  ATENÇÃO: Alguns campos estão faltando!\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "\n📝 Detalhes técnicos:\n";
    echo "  Arquivo: " . $e->getFile() . "\n";
    echo "  Linha: " . $e->getLine() . "\n";
    exit(1);
}
