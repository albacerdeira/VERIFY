-- ========================================
-- TABELA: aml_screenings
-- Armazena histórico de triagens AML
-- ========================================

CREATE TABLE IF NOT EXISTS aml_screenings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL COMMENT 'ID da empresa que solicitou o screening',
    
    -- Dados da pessoa/empresa screenada
    cpf VARCHAR(11) NULL COMMENT 'CPF limpo (apenas números)',
    cnpj VARCHAR(14) NULL COMMENT 'CNPJ limpo (apenas números)',
    nome VARCHAR(255) NOT NULL COMMENT 'Nome ou Razão Social',
    tipo ENUM('pf', 'pj') NOT NULL COMMENT 'Tipo de pessoa',
    
    -- Resultado do screening
    risk_score INT NOT NULL DEFAULT 0 COMMENT 'Score de risco (0-100)',
    risk_level ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL COMMENT 'Nível de risco',
    flags JSON COMMENT 'Array de flags encontradas (CEIS, CNEP, PEP)',
    
    -- Metadata
    screened_at DATETIME NOT NULL COMMENT 'Data/hora do screening',
    
    -- Índices
    INDEX idx_empresa_id (empresa_id),
    INDEX idx_cpf (cpf),
    INDEX idx_cnpj (cnpj),
    INDEX idx_risk_level (risk_level),
    INDEX idx_screened_at (screened_at),
    INDEX idx_tipo (tipo),
    
    -- Chave estrangeira (opcional, se configuracoes_whitelabel existir)
    FOREIGN KEY (empresa_id) REFERENCES configuracoes_whitelabel(id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de triagens AML (CEIS, CNEP, PEP)';

-- ========================================
-- EXEMPLO DE CONSULTAS ÚTEIS
-- ========================================

-- 1. Total de screenings por empresa (últimos 30 dias)
-- SELECT empresa_id, COUNT(*) as total
-- FROM aml_screenings
-- WHERE screened_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
-- GROUP BY empresa_id;

-- 2. Screenings com alto risco
-- SELECT id, nome, cpf, cnpj, risk_score, risk_level, screened_at
-- FROM aml_screenings
-- WHERE risk_level IN ('HIGH', 'CRITICAL')
-- ORDER BY screened_at DESC
-- LIMIT 100;

-- 3. Top 10 pessoas/empresas screenadas
-- SELECT nome, COUNT(*) as screenings_count
-- FROM aml_screenings
-- GROUP BY nome
-- ORDER BY screenings_count DESC
-- LIMIT 10;

-- 4. Estatísticas por nível de risco
-- SELECT risk_level, COUNT(*) as count
-- FROM aml_screenings
-- GROUP BY risk_level;

-- 5. Verificar rate limit de uma empresa (última hora)
-- SELECT COUNT(*) as count
-- FROM aml_screenings
-- WHERE empresa_id = 18
-- AND screened_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
