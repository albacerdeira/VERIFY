-- Script de atualização para adicionar a funcionalidade do Portal do Cliente KYC.
-- Execute este script uma única vez no seu banco de dados.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Cria a nova tabela para armazenar os dados de login dos clientes do KYC.
CREATE TABLE `kyc_clientes` (
  `id` int(11) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `codigo_verificacao` varchar(10) DEFAULT NULL,
  `codigo_expira_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Adiciona os índices e a chave primária para a nova tabela.
ALTER TABLE `kyc_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

-- 3. Define o AUTO_INCREMENT para a nova tabela.
ALTER TABLE `kyc_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- 4. Adiciona a coluna `cliente_id` na tabela `kyc_empresas` para vincular o cadastro ao cliente.
ALTER TABLE `kyc_empresas`
ADD COLUMN `cliente_id` INT(11) DEFAULT NULL AFTER `id`,
ADD KEY `idx_cliente_id` (`cliente_id`);

-- 5. Adiciona a restrição (chave estrangeira) para garantir a integridade dos dados entre `kyc_empresas` e `kyc_clientes`.
ALTER TABLE `kyc_empresas`
ADD CONSTRAINT `fk_cliente_id` FOREIGN KEY (`cliente_id`) REFERENCES `kyc_clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 6. (Manutenção Opcional) Remove a restrição da tabela 'consultas' que pode causar erros para o superadmin.
-- Se você já removeu esta restrição antes, este comando pode dar um erro, o que é seguro ignorar.
-- Se não tiver certeza, pode remover as duas linhas abaixo antes de executar.
ALTER TABLE `consultas`
DROP FOREIGN KEY `consultas_ibfk_1`;

COMMIT;
