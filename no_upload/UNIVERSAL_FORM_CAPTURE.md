# 🎯 VERIFY - Captura Universal de Formulários

## O que é?

Um **único código JavaScript** que você coloca no seu site e ele **captura automaticamente TODOS os formulários**, enviando os leads direto pro sistema Verify!

## ✨ Características

✅ **Universal**: Funciona com qualquer formulário HTML  
✅ **Automático**: Detecta campos automaticamente (nome, email, telefone, empresa)  
✅ **Compatível**: WordPress, HTML puro, Elementor, Contact Form 7, WPForms, Gravity Forms, etc  
✅ **Dinâmico**: Captura até formulários carregados via AJAX  
✅ **Não-invasivo**: Não interfere no funcionamento normal dos formulários  
✅ **Inteligente**: Ignora formulários de login, busca, etc  
✅ **Rastreável**: Integra com Google Analytics e GTM automaticamente  

---

## 📦 Instalação

### Método 1: Instalação Automática (Recomendado) ⚡

**A forma mais fácil!** O token já vem configurado automaticamente na URL:

#### Passo 1: Copie o código pronto

No painel Verify, vá em **Configurações > Sistema de Leads > Captura Universal de Formulários**

Você verá um código pronto tipo:
```html
<script src="https://verify2b.com/verify-universal-form-capture.js?token=abc123..."></script>
```

#### Passo 2: Cole no seu site

**WordPress:**
- Adicione no `header.php` do tema (antes do `</head>`)
- OU use plugin "Insert Headers and Footers"

**HTML/PHP:**
- Cole antes do `</body>` em todas as páginas

**Pronto!** ✅ Não precisa editar nada, o token já está configurado na URL!

---

### Método 2: Instalação Manual (Avançado)

Se preferir hospedar o arquivo localmente:

#### Passo 1: Baixe o Script

Baixe o arquivo `verify-universal-form-capture.js` do servidor.

#### Passo 2: Configure o Token

1. Acesse **Configurações** no painel Verify
2. Role até **Sistema de Leads**
3. Copie o **Token da API**

Abra o arquivo `verify-universal-form-capture.js` e edite esta linha:

```javascript
apiToken: 'SEU_TOKEN_AQUI',  // ← Cole seu token aqui
```

#### Passo 3: Adicione no Site

**WordPress:** Adicione no `header.php` do seu tema (ou use um plugin como "Insert Headers and Footers"):

```html
<!-- Antes do </head> -->
<script src="/wp-content/themes/seu-tema/verify-universal-form-capture.js"></script>
```

**HTML Puro:**

```html
<!-- Antes do </body> -->
<script src="/js/verify-universal-form-capture.js"></script>
```

---

## 🔍 Qual Método Escolher?

| Método | Vantagens | Desvantagens |
|--------|-----------|--------------|
| **Automático (URL)** | ✅ Mais fácil<br>✅ Não precisa editar<br>✅ Token sempre atualizado | ⚠️ Depende do servidor Verify |
| **Manual (Local)** | ✅ Controle total<br>✅ Pode customizar | ⚠️ Precisa atualizar token manualmente |

**Recomendação:** Use o **Método Automático** se você quer praticidade e não precisa customizar o script.

---

1. Vá em **Tags** > **Nova**
2. Tipo: **HTML Personalizado**
3. Cole:
```html
<script src="https://verify2b.com/verify-universal-form-capture.js"></script>
```
4. Acionador: **All Pages**

---

## 🎨 Exemplos de Formulários Capturados

O script captura automaticamente:

### ✅ Contact Form 7 (WordPress)
```html
[contact-form-7 id="123"]
```

### ✅ Elementor Pro Forms
```html
<!-- Qualquer formulário do Elementor -->
```

### ✅ HTML Puro
```html
<form action="/contato.php" method="POST">
    <input type="text" name="nome" placeholder="Seu nome">
    <input type="email" name="email" placeholder="Seu email">
    <input type="tel" name="whatsapp" placeholder="WhatsApp">
    <button type="submit">Enviar</button>
</form>
```

### ✅ WPForms / Gravity Forms
```html
<!-- Formulários são detectados automaticamente -->
```

### ✅ Formulários AJAX/JavaScript
```javascript
// Mesmo formulários carregados dinamicamente são capturados!
```

---

## 🔧 Configuração Avançada

### Ignorar Formulários Específicos

Para ignorar certos formulários (login, busca, etc):

```javascript
ignoreSelectors: [
    'form[action*="login"]',      // Ignora login
    'form[action*="logout"]',     // Ignora logout
    'form[action*="search"]',     // Ignora busca
    'form.woocommerce-cart-form', // Ignora carrinho
    'form#meu-form-especial'      // Ignora por ID
]
```

### Personalizar Detecção de Campos

Se seus formulários usam nomes diferentes:

```javascript
fieldMapping: {
    nome: ['name', 'nome', 'full_name', 'seu_nome'],
    email: ['email', 'e-mail', 'mail'],
    whatsapp: ['whatsapp', 'phone', 'telefone', 'zap'],
    empresa: ['company', 'empresa', 'nome_empresa']
}
```

### Desativar Logs (Produção)

```javascript
debug: false  // Desativa logs do console
```

---

## 📊 Integrações Automáticas

O script detecta automaticamente se o site onde foi instalado já possui Google Analytics ou Google Tag Manager e envia os eventos para eles!

### Google Analytics (GA4)

Se você tem o GA4 instalado, o script envia automaticamente:

```javascript
gtag('event', 'generate_lead', {
    'event_category': 'Lead',
    'event_label': 'Universal Form Capture',
    'value': 1
});
```

**Como ver no GA4:**
1. Acesse seu Google Analytics
2. Vá em **Relatórios** > **Eventos**
3. Procure por `generate_lead`

### Google Tag Manager

Se você tem GTM, o script envia:

```javascript
dataLayer.push({
    'event': 'lead_captured',
    'lead_id': 123,
    'form_url': 'https://seusite.com/contato'
});
```

**Como configurar no GTM:**
1. Crie uma **Acionador** tipo "Evento Personalizado"
2. Nome do evento: `lead_captured`
3. Crie uma **Tag** (Google Analytics, Facebook Pixel, etc)
4. Use o acionador criado acima

---

## ❓ FAQ - Google Tag Manager

### Preciso configurar o GTM ID no painel Verify?

**Depende de onde você está usando:**

| Local | Precisa configurar? | Por quê? |
|-------|---------------------|----------|
| **Formulários Whitelabel** (lead_form.php, kyc_form.php) | ✅ **SIM** | Esses forms rodam no servidor Verify, então você precisa informar seu GTM ID nas Configurações |
| **Script Universal** (no site do cliente) | ❌ **NÃO** | O script detecta automaticamente o GTM que já está no site |

### Exemplo Prático:

**Cenário 1: Formulário Whitelabel**
```
Cliente acessa: https://verify2b.com/lead_form.php?slug=minha-empresa
↓
Formulário carrega com SEU GTM (configurado no painel)
↓
Eventos vão para SUA conta do Google Analytics
```

**Cenário 2: Script Universal no WordPress**
```
Cliente acessa: https://sitedomeucliente.com.br/contato
↓
Script universal detecta o GTM do cliente
↓
Eventos vão para a conta do Analytics DO CLIENTE
```

### Então qual é a vantagem?

✅ **Formulário Whitelabel:** Você controla o tracking (usa seu GTM)  
✅ **Script Universal:** Cliente mantém seu tracking (usa GTM dele)

Os dois enviam leads para o Verify, mas o tracking de analytics fica separado!

---

### Evento JavaScript Customizado

Você pode escutar quando um lead é capturado:

```javascript
window.addEventListener('verifyLeadCaptured', function(e) {
    console.log('Lead capturado!', e.detail.lead_id);
    
    // Redirecionar para página de obrigado
    // window.location.href = '/obrigado';
    
    // Mostrar mensagem
    // alert('Obrigado! Em breve entraremos em contato.');
});
```

---

## 🧪 Como Testar

### Método 1: Página de Teste Interativa (Recomendado) 🎯

**A forma mais fácil de testar!**

1. No painel Verify, vá em **Configurações > Sistema de Leads > Captura Universal**
2. Clique no botão **"Testar Captura em Tempo Real"**
3. Você verá uma página com:
   - ✅ Status do script (carregado/erro)
   - ✅ Validação do token
   - ✅ Console de logs em tempo real
   - ✅ Formulários de teste prontos
   - ✅ Botão de auto-preencher
   - ✅ Tabela com últimos leads capturados

4. Clique em **"Auto-Preencher e Testar"**
5. Veja os logs aparecerem em tempo real
6. Confira se o lead foi criado na tabela

**Vantagens:**
- ✅ Não precisa instalar no site ainda
- ✅ Logs visuais em tempo real
- ✅ Testa o token automaticamente
- ✅ Vê os leads sendo criados instantaneamente

---

### Método 2: Verificar no Site Real

Depois de instalar o script no seu site:

### Teste 1: Verificar se o Script Carregou

1. Abra o site com **F12** (DevTools)
2. Vá na aba **Console**
3. Você deve ver:
   ```
   [VERIFY Lead Capture] 🚀 Iniciando captura universal de formulários...
   [VERIFY Lead Capture] ✅ Monitoramento ativo! Total de formulários: X
   ```

4. Se aparecer erro de token:
   ```
   [VERIFY] ❌ Configure seu API Token antes de usar!
   ```
   **Solução:** Verifique se o token está correto na URL do script ou no arquivo JS

### Teste 2: Capturar um Lead

1. Preencha um formulário no site
2. No **Console**, você verá:
   ```
   [VERIFY Lead Capture] Campo detectado: nome = João Silva
   [VERIFY Lead Capture] Campo detectado: email = joao@email.com
   [VERIFY Lead Capture] Campo detectado: whatsapp = 11999999999
   [VERIFY Lead Capture] ✅ Lead capturado e enviado!
   ```

3. Acesse **Leads** no painel Verify
4. O lead deve aparecer com status "Novo"

### Teste 3: Via Console do Navegador

```javascript
// No console do DevTools:
VerifyLeadCapture.sendLead({
    nome: 'Teste Console',
    email: 'teste@console.com',
    whatsapp: '11999999999',
    empresa: 'Empresa Teste'
});
```

---

## ❓ FAQ

### O script funciona com formulários em popups?
✅ **Sim!** Funciona com modals, lightboxes, popups, etc.

### Funciona com formulários carregados via AJAX?
✅ **Sim!** O script monitora novos formulários adicionados dinamicamente.

### Posso usar em múltiplos sites?
✅ **Sim!** Cada site pode ter seu próprio token de empresa.

### O formulário continua funcionando normalmente?
✅ **Sim!** O script apenas captura os dados, não interfere no submit.

### E se o formulário não tiver todos os campos?
⚠️ O lead só é criado se tiver: **nome**, **email** e **whatsapp**.

### Posso personalizar quais campos são obrigatórios?

Sim! Edite a função `isValidLeadData`:

```javascript
function isValidLeadData(data) {
    // Apenas email obrigatório
    return data.email;
    
    // Ou nome + email
    return data.nome && data.email;
}
```

### Como ver quais formulários foram detectados?

No console do navegador:

```javascript
document.querySelectorAll('form').forEach((form, i) => {
    console.log(`Form ${i}:`, form.action, form);
});
```

---

## 🐛 Troubleshooting

### "Configure seu API Token antes de usar!"

❌ Você esqueceu de colocar o token.  
✅ Edite `apiToken: 'SEU_TOKEN_AQUI'` no arquivo .js

### "Dados insuficientes para criar lead"

❌ O formulário não tem os campos mínimos (nome, email, whatsapp).  
✅ Adicione esses campos ou personalize `fieldMapping`.

### "Lead não aparece no painel"

1. ✅ Verifique se o token está correto
2. ✅ Abra o console (F12) e veja se há erros
3. ✅ Teste com `debug: true` na configuração
4. ✅ Verifique se o formulário tem os campos mínimos

### Formulário está sendo ignorado

✅ Verifique se ele não está em `ignoreSelectors`  
✅ Use `debug: true` para ver logs

---

## 🎁 Recursos Extras

### Captura Manual via JavaScript

```javascript
// Capturar lead manualmente
VerifyLeadCapture.sendLead({
    nome: 'João Silva',
    email: 'joao@email.com',
    whatsapp: '11999999999',
    empresa: 'Empresa XYZ',
    mensagem: 'Tenho interesse nos serviços',
    origem: 'Landing Page Produto A'
});
```

### Redirecionar após captura

```javascript
window.addEventListener('verifyLeadCaptured', function(e) {
    // Aguarda 500ms para garantir que enviou
    setTimeout(() => {
        window.location.href = '/obrigado?lead_id=' + e.detail.lead_id;
    }, 500);
});
```

### Integrar com Facebook Pixel

```javascript
window.addEventListener('verifyLeadCaptured', function(e) {
    if (typeof fbq !== 'undefined') {
        fbq('track', 'Lead', {
            content_name: 'Form Submission',
            value: 1.00,
            currency: 'BRL'
        });
    }
});
```

---

## 📈 Vantagens

| Método | Vantagens |
|--------|-----------|
| **Script Universal** | ✅ 1 código captura TUDO<br>✅ Fácil de instalar<br>✅ Funciona com qualquer formulário<br>✅ Detecta campos automaticamente |
| Contact Form 7 Hook | ⚠️ Só WordPress<br>⚠️ Só Contact Form 7<br>⚠️ Precisa configurar cada form |
| Zapier/Make | ⚠️ Custo mensal<br>⚠️ Configuração complexa<br>⚠️ Delay no envio |

---

## 🚀 Próximos Passos

1. ✅ Configure o token
2. ✅ Adicione o script no site
3. ✅ Teste com um formulário
4. ✅ Configure o GTM/GA4 (opcional)
5. ✅ Monitore os leads no painel

---

## 💡 Dicas de Performance

- Host o arquivo .js no seu próprio servidor para carregamento mais rápido
- Use CDN (Cloudflare) para distribuição global
- Minimize o arquivo antes de publicar (use terser ou uglify-js)
- Considere carregar o script de forma assíncrona:

```html
<script src="verify-universal-form-capture.js" async></script>
```

---

## 📞 Suporte

Problemas? Entre em contato com a equipe Verify!

**Email**: suporte@verify2b.com  
**WhatsApp**: (11) 99999-9999

---

*Desenvolvido com ❤️ para simplificar sua captura de leads!*
