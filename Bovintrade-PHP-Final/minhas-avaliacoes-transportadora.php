<?php
// Força o PHP a mostrar os erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
// O require 'conexao.php' deve estar aqui em cima
require_once  'config.php'; // Usa o seu arquivo de conexão

function e($s){ 
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$current_page = basename($_SERVER['PHP_SELF']); // Pega nome do arquivo atual

// --- 1. PROTEÇÃO DE ROTA (TRANSPORTADORA) ---
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'TRANSPORTADORA') {
    if ($u['tipo_usuario'] === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
    if ($u['tipo_usuario'] === 'FAZENDA')     { header('Location: 02-painel-fazenda.php'); exit; }
    header('Location: login.php'); exit;
}

$nome              = htmlspecialchars($u['nome_razao'] ?? 'Transportadora');
$email             = htmlspecialchars($u['email'] ?? '');
$transportadora_id = (int)$u['id']; // ID da transportadora logada

// --- 2. INICIALIZAÇÃO DE VARIÁVEIS ---
$mensagem_erro = '';
$avg_nota = 0;
$total_avaliacoes = 0;
$avaliacoes = [];

// Assumindo que conexao.php define $pdo
if (!isset($pdo)) {
    // Tenta usar $conn se $pdo não existir (fallback para seu código original)
    if (isset($conn)) {
         // Se $conn é mysqli, precisamos de um wrapper PDO ou reescrever as queries
         // Para este exemplo, vou assumir que config.php define $pdo como nos outros arquivos.
         // Se 'conexao.php' define $conn (mysqli), o código abaixo falhará.
         // Vou reescrever para PDO para manter consistência com seus outros arquivos.
         $pdo = $conn; // Má prática, mas para adaptar ao seu código.
         $fetch_mode = PDO::FETCH_ASSOC;
         $execute_method = 'execute';
         $fetch_all_method = 'fetchAll';
         $fetch_method = 'fetch';
    } else {
         die("Erro: Objeto de conexão PDO não encontrado. Verifique o arquivo config.php.");
    }
} else {
    // É PDO
    $fetch_mode = PDO::FETCH_ASSOC;
    $execute_method = 'execute';
    $fetch_all_method = 'fetchAll';
    $fetch_method = 'fetch';
}


try {
    // --- 3. BUSCAR DADOS DAS AVALIAÇÕES (LÓGICA CORRIGIDA) ---
    
    // Query 1: Calcular Média e Total
    $sqlAvg = "SELECT AVG(at.nota) as media_notas, COUNT(at.id) as total_avaliacoes 
               FROM avaliacoes_transporte at
               JOIN transportes t ON at.transporte_id = t.id
               WHERE t.transportadora_id = ?";
    $stmtAvg = $pdo->prepare($sqlAvg);
    $stmtAvg->$execute_method([$transportadora_id]);
    $stats = $stmtAvg->$fetch_method($fetch_mode);
    
    if ($stats) {
        $avg_nota = (float)($stats['media_notas'] ?? 0);
        $total_avaliacoes = (int)($stats['total_avaliacoes'] ?? 0);
    }

    // Query 2: Listar últimos comentários (COM SUB-NOTAS)
    $sqlList = "SELECT 
                    at.nota, 
                    at.comentario, 
                    at.data_avaliacao, 
                    at.avaliador_tipo,
                    t.pedido_id,
                    aval.nome_razao as avaliador_nome,
                    
                    -- Adicionando as sub-notas
                    at.pontualidade,
                    at.bem_estar_viagem,
                    at.condicao_veiculo

                FROM avaliacoes_transporte at
                JOIN transportes t ON at.transporte_id = t.id
                JOIN usuarios aval ON at.avaliador_id = aval.id
                WHERE t.transportadora_id = ?
                ORDER BY at.data_avaliacao DESC
                LIMIT 20";
    
    $stmtList = $pdo->prepare($sqlList);
    $stmtList->$execute_method([$transportadora_id]);
    $avaliacoes = $stmtList->$fetch_all_method($fetch_mode);

} catch (Exception $e) {
    $mensagem_erro = "Erro de Banco de Dados: " . $e->getMessage();
}

// Função helper para exibir estrelas
function exibir_estrelas($nota) {
    $nota_int = round($nota);
    $html = '<span class="stars-display">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $nota_int) {
            $html .= '<i class="fas fa-star" style="color: #f7d100;"></i>'; // Preenchida
        } else {
            $html .= '<i class="far fa-star" style="color: #ccc;"></i>'; // Vazia
        }
    }
    $html .= '</span>';
    return $html;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Minhas Avaliações (Transportadora)</title>
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
      --background-light: #f9f9f9;
    }
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:var(--background-light); color:var(--text); }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
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
    .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap: wrap;}
    .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
    .dashboard-actions { display:flex; gap:1rem;}
    .card { background: var(--background); border-radius: 12px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .alert-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500; }
    .no-data { color: var(--text-light); font-style: italic; }

    /* Card de Sumário */
    .summary-card {
      background: linear-gradient(135deg, var(--primary-dark), var(--primary)); /* Alterado para vermelho */
      color: white;
      padding: 2.5rem;
      border-radius: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }
    .summary-card .score {
      font-size: 3.5rem;
      font-weight: 700;
    }
    .summary-card .details {
      text-align: right;
    }
    .summary-card .stars-display { font-size: 1.5rem; }
    .summary-card .total { font-size: 1rem; font-weight: 500; margin-top: 0.5rem; }

    /* Lista de Comentários */
    .comment-list { list-style: none; }
    .comment-item {
      background: var(--background);
      border-radius: 8px;
      padding: 1.5rem;
      border: 1px solid var(--border);
      margin-bottom: 1rem;
    }
    .comment-item .header {
      display: flex;
      flex-wrap: wrap; /* Permite quebra de linha em telas menores */
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.75rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid var(--border);
      gap: 0.5rem; /* Espaçamento entre os itens */
    }
    .comment-item .header .evaluator { font-weight: 600; }
    .comment-item .header .date { font-size: 0.9rem; color: var(--text-light); }
    .comment-item .body {
      font-style: italic;
      color: var(--text-light);
    }
    .comment-item .body:empty::before {
      content: "Nenhum comentário fornecido.";
      color: #aaa;
      font-style: italic;
    }
    .badge {
      padding: 0.2em 0.5em;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: 600;
      margin-left: 0.5rem;
    }
    .badge-frigorifico { background-color: #f8d7da; color: #721c24; }
    .badge-fazenda { background-color: #d4edda; color: #155724; }

    /* --- NOVOS ESTILOS PARA SUB-NOTAS --- */
    .comment-metrics {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        justify-content: space-between;
        align-items: center;
    }
    .metric-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        flex-basis: 60%; /* Ocupa mais espaço */
    }
    .metric-item {
        font-size: 0.9rem;
        color: var(--text-light);
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }
    .metric-item strong {
        color: var(--text);
        min-width: 160px; /* Alinhamento */
        display: inline-block;
        margin-right: 10px;
    }
    .metric-average {
        text-align: center;
        flex-basis: 30%; /* Ocupa menos espaço */
        padding: 0.5rem;
        border-left: 2px solid var(--border);
    }
    .metric-average .avg-score {
        font-size: 1.8rem;
        font-weight: 600;
        color: var(--primary);
    }
    .metric-average .avg-label {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-light);
    }
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
          padding: 1.5rem;
          width: 100%;
        }
        .summary-card { flex-direction: column; text-align: center; gap: 1rem; }
        .summary-card .details { text-align: center; }
        .comment-metrics { flex-direction: column; align-items: stretch; gap: 1rem; }
        .metric-average { border-left: none; border-top: 2px solid var(--border); padding-top: 1rem; }
        .dashboard-header{ flex-direction:column; align-items:flex-start; gap:1rem; }
     }
     @media (max-width:480px){ header{ padding:1rem; } .logo{ font-size:1.5rem; } .user-menu span{ display:none; } .main{ padding:0.8rem; } }
  </style>
</head>
<body>
<header>
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">
      🐄
      <span>BovinTrade • Transportadora</span>
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
  <aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
      <a href="14-painel-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '14-painel-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Painel</span></a>
      <a href="cadastro-transporte.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-transporte.php' ? 'active' : ''; ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
      <a href="cadastro-motorista.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-motorista.php' ? 'active' : ''; ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
       <a href="gerenciar-motoristas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-motoristas.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
        <a href="gerenciar-transportes-transp.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-transportes-transp.php' ? 'active' : ''; ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
      <a href="pedidos-transportes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pedidos-transportes.php' ? 'active' : ''; ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
      <a href="coletas-agendadas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'coletas-agendadas.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
      <a href="rastreamento-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'rastreamento-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
      <a href="historico-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'historico-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
      <a href="notificacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'notificacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
      <a href="minhas-avaliacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'minhas-avaliacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
      <a href="17-ajudat.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '17-ajudat.php' ? 'active' : ''; ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
      <a href="meu-perfil-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'meu-perfil-transportadora.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
      </a>
    </ul>
  </aside>
  <div class="resizer"></div>

  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-star"></i> Minhas Avaliações</h1>
      <div class="dashboard-actions">
        <!-- Adicione ações se necessário, como exportar ou filtrar -->
      </div>
    </div>

    <?php if ($mensagem_erro): ?>
      <div class="alert alert-erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
    <?php endif; ?>

    <div class="summary-card">
      <div>
        <div class="score"><?php echo number_format($avg_nota, 1, ',', '.'); ?></div>
        <div class="stars-display"><?php echo exibir_estrelas($avg_nota); ?></div>
      </div>
      <div class="details">
        <h2>Média Geral</h2>
        <div class="total"><?php echo $total_avaliacoes; ?> avaliações recebidas</div>
      </div>
    </div>

    <div class="card">
      <h2>Comentários Recentes</h2>
      
      <?php if (empty($avaliacoes)): ?>
        <p class="no-data" style="margin-top: 1.5rem;">Você ainda não recebeu nenhuma avaliação.</p>
      <?php else: ?>
        <ul class="comment-list" style="margin-top: 1.5rem;">
          <?php foreach ($avaliacoes as $avaliacao): ?>
            <li class="comment-item">
              <div class="header">
                <div>
                  <span class="evaluator"><?php echo htmlspecialchars($avaliacao['avaliador_nome']); ?></span>
                  
                  <?php if ($avaliacao['avaliador_tipo'] == 'frigorifico'): ?>
                    <span class="badge badge-frigorifico">Frigorífico</span>
                  <?php elseif ($avaliacao['avaliador_tipo'] == 'fazenda'): ?>
                    <span class="badge badge-fazenda">Fazenda</span>
                  <?php endif; ?>
                  
                  <span style="margin: 0 0.5rem;">|</span>
                  <span>Pedido #<?php echo $avaliacao['pedido_id']; ?></span>
                </div>
                <div>
                  <?php echo exibir_estrelas($avaliacao['nota']); ?>
                  <span class="date" style="margin-left: 1rem;"><?php echo date('d/m/Y', strtotime($avaliacao['data_avaliacao'])); ?></span>
                </div>
              </div>
              <div class="body">
                <?php echo htmlspecialchars($avaliacao['comentario']); ?>
              </div>
              
              <?php
              // Array para guardar as sub-notas que existem
              $sub_notas = [];
              if (isset($avaliacao['pontualidade']) && $avaliacao['pontualidade'] > 0) {
                  $sub_notas['Pontualidade'] = (int)$avaliacao['pontualidade'];
              }
              if (isset($avaliacao['bem_estar_viagem']) && $avaliacao['bem_estar_viagem'] > 0) {
                  $sub_notas['Bem-Estar na Viagem'] = (int)$avaliacao['bem_estar_viagem'];
              }
              if (isset($avaliacao['condicao_veiculo']) && $avaliacao['condicao_veiculo'] > 0) {
                  $sub_notas['Condição do Veículo'] = (int)$avaliacao['condicao_veiculo'];
              }

              $media_sub_notas = 0;
              if (count($sub_notas) > 0) {
                  $media_sub_notas = array_sum($sub_notas) / count($sub_notas);
              }
              ?>

              <?php if (count($sub_notas) > 0): ?>
                <div class="comment-metrics">
                    <div class="metric-group">
                        <?php foreach ($sub_notas as $label => $nota): ?>
                            <div class="metric-item">
                                <strong><?php echo e($label); ?>:</strong>
                                <?php echo exibir_estrelas($nota); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="metric-average">
                        <div class="avg-score"><?php echo number_format($media_sub_notas, 1, ',', '.'); ?></div>
                        <div class="avg-label">Média (Critérios)</div>
                    </div>
                </div>
              <?php endif; ?>
              </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
// Função para alternar a sidebar em dispositivos móveis
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function() {
    // Lógica de fechamento do sidebar em mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger');
        if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
    
    // Resizer functionality (copiado do exemplo para a barra lateral redimensionável)
    let isResizing = false;
    const resizer = document.querySelector('.resizer');
    const sidebar = document.querySelector('.sidebar');
    const container = document.querySelector('.container');
    
    // Só adiciona funcionalidade de redimensionamento em telas maiores
    if (window.innerWidth > 768 && resizer) {
        resizer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isResizing = true;
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
            container.style.cursor = 'col-resize';
        });
    }

    function resize(e) {
        if (!isResizing) return;
        let newWidth = e.clientX - sidebar.getBoundingClientRect().left;
        if (newWidth < 200) newWidth = 200;
        let maxWidth = window.innerWidth - 100;
        if (newWidth > maxWidth / 2) newWidth = maxWidth / 2; 
        sidebar.style.width = newWidth + 'px';
    }

    function stopResize() {
        isResizing = false;
        document.removeEventListener('mousemove', resize);
        document.removeEventListener('mouseup', stopResize);
        container.style.cursor = '';
    }
});
</script>
</body>
</html>