<?php
/**
 * Validador de Documentos usando AWS Textract (OCR)
 * Funciona em hospedagem compartilhada (Hostinger)
 */

namespace Verify;

use Aws\Textract\TextractClient;
use Aws\Exception\AwsException;

class DocumentValidatorAWS {
    private $textractClient;
    private $confidenceThreshold;
    
    public function __construct() {
        // Carrega configurações do .env (tenta $_ENV primeiro, depois getenv)
        $key = $_ENV['AWS_ACCESS_KEY_ID'] ?? getenv('AWS_ACCESS_KEY_ID');
        $secret = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? getenv('AWS_SECRET_ACCESS_KEY');
        $region = $_ENV['AWS_REGION'] ?? getenv('AWS_REGION') ?: 'us-east-1';
        
        if (empty($key) || empty($secret)) {
            throw new \Exception('AWS credentials não configuradas no .env');
        }
        
        // Inicializa cliente Textract
        $this->textractClient = new TextractClient([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $key,
                'secret' => $secret
            ]
        ]);
        
        $this->confidenceThreshold = (int)($_ENV['OCR_CONFIDENCE_THRESHOLD'] ?? getenv('OCR_CONFIDENCE_THRESHOLD') ?: 70);
    }
    
    /**
     * Extrai texto de um documento usando AWS Textract
     */
    public function extractText($filePath) {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Arquivo não encontrado'
            ];
        }
        
        try {
            // Lê arquivo
            $imageBytes = file_get_contents($filePath);
            
            // Chama AWS Textract
            $result = $this->textractClient->detectDocumentText([
                'Document' => [
                    'Bytes' => $imageBytes
                ]
            ]);
            
            // Extrai texto e confiança
            $fullText = '';
            $confidenceSum = 0;
            $confidenceCount = 0;
            
            foreach ($result['Blocks'] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $fullText .= $block['Text'] . "\n";
                    
                    if (isset($block['Confidence'])) {
                        $confidenceSum += $block['Confidence'];
                        $confidenceCount++;
                    }
                }
            }
            
            $avgConfidence = $confidenceCount > 0 ? 
                round($confidenceSum / $confidenceCount) : 0;
            
            return [
                'success' => true,
                'text' => trim($fullText),
                'confidence' => $avgConfidence,
                'blocks_detected' => count($result['Blocks'])
            ];
            
        } catch (AwsException $e) {
            return [
                'success' => false,
                'error' => 'AWS Textract error: ' . $e->getAwsErrorMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao processar documento: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Extrai CPF do texto com validação
     */
    public function extractCPF($text) {
        // Padrões de CPF (ordem de prioridade)
        $patterns = [
            // Padrão com label "CPF"
            '/CPF[:\s]*(\d{3}\.?\d{3}\.?\d{3}[\/-]?\d{2})/i',
            '/Cadastro[:\s]*(\d{3}\.?\d{3}\.?\d{3}[\/-]?\d{2})/i',
            // Padrão formatado tradicional
            '/\b(\d{3}\.?\d{3}\.?\d{3}-?\d{2})\b/',
            // Padrão sem formatação (11 dígitos seguidos ou com barra)
            '/\b(\d{9}[\/-]?\d{2})\b/',
            '/\b(\d{11})\b/'
        ];
        
        $found_cpfs = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    // Remove tudo exceto dígitos
                    $cpf = preg_replace('/\D/', '', $match);
                    
                    // Verifica se tem 11 dígitos
                    if (strlen($cpf) === 11) {
                        // Valida o CPF
                        $is_valid = $this->validateCPF($cpf);
                        
                        // Ignora CPFs inválidos conhecidos (todos números iguais)
                        if (!preg_match('/^(\d)\1{10}$/', $cpf)) {
                            $found_cpfs[] = [
                                'raw' => $cpf,
                                'formatted' => $this->formatCPF($cpf),
                                'valid' => $is_valid,
                                'pattern' => $pattern  // Debug: qual padrão pegou
                            ];
                            
                            // Se encontrou um CPF VÁLIDO, retorna imediatamente
                            if ($is_valid) {
                                return [
                                    'raw' => $cpf,
                                    'formatted' => $this->formatCPF($cpf),
                                    'valid' => true
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Se não encontrou CPF válido, retorna o primeiro encontrado (mesmo que inválido)
        if (!empty($found_cpfs)) {
            $first = $found_cpfs[0];
            return [
                'raw' => $first['raw'],
                'formatted' => $first['formatted'],
                'valid' => $first['valid']
            ];
        }
        
        return null;
    }
    
    /**
     * Extrai CNPJ do texto com validação
     */
    public function extractCNPJ($text) {
        $patterns = [
            '/(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})/',
            '/CNPJ[:\s]*(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $cnpj = preg_replace('/[^0-9]/', '', $matches[1]);
                
                if (strlen($cnpj) === 14) {
                    return [
                        'raw' => $cnpj,
                        'formatted' => $this->formatCNPJ($cnpj),
                        'valid' => $this->validateCNPJ($cnpj)
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extrai RG do texto
     */
    public function extractRG($text) {
        $patterns = [
            '/(?:RG|Identidade)[:\s]*(\d{1,2}\.?\d{3}\.?\d{3}-?[0-9X])/i',
            '/(\d{1,2}\.?\d{3}\.?\d{3}-?[0-9X])/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $rg = $matches[1];
                return [
                    'raw' => preg_replace('/[^0-9X]/', '', $rg),
                    'formatted' => $rg
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Extrai CNH do texto
     */
    public function extractCNH($text) {
        if (preg_match('/(?:CNH|Habilitação)[:\s]*(\d{11})/i', $text, $matches)) {
            return [
                'raw' => $matches[1],
                'formatted' => $matches[1]
            ];
        }
        
        return null;
    }
    
    /**
     * Extrai nome do texto
     */
    public function extractName($text) {
        // Primeiro tenta encontrar o nome após o label "NOME"
        // Permite quebras de linha entre NOME e o nome real
        if (preg_match('/NOME[:\s]*[\r\n]+\s*([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+?)(?:[\r\n]|FILIAÇÃO|FILIACAO)/ius', $text, $matches)) {
            $candidate = trim($matches[1]);
            
            // Limpa o candidato (remove espaços extras)
            $candidate = preg_replace('/\s+/', ' ', $candidate);
            
            // Valida se tem entre 2-6 palavras e cada palavra tem pelo menos 3 caracteres
            $words = preg_split('/\s+/', $candidate);
            $word_count = count($words);
            
            if ($word_count >= 2 && $word_count <= 6) {
                $valid = true;
                foreach ($words as $word) {
                    if (strlen($word) < 3) {
                        $valid = false;
                        break;
                    }
                }
                
                if ($valid) {
                    return $candidate;
                }
            }
        }
        
        // Se não encontrou com label, usa método tradicional com filtros
        // Remove linhas que são claramente não-nome (lista expandida)
        $exclude_keywords = [
            'ASSINATURA', 'CARTEIRA', 'IDENTIDADE', 'REPÚBLICA', 'FEDERATIVA',
            'MINISTÉRIO', 'SECRETARIA', 'POLEGAR', 'DIREITO', 'ESQUERDO',
            'VALID', 'DOCUMENTO', 'RG', 'CPF', 'DATA', 'EMISSÃO', 'EXPEDIÇÃO',
            'REGISTRO', 'TERRITÓRIO', 'NACIONAL', 'ESTADO', 'BRASILEIRA',
            'BRASIL', 'ÓRGÃO', 'EMISSOR', 'VALIDADE', 'NATURALIDADE',
            'MUNICÍPIO', 'OBSERVAÇÕES', 'OBSERVACOES', 'DIGITAIS', 'DIGITAL',
            'INSTITUTO', 'IDENTIFICAÇÃO', 'IDENTIFICACAO', 'RICARDO', 'GUMBLETON', 
            'DAUNT', 'PÚBLIC', 'PUBLIC', 'SEGURANÇA', 'SEGURANCA', 'CIVIL',
            'SSP', 'DETRAN', 'POLÍCIA', 'POLICIA', 'FEDERAL', 'ESTADUAL'
        ];
        
        $lines = explode("\n", $text);
        $possible_names = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pula linhas vazias ou muito curtas
            if (strlen($line) < 10) {
                continue;
            }
            
            // Pula se contém palavras-chave que não são nome
            $skip = false;
            foreach ($exclude_keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            
            // Pula linhas que começam com números (podem ser CPF, RG, etc)
            if (preg_match('/^\d/', $line)) {
                continue;
            }
            
            // Pula linhas com muitas palavras (provavelmente texto do documento)
            $word_count = str_word_count($line);
            if ($word_count > 6) {
                continue;
            }
            
            // Verifica se é um nome válido (só letras e espaços, pelo menos 2 palavras)
            if (preg_match('/^[A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]{10,}$/i', $line)) {
                $words = explode(' ', $line);
                // Precisa ter entre 2 e 6 palavras
                if ($word_count >= 2 && $word_count <= 6) {
                    // Verifica se cada palavra tem tamanho razoável (não é só inicial)
                    $valid_words = 0;
                    foreach ($words as $word) {
                        if (strlen($word) >= 3) {  // Aumentado para 3 caracteres
                            $valid_words++;
                        }
                    }
                    // Se pelo menos 2 palavras válidas (3+ chars cada), é um candidato
                    if ($valid_words >= 2) {
                        $possible_names[] = $line;
                    }
                }
            }
        }
        
        // Retorna o primeiro nome encontrado
        if (!empty($possible_names)) {
            return trim($possible_names[0]);
        }
        
        // Fallback: padrões tradicionais
        $patterns = [
            '/(?:NOME|Nome Completo|Titular)[:\s]+([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]+)/i',
            '/^([A-ZÁÀÂÃÉÈÊÍÏÓÔÕÖÚÇÑ\s]{10,})$/m'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $name = trim($matches[1]);
                if (str_word_count($name) >= 2) {
                    return $name;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Valida CPF
     */
    private function validateCPF($cpf) {
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
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
     */
    private function validateCNPJ($cnpj) {
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        $b = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        
        for ($i = 0, $n = 0; $i < 12; $n += $cnpj[$i] * $b[++$i]);
        if ($cnpj[12] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
            return false;
        }
        
        for ($i = 0, $n = 0; $i <= 12; $n += $cnpj[$i] * $b[$i++]);
        if ($cnpj[13] != ((($n %= 11) < 2) ? 0 : 11 - $n)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Formata CPF
     */
    private function formatCPF($cpf) {
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }
    
    /**
     * Formata CNPJ
     */
    private function formatCNPJ($cnpj) {
        return substr($cnpj, 0, 2) . '.' . 
               substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . 
               substr($cnpj, 8, 4) . '-' . 
               substr($cnpj, 12, 2);
    }
}
