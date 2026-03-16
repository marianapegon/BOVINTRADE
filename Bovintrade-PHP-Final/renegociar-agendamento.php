<?php
session_start();
require_once  'config.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    header("Location: login.php"); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transporte_id = (int)$_POST['transporte_id'];
    $novo_valor = (float)$_POST['novo_valor'];
    $mensagem = trim($_POST['mensagem']);

    if ($novo_valor > 0) {
        $stmt = $pdo->prepare("
            UPDATE transportes
            SET valor_transporte = :valor,
                mensagem_frigorifico = :msg,
                status_aceite = 'RENOVACAO'
            WHERE id = :tid
        ");
        $stmt->execute([
            ':valor' => $novo_valor,
            ':msg' => $mensagem,
            ':tid' => $transporte_id
        ]);
        $msg = "Proposta enviada para a transportadora!";
    } else {
        $msg = "Informe um valor válido.";
    }
}

header("Location: pedidos-pendentes.php");
exit;
