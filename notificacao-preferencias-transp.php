<?php
$current_page = 'notificacao-preferencias-transp.php'; // Define a página atual
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php?expired=1"); exit; }
$u = $_SESSION['usuario'];

// --- Proteção de Rota (Transportadora) ---
if (($u['tipo_usuario'] ?? '') !== 'TRANSPORTADORA') {
    if (($u['tipo_usuario'] ?? '') === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    elseif (($u['tipo_usuario'] ?? '') === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
    header('Location: login.php'); exit;
}
require_once  'config.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
$uid = (int)$u['id'];
$email = e($u['email'] ?? '');
$nome  = e($u['nome_razao'] ?? 'Transportadora');

// --- CONTADOR DE NOTIFICAÇÕES (Para a sidebar) ---
$notif_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida_em IS NULL");
$notif_count_stmt->execute([$uid]);
$unread_count = (int)$notif_count_stmt->fetchColumn();

// --- Definição dos Tipos de Notificação Relevantes para TRANSPORTADORA ---
$tipos_notificacao = [
    // Grupo 1: Solicitações
    'SOLICITACAO_TRANSPORTE' => "Nova solicitação de transporte",
    'SOLICITACAO_ATUALIZADA' => "Atualização em uma solicitação (ex: cancelada)",
    // Grupo 2: Transporte
    'COLETA_AUTORIZADA'      => "Coleta autorizada pelo frigorífico",
    'TRANSPORTE_INICIADO'    => "Confirmação de início de transporte",
    'TRANSPORTE_CONCLUIDO'   => "Confirmação de entrega",
    // Grupo 3: Pagamento
    'PAGAMENTO_LIBERADO'     => "Aviso de pagamento liberado"
];

$message = ''; // Para feedback ao salvar

// --- Lógica para Salvar Preferências ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notificacao_preferencias (usuario_id, tipo_notificacao, canal_email) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE canal_email = VALUES(canal_email)
        ");

        foreach ($tipos_notificacao as $tipo => $nomeAmigavel) {
            $email_ativo = isset($_POST['preferencias'][$tipo]['email']) ? 1 : 0;
            $stmt->execute([$uid, $tipo, $email_ativo]);
        }
        $pdo->commit();
        $message = 'Preferências salvas com sucesso!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Erro ao salvar preferências. Tente novamente.'; 
    }
}

// --- Carregar Preferências Atuais ---
$preferencias_atuais = [];
$stmtLoad = $pdo->prepare("SELECT tipo_notificacao, canal_email FROM notificacao_preferencias WHERE usuario_id = ?");
$stmtLoad->execute([$uid]);
while ($row = $stmtLoad->fetch(PDO::FETCH_ASSOC)) {
    $preferencias_atuais[$row['tipo_notificacao']] = ['email' => (bool)$row['canal_email']];
}

// Define padrões (ativo) para tipos que ainda não estão no banco
foreach ($tipos_notificacao as $tipo => $nomeAmigavel) {
    if (!isset($preferencias_atuais[$tipo])) {
        $preferencias_atuais[$tipo] = ['email' => true]; // Padrão: Email ativo
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>BovinTrade - Preferências de Notificação</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary: #a30000; --primary-light: #d43b3b; --primary-dark: #7a0000;
            --secondary: #f8f5f2; --text: #333333; --text-light: #666666;
            --background: #ffffff; --border: #e0e0e0; --success: #4caf50;
            --warning: #ff9800; --info: #2196f3; --danger: #f44336;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Montserrat',sans-serif;background:#f9f9f9;color:var(--text);}
        header{background:linear-gradient(135deg,var(--primary-dark),var(--primary));color:white;padding:1.5rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        .logo{font-size:1.8rem;font-weight:700;display:flex;align-items:center;gap:0.75rem;}
        .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
        .user-menu{display:flex;align-items:center;gap:1.5rem;}
        .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
        .user-avatar{width:40px;height:40px;border-radius:50%;background-color:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;}
        .container{display:flex;min-height:calc(100vh - 76px);}
        .sidebar{width:280px;background:var(--background);border-right:1px solid var(--border);padding:1.5rem 0;box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink: 0; transition: transform 0.3s ease;}
        .sidebar-menu{list-style:none;}
        .sidebar-menu li { list-style: none; }
        .menu-item{padding:0.8rem 1.5rem;display:flex;align-items:center;gap:0.75rem;color:var(--text);text-decoration:none;font-weight:500;border-left:3px solid transparent;transition:0.2s; position: relative;}
        .menu-item i{width:24px;text-align:center;color:var(--text-light);}
        .menu-item:hover{background-color:rgba(163,0,0,0.05);color:var(--primary);border-left:3px solid var(--primary);}
        .menu-item.active{background-color:rgba(163,0,0,0.1);color:var(--primary);border-left:3px solid var(--primary);}
        .badge { position: absolute; top: 8px; right: 12px; background: #d32f2f; color: white; font-size: 0.65rem; font-weight: 700; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .main{flex:1;padding:2.5rem;}
        .dashboard-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;}
        .dashboard-title{font-size:1.6rem;font-weight:600;color:var(--text);}
        .btn{padding:.6rem 1rem;border-radius:8px;border:1px solid var(--primary);background:var(--primary);color:#fff;font-weight:600;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:.5rem; text-decoration: none;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(163,0,0,.2);}
        .btn-outline{background:transparent;color:var(--primary); border-color: var(--primary);}
        .btn-outline:hover{background:rgba(163,0,0,.06);}
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid transparent; }
        .message-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .message-error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .preferences-form { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.07); max-width: 800px; margin: 0 auto; }
        .preference-group { margin-bottom: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 1.5rem; }
        .preference-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .preference-group h3 { font-size: 1.1rem; font-weight: 600; color: var(--primary-dark); margin-bottom: 1rem; }
        .preference-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-top: 1px solid #f0f0f0; }
        .preference-item:first-of-type { border-top: none; }
        .preference-label { font-weight: 500; color: var(--text); }
        .preference-controls { display: flex; gap: 1.5rem; align-items: center; }
        .preference-controls label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; color: var(--text-light); }
        .preference-controls input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .form-actions { margin-top: 2rem; text-align: right; }
        
        /* Toggle Switch CSS (para bater com o exemplo) */
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; border-radius: 34px; transition: .4s; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; border-radius: 50%; transition: .4s; }
        input:checked + .slider { background-color: var(--success); }
        input:checked + .slider:before { transform: translateX(22px); }

        @media (max-width: 768px){
            .hamburger { display: block; }
            .user-menu span { display: none; }
            .dashboard-header{flex-direction:column;align-items:flex-start; gap: 1rem;}
            .sidebar{width:100%;border-right:none;box-shadow:none; position: fixed; top:76px; left:0; transform: translateX(-100%); height: calc(100vh - 76px); z-index: 1000; overflow-y: auto;}
            .sidebar.active { transform: translateX(0); }
            .container{flex-direction:column;}
            .main{padding:1.5rem;}
            .preference-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .preference-controls { margin-top: 0.5rem; }
            .form-actions { text-align: left; }
        }
         @media (max-width: 480px) {
             header { padding: 1rem; }
             .logo { font-size: 1.5rem; }
         }
    </style>
</head>
<body>
<header>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="logo">🐄 <span>BovinTrade • Transportadora</span></div>
        <div class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></div>
    </div>
    <div class="user-menu">
        <span><?php echo $email; ?></span>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit" style="background:none;border:none;color:white;cursor:pointer;">Sair</button>
        </form>
        <div class="user-avatar"><i class="fas fa-user"></i></div>
    </div>
</header>

<div class="container">
    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <a href="14-painel-transportadora.php" class="menu-item <?= $current_page === '14-painel-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Painel</span></a>
            <a href="cadastro-transporte.php" class="menu-item <?= $current_page === 'cadastro-transporte.php' ? 'active' : '' ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
            <a href="cadastro-motorista.php" class="menu-item <?= $current_page === 'cadastro-motorista.php' ? 'active' : '' ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
            <a href="gerenciar-motoristas.php" class="menu-item <?= $current_page === 'gerenciar-motoristas.php' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
            <a href="gerenciar-transportes-transp.php" class="menu-item <?= $current_page === 'gerenciar-transportes-transp.php' ? 'active' : '' ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
            <a href="pedidos-transportes.php" class="menu-item <?= $current_page === 'pedidos-transportes.php' ? 'active' : '' ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
            <a href="coletas-agendadas.php" class="menu-item <?= $current_page === 'coletas-agendadas.php' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
            <a href="rastreamento-transporte-t.php" class="menu-item <?= $current_page === 'rastreamento-transporte-t.php' ? 'active' : '' ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
            <a href="historico-transporte-t.php" class="menu-item <?= $current_page === 'historico-transporte-t.php' ? 'active' : '' ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
            
            <a href="notificacoes-transportadora.php" class="menu-item <?= $current_page === 'notificacoes-transportadora.php' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i><span>Notificações</span>
                <?php if ($unread_count > 0): ?><span class="badge" id="sidebar-badge"><?= $unread_count ?></span><?php endif; ?>
            </a>
            
            <a href="minhas-avaliacoes-transportadora.php" class="menu-item <?= $current_page === 'minhas-avaliacoes-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
            <a href="17-ajudat.php" class="menu-item <?= $current_page === '17-ajudat.php' ? 'active' : '' ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
            <a href="meu-perfil-transportadora.php" class="menu-item <?= $current_page === 'meu-perfil-transportadora.php' ? 'active' : '' ?>">
                <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
            </a>
            <a href="logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i><span>Sair</span></a>
        </ul>
    </aside>

    <main class="main">
        <div class="dashboard-header">
            <h1 class="dashboard-title">Preferências de Notificação</h1>
            <a href="notificacoes-transportadora.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Voltar para Notificações</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Erro') !== false ? 'message-error' : 'message-success'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <form class="preferences-form" method="POST" action="notificacao-preferencias-transp.php">
            <p style="color: var(--text-light); margin-bottom: 2rem;">Selecione abaixo quais tipos de notificações você deseja receber por e-mail. As notificações no site sempre estarão ativas.</p>

            <div class="preference-group">
                <h3><i class="fas fa-handshake" style="color: var(--info);"></i> Solicitações e Negociações</h3>
                <?php foreach ($tipos_notificacao as $tipo => $nomeAmigavel): ?>
                    <?php if (in_array($tipo, ['SOLICITACAO_TRANSPORTE', 'SOLICITACAO_ATUALIZADA'])): ?>
                        <div class="preference-item">
                            <span class="preference-label"><?php echo e($nomeAmigavel); ?></span>
                            <div class="preference-controls">
                                <label class="switch">
                                    <input type="checkbox" 
                                           name="preferencias[<?php echo e($tipo); ?>][email]" 
                                           value="1" 
                                           <?php echo ($preferencias_atuais[$tipo]['email'] ?? true) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="preference-group">
                <h3><i class="fas fa-truck" style="color: var(--warning);"></i> Alertas de Transporte</h3>
                <?php foreach ($tipos_notificacao as $tipo => $nomeAmigavel): ?>
                    <?php if (in_array($tipo, ['COLETA_AUTORIZADA', 'TRANSPORTE_INICIADO', 'TRANSPORTE_CONCLUIDO', 'ALERTA_GERAL'])): ?>
                        <div class="preference-item">
                            <span class="preference-label"><?php echo e($nomeAmigavel); ?></span>
                            <div class="preference-controls">
                                <label class="switch">
                                    <input type="checkbox" 
                                           name="preferencias[<?php echo e($tipo); ?>][email]" 
                                           value="1" 
                                           <?php echo ($preferencias_atuais[$tipo]['email'] ?? true) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="preference-group">
                <h3><i class="fas fa-money-check-alt" style="color: var(--success);"></i> Notificações de Pagamento</h3>
                <?php foreach ($tipos_notificacao as $tipo => $nomeAmigavel): ?>
                    <?php if (in_array($tipo, ['PAGAMENTO_LIBERADO'])): ?>
                        <div class="preference-item">
                            <span class="preference-label"><?php echo e($nomeAmigavel); ?></span>
                            <div class="preference-controls">
                                <label class="switch">
                                    <input type="checkbox" 
                                           name="preferencias[<?php echo e($tipo); ?>][email]" 
                                           value="1" 
                                           <?php echo ($preferencias_atuais[$tipo]['email'] ?? true) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Preferências</button>
            </div>
        </form>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        // --- Sidebar Mobile Toggle ---
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger');
            if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        // --- Resizer (ativado em desktop) ---
        const resizer = document.querySelector('.resizer');
        if (window.innerWidth > 768 && resizer) {
            resizer.style.display = 'flex';
            const sidebar = document.querySelector('.sidebar');
            const container = document.querySelector('.container');
            let isResizing = false;

            resizer.addEventListener('mousedown', function(e) {
                e.preventDefault();
                isResizing = true;
                document.addEventListener('mousemove', resize);
                document.addEventListener('mouseup', stopResize);
                container.style.cursor = 'col-resize';
            });

            function resize(e) {
                if (!isResizing) return;
                let newWidth = e.clientX; 
                if (newWidth < 200) newWidth = 200;
                if (newWidth > 400) newWidth = 400;
                sidebar.style.width = newWidth + 'px';
            }

            function stopResize() {
                isResizing = false;
                document.removeEventListener('mousemove', resize);
                document.removeEventListener('mouseup', stopResize);
                container.style.cursor = '';
            }
        }
    });
</script>
</body>
</html>
