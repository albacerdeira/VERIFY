# 🚀 Integração RD Station Marketing - Guia Completo

## 📋 Índice
1. [Visão Geral](#visão-geral)
2. [Configuração Inicial](#configuração-inicial)
3. [Configurar Webhook no RD Station](#configurar-webhook-no-rd-station)
4. [Testar a Integração](#testar-a-integração)
5. [Solução de Problemas](#solução-de-problemas)

---

## 🎯 Visão Geral

Esta integração permite que leads capturados no RD Station Marketing sejam automaticamente enviados para o sistema Verify2B.

**URL do Webhook:**
```
https://verify2b.com/api_lead_webhook.php?token=SEU_TOKEN_AQUI
```

**Método:** `POST`  
**Content-Type:** `application/json`

---

## ⚙️ Configuração Inicial

### 1. Obter o Token de API

1. Acesse: https://verify2b.com/configuracoes
2. Localize a seção **"Configurações da Empresa"**
3. Procure pelo campo **"Token da API"**
4. Copie o token (exemplo: `0d342ebaa87c9a8d9524b2fbfb3152141f3954b79b52f94ce5183d5523d87090`)

### 2. Configurar Token RD Station (Opcional)

Se você deseja enviar dados DE VOLTA para o RD Station:

1. Na mesma página de Configurações
2. Localize o campo **"Token RD Station"**
3. Cole o token de API do RD Station
4. Clique em **"Salvar Configurações"**

---

## 🔗 Configurar Webhook no RD Station

### Passo 1: Acessar Configurações de Webhook

1. Faça login no **RD Station Marketing**
2. Vá em: **Configurações** → **Integrações** → **Webhooks**
3. Clique em **"Nova Integração"** ou **"Adicionar Webhook"**

### Passo 2: Configurar URL do Webhook

**URL completa:**
```
https://verify2b.com/api_lead_webhook.php?token=0d342ebaa87c9a8d9524b2fbfb3152141f3954b79b52f94ce5183d5523d87090
```

⚠️ **IMPORTANTE:** Substitua `0d342ebaa87c9a8d9524b2fbfb3152141f3954b79b52f94ce5183d5523d87090` pelo **seu token real** obtido nas configurações.

### Passo 3: Selecionar Eventos

Marque os eventos que devem acionar o webhook:

- ✅ **Conversão de Lead** (recomendado)
- ✅ **Lead criado**
- ✅ **Lead atualizado** (opcional)
- ⬜ Oportunidade criada
- ⬜ Negócio ganho

### Passo 4: Configurar Campos Enviados

O RD Station deve enviar os seguintes campos (mínimo obrigatório):

| Campo RD Station | Campo Verify2B | Obrigatório |
|------------------|----------------|-------------|
| `name` ou `nome` | `nome` | ✅ Sim |
| `email` | `email` | ✅ Sim |
| `mobile_phone` ou `telefone` | `whatsapp` | ✅ Sim |
| `company` | `empresa` | ⬜ Não |
| `personal_phone` | `telefone_fixo` | ⬜ Não |

**Exemplo de payload JSON esperado:**
```json
{
  "nome": "João Silva",
  "email": "joao.silva@exemplo.com",
  "whatsapp": "(11) 98765-4321",
  "empresa": "Empresa Exemplo Ltda",
  "mensagem": "Gostaria de mais informações",
  "origem": "RD Station",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "campanha_teste"
}
```

### Passo 5: Salvar e Ativar

1. Clique em **"Salvar"**
2. Certifique-se de que o webhook está **ATIVO** (toggle ligado)

---

## 🧪 Testar a Integração

### Método 1: Teste Integrado do RD Station

1. No RD Station, vá em **Configurações** → **Webhooks**
2. Clique no webhook criado
3. Clique em **"Testar Webhook"**
4. **Escolha um lead existente no RD Station**
   - ⚠️ **IMPORTANTE:** O lead deve existir no banco de dados do RD Station
   - Se aparecer "Lead não encontrado", significa que não há lead com esse nome no RD
   - Crie um lead de teste primeiro ou escolha outro da lista
5. Clique em **"Enviar Teste"**

### Método 2: Teste Manual via Script

1. Acesse: https://verify2b.com/test_rd_webhook.php
2. O script enviará um lead de teste automaticamente
3. Verifique a resposta na tela

### Método 3: Teste via cURL (Terminal)

```bash
curl -X POST \
  'https://verify2b.com/api_lead_webhook.php?token=SEU_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "nome": "Teste Lead",
    "email": "teste@exemplo.com",
    "whatsapp": "(11) 98765-4321",
    "empresa": "Empresa Teste"
  }'
```

### Verificar se o Lead foi Criado

1. Acesse: https://verify2b.com/leads.php
2. Procure pelo lead com o email usado no teste
3. Verifique se o campo **"Origem"** está como `RD Station`

---

## 🐛 Solução de Problemas

### ❌ "Lead não encontrado" no teste do RD Station

**Causa:** Você está tentando escolher um lead que não existe no banco de dados do RD Station.

**Solução:**
1. Crie um lead de teste no RD Station primeiro
2. Ou escolha um lead existente da lista dropdown
3. Ou use o teste manual (Método 2 ou 3 acima)

---

### ❌ Erro 401 - "Token inválido"

**Causa:** O token na URL está incorreto ou expirado.

**Solução:**
1. Vá em https://verify2b.com/configuracoes
2. Verifique se o token está correto
3. Se necessário, regenere o token
4. Atualize a URL do webhook no RD Station

---

### ❌ Erro 400 - "Campo obrigatório: XXX"

**Causa:** O RD Station não está enviando os campos obrigatórios.

**Solução:**
1. No RD Station, vá em configurações do webhook
2. Certifique-se de que os campos `nome`, `email` e `whatsapp` estão mapeados
3. Salve e teste novamente

---

### ❌ Erro 429 - "Rate limit excedido"

**Causa:** Você excedeu o limite de 100 requisições por hora.

**Solução:**
1. Aguarde 1 hora para o limite ser resetado
2. Ou entre em contato com o suporte para aumentar o limite

---

### ❌ Leads não estão aparecendo na lista

**Verificações:**

1. **Confirme que o webhook retornou HTTP 201:**
   - Veja os logs do RD Station
   - Use o teste manual para verificar a resposta

2. **Verifique a empresa correta:**
   - O lead é salvo com `id_empresa_master` do token usado
   - Certifique-se de estar logado na empresa correta

3. **Verifique duplicatas:**
   - O sistema ignora emails duplicados dos últimos 30 dias
   - Se o lead já existe, retorna HTTP 200 com o `lead_id` existente

4. **Verifique os logs:**
   - Acesse https://verify2b.com/diagnostico.php
   - Procure por erros relacionados a leads

---

## 📊 Formato Completo dos Dados Aceitos

```json
{
  // OBRIGATÓRIOS
  "nome": "string (nome completo do lead)",
  "email": "string (email válido)",
  "whatsapp": "string (telefone com DDD)",
  
  // OPCIONAIS
  "empresa": "string (nome da empresa do lead)",
  "mensagem": "string (mensagem ou observações)",
  "origem": "string (ex: 'RD Station', 'Google Ads')",
  "utm_source": "string (origem do tráfego)",
  "utm_medium": "string (meio de marketing)",
  "utm_campaign": "string (nome da campanha)"
}
```

---

## 🔒 Segurança

- ✅ Autenticação via token de API
- ✅ Rate limiting (100 requisições/hora por padrão)
- ✅ Validação de campos obrigatórios
- ✅ Validação de formato de email
- ✅ Validação de formato de telefone
- ✅ Logs de todas as requisições

---

## 📞 Suporte

Se precisar de ajuda:

1. Acesse https://verify2b.com/diagnostico.php para verificar logs
2. Use o teste manual em https://verify2b.com/test_rd_webhook.php
3. Entre em contato com o suporte técnico

---

## ✅ Checklist de Configuração

- [ ] Token de API obtido em Configurações
- [ ] Webhook configurado no RD Station com URL completa
- [ ] Campos obrigatórios mapeados (nome, email, whatsapp)
- [ ] Webhook ativado no RD Station
- [ ] Teste realizado com sucesso
- [ ] Lead aparece na lista de Leads do Verify2B
- [ ] Origem do lead está como "RD Station"

---

**Última atualização:** 05/11/2025
