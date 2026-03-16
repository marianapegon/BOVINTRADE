<?php
require_once  'config.php';

if(isset($_POST['transportadora_id'])){
    $transportadora_id = $_POST['transportadora_id'];

    $stmt = $pdo->prepare("
        SELECT m.id, m.nome
        FROM motorista m
        JOIN transportadora_motorista tm ON m.id = tm.motorista_id
        WHERE tm.transportadora_usuario_id = :tid AND m.ativo = 1
    ");
    $stmt->execute([':tid' => $transportadora_id]);
    $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<option value="">-- Selecione --</option>';
    foreach($motoristas as $m){
        echo '<option value="'.$m['id'].'">'.$m['nome'].'</option>';
    }
}
?>
