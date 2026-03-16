<?php
// pagar_pix.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php';

function brl($v){ return 'R$ '.number_format((float)$v,2,',','.'); }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$pedido = (int)($_GET['pedido'] ?? 0);
$pag    = (int)($_GET['pag'] ?? 0);

$stmt = $conn->prepare("
  SELECT p.id, p.total_pedido, p.status AS pedido_status,
         pg.metodo, pg.status AS pg_status, pg.referencia_externa,
         COALESCE(pp.copia_cola,'') AS copia_cola
    FROM pedidos p
    JOIN pagamentos pg ON pg.pedido_id=p.id
    LEFT JOIN pagamentos_pix pp ON pp.pagamento_id=pg.id
   WHERE p.id=? AND pg.id=? AND pg.metodo='PIX'
   LIMIT 1
");
$stmt->bind_param('ii', $pedido, $pag);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) { http_response_code(404); echo 'Pagamento PIX não encontrado.'; exit; }

$pixCode = $row['copia_cola'] !== '' ? $row['copia_cola'] :
  ("BRPAY|PED={$pedido}|VAL=".number_format($row['total_pedido'],2,'.','')."|REF=".strtoupper(substr(sha1($row['referencia_externa']),0,12)));
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data='.urlencode($pixCode);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Meu Carrinho</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    /* --- Cole aqui todo o CSS do template que você enviou --- */
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
    * { box-sizing: border-box; margin:0; padding:0; }
    html, body { font-family: 'Montserrat', sans-serif; background-color: var(--background); color: var(--text); height: 100%; max-width: 100vw; overflow-x: hidden; line-height: 1.6; }
    header { background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: relative; z-index:100;}
    .logo { font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem;}
    .logo i { font-size:1.5rem; }
    .user-menu { display:flex; align-items:center; gap:1.5rem;}
    .user-menu a { color:white; text-decoration:none; font-weight:500; transition: opacity 0.2s;}
    .user-menu a:hover { opacity:0.9; }
    .user-avatar { width:40px; height:40px; border-radius:50%; background-color: rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; cursor:pointer; transition: background-color 0.2s; }
    .user-avatar:hover { background-color: rgba(255,255,255,0.3); }
    .container { display:flex; min-height: calc(100vh - 76px); }
    .sidebar { width:280px; background-color: var(--background); border-right:1px solid var(--border); padding:1.5rem 0; transition: transform 0.3s ease; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
    .sidebar-header { padding:0 1.5rem 1.5rem; border-bottom:1px solid var(--border); margin-bottom:1rem;}
    .sidebar-title { font-size:1rem; text-transform:uppercase; letter-spacing:1px; color: var(--text-light); font-weight:600; margin-bottom:0.5rem; }
    .sidebar-menu { list-style:none; padding:0; margin:0; }
    .menu-category { color: var(--text-light); font-size:0.85rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; padding:0.75rem 1.5rem; margin-top:1rem;}
    .menu-item { padding:0.75rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color: var(--text); text-decoration:none; font-weight:500; transition: all 0.2s; border-left:3px solid transparent;}
    .menu-item i { width:24px; text-align:center; color: var(--text-light);}
    .menu-item:hover { background-color: rgba(163,0,0,0.05); color: var(--primary); border-left:3px solid var(--primary);}
    .menu-item:hover i { color: var(--primary); }
    .menu-item.active { background-color: rgba(163,0,0,0.1); color: var(--primary); border-left:3px solid var(--primary);}
    .menu-item.active i { color: var(--primary);}
    .main { flex:1; padding:2.5rem; background-color:#f9f9f9; position:relative; overflow-y:auto;}
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
    .cart-container { display:flex; gap:2rem;}
    .cart-items { flex:2;}
    .cart-summary { flex:1;}
    .cart-card { background-color: var(--background); border-radius:10px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:1.5rem;}
    .cart-item-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border);}
    .cart-farm-name { font-weight:600; color:var(--primary); display:flex; align-items:center; gap:0.5rem;}
    .cart-item-details { display:flex; gap:1.5rem;}
    .cart-item-image { width:120px; height:120px; border-radius:8px; background-color:#f0f0f0; display:flex; align-items:center; justify-content:center; color: var(--text-light); overflow:hidden;}
    .cart-item-image img { width:100%; height:100%; object-fit:cover;}
    .cart-item-info { flex:1;}
    .cart-item-title { font-size:1.1rem; font-weight:600; margin-bottom:0.5rem;}
    .cart-item-description { font-size:0.9rem; color: var(--text-light); margin-bottom:1rem;}
    .cart-item-specs { display:grid; grid-template-columns:repeat(2,1fr); gap:0.75rem; margin-bottom:1rem;}
    .spec-item { display:flex; align-items:center; gap:0.5rem; font-size:0.9rem;}
    .spec-item i { color: var(--primary); width:20px; text-align:center;}
    .cart-item-footer { display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--border);}
    .cart-item-price { font-size:1.2rem; font-weight:700; color: var(--primary);}
    .cart-item-actions { display:flex; gap:0.5rem;}
    .quantity-control { display:flex; align-items:center; gap:0.5rem;}
    .quantity-btn { width:30px; height:30px; border-radius:50%; background-color: rgba(163,0,0,0.1); display:flex; align-items:center; justify-content:center; cursor:pointer; transition: all 0.2s; border:none;}
    .quantity-btn:hover { background-color: rgba(163,0,0,0.2);}
    .quantity-value { width:40px; text-align:center;}
    .summary-card { background-color: var(--background); border-radius:10px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:1.5rem;}
    .summary-title { font-size:1.2rem; font-weight:600; margin-bottom:1.5rem; color: var(--text); display:flex; align-items:center; gap:0.75rem;}
    .summary-row { display:flex; justify-content:space-between; margin-bottom:0.75rem;}
    .summary-total { font-weight:600; border-top:1px solid var(--border); padding-top:0.75rem; margin-top:0.75rem; font-size:1.1rem;}
    .summary-total .value { color: var(--primary); font-size:1.3rem;}
    .empty-cart { text-align:center; padding:3rem; background-color: var(--background); border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.05);}
    .empty-cart-icon { font-size:3rem; color: var(--text-light); margin-bottom:1.5rem;}
    .empty-cart-title { font-size:1.3rem; font-weight:600; margin-bottom:0.5rem;}
    .empty-cart-text { color: var(--text-light);}
  </style>
</head>
<body>
<header>
  <div class="logo">
    🐄
    <span>BovinTrade • Frigorífico</span>
  </div>
  <div class="user-menu">
    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
    </form>
    <div class="user-avatar"><i class="fas fa-user"></i></div>
  </div>
</header>

<div class="container">
  <aside class="sidebar">
    <ul class="sidebar-menu">
      <a href="07-painel-frigorifico.php" class="menu-item"><i class="fas fa-home"></i><span>Painel</span></a>
      <a href="meu-carrinho.php" class="menu-item"><i class="fas fa-shopping-cart"></i><span>Meu Carrinho</span></a>
      <a href="pesquisa-lotes.php" class="menu-item active"><i class="fas fa-search"></i><span>Pesquisa de Lotes</span></a>
      <a href="09-recebimento-lotes.php" class="menu-item"><i class="fas fa-truck-loading"></i><span>Recebimento</span></a>
      <a href="10-historico-compras.php" class="menu-item"><i class="fas fa-history"></i><span>Histórico de Compras</span></a>
      <a href="11-historico-pagamentos.php" class="menu-item"><i class="fas fa-credit-card"></i><span>Histórico de Pagamento</span></a>
      <a href="12-avaliacoes.php" class="menu-item"><i class="fas fa-star"></i><span>Avaliações</span></a>
      <a href="13-relatorios.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Relatórios</span></a>
      <a href="14-pedidos-pendentes.php" class="menu-item"><i class="fas fa-tasks"></i><span>Pedidos Pendentes</span></a>
      <a href="15-transportes.php" class="menu-item"><i class="fas fa-truck"></i><span>Transportes Agendados</span></a>
      <a href="16-notificacoes.php" class="menu-item"><i class="fas fa-bell"></i><span>Notificações</span></a>
      <a href="17-ajuda.php" class="menu-item"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
      <a href="meu-perfil-frigorifico.php" class="menu-item"><i class="fas fa-user-cog"></i><span>Meu Perfil</span></a>
    </ul>
  </aside>
</body>
</html>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Pagamento PIX</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    :root{ --primary:#a30000; --primary-dark:#7a0000; --text:#333; }
    body{ font-family:Montserrat,sans-serif; margin:0; background:#f9f9f9; color:var(--text) }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:#fff; padding:16px 24px; display:flex; justify-content:space-between; }
    .wrap{ max-width:900px; margin:24px auto; background:#fff; border:1px solid #eee; border-radius:12px; padding:20px; }
    .row{ display:flex; gap:16px; flex-wrap:wrap }
    .box{ flex:1 1 300px; border:1px solid #eee; border-radius:10px; padding:16px }
    pre{ background:#fafafa; border:1px solid #eee; padding:12px; border-radius:8px; overflow:auto }
    .btn{ padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
    .ok{ background:#2e7d32; color:#fff }
    .warn{ background:#c62828; color:#fff }
    .link{ color:var(--primary); text-decoration:none }
  </style>
</head>
<body>


<div class="wrap">
  <h2>Pagamento PIX — Pedido #<?= (int)$pedido ?></h2>
  <p><strong>Valor:</strong> <?= brl($row['total_pedido']) ?></p>

  <div class="row" style="margin-top:10px">
    <div class="box" style="text-align:center">
      <div><strong>QR Code</strong></div>
      <img src="<?= e($qrUrl) ?>" alt="QR Code PIX" width="240" height="240">
      <div style="color:#666;margin-top:6px">Escaneie no seu app bancário (simulado)</div>
    </div>
    <div class="box">
      <div><strong>Código “Copia e Cola”</strong></div>
      <pre id="pixcode"><?= e($pixCode) ?></pre>
      <button class="btn" onclick="navigator.clipboard.writeText(document.getElementById('pixcode').innerText).then(()=>alert('Código copiado!'))">
        <i class="fas fa-copy"></i> Copiar
      </button>
    </div>
  </div>

  <form method="post" action="finalizar_pagamento.php" style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap">
    <input type="hidden" name="pedido_id" value="<?= (int)$pedido ?>">
    <input type="hidden" name="pagamento_id" value="<?= (int)$pag ?>">
    <input type="hidden" name="metodo" value="PIX">
    <button class="btn ok"   name="acao" value="APROVADO"><i class="fas fa-check"></i> Realizar pagamento</button>
    <button class="btn warn" name="acao" value="CANCELADO"><i class="fas fa-ban"></i> Cancelar</button>
    <a class="link" href="meu-carrinho.php"><i class="fas fa-shopping-cart"></i> Voltar ao carrinho</a>
  </form>
</div>
</body>
</html>