<?php
namespace Verify;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;

/**
 * Classe para validação de selfies e comparação facial usando AWS Rekognition
 */
class FaceValidator
{
    private $client;
    private $collectionId;
    private $matchThreshold;

    public function __construct()
    {
        // Carrega configurações
        $this->matchThreshold = (int)(getenv('FACE_MATCH_THRESHOLD') ?: 90);
        $this->collectionId = getenv('AWS_REKOGNITION_COLLECTION') ?: 'verify-kyc-faces';

        // Inicializa cliente AWS Rekognition
        $this->client = new RekognitionClient([
            'version' => 'latest',
            'region' => getenv('AWS_REGION') ?: 'us-east-1',
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);

        // Cria collection se não existir
        $this->ensureCollectionExists();
    }

    /**
     * Detecta face em uma imagem
     * 
     * @param string $imagePath Caminho da imagem
     * @return array ['success' => bool, 'faces' => array, 'quality' => array, 'error' => string]
     */
    public function detectFace($imagePath)
    {
        try {
            if (!file_exists($imagePath)) {
                return [
                    'success' => false,
                    'faces' => [],
                    'quality' => [],
                    'error' => 'Arquivo não encontrado'
                ];
            }

            $imageData = file_get_contents($imagePath);

            $result = $this->client->detectFaces([
                'Image' => [
                    'Bytes' => $imageData,
                ],
                'Attributes' => ['ALL']
            ]);

            $faces = $result->get('FaceDetails');

            if (empty($faces)) {
                return [
                    'success' => false,
                    'faces' => [],
                    'quality' => [],
                    'error' => 'Nenhuma face detectada na imagem'
                ];
            }

            // Analisa qualidade da primeira face
            $face = $faces[0];
            $quality = $this->analyzeFaceQuality($face);

            return [
                'success' => true,
                'faces' => $faces,
                'face_count' => count($faces),
                'quality' => $quality,
                'error' => ''
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'faces' => [],
                'quality' => [],
                'error' => 'AWS Error: ' . $e->getAwsErrorMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'faces' => [],
                'quality' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Compara duas faces (selfie vs documento)
     * 
     * @param string $selfieePath Caminho da selfie
     * @param string $documentPath Caminho do documento com foto
     * @return array ['success' => bool, 'match' => bool, 'similarity' => float, 'confidence' => float, 'error' => string]
     */
    public function compareFaces($selfiePath, $documentPath)
    {
        try {
            if (!file_exists($selfiePath) || !file_exists($documentPath)) {
                return [
                    'success' => false,
                    'match' => false,
                    'similarity' => 0,
                    'confidence' => 0,
                    'error' => 'Um ou ambos os arquivos não foram encontrados'
                ];
            }

            $selfieData = file_get_contents($selfiePath);
            $documentData = file_get_contents($documentPath);

            $result = $this->client->compareFaces([
                'SourceImage' => [
                    'Bytes' => $selfieData,
                ],
                'TargetImage' => [
                    'Bytes' => $documentData,
                ],
                'SimilarityThreshold' => $this->matchThreshold
            ]);

            $faceMatches = $result->get('FaceMatches');

            if (empty($faceMatches)) {
                return [
                    'success' => true,
                    'match' => false,
                    'similarity' => 0,
                    'confidence' => 0,
                    'error' => 'Faces não correspondem ou nenhuma face detectada',
                    'unmatched_faces' => count($result->get('UnmatchedFaces'))
                ];
            }

            $match = $faceMatches[0];
            $similarity = $match['Similarity'];
            $confidence = $match['Face']['Confidence'];

            return [
                'success' => true,
                'match' => $similarity >= $this->matchThreshold,
                'similarity' => round($similarity, 2),
                'confidence' => round($confidence, 2),
                'error' => '',
                'face_details' => $match['Face']
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'match' => false,
                'similarity' => 0,
                'confidence' => 0,
                'error' => 'AWS Error: ' . $e->getAwsErrorMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'match' => false,
                'similarity' => 0,
                'confidence' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Indexa uma face na collection (para busca futura)
     * 
     * @param string $imagePath Caminho da imagem
     * @param string $externalId ID externo (ex: cliente_id)
     * @return array ['success' => bool, 'face_id' => string, 'error' => string]
     */
    public function indexFace($imagePath, $externalId)
    {
        try {
            $imageData = file_get_contents($imagePath);

            $result = $this->client->indexFaces([
                'CollectionId' => $this->collectionId,
                'Image' => [
                    'Bytes' => $imageData,
                ],
                'ExternalImageId' => $externalId,
                'DetectionAttributes' => ['ALL'],
                'MaxFaces' => 1,
                'QualityFilter' => 'AUTO'
            ]);

            $faceRecords = $result->get('FaceRecords');

            if (empty($faceRecords)) {
                return [
                    'success' => false,
                    'face_id' => null,
                    'error' => 'Nenhuma face detectada ou qualidade insuficiente'
                ];
            }

            $faceId = $faceRecords[0]['Face']['FaceId'];

            return [
                'success' => true,
                'face_id' => $faceId,
                'external_id' => $externalId,
                'error' => ''
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'face_id' => null,
                'error' => 'AWS Error: ' . $e->getAwsErrorMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'face_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Busca faces similares na collection
     * 
     * @param string $imagePath Caminho da imagem
     * @return array ['success' => bool, 'matches' => array, 'error' => string]
     */
    public function searchFacesByImage($imagePath)
    {
        try {
            $imageData = file_get_contents($imagePath);

            $result = $this->client->searchFacesByImage([
                'CollectionId' => $this->collectionId,
                'Image' => [
                    'Bytes' => $imageData,
                ],
                'MaxFaces' => 5,
                'FaceMatchThreshold' => $this->matchThreshold
            ]);

            $faceMatches = $result->get('FaceMatches');

            return [
                'success' => true,
                'matches' => $faceMatches,
                'match_count' => count($faceMatches),
                'error' => ''
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'matches' => [],
                'error' => 'AWS Error: ' . $e->getAwsErrorMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'matches' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analisa qualidade da face detectada
     * 
     * @param array $faceDetails Detalhes da face do AWS
     * @return array Análise de qualidade
     */
    private function analyzeFaceQuality($faceDetails)
    {
        $quality = [
            'brightness' => $faceDetails['Quality']['Brightness'] ?? 0,
            'sharpness' => $faceDetails['Quality']['Sharpness'] ?? 0,
            'confidence' => $faceDetails['Confidence'] ?? 0,
            'eyes_open' => $faceDetails['EyesOpen']['Value'] ?? false,
            'eyes_open_confidence' => $faceDetails['EyesOpen']['Confidence'] ?? 0,
            'mouth_open' => $faceDetails['MouthOpen']['Value'] ?? false,
            'sunglasses' => $faceDetails['Sunglasses']['Value'] ?? false,
            'eyeglasses' => $faceDetails['Eyeglasses']['Value'] ?? false,
            'smile' => $faceDetails['Smile']['Value'] ?? false,
            'age_range' => $faceDetails['AgeRange'] ?? null,
            'gender' => $faceDetails['Gender']['Value'] ?? 'Unknown',
            'emotions' => $faceDetails['Emotions'] ?? []
        ];

        // Calcula score de qualidade geral (0-100)
        $score = 0;
        $score += min(25, $quality['brightness'] / 4);
        $score += min(25, $quality['sharpness'] / 4);
        $score += min(25, $quality['confidence'] / 4);
        $score += $quality['eyes_open'] ? 25 : 0;

        $quality['overall_score'] = round($score);
        $quality['is_good_quality'] = $score >= 70;

        // Avisos
        $quality['warnings'] = [];
        if ($quality['sunglasses']) $quality['warnings'][] = 'Usando óculos de sol';
        if (!$quality['eyes_open']) $quality['warnings'][] = 'Olhos fechados';
        if ($quality['brightness'] < 30) $quality['warnings'][] = 'Imagem muito escura';
        if ($quality['sharpness'] < 30) $quality['warnings'][] = 'Imagem desfocada';

        return $quality;
    }

    /**
     * Garante que a collection existe, cria se necessário
     */
    private function ensureCollectionExists()
    {
        try {
            $this->client->describeCollection([
                'CollectionId' => $this->collectionId
            ]);
        } catch (AwsException $e) {
            // Collection não existe, vamos criar
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                try {
                    $this->client->createCollection([
                        'CollectionId' => $this->collectionId
                    ]);
                    error_log("Collection '{$this->collectionId}' criada com sucesso");
                } catch (AwsException $createError) {
                    error_log("Erro ao criar collection: " . $createError->getAwsErrorMessage());
                }
            }
        }
    }

    /**
     * Remove uma face da collection
     * 
     * @param string $faceId ID da face
     * @return bool
     */
    public function deleteFace($faceId)
    {
        try {
            $this->client->deleteFaces([
                'CollectionId' => $this->collectionId,
                'FaceIds' => [$faceId]
            ]);
            return true;
        } catch (AwsException $e) {
            error_log("Erro ao deletar face: " . $e->getAwsErrorMessage());
            return false;
        }
    }
}
