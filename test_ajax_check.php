<?php
// Teste direto do endpoint
session_start();

// Simula usuário logado para teste
$_SESSION['usuario_id'] = 1;
$_SESSION['role'] = 'administrador';
$_SESSION['empresa_id'] = 18; // Forma e Conteúdo

// Simula POST
$_POST['website_url'] = 'https://formaconteudo.com.br';
$_POST['empresa_id'] = 18;

echo "=== TESTE DIRETO DO AJAX ===\n\n";
echo "Incluindo ajax_check_script_installation.php...\n\n";

include 'ajax_check_script_installation.php';
