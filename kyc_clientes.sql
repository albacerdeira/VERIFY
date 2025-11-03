-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 02/11/2025 às 18:45
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u640879529_kyc`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_clientes`
--

CREATE TABLE `kyc_clientes` (
  `id` int(11) NOT NULL,
  `id_empresa_master` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL COMMENT 'ID do lead que originou este cliente',
  `origem` varchar(50) DEFAULT 'registro_direto' COMMENT 'Origem do cliente: registro_direto, lead_conversion, importacao, etc',
  `nome_completo` varchar(255) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL COMMENT 'Telefone/WhatsApp capturado do lead',
  `password` varchar(255) NOT NULL,
  `selfie_path` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `token_acesso` varchar(64) DEFAULT NULL COMMENT 'Token seguro para acesso direto ao formulário KYC (64 chars hex)',
  `token_expiracao` datetime DEFAULT NULL COMMENT 'Data/hora de expiração do token (padrão: 30 dias)',
  `status` enum('ativo','inativo','pendente') NOT NULL DEFAULT 'pendente',
  `codigo_verificacao` varchar(10) DEFAULT NULL,
  `codigo_expira_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `kyc_clientes`
--

INSERT INTO `kyc_clientes` (`id`, `id_empresa_master`, `lead_id`, `origem`, `nome_completo`, `cpf`, `email`, `telefone`, `password`, `selfie_path`, `email_verificado`, `token_acesso`, `token_expiracao`, `status`, `codigo_verificacao`, `codigo_expira_em`, `created_at`, `updated_at`) VALUES
(39, 18, NULL, 'lead_conversion', 'Luiz Antonio da Silva', '272.277.478-08', 'Programawp@gmail.com', NULL, '$2y$10$PrT/6BGDQA5auWAH6xFavO.1EvO33IdwDMM6721j217xw8WdwJJSS', 'uploads/selfies/selfie_69079ecb89f6c5.18913072.jpg', 1, NULL, NULL, 'ativo', NULL, NULL, '2025-11-02 18:11:23', '2025-11-02 18:11:44'),
(35, 18, NULL, 'lead_conversion', 'Maria Santos Teste', NULL, 'maria.teste@email.com', '11988888888', '$2y$10$CL0QW0OdWbn5HLwP2O2pUO/CrlnC4nMLcJG53hiymkr/FolkczQBS', NULL, 0, 'f55899eacc9780fee914536b8234e9cccc60e8427fa8509a86ff4aea6e7f5b66', '2025-12-02 15:05:09', 'ativo', NULL, NULL, '2025-11-02 15:04:32', '2025-11-02 15:05:09'),
(34, 1, NULL, 'registro_direto', 'Sergio Henrique Maia Pompeu', '110.880.998-74', 'sergiopompeu.edu@gmail.com', NULL, '$2y$10$jVEKsZzWNhaoGTvF//UzYuEywO0XKwcebOVJ3vRjVGyYZfOSvaeD.', 'uploads/selfies/selfie_6904f8f5dd3e17.57013796.jpg', 1, NULL, NULL, 'ativo', NULL, NULL, '2025-10-31 17:59:17', '2025-11-01 19:10:17'),
(32, 16, NULL, 'registro_direto', 'Maria 2', '272.277.478-08', 'kyc@verify2b.com', NULL, '$2y$10$MHlbtlsonasCVFXHSstqe.V6ccMZwFvSmcdlUy3Drcum43gl3cgw2', 'uploads/selfies/selfie_6902695b61a3d4.84756196.png', 1, NULL, NULL, 'ativo', NULL, NULL, '2025-10-29 19:22:03', '2025-10-31 18:37:51'),
(30, 1, NULL, 'registro_direto', 'Antônia', '272.277.478-08', 'cerdeira.alba@gmail.com', NULL, '$2y$10$TIahE9mjq0W3IbZqz0Vl7Ocnsm.04ykAc.HjEIFs9yl75P/FovIZe', 'uploads/selfies/selfie_6902417fe23cc3.09442602.png', 1, NULL, NULL, 'ativo', NULL, NULL, '2025-10-29 16:31:59', '2025-10-31 16:29:36'),
(28, 16, NULL, 'registro_direto', 'Alba Verify', '272.277.478-08', 'alba@verify.com', NULL, '$2y$10$DlYractsnzqnwBX7KY.vCO9D7vd7eZQUSLciSsAjw3kYAywXrvzZ6', 'uploads//selfies/selfie_68ffab7cb7adc0.82859602.jpg', 1, NULL, NULL, 'ativo', '0ce2a8898e', '2025-10-28 14:27:24', '2025-10-27 17:27:24', '2025-10-31 18:35:38'),
(27, 16, NULL, 'registro_direto', 'ana', '27227747808', 'albacrodrigues@gmail.com', NULL, '$2y$10$8XpuvqzoxqA0wJ43/7CzvenO9HOkMTbf8XriIHyqPRpBInN5OuwSa', NULL, 1, '762ddf251d5ae2063231f85f3055b132be34fb61daeb01465235a44ca0f90782', '2025-12-02 16:01:55', 'ativo', NULL, NULL, '2025-10-25 21:50:58', '2025-11-02 16:01:55');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_kyc_clientes_empresa` (`id_empresa_master`),
  ADD KEY `idx_token_acesso` (`token_acesso`),
  ADD KEY `idx_origem` (`origem`),
  ADD KEY `idx_lead_id` (`lead_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  ADD CONSTRAINT `fk_kyc_clientes_empresa` FOREIGN KEY (`id_empresa_master`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kyc_clientes_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
