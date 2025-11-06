<?php
/**
 * Template HTML para envio de emails
 * Usa as cores da empresa (whitelabel) e Bootstrap Icons
 * 
 * @param string $titulo - Título do email
 * @param string $nome_destinatario - Nome do destinatário
 * @param string $conteudo_html - Conteúdo principal do email (HTML)
 * @param string $link_acao - URL do botão de ação
 * @param string $texto_botao - Texto do botão de ação
 * @param string $cor_primaria - Cor principal da empresa (hex)
 * @param string $logo_url - URL completa do logo da empresa
 * @param string $nome_empresa - Nome da empresa
 * @return string HTML do email
 */
function gerarEmailTemplate($params) {
    // Parâmetros
    $titulo = $params['titulo'] ?? 'Notificação';
    $nome_destinatario = $params['nome_destinatario'] ?? '';
    $conteudo_html = $params['conteudo_html'] ?? '';
    $link_acao = $params['link_acao'] ?? null;
    $texto_botao = $params['texto_botao'] ?? 'Acessar';
    $cor_primaria = $params['cor_primaria'] ?? '#4f46e5';
    $logo_url = $params['logo_url'] ?? '';
    $nome_empresa = $params['nome_empresa'] ?? 'Verify';
    $icone_titulo = $params['icone_titulo'] ?? '📧';
    
    // Converte cor hex para RGB para usar transparência
    $cor_rgb = hexToRgb($cor_primaria);
    $cor_header = "rgba({$cor_rgb['r']}, {$cor_rgb['g']}, {$cor_rgb['b']}, 0.95)";
    $cor_botao = $cor_primaria;
    $cor_botao_hover = darkenColor($cor_primaria, 10);
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        .email-header {
            background: linear-gradient(135deg, {$cor_primaria} 0%, {$cor_header} 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        
        .email-header .logo {
            margin-bottom: 20px;
        }
        
        .email-header .logo img {
            max-height: 50px;
            max-width: 200px;
            filter: brightness(0) invert(1);
        }
        
        .email-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .email-body {
            padding: 40px 30px;
            background-color: #ffffff;
        }
        
        .email-body .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        .email-body .greeting strong {
            color: {$cor_primaria};
        }
        
        .email-body .content {
            color: #4b5563;
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .email-body .content p {
            margin-bottom: 15px;
        }
        
        .email-body .content ul {
            margin: 15px 0;
            padding-left: 25px;
        }
        
        .email-body .content li {
            margin-bottom: 10px;
            color: #6b7280;
        }
        
        .email-body .content li i {
            color: {$cor_primaria};
            margin-right: 8px;
        }
        
        .cta-container {
            text-align: center;
            margin: 35px 0;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, {$cor_botao} 0%, {$cor_botao_hover} 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }
        
        .info-box {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            border-left: 4px solid {$cor_primaria};
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .info-box p {
            margin: 0;
            color: #374151;
            font-size: 14px;
        }
        
        .info-box strong {
            color: {$cor_primaria};
        }
        
        .link-alternativo {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9fafb;
            border-radius: 8px;
            text-align: center;
        }
        
        .link-alternativo p {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .link-alternativo code {
            background-color: #ffffff;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            color: {$cor_primaria};
            font-size: 12px;
            word-break: break-all;
            display: block;
            margin-top: 8px;
        }
        
        .email-footer {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .email-footer p {
            color: #9ca3af;
            font-size: 13px;
            margin: 8px 0;
        }
        
        .email-footer .social-links {
            margin: 20px 0;
        }
        
        .email-footer .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6b7280;
            font-size: 20px;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .email-footer .social-links a:hover {
            color: {$cor_primaria};
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
            margin: 25px 0;
        }
        
        /* Responsividade */
        @media only screen and (max-width: 600px) {
            .email-container {
                border-radius: 0;
            }
            
            .email-header, .email-body, .email-footer {
                padding: 25px 20px;
            }
            
            .email-header h1 {
                font-size: 20px;
            }
            
            .cta-button {
                padding: 14px 30px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- HEADER -->
        <div class="email-header">
            <div class="logo">
                <img src="{$logo_url}" alt="{$nome_empresa}">
            </div>
            <h1>
                <span>{$icone_titulo}</span>
                <span>{$titulo}</span>
            </h1>
        </div>
        
        <!-- BODY -->
        <div class="email-body">
HTML;

    // Adiciona saudação se houver nome
    if (!empty($nome_destinatario)) {
        $html .= <<<HTML
            <div class="greeting">
                Olá, <strong>{$nome_destinatario}</strong>!
            </div>
HTML;
    }
    
    // Adiciona conteúdo principal
    $html .= <<<HTML
            <div class="content">
                {$conteudo_html}
            </div>
HTML;

    // Adiciona botão de ação se houver link
    if (!empty($link_acao)) {
        $html .= <<<HTML
            <div class="cta-container">
                <a href="{$link_acao}" class="cta-button">
                    <i class="bi bi-arrow-right-circle"></i> {$texto_botao}
                </a>
            </div>
            
            <div class="link-alternativo">
                <p><i class="bi bi-info-circle"></i> <strong>Ou copie e cole este link no seu navegador:</strong></p>
                <code>{$link_acao}</code>
            </div>
HTML;
    }
    
    $html .= <<<HTML
        </div>
        
        <!-- FOOTER -->
        <div class="email-footer">
            <div class="divider"></div>
            
            <p><strong>{$nome_empresa}</strong></p>
            <p>Este é um email automático, por favor não responda.</p>
            <p style="margin-top: 15px; color: #d1d5db; font-size: 11px;">
                © {ano} {$nome_empresa} - Todos os direitos reservados.
            </p>
        </div>
    </div>
</body>
</html>
HTML;

    // Substitui {ano} pelo ano atual
    $html = str_replace('{ano}', date('Y'), $html);
    
    return $html;
}

/**
 * Converte cor hexadecimal para RGB
 */
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    return ['r' => $r, 'g' => $g, 'b' => $b];
}

/**
 * Escurece uma cor hexadecimal em X%
 */
function darkenColor($hex, $percent) {
    $rgb = hexToRgb($hex);
    
    $r = max(0, $rgb['r'] - ($rgb['r'] * $percent / 100));
    $g = max(0, $rgb['g'] - ($rgb['g'] * $percent / 100));
    $b = max(0, $rgb['b'] - ($rgb['b'] * $percent / 100));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
