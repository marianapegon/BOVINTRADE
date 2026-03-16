<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?expired=1");
    exit;
}

// Proteção de rota
if (empty($_SESSION['usuario'])) { header('Location: login.php'); exit; }
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
    if (($u['tipo_usuario'] ?? '') === 'FRIGORIFICO')      { header('Location: 07-painel-frigorifico.php'); exit; }
    if (($u['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

// Config do banco está um diretório acima:
require_once 'config.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$userId = (int)($u['id'] ?? 0);

// CSRF simples
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf_token'];

$erros = [];
$mensagem = '';

// ===== Ações (POST) - Padronizado =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (empty($_POST['_csrf']) || !hash_equals($csrf, (string)$_POST['_csrf'])) {
        $erros[] = 'Token inválido. Recarregue a página.';
    } else {
        $acao = $_POST['acao'] ?? '';
        if ($acao === 'deletar' && !$erros) {
            // Excluir conta do usuário atual
            try {
                $pdo->beginTransaction();
                $del = $pdo->prepare('DELETE FROM usuarios WHERE id = ? LIMIT 1');
                $del->execute([$userId]);
                $pdo->commit();

                // Desloga e redireciona
                session_destroy();
                header('Location: login.php?deleted=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erros[] = 'Erro ao excluir conta: ' . $e->getMessage();
            }
        }

        if ($acao === 'salvar' && !$erros) {
            // Coleta/valida campos editáveis
            $in = [];
            $in['nome_razao']       = trim((string)($_POST['nome_razao'] ?? ''));
            $in['email']            = trim((string)($_POST['email'] ?? ''));
            $in['telefone']         = trim((string)($_POST['telefone'] ?? ''));
            $in['cep']              = trim((string)($_POST['cep'] ?? ''));
            $in['cidade']           = trim((string)($_POST['cidade'] ?? ''));
            $in['estado']           = strtoupper(trim((string)($_POST['estado'] ?? '')));
            $in['bairro']           = trim((string)($_POST['bairro'] ?? ''));
            $in['rua']              = trim((string)($_POST['rua'] ?? ''));
            $in['numero']           = trim((string)($_POST['numero'] ?? ''));
            $in['complemento']      = trim((string)($_POST['complemento'] ?? ''));
            $in['latitude']         = trim((string)($_POST['latitude'] ?? ''));
            $in['longitude']       = trim((string)($_POST['longitude'] ?? ''));

            $in['responsavel_legal']= trim((string)($_POST['responsavel_legal'] ?? ''));
            $in['cpf_responsavel']  = preg_replace('/\D+/', '', (string)($_POST['cpf_responsavel'] ?? ''));
            $in['cargo_responsavel']= trim((string)($_POST['cargo_responsavel'] ?? ''));
            $in['sistema_criacao']  = trim((string)($_POST['sistema_criacao'] ?? ''));

            // Validações mínimas (segundo seu schema)
            if ($in['nome_razao'] === '')                                     $erros[] = 'Informe o nome da Fazenda.';
            if ($in['email'] === '' || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
            if ($in['telefone'] === '')                                       $erros[] = 'Informe o telefone.';
            if ($in['cep'] === '')                                            $erros[] = 'Informe o CEP.';
            if ($in['cidade'] === '')                                         $erros[] = 'Informe a cidade.';
            if ($in['estado'] === '' || strlen($in['estado']) !== 2)           $erros[] = 'Estado deve ter 2 letras (UF).';
            if ($in['responsavel_legal'] === '')                              $erros[] = 'Informe o responsável legal.';
            if ($in['cpf_responsavel'] === '' || strlen($in['cpf_responsavel']) !== 11) $erros[] = 'CPF do responsável deve ter 11 dígitos.';
            if ($in['sistema_criacao'] === '')                                $erros[] = 'Selecione o sistema de criação.';

            if (!$erros) {
                try {
                    $pdo->beginTransaction();

                    // Atualiza usuarios (CNPJ é imutável -> não atualiza)
                    $upU = $pdo->prepare("UPDATE usuarios SET
                        nome_razao=?, email=?, telefone=?, cep=?, cidade=?, estado=?,
                        bairro=?, rua=?, numero=?, complemento=?, latitude=?, longitude=?
                      WHERE id=? LIMIT 1");
                    $upU->execute([
                        $in['nome_razao'],
                        $in['email'],
                        $in['telefone'],
                        $in['cep'],
                        $in['cidade'],
                        $in['estado'],
                        $in['bairro'] !== '' ? $in['bairro'] : null,
                        $in['rua'] !== '' ? $in['rua'] : null,
                        $in['numero'] !== '' ? $in['numero'] : null,
                        $in['complemento'] !== '' ? $in['complemento'] : null,
                        $in['latitude'] !== '' ? $in['latitude'] : null,
                        $in['longitude'] !== '' ? $in['longitude'] : null,
                        $userId
                    ]);

                    // Garante registro em fazenda
                    $hasF = $pdo->prepare('SELECT 1 FROM fazenda WHERE usuario_id = ? LIMIT 1');
                    $hasF->execute([$userId]);
                    if ($hasF->fetchColumn()) {
                        $upF = $pdo->prepare("UPDATE fazenda SET
                            sistema_criacao=?, responsavel_legal=?, cpf_responsavel=?, cargo_responsavel=?
                          WHERE usuario_id=? LIMIT 1");
                        $upF->execute([
                            $in['sistema_criacao'],
                            $in['responsavel_legal'],
                            $in['cpf_responsavel'],
                            $in['cargo_responsavel'] !== '' ? $in['cargo_responsavel'] : null,
                            $userId
                        ]);
                    } else {
                        $insF = $pdo->prepare("INSERT INTO fazenda
                            (usuario_id, sistema_criacao, responsavel_legal, cpf_responsavel, cargo_responsavel)
                            VALUES (?, ?, ?, ?, ?)");
                        $insF->execute([
                            $userId,
                            $in['sistema_criacao'],
                            $in['responsavel_legal'],
                            $in['cpf_responsavel'],
                            $in['cargo_responsavel'] !== '' ? $in['cargo_responsavel'] : null
                        ]);
                    }

                    $pdo->commit();
                    $mensagem = 'Dados atualizados com sucesso.';

                    // Atualiza sessão (cabeçalho usa email/nome)
                    $_SESSION['usuario']['nome_razao'] = $in['nome_razao'];
                    $_SESSION['usuario']['email']      = $in['email'];
                    $_SESSION['usuario']['telefone']   = $in['telefone'];
                    $_SESSION['usuario']['cep']        = $in['cep'];
                    $_SESSION['usuario']['cidade']     = $in['cidade'];
                    $_SESSION['usuario']['estado']     = $in['estado'];
                    $_SESSION['usuario']['bairro']     = $in['bairro'];
                    $_SESSION['usuario']['rua']        = $in['rua'];
                    $_SESSION['usuario']['numero']     = $in['numero'];
                    $_SESSION['usuario']['complemento']= $in['complemento'];
                    $_SESSION['usuario']['latitude']   = $in['latitude'];
                    $_SESSION['usuario']['longitude'] = $in['longitude'];

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    // Tratativa para violação de unicidade
                    if ($e->getCode() === '23000') {
                        $msg = $e->getMessage();
                        if (stripos($msg, 'uq_usuarios_email') !== false) {
                            $erros[] = 'E-mail já está em uso.';
                        } else {
                            $erros[] = 'Violação de restrição de unicidade.';
                        }
                    } else {
                        $erros[] = 'Erro ao salvar: ' . $e->getMessage();
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $erros[] = 'Erro ao salvar: ' . $e->getMessage();
                }
            }
        }
    }
}

// ===== Carrega dados do banco para exibir no formulário =====
$dados = [
    'nome_razao'       => '',
    'cnpj'             => '',
    'cep'              => '',
    'cidade'           => '',
    'estado'           => '',
    'bairro'           => '',
    'rua'              => '',
    'numero'           => '',
    'complemento'      => '',
    'latitude'         => '',
    'longitude'       => '',
    'email'            => '',
    'telefone'         => '',
    'responsavel_legal'=> '',
    'cpf_responsavel'  => '',
    'cargo_responsavel'=> '',
    'sistema_criacao'  => '',
];

try {
    // Busca dados do usuário e fazenda
    $sql = "SELECT
             u.nome_razao, u.cnpj, u.cep, u.cidade, u.estado, u.bairro, u.rua, u.numero, u.complemento,
             u.latitude, u.longitude,
             u.email, u.telefone,
             f.responsavel_legal, f.cpf_responsavel, f.cargo_responsavel, f.sistema_criacao
           FROM usuarios u
           LEFT JOIN fazenda f ON f.usuario_id = u.id
           WHERE u.id = ?
           LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$userId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        foreach ($dados as $k => $v) { if (array_key_exists($k, $row)) $dados[$k] = (string)$row[$k]; }
    }

} catch (Throwable $e) {
    $erros[] = 'Erro ao carregar dados: ' . $e->getMessage();
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
    .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; }
    .form-group input[readonly] { background: #f5f5f5; color: var(--text-light); }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
    .buttons button { padding: 10px 18px; border: 2px solid var(--primary); border-radius: 8px; font-weight: 600; background: transparent; color: var(--primary); cursor: pointer; transition: all 0.2s; }
    .buttons button:hover { background: var(--primary); color: white; }
    .buttons .delete { border-color: #dc3545; color: #dc3545; }
    .buttons .delete:hover { background: #dc3545; color: white; }
    .buttons .btn-secondary { border-color: var(--text-light); color: var(--text-light); }
    .buttons .btn-secondary:hover { background: var(--text-light); color: white; }
    #mensagem { text-align: center; margin-top: 10px; font-size: 0.95rem; color: #28a745; min-height: 1.2rem; }
    .alert { padding:1rem; border-radius:8px; margin:0 0 1rem 0; }
    .alert-success{ background:#e8f5e9; border:1px solid #c8e6c9; color:#256029; }
    .alert-error{ background:#ffebee; border:1px solid #ffcdd2; color:#7a0000; }
    .sistema-criacao { display: flex; gap: 2rem; flex-wrap: wrap; }
    .sistema-criacao label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }

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
      .sistema-criacao {
        flex-direction: column;
        gap: 1rem;
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
    <span><?= e($u['email'] ?? '') ?></span>
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
      <h1 class="dashboard-title"><i class="fas fa-user-cog"></i> Meu Perfil</h1>
    </div>

    <?php if ($erros): ?>
      <div class="alert alert-error">
        <?php foreach ($erros as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="profile-container">
      <h1>Meu Perfil - Fazenda</h1>

      <form method="post" onsubmit="return confirmSubmit(event);">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="acao" value="salvar">

        <div class="form-row">
          <div class="form-group"><label>Nome da Fazenda</label>
            <input type="text" name="nome_razao" value="<?= e($dados['nome_razao']) ?>" required>
          </div>
          <div class="form-group"><label>CNPJ</label>
            <input type="text" name="cnpj" value="<?= e($dados['cnpj']) ?>" readonly>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group"><label>CEP</label>
            <input type="text" name="cep" value="<?= e($dados['cep']) ?>" required>
          </div>
          <div class="form-group"><label>Cidade</label>
            <input type="text" name="cidade" value="<?= e($dados['cidade']) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group"><label>Estado (UF)</label>
            <input type="text" name="estado" maxlength="2" value="<?= e($dados['estado']) ?>" required>
          </div>
          <div class="form-group"><label>Bairro</label>
            <input type="text" name="bairro" value="<?= e($dados['bairro']) ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group"><label>Rua</label>
            <input type="text" name="rua" value="<?= e($dados['rua']) ?>">
          </div>
          <div class="form-group"><label>Número</label>
            <input type="text" name="numero" value="<?= e($dados['numero']) ?>">
          </div>
        </div>

        <div class="form-group"><label>Complemento</label>
          <input type="text" name="complemento" value="<?= e($dados['complemento']) ?>">
        </div>

        <div class="form-row">
          <div class="form-group"><label>Latitude</label>
            <input type="text" name="latitude" value="<?= e($dados['latitude']) ?>">
          </div>
          <div class="form-group"><label>Longitude</label>
            <input type="text" name="longitude" value="<?= e($dados['longitude']) ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group"><label>E-mail</label>
            <input type="email" name="email" value="<?= e($dados['email']) ?>" required>
          </div>
          <div class="form-group"><label>Telefone</label>
            <input type="tel" name="telefone" value="<?= e($dados['telefone']) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group"><label>Responsável Legal</label>
            <input type="text" name="responsavel_legal" value="<?= e($dados['responsavel_legal']) ?>" required>
          </div>
          <div class="form-group"><label>CPF do Responsável</label>
            <input type="text" name="cpf_responsavel" value="<?= e($dados['cpf_responsavel']) ?>" required>
          </div>
        </div>

        <div class="form-group"><label>Cargo</label>
          <input type="text" name="cargo_responsavel" value="<?= e($dados['cargo_responsavel']) ?>">
        </div>

        <div class="form-group">
          <label>Sistema de Criação</label>
          <div class="sistema-criacao">
            <?php $sc = $dados['sistema_criacao']; ?>
            <label><input type="radio" name="sistema_criacao" value="Pasto" <?= $sc==='Pasto'?'checked':'' ?>> Pasto</label>
            <label><input type="radio" name="sistema_criacao" value="Confinamento" <?= $sc==='Confinamento'?'checked':'' ?>> Confinamento</label>
            <label><input type="radio" name="sistema_criacao" value="Semi-confinamento" <?= $sc==='Semi-confinamento'?'checked':'' ?>> Semi-confinamento</label>
          </div>
        </div>

        <div class="buttons">
          <button type="submit" class="btn btn-primary">Salvar Alterações</button>
          <button type="submit" name="acao" value="deletar" class="btn btn-danger delete" onclick="return confirm('Deseja realmente excluir sua conta? Esta ação é irreversível.')">Excluir Conta</button>
          <button type="button" class="btn btn-outline btn-secondary" onclick="window.history.back()">Voltar</button>
        </div>

        <div id="mensagem"><?= e($mensagem) ?></div>
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