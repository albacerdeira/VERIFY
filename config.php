<?php
// Habilita o log de erros, mas não exibe para o usuário em produção
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log'); // Garante que os erros sejam registrados
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');

// --- CONFIGURAÇÃO DO BANCO DE DADOS ---
define('DB_HOST', 'localhost');
define('DB_USER', 'u640879529_kyc');
define('DB_PASS', '005@Fabio');
define('DB_NAME', 'u640879529_kyc');
define('SITE_URL', 'https://verify2b.com');

// --- CRIAÇÃO DA CONEXÃO PDO ---
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    // Define timezone do MySQL para horário de Brasília (GMT-3)
    $pdo->exec("SET time_zone = '-03:00'");
    
} catch (PDOException $e) {
    error_log('FATAL: Erro de Conexão com o Banco de Dados: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno no servidor.'); 
}

// --- CONFIGURAÇÃO DE E-MAIL (SMTP) ---
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'noreply@foconteudo.com.br');
define('SMTP_PASS', '005@Fabio');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'noreply@foconteudo.com.br');
define('SMTP_FROM_NAME', 'Plataforma KYC');

// --- CAMINHOS E DIRETÓRIOS ---
define('SERVER_ROOT', __DIR__);
define('UPLOAD_DIR', SERVER_ROOT . '/uploads');
define('PHPMAILER_DIR', SERVER_ROOT . '/vendor/phpmailer/phpmailer/src/');
