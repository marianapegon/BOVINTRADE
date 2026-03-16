<?php
// 07-avaliar-comprador.php — Fazenda avalia FRIGORÍFICO e TRANSPORTADORA por lote (pedido_item)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'config.php'; // $pdo (PDO)

// Proteção de rota (apenas FAZENDA)
if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['tipo_usuario'] ?? '') !== 'FAZENDA') {
  header('Location: login.php'); exit;
}

$u = $_SESSION['usuario'];
$fazenda_id = (int)$u['id'];
$nome  = htmlspecialchars($u['nome_razao'] ?? 'Fazenda', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// Lista apenas itens em que: a fazenda vendeu, pedido está PAGO e transporte ENTREGUE.
// Também esconde itens já avaliados por esta fazenda para o mesmo alvo (frigorífico OU transportadora).
$sql = "
SELECT
  pi.id              AS pedido_item_id,
  pi.lote_id,
  pi.codigo_lote,
  pi.valor_total,
  p.id               AS pedido_id,
  p.created_at       AS data_pedido,
  p.status           AS status_pedido,
  p.frigorifico_id   AS frigorifico_id,
  uf.nome_razao      AS frigorifico_nome,
  t.id               AS transporte_id,
  t.transportadora_id,
  ut.nome_razao      AS transportadora_nome

FROM pedido_itens pi
JOIN pedidos p
  ON p.id = pi.pedido_id
JOIN usuarios uf
  ON uf.id = p.frigorifico_id
LEFT JOIN transportes t
  ON t.pedido_id = p.id
 AND t.fazenda_id = pi.fazenda_id
 AND t.frigorifico_id = p.frigorifico_id
LEFT JOIN usuarios ut
  ON ut.id = t.transportadora_id

WHERE
  pi.fazenda_id = :fazenda
  AND p.status = 'PAGO'
  AND t.status = 'ENTREGUE'
ORDER BY p.created_at DESC, pi.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':fazenda' => $fazenda_id]);
$raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtra no PHP para esconder **só** o card do alvo já avaliado (pode ter avaliado um e faltar o outro)
$itens = [];
if ($raw) {
  // pega todas as avaliações desta fazenda ligadas aos itens listados
  $idsItens = array_column($raw, 'pedido_item_id');
  $idsItens = array_map('intval', $idsItens);
  $in = implode(',', array_fill(0, count($idsItens), '?'));

  $avaliadas = [];
  if ($in) {
    $q = "SELECT pedido_item_id, alvo_tipo, alvo_id
            FROM avaliacao
           WHERE avaliador_tipo='FAZENDA' AND avaliador_id=?
             AND pedido_item_id IN ($in)";
    $stA = $pdo->prepare($q);
    $params = array_merge([$fazenda_id], $idsItens);
    $stA->execute($params);
    $avaliadas = $stA->fetchAll(PDO::FETCH_ASSOC);
  }

  // Indexa avaliações já feitas por item e alvo_tipo
  $done = []; // [$pedido_item_id]['FRIGORIFICO']=true / ['TRANSPORTADORA']=true
  foreach ($avaliadas as $a) {
    $pi = (int)$a['pedido_item_id'];
    $done[$pi][strtoupper($a['alvo_tipo'])] = true;
  }

  foreach ($raw as $r) {
    $pi = (int)$r['pedido_item_id'];
    $r['_avaliou_frigorifico']   = !empty($done[$pi]['FRIGORIFICO']);
    $r['_avaliou_transportadora']= !empty($done[$pi]['TRANSPORTADORA']);
    $itens[] = $r;
  }
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function brl($v){ return 'R$ '.number_format((float)$v, 2, ',', '.'); }
function dta($ts){ return date('d/m/Y H:i', strtotime($ts)); }

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
:root {
  --primary: #a30000;
  --primary-dark: #7a0000;
  --text: #333333;
  --text-light: #666666;
  --background: #ffffff;
  --border: #e0e0e0;

  /* NOVAS (usadas nos cards e estrelas) */
  --card: #ffffff;
  --muted: #6b7280;
  --chip: #f0f2f5;
  --star: #ffc107;      /* dourado */
  --star-off: #e3e3e3;  /* cinza */
}
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
    .sidebar-menu{ list-style:none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; }
    .welcome-card{ background:linear-gradient(135deg,rgba(163,0,0,0.9),rgba(122,0,0,0.9)); color:white; border-radius:12px; padding:2.5rem; margin-bottom:2.5rem; }
  .page-title{font-size:1.6rem;font-weight:700;color:var(--primary);display:flex;gap:10px;align-items:center;margin-bottom:12px}
.note{background:#fffbe6;border:1px solid #ffe58f;color:#614700;border-radius:10px;padding:10px 12px;margin-bottom:16px}
.card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;margin-bottom:16px;box-shadow:0 8px 16px rgba(0,0,0,.03)}
.row{display:flex;justify-content:space-between;gap:10px;padding:4px 0}
.muted{color:var(--muted)}
.tag{background:var(--chip);padding:2px 8px;border-radius:99px;font-size:.85rem;color:#374151}
.split{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;margin-top:10px}
.eval{border:1px dashed var(--border);border-radius:12px;padding:12px}
.eval h3{font-size:1.05rem;margin-bottom:6px;display:flex;gap:8px;align-items:center}
.label{font-weight:600;margin:6px 0 4px}
.input, textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit}
textarea{min-height:110px;resize:vertical}
.actions{display:flex;justify-content:flex-end;margin-top:10px}
.btn{background:var(--primary);color:#fff;border:none;border-radius:8px;padding:10px 14px;cursor:pointer;font-weight:600}
.btn:hover{background:var(--primary-dark)}
.stars{ display:flex; gap:6px; font-size:22px; margin:4px 0 }
.star{ color: var(--star-off); cursor:pointer; transition: color .15s, transform .1s }
.star:hover{ transform: scale(1.08) }
.star.active{ color: var(--star) }

.subgrid{display:grid;grid-template-columns:repeat(3,minmax(80px,1fr));gap:8px}
.subgrid input{width:100%}
.done{background:#eafaf1;border:1px solid #b7eb8f;color:#135200;padding:8px 10px;border-radius:8px;font-size:.9rem;margin-top:8px}
.badge{display:inline-block;border-radius:6px;padding:2px 8px;font-size:.85rem;border:1px solid var(--border);background:#fafafa}

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
      <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
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
    <div class="page-title"><i class="fas fa-star"></i> Avaliar Comprador e Transportadora</div>


    <?php if (!$itens): ?>
      <p class="muted">Nenhum lote elegível para avaliação no momento.</p>
    <?php else: ?>
      <?php foreach ($itens as $it): ?>
        <div class="card">
          <div class="row">
            <div class="muted">Pedido</div>
            <div>#<?= (int)$it['pedido_id'] ?> • <?= e(dta($it['data_pedido'])) ?></div>
          </div>
          <div class="row"><div class="muted">Lote</div><div class="badge">#<?= e($it['codigo_lote']) ?></div></div>
          <div class="row"><div class="muted">Valor</div><div><strong><?= brl($it['valor_total']) ?></strong></div></div>

          <div class="split">
            <!-- CARD 1: Avaliação do Frigorífico -->
            <div class="eval">
              <h3><i class="fas fa-industry"></i> Avaliar Frigorífico</h3>

              <div class="row" style="padding:0"><div class="muted">Comprador</div><div><?= e($it['frigorifico_nome']) ?></div></div>

              <?php if ($it['_avaliou_frigorifico']): ?>
                <div class="done"><i class="fas fa-check"></i> Você já avaliou este frigorífico para este lote.</div>
              <?php else: ?>
                <form method="POST" action="salvar-avaliacao.php">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="pedido_item_id" value="<?= (int)$it['pedido_item_id'] ?>">
                  <input type="hidden" name="alvo_tipo" value="FRIGORIFICO">
                  <input type="hidden" name="alvo_id" value="<?= (int)$it['frigorifico_id'] ?>">

                  <div class="label">Nota geral</div>
                  <div class="stars" data-target="frig-<?= (int)$it['pedido_item_id'] ?>">
                    <?php for($s=1;$s<=5;$s++): ?>
                      <i class="fas fa-star star" data-val="<?= $s ?>"></i>
                    <?php endfor; ?>
                  </div>
                  <input type="hidden" name="nota_geral" id="frig-<?= (int)$it['pedido_item_id'] ?>-nota" value="0">

                  <div class="label">Subnotas (opcionais)</div>
                  <div class="subgrid">
                    <input type="number" min="1" max="5" name="metricas[cumprimento_pagamento]" placeholder="Pagamento (1–5)">
                    <input type="number" min="1" max="5" name="metricas[comunicacao_pos_venda]" placeholder="Comunicação (1–5)">
                    <input type="number" min="1" max="5" name="metricas[agilidade_conferencia]" placeholder="Conferência (1–5)">
                  </div>

                  <div class="label">Comentário (opcional)</div>
                  <textarea name="comentario" placeholder="Como foi vender para este frigorífico?"></textarea>

                  <div class="actions"><button class="btn" type="submit"><i class="fas fa-paper-plane"></i>&nbsp;Enviar avaliação</button></div>
                </form>
              <?php endif; ?>
            </div>

            <!-- CARD 2: Avaliação da Transportadora -->
            <div class="eval">
              <h3><i class="fas fa-truck"></i> Avaliar Transportadora</h3>

              <div class="row" style="padding:0">
                <div class="muted">Transportadora</div>
                <div><?= $it['transportadora_id'] ? e($it['transportadora_nome']) : '<span class="muted">Não identificada</span>' ?></div>
              </div>

              <?php if (!$it['transportadora_id']): ?>
                <div class="done" style="background:#fff2f0;border-color:#ffccc7;color:#a8071a">
                  <i class="fas fa-info-circle"></i> Não encontramos a transportadora deste lote como ENTREGUE.
                </div>
              <?php elseif ($it['_avaliou_transportadora']): ?>
                <div class="done"><i class="fas fa-check"></i> Você já avaliou esta transportadora para este lote.</div>
              <?php else: ?>
                <form method="POST" action="salvar-avaliacao.php">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="pedido_item_id" value="<?= (int)$it['pedido_item_id'] ?>">
                  <input type="hidden" name="alvo_tipo" value="TRANSPORTADORA">
                  <input type="hidden" name="alvo_id" value="<?= (int)$it['transportadora_id'] ?>">

                  <div class="label">Nota geral</div>
                  <div class="stars" data-target="transp-<?= (int)$it['pedido_item_id'] ?>">
                    <?php for($s=1;$s<=5;$s++): ?>
                      <i class="fas fa-star star" data-val="<?= $s ?>"></i>
                    <?php endfor; ?>
                  </div>
                  <input type="hidden" name="nota_geral" id="transp-<?= (int)$it['pedido_item_id'] ?>-nota" value="0">

                  <div class="label">Subnotas (opcionais)</div>
                  <div class="subgrid">
                    <input type="number" min="1" max="5" name="metricas[pontualidade]" placeholder="Pontualidade (1–5)">
                    <input type="number" min="1" max="5" name="metricas[bem_estar_viagem]" placeholder="Bem-estar (1–5)">
                    <input type="number" min="1" max="5" name="metricas[condicao_veiculo]" placeholder="Veículo (1–5)">
                  </div>

                  <div class="label">Comentário (opcional)</div>
                  <textarea name="comentario" placeholder="Como foi o transporte do lote?"></textarea>

                  <div class="actions"><button class="btn" type="submit"><i class="fas fa-paper-plane"></i>&nbsp;Enviar avaliação</button></div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>

<script>
// Estrelas (hover preview + clique fixa)
document.querySelectorAll('.stars').forEach(starBlock => {
  const target = starBlock.getAttribute('data-target');
  const hidden = document.getElementById(target + '-nota');
  const stars  = starBlock.querySelectorAll('.star');
  let selected = 0;

  const paint = n => stars.forEach((s,i)=> s.classList.toggle('active', i < n));

  stars.forEach((s, idx) => {
    const val = idx + 1;
    s.addEventListener('mouseenter', () => paint(val));
    s.addEventListener('mouseleave', () => paint(selected || 0));
    s.addEventListener('click', () => {
      selected = val;
      hidden.value = String(val);
      paint(selected);
    });
  });
});

// Envio AJAX dos formulários de avaliação
document.querySelectorAll('form[action="salvar-avaliacao.php"]').forEach(form => {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nota = form.querySelector('input[name="nota_geral"]');
    if (!nota || Number(nota.value) < 1) {
      alert('Selecione uma nota de 1 a 5 estrelas antes de enviar.');
      return;
    }

    // UI: desabilita temporariamente
    const btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.style.opacity = .7; }

    try {
      const fd = new FormData(form);
      const resp = await fetch('salvar-avaliacao.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const data = await resp.json().catch(() => ({}));

      if (!resp.ok || !data.ok) {
        alert(data.msg || 'Erro ao enviar a avaliação.');
        if (btn) { btn.disabled = false; btn.style.opacity = 1; }
        return;
      }

      // Sucesso: substitui o form por um "já avaliado"
      const wrap = form.closest('.eval');
      if (wrap) {
        wrap.innerHTML = `
          <h3>${wrap.querySelector('h3')?.innerHTML || ''}</h3>
          <div class="done"><i class="fas fa-check"></i> Avaliação enviada com sucesso!</div>
        `;
      }

    } catch (err) {
      alert('Falha de rede ao enviar a avaliação.');
      if (btn) { btn.disabled = false; btn.style.opacity = 1; }
    }
  });
});
</script>


</body>
</html>