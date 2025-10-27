<?php
// --- CONFIGURAÇÃO DO FUSO HORÁRIO ---
// Define o fuso horário padrão para todas as funções de data e hora.
date_default_timezone_set('America/Sao_Paulo');

// --- CONFIGURAÇÃO DO BANCO DE DADOS ---
define('DB_HOST', 'localhost');
define('DB_USER', 'u640879529_kyc');
define('DB_PASS', '005@Fabio');
define('DB_NAME', 'u640879529_kyc');

// --- CRIAÇÃO DA CONEXÃO PDO ---
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Em vez de exibir o erro, loga-o para análise e encerra a execução de forma limpa.
    error_log('Erro de Conexão com o Banco de Dados: ' . $e->getMessage());
    // Retorna uma resposta genérica para o usuário para não expor detalhes do sistema.
    http_response_code(500);
    // Garante que o script pare aqui se não houver conexão.
    exit('Ocorreu um erro interno no servidor. Por favor, tente novamente mais tarde.'); 
}

// --- CONFIGURAÇÃO DE E-MAIL (SMTP) ---
// Preencha com as suas credenciais de e-mail.
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'noreply@foconteudo.com.br');
define('SMTP_PASS', '005@Fabio');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'noreply@foconteudo.com.br');
define('SMTP_FROM_NAME', 'Plataforma KYC');

