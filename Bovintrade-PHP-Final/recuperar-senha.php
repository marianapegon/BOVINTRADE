<?php
// recuperar-senha.php (versão mysqli)
session_start();
require_once 'conexao.php'; // deve definir $conn = new mysqli(...);
require_once 'vendor/autoload.php'; // PHPMailer via Composer

// URL base fixa do projeto (AJUSTE em produção)
const BASE_URL = 'http://localhost/Bovintrade-PHP/Bovintrade-PHP2/Bovintrade-PHP2/';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function gerarTokenSeguro($bytes = 32) {
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Resposta genérica SEMPRE (anti-enumeração)
    $mensagem = "Se o e-mail informado existir, você receberá um link para redefinir a senha.";

    if ($email !== '') {
        // 1) Buscar usuário por e-mail (mysqli)
        $sql = "SELECT id, nome_razao FROM usuarios WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();

            // get_result (se mysqlnd) ou bind_result (fallback)
            $usuario = null;
            if (method_exists($stmt, 'get_result')) {
                $res = $stmt->get_result();
                $usuario = $res ? $res->fetch_assoc() : null;
            } else {
                $stmt->bind_result($id, $nome_razao);
                if ($stmt->fetch()) {
                    $usuario = ['id' => $id, 'nome_razao' => $nome_razao];
                }
            }
            $stmt->close();

            if ($usuario) {
                // 2) Gerar token e salvar hash
                $tokenRaw  = gerarTokenSeguro(32);
                $tokenHash = hash('sha256', $tokenRaw);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // +1h

                $sqlIns = "INSERT INTO password_reset_tokens
                           (usuario_id, token_hash, expires_at, request_ip, user_agent)
                           VALUES (?, ?, ?, INET6_ATON(?), ?)";
                if ($ins = $conn->prepare($sqlIns)) {
                    $ip  = $_SERVER['REMOTE_ADDR']     ?? null;
                    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    $ins->bind_param('issss', $usuario['id'], $tokenHash, $expiresAt, $ip, $ua);
                    $ins->execute();
                    $ins->close();
                }

                // 3) Montar link (AGORA COM / GARANTIDO)
                $base = rtrim(BASE_URL, '/');
                $link = $base . '/resetar-senha.php?uid=' . $usuario['id'] . '&token=' . urlencode($tokenRaw);

                // 4) Enviar e-mail via Brevo (Sendinblue)
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp-relay.brevo.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = '998eb9001@smtp-brevo.com';  // Login Brevo
                    $mail->Password   = 'sj98z3E2mpL0FVUS';          // Senha/API Key
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    // Remetente verificado no Brevo
                    $mail->setFrom('bovintrade@hotmail.com', 'BovinTrade Suporte');
                    $mail->addAddress($email, $usuario['nome_razao']);
                    $mail->Subject = 'Redefinição de senha - BovinTrade';
                    $mail->isHTML(false);
                    $mail->Body =
                        "Olá, {$usuario['nome_razao']},\n\n" .
                        "Recebemos uma solicitação para redefinir sua senha.\n" .
                        "Clique no link abaixo (válido por 1 hora):\n\n{$link}\n\n" .
                        "Se não foi você, ignore este e-mail.";

                    // $mail->SMTPDebug = 2; // habilite se precisar diagnosticar
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Erro ao enviar e-mail de recuperação: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Recuperar Senha - BovinTrade</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&family=Poppins:wght@300;500;600&display=swap" rel="stylesheet" />
  <style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Poppins',sans-serif;background:linear-gradient(to bottom,#f2f1ee,#e8e6e1);min-height:100vh;display:flex;flex-direction:column;color:#000;}
    .recovery-container{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,0.08);width:100%;max-width:450px;margin:auto;padding:2.5rem;border:1px solid #dedbd2;display:flex;flex-direction:column;}
    .recovery-header{text-align:center;margin-bottom:2rem;}
    .recovery-header h1{font-family:'Lora',serif;color:#8B0000;font-weight:700;font-size:2.2rem;}
    form{display:flex;flex-direction:column;gap:1.5rem;}
    .input-group{display:flex;flex-direction:column;gap:0.5rem;}
    label{font-weight:500;font-size:0.95rem;color:#000;}
    input{padding:12px 14px;border:1px solid #ccc;border-radius:8px;font-size:1rem;transition:border-color .3s;width:100%;color:#000;}
    input:focus{border-color:#8B0000;outline:none;}
    button{background:transparent;color:#000;border:2px solid #8B0000;padding:14px;border-radius:8px;font-weight:600;font-size:1.1rem;transition:all .3s;cursor:pointer;margin-top:.5rem;}
    button:hover{background:#8B0000;color:#fff;}
    .back-link{margin-top:1.5rem;text-align:center;font-size:0.95rem;}
    .back-link a{color:#8B0000;text-decoration:none;font-weight:600;}
    .back-link a:hover{text-decoration:underline;}
  </style>
</head>
<body>
  <div class="recovery-container">
    <div class="recovery-header">
      <h1>Recuperar senha</h1>
    </div>

    <?php if (!empty($mensagem)): ?>
      <p style="color:#8B0000; text-align:center; font-weight:600; margin-bottom:1rem;">
        <?= htmlspecialchars($mensagem) ?>
      </p>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="input-group">
        <label for="email">Informe seu e-mail</label>
        <input type="email" id="email" name="email" required placeholder="Seu e-mail cadastrado" />
      </div>

      <button type="submit">Enviar link</button>
    </form>

    <div class="back-link">
      <a href="login.php">Voltar ao login</a>
    </div>
  </div>
</body>
</html>
