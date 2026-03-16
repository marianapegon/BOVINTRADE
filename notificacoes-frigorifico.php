<?php
$current_page = basename($_SERVER['PHP_SELF']); // Pega nome do arquivo atual
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php?expired=1"); exit; }
$u = $_SESSION['usuario'];

// Proteção de Rota (Frigorífico)
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if (($u['tipo_usuario'] ?? '') === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    elseif (($u['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

require_once 'config.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$uid = (int)$u['id'];
$email = e($u['email'] ?? '');
$nome  = e($u['nome_razao'] ?? 'Frigorífico');

// === AJAX HANDLERS ===
if (($_GET['action'] ?? '') === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nid = (int)($_POST['id'] ?? 0);
    if ($nid > 0) {
        $stmt = $pdo->prepare("UPDATE notificacoes SET lida_em = NOW() WHERE id = ? AND usuario_id = ? AND lida_em IS NULL");
        $stmt->execute([$nid, $uid]);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}
if (($_GET['action'] ?? '') === 'mark_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL");
    $stmt->execute([$uid]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}
if (($_GET['action'] ?? '') === 'count' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL");
    $stmt->execute([$uid]);
    $count = (int)$stmt->fetchColumn();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['count' => $count]);
    exit;
}

// === FILTROS E CONSULTA ===
$show = $_GET['show'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Grupos ajustados
$typeGroups = [
    'compra'     => ['PAGAMENTO_DEVIDO', 'PAGAMENTO_RECEBIDO', 'COMPRA_STATUS', 'LOTE_DISPONIVEL', 'LOTE_REMOVIDO'],
    'transporte' => ['TRANSPORTE_SOLICITADO', 'TRANSPORTE_ACEITO', 'TRANSPORTE_RECUSADO', 'ENTREGA_CONFIRMADA', 'TRANSPORTE_ALERTA']
];
// Mapeamento Chave da URL/Grupo para Label da Aba (necessário porque a chave do grupo é 'compra' mas a URL ainda pode usar 'compra_venda' por compatibilidade)
$groupKeyMapping = [
    'compra_venda' => 'compra', // Mapeia a chave antiga para a nova
    'compra' => 'compra',
    'transporte' => 'transporte'
];
$activeGroupKey = $groupKeyMapping[$show] ?? null; // Pega a chave correta para $typeGroups

$where = "usuario_id = ?";
$params = [$uid];

if ($show === 'unread') {
    $where .= " AND lida_em IS NULL";
} elseif ($activeGroupKey && array_key_exists($activeGroupKey, $typeGroups)) { // Usa a chave mapeada
    $in = $typeGroups[$activeGroupKey];
    if (count($in) > 0) {
        $ph = implode(',', array_fill(0, count($in), '?'));
        $where .= " AND tipo IN ($ph)";
        $params = array_merge($params, $in);
    } else { $where .= " AND 1=0"; }
} elseif ($show !== 'all') {
    $where .= " AND 1=0";
}

$sql = "SELECT id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id, created_at, lida_em
        FROM notificacoes WHERE $where ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL");
$stmtCnt->execute([$uid]);
$unreadCount = (int)$stmtCnt->fetchColumn();

// === HELPERS DE EXIBIÇÃO ===
function labelDate($dt) { /* ... (função igual anterior) ... */
    if (!$dt) return 'Data inválida';
    $d = substr($dt, 0, 10);
    try {
        $today = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $yesterday = (new DateTime('yesterday', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        if ($d === $today) return 'Hoje';
        if ($d === $yesterday) return 'Ontem';
        $p = DateTime::createFromFormat('Y-m-d', $d);
        return $p ? $p->format('d/m/Y') : e($d);
    } catch (Exception $e) { return e($d); }
}
function typeMeta($tipo) { /* ... (função igual anterior) ... */
    $map = [
        'COMPRA_STATUS' => ['fa-shopping-bag', 'notification-info'], 'LOTE_DISPONIVEL' => ['fa-tag', 'notification-info'],
        'LOTE_REMOVIDO' => ['fa-trash-alt', 'notification-warning'], 'PAGAMENTO_DEVIDO' => ['fa-money-bill-wave', 'notification-warning'],
        'PAGAMENTO_RECEBIDO' => ['fa-check-circle', 'notification-success'], 'TRANSPORTE_SOLICITADO'=> ['fa-truck-loading', 'notification-warning'],
        'TRANSPORTE_ACEITO' => ['fa-truck-moving', 'notification-info'], 'TRANSPORTE_RECUSADO' => ['fa-times-circle', 'notification-danger'],
        'ENTREGA_CONFIRMADA' => ['fa-clipboard-check', 'notification-success'], 'TRANSPORTE_ALERTA' => ['fa-exclamation-triangle', 'notification-warning']
    ];
    return $map[$tipo] ?? ['fa-bell', ''];
}

$groups = []; foreach ($rows as $r) { $label = labelDate($r['created_at']); $groups[$label][] = $r; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>BovinTrade - Notificações</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary: #a30000; --primary-light: #d43b3b; --primary-dark: #7a0000;
            --secondary: #f8f5f2; --text: #333333; --text-light: #666666;
            --background: #ffffff; --border: #e0e0e0; --success: #4caf50;
            --warning: #ff9800; --info: #2196f3; --danger: #f44336;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Montserrat',sans-serif;background:#f9f9f9;color:var(--text);}
        header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1001;}
        .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
        .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; margin-left: 1rem; }
        .user-menu{ display:flex; align-items:center; gap:1.5rem; }
        .user-menu form button { background: none; border: none; color: white; cursor: pointer; font-size: 1rem;}
        .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
        .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
        .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; height: calc(100vh - 76px); position: sticky; top: 76px; overflow-y: auto; -ms-overflow-style: none;  scrollbar-width: none; }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar-menu{ list-style:none; padding-bottom: 2rem; }
        .sidebar-menu li { list-style: none; }
        .menu-item{ position: relative; padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
        .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
        .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
        .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
        .main{flex:1;padding:2.5rem; overflow-x: hidden;}
        .dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem; flex-wrap: wrap; gap: 1rem;}
        .dashboard-title{font-size:1.6rem;font-weight:600;color:var(--text); display: flex; align-items: center; gap: 0.75rem;}
        .actions{display:flex;gap:.5rem;flex-wrap:wrap;}
        .btn{padding:.6rem 1rem;border-radius:8px;border:1px solid var(--primary);background:var(--primary);color:#fff;font-weight:600;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:.5rem; text-decoration: none;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(163,0,0,.2);}
        .btn-outline{background:transparent;color:var(--primary);}
        .btn-outline:hover{background:rgba(163,0,0,.06);}
        .filters{display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 1.25rem;}
        .pill{border:1px solid var(--border);border-radius:999px;padding:.35rem .8rem;cursor:pointer;font-weight:600;color:var(--text-light);background:#fff; text-decoration: none;}
        .pill.active{border-color:var(--primary);color:var(--primary);background:#fff7f6;}
        .block{margin-bottom:1.5rem;}
        .block h3{font-size:1rem;font-weight:700;color:#111;letter-spacing:.3px;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem;}
        .block h3 .dot{width:10px;height:10px;background:var(--primary);border-radius:50%;display:inline-block;}
        .grid{display:flex;flex-direction:column;gap:0.8rem;}
        .notification-card{background:#fff;border:1px solid var(--border);border-left:5px solid var(--primary);border-radius:12px;padding:1rem;display:flex;gap:.9rem;align-items:flex-start;box-shadow:0 3px 10px rgba(0,0,0,.05);}
        .notification-unread{background:#fff8f6;border-left-color:var(--primary-light);}
        .notification-success{border-left-color:var(--success);} .notification-warning{border-left-color:var(--warning);}
        .notification-info{border-left-color:var(--info);} .notification-danger{border-left-color:var(--danger);}
        .notification-icon i{font-size:1.15rem;color:var(--primary);margin-top:.15rem;}
        .notification-success .notification-icon i{color:var(--success);} .notification-warning .notification-icon i{color:var(--warning);}
        .notification-info .notification-icon i{color:var(--info);} .notification-danger .notification-icon i{color:var(--danger);}
        .notification-content { flex-grow: 1; }
        .notification-title{font-weight:700;margin-bottom:.2rem;display:flex;justify-content:space-between;gap:.75rem; flex-wrap: wrap;}
        .notification-time{font-size:.85rem;color:var(--text-light);white-space:nowrap;}
        .notification-message{color:var(--text-light);font-size:.95rem;margin-top:.25rem;}
        .notification-actions{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.6rem;}
        .notification-btn{border:1px solid var(--border);background:#fff;color:#444;font-size:.9rem;border-radius:8px;padding:.35rem .65rem;cursor:pointer; font-family: inherit;}
        .notification-btn:hover{border-color:var(--primary);color:var(--primary);}
        .toolbar{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
        .counter{background:#fff;border:1px solid var(--border);border-radius:8px;padding:.45rem .75rem;color:var(--text-light);font-weight:700;}
        .hint{color:var(--text-light);font-size:.9rem;}
        .card-empty{border:2px dashed var(--border);background:#fff;border-radius:12px;padding:2rem;text-align:center;color:var(--text-light);}
        
        /* === NOVAS CLASSES DO BADGE (Bolinha Vermelha) === */
        .notif-badge {
            display: none; /* Começa escondido */
            background-color: var(--danger);
            color: white;
            font-weight: 700;
            border-radius: 50%;
            text-align: center;
            vertical-align: middle;
        }
        .menu-item .notif-badge { /* Badge no Menu Lateral */
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            width: 20px;
            height: 20px;
            line-height: 20px;
        }
        .dashboard-title .notif-badge { /* Badge no Título Principal */
            font-size: 0.9rem;
            width: 25px;
            height: 25px;
            line-height: 25px;
        }
        /* ============================================= */

        @media (max-width: 992px) {
           .sidebar { display: none; }
           .sidebar.active { display: block; width: 250px; position: fixed; left: 0; top: 76px; height: calc(100vh - 76px); z-index: 1000;}
           .hamburger { display: block; }
           .container { flex-direction: row; }
           .main { width: 100%; }
        }
        @media (max-width:768px){
            .dashboard-header{flex-direction:column;align-items:flex-start;}
            .toolbar{margin-top:1rem;}
            .sidebar{width:100%;border-right:none;box-shadow:none; position: fixed; top:76px; left:0; transform: translateX(-100%); height: calc(100vh - 76px); z-index: 1000; overflow-y: auto; -ms-overflow-style: none;  scrollbar-width: none; }
            .sidebar::-webkit-scrollbar { display: none; }
            .sidebar.active { transform: translateX(0); }
            .container{flex-direction:column;}
            .main{padding:1.5rem;}
        }
         @media (max-width: 480px) {
             header { padding: 1rem; }
             .logo { font-size: 1.5rem; }
             .user-menu span { display: none; }
             .main { padding: 1rem; }
             .btn { padding: .5rem .8rem; font-size: 0.9rem;}
             .filters { margin-bottom: 1rem;}
             .notification-card { padding: 0.8rem;}
             .notification-icon i { font-size: 1rem; }
             .notification-title { font-size: 0.95rem;}
             .notification-message { font-size: 0.9rem;}
             .notification-btn { font-size: 0.85rem; padding: .3rem .5rem;}
         }
    </style>
</head>
<body>
<header>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="logo">🐄 <span>BovinTrade • Frigorífico</span></div>
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
    </div>
    <div class="user-menu">
        <span><?= $email ?></span>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit">Sair</button>
        </form>
        <div class="user-avatar"><i class="fas fa-user"></i></div>
    </div>
</header>
<div class="container">
  <aside class="sidebar" id="sidebar"> <ul class="sidebar-menu">
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
         id="sidebar-notif-link" 
         class="menu-item <?= $current_page === 'notificacoes-frigorifico.php' ? 'active' : '' ?>">
         <i class="fas fa-bell"></i>
         <span>Notificações</span>
         <span class="notif-badge" id="sidebar-notif-badge" style="display:none;"></span>
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
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <i class="fas fa-bell"></i> Notificações
                <span class="notif-badge" id="main-notif-badge" style="display:none;"></span>
            </h1>
            <div class="toolbar">
                <span class="counter">Não lidas: <?= $unreadCount ?></span>
                <button id="btnMarkAll" class="btn" style="<?= $unreadCount == 0 ? 'display:none;' : '' ?>">
                    <i class="fas fa-check-double"></i> Marcar todas
                </button>
                <a class="btn btn-outline" href="notificacao-preferencias.php"><i class="fas fa-cog"></i> Configurações</a>
            </div>
        </div>

        <div class="filters">
            <?php
            // Abas ajustadas
            $tabs = [
                'all' => 'Todas', 'unread' => 'Não lidas', 'compra' => 'Compra',
                'transporte' => 'Transporte'
            ];
             // Define a chave ativa para comparação, usando o mapeamento
             $activeShowKey = $groupKeyMapping[$show] ?? $show;

            foreach ($tabs as $key => $label):
                 // Chave para a URL (mantém compra_venda se for o caso, senão usa a chave da aba)
                 $urlKey = ($key === 'compra') ? 'compra_venda' : $key;
            ?>
                <a class="pill <?= $activeShowKey === $key ? 'active' : '' ?>" href="?show=<?= e($urlKey) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($rows)): ?>
            <div class="card-empty">
                 <i class="fas fa-bell-slash" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                 <p>Sem notificações
                      <?php
                          if ($show === 'unread') echo 'não lidas';
                          elseif ($activeGroupKey) echo 'do tipo "' . e($tabs[$activeGroupKey] ?? $activeGroupKey) . '"';
                      ?>
                 no momento.</p>
                 <p class="hint">Os eventos novos aparecerão aqui automaticamente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $dateLabel => $list): ?>
                <section class="block">
                    <h3><span class="dot"></span><?= e($dateLabel) ?></h3>
                    <div class="grid">
                        <?php foreach ($list as $n):
                            [$icon, $extraClass] = typeMeta($n['tipo']);
                            $unread = empty($n['lida_em']);
                            $cls = 'notification-card' . ($unread ? ' notification-unread' : '') . ($extraClass ? ' ' . $extraClass : '');
                            $msgBody = trim((string)$n['mensagem']);
                            $title = trim((string)$n['titulo']);
                        ?>
                            <div class="col">
                                <article class="<?= e($cls) ?>" data-id="<?= (int)$n['id'] ?>">
                                    <div class="notification-icon"><i class="fas <?= e($icon) ?>"></i></div>
                                    <div class="notification-content">
                                        <div class="notification-title">
                                            <span><?= e($title ?: 'Notificação') ?></span>
                                            <span class="notification-time" data-timestamp="<?= e($n['created_at']) ?>">
                                                calculando...
                                            </span>
                                        </div>
                                        <?php if ($msgBody !== ''): ?>
                                            <p class="notification-message"><?= nl2br(e($msgBody)) ?></p>
                                        <?php endif; ?>
                                        <div class="notification-actions">
                                            <?php /* Lógica de Ações */
                                            switch ($n['tipo']) {
                                                case 'COMPRA_STATUS': case 'PAGAMENTO_DEVIDO': case 'PAGAMENTO_RECEBIDO': case 'LOTE_DISPONIVEL': case 'LOTE_REMOVIDO':
                                                     $refId = (int)json_decode($n['dados_json'] ?: '{}', true)['compra_id'] ?? $n['relacionado_id'] ?? 0;
                                                     $href = '10-historico-compras.php' . ($refId ? '?id=' . $refId : '');
                                                     echo '<a class="notification-btn" href="' . e($href) . '"><i class="fas fa-arrow-right"></i> Ver Compra/Lote</a>'; break;
                                                case 'TRANSPORTE_SOLICITADO':
                                                     echo '<a class="notification-btn" href="autorizar-coleta-frig.php"><i class="fas fa-check"></i> Autorizar Coleta</a>'; break;
                                                case 'ENTREGA_CONFIRMADA':
                                                     echo '<a class="notification-btn" href="09-recebimento-lotes.php?transporte_id='.e($n['relacionado_id'] ?? 0).'"><i class="fas fa-box-open"></i> Ver Recebimento</a>'; break;
                                                case 'TRANSPORTE_ACEITO': case 'TRANSPORTE_RECUSADO': case 'TRANSPORTE_ALERTA':
                                                     echo '<a class="notification-btn" href="historico-transporte-frig.php"><i class="fas fa-truck"></i> Ver Transporte</a>'; break;
                                                default: echo '<button class="notification-btn" disabled><i class="fas fa-info-circle"></i> Info</button>';
                                            } ?>
                                            <?php if ($unread): ?>
                                                <button type="button" class="notification-btn btn-mark" data-id="<?= (int)$n['id'] ?>"><i class="fas fa-check"></i> Marcar lida</button>
                                            <?php else: ?>
                                                <span class="hint">Lida</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <div class="actions" style="margin-top:1rem; justify-content: center;">
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline" href="?show=<?= e($show) ?>&page=<?= $page - 1 ?>"><i class="fas fa-arrow-left"></i> Anterior</a>
                <?php endif; ?>
                <?php if (count($rows) === $limit): ?>
                    <a class="btn btn-outline" href="?show=<?= e($show) ?>&page=<?= $page + 1 ?>">Mais <i class="fas fa-arrow-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    // === TEMPO DINÂMICO (COM CORREÇÃO DE PARSING E TIMEZONE) ===
    function updateTimeAgo() {
        document.querySelectorAll('.notification-time[data-timestamp]').forEach(el => {
            const timestampStr = el.dataset.timestamp;
            if (!timestampStr) { el.textContent = 'Data inválida'; return; }
            const isoTimestampStr = timestampStr.replace(' ', 'T'); // Formato ISO
            const dt = new Date(isoTimestampStr);
            if (isNaN(dt.getTime())) { el.textContent = 'Data inválida'; console.error('Falha ao parsear data:', timestampStr); return; }
            const now = new Date();
            const diff = Math.floor((now - dt) / 1000); // Segundos
            let text = '';
            if (diff < 60) text = 'agora mesmo';
            else if (diff < 3600) text = Math.floor(diff / 60) + ' min atrás';
            else if (diff < 86400) text = Math.floor(diff / 3600) + ' h atrás';
            else if (diff < 172800 && now.getDate() !== dt.getDate() && (now.getTime() - dt.getTime()) < (2 * 86400 * 1000)) text = 'ontem';
            else text = dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            el.textContent = text;
        });
    }

    // === MARCAÇÃO E CONTADOR (COM CORREÇÕES E ERROR HANDLING) ===
    async function atualizarContador() {
        try {
            const response = await fetch('notificacoes-frigorifico.php?action=count', {cache: 'no-store'});
            // *** CORREÇÃO DE SINTAXE AQUI ***
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            const count = data.count;

            // 1. Atualiza o contador de texto
            const counter = document.querySelector('.counter');
            if (counter) counter.textContent = `Não lidas: ${count}`;

            // 2. Atualiza o botão "Marcar todas"
            const btn = document.getElementById('btnMarkAll');
            if (btn) btn.style.display = count === 0 ? 'none' : 'inline-flex';

            // 3. ATUALIZA O BADGE DO TÍTULO
            const mainBadge = document.getElementById('main-notif-badge');
            if (mainBadge) {
                mainBadge.textContent = count;
                mainBadge.style.display = count > 0 ? 'inline-block' : 'none';
            }

            // 4. ATUALIZA O BADGE DA SIDEBAR
            const sidebarBadge = document.getElementById('sidebar-notif-badge');
            if (sidebarBadge) {
                sidebarBadge.textContent = count;
                sidebarBadge.style.display = count > 0 ? 'inline-block' : 'none';
            }

        } catch (e) { console.error("Erro ao atualizar contador:", e); }
    }

    document.querySelectorAll('.btn-mark').forEach(btn => {
        btn.addEventListener('click', async (event) => {
             event.stopPropagation();
            const id = btn.dataset.id;
            const card = btn.closest('.notification-card');
            if (!id || !card) { console.error("ID ou Card não encontrado."); return; }
            try {
                const response = await fetch('notificacoes-frigorifico.php?action=mark_read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({id})
                });
                // *** CORREÇÃO DE SINTAXE AQUI ***
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (result.ok) {
                    card.classList.remove('notification-unread');
                    const lidaSpan = document.createElement('span'); lidaSpan.className = 'hint'; lidaSpan.textContent = 'Lida';
                    if (btn.parentNode) { btn.parentNode.replaceChild(lidaSpan, btn); }
                    atualizarContador();
                } else { console.error("Falha ao marcar como lida (API):", result); }
            } catch(e) { console.error("Erro no fetch ao marcar notificação:", e); alert("Ocorreu um erro de rede. Tente novamente."); }
        });
    });

    const btnAll = document.getElementById('btnMarkAll');
    if (btnAll) {
        btnAll.addEventListener('click', async (event) => {
            event.stopPropagation();
            try {
                const response = await fetch('notificacoes-frigorifico.php?action=mark_all', {method: 'POST'});
                 // *** CORREÇÃO DE SINTAXE AQUI ***
                 if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                 const result = await response.json();
                 if (result.ok) {
                     document.querySelectorAll('.notification-card.notification-unread').forEach(c => {
                         c.classList.remove('notification-unread');
                         const markBtn = c.querySelector('.btn-mark');
                         if (markBtn && markBtn.parentNode) {
                             const lidaSpan = document.createElement('span'); lidaSpan.className = 'hint'; lidaSpan.textContent = 'Lida';
                             markBtn.parentNode.replaceChild(lidaSpan, markBtn);
                         }
                     });
                     atualizarContador();
                 } else { console.error("Falha ao marcar todas (API):", result); }
            } catch (e) { console.error("Erro no fetch ao marcar todas:", e); alert("Ocorreu um erro de rede. Tente novamente."); }
        });
    }

    // --- INICIALIZAÇÃO E INTERVALOS ---
    function initPage() { updateTimeAgo(); atualizarContador(); }
    document.addEventListener('DOMContentLoaded', initPage);
    setInterval(updateTimeAgo, 30000);
    setInterval(atualizarContador, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) initPage(); });

     // --- SIDEBAR MOBILE ---
     function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); }
     document.addEventListener('click', function(event) {
       const sidebar = document.getElementById('sidebar');
       const hamburger = document.querySelector('.hamburger');
       // Verifica se a sidebar, o hamburger existem e se o clique foi fora de ambos
       if (sidebar && sidebar.classList.contains('active') && hamburger && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
           sidebar.classList.remove('active');
       }
     });
</script>
</body>
</html>