# Blueprint do Módulo de Consulta CNPJ

## Visão Geral

Este módulo é uma ferramenta de acesso restrito, eitelabel, projetada para permitir que usuários autenticados consultem informações detalhadas de CNPJs através de uma API externa. O acesso é controlado por um sistema de login e senha, com os dados dos usuários armazenados de forma segura em um banco de dados dedicado.

O módulo opera de forma independente, mas herda integralmente a identidade visual (CSS, componentes e assets) do site principal para garantir uma experiência de usuário coesa e consistente com a marca FDBank.

## Arquitetura e Estrutura de Arquivos

O módulo está contido inteiramente na pasta `/consulta_cnpj/` e é composto pelos seguintes arquivos principais:

*   `config.php`: Armazena as credenciais de conexão com o banco de dados (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
*   `login.php`: Página de entrada e formulário de autenticação. Valida as credenciais do usuário contra o banco de dados.
*   `index.php`: (A ser criado) A página principal da ferramenta, acessível apenas após o login. Conterá o formulário para inserir o CNPJ e a área para exibir os resultados.
*   `logout.php`: (A ser criado) Script para encerrar a sessão do usuário e redirecioná-lo para a página de login.
*   `style.css`,  `imgens/ public/,
*   `blueprint.md`: Este arquivo, documentando o módulo.

### Estrutura do Banco de Dados

*   **Tabela:** `users`
*   **Colunas:**
    *   `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
    *   `username` (VARCHAR, UNIQUE)
    *   `password` (VARCHAR) - Armazena o hash da senha, gerado por `password_hash()`.
    *   `created_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP)

## Design e Estilo

O design deste módulo não é autônomo. Ele é um **reflexo direto do site principal**. Para manter a consistência:

*   Utiliza o mesmo arquivo `style.css`.
*   Carrega dinamicamente o `header.html` and `footer.html` do projeto.
*   Aplica as mesmas classes de componentes (cards, botões, inputs) e paleta de cores.

## Plano de Ação (Próximos Passos)

1.  **Correção dos Caminhos (Paths):** Ajustar os caminhos dos assets (`style.css`, `main.js`, imagens, etc.) no `login.php` para que o navegador os encontre corretamente e a página seja renderizada com o layout certo.
2.  **Criação do `index.php`:** Desenvolver a página principal da ferramenta, protegida por sessão, com o formulário de consulta.
3.  **Implementação da API:** Integrar a chamada à API externa para buscar os dados do CNPJ.
4.  **Criação do `logout.php`:** Implementar a funcionalidade de sair do sistema.

Descrição da Aplicação
Whitelabel:

Você será o superadministrador, com acesso exclusivo para criar empresas.
Cada empresa terá um administrador que poderá criar usuários para acessar o módulo de consulta ao CNPJ.
Funcionalidades Principais:

Criação de Empresas: Apenas você pode criar empresas.
Criação de Usuários: O administrador da empresa pode criar usuários vinculados à sua empresa.
Consulta de CNPJ: Usuários podem consultar CNPJs e os dados serão armazenados automaticamente no banco.
Integração com RD Station e Google Tag Manager:
Botão para enviar os dados do CNPJ como lead para o RD Station.
Verificação no RD Station: Se o CNPJ não existir, criar um novo contato.
Fluxo de Dados:

Usuário consulta um CNPJ.
Dados são armazenados no banco.
Botão para enviar os dados ao RD Station e Google Tag Manager.
No RD Station, verifica-se se o CNPJ já existe. Caso contrário, cria-se um novo contato.
Estrutura do Banco de Dados
Tabela: superadmin

id (INT, PRIMARY KEY, AUTO_INCREMENT)
email (VARCHAR, UNIQUE)
password (VARCHAR) - Hash da senha.
Tabela: empresas

id (INT, PRIMARY KEY, AUTO_INCREMENT)
nome (VARCHAR)
email (VARCHAR, UNIQUE)
created_by (INT) - Relacionado ao superadmin.
Tabela: usuarios

id (INT, PRIMARY KEY, AUTO_INCREMENT)
nome (VARCHAR)
email (VARCHAR, UNIQUE)
password (VARCHAR) - Hash da senha.
empresa_id (INT) - Relacionado à tabela empresas.
Tabela: consultas

id (INT, PRIMARY KEY, AUTO_INCREMENT)
cnpj (VARCHAR)
dados (JSON) - Dados retornados da consulta.
usuario_id (INT) - Relacionado à tabela usuarios.
created_at (DATETIME, DEFAULT CURRENT_TIMESTAMP).
Plano de Ação
Autenticação e Controle de Acesso:

Criar sistema de login para superadministrador, administradores e usuários.
Implementar middleware para verificar permissões (ex.: superadmin pode criar empresas, admin pode criar usuários).
Criação de Empresas e Usuários:

Página para o superadmin criar empresas.
Página para o administrador criar usuários vinculados à sua empresa.
Módulo de Consulta ao CNPJ:

Página com formulário para consulta de CNPJ.
Integração com API externa para buscar dados do CNPJ.
Armazenar os dados no banco de dados.
Integração com RD Station e Google Tag Manager:

Botão para enviar os dados do CNPJ como lead para o RD Station.
Implementar verificação no RD Station para evitar duplicação de contatos.
Adicionar eventos no Google Tag Manager para rastrear ações.
Interface e Design:

Utilizar um framework CSS (ex.: Tailwind ou Bootstrap) para criar uma interface responsiva.
Garantir que a aplicação seja whitelabel, permitindo personalização por empresa (ex.: logotipo, cores).
Possíveis Inconsistências
Integração com RD Station:

Certifique-se de que a API do RD Station permite verificar e criar contatos com base no CNPJ.
Verifique os limites de requisições da API para evitar problemas de performance.
Segurança:

Use password_hash() e password_verify() para armazenar e validar senhas.
Implemente proteção contra SQL Injection e XSS.
Escalabilidade:

Certifique-se de que a estrutura do banco de dados suporta múltiplas empresas e usuários sem comprometer a performance.
Ótimo! Vamos implementar a aplicação considerando as respostas às inconsistências. Vou organizar o trabalho em etapas para garantir que tudo seja feito de forma clara e eficiente.

Plano de Implementação
Configuração Inicial:

Criar a estrutura do banco de dados com as tabelas necessárias.
Configurar o ambiente PHP e SQL para a aplicação.
Autenticação e Controle de Acesso:

Implementar login para superadmin, administradores e usuários.
Criar middleware para verificar permissões.
Criação de Empresas e Usuários:

Página para o superadmin criar empresas.
Página para o administrador criar usuários vinculados à sua empresa.
Módulo de Consulta ao CNPJ:

Página com formulário para consulta de CNPJ.
Integração com API externa para buscar dados do CNPJ.
Armazenar os dados no banco de dados.
Integração com RD Station e Google Tag Manager:

Adicionar campo para o administrador configurar o ID do Google Tag Manager da empresa.
Automatizar a instalação dos scripts do Tag Manager com base no ID configurado.
Botão para enviar os dados do CNPJ como lead para o RD Station.
Implementar verificação no RD Station para evitar duplicação de contatos.
Interface e Design:

Criar uma interface responsiva e whitelabel.
Permitir personalização por empresa (ex.: logotipo, cores).