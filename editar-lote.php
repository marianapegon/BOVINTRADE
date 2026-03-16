<?php
// 01 - meu-perfil-fazenda.php — visualizar/editar/excluir conta (FAZENDA)
// Config do banco está um diretório acima:
require_once 'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Proteção de rota
if (empty($_SESSION['usuario'])) { header('Location: login.php'); exit; }
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
  if (($u['tipo_usuario'] ?? '') === 'FRIGORIFICO')    { header('Location: 07 - painel-frigorifico.php'); exit; }
  if (($u['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14 - painel-transportadora.php'); exit; }
  header('Location: login.php'); exit;
}
$email = $_SESSION['usuario']['email'] ?? '';


$fazendaId = (int)($_SESSION['usuario']['id'] ?? 0);
if ($fazendaId <= 0) { header('Location: ../login.php'); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function old($k, $def=''){ return e($_POST[$k] ?? $def); }

$loteId = max(0, (int)($_GET['id'] ?? 0));
if ($loteId <= 0) { die('ID inválido.'); }

$erro = null; $sucesso = null;

/* Carrega lote da fazenda */
try {
  $stmt = $pdo->prepare("
    SELECT id, codigo_lote, fazenda_id, quantidade, peso_medio_kg, raca, preco,
           historico_vacinacao, tipo_alimentacao, descricao, status, localizacao,
           preco_total, created_at, updated_at
      FROM lote_bois
     WHERE id = ? AND fazenda_id = ?
     LIMIT 1
  ");
  $stmt->execute([$loteId, $fazendaId]);
  $lote = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$lote) { die('Lote não encontrado ou não pertence à sua fazenda.'); }
} catch (Throwable $e) {
  die('Erro ao carregar lote.');
}

/* Só edita se status atual for DISPONIVEL */
$editable = ($lote['status'] === 'DISPONIVEL');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$editable) {
    $erro = 'Somente lotes com status DISPONIVEL podem ser editados.';
  } else {
    $quantidade       = (int)($_POST['quantidade'] ?? 0);
    $pesoMedio        = str_replace(',', '.', trim($_POST['peso_medio'] ?? ''));
    $raca             = trim($_POST['raca'] ?? '');
    $preco            = str_replace(',', '.', trim($_POST['preco'] ?? '')); // preço por animal
    $tipoAlimentacao  = trim($_POST['alimentacao'] ?? '');
    $historicoVacinas = trim($_POST['vacinas'] ?? '');
    $descricao        = trim($_POST['descricao'] ?? '');
    $statusNovo       = trim($_POST['status'] ?? '');

    $erros = [];
    if ($quantidade <= 0) $erros[] = 'Quantidade deve ser maior que 0.';
    if ($pesoMedio === '' || !is_numeric($pesoMedio) || (float)$pesoMedio <= 0) $erros[] = 'Peso médio (kg) inválido.';
    if ($raca === '') $erros[] = 'Selecione a raça.';
    if ($preco === '' || !is_numeric($preco) || (float)$preco < 0) $erros[] = 'Preço por animal inválido.';
    if ($tipoAlimentacao === '') $erros[] = 'Selecione o tipo de alimentação.';
    if ($historicoVacinas === '') $erros[] = 'Informe o histórico de vacinação.';

    // Status permitido neste form
    $statusPermitidos = ['DISPONIVEL','EM_NEGOCIACAO','INATIVO'];
    if (!in_array($statusNovo, $statusPermitidos, true)) $erros[] = 'Status inválido.';

    // Calcula preco_total (server-side — fonte da verdade)
    $precoUnitario = is_numeric($preco) ? (float)$preco : 0.0;
    $precoTotal    = $precoUnitario * $quantidade;
    if ($precoTotal < 0) $erros[] = 'Preço total calculado é inválido.';

    if (empty($erros)) {
      try {
        $upd = $pdo->prepare("
          UPDATE lote_bois
             SET quantidade = ?,
                 peso_medio_kg = ?,
                 raca = ?,
                 preco = ?,
                 historico_vacinacao = ?,
                 tipo_alimentacao = ?,
                 descricao = ?,
                 status = ?,
                 preco_total = ?     -- ✅ atualiza total
           WHERE id = ? AND fazenda_id = ? AND status = 'DISPONIVEL'
        ");
        $upd->execute([
          $quantidade,
          (float)$pesoMedio,
          $raca,
          $precoUnitario,
          $historicoVacinas,
          $tipoAlimentacao,
          ($descricao !== '' ? $descricao : null),
          $statusNovo,
          $precoTotal,
          $loteId,
          $fazendaId
        ]);

        if ($upd->rowCount() > 0) {
          $sucesso = 'Lote atualizado com sucesso. Total recalculado: R$ ' . number_format($precoTotal, 2, ',', '.');
          // Recarrega dados
          $stmt = $pdo->prepare("SELECT * FROM lote_bois WHERE id = ? AND fazenda_id = ? LIMIT 1");
          $stmt->execute([$loteId, $fazendaId]);
          $lote = $stmt->fetch(PDO::FETCH_ASSOC);
          $editable = ($lote['status'] === 'DISPONIVEL');
           header('Location: gerenciar-lotes.php?ok=1'); exit;
        } else {
          $erro = 'Nenhuma alteração aplicada. Verifique se o lote ainda está DISPONIVEL.';
        }
      } catch (Throwable $e) {
        $erro = 'Erro ao salvar alterações: ' . $e->getMessage();
      }
    } else {
      $erro = implode(' ', $erros);
    }
  }
}

$dis = $editable ? '' : 'disabled';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Painel da Fazenda</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonte + Ícones -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root { --primary:#a30000; --primary-dark:#7a0000; --text:#333; --text-light:#666; --background:#fff; --border:#e0e0e0; }
    *{ box-sizing:border-box; margin:0; padding:0; }
    html,body{ font-family:'Montserrat',sans-serif; background:#fff; color:#333; height:100%; max-width:100vw; overflow-x:hidden; line-height:1.6; }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:#fff; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:.75rem; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu form button{ background:none; border:1px solid #fff; color:#fff; padding:.4rem .8rem; border-radius:6px; cursor:pointer; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); }
    .sidebar{ width:280px; background:#fff; border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,.05); }
    .sidebar-header{ padding:0 1.5rem 1.5rem; border-bottom:1px solid var(--border); margin-bottom:1rem; }
    .sidebar-title{ font-size:1rem; text-transform:uppercase; letter-spacing:1px; color:var(--text-light); font-weight:600; margin-bottom:.5rem; }
    .sidebar-menu{ list-style:none; }
    .menu-category{ color:var(--text-light); font-size:.85rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; padding:.75rem 1.5rem; margin-top:1rem; }
    .menu-item{ padding:.75rem 1.5rem; display:flex; align-items:center; gap:.75rem; color:#333; text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background:rgba(163,0,0,.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background:rgba(163,0,0,.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; background:#f9f9f9; overflow-y:auto; }
    .dashboard-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
    .dashboard-title{ font-size:1.8rem; font-weight:600; }
    .profile-card{ background:#fff; border-radius:12px; padding:2.5rem; margin-bottom:2.5rem; box-shadow:0 8px 24px rgba(0,0,0,.1); }
    .profile-header{ margin-bottom:2rem; padding-bottom:1.5rem; border-bottom:1px solid var(--border); }
    .profile-title{ font-size:1.5rem; font-weight:600; display:flex; align-items:center; gap:1rem; }
    .profile-title i{ color:var(--primary); }
    .form-grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:1.5rem; }
    .form-group{ margin-bottom:1.5rem; }
    .form-group.full-width{ grid-column: span 2; }
    label{ display:block; margin-bottom:.5rem; font-weight:500; }
    input, select{ width:100%; padding:.75rem 1rem; border:1px solid var(--border); border-radius:6px; font-family:'Montserrat',sans-serif; font-size:1rem; }
    input[disabled]{ background:#f6f6f6; color:#666; }
    .btn{ padding:.75rem 1.25rem; border-radius:8px; font-weight:600; cursor:pointer; border:1px solid var(--primary); background:#fff; color:var(--primary); text-decoration:none; display:inline-flex; align-items:center; gap:.5rem; }
    .btn:hover{ background:#fff3f3; }
    .btn-primary{ background:var(--primary); color:#fff; border-color:var(--primary); }
    .btn-primary:hover{ background:var(--primary-dark); color:#fff; }
    .btn-danger{ border-color:#b00020; color:#b00020; }
    .btn-danger:hover{ background:#ffebee; }
    .alert{ padding:1rem; border-radius:8px; margin:0 0 1rem 0; }
    .alert-success{ background:#e8f5e9; border:1px solid #c8e6c9; color:#256029; }
    .alert-error{ background:#ffebee; border:1px solid #ffcdd2; color:#7a0000; }
    .welcome-card{ background:linear-gradient(135deg,rgba(163,0,0,0.9),rgba(122,0,0,0.9)); color:white; border-radius:12px; padding:2.5rem; margin-bottom:2.5rem; }

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
    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit" style="background:none; border:1px solid #fff; color:white; cursor:pointer; padding:.4rem .8rem; border-radius:6px;">Sair</button>
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

            <a href="agendar-transporte-f.php" class="menu-item <?= $current_page === 'agendar-retirada.php' ? 'active' : '' ?>">
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

            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i><span>Sair</span>
            </a>
        </ul>
    </aside>

  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title">Editar Lote • <?= e($lote['codigo_lote']) ?></h1>
      <div>
        <a class="btn btn-outline" href="gerenciar-lotes.php"><i class="fa fa-arrow-left"></i> Voltar</a>
      </div>
    </div>

    <div class="form-container">
      <?php if ($sucesso): ?><div class="alert alert-success"><?= e($sucesso) ?></div><?php endif; ?>
      <?php if ($erro):    ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
      <?php if (!$editable): ?>
        <div class="alert alert-error"><strong>Atenção:</strong> só é possível editar lotes com status <b>DISPONIVEL</b>. Este está: <b><?= e($lote['status']) ?></b>.</div>
      <?php endif; ?>

      <form method="post" action="">
        <h2 class="form-title"><i class="fas fa-cow"></i> Dados do Lote</h2>
        <p class="form-description">Altere as informações do lote. Campos com * são obrigatórios.</p>

        <!-- Código (somente leitura) -->
        <div class="form-group">
          <label class="form-label">Código</label>
          <input class="form-control" value="<?= e($lote['codigo_lote']) ?>" disabled>
        </div>

        <div class="form-row">
          <div class="form-col">
            <div class="form-group">
              <label for="quantidade" class="form-label required">Quantidade (cabeças)</label>
              <input type="number" id="quantidade" name="quantidade" class="form-control"
                     placeholder="Ex: 50" value="<?= old('quantidade', $lote['quantidade']) ?>"
                     min="1" required <?= $dis ?>>
            </div>
          </div>
          <div class="form-col">
            <div class="form-group">
              <label for="peso_medio" class="form-label required">Peso Médio (kg)</label>
              <input type="number" id="peso_medio" name="peso_medio" class="form-control"
                     step="0.01" placeholder="Ex: 450"
                     value="<?= old('peso_medio', $lote['peso_medio_kg']) ?>" min="0.01" required <?= $dis ?>>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-col">
            <div class="form-group">
              <label for="raca" class="form-label required">Raça</label>
              <select id="raca" name="raca" class="form-select" required <?= $dis ?>>
                <?php
                  $racas = ['Nelore','Angus','Brahman','Hereford'];
                  $sel = old('raca', $lote['raca']);
                  echo '<option value="" disabled '.($sel===''?'selected':'').'>Selecione</option>';
                  foreach ($racas as $r) echo '<option '.($sel===$r?'selected':'').'>'.e($r).'</option>';
                ?>
              </select>
            </div>
          </div>
          <div class="form-col">
            <div class="form-group">
              <label for="preco" class="form-label required">Preço por animal (R$)</label>
              <div class="input-with-icon">
                <input type="number" id="preco" name="preco" class="form-control" step="0.01"
                       placeholder="Ex: 2500" value="<?= old('preco', $lote['preco']) ?>"
                       min="0" required <?= $dis ?>>
                <i class="fas fa-dollar-sign"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Pré-visualização do total calculado -->
        <div class="form-group">
          <label class="form-label">Total do Lote (R$) — calculado automaticamente</label>
          <input type="text" id="total_lote" class="form-control"
                 value="R$ <?= number_format((float)($lote['preco'])* (int)($lote['quantidade']), 2, ',', '.') ?>"
                 readonly>
        </div>

        <div class="form-group">
          <label for="alimentacao" class="form-label required">Tipo de Alimentação</label>
          <select id="alimentacao" name="alimentacao" class="form-select" required <?= $dis ?>>
            <?php
              $alims = ['Pastagem','Confinamento','Semi-confinamento'];
              $selA = old('alimentacao', $lote['tipo_alimentacao']);
              echo '<option value="" disabled '.($selA===''?'selected':'').'>Selecione</option>';
              foreach ($alims as $a) echo '<option '.($selA===$a?'selected':'').'>'.e($a).'</option>';
            ?>
          </select>
        </div>

        <div class="form-group">
          <label for="vacinas" class="form-label required">Histórico de Vacinação</label>
          <textarea id="vacinas" name="vacinas" class="form-control form-textarea"
                    placeholder="Descreva as vacinas aplicadas..." required <?= $dis ?>><?= old('vacinas', $lote['historico_vacinacao']) ?></textarea>
        </div>

        <div class="form-group">
          <label for="descricao" class="form-label">Descrição do Lote</label>
          <textarea id="descricao" name="descricao" class="form-control form-textarea"
                    placeholder="Informações adicionais..." <?= $dis ?>><?= old('descricao', $lote['descricao']) ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-col">
            <div class="form-group">
              <label class="form-label">Localização</label>
              <input class="form-control" value="<?= e($lote['localizacao'] ?? '') ?>" disabled>
            </div>
          </div>
          <div class="form-col">
            <div class="form-group">
              <label for="status" class="form-label required">Status</label>
              <select id="status" name="status" class="form-select" required <?= $dis ?>>
                <?php
                  $opts = ['DISPONIVEL'=>'Disponível','EM_NEGOCIACAO'=>'Em negociação','INATIVO'=>'Inativo'];
                  $selStatus = old('status', $lote['status']);
                  foreach ($opts as $val=>$lbl) {
                    echo '<option value="'.e($val).'" '.($selStatus===$val?'selected':'').'>'.e($lbl).'</option>';
                  }
                ?>
              </select>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1rem">
          <?php if ($editable): ?>
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Salvar Alterações</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
  // Pré-visualização dinâmica do total (quantidade × preço)
  function formatBR(n){
    return n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function calcTotal(){
    const q = parseInt(document.getElementById('quantidade')?.value || '0', 10);
    const p = parseFloat(document.getElementById('preco')?.value || '0');
    const total = (isFinite(q) ? q : 0) * (isFinite(p) ? p : 0);
    const out = document.getElementById('total_lote');
    if (out) out.value = 'R$ ' + formatBR(total);
  }
  document.addEventListener('DOMContentLoaded', () => {
    ['quantidade','preco'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', calcTotal);
    });
    calcTotal();
  });
</script>
</body>
</html>  