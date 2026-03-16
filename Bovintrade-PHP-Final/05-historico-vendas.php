<?php
// 05-historico-vendas.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php'; // Altere para '/../config.php' se estiver usando o padrão do seu outro código

// ---------- Autenticação básica (ajuste se precisar) ----------
$usuario = $_SESSION['usuario'] ?? null;
// Se sua sessão tiver outro formato, ajuste aqui:
$fazendaId = (int)($usuario['id'] ?? 0);
$current_page = basename($_SERVER['PHP_SELF']); // Adicionado para highlight no menu

// Validação se é Fazenda
if (($usuario['tipo_usuario'] ?? '') !== 'FAZENDA' || $fazendaId <= 0) {
    // Redireciona outros tipos de usuário ou se não logado
    if (($usuario['tipo_usuario'] ?? '') === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
    if (($usuario['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php?expired=1'); exit;
}

$email = $usuario['email'] ?? 'Fazenda'; // Usar um nome genérico se email não estiver disponível

// ---------- Helpers ----------
function brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts) {
    if (!$ts) return '-';
    $t = is_numeric($ts) ? (int)$ts : strtotime($ts);
    // Verifica se strtotime falhou
    if ($t === false || $t < 0) return '-';
    return date('d/m/Y H:i', $t);
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sel($a, $b) { return $a === $b ? 'selected' : ''; }

// ---------- Filtros (GET) ----------
$periodo = $_GET['periodo'] ?? '30'; // '30','90','ano','custom'
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$statusFiltro = $_GET['status'] ?? 'todos'; // 'todos','concluida','pendente','cancelada'
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// datas
$inicio = null; $fim = null;
$hoje = new DateTime('today');
switch ($periodo) {
    case '90':
        $inicio = (clone $hoje)->modify('-90 days')->format('Y-m-d 00:00:00');
        $fim    = (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
        break;
    case 'ano':
        $inicio = date('Y-01-01 00:00:00');
        $fim    = (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
        break;
    case 'custom':
        // esperados em formato YYYY-MM-DD
        $ini = preg_replace('/[^0-9\-]/','',$data_ini);
        $fi  = preg_replace('/[^0-9\-]/','',$data_fim);
        $inicio = $ini ? $ini.' 00:00:00' : '1970-01-01 00:00:00';
        // Se a data final for definida, incluir o dia inteiro
        $fim    = $fi  ? $fi .' 23:59:59' : (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
        break;
    case '30':
    default:
        $inicio = (clone $hoje)->modify('-30 days')->format('Y-m-d 00:00:00');
        $fim    = (clone $hoje)->modify('+1 day')->format('Y-m-d 00:00:00');
        break;
}

// ---------- Query principal ----------
// CAMPOS NOVOS ADICIONADOS PARA O MODAL:
// - u.email, u.cnpj, u.telefone, u.rua, u.numero, u.bairro, u.cidade, u.estado, u.cep (Detalhes do Frigorífico)
// - lb.raca, lb.peso_medio_kg, lb.tipo_alimentacao, lb.historico_vacinacao (Detalhes do Lote)
$sql = "
    SELECT
        pi.id                   AS venda_item_id,
        pi.status               AS status_item,
        pi.lote_id,
        pi.quantidade_cabecas,
        pi.preco_unitario_cab,
        pi.valor_total,

        p.id                    AS pedido_id,
        p.frigorifico_id,
        p.status                AS status_pedido,
        p.created_at            AS criado_em,

        lb.codigo_lote,
        lb.raca,                
        lb.peso_medio_kg,       
        lb.tipo_alimentacao,    
        lb.historico_vacinacao, 

        u.nome_razao            AS frigorifico_nome,
        u.email                 AS frigorifico_email,    -- NOVO
        u.cnpj                  AS frigorifico_cnpj,     -- NOVO
        u.telefone              AS frigorifico_telefone, -- NOVO
        u.rua                   AS frigorifico_rua,      -- NOVO
        u.numero                AS frigorifico_numero,   -- NOVO
        u.bairro                AS frigorifico_bairro,   -- NOVO
        u.cidade                AS frigorifico_cidade,   -- NOVO
        u.estado                AS frigorifico_estado,   -- NOVO
        u.cep                   AS frigorifico_cep,      -- NOVO

        pg.status               AS status_pagamento
    FROM pedido_itens pi
    JOIN pedidos p
        ON p.id = pi.pedido_id
    LEFT JOIN usuarios u 
        ON u.id = p.frigorifico_id
    LEFT JOIN lote_bois lb
        ON lb.id = pi.lote_id
    LEFT JOIN (
        SELECT x1.pedido_id, x1.status
        FROM pagamentos x1
        JOIN (
            SELECT pedido_id, MAX(id) AS max_id
            FROM pagamentos
            GROUP BY pedido_id
        ) x2 ON x2.pedido_id = x1.pedido_id AND x2.max_id = x1.id
    ) pg ON pg.pedido_id = p.id
    WHERE pi.fazenda_id = ?
        AND p.created_at >= ?
        AND p.created_at <= ?
    ORDER BY p.created_at DESC, pi.id DESC
    LIMIT 1000 -- Limite alto para buscar todos e paginar em memória
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iss', $fazendaId, $inicio, $fim);
$stmt->execute();
$res = $stmt->get_result();

$linhas = [];
while ($r = $res->fetch_assoc()) {
    // Deriva "status_venda" (Concluída/Pendente/Cancelada)
    $stPedido = $r['status_pedido'] ?? '';
    $stPg     = $r['status_pagamento'] ?? '';
    $stItem   = $r['status_item'] ?? '';

    // Lógica para determinar o status final da venda
    if ($stPedido === 'CANCELADO' || in_array($stPg, ['CANCELADO','EXPIRADO'], true) || $stItem === 'CANCELADO') {
        $statusVenda = 'Cancelada';
    } elseif ($stPedido === 'PAGO' || $stPg === 'APROVADO' || $stItem === 'CONFIRMADO') {
         // Considerando que 'CONFIRMADO' no item pode significar entrega/conclusão
        $statusVenda = 'Concluída';
    } else {
        // Outros status como AGUARDANDO_PAGAMENTO, PROCESSANDO, APROVADO (se houver no item)
        $statusVenda = 'Aguardando pagamento'; // Ou outro status pendente relevante
    }

    // Aplica filtro de status (se selecionado)
    $ok = true;
    if ($statusFiltro === 'concluida' && $statusVenda !== 'Concluída') $ok = false;
    if ($statusFiltro === 'pendente'  && $statusVenda !== 'Aguardando pagamento') $ok = false;
    if ($statusFiltro === 'cancelada' && $statusVenda !== 'Cancelada') $ok = false;

    if ($ok) {
        $r['status_venda'] = $statusVenda;
        $linhas[] = $r;
    }
}
$stmt->close();

// ---------- Paginação em memória ----------
/* ---------- Export CSV ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // limpa qualquer saída anterior
    while (ob_get_level()) { ob_end_clean(); }

    // nome do arquivo com data
    $fname = 'historico_vendas_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$fname.'"');
    // BOM para Excel no Windows reconhecer UTF-8
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // cabeçalho (usando ; que costuma abrir bem no Excel pt-BR)
    fputcsv($out, [
        'ID Venda', 'Data', 'Código do Lote', 'Qtde (cabeças)',
        'Comprador (Frigorífico)', 'Preço Unit. (R$)', 'Valor Total (R$)',
        'Status da Venda'
    ], ';');

    // exporta TODAS as linhas filtradas (não só a página atual)
    foreach ($linhas as $row) {
        $idVenda   = '#VDA-' . (int)$row['venda_item_id'];
        $data      = dtbr($row['criado_em']);
        $lote      = $row['codigo_lote'] ?? ('ID ' . $row['lote_id']);
        $qtde      = (int)$row['quantidade_cabecas'];
        $comprador = $row['frigorifico_nome'] ?? ('Frigorífico #' . (int)$row['frigorifico_id']);
        $precoUnit = number_format((float)$row['preco_unitario_cab'], 2, ',', '.');
        $valorTot  = number_format((float)$row['valor_total'],       2, ',', '.');
        $status    = $row['status_venda'] ?? '-';

        fputcsv($out, [
            $idVenda, $data, $lote, $qtde, $comprador, $precoUnit, $valorTot, $status
        ], ';');
    }

    fclose($out);
    exit;
}

$total = count($linhas);
$totalPages = (int)ceil(max(1, $total) / $perPage);
$page = min($page, $totalPages); // Garante que a página não seja maior que o total
$offset = ($page - 1) * $perPage;
$pagina = array_slice($linhas, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>BovinTrade - Histórico de Vendas</title>
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
      --success: #4caf50;
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
        .history-container { 
            max-width: 100%; 
        }
        .history-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem; 
            flex-wrap: wrap; /* Para responsividade */
            gap: 1rem; /* Espaço entre título e botão */
        }
        .history-title { 
            font-size: 1.8rem; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: .5rem; 
        }
        .filters { 
            background: #fff; 
            border-radius: 12px; 
            padding: 2rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,.05); 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; 
            align-items: end; 
        }
        .group { 
            display: flex; 
            flex-direction: column; 
        }
        .group label { 
            margin-bottom: .5rem; 
            font-weight: 500; 
            font-size: 0.9rem; /* Tamanho ajustado */
            color: var(--text-light);
        }
        input, select, .btn { /* Estilo unificado */
            width: 100%; 
            padding: .75rem 1rem; 
            border: 1px solid var(--border); 
            border-radius: 6px; 
            font-family: 'Montserrat', sans-serif; 
            font-size: 0.95rem; 
        }
        input[type="date"] {
            line-height: 1.5; /* Ajuste para melhor visualização da data */
        }
        input[disabled] { 
            background: #f6f6f6; 
            color: #999; 
            cursor: not-allowed;
        }
        .btn { 
            padding: .75rem 1.25rem; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            border: 1px solid var(--primary); 
            background: var(--primary); /* Botão filtrar primário */
            color: #fff; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; /* Centralizar ícone e texto */
            gap: .5rem; 
            transition: background-color 0.2s ease;
        }
        .btn:hover { 
            background: var(--primary-dark); 
            border-color: var(--primary-dark);
        }
        .btn-outline { /* Botão exportar */
            background: #fff;
            color: var(--primary);
            border-color: var(--primary);
        }
        .btn-outline:hover {
            background: rgba(163,0,0,.05);
        }
        
        .table-wrapper { /* Adicionado para responsividade da tabela */
            overflow-x: auto;
            background: #fff; 
            box-shadow: 0 4px 8px rgba(0,0,0,.1); 
            border-radius: 8px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            min-width: 800px; /* Evita que a tabela fique muito espremida */
        }
        th, td { 
            padding: 12px 15px; /* Ajustado padding */
            border-bottom: 1px solid var(--border); /* Apenas borda inferior */
            text-align: left; 
            vertical-align: middle; /* Alinhado ao meio */
            font-size: 0.9rem; /* Tamanho de fonte ajustado */
        }
        th { 
            background-color: var(--primary); 
            color: #fff; 
            white-space: nowrap; 
            font-weight: 600;
        }
        tr:nth-child(even) td { /* Mudado para aplicar em TD */
            background-color: var(--zebra); 
        }
        tr:last-child td {
            border-bottom: none; /* Remove borda do último item */
        }
        td button.btn { /* Botão de detalhes menor */
             padding: .4rem .8rem;
             font-size: 0.85rem;
             background: #fff;
             color: var(--primary);
             border: 1px solid var(--primary);
        }
        td button.btn:hover {
            background: rgba(163,0,0,.05);
        }
        .status-badge { 
            padding: .3rem .7rem; 
            border-radius: 20px; 
            font-size: .8rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            white-space: nowrap;
        }
        .status-ok { background: #d4edda; color: #155724; }
        .status-wait { background: #fff3cd; color: #856404; }
        .status-cancel { background: #f8d7da; color: #721c24; }
        .pagination { 
            display: flex; 
            gap: .4rem; 
            align-items: center; 
            margin-top: 1.5rem; 
            flex-wrap: wrap; 
            justify-content: center; /* Centralizar paginação */
        }
        .pagination a, .pagination span { 
            padding: .5rem .8rem; 
            border: 1px solid var(--border); 
            border-radius: 6px; 
            text-decoration: none; 
            color: var(--primary); 
            background: #fff;
            font-weight: 500;
        }
        .pagination a:hover {
            background: rgba(163,0,0,.05);
        }
        .pagination .active { 
            background: var(--primary); 
            color: #fff; 
            border-color: var(--primary); 
        }
        .muted { color: #999; }
        
        @media (max-width: 1024px) {
            .container { flex-direction: column; } /* Ajuste para layout mobile */
            .sidebar { 
                width: 100%; /* Sidebar ocupa toda a largura */
                border-right: none;
                border-bottom: 1px solid var(--border);
                box-shadow: none;
                padding: 1rem 0; /* Menos padding */
            }
            .main { padding: 1.5rem; }
            .filters { grid-template-columns: 1fr; } /* Filtros em coluna única */
            .history-header { align-items: flex-start; }
        }
        
        /* Estilos do Modal (Janela Flutuante) */
        .modal {
            display: none; position: fixed; z-index: 2000; 
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.5); 
            backdrop-filter: blur(2px); padding-top: 60px;
        }
        .modal-content {
            background-color: var(--background); margin: 5% auto; 
            padding: 30px; border: 1px solid var(--border);
            border-radius: 8px; width: 90%; max-width: 600px; 
            position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            color: var(--primary); margin-top: 0; margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem;
            font-weight: 600; font-size: 1.4rem;
        }
        .modal-content h4 {
            color: var(--primary-dark); margin-top: 1.5rem; margin-bottom: 0.8rem;
            font-weight: 600; font-size: 1.1rem;
        }
        .modal-content p {
            margin-bottom: 0.8rem; font-size: 0.95rem; line-height: 1.5;
            word-wrap: break-word; display: flex; 
            border-bottom: 1px solid #f0f0f0; /* Linha separadora suave */
            padding-bottom: 0.8rem;
        }
        .modal-content p:last-of-type { /* Último P (vacina) não precisa de borda */
             border-bottom: none;
             padding-bottom: 0;
             display: block; /* Para permitir quebras de linha em vacinas */
        }
        .modal-content p strong {
            font-weight: 600; color: var(--text); /* Cor normal para label */
            display: inline-block; min-width: 150px; /* Largura mínima ajustada */
            margin-right: 10px; flex-shrink: 0;
        }
        .modal-content p span { /* Valor */
             color: var(--text-light);
             flex-grow: 1; /* Ocupa espaço restante */
        }
         .modal-content p#modal-vacinas-container { /* Container da vacina */
             display: block;
         }
        .modal-content p#modal-vacinas-container span#modal-vacinas { /* Span da vacina */
            white-space: pre-wrap; /* Mantém quebras de linha */
            color: var(--text-light);
            display: block; /* Para ocupar a linha */
            margin-top: 0.3rem; /* Espaço após o título */
        }
        .modal-content hr {
            border: 0; border-top: 1px solid var(--border);
            margin: 1.5rem 0;
        }
        .close-btn {
            color: #aaa; position: absolute; top: 10px; right: 15px; /* Posição ajustada */
            font-size: 28px; font-weight: bold; line-height: 20px;
            transition: color 0.2s; background: none; border: none; cursor: pointer;
        }
        .close-btn:hover, .close-btn:focus { color: var(--primary); }
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
    <span><?= e($email) ?></span>
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
            <div class="history-container">
                <div class="history-header">
                    <h1 class="history-title"><i class="fas fa-history"></i> Histórico de Vendas</h1>
                    <div>
                        <a class="btn btn-outline" href="?export=csv&periodo=<?= e($periodo) ?>&data_ini=<?= e($data_ini) ?>&data_fim=<?= e($data_fim) ?>&status=<?= e($statusFiltro) ?>"><i class="fas fa-download"></i> Exportar CSV</a>
                    </div>
                </div>

                <form class="filters" method="get">
                    <div class="group">
                        <label for="periodo">Período</label>
                        <select name="periodo" id="periodo" onchange="toggleDateInputs(this.value); this.form.submit()">
                            <option value="30" <?= sel($periodo,'30') ?>>Últimos 30 dias</option>
                            <option value="90" <?= sel($periodo,'90') ?>>Últimos 90 dias</option>
                            <option value="ano" <?= sel($periodo,'ano') ?>>Este ano</option>
                            <option value="custom" <?= sel($periodo,'custom') ?>>Personalizado</option>
                        </select>
                    </div>
                    <div class="group">
                        <label for="data_ini">Início</label>
                        <input type="date" name="data_ini" id="data_ini" value="<?= e($data_ini) ?>" <?= $periodo==='custom' ? '' : 'disabled' ?>>
                    </div>
                    <div class="group">
                        <label for="data_fim">Fim</label>
                        <input type="date" name="data_fim" id="data_fim" value="<?= e($data_fim) ?>" <?= $periodo==='custom' ? '' : 'disabled' ?>>
                    </div>
                    <div class="group">
                        <label for="status">Status</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="todos" <?= sel($statusFiltro,'todos') ?>>Todos</option>
                            <option value="concluida" <?= sel($statusFiltro,'concluida') ?>>Concluídas</option>
                            <option value="pendente" <?= sel($statusFiltro,'pendente') ?>>Pendentes</option>
                            <option value="cancelada" <?= sel($statusFiltro,'cancelada') ?>>Canceladas</option>
                        </select>
                    </div>
                    <div class="group" style="align-self:flex-end">
                        <button class="btn" type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    </div>
                </form>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Venda</th>
                                <th>Data</th>
                                <th>Lote</th>
                                <th>Comprador</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pagina)): ?>
                            <tr><td colspan="7" style="text-align:center; color:#777; padding:24px">Nenhuma venda encontrada para o período/critério selecionado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pagina as $row):
                                $badgeClass = $row['status_venda']==='Concluída' ? 'status-ok' : ($row['status_venda']==='Cancelada' ? 'status-cancel' : 'status-wait');
                            ?>
                            <tr>
                                <td>#VDA-<?= (int)$row['venda_item_id'] ?></td>
                                <td><?= dtbr($row['criado_em']) ?></td>
                                <td>
                                    Lote #<?= e($row['codigo_lote'] ?? ('ID '.$row['lote_id'])) ?>
                                    (<?= (int)$row['quantidade_cabecas'] ?> cabeças)
                                </td>
                                <td>
                                    <?= e($row['frigorifico_nome'] ?? ('Frigorífico #'.(int)$row['frigorifico_id'])) ?>
                                </td>
                                <td><?= brl($row['valor_total']) ?></td>
                                <td><span class="status-badge <?= $badgeClass ?>"><?= e($row['status_venda']) ?></span></td>
                                <td>
                                    <button class="btn"
                                        onclick="showDetails(
                                            '<?= (int)$row['venda_item_id'] ?>',
                                            '<?= e(addslashes($row['frigorifico_nome'] ?? ('Frigorífico #'.(int)$row['frigorifico_id']))) ?>',
                                            '<?= e(addslashes($row['status_venda'])) ?>',
                                            '<?= e(addslashes($row['codigo_lote'] ?? ('ID '.$row['lote_id']))) ?>',
                                            '<?= (int)$row['quantidade_cabecas'] ?>',
                                            '<?= e(addslashes($row['raca'] ?? '-')) ?>',
                                            '<?= number_format((float)($row['peso_medio_kg'] ?? 0), 2, '.', '') ?>',
                                            '<?= e(addslashes($row['tipo_alimentacao'] ?? '-')) ?>',
                                            '<?= brl($row['preco_unitario_cab']) ?>',
                                            '<?= brl($row['valor_total']) ?>',
                                            '<?= e(addslashes(preg_replace('/[\r\n]+/', '\\n', $row['historico_vacinacao'] ?? '-'))) ?>',
                                            '<?= e(addslashes($row['frigorifico_email'] ?? '-')) ?>',
                                            '<?= e(addslashes($row['frigorifico_cnpj'] ?? '-')) ?>',
                                            '<?= e(addslashes($row['frigorifico_telefone'] ?? '-')) ?>',
                                            '<?= e(addslashes(implode(', ', array_filter([$row['frigorifico_rua'], $row['frigorifico_numero'], $row['frigorifico_bairro']])))) ?>',
                                            '<?= e(addslashes(implode(' - ', array_filter([$row['frigorifico_cidade'], $row['frigorifico_estado']])))) ?>',
                                            '<?= e(addslashes($row['frigorifico_cep'] ?? '-')) ?>'
                                        )">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i=1; $i<=$totalPages; $i++):
                        // preserva filtros na paginação
                        $qs = $_GET; $qs['page'] = $i; $href = '?'.http_build_query($qs);
                    ?>
                        <a class="page <?= $i===$page ? 'active' : '' ?>" href="<?= e($href) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal()">&times;</button> <h3>Detalhes da Venda #<span id="modal-venda-id"></span></h3>

             <h4>Detalhes do Comprador</h4>
            <p><strong>Nome/Razão Social:</strong> <span id="modal-frigorifico-nome"></span></p>
            <p><strong>CNPJ:</strong> <span id="modal-frigorifico-cnpj"></span></p>
            <p><strong>E-mail:</strong> <span id="modal-frigorifico-email"></span></p>
            <p><strong>Telefone:</strong> <span id="modal-frigorifico-telefone"></span></p>
            <p><strong>Endereço:</strong> <span id="modal-frigorifico-endereco"></span></p>
            <hr>

            <p><strong>Status da Venda:</strong> <span id="modal-status-venda" class="status-badge"></span></p>
            <hr>

            <h4>Detalhes do Lote</h4>
            <p><strong>Código do Lote:</strong> <span id="modal-lote-codigo"></span></p>
            <p><strong>Quantidade (Cabeças):</strong> <span id="modal-quantidade"></span></p>
            <p><strong>Raça:</strong> <span id="modal-raca"></span></p>
            <p><strong>Peso Médio:</strong> <span id="modal-peso-medio"></span></p>
            <p><strong>Alimentação:</strong> <span id="modal-alimentacao"></span></p>
            <p><strong>Preço Unitário:</strong> <span id="modal-preco-unitario"></span></p>
            <p><strong>Valor Total da Venda:</strong> <span id="modal-valor-total"></span></p>

            <h4 style="margin-top: 1.5rem;">Histórico de Vacinação</h4>
            <p id="modal-vacinas-container"> <span id="modal-vacinas"></span>
            </p>

            <button class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;" onclick="closeModal()">Fechar</button>
        </div>
    </div>

    <script>
    // Funcionalidade do Modal
    const detailsModal = document.getElementById('detailsModal');

    // Função para abrir e preencher o modal
    // Adicionados novos parâmetros no final: email, cnpj, telefone, end1, end2, cep
    function showDetails(vendaId, frigorifico, statusVenda, loteCodigo, quantidade, raca, pesoMedio, alimentacao, precoUnitario, valorTotal, vacinas, email, cnpj, telefone, end1, end2, cep) {
        document.getElementById('modal-venda-id').textContent = vendaId;

        // Detalhes do Comprador
        document.getElementById('modal-frigorifico-nome').textContent = frigorifico || '-';
        document.getElementById('modal-frigorifico-cnpj').textContent = cnpj || '-';
        document.getElementById('modal-frigorifico-email').textContent = email || '-';
        document.getElementById('modal-frigorifico-telefone').textContent = telefone || '-';
        // Monta o endereço completo
        const enderecoCompleto = [end1, end2, cep].filter(Boolean).join(', ') || '-'; // Junta partes, mostra '-' se tudo for vazio
        document.getElementById('modal-frigorifico-endereco').textContent = enderecoCompleto;


        // Detalhes do Lote
        document.getElementById('modal-lote-codigo').textContent = loteCodigo || '-';
        document.getElementById('modal-quantidade').textContent = quantidade + ' cabeças';
        document.getElementById('modal-raca').textContent = raca || '-';
        document.getElementById('modal-peso-medio').textContent = parseFloat(pesoMedio).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' kg';
        document.getElementById('modal-alimentacao').textContent = alimentacao || '-';
        document.getElementById('modal-preco-unitario').textContent = precoUnitario || '-';
        document.getElementById('modal-valor-total').textContent = valorTotal || '-';

        // Define o texto e formatação para o Histórico de Vacinação
        const vacinasElement = document.getElementById('modal-vacinas');
        vacinasElement.textContent = vacinas.replace(/\\n/g, '\n') || '-'; // Processa quebras de linha

        // Define o status e sua cor (badge)
        const statusElement = document.getElementById('modal-status-venda');
        statusElement.textContent = statusVenda;
        statusElement.className = 'status-badge ' + (
            statusVenda === 'Concluída' ? 'status-ok' :
            (statusVenda === 'Cancelada' ? 'status-cancel' : 'status-wait')
        );

        detailsModal.style.display = 'block';
    }

    // Função para fechar o modal
    function closeModal() {
        detailsModal.style.display = 'none';
    }

    // Fechar o modal clicando fora
    window.onclick = function(event) {
        if (event.target == detailsModal) {
            closeModal();
        }
    }

    // Habilitar/Desabilitar datas personalizadas
    function toggleDateInputs(periodValue) {
        const custom = periodValue === 'custom';
        document.getElementById('data_ini').disabled = !custom;
        document.getElementById('data_fim').disabled = !custom;
        // Ajusta a opacidade dos grupos de data
        document.getElementById('data_ini').closest('.group').style.opacity = custom ? '1' : '0.5';
        document.getElementById('data_fim').closest('.group').style.opacity = custom ? '1' : '0.5';
    }
    // Chama a função ao carregar a página para definir o estado inicial correto
    document.addEventListener('DOMContentLoaded', () => {
         toggleDateInputs(document.getElementById('periodo').value);
    });

    </script>
</body>
</html>