-- Adiciona campo de API Token à tabela configuracoes_whitelabel
-- Execute este script para adicionar autenticação por empresa
-- Versão segura: verifica se colunas já existem

-- Adiciona colunas na tabela configuracoes_whitelabel (se não existirem)
SET @dbname = DATABASE();
SET @tablename = "configuracoes_whitelabel";

-- Verifica e adiciona api_token
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='api_token') > 0,
    "SELECT 'Column api_token already exists' AS Info",
    "ALTER TABLE configuracoes_whitelabel ADD COLUMN api_token VARCHAR(64) NULL"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona api_token_ativo
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='api_token_ativo') > 0,
    "SELECT 'Column api_token_ativo already exists' AS Info",
    "ALTER TABLE configuracoes_whitelabel ADD COLUMN api_token_ativo TINYINT(1) DEFAULT 1"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona api_rate_limit
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='api_rate_limit') > 0,
    "SELECT 'Column api_rate_limit already exists' AS Info",
    "ALTER TABLE configuracoes_whitelabel ADD COLUMN api_rate_limit INT DEFAULT 100 COMMENT 'Máximo de leads por hora'"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona api_ultimo_uso
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND COLUMN_NAME='api_ultimo_uso') > 0,
    "SELECT 'Column api_ultimo_uso already exists' AS Info",
    "ALTER TABLE configuracoes_whitelabel ADD COLUMN api_ultimo_uso DATETIME NULL"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona índice (ignora se já existir)
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename AND INDEX_NAME='idx_api_token') > 0,
    "SELECT 'Index idx_api_token already exists' AS Info",
    "ALTER TABLE configuracoes_whitelabel ADD INDEX idx_api_token (api_token)"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adiciona campo empresa_id na tabela leads_webhook_log (se não existir)
SET @tablename2 = "leads_webhook_log";

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename2 AND COLUMN_NAME='empresa_id') > 0,
    "SELECT 'Column empresa_id already exists' AS Info",
    "ALTER TABLE leads_webhook_log ADD COLUMN empresa_id INT UNSIGNED NULL AFTER lead_id"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA=@dbname AND TABLE_NAME=@tablename2 AND INDEX_NAME='idx_empresa_id') > 0,
    "SELECT 'Index idx_empresa_id already exists' AS Info",
    "ALTER TABLE leads_webhook_log ADD INDEX idx_empresa_id (empresa_id)"
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Gera tokens únicos para empresas que não têm token
UPDATE configuracoes_whitelabel
SET api_token = CONCAT(
    SUBSTRING(MD5(CONCAT(id, slug, RAND())), 1, 32),
    SUBSTRING(SHA1(CONCAT(slug, nome_empresa, NOW())), 1, 32)
)
WHERE api_token IS NULL OR api_token = '';

-- Verificação final
SELECT 
    id,
    nome_empresa,
    slug,
    api_token,
    api_token_ativo,
    api_rate_limit,
    api_ultimo_uso
FROM configuracoes_whitelabel;
