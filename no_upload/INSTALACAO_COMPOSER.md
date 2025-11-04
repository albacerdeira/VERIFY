# 🚀 Guia de Instalação - Composer e AWS SDK

## ⚠️ CRÍTICO: Composer Autoloader Necessário

Os arquivos `ajax_verify_face.php` e `ajax_verify_document.php` agora requerem o Composer autoloader para carregar o AWS SDK.

---

## 📋 Pré-requisitos

- Acesso SSH ao servidor Hostinger
- PHP 7.4 ou superior
- Composer instalado no servidor

---

## 🔧 Instalação no Servidor Hostinger

### Opção 1: Via SSH (Recomendado)

```bash
# 1. Conecte via SSH
ssh u640879529@verify2b.com

# 2. Navegue até o diretório do projeto
cd ~/domains/verify2b.com/public_html

# 3. Verifique se o Composer está instalado
composer --version

# 4. Se não estiver, instale o Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
alias composer='php ~/composer.phar'

# 5. Instale as dependências
composer install --no-dev --optimize-autoloader

# 6. Verifique se a pasta vendor foi criada
ls -la vendor/

# 7. Verifique se o autoload.php existe
ls -la vendor/autoload.php
```

### Opção 2: Via File Manager (Alternativa)

Se não tiver acesso SSH:

1. **Baixe as dependências localmente:**
   ```bash
   # No seu computador local
   cd "c:\Users\albac\Downloads\fdbank\teste servidor 29_10\consulta_cnpj"
   composer install --no-dev --optimize-autoloader
   ```

2. **Faça upload da pasta `vendor/` via FTP:**
   - Conecte ao FTP
   - Faça upload da pasta `vendor/` completa para:
     ```
     /home/u640879529/domains/verify2b.com/public_html/vendor/
     ```

   ⚠️ **AVISO:** A pasta vendor pode ser grande (20-30 MB). O upload pode demorar.

---

## ✅ Verificação da Instalação

### Teste 1: Verificar Autoloader

Crie um arquivo `test_composer.php` na raiz:

```php
<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ Composer autoloader carregado com sucesso!<br>";
    
    // Testa AWS SDK
    if (class_exists('Aws\Textract\TextractClient')) {
        echo "✅ AWS Textract disponível!<br>";
    } else {
        echo "❌ AWS Textract NÃO encontrado<br>";
    }
    
    if (class_exists('Aws\Rekognition\RekognitionClient')) {
        echo "✅ AWS Rekognition disponível!<br>";
    } else {
        echo "❌ AWS Rekognition NÃO encontrado<br>";
    }
} else {
    echo "❌ Composer autoloader NÃO encontrado!<br>";
    echo "Execute: composer install<br>";
}
?>
```

Acesse: `https://verify2b.com/test_composer.php`

**Resultado esperado:**
```
✅ Composer autoloader carregado com sucesso!
✅ AWS Textract disponível!
✅ AWS Rekognition disponível!
```

### Teste 2: Verificar Estrutura de Pastas

```bash
# Via SSH
cd ~/domains/verify2b.com/public_html
tree -L 2 vendor/aws/
```

Deve mostrar:
```
vendor/
├── autoload.php
├── composer/
└── aws/
    └── aws-sdk-php/
```

---

## 🐛 Solução de Problemas

### Erro: "Composer autoloader não encontrado"

**Causa:** Pasta `vendor/` não existe ou autoload.php ausente

**Solução:**
```bash
cd ~/domains/verify2b.com/public_html
composer install --no-dev --optimize-autoloader
```

### Erro: "Class 'Aws\Textract\TextractClient' not found"

**Causa:** AWS SDK não instalado corretamente

**Solução:**
```bash
composer require aws/aws-sdk-php
composer dump-autoload
```

### Erro: "Memory limit exceeded" durante composer install

**Causa:** Memória PHP insuficiente

**Solução:**
```bash
php -d memory_limit=512M ~/composer.phar install --no-dev
```

### Permissões Incorretas

```bash
# Ajusta permissões da pasta vendor
chmod -R 755 vendor/
chown -R u640879529:u640879529 vendor/
```

---

## 📦 Estrutura do composer.json

```json
{
    "require": {
        "php": ">=7.4",
        "guzzlehttp/guzzle": "^7.10",
        "aws/aws-sdk-php": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "Verify\\": "src/"
        }
    }
}
```

---

## 🔐 Dependências Instaladas

Após `composer install`, serão instalados:

- **aws/aws-sdk-php** (3.x): SDK da AWS para PHP
- **guzzlehttp/guzzle** (7.x): Cliente HTTP (dependência do AWS SDK)
- **guzzlehttp/psr7**: PSR-7 HTTP message library
- **guzzlehttp/promises**: Promises/A+ implementation
- **mtdowling/jmespath.php**: JSONPath implementation

**Tamanho total:** ~25-30 MB

---

## 🚀 Próximos Passos

Após instalar o Composer:

1. ✅ Faça upload dos arquivos atualizados:
   - `ajax_verify_face.php`
   - `ajax_verify_document.php`
   - `cliente_edit.php`

2. ✅ Teste a verificação facial novamente

3. ✅ Teste a verificação de documento

4. ✅ Delete o arquivo `test_composer.php` após confirmar

---

## 📝 Comandos Úteis

```bash
# Atualizar dependências
composer update --no-dev

# Recriar autoloader
composer dump-autoload --optimize

# Verificar versões instaladas
composer show

# Verificar apenas AWS SDK
composer show aws/aws-sdk-php

# Remover cache do Composer
composer clear-cache
```

---

## ⚠️ IMPORTANTE

- **NÃO** delete a pasta `vendor/` após instalação
- **NÃO** commite a pasta `vendor/` no Git (já está no .gitignore)
- **SEMPRE** use `--no-dev` em produção
- **MANTENHA** o composer.json e composer.lock versionados

---

## 📞 Suporte

Se o erro persistir após instalação:

1. Verifique o arquivo `error.log`
2. Verifique permissões da pasta `vendor/`
3. Confirme que o PHP pode ler a pasta `vendor/`
4. Teste com o arquivo `test_composer.php`

---

## ✅ Checklist de Instalação

- [ ] SSH conectado ao servidor
- [ ] Composer instalado/verificado
- [ ] `composer install` executado com sucesso
- [ ] Pasta `vendor/` criada
- [ ] Arquivo `vendor/autoload.php` existe
- [ ] `test_composer.php` mostra todas as classes AWS
- [ ] Arquivos AJAX atualizados no servidor
- [ ] Verificação facial testada
- [ ] Verificação de documento testada
- [ ] Arquivo de teste deletado
