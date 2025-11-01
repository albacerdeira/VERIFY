-- Script para atualizar status "Enviado" para "Novo Registro"
-- Execute este script no banco de dados para atualizar registros existentes

-- Atualiza os registros na tabela kyc_empresas
UPDATE kyc_empresas 
SET status = 'Novo Registro' 
WHERE status = 'Enviado';

-- Verifica quantos registros foram atualizados
SELECT COUNT(*) as total_atualizados 
FROM kyc_empresas 
WHERE status = 'Novo Registro';

-- Lista os registros atualizados
SELECT id, razao_social, cnpj, status, data_criacao 
FROM kyc_empresas 
WHERE status = 'Novo Registro' 
ORDER BY data_criacao DESC 
LIMIT 20;
