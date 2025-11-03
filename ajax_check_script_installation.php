<?php
/**
 * Verifica se o script de captura universal está instalado em um site
 * Similar ao Google Tag Manager Assistant
 */

// Inicia sessão PRIMEIRO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Remove qualquer output anterior
ob_start();

header('Content-Type: application/json');

// Carrega apenas config (sem bootstrap que pode redirecionar)
require_once 'config.php';

// Debug da sessão
error_log("AJAX_CHECK - Session ID: " . session_id());
error_log("AJAX_CHECK - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("AJAX_CHECK - Session usuario_id: " . ($_SESSION['usuario_id'] ?? 'NOT SET'));
error_log("AJAX_CHECK - Session user_role: " . ($_SESSION['user_role'] ?? 'NOT SET'));
error_log("AJAX_CHECK - Session empresa_id: " . ($_SESSION['empresa_id'] ?? 'NOT SET'));

// Verifica se está logado (pode ser user_id OU usuario_id)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    ob_clean();
    http_response_code(401);
    error_log("AJAX_CHECK - ERRO: Usuário não autenticado");
    echo json_encode(['success' => false, 'error' => 'Não autenticado. Faça login novamente.']);
    exit;
}

$user_role = $_SESSION['user_role'] ?? '';
$user_empresa_id = $_SESSION['empresa_id'] ?? null;

// Apenas admin e superadmin
if ($user_role !== 'administrador' && $user_role !== 'superadmin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão']);
    exit;
}

try {
    // Pega a URL a ser verificada
    $website_url = $_POST['website_url'] ?? '';
    
    error_log("AJAX_CHECK_SCRIPT - Iniciando verificação");
    error_log("AJAX_CHECK_SCRIPT - URL recebida: " . $website_url);
    error_log("AJAX_CHECK_SCRIPT - Empresa ID: " . $user_empresa_id);
    
    if (empty($website_url)) {
        throw new Exception('URL não informada');
    }
    
    // Normaliza a URL
    if (!preg_match('/^https?:\/\//i', $website_url)) {
        $website_url = 'https://' . $website_url;
    }
    
    // Busca o token esperado da empresa
    $empresa_id = $user_role === 'superadmin' && isset($_POST['empresa_id']) 
        ? (int)$_POST['empresa_id'] 
        : $user_empresa_id;
    
    $stmt = $pdo->prepare("SELECT api_token FROM configuracoes_whitelabel WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['api_token'])) {
        throw new Exception('Token não configurado para esta empresa');
    }
    
    $expected_token = $config['api_token'];
    
    error_log("AJAX_CHECK_SCRIPT - Token esperado: " . substr($expected_token, 0, 20) . "...");
    error_log("AJAX_CHECK_SCRIPT - Fazendo requisição para: " . $website_url);
    
    // Faz requisição HTTP para buscar o HTML do site
    $ch = curl_init($website_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Verify Script Checker/1.0');
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("AJAX_CHECK_SCRIPT - HTTP Code: " . $http_code);
    error_log("AJAX_CHECK_SCRIPT - HTML length: " . strlen($html));
    
    if ($http_code !== 200) {
        $error_msg = "Erro ao acessar o site (HTTP $http_code)";
        if ($error) {
            $error_msg .= ": $error";
        }
        error_log("AJAX_CHECK_SCRIPT - ERRO: " . $error_msg);
        throw new Exception($error_msg);
    }
    
    if (empty($html)) {
        throw new Exception('Site retornou conteúdo vazio');
    }
    
    // Verifica se o script está presente no HTML
    $script_found = false;
    $token_found = false;
    $script_url_found = null;
    
    // Procura por <script src="...verify-universal-form-capture.js...">
    if (preg_match('/src=["\']([^"\']*verify-universal-form-capture\.js[^"\']*)["\']/', $html, $matches)) {
        $script_found = true;
        $script_url_found = $matches[1];
        
        // Verifica se tem o token correto na URL
        if (strpos($script_url_found, $expected_token) !== false) {
            $token_found = true;
        }
    }
    
    // Conta quantos formulários existem na página
    $form_count = preg_match_all('/<form[^>]*>/i', $html, $forms);
    
    // Extrai detalhes de cada formulário
    $forms_details = [];
    if (preg_match_all('/<form[^>]*>.*?<\/form>/is', $html, $full_forms)) {
        foreach ($full_forms[0] as $index => $form_html) {
            $form_info = [
                'index' => $index + 1,
                'id' => null,
                'name' => null,
                'action' => null,
                'method' => 'GET',
                'inputs' => []
            ];
            
            // Extrai atributos do form
            if (preg_match('/id=["\']([^"\']+)["\']/', $form_html, $match)) {
                $form_info['id'] = $match[1];
            }
            if (preg_match('/name=["\']([^"\']+)["\']/', $form_html, $match)) {
                $form_info['name'] = $match[1];
            }
            if (preg_match('/action=["\']([^"\']+)["\']/', $form_html, $match)) {
                $form_info['action'] = $match[1];
            }
            if (preg_match('/method=["\']([^"\']+)["\']/', $form_html, $match)) {
                $form_info['method'] = strtoupper($match[1]);
            }
            
            // Extrai inputs (input, textarea, select)
            preg_match_all('/<(input|textarea|select)[^>]*>/i', $form_html, $inputs);
            foreach ($inputs[0] as $input_html) {
                $input_info = [
                    'type' => 'text',
                    'name' => null,
                    'id' => null,
                    'placeholder' => null,
                    'tag' => 'input'
                ];
                
                // Tag type
                if (preg_match('/<(input|textarea|select)/i', $input_html, $match)) {
                    $input_info['tag'] = strtolower($match[1]);
                }
                
                // Type (para inputs)
                if (preg_match('/type=["\']([^"\']+)["\']/', $input_html, $match)) {
                    $input_info['type'] = $match[1];
                }
                
                // Name
                if (preg_match('/name=["\']([^"\']+)["\']/', $input_html, $match)) {
                    $input_info['name'] = $match[1];
                }
                
                // ID
                if (preg_match('/id=["\']([^"\']+)["\']/', $input_html, $match)) {
                    $input_info['id'] = $match[1];
                }
                
                // Placeholder
                if (preg_match('/placeholder=["\']([^"\']+)["\']/', $input_html, $match)) {
                    $input_info['placeholder'] = $match[1];
                }
                
                // Ignora campos hidden, submit, button
                if (!in_array($input_info['type'], ['hidden', 'submit', 'button', 'reset', 'file'])) {
                    $form_info['inputs'][] = $input_info;
                }
            }
            
            // Só adiciona se tiver pelo menos 1 input visível
            if (!empty($form_info['inputs'])) {
                $forms_details[] = $form_info;
            }
        }
    }
    
    // Monta resposta
    $response = [
        'success' => true,
        'website_url' => $website_url,
        'script_installed' => $script_found,
        'token_correct' => $token_found,
        'script_url' => $script_url_found,
        'form_count' => $form_count,
        'forms_details' => $forms_details,
        'http_code' => $http_code,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Mensagem amigável
    if (!$script_found) {
        $response['message'] = '❌ Script não encontrado no site';
        $response['details'] = 'O script verify-universal-form-capture.js não foi detectado no código HTML da página.';
        $response['action'] = 'Copie o código de instalação e cole no <head> ou antes do </body> do seu site.';
    } elseif (!$token_found) {
        $response['message'] = '⚠️ Script encontrado mas token incorreto';
        $response['details'] = 'O script está instalado, mas está usando um token diferente do configurado para sua empresa.';
        $response['action'] = 'Verifique se copiou o código correto da seção "Captura Universal de Formulários".';
    } else {
        $response['message'] = '✅ Script instalado corretamente!';
        $response['details'] = "O script está ativo e pronto para capturar leads. Foram detectados $form_count formulário(s) na página.";
        $response['action'] = null;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// Limpa qualquer output adicional
ob_end_flush();
