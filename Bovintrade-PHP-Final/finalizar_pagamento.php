<?php
// finalizar_pagamento.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Usa config.php (PDO) para ser consistente com o restante do projeto
require_once 'config.php'; 

// === Funções Utilitárias ===
function back_cart($msg, $type='success'){
    $_SESSION['flash_'.$type] = $msg;
    header('Location: meu-carrinho.php'); 
    exit;
}

// === Coleta de Dados do POST ===
$pedido = (int)($_POST['pedido_id'] ?? 0);
$pag    = (int)($_POST['pagamento_id'] ?? 0);
$acao   = strtoupper(trim($_POST['acao'] ?? '')); // 'APROVADO' ou 'CANCELADO'
$metodo = trim($_POST['metodo'] ?? '');

if (!$pedido || !$pag || !in_array($acao,['APROVADO','CANCELADO'], true)) {
    back_cart('Requisição inválida (IDs ausentes ou ação desconhecida).', 'error');
}


// === INÍCIO DA TRANSAÇÃO (PDO) ===
$pdo->beginTransaction();

try {
    // 1. Consulta Inicial (PDO)
    $stmt = $pdo->prepare("
        SELECT pg.id AS pagamento_id, pg.metodo, pg.status, p.id AS pedido_id, p.total_pedido
        FROM pagamentos pg
        JOIN pedidos p ON p.id = pg.pedido_id
        WHERE p.id=? AND pg.id=? LIMIT 1
    ");
    $stmt->execute([$pedido, $pag]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) throw new Exception('Pedido/pagamento não encontrado.');
    
    // 2. Coleta IDs dos lotes do pedido
    $stmt = $pdo->prepare("SELECT lote_id FROM pedido_itens WHERE pedido_id=?");
    $stmt->execute([$pedido]);
    $lotes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    

    // 3. Processamento de CANCELAMENTO
    if ($acao === 'CANCELADO') {
        // Atualiza status do pagamento e pedido
        $pdo->prepare("UPDATE pagamentos SET status='CANCELADO', updated_at=NOW() WHERE id=?")->execute([$pag]);
        $pdo->prepare("UPDATE pedidos SET status='CANCELADO', updated_at=NOW(), cancelado_em=NOW() WHERE id=?")->execute([$pedido]);
        
        if ($lotes) {
            $in = implode(',', array_fill(0, count($lotes), '?'));
            
            // Reverte o status dos lotes para DISPONIVEL e remove reservas
            $pdo->prepare("UPDATE pedido_itens SET status='CANCELADO', updated_at=NOW() WHERE pedido_id=?")->execute([$pedido]);
            $pdo->prepare("UPDATE lote_bois SET status='DISPONIVEL', updated_at=NOW() WHERE id IN ($in)")->execute($lotes);
            $pdo->prepare("DELETE FROM reservas_lote WHERE lote_id IN ($in)")->execute($lotes);
        }
        $pdo->commit();
        back_cart('Pagamento cancelado. Lotes liberados.', 'info');
    }

    // 4. Processamento de APROVAÇÃO
    if ($row['status'] !== 'PENDENTE') throw new Exception('Pagamento não está pendente ou já foi processado.');
    
    // 5. Se for CARTAO, guarda dados adicionais
    if ($metodo === 'CARTAO') {
        $tok = $_POST['cartao_token'] ?? '';
        $ban = $_POST['bandeira'] ?? '';
        $l4  = $_POST['last4'] ?? '';
        $tit = $_POST['titular_nome'] ?? '';
        $mm  = (int)($_POST['exp_mes'] ?? 0);
        $yy  = (int)($_POST['exp_ano'] ?? 0);
        $auth = 'SIM'.substr(md5($pag.$pedido),0,6);

        $stmt = $pdo->prepare("
            INSERT INTO pagamentos_cartao (pagamento_id, cartao_token, bandeira, last4, titular_nome, exp_mes, exp_ano, autorizacao_codigo)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE cartao_token=VALUES(cartao_token), bandeira=VALUES(bandeira), bandeira=VALUES(bandeira), last4=VALUES(last4),
            titular_nome=VALUES(titular_nome), exp_mes=VALUES(exp_mes), exp_ano=VALUES(exp_ano), autorizacao_codigo=VALUES(autorizacao_codigo)
        ");
        $stmt->execute([$pag, $tok, $ban, $l4, $tit, $mm, $yy, $auth]);
    }

    // 6. Aprova Pagamento (O trigger do banco deve aprovar o Pedido e vender os lotes)
    $pdo->prepare("UPDATE pagamentos SET status='APROVADO', confirmado_em=NOW(), updated_at=NOW() WHERE id=?")->execute([$pag]);
    
    // 7. Remove itens pagos do carrinho
    if (!empty($_SESSION['carrinho']) && !empty($lotes)) {
        foreach ($_SESSION['carrinho'] as $k => $it) {
            $lid = (int)($it['id_lote'] ?? $it['id'] ?? 0);
            if (in_array($lid, $lotes, true)) unset($_SESSION['carrinho'][$k]);
        }
    }

    // === GERAR NOTIFICAÇÃO DE PAGAMENTO APROVADO ===
    $frigorifico_id = $_SESSION['usuario']['id'];
    $titulo = "Pagamento Aprovado";
    $mensagem = "O pagamento do pedido #{$pedido} foi aprovado com sucesso. Total: R$ " . number_format($row['total_pedido'], 2, ',', '.');
    $dados_json = json_encode(['compra_id' => $pedido]);

    $stmt_notif = $pdo->prepare("
        INSERT INTO notificacoes 
        (usuario_id, tipo, titulo, mensagem, dados_json, created_at) 
        VALUES (?, 'PAGAMENTO_RECEBIDO', ?, ?, ?, NOW())
    ");
    $stmt_notif->execute([$frigorifico_id, $titulo, $mensagem, $dados_json]);

    $pdo->commit();
    back_cart('Pagamento realizado com sucesso!', 'success');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Falha no checkout: ".$e->getMessage());
    back_cart('Falha ao finalizar pagamento: '.$e->getMessage(), 'error');
}
?>