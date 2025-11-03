<?php
// Teste de sessão para debug
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG DE SESSÃO AJAX ===\n\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . " (1=disabled, 2=active)\n\n";

echo "=== DADOS DA SESSÃO ===\n\n";
foreach ($_SESSION as $key => $value) {
    echo "$key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
}

if (empty($_SESSION)) {
    echo "(sessão vazia)\n";
}

echo "\n=== COOKIES ===\n\n";
foreach ($_COOKIE as $key => $value) {
    echo "$key: " . substr($value, 0, 50) . "...\n";
}

echo "\n=== HEADERS ===\n\n";
foreach (getallheaders() as $key => $value) {
    echo "$key: " . substr($value, 0, 100) . "\n";
}
