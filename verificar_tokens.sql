-- Verificar tokens por empresa
SELECT 
    id as config_id,
    empresa_id,
    nome_empresa,
    CASE 
        WHEN api_token IS NULL THEN '❌ NULL'
        WHEN api_token = '' THEN '❌ VAZIO'
        ELSE CONCAT('✅ ', SUBSTRING(api_token, 1, 20), '...')
    END as token_status,
    api_token_ativo,
    slug
FROM configuracoes_whitelabel
ORDER BY empresa_id;

-- Verificar se há tokens duplicados
SELECT 
    api_token,
    COUNT(*) as qtd_empresas,
    GROUP_CONCAT(nome_empresa SEPARATOR ', ') as empresas
FROM configuracoes_whitelabel
WHERE api_token IS NOT NULL AND api_token != ''
GROUP BY api_token
HAVING COUNT(*) > 1;

-- Ver quantas empresas têm token configurado
SELECT 
    COUNT(*) as total_configs,
    SUM(CASE WHEN api_token IS NOT NULL AND api_token != '' THEN 1 ELSE 0 END) as com_token,
    SUM(CASE WHEN api_token IS NULL OR api_token = '' THEN 1 ELSE 0 END) as sem_token
FROM configuracoes_whitelabel;
