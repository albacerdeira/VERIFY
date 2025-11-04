# 🚀 Guia de Instalação - Sistema de Validação de Documentos KYC

## 📋 Visão Geral

Sistema de validação automática de documentos usando:
- **AWS Rekognition** para validação de selfies e comparação facial
- **Tesseract OCR** para extração de texto de documentos (RG, CNH, CPF, comprovantes)

---

## ✅ Pré-requisitos

### 1. PHP e Extensões
```bash
# Versão mínima: PHP 7.4
php -v

# Extensões necessárias:
- php-curl (para AWS SDK)
- php-json
- php-mbstring
- php-imagick (para conversão PDF → Imagem)
```

### 2. Composer
```bash
# Verificar se está instalado:
composer --version

# Se não estiver instalado, baixar de: https://getcomposer.org/download/
```

### 3. Conta AWS
- Criar conta em: https://aws.amazon.com/
- Ativar serviço AWS Rekognition
- Criar usuário IAM com permissões de Rekognition

---

## 📦 PASSO 1: Instalar Dependências do Composer

### 1.1. Editar composer.json

Abra o arquivo `composer.json` e adicione na seção `"require"`:

```json
{
    "require": {
        "aws/aws-sdk-php": "^3.0",
        "thiagoalessio/tesseract_ocr": "^2.13"
    },
    "autoload": {
        "psr-4": {
            "Verify\\": "src/"
        }
    }
}
```

### 1.2. Instalar dependências

```bash
# No terminal, dentro da pasta do projeto:
composer install

# Ou se já tinha composer.json anterior:
composer update
```

Aguarde o download (pode demorar alguns minutos).

---

## 🖼️ PASSO 2: Instalar Tesseract OCR

### Windows

1. Baixar instalador:
   - https://github.com/UB-Mannheim/tesseract/wiki
   - Escolher versão: `tesseract-ocr-w64-setup-5.x.x.exe`

2. Instalar com idioma Português:
   - Durante instalação, marcar **"Portuguese"** language pack
   - Anotar o caminho de instalação (geralmente `C:\Program Files\Tesseract-OCR`)

3. Adicionar ao PATH (Opcional):
   - Painel de Controle → Sistema → Variáveis de Ambiente
   - Adicionar `C:\Program Files\Tesseract-OCR` ao PATH

### Linux (Ubuntu/Debian)

```bash
sudo apt-get update
sudo apt-get install tesseract-ocr tesseract-ocr-por
```

### Linux (CentOS/RHEL)

```bash
sudo yum install tesseract tesseract-langpack-por
```

### macOS

```bash
brew install tesseract tesseract-lang
```

### Verificar instalação:

```bash
tesseract --version
tesseract --list-langs  # Deve listar "por" (português)
```

---

## 🔑 PASSO 3: Configurar Credenciais AWS

### 3.1. Criar usuário IAM na AWS

1. Acessar AWS Console: https://console.aws.amazon.com/iam/
2. Ir em **Users** → **Add User**
3. Nome: `verify-kyc-rekognition`
4. Marcar: **Programmatic access**
5. Anexar política: **AmazonRekognitionFullAccess**
6. Criar usuário e **SALVAR**:
   - Access Key ID
   - Secret Access Key

### 3.2. Configurar .env

1. Copiar arquivo de exemplo:
```bash
cp .env.example .env
```

2. Editar `.env` com suas credenciais:
```env
# AWS Rekognition
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_REGION=us-east-1
AWS_REKOGNITION_COLLECTION=verify-kyc-faces

# Tesseract OCR
# Windows:
TESSERACT_PATH=C:\Program Files\Tesseract-OCR\tesseract.exe
# Linux/Mac:
# TESSERACT_PATH=/usr/bin/tesseract

TESSERACT_LANG=por

# Thresholds
FACE_MATCH_THRESHOLD=90
OCR_CONFIDENCE_THRESHOLD=70
```

**⚠️ IMPORTANTE:** Nunca commitar o arquivo `.env` no Git! Ele já está no `.gitignore`.

---

## 🧪 PASSO 4: Testar Instalação

### 4.1. Testar Tesseract

Criar arquivo `test_tesseract.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/DocumentValidator.php';

use Verify\DocumentValidator;

$validator = new DocumentValidator();

// Teste com uma imagem de documento (ajuste o caminho)
$result = $validator->extractText(__DIR__ . '/uploads/test_document.jpg');

if ($result['success']) {
    echo "✅ Tesseract funcionando!\n";
    echo "Confiança: {$result['confidence']}%\n";
    echo "Texto: " . substr($result['text'], 0, 200) . "...\n";
} else {
    echo "❌ Erro: {$result['error']}\n";
}
```

Executar:
```bash
php test_tesseract.php
```

### 4.2. Testar AWS Rekognition

Criar arquivo `test_aws.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/FaceValidator.php';

use Verify\FaceValidator;

$validator = new FaceValidator();

// Teste com uma selfie (ajuste o caminho)
$result = $validator->detectFace(__DIR__ . '/uploads/test_selfie.jpg');

if ($result['success']) {
    echo "✅ AWS Rekognition funcionando!\n";
    echo "Faces detectadas: {$result['face_count']}\n";
    echo "Score de qualidade: {$result['quality']['overall_score']}/100\n";
} else {
    echo "❌ Erro: {$result['error']}\n";
}
```

Executar:
```bash
php test_aws.php
```

### 4.3. Teste Completo

Executar o validador de exemplo:

```bash
php validate_kyc_documents.php
```

---

## 🔧 Solução de Problemas

### Erro: "Class 'TesseractOCR' not found"
- Execute: `composer install`
- Verifique se `vendor/autoload.php` está sendo carregado

### Erro: "tesseract not found"
- Windows: Verifique o caminho em `.env` → `TESSERACT_PATH`
- Linux: Execute `which tesseract` e atualize `.env`
- Verifique se o idioma português está instalado: `tesseract --list-langs`

### Erro: "AWS credentials not found"
- Verifique se `.env` existe (copiar de `.env.example`)
- Confirme que `AWS_ACCESS_KEY_ID` e `AWS_SECRET_ACCESS_KEY` estão preenchidos
- Teste as credenciais no AWS CLI: `aws sts get-caller-identity`

### Erro: "InvalidParameterException: Collection not found"
- Não se preocupe! O sistema cria a collection automaticamente na primeira execução
- Se persistir, criar manualmente via AWS CLI:
  ```bash
  aws rekognition create-collection --collection-id verify-kyc-faces --region us-east-1
  ```

### Erro: "Class 'Imagick' not found"
- Instalar extensão PHP Imagick:
  ```bash
  # Ubuntu/Debian:
  sudo apt-get install php-imagick
  
  # Windows: Baixar DLL de https://windows.php.net/downloads/pecl/releases/imagick/
  # Adicionar ao php.ini: extension=imagick
  ```

### Baixa confiança no OCR (<50%)
- Melhorar qualidade das imagens (mínimo 300 DPI)
- Garantir boa iluminação
- Evitar fotos tremidas ou desfocadas
- Verificar se o documento está na horizontal

### Faces não estão correspondendo
- Verificar qualidade das fotos (usar `analyzeFaceQuality()`)
- Ajustar threshold em `.env`: `FACE_MATCH_THRESHOLD=85` (diminuir para ser menos rigoroso)
- Garantir que ambas as fotos têm boa iluminação
- Evitar óculos escuros na selfie

---

## 📊 Custos AWS

### Rekognition - Preços (região us-east-1)

| Operação | Preço por 1.000 imagens | Custo por validação KYC* |
|----------|-------------------------|--------------------------|
| DetectFaces | $1.00 | $0.001 |
| CompareFaces | $1.00 | $0.002 |
| SearchFacesByImage | $1.00 | $0.001 |
| IndexFaces | $1.00 | $0.001 |
| **Total por KYC** | - | **~$0.005 (R$ 0,025)** |

*Estimativa: 2 DetectFaces + 1 CompareFaces + 1 SearchFacesByImage + 1 IndexFaces por cliente

### Free Tier
- **5.000 imagens/mês grátis** no primeiro ano
- Depois: 1.000 imagens/mês grátis permanentemente

### Reduzir custos
- Não indexar todas as faces (apenas fazer comparação direta)
- Processar em batch (aguardar acumular X clientes)
- Usar cache de resultados para evitar reprocessamento

---

## 🔐 Segurança

### Proteção de Credenciais
- ✅ `.env` está no `.gitignore`
- ✅ Nunca expor chaves AWS em código
- ✅ Rotacionar Access Keys periodicamente (AWS Console)

### Proteção de Arquivos
- Adicionar ao `.htaccess`:
  ```apache
  <Files ".env">
      Require all denied
  </Files>
  ```

### LGPD - Dados Sensíveis
- Imagens biométricas são dados sensíveis
- Obter consentimento explícito do cliente
- Permitir exclusão de dados (implementar `FaceValidator->deleteFace()`)
- Não compartilhar com terceiros sem autorização
- Criptografar arquivos em repouso

---

## 📚 Próximos Passos

Após instalação bem-sucedida:

1. **Criar endpoints AJAX**
   - `ajax_validate_document.php`
   - `ajax_validate_selfie.php`
   - `ajax_compare_faces.php`

2. **Integrar ao formulário KYC**
   - Adicionar upload de selfie em `kyc.php`
   - Processar validação em `kyc_submit.php`

3. **Criar interface de revisão**
   - Mostrar resultados de OCR em `kyc_evaluate.php`
   - Exibir scores de confiança
   - Permitir override manual

4. **Implementar logging**
   - Criar tabela `document_validations`
   - Criar tabela `face_validations`
   - Registrar todas as tentativas

---

## 📞 Suporte

### Documentação Oficial
- AWS Rekognition: https://docs.aws.amazon.com/rekognition/
- Tesseract OCR: https://tesseract-ocr.github.io/
- Tesseract OCR PHP: https://github.com/thiagoalessio/tesseract-ocr-for-php

### Problemas Comuns
- Verifique os logs PHP: `tail -f /var/log/apache2/error.log`
- Ative debug mode: adicionar `ini_set('display_errors', 1);` nos scripts de teste
- Verifique permissões da pasta `uploads/`: `chmod 755 uploads`

---

✨ **Sistema pronto para uso após seguir todos os passos!**
