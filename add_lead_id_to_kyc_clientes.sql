-- ============================================
-- MIGRAÇÃO: Adiciona coluna lead_id à tabela kyc_clientes
-- ============================================
-- Data: 2025-11-02
-- Objetivo: Permitir associação entre Leads e Clientes KYC
--
-- INSTRUÇÕES:
-- 1. Execute este script no banco de dados
-- 2. Verifique se a coluna foi criada: SHOW COLUMNS FROM kyc_clientes LIKE 'lead_id';
-- ============================================

-- Verifica e adiciona coluna lead_id se não existir
SET @dbname = DATABASE();
SET @tablename = 'kyc_clientes';
SET @columnname = 'lead_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL COMMENT ''ID do lead que originou este cliente'' AFTER id_empresa_master')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verifica e adiciona coluna origem se não existir
SET @columnname = 'origem';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename)
   AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(50) NULL DEFAULT ''registro_direto'' COMMENT ''Origem do cliente'' AFTER lead_id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona índice para lead_id (ignora erro se já existir)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE table_schema = DATABASE()
   AND table_name = 'kyc_clientes'
   AND index_name = 'idx_lead_id') > 0,
  'SELECT 1',
  'CREATE INDEX idx_lead_id ON kyc_clientes (lead_id)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona índice para origem (ignora erro se já existir)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE table_schema = DATABASE()
   AND table_name = 'kyc_clientes'
   AND index_name = 'idx_origem') > 0,
  'SELECT 1',
  'CREATE INDEX idx_origem ON kyc_clientes (origem)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Adiciona constraint de chave estrangeira (ignora erro se já existir)
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
   WHERE table_schema = DATABASE()
   AND table_name = 'kyc_clientes'
   AND constraint_name = 'fk_kyc_clientes_lead') > 0,
  'SELECT 1',
  'ALTER TABLE kyc_clientes ADD CONSTRAINT fk_kyc_clientes_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL ON UPDATE CASCADE'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Atualiza clientes existentes sem origem definida
UPDATE kyc_clientes 
SET origem = 'registro_direto' 
WHERE origem IS NULL;

-- ============================================
-- VERIFICAÇÃO
-- ============================================

-- Exibe estrutura atualizada
SELECT 
    COLUMN_NAME as 'Coluna',
    COLUMN_TYPE as 'Tipo',
    IS_NULLABLE as 'NULL',
    COLUMN_DEFAULT as 'Padrão',
    COLUMN_COMMENT as 'Comentário'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'kyc_clientes'
AND COLUMN_NAME IN ('lead_id', 'origem')
ORDER BY ORDINAL_POSITION;

-- Exibe índices criados
SHOW INDEX FROM kyc_clientes WHERE Key_name IN ('idx_lead_id', 'idx_origem');

-- Confirma constraints
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND CONSTRAINT_NAME = 'fk_kyc_clientes_lead';

SELECT '✅ Migração concluída com sucesso!' as Status;
