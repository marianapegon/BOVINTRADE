<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once  'config.php'; // Conexão PDO

// Proteção: só Fazenda
if (empty($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FAZENDA') {
    header("Location: login.php");
    exit;
}

$u = $_SESSION['usuario'];
$fazenda_id = $u['id'];
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$email = e($u['email'] ?? '');
$msg = $_GET['msg'] ?? ''; // Para mensagens de feedback

// ----------------------------------------------------
// AÇÃO POST: CONFIRMAR RETIRADA (CHEGOU_NA_FAZENDA -> EM_TRANSITO_DESTINO)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transporte_id'], $_POST['acao']) && $_POST['acao'] === 'confirmar_retirada') {
    $transporte_id = (int)$_POST['transporte_id'];

    try {
        // Altera o status APENAS se estiver em CHEGOU_NA_FAZENDA e pertencer a esta fazenda
        $stmt = $pdo->prepare("UPDATE transportes SET 
            status = 'EM_TRANSITO_DESTINO', 
            atualizado_em = NOW() 
            WHERE id = :tid AND fazenda_id = :fid AND status = 'CHEGOU_NA_FAZENDA'");
        $stmt->execute([':tid' => $transporte_id, ':fid' => $fazenda_id]);

        if ($stmt->rowCount() > 0) {
            $msg = "✅ Retirada do lote #{$transporte_id} confirmada! O transporte iniciou o trajeto até o Frigorífico.";
        } else {
            throw new Exception("Status atual não permite a confirmação da retirada (Motorista não marcou 'Chegou na Fazenda' ou já foi confirmado).");
        }
    } catch (Throwable $e) {
        $msg = "❌ Erro ao processar a ação: " . $e->getMessage();
    }
    // Redireciona para evitar reenvio do formulário
    header('Location: monitorar-transportes-faz.php?msg=' . urlencode($msg));
    exit;
}

// ----------------------------------------------------
// CONSULTA: Buscar TODOS os transportes agendados pela Fazenda
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        t.id, 
        t.pedido_id, 
        t.data_retirada, 
        t.hora_retirada, 
        t.status,
        t.status_aceite,
        t.mensagem_transportadora,
        tr.nome_razao AS transportadora_nome,
        u_frig.nome_razao AS frigorifico_nome
    FROM transportes t
    JOIN usuarios tr ON tr.id = t.transportadora_id
    JOIN usuarios u_frig ON u_frig.id = t.frigorifico_id
    WHERE t.fazenda_id = :fid 
    ORDER BY t.data_retirada DESC
");
$stmt->execute([':fid' => $fazenda_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'monitorar-transportes-faz.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8" />
<title>BovinTrade - Status de Transportes</title>
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
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
body {
  font-family: 'Montserrat', sans-serif;
  background: #f9f9f9;
  color: var(--text);
  overflow-x: hidden;
}
header {
  background: linear-gradient(135deg, var(--primary-dark), var(--primary));
  color: white;
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.logo {
  font-size: 1.8rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.logo i {
  font-size: 1.6rem;
}
.hamburger {
  display: none;
  cursor: pointer;
  font-size: 1.5rem;
  color: white;
}
.user-menu {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}
.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}
.container {
  display: flex;
  min-height: calc(100vh - 76px);
  width: 100%;
}
.sidebar {
  width: 280px;
  background: var(--background);
  border-right: 1px solid var(--border);
  padding: 1.5rem 0;
  box-shadow: 2px 0 8px rgba(0,0,0,0.05);
  flex-shrink: 0;
  transition: transform 0.3s ease;
}
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
.sidebar-menu {
  list-style: none;
}
.menu-item {
  padding: 0.8rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: var(--text);
  text-decoration: none;
  font-weight: 500;
  border-left: 3px solid transparent;
  transition: 0.2s;
}
.menu-item i {
  width: 24px;
  text-align: center;
  color: var(--text-light);
}
.menu-item:hover {
  background-color: rgba(163,0,0,0.05);
  color: var(--primary);
  border-left: 3px solid var(--primary);
}
.menu-item.active {
  background-color: rgba(163,0,0,0.1);
  color: var(--primary);
  border-left: 3px solid var(--primary);
}
.main {
  flex: 1;
  padding: 2.5rem;
  min-width: 0;
}
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}
.dashboard-title {
  font-size: 1.8rem;
  font-weight: 600;
  color: var(--text);
}
.dashboard-actions {
  display: flex;
  gap: 1rem;
}
.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}
.btn-primary {
  background-color: var(--primary);
  color: white;
}
.btn-primary:hover {
  background-color: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(163,0,0,0.2);
}
.btn-outline {
  background-color: transparent;
  color: var(--primary);
  border: 1px solid var(--primary);
}
.btn-outline:hover {
  background-color: rgba(163,0,0,0.05);
}
.profile-container {
  background: var(--background);
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  border: 1px solid var(--border);
  max-width: 1000px;
  margin: auto;
  overflow-x: auto;
}
.profile-container h1 {
  color: var(--primary);
  font-size: 1.6rem;
  margin-bottom: 1.5rem;
  text-align: center;
}
table {
  width: 100%;
  border-collapse: collapse;
  table-layout: auto;
  margin-top: 1rem;
}
th, td {
  padding: 0.75rem;
  border: 1px solid var(--border);
  text-align: left;
  vertical-align: middle;
}
th {
  background-color: var(--primary);
  color: #fff;
  font-weight: 600;
}
tr:nth-child(even) {
  background-color: #f8f9fa;
}
.status-tag {
  padding: 4px 8px;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.85rem;
}
/* Mapeamento de Status */
.status-AGENDADO { background: #ffebee; color: #a30000; }
.status-RECUSADO { background: #f8d7da; color: #721c24; }
.status-CONFIRMADO, .status-AUTORIZADO { background: #fff3cd; color: #856404; }
.status-EM_TRANSITO_ORIGEM { background: #cce5ff; color: #004085; }
.status-CHEGOU_NA_FAZENDA { background: #d4edda; color: #155724; }
.status-EM_TRANSITO_DESTINO { background: #7ab3a3; color: #ffffff; }
.status-ENTREGUE { background: #d4edda; color: #155724; }
.status-CANCELADO { background: #f8d7da; color: #721c24; }
.msg-alerta {
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
  text-align: center;
  font-weight: 500;
}
.msg-alerta.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.msg-alerta.erro { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.btn-confirmar-retirada {
  background: #00a000;
  color: white;
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
}
/* Responsividade */
@media (max-width: 768px) {
  .hamburger { display: block; }
  .user-menu { gap: 1rem; }
  .user-menu span { display: none; }
  .container { flex-direction: column; }
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
  .sidebar.active { transform: translateX(0); }
  .resizer { display: none; }
  .main { padding: 1rem; width: 100%; }
  .profile-container { padding: 1.5rem; margin: 0; }
  table { display: block; overflow-x: auto; white-space: nowrap; }
  .dashboard-title { font-size: 1.5rem; }
}
@media (max-width: 480px) {
  header { padding: 1rem; }
  .logo { font-size: 1.5rem; }
  .user-menu { gap: 0.5rem; }
  .main { padding: 0.5rem; }
  .profile-container { padding: 1rem; }
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

            <a href="notificacoes-fazenda.php" class="menu-item <?= $current_page === 'notificacoes-fazenda.html' ? 'active' : '' ?>">
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

  <div class="resizer"></div>

  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-truck"></i> Monitoramento e Status dos Transportes</h1>
    </div>

    <div class="profile-container">
      <?php if ($msg): ?>
        <div class="msg-alerta <?= strpos($msg, '✅') !== false ? 'ok' : 'erro' ?>"><?= e(urldecode($msg)) ?></div>
      <?php endif; ?>

      <?php if (count($transportes) === 0): ?>
        <p style="text-align: center; color: var(--text-light);">Nenhum transporte agendado até o momento.</p>
      <?php else: ?>
        <table>
         <thead>
           <tr>
             <th>ID Transp.</th>
             <th>Pedido</th>
             <th>Frigorífico</th>
             <th>Transportadora</th>
             <th>Data Retirada</th>
             <th>Status Geral</th>
             <th>Ações/Feedback</th>
           </tr>
         </thead>
         <tbody>
           <?php foreach ($transportes as $t): 
             $display_status = ($t['status_aceite'] === 'RECUSADO') ? 'RECUSADO' : $t['status'];
           ?>
             <tr>
               <td><?= e($t['id']) ?></td>
               <td><?= e($t['pedido_id']) ?></td>
               <td><?= e($t['frigorifico_nome']) ?></td>
               <td><?= e($t['transportadora_nome']) ?></td>
               <td><?= date('d/m/Y', strtotime($t['data_retirada'])) ?> às <?= substr($t['hora_retirada'], 0, 5) ?></td>
               <td>
                 <span class="status-tag status-<?= e($display_status) ?>">
                   <?= e($display_status) ?>
                 </span>
               </td>
               <td>
                 <?php if ($t['status'] === 'CHEGOU_NA_FAZENDA'): ?>
                   <form method="post" style="display:inline;">
                     <input type="hidden" name="acao" value="confirmar_retirada">
                     <input type="hidden" name="transporte_id" value="<?= e($t['id']) ?>">
                     <button type="submit" class="btn-confirmar-retirada">
                       <i class="fas fa-check-circle"></i> Confirmar Retirada
                     </button>
                   </form>
                 <?php else: ?>
                   <small><?= e($t['mensagem_transportadora'] ?? '') ?></small>
                 <?php endif; ?>
               </td>
             </tr>
           <?php endforeach; ?>
         </tbody>
       </table>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.querySelector('.sidebar').classList.toggle('active');
}
</script>
</body>
</html>