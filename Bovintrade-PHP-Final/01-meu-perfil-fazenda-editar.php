<?php
// 01-meu-perfil-fazenda-editar.php — editar cadastro (FAZENDA)
require_once 'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Proteção de rota */
if (empty($_SESSION['usuario'])) { header('Location: login.php'); exit; }
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'FAZENDA') {
    if (($u['tipo_usuario'] ?? '') === 'FRIGORIFICO')      { header('Location: 07-painel-frigorifico.php'); exit; }
    if (($u['tipo_usuario'] ?? '') === 'TRANSPORTADORA') { header('Location: 14-painel-transportadora.php'); exit; }
    header('Location: login.php'); exit;
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$userId = (int)($u['id'] ?? 0);

/* CSRF */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf_token'];

$erros = [];
$okMsg = null;

/* Carrega dados atuais */
$dados = [
    'nome_razao'=>'','cnpj'=>'','cep'=>'','cidade'=>'','estado'=>'','bairro'=>'','rua'=>'','numero'=>'','complemento'=>'',
    'email'=>'','telefone'=>'','responsavel_legal'=>'','cpf_responsavel'=>'','cargo_responsavel'=>'','sistema_criacao'=>'',
];
// NOVO: Array para armazenar as URLs das imagens
$imagens_existentes = [];

try {
    $sql = "SELECT
             u.nome_razao, u.cnpj, u.cep, u.cidade, u.estado, u.bairro, u.rua, u.numero, u.complemento,
             u.email, u.telefone,
             f.responsavel_legal, f.cpf_responsavel, f.cargo_responsavel, f.sistema_criacao
           FROM usuarios u
           LEFT JOIN fazenda f ON f.usuario_id = u.id
           WHERE u.id = ?
           LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([$userId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        foreach ($dados as $k => $v) if (array_key_exists($k, $row)) $dados[$k] = (string)$row[$k];
    }
    
    // NOVO: Busca de Imagens
    $sqlImg = "SELECT id, url FROM fazenda_imagens WHERE usuario_id = ?";
    $stImg = $pdo->prepare($sqlImg);
    $stImg->execute([$userId]);
    $imagens_existentes = $stImg->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $erros[] = 'Erro ao carregar dados: ' . $e->getMessage();
}

/* POST: salvar atualização (CNPJ não altera) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (restante da lógica de POST, omitida aqui por brevidade, mas deve ser mantida) ...

    /* O trecho POST: salvar atualização deve ser mantido aqui */
    if (empty($_POST['_csrf']) || !hash_equals($csrf, (string)$_POST['_csrf'])) {
        $erros[] = 'Token inválido. Recarregue a página.';
    } else {
        $in = [];
        $in['nome_razao']          = trim((string)($_POST['nome_razao'] ?? ''));
        $in['email']               = trim((string)($_POST['email'] ?? ''));
        $in['telefone']            = trim((string)($_POST['telefone'] ?? ''));
        $in['cep']                 = trim((string)($_POST['cep'] ?? ''));
        $in['cidade']              = trim((string)($_POST['cidade'] ?? ''));
        $in['estado']              = strtoupper(trim((string)($_POST['estado'] ?? '')));
        $in['bairro']              = trim((string)($_POST['bairro'] ?? ''));
        $in['rua']                 = trim((string)($_POST['rua'] ?? ''));
        $in['numero']              = trim((string)($_POST['numero'] ?? ''));
        $in['complemento']         = trim((string)($_POST['complemento'] ?? ''));
        $in['responsavel_legal']   = trim((string)($_POST['responsavel_legal'] ?? ''));
        $in['cpf_responsavel']     = preg_replace('/\D+/', '', (string)($_POST['cpf_responsavel'] ?? ''));
        $in['cargo_responsavel']   = trim((string)($_POST['cargo_responsavel'] ?? ''));
        $in['sistema_criacao']     = trim((string)($_POST['sistema_criacao'] ?? ''));

        if ($in['nome_razao'] === '')                                    $erros[] = 'Informe o nome da Fazenda.';
        if ($in['email'] === '' || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
        if ($in['telefone'] === '')                                      $erros[] = 'Informe o telefone.';
        if ($in['cep'] === '')                                           $erros[] = 'Informe o CEP.';
        if ($in['cidade'] === '')                                        $erros[] = 'Informe a cidade.';
        if ($in['estado'] === '' || strlen($in['estado']) !== 2)          $erros[] = 'Estado deve ter 2 letras (UF).';
        if ($in['responsavel_legal'] === '')                             $erros[] = 'Informe o responsável legal.';
        if ($in['cpf_responsavel'] === '' || strlen($in['cpf_responsavel']) !== 11) $erros[] = 'CPF do responsável deve ter 11 dígitos.';
        if ($in['sistema_criacao'] === '')                               $erros[] = 'Selecione o sistema de criação.';

        if (!$erros) {
            try {
                $pdo->beginTransaction();

                $upU = $pdo->prepare("UPDATE usuarios SET
                    nome_razao=?, email=?, telefone=?, cep=?, cidade=?, estado=?,
                    bairro=?, rua=?, numero=?, complemento=?
                  WHERE id=? LIMIT 1");
                $upU->execute([
                    $in['nome_razao'], $in['email'], $in['telefone'], $in['cep'], $in['cidade'], $in['estado'],
                    $in['bairro'] !== '' ? $in['bairro'] : null,
                    $in['rua'] !== '' ? $in['rua'] : null,
                    $in['numero'] !== '' ? $in['numero'] : null,
                    $in['complemento'] !== '' ? $in['complemento'] : null,
                    $userId
                ]);

                $hasF = $pdo->prepare('SELECT 1 FROM fazenda WHERE usuario_id = ? LIMIT 1');
                $hasF->execute([$userId]);
                if ($hasF->fetchColumn()) {
                    $upF = $pdo->prepare("UPDATE fazenda SET
                        sistema_criacao=?, responsavel_legal=?, cpf_responsavel=?, cargo_responsavel=?
                      WHERE usuario_id=? LIMIT 1");
                    $upF->execute([
                        $in['sistema_criacao'],
                        $in['responsavel_legal'],
                        $in['cpf_responsavel'],
                        $in['cargo_responsavel'] !== '' ? $in['cargo_responsavel'] : null,
                        $userId
                    ]);
                } else {
                    $insF = $pdo->prepare("INSERT INTO fazenda
                        (usuario_id, sistema_criacao, responsavel_legal, cpf_responsavel, cargo_responsavel)
                        VALUES (?, ?, ?, ?, ?)");
                    $insF->execute([
                        $userId, $in['sistema_criacao'], $in['responsavel_legal'],
                        $in['cpf_responsavel'], $in['cargo_responsavel'] !== '' ? $in['cargo_responsavel'] : null
                    ]);
                }

                $pdo->commit();

                // Atualiza sessão p/ header/topbar
                $_SESSION['usuario'] = array_merge($_SESSION['usuario'], [
                    'nome_razao' => $in['nome_razao'],
                    'email'      => $in['email'],
                    'telefone'   => $in['telefone'],
                    'cep'        => $in['cep'],
                    'cidade'     => $in['cidade'],
                    'estado'     => $in['estado'],
                    'bairro'     => $in['bairro'],
                    'rua'        => $in['rua'],
                    'numero'     => $in['numero'],
                    'complemento'=> $in['complemento'],
                ]);

                // Redireciona de volta ao perfil (modo leitura)
                header('Location: 01-meu-perfil-fazenda.php?ok=1');
                exit;

             } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($e->getCode() === '23000' && stripos($e->getMessage(), 'uq_usuarios_email') !== false) {
                    $erros[] = 'E-mail já está em uso.';
                } else {
                    $erros[] = 'Erro ao salvar: ' . $e->getMessage();
                }
             } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erros[] = 'Erro ao salvar: ' . $e->getMessage();
             }
        }
    }
}
// POST: salvar atualização FIM

/* Para header */
$emailHeader = e($u['email'] ?? '');
$nomeHeader  = e($u['nome_razao'] ?? 'Fazenda');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>BovinTrade - Editar Perfil (Fazenda)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS Existente e Adições */
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
        .page-header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .page-title{ font-size:1.8rem; font-weight:600; }
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
        
        /* Estilos para o bloco de imagens */
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 20px;
            padding: 10px 0;
            border-top: 1px dashed var(--border);
            border-bottom: 1px dashed var(--border);
        }
        .image-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-item .remove-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(163, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            transition: background 0.2s;
        }
        .image-item .remove-btn:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
<header>
    <div class="logo">🐄 <span>BovinTrade • Fazenda</span></div>
    <div class="user-menu">
        <span><?= $emailHeader ?></span>
        <form action="logout.php" method="post"><button type="submit">Sair</button></form>
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

        
        </ul>
    </aside>

    <main class="main">
        <div class="page-header">
            <div class="page-title">Editar Perfil – Fazenda</div>
            <div>
                <a class="btn" href="01-meu-perfil-fazenda.php"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <?php if ($erros): ?>
            <div class="alert alert-error">
                <?php foreach ($erros as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form class="form-grid" method="post" autocomplete="on" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

                <div class="form-group full-width">
                    <label for="nome_razao">Nome da Fazenda</label>
                    <input type="text" id="nome_razao" name="nome_razao" required value="<?= e($dados['nome_razao']) ?>">
                </div>

                <div class="form-group">
                    <label for="cnpj">CNPJ (imutável)</label>
                    <input type="text" id="cnpj" value="<?= e($dados['cnpj']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="cep">CEP</label>
                    <input type="text" id="cep" name="cep" required value="<?= e($dados['cep']) ?>">
                </div>

                <div class="form-group">
                    <label for="cidade">Cidade</label>
                    <input type="text" id="cidade" name="cidade" required value="<?= e($dados['cidade']) ?>">
                </div>

                <div class="form-group">
                    <label for="estado">Estado (UF)</label>
                    <input type="text" id="estado" name="estado" maxlength="2" required value="<?= e($dados['estado']) ?>">
                </div>

                <div class="form-group">
                    <label for="bairro">Bairro</label>
                    <input type="text" id="bairro" name="bairro" value="<?= e($dados['bairro']) ?>">
                </div>

                <div class="form-group">
                    <label for="rua">Rua</label>
                    <input type="text" id="rua" name="rua" value="<?= e($dados['rua']) ?>">
                </div>

                <div class="form-group">
                    <label for="numero">Número</label>
                    <input type="text" id="numero" name="numero" value="<?= e($dados['numero']) ?>">
                </div>

                <div class="form-group">
                    <label for="complemento">Complemento</label>
                    <input type="text" id="complemento" name="complemento" value="<?= e($dados['complemento']) ?>">
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required value="<?= e($dados['email']) ?>">
                </div>

                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="tel" id="telefone" name="telefone" required value="<?= e($dados['telefone']) ?>">
                </div>

                <div class="form-group full-width">
                    <label>Imagens Atuais da Fazenda (Substituição/Remoção)</label>
                    
                    <?php if (!empty($imagens_existentes)): ?>
                        <div class="image-gallery">
                            <?php foreach ($imagens_existentes as $img): ?>
                                <div class="image-item" id="img-<?= e($img['id']) ?>">
                                    <img src="<?= e($img['url']) ?>" alt="Imagem da Fazenda">
                                    <button type="button" class="remove-btn" 
                                            onclick="if(confirm('Tem certeza que deseja remover esta imagem?')) removerImagem(<?= (int)$img['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--text-light); font-size:0.9rem;">Nenhuma imagem cadastrada. Use o campo abaixo para adicionar.</p>
                    <?php endif; ?>
                    
                    <label for="imagens_upload" style="margin-top:10px;">Adicionar/Substituir Imagens (Máx. 5)</label>
                    <input type="file" id="imagens_upload" name="imagens[]" accept="image/jpeg, image/png" multiple>
                    <small style="color:var(--text-light); font-size:0.85rem;">Selecione novos arquivos para substituir as imagens existentes ou adicionar mais (até 5 no total).</small>
                </div>
                <div class="form-group">
                    <label for="responsavel_legal">Responsável Legal</label>
                    <input type="text" id="responsavel_legal" name="responsavel_legal" required value="<?= e($dados['responsavel_legal']) ?>">
                </div>

                <div class="form-group">
                    <label for="cpf_responsavel">CPF do Responsável</label>
                    <input type="text" id="cpf_responsavel" name="cpf_responsavel" required value="<?= e($dados['cpf_responsavel']) ?>">
                </div>

                <div class="form-group">
                    <label for="cargo_responsavel">Cargo</label>
                    <input type="text" id="cargo_responsavel" name="cargo_responsavel" value="<?= e($dados['cargo_responsavel']) ?>">
                </div>

                <div class="form-group full-width">
                    <label>Sistema de Criação</label>
                    <div style="display:flex; gap:2rem;">
                        <?php $sc = $dados['sistema_criacao']; ?>
                        <label style="display:flex; align-items:center; gap:.5rem;">
                            <input type="radio" name="sistema_criacao" value="Pasto" <?= $sc==='Pasto'?'checked':'' ?>> Pasto
                        </label>
                        <label style="display:flex; align-items:center; gap:.5rem;">
                            <input type="radio" name="sistema_criacao" value="Confinamento" <?= $sc==='Confinamento'?'checked':'' ?>> Confinamento
                        </label>
                        <label style="display:flex; align-items:center; gap:.5rem;">
                            <input type="radio" name="sistema_criacao" value="Semi-confinamento" <?= $sc==='Semi-confinamento'?'checked':'' ?>> Semi-confinamento
                        </label>
                        <label style="display:flex; align-items:center; gap:.5rem;">
                            <input type="radio" name="sistema_criacao" value="Outro" <?= !in_array($sc, ['Pasto', 'Confinamento', 'Semi-confinamento'])?'checked':'' ?>> Outro
                        </label>
                    </div>
                </div>

                <div class="actions full-width" style="display:flex; gap:1rem; justify-content:flex-end; padding-top:1.5rem; border-top:1px solid var(--border);">
                    <a class="btn" href="01-meu-perfil-fazenda.php"><i class="fas fa-times"></i> Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar alterações</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    // Função básica de remoção de imagem (requer implementação AJAX no lado do servidor)
    function removerImagem(imageId) {
        // ATENÇÃO: Você deve implementar um script AJAX que faça o DELETE no banco e exclua o arquivo do disco.
        console.log("Tentando remover imagem ID: " + imageId);
        alert("A remoção da imagem requer um endpoint AJAX no lado do servidor. Implemente 'remover_imagem_fazenda.php' para excluir a imagem ID " + imageId + " do banco e do disco.");
        
        // Exemplo visual (apenas para simular o front-end):
        const item = document.getElementById('img-' + imageId);
        if (item) item.remove();
    }
</script>
</body>
</html>