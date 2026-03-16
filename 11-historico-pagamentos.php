<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();

// Proteção de rota
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if ($u['tipo_usuario'] === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

// 1. Obter e validar o ID do Frigorífico Logado (Assumindo que a chave é 'id' no array da sessão)
$id_frigorifico_logado = $u['id'] ?? null;
if (!$id_frigorifico_logado) {
    header('Location: login.php'); exit;
}
$id_frigorifico_logado = (int) $id_frigorifico_logado; // Garante que é um inteiro

$nome = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');

require 'conexao.php';

// --- Helpers ---
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts){ 
    if (strtotime($ts) === false) return 'N/A';
    return date('d/m/Y H:i', strtotime($ts)); 
}
function status_label_class($s){
    switch (strtoupper($s)) {
        case 'APROVADO': 
        case 'PAGO': return ['Pago', 'status-completed'];
        case 'PENDENTE': return ['Pendente', 'status-pending'];
        case 'CANCELADO': return ['Cancelado', 'status-canceled'];
        case 'ESTORNADO': return ['Estornado', 'status-refunded'];
        case 'PROCESSANDO': return ['Processando', 'status-processing'];
        default: return [ucfirst(strtolower($s)), 'status-pending'];
    }
}

// --- Filtros (GET) ---
$periodo = $_GET['periodo'] ?? '30';
$status = $_GET['status'] ?? 'todos';
$ini = $_GET['ini'] ?? '';
$fim = $_GET['fim'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Monta WHERE
// 2. Inicia a cláusula WHERE com a restrição do frigorífico logado.
$where = "ped.frigorifico_id = ?"; 
$params = [$id_frigorifico_logado];
$types = "i"; 

// período
if ($periodo === '30') {
    $where .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($periodo === '90') {
    $where .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
} elseif ($periodo === 'ano') {
    $where .= " AND YEAR(p.created_at) = YEAR(NOW())";
} elseif ($periodo === 'custom' && $ini && $fim) {
    $where .= " AND DATE(p.created_at) BETWEEN ? AND ?";
    $params[] = $ini; $types .= 's';
    $params[] = $fim; $types .= 's';
}

// status
$allowed_status = ['PAGO', 'PENDENTE', 'CANCELADO', 'ESTORNADO', 'PROCESSANDO', 'APROVADO'];

// Lógica de correção: Trata 'PAGO' como 'PAGO' ou 'APROVADO'
if (strtoupper($status) === 'PAGO') {
    $where .= " AND p.status IN (?, ?)";
    $params[] = 'PAGO'; $types .= 's';
    $params[] = 'APROVADO'; $types .= 's';
} 
// Mantém filtro para outros status
elseif (in_array(strtoupper($status), $allowed_status, true)) {
    $where .= " AND p.status = ?";
    $params[] = strtoupper($status); $types .= 's';
}

// ----------------------------------------------------
// --- Consulta paginada (para a tabela principal) ---
// ----------------------------------------------------
$sql_count = "SELECT COUNT(*) AS total 
              FROM pagamentos p 
              JOIN pedidos ped ON p.pedido_id = ped.id
              WHERE $where";
$stmt = $conn->prepare($sql_count);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = max(1, (int)ceil($total_rows / $limit));

$sql = "SELECT p.id, p.pedido_id, p.metodo, p.status, p.valor, p.moeda, p.referencia_externa, p.created_at
        FROM pagamentos p
        JOIN pedidos ped ON p.pedido_id = ped.id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";
$params_paged = $params;
$types_paged = $types . "ii";
$params_paged[] = $limit; $params_paged[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types_paged, ...$params_paged);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------------------------------------
// --- Consulta para o Modal de Nota Fiscal (Pagamentos APROVADO, sem paginação) ---
// -----------------------------------------------------------------------------------

$where_paid = "ped.frigorifico_id = ?"; 
$params_paid = [$id_frigorifico_logado];
$types_paid = "i"; 

// período (mesmo que acima, mas sem a variável $params e $types do filtro de status)
if ($periodo === '30') {
    $where_paid .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($periodo === '90') {
    $where_paid .= " AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
} elseif ($periodo === 'ano') {
    $where_paid .= " AND YEAR(p.created_at) = YEAR(NOW())";
} elseif ($periodo === 'custom' && $ini && $fim) {
    $where_paid .= " AND DATE(p.created_at) BETWEEN ? AND ?";
    $params_paid[] = $ini; $types_paid .= 's';
    $params_paid[] = $fim; $types_paid .= 's';
}

// Força status APROVADO (que é o status que permite gerar NF na sua lógica)
$where_paid .= " AND p.status = 'APROVADO'";

$sql_paid = "SELECT p.id, p.pedido_id, p.metodo, p.status, p.valor, p.moeda, p.referencia_externa, p.created_at
        FROM pagamentos p
        JOIN pedidos ped ON p.pedido_id = ped.id
        WHERE $where_paid
        ORDER BY p.created_at DESC";
$stmt_paid = $conn->prepare($sql_paid);
if ($params_paid) $stmt_paid->bind_param($types_paid, ...$params_paid);
$stmt_paid->execute();
$paid_rows = $stmt_paid->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_paid->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>BovinTrade - Histórico de Pagamentos</title>
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
            --success: #4CAF50;
            --warning: #FF9800;
            --info: #2196F3;
        }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); overflow-x: hidden; }
        header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
        .logo i{ font-size:1.6rem; }
        .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
        .user-menu{ display:flex; align-items:center; gap:1.5rem; }
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
        .history-container { background: var(--background); border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
        .history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .history-title { font-size: 1.8rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 1rem; }
        .filters { display: flex; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; min-width: 200px; }
        .filter-group label { margin-bottom: 0.5rem; font-weight: 500; color: var(--text); }
        .filter-group select, .filter-group input[type="date"] { padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; }
        .filter-group .date-range { display: flex; gap: .5rem; }
        #custom-dates { display: none; }
        .payments-table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: var(--background); border-radius: 12px; overflow: hidden; }
        .payments-table th { background-color: var(--primary); color: white; padding: 1rem; text-align: left; font-weight: 600; }
        .payments-table td { padding: 1rem; border-bottom: 1px solid var(--border); }
        .payments-table tr:hover { background-color: rgba(163,0,0,0.05); }
        .status-badge { padding: .35rem .75rem; border-radius: 20px; font-size: .85rem; font-weight: 500; display: inline-block; }
        .status-completed { background: rgba(76,175,80,0.1); color: var(--success); }
        .status-pending { background: rgba(255,152,0,0.1); color: var(--warning); }
        .status-canceled { background: rgba(244,67,54,0.1); color: #f44336; }
        .status-refunded { background: rgba(33,150,243,0.1); color: var(--info); }
        .status-processing { background: rgba(147,112,219,0.1); color: #9370db; }
        .view-details { color: var(--primary); text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: .5rem; }
        .view-details:hover { text-decoration: underline; }
        .pagination { display: flex; justify-content: center; margin-top: 2rem; gap: .5rem; }
        .page-item { padding: .5rem 1rem; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; background: #fff; text-decoration: none; color: var(--text); }
        .page-item.active { background-color: var(--primary); color: white; border-color: var(--primary); }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: .3s; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal { background: #fff; border-radius: 12px; width: 90%; max-width: 900px; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 8px 24px rgba(0,0,0,.15); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .modal-title { font-size: 1.4rem; font-weight: 600; color: var(--primary); }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-light); }
        .modal-content { display: flex; flex-direction: column; gap: 1.5rem; }
        .modal-section { padding: 1rem; border-bottom: 1px solid var(--border); }
        .modal-section:last-child { border-bottom: none; }
        .modal-section h3 { font-size: 1.2rem; font-weight: 600; color: var(--primary); margin-bottom: 1rem; }
        .modal-row { display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.9rem; }
        .modal-row .label { font-weight: 500; color: var(--text); }

        /* Estilo específico para o modal de NF */
        #invoiceModal .payments-table th { background-color: var(--info); }
        #invoiceModal .modal-title { color: var(--info); }
        
        @media (max-width: 768px) {
            .hamburger { display: block; }
            .user-menu { gap: 1rem; }
            .user-menu span { display: none; }
            .container { flex-direction: column; }
            .sidebar { width: 100%; transform: translateX(-100%); position: fixed; top: 76px; left: 0; height: calc(100vh - 76px); z-index: 1000; overflow-y: auto; box-shadow: none; border-right: none; }
            .sidebar.active { transform: translateX(0); }
            .resizer { display: none; }
            .main { padding: 1rem; width: 100%; }
            .history-container { padding: 1.5rem; }
            .filters { flex-direction: column; }
            .filter-group { min-width: auto; }
            .dashboard-title { font-size: 1.5rem; }
            .payments-table th, .payments-table td { padding: 0.75rem 0.5rem; }
        }

        @media (max-width: 480px) {
            header { padding: 1rem; }
            .logo { font-size: 1.5rem; }
            .user-menu { gap: 0.5rem; }
            .main { padding: 0.5rem; }
            .history-container { padding: 1rem; }
        }
    </style>
</head>
<body>
<header>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="logo">
            🐄
            <span>BovinTrade • Frigorífico</span>
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
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <a href="07-painel-frigorifico.php" class="menu-item <?= $current_page === '07-painel-frigorifico.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i><span>Painel</span>
            </a>
            <a href="meu-carrinho.php" class="menu-item <?= $current_page === 'meu-carrinho.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i><span>Meu Carrinho</span>
            </a>
            <a href="pesquisa-lotes.php" class="menu-item <?= $current_page === 'pesquisa-lotes.php' ? 'active' : '' ?>">
                <i class="fas fa-search"></i><span>Pesquisa de Lotes</span>
            </a>
            <a href="09-recebimento-lotes.php" class="menu-item <?= $current_page === '09-recebimento-lotes.php' ? 'active' : '' ?>">
                <i class="fas fa-truck-loading"></i><span>Recebimento</span>
            </a>
            <a href="10-historico-compras.php" class="menu-item <?= $current_page === '10-historico-compras.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i><span>Histórico de Compras</span>
            </a>
            <a href="11-historico-pagamentos.php" class="menu-item <?= $current_page === '11-historico-pagamentos.php' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i><span>Histórico de Pagamento</span>
            </a>
            <a href="autorizar-coleta-frig.php" class="menu-item <?= $current_page === 'autorizar-coleta-frig.php' ? 'active' : '' ?>">
                <i class="fas fa-check"></i><span>Autorizar Coleta de Lote</span>
            </a>
            <a href="historico-transporte-frig.php" class="menu-item <?= $current_page === 'historico-transporte-frig.php' ? 'active' : '' ?>">
                <i class="fas fa-truck"></i><span>Histórico de Transportes</span>
            </a>
            <a href="12-avaliacoes.php" class="menu-item <?= $current_page === '12-avaliacoes.php' ? 'active' : '' ?>">
                <i class="fas fa-star"></i><span>Avaliações</span>
            </a>
            <a href="notificacoes-frigorifico.php" class="menu-item <?= $current_page === 'notificacoes-frigorifico.php' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i><span>Notificações</span>
            </a>
            <a href="17-ajuda.php" class="menu-item <?= $current_page === '17-ajuda.php' ? 'active' : '' ?>">
                <i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span>
            </a>
            <a href="meu-perfil-frigorifico.php" class="menu-item <?= $current_page === 'meu-perfil-frigorifico.php' ? 'active' : '' ?>">
                <i class="fas fa-user-cog"></i><span>Meu Perfil</span>
            </a>
        </ul>
    </aside>
    <div class="resizer"></div>
    <main class="main">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-credit-card"></i> Histórico de Pagamentos</h1>
        </div>

        <div class="history-container">
            <form class="filters" method="get">
                <div class="filter-group">
                    <label>Período</label>
                    <select name="periodo" onchange="toggleCustom(this.value)">
                        <option value="30" <?= $periodo==='30'?'selected':'' ?>>Últimos 30 dias</option>
                        <option value="90" <?= $periodo==='90'?'selected':'' ?>>Últimos 90 dias</option>
                        <option value="ano" <?= $periodo==='ano'?'selected':'' ?>>Este ano</option>
                        <option value="custom" <?= $periodo==='custom'?'selected':'' ?>>Personalizado</option>
                    </select>
                </div>
                <div class="filter-group" id="custom-dates" style="display:<?= $periodo==='custom'?'block':'none' ?>;">
                    <label>De / Até</label>
                    <div class="date-range">
                        <input type="date" name="ini" value="<?= e($ini) ?>">
                        <input type="date" name="fim" value="<?= e($fim) ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="todos" <?= $status==='todos'?'selected':'' ?>>Todos</option>
                        <option value="PAGO" <?= $status==='PAGO'?'selected':'' ?>>Pago</option>
                        <option value="PENDENTE" <?= $status==='PENDENTE'?'selected':'' ?>>Pendente</option>
                        <option value="CANCELADO" <?= $status==='CANCELADO'?'selected':'' ?>>Cancelado</option>
                        <option value="ESTORNADO" <?= $status==='ESTORNADO'?'selected':'' ?>>Estornado</option>
                        <option value="PROCESSANDO" <?= $status==='PROCESSANDO'?'selected':'' ?>>Processando</option>
                    </select>
                </div>
                <div class="filter-group" style="align-self:end; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                    <button type="button" class="btn btn-outline" id="generateInvoiceBtn"><i class="fas fa-file-invoice"></i> Gerar Nota Fiscal</button>
                </div>
            </form>

            <table class="payments-table">
                <thead>
                    <tr>
                        <th>ID Pagamento</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Referência</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7">Nenhum pagamento encontrado.</td></tr>
                <?php else: foreach ($rows as $r):
                    [$label,$cls] = status_label_class($r['status']);
                ?>
                    <tr>
                        <td>#<?= (int)$r['id'] ?></td>
                        <td><?= e(dtbr($r['created_at'])) ?></td>
                        <td><?= brl($r['valor']).' '.e($r['moeda']) ?></td>
                        <td><?= e($r['metodo']) ?></td>
                        <td><?= e($r['referencia_externa']) ?></td>
                        <td><span class="status-badge <?= e($cls) ?>"><?= e($label) ?></span></td>
                        <td>
                            <a href="#" class="view-details" data-id="<?= (int)$r['id'] ?>">
                                <i class="fas fa-eye"></i> Detalhes
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($p=1;$p<=$total_pages;$p++): 
                    $q = $_GET; $q['page']=$p; $url = '?'.http_build_query($q);
                ?>
                    <a class="page-item <?= $p===$page?'active':'' ?>" href="<?= e($url) ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="paymentDetailsModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Detalhes do Pagamento <span id="mdl-pagamento-id"></span></h2>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div id="modal-body">Carregando...</div>
        
    </div>
</div>

<div class="modal-overlay" id="invoiceModal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-file-invoice"></i> Notas Fiscais</h2>
            <button class="close-modal" onclick="closeInvoiceModal()">&times;</button>
        </div>
        <div id="invoice-body">
            <p style="text-align:center;">Carregando notas fiscais...</p>
        </div>
    </div>
</div>

<div class="modal-overlay" id="nfViewerModal">
    <div class="modal" style="max-width: 1000px; height: 90vh;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-file-pdf"></i> Nota Fiscal Simulação <span id="nf-pagamento-id"></span></h2>
            <button class="close-modal" onclick="closeNFViewerModal()">&times;</button>
        </div>
            <iframe id="nf-iframe" style="width: 100%; height: 80vh; border: none; margin-top: 1rem;"></iframe>
        <div style="text-align: center; margin-top: 1rem;">
            <a id="downloadNFSimuladoBtn" href="#" class="btn btn-success"><i class="fas fa-download"></i> Baixar PDF Simulado</a>
        </div>
    </div>
</div>

<script>
function toggleCustom(val){
    document.getElementById('custom-dates').style.display = (val==='custom') ? 'block' : 'none';
}

const modal = document.getElementById('paymentDetailsModal');
const modalBody = document.getElementById('modal-body');
function openModal(){ modal.classList.add('active'); }
function closeModal(){ modal.classList.remove('active'); }

// Array global para armazenar os dados dos pagamentos
// CORREÇÃO DE SEGURANÇA APLICADA: 
// 1. Uso de '?? []' para evitar Undefined variable (se a consulta falhar)
// 2. Flags JSON_HEX_* para evitar quebra de JavaScript por caracteres especiais
const paymentData = <?php echo json_encode($rows ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const paidPaymentData = <?php echo json_encode($paid_rows ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

document.querySelectorAll('.view-details').forEach(a => {
    a.addEventListener('click', e => {
        e.preventDefault();
        const id = parseInt(a.dataset.id);
        // O find deve ser feito com base no ID do pagamento, que é uma string no array JSON do PHP
        const payment = paymentData.find(p => parseInt(p.id) === id); 

        if (payment) {
            document.getElementById('mdl-pagamento-id').textContent = '#' + id;
            const [$statusLabel, $statusClass] = status_label_class(payment.status);
            document.getElementById('modal-body').innerHTML = `
                <div class="modal-content">
                    <div class="modal-section">
                    <h3>Informações do Pagamento</h3>
                    <div class="modal-row"><span class="label">ID do Pagamento:</span><span>#${payment.id}</span></div>
                    <div class="modal-row"><span class="label">Data:</span><span>${dtbr(payment.created_at)}</span></div>
                    <div class="modal-row"><span class="label">Valor:</span><span>${brl(payment.valor)} ${payment.moeda}</span></div>
                    <div class="modal-row"><span class="label">Método:</span><span>${payment.metodo}</span></div>
                    <div class="modal-row"><span class="label">Referência Externa:</span><span>${payment.referencia_externa || 'N/A'}</span></div>
                    <div class="modal-row"><span class="label">Status:</span><span class="status-badge ${$statusClass}">${$statusLabel}</span></div>
                    </div>
                </div>
            `;
            openModal();
        } else {
            document.getElementById('modal-body').innerHTML = 'Pagamento não encontrado.';
            openModal();
        }
    });
});

// ------------------------------------------------------------------
// --- NOVAS FUNÇÕES E LÓGICA DO MODAL DE NOTA FISCAL (NF) ---
// ------------------------------------------------------------------

const nfModal = document.getElementById('nfViewerModal');
const nfIframe = document.getElementById('nf-iframe');
const nfDownloadBtn = document.getElementById('downloadNFSimuladoBtn');

function openNFViewerModal() { 
    nfModal.classList.add('active'); 
}
function closeNFViewerModal() { 
    nfModal.classList.remove('active'); 
    nfIframe.src = ''; // Limpa o iframe ao fechar
    nfDownloadBtn.href = '#'; // Limpa o link de download
}

// 1. Abre o modal com a lista de NFs (chamado pelo botão "Gerar Nota Fiscal")
function openInvoiceModal() {
    const modalNFList = document.getElementById('invoiceModal');
    modalNFList.classList.add('active');
    const invoiceBody = document.getElementById('invoice-body');
    
    let html = '<div class="modal-content"><div class="modal-section" style="padding: 0;">';
    
    if (paidPaymentData.length === 0) {
        html += '<p style="padding: 1rem; text-align: center;">Nenhum pagamento aprovado encontrado para gerar nota fiscal no período selecionado.</p>';
    } else {
        html += '<table class="payments-table" style="box-shadow: none;"><thead><tr><th>ID Pagamento</th><th>Pedido ID</th><th style="text-align: center;">Ações</th></tr></thead><tbody>';
        
        paidPaymentData.forEach(p => {
            // CORREÇÃO DE SINTAXE JAVASCRIPT: Uso correto de template literal (backticks)
            const viewUrl = `nota_fiscal.php?id=${p.id}&action=view`;
            const downloadUrl = `nota_fiscal.php?id=${p.id}&action=download`;

            html += `<tr>
                <td>#${p.id}</td>
                <td>#${p.pedido_id}</td>
                <td style="text-align: center; display: flex; gap: 0.5rem; justify-content: center;">
                    <button class="btn btn-primary btn-sm" onclick="showNFViewer(${p.id}, '${viewUrl}', '${downloadUrl}')"><i class="fas fa-eye"></i> Ver</button>
                    <a href="${downloadUrl}" target="_blank" class="btn btn-success btn-sm" title="Baixar NF"><i class="fas fa-download"></i> Baixar</a>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
    }
    html += '</div></div>';
    invoiceBody.innerHTML = html;
}

// 2. Função que carrega o simulador no iframe do novo modal (chamada pelo botão "Ver")
function showNFViewer(id, viewUrl, downloadUrl) {
    // Fecha o modal de lista de NFs
    closeInvoiceModal(); 
    
    // CORREÇÃO DE SINTAXE JAVASCRIPT: Uso correto de template literal (backticks)
    document.getElementById('nf-pagamento-id').textContent = `#${id}`;
    
    // Configura o botão de download dentro do modal de visualização
    nfDownloadBtn.href = downloadUrl;
    nfDownloadBtn.target = '_blank'; // O download abre em nova aba/janela

    // Carrega o conteúdo HTML gerado pelo PHP no iframe
    nfIframe.src = viewUrl;
    openNFViewerModal();
}

// 3. Fecha o modal de lista de NFs
function closeInvoiceModal() {
    document.getElementById('invoiceModal').classList.remove('active');
}

// Event listener para o botão Gerar Nota Fiscal
document.getElementById('generateInvoiceBtn').addEventListener('click', e => {
    e.preventDefault();
    openInvoiceModal();
});

// Resizer functionality
let isResizing = false;
const resizer = document.querySelector('.resizer');
const sidebar = document.querySelector('.sidebar');

resizer.addEventListener('mousedown', function(e) {
    isResizing = true;
    document.addEventListener('mousemove', resize);
    document.addEventListener('mouseup', stopResize);
});

function resize(e) {
    if (!isResizing) return;
    let newWidth = e.clientX - sidebar.getBoundingClientRect().left;
    if (newWidth < 200) newWidth = 200;
    let maxWidth = window.innerWidth - 100;
    if (newWidth > maxWidth) newWidth = maxWidth;
    sidebar.style.width = newWidth + 'px';
}

function stopResize() {
    isResizing = false;
    document.removeEventListener('mousemove', resize);
    document.removeEventListener('mouseup', stopResize);
}

// Mobile sidebar toggle
function toggleSidebar() {
    sidebar.classList.toggle('active');
}

// Funções de formatação (copiadas do PHP para o JS, garantindo a funcionalidade do Modal)
function status_label_class(s) {
    switch (s.toUpperCase()) {
        case 'APROVADO': // Usando APROVADO para pagamentos que geram NF
        case 'PAGO': return ['Pago', 'status-completed'];
        case 'PENDENTE': return ['Pendente', 'status-pending'];
        case 'CANCELADO': return ['Cancelado', 'status-canceled'];
        case 'ESTORNADO': return ['Estornado', 'status-refunded'];
        case 'PROCESSANDO': return ['Processando', 'status-processing'];
        default: return [s.charAt(0).toUpperCase() + s.slice(1).toLowerCase(), 'status-pending'];
    }
}
function dtbr(ts) { 
    // Aceita o formato YYYY-MM-DD HH:MM:SS (vindo do PHP)
    const date = new Date(ts.replace(' ', 'T'));
    if (isNaN(date.getTime())) return 'N/A';
    return date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }); 
}
function brl(v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
</script>
</body>
</html>