<?php
/**
 * Formulário de Dados Pessoais Completos do Cliente
 * Coleta: foto do documento, filiação, data nascimento, endereço
 */

session_start();
require_once 'config.php';

// Verificação de autenticação
if (!isset($_SESSION['cliente_id'])) {
    header('Location: cliente_login.php');
    exit;
}

$cliente_id = $_SESSION['cliente_id'];
$nome_cliente = $_SESSION['cliente_nome'] ?? 'Cliente';
$error = '';
$success = '';

// Carrega dados do cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM kyc_clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        throw new Exception("Cliente não encontrado");
    }
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// Lógica Whitelabel (mesma do dashboard)
$nome_empresa_padrao = 'Verify KYC';
$cor_variavel_padrao = '#4f46e5'; 
$logo_url_padrao = 'imagens/verify-kyc.png';

$nome_empresa = $nome_empresa_padrao;
$cor_variavel = $cor_variavel_padrao;
$logo_url = $logo_url_padrao;

if (isset($pdo)) {
    try {
        // Busca configuração whitelabel associada ao cliente
        $stmt_parceiro = $pdo->prepare(
            'SELECT cw.nome_empresa, cw.cor_variavel, cw.logo_url '
            . 'FROM kyc_clientes kc ' 
            . 'JOIN configuracoes_whitelabel cw ON kc.id_empresa_master = cw.empresa_id ' 
            . 'WHERE kc.id = ?'
        );
        $stmt_parceiro->execute([$cliente_id]);
        $parceiro_associado = $stmt_parceiro->fetch(PDO::FETCH_ASSOC);

        if ($parceiro_associado) {
            $nome_empresa = $parceiro_associado['nome_empresa'];
            $cor_variavel = $parceiro_associado['cor_variavel'];
            $logo_url = $parceiro_associado['logo_url'];
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar whitelabel: " . $e->getMessage());
    }
}

// Cache-busting para o logo
$logo_path_servidor = ltrim(htmlspecialchars($logo_url), '/');
$logo_cache_buster = file_exists($logo_path_servidor) ? '?v=' . filemtime($logo_path_servidor) : '';
$logo_url_final = htmlspecialchars($logo_url) . $logo_cache_buster;

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Coleta dados do formulário
        $rg = trim($_POST['rg'] ?? '');
        $data_nascimento = trim($_POST['data_nascimento'] ?? '');
        $nome_pai = trim($_POST['nome_pai'] ?? '');
        $nome_mae = trim($_POST['nome_mae'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $endereco_cep = trim($_POST['endereco_cep'] ?? '');
        $endereco_rua = trim($_POST['endereco_rua'] ?? '');
        $endereco_numero = trim($_POST['endereco_numero'] ?? '');
        $endereco_complemento = trim($_POST['endereco_complemento'] ?? '');
        $endereco_bairro = trim($_POST['endereco_bairro'] ?? '');
        $endereco_cidade = trim($_POST['endereco_cidade'] ?? '');
        $endereco_estado = trim($_POST['endereco_estado'] ?? '');
        
        // Validações básicas
        if (empty($nome_mae)) {
            throw new Exception("Nome da mãe é obrigatório");
        }
        
        if (empty($data_nascimento)) {
            throw new Exception("Data de nascimento é obrigatória");
        }
        
        if (empty($endereco_cep)) {
            throw new Exception("CEP é obrigatório");
        }
        
        // Processa upload da foto do documento (se enviada)
        $documento_foto_path = $cliente['documento_foto_path']; // Mantém o atual
        
        if (isset($_FILES['documento_foto']) && $_FILES['documento_foto']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/documentos_clientes';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Valida tipo de arquivo
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['documento_foto']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Tipo de arquivo não permitido. Use JPG, PNG ou PDF.");
            }
            
            // Valida tamanho (10MB)
            if ($_FILES['documento_foto']['size'] > 10 * 1024 * 1024) {
                throw new Exception("Arquivo muito grande. Tamanho máximo: 10MB");
            }
            
            // Gera nome único
            $extensao = pathinfo($_FILES['documento_foto']['name'], PATHINFO_EXTENSION);
            $nome_arquivo = 'doc_cliente_' . $cliente_id . '_' . uniqid() . '.' . $extensao;
            $documento_foto_path = $upload_dir . '/' . $nome_arquivo;
            
            // Move arquivo
            if (!move_uploaded_file($_FILES['documento_foto']['tmp_name'], $documento_foto_path)) {
                throw new Exception("Erro ao salvar foto do documento");
            }
            
            // Remove arquivo antigo se existir
            if (!empty($cliente['documento_foto_path']) && file_exists($cliente['documento_foto_path'])) {
                @unlink($cliente['documento_foto_path']);
            }
        }
        
        // Atualiza dados no banco
        $stmt = $pdo->prepare("
            UPDATE kyc_clientes SET
                rg = ?,
                data_nascimento = ?,
                nome_pai = ?,
                nome_mae = ?,
                telefone = ?,
                endereco_cep = ?,
                endereco_rua = ?,
                endereco_numero = ?,
                endereco_complemento = ?,
                endereco_bairro = ?,
                endereco_cidade = ?,
                endereco_estado = ?,
                documento_foto_path = ?,
                dados_completos_preenchidos = TRUE,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $rg,
            $data_nascimento,
            $nome_pai,
            $nome_mae,
            $telefone,
            $endereco_cep,
            $endereco_rua,
            $endereco_numero,
            $endereco_complemento,
            $endereco_bairro,
            $endereco_cidade,
            $endereco_estado,
            $documento_foto_path,
            $cliente_id
        ]);
        
        $success = "Dados pessoais salvos com sucesso!";
        
        // Recarrega dados atualizados
        $stmt = $pdo->prepare("SELECT * FROM kyc_clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Redireciona para o dashboard após salvar
        $_SESSION['flash_message'] = $success;
        header('Location: cliente_dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = 'Meus Dados Pessoais';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= htmlspecialchars($nome_empresa) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($cor_variavel) ?>;
        }
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f4f7f9; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        .main-header { 
            background-color: #fff; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            padding: 1rem 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .header-logo img { 
            max-height: 40px; 
            object-fit: contain; 
        }
        .header-user-menu { 
            display: flex; 
            align-items: center; 
        }
        .header-user-menu span { 
            margin-right: 1.5rem; 
            color: #333; 
            font-weight: 500; 
        }
        .logout-link { 
            color: var(--primary-color); 
            text-decoration: none; 
            font-weight: 500; 
            padding: 0.5rem 1rem; 
            border: 1px solid var(--primary-color); 
            border-radius: 5px; 
            transition: all 0.3s ease; 
        }
        .logout-link:hover { 
            background-color: var(--primary-color); 
            color: #fff; 
        }
        .container { 
            flex-grow: 1; 
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="header-logo">
        <img src="<?= $logo_url_final ?>" alt="Logo de <?= htmlspecialchars($nome_empresa) ?>">
    </div>
    <div class="header-user-menu">
        <span>Olá, <strong><?= htmlspecialchars(explode(' ', $nome_cliente)[0]) ?></strong>!</span>
        <a href="cliente_logout.php" class="logout-link">Sair</a>
    </div>
</header>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm mt-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-person-vcard"></i> Meus Dados Pessoais Completos</h2>
        <a href="cliente_dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($cliente['dados_completos_preenchidos']): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Seus dados pessoais já foram preenchidos. Você pode atualizá-los abaixo se necessário.
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <!-- Dados Pessoais -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Dados Pessoais</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nome_completo" class="form-label">Nome Completo <span class="text-muted">(não editável)</span></label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($cliente['nome_completo']) ?>" disabled>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cpf" class="form-label">CPF <span class="text-muted">(não editável)</span></label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($cliente['cpf'] ?? '') ?>" disabled>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="rg" class="form-label">RG</label>
                        <input type="text" class="form-control" id="rg" name="rg" value="<?= htmlspecialchars($cliente['rg'] ?? '') ?>" placeholder="Ex: 12.345.678-9">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="data_nascimento" class="form-label">Data de Nascimento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($cliente['data_nascimento'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>" placeholder="(11) 98765-4321">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nome_mae" class="form-label">Nome Completo da Mãe <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome_mae" name="nome_mae" value="<?= htmlspecialchars($cliente['nome_mae'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nome_pai" class="form-label">Nome Completo do Pai</label>
                        <input type="text" class="form-control" id="nome_pai" name="nome_pai" value="<?= htmlspecialchars($cliente['nome_pai'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="documento_foto" class="form-label">
                        <i class="bi bi-file-earmark-image"></i> Foto do Documento (RG ou CNH)
                        <?php if (!empty($cliente['documento_foto_path'])): ?>
                            <span class="badge bg-success">Já enviado</span>
                        <?php endif; ?>
                    </label>
                    <input type="file" class="form-control" id="documento_foto" name="documento_foto" accept="image/jpeg,image/jpg,image/png,application/pdf">
                    <small class="text-muted">Formatos aceitos: JPG, PNG, PDF (máx. 10MB)</small>
                    
                    <?php if (!empty($cliente['documento_foto_path'])): ?>
                        <div class="mt-2">
                            <a href="<?= htmlspecialchars($cliente['documento_foto_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Ver documento atual
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Endereço Residencial</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="endereco_cep" class="form-label">CEP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="endereco_cep" name="endereco_cep" value="<?= htmlspecialchars($cliente['endereco_cep'] ?? '') ?>" placeholder="12345-678" required onblur="buscarCEP()">
                    </div>
                    <div class="col-md-7 mb-3">
                        <label for="endereco_rua" class="form-label">Logradouro (Rua, Avenida, etc)</label>
                        <input type="text" class="form-control" id="endereco_rua" name="endereco_rua" value="<?= htmlspecialchars($cliente['endereco_rua'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="endereco_numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="endereco_numero" name="endereco_numero" value="<?= htmlspecialchars($cliente['endereco_numero'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="endereco_complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="endereco_complemento" name="endereco_complemento" value="<?= htmlspecialchars($cliente['endereco_complemento'] ?? '') ?>" placeholder="Apto, Bloco, etc">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="endereco_bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="endereco_bairro" name="endereco_bairro" value="<?= htmlspecialchars($cliente['endereco_bairro'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="endereco_cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" id="endereco_cidade" name="endereco_cidade" value="<?= htmlspecialchars($cliente['endereco_cidade'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="endereco_estado" class="form-label">Estado (UF)</label>
                        <select class="form-select" id="endereco_estado" name="endereco_estado">
                            <option value="">Selecione...</option>
                            <?php
                            $estados = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
                            foreach ($estados as $uf) {
                                $selected = ($cliente['endereco_estado'] ?? '') === $uf ? 'selected' : '';
                                echo "<option value=\"$uf\" $selected>$uf</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <a href="cliente_dashboard.php" class="btn btn-secondary btn-lg me-2">
                <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save"></i> Salvar Dados Pessoais
            </button>
        </div>
    </form>
</div>

<script>
// Busca CEP automaticamente
function buscarCEP() {
    const cep = document.getElementById('endereco_cep').value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        return;
    }
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (!data.erro) {
                document.getElementById('endereco_rua').value = data.logradouro || '';
                document.getElementById('endereco_bairro').value = data.bairro || '';
                document.getElementById('endereco_cidade').value = data.localidade || '';
                document.getElementById('endereco_estado').value = data.uf || '';
            } else {
                alert('CEP não encontrado!');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar CEP:', error);
        });
}

// Máscara para CEP
document.getElementById('endereco_cep').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 5) {
        value = value.substring(0, 5) + '-' + value.substring(5, 8);
    }
    e.target.value = value;
});

// Máscara para telefone
document.getElementById('telefone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) {
        value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (value.length > 6) {
        value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    }
    e.target.value = value;
});
</script>

</body>
</html>
