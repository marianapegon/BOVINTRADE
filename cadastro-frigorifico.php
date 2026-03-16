<?php
// Bovintrade-PHP/Projeto-Bovintrade-2/cadastro-frigorifico.php
require_once 'config.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- Coleta e normalização ---
        $razao_social      = trim($_POST['razao_social'] ?? '');
        $cnpj_raw          = trim($_POST['cnpj'] ?? '');                 // com máscara
        $cnpj              = preg_replace('/\D+/', '', $cnpj_raw);       // só dígitos (14)
        $email             = strtolower(trim($_POST['email'] ?? ''));    // normaliza
        $telefone_raw      = trim($_POST['telefone'] ?? '');
        $telefone_digits   = preg_replace('/\D+/', '', $telefone_raw);   // só dígitos
        $cep               = preg_replace('/\D+/', '', $_POST['cep'] ?? '');
        $cidade            = trim($_POST['cidade'] ?? '');
        $estado            = strtoupper(trim($_POST['estado'] ?? ''));
        $bairro            = trim($_POST['bairro'] ?? '') ?: null;
        $rua               = trim($_POST['rua'] ?? '') ?: null;
        $numero            = trim($_POST['numero'] ?? '') ?: null;
        $complemento       = trim($_POST['complemento'] ?? '') ?: null;

        $responsavel_legal = trim($_POST['responsavel_legal'] ?? '');
        $cpf_resp_raw      = trim($_POST['cpf_responsavel'] ?? '');
        $cpf_responsavel   = preg_replace('/\D+/', '', $cpf_resp_raw);   // 11 dígitos
        $cargo_responsavel = trim($_POST['cargo_responsavel'] ?? '') ?: null;

        $latitude_str      = trim($_POST['latitude'] ?? '');
        $longitude_str     = trim($_POST['longitude'] ?? '');
        $latitude          = ($latitude_str !== '') ? (float)$latitude_str : null;
        $longitude         = ($longitude_str !== '') ? (float)$longitude_str : null;

        $senha             = (string)($_POST['senha'] ?? '');
        $confirmar_senha   = (string)($_POST['confirmar_senha'] ?? '');

        // --- Validações ---
        if ($razao_social === '' || $email === '' || $telefone_raw === '' ||
            $cep === '' || $cidade === '' || $estado === '' || $cnpj === '' ||
            $responsavel_legal === '' || $cpf_responsavel === '' ||
            $senha === '' || $confirmar_senha === '') {
            throw new Exception('Preencha todos os campos obrigatórios marcados com *.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail inválido.');
        }
        if (!preg_match('/^\d{14}$/', $cnpj)) {
            throw new Exception('CNPJ deve conter 14 dígitos (somente números).');
        }
        if (!preg_match('/^\d{11}$/', $cpf_responsavel)) {
            throw new Exception('CPF do responsável deve conter 11 dígitos (somente números).');
        }
        if (strlen($estado) !== 2) {
            throw new Exception('UF deve ter 2 caracteres (ex.: SP, PR).');
        }
        if ($latitude === null || $longitude === null) {
            throw new Exception('Geolocalização é obrigatória para frigorífico. Clique em "Obter".');
        }
        if (strlen($senha) < 8) {
            throw new Exception('A senha deve ter pelo menos 8 caracteres.');
        }
        if ($senha !== $confirmar_senha) {
            throw new Exception('As senhas não conferem.');
        }

        // --- Hash da senha ---
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // --- Transação: usuarios -> frigorifico ---
        $pdo->beginTransaction();

        $sqlUsuario = "INSERT INTO usuarios
            (tipo_usuario, nome_razao, email, telefone, senha_hash, cnpj, cpf,
             cep, cidade, estado, bairro, rua, numero, complemento,
             latitude, longitude)
            VALUES
            ('FRIGORIFICO', :nome_razao, :email, :telefone, :senha_hash, :cnpj, NULL,
             :cep, :cidade, :estado, :bairro, :rua, :numero, :complemento,
             :latitude, :longitude)";
        $st = $pdo->prepare($sqlUsuario);
        $st->execute([
            ':nome_razao'  => $razao_social,
            ':email'       => $email,
            ':telefone'    => $telefone_raw, // se preferir só dígitos, use $telefone_digits
            ':senha_hash'  => $senha_hash,
            ':cnpj'        => $cnpj,
            ':cep'         => $cep,
            ':cidade'      => $cidade,
            ':estado'      => $estado,
            ':bairro'      => $bairro,
            ':rua'         => $rua,
            ':numero'      => $numero,
            ':complemento' => $complemento,
            ':latitude'    => $latitude,
            ':longitude'   => $longitude,
        ]);

        $usuarioId = (int)$pdo->lastInsertId();

        $sqlFrig = "INSERT INTO frigorifico
            (usuario_id, responsavel_legal, cpf_responsavel, cargo_responsavel)
            VALUES
            (:usuario_id, :responsavel_legal, :cpf_responsavel, :cargo_responsavel)";
        $st2 = $pdo->prepare($sqlFrig);
        $st2->execute([
            ':usuario_id'        => $usuarioId,
            ':responsavel_legal' => $responsavel_legal,
            ':cpf_responsavel'   => $cpf_responsavel,
            ':cargo_responsavel' => $cargo_responsavel,
        ]);

        $pdo->commit();

        // Redireciona para login
        header('Location: login.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $msg = $e->getMessage();
        if (str_contains($msg, 'uq_usuarios_email')) $msg = 'Este e-mail já está cadastrado.';
        if (str_contains($msg, 'uq_usuarios_cnpj'))  $msg = 'Este CNPJ já está cadastrado.';
        $erro = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Cadastro - Frigorífico</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0; font-family: 'Poppins', sans-serif;
      background: linear-gradient(to bottom right, #f2f1ee, #e8e6e1);
      min-height: 100vh; display: flex; justify-content: center; align-items: center; color: #000;
    }
    .container {
      background-color: rgba(255, 255, 255, 0.95);
      border-radius: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.15);
      padding: 40px; width: 90%; max-width: 680px;
    }
    h1 { font-family: 'Lora', serif; color: #8B0000; font-size: 2rem; margin: 0 0 20px 0; text-align: center; }
    form { display: flex; flex-direction: column; gap: 14px; }
    label { font-family: 'Lora', serif; font-weight: 600; font-size: 0.95rem; }
    input, button {
      padding: 12px; font-size: 1rem; border-radius: 10px; border: 2px solid #8B0000;
      outline: none; font-family: 'Poppins', sans-serif; width: 100%; background: #fff;
    }
    input:focus { border-color: #000; }
    .grid-2 { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 560px){ .grid-2 { grid-template-columns: 1fr; } }
    button { background-color: #8B0000; color: #fff; font-weight: bold; cursor: pointer; transition: 0.3s; }
    button:hover { background-color: #600000; }
    .btn-voltar { display: inline-block; margin-top: 16px; font-size: 1rem; text-decoration: none; color: #8B0000; font-weight: bold; transition: 0.3s; text-align: center; width: 100%; }
    .btn-voltar:hover { color: #000; }
    .localizacao-container { display: flex; gap: 10px; align-items: center; }
    .localizacao-container input { flex-grow: 1; }
    .help { font-size: 0.85rem; color: #333; margin-top: -6px; }
    .alert-erro { background:#fdecea; color:#a30000; border:1px solid #f5c2c0; padding:10px 12px; border-radius:8px; margin-bottom:8px; }
  </style>
</head>
<body>
<div class="container">
  <h1>Cadastro - Frigorífico</h1>

  <?php if ($erro): ?>
    <div class="alert-erro"><?php echo htmlspecialchars($erro); ?></div>
  <?php endif; ?>

  <form id="formCadastro" method="post" action="">
    <!-- Identificação -->
    <label for="razao_social">Razão Social*</label>
    <input type="text" id="razao_social" name="razao_social" placeholder="Razão social do frigorífico" required>

    <div class="grid-2">
      <div>
        <label for="cnpj">CNPJ* (00.000.000/0000-00)</label>
        <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required>
      </div>
      <div>
        <label for="email">E-mail*</label>
        <input type="email" id="email" name="email" placeholder="contato@frigorifico.com" required>
      </div>
    </div>

    <label for="telefone">Telefone* (formato (99) 99999-9999)</label>
    <input type="text" id="telefone" name="telefone" placeholder="(99) 99999-9999" required>

    <!-- Endereço -->
    <label for="cep">CEP*</label>
    <input type="text" id="cep" name="cep" placeholder="00000000" pattern="\d{8}" title="Digite 8 números" required>
    <span class="help">Ao sair do CEP, cidade/estado/bairro/rua podem ser preenchidos automaticamente.</span>

    <div class="grid-2">
      <div>
        <label for="cidade">Cidade*</label>
        <input type="text" id="cidade" name="cidade" required>
      </div>
      <div>
        <label for="estado">UF*</label>
        <input type="text" id="estado" name="estado" maxlength="2" required>
      </div>
    </div>

    <div class="grid-2">
      <div><label for="bairro">Bairro</label><input type="text" id="bairro" name="bairro"></div>
      <div><label for="rua">Rua</label><input type="text" id="rua" name="rua"></div>
    </div>

    <div class="grid-2">
      <div><label for="numero">Número</label><input type="text" id="numero" name="numero"></div>
      <div><label for="complemento">Complemento</label><input type="text" id="complemento" name="complemento" placeholder="Opcional"></div>
    </div>

    <!-- Responsável -->
    <div class="grid-2">
      <div>
        <label for="responsavel_legal">Responsável Legal*</label>
        <input type="text" id="responsavel_legal" name="responsavel_legal" required>
      </div>
      <div>
        <label for="cpf_responsavel">CPF do Responsável* (000.000.000-00)</label>
        <input type="text" id="cpf_responsavel" name="cpf_responsavel" placeholder="000.000.000-00" required>
      </div>
    </div>

    <label for="cargo_responsavel">Cargo</label>
    <input type="text" id="cargo_responsavel" name="cargo_responsavel" placeholder="Opcional">

    <!-- Geolocalização -->
    <div class="localizacao-container">
      <input type="text" id="localizacao" placeholder="Clique para obter localização*" readonly required>
      <button type="button" id="btnGeo">Obter</button>
    </div>
    <input type="hidden" id="latitude" name="latitude">
    <input type="hidden" id="longitude" name="longitude">

    <!-- Senha (final) -->
    <div class="grid-2">
      <div><label for="senha">Senha* (mín. 8)</label><input type="password" id="senha" name="senha" minlength="8" required></div>
      <div><label for="confirmar_senha">Confirmar Senha*</label><input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" required></div>
    </div>

    <button type="submit">Cadastrar</button>
  </form>

  <a href="cadastro-geral.php" class="btn-voltar">⟵ Voltar</a>
</div>

<script>
  // Máscara CNPJ
  document.getElementById('cnpj').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,14);
    if (v.length >= 3)  v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    if (v.length >= 7)  v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    if (v.length >= 11) v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4');
    if (v.length >= 16) v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
    this.value = v;
  });

  // Máscara Telefone
  document.getElementById('telefone').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 2)  v = v.replace(/^(\d{2})(\d)/, '($1) $2');
    if (v.length > 7)  v = v.replace(/(\(\d{2}\)\s)(\d{4,5})(\d{1,4})$/, function(_, p1, p2, p3){ return p1 + p2 + '-' + p3; });
    this.value = v;
  });

  // Máscara CPF
  document.getElementById('cpf_responsavel').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 3) v = v.replace(/^(\d{3})(\d)/, '$1.$2');
    if (v.length > 6) v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
    if (v.length > 9) v = v.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
    this.value = v;
  });

  // ViaCEP
  document.getElementById('cep').addEventListener('blur', function(){
    const cep = this.value.replace(/\D/g,'');
    if(cep.length === 8){
      fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(r => r.json())
        .then(d => {
          if(!d.erro){
            document.getElementById('cidade').value = d.localidade || '';
            document.getElementById('estado').value = d.uf || '';
            document.getElementById('bairro').value = d.bairro || '';
            document.getElementById('rua').value = d.logradouro || '';
          }
        })
        .catch(()=>{});
    }
  });

  // Geolocalização (obrigatória)
  document.getElementById('btnGeo').addEventListener('click', function(){
    if(navigator.geolocation){
      navigator.geolocation.getCurrentPosition(
        function(pos){
          const lat = pos.coords.latitude.toFixed(6);
          const lng = pos.coords.longitude.toFixed(6);
          document.getElementById('latitude').value = lat;
          document.getElementById('longitude').value = lng;
          document.getElementById('localizacao').value = `Lat: ${lat}, Lng: ${lng}`;
        },
        function(){ alert('Não foi possível obter a localização. Autorize o acesso e tente novamente.'); }
      );
    } else { alert('Geolocalização não suportada.'); }
  });

  // Validações no cliente
  document.getElementById('formCadastro').addEventListener('submit', function(e){
    const s1 = document.getElementById('senha').value;
    const s2 = document.getElementById('confirmar_senha').value;
    if (s1.length < 8) { e.preventDefault(); alert('A senha deve ter pelo menos 8 caracteres.'); return; }
    if (s1 !== s2) { e.preventDefault(); alert('As senhas não conferem.'); return; }

    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    if(!lat || !lng){ e.preventDefault(); alert('Geolocalização é obrigatória. Clique em "Obter".'); }
  });
</script>
</body>
</html>
