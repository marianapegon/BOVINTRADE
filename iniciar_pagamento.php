<?php
// iniciar_pagamento.php (Convertido para PDO)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Usamos config.php para a conexão PDO
require_once  'config.php'; 

// === Utilitary Functions ===
function fail($msg, $fallback='formas_pagamento.php'){
    $_SESSION['flash_error'] = $msg;
    header("Location: $fallback"); 
    exit;
}

// Proteção de rota (Mantida)
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if ($u['tipo_usuario'] === 'FAZENDA')      { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

$metodo = $_POST['metodo'] ?? 'PIX';
if (!in_array($metodo, ['PIX','CARTAO'], true)) $metodo = 'PIX';

$carrinho = $_SESSION['carrinho'] ?? [];
if (empty($carrinho)) fail('Carrinho vazio.', 'meu-carrinho.php');

$selecionados = $_SESSION['checkout_selecionados'] ?? [];
if (empty($selecionados)) {
    // assume todos
    foreach ($carrinho as $k => $it) $selecionados[] = (string)($it['id'] ?? $k);
    $_SESSION['checkout_selecionados'] = $selecionados;
}

$usuario = $_SESSION['usuario'] ?? null; 

// --- Obter Totais (Frete e Subtotal) da Sessão ---
$total_frete = $_SESSION['frete_total'] ?? 0.0;
$total_itens = $_SESSION['subtotal'] ?? 0.0;
$total_pedido = $total_itens + $total_frete;

// === Monta Itens Selecionados ===
$itens = [];
$total_verif = 0.0; // Recálculo no servidor
foreach ($carrinho as $k => $raw) {
    $rowId = (string)($raw['id'] ?? $k);
    if (!in_array($rowId, $selecionados, true)) continue;

    $idLote    = (int)($raw['id_lote'] ?? $raw['id'] ?? 0);
    $fazenda   = (int)($raw['fazenda_id'] ?? 0);
    $codigo    = (string)($raw['codigo_lote'] ?? '');
    $qCab      = (int)($raw['quantidade'] ?? 1);
    $pUnit     = (float)($raw['preco'] ?? 0);
    $pTotal    = $qCab * $pUnit;

    if ($idLote<=0 || $fazenda<=0 || $pTotal<=0) fail('Item inválido na seleção.');

    $itens[] = compact('rowId','idLote','fazenda','codigo','qCab','pUnit','pTotal');
    $total_verif += $pTotal;
}

// Verifica se o total calculado bate com o subtotal da sessão (segurança)
if (abs($total_verif - $total_itens) > 0.01) fail('Erro de cálculo no checkout. Tente novamente no carrinho.', 'meu-carrinho.php');
if (empty($itens)) fail('Nenhum item selecionado.', 'meu-carrinho.php');


// === INÍCIO DA TRANSAÇÃO (PDO) ===
$pdo->beginTransaction();

try {
    // 1. Limpa reservas expiradas (PDO) - Garantir que o lote esteja disponível antes de reservar
    // ALTERAÇÃO: Adicionado o tempo limite de expiração explícito (30 minutos)
    $pdo->query("DELETE FROM reservas_lote WHERE expira_em IS NOT NULL AND expira_em < NOW()");
    
    // ALTERAÇÃO: Simplificado o UPDATE para liberar lotes EM_NEGOCIACAO sem reserva ativa
    $pdo->query("
        UPDATE lote_bois lb
        LEFT JOIN reservas_lote r ON r.lote_id = lb.id
        SET lb.status='DISPONIVEL', lb.updated_at=NOW()
        WHERE lb.status='EM_NEGOCIACAO' AND (r.lote_id IS NULL OR r.expira_em < NOW())
    ");

    // 2. Cria o Pedido (PDO)
    $frigoId = (int)($usuario['id'] ?? 0);
    $statusPedido = 'AGUARDANDO_PAGAMENTO';
    $zero = 0.00; // Desconto e Frete já estão no total_pedido

    $stmt = $pdo->prepare("INSERT INTO pedidos (frigorifico_id, status, total_itens, desconto, frete, total_pedido)
                             VALUES (?,?,?,?,?,?)");
    $stmt->execute([$frigoId, $statusPedido, $total_itens, $zero, $total_frete, $total_pedido]);
    $pedidoId = $pdo->lastInsertId();

    // 3. Insere Itens do Pedido + Bloqueio + Reserva (PDO)
    // prepara statements (antes do foreach)
    $stmtItem = $pdo->prepare("INSERT INTO pedido_itens
        (pedido_id, lote_id, fazenda_id, codigo_lote, quantidade_cabecas, preco_unitario_cab, valor_total, status)
        VALUES (?,?,?,?,?,?,?, 'RESERVADO')");

    $stmtBloq = $pdo->prepare("UPDATE lote_bois SET status='EM_NEGOCIACAO', updated_at=NOW() WHERE id=? AND status='DISPONIVEL'");

    $stmtRes = $pdo->prepare("INSERT INTO reservas_lote (lote_id, pedido_id, expira_em) VALUES (?,?,?)");

    $stmtCheckReserva = $pdo->prepare("
        SELECT p.id AS pedido_reserva, p.frigorifico_id
        FROM reservas_lote r
        JOIN pedidos p ON p.id = r.pedido_id
        WHERE r.lote_id = ? AND r.expira_em > NOW()
        LIMIT 1
    ");

    $stmtAtualizaReserva = $pdo->prepare("
        UPDATE reservas_lote
        SET pedido_id = ?, expira_em = ?
        WHERE lote_id = ? AND expira_em > NOW()
    ");

    $expira = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s'); // reserva expira em 30 min

    foreach ($itens as $it) {
        $loteId    = $it['idLote'];
        $fazendaId = $it['fazenda'];
        $codigo    = $it['codigo'];
        $qCab      = $it['qCab'];
        $pUnit     = $it['pUnit'];
        $pTotal    = $it['pTotal'];

        // Insere item do pedido (mantém como antes)
        $stmtItem->execute([$pedidoId, $loteId, $fazendaId, $codigo, $qCab, $pUnit, $pTotal]);

        // 1) Checa se existe reserva ativa para este lote
        $stmtCheckReserva->execute([$loteId]);
        $reservaAtiva = $stmtCheckReserva->fetch(PDO::FETCH_ASSOC);

        if ($reservaAtiva) {
            // reserva existe: verifica a quem pertence o pedido que originou a reserva
            $pedido_reserva_id = (int)$reservaAtiva['pedido_reserva'];
            $pedido_reserva_frigo = (int)$reservaAtiva['frigorifico_id'];

            if ($pedido_reserva_frigo !== $frigoId) {
                // reserva pertence a outro frigorífico -> erro
                throw new Exception("Lote {$codigo} ({$loteId}) indisponível para negociação. Reservado por outro frigorífico.");
            }

            // reserva pertence ao mesmo frigorífico:
            // atualiza a reserva para apontar para o novo pedido (mantém o tempo de expiração)
            $stmtAtualizaReserva->execute([$pedidoId, $expira, $loteId]);

            // também garante que o status do lote esteja 'EM_NEGOCIACAO'
            $pdo->prepare("UPDATE lote_bois SET status='EM_NEGOCIACAO', updated_at=NOW() WHERE id=?")
                ->execute([$loteId]);

            // segue para próximo item (já reservado para este frigorífico)
            continue;
        }

        // 2) Sem reserva ativa: tenta bloquear o lote (só se estiver DISPONIVEL)
        $stmtBloq->execute([$loteId]);

        if ($stmtBloq->rowCount() !== 1) {
            // não conseguiu bloquear -> provavelmente outro frigo pegou entre a checagem e agora
            throw new Exception("Lote {$codigo} ({$loteId}) indisponível para negociação. Ele pode ter sido reservado recentemente por outro frigorífico.");
        }

        // 3) Cria reserva (novo pedido)
        $stmtRes->execute([$loteId, $pedidoId, $expira]);
    }

    // 4. Cria o Registro de Pagamento (PDO)
    $pStatus = 'PENDENTE';
    $moeda   = 'BRL';
    $ref     = 'SIM-'.$pedidoId.'-'.substr(md5($pedidoId.$total_pedido),0,6);
    $expiraPg= ($metodo==='PIX') ? (new DateTime('+30 minutes'))->format('Y-m-d H:i:s') : null;

    $payload = json_encode(['metodo'=>$metodo,'total'=>$total_pedido], JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("INSERT INTO pagamentos (pedido_id, metodo, status, valor, moeda, referencia_externa, payload, expiracao_em)
                             VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$pedidoId, $metodo, $pStatus, $total_pedido, $moeda, $ref, $payload, $expiraPg]);
    $pagId = $pdo->lastInsertId();

    // 5. PIX: guarda “copia e cola” no BD (simulado) (PDO)
    if ($metodo === 'PIX') {
        $chave = 'CHAVE-PIX-PLATAFORMA';
        $copia = "BRPAY|PED={$pedidoId}|VAL=".number_format($total_pedido,2,'.','')."|REF=".strtoupper(substr(sha1($ref),0,12));
        $qr    = $copia; // para simulação
        $stmt = $pdo->prepare("INSERT INTO pagamentos_pix (pagamento_id, pagador_id, chave_destino, qr_code, copia_cola)
                                 VALUES (?,?,?,?,?)");
        $pagador = (int)($usuario['id'] ?? 0);
        $stmt->execute([$pagId, $pagador, $chave, $qr, $copia]);
    }

    $pdo->commit();

    // 6. Redireciona para a página do método
    if ($metodo === 'PIX') {
        header('Location: pagar_pix.php?pedido='.$pedidoId.'&pag='.$pagId);
    } else {
        header('Location: pagar_cartao.php?pedido='.$pedidoId.'&pag='.$pagId);
    }
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        // O rollback garante que o status do lote volte para DISPONIVEL (se ele estava)
        $pdo->rollBack();
    }
    error_log("Falha no checkout: ".$e->getMessage());
    fail('Falha ao iniciar pagamento: '.$e->getMessage(), 'meu-carrinho.php');
}