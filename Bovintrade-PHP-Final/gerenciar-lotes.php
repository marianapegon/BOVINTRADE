<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once  'config.php';

// Proteção de rota: exige login e tipo FAZENDA
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
    if ($u['tipo_usuario'] === 'FRIGORIFICO')      { header('Location: 07-painel-frigorifico.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

$fazendaId = (int)($u['id'] ?? 0);
if ($fazendaId <= 0) { header('Location: login.php'); exit; } // Redirecionamento corrigido para evitar loop desnecessário

// =========================================================
// DEFINIÇÃO DE VARIÁVEIS DE CABEÇALHO E UTILS (CORRIGIDO)
// =========================================================
$nome  = htmlspecialchars($u['nome_razao'] ?? 'Fazenda', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($u['email']      ?? '',          ENT_QUOTES, 'UTF-8');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function moeda($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function kg($v){ return number_format((float)$v, 2, ',', '.') . ' kg'; }
function old($k){ return htmlspecialchars($_POST[$k] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } // Mantido para futuras edições de formulário

/* CSRF simples */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$flashSucesso = null;
$flashErro    = null;

/* Exclusão via POST (com CSRF) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['lote_id']) && $_POST['acao'] === 'excluir') {
    if (empty($_POST['_csrf']) || !hash_equals($csrf, (string)$_POST['_csrf'])) {
        $flashErro = "Token inválido. Recarregue a página.";
    } else {
        $loteId = (int)$_POST['lote_id'];
        try {
            // Só exclui se for da fazenda e estiver DISPONIVEL
            $del = $pdo->prepare("DELETE FROM lote_bois WHERE id = ? AND fazenda_id = ? AND status = 'DISPONIVEL'");
            $del->execute([$loteId, $fazendaId]);
            if ($del->rowCount() > 0) {
                $flashSucesso = "Lote #{$loteId} excluído com sucesso.";
            } else {
                $flashErro = "Não foi possível excluir. O lote pode não ser seu ou não está 'DISPONIVEL'.";
            }
        } catch (Throwable $e) {
            $flashErro = "Erro ao excluir lote: " . $e->getMessage();
        }
    }
}

/* Paginação */
$porPagina = 10;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$offset = ($pagina - 1) * $porPagina;

/* Filtros (opcionais) */
$filtroRaca   = trim($_GET['raca']   ?? '');
$filtroStatus = trim($_GET['status'] ?? '');
$busca        = trim($_GET['q']      ?? '');

$where = ["fazenda_id = :fid"];
$params = [":fid" => $fazendaId];

if ($filtroRaca !== '')   { $where[] = "raca = :raca"; $params[':raca'] = $filtroRaca; }
if ($filtroStatus !== '') { $where[] = "status = :status"; $params[':status'] = $filtroStatus; }
if ($busca !== '') {
    // busca em codigo_lote e descricao
    $where[] = "(codigo_lote LIKE :q OR descricao LIKE :q)";
    $params[':q'] = '%'.$busca.'%';
}

$sqlWhere = 'WHERE ' . implode(' AND ', $where);

/* Total para paginação */
$total = 0;
try {
    $stmtTot = $pdo->prepare("SELECT COUNT(*) FROM lote_bois $sqlWhere");
    $stmtTot->execute($params);
    $total = (int)$stmtTot->fetchColumn();
} catch (Throwable $e) {
    $flashErro = "Erro ao carregar lotes.";
}

/* Consulta dos registros da página (inclui preco_total) */
$lotes = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, codigo_lote, raca, quantidade, peso_medio_kg, tipo_alimentacao,
               historico_vacinacao, preco, preco_total, status, created_at
          FROM lote_bois
          $sqlWhere
        ORDER BY created_at DESC, id DESC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $flashErro = "Erro ao carregar lotes.";
}

$totalPaginas = max(1, (int)ceil($total / $porPagina));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>BovinTrade - Gerenciar Lotes</title>
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
        .btn-danger { background-color:#f44336; color:white;}
        .btn-danger:hover { background-color:#d32f2f; transform: translateY(-1px); box-shadow:0 4px 8px rgba(244,67,54,0.2);}
        .btn-sm { padding:0.5rem 1rem; font-size:0.85rem;}
        .btn-block { width:100%; justify-content:center; }
        .profile-container { background: var(--background); padding: 2rem; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border: 1px solid var(--border); margin-bottom: 2rem; }
        .filters { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: end; }
        .filters input, .filters select { padding: 0.75rem; border: 1px solid var(--border); border-radius: 6px; flex: 1; min-width: 150px; }
        .filters button { padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; display: inline-flex; align-items: center; gap: 0.5rem; background: var(--primary); color: white; }
        .filters button:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 8px rgba(163,0,0,0.2); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; background: var(--background); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        th, td { padding: 1rem; border: 1px solid var(--border); text-align: center; vertical-align: middle; }
        th { background-color: var(--primary); color: #fff; white-space: nowrap; font-weight: 600; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .btn-excluir, .btn-editar { background: transparent; border: none; font-weight: 600; cursor: pointer; padding: 0.5rem 1rem; border-radius: 4px; transition: all 0.2s; }
        .btn-excluir { color: #d32f2f; }
        .btn-excluir:hover { color: #b71c1c; background: #ffebee; text-decoration: underline; }
        .btn-editar { color: #0d6efd; }
        .btn-editar:hover { color: #084298; background: #e7f3ff; text-decoration: underline; }
        .muted { color: var(--text-light); }
        .alert { padding: 1rem; border-radius: 8px; margin: 0 0 1rem 0; }
        .alert-success { background: #e8f5e9; border: 1px solid #c8e6c9; color: #256029; }
        .alert-error { background: #ffebee; border: 1px solid #ffcdd2; color: #7a0000; }
        .pagination { display: flex; gap: 0.5rem; align-items: center; margin-top: 2rem; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: var(--text); transition: all 0.2s; }
        .pagination a:hover { background: rgba(163,0,0,0.05); color: var(--primary); border-color: var(--primary); }
        .pagination .active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Responsividade */
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
                padding: 1rem;
                width: 100%;
            }
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .dashboard-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 1rem;
            }
            .logo {
                font-size: 1.5rem;
            }
            .user-menu {
                gap: 0.5rem;
            }
            .main {
                padding: 0.5rem;
            }
        }
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
        <span><?= $email ?></span> <form action="logout.php" method="post" style="display:inline;">
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
    
    <div class="resizer"></div>
    <main class="main">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-edit"></i> Lotes Cadastrados</h1>
        </div>

        <?php if ($flashSucesso): ?>
            <div class="alert alert-success"><?= e($flashSucesso) ?></div>
        <?php endif; ?>
        <?php if ($flashErro): ?>
            <div class="alert alert-error"><?= e($flashErro) ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <form method="get" action="" style="display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; align-items: end;">
                <input type="text" name="q" value="<?= e($busca) ?>" placeholder="Buscar por código ou descrição..." style="flex: 1; min-width: 200px;" />
                <select name="raca" style="min-width: 150px;">
                    <option value="">Todas as raças</option>
                    <?php foreach (['Nelore','Angus','Brahman','Hereford'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= $filtroRaca===$r?'selected':''; ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" style="min-width: 150px;">
                    <option value="">Todos os status</option>
                    <?php foreach (['DISPONIVEL','EM_NEGOCIACAO','VENDIDO','INATIVO'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filtroStatus===$s?'selected':''; ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary" style="white-space: nowrap;"><i class="fas fa-search"></i> Filtrar</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Raça</th>
                        <th>Qtd</th>
                        <th>Peso Médio</th>
                        <th>Alimentação</th>
                        <th>Preço unit.</th>
                        <th>Total do lote</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$lotes): ?>
                    <tr><td colspan="9" class="muted" style="text-align: center;">Nenhum lote encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($lotes as $l): ?>
                        <tr>
                            <td><?= e($l['codigo_lote']) ?></td>
                            <td><?= e($l['raca']) ?></td>
                            <td><?= e($l['quantidade']) ?></td>
                            <td><?= e(kg($l['peso_medio_kg'])) ?></td>
                            <td><?= e($l['tipo_alimentacao']) ?></td>
                            <td><?= e(moeda($l['preco'])) ?></td>
                            <td><?= e(moeda($l['preco_total'])) ?></td>
                            <td><?= e($l['status']) ?></td>
                            <td>
                                <button class="btn-editar" onclick="location.href='editar-lote.php?id=<?= (int)$l['id'] ?>'" style="margin-right: 0.5rem;">Editar</button>
                                <?php if ($l['status'] === 'DISPONIVEL'): ?>
                                    <form method="post" action="" style="display: inline;" onsubmit="return confirm('Excluir este lote?');">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="lote_id" value="<?= (int)$l['id'] ?>">
                                        <button type="submit" class="btn-excluir">Excluir</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-excluir" disabled title="Só é possível excluir quando 'DISPONIVEL'">Excluir</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPaginas > 1): ?>
                <div class="pagination">
                    <?php
                        $qsBase = $_GET; unset($qsBase['p']);
                        $qs = function($p) use ($qsBase){ $qsBase['p']=$p; return '?'.http_build_query($qsBase); };
                    ?>
                    <?php if ($pagina > 1): ?>
                        <a href="<?= e($qs($pagina-1)) ?>">&laquo; Anterior</a>
                    <?php endif; ?>

                    <?php for ($p=1; $p <= $totalPaginas; $p++): ?>
                        <?php if ($p === $pagina): ?>
                            <span class="active"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= e($qs($p)) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="<?= e($qs($pagina+1)) ?>">Próxima &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
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
</script>
</body>
</html>