<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php';

// Proteção de rota: exige login e tipo FAZENDA
if (empty($_SESSION['usuario'])) {
  header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
  if ($u['tipo_usuario'] === 'FRIGORIFICO')    { header('Location: 07-painel-frigorifico.php'); exit; }
  if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
  header('Location: login.php'); exit;
}

// Define variáveis que serão usadas no header
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function old($k){ return e($_POST[$k] ?? ''); }
$nome  = e($u['nome_razao'] ?? 'Fazenda');
$email = e($u['email']      ?? '');

$erros = [];
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fazendaId        = (int)($_SESSION['usuario']['id'] ?? 0); // usuarios.id
  $quantidade       = (int)($_POST['quantidade'] ?? 0);
  $pesoMedio        = str_replace(',', '.', trim($_POST['peso_medio'] ?? ''));
  $raca             = trim($_POST['raca'] ?? '');
  $preco            = str_replace(',', '.', trim($_POST['preco'] ?? '')); // preço por animal
  $tipoAlimentacao  = trim($_POST['alimentacao'] ?? '');
  $historicoVacinas = trim($_POST['vacinas'] ?? '');
  $descricao        = trim($_POST['descricao'] ?? '');
  $localizacao      = trim($_POST['localizacao'] ?? '');

  if ($fazendaId <= 0) $erros[] = 'Sessão expirada. Faça login novamente.';
  if ($quantidade <= 0) $erros[] = 'Quantidade deve ser maior que 0.';
  if ($pesoMedio === '' || !is_numeric($pesoMedio) || (float)$pesoMedio <= 0) $erros[] = 'Peso médio (kg) inválido.';
  if ($raca === '') $erros[] = 'Selecione a raça.';
  if ($preco === '' || !is_numeric($preco) || (float)$preco < 0) $erros[] = 'Preço por animal inválido.';
  if ($tipoAlimentacao === '') $erros[] = 'Selecione o tipo de alimentação.';
  if ($historicoVacinas === '') $erros[] = 'Informe o histórico de vacinação.';
  if ($localizacao === '') $erros[] = 'Selecione a localização.';

  // Cálculo do total (server-side)
  $precoUnitario = is_numeric($preco) ? (float)$preco : 0.0;
  $precoTotal    = $precoUnitario * max(0, $quantidade);
  if ($precoTotal <= 0) $erros[] = 'Preço total calculado é inválido.';

  // Garante que há registro na tabela fazenda
  if (!$erros) {
    try {
      $chk = $pdo->prepare('SELECT 1 FROM fazenda WHERE usuario_id = ? LIMIT 1');
      $chk->execute([$fazendaId]);
      if (!$chk->fetchColumn()) $erros[] = 'Complete o cadastro da Fazenda antes de criar lotes.';
    } catch (Throwable $e) {
      $erros[] = 'Erro ao validar perfil de fazenda.';
    }
  }

  if (!$erros) {
    try {
      $ins = $pdo->prepare("
        INSERT INTO lote_bois
          (fazenda_id, quantidade, peso_medio_kg, raca, preco,
           historico_vacinacao, tipo_alimentacao, descricao, status,
           localizacao, preco_total)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, 'DISPONIVEL', ?, ?)
      ");
      $ins->execute([
        $fazendaId,
        $quantidade,
        (float)$pesoMedio,
        $raca,
        $precoUnitario,         // preço por animal
        $historicoVacinas,
        $tipoAlimentacao,
        $descricao !== '' ? $descricao : null,
        $localizacao,
        $precoTotal             // ✅ novo campo calculado
      ]);

      $id = (int)$pdo->lastInsertId();
      $q  = $pdo->prepare('SELECT codigo_lote FROM lote_bois WHERE id = ?');
      $q->execute([$id]);
      $codigo = $q->fetchColumn();
      $sucesso = 'Lote cadastrado com sucesso!'
        . ($codigo ? " Código: {$codigo}." : '')
        . ' Total do lote: R$ ' . number_format($precoTotal, 2, ',', '.');

      // limpa campos do form
      $_POST = [];
    } catch (Throwable $e) {
      $erros[] = 'Erro ao salvar o lote: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Plataforma de Negociação Pecuária</title>
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
    .profile-container { background: var(--background); padding: 2rem; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border: 1px solid var(--border); max-width: 800px; margin: auto; }
    .profile-container h1 { color: var(--primary); font-size: 1.6rem; margin-bottom: 1.5rem; text-align: center; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { font-weight: 600; display: block; margin-bottom: 0.4rem; color: var(--text); }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; }
    .form-group input[readonly], .form-group textarea[readonly] { background: #f5f5f5; color: var(--text-light); }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .form-textarea { min-height: 52px; resize: vertical; }
    .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(163,0,0,.2); }
    .alert { padding:1rem; border-radius:8px; margin:0 0 1rem 0; }
    .alert-success{ background:#e8f5e9; border:1px solid #c8e6c9; color:#256029; }
    .alert-error{ background:#ffebee; border:1px solid #ffcdd2; color:#7a0000; }
    .buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
    .buttons button { padding: 10px 18px; border: 2px solid var(--primary); border-radius: 8px; font-weight: 600; background: transparent; color: var(--primary); cursor: pointer; transition: all 0.2s; }
    .buttons button:hover { background: var(--primary); color: white; }
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
      <span>BovinTrade • Fazenda</span>
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
      <h1 class="dashboard-title"><i class="fas fa-plus-circle"></i> Cadastro de Lote de Bois</h1>

    </div>

    <div class="profile-container">
      <?php if ($sucesso): ?>
        <div class="alert alert-success"><?= e($sucesso) ?></div>
      <?php endif; ?>
      <?php if ($erros): ?>
        <div class="alert alert-error">
          <?php foreach ($erros as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <h2 class="dashboard-title"><i class="fas fa-cow"></i> Cadastro de Lote de Bois</h2>
        <p style="text-align: center; color: var(--text-light); margin-bottom: 2rem;">Preencha os dados abaixo para cadastrar um novo lote</p>

        <div class="form-row">
          <div class="form-group"><label>Quantidade (cabeças)</label>
            <input type="number" name="quantidade" placeholder="Ex: 50" required min="1" value="<?= old('quantidade') ?>">
          </div>
          <div class="form-group"><label>Peso Médio (kg)</label>
            <input type="number" name="peso_medio" placeholder="Ex: 450" step="0.01" required min="0.01" value="<?= old('peso_medio') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group"><label>Raça</label>
            <select name="raca" required>
              <option value="" disabled <?= old('raca')===''?'selected':''; ?>>Selecione</option>
              <option <?= old('raca')==='Nelore'?'selected':''; ?>>Nelore</option>
              <option <?= old('raca')==='Angus'?'selected':''; ?>>Angus</option>
              <option <?= old('raca')==='Brahman'?'selected':''; ?>>Brahman</option>
              <option <?= old('raca')==='Hereford'?'selected':''; ?>>Hereford</option>
            </select>
          </div>
          <div class="form-group"><label>Preço por animal (R$)</label>
            <div class="input-with-icon">
              <input type="number" name="preco" placeholder="Ex: 2500" step="0.01" required min="0" value="<?= old('preco') ?>">
              <i class="fas fa-dollar-sign"></i>
            </div>
          </div>
        </div>

        <div class="form-group"><label>Total do Lote (R$) — calculado automaticamente</label>
          <input type="text" id="total_lote" value="R$ 0,00" readonly>
        </div>

        <div class="form-row">
          <div class="form-group"><label>Tipo de Alimentação</label>
            <select name="alimentacao" required>
              <option value="" disabled <?= old('alimentacao')===''?'selected':''; ?>>Selecione</option>
              <option <?= old('alimentacao')==='Pastagem'?'selected':''; ?>>Pastagem</option>
              <option <?= old('alimentacao')==='Confinamento'?'selected':''; ?>>Confinamento</option>
              <option <?= old('alimentacao')==='Semi-confinamento'?'selected':''; ?>>Semi-confinamento</option>
            </select>
          </div>
          <div class="form-group"><label>Localização</label>
            <select name="localizacao" required>
              <option value="" disabled <?= old('localizacao')===''?'selected':''; ?>>Selecione</option>
              <option <?= old('localizacao')==='AC'?'selected':''; ?>>Acre</option>
              <option <?= old('localizacao')==='AL'?'selected':''; ?>>Alagoas</option>
              <option <?= old('localizacao')==='AP'?'selected':''; ?>>Amapá</option>
              <option <?= old('localizacao')==='AM'?'selected':''; ?>>Amazonas</option>
              <option <?= old('localizacao')==='BA'?'selected':''; ?>>Bahia</option>
              <option <?= old('localizacao')==='CE'?'selected':''; ?>>Ceará</option>
              <option <?= old('localizacao')==='DF'?'selected':''; ?>>Distrito Federal</option>
              <option <?= old('localizacao')==='ES'?'selected':''; ?>>Espírito Santo</option>
              <option <?= old('localizacao')==='GO'?'selected':''; ?>>Goiás</option>
              <option <?= old('localizacao')==='MA'?'selected':''; ?>>Maranhão</option>
              <option <?= old('localizacao')==='MT'?'selected':''; ?>>Mato Grosso</option>
              <option <?= old('localizacao')==='MS'?'selected':''; ?>>Mato Grosso do Sul</option>
              <option <?= old('localizacao')==='MG'?'selected':''; ?>>Minas Gerais</option>
              <option <?= old('localizacao')==='PA'?'selected':''; ?>>Pará</option>
              <option <?= old('localizacao')==='PB'?'selected':''; ?>>Paraíba</option>
              <option <?= old('localizacao')==='PR'?'selected':''; ?>>Paraná</option>
              <option <?= old('localizacao')==='PE'?'selected':''; ?>>Pernambuco</option>
              <option <?= old('localizacao')==='PI'?'selected':''; ?>>Piauí</option>
              <option <?= old('localizacao')==='RJ'?'selected':''; ?>>Rio de Janeiro</option>
              <option <?= old('localizacao')==='RN'?'selected':''; ?>>Rio Grande do Norte</option>
              <option <?= old('localizacao')==='RS'?'selected':''; ?>>Rio Grande do Sul</option>
              <option <?= old('localizacao')==='RO'?'selected':''; ?>>Rondônia</option>
              <option <?= old('localizacao')==='RR'?'selected':''; ?>>Roraima</option>
              <option <?= old('localizacao')==='SC'?'selected':''; ?>>Santa Catarina</option>
              <option <?= old('localizacao')==='SP'?'selected':''; ?>>São Paulo</option>
              <option <?= old('localizacao')==='SE'?'selected':''; ?>>Sergipe</option>
              <option <?= old('localizacao')==='TO'?'selected':''; ?>>Tocantins</option>
            </select>
          </div>
        </div>

        <div class="form-group"><label>Histórico de Vacinação</label>
          <textarea name="vacinas" placeholder="Descreva as vacinas aplicadas..." required><?= old('vacinas') ?></textarea>
        </div>

        <div class="form-group"><label>Descrição do Lote</label>
          <textarea name="descricao" placeholder="Informações adicionais sobre o lote..."><?= old('descricao') ?></textarea>
        </div>

        <div class="buttons">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Cadastrar Lote</button>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
function formatBR(n){
  return n.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function calcTotal(){
  const q = parseInt(document.getElementById('quantidade')?.value || '0', 10);
  const p = parseFloat(document.getElementById('preco')?.value || '0');
  const total = (isFinite(q) ? q : 0) * (isFinite(p) ? p : 0);
  const out = document.getElementById('total_lote');
  if (out) out.value = 'R$ ' + formatBR(total);
}
document.addEventListener('DOMContentLoaded', function() {
  ['quantidade','preco'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', calcTotal);
  });
  calcTotal();

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
});
</script>
</body>
</html>