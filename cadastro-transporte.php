<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once  'config.php';

// Proteção de rota
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php");
    exit;
}

// Funções auxiliares para HTML e POST
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function old($k){ return e($_POST[$k] ?? ''); }

$email = e($_SESSION['usuario']['email'] ?? '');
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- Captura dos dados do formulário ---
        $placa = strtoupper(trim($_POST['placa'] ?? ''));
        $modelo = trim($_POST['modelo'] ?? ''); // NOVO CAMPO
        $tipo = $_POST['tipo'] ?? '';
        $tipo_outro = trim($_POST['tipo_outro'] ?? '');
        $capacidade_min = (int)($_POST['capacidade_min'] ?? 0);
        $capacidade_max = (int)($_POST['capacidade_max'] ?? 0);
        $renavam = trim($_POST['renavam'] ?? '');
        $ano_fabricacao = (int)($_POST['ano_fabricacao'] ?? 0);
        // NOVO CAMPO (Tratamento para data vazia)
        $crlv_validade = empty($_POST['crlv_validade']) ? null : $_POST['crlv_validade']; 

        // Captura dos semir reboques (apenas se tipo for CARRETA)
        $semireboques = [];
        if ($tipo === 'CARRETA') {
            for ($i = 1; $i <= 5; $i++) {
                $sr_placa = strtoupper(trim($_POST["sr_placa_{$i}"] ?? ''));
                $sr_modelo = trim($_POST["sr_modelo_{$i}"] ?? '');
                if (!empty($sr_placa)) {
                    $semireboques[] = ['placa' => $sr_placa, 'modelo' => $sr_modelo];
                }
            }
            if (empty($semireboques)) {
                throw new Exception('Para veículos do tipo Carreta, adicione pelo menos um semir reboque.');
            }
            if (count($semireboques) > 5) {
                throw new Exception('Máximo de 5 semir reboques permitidos.');
            }
        }

        if ($tipo === 'OUTRO' && $tipo_outro !== '') {
            $tipo = $tipo_outro;
        }

        // --- Validações ---
        if ($placa === '' || $tipo === '' || $capacidade_max <= 0 || $ano_fabricacao <= 0) {
            throw new Exception('Preencha todos os campos obrigatórios marcados com *.');
        }

        // Regex para Placa Mercosul (3 letras, 1 número, 1 letra/número, 2 números)
        // Ajuste se precisar aceitar placas antigas também
        if (!preg_match('/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/', $placa)) {
            throw new Exception('Placa inválida. Use o formato Mercosul (ex.: ABC1D23).');
        }

        // Validação de placas de semir reboques (se aplicável)
        foreach ($semireboques as $sr) {
            if (!preg_match('/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/', $sr['placa'])) {
                throw new Exception("Placa de semir reboque inválida: {$sr['placa']}. Use o formato Mercosul (ex.: ABC1D23).");
            }
        }

        // Verifica duplicidade da placa principal
        $stmt = $pdo->prepare("SELECT id FROM veiculo WHERE placa = :placa LIMIT 1");
        $stmt->execute([':placa' => $placa]);
        if ($stmt->fetch()) {
            throw new Exception("Já existe um veículo cadastrado com esta placa.");
        }

        // Verifica duplicidade de placas de semir reboques (global, para evitar duplicatas no sistema)
        foreach ($semireboques as $sr) {
            $stmt = $pdo->prepare("SELECT id FROM veiculo WHERE placa = :placa LIMIT 1");
            $stmt->execute([':placa' => $sr['placa']]);
            if ($stmt->fetch()) {
                throw new Exception("Já existe um veículo cadastrado com a placa de semir reboque: {$sr['placa']}.");
            }
        }

        // --- Insere veículo (COM OS NOVOS CAMPOS) ---
        $stmt = $pdo->prepare("INSERT INTO veiculo 
            (placa, modelo, tipo, capacidade_min, capacidade_max, renavam, ano_fabricacao, crlv_validade, ativo, created_at, updated_at) 
            VALUES (:placa, :modelo, :tipo, :capacidade_min, :capacidade_max, :renavam, :ano_fabricacao, :crlv_validade, 1, NOW(), NOW())");
        $stmt->execute([
            ':placa' => $placa,
            ':modelo' => $modelo, // NOVO
            ':tipo' => $tipo,
            ':capacidade_min' => $capacidade_min,
            ':capacidade_max' => $capacidade_max,
            ':renavam' => $renavam,
            ':ano_fabricacao' => $ano_fabricacao,
            ':crlv_validade' => $crlv_validade // NOVO
        ]);

        // === Vincula veículo à transportadora logada ===
        $veiculo_id = $pdo->lastInsertId();
        $transportadora_id = $_SESSION['usuario']['id'];

        $stmt = $pdo->prepare("INSERT INTO transportadora_veiculo 
            (transportadora_usuario_id, veiculo_id, data_inicio, principal, created_at) 
            VALUES (:tid, :vid, NOW(), 1, NOW())");
        $stmt->execute([
            ':tid' => $transportadora_id,
            ':vid' => $veiculo_id
        ]);

        // Insere semir reboques se aplicável
        if ($tipo === 'CARRETA' && !empty($semireboques)) {
            $stmt = $pdo->prepare("INSERT INTO semireboque (veiculo_id, placa, modelo, created_at, updated_at) VALUES (:veiculo_id, :placa, :modelo, NOW(), NOW())");
            foreach ($semireboques as $sr) {
                $stmt->execute([
                    ':veiculo_id' => $veiculo_id,
                    ':placa' => $sr['placa'],
                    ':modelo' => $sr['modelo']
                ]);
            }
        }

        $sucesso = "Veículo *{$placa}* cadastrado e vinculado à transportadora com sucesso!";
        // Limpa POST para não preencher o formulário após sucesso
        $_POST = [];

    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

// Calcular contador de semir reboques para JS
$contador_semireboques = 0;
if (isset($_POST['tipo']) && $_POST['tipo'] === 'CARRETA') {
    foreach ($_POST as $k => $v) {
        if (preg_match('/^sr_placa_(\d+)$/', $k, $m)) {
            $contador_semireboques = max($contador_semireboques, (int)$m[1]);
        }
    }
}
$contador_semireboques = $contador_semireboques ?: 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cadastro de Veículo</title>
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
.dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;}
.dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
.dashboard-actions { display:flex; gap:1rem;}
.btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem;}
.btn-primary { background-color: var(--primary); color:white;}
.btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
.btn-outline { background-color:transparent; color:var(--primary); border:1px solid var(--primary);}
.btn-outline:hover { background-color: rgba(163,0,0,0.05);}
.profile-container { background: var(--background); padding: 2rem; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border: 1px solid var(--border); max-width: 800px; margin: auto; }
.profile-container h1 { color: var(--primary); font-size: 1.6rem; margin-bottom: 1.5rem; text-align: center; }
.form-group { margin-bottom: 1rem; }
.form-group label { font-weight: 600; display: block; margin-bottom: 0.4rem; color: var(--text); }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; font-family: 'Montserrat', sans-serif; }
.form-group input[readonly], .form-group textarea[readonly] { background: #f5f5f5; color: var(--text-light); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-textarea { min-height: 52px; resize: vertical; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(163,0,0,.2); }
.alert { padding:1rem; border-radius:8px; margin:0 0 1rem 0; }
.alert-success{ background:#e8f5e9; border:1px solid #c8e6c9; color:#256029; }
.alert-error{ background:#ffebee; border:1px solid #ffcdd2; color:#7a0000; }
.buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
.buttons button { padding: 10px 18px; border: 2px solid var(--primary); border-radius: 8px; font-weight: 600; background: transparent; color: var(--primary); cursor: pointer; transition: all 0.2s; }
.buttons button:hover { background: var(--primary); color: white; }
.buttons button[type="submit"] { background: var(--primary); color: white; border-color: var(--primary); }
.buttons button[type="submit"]:hover { background: var(--primary-dark); }
.input-with-icon { position: relative; }
.input-with-icon i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
#campo-outro{ display:none; }
#secao-semireboques { display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid var(--border); }
.semireboque-item { display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 6px; border: 1px solid var(--border); }
.semireboque-item button { background: #dc3545; color: white; border: none; padding: 0.5rem; border-radius: 4px; cursor: pointer; }
.semireboque-item button:hover { background: #c82333; }

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
  .profile-container {
    padding: 1.5rem;
    margin: 0;
  }
  .buttons {
    flex-direction: column;
    align-items: center;
  }
  .dashboard-title {
    font-size: 1.5rem;
  }
  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
    margin-bottom: 0;
  }
  .dashboard-actions {
    flex-direction: column;
    width: 100%;
    margin-top: 1rem;
  }
  .dashboard-actions .btn {
    width: 100%;
  }
  .semireboque-item {
    grid-template-columns: 1fr;
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
  .profile-container {
    padding: 1rem;
  }
}
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
    <i class="fas fa-user-circle"></i><span>Meu Perfil</span></a>
    </ul>
  </aside>

  <div class="resizer"></div>
  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-truck-front"></i> Cadastro de Veículo</h1>
      <div class="dashboard-actions">
        </div>
    </div>

    <div class="profile-container">
      <?php if ($sucesso): ?>
        <div class="alert alert-success"><?= $sucesso // Usando $sucesso sem e() para renderizar o <b> ?></div>
      <?php endif; ?>
      <?php if ($erro): ?>
        <div class="alert alert-error">
          <div>• <?= e($erro) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <h2 class="dashboard-title" style="text-align: center; margin-bottom: 0.5rem;"><i class="fas fa-truck-moving"></i> Detalhes do Veículo</h2>
        <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">Preencha os dados abaixo para cadastrar um novo veículo na sua frota. Campos com * são obrigatórios.</p>

        <div class="form-row">
          <div class="form-group">
              <label for="placa">Placa*</label>
              <input type="text" id="placa" name="placa" maxlength="7" required placeholder="Ex: ABC1D23" value="<?= old('placa') ?>">
          </div>
          <div class="form-group">
              <label for="modelo">Modelo</label>
              <input type="text" id="modelo" name="modelo" placeholder="Ex: Scania R440" value="<?= old('modelo') ?>">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
              <label for="tipo">Tipo*</label>
              <select id="tipo" name="tipo" required onchange="toggleOutro(); toggleSemireboques()">
                  <option value="" disabled <?= old('tipo') === '' || (old('tipo') !== '' && !in_array(old('tipo'), ['BOIADEIRO', 'CARRETA', 'TRUCK', 'CAMINHAO 3/4', 'VAN', 'OUTRO'])) ? 'selected' : '' ?>>Selecione</option>
                  <option value="BOIADEIRO" <?= old('tipo') === 'BOIADEIRO' ? 'selected' : '' ?>>Boiadeiro</option>
                  <option value="CARRETA" <?= old('tipo') === 'CARRETA' ? 'selected' : '' ?>>Carreta</option>
                  <option value="TRUCK" <?= old('tipo') === 'TRUCK' ? 'selected' : '' ?>>Truck</option>
                  <option value="CAMINHAO 3/4" <?= old('tipo') === 'CAMINHAO 3/4' ? 'selected' : '' ?>>Caminhão 3/4</option>
                  <option value="VAN" <?= old('tipo') === 'VAN' ? 'selected' : '' ?>>Van</option>
                  <option value="OUTRO" <?= old('tipo') !== '' && !in_array(old('tipo'), ['BOIADEIRO', 'CARRETA', 'TRUCK', 'CAMINHAO 3/4', 'VAN']) ? 'selected' : '' ?>>Outro</option>
              </select>
          </div>
          <div class="form-group">
              <label for="ano_fabricacao">Ano de Fabricação*</label>
              <input type="number" id="ano_fabricacao" name="ano_fabricacao" min="1900" max="2099" required placeholder="Ex: 2020" value="<?= old('ano_fabricacao') ?>">
          </div>
        </div>

        <div class="form-group" id="campo-outro" style="display:<?= old('tipo') !== '' && !in_array(old('tipo'), ['BOIADEIRO', 'CARRETA', 'TRUCK', 'CAMINHAO 3/4', 'VAN']) ? 'block' : 'none' ?>;">
            <label for="tipo_outro">Informe o tipo*</label>
            <input type="text" id="tipo_outro" name="tipo_outro" placeholder="Digite o tipo do veículo" value="<?= old('tipo') !== '' && !in_array(old('tipo'), ['BOIADEIRO', 'CARRETA', 'TRUCK', 'CAMINHAO 3/4', 'VAN']) ? old('tipo') : '' ?>">
        </div>

        <div id="secao-semireboques" style="display:<?= old('tipo') === 'CARRETA' ? 'block' : 'none' ?>;">
            <h3 style="margin-bottom: 1rem; color: var(--primary);"><i class="fas fa-trailer"></i> Semir reboques (até 5)</h3>
            <p style="color: var(--text-light); margin-bottom: 1rem;">Adicione os semir reboques vinculados a esta carreta. Pelo menos um é obrigatório.</p>
            <div id="container-semireboques">
                <?php
                // Preenche old values para semir reboques (até 5, baseado em POST)
                $has_sr = false;
                for ($i = 1; $i <= 5; $i++) {
                    $sr_placa = old("sr_placa_{$i}");
                    $sr_modelo = old("sr_modelo_{$i}");
                    if (!empty($sr_placa)) {
                        $has_sr = true;
                        echo "<div class='semireboque-item' data-index='{$i}'>";
                        echo "<div class='form-group'><label>Placa Semir reboque {$i}*</label><input type='text' name='sr_placa_{$i}' maxlength='7' placeholder='Ex: XYZ9A12' value='{$sr_placa}' required></div>";
                        echo "<div class='form-group'><label>Modelo Semir reboque {$i}</label><input type='text' name='sr_modelo_{$i}' placeholder='Ex: Semir reboque 2020' value='{$sr_modelo}'></div>";
                        echo "<button type='button' onclick='removerSemireboque(this)'><i class='fas fa-trash'></i></button>";
                        echo "</div>";
                    }
                }
                // Se não há old values, adiciona um vazio por padrão para CARRETA
                if (old('tipo') === 'CARRETA' && !$has_sr) {
                    echo "<div class='semireboque-item' data-index='1'>";
                    echo "<div class='form-group'><label>Placa Semir reboque 1*</label><input type='text' name='sr_placa_1' maxlength='7' placeholder='Ex: XYZ9A12' required></div>";
                    echo "<div class='form-group'><label>Modelo Semir reboque 1</label><input type='text' name='sr_modelo_1' placeholder='Ex: Semir reboque 2020'></div>";
                    echo "<button type='button' onclick='removerSemireboque(this)'><i class='fas fa-trash'></i></button>";
                    echo "</div>";
                }
                ?>
            </div>
            <button type="button" class="btn btn-outline" onclick="adicionarSemireboque()" style="margin-top: 0.5rem;"><i class="fas fa-plus"></i> Adicionar Outro Semir reboque</button>
            <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.5rem;">Máximo de 5 semir reboques.</p>
        </div>

        <div class="form-row">
          <div class="form-group">
              <label for="capacidade_min">Capacidade Mínima (cabeças)</label>
              <input type="number" id="capacidade_min" name="capacidade_min" min="0" placeholder="Ex: 10" value="<?= old('capacidade_min') ?>">
          </div>
          <div class="form-group">
              <label for="capacidade_max">Capacidade Máxima* (cabeças)</label>
              <input type="number" id="capacidade_max" name="capacidade_max" min="1" required placeholder="Ex: 50" value="<?= old('capacidade_max') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="renavam">RENAVAM</label>
            <input type="text" id="renavam" name="renavam" placeholder="Número do RENAVAM" value="<?= old('renavam') ?>">
          </div>
          <div class="form-group">
              <label for="crlv_validade">Validade CRLV</label>
              <input type="date" id="crlv_validade" name="crlv_validade" value="<?= old('crlv_validade') ?>">
          </div>
        </div>

        <div class="buttons">
          <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Cadastrar Veículo</button>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
let contadorSemireboques = <?= $contador_semireboques ?>;

function toggleOutro() {
    var select = document.getElementById("tipo");
    var campoOutro = document.getElementById("campo-outro");
    var inputOutro = document.getElementById("tipo_outro");
    
    if (select.value === "OUTRO") {
        campoOutro.style.display = "block";
        inputOutro.required = true;
    } else {
        campoOutro.style.display = "none";
        inputOutro.required = false;
        // Limpa o campo 'outro' se outra opção for selecionada
        if(select.value !== "" && select.value !== "OUTRO") {
             inputOutro.value = "";
        }
    }
}

function toggleSemireboques() {
    var select = document.getElementById("tipo");
    var secao = document.getElementById("secao-semireboques");
    var container = document.getElementById("container-semireboques");
    
    if (select.value === "CARRETA") {
        secao.style.display = "block";
        // Se não há itens, adiciona um por padrão
        if (container.children.length === 0) {
            adicionarSemireboque();
        }
    } else {
        secao.style.display = "none";
        // Limpa todos os campos de semir reboque
        for (let i = 1; i <= 5; i++) {
            let placaInput = document.querySelector(`input[name="sr_placa_${i}"]`);
            let modeloInput = document.querySelector(`input[name="sr_modelo_${i}"]`);
            if (placaInput) placaInput.remove();
            if (modeloInput) modeloInput.remove();
        }
        contadorSemireboques = 1;
    }
}

function adicionarSemireboque() {
    if (contadorSemireboques >= 5) {
        alert('Máximo de 5 semir reboques permitidos.');
        return;
    }
    contadorSemireboques++;
    
    var container = document.getElementById("container-semireboques");
    var item = document.createElement('div');
    item.className = 'semireboque-item';
    item.dataset.index = contadorSemireboques;
    item.innerHTML = `
        <div class="form-group">
            <label>Placa Semir reboque ${contadorSemireboques}*</label>
            <input type="text" name="sr_placa_${contadorSemireboques}" maxlength="7" placeholder="Ex: XYZ9A12" required>
        </div>
        <div class="form-group">
            <label>Modelo Semir reboque ${contadorSemireboques}</label>
            <input type="text" name="sr_modelo_${contadorSemireboques}" placeholder="Ex: Semir reboque 2020">
        </div>
        <button type="button" onclick="removerSemireboque(this)"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(item);
}

function removerSemireboque(btn) {
    var item = btn.closest('.semireboque-item');
    // Reindexa os itens restantes para manter numeração sequencial
    var itens = document.querySelectorAll('#container-semireboques .semireboque-item');
    itens.forEach(function(it, index) {
        var novoIndex = index + 1;
        it.dataset.index = novoIndex;
        var inputs = it.querySelectorAll('input');
        inputs[0].name = `sr_placa_${novoIndex}`;
        inputs[0].previousElementSibling.textContent = `Placa Semir reboque ${novoIndex}*`;
        inputs[1].name = `sr_modelo_${novoIndex}`;
        inputs[1].previousElementSibling.textContent = `Modelo Semir reboque ${novoIndex}`;
    });
    contadorSemireboques = itens.length;
    item.remove();
    // Se removeu o último e ainda é obrigatório, adiciona um novo
    if (document.getElementById("tipo").value === "CARRETA" && contadorSemireboques === 0) {
        adicionarSemireboque();
    }
}

// Chamar no carregamento da página para garantir que o campo "Outro" apareça
// se um valor customizado já estiver selecionado (ex: após erro de POST).
document.addEventListener('DOMContentLoaded', function() {
    toggleOutro(); 
    toggleSemireboques();
    
    let isResizing = false;
    const resizer = document.querySelector('.resizer');
    const sidebar = document.querySelector('.sidebar');
    const container = document.querySelector('.container');
    
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
    
    // Mobile sidebar toggle
    window.toggleSidebar = function() {
        sidebar.classList.toggle('active');
    }
});
</script>
</body>
</html>