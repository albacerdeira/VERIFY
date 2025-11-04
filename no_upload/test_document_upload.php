<?php
session_start();

// Para testes, simula autenticação
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['cliente_id'] = 1;
}

require_once 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card mt-4">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-file-check"></i> Teste de Validação de Documentos (OCR)</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Teste o sistema OCR:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Faça upload de um documento (RG, CNH, CPF, Comprovante)</li>
                            <li>O sistema irá extrair automaticamente: CPF, CNPJ, RG, CNH e Nomes</li>
                            <li>Aceita: JPG, PNG, PDF (até 10MB)</li>
                        </ul>
                    </div>

                    <!-- Área de Upload -->
                    <div class="upload-area" id="uploadArea">
                        <div class="text-center p-5">
                            <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                            <h5>Arraste um documento aqui</h5>
                            <p class="text-muted">ou clique para selecionar</p>
                            <input type="file" id="fileInput" accept="image/jpeg,image/png,application/pdf" style="display:none;">
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-folder-open"></i> Selecionar Arquivo
                            </button>
                        </div>
                    </div>

                    <!-- Preview do arquivo -->
                    <div id="previewArea" class="mt-3" style="display:none;">
                        <h5>Arquivo selecionado:</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-alt fa-3x text-primary mr-3"></i>
                                    <div class="flex-grow-1">
                                        <strong id="fileName"></strong>
                                        <br>
                                        <small class="text-muted" id="fileSize"></small>
                                    </div>
                                    <button type="button" class="btn btn-success" onclick="processDocument()">
                                        <i class="fas fa-cog"></i> Processar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div id="loadingArea" class="text-center mt-4" style="display:none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Processando...</span>
                        </div>
                        <p class="mt-2">Extraindo dados do documento...</p>
                    </div>

                    <!-- Resultado -->
                    <div id="resultArea" class="mt-4" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.upload-area {
    border: 3px dashed #ccc;
    border-radius: 10px;
    transition: all 0.3s;
    cursor: pointer;
}

.upload-area:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.upload-area.drag-over {
    border-color: #28a745;
    background-color: #d4edda;
}

.extracted-data {
    background-color: #f8f9fa;
    border-left: 4px solid #28a745;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
}

.confidence-badge {
    font-size: 1.1rem;
    padding: 8px 15px;
}
</style>

<script>
let selectedFile = null;

// Upload por clique
document.getElementById('fileInput').addEventListener('change', function(e) {
    if (e.target.files.length > 0) {
        handleFileSelect(e.target.files[0]);
    }
});

// Drag and Drop
const uploadArea = document.getElementById('uploadArea');

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.classList.add('drag-over');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    
    if (e.dataTransfer.files.length > 0) {
        handleFileSelect(e.dataTransfer.files[0]);
    }
});

uploadArea.addEventListener('click', function() {
    document.getElementById('fileInput').click();
});

function handleFileSelect(file) {
    // Valida tipo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        alert('Tipo de arquivo não permitido. Use JPG, PNG ou PDF');
        return;
    }

    // Valida tamanho (10MB)
    if (file.size > 10 * 1024 * 1024) {
        alert('Arquivo muito grande. Máximo 10MB');
        return;
    }

    selectedFile = file;

    // Mostra preview
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('previewArea').style.display = 'block';
    document.getElementById('resultArea').style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function processDocument() {
    if (!selectedFile) {
        alert('Nenhum arquivo selecionado');
        return;
    }

    // Mostra loading
    document.getElementById('loadingArea').style.display = 'block';
    document.getElementById('resultArea').style.display = 'none';

    // Prepara FormData
    const formData = new FormData();
    formData.append('documento', selectedFile);
    formData.append('save_file', 'false'); // Não salvar durante testes

    // Envia via AJAX
    fetch('ajax_validate_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingArea').style.display = 'none';
        displayResult(data);
    })
    .catch(error => {
        document.getElementById('loadingArea').style.display = 'none';
        alert('Erro ao processar documento: ' + error);
    });
}

function displayResult(data) {
    const resultArea = document.getElementById('resultArea');
    resultArea.style.display = 'block';

    if (!data.success) {
        resultArea.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Erro:</strong> ${data.error}
            </div>
        `;
        return;
    }

    // Score de confiança
    let confidenceBadge = '';
    let confidenceClass = 'success';
    if (data.confidence >= 70) {
        confidenceClass = 'success';
    } else if (data.confidence >= 50) {
        confidenceClass = 'warning';
    } else {
        confidenceClass = 'danger';
    }

    let html = `
        <div class="card border-${confidenceClass}">
            <div class="card-header bg-${confidenceClass} text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle"></i> Documento Processado
                    <span class="badge badge-light confidence-badge float-right">
                        Confiança: ${data.confidence}%
                    </span>
                </h5>
            </div>
            <div class="card-body">
    `;

    // Avisos
    if (data.warnings && data.warnings.length > 0) {
        html += '<div class="alert alert-warning">';
        html += '<i class="fas fa-exclamation-triangle"></i> <strong>Avisos:</strong><ul class="mb-0 mt-2">';
        data.warnings.forEach(warning => {
            html += `<li>${warning}</li>`;
        });
        html += '</ul></div>';
    }

    // Dados extraídos
    if (data.extracted_data && Object.keys(data.extracted_data).length > 0) {
        html += '<h5><i class="fas fa-database"></i> Dados Extraídos:</h5>';

        for (const [key, value] of Object.entries(data.extracted_data)) {
            html += '<div class="extracted-data">';
            
            if (key === 'cpf') {
                html += `
                    <strong><i class="fas fa-id-card"></i> CPF:</strong> ${value.value}
                    ${value.valid ? '<span class="badge badge-success">Válido</span>' : '<span class="badge badge-danger">Inválido</span>'}
                `;
            } else if (key === 'cnpj') {
                html += `
                    <strong><i class="fas fa-building"></i> CNPJ:</strong> ${value.value}
                    ${value.valid ? '<span class="badge badge-success">Válido</span>' : '<span class="badge badge-danger">Inválido</span>'}
                `;
            } else if (key === 'rg') {
                html += `<strong><i class="fas fa-id-card-alt"></i> RG:</strong> ${value.value}`;
            } else if (key === 'cnh') {
                html += `<strong><i class="fas fa-car"></i> CNH:</strong> ${value.value}`;
            } else if (key === 'nome') {
                html += `<strong><i class="fas fa-user"></i> Nome:</strong> ${value}`;
            }
            
            html += '</div>';
        }
    } else {
        html += '<div class="alert alert-info">Nenhum dado específico foi identificado automaticamente.</div>';
    }

    // Prévia do texto
    if (data.text_preview) {
        html += `
            <div class="mt-3">
                <h6><i class="fas fa-align-left"></i> Prévia do Texto Extraído:</h6>
                <pre class="bg-light p-3" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">${data.text_preview}</pre>
            </div>
        `;
    }

    html += `
            </div>
        </div>
        <div class="text-center mt-3">
            <button type="button" class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-redo"></i> Testar Outro Documento
            </button>
        </div>
    `;

    resultArea.innerHTML = html;
}
</script>

<?php require_once 'footer.php'; ?>
