<?php
session_start();
// Configuração de exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 1. Verificação de Sessão e Rota (Segurança) ---
if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    http_response_code(403);
    die("Acesso negado ou usuário não logado.");
}

$id_frigorifico_logado = $_SESSION['usuario']['id'] ?? null;
if (!$id_frigorifico_logado) {
    http_response_code(403);
    die("ID de Frigorífico não encontrado na sessão.");
}

// --- 2. Obter Parâmetros e Helpers ---
$pagamento_id = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? 'view'; // 'view' ou 'download'

$pagamento_id = (int) $pagamento_id;

if ($pagamento_id <= 0 || !in_array($action, ['view', 'download'])) {
    http_response_code(400);
    die("Parâmetros inválidos.");
}

require 'conexao.php'; // Sua conexão com o banco de dados

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v){ return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function dtbr($ts){ 
    if (strtotime($ts) === false) return 'N/A';
    return date('d/m/Y H:i:s', strtotime($ts)); 
}
function status_label_class($s){
    switch (strtoupper($s)) {
        case 'APROVADO': case 'PAGO': return 'PAGO';
        default: return 'OUTRO';
    }
}

// --- 3. Busca de Dados do Pagamento e Pedido (Validação de Acesso) ---
$sql_pagamento = "
    SELECT 
        p.id AS pagamento_id, p.pedido_id, p.metodo, p.status AS pagamento_status, p.valor, p.moeda, p.referencia_externa, p.created_at AS pagamento_criado_em,
        ped.id AS pedido_id_real, ped.frigorifico_id, ped.total_pedido, ped.nf_url, ped.status AS pedido_status,
        u_frig.nome_razao AS nome_frigorifico, u_frig.cnpj AS cnpj_frigorifico
    FROM pagamentos p 
    JOIN pedidos ped ON p.pedido_id = ped.id
    JOIN usuarios u_frig ON ped.frigorifico_id = u_frig.id
    WHERE p.id = ? AND ped.frigorifico_id = ? AND p.status = 'APROVADO'
"; 

$stmt_pag = $conn->prepare($sql_pagamento);
$stmt_pag->bind_param("ii", $pagamento_id, $id_frigorifico_logado);
$stmt_pag->execute();
$pagamento_data = $stmt_pag->get_result()->fetch_assoc();
$stmt_pag->close();

if (!$pagamento_data) {
    http_response_code(404);
    die("Pagamento (ID #{$pagamento_id}) não encontrado, não aprovado, ou acesso não autorizado.");
}

$pedido_id = $pagamento_data['pedido_id'];

// --- 4. Busca de Itens do Pedido e Dados da Fazenda/Lote ---
$sql_itens = "
    SELECT 
        pi.id AS item_id, pi.quantidade_cabecas, pi.preco_unitario_cab, pi.valor_total AS item_valor_total, pi.status AS item_status,
        lb.codigo_lote, lb.raca, lb.peso_medio_kg,
        u_faz.nome_razao AS nome_fazenda, u_faz.cnpj AS cnpj_fazenda
    FROM pedido_itens pi
    JOIN lote_bois lb ON pi.lote_id = lb.id
    JOIN usuarios u_faz ON pi.fazenda_id = u_faz.id
    WHERE pi.pedido_id = ?
";
$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $pedido_id);
$stmt_itens->execute();
$itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_itens->close();


// -----------------------------------------------------------
// --- Geração e Entrega do CONTEÚDO (Simulador) ---
// -----------------------------------------------------------

// --- Conteúdo HTML/PDF da NF ---
ob_start(); // Inicia o buffer de saída

// O layout HTML foi simplificado para ser injetado diretamente no modal.
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Montserrat', sans-serif; padding: 10px; margin: 0; background: white; }
        h3 { color: #a30000; border-bottom: 2px solid #a30000; padding-bottom: 5px; margin-top: 15px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9em; }
        .info-label { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.85em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; color: #333; }
        .total { font-weight: bold; background-color: #ffeeba; }
    </style>
</head>
<body>
    <h3>NOTA FISCAL SIMULADA #<?= e($pagamento_data['pagamento_id']) ?></h3>
    <p style="text-align: right; font-size: 0.8em; color: gray;">Emissão: <?= dtbr(date('Y-m-d H:i:s')) ?></p>
    
    <h3 style="color: #2196F3; border-bottom: 2px solid #2196F3;">DADOS DO COMPRADOR (FRIGORÍFICO)</h3>
    <div class="info-row"><span class="info-label">Razão Social:</span><span><?= e($pagamento_data['nome_frigorifico']) ?></span></div>
    <div class="info-row"><span class="info-label">CNPJ:</span><span><?= e($pagamento_data['cnpj_frigorifico']) ?></span></div>
    <div class="info-row"><span class="info-label">E-mail:</span><span><?= e($_SESSION['usuario']['email']) ?></span></div>
    
    <h3 style="color: #4CAF50; border-bottom: 2px solid #4CAF50;">DADOS DA OPERAÇÃO</h3>
    <div class="info-row"><span class="info-label">Status do Pagamento:</span><span><?= e(status_label_class($pagamento_data['pagamento_status'])) ?></span></div>
    <div class="info-row"><span class="info-label">Data do Pedido:</span><span><?= dtbr($pagamento_data['pagamento_criado_em']) ?></span></div>
    <div class="info-row"><span class="info-label">Método de Pagamento:</span><span><?= e($pagamento_data['metodo']) ?></span></div>
    <div class="info-row"><span class="info-label">Referência Externa:</span><span><?= e($pagamento_data['referencia_externa'] ?? 'N/A') ?></span></div>
    
    <h3 style="color: #a30000;">ITENS DA NOTA</h3>
    <table>
        <thead>
            <tr>
                <th>Lote</th>
                <th>Fazenda Vendedora</th>
                <th>Raça / Peso Médio (kg)</th>
                <th>Qtd. Cabeças</th>
                <th>Preço Unit. (R$)</th>
                <th>Total (R$)</th>
            </tr>
        </thead>
        <tbody>
            <?php $total_nf = 0; foreach ($itens as $item): $total_nf += $item['item_valor_total']; ?>
                <tr>
                    <td><?= e($item['codigo_lote']) ?></td>
                    <td><?= e($item['nome_fazenda']) ?></td>
                    <td><?= e($item['raca']) ?> / <?= e($item['peso_medio_kg']) ?></td>
                    <td><?= e($item['quantidade_cabecas']) ?></td>
                    <td><?= brl($item['preco_unitario_cab']) ?></td>
                    <td><?= brl($item['item_valor_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="5" style="text-align: right;">VALOR TOTAL DA NOTA:</td>
                <td><?= brl($total_nf) ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
<?php
$html_output = ob_get_clean();

$pdf_filename = "NF_BOVINTRADE_{$pagamento_data['pagamento_id']}.pdf";

if ($action === 'download') {
    // --- LÓGICA DE DOWNLOAD (SIMULAÇÃO DE PDF) ---
    // Você usaria o Dompdf ou TCPDF para gerar o PDF aqui.
    
    // Simulação: Apenas para demonstrar o download, retornamos um conteúdo simples
    $simulated_pdf_content = "Este é um PDF de simulação para o pagamento #{$pagamento_data['pagamento_id']}. \n\nDetalhes: O valor total é " . brl($pagamento_data['total_pedido']) . ". \n\nNa NF real, você usaria uma biblioteca PHP para gerar o documento fiscal (PDF).";

    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf'); // Tipo MIME de PDF
    header('Content-Disposition: attachment; filename="' . $pdf_filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($simulated_pdf_content));
    echo $simulated_pdf_content;
    exit;

} elseif ($action === 'view') {
    // --- LÓGICA DE VISUALIZAÇÃO (HTML) ---
    echo $html_output; // Retorna o HTML para ser exibido no Modal/iframe
} else {
    // Ação inválida já foi verificada, mas para garantia
    http_response_code(400);
    die("Ação de NF inválida.");
}
?>