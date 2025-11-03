-- ============================================
-- VERIFICA DUPLICAÇÃO DE KYCs DO MESMO CLIENTE
-- ============================================

-- 1. Clientes com múltiplos KYCs
SELECT 
    kc.id as cliente_id,
    kc.nome_completo,
    kc.email,
    COUNT(ke.id) as total_kycs,
    GROUP_CONCAT(ke.razao_social SEPARATOR ' | ') as empresas,
    GROUP_CONCAT(ke.status SEPARATOR ' | ') as status_kycs
FROM kyc_clientes kc
INNER JOIN kyc_empresas ke ON kc.id = ke.cliente_id
GROUP BY kc.id
HAVING COUNT(ke.id) > 1
ORDER BY total_kycs DESC;

-- 2. Detalhes dos KYCs do Luiz (lead #24)
SELECT 
    ke.id as kyc_id,
    ke.razao_social,
    ke.cnpj,
    ke.status,
    ke.data_criacao,
    ke.data_atualizacao,
    kc.nome_completo as cliente_nome,
    kc.lead_id
FROM kyc_empresas ke
INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
WHERE kc.email = 'Programawp@gmail.com'
ORDER BY ke.data_criacao DESC;

-- 3. KYCs "Em Preenchimento" (incompletos)
SELECT 
    ke.id,
    ke.razao_social,
    ke.cnpj,
    ke.status,
    kc.nome_completo as cliente,
    kc.email,
    ke.data_criacao,
    TIMESTAMPDIFF(DAY, ke.data_criacao, NOW()) as dias_parado
FROM kyc_empresas ke
INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
WHERE ke.status = 'Em Preenchimento'
ORDER BY ke.data_criacao DESC;

-- 4. Sugestão: KYCs abandonados há mais de 7 dias
SELECT 
    'KYCs abandonados (>7 dias sem atualização)' as info,
    COUNT(*) as total
FROM kyc_empresas ke
WHERE ke.status = 'Em Preenchimento'
AND TIMESTAMPDIFF(DAY, ke.data_atualizacao, NOW()) > 7;
