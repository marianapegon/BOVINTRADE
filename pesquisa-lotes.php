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

require 'conexao.php'; // Conexão com o banco (mysqli em $conn)

// Inicializa filtros
$peso_min   = $_GET['peso_min']   ?? '';
$peso_max   = $_GET['peso_max']   ?? '';
$raca       = $_GET['raca']       ?? '';
$alimentacao= $_GET['alimentacao']?? '';
$localizacao= $_GET['localizacao']?? '';
$ordenar    = $_GET['ordenar']    ?? 'preco';

// Mapa de siglas para nomes completos (se no BD ficou salvo o nome completo)
$mapa_localizacao = [
  'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia',
  'CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás',
  'MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais',
  'PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí',
  'RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul',
  'RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo',
  'SE'=>'Sergipe','TO'=>'Tocantins'
];

// Converte a sigla enviada no GET para o nome completo salvo no BD (se aplicável)
if ($localizacao !== '' && isset($mapa_localizacao[$localizacao])) {
  $localizacao = $mapa_localizacao[$localizacao];
}

// Monta a query dinâmica
$query = "SELECT 
            id,
            codigo_lote,
            quantidade,
            peso_medio_kg,
            raca,
            preco,
            (quantidade * preco) AS preco_total,
            tipo_alimentacao,
            localizacao
          FROM lote_bois
          WHERE status = 'DISPONIVEL'";

$params = [];
$tipos  = '';

if ($peso_min !== '') {
  $query   .= " AND peso_medio_kg >= ?";
  $params[] = (float)$peso_min;
  $tipos   .= 'd';
}
if ($peso_max !== '') {
  $query   .= " AND peso_medio_kg <= ?";
  $params[] = (float)$peso_max;
  $tipos   .= 'd';
}
if ($raca !== '') {
  $query   .= " AND raca = ?";
  $params[] = $raca;
  $tipos   .= 's';
}
if ($alimentacao !== '') {
  $query   .= " AND tipo_alimentacao = ?";
  $params[] = $alimentacao;
  $tipos   .= 's';
}
if ($localizacao !== '') {
  $query   .= " AND localizacao = ?";
  $params[] = $localizacao;
  $tipos   .= 's';
}

// Ordenação com whitelist
$ordenar_allowed = ['preco', 'preco_total', 'peso_medio_kg', 'quantidade', 'localizacao'];
if (!in_array($ordenar, $ordenar_allowed, true)) {
  $ordenar = 'preco';
}
$query .= " ORDER BY $ordenar ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
  die('Erro na preparação da consulta: '.$conn->error);
}
if (!empty($params)) {
  $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>BovinTrade - Pesquisa de Lotes</title>
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
    .search-container {
      background: var(--background);
      border-radius: 12px;
      padding: 2.5rem;
      box-shadow: 0 4px 12px rgba(0,0,0,.05);
      margin-bottom: 2rem;
    }
    .search-filters {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .filter-group {
      margin-bottom: 1rem;
    }
    .filter-label {
      display: block;
      margin-bottom: .5rem;
      font-weight: 500;
      color: var(--text);
    }
    .filter-control, .filter-select {
      width: 100%;
      padding: .6rem .75rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 1rem;
    }
    .results-table-container {
      overflow-x: auto;
      margin-bottom: 1rem;
    }
    .results-table {
      width: 100%;
      min-width: 800px;
      border-collapse: collapse;
      background: var(--background);
      border-radius: 12px;
      overflow: hidden;
    }
    .results-table th,
    .results-table td {
      padding: 0.75rem;
      text-align: left;
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    .results-table th {
      background-color: var(--primary);
      color: white;
      font-weight: 600;
    }
    .results-table tr:hover {
      background-color: rgba(163,0,0,0.05);
    }
    .empty {
      background: #fff;
      border: 1px dashed var(--border);
      padding: 1.25rem;
      border-radius: 10px;
      text-align: center;
      color: var(--text-light);
    }
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,.5);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      max-width: 900px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
      box-shadow: 0 8px 24px rgba(0,0,0,.15);
    }
    .close-modal {
      position: absolute;
      top: 10px;
      right: 10px;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-light);
      background: none;
      border: none;
    }
    .search-actions { display:flex;gap:12px;align-items:center; }

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
      .search-container {
        padding: 1.5rem;
      }
      .search-filters {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      .dashboard-title {
        font-size: 1.5rem;
      }
      .results-table {
        font-size: 0.9rem;
        min-width: 600px;
      }
      .results-table th,
      .results-table td {
        padding: 0.5rem 0.25rem;
      }
      .search-actions {
        flex-direction: column;
        align-items: stretch;
      }
      .search-actions .filter-group {
        margin: 0.5rem 0;
      }
      .search-actions select {
        max-width: none;
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
      .search-container {
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
      <h1 class="dashboard-title"><i class="fas fa-search"></i> Pesquisa de Lotes</h1>
    </div>

    <div class="search-container">
      <form class="search-form" method="GET" action="">
        <div class="search-filters">
          <div class="filter-group">
            <label class="filter-label">Peso Mínimo (kg)</label>
            <input type="number" name="peso_min" class="filter-control" value="<?= htmlspecialchars($peso_min) ?>">
          </div>
          <div class="filter-group">
            <label class="filter-label">Peso Máximo (kg)</label>
            <input type="number" name="peso_max" class="filter-control" value="<?= htmlspecialchars($peso_max) ?>">
          </div>
          <div class="filter-group">
            <label class="filter-label">Raça</label>
            <select name="raca" class="filter-select">
              <option value="">Todas</option>
              <option value="Nelore"     <?= $raca==='Nelore'?'selected':'' ?>>Nelore</option>
              <option value="Angus"      <?= $raca==='Angus'?'selected':'' ?>>Angus</option>
              <option value="Brahman"    <?= $raca==='Brahman'?'selected':'' ?>>Brahman</option>
              <option value="Hereford"   <?= $raca==='Hereford'?'selected':'' ?>>Hereford</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label">Tipo de Alimentação</label>
            <select name="alimentacao" class="filter-select">
              <option value="">Todos</option>
              <option value="Pastagem"          <?= $alimentacao==='Pastagem'?'selected':'' ?>>Pastagem</option>
              <option value="Confinamento"      <?= $alimentacao==='Confinamento'?'selected':'' ?>>Confinamento</option>
              <option value="Semi-confinamento" <?= $alimentacao==='Semi-confinamento'?'selected':'' ?>>Semi-confinamento</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label">Localização</label>
            <select name="localizacao" class="filter-select">
              <option value="">Todas</option>
              <?php foreach($mapa_localizacao as $sigla => $nome): ?>
                <option value="<?= $sigla ?>" <?= (($_GET['localizacao']??'')===$sigla)?'selected':'' ?>><?= $nome ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="search-actions">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Pesquisar</button>
          <div class="filter-group" style="margin:0">
            <label class="filter-label" style="margin:0 8px 0 16px;">Ordenar por</label>
          </div>
          <select name="ordenar" class="filter-select" style="max-width:220px">
            <option value="preco"         <?= $ordenar==='preco'?'selected':'' ?>>Preço (unitário)</option>
            <option value="preco_total"   <?= $ordenar==='preco_total'?'selected':'' ?>>Preço Total</option>
            <option value="peso_medio_kg" <?= $ordenar==='peso_medio_kg'?'selected':'' ?>>Peso Médio</option>
            <option value="quantidade"    <?= $ordenar==='quantidade'?'selected':'' ?>>Quantidade</option>
            <option value="localizacao"   <?= $ordenar==='localizacao'?'selected':'' ?>>Localização</option>
          </select>
        </div>
      </form>
    </div>

    <?php if ($result->num_rows === 0): ?>
      <div class="empty"><i class="fas fa-info-circle"></i> Nenhum lote encontrado com os filtros aplicados.</div>
    <?php else: ?>
    <div class="results-table-container">
<table class="results-table">
  <thead>
    <tr>
      <th>Código</th>
      <th>Qtd.</th>
      <th>Peso Médio</th>
      <th>Raça</th>
      <th>Tipo de Alimentação</th>
      <th>Preço</th>
      <th>Preço Total</th>
      <th>Localização</th>
      <th>Detalhes</th>
      <th>Carrinho</th>
    </tr>
  </thead>
  <tbody>
    <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['codigo_lote']) ?></td>
        <td><?= htmlspecialchars($row['quantidade']) ?> cabeças</td>
        <td><?= htmlspecialchars($row['peso_medio_kg']) ?> kg</td>
        <td><?= htmlspecialchars($row['raca']) ?></td>
        <td><?= htmlspecialchars($row['tipo_alimentacao']) ?></td>
        <td>R$ <?= number_format((float)$row['preco'], 2, ',', '.') ?></td>
        <td>R$ <?= number_format((float)$row['preco_total'], 2, ',', '.') ?></td>
        <td><?= htmlspecialchars($row['localizacao']) ?></td>
        <td>
          <button class="btn btn-outline btn-sm" onclick="abrirModal(<?= (int)$row['id'] ?>)">Detalhes</button>
        </td>
        <td>
          <form action="adicionar-carrinho.php" method="post" style="margin:0;">
            <input type="hidden" name="id_lote" value="<?= (int)$row['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm">Adicionar</button>
          </form>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>
    <?php endif; ?>
  </main>
</div>

<!-- Modal -->
<div id="modal" class="modal">
  <div class="modal-content">
    <button class="close-modal" onclick="fecharModal()">&times;</button>
    <div id="modal-body"></div>
  </div>
</div>

<script>
function abrirModal(id){
  fetch('detalhes-lote.php?id=' + id)
    .then(res => res.text())
    .then(html => {
      document.getElementById('modal-body').innerHTML = html;
      document.getElementById('modal').style.display = 'flex';
    });
}
function fecharModal(){
  document.getElementById('modal').style.display = 'none';
}
window.onclick = function(ev){
  if (ev.target === document.getElementById('modal')) fecharModal();
}

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