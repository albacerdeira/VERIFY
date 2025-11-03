-- Adiciona campo para URL do site da empresa
-- Isso permite que cada empresa teste o script com seus próprios formulários

ALTER TABLE configuracoes_whitelabel 
ADD COLUMN website_url VARCHAR(500) DEFAULT NULL COMMENT 'URL do site da empresa para teste de captura' 
AFTER slug;

-- Atualiza as empresas existentes (opcional - pode ser preenchido manualmente)
-- UPDATE configuracoes_whitelabel SET website_url = 'https://seusite.com.br' WHERE slug = 'b4u';
-- UPDATE configuracoes_whitelabel SET website_url = 'https://verify2b.com' WHERE slug = '2b';
