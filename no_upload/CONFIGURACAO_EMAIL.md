# 📧 Guia de Configuração: Envio de Email KYC

## 🚀 Configuração Atual (Simples!)

✨ **O sistema já está configurado e pronto para uso!** ✨

As configurações de email estão centralizadas no arquivo `config.php`:

```php
// Em config.php (já configurado):
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'noreply@foconteudo.com.br');
define('SMTP_PASS', '005@Fabio');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'noreply@foconteudo.com.br');
define('SMTP_FROM_NAME', 'Plataforma KYC');
```

### � Para usar, simplesmente:
1. Acesse um lead na sua interface
2. Clique em **"Enviar Formulário KYC"**
3. Escolha **"Enviar por Email"**
4. Pronto! O email será enviado automaticamente 📧

---

## 🎯 Como Funciona

O sistema usa o email já configurado no `config.php`:
- **Servidor SMTP:** smtp.hostinger.com
- **Remetente:** noreply@foconteudo.com.br
- **Nome do Remetente:** Adapta-se ao whitelabel (se configurado) ou usa "Plataforma KYC"

### 🔮 Configuração Futura (Whitelabel)

Quando a plataforma crescer, você poderá configurar emails diferentes por whitelabel:
- Cada empresa parceira terá seu próprio email remetente
- Templates personalizados com logo e cores da marca
- Domínios próprios para cada whitelabel

**Por enquanto:** Todos os emails saem do mesmo remetente (simples e funcional!) ✨

---

## 🔧 Métodos de Envio Disponíveis

### 1️⃣ Enviar por Email 📧
- Envia email automático com link personalizado
- Template profissional com branding da empresa
- Requer configuração SMTP
- **Status:** Configuração necessária

### 2️⃣ Enviar por WhatsApp 📱
- Abre WhatsApp Web com mensagem pré-preenchida
- Não requer configuração de servidor
- Usuário revisa e envia manualmente
- **Status:** ✅ Funciona imediatamente

### 3️⃣ Apenas gerar link 🔗
- Copia link para área de transferência
- Para envio manual (Telegram, SMS, etc)
- Não requer nenhuma configuração
- **Status:** ✅ Funciona imediatamente

---

## 🐛 Solução de Problemas

### Email não está sendo enviado

**Verificações básicas:**
- ✅ Confirme que o servidor Hostinger está funcionando
- ✅ Verifique se a senha está correta em `config.php`
- ✅ Teste enviar um email manualmente pelo webmail da Hostinger

**Email vai para spam:**
- Configure SPF/DKIM no painel da Hostinger
- Peça ao destinatário para marcar como "Não é spam"
- Use um domínio verificado como remetente

### Ver erros detalhados
Verifique o arquivo `error.log` na raiz do projeto:
```powershell
Get-Content error.log -Tail 20
```

---

## 📊 Rastreamento de Envios

Todos os envios são registrados na tabela `leads_historico`:

```sql
SELECT 
    lh.id,
    lh.acao,
    lh.detalhes,
    lh.created_at,
    l.nome,
    l.email
FROM leads_historico lh
JOIN leads l ON lh.lead_id = l.id
WHERE lh.acao IN ('kyc_enviado', 'email_enviado', 'whatsapp_preparado')
ORDER BY lh.created_at DESC;
```

**Tipos de ação:**
- `kyc_enviado`: Link gerado (para qualquer método)
- `email_enviado`: Email enviado via SMTP
- `whatsapp_preparado`: Link preparado para WhatsApp

---

## 🔒 Segurança

### ✅ Já implementado:
- Arquivo `email_config.php` está no `.gitignore` (não vai para Git)
- Tokens KYC expiram em 30 dias
- Validação de empresa_id (usuário só vê seus leads)

### ⚠️ Recomendações:
1. **Nunca** commite `email_config.php` no repositório
2. Use senhas de aplicativo (Gmail) ao invés de senhas principais
3. Limite permissões do email SMTP (não use conta de admin)
4. Configure SSL/TLS no servidor web (HTTPS)
5. Implemente rate limiting para evitar spam

---

## 📝 Checklist de Implementação

- [x] Configuração de email no `config.php` ✅
- [ ] Testar envio de email para um lead real
- [ ] Verificar recebimento (inbox e spam)
- [ ] Testar método WhatsApp
- [ ] Testar método "apenas link"
- [ ] Verificar registro no `leads_historico`

### 🚀 Melhorias Futuras (quando crescer!)
- [ ] Email personalizado por whitelabel
- [ ] Templates customizados com logo da empresa
- [ ] Configuração SPF/DKIM por domínio
- [ ] Tracking de abertura de email
- [ ] Opção de envio por SMS

---

## 📞 Arquivos Relacionados

- `config.php` - **Configuração central de email** 📧
- `ajax_send_kyc_to_lead.php` - Backend de envio
- `lead_detail.php` - Interface do lead individual
- `leads.php` - Interface da lista de leads

---

## 💡 Filosofia: Start Small, Grow Big

Por enquanto, mantemos tudo **simples e funcional**:
- ✅ Um único email configurado
- ✅ Sistema centralizado
- ✅ Fácil de manter

Conforme a plataforma crescer, o sistema já está **preparado para escalar**:
- 🔮 Configuração por whitelabel
- 🔮 Templates personalizados
- 🔮 Domínios próprios
- 🔮 Análises avançadas

**"Ainda estou pequena, mas com uma base sólida!"** 🌱✨
