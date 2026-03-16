<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php'; // Conexão PDO
// Proteção: só fazenda pode acessar
if (empty($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FAZENDA') {
    header("Location: login.php");
    exit;
}
$u = $_SESSION['usuario'];
$fazenda_id = $u['id'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$email = e($u['email'] ?? '');
$nome = e($u['nome_razao'] ?? '');
$msg = "";
// Página atual para sidebar
$current_page = 'agendar-transporte-f.php';
// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $pedido_id = $_POST['pedido_id'];
    $transportadora_id = $_POST['transportadora_id'];
    $motorista_id = $_POST['motorista_id'];
    $veiculo_id = $_POST['veiculo_id'];
    $data_retirada = $_POST['data_retirada'];
    $hora_retirada = $_POST['hora_retirada'];
    $distancia = $_POST['distancia'];
    $metodo_pagamento = $_POST['metodo_pagamento_transporte'];
    // Validação básica
    if (empty($pedido_id) || empty($transportadora_id) || empty($motorista_id) || empty($veiculo_id) || empty($data_retirada) || empty($hora_retirada) || empty($distancia) || empty($metodo_pagamento)) {
        $msg = "Erro: Todos os campos são obrigatórios.";
    } else {
        try {
            // 1. OBTEM A QUANTIDADE TOTAL DE BOIS (CAPACIDADE NECESSÁRIA) DO PEDIDO
            // ⚠️ CORRIGIDO: Usando 'lb.quantidade'
            $getCapacNec = $pdo->prepare("
                SELECT SUM(lb.quantidade) AS total_bois
                FROM pedido_itens pi
                INNER JOIN lote_bois lb ON lb.id = pi.lote_id
                WHERE pi.pedido_id = :pid AND pi.fazenda_id = :fid
            ");
            $getCapacNec->execute([':pid' => $pedido_id, ':fid' => $fazenda_id]);
            $capacidade_necessaria = (int)($getCapacNec->fetchColumn() ?? 0);
            if ($capacidade_necessaria === 0) {
                throw new Exception("Não foi possível determinar a quantidade de bois do pedido.");
            }
            // 2. OBTEM A CAPACIDADE DO VEÍCULO SELECIONADO
            $getCapacVeic = $pdo->prepare("SELECT capacidade_max FROM veiculo WHERE id = :vid");
            $getCapacVeic->execute([':vid' => $veiculo_id]);
            $capacidade_veiculo = (int)($getCapacVeic->fetchColumn() ?? 0);
            // 3. VERIFICAÇÃO DE CAPACIDADE (RESTRIÇÃO)
            if ($capacidade_veiculo < $capacidade_necessaria) {
                $msg = "Erro: O veículo selecionado (Capacidade: {$capacidade_veiculo} bois) não é suficiente para o lote (Necessário: {$capacidade_necessaria} bois). Por favor, selecione outro veículo.";
            } else {
                // PROCEDE COM O AGENDAMENTO SE A CAPACIDADE FOR SUFICIENTE
                // Obter frigorífico do pedido
                $getFrig = $pdo->prepare("SELECT frigorifico_id FROM pedidos WHERE id = :pid");
                $getFrig->execute([':pid' => $pedido_id]);
                $frig = $getFrig->fetch(PDO::FETCH_ASSOC);
                if (!$frig) throw new Exception("Pedido não encontrado.");
                $frigorifico_id = $frig['frigorifico_id'];
                // Insere o novo agendamento
                $stmt = $pdo->prepare("INSERT INTO transportes
                    (pedido_id, fazenda_id, frigorifico_id, transportadora_id, motorista_id, veiculo_id, data_retirada, hora_retirada, distancia_km, status, status_aceite, metodo_pagamento_transporte)
                    VALUES (:pedido_id, :fazenda_id, :frigorifico_id, :transportadora_id, :motorista_id, :veiculo_id, :data_retirada, :hora_retirada, :distancia_km, 'AGENDADO', 'PENDENTE', :metodo_pagamento)");
                $stmt->execute([
                    ':pedido_id' => $pedido_id,
                    ':fazenda_id' => $fazenda_id,
                    ':frigorifico_id' => $frigorifico_id,
                    ':transportadora_id' => $transportadora_id,
                    ':motorista_id' => $motorista_id,
                    ':veiculo_id' => $veiculo_id,
                    ':data_retirada' => $data_retirada,
                    ':hora_retirada' => $hora_retirada,
                    ':distancia_km' => $distancia,
                    ':metodo_pagamento' => $metodo_pagamento
                ]);
                // ID do transporte recém-criado
                $transporte_id = $pdo->lastInsertId();
                // === CRIA NOTIFICAÇÃO PARA A TRANSPORTADORA ===
                $titulo = "Nova Solicitação de Transporte";
                $mensagem = "A fazenda {$nome} agendou um transporte para retirada em {$data_retirada} às {$hora_retirada}. Distância: {$distancia} km. Método de pagamento: {$metodo_pagamento}.";
                $dados_json = json_encode([
                    'pedido_id' => $pedido_id,
                    'transporte_id' => $transporte_id,
                    'fazenda_nome' => $nome,
                    'data_retirada' => $data_retirada,
                    'hora_retirada' => $hora_retirada,
                    'distancia_km' => $distancia,
                    'metodo_pagamento' => $metodo_pagamento
                ], JSON_UNESCAPED_UNICODE);
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notificacoes
                    (usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id, created_at)
                    VALUES
                    (:usuario_id, 'SOLICITACAO_TRANSPORTE', :titulo, :mensagem, :dados_json, 'transportes', :transporte_id, NOW())
                ");
                $notif_stmt->execute([
                    ':usuario_id' => $transportadora_id,
                    ':titulo' => $titulo,
                    ':mensagem' => $mensagem,
                    ':dados_json' => $dados_json,
                    ':transporte_id' => $transporte_id
                ]);
                $msg = "Transporte agendado e notificação enviada à transportadora com sucesso!";
            }
        } catch (Throwable $e) {
            // Erro de chave duplicada (transporte já agendado)
            if ($e instanceof PDOException && $e->getCode() == '23000') {
                $checkStmt = $pdo->prepare("SELECT status, status_aceite FROM transportes WHERE pedido_id = :pid AND fazenda_id = :fid ORDER BY id DESC LIMIT 1");
                $checkStmt->execute([':pid' => $pedido_id, ':fid' => $fazenda_id]);
                $existingTransport = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($existingTransport) {
                    $statusAtual = $existingTransport['status'];
                    $aceiteAtual = $existingTransport['status_aceite'];
                    if ($aceiteAtual == 'RECUSADO' || $statusAtual == 'CANCELADO') {
                        $msg = "Erro: Um transporte para este pedido foi recusado/cancelado. Você pode tentar agendar novamente.";
                    } else {
                        $msg = "Erro: Já existe um transporte agendado ou em andamento para este pedido (Status: {$statusAtual}, Aceite: {$aceiteAtual}).";
                    }
                } else {
                    $msg = "Erro ao agendar. Pode já existir um agendamento para este pedido.";
                }
            } else {
                $msg = "Erro ao agendar: " . e($e->getMessage());
            }
        }
    }
}
// Consultar pedidos vendidos pela fazenda (pagamento aprovado)
$pedidos_stmt = $pdo->prepare("
    SELECT
        p.id AS pedido_id,
        GROUP_CONCAT(DISTINCT lb.codigo_lote ORDER BY lb.id SEPARATOR ', ') AS codigos_lotes,
        u.nome_razao AS nome_frigorifico
    FROM pedidos p
    INNER JOIN pedido_itens pi ON pi.pedido_id = p.id
    INNER JOIN lote_bois lb ON lb.id = pi.lote_id
    INNER JOIN usuarios u ON u.id = p.frigorifico_id
    INNER JOIN pagamentos pg ON pg.pedido_id = p.id
    WHERE pi.fazenda_id = :fid
      AND pg.status = 'APROVADO'
      AND p.status = 'PAGO'
      AND NOT EXISTS (
         SELECT 1 FROM transportes t
         WHERE t.pedido_id = p.id
           AND t.fazenda_id = pi.fazenda_id
           AND t.status NOT IN ('CANCELADO', 'RECUSADO')
           AND t.status_aceite != 'RECUSADO'
         LIMIT 1
      )
    GROUP BY p.id, u.nome_razao
    ORDER BY p.id DESC
");
$pedidos_stmt->execute([':fid' => $fazenda_id]);
$pedidos = $pedidos_stmt->fetchAll(PDO::FETCH_ASSOC);
// Buscar transportadoras
$transportadoras = $pdo->query("SELECT id, nome_razao FROM usuarios WHERE tipo_usuario='TRANSPORTADORA' ORDER BY nome_razao ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Agendar Transporte</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
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
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1001; }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu form button { background: none; border: none; color: white; cursor: pointer; font-size: 0.85rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; height: calc(100vh - 76px); position: sticky; top: 76px; overflow-y: auto; }
    .sidebar-menu{ list-style:none; }
    .sidebar-menu li { list-style: none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; min-width:0; }
    .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;}
    .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
    .btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem; text-decoration: none;}
    .btn-primary { background-color: var(--primary); color:white;}
    .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
    .btn-outline { background-color:transparent; color:var(--primary); border:1px solid var(--primary);}
    .btn-outline:hover { background-color: rgba(163,0,0,0.05);}
    .profile-container { background: var(--background); padding: 2rem; border-radius: 16px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); border: 1px solid var(--border); max-width: 800px; margin: auto; }
    .profile-container h1 { color: var(--primary); font-size: 1.6rem; margin-bottom: 1.5rem; text-align: center; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { font-weight: 600; display: block; margin-bottom: 0.4rem; color: var(--text); font-size: 0.9rem; }
    .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 1rem; font-family: 'Montserrat', sans-serif; }
    .form-group input:focus, .form-group select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(163, 0, 0, 0.2); }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .msg { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; font-weight: 500; }
    .ok { background: #e8f5e9; border: 1px solid #c8e6c9; color: #256029; }
    .erro { background: #ffebee; border: 1px solid #ffcdd2; color: #7a0000; }
    .buttons { display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap; }
    .buttons button[type="submit"] {
         padding: 12px 25px;
         font-size: 1rem;
         background: var(--primary);
         color: white;
         border: none;
         border-radius: 8px;
         cursor: pointer;
         transition: all 0.2s;
    }
     .buttons button[type="submit"]:hover {
         background: var(--primary-dark);
         box-shadow: 0 4px 8px rgba(163, 0, 0, 0.2);
         transform: translateY(-1px);
    }
     
    @media (max-width: 992px) {
       .sidebar {
           transform: translateX(-100%);
           position: fixed;
           top: 76px;
           left: 0;
           height: calc(100vh - 76px);
           z-index: 1000;
       }
       .sidebar.active { transform: translateX(0); }
       .hamburger { display: block; }
       .main { width: 100%; }
    }
    @media (max-width: 768px) {
      .container { flex-direction: column; }
      .sidebar { width: 100%; border-right: none; box-shadow: none; position: fixed; top:76px; left:0; transform: translateX(-100%); height: calc(100vh - 76px); overflow-y: auto;}
      .sidebar.active { transform: translateX(0); z-index: 1000;}
      .main { padding: 1rem; width: 100%; }
      .profile-container { padding: 1.5rem; margin: 0; }
      .buttons { flex-direction: column; align-items: center; }
      .dashboard-title { font-size: 1.5rem; }
      .form-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
      header { padding: 1rem; }
      .logo { font-size: 1.5rem; }
      .user-menu { gap: 0.5rem; }
      .user-menu span { display: none; }
      .main { padding: 0.5rem; }
      .profile-container { padding: 1rem; }
    }
  </style>
</head>
<body>
<header>
  <div class="logo">
    🐄
    <span>BovinTrade • Fazenda</span>
  </div>
  <div class="user-menu">
    <span><?php echo $email; ?></span>
    <form action="logout.php" method="post">
      <button type="submit" >Sair</button>
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
            <a href="agendar-transporte-f.php" class="menu-item <?= $current_page === 'agendar-transporte-f.php' ? 'active' : '' ?>">
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
  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-calendar-check"></i> Agendar Transporte para o Frigorífico</h1>
    </div>
    <div class="profile-container">
      <?php if ($msg): ?>
        <div class="msg <?= strpos($msg,'Erro')!==false || strpos($msg,'Erro')!==false ? 'erro':'ok' ?>"><?= $msg ?></div>
      <?php endif; ?>
      <?php if (empty($pedidos) && empty($msg)): ?>
          <div class="msg erro" style="background-color: #fff3cd; border-color: #ffeeba; color: #856404;">
              <i class="fas fa-info-circle"></i> Não há pedidos pagos aguardando agendamento de transporte no momento. Verifique seu <a href="05-historico-vendas.php" style="color: #856404; font-weight: bold;">histórico de vendas</a>.
          </div>
      <?php elseif (!empty($pedidos)): ?>
          <form method="POST" id="formTransporte">
            <div class="form-row">
              <div class="form-group">
                <label for="pedido_id">Pedido (lote vendido):</label>
                <select name="pedido_id" id="pedido_id" required>
                  <option value="">-- Selecione o Pedido Pago --</option>
                  <?php foreach ($pedidos as $p): ?>
                    <option value="<?= e($p['pedido_id']) ?>">
                      Pedido #<?= e($p['pedido_id']) ?> (Lote(s): <?= e($p['codigos_lotes']) ?>) → <?= e($p['nome_frigorifico']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="transportadora">Transportadora:</label>
                <select name="transportadora_id" id="transportadora" required>
                  <option value="">-- Selecione a Transportadora --</option>
                  <?php foreach ($transportadoras as $t): ?>
                    <option value="<?= e($t['id']) ?>"><?= e($t['nome_razao']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="motorista">Motorista:</label>
                <select name="motorista_id" id="motorista" required>
                  <option value="">-- Selecione o pedido e a transportadora --</option>
                </select>
              </div>
              <div class="form-group">
                <label for="veiculo">Veículo:</label>
                <select name="veiculo_id" id="veiculo" required>
                  <option value="">-- Selecione o pedido e a transportadora --</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="data_retirada">Data da Retirada (na fazenda):</label>
                <input type="date" name="data_retirada" id="data_retirada" required min="<?= date('Y-m-d') ?>">
              </div>
              <div class="form-group">
                <label for="hora_retirada">Hora da Retirada:</label>
                <input type="time" name="hora_retirada" id="hora_retirada" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="metodo_pagamento">Método de Pagamento do Frete:</label>
                <select name="metodo_pagamento_transporte" id="metodo_pagamento" required>
                    <option value="">-- Selecione como pagará o frete --</option>
                    <option value="PIX">PIX</option>
                    <option value="CARTAO">Cartão (Crédito/Débito)</option>
                    <option value="BOLETO">Boleto Bancário</option>
                    <option value="TRANSFERENCIA">Transferência Bancária</option>
                    <option value="A_COMBINAR">A Combinar Diretamente</option>
                </select>
              </div>
              <div class="form-group">
                <label for="distancia">Distância até o Frigorífico (km):</label>
                <input type="number" name="distancia" id="distancia" min="1" step="0.1" required placeholder="Ex: 150.5">
              </div>
            </div>
            <div class="buttons">
              <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Agendar e Notificar Transportadora</button>
            </div>
          </form>
      <?php endif; ?>
    </div>
  </main>
</div>
<script>
// Função para carregar motoristas e veículos
function loadTransportData() {
    let transportadora_id = $('#transportadora').val();
    let pedido_id = $('#pedido_id').val(); // Pedido é obrigatório para calcular a capacidade
    $('#motorista').html('<option value="">Carregando...</option>');
    $('#veiculo').html('<option value="">Carregando...</option>');
    // Checar se AMBOS os campos estão preenchidos antes de fazer a chamada AJAX
    if (transportadora_id && pedido_id) {
       
        // Se ambos estiverem preenchidos, faz a chamada AJAX
        $.ajax({
            url: 'fetch_motoristas_veiculos.php',
            type: 'POST',
            dataType: 'json',
            // Envia tanto a transportadora quanto o pedido
            data: {
                transportadora_id: transportadora_id,
                pedido_id: pedido_id
            },
            success: function(data){
                if (data.error) {
                    $('#motorista').html('<option value="">Erro: ' + data.error + '</option>');
                    $('#veiculo').html('<option value="">Erro: ' + data.error + '</option>');
                    console.error("Erro:", data.error);
                } else {
                    $('#motorista').html(data.motoristas || '<option value="">Nenhum motorista encontrado</option>');
                    $('#veiculo').html(data.veiculos || '<option value="">Nenhum veículo encontrado</option>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown){
                // Isso captura erros de rede ou de sintaxe JSON
                $('#motorista').html('<option value="">Erro na requisição (Verifique o console)</option>');
                $('#veiculo').html('<option value="">Erro na requisição (Verifique o console)</option>');
                console.error("Erro AJAX:", textStatus, errorThrown);
                console.log("Resposta do Servidor:", jqXHR.responseText); // Ajuda a debugar erro 500
            }
        });
    } else {
        // Se um dos campos estiver faltando, mostra uma mensagem para o usuário
        let msg = '-- Selecione o Pedido e a Transportadora para carregar motoristas e veículos --';
        if (!pedido_id) {
            msg = '-- Selecione primeiro o Pedido (Lote) --';
        } else if (!transportadora_id) {
            msg = '-- Selecione a Transportadora --';
        }
        $('#motorista').html('<option value="">' + msg + '</option>');
        $('#veiculo').html('<option value="">' + msg + '</option>');
    }
}
// Chamar a função quando a transportadora mudar
$('#transportadora').change(loadTransportData);
// Chamar a função quando o pedido mudar
$('#pedido_id').change(loadTransportData);
// Inicializar com a mensagem de seleção
$(document).ready(function() {
    loadTransportData();
});
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
}
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const hamburger = document.querySelector('.hamburger');
    if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
        sidebar.classList.remove('active');
    }
});
</script>
</body>
</html>