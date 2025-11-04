# 🚀 Guia de Configuração AWS para Validação de Documentos

## 📋 Pré-requisitos
- Cartão de crédito internacional (AWS requer, mesmo para plano gratuito)
- Email válido
- CPF/CNPJ para cadastro

---

## 🎁 Limites FREE TIER (SEM CUSTO)

### 📄 AWS Textract (OCR de Documentos)
- ✅ **1.000 páginas/mês GRÁTIS** por **3 meses**
- Válido apenas para novos clientes AWS
- Após 3 meses: $1.50 por 1.000 páginas ($0.0015 por página)

### 👤 AWS Rekognition (Reconhecimento Facial)
- ✅ **5.000 imagens/mês GRÁTIS** por **12 meses** para:
  - Detecção de faces
  - Análise de qualidade facial (brightness, sharpness)
  - Detecção de emoções e atributos
- ✅ **1.000 comparações de faces/mês GRÁTIS** por **12 meses**
- ✅ **1.000 faces armazenadas GRÁTIS** (collections anti-fraude) por **12 meses**
- Válido apenas para novos clientes AWS
- Após 12 meses: $1.00 por 1.000 imagens

### 💰 Exemplos de Custo por Volume

#### Cenário 1: Até 1.000 validações/mês
```
Mês 1-3 (com Free Tier):
├─ OCR: 1.000 grátis ✅
├─ Face Detection: 1.000 grátis ✅
├─ Face Comparison: 1.000 grátis ✅
└─ TOTAL: R$ 0,00 🎉

Mês 4-12 (Textract pago, Rekognition grátis):
├─ OCR: 1.000 × $0.0015 = $1.50
├─ Face Detection: 1.000 grátis ✅
├─ Face Comparison: 1.000 grátis ✅
└─ TOTAL: $1.50/mês (R$ 7.50)

Após 12 meses (tudo pago):
├─ OCR: 1.000 × $0.0015 = $1.50
├─ Face Detection: 1.000 × $0.001 = $1.00
├─ Face Comparison: 1.000 × $0.001 = $1.00
└─ TOTAL: $3.50/mês (R$ 17.50)
```

#### Cenário 2: 5.300 validações/mês (seu caso)
```
Mês 1-3 (melhor período):
├─ OCR: 1.000 grátis + 4.300 × $0.0015 = $6.45
├─ Face Detection: 5.000 grátis + 300 × $0.001 = $0.30
├─ Face Comparison: 1.000 grátis + 4.300 × $0.001 = $4.30
└─ TOTAL: $11.05/mês (R$ 55.25) 💚

Mês 4-12 (Rekognition ainda grátis):
├─ OCR: 5.300 × $0.0015 = $7.95
├─ Face Detection: 5.000 grátis + 300 × $0.001 = $0.30
├─ Face Comparison: 1.000 grátis + 4.300 × $0.001 = $4.30
└─ TOTAL: $12.55/mês (R$ 62.75)

Após 12 meses (tudo pago):
├─ OCR: 5.300 × $0.0015 = $7.95
├─ Face Detection: 5.300 × $0.001 = $5.30
├─ Face Comparison: 5.300 × $0.001 = $5.30
└─ TOTAL: $18.55/mês (R$ 92.75)
```

#### Cenário 3: 10.000 validações/mês
```
Mês 1-3:
└─ TOTAL: $21.50/mês (R$ 107.50)

Mês 4-12:
└─ TOTAL: $24.00/mês (R$ 120.00)

Após 12 meses:
└─ TOTAL: $35.00/mês (R$ 175.00)
```

### 📊 Resumo Rápido

| Volume/Mês | Mês 1-3 | Mês 4-12 | Após 12m |
|------------|---------|----------|----------|
| ≤ 1.000    | **R$ 0,00** 🎉 | R$ 7,50  | R$ 17,50 |
| 5.300      | R$ 55,25 💚 | R$ 62,75 | R$ 92,75 |
| 10.000     | R$ 107,50 | R$ 120,00 | R$ 175,00 |

**🏆 MÁXIMO SEM CUSTO:**
- **1.000 documentos completos/mês** nos primeiros **3 meses**
- Inclui OCR + detecção facial + comparação

**💡 DICA:** Se processar menos de 1.000 KYCs/mês, seus primeiros 3 meses serão **100% GRATUITOS**!

---

## 1️⃣ Criar Conta AWS

### 1.1 Acesse e Cadastre-se
1. Acesse: https://aws.amazon.com/pt/free/
2. Clique em **"Criar uma conta da AWS"**
3. Preencha:
   - Email
   - Nome da conta AWS (pode ser o nome da sua empresa)
   - Senha forte

### 1.2 Informações de Contato
- Escolha: **Pessoal** ou **Empresa**
- Preencha: Nome completo, telefone, endereço
- Aceite os termos

### 1.3 Informações de Pagamento
- Insira dados do cartão de crédito
- **Não se preocupe**: AWS tem plano gratuito generoso
- Cobra apenas R$ 1-5 para validação (estorna depois)

### 1.4 Verificação de Identidade
- Receberá ligação ou SMS com código
- Digite o código no site

### 1.5 Selecione o Plano
- Escolha: **Plano de suporte básico (gratuito)**

🎉 **Conta criada!** Pode demorar 5-15 minutos para ativar completamente.

---

## 2️⃣ Criar Usuário IAM (Acesso Programático)

### 2.1 Acessar Console IAM
1. Faça login no AWS Console: https://console.aws.amazon.com/
2. No campo de busca (topo), digite: **IAM**
3. Clique em **IAM** (Identity and Access Management)

### 2.2 Criar Novo Usuário
1. No menu lateral esquerdo, clique em **Users** (Usuários)
2. Clique no botão **Add users** (Adicionar usuários)
3. Preencha:
   - **User name**: `verify-kyc-user` (ou outro nome)
   - ✅ Marque: **Access key - Programmatic access** (Chave de acesso programático)
   - ❌ **NÃO** marque: Password (não precisa)
4. Clique em **Next: Permissions**

### 2.3 Adicionar Permissões
1. Clique na aba **Attach existing policies directly** (Anexar políticas existentes diretamente)
2. No campo de busca, digite: **textract**
3. ✅ Marque: **AmazonTextractFullAccess**
4. No campo de busca, digite: **rekognition**
5. ✅ Marque: **AmazonRekognitionFullAccess**
6. Clique em **Next: Tags**

### 2.4 Tags (Opcional)
- Pode pular clicando em **Next: Review**

### 2.5 Revisar e Criar
1. Revise as informações
2. Clique em **Create user**

### 2.6 **IMPORTANTE: Salvar Credenciais**
🚨 **ATENÇÃO**: Esta é a ÚNICA vez que você verá a Secret Access Key!

Você verá uma tela com:
```
Access key ID: AKIAIOSFODNN7EXAMPLE
Secret access key: wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
```

**COPIE E SALVE COM CUIDADO:**
- Clique em **Download .csv** (recomendado)
- OU copie e cole em um arquivo de texto seguro
- OU deixe esta aba aberta até configurar o .env

---

## 3️⃣ Configurar Credenciais no Projeto

### 3.1 Abrir o arquivo `.env`
Localize o arquivo `.env` na raiz do projeto.

### 3.2 Substituir as Credenciais
Substitua as linhas:

```env
# ANTES (valores de exemplo)
AWS_ACCESS_KEY_ID=sua_chave_aqui
AWS_SECRET_ACCESS_KEY=sua_chave_secreta_aqui
AWS_REGION=us-east-1

# DEPOIS (com seus valores reais)
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_REGION=us-east-1
```

**Observações:**
- ✅ Cole os valores EXATOS copiados do AWS Console
- ✅ NÃO adicione espaços ou aspas extras
- ✅ A região `us-east-1` (Virgínia) é boa para começar
- ⚠️ **NUNCA** compartilhe essas credenciais publicamente

### 3.3 Outras Configurações (Já Prontas)
```env
# Rekognition - Detecção Facial
AWS_REKOGNITION_COLLECTION=verify-kyc-faces

# Thresholds (Limites de Confiança)
FACE_MATCH_THRESHOLD=90          # 90% de similaridade para aprovar
OCR_CONFIDENCE_THRESHOLD=70       # 70% de confiança mínima no OCR
```

🔒 **Segurança**: Nunca faça commit do arquivo `.env` para Git!

---

## 4️⃣ Criar Collection no Rekognition (Anti-Fraude)

### 4.1 Via AWS CLI (Se tiver instalado)
```bash
aws rekognition create-collection --collection-id verify-kyc-faces --region us-east-1
```

### 4.2 OU Via Console AWS
1. Acesse: https://console.aws.amazon.com/rekognition/
2. No menu lateral, clique em **Collections**
3. Clique em **Create collection**
4. Nome: `verify-kyc-faces`
5. Clique em **Create collection**

### 4.3 O que é Collection?
É um banco de faces indexado. Quando você aprovar um cliente:
- A selfie dele é indexada na collection
- Futuras tentativas de cadastro com a mesma face são detectadas
- **Anti-fraude**: Evita que uma pessoa se cadastre múltiplas vezes

---

## 5️⃣ Configurar Alertas de Custo (IMPORTANTE!)

### 5.1 Criar Alerta de Billing
1. Acesse: https://console.aws.amazon.com/billing/
2. No menu lateral, clique em **Budgets**
3. Clique em **Create budget**
4. Escolha: **Cost budget**
5. Preencha:
   - **Budget name**: `Alerta-KYC`
   - **Period**: Monthly (Mensal)
   - **Budget amount**: `$10` (ou outro valor)
6. Configure alerta:
   - **Threshold**: `80%` (alerta aos $8)
   - Email: seu_email@exemplo.com
7. Clique em **Create budget**

### 5.2 Ativar Billing Alerts
1. Acesse: https://console.aws.amazon.com/billing/home#/preferences
2. ✅ Marque: **Receive Billing Alerts**
3. Clique em **Save preferences**

---

## 6️⃣ Upload para Hostinger (Via FTP)

### 6.1 Arquivos para Fazer Upload

#### Novos/Atualizados:
```
📁 Raiz do projeto:
├── composer.json (ATUALIZADO)
├── .env (COM CREDENCIAIS AWS)
├── ajax_validate_document.php (ATUALIZADO PARA AWS)
├── test_document_upload.php

📁 src/:
├── DocumentValidatorAWS.php (NOVO)
├── FaceValidator.php

📁 uploads/ (CRIAR PASTAS):
├── temp/
├── documentos/
```

### 6.2 Conexão FTP
1. Abra seu cliente FTP (FileZilla, WinSCP, etc.)
2. Configure:
   - **Host**: ftp.seusite.com.br (fornecido pela Hostinger)
   - **Usuário**: seu_usuario_ftp
   - **Senha**: sua_senha_ftp
   - **Porta**: 21
3. Conecte

### 6.3 Fazer Upload
1. Navegue até a pasta `public_html` (ou `www`)
2. Faça upload dos arquivos listados acima
3. **ATENÇÃO**: Certifique-se de:
   - ✅ Preservar estrutura de pastas (`src/`, `uploads/`)
   - ✅ Fazer upload em modo **TEXT** para PHP
   - ✅ Fazer upload em modo **BINARY** para imagens

### 6.4 Criar Pastas (Se não existirem)
No FTP, dentro de `uploads/`:
- Criar pasta: `temp`
- Criar pasta: `documentos`

### 6.5 Permissões
**Via FTP:**
1. Clique direito na pasta `uploads`
2. Escolha **File permissions** ou **CHMOD**
3. Configure: `755` (rwxr-xr-x)
4. ✅ Marque: **Apply to directories recursively**
5. OK

---

## 7️⃣ Instalar Dependências (Composer)

### 7.1 Via SSH (Se Disponível)
```bash
cd public_html
composer install --no-dev --optimize-autoloader
```

### 7.2 Via Painel Hostinger
1. Faça login no hPanel da Hostinger
2. Procure por **Terminal** ou **SSH Access**
3. Clique em **Open Terminal**
4. Execute:
```bash
cd public_html
composer install
```

### 7.3 Se Não Tiver Composer
**Instalar Composer primeiro:**
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
php composer.phar install
```

### 7.4 Verificar Instalação
Deve criar a pasta `vendor/` com a AWS SDK:
```
📁 vendor/
├── aws/
│   └── aws-sdk-php/
├── autoload.php
```

---

## 8️⃣ Criar Tabela no Banco de Dados

### 8.1 Acessar phpMyAdmin
1. Faça login no hPanel da Hostinger
2. Procure por **Databases** > **phpMyAdmin**
3. Selecione seu banco de dados

### 8.2 Executar SQL
1. Clique na aba **SQL**
2. Copie e cole o conteúdo do arquivo: `create_document_validations_table.sql`
3. Clique em **Go** ou **Executar**

### 8.3 Verificar
Na lista de tabelas, deve aparecer:
- `document_validations` (nova tabela)

---

## 9️⃣ Testar o Sistema

### 9.1 Acessar Página de Teste
```
https://seusite.com.br/test_document_upload.php
```

### 9.2 Testar OCR (Documentos)
1. Clique em **Escolher arquivo**
2. Selecione uma foto de:
   - RG (frente)
   - CNH
   - Documento com CPF/CNPJ
3. Clique em **Validar Documento**

**Resultado esperado:**
```json
{
  "success": true,
  "ocr_result": {
    "success": true,
    "text": "TEXTO EXTRAÍDO...",
    "confidence": 87.5,
    "blocks_detected": 25
  },
  "extracted_data": {
    "cpf": {
      "raw": "12345678900",
      "formatted": "123.456.789-00",
      "valid": true
    },
    "name": "JOÃO DA SILVA",
    "rg": "12.345.678-9"
  }
}
```

### 9.3 Testar Face Detection (Selfie)
1. Clique em **Escolher arquivo**
2. Selecione uma selfie nítida
3. Clique em **Validar Documento**

**Resultado esperado:**
```json
{
  "success": true,
  "face_result": {
    "success": true,
    "faces_detected": 1,
    "faces": [
      {
        "confidence": 99.9,
        "quality": {
          "brightness": 85.3,
          "sharpness": 92.1
        },
        "pose": {
          "pitch": 2.5,
          "roll": -1.3,
          "yaw": 0.8
        }
      }
    ]
  }
}
```

---

## 🔟 Monitorar Custos

### 10.1 Acompanhar Gastos
1. Acesse: https://console.aws.amazon.com/billing/
2. Dashboard mostra gastos atuais do mês
3. **Free Tier**: Mostra quanto você já usou do plano gratuito

### 10.2 Limites Gratuitos (Free Tier) - Detalhado

#### AWS Textract (OCR)
- ✅ **1.000 páginas/mês GRÁTIS** durante os primeiros **3 meses**
- Após 3 meses: **$1.50 por 1.000 páginas** ($0.0015 cada)
- Formatos suportados: JPG, PNG, PDF, TIFF
- Máximo 10 MB por arquivo

#### AWS Rekognition (Face)
- ✅ **5.000 detecções faciais/mês GRÁTIS** por **12 meses**
- ✅ **1.000 comparações faciais/mês GRÁTIS** por **12 meses**  
- ✅ **1.000 faces indexadas/mês GRÁTIS** (collections) por **12 meses**
- Após 12 meses: **$1.00 por 1.000 imagens**

### 10.3 Calculadora de Custos

#### Para seu volume (5.300 validações/mês):

**📅 Mês 1-3 (Ambos no Free Tier):**
```
Textract OCR:
  1.000 grátis
  4.300 pagos × $0.0015 = $6.45

Rekognition Face Detection:
  5.000 grátis ✅ (cobre tudo!)
  300 excedentes × $0.001 = $0.30

Rekognition Face Comparison:
  1.000 grátis
  4.300 pagos × $0.001 = $4.30

💰 TOTAL: $11.05/mês (R$ 55.25)
```

**📅 Mês 4-12 (Só Rekognition grátis):**
```
Textract OCR:
  5.300 × $0.0015 = $7.95

Rekognition (ainda no Free Tier):
  Face Detection: 5.000 grátis + 300 × $0.001 = $0.30
  Face Comparison: 1.000 grátis + 4.300 × $0.001 = $4.30

💰 TOTAL: $12.55/mês (R$ 62.75)
```

**📅 Após 12 meses (Tudo pago):**
```
Textract OCR:
  5.300 × $0.0015 = $7.95

Rekognition Face Detection:
  5.300 × $0.001 = $5.30

Rekognition Face Comparison:
  5.300 × $0.001 = $5.30

💰 TOTAL: $18.55/mês (R$ 92.75)
```

### 10.4 Como Economizar

#### ✅ Opção 1: Reduzir Volume (Primeiros Meses)
Se processar **≤ 1.000 docs/mês** nos primeiros **3 meses**:
- **100% GRATUITO** (tudo dentro do Free Tier)
- Use para validar seu modelo de negócio

#### ✅ Opção 2: Após Free Tier, Migrar para Azure
Após 12 meses, se volume for alto, considere **Azure Computer Vision**:
- **30.000 transações/mês GRÁTIS** (permanente!)
- Para 5.300 docs: apenas **$0.30/mês** (R$ 1.50)
- **97% mais barato** que AWS após Free Tier

#### ✅ Opção 3: VPS com Tesseract (Volume Alto)
Para volumes acima de 10.000/mês:
- VPS Contabo/Vultr: **R$ 20/mês fixo**
- Tesseract OCR instalado localmente (ilimitado)
- face-api.js para detecção facial (grátis)
- Sem custos variáveis por processamento

#### ❌ Não Recomendado: Rotacionar Contas
- Criar nova conta AWS a cada 12 meses para renovar Free Tier
- **Viola os termos de serviço da AWS**
- Risco de banimento permanente

---

## ⚠️ Troubleshooting (Solução de Problemas)

### Erro: "Invalid security token"
**Causa**: Credenciais AWS incorretas ou expiradas

**Solução:**
1. Verifique se copiou corretamente:
   - `AWS_ACCESS_KEY_ID`
   - `AWS_SECRET_ACCESS_KEY`
2. Sem espaços extras ou quebras de linha
3. Se necessário, crie novas credenciais no IAM

---

### Erro: "Access Denied"
**Causa**: Usuário IAM sem permissões

**Solução:**
1. Acesse AWS Console > IAM > Users
2. Clique no usuário `verify-kyc-user`
3. Aba **Permissions**
4. Adicione policies:
   - `AmazonTextractFullAccess`
   - `AmazonRekognitionFullAccess`

---

### Erro: "Region not found"
**Causa**: Região AWS inválida

**Solução:**
Edite `.env`:
```env
AWS_REGION=us-east-1  # Ou: sa-east-1 (São Paulo), us-west-2, etc.
```

Regiões recomendadas:
- `us-east-1` (Virgínia, EUA) - Mais barata, geralmente
- `sa-east-1` (São Paulo, Brasil) - Menor latência

---

### Erro: "Collection not found"
**Causa**: Collection do Rekognition não criada

**Solução:**
```bash
aws rekognition create-collection \
  --collection-id verify-kyc-faces \
  --region us-east-1
```

OU via Console AWS > Rekognition > Collections > Create

---

### Erro: "Vendor/autoload.php not found"
**Causa**: Composer não instalou as dependências

**Solução:**
```bash
cd public_html
composer install
```

Verificar se existe:
- `vendor/autoload.php`
- `vendor/aws/aws-sdk-php/`

---

### Erro: "Cannot write to uploads/"
**Causa**: Permissões de pasta incorretas

**Solução:**
Via FTP ou SSH:
```bash
chmod 755 uploads/
chmod 755 uploads/temp/
chmod 755 uploads/documentos/
```

---

### OCR retorna texto vazio
**Causas possíveis:**
1. Imagem muito escura/clara
2. Texto ilegível
3. Formato de arquivo não suportado

**Soluções:**
1. Use imagens nítidas, bem iluminadas
2. Formatos: JPG, PNG, PDF
3. Tamanho: 50KB - 5MB
4. Resolução mínima: 150 DPI

---

### Face Detection falha
**Causas:**
1. Rosto não visível
2. Face coberta (máscara, óculos escuros)
3. Imagem muito pequena

**Soluções:**
1. Selfie frontal, rosto descoberto
2. Boa iluminação
3. Tamanho mínimo: 80x80 pixels por face
4. Fundo neutro ajuda

---

## 📞 Suporte

### AWS Support
- **Documentação**: https://docs.aws.amazon.com/
- **Fórum**: https://forums.aws.amazon.com/
- **Support Center**: https://console.aws.amazon.com/support/

### Textract
- **Guia**: https://docs.aws.amazon.com/textract/
- **Limites**: https://docs.aws.amazon.com/textract/latest/dg/limits.html

### Rekognition
- **Guia**: https://docs.aws.amazon.com/rekognition/
- **Facial Analysis**: https://docs.aws.amazon.com/rekognition/latest/dg/faces.html

---

## 🎯 Próximos Passos

1. ✅ **Conta AWS criada**
2. ✅ **Usuário IAM configurado**
3. ✅ **Credenciais salvas no .env**
4. ✅ **Arquivos enviados via FTP**
5. ✅ **Composer install executado**
6. ✅ **Tabela criada no banco**
7. ✅ **Sistema testado**

### Integração com KYC
Próximo passo: integrar com o fluxo KYC principal:
- Adicionar upload de documentos em `kyc.php`
- Auto-preencher campos com dados extraídos
- Validar face na avaliação (`kyc_evaluate.php`)
- Implementar anti-fraude com collections

---

## 📊 Resumo de Custos

### Comparação por Volume e Período

| Volume/Mês | Mês 1-3 (Free Tier) | Mês 4-12 (Parcial) | Após 12m (Tudo Pago) |
|------------|---------------------|--------------------|-----------------------|
| **≤ 1.000** | **R$ 0,00** 🎉 | R$ 7,50 | R$ 17,50 |
| **2.000** | R$ 8,25 | R$ 28,50 | R$ 35,00 |
| **5.300** | R$ 55,25 💚 | R$ 62,75 | R$ 92,75 |
| **10.000** | R$ 107,50 | R$ 120,00 | R$ 175,00 |
| **25.000** | R$ 282,50 | R$ 300,00 | R$ 437,50 |

### Detalhamento do Free Tier

#### ✅ O que está incluído GRÁTIS:

**Primeiros 3 meses:**
- 1.000 documentos OCR (Textract)
- 5.000 detecções faciais (Rekognition)
- 1.000 comparações faciais (Rekognition)

**Mês 4-12 (só Rekognition):**
- 5.000 detecções faciais/mês
- 1.000 comparações faciais/mês
- 1.000 faces indexadas (collections)

#### 🎯 Máximo SEM CUSTO:

**1.000 validações completas/mês** nos primeiros **3 meses** = **R$ 0,00**

Inclui:
- ✅ OCR de documento (CPF, RG, CNH, CNPJ)
- ✅ Detecção facial na selfie
- ✅ Comparação face do documento vs selfie
- ✅ Análise de qualidade (brightness, sharpness)

### 💡 Recomendações por Fase

#### Fase 1: Validação (0-3 meses)
- **Mantenha < 1.000 validações/mês**
- **Custo: R$ 0,00** (100% Free Tier)
- Use para testar o sistema e validar modelo de negócio

#### Fase 2: Crescimento (4-12 meses)
- **AWS com Free Tier parcial**
- Rekognition ainda grátis (5k faces/mês)
- Custo controlado durante expansão

#### Fase 3: Escala (após 12 meses)
**Se volume < 5.000/mês:**
- Migre para **Azure Computer Vision**
- R$ 1,50/mês (30k grátis)
- Economia de **98%**

**Se volume > 10.000/mês:**
- Migre para **VPS com Tesseract**
- R$ 20/mês fixo (ilimitado)
- Previsibilidade de custos

**Se precisa de recursos avançados:**
- Mantenha AWS
- Anti-fraude, ML, análise avançada
- Melhor custo-benefício para features premium

### 🔍 Monitoramento de Gastos

**Configure SEMPRE alertas de billing:**
1. Alerta aos 50% do orçamento
2. Alerta aos 80% do orçamento
3. Email de notificação diária

**Budget recomendado inicial:** $15/mês (R$ 75)

---

### 🆚 Comparação AWS vs Alternativas (5.300 docs/mês)

| Provedor | Mês 1-3 | Mês 4-12 | Após 12m | Features |
|----------|---------|----------|----------|----------|
| **AWS** | R$ 55 💚 | R$ 63 | R$ 93 | 🏆 Melhor anti-fraude |
| **Azure** | R$ 1,50 🤑 | R$ 1,50 | R$ 1,50 | 🥇 Mais barato |
| **Google** | R$ 97 | R$ 97 | R$ 97 | ⚠️ Sem face comparison |
| **OCR.space** | R$ 0 | R$ 0 | R$ 0 | ⚠️ 25k/mês grátis, sem face |
| **VPS Próprio** | R$ 20 | R$ 20 | R$ 20 | 🔧 Requer manutenção |

**Legenda:**
- 💚 Bom custo-benefício
- 🤑 Mais econômico
- 🏆 Melhores recursos
- ⚠️ Limitações importantes

---

**Free Tier AWS:**
- ✅ Textract: 1.000 docs/mês grátis por 3 meses
- ✅ Rekognition: 5.000 faces/mês grátis por 12 meses

---

✅ **Sistema pronto para produção!**

Se tiver dúvidas, consulte a documentação AWS ou entre em contato com o suporte.
