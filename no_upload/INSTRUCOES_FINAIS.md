# 🚀 Instruções Finais - Sistema de Verificação

## ✅ Status Atual

- ✅ Autoloader do Composer funcionando
- ✅ AWS SDK carregado (Textract + Rekognition)
- ✅ Tabela `document_verifications` corrigida
- ✅ Coluna `usuario_id` adicionada
- ⚠️ Coluna `user_agent` precisa ser adicionada em `facial_verifications`
- ⚠️ OCR melhorado mas precisa ajustes

---

## 📤 PASSO 1: Upload dos Arquivos Atualizados

Faça upload via FTP dos seguintes arquivos:

### Arquivos Principais:
```
✅ ajax_verify_face.php
✅ ajax_verify_document.php
✅ cliente_edit.php
✅ src/DocumentValidatorAWS.php (ATUALIZADO - melhorias OCR)
✅ migrate_document_verifications.php (ATUALIZADO - adiciona user_agent)
```

### Estrutura de Pastas:
```
/home/u640879529/domains/verify2b.com/public_html/
├── ajax_verify_face.php
├── ajax_verify_document.php
├── cliente_edit.php
├── migrate_document_verifications.php
├── src/
│   └── DocumentValidatorAWS.php
└── vendor/ (já existe)
```

---

## 🔧 PASSO 2: Execute a Migração (NOVAMENTE)

**URL:** `https://verify2b.com/migrate_document_verifications.php`

A migração agora vai adicionar a coluna `user_agent` que está faltando.

**Resultado esperado:**
```
✅ Coluna 'usuario_id' JÁ EXISTE.
✅ Coluna 'user_agent' adicionada!
✅ MIGRAÇÃO CONCLUÍDA COM SUCESSO!
```

---

## 🧪 PASSO 3: Teste as Verificações

### Teste 1: Verificação Facial (Selfie Simples)
1. Faça login no sistema
2. Vá em "Clientes" → Editar um cliente
3. Clique em "Selfie Simples"
4. Tire uma foto ou faça upload
5. **Resultado esperado:** Similaridade >= 90%

### Teste 2: Verificação de Documento (RG/CNH)
1. No mesmo cliente, clique em "Documento com Foto"
2. Tire foto do RG ou CNH ou faça upload
3. **Resultado esperado:** Score >= 70% (8/12 pontos)

**Console do Navegador (F12):**
```javascript
Response status: 200
Response text: {"success":true,"message":"Verificação bem-sucedida!"}
```

---

## 🐛 Problemas Conhecidos e Soluções

### Problema: Nome extraído errado
**Exemplo:** "REGISTRO O VÁLIDA EM TODO O TERRITÓRIO NACIONAL"

**Causa:** OCR pegando texto do cabeçalho do documento

**Solução aplicada:**
- ✅ Lista expandida de palavras-chave excluídas (REGISTRO, TERRITÓRIO, etc.)
- ✅ Filtro de linhas que começam com números
- ✅ Validação de quantidade de palavras (2-6 palavras)
- ✅ Validação de tamanho mínimo por palavra (>= 2 caracteres)

### Problema: CPF inválido sendo aceito
**Exemplo:** 128.216.698-11 (inválido)

**Solução aplicada:**
- ✅ Validação matemática do CPF (algoritmo módulo 11)
- ✅ Rejeição de CPFs com todos os dígitos iguais
- ✅ Priorização de CPFs com label "CPF:"

### Problema: Column 'user_agent' not found
**Solução:** Execute novamente `migrate_document_verifications.php` atualizado

---

## 📊 Sistema de Pontuação

### Verificação de Documento (12 pontos máximo):

| Campo | Pontos | Critério |
|-------|--------|----------|
| Nome | 3 | Similaridade >= 80% |
| CPF | 3 | Match 100% + válido |
| RG | 2 | Match 100% |
| Face | 4 | Similaridade >= 90% |
| **TOTAL** | **12** | **Mínimo 8 (70%)** |

### Validação aprovada se:
- ✅ Score >= 8 pontos (70%)
- ✅ Face similarity >= 90%
- ✅ CPF matematicamente válido

---

## 🎯 Exemplo de Resultado Esperado

```json
{
  "success": true,
  "message": "Verificação bem-sucedida! Score: 10/12 (83.33%)",
  "validations": {
    "nome": {
      "extracted": "ALBA AMARAL GURGEL CERDEIRA",
      "database": "Alba Amaral Gurgel Cerdeira",
      "match": true,
      "similarity": 95.12
    },
    "cpf": {
      "extracted": "272.277.478-08",
      "database": "272.277.478-08",
      "match": true,
      "valid": true
    },
    "rg": {
      "extracted": "27.813.031-8",
      "database": "Não cadastrado",
      "match": null
    },
    "face": {
      "similarity": 95.67,
      "match": true
    }
  },
  "verification_token": "abc123..."
}
```

---

## 🗑️ PASSO 4: Limpar Arquivos de Debug

Após confirmar que tudo funciona:

```bash
rm /home/u640879529/domains/verify2b.com/public_html/migrate_document_verifications.php
rm /home/u640879529/domains/verify2b.com/public_html/debug_autoloader.php
rm /home/u640879529/domains/verify2b.com/public_html/test_composer.php
```

Ou via File Manager do Hostinger.

---

## 📝 Melhorias Futuras (Opcional)

### 1. Ajustar Thresholds
Se a validação estiver muito rigorosa ou permissiva:

**Arquivo:** `ajax_verify_document.php`

```php
// Linha ~180: Similaridade do nome
if ($percent >= 80) $validation_score += 3;  // Ajuste: 70-90

// Linha ~310: Similaridade facial
$face_threshold = 90;  // Ajuste: 85-95
```

### 2. Adicionar Mais Campos
Para extrair mais dados do documento:

**Arquivo:** `src/DocumentValidatorAWS.php`

- Data de nascimento (melhorar padrão)
- Naturalidade
- Órgão emissor
- Data de emissão

### 3. Log de Tentativas
Monitorar tentativas de verificação:

```sql
SELECT 
    cliente_id,
    verification_result,
    validation_percent,
    created_at
FROM document_verifications
WHERE verification_result = 'failed'
ORDER BY created_at DESC
LIMIT 20;
```

---

## 🔐 Segurança

### Tokens de Verificação:
- ✅ Gerados com `random_bytes(32)` (64 caracteres hex)
- ✅ Expiração: 5 minutos
- ✅ Uso único (deletado após consumo)
- ✅ Vinculado ao cliente e usuário

### Upload de Arquivos:
- ✅ Validação de tipo MIME
- ✅ Limite de tamanho: Selfie 5MB, Documento 10MB
- ✅ Apenas JPG/PNG aceitos
- ✅ Arquivos temporários deletados após processamento

---

## 💰 Custos AWS

### Estimativa Mensal:

**Textract (OCR):**
- Custo: $0.0015 por página
- 1000 verificações/mês = $1.50

**Rekognition (Face):**
- DetectFaces: $0.001 por imagem
- CompareFaces: $0.001 por comparação
- 1000 verificações/mês = $2.00

**Total:** ~$3.50/mês para 1000 verificações

**Free Tier:**
- Textract: 1000 páginas/mês (3 meses)
- Rekognition: 1000 faces/mês (12 meses)

---

## 📞 Suporte

### Logs de Erro:
```
/home/u640879529/domains/verify2b.com/public_html/error.log
```

### Console do Navegador (F12):
Verifique logs de `console.log()` para debug

### Verificar Estrutura do Banco:
```sql
DESCRIBE document_verifications;
DESCRIBE facial_verifications;
```

---

## ✅ Checklist Final

- [ ] Upload dos 5 arquivos principais
- [ ] Executar `migrate_document_verifications.php`
- [ ] Verificar mensagem de sucesso da migração
- [ ] Testar verificação facial (selfie)
- [ ] Testar verificação de documento (RG/CNH)
- [ ] Verificar console do navegador (F12)
- [ ] Confirmar token gerado e salvo
- [ ] Deletar arquivos de migração/debug
- [ ] Testar em diferentes clientes
- [ ] Monitorar custos AWS

---

**🎉 Sistema pronto para produção!**

Se houver problemas, verifique:
1. Console do navegador (F12)
2. Arquivo `error.log`
3. Estrutura das tabelas (via `debug_autoloader.php`)
