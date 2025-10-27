A solução envolve a criação de um "portal do cliente" separado do portal administrativo que você já possui. Isso requer uma nova tabela no banco de dados, novas páginas para registro e login do cliente, e algumas modificações nos arquivos existentes para conectar tudo.

Aqui está o plano passo a passo:

Banco de Dados: Criarei uma nova tabela kyc_clientes para armazenar os dados de login dos seus clientes externos. Também adicionarei uma coluna na tabela kyc_empresas para vincular um cadastro KYC a um cliente.
Novos Arquivos: Criarei os arquivos necessários para o fluxo do cliente:
cliente_registro.php: Página para o cliente se cadastrar.
cliente_verificacao.php: Página para validar o e-mail com um código.
cliente_login.php: Uma nova tela de login, que pode ser whitelabel, exclusiva para clientes.
cliente_dashboard.php: Uma página simples para o cliente ver o status do seu KYC ou iniciar o preenchimento.
Modificações: Ajustarei os arquivos existentes para integrar o novo sistema:
bootstrap.php: Para reconhecer quando um cliente está logado.
kyc_form.php e kyc_submit.php: Para associar o formulário preenchido ao cliente que está logado.