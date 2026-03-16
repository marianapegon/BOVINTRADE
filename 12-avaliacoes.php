<?php
// Força o PHP a mostrar os erros (IMPORTANTE para debug)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'conexao.php'; // Usa o seu arquivo de conexão

// --- 1. PROTEÇÃO DE ROTA E SESSÃO ---
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if ($u['tipo_usuario'] === 'FAZENDA')       { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

$nome             = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email            = htmlspecialchars($u['email'] ?? '');
$frigorifico_id   = (int)$u['id']; // ID do frigorífico logado

// --- 2. INICIALIZAÇÃO DE VARIÁVEIS ---
$mensagem_sucesso = '';
$mensagem_erro = '';
$modo_avaliacao = false;
$pedido_para_avaliar = null;
$pedidos_pendentes = [];
$avaliacoes_concluidas = []; // Variável para o histórico

try {
    // --- 3. PROCESSAMENTO DO FORMULÁRIO (SE FOR UM POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // MUDANÇA: Agora recebemos o pedido_item_id
        $pedido_item_id       = (int)($_POST['pedido_item_id'] ?? 0);
        $nota_lote            = (int)($_POST['nota_lote'] ?? 0);
        $comentario_lote      = $_POST['comentario_lote'] ?? '';
        $nota_transporte      = (int)($_POST['nota_transporte'] ?? 0);
        $comentario_transporte = $_POST['comentario_transporte'] ?? '';

        // Captura das Métricas (Subnotas)
        $metricas = $_POST['metricas'] ?? [];
        
        // ATUALIZADO: Função helper com validação de 1 a 5
        $sanitize_subnota = function($value) {
            if (empty($value)) {
                return null;
            }
            $num = (int)$value;
            // Garante que o número está no intervalo de 1 a 5
            if ($num >= 1 && $num <= 5) {
                return $num;
            }
            return null; // Descarta o valor se for inválido (ex: 0, 6, 10)
        };

        // Subnotas do Lote
        $estrutura_corporal = $sanitize_subnota($metricas['estrutura_corporal'] ?? null);
        $qualidade_carcaca  = $sanitize_subnota($metricas['qualidade_carcaca'] ?? null);
        $saude_bem_estar    = $sanitize_subnota($metricas['saude_bem_estar'] ?? null);
        
        // Subnotas do Transporte
        $pontualidade       = $sanitize_subnota($metricas['pontualidade'] ?? null);
        $bem_estar_viagem   = $sanitize_subnota($metricas['bem_estar_viagem'] ?? null);
        $condicao_veiculo   = $sanitize_subnota($metricas['condicao_veiculo'] ?? null);

        // MUDANÇA: Validação baseada no pedido_item_id
        if (empty($pedido_item_id) || empty($nota_lote) || empty($nota_transporte)) {
            throw new Exception("Erro: Todos os campos de nota são obrigatórios.");
        }

        $conn->begin_transaction();

        // A.1. MUDANÇA: Buscar pedido_id e fazenda_id a partir do pedido_item_id
        $sqlItem = "SELECT pedido_id, fazenda_id FROM pedido_itens WHERE id = ?";
        $stmtItem = $conn->prepare($sqlItem);
        $stmtItem->bind_param('i', $pedido_item_id);
        $stmtItem->execute();
        $resultItem = $stmtItem->get_result();
        $item = $resultItem->fetch_assoc();
        if (!$item) { throw new Exception("Item de pedido não encontrado (ID: $pedido_item_id)."); }
        $pedido_id = (int)$item['pedido_id']; // Agora temos o pedido_id
        $fazenda_id = (int)$item['fazenda_id'];
        
        // A.2. Descobrir o ID do transporte (usa o pedido_id encontrado)
        $sqlTransp = "SELECT id FROM transportes WHERE pedido_id = ? AND status = 'ENTREGUE'";
        $stmtTransp = $conn->prepare($sqlTransp);
        $stmtTransp->bind_param('i', $pedido_id);
        $stmtTransp->execute();
        $resultTransp = $stmtTransp->get_result();
        $transporte = $resultTransp->fetch_assoc();

        if (!$transporte) {
            throw new Exception("Falha de lógica: Não foi encontrado um registro de transporte 'ENTREGUE' para este pedido (ID: $pedido_id). Impossível avaliar.");
        }
        $transporte_id = (int)$transporte['id'];
        
        // B. Inserir a avaliação do LOTE (com novas subnotas)
        $sqlLote = "INSERT INTO avaliacoes_lote (
                        pedido_item_id, frigorifico_id, fazenda_id, nota, comentario, 
                        estrutura_corporal, qualidade_carcaca, saude_bem_estar
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtLote = $conn->prepare($sqlLote);
        $stmtLote->bind_param('iiiisiii', 
            $pedido_item_id, $frigorifico_id, $fazenda_id, $nota_lote, $comentario_lote,
            $estrutura_corporal, $qualidade_carcaca, $saude_bem_estar
        );
        $stmtLote->execute();

        // C. ATUALIZADO: Inserir a avaliação do TRANSPORTE (Apenas se não existir)
        
        // C.1. Verificar se já existe uma avaliação para este transporte
        $sqlCheckTrans = "SELECT id FROM avaliacoes_transporte WHERE transporte_id = ? AND avaliador_id = ? AND avaliador_tipo = 'frigorifico'";
        $stmtCheckTrans = $conn->prepare($sqlCheckTrans);
        $stmtCheckTrans->bind_param('ii', $transporte_id, $frigorifico_id);
        $stmtCheckTrans->execute();
        $resultCheckTrans = $stmtCheckTrans->get_result();

        if ($resultCheckTrans->fetch_assoc() === null) {
            // C.2. Só insere se não houver avaliação prévia
            $sqlTransporteAval = "INSERT INTO avaliacoes_transporte (
                                    transporte_id, avaliador_tipo, avaliador_id, nota, comentario, 
                                    pontualidade, bem_estar_viagem, condicao_veiculo
                                ) VALUES (?, 'frigorifico', ?, ?, ?, ?, ?, ?)";
            $stmtTransporteAval = $conn->prepare($sqlTransporteAval);
            $stmtTransporteAval->bind_param('iiisiii', 
                $transporte_id, $frigorifico_id, $nota_transporte, $comentario_transporte,
                $pontualidade, $bem_estar_viagem, $condicao_veiculo
            );
            $stmtTransporteAval->execute();
        }

        $conn->commit();
        $mensagem_sucesso = "Avaliação registrada com sucesso!";
    }

    // --- 4. LÓGICA DE EXIBIÇÃO (SE FOR UM GET) ---
    
    // MUDANÇA: Agora checa por avaliar_item_id
    if (isset($_GET['avaliar_item_id'])) {
        // MODO: Exibir formulário de avaliação
        $modo_avaliacao = true;
        $pedido_item_id_avaliar = (int)$_GET['avaliar_item_id'];

        // ATUALIZADO: Buscar dados com base no pedido_item_id
        $sql = "SELECT 
                    p.id AS pedido_id, 
                    pi.id AS pedido_item_id,
                    faz.nome_razao AS fazenda_nome,
                    trans.nome_razao AS transportadora_nome
                FROM pedido_itens pi
                JOIN pedidos p ON pi.pedido_id = p.id
                JOIN usuarios faz ON pi.fazenda_id = faz.id
                JOIN transportes t ON t.pedido_id = p.id
                JOIN usuarios trans ON t.transportadora_id = trans.id
                WHERE pi.id = ? 
                  AND p.frigorifico_id = ?
                  AND t.status = 'ENTREGUE'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $pedido_item_id_avaliar, $frigorifico_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pedido_para_avaliar = $result->fetch_assoc();

        if (!$pedido_para_avaliar) {
            $modo_avaliacao = false;
            $mensagem_erro = "Lote não encontrado, não pertence a você, ou ainda não foi entregue.";
        }

    } else {
        // MODO: Exibir lista de pedidos pendentes E HISTÓRICO
        $modo_avaliacao = false;
        
        // ATUALIZADO: SQL para PENDENTES (agora por item)
        $sql = "SELECT 
                    pi.id AS pedido_item_id,
                    p.id AS pedido_id, 
                    p.created_at,
                    faz.nome_razao AS fazenda_nome,
                    l.raca AS raca_lote
                FROM pedido_itens pi
                JOIN pedidos p ON pi.pedido_id = p.id
                JOIN transportes t ON t.pedido_id = p.id
                JOIN lote_bois l ON pi.lote_id = l.id
                JOIN usuarios faz ON pi.fazenda_id = faz.id
                LEFT JOIN avaliacoes_lote al ON al.pedido_item_id = pi.id
                WHERE 
                    p.frigorifico_id = ? 
                    AND t.status = 'ENTREGUE'
                    AND al.id IS NULL
                GROUP BY pi.id
                ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $frigorifico_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pedidos_pendentes = $result->fetch_all(MYSQLI_ASSOC);
        
        // CONSULTA PARA HISTÓRICO (Esta consulta já estava correta)
        $sqlHist = "SELECT 
                    p.id AS pedido_id,
                    faz.nome_razao AS fazenda_nome,
                    trans.nome_razao AS transportadora_nome,
                    al.nota AS nota_lote,
                    al.comentario AS comentario_lote,
                    al.created_at AS data_avaliacao,
                    at.nota AS nota_transporte,
                    at.comentario AS comentario_transporte,
                    al.estrutura_corporal,
                    al.qualidade_carcaca,
                    al.saude_bem_estar,
                    at.pontualidade,
                    at.bem_estar_viagem,
                    at.condicao_veiculo
                FROM avaliacoes_lote al
                JOIN pedido_itens pi ON al.pedido_item_id = pi.id
                JOIN pedidos p ON pi.pedido_id = p.id
                JOIN usuarios faz ON al.fazenda_id = faz.id
                JOIN transportes t ON t.pedido_id = p.id
                JOIN usuarios trans ON t.transportadora_id = trans.id
                LEFT JOIN avaliacoes_transporte at ON at.transporte_id = t.id 
                                          AND at.avaliador_id = al.frigorifico_id 
                                          AND at.avaliador_tipo = 'frigorifico'
                WHERE 
                    al.frigorifico_id = ?
                GROUP BY al.id
                ORDER BY al.created_at DESC";
        
        $stmtHist = $conn->prepare($sqlHist);
        $stmtHist->bind_param('i', $frigorifico_id);
        $stmtHist->execute();
        $resultHist = $stmtHist->get_result();
        $avaliacoes_concluidas = $resultHist->fetch_all(MYSQLI_ASSOC);
    }

} catch (Exception $e) {
    if ($conn && $conn->ping()) { $conn->rollback(); }
    
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        // Erro de duplicidade na avaliação do LOTE (já foi avaliado)
        if (strpos($e->getMessage(), 'avaliacoes_lote') !== false) {
            $mensagem_erro = "Erro: Este lote já foi avaliado.";
        } else {
            // Se não for do lote, é do transporte, o que é esperado e não um erro.
            // Mas se a do Lote falhar (por ex. por outra 'Duplicate entry' não tratada), mostramos.
            $mensagem_erro = "Erro: Este item já foi avaliado.";
        }
    } else {
        // Se o erro for 'created_at', damos a instrução
        if (strpos($e->getMessage(), "Unknown column 'al.created_at'") !== false) {
            $mensagem_erro = "Erro de Banco de Dados: A coluna created_at não foi encontrada em avaliacoes_lote. Por favor, execute o comando SQL: ALTER TABLE avaliacoes_lote ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;";
        } else {
            $mensagem_erro = "Erro de Banco de Dados: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>BovinTrade - Avaliações</title>
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
        .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
        .container{ display:flex; min-height:calc(100vh - 76px); }
        .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
        .sidebar-menu{ list-style:none; }
        .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
        .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
        .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
        .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
        .main{ flex:1; padding:2.5rem; }
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 2rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; }
        .card { background: var(--background); border-radius: 12px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .tabela-avaliacoes { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        .tabela-avaliacoes th,
        .tabela-avaliacoes td { padding: 1rem 1.25rem; text-align: left; border-bottom: 1px solid var(--border); }
        .tabela-avaliacoes th { background-color: var(--background-light); font-weight: 600; }
        .tabela-avaliacoes td { font-size: 0.95rem; }
        .btn-avaliar { background-color: var(--primary); color: white; padding: 0.6rem 1rem; text-decoration: none; border-radius: 6px; font-weight: 500; transition: 0.2s; font-size: 0.9rem; }
        .btn-avaliar:hover { background-color: var(--primary-dark); }
        .form-avaliacao h2 { font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .form-avaliacao h3 { font-size: 1.1rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group textarea { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid var(--border); font-family: 'Montserrat', sans-serif; min-height: 100px; }
        .btn-submit { background-color: var(--primary); color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.2s; margin-top: 1rem; }
        .btn-submit:hover { background-color: var(--primary-dark); }
        .btn-voltar { color: var(--text-light); text-decoration: none; margin-bottom: 1.5rem; display: inline-block; }
        .btn-voltar:hover { color: var(--primary); }
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
        .rating-stars input[type="radio"] { display: none; }
        .rating-stars label { font-size: 2rem; color: #ddd; cursor: pointer; padding: 0 0.15em; transition: 0.2s; }
        .rating-stars input[type="radio"]:checked ~ label { color: #f7d100; }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label { color: #f7d100; }
        .alert { padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500; }
        .alert-sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .no-data { color: var(--text-light); font-style: italic; text-align: center; }
        
        .form-group .label {
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--text-light);
            margin-top: 1.5rem; 
            margin-bottom: 0.75rem;
        }
        .form-group .subgrid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
        }
        .form-group .subgrid input[type="number"] {
            width: 100%;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: 'Montserrat', sans-serif;
        }
        .form-group .subgrid input[type=number]::-webkit-inner-spin-button, 
        .form-group .subgrid input[type=number]::-webkit-outer-spin-button { 
         -webkit-appearance: none; 
         margin: 0; 
        }
        .form-group .subgrid input[type=number] {
         -moz-appearance: textfield;
        }

        .card-historico { margin-top: 2.5rem; }
        .avaliacao-item { 
            border-bottom: 1px solid var(--border); 
            padding: 1.5rem 0; 
            margin-bottom: 1.5rem;
        }
        .avaliacao-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .avaliacao-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1.5rem; 
            flex-wrap: wrap; 
        }
        .avaliacao-header span { font-weight: 600; font-size: 1.2rem; }
        .avaliacao-header time { font-size: 0.9rem; color: var(--text-light); }
        .avaliacao-detalhe { margin-bottom: 1.5rem; }
        .avaliacao-detalhe:last-child { margin-bottom: 0; }
        .avaliacao-detalhe h4 { font-weight: 600; margin-bottom: 0.5rem; font-size: 1.05rem; }
        .avaliacao-detalhe .stars { color: #f7d100; margin-bottom: 0.5rem; font-size: 1.1rem; }
        .avaliacao-detalhe .stars .far.fa-star { color: #ddd; } 
        .avaliacao-detalhe blockquote { 
            background: var(--background-light); 
            border-left: 4px solid var(--border); 
            padding: 0.75rem 1rem; 
            color: var(--text); 
            font-style: italic; 
            margin-top: 0.5rem;
        }
        .avaliacao-detalhe .no-comment {
            color: var(--text-light);
            font-style: italic;
            font-size: 0.9rem;
        }
        .subnotas-display {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
            padding-top: 0.75rem;
            border-top: 1px solid var(--background-light);
        }
        .subnota-item {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        .subnota-item strong {
            color: var(--text);
            margin-left: 0.25rem;
        }
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
<h1 class="page-title"><i class="fas fa-star"></i> Gerenciar Avaliações</h1>
        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-sucesso"><?php echo $mensagem_sucesso; ?></div>
        <?php endif; ?>
        <?php if ($mensagem_erro): ?>
            <div class="alert alert-erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
        <?php endif; ?>

        
        <?php if ($modo_avaliacao && $pedido_para_avaliar): ?>
                        <a href="12-avaliacoes.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar para a lista</a>
            
            <div class="card">
                <form class="form-avaliacao" action="12-avaliacoes.php" method="POST">
                                        <input type="hidden" name="pedido_item_id" value="<?php echo htmlspecialchars($pedido_para_avaliar['pedido_item_id']); ?>">
                    
                    <h2>Avaliar Lote do Pedido #<?php echo htmlspecialchars($pedido_para_avaliar['pedido_id']); ?></h2>

                                    <h3>1. Avaliação do Lote (Fazenda: <?php echo htmlspecialchars($pedido_para_avaliar['fazenda_nome']); ?>)</h3>
                    <div class="form-group">
                        <label for="nota_lote">Nota Geral (1 a 5 estrelas)</label>
                        <div class="rating-stars">
                                <input type="radio" id="lote-star5" name="nota_lote" value="5" required><label for="lote-star5" title="5 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="lote-star4" name="nota_lote" value="4"><label for="lote-star4" title="4 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="lote-star3" name="nota_lote" value="3"><label for="lote-star3" title="3 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="lote-star2" name="nota_lote" value="2"><label for="lote-star2" title="2 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="lote-star1" name="nota_lote" value="1"><label for="lote-star1" title="1 estrela"><i class="fas fa-star"></i></label>
                        </div>
                        
                        <div class="label">Subnotas do Lote (opcionais)</div>
                        <div class="subgrid">
                                <input type="number" min="1" max="5" name="metricas[estrutura_corporal]" placeholder="Estrutura corporal (1–5)">
                                <input type="number" min="1" max="5" name="metricas[qualidade_carcaca]"  placeholder="Qualidade carcaça (1–5)">
                                <input type="number" min="1" max="5" name="metricas[saude_bem_estar]"     placeholder="Saúde/Bem-estar (1–5)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="comentario_lote">Comentário sobre o Lote (Opcional)</label>
                        <textarea id="comentario_lote" name="comentario_lote" rows="4" placeholder="Descreva o que achou da qualidade do lote, conformidade, etc..."></textarea>
                    </div>

                                    <h3>2. Avaliação do Transporte (Transportadora: <?php echo htmlspecialchars($pedido_para_avaliar['transportadora_nome'] ?? 'N/D'); ?>)</h3>
                    <div class="form-group">
                        <label for="nota_transporte">Nota Geral (1 a 5 estrelas)</label>
                        <div class="rating-stars">
                                <input type="radio" id="trans-star5" name="nota_transporte" value="5" required><label for="trans-star5" title="5 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="trans-star4" name="nota_transporte" value="4"><label for="trans-star4" title="4 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="trans-star3" name="nota_transporte" value="3"><label for="trans-star3" title="3 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="trans-star2" name="nota_transporte" value="2"><label for="trans-star2" title="2 estrelas"><i class="fas fa-star"></i></label>
                                <input type="radio" id="trans-star1" name="nota_transporte" value="1"><label for="trans-star1" title="1 estrela"><i class="fas fa-star"></i></label>
                        </div>
                        
                        <div class="label">Subnotas do Transporte (opcionais)</div>
                        <div class="subgrid">
                                <input type="number" min="1" max="5" name="metricas[pontualidade]"      placeholder="Pontualidade (1–5)">
                                <input type="number" min="1" max="5" name="metricas[bem_estar_viagem]"  placeholder="Bem-estar (1–5)">
                                <input type="number" min="1" max="5" name="metricas[condicao_veiculo]"  placeholder="Veículo (1–5)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="comentario_transporte">Comentário sobre o Transporte (Opcional)</label>
                        <textarea id="comentario_transporte" name="comentario_transporte" rows="4" placeholder="Descreva o que achou do serviço de transporte, pontualidade, etc..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Enviar Avaliação</button>
                </form>
            </div>

        <?php else: ?>
                        <div class="card">
            <h2>Lotes Pendentes de Avaliação</h2>
            <p style="margin-top: 5px; color: var(--text-light);">Aqui estão os lotes que já foram entregues mas ainda não foram avaliados por você.</p>
            
                                <table class="tabela-avaliacoes">
                <thead>
                    <tr>
                        <th>Pedido ID</th>
                        <th>Fazenda</th>
                        <th>Raça do Lote</th>
                        <th>Data do Pedido</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pedidos_pendentes)): ?>
                        <tr>
                            <td colspan="5" class="no-data">Nenhum lote pendente de avaliação no momento.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pedidos_pendentes as $pedido): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($pedido['pedido_id']); ?></td>
                                <td><?php echo htmlspecialchars($pedido['fazenda_nome']); ?></td>
                                <td><?php echo htmlspecialchars($pedido['raca_lote']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($pedido['created_at'])); ?></td>
                                <td>
                                                                        <a href="12-avaliacoes.php?avaliar_item_id=<?php echo $pedido['pedido_item_id']; ?>" class="btn-avaliar">
                                        <i class="fas fa-star"></i> Avaliar Lote
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

                        <div class="card card-historico">
            <h2>Histórico de Avaliações Realizadas</h2>
            
            <?php if (empty($avaliacoes_concluidas)): ?>
                <p class="no-data">Você ainda não realizou nenhuma avaliação.</p>
            <?php else: ?>
                <?php foreach ($avaliacoes_concluidas as $aval): ?>
                    <div class="avaliacao-item">
                        <div class="avaliacao-header">
                            <span>Pedido #<?php echo htmlspecialchars($aval['pedido_id']); ?></span>
                            <time>Avaliado em: <?php echo $aval['data_avaliacao'] ? date('d/m/Y H:i', strtotime($aval['data_avaliacao'])) : 'Data não registrada'; ?></time>
                        </div>
                        
                        <div class="avaliacao-detalhe">
                            <h4>Lote (Fazenda: <?php echo htmlspecialchars($aval['fazenda_nome']); ?>)</h4>
                            <div class="stars">
                                    <?php 
                                    $nota_l = (int)$aval['nota_lote'];
                                    for ($i = 1; $i <= 5; $i++):
                                        echo $i <= $nota_l ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                    endfor; 
                                    ?>
                            </div>
                            <?php if (!empty(trim($aval['comentario_lote']))): ?>
                                    <blockquote><?php echo htmlspecialchars($aval['comentario_lote']); ?></blockquote>
                            <?php else: ?>
                                    <p class="no-comment">Nenhum comentário fornecido.</p>
                            <?php endif; ?>

                                    <div class="subnotas-display">
                            <?php if (!empty($aval['estrutura_corporal'])): ?>
                                    <span class="subnota-item">Estrutura corporal: <strong><?php echo $aval['estrutura_corporal']; ?>/5</strong></span>
                            <?php endif; ?>
                            <?php if (!empty($aval['qualidade_carcaca'])): ?>
                                    <span class="subnota-item">Qualidade carcaça: <strong><?php echo $aval['qualidade_carcaca']; ?>/5</strong></span>
                            <?php endif; ?>
                            <?php if (!empty($aval['saude_bem_estar'])): ?>
                                    <span class="subnota-item">Saúde/Bem-estar: <strong><?php echo $aval['saude_bem_estar']; ?>/5</strong></span>
                            <?php endif; ?>
                        </div>
                        </div>

                        <div class="avaliacao-detalhe">
                                <h4>Transporte (Transp.: <?php echo htmlspecialchars($aval['transportadora_nome']); ?>)</h4>
                                <div class="stars">
                                        <?php 
                                        $nota_t = (int)($aval['nota_transporte'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++):
                                            echo $i <= $nota_t ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                        endfor; 
                                        ?>
                                </div>
                                <?php if (!empty(trim($aval['comentario_transporte']))): ?>
                                        <blockquote><?php echo htmlspecialchars($aval['comentario_transporte']); ?></blockquote>
                                <?php else: ?>
                                        <p class="no-comment">Nenhum comentário fornecido.</p>
                                <?php endif; ?>
                                
                                        <div class="subnotas-display">
                                <?php if (!empty($aval['pontualidade'])): ?>
                                        <span class="subnota-item">Pontualidade: <strong><?php echo $aval['pontualidade']; ?>/5</strong></span>
                                <?php endif; ?>
                                <?php if (!empty($aval['bem_estar_viagem'])): ?>
                                        <span class="subnota-item">Bem-estar: <strong><?php echo $aval['bem_estar_viagem']; ?>/5</strong></span>
                                <?php endif; ?>
                                <?php if (!empty($aval['condicao_veiculo'])): ?>
                                        <span class="subnota-item">Veículo: <strong><?php echo $aval['condicao_veiculo']; ?>/5</strong></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>