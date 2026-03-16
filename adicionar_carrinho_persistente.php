<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'config.php';

if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}

$frigoId = (int)$_SESSION['usuario']['id'];
$loteId = (int)$_POST['lote_id'];
$qtd = max(1, (int)$_POST['quantidade']);

// Verifica lote disponível
$stmt = $pdo->prepare("SELECT id, codigo_lote, fazenda_id, preco FROM lote_bois WHERE id=? AND status='DISPONIVEL'");
$stmt->execute([$loteId]);
$lote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lote) {
    $_SESSION['flash_error'] = 'Lote indisponível.';
    header('Location: ver-lote.php?id='.$loteId); exit;
}

// Upsert no carrinho persistente
$stmt = $pdo->prepare("
    INSERT INTO carrinho_persistente (frigorifico_id, lote_id, quantidade, preco_unitario)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        quantidade = ?, preco_unitario = ?, atualizado_em = NOW()
");
$stmt->execute([
    $frigoId, $loteId, $qtd, $lote['preco'],
    $qtd, $lote['preco']
]);

$_SESSION['flash_success'] = 'Lote adicionado ao carrinho!';
header('Location: meu-carrinho.php');
exit;