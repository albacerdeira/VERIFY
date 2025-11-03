-- ============================================
-- VERIFICAÇÃO DA ESTRUTURA ATUAL
-- ============================================

-- Mostra a estrutura atual da tabela kyc_clientes
SHOW COLUMNS FROM kyc_clientes;

-- Mostra os índices existentes
SHOW INDEX FROM kyc_clientes;

-- Verifica se há clientes com lead_id preenchido
SELECT 
    COUNT(*) as total_clientes,
    COUNT(lead_id) as com_lead_id,
    COUNT(*) - COUNT(lead_id) as sem_lead_id,
    COUNT(origem) as com_origem
FROM kyc_clientes;

-- Mostra alguns exemplos
SELECT *
FROM kyc_clientes
ORDER BY id DESC
LIMIT 10;
