<?php
/**
 * DEBUG: Diagnóstico do Autoloader
 * Upload para: /home/u640879529/domains/verify2b.com/public_html/debug_autoloader.php
 * Acesse: https://verify2b.com/debug_autoloader.php
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Autoloader - Verify KYC</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .section { border: 1px solid #333; padding: 15px; margin: 10px 0; background: #000; }
        h2 { color: #0ff; }
        pre { background: #222; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>🔍 Diagnóstico do Autoloader</h1>

<div class="section">
<h2>1. Informações do Servidor</h2>
<?php
echo "<strong>Caminho atual:</strong> " . __DIR__ . "<br>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>Server:</strong> " . $_SERVER['SERVER_NAME'] . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
?>
</div>

<div class="section">
<h2>2. Verificação do Autoloader</h2>
<?php
$autoload_path = __DIR__ . '/vendor/autoload.php';
echo "<strong>Caminho esperado:</strong> <code>{$autoload_path}</code><br>";

if (file_exists($autoload_path)) {
    echo "<span class='ok'>✅ Arquivo autoload.php EXISTE</span><br>";
    echo "<strong>Tamanho:</strong> " . filesize($autoload_path) . " bytes<br>";
    echo "<strong>Permissões:</strong> " . substr(sprintf('%o', fileperms($autoload_path)), -4) . "<br>";
    echo "<strong>Readable:</strong> " . (is_readable($autoload_path) ? '<span class="ok">SIM</span>' : '<span class="error">NÃO</span>') . "<br>";
} else {
    echo "<span class='error'>❌ Arquivo autoload.php NÃO EXISTE</span><br>";
}
?>
</div>

<div class="section">
<h2>3. Estrutura da Pasta vendor/</h2>
<?php
$vendor_dir = __DIR__ . '/vendor';
if (is_dir($vendor_dir)) {
    echo "<span class='ok'>✅ Pasta vendor/ existe</span><br>";
    echo "<strong>Permissões:</strong> " . substr(sprintf('%o', fileperms($vendor_dir)), -4) . "<br><br>";
    
    echo "<strong>Conteúdo:</strong><br>";
    $items = scandir($vendor_dir);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            $path = $vendor_dir . '/' . $item;
            $type = is_dir($path) ? '[DIR]' : '[FILE]';
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            echo "{$type} {$item} (perms: {$perms})<br>";
        }
    }
} else {
    echo "<span class='error'>❌ Pasta vendor/ NÃO EXISTE</span><br>";
}
?>
</div>

<div class="section">
<h2>4. Verificação AWS SDK</h2>
<?php
$aws_dir = __DIR__ . '/vendor/aws';
if (is_dir($aws_dir)) {
    echo "<span class='ok'>✅ Pasta vendor/aws/ existe</span><br>";
    
    $items = scandir($aws_dir);
    foreach ($items as $item) {
        if ($item != '.' && $item != '..') {
            echo "  - {$item}<br>";
        }
    }
    
    // Verifica aws-sdk-php
    $sdk_path = $aws_dir . '/aws-sdk-php';
    if (is_dir($sdk_path)) {
        echo "<br><span class='ok'>✅ Pasta aws-sdk-php existe</span><br>";
        
        // Verifica src/
        $src_path = $sdk_path . '/src';
        if (is_dir($src_path)) {
            echo "<span class='ok'>✅ Pasta src/ existe</span><br>";
            
            // Lista os serviços disponíveis
            $services = scandir($src_path);
            $service_count = 0;
            foreach ($services as $service) {
                if ($service != '.' && $service != '..' && is_dir($src_path . '/' . $service)) {
                    $service_count++;
                }
            }
            echo "<strong>Serviços AWS encontrados:</strong> {$service_count}<br>";
            
            // Verifica Textract e Rekognition especificamente
            $textract_path = $src_path . '/Textract';
            $rekognition_path = $src_path . '/Rekognition';
            
            echo "<br>";
            if (is_dir($textract_path)) {
                echo "<span class='ok'>✅ Textract existe</span><br>";
            } else {
                echo "<span class='error'>❌ Textract NÃO existe</span><br>";
            }
            
            if (is_dir($rekognition_path)) {
                echo "<span class='ok'>✅ Rekognition existe</span><br>";
            } else {
                echo "<span class='error'>❌ Rekognition NÃO existe</span><br>";
            }
        } else {
            echo "<span class='error'>❌ Pasta src/ NÃO existe</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Pasta aws-sdk-php NÃO existe</span><br>";
    }
} else {
    echo "<span class='error'>❌ Pasta vendor/aws/ NÃO EXISTE</span><br>";
}
?>
</div>

<div class="section">
<h2>5. Teste de Carregamento do Autoloader</h2>
<?php
if (file_exists($autoload_path)) {
    try {
        echo "Tentando carregar autoloader...<br>";
        require_once $autoload_path;
        echo "<span class='ok'>✅ Autoloader carregado com sucesso!</span><br><br>";
        
        // Verifica se as classes estão disponíveis
        echo "<strong>Verificando classes AWS:</strong><br>";
        
        if (class_exists('Aws\Textract\TextractClient')) {
            echo "<span class='ok'>✅ Aws\\Textract\\TextractClient DISPONÍVEL</span><br>";
        } else {
            echo "<span class='error'>❌ Aws\\Textract\\TextractClient NÃO ENCONTRADO</span><br>";
        }
        
        if (class_exists('Aws\Rekognition\RekognitionClient')) {
            echo "<span class='ok'>✅ Aws\\Rekognition\\RekognitionClient DISPONÍVEL</span><br>";
        } else {
            echo "<span class='error'>❌ Aws\\Rekognition\\RekognitionClient NÃO ENCONTRADO</span><br>";
        }
        
        if (class_exists('GuzzleHttp\Client')) {
            echo "<span class='ok'>✅ GuzzleHttp\\Client DISPONÍVEL</span><br>";
        } else {
            echo "<span class='error'>❌ GuzzleHttp\\Client NÃO ENCONTRADO</span><br>";
        }
        
        // Tenta instanciar (sem credenciais, apenas para testar se a classe carrega)
        echo "<br><strong>Testando instanciação:</strong><br>";
        try {
            $test = new ReflectionClass('Aws\Textract\TextractClient');
            echo "<span class='ok'>✅ Reflection de TextractClient OK</span><br>";
            echo "Namespace: " . $test->getNamespaceName() . "<br>";
            echo "Arquivo: " . $test->getFileName() . "<br>";
        } catch (Exception $e) {
            echo "<span class='error'>❌ Erro: " . $e->getMessage() . "</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span class='error'>❌ ERRO ao carregar autoloader:</span><br>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
} else {
    echo "<span class='error'>❌ Não é possível testar - autoload.php não existe</span><br>";
}
?>
</div>

<div class="section">
<h2>6. Composer Autoload Files</h2>
<?php
$composer_autoload = __DIR__ . '/vendor/composer/autoload_classmap.php';
if (file_exists($composer_autoload)) {
    echo "<span class='ok'>✅ autoload_classmap.php existe</span><br>";
    
    $classmap = include $composer_autoload;
    if (is_array($classmap)) {
        echo "<strong>Total de classes mapeadas:</strong> " . count($classmap) . "<br>";
        
        // Procura por classes AWS
        $aws_classes = array_filter(array_keys($classmap), function($class) {
            return strpos($class, 'Aws\\') === 0;
        });
        echo "<strong>Classes AWS no classmap:</strong> " . count($aws_classes) . "<br>";
        
        if (count($aws_classes) > 0) {
            echo "<br><strong>Primeiras 10 classes AWS:</strong><br>";
            $i = 0;
            foreach ($aws_classes as $class) {
                if ($i++ >= 10) break;
                echo "  - {$class}<br>";
            }
        }
    }
} else {
    echo "<span class='warning'>⚠️ autoload_classmap.php não existe</span><br>";
}

$psr4_file = __DIR__ . '/vendor/composer/autoload_psr4.php';
if (file_exists($psr4_file)) {
    echo "<br><span class='ok'>✅ autoload_psr4.php existe</span><br>";
    $psr4 = include $psr4_file;
    if (is_array($psr4)) {
        echo "<strong>Namespaces PSR-4 registrados:</strong><br>";
        foreach ($psr4 as $namespace => $paths) {
            echo "  - {$namespace}<br>";
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    echo "    → {$path}<br>";
                }
            }
        }
    }
} else {
    echo "<span class='error'>❌ autoload_psr4.php NÃO EXISTE</span><br>";
}
?>
</div>

<div class="section">
<h2>7. Include Path do PHP</h2>
<?php
echo "<pre>" . htmlspecialchars(get_include_path()) . "</pre>";
?>
</div>

<div class="section">
<h2>8. Sugestões de Correção</h2>
<?php
$issues = [];

if (!file_exists($autoload_path)) {
    $issues[] = "Execute: composer install no diretório do projeto";
}

if (file_exists($autoload_path) && !is_readable($autoload_path)) {
    $issues[] = "Ajuste permissões: chmod 644 vendor/autoload.php";
}

if (!is_dir($aws_dir)) {
    $issues[] = "Execute: composer require aws/aws-sdk-php";
}

if (empty($issues)) {
    echo "<span class='ok'>✅ Nenhum problema óbvio detectado!</span><br>";
    echo "<br>Se ainda há erro, o problema pode ser:<br>";
    echo "- Cache do OPcache (reinicie o servidor web ou limpe o cache)<br>";
    echo "- Permissões incorretas nos arquivos PHP dentro de vendor/<br>";
    echo "- composer.lock desatualizado (execute: composer dump-autoload)<br>";
} else {
    echo "<span class='warning'>⚠️ Problemas detectados:</span><br><br>";
    foreach ($issues as $issue) {
        echo "• {$issue}<br>";
    }
}
?>
</div>

<hr>
<p><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após o diagnóstico!</p>
<code>rm /home/u640879529/domains/verify2b.com/public_html/debug_autoloader.php</code>

</body>
</html>
