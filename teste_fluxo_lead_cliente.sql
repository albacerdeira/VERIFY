-- ============================================
-- TESTE: Verificar associação Lead → Cliente
-- ============================================

-- 1. Mostra os últimos leads criados
SELECT *
FROM leads
ORDER BY id DESC
LIMIT 5;

-- 2. Mostra clientes recentes e seus leads
SELECT 
    kc.id as cliente_id,
    kc.nome_completo,
    kc.email,
    kc.lead_id,
    kc.origem,
    kc.email_verificado,
    kc.created_at as cliente_criado_em,
    l.id as lead_info_id,
    l.nome as lead_nome,
    l.status as lead_status
FROM kyc_clientes kc
LEFT JOIN leads l ON kc.lead_id = l.id
ORDER BY kc.id DESC
LIMIT 10;

-- 3. Conta leads x clientes
SELECT 
    'Leads' as tipo,
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'novo' THEN 1 END) as novos,
    COUNT(CASE WHEN status = 'contatado' THEN 1 END) as contatados,
    COUNT(CASE WHEN status = 'qualificado' THEN 1 END) as qualificados
FROM leads

UNION ALL

SELECT 
    'Clientes' as tipo,
    COUNT(*) as total,
    COUNT(CASE WHEN origem = 'registro_direto' THEN 1 END) as registro_direto,
    COUNT(CASE WHEN origem = 'lead_conversion' THEN 1 END) as lead_conversion,
    COUNT(lead_id) as com_lead_associado
FROM kyc_clientes;

-- 4. Verifica se há KYCs preenchidos
SELECT 
    ke.id as kyc_id,
    ke.razao_social,
    ke.cnpj,
    ke.status,
    kc.nome_completo as cliente_nome,
    kc.lead_id,
    kc.origem,
    ke.data_criacao
FROM kyc_empresas ke
INNER JOIN kyc_clientes kc ON ke.cliente_id = kc.id
ORDER BY ke.data_criacao DESC
LIMIT 5;
