<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php';

// Proteção: só transportadora
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php"); exit;
}

$u = $_SESSION['usuario'];
$transportadora_id = $u['id'];
$email = htmlspecialchars($u['email']);
$nome = htmlspecialchars($u['nome_razao']);

// Página atual para sidebar
$current_page = basename($_SERVER['PHP_SELF']);

// --- Mapeamento para nomes amigáveis de pagamento ---
$metodos_pagamento_map = [
    'PIX' => 'PIX',
    'CARTAO' => 'Cartão',
    'BOLETO' => 'Boleto',
    'TRANSFERENCIA' => 'Transferência',
    'A_COMBINAR' => 'A Combinar',
    'PAGAMENTO_NA_ENTREGA' => 'Na Entrega', 
    'PAGAMENTO_NA_COLETA' => 'Na Coleta',  
    'FATURADO' => 'Faturado',
];
function formatarMetodoPagamento($metodo, $map) {
    return htmlspecialchars($map[$metodo] ?? $metodo ?? 'Não informado');
}
// --- Fim do mapeamento ---


// Processa Aceite/Recusa
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'] ?? '';
    $transporte_id = (int)($_POST['transporte_id'] ?? 0);
    $motivo = trim($_POST['motivo_recusa'] ?? '');

    try {
        if ($acao === 'aceitar') {
            $stmt = $pdo->prepare("UPDATE transportes SET
                status_aceite = 'ACEITO',
                status = 'CONFIRMADO',
                atualizado_em = NOW()
                WHERE id = :tid AND transportadora_id = :uid AND status_aceite = 'PENDENTE'");
            $stmt->execute([':tid' => $transporte_id, ':uid' => $transportadora_id]);

            if ($stmt->rowCount() > 0) {
                $msg = "✅ Transporte Aceito! A coleta agora está CONFIRMADA.";
                
                // --- INÍCIO DO BLOCO DE NOTIFICAÇÃO (ACEITE) ---
                try {
                    // 1. Buscar IDs do pedido (Frigorífico e Fazenda) e data
                    $stmt_info = $pdo->prepare("SELECT pedido_id, frigorifico_id, fazenda_id, data_retirada FROM transportes WHERE id = ?");
                    $stmt_info->execute([$transporte_id]);
                    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

                    if ($info) {
                        $frigorifico_id = $info['frigorifico_id'];
                        $fazenda_id = $info['fazenda_id'];
                        $pedido_id = $info['pedido_id'];
                        $data_retirada_formatada = date('d/m/Y', strtotime($info['data_retirada']));
                        $dados_json = json_encode([
                            'transporte_id' => $transporte_id,
                            'pedido_id' => $pedido_id,
                            'transportadora_nome' => $u['nome_razao']
                        ]);

                        // 2. Notificar o FRIGORÍFICO para autorizar
                        $stmt_notif_frig = $pdo->prepare("
                            INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id)
                            VALUES (?, 'TRANSPORTE_SOLICITADO', ?, ?, ?, 'transportes', ?)
                        ");
                        $titulo_frig = "Autorização Pendente: Pedido #" . $pedido_id;
                        $mensagem_frig = "A transportadora '" . $u['nome_razao'] . "' aceitou a coleta para " . $data_retirada_formatada . ". Por favor, revise e autorize.";
                        $stmt_notif_frig->execute([$frigorifico_id, $titulo_frig, $mensagem_frig, $dados_json, $transporte_id]);

                        // 3. Notificar a FAZENDA que foi aceito
                        $stmt_notif_faz = $pdo->prepare("
                            INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id)
                            VALUES (?, 'TRANSPORTE_ACEITO', ?, ?, ?, 'transportes', ?) 
                        ");
                        $titulo_faz = "Transporte Confirmado: Pedido #" . $pedido_id;
                        $mensagem_faz = "A transportadora '" . $u['nome_razao'] . "' confirmou a coleta do seu lote para " . $data_retirada_formatada . ".";
                        $stmt_notif_faz->execute([$fazenda_id, $titulo_faz, $mensagem_faz, $dados_json, $transporte_id]);
                    }
                } catch (Exception $e) {
                    error_log("Falha ao criar notificacoes de aceite de transporte: " . $e->getMessage());
                }
                // --- FIM DO BLOCO DE NOTIFICAÇÃO ---

            } else {
                throw new Exception("Nenhuma alteração feita (pode já ter sido processado).");
            }
        } elseif ($acao === 'recusar') {
            $motivo_limpo = substr($motivo, 0, 500);

            $stmt = $pdo->prepare("UPDATE transportes SET
                status_aceite = 'RECUSADO',
                status = 'RECUSADO',
                mensagem_transportadora = :motivo,
                atualizado_em = NOW()
                WHERE id = :tid AND transportadora_id = :uid AND status_aceite = 'PENDENTE'");
            $stmt->execute([':tid' => $transporte_id, ':uid' => $transportadora_id, ':motivo' => $motivo_limpo]);

            if ($stmt->rowCount() > 0) {
                $msg = "❌ Transporte Recusado. A fazenda será notificada.";
                
                // --- INÍCIO DO BLOCO DE NOTIFICAÇÃO (RECUSA) ---
                 try {
                    // 1. Buscar IDs do pedido (Frigorífico e Fazenda)
                    $stmt_info = $pdo->prepare("SELECT pedido_id, frigorifico_id, fazenda_id FROM transportes WHERE id = ?");
                    $stmt_info->execute([$transporte_id]);
                    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

                    if ($info) {
                        // 2. Notificar a FAZENDA que foi recusado
                        $stmt_notif_faz = $pdo->prepare("
                            INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id)
                            VALUES (?, 'TRANSPORTE_RECUSADO', ?, ?, ?, 'transportes', ?) 
                        ");
                        
                        $titulo_faz = "Transporte Recusado: Pedido #" . $info['pedido_id'];
                        $mensagem_faz = "A transportadora '" . $u['nome_razao'] . "' recusou a solicitação de transporte. Motivo: " . ($motivo_limpo ?: 'Não informado');
                        $dados_faz = json_encode([
                            'transporte_id' => $transporte_id,
                            'pedido_id' => $info['pedido_id'],
                            'transportadora_nome' => $u['nome_razao'],
                            'motivo_recusa' => $motivo_limpo
                        ]);
                        
                        $stmt_notif_faz->execute([
                            $info['fazenda_id'], // ID da Fazenda
                            $titulo_faz,
                            $mensagem_faz,
                            $dados_faz,
                            $transporte_id
                        ]);
                    }
                } catch (Exception $e) {
                     error_log("Falha ao criar notificacao de recusa de transporte: " . $e->getMessage());
                }
                // --- FIM DO BLOCO DE NOTIFICAÇÃO DE RECUSA ---

            } else {
                throw new Exception("Nenhuma alteração feita na recusa (pode já ter sido processado).");
            }
        } else {
            throw new Exception("Ação inválida.");
        }
    } catch (Throwable $e) {
        $msg = "❌ Erro ao processar a ação: " . htmlspecialchars($e->getMessage());
    }
}

// Busca transportes PENDENTES (incluindo todos os detalhes)
// CORRIGIDO: Seleciona v.tipo em vez de v.modelo
$stmt = $pdo->prepare("
    SELECT
        t.id, t.pedido_id, t.data_retirada, t.hora_retirada, t.distancia_km, t.status_aceite, t.metodo_pagamento_transporte,
        f.nome_razao AS fazenda_nome, f.rua AS fazenda_rua, f.numero AS fazenda_numero, f.bairro AS fazenda_bairro, f.cidade AS fazenda_cidade, f.estado AS fazenda_estado, f.cep AS fazenda_cep, f.telefone AS fazenda_telefone,
        u.nome_razao AS frigorifico_nome, u.rua AS frigorifico_rua, u.numero AS frigorifico_numero, u.bairro AS frigorifico_bairro, u.cidade AS frigorifico_cidade, u.estado AS frigorifico_estado, u.cep AS frigorifico_cep, u.telefone AS frigorifico_telefone,
        m.nome AS motorista_sugerido,
        v.placa AS veiculo_sugerido_placa,
        v.tipo AS veiculo_sugerido_tipo,
        v.modelo AS veiculo_sugerido_modelo, -- Adicionado modelo
        GROUP_CONCAT(DISTINCT lb.raca ORDER BY lb.id SEPARATOR ', ') AS lotes_racas, -- Trocado descrição por raça
        SUM(pi.quantidade_cabecas) AS total_cabecas
    FROM transportes t
    JOIN pedidos p ON p.id = t.pedido_id
    JOIN pedido_itens pi ON pi.pedido_id = p.id AND pi.fazenda_id = t.fazenda_id
    JOIN usuarios f ON f.id = t.fazenda_id
    JOIN usuarios u ON u.id = t.frigorifico_id
    JOIN lote_bois lb ON lb.id = pi.lote_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    LEFT JOIN veiculo v ON v.id = t.veiculo_id
    WHERE t.transportadora_id = :tid AND t.status = 'AGENDADO' AND t.status_aceite = 'PENDENTE'
    GROUP BY t.id
    ORDER BY t.data_retirada ASC, t.hora_retirada ASC
");
$stmt->execute([':tid' => $transportadora_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$has_pending_transportes = count($transportes) > 0;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>BovinTrade - Solicitações de Frete</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<style>
:root {
  --primary: #a30000; --primary-dark: #7a0000; --text: #333333;
  --text-light: #666666; --background: #ffffff; --border: #e0e0e0;
}
*{ margin:0; padding:0; box-sizing:border-box; }
body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); }
header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
.logo i{ font-size:1.6rem; }
.user-menu{ display:flex; align-items:center; gap:1.5rem; }
.user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
.user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
.container{ display:flex; min-height:calc(100vh - 76px); }
.sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
.sidebar-menu{ list-style:none; }
.menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
.menu-item i{ width:24px; text-align:center; color:var(--text-light); }
.menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
.menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
.main{ flex:1; padding:2.5rem; }
.welcome-card{ background:var(--background); color:var(--text); border-radius:12px; padding:2.5rem; margin-bottom:2.5rem; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
.card{ background:var(--background); padding:2rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); max-width:100%; margin:auto; overflow-x:auto; }
h2{ color:var(--primary); text-align:center; margin-bottom:1.5rem; }
table{ width:100%; border-collapse:collapse; table-layout:auto; margin-top: 1rem; }
th, td{ padding:0.8rem 1rem; border:1px solid var(--border); text-align:left; vertical-align: middle; white-space:nowrap; font-size: 0.9rem; }
th{ background:var(--primary); color:white; }
.btn-acao { padding: 5px 10px; font-size: 0.85rem; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; border: none; cursor: pointer; white-space: nowrap; margin: 2px; }
.btn-aceitar { background: #0a6b2b; color: white; }
.btn-aceitar:hover { background: #074d1e; }
.btn-recusar { background: #d9534f; color: white; }
.btn-recusar:hover { background: #b00020; }
.btn-details { background: #f0f0f0; color: var(--text-light); border: 1px solid #ccc; }
.btn-details:hover { background: #e0e0e0; }
.alerta { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; text-align: center; }
.alerta-erro { background: #fdecea; border: 1px solid #f5c2c0; color: var(--primary); }
.alerta-sucesso { background: #e6f4ea; border: 1px solid #b7e0b7; color: #2d662d; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background: var(--background); margin: 10% auto; padding: 2rem; border-radius: 12px; max-width: 500px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); position: relative; }
.modal-content h3 { color: var(--primary); margin-bottom: 1rem; }
.close { position: absolute; top: 10px; right: 15px; font-size: 1.5rem; cursor: pointer; color: var(--text); background: none; border: none; }
textarea { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; margin-top: 0.5rem; font-family: 'Montserrat', sans-serif; }
.no-requests { text-align: center; padding: 3rem; color: var(--text-light); font-size: 1.1rem; font-weight: 500; }
.details-row { display: none; background-color: #fdfbfb; }
.details-row td { border-top: 2px dashed var(--primary); }
.details-panel { padding: 1.5rem; }
.details-panel h4 { color: var(--primary-dark); margin-top: 1rem; margin-bottom: 0.5rem; font-size: 1rem; font-weight: 600; }
.details-panel h4:first-child { margin-top: 0; }
.details-panel p { margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--text-light); line-height: 1.4; white-space: normal; /* Permite quebra de linha nos detalhes */ }
.details-panel strong { font-weight: 600; color: var(--text); margin-right: 5px; }
@media (max-width: 768px) {
    .sidebar { width: 100%; border-right: none; box-shadow: none; padding: 1rem 0;}
    .container { flex-direction: column; }
    .main { padding: 1.5rem; }
    th, td { white-space: normal; }
    .details-row td { padding: 0; }
    .details-panel { padding: 1rem; }
}
</style>
</head>
<body>
<header>
    <div class="logo">🐄 <span>BovinTrade • Transportadora</span></div>
    <div class="user-menu">
        <span><?= $email ?></span>
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
        <?php if ($has_pending_transportes): ?>
            <div class="welcome-card">
                <h2>Solicitações de Frete Pendentes de Aceite</h2>
                <p style="text-align: center; color: var(--text-light); margin-top: -1rem;">Aguardando sua confirmação para serem agendadas como Coletas Confirmadas.</p>
            </div>
        <?php else: ?>
             <div class="welcome-card">
                 <h2 style="margin-bottom: 0.5rem;">Solicitações de Frete</h2>
                 <div class="no-requests">
                     <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                     <p>🎉 Sem pedidos de frete aguardando sua resposta no momento!</p>
                 </div>
             </div>
        <?php endif; ?>

        <?php if ($msg): ?>
            <div class="alerta <?= strpos($msg, '❌') !== false ? 'alerta-erro' : 'alerta-sucesso' ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-top: 2.5rem;">
            <?php if ($has_pending_transportes): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Fazenda Origem</th>
                            <th>Frigorífico Destino</th>
                            <th>Retirada Agendada</th>
                            <th>Dist. (km)</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transportes as $index => $t):
                            $enderecoFazenda = implode(', ', array_filter([$t['fazenda_rua'], $t['fazenda_numero'], $t['fazenda_bairro']]))
                                . ( ($t['fazenda_cidade'] || $t['fazenda_estado']) ? '<br>' . implode(' - ', array_filter([$t['fazenda_cidade'], $t['fazenda_estado']])) : '')
                                . ($t['fazenda_cep'] ? '<br>CEP: '.htmlspecialchars($t['fazenda_cep']) : '');
                            $telFazenda = htmlspecialchars($t['fazenda_telefone'] ?? 'Não informado');

                            $enderecoFrigorifico = implode(', ', array_filter([$t['frigorifico_rua'], $t['frigorifico_numero'], $t['frigorifico_bairro']]))
                                . ( ($t['frigorifico_cidade'] || $t['frigorifico_estado']) ? '<br>' . implode(' - ', array_filter([$t['frigorifico_cidade'], $t['frigorifico_estado']])) : '')
                                . ($t['frigorifico_cep'] ? '<br>CEP: '.htmlspecialchars($t['frigorifico_cep']) : '');
                            $telFrigorifico = htmlspecialchars($t['frigorifico_telefone'] ?? 'Não informado');
                            ?>

                            <?php
                            // --- INÍCIO DA MODIFICAÇÃO: CÁLCULO DO FRETE ---
                            $distancia = floatval($t['distancia_km'] ?? 0);
                            $valor_por_km = 5.50;
                            $frete_estimado_formatado = 'N/A';

                            if ($distancia > 0) {
                                $frete_valor = $distancia * $valor_por_km;
                                $frete_estimado_formatado = 'R$ ' . number_format($frete_valor, 2, ',', '.');
                            }
                            // --- FIM DA MODIFICAÇÃO ---
                            ?>

                            <?php
                            $metodoPagamentoFormatado = formatarMetodoPagamento($t['metodo_pagamento_transporte'] ?? null, $metodos_pagamento_map);
                            ?>
                            <tr>
                                <td>#<?= htmlspecialchars($t['pedido_id']) ?></td>
                                <td><?= htmlspecialchars($t['fazenda_nome']) ?></td>
                                <td><?= htmlspecialchars($t['frigorifico_nome']) ?></td>
                                <td><?= date('d/m/Y', strtotime($t['data_retirada'])) ?> às <?= substr($t['hora_retirada'], 0, 5) ?></td>
                                <td><?= htmlspecialchars($t['distancia_km']) ?></td>
                                <td style="white-space: nowrap;">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="transporte_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="acao" value="aceitar">
                                         <button type="submit" class="btn-acao btn-aceitar" title="Aceitar esta solicitação">
                                            <i class="fas fa-check-circle"></i> Aceitar
                                        </button>
                                    </form>
                                    <button class="btn-acao btn-recusar" onclick="openModal(<?= $t['id'] ?>)" title="Recusar esta solicitação">
                                         <i class="fas fa-times-circle"></i> Recusar
                                     </button>
                                    <button class="btn-acao btn-details" onclick="toggleDetails(<?= $index ?>)" title="Ver mais detalhes">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                </td>
                            </tr>
                            <tr class="details-row" id="details-<?= $index ?>">
                                <td colspan="6">
                                    <div class="details-panel">
                                        <h4><i class="fas fa-map-marker-alt"></i> Endereço de Origem (Fazenda)</h4>
                                        <p><?= $enderecoFazenda ?: 'Endereço não informado' ?></p>
                                        <p><strong>Telefone:</strong> <?= $telFazenda ?></p>

                                        <h4><i class="fas fa-building"></i> Endereço de Destino (Frigorífico)</h4>
                                         <p><?= $enderecoFrigorifico ?: 'Endereço não informado' ?></p>
                                        <p><strong>Telefone:</strong> <?= $telFrigorifico ?></p>

                                        <h4><i class="fas fa-user-tie"></i> Sugestões da Fazenda</h4>
                                        <p><strong>Motorista Sugerido:</strong> <?= htmlspecialchars($t['motorista_sugerido'] ?? 'Não especificado') ?></p>
                                         <p><strong>Veículo Sugerido:</strong>
                                            <?= htmlspecialchars($t['veiculo_sugerido_placa'] ?? 'N/E') ?>
                                             <?= $t['veiculo_sugerido_modelo'] ? ' (' . htmlspecialchars($t['veiculo_sugerido_modelo']) . ')' : '' ?>
                                        </p>

                                        <h4><i class="fas fa-dollar-sign"></i> Pagamento do Frete</h4>
                                                                                            <p><strong>Valor Estimado (R$ 5,50/km):</strong> <?= $frete_estimado_formatado ?></p>
                                                                                             <p><strong>Método Informado:</strong> <?= $metodoPagamentoFormatado ?></p>

                                        <h4><i class="fas fa-boxes"></i> Carga</h4>
                                        <p><strong>Total de Cabeças:</strong> <?= htmlspecialchars($t['total_cabecas'] ?? 'N/E') ?></p>
                                         <p><strong>Raças:</strong> <?= htmlspecialchars($t['lotes_racas'] ?? 'N/E') ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (!$msg) : ?>
                 <p style="text-align: center; color: var(--text-light);">Acompanhe suas outras páginas do menu lateral para ver Coletas Agendadas, em Rastreamento ou Histórico.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<div id="recusaModal" class="modal">
    <div class="modal-content">
        <button class="close" onclick="closeModal()">&times;</button>
        <h3>Recusar Solicitação de Frete</h3>
        <form id="recusaForm" method="POST">
            <input type="hidden" name="transporte_id" id="modal_transporte_id">
             <input type="hidden" name="acao" value="recusar">
            <label for="motivo_recusa">Motivo da Recusa (Opcional):</label>
            <textarea id="motivo_recusa" name="motivo_recusa" rows="3"></textarea>
            <button type="submit" class="btn-acao btn-recusar" style="width:100%; margin-top:1rem;">
                 <i class="fas fa-minus-circle"></i> Confirmar Recusa
            </button>
        </form>
    </div>
</div>

<script>
// Função para mostrar/esconder detalhes
function toggleDetails(index) {
    const detailsRow = document.getElementById('details-' + index);
    if (detailsRow) {
        // Fecha todos os outros detalhes abertos antes de abrir/fechar o atual
        document.querySelectorAll('.details-row').forEach(row => {
            if (row.id !== detailsRow.id) {
                 row.style.display = 'none';
            }
        });
        // Alterna a exibição da linha clicada
        detailsRow.style.display = detailsRow.style.display === 'none' || detailsRow.style.display === '' ? 'table-row' : 'none';
    }
}


function openModal(transporteId) {
    document.getElementById('modal_transporte_id').value = transporteId;
    document.getElementById('recusaModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('recusaModal').style.display = 'none';
    document.getElementById('motivo_recusa').value = ''; // Limpa o motivo
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    let modal = document.getElementById('recusaModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>
