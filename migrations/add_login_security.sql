-- Adicionar colunas de segurança na tabela kyc_clientes
ALTER TABLE kyc_clientes
ADD COLUMN IF NOT EXISTS ultimo_login DATETIME NULL,
ADD COLUMN IF NOT EXISTS ultimo_ip VARCHAR(45) NULL,
ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS lockout_until DATETIME NULL;

-- Tabela para log de tentativas de login
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NULL,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    status ENUM(
        'success',
        'failed',
        'blocked'
    ) NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_created (created_at),
    FOREIGN KEY (cliente_id) REFERENCES kyc_clientes (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tabela para armazenar foto de verificação facial (usada no cliente_edit)
CREATE TABLE IF NOT EXISTS facial_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    verification_type ENUM(
        'edit_profile',
        'password_change',
        'sensitive_data'
    ) NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    similarity_score DECIMAL(5, 2) NULL,
    verification_status ENUM(
        'pending',
        'approved',
        'rejected'
    ) DEFAULT 'pending',
    verified_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES kyc_clientes (id) ON DELETE CASCADE,
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_created (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;