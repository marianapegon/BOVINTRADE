<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php'; // Ajuste o caminho se necessário

// --- Autenticação e Helpers ---
if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'TRANSPORTADORA') {
    if ($u['tipo_usuario'] === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
    header('Location: login.php');
    exit;
}
$nome_transportadora = htmlspecialchars($u['nome_razao'] ?? 'Transportadora');
$email = htmlspecialchars($u['email'] ?? '');
$transportadora_id = $u['id'];


// --- LÓGICA DE EXCLUSÃO (DESATIVAÇÃO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir') {
    $motorista_id = (int)($_POST['motorista_id'] ?? 0);
    
    // 1. Verifica se o motorista está em um transporte ativo
    $stmt_check_active = $pdo->prepare("
        SELECT 1 FROM transportes 
        WHERE motorista_id = ? 
        AND transportadora_id = ?
        AND status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO')
        LIMIT 1
    ");
    $stmt_check_active->execute([$motorista_id, $transportadora_id]);
    
    if ($stmt_check_active->fetch()) {
        $_SESSION['error_message'] = 'Não é possível excluir. Este motorista está atualmente em um transporte ativo.';
    } else {
        // 2. Procede com a desativação
        try {
            $pdo->beginTransaction();
            
            // Marca o motorista como inativo
            $stmt_motorista = $pdo->prepare("UPDATE motorista SET ativo = 0 WHERE id = ?");
            $stmt_motorista->execute([$motorista_id]);
            
            // Define a data de fim na tabela de vínculo
            $stmt_link = $pdo->prepare("
                UPDATE transportadora_motorista 
                SET data_fim = CURDATE() 
                WHERE motorista_id = ? AND transportadora_usuario_id = ?
            ");
            $stmt_link->execute([$motorista_id, $transportadora_id]);
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Motorista desativado e removido da sua frota com sucesso!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Erro ao excluir motorista: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao desativar o motorista. Tente novamente.';
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


// --- PROCESSAR EDIÇÃO (MESMO ARQUIVO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_motorista'])) {
    $id = $_POST['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        $_SESSION['error_message'] = 'ID do motorista inválido.';
    } else {
        // Verifica se o motorista pertence à transportadora
        $stmt = $pdo->prepare("
            SELECT m.id FROM motorista m
            JOIN transportadora_motorista tm ON m.id = tm.motorista_id
            WHERE m.id = :id AND tm.transportadora_usuario_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tid' => $transportadora_id]);
        if (!$stmt->fetch()) {
            $_SESSION['error_message'] = 'Motorista não encontrado ou sem permissão.';
        } else {
            $campos = ['nome', 'cpf', 'telefone', 'email', 'cnh_numero', 'cnh_categoria', 'cnh_uf', 'cnh_validade'];
            $valores = [];
            foreach ($campos as $c) {
                $valores[$c] = $_POST[$c] ?? null;
            }

            $valores['nome'] = trim($valores['nome'] ?? '');
            $valores['cpf'] = preg_replace('/\D/', '', $valores['cpf'] ?? '');
            $valores['telefone'] = preg_replace('/\D/', '', $valores['telefone'] ?? '');
            $valores['cnh_validade'] = empty($valores['cnh_validade']) ? null : $valores['cnh_validade'];


            if (empty($valores['nome'])) {
                $_SESSION['error_message'] = 'Nome é obrigatório.';
            } else {
                $sql = "UPDATE motorista SET 
                        nome = :nome, cpf = :cpf, telefone = :telefone, email = :email,
                        cnh_numero = :cnh_numero, cnh_categoria = :cnh_categoria,
                        cnh_uf = :cnh_uf, cnh_validade = :cnh_validade
                        WHERE id = :id";
                $valores['id'] = $id;
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($valores);
                    $_SESSION['success_message'] = 'Motorista atualizado com sucesso!';
                } catch (Exception $e) {
                    error_log('Erro ao atualizar motorista: ' . $e->getMessage());
                    $_SESSION['error_message'] = 'Erro ao salvar. Tente novamente.';
                }
            }
        }
    }
    // Recarrega a página para evitar reenvio
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Helpers ---
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function dtval($ts) {
    if (!$ts || strtotime($ts) === false || substr($ts, 0, 10) === '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($ts));
}

// --- Busca de Dados ---

// *** CORREÇÃO AQUI ***
// Buscar motoristas vinculados à transportadora, e não apenas os que estão em transportes
$stmt_motoristas = $pdo->prepare("
    SELECT DISTINCT m.* FROM motorista m
    JOIN transportadora_motorista tm ON m.id = tm.motorista_id
    WHERE tm.transportadora_usuario_id = :tid 
      AND m.ativo = 1
      AND tm.data_fim IS NULL
    ORDER BY m.nome
");
$stmt_motoristas->execute([':tid' => $transportadora_id]);
$motoristas = $stmt_motoristas->fetchAll(PDO::FETCH_ASSOC);

// Buscar motoristas em transportes ATIVOS (para status "Em uso")
$stmt_ativos = $pdo->prepare("
    SELECT DISTINCT motorista_id FROM transportes
    WHERE transportadora_id = :tid 
    AND status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO') 
    AND motorista_id IS NOT NULL
");
$stmt_ativos->execute([':tid' => $transportadora_id]);
$active_driver_ids = $stmt_ativos->fetchAll(PDO::FETCH_COLUMN, 0);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Gerenciamento de Motorista</title>
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
      --warning: #ff9800;
      --info: #2196f3;
      --danger: #f44336;
      --danger-dark: #b00020;
      --bg-light: #f9f9f9;
    }
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
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
    .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap: wrap;}
    .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
    .dashboard-actions { display:flex; gap:1rem;}
    .btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem;}
    .btn-primary { background-color: var(--primary); color:white;}
    .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
    .btn-outline { background-color:transparent; color:var(--primary); border:1px solid var(--primary);}
    .btn-outline:hover { background-color: rgba(163,0,0,0.05);}
    .btn-danger{ background-color:var(--danger); color:white; }
    .btn-danger:hover{ background-color:var(--danger-dark); }
    .btn-sm{ padding:0.4rem 0.8rem; font-size:0.8rem; }
    
    .driver-overview{ display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.2rem; margin-bottom:1.5rem; }
    .driver-card{ background-color:var(--background); border-radius:8px; overflow:hidden; box-shadow:0 3px 10px rgba(0,0,0,0.05); transition:transform 0.2s,box-shadow 0.2s; }
    .driver-card:hover{ transform:translateY(-3px); box-shadow:0 5px 12px rgba(0,0,0,0.1); }
    .driver-header{ height:100px; background-color:var(--bg-light); display:flex; align-items:center; justify-content:center; color:var(--text-light); position:relative; }
    .driver-status{ position:absolute; top:0.8rem; right:0.8rem; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:600; color:white; }
    .status-available{ background-color:var(--success); }
    .status-in-use{ background-color:var(--info); }
    .status-inactive{ background-color:var(--text-light); }
    .driver-details{ padding:1.2rem; }
    .driver-title{ font-size:1.1rem; font-weight:600; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center; }
    .driver-cnh-cat{ background-color:rgba(163,0,0,0.1); color:var(--primary); padding:0.2rem 0.6rem; border-radius:4px; font-weight:700; font-size:0.8rem; }
    .driver-specs{ display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem; }
    .driver-spec{ font-size:0.9rem; color:var(--text-light); }
    .driver-spec strong{ color:var(--text); font-weight:500; }
    .driver-actions{ display:flex; gap:0.5rem; margin-top:1rem; flex-wrap: wrap; }
    .tabs{ display:flex; border-bottom:1px solid var(--border); margin-bottom:1.5rem; }
    .tab{ padding:0.6rem 1.2rem; cursor:pointer; font-weight:500; font-size:0.9rem; color:var(--text-light); border-bottom:3px solid transparent; transition:all 0.2s; }
    .tab:hover{ color:var(--primary); }
    .tab.active{ color:var(--primary); border-bottom:3px solid var(--primary); }
    .tab-content{ display:none; }
    .tab-content.active{ display:block; }
    .documents-list{ display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1.2rem; }
    .document-card{ background-color:var(--background); border-radius:8px; padding:1.2rem; box-shadow:0 3px 10px rgba(0,0,0,0.05); }
    .document-title{ font-weight:600; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center; }
    .document-expiry{ font-size:0.9rem; color:var(--text-light); margin-bottom:0.8rem; }
    .document-expiry strong{ color:var(--text); font-weight:500; }
    .document-status{ display:inline-block; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:600; text-transform:uppercase; }
    .status-valid{ background-color:rgba(76,175,80,0.1); color:var(--success); }
    .status-expired{ background-color:rgba(244,67,54,0.1); color:var(--danger); }
    .status-warning{ background-color:rgba(255,152,0,0.1); color:var(--warning); }
    .status-na{ background-color:#eee; color:var(--text-light); }
    .document-actions{ margin-top:1rem; display:flex; gap:0.5rem; }
    .empty-state{ text-align:center; padding:3rem; background:var(--background); border-radius:8px; box-shadow:0 3px 10px rgba(0,0,0,0.05); color:var(--text-light); }
    .empty-state i{ font-size:2rem; margin-bottom:1rem; }
    .alert{ padding:1rem; border-radius:5px; margin-bottom:1rem; }
    .alert-success{ background:#d4edda; color:#155724; }
    .alert-error{ background:#f8d7da; color:#721c24; }
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
      .driver-overview,.documents-list{ grid-template-columns:1fr; }
      .dashboard-header{ flex-direction:column; align-items:flex-start; gap:1rem; }
    }
    @media (max-width:480px){ header{ padding:1rem; } .logo{ font-size:1.5rem; } .user-menu span{ display:none; } .main{ padding:0.8rem; } .driver-actions,.document-actions{ flex-direction:column; } }
    .modal{ display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
    .modal-content{ background-color:var(--background); margin:5% auto; padding:0; border-radius:8px; width:90%; max-width:600px; max-height:80vh; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3); }
    .modal-header{ padding:1rem; background-color:var(--primary); color:white; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; }
    .modal-header-danger { background-color: var(--danger); }
    .close{ color:white; font-size:28px; font-weight:bold; cursor:pointer; }
    .close:hover{ opacity:0.7; }
    .modal-body{ padding:1.5rem; }
    .modal-body .form-group{ margin-bottom:1rem; }
    .modal-body label{ display:block; margin-bottom:0.5rem; font-weight:500; }
    .modal-body input, .modal-body textarea, .modal-body select { width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:4px; font-family: 'Montserrat', sans-serif; }
    .modal-footer{ padding:1rem; text-align:right; border-top:1px solid var(--border); }
    .driver-details-modal .details-section{ margin-bottom:1.5rem; }
    .driver-details-modal .details-row{ display:flex; justify-content:space-between; margin-bottom:0.5rem; }
    .driver-details-modal .details-label{ font-weight:500; color:var(--text-light); }
  </style>
</head>
<body>
<header>
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">
      🐄
      <span>BovinTrade • Transportadora</span>
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
  <aside class="sidebar" id="sidebar">
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
  <div class="resizer"></div>

  <main class="main">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-users"></i> Gerenciamento de Motoristas</h1>
      <div class="dashboard-actions">
        <button class="btn btn-outline" onclick="exportarMotoristas()">
          <i class="fas fa-download"></i> Exportar
        </button>
        <a href="cadastro-motorista.php" class="btn btn-primary">
          <i class="fas fa-plus"></i> Adicionar Motorista
        </a>
      </div>
    </div>

    <div class="tabs">
      <div class="tab active" data-tab="overview">Visão Geral</div>
      <div class="tab" data-tab="documents">Documentação (CNH)</div>
    </div>

    <div class="tab-content active" id="overview">
      <?php if (count($motoristas) === 0): ?>
          <div class="empty-state">
              <i class="fas fa-users"></i>
              <p>Nenhum motorista cadastrado ainda.</p>
              <p>Use o botão "Adicionar Motorista" para começar.</p>
          </div>
      <?php else: ?>
          <div class="driver-overview">
          <?php foreach($motoristas as $motorista): ?>
              <?php
              // Lógica de status corrigida
              $is_active = in_array($motorista['id'], $active_driver_ids);
              $status_class = $is_active ? 'status-in-use' : 'status-available';
              $status_label = $is_active ? 'Em uso' : 'Disponível';
              $motorista_json = htmlspecialchars(json_encode($motorista), ENT_QUOTES, 'UTF-8');
              ?>
              <div class="driver-card">
                <div class="driver-header">
                  <i class="fas fa-user-circle" style="font-size: 3rem; color: var(--primary-light);"></i>
                  <span class="driver-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                </div>
                <div class="driver-details">
                  <div class="driver-title">
                    <span><?php echo e($motorista['nome']); ?></span>
                    <span class="driver-cnh-cat"><?php echo e($motorista['cnh_categoria'] ?? 'N/A'); ?></span>
                  </div>
                  <div class="driver-specs">
                    <div class="driver-spec">
                      <strong>CPF:</strong> <?php echo e($motorista['cpf'] ?? 'Não informado'); ?>
                    </div>
                    <div class="driver-spec">
                      <strong>Telefone:</strong> <?php echo e($motorista['telefone'] ?? 'Não informado'); ?>
                    </div>
                    <div class="driver-spec">
                      <strong>Email:</strong> <?php echo e($motorista['email'] ?? 'Não informado'); ?>
                    </div>
                  </div>
                  <div class="driver-actions">
                    <button class="btn btn-outline btn-sm edit-btn" data-id="<?php echo $motorista['id']; ?>" data-motorista="<?php echo $motorista_json; ?>">
                      <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn btn-primary btn-sm details-btn" data-motorista="<?php echo $motorista_json; ?>">
                      <i class="fas fa-info-circle"></i> Detalhes
                    </button>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $motorista['id']; ?>" data-motorista="<?php echo $motorista_json; ?>">
                      <i class="fas fa-trash"></i> Excluir
                    </button>
                  </div>
                </div>
              </div>
          <?php endforeach; ?>
          </div>
      <?php endif; ?>
    </div>

    <div class="tab-content" id="documents">
      <?php if (count($motoristas) === 0): ?>
           <div class="empty-state">
              <i class="fas fa-id-card"></i>
              <p>Nenhum motorista cadastrado para verificar a documentação.</p>
          </div>
      <?php else: ?>
          <div class="documents-list">
          <?php foreach($motoristas as $motorista): ?>
              <?php
              $status_class = 'status-na';
              $status_label = 'Não Informada';
              $validade_label = 'N/A';
              
              if (!empty($motorista['cnh_validade']) && substr($motorista['cnh_validade'], 0, 10) !== '0000-00-00') {
                  $validade_dt = new DateTime(substr($motorista['cnh_validade'], 0, 10));
                  $hoje = new DateTime(date('Y-m-d'));
                  $diff = $hoje->diff($validade_dt);
                  $dias_restantes = (int)$diff->format('%r%a');
                  $validade_label = $validade_dt->format('d/m/Y');

                  if ($dias_restantes < 0) {
                      $status_class = 'status-expired';
                      $status_label = 'Vencida';
                  } elseif ($dias_restantes <= 30) {
                      $status_class = 'status-warning';
                      $status_label = 'Vence em ' . $dias_restantes . ' dias';
                  } else {
                      $status_class = 'status-valid';
                      $status_label = 'Válida';
                  }
              }
              $motorista_json = htmlspecialchars(json_encode($motorista), ENT_QUOTES, 'UTF-8');
              ?>
              <div class="document-card">
                <div class="document-title">
                  <span>CNH - <?php echo e($motorista['nome']); ?></span>
                  <span class="document-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                </div>
                <div class="document-expiry">
                  <strong>Nº CNH:</strong> <?php echo e($motorista['cnh_numero'] ?? 'N/A'); ?>
                </div>
                 <div class="document-expiry">
                  <strong>Categoria:</strong> <?php echo e($motorista['cnh_categoria'] ?? 'N/A'); ?> | <strong>UF:</strong> <?php echo e($motorista['cnh_uf'] ?? 'N/A'); ?>
                </div>
                <div class="document-expiry">
                  <strong>Validade:</strong> <?php echo $validade_label; ?>
                </div>
                <div class="document-actions">
                  <button class="btn btn-primary btn-sm edit-btn" data-id="<?php echo $motorista['id']; ?>" data-motorista="<?php echo $motorista_json; ?>">
                    <i class="fas fa-edit"></i> Atualizar / Renovar
                  </button>
                </div>
              </div>
          <?php endforeach; ?>
          </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<div id="detailsModal" class="modal driver-details-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="detailsTitle">Detalhes do Motorista</h2>
      <span class="close" onclick="closeModal('detailsModal')">&times;</span>
    </div>
    <div class="modal-body">
      <div id="detailsContent"></div>
    </div>
  </div>
</div>

<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="editTitle">Editar Motorista</h2>
      <span class="close" onclick="closeModal('editModal')">&times;</span>
    </div>
    <div class="modal-body">
      <form id="editForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" name="edit_motorista" value="1">
        <input type="hidden" id="editId" name="id">
        <div class="form-group">
          <label for="editNome">Nome:</label>
          <input type="text" id="editNome" name="nome" required>
        </div>
        <div class="form-group">
          <label for="editCpf">CPF:</label>
          <input type="text" id="editCpf" name="cpf">
        </div>
        <div class="form-group">
          <label for="editTelefone">Telefone:</label>
          <input type="text" id="editTelefone" name="telefone">
        </div>
        <div class="form-group">
          <label for="editEmail">Email:</label>
          <input type="email" id="editEmail" name="email">
        </div>
        <div class="form-group">
          <label for="editCnhNumero">Número CNH:</label>
          <input type="text" id="editCnhNumero" name="cnh_numero">
        </div>
        <div class="form-group">
          <label for="editCnhCategoria">Categoria CNH:</label>
          <input type="text" id="editCnhCategoria" name="cnh_categoria">
        </div>
        <div class="form-group">
          <label for="editCnhUf">UF CNH:</label>
          <input type="text" id="editCnhUf" name="cnh_uf">
        </div>
        <div class="form-group">
          <label for="editCnhValidade">Validade CNH:</label>
          <input type="date" id="editCnhValidade" name="cnh_validade">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancelar</button>
      <button type="submit" form="editForm" class="btn btn-primary">Salvar Alterações</button>
    </div>
  </div>
</div>

<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header modal-header-danger">
      <h2>Confirmar Exclusão</h2>
      <span class="close" onclick="closeModal('deleteModal')">&times;</span>
    </div>
    <div class="modal-body">
      <p>Tem certeza que deseja desativar o motorista <strong id="deleteMotoristaName"></strong>?</p>
      <p style="font-size: 0.9rem; color: var(--text-light); margin-top: 1rem;">
        Esta ação irá marcá-lo como inativo e removê-lo da sua frota. 
        Ele não poderá ser selecionado para novos transportes. O histórico dele será mantido.
      </p>
      <form id="deleteForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" id="deleteMotoristaId" name="motorista_id">
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancelar</button>
      <button type="submit" form="deleteForm" class="btn btn-danger">Sim, Desativar</button>
    </div>
  </div>
</div>


<script>
  // Função para alternar a sidebar em dispositivos móveis
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function() {
    // Lógica de fechamento do sidebar em mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger');
        if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
    
    // Resizer functionality (copiado do exemplo para a barra lateral redimensionável)
    let isResizing = false;
    const resizer = document.querySelector('.resizer');
    const sidebar = document.querySelector('.sidebar');
    const container = document.querySelector('.container');
    
    // Só adiciona funcionalidade de redimensionamento em telas maiores
    if (window.innerWidth > 768 && resizer) {
        resizer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isResizing = true;
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
            container.style.cursor = 'col-resize';
        });
    }

    function resize(e) {
        if (!isResizing) return;
        let newWidth = e.clientX - sidebar.getBoundingClientRect().left;
        if (newWidth < 200) newWidth = 200;
        let maxWidth = window.innerWidth - 100;
        if (newWidth > maxWidth / 2) newWidth = maxWidth / 2; 
        sidebar.style.width = newWidth + 'px';
    }

    function stopResize() {
        isResizing = false;
        document.removeEventListener('mousemove', resize);
        document.removeEventListener('mouseup', stopResize);
        container.style.cursor = '';
    }
});
  // Exportar CSV
  function exportarMotoristas() {
      const motoristas = <?php echo json_encode($motoristas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const activeIds = <?php echo json_encode($active_driver_ids); ?>;

      if (motoristas.length === 0) {
          alert('Nenhum motorista para exportar.');
          return;
      }

      let csv = 'Nome,CPF,Telefone,Email,CNH Nº,Categoria,UF,Validade CNH,Status\n';

      motoristas.forEach(m => {
          const status = activeIds.includes(m.id) ? 'Em uso' : 'Disponível';
          const validade = m.cnh_validade && m.cnh_validade !== '0000-00-00' 
              ? new Date(m.cnh_validade + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'})
              : 'N/A';

          csv += [
              `"${(m.nome || '').replace(/"/g, '""')}"`,
              `"${(m.cpf || '').replace(/"/g, '""')}"`,
              `"${(m.telefone || '').replace(/"/g, '""')}"`,
              `"${(m.email || '').replace(/"/g, '""')}"`,
              `"${(m.cnh_numero || '').replace(/"/g, '""')}"`,
              `"${(m.cnh_categoria || '').replace(/"/g, '""')}"`,
              `"${(m.cnh_uf || '').replace(/"/g, '""')}"`,
              `"${validade}"`,
              `"${status}"`
          ].join(',') + '\n';
      });

      const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', 'motoristas_' + new Date().toISOString().slice(0,10) + '.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
  }

  // Tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.tab).classList.add('active');
    });
  });

  // Modais
  function openDetailsModal(data) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    const title = document.getElementById('detailsTitle');
    title.textContent = 'Detalhes de ' + (data.nome || 'Motorista');
    
    const validadeCNH = data.cnh_validade ? new Date(data.cnh_validade + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'}) : 'N/A';

    content.innerHTML = `
      <div class="details-section">
        <h3><i class="fas fa-user"></i> Informações Pessoais</h3>
        <div class="details-row"><span class="details-label">Nome:</span><span>${data.nome || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">CPF:</span><span>${data.cpf || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Telefone:</span><span>${data.telefone || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Email:</span><span>${data.email || 'N/A'}</span></div>
      </div>
      <div class="details-section">
        <h3><i class="fas fa-id-card"></i> CNH</h3>
        <div class="details-row"><span class="details-label">Número:</span><span>${data.cnh_numero || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Categoria:</span><span>${data.cnh_categoria || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">UF:</span><span>${data.cnh_uf || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Validade:</span><span>${validadeCNH}</span></div>
      </div>
    `;
    modal.style.display = 'block';
  }

  function openEditModal(id, data) {
    const modal = document.getElementById('editModal');
    const title = document.getElementById('editTitle');
    title.textContent = 'Editar ' + (data.nome || 'Motorista');
    document.getElementById('editId').value = id;
    document.getElementById('editNome').value = data.nome || '';
    document.getElementById('editCpf').value = data.cpf || '';
    document.getElementById('editTelefone').value = data.telefone || '';
    document.getElementById('editEmail').value = data.email || '';
    document.getElementById('editCnhNumero').value = data.cnh_numero || '';
    document.getElementById('editCnhCategoria').value = data.cnh_categoria || '';
    document.getElementById('editCnhUf').value = data.cnh_uf || '';
    document.getElementById('editCnhValidade').value = data.cnh_validade ? data.cnh_validade.split(' ')[0] : '';
    modal.style.display = 'block';
  }

  // --- NOVA FUNÇÃO PARA MODAL DE EXCLUIR ---
  function openDeleteModal(id, data) {
    document.getElementById('deleteMotoristaName').textContent = data.nome || 'ID ' + id;
    document.getElementById('deleteMotoristaId').value = id;
    document.getElementById('deleteModal').style.display = 'block';
  }

  function closeModal(id) {
    document.getElementById(id).style.display = 'none';
  }

  // Event delegation
  document.addEventListener('click', e => {
    const detailsBtn = e.target.closest('.details-btn');
    if (detailsBtn) {
      const data = JSON.parse(detailsBtn.dataset.motorista);
      openDetailsModal(data);
      return;
    }
    
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
      const id = editBtn.dataset.id;
      const data = JSON.parse(editBtn.dataset.motorista);
      openEditModal(id, data);
      return;
    }

    // --- NOVO LISTENER PARA EXCLUIR ---
    const deleteBtn = e.target.closest('.delete-btn');
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      const data = JSON.parse(deleteBtn.dataset.motorista);
      openDeleteModal(id, data);
      return;
    }
  });

  // Fechar modal ao clicar fora
  window.onclick = function(e) {
    ['detailsModal', 'editModal', 'deleteModal'].forEach(id => {
      const modal = document.getElementById(id);
      if (e.target === modal) modal.style.display = 'none';
    });
  };

  // Validação simples no form
  document.getElementById('editForm').addEventListener('submit', function(e) {
    const nome = document.getElementById('editNome').value.trim();
    if (!nome) {
      e.preventDefault();
      alert('O nome é obrigatório.');
    }
  });
</script>
</body>
</html>