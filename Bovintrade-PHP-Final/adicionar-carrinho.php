<?php
// adicionar-carrinho.php
require 'conexao.php';
session_start();

// === Proteção de rota ===
if (empty($_SESSION['usuario'])) {
    $_SESSION['flash_error'] = 'Faça login para adicionar ao carrinho.';
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    $_SESSION['flash_error'] = 'Acesso não autorizado.';
    header('Location: login.php'); exit;
}

// === Recebe e valida ID do lote ===
$id = (int)($_POST['id_lote'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Lote inválido.';
    header('Location: pesquisa-lotes.php'); exit;
}

// === Busca lote DISPONÍVEL (sem bloquear ainda) ===
$sql = "SELECT * FROM lote_bois WHERE id = ? AND status = 'DISPONIVEL' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$lote = $result->fetch_assoc();
$stmt->close();

if (!$lote) {
    $_SESSION['flash_error'] = 'Lote não encontrado ou já reservado.';
    header('Location: pesquisa-lotes.php'); exit;
}

// === Monta item do carrinho (sem alterar status do lote) ===
$item = [
    'id' => (string)$lote['id'],
    'id_lote' => (int)$lote['id'],
    'codigo_lote' => $lote['codigo_lote'],
    'quantidade' => (int)$lote['quantidade'],
    'preco' => (float)$lote['preco'],
    'preco_total' => (int)$lote['quantidade'] * (float)$lote['preco'],
    'raca' => $lote['raca'],
    'peso_medio_kg' => (float)$lote['peso_medio_kg'],
    'tipo_alimentacao' => $lote['tipo_alimentacao'] ?? '',
    'localizacao' => $lote['localizacao'],
    'descricao' => $lote['descricao'] ?? '',
    'fazenda_id' => (int)$lote['fazenda_id'],
    'fazenda' => '',
    'imagem' => 'img-placeholder.png',
];

// === Adiciona ao carrinho na sessão ===
$_SESSION['carrinho'] = $_SESSION['carrinho'] ?? [];
$existe = false;

foreach ($_SESSION['carrinho'] as $k => $it) {
    if (($it['id_lote'] ?? 0) == $item['id_lote']) {
        // Atualiza (substitui) se já existe
        $_SESSION['carrinho'][$k] = $item;
        $existe = true;
        break;
    }
}

if (!$existe) {
    $_SESSION['carrinho'][] = $item;
}

// === Mensagem de sucesso ===
$_SESSION['flash_success'] = "Lote {$lote['codigo_lote']} adicionado ao carrinho!";

// === Redireciona ===
header('Location: meu-carrinho.php');
exit;