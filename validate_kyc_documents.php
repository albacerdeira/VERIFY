<?php
/**
 * Script de validação de documentos KYC
 * Integra Tesseract OCR para documentos e AWS Rekognition para selfies
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/DocumentValidator.php';
require_once __DIR__ . '/src/FaceValidator.php';

use Verify\DocumentValidator;
use Verify\FaceValidator;

// Carrega variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

/**
 * Valida documentos de um cliente KYC
 * 
 * @param array $files Array com caminhos dos arquivos
 * @return array Resultado da validação
 */
function validateKYCDocuments($files) {
    $result = [
        'success' => false,
        'document_validation' => [],
        'face_validation' => [],
        'extracted_data' => [],
        'errors' => [],
        'warnings' => []
    ];

    // Inicializa validadores
    $documentValidator = new DocumentValidator();
    $faceValidator = new FaceValidator();

    // 1. VALIDA SELFIE
    if (isset($files['selfie']) && file_exists($files['selfie'])) {
        echo "🔍 Validando selfie...\n";
        
        $faceDetection = $faceValidator->detectFace($files['selfie']);
        
        if ($faceDetection['success']) {
            $result['face_validation']['selfie'] = [
                'detected' => true,
                'face_count' => $faceDetection['face_count'],
                'quality' => $faceDetection['quality']
            ];

            if ($faceDetection['face_count'] > 1) {
                $result['warnings'][] = 'Múltiplas faces detectadas na selfie';
            }

            if (!$faceDetection['quality']['is_good_quality']) {
                $result['warnings'][] = 'Qualidade da selfie está abaixo do ideal: ' . 
                                        $faceDetection['quality']['overall_score'] . '/100';
                foreach ($faceDetection['quality']['warnings'] as $warning) {
                    $result['warnings'][] = 'Selfie: ' . $warning;
                }
            }

            echo "✅ Selfie validada - Score: {$faceDetection['quality']['overall_score']}/100\n";
        } else {
            $result['errors'][] = 'Selfie: ' . $faceDetection['error'];
            echo "❌ Erro na selfie: {$faceDetection['error']}\n";
        }
    }

    // 2. VALIDA DOCUMENTO COM FOTO (RG, CNH, etc.)
    if (isset($files['documento_foto']) && file_exists($files['documento_foto'])) {
        echo "\n🔍 Validando documento com foto...\n";
        
        // Extrai texto do documento
        $ocrResult = $documentValidator->extractText($files['documento_foto']);
        
        if ($ocrResult['success']) {
            $text = $ocrResult['text'];
            $result['document_validation']['documento_foto'] = [
                'text_extracted' => true,
                'confidence' => $ocrResult['confidence'],
                'text_length' => strlen($text)
            ];

            echo "✅ Texto extraído - Confiança: {$ocrResult['confidence']}%\n";

            // Extrai dados específicos
            $cpf = $documentValidator->extractCPF($text);
            if ($cpf) {
                $result['extracted_data']['cpf'] = $cpf;
                echo "   📋 CPF encontrado: {$cpf['formatted']}\n";
            }

            $rg = $documentValidator->extractRG($text);
            if ($rg) {
                $result['extracted_data']['rg'] = $rg;
                echo "   📋 RG encontrado: {$rg['formatted']}\n";
            }

            $cnh = $documentValidator->extractCNH($text);
            if ($cnh) {
                $result['extracted_data']['cnh'] = $cnh;
                echo "   📋 CNH encontrada: {$cnh['formatted']}\n";
            }

            $nome = $documentValidator->extractName($text);
            if ($nome) {
                $result['extracted_data']['nome'] = $nome;
                echo "   📋 Nome encontrado: {$nome}\n";
            }

            // Detecta face no documento
            $faceInDoc = $faceValidator->detectFace($files['documento_foto']);
            if ($faceInDoc['success']) {
                $result['face_validation']['documento'] = [
                    'detected' => true,
                    'quality' => $faceInDoc['quality']
                ];
                echo "   ✅ Face detectada no documento\n";
            } else {
                $result['warnings'][] = 'Não foi possível detectar face no documento';
                echo "   ⚠️  Face não detectada no documento\n";
            }

        } else {
            $result['errors'][] = 'Documento: ' . $ocrResult['error'];
            echo "❌ Erro ao extrair texto: {$ocrResult['error']}\n";
        }
    }

    // 3. COMPARA SELFIE COM DOCUMENTO
    if (isset($files['selfie']) && isset($files['documento_foto']) && 
        file_exists($files['selfie']) && file_exists($files['documento_foto'])) {
        
        echo "\n🔍 Comparando selfie com documento...\n";
        
        $comparison = $faceValidator->compareFaces($files['selfie'], $files['documento_foto']);
        
        if ($comparison['success']) {
            $result['face_validation']['comparison'] = [
                'match' => $comparison['match'],
                'similarity' => $comparison['similarity'],
                'confidence' => $comparison['confidence']
            ];

            if ($comparison['match']) {
                echo "✅ Faces correspondem! Similaridade: {$comparison['similarity']}%\n";
            } else {
                echo "❌ Faces NÃO correspondem. Similaridade: {$comparison['similarity']}%\n";
                $result['errors'][] = 'Selfie não corresponde à foto do documento';
            }
        } else {
            $result['warnings'][] = 'Não foi possível comparar as faces: ' . $comparison['error'];
            echo "⚠️  Não foi possível comparar: {$comparison['error']}\n";
        }
    }

    // 4. VALIDA COMPROVANTE DE RESIDÊNCIA
    if (isset($files['comprovante_residencia']) && file_exists($files['comprovante_residencia'])) {
        echo "\n🔍 Validando comprovante de residência...\n";
        
        $ocrResult = $documentValidator->extractText($files['comprovante_residencia']);
        
        if ($ocrResult['success']) {
            $text = $ocrResult['text'];
            $result['document_validation']['comprovante'] = [
                'text_extracted' => true,
                'confidence' => $ocrResult['confidence']
            ];

            echo "✅ Texto extraído - Confiança: {$ocrResult['confidence']}%\n";

            // Tenta extrair CPF/CNPJ do comprovante
            $cpfComprovante = $documentValidator->extractCPF($text);
            if ($cpfComprovante) {
                $result['extracted_data']['cpf_comprovante'] = $cpfComprovante;
                echo "   📋 CPF no comprovante: {$cpfComprovante['formatted']}\n";
            }

            $cnpjComprovante = $documentValidator->extractCNPJ($text);
            if ($cnpjComprovante) {
                $result['extracted_data']['cnpj_comprovante'] = $cnpjComprovante;
                echo "   📋 CNPJ no comprovante: {$cnpjComprovante['formatted']}\n";
            }

        } else {
            $result['errors'][] = 'Comprovante: ' . $ocrResult['error'];
            echo "❌ Erro ao extrair texto: {$ocrResult['error']}\n";
        }
    }

    // Determina sucesso geral
    $result['success'] = empty($result['errors']);
    
    return $result;
}

/**
 * Exemplo de uso
 */
if (php_sapi_name() === 'cli') {
    echo "\n=== VALIDADOR DE DOCUMENTOS KYC ===\n\n";

    // Exemplo de arquivos (ajuste os caminhos conforme necessário)
    $files = [
        'selfie' => __DIR__ . '/uploads/selfies/exemplo_selfie.jpg',
        'documento_foto' => __DIR__ . '/uploads/documentos/exemplo_rg.jpg',
        'comprovante_residencia' => __DIR__ . '/uploads/comprovantes/exemplo_conta.pdf'
    ];

    // Executa validação
    $result = validateKYCDocuments($files);

    // Exibe resultado final
    echo "\n=== RESULTADO FINAL ===\n";
    echo "Status: " . ($result['success'] ? '✅ APROVADO' : '❌ REPROVADO') . "\n";
    echo "Erros: " . count($result['errors']) . "\n";
    echo "Avisos: " . count($result['warnings']) . "\n\n";

    if (!empty($result['errors'])) {
        echo "❌ Erros encontrados:\n";
        foreach ($result['errors'] as $error) {
            echo "   - $error\n";
        }
    }

    if (!empty($result['warnings'])) {
        echo "\n⚠️  Avisos:\n";
        foreach ($result['warnings'] as $warning) {
            echo "   - $warning\n";
        }
    }

    echo "\n📊 Dados extraídos:\n";
    echo json_encode($result['extracted_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
