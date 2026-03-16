<?php
// Bovintrade-PHP/Projeto-Bovintrade-2/cadastro-transportadora.php
require_once 'config.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- coleta e normalização (sempre sanitizar no back) ---
        $nome_razao        = trim($_POST['nome_razao'] ?? '');
        $tipo_transportador= strtoupper(trim($_POST['tipo_transportador'] ?? '')); // PF ou PJ
        $cpf_raw           = trim($_POST['cpf'] ?? '');
        $cnpj_raw          = trim($_POST['cnpj'] ?? '');
        $cpf               = preg_replace('/\D+/', '', $cpf_raw);
        $cnpj              = preg_replace('/\D+/', '', $cnpj_raw);
        $email             = strtolower(trim($_POST['email'] ?? ''));
        $telefone_raw      = trim($_POST['telefone'] ?? '');
        $telefone_digits   = preg_replace('/\D+/', '', $telefone_raw);

        $cep               = preg_replace('/\D+/', '', $_POST['cep'] ?? '');
        $cidade            = trim($_POST['cidade'] ?? '');
        $estado            = strtoupper(trim($_POST['estado'] ?? ''));

        // opcionais
        $bairro            = trim($_POST['bairro'] ?? '') ?: null;
        $rua               = trim($_POST['rua'] ?? '') ?: null;
        $numero            = trim($_POST['numero'] ?? '') ?: null;
        $complemento       = trim($_POST['complemento'] ?? '') ?: null;

        // geolocalização (opcional p/ transportadora)
        $latitude_str      = trim($_POST['latitude'] ?? '');
        $longitude_str     = trim($_POST['longitude'] ?? '');
        $latitude          = ($latitude_str !== '') ? (float)$latitude_str : null;
        $longitude         = ($longitude_str !== '') ? (float)$longitude_str : null;

        // senha
        $senha             = (string)($_POST['senha'] ?? '');
        $confirmar_senha   = (string)($_POST['confirmar_senha'] ?? '');

        // --- validações mínimas ---
        if ($nome_razao === '' || $email === '' || $telefone_raw === '' || $cep === '' || $cidade === '' || $estado === '' || $tipo_transportador === '' || $senha === '' || $confirmar_senha === '') {
            throw new Exception('Preencha todos os campos obrigatórios marcados com *.');
        }
        if (!in_array($tipo_transportador, ['PF','PJ'], true)) {
            throw new Exception('Tipo de transportador inválido.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail inválido.');
        }
        if (strlen($estado) !== 2) {
            throw new Exception('UF deve ter 2 caracteres (ex.: SP, PR).');
        }
        // regras PF/PJ
        if ($tipo_transportador === 'PF') {
            if ($cpf === '' || !preg_match('/^\d{11}$/', $cpf)) {
                throw new Exception('Para PF, informe um CPF válido (11 dígitos).');
            }
            $cnpj = null; // garante nulo
        } else { // PJ
            if ($cnpj === '' || !preg_match('/^\d{14}$/', $cnpj)) {
                throw new Exception('Para PJ, informe um CNPJ válido (14 dígitos).');
            }
            $cpf = null; // garante nulo
        }
        if (strlen($senha) < 8) {
            throw new Exception('A senha deve ter pelo menos 8 caracteres.');
        }
        if ($senha !== $confirmar_senha) {
            throw new Exception('As senhas não conferem.');
        }

        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // --- transação: usuarios -> transportadora ---
        $pdo->beginTransaction();

        $sqlUsuario = "INSERT INTO usuarios
            (tipo_usuario, nome_razao, email, telefone, senha_hash, cnpj, cpf,
             cep, cidade, estado, bairro, rua, numero, complemento,
             latitude, longitude)
            VALUES
            ('TRANSPORTADORA', :nome_razao, :email, :telefone, :senha_hash, :cnpj, :cpf,
             :cep, :cidade, :estado, :bairro, :rua, :numero, :complemento,
             :latitude, :longitude)";
        $st = $pdo->prepare($sqlUsuario);
        $st->execute([
            ':nome_razao'  => $nome_razao,
            ':email'       => $email,
            ':telefone'    => $telefone_raw, // se quiser só dígitos, use $telefone_digits
            ':senha_hash'  => $senha_hash,
            ':cnpj'        => $cnpj,
            ':cpf'         => $cpf,
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

        $sqlTransp = "INSERT INTO transportadora (usuario_id, tipo_transportador)
                      VALUES (:usuario_id, :tipo_transportador)";
        $st2 = $pdo->prepare($sqlTransp);
        $st2->execute([
            ':usuario_id'        => $usuarioId,
            ':tipo_transportador'=> $tipo_transportador,
        ]);

        $pdo->commit();

        // redireciona para login
        header('Location: login.php');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $msg = $e->getMessage();
        if (str_contains($msg, 'uq_usuarios_email')) $msg = 'Este e-mail já está cadastrado.';
        if (str_contains($msg, 'uq_usuarios_cnpj'))  $msg = 'Este CNPJ já está cadastrado.';
        if (str_contains($msg, 'uq_usuarios_cpf'))   $msg = 'Este CPF já está cadastrado.';
        $erro = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Cadastro - Transportadora</title>
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
    input, select, button {
      padding: 12px; font-size: 1rem; border-radius: 10px; border: 2px solid #8B0000;
      outline: none; font-family: 'Poppins', sans-serif; width: 100%; background: #fff;
    }
    input:focus, select:focus { border-color: #000; }
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
  <h1>Cadastro - Transportadora</h1>

  <?php if ($erro): ?>
    <div class="alert-erro"><?php echo htmlspecialchars($erro); ?></div>
  <?php endif; ?>

  <form id="formCadastro" method="post" action="">
    <!-- Identificação -->
    <label for="nome_razao">Razão Social / Nome (PF)*</label>
    <input type="text" id="nome_razao" name="nome_razao" placeholder="Razão social (PJ) ou nome completo (PF)" required value="<?= htmlspecialchars($_POST['nome_razao'] ?? '') ?>">

    <div class="grid-2">
      <div>
        <label for="tipo_transportador">Tipo de Transportador*</label>
        <select id="tipo_transportador" name="tipo_transportador" required>
          <option value="" disabled selected>Selecione</option>
          <?php $tipo_val = $_POST['tipo_transportador'] ?? ''; ?>
          <option value="PF" <?= $tipo_val === 'PF' ? 'selected' : '' ?>>Pessoa Física (PF)</option>
          <option value="PJ" <?= $tipo_val === 'PJ' ? 'selected' : '' ?>>Pessoa Jurídica (PJ)</option>
        </select>
      </div>
      <div>
        <!-- Campo dinâmico CPF/CNPJ -->
        <div id="grupo_cpf" style="display:none;">
          <label for="cpf">CPF* (000.000.000-00)</label>
          <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>">
        </div>
        <div id="grupo_cnpj" style="display:none;">
          <label for="cnpj">CNPJ* (00.000.000/0000-00)</label>
          <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div>
        <label for="email">E-mail*</label>
        <input type="email" id="email" name="email" placeholder="contato@transportadora.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div>
        <label for="telefone">Telefone* (formato (99) 99999-9999)</label>
        <input type="text" id="telefone" name="telefone" placeholder="(99) 99999-9999" required value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
      </div>
    </div>

    <!-- Endereço -->
    <label for="cep">CEP*</label>
    <input type="text" id="cep" name="cep" placeholder="00000000" pattern="\d{8}" title="Digite 8 números" required value="<?= htmlspecialchars($_POST['cep'] ?? '') ?>">
    <span class="help">Ao sair do CEP, cidade/estado/bairro/rua podem ser preenchidos automaticamente.</span>

    <div class="grid-2">
      <div>
        <label for="cidade">Cidade*</label>
        <input type="text" id="cidade" name="cidade" required value="<?= htmlspecialchars($_POST['cidade'] ?? '') ?>">
      </div>
      <div>
        <label for="estado">UF*</label>
        <input type="text" id="estado" name="estado" maxlength="2" required value="<?= htmlspecialchars($_POST['estado'] ?? '') ?>">
      </div>
    </div>

    <div class="grid-2">
      <div><label for="bairro">Bairro</label><input type="text" id="bairro" name="bairro" value="<?= htmlspecialchars($_POST['bairro'] ?? '') ?>"></div>
      <div><label for="rua">Rua</label><input type="text" id="rua" name="rua" value="<?= htmlspecialchars($_POST['rua'] ?? '') ?>"></div>
    </div>

    <div class="grid-2">
      <div><label for="numero">Número</label><input type="text" id="numero" name="numero" value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>"></div>
      <div><label for="complemento">Complemento</label><input type="text" id="complemento" name="complemento" placeholder="Opcional" value="<?= htmlspecialchars($_POST['complemento'] ?? '') ?>"></div>
    </div>

    <!-- Geolocalização (opcional) -->
    <div class="localizacao-container">
      <input type="text" id="localizacao" placeholder="Clique para obter localização (opcional)" readonly value="<?= (isset($_POST['latitude']) && isset($_POST['longitude'])) ? "Lat: " . htmlspecialchars($_POST['latitude']) . ", Lng: " . htmlspecialchars($_POST['longitude']) : "" ?>">
      <button type="button" id="btnGeo">Obter</button>
    </div>
    <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
    <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">

    <!-- Senha (final) -->
    <div class="grid-2">
      <div><label for="senha">Senha* (mín. 8)</label><input type="password" id="senha" name="senha" minlength="8" required></div>
      <div><label for="confirmar_senha">Confirmar Senha*</label><input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" required></div>
    </div>

    <button type="submit" id="botaoSubmit">Cadastrar</button>
  </form>

  <a href="cadastro-geral.php" class="btn-voltar">⟵ Voltar</a>
</div>

<script>
  const tipoSelect = document.getElementById('tipo_transportador');
  const grupoCPF = document.getElementById('grupo_cpf');
  const grupoCNPJ = document.getElementById('grupo_cnpj');
  const cpfInput = document.getElementById('cpf');
  const cnpjInput = document.getElementById('cnpj');

  function togglePF_PJ() {
    const tipo = tipoSelect.value;
    if (tipo === 'PF') {
      grupoCPF.style.display = 'block';
      grupoCNPJ.style.display = 'none';
      cpfInput.required = true;
      cnpjInput.required = false;
      cnpjInput.value = '';
    } else if (tipo === 'PJ') {
      grupoCPF.style.display = 'none';
      grupoCNPJ.style.display = 'block';
      cpfInput.required = false;
      cnpjInput.required = true;
      cpfInput.value = '';
    } else {
      grupoCPF.style.display = 'none';
      grupoCNPJ.style.display = 'none';
      cpfInput.required = false;
      cnpjInput.required = false;
      cpfInput.value = '';
      cnpjInput.value = '';
    }
  }
  tipoSelect.addEventListener('change', togglePF_PJ);

  // Máscara CPF: 000.000.000-00
  if (cpfInput) {
    cpfInput.addEventListener('input', function() {
      let v = this.value.replace(/\D/g,'').slice(0,11);
      if (v.length > 3) v = v.replace(/^(\d{3})(\d)/, '$1.$2');
      if (v.length > 6) v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
      if (v.length > 9) v = v.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
      this.value = v;
    });
  }

  // Máscara CNPJ: 00.000.000/0000-00
  if (cnpjInput) {
    cnpjInput.addEventListener('input', function() {
      let v = this.value.replace(/\D/g,'').slice(0,14);
      if (v.length >= 3)  v = v.replace(/^(\d{2})(\d)/, '$1.$2');
      if (v.length >= 7)  v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
      if (v.length >= 11) v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4');
      if (v.length >= 14) v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
      this.value = v;
    });
  }

  // Máscara Telefone: (00) 00000-0000
  const telInput = document.getElementById('telefone');
  telInput.addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 2)  v = v.replace(/^(\d{2})(\d)/, '($1) $2');
    if (v.length > 7)  v = v.replace(/(\(\d{2}\)\s)(\d{4,5})(\d{1,4})$/, function(_, p1, p2, p3){ return p1 + p2 + '-' + p3; });
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

  // Geolocalização (opcional)
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
    const tipo = tipoSelect.value;
    if (tipo === 'PF' && !cpfInput.value.trim()) { e.preventDefault(); alert('Informe o CPF.'); return; }
    if (tipo === 'PJ' && !cnpjInput.value.trim()) { e.preventDefault(); alert('Informe o CNPJ.'); return; }

    const s1 = document.getElementById('senha').value;
    const s2 = document.getElementById('confirmar_senha').value;
    if (s1.length < 8) { e.preventDefault(); alert('A senha deve ter pelo menos 8 caracteres.'); return; }
    if (s1 !== s2) { e.preventDefault(); alert('As senhas não conferem.'); return; }
  });

  // Inicializa estado dos campos dinâmicos
  togglePF_PJ();
</script>
</body>
</html>