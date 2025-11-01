# Documentação Completa do Sistema de KYC Whitelabel

## 1. Visão Geral do Projeto

Este é um sistema de **Consulta de CNPJ e Onboarding de Clientes (KYC)**, projetado para operar em um modelo **Whitelabel**. O objetivo é permitir que empresas parceiras utilizem a plataforma com sua própria identidade visual para cadastrar e analisar seus próprios clientes.

**Funcionalidades Principais:**
-   **Gestão de Usuários:** Múltiplos níveis de permissão (Superadmin, Administrador, Analista, Usuário).
-   **Configuração Whitelabel:** Permite que parceiros personalizem logo, cor e tags de rastreamento.
-   **Consulta de CNPJ:** Ferramenta interna para usuários logados consultarem dados de empresas via API.
-   **Fluxo de KYC:** Um formulário público e multi-etapas que clientes de parceiros podem preencher para submeter seus dados e documentos para análise.
-   **Painel de Análise:** Interface para que Analistas e Administradores revisem e aprovem/reprovem as submissões de KYC.

---

## 2. Conceitos Fundamentais

### a. Modelo Whitelabel

O sistema identifica qual "marca" exibir através de um parâmetro na URL: `?cliente=slug-da-empresa`.

-   **`slug-da-empresa`**: É um identificador de texto único para cada empresa parceira, configurado na página de "Configurações".
-   **Como funciona:** O arquivo `whitelabel_logic.php` detecta esse `slug`, busca no banco de dados as configurações de branding (logo, cor, GTM ID) associadas a ele e as armazena em variáveis de "contexto".
-   **Fallback:** Se nenhum `slug` for fornecido e o usuário não estiver logado, o sistema usa uma marca padrão definida no `header.php`.

### b. Hierarquia de Usuários e Permissões

Existem 4 níveis de usuários, cada um com um propósito claro:

1.  **Superadmin:**
    -   **Quem é:** O dono da plataforma (você).
    -   **O que faz:** Tem acesso total. Gerencia as empresas parceiras, todos os usuários e todas as configurações. É o único que pode criar novas empresas.
    -   **Visão:** Vê os dados de *todas* as empresas.

2.  **Administrador (Admin):**
    -   **Quem é:** O gestor da empresa parceira.
    -   **O que faz:** Gerencia os usuários (Analistas e Usuários) e as configurações de whitelabel *da sua própria empresa*.
    -   **Visão:** Vê apenas os dados (consultas, cadastros KYC) associados à sua empresa.

3.  **Analista:**
    -   **Quem é:** Um funcionário da empresa parceira.
    -   **O que faz:** Sua função principal é acessar o "Painel de Análise KYC" para revisar e dar um parecer sobre as submissões dos clientes.
    -   **Visão:** Vê apenas os cadastros KYC associados à sua empresa.

4.  **Usuário:**
    -   **Quem é:** O usuário mais básico da empresa parceira.
    -   **O que faz:** Pode apenas fazer consultas de CNPJ no dashboard e ver seu próprio histórico.
    -   **Visão:** Vê apenas os dados que ele mesmo gerou.

---

## 3. Estrutura de Arquivos e Fluxos Lógicos

### a. O Processo de Bootstrap (Inicialização de Página)

Para evitar repetição de código e centralizar a lógica, o sistema usa um arquivo "cérebro" chamado `bootstrap.php`. **Quase todas as páginas começam incluindo este arquivo.**

O fluxo de carregamento de uma página segura (ex: `dashboard.php`) é:

1.  `dashboard.php` é acessado.
2.  A primeira linha é `require_once 'bootstrap.php';`.
3.  **`bootstrap.php` assume o controle:**
    -   Inicia a sessão (`session_start()`).
    -   Carrega o `config.php` (conexão com o banco e credenciais de SMTP).
    -   Carrega o `whitelabel_logic.php` para definir o contexto de branding.
    -   Verifica se o usuário está logado. Se não estiver e a página não for pública, redireciona para o `login.php`.
    -   Define as variáveis de permissão (`$is_superadmin`, `$is_admin`, etc.) com base na `$_SESSION['user_role']`.
4.  O controle volta para o `dashboard.php`.
5.  `dashboard.php` chama o `require 'header.php';`.
6.  **`header.php` (agora apenas visual)** usa as variáveis preparadas pelo `bootstrap.php` (`$logo_url`, `$cor_variavel`, `$is_superadmin`, etc.) para renderizar o topo da página, o menu correto e as tags de rastreamento.
7.  O conteúdo específico do `dashboard.php` é exibido.
8.  `require 'footer.php';` fecha a página.

### b. Fluxo de Login (`login.php`)

A página de login é especial e **não** usa o `bootstrap.php`.

1.  O usuário preenche e-mail e senha.
2.  O script primeiro tenta encontrar uma correspondência na tabela `superadmin`.
    -   Se encontrar, cria a sessão com `role = 'superadmin'` e define as variáveis de branding padrão (o logo e a cor da sua plataforma).
3.  Se não encontrar, ele tenta encontrar uma correspondência na tabela `usuarios`.
    -   Se encontrar, cria a sessão com a `role` do usuário e busca as configurações de whitelabel (`logo_url`, `cor_variavel`, `google_tag_manager_id`) da empresa associada a ele.
4.  Se a autenticação for bem-sucedida, o usuário é redirecionado para o `dashboard.php`.

### c. Controle de Acesso (Permissões)

A segurança é aplicada em dois níveis:

1.  **Nível de Visualização (no `header.php`):** O menu de navegação usa as variáveis de permissão (`$is_superadmin`, `$is_admin`) para mostrar ou esconder os links. Isso melhora a experiência do usuário.
    ```php
    <?php if ($is_superadmin): ?>
        <a href="empresas.php">Empresas</a>
    <?php endif; ?>
    ```
2.  **Nível de Execução (no topo de cada página):** Cada página restrita (ex: `empresas.php`, `configuracoes.php`) tem um bloco de verificação no início. Se o usuário não tiver a `role` correta, a execução do script é interrompida. **Esta é a camada de segurança principal.**
    ```php
    // No topo de empresas.php
    require_once 'bootstrap.php';
    if (!$is_superadmin) {
        // Mostra erro e para a execução
        exit('Acesso negado.');
    }
    ```

---

## 4. Guia para Futuros Desenvolvedores

### a. Como Adicionar uma Nova Página Segura (ex: `relatorios.php`)

1.  Crie o arquivo `relatorios.php`.
2.  No início do arquivo, adicione:
    ```php
    <?php
    $page_title = 'Relatórios'; // Define o título que aparecerá na aba do navegador
    require_once 'bootstrap.php'; // Carrega toda a lógica principal

    // Adicione sua regra de permissão. Ex: Apenas admins e superadmins podem ver.
    if (!$is_admin && !$is_superadmin) {
        require_once 'header.php';
        echo "<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>";
        require_once 'footer.php';
        exit;
    }

    // Carrega o cabeçalho da página
    require_once 'header.php';
    ?>

    <!-- Seu conteúdo HTML aqui -->
    <div class="container">
        <h1>Página de Relatórios</h1>
        <p>Conteúdo da sua nova página.</p>
    </div>

    <?php
    // Carrega o rodapé da página
    require_once 'footer.php';
    ?>
    ```

### b. Como Adicionar um Link no Menu Principal

1.  Abra o arquivo `header.php`.
2.  Encontre a seção `<nav class='main-nav'>`.
3.  Adicione o novo link, envolvendo-o na verificação de permissão correta. Exemplo:
    ```php
    // ... outros links ...
    <?php if ($is_admin || $is_superadmin): ?>
        <a href='<?= $path_prefix ?>relatorios.php' class='<?= ($current_page_base == 'relatorios.php') ? 'active' : '' ?>'>Relatórios</a>
    <?php endif; ?>
    // ... outros links ...
    ```

---

## 5. Guia de Padronização Visual e UX

### a. Sistema de Ícones

O sistema utiliza **Bootstrap Icons** de forma padronizada em toda a aplicação.

**Ícones por Contexto:**
```html
<!-- Status de KYC -->
<i class="bi bi-check-circle-fill"></i>      <!-- Aprovado -->
<i class="bi bi-x-circle-fill"></i>          <!-- Reprovado -->
<i class="bi bi-clock-history"></i>          <!-- Em Análise -->
<i class="bi bi-exclamation-circle-fill"></i> <!-- Pendenciado -->
<i class="bi bi-pencil-square"></i>          <!-- Em Preenchimento -->
<i class="bi bi-file-earmark-plus"></i>      <!-- Novo Registro -->

<!-- Alertas de Compliance -->
<i class="bi bi-exclamation-triangle-fill"></i>    <!-- CEIS -->
<i class="bi bi-exclamation-diamond-fill"></i>     <!-- CNEP -->
<i class="bi bi-person-fill-exclamation"></i>      <!-- PEP -->

<!-- Navegação e Ações -->
<i class="bi bi-search"></i>                 <!-- Consulta -->
<i class="bi bi-file-earmark-pdf"></i>       <!-- PDF -->
<i class="bi bi-file-earmark-image"></i>     <!-- Imagem -->
<i class="bi bi-person-badge"></i>           <!-- Cliente/KYC -->
```

### b. Paleta de Cores por Status

**Status de KYC (Badges):**

| Status | Cor | Classe Bootstrap | Hex | Uso |
|--------|-----|-----------------|-----|-----|
| **Aprovado** | 🟢 Verde | `bg-success` | `#198754` | Processo concluído com sucesso |
| **Reprovado** | 🔴 Vermelho | `bg-danger` | `#dc3545` | Processo rejeitado |
| **Em Análise** | 🔵 Azul | `bg-info` | `#0dcaf0` | Em avaliação pelo analista |
| **Pendenciado** | 🟡 Amarelo | `bg-warning text-dark` | `#ffc107` | Aguardando ação/documentos |
| **Em Preenchimento** | ⚪ Cinza | `bg-secondary` | `#6c757d` | Cliente ainda preenchendo (sem ação) |
| **Novo Registro** | 🔵 Azul Escuro | `bg-primary` | `#0d6efd` | Novo cadastro enviado para análise |

**Alertas de Compliance:**

| Tipo | Cor | Classe/Style | Hex | Descrição |
|------|-----|-------------|-----|-----------|
| **CEIS** | 🔴 Vermelho | `text-danger` / `bg-danger` | `#dc3545` | Sanções administrativas |
| **CNEP** | 🟡 Amarelo | `text-warning` / `bg-warning` | `#ffc107` | Registro de penalidades |
| **PEP** | 💜 Roxo | `style="color: #6f42c1"` | `#6f42c1` | Pessoa Exposta Politicamente |

### c. Componentes de Badge com Ícone

**Padrão de Implementação:**

```php
<?php
// Função para determinar classe e ícone do badge
function getBadgeInfo($status) {
    switch ($status) {
        case 'Aprovado':
            return ['class' => 'bg-success', 'icon' => 'bi-check-circle-fill'];
        case 'Reprovado':
            return ['class' => 'bg-danger', 'icon' => 'bi-x-circle-fill'];
        case 'Em Análise':
            return ['class' => 'bg-info', 'icon' => 'bi-clock-history'];
        case 'Pendenciado':
            return ['class' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-circle-fill'];
        case 'Em Preenchimento':
            return ['class' => 'bg-secondary', 'icon' => 'bi-pencil-square'];
        case 'Novo Registro':
            return ['class' => 'bg-primary', 'icon' => 'bi-file-earmark-plus'];
        default:
            return ['class' => 'bg-light text-dark', 'icon' => 'bi-question-circle'];
    }
}

$badge = getBadgeInfo($caso['status']);
?>

<!-- Renderização do Badge -->
<span class="badge <?= $badge['class'] ?>">
    <i class="bi <?= $badge['icon'] ?> me-1"></i>
    <?= htmlspecialchars($caso['status']) ?>
</span>
```

### d. Alertas em Accordion (Padrão Visual Leve)

Os alertas de sanção (CEIS, CNEP, PEP) utilizam accordions com **fundo branco** e bordas/ícones coloridos:

```html
<!-- Exemplo: Accordion de PEP -->
<div class="accordion-item" style="border-color: #6f42c1;">
    <h2 class="accordion-header">
        <button class="accordion-button collapsed bg-white" 
                style="border-left: 4px solid #6f42c1;"
                data-bs-toggle="collapse" 
                data-bs-target="#collapsePep">
            <i class="bi bi-person-fill-exclamation me-2" style="color: #6f42c1;"></i>
            <strong>Alertas PEP</strong>
            <span class="badge ms-2" style="background-color: #6f42c1;">3</span>
        </button>
    </h2>
    <div id="collapsePep" class="accordion-collapse collapse">
        <div class="accordion-body">
            <!-- Conteúdo do alerta -->
        </div>
    </div>
</div>
```

**Características:**
- Fundo branco (`bg-white`) no botão
- Borda colorida conforme tipo (`border-color`)
- Barra lateral esquerda colorida (`border-left: 4px solid`)
- Ícone colorido no início
- Badge com contagem na mesma cor

### e. Sistema de Pin (Coluna Fixa)

Implementação de funcionalidade para "pinar" colunas com cor da empresa:

```css
#rightColumn.pinned {
    position: -webkit-sticky !important;
    position: sticky !important;
    top: 20px !important;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}

/* Scrollbar personalizado com cor da empresa */
#rightColumn.pinned::-webkit-scrollbar-thumb {
    background: var(--primary-color, #198754);
    border-radius: 10px;
}

/* Botão de pin com cor da empresa */
#rightColumn.pinned #pinDocumentosBtn {
    background-color: color-mix(in srgb, var(--primary-color) 20%, white) !important;
    border-color: var(--primary-color) !important;
    color: var(--primary-color) !important;
}
```

**JavaScript para Persistência:**

```javascript
// Recupera estado do localStorage
const isPinned = localStorage.getItem('rightColumnPinned') === 'true';
if (isPinned) {
    rightColumn.classList.add('pinned');
}

// Salva estado ao clicar
pinBtn.addEventListener('click', function(e) {
    e.preventDefault();
    rightColumn.classList.toggle('pinned');
    const pinned = rightColumn.classList.contains('pinned');
    localStorage.setItem('rightColumnPinned', pinned);
});
```

### f. Visualizador de Documentos

**Preview de Imagens:**
```javascript
if (['jpg', 'jpeg', 'png', 'gif'].includes(docExt)) {
    viewerContent.innerHTML = `
        <img src="${docPath}" 
             style="max-width: 100%; max-height: 450px; object-fit: contain;" 
             alt="Preview">
    `;
}
```

**Preview de PDFs (3 métodos):**
```javascript
// Método 1: Google Docs Viewer (Padrão)
const encodedPath = encodeURIComponent(window.location.origin + '/' + docPath);
viewerContent.innerHTML = `
    <iframe src="https://docs.google.com/viewer?url=${encodedPath}&embedded=true" 
            style="width: 100%; height: 500px; border: none;">
    </iframe>
`;

// Método 2: Embed Tag
viewerContent.innerHTML = `
    <embed src="${docPath}#toolbar=1" 
           type="application/pdf" 
           style="width: 100%; height: 500px;">
`;

// Método 3: Object Tag
viewerContent.innerHTML = `
    <object data="${docPath}" 
            type="application/pdf" 
            style="width: 100%; height: 500px;">
        <p>Navegador não suporta PDF. 
           <a href="${docPath}" target="_blank">Abrir em nova aba</a>
        </p>
    </object>
`;
```

### g. Tooltips para Melhor UX

Ativar tooltips do Bootstrap em todos os elementos:

```javascript
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
```

**Uso em Ícones:**
```html
<i class="bi bi-exclamation-triangle-fill text-danger" 
   data-bs-toggle="tooltip" 
   data-bs-placement="top" 
   title="Empresa possui sanções CEIS"></i>
```

### h. Arquivos com Padronização Implementada

| Arquivo | Badges | Ícones | Alertas | Pin | Docs |
|---------|--------|--------|---------|-----|------|
| `kyc_evaluate.php` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `kyc_list.php` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `dashboard_analytics.php` | ✅ | ✅ | ✅ | ❌ | ❌ |

### i. Checklist para Nova Página com KYC

Ao criar uma nova página que exibe dados de KYC:

- [ ] Incluir Bootstrap Icons CSS no header
- [ ] Usar função `getBadgeClass()` ou switch para badges
- [ ] Adicionar ícones nos badges de status
- [ ] Implementar tooltips para ícones de alerta
- [ ] Usar cores padronizadas (CEIS vermelho, CNEP amarelo, PEP roxo)
- [ ] Se houver alertas, usar accordion com fundo branco
- [ ] Se houver documentos, implementar preview inline
- [ ] Considerar funcionalidade de pin para colunas auxiliares

---
