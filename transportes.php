<?php
session_start();
require_once  'config.php';

// Proteção: só frigorífico pode acessar
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    header("Location: login.php");
    exit;
}

$u = $_SESSION['usuario'];
$frigorifico_id = (int)$u['id'];
$email = htmlspecialchars($u['email']);
$nome = htmlspecialchars($u['nome_razao'] ?? '');
$current_page = 'lotes-agendados.php';

// Consulta os lotes agendados (status AGENDADO) relacionados ao frigorífico
$stmt = $pdo->prepare("
SELECT 
    t.id AS transporte_id,
    t.pedido_id,
    t.status,
    t.valor_transporte,
    t.data_retirada,
    t.hora_retirada,
    t.distancia_km,
    t.status_aceite,
    t.criado_em,
    u.nome_razao AS transportadora_nome,
    u.telefone AS transportadora_telefone,
    v.tipo AS veiculo_tipo,
    v.placa AS veiculo_placa,
    m.nome AS motorista_nome,
    m.telefone AS motorista_telefone,
    lb.id AS lote_id,
    lb.codigo_lote,
    lb.descricao AS lote_descricao,
    lb.quantidade AS lote_quantidade,
    lb.peso_medio_kg AS lote_peso_medio,
    lb.raca AS lote_raca,
    lb.preco AS lote_preco,
    lb.preco_total AS lote_preco_total,
    lb.historico_vacinacao,
    lb.tipo_alimentacao,
    lb.localizacao,
    p.created_at AS data_pedido,
    p.status AS status_pedido,
    p.total_pedido,
    pi.quantidade_cabecas,
    pi.preco_unitario_cab,
    pi.valor_total AS valor_total_item,
    prod.nome_razao AS produtor_nome,
    prod.cidade AS fazenda_cidade,
    prod.estado AS fazenda_estado
FROM transportes t
JOIN pedidos p ON p.id = t.pedido_id
JOIN usuarios u ON u.id = t.transportadora_id
JOIN veiculo v ON v.id = t.veiculo_id
JOIN motorista m ON m.id = t.motorista_id
JOIN pedido_itens pi ON pi.pedido_id = p.id
JOIN lote_bois lb ON lb.id = pi.lote_id
JOIN fazenda f ON f.usuario_id = lb.fazenda_id
JOIN usuarios prod ON prod.id = f.usuario_id
WHERE p.frigorifico_id = :fid 
AND t.status = 'AGENDADO'
ORDER BY t.data_retirada DESC, t.criado_em DESC
");
$stmt->execute([':fid' => $frigorifico_id]);
$lotes_agendados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8" />
<title>BovinTrade - Lotes Agendados</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{ --primary:#a30000; --text:#333; --border:#e0e0e0; --bg:#fff; }
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
.info-badge{background:#e7f3ff;border:1px solid #b3d9ff;border-radius:4px;padding:8px 12px;margin-bottom:15px;font-size:0.9rem}
.card-lote{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:1rem;margin-bottom:1rem;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
.lote-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:1px solid var(--border)}
.lote-title{font-size:1.1rem;font-weight:600;color:var(--primary)}
.lote-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1rem}
.info-group h4{color:var(--primary);margin-bottom:0.5rem;font-size:0.9rem}
.info-group p{margin:0.25rem 0;font-size:0.9rem}
.transport-group{background:#f8f9fa;padding:1rem;border-radius:6px;margin-top:1rem}
.actions{display:flex;gap:0.5rem;margin-top:1rem}
.empty-state{text-align:center;padding:3rem;color:#666}
.empty-state i{font-size:3rem;margin-bottom:1rem;color:#ccc}
.badge{display:inline-block;padding:0.25rem 0.5rem;border-radius:4px;font-size:0.8rem;font-weight:600}
.badge-success{background:#d4edda;color:#155724}
.badge-warning{background:#fff3cd;color:#856404}
</style>
</head>
<body>
<header>
  <div class="logo">🐄 BovinTrade • Frigorífico</div>
  <div class="user-info">
    <?= $email ?>
    &nbsp;
    <form action="logout.php" method="post" style="display:inline">
      <button style="background:none;border:none;color:#fff;cursor:pointer">Sair</button>
    </form>
  </div>
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
      <h2>Lotes Agendados</h2>

      <div class="info-badge">
        <i class="fas fa-info-circle"></i> 
        <strong>Lotes com transporte agendado:</strong> Estes são os lotes que tiveram o transporte confirmado e estão agendados para entrega.
      </div>

      <?php if(!$lotes_agendados): ?>
        <div class="empty-state">
          <i class="fas fa-truck-loading"></i>
          <h3>Nenhum lote agendado</h3>
          <p>Não há lotes com transporte agendado no momento.</p>
        </div>
      <?php else: ?>
        <div class="lotes-list">
          <?php foreach($lotes_agendados as $lote): 
            $valor_transporte = $lote['valor_transporte'] ? 'R$ ' . number_format($lote['valor_transporte'], 2, ',', '.') : 'Não informado';
            $valor_lote = $lote['lote_preco'] ? 'R$ ' . number_format($lote['lote_preco'], 2, ',', '.') : 'Não informado';
            $valor_total = $lote['lote_preco_total'] ? 'R$ ' . number_format($lote['lote_preco_total'], 2, ',', '.') : 'Não informado';
            $valor_total_pedido = $lote['total_pedido'] ? 'R$ ' . number_format($lote['total_pedido'], 2, ',', '.') : 'Não informado';
            $data_retirada = $lote['data_retirada'] ? date('d/m/Y', strtotime($lote['data_retirada'])) : 'Não definida';
            $hora_retirada = $lote['hora_retirada'] ?? 'Não definida';
            $data_pedido = $lote['data_pedido'] ? date('d/m/Y', strtotime($lote['data_pedido'])) : 'Não informada';
            $distancia = $lote['distancia_km'] ? $lote['distancia_km'] . ' km' : 'Não informada';
          ?>
            <div class="card-lote">
              <div class="lote-header">
                <div class="lote-title">
                  Lote #<?= (int)$lote['lote_id'] ?> - <?= htmlspecialchars($lote['codigo_lote']) ?>
                </div>
                <div class="status-agendado">
                  <i class="fas fa-calendar-check"></i> AGENDADO
                  <?php if($lote['status_aceite']): ?>
                    <span class="badge badge-success">ACEITO</span>
                  <?php else: ?>
                    <span class="badge badge-warning">AGUARDANDO ACEITE</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="lote-info">
                <div class="info-group">
                  <h4><i class="fas fa-box"></i> Informações do Lote</h4>
                  <p><strong>Código:</strong> <?= htmlspecialchars($lote['codigo_lote']) ?></p>
                  <p><strong>Descrição:</strong> <?= htmlspecialchars($lote['lote_descricao']) ?></p>
                  <p><strong>Quantidade:</strong> <?= (int)$lote['quantidade_cabecas'] ?> cabeças</p>
                  <p><strong>Peso médio:</strong> <?= htmlspecialchars($lote['lote_peso_medio']) ?> kg</p>
                  <p><strong>Raça:</strong> <?= htmlspecialchars($lote['lote_raca'] ?? 'Não informada') ?></p>
                </div>

                <div class="info-group">
                  <h4><i class="fas fa-dollar-sign"></i> Valores</h4>
                  <p><strong>Preço unitário:</strong> <?= $valor_lote ?></p>
                  <p><strong>Preço total lote:</strong> <?= $valor_total ?></p>
                  <p><strong>Total do pedido:</strong> <?= $valor_total_pedido ?></p>
                  <p><strong>Data do pedido:</strong> <?= $data_pedido ?></p>
                  <p><strong>Status pedido:</strong> <?= htmlspecialchars($lote['status_pedido']) ?></p>
                </div>

                <div class="info-group">
                  <h4><i class="fas fa-user"></i> Produtor</h4>
                  <p><strong>Nome:</strong> <?= htmlspecialchars($lote['produtor_nome']) ?></p>
                  <p><strong>Localização:</strong> <?= htmlspecialchars($lote['fazenda_cidade']) ?> - <?= htmlspecialchars($lote['fazenda_estado']) ?></p>
                  <p><strong>Localização lote:</strong> <?= htmlspecialchars($lote['localizacao'] ?? 'Não informada') ?></p>
                  <?php if($lote['historico_vacinacao']): ?>
                    <p><strong>Vacinação:</strong> <?= htmlspecialchars($lote['historico_vacinacao']) ?></p>
                  <?php endif; ?>
                  <?php if($lote['tipo_alimentacao']): ?>
                    <p><strong>Alimentação:</strong> <?= htmlspecialchars($lote['tipo_alimentacao']) ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <div class="transport-group">
                <h4><i class="fas fa-truck"></i> Transporte</h4>
                <div class="lote-info">
                  <div class="info-group">
                    <h5>Transportadora</h5>
                    <p><strong>Nome:</strong> <?= htmlspecialchars($lote['transportadora_nome']) ?></p>
                    <p><strong>Telefone:</strong> <?= htmlspecialchars($lote['transportadora_telefone'] ?? 'Não informado') ?></p>
                    <p><strong>Valor do frete:</strong> <?= $valor_transporte ?></p>
                  </div>

                  <div class="info-group">
                    <h5>Motorista</h5>
                    <p><strong>Nome:</strong> <?= htmlspecialchars($lote['motorista_nome']) ?></p>
                    <p><strong>Telefone:</strong> <?= htmlspecialchars($lote['motorista_telefone'] ?? 'Não informado') ?></p>
                  </div>

                  <div class="info-group">
                    <h5>Veículo & Agendamento</h5>
                    <p><strong>Tipo:</strong> <?= htmlspecialchars($lote['veiculo_tipo']) ?></p>
                    <p><strong>Placa:</strong> <?= htmlspecialchars($lote['veiculo_placa']) ?></p>
                    <p><strong>Data de retirada:</strong> <?= $data_retirada ?></p>
                    <p><strong>Hora de retirada:</strong> <?= $hora_retirada ?></p>
                    <p><strong>Distância:</strong> <?= $distancia ?></p>
                  </div>
                </div>
              </div>

              <div class="actions">
                <button class="btn btn-detalhes" onclick="verDetalhesLote(<?= (int)$lote['lote_id'] ?>)">
                  <i class="fas fa-eye"></i> Ver Detalhes
                </button>
                <form class="form-inline" action="cancelar-agendamento.php" method="post" onsubmit="return confirm('Tem certeza que deseja cancelar este agendamento?');">
                  <input type="hidden" name="transporte_id" value="<?= (int)$lote['transporte_id'] ?>">
                  <button type="submit" class="btn btn-excluir">
                    <i class="fas fa-times"></i> Cancelar Agendamento
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <p class="small-note" style="margin-top: 15px;">
          <strong>Total de lotes agendados:</strong> <?= count($lotes_agendados) ?> lote(s)
        </p>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
function verDetalhesLote(loteId) {
  window.location.href = 'detalhes-lote.php?id=' + loteId;
}
</script>
</body>
</html>