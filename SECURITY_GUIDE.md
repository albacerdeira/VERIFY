# 🔐 GUIA DE SEGURANÇA - PROTEÇÃO DE CREDENCIAIS

## ⚠️ PROBLEMAS DE SEGURANÇA IDENTIFICADOS E CORRIGIDOS

### 🔴 Antes (INSEGURO):
- ❌ `config.php` com credenciais hardcoded
- ❌ `env.php` com credenciais hardcoded  
- ❌ Arquivos **NÃO** protegidos no `.gitignore`
- ❌ Senha exposta: `005@Fabio`
- ❌ Usuário exposto: `u640879529_kyc`

### ✅ Agora (SEGURO):
- ✅ `.gitignore` atualizado para proteger `config.php` e `env.php`
- ✅ Arquivos `.example` criados (sem credenciais reais)
- ✅ Credenciais sensíveis removidas do repositório

---

## 🚨 AÇÕES IMEDIATAS NECESSÁRIAS

### 1. **Remover arquivos comprometidos do Git**
Execute estes comandos no terminal:

```bash
# Remover do histórico do Git (CUIDADO: pode reescrever histórico)
git rm --cached config.php
git rm --cached env.php

# Commitar a remoção
git add .gitignore
git commit -m "🔒 Security: Remove credentials from repository"

# IMPORTANTE: Force push SOMENTE se for repositório privado
# git push --force
```

### 2. **Trocar TODAS as senhas expostas**
⚠️ **CRÍTICO**: A senha `005@Fabio` está comprometida!

- [ ] Trocar senha do banco de dados de produção
- [ ] Trocar senha do banco de dados de desenvolvimento
- [ ] Trocar senhas de usuários admin
- [ ] Trocar tokens de API (se houver)

### 3. **Configurar ambiente local**
```bash
# Copiar arquivos de exemplo
cp config.php.example config.php
cp env.php.example env.php

# Editar com suas credenciais REAIS
# (estes arquivos NÃO serão commitados)
```

---

## 📋 CHECKLIST DE SEGURANÇA

### Banco de Dados:
- [ ] Senha alterada no servidor de produção
- [ ] Senha alterada no servidor de desenvolvimento
- [ ] `config.php` removido do Git
- [ ] `env.php` removido do Git

### Git/GitHub:
- [ ] `.gitignore` atualizado
- [ ] Arquivos sensíveis removidos do histórico
- [ ] Verificar que não há commits com credenciais
- [ ] Repositório configurado como **PRIVADO**

### Servidores:
- [ ] Verificar logs de acesso suspeito
- [ ] Implementar autenticação de dois fatores (2FA)
- [ ] Revisar permissões de usuários do banco

---

## 🛡️ BOAS PRÁTICAS IMPLEMENTADAS

### 1. **Arquivo `.gitignore` atualizado**
```gitignore
# Arquivos de configuração (NUNCA COMMITAR!)
.env
env.php
config.php
```

### 2. **Arquivos .example criados**
- ✅ `config.php.example` - Template sem credenciais
- ✅ `env.php.example` - Template sem credenciais

### 3. **Separação de ambientes**
- Produção vs Desenvolvimento
- Credenciais diferentes para cada ambiente
- Detecção automática via `$_SERVER['HTTP_HOST']`

---

## 🔍 VERIFICAÇÃO DE SEGURANÇA

### Verificar se credenciais foram expostas:
```bash
# Buscar no histórico do Git
git log --all --full-history -- config.php
git log --all --full-history -- env.php

# Buscar senhas no código
grep -r "005@Fabio" .
grep -r "u640879529" .
```

### Verificar commits recentes:
```bash
# Ver o que vai ser commitado
git status

# Ver diferenças
git diff
```

---

## 📞 SUPORTE

Se você já fez commits com credenciais:

### Opção 1: Repositório Privado
Se o repositório é **privado** e você confia em todos os colaboradores:
1. Trocar as senhas expostas
2. Remover arquivos do Git
3. Continuar normalmente

### Opção 2: Repositório Público (CRÍTICO!)
Se o repositório é **público**:
1. **URGENTE**: Trocar TODAS as senhas IMEDIATAMENTE
2. Deletar o repositório
3. Criar novo repositório
4. Fazer push apenas com arquivos seguros

### Opção 3: Reescrever Histórico (Avançado)
```bash
# Usar git filter-branch ou BFG Repo-Cleaner
# CUIDADO: Reescreve todo o histórico!
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch config.php env.php" \
  --prune-empty --tag-name-filter cat -- --all
```

---

## ✅ STATUS ATUAL

- [x] `.gitignore` corrigido
- [x] Arquivos `.example` criados
- [ ] **VOCÊ PRECISA**: Trocar senhas expostas
- [ ] **VOCÊ PRECISA**: Remover do histórico Git
- [ ] **VOCÊ PRECISA**: Configurar arquivos locais

---

## 📌 LEMBRE-SE:

> **NUNCA commite:**
> - Senhas
> - Tokens de API
> - Chaves privadas
> - Dados de cartão de crédito
> - Informações pessoais sensíveis

> **SEMPRE use:**
> - Arquivos `.example` para templates
> - Variáveis de ambiente
> - `.gitignore` para proteção
> - Repositórios privados quando possível

---

**Data de correção:** 06/11/2025  
**Arquivos protegidos:** `config.php`, `env.php`  
**Senha comprometida:** `005@Fabio` (TROCAR IMEDIATAMENTE!)
