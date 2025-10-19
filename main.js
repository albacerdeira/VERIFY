// Máscara para o campo CNPJ (XX.XXX.XXX/XXXX-XX)
document.addEventListener('DOMContentLoaded', function() {
    const cnpjInput = document.getElementById('cnpj');

    if (cnpjInput) {
        cnpjInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            
            // Aplica a máscara
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            
            e.target.value = value.slice(0, 18); // Limita o tamanho para não exceder a máscara
        });
    }
});
