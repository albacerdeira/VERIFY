<?php
/**
 * DEBUG: Mostra TODOS os textos extraídos pelo OCR
 * Upload para: /home/u640879529/domains/verify2b.com/public_html/debug_ocr_full.php
 * 
 * USO: Envie um POST com a imagem do documento
 */

session_start();
require_once 'config.php';

// Carrega autoloader do Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once 'src/DocumentValidatorAWS.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];
    
    // Salva temporariamente
    $temp_path = sys_get_temp_dir() . '/' . uniqid('debug_ocr_') . '.jpg';
    move_uploaded_file($file['tmp_name'], $temp_path);
    
    try {
        $validator = new \Verify\DocumentValidatorAWS();
        $result = $validator->extractText($temp_path);
        
        echo "<h1>🔍 Debug OCR - Texto Completo Extraído</h1>";
        
        if ($result['success']) {
            echo "<h2>✅ OCR Bem-sucedido</h2>";
            echo "<p><strong>Confiança:</strong> " . $result['confidence'] . "%</p>";
            echo "<p><strong>Total de caracteres:</strong> " . strlen($result['text']) . "</p>";
            
            echo "<hr>";
            echo "<h2>📝 Texto Completo (Raw):</h2>";
            echo "<pre style='background:#f0f0f0; padding:15px; overflow:auto;'>";
            echo htmlspecialchars($result['text']);
            echo "</pre>";
            
            echo "<hr>";
            echo "<h2>📋 Texto Linha por Linha:</h2>";
            $lines = explode("\n", $result['text']);
            echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%;'>";
            echo "<tr><th>#</th><th>Linha</th><th>Tamanho</th></tr>";
            foreach ($lines as $i => $line) {
                $line = trim($line);
                if (!empty($line)) {
                    echo "<tr>";
                    echo "<td>" . ($i + 1) . "</td>";
                    echo "<td>" . htmlspecialchars($line) . "</td>";
                    echo "<td>" . strlen($line) . " chars</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
            
            echo "<hr>";
            echo "<h2>🔢 Padrões Numéricos Encontrados:</h2>";
            echo "<ul>";
            
            // Busca por CPFs
            preg_match_all('/\d{3}\.?\d{3}\.?\d{3}-?\d{2}/', $result['text'], $cpfs);
            if (!empty($cpfs[0])) {
                echo "<li><strong>Padrões de CPF:</strong>";
                echo "<ul>";
                foreach ($cpfs[0] as $cpf) {
                    echo "<li>" . htmlspecialchars($cpf) . "</li>";
                }
                echo "</ul></li>";
            }
            
            // Busca por RGs
            preg_match_all('/\d{1,2}\.?\d{3}\.?\d{3}-?[0-9X]/i', $result['text'], $rgs);
            if (!empty($rgs[0])) {
                echo "<li><strong>Padrões de RG:</strong>";
                echo "<ul>";
                foreach ($rgs[0] as $rg) {
                    echo "<li>" . htmlspecialchars($rg) . "</li>";
                }
                echo "</ul></li>";
            }
            
            // Busca por números de 11 dígitos
            preg_match_all('/\b\d{11}\b/', $result['text'], $nums11);
            if (!empty($nums11[0])) {
                echo "<li><strong>Números de 11 dígitos:</strong>";
                echo "<ul>";
                foreach ($nums11[0] as $num) {
                    echo "<li>" . htmlspecialchars($num) . "</li>";
                }
                echo "</ul></li>";
            }
            
            // Busca por datas
            preg_match_all('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $result['text'], $dates);
            if (!empty($dates[0])) {
                echo "<li><strong>Padrões de Data:</strong>";
                echo "<ul>";
                foreach ($dates[0] as $date) {
                    echo "<li>" . htmlspecialchars($date) . "</li>";
                }
                echo "</ul></li>";
            }
            
            echo "</ul>";
            
            echo "<hr>";
            echo "<h2>📊 Análise de Palavras Longas (Possíveis Nomes):</h2>";
            echo "<ul>";
            foreach ($lines as $line) {
                $line = trim($line);
                if (strlen($line) >= 10 && preg_match('/^[A-Z\s]+$/i', $line)) {
                    $word_count = str_word_count($line);
                    echo "<li>" . htmlspecialchars($line) . " <em>(" . $word_count . " palavras)</em></li>";
                }
            }
            echo "</ul>";
            
        } else {
            echo "<h2>❌ Erro no OCR</h2>";
            echo "<p>" . htmlspecialchars($result['error']) . "</p>";
        }
        
        // Remove arquivo temporário
        unlink($temp_path);
        
    } catch (Exception $e) {
        echo "<h2>❌ Exceção</h2>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        if (file_exists($temp_path)) {
            unlink($temp_path);
        }
    }
    
    echo "<hr>";
    echo "<p><a href='debug_ocr_full.php'>← Voltar</a></p>";
    
} else {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>Debug OCR - Texto Completo</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .upload-form { border: 2px dashed #ccc; padding: 30px; text-align: center; background: #f9f9f9; }
            input[type="file"] { margin: 20px 0; }
            button { background: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            button:hover { background: #4338ca; }
        </style>
    </head>
    <body>
        <h1>🔍 Debug OCR - Análise Completa do Texto</h1>
        <p>Faça upload de um documento (RG/CNH) para ver TODOS os textos extraídos pelo AWS Textract.</p>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data">
                <h3>📄 Selecione o Documento:</h3>
                <input type="file" name="document" accept="image/*" required>
                <br>
                <button type="submit">🚀 Analisar Documento</button>
            </form>
        </div>
        
        <hr>
        <p><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após uso:</p>
        <code>rm /home/u640879529/domains/verify2b.com/public_html/debug_ocr_full.php</code>
    </body>
    </html>
    <?php
}
?>
