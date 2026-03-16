<?php
$solicitacao_id = $_GET['solicitacao_id'];
$transportadora_id = $_SESSION['transportadora_id'];

// Buscar veículos e motoristas da transportadora
$veiculos = $conn->query("SELECT * FROM veiculo WHERE transportadora_id = $transportadora_id")->fetchAll();
$motoristas = $conn->query("SELECT * FROM motorista WHERE transportadora_id = $transportadora_id")->fetchAll();
?>

<form action="processar-proposta.php" method="POST">
    <input type="hidden" name="solicitacao_id" value="<?= $solicitacao_id ?>">
    
    <label>Valor do Frete (R$):</label>
    <input type="number" name="valor_proposta" step="0.01" required>
    
    <label>Data para Retirada:</label>
    <input type="date" name="data_estimada_retirada" required>
    
    <label>Veículo:</label>
    <select name="veiculo_id" required>
        <?php foreach($veiculos as $veiculo): ?>
        <option value="<?= $veiculo['id'] ?>">
            <?= $veiculo['modelo'] ?> - Capacidade: <?= $veiculo['capacidade_maxima'] ?> cabeças
        </option>
        <?php endforeach; ?>
    </select>
    
    <label>Motorista:</label>
    <select name="motorista_id" required>
        <?php foreach($motoristas as $motorista): ?>
        <option value="<?= $motorista['id'] ?>">
            <?= $motorista['nome'] ?> - CNH: <?= $motorista['cnh'] ?>
        </option>
        <?php endforeach; ?>
    </select>
    
    <button type="submit">Enviar Proposta</button>
</form>