<?php
$page_title = 'Consulta CNPJ Simplificado';
require_once 'bootstrap.php';
require 'header.php'; 
?>

<div class='consulta-cnj-container'>
    <h2><i class="bi bi-building"></i> Consulta CNPJ Simplificado</h2>
    <p>Digite o CNPJ que deseja consultar.</p>

    <form id='cnpj-form' class='consulta-form'>
        <div class='input-group-dashboard'>
            <input type='text' id='cnpj' name='cnpj' placeholder='00.000.000/0000-00' required maxlength='18'>
            <button type='submit' id='search-button' class='btn-consultar'>
                <span id='button-text'>Consultar</span>
                <svg id='loading-spinner' class='animate-spin hidden' style='height: 1.25rem; width: 1.25rem; margin: auto;' xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24'>
                    <circle style='opacity: 0.25;' cx='12' cy='12' r='10' stroke='currentColor' stroke-width='4'></circle>
                    <path style='opacity: 0.75;' fill='currentColor' d='M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z'></path>
                </svg>
            </button>
        </div>
    </form>
    <div id='error-message' class='error-message hidden'></div>
</div>

<div id='result-container' class='resultado-container hidden'></div>

<script>
    // --- MÁSCARA DE CNPJ ---
    const cnpjInput = document.getElementById('cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', (e) => {
            e.target.value = formatCnpjMask(e.target.value);
        });
    }
    
    function formatCnpjMask(cnpj) {
        if (!cnpj) return "";
        const value = cnpj.replace(/\D/g, '');
        let maskedValue = '';
        if (value.length > 0) maskedValue = value.substring(0, 2);
        if (value.length > 2) maskedValue += '.' + value.substring(2, 5);
        if (value.length > 5) maskedValue += '.' + value.substring(5, 8);
        if (value.length > 8) maskedValue += '/' + value.substring(8, 12);
        if (value.length > 12) maskedValue += '-' + value.substring(12, 14);
        return maskedValue;
    }

    // --- LÓGICA DE CONSULTA (FETCH) ---
    document.getElementById('cnpj-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const cnpj = document.getElementById('cnpj').value;
        const resultContainer = document.getElementById('result-container');
        const errorMessage = document.getElementById('error-message');
        const searchButton = document.getElementById('search-button');
        const buttonText = document.getElementById('button-text');
        const loadingSpinner = document.getElementById('loading-spinner');

        resultContainer.innerHTML = '';
        resultContainer.classList.add('hidden');
        errorMessage.classList.add('hidden');
        
        buttonText.style.display = 'none';
        loadingSpinner.classList.remove('hidden');
        searchButton.disabled = true;

        fetch(`cnpj_proxy.php?cnpj=${cnpj}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { 
                        const defaultMessage = `Erro HTTP ${response.status}. Por favor, tente novamente.`;
                        throw new Error(err.message || defaultMessage);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.status && data.status === 'ERROR') {
                    throw new Error(data.message);
                }
                displayData(data);
            })
            .catch(error => {
                errorMessage.textContent = `Erro ao consultar: ${error.message}`;
                errorMessage.classList.remove('hidden');
            })
            .finally(() => {
                buttonText.style.display = 'inline';
                loadingSpinner.classList.add('hidden');
                searchButton.disabled = false;
            });
    });

    // --- FUNÇÕES GLOBAIS DE FORMATAÇÃO E CRIAÇÃO DE HTML ---
    const v = (field) => field || 'Não informado';
    const formatDate = (date) => date ? new Date(date + 'T00:00:00').toLocaleDateString('pt-BR') : 'Não informado';
    const formatCurrency = (value) => typeof value === 'number' ? value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : 'Não informado';
    const formatPercent = (value) => (value !== null && value !== undefined) ? `${value}%` : 'Não informado';
    const formatCnpjDisplay = (cnpj) => cnpj ? formatCnpjMask(cnpj) : 'Não informado';

    function displayData(data) {
        const resultContainer = document.getElementById('result-container');
        const html = `
        <div class='animate-fade-in' style='background-color: #fff; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.05);'>
            <h2 style='font-size: 1.25rem; font-weight: 700; color: #2d3748; margin-bottom: 1.5rem;'>Resultado da Consulta</h2>
            
            <!-- LINHA 1 -->
            <div style='display: grid; grid-template-columns: 3fr 3fr 1.5fr; gap: 1.5rem; margin-bottom: 1.5rem;'>
                <div><p style='font-size: 0.875rem; color: #718096;'>Razão Social</p><p style='font-weight: 600; color: #2d3748;'>${v(data.razao_social)}</p></div>
                <div><p style='font-size: 0.875rem; color: #718096;'>Nome Fantasia</p><p style='font-weight: 600; color: #2d3748;'>${v(data.nome_fantasia)}</p></div>
                <div><p style='font-size: 0.875rem; color: #718096;'>CNPJ</p><p style='font-weight: 600; color: #2d3748;'>${formatCnpjDisplay(data.cnpj)}</p></div>
            </div>

            <!-- LINHA 2 -->
            <div style='display: grid; grid-template-columns: 2fr 1fr 1.5fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem;'>
                <div><p style='font-size: 0.875rem; color: #718096;'>Natureza Jurídica</p><p style='font-weight: 600; color: #2d3748;'>${v(data.natureza_juridica)}</p></div>
                <div><p style='font-size: 0.875rem; color: #718096;'>Data de Abertura</p><p style='font-weight: 600; color: #2d3748;'>${formatDate(data.data_inicio_atividade)}</p></div>
                <div><p style='font-size: 0.875rem; color: #718096;'>Situação Cadastral</p><p style='font-weight: 600; color: ${data.descricao_situacao_cadastral === 'ATIVA' ? '#38a169' : '#e53e3e'};'>${v(data.descricao_situacao_cadastral)} desde ${formatDate(data.data_situacao_cadastral)}</p></div>
                <div><p style='font-size: 0.875rem; color: #718096;'>Capital Social</p><p style='font-weight: 600; color: #2d3748;'>${formatCurrency(data.capital_social)}</p></div>
            </div>
            
            <div style='display: flex; flex-direction: column; gap: 1rem;'>
                ${createAccordion('Endereço e Contato', createAddressHtml(data))}
                ${createAccordion('Atividades Econômicas (CNAE)', createCnaeHtml(data))}
                ${createAccordion('Quadro de Sócios (QSA)', createQsaHtml(data))}
            </div>
        </div>`;
        
        resultContainer.innerHTML = html;
        resultContainer.classList.remove('hidden');
    }

    function createAccordion(title, content) {
        if (!content) return '';
        return `
        <details style='background-color: #f7fafc; padding: 0.75rem; border-radius: 0.5rem; cursor: pointer;'>
            <summary style='display: flex; align-items: center; justify-content: space-between; font-size: 1.125rem; font-weight: 500; color: #4a5568; list-style: none inside;'>
                ${title}
                <span class='accordion-arrow' style='transition: transform 0.3s;'>▼</span>
            </summary>
            <div style='margin-top: 1rem; font-size: 0.875rem;'>${content}</div>
        </details>`;
    }

    function createAddressHtml(data) {
        return `
        <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;'>
            <div><p style='color: #718096;'>Logradouro</p><p style='font-weight: 500; color: #2d3748;'>${v(data.logradouro)}</p></div>
            <div><p style='color: #718096;'>Número</p><p style='font-weight: 500; color: #2d3748;'>${v(data.numero)}</p></div>
            <div><p style='color: #718096;'>Complemento</p><p style='font-weight: 500; color: #2d3748;'>${v(data.complemento)}</p></div>
            <div><p style='color: #718096;'>Bairro</p><p style='font-weight: 500; color: #2d3748;'>${v(data.bairro)}</p></div>
            <div><p style='color: #718096;'>Município / UF</p><p style='font-weight: 500; color: #2d3748;'>${v(data.municipio)} - ${v(data.uf)}</p></div>
            <div><p style='color: #718096;'>CEP</p><p style='font-weight: 500; color: #2d3748;'>${v(data.cep)}</p></div>
            <div><p style='color: #718096;'>Telefone</p><p style='font-weight: 500; color: #2d3748;'>${v(data.ddd_telefone_1) || v(data.ddd_telefone_2)}</p></div>
            <div><p style='color: #718096;'>E-mail</p><p style='font-weight: 500; color: #2d3748;'>${v(data.email)}</p></div>
        </div>`;
    }

    function createCnaeHtml(data) {
        if (!data.cnae_fiscal) return null;
        let secondaryHtml = '';
        if (data.cnaes_secundarios && data.cnaes_secundarios.length > 0) {
            secondaryHtml = `<p style='color: #718096; margin-top: 1rem;'>CNAEs Secundários</p><ul style='list-style-type: disc; list-style-position: inside; font-weight: 500; color: #2d3748; padding-left: 1rem;'>
                ${data.cnaes_secundarios.map(cnae => `<li><b>${cnae.codigo}:</b> ${cnae.descricao}</li>`).join('')}
            </ul>`;
        }
        return `
            <p style='color: #718096;'>CNAE Principal</p>
            <p style='font-weight: 500; color: #2d3748; margin-bottom: 0.5rem;'><b>${data.cnae_fiscal}:</b> ${data.cnae_fiscal_descricao}</p>
            ${secondaryHtml}
        `;
    }

    function createQsaHtml(data) {
        if (!data.qsa || data.qsa.length === 0) return "<p style='color: #718096;'>Quadro de Sócios e Administradores não disponível.</p>";
        
        const header = `
        <div class='qsa-table'>
            <div style='display: grid; grid-template-columns: 2fr 1fr 1.5fr 1fr 1fr 1fr 1.5fr; gap: 1rem; font-weight: 600; color: #4a5568; margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; min-width: 900px;'>
                <div>Nome</div>
                <div>CPF/CNPJ</div>
                <div>Qualificação</div>
                <div>Participação</div>
                <div>País Origem</div>
                <div>Data Entrada</div>
                <div>Rep. Legal</div>
            </div>`;

        const rows = data.qsa.map(socio => `
            <div style='display: grid; grid-template-columns: 2fr 1fr 1.5fr 1fr 1fr 1fr 1.5fr; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #edf2f7; min-width: 900px;'>
                <div style='font-weight: 500; color: #2d3748;'>${v(socio.nome_socio)}</div>
                <div>${v(socio.cnpj_cpf_do_socio)}</div>
                <div>${v(socio.qualificacao_socio)}</div>
                <div>${formatPercent(socio.percentual_capital_social)}</div>
                <div>${v(socio.pais)}</div>
                <div>${formatDate(socio.data_entrada_sociedade)}</div>
                <div>${v(socio.nome_representante_legal)} (${v(socio.qualificacao_representante_legal)})</div>
            </div>
        `).join('');
        return header + rows + '</div>';
    }
    
    document.addEventListener('toggle', event => {
        if (event.target.tagName === 'DETAILS') {
            const arrow = event.target.querySelector('.accordion-arrow');
            if (arrow) {
                arrow.style.transform = event.target.open ? 'rotate(180deg)' : 'rotate(0deg)';
            }
        }
    }, true);

</script>

<?php require 'footer.php'; ?>