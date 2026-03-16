<?php
// confirmar_pagamento.php - Confirmação final
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$pedido_id = $_GET['pedido_id'] ?? '';
$status = $_GET['status'] ?? '';

if (!$pedido_id) {
    header('Location: meu-carrinho.php');
    exit;
}

require 'conexao.php';

// Atualizar banco de dados baseado no status
if ($status === 'sucesso') {
    try {
        // 1. Atualizar pedido para PAGO
        $sql_pedido = "UPDATE pedidos SET status = 'pago', data_pagamento = NOW() WHERE id = ?";
        $stmt_pedido = $conn->prepare($sql_pedido);
        $stmt_pedido->bind_param("i", $pedido_id);
        $stmt_pedido->execute();
        
        // 2. Atualizar solicitações de frete para RECEBENDO PROPOSTAS
        $sql_frete = "UPDATE solicitacoes_frete SET status = 'recebendo_propostas' WHERE pedido_id = ?";
        $stmt_frete = $conn->prepare($sql_frete);
        $stmt_frete->bind_param("i", $pedido_id);
        $stmt_frete->execute();
        
        // 3. Limpar carrinho
        $_SESSION['carrinho'] = [];
        
    } catch (Exception $e) {
        error_log("Erro ao atualizar pedido: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Confirmação - BovinTrade</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Montserrat, sans-serif;
            background: #f9f9f9;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-align: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        .icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            background: #a30000;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($status === 'sucesso'): ?>
            <div class="icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="success">Pagamento Aprovado!</h1>
            <p>Pedido #<?= htmlspecialchars($pedido_id) ?> confirmado com sucesso.</p>
            <p><strong>Próxima etapa:</strong> Licitação de transporte</p>
            
            <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h3>🚚 Transporte</h3>
                <p>As transportadoras serão notificadas e você poderá escolher a melhor proposta em até 24h.</p>
            </div>
            
            <a href="propostas-recebidas.php?pedido_id=<?= $pedido_id ?>" class="btn">
                <i class="fas fa-truck"></i> Ver Propostas de Transporte
            </a>
            
        <?php else: ?>
            <div class="icon error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1 class="error">Pagamento Recusado</h1>
            <p>Não foi possível processar o pagamento do pedido #<?= htmlspecialchars($pedido_id) ?>.</p>
            
            <a href="formas_pagamento.php?pedido_id=<?= $pedido_id ?>" class="btn">
                <i class="fas fa-credit-card"></i> Tentar Novamente
            </a>
        <?php endif; ?>
        
        <br>
        <a href="07-painel-frigorifico.php" style="color: #666; text-decoration: none;">
            <i class="fas fa-home"></i> Voltar ao Painel
        </a>
    </div>
</body>
</html>