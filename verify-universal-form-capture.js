/**
 * VERIFY LEAD CAPTURE - Universal Form Tracker
 * 
 * Este script captura automaticamente TODOS os formulários do seu site
 * e envia os dados para o sistema Verify via webhook.
 * 
 * INSTALAÇÃO:
 * 1. Cole este script no <head> ou antes do </body> do seu site
 * 2. Configure seu token na variável VERIFY_API_TOKEN
 * 3. Pronto! Todos os formulários serão capturados automaticamente
 */

(function() {
    'use strict';
    
    // ═══════════════════════════════════════════════════════════════
    // CONFIGURAÇÃO - EDITE APENAS ESTA SEÇÃO
    // ═══════════════════════════════════════════════════════════════
    
    // Detecta token da URL (se chamado com ?token=xxx) ou usa o configurado
    const urlParams = new URLSearchParams(window.location.search);
    const tokenFromScript = new URLSearchParams(document.currentScript?.src?.split('?')[1] || '');
    const apiTokenFromUrl = tokenFromScript.get('token');
    
    const VERIFY_CONFIG = {
        // Token de API - pode vir da URL do script ou ser configurado manualmente
        apiToken: apiTokenFromUrl || 'SEU_TOKEN_AQUI',
        
        // URL do webhook (deixe como está)
        apiUrl: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
            ? window.location.protocol + '//' + window.location.host + '/api_lead_webhook.php'
            : 'https://verify2b.com/api_lead_webhook.php',
        
        // Mapeamento de campos (o script tenta detectar automaticamente)
        fieldMapping: {
            nome: ['name', 'nome', 'full_name', 'fullname', 'your-name', 'first_name', 'firstname'],
            email: ['email', 'e-mail', 'mail', 'your-email', 'user_email'],
            whatsapp: ['whatsapp', 'phone', 'telefone', 'tel', 'mobile', 'celular', 'your-phone'],
            empresa: ['company', 'empresa', 'organization', 'your-company']
        },
        
        // Formulários a ignorar (seletores CSS)
        ignoreSelectors: [
            'form[action*="login"]',
            'form[action*="logout"]',
            'form[action*="search"]',
            'form.woocommerce-cart-form'
        ],
        
        // Mostrar logs no console (para debug)
        debug: true
    };
    
    // ═══════════════════════════════════════════════════════════════
    // CÓDIGO PRINCIPAL - NÃO EDITE ABAIXO DESTA LINHA
    // ═══════════════════════════════════════════════════════════════
    
    // Função de log
    function log(message, data) {
        if (VERIFY_CONFIG.debug) {
            console.log('[VERIFY Lead Capture]', message, data || '');
        }
    }
    
    // Detecta o nome do campo baseado no mapeamento
    function detectFieldType(input) {
        const name = (input.name || input.id || '').toLowerCase();
        const placeholder = (input.placeholder || '').toLowerCase();
        const label = getFieldLabel(input);
        const allText = `${name} ${placeholder} ${label}`.toLowerCase();
        
        for (const [fieldType, keywords] of Object.entries(VERIFY_CONFIG.fieldMapping)) {
            for (const keyword of keywords) {
                if (allText.includes(keyword)) {
                    return fieldType;
                }
            }
        }
        
        // Detecção por tipo de input
        if (input.type === 'email') return 'email';
        if (input.type === 'tel') return 'whatsapp';
        
        return null;
    }
    
    // Pega o label associado ao campo
    function getFieldLabel(input) {
        if (input.id) {
            const label = document.querySelector(`label[for="${input.id}"]`);
            if (label) return label.textContent.toLowerCase();
        }
        const parent = input.closest('label');
        if (parent) return parent.textContent.toLowerCase();
        return '';
    }
    
    // Extrai dados do formulário
    function extractFormData(form) {
        const data = {
            nome: null,
            email: null,
            whatsapp: null,
            empresa: null,
            mensagem: null
        };
        
        // Pega todos os inputs, textareas e selects
        const fields = form.querySelectorAll('input, textarea, select');
        
        fields.forEach(field => {
            // Ignora campos ocultos, botões, etc
            if (['hidden', 'submit', 'button', 'reset', 'file'].includes(field.type)) {
                return;
            }
            
            const fieldType = detectFieldType(field);
            const value = field.value.trim();
            
            if (value && fieldType && !data[fieldType]) {
                data[fieldType] = value;
                log(`Campo detectado: ${fieldType}`, value);
            } else if (value && !fieldType && field.tagName === 'TEXTAREA') {
                // TextAreas sem identificação viram mensagem
                if (!data.mensagem) {
                    data.mensagem = value;
                }
            }
        });
        
        return data;
    }
    
    // Valida se os dados mínimos estão presentes
    function isValidLeadData(data) {
        return data.nome && data.email && data.whatsapp;
    }
    
    // Captura parâmetros UTM da URL
    function getUTMParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            utm_source: params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign')
        };
    }
    
    // Envia dados para o webhook
    function sendToVerify(leadData) {
        const payload = {
            ...leadData,
            origem: window.location.href,
            referer: document.referrer,
            ...getUTMParams()
        };
        
        log('Enviando lead para Verify...', payload);
        
        const url = `${VERIFY_CONFIG.apiUrl}?token=${VERIFY_CONFIG.apiToken}`;
        
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            // Captura o status HTTP
            if (!response.ok) {
                log(`❌ Erro HTTP: ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                log('✅ Lead enviado com sucesso!', data);
                
                // Dispara evento customizado para tracking
                window.dispatchEvent(new CustomEvent('verifyLeadCaptured', {
                    detail: { lead_id: data.lead_id, payload }
                }));
                
                // Envia para Google Analytics se disponível
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'generate_lead', {
                        'event_category': 'Lead',
                        'event_label': 'Universal Form Capture',
                        'value': 1
                    });
                }
                
                // Envia para GTM se disponível
                if (typeof dataLayer !== 'undefined') {
                    dataLayer.push({
                        'event': 'lead_captured',
                        'lead_id': data.lead_id,
                        'form_url': window.location.href
                    });
                }
                
                return true;
            } else {
                log('❌ Erro ao enviar lead:', data.message || 'Erro desconhecido');
                log('📋 Detalhes da resposta:', data);
                return false;
            }
        })
        .catch(error => {
            log('❌ Erro de conexão:', error.message || error);
            log('🔍 URL tentada:', url);
            log('📦 Payload enviado:', payload);
            return false;
        });
    }
    
    // Verifica se o formulário deve ser ignorado
    function shouldIgnoreForm(form) {
        return VERIFY_CONFIG.ignoreSelectors.some(selector => {
            return form.matches(selector);
        });
    }
    
    // Intercepta submissão do formulário
    function interceptFormSubmit(form) {
        if (shouldIgnoreForm(form)) {
            log('Formulário ignorado', form.action);
            return;
        }
        
        log('Formulário monitorado', form.action || 'sem action');
        
        form.addEventListener('submit', function(e) {
            const formData = extractFormData(form);
            
            log('Dados capturados do formulário:', formData);
            
            if (isValidLeadData(formData)) {
                // Envia de forma assíncrona (não bloqueia o submit)
                sendToVerify(formData);
                log('✅ Lead capturado e enviado!');
            } else {
                log('⚠️ Dados insuficientes para criar lead', formData);
            }
            
            // Deixa o formulário continuar normalmente
            // (não fazemos e.preventDefault())
        });
    }
    
    // Inicializa o monitoramento
    function init() {
        // Valida configuração
        if (!VERIFY_CONFIG.apiToken || VERIFY_CONFIG.apiToken === 'SEU_TOKEN_AQUI') {
            console.error('[VERIFY] ❌ Configure seu API Token antes de usar!');
            return;
        }
        
        log('🚀 Iniciando captura universal de formulários...');
        
        // Monitora formulários existentes
        document.querySelectorAll('form').forEach(form => {
            interceptFormSubmit(form);
        });
        
        // Monitora formulários adicionados dinamicamente (AJAX/SPA)
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) { // ELEMENT_NODE
                        if (node.tagName === 'FORM') {
                            interceptFormSubmit(node);
                        }
                        // Busca formulários dentro do novo elemento
                        node.querySelectorAll && node.querySelectorAll('form').forEach(form => {
                            interceptFormSubmit(form);
                        });
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        log('✅ Monitoramento ativo! Total de formulários:', document.querySelectorAll('form').length);
    }
    
    // Aguarda o DOM estar pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expõe API global (opcional)
    window.VerifyLeadCapture = {
        version: '1.0.0',
        config: VERIFY_CONFIG,
        sendLead: sendToVerify
    };
    
})();
