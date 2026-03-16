<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php?expired=1"); exit; }
$u = $_SESSION['usuario'];

// --- Proteção de Rota (Transportadora) ---
if (($u['tipo_usuario'] ?? '') !== 'TRANSPORTADORA') {
    if (($u['tipo_usuario'] ?? '') === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    elseif (($u['tipo_usuario'] ?? '') === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
    header('Location: login.php'); exit;
}
require_once 'config.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
$uid = (int)$u['id'];
$email = e($u['email'] ?? '');
$nome  = e($u['nome_razao'] ?? 'Transportadora');

// --- CONTADOR DE NOTIFICAÇÕES ---
$notif_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL");
$notif_count_stmt->execute([$uid]);
$unread_count = (int)$notif_count_stmt->fetchColumn();

// --- AJAX HANDLERS ---
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

// --- FILTROS E CONSULTA ---
$valid_show = ['all', 'unread', 'solicitacoes', 'pagamentos', 'alertas'];
$show = in_array($_GET['show'] ?? '', $valid_show) ? $_GET['show'] : 'all';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Grupos de notificação para Transportadora
$typeGroups = [
    'solicitacoes' => ['SOLICITACAO_TRANSPORTE', 'SOLICITACAO_ATUALIZADA'],
    'alertas'      => ['COLETA_AUTORIZADA', 'TRANSPORTE_INICIADO', 'TRANSPORTE_CONCLUIDO', 'ALERTA_GERAL'],
    'pagamentos'   => ['PAGAMENTO_LIBERADO', 'PAGAMENTO_RECEBIDO_TRANSP', 'PAGAMENTO_DEVIDO_TRANSP']
];

$where = "usuario_id = ?";
$params = [$uid];

if ($show === 'unread') {
    $where .= " AND lida_em IS NULL";
} elseif (array_key_exists($show, $typeGroups)) { // Checa se $show é uma chave de grupo válida
    $in = $typeGroups[$show];
    if (count($in) > 0) {
        $ph = implode(',', array_fill(0, count($in), '?'));
        $where .= " AND tipo IN ($ph)";
        $params = array_merge($params, $in);
    } else { $where .= " AND 1=0"; }
} elseif ($show !== 'all') {
    $show = 'all';
}

$sql = "SELECT id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id, created_at, lida_em
        FROM notificacoes WHERE $where ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === HELPERS ===
function labelDate($dt) {
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

function typeMeta($tipo) {
    $map = [
        'SOLICITACAO_TRANSPORTE'   => ['fa-truck-loading', 'notification-info'],
        'SOLICITACAO_ATUALIZADA'   => ['fa-sync-alt', 'notification-warning'],
        'COLETA_AUTORIZADA'        => ['fa-check-circle', 'notification-success'],
        'TRANSPORTE_INICIADO'      => ['fa-truck', 'notification-info'],
        'TRANSPORTE_CONCLUIDO'     => ['fa-flag-checkered', 'notification-success'],
        'PAGAMENTO_LIBERADO'       => ['fa-money-check-alt', 'notification-success'],
        'PAGAMENTO_RECEBIDO_TRANSP'=> ['fa-check-circle', 'notification-success'],
        'PAGAMENTO_DEVIDO_TRANSP'  => ['fa-money-bill-wave', 'notification-warning'],
        'ALERTA_GERAL'             => ['fa-exclamation-triangle', 'notification-danger']
    ];
    return $map[$tipo] ?? ['fa-bell', ''];
}

$groups = [];
foreach ($rows as $r) {
    $label = labelDate($r['created_at']);
    $groups[$label][] = $r;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>BovinTrade - Notificações</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <style>
        :root {
            --primary: #a30000; --primary-dark: #7a0000; --text: #333333;
            --text-light: #666666; --background: #ffffff; --border: #e0e0e0;
            --success: #4caf50; --warning: #ff9800; --info: #2196f3; --danger: #f44336;
        }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); overflow-x:hidden; }
        header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
        .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
        .user-menu{ display:flex; align-items:center; gap:1.5rem; }
        .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
        .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
        .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
        .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; }
        .resizer { width: 5px; background: var(--border); cursor: col-resize; height: 100%; display: flex; align-items: center; }
        .resizer:hover { background: var(--primary); }
        .sidebar-menu{ list-style:none; }
        .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; position: relative; }
        .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
        .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
        .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
        
   /* === BADGE (Bolinha Vermelha) === */
.notif-badge {
    display: none; /* Começa escondido */
    background-color: var(--danger);
    color: white;
    font-weight: 700;
    border-radius: 50%;
    text-align: center;
    /* Linhas Adicionadas/Alteradas para Centralização */
    display: flex; /* Garante que o Flexbox funcione */
    align-items: center; /* Centraliza verticalmente */
    justify-content: center; /* Centraliza horizontalmente */
    /* Fim das Alterações */
}
/* ... o resto do seu CSS para .menu-item .notif-badge e .dashboard-title .notif-badge pode permanecer ... */
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
        /* ================================ */
        
        .main{ flex:1; padding:2.5rem; min-width:0; }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap: wrap;}
        .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text); display: flex; align-items: center; gap: 0.75rem;}
        .dashboard-actions { display:flex; gap:1rem;}
        .btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem; text-decoration: none;}
        .btn-primary { background-color: var(--primary); color:white;}
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
        .btn-outline { background-color:transparent; color:var(--primary); border:1px solid var(--primary);}
        .btn-outline:hover { background-color: rgba(163,0,0,0.05);}
        .filters { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; justify-content: flex-start; }
        .pill { padding: 0.5rem 1rem; border-radius: 999px; font-size: 0.9rem; font-weight: 600; color: var(--text-light); background: #fff; border: 1px solid var(--border); text-decoration: none; transition: 0.2s; }
        .pill:hover { border-color: var(--primary); color:var(--primary); }
        .pill.active { background: rgba(163,0,0,0.1); color:var(--primary); border-color:var(--primary); }
        .block { margin-bottom: 2rem; }
        .block h3 { font-size: 1.1rem; font-weight: 700; color:var(--text); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .block h3 .dot { width: 10px; height: 10px; background: var(--primary); border-radius: 50%; }
        .notification-card { background: #fff; border: 1px solid var(--border); border-left: 5px solid var(--primary); border-radius: 12px; padding: 1rem; display: flex; gap: 1rem; box-shadow: 0 3px 10px rgba(0,0,0,0.05); transition: all 0.2s; }
        .notification-unread { background: #fff8f8; border-left-color: #d32f2f; }
        .notification-success { border-left-color: #4caf50; }
        .notification-warning { border-left-color: #ff9800; }
        .notification-info { border-left-color: #2196f3; }
        .notification-danger { border-left-color: #f44336; }
        .notification-icon i { font-size: 1.2rem; color: var(--primary); margin-top: .15rem;}
        .notification-success .notification-icon i { color: #4caf50; }
        .notification-warning .notification-icon i { color: #ff9800; }
        .notification-info .notification-icon i { color: #2196f3; }
        .notification-danger .notification-icon i { color: #f44336; }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 700; font-size: 1rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .notification-time { font-size: 0.85rem; color: var(--text-light); white-space: nowrap; }
        .notification-message { color: var(--text-light); font-size: 0.95rem; margin-top: 0.25rem; }
        .notification-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
        .notification-btn { padding: 0.4rem 0.8rem; border: 1px solid var(--border); background: #fff; color: var(--text); font-size: 0.9rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: 0.2s; font-family: inherit;}
        .notification-btn:hover { border-color: var(--primary); color: var(--primary); }
        .counter { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; font-weight: 600; color: var(--text-light); }
        .card-empty { border: 2px dashed var(--border); background: #fff; border-radius: 12px; padding: 2.5rem; text-align: center; color: var(--text-light); }
        .card-empty i { font-size: 2.5rem; margin-bottom: 1rem; display: block; color: var(--border); }
        .hint { color: var(--text-light); font-size: 0.9rem; }
        
        @media (max-width: 992px) {
           .sidebar { display: none; }
           .sidebar.active { display: block; width: 250px; position: fixed; left: 0; top: 76px; height: calc(100vh - 76px); z-index: 1000;}
           .hamburger { display: block; }
           .container { flex-direction: row; }
           .main { width: 100%; }
        }
        @media (max-width: 768px) {
            .hamburger { display: block; }
            .user-menu span { display: none; }
            .container { flex-direction: column; }
            .sidebar { width: 100%; transform: translateX(-100%); position: fixed; top: 76px; left: 0; height: calc(100vh - 76px); z-index: 1000; overflow-y: auto; box-shadow: none; border-right: none; }
            .sidebar.active { transform: translateX(0); }
            .resizer { display: none; }
            .main { padding: 1.5rem; }
            .dashboard-title { font-size: 1.5rem; }
            .dashboard-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .filters { justify-content: flex-start; }
            .notification-card { flex-direction: column; }
            .notification-actions { justify-content: flex-start; }
        }
        @media (max-width: 480px) {
             header { padding: 1rem; }
             .logo { font-size: 1.5rem; }
         }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(163,0,0,.3); }
            70% { box-shadow: 0 0 0 12px rgba(163,0,0,0); }
            100% { box-shadow: 0 0 0 0 rgba(163,0,0,0); }
        }
        .highlight { animation: pulse 2s; background:#fff8e1 !important; }
    </style>
</head>
<body>
<header>
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">🐄 <span>BovinTrade • Transportadora</span></div>
    <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
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
    <i class="fas fa-user-circle"></i><span>Meu Perfil</span></a>
    </ul>
  </aside>

    <div class="resizer" style="display: none;"></div>

    <main class="main">
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <i class="fas fa-bell"></i> Notificações
                <span class="notif-badge" id="main-notif-badge" style="display: <?= $unread_count > 0 ? 'inline-block' : 'none' ?>;"><?= $unread_count ?></span>
            </h1>
            <div class="dashboard-actions">
                <span class="counter">Não lidas: <span id="unreadCount"><?= $unread_count ?></span></span>
                <button id="btnMarkAll" class="btn btn-outline" style="<?= $unread_count == 0 ? 'display:none;' : '' ?>">
                    <i class="fas fa-check-double"></i> Marcar todas
                </button>
                <a class="btn btn-outline" href="notificacao-preferencias-transp.php"><i class="fas fa-cog"></i> Configurações</a>
            </div>
        </div>

        <div class="filters">
            <?php
            // Abas dinâmicas para Transportadora
            $tabs = [
                'all' => 'Todas', 
                'unread' => 'Não lidas', 
                'solicitacoes' => 'Solicitações',
            ];
            
            foreach ($tabs as $key => $label):
            ?>
                <a class="pill <?= $show === $key ? 'active' : '' ?>" href="?show=<?= e($key) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($rows)): ?>
            <div class="card-empty">
                <i class="fas fa-bell-slash"></i>
                <p>Sem notificações
                    <?php
                        if ($show === 'unread') echo 'não lidas';
                        elseif (isset($tabs[$show])) echo 'do tipo "' . e($tabs[$show]) . '"';
                    ?>
                no momento.</p>
                <p class="hint">Novos eventos aparecerão aqui automaticamente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $dateLabel => $list): ?>
                <div class="block">
                    <h3><span class="dot"></span><?= e($dateLabel) ?></h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($list as $n):
                            [$icon, $extraClass] = typeMeta($n['tipo']);
                            $unread = empty($n['lida_em']);
                            $cls = 'notification-card' . ($unread ? ' notification-unread' : '') . ($extraClass ? ' ' . $extraClass : '');
                            $dados = json_decode($n['dados_json'] ?? '{}', true) ?: [];

                            // Link dinâmico
                            $link = '#';
                            $btnText = 'Ver Detalhes';
                            $btnIcon = 'fa-eye';

                            // Lógica de Ações para Transportadora
                            switch ($n['tipo']) {
                                case 'SOLICITACAO_TRANSPORTE':
                                    $link = "pedidos-transportes.php";
                                    $btnText = 'Ver Negociações';
                                    $btnIcon = 'fa-handshake';
                                    break;
                                case 'COLETA_AUTORIZADA':
                                    $link = "coletas-agendadas.php";
                                    if(!empty($n['relacionado_id'])) $link .= "#transporte-{$n['relacionado_id']}";
                                    $btnText = 'Ver Coletas';
                                    $btnIcon = 'fa-calendar-check';
                                    break;
                                case 'TRANSPORTE_INICIADO':
                                    $link = "rastreamento-transporte-t.php";
                                    $btnText = 'Rastrear';
                                    $btnIcon = 'fa-map-marker-alt';
                                    break;
                                case 'TRANSPORTE_CONCLUIDO':
                                case 'PAGAMENTO_LIBERADO':
                                    $link = "historico-transporte-t.php";
                                    $btnText = 'Ver Histórico';
                                    $btnIcon = 'fa-history';
                                    break;
                                default:
                                    // Fallback para histórico se for um tipo desconhecido
                                    $link = "historico-transporte-t.php";
                                    $btnIcon = 'fa-info-circle';
                                    $btnText = 'Ver Detalhes';
                            }
                        ?>
                            <article class="<?= e($cls) ?>" data-id="<?= (int)$n['id'] ?>">
                                <div class="notification-icon"><i class="fas <?= e($icon) ?>"></i></div>
                                <div class="notification-content">
                                    <div class="notification-title">
                                        <span><?= e($n['titulo'] ?: 'Nova Notificação') ?></span>
                                        <span class="notification-time" data-timestamp="<?= e($n['created_at']) ?>">agora</span>
                                    </div>

                                    <?php if (!empty($n['mensagem'])): ?>
                                        <p class="notification-message"><?= nl2br(e($n['mensagem'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="notification-actions">
                                        <a href="<?= e($link) ?>" class="notification-btn">
                                            <i class="fas <?= e($btnIcon) ?>"></i> <?= e($btnText) ?>
                                        </a>
                                        <?php if ($unread): ?>
                                            <button type="button" class="notification-btn btn-mark" data-id="<?= (int)$n['id'] ?>">
                                                <i class="fas fa-check"></i> Marcar lida
                                            </button>
                                        <?php else: ?>
                                            <span class="hint">Lida</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>

<script>
// --- SCRIPT ATUALIZADO E CORRIGIDO ---

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

function updateTimeAgo() {
    document.querySelectorAll('.notification-time[data-timestamp]').forEach(el => {
        const timestampStr = el.dataset.timestamp;
        if (!timestampStr) { el.textContent = 'Data inválida'; return; }
        const isoTimestampStr = timestampStr.replace(' ', 'T');
        const dt = new Date(isoTimestampStr);
        if (isNaN(dt.getTime())) { el.textContent = 'Data inválida'; return; }
        
        const now = new Date();
        const diff = Math.floor((now - dt) / 1000);
        let text = '';
        if (diff < 60) text = 'agora mesmo';
        else if (diff < 3600) text = Math.floor(diff / 60) + ' min atrás';
        else if (diff < 86400) text = Math.floor(diff / 3600) + ' h atrás';
        else text = dt.toLocaleDateString('pt-BR');
        el.textContent = text;
    });
}

async function atualizarContador() {
    try {
        const res = await fetch('?action=count', {cache: 'no-store'});
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`); // Correção de sintaxe
        const data = await res.json();
        const count = data.count;

        // 1. Atualiza o contador de texto
        document.getElementById('unreadCount').textContent = count;
        
        // 2. Atualiza o botão "Marcar todas"
        const btn = document.getElementById('btnMarkAll');
        if (btn) btn.style.display = count === 0 ? 'none' : 'inline-flex';

        // 3. ATUALIZA O BADGE DA SIDEBAR (Estilo original do seu código)
        let badge = document.getElementById('sidebar-badge');
        if (count > 0) {
            if (!badge) {
                const link = document.querySelector('.sidebar a[href="notificacoes-transportadora.php"]');
                if (link) {
                    const span = document.createElement('span');
                    span.className = 'notif-badge';
                    span.id = 'sidebar-badge';
                    link.appendChild(span);
                    badge = span;
                }
            }
            if(badge) {
                badge.textContent = count;
                badge.style.display = 'flex';
            }
        } else {
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // 4. ATUALIZA O BADGE DO TÍTULO (A "bolinha vermelha")
        const mainBadge = document.getElementById('main-notif-badge');
        if (mainBadge) {
            mainBadge.textContent = count;
            mainBadge.style.display = count > 0 ? 'inline-block' : 'none';
        }

    } catch (e) { console.error("Erro ao atualizar contador:", e); }
}

document.querySelectorAll('.btn-mark').forEach(btn => {
    btn.addEventListener('click', async (event) => { // Adicionado 'event'
        event.stopPropagation(); // Adicionado para parar a propagação
        const id = btn.dataset.id;
        const card = btn.closest('.notification-card');
        try {
            const res = await fetch('?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({id})
            });
            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`); // Correção de sintaxe
            
            card.classList.remove('notification-unread');
            btn.outerHTML = '<span class="hint">Lida</span>';
            atualizarContador();
        } catch (e) { console.error("Erro no fetch 'mark_read':", e); }
    });
});

document.getElementById('btnMarkAll')?.addEventListener('click', async (event) => { // Adicionado 'event'
    event.stopPropagation(); // Adicionado para parar a propagação
    try {
        const res = await fetch('?action=mark_all', {method: 'POST'});
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`); // Correção de sintaxe
        
        document.querySelectorAll('.notification-unread').forEach(c => {
            c.classList.remove('notification-unread');
            const btn = c.querySelector('.btn-mark');
            if (btn) btn.outerHTML = '<span class="hint">Lida</span>';
        });
        atualizarContador();
    } catch (e) { console.error("Erro no fetch 'mark_all':", e); }
});

document.addEventListener('DOMContentLoaded', () => {
    updateTimeAgo();
    setInterval(updateTimeAgo, 30000);
    setInterval(atualizarContador, 15000);
    // Chama a função para garantir que os contadores e badges estejam corretos no load
    atualizarContador(); 

    // --- Sidebar Mobile Toggle ---
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger');
        if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });

    // --- Resizer (ativado em desktop) ---
    const resizer = document.querySelector('.resizer');
    if (window.innerWidth > 768 && resizer) {
        resizer.style.display = 'flex';
        const sidebar = document.querySelector('.sidebar');
        const container = document.querySelector('.container');
        let isResizing = false;

        resizer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isResizing = true;
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
            container.style.cursor = 'col-resize';
        });

        function resize(e) {
            if (!isResizing) return;
            let newWidth = e.clientX; 
            if (newWidth < 200) newWidth = 200;
            if (newWidth > 400) newWidth = 400;
            sidebar.style.width = newWidth + 'px';
        }

        function stopResize() {
            isResizing = false;
            document.removeEventListener('mousemove', resize);
            document.removeEventListener('mouseup', stopResize);
            container.style.cursor = '';
        }
    }
    
    // --- Destaque de Hash (para links de notificação) ---
    const hash = window.location.hash;
    if (hash && hash.startsWith('#transporte-')) {
        const notifId = hash.split('-')[1];
        // O hash pode não ser o ID da notificação, mas o ID do transporte.
        // O ideal é que o 'relacionado_id' da notificação seja o ID do transporte.
        // Vamos procurar pelo card que tenha o link para esse hash
        const targetLink = document.querySelector(`a[href*="${hash}"]`);
        if (targetLink) {
            const target = targetLink.closest('.notification-card');
            if (target) {
                setTimeout(() => {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.classList.add('highlight');
                    setTimeout(() => target.classList.remove('highlight'), 2500);
                }, 300);
            }
        }
    }
});
</script>
</body>
</html>
