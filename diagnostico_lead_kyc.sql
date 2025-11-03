-- ============================================
-- DIAGNÓSTICO: Lead → Cliente → KYC
-- ============================================
-- Use este script para investigar o fluxo completo
-- de um lead específico até seu KYC
-- ============================================

-- PASSO 1: Verificar dados do lead
SELECT 
    '=== LEAD ===' as tipo,
    l.id as lead_id,
    l.nome,
    l.email,
    l.whatsapp,
    l.empresa as empresa_lead,
    l.status as lead_status,
    l.id_empresa_master,
    l.data_criacao as lead_criado_em
FROM leads l
WHERE l.email = 'EMAIL_DO_CLIENTE_AQUI'  -- SUBSTITUA pelo email real
   OR l.id = 999;  -- OU SUBSTITUA pelo ID do lead

-- PASSO 2: Verificar se o lead se converteu em cliente
SELECT 
    '=== CLIENTE ===' as tipo,
    kc.id as cliente_id,
    kc.nome_completo,
    kc.email,
    kc.cpf,
    kc.status as cliente_status,
    kc.email_verificado,
    kc.lead_id,  -- Deve apontar para o lead
    kc.origem,   -- Deve ser 'lead_conversion'
    kc.id_empresa_master as empresa_id,
    kc.created_at as cliente_criado_em
FROM kyc_clientes kc
WHERE kc.email = 'EMAIL_DO_CLIENTE_AQUI'  -- SUBSTITUA pelo email real
   OR kc.lead_id = 999;  -- OU SUBSTITUA pelo ID do lead

-- PASSO 3: Verificar se o cliente preencheu KYC
SELECT 
    '=== KYC EMPRESA ===' as tipo,
    ke.id as kyc_id,
    ke.cliente_id,
    ke.razao_social,
    ke.cnpj,
    ke.status as kyc_status,
    ke.id_empresa_master as empresa_id,
    ke.data_criacao as kyc_criado_em,
    ke.data_atualizacao as kyc_atualizado_em
FROM kyc_empresas ke
WHERE ke.cliente_id IN (
    SELECT kc.id 
    FROM kyc_clientes kc 
    WHERE kc.email = 'EMAIL_DO_CLIENTE_AQUI'  -- SUBSTITUA pelo email real
);

-- PASSO 4: Verificar histórico do lead
SELECT 
    '=== HISTÓRICO ===' as tipo,
    lh.id,
    lh.lead_id,
    lh.usuario_id,
    lh.acao,
    lh.descricao,
    lh.created_at,
    u.nome as usuario_nome
FROM leads_historico lh
LEFT JOIN usuarios u ON lh.usuario_id = u.id
WHERE lh.lead_id = 999  -- SUBSTITUA pelo ID do lead
ORDER BY lh.created_at DESC;

-- ============================================
-- ANÁLISE DO PROBLEMA
-- ============================================

-- 1. Verifica se existem clientes sem lead_id
SELECT 
    'CLIENTES SEM LEAD_ID' as problema,
    COUNT(*) as total,
    GROUP_CONCAT(id) as cliente_ids
FROM kyc_clientes
WHERE lead_id IS NULL 
AND origem = 'lead_conversion';

-- 2. Verifica se existem KYCs com id_empresa_master diferente do lead
SELECT 
    'EMPRESA DIFERENTE' as problema,
    l.id as lead_id,
    l.id_empresa_master as lead_empresa,
    kc.id as cliente_id,
    kc.id_empresa_master as cliente_empresa,
    ke.id as kyc_id,
    ke.id_empresa_master as kyc_empresa
FROM leads l
INNER JOIN kyc_clientes kc ON kc.lead_id = l.id
LEFT JOIN kyc_empresas ke ON ke.cliente_id = kc.id
WHERE l.id_empresa_master != kc.id_empresa_master
   OR (ke.id IS NOT NULL AND l.id_empresa_master != ke.id_empresa_master);

-- 3. Conta KYCs por status (deve aparecer no dashboard)
SELECT 
    'CONTAGEM POR STATUS' as tipo,
    status,
    COUNT(*) as total
FROM kyc_empresas
GROUP BY status
ORDER BY 
    CASE status
        WHEN 'Novo Registro' THEN 1
        WHEN 'Em Análise' THEN 2
        WHEN 'Pendenciado' THEN 3
        WHEN 'Aprovado' THEN 4
        WHEN 'Reprovado' THEN 5
        ELSE 6
    END;

-- 4. Verifica clientes que se registraram mas não preencheram KYC
SELECT 
    'CLIENTES SEM KYC' as problema,
    kc.id,
    kc.nome_completo,
    kc.email,
    kc.created_at,
    kc.lead_id,
    kc.origem
FROM kyc_clientes kc
LEFT JOIN kyc_empresas ke ON ke.cliente_id = kc.id
WHERE ke.id IS NULL
ORDER BY kc.created_at DESC
LIMIT 10;

-- ============================================
-- CORREÇÕES AUTOMÁTICAS (USE COM CUIDADO!)
-- ============================================

-- APENAS SE NECESSÁRIO: Atualiza origem de clientes sem lead_id
-- UPDATE kyc_clientes 
-- SET origem = 'registro_direto' 
-- WHERE origem IS NULL;

-- APENAS SE NECESSÁRIO: Atualiza lead_id se você souber a associação correta
-- UPDATE kyc_clientes 
-- SET lead_id = 123  -- ID do lead correto
-- WHERE id = 456;    -- ID do cliente

-- ============================================
-- FIM DO DIAGNÓSTICO
-- ============================================
