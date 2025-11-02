-- ============================================
-- SCRIPT COMPLETO: Sistema de Leads → Cliente KYC
-- Data: 01/11/2025
-- Versão: 1.0
-- ============================================

-- Este script cria toda estrutura necessária para:
-- 1. Captura de leads via formulário web
-- 2. Gestão de leads no painel administrativo
-- 3. Conversão de leads em clientes KYC
-- 4. Acesso direto ao formulário via token seguro

-- ============================================
-- PARTE 1: TABELAS DE LEADS
-- ============================================

-- Tabela principal de leads capturados
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    empresa VARCHAR(255) NULL,
    mensagem TEXT NULL,
    
    -- Informações de rastreamento
    origem VARCHAR(100) NULL COMMENT 'Página de origem do lead',
    utm_source VARCHAR(100) NULL,
    utm_medium VARCHAR(100) NULL,
    utm_campaign VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    -- Status do lead no funil
    status ENUM('novo', 'contatado', 'qualificado', 'convertido', 'perdido') DEFAULT 'novo',
    
    -- Empresa parceira associada (whitelabel)
    id_empresa_master INT NULL,
    
    -- Flag para integração com CRM externo
    enviado_crm TINYINT(1) DEFAULT 0,
    crm_id VARCHAR(100) NULL COMMENT 'ID do lead no CRM externo',
    crm_data_envio DATETIME NULL,
    
    -- Responsável pelo lead
    id_usuario_responsavel INT NULL,
    
    -- Observações internas
    observacoes TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para performance
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_empresa (id_empresa_master),
    INDEX idx_created (created_at),
    INDEX idx_responsavel (id_usuario_responsavel),
    
    -- Chaves estrangeiras
    FOREIGN KEY (id_empresa_master) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (id_usuario_responsavel) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Leads capturados de formulários web';

-- Tabela de histórico de interações com leads
CREATE TABLE IF NOT EXISTS leads_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    usuario_id INT NULL,
    acao VARCHAR(100) NOT NULL COMMENT 'contatado, email_enviado, qualificado, kyc_enviado, etc',
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_lead (lead_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_created (created_at),
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de todas interações com leads';

-- Tabela de log de webhooks para CRM (auditoria)
CREATE TABLE IF NOT EXISTS leads_webhook_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    payload_enviado TEXT NOT NULL,
    response_code INT NULL,
    response_body TEXT NULL,
    success BOOLEAN DEFAULT FALSE,
    error_message TEXT NULL,
    tempo_resposta_ms INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_lead_id (lead_id),
    INDEX idx_success (success),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log de chamadas webhook para integração com CRM externo';

-- ============================================
-- PARTE 2: EXTENSÃO DA TABELA kyc_clientes
-- ============================================

-- Adiciona campos para integração Lead → Cliente KYC
-- Permite acesso direto ao formulário via token seguro

-- Campo: token_acesso (64 caracteres hexadecimais)
-- Segurança: bin2hex(random_bytes(32)) = 2^256 possibilidades
ALTER TABLE kyc_clientes 
ADD COLUMN IF NOT EXISTS token_acesso VARCHAR(64) NULL 
COMMENT 'Token seguro para acesso direto ao formulário KYC (64 chars hex)'
AFTER email_verificado;

-- Campo: token_expiracao (validade do token)
ALTER TABLE kyc_clientes 
ADD COLUMN IF NOT EXISTS token_expiracao DATETIME NULL 
COMMENT 'Data/hora de expiração do token (padrão: 30 dias)'
AFTER token_acesso;

-- Campo: origem (rastreamento de conversão)
ALTER TABLE kyc_clientes 
ADD COLUMN IF NOT EXISTS origem VARCHAR(50) NULL DEFAULT 'registro_direto'
COMMENT 'Origem do cliente: registro_direto, lead_conversion, importacao, etc'
AFTER id_empresa_master;

-- Campo: telefone (WhatsApp do lead)
ALTER TABLE kyc_clientes 
ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) NULL 
COMMENT 'Telefone/WhatsApp capturado do lead'
AFTER email;

-- ============================================
-- PARTE 3: ÍNDICES PARA PERFORMANCE
-- ============================================

-- Índice para busca rápida por token
-- Usado em kyc_form.php ao acessar via link
ALTER TABLE kyc_clientes 
ADD INDEX IF NOT EXISTS idx_token_acesso (token_acesso);

-- Índice para relatórios de conversão
ALTER TABLE kyc_clientes 
ADD INDEX IF NOT EXISTS idx_origem (origem);

-- ============================================
-- PARTE 4: DADOS INICIAIS
-- ============================================

-- Atualiza clientes existentes sem origem definida
UPDATE kyc_clientes 
SET origem = 'registro_direto' 
WHERE origem IS NULL;

-- ============================================
-- VERIFICAÇÃO DA INSTALAÇÃO
-- ============================================

-- Exibe estrutura criada
SELECT 
    'Tabela criada' as status,
    TABLE_NAME as tabela,
    TABLE_ROWS as registros
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME IN ('leads', 'leads_historico', 'leads_webhook_log')
ORDER BY TABLE_NAME;

-- Exibe campos adicionados em kyc_clientes
SELECT 
    'Campo adicionado' as status,
    COLUMN_NAME as campo,
    COLUMN_TYPE as tipo,
    COLUMN_COMMENT as comentario
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'kyc_clientes'
AND COLUMN_NAME IN ('token_acesso', 'token_expiracao', 'origem', 'telefone')
ORDER BY ORDINAL_POSITION;

-- ============================================
-- CONCLUÍDO!
-- ============================================
-- Sistema de Leads → Cliente KYC instalado com sucesso!
-- 
-- Próximos passos:
-- 1. Testar captura de lead em lead_form.php
-- 2. Acessar gestão em leads.php
-- 3. Clicar "Enviar Formulário KYC" para gerar token
-- 4. Testar link gerado
-- 5. Verificar formulário KYC com token
-- ============================================
