-- ============================================================
-- FIX: Corrige empresa_id na tabela configuracoes_whitelabel
-- ============================================================
-- 
-- PROBLEMA: O campo empresa_id pode estar NULL, causando erro
-- de foreign key ao inserir leads.
--
-- SOLUÇÃO: Este script corrige o empresa_id baseado no nome
-- da empresa ou cria um registro na tabela empresas.
-- ============================================================

-- Verifica quantos registros estão com empresa_id NULL
SELECT 
    id,
    nome_empresa,
    empresa_id,
    slug
FROM configuracoes_whitelabel 
WHERE empresa_id IS NULL OR empresa_id = 0;

-- Se houver registros com empresa_id NULL, vamos corrigir:

-- Opção 1: Se a empresa JÁ EXISTE na tabela empresas, associa pelo nome
UPDATE configuracoes_whitelabel cw
INNER JOIN empresas e ON LOWER(cw.nome_empresa) = LOWER(e.nome)
SET cw.empresa_id = e.id
WHERE cw.empresa_id IS NULL OR cw.empresa_id = 0;

-- Opção 2: Se a empresa NÃO EXISTE, cria na tabela empresas primeiro
-- (Execute este bloco apenas se necessário)

-- Para cada registro sem empresa_id:
-- 1. Cria empresa na tabela empresas
-- NOTA: A tabela empresas tem: id, nome, email, created_by, created_at
-- Vamos usar o email como 'contato@' + slug se existir
INSERT INTO empresas (nome, email, created_by)
SELECT 
    nome_empresa,
    CONCAT('contato@', COALESCE(slug, LOWER(REPLACE(nome_empresa, ' ', ''))), '.com.br'),
    1 -- ID do usuário criador (ajuste conforme necessário)
FROM configuracoes_whitelabel
WHERE (empresa_id IS NULL OR empresa_id = 0)
AND nome_empresa NOT IN (SELECT nome FROM empresas)
GROUP BY nome_empresa;

-- 2. Atualiza o empresa_id na configuracoes_whitelabel
UPDATE configuracoes_whitelabel cw
INNER JOIN empresas e ON LOWER(cw.nome_empresa) = LOWER(e.nome)
SET cw.empresa_id = e.id
WHERE cw.empresa_id IS NULL OR cw.empresa_id = 0;

-- Verifica se todos foram corrigidos
SELECT 
    COUNT(*) as total_corrigidos
FROM configuracoes_whitelabel 
WHERE empresa_id IS NOT NULL AND empresa_id > 0;

-- Verifica se ainda há algum com problema
SELECT 
    COUNT(*) as total_com_problema
FROM configuracoes_whitelabel 
WHERE empresa_id IS NULL OR empresa_id = 0;

-- ============================================================
-- TESTE: Verifica a integridade referencial
-- ============================================================

-- Verifica se todos empresa_id apontam para empresas válidas
SELECT 
    cw.id,
    cw.nome_empresa,
    cw.empresa_id,
    e.nome as empresa_real,
    CASE 
        WHEN e.id IS NULL THEN '❌ EMPRESA NÃO EXISTE!'
        ELSE '✅ OK'
    END as status
FROM configuracoes_whitelabel cw
LEFT JOIN empresas e ON cw.empresa_id = e.id;

-- ============================================================
-- PREVENÇÃO: Adiciona constraint para evitar NULL no futuro
-- ============================================================
-- (Descomente apenas se quiser forçar empresa_id obrigatório)

-- ALTER TABLE configuracoes_whitelabel 
-- MODIFY COLUMN empresa_id INT UNSIGNED NOT NULL;
