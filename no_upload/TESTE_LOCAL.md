# 🖥️ Como Testar Localmente (Servidor PHP Embutido)

## Opção 1: Servidor PHP Built-in (Simples)

Se você tem PHP instalado localmente:

```powershell
# No PowerShell, na pasta do projeto:
cd "C:\Users\albac\Downloads\fdbank\teste servidor 29_10\consulta_cnpj"

# Iniciar servidor PHP na porta 8000:
php -S localhost:8000

# Acesse no navegador:
# http://localhost:8000/test_document_upload.php
```

**Requisitos:**
- PHP instalado (qualquer versão 7.4+)
- Tesseract OCR instalado
- Composer executado (`composer install`)

---

## Opção 2: XAMPP (Recomendado para Windows)

### Instalação:
1. Baixar XAMPP: https://www.apachefriends.org/
2. Instalar (default: `C:\xampp`)
3. Copiar projeto para `C:\xampp\htdocs\verify\`

### Configuração:
```powershell
# Abrir XAMPP Control Panel
# Iniciar Apache
# Iniciar MySQL (se precisar do banco)
```

### Acessar:
```
http://localhost/verify/test_document_upload.php
```

---

## Opção 3: Laragon (Mais Rápido)

### Instalação:
1. Baixar: https://laragon.org/download/
2. Instalar e iniciar
3. Copiar projeto para `C:\laragon\www\verify\`

### Acessar:
```
http://verify.test/test_document_upload.php
```

---

## 🔧 Teste Sem Servidor (Direto)

Se quiser testar apenas o OCR sem interface web:

```powershell
# Criar arquivo de teste simples:
cd "C:\Users\albac\Downloads\fdbank\teste servidor 29_10\consulta_cnpj"

# Executar:
php test_tesseract.php
```

Isso vai:
- ✅ Verificar se Tesseract está instalado
- ✅ Criar imagem de teste
- ✅ Extrair texto
- ✅ Validar CPF/RG/Nome

---

## ❓ Qual Escolher?

| Opção | Quando Usar | Complexidade |
|-------|-------------|--------------|
| **PHP Built-in** | Teste rápido sem instalação | ⭐ Fácil |
| **XAMPP** | Desenvolvimento local completo | ⭐⭐ Médio |
| **Laragon** | Desenvolvimento profissional | ⭐⭐ Médio |
| **Sem Servidor** | Testar apenas OCR | ⭐ Muito Fácil |
| **Servidor Remoto** | Produção | ⭐⭐⭐ Avançado |

---

## 🚀 Recomendação

**Para você agora:**

1. **Instalar XAMPP** (se ainda não tem)
2. Copiar pasta do projeto para `C:\xampp\htdocs\verify\`
3. Iniciar Apache no XAMPP
4. Acessar: `http://localhost/verify/test_document_upload.php`

**OU se preferir rapidez:**

1. Abrir PowerShell na pasta do projeto
2. Executar: `php -S localhost:8000`
3. Acessar: `http://localhost:8000/test_document_upload.php`

---

## 📝 Observação Importante

⚠️ **Para testes locais no Windows:**
- Tesseract já instalado: ✅
- `.env` já configurado para Windows: ✅
- Composer precisa ser executado: `composer install`

⚠️ **Quando subir para servidor Linux:**
- Editar `.env`: Mudar path do Tesseract
- Instalar Tesseract no servidor
- Executar `composer install` no servidor

---

Quer que eu te ajude a configurar alguma dessas opções?
