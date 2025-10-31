<?php
$page_title = 'Consulta de CPF';
require_once 'bootstrap.php';
require 'header.php';
?>

<div class="container bg-white p-4 p-md-5 rounded shadow-sm">
    <h2 class="mb-3">Consulta de CPF no Portal da Transparência</h2>
    <p class="lead text-muted mb-4">Digite o CPF que deseja consultar para verificar vínculos com o governo federal.</p>

    <div class="card mb-4">
        <div class="card-body">
            <form id="cpf-form" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00" required maxlength="14">
                </div>
                <div class="col-md-4">
                    <button type="submit" id="search-button" class="btn btn-primary w-100">
                        <span id="button-text">Consultar</span>
                        <span id="loading-spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                    </button>
                </div>
            </form>
            <div id="error-message" class="alert alert-danger mt-3" style="display: none;"></div>
        </div>
    </div>

    <div id="result-container" style="display: none;">
        <h3 class="mb-3">Resultado da Consulta</h3>
        <div class="card">
            <div class="card-header">
                <h5 id="result-nome" class="mb-0"></h5>
                <small id="result-cpf" class="text-muted"></small>
            </div>
            <div class="card-body">
                <div id="result-grid" class="row"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cpfInput = document.getElementById('cpf');
    const form = document.getElementById('cpf-form');
    const searchButton = document.getElementById('search-button');
    const buttonText = document.getElementById('button-text');
    const loadingSpinner = document.getElementById('loading-spinner');
    const errorMessage = document.getElementById('error-message');
    const resultContainer = document.getElementById('result-container');

    // Máscara de CPF
    cpfInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const cpf = cpfInput.value;

        // Reset UI
        errorMessage.style.display = 'none';
        resultContainer.style.display = 'none';
        buttonText.style.display = 'none';
        loadingSpinner.style.display = 'inline-block';
        searchButton.disabled = true;

        fetch(`cpf_proxy.php?cpf=${cpf}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || `Erro ${response.status}`); });
                }
                return response.json();
            })
            .then(data => {
                if (Array.isArray(data) && data.length > 0) {
                    displayData(data[0]); // A API retorna um array com um objeto
                } else {
                    throw new Error('Nenhum resultado encontrado para este CPF.');
                }
            })
            .catch(error => {
                errorMessage.textContent = `Erro ao consultar: ${error.message}`;
                errorMessage.style.display = 'block';
            })
            .finally(() => {
                buttonText.style.display = 'inline-block';
                loadingSpinner.style.display = 'none';
                searchButton.disabled = false;
            });
    });

    function displayData(data) {
        document.getElementById('result-nome').textContent = data.nome || 'Nome não informado';
        document.getElementById('result-cpf').textContent = `CPF: ${cpfInput.value}`;
        
        const resultGrid = document.getElementById('result-grid');
        resultGrid.innerHTML = '';

        const labels = {
            favorecidoBolsaFamilia: "Bolsa Família",
            favorecidoNovoBolsaFamilia: "Novo Bolsa Família",
            servidor: "Servidor Público Federal",
            servidorInativo: "Servidor Inativo/Pensionista",
            auxilioEmergencial: "Auxílio Emergencial",
            favorecidoAuxilioBrasil: "Auxílio Brasil",
            sancionadoCEIS: "Sanção (CEIS)",
            sancionadoCNEP: "Sanção (CNEP)",
            participanteLicitacao: "Participante de Licitação"
        };

        for (const key in data) {
            if (labels[key] && data[key] === true) {
                const col = document.createElement('div');
                col.className = 'col-md-4 mb-3';
                col.innerHTML = `
                    <div class="alert alert-warning h-100">
                        <h6 class="alert-heading">${labels[key]}</h6>
                        <p class="mb-0">Este CPF possui um vínculo como ${labels[key]}.</p>
                    </div>
                `;
                resultGrid.appendChild(col);
            }
        }

        if (resultGrid.innerHTML === '') {
            resultGrid.innerHTML = '<div class="col-12"><p class="text-muted">Nenhum vínculo de interesse encontrado para este CPF.</p></div>';
        }

        resultContainer.style.display = 'block';
    }
});
</script>

<?php require 'footer.php'; ?>
