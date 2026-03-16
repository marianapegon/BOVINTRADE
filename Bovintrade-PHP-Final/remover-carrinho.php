<?php
// remover-carrinho.php?id=123
require 'conexao.php';
session_start();

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
if ($id === '') {
  header('Location: meu-carrinho.php?err=sem_id'); exit;
}

// Remove da sessão
if (!empty($_SESSION['carrinho'])) {
  foreach ($_SESSION['carrinho'] as $k => $it) {
    if ((string)($it['id'] ?? $k) === $id) {
      unset($_SESSION['carrinho'][$k]);
      break;
    }
  }
  $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
}

// Libera o lote no BD (volta para DISPONIVEL)
if (ctype_digit($id)) {
  $intId = (int)$id;
  $up = $conn->prepare("UPDATE lote_bois SET status='DISPONIVEL' WHERE id = ? LIMIT 1");
  $up->bind_param('i', $intId);
  $up->execute();
  $up->close();
}

// Volta para a pesquisa de lotes
header('Location: meu-carrinho.php.');
exit;
