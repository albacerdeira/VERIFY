<?php
// filepath: config.php

// 1. CONFIGURAÇÕES ESSENCIAIS
// ===========================

// Define o fuso horário padrão para consistência nas funções de data e hora.
date_default_timezone_set('America/Sao_Paulo');

// A URL base completa do seu sistema. ESSENCIAL para gerar links corretos.
// Use https:// em produção.
define('SITE_URL', 'https://kyc.verify2b.com');


// 2. CONFIGURAÇÃO DO BANCO DE DADOS
// =================================
define('DB_HOST', 'localhost');
define('DB_USER', 'u640879529_kyc');
define('DB_PASS', '005@Fabio');
define('DB_NAME', 'u640879529_kyc');


// 3. CONFIGURAÇÃO DE E-MAIL (SMTP)
// ================================
// Credenciais do seu serviço de envio de e-mail.
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587); // Porta padrão para TLS/STARTTLS
define('SMTP_USER', 'noreply@foconteudo.com.br');
define('SMTP_PASS', '005@Fabio');
define('SMTP_FROM_EMAIL', 'noreply@foconteudo.com.br');
define('SMTP_FROM_NAME', 'Plataforma KYC'); // Nome que aparecerá como remetente


// 4. INICIALIZAÇÃO DA CONEXÃO COM O BANCO DE DADOS (PDO)
// =======================================================
// Nenhuma alteração é necessária aqui.

$pdo = null; // Inicializa a variável

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna resultados como array associativo
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Desabilita a emulação de prepared statements
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // Em caso de falha de conexão, registra o erro e para a execução de forma segura.
    error_log('Erro Crítico de Conexão PDO: ' . $e->getMessage());
    
    // Envia uma resposta HTTP 500 genérica, sem expor detalhes do erro.
    http_response_code(500);
    exit('Ocorreu uma falha interna no servidor. A equipe de suporte já foi notificada.');
}

?>
