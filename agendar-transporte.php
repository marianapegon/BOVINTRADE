<?php
session_start();
require_once 'config.php'; // Conexão PDO

// Proteção: só frigorífico pode acessar
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    header("Location: login.php"); exit;
}

$u = $_SESSION['usuario'];
$frigorifico_id = $u['id'];
$email = htmlspecialchars($u['email']);
$nome = htmlspecialchars($u['nome_razao']);
$msg = "";

// Página atual para sidebar
$current_page = 'agendar-transporte.php';

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $pedido_id = $_POST['pedido_id'];
    $transportadora_id = $_POST['transportadora_id'];
    $motorista_id = $_POST['motorista_id'];
    $veiculo_id = $_POST['veiculo_id'];
    $data_retirada = $_POST['data_retirada'];
    $hora_retirada = $_POST['hora_retirada'];
    $distancia = $_POST['distancia'];

    try {
        $stmt = $pdo->prepare("INSERT INTO transportes 
            (pedido_id, transportadora_id, motorista_id, veiculo_id, data_retirada, hora_retirada, distancia_km, status)
            VALUES (:pedido_id, :transportadora_id, :motorista_id, :veiculo_id, :data_retirada, :hora_retirada, :distancia_km, 'AGENDADO')");
        $stmt->execute([
            ':pedido_id' => $pedido_id,
            ':transportadora_id' => $transportadora_id,
            ':motorista_id' => $motorista_id,
            ':veiculo_id' => $veiculo_id,
            ':data_retirada' => $data_retirada,
            ':hora_retirada' => $hora_retirada,
            ':distancia_km' => $distancia
        ]);
        $msg = "✅ Transporte agendado com sucesso!";
    } catch (Throwable $e) {
        $msg = "❌ Erro ao agendar: " . $e->getMessage();
    }
}

// Consultas para selects
$pedidos_stmt = $pdo->prepare("SELECT p.id, lb.descricao 
    FROM pedidos p
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    JOIN lote_bois lb ON lb.id = pi.lote_id
    JOIN pagamentos pg ON pg.pedido_id = p.id
    WHERE p.frigorifico_id = :fid AND pg.status='APROVADO'
    GROUP BY p.id");
$pedidos_stmt->execute([':fid' => $frigorifico_id]);
$pedidos = $pedidos_stmt->fetchAll(PDO::FETCH_ASSOC);

$transportadoras = $pdo->query("SELECT id, nome_razao FROM usuarios WHERE tipo_usuario='TRANSPORTADORA'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Agendar Transporte</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

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
    .card{ background:var(--background); padding:2rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.05); max-width:700px; margin:auto; }
    h2{ margin-bottom:1.5rem; color:var(--primary); text-align:center; }
    form{ display:flex; flex-direction:column; gap:1rem; }
    label{ font-weight:600; margin-bottom:.25rem; }
    select,input{ padding:.7rem; border:1px solid var(--border); border-radius:8px; width:100%; }
    button{ background:var(--primary); color:#fff; padding:.9rem; border:none; border-radius:8px; cursor:pointer; font-weight:600; }
    button:hover{ background:var(--primary-dark); }
    .msg{ padding:.8rem 1rem; border-radius:8px; margin-bottom:1rem; text-align:center; }
    .msg.ok{ background:#e6f8ec; color:#0a6b2b; border:1px solid #b5e3c7; }
    .msg.erro{ background:#fdecea; color:#a30000; border:1px solid #f5c2c0; }
  </style>
</head>
<body>
<header>
  <div class="logo">🐄<span>BovinTrade • Frigorífico</span></div>
  <div class="user-menu">
    <span><?= $email ?></span>
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

 <a href="agendar-transporte.php" 
     class="menu-item <?= $current_page === 'agendar-transporte.php' ? 'active' : '' ?>">
     <i class="fas fa-calendar"></i><span>Agendar Transporte</span>
  </a>

  <a href="12-avaliacoes.php" 
     class="menu-item <?= $current_page === '12-avaliacoes.php' ? 'active' : '' ?>">
     <i class="fas fa-star"></i><span>Avaliações</span>
  </a>

  <a href="13-relatorios.php" 
     class="menu-item <?= $current_page === '13-relatorios.php' ? 'active' : '' ?>">
     <i class="fas fa-chart-line"></i><span>Relatórios</span>
  </a>

  <a href="pedidos-pendentes.php" 
     class="menu-item <?= $current_page === 'pedidos-pendentes.php' ? 'active' : '' ?>">
     <i class="fas fa-tasks"></i><span>Pedidos Pendentes</span>
  </a>

  <a href="agendar-transportes.php" 
     class="menu-item <?= $current_page === 'agendar-transportes.php' ? 'active' : '' ?>">
     <i class="fas fa-truck"></i><span>Transportes Agendados</span>
  </a>

  <a href="16-notificacoes.php" 
     class="menu-item <?= $current_page === '16-notificacoes.php' ? 'active' : '' ?>">
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
    <div class="card">
      <h2>Agendar Transporte</h2>

      <?php if ($msg): ?>
        <div class="msg <?= strpos($msg,'Erro')!==false ? 'erro':'ok' ?>"><?= $msg ?></div>
      <?php endif; ?>

      <form method="POST" id="formTransporte">
        <div>
          <label>Pedido:</label>
          <select name="pedido_id" required>
            <option value="">-- Selecione --</option>
            <?php foreach ($pedidos as $p): ?>
              <option value="<?= $p['id'] ?>">Pedido <?= $p['id'] ?> - <?= $p['descricao'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Transportadora:</label>
          <select name="transportadora_id" id="transportadora" required>
            <option value="">-- Selecione --</option>
            <?php foreach ($transportadoras as $t): ?>
              <option value="<?= $t['id'] ?>"><?= $t['nome_razao'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>Motorista:</label>
          <select name="motorista_id" id="motorista" required>
            <option value="">-- Selecione a transportadora --</option>
          </select>
        </div>

        <div>
          <label>Veículo:</label>
          <select name="veiculo_id" id="veiculo" required>
            <option value="">-- Selecione a transportadora --</option>
          </select>
        </div>

        <div>
          <label>Data Retirada:</label>
          <input type="date" name="data_retirada" required>
        </div>

        <div>
          <label>Hora Retirada:</label>
          <input type="time" name="hora_retirada" required>
        </div>

        <div>
          <label>Distância (km):</label>
          <input type="number" name="distancia" min="1" required>
        </div>

        <button type="submit">Agendar</button>
      </form>
    </div>
  </main>
</div>

<script>
// AJAX para carregar motoristas e veículos ao selecionar transportadora
$('#transportadora').change(function(){
    let transportadora_id = $(this).val();
    if(transportadora_id){
        $.ajax({
            url: 'fetch_motoristas_veiculos.php',
            type: 'POST',
            data: {transportadora_id: transportadora_id},
            success: function(data){
                let obj = JSON.parse(data);
                $('#motorista').html(obj.motoristas);
                $('#veiculo').html(obj.veiculos);
            }
        });
    } else {
        $('#motorista').html('<option value="">-- Selecione a transportadora --</option>');
        $('#veiculo').html('<option value="">-- Selecione a transportadora --</option>');
    }
});
</script>
</body>
</html>
