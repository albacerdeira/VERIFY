# 🔄 FLUXO: LEAD → CLIENTE KYC

## Visão Geral
Sistema completo de conversão de leads em clientes através do formulário KYC.

---

## 📋 FLUXO PASSO A PASSO

### 1️⃣ **Captura do Lead**
- Visitante preenche formulário em `lead_form.php`
- Dados enviados via webhook para `api_lead_webhook.php`
- Lead armazenado na tabela `leads` com status "novo"
- Evento enviado para Google Analytics

### 2️⃣ **Gestão do Lead**
**Página:** `leads.php`

**Visualização:**
- Dashboard com estatísticas
- Filtros por status, data, busca
- Listagem completa com informações de contato

**Ações Disponíveis:**
- 👁️ Ver detalhes
- 📋 Enviar formulário KYC (botão verde)
- 📞 Contato via WhatsApp
- ✉️ Enviar email

### 3️⃣ **Conversão em Cliente**
**Botão:** "Enviar Formulário KYC"

**Processo Automático:**
1. Verifica se já existe cliente com mesmo email
   - **SIM:** Reutiliza cliente existente
   - **NÃO:** Cria novo registro em `kyc_clientes`

2. Gera token único de acesso (válido por 30 dias)

3. Cria URL personalizada:
   ```
   https://seusite.com/kyc_form.php?slug=empresa&token=abc123...
   ```

4. Registra ação no histórico do lead

5. Atualiza status: "novo" → "contatado"

### 4️⃣ **Envio ao Cliente**
**Opções:**
- ✅ Copiar link gerado
- 📧 Enviar via email (futuro: automático com PHPMailer)
- 💬 Compartilhar via WhatsApp

### 5️⃣ **Preenchimento do Formulário**
**Cliente acessa o link:**
- Formulário KYC carrega automaticamente
- Contexto whitelabel aplicado (cores, logo da empresa parceira)
- Cliente preenche dados da empresa + documentos
- Sócios/representantes adicionados

### 6️⃣ **Submissão e Análise**
- Formulário enviado via `kyc_submit.php`
- Status inicial: "Novo Registro"
- Aparece em `kyc_list.php` para análise
- Equipe pode avaliar em `kyc_evaluate.php`

---

## 🗄️ ESTRUTURA DE BANCO DE DADOS

### Tabelas Envolvidas

**`leads`**
- Informações básicas do interessado
- Rastreamento (UTM, IP, origem)
- Status do funil de vendas
- Vínculo com empresa parceira

**`leads_historico`**
- Log de todas interações
- Quem fez o quê e quando
- Observações e mudanças de status

**`kyc_clientes`**
- Dados do cliente para acesso
- Token de acesso único
- **NOVOS CAMPOS:**
  - `token_acesso`: Hash único para link direto
  - `token_expiracao`: Validade do token (30 dias)
  - `origem`: 'lead_conversion' ou 'registro_direto'
  - `telefone`: WhatsApp do lead

**`kyc_empresas`**
- Dados completos da empresa
- CNPJ, razão social, endereço
- Vinculado ao `cliente_id`

**`kyc_avaliacoes`**
- Resultado da análise de compliance
- Flags CEIS, CNEP, PEP
- Status: Aprovado, Reprovado, etc.

---

## 🔐 SEGURANÇA E PERMISSÕES

### Quem Pode Enviar KYC?
- ✅ **Superadmin:** Todos os leads
- ✅ **Administrador:** Apenas leads da sua empresa
- ❌ **Analista:** Sem acesso à seção de leads

### Token de Acesso
- Gerado com `bin2hex(random_bytes(32))` = 64 caracteres
- Único por cliente
- Expira em 30 dias
- Permite acesso sem login/senha
- Uma vez usado, cliente pode criar senha própria

---

## 📁 ARQUIVOS DO SISTEMA

### Backend
| Arquivo | Função |
|---------|--------|
| `leads.php` | Listagem e gerenciamento de leads |
| `lead_detail.php` | Visualização detalhada + histórico |
| `lead_form.php` | Formulário público de captura |
| `ajax_send_kyc_to_lead.php` | Gera link KYC para lead |
| `ajax_update_lead_status.php` | Atualiza status do lead |
| `api_lead_webhook.php` | Recebe leads via POST JSON |

### SQL
| Arquivo | Função |
|---------|--------|
| `create_leads_table.sql` | Cria tabelas de leads |
| `alter_kyc_clientes_lead_integration.sql` | Adiciona campos para integração |

### Frontend
- Botão "Enviar Formulário KYC" em `leads.php`
- Botão principal em `lead_detail.php`
- Modal de mudança de status
- JavaScript para AJAX

---

## 🎯 STATUS DO LEAD

### Fluxo Normal
```
novo → contatado → qualificado → convertido
```

### Status Perdido
```
novo → contatado → qualificado → perdido
```

### Mudanças Automáticas
- **novo → contatado:** Quando envia formulário KYC
- **Manual:** Admin pode alterar a qualquer momento

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Banco de Dados
- [ ] Executar `create_leads_table.sql`
- [ ] Executar `alter_kyc_clientes_lead_integration.sql`
- [ ] Verificar criação de índices

### Configuração
- [ ] Testar captura de lead em `lead_form.php`
- [ ] Verificar webhook em `api_lead_webhook.php`
- [ ] Configurar Google Analytics/GTM

### Testes de Fluxo
- [ ] Criar lead de teste
- [ ] Clicar em "Enviar Formulário KYC"
- [ ] Verificar criação de cliente
- [ ] Testar link gerado
- [ ] Confirmar preenchimento de formulário
- [ ] Validar aparição em `kyc_list.php`

### Opcional (Futuro)
- [ ] Integrar PHPMailer para envio automático
- [ ] Configurar webhook CRM externo
- [ ] Criar relatórios de conversão

---

## 🚀 EXEMPLO DE USO

### Cenário Real

**1. Lead chega pelo site:**
```
Nome: João Silva
Email: joao@empresa.com
WhatsApp: (11) 98765-4321
Empresa: Silva & Cia
Origem: Página de contato
UTM: google / cpc / campanha-2025
```

**2. Aparece em `leads.php`:**
- Status: "Novo" (badge azul)
- Botão verde: "📋" (Enviar KYC)

**3. Admin clica no botão:**
- Sistema cria cliente automático
- Gera link: `kyc_form.php?token=a1b2c3...`
- Mostra popup com o link

**4. Admin envia ao lead:**
- Copia link e envia via WhatsApp/Email
- Lead recebe e acessa

**5. Lead preenche KYC:**
- Formulário carrega com branding correto
- Preenche CNPJ, dados, documentos
- Submete formulário

**6. Análise interna:**
- KYC aparece em "Novo Registro"
- Equipe analisa compliance
- Aprova ou reprova

**7. Resultado:**
- Lead convertido em cliente
- Status alterado para "Convertido"
- Histórico completo registrado

---

## 🔔 NOTIFICAÇÕES E INTEGRAÇÕES

### Atual
- ✅ Histórico completo em `leads_historico`
- ✅ Google Analytics (evento 'lead_submitted')
- ✅ Log de webhook em `leads_webhook_log`

### Futuro
- 🔄 Email automático com link KYC
- 🔄 Notificação por email quando KYC preenchido
- 🔄 Integração com CRM externo (HubSpot, Salesforce, etc.)
- 🔄 Dashboard de conversão (Lead → Cliente)

---

## 📊 MÉTRICAS DISPONÍVEIS

### Em `leads.php`
- Total de leads
- Por status: Novos, Contatados, Qualificados, Convertidos, Perdidos
- Taxa de conversão (implícita)

### Em `dashboard_analytics.php`
- Total de clientes KYC
- Processos em análise
- Alertas de compliance

---

## 🆘 TROUBLESHOOTING

### Lead não aparece
- ✅ Verificar tabela `leads` no banco
- ✅ Checar permissões (admin vs superadmin)
- ✅ Validar `id_empresa_master`

### Link KYC não funciona
- ✅ Verificar campos `token_acesso` e `token_expiracao` em `kyc_clientes`
- ✅ Executar `alter_kyc_clientes_lead_integration.sql`
- ✅ Checar se token não expirou (30 dias)

### Formulário não carrega branding
- ✅ Verificar parâmetro `?slug=` na URL
- ✅ Conferir tabela `configuracoes_whitelabel`
- ✅ Validar `id_empresa_master` do cliente

### Status não atualiza
- ✅ Verificar permissões em `ajax_update_lead_status.php`
- ✅ Checar logs do navegador (console)
- ✅ Validar foreign keys nas tabelas

---

**Sistema pronto para uso! 🎉**
