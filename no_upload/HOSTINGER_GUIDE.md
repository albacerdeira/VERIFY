# 🌐 Sistema OCR para Hostinger (Hospedagem Compartilhada)

## ⚠️ LIMITAÇÃO IMPORTANTE

**Hospedagem compartilhada (Hostinger) NÃO permite:**
- ❌ Instalar Tesseract OCR
- ❌ Acesso SSH/Terminal
- ❌ Instalar binários do sistema
- ❌ sudo/apt-get

## 🔄 SOLUÇÕES ALTERNATIVAS

### Opção 1: API Externa de OCR (Recomendado) ⭐

Use serviços de OCR na nuvem que funcionam via API:

#### **Google Cloud Vision API** (Melhor para português)
- ✅ Funciona em hospedagem compartilhada
- ✅ 1000 requisições/mês grátis
- ✅ Excelente precisão em português
- ✅ Suporta PDF, JPG, PNG

**Como configurar:**
1. Criar conta: https://cloud.google.com/vision
2. Ativar Vision API
3. Criar chave API
4. Adicionar no `.env`:
```env
GOOGLE_VISION_API_KEY=sua_chave_aqui
```

#### **OCR.space API** (Gratuito, mais simples)
- ✅ 25.000 requisições/mês grátis
- ✅ Não precisa cartão de crédito
- ✅ API REST simples
- ⚠️ Menos preciso que Google

**Como configurar:**
1. Criar conta: https://ocr.space/ocrapi
2. Pegar API Key
3. Adicionar no `.env`:
```env
OCR_SPACE_API_KEY=sua_chave_aqui
```

---

### Opção 2: Usar Servidor VPS Separado 💰

Se precisar de Tesseract local:

1. **Contratar VPS barato:**
   - DigitalOcean ($4/mês)
   - Vultr ($2.50/mês)
   - Contabo ($4/mês)

2. **Instalar Tesseract no VPS**

3. **Criar API REST no VPS** que recebe imagens e retorna texto

4. **Seu site Hostinger chama a API do VPS**

---

### Opção 3: Hostinger VPS ou Cloud 💎

Migrar para plano que permite instalação:
- Hostinger VPS (a partir de $3.99/mês)
- Tem acesso SSH completo
- Pode instalar Tesseract

---

## 📝 CÓDIGO PARA HOSTINGER (API Externa)

Vou criar uma versão que funciona com OCR.space (gratuito):

### 1. Criar `src/DocumentValidatorCloud.php`

```php
<?php
namespace Verify;

class DocumentValidatorCloud {
    private $apiKey;
    private $apiUrl = 'https://api.ocr.space/parse/image';
    
    public function __construct() {
        $this->apiKey = getenv('OCR_SPACE_API_KEY') ?: '';
    }
    
    public function extractText($filePath) {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Arquivo não encontrado'
            ];
        }
        
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'OCR_SPACE_API_KEY não configurada no .env'
            ];
        }
        
        // Prepara requisição
        $postData = [
            'apikey' => $this->apiKey,
            'language' => 'por',
            'isOverlayRequired' => false,
            'file' => new \CURLFile($filePath)
        ];
        
        // Envia para API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'Erro na API OCR: HTTP ' . $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result['IsErroredOnProcessing']) {
            $text = $result['ParsedResults'][0]['ParsedText'] ?? '';
            $confidence = 75; // OCR.space não retorna confidence preciso
            
            return [
                'success' => true,
                'text' => $text,
                'confidence' => $confidence
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['ErrorMessage'][0] ?? 'Erro desconhecido'
        ];
    }
    
    // Mesmos métodos de extração que DocumentValidator
    public function extractCPF($text) {
        // ... (mesmo código do DocumentValidator.php)
    }
    
    public function extractCNPJ($text) {
        // ... (mesmo código)
    }
    
    public function extractRG($text) {
        // ... (mesmo código)
    }
    
    public function extractCNH($text) {
        // ... (mesmo código)
    }
    
    public function extractName($text) {
        // ... (mesmo código)
    }
}
```

### 2. Atualizar `.env`

```env
# OCR Cloud API (OCR.space - Gratuito)
OCR_SPACE_API_KEY=K88888888888888
# Pegue sua chave em: https://ocr.space/ocrapi/freekey

# AWS Rekognition (para depois)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_REGION=us-east-1
AWS_REKOGNITION_COLLECTION=verify-kyc-faces

# Thresholds
FACE_MATCH_THRESHOLD=90
OCR_CONFIDENCE_THRESHOLD=70
```

### 3. Atualizar `ajax_validate_document.php`

No início do arquivo, trocar:
```php
// DE:
require_once __DIR__ . '/src/DocumentValidator.php';
use Verify\DocumentValidator;
$validator = new DocumentValidator();

// PARA:
require_once __DIR__ . '/src/DocumentValidatorCloud.php';
use Verify\DocumentValidatorCloud;
$validator = new DocumentValidatorCloud();
```

---

## 📤 UPLOAD VIA FTP (Hostinger)

### Arquivos para Subir:

```
✅ /src/DocumentValidatorCloud.php  (novo)
✅ /ajax_validate_document.php      (atualizado)
✅ /test_document_upload.php
✅ /.env                             (atualizado com API key)
✅ /vendor/                          (pasta do Composer)
```

### Passos no Hostinger:

1. **Acessar painel Hostinger**
2. **Ir em "Gerenciador de Arquivos"** ou usar FTP (FileZilla)
3. **Navegar até public_html** (ou pasta do seu site)
4. **Upload dos arquivos** via drag & drop
5. **Criar pastas:**
   - `uploads/temp/`
   - `uploads/documentos/`
   - Definir permissão 755

6. **phpMyAdmin:**
   - Abrir SQL
   - Copiar conteúdo de `create_document_validations_table.sql`
   - Executar

7. **Composer no Hostinger:**
```bash
# No Terminal SSH do Hostinger (se tiver acesso):
cd public_html
composer install

# OU usar Composer via painel Hostinger:
# Alguns planos têm botão "Composer Install" no painel
```

---

## 🆓 PEGAR API KEY GRATUITA

### OCR.space (Recomendado para Hostinger):

1. Acesse: https://ocr.space/ocrapi/freekey
2. Preencha email
3. Receberá chave por email
4. Copie e cole no `.env`

**Limites Grátis:**
- 25.000 requisições/mês
- Máx 1MB por imagem
- Sem cartão de crédito

### Google Vision (Melhor precisão):

1. Acesse: https://console.cloud.google.com/
2. Criar projeto
3. Ativar "Vision API"
4. Criar credenciais (API Key)
5. Adicionar no `.env`

**Limites Grátis:**
- 1.000 requisições/mês
- Depois: $1.50 por 1000 imagens

---

## 🧪 TESTAR

1. Acessar: `https://seusite.hostinger.com.br/test_document_upload`
2. Upload de documento teste
3. Sistema enviará para API OCR.space
4. Receberá dados extraídos

---

## 💡 RECOMENDAÇÃO FINAL

Para **Hostinger compartilhada**, use:

1. **OCR.space** (gratuito, 25k/mês) para testes
2. **Google Vision API** se precisar mais precisão
3. Considere **migrar para VPS** se crescer muito

---

## 📊 COMPARAÇÃO

| Opção | Custo | Precisão | Limite Grátis | Hostinger? |
|-------|-------|----------|---------------|------------|
| **Tesseract Local** | Grátis | ⭐⭐⭐⭐ | Ilimitado | ❌ Não |
| **OCR.space** | Grátis | ⭐⭐⭐ | 25k/mês | ✅ Sim |
| **Google Vision** | Grátis* | ⭐⭐⭐⭐⭐ | 1k/mês | ✅ Sim |
| **Hostinger VPS** | $3.99/mês | ⭐⭐⭐⭐ | Ilimitado | ✅ Sim |

---

Quer que eu crie o `DocumentValidatorCloud.php` completo para você usar no Hostinger?
