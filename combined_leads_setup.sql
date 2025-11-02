-- Tabela para captura de leads de empresas interessadas
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(20) NOT NULL,
    empresa VARCHAR(255) NULL,
    mensagem TEXT NULL,
    
    -- InformaÃ§Ãµes de rastreamento
    origem VARCHAR(100) NULL COMMENT 'PÃ¡gina de origem do lead',
    utm_source VARCHAR(100) NULL,
    utm_medium VARCHAR(100) NULL,
    utm_campaign VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    -- Status do lead
    status ENUM('novo', 'contatado', 'qualificado', 'convertido', 'perdido') DEFAULT 'novo',
    
    -- Empresa parceira associada (whitelabel)
    id_empresa_master INT NULL,
    
    -- Flag para integraÃ§Ã£o com CRM
    enviado_crm TINYINT(1) DEFAULT 0,
    crm_id VARCHAR(100) NULL,
    crm_data_envio DATETIME NULL,
    
    -- ObservaÃ§Ãµes internas
    observacoes TEXT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Ãndices
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_empresa (id_empresa_master),
    INDEX idx_created (created_at),
    
    -- Chave estrangeira
    FOREIGN KEY (id_empresa_master) REFERENCES empresas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para histÃ³rico de interaÃ§Ãµes com leads
CREATE TABLE IF NOT EXISTS leads_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    usuario_id INT NULL,
    acao VARCHAR(100) NOT NULL COMMENT 'contatado, email_enviado, qualificado, etc',
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_lead (lead_id),
    INDEX idx_usuario (usuario_id),
    
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de log de webhooks (para debug e auditoria)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Adiciona campos para integraÃ§Ã£o Lead â†’ Cliente KYC
-- Executar este script caso os campos nÃ£o existam

-- Adiciona token de acesso Ãºnico para formulÃ¡rio KYC sem login
ALTER TABLE kyc_clientes 
ADD COLUMN IF NOT EXISTS token_acesso VARCHAR(64) NULL AFTER email_verificado,
ADD COLUMN IF NOT EXISTS token_expiracao DATETIME NULL AFTER token_acesso,
ADD COLUMN IF NOT EXISTS origem VARCHAR(50) NULL DEFAULT 'registro_direto' AFTER id_empresa_master,
ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) NULL AFTER email;

-- Adiciona Ã­ndice para busca rÃ¡pida por token
ALTER TABLE kyc_clientes 
ADD INDEX IF NOT EXISTS idx_token_acesso (token_acesso);

-- Adiciona Ã­ndice para busca por origem
ALTER TABLE kyc_clientes 
ADD INDEX IF NOT EXISTS idx_origem (origem);

-- Atualiza clientes existentes sem origem definida
UPDATE kyc_clientes 
SET origem = 'registro_direto' 
WHERE origem IS NULL;
