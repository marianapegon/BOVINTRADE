<?php
session_start();
require_once 'config.php';

// Proteção: só frigorífico pode excluir
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    header("Location: login.php"); exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])){
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM transportes WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

header("Location: pedidos-pendentes.php");
exit;
