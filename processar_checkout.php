<?php
// processar_checkout.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php';

// Verificar se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['finalizar_pedido'])) {
    header('Location: checkout_resumo.php');
    exit;
}

try {
    $conn->begin_transaction();
    
    // 1. DADOS BÁSICOS
    $frigorifico_id = $_SESSION['frigorifico_id'] ?? null;
    $itens_selecionados = json_decode($_POST['itens_selecionados'] ?? '[]', true);
    $total_pedido = (float)($_POST['total_pedido'] ?? 0);
    
    if (!$frigorifico_id || empty($itens_selecionados)) {
        throw new Exception("Dados do pedido inválidos");
    }
    
    // 2. CRIAR O PEDIDO PRINCIPAL
    $sql_pedido = "INSERT INTO pedidos (frigorifico_id, total, status, data_pedido) 
                   VALUES (?, ?, 'aguardando_pagamento', NOW())";
    $stmt_pedido = $conn->prepare($sql_pedido);
    $stmt_pedido->bind_param("id", $frigorifico_id, $total_pedido);
    $stmt_pedido->execute();
    $pedido_id = $conn->insert_id;
    $stmt_pedido->close();
    
    // 3. ADICIONAR ITENS DO PEDIDO (do carrinho)
    $carrinho = $_SESSION['carrinho'] ?? [];
    $fazendas_envolvidas = []; // Para criar solicitações de frete
    
    foreach ($itens_selecionados as $rowId) {
        if (!isset($carrinho[$rowId])) continue;
        
        $item = $carrinho[$rowId];
        $lote_id = $item['id'] ?? null;
        $fazenda_id = $item['fazenda_id'] ?? null;
        $quantidade = (int)($item['quantidade'] ?? 1);
        $preco_unitario = (float)($item['preco'] ?? 0);
        
        if ($lote_id && $fazenda_id) {
            // Inserir item do pedido
            $sql_item = "INSERT INTO pedido_itens (pedido_id, lote_id, quantidade, preco_unitario) 
                         VALUES (?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            $stmt_item->bind_param("iiid", $pedido_id, $lote_id, $quantidade, $preco_unitario);
            $stmt_item->execute();
            $stmt_item->close();
            
            // Registrar fazenda envolvida
            if (!in_array($fazenda_id, $fazendas_envolvidas)) {
                $fazendas_envolvidas[] = $fazenda_id;
            }
            
            // Atualizar status do lote para "Vendido"
            $sql_lote = "UPDATE lote_bois SET status = 'vendido' WHERE id = ?";
            $stmt_lote = $conn->prepare($sql_lote);
            $stmt_lote->bind_param("i", $lote_id);
            $stmt_lote->execute();
            $stmt_lote->close();
        }
    }
    
    // 4. CRIAR SOLICITAÇÕES DE FRETE PARA CADA FAZENDA
    foreach ($fazendas_envolvidas as $fazenda_id) {
        $sql_solicitacao = "INSERT INTO solicitacoes_frete 
                           (pedido_id, fazenda_id, frigorifico_id, status, data_criacao, data_limite_propostas) 
                           VALUES (?, ?, ?, 'aguardando_propostas', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))";
        $stmt_solicitacao = $conn->prepare($sql_solicitacao);
        $stmt_solicitacao->bind_param("iii", $pedido_id, $fazenda_id, $frigorifico_id);
        $stmt_solicitacao->execute();
        $solicitacao_id = $conn->insert_id;
        $stmt_solicitacao->close();
        
        // Buscar dados do lote para a solicitação
        $sql_lote_info = "SELECT lb.quantidade, lb.peso_medio, f.cidade as origem_cidade, f.estado as origem_estado,
                                 fr.cidade as destino_cidade, fr.estado as destino_estado
                          FROM lote_bois lb
                          JOIN fazenda f ON lb.fazenda_id = f.id
                          JOIN frigorifico fr ON ? = fr.id
                          WHERE lb.fazenda_id = ? AND lb.id IN (
                              SELECT lote_id FROM pedido_itens WHERE pedido_id = ?
                          ) LIMIT 1";
        $stmt_info = $conn->prepare($sql_lote_info);
        $stmt_info->bind_param("iii", $frigorifico_id, $fazenda_id, $pedido_id);
        $stmt_info->execute();
        $stmt_info->store_result();
        
        if ($stmt_info->num_rows > 0) {
            $stmt_info->bind_result($quantidade, $peso_medio, $origem_cidade, $origem_estado, $destino_cidade, $destino_estado);
            $stmt_info->fetch();
            
            // Atualizar solicitação com informações detalhadas
            $sql_update = "UPDATE solicitacoes_frete 
                          SET quantidade_animais = ?, peso_total_estimado = ?,
                              origem_cidade = ?, origem_estado = ?,
                              destino_cidade = ?, destino_estado = ?
                          WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $peso_total = $quantidade * $peso_medio;
            $stmt_update->bind_param("idssssi", $quantidade, $peso_total, $origem_cidade, $origem_estado, $destino_cidade, $destino_estado, $solicitacao_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
        $stmt_info->close();
    }
    
    // 5. LIMPAR CARRINHO E ATUALIZAR SESSÃO
    $_SESSION['carrinho'] = [];
    $_SESSION['ultimo_pedido_id'] = $pedido_id;
    
    $conn->commit();
    
    // 6. REDIRECIONAR PARA PAGAMENTO
    header("Location: formas_pagamento.php?pedido_id=" . $pedido_id);
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    // Em caso de erro, redirecionar de volta com mensagem
    $_SESSION['erro_checkout'] = "Erro ao processar pedido: " . $e->getMessage();
    header('Location: checkout_resumo.php');
    exit;
}
?>