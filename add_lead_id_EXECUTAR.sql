-- ============================================
-- MIGRAÇÃO SIMPLES: Adiciona lead_id à kyc_clientes
-- ============================================
-- Execute este script no phpMyAdmin
-- Banco: u640879529_kyc
-- ============================================

-- Passo 1: Adiciona coluna lead_id (ignora erro se já existir)
ALTER TABLE kyc_clientes 
ADD COLUMN lead_id INT NULL COMMENT 'ID do lead que originou este cliente' 
AFTER id_empresa_master;

-- Passo 2: Adiciona coluna origem (ignora erro se já existir)
ALTER TABLE kyc_clientes 
ADD COLUMN origem VARCHAR(50) NULL DEFAULT 'registro_direto' COMMENT 'Origem do cliente' 
AFTER lead_id;

-- Passo 3: Cria índice para lead_id (ignora erro se já existir)
ALTER TABLE kyc_clientes 
ADD INDEX idx_lead_id (lead_id);

-- Passo 4: Cria índice para origem (ignora erro se já existir)
ALTER TABLE kyc_clientes 
ADD INDEX idx_origem (origem);

-- Passo 5: Adiciona chave estrangeira (ignora erro se já existir)
ALTER TABLE kyc_clientes 
ADD CONSTRAINT fk_kyc_clientes_lead 
FOREIGN KEY (lead_id) REFERENCES leads(id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

-- Passo 6: Atualiza registros existentes
UPDATE kyc_clientes 
SET origem = 'registro_direto' 
WHERE origem IS NULL;

-- ============================================
-- VERIFICAÇÃO
-- ============================================
SELECT 'Migração concluída! Verificando estrutura...' as Status;

SHOW COLUMNS FROM kyc_clientes LIKE 'lead_id';
SHOW COLUMNS FROM kyc_clientes LIKE 'origem';

SELECT 
    COUNT(*) as total_clientes,
    COUNT(lead_id) as com_lead_id,
    COUNT(*) - COUNT(lead_id) as sem_lead_id
FROM kyc_clientes;
