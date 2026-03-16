<?php
$current_page = 'checkout_resumo.php';
session_start();
require_once 'config.php'; // Conexão PDO

// Proteção de rota
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if ($u['tipo_usuario'] === 'FAZENDA')      { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

$nome = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');
$frigorifico_id = $u['id'];

// =========================================================
// VARIÁVEIS DE CAMINHO DE IMAGEM E FUNÇÕES DE UTILITY
// =========================================================
$project_folder = 'Projeto-Bovintrade-2'; 
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/'; 
if (strpos($request_uri, $project_folder) !== false) {
    $path_segment = substr($request_uri, 0, strpos($request_uri, $project_folder) + strlen($project_folder));
    $base_path = rtrim($path_segment, '/') . '/';
} 

function brl($v){ return 'R$ '.number_format((float)$v,2,',','.'); }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Variável de controle do carrinho
$carrinho = $_SESSION['carrinho'] ?? [];
if (empty($carrinho)) { header('Location: meu-carrinho.php'); exit; }


// ----------------------------------------------------------------------
// 1. LÓGICA DE CÁLCULO DE FRETE (Integrada)
// ----------------------------------------------------------------------
$frete_por_km = 5.50;
$frete_total = 0.00;
$subtotal = 0.0;
$selecionados = [];
$fazendaIds = [];
$fretes_por_lote = [];


try {
    // a. Obter Coordenadas do Frigorífico (Destino)
    $stmt_frig = $pdo->prepare("SELECT latitude, longitude FROM usuarios WHERE id = ?");
    $stmt_frig->execute([$frigorifico_id]);
    $frig_coords = $stmt_frig->fetch(PDO::FETCH_ASSOC);

    if (!$frig_coords || empty($frig_coords['latitude']) || empty($frig_coords['longitude'])) {
        // Se as coordenadas estiverem faltando, o frete permanece 0.00
        throw new Exception("Coordenadas do Frigorífico (destino) não encontradas.");
    }
    $lat_frig = (float)$frig_coords['latitude'];
    $lon_frig = (float)$frig_coords['longitude'];

    // b. Coletar IDs e Pré-calcular subtotal
    foreach ($carrinho as $k => $it) {
        $selecionados[] = isset($it['id']) ? (string)$it['id'] : (string)$k;
        $subtotal += ((float)($it['preco'] ?? 0) * (int)($it['quantidade'] ?? 1));
        if (!empty($it['fazenda_id'])) {
            $fazendaIds[] = (int)$it['fazenda_id'];
        }
    }
    $_SESSION['checkout_selecionados'] = $selecionados;


    // c. Obter Coordenadas de Todas as Fazendas (Origem)
    $fazendas_coords = [];
    if (!empty($fazendaIds)) {
        $fazendaIds_unique = array_values(array_unique(array_filter($fazendaIds)));
        $placeholders = implode(',', array_fill(0, count($fazendaIds_unique), '?'));
        $stmt_faz = $pdo->prepare("SELECT id, latitude, longitude FROM usuarios WHERE id IN ($placeholders)");
        $stmt_faz->execute($fazendaIds_unique);

        while ($row = $stmt_faz->fetch(PDO::FETCH_ASSOC)) {
            $fazendas_coords[$row['id']] = [
                'lat' => (float)$row['latitude'],
                'lon' => (float)$row['longitude']
            ];
        }
    }

    // d. Função Haversine (embutida)
    function haversine($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($R * $c); // Distância em km
    }

    // e. Calcular o Frete para Cada Item
    $frete_index = 0;
    foreach ($carrinho as $item) {
        $fazenda_id = $item['fazenda_id'] ?? 0;
        if (isset($fazendas_coords[$fazenda_id]) && !empty($fazendas_coords[$fazenda_id]['lat'])) {
            $coords_faz = $fazendas_coords[$fazenda_id];
            
            $distancia_km = haversine($coords_faz['lat'], $coords_faz['lon'], $lat_frig, $lon_frig);
            $custo_frete = $distancia_km * $frete_por_km;
            
            $fretes_por_lote[$frete_index] = ['distancia' => $distancia_km, 'custo' => $custo_frete];
            $frete_total += $custo_frete;
        }
        $frete_index++;
    }

} catch (Throwable $e) {
    error_log("Erro no cálculo do frete: " . $e->getMessage());
}

$total_pedido = $subtotal + $frete_total;
// ----------------------------------------------------------------------


// --- Busca dados de fazendas (unificada para exibir detalhes) ---
$fazendas_data = []; 
if (!empty($fazendaIds)) {
    $fazendaIds_unique = array_values(array_unique(array_filter($fazendaIds)));
    $placeholders = implode(',', array_fill(0, count($fazendaIds_unique), '?'));
    $sql = "SELECT id, nome_razao, cidade, estado, telefone
            FROM usuarios
            WHERE id IN ({$placeholders})";
    
    $stmt_faz = $pdo->prepare($sql);
    $stmt_faz->execute($fazendaIds_unique);
    
    while ($row = $stmt_faz->fetch(PDO::FETCH_ASSOC)) {
        $fazendas_data[(int)$row['id']] = $row;
    }
}

// SALVA OS TOTAIS NA SESSÃO para a próxima página (formas_pagamento.php)
$_SESSION['subtotal'] = $subtotal;
$_SESSION['frete_total'] = $frete_total;
$_SESSION['total_pedido'] = $total_pedido; 
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Resumo do Pedido</title>
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

    /* ESTILOS DE RESUMO */
    h1{ color: var(--primary); margin-bottom:15px; font-size: 1.8rem; }
    .muted{ color:var(--text-light); }
    .row-layout{ display:flex; gap:30px; flex-wrap:wrap; margin-top:20px; }
    .left-items{ flex:1; min-width: 300px; }
    .right-summary{ width:350px; max-width:100%; }

    /* ITEM BOX */
    .item-card { border:1px solid #eee; border-radius:10px; padding:15px; margin-bottom:15px; background: #fff; }
    .item-head{ display:flex; align-items:flex-start; gap:15px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
    .thumb-container { 
        width:100px; height:100px; display:flex; align-items:center; justify-content:center;
        border-radius:8px; border:1px solid #eee; background-color: #f0f0f0; 
    }
    img.thumb{ width:100%; height:100%; object-fit:cover; border-radius:8px; }
    .cow-placeholder { font-size: 3rem; line-height: 1; }
    .item-title{ font-weight:700; font-size: 1.1rem; }
    .item-details-box { display: grid; grid-template-columns: 1fr 1fr; }
    .line{ display:flex; justify-content:space-between; margin:4px 0; font-size: 0.95rem; }
    .line span:first-child { color: var(--text-light); }
    .total{ border-top:1px solid #ddd; margin-top:10px; padding-top:10px; font-weight:700; font-size: 1rem; }

    /* FARM INFO */
    .farm{ background:#fafafa; border:1px dashed #ddd; border-radius:8px; padding:10px; margin-top:10px; font-size: 0.9rem; }

    /* SUMMARY BOX */
    .summary-box{ background:#fff; border:1px solid var(--border); border-radius:10px; padding:20px; }
    .summary-box .line.total { border-top: 2px solid var(--primary); padding-top: 15px; margin-top: 15px; font-size: 1.2rem; }
    .summary-box .line.total span:last-child { color: var(--primary-dark); font-size: 1.4rem; font-weight: 700; }

    /* BUTTONS */
    .btn{ padding:10px 16px; border-radius:8px; border:1px solid var(--primary); background:var(--primary); color:#fff; font-weight:600; cursor:pointer; width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px; text-decoration: none; }
    .btn-outline{ background:#fff; color:var(--primary); }
    .wrap{ background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.05); padding:30px; }
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
    <div class="wrap">
        <h1>Resumo do Pedido</h1>
        <p class="muted">Confira os lotes e os dados da fazenda antes de escolher a forma de pagamento.</p>

        <div class="row-layout">
            <div class="left-items">
                <?php $frete_item_index = 0; ?>
                <?php foreach ($carrinho as $k => $it):
                    $rowId = isset($it['id']) ? (string)$it['id'] : (string)$k;
                    if (!in_array($rowId, $selecionados, true)) continue;

                    $qtd  = (int)($it['quantidade'] ?? 1);
                    $puni = (float)($it['preco'] ?? 0);
                    $tot  = $qtd * $puni;

                    $fazId = (int)($it['fazenda_id'] ?? 0);
                    $farm  = $fazendas_data[$fazId] ?? null;

                    // Pega os dados do frete para este item
                    $frete_dados = $fretes_por_lote[$frete_item_index] ?? null;
                    $frete_item_index++;
                    
                    // Imagem
                    $img_url_lote = $it['imagem_url'] ?? 'placeholder.png'; 
                    $img_src = $base_path . e($img_url_lote); 
                ?>
                    <div class="item-card">
                        <div class="item-head">
                            
                            <div class="thumb-container">
                                <?php if (strpos($img_src, 'placeholder.png') !== false || empty($it['imagem_url'])): ?>
                                    <span class="cow-placeholder">🐄</span>
                                <?php else: ?>
                                    <img class="thumb" src="<?= $img_src ?>" alt="Lote">
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="item-title">
                                    Lote #<?= e($it['codigo_lote'] ?? '') ?> <?= !empty($it['raca']) ? ' - '.e($it['raca']) : '' ?>
                                </div>
                                <div class="muted"><?= e($it['descricao'] ?? '') ?></div>
                            </div>
                        </div>
                        
                        <div class="item-details-box">
                            <div class="total" style="padding: 10px 0;">
                                <div class="line"><span>Quantidade</span><span><?= (int)$qtd ?></span></div>
                                <div class="line"><span>Preço Unitário</span><span><?= brl($puni) ?></span></div>
                                <div class="line"><span>Total Lote</span><span><?= brl($tot) ?></span></div>
                            </div>
                            
                            <div class="total" style="padding: 10px 0; ">
                                <div class="line">&nbsp;<span>Frete por Lote</span>
                                    <span>
                                        <?php if ($frete_dados): ?>
                                            <?= brl($frete_dados['custo']) ?>
                                        <?php else: ?>
                                            R$ 0,00
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($frete_dados): ?>
                                    <div class="line" style="font-size: 0.8em;">&nbsp;<span>Distância Estimada</span>
                                        <span><?= number_format($frete_dados['distancia'], 0, ',', '.') ?> km</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="farm">
                            <div style="font-weight:600; margin-bottom:4px">Vendedor (Fazenda)</div>
                            <?php if ($farm): ?>
                                <div><strong><?= e($farm['nome_razao'] ?? 'Fazenda') ?></strong></div>
                                <div class="muted">
                                    <?= e(($farm['cidade'] ?? '').(!empty($farm['estado']) ? ' - '.$farm['estado'] : '')) ?>
                                </div>
                                <div class="muted">Tel: <?= e($farm['telefone'] ?? 'N/A') ?></div>
                            <?php else: ?>
                                <div><strong><?= e($it['fazenda'] ?? 'Fazenda') ?></strong></div>
                                <div class="muted">Localização: <?= e($it['localizacao'] ?? 'N/A') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="right-summary">
                <div class="summary-box">
                    <h2 style="font-size: 1.2rem; color: var(--primary-dark); margin-bottom: 15px;">Resumo da Transação</h2>
                    <div class="line"><span>Subtotal dos Lotes</span><span><?= brl($subtotal) ?></span></div>
                    <div class="line"><span>Frete (Total)</span><span><?= brl($frete_total) ?></span></div>
                    
                    <div class="line total"><span>Total do Pedido</span><span><?= brl($total_pedido) ?></span></div>

                    <form method="get" action="formas_pagamento.php" style="margin-top:20px">
                        <button class="btn" type="submit"><i class="fas fa-arrow-right"></i> Ir para formas de pagamento</button>
                    </form>

                    <a class="btn btn-outline" href="meu-carrinho.php" style="display:inline-block; margin-top:10px; text-align:center; text-decoration:none; width:100%;">
                        <i class="fas fa-pen"></i> Alterar itens
                    </a>
                </div>
            </div>
        </div>

    </div>
  </main>
</div>
</body>
</html>