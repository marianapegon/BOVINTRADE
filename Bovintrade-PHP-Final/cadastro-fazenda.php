<?php
// Bovintrade-PHP/Projeto-Bovintrade-2/cadastro-fazenda.php
// Inicializa a sessão (necessário para mensagens flash futuras, se for o caso)
session_start();
// ATENÇÃO: Verifique se este caminho está correto (um nível acima do diretório atual)
require_once 'config.php';
$erro = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Coleta e normaliza (sempre sanitizar no back)
        $nome_razao = trim($_POST['nome_razao'] ?? '');
        $cnpj_raw = trim($_POST['cnpj'] ?? '');
        $cnpj = preg_replace('/\D+/', '', $cnpj_raw); // 14 dígitos
        $email = trim($_POST['email'] ?? '');
        $telefone_raw = trim($_POST['telefone'] ?? '');
        $telefone_digits = preg_replace('/\D+/', '', $telefone_raw);
        $cep = preg_replace('/\D+/', '', $_POST['cep'] ?? '');
        $cidade = trim($_POST['cidade'] ?? '');
        $estado = strtoupper(trim($_POST['estado'] ?? ''));
        $bairro = trim($_POST['bairro'] ?? '') ?: null;
        $rua = trim($_POST['rua'] ?? '') ?: null;
        $numero = trim($_POST['numero'] ?? '') ?: null;
        $complemento = trim($_POST['complemento'] ?? '') ?: null;
        $sistema_criacao = trim($_POST['sistema_criacao'] ?? '');
        $sistema_outro = trim($_POST['sistema_criacao_outro'] ?? '');
        if ($sistema_criacao === 'OUTRO' && $sistema_outro !== '') {
            $sistema_criacao = $sistema_outro; // usa o texto customizado
        }
        $responsavel_legal = trim($_POST['responsavel_legal'] ?? '');
        $cpf_resp_raw = trim($_POST['cpf_responsavel'] ?? '');
        $cpf_responsavel = preg_replace('/\D+/', '', $cpf_resp_raw); // 11 dígitos
        $cargo_responsavel = trim($_POST['cargo_responsavel'] ?? '') ?: null;
        $latitude_str = trim($_POST['latitude'] ?? '');
        $longitude_str = trim($_POST['longitude'] ?? '');
        $latitude = ($latitude_str !== '') ? (float)$latitude_str : null;
        $longitude = ($longitude_str !== '') ? (float)$longitude_str : null;
        // Senha
        $senha = (string)($_POST['senha'] ?? '');
        $confirmar_senha = (string)($_POST['confirmar_senha'] ?? '');
        // --------------------------------------------------------------------------------
        // 1. VALIDAÇÕES
        // --------------------------------------------------------------------------------
        if ($nome_razao === '' || $email === '' || $telefone_raw === '' ||
            $cep === '' || $cidade === '' || $estado === '' || $cnpj === '' ||
            $sistema_criacao === '' || $responsavel_legal === '' || $cpf_responsavel === '' ||
            $senha === '' || $confirmar_senha === '') {
            throw new Exception('Preencha todos os campos obrigatórios marcados com *.');
        }
        if ($latitude === null || $longitude === null) {
            throw new Exception('Geolocalização é obrigatória para fazenda. Clique em "Obter".');
        }
        if (strlen($estado) !== 2) {
            throw new Exception('UF deve ter 2 caracteres (ex.: SP, PR).');
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
        if (strlen($senha) < 8) {
            throw new Exception('A senha deve ter pelo menos 8 caracteres.');
        }
        if ($senha !== $confirmar_senha) {
            throw new Exception('As senhas não conferem.');
        }
        // Gera hash da senha
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        // --------------------------------------------------------------------------------
        // 2. TRANSAÇÃO (INSERT no Banco de Dados)
        // --------------------------------------------------------------------------------
        $pdo->beginTransaction();
        // 2.1. INSERT em usuarios
        $sqlUsuario = "INSERT INTO usuarios
            (tipo_usuario, nome_razao, email, telefone, senha_hash, cnpj, cpf,
             cep, cidade, estado, bairro, rua, numero, complemento,
             latitude, longitude)
            VALUES
            ('FAZENDA', :nome_razao, :email, :telefone, :senha_hash, :cnpj, NULL,
             :cep, :cidade, :estado, :bairro, :rua, :numero, :complemento,
             :latitude, :longitude)";
        $st = $pdo->prepare($sqlUsuario);
        $st->execute([
            ':nome_razao' => $nome_razao,
            ':email' => $email,
            ':telefone' => $telefone_raw,
            ':senha_hash' => $senha_hash,
            ':cnpj' => $cnpj,
            ':cep' => $cep,
            ':cidade' => $cidade,
            ':estado' => $estado,
            ':bairro' => $bairro,
            ':rua' => $rua,
            ':numero' => $numero,
            ':complemento' => $complemento,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
        ]);
        $usuarioId = (int)$pdo->lastInsertId();
        // 2.2. INSERT em fazenda (filha)
        $sqlFazenda = "INSERT INTO fazenda
            (usuario_id, sistema_criacao, responsavel_legal, cpf_responsavel, cargo_responsavel)
            VALUES
            (:usuario_id, :sistema_criacao, :responsavel_legal, :cpf_responsavel, :cargo_responsavel)";
        $st2 = $pdo->prepare($sqlFazenda);
        $st2->execute([
            ':usuario_id' => $usuarioId,
            ':sistema_criacao' => $sistema_criacao,
            ':responsavel_legal' => $responsavel_legal,
            ':cpf_responsavel' => $cpf_responsavel,
            ':cargo_responsavel' => $cargo_responsavel,
        ]);
        $pdo->commit();
        // Redireciona para login após sucesso
        header('Location: login.php?msg=sucesso');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $msg = $e->getMessage();
        if (str_contains($msg, 'uq_usuarios_email')) $msg = 'Este e-mail já está cadastrado.';
        if (str_contains($msg, 'uq_usuarios_cnpj')) $msg = 'Este CNPJ já está cadastrado.';
        $erro = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Cadastro - Fazenda</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* CSS Existente */
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
        input[type="file"] {
            padding: 6px; /* Ajuste para o campo file */
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
    <h1>Cadastro - Fazenda</h1>
    <?php if ($erro): ?>
      <div class="alert-erro"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <form id="formCadastro" method="post" action="">
        <label for="nome_razao">Nome da Fazenda*</label>
        <input type="text" id="nome_razao" name="nome_razao" placeholder="Nome completo da fazenda" required value="<?= htmlspecialchars($_POST['nome_razao'] ?? '') ?>">
        <div class="grid-2">
            <div>
                <label for="cnpj">CNPJ* (00.000.000/0000-00)</label>
                <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required value="<?= htmlspecialchars($_POST['cnpj'] ?? '') ?>">
            </div>
            <div>
                <label for="email">E-mail*</label>
                <input type="email" id="email" name="email" placeholder="contato@fazenda.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="telefone">Telefone* (formato (99) 99999-9999)</label>
                <input type="text" id="telefone" name="telefone" placeholder="(99) 99999-9999" required value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
            </div>
            <div>
                <label for="sistema_criacao">Sistema de Criação*</label>
                <select id="sistema_criacao" name="sistema_criacao" required>
                    <option value="" disabled selected>Selecione</option>
                    <?php $sistema_val = $_POST['sistema_criacao'] ?? ''; ?>
                    <option value="INTENSIVO" <?= $sistema_val == 'INTENSIVO' ? 'selected' : '' ?>>Intensivo</option>
                    <option value="EXTENSIVO" <?= $sistema_val == 'EXTENSIVO' ? 'selected' : '' ?>>Extensivo</option>
                    <option value="SEMI-INTENSIVO" <?= $sistema_val == 'SEMI-INTENSIVO' ? 'selected' : '' ?>>Semi-intensivo</option>
                    <option value="CONFINAMENTO" <?= $sistema_val == 'CONFINAMENTO' ? 'selected' : '' ?>>Confinamento</option>
                    <option value="PASTAGEM" <?= $sistema_val == 'PASTAGEM' ? 'selected' : '' ?>>Pastagem</option>
                    <option value="MISTO" <?= $sistema_val == 'MISTO' ? 'selected' : '' ?>>Misto</option>
                    <option value="OUTRO" <?= $sistema_val == 'OUTRO' ? 'selected' : '' ?>>Outro</option>
                </select>
            </div>
        </div>
        <div id="grupo_outro" style="display:none;">
            <label for="sistema_criacao_outro">Descreva o sistema de criação*</label>
            <input type="text" id="sistema_criacao_outro" name="sistema_criacao_outro" placeholder="Ex.: Integração Lavoura-Pecuária, Compost Barn, etc." value="<?= htmlspecialchars($_POST['sistema_criacao_outro'] ?? '') ?>">
        </div>
        <label for="cep">CEP*</label>
        <input type="text" id="cep" name="cep" placeholder="00000000" pattern="\d{8}" title="Digite 8 números" required value="<?= htmlspecialchars($_POST['cep'] ?? '') ?>">
        <span class="help">Ao sair do campo CEP, cidade/estado/bairro/rua podem ser preenchidos automaticamente.</span>
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
            <div>
                <label for="bairro">Bairro</label>
                <input type="text" id="bairro" name="bairro" value="<?= htmlspecialchars($_POST['bairro'] ?? '') ?>">
            </div>
            <div>
                <label for="rua">Rua</label>
                <input type="text" id="rua" name="rua" value="<?= htmlspecialchars($_POST['rua'] ?? '') ?>">
            </div>
        </div>
        <div class="grid-2">
            <div>
                <label for="numero">Número</label>
                <input type="text" id="numero" name="numero" value="<?= htmlspecialchars($_POST['numero'] ?? '') ?>">
            </div>
            <div>
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento" placeholder="Opcional" value="<?= htmlspecialchars($_POST['complemento'] ?? '') ?>">
            </div>
        </div>
       
        <div class="grid-2">
            <div>
                <label for="responsavel_legal">Responsável Legal*</label>
                <input type="text" id="responsavel_legal" name="responsavel_legal" required value="<?= htmlspecialchars($_POST['responsavel_legal'] ?? '') ?>">
            </div>
            <div>
                <label for="cpf_responsavel">CPF do Responsável* (000.000.000-00)</label>
                <input type="text" id="cpf_responsavel" name="cpf_responsavel" placeholder="000.000.000-00" required value="<?= htmlspecialchars($_POST['cpf_responsavel'] ?? '') ?>">
            </div>
        </div>
        <label for="cargo_responsavel">Cargo</label>
        <input type="text" id="cargo_responsavel" name="cargo_responsavel" placeholder="Opcional" value="<?= htmlspecialchars($_POST['cargo_responsavel'] ?? '') ?>">
        <div class="localizacao-container">
            <input type="text" id="localizacao" placeholder="Clique para obter localização*" readonly required
                   value="<?= (isset($_POST['latitude']) && isset($_POST['longitude'])) ? "Lat: " . htmlspecialchars($_POST['latitude']) . ", Lng: " . htmlspecialchars($_POST['longitude']) : "" ?>">
            <button type="button" id="btnGeo">Obter</button>
        </div>
        <input type="hidden" id="latitude" name="latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '') ?>">
        <input type="hidden" id="longitude" name="longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '') ?>">
        <div class="grid-2">
            <div>
                <label for="senha">Senha* (mín. 8 caracteres)</label>
                <input type="password" id="senha" name="senha" minlength="8" required>
            </div>
            <div>
                <label for="confirmar_senha">Confirmar Senha*</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" required>
            </div>
        </div>
        <button type="submit" id="botaoSubmit">Cadastrar</button>
    </form>
    <a href="cadastro-geral.php" class="btn-voltar">⟵ Voltar</a>
</div>
<script>
    const form = document.getElementById('formCadastro');
    const sistemaSel = document.getElementById('sistema_criacao');
    const grupoOutro = document.getElementById('grupo_outro');
    const sistemaOutroInput = document.getElementById('sistema_criacao_outro');
    // Manter valores preenchidos em caso de erro
    function setInitialValues() {
        if (sistemaSel.value === 'OUTRO') {
            toggleOutro();
        }
    }
    // Mostrar/ocultar campo "OUTRO" do sistema de criação
    function toggleOutro(){
        if (sistemaSel.value === 'OUTRO') {
            grupoOutro.style.display = 'block';
            sistemaOutroInput.required = true;
        } else {
            grupoOutro.style.display = 'none';
            sistemaOutroInput.required = false;
            sistemaOutroInput.value = '';
        }
    }
    sistemaSel.addEventListener('change', toggleOutro);
    // Máscaras (CNPJ, Telefone, CPF) - código mantido
    const cnpjInput = document.getElementById('cnpj');
    cnpjInput.addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').slice(0,14);
        if (v.length >= 3)  v = v.replace(/^(\d{2})(\d)/, '$1.$2');
        if (v.length >= 7)  v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        if (v.length >= 11) v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4');
        if (v.length >= 16) v = v.replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
        this.value = v;
    });
    const telInput = document.getElementById('telefone');
    telInput.addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').slice(0,11);
        if (v.length > 2)  v = v.replace(/^(\d{2})(\d)/, '($1) $2');
        if (v.length > 7)  v = v.replace(/(\(\d{2}\)\s)(\d{4,5})(\d{1,4})$/, function(_, p1, p2, p3){ return p1 + p2 + '-' + p3; });
        this.value = v;
    });
    const cpfInput = document.getElementById('cpf_responsavel');
    cpfInput.addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'').slice(0,11);
        if (v.length > 3) v = v.replace(/^(\d{3})(\d)/, '$1.$2');
        if (v.length > 6) v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        if (v.length > 9) v = v.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
        this.value = v;
    });
    // ViaCEP - código mantido
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
    // Geolocalização (obrigatória) - código mantido
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
    form.addEventListener('submit', function(e){
        // Validação de sistema "Outro"
        if (sistemaSel.value === 'OUTRO' && !sistemaOutroInput.value.trim()){
            e.preventDefault();
            alert('Descreva o sistema de criação.');
            return;
        }
        // Validação de senhas
        const s1 = document.getElementById('senha').value;
        const s2 = document.getElementById('confirmar_senha').value;
        if (s1.length < 8) { e.preventDefault(); alert('A senha deve ter pelo menos 8 caracteres.'); return; }
        if (s1 !== s2) { e.preventDefault(); alert('As senhas não conferem.'); return; }
        // Validação de Geolocalização
        const lat = document.getElementById('latitude').value;
        const lng = document.getElementById('longitude').value;
        if(!lat || !lng){ e.preventDefault(); alert('Geolocalização é obrigatória para fazenda. Clique em "Obter".'); return; }
    });
    // inicializa visibilidade do "OUTRO" (caso o user volte a página ou o post falhe)
    toggleOutro();
</script>
</body>
</html>