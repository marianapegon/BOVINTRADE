<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
// Define o fuso horário padrão para o Brasil (São Paulo)
date_default_timezone_set('America/Sao_Paulo');
require_once 'config.php'; // Conexão PDO
// Proteção: só transportadora
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php"); exit;
}
$u = $_SESSION['usuario'];
$transportadora_id = $u['id'];
$email = htmlspecialchars($u['email'] ?? '');
$nome = htmlspecialchars($u['nome_razao'] ?? 'Transportadora');
// CORREÇÃO: Inicializa $msg para evitar o Warning
$msg = $_GET['msg'] ?? '';
// Página atual para sidebar
$current_page = 'historico-transporte-t.php';
// --- Helpers ---
function dtval($ts){
    // Helper para formatar apenas a data (d/m/Y), retorna N/A se nulo/inválido
    if (!$ts || strtotime($ts) === false || substr($ts, 0, 10) === '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($ts));
}
function js_str($val) {
    $val = $val ?? '';
    return "'" . str_replace("'", "\\'", htmlspecialchars($val)) . "'";
}
// ----------------------------------------------------
// CONSULTA: Buscar transportes FINALIZADOS ou CANCELADOS
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.pedido_id,
        t.data_retirada,
        t.hora_retirada,
        t.distancia_km,
        t.status,
        t.status_aceite,
        t.data_prevista_entrega,
        t.data_entrega_real,
        t.hora_entrega_real,
        f.nome_razao AS fazenda_nome,
        u.nome_razao AS frigorifico_nome,
        -- CAMPO REAL: Concatenando dados de lote_bois (lb)
        CONCAT(
            'Bovinos de Corte - Raça ', lb.raca, ', ', lb.quantidade, ' cabeças'
        ) AS caracteristicas_lote
    FROM transportes t
    -- JUNÇÃO CORRETA: Ligando o transporte ao item do pedido que contém o lote_id
    JOIN pedido_itens pi ON pi.pedido_id = t.pedido_id
    JOIN lote_bois lb ON lb.id = pi.lote_id
    -- Junções de usuários (Fazenda e Frigorífico)
    JOIN usuarios f ON f.id = t.fazenda_id
    JOIN usuarios u ON u.id = t.frigorifico_id
    WHERE t.transportadora_id = :tid
      AND t.status IN ('ENTREGUE', 'CANCELADO')
    ORDER BY t.data_retirada DESC
");
$stmt->execute([':tid' => $transportadora_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>BovinTrade - Histórico de Transportes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #a30000; --primary-dark: #7a0000; --text: #333333;
            --text-light: #666666; --background: #ffffff; --border: #e0e0e0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; background: #f9f9f9; color: var(--text); }
        header { background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color: white; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .logo { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
        .logo i { font-size: 1.6rem; }
        .user-menu { display: flex; align-items: center; gap: 1.5rem; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; }
        .container { display: flex; min-height: calc(100vh - 76px); }
        .sidebar { width: 280px; background: var(--background); border-right: 1px solid var(--border); padding: 1.5rem 0; box-shadow: 2px 0 8px rgba(0,0,0,0.05); }
        .sidebar-menu { list-style: none; }
        .menu-item { padding: 0.8rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--text); text-decoration: none; font-weight: 500; border-left: 3px solid transparent; transition: 0.2s; }
        .menu-item i { width: 24px; text-align: center; color: var(--text-light); }
        .menu-item:hover { background-color: rgba(163,0,0,0.05); color: var(--primary); border-left: 3px solid var(--primary); }
        .menu-item.active { background-color: rgba(163,0,0,0.1); color: var(--primary); border-left: 3px solid var(--primary); }
        .main { flex: 1; padding: 2.5rem; }
        .card { background: var(--background); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); max-width: 1200px; margin: auto; overflow-x: auto; }
        h2 { color: var(--primary); text-align: center; margin-bottom: 1.5rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th, td { padding: 0.75rem; border: 1px solid var(--border); text-align: left; }
        th { background: var(--primary); color: white; font-weight: 600; }
        .status-tag { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; text-transform: capitalize; }
        .status-ENTREGUE { background: #d4edda; color: #155724; }
        .status-CANCELADO { background: #f8d7da; color: #721c24; }
        .status-tag.small { font-size: 0.75rem; padding: 3px 6px; }
        .msg-alerta.ok { background-color: #d4edda; border-color: #c3e6cb; color: #155724; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
        .msg-alerta.erro { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
       
        /* Estilos do Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
            padding-top: 60px;
        }
        .modal-content {
            background-color: var(--background);
            margin: 5% auto;
            padding: 30px;
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            color: var(--primary);
            margin-top: 0;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.5rem;
            font-weight: 600;
        }
        .modal-content p {
            margin-bottom: 0.8rem;
            font-size: 1rem;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .modal-content strong {
            font-weight: 600;
            color: var(--primary-dark);
            display: inline-block;
            min-width: 150px; /* Ajuste para alinhamento */
            margin-right: 10px;
        }
       
        .btn-block {
            display: block;
            width: 100%;
            padding: 10px;
            text-align: center;
            margin-top: 15px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-block:hover {
            background-color: var(--primary-dark);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            line-height: 20px;
            transition: color 0.2s;
        }
        .close:hover,
        .close:focus {
            color: var(--primary);
            text-decoration: none;
            cursor: pointer;
        }
       
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background-color: var(--primary);
            color: white;
        }
        .btn-sm:hover {
            background-color: var(--primary-dark);
        }
        .data-info { display: block; font-size: 0.85em; color: var(--text-light); margin-top: 2px; }
       
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .card { padding: 1rem; }
            table { min-width: 700px; }
            th, td { padding: 0.5rem; font-size: 0.85rem; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        🐄
        <span>BovinTrade • Transportadora</span>
    </div>
    <div class="user-menu">
        <span><?= $email ?></span>
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
        <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
      </a>
    </ul>
  </aside>
    <main class="main">
        <div class="card">
            <h2>Histórico de Transportes Finalizados</h2>
            <?php if ($msg): ?>
                <div class="msg-alerta <?= strpos($msg, '✅') !== false ? 'ok' : 'erro' ?>"><?= htmlspecialchars(urldecode($msg)) ?></div>
            <?php endif; ?>
            <?php if(count($transportes) === 0): ?>
                <p>Nenhum transporte finalizado ou cancelado no histórico.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Transp.</th>
                            <th>Pedido</th>
                            <th>Fazenda Origem</th>
                            <th>Frigorífico Destino</th>
                            <th>Data Retirada</th>
                            <th>Prev. Entrega</th>
                            <th>Entrega Real</th>
                            <th>Status Final</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transportes as $t):
                             $status = str_replace('_', ' ', $t['status']);
                             $statusClass = 'status-' . str_replace('_', '', $t['status']);
                            
                             $dataRetirada = dtval($t['data_retirada']);
                             $horaRetirada = substr($t['hora_retirada'], 0, 5);
                             $dataPrevista = dtval($t['data_prevista_entrega']);
                            
                             $dataReal = dtval($t['data_entrega_real']);
                             $horaReal = substr($t['hora_entrega_real'] ?? 'N/A', 0, 5);
                        ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><?= $t['pedido_id'] ?></td>
                                <td><?= htmlspecialchars($t['fazenda_nome']) ?></td>
                                <td><?= htmlspecialchars($t['frigorifico_nome']) ?></td>
                                <td>
                                    <?= $dataRetirada ?>
                                    <span class="data-info">às <?= $horaRetirada ?></span>
                                </td>
                                <td><?= $dataPrevista ?></td>
                                <td>
                                    <?= $dataReal ?>
                                    <?php if ($horaReal !== 'N/A'): ?>
                                        <span class="data-info">às <?= $horaReal ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-tag <?= $statusClass ?>"><?= $status ?></span></td>
                                <td>
                                    <button class="btn-sm" onclick="showTransportDetails(
                                         <?= $t['id'] ?>,
                                         <?= $t['pedido_id'] ?>,
                                         <?= js_str($t['fazenda_nome']) ?>,
                                         <?= js_str($t['frigorifico_nome']) ?>,
                                         <?= js_str($t['caracteristicas_lote'] ?? 'Informação não disponível') ?>,
                                         <?= js_str($t['distancia_km'] ?? 'N/A') ?>,
                                         <?= js_str($dataRetirada) ?>,
                                         <?= js_str($horaRetirada) ?>,
                                         <?= js_str($dataPrevista) ?>,
                                         <?= js_str($dataReal) ?>,
                                         <?= js_str($horaReal) ?>,
                                         <?= js_str($status) ?>
                                     )">
                                        <i class="fas fa-info-circle"></i> Ver
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Detalhes do Transporte #<span id="modal-transp-id"></span></h3>
        <p><strong>Pedido ID:</strong> <span id="modal-pedido-id"></span></p>
        <p><strong>Origem (Fazenda):</strong> <span id="modal-origem"></span></p>
        <p><strong>Destino (Frigorífico):</strong> <span id="modal-destino"></span></p>
        <p><strong>Data/Hora Retirada:</strong> <span id="modal-data-retirada"></span></p>
        <p><strong>Características do Lote:</strong> <span id="modal-caracteristicas"></span></p>
        <p><strong>KM Percorridos:</strong> <span id="modal-km"></span></p>
        <p><strong>Frete Estimado (R$ 5,50/km):</strong> <span id="modal-frete"></span></p>
        <p><strong>Data Prevista:</strong> <span id="modal-data-prevista"></span></p>
        <p><strong>Data/Hora Entrega Real:</strong> <span id="modal-data-real"></span></p>
        <p><strong>Status Final:</strong> <span id="modal-status"></span></p>
        <button class="btn-block" onclick="closeModal()">Fechar</button>
    </div>
</div>
<script>
// Funcionalidade do Modal
const detailsModal = document.getElementById('detailsModal');
// JAVASCRIPT CORRIGIDO:
// A função agora aceita 12 parâmetros, incluindo a nova hora real.
function showTransportDetails(
    id,
    pedido_id,
    origem,
    destino,
    caracteristicas,
    km,
    data_retirada,
    hora_retirada,
    data_prevista,
    data_real,
    hora_real,
    status
) {
    // Monta a string de Data/Hora Retirada
    const retiradaCompleta = `${data_retirada} às ${hora_retirada}`;
   
    // Monta a string de Data/Hora Entrega Real
    let entregaCompleta = data_real;
    if (hora_real && hora_real !== 'N/A' && hora_real.trim() !== '') {
        entregaCompleta += ` às ${hora_real}`;
    }
    // Preenche os campos de texto
    document.getElementById('modal-transp-id').textContent = id;
    document.getElementById('modal-pedido-id').textContent = pedido_id;
    document.getElementById('modal-origem').textContent = origem;
    document.getElementById('modal-destino').textContent = destino;
    document.getElementById('modal-caracteristicas').textContent = caracteristicas;
    document.getElementById('modal-data-retirada').textContent = retiradaCompleta;
    document.getElementById('modal-data-prevista').textContent = data_prevista;
    document.getElementById('modal-data-real').textContent = entregaCompleta;
    document.getElementById('modal-status').textContent = status;
   
    // Preenche KM
    document.getElementById('modal-km').textContent = km + (km !== 'N/A' ? ' km' : '');
   
    // --- CÁLCULO DO FRETE (R$ 5,50/km) ---
    const distancia = parseFloat(km);
    let valorFreteFormatado = 'N/A';
    if (!isNaN(distancia) && distancia > 0) {
        const freteCalculado = distancia * 5.50;
        valorFreteFormatado = freteCalculado.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }
    document.getElementById('modal-frete').textContent = valorFreteFormatado;
    // --- FIM DO CÁLCULO ---
   
    detailsModal.style.display = 'block'; // O ponto crucial para abrir o modal
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
</script>
</body>
</html>