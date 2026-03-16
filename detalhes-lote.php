<?php
require 'conexao.php';

$id_lote = (int)($_GET['id'] ?? 0);

// Debug: Verifique se o ID foi passado
if ($id_lote === 0) {
    die('Erro: Parâmetro "id" não foi passado ou é inválido. Exemplo: ?id=1');
}

// Busca por ID
$stmt = $conn->prepare("SELECT descricao, historico_vacinacao FROM lote_bois WHERE id = ?");
if (!$stmt) {
    die('Erro na preparação da query: ' . $conn->error);
}

$stmt->bind_param("i", $id_lote);
if (!$stmt->execute()) {
    die('Erro na execução da query: ' . $stmt->error);
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Lote não encontrado para o ID: ' . $id_lote);
}

$lote = $result->fetch_assoc();
$detalhes = $lote['descricao'] ?? 'Sem detalhes adicionais.';
$vacinas = $lote['historico_vacinacao'] ?? '';

$stmt->close();
?>

<style>
.scroll-container {
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #ccc;
    padding: 15px;
    background-color: #fdfdfd;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}
.scroll-container h3 {
    margin-top: 0;
    color: #a30000;
}
.scroll-container p {
    margin-bottom: 10px;
    line-height: 1.5;
}
.scroll-container ul {
    padding-left: 20px;
}
.scroll-container li {
    margin-bottom: 5px;
}
</style>

<h2>Detalhes do Lote</h2>
<div class="scroll-container">
    <?php
    // Quebra o texto de detalhes em parágrafos se houver quebras de linha
    $paragrafos = preg_split("/[\r\n]+/", $detalhes);
    foreach ($paragrafos as $p):
        $p_limpo = trim($p);
        if (!empty($p_limpo)):
    ?>
        <p><?= htmlspecialchars($p_limpo) ?></p>
    <?php
        endif;
    endforeach;
    ?>
</div>

<h2>Histórico de Vacinação</h2>
<div class="scroll-container">
    <?php if (!empty($vacinas)): ?>
        <ul>
            <?php
            $vacinas_array = preg_split("/[\r\n,]+/", $vacinas);
            foreach ($vacinas_array as $v):
                $v_limpo = trim($v);
                if (!empty($v_limpo)):
            ?>
                <li><?= htmlspecialchars($v_limpo) ?></li>
            <?php endif; endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Sem histórico de vacinação.</p>
    <?php endif; ?>
</div>
