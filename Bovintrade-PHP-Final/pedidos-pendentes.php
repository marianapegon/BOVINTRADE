<?php
session_start();
require_once 'config.php'; // ajuste se necessário

// Proteção: só frigorífico pode acessar
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    header("Location: login.php");
    exit;
}

$u = $_SESSION['usuario'];
$frigorifico_id = $u['id'];
$email = htmlspecialchars($u['email']);
$nome = htmlspecialchars($u['nome_razao'] ?? '');
$msg = "";

// Página atual para sidebar
$current_page = 'pedidos-pendentes.php';

// PRIMEIRO: Corrigir os status no banco de dados - mudar AGENDADO para PENDENTE
$stmt_correcao = $pdo->prepare("
    UPDATE transportes 
    SET status = 'PENDENTE' 
    WHERE status = 'AGENDADO' 
    AND pedido_id IN (SELECT id FROM pedidos WHERE frigorifico_id = :fid)
");
$stmt_correcao->execute([':fid' => $frigorifico_id]);

// Consulta os agendamentos relacionados ao frigorífico
$stmt = $pdo->prepare("
SELECT t.id, t.pedido_id, t.transportadora_id, t.veiculo_id, t.motorista_id,
       t.status, t.valor_transporte,
       u.nome_razao AS transportadora_nome,
       v.tipo AS veiculo_tipo,
       m.nome AS motorista_nome,
       lb.descricao AS lote_descricao
FROM transportes t
JOIN pedidos p ON p.id = t.pedido_id
JOIN usuarios u ON u.id = t.transportadora_id
JOIN veiculo v ON v.id = t.veiculo_id
JOIN motorista m ON m.id = t.motorista_id
JOIN pedido_itens pi ON pi.pedido_id = p.id
JOIN lote_bois lb ON lb.id = pi.lote_id
WHERE p.frigorifico_id = :fid
ORDER BY t.id DESC
");
$stmt->execute([':fid' => $frigorifico_id]);
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8" />
<title>BovinTrade - Pedidos Pendentes</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --primary:#a30000; --text:#333; --border:#e0e0e0; --bg:#fff;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Montserrat,Arial,sans-serif;background:#f5f5f7;color:var(--text);padding-bottom:80px;}
header{background:linear-gradient(135deg,#7a0000,#a30000);color:#fff;padding:1rem 1.5rem;display:flex;justify-content:space-between;align-items:center}
.logo{font-weight:700}
.container{display:flex;min-height:calc(100vh - 68px)}
.sidebar{width:260px;background:var(--bg);border-right:1px solid var(--border);padding:1rem}
.menu-item{display:flex;align-items:center;gap:.5rem;padding:.6rem .8rem;color:var(--text);text-decoration:none;border-left:3px solid transparent}
.menu-item.active{background:rgba(163,0,0,0.08);color:var(--primary);border-left:3px solid var(--primary)}
.main{flex:1;padding:1.5rem}
.card{background:var(--bg);padding:1.25rem;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,0.04);max-width:1200px;margin:auto;overflow-x:auto}
h2{color:var(--primary);text-align:center;margin-bottom:1rem}
table{width:100%;border-collapse:collapse}
th,td{padding:.6rem;border:1px solid var(--border);vertical-align:top;text-align:left}
th{background:var(--primary);color:#fff}
.lote-descricao{max-width:220px;max-height:40px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:middle}
.ver-mais{color:var(--primary);cursor:pointer;font-size:.85rem;margin-left:.4rem}
.btn{padding:.35rem .7rem;border-radius:6px;border:none;font-weight:600;cursor:pointer;margin-right:.4rem}
.btn-aceitar{background:green;color:#fff}
.btn-recusar{background:#d23;color:#fff}
.btn-renegociar{background:orange;color:#fff}
.btn-excluir{background:#b00000;color:#fff}
.form-inline{display:inline;margin:0;padding:0}
.small-note{font-size:.85rem;color:#666}
.status-aceito{color:green;font-weight:600}
.status-recusado{color:red;font-weight:600}
.status-agendado{color:orange;font-weight:600}
.status-pendente{color:#0066cc;font-weight:600}
.status-negociacao{color:#cc6600;font-weight:600}
.status-proposta{color:#9932CC;font-weight:600}

/* Modal renegociar */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:50}
.modal{background:#fff;padding:1rem;border-radius:8px;max-width:480px;width:95%;box-shadow:0 10px 30px rgba(0,0,0,0.2)}
.modal h3{margin-bottom:.5rem}
.modal .field{margin-bottom:.6rem}
.modal label{display:block;font-size:.9rem;margin-bottom:.25rem}
.modal input[type="text"], .modal input[type="number"], .modal textarea{width:100%;padding:.5rem;border:1px solid var(--border);border-radius:6px}
.modal .actions{display:flex;gap:.6rem;justify-content:flex-end;margin-top:.6rem}
.modal .close{background:#eee;border:none;padding:.4rem .6rem;border-radius:6px;cursor:pointer}

.info-badge {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 15px;
    font-size: 0.9rem;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}
</style>
</head>
<body>
<header>
  <div class="logo">🐄 BovinTrade • Frigorífico</div>
  <div class="user-info"><?= $email ?> &nbsp; <form action="logout.php" method="post" style="display:inline"><button style="background:none;border:none;color:#fff;cursor:pointer">Sair</button></form></div>
</header>

<div class="container">
  <aside class="sidebar">
   <ul class="sidebar-menu">
  <a href="07-painel-frigorifico.php" 
     class="menu-item <?= $current_page === '07-painel-frigorifico.php' ? 'active' : '' ?>">
     <i class="fas fa-home"></i><span>Painel</span>
  </a>

  <a href="meu-carrinho.php" 
     class="menu-item <?= $current_page === 'meu-carrinho.php' ? 'active' : '' ?>">
     <i class="fas fa-shopping-cart"></i><span>Meu Carrinho</span>
  </a>

  <a href="pesquisa-lotes.php" 
     class="menu-item <?= $current_page === 'pesquisa-lotes.php' ? 'active' : '' ?>">
     <i class="fas fa-search"></i><span>Pesquisa de Lotes</span>
  </a>

  <a href="09-recebimento-lotes.php" 
     class="menu-item <?= $current_page === '09-recebimento-lotes.php' ? 'active' : '' ?>">
     <i class="fas fa-truck-loading"></i><span>Recebimento</span>
  </a>

  <a href="10-historico-compras.php" 
     class="menu-item <?= $current_page === '10-historico-compras.php' ? 'active' : '' ?>">
     <i class="fas fa-history"></i><span>Histórico de Compras</span>
  </a>

  <a href="11-historico-pagamentos.php" 
     class="menu-item <?= $current_page === '11-historico-pagamentos.php' ? 'active' : '' ?>">
     <i class="fas fa-credit-card"></i><span>Histórico de Pagamento</span>
  </a>

 <a href="agendar-transporte.php" 
     class="menu-item <?= $current_page === 'agendar-transporte.php' ? 'active' : '' ?>">
     <i class="fas fa-calendar"></i><span>Agendar Transporte</span>
  </a>

  <a href="12-avaliacoes.php" 
     class="menu-item <?= $current_page === '12-avaliacoes.php' ? 'active' : '' ?>">
     <i class="fas fa-star"></i><span>Avaliações</span>
  </a>

  <a href="13-relatorios.php" 
     class="menu-item <?= $current_page === '13-relatorios.php' ? 'active' : '' ?>">
     <i class="fas fa-chart-line"></i><span>Relatórios</span>
  </a>

  <a href="pedidos-pendentes.php" 
     class="menu-item <?= $current_page === 'pedidos-pendentes.php' ? 'active' : '' ?>">
     <i class="fas fa-tasks"></i><span>Pedidos Pendentes</span>
  </a>

  <a href="transportes-agendados-frig.php" 
     class="menu-item <?= $current_page === 'transportes-agendados-frig.php' ? 'active' : '' ?>">
     <i class="fas fa-truck"></i><span>Transportes Agendados</span>
  </a>

  <a href="16-notificacoes.php" 
     class="menu-item <?= $current_page === '16-notificacoes.php' ? 'active' : '' ?>">
     <i class="fas fa-bell"></i><span>Notificações</span>
  </a>

  <a href="17-ajuda.php" 
     class="menu-item <?= $current_page === '17-ajuda.php' ? 'active' : '' ?>">
     <i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span>
  </a>

  <a href="meu-perfil-frigorifico.php" 
     class="menu-item <?= $current_page === 'meu-perfil-frigorifico.php' ? 'active' : '' ?>">
     <i class="fas fa-user-cog"></i><span>Meu Perfil</span>
  </a>
</ul>
  </aside>

  <main class="main">
    <div class="card">
      <h2>Pedidos Pendentes</h2>
      

      
      <div class="info-badge">
        <i class="fas fa-info-circle"></i> 
        <strong>Status do Agendamento:</strong><br>
        • <span class="status-pendente">PENDENTE</span>: Aguardando sua ação<br>
        • <span class="status-proposta">PROPOSTA ENVIADA</span>: Aguardando resposta da transportadora<br>
        • <span class="status-agendado">AGENDADO</span>: Valor aceito por ambas as partes
      </div>

      <?php if(!$agendamentos): ?>
        <p>Nenhum pedido pendente no momento.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Lote</th>
              <th>Transportadora</th>
              <th>Motorista</th>
              <th>Veículo</th>
              <th>Status</th>
              <th>Valor Transporte</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($agendamentos as $a):
            $status = strtoupper(trim((string)($a['status'] ?? '')));
            $valor = $a['valor_transporte'] !== null ? 'R$ ' . number_format($a['valor_transporte'], 2, ',', '.') : '-';
            $loteDesc = $a['lote_descricao'] ?? '';
            
            // Determina o status para exibição
            if (empty($status) || $status === 'PENDENTE') {
                $status_exibicao = 'PENDENTE';
                $status_class = 'pendente';
            } elseif ($status === 'PROPOSTA_ENVIADA') {
                $status_exibicao = 'PROPOSTA ENVIADA';
                $status_class = 'proposta';
            } elseif ($status === 'AGENDADO') {
                $status_exibicao = 'AGENDADO';
                $status_class = 'agendado';
            } else {
                $status_exibicao = $status;
                $status_class = strtolower($status);
            }
          ?>
            <tr>
              <td><?= htmlspecialchars($a['pedido_id']) ?></td>
              <td>
                <span class="lote-descricao"><?= htmlspecialchars($loteDesc) ?></span>
                <?php if(mb_strlen($loteDesc, 'UTF-8') > 40): ?>
                  <span class="ver-mais">ver mais</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($a['transportadora_nome']) ?></td>
              <td><?= htmlspecialchars($a['motorista_nome']) ?></td>
              <td><?= htmlspecialchars($a['veiculo_tipo']) ?></td>
              <td class="status-<?= $status_class ?>"><?= $status_exibicao ?></td>
              <td><?= $valor ?></td>
              <td>
                <?php if($status_exibicao === 'PENDENTE' || $status_exibicao === 'PROPOSTA ENVIADA'): ?>
                  <!-- Aceitar - só aparece durante a negociação -->
                  <form class="form-inline" action="aceitar-agendamento.php" method="post" onsubmit="return confirm('Confirmar aceite deste agendamento?');">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn btn-aceitar">Aceitar</button>
                  </form>

                  <!-- Recusar -->
                  <form class="form-inline" action="recusar-agendamento.php" method="post" onsubmit="return confirm('Tem certeza que deseja recusar?');">
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn btn-recusar">Recusar</button>
                  </form>

                  <!-- botão renegociar (abre modal) -->
                  <button type="button" class="btn btn-renegociar btn-open-renegociar"
                    data-id="<?= (int)$a['id'] ?>"
                    data-valor="<?= htmlspecialchars($a['valor_transporte']) ?>">
                    Renegociar
                  </button>
                <?php elseif($status_exibicao === 'AGENDADO'): ?>
                  <!-- Quando estiver AGENDADO, só mostra mensagem -->
                  <span class="small-note">Agendamento confirmado</span>
                <?php endif; ?>

                <!-- Botão Excluir (sempre presente) -->
                <form class="form-inline" action="excluir-agendamento.php" method="post" onsubmit="return confirm('Excluir este agendamento?');">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="btn btn-excluir">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        
        <p class="small-note" style="margin-top: 15px;">
          <strong>Nota:</strong> O status "AGENDADO" só aparece quando o valor do transporte foi aceito por ambas as partes (frigorífico e transportadora).
        </p>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Modal Renegociar -->
<div id="modal-overlay" class="modal-overlay" role="dialog" aria-hidden="true">
  <div class="modal" role="document">
    <h3>Renegociar Agendamento</h3>
    <form id="form-renegociar" action="renegociar-agendamento.php" method="post">
      <input type="hidden" name="id" id="reneg-id">
      <div class="field">
        <label for="novo_valor">Novo valor (R$)</label>
        <input type="number" step="0.01" name="novo_valor" id="novo_valor" required>
      </div>
      <div class="field">
        <label for="mensagem_frigorifico">Mensagem (opcional)</label>
        <textarea name="mensagem_frigorifico" id="mensagem_frigorifico" rows="3" placeholder="Escreva uma observação..."></textarea>
      </div>
      <div class="actions">
        <button type="button" class="close" id="cancel-reneg">Cancelar</button>
        <button type="submit" class="btn btn-renegociar">Enviar Proposta</button>
      </div>
    </form>
  </div>
</div>

<script>
// ver mais / ver menos
document.querySelectorAll('.ver-mais').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const desc = btn.previousElementSibling;
    if(desc.style.whiteSpace === 'normal'){
      desc.style.whiteSpace = 'nowrap';
      desc.style.overflow = 'hidden';
      btn.textContent = 'ver mais';
    } else {
      desc.style.whiteSpace = 'normal';
      desc.style.overflow = 'visible';
      btn.textContent = 'ver menos';
    }
  });
});

// modal renegociar
const overlay = document.getElementById('modal-overlay');
const renegId = document.getElementById('reneg-id');
const novoValor = document.getElementById('novo_valor');
const mensagem = document.getElementById('mensagem_frigorifico'); // corrigido

document.querySelectorAll('.btn-open-renegociar').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const id = btn.getAttribute('data-id');
    const valor = btn.getAttribute('data-valor') || '';
    renegId.value = id;
    novoValor.value = valor;
    mensagem.value = ''; // zera o campo
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden','false');
    novoValor.focus();
  });
});

document.getElementById('cancel-reneg').addEventListener('click', ()=>{
  overlay.style.display = 'none';
  overlay.setAttribute('aria-hidden','true');
});

overlay.addEventListener('click', (e)=>{
  if(e.target === overlay){
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden','true');
  }
});

</script>
</body>
</html>