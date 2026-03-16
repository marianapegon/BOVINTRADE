<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php'; // Conexão PDO

// Proteção: só transportadora
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php"); exit;
}

$u = $_SESSION['usuario'];
$transportadora_id = $u['id'];
$email = htmlspecialchars($u['email'] ?? ''); // Adicionado fallback ?? ''
$nome = htmlspecialchars($u['nome_razao'] ?? ''); // Adicionado fallback ?? ''

// Página atual para sidebar
$current_page = basename($_SERVER['PHP_SELF']); // Usa o nome do arquivo atual

$msg = $_GET['msg'] ?? ''; // Para mensagens de sucesso/erro vindas do redirecionamento

// --- Mapeamento para nomes amigáveis de pagamento ---
$metodos_pagamento_map = [
    'PIX' => 'PIX',
    'CARTAO' => 'Cartão',
    'BOLETO' => 'Boleto',
    'TRANSFERENCIA' => 'Transferência',
    'A_COMBINAR' => 'A Combinar',
    'PAGAMENTO_NA_ENTREGA' => 'Na Entrega',
    'PAGAMENTO_NA_COLETA' => 'Na Coleta',
    'FATURADO' => 'Faturado',
];
function formatarMetodoPagamento($metodo, $map) {
    return htmlspecialchars($map[$metodo] ?? $metodo ?? 'Não informado');
}
// --- Fim do mapeamento ---

// ---------- Processa atualização da Data Prevista de Entrega ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_previsao') {
    $transporte_id_update = (int)($_POST['transporte_id'] ?? 0);
    $data_prevista = $_POST['data_prevista_entrega'] ?? null;

    // Validação simples da data (pode ser mais robusta)
    if ($transporte_id_update > 0 && (!empty($data_prevista) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_prevista))) {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE transportes SET data_prevista_entrega = :data_prevista
                                         WHERE id = :tid AND transportadora_id = :transp_id
                                         AND status IN ('CONFIRMADO', 'AUTORIZADO')"); // Só atualiza se estiver nestes status
            $stmtUpdate->execute([
                ':data_prevista' => $data_prevista,
                ':tid' => $transporte_id_update,
                ':transp_id' => $transportadora_id
            ]);

            if ($stmtUpdate->rowCount() > 0) {
                // Redireciona com mensagem de sucesso
                header("Location: coletas-agendadas.php?msg=" . urlencode("✅ Data prevista de entrega salva para o transporte #{$transporte_id_update}!"));
                exit;
            } else {
                 $msg = "⚠ Nenhuma alteração feita. Verifique se a data é diferente ou se o transporte pertence a você e está no status correto.";
            }

        } catch (PDOException $e) {
            $msg = "❌ Erro ao salvar data prevista: " . htmlspecialchars($e->getMessage());
        }
    } elseif ($transporte_id_update > 0 && empty($data_prevista)) {
         // Permite limpar a data
         try {
            $stmtUpdate = $pdo->prepare("UPDATE transportes SET data_prevista_entrega = NULL
                                         WHERE id = :tid AND transportadora_id = :transp_id
                                         AND status IN ('CONFIRMADO', 'AUTORIZADO')");
            $stmtUpdate->execute([
                ':tid' => $transporte_id_update,
                ':transp_id' => $transportadora_id
            ]);
             if ($stmtUpdate->rowCount() > 0) {
                 header("Location: coletas-agendadas.php?msg=" . urlencode("✅ Data prevista de entrega removida para o transporte #{$transporte_id_update}!"));
                 exit;
             } else {
                 $msg = "⚠ Nenhuma alteração feita ao limpar a data.";
             }
         } catch (PDOException $e) {
              $msg = "❌ Erro ao limpar data prevista: " . htmlspecialchars($e->getMessage());
         }
    }
    else if ($transporte_id_update > 0) { // Se ID existe mas data é inválida (não vazia)
         $msg = "❌ Data inválida fornecida para o transporte #{$transporte_id_update}. Use o formato AAAA-MM-DD.";
    }
    else {
        $msg = "❌ Dados inválidos para salvar a data prevista.";
    }
}
// ---------- FIM: Processa atualização ----------


// ----------------------------------------------------
// CONSULTA: Buscar transportes CONFIRMADOS e AUTORIZADOS
// Adicionada a coluna data_prevista_entrega
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.pedido_id,
        t.data_retirada,
        t.hora_retirada,
        t.status,
        t.data_prevista_entrega, -- <<< CAMPO ADICIONADO AQUI
        f.nome_razao AS fazenda_nome,
        u.nome_razao AS frigorifico_nome,
        m.nome AS motorista_confirmado,
        v.placa AS veiculo_confirmado_placa
    FROM transportes t
    JOIN usuarios f ON f.id = t.fazenda_id
    JOIN usuarios u ON u.id = t.frigorifico_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    LEFT JOIN veiculo v ON v.id = t.veiculo_id

    WHERE t.transportadora_id = :tid
      AND t.status IN ('CONFIRMADO', 'AUTORIZADO') /* AGENDADOS AGUARDANDO INÍCIO */
    ORDER BY t.data_retirada ASC, t.hora_retirada ASC -- Ordena por hora também
");
$stmt->execute([':tid' => $transportadora_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$has_transportes = count($transportes) > 0; // Renomeado para clareza
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>BovinTrade - Coletas Agendadas</title>
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
      --success-bg: #d4edda;
      --success-border: #c3e6cb;
      --success-text: #155724;
      --error-bg: #f8d7da;
      --error-border: #f5c6cb;
      --error-text: #721c24;
      --warning-bg: #fff3cd;
      --warning-border: #ffeeba;
      --warning-text: #856404;
    }
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
     .user-menu form button { background: none; border: none; color: white; cursor: pointer; font-size: 1rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink: 0; }
    .sidebar-menu{ list-style:none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; overflow-x: hidden; } /* Evita scroll horizontal desnecessário */
    .welcome-card{ background:linear-gradient(135deg,rgba(163,0,0,0.9),rgba(122,0,0,0.9)); color:white; border-radius:12px; padding:2rem; margin-bottom:2.5rem; text-align: center;}
    .welcome-card h2 { color: white; margin-bottom: 0.5rem;}
    .welcome-card p { opacity: 0.9; }

    .card { background: var(--background); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); max-width: 100%; margin: auto; overflow-x: auto; } /* overflow-x para tabela */
    h2.card-title { color: var(--primary); text-align: center; margin-bottom: 1.5rem; font-weight: 600; }
    .table-wrapper { overflow-x: auto; } /* Container para forçar scroll horizontal apenas na tabela */
    table { width: 100%; border-collapse: collapse; table-layout: auto; min-width: 1000px; /* Garante largura mínima */ }
    th, td { padding: 0.75rem 1rem; border: 1px solid var(--border); text-align: left; vertical-align: middle; font-size: 0.9rem; }
    th { background: var(--primary); color: white; font-weight: 600; white-space: nowrap; }
    tr:nth-child(even) td { background-color: #f8f9fa; }
    .btn-acao { padding: 6px 10px; font-size: 0.85rem; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; font-weight: 600; white-space: nowrap; }
    .btn-next-step { background: #008a00; color: white; } /* Verde um pouco mais escuro */
    .btn-next-step:hover { background: #005a00; }
    .status-tag { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; text-transform: capitalize; }
    .status-CONFIRMADO { background: var(--warning-bg); color: var(--warning-text); border: 1px solid var(--warning-border); }
    .status-AUTORIZADO { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
    .msg-alerta { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; border: 1px solid transparent; }
    .msg-alerta.ok { background: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
    .msg-alerta.erro { background: var(--error-bg); color: var(--error-text); border-color: var(--error-border); } /* Adicionado estilo erro */
    .msg-alerta.aviso { background: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); } /* Adicionado estilo aviso */
    .form-previsao { display: flex; gap: 5px; align-items: center; }
    .form-previsao input[type="date"] { padding: 4px 6px; font-size: 0.85rem; border-radius: 4px; border: 1px solid var(--border); max-width: 140px; }
    .btn-save-date { background: var(--primary-dark); color: white; padding: 6px 8px; }
    .btn-save-date:hover { background: var(--primary); }
    .no-coletas { text-align: center; color: var(--text-light); padding: 2rem; font-size: 1.1rem; }

    @media (max-width: 768px) {
      .sidebar { width: 100%; border-right: none; box-shadow: none; padding: 1rem 0;}
      .container { flex-direction: column; }
      .main { padding: 1.5rem; }
      th, td { white-space: normal; } /* Permite quebra em mobile */
      .form-previsao { flex-direction: column; align-items: flex-start; } /* Ajusta form de data em mobile */
      .form-previsao input[type="date"] { max-width: none; width: 100%; }
      .btn-acao { font-size: 0.8rem; padding: 4px 8px;}
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
      <button type="submit">Sair</button>
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

    <?php if (!$has_transportes): ?>
         <div class="welcome-card">
             <h2>Coletas Agendadas</h2>
             <p>Nenhuma coleta confirmada ou autorizada aguardando início no momento.</p>
         </div>
    <?php endif; ?>


    <?php if ($msg): ?>
      <div class="msg-alerta <?= strpos($msg, '❌') !== false ? 'erro' : (strpos($msg, '⚠') !== false ? 'aviso' : 'ok') ?>">
           <?= htmlspecialchars(urldecode($msg)) ?>
      </div>
    <?php endif; ?>

    <div class="card">
       <h2 class="card-title">Agenda de Coletas Confirmadas</h2>

      <?php if(count($transportes) === 0 && !$msg): // Mostra só se não houver transportes E não houver mensagem de erro/aviso ?>
        <p class="no-coletas">Nenhuma coleta agendada ou aguardando autorização do Frigorífico no momento.</p>
      <?php elseif (count($transportes) > 0): ?>
         <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>ID Transp.</th>
                  <th>Pedido</th>
                  <th>Fazenda Origem</th>
                  <th>Frigorífico Destino</th>
                  <th>Motorista/Veículo</th>
                  <th>Retirada Agendada</th>
                  <th>Data Prev. Entrega</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($transportes as $t): ?>
                  <tr>
                    <td><?= $t['id'] ?></td>
                    <td>#<?= $t['pedido_id'] ?></td>
                    <td><?= htmlspecialchars($t['fazenda_nome']) ?></td>
                    <td><?= htmlspecialchars($t['frigorifico_nome']) ?></td>
                    <td><?= htmlspecialchars($t['motorista_confirmado'] ?? 'N/A') ?> / <?= htmlspecialchars($t['veiculo_confirmado_placa'] ?? 'N/A') ?></td>
                    <td><?= date('d/m/Y', strtotime($t['data_retirada'])) ?> às <?= substr($t['hora_retirada'], 0, 5) ?></td>
                    <td>
                        <form method="POST" class="form-previsao">
                             <input type="hidden" name="transporte_id" value="<?= $t['id'] ?>">
                             <input type="hidden" name="acao" value="salvar_previsao">
                             <input type="date" name="data_prevista_entrega"
                                    value="<?= htmlspecialchars($t['data_prevista_entrega'] ?? '') ?>"
                                    min="<?= date('Y-m-d') ?>"
                                    title="Data prevista de chegada no frigorífico">
                             <button type="submit" class="btn-acao btn-save-date" title="Salvar data prevista">
                                 <i class="fas fa-save"></i>
                             </button>
                        </form>
                    </td>
                    <td><span class="status-tag status-<?= $t['status'] ?>"><?= str_replace('_', ' ', $t['status']) ?></span></td>
                    <td>
                      <?php if ($t['status'] === 'CONFIRMADO'): ?>
                        <span class="btn-acao" style="background:var(--warning-bg); color:var(--warning-text); cursor:default; border: 1px solid var(--warning-border);">
                          <i class="fas fa-clock"></i> Aguard. Autorização
                        </span>
                      <?php elseif ($t['status'] === 'AUTORIZADO'): ?>
                        <a href="rastreamento-transporte-t.php?transporte_id=<?= $t['id'] ?>&acao=iniciar_coleta" class="btn-acao btn-next-step" title="Ir para a página de rastreamento e iniciar a coleta">
                          <i class="fas fa-arrow-right"></i> Iniciar Coleta
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
         </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>