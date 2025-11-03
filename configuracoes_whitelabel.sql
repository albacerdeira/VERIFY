-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 02/11/2025 às 20:08
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
  `timezone` varchar(50) DEFAULT '-3:00',
  `api_token` varchar(64) DEFAULT NULL,
  `api_token_ativo` tinyint(1) DEFAULT 1,
  `api_rate_limit` int(11) DEFAULT 100 COMMENT 'Máximo de leads por hora',
  `api_ultimo_uso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracoes_whitelabel`
--

INSERT INTO `configuracoes_whitelabel` (`id`, `empresa_id`, `google_tag_manager_id`, `rd_station_token`, `created_at`, `updated_at`, `nome_empresa`, `slug`, `logo_url`, `fonte_google`, `cor_variavel`, `timezone`, `api_token`, `api_token_ativo`, `api_rate_limit`, `api_ultimo_uso`) VALUES
(11, 16, 'GTM-M4GTXF6V', NULL, '2025-10-19 19:29:06', '2025-11-02 00:19:15', 'B4U Soluções', 'b4u', 'uploads/logos/6900c53b4bf17-Logo-b4ut.png', NULL, '#ecac31', '-3:00', '3436b616e02c7fd435bc3d8ac555ba6a3b793380b0382cfbad7974b90b1f0f11', 1, 100, NULL),
(12, 1, '', NULL, '2025-10-31 23:35:48', '2025-11-02 00:19:15', 'Verify', '2b', 'imagens/verify-kyc.png', NULL, '#1c305c', '-3:00', '113b95a21cfb131f9e0311c3ec4d8ea929ec9c97b619486e8683c12b89a123de', 1, 100, NULL),
(13, 18, '', NULL, '2025-11-01 23:09:45', '2025-11-02 15:03:38', 'Forma e conteudo', 'fconteudo', 'uploads/logos/690693ae1469f-Cópia-de-forma-e-conteúdo3.jpg', NULL, '#ea9fa0', '-3:00', '0d342ebaa87c9a8d9524b2fbfb3152141f3954b79b52f94ce5183d5523d87090', 1, 100, '2025-11-02 15:03:38'),
(14, 19, '', NULL, '2025-11-02 00:05:35', '2025-11-02 00:19:15', 'Fdbank', 'fdbank', 'uploads/logos/6906a0bd8a5b9-Verde vibrante.svg', NULL, '#17654b', '-3:00', 'fbaea34cd6753d3664ec17a3b1712f21f52bef5f3c5b106ae386965a0ebbf396', 1, 100, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `empresa_id` (`empresa_id`),
  ADD KEY `idx_api_token` (`api_token`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `configuracoes_whitelabel`
--
ALTER TABLE `configuracoes_whitelabel`
  ADD CONSTRAINT `configuracoes_whitelabel_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
