<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once 'config.php'; // Conexão PDO

// Proteção de rota
if (empty($_SESSION['usuario']) || ($_SESSION['usuario']['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    header("Location: login.php"); exit;
}

// ----------------------------------------------------
// FUNÇÃO DE NOTIFICAÇÃO (Definida neste arquivo)
// ----------------------------------------------------
/**
 * Insere uma notificação no banco de dados para um usuário específico.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $usuario_id ID do usuário que deve receber a notificação.
 * @param string $tipo Tipo da notificação (deve ser um dos tipos definidos na tabela 'notificacoes').
 * @param string $titulo Título da notificação.
 * @param string $mensagem Mensagem detalhada.
 * @param string $tabela Tabela relacionada (opcional, ex: 'transportes').
 * @param int $id_relacionado ID da linha relacionada (opcional).
 * @param array $dados_array Dados adicionais (serão convertidos para JSON).
 * @return bool Retorna true em caso de sucesso, false caso contrário.
 */
function criar_notificacao($pdo, $usuario_id, $tipo, $titulo, $mensagem, $tabela = null, $id_relacionado = null, $dados_array = []) {
    if (!$usuario_id) return false;

    // Converte os dados adicionais para JSON
    $dados_json = $dados_array ? json_encode($dados_array) : null;

    $sql = "INSERT INTO notificacoes
                (usuario_id, tipo, titulo, mensagem, relacionado_tabela, relacionado_id, dados_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $usuario_id,
            $tipo,
            $titulo,
            $mensagem,
            $tabela,
            $id_relacionado,
            $dados_json
        ]);
    } catch (PDOException $e) {
        // Em ambiente de produção, registre o erro:
        error_log("Erro ao criar notificação: " . $e->getMessage());
        return false;
    }
}


$u = $_SESSION['usuario'];
$frigorifico_id = $u['id'];
$nome = htmlspecialchars($u['nome_razao'] ?? 'Frigorífico');
$email = htmlspecialchars($u['email'] ?? '');
$msg = $_GET['msg'] ?? ''; // Para mensagens de feedback via GET

// ----------------------------------------------------
// AÇÃO POST: Autorizar Coleta (CONFIRMADO -> AUTORIZADO) (Atualizado com Notificação)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transporte_id'], $_POST['acao']) && $_POST['acao'] === 'autorizar_coleta') {
    $transporte_id = (int)$_POST['transporte_id'];

    try {
        // 1. OBTÉM os IDs necessários antes de atualizar
        $stmt_get = $pdo->prepare("SELECT transportadora_id, pedido_id, fazenda_id FROM transportes WHERE id = :tid AND frigorifico_id = :fid AND status = 'CONFIRMADO'");
        $stmt_get->execute([':tid' => $transporte_id, ':fid' => $frigorifico_id]);
        $transporte_data = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if (!$transporte_data) {
             $msg = "❌ Erro: Transporte não encontrado ou não está no status CONFIRMADO para autorização.";
             // Não redireciona aqui para exibir a mensagem na mesma página
        } else {
            $transportadora_id = (int)$transporte_data['transportadora_id'];
            $pedido_id = (int)$transporte_data['pedido_id'];
            $fazenda_id = (int)$transporte_data['fazenda_id'];
            $novo_status = 'AUTORIZADO';

            // 2. AÇÃO: AUTORIZAR COLETA (Muda de CONFIRMADO -> AUTORIZADO)
            $stmt = $pdo->prepare("UPDATE transportes SET
                status = :novo_status,
                atualizado_em = NOW()
                WHERE id = :tid AND frigorifico_id = :fid AND status = 'CONFIRMADO'");
            $updateSuccess = $stmt->execute([':novo_status' => $novo_status, ':tid' => $transporte_id, ':fid' => $frigorifico_id]);

            if ($updateSuccess && $stmt->rowCount() > 0) {
                $msg = "✅ Coleta Autorizada! A Transportadora foi notificada e pode iniciar o rastreamento.";

                // 3. NOTIFICAÇÕES (Usando a função definida acima)
                $dados_notificacao = ['transporte_id' => $transporte_id, 'pedido_id' => $pedido_id, 'novo_status' => $novo_status];

                // Notificação para a TRANSPORTADORA
                criar_notificacao($pdo,
                    $transportadora_id,
                    'TRANSPORTE_ACEITO', // Usando tipo genérico de aceite/progresso
                    "🚚 Coleta Autorizada pelo Frigorífico",
                    "O frigorífico autorizou o início da coleta do Pedido #{$pedido_id} (Transporte #{$transporte_id}). Você já pode começar o rastreamento.",
                    'transportes',
                    $transporte_id,
                    $dados_notificacao);

                // Notificação para a FAZENDA
                criar_notificacao($pdo,
                    $fazenda_id,
                    'TRANSPORTE_ACEITO', // Usando tipo genérico de aceite/progresso
                    "✅ Transporte do Pedido #{$pedido_id} Autorizado",
                    "O Frigorífico autorizou o transporte #{$transporte_id}. A transportadora iniciará o trajeto em breve.",
                    'transportes',
                    $transporte_id,
                    $dados_notificacao);

                // Redireciona APENAS em caso de SUCESSO para evitar reenvio
                 header('Location: autorizar-coleta-frig.php?msg=' . urlencode($msg));
                 exit;

            } else if ($updateSuccess && $stmt->rowCount() === 0) {
                 $msg = "⚠️ Nenhuma alteração realizada. O transporte pode já ter sido autorizado.";
            } else {
                // Caso execute() retorne false
                 throw new Exception("Falha ao executar a atualização no banco de dados.");
            }
        } // Fim do else $transporte_data

    } catch (Throwable $e) {
        $msg = "❌ Erro ao processar a ação: " . htmlspecialchars($e->getMessage()); // Usar htmlspecialchars
    }
    // Se chegou aqui (erro ou nenhuma alteração), não redireciona, exibe $msg na página
}

// ----------------------------------------------------
// CONSULTA CORRIGIDA: Buscar transportes ATIVOS (Todas as fases de rastreamento)
// ADICIONADO: t.data_prevista_entrega
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.pedido_id,
        t.data_retirada,
        t.hora_retirada,
        t.distancia_km,
        t.status,
        t.data_prevista_entrega, -- <<< CAMPO ADICIONADO AQUI
        tr.nome_razao AS transportadora_nome,
        f.nome_razao AS fazenda_nome,
        m.nome AS motorista_confirmado,
        v.placa AS veiculo_confirmado_placa
    FROM transportes t
    JOIN usuarios tr ON tr.id = t.transportadora_id
    JOIN usuarios f ON f.id = t.fazenda_id
    LEFT JOIN motorista m ON m.id = t.motorista_id
    LEFT JOIN veiculo v ON v.id = t.veiculo_id

    WHERE t.frigorifico_id = :fid
      AND t.status IN (
          'CONFIRMADO', 'AUTORIZADO',
          'EM_TRANSITO_ORIGEM', 'CHEGOU_NA_FAZENDA',
          'EM_TRANSITO_DESTINO', 'CHEGOU_NO_FRIGORIFICO'
      )
    ORDER BY t.data_retirada ASC, t.hora_retirada ASC -- Ordena por hora também
");
$stmt->execute([':fid' => $frigorifico_id]);
$transportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>BovinTrade - Autorizar Coleta</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Seu CSS Padrão aqui */
:root {
    --primary: #a30000; --primary-dark: #7a0000; --text: #333333;
    --text-light: #666666; --background: #ffffff; --border: #e0e0e0;
    --success-bg: #d4edda; --success-border: #c3e6cb; --success-text: #155724;
    --error-bg: #f8d7da; --error-border: #f5c6cb; --error-text: #721c24;
    --warning-bg: #fff3cd; --warning-border: #ffeeba; --warning-text: #856404;
    --info-bg: #cce5ff; --info-border: #b8daff; --info-text: #004085; /* Azul para em trânsito */
}
*{ margin:0; padding:0; box-sizing:border-box; }
body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); overflow-x: hidden; }
header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1001;}
.logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
.hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
.user-menu{ display:flex; align-items:center; gap:1.5rem; }
.user-menu form button { background: none; border: none; color: white; cursor: pointer; font-size: 1rem; }
.user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
.container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
.sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; height: calc(100vh - 76px); position: sticky; top: 76px; overflow-y: auto;}
.sidebar-menu{ list-style:none; }
.menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
.menu-item i{ width:24px; text-align:center; color:var(--text-light); }
.menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
.menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
.main{ flex:1; padding:2.5rem; min-width:0; }
.dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;}
.dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
.btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem;}
.btn-primary { background-color: var(--primary); color:white;}
.btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
.btn-success { background-color: #0a6b2b; color:white; border:1px solid #0a6b2b;}
.btn-success:hover { background-color: #074d1e; }
.btn-sm { padding:0.5rem 1rem; font-size:0.85rem;}
.card { background: var(--background); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); max-width: 100%; margin: auto; overflow-x: auto; } /* overflow-x */
h2.card-title { color: var(--primary); text-align: center; margin-bottom: 1.5rem; font-weight: 600;}
.table-wrapper { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; table-layout: auto; min-width: 1000px; }
th, td { padding: 0.75rem 1rem; border: 1px solid var(--border); text-align: left; vertical-align: middle; font-size: 0.9rem;}
th { background: var(--primary); color: white; font-weight: 600; white-space: nowrap; }
tr:nth-child(even) td { background-color: #f8f9fa; }
.status-tag { padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.85rem; text-transform: capitalize; border: 1px solid transparent; }
.status-CONFIRMADO { background: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }
.status-AUTORIZADO { background: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
.status-EMTRANSITOORIGEM, .status-EMTRANSITODESTINO { background: var(--info-bg); color: var(--info-text); border-color: var(--info-border); }
.status-CHEGOUNAFAZENDA { background: #e2f0d9; color: #385723; border-color: #c5e0b4; } /* Verde mais claro */
.status-CHEGOUNOFRIGORIFICO { background: #fdebd0; color: #7f6000; border-color: #fbe5d6; } /* Laranja claro */
.msg-alerta { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-weight: 500; border: 1px solid transparent; }
.msg-alerta.ok { background: var(--success-bg); color: var(--success-text); border-color: var(--success-border); }
.msg-alerta.erro { background: var(--error-bg); color: var(--error-text); border-color: var(--error-border); }
.msg-alerta.aviso { background: var(--warning-bg); color: var(--warning-text); border-color: var(--warning-border); }
.no-transportes { text-align: center; color: var(--text-light); padding: 2rem; font-size: 1.1rem; }
@media (max-width: 992px) {
   .sidebar { transform: translateX(-100%); position: fixed; top: 76px; left: 0; height: calc(100vh - 76px); z-index: 1000; }
   .sidebar.active { transform: translateX(0); }
   .hamburger { display: block; }
   .main { width: 100%; }
}
@media (max-width: 768px) {
  .container { flex-direction: column; }
  .sidebar { width: 100%; border-right: none; box-shadow: none; position: fixed; top:76px; left:0; transform: translateX(-100%); height: calc(100vh - 76px); overflow-y: auto;}
  .sidebar.active { transform: translateX(0); z-index: 1000;}
  .main { padding: 1rem; width: 100%; }
  th, td { white-space: normal; }
  .dashboard-title { font-size: 1.5rem; }
}
@media (max-width: 480px) {
  header { padding: 1rem; }
  .logo { font-size: 1.5rem; }
  .user-menu { gap: 0.5rem; }
  .user-menu span { display: none; }
  .main { padding: 0.5rem; }
  .card { padding: 1rem; }
}
</style>
</head>
<body>
<header>
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">
      🐄
      <span>BovinTrade • Frigorífico</span>
    </div>
    <div class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </div>
  </div>
  <div class="user-menu">
    <span><?php echo $email; ?></span>
    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit">Sair</button>
    </form>
    <div class="user-avatar"><i class="fas fa-user"></i></div>
  </div>
</header>

<div class="container">
  <aside class="sidebar" id="sidebar">
   <ul class="sidebar-menu">
        <li><a href="07-painel-frigorifico.php" class="menu-item <?= $current_page == '07-painel-frigorifico.php' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Painel</span></a></li>
        <li><a href="meu-carrinho.php" class="menu-item <?= $current_page == 'meu-carrinho.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i><span>Meu Carrinho</span></a></li>
        <li><a href="pesquisa-lotes.php" class="menu-item <?= $current_page == 'pesquisa-lotes.php' ? 'active' : '' ?>"><i class="fas fa-search"></i><span>Pesquisa de Lotes</span></a></li>
        <li><a href="09-recebimento-lotes.php" class="menu-item <?= $current_page == '09-recebimento-lotes.php' ? 'active' : '' ?>"><i class="fas fa-truck-loading"></i><span>Recebimento</span></a></li>
        <li><a href="10-historico-compras.php" class="menu-item <?= $current_page == '10-historico-compras.php' ? 'active' : '' ?>"><i class="fas fa-history"></i><span>Histórico de Compras</span></a></li>
        <li><a href="11-historico-pagamentos.php" class="menu-item <?= $current_page == '11-historico-pagamentos.php' ? 'active' : '' ?>"><i class="fas fa-credit-card"></i><span>Histórico de Pagamento</span></a></li>
        <li><a href="autorizar-coleta-frig.php" class="menu-item <?= $current_page == 'autorizar-coleta-frig.php' ? 'active' : '' ?>"><i class="fas fa-check"></i><span>Autorizar Coleta de Lote</span></a></li>
        <li><a href="historico-transporte-frig.php" class="menu-item <?= $current_page == 'historico-transporte-frig.php' ? 'active' : '' ?>"><i class="fas fa-truck"></i><span>Histórico de Transportes</span></a></li>
        <li><a href="12-avaliacoes.php" class="menu-item <?= $current_page == '12-avaliacoes.php' ? 'active' : '' ?>"><i class="fas fa-star"></i><span>Avaliações</span></a></li>
        <li><a href="notificacoes-frigorifico.php" class="menu-item <?= $current_page == 'notificacoes-frigorifico.php' ? 'active' : '' ?>"><i class="fas fa-bell"></i><span>Notificações</span></a></li>
        <li><a href="17-ajuda.php" class="menu-item <?= $current_page == '17-ajuda.php' ? 'active' : '' ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a></li>
        <li><a href="meu-perfil-frigorifico.php" class="menu-item <?= $current_page == 'meu-perfil-frigorifico.php' ? 'active' : '' ?>"><i class="fas fa-user-cog"></i><span>Meu Perfil</span></a></li>
   </ul>
  </aside>
  <main class="main">
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-check-circle"></i> Autorizar Coleta de Lote</h1>
    </div>

    <div class="card">
       <h2 class="card-title">Monitoramento e Autorização de Coleta</h2>

        <?php if ($msg): ?>
          <div class="msg-alerta <?= strpos($msg, '✅') !== false ? 'ok' : (strpos($msg, '⚠️') !== false ? 'aviso' : 'erro') ?>">
               <?= htmlspecialchars(urldecode($msg)) ?>
          </div>
        <?php endif; ?>

        <?php if(count($transportes) === 0): ?>
          <p class="no-transportes">Nenhum transporte pendente de autorização ou em andamento no momento.</p>
        <?php else: ?>
           <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Pedido</th>
                    <th>Fazenda Origem</th>
                    <th>Transportadora</th>
                    <th>Motorista/Veículo</th>
                    <th>Data Retirada</th>
                    <th>Prev. Entrega</th> <th>Status</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($transportes as $t):
                       // Formata a data prevista
                       $dataPrevistaFmt = !empty($t['data_prevista_entrega']) ? date('d/m/Y', strtotime($t['data_prevista_entrega'])) : 'N/A';
                       // Limpa o _ dos status para exibição e classe CSS
                       $statusLimpo = str_replace('_', ' ', $t['status']);
                       $statusClasse = str_replace('_', '', $t['status']); // Para classe CSS sem _
                  ?>
                    <tr>
                      <td>#<?= $t['pedido_id'] ?></td>
                      <td><?= htmlspecialchars($t['fazenda_nome']) ?></td>
                      <td><?= htmlspecialchars($t['transportadora_nome']) ?></td>
                      <td><?= htmlspecialchars($t['motorista_confirmado'] ?? '---') ?> / <?= htmlspecialchars($t['veiculo_confirmado_placa'] ?? '---') ?></td>
                      <td><?= date('d/m/Y', strtotime($t['data_retirada'])) ?> às <?= substr($t['hora_retirada'], 0, 5) ?></td>
                      <td><?= $dataPrevistaFmt ?></td> <td>
                          <span class="status-tag status-<?= $statusClasse ?>"><?= $statusLimpo ?></span>
                      </td>
                      <td>
                        <?php if ($t['status'] === 'CONFIRMADO'): ?>
                          <form method="post" onsubmit="return confirm('Confirmar autorização de coleta?\nIsso notificará a Transportadora e a Fazenda para iniciar o trajeto.');">
                            <input type="hidden" name="transporte_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="acao" value="autorizar_coleta">
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-circle"></i> Autorizar Coleta</button>
                          </form>
                        <?php else: ?>
                          <span class="btn-sm" style="background:none; border:none; color:var(--text-light); cursor: default; white-space: nowrap;">
                               <i class="fas fa-truck"></i> Em andamento...
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
           </div>
        <?php endif; ?>
    </div>
  </main>
</div>

<script>
 // Função para sidebar mobile (se aplicável)
 function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
 }

 // Fecha sidebar se clicar fora (em mobile)
 document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger');
    // Se sidebar existe, está ativa, o clique NÃO foi no hamburger e NÃO foi dentro da sidebar
    if (sidebar && sidebar.classList.contains('active') && hamburger && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
        sidebar.classList.remove('active');
    }
 });
</script>

</body>
</html>