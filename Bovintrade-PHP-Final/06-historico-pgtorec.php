<?php
// 06-historico-pgtorec.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php';
$current_page = basename($_SERVER['PHP_SELF']); // para destacar o menu corretamente


/* ---------- Autenticação básica ---------- */
$usuario   = $_SESSION['usuario'] ?? null;
$fazendaId = (int)($usuario['id'] ?? 0);
if ($fazendaId <= 0) {
  http_response_code(401);
  echo 'Faça login como Fazenda para ver o histórico de recebimentos.';
  exit;
}
$email = $usuario['email'] ?? $usuario['login'] ?? $usuario['nome'] ?? 'Minha conta';

/* ---------- Helpers ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts) {
  if (!$ts) return '-';
  $t = is_numeric($ts) ? (int)$ts : strtotime($ts);
  return date('d/m/Y H:i', $t);
}
function sel($a, $b) { return $a === $b ? 'selected' : ''; }

/* ---------- Filtros (GET) ---------- */
$periodo      = $_GET['periodo'] ?? '30';        // '30','90','ano','custom'
$data_ini     = $_GET['data_ini'] ?? '';
$data_fim     = $_GET['data_fim'] ?? '';
$statusFiltro = $_GET['status']  ?? 'todos';     // 'todos','recebido','pendente','atrasado','estornado'
$metodoFiltro = $_GET['metodo']  ?? 'todos';     // 'todos','PIX','CARTAO'
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 10;

/* Datas */
$inicio = null; $fim = null;
$hoje = new DateTime('today');
switch ($periodo) {
  case '90':
    $inicio = (clone $hoje)->modify('-90 days')->format('Y-m-d 00:00:00');
    $fim    = (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
    break;
  case 'ano':
    $inicio = date('Y-01-01 00:00:00');
    $fim    = (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
    break;
  case 'custom':
    $ini = preg_replace('/[^0-9\-]/','',$data_ini);
    $fi  = preg_replace('/[^0-9\-]/','',$data_fim);
    $inicio = $ini ? $ini.' 00:00:00' : '1970-01-01 00:00:00';
    $fim    = $fi  ? $fi .' 23:59:59' : (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
    break;
  case '30':
  default:
    $inicio = (clone $hoje)->modify('-30 days')->format('Y-m-d 00:00:00');
    $fim    = (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
    break;
}

/* ---------- Query principal ----------
   - Lista pagamentos (pg) de pedidos (p) que têm itens desta fazenda (x).
   - x.valor_total_fazenda é a soma dos itens da sua fazenda dentro do pedido.
*/
$sql = "
  SELECT
    pg.id              AS pagamento_id,
    pg.metodo,
    pg.status          AS status_pagamento,
    pg.valor,
    pg.moeda,
    pg.created_at,
    pg.confirmado_em,

    p.id               AS pedido_id,
    p.frigorifico_id,
    p.status           AS status_pedido,

    x.valor_total_fazenda,

    /* Nome do frigorífico vindo de usuarios */
    COALESCE(u.nome_razao, CONCAT('Frigorífico #', p.frigorifico_id)) AS frigorifico_nome

  FROM pagamentos pg
  JOIN pedidos p
    ON p.id = pg.pedido_id

  /* total relacionado à fazenda (mantém como já estava) */
  JOIN (
    SELECT pedido_id, SUM(valor_total) AS valor_total_fazenda
    FROM pedido_itens
    WHERE fazenda_id = ?
    GROUP BY pedido_id
  ) x ON x.pedido_id = p.id

  /* pega o nome do frigorífico */
  LEFT JOIN usuarios u
    ON u.id = p.frigorifico_id
   AND u.tipo_usuario = 'FRIGORIFICO'

  WHERE pg.created_at >= ? AND pg.created_at <= ?
";

$types = 'iss';
$params = [$fazendaId, $inicio, $fim];

/* Filtro de status -> mapeia para pagamentos.status */
if ($statusFiltro === 'recebido') {
  $sql .= " AND pg.status = 'APROVADO' ";
} elseif ($statusFiltro === 'pendente') {
  $sql .= " AND pg.status = 'PENDENTE' ";
} elseif ($statusFiltro === 'atrasado') {
  $sql .= " AND pg.status = 'EXPIRADO' ";
} elseif ($statusFiltro === 'estornado') {
  // tratando estorno como recusado/cancelado
  $sql .= " AND pg.status IN ('RECUSADO','CANCELADO') ";
}

/* Filtro de método */
if (in_array($metodoFiltro, ['PIX','CARTAO'], true)) {
  $sql .= " AND pg.metodo = ? ";
  $types .= 's';
  $params[] = $metodoFiltro;
}

$sql .= " ORDER BY COALESCE(pg.confirmado_em, pg.created_at) DESC, pg.id DESC LIMIT 1000";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
  // Badge + label
  $st = $r['status_pagamento'] ?? '';
  if ($st === 'APROVADO') {
    $label = 'Recebido'; $badge = 'status-received';
  } elseif ($st === 'PENDENTE') {
    $label = 'Pendente'; $badge = 'status-pending';
  } elseif ($st === 'EXPIRADO') {
    $label = 'Atrasado'; $badge = 'status-overdue';
  } elseif ($st === 'RECUSADO' || $st === 'CANCELADO') {
    $label = 'Estornado/Cancelado'; $badge = 'status-refunded';
  } else {
    $label = $st ?: '-'; $badge = 'status-pending';
  }
  $r['_status_label'] = $label;
  $r['_status_badge'] = $badge;

  // Data de referência: confirmado_em (se houver) senão created_at
  $r['_data_ref'] = $r['confirmado_em'] ?: $r['created_at'];

  $rows[] = $r;
}
$stmt->close();

/* ---------- Paginação em memória ---------- */
$total      = count($rows);
$totalPages = (int)ceil(max(1,$total) / $perPage);
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$pagina     = array_slice($rows, $offset, $perPage);

/* Para manter filtros na paginação/export */
function build_qs($extra = []) {
  $qs = $_GET; foreach ($extra as $k=>$v) { $qs[$k]=$v; }
  return '?'.http_build_query($qs);
}

/* ---------- Export CSV ---------- */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="historico_recebimentos.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID Pagamento', 'Data', 'Método', 'Status', 'Valor Pago', 'ID Pedido', 'Valor da Sua Venda', 'Frigorífico']);

  foreach ($rows as $r) {
    fputcsv($out, [
      $r['pagamento_id'],
      dtbr($r['_data_ref']),
      $r['metodo'],
      $r['_status_label'],
      number_format((float)$r['valor'],2,',','.'),
      $r['pedido_id'],
      number_format((float)$r['valor_total_fazenda'],2,',','.'),
      $r['frigorifico_nome'],
    ]);
  }
  fclose($out);
  exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Painel da Fazenda</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonte + Ícones -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    
    /* ===== Modal de Detalhes do Pagamento (mesmo look do modal de vendas) ===== */
.modal {
  display: none; position: fixed; z-index: 2000;
  left: 0; top: 0; width: 100%; height: 100%;
  overflow: auto; background-color: rgba(0,0,0,0.5);
  backdrop-filter: blur(2px); padding-top: 60px;
}
.modal-content {
  background-color: var(--background); margin: 5% auto;
  padding: 30px; border: 1px solid var(--border);
  border-radius: 8px; width: 90%; max-width: 800px;
  position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.modal-content h3 {
  color: var(--primary); margin-top: 0; margin-bottom: 1.5rem;
  border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem;
  font-weight: 600; font-size: 1.4rem;
}
.modal-content h4 {
  color: var(--primary-dark); margin-top: 1.5rem; margin-bottom: 0.8rem;
  font-weight: 600; font-size: 1.1rem;
}
.modal-row { display:flex; gap:14px; align-items:flex-start; border-bottom:1px solid #f0f0f0; padding:10px 0; }
.modal-row:last-of-type { border-bottom:none; }
.modal-row strong { min-width: 180px; color:#444; font-weight:600; flex-shrink:0; }
.modal-row span { color: var(--text-light); flex:1; word-break:break-word; }
.modal-hr { border:0; border-top:1px solid var(--border); margin: 1.2rem 0; }
.close-btn { color:#aaa; position:absolute; top:10px; right:15px;
  font-size:28px; font-weight:bold; line-height:20px; background:none; border:none; cursor:pointer; }
.close-btn:hover, .close-btn:focus { color: var(--primary); }

/* badgets reaproveitando classes existentes */
.badge { padding:.3rem .7rem; border-radius: 20px; font-size:.8rem; font-weight:600; text-transform:uppercase; }
.badge-ok { background:#d4edda; color:#155724; }
.badge-warn { background:#fff3cd; color:#856404; }
.badge-err { background:#f8d7da; color:#721c24; }
.badge-neutral { background:#e2e3e5; color:#383d41; }

/* tabela de itens dentro do modal */
.modal-table { width:100%; border-collapse: collapse; margin-top:.6rem; }
.modal-table th, .modal-table td { padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; font-size: .92rem; }
.modal-table th { background:#fafafa; color:#333; white-space:nowrap; }
.modal-table tr:last-child td { border-bottom:none; }
.modal-footer-actions { display:flex; gap:10px; margin-top: 1rem; }

    :root {
      --primary: #a30000;
      --primary-dark: #7a0000;
      --text: #333333;
      --text-light: #666666;
      --background: #ffffff;
      --border: #e0e0e0;
      --success: #4caf50;
    }
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); overflow-x: hidden; }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; }
    .resizer {
      width: 5px;
      background: var(--border);
      cursor: col-resize;
      height: 100%;
      display: flex;
      align-items: center;
    }
    .resizer:hover {
      background: var(--primary);
    }
    .sidebar-menu{ list-style:none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; min-width:0; }
    .dashboard-header { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      margin-bottom: 2rem; 
    }
    .dashboard-title { 
      font-size: 1.8rem; 
      font-weight: 600; 
    }
    .profile-card { 
      background: #fff; 
      border-radius: 12px; 
      padding: 2.5rem; 
      margin-bottom: 2.5rem; 
      box-shadow: 0 8px 24px rgba(0,0,0,.1); 
    }
    .profile-header { 
      margin-bottom: 2rem; 
      padding-bottom: 1.5rem; 
      border-bottom: 1px solid var(--border); 
    }
    .profile-title { 
      font-size: 1.5rem; 
      font-weight: 600; 
      display: flex; 
      align-items: center; 
      gap: 1rem; 
    }
    .profile-title i { 
      color: var(--primary); 
    }
    .form-grid { 
      display: grid; 
      grid-template-columns: repeat(2, 1fr); 
      gap: 1.5rem; 
    }
    .form-group { 
      margin-bottom: 1.5rem; 
    }
    .form-group.full-width { 
      grid-column: span 2; 
    }
    label { 
      display: block; 
      margin-bottom: .5rem; 
      font-weight: 500; 
    }
    input, select { 
      width: 100%; 
      padding: .75rem 1rem; 
      border: 1px solid var(--border); 
      border-radius: 6px; 
      font-family: 'Montserrat', sans-serif; 
      font-size: 1rem; 
    }
    input[disabled] { 
      background: #f6f6f6; 
      color: #666; 
    }
    .btn { 
      padding: .75rem 1.25rem; 
      border-radius: 8px; 
      font-weight: 600; 
      cursor: pointer; 
      border: 1px solid var(--primary); 
      background: #fff; 
      color: var(--primary); 
      text-decoration: none; 
      display: inline-flex; 
      align-items: center; 
      gap: .5rem; 
    }
    .btn:hover { 
      background: #fff3f3; 
    }
    .btn-primary { 
      background: var(--primary); 
      color: #fff; 
      border-color: var(--primary); 
    }
    .btn-primary:hover { 
      background: var(--primary-dark); 
      color: #fff; 
    }
    .btn-danger { 
      border-color: #b00020; 
      color: #b00020; 
    }
    .btn-danger:hover { 
      background: #ffebee; 
    }
    .alert { 
      padding: 1rem; 
      border-radius: 8px; 
      margin: 0 0 1rem 0; 
    }
    .alert-success { 
      background: #e8f5e9; 
      border: 1px solid #c8e6c9; 
      color: #256029; 
    }
    .alert-error { 
      background: #ffebee; 
      border: 1px solid #ffcdd2; 
      color: #7a0000; 
    }
    .history-container { 
      max-width: 100%; 
    }
    .history-header { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      margin-bottom: 2rem; 
    }
    .history-title { 
      font-size: 1.8rem; 
      font-weight: 600; 
      display: flex; 
      align-items: center; 
      gap: .5rem; 
    }
    .filters { 
      background: #fff; 
      border-radius: 12px; 
      padding: 2rem; 
      margin-bottom: 2rem; 
      box-shadow: 0 4px 12px rgba(0,0,0,.05); 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
      gap: 1.5rem; 
      align-items: end; 
    }
    .group { 
      display: flex; 
      flex-direction: column; 
    }
    .group label { 
      margin-bottom: .5rem; 
      font-weight: 500; 
    }
    table { 
      width: 100%; 
      border-collapse: collapse; 
      background: #fff; 
      box-shadow: 0 4px 8px rgba(0,0,0,.1); 
      border-radius: 8px; 
      overflow: hidden; 
    }
    th, td { 
      padding: 12px; 
      border: 1px solid var(--border); 
      text-align: left; 
      vertical-align: top; 
    }
    th { 
      background-color: var(--primary); 
      color: #fff; 
      white-space: nowrap; 
      text-align: center; 
    }
    tr:nth-child(even) { 
      background-color: var(--zebra); 
    }
    .status-badge { 
      padding: .4rem .8rem; 
      border-radius: 20px; 
      font-size: .85rem; 
      font-weight: 600; 
      text-transform: uppercase; 
    }
    .status-received { 
      background: #d4edda; 
      color: #155724; 
    }
    .status-pending { 
      background: #fff3cd; 
      color: #856404; 
    }
    .status-overdue { 
      background: #f8d7da; 
      color: #721c24; 
    }
    .status-refunded { 
      background: #e2e3e5; 
      color: #383d41; 
    }
    .money-in { 
      color: #28a745; 
      font-weight: bold; 
    }
    .money-out { 
      color: #dc3545; 
      font-weight: bold; 
    }
    .pagination { 
      display: flex; 
      gap: .4rem; 
      align-items: center; 
      margin-top: 1rem; 
      flex-wrap: wrap; 
    }
    .pagination a, .pagination span { 
      padding: .45rem .7rem; 
      border: 1px solid var(--border); 
      border-radius: 6px; 
      text-decoration: none; 
      color: var(--text); 
    }
    .pagination .active { 
      background: var(--primary); 
      color: #fff; 
      border-color: var(--primary); 
    }
    .muted { 
      color: #999; 
    }
    @media (max-width: 1024px) {
      .sidebar { 
        position: fixed; 
        top: 76px; 
        left: 0; 
        bottom: 0; 
        transform: translateX(-100%); 
        z-index: 90; 
      }
      .sidebar.active { 
        transform: translateX(0); 
      }
      .main { 
        padding: 1.5rem; 
      }
      table { 
        display: block; 
        overflow: auto; 
      }
      .filters { 
        grid-template-columns: 1fr; 
      }
      .history-header { 
        flex-direction: column; 
        gap: 1rem; 
        align-items: flex-start; 
      }
    }

   

  </style>
</head>

<body>
   <header>
<div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">
      🐄
      <span>BovinTrade • Fazenda</span>
    </div>
    <div class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </div>
  </div>
  <div class="user-menu">
    <span><?= e($email) ?></span>
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

    <main class="main">
      <div class="history-container">
        <div class="history-header">
          <h1 class="history-title"><i class="fas fa-money-bill-wave"></i> Histórico de Pagamentos Recebidos</h1>
          <div>
            <a class="btn" href="<?= e(build_qs(['export'=>'csv'])) ?>"><i class="fas fa-download"></i> Exportar</a>
          </div>
        </div>

        <!-- Filtros -->
        <form class="filters" method="get">
          <div class="group">
            <label>Período</label>
            <select name="periodo" onchange="this.form.submit()">
              <option value="30"  <?= sel($periodo,'30')  ?>>Últimos 30 dias</option>
              <option value="90"  <?= sel($periodo,'90')  ?>>Últimos 90 dias</option>
              <option value="ano" <?= sel($periodo,'ano') ?>>Este ano</option>
              <option value="custom" <?= sel($periodo,'custom') ?>>Personalizado</option>
            </select>
          </div>
          <div class="group" <?= $periodo==='custom' ? '' : 'style="opacity:.5"' ?>>
            <label>Início</label>
            <input type="date" name="data_ini" value="<?= e($data_ini) ?>" <?= $periodo==='custom' ? '' : 'disabled' ?>>
          </div>
          <div class="group" <?= $periodo==='custom' ? '' : 'style="opacity:.5"' ?>>
            <label>Fim</label>
            <input type="date" name="data_fim" value="<?= e($data_fim) ?>" <?= $periodo==='custom' ? '' : 'disabled' ?>>
          </div>
          <div class="group">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
              <option value="todos"     <?= sel($statusFiltro,'todos')     ?>>Todos</option>
              <option value="recebido"  <?= sel($statusFiltro,'recebido')  ?>>Recebidos</option>
              <option value="pendente"  <?= sel($statusFiltro,'pendente')  ?>>Pendentes</option>
              <option value="atrasado"  <?= sel($statusFiltro,'atrasado')  ?>>Atrasados</option>
              <option value="estornado" <?= sel($statusFiltro,'estornado') ?>>Estornados/Cancelados</option>
            </select>
          </div>
          <div class="group">
            <label>Forma de Pagamento</label>
            <select name="metodo" onchange="this.form.submit()">
              <option value="todos"  <?= sel($metodoFiltro,'todos')  ?>>Todos</option>
              <option value="PIX"    <?= sel($metodoFiltro,'PIX')    ?>>PIX</option>
              <option value="CARTAO" <?= sel($metodoFiltro,'CARTAO') ?>>Cartão de Crédito</option>
            </select>
          </div>
          <div class="group" style="align-self:flex-end">
            <button class="btn" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
          </div>
        </form>

        <table class="payments-table">
          <thead>
            <tr>
              <th>ID Pagamento</th>
              <th>Data</th>
              <th>Valor</th>
              <th>Frigorífico</th>
              <th>Venda (Pedido)</th>
              <th>Sua Venda (R$)</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($pagina)): ?>
            <tr><td colspan="8" style="text-align:center; color:#777; padding:24px">Nenhum pagamento encontrado para o critério selecionado.</td></tr>
          <?php else: ?>
            <?php foreach ($pagina as $r): 
              $moneyClass = ($r['status_pagamento']==='APROVADO') ? 'money-in' :
                            (($r['status_pagamento']==='RECUSADO'||$r['status_pagamento']==='CANCELADO') ? 'money-out' : '');
            ?>
            <tr>
              <td>#PAG-<?= (int)$r['pagamento_id'] ?></td>
              <td><?= dtbr($r['_data_ref']) ?></td>
              <td class="<?= $moneyClass ?>"><?= brl($r['valor']) ?></td>
              <td><?= e($r['frigorifico_nome']) ?></td>
              <td>#VDA-<?= (int)$r['pedido_id'] ?></td>
              <td><?= brl($r['valor_total_fazenda']) ?></td>
              <td><span class="status-badge <?= e($r['_status_badge']) ?>"><?= e($r['_status_label']) ?></span></td>
              <td>
                <button class="btn" 
  onclick="verDetalhesPagamento(<?= (int)$r['pagamento_id'] ?>)">
  <i class="fas fa-eye"></i> Detalhes
</button>

              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <a class="page <?= $i===$page ? 'active' : '' ?>" href="<?= e(build_qs(['page'=>$i])) ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

 <!-- ========== MODAL: Detalhes do Pagamento (sem trocar de página) ========== -->
<div id="modalPagamento" class="modal">
  <div class="modal-content">
    <button class="close-btn" onclick="fecharModalPagamento()">&times;</button>
    <h3>Detalhes do Pagamento #<span id="pg-id"></span></h3>

    <h4>Pagamento</h4>
    <div class="modal-row"><strong>Status:</strong>  <span id="pg-status-badge"></span></div>
    <div class="modal-row"><strong>Método:</strong>  <span id="pg-metodo"></span></div>
    <div class="modal-row"><strong>Valor Total:</strong>  <span id="pg-valor"></span></div>
    <div class="modal-row"><strong>Moeda:</strong>  <span id="pg-moeda"></span></div>
    <div class="modal-row"><strong>Criado em:</strong>  <span id="pg-criado"></span></div>
    <div class="modal-row"><strong>Confirmado em:</strong>  <span id="pg-confirmado"></span></div>
    <div class="modal-row"><strong>Ref. Externa:</strong>  <span id="pg-ref"></span></div>
    <div class="modal-row"><strong>Pedido:</strong>  <span id="pg-pedido"></span></div>

    <hr class="modal-hr">

    <h4>Frigorífico</h4>
    <div class="modal-row"><strong>Nome/Razão:</strong>  <span id="pg-frig-nome"></span></div>
    <div class="modal-row"><strong>CNPJ:</strong>  <span id="pg-frig-cnpj"></span></div>
    <div class="modal-row"><strong>E-mail:</strong>  <span id="pg-frig-email"></span></div>
    <div class="modal-row"><strong>Telefone:</strong>  <span id="pg-frig-tel"></span></div>
    <div class="modal-row"><strong>Endereço:</strong>  <span id="pg-frig-end"></span></div>

    <hr class="modal-hr">

    <h4>Totais da Sua Venda</h4>
    <div class="modal-row"><strong>Bruto:</strong>  <span id="pg-bruto"></span></div>
    <div class="modal-row"><strong>Taxas:</strong>  <span id="pg-taxa"></span></div>
    <div class="modal-row"><strong>Líquido:</strong>  <span id="pg-liquido" style="font-weight:700;"></span></div>
    <div class="modal-row"><strong>Total de Cabeças:</strong>  <span id="pg-cabs"></span></div>

    <div id="pg-extra-bloco" style="display:none;">
      <hr class="modal-hr">
      <h4 id="pg-extra-titulo"></h4>
      <div class="modal-row"><strong>Detalhes:</strong>  <span id="pg-extra" style="white-space:pre-wrap"></span></div>
    </div>

    <hr class="modal-hr">

    <h4>Repasses por Item/Lote</h4>
    <div style="overflow:auto">
      <table class="modal-table" id="pg-itens-table">
        <thead>
          <tr>
            <th>#Repasse</th>
            <th>Lote</th>
            <th>Qtd</th>
            <th>Preço Unit. (R$)</th>
            <th>Valor Item (R$)</th>
            <th>Status</th>
            <th>Bruto (R$)</th>
            <th>Taxa (%)</th>
            <th>Taxa (R$)</th>
            <th>Líquido (R$)</th>
            <th>Previsto</th>
            <th>Pago</th>
          </tr>
        </thead>
        <tbody id="pg-itens-body"></tbody>
      </table>
    </div>

    <div class="modal-footer-actions">
      <button class="btn" onclick="fecharModalPagamento()">Fechar</button>
      <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    </div>
  </div>
</div>
<script>
const modalPg = document.getElementById('modalPagamento');

function badgeByStatusPagamento(st) {
  if (st === 'APROVADO')   return {text:'Recebido', cls:'badge badge-ok'};
  if (st === 'PENDENTE')   return {text:'Pendente', cls:'badge badge-warn'};
  if (st === 'EXPIRADO')   return {text:'Atrasado', cls:'badge badge-err'};
  if (st === 'RECUSADO' || st === 'CANCELADO') return {text:'Estornado/Cancelado', cls:'badge badge-neutral'};
  return {text:(st||'-'), cls:'badge badge-warn'};
}

async function verDetalhesPagamento(id) {
  try {
    const resp = await fetch('ajax_detalhe_pagamento.php?id=' + encodeURIComponent(id), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const raw = await resp.text();   // <- pega texto bruto
    let d;
    try { d = JSON.parse(raw); }     // <- tenta parsear JSON
    catch (e) {
      alert('Resposta do servidor não é JSON.\n\n' + raw);
      return;
    }
    if (!resp.ok || d.erro) {
      alert(d.erro || ('Erro HTTP ' + resp.status));
      return;
    }

    // header
    document.getElementById('pg-id').textContent = d.id;
    const b = badgeByStatusPagamento(d.status_raw);
    document.getElementById('pg-status-badge').innerHTML = `<span class="${b.cls}">${b.text}</span>`;
    document.getElementById('pg-metodo').textContent = d.metodo || '-';
    document.getElementById('pg-valor').textContent = d.valor || '-';
    document.getElementById('pg-moeda').textContent = d.moeda || 'BRL';
    document.getElementById('pg-criado').textContent = d.criado_em || '-';
    document.getElementById('pg-confirmado').textContent = d.confirmado_em || '-';
    document.getElementById('pg-ref').textContent = d.referencia_externa || '-';
    document.getElementById('pg-pedido').textContent = d.pedido ? ('#VDA-' + d.pedido) : '-';

    // frigorífico
    document.getElementById('pg-frig-nome').textContent = d.frigorifico.nome || '-';
    document.getElementById('pg-frig-cnpj').textContent = d.frigorifico.cnpj || '-';
    document.getElementById('pg-frig-email').textContent = d.frigorifico.email || '-';
    document.getElementById('pg-frig-tel').textContent = d.frigorifico.telefone || '-';
    document.getElementById('pg-frig-end').textContent = d.frigorifico.endereco || '-';

    // totais
    document.getElementById('pg-bruto').textContent = d.totais.bruto || '-';
    document.getElementById('pg-taxa').textContent = d.totais.taxa || '-';
    document.getElementById('pg-liquido').textContent = d.totais.liquido || '-';
    document.getElementById('pg-cabs').textContent = d.totais.cabecas || '-';

    // extra (PIX/cartão)
    if (d.extra && d.extra.titulo && d.extra.texto) {
      document.getElementById('pg-extra-bloco').style.display = 'block';
      document.getElementById('pg-extra-titulo').textContent = d.extra.titulo;
      document.getElementById('pg-extra').textContent = d.extra.texto;
    } else {
      document.getElementById('pg-extra-bloco').style.display = 'none';
      document.getElementById('pg-extra-titulo').textContent = '';
      document.getElementById('pg-extra').textContent = '';
    }

    // itens
    const tbody = document.getElementById('pg-itens-body');
    tbody.innerHTML = '';
    if (Array.isArray(d.itens) && d.itens.length) {
      d.itens.forEach(it => {
        const badge = (it.status_repasse === 'PAGO') ? '<span class="badge badge-ok">Pago</span>'
                     : (it.status_repasse === 'AGENDADO') ? '<span class="badge badge-warn">Agendado</span>'
                     : (it.status_repasse === 'AGUARDANDO') ? '<span class="badge badge-warn">Aguardando</span>'
                     : (it.status_repasse === 'CANCELADO') ? '<span class="badge badge-neutral">Cancelado</span>'
                     : `<span class="badge badge-warn">${(it.status_repasse||'-')}</span>`;

        const loteTxt = (it.codigo_lote ? ('Lote '+it.codigo_lote) : 'Lote s/ código')
                        + (it.raca ? ('<br><span class="muted">Raça:</span> '+it.raca) : '')
                        + (it.peso_medio ? (' • <span class="muted">Peso médio:</span> '+it.peso_medio+' kg') : '');

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>#RPS-${it.repasse_id}</td>
          <td>${loteTxt}</td>
          <td>${it.qtd}</td>
          <td>${it.preco_unit}</td>
          <td>${it.valor_item}</td>
          <td>${badge}</td>
          <td>${it.bruto}</td>
          <td>${it.taxa_percent}</td>
          <td>${it.taxa}</td>
          <td><strong>${it.liquido}</strong></td>
          <td>${it.previsto}</td>
          <td>${it.pago}</td>
        `;
        tbody.appendChild(tr);
      });
    } else {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="12" style="text-align:center; color:#777; padding:14px">Nenhum repasse encontrado.</td>';
      tbody.appendChild(tr);
    }

    modalPg.style.display = 'block';
  } catch (err) {
    alert('Falha na requisição: ' + err.message);
    console.error(err);
  }
}

function fecharModalPagamento(){ modalPg.style.display='none'; }
window.addEventListener('click', (ev) => { if (ev.target === modalPg) fecharModalPagamento(); });
</script>


</body>
</html>