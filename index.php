<?php

session_start();

// Verifica se o usuário não está logado
if (!isset($_SESSION['user_id'])) {
    // Se não estiver logado, redireciona para a página de login
    header('Location: login.php');
    exit;
}

// Se o usuário estiver logado, o fluxo continua
// e o conteúdo da página principal (dashboard) é incluído.

// Define o título que será usado no header.php dentro do dashboard
$page_title = 'Dashboard';

// Inclui o dashboard, que é a página principal para usuários logados.
// O dashboard.php já inclui o header.php e o footer.php
require 'dashboard.php';

?>