-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 01/11/2025 às 01:39
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
-- Estrutura para tabela `ceis`
--

CREATE TABLE `ceis` (
  `id` int(11) NOT NULL,
  `codigo_sancao` varchar(50) NOT NULL,
  `tipo_pessoa` char(1) DEFAULT NULL,
  `cpf_cnpj_sancionado` varchar(20) DEFAULT NULL,
  `nome_sancionado` varchar(255) DEFAULT NULL,
  `nome_informado_orgao` varchar(255) DEFAULT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `numero_processo` varchar(50) DEFAULT NULL,
  `categoria_sancao` varchar(255) DEFAULT NULL,
  `data_inicio_sancao` date DEFAULT NULL,
  `data_final_sancao` date DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL,
  `publicacao` varchar(255) DEFAULT NULL,
  `detalhamento_publicacao` varchar(255) DEFAULT NULL,
  `data_transito_julgado` date DEFAULT NULL,
  `abrangencia_sancao` varchar(255) DEFAULT NULL,
  `orgao_sancionador` varchar(255) DEFAULT NULL,
  `uf_orgao_sancionador` char(2) DEFAULT NULL,
  `esfera_orgao_sancionador` varchar(50) DEFAULT NULL,
  `fundamentacao_legal` text DEFAULT NULL,
  `data_origem_informacao` date DEFAULT NULL,
  `origem_informacoes` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `cadastro_origem` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cnep`
--

CREATE TABLE `cnep` (
  `id` int(11) NOT NULL,
  `cadastro_origem` varchar(50) DEFAULT NULL,
  `codigo_sancao` varchar(100) NOT NULL,
  `tipo_pessoa` char(1) DEFAULT NULL,
  `cpf_cnpj_sancionado` varchar(20) DEFAULT NULL,
  `nome_sancionado` varchar(255) DEFAULT NULL,
  `nome_informado_orgao` varchar(255) DEFAULT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `numero_processo` varchar(100) DEFAULT NULL,
  `categoria_sancao` varchar(255) DEFAULT NULL,
  `valor_da_multa` decimal(15,2) DEFAULT 0.00,
  `data_inicio_sancao` date DEFAULT NULL,
  `data_final_sancao` date DEFAULT NULL,
  `data_publicacao` date DEFAULT NULL,
  `publicacao` text DEFAULT NULL,
  `detalhamento_publicacao` text DEFAULT NULL,
  `data_transito_julgado` date DEFAULT NULL,
  `abrangencia_sancao` varchar(255) DEFAULT NULL,
  `orgao_sancionador` varchar(255) DEFAULT NULL,
  `uf_orgao_sancionador` char(2) DEFAULT NULL,
  `esfera_orgao_sancionador` varchar(50) DEFAULT NULL,
  `fundamentacao_legal` text DEFAULT NULL,
  `data_origem_informacao` date DEFAULT NULL,
  `origem_informacoes` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
-- Estrutura para tabela `consultas`
--

CREATE TABLE `consultas` (
  `id` int(11) NOT NULL,
  `cnpj` varchar(20) NOT NULL,
  `dados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`dados`)),
  `usuario_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `uf` varchar(2) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `qsa` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qsa`)),
  `pais` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `porte` varchar(50) DEFAULT NULL,
  `bairro` varchar(255) DEFAULT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `ddd_fax` varchar(10) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `cnae_fiscal` varchar(20) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `razao_social` varchar(255) DEFAULT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `capital_social` decimal(15,2) DEFAULT NULL,
  `ddd_telefone_1` varchar(15) DEFAULT NULL,
  `ddd_telefone_2` varchar(15) DEFAULT NULL,
  `natureza_juridica` varchar(255) DEFAULT NULL,
  `cnaes_secundarios` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cnaes_secundarios`)),
  `regime_tributario` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`regime_tributario`)),
  `situacao_especial` varchar(255) DEFAULT NULL,
  `situacao_cadastral` int(11) DEFAULT NULL,
  `data_inicio_atividade` date DEFAULT NULL,
  `data_situacao_cadastral` date DEFAULT NULL,
  `codigo_natureza_juridica` int(11) DEFAULT NULL,
  `identificador_matriz_filial` int(11) DEFAULT NULL,
  `descricao_situacao_cadastral` varchar(255) DEFAULT NULL,
  `descricao_tipo_de_logradouro` varchar(255) DEFAULT NULL,
  `descricao_identificador_matriz_filial` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_avaliacoes`
--

CREATE TABLE `kyc_avaliacoes` (
  `id` int(11) NOT NULL,
  `kyc_empresa_id` int(11) NOT NULL,
  `av_analista_id` int(11) DEFAULT NULL,
  `av_check_dados_empresa_ok` tinyint(1) DEFAULT 0,
  `av_check_perfil_negocio_ok` tinyint(1) DEFAULT 0,
  `av_check_socios_ubos_ok` tinyint(1) DEFAULT 0,
  `av_check_socios_ubos_origin` varchar(10) DEFAULT 'analyst' COMMENT 'Indica se o check de UBOs foi feito pelo sistema ou analista',
  `av_check_documentos_ok` tinyint(1) DEFAULT 0,
  `av_check_ceis_ok` tinyint(1) DEFAULT NULL COMMENT 'Resultado da verificação no CEIS (1=OK, 0=Sanção encontrada)',
  `av_check_ceis_pf_ok` tinyint(1) DEFAULT NULL COMMENT 'Resultado CEIS Sócios (1=OK, 0=Sanção)',
  `av_check_cnep_ok` tinyint(1) DEFAULT NULL COMMENT 'Resultado CNEP PJ (1=OK, 0=Sanção)',
  `av_check_cnep_pf_ok` tinyint(1) DEFAULT NULL COMMENT 'Resultado CNEP Sócios (1=OK, 0=Sanção)',
  `av_obs_dados_empresa` text DEFAULT NULL,
  `av_check_perfil_atividade` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_perfil_motivo` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_perfil_fluxo` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_perfil_volume` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_perfil_origem` tinyint(1) NOT NULL DEFAULT 0,
  `av_obs_perfil_negocio` text DEFAULT NULL,
  `av_check_doc_contrato_social` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_doc_ultima_alteracao` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_doc_cartao_cnpj` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_doc_balanco` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_doc_balancete` tinyint(1) NOT NULL DEFAULT 0,
  `av_check_doc_dirpj` tinyint(1) NOT NULL DEFAULT 0,
  `av_obs_documentos` text DEFAULT NULL,
  `av_data_avaliacao` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `av_check_docs_empresa_ok` tinyint(1) DEFAULT 0,
  `av_check_docs_socios_ok` tinyint(1) DEFAULT 0,
  `av_check_estrutura_societaria_ok` tinyint(1) DEFAULT 0,
  `av_anotacoes_internas` text DEFAULT NULL,
  `av_risco_atividade` varchar(20) DEFAULT NULL,
  `av_risco_geografico` varchar(20) DEFAULT NULL,
  `av_risco_societario` varchar(20) DEFAULT NULL,
  `av_risco_midia_pep` varchar(20) DEFAULT NULL,
  `av_risco_final` varchar(20) DEFAULT NULL,
  `av_justificativa_risco` text DEFAULT NULL,
  `av_info_pendencia` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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
-- Estrutura para tabela `kyc_cnaes_secundarios`
--

CREATE TABLE `kyc_cnaes_secundarios` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `cnae` varchar(10) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_documentos`
--

CREATE TABLE `kyc_documentos` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `socio_id` int(11) DEFAULT NULL,
  `tipo_documento` varchar(100) NOT NULL,
  `path_arquivo` varchar(255) NOT NULL,
  `nome_arquivo` varchar(255) DEFAULT NULL,
  `data_upload` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

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

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_logs`
--

CREATE TABLE `kyc_logs` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(255) NOT NULL,
  `detalhes` text DEFAULT NULL,
  `data_ocorrencia` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_log_atividades`
--

CREATE TABLE `kyc_log_atividades` (
  `id` int(11) NOT NULL,
  `kyc_empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nome` varchar(255) NOT NULL,
  `acao` text NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `dados_avaliacao_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_avaliacao_snapshot`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `kyc_socios`
--

CREATE TABLE `kyc_socios` (
  `id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `cpf_cnpj` varchar(18) NOT NULL,
  `qualificacao_cargo` varchar(100) DEFAULT NULL,
  `percentual_participacao` decimal(5,2) DEFAULT NULL,
  `cep` varchar(9) DEFAULT NULL,
  `logradouro` varchar(255) DEFAULT NULL,
  `numero` varchar(50) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` varchar(2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `is_pep` tinyint(1) DEFAULT 0,
  `dados_validados` tinyint(1) DEFAULT 0,
  `av_socio_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `av_socio_observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `peps`
--

CREATE TABLE `peps` (
  `id` int(11) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `nome_pep` varchar(255) NOT NULL,
  `sigla_funcao` varchar(50) DEFAULT NULL,
  `descricao_funcao` varchar(255) DEFAULT NULL,
  `nivel_funcao` varchar(50) DEFAULT NULL,
  `nome_orgao` varchar(255) DEFAULT NULL,
  `data_inicio_exercicio` date DEFAULT NULL,
  `data_fim_exercicio` date DEFAULT NULL,
  `data_fim_carencia` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `superadmin`
--

CREATE TABLE `superadmin` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) CHARACTER SET armscii8 COLLATE armscii8_general_ci NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `ceis`
--
ALTER TABLE `ceis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_codigo_sancao` (`codigo_sancao`);

--
-- Índices de tabela `cnep`
--
ALTER TABLE `cnep`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codigo_sancao` (`codigo_sancao`);

--
-- Índices de tabela `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Índices de tabela `consultas`
--
ALTER TABLE `consultas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `empresas_ibfk_1` (`created_by`);

--
-- Índices de tabela `kyc_avaliacoes`
--
ALTER TABLE `kyc_avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_kyc_empresa_id` (`kyc_empresa_id`);

--
-- Índices de tabela `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_kyc_clientes_empresa` (`id_empresa_master`);

--
-- Índices de tabela `kyc_cnaes_secundarios`
--
ALTER TABLE `kyc_cnaes_secundarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Índices de tabela `kyc_documentos`
--
ALTER TABLE `kyc_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `socio_id` (`socio_id`);

--
-- Índices de tabela `kyc_empresas`
--
ALTER TABLE `kyc_empresas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_id_empresa_master` (`id_empresa_master`),
  ADD KEY `idx_cliente_id` (`cliente_id`),
  ADD KEY `fk_cliente_vinculado` (`id_cliente_vinculado`);

--
-- Índices de tabela `kyc_logs`
--
ALTER TABLE `kyc_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- Índices de tabela `kyc_log_atividades`
--
ALTER TABLE `kyc_log_atividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kyc_empresa_id` (`kyc_empresa_id`);

--
-- Índices de tabela `kyc_socios`
--
ALTER TABLE `kyc_socios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `empresa_id` (`empresa_id`,`cpf_cnpj`);

--
-- Índices de tabela `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token` (`token`(191));

--
-- Índices de tabela `peps`
--
ALTER TABLE `peps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cpf` (`cpf`);

--
-- Índices de tabela `superadmin`
--
ALTER TABLE `superadmin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `empresa_id` (`empresa_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `ceis`
--
ALTER TABLE `ceis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cnep`
--
ALTER TABLE `cnep`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `consultas`
--
ALTER TABLE `consultas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_avaliacoes`
--
ALTER TABLE `kyc_avaliacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_cnaes_secundarios`
--
ALTER TABLE `kyc_cnaes_secundarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_documentos`
--
ALTER TABLE `kyc_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_empresas`
--
ALTER TABLE `kyc_empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_logs`
--
ALTER TABLE `kyc_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_log_atividades`
--
ALTER TABLE `kyc_log_atividades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `kyc_socios`
--
ALTER TABLE `kyc_socios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `peps`
--
ALTER TABLE `peps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `superadmin`
--
ALTER TABLE `superadmin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
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
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `superadmin` (`id`);

--
-- Restrições para tabelas `kyc_avaliacoes`
--
ALTER TABLE `kyc_avaliacoes`
  ADD CONSTRAINT `kyc_avaliacoes_ibfk_1` FOREIGN KEY (`kyc_empresa_id`) REFERENCES `kyc_empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `kyc_clientes`
--
ALTER TABLE `kyc_clientes`
  ADD CONSTRAINT `fk_kyc_clientes_empresa` FOREIGN KEY (`id_empresa_master`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `kyc_cnaes_secundarios`
--
ALTER TABLE `kyc_cnaes_secundarios`
  ADD CONSTRAINT `kyc_cnaes_secundarios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `kyc_empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `kyc_documentos`
--
ALTER TABLE `kyc_documentos`
  ADD CONSTRAINT `kyc_documentos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `kyc_empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `kyc_documentos_ibfk_2` FOREIGN KEY (`socio_id`) REFERENCES `kyc_socios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `kyc_empresas`
--
ALTER TABLE `kyc_empresas`
  ADD CONSTRAINT `fk_cliente_id` FOREIGN KEY (`cliente_id`) REFERENCES `kyc_clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cliente_vinculado` FOREIGN KEY (`id_cliente_vinculado`) REFERENCES `kyc_clientes` (`id`),
  ADD CONSTRAINT `fk_id_empresa_master` FOREIGN KEY (`id_empresa_master`) REFERENCES `empresas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_kyc_empresas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `kyc_clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Restrições para tabelas `kyc_logs`
--
ALTER TABLE `kyc_logs`
  ADD CONSTRAINT `kyc_logs_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `kyc_empresas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `kyc_log_atividades`
--
ALTER TABLE `kyc_log_atividades`
  ADD CONSTRAINT `kyc_log_atividades_ibfk_1` FOREIGN KEY (`kyc_empresa_id`) REFERENCES `kyc_empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `kyc_socios`
--
ALTER TABLE `kyc_socios`
  ADD CONSTRAINT `kyc_socios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `kyc_empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
