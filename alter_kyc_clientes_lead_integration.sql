-- Adiciona campos para integração Lead → Cliente KYC
-- Executar este script caso os campos não existam

-- Adiciona token de acesso único para formulário KYC sem login
ALTER TABLE kyc_clientes 
ADD COLUMN IF NOT EXISTS token_acesso VARCHAR(64) NULL AFTER email_verificado,
ADD COLUMN IF NOT EXISTS token_expiracao DATETIME NULL AFTER token_acesso,
ADD COLUMN IF NOT EXISTS origem VARCHAR(50) NULL DEFAULT 'registro_direto' AFTER id_empresa_master,
ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) NULL AFTER email;

-- Adiciona índice para busca rápida por token
ALTER TABLE kyc_clientes 
ADD INDEX IF NOT EXISTS idx_token_acesso (token_acesso);

-- Adiciona índice para busca por origem
ALTER TABLE kyc_clientes 
ADD INDEX IF NOT EXISTS idx_origem (origem);

-- Atualiza clientes existentes sem origem definida
UPDATE kyc_clientes 
SET origem = 'registro_direto' 
WHERE origem IS NULL;
