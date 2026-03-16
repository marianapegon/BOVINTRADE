<?php
// detalhes-pedido.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php';

$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['tipo_usuario'] ?? '') !== 'FRIGORIFICO') { http_response_code(403); exit('Acesso negado'); }

$pedido_id = (int)($_GET['id'] ?? 0);
if ($pedido_id <= 0) { http_response_code(400); exit('ID inválido'); }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts){ return date('d/m/Y H:i', strtotime($ts)); }

// Cabeçalho do pedido + método/status do pagamento
$sql = "
  SELECT p.id, p.created_at, p.total_pedido, p.status,
         pg.metodo AS pagamento_metodo, pg.status AS pagamento_status, pg.created_at AS pagamento_criado
  FROM pedidos p
  LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
  WHERE p.id=? AND p.frigorifico_id=?
  ORDER BY pg.id DESC
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $pedido_id, $usuario['id']);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) { http_response_code(404); exit('Pedido não encontrado'); }

// Itens do pedido com info do lote e da fazenda
$sql = "
  SELECT
    pi.id AS pedido_item_id, pi.codigo_lote, pi.quantidade_cabecas, pi.preco_unitario_cab, pi.valor_total,
    u.id AS fazenda_id, u.nome_razao AS fazenda_nome, u.cidade, u.estado,
    lb.raca, lb.peso_medio_kg, lb.tipo_alimentacao, lb.localizacao, lb.descricao
  FROM pedido_itens pi
  JOIN usuarios u   ON u.id = pi.fazenda_id
  LEFT JOIN lote_bois lb ON lb.id = pi.lote_id
  WHERE pi.pedido_id = ?
  ORDER BY pi.id ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $pedido_id);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function badge($status){
  $map = [
    'PAGO' => ['Concluída','status-completed'],
    'AGUARDANDO_PAGAMENTO' => ['Aguardando pagamento','status-pending'],
    'CANCELADO' => ['Cancelada','status-canceled'],
    'CRIADO' => ['Criado','status-intransit'],
  ];
  $d = $map[$status] ?? [$status, 'status-pending'];
  return '<span class="status-badge '.$d[1].'">'.e($d[0]).'</span>';
}
?>
<style>
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
  .box{border:1px solid #eee;border-radius:8px;padding:12px}
  .title{font-weight:600;margin-bottom:.5rem;color:#a30000}
  .row{display:flex;justify-content:space-between;border-bottom:1px dashed #eee;padding:.35rem 0}
  .muted{color:#666}
  .list{margin-top:.5rem}
  .list li{margin:.25rem 0}
</style>

<div class="grid">
  <div class="box">
    <div class="title"><i class="fas fa-receipt"></i> Dados da Compra</div>
    <div class="row"><span class="muted">Pedido</span><span>#<?= (int)$pedido['id'] ?></span></div>
    <div class="row"><span class="muted">Data</span><span><?= e(dtbr($pedido['created_at'])) ?></span></div>
    <div class="row"><span class="muted">Status</span><span><?= badge($pedido['status']) ?></span></div>
    <div class="row"><span class="muted">Total</span><span><strong><?= brl($pedido['total_pedido']) ?></strong></span></div>
    <?php if ($pedido['pagamento_metodo']): ?>
      <div class="row"><span class="muted">Pagamento</span><span><?= e($pedido['pagamento_metodo']) ?> (<?= e($pedido['pagamento_status']) ?>)</span></div>
    <?php endif; ?>
  </div>

  <div class="box">
    <div class="title"><i class="fas fa-file-invoice"></i> Documentos</div>
    <p class="muted">NF-e ainda não implementada neste MVP.</p>
  </div>
</div>

<div class="box" style="margin-top:1rem">
  <div class="title"><i class="fas fa-cow"></i> Itens / Lotes</div>
  <?php if (!$itens): ?>
    <p>Nenhum item.</p>
  <?php else: ?>
    <?php foreach ($itens as $it): ?>
      <div style="border:1px solid #f0f0f0;border-radius:8px;padding:10px;margin:.5rem 0">
        <div class="row"><span class="muted">Lote</span><span>#<?= e($it['codigo_lote']) ?></span></div>
        <div class="row"><span class="muted">Fazenda</span><span><?= e($it['fazenda_nome']) ?> (<?= e($it['cidade']) ?> - <?= e($it['estado']) ?>)</span></div>
        <div class="row"><span class="muted">Cabeças</span><span><?= (int)$it['quantidade_cabecas'] ?></span></div>
        <div class="row"><span class="muted">Raça</span><span><?= e($it['raca']) ?></span></div>
        <div class="row"><span class="muted">Peso médio</span><span><?= number_format((float)$it['peso_medio_kg'],2,',','.') ?> kg</span></div>
        <div class="row"><span class="muted">Alimentação</span><span><?= e($it['tipo_alimentacao']) ?></span></div>
        <div class="row"><span class="muted">Localização</span><span><?= e($it['localizacao']) ?></span></div>
        <div class="row"><span class="muted">Preço unit.</span><span><?= brl($it['preco_unitario_cab']) ?></span></div>
        <div class="row"><span class="muted">Total do lote</span><span><strong><?= brl($it['valor_total']) ?></strong></span></div>
        <?php if (!empty($it['descricao'])): ?>
          <div style="margin-top:.5rem" class="muted"><?= e($it['descricao']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
