-- ============================================
-- MIGRAÇÃO SIMPLIFICADA: Adiciona lead_id
-- ============================================
-- Execute comando por comando no phpMyAdmin
-- Ignore erros "já existe" - isso é normal!
-- ============================================

-- 1. Adiciona coluna lead_id (ignora se já existir)
ALTER TABLE kyc_clientes 
ADD COLUMN lead_id INT NULL 
COMMENT 'ID do lead que originou este cliente'
AFTER id_empresa_master;

-- 2. Adiciona índice (ignora se já existir)
CREATE INDEX idx_lead_id ON kyc_clientes (lead_id);

-- 3. Adiciona constraint (ignora se já existir)
ALTER TABLE kyc_clientes 
ADD CONSTRAINT fk_kyc_clientes_lead 
FOREIGN KEY (lead_id) REFERENCES leads(id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- 4. Atualiza clientes existentes sem origem
UPDATE kyc_clientes 
SET origem = 'registro_direto' 
WHERE origem IS NULL;

-- ============================================
-- VERIFICAÇÃO
-- ============================================

-- Mostra estrutura da coluna lead_id
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'kyc_clientes'
AND COLUMN_NAME = 'lead_id';

-- Conta clientes por origem
SELECT 
    origem,
    COUNT(*) as total
FROM kyc_clientes
GROUP BY origem;

SELECT '✅ Migração concluída!' as Status;
