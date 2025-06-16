<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projecto - Gerenciamento de Documentos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c, #2a4d69, #4b86b4);
            color: #333;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(to right, #2c3e50, #3498db);
            color: white;
            padding: 30px 40px;
            text-align: center;
            position: relative;
        }
        
        h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .status {
            background: #e67e22;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-weight: bold;
            font-size: 1.1rem;
            margin: 15px 0;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }
        
        .content {
            padding: 30px 40px;
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin: 25px 0 15px;
            font-size: 1.8rem;
        }
        
        .section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .structure {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            line-height: 1.8;
            margin: 15px 0;
        }
        
        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .tech-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .tech-card:hover {
            transform: translateY(-5px);
        }
        
        .tech-card i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .frontend { color: #3498db; }
        .backend { color: #e74c3c; }
        .database { color: #27ae60; }
        
        .team-section {
            text-align: center;
            background: linear-gradient(to right, #2c3e50, #4a6491);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .team-name {
            font-size: 2.2rem;
            font-weight: bold;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .folder-icon {
            color: #f1c40f;
            margin-right: 8px;
        }
        
        footer {
            text-align: center;
            padding: 20px;
            background: #2c3e50;
            color: #ecf0f1;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .tech-grid {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Projecto - Gerenciamento de Documentos</h1>
            <div class="status">
                <i class="fas fa-tools"></i> Status: 🚧 Desenvolvimento ativo com melhorias contínuas
            </div>
            <p>Sistema para criação e gerenciamento de documentos e apresentações</p>
        </header>
        
        <div class="content">
            <div class="section">
                <h2><i class="fas fa-book-open"></i> Visão Geral</h2>
                <p>Projeto em desenvolvimento para criação e gerenciamento de documentos e apresentações com interface moderna e funcionalidades avançadas.</p>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-folder-tree"></i> Estrutura do Projeto</h2>
                <div class="structure">
                    <div><i class="fas fa-folder folder-icon"></i> Projecto/</div>
                    <div style="margin-left: 20px;">
                        <div><i class="fas fa-folder folder-icon"></i> backend/ <span style="color: #888;"># Java e JS (funcionalidades parciais)</span></div>
                        <div style="margin-left: 40px;"><i class="fas fa-folder folder-icon"></i> src/</div>
                        
                        <div><i class="fas fa-folder folder-icon"></i> database/ <span style="color: #888;"># Banco de dados SQL</span></div>
                        
                        <div><i class="fas fa-folder folder-icon"></i> frontend/ <span style="color: #888;"># HTML/CSS (interface principal)</span></div>
                        
                        <div><i class="fas fa-folder folder-icon"></i> docs/ <span style="color: #888;"># Documentação e PPTs</span></div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-microchip"></i> Tecnologias</h2>
                <div class="tech-grid">
                    <div class="tech-card">
                        <i class="fab fa-html5 frontend"></i>
                        <h3>Frontend</h3>
                        <p>HTML, CSS, JavaScript</p>
                    </div>
                    
                    <div class="tech-card">
                        <i class="fas fa-server backend"></i>
                        <h3>Backend</h3>
                        <p>Java, JavaScript</p>
                    </div>
                    
                    <div class="tech-card">
                        <i class="fas fa-database database"></i>
                        <h3>Banco de Dados</h3>
                        <p>SQL</p>
                    </div>
                </div>
            </div>
            
            <div class="team-section">
                <h2><i class="fas fa-users"></i> Equipe</h2>
                <div class="team-name">BOVINTRADE</div>
                <p>Trabalhando juntos para criar soluções inovadoras</p>
            </div>
        </div>
        
        <footer>
            <p>© 2023 Projecto - Gerenciamento de Documentos | Todos os direitos reservados</p>
        </footer>
    </div>
</body>
</html>
