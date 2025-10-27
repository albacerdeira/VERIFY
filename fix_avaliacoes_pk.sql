-- Garante que a tabela kyc_avaliacoes use o kyc_empresa_id como chave primária.
-- Se já for a chave primária, este comando pode dar um erro, o que é normal e pode ser ignorado.
ALTER TABLE `kyc_avaliacoes` DROP PRIMARY KEY, ADD PRIMARY KEY (`kyc_empresa_id`);
