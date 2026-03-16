<?php
// Bovintrade-PHP/Projeto-Bovintrade-2/login.php
session_start();
require_once 'config.php';

/**
 * Se já estiver logado, redireciona imediatamente para o painel correto.
 * (Todos os arquivos alvo são .php, sem espaços.)
 */
if (!empty($_SESSION['usuario'])) {
  $tipo = $_SESSION['usuario']['tipo_usuario'] ?? '';
  switch ($tipo) {
    case 'FAZENDA':
      header('Location: 02-painel-fazenda.php');        exit;
    case 'FRIGORIFICO':
      header('Location: 07-painel-frigorifico.php');    exit;
    case 'TRANSPORTADORA':
      header('Location: 14-painel-transportadora.php'); exit;
  }
}

$erro = null;

/** Mensagem automática se veio de sessão expirada */
if (isset($_GET['expired']) && $_GET['expired'] === '1') {
  $erro = 'Sessão expirada. Faça login novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');

    if ($email === '' || $senha === '') {
      throw new Exception('Informe e-mail e senha.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception('E-mail inválido.');
    }

    // Busca usuário por e-mail
    $sql = "SELECT id, tipo_usuario, nome_razao, email, senha_hash
            FROM usuarios
            WHERE email = :email
            LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':email' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['senha_hash']) || !password_verify($senha, $user['senha_hash'])) {
      throw new Exception('E-mail ou senha inválidos.');
    }

    // Segurança: regenera o ID de sessão no login
    session_regenerate_id(true);

    // OK: cria a sessão do usuário
    $_SESSION['usuario'] = [
      'id'           => (int)$user['id'],
      'nome_razao'   => $user['nome_razao'],
      'email'        => $user['email'],
      'tipo_usuario' => $user['tipo_usuario'],
      'logado_em'    => date('Y-m-d H:i:s'),
    ];

    // Redireciona conforme o tipo
    switch ($user['tipo_usuario']) {
      case 'FAZENDA':
        header('Location: 02-painel-fazenda.php');        break;
      case 'FRIGORIFICO':
        header('Location: 07-painel-frigorifico.php');    break;
      case 'TRANSPORTADORA':
        header('Location: 14-painel-transportadora.php'); break;
      default:
        header('Location: index.php');                    break;
    }
    exit;

  } catch (Throwable $e) {
    $erro = $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login - BovinTrade</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonte e ícones -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

  <style>
    :root {
      --primary: #a30000;
      --primary-dark: #7a0000;
      --background: #ffffff;
      --text: #333333;
      --border: #e0e0e0;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Montserrat', sans-serif;
      background: #f9f9f9;
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    header {
      background: linear-gradient(135deg, var(--primary-dark), var(--primary));
      color: #f9f9f9;
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      position: relative;
      z-index: 100;
    }
    .logo {
      font-size: 1.8rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .user-menu { display: flex; align-items: center; gap: 1.5rem; }
    .user-menu a {
      color: white; text-decoration: none; font-weight: 500; transition: opacity 0.2s;
    }
    .user-menu a:hover { opacity: 0.9; }

    .container {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }
    form {
      background: #fff;
      padding: 2rem;
      width: 100%;
      max-width: 400px;
      border-radius: 12px;
      box-shadow: 0 6px 16px rgba(0,0,0,0.1);
      border: 1px solid var(--border);
      text-align: center;
    }
    form h2 { margin-bottom: 1rem; color: var(--primary-dark); }
    input {
      width: 100%;
      padding: 12px; margin-top: 12px;
      border: 1px solid #ccc; border-radius: 6px;
      font-size: 0.95rem; transition: border-color 0.3s;
      font-family: 'Montserrat', sans-serif;
    }
    input:focus { border-color: var(--primary); outline: none; }
    .link-recuperar {
      display: block; margin-top: 8px; margin-bottom: 12px; font-size: 0.9rem;
    }
    .link-recuperar a { color: var(--primary); text-decoration: none; }
    .btn {
      width: 100%; padding: 12px; margin-top: 10px; border: none; border-radius: 8px;
      font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s ease;
      font-family: 'Montserrat', sans-serif;
    }
    .btn-primary { background-color: var(--primary); color: #fff; }
    .btn-primary:hover { background-color: var(--primary-dark); }
    .btn-outline {
      background: transparent; color: var(--primary-dark); border: 2px solid var(--primary-dark);
    }
    .btn-outline:hover { background: var(--primary-dark); color: #fff; }
    #mensagem {
      margin-top: 10px; color: red; font-size: 0.9rem; min-height: 1.2rem;
    }
  </style>
</head>
<body>

<header>
  <div class="logo">🐄 <span>BovinTrade</span></div>
  <div class="user-menu">
    <a href="index.php">Início</a>
    <a href="cadastro-geral.php">Cadastre-se</a>
  </div>
</header>

<div class="container">
  <form id="loginForm" method="post" action="">
    <h2>Login</h2>

    <?php if (!empty($erro)): ?>
      <div id="mensagem"><?php echo htmlspecialchars($erro); ?></div>
    <?php else: ?>
      <div id="mensagem"></div>
    <?php endif; ?>

    <input type="email" name="email" id="email" placeholder="Email" required>
    <input type="password" name="senha" id="senha" placeholder="Senha" required>

    <div class="link-recuperar">
      <a href="recuperar-senha.php">Esqueceu a senha?</a>
    </div>

    <button type="submit" class="btn btn-primary">Entrar</button>
    <button id="voltarBtn" type="button" class="btn btn-outline" onclick="window.history.back()">← Voltar</button>
  </form>
</div>

</body>
</html>
