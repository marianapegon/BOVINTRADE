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
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Painel do Frigorífico</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Fonte + Ícones -->
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
  </style>
</head>
<body>
<header>
  <div class="logo">
    🐄
    <span>BovinTrade • Frigorífico</span>
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

  <main class="main">
    <div class="welcome-card">
      <h2>Bem-vindo(a), <?php echo $nome; ?>!</h2>
      <p>Gerencie seus pedidos, pagamentos, recebimentos e histórico dentro da plataforma BovinTrade.</p>
    </div>
  </main>
</div>
</body>
</html>