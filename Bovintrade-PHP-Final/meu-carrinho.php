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

// =========================================================
// SOLUÇÃO DE CAMINHO DE IMAGEM: DEFINIR O PATH BASE
// =========================================================
// ATENÇÃO: Confirme que 'Projeto-Bovintrade-2' é o nome da ÚLTIMA pasta na sua URL.
$project_folder = 'Projeto-Bovintrade-2'; 
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/'; 

if (strpos($request_uri, $project_folder) !== false) {
    // Extrai o caminho da URL até o nome da pasta do projeto
    $start_pos = strpos($request_uri, $project_folder);
    $path_segment = substr($request_uri, 0, $start_pos + strlen($project_folder));
    $base_path = rtrim($path_segment, '/') . '/';
} 
// =========================================================

// Inicializa o carrinho se não existir
if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

$carrinho = $_SESSION['carrinho'];

// --- Cálculo do Subtotal PHP (Usado para inicialização e fallback) ---
$subtotal = 0;
foreach ($carrinho as $item) {
    $preco = $item['preco'] ?? 0;
    $quantidade = $item['quantidade'] ?? 1;
    $subtotal += $preco * $quantidade;
}

// =========================================================
// ALTERAÇÃO CRUCIAL: Captura a mensagem e LIMPA A SESSÃO IMEDIATAMENTE.
// =========================================================
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_info    = $_SESSION['flash_info'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;

// Limpa as variáveis de sessão APÓS serem lidas
unset($_SESSION['flash_success'], $_SESSION['flash_info'], $_SESSION['flash_error']);

// =========================================================
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

  <style>
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
    .cart-container { display:flex; gap:2rem;}
    .cart-items { flex:2;}
    .cart-summary { flex:1;}
    .cart-card { background-color: var(--background); border-radius:10px; padding:1.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.05); margin-bottom:1.5rem;}
    .cart-item-header { 
        display:flex; 
        justify-content:space-between; 
        align-items:center; 
        margin-bottom:1rem; 
        padding-bottom:1rem; 
        border-bottom:1px solid var(--border);
    }
    .farm-header-content {
        display: flex;
        align-items: center;
        gap: 1rem; /* Espaço entre a imagem da fazenda e o nome */
    }
    .farm-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid var(--border);
    }
    .farm-avatar-placeholder {
        background-color: #e0e0e0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
    }
    .cart-farm-name { font-weight:600; color:var(--primary); display:flex; align-items:center; gap:0.5rem;}
    .cart-item-details { display:flex; gap:1.5rem;}
    .cart-item-image { width:150px; height:150px; border-radius:8px; background-color:; display:flex; align-items:center; justify-content:center; color: var(--text-light); overflow:hidden; font-size:3rem;}
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
    <?php if ($flash_success || $flash_info || $flash_error): // Usa as variáveis locais capturadas no topo ?>
        <div style="max-width:1100px;margin:12px auto;padding:10px 14px;border-radius:8px;
                    background:<?= $flash_error ? '#fdecea' : ($flash_info ? '#eef5ff' : '#e8f5e9') ?>;
                    color:<?= $flash_error ? '#b00020' : '#1b5e20' ?>; border:1px solid #ddd;">
            <?= htmlspecialchars($flash_success ?? $flash_info ?? $flash_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-header">
<h1 class="dashboard-title"><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>    </div>

    <div class="cart-container">
        <div class="cart-items">
            <?php if(empty($carrinho)): ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="empty-cart-title">Seu carrinho está vazio</div>
                    <div class="empty-cart-text">Adicione lotes clicando em "Adicionar ao Carrinho" na pesquisa de lotes.</div>
                </div>
            <?php else: ?>
                <?php foreach($carrinho as $item): ?>
                    <div class="cart-card">
                        <div class="cart-item-header">
                            <div class="farm-header-content">
                                <?php if (isset($item['fazenda_imagens']) && !empty($item['fazenda_imagens'])): ?>
                                    <img src="<?= htmlspecialchars($base_path . $item['fazenda_imagens']) ?>" alt="Logo da Fazenda" class="farm-avatar">
                                <?php else: ?>
                                    <div class="farm-avatar farm-avatar-placeholder"><i class="fas fa-tractor"></i></div>
                                <?php endif; ?>
                                <div class="cart-farm-name"><?= $item['fazenda'] ?? 'Fazenda Desconhecida' ?></div>
                            </div>
                        </div>
                        <div class="cart-item-details">
                            <div class="cart-item-image">
                                🐄
                            </div>
                            <div class="cart-item-info">
                                <h3 class="cart-item-title">
                                    Lote #<?= $item['codigo_lote'] ?? '' ?> - <?= $item['raca'] ?? '' ?>
                                </h3>
                                <p class="cart-item-description"><?= $item['descricao'] ?? 'Sem descrição' ?></p>
                                <div class="cart-item-specs">
                                    <div class="spec-item"><i class="fas fa-hashtag"></i> Quantidade: <?= $item['quantidade'] ?? 1 ?></div>
                                    <div class="spec-item"><i class="fas fa-weight-hanging"></i> Peso médio: <?= $item['peso_medio_kg'] ?? 0 ?> kg</div>
                                    <div class="spec-item"><i class="fas fa-map-marker-alt"></i> Localização: <?= $item['localizacao'] ?? 'ND' ?></div>
                                </div>
                                <div class="cart-item-footer">
                                    <div class="cart-item-price">R$ <?= number_format(($item['preco'] ?? 0) * ($item['quantidade'] ?? 1),2,',','.') ?></div>
                                    <div class="cart-item-actions">
                                        <a href="remover-carrinho.php?id=<?= $item['id'] ?? 0 ?>" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Remover
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="cart-summary">
            <div class="summary-card">
                <h3 class="summary-title"><i class="fas fa-receipt"></i> Resumo do Pedido</h3>
                
                <div class="summary-row"><span>Subtotal (<?= count($carrinho) ?> lotes)</span><span>R$ <?= number_format($subtotal,2,',','.') ?></span></div>
                
                <div class="summary-row">
                    <span>Frete (R$ 5,50/km)</span>
                    <span id="frete-valor">
                        <?php if (empty($carrinho)): ?>
                            R$ 0,00
                        <?php else: ?>
                            <i class="fas fa-spinner fa-spin"></i> Calculando...
                        <?php endif; ?>
                    </span>
                </div>

                <div class="summary-total">
                    <span>Total Geral</span> 
                    <span class="value" id="total-geral-valor">R$ <?= number_format($subtotal,2,',','.') ?></span>
                </div>

                <form method="post" action="checkout_resumo.php" style="margin-top:12px">
                    <button class="btn btn-primary btn-block" type="submit" <?= empty($carrinho) ? 'disabled' : '' ?>>
                        <i class="fas fa-check-circle"></i> Finalizar Compra
                    </button>
                </form>

            </div>
        </div>
    </div>
  </main>
</div>

<script>
$(document).ready(function() {
    function calcularFrete() {
        $.ajax({
            url: 'calcular_frete.php',
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                // Se o carrinho não estiver vazio, exibe o spinner
                if (<?= empty($carrinho) ? 'false' : 'true' ?>) {
                    $('#frete-valor').html('<i class="fas fa-spinner fa-spin"></i>');
                }
            },
            success: function(data) {
                if (data.sucesso) {
                    let subtotal = <?= $subtotal ?>;
                    let freteTotal = parseFloat(data.frete_total);
                    let totalGeral = subtotal + freteTotal;

                    // Função para formatar o valor como moeda brasileira
                    const formatarMoeda = (valor) => 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    
                    // Atualiza o resumo
                    $('#frete-valor').text(formatarMoeda(freteTotal));
                    $('#total-geral-valor').text(formatarMoeda(totalGeral));

                } else {
                    $('#frete-valor').text('Erro');
                    $('#total-geral-valor').text('R$ ' + '<?= number_format($subtotal, 2, ',', '.') ?>');
                    console.error("Erro no cálculo do frete:", data.error);
                }
            },
            error: function(xhr, status, error) {
                // Em caso de erro, exibe 'Indisponível' e mantém o subtotal como total geral
                $('#frete-valor').text('Indisponível');
                $('#total-geral-valor').text('R$ ' + '<?= number_format($subtotal, 2, ',', '.') ?>');
                console.error("Erro AJAX:", status, error);
            }
        });
    }

    // Só calcula se o carrinho não estiver vazio
    if (<?= empty($carrinho) ? 'false' : 'true' ?>) {
        calcularFrete();
    }
});

function toggleMenu() {
    const menu = document.getElementById('dropdownMenu');
    if(menu) menu.classList.toggle('show');
}
</script>
</body>
</html>