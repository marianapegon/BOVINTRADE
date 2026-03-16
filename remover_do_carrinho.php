<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once  'config.php';

$cartId = $_POST['cart_id'] ?? '';

// Remove da sessão
$_SESSION['carrinho'] = array_filter($_SESSION['carrinho'], fn($i) => ($i['id'] ?? '') !== $cartId);

// Se for persistente, remove do BD
if (strpos($cartId, 'persist_') === 0) {
    $dbId = (int)str_replace('persist_', '', $cartId);
    $pdo->prepare("DELETE FROM carrinho_persistente WHERE id = ? AND frigorifico_id = ?")
        ->execute([$dbId, $_SESSION['usuario']['id']]);
}

header('Location: meu-carrinho.php');
exit;