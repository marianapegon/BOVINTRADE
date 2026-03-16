<?php
// salvar-avaliacao.php — versão ajustada para BovinTrade (mysqli)
session_start();
require_once 'conexao.php'; // $conn (mysqli)

// ===============================================================
// 1) FUNÇÃO: Buscar o contexto do pedido_item
// ===============================================================
function getPedidoItemContext(mysqli $conn, int $pedidoItemId): ?array {
  // Aqui usamos a tabela 'transportes' real do seu BD, que amarra pedido, fazenda, frigorífico e transportadora.
  $sql = "
    SELECT 
      pi.id AS pedido_item_id,
      pi.lote_id,
      pi.fazenda_id,
      p.id AS pedido_id,
      p.frigorifico_id,
      p.status AS status_pedido,
      t.transportadora_id,
      t.status AS status_transporte
    FROM pedido_itens pi
    JOIN pedidos p       ON p.id = pi.pedido_id
    LEFT JOIN transportes t 
           ON t.pedido_id = p.id
          AND t.fazenda_id = pi.fazenda_id
          AND t.frigorifico_id = p.frigorifico_id
    WHERE pi.id = ?
    LIMIT 1
  ";

  $st = $conn->prepare($sql);
  $st->bind_param('i', $pedidoItemId);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  return $row ?: null;
}

// ===============================================================
// 2) FUNÇÃO: Validar se o usuário pode avaliar
// ===============================================================
function podeAvaliar(array $ctx, string $atorTipo, int $atorId, string $alvoTipo, int $alvoId): array {
  $pedidoOk = (strtoupper($ctx['status_pedido']) === 'PAGO');
  $transpOk = (strtoupper($ctx['status_transporte']) === 'ENTREGUE');

  if (!$pedidoOk || !$transpOk) {
    return [false, 'Avaliação liberada apenas após pagamento e transporte entregue.'];
  }

  // Confirma participação do ator
  $participou = false;
  if ($atorTipo === 'FRIGORIFICO' && (int)$ctx['frigorifico_id'] === $atorId) $participou = true;
  if ($atorTipo === 'FAZENDA'     && (int)$ctx['fazenda_id'] === $atorId) $participou = true;
  if ($atorTipo === 'TRANSPORTADORA' && (int)$ctx['transportadora_id'] === $atorId) $participou = true;
  if (!$participou) {
    return [false, 'Você não participou desta transação.'];
  }

  // Regras específicas por tipo
  switch ($atorTipo) {
case 'FRIGORIFICO':
  // Frigorífico pode avaliar FAZENDA (vendedora) e TRANSPORTADORA do mesmo item

  // Variação camelCase (se sua função recebe $alvoTipo e $alvoId)
  if (isset($alvoTipo, $alvoId)) {
    if ($alvoTipo === 'FAZENDA'        && (int)$ctx['fazenda_id']        === (int)$alvoId) return [true, ''];
    if ($alvoTipo === 'TRANSPORTADORA' && !empty($ctx['transportadora_id']) && (int)$ctx['transportadora_id'] === (int)$alvoId) return [true, ''];
    return [false, 'Frigorífico só pode avaliar a fazenda vendedora e a transportadora deste item.'];
  }

  // Variação snake_case (se sua função recebe $alvo_tipo e $alvo_id)
  if (isset($alvo_tipo, $alvo_id)) {
    if ($alvo_tipo === 'FAZENDA'        && (int)$ctx['fazenda_id']        === (int)$alvo_id) return [true, ''];
    if ($alvo_tipo === 'TRANSPORTADORA' && !empty($ctx['transportadora_id']) && (int)$ctx['transportadora_id'] === (int)$alvo_id) return [true, ''];
    return [false, 'Frigorífico só pode avaliar a fazenda vendedora e a transportadora deste item.'];
  }

  // Se chegou aqui, algo não bateu
  return [false, 'Parâmetros de alvo inválidos.'];

    case 'FAZENDA':
      // Pode avaliar o FRIGORÍFICO comprador e a TRANSPORTADORA que fez o transporte
      if ($alvoTipo === 'FRIGORIFICO' && (int)$ctx['frigorifico_id'] === $alvoId) return [true, ''];
      if ($alvoTipo === 'TRANSPORTADORA' && (int)$ctx['transportadora_id'] === $alvoId) return [true, ''];
      return [false, 'Fazenda só pode avaliar o frigorífico comprador e a transportadora deste lote.'];

    case 'TRANSPORTADORA':
      // Pode avaliar a FAZENDA e o FRIGORÍFICO dessa viagem
      if ($alvoTipo === 'FAZENDA' && (int)$ctx['fazenda_id'] === $alvoId) return [true, ''];
      if ($alvoTipo === 'FRIGORIFICO' && (int)$ctx['frigorifico_id'] === $alvoId) return [true, ''];
      return [false, 'Transportadora só pode avaliar a fazenda e o frigorífico desta entrega.'];

    default:
      return [false, 'Tipo de usuário inválido.'];
  }
}

// ===============================================================
// 3) RECEBE OS DADOS DO FORMULÁRIO
// ===============================================================
$pedido_item_id = (int)($_POST['pedido_item_id'] ?? 0);
$alvo_tipo      = strtoupper(trim($_POST['alvo_tipo'] ?? ''));
$alvo_id        = (int)($_POST['alvo_id'] ?? 0);
$nota_geral     = (int)($_POST['nota_geral'] ?? 0);
$comentario     = trim($_POST['comentario'] ?? '');
$metricas       = $_POST['metricas'] ?? [];

$ator_tipo = $_SESSION['usuario']['tipo_usuario'] ?? ''; // conforme seu sistema de sessão
$ator_id   = (int)($_SESSION['usuario']['id'] ?? 0);

if (!$pedido_item_id || !$alvo_tipo || !$alvo_id || !$nota_geral || !$ator_tipo || !$ator_id) {
  echo "<script>alert('Dados incompletos.');history.back();</script>"; exit;
}

// ===============================================================
// 4) CONTEXTO DO ITEM
// ===============================================================
$ctx = getPedidoItemContext($conn, $pedido_item_id);
if (!$ctx) {
  echo "<script>alert('Item de pedido não encontrado.');history.back();</script>"; exit;
}

// ===============================================================
// 5) VALIDA PERMISSÃO
// ===============================================================
list($ok, $msg) = podeAvaliar($ctx, $ator_tipo, $ator_id, $alvo_tipo, $alvo_id);
if (!$ok) {
  echo "<script>alert('".$msg."');history.back();</script>"; exit;
}

// ===============================================================
// 6) EVITA DUPLICIDADE
// ===============================================================
$sqlDup = "
  SELECT 1 
    FROM avaliacao 
   WHERE pedido_item_id=? 
     AND alvo_tipo=? 
     AND alvo_id=? 
     AND avaliador_tipo=? 
     AND avaliador_id=? 
   LIMIT 1";
$stDup = $conn->prepare($sqlDup);
$stDup->bind_param('isssi', $pedido_item_id, $alvo_tipo, $alvo_id, $ator_tipo, $ator_id);
$stDup->execute();
if ($stDup->get_result()->num_rows > 0) {
  echo "<script>alert('Você já avaliou este alvo nesta transação.');history.back();</script>"; exit;
}

// ===============================================================
// 7) INSERE A AVALIAÇÃO E AS MÉTRICAS
// ===============================================================
// ... (código anterior igual)

$conn->begin_transaction();
try {
  $sqlIns = "
    INSERT INTO avaliacao 
      (pedido_id, pedido_item_id, alvo_tipo, alvo_id, avaliador_tipo, avaliador_id, nota_geral, comentario, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
  ";

  $pedido_id = (int)$ctx['pedido_id'];
  $st = $conn->prepare($sqlIns);
  $st->bind_param('iisssiss', $pedido_id, $pedido_item_id, $alvo_tipo, $alvo_id, $ator_tipo, $ator_id, $nota_geral, $comentario);
  $st->execute();
  $avaliacao_id = $st->insert_id;

  if (is_array($metricas) && count($metricas)) {
    $sqlM = "INSERT INTO avaliacao_metrica (avaliacao_id, metrica_codigo, nota, peso) VALUES (?, ?, ?, 1.0)";
    $stm = $conn->prepare($sqlM);
    foreach ($metricas as $codigo => $nota) {
      $codigo = trim($codigo);
      $nota   = (int)$nota;
      if ($codigo !== '' && $nota >= 1 && $nota <= 5) {
        $stm->bind_param('isi', $avaliacao_id, $codigo, $nota);
        $stm->execute();
      }
    }
  }

  $conn->commit();

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'msg' => 'Avaliação enviada com sucesso!',
    'pedido_item_id' => $pedido_item_id,
    'alvo_tipo' => $alvo_tipo,
    'alvo_id' => $alvo_id,
  ], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'msg' => 'Erro ao salvar a avaliação.',
    'detalhe' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
