# 🔌 Integração WordPress/Elementor - Guia Rápido

## Como capturar leads do seu site WordPress

### ✅ Método Recomendado: URL com Token Integrado

A forma mais fácil é usar a **URL completa com token** que já está pronta em **Configurações > Sistema de Leads**.

---

## 📋 Passo a Passo

### 1. **Copie sua URL pronta**

Acesse: **Configurações > Sistema de Captura e Conversão de Leads**

Você verá uma caixa verde com:
```
https://verify2b.com/api_lead_webhook.php?token=SEU_TOKEN_AQUI
```

Clique em **"Copiar"** ✅

---

### 2. **Configure no WordPress**

#### **Opção A: Contact Form 7**

```html
[contact-form-7 id="123" title="Lead Form"]

<label> Nome *
    [text* nome] 
</label>

<label> E-mail *
    [email* email] 
</label>

<label> WhatsApp *
    [tel* whatsapp] 
</label>

<label> Empresa
    [text empresa] 
</label>

[submit "Enviar"]
```

**Configuração Adicional:**
1. Vá em **Contact > Integration**
2. Adicione webhook com a URL copiada
3. Método: POST
4. Body format: JSON

---

#### **Opção B: Elementor Pro Form**

1. Arraste o widget **"Form"**
2. Adicione os campos:
   - Nome (Field Type: Text, Required: Yes)
   - Email (Field Type: Email, Required: Yes)
   - WhatsApp (Field Type: Tel, Required: Yes)
   - Empresa (Field Type: Text, Required: No)

3. Vá em **Actions After Submit**
4. Adicione: **Webhook**
5. Cole a URL completa com token
6. Método: POST
7. Mapeie os campos:
   ```
   nome = Nome
   email = Email
   whatsapp = WhatsApp
   empresa = Empresa
   ```

---

#### **Opção C: WPForms**

1. Crie um novo formulário
2. Adicione campos: Nome, Email, WhatsApp, Empresa
3. Vá em **Settings > Webhooks**
4. Adicione novo webhook:
   - **URL**: Cole a URL completa com token
   - **Request Method**: POST
   - **Request Format**: JSON
   - **Data**: Map fields
     ```json
     {
       "nome": "{field_id='1'}",
       "email": "{field_id='2'}",
       "whatsapp": "{field_id='3'}",
       "empresa": "{field_id='4'}"
     }
     ```

---

#### **Opção D: Gravity Forms**

1. Crie seu formulário
2. Vá em **Form Settings > Webhooks**
3. **URL**: Cole a URL completa com token
4. **Method**: POST
5. **Request Body**: JSON
6. **Request Headers**: 
   ```
   Content-Type: application/json
   ```
7. **Map Fields**:
   ```json
   {
     "nome": "{Nome:1}",
     "email": "{Email:2}",
     "whatsapp": "{WhatsApp:3}",
     "empresa": "{Empresa:4}"
   }
   ```

---

## 🔧 Método Avançado: JavaScript Customizado

Se preferir controle total, use JavaScript:

```html
<form id="leadForm">
    <input type="text" name="nome" placeholder="Nome" required>
    <input type="email" name="email" placeholder="E-mail" required>
    <input type="tel" name="whatsapp" placeholder="WhatsApp" required>
    <input type="text" name="empresa" placeholder="Empresa">
    <button type="submit">Enviar</button>
</form>

<script>
document.getElementById('leadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Sua URL com token (copie de Configurações)
    const apiUrl = 'https://verify2b.com/api_lead_webhook.php?token=SEU_TOKEN_AQUI';
    
    const formData = new FormData(this);
    const data = {
        nome: formData.get('nome'),
        email: formData.get('email'),
        whatsapp: formData.get('whatsapp'),
        empresa: formData.get('empresa'),
        origem: window.location.href,
        referer: document.referrer,
        utm_source: new URLSearchParams(window.location.search).get('utm_source'),
        utm_medium: new URLSearchParams(window.location.search).get('utm_medium'),
        utm_campaign: new URLSearchParams(window.location.search).get('utm_campaign')
    };
    
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Obrigado! Entraremos em contato em breve.');
            this.reset();
        } else {
            alert('Erro: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao enviar. Tente novamente.');
    });
});
</script>
```

---

## 📊 Testando a Integração

### Teste Manual via Browser:

Abra o Console do navegador (F12) e execute:

```javascript
fetch('https://verify2b.com/api_lead_webhook.php?token=SEU_TOKEN_AQUI', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        nome: 'Teste WordPress',
        email: 'teste@wordpress.com',
        whatsapp: '11999999999',
        empresa: 'Meu Site WP',
        origem: window.location.href
    })
})
.then(r => r.json())
.then(data => console.log('Resposta:', data));
```

**Resposta esperada:**
```json
{
    "success": true,
    "message": "Lead registrado com sucesso!",
    "lead_id": 123
}
```

---

## 🎯 Boas Práticas

### ✅ Faça:

1. **Sempre use HTTPS** na URL da API
2. **Capture UTM params** para rastreamento
3. **Valide campos no frontend** antes de enviar
4. **Mostre mensagem de sucesso** ao usuário
5. **Teste em ambiente de staging** primeiro

### ❌ Evite:

1. ❌ Expor o token em código público do GitHub
2. ❌ Usar HTTP (sem SSL)
3. ❌ Enviar dados sem validação
4. ❌ Deixar campos obrigatórios vazios
5. ❌ Ignorar mensagens de erro

---

## 🔒 Segurança

### Token via URL é seguro?

✅ **SIM**, se usado corretamente:

- ✅ Token tem 64 caracteres (2^256 possibilidades)
- ✅ Rate limit de 100 requisições/hora por token
- ✅ Pode ser desativado/regenerado a qualquer momento
- ✅ API só aceita POST (não aparece em logs de servidor)
- ✅ HTTPS criptografa a URL em trânsito

⚠️ **Cuidados:**
- Token ficará visível nos logs do servidor
- Não compartilhe o token publicamente
- Regenere o token se suspeitar de vazamento

---

## 🆘 Solução de Problemas

### Erro: "Token de API obrigatório"
- ✅ Verifique se copiou a URL completa com `?token=`
- ✅ Verifique se o token está no final da URL

### Erro: "Token inválido"
- ✅ Regenere o token em Configurações
- ✅ Copie novamente a URL atualizada
- ✅ Atualize em todos os formulários

### Erro: "Rate limit excedido"
- ✅ Seu site está enviando mais de 100 leads/hora
- ✅ Contate o suporte para aumentar o limite
- ✅ Verifique se não há loop infinito no código

### Lead não aparece no painel
- ✅ Verifique se a requisição foi bem-sucedida (status 201)
- ✅ Confirme que os campos obrigatórios foram enviados
- ✅ Verifique se não é e-mail duplicado (últimos 30 dias)

---

## 📞 Suporte

- **Documentação completa**: Ver arquivo `API_TOKEN_GUIDE.md`
- **Painel de Leads**: Menu > Leads
- **Configurações**: Menu > Configurações
- **Email**: suporte@verify2b.com

---

**Pronto para integrar? Copie sua URL e comece agora!** 🚀
