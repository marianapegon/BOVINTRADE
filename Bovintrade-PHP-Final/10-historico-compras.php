<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();

// Proteção de rota
if (empty($_SESSION['usuario'])) {
  header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
  if ($u['tipo_usuario'] === 'FAZENDA')        { header('Location: 02-painel-fazenda.php'); exit; }
  if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
  header('Location: login.php'); exit;
}

$nome  = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');

require 'conexao.php';

// --- Helpers ---
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts){ return date('d/m/Y H:i', strtotime($ts)); }
function status_label_class($s){
  switch ($s) {
    case 'PAGO': return ['Concluída', 'status-completed'];
    case 'AGUARDANDO_PAGAMENTO': return ['Aguardando pagamento', 'status-pending'];
    case 'CANCELADO': return ['Cancelada', 'status-canceled'];
    case 'CRIADO': return ['Criado', 'status-intransit']; // placeholder
    default: return [ucfirst(strtolower($s)), 'status-pending'];
  }
}

// --- Filtros (GET) ---
$periodo = $_GET['periodo'] ?? '30'; // '30','90','ano','custom'
$status  = $_GET['status']  ?? 'todos'; // 'todos','PAGO','AGUARDANDO_PAGAMENTO','CANCELADO'
$ini     = $_GET['ini']     ?? ''; // custom ini (YYYY-MM-DD)
$fim     = $_GET['fim']     ?? ''; // custom fim (YYYY-MM-DD)
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page - 1) * $limit;

// Monta WHERE
$where = "p.frigorifico_id = ?";
$params = [(int)$u['id']];
$types  = "i";

// período
if ($periodo === '30') {
  $where .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($periodo === '90') {
  $where .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
} elseif ($periodo === 'ano') {
  $where .= " AND YEAR(p.created_at) = YEAR(NOW())";
} elseif ($periodo === 'custom' && $ini && $fim) {
  $where .= " AND DATE(p.created_at) BETWEEN ? AND ?";
  $params[] = $ini; $types .= 's';
  $params[] = $fim; $types .= 's';
}

// status
$allowed_status = ['PAGO','AGUARDANDO_PAGAMENTO','CANCELADO','CRIADO'];
if (in_array($status, $allowed_status, true)) {
  $where .= " AND p.status = ?";
  $params[] = $status; $types .= 's';
}

// --- Export CSV (opcional) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $sql = "
    SELECT p.id, p.created_at, p.total_pedido, p.status,
           COUNT(pi.id) AS itens_count,
           GROUP_CONCAT(DISTINCT u.nome_razao ORDER BY u.nome_razao SEPARATOR ' | ') AS fazendas
    FROM pedidos p
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    JOIN usuarios u ON u.id = pi.fazenda_id
    WHERE $where
    GROUP BY p.id
    ORDER BY p.id DESC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=historico_compras.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID Compra','Data','Fazendas','Qtd Itens','Valor','Status']);
  while ($r = $res->fetch_assoc()) {
    [$label, ] = status_label_class($r['status']);
    fputcsv($out, [
      '#'.$r['id'],
      dtbr($r['created_at']),
      $r['fazendas'],
      (int)$r['itens_count'],
      number_format((float)$r['total_pedido'], 2, ',', '.'),
      $label
    ]);
  }
  fclose($out);
  exit;
}

// --- Consulta paginada ---
$sql_count = "
  SELECT COUNT(*) AS total
  FROM (
    SELECT p.id
    FROM pedidos p
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    WHERE $where
    GROUP BY p.id
  ) t
";
$stmt = $conn->prepare($sql_count);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = max(1, (int)ceil($total_rows / $limit));

$sql = "
  SELECT p.id, p.created_at, p.total_pedido, p.status,
         COUNT(pi.id) AS itens_count,
         GROUP_CONCAT(DISTINCT pi.codigo_lote ORDER BY pi.id SEPARATOR ', ') AS codigos,
         GROUP_CONCAT(DISTINCT u.nome_razao ORDER BY u.nome_razao SEPARATOR ' | ') AS fazendas
  FROM pedidos p
  JOIN pedido_itens pi ON pi.pedido_id = p.id
  JOIN usuarios u ON u.id = pi.fazenda_id
  WHERE $where
  GROUP BY p.id
  ORDER BY p.id DESC
  LIMIT ? OFFSET ?
";
$params_paged = $params;
$types_paged  = $types . "ii";
$params_paged[] = $limit; $params_paged[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types_paged, ...$params_paged);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>BovinTrade - Histórico de Compras</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #a30000;
      --primary-dark: #7a0000;
      --text: #333333;
      --text-light: #666666;
      --background: #ffffff;
      --border: #e0e0e0;
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
    .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;}
    .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
    .dashboard-actions { display:flex; gap:1rem;}
    .btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem;}
    .btn-primary { background-color: var(--primary); color:white;}
    .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
    .btn-outline { background-color:transparent; color:var(--primary); border:1px solid var(--primary);}
    .btn-outline:hover { background-color: rgba(163,0,0,0.05);}
    .btn-success { background-color: var(--success); color:white;}
    .btn-success:hover { background-color:#3d8b40; transform: translateY(-1px); box-shadow:0 4px 8px rgba(76,175,80,0.2);}
    .btn-danger { background-color:#f44336; color:white;}
    .btn-danger:hover { background-color:#d32f2f; transform: translateY(-1px); box-shadow:0 4px 8px rgba(244,67,54,0.2);}
    .btn-sm { padding:0.5rem 1rem; font-size:0.85rem;}
    .btn-block { width:100%; justify-content:center; }
    .history-container { background: var(--background); border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
    .history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    .history-title { font-size: 1.8rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 1rem; }
    .filters { display: flex; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .filter-group { display: flex; flex-direction: column; min-width: 200px; }
    .filter-group label { margin-bottom: 0.5rem; font-weight: 500; color: var(--text); }
    .filter-group select, .filter-group input[type="date"] { padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; }
    .filter-group .date-range { display: flex; gap: .5rem; }
    #custom-dates { display: none; }
    .purchases-table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: var(--background); border-radius: 12px; overflow: hidden; }
    .purchases-table th { background-color: var(--primary); color: white; padding: 1rem; text-align: left; font-weight: 600; }
    .purchases-table td { padding: 1rem; border-bottom: 1px solid var(--border); }
    .purchases-table tr:hover { background-color: rgba(163,0,0,0.05); }
    .status-badge { padding: .35rem .75rem; border-radius: 20px; font-size: .85rem; font-weight: 500; display: inline-block; }
    .status-completed { background: rgba(76,175,80,0.1); color: var(--success); }
    .status-pending { background: rgba(255,152,0,0.1); color: var(--warning); }
    .status-canceled { background: rgba(244,67,54,0.1); color: #f44336; }
    .view-details { color: var(--primary); text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: .5rem; }
    .view-details:hover { text-decoration: underline; }
    .pagination { display: flex; justify-content: center; margin-top: 2rem; gap: .5rem; }
    .page-item { padding: .5rem 1rem; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; background: #fff; text-decoration: none; color: var(--text); }
    .page-item.active { background-color: var(--primary); color: white; border-color: var(--primary); }
    /* Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: .3s; }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal { background: #fff; border-radius: 12px; width: 90%; max-width: 900px; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 8px 24px rgba(0,0,0,.15); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
    .modal-title { font-size: 1.4rem; font-weight: 600; color: var(--primary); }
    .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light); }

    /* Responsividade */
    @media (max-width: 768px) {
      .hamburger { display: block; }
      .user-menu { gap: 1rem; }
      .user-menu span { display: none; }
      .container {
        flex-direction: column;
      }
      .sidebar {
        width: 100%;
        transform: translateX(-100%);
        position: fixed;
        top: 76px;
        left: 0;
        height: calc(100vh - 76px);
        z-index: 1000;
        overflow-y: auto;
        box-shadow: none;
        border-right: none;
      }
      .sidebar.active {
        transform: translateX(0);
      }
      .resizer {
        display: none;
      }
      .main {
        padding: 1rem;
        width: 100%;
      }
      .history-container {
        padding: 1.5rem;
      }
      .filters {
        flex-direction: column;
      }
      .filter-group {
        min-width: auto;
      }
      .dashboard-title {
        font-size: 1.5rem;
      }
      .purchases-table th,
      .purchases-table td {
        padding: 0.75rem 0.5rem;
      }
    }

    @media (max-width: 480px) {
      header {
        padding: 1rem;
      }
      .logo {
        font-size: 1.5rem;
      }
      .user-menu {
        gap: 0.5rem;
      }
      .main {
        padding: 0.5rem;
      }
      .history-container {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
<header>
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">
      🐄
      <span>BovinTrade • Frigorífico</span>
    </div>
    <div class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </div>
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
  <a href="07-painel-frigorifico.php" 
     class="menu-item <?= $current_page === '07-painel-frigorifico.php' ? 'active' : '' ?>">
     <i class="fas fa-home"></i><span>Painel</span>
  </a>

  <a href="meu-carrinho.php" 
     class="menu-item <?= $current_page === 'meu-carrinho.php' ? 'active' : '' ?>">
     <i class="fas fa-shopping-cart"></i><span>Meu Carrinho</span>
  </a>

  <a href="pesquisa-lotes.php" 
     class="menu-item <?= $current_page === 'pesquisa-lotes.php' ? 'active' : '' ?>">
     <i class="fas fa-search"></i><span>Pesquisa de Lotes</span>
  </a>

  <a href="09-recebimento-lotes.php" 
     class="menu-item <?= $current_page === '09-recebimento-lotes.php' ? 'active' : '' ?>">
     <i class="fas fa-truck-loading"></i><span>Recebimento</span>
  </a>

  <a href="10-historico-compras.php" 
     class="menu-item <?= $current_page === '10-historico-compras.php' ? 'active' : '' ?>">
     <i class="fas fa-history"></i><span>Histórico de Compras</span>
  </a>

  <a href="11-historico-pagamentos.php" 
     class="menu-item <?= $current_page === '11-historico-pagamentos.php' ? 'active' : '' ?>">
     <i class="fas fa-credit-card"></i><span>Histórico de Pagamento</span>
  </a>

 <a href="autorizar-coleta-frig.php" 
     class="menu-item <?= $current_page === 'autorizar-coleta-frig.php' ? 'active' : '' ?>">
     <i class="fas fa-check"></i><span>Autorizar Coleta de Lote</span>
  </a>
  
  <a href="historico-transporte-frig.php" 
     class="menu-item <?= $current_page === 'historico-transporte-frig.php' ? 'active' : '' ?>">
     <i class="fas fa-truck"></i><span>Histórico de Transportes</span>
  </a>

  <a href="12-avaliacoes.php" 
     class="menu-item <?= $current_page === '12-avaliacoes.php' ? 'active' : '' ?>">
     <i class="fas fa-star"></i><span>Avaliações</span>
  </a>

  <a href="notificacoes-frigorifico.php" 
     class="menu-item <?= $current_page === 'notificacoes-frigorifico.php' ? 'active' : '' ?>">
     <i class="fas fa-bell"></i><span>Notificações</span>
  </a>

  <a href="17-ajuda.php" 
     class="menu-item <?= $current_page === '17-ajuda.php' ? 'active' : '' ?>">
     <i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span>
  </a>

  <a href="meu-perfil-frigorifico.php" 
     class="menu-item <?= $current_page === 'meu-perfil-frigorifico.php' ? 'active' : '' ?>">
     <i class="fas fa-user-cog"></i><span>Meu Perfil</span>
  </a>
</ul>
  </aside>
  <div class="resizer"></div>
  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-history"></i> Histórico de Compras</h1>
    </div>

    <div class="history-container">
      <div class="history-header">
        <div class="dashboard-actions">
          <form method="get" style="display:inline">
            <!-- preserva filtros no export -->
            <input type="hidden" name="periodo" value="<?= e($periodo) ?>">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php if ($periodo==='custom'): ?>
              <input type="hidden" name="ini" value="<?= e($ini) ?>">
              <input type="hidden" name="fim" value="<?= e($fim) ?>">
            <?php endif; ?>
            <button class="btn btn-outline" name="export" value="csv"><i class="fas fa-download"></i> Exportar</button>
          </form>
        </div>
      </div>

      <form class="filters" method="get">
        <div class="filter-group">
          <label>Período</label>
          <select name="periodo" onchange="toggleCustom(this.value)">
            <option value="30"  <?= $periodo==='30'?'selected':'' ?>>Últimos 30 dias</option>
            <option value="90"  <?= $periodo==='90'?'selected':'' ?>>Últimos 90 dias</option>
            <option value="ano" <?= $periodo==='ano'?'selected':'' ?>>Este ano</option>
            <option value="custom" <?= $periodo==='custom'?'selected':'' ?>>Personalizado</option>
          </select>
        </div>
        <div class="filter-group" id="custom-dates" style="display:<?= $periodo==='custom'?'block':'none' ?>;">
          <label>De / Até</label>
          <div class="date-range">
            <input type="date" name="ini" value="<?= e($ini) ?>">
            <input type="date" name="fim" value="<?= e($fim) ?>">
          </div>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="status">
            <option value="todos" <?= $status==='todos'?'selected':'' ?>>Todos</option>
            <option value="PAGO" <?= $status==='PAGO'?'selected':'' ?>>Concluídas</option>
            <option value="AGUARDANDO_PAGAMENTO" <?= $status==='AGUARDANDO_PAGAMENTO'?'selected':'' ?>>Aguardando pagamento</option>
            <option value="CANCELADO" <?= $status==='CANCELADO'?'selected':'' ?>>Canceladas</option>
            <option value="CRIADO" <?= $status==='CRIADO'?'selected':'' ?>>Criado</option>
          </select>
        </div>
        <div class="filter-group" style="align-self:end">
          <button class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
        </div>
      </form>

      <table class="purchases-table">
        <thead>
          <tr>
            <th>ID Compra</th>
            <th>Data</th>
            <th>Lotes</th>
            <th>Vendedor(es)</th>
            <th>Valor</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7">Nenhuma compra encontrada.</td></tr>
        <?php else: foreach ($rows as $r):
          [$label,$cls] = status_label_class($r['status']);
        ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e(dtbr($r['created_at'])) ?></td>
            <td><?= e($r['codigos'] ?: "{$r['itens_count']} lote(s)") ?></td>
            <td><?= e($r['fazendas']) ?></td>
            <td><?= brl($r['total_pedido']) ?></td>
            <td><span class="status-badge <?= e($cls) ?>"><?= e($label) ?></span></td>
            <td>
              <a href="#" class="view-details" data-id="<?= (int)$r['id'] ?>">
                <i class="fas fa-eye"></i> Detalhes
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div class="pagination">
        <?php for ($p=1;$p<=$total_pages;$p++): 
          $q = $_GET; $q['page']=$p; $url = '?'.http_build_query($q);
        ?>
          <a class="page-item <?= $p===$page?'active':'' ?>" href="<?= e($url) ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </main>
</div>

<!-- Modal Detalhes -->
<div class="modal-overlay" id="purchaseDetailsModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Detalhes da Compra <span id="mdl-pedido-id"></span></h2>
      <button class="close-modal" onclick="closeModal()">&times;</button>
    </div>
    <div id="modal-body">Carregando...</div>
  </div>
</div>

<script>
function toggleCustom(val){
  document.getElementById('custom-dates').style.display = (val==='custom') ? 'block' : 'none';
}

const modal = document.getElementById('purchaseDetailsModal');
const modalBody = document.getElementById('modal-body');
function openModal(){ modal.classList.add('active'); }
function closeModal(){ modal.classList.remove('active'); }

document.querySelectorAll('.view-details').forEach(a=>{
  a.addEventListener('click', e=>{
    e.preventDefault();
    const id = a.dataset.id;
    document.getElementById('mdl-pedido-id').textContent = '#'+id;
    modalBody.innerHTML = 'Carregando...';
    openModal();
    fetch('detalhes-pedido.php?id='+id)
      .then(r=>r.text())
      .then(html=> modalBody.innerHTML = html)
      .catch(()=> modalBody.innerHTML = 'Erro ao carregar detalhes.');
  });
});

modal.addEventListener('click', function(e){
  if (e.target === modal) closeModal();
});

// Resizer functionality
let isResizing = false;
const resizer = document.querySelector('.resizer');
const sidebar = document.querySelector('.sidebar');

resizer.addEventListener('mousedown', function(e) {
  isResizing = true;
  document.addEventListener('mousemove', resize);
  document.addEventListener('mouseup', stopResize);
});

function resize(e) {
  if (!isResizing) return;
  let newWidth = e.clientX - sidebar.getBoundingClientRect().left;
  if (newWidth < 200) newWidth = 200;
  let maxWidth = window.innerWidth - 100;
  if (newWidth > maxWidth) newWidth = maxWidth;
  sidebar.style.width = newWidth + 'px';
}

function stopResize() {
  isResizing = false;
  document.removeEventListener('mousemove', resize);
  document.removeEventListener('mouseup', stopResize);
}

// Mobile sidebar toggle
function toggleSidebar() {
  sidebar.classList.toggle('active');
}
</script>
</body>
</html>