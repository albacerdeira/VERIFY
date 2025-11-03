-- ============================================
-- ASSOCIA CLIENTES EXISTENTES AOS SEUS LEADS
-- ============================================
-- Este script vincula clientes que foram criados a partir de leads
-- mas que não tiveram o lead_id capturado no momento do registro

-- 1. Mostra situação atual
SELECT 
    'ANTES DA ASSOCIAÇÃO' as etapa,
    COUNT(*) as total_clientes,
    COUNT(lead_id) as com_lead_id,
    COUNT(*) - COUNT(lead_id) as sem_lead_id
FROM kyc_clientes;

-- 2. Mostra possíveis matches entre clientes e leads (por email E mesma empresa)
SELECT 
    kc.id as cliente_id,
    kc.nome_completo as cliente_nome,
    kc.email as cliente_email,
    kc.id_empresa_master as cliente_empresa_id,
    kc.lead_id as lead_id_atual,
    kc.origem,
    l.id as lead_encontrado_id,
    l.nome as lead_nome,
    l.email as lead_email,
    l.id_empresa_master as lead_empresa_id,
    l.status as lead_status,
    CASE 
        WHEN kc.lead_id IS NULL AND l.id IS NOT NULL AND kc.id_empresa_master = l.id_empresa_master THEN 'PODE ASSOCIAR ✅'
        WHEN kc.lead_id IS NULL AND l.id IS NOT NULL AND kc.id_empresa_master != l.id_empresa_master THEN 'EMPRESA DIFERENTE ⚠️'
        WHEN kc.lead_id = l.id THEN 'JÁ ASSOCIADO'
        WHEN kc.lead_id IS NOT NULL AND kc.lead_id != l.id THEN 'CONFLITO'
        ELSE 'SEM LEAD CORRESPONDENTE'
    END as situacao
FROM kyc_clientes kc
LEFT JOIN leads l ON LOWER(TRIM(kc.email)) COLLATE utf8mb4_general_ci = LOWER(TRIM(l.email)) COLLATE utf8mb4_general_ci
ORDER BY kc.id DESC;

-- 3. EXECUTA A ASSOCIAÇÃO (somente mesma empresa)
-- Associa clientes aos leads pelo email E mesma empresa whitelabel
UPDATE kyc_clientes kc
INNER JOIN leads l ON 
    LOWER(TRIM(kc.email)) COLLATE utf8mb4_general_ci = LOWER(TRIM(l.email)) COLLATE utf8mb4_general_ci
    AND kc.id_empresa_master = l.id_empresa_master
SET 
    kc.lead_id = l.id,
    kc.origem = 'lead_conversion'
WHERE kc.lead_id IS NULL;

-- 4. Mostra resultado
SELECT 
    'APÓS A ASSOCIAÇÃO' as etapa,
    COUNT(*) as total_clientes,
    COUNT(lead_id) as com_lead_id,
    COUNT(*) - COUNT(lead_id) as sem_lead_id
FROM kyc_clientes;

-- 5. Lista clientes que foram associados
SELECT 
    kc.id as cliente_id,
    kc.nome_completo,
    kc.email,
    kc.lead_id,
    kc.origem,
    l.nome as lead_nome,
    l.status as lead_status,
    kc.created_at as cliente_criado_em,
    l.created_at as lead_criado_em
FROM kyc_clientes kc
INNER JOIN leads l ON kc.lead_id = l.id
WHERE kc.origem = 'lead_conversion'
ORDER BY kc.created_at DESC;

-- 6. Verifica quantos KYCs agora estão vinculados a leads
SELECT 
    'KYCs vinculados a leads' as info,
    COUNT(DISTINCT ke.id) as total_kycs,
    COUNT(DISTINCT kc.lead_id) as kycs_com_lead
FROM kyc_empresas ke
INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
WHERE kc.lead_id IS NOT NULL;

SELECT '✅ Associação concluída! Agora teste o lead_detail.php' as Status;
