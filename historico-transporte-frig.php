<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();

// --- CORREÇÃO CRÍTICA DE FUSO HORÁRIO ---
// Define o fuso horário padrão para o Brasil (São Paulo) para todas as operações de data/hora.
date_default_timezone_set('America/Sao_Paulo'); 
// ------------------------------------------

require_once 'config.php'; // Assumindo que o arquivo config existe e define $pdo
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if ($u['tipo_usuario'] === 'FAZENDA')      { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}
$nome   = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');
$frigorifico_id = $u['id'];
// --- Helpers ---
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// FUNÇÃO ATUALIZADA: Formata Data/Hora (DD/MM/AAAA HH:MM) a partir de campos separados
function dtbr($date_ts, $time_ts = null){
    if (!$date_ts || strtotime($date_ts) === false || substr($date_ts, 0, 10) === '0000-00-00') return 'N/A';
    
    $dateTimeStr = $date_ts;
    // Se a hora for fornecida e não for '00:00:00' (ou nula), anexa à data
    if ($time_ts && substr($time_ts, 0, 5) !== '00:00') {
        $dateTimeStr = date('Y-m-d', strtotime($date_ts)) . ' ' . substr($time_ts, 0, 5);
    }
    
    // Formata para DD/MM/AAAA HH:MM
    return date('d/m/Y H:i', strtotime($dateTimeStr));
}

// FUNÇÃO ATUALIZADA: Formata apenas a Data (DD/MM/AAAA)
function dtval($ts){
    if (!$ts || strtotime($ts) === false || substr($ts, 0, 10) === '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($ts));
}
function status_label_class($s){
    switch (strtoupper($s ?? '')) {
        case 'ENTREGUE': return ['Entregue', 'status-entregue'];
        case 'CANCELADO': return ['Cancelado', 'status-cancelado'];
        default: return [ucfirst(strtolower(str_replace('_', ' ', $s ?? ''))), 'status-processing'];
    }
}
// --- FIM Helpers ---
// FILTROS
$where_clauses = ["t.frigorifico_id = :fid", "t.status IN ('ENTREGUE', 'CANCELADO')"];
$params = [':fid' => $frigorifico_id];
$search_term = $_GET['search'] ?? '';
if (!empty($search_term)) { $where_clauses[] = "(CAST(t.id AS CHAR) LIKE :search OR f.nome_razao LIKE :search OR tr.nome_razao LIKE :search)"; $params[':search'] = '%' . $search_term . '%'; }
$status_filter = $_GET['status'] ?? '';
if (!empty($status_filter) && in_array($status_filter, ['ENTREGUE', 'CANCELADO'])) { $where_clauses[] = "t.status = :status"; $params[':status'] = $status_filter; }
$start_date = $_GET['start_date'] ?? '';
if (!empty($start_date)) { $where_clauses[] = "DATE(t.data_retirada) >= :start_date"; $params[':start_date'] = $start_date; }
$end_date = $_GET['end_date'] ?? '';
if (!empty($end_date)) { $where_clauses[] = "DATE(t.data_retirada) <= :end_date"; $params[':end_date'] = $end_date; }
else { if (!empty($start_date)) { $end_date = $start_date; $where_clauses[] = "DATE(t.data_retirada) <= :end_date_single"; $params[':end_date_single'] = $end_date; } }
$transportadora_filter = $_GET['transportadora'] ?? '';
if (!empty($transportadora_filter)) { $where_clauses[] = "tr.nome_razao = :transportadora"; $params[':transportadora'] = $transportadora_filter; }
$sql_where = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
// --- Consulta SQL (Adiciona data_entrega_real e hora_entrega_real) ---
$stmt = $pdo->prepare("
    SELECT
        t.id, t.pedido_id, t.data_retirada, t.hora_retirada, t.status, t.status_aceite, t.mensagem_transportadora, t.data_prevista_entrega,
        t.data_entrega_real, t.hora_entrega_real, 
        tr.nome_razao AS transportadora_nome, f.nome_razao AS fazenda_nome,
        m.nome AS motorista_nome, m.cpf AS motorista_cpf, m.cnh_numero AS motorista_cnh_numero,
        m.cnh_categoria AS motorista_cnh_categoria, m.cnh_uf AS motorista_cnh_uf,
        m.cnh_validade AS motorista_cnh_validade, m.telefone AS motorista_telefone, m.email AS motorista_email
    FROM transportes t
    JOIN usuarios tr ON tr.id = t.transportadora_id
    JOIN usuarios f ON f.id = t.fazenda_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    $sql_where
    ORDER BY t.data_retirada DESC
");
$stmt->execute($params);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Busca transportadoras únicas para o filtro
$unique_transportadoras_obj = $pdo->prepare("SELECT DISTINCT tr.nome_razao FROM transportes t JOIN usuarios tr ON t.transportadora_id = tr.id WHERE t.frigorifico_id = :fid ORDER BY tr.nome_razao");
$unique_transportadoras_obj->execute([':fid' => $frigorifico_id]);
$unique_transportadoras = $unique_transportadoras_obj->fetchAll(PDO::FETCH_COLUMN);
// JSON para JS
$transportes_json = json_encode($transportes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>BovinTrade - Histórico de Transportes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #a30000; --primary-dark: #7a0000; --text: #333;
            --text-light: #666; --background: #fff; --border: #e0e0e0;
            --success: #4caf50; --danger: #f44336; --bg-light: #f9f9f9;
            --processing: #9370db;
        }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Montserrat',sans-serif; background:var(--bg-light); color:var(--text); }
        header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:#fff; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,.1); }
        .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:.75rem; }
        .user-menu{ display:flex; align-items:center; gap:1.5rem; }
         .user-menu form button { background: none; border: none; color: white; cursor: pointer; font-size: 1rem; }
        .user-avatar{ width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; }
        .container{ display:flex; min-height:calc(100vh - 76px); }
        .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,.05); flex-shrink: 0; }
        .sidebar-menu{ list-style:none; }
        .sidebar-menu li { list-style: none; }
        .menu-item{ padding:.8rem 1.5rem; display:flex; align-items:center; gap:.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:.2s; }
        .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
        .menu-item:hover{ background:rgba(163,0,0,.05); color:var(--primary); border-left:3px solid var(--primary); }
        .menu-item.active{ background:rgba(163,0,0,.1); color:var(--primary); border-left:3px solid var(--primary); }
        .main{ flex:1; padding:2.5rem; overflow-x:auto; }
        .main h1 { font-size: 1.8rem; font-weight: 600; color: var(--text); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(163, 0, 0, 0.2); }
        .btn-outline { background-color: transparent; color: var(--primary); border: 1px solid var(--primary); }
        .btn-outline:hover { background-color: rgba(163, 0, 0, 0.05); }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        .filters { background:var(--background); border-radius:10px; padding:1.5rem; margin-bottom:2rem; box-shadow:0 4px 12px rgba(0,0,0,.05); }
        .filter-row { display: flex; gap: 1.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; display: flex; flex-direction: column; }
        .filter-group:nth-child(3) { flex: 0 0 320px; }
        .filter-group:nth-child(4) { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text); font-size: 0.9rem; }
        .filter-group input, .filter-group select { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 6px; font-family: inherit; font-size: 0.9rem; }
        .filter-group .date-range { display: flex; gap: .5rem; }
        .filter-group .date-range input { width: auto; flex: 1; }
        .filter-actions { display: flex; justify-content: flex-start; gap: 1rem; margin-top: 1rem; flex-wrap: wrap; }
        .transports-history { background:var(--background); border-radius:10px; padding: 0; box-shadow:0 4px 12px rgba(0,0,0,.05); overflow-x: auto; }
        .history-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .history-table th { text-align: left; padding: 1rem; background-color: var(--primary); color: white; font-weight: 600; border-bottom: 2px solid var(--primary); white-space: nowrap; }
        .history-table td { padding: 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; white-space: nowrap; font-size: 0.9rem; }
        .history-table tr:last-child td { border-bottom: none; }
        .history-table tr:not(.details-row-hidden):hover td { background-color: rgba(163, 0, 0, 0.02); }
        .transport-id { font-weight: 600; color: var(--primary); }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; text-transform: uppercase; }
        .status-entregue { background-color: rgba(76, 175, 80, 0.1); color: var(--success); }
        .status-cancelado { background-color: rgba(244, 67, 54, 0.1); color: var(--danger); }
        .view-details { color: var(--primary); text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: .3rem; cursor: pointer; padding: .3rem .6rem; border: 1px solid transparent; border-radius: 4px; background: none; font-family: inherit; font-size: 0.85rem;} 
        .view-details:hover { text-decoration: none; background-color: rgba(163, 0, 0, 0.05); border-color: rgba(163, 0, 0, 0.1); }
        .details-row-hidden { display: none; }
        .details-row-hidden.active { display: table-row; background-color: #fffafa; }
        .transport-details-panel { padding: 1.5rem; background-color: transparent; margin: 0; border-top: 1px dashed var(--border); }
        .details-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .details-section { background-color: white; border-radius: 8px; padding: 1rem; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
        .details-section-title { font-weight: 600; margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); font-size: 1rem; }
        .details-row { display: flex; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .details-label { flex: 0 0 150px; font-weight: 500; color: var(--text-light); font-size: 0.9rem; padding-right: 10px; }
        .details-value { flex: 1; font-size: 0.9rem; min-width: 150px; word-break: break-word; white-space: normal; }
        @media (max-width: 992px) { 
             .sidebar { display: none; }
             .sidebar.active { display: block; width: 250px; position: fixed; left: 0; top: 76px; height: calc(100vh - 76px); z-index: 1000;}
             .hamburger { display: block; }
             .container { flex-direction: row; } 
             .main { width: 100%; }
        }
        @media (max-width: 768px) {
            .container { flex-direction: column; } 
            .sidebar { width: 100%; position: fixed; top: 76px; left:0; transform: translateX(-100%); height: calc(100vh - 76px); z-index: 1000; overflow-y: auto; box-shadow: none; border-right: none;}
            .sidebar.active { transform: translateX(0); }
            .main { padding: 1rem; }
            .history-table th, .history-table td { font-size: 0.85rem; white-space: normal; padding: 0.75rem 0.5rem; }
            .details-grid { grid-template-columns: 1fr; }
            .filter-row { flex-direction: column; gap: 1rem; }
            .filter-group { min-width: 100%; }
            .filter-group:nth-child(3) { flex: 1 1 auto; } 
            .filter-actions { justify-content: space-between; gap: 0.5rem; }
            .filter-actions .btn { flex: 1; }
        }
        @media (max-width: 480px) {
            header { padding: 1rem; }
            .logo { font-size: 1.5rem; }
            .user-menu span { display: none; }
            .main { padding: 0.5rem; }
            .history-table th, .history-table td { font-size: 0.8rem; }
            .details-row { flex-direction: column; gap: 0.25rem; }
            .details-label { flex: 1; padding-right: 0; font-weight: 600; color: var(--text); }
            .btn { padding: 0.6rem 1rem; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">🐄 <span>BovinTrade • Frigorífico</span></div>
    <div class="user-menu">
        <span><?php echo $email; ?></span>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit">Sair</button>
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
        <h1><i class="fas fa-history"></i> Histórico de Transportes</h1>
        <form class="filters" method="GET" action="">
           <div class="filter-row">
                 <div class="filter-group">
                    <label for="search">Buscar</label>
                    <input type="text" name="search" id="search" placeholder="ID, Pedido, Fazenda, Transportadora..." value="<?= e($search_term) ?>">
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">Todos os status</option>
                        <option value="ENTREGUE" <?= $status_filter === 'ENTREGUE' ? 'selected' : '' ?>>Entregue</option>
                        <option value="CANCELADO" <?= $status_filter === 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="start-date">Data Retirada</label>
                    <div class="date-range">
                        <input type="date" name="start_date" id="start-date" value="<?= e($start_date) ?>" placeholder="Data Inicial">
                        <input type="date" name="end_date" id="end-date" value="<?= e($end_date) ?>" placeholder="Data Final">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="transportadora">Transportadora</label>
                    <select name="transportadora" id="transportadora">
                        <option value="">Todas as transportadoras</option>
                        <?php foreach($unique_transportadoras as $transp): ?>
                            <option value="<?= e($transp) ?>" <?= $transportadora_filter === $transp ? 'selected' : '' ?>><?= e($transp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                <button type="button" class="btn btn-outline" onclick="clearFiltersAndSubmit()">Limpar Filtros</button>
                <button type="button" class="btn btn-outline" onclick="exportToCSV()"> <i class="fas fa-download"></i> Exportar CSV</button>
            </div>
        </form>
        <div class="transports-history">
            <?php if(count($transportes) === 0): ?>
                <p style="text-align: center; color: var(--text-light); padding: 2rem;">Nenhum transporte finalizado ou cancelado no histórico com os filtros aplicados.</p>
            <?php else: ?>
                <table class="history-table" id="transportsTable">
                    <thead>
                        <tr>
                            <th>ID Transp.</th>
                            <th>Pedido</th>
                            <th>Fazenda Origem</th>
                            <th>Transportadora</th>
                            <th>Data/Hora Retirada</th> <th>Data/Hora Entrega</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transportes as $t):
                            [$statusLabel,$statusClass] = status_label_class($t['status']);
                            // FORMATADO COM HORA
                            $dataHoraRetiradaFmt = dtbr($t['data_retirada'], $t['hora_retirada']); 
                            $dataEntregaFmt = dtbr($t['data_entrega_real'] ?? '', $t['hora_entrega_real'] ?? ''); 
                            $dataPrevistaFmt = dtval($t['data_prevista_entrega'] ?? '');
                        ?>
                            <tr data-id="<?= $t['id'] ?>">
                                <td class="transport-id">#TR-<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td>#<?= $t['pedido_id'] ?></td>
                                <td><?= e($t['fazenda_nome']) ?></td>
                                <td><?= e($t['transportadora_nome']) ?></td>
                                <td><?= $dataHoraRetiradaFmt ?></td> <td><?= $dataEntregaFmt ?></td>
                                <td><span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                <td>
                                    <button type="button" class="view-details">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                </td>
                            </tr>
                            <tr class="details-row-hidden">
                                <td colspan="8" style="padding: 0;"> <div class="transport-details-panel">
                                        <div class="details-grid">
                                            <div class="details-section">
                                                <h3 class="details-section-title"><i class="fas fa-info-circle"></i> Informações do Transporte</h3>
                                                 <div class="details-row">
                                                     <div class="details-label">ID Transporte:</div> <div class="details-value">#TR-<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                                 </div>
                                                 <div class="details-row">
                                                     <div class="details-label">Pedido:</div> <div class="details-value">#<?= $t['pedido_id'] ?></div>
                                                 </div>
                                                 <div class="details-row">
                                                     <div class="details-label">Status:</div> <div class="details-value"><span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></div>
                                                 </div>
                                                 <div class="details-row">
                                                     <div class="details-label">Data/Hora Retirada:</div> <div class="details-value"><?= $dataHoraRetiradaFmt ?></div> </div>
                                                 <div class="details-row">
                                                     <div class="details-label">Data/Hora Entrega:</div> <div class="details-value"><?= $dataEntregaFmt ?></div>
                                                 </div>
                                                 <div class="details-row">
                                                     <div class="details-label">Prev. Entrega:</div> <div class="details-value"><?= dtval($t['data_prevista_entrega'] ?? '') ?></div>
                                                 </div>
                                            </div>
                                               <div class="details-section">
                                                   <h3 class="details-section-title"><i class="fas fa-users"></i> Partes Envolvidas</h3>
                                                   <div class="details-row"> <div class="details-label">Fazenda:</div> <div class="details-value"><?= e($t['fazenda_nome']) ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">Transportadora:</div> <div class="details-value"><?= e($t['transportadora_nome']) ?></div> </div>
                                               </div>
                                               <div class="details-section">
                                                   <h3 class="details-section-title"><i class="fas fa-user-circle"></i> Detalhes do Motorista</h3>
                                                   <div class="details-row"> <div class="details-label">Nome:</div> <div class="details-value"><?= e($t['motorista_nome'] ?? 'N/A') ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">CPF:</div> <div class="details-value"><?= e($t['motorista_cpf'] ?? 'N/A') ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">Telefone:</div> <div class="details-value"><?= e($t['motorista_telefone'] ?? 'N/A') ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">Email:</div> <div class="details-value"><?= e($t['motorista_email'] ?? 'N/A') ?></div> </div>
                                               </div>
                                               <div class="details-section">
                                                   <h3 class="details-section-title"><i class="fas fa-id-badge"></i> Detalhes da CNH</h3>
                                                   <div class="details-row"> <div class="details-label">Número CNH:</div> <div class="details-value"><?= e($t['motorista_cnh_numero'] ?? 'N/A') ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">Categoria:</div> <div class="details-value"><?= e($t['motorista_cnh_categoria'] ?? 'N/A') ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">UF:</div> <div class="details-value"><?= e($t['motorista_cnh_uf'] ?? 'N/A') ?></div> </div>
                                                   <div class="details-row"> <div class="details-label">Validade:</div> <div class="details-value"><?= dtval($t['motorista_cnh_validade'] ?? '') ?></div> </div>
                                               </div>
                                            </div>
                                    </div>
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
// --- JAVASCRIPT DETALHES ---
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', (e) => {
        e.preventDefault();
        const mainRow = button.closest('tr');
        const detailsRow = mainRow.nextElementSibling;
        if (detailsRow && detailsRow.classList.contains('details-row-hidden')) {
            document.querySelectorAll('.details-row-hidden.active').forEach(row => {
                if (row !== detailsRow) {
                    row.classList.remove('active');
                    const associatedButton = row.previousElementSibling.querySelector('.view-details i');
                    if (associatedButton) associatedButton.className = 'fas fa-eye';
                }
            });
            detailsRow.classList.toggle('active');
            const icon = button.querySelector('i');
            icon.className = detailsRow.classList.contains('active') ? 'fas fa-eye-slash' : 'fas fa-eye';
        }
    });
});
function clearFiltersAndSubmit() { 
    document.getElementById('search').value = '';
    document.getElementById('status').value = '';
    document.getElementById('start-date').value = '';
    document.getElementById('end-date').value = '';
    document.getElementById('transportadora').value = '';
    document.querySelector('.filters').submit();
}
// --- JAVASCRIPT CSV (AJUSTADO PARA NOVAS COLUNAS) ---
function exportToCSV() {
    const table = document.getElementById('transportsTable');
    
    // Cabeçalhos: Visíveis (7 colunas) + Detalhes (9 colunas - Mensagem Transp)
    // O cabeçalho 'Data/Hora Retirada' está na coluna 5 (index 4)
    const mainHeadersVisible = Array.from(table.querySelectorAll('thead th'))
         .slice(0, -1) // Remove Ações (Total de 7 cabeçalhos)
         .map(th => `"${th.textContent.trim().replace(/"/g, '""')}`); 
         
    const detailHeaders = [ // Cabeçalhos dos detalhes
        "Prev. Entrega", "Motorista", "CPF Motorista", "Telefone Motorista",
        "Email Motorista", "CNH", "Cat. CNH", "UF CNH", "Val. CNH" // Mensagem Transp. REMOVIDA
    ];
    
    // Junta cabeçalhos visíveis + cabeçalhos de detalhes
    const allHeaders = [...mainHeadersVisible, ...detailHeaders].join(',');
    let csvContent = `data:text/csv;charset=utf-8,${allHeaders}\r\n`;
    const rows = table.querySelectorAll('tbody tr:not(.details-row-hidden)');
    
    rows.forEach(row => {
        // Pega dados da linha principal visível (7 colunas de dados)
        const mainCells = row.querySelectorAll('td');
        const mainData = Array.from(mainCells).slice(0, -1) // Pega os 7 TDs de dados
             .map((td, index) => {
                 let text = td.textContent.trim();
                 if (index === 6) { // Coluna Status (index 6)
                      text = td.querySelector('.status-badge')?.textContent.trim() || td.textContent.trim();
                 }
                 return `"${text.replace(/"/g, '""')}"`;
             });
             
        // Pega dados da linha de detalhes
        const detailsRow = row.nextElementSibling;
        let detailData = Array(detailHeaders.length).fill('"N/A"'); 
        
        if (detailsRow && detailsRow.classList.contains('details-row-hidden')) {
             const findDetailValue = (labelText) => {
                 const labels = detailsRow.querySelectorAll('.details-label');
             for (let label of labels) {
                 if (label.textContent.trim().endsWith(labelText)) {
                     const valueElement = label.nextElementSibling;
                     return valueElement ? valueElement.textContent.trim() : 'N/A';
                 }
             }
             return 'N/A';
             };
             
             detailData = [ // Pega os detalhes na ordem definida em detailHeaders
                 `"${findDetailValue('Prev. Entrega:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('Nome:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('CPF:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('Telefone:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('Email:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('Número CNH:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('Categoria:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('UF:').replace(/"/g, '""')}"`,
                 `"${findDetailValue('Validade:').replace(/"/g, '""')}"`
             ];
        }
        // Junta colunas principais visíveis + colunas de detalhes
        csvContent += [...mainData, ...detailData].join(',') + "\r\n";
    });
    // Código para download
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    const date = new Date().toISOString().slice(0, 10);
    link.setAttribute('download', `historico_transportes_frigorifico_${date}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
</body>
</html>
