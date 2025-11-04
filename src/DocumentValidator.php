<?php
namespace Verify;

use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Classe para validação e extração de dados de documentos usando Tesseract OCR
 */
class DocumentValidator
{
    private $tesseractPath;
    private $lang;
    private $confidenceThreshold;

    public function __construct()
    {
        // Carrega configurações do .env ou usa padrões
        $this->tesseractPath = getenv('TESSERACT_PATH') ?: '/usr/bin/tesseract';
        $this->lang = getenv('TESSERACT_LANG') ?: 'por';
        $this->confidenceThreshold = (int)(getenv('OCR_CONFIDENCE_THRESHOLD') ?: 70);
    }

    /**
     * Extrai texto de uma imagem ou PDF
     * 
     * @param string $filePath Caminho do arquivo
     * @return array ['success' => bool, 'text' => string, 'confidence' => int, 'error' => string]
     */
    public function extractText($filePath)
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'text' => '',
                    'confidence' => 0,
                    'error' => 'Arquivo não encontrado'
                ];
            }

            // Converte PDF para imagem se necessário
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf') {
                $filePath = $this->convertPdfToImage($filePath);
                if (!$filePath) {
                    return [
                        'success' => false,
                        'text' => '',
                        'confidence' => 0,
                        'error' => 'Erro ao converter PDF'
                    ];
                }
            }

            $ocr = new TesseractOCR($filePath);
            $ocr->executable($this->tesseractPath);
            $ocr->lang($this->lang);
            
            // Opções para melhorar leitura de documentos
            $ocr->psm(6); // Assume um único bloco uniforme de texto
            $ocr->oem(3); // Modo LSTM + legado
            
            $text = $ocr->run();
            
            // Calcula confiança aproximada (Tesseract não retorna isso facilmente)
            $confidence = $this->estimateConfidence($text);

            return [
                'success' => true,
                'text' => $text,
                'confidence' => $confidence,
                'error' => ''
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extrai CPF do texto usando regex
     * 
     * @param string $text Texto extraído
     * @return array|null CPF encontrado ou null
     */
    public function extractCPF($text)
    {
        // Padrões de CPF: 123.456.789-00 ou 12345678900
        $patterns = [
            '/\d{3}\.\d{3}\.\d{3}-\d{2}/',
            '/(?<!\d)\d{11}(?!\d)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $cpf = preg_replace('/\D/', '', $matches[0]);
                if ($this->validateCPF($cpf)) {
                    return [
                        'cpf' => $cpf,
                        'formatted' => $this->formatCPF($cpf)
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extrai CNPJ do texto
     * 
     * @param string $text Texto extraído
     * @return array|null CNPJ encontrado ou null
     */
    public function extractCNPJ($text)
    {
        // Padrões de CNPJ: 12.345.678/0001-00 ou 12345678000100
        $patterns = [
            '/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/',
            '/(?<!\d)\d{14}(?!\d)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $cnpj = preg_replace('/\D/', '', $matches[0]);
                if ($this->validateCNPJ($cnpj)) {
                    return [
                        'cnpj' => $cnpj,
                        'formatted' => $this->formatCNPJ($cnpj)
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extrai RG do texto
     * 
     * @param string $text Texto extraído
     * @return array|null RG encontrado ou null
     */
    public function extractRG($text)
    {
        // Padrões de RG (varia por estado)
        $patterns = [
            '/RG[:\s]+(\d{1,2}\.?\d{3}\.?\d{3}-?[0-9X])/i',
            '/(?<!\d)(\d{1,2}\.?\d{3}\.?\d{3}-?[0-9X])(?!\d)/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return [
                    'rg' => preg_replace('/\D/', '', $matches[1]),
                    'formatted' => $matches[1]
                ];
            }
        }

        return null;
    }

    /**
     * Extrai CNH do texto
     * 
     * @param string $text Texto extraído
     * @return array|null CNH encontrada ou null
     */
    public function extractCNH($text)
    {
        // CNH tem 11 dígitos
        if (preg_match('/(?<!\d)\d{11}(?!\d)/', $text, $matches)) {
            return [
                'cnh' => $matches[0],
                'formatted' => $matches[0]
            ];
        }

        return null;
    }

    /**
     * Extrai nome do texto (procura por linha com nome após "NOME:" ou similar)
     * 
     * @param string $text Texto extraído
     * @return string|null Nome encontrado ou null
     */
    public function extractName($text)
    {
        $patterns = [
            '/NOME[:\s]+([A-ZÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜ\s]+)/i',
            '/TITULAR[:\s]+([A-ZÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜ\s]+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $nome = trim($matches[1]);
                // Remove números e caracteres especiais
                $nome = preg_replace('/[0-9]/', '', $nome);
                if (strlen($nome) > 5) { // Nome mínimo razoável
                    return ucwords(strtolower($nome));
                }
            }
        }

        return null;
    }

    /**
     * Converte PDF para imagem usando Imagick
     * 
     * @param string $pdfPath Caminho do PDF
     * @return string|false Caminho da imagem ou false em caso de erro
     */
    private function convertPdfToImage($pdfPath)
    {
        if (!extension_loaded('imagick')) {
            error_log('Imagick não está instalado. Não é possível converter PDF.');
            return false;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdfPath . '[0]'); // Lê primeira página
            $imagick->setImageFormat('jpg');
            
            $tempImage = sys_get_temp_dir() . '/' . uniqid('pdf_') . '.jpg';
            $imagick->writeImage($tempImage);
            $imagick->clear();

            return $tempImage;

        } catch (\Exception $e) {
            error_log('Erro ao converter PDF: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Estima confiança da leitura OCR baseado na qualidade do texto
     * 
     * @param string $text Texto extraído
     * @return int Confiança de 0 a 100
     */
    private function estimateConfidence($text)
    {
        if (empty($text)) return 0;

        $confidence = 100;

        // Reduz confiança se houver muitos caracteres estranhos
        $strangeChars = preg_match_all('/[^a-zA-Z0-9\sçÇáàâãéèêíïóôõöúçñÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\.\-\/\(\)]/', $text);
        $confidence -= min(30, $strangeChars * 2);

        // Reduz confiança se o texto for muito curto
        if (strlen($text) < 50) {
            $confidence -= 20;
        }

        // Aumenta confiança se encontrar padrões brasileiros
        if (preg_match('/\d{3}\.\d{3}\.\d{3}-\d{2}/', $text)) $confidence += 10; // CPF
        if (preg_match('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $text)) $confidence += 10; // CNPJ

        return max(0, min(100, $confidence));
    }

    /**
     * Valida CPF
     * 
     * @param string $cpf CPF sem formatação
     * @return bool
     */
    private function validateCPF($cpf)
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        
        if (strlen($cpf) != 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Valida CNPJ
     * 
     * @param string $cnpj CNPJ sem formatação
     * @return bool
     */
    private function validateCNPJ($cnpj)
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        if (strlen($cnpj) != 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $tamanho = strlen($cnpj) - 2;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ($resultado != $digitos[0]) return false;

        $tamanho = $tamanho + 1;
        $numeros = substr($cnpj, 0, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        return $resultado == $digitos[1];
    }

    /**
     * Formata CPF
     */
    private function formatCPF($cpf)
    {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    /**
     * Formata CNPJ
     */
    private function formatCNPJ($cnpj)
    {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
}
