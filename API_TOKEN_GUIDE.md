# 🔐 Guia de Autenticação por Token de API

## Por que usar Token de API por Empresa?

Cada empresa parceira possui seu próprio **token único de 64 caracteres** para autenticar requisições ao webhook de leads.

### Benefícios:

✅ **Segurança**: Rastreamento preciso de cada empresa parceira  
✅ **Controle**: Desativar/ativar tokens individualmente  
✅ **Rate Limiting**: Limitar requisições por empresa (padrão: 100/hora)  
✅ **Analytics**: Relatórios de conversão por parceiro  
✅ **Auditoria**: Log completo de uso por empresa  

---

## Como Funciona

### 1. Cada empresa recebe um token único

O token é gerado automaticamente ao criar a configuração whitelabel:

```sql
-- Token de 64 caracteres (MD5 + SHA1)
CONCAT(
    SUBSTRING(MD5(...), 1, 32),
    SUBSTRING(SHA1(...), 1, 32)
)
```

### 2. Token é enviado no header da requisição

```javascript
fetch('https://verify2b.com/api_lead_webhook.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer SEU_TOKEN_AQUI'
    },
    body: JSON.stringify({
        nome: "João Silva",
        email: "joao@example.com",
        whatsapp: "11999999999"
    })
})
```

### 3. API valida e registra

- ✅ Token existe?
- ✅ Token está ativo?
- ✅ Rate limit não excedido?
- ✅ Registra uso e cria lead

---

## Exemplo de Implementação

### HTML + JavaScript (Formulário Externo)

```html
<!DOCTYPE html>
<html>
<head>
    <title>Captura de Lead</title>
</head>
<body>
    <form id="leadForm">
        <input type="text" name="nome" placeholder="Nome" required>
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="tel" name="whatsapp" placeholder="WhatsApp" required>
        <button type="submit">Enviar</button>
    </form>

    <script>
    const API_TOKEN = 'seu_token_de_64_caracteres_aqui';
    const API_URL = 'https://verify2b.com/api_lead_webhook.php';

    document.getElementById('leadForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            nome: formData.get('nome'),
            email: formData.get('email'),
            whatsapp: formData.get('whatsapp'),
            origem: window.location.href
        };

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${API_TOKEN}`
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert('Lead registrado com sucesso!');
                e.target.reset();
            } else {
                alert('Erro: ' + result.message);
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro de conexão');
        }
    });
    </script>
</body>
</html>
```

### PHP (Sistema Externo)

```php
<?php
$api_token = 'seu_token_de_64_caracteres_aqui';
$api_url = 'https://verify2b.com/api_lead_webhook.php';

$lead_data = [
    'nome' => $_POST['nome'],
    'email' => $_POST['email'],
    'whatsapp' => $_POST['whatsapp'],
    'empresa' => $_POST['empresa'] ?? null,
    'origem' => $_SERVER['HTTP_HOST']
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($lead_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_token
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code === 201 && $result['success']) {
    echo "Lead registrado: ID " . $result['lead_id'];
} else {
    echo "Erro: " . ($result['message'] ?? 'Desconhecido');
}
?>
```

### Python (Script)

```python
import requests

API_TOKEN = 'seu_token_de_64_caracteres_aqui'
API_URL = 'https://verify2b.com/api_lead_webhook.php'

lead_data = {
    'nome': 'Maria Santos',
    'email': 'maria@example.com',
    'whatsapp': '11987654321',
    'empresa': 'Empresa XYZ',
    'origem': 'landing_page_python'
}

headers = {
    'Content-Type': 'application/json',
    'Authorization': f'Bearer {API_TOKEN}'
}

response = requests.post(API_URL, json=lead_data, headers=headers)

if response.status_code == 201:
    result = response.json()
    print(f"Lead registrado com sucesso! ID: {result['lead_id']}")
else:
    print(f"Erro: {response.json().get('message', 'Desconhecido')}")
```

---

## Gerenciamento de Tokens

### Visualizar Token

1. Acesse **Configurações > Sistema de Leads**
2. Na seção "Formulário de Captura"
3. Clique no ícone 👁️ para revelar o token
4. Clique em 📋 para copiar

### Desativar Token

```sql
UPDATE configuracoes_whitelabel
SET api_token_ativo = 0
WHERE id = 1;
```

### Regenerar Token

```sql
UPDATE configuracoes_whitelabel
SET api_token = CONCAT(
    SUBSTRING(MD5(CONCAT(id, slug, RAND())), 1, 32),
    SUBSTRING(SHA1(CONCAT(slug, nome_empresa, NOW())), 1, 32)
)
WHERE id = 1;
```

### Alterar Rate Limit

```sql
UPDATE configuracoes_whitelabel
SET api_rate_limit = 200  -- 200 requisições por hora
WHERE id = 1;
```

---

## Respostas da API

### ✅ Sucesso (HTTP 201)

```json
{
    "success": true,
    "message": "Lead registrado com sucesso!",
    "lead_id": 123,
    "data": {
        "nome": "João Silva",
        "email": "joao@example.com"
    }
}
```

### ❌ Token Inválido (HTTP 401)

```json
{
    "error": "Token inválido",
    "message": "Token de API não encontrado ou inativo"
}
```

### ❌ Token Desativado (HTTP 403)

```json
{
    "error": "Token desativado",
    "message": "Este token de API foi desativado. Entre em contato com o suporte."
}
```

### ❌ Rate Limit (HTTP 429)

```json
{
    "error": "Rate limit excedido",
    "message": "Limite de 100 requisições por hora atingido"
}
```

### ❌ Lead Duplicado (HTTP 200)

```json
{
    "success": true,
    "message": "Lead já registrado recentemente",
    "lead_id": 123
}
```

### ❌ Método Errado (HTTP 405)

```json
{
    "error": "Método não permitido. Use POST."
}
```

---

## Monitoramento

### Verificar Último Uso

```sql
SELECT 
    nome_empresa,
    api_ultimo_uso,
    api_rate_limit,
    api_token_ativo
FROM configuracoes_whitelabel
WHERE api_token IS NOT NULL;
```

### Log de Requisições (Última Hora)

```sql
SELECT 
    w.nome_empresa,
    COUNT(*) as total_requisicoes,
    MIN(l.created_at) as primeira,
    MAX(l.created_at) as ultima
FROM leads_webhook_log l
JOIN configuracoes_whitelabel w ON w.id = l.empresa_id
WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY w.id, w.nome_empresa;
```

### Leads por Empresa (Hoje)

```sql
SELECT 
    w.nome_empresa,
    COUNT(l.id) as total_leads,
    COUNT(CASE WHEN l.status = 'convertido' THEN 1 END) as convertidos
FROM leads l
JOIN configuracoes_whitelabel w ON w.empresa_id = l.id_empresa_master
WHERE DATE(l.created_at) = CURDATE()
GROUP BY w.id, w.nome_empresa
ORDER BY total_leads DESC;
```

---

## Segurança

### ✅ Boas Práticas

1. **Nunca exponha o token no frontend** (código público)
2. **Use HTTPS** para todas as requisições
3. **Armazene tokens em variáveis de ambiente** (.env)
4. **Rotacione tokens periodicamente** (a cada 6 meses)
5. **Monitore uso anormal** (rate limit excedido)
6. **Desative tokens comprometidos** imediatamente

### ❌ Evite

- ❌ Hardcoded em código-fonte versionado (Git)
- ❌ Compartilhar o mesmo token entre múltiplos parceiros
- ❌ Enviar token via URL (query string)
- ❌ Logar tokens em arquivos de log
- ❌ Armazenar em localStorage do navegador

---

## Suporte

Para problemas com autenticação:

1. Verifique se o token está correto (64 caracteres)
2. Confirme que o token está ativo no banco
3. Verifique se não excedeu o rate limit
4. Consulte os logs em `leads_webhook_log`

**Contato**: suporte@verify2b.com
