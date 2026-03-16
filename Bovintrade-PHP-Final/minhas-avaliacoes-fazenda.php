<?php
// Força o PHP a mostrar os erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'conexao.php'; // Usa o seu arquivo de conexão (mysqli)

$current_page = basename($_SERVER['PHP_SELF']); // Pega nome do arquivo atual

// --- 1. PROTEÇÃO DE ROTA (FAZENDA) ---
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
    if ($u['tipo_usuario'] === 'FRIGORIFICO')    { header('Location: 07-painel-frigorifico.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

// --- Adicionando a função e() ---
function e($s){ 
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$nome       = htmlspecialchars($u['nome_razao'] ?? 'Fazenda');
$email      = htmlspecialchars($u['email'] ?? '');
$fazenda_id = (int)$u['id']; // ID da fazenda logada

// --- 2. INICIALIZAÇÃO DE VARIÁVEIS ---
$mensagem_erro = '';
$avg_nota = 0;
$total_avaliacoes = 0;
$avaliacoes = [];

try {
    // --- 3. BUSCAR DADOS DAS AVALIAÇÕES ---

    // Query 1: Calcular Média e Total
    $sqlAvg = "SELECT AVG(nota) as media_notas, COUNT(id) as total_avaliacoes 
               FROM avaliacoes_lote 
               WHERE fazenda_id = ?";
    $stmtAvg = $conn->prepare($sqlAvg);
    $stmtAvg->bind_param('i', $fazenda_id);
    $stmtAvg->execute();
    $resultAvg = $stmtAvg->get_result();
    $stats = $resultAvg->fetch_assoc();
    
    if ($stats) {
        $avg_nota = (float)($stats['media_notas'] ?? 0);
        $total_avaliacoes = (int)($stats['total_avaliacoes'] ?? 0);
    }

    // Query 2: Listar últimos comentários (COM SUB-NOTAS)
    $sqlList = "SELECT 
                    al.nota, 
                    al.comentario, 
                    al.data_avaliacao, 
                    pi.pedido_id,
                    frig.nome_razao as frigorifico_nome,

                    -- Adicionando as sub-notas
                    al.estrutura_corporal,
                    al.qualidade_carcaca,
                    al.saude_bem_estar,
                    al.cumprimento_acordo,
                    al.preparo_embarque,
                    al.comunicacao

                FROM avaliacoes_lote al
                JOIN pedido_itens pi ON al.pedido_item_id = pi.id
                JOIN usuarios frig ON al.frigorifico_id = frig.id
                WHERE al.fazenda_id = ?
                ORDER BY al.data_avaliacao DESC
                LIMIT 20";
    
    $stmtList = $conn->prepare($sqlList);
    $stmtList->bind_param('i', $fazenda_id);
    $stmtList->execute();
    $resultList = $stmtList->get_result();
    $avaliacoes = $resultList->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $mensagem_erro = "Erro de Banco de Dados: " . $e->getMessage();
}

// Função helper para exibir estrelas
function exibir_estrelas($nota) {
    $nota_int = round($nota);
    // Corrigido: removido o ponto de 'class.='
    $html = '<span class="stars-display">'; 
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $nota_int) {
            $html .= '<i class="fas fa-star" style="color: #f7d100;"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color: #ccc;"></i>';
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
  <title>BovinTrade - Minhas Avaliações (Fazenda)</title>
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
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
    .sidebar-menu{ list-style:none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; }
    .alert-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500; }
    .no-data { color: var(--text-light); font-style: italic; }

    /* Card de Sumário */
    .summary-card {
      background: linear-gradient(135deg, #f7d100, #f8c242);
      color: #333;
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
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.75rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid var(--border);
      gap: 0.5rem;
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

    /* Título com ícone (mesmo estilo do histórico) */
    .history-title { 
      font-size: 1.8rem; 
      font-weight: 600; 
      display: flex; 
      align-items: center; 
      gap: .5rem; 
      margin-bottom: 2rem;
    }

     @media (max-width: 768px) {
        .sidebar { width: 100%; border-right: none; box-shadow: none; padding: 1rem 0;}
        .container { flex-direction: column; }
        .main { padding: 1.5rem; }
        .summary-card { flex-direction: column; text-align: center; gap: 1rem; }
        .summary-card .details { text-align: center; }
        .comment-metrics { flex-direction: column; align-items: stretch; gap: 1rem; }
        .metric-average { border-left: none; border-top: 2px solid var(--border); padding-top: 1rem; }
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

  <main class="main">
    <h1 class="history-title"><i class="fas fa-star"></i> Minhas Avaliações (Lotes)</h1>

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
      <h2>Comentários Recentes dos Frigoríficos</h2>
      
      <?php if (empty($avaliacoes)): ?>
        <p class="no-data" style="margin-top: 1.5rem;">Você ainda não recebeu nenhuma avaliação.</p>
      <?php else: ?>
        <ul class="comment-list" style="margin-top: 1.5rem;">
          <?php foreach ($avaliacoes as $avaliacao): ?>
            <li class="comment-item">
              <div class="header">
                <div>
                  <span class="evaluator"><?php echo htmlspecialchars($avaliacao['frigorifico_nome']); ?></span>
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
              if (isset($avaliacao['estrutura_corporal']) && $avaliacao['estrutura_corporal'] > 0) {
                  $sub_notas['Estrutura Corporal'] = (int)$avaliacao['estrutura_corporal'];
              }
              if (isset($avaliacao['qualidade_carcaca']) && $avaliacao['qualidade_carcaca'] > 0) {
                  $sub_notas['Qualidade da Carcaça'] = (int)$avaliacao['qualidade_carcaca'];
              }
              if (isset($avaliacao['saude_bem_estar']) && $avaliacao['saude_bem_estar'] > 0) {
                  $sub_notas['Saúde e Bem-Estar'] = (int)$avaliacao['saude_bem_estar'];
              }
              // (Adicionei estas com base no seu DB dump)
              if (isset($avaliacao['cumprimento_acordo']) && $avaliacao['cumprimento_acordo'] > 0) {
                  $sub_notas['Cumprimento do Acordo'] = (int)$avaliacao['cumprimento_acordo'];
              }
              if (isset($avaliacao['preparo_embarque']) && $avaliacao['preparo_embarque'] > 0) {
                  $sub_notas['Preparo para Embarque'] = (int)$avaliacao['preparo_embarque'];
              }
              if (isset($avaliacao['comunicacao']) && $avaliacao['comunicacao'] > 0) {
                  $sub_notas['Comunicação'] = (int)$avaliacao['comunicacao'];
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
</body>
</html>