# 🚀 Como Testar o Sistema OCR no Site

## ✅ O que você já tem pronto:

1. ✅ Tesseract OCR instalado no Windows
2. ✅ Classes PHP criadas (`src/DocumentValidator.php`)
3. ✅ Endpoint AJAX (`ajax_validate_document.php`)
4. ✅ Página de teste (`test_document_upload.php`)
5. ✅ Arquivo `.env` configurado

---

## 📤 Passo 1: Fazer Upload dos Arquivos

Suba para o servidor os seguintes arquivos:

```
/vendor/                           (pasta do Composer - IMPORTANTE!)
/src/DocumentValidator.php         (classe OCR)
/src/FaceValidator.php             (classe AWS - para usar depois)
/ajax_validate_document.php        (endpoint de validação)
/test_document_upload.php          (página de teste)
/.env                              (configurações)
```

**⚠️ IMPORTANTE:** Se o servidor for **Linux**, você precisa editar o `.env`:

```bash
# Linux:
TESSERACT_PATH=/usr/bin/tesseract

# Windows (seu computador local):
TESSERACT_PATH=C:\Program Files\Tesseract-OCR\tesseract.exe
```

---

## 🖥️ Passo 2: Instalar Tesseract no Servidor

### Se o servidor for Linux (mais comum):

```bash
# Conecte via SSH e execute:
sudo apt-get update
sudo apt-get install tesseract-ocr tesseract-ocr-por

# Verifique instalação:
tesseract --version
tesseract --list-langs  # Deve mostrar "por"
```

### Se o servidor for Windows:

- Baixar: https://github.com/UB-Mannheim/tesseract/wiki
- Instalar com idioma **Portuguese**
- Anotar o caminho de instalação

---

## 📦 Passo 3: Instalar Dependências do Composer

No servidor, via SSH ou terminal:

```bash
cd /caminho/do/seu/site/
composer install
```

Isso vai instalar:
- `thiagoalessio/tesseract_ocr` - Biblioteca PHP para Tesseract
- `aws/aws-sdk-php` - SDK da AWS (para usar depois)

---

## 🧪 Passo 4: Testar

1. Acesse no navegador:
   ```
   https://seusite.com.br/test_document_upload.php
   ```

2. Faça upload de um documento:
   - RG (frente ou verso)
   - CNH
   - CPF
   - Comprovante de residência
   - Formatos: JPG, PNG ou PDF

3. Clique em **"Processar"**

4. O sistema vai mostrar:
   - ✅ Score de confiança (0-100%)
   - 📋 Dados extraídos (CPF, RG, CNH, Nome)
   - 📄 Prévia do texto completo

---

## 🔧 Solução de Problemas

### Erro: "Tesseract not found"
**Causa:** Tesseract não instalado ou path incorreto

**Solução:**
1. Linux: `which tesseract` para ver o caminho
2. Edite `.env` com o caminho correto
3. Verifique se português está instalado: `tesseract --list-langs`

### Erro: "Class 'TesseractOCR' not found"
**Causa:** Composer não executado

**Solução:**
```bash
cd /caminho/do/site/
composer install
```

### Erro: "Permission denied"
**Causa:** Pasta `uploads/` sem permissão

**Solução:**
```bash
chmod 755 uploads/
chmod 755 uploads/temp/
chmod 755 uploads/documentos/
```

### Confiança muito baixa (<50%)
**Causa:** Documento com qualidade ruim

**Solução:**
- Use fotos com boa iluminação
- Evite fotos tremidas
- Prefira scanner ou câmera boa
- Mínimo 300 DPI para PDFs

---

## 📊 Próximos Passos (após testes)

1. **Integrar ao KYC:**
   - Adicionar upload de documentos em `kyc.php`
   - Processar automaticamente em `kyc_submit.php`
   - Mostrar dados extraídos em `kyc_evaluate.php`

2. **Criar tabela de logs:**
   ```bash
   # Executar SQL:
   mysql -u usuario -p database < create_document_validations_table.sql
   ```

3. **AWS Rekognition (opcional):**
   - Criar conta AWS
   - Configurar credenciais no `.env`
   - Testar comparação de selfies

---

## 📞 Suporte Rápido

### Verificar se Tesseract está funcionando:
```bash
tesseract --version
```

### Verificar idiomas instalados:
```bash
tesseract --list-langs
```

### Testar OCR manualmente:
```bash
tesseract documento.jpg saida -l por
cat saida.txt
```

### Verificar permissões PHP:
```php
<?php
echo shell_exec('tesseract --version');
echo shell_exec('which tesseract');
?>
```

---

✨ **Sistema pronto para testes no site!**

Se funcionar, podemos integrar com o fluxo KYC completo.
