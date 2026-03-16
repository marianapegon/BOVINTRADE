<?php
// notificacoes-fazenda.php
// ---------------------------------------------
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php?expired=1"); exit; }
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
  if (($u['tipo_usuario'] ?? '') === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
  if (($u['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
  header('Location: login.php'); exit;
}
require_once 'config.php'; // deve configurar $pdo (PDO)
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
$uid = (int)$u['id'];
// =======================
// Handlers AJAX (POST)
// =======================
if (($_GET['action'] ?? '') === 'mark_read' && $_SERVER['REQUEST_METHOD']==='POST') {
  $nid = (int)($_POST['id'] ?? 0);
  $stmt = $pdo->prepare("UPDATE notificacoes SET lida_em = NOW() WHERE id = ? AND usuario_id = ? AND lida_em IS NULL");
  $stmt->execute([$nid, $uid]);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true]); exit;
}
if (($_GET['action'] ?? '') === 'mark_all' && $_SERVER['REQUEST_METHOD']==='POST') {
  $stmt = $pdo->prepare("UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL");
  $stmt->execute([$uid]);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true]); exit;
}
if (($_GET['action'] ?? '') === 'count' && $_SERVER['REQUEST_METHOD']==='GET') {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL");
  $stmt->execute([$uid]);
  $count = (int)$stmt->fetchColumn();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['count'=>$count]); exit;
}
// =======================
// Filtros e paginação
// =======================
$show = $_GET['show'] ?? 'all'; // all|unread|financeiro|pedido|transporte|avaliacao
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page-1)*$limit;
// Mapeamento de categorias -> tipos
$typeGroups = [
  'financeiro' => ['REPASSE_CRIADO','REPASSE_STATUS','PAGAMENTO_CONFIRMADO'],
  'pedido'     => ['PEDIDO_NOVO','PEDIDO_STATUS','LOTE_RESERVADO'],
  'transporte' => ['TRANSPORTE_CRIADO','RASTREAMENTO','AGENDAMENTO_TRANSPORTE','RETIRADA_CONFIRMADA','TRANSPORTE_ALERTA'],
  'avaliacao'  => ['AVALIACAO_NOVA','AVALIACAO_METRICA']
];
$where = "usuario_id = ?";
$params = [$uid];
if ($show === 'unread') {
  $where .= " AND lida_em IS NULL";
} elseif (array_key_exists($show, $typeGroups)) {
  $in = $typeGroups[$show];
  $ph = implode(',', array_fill(0, count($in), '?'));
  $where .= " AND tipo IN ($ph)";
  $params = array_merge($params, $in);
}
// Consulta principal
$sql = "
  SELECT id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id, created_at, lida_em
  FROM notificacoes
  WHERE $where
  ORDER BY created_at DESC, id DESC
  LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// contador de não lidas (para o badge)
$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL");
$stmtCnt->execute([$uid]);
$unreadCount = (int)$stmtCnt->fetchColumn();
// Agrupar por data (Hoje, Ontem, dd/mm/aaaa)
function isSameDate($a,$b){ return substr($a,0,10)===substr($b,0,10); }
$today = (new DateTime())->format('Y-m-d');
$yesterday = (new DateTime('yesterday'))->format('Y-m-d');
function labelDate($dt) {
  $d = substr($dt,0,10);
  global $today, $yesterday;
  if ($d === $today) return 'Hoje';
  if ($d === $yesterday) return 'Ontem';
  $p = DateTime::createFromFormat('Y-m-d', $d);
  return $p? $p->format('d/m/Y') : e($d);
}
// ícones e classes → por tipo
function typeMeta($tipo) {
  $map = [
    // PEDIDOS
    'PEDIDO_NOVO'        => ['fa-handshake',   'notification-info'],
    'PEDIDO_STATUS'      => ['fa-clipboard',   'notification-info'],
    'LOTE_RESERVADO'     => ['fa-bookmark',    'notification-warning'],
    // TRANSPORTE
    'TRANSPORTE_CRIADO'  => ['fa-truck',       'notification-info'],
    'RASTREAMENTO'       => ['fa-route',       'notification-info'],
    'AGENDAMENTO_TRANSPORTE'=>['fa-calendar-check','notification-info'],
    'RETIRADA_CONFIRMADA'=> ['fa-qrcode',      ''],
    'TRANSPORTE_ALERTA'  => ['fa-exclamation-triangle','notification-warning'],
    // FINANCEIRO
    'REPASSE_CRIADO'     => ['fa-money-bill-wave','notification-success'],
    'REPASSE_STATUS'     => ['fa-receipt',     'notification-success'],
    'PAGAMENTO_CONFIRMADO'=>['fa-check-circle','notification-success'],
    // AVALIAÇÕES
    'AVALIACAO_NOVA'     => ['fa-star',        ''],
    'AVALIACAO_METRICA'  => ['fa-star-half-alt','']
  ];
  return $map[$tipo] ?? ['fa-bell',''];
}
function timeAgo($datetime) {
  $dt = new DateTime($datetime);
  $now = new DateTime();
  $diff = $now->getTimestamp() - $dt->getTimestamp();
  if ($diff < 60) return "agora mesmo";
  $mins = floor($diff/60);
  if ($mins < 60) return $mins." min atrás";
  $hours = floor($mins/60);
  if ($hours < 24) return $hours." h atrás";
  $days = floor($hours/24);
  if ($days === 1) return "ontem";
  return $dt->format('d/m/Y H:i');
}
// Organizar por grupos de data
$groups = [];
foreach ($rows as $r) {
  $label = labelDate($r['created_at']);
  $groups[$label][] = $r;
}
$email = htmlspecialchars($u['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Notificações</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    :root {
  --primary: #a30000;
  --primary-light: #d43b3b;
  --primary-dark: #7a0000;
  --secondary: #f8f5f2;
  --text: #333333;
  --text-light: #666666;
  --background: #ffffff;
  --border: #e0e0e0;
  --success: #4caf50;
  --warning: #ff9800;
  --info: #2196f3;
}
  *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
    .sidebar-menu{ list-style:none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; }
    .welcome-card{ background:linear-gradient(135deg,rgba(163,0,0,0.9),rgba(122,0,0,0.9)); color:white; border-radius:12px; padding:2.5rem; margin-bottom:2.5rem; }
.dashboard-header {
  display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;
}
.dashboard-title { font-size:1.6rem; font-weight:600; color:var(--text); }
.actions { display:flex; gap:.5rem; flex-wrap: wrap; }
.btn {
  padding:.6rem 1rem; border-radius:8px; border:1px solid var(--primary);
  background: var(--primary); color:#fff; font-weight:600; cursor:pointer; transition:.15s;
  display:inline-flex; align-items:center; gap:.5rem;
}
.btn:hover{ transform: translateY(-1px); box-shadow:0 6px 14px rgba(163,0,0,.2); }
.btn-outline { background: transparent; color: var(--primary); }
.btn-outline:hover{ background: rgba(163,0,0,.06); }
/* Filtros pill */
.filters {
  display:flex; gap:.5rem; flex-wrap: wrap; margin: 1rem 0 1.25rem;
}
.pill {
  border:1px solid var(--border); border-radius:999px; padding:.35rem .8rem; cursor:pointer; font-weight:600; color:var(--text-light); background:#fff;
}
.pill.active{ border-color: var(--primary); color: var(--primary); background: #fff7f6; }
/* ===== BLOCO DE NOTIFICAÇÕES (UM POR LINHA) ===== */
.block { margin-bottom: 1.5rem; }
.block h3 {
  font-size:1rem; font-weight:700; color:#111; letter-spacing:.3px;
  margin-bottom:.75rem; display:flex; align-items:center; gap:.5rem;
}
.block h3 .dot { width:10px; height:10px; background: var(--primary); border-radius:50%; display:inline-block; }
/* -- força layout em ligação -- */
.grid {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
}
.grid .col {
  width: 100%;
}
/* ==== CARD DE NOTIFICAÇÃO ==== */
.notification-card {
  background: #fff;
  border:1px solid var(--border);
  border-left: 5px solid var(--primary);
  border-radius:12px;
  padding:1rem;
  display:flex;
  gap:.9rem;
  align-items:flex-start;
  box-shadow: 0 3px 10px rgba(0,0,0,.05);
}
.notification-unread { background: #fff8f6; border-left-color: var(--primary-light); }
.notification-success { border-left-color: var(--success); }
.notification-warning { border-left-color: var(--warning); }
.notification-info    { border-left-color: var(--info); }
.notification-icon i{ font-size:1.15rem; color: var(--primary); margin-top:.15rem; }
.notification-success .notification-icon i { color: var(--success); }
.notification-warning .notification-icon i { color: var(--warning); }
.notification-info .notification-icon i    { color: var(--info); }
.notification-title { font-weight:700; margin-bottom:.2rem; display:flex; justify-content:space-between; gap:.75rem; }
.notification-time { font-size:.85rem; color:var(--text-light); white-space:nowrap; }
.notification-message { color:var(--text-light); font-size:.95rem; margin-top:.25rem; }
.notification-actions { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.6rem; }
.notification-btn {
  border:1px solid var(--border); background:#fff; color:#444; font-size:.9rem; border-radius:8px; padding:.35rem .65rem; cursor:pointer;
}
.notification-btn:hover{ border-color: var(--primary); color: var(--primary); }
.toolbar {
  display:flex; gap:.5rem; align-items:center; flex-wrap: wrap;
}
.counter { background:#fff; border:1px solid var(--border); border-radius:8px; padding:.45rem .75rem; color:var(--text-light); font-weight:700; }
.hint { color:var(--text-light); font-size:.9rem; }
.card-empty {
  border:2px dashed var(--border);
  background: #fff;
  border-radius:12px;
  padding:2rem; text-align:center; color:var(--text-light);
}
  </style>
</head>
<body>
<header>
         <div class="logo">
      🐄
      <span>BovinTrade • Fazenda</span>
    </div>
  <div class="user-menu">
    <span><?php echo $email; ?></span>
    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
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
           
        </ul>
    </aside>
  <!-- Main -->
  <main class="main">
    <div class="dashboard-header">
   <h1 class="history-title"><i class="fas fa-bell"></i> Notificações</h1>
      <div class="toolbar">
        <span class="counter">Não lidas: <?= (int)$unreadCount ?></span>
        <button id="btnMarkAll" class="btn"><i class="fas fa-check-double"></i> Marcar todas como lidas</button>
        <a class="btn btn-outline" href="notificacoes-preferencias-fazenda.php"><i class="fas fa-cog"></i> Configurações</a>
      </div>
    </div>
    <!-- Filtros -->
    <div class="filters">
      <?php
        $tabs = [
          'all'       => 'Todas',
          'unread'    => 'Não lidas',
          'financeiro'=> 'Financeiro',
          'pedido'    => 'Pedidos',
          'transporte'=> 'Transporte',

        ];
        foreach ($tabs as $key=>$label):
      ?>
        <a class="pill <?= $show===$key?'active':'' ?>" href="?show=<?= e($key) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (empty($rows)): ?>
      <div class="card-empty">
        <i class="fas fa-bell-slash"></i>
        <p>Sem notificações <?= $show==='unread'?'não lidas':'' ?> no momento.</p>
        <p class="hint">Os eventos novos aparecerão aqui automaticamente.</p>
      </div>
    <?php else: ?>
      <?php foreach ($groups as $dateLabel => $list): ?>
        <section class="block">
          <h3><span class="dot"></span><?= e($dateLabel) ?></h3>
          <div class="grid">
            <?php foreach ($list as $n):
              [$icon,$extraClass] = typeMeta($n['tipo']);
              $unread = empty($n['lida_em']);
              $cls = 'notification-card'.($unread?' notification-unread':'').($extraClass?(' '.$extraClass):'');
              $msg = trim((string)$n['mensagem']);
              $t   = trim((string)$n['titulo']);
              $dados = json_decode($n['dados_json'] ?: '{}', true);
            ?>
              <div class="col">
                <article class="<?= e($cls) ?>" data-id="<?= (int)$n['id'] ?>">
                  <div class="notification-icon"><i class="fas <?= e($icon) ?>"></i></div>
                  <div class="notification-content">
                    <div class="notification-title">
                      <span><?= e($t ?: 'Notificação') ?></span>
                      <span class="notification-time"><?= e(timeAgo($n['created_at'])) ?></span>
                    </div>
                    <?php if ($msg!==''): ?>
                      <p class="notification-message"><?= nl2br(e($msg)) ?></p>
                    <?php endif; ?>
                    <div class="notification-actions">
                      <?php
                        // === CTA PRINCIPAL COM LINK FUNCIONAL (CORRIGIDO) ===
                        $ctaHref = '#';
                        $ctaLabel = 'Ver detalhes';
                        $ctaIcon = 'fa-arrow-right';

                        switch ($n['tipo']) {
                          // === PEDIDOS (agora com telas reais) ===
                          case 'PEDIDO_NOVO':
                          case 'PEDIDO_STATUS':
                            $ctaHref = '05-historico-vendas.php';
                            $ctaLabel = 'Ver vendas';
                            $ctaIcon = 'fa-history';
                            break;

                          case 'LOTE_RESERVADO':
                            $loteId = (int)($dados['lote_id'] ?? 0);
                            if ($loteId > 0) {
                              $ctaHref = "gerenciar-lotes.php#lote-{$loteId}";
                              $ctaLabel = 'Ver lote';
                              $ctaIcon = 'fa-bookmark';
                            } else {
                              $ctaHref = 'gerenciar-lotes.php';
                              $ctaLabel = 'Ver lotes';
                              $ctaIcon = 'fa-edit';
                            }
                            break;

                          // === TRANSPORTE ===
                          case 'TRANSPORTE_CRIADO':
                          case 'RASTREAMENTO':
                          case 'AGENDAMENTO_TRANSPORTE':
                          case 'RETIRADA_CONFIRMADA':
                          case 'TRANSPORTE_ALERTA':
                            $ctaHref = 'monitorar-transportes-faz.php';
                            $ctaLabel = 'Ver transporte';
                            $ctaIcon = 'fa-truck';
                            break;

                          // === FINANCEIRO ===
                          case 'REPASSE_CRIADO':
                          case 'REPASSE_STATUS':
                          case 'PAGAMENTO_CONFIRMADO':
                            $ctaHref = '06-historico-pgtorec.php';
                            $ctaLabel = 'Ver financeiro';
                            $ctaIcon = 'fa-receipt';
                            break;

                          // === AVALIAÇÕES ===
                          case 'AVALIACAO_NOVA':
                          case 'AVALIACAO_METRICA':
                            $ctaHref = 'minhas-avaliacoes-fazenda.php';
                            $ctaLabel = 'Ver avaliações';
                            $ctaIcon = 'fa-star';
                            break;

                          // === OUTROS (fallback inteligente) ===
                          default:
                            if (!empty($n['relacionado_tabela']) && !empty($n['relacionado_id'])) {
                              $tabela = strtolower($n['relacionado_tabela']);
                              $id = (int)$n['relacionado_id'];
                              if (strpos($tabela, 'pedido') !== false || strpos($tabela, 'venda') !== false) {
                                $ctaHref = '05-historico-vendas.php';
                                $ctaLabel = 'Ver vendas';
                                $ctaIcon = 'fa-history';
                              } elseif (strpos($tabela, 'lote') !== false) {
                                $ctaHref = "gerenciar-lotes.php" . ($id > 0 ? "#lote-{$id}" : '');
                                $ctaLabel = 'Ver lotes';
                                $ctaIcon = 'fa-edit';
                              } elseif (strpos($tabela, 'transporte') !== false) {
                                $ctaHref = 'monitorar-transportes-faz.php';
                                $ctaLabel = 'Ver transporte';
                                $ctaIcon = 'fa-truck';
                              } elseif (strpos($tabela, 'repasse') !== false || strpos($tabela, 'pagamento') !== false) {
                                $ctaHref = '06-historico-pgtorec.php';
                                $ctaLabel = 'Ver financeiro';
                                $ctaIcon = 'fa-receipt';
                              } elseif (strpos($tabela, 'avaliacao') !== false) {
                                $ctaHref = 'minhas-avaliacoes-fazenda.php';
                                $ctaLabel = 'Ver avaliações';
                                $ctaIcon = 'fa-star';
                              }
                            }
                            if ($ctaHref === '#') {
                              $ctaHref = 'notificacoes-fazenda.php';
                              $ctaLabel = 'Ver notificações';
                              $ctaIcon = 'fa-bell';
                            }
                            break;
                        }

                        echo '<a class="notification-btn" href="' . e($ctaHref) . '"><i class="fas ' . $ctaIcon . '"></i> ' . e($ctaLabel) . '</a>';
                      ?>

                      <?php if ($unread): ?>
                        <button class="notification-btn btn-mark" data-id="<?= (int)$n['id'] ?>"><i class="fas fa-check"></i> Marcar como lida</button>
                      <?php else: ?>
                        <span class="hint">Lida</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
      <!-- Paginação simples -->
      <div class="actions" style="margin-top:1rem;">
        <?php if ($page>1): ?>
          <a class="btn btn-outline" href="?show=<?= e($show) ?>&page=<?= $page-1 ?>"><i class="fas fa-arrow-left"></i> Anterior</a>
        <?php endif; ?>
        <?php if (count($rows)===$limit): ?>
          <a class="btn btn-outline" href="?show=<?= e($show) ?>&page=<?= $page+1 ?>">Mais <i class="fas fa-arrow-right"></i></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</div>
<script>
  // Marcar como lida (individual)
  document.querySelectorAll('.btn-mark').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      ev.stopPropagation();
      const id = btn.dataset.id;
      try{
        const r = await fetch('notificacoes-fazenda.php?action=mark_read', {
          method:'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: 'id='+encodeURIComponent(id)
        });
        const js = await r.json();
        if(js.ok){
          const card = btn.closest('.notification-card');
          card.classList.remove('notification-unread');
          btn.remove();
          atualizarContador();
        }
      }catch(e){}
    });
  });
  // Marcar todas como lidas
  const btnAll = document.getElementById('btnMarkAll');
  if(btnAll){
    btnAll.addEventListener('click', async ()=>{
      try{
        const r = await fetch('notificacoes-fazenda.php?action=mark_all', {method:'POST'});
        const js = await r.json();
        if(js.ok){
          document.querySelectorAll('.notification-card.notification-unread').forEach(c=>c.classList.remove('notification-unread'));
          atualizarContador();
        }
      }catch(e){}
    });
  }
  // Atualizar badge sem reload
  async function atualizarContador(){
    try{
      const r = await fetch('notificacoes-fazenda.php?action=count', {cache:'no-store'});
      const {count} = await r.json();
      let badge = document.querySelector('.user-menu a[title="Notificações"] .badge');
      if(!badge && count>0){
        badge = document.createElement('span');
        badge.className = 'badge';
        document.querySelector('.user-menu a[title="Notificações"]').appendChild(badge);
      }
      if(badge){
        badge.textContent = count>99 ? '99+' : (count||'');
        if(count===0) badge.remove();
      }
    }catch(e){}
  }
  // Polling leve (15s)
  setInterval(atualizarContador, 15000);
  document.addEventListener('visibilitychange', ()=>{ if(!document.hidden) atualizarContador(); });
</script>
</body>
</html>