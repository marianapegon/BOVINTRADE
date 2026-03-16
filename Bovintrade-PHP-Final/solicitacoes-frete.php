<?php
session_start();
require_once  'config.php'; // caminho da conexão PDO

// Verifica se a transportadora está logada
if (!isset($_SESSION['transportadora_id'])) {
    header("Location: login.php");
    exit;
}

$transportadora_id = $_SESSION['transportadora_id'];

$sql = "SELECT sf.*, p.id as pedido_id, f.nome as fazenda_nome, 
               fr.razao_social as frigorifico_nome, lb.quantidade
        FROM solicitacoes_frete sf
        JOIN pedidos p ON sf.pedido_id = p.id
        JOIN lote_bois lb ON p.lote_id = lb.id
        JOIN fazenda f ON lb.fazenda_id = f.id
        JOIN frigorifico fr ON p.frigorifico_id = fr.id
        WHERE sf.status = 'aguardando_propostas'
        AND sf.data_limite_propostas > NOW()
        AND lb.quantidade BETWEEN 
            (SELECT MIN(capacidade_minima) FROM veiculo WHERE transportadora_id = ?) 
            AND (SELECT MAX(capacidade_maxima) FROM veiculo WHERE transportadora_id = ?)";

$stmt = $conn->prepare($sql);
$stmt->execute([$transportadora_id, $transportadora_id]);
$solicitacoes = $stmt->fetchAll();
?>

<!-- Listar solicitações -->
<?php foreach($solicitacoes as $sol): ?>
<div class="solicitacao-card">
    <h3>Frete: <?= htmlspecialchars($sol['fazenda_nome']) ?> → <?= htmlspecialchars($sol['frigorifico_nome']) ?></h3>
    <p>Quantidade: <?= (int)$sol['quantidade'] ?> cabeças</p>
    <p>Prazo para proposta: <?= date('d/m/Y H:i', strtotime($sol['data_limite_propostas'])) ?></p>
    <a href="enviar-proposta.php?solicitacao_id=<?= $sol['id'] ?>" class="btn">Enviar Proposta</a>
</div>
<?php endforeach; ?>
