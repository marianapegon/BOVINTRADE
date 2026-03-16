<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php';

// Funções auxiliares para HTML e POST
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function old($k){ 
    // Usado para preencher os campos em caso de erro. 
    // Trata CPF e Telefone para retornar o valor limpo se a validação falhar, ou o valor postado.
    if ($k === 'cpf' || $k === 'telefone') {
        return e(preg_replace('/\D/', '', $_POST[$k] ?? ''));
    }
    return e($_POST[$k] ?? ''); 
}

// Proteção: só transportadora pode acessar
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php");
    exit;
}

$erro = null;
$sucesso = null;
$email = e($_SESSION['usuario']['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nome = trim($_POST['nome'] ?? '');
        $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $cnh_numero = trim($_POST['cnh_numero'] ?? '');
        $cnh_categoria = strtoupper(trim($_POST['cnh_categoria'] ?? ''));
        $cnh_uf = strtoupper(trim($_POST['cnh_uf'] ?? ''));
        $cnh_validade = $_POST['cnh_validade'] ?? '';
        $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
        $email_motorista = trim($_POST['email_motorista'] ?? '');

        // Validações básicas
        if ($nome === '' || $cpf === '' || $cnh_numero === '' || $cnh_categoria === '' || $cnh_uf === '' || $cnh_validade === '' || $telefone === '' || $email_motorista === '') {
            throw new Exception('Preencha todos os campos obrigatórios.');
        }

        if (!filter_var($email_motorista, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail inválido.');
        }
        
        if (strlen($cpf) !== 11) {
            throw new Exception('CPF inválido. Deve conter 11 dígitos.');
        }

        // Verifica se CPF já existe
        $stmt = $pdo->prepare("SELECT id FROM motorista WHERE cpf = :cpf LIMIT 1");
        $stmt->execute([':cpf' => $cpf]);
        if ($stmt->fetch()) {
            throw new Exception("Já existe um motorista cadastrado com este CPF.");
        }

        // Insere no banco
        $stmt = $pdo->prepare("INSERT INTO motorista
            (nome, cpf, cnh_numero, cnh_categoria, cnh_uf, cnh_validade, telefone, email, ativo, created_at, updated_at)
            VALUES
            (:nome, :cpf, :cnh_numero, :cnh_categoria, :cnh_uf, :cnh_validade, :telefone, :email, 1, NOW(), NOW())");
        $stmt->execute([
            ':nome' => $nome,
            ':cpf' => $cpf,
            ':cnh_numero' => $cnh_numero,
            ':cnh_categoria' => $cnh_categoria,
            ':cnh_uf' => $cnh_uf,
            ':cnh_validade' => $cnh_validade,
            ':telefone' => $telefone,
            ':email' => $email_motorista
        ]);

        // === Vínculo do motorista com a transportadora logada ===
        $motorista_id = $pdo->lastInsertId();
        $transportadora_id = $_SESSION['usuario']['id'];

        $stmt = $pdo->prepare("INSERT INTO transportadora_motorista 
            (transportadora_usuario_id, motorista_id, data_inicio, principal, created_at) 
            VALUES (:tid, :mid, NOW(), 1, NOW())");
        $stmt->execute([
            ':tid' => $transportadora_id,
            ':mid' => $motorista_id
        ]);

        $sucesso = "Motorista *{$nome}* cadastrado e vinculado à transportadora com sucesso!";
        // Limpa POST para não preencher o formulário após sucesso
        $_POST = [];

    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cadastro de Motorista</title>
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
.dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap: wrap;}
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
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(163,0,0,.2); }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
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
  }
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
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
      <a href="14-painel-transportadora.php" class="menu-item <?= $current_page === '14-painel-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Painel</span></a>
      <a href="cadastro-transporte.php" class="menu-item <?= $current_page === 'cadastro-transporte.php' ? 'active' : '' ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
      <a href="cadastro-motorista.php" class="menu-item <?= $current_page === 'cadastro-motorista.php' ? 'active' : '' ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
      <a href="gerenciar-motoristas.php" class="menu-item <?= $current_page === 'gerenciar-motoristas.php' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
      <a href="gerenciar-transportes-transp.php" class="menu-item <?= $current_page === 'gerenciar-transportes-transp.php' ? 'active' : '' ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
      <a href="pedidos-transportes.php" class="menu-item <?= $current_page === 'pedidos-transportes.php' ? 'active' : '' ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
      <a href="coletas-agendadas.php" class="menu-item <?= $current_page === 'coletas-agendadas.php' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
      <a href="rastreamento-transporte-t.php" class="menu-item <?= $current_page === 'rastreamento-transporte-t.php' ? 'active' : '' ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
      <a href="historico-transporte-t.php" class="menu-item <?= $current_page === 'historico-transporte-t.php' ? 'active' : '' ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
      <a href="notificacoes-transportadora.php" class="menu-item <?= $current_page === 'notificacoes-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
      <a href="minhas-avaliacoes-transportadora.php" class="menu-item <?= $current_page === 'minhas-avaliacoes-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
      <a href="17-ajudat.php" class="menu-item <?= $current_page === '17-ajudat.php' ? 'active' : '' ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
      <a href="meu-perfil-transportadora.php" class="menu-item <?= $current_page === 'meu-perfil-transportadora.php' ? 'active' : '' ?>">
        <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
      </a>
      
    </ul>
  </aside>

  <div class="resizer"></div>
  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-user-plus"></i> Cadastro de Motorista</h1>
      <div class="dashboard-actions">
        </div>
    </div>

    <div class="profile-container">
      <?php if ($sucesso): ?>
        <div class="alert alert-success"><?= e($sucesso) ?></div>
      <?php endif; ?>
      <?php if ($erro): ?>
        <div class="alert alert-error">
            <div>• <?= e($erro) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <h2 class="dashboard-title" style="text-align: center; margin-bottom: 0.5rem;"><i class="fas fa-address-card"></i> Dados Pessoais e CNH</h2>
        <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">Preencha os dados abaixo para cadastrar um novo motorista. Todos os campos são obrigatórios.</p>

        <div class="form-group">
            <label for="nome">Nome Completo*</label>
            <input type="text" id="nome" name="nome" required placeholder="Nome do Motorista" value="<?= old('nome') ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="cpf">CPF (somente números)*</label>
                <input type="text" id="cpf" name="cpf" maxlength="11" required placeholder="00011122233" value="<?= old('cpf') ?>">
            </div>
            <div class="form-group">
                <label for="telefone">Telefone (somente números)*</label>
                <input type="text" id="telefone" name="telefone" required placeholder="5511987654321" value="<?= old('telefone') ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="email_motorista">E-mail*</label>
            <input type="email" id="email_motorista" name="email_motorista" required placeholder="email@motorista.com" value="<?= old('email_motorista') ?>">
        </div>

        <hr style="margin: 2rem 0; border: 0; border-top: 1px solid var(--border);">

        <div class="form-row">
            <div class="form-group">
                <label for="cnh_numero">Número CNH*</label>
                <input type="text" id="cnh_numero" name="cnh_numero" required placeholder="Número do registro da CNH" value="<?= old('cnh_numero') ?>">
            </div>
            <div class="form-group">
                <label for="cnh_validade">Validade CNH*</label>
                <input type="date" id="cnh_validade" name="cnh_validade" required value="<?= old('cnh_validade') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="cnh_categoria">Categoria CNH*</label>
                <input type="text" id="cnh_categoria" name="cnh_categoria" maxlength="2" required placeholder="Ex: D, E" value="<?= old('cnh_categoria') ?>">
            </div>
            <div class="form-group">
                <label for="cnh_uf">UF CNH*</label>
                <input type="text" id="cnh_uf" name="cnh_uf" maxlength="2" required placeholder="Ex: SP, MG" value="<?= old('cnh_uf') ?>">
            </div>
        </div>

        <div class="buttons">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Cadastrar Motorista</button>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
// Função para alternar a sidebar em dispositivos móveis
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function() {
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
</script>
</body>
</html>