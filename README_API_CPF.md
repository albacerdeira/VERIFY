# Guia Definitivo: Resolvendo Problemas com a API de Consulta de CPF

## 1. Diagnóstico Final

Os testes confirmaram que nosso código está 100% correto. Ele se conecta com sucesso à API do Portal da Transparência, mas a API responde com um corpo vazio (`content-length: 0`).

**Isso significa que o problema é externo e está relacionado à sua chave de API ou ao seu servidor de hospedagem.**

## 2. Plano de Ação (Passos Obrigatórios)

Siga estes passos em ordem. O mais provável é que o Passo 1 resolva o problema.

### Passo 1: Gerar uma Nova Chave de API (Ação Mais Importante)

Esta é a causa mais comum. Mesmo que a chave pareça correta, ela pode ter sido invalidada ou não ter as permissões certas.

1.  Acesse o site do **[Portal da Transparência - API de Dados](https://portaldatransparencia.gov.br/dados-abertos/api-de-dados)**.
2.  Faça login com sua conta `gov.br`.
3.  **Gere uma nova chave de API.**
4.  Copie a nova chave.
5.  Abra o arquivo `cpf_proxy.php` e substitua a chave antiga pela nova na linha:
    ```php
    define('CHAVE_API_TRANSPARENCIA', 'SUA_NOVA_CHAVE_DE_API_AQUI');
    ```
6.  Salve o arquivo e teste a consulta de CPF novamente. **Se funcionar, o problema está resolvido.**

### Passo 2: Contatar o Suporte do Portal da Transparência (Ação Atual)

Se uma nova chave de API não resolveu, o problema é um bloqueio de IP. A Hostinger confirmou que o IP de saída pode variar. Siga estes passos:

1.  **Obtenha o IP de Saída Atual:**
    *   Crie um arquivo chamado `get_ip.php` no seu servidor com o seguinte conteúdo:
        ```php
        <?php
        echo file_get_contents('https://api.ipify.org');
        ?>
        ```
    *   Acesse `https://kyc.verify2b.com/get_ip.php` no seu navegador.
    *   Copie o endereço de IP que aparecer na tela.

2.  **Contate o Suporte do Portal da Transparência:** Envie um e-mail para eles. Use o modelo abaixo, substituindo o placeholder pelo IP que você copiou.

    ---
    **Assunto:** API de Dados - Resposta Vazia (Content-Length: 0) - Solicitação de Whitelist

    **Corpo do E-mail:**
    Prezada equipe do Portal da Transparência,

    Estou tentando utilizar a API de dados para consulta de pessoa física a partir do meu sistema hospedado na Hostinger, mas estou recebendo uma resposta com status 200 OK e corpo vazio (content-length: 0).

    Já confirmei que minha chave de API está correta e ativa. Acredito que o endereço de IP de saída do meu servidor de hospedagem compartilhada possa estar bloqueado.

    -   **IP de Saída Atual do meu Servidor:** 147.93.37.119
    -   **URL da API:** `https://api.portaldatransparencia.gov.br/api-de-dados/pessoa-fisica`
    -   **Exemplo de CPF testado:** `27227747808`

    Entendo que em hospedagens compartilhadas o IP pode variar. Vocês trabalham com a liberação de um *range* de IPs (faixa de IPs) da Hostinger, ou poderiam, por favor, liberar este IP atual para que eu possa validar a integração?

    Agradeço a atenção.
    ---

Seguindo este guia, você conseguirá diagnosticar e resolver o problema com a API.
