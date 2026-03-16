<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?expired=1");
    exit;
}

$id_usuario = $_SESSION['usuario']['id'];
$email      = $_SESSION['usuario']['email'];
$nome       = $_SESSION['usuario']['nome_razao'];

// Proteção de rota
if (empty($_SESSION['usuario'])) {
  header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
  if ($u['tipo_usuario'] === 'FAZENDA')        { header('Location: 02-painel-fazenda.php'); exit; }
  if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
  header('Location: login.php'); exit;
}

$nome  = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');

// Ajuste o caminho para o seu config.php caso necessário
require_once 'config.php'; // espera prover $pdo (PDO)

$mensagem = '';

try {
    // Verifica sessão e tipo
    if (
        empty($_SESSION['usuario']) ||
        (($_SESSION['usuario']['tipo_usuario'] ?? '') !== 'FRIGORIFICO')
    ) {
        header('Location: login.php?expired=1');
        exit;
    }

    $id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception('ID de usuário inválido na sessão.');
    }

    // BUSCA colunas existentes na tabela `usuarios` (para atualizar somente colunas válidas)
    $stmt = $pdo->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN); // lista de nomes de colunas

    // Map de campos do formulário -> coluna no banco
    $fieldMap = [
        'nome'             => 'nome_razao',
        'cnpj'             => 'cnpj',
        'email'            => 'email',
        'telefone'         => 'telefone',
        'cidade'           => 'cidade',
        'estado'           => 'estado',
        'bairro'           => 'bairro',
        'rua'              => 'rua',
        'numero'           => 'numero',
        'complemento'      => 'complemento',
        'latitude'         => 'latitude',
        'longitude'        => 'longitude'
    ];

    // TRATAMENTO DO POST (salvar ou deletar)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Exclusão
        if (isset($_POST['acao']) && $_POST['acao'] === 'deletar') {
            $st = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
            $st->execute([':id' => $id]);

            session_unset();
            session_destroy();
            header('Location: login.php');
            exit;
        }

        // Salvar dados da tabela `usuarios`
        if (isset($_POST['acao']) && $_POST['acao'] === 'salvar') {
            $sets = [];
            $params = [':id' => $id];

            foreach ($fieldMap as $formName => $colName) {
                if (in_array($colName, $columns, true) && array_key_exists($formName, $_POST)) {
                    $value = trim((string) $_POST[$formName]);
                    $sets[] = "`$colName` = :$colName";
                    $params[":$colName"] = $value;
                }
            }

            if (!empty($sets)) {
                $sql = "UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = :id";
                $st = $pdo->prepare($sql);
                $st->execute($params);

                if (isset($params[':nome_razao'])) {
                    $_SESSION['usuario']['nome_razao'] = $params[':nome_razao'];
                }
                if (isset($params[':email'])) {
                    $_SESSION['usuario']['email'] = $params[':email'];
                }
            }

            // Atualiza dados do responsável na tabela `frigorifico`
            $stmt = $pdo->prepare("
                UPDATE frigorifico SET 
                    responsavel_legal = :responsavel_legal,
                    cpf_responsavel   = :cpf_responsavel,
                    cargo_responsavel = :cargo_responsavel
                WHERE usuario_id = :id
            ");
            $stmt->execute([
                ':responsavel_legal' => $_POST['responsavelLegal'] ?? '',
                ':cpf_responsavel'   => $_POST['cpfResponsavel'] ?? '',
                ':cargo_responsavel' => $_POST['cargoResponsavel'] ?? '',
                ':id'                => $id
            ]);

            $mensagem = 'Perfil atualizado com sucesso!';
        }
    }

    // BUSCAR dados atualizados do usuário
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $dados = $st->fetch(PDO::FETCH_ASSOC);

    // BUSCAR dados do responsável na tabela `frigorifico`
    $st2 = $pdo->prepare("SELECT responsavel_legal, cpf_responsavel, cargo_responsavel FROM frigorifico WHERE usuario_id = :id LIMIT 1");
    $st2->execute([':id' => $id]);
    $frigorifico = $st2->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        throw new Exception('Usuário não encontrado.');
    }

} catch (Throwable $e) {
    $mensagem = 'Erro: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>BovinTrade - Meu Perfil</title>
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
    .btn-success { background-color: var(--success); color:white;}
    .btn-success:hover { background-color:#3d8b40; transform: translateY(-1px); box-shadow:0 4px 8px rgba(76,175,80,0.2);}
    .btn-danger { background-color:#f44336; color:white;}
    .btn-danger:hover { background-color:#d32f2f; transform: translateY(-1px); box-shadow:0 4px 8px rgba(244,67,54,0.2);}
    .btn-sm { padding:0.5rem 1rem; font-size:0.85rem;}
    .btn-block { width:100%; justify-content:center; }
    .profile-container { background: var(--background); padding: 2rem; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border: 1px solid var(--border); max-width: 800px; margin: auto; }
    .profile-container h1 { color: var(--primary); font-size: 1.6rem; margin-bottom: 1.5rem; text-align: center; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { font-weight: 600; display: block; margin-bottom: 0.4rem; color: var(--text); }
    .form-group input { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; }
    .form-group input[readonly] { background: #f5f5f5; color: var(--text-light); }
    .buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
    .buttons button { padding: 10px 18px; border: 2px solid var(--primary); border-radius: 8px; font-weight: 600; background: transparent; color: var(--primary); cursor: pointer; transition: all 0.2s; }
    .buttons button:hover { background: var(--primary); color: white; }
    .buttons .delete { border-color: #dc3545; color: #dc3545; }
    .buttons .delete:hover { background: #dc3545; color: white; }
    .buttons .btn-secondary { border-color: var(--text-light); color: var(--text-light); }
    .buttons .btn-secondary:hover { background: var(--text-light); color: white; }
    #mensagem { text-align: center; margin-top: 10px; font-size: 0.95rem; color: #28a745; min-height: 1.2rem; }

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
      <span>BovinTrade • Frigorífico</span>
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
  <div class="resizer"></div>
  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-user-cog"></i> Meu Perfil</h1>
    </div>

    <div class="profile-container">
      <h1>Meu Perfil - Frigorífico</h1>

      <form method="post" onsubmit="return confirmSubmit(event);">
        <input type="hidden" name="acao" value="salvar">

        <div class="form-group"><label>Nome da Empresa</label>
          <input type="text" name="nome" value="<?= htmlspecialchars($dados['nome_razao'] ?? '') ?>" required>
        </div>
        <div class="form-group"><label>CNPJ</label>
          <input type="text" name="cnpj" value="<?= htmlspecialchars($dados['cnpj'] ?? '') ?>" readonly>
        </div>
        <div class="form-group"><label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($dados['email'] ?? '') ?>" required>
        </div>
        <div class="form-group"><label>Telefone</label>
          <input type="text" name="telefone" value="<?= htmlspecialchars($dados['telefone'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Cidade</label>
          <input type="text" name="cidade" value="<?= htmlspecialchars($dados['cidade'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Estado</label>
          <input type="text" name="estado" value="<?= htmlspecialchars($dados['estado'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Bairro</label>
          <input type="text" name="bairro" value="<?= htmlspecialchars($dados['bairro'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Rua</label>
          <input type="text" name="rua" value="<?= htmlspecialchars($dados['rua'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Número</label>
          <input type="text" name="numero" value="<?= htmlspecialchars($dados['numero'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Complemento</label>
          <input type="text" name="complemento" value="<?= htmlspecialchars($dados['complemento'] ?? '') ?>">
        </div>

        <div class="form-group"><label>Responsável</label>
          <input type="text" name="responsavelLegal" value="<?= htmlspecialchars($frigorifico['responsavel_legal'] ?? '') ?>">
        </div>
        <div class="form-group"><label>CPF do Responsável</label>
          <input type="text" name="cpfResponsavel" value="<?= htmlspecialchars($frigorifico['cpf_responsavel'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Cargo</label>
          <input type="text" name="cargoResponsavel" value="<?= htmlspecialchars($frigorifico['cargo_responsavel'] ?? '') ?>">
        </div>

        <div class="form-group"><label>Latitude</label>
          <input type="text" name="latitude" value="<?= htmlspecialchars($dados['latitude'] ?? '') ?>">
        </div>
        <div class="form-group"><label>Longitude</label>
          <input type="text" name="longitude" value="<?= htmlspecialchars($dados['longitude'] ?? '') ?>">
        </div>

        <div class="buttons">
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          <button type="submit" name="acao" value="deletar" class="btn btn-danger delete" onclick="return confirm('Deseja realmente excluir sua conta? Esta ação é irreversível.')">Excluir Conta</button>
          <button type="button" class="btn btn-outline btn-secondary" onclick="window.history.back()">Voltar</button>
        </div>

        <div id="mensagem"><?= htmlspecialchars($mensagem) ?></div>
      </form>
    </div>
  </main>
</div>

<script>
function confirmSubmit(e) { return true; }

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