# 🔧 CORREÇÃO: Erro "Sem permissão para acessar este cliente"

## ❌ Problema
Admin da empresa #18 não consegue editar cliente #55 (que também é da empresa #18).

## ✅ Solução
Arquivo: `cliente_edit.php`  
Linha: **33**

### TROCAR ESTA LINHA:
```php
if (($is_admin || $is_analista) && $cliente['id_empresa_master'] != $user_empresa_id) {
```

### POR ESTA:
```php
if (($is_admin || $is_analista) && (int)$cliente['id_empresa_master'] !== (int)$user_empresa_id) {
```

## 📝 Explicação
O problema é **comparação de tipo**:
- `$cliente['id_empresa_master']` pode ser string "18"
- `$user_empresa_id` pode ser int 18
- Operador `!=` faz comparação frouxa (permite falha)
- Operador `!==` faz comparação estrita (mais seguro)
- Cast `(int)` garante que ambos sejam números

## 🎯 Como aplicar

### Método 1: Upload via FTP/FileZilla
1. Abra FileZilla
2. Conecte no servidor verify2b.com
3. Navegue até `/public_html/`
4. Faça backup do `cliente_edit.php` atual
5. Substitua pela versão local corrigida

### Método 2: Editar via cPanel File Manager
1. Acesse cPanel → File Manager
2. Navegue até `cliente_edit.php`
3. Clique com botão direito → Edit
4. Encontre linha 33
5. Faça a alteração acima
6. Salve (Ctrl+S)

### Método 3: Editar via SSH/Terminal
```bash
ssh usuario@verify2b.com
cd /caminho/para/pasta
nano cliente_edit.php
# Edite a linha 33
# Ctrl+X, Y, Enter para salvar
```

## ✅ Teste após correção
1. Acesse: `https://verify2b.com/cliente_edit.php?id=55`
2. Deve carregar normalmente (sem erro de permissão)
3. Todos os campos devem aparecer preenchidos

## 📋 Resumo das mudanças no arquivo
- ✅ Adicionados campos: RG, Data Nascimento, Telefone, Filiação, Endereço completo
- ✅ Organizado em seções: Identificação, Contato, Filiação, Endereço, Segurança
- ✅ Corrigida verificação de permissão (linha 33)
- ✅ Compatibilidade com PHP 7.2+ (removido `match()`)

## 🚨 IMPORTANTE
**NÃO esqueça de fazer backup antes de editar!**
