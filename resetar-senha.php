<?php
// resetar-senha.php (mysqli)
session_start();
require_once 'conexao.php'; // $conn = new mysqli(...)

function limpar($v) { return trim($v ?? ''); }

// Busca token válido (não usado, não expirado) para o uid/tokenHash
function buscarTokenValido(mysqli $conn, int $uid, string $tokenHash): ?array {
    $sql = "SELECT prt.id AS prt_id, prt.usuario_id, prt.expires_at
            FROM password_reset_tokens prt
            WHERE prt.usuario_id = ?
              AND prt.token_hash = ?
              AND prt.used_at IS NULL
              AND prt.expires_at > NOW()
            LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('is', $uid, $tokenHash);
        $stmt->execute();
        $row = null;
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
        } else {
            $stmt->bind_result($prt_id, $usuario_id, $expires_at);
            if ($stmt->fetch()) {
                $row = ['prt_id' => $prt_id, 'usuario_id' => $usuario_id, 'expires_at' => $expires_at];
            }
        }
        $stmt->close();
        return $row ?: null;
    }
    return null;
}

$erro = '';
$sucesso = '';
$mostrarFormulario = false;
$uid   = 0;
$token = '';

// Fluxo GET: validar link e exibir formulário
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $uid   = (int)($_GET['uid'] ?? 0);
    $token = limpar($_GET['token'] ?? '');

    if ($uid <= 0 || $token === '') {
        $erro = 'Link inválido.';
    } else {
        $tokenHash = hash('sha256', $token);
        $valido = buscarTokenValido($conn, $uid, $tokenHash);
        if ($valido) {
            $mostrarFormulario = true; // exibe o form de nova senha
        } else {
            $erro = 'Link inválido ou expirado.';
        }
    }
}

// Fluxo POST: processar troca de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid        = (int)($_POST['uid'] ?? 0);
    $token      = limpar($_POST['token'] ?? '');
    $senha1     = limpar($_POST['senha1'] ?? '');
    $senha2     = limpar($_POST['senha2'] ?? '');

    if ($uid <= 0 || $token === '') {
        $erro = 'Requisição inválida.';
    } elseif ($senha1 === '' || $senha2 === '') {
        $erro = 'Informe e confirme a nova senha.';
    } elseif ($senha1 !== $senha2) {
        $erro = 'As senhas não conferem.';
    } elseif (strlen($senha1) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
    } else {
        // Validar token novamente
        $tokenHash = hash('sha256', $token);
        $valido = buscarTokenValido($conn, $uid, $tokenHash);

        if (!$valido) {
            $erro = 'Link inválido ou expirado.';
        } else {
            // 1) Atualizar senha do usuário
            $hash = password_hash($senha1, PASSWORD_DEFAULT);

            $sqlUp = "UPDATE usuarios SET senha_hash = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sqlUp)) {
                $stmt->bind_param('si', $hash, $uid);
                $stmt->execute();
                $stmt->close();
            }

            // 2) Marcar esse token como usado
            $sqlTok = "UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?";
            if ($stmt = $conn->prepare($sqlTok)) {
                $stmt->bind_param('i', $valido['prt_id']);
                $stmt->execute();
                $stmt->close();
            }

            // 3) (Opcional) invalidar outros tokens pendentes do usuário
            $sqlInv = "UPDATE password_reset_tokens SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL";
            if ($stmt = $conn->prepare($sqlInv)) {
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();
            }

            $sucesso = 'Senha redefinida com sucesso! Você já pode fazer login.';
        }
    }

    if ($erro !== '') {
        $mostrarFormulario = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Redefinir Senha - BovinTrade</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Poppins:wght@300;500;600&display=swap" rel="stylesheet" />
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Poppins',sans-serif;background:linear-gradient(to bottom,#f2f1ee,#e8e6e1);min-height:100vh;display:flex;flex-direction:column;color:#000;}
    .container{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,0.08);width:100%;max-width:520px;margin:auto;padding:2.5rem;border:1px solid #dedbd2;display:flex;flex-direction:column;}
    h1{font-family:'Lora',serif;color:#8B0000;font-weight:700;font-size:2.0rem;margin-bottom:1rem;text-align:center;}
    form{display:flex;flex-direction:column;gap:1rem;margin-top:1rem;}
    label{font-weight:500;font-size:0.95rem}
    input{padding:12px 14px;border:1px solid #ccc;border-radius:8px;font-size:1rem;transition:border-color .3s;width:100%;}
    input:focus{border-color:#8B0000;outline:none;}
    button{background:transparent;border:2px solid #8B0000;padding:14px;border-radius:8px;font-weight:600;font-size:1.1rem;transition:all .3s;cursor:pointer;margin-top:.5rem;}
    button:hover{background:#8B0000;color:#fff;}
    .msg{margin:10px 0;padding:10px 12px;border-radius:8px;font-size:0.95rem;}
    .erro{background:#ffe8e6;color:#7a0000;border:1px solid #f3c1bc;}
    .ok{background:#e8f7ea;color:#1c5d2f;border:1px solid #bfe3c8;}
    .back{margin-top:1.2rem;text-align:center}
    .back a{color:#8B0000;text-decoration:none;font-weight:600;}
    .back a:hover{text-decoration:underline;}
  </style>
</head>
<body>
  <div class="container">
    <h1>Redefinir senha</h1>

    <?php if ($erro): ?>
      <div class="msg erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
      <div class="msg ok"><?= htmlspecialchars($sucesso) ?></div>
      <div class="back"><a href="login.php">Ir para o login</a></div>
    <?php endif; ?>

    <?php if ($mostrarFormulario && !$sucesso): ?>
      <form method="POST" action="">
        <input type="hidden" name="uid" value="<?= (int)$uid ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div>
          <label for="senha1">Nova senha</label>
          <input type="password" id="senha1" name="senha1" required minlength="8" placeholder="Mínimo 8 caracteres">
        </div>

        <div>
          <label for="senha2">Confirmar nova senha</label>
          <input type="password" id="senha2" name="senha2" required minlength="8" placeholder="Repita a nova senha">
        </div>

        <button type="submit">Salvar nova senha</button>
      </form>
    <?php endif; ?>

    <?php if (!$mostrarFormulario && !$sucesso && !$erro): ?>
      <div class="msg erro">Link inválido.</div>
    <?php endif; ?>

    <div class="back" style="margin-top:16px;">
      <a href="login.php">Voltar ao login</a>
    </div>
  </div>
</body>
</html>
