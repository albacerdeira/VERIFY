<?php
/**
 * Script para executar migração: add_kyc_personal_data.sql
 * Adiciona campos de dados pessoais completos na tabela kyc_clientes
 */

require_once 'bootstrap.php';

try {
    echo "=== EXECUTANDO MIGRAÇÃO: add_kyc_personal_data.sql ===\n\n";
    
    // Tenta múltiplos caminhos possíveis
    $possible_paths = [
        __DIR__ . '/migrations/add_kyc_personal_data.sql',
        dirname(__FILE__) . '/migrations/add_kyc_personal_data.sql',
        'migrations/add_kyc_personal_data.sql'
    ];
    
    $sql_file = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $sql_file = $path;
            echo "✅ Arquivo encontrado em: $path\n\n";
            break;
        }
    }
    
    if (!$sql_file) {
        echo "❌ Arquivo não encontrado. Tentativas:\n";
        foreach ($possible_paths as $path) {
            echo "  - $path\n";
        }
        echo "\n📁 Diretório atual: " . __DIR__ . "\n";
        echo "📁 Arquivos em migrations/:\n";
        if (is_dir(__DIR__ . '/migrations')) {
            $files = scandir(__DIR__ . '/migrations');
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "  - $file\n";
                }
            }
        } else {
            echo "  ❌ Diretório migrations/ não existe!\n";
        }
        throw new Exception("Arquivo de migração não encontrado em nenhum caminho possível");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Executa a migração
    $pdo->exec($sql);
    
    echo "✅ Migração executada com sucesso!\n\n";
    
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
    
    foreach ($new_fields as $field) {
        $status = in_array($field, $fields) ? '✅' : '❌';
        echo "$status Campo: $field\n";
    }
    
    echo "\n=== MIGRAÇÃO CONCLUÍDA ===\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
