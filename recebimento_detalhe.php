<?php
// recebimento_detalhe.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php';

/* ---------- Autenticação e segurança ---------- */
$usuario = $_SESSION['usuario'] ?? null;
if (!$usuario || ($usuario['tipo_usuario'] ?? '') !== 'FAZENDA') {
    header('Location: login.php?expired=1'); exit;
}
$fazendaId = (int)($usuario['id'] ?? 0);
if ($fazendaId <= 0) { header('Location: login.php?expired=1'); exit; }
$current_page = basename($_SERVER['PHP_SELF']);

$pagamentoId = (int)($_GET['pagamento_id'] ?? 0);
if ($pagamentoId <= 0) {
    http_response_code(400);
    echo 'Parâmetro pagamento_id inválido.'; exit;
}

/* ---------- Helpers ---------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts){
  if (!$ts) return '-';
  $t = is_numeric($ts) ? (int)$ts : strtotime($ts);
  if ($t === false || $t < 0) return '-';
  return date('d/m/Y H:i', $t);
}

/* ---------- Consulta principal (header do pagamento) ----------
   Garante que exista ao menos um repasse da fazenda para este pagamento.
*/
$sqlHeader = "
  SELECT
    pg.id                AS pagamento_id,
    pg.pedido_id,
    pg.metodo,
    pg.status            AS status_pg,
    pg.valor,
    pg.moeda,
    pg.referencia_externa,
    pg.created_at,
    pg.updated_at,
    pg.confirmado_em,
    pg.expiracao_em,

    p.status             AS status_pedido,
    p.frigorifico_id,

    COALESCE(u.nome_razao, CONCAT('Frigorífico #', p.frigorifico_id)) AS frigorifico_nome,
    u.email              AS frigorifico_email,
    u.cnpj               AS frigorifico_cnpj,
    u.telefone           AS frigorifico_telefone,
    u.rua                AS frigorifico_rua,
    u.numero             AS frigorifico_numero,
    u.bairro             AS frigorifico_bairro,
    u.cidade             AS frigorifico_cidade,
    u.estado             AS frigorifico_estado,
    u.cep                AS frigorifico_cep

  FROM pagamentos pg
  JOIN pedidos p ON p.id = pg.pedido_id
  LEFT JOIN usuarios u ON u.id = p.frigorifico_id AND u.tipo_usuario='FRIGORIFICO'
  WHERE pg.id = ?
    AND EXISTS (
      SELECT 1 FROM repasses_fazenda rf
      WHERE rf.pagamento_id = pg.id
        AND rf.fazenda_id   = ?
    )
  LIMIT 1
";
$stmt = $conn->prepare($sqlHeader);
$stmt->bind_param('ii', $pagamentoId, $fazendaId);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$header){
  http_response_code(404);
  echo 'Pagamento não encontrado para esta fazenda.'; exit;
}

/* ---------- Método específico (PIX / CARTÃO) ---------- */
$pix = $cartao = null;
if ($header['metodo'] === 'PIX'){
  $stmt = $conn->prepare("SELECT pagador_id, chave_destino, qr_code, copia_cola FROM pagamentos_pix WHERE pagamento_id = ? LIMIT 1");
  $stmt->bind_param('i', $pagamentoId);
  $stmt->execute();
  $pix = $stmt->get_result()->fetch_assoc();
  $stmt->close();
} elseif ($header['metodo'] === 'CARTAO'){
  $stmt = $conn->prepare("SELECT cartao_token, bandeira, last4, titular_nome, exp_mes, exp_ano, autorizacao_codigo FROM pagamentos_cartao WHERE pagamento_id = ? LIMIT 1");
  $stmt->bind_param('i', $pagamentoId);
  $stmt->execute();
  $cartao = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

/* ---------- Repasses por item (detalhe) + dados do lote ---------- */
$sqlItens = "
  SELECT
    rf.id                AS repasse_id,
    rf.pedido_item_id,
    rf.metodo            AS repasse_metodo,
    rf.status            AS repasse_status,
    rf.valor_bruto,
    rf.taxa_plataforma_percent,
    rf.valor_taxa,
    rf.valor_liquido,
    rf.chave_pix_destino,
    rf.previsto_em,
    rf.pago_em,

    pi.quantidade_cabecas,
    pi.preco_unitario_cab,
    pi.valor_total,

    lb.codigo_lote,
    lb.raca,
    lb.peso_medio_kg

  FROM repasses_fazenda rf
  JOIN pedido_itens pi ON pi.id = rf.pedido_item_id
  LEFT JOIN lote_bois lb ON lb.id = pi.lote_id
  WHERE rf.pagamento_id = ? AND rf.fazenda_id = ?
  ORDER BY rf.id ASC
";
$stmt = $conn->prepare($sqlItens);
$stmt->bind_param('ii', $pagamentoId, $fazendaId);
$stmt->execute();
$resItens = $stmt->get_result();

$itens = [];
$tot_bruto = $tot_taxa = $tot_liq = 0.00;
$tot_cabs = 0;
while ($r = $resItens->fetch_assoc()){
  $itens[] = $r;
  $tot_bruto += (float)$r['valor_bruto'];
  $tot_taxa  += (float)$r['valor_taxa'];
  $tot_liq   += (float)$r['valor_liquido'];
  $tot_cabs  += (int)$r['quantidade_cabecas'];
}
$stmt->close();

/* ---------- Badge/labels ---------- */
$mapStatus = [
  'APROVADO'  => ['label'=>'Recebido','badge'=>'status-received'],
  'PENDENTE'  => ['label'=>'Pendente','badge'=>'status-pending'],
  'EXPIRADO'  => ['label'=>'Atrasado','badge'=>'status-overdue'],
  'RECUSADO'  => ['label'=>'Estornado/Cancelado','badge'=>'status-refunded'],
  'CANCELADO' => ['label'=>'Estornado/Cancelado','badge'=>'status-refunded'],
];
$stInfo = $mapStatus[$header['status_pg']] ?? ['label'=>$header['status_pg'] ?: '-', 'badge'=>'status-pending'];

$email = $usuario['email'] ?? $usuario['login'] ?? $usuario['nome'] ?? 'Minha conta';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Detalhe do Recebimento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root{
      --primary:#a30000; --primary-dark:#7a0000; --text:#333; --text-light:#666;
      --background:#fff; --border:#e0e0e0; --zebra:#f8f9fa; --ok:#155724; --warn:#856404; --err:#721c24;
    }
    *{box-sizing:border-box; margin:0; padding:0}
    html,body{font-family:'Montserrat',sans-serif; background:#f9f9f9; color:#333; height:100%; max-width:100vw; overflow-x:hidden; line-height:1.6}
    header{background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:#fff; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,.1)}
    .logo{font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:.75rem}
    .user-menu{display:flex; align-items:center; gap:1.5rem}
    .user-menu form button{background:none; border:1px solid #fff; color:#fff; padding:.4rem .8rem; border-radius:6px; cursor:pointer}
    .user-avatar{width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center}
    .container{display:flex; min-height:calc(100vh - 76px)}
    .sidebar{width:280px; background:#fff; border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,.05)}
    .sidebar-menu{list-style:none}
    .menu-item{padding:.75rem 1.5rem; display:flex; align-items:center; gap:.75rem; color:#333; text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:.2s}
    .menu-item i{width:24px; text-align:center; color:var(--text-light)}
    .menu-item:hover{background:rgba(163,0,0,.05); color:var(--primary); border-left:3px solid var(--primary)}
    .menu-item.active{background:rgba(163,0,0,.1); color:var(--primary); border-left:3px solid var(--primary)}
    .main{flex:1; padding:2rem; background:#f9f9f9; overflow-y:auto}
    .topbar{display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem}
    .title{font-size:1.6rem; font-weight:600; display:flex; align-items:center; gap:.6rem}
    .actions{display:flex; gap:.5rem; flex-wrap:wrap}
    .btn{padding:.6rem 1rem; border-radius:8px; font-weight:600; cursor:pointer; border:1px solid var(--primary); background:#fff; color:var(--primary); text-decoration:none; display:inline-flex; align-items:center; gap:.5rem}
    .btn:hover{background:rgba(163,0,0,.05)}
    .btn-primary{background:var(--primary); color:#fff}
    .btn-primary:hover{background:var(--primary-dark); color:#fff}
    .grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem}
    .card{background:#fff; border:1px solid var(--border); border-radius:12px; padding:1rem; box-shadow:0 4px 12px rgba(0,0,0,.05)}
    .card h3{font-size:1rem; margin-bottom:.6rem; color:var(--text-light); text-transform:uppercase; letter-spacing:.5px}
    .big{font-size:1.4rem; font-weight:700}
    .muted{color:#999}
    .status-badge{padding:.35rem .7rem; border-radius:20px; font-size:.85rem; font-weight:700; text-transform:uppercase; display:inline-block}
    .status-received{background:#d4edda; color:var(--ok)}
    .status-pending{background:#fff3cd; color:var(--warn)}
    .status-overdue{background:#f8d7da; color:var(--err)}
    .status-refunded{background:#e2e3e5; color:#383d41}
    .section{margin-top:1.25rem}
    table{width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border); border-radius:8px; overflow:hidden}
    th,td{padding:12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top}
    th{background:var(--primary); color:#fff; white-space:nowrap}
    tr:last-child td{border-bottom:none}
    .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; word-break:break-all}
    .pill{display:inline-block; padding:.25rem .5rem; border-radius:999px; border:1px solid var(--border); font-size:.8rem; background:#fff}
    .two-cols{display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1rem}
    .kv p{margin:.25rem 0}
    .kv strong{display:inline-block; min-width:160px; color:#444}
    .total-line{display:flex; justify-content:flex-end; gap:2rem; padding:.6rem 0; font-weight:700}
    @media print{
      header,.sidebar,.actions{display:none !important}
      .main{padding:0}
      body{background:#fff}
      .card,table{box-shadow:none}
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">🐄 <span>BovinTrade • Fazenda</span></div>
    <div class="user-menu">
      <span><?= e($email) ?></span>
      <form action="logout.php" method="post" style="display:inline">
        <button type="submit">Sair</button>
      </form>
      <div class="user-avatar"><i class="fas fa-user"></i></div>
    </div>
  </header>

  <div class="container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
          
            <a href="02-painel-fazenda.php" class="menu-item <?= $current_page === '02-painel-fazenda.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i><span>Painel da Fazenda</span>
            </a>

            <a href="03-cadastro-lote.php" class="menu-item <?= $current_page === '03-cadastro-lote.php' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i><span>Cadastro de Lotes</span>
            </a>

            <a href="gerenciar-lotes.php" class="menu-item <?= $current_page === 'gerenciar-lotes.php' ? 'active' : '' ?>">
                <i class="fas fa-edit"></i><span>Gerenciar Lotes</span>
            </a>

            <a href="agendar-transporte-f.php" class="menu-item <?= $current_page === 'agendar-retirada.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i><span>Agendamento de Retirada</span>
            </a>
              <a href="monitorar-transportes-faz.php" class="menu-item">
                <i class="fas fa-truck"></i><span>Monitorar Transportes</span>
            </a>

            <a href="05-historico-vendas.php" class="menu-item <?= $current_page === '05-historico-vendas.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i><span>Histórico de Vendas</span>
            </a>

            <a href="06-historico-pgtorec.php" class="menu-item <?= $current_page === '06-historico-pgtorec.php' ? 'active' : '' ?>">
                <i class="fas fa-receipt"></i><span>Histórico de Pag./Receb.</span>
            </a>

            <a href="minhas-avaliacoes-fazenda.php" class="menu-item <?= $current_page === 'minhas-avaliacoes-fazenda.php' ? 'active' : '' ?>">
                <i class="fas fa-star"></i><span>Minhas Avaliações</span>
            </a>

            <a href="notificacoes-fazenda.php" class="menu-item <?= $current_page === 'notificacoes-fazenda.php' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i><span>Notificações</span>
            </a>
            
            <a href="17-ajudafz.php" class="menu-item <?= $current_page === '17-ajudafz.php' ? 'active' : '' ?>">
                <i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span>
            </a>
                
            <a href="01-meu-perfil-fazenda.php" class="menu-item <?= $current_page === '01-meu-perfil-fazenda.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i><span>Meu Perfil</span>
            </a>

            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i><span>Sair</span>
            </a>
        </ul>
    </aside>

    <main class="main">
      <div class="topbar">
        <div class="title"><i class="fas fa-file-invoice-dollar"></i> Detalhe do Recebimento • <span class="pill">#PAG-<?= (int)$header['pagamento_id'] ?></span></div>
        <div class="actions">
          <a class="btn" href="06-historico-pgtorec.php"><i class="fas fa-arrow-left"></i> Voltar</a>
          <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir/Recibo</button>
        </div>
      </div>

      <!-- Cards resumo -->
      <div class="grid">
        <div class="card">
          <h3>Status</h3>
          <span class="status-badge <?= e($stInfo['badge']) ?>"><?= e($stInfo['label']) ?></span>
          <p class="muted" style="margin-top:.5rem">
            Criado: <strong><?= dtbr($header['created_at']) ?></strong><br>
            Confirmado: <strong><?= dtbr($header['confirmed_at'] ?? $header['confirmado_em']) ?></strong><br>
            Expiração: <strong><?= dtbr($header['expiracao_em']) ?></strong>
          </p>
        </div>
        <div class="card">
          <h3>Valor do Pagamento</h3>
          <div class="big"><?= brl($header['valor']) ?> <span class="muted" style="font-weight:500">(<?= e($header['moeda'] ?: 'BRL') ?>)</span></div>
          <p class="muted">Referência: <span class="mono"><?= e($header['referencia_externa'] ?: '-') ?></span></p>
        </div>
        <div class="card">
          <h3>Totais dos Repasses (sua fazenda)</h3>
          <p><strong>Bruto:</strong> <?= brl($tot_bruto) ?></p>
          <p><strong>Taxas:</strong> <?= brl($tot_taxa) ?></p>
          <p><strong>Líquido:</strong> <span class="big"><?= brl($tot_liq) ?></span></p>
        </div>
        <div class="card">
          <h3>Pedido / Frigorífico</h3>
          <p><strong>Pedido:</strong> <span class="pill">#VDA-<?= (int)$header['pedido_id'] ?></span></p>
          <p><strong>Frigorífico:</strong> <?= e($header['frigorifico_nome']) ?></p>
          <p class="muted"><strong>Contato:</strong> <?= e($header['frigorifico_email'] ?: '-') ?> • <?= e($header['frigorifico_telefone'] ?: '-') ?></p>
        </div>
      </div>

      <!-- Dados do Método -->
      <div class="section two-cols">
        <div class="card">
          <h3>Método de Pagamento</h3>
          <p><strong>Método:</strong> <?= e($header['metodo']) ?></p>
          <?php if ($header['metodo']==='PIX' && $pix): ?>
            <div class="kv" style="margin-top:.5rem">
              <p><strong>Chave destino:</strong> <span class="mono"><?= e($pix['chave_destino'] ?: '-') ?></span></p>
              <p><strong>PIX copia e cola:</strong> <span class="mono"><?= e($pix['copia_cola'] ?: '-') ?></span></p>
              <?php if (!empty($pix['qr_code'])): ?>
                <p><strong>QR Code (texto):</strong> <span class="mono"><?= e($pix['qr_code']) ?></span></p>
              <?php endif; ?>
            </div>
          <?php elseif ($header['metodo']==='CARTAO' && $cartao): ?>
            <div class="kv" style="margin-top:.5rem">
              <p><strong>Bandeira:</strong> <?= e($cartao['bandeira'] ?: '-') ?></p>
              <p><strong>Final do cartão:</strong> <?= e($cartao['last4'] ?: '****') ?></p>
              <p><strong>Titular:</strong> <?= e($cartao['titular_nome'] ?: '-') ?></p>
              <p><strong>Validade:</strong> <?= e(($cartao['exp_mes'] ?? '--') . '/' . ($cartao['exp_ano'] ?? '----')) ?></p>
              <p><strong>Cód. Autorização:</strong> <span class="mono"><?= e($cartao['autorizacao_codigo'] ?: '-') ?></span></p>
            </div>
          <?php else: ?>
            <p class="muted">Sem dados adicionais do método.</p>
          <?php endif; ?>
        </div>

        <div class="card">
          <h3>Endereço do Frigorífico</h3>
          <?php
            $linha1 = implode(', ', array_filter([$header['frigorifico_rua'], $header['frigorifico_numero'], $header['frigorifico_bairro']]));
            $linha2 = implode(' - ', array_filter([$header['frigorifico_cidade'], $header['frigorifico_estado']]));
            $cep    = $header['frigorifico_cep'] ?: '';
          ?>
          <p><?= e($linha1 ?: '-') ?></p>
          <p><?= e($linha2 ?: '-') ?></p>
          <p><?= e($cep ?: '-') ?></p>
        </div>
      </div>

      <!-- Repasses por item/lote -->
      <div class="section card">
        <h3>Repasses (por item/lote) • Cabeças: <?= (int)$tot_cabs ?></h3>
        <div style="overflow:auto; margin-top:.6rem">
          <table>
            <thead>
              <tr>
                <th>#Repasse</th>
                <th>Lote</th>
                <th>Qtd. Cabeças</th>
                <th>Preço Unit. (R$)</th>
                <th>Valor Item (R$)</th>
                <th>Status Repasse</th>
                <th>Bruto (R$)</th>
                <th>Taxa (%)</th>
                <th>Taxa (R$)</th>
                <th>Líquido (R$)</th>
                <th>Previsto</th>
                <th>Pago em</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($itens)): ?>
                <tr><td colspan="12" style="text-align:center; color:#777; padding:16px">Nenhum repasse encontrado.</td></tr>
              <?php else: foreach ($itens as $it): 
                $badge = 'status-pending'; $lab = $it['repasse_status'];
                if ($it['repasse_status']==='PAGO'){ $badge='status-received'; $lab='Pago'; }
                elseif ($it['repasse_status']==='AGENDADO'){ $badge='status-pending'; $lab='Agendado'; }
                elseif ($it['repasse_status']==='AGUARDANDO'){ $badge='status-pending'; $lab='Aguardando'; }
                elseif ($it['repasse_status']==='CANCELADO'){ $badge='status-refunded'; $lab='Cancelado'; }
              ?>
              <tr>
                <td>#RPS-<?= (int)$it['repasse_id'] ?></td>
                <td>
                  <?= e($it['codigo_lote'] ? ('Lote '.$it['codigo_lote']) : 'Lote s/ código') ?><br>
                  <span class="muted">Raça:</span> <?= e($it['raca'] ?? '-') ?> •
                  <span class="muted">Peso médio:</span> <?= number_format((float)($it['peso_medio_kg'] ?? 0),2,',','.') ?> kg
                </td>
                <td><?= (int)$it['quantidade_cabecas'] ?></td>
                <td><?= brl($it['preco_unitario_cab']) ?></td>
                <td><?= brl($it['valor_total']) ?></td>
                <td><span class="status-badge <?= e($badge) ?>"><?= e($lab) ?></span></td>
                <td><?= brl($it['valor_bruto']) ?></td>
                <td><?= number_format((float)$it['taxa_plataforma_percent'],2,',','.') ?>%</td>
                <td><?= brl($it['valor_taxa']) ?></td>
                <td><strong><?= brl($it['valor_liquido']) ?></strong></td>
                <td><?= dtbr($it['previsto_em']) ?></td>
                <td><?= dtbr($it['pago_em']) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="total-line">
          <div>Total Bruto: <?= brl($tot_bruto) ?></div>
          <div>Total Taxas: <?= brl($tot_taxa) ?></div>
          <div>Total Líquido: <?= brl($tot_liq) ?></div>
        </div>
      </div>

      <!-- Observações -->
      <div class="section card">
        <h3>Observações</h3>
        <p class="muted" style="margin-top:.25rem">
          • O “Valor do Pagamento” reflete o pagamento feito pelo frigorífico (transação em <em>pagamentos</em>).<br>
          • Os “Repasses” somam o que entra para sua fazenda, já aplicadas as taxas de plataforma por item.<br>
          • Em caso de dúvidas sobre confirmação/estorno, verifique o status e os carimbos de data/hora.
        </p>
      </div>
    </main>
  </div>
</body>
</html>
