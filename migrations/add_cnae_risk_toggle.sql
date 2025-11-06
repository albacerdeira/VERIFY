-- Migration: Adicionar toggle para habilitar/desabilitar análise de risco CNAE no KYC
-- Data: 2024
-- Descrição: Adiciona campo analise_risco_cnae_ativo na tabela configuracoes_whitelabel

ALTER TABLE configuracoes_whitelabel 
ADD COLUMN analise_risco_cnae_ativo TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Habilita análise automática de risco por CNAE no KYC (0=desabilitado, 1=habilitado)';

-- Atualizar empresas existentes (opcional - deixar desabilitado por padrão)
-- UPDATE configuracoes_whitelabel SET analise_risco_cnae_ativo = 1 WHERE id > 0;

-- Verificação
SELECT id, nome_empresa, analise_risco_cnae_ativo 
FROM configuracoes_whitelabel 
ORDER BY id;
