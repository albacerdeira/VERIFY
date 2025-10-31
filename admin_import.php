<?php
require_once 'bootstrap.php';
require_once 'header.php';

// --- VERIFICAÇÃO DE ACESSO ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
    echo '<div class="alert alert-danger">Acesso negado. Você não tem permissão para acessar esta página.</div>';
    require_once 'footer.php';
    exit();
}

// --- Definir o locale para pt_BR UTF-8 ---
setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil.1252');

$messages = [];

// --- FUNÇÕES AUXILIARES ---
function formatDate($dateString) {
    if (empty($dateString) || in_array(strtolower($dateString), ['não informada', 'sem informação'])) {
        return null;
    }
    try {
        $formats = ['d/m/Y', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
    } catch (Exception $e) { /* Ignora erros */ }
    return null;
}

function cleanCpfCnpj($doc) {
    return preg_replace('/[^0-9]/', '', $doc);
}

// --- FUNÇÃO PARA LIMPAR VALOR MONETÁRIO ---
function cleanValorMulta($value) {
    if (empty($value)) return 0.00;
    $value = str_replace('.', '', $value); // Remove separador de milhar (ex: 1.000)
    $value = str_replace(',', '.', $value); // Troca vírgula decimal por ponto
    return (float) preg_replace('/[^0-9\.]/', '', $value); // Remove qualquer outro caractere
}


// --- LÓGICA DE PROCESSAMENTO DO UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['data_file'])) {
    
    if ($_FILES['data_file']['error'] !== UPLOAD_ERR_OK) {
        $messages[] = ['type' => 'danger', 'text' => 'Erro no upload do arquivo. Código: ' . $_FILES['data_file']['error']];
    } else {
        $filePath = $_FILES['data_file']['tmp_name'];
        $importType = $_POST['import_type'] ?? '';
        $hasHeader = isset($_POST['has_header']);
        $delimiter = $_POST['delimiter'] ?? ';';
        if ($delimiter === '\t') $delimiter = "\t";

        $clearTable = isset($_POST['clear_table']);
        $forceUtf8 = isset($_POST['force_utf8']);

        $lineNumber = 0;
        $importedCount = 0;
        $errorCount = 0;

        $fileHandle = fopen($filePath, 'r');
        if (!$fileHandle) {
            $messages[] = ['type' => 'danger', 'text' => 'Erro: Não foi possível abrir o arquivo enviado.'];
        } else {
            if ($hasHeader) {
                fgetcsv($fileHandle, 0, $delimiter); // Pula cabeçalho
                $lineNumber++;
            }

            // --- ROTEAMENTO POR TIPO DE IMPORTAÇÃO ---
            switch ($importType) {
                case 'pep':
                    if ($clearTable) {
                        try {
                            $pdo->exec("TRUNCATE TABLE peps");
                            $messages[] = ['type' => 'warning', 'text' => '<b>Atenção:</b> Tabela [peps] foi limpa (TRUNCATE) antes da importação.'];
                        } catch (PDOException $e) {
                            $messages[] = ['type' => 'danger', 'text' => 'Erro CRÍTICO ao tentar limpar a tabela [peps]: ' . $e->getMessage()];
                            break; 
                        }
                    }

                    $sql = "INSERT INTO peps (cpf, nome_pep, sigla_funcao, descricao_funcao, nivel_funcao, nome_orgao, data_inicio_exercicio, data_fim_exercicio, data_fim_carencia) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE nome_pep=VALUES(nome_pep), sigla_funcao=VALUES(sigla_funcao), descricao_funcao=VALUES(descricao_funcao), nivel_funcao=VALUES(nivel_funcao), nome_orgao=VALUES(nome_orgao), data_inicio_exercicio=VALUES(data_inicio_exercicio), data_fim_exercicio=VALUES(data_fim_exercicio), data_fim_carencia=VALUES(data_fim_carencia)";
                    $stmt = $pdo->prepare($sql);

                    while (($row = fgetcsv($fileHandle, 0, $delimiter)) !== false) {
                        $lineNumber++;
                        if (count($row) < 9) { $errorCount++; continue; }

                        if ($forceUtf8) {
                            $row = array_map(function($cell) { return mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1'); }, $row);
                        }

                        list($cpf, $nome, $sigla, $desc, $nivel, $orgao, $inicio, $fim, $carencia) = $row;
                        $params = [trim($cpf), trim($nome), trim($sigla), trim($desc), trim($nivel), trim($orgao), formatDate($inicio), formatDate($fim), formatDate($carencia)];
                        try { $stmt->execute($params); $importedCount++; } catch (PDOException $e) { $errorCount++; }
                    }
                    break;

                case 'ceis':
                    if ($clearTable) {
                        try {
                            $pdo->exec("TRUNCATE TABLE ceis");
                            $messages[] = ['type' => 'warning', 'text' => '<b>Atenção:</b> Tabela [ceis] foi limpa (TRUNCATE) antes da importação.'];
                        } catch (PDOException $e) {
                            $messages[] = ['type' => 'danger', 'text' => 'Erro CRÍTICO ao tentar limpar a tabela [ceis]: ' . $e->getMessage()];
                            break; 
                        }
                    }
                    
                    // SQL Original com 24 colunas
                    $sql = "INSERT INTO ceis (cadastro_origem, codigo_sancao, tipo_pessoa, cpf_cnpj_sancionado, nome_sancionado, nome_informado_orgao, razao_social, nome_fantasia, numero_processo, categoria_sancao, data_inicio_sancao, data_final_sancao, data_publicacao, publicacao, detalhamento_publicacao, data_transito_julgado, abrangencia_sancao, orgao_sancionador, uf_orgao_sancionador, esfera_orgao_sancionador, fundamentacao_legal, data_origem_informacao, origem_informacoes, observacoes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE tipo_pessoa=VALUES(tipo_pessoa), cpf_cnpj_sancionado=VALUES(cpf_cnpj_sancionado), nome_sancionado=VALUES(nome_sancionado), data_final_sancao=VALUES(data_final_sancao), orgao_sancionador=VALUES(orgao_sancionador)";
                    $stmt = $pdo->prepare($sql);

                    while (($row = fgetcsv($fileHandle, 0, $delimiter)) !== false) {
                        $lineNumber++;
                        if (count($row) < 24) { $errorCount++; continue; } // Espera 24 colunas

                        if ($forceUtf8) {
                            $row = array_map(function($cell) { return mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1'); }, $row);
                        }
                        
                        // Mapeamento original de 24 colunas
                        $params = [
                            trim($row[0]), trim($row[1]), trim($row[2]), cleanCpfCnpj($row[3]), trim($row[4]), trim($row[5]), trim($row[6]), trim($row[7]), trim($row[8]), trim($row[9]), 
                            formatDate($row[10]), formatDate($row[11]), formatDate($row[12]), trim($row[13]), trim($row[14]), formatDate($row[15]), trim($row[16]), trim($row[17]), 
                            trim($row[18]), trim($row[19]), trim($row[20]), formatDate($row[21]), trim($row[22]), trim($row[23])
                        ];
                        try { $stmt->execute($params); $importedCount++; } catch (PDOException $e) { $errorCount++; $messages[] = ['type' => 'warning', 'text' => "Erro linha {$lineNumber}: " . $e->getMessage()]; }
                    }
                    break;

                // --- NOVO CASE PARA CNEP (TABELA SEPARADA) ---
                case 'cnep':
                    if ($clearTable) {
                        try {
                            $pdo->exec("TRUNCATE TABLE cnep");
                            $messages[] = ['type' => 'warning', 'text' => '<b>Atenção:</b> Tabela [cnep] foi limpa (TRUNCATE) antes da importação.'];
                        } catch (PDOException $e) {
                            $messages[] = ['type' => 'danger', 'text' => 'Erro CRÍTICO ao tentar limpar a tabela [cnep]: ' . $e->getMessage()];
                            break;
                        }
                    }

                    // SQL para 25 colunas na tabela CNEP
                    $sql = "INSERT INTO cnep (cadastro_origem, codigo_sancao, tipo_pessoa, cpf_cnpj_sancionado, nome_sancionado, nome_informado_orgao, razao_social, nome_fantasia, numero_processo, categoria_sancao, valor_da_multa, data_inicio_sancao, data_final_sancao, data_publicacao, publicacao, detalhamento_publicacao, data_transito_julgado, abrangencia_sancao, orgao_sancionador, uf_orgao_sancionador, esfera_orgao_sancionador, fundamentacao_legal, data_origem_informacao, origem_informacoes, observacoes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                cadastro_origem=VALUES(cadastro_origem), tipo_pessoa=VALUES(tipo_pessoa), cpf_cnpj_sancionado=VALUES(cpf_cnpj_sancionado), nome_sancionado=VALUES(nome_sancionado), 
                                nome_informado_orgao=VALUES(nome_informado_orgao), razao_social=VALUES(razao_social), nome_fantasia=VALUES(nome_fantasia), numero_processo=VALUES(numero_processo), 
                                categoria_sancao=VALUES(categoria_sancao), valor_da_multa=VALUES(valor_da_multa), data_inicio_sancao=VALUES(data_inicio_sancao), data_final_sancao=VALUES(data_final_sancao), 
                                data_publicacao=VALUES(data_publicacao), publicacao=VALUES(publicacao), detalhamento_publicacao=VALUES(detalhamento_publicacao), data_transito_julgado=VALUES(data_transito_julgado), 
                                abrangencia_sancao=VALUES(abrangencia_sancao), orgao_sancionador=VALUES(orgao_sancionador), uf_orgao_sancionador=VALUES(uf_orgao_sancionador), 
                                esfera_orgao_sancionador=VALUES(esfera_orgao_sancionador), fundamentacao_legal=VALUES(fundamentacao_legal), data_origem_informacao=VALUES(data_origem_informacao), 
                                origem_informacoes=VALUES(origem_informacoes), observacoes=VALUES(observacoes)";
                    $stmt = $pdo->prepare($sql);

                    while (($row = fgetcsv($fileHandle, 0, $delimiter)) !== false) {
                        $lineNumber++;
                        if (count($row) < 25) { $errorCount++; continue; } // Espera 25 colunas

                        if ($forceUtf8) {
                            $row = array_map(function($cell) { return mb_convert_encoding($cell, 'UTF-8', 'ISO-8859-1'); }, $row);
                        }

                        // Mapeamento de 25 colunas
                        $params = [
                            trim($row[0]), // cadastro_origem
                            trim($row[1]), // codigo_sancao
                            trim($row[2]), // tipo_pessoa
                            cleanCpfCnpj($row[3]), // cpf_cnpj_sancionado
                            trim($row[4]), // nome_sancionado
                            trim($row[5]), // nome_informado_orgao
                            trim($row[6]), // razao_social
                            trim($row[7]), // nome_fantasia
                            trim($row[8]), // numero_processo
                            trim($row[9]), // categoria_sancao
                            cleanValorMulta($row[10]), // valor_da_multa
                            formatDate($row[11]), // data_inicio_sancao
                            formatDate($row[12]), // data_final_sancao
                            formatDate($row[13]), // data_publicacao
                            trim($row[14]), // publicacao
                            trim($row[15]), // detalhamento_publicacao
                            formatDate($row[16]), // data_transito_julgado
                            trim($row[17]), // abrangencia_sancao
                            trim($row[18]), // orgao_sancionador
                            trim($row[19]), // uf_orgao_sancionador
                            trim($row[20]), // esfera_orgao_sancionador
                            trim($row[21]), // fundamentacao_legal
                            formatDate($row[22]), // data_origem_informacao
                            trim($row[23]), // origem_informacoes
                            trim($row[24])  // observacoes
                        ];
                        try { $stmt->execute($params); $importedCount++; } catch (PDOException $e) { $errorCount++; $messages[] = ['type' => 'warning', 'text' => "Erro linha {$lineNumber}: " . $e->getMessage()]; }
                    }
                    break;
                // --- FIM DO CASE CNEP ---

                default:
                    $messages[] = ['type' => 'danger', 'text' => 'Tipo de importação inválido selecionado.'];
                    break;
            }
            fclose($fileHandle);
            
            if (empty($messages) || ($importedCount > 0 && $errorCount == 0)) {
                $messages[] = ['type' => 'success', 'text' => 'Importação concluída.'];
                $messages[] = ['type' => 'info', 'text' => "Total de linhas processadas no arquivo: " . ($lineNumber - ($hasHeader ? 1 : 0))];
                $messages[] = ['type' => 'info', 'text' => "Registros importados/atualizados com sucesso: {$importedCount}"];
                $messages[] = ['type' => 'info', 'text' => "Linhas com erro ou ignoradas: {$errorCount}"];
            } else if ($errorCount > 0 && $importedCount > 0) {
                 $messages[] = ['type' => 'warning', 'text' => 'Importação concluída com erros.'];
                 $messages[] = ['type' => 'info', 'text' => "Registros importados/atualizados: {$importedCount}"];
                 $messages[] = ['type' => 'info', 'text' => "Linhas com erro: {$errorCount}"];
            }
        }
    }
}
?>

<div class="container mt-4">
    <h2>Importar Bases de Dados (PEP, CEIS & CNEP)</h2>
    <p>Esta página permite o upload de arquivos para popular os bancos de dados de compliance.</p>

    <?php if (!empty($messages)): ?>
        <div class="card my-4"><div class="card-header">Resultado da Importação</div><div class="card-body">
            <?php foreach ($messages as $message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>"><?php echo htmlspecialchars($message['text']); ?></div>
            <?php endforeach; ?>
        </div></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">Formulário de Upload</div>
        <div class="card-body">
            <form action="admin_import.php" method="post" enctype="multipart/form-data">
                <div class="form-group mb-3">
                    <label for="import_type"><strong>1. Selecione o Tipo de Base</strong></label>
                    <select class="form-control" name="import_type" id="import_type" required>
                        <option value="">-- Escolha uma opção --</option>
                        <option value="pep">PEP - Pessoas Politicamente Expostas</option>
                        <option value="ceis">CEIS - Empresas Inidôneas e Suspensas</option>
                        <option value="cnep">CNEP - Empresas Punidas (Lei Anticorrupção)</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label for="data_file"><strong>2. Selecione o arquivo (.csv, .txt)</strong></label>
                    <input type="file" class="form-control" name="data_file" id="data_file" accept=".csv,.txt,.tsv" required>
                </div>

                <div class="form-group mb-3">
                    <label for="delimiter"><strong>3. Informe o delimitador de colunas</strong></label>
                    <input type="text" class="form-control" name="delimiter" id="delimiter" value=";" style="width: 100px;">
                    <small class="form-text text-muted">Use <code>;</code> para ponto e vírgula, <code>\t</code> para tabulação, ou <code>,</code> para vírgula.</small>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="has_header" id="has_header" checked>
                    <label class="form-check-label" for="has_header"><strong>4. O arquivo contém cabeçalho?</strong> (A primeira linha será ignorada)</label>
                </div>

                <hr>
                <label><strong>5. Opções Avançadas</strong></label>
                
                <div class="form-check mb-3 p-3" style="background-color: #fff8f8; border: 1px solid #dc3545; border-radius: 5px;">
                    <input class="form-check-input" type="checkbox" name="clear_table" id="clear_table">
                    <label class="form-check-label text-danger" for="clear_table">
                        <strong>Limpar dados existentes antes de importar?</strong>
                        <br>
                        <small><strong>ATENÇÃO:</strong> Isso excluirá PERMANENTEMENTE todos os dados da tabela selecionada (<code>peps</code>, <code>ceis</code> ou <code>cnep</code>).</small>
                    </label>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="force_utf8" id="force_utf8">
                    <label class="form-check-label" for="force_utf8">
                        <strong>Forçar conversão UTF-8?</strong>
                        <br>
                        <small class="form-text text-muted">Marque esta opção se os acentos (ç, á, õ) não forem importados corretamente. (Converte de ISO-8859-1 para UTF-8)</small>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-upload"></i> Importar Arquivo</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>