<?php
$current_page = basename($_SERVER['PHP_SELF']); // Define a página atual
session_start();

// --- CORREÇÃO CRÍTICA DE FUSO HORÁRIO ---
// Define o fuso horário padrão para o Brasil (São Paulo) para todas as operações de data/hora.
date_default_timezone_set('America/Sao_Paulo'); 
// ------------------------------------------

require_once 'config.php'; // Conexão PDO

// Variável de controle de sessão para a proteção de rota
$u = $_SESSION['usuario'] ?? null;

// --- Proteção de rota ---
if (empty($u) || ($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if (($u['tipo_usuario'] ?? '') === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    elseif (($u['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php?expired=1'); exit; // Adicionado expired para clareza
}

$frigorifico_id = (int)$u['id'];
$nome = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');
$msg = $_GET['msg'] ?? ''; // Para mensagens de feedback via GET

// ----------------------------------------------------
// AÇÃO POST: Confirmar Recebimento (CHEGOU_NO_FRIGORIFICO -> ENTREGUE)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transporte_id'], $_POST['acao']) && $_POST['acao'] === 'confirmar_recebimento') {
    $transporte_id = (int)$_POST['transporte_id'];
    $data_entrega_real = $_POST['data_entrega_real'] ?? null;
    $hora_entrega_real = $_POST['hora_entrega_real'] ?? null;
    
    // Concatena data e hora para formar um DATETIME completo para validação
    $datetime_entrega = trim("{$data_entrega_real} {$hora_entrega_real}");
    
    // Validação básica da data e hora
    if (empty($data_entrega_real) || empty($hora_entrega_real) || !preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $datetime_entrega)) {
        $msg = "❌ Erro: Por favor, informe a data e a hora do recebimento no formato correto.";
    } else {
        try {
            // Verifica se a data/hora não é futura (Permite até o momento atual)
            // time() agora retorna o timestamp correto para America/Sao_Paulo
            if (strtotime($datetime_entrega) > time() + 60) { // +60 segundos de tolerância
                 throw new Exception("A data e hora de recebimento não podem ser futuras.");
            }
            
            // Variáveis para salvar no banco (assumindo colunas separadas: DATE e TIME)
            $data_somente = $data_entrega_real;
            $hora_somente = $hora_entrega_real;

            // Atualiza status, a data de entrega real E A HORA DE ENTREGA REAL
            $stmt = $pdo->prepare("UPDATE transportes SET
                status = 'ENTREGUE',
                data_entrega_real = :data_somente,
                hora_entrega_real = :hora_somente,
                atualizado_em = NOW()
                WHERE id = :tid AND frigorifico_id = :fid AND status = 'CHEGOU_NO_FRIGORIFICO'");
            $updateSuccess = $stmt->execute([
                ':tid' => $transporte_id,
                ':fid' => $frigorifico_id,
                ':data_somente' => $data_somente, 
                ':hora_somente' => $hora_somente
            ]);

            if ($updateSuccess && $stmt->rowCount() > 0) {
                 $msg = "✅ Lote recebido em ".date('d/m/Y', strtotime($data_somente))." às ".date('H:i', strtotime($hora_somente))." e transporte finalizado com sucesso!";
                
                 // Redireciona APENAS em caso de SUCESSO
                 header('Location: 09-recebimento-lotes.php?msg=' . urlencode($msg));
                 exit;
            } else if ($updateSuccess && $stmt->rowCount() === 0) {
                $msg = "⚠️ Nenhuma alteração realizada. O transporte pode já ter sido confirmado ou não está no status correto ('CHEGOU_NO_FRIGORIFICO').";
            } else {
                 throw new Exception("Falha ao executar a atualização no banco de dados.");
            }

        } catch (Throwable $e) {
            $msg = "❌ Erro ao processar a ação: " . htmlspecialchars($e->getMessage());
        }
    }
} // Fim do POST


// ----------------------------------------------------
// CONSULTA: Buscar transportes ATIVOS e AGUARDANDO RECEBIMENTO
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.id, t.pedido_id, t.data_retirada, t.hora_retirada, t.distancia_km, t.status,
        tr.nome_razao AS transportadora_nome, f.nome_razao AS fazenda_nome,
        m.nome AS motorista_confirmado, v.placa AS veiculo_confirmado_placa
    FROM transportes t
    JOIN usuarios tr ON tr.id = t.transportadora_id
    JOIN usuarios f ON f.id = t.fazenda_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    LEFT JOIN veiculo v ON v.id = t.veiculo_id
    WHERE t.frigorifico_id = :fid
      AND t.status IN ('AUTORIZADO', 'EM_TRANSITO_ORIGEM', 'CHEGOU_NA_FAZENDA', 'EM_TRANSITO_DESTINO', 'CHEGOU_NO_FRIGORIFICO')
    ORDER BY FIELD(t.status, 'CHEGOU_NO_FRIGORIFICO', 'EM_TRANSITO_DESTINO', 'CHEGOU_NA_FAZENDA', 'EM_TRANSITO_ORIGEM', 'AUTORIZADO'), t.data_retirada ASC, t.hora_retirada ASC
");
$stmt->execute([':fid' => $frigorifico_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>BovinTrade - Recebimento de Lotes</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary: #a30000; --primary-dark: #7a0000; --text: #333333;
        --text-light: #666666; --background: #ffffff; --border: #e0e0e0;
        --success-bg: #d4edda; --success-border: #c3e6cb; --success-text: #155724;
        --error-bg: #f8d7da; --error-border: #f5c6cb; --error-text: #721c24;
        --warning-bg: #fff3cd; --warning-border: #ffeeba; --warning-text: #856404;
        --info-bg: #cce5ff; --info-border: #b8daff; --info-text: #004085;
    }
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); overflow-x: hidden; }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1001;}
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; margin-left: 1rem;}
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu form button { background: none; border: none; color: white; cursor: pointer; font-size: 1rem;}
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; height: calc(100vh - 76px); position: sticky; top: 76px; overflow-y: auto;}
    .sidebar-menu{ list-style:none; padding-bottom: 2rem;}
    .sidebar-menu li { list-style: none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; min-width:0; }
    .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;}
    .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
    .btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem;}
    .btn-success { background-color: #0a6b2b; color:white; border:1px solid #0a6b2b;}
    .btn-success:hover { background-color: #074d1e; }
    .btn-sm { padding:0.5rem 1rem; font-size:0.85rem;}
    .card { background: var(--background); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); max-width: 100%; margin: auto; overflow-x: auto; }
    h2.card-title { color: var(--primary); text-align: center; margin-bottom: 1.5rem; font-weight: 600;}
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; table-layout: auto; min-width: 900px; }
    th, td { padding: 0.75rem 1rem; border: 1px solid var(--border); text-align: left; vertical-align: middle; font-size: 0.9rem;}
    th { background: var(--primary); color: white; font-weight: 600; white-space: nowrap; }
    tr:nth-child(even) td { background-color: #f8f9fa; }
    .status-tag { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; text-transform: capitalize; border: 1px solid transparent; white-space: nowrap;}
    .status-AUTORIZADO { background: var(--success-bg); color: var(--success-text); border-color: var(--success-border); } /* Verde: Pronto p/ Início */
    .status-EMTRANSITOORIGEM, .status-EMTRANSITODESTINO { background: var(--info-bg); color: var(--info-text); border-color: var(--info-border);} /* Azul: Em andamento */
    .status-CHEGOUNAFAZENDA { background: #e2f0d9; color: #385723; border-color: #c5e0b4; } /* Verde mais claro */
    .status-CHEGOUNOFRIGORIFICO { background: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border);} /* Amarelo/Laranja: Pendente Recebimento */
    .msg-alerta { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; border: 1px solid transparent; }
    .msg-alerta.ok { background: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
    .msg-alerta.erro { background: var(--error-bg); color: var(--error-text); border-color: var(--error-border); }
    .no-transportes { text-align: center; color: var(--text-light); padding: 2rem; font-size: 1.1rem; }
    .form-recebimento { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .form-recebimento label { font-weight: 500; font-size: 0.85rem; margin-right: 5px; white-space: nowrap;}
    .form-recebimento input[type="date"],
    .form-recebimento input[type="time"] { 
        padding: 5px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; 
        max-width: 120px; font-family: inherit;
    }
    .form-recebimento button { font-size: 0.85rem; padding: 6px 10px; flex-shrink: 0; } /* Evita que o botão quebre linha facilmente */
    
    @media (max-width: 992px) {
        .sidebar { display: none; } .sidebar.active { display: block; width: 250px; position: fixed; left: 0; top: 76px; height: calc(100vh - 76px); z-index: 1000;}
        .hamburger { display: block; } .container { flex-direction: row; } .main { width: 100%; }
    }
    @media (max-width: 768px) {
        .container { flex-direction: column; }
        .sidebar { width: 100%; position: fixed; top: 76px; left:0; transform: translateX(-100%); height: calc(100vh - 76px); z-index: 1000; overflow-y: auto; box-shadow: none; border-right: none;}
        .sidebar.active { transform: translateX(0); }
        .main { padding: 1rem; }
        th, td { white-space: normal; }
        .dashboard-title { font-size: 1.5rem; }
        .form-recebimento { flex-direction: column; align-items: flex-start; }
        /* Ajuste para data e hora no mobile */
        .form-recebimento input[type="date"],
        .form-recebimento input[type="time"] { max-width: none; width: 100%; }
        .form-recebimento button { width: 100%; justify-content: center; } /* Botão ocupa largura total */
    }
    @media (max-width: 480px) {
        header { padding: 1rem; } .logo { font-size: 1.5rem; }
        .user-menu span { display: none; } .main { padding: 0.5rem; }
        .card { padding: 1rem; } .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem;}
        th, td { padding: 0.5rem; font-size: 0.85rem;}
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
    <span><?php echo $email; ?></span>
    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit">Sair</button>
    </form>
    <div class="user-avatar"><i class="fas fa-user"></i></div>
  </div>
</header>

<div class="container">
  <aside class="sidebar" id="sidebar">
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
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-clipboard-check"></i> Monitoramento e Recebimento de Lotes</h1>
    </div>

    <div class="card">
       <h2 class="card-title">Status de Transportes Ativos</h2>

        <?php if ($msg): ?>
          <div class="msg-alerta <?= strpos($msg, '✅') !== false ? 'ok' : (strpos($msg, '⚠️') !== false ? 'aviso' : 'erro') ?>">
                <?= htmlspecialchars(urldecode($msg)) ?>
          </div>
        <?php endif; ?>

        <?php if(count($transportes) === 0): ?>
          <p class="no-transportes">Nenhum transporte em trânsito ou aguardando recebimento no momento.</p>
        <?php else: ?>
           <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Pedido</th>
                    <th>Fazenda Origem</th>
                    <th>Transportadora</th>
                    <th>Motorista/Veículo</th>
                    <th>Previsão Retirada</th>
                    <th>Status Atual</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($transportes as $t):
                        $statusLimpo = str_replace('_', ' ', $t['status']);
                        $statusClasse = str_replace('_', '', $t['status']); // Para classe CSS sem _
                  ?>
                    <tr>
                      <td>#<?= $t['pedido_id'] ?></td>
                      <td><?= htmlspecialchars($t['fazenda_nome']) ?></td>
                      <td><?= htmlspecialchars($t['transportadora_nome']) ?></td>
                      <td><?= htmlspecialchars($t['motorista_confirmado'] ?? '---') ?> / <?= htmlspecialchars($t['veiculo_confirmado_placa'] ?? '---') ?></td>
                      <td><?= date('d/m/Y', strtotime($t['data_retirada'])) ?> às <?= substr($t['hora_retirada'], 0, 5) ?></td>
                      <td><span class="status-tag status-<?= $statusClasse ?>"><?= $statusLimpo ?></span></td>
                      <td>
                        <?php if ($t['status'] === 'CHEGOU_NO_FRIGORIFICO'): ?>
                          <form method="post" class="form-recebimento" onsubmit="return confirm('Confirma que o lote foi entregue e recebido nesta data e hora?\nEsta ação finaliza o transporte.');">
                              <input type="hidden" name="transporte_id" value="<?= $t['id'] ?>">
                              <input type="hidden" name="acao" value="confirmar_recebimento">
                              <label for="data_entrega_<?= $t['id'] ?>">Data/Hora:</label>
                              
                              <input type="date" 
                                     name="data_entrega_real" 
                                     id="data_entrega_<?= $t['id'] ?>" 
                                     required 
                                     max="<?= date('Y-m-d') ?>" 
                                     value="<?= date('Y-m-d') ?>"> 
                              
                              <input type="time" 
                                     name="hora_entrega_real" 
                                     id="hora_entrega_<?= $t['id'] ?>_hora" 
                                     required 
                                     value="<?= date('H:i') ?>"> <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-box-open"></i> Confirmar</button>
                          </form>
                        <?php else: ?>
                          <span style="color:var(--text-light); font-style: italic; white-space: nowrap;">Aguardando chegada...</span>
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

<script>
  function toggleSidebar() { 
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
      sidebar.classList.toggle('active'); 
    }
  }
  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger');
    if (sidebar && sidebar.classList.contains('active') && hamburger && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
        sidebar.classList.remove('active');
    }
  });
</script>

</body>
</html>