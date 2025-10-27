<?php
// Inicia a sessão como a primeira ação de todas.
session_start();

require_once 'config.php';

// Configurações de erro
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Captura o slug e o código da URL
$slug_contexto = $_GET['cliente'] ?? null;
$codigo = $_GET['codigo'] ?? '';

// Prepara a URL de login para redirecionamento em caso de erro, mantendo o contexto.
$login_url = "cliente_login.php" . ($slug_contexto ? "?cliente=" . urlencode($slug_contexto) : "");

try {
    if (empty($codigo)) {
        throw new Exception('Código de verificação não fornecido.');
    }

    if (!isset($pdo)) {
        throw new Exception('Falha na conexão com o banco de dados.');
    }

    $pdo->beginTransaction();

    // PASSO 1: Busca o cliente pelo código e obtém os dados necessários para o auto-login.
    $stmt = $pdo->prepare("SELECT id, nome_completo, email FROM kyc_clientes WHERE codigo_verificacao = ? AND codigo_expira_em > NOW()");
    $stmt->execute([$codigo]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        // Se o código for inválido, expirado ou já tiver sido usado, redireciona com erro.
        $_SESSION['error_message'] = 'Código de verificação inválido, expirado ou já utilizado.';
        header("Location: " . $login_url);
        exit;
    }

    // PASSO 2: O código é válido, então atualiza o cliente para 'ativo' e limpa os dados de verificação.
    $stmt_update = $pdo->prepare("
        UPDATE kyc_clientes 
        SET email_verificado = 1, 
            status = 'ativo', 
            codigo_verificacao = NULL, 
            codigo_expira_em = NULL
        WHERE id = ?
    ");
    $stmt_update->execute([$cliente['id']]);

    $pdo->commit();

    // PASSO 3: AUTO-LOGIN
    // Cria a sessão completa para o cliente, logando-o automaticamente.
    $_SESSION['cliente_id'] = $cliente['id'];
    $_SESSION['cliente_nome'] = $cliente['nome_completo'];
    $_SESSION['cliente_email'] = $cliente['email']; // Garante que a sessão esteja completa.

    // PASSO 4: REDIRECIONAMENTO COM CONTEXTO
    // Redireciona para o painel, passando o slug do parceiro para garantir a experiência correta.
    $dashboard_url = 'cliente_dashboard.php';
    if ($slug_contexto) {
        $dashboard_url .= '?cliente=' . urlencode($slug_contexto);
    }
    header('Location: ' . $dashboard_url);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Em caso de erro, registra, define a mensagem e redireciona para o login.
    error_log("Erro na verificação de e-mail: " . $e->getMessage());
    $_SESSION['error_message'] = 'Ocorreu um erro durante a verificação. Por favor, tente novamente ou contate o suporte.'; // Mensagem genérica para o usuário.
    header("Location: " . $login_url);
    exit;
}
?>
