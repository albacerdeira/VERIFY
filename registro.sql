-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 31/10/2025 às 17:17
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
-- Estrutura para tabela `configuracoes_whitelabel`
--

CREATE TABLE `configuracoes_whitelabel` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `google_tag_manager_id` varchar(255) DEFAULT NULL,
  `rd_station_token` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nome_empresa` varchar(255) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `fonte_google` varchar(255) DEFAULT NULL,
  `cor_variavel` varchar(50) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT '-3:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_clientes`
--

CREATE TABLE `kyc_clientes` (
  `id` int(11) NOT NULL,
  `id_empresa_master` int(11) DEFAULT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `selfie_path` varchar(255) DEFAULT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ativo','inativo','pendente') NOT NULL DEFAULT 'pendente',
  `codigo_verificacao` varchar(10) DEFAULT NULL,
  `codigo_expira_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_empresas`
--

CREATE TABLE `kyc_empresas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `cnpj` varchar(18) NOT NULL,
  `razao_social` varchar(255) NOT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `data_constituicao` date DEFAULT NULL,
  `cep` varchar(9) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` varchar(2) DEFAULT NULL,
  `cnae_fiscal` varchar(10) DEFAULT NULL,
  `cnae_fiscal_descricao` text DEFAULT NULL,
  `identificador_matriz_filial` varchar(10) DEFAULT NULL,
  `situacao_cadastral` varchar(50) DEFAULT NULL,
  `descricao_motivo_situacao_cadastral` text DEFAULT NULL,
  `porte` varchar(50) DEFAULT NULL,
  `natureza_juridica` varchar(100) DEFAULT NULL,
  `opcao_pelo_simples` varchar(20) DEFAULT NULL,
  `representante_legal` varchar(255) DEFAULT NULL,
  `email_contato` varchar(255) DEFAULT NULL,
  `ddd_telefone_1` varchar(20) DEFAULT NULL,
  `observacoes_empresa` text DEFAULT NULL,
  `atividade_principal` text DEFAULT NULL,
  `motivo_abertura_conta` text DEFAULT NULL,
  `fluxo_financeiro_pretendido` text DEFAULT NULL,
  `moedas_operar` text DEFAULT NULL,
  `blockchains_operar` text DEFAULT NULL,
  `volume_mensal_pretendido` varchar(100) DEFAULT NULL,
  `origem_fundos` varchar(50) DEFAULT NULL,
  `descricao_fundos_terceiros` text DEFAULT NULL,
  `consentimento_termos` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'Em Preenchimento',
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `check_listas_restritivas` tinyint(1) DEFAULT 0,
  `check_midia_negativa` tinyint(1) DEFAULT 0,
  `check_processos_judiciais` tinyint(1) DEFAULT 0,
  `analise_decisao_final` varchar(50) DEFAULT NULL,
  `id_empresa_master` int(11) DEFAULT NULL,
  `id_cliente_vinculado` int(11) DEFAULT NULL,
  `data_submissao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Índices de tabela `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_kyc_clientes_empresa` (`id_empresa_master`);

--
-- Índices de tabela `kyc_empresas`
--
ALTER TABLE `kyc_empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_id_empresa_master` (`id_empresa_master`),
  ADD KEY `idx_cliente_id` (`cliente_id`),
  ADD KEY `fk_cliente_vinculado` (`id_cliente_vinculado`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_empresas`
--
ALTER TABLE `kyc_empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  ADD CONSTRAINT `configuracoes_whitelabel_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);

--
-- Restrições para tabelas `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  ADD CONSTRAINT `fk_kyc_clientes_empresa` FOREIGN KEY (`id_empresa_master`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `kyc_empresas`
--
ALTER TABLE `kyc_empresas`
  ADD CONSTRAINT `fk_cliente_id` FOREIGN KEY (`cliente_id`) REFERENCES `kyc_clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cliente_vinculado` FOREIGN KEY (`id_cliente_vinculado`) REFERENCES `kyc_clientes` (`id`),
  ADD CONSTRAINT `fk_id_empresa_master` FOREIGN KEY (`id_empresa_master`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kyc_empresas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `kyc_clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
