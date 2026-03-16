<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php';

// Proteção: só transportadora
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php"); exit;
}

// ----------------------------------------------------
// FUNÇÃO DE NOTIFICAÇÃO (COPIADA PARA ESTE ARQUIVO)
// ----------------------------------------------------
/**
 * Insere uma notificação no banco de dados para um usuário específico.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $usuario_id ID do usuário que deve receber a notificação.
 * @param string $tipo Tipo da notificação (deve ser um dos tipos definidos na tabela 'notificacoes').
 * @param string $titulo Título da notificação.
 * @param string $mensagem Mensagem detalhada.
 * @param string $tabela Tabela relacionada (opcional, ex: 'transportes').
 * @param int $id_relacionado ID da linha relacionada (opcional).
 * @param array $dados_array Dados adicionais (serão convertidos para JSON).
 * @return bool Retorna true em caso de sucesso, false caso contrário.
 */
function criar_notificacao($pdo, $usuario_id, $tipo, $titulo, $mensagem, $tabela = null, $id_relacionado = null, $dados_array = []) {
    if (!$usuario_id) return false;
    
    // Converte os dados adicionais para JSON
    $dados_json = $dados_array ? json_encode($dados_array) : null;

    $sql = "INSERT INTO notificacoes 
            (usuario_id, tipo, titulo, mensagem, relacionado_tabela, relacionado_id, dados_json, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $usuario_id, 
            $tipo, 
            $titulo, 
            $mensagem, 
            $tabela, 
            $id_relacionado, 
            $dados_json
        ]);
    } catch (PDOException $e) {
        // Em ambiente de produção, registre o erro:
        error_log("Erro ao criar notificação: " . $e->getMessage());
        return false;
    }
}
// ----------------------------------------------------
// FIM FUNÇÃO DE NOTIFICAÇÃO
// ----------------------------------------------------

$u = $_SESSION['usuario'];
$transportadora_id = $u['id'];
$msg = $_GET['msg'] ?? ''; 

// Mapeamento dos próximos status permitidos no fluxo
$status_permitidos = [
    'AUTORIZADO' => 'EM_TRANSITO_ORIGEM',
    'EM_TRANSITO_ORIGEM' => 'CHEGOU_NA_FAZENDA',
    // 'CHEGOU_NA_FAZENDA' => 'EM_TRANSITO_DESTINO' (A transição só é feita pelo Fazendeiro no painel dele)
    'EM_TRANSITO_DESTINO' => 'CHEGOU_NO_FRIGORIFICO',
];

// Mapeamento dos textos para os botões de ação
$mapa_proximo_status_display = [
    'AUTORIZADO' => ['status' => 'EM_TRANSITO_ORIGEM', 'texto' => 'INICIAR COLETA (A Caminho da Fazenda)'],
    'EM_TRANSITO_ORIGEM' => ['status' => 'CHEGOU_NA_FAZENDA', 'texto' => 'CHEGOU NA FAZENDA'],
    'CHEGOU_NA_FAZENDA' => ['texto' => 'AGUARDANDO CONFIRMAÇÃO DA FAZENDA PARA RETIRADA'],
    'EM_TRANSITO_DESTINO' => ['status' => 'CHEGOU_NO_FRIGORIFICO', 'texto' => 'CHEGOU NO FRIGORÍFICO'],
    'CHEGOU_NO_FRIGORIFICO' => ['texto' => 'AGUARDANDO CONFIRMAÇÃO DE RECEBIMENTO'],
    'CONFIRMADO' => ['texto' => 'AGUARDANDO AUTORIZAÇÃO DO FRIGORÍFICO'], // Ação deve ser desabilitada
];


// ----------------------------------------------------
// AÇÃO POST: Atualizar Status (COM NOTIFICAÇÃO)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transporte_id'], $_POST['novo_status'])) {
    $transporte_id = (int)$_POST['transporte_id'];
    $novo_status = strtoupper(trim($_POST['novo_status']));
    
    try {
        // 1. Verificar o status atual e obter IDs necessários
        $check_stmt = $pdo->prepare("SELECT status, frigorifico_id, fazenda_id, pedido_id FROM transportes WHERE id = :tid AND transportadora_id = :uid LIMIT 1");
        $check_stmt->execute([':tid' => $transporte_id, ':uid' => $transportadora_id]);
        $transporte_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transporte_data) {
            throw new Exception("Transporte não encontrado ou não pertence a você.");
        }
        
        $current_status = $transporte_data['status'];
        $frigorifico_id = (int)$transporte_data['frigorifico_id'];
        $fazenda_id = (int)$transporte_data['fazenda_id'];
        $pedido_id = (int)$transporte_data['pedido_id'];
        
        // Se o status atual for CONFIRMADO, não permitimos mudança daqui
        if ($current_status === 'CONFIRMADO') {
             throw new Exception("Aguarde a autorização do Frigorífico para iniciar a coleta.");
        }

        // Restrição: Não permitir transição de CHEGOU_NA_FAZENDA para EM_TRANSITO_DESTINO (fazenda confirma)
        if ($current_status === 'CHEGOU_NA_FAZENDA' && $novo_status === 'EM_TRANSITO_DESTINO') {
            throw new Exception("Aguarde a confirmação da fazenda para prosseguir com a retirada do lote.");
        }

        // 2. Validar se a transição de status é permitida pelo fluxo (mapa)
        if (isset($status_permitidos[$current_status]) && $status_permitidos[$current_status] === $novo_status) {
            
            // 3. Atualiza o status
            $stmt = $pdo->prepare("UPDATE transportes SET 
                status = :novo_status, 
                atualizado_em = NOW() 
                WHERE id = :tid AND transportadora_id = :uid AND status = :status_atual");
            
            $stmt->execute([
                ':novo_status' => $novo_status,
                ':tid' => $transporte_id,
                ':uid' => $transportadora_id,
                ':status_atual' => $current_status
            ]);

            $msg = "✅ Status do Transporte #{$transporte_id} atualizado para " . str_replace('_', ' ', $novo_status) . "!";
            
            // 4. NOTIFICAÇÃO DO FRIGORÍFICO E FAZENDA
            
            $dados_notificacao = [
                'transporte_id' => $transporte_id, 
                'pedido_id' => $pedido_id, 
                'novo_status' => $novo_status
            ];
            $tipo_notificacao = 'TRANSPORTE_ALERTA';
            $titulo_alerta = "Alerta de Transporte | Pedido #{$pedido_id}";
            $mensagem_alerta = "";
            
            switch ($novo_status) {
                case 'EM_TRANSITO_ORIGEM':
                    $mensagem_alerta = "A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.";
                    break;
                case 'CHEGOU_NA_FAZENDA':
                    $mensagem_alerta = "O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.";
                    $tipo_notificacao = 'TRANSPORTE_SOLICITADO'; // Altera o tipo para um que gere ação na fazenda
                    break;
                case 'CHEGOU_NO_FRIGORIFICO':
                    $mensagem_alerta = "O transporte do Pedido #{$pedido_id} chegou ao seu frigorífico. Por favor, inicie o recebimento.";
                    $tipo_notificacao = 'ENTREGA_CONFIRMADA'; // Altera o tipo para um que gere ação no frigorífico
                    break;
            }

            // Notifica o Frigorífico
            criar_notificacao($pdo, 
                              $frigorifico_id, 
                              $tipo_notificacao, 
                              $titulo_alerta, 
                              "Status: " . str_replace('_', ' ', $novo_status) . ". " . $mensagem_alerta, 
                              'transportes', 
                              $transporte_id, 
                              $dados_notificacao);

            // Notifica a Fazenda (Exceto quando chega no frigorífico, que não é relevante para ela)
            if ($novo_status !== 'CHEGOU_NO_FRIGORIFICO') {
                criar_notificacao($pdo, 
                                  $fazenda_id, 
                                  $tipo_notificacao, 
                                  $titulo_alerta, 
                                  "Status: " . str_replace('_', ' ', $novo_status) . ". " . $mensagem_alerta, 
                                  'transportes', 
                                  $transporte_id, 
                                  $dados_notificacao);
            }
            
        } else {
            throw new Exception("Transição de status inválida.");
        }

    } catch (Throwable $e) {
        $msg = "❌ Erro ao atualizar status: " . $e->getMessage();
    }
    // Redireciona para evitar reenvio do formulário
    header('Location: rastreamento-transporte-t.php?msg=' . urlencode($msg));
    exit;
}

// ----------------------------------------------------
// CONSULTA: Buscar transportes ativos (INCLUINDO CONFIRMADO)
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        t.id, 
        t.pedido_id, 
        t.data_retirada, 
        t.hora_retirada, 
        t.status,
        f.nome_razao AS fazenda_nome,
        u.nome_razao AS frigorifico_nome,
        m.nome AS motorista_nome,
        v.placa AS veiculo_placa
    FROM transportes t
    JOIN usuarios f ON f.id = t.fazenda_id
    JOIN usuarios u ON u.id = t.frigorifico_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    LEFT JOIN veiculo v ON v.id = t.veiculo_id

    WHERE t.transportadora_id = :tid 
      AND t.status IN ('CONFIRMADO', 'AUTORIZADO', 'EM_TRANSITO_ORIGEM', 'CHEGOU_NA_FAZENDA', 'EM_TRANSITO_DESTINO', 'CHEGOU_NO_FRIGORIFICO')
    ORDER BY t.data_retirada ASC
");
$stmt->execute([':tid' => $transportadora_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'rastreamento-transporte-t.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>BovinTrade - Rastreamento Ativo</title>
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
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Montserrat', sans-serif; background: #f9f9f9; color: var(--text); }
    header { background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color: white; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .logo { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
    .logo i { font-size: 1.6rem; }
    .user-menu { display: flex; align-items: center; gap: 1.5rem; }
    .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; }
    .container { display: flex; min-height: calc(100vh - 76px); }
    .sidebar { width: 280px; background: var(--background); border-right: 1px solid var(--border); padding: 1.5rem 0; box-shadow: 2px 0 8px rgba(0,0,0,0.05); }
    .sidebar-menu { list-style: none; }
    .menu-item { padding: 0.8rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--text); text-decoration: none; font-weight: 500; border-left: 3px solid transparent; transition: 0.2s; }
    .menu-item i { width: 24px; text-align: center; color: var(--text-light); }
    .menu-item:hover { background-color: rgba(163,0,0,0.05); color: var(--primary); border-left: 3px solid var(--primary); }
    .menu-item.active { background-color: rgba(163,0,0,0.1); color: var(--primary); border-left: 3px solid var(--primary); }
    .main { flex: 1; padding: 2.5rem; }
    .card { background: var(--background); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); max-width: 1200px; margin: auto; overflow-x: auto; }
    h2 { color: var(--primary); text-align: center; margin-bottom: 1.5rem; font-weight: 600; }
    table { width: 100%; border-collapse: collapse; table-layout: auto; } 
    th, td { padding: 0.75rem; border: 1px solid var(--border); text-align: left; }
    th { background: var(--primary); color: white; font-weight: 600; }
    .btn-acao { padding: 8px 12px; font-size: 0.9rem; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; font-weight: 600; }
    .btn-next-step { background: #007bff; color: white; }
    .btn-next-step:hover { background: #0056b3; }
    .status-tag { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; }
    
    /* Mapeamento de Status */
    .status-CONFIRMADO { background: #fff3cd; color: #856404; } /* Amarelo: Aguardando Autorização do Frig. */
    .status-AUTORIZADO { background: #d4edda; color: #155724; } /* Verde Claro: Pronto p/ Início */
    .status-EMTRANSITOORIGEM { background: #cce5ff; color: #004085; } /* Azul */
    .status-CHEGOUNAFAZENDA { background: #90ee90; color: #155724; } /* Verde/Amarelo */
    .status-EMTRANSITODESTINO { background: #87cefa; color: #004085; } /* Azul Claro */
    .status-CHEGUNOFRIGORIFICO { background: #ffcdd2; color: #721c24; } /* Vermelho Claro: Aguardando Recebimento */
    
    .msg-alerta { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; }
    .msg-alerta.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .msg-alerta.erro { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>
</head>
<body>
<header>
    <div class="logo">
        🐄
        <span>BovinTrade • Transportadora</span>
    </div>
    <div class="user-menu">
        <span><?= htmlspecialchars($u['email']) ?></span>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
        </form>
        <div class="user-avatar"><i class="fas fa-user"></i></div>
    </div>
</header>

<div class="container">
  <aside class="sidebar">
    <ul class="sidebar-menu">
      <a href="14-painel-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '14-painel-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Painel</span></a>
      <a href="cadastro-transporte.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-transporte.php' ? 'active' : ''; ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
      <a href="cadastro-motorista.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-motorista.php' ? 'active' : ''; ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
       <a href="gerenciar-motoristas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-motoristas.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
        <a href="gerenciar-transportes-transp.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-transportes-transp.php' ? 'active' : ''; ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
      <a href="pedidos-transportes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pedidos-transportes.php' ? 'active' : ''; ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
      <a href="coletas-agendadas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'coletas-agendadas.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
      <a href="rastreamento-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'rastreamento-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
      <a href="historico-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'historico-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
      <a href="notificacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'notificacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
      <a href="minhas-avaliacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'minhas-avaliacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
      <a href="17-ajudat.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '17-ajudat.php' ? 'active' : ''; ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
      <a href="meu-perfil-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'meu-perfil-transportadora.php' ? 'active' : ''; ?>">
    <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
</a>
    </ul>
  </aside>

    <main class="main">
        <div class="card">
            <h2>Rastreamento e Controle de Transportes Ativos</h2>

            <?php if ($msg): ?>
                <div class="msg-alerta <?= strpos($msg, '✅') !== false ? 'ok' : 'erro' ?>"><?= htmlspecialchars(urldecode($msg)) ?></div>
            <?php endif; ?>

            <?php if(count($transportes) === 0): ?>
                <p>Nenhum transporte para iniciar ou rastrear no momento. Verifique as "Solicitações de Frete".</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Transp.</th>
                            <th>Fazenda Origem</th>
                            <th>Frigorífico Destino</th>
                            <th>Motorista/Veículo</th>
                            <th>Retirada Agendada</th>
                            <th>Status Atual</th>
                            <th>Próxima Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transportes as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['fazenda_nome']) ?></td>
                                <td><?= htmlspecialchars($t['frigorifico_nome']) ?></td>
                                <td><?= htmlspecialchars($t['motorista_nome'] ?? 'N/A') ?> / <?= htmlspecialchars($t['veiculo_placa'] ?? 'N/A') ?></td>
                                <td><?= date('d/m/Y', strtotime($t['data_retirada'])) ?> às <?= substr($t['hora_retirada'], 0, 5) ?></td>
                                
                                <td><span class="status-tag status-<?= str_replace('', '', $t['status']) ?>"><?= str_replace('', ' ', $t['status']) ?></span></td>
                                
                                <td>
                                    <?php 
                                    $acao = $mapa_proximo_status_display[$t['status']] ?? ['texto' => 'Finalizado'];
                                    $proximo_status = $acao['status'] ?? null;
                                    $texto_acao = $acao['texto'];

                                    if ($t['status'] === 'CONFIRMADO' || $t['status'] === 'CHEGOU_NA_FAZENDA'):
                                    ?>
                                        <span class="btn-acao" style="background:#555; color:white; cursor:default;">
                                            <i class="fas fa-clock"></i> <?= $texto_acao ?>
                                        </span>
                                    
                                    <?php elseif ($proximo_status): ?>
                                        <form method="post" onsubmit="return confirm('Confirma a transição para: <?= $texto_acao ?>?');">
                                            <input type="hidden" name="transporte_id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="novo_status" value="<?= $proximo_status ?>">
                                            <button type="submit" class="btn-acao btn-next-step">
                                                <i class="fas fa-arrow-right"></i> <?= $texto_acao ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span><?= $texto_acao ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>