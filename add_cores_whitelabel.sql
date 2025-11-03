-- Adiciona colunas de cores personalizadas à tabela configuracoes_whitelabel
-- Execute este script se estiver recebendo erro 500 no test_universal_capture.php

-- Verifica e adiciona coluna cor_primaria
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'configuracoes_whitelabel' 
    AND COLUMN_NAME = 'cor_primaria'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE configuracoes_whitelabel ADD COLUMN cor_primaria VARCHAR(7) DEFAULT "#0d6efd" COMMENT "Cor primária do whitelabel (formato hex)"',
    'SELECT "Coluna cor_primaria já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verifica e adiciona coluna cor_secundaria
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'configuracoes_whitelabel' 
    AND COLUMN_NAME = 'cor_secundaria'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE configuracoes_whitelabel ADD COLUMN cor_secundaria VARCHAR(7) DEFAULT "#6c757d" COMMENT "Cor secundária do whitelabel (formato hex)"',
    'SELECT "Coluna cor_secundaria já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Atualiza empresas existentes com cores padrão (caso estejam NULL)
UPDATE configuracoes_whitelabel 
SET cor_primaria = '#0d6efd' 
WHERE cor_primaria IS NULL OR cor_primaria = '';

UPDATE configuracoes_whitelabel 
SET cor_secundaria = '#6c757d' 
WHERE cor_secundaria IS NULL OR cor_secundaria = '';

-- Verifica resultado
SELECT 
    'Configuração concluída!' AS status,
    COUNT(*) AS total_empresas,
    SUM(CASE WHEN cor_primaria IS NOT NULL THEN 1 ELSE 0 END) AS com_cor_primaria,
    SUM(CASE WHEN cor_secundaria IS NOT NULL THEN 1 ELSE 0 END) AS com_cor_secundaria
FROM configuracoes_whitelabel;
