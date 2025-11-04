# ✅ Sistema OCR - Corrigido e Pronto para Upload

## 🎯 O Que Foi Feito

1. ✅ **SQL Corrigido** - `create_document_validations_table.sql`
   - Ajustado para a estrutura real do banco (kyc_clientes)
   - Removidas foreign keys problemáticas
   - Adicionados campos: `file_size`, `mime_type`, `updated_at`
   - Charset correto: `utf8mb4_general_ci` (igual ao seu banco)

2. ✅ **Classes PHP** - Prontas para uso:
   - `src/DocumentValidator.php` - OCR com Tesseract
   - `src/FaceValidator.php` - AWS Rekognition (para depois)

3. ✅ **Endpoints** - `ajax_validate_document.php`
   - Recebe uploads via POST
   - Processa com OCR
   - Retorna JSON com dados extraídos

4. ✅ **Interface de Teste** - `test_document_upload.php`
   - Drag & drop
   - Preview de arquivo
   - Resultados visuais

---

## 📤 Checklist de Upload (em ordem)

### 1️⃣ Arquivos PHP
```
✅ /src/DocumentValidator.php
✅ /ajax_validate_document.php
✅ /test_document_upload.php
✅ /.env (já criado com config Windows)
```

### 2️⃣ No Servidor (SSH/Terminal)

**A. Instalar Tesseract OCR:**
```bash
# Linux (Ubuntu/Debian):
sudo apt-get update
sudo apt-get install tesseract-ocr tesseract-ocr-por

# Verificar:
tesseract --version
tesseract --list-langs  # Deve mostrar "por"
```

**B. Atualizar .env no servidor:**
```bash
# Se for Linux, edite o .env:
nano .env

# Mude de:
TESSERACT_PATH=C:\Program Files\Tesseract-OCR\tesseract.exe

# Para:
TESSERACT_PATH=/usr/bin/tesseract
```

**C. Instalar dependências Composer:**
```bash
cd /caminho/do/seu/site/
composer install
```

**D. Criar pastas de upload:**
```bash
mkdir -p uploads/temp uploads/documentos
chmod 755 uploads uploads/temp uploads/documentos
```

**E. Executar SQL:**
```bash
# Opção 1: Via phpMyAdmin
# - Abra o arquivo create_document_validations_table.sql
# - Cole no SQL do phpMyAdmin
# - Execute

# Opção 2: Via linha de comando
mysql -u seu_usuario -p seu_banco < create_document_validations_table.sql
```

---

## 🧪 Testar no Site

1. Acesse: `https://seusite.com.br/test_document_upload`

2. Faça upload de um documento (RG, CNH, CPF, Comprovante)

3. Clique em **"Processar"**

4. O sistema vai mostrar:
   - ✅ Score de confiança (0-100%)
   - 📋 CPF extraído (com validação)
   - 📋 RG/CNH se houver
   - 📋 Nome extraído
   - 📄 Prévia do texto completo

---

## 🔧 Resolução de Problemas

### ❌ Erro: "Tesseract not found"

**Solução:**
```bash
# Verifique onde está instalado:
which tesseract

# Copie o caminho e atualize no .env:
TESSERACT_PATH=/usr/bin/tesseract  # ou o caminho que aparecer
```

### ❌ Erro: "Class 'TesseractOCR' not found"

**Solução:**
```bash
cd /caminho/do/site/
composer install
# ou se já instalou antes:
composer update
```

### ❌ Erro: "Permission denied" ao salvar arquivo

**Solução:**
```bash
chmod 755 uploads
chmod 755 uploads/temp
chmod 755 uploads/documentos

# Ou dar permissão total (menos seguro):
chmod 777 uploads -R
```

### ❌ Confiança muito baixa (<50%)

**Causas:**
- Foto tremida/desfocada
- Iluminação ruim
- Documento dobrado/amassado
- Qualidade da câmera ruim

**Solução:**
- Use scanner ou câmera de alta qualidade
- Boa iluminação (luz natural é melhor)
- Documento plano e limpo
- Mínimo 300 DPI para PDFs

### ❌ Erro SQL ao criar tabela

**Se aparecer erro de Foreign Key:**
```sql
-- Execute este SQL alternativo (sem foreign keys):
CREATE TABLE IF NOT EXISTS `document_validations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) DEFAULT NULL,
  `kyc_empresa_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `confidence_score` int(11) DEFAULT NULL,
  `extracted_data` text DEFAULT NULL,
  `text_content` text DEFAULT NULL,
  `validation_status` enum('pending','approved','rejected','review_needed') DEFAULT 'pending',
  `validation_notes` text DEFAULT NULL,
  `validated_by_user_id` int(11) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_kyc_empresa_id` (`kyc_empresa_id`),
  KEY `idx_validation_status` (`validation_status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

---

## 📊 O Que o Sistema Faz

### Documentos Suportados:
- ✅ RG (frente e verso)
- ✅ CNH
- ✅ CPF
- ✅ Comprovantes de residência
- ✅ Contratos sociais
- ✅ Documentos CNPJ

### Dados Extraídos Automaticamente:
- 📋 CPF (com validação de dígitos)
- 📋 CNPJ (com validação)
- 📋 RG (vários formatos estaduais)
- 📋 CNH (11 dígitos)
- 📋 Nomes (busca "NOME:" ou "TITULAR:")
- 📄 Texto completo do documento

### Formatos Aceitos:
- 📷 JPG/JPEG
- 🖼️ PNG
- 📄 PDF (converte automaticamente para imagem)

### Limite:
- 📦 10MB por arquivo

---

## 🎯 Próximos Passos (depois que funcionar)

1. **Integrar ao formulário KYC:**
   - Adicionar upload em `kyc.php`
   - Auto-preencher campos com dados extraídos
   - Validar se dados batem com o cadastrado

2. **Painel do analista:**
   - Listar documentos validados
   - Mostrar score de confiança
   - Permitir aprovar/rejeitar
   - Adicionar observações

3. **AWS Rekognition (opcional):**
   - Validação de selfies
   - Comparação selfie vs documento
   - Detecção de duplicatas (anti-fraude)
   - Análise de qualidade da foto

---

## 📞 Comandos Úteis

### Verificar instalação:
```bash
# Tesseract
tesseract --version
tesseract --list-langs

# PHP
php -v
php -m | grep imagick  # Verifica se Imagick está instalado

# Composer
composer --version
```

### Testar OCR manualmente:
```bash
tesseract documento.jpg saida -l por
cat saida.txt
```

### Ver logs de erro PHP:
```bash
tail -f /var/log/apache2/error.log
# ou
tail -f /var/log/nginx/error.log
```

---

✨ **Sistema pronto para testes!**

Depois que testar e funcionar, me avise para continuarmos com a integração no KYC principal.
