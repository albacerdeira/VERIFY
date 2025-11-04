<?php
/**
 * Script de teste para verificação facial
 * Testa o FaceValidator com imagens de exemplo
 */

require_once 'config.php';
require_once 'src/FaceValidator.php';

// Carrega variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Verificação Facial - AWS Rekognition</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-shield-check"></i> Teste de Verificação Facial</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Instruções:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Primeiro, busque um cliente existente no banco de dados</li>
                                <li>Capture uma nova selfie usando a câmera</li>
                                <li>O sistema comparará a nova selfie com a selfie original do cliente</li>
                                <li>Resultado mostrará a porcentagem de similaridade</li>
                            </ol>
                        </div>

                        <hr>

                        <h5 class="mb-3"><i class="bi bi-search"></i> Buscar Cliente</h5>
                        <form method="GET" class="mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="text" name="search" class="form-control" placeholder="Digite o nome, email ou CPF do cliente" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php
                        if (isset($_GET['search']) && !empty($_GET['search'])) {
                            $search = '%' . $_GET['search'] . '%';
                            $stmt = $pdo->prepare("
                                SELECT id, nome_completo, email, cpf, selfie_path, created_at
                                FROM kyc_clientes
                                WHERE (nome_completo LIKE ? OR email LIKE ? OR cpf LIKE ?)
                                AND selfie_path IS NOT NULL
                                AND selfie_path != ''
                                LIMIT 10
                            ");
                            $stmt->execute([$search, $search, $search]);
                            $clientes = $stmt->fetchAll();

                            if ($clientes) {
                                echo '<h5 class="mb-3">Clientes Encontrados:</h5>';
                                echo '<div class="list-group mb-4">';
                                foreach ($clientes as $cliente) {
                                    $selfie_exists = file_exists($cliente['selfie_path']);
                                    $badge = $selfie_exists ? 
                                        '<span class="badge bg-success">Selfie OK</span>' : 
                                        '<span class="badge bg-danger">Selfie não encontrada</span>';
                                    
                                    echo '<a href="?cliente_id=' . $cliente['id'] . '" class="list-group-item list-group-item-action">';
                                    echo '<div class="d-flex w-100 justify-content-between">';
                                    echo '<h6 class="mb-1">' . htmlspecialchars($cliente['nome_completo']) . '</h6>';
                                    echo $badge;
                                    echo '</div>';
                                    echo '<p class="mb-1"><small><i class="bi bi-envelope"></i> ' . htmlspecialchars($cliente['email']) . '</small></p>';
                                    if ($cliente['cpf']) {
                                        echo '<p class="mb-1"><small><i class="bi bi-person-badge"></i> CPF: ' . htmlspecialchars($cliente['cpf']) . '</small></p>';
                                    }
                                    echo '<small class="text-muted">Cadastrado em: ' . date('d/m/Y H:i', strtotime($cliente['created_at'])) . '</small>';
                                    echo '</a>';
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-warning">Nenhum cliente encontrado com selfie cadastrada.</div>';
                            }
                        }

                        if (isset($_GET['cliente_id'])) {
                            $cliente_id = (int) $_GET['cliente_id'];
                            $stmt = $pdo->prepare("SELECT * FROM kyc_clientes WHERE id = ?");
                            $stmt->execute([$cliente_id]);
                            $cliente = $stmt->fetch();

                            if ($cliente && !empty($cliente['selfie_path'])) {
                                ?>
                                <hr>
                                <div class="card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">Cliente Selecionado: <?= htmlspecialchars($cliente['nome_completo']) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Selfie Original:</h6>
                                                <?php
                                                if (file_exists($cliente['selfie_path'])) {
                                                    $path_web = '/' . ltrim($cliente['selfie_path'], '/');
                                                    echo '<img src="' . $path_web . '" alt="Selfie Original" class="img-fluid rounded border">';
                                                    echo '<p class="text-success mt-2"><i class="bi bi-check-circle"></i> Arquivo encontrado</p>';
                                                } else {
                                                    echo '<div class="alert alert-danger">Arquivo não encontrado no servidor</div>';
                                                }
                                                ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Capture Nova Selfie:</h6>
                                                <video id="camera" autoplay playsinline class="border rounded w-100 mb-2" style="transform: scaleX(-1);"></video>
                                                <canvas id="canvas" style="display:none;"></canvas>
                                                <img id="captured-photo" class="border rounded w-100 mb-2" style="display:none;">
                                                
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-primary" id="btn-start-camera" onclick="startCamera()">
                                                        <i class="bi bi-camera"></i> Iniciar Câmera
                                                    </button>
                                                    <button class="btn btn-success" id="btn-capture" onclick="capture()" style="display:none;">
                                                        <i class="bi bi-camera-fill"></i> Capturar Foto
                                                    </button>
                                                    <button class="btn btn-secondary" id="btn-reset" onclick="reset()" style="display:none;">
                                                        <i class="bi bi-arrow-clockwise"></i> Nova Tentativa
                                                    </button>
                                                    <button class="btn btn-warning" id="btn-verify" onclick="verifyFaces()" style="display:none;">
                                                        <i class="bi bi-shield-check"></i> Comparar Faces
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="result" class="mt-4" style="display:none;">
                                            <hr>
                                            <h5>Resultado da Verificação:</h5>
                                            <div id="result-content"></div>
                                        </div>
                                    </div>
                                </div>

                                <script>
                                let stream = null;
                                let capturedBlob = null;

                                function startCamera() {
                                    navigator.mediaDevices.getUserMedia({ 
                                        video: { 
                                            facingMode: 'user',
                                            width: { ideal: 1280 },
                                            height: { ideal: 720 }
                                        } 
                                    })
                                    .then(function(mediaStream) {
                                        stream = mediaStream;
                                        document.getElementById('camera').srcObject = stream;
                                        document.getElementById('btn-start-camera').style.display = 'none';
                                        document.getElementById('btn-capture').style.display = 'block';
                                    })
                                    .catch(function(err) {
                                        alert('Erro ao acessar câmera: ' + err.message);
                                    });
                                }

                                function capture() {
                                    const video = document.getElementById('camera');
                                    const canvas = document.getElementById('canvas');
                                    const photo = document.getElementById('captured-photo');
                                    
                                    canvas.width = video.videoWidth;
                                    canvas.height = video.videoHeight;
                                    
                                    const ctx = canvas.getContext('2d');
                                    ctx.scale(-1, 1);
                                    ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
                                    
                                    canvas.toBlob(function(blob) {
                                        capturedBlob = blob;
                                        photo.src = URL.createObjectURL(blob);
                                        photo.style.display = 'block';
                                        video.style.display = 'none';
                                        
                                        if (stream) {
                                            stream.getTracks().forEach(track => track.stop());
                                        }
                                        
                                        document.getElementById('btn-capture').style.display = 'none';
                                        document.getElementById('btn-reset').style.display = 'block';
                                        document.getElementById('btn-verify').style.display = 'block';
                                    }, 'image/jpeg', 0.9);
                                }

                                function reset() {
                                    document.getElementById('captured-photo').style.display = 'none';
                                    document.getElementById('camera').style.display = 'block';
                                    document.getElementById('btn-reset').style.display = 'none';
                                    document.getElementById('btn-verify').style.display = 'none';
                                    document.getElementById('result').style.display = 'none';
                                    startCamera();
                                }

                                function verifyFaces() {
                                    if (!capturedBlob) {
                                        alert('Capture uma foto primeiro!');
                                        return;
                                    }

                                    const formData = new FormData();
                                    formData.append('verification_selfie', capturedBlob, 'selfie.jpg');
                                    formData.append('cliente_id', <?= $cliente_id ?>);

                                    document.getElementById('btn-verify').disabled = true;
                                    document.getElementById('btn-verify').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verificando...';

                                    fetch('ajax_verify_face.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        const resultDiv = document.getElementById('result');
                                        const resultContent = document.getElementById('result-content');
                                        
                                        resultDiv.style.display = 'block';
                                        
                                        if (data.success) {
                                            resultContent.innerHTML = `
                                                <div class="alert alert-success">
                                                    <h5><i class="bi bi-check-circle"></i> Verificação Bem-Sucedida!</h5>
                                                    <p class="mb-1"><strong>Similaridade:</strong> ${data.similarity}%</p>
                                                    <p class="mb-0"><strong>Confiança:</strong> ${data.confidence}%</p>
                                                    <hr>
                                                    <p class="mb-0">${data.message}</p>
                                                </div>
                                            `;
                                        } else {
                                            resultContent.innerHTML = `
                                                <div class="alert alert-danger">
                                                    <h5><i class="bi bi-x-circle"></i> Verificação Falhou</h5>
                                                    <p class="mb-0">${data.message}</p>
                                                </div>
                                            `;
                                        }

                                        document.getElementById('btn-verify').disabled = false;
                                        document.getElementById('btn-verify').innerHTML = '<i class="bi bi-shield-check"></i> Comparar Faces';
                                    })
                                    .catch(error => {
                                        alert('Erro na requisição: ' + error.message);
                                        document.getElementById('btn-verify').disabled = false;
                                        document.getElementById('btn-verify').innerHTML = '<i class="bi bi-shield-check"></i> Comparar Faces';
                                    });
                                }
                                </script>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Informações do Sistema</h5>
                    </div>
                    <div class="card-body">
                        <h6>Configuração AWS:</h6>
                        <ul>
                            <li><strong>AWS Region:</strong> <?= getenv('AWS_REGION') ?: 'Não configurado' ?></li>
                            <li><strong>AWS Access Key:</strong> <?= getenv('AWS_ACCESS_KEY_ID') ? substr(getenv('AWS_ACCESS_KEY_ID'), 0, 10) . '...' : 'Não configurado' ?></li>
                            <li><strong>Rekognition Collection:</strong> <?= getenv('AWS_REKOGNITION_COLLECTION') ?: 'verify-kyc-faces (padrão)' ?></li>
                            <li><strong>Match Threshold:</strong> <?= getenv('FACE_MATCH_THRESHOLD') ?: '90%' ?></li>
                        </ul>

                        <h6 class="mt-3">Limites AWS Free Tier:</h6>
                        <ul>
                            <li><strong>Rekognition Face Detection:</strong> 5.000 imagens/mês (12 meses)</li>
                            <li><strong>Rekognition Face Comparison:</strong> 1.000 comparações/mês (12 meses)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
