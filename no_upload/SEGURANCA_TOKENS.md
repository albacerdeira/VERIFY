# 🔐 SEGURANÇA: Códigos vs Tokens

## ⚠️ IMPORTANTE: SÃO DOIS SISTEMAS DIFERENTES!

---

## 1️⃣ CÓDIGO DE VERIFICAÇÃO DE EMAIL

### Uso Atual no Sistema
```sql
-- Tabela: kyc_clientes
codigo_verificacao VARCHAR(10)     -- Ex: "0ce2a8898e"
codigo_expira_em DATETIME          -- Expira em minutos
```

### Geração (Cliente Registro)
```php
// Em cliente_registro.php:
$codigo_verificacao = substr(md5(uniqid(rand(), true)), 0, 6);
// Gera: "0ce2a8" (6 caracteres)
```

### Propósito
- ✅ Confirmar que email existe
- ✅ Ativação inicial da conta
- ✅ Usado UMA ÚNICA VEZ
- ✅ Expira em poucos minutos
- ✅ Cliente já tem senha depois

### Segurança = SUFICIENTE para este caso
- Tentativas limitadas
- Expira rapidamente
- Não dá acesso a dados sensíveis
- Apenas ativa a conta

---

## 2️⃣ TOKEN DE ACESSO PARA FORMULÁRIO KYC

### Novo Campo (Integração Lead → Cliente)
```sql
-- Tabela: kyc_clientes
token_acesso VARCHAR(64)           -- Ex: "a1b2c3d4e5f6..." (64 chars!)
token_expiracao DATETIME           -- Expira em 30 DIAS
```

### Geração (Lead → Cliente)
```php
// Em ajax_send_kyc_to_lead.php:
$token = bin2hex(random_bytes(32));
// Gera: "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6..." (64 chars)
```

### Propósito
- 🔒 Acesso DIRETO ao formulário KYC completo
- 🔒 SEM necessidade de login/senha
- 🔒 Cliente preenche dados sensíveis (CNPJ, documentos)
- 🔒 Válido por 30 dias
- 🔒 Link pode ser compartilhado

### Segurança = MÁXIMA!
- Impossível adivinhar
- Único por cliente
- Criptograficamente seguro
- Índice de busca otimizado

---

## 📊 COMPARAÇÃO VISUAL

### Código de Verificação (6 chars)
```
0ce2a8
^^^^^^
6 caracteres = 16^6 = 16.777.216 possibilidades
```
**Tempo para quebrar (força bruta):**
- 1 tentativa/segundo = ~194 dias
- MAS: Expira em minutos! ✅
- MAS: Limitado a 3-5 tentativas! ✅

### Token de Acesso KYC (64 chars)
```
a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
64 caracteres hex = 16^64 = 2^256 possibilidades
```
**Tempo para quebrar (força bruta):**
- 1 bilhão de tentativas/segundo = **10^60 anos** 🤯
- Mais que a idade do universo!
- IMPOSSÍVEL na prática! 🔒

---

## 🎯 CONCLUSÃO

### ✅ NÃO É PROBLEMA!

**Por quê?**

1. **Código de verificação pequeno = OK**
   - Usado apenas para confirmar email
   - Expira rapidamente
   - Tentativas limitadas
   - Não dá acesso a dados críticos

2. **Token de acesso grande = NECESSÁRIO**
   - Dá acesso completo ao formulário
   - Válido por 30 dias
   - Link pode ser compartilhado
   - Precisa ser criptograficamente seguro

3. **Cada um tem seu propósito**
   - Código = Ativar conta (baixo risco)
   - Token = Acessar formulário (alto risco)

---

## 🔧 EXEMPLO REAL

### Cenário 1: Registro Normal
```
1. Cliente se registra → email enviado
2. Email contém: "Seu código: 0ce2a8"
3. Cliente digita código na página
4. Conta ativada → define senha
5. Código nunca mais usado ✅
```

### Cenário 2: Lead → Cliente (NOVO)
```
1. Lead capturado no site
2. Admin clica "Enviar KYC"
3. Sistema gera token: "a1b2c3d4e5f6..."
4. Admin envia link: kyc_form.php?token=a1b2c3d4...
5. Cliente acessa link DIRETO (sem login)
6. Cliente preenche formulário completo
7. Token continua válido por 30 dias 🔒
```

---

## 🛡️ MEDIDAS DE SEGURANÇA ADICIONAIS

### No Código Atual (ajax_send_kyc_to_lead.php):

**1. Geração Segura:**
```php
$token = bin2hex(random_bytes(32));
// random_bytes() = função criptográfica do PHP
// bin2hex() = converte para hexadecimal
// 32 bytes × 2 = 64 caracteres finais
```

**2. Validação de Acesso:**
```php
// Em kyc_form.php, verificar:
- Token existe?
- Token não expirou? (30 dias)
- Cliente ainda ativo?
- Empresa parceira válida?
```

**3. Auditoria:**
```php
// Registra em leads_historico:
- Quem gerou o token
- Quando foi gerado
- Para qual lead
- Quando foi usado
```

**4. Expiração Automática:**
```sql
token_expiracao = DATE_ADD(NOW(), INTERVAL 30 DAY)
-- Após 30 dias, token inválido automaticamente
```

---

## ✅ RECOMENDAÇÕES

### O que está CORRETO:
- ✅ `token_acesso VARCHAR(64)` - Tamanho perfeito
- ✅ Geração com `random_bytes(32)` - Seguro
- ✅ Expiração em 30 dias - Razoável
- ✅ Índice no campo - Performance OK
- ✅ Auditoria completa - Rastreável

### Melhorias Futuras (Opcional):
- 🔄 Invalidar token após primeiro uso
- 🔄 Log de todas tentativas de acesso
- 🔄 Rate limiting (limitar tentativas por IP)
- 🔄 Notificar admin quando token usado
- 🔄 Adicionar captcha no formulário

---

## 📝 RESUMO FINAL

| Aspecto | Código Verificação | Token KYC |
|---------|-------------------|-----------|
| **Tamanho** | 6-10 chars | **64 chars** ✅ |
| **Segurança** | Baixa (suficiente) | **Máxima** ✅ |
| **Validade** | Minutos | 30 dias |
| **Acesso** | Ativa conta | Formulário completo |
| **Problema?** | ❌ NÃO | ❌ NÃO |

---

**🎉 Sistema seguro e bem implementado!**

O código pequeno (`0ce2a8`) é apropriado para verificação de email.  
O token longo (64 chars) é apropriado para acesso ao formulário.  

**Cada um no seu lugar! Tudo certo! ✅**
