<?php
session_start();
require_once 'config.php';

// Proteção
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'TRANSPORTADORA') {
    header("Location: login.php"); exit;
}
$transportadora_id = $_SESSION['usuario']['id'];
// Lê o ID do GET ou POST
$transporte_id = (int)($_GET['id'] ?? $_POST['transporte_id'] ?? 0); 

if ($transporte_id === 0) {
    header('Location: solicitacoes-frete.php');
    exit;
}

$msg = '';

// ----------------------------------------------------
// 1. Processa Aceite/Recusa (CORRIGIDO PARA GARANTIR O UPDATE)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $motivo = trim($_POST['motivo_recusa'] ?? '');
    
    try {
        if ($acao === 'aceitar') {
            // CORREÇÃO APLICADA: Removemos a restrição de status_aceite PENDENTE no WHERE
            // Apenas exigimos ID e Transportadora ID para mudar o status para CONFIRMADO
            $stmt = $pdo->prepare("UPDATE transportes SET 
                status_aceite = 'ACEITO', 
                status = 'CONFIRMADO', 
                atualizado_em = NOW() 
                WHERE id = :tid AND transportadora_id = :uid");
            $stmt->execute([':tid' => $transporte_id, ':uid' => $transportadora_id]);
            
            if ($stmt->rowCount() > 0) {
                 $msg = "✅ Transporte Aceito! A coleta agora está CONFIRMADA e será liberada para rastreio pelo Frigorífico.";
            } else {
                 throw new Exception("Nenhuma alteração feita. O transporte pode já ter sido aceito/recusado.");
            }
            
        } elseif ($acao === 'recusar') {
            // Ação de RECUSA (mantida)
            $stmt = $pdo->prepare("UPDATE transportes SET 
                status_aceite = 'RECUSADO', 
                mensagem_transportadora = :motivo, 
                atualizado_em = NOW() 
                WHERE id = :tid AND transportadora_id = :uid AND status_aceite = 'PENDENTE'");
            $stmt->execute([':tid' => $transporte_id, ':uid' => $transportadora_id, ':motivo' => $motivo]);
            
            if ($stmt->rowCount() > 0) {
                $msg = "❌ Transporte Recusado. A fazenda e o frigorífico serão notificados.";
            } else {
                throw new Exception("Nenhuma alteração feita na recusa. O transporte pode já ter sido respondido.");
            }

        } else {
            throw new Exception("Ação inválida.");
        }

        // Após aceitar/recusar, redireciona para a lista
        header('Location: solicitacoes-frete.php?msg=' . urlencode($msg));
        exit;

    } catch (Throwable $e) {
        $msg = "❌ Erro ao processar a ação: " . $e->getMessage();
    }
}

// ----------------------------------------------------
// 2. Carrega Detalhes do Transporte (MANTIDO)
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        t.id, 
        t.pedido_id, 
        t.data_retirada, 
        t.hora_retirada, 
        t.distancia_km,
        t.status_aceite,
        t.status,
        f.nome_razao AS fazenda_nome,
        u.nome_razao AS frigorifico_nome,
        m.nome AS motorista_sugerido,
        v.placa AS veiculo_sugerido_placa,
        GROUP_CONCAT(lb.codigo_lote SEPARATOR ', ') AS lotes_codigos,
        GROUP_CONCAT(lb.quantidade SEPARATOR ' + ') AS lotes_qtd
    FROM transportes t
    JOIN pedidos p ON p.id = t.pedido_id
    JOIN pedido_itens pi ON pi.pedido_id = p.id
    JOIN usuarios f ON f.id = t.fazenda_id
    JOIN usuarios u ON u.id = t.frigorifico_id
    JOIN lote_bois lb ON lb.id = pi.lote_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    LEFT JOIN veiculo v ON v.id = t.veiculo_id
    WHERE t.id = :tid AND t.transportadora_id = :uid
    GROUP BY t.id
");
$stmt->execute([':tid' => $transporte_id, ':uid' => $transportadora_id]);
$transporte = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o transporte existe E se o status não é PENDENTE
if (!$transporte) {
    // Se não encontrou o transporte (ID inválido ou Transportadora errada), redireciona.
    header('Location: solicitacoes-frete.php?msg=' . urlencode("Transporte ID inválido ou não pertence a você."));
    exit;
}

if ($transporte['status_aceite'] !== 'PENDENTE') {
    $msg_status = "O transporte ID {$transporte_id} já está com o status: {$transporte['status_aceite']}.";
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: solicitacoes-frete-t.php?msg=' . urlencode($msg_status));
        exit;
    }
}

// Monta o total de cabeças
$total_cabecas = 0;
$quantidades = explode(' + ', $transporte['lotes_qtd']);
foreach ($quantidades as $q) {
    $total_cabecas += (int)$q;
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>BovinTrade - Responder Frete</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<style>
/* ... (CSS mantido) ... */
body{ font-family:Montserrat, sans-serif; background:#f9f9f9; color:#333; margin:0; }
header{ background:linear-gradient(135deg,#7a0000,#a30000); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; }
.logo{ font-size:1.8rem; font-weight:700; }
.container{ display:flex; min-height:calc(100vh - 76px); }
.sidebar{ width:280px; background:#fff; border-right:1px solid #e0e0e0; padding:1.5rem 0; }
.menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:#333; text-decoration:none; border-left:3px solid transparent; }
.main{ flex:1; padding:2.5rem; }
.card{ background:#fff; padding:2rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.05); max-width:700px; margin:auto; }
h2{ color:#a30000; text-align:center; margin-bottom:1.5rem; }
.dados-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 2rem; margin-bottom: 2rem; }
.item-dado strong { display: block; color: #a30000; margin-bottom: 0.3rem; font-size: 0.9rem; }
.item-dado span { font-size: 1rem; color: #333; }
.alerta { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; text-align: center; }
.alerta-erro { background: #fdecea; border: 1px solid #f5c2c0; color: #a30000; }
.alerta-sucesso { background: #e6f8ec; border: 1px solid #b5e3c7; color: #0a6b2b; }
.botoes-acao { display: flex; justify-content: space-between; margin-top: 2rem; }
.btn-submit { padding: 10px 20px; font-size: 1rem; border-radius: 6px; cursor: pointer; font-weight: 600; }
.btn-aceitar { background: #0a6b2b; color: white; border: none; }
.btn-aceitar:hover { background: #074d1e; }
.btn-recusar { background: #d9534f; color: white; border: none; }
.btn-recusar:hover { background: #b00020; }
</style>
</head>
<body>
<header>
    <div class="logo">🐄 BovinTrade • Transportadora</div>
    <div>
        <?= htmlspecialchars($_SESSION['usuario']['email']) ?>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
        </form>
    </div>
</header>

<div class="container">
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <a href="solicitacoes-frete.php" class="menu-item active"><i class="fas fa-handshake"></i><span>Solicitações de Frete</span></a>
            <a href="14-painel-transportadora.php" class="menu-item"><i class="fas fa-home"></i><span>Painel</span></a>
        </ul>
    </aside>

    <main class="main">
        <div class="card">
            <h2>Responder Solicitação de Frete #<?= $transporte_id ?></h2>

            <?php if ($msg && strpos($msg, '❌') !== false): ?>
                <div class="alerta alerta-erro"><?= htmlspecialchars($msg) ?></div>
            <?php elseif ($msg && strpos($msg, '✅') !== false): ?>
                <div class="alerta alerta-sucesso"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <?php if ($transporte && $transporte['status_aceite'] === 'PENDENTE'): ?>
                <div class="dados-grid">
                    <div class="item-dado"><strong>Fazenda de Origem</strong><span><?= htmlspecialchars($transporte['fazenda_nome']) ?></span></div>
                    <div class="item-dado"><strong>Frigorífico de Destino</strong><span><?= htmlspecialchars($transporte['frigorifico_nome']) ?></span></div>
                    <div class="item-dado"><strong>Data/Hora de Retirada</strong><span><?= date('d/m/Y', strtotime($transporte['data_retirada'])) ?> às <?= substr($transporte['hora_retirada'], 0, 5) ?></span></div>
                    <div class="item-dado"><strong>Distância Estimada</strong><span><?= $transporte['distancia_km'] ?> km</span></div>
                    <div class="item-dado"><strong>Lotes Envolvidos</strong><span><?= htmlspecialchars($transporte['lotes_codigos']) ?> (Total: <?= $total_cabecas ?> cabeças)</span></div>
                    <div class="item-dado"><strong>Sugestão da Fazenda</strong><span>Motorista: <?= htmlspecialchars($transporte['motorista_sugerido'] ?? 'N/A') ?>, Veículo: <?= htmlspecialchars($transporte['veiculo_sugerido_placa'] ?? 'N/A') ?></span></div>
                </div>

                <form method="POST" action="responder-transporte.php?id=<?= $transporte_id ?>">
                    <input type="hidden" name="transporte_id" value="<?= $transporte_id ?>">

                    <p style="margin-bottom: 1.5rem; font-weight: 500;">Deseja aceitar este agendamento?</p>
                    
                    <div class="botoes-acao">
                        <button type="submit" name="acao" value="aceitar" class="btn-submit btn-aceitar">
                            <i class="fas fa-thumbs-up"></i> Aceitar Agendamento
                        </button>
                        
                        <button type="button" onclick="$('#recusa-form').toggle();" class="btn-submit btn-recusar" style="background: #f0ad4e;">
                            <i class="fas fa-times-circle"></i> Recusar
                        </button>
                    </div>
                </form>

                <form id="recusa-form" method="POST" action="responder-transporte.php?id=<?= $transporte_id ?>" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ccc; display: none;">
                    <input type="hidden" name="acao" value="recusar">
                    <input type="hidden" name="transporte_id" value="<?= $transporte_id ?>">
                    <label for="motivo_recusa">Motivo da Recusa (Opcional):</label>
                    <textarea id="motivo_recusa" name="motivo_recusa" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; margin-top: 0.5rem;"></textarea>
                    <button type="submit" class="btn-submit btn-recusar" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-minus-circle"></i> Confirmar Recusa
                    </button>
                </form>
            <?php else: ?>
                 <div class="alerta alerta-erro">
                    <?= htmlspecialchars($transporte['status_aceite'] ?? 'Erro de Carregamento') === 'ACEITO' ? 'Este transporte já foi aceito. Veja o status em "Transportes Ativos".' : 'Esta solicitação não está mais PENDENTE ou não foi encontrada.' ?>
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="solicitacoes-frete.php" class="btn-submit btn-aceitar" style="background: #555;">
                        <i class="fas fa-arrow-left"></i> Voltar para Solicitações
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>