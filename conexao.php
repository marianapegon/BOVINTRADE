<?php
$host = "localhost";
$user = "root"; // usuário padrão do XAMPP
$pass = "";     // senha (normalmente em branco no XAMPP)
$db   = "bovintrade_2"; // nome do banco de dados que você criou

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
?>