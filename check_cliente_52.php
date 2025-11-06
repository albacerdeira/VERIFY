<?php
require_once 'config.php';

$stmt = $pdo->prepare("SELECT id, nome_completo, email, id_empresa_master FROM kyc_clientes WHERE id = 52");
$stmt->execute();
$c = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Cliente #52:" . PHP_EOL;
echo "Nome: " . $c['nome_completo'] . PHP_EOL;
echo "Email: " . $c['email'] . PHP_EOL;
echo "id_empresa_master: " . ($c['id_empresa_master'] ?: 'NULL') . PHP_EOL;
