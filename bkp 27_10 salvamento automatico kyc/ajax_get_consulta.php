<?php
session_start();
require_once 'config.php';

function exit_with_error($message) {
    http_response_code(400);
    echo "<div class='alert alert-danger'>".htmlspecialchars($message)."</div>";
    exit;
}

if (!isset($_SESSION['user_id'])) {
    exit_with_error('Usuário não autenticado.');
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit_with_error('ID de consulta inválido.');
}

$consulta_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$empresa_id = $_SESSION['empresa_id'] ?? null;

// Função auxiliar para exibir um campo na lista de detalhes
function render_detail($label, $value, $is_money = false, $is_date = false) {
    if (!empty($value) || $value === 0 || $value === '0') {
        $display_value = htmlspecialchars($value);
        if ($is_money) {
            $display_value = 'R$ ' . number_format(floatval($value), 2, ',', '.');
        } elseif ($is_date && strtotime($value)) {
            $display_value = date('d/m/Y', strtotime($value));
        }
        return '<dt class="col-sm-4">' . htmlspecialchars($label) . '</dt><dd class="col-sm-8">' . $display_value . '</dd>';
    }
    return '';
}

try {
    // 1. SQL CORRIGIDO: Seleciona todas as colunas
    $sql = "SELECT c.* FROM consultas c JOIN usuarios u ON c.usuario_id = u.id WHERE c.id = :consulta_id";
    
    // Lógica de permissão
    if ($user_role === 'usuario') {
        $sql .= " AND c.usuario_id = :user_id";
        $params = [':consulta_id' => $consulta_id, ':user_id' => $user_id];
    } elseif (in_array($user_role, ['admin', 'administrador'])) {
        $sql .= " AND u.empresa_id = :empresa_id";
        $params = [':consulta_id' => $consulta_id, ':empresa_id' => $empresa_id];
    } else { // superadmin
        $params = [':consulta_id' => $consulta_id];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $consulta = $stmt->fetch(PDO::FETCH_ASSOC); // Usando $consulta para clareza

    if (!$consulta) {
        exit_with_error('Consulta não encontrada ou você não tem permissão para visualizá-la.');
    }

    // 2. HTML RECONSTRUÍDO: Usando as colunas individuais
    header('Content-Type: text/html; charset=utf-8');
    $html = '<div class="container-fluid">';
    
    $html .= '<h4>'.htmlspecialchars($consulta['razao_social'] ?? 'N/A').'</h4>';
    $html .= '<p class="text-muted mb-2">'.htmlspecialchars($consulta['nome_fantasia'] ?? 'Nome Fantasia não disponível').'</p>';
    $html .= '<hr class="mt-2">';

    $html .= '<div class="row">';
    // Coluna da Esquerda
    $html .= '<div class="col-md-6">';
    $html .= '<h5><i class="fas fa-building mr-2"></i>Dados da Empresa</h5><dl class="row">';
    $html .= render_detail('CNPJ', $consulta['cnpj']);
    $html .= render_detail('Situação Cadastral', $consulta['descricao_situacao_cadastral']);
    $html .= render_detail('Data da Situação', $consulta['data_situacao_cadastral'], false, true);
    $html .= render_detail('Início da Atividade', $consulta['data_inicio_atividade'], false, true);
    $html .= render_detail('Tipo', $consulta['descricao_identificador_matriz_filial']);
    $html .= render_detail('Porte', $consulta['porte']);
    $html .= render_detail('Capital Social', $consulta['capital_social'], true);
    $html .= render_detail('Natureza Jurídica', $consulta['natureza_juridica']);
    $html .= render_detail('CNAE Principal', $consulta['cnae_fiscal']);
    $html .= render_detail('Situação Especial', $consulta['situacao_especial']);
    $html .= '</dl>';
    $html .= '</div>';

    // Coluna da Direita
    $html .= '<div class="col-md-6">';
    $html .= '<h5><i class="fas fa-map-marker-alt mr-2"></i>Endereço</h5><address>';
    $html .= htmlspecialchars($consulta['logradouro'] ?? 'N/A') . ', ' . htmlspecialchars($consulta['numero'] ?? 'S/N') . ' - ' . htmlspecialchars($consulta['complemento'] ?? '') . '<br>';
    $html .= htmlspecialchars($consulta['bairro'] ?? '') . '<br>';
    $html .= htmlspecialchars($consulta['municipio'] ?? '') . ' - ' . htmlspecialchars($consulta['uf'] ?? '') . '<br>';
    $html .= 'CEP: ' . htmlspecialchars($consulta['cep'] ?? '') . '<br>';
    $html .= 'País: ' . htmlspecialchars($consulta['pais'] ?? '');
    $html .= '</address>';

    $html .= '<h5 class="mt-3"><i class="fas fa-phone mr-2"></i>Contato</h5><dl class="row">';
    $html .= render_detail('E-mail', $consulta['email']);
    $html .= render_detail('Telefone 1', $consulta['ddd_telefone_1']);
    $html .= render_detail('Telefone 2', $consulta['ddd_telefone_2']);
    $html .= render_detail('Fax', $consulta['ddd_fax']);
    $html .= '</dl>';
    $html .= '</div>';
    $html .= '</div><hr>'; // Fim da row

    // QSA
    if (!empty($consulta['qsa'])) {
        $qsa = json_decode($consulta['qsa'], true);
        if ($qsa && json_last_error() === JSON_ERROR_NONE) {
            $html .= '<h5><i class="fas fa-users mr-2"></i>Quadro de Sócios e Administradores (QSA)</h5>';
            $html .= '<div class="table-responsive" style="max-height: 250px; overflow-y: auto;"><table class="table table-sm table-bordered table-striped mb-0"><thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>Qualificação</th><th>Participação</th><th>País Origem</th><th>Data Entrada</th><th>Rep. Legal</th></tr></thead><tbody>';
            foreach ($qsa as $socio) {
                $html .= '<tr>';
                $html .= '<td>'.htmlspecialchars($socio['nome_socio'] ?? 'N/A').'</td>';
                $html .= '<td>'.htmlspecialchars($socio['cnpj_cpf_do_socio'] ?? 'N/A').'</td>';
                $html .= '<td>'.htmlspecialchars($socio['qualificacao_socio'] ?? 'N/A').'</td>';
                $html .= '<td>'.(isset($socio['percentual_capital_social']) ? htmlspecialchars($socio['percentual_capital_social']) . '%' : 'N/A').'</td>';
                $html .= '<td>'.htmlspecialchars($socio['pais'] ?? 'N/A').'</td>';
                $html .= '<td>'.(!empty($socio['data_entrada_sociedade']) ? htmlspecialchars(date('d/m/Y', strtotime($socio['data_entrada_sociedade']))) : 'N/A').'</td>';
                $html .= '<td>'.htmlspecialchars($socio['nome_representante_legal'] ?? 'N/A').'</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div><hr>';
        }
    }

    // CNAEs Secundários
    if (!empty($consulta['cnaes_secundarios'])) {
        $cnaes = json_decode($consulta['cnaes_secundarios'], true);
        if ($cnaes && json_last_error() === JSON_ERROR_NONE) {
            $html .= '<h5><i class="fas fa-list-ol mr-2"></i>CNAEs Secundários</h5>';
            $html .= '<ul class="list-group list-group-flush">';
            foreach ($cnaes as $cnae) {
                $html .= '<li class="list-group-item py-1">' . htmlspecialchars($cnae['codigo'] ?? 'N/A') . ' - ' . htmlspecialchars($cnae['descricao'] ?? 'N/A') . '</li>';
            }
            $html .= '</ul><hr>';
        }
    }
    
    $html .= '</div>'; // Fim do container-fluid
    echo $html;

} catch (PDOException $e) {
    error_log("Erro em ajax_get_consulta.php: " . $e->getMessage());
    exit_with_error('Ocorreu um erro interno ao buscar os dados da consulta. Avise o administrador.');
}
