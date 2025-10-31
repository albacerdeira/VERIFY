<?php
// Inicia a sessão para poder acessá-la.
session_start();

require_once 'config.php'; // Necessário para acessar o PDO.

$slug_parceiro = null;

// Verifica se o cliente estava logado e se o PDO está disponível.
if (isset($_SESSION['cliente_id']) && isset($pdo)) {
    try {
        // --- CORREÇÃO AQUI ---
        // Busca o slug do parceiro associado a este cliente
        // Usando c.id_empresa_master = cw.empresa_id
        $stmt = $pdo->prepare(
            'SELECT cw.slug FROM kyc_clientes c ' .
            'JOIN configuracoes_whitelabel cw ON c.id_empresa_master = cw.empresa_id ' .
            'WHERE c.id = ?'
        );
        $stmt->execute([$_SESSION['cliente_id']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($resultado) {
            $slug_parceiro = $resultado['slug'];
        }
    } catch (PDOException $e) {
        // Em caso de erro, apenas registra e continua o processo de logout.
        error_log("Erro ao buscar slug do parceiro no logout: " . $e->getMessage());
    }
}

// --- DESTRUIÇÃO DA SESSÃO ---

// Remove todas as variáveis de sessão.
$_SESSION = array();

// Destrói o cookie da sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão.
session_destroy();

// --- REDIRECIONAMENTO ---

// Constrói a URL de login, usando o slug do parceiro se ele foi encontrado.
$login_url = 'cliente_login.php';
if ($slug_parceiro) {
    $login_url .= '?cliente=' . urlencode($slug_parceiro);
}

// Redireciona para a página de login apropriada.
header("Location: " . $login_url);
exit;
?>