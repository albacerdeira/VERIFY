-- ============================================================
-- FIX RÁPIDO: Corrige empresa_id NULL (Passo a Passo)
-- ============================================================
-- Execute cada bloco separadamente no phpMyAdmin
-- ============================================================

-- PASSO 1: Verifica o problema
-- Copie e execute este SELECT primeiro
SELECT 
    id as config_id,
    nome_empresa,
    empresa_id,
    slug,
    CASE 
        WHEN empresa_id IS NULL THEN '❌ NULL - PRECISA CORRIGIR'
        WHEN empresa_id = 0 THEN '❌ ZERO - PRECISA CORRIGIR'
        ELSE '✅ OK'
    END as status
FROM configuracoes_whitelabel;

-- ============================================================

-- PASSO 2: Tenta associar com empresas existentes
-- Se a empresa já existe na tabela empresas, faz o link
UPDATE configuracoes_whitelabel cw
INNER JOIN empresas e ON LOWER(TRIM(cw.nome_empresa)) = LOWER(TRIM(e.nome))
SET cw.empresa_id = e.id
WHERE cw.empresa_id IS NULL OR cw.empresa_id = 0;

-- ============================================================

-- PASSO 3: Verifica quantos ainda precisam criar
-- Se retornar 0, pule para o PASSO 5
SELECT COUNT(*) as precisa_criar_empresa
FROM configuracoes_whitelabel
WHERE (empresa_id IS NULL OR empresa_id = 0);

-- ============================================================

-- PASSO 4: Cria empresas que não existem
-- APENAS se o PASSO 3 retornou > 0
-- IMPORTANTE: Ajuste o created_by se necessário (use o ID de um admin válido)
INSERT INTO empresas (nome, email, created_by)
SELECT 
    cw.nome_empresa,
    CONCAT('contato@', LOWER(REPLACE(REPLACE(cw.nome_empresa, ' ', ''), '.', '')), '.com.br') as email,
    (SELECT id FROM usuarios WHERE role = 'superadmin' LIMIT 1) as created_by
FROM configuracoes_whitelabel cw
WHERE (cw.empresa_id IS NULL OR cw.empresa_id = 0)
AND cw.nome_empresa NOT IN (SELECT nome FROM empresas)
GROUP BY cw.nome_empresa;

-- ============================================================

-- PASSO 5: Atualiza o link novamente (para as recém-criadas)
UPDATE configuracoes_whitelabel cw
INNER JOIN empresas e ON LOWER(TRIM(cw.nome_empresa)) = LOWER(TRIM(e.nome))
SET cw.empresa_id = e.id
WHERE cw.empresa_id IS NULL OR cw.empresa_id = 0;

-- ============================================================

-- PASSO 6: VERIFICAÇÃO FINAL - Deve retornar 0
SELECT COUNT(*) as ainda_com_problema
FROM configuracoes_whitelabel
WHERE empresa_id IS NULL OR empresa_id = 0;

-- ============================================================

-- PASSO 7: Mostra o resultado final
SELECT 
    cw.id as config_id,
    cw.nome_empresa as config_nome,
    cw.empresa_id,
    e.nome as empresa_nome,
    e.email as empresa_email,
    CASE 
        WHEN cw.empresa_id IS NULL THEN '❌ AINDA NULL'
        WHEN e.id IS NULL THEN '❌ EMPRESA NÃO EXISTE'
        WHEN cw.empresa_id = e.id THEN '✅ OK'
        ELSE '⚠️ INCONSISTENTE'
    END as status
FROM configuracoes_whitelabel cw
LEFT JOIN empresas e ON cw.empresa_id = e.id
ORDER BY status DESC, cw.id;

-- ============================================================
-- Se tudo estiver OK, você pode testar o webhook agora!
-- ============================================================
