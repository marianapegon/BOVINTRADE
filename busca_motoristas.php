<?php
require_once 'config.php'; // conexão PDO

if (!isset($_GET['transportadora_id'])) {
    echo json_encode([]);
    exit;
}

$tid = (int)$_GET['transportadora_id'];

$stmt = $pdo->prepare("
    SELECT m.id, m.nome
    FROM motorista m
    JOIN transportadora_motorista tm 
      ON m.id = tm.motorista_id
    WHERE tm.transportadora_usuario_id = :tid
      AND (tm.data_fim IS NULL OR tm.data_fim >= CURDATE())
");
$stmt->execute([':tid' => $tid]);
$motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($motoristas);
