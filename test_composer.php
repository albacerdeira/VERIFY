<?php
/**
 * Teste de Instalação do Composer e AWS SDK
 * Upload para: /home/u640879529/domains/verify2b.com/public_html/test_composer.php
 * Acesse: https://verify2b.com/test_composer.php
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Composer - Verify KYC</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #4f46e5;
            margin-top: 0;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #ef4444;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
            font-weight: bold;
        }
        .info {
            background: #dbeafe;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #3b82f6;
        }
        .test-item {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #ccc;
            padding-left: 15px;
        }
        .test-item.pass {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .test-item.fail {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .command {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
        .status-icon {
            font-size: 24px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Teste de Instalação - Composer & AWS SDK</h1>
        
        <?php
        $all_ok = true;
        
        // Teste 1: Composer Autoloader
        echo '<div class="test-item ';
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            echo 'pass"><span class="status-icon">✅</span>';
            echo '<strong>Composer Autoloader:</strong> Encontrado e carregado!';
            require_once __DIR__ . '/vendor/autoload.php';
        } else {
            echo 'fail"><span class="status-icon">❌</span>';
            echo '<strong>Composer Autoloader:</strong> NÃO encontrado!';
            $all_ok = false;
            echo '<div class="info">';
            echo '<strong>Solução:</strong> Execute no SSH:<br>';
            echo '<div class="command">cd ~/domains/verify2b.com/public_html<br>';
            echo 'composer install --no-dev --optimize-autoloader</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Teste 2: PHP Version
        echo '<div class="test-item ';
        $php_version = phpversion();
        if (version_compare($php_version, '7.4.0', '>=')) {
            echo 'pass"><span class="status-icon">✅</span>';
            echo '<strong>Versão do PHP:</strong> ' . $php_version . ' (OK)';
        } else {
            echo 'fail"><span class="status-icon">⚠️</span>';
            echo '<strong>Versão do PHP:</strong> ' . $php_version . ' (Requer >= 7.4)';
            $all_ok = false;
        }
        echo '</div>';
        
        // Teste 3: AWS Textract
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            echo '<div class="test-item ';
            if (class_exists('Aws\Textract\TextractClient')) {
                echo 'pass"><span class="status-icon">✅</span>';
                echo '<strong>AWS Textract Client:</strong> Disponível!';
            } else {
                echo 'fail"><span class="status-icon">❌</span>';
                echo '<strong>AWS Textract Client:</strong> NÃO encontrado!';
                $all_ok = false;
            }
            echo '</div>';
            
            // Teste 4: AWS Rekognition
            echo '<div class="test-item ';
            if (class_exists('Aws\Rekognition\RekognitionClient')) {
                echo 'pass"><span class="status-icon">✅</span>';
                echo '<strong>AWS Rekognition Client:</strong> Disponível!';
            } else {
                echo 'fail"><span class="status-icon">❌</span>';
                echo '<strong>AWS Rekognition Client:</strong> NÃO encontrado!';
                $all_ok = false;
            }
            echo '</div>';
            
            // Teste 5: Guzzle
            echo '<div class="test-item ';
            if (class_exists('GuzzleHttp\Client')) {
                echo 'pass"><span class="status-icon">✅</span>';
                echo '<strong>Guzzle HTTP Client:</strong> Disponível!';
            } else {
                echo 'fail"><span class="status-icon">❌</span>';
                echo '<strong>Guzzle HTTP Client:</strong> NÃO encontrado!';
                $all_ok = false;
            }
            echo '</div>';
        }
        
        // Teste 6: Verify Classes
        echo '<div class="test-item ';
        if (file_exists(__DIR__ . '/src/FaceValidator.php')) {
            echo 'pass"><span class="status-icon">✅</span>';
            echo '<strong>FaceValidator:</strong> Arquivo existe';
        } else {
            echo 'fail"><span class="status-icon">❌</span>';
            echo '<strong>FaceValidator:</strong> Arquivo NÃO encontrado!';
            $all_ok = false;
        }
        echo '</div>';
        
        echo '<div class="test-item ';
        if (file_exists(__DIR__ . '/src/DocumentValidatorAWS.php')) {
            echo 'pass"><span class="status-icon">✅</span>';
            echo '<strong>DocumentValidatorAWS:</strong> Arquivo existe';
        } else {
            echo 'fail"><span class="status-icon">❌</span>';
            echo '<strong>DocumentValidatorAWS:</strong> Arquivo NÃO encontrado!';
            $all_ok = false;
        }
        echo '</div>';
        
        // Teste 7: .env file
        echo '<div class="test-item ';
        if (file_exists(__DIR__ . '/.env')) {
            echo 'pass"><span class="status-icon">✅</span>';
            echo '<strong>Arquivo .env:</strong> Encontrado';
            
            // Verifica credenciais AWS (sem revelar valores)
            $env_content = file_get_contents(__DIR__ . '/.env');
            $has_key = strpos($env_content, 'AWS_ACCESS_KEY_ID') !== false;
            $has_secret = strpos($env_content, 'AWS_SECRET_ACCESS_KEY') !== false;
            
            if ($has_key && $has_secret) {
                echo ' <span class="success">(credenciais AWS configuradas)</span>';
            } else {
                echo ' <span class="warning">(credenciais AWS podem estar faltando)</span>';
            }
        } else {
            echo 'fail"><span class="status-icon">⚠️</span>';
            echo '<strong>Arquivo .env:</strong> NÃO encontrado (opcional)';
        }
        echo '</div>';
        
        // Teste 8: Uploads folder
        echo '<div class="test-item ';
        if (is_dir(__DIR__ . '/uploads') && is_writable(__DIR__ . '/uploads')) {
            echo 'pass"><span class="status-icon">✅</span>';
            echo '<strong>Pasta uploads/:</strong> Existe e tem permissão de escrita';
        } else {
            echo 'fail"><span class="status-icon">⚠️</span>';
            echo '<strong>Pasta uploads/:</strong> Não encontrada ou sem permissão';
            echo '<div class="info">';
            echo '<strong>Solução:</strong><br>';
            echo '<div class="command">mkdir -p uploads/temp_documents uploads/selfies<br>';
            echo 'chmod -R 755 uploads/</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Resultado Final
        echo '<hr style="margin: 30px 0;">';
        if ($all_ok) {
            echo '<div class="test-item pass" style="font-size: 18px;">';
            echo '<span class="status-icon">🎉</span>';
            echo '<strong>TODOS OS TESTES PASSARAM!</strong><br>';
            echo 'O sistema está pronto para uso. Você pode deletar este arquivo agora.';
            echo '</div>';
        } else {
            echo '<div class="test-item fail" style="font-size: 18px;">';
            echo '<span class="status-icon">⚠️</span>';
            echo '<strong>ALGUNS TESTES FALHARAM</strong><br>';
            echo 'Corrija os problemas acima e recarregue esta página.';
            echo '</div>';
        }
        ?>
    </div>
    
    <div class="card">
        <h2>📋 Informações do Sistema</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px;"><strong>Servidor:</strong></td>
                <td style="padding: 10px;"><?php echo $_SERVER['SERVER_NAME']; ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px;"><strong>Caminho Raiz:</strong></td>
                <td style="padding: 10px;"><code><?php echo __DIR__; ?></code></td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px;"><strong>PHP Version:</strong></td>
                <td style="padding: 10px;"><?php echo phpversion(); ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px;"><strong>Memória Disponível:</strong></td>
                <td style="padding: 10px;"><?php echo ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px;"><strong>Upload Max Size:</strong></td>
                <td style="padding: 10px;"><?php echo ini_get('upload_max_filesize'); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="info">
        <strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após confirmar que tudo está funcionando:<br>
        <div class="command">rm ~/domains/verify2b.com/public_html/test_composer.php</div>
    </div>
</body>
</html>
