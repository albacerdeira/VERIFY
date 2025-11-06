# 🧪 TESTE DA API AML SCREENING

**Endpoint:** `POST /api/v1/screening/aml`

---

## 📋 PRÉ-REQUISITOS

1. ✅ Tabelas `ceis`, `cnep`, `peps` devem existir e estar populadas
2. ✅ Criar tabela `aml_screenings` (executar `sql/aml_screenings.sql`)
3. ✅ Token de API ativo em `configuracoes_whitelabel.api_token`

---

## 🧪 TESTE 1: Screening de Pessoa Física (CPF)

### Request:

```bash
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -d '{
    "tipo": "pf",
    "cpf": "123.456.789-01",
    "nome": "João da Silva"
  }'
```

### Response Esperada (SEM sanções):

```json
{
  "success": true,
  "data": {
    "screening_id": 1,
    "tipo": "PF",
    "risk_score": 0,
    "risk_level": "LOW",
    "flags": [],
    "flags_count": 0,
    "recommendation": "Aprovado. Monitoramento padrão.",
    "screened_at": "2025-11-05 15:30:22",
    "bases_consultadas": {
      "ceis": true,
      "cnep": true,
      "pep": true
    }
  }
}
```

### Response Esperada (COM PEP):

```json
{
  "success": true,
  "data": {
    "screening_id": 2,
    "tipo": "PF",
    "risk_score": 40,
    "risk_level": "MEDIUM",
    "flags": [
      {
        "type": "PEP",
        "severity": "HIGH",
        "details": {
          "nome_pep": "JOÃO DA SILVA",
          "cpf": "***.456.789-**",
          "sigla_funcao": "MIN",
          "descricao_funcao": "Ministro de Estado",
          "nivel_funcao": "1",
          "orgao": "Ministério da Fazenda",
          "data_inicio": "2023-01-01",
          "data_fim": null
        }
      }
    ],
    "flags_count": 1,
    "recommendation": "Aprovado com restrições. Solicitar documentos adicionais.",
    "screened_at": "2025-11-05 15:31:45",
    "bases_consultadas": {
      "ceis": true,
      "cnep": true,
      "pep": true
    }
  }
}
```

---

## 🧪 TESTE 2: Screening de Pessoa Jurídica (CNPJ)

### Request:

```bash
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -d '{
    "tipo": "pj",
    "cnpj": "12.345.678/0001-90",
    "razao_social": "EMPRESA EXEMPLO LTDA",
    "nome": "Empresa Exemplo"
  }'
```

### Response Esperada (SEM sanções):

```json
{
  "success": true,
  "data": {
    "screening_id": 3,
    "tipo": "PJ",
    "risk_score": 0,
    "risk_level": "LOW",
    "flags": [],
    "flags_count": 0,
    "recommendation": "Aprovado. Monitoramento padrão.",
    "screened_at": "2025-11-05 15:35:10",
    "bases_consultadas": {
      "ceis": true,
      "cnep": true,
      "pep": false
    }
  }
}
```

### Response Esperada (COM CEIS):

```json
{
  "success": true,
  "data": {
    "screening_id": 4,
    "tipo": "PJ",
    "risk_score": 40,
    "risk_level": "MEDIUM",
    "flags": [
      {
        "type": "CEIS",
        "severity": "HIGH",
        "similarity": 92.5,
        "details": {
          "nome_sancionado": "EMPRESA EXEMPLO LTDA",
          "orgao_sancionador": "CGU",
          "data_inicio": "2024-03-15",
          "tipo_sancao": "Impedimento de licitar"
        }
      }
    ],
    "flags_count": 1,
    "recommendation": "Aprovado com restrições. Solicitar documentos adicionais.",
    "screened_at": "2025-11-05 15:36:22",
    "bases_consultadas": {
      "ceis": true,
      "cnep": true,
      "pep": false
    }
  }
}
```

---

## 🧪 TESTE 3: Auto-Detecção de Tipo

### Request (sem especificar "tipo"):

```bash
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -d '{
    "cpf": "123.456.789-01",
    "nome": "João da Silva"
  }'
```

✅ **Sistema detecta automaticamente:** `tipo = "pf"` (porque tem CPF)

---

## 🧪 TESTE 4: Erros de Validação

### 4.1 - Token Inválido:

```bash
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN_INVALIDO" \
  -d '{"cpf": "123.456.789-01", "nome": "João"}'
```

**Response:**
```json
{
  "success": false,
  "error": "Token inválido ou inativo"
}
```

HTTP Status: `401 Unauthorized`

---

### 4.2 - Campo Obrigatório Faltando:

```bash
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{"cpf": "123.456.789-01"}'
```

**Response:**
```json
{
  "success": false,
  "error": "Campo obrigatório: nome"
}
```

HTTP Status: `400 Bad Request`

---

### 4.3 - CPF Inválido:

```bash
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{"tipo": "pf", "cpf": "123", "nome": "João"}'
```

**Response:**
```json
{
  "success": false,
  "error": "CPF inválido. Deve conter 11 dígitos."
}
```

HTTP Status: `400 Bad Request`

---

### 4.4 - Rate Limit Excedido:

```bash
# Após 100 requisições na mesma hora:
```

**Response:**
```json
{
  "success": false,
  "error": "Rate limit excedido. Máximo: 100 requisições/hora"
}
```

HTTP Status: `429 Too Many Requests`

---

## 🧪 TESTE 5: Múltiplos Flags (Alto Risco)

### Request:

```bash
# CPF que está em CEIS + CNEP + PEP
curl -X POST "http://localhost/api/v1/screening/aml" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "tipo": "pf",
    "cpf": "999.888.777-66",
    "nome": "Fulano de Tal"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "screening_id": 5,
    "tipo": "PF",
    "risk_score": 100,
    "risk_level": "CRITICAL",
    "flags": [
      {
        "type": "CEIS",
        "severity": "CRITICAL",
        "details": {...}
      },
      {
        "type": "CNEP",
        "severity": "CRITICAL",
        "details": {...}
      },
      {
        "type": "PEP",
        "severity": "HIGH",
        "details": {...}
      }
    ],
    "flags_count": 3,
    "recommendation": "BLOQUEADO. Cliente em lista de sanções ou PEP de alto risco.",
    "screened_at": "2025-11-05 15:40:15",
    "bases_consultadas": {
      "ceis": true,
      "cnep": true,
      "pep": true
    }
  }
}
```

---

## 🔧 TESTANDO NO POSTMAN

### 1. Criar Nova Request:
- **Method:** POST
- **URL:** `http://localhost/api/v1/screening/aml`

### 2. Headers:
```
Content-Type: application/json
Authorization: Bearer SEU_TOKEN_AQUI
```

### 3. Body (raw JSON):
```json
{
  "tipo": "pf",
  "cpf": "123.456.789-01",
  "nome": "João da Silva"
}
```

### 4. Executar e verificar Response

---

## 📊 VERIFICAR NO BANCO DE DADOS

```sql
-- Ver últimos 10 screenings
SELECT 
    id,
    tipo,
    nome,
    cpf,
    cnpj,
    risk_score,
    risk_level,
    flags_count,
    screened_at
FROM (
    SELECT 
        id,
        tipo,
        nome,
        cpf,
        cnpj,
        risk_score,
        risk_level,
        JSON_LENGTH(flags) as flags_count,
        screened_at
    FROM aml_screenings
    ORDER BY screened_at DESC
    LIMIT 10
) as recent;

-- Ver detalhes de um screening específico
SELECT 
    id,
    nome,
    risk_score,
    risk_level,
    JSON_PRETTY(flags) as flags_detalhadas,
    screened_at
FROM aml_screenings
WHERE id = 1;
```

---

## ✅ CHECKLIST DE SUCESSO

- [ ] Tabela `aml_screenings` criada
- [ ] API retorna 401 para token inválido
- [ ] API retorna 400 para CPF/CNPJ inválido
- [ ] API retorna 400 para campo obrigatório faltando
- [ ] Screening de PF sem sanções retorna `risk_level: LOW`
- [ ] Screening de PJ sem sanções retorna `risk_level: LOW`
- [ ] Screening com PEP retorna flag `type: PEP`
- [ ] Screening com CEIS retorna flag `type: CEIS`
- [ ] Screening com CNEP retorna flag `type: CNEP`
- [ ] Rate limiting funciona (após 100 req/hora retorna 429)
- [ ] Logs salvos corretamente na tabela `aml_screenings`

---

## 🚀 PRÓXIMOS PASSOS

1. ✅ Testar com dados reais do seu banco
2. ✅ Ajustar threshold de similaridade (atualmente 85%)
3. ✅ Adicionar mais detalhes nos flags conforme necessário
4. ✅ Documentar no Swagger/OpenAPI
5. ✅ Criar SDK PHP para facilitar integração
