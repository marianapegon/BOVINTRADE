<?php
session_start();
require_once 'config.php'; // Conexão PDO 

// Verificação de segurança: AGORA EXIGE transportadora_id E pedido_id
if (!isset($_SESSION['usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['transportadora_id']) || empty($_POST['pedido_id'])) { 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Acesso não autorizado ou dados ausentes. Selecione Pedido e Transportadora.']);
    exit;
}

$transportadora_id = (int)$_POST['transportadora_id'];
$pedido_id = (int)$_POST['pedido_id'];
$html_motoristas = '<option value="">-- Selecione um motorista disponível --</option>';
$html_veiculos = '<option value="">-- Selecione um veículo disponível --</option>';
$error_msg = null;

try {
    // 0. CALCULAR CAPACIDADE NECESSÁRIA DO PEDIDO
    // ⚠️ CORRIGIDO: Usando 'lb.quantidade'
    $get_capacidade_necessaria = $pdo->prepare("
        SELECT SUM(lb.quantidade) AS total_bois
        FROM pedido_itens pi
        INNER JOIN lote_bois lb ON lb.id = pi.lote_id
        WHERE pi.pedido_id = ?
    ");
    $get_capacidade_necessaria->execute([$pedido_id]);
    $capacidade_necessaria = (int)($get_capacidade_necessaria->fetchColumn() ?? 0);

    if ($capacidade_necessaria === 0) {
        throw new Exception("Não foi possível calcular a quantidade de bois do pedido. (ID Pedido: {$pedido_id})");
    }

    // --- LÓGICA DE MOTORISTAS ---
    
    // 1. Buscar IDs de motoristas OCUPADOS desta transportadora
    $stmt_busy_m = $pdo->prepare("
        SELECT DISTINCT motorista_id FROM transportes
        WHERE transportadora_id = ? 
        AND status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO')
    ");
    $stmt_busy_m->execute([$transportadora_id]);
    $busy_motorista_ids = $stmt_busy_m->fetchAll(PDO::FETCH_COLUMN, 0);

    // 2. Buscar motoristas DISPONÍVEIS
    $sql_motoristas = "
        SELECT m.id, m.nome 
        FROM motorista m
        JOIN transportadora_motorista tm ON m.id = tm.motorista_id
        WHERE tm.transportadora_usuario_id = ? 
          AND m.ativo = 1
    ";
    
    $params_motoristas = [$transportadora_id];
    
    if (!empty($busy_motorista_ids)) {
        $placeholders = implode(',', array_fill(0, count($busy_motorista_ids), '?'));
        $sql_motoristas .= " AND m.id NOT IN ($placeholders)";
        $params_motoristas = array_merge($params_motoristas, $busy_motorista_ids);
    }
    
    $sql_motoristas .= " ORDER BY m.nome ASC";
    
    $stmt_motoristas = $pdo->prepare($sql_motoristas);
    $stmt_motoristas->execute($params_motoristas);
    
    while ($row = $stmt_motoristas->fetch(PDO::FETCH_ASSOC)) {
        $html_motoristas .= '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['nome']) . '</option>';
    }

    // --- LÓGICA DE VEÍCULOS ---
    
    // 3. Buscar IDs de veículos OCUPADOS desta transportadora
    $stmt_busy_v = $pdo->prepare("
        SELECT DISTINCT veiculo_id FROM transportes
        WHERE transportadora_id = ? 
        AND status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO')
    ");
    $stmt_busy_v->execute([$transportadora_id]);
    $busy_veiculo_ids = $stmt_busy_v->fetchAll(PDO::FETCH_COLUMN, 0);

    // 4. Buscar veículos DISPONÍVEIS (Capacidade MÍNIMA: $capacidade_necessaria)
    $sql_veiculos = "
        SELECT v.id, v.placa, v.modelo, v.tipo, v.capacidade_max 
        FROM veiculo v
        JOIN transportadora_veiculo tv ON v.id = tv.veiculo_id
        WHERE tv.transportadora_usuario_id = ? 
          AND v.ativo = 1
          AND v.capacidade_max >= ? 
    ";
    
    // ⚠️ Parâmetros FIXOS (transportadora_id e capacidade_necessaria) vêm primeiro.
    $params_veiculos = [$transportadora_id, $capacidade_necessaria];
    
    if (!empty($busy_veiculo_ids)) {
        $placeholders = implode(',', array_fill(0, count($busy_veiculo_ids), '?'));
        $sql_veiculos .= " AND v.id NOT IN ($placeholders)";
        // Junta todos os parâmetros, mantendo os fixos na frente.
        $params_veiculos = array_merge($params_veiculos, $busy_veiculo_ids);
    }

    $sql_veiculos .= " ORDER BY v.placa ASC";
    
    $stmt_veiculos = $pdo->prepare($sql_veiculos);
    $stmt_veiculos->execute($params_veiculos);
    
    if ($stmt_veiculos->rowCount() === 0) {
        $html_veiculos = '<option value="">-- Nenhum veículo disponível (Capacidade mínima: ' . $capacidade_necessaria . ' bois) --</option>';
    } else {
        while ($row = $stmt_veiculos->fetch(PDO::FETCH_ASSOC)) {
            $label = htmlspecialchars($row['placa'] . ' - ' . ($row['modelo'] ?: $row['tipo']) . ' (Cap: ' . $row['capacidade_max'] . ')');
            $html_veiculos .= '<option value="' . htmlspecialchars($row['id']) . '" data-capacidade="' . $row['capacidade_max'] . '">' . $label . '</option>';
        }
    }


} catch (PDOException $e) {
    error_log("Erro PDO em fetch_motoristas_veiculos.php: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode());
    $error_msg = "Erro de banco de dados: " . $e->getMessage();
} catch (Exception $e) {
    $error_msg = "Erro interno: " . $e->getMessage();
}

// Retorna o JSON para o jQuery
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'motoristas' => $html_motoristas,
    'veiculos' => $html_veiculos,
    'error' => $error_msg
]);
exit;
?>