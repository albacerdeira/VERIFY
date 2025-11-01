<?php
$page_title = 'Configurações Whitelabel';
require_once 'bootstrap.php';

// Garante que apenas administradores e superadmins possam acessar.
if (!$is_admin && !$is_superadmin) {
    require_once 'header.php'; // Carrega o header para manter o layout
    echo "<div class='container'><div class='alert alert-danger'>Acesso negado.</div></div>";
    require_once 'footer.php';
    exit;
}

function gerarSlug($texto) {
    $texto = preg_replace('~[^\pL\d]+~u', '-', $texto);
    $texto = iconv('utf-8', 'us-ascii//TRANSLIT', $texto);
    $texto = preg_replace('~[^-\w]+~', '', $texto);
    $texto = trim($texto, '-');
    $texto = preg_replace('~-+~', '-', $texto);
    $texto = strtolower($texto);
    if (empty($texto)) { return 'n-a-' . uniqid(); }
    return $texto;
}

$error = null;
$success = null;

// Lógica de decisão de qual empresa editar (deve vir antes do POST)
$empresa_id_para_editar = null;
if ($is_superadmin && isset($_GET['id'])) {
    $empresa_id_para_editar = (int)$_GET['id'];
} else if ($is_admin) {
    // Admin sempre edita sua própria empresa
    $empresa_id_para_editar = (int)($_SESSION['empresa_id'] ?? 0);
}

// DEBUG: Remover depois
error_log("DEBUG configuracoes.php: empresa_id_para_editar = " . var_export($empresa_id_para_editar, true));
error_log("DEBUG configuracoes.php: SESSION empresa_id = " . var_export($_SESSION['empresa_id'] ?? 'NOT SET', true));
error_log("DEBUG configuracoes.php: is_admin = " . var_export($is_admin, true));
error_log("DEBUG configuracoes.php: is_superadmin = " . var_export($is_superadmin, true));

// Carrega as configurações atuais ANTES de processar o POST para ter o estado pré-mudança
$config_atual = null;
if ($empresa_id_para_editar && $empresa_id_para_editar > 0) {
    $stmt_current = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt_current->execute([$empresa_id_para_editar]);
    $config_atual = $stmt_current->fetch(PDO::FETCH_ASSOC);
    
    // Se não existe registro, cria um novo
    if (!$config_atual) {
        $stmt_empresa = $pdo->prepare("SELECT nome FROM empresas WHERE id = ?");
        $stmt_empresa->execute([$empresa_id_para_editar]);
        $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            // Cria registro inicial
            $stmt_insert = $pdo->prepare(
                "INSERT INTO configuracoes_whitelabel (empresa_id, nome_empresa, logo_url, cor_variavel) 
                 VALUES (?, ?, 'imagens/verify-kyc.png', '#4f46e5')"
            );
            $stmt_insert->execute([$empresa_id_para_editar, $empresa['nome']]);
            
            // Recarrega
            $stmt_current->execute([$empresa_id_para_editar]);
            $config_atual = $stmt_current->fetch(PDO::FETCH_ASSOC);
        }
    }
}

// Lógica de processamento do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id_post = $_POST['empresa_id_para_editar'];
    
    if (!$is_superadmin && $empresa_id_post != $_SESSION['empresa_id']) {
        $error = "Acesso negado.";
    } else {
        $nome_empresa = trim($_POST['nome_empresa']);
        $cor_variavel = trim($_POST['cor_variavel']);
        $google_tag_manager_id = trim($_POST['google_tag_manager_id']);
        $logo_path = $_POST['current_logo'] ?? '';
        $slug = $_POST['slug'] ?? ''; 

        try {
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $filename = uniqid() . '-' . basename($_FILES['logo']['name']);
                $target_file = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                    $logo_path = $target_file;
                } else { throw new Exception("Falha ao mover o arquivo de logo enviado."); }
            }

            $sql = "UPDATE configuracoes_whitelabel SET nome_empresa = :nome, logo_url = :logo, cor_variavel = :cor, google_tag_manager_id = :gtm_id";
            $params = [':nome' => $nome_empresa, ':logo' => $logo_path, ':cor' => $cor_variavel, ':gtm_id' => $google_tag_manager_id, ':id' => $empresa_id_post];

            if ($is_superadmin || empty($config_atual['slug'])) {
                if (empty($slug)) throw new Exception("O campo Slug é obrigatório.");
                $sql .= ", slug = :slug";
                $params[':slug'] = gerarSlug($slug);
            }

            $sql .= " WHERE empresa_id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($empresa_id_post == ($_SESSION['empresa_id'] ?? null)) {
                $_SESSION['nome_empresa'] = $nome_empresa;
                $_SESSION['logo_url'] = $logo_path;
                $_SESSION['cor_variavel'] = $cor_variavel;
            }

            $success = "Configurações salvas com sucesso!";
            // Recarrega a página para refletir as mudanças na sessão e no formulário
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;

        } catch (Exception $e) {
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// Carrega o header da página
require_once 'header.php';

// --- LÓGICA DE EXIBIÇÃO ---

if ($is_superadmin && !$empresa_id_para_editar) {
    $empresas = $pdo->query("SELECT id, nome FROM empresas ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>
    <div class="container">
        <div class="alert alert-info mb-4">
            <h5 class="alert-heading">Modo Superadmin</h5>
            <p>Selecione uma empresa para editar suas configurações.</p>
            <select id="empresa-selector" class="form-select">
                <option value="">Selecione...</option>
                <?php foreach ($empresas as $empresa): ?>
                    <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <script>
    document.getElementById('empresa-selector').addEventListener('change', function() {
        if (this.value) {
            window.location.href = 'configuracoes.php?id=' + this.value;
        }
    });
    </script>
<?php
} elseif ($empresa_id_para_editar && $empresa_id_para_editar > 0) {
    // Recarrega os dados da empresa caso tenham sido atualizados
    $stmt = $pdo->prepare("SELECT * FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$empresa_id_para_editar]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se não encontrou, tenta criar
    if (!$config) {
        $stmt_empresa = $pdo->prepare("SELECT nome FROM empresas WHERE id = ?");
        $stmt_empresa->execute([$empresa_id_para_editar]);
        $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $stmt_insert = $pdo->prepare(
                "INSERT INTO configuracoes_whitelabel (empresa_id, nome_empresa, logo_url, cor_variavel) 
                 VALUES (?, ?, 'imagens/verify-kyc.png', '#4f46e5')"
            );
            $stmt_insert->execute([$empresa_id_para_editar, $empresa['nome']]);
            
            // Recarrega
            $stmt->execute([$empresa_id_para_editar]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
?>
    <div class="container-fluid">
        <h2 class="mb-4">Configurações para: <?= htmlspecialchars($config['nome_empresa'] ?? 'Nova Empresa') ?></h2>

        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="configuracoes.php<?= $is_superadmin ? '?id=' . $empresa_id_para_editar : '' ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="empresa_id_para_editar" value="<?= $empresa_id_para_editar ?>">
                    
                    <div class="form-group mb-3">
                        <label for="nome_empresa">Nome da Empresa</label>
                        <input type="text" class="form-control" id="nome_empresa" name="nome_empresa" value="<?= htmlspecialchars($config['nome_empresa'] ?? '') ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="slug">Slug da URL (identificador para o link do parceiro)</label>
                        <div class="input-group">
                            <span class="input-group-text">cnpj.foconteudo.com.br/kyc_form.php?cliente=</span>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?= htmlspecialchars($config['slug'] ?? '') ?>"
                                   <?php if (!$is_superadmin && !empty($config['slug'])) echo 'readonly'; ?>
                                   placeholder="defina-o-slug-aqui">
                        </div>
                        <small class="form-text text-muted">
                            <?php if (empty($config['slug']) && !$is_superadmin): ?>
                                Defina o identificador único para seu link de KYC. Use apenas letras, números e hífens. **Esta ação só pode ser feita uma vez.**
                            <?php else: ?>
                                Este é o identificador único para seu link de KYC.
                            <?php endif; ?>
                        </small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="logo" class="form-label">Logotipo</label>
                        <input type="file" class="form-control" id="logo" name="logo">
                        <small class="form-text text-muted">Envie um novo arquivo para substituir o logo atual.</small>
                        <?php if (!empty($config['logo_url'])): ?>
                            <div class="mt-2">Logo Atual: <img src="<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo Atual" style="max-height: 50px; background-color: #f0f0f0; padding: 5px; border-radius: 4px;"></div>
                            <input type="hidden" name="current_logo" value="<?= htmlspecialchars($config['logo_url']) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="cor_variavel" class="form-label">Cor Principal</label>
                        <input type="color" class="form-control form-control-color" id="cor_variavel" name="cor_variavel" value="<?= htmlspecialchars($config['cor_variavel'] ?? '#4f46e5') ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label for="google_tag_manager_id">ID do Google Tag Manager</label>
                        <input type="text" class="form-control" id="google_tag_manager_id" name="google_tag_manager_id" value="<?= htmlspecialchars($config['google_tag_manager_id'] ?? '') ?>" placeholder="GTM-XXXXXX">
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Salvar Configurações</button>
                </form>
            </div>
        </div>
    </div>
<?php
} else {
    echo "<div class='container'><div class='alert alert-warning'>Nenhuma empresa associada à sua conta.</div></div>";
}

require_once 'footer.php';
?>