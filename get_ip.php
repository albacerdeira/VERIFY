<?php
// Este script simples retorna o endereço de IP de saída atual do servidor.
// Útil para solicitar a liberação (whitelisting) em APIs externas.
header('Content-Type: text/plain');
$ip = @file_get_contents('https://api.ipify.org');
if ($ip) {
    echo "O endereço de IP de saída atual do servidor é: " . $ip;
} else {
    echo "Não foi possível obter o endereço de IP de saída.";
}
?>
