<?php
/* ================================
File: config.php
Purpose: Environment/config vars
================================ */

$host = "127.0.0.1";  // ou "localhost"
$usuario = "root";    // usuário do MySQL
$senha = "";          // senha (no XAMPP normalmente é vazio)
$banco = "bovintrade_2"; // nome que você vai dar no Workbench

try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão: " . $e->getMessage());
}
?>