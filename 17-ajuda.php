<?php
// Força o PHP a mostrar os erros (IMPORTANTE para debug)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require 'conexao.php'; // Usa o seu arquivo de conexão

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
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issss', $usuario_id, $nome_usuario, $email_usuario, $assunto, $mensagem);
        
        if ($stmt->execute()) {
            $mensagem_sucesso = "Sua solicitação foi enviada com sucesso! Entraremos em contato em breve pelo email $email_usuario.";
        } else {
            throw new Exception("Falha ao registrar sua solicitação. Tente novamente.");
        }
    }

} catch (Exception $e) {
    $mensagem_erro = $e->getMessage();
}

// Pegar o tipo de usuário para saber qual sidebar mostrar
$tipo_usuario_sidebar = $u['tipo_usuario'] ?? '';

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
        /* 💡 ESTILO CORRIGIDO: Adicionado estilo para o ícone do logo */
        .logo i{ font-size:1.6rem; }
        .user-menu{ display:flex; align-items:center; gap:1.5rem; }
        .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
        .container{ display:flex; min-height:calc(100vh - 76px); }
        
        /* 🎨 Estilo da Sidebar */
        .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); }
        .sidebar-menu{ list-style:none; }
        .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
        .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
        .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
        .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
        
        .main{ flex:1; padding:2.5rem; }
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 2rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; }
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

    </style>
</head>
<body>
<header>
    <div class="logo">
        🐄
        <span>BovinTrade • <?php echo ucfirst(strtolower($tipo_usuario_sidebar)); ?></span>
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
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <?php if ($tipo_usuario_sidebar === 'FRIGORIFICO'): ?>
                <a href="07-painel-frigorifico.php" class="menu-item <?= $current_page === '07-painel-frigorifico.php' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Painel</span></a>
                <a href="meu-carrinho.php" class="menu-item <?= $current_page === 'meu-carrinho.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart"></i><span>Meu Carrinho</span></a>
                <a href="pesquisa-lotes.php" class="menu-item <?= $current_page === 'pesquisa-lotes.php' ? 'active' : '' ?>"><i class="fas fa-search"></i><span>Pesquisa de Lotes</span></a>
                <a href="09-recebimento-lotes.php" class="menu-item <?= $current_page === '09-recebimento-lotes.php' ? 'active' : '' ?>"><i class="fas fa-truck-loading"></i><span>Recebimento</span></a>
                <a href="10-historico-compras.php" class="menu-item <?= $current_page === '10-historico-compras.php' ? 'active' : '' ?>"><i class="fas fa-history"></i><span>Histórico de Compras</span></a>
                <a href="11-historico-pagamentos.php" class="menu-item <?= $current_page === '11-historico-pagamentos.php' ? 'active' : '' ?>"><i class="fas fa-credit-card"></i><span>Histórico de Pagamento</span></a>
                <a href="autorizar-coleta-frig.php" class="menu-item <?= $current_page === 'autorizar-coleta-frig.php' ? 'active' : '' ?>"><i class="fas fa-check"></i><span>Autorizar Coleta de Lote</span></a>
                <a href="historico-transporte-frig.php" class="menu-item <?= $current_page === 'historico-transporte-frig.php' ? 'active' : '' ?>"><i class="fas fa-truck"></i><span>Histórico de Transportes</span></a>
                <a href="12-avaliacoes.php" class="menu-item <?= $current_page === '12-avaliacoes.php' ? 'active' : '' ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
                <a href="notificacoes-frigorifico.php" class="menu-item <?= $current_page === 'notificacoes-frigorifico.php' ? 'active' : '' ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
                <a href="17-ajuda.php" class="menu-item <?= $current_page === '17-ajuda.php' ? 'active' : '' ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
                <a href="meu-perfil-frigorifico.php" class="menu-item <?= $current_page === 'meu-perfil-frigorifico.php' ? 'active' : '' ?>"><i class="fas fa-user-cog"></i><span>Meu Perfil</span></a>

            <?php elseif ($tipo_usuario_sidebar === 'FAZENDA'): ?>
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


            <?php elseif ($tipo_usuario_sidebar === 'TRANSPORTADORA'): ?>
                <a href="14-painel-transportadora.php" class="menu-item <?= $current_page === '14-painel-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-home"></i><span>Painel</span></a>
<a href="cadastro-transporte.php" class="menu-item <?= $current_page === 'cadastro-transporte.php' ? 'active' : '' ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
<a href="cadastro-motorista.php" class="menu-item <?= $current_page === 'cadastro-motorista.php' ? 'active' : '' ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
<a href="gerenciar-motoristas.php" class="menu-item <?= $current_page === 'gerenciar-motoristas.php' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
<a href="gerenciar-transportes-transp.php" class="menu-item <?= $current_page === 'gerenciar-transportes-transp.php' ? 'active' : '' ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
<a href="pedidos-transportes.php" class="menu-item <?= $current_page === 'pedidos-transportes.php' ? 'active' : '' ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
<a href="coletas-agendadas.php" class="menu-item <?= $current_page === 'coletas-agendadas.php' ? 'active' : '' ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
<a href="rastreamento-transporte-t.php" class="menu-item <?= $current_page === 'rastreamento-transporte-t.php' ? 'active' : '' ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
<a href="historico-transporte-t.php" class="menu-item <?= $current_page === 'historico-transporte-t.php' ? 'active' : '' ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
<a href="notificacoes-transportadora.php" class="menu-item <?= $current_page === 'notificacoes-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
<a href="minhas-avaliacoes-transportadora.php" class="menu-item <?= $current_page === 'minhas-avaliacoes-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
<a href="17-ajudat.php" class="menu-item <?= $current_page === '17-ajudat.php' ? 'active' : '' ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
<a href="meu-perfil-transportadora.php" class="menu-item <?= $current_page === 'meu-perfil-transportadora.php' ? 'active' : '' ?>"><i class="fas fa-user-cog"></i><span>Meu Perfil</span></a>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="main">
<h1 class="page-title"><i class="fas fa-question-circle"></i> Ajuda & Suporte</h1>
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
</body>
</html>