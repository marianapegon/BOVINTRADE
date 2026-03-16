<?php
// Força o PHP a mostrar os erros (IMPORTANTE para debug)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php'; // Usa o seu arquivo de conexão

// --- 1. PROTEÇÃO DE ROTA E SESSÃO ---
if (empty($_SESSION['usuario'])) {
    header('Location: login.php'); exit;
}
$u = $_SESSION['usuario'];
// Esta página pode ser acessada por qualquer tipo de usuário logado
// Se quiser restringir, descomente e ajuste as linhas abaixo
/*
if (($u['tipo_usuario'] ?? '') !== 'FRIGORIFICO') {
    if ($u['tipo_usuario'] === 'FAZENDA')       { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}
*/

$nome_usuario   = htmlspecialchars($u['nome_razao'] ?? 'Usuário');
$email_usuario  = htmlspecialchars($u['email'] ?? '');
$usuario_id     = (int)$u['id']; // ID do usuário logado

// 🔑 ADICIONADO: Define a página atual para a sidebar
$current_page = basename($_SERVER['PHP_SELF']);

// --- 2. INICIALIZAÇÃO DE VARIÁVEIS ---
$mensagem_sucesso = '';
$mensagem_erro = '';

// Pegar o tipo de usuário para saber qual sidebar mostrar
$tipo_usuario_sidebar = $u['tipo_usuario'] ?? '';

try {
    // --- 3. PROCESSAMENTO DO FORMULÁRIO (SE FOR UM POST) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $assunto   = $_POST['assunto'] ?? '';
        $mensagem = $_POST['mensagem'] ?? '';

        if (empty($assunto) || empty($mensagem)) {
            throw new Exception("Erro: Assunto e Mensagem são obrigatórios.");
        }
        
        if (strlen($mensagem) < 10) {
            throw new Exception("Erro: A mensagem precisa ter pelo menos 10 caracteres.");
        }

        // Inserir o ticket de suporte no banco de dados
        $sql = "INSERT INTO suporte_tickets (usuario_id, nome_contato, email_contato, assunto, mensagem) 
                         VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id, $nome_usuario, $email_usuario, $assunto, $mensagem]);
        
        $mensagem_sucesso = "Sua solicitação foi enviada com sucesso! Entraremos em contato em breve pelo email $email_usuario.";

    }

} catch (Exception $e) {
    $mensagem_erro = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>BovinTrade - Ajuda & Suporte</title>
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
            --background-light: #f9f9f9;
        }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body{ font-family:'Montserrat',sans-serif; background:var(--background-light); color:var(--text); }
        header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
        .logo i{ font-size:1.6rem; }
        .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
        .user-menu{ display:flex; align-items:center; gap:1.5rem; }
        .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
        .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
        .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
        .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; }
        .resizer {
          width: 5px;
          background: var(--border);
          cursor: col-resize;
          height: 100%;
          display: flex;
          align-items: center;
        }
        .resizer:hover {
          background: var(--primary);
        }
        .sidebar-menu{ list-style:none; }
        .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
        .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
        .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
        .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
        .main{ flex:1; padding:2.5rem; min-width:0; }
        .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap: wrap;}
        .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
        .dashboard-actions { display:flex; gap:1rem;}
        .card { background: var(--background); border-radius: 12px; padding: 2.5rem; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        /* --- ESTILOS DESTA PÁGINA --- */
        .support-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: flex-start;
        }
        /* Media query para telas menores */
        @media (max-width: 992px) {
            .support-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input[type="text"],
        .form-group textarea { 
            width: 100%; 
            padding: 0.75rem; 
            border-radius: 6px; 
            border: 1px solid var(--border); 
            font-family: 'Montserrat', sans-serif; 
        }
        .form-group textarea { min-height: 150px; }

        .btn-submit { background-color: var(--primary); color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.2s; margin-top: 1rem; }
        .btn-submit:hover { background-color: var(--primary-dark); }

        .contact-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
        }
        .contact-info h3:first-of-type { margin-top: 0; }
        .contact-info p {
            margin-bottom: 0.75rem;
            color: var(--text-light);
            line-height: 1.6;
        }
        .contact-info p i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
            color: var(--primary);
        }
        .contact-info p strong { color: var(--text); }
        
        .alert { padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-radius: 6px; font-weight: 500; }
        .alert-sucesso { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 768px) {
          .hamburger { display: block; }
          .user-menu { gap: 1rem; }
          .user-menu span { display: none; }
          .container {
            flex-direction: column;
          }
          .sidebar {
            width: 100%;
            transform: translateX(-100%);
            position: fixed;
            top: 76px;
            left: 0;
            height: calc(100vh - 76px);
            z-index: 1000;
            overflow-y: auto;
            box-shadow: none;
            border-right: none;
          }
          .sidebar.active {
            transform: translateX(0);
          }
          .resizer {
            display: none;
          }
          .main {
            padding: 1rem;
            width: 100%;
          }
          .dashboard-header{ flex-direction:column; align-items:flex-start; gap:1rem; }
        }
        @media (max-width:480px){ header{ padding:1rem; } .logo{ font-size:1.5rem; } .user-menu span{ display:none; } .main{ padding:0.8rem; } }
    </style>
</head>
<body>
<header>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="logo">
            🐄
            <span>BovinTrade • <?php echo ucfirst(strtolower($tipo_usuario_sidebar)); ?></span>
        </div>
        <div class="hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </div>
    </div>
    <div class="user-menu">
        <span><?php echo $email_usuario; ?></span>
        <form action="logout.php" method="post" style="display:inline;">
            <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
        </form>
        <div class="user-avatar"><i class="fas fa-user"></i></div>
    </div>
</header>
<div class="container">
  <aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
      <a href="14-painel-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '14-painel-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Painel</span></a>
      <a href="cadastro-transporte.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-transporte.php' ? 'active' : ''; ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
      <a href="cadastro-motorista.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-motorista.php' ? 'active' : ''; ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
       <a href="gerenciar-motoristas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-motoristas.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
        <a href="gerenciar-transportes-transp.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-transportes-transp.php' ? 'active' : ''; ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
      <a href="pedidos-transportes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pedidos-transportes.php' ? 'active' : ''; ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
      <a href="coletas-agendadas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'coletas-agendadas.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
      <a href="rastreamento-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'rastreamento-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
      <a href="historico-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'historico-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
      <a href="notificacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'notificacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
      <a href="minhas-avaliacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'minhas-avaliacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
      <a href="17-ajudat.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '17-ajudat.php' ? 'active' : ''; ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
      <a href="meu-perfil-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'meu-perfil-transportadora.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
      </a>
    </ul>
  </aside>
  <div class="resizer"></div>

    <main class="main">
        <div class="dashboard-header">
            <h1 class="dashboard-title"><i class="fas fa-question-circle"></i> Ajuda & Suporte</h1>
            <div class="dashboard-actions">
                <!-- Adicione ações se necessário, como exportar ou filtrar -->
            </div>
        </div>

        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-sucesso"><?php echo $mensagem_sucesso; ?></div>
        <?php endif; ?>
        <?php if ($mensagem_erro): ?>
            <div class="alert alert-erro"><?php echo htmlspecialchars($mensagem_erro); ?></div>
        <?php endif; ?>

        <div class="support-grid">
              
                <div class="card support-form">
            <h2>Enviar uma Solicitação</h2>
            <p style="margin-top: 5px; color: var(--text-light); margin-bottom: 2rem;">
                Encontrou um problema ou tem uma dúvida? Preencha o formulário abaixo e nossa equipe de administradores responderá o mais rápido possível.
            </p>
            
            <form action="17-ajuda.php" method="POST">
                <div class="form-group">
                    <label for="assunto">Assunto</label>
                    <input type="text" id="assunto" name="assunto" placeholder="Ex: Dúvida sobre pagamento" required>
                </div>
                
                <div class="form-group">
                    <label for="mensagem">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" rows="6" placeholder="Descreva seu problema ou dúvida em detalhes..." required minlength="10"></textarea>
                </div>
                
                    <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
                <input type="hidden" name="nome_contato" value="<?php echo $nome_usuario; ?>">
                <input type="hidden" name="email_contato" value="<?php echo $email_usuario; ?>">
                
                <button type="submit" class="btn-submit">Enviar Solicitação</button>
            </form>
        </div>

                <div class="card contact-info">
            <h2>Informações de Contato</h2>
            <p style="color: var(--text-light); margin-top: 5px;">
                Para questões urgentes ou contato direto com a equipe de desenvolvimento.
            </p>
            
            <h3><i class="fas fa-users"></i> Desenvolvedores</h3>
            <p>
                        <strong>Elisandra Carol da Silva</strong><br>
                    <i class="fas fa-envelope"></i> elicarol@gmail.com
            </p>
            <p>
                        <strong>Fábio Ribeiro Barbosa</strong><br>
                    <i class="fas fa-envelope"></i> fabioribeiro2@gmail.com
            </p>
               <p>
                        <strong>Maria Clara Soares Bertolo</strong><br>
                    <i class="fas fa-envelope"></i> mariabertolo@gmail.com
            </p>
               <p>
                        <strong>Mariana Pereira Gonçalves</strong><br>
                    <i class="fas fa-envelope"></i> marianapereira@gmail.com
            </p>
               <p>
                        <strong>Thaissa Rodrigues Martins</strong><br>
                    <i class="fas fa-envelope"></i> thaissarodrigues@gmail.com
            </p>

            <h3><i class="fas fa-phone"></i> Telefone de Suporte</h3>
            <p>
                Phones:<br>
                <i class="fas fa-phone-alt"></i> +55 (14) 99894-0708 <br>
                <i class="fas fa-phone-alt"></i> +55 (14) 3344-2794
            </p>
        </div>
        
        </div>

    </main>
</div>

<script>
// Função para alternar a sidebar em dispositivos móveis
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function() {
    // Lógica de fechamento do sidebar em mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger');
        if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
    
    // Resizer functionality (copiado do exemplo para a barra lateral redimensionável)
    let isResizing = false;
    const resizer = document.querySelector('.resizer');
    const sidebar = document.querySelector('.sidebar');
    const container = document.querySelector('.container');
    
    // Só adiciona funcionalidade de redimensionamento em telas maiores
    if (window.innerWidth > 768 && resizer) {
        resizer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isResizing = true;
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
            container.style.cursor = 'col-resize';
        });
    }

    function resize(e) {
        if (!isResizing) return;
        let newWidth = e.clientX - sidebar.getBoundingClientRect().left;
        if (newWidth < 200) newWidth = 200;
        let maxWidth = window.innerWidth - 100;
        if (newWidth > maxWidth / 2) newWidth = maxWidth / 2; 
        sidebar.style.width = newWidth + 'px';
    }

    function stopResize() {
        isResizing = false;
        document.removeEventListener('mousemove', resize);
        document.removeEventListener('mouseup', stopResize);
        container.style.cursor = '';
    }
});
</script>
</body>
</html>