<?php
// Buscar informações do transporte
$sql = "SELECT rt.*, pf.valor_proposta, t.nome as transportadora, 
               v.modelo as veiculo, m.nome as motorista
        FROM rastreamento_transporte rt
        JOIN propostas_frete pf ON rt.proposta_id = pf.id
        JOIN transportadora t ON pf.transportadora_id = t.id
        JOIN veiculo v ON pf.veiculo_id = v.id
        JOIN motorista m ON pf.motorista_id = m.id
        WHERE pf.id = ?
        ORDER BY rt.data_hora DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$proposta_id]);
$rastreio = $stmt->fetchAll();
?>

<!-- Timeline do transporte -->
<div class="timeline">
    <?php foreach($rastreio as $evento): ?>
    <div class="timeline-event">
        <span class="status"><?= $evento['status'] ?></span>
        <span class="data"><?= date('d/m/Y H:i', strtotime($evento['data_hora'])) ?></span>
        <?php if($evento['observacao']): ?>
        <p class="obs"><?= $evento['observacao'] ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>