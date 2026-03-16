<?php // Bovintrade-PHP/Projeto-Bovintrade-2/index.php ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Página Inicial</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary: #a30000;
      --primary-dark: #7a0000;
      --background: #ffffff;
      --text: #333333;
      --border: #e0e0e0;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      font-family: 'Montserrat', sans-serif;
      background-color: var(--background);
      color: var(--text);
      height: 100%;
      max-width: 100vw;
      overflow-x: hidden;
      line-height: 1.6;
    }
    header {
      background: linear-gradient(135deg, var(--primary-dark), var(--primary));
      color: #f9f9f9;
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      position: relative;
      z-index: 100;
    }
    .logo { font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; }
    .user-menu { display: flex; align-items: center; gap: 1.5rem; }
    .user-menu a { color: white; text-decoration: none; font-weight: 500; transition: opacity 0.2s; }
    .user-menu a:hover { opacity: 0.9; }
    .main { padding: 2.5rem; background-color: #f9f9f9; }
    .intro { text-align: center; max-width: 1000px; margin: 0 auto; }
    .intro h2 { font-size: 2rem; margin-bottom: 1rem; }
    .intro p { font-size: 1rem; color: var(--text); margin: 0 auto; max-width: 600px; }

    .cards {
      margin-top: 3rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
    }
    .card-link { text-decoration: none; color: inherit; display: block; }
   .card {
  background: white; 
  border: 1px solid var(--border);
  border-radius: 10px; 
  padding: 1.5rem;
  box-shadow: 0 4px 8px rgba(0,0,0,0.05);
  transition: transform 0.2s, box-shadow 0.2s; 
  text-align: center; 
  cursor: pointer;
  
  /* Altura fixa para todos os cards */
  height: 250px;
  
  /* Flex para centralizar conteúdo */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 0.75rem;
}

.card:hover { 
  transform: translateY(-3px); 
  box-shadow: 0 6px 12px rgba(0,0,0,0.1); 
}

.card i { 
  font-size: 2.5rem; 
  color: var(--primary); 
}

.card h3 { 
  color: var(--primary); 
  margin: 0;
  font-size: 1.1rem;
}

/* Limita o parágrafo a 3 linhas com reticências */
.card p {
  margin: 0;
  font-size: 0.92rem;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  max-width: 100%;
}
    .credits {
      margin-top: 2.5rem; font-size: 0.9rem; color: var(--primary-dark);
      text-align: center; max-width: 700px; margin-left: auto; margin-right: auto;
      padding: 1rem 1.5rem; font-style: italic;
    }
  </style>
</head>
<body>
<header>
  <div class="logo">🐄 <span>BovinTrade</span></div>

  <div class="user-menu">
    <!-- se você criar login.php depois, atualize o link -->
    <a href="login.php">Login</a>
    <!-- IMPORTANTE: link em relação ao próprio diretório -->
  </div>
</header>

<div class="main">
  <section class="intro">
    <h2>Bem-vindo ao BovinTrade</h2>
    <p>
      O BovinTrade é uma plataforma de e-commerce para comercialização de lotes de bois diretamente de fazendas, conectando-as com frigoríficos e transportadoras.
      Nosso objetivo é automatizar e agilizar todo o processo de compra e venda de bois para abate, garantindo transparência, segurança e eficiência na cadeia produtiva.
    </p>

    <div class="cards">
      <a href="02-painel-fazenda.php" class="card-link">
        <div class="card">
          <i class="fas fa-tractor"></i>
          <h3>Sou Fazenda</h3>
          <p>Cadastre seus lotes de bois e tenha acesso direto a frigoríficos e transportadoras de todo o Brasil, garantindo melhores oportunidades de venda.</p>
        </div>
      </a>

      <a href="07-painel-frigorifico.php" class="card-link">
        <div class="card">
          <i class="fas fa-drumstick-bite"></i>
          <h3>Sou Frigorífico</h3>
          <p>Encontre fornecedores confiáveis de gado e acompanhe o processo de compra com segurança, qualidade e rastreabilidade.</p>
        </div>
      </a>

      <a href="14-painel-transportadora.php" class="card-link">
        <div class="card">
          <i class="fas fa-truck"></i>
          <h3>Sou Transportadora</h3>
          <p>Gerencie o transporte dos lotes com eficiência, integrando-se às negociações entre fazendas e frigoríficos.</p>
        </div>
      </a>
    </div>

    <p class="credits">
      <br> Projeto desenvolvido pelos estudantes: Elisandra Carol da Silva;<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Fábio Ribeiro Barbosa;<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Maria Clara Soares Bertolo;<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Mariana Pereira Gonçalves;<br>
      &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Thaissa Rodrigues Martins;<br>
      <br><br>
      Curso: Análise e Desenvolvimento de Sistemas <br>
      Fatec Ourinhos - 2025.
    </p>
  </section>
</div>
</body>
</html>