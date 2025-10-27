USE u640879529_kyc;

CREATE TABLE IF NOT EXISTS kyc_clientes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    codigo_verificacao VARCHAR(10) NULL DEFAULT NULL,
    codigo_expira_em DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('ativo', 'inativo', 'pendente') NOT NULL DEFAULT 'pendente',
    PRIMARY KEY (id),
    UNIQUE KEY uk_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
