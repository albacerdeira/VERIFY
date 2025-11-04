O sistema opera com quatro níveis principais de usuários, cada um com um escopo de acesso diferente:

**Superadmin:**

*   **Função:** O administrador geral da plataforma.
*   **Acesso:** Possui acesso irrestrito. Sua principal função de gestão é criar e gerenciar as `Empresas` parceiras que utilizarão o sistema. Ele não pertence a nenhuma empresa específica.
*   **Páginas Típicas:** Dashboard de superadmin, página de criação/listagem de empresas.
*   **Acesso à todas as páginas.**

**Administrador (de Empresa):**

*   **Função:** O gestor principal de uma `Empresa` específica.
*   **Acesso:** Seu acesso é restrito aos dados e usuários de sua própria empresa (delimitado pelo `empresa_id`). Ele pode criar e gerenciar `Usuários` e `Analistas` dentro de sua empresa.
*   **Páginas Típicas:** Dashboard da empresa, página de criação/listagem de usuários, visualização dos formulários KYC associados à sua empresa.

**Analista (de Empresa):**

*   **Função:** O operador principal do processo de KYC. Este usuário é responsável por revisar as submissões de formulários KYC, analisar os dados e documentos, e aprovar ou reprovar os cadastros.
*   **Acesso:** Seu acesso é restrito aos dados da sua própria empresa (delimitado pelo `empresa_id`). Eles podem ver e interagir com a fila de submissões de KYC, mas não podem gerenciar usuários ou configurações da empresa.
*   **Páginas Típicas:** Fila de análise de KYC, página de detalhes da submissão de KYC (com botões de aprovação/reprovação), visualização de documentos enviados.

**Usuário (de Empresa):**

*   **Função:** Um membro padrão de uma `Empresa`, com permissões limitadas.
*   **Acesso:** Assim como o Administrador, seu acesso é restrito à sua própria empresa (`empresa_id`), mas com menos privilégios. Ele pode preencher formulários ou visualizar dados, mas não gerenciar outros usuários.

### Lógica de Autenticação (Login)

O processo de login é a porta de entrada para usuários registrados e o mecanismo que define seu contexto de acesso.

*   **Processo de Login:** Usuários (Superadmin, Admin, Analista, Usuário) inserem suas credenciais. O sistema valida essas credenciais usando `password_verify()` contra o hash armazenado no banco (`password_hash()`).
*   **Criação da Sessão:** Após o login bem-sucedido, uma sessão PHP (`session_start()`) é criada e populada com informações cruciais do usuário:
    *   `$_SESSION['user_id']`: ID único do usuário logado.
    *   `$_SESSION['role']`: O perfil do usuário (ex: 'superadmin', 'admin', 'analyst', 'user').
    *   `$_SESSION['empresa_id']`: A chave da segregação de dados. Para um Administrador, Analista ou Usuário, isso armazena o ID da empresa à qual ele pertence. Para o Superadmin, este valor pode ser nulo.
    *   `$_SESSION['nome_empresa']`: Nome da empresa do usuário, usado para personalização da interface.

### Gestão de Acesso às Páginas (Autorização)

A autorização é controlada por um "middleware" ou um script de verificação incluído no topo de cada página protegida (provavelmente no `header.php`).

*   **Verificação de Sessão:** A primeira coisa que uma página protegida faz é verificar se uma sessão de usuário existe e se ela é válida. Se não houver sessão, o usuário é redirecionado para a página de login.
*   **Controle Baseado em Role:** Em seguida, o script verifica o `$_SESSION['role']` para determinar se aquele tipo de usuário tem permissão para acessar a página.
    *   Ex: A página de "Criar Empresa" só renderiza se `$_SESSION['role'] === 'superadmin'`.
    *   Ex: A página de "Criar Usuário" só renderiza se `$_SESSION['role'] === 'admin'`.
    *   Ex: A página "Fila de Análise KYC" só renderiza se `$_SESSION['role'] === 'analyst'` ou `'admin'`.
*   **Segregação de Dados:** Para administradores, analistas e usuários, todas as consultas ao banco de dados para listar ou visualizar dados (ex: formulários KYC, outros usuários) **obrigatoriamente** incluem uma cláusula `WHERE empresa_id = ?`, utilizando o `$_SESSION['empresa_id']` como parâmetro. Isso garante que uma empresa nunca veja os dados de outra.

### Caso Especial: O Acesso ao `kyc_form.php`

O formulário KYC é a página mais complexa em termos de acesso, pois serve a dois propósitos distintos:

**Cenário 1: Acesso Público White-Label (Usuário não logado)**

*   Um cliente de um parceiro acessa a URL com um parâmetro: `kyc_form.php?cliente=nome-do-parceiro`.
*   O `whitelabel_logic.php` identifica que não há sessão de usuário, mas há um parâmetro `cliente`.
*   Ele busca no banco o `id_empresa_master` correspondente ao "slug" `nome-do-parceiro`.
*   O formulário é renderizado com a marca do parceiro, e o `id_empresa_master` é embutido em um campo oculto para ser enviado na submissão.

**Cenário 2: Acesso Interno (Usuário logado)**

*   Um Administrador, Analista ou Usuário logado acessa la URL **sem** o parâmetro: `kyc_form.php`.
*   O `whitelabel_logic.php` identifica que há uma sessão (`$_SESSION['empresa_id']` está definido).
*   Ele define o `$id_empresa_master` como o valor do `$_SESSION['empresa_id']`.
*   O formulário é renderizado com a marca da empresa do próprio usuário logado, e o ID de sua empresa é embutido no campo oculto, garantindo que o novo cadastro KYC seja associado à sua própria empresa.
