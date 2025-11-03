-- ============================================
-- CORRIGE STATUS DOS LEADS EXISTENTES
-- ============================================
-- Atualiza status de leads baseado no progresso real do cliente

-- 1. Leads que têm cliente registrado → 'qualificado'
UPDATE leads l
INNER JOIN kyc_clientes kc ON l.id = kc.lead_id
SET l.status = 'qualificado'
WHERE l.status IN ('novo', 'contatado')
AND kc.email_verificado = 1;

-- 2. Leads que têm KYC iniciado ou completo → 'convertido'
UPDATE leads l
INNER JOIN kyc_clientes kc ON l.id = kc.lead_id
INNER JOIN kyc_empresas ke ON kc.id = ke.cliente_id
SET l.status = 'convertido'
WHERE l.status != 'perdido';

-- 3. Leads com KYC reprovado → 'perdido'
UPDATE leads l
INNER JOIN kyc_clientes kc ON l.id = kc.lead_id
INNER JOIN kyc_empresas ke ON kc.id = ke.cliente_id
SET l.status = 'perdido'
WHERE ke.status = 'Reprovado';

-- 4. Mostra resultado
SELECT 
    l.id,
    l.nome,
    l.email,
    l.status as status_lead,
    kc.id as cliente_id,
    kc.nome_completo as cliente_nome,
    kc.email_verificado,
    ke.id as kyc_id,
    ke.razao_social,
    ke.status as status_kyc,
    CASE
        WHEN ke.status = 'Reprovado' THEN '❌ Deveria ser: perdido'
        WHEN ke.id IS NOT NULL THEN '✅ Deveria ser: convertido'
        WHEN kc.email_verificado = 1 THEN '✅ Deveria ser: qualificado'
        ELSE '✅ Status correto'
    END as verificacao
FROM leads l
LEFT JOIN kyc_clientes kc ON l.id = kc.lead_id
LEFT JOIN kyc_empresas ke ON kc.id = ke.cliente_id
WHERE kc.id IS NOT NULL
ORDER BY l.id DESC;

SELECT '✅ Status dos leads corrigidos!' as Status;
