<?php // Bovintrade-PHP/Projeto-Bovintrade-2/cadastro-geral.php ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Geral</title>
    <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
          margin: 0;
          padding: 0;
          font-family: 'Poppins', sans-serif;
          background: linear-gradient(to bottom, #f2f1ee, #e8e6e1);
          min-height: 100vh;
          display: flex;
          justify-content: center;
          align-items: center;
          color: #000000;
        }
        .container {
          background-color: rgba(255, 255, 255, 0.9);
          border-radius: 20px;
          box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
          padding: 50px;
          width: 90%;
          max-width: 600px;
          text-align: center;
        }
        h1 {
          font-family: 'Lora', serif;
          color: #8B0000;
          font-size: 2.5rem;
          margin-bottom: 10px;
        }
        p {
          font-family: 'Lora', serif;
          font-size: 1.2rem;
          margin-bottom: 40px;
        }
        .btn-container {
          display: grid;
          grid-template-columns: 1fr 1fr 1fr;
          gap: 20px;
          margin-bottom: 30px;
        }
        .btn {
          font-family: 'Poppins', sans-serif;
          font-weight: 600;
          font-size: 1rem;
          padding: 20px 15px;
          border: 2px solid #8B0000;
          border-radius: 12px;
          background-color: transparent;
          color: #000000;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 10px;
        }
        .btn img { width: 60px; height: 60px; }
        .btn:hover {
          background-color: #8B0000;
          color: white;
          transform: translateY(-3px);
        }
        .btn-voltar {
          display: inline-block;
          margin-top: 20px;
          font-size: 1rem;
          text-decoration: none;
          color: #8B0000;
          font-weight: bold;
          transition: 0.3s;
        }
        .btn-voltar:hover { color: #000000; }
    </style>
</head>
<body>
<div class="container">
    <h1>Cadastre-se!</h1>
    <p>Escolha seu perfil para continuar:</p>

    <div class="btn-container">
        <a href="cadastro-fazenda.php?tipo=FAZENDA" class="btn">
            <img src="https://img.icons8.com/ios-filled/100/8B0000/farm.png" alt="Fazenda">
            Fazenda
        </a>
        <a href="cadastro-frigorifico.php?tipo=FRIGORIFICO" class="btn">
            <img src="https://img.icons8.com/ios-filled/100/8B0000/steak.png" alt="Frigorífico">
            Frigorífico
        </a>
        <a href="cadastro-transportadora.php?tipo=TRANSPORTADORA" class="btn">
            <img src="https://img.icons8.com/ios-filled/100/8B0000/delivery.png" alt="Transportadora">
            Transportadora
        </a>
    </div>

    <a href="index.php" class="btn-voltar">⟵ Voltar</a>
</div>
</body>
</html>
