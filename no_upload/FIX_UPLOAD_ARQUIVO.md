# 🚨 ERRO PERSISTENTE: api_lead_webhook.php NÃO FOI ATUALIZADO!

## ❌ O Problema:

O erro continua acontecendo porque o arquivo `api_lead_webhook.php` **NO SERVIDOR** ainda está com o código antigo (errado).

```
Erro: SQLSTATE[23000]: Integrity constraint violation: 1452
Linha: api_lead_webhook.php(263)
```

Isso significa que o servidor está usando a **VERSÃO ANTIGA** do arquivo!

---

## ✅ SOLUÇÃO IMEDIATA:

### Passo 1: Verifique Qual Versão Está no Servidor

Acesse no navegador:
```
https://verify2b.com/check_empresa_id.php?token=SEU_TOKEN_AQUI
```

**Substitua `SEU_TOKEN_AQUI` pelo seu token de API real!**

Esta página vai mostrar:
- ✅ Se o `empresa_id` está correto
- ❌ Se o código ainda está errado
- 🔍 Qual valor está sendo usado

---

### Passo 2: Faça Upload do Arquivo Corrigido

**IMPORTANTE:** O arquivo `api_lead_webhook.php` que está **nesta pasta local** JÁ ESTÁ CORRIGIDO!

Você precisa fazer upload dele para o servidor:

#### Via FTP/SFTP:
1. Conecte no servidor via FileZilla, WinSCP, etc
2. Vá para: `/home/u640879529/domains/verify2b.com/public_html/`
3. Faça backup do arquivo atual: `api_lead_webhook.php` → `api_lead_webhook.php.bak`
4. Faça upload do arquivo LOCAL corrigido
5. Teste novamente

#### Via cPanel/Gerenciador de Arquivos:
1. Acesse o cPanel
2. Vá em **Gerenciador de Arquivos**
3. Navegue até: `public_html/`
4. Clique com botão direito em `api_lead_webhook.php`
5. Escolha **Editar**
6. Copie o conteúdo do arquivo LOCAL e cole lá
7. Salve

#### Via SSH/Terminal:
```bash
# Conecte via SSH
ssh usuario@verify2b.com

# Faça backup
cd /home/u640879529/domains/verify2b.com/public_html/
cp api_lead_webhook.php api_lead_webhook.php.bak

# Upload do arquivo novo
# (use scp, rsync, ou edite manualmente)
```

---

## 🔍 Como Confirmar Que Foi Corrigido:

### Teste 1: Verifique o Código
Abra o arquivo no servidor e procure por esta linha (perto da linha 95-100):

**❌ VERSÃO ANTIGA (ERRADA):**
```php
$empresa_id = $empresa['id'];  // ERRADO!
```

**✅ VERSÃO NOVA (CORRETA):**
```php
$config_id = $empresa['id'];       // ID da config
$empresa_id = $empresa['empresa_id']; // ID da empresas ✅
```

### Teste 2: Verifique a Query SELECT
Procure pela query SQL (perto da linha 67):

**❌ VERSÃO ANTIGA (ERRADA):**
```php
SELECT id, slug, nome_empresa, api_token_ativo, api_rate_limit 
FROM configuracoes_whitelabel
```

**✅ VERSÃO NOVA (CORRETA):**
```php
SELECT id, empresa_id, slug, nome_empresa, api_token_ativo, api_rate_limit 
FROM configuracoes_whitelabel
```

**ATENÇÃO:** A diferença é a coluna `empresa_id` que DEVE estar no SELECT!

---

## 📋 Checklist de Verificação:

- [ ] Fiz upload do `api_lead_webhook.php` corrigido
- [ ] Verifiquei que o arquivo tem `empresa_id` no SELECT
- [ ] Verifiquei que usa `$empresa['empresa_id']` e não `$empresa['id']`
- [ ] Testei em: `check_empresa_id.php?token=MEU_TOKEN`
- [ ] O check mostra "✅ CORRETO!"
- [ ] Testei o formulário em: `test_universal_capture.php`
- [ ] O lead foi criado com sucesso!

---

## 🔧 Se o Erro Persistir:

### Possibilidade 1: Cache do PHP
```bash
# Limpa o cache do OPcache (se estiver ativo)
# Via SSH:
sudo service php-fpm reload

# Ou adicione no topo do api_lead_webhook.php temporariamente:
opcache_reset();
```

### Possibilidade 2: Arquivo Errado
Certifique-se de que está editando o arquivo certo:
```
/home/u640879529/domains/verify2b.com/public_html/api_lead_webhook.php
```

E NÃO em outra pasta como:
- `/public_html/admin/api_lead_webhook.php`
- `/public_html/teste/api_lead_webhook.php`
- etc.

### Possibilidade 3: Permissões
```bash
# Verifique se o arquivo pode ser lido:
ls -la /home/u640879529/domains/verify2b.com/public_html/api_lead_webhook.php

# Deve mostrar algo como:
# -rw-r--r-- 1 usuario usuario 12345 Nov 01 22:30 api_lead_webhook.php

# Se não, ajuste:
chmod 644 api_lead_webhook.php
```

---

## 🎯 Resumo:

**VOCÊ JÁ CORRIGIU O BANCO DE DADOS** ✅ (Passo 7 mostrou tudo OK)

**FALTA APENAS:** Atualizar o arquivo PHP no servidor! 

O arquivo LOCAL já está correto. Faça o upload e pronto! 🚀

---

## 📞 Precisa de Ajuda?

Se mesmo após fazer o upload o erro persistir:

1. Acesse: `https://verify2b.com/check_empresa_id.php?token=SEU_TOKEN`
2. Tire um print da página
3. Me envie o resultado

Assim posso ver exatamente onde está o problema! 😊
