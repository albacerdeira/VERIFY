ALTER TABLE `kyc_avaliacoes`
ADD COLUMN `av_check_socios_ubos_origin` VARCHAR(10) NULL DEFAULT 'analyst' COMMENT 'Indica se o check de UBOs foi feito pelo sistema ou analista' AFTER `av_check_socios_ubos_ok`;
