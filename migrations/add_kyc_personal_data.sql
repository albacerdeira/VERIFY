-- ================================================================
-- Migração: Adiciona campos de dados pessoais completos em kyc_clientes
-- Data: 2025-11-04
-- Objetivo: Armazenar foto do documento, filiação, data nascimento e endereço
-- ================================================================

ALTER TABLE kyc_clientes
ADD COLUMN IF NOT EXISTS documento_foto_path VARCHAR(500) DEFAULT NULL COMMENT 'Caminho da foto do RG/CNH do cliente',
ADD COLUMN IF NOT EXISTS rg VARCHAR(20) DEFAULT NULL COMMENT 'Número do RG (extraído do documento)',
ADD COLUMN IF NOT EXISTS data_nascimento DATE DEFAULT NULL COMMENT 'Data de nascimento do cliente',
ADD COLUMN IF NOT EXISTS nome_pai VARCHAR(255) DEFAULT NULL COMMENT 'Nome completo do pai',
ADD COLUMN IF NOT EXISTS nome_mae VARCHAR(255) DEFAULT NULL COMMENT 'Nome completo da mãe',
ADD COLUMN IF NOT EXISTS endereco_rua VARCHAR(255) DEFAULT NULL COMMENT 'Logradouro (rua, avenida, etc)',
ADD COLUMN IF NOT EXISTS endereco_numero VARCHAR(20) DEFAULT NULL COMMENT 'Número da residência',
ADD COLUMN IF NOT EXISTS endereco_complemento VARCHAR(100) DEFAULT NULL COMMENT 'Complemento (apto, bloco, etc)',
ADD COLUMN IF NOT EXISTS endereco_bairro VARCHAR(100) DEFAULT NULL COMMENT 'Bairro',
ADD COLUMN IF NOT EXISTS endereco_cidade VARCHAR(100) DEFAULT NULL COMMENT 'Cidade',
ADD COLUMN IF NOT EXISTS endereco_estado VARCHAR(2) DEFAULT NULL COMMENT 'UF (estado)',
ADD COLUMN IF NOT EXISTS endereco_cep VARCHAR(10) DEFAULT NULL COMMENT 'CEP',
ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) DEFAULT NULL COMMENT 'Telefone de contato',
ADD COLUMN IF NOT EXISTS dados_completos_preenchidos BOOLEAN DEFAULT FALSE COMMENT 'Indica se o cliente preencheu todos os dados complementares';

-- Índices para otimizar buscas
CREATE INDEX IF NOT EXISTS idx_kyc_clientes_cpf ON kyc_clientes (cpf);

CREATE INDEX IF NOT EXISTS idx_kyc_clientes_data_nascimento ON kyc_clientes (data_nascimento);

CREATE INDEX IF NOT EXISTS idx_kyc_clientes_endereco_cep ON kyc_clientes (endereco_cep);

CREATE INDEX IF NOT EXISTS idx_kyc_clientes_dados_completos ON kyc_clientes (dados_completos_preenchidos);

-- ================================================================
-- Fim da Migração
-- ================================================================