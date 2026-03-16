<?php
// Coloque isso no topo de qualquer página autenticada (ou no login)
if (!empty($_SESSION['usuario']['id'])) {
    $frigoId = (int)$_SESSION['usuario']['id'];

    $stmt = $pdo->prepare("
        SELECT 
            cp.id as cart_id,
            cp.lote_id,
            cp.fazenda_id,
            lb.codigo_lote,
            cp.quantidade,
            cp.preco_unitario as preco
        FROM carrinho_persistente cp
        JOIN lote_bois lb ON lb.id = cp.lote_id
        WHERE cp.frigorifico_id = ? AND lb.status = 'DISPONIVEL'
    ");
    $stmt->execute([$frigoId]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reconstrói o carrinho na sessão
    $_SESSION['carrinho'] = [];
    foreach ($itens as $item) {
        $_SESSION['carrinho'][] = [
            'id' => 'persist_' . $item['cart_id'],
            'id_lote' => $item['lote_id'],
            'fazenda_id' => $item['fazenda_id'],
            'codigo_lote' => $item['codigo_lote'],
            'quantidade' => $item['quantidade'],
            'preco' => $item['preco']
        ];
    }
}