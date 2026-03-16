<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    header("Location: login.php"); exit;
}
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])){
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("UPDATE transportes SET status = 'ACEITO' WHERE id = :id");
    $stmt->execute([':id'=>$id]);
}
header("Location: pedidos-pendentes.php");
exit;
