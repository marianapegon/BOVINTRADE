-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16/03/2026 às 15:24
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `bovintrade_2`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao`
--

CREATE TABLE `avaliacao` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `pedido_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `alvo_tipo` enum('LOTE','TRANSPORTE','FAZENDA','FRIGORIFICO','TRANSPORTADORA') NOT NULL,
  `alvo_id` bigint(20) UNSIGNED NOT NULL,
  `avaliador_tipo` enum('FRIGORIFICO','FAZENDA','TRANSPORTADORA') NOT NULL,
  `avaliador_id` bigint(20) UNSIGNED NOT NULL,
  `nota_geral` tinyint(4) NOT NULL,
  `comentario` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `avaliacao`
--

INSERT INTO `avaliacao` (`id`, `pedido_id`, `pedido_item_id`, `alvo_tipo`, `alvo_id`, `avaliador_tipo`, `avaliador_id`, `nota_geral`, `comentario`, `created_at`, `updated_at`) VALUES
(1, 39, 40, 'TRANSPORTADORA', 7, 'FRIGORIFICO', 1, 5, 'sjkdbasjodb', '2025-10-27 23:35:06', '2025-10-27 23:35:06'),
(2, 39, 40, 'FAZENDA', 4, 'FRIGORIFICO', 1, 5, 'askdjaosçd', '2025-10-27 23:35:09', '2025-10-27 23:35:09');

--
-- Acionadores `avaliacao`
--
DELIMITER $$
CREATE TRIGGER `tg_avaliacao_after_del` AFTER DELETE ON `avaliacao` FOR EACH ROW BEGIN
  UPDATE reputacao_resumo rr
  SET media_geral = IFNULL((
      SELECT ROUND(AVG(a.nota_geral), 2)
      FROM avaliacao a
      WHERE a.alvo_tipo = rr.alvo_tipo AND a.alvo_id = rr.alvo_id
    ), 0.00),
      qtd_avaliacoes = (
      SELECT COUNT(*)
      FROM avaliacao a
      WHERE a.alvo_tipo = rr.alvo_tipo AND a.alvo_id = rr.alvo_id
    )
  WHERE rr.alvo_tipo = OLD.alvo_tipo AND rr.alvo_id = OLD.alvo_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tg_avaliacao_after_ins` AFTER INSERT ON `avaliacao` FOR EACH ROW BEGIN
  INSERT INTO reputacao_resumo (alvo_tipo, alvo_id, media_geral, qtd_avaliacoes)
  VALUES (NEW.alvo_tipo, NEW.alvo_id, NEW.nota_geral, 1)
  ON DUPLICATE KEY UPDATE
    media_geral = ROUND(((media_geral * qtd_avaliacoes) + NEW.nota_geral) / (qtd_avaliacoes + 1), 2),
    qtd_avaliacoes = qtd_avaliacoes + 1;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tg_avaliacao_after_upd` AFTER UPDATE ON `avaliacao` FOR EACH ROW BEGIN
  -- Recalcular por segurança (opção simples: recalcula por subconsulta)
  UPDATE reputacao_resumo rr
  SET media_geral = (
      SELECT ROUND(AVG(a.nota_geral), 2)
      FROM avaliacao a
      WHERE a.alvo_tipo = rr.alvo_tipo AND a.alvo_id = rr.alvo_id
    ),
      qtd_avaliacoes = (
      SELECT COUNT(*)
      FROM avaliacao a
      WHERE a.alvo_tipo = rr.alvo_tipo AND a.alvo_id = rr.alvo_id
    )
  WHERE rr.alvo_tipo = NEW.alvo_tipo AND rr.alvo_id = NEW.alvo_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacao_metrica`
--

CREATE TABLE `avaliacao_metrica` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `avaliacao_id` bigint(20) UNSIGNED NOT NULL,
  `metrica_codigo` varchar(50) NOT NULL,
  `nota` tinyint(4) NOT NULL,
  `peso` decimal(4,2) NOT NULL DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes_lote`
--

CREATE TABLE `avaliacoes_lote` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_item_id` bigint(20) UNSIGNED NOT NULL,
  `frigorifico_id` bigint(20) UNSIGNED NOT NULL,
  `fazenda_id` bigint(20) UNSIGNED NOT NULL,
  `nota` int(11) DEFAULT NULL CHECK (`nota` >= 1 and `nota` <= 5),
  `comentario` text DEFAULT NULL,
  `data_avaliacao` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cumprimento_acordo` tinyint(4) DEFAULT NULL,
  `preparo_embarque` tinyint(4) DEFAULT NULL,
  `comunicacao` tinyint(4) DEFAULT NULL,
  `estrutura_corporal` tinyint(4) DEFAULT NULL,
  `qualidade_carcaca` tinyint(4) DEFAULT NULL,
  `saude_bem_estar` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `avaliacoes_lote`
--

INSERT INTO `avaliacoes_lote` (`id`, `pedido_item_id`, `frigorifico_id`, `fazenda_id`, `nota`, `comentario`, `data_avaliacao`, `created_at`, `cumprimento_acordo`, `preparo_embarque`, `comunicacao`, `estrutura_corporal`, `qualidade_carcaca`, `saude_bem_estar`) VALUES
(1, 40, 1, 4, 5, 'ótimo', '2025-10-25 03:26:22', '2025-10-27 22:44:14', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 1, 4, 5, 'Lote excelente, gado muito bem-acabado e uniforme. O peso médio veio exatamente conforme o descrito no anúncio. Ótimo padrão de qualidade, com certeza negociaremos novamente.', '2025-10-25 03:33:21', '2025-10-27 22:44:14', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 39, 1, 4, 4, 'bla bla bla bla bla blabla', '2025-10-27 20:32:44', '2025-10-27 23:32:44', NULL, NULL, NULL, 4, 4, 5),
(4, 271, 1, 4, 4, 'DSDFS', '2025-10-29 15:44:33', '2025-10-29 18:44:33', NULL, NULL, NULL, 5, 4, 5),
(5, 268, 1, 4, 5, '', '2025-10-29 22:41:59', '2025-10-30 01:41:59', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 281, 19, 18, 5, 'Este é um lote de alto padrão, ideal para investidores ou confinamentos que buscam rapidez e eficiência na terminação. O lote está em plenas condições para entrega imediata e representa um excelente custo-benefício pelo preço solicitado.', '2025-11-12 02:32:07', '2025-11-12 05:32:07', NULL, NULL, NULL, 5, 5, 5);

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes_transporte`
--

CREATE TABLE `avaliacoes_transporte` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transporte_id` int(11) NOT NULL,
  `avaliador_tipo` enum('frigorifico','fazenda') DEFAULT NULL,
  `avaliador_id` bigint(20) UNSIGNED NOT NULL,
  `nota` int(11) DEFAULT NULL CHECK (`nota` >= 1 and `nota` <= 5),
  `comentario` text DEFAULT NULL,
  `data_avaliacao` datetime DEFAULT current_timestamp(),
  `pontualidade` tinyint(4) DEFAULT NULL,
  `bem_estar_viagem` tinyint(4) DEFAULT NULL,
  `condicao_veiculo` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `avaliacoes_transporte`
--

INSERT INTO `avaliacoes_transporte` (`id`, `transporte_id`, `avaliador_tipo`, `avaliador_id`, `nota`, `comentario`, `data_avaliacao`, `pontualidade`, `bem_estar_viagem`, `condicao_veiculo`) VALUES
(1, 29, 'frigorifico', 1, 5, 'legal', '2025-10-25 03:26:22', NULL, NULL, NULL),
(2, 27, 'frigorifico', 1, 5, 'Serviço de transporte impecável. O motorista foi muito profissional, chegou no horário agendado e o desembarque foi realizado com calma e cuidado. Recomendo.', '2025-10-25 03:33:21', NULL, NULL, NULL),
(3, 33, 'frigorifico', 1, 4, 'bla bla bla bla bla blabla', '2025-10-27 20:32:44', 4, 5, 4),
(4, 40, 'frigorifico', 1, 5, 'GFDGS', '2025-10-29 15:44:33', 5, 5, 5),
(5, 41, 'frigorifico', 1, 5, '', '2025-10-29 22:41:59', NULL, NULL, NULL),
(6, 52, 'frigorifico', 19, 5, 'O ativo inspecionado (seja um lote de gado ou um veículo) demonstra um padrão de qualidade e excelência elevadíssimo. O nível de cuidado na manutenção e a conformidade legal são notáveis. Representa uma oportunidade de investimento de baixo risco para qualquer operação que exija confiabilidade e performance de alto nível.', '2025-11-12 02:32:07', 5, 5, 5);

-- --------------------------------------------------------

--
-- Estrutura para tabela `carrinho_persistente`
--

CREATE TABLE `carrinho_persistente` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `frigorifico_id` bigint(20) UNSIGNED NOT NULL,
  `lote_id` bigint(20) UNSIGNED NOT NULL,
  `quantidade` int(11) DEFAULT 1,
  `preco_unitario` decimal(12,2) NOT NULL,
  `adicionado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `fazenda`
--

CREATE TABLE `fazenda` (
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `sistema_criacao` varchar(100) NOT NULL,
  `responsavel_legal` varchar(255) NOT NULL,
  `cpf_responsavel` char(11) NOT NULL,
  `cargo_responsavel` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `fazenda`
--

INSERT INTO `fazenda` (`usuario_id`, `sistema_criacao`, `responsavel_legal`, `cpf_responsavel`, `cargo_responsavel`) VALUES
(4, 'Confinamento', 'julio', '14725836945', 'dono'),
(6, 'EXTENSIVO', 'bob', '85278941637', 'gerente'),
(13, 'EXTENSIVO', 'theodor', '78945423159', 'luna luna luna'),
(14, 'EXTENSIVO', 'tony stark', '36925258958', 'vingador'),
(15, 'EXTENSIVO', 'Eleine', '78952305854', 'dona'),
(16, 'SEMI-INTENSIVO', 'Sr Sirigueijo', '78517851852', 'Dono'),
(18, 'SEMI-INTENSIVO', 'Elisandra Carol', '65478517789', 'Dona'),
(21, 'SEMI-INTENSIVO', 'Joao Mauricio', '32115646489', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `fazenda_imagens`
--

CREATE TABLE `fazenda_imagens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `url` varchar(1024) NOT NULL,
  `legenda` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `fazenda_imagens`
--

INSERT INTO `fazenda_imagens` (`id`, `usuario_id`, `url`, `legenda`, `created_at`) VALUES
(1, 14, 'uploads/fazendas/fazenda_14_68e497cc8b5a5.jpg', NULL, '2025-10-07 04:32:12'),
(2, 15, 'uploads/fazendas/fazenda_15_68e6b24c9af56.png', NULL, '2025-10-08 18:49:48'),
(3, 16, 'uploads/fazendas/fazenda_16_68febfd700dbb.jpg', NULL, '2025-10-27 00:41:59');

-- --------------------------------------------------------

--
-- Estrutura para tabela `frigorifico`
--

CREATE TABLE `frigorifico` (
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `responsavel_legal` varchar(255) NOT NULL,
  `cpf_responsavel` char(11) NOT NULL,
  `cargo_responsavel` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `frigorifico`
--

INSERT INTO `frigorifico` (`usuario_id`, `responsavel_legal`, `cpf_responsavel`, `cargo_responsavel`) VALUES
(1, 'José Soares', '98741236547', 'dono'),
(2, 'aline', '58769325478', 'gerente'),
(19, 'Maria Clara Soares', '45985219894', 'Adm');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lote_bois`
--

CREATE TABLE `lote_bois` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `fazenda_id` bigint(20) UNSIGNED NOT NULL,
  `codigo_lote` varchar(32) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `quantidade` int(10) UNSIGNED NOT NULL,
  `peso_medio_kg` decimal(7,2) NOT NULL,
  `raca` varchar(100) NOT NULL,
  `preco` decimal(12,2) NOT NULL,
  `historico_vacinacao` text NOT NULL,
  `tipo_alimentacao` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `status` enum('DISPONIVEL','EM_NEGOCIACAO','VENDIDO','INATIVO') NOT NULL DEFAULT 'DISPONIVEL',
  `localizacao` varchar(100) NOT NULL DEFAULT 'ND',
  `preco_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `imagem` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lote_bois`
--

INSERT INTO `lote_bois` (`id`, `fazenda_id`, `codigo_lote`, `created_at`, `updated_at`, `quantidade`, `peso_medio_kg`, `raca`, `preco`, `historico_vacinacao`, `tipo_alimentacao`, `descricao`, `status`, `localizacao`, `preco_total`, `imagem`) VALUES
(1, 4, 'LOTE20250904-000001', '2025-09-05 01:35:57', '2025-10-08 06:33:02', 200, 530.00, 'Nelore', 1500.00, '1dfnnsdfn\r\nsncions\r\ndisnixn', 'Pastagem', 'lote teste', 'VENDIDO', 'ND', 0.00, NULL),
(2, 4, 'LOTE20250904-000002', '2025-09-05 01:36:48', '2025-09-17 02:42:02', 100, 600.00, 'Angus', 3000.00, '45fdfsihf\r\nskndklsdn\r\n\\kdnkmsssdc', 'Confinamento', 'lote teste 2', 'VENDIDO', 'ND', 0.00, NULL),
(3, 4, 'LOTE20250904-000003', '2025-09-05 02:15:30', '2025-10-08 18:58:58', 450, 750.00, 'Brahman', 5600.00, 'aiasbuiasbsac', 'Confinamento', 'bsjabdajsbda', 'VENDIDO', 'ND', 0.00, NULL),
(4, 4, 'LOTE20250904-000004', '2025-09-05 02:16:02', '2025-10-28 03:43:25', 300, 750.00, 'Hereford', 6000.00, 'fjbsdjbcjsdc', 'Semi-confinamento', 'ajsnoiasnoid', 'VENDIDO', 'ND', 0.00, NULL),
(5, 4, 'LOTE20250904-000005', '2025-09-05 02:16:35', '2025-09-27 01:32:14', 50, 900.00, 'Angus', 5000.00, 'adasasdasd', 'Confinamento', 'adasr arf', 'VENDIDO', 'ND', 0.00, NULL),
(6, 4, 'LOTE20250908-000006', '2025-09-09 01:14:44', '2025-10-07 01:23:09', 98, 450.00, 'Nelore', 6054.00, 'ghgfhgdg', 'Confinamento', 'drgertet', 'VENDIDO', 'Rio Grande do Sul', 0.00, NULL),
(7, 6, 'LOTE20250908-000007', '2025-09-09 01:23:15', '2025-10-08 06:31:47', 65, 400.00, 'Hereford', 6500.00, 'masdjhasudh', 'Confinamento', 'sdjnasidhasipd', 'VENDIDO', 'Rio de Janeiro', 0.00, NULL),
(8, 6, 'LOTE20250908-000008', '2025-09-09 02:21:51', '2025-10-08 17:13:52', 100, 800.00, 'Angus', 8000.00, '07/04/2024: Ibr + Bvd + Pi3 + BRSV (vacinação contra vírus respiratórios)\r\n06/12/2023: Pasteurella (proteção contra pneumonia bacteriana)\r\n30/12/2023: Pasteurella (reforço anual)\r\n24/08/2023: Leptospirose (contra infecções renais e hepáticas)\r\n22/02/2025: Clostridioses (7-way) (contra tétano e outras infecções por Clostridium)', 'Confinamento', 'Lote Angus Premium - Código: ANGUS-2025-001\r\n\r\nEste lote é composto por 100 cabeças de gado da raça Angus, reconhecida por seu marmoreio superior e alto rendimento de carcaça. Os animais foram selecionados de criadores certificados no Rio Grande do Sul, com foco em genética de alta performance para produção de cortes premium.\r\n\r\nCaracterísticas Principais:\r\n\r\nQuantidade: 100 cabeças (machos e fêmeas, predominantemente novilhos e novilhas em fase de terminação).\r\n\r\nRaça: Angus Puro (100% sangue Angus, com pedigree registrado na Associação Brasileira de Angus).\r\n\r\nPeso Médio: 800 kg por animal (faixa de 750-850 kg), indicando maturidade ideal para abate. Ganho de peso diário médio: 1,2 kg/dia durante o confinamento.\r\n\r\nIdade Média: 24-30 meses, garantindo carne tenra e suculenta.\r\n\r\nSistema de Alimentação: Confinamento intensivo com ração balanceada (milho, soja e silagem de alta qualidade), suplementada com minerais para otimizar o ganho de peso e a qualidade da carne.\r\n\r\nSaúde e Bem-Estar:\r\n\r\nVacinação completa (IBR, BVD, leptospirose e clostridioses).\r\n\r\nDesvermifugação recente.\r\n\r\nSem histórico de doenças.\r\n\r\nCertificação sanitária emitida pelo MAPA.\r\n\r\nCondição corporal excelente (escore 6-7), pelagem preta característica da raça, livre de chifres (polled).\r\n\r\nDesempenho Produtivo: Rendimento de carcaça estimado em 60-65%, com alto grau de marmoreio (grau 2-3 na escala USDA), ideal para exportação ou mercado interno de alta gastronomia. Preço médio estimado: R$ 25,00/kg vivo (total aproximado do lote: R$ 2.000.000,00).\r\n\r\nLocalização e Histórico: Originário de fazendas no interior do RS, formado em abril de 2025 e pronto para comercialização ou transferência para abate. Transporte disponível para todo o Brasil.\r\n\r\nEste lote representa uma excelente oportunidade para engordadores ou frigoríficos que buscam qualidade premium em Angus. Para mais detalhes ou inspeção, contate o responsável pela fazenda.', 'VENDIDO', 'Paraná', 0.00, NULL),
(9, 4, 'LOTE20250927-000009', '2025-09-27 04:21:23', '2025-10-07 01:27:43', 90, 800.00, 'Nelore', 3000.00, 'ewreztxjckvhbkjnkl', 'Confinamento', 'zerxtfcgvhjkjk', 'VENDIDO', 'Minas Gerais', 270000.00, NULL),
(10, 4, 'LOTE20251007-000010', '2025-10-07 04:13:01', '2025-10-08 03:44:27', 60, 820.00, 'Brahman', 950.00, '10-09-2025 : vermifugo', 'Pastagem', '60 cabeças de gado, regime de pastagem, pesando aproximadamente 820kg, todos vacinados, carne da melhor qualidade.', 'VENDIDO', 'Espírito Santo', 57000.00, NULL),
(11, 6, 'LOTE20251007-000011', '2025-10-07 04:14:01', '2025-10-08 06:32:28', 40, 580.00, 'Nelore', 1050.00, '8-9-2025:dkajsdpamdoaps', 'Pastagem', 'akndsioasdioasjdlknscjkjcksdoasjdlkasn asdasndhioasd+', 'VENDIDO', 'Amapá', 42000.00, NULL),
(12, 13, 'LOTE20251007-000012', '2025-10-07 04:22:24', '2025-10-07 04:24:53', 70, 790.00, 'Angus', 4200.00, '14-11-2025: Vermifugo\r\n6-8-2024: Raiva', 'Confinamento', 'Raça importada da arabia saudita;\r\ncarne com selo internacional de qualidade;\r\nComprando ganha ingresso pro lolapalozza;', 'VENDIDO', 'São Paulo', 294000.00, NULL),
(13, 14, 'LOTE20251007-000013', '2025-10-07 04:36:48', '2025-11-05 03:49:50', 20, 900.00, 'Brahman', 3000.00, 'dscdzcsdcsc', 'Confinamento', 'ascascadasd', 'VENDIDO', 'Alagoas', 60000.00, NULL),
(14, 4, 'LOTE20251007-000014', '2025-10-07 22:12:28', '2025-10-07 22:27:38', 60, 650.00, 'Angus', 1020.00, 'ioplkjcvbnknlç78956130', 'Confinamento', '=-0978565wewer6ty8uo', 'VENDIDO', 'Tocantins', 61200.00, 'uploads/lotes/lote_68e5904ce531a0.15087625.jpg'),
(15, 4, 'LOTE20251007-000015', '2025-10-07 22:13:56', '2025-10-27 01:10:26', 60, 650.00, 'Angus', 1020.00, 'ioplkjcvbnknlç78956130', 'Confinamento', '=-0978565wewer6ty8uo', 'VENDIDO', 'Tocantins', 61200.00, NULL),
(17, 4, 'LOTE20251028-000017', '2025-10-29 02:59:23', '2025-10-30 01:39:26', 45, 945.00, 'Angus', 500.00, 'teste', 'Confinamento', 'teste', 'VENDIDO', 'Maranhão', 22500.00, NULL),
(18, 4, 'LOTE20251029-000018', '2025-10-29 03:00:07', '2025-11-05 00:17:16', 90, 500.00, 'Nelore', 1625.00, 'testetesteteste', 'Pastagem', 'testetesteteste', 'VENDIDO', 'Pará', 146250.00, NULL),
(19, 4, 'LOTE20251029-000019', '2025-10-29 03:00:55', '2025-11-11 02:20:27', 60, 690.00, 'Angus', 2300.00, 'testetesteteste', 'Semi-confinamento', 'testetesteteste', 'VENDIDO', 'Rio de Janeiro', 138000.00, NULL),
(20, 6, 'LOTE20251029-000020', '2025-10-29 03:03:04', '2025-10-29 03:03:04', 75, 840.00, 'Brahman', 750.00, 'testetesteteste', 'Confinamento', 'testetesteteste', 'DISPONIVEL', 'São Paulo', 56250.00, NULL),
(21, 6, 'LOTE20251029-000021', '2025-10-29 03:03:53', '2025-11-05 02:54:53', 80, 860.00, 'Hereford', 3050.00, 'testetesteteste', 'Confinamento', 'testetesteteste', 'VENDIDO', 'Alagoas', 244000.00, NULL),
(22, 18, 'LOTE20251112-000022', '2025-11-12 04:19:22', '2025-11-12 04:19:22', 120, 450.00, 'Nelore', 3800.00, 'Lote composto por 120 cabeças de gado Nelore, machos, puros de origem, criados com foco em terminação para abate. Animais com idade média de 30 meses (2 anos e meio). Apresentam bom desenvolvimento corporal, escore de condição corporal (ECC) 4 e uniformidade de peso. Gado manso e com manejo sanitário rigoroso, sem histórico de doenças crônicas ou lesões. Pronto para confinamento final ou abate.', 'Semi-confinamento', 'Histórico de Vacinação: Completo e em dia.\r\nFebre Aftosa: Última dose em Maio/2025.\r\nBrucelose: Vacinadas apenas fêmeas (não aplicável a este lote de machos).\r\nRaiva: Última dose em Outubro/2024.\r\nClostridiose: Última dose em Abril/2025.', 'DISPONIVEL', 'São Paulo', 456000.00, NULL),
(23, 18, 'LOTE20251112-000023', '2025-11-12 04:22:09', '2025-11-12 04:22:09', 85, 520.00, 'Angus', 4500.00, 'Histórico de Vacinação: Em dia, conforme calendário oficial da região.\r\nFebre Aftosa: Última dose em Novembro/2024.\r\nBrucelose: Todas as fêmeas vacinadas (B19), com comprovante.\r\nIBR/BVD: Protocolo reprodutivo iniciado, última dose em Setembro/2025.\r\nParasitários: Desverminação realizada há 60 dias.', 'Pastagem', 'Lote de 85 novilhas e vacas Guzerá PO (Puro de Origem), excelente linhagem materna. Animais rústicos e adaptados ao clima do Pantanal. O lote inclui 50 novilhas prontas para IATF (Inseminação Artificial em Tempo Fixo), com idade média de 24-30 meses, e 35 vacas multíparas (2ª ou 3ª cria) com ótima carcaça. Lote negativo para tuberculose e brucelose (Teste TB e BT). Ideal para quem busca melhoramento genético e alta taxa de prenhez.', 'DISPONIVEL', 'São Paulo', 382500.00, NULL),
(24, 18, 'LOTE20251112-000024', '2025-11-12 04:23:44', '2025-11-12 05:12:14', 65, 600.00, 'Brahman', 7500.00, 'Histórico de Vacinação: Regime intensivo de prevenção.\r\nFebre Aftosa: Conforme calendário estadual.\r\nBrucelose: Todas vacinadas e com atestado negativo.\r\nMastite: Rotina de secagem seletiva e vacinação contra E. coli.\r\nControle de Casco: Casqueamento preventivo trimestral.', 'Semi-confinamento', 'Lote composto por 65 vacas leiteiras da raça Holandesa (Preto e Branco), de alta performance genética. O lote é formado por vacas em pico de lactação (10 a 150 dias pós-parto), com produção média atual de 35 litros/dia, corrigida para 3,5% de gordura. O lote inclui 30 vacas de primeira cria (novilhas) e 35 vacas de segunda e terceira cria. Todas as vacas possuem registro genealógico (Registro Leiteiro Oficial) e acompanhamento veterinário quinzenal. Ideal para expansão de rebanhos de alta tecnologia.', 'VENDIDO', 'São Paulo', 487500.00, NULL),
(25, 18, 'LOTE20251112-000025', '2025-11-12 04:25:17', '2025-11-12 04:25:17', 210, 250.00, 'Hereford', 2450.00, 'Histórico de Vacinação: Completo e protocolado para a fase de recria.\r\nFebre Aftosa: Última dose pós-desmama (Setembro/2025).\r\nBrucelose: Não aplicável (machos).\r\nClostridiose e Carbúnculo: Reforço vacinal aplicado na desmama.\r\nCastração: 100% dos machos castrados (lote de bois magros).', 'Pastagem', 'Lote composto por 210 bezerros machos castrados (bois magros), predominantemente Anelorados (Mestiços Industriais), com boa presença de sangue zebuíno e adaptabilidade. Animais de idade média de 10 a 12 meses, recém-desmamados e em ótimo ganho de peso compensatório. Lote homogêneo e ideal para quem busca animais de reposição para engorda intensiva (confinamento ou semi-confinamento). Todos os bezerros foram vermifugados e possuem brinco de identificação individual.', 'DISPONIVEL', 'São Paulo', 514500.00, NULL),
(26, 18, 'LOTE20251112-000026', '2025-11-12 04:26:39', '2025-11-12 04:26:39', 90, 580.00, 'Brahman', 4200.00, 'Histórico de Vacinação: Protocolo de abate.\r\nFebre Aftosa: Última dose em Dezembro/2024.\r\nBrucelose: Não aplicável (machos castrados).\r\nPeríodo de Carência: Todas as medicações e vermífugos respeitaram o prazo de carência para abate.\r\nSanidade: Sem incidência de doenças nos últimos 6 meses.', 'Confinamento', 'Lote de 90 novilhos e tourinhos Mestiços, com predominância de genética Angus e Canchim, caracterizados pela precocidade e excelente acabamento de carcaça (grau 4 de gordura). Animais com idade média de 18 a 20 meses (abate jovem). Lote inteiramente castrado, pronto para abate imediato (Boi de Cocho). Os animais apresentam alto rendimento de carcaça esperado (acima de 55%) devido ao cruzamento industrial. Ideal para frigoríficos ou programas de carne de qualidade.', 'DISPONIVEL', 'São Paulo', 378000.00, NULL),
(27, 18, 'LOTE20251112-000027', '2025-11-12 04:27:59', '2025-11-12 04:27:59', 75, 400.00, 'Angus', 5500.00, 'Histórico de Vacinação: Regime padrão para bacia leiteira.\r\nFebre Aftosa: Última dose em Maio/2025.\r\nBrucelose: Todas vacinadas (B19), com certificado sanitário atualizado.\r\nLeptospirose/IBR/BVD: Protocolo reprodutivo aplicado a cada 6 meses.\r\nCCS (Contagem de Células Somáticas): Média do lote abaixo de 200.000.', 'Confinamento', 'Lote composto por 75 vacas e novilhas Jersey P.O. (Puro de Origem). Os animais são conhecidos por sua rusticidade e alta eficiência alimentar. O lote é formado por 30 vacas em lactação (média de 18 litros/dia) e 45 novilhas prenhas (gestação confirmada entre 4 e 6 meses). A raça Jersey é valorizada pelo alto teor de gordura e proteína no leite, ideal para produção de queijos finos e laticínios especializados. Animais jovens, com alta vida útil produtiva esperada.', 'DISPONIVEL', 'São Paulo', 412500.00, NULL),
(28, 21, 'LOTE20260316-000028', '2026-03-16 14:20:13', '2026-03-16 14:21:03', 135, 425.00, 'Angus', 75000.00, 'ok', 'Pastagem', 'ok', 'VENDIDO', 'São Paulo', 10125000.00, NULL);

--
-- Acionadores `lote_bois`
--
DELIMITER $$
CREATE TRIGGER `trg_lote_bois_codigo` BEFORE INSERT ON `lote_bois` FOR EACH ROW BEGIN
  DECLARE prox_ai BIGINT;

  -- Pega o próximo AUTO_INCREMENT da tabela
  SELECT AUTO_INCREMENT
    INTO prox_ai
    FROM information_schema.tables
   WHERE table_schema = DATABASE()
     AND table_name   = 'lote_bois'
   LIMIT 1;

  -- Monta o código no formato LOTEyyyymmdd-000001
  SET NEW.codigo_lote = CONCAT(
    'LOTE',
    DATE_FORMAT(CURRENT_DATE, '%Y%m%d'),
    '-',
    LPAD(prox_ai, 6, '0')
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `motorista`
--

CREATE TABLE `motorista` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cpf` char(11) NOT NULL,
  `cnh_numero` varchar(20) NOT NULL,
  `cnh_categoria` enum('A','B','C','D','E','AB','AC','AD','AE') DEFAULT NULL,
  `cnh_uf` char(2) DEFAULT NULL,
  `cnh_validade` date NOT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `motorista`
--

INSERT INTO `motorista` (`id`, `nome`, `cpf`, `cnh_numero`, `cnh_categoria`, `cnh_uf`, `cnh_validade`, `telefone`, `email`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'Antonio', '89674523678', '741852789854', 'E', 'SP', '2036-09-25', '14995326147', 'antonio@gmail.com', 1, '2025-09-27 03:44:10', '2025-09-27 03:44:10'),
(2, 'Barbara Raquel', '74178975362', '7896521541236', 'E', 'PR', '2036-04-08', '1475866232', 'barb@gmail.com', 1, '2025-09-27 04:33:57', '2025-09-27 04:33:57'),
(3, 'Bob esponja', '7533575341', '9784651798465', 'AE', 'PR', '2036-04-08', '1475866232', 'bob@gmail.com', 1, '2025-09-27 04:51:48', '2025-11-05 01:21:50'),
(4, 'Peloamoracaba', '45846551254', '845645121456', 'D', 'SP', '2030-12-30', '79461385295', 'dfksio@gmail.com', 1, '2025-09-27 05:02:29', '2025-11-05 04:23:21'),
(5, 'Hanna montana', '96385212345', '74583451525345', 'E', 'SP', '2027-02-06', '1196542387', 'hanna@gmail.com', 1, '2025-09-29 22:16:51', '2025-09-29 22:16:51'),
(6, 'Fabio', '69874125836', '784852845', 'E', 'PR', '2029-10-10', '14998940708', 'fabio@gmail.com', 1, '2025-10-08 19:01:13', '2025-10-08 19:01:13'),
(7, 'Marco Antonio Coelho da Silva', '87384975384', '5437649832463', 'E', 'SP', '2028-11-28', '1196547852', 'marco.coelho@gmail.com', 1, '2025-11-12 04:59:20', '2025-11-12 04:59:20'),
(8, 'Josefina Cardoso', '24342654987', '65423587412', 'E', 'SP', '2030-05-04', '1499654127', 'josefina12card@gmail.com', 1, '2025-11-12 05:01:19', '2025-11-12 05:01:19'),
(9, 'Nelson Soares', '31952578941', '76523340987', 'AE', 'SP', '2032-08-16', '14996303114', 'nelson20@gmail.com', 1, '2025-11-12 05:02:46', '2025-11-12 05:02:46'),
(10, 'Berta Conceição Noronha', '98876541254', '1236547895', 'AE', 'SP', '2031-12-31', '11997456231', 'berta_noronha@gmail.com', 1, '2025-11-12 05:06:11', '2025-11-12 05:06:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacao_preferencias`
--

CREATE TABLE `notificacao_preferencias` (
  `id` int(11) NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_notificacao` varchar(50) NOT NULL,
  `canal_email` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `notificacao_preferencias`
--

INSERT INTO `notificacao_preferencias` (`id`, `usuario_id`, `tipo_notificacao`, `canal_email`) VALUES
(1, 1, 'COMPRA_STATUS', 1),
(2, 1, 'LOTE_DISPONIVEL', 1),
(3, 1, 'LOTE_REMOVIDO', 1),
(4, 1, 'PAGAMENTO_DEVIDO', 1),
(5, 1, 'PAGAMENTO_RECEBIDO', 1),
(6, 1, 'TRANSPORTE_SOLICITADO', 1),
(7, 1, 'TRANSPORTE_ACEITO', 1),
(8, 1, 'TRANSPORTE_RECUSADO', 1),
(9, 1, 'ENTREGA_CONFIRMADA', 0),
(10, 1, 'TRANSPORTE_ALERTA', 0),
(11, 4, 'PEDIDO_NOVO', 1),
(12, 4, 'PEDIDO_STATUS', 1),
(13, 4, 'LOTE_RESERVADO', 1),
(14, 4, 'TRANSPORTE_CRIADO', 1),
(15, 4, 'RASTREAMENTO', 1),
(16, 4, 'AGENDAMENTO_TRANSPORTE', 1),
(17, 4, 'RETIRADA_CONFIRMADA', 1),
(18, 4, 'TRANSPORTE_ALERTA', 1),
(19, 4, 'REPASSE_CRIADO', 1),
(20, 4, 'REPASSE_STATUS', 1),
(21, 4, 'PAGAMENTO_CONFIRMADO', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacao_push_tokens`
--

CREATE TABLE `notificacao_push_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` text NOT NULL,
  `auth` text NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` varchar(64) NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `mensagem` text NOT NULL,
  `dados_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_json`)),
  `relacionado_tabela` varchar(64) DEFAULT NULL,
  `relacionado_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `lida_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `notificacoes`
--

INSERT INTO `notificacoes` (`id`, `usuario_id`, `tipo`, `titulo`, `mensagem`, `dados_json`, `relacionado_tabela`, `relacionado_id`, `created_at`, `lida_em`) VALUES
(1, 4, 'PEDIDO_NOVO', 'Novo pedido #267 para o lote LOTE20251007-000015', 'Quantidade: 60 | Valor total: R$ 61200,00', '{\"pedido_id\": 267, \"pedido_item_id\": 268, \"lote_id\": 15, \"codigo_lote\": \"LOTE20251007-000015\"}', 'pedido_itens', 268, '2025-10-26 22:10:24', '2025-11-10 22:41:43'),
(2, 4, 'LOTE_RESERVADO', 'Lote LOTE20251007-000015 foi reservado', 'Reserva expira em 27/10/2025 02:40', '{\"lote_id\": 15, \"pedido_id\": 267, \"expira_em\": \"2025-10-27 02:40:24\"}', 'reservas_lote', 267, '2025-10-26 22:10:24', '2025-10-27 20:36:44'),
(3, 4, 'PEDIDO_STATUS', 'Pedido #267 atualizado para PAGO', 'Frete: R$ 2222,00 | Total: R$ 63422,00', '{\"pedido_id\": 267, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 267, '2025-10-26 22:10:26', '2025-11-10 22:41:43'),
(4, 14, 'PEDIDO_NOVO', 'Novo pedido #268 para o lote LOTE20251007-000013', 'Quantidade: 20 | Valor total: R$ 60000,00', '{\"pedido_id\": 268, \"pedido_item_id\": 269, \"lote_id\": 13, \"codigo_lote\": \"LOTE20251007-000013\"}', 'pedido_itens', 269, '2025-10-27 23:13:08', NULL),
(5, 14, 'LOTE_RESERVADO', 'Lote LOTE20251007-000013 foi reservado', 'Reserva expira em 28/10/2025 03:43', '{\"lote_id\": 13, \"pedido_id\": 268, \"expira_em\": \"2025-10-28 03:43:08\"}', 'reservas_lote', 268, '2025-10-27 23:13:08', NULL),
(7, 4, 'PEDIDO_NOVO', 'Novo pedido #270 para o lote LOTE20250904-000004', 'Quantidade: 300 | Valor total: R$ 1800000,00', '{\"pedido_id\": 270, \"pedido_item_id\": 271, \"lote_id\": 4, \"codigo_lote\": \"LOTE20250904-000004\"}', 'pedido_itens', 271, '2025-10-28 00:43:24', '2025-11-10 22:41:43'),
(8, 4, 'LOTE_RESERVADO', 'Lote LOTE20250904-000004 foi reservado', 'Reserva expira em 28/10/2025 05:13', '{\"lote_id\": 4, \"pedido_id\": 270, \"expira_em\": \"2025-10-28 05:13:24\"}', 'reservas_lote', 270, '2025-10-28 00:43:24', '2025-11-10 22:41:43'),
(9, 4, 'PEDIDO_STATUS', 'Pedido #270 atualizado para PAGO', 'Frete: R$ 2222,00 | Total: R$ 1802222,00', '{\"pedido_id\": 270, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 270, '2025-10-28 00:43:25', '2025-11-10 22:41:43'),
(10, 14, 'PEDIDO_NOVO', 'Novo pedido #271 para o lote LOTE20251007-000013', 'Quantidade: 20 | Valor total: R$ 60000,00', '{\"pedido_id\": 271, \"pedido_item_id\": 272, \"lote_id\": 13, \"codigo_lote\": \"LOTE20251007-000013\"}', 'pedido_itens', 272, '2025-10-28 16:36:10', NULL),
(11, 14, 'LOTE_RESERVADO', 'Lote LOTE20251007-000013 foi reservado', 'Reserva expira em 28/10/2025 21:06', '{\"lote_id\": 13, \"pedido_id\": 271, \"expira_em\": \"2025-10-28 21:06:10\"}', 'reservas_lote', 271, '2025-10-28 16:36:10', NULL),
(14, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #2 (Transporte #38). Você já pode começar o rastreamento.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 38, '2025-10-29 01:35:33', '2025-11-04 23:44:47'),
(15, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #2 Autorizado', 'O Frigorífico autorizou o transporte #38. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 38, '2025-10-29 01:35:33', '2025-11-10 22:41:43'),
(16, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #2', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 38, '2025-10-29 01:40:38', '2025-10-29 01:59:19'),
(17, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #2', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 38, '2025-10-29 01:40:38', '2025-11-10 22:41:43'),
(18, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #2', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 38, '2025-10-29 01:41:55', '2025-10-29 01:59:19'),
(19, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #2', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 38, '2025-10-29 01:41:55', '2025-11-10 22:41:43'),
(20, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #2', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #2 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":38,\"pedido_id\":2,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 38, '2025-10-29 01:42:36', '2025-10-29 01:59:01'),
(21, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #270 (Transporte #40). Você já pode começar o rastreamento.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 40, '2025-10-29 02:18:21', '2025-11-04 23:44:47'),
(22, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #270 Autorizado', 'O Frigorífico autorizou o transporte #40. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 40, '2025-10-29 02:18:21', '2025-11-10 22:41:43'),
(23, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #270', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 40, '2025-10-29 02:18:34', '2025-10-29 15:45:34'),
(24, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #270', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 40, '2025-10-29 02:18:34', '2025-11-10 22:41:43'),
(25, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #270', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 40, '2025-10-29 02:18:36', '2025-10-29 15:45:34'),
(26, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #270', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 40, '2025-10-29 02:18:36', '2025-11-10 22:41:43'),
(27, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #270', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #270 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":40,\"pedido_id\":270,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 40, '2025-10-29 02:18:49', '2025-10-29 15:45:34'),
(28, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #267 (Transporte #41). Você já pode começar o rastreamento.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 41, '2025-10-29 02:21:45', '2025-11-04 23:44:47'),
(29, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #267 Autorizado', 'O Frigorífico autorizou o transporte #41. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 41, '2025-10-29 02:21:45', '2025-11-10 22:41:43'),
(30, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #267', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 41, '2025-10-29 02:22:04', '2025-10-29 15:45:34'),
(31, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #267', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 41, '2025-10-29 02:22:04', '2025-11-10 22:41:43'),
(32, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #267', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 41, '2025-10-29 02:22:06', '2025-10-29 15:45:34'),
(33, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #267', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 41, '2025-10-29 02:22:06', '2025-11-10 22:41:43'),
(34, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #267', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #267 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":41,\"pedido_id\":267,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 41, '2025-10-29 02:22:23', '2025-10-29 15:45:34'),
(35, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #264 (Transporte #42). Você já pode começar o rastreamento.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 42, '2025-10-29 13:58:24', '2025-11-04 23:44:47'),
(36, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #264 Autorizado', 'O Frigorífico autorizou o transporte #42. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 42, '2025-10-29 13:58:24', '2025-11-10 22:41:43'),
(37, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #264', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 42, '2025-10-29 13:58:42', '2025-10-29 15:45:34'),
(38, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #264', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 42, '2025-10-29 13:58:42', '2025-11-10 22:41:43'),
(39, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #264', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 42, '2025-10-29 13:58:45', '2025-10-29 15:45:34'),
(40, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #264', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 42, '2025-10-29 13:58:45', '2025-11-10 22:41:43'),
(41, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #264', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #264 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":42,\"pedido_id\":264,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 42, '2025-10-29 13:59:11', '2025-10-29 15:45:34'),
(42, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #262 (Transporte #43). Você já pode começar o rastreamento.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 43, '2025-10-29 15:48:48', '2025-11-04 23:44:47'),
(43, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #262 Autorizado', 'O Frigorífico autorizou o transporte #43. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 43, '2025-10-29 15:48:48', '2025-11-10 22:41:43'),
(44, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #262', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 43, '2025-10-29 15:49:04', '2025-10-29 22:42:13'),
(45, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #262', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 43, '2025-10-29 15:49:04', '2025-11-10 22:41:43'),
(46, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #262', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 43, '2025-10-29 15:49:06', '2025-10-29 22:42:13'),
(47, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #262', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 43, '2025-10-29 15:49:06', '2025-11-10 22:41:43'),
(48, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #262', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #262 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":43,\"pedido_id\":262,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 43, '2025-10-29 15:49:23', '2025-10-29 22:42:13'),
(49, 4, 'PEDIDO_NOVO', 'Novo pedido #274 para o lote LOTE20251028-000017', 'Quantidade: 45 | Valor total: R$ 22500,00', '{\"pedido_id\": 274, \"pedido_item_id\": 275, \"lote_id\": 17, \"codigo_lote\": \"LOTE20251028-000017\"}', 'pedido_itens', 275, '2025-10-29 22:38:25', '2025-11-10 22:41:43'),
(50, 4, 'LOTE_RESERVADO', 'Lote LOTE20251028-000017 foi reservado', 'Reserva expira em 30/10/2025 03:08', '{\"lote_id\": 17, \"pedido_id\": 274, \"expira_em\": \"2025-10-30 03:08:25\"}', 'reservas_lote', 274, '2025-10-29 22:38:25', '2025-11-10 22:41:43'),
(51, 4, 'PEDIDO_NOVO', 'Novo pedido #275 para o lote LOTE20251028-000017', 'Quantidade: 45 | Valor total: R$ 22500,00', '{\"pedido_id\": 275, \"pedido_item_id\": 276, \"lote_id\": 17, \"codigo_lote\": \"LOTE20251028-000017\"}', 'pedido_itens', 276, '2025-10-29 22:38:41', '2025-11-10 22:41:43'),
(52, 4, 'PEDIDO_STATUS', 'Pedido #275 atualizado para PAGO', 'Frete: R$ 2222,00 | Total: R$ 24722,00', '{\"pedido_id\": 275, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 275, '2025-10-29 22:39:26', '2025-11-10 22:41:43'),
(53, 1, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #275 foi aprovado com sucesso. Total: R$ 24.722,00', '{\"compra_id\":275}', NULL, NULL, '2025-10-29 22:39:27', '2025-10-29 22:42:13'),
(54, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #275 (Transporte #44). Você já pode começar o rastreamento.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 44, '2025-10-29 22:47:00', '2025-11-04 23:44:47'),
(55, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #275 Autorizado', 'O Frigorífico autorizou o transporte #44. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 44, '2025-10-29 22:47:00', '2025-11-10 22:41:43'),
(56, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #275', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 44, '2025-10-29 22:47:14', '2025-11-04 20:52:30'),
(57, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #275', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 44, '2025-10-29 22:47:14', '2025-11-10 22:41:43'),
(58, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #275', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 44, '2025-10-29 22:47:19', '2025-11-04 20:52:30'),
(59, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #275', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 44, '2025-10-29 22:47:19', '2025-11-10 22:41:43'),
(60, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #275', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #275 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":44,\"pedido_id\":275,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 44, '2025-10-29 22:47:46', '2025-11-04 20:52:30'),
(61, 4, 'PEDIDO_NOVO', 'Novo pedido #276 para o lote LOTE20251029-000018', 'Quantidade: 90 | Valor total: R$ 146250,00', '{\"pedido_id\": 276, \"pedido_item_id\": 277, \"lote_id\": 18, \"codigo_lote\": \"LOTE20251029-000018\"}', 'pedido_itens', 277, '2025-11-04 21:17:14', '2025-11-10 22:41:43'),
(62, 4, 'LOTE_RESERVADO', 'Lote LOTE20251029-000018 foi reservado', 'Reserva expira em 05/11/2025 01:47', '{\"lote_id\": 18, \"pedido_id\": 276, \"expira_em\": \"2025-11-05 01:47:14\"}', 'reservas_lote', 276, '2025-11-04 21:17:14', '2025-11-10 22:41:43'),
(63, 4, 'PEDIDO_STATUS', 'Pedido #276 atualizado para PAGO', 'Frete: R$ 2222,00 | Total: R$ 148472,00', '{\"pedido_id\": 276, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 276, '2025-11-04 21:17:16', '2025-11-10 22:41:43'),
(64, 1, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #276 foi aprovado com sucesso. Total: R$ 148.472,00', '{\"compra_id\":276}', NULL, NULL, '2025-11-04 21:17:16', '2025-11-04 23:38:19'),
(65, 6, 'PEDIDO_NOVO', 'Novo pedido #277 para o lote LOTE20251029-000021', 'Quantidade: 80 | Valor total: R$ 244000,00', '{\"pedido_id\": 277, \"pedido_item_id\": 278, \"lote_id\": 21, \"codigo_lote\": \"LOTE20251029-000021\"}', 'pedido_itens', 278, '2025-11-04 23:54:52', NULL),
(66, 6, 'LOTE_RESERVADO', 'Lote LOTE20251029-000021 foi reservado', 'Reserva expira em 05/11/2025 04:24', '{\"lote_id\": 21, \"pedido_id\": 277, \"expira_em\": \"2025-11-05 04:24:52\"}', 'reservas_lote', 277, '2025-11-04 23:54:52', NULL),
(67, 6, 'PEDIDO_STATUS', 'Pedido #277 atualizado para PAGO', 'Frete: R$ 0,00 | Total: R$ 244000,00', '{\"pedido_id\": 277, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 277, '2025-11-04 23:54:53', NULL),
(68, 1, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #277 foi aprovado com sucesso. Total: R$ 244.000,00', '{\"compra_id\":277}', NULL, NULL, '2025-11-04 23:54:53', '2025-11-05 01:06:32'),
(69, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda fazenda bela vista agendou um transporte para retirada em 2025-11-06 às 08:30. Distância: 660 km. Método de pagamento: TRANSFERENCIA.', '{\"pedido_id\":\"276\",\"transporte_id\":\"45\",\"fazenda_nome\":\"fazenda bela vista\",\"data_retirada\":\"2025-11-06\",\"hora_retirada\":\"08:30\",\"distancia_km\":\"660\",\"metodo_pagamento\":\"TRANSFERENCIA\"}', 'transportes', 45, '2025-11-05 00:26:14', '2025-11-05 01:57:58'),
(70, 14, 'PEDIDO_NOVO', 'Novo pedido #278 para o lote LOTE20251007-000013', 'Quantidade: 20 | Valor total: R$ 60000,00', '{\"pedido_id\": 278, \"pedido_item_id\": 279, \"lote_id\": 13, \"codigo_lote\": \"LOTE20251007-000013\"}', 'pedido_itens', 279, '2025-11-05 00:49:49', NULL),
(71, 14, 'LOTE_RESERVADO', 'Lote LOTE20251007-000013 foi reservado', 'Reserva expira em 05/11/2025 05:19', '{\"lote_id\": 13, \"pedido_id\": 278, \"expira_em\": \"2025-11-05 05:19:49\"}', 'reservas_lote', 278, '2025-11-05 00:49:49', NULL),
(72, 14, 'PEDIDO_STATUS', 'Pedido #278 atualizado para PAGO', 'Frete: R$ 14811,50 | Total: R$ 74811,50', '{\"pedido_id\": 278, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 278, '2025-11-05 00:49:50', NULL),
(73, 1, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #278 foi aprovado com sucesso. Total: R$ 74.811,50', '{\"compra_id\":278}', NULL, NULL, '2025-11-05 00:49:50', '2025-11-05 01:06:32'),
(74, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda fazenda bela vista agendou um transporte para retirada em 2025-11-06 às 08:30. Distância: 660 km. Método de pagamento: TRANSFERENCIA.', '{\"pedido_id\":\"276\",\"transporte_id\":\"46\",\"fazenda_nome\":\"fazenda bela vista\",\"data_retirada\":\"2025-11-06\",\"hora_retirada\":\"08:30\",\"distancia_km\":\"660\",\"metodo_pagamento\":\"TRANSFERENCIA\"}', 'transportes', 46, '2025-11-05 00:49:56', '2025-11-05 01:57:58'),
(75, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda fazenda bela vista agendou um transporte para retirada em 2025-11-06 às 08:30. Distância: 660 km. Método de pagamento: TRANSFERENCIA.', '{\"pedido_id\":\"276\",\"transporte_id\":\"47\",\"fazenda_nome\":\"fazenda bela vista\",\"data_retirada\":\"2025-11-06\",\"hora_retirada\":\"08:30\",\"distancia_km\":\"660\",\"metodo_pagamento\":\"TRANSFERENCIA\"}', 'transportes', 47, '2025-11-05 00:49:57', '2025-11-05 01:57:58'),
(76, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda Recanto agendou um transporte para retirada em 2025-11-07 às 09:00. Distância: 85 km. Método de pagamento: A_COMBINAR.', '{\"pedido_id\":\"277\",\"transporte_id\":\"48\",\"fazenda_nome\":\"Recanto\",\"data_retirada\":\"2025-11-07\",\"hora_retirada\":\"09:00\",\"distancia_km\":\"85\",\"metodo_pagamento\":\"A_COMBINAR\"}', 'transportes', 48, '2025-11-05 00:51:04', '2025-11-05 01:57:58'),
(77, 1, 'TRANSPORTE_SOLICITADO', 'Autorização Pendente: Pedido #276', 'A transportadora \'TransportadoraBovina\' aceitou a coleta para 06/11/2025. Por favor, revise e autorize.', '{\"transporte_id\":46,\"pedido_id\":276,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 46, '2025-11-05 00:51:44', '2025-11-05 01:06:32'),
(78, 4, 'TRANSPORTE_ACEITO', 'Transporte Confirmado: Pedido #276', 'A transportadora \'TransportadoraBovina\' confirmou a coleta do seu lote para 06/11/2025.', '{\"transporte_id\":46,\"pedido_id\":276,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 46, '2025-11-05 00:51:44', '2025-11-10 22:41:43'),
(79, 1, 'TRANSPORTE_SOLICITADO', 'Autorização Pendente: Pedido #277', 'A transportadora \'TransportadoraBovina\' aceitou a coleta para 07/11/2025. Por favor, revise e autorize.', '{\"transporte_id\":48,\"pedido_id\":277,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 48, '2025-11-05 00:51:48', '2025-11-05 01:06:32'),
(80, 6, 'TRANSPORTE_ACEITO', 'Transporte Confirmado: Pedido #277', 'A transportadora \'TransportadoraBovina\' confirmou a coleta do seu lote para 07/11/2025.', '{\"transporte_id\":48,\"pedido_id\":277,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 48, '2025-11-05 00:51:48', NULL),
(81, 1, 'TRANSPORTE_SOLICITADO', 'Autorização Pendente: Pedido #276', 'A transportadora \'TransportadoraBovina\' aceitou a coleta para 06/11/2025. Por favor, revise e autorize.', '{\"transporte_id\":47,\"pedido_id\":276,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 47, '2025-11-05 00:51:49', '2025-11-05 01:06:32'),
(82, 4, 'TRANSPORTE_ACEITO', 'Transporte Confirmado: Pedido #276', 'A transportadora \'TransportadoraBovina\' confirmou a coleta do seu lote para 06/11/2025.', '{\"transporte_id\":47,\"pedido_id\":276,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 47, '2025-11-05 00:51:49', '2025-11-10 22:41:43'),
(83, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #277 (Transporte #48). Você já pode começar o rastreamento.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 48, '2025-11-05 00:53:25', '2025-11-05 01:57:58'),
(84, 6, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #277 Autorizado', 'O Frigorífico autorizou o transporte #48. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 48, '2025-11-05 00:53:25', NULL),
(85, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #276 (Transporte #47). Você já pode começar o rastreamento.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 47, '2025-11-05 00:53:29', '2025-11-05 01:57:58'),
(86, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #276 Autorizado', 'O Frigorífico autorizou o transporte #47. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 47, '2025-11-05 00:53:29', '2025-11-10 22:41:43'),
(87, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #276 (Transporte #46). Você já pode começar o rastreamento.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 46, '2025-11-05 00:53:33', '2025-11-05 01:57:58'),
(88, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #276 Autorizado', 'O Frigorífico autorizou o transporte #46. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 46, '2025-11-05 00:53:33', '2025-11-10 22:41:43'),
(89, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #276 (Transporte #45). Você já pode começar o rastreamento.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 45, '2025-11-05 00:53:37', '2025-11-05 01:57:58'),
(90, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #276 Autorizado', 'O Frigorífico autorizou o transporte #45. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 45, '2025-11-05 00:53:37', '2025-11-10 22:41:43'),
(91, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #276', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 45, '2025-11-05 01:04:11', '2025-11-05 01:06:32'),
(92, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #276', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 45, '2025-11-05 01:04:11', '2025-11-10 22:41:43'),
(93, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #276', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 46, '2025-11-05 01:04:14', '2025-11-05 01:06:32'),
(94, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #276', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 46, '2025-11-05 01:04:14', '2025-11-10 22:41:43'),
(95, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #276', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 47, '2025-11-05 01:04:16', '2025-11-05 01:06:32'),
(96, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #276', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 47, '2025-11-05 01:04:16', '2025-11-10 22:41:43'),
(97, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 47, '2025-11-05 01:04:19', '2025-11-05 01:06:32'),
(98, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 47, '2025-11-05 01:04:19', '2025-11-10 22:41:43'),
(99, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 46, '2025-11-05 01:04:21', '2025-11-05 01:06:32'),
(100, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 46, '2025-11-05 01:04:21', '2025-11-10 22:41:43'),
(101, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 45, '2025-11-05 01:04:23', '2025-11-05 01:06:32'),
(102, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 45, '2025-11-05 01:04:23', '2025-11-10 22:41:43'),
(103, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #277', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 48, '2025-11-05 01:04:26', '2025-11-05 01:06:32'),
(104, 6, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #277', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 48, '2025-11-05 01:04:26', NULL),
(105, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #277', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 48, '2025-11-05 01:04:29', '2025-11-05 01:06:32'),
(106, 6, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #277', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 48, '2025-11-05 01:04:29', NULL),
(107, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #277', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #277 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":48,\"pedido_id\":277,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 48, '2025-11-05 01:04:46', '2025-11-05 01:06:32'),
(108, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #276 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":45,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 45, '2025-11-05 01:06:15', '2025-11-05 01:06:32'),
(109, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #276 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":46,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 46, '2025-11-05 01:06:17', '2025-11-05 01:06:32'),
(110, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #276', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #276 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":47,\"pedido_id\":276,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 47, '2025-11-05 01:06:18', '2025-11-05 01:06:32'),
(111, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda Recanto agendou um transporte para retirada em 2025-11-09 às 16:00. Distância: 96 km. Método de pagamento: PIX.', '{\"pedido_id\":\"263\",\"transporte_id\":\"49\",\"fazenda_nome\":\"Recanto\",\"data_retirada\":\"2025-11-09\",\"hora_retirada\":\"16:00\",\"distancia_km\":\"96\",\"metodo_pagamento\":\"PIX\"}', 'transportes', 49, '2025-11-05 01:32:56', '2025-11-05 01:57:58'),
(112, 1, 'TRANSPORTE_SOLICITADO', 'Autorização Pendente: Pedido #263', 'A transportadora \'TransportadoraBovina\' aceitou a coleta para 09/11/2025. Por favor, revise e autorize.', '{\"transporte_id\":49,\"pedido_id\":263,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 49, '2025-11-05 01:33:09', NULL),
(113, 6, 'TRANSPORTE_ACEITO', 'Transporte Confirmado: Pedido #263', 'A transportadora \'TransportadoraBovina\' confirmou a coleta do seu lote para 09/11/2025.', '{\"transporte_id\":49,\"pedido_id\":263,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 49, '2025-11-05 01:33:09', NULL),
(114, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #263 (Transporte #49). Você já pode começar o rastreamento.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 49, '2025-11-05 01:34:01', '2025-11-05 01:57:58'),
(115, 6, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #263 Autorizado', 'O Frigorífico autorizou o transporte #49. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 49, '2025-11-05 01:34:01', NULL),
(116, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #263', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 49, '2025-11-05 01:34:11', NULL),
(117, 6, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #263', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 49, '2025-11-05 01:34:11', NULL),
(118, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #263', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 49, '2025-11-05 01:34:13', NULL),
(119, 6, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #263', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 49, '2025-11-05 01:34:13', NULL),
(120, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #263', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #263 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":49,\"pedido_id\":263,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 49, '2025-11-05 01:34:40', NULL),
(121, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda Recanto agendou um transporte para retirada em 2025-11-05 às 20:00. Distância: 63 km. Método de pagamento: BOLETO.', '{\"pedido_id\":\"261\",\"transporte_id\":\"50\",\"fazenda_nome\":\"Recanto\",\"data_retirada\":\"2025-11-05\",\"hora_retirada\":\"20:00\",\"distancia_km\":\"63\",\"metodo_pagamento\":\"BOLETO\"}', 'transportes', 50, '2025-11-05 17:35:12', '2025-11-10 23:17:44'),
(122, 4, 'PEDIDO_NOVO', 'Novo pedido #279 para o lote LOTE20251029-000019', 'Quantidade: 60 | Valor total: R$ 138000,00', '{\"pedido_id\": 279, \"pedido_item_id\": 280, \"lote_id\": 19, \"codigo_lote\": \"LOTE20251029-000019\"}', 'pedido_itens', 280, '2025-11-10 23:20:26', NULL),
(123, 4, 'LOTE_RESERVADO', 'Lote LOTE20251029-000019 foi reservado', 'Reserva expira em 11/11/2025 03:50', '{\"lote_id\": 19, \"pedido_id\": 279, \"expira_em\": \"2025-11-11 03:50:26\"}', 'reservas_lote', 279, '2025-11-10 23:20:26', NULL),
(124, 4, 'PEDIDO_STATUS', 'Pedido #279 atualizado para PAGO', 'Frete: R$ 2222,00 | Total: R$ 140222,00', '{\"pedido_id\": 279, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 279, '2025-11-10 23:20:27', NULL),
(125, 1, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #279 foi aprovado com sucesso. Total: R$ 140.222,00', '{\"compra_id\":279}', NULL, NULL, '2025-11-10 23:20:27', NULL),
(126, 7, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda fazenda bela vista agendou um transporte para retirada em 2025-11-11 às 16:00. Distância: 90 km. Método de pagamento: BOLETO.', '{\"pedido_id\":\"279\",\"transporte_id\":\"51\",\"fazenda_nome\":\"fazenda bela vista\",\"data_retirada\":\"2025-11-11\",\"hora_retirada\":\"16:00\",\"distancia_km\":\"90\",\"metodo_pagamento\":\"BOLETO\"}', 'transportes', 51, '2025-11-10 23:22:32', NULL),
(127, 1, 'TRANSPORTE_SOLICITADO', 'Autorização Pendente: Pedido #279', 'A transportadora \'TransportadoraBovina\' aceitou a coleta para 11/11/2025. Por favor, revise e autorize.', '{\"transporte_id\":51,\"pedido_id\":279,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 51, '2025-11-10 23:22:48', NULL),
(128, 4, 'TRANSPORTE_ACEITO', 'Transporte Confirmado: Pedido #279', 'A transportadora \'TransportadoraBovina\' confirmou a coleta do seu lote para 11/11/2025.', '{\"transporte_id\":51,\"pedido_id\":279,\"transportadora_nome\":\"TransportadoraBovina\"}', 'transportes', 51, '2025-11-10 23:22:48', NULL),
(129, 7, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #279 (Transporte #51). Você já pode começar o rastreamento.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 51, '2025-11-10 23:24:12', NULL),
(130, 4, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #279 Autorizado', 'O Frigorífico autorizou o transporte #51. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 51, '2025-11-10 23:24:12', NULL),
(131, 1, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #279', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 51, '2025-11-10 23:24:22', NULL),
(132, 4, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #279', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 51, '2025-11-10 23:24:22', '2025-11-11 01:01:19'),
(133, 1, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #279', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 51, '2025-11-10 23:24:26', NULL),
(134, 4, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #279', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 51, '2025-11-10 23:24:26', '2025-11-11 01:01:16'),
(135, 1, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #279', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #279 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":51,\"pedido_id\":279,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 51, '2025-11-10 23:25:11', NULL),
(136, 18, 'PEDIDO_NOVO', 'Novo pedido #280 para o lote LOTE20251112-000024', 'Quantidade: 65 | Valor total: R$ 487500,00', '{\"pedido_id\": 280, \"pedido_item_id\": 281, \"lote_id\": 24, \"codigo_lote\": \"LOTE20251112-000024\"}', 'pedido_itens', 281, '2025-11-12 02:12:13', NULL),
(137, 18, 'LOTE_RESERVADO', 'Lote LOTE20251112-000024 foi reservado', 'Reserva expira em 12/11/2025 06:42', '{\"lote_id\": 24, \"pedido_id\": 280, \"expira_em\": \"2025-11-12 06:42:13\"}', 'reservas_lote', 280, '2025-11-12 02:12:13', NULL),
(138, 18, 'PEDIDO_STATUS', 'Pedido #280 atualizado para PAGO', 'Frete: R$ 3217,50 | Total: R$ 490717,50', '{\"pedido_id\": 280, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 280, '2025-11-12 02:12:14', NULL),
(139, 19, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #280 foi aprovado com sucesso. Total: R$ 490.717,50', '{\"compra_id\":280}', NULL, NULL, '2025-11-12 02:12:14', '2025-11-12 02:29:22'),
(140, 20, 'SOLICITACAO_TRANSPORTE', 'Nova Solicitação de Transporte', 'A fazenda Vila Real agendou um transporte para retirada em 2025-12-11 às 14:00. Distância: 585 km. Método de pagamento: PIX.', '{\"pedido_id\":\"280\",\"transporte_id\":\"52\",\"fazenda_nome\":\"Vila Real\",\"data_retirada\":\"2025-12-11\",\"hora_retirada\":\"14:00\",\"distancia_km\":\"585\",\"metodo_pagamento\":\"PIX\"}', 'transportes', 52, '2025-11-12 02:23:46', NULL),
(141, 19, 'TRANSPORTE_SOLICITADO', 'Autorização Pendente: Pedido #280', 'A transportadora \'Uboi\' aceitou a coleta para 11/12/2025. Por favor, revise e autorize.', '{\"transporte_id\":52,\"pedido_id\":280,\"transportadora_nome\":\"Uboi\"}', 'transportes', 52, '2025-11-12 02:24:34', '2025-11-12 02:29:22'),
(142, 18, 'TRANSPORTE_ACEITO', 'Transporte Confirmado: Pedido #280', 'A transportadora \'Uboi\' confirmou a coleta do seu lote para 11/12/2025.', '{\"transporte_id\":52,\"pedido_id\":280,\"transportadora_nome\":\"Uboi\"}', 'transportes', 52, '2025-11-12 02:24:34', NULL),
(143, 20, 'TRANSPORTE_ACEITO', '🚚 Coleta Autorizada pelo Frigorífico', 'O frigorífico autorizou o início da coleta do Pedido #280 (Transporte #52). Você já pode começar o rastreamento.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 52, '2025-11-12 02:25:26', NULL),
(144, 18, 'TRANSPORTE_ACEITO', '✅ Transporte do Pedido #280 Autorizado', 'O Frigorífico autorizou o transporte #52. A transportadora iniciará o trajeto em breve.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"AUTORIZADO\"}', 'transportes', 52, '2025-11-12 02:25:26', NULL),
(145, 19, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #280', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 52, '2025-11-12 02:26:15', '2025-11-12 02:29:22'),
(146, 18, 'TRANSPORTE_ALERTA', 'Alerta de Transporte | Pedido #280', 'Status: EM TRANSITO ORIGEM. A Transportadora iniciou o trajeto! O veículo está a caminho da fazenda para a coleta.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"EM_TRANSITO_ORIGEM\"}', 'transportes', 52, '2025-11-12 02:26:15', NULL),
(147, 19, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #280', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 52, '2025-11-12 02:26:51', '2025-11-12 02:29:22'),
(148, 18, 'TRANSPORTE_SOLICITADO', 'Alerta de Transporte | Pedido #280', 'Status: CHEGOU NA FAZENDA. O veículo da transportadora chegou na fazenda. Por favor, confirme a retirada no painel da Fazenda.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"CHEGOU_NA_FAZENDA\"}', 'transportes', 52, '2025-11-12 02:26:51', NULL),
(149, 19, 'ENTREGA_CONFIRMADA', 'Alerta de Transporte | Pedido #280', 'Status: CHEGOU NO FRIGORIFICO. O transporte do Pedido #280 chegou ao seu frigorífico. Por favor, inicie o recebimento.', '{\"transporte_id\":52,\"pedido_id\":280,\"novo_status\":\"CHEGOU_NO_FRIGORIFICO\"}', 'transportes', 52, '2025-11-12 02:27:21', '2025-11-12 02:29:22'),
(150, 21, 'PEDIDO_NOVO', 'Novo pedido #281 para o lote LOTE20260316-000028', 'Quantidade: 135 | Valor total: R$ 10125000,00', '{\"pedido_id\": 281, \"pedido_item_id\": 282, \"lote_id\": 28, \"codigo_lote\": \"LOTE20260316-000028\"}', 'pedido_itens', 282, '2026-03-16 11:21:00', NULL),
(151, 21, 'LOTE_RESERVADO', 'Lote LOTE20260316-000028 foi reservado', 'Reserva expira em 16/03/2026 15:51', '{\"lote_id\": 28, \"pedido_id\": 281, \"expira_em\": \"2026-03-16 15:51:00\"}', 'reservas_lote', 281, '2026-03-16 11:21:00', NULL),
(152, 21, 'PEDIDO_STATUS', 'Pedido #281 atualizado para PAGO', 'Frete: R$ 1006,50 | Total: R$ 10126006,50', '{\"pedido_id\": 281, \"status_antigo\": \"AGUARDANDO_PAGAMENTO\", \"status_novo\": \"PAGO\"}', 'pedidos', 281, '2026-03-16 11:21:03', NULL),
(153, 1, 'PAGAMENTO_RECEBIDO', 'Pagamento Aprovado', 'O pagamento do pedido #281 foi aprovado com sucesso. Total: R$ 10.126.006,50', '{\"compra_id\":281}', NULL, NULL, '2026-03-16 11:21:03', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos`
--

CREATE TABLE `pagamentos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `metodo` enum('PIX','CARTAO') NOT NULL,
  `status` enum('PENDENTE','APROVADO','RECUSADO','CANCELADO','EXPIRADO') NOT NULL DEFAULT 'PENDENTE',
  `valor` decimal(12,2) NOT NULL,
  `moeda` char(3) NOT NULL DEFAULT 'BRL',
  `referencia_externa` varchar(100) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmado_em` datetime DEFAULT NULL,
  `expiracao_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagamentos`
--

INSERT INTO `pagamentos` (`id`, `pedido_id`, `metodo`, `status`, `valor`, `moeda`, `referencia_externa`, `payload`, `created_at`, `updated_at`, `confirmado_em`, `expiracao_em`) VALUES
(1, 1, 'PIX', 'CANCELADO', 1100000.00, 'BRL', 'SIM-1-f2515c', '{\"metodo\":\"PIX\",\"total\":1100000}', '2025-09-17 02:35:18', '2025-10-08 05:55:17', '2025-09-16 23:35:25', '2025-09-17 05:05:18'),
(2, 2, 'CARTAO', 'APROVADO', 300000.00, 'BRL', 'SIM-2-abb984', '{\"metodo\":\"CARTAO\",\"total\":300000}', '2025-09-17 02:41:12', '2025-09-17 02:42:02', '2025-09-16 23:42:02', NULL),
(3, 3, 'PIX', 'APROVADO', 250000.00, 'BRL', 'SIM-3-562d12', '{\"metodo\":\"PIX\",\"total\":250000}', '2025-09-27 01:32:07', '2025-09-27 01:32:14', '2025-09-26 22:32:14', '2025-09-27 04:02:07'),
(4, 4, 'PIX', 'PENDENTE', 270000.00, 'BRL', 'SIM-4-961ebe', '{\"metodo\":\"PIX\",\"total\":270000}', '2025-10-04 01:32:11', '2025-10-04 01:32:11', NULL, '2025-10-04 04:02:11'),
(5, 34, 'PIX', 'CANCELADO', 593292.00, 'BRL', 'SIM-34-58d061', '{\"metodo\":\"PIX\",\"total\":593292}', '2025-10-07 01:22:21', '2025-10-07 01:22:26', NULL, '2025-10-07 03:52:21'),
(6, 35, 'CARTAO', 'APROVADO', 593292.00, 'BRL', 'SIM-35-3cd4b5', '{\"metodo\":\"CARTAO\",\"total\":593292}', '2025-10-07 01:22:30', '2025-10-07 01:23:09', '2025-10-06 22:23:09', NULL),
(7, 36, 'PIX', 'APROVADO', 270000.00, 'BRL', 'SIM-36-90177a', '{\"metodo\":\"PIX\",\"total\":270000}', '2025-10-07 01:27:42', '2025-10-07 01:27:43', '2025-10-06 22:27:43', '2025-10-07 03:57:42'),
(8, 37, 'PIX', 'APROVADO', 294000.00, 'BRL', 'SIM-37-d76cf7', '{\"metodo\":\"PIX\",\"total\":294000}', '2025-10-07 04:24:03', '2025-10-07 04:24:53', '2025-10-07 01:24:53', '2025-10-07 06:54:03'),
(9, 38, 'CARTAO', 'APROVADO', 61200.00, 'BRL', 'SIM-38-59df8a', '{\"metodo\":\"CARTAO\",\"total\":61200}', '2025-10-07 22:27:03', '2025-10-07 22:27:38', '2025-10-07 19:27:38', NULL),
(10, 39, 'PIX', 'APROVADO', 57000.00, 'BRL', 'SIM-39-2647f2', '{\"metodo\":\"PIX\",\"total\":57000}', '2025-10-08 03:44:24', '2025-10-08 03:44:27', '2025-10-08 00:44:27', '2025-10-08 06:14:24'),
(11, 40, 'PIX', 'CANCELADO', 60000.00, 'BRL', 'SIM-40-431bc4', '{\"metodo\":\"PIX\",\"total\":60000}', '2025-10-08 05:19:31', '2025-10-08 05:19:37', NULL, '2025-10-08 07:49:31'),
(12, 41, 'PIX', 'CANCELADO', 60000.00, 'BRL', 'SIM-41-6ec3dc', '{\"metodo\":\"PIX\",\"total\":60000}', '2025-10-08 05:19:42', '2025-10-08 05:19:46', NULL, '2025-10-08 07:49:42'),
(13, 42, 'CARTAO', 'CANCELADO', 60000.00, 'BRL', 'SIM-42-fa4fd3', '{\"metodo\":\"CARTAO\",\"total\":60000}', '2025-10-08 05:19:49', '2025-10-08 05:19:51', NULL, NULL),
(14, 43, 'PIX', 'PENDENTE', 60000.00, 'BRL', 'SIM-43-2ce67d', '{\"metodo\":\"PIX\",\"total\":60000}', '2025-10-08 05:20:18', '2025-10-08 05:20:18', NULL, '2025-10-08 07:50:18'),
(15, 46, 'PIX', 'PENDENTE', 61200.00, 'BRL', 'SIM-46-3b383d', '{\"metodo\":\"PIX\",\"total\":61200}', '2025-10-08 05:23:37', '2025-10-08 05:23:37', NULL, '2025-10-08 07:53:37'),
(16, 133, 'PIX', 'PENDENTE', 1800000.00, 'BRL', 'SIM-133-5a310c', '{\"metodo\":\"PIX\",\"total\":1800000}', '2025-10-08 05:45:50', '2025-10-08 05:45:50', NULL, '2025-10-08 08:15:50'),
(17, 260, 'PIX', 'APROVADO', 422500.00, 'BRL', 'SIM-260-6022d7', '{\"metodo\":\"PIX\",\"total\":422500}', '2025-10-08 06:31:45', '2025-10-08 06:31:47', '2025-10-08 03:31:47', '2025-10-08 09:01:45'),
(18, 261, 'CARTAO', 'APROVADO', 42000.00, 'BRL', 'SIM-261-5d7ee9', '{\"metodo\":\"CARTAO\",\"total\":42000}', '2025-10-08 06:32:20', '2025-10-08 06:32:28', '2025-10-08 03:32:28', NULL),
(19, 262, 'PIX', 'APROVADO', 302222.00, 'BRL', 'SIM-262-193a64', '{\"metodo\":\"PIX\",\"total\":302222}', '2025-10-08 06:32:57', '2025-10-08 06:33:02', '2025-10-08 03:33:02', '2025-10-08 09:02:57'),
(20, 263, 'PIX', 'APROVADO', 800000.00, 'BRL', 'SIM-263-ed0639', '{\"metodo\":\"PIX\",\"total\":800000}', '2025-10-08 17:13:51', '2025-10-08 17:13:52', '2025-10-08 14:13:52', '2025-10-08 19:43:51'),
(21, 264, 'PIX', 'APROVADO', 2522222.00, 'BRL', 'SIM-264-90dd8f', '{\"metodo\":\"PIX\",\"total\":2522222}', '2025-10-08 18:58:56', '2025-10-08 18:58:58', '2025-10-08 15:58:58', '2025-10-08 21:28:56'),
(22, 265, 'PIX', 'CANCELADO', 63422.00, 'BRL', 'SIM-265-0151fa', '{\"metodo\":\"PIX\",\"total\":63422}', '2025-10-26 23:44:28', '2025-10-26 23:44:36', NULL, '2025-10-27 01:14:28'),
(23, 266, 'CARTAO', 'CANCELADO', 63422.00, 'BRL', 'SIM-266-ff30df', '{\"metodo\":\"CARTAO\",\"total\":63422}', '2025-10-26 23:44:39', '2025-10-26 23:44:46', NULL, NULL),
(24, 267, 'PIX', 'APROVADO', 63422.00, 'BRL', 'SIM-267-9d65bd', '{\"metodo\":\"PIX\",\"total\":63422}', '2025-10-27 01:10:24', '2025-10-27 01:10:26', '2025-10-26 22:10:26', '2025-10-27 02:40:24'),
(25, 268, 'PIX', 'PENDENTE', 74811.50, 'BRL', 'SIM-268-3912a4', '{\"metodo\":\"PIX\",\"total\":74811.5}', '2025-10-28 02:13:08', '2025-10-28 02:13:08', NULL, '2025-10-28 03:43:08'),
(26, 270, 'PIX', 'APROVADO', 1802222.00, 'BRL', 'SIM-270-cb422c', '{\"metodo\":\"PIX\",\"total\":1802222}', '2025-10-28 03:43:24', '2025-10-28 03:43:25', '2025-10-28 00:43:25', '2025-10-28 05:13:24'),
(27, 271, 'CARTAO', 'PENDENTE', 74811.50, 'BRL', 'SIM-271-191e0e', '{\"metodo\":\"CARTAO\",\"total\":74811.5}', '2025-10-28 19:36:10', '2025-10-28 19:36:10', NULL, NULL),
(28, 274, 'PIX', 'PENDENTE', 24722.00, 'BRL', 'SIM-274-94e73f', '{\"metodo\":\"PIX\",\"total\":24722}', '2025-10-30 01:38:25', '2025-10-30 01:38:25', NULL, '2025-10-30 03:08:25'),
(29, 275, 'CARTAO', 'APROVADO', 24722.00, 'BRL', 'SIM-275-470f7b', '{\"metodo\":\"CARTAO\",\"total\":24722}', '2025-10-30 01:38:41', '2025-10-30 01:39:26', '2025-10-29 22:39:26', NULL),
(30, 276, 'PIX', 'APROVADO', 148472.00, 'BRL', 'SIM-276-770629', '{\"metodo\":\"PIX\",\"total\":148472}', '2025-11-05 00:17:14', '2025-11-05 00:17:16', '2025-11-04 21:17:16', '2025-11-05 01:47:14'),
(31, 277, 'PIX', 'APROVADO', 244000.00, 'BRL', 'SIM-277-004a07', '{\"metodo\":\"PIX\",\"total\":244000}', '2025-11-05 02:54:52', '2025-11-05 02:54:53', '2025-11-04 23:54:53', '2025-11-05 04:24:52'),
(32, 278, 'PIX', 'APROVADO', 74811.50, 'BRL', 'SIM-278-3ae0ea', '{\"metodo\":\"PIX\",\"total\":74811.5}', '2025-11-05 03:49:49', '2025-11-05 03:49:50', '2025-11-05 00:49:50', '2025-11-05 05:19:49'),
(33, 279, 'PIX', 'APROVADO', 140222.00, 'BRL', 'SIM-279-a960c8', '{\"metodo\":\"PIX\",\"total\":140222}', '2025-11-11 02:20:26', '2025-11-11 02:20:27', '2025-11-10 23:20:27', '2025-11-11 03:50:26'),
(34, 280, 'PIX', 'APROVADO', 490717.50, 'BRL', 'SIM-280-a448fa', '{\"metodo\":\"PIX\",\"total\":490717.5}', '2025-11-12 05:12:13', '2025-11-12 05:12:14', '2025-11-12 02:12:14', '2025-11-12 06:42:13'),
(35, 281, 'PIX', 'APROVADO', 10126006.50, 'BRL', 'SIM-281-cbf8b6', '{\"metodo\":\"PIX\",\"total\":10126006.5}', '2026-03-16 14:21:00', '2026-03-16 14:21:03', '2026-03-16 11:21:03', '2026-03-16 15:51:00');

--
-- Acionadores `pagamentos`
--
DELIMITER $$
CREATE TRIGGER `trg_pag_aprovado` AFTER UPDATE ON `pagamentos` FOR EACH ROW BEGIN
  IF NEW.status='APROVADO' AND OLD.status <> 'APROVADO' THEN
    UPDATE pedidos 
      SET status='PAGO', pago_em=NOW()
      WHERE id=NEW.pedido_id;

    UPDATE pedido_itens 
      SET status='CONFIRMADO'
      WHERE pedido_id=NEW.pedido_id;

    UPDATE lote_bois lb
      JOIN pedido_itens pi ON pi.lote_id=lb.id
      SET lb.status='VENDIDO', lb.updated_at=NOW()
      WHERE pi.pedido_id=NEW.pedido_id;

    UPDATE repasses_fazenda
      SET status='AGENDADO'
      WHERE pagamento_id=NEW.id AND status='AGUARDANDO';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_pag_cancelado` AFTER UPDATE ON `pagamentos` FOR EACH ROW BEGIN
  IF NEW.status IN ('CANCELADO','EXPIRADO','RECUSADO') 
     AND OLD.status NOT IN ('CANCELADO','EXPIRADO','RECUSADO') THEN

    UPDATE pedidos 
      SET status='CANCELADO', cancelado_em=NOW()
      WHERE id=NEW.pedido_id;

    UPDATE pedido_itens 
      SET status='CANCELADO'
      WHERE pedido_id=NEW.pedido_id;

    UPDATE lote_bois lb
      JOIN pedido_itens pi ON pi.lote_id=lb.id
      SET lb.status='DISPONIVEL', lb.updated_at=NOW()
      WHERE pi.pedido_id=NEW.pedido_id AND lb.status='EM_NEGOCIACAO';

    DELETE FROM reservas_lote WHERE pedido_id = NEW.pedido_id;

    UPDATE repasses_fazenda
      SET status='CANCELADO'
      WHERE pagamento_id=NEW.id AND status IN ('AGUARDANDO','AGENDADO');
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_cartao`
--

CREATE TABLE `pagamentos_cartao` (
  `pagamento_id` bigint(20) UNSIGNED NOT NULL,
  `cartao_token` varchar(100) NOT NULL,
  `bandeira` varchar(20) DEFAULT NULL,
  `last4` char(4) DEFAULT NULL,
  `titular_nome` varchar(255) DEFAULT NULL,
  `exp_mes` tinyint(3) UNSIGNED DEFAULT NULL,
  `exp_ano` smallint(5) UNSIGNED DEFAULT NULL,
  `autorizacao_codigo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagamentos_cartao`
--

INSERT INTO `pagamentos_cartao` (`pagamento_id`, `cartao_token`, `bandeira`, `last4`, `titular_nome`, `exp_mes`, `exp_ano`, `autorizacao_codigo`) VALUES
(2, '3243434', 'sdfdsf', '7894', 'dvsdvsdvsv', 6, 2032, 'SIMb6d767'),
(6, 'ABC789', 'VISA', '7896', 'raven', 12, 2026, 'SIM6a10bb'),
(9, 'DEF123', 'MASTERCARD', '5678', 'barbie', 5, 2036, 'SIM74bba2'),
(18, '3243434', 'MASTERCARD', '5678', 'barbie', 6, 2029, 'SIM1a3352'),
(29, 'UHA895', 'VISA', '9874', 'DEBORA', 9, 2036, 'SIMafb10d');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pagamentos_pix`
--

CREATE TABLE `pagamentos_pix` (
  `pagamento_id` bigint(20) UNSIGNED NOT NULL,
  `pagador_id` bigint(20) UNSIGNED NOT NULL,
  `chave_destino` varchar(77) DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `copia_cola` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pagamentos_pix`
--

INSERT INTO `pagamentos_pix` (`pagamento_id`, `pagador_id`, `chave_destino`, `qr_code`, `copia_cola`) VALUES
(1, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=1|VAL=1100000.00|REF=9DED0FD0F16D', 'BRPAY|PED=1|VAL=1100000.00|REF=9DED0FD0F16D'),
(3, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=3|VAL=250000.00|REF=7196C0236912', 'BRPAY|PED=3|VAL=250000.00|REF=7196C0236912'),
(4, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=4|VAL=270000.00|REF=3A9C91F05601', 'BRPAY|PED=4|VAL=270000.00|REF=3A9C91F05601'),
(5, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=34|VAL=593292.00|REF=B792832EE251', 'BRPAY|PED=34|VAL=593292.00|REF=B792832EE251'),
(7, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=36|VAL=270000.00|REF=C3E28EC022FC', 'BRPAY|PED=36|VAL=270000.00|REF=C3E28EC022FC'),
(8, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=37|VAL=294000.00|REF=AC7166654A36', 'BRPAY|PED=37|VAL=294000.00|REF=AC7166654A36'),
(10, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=39|VAL=57000.00|REF=3146E66FE39B', 'BRPAY|PED=39|VAL=57000.00|REF=3146E66FE39B'),
(11, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=40|VAL=60000.00|REF=7BE7DCCA6DD3', 'BRPAY|PED=40|VAL=60000.00|REF=7BE7DCCA6DD3'),
(12, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=41|VAL=60000.00|REF=4C5151DA7A8F', 'BRPAY|PED=41|VAL=60000.00|REF=4C5151DA7A8F'),
(14, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=43|VAL=60000.00|REF=D10A55D64995', 'BRPAY|PED=43|VAL=60000.00|REF=D10A55D64995'),
(15, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=46|VAL=61200.00|REF=107A096C1E60', 'BRPAY|PED=46|VAL=61200.00|REF=107A096C1E60'),
(16, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=133|VAL=1800000.00|REF=0DAB96F5E720', 'BRPAY|PED=133|VAL=1800000.00|REF=0DAB96F5E720'),
(17, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=260|VAL=422500.00|REF=A039341E595E', 'BRPAY|PED=260|VAL=422500.00|REF=A039341E595E'),
(19, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=262|VAL=302222.00|REF=A33A84B7FE68', 'BRPAY|PED=262|VAL=302222.00|REF=A33A84B7FE68'),
(20, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=263|VAL=800000.00|REF=A9074F2B4D5E', 'BRPAY|PED=263|VAL=800000.00|REF=A9074F2B4D5E'),
(21, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=264|VAL=2522222.00|REF=2A626E8C7B0F', 'BRPAY|PED=264|VAL=2522222.00|REF=2A626E8C7B0F'),
(22, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=265|VAL=63422.00|REF=E1E6B50CDBD5', 'BRPAY|PED=265|VAL=63422.00|REF=E1E6B50CDBD5'),
(24, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=267|VAL=63422.00|REF=4A83622904F1', 'BRPAY|PED=267|VAL=63422.00|REF=4A83622904F1'),
(25, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=268|VAL=74811.50|REF=6E7D0E2F5354', 'BRPAY|PED=268|VAL=74811.50|REF=6E7D0E2F5354'),
(26, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=270|VAL=1802222.00|REF=0D5578ED3101', 'BRPAY|PED=270|VAL=1802222.00|REF=0D5578ED3101'),
(28, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=274|VAL=24722.00|REF=7EBBF91EB6C1', 'BRPAY|PED=274|VAL=24722.00|REF=7EBBF91EB6C1'),
(30, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=276|VAL=148472.00|REF=8C552A6F093B', 'BRPAY|PED=276|VAL=148472.00|REF=8C552A6F093B'),
(31, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=277|VAL=244000.00|REF=58EFE904453D', 'BRPAY|PED=277|VAL=244000.00|REF=58EFE904453D'),
(32, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=278|VAL=74811.50|REF=D58442A2917B', 'BRPAY|PED=278|VAL=74811.50|REF=D58442A2917B'),
(33, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=279|VAL=140222.00|REF=ED904B53F579', 'BRPAY|PED=279|VAL=140222.00|REF=ED904B53F579'),
(34, 19, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=280|VAL=490717.50|REF=A6C513287689', 'BRPAY|PED=280|VAL=490717.50|REF=A6C513287689'),
(35, 1, 'CHAVE-PIX-PLATAFORMA', 'BRPAY|PED=281|VAL=10126006.50|REF=4571AD09B85A', 'BRPAY|PED=281|VAL=10126006.50|REF=4571AD09B85A');

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `request_ip` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `usuario_id`, `token_hash`, `created_at`, `expires_at`, `used_at`, `request_ip`, `user_agent`) VALUES
(2, 16, 'b467f4831e8daead441066332c5fdcce81373336b1b41771d64d5fbd38e0400a', '2025-11-12 02:42:25', '2025-11-12 07:42:25', '2025-11-12 02:45:12', 0x7f000001, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0'),
(3, 16, '6f30abb6bdbfc9c054194a1d5fb32ca1a436826673a9ebf6e8f65d58aaf200ed', '2025-11-12 02:44:28', '2025-11-12 07:44:28', '2025-11-12 02:45:12', 0x7f000001, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:144.0) Gecko/20100101 Firefox/144.0');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `frigorifico_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('CRIADO','AGUARDANDO_PAGAMENTO','PAGO','CANCELADO') NOT NULL DEFAULT 'CRIADO',
  `total_itens` decimal(12,2) NOT NULL DEFAULT 0.00,
  `desconto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frete` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_pedido` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nf_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pago_em` datetime DEFAULT NULL,
  `cancelado_em` datetime DEFAULT NULL,
  `status_transporte` varchar(50) DEFAULT 'pendente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `frigorifico_id`, `status`, `total_itens`, `desconto`, `frete`, `total_pedido`, `nf_url`, `created_at`, `updated_at`, `pago_em`, `cancelado_em`, `status_transporte`) VALUES
(1, 1, 'CANCELADO', 1100000.00, 0.00, 0.00, 1100000.00, NULL, '2025-09-17 02:35:18', '2025-10-08 05:55:17', '2025-09-16 23:35:25', '2025-10-08 02:55:17', 'pendente'),
(2, 1, 'PAGO', 300000.00, 0.00, 0.00, 300000.00, NULL, '2025-09-17 02:41:12', '2025-09-17 02:42:02', '2025-09-16 23:42:02', NULL, 'pendente'),
(3, 1, 'PAGO', 250000.00, 0.00, 0.00, 250000.00, NULL, '2025-09-27 01:32:07', '2025-09-27 01:32:14', '2025-09-26 22:32:14', NULL, 'pendente'),
(4, 1, 'AGUARDANDO_PAGAMENTO', 270000.00, 0.00, 0.00, 270000.00, NULL, '2025-10-04 01:32:11', '2025-10-04 01:32:11', NULL, NULL, 'pendente'),
(34, 1, 'CANCELADO', 593292.00, 0.00, 0.00, 593292.00, NULL, '2025-10-07 01:22:21', '2025-10-07 01:22:27', NULL, '2025-10-06 22:22:27', 'pendente'),
(35, 1, 'PAGO', 593292.00, 0.00, 0.00, 593292.00, NULL, '2025-10-07 01:22:30', '2025-10-07 01:23:09', '2025-10-06 22:23:09', NULL, 'pendente'),
(36, 1, 'PAGO', 270000.00, 0.00, 0.00, 270000.00, NULL, '2025-10-07 01:27:42', '2025-10-07 01:27:43', '2025-10-06 22:27:43', NULL, 'pendente'),
(37, 1, 'PAGO', 294000.00, 0.00, 0.00, 294000.00, NULL, '2025-10-07 04:24:03', '2025-10-07 04:24:53', '2025-10-07 01:24:53', NULL, 'pendente'),
(38, 1, 'PAGO', 61200.00, 0.00, 0.00, 61200.00, NULL, '2025-10-07 22:27:03', '2025-10-07 22:27:38', '2025-10-07 19:27:38', NULL, 'pendente'),
(39, 1, 'PAGO', 57000.00, 0.00, 0.00, 57000.00, NULL, '2025-10-08 03:44:24', '2025-10-08 03:44:27', '2025-10-08 00:44:27', NULL, 'pendente'),
(40, 1, 'CANCELADO', 60000.00, 0.00, 0.00, 60000.00, NULL, '2025-10-08 05:19:31', '2025-10-08 05:19:37', NULL, '2025-10-08 02:19:37', 'pendente'),
(41, 1, 'CANCELADO', 60000.00, 0.00, 0.00, 60000.00, NULL, '2025-10-08 05:19:42', '2025-10-08 05:19:46', NULL, '2025-10-08 02:19:46', 'pendente'),
(42, 1, 'CANCELADO', 60000.00, 0.00, 0.00, 60000.00, NULL, '2025-10-08 05:19:49', '2025-10-08 05:19:51', NULL, '2025-10-08 02:19:51', 'pendente'),
(43, 1, 'AGUARDANDO_PAGAMENTO', 60000.00, 0.00, 0.00, 60000.00, NULL, '2025-10-08 05:20:18', '2025-10-08 05:20:18', NULL, NULL, 'pendente'),
(46, 1, 'AGUARDANDO_PAGAMENTO', 61200.00, 0.00, 0.00, 61200.00, NULL, '2025-10-08 05:23:37', '2025-10-08 05:23:37', NULL, NULL, 'pendente'),
(133, 1, 'AGUARDANDO_PAGAMENTO', 1800000.00, 0.00, 0.00, 1800000.00, NULL, '2025-10-08 05:45:50', '2025-10-08 05:45:50', NULL, NULL, 'pendente'),
(260, 1, 'PAGO', 422500.00, 0.00, 0.00, 422500.00, NULL, '2025-10-08 06:31:45', '2025-10-08 06:31:47', '2025-10-08 03:31:47', NULL, 'pendente'),
(261, 1, 'PAGO', 42000.00, 0.00, 0.00, 42000.00, NULL, '2025-10-08 06:32:20', '2025-10-08 06:32:28', '2025-10-08 03:32:28', NULL, 'pendente'),
(262, 1, 'PAGO', 300000.00, 0.00, 2222.00, 302222.00, NULL, '2025-10-08 06:32:57', '2025-10-08 06:33:02', '2025-10-08 03:33:02', NULL, 'pendente'),
(263, 1, 'PAGO', 800000.00, 0.00, 0.00, 800000.00, NULL, '2025-10-08 17:13:51', '2025-10-08 17:13:52', '2025-10-08 14:13:52', NULL, 'pendente'),
(264, 1, 'PAGO', 2520000.00, 0.00, 2222.00, 2522222.00, NULL, '2025-10-08 18:58:56', '2025-10-08 18:58:58', '2025-10-08 15:58:58', NULL, 'pendente'),
(265, 1, 'CANCELADO', 61200.00, 0.00, 2222.00, 63422.00, NULL, '2025-10-26 23:44:28', '2025-10-26 23:44:36', NULL, '2025-10-26 20:44:36', 'pendente'),
(266, 1, 'CANCELADO', 61200.00, 0.00, 2222.00, 63422.00, NULL, '2025-10-26 23:44:39', '2025-10-26 23:44:46', NULL, '2025-10-26 20:44:46', 'pendente'),
(267, 1, 'PAGO', 61200.00, 0.00, 2222.00, 63422.00, NULL, '2025-10-27 01:10:24', '2025-10-27 01:10:26', '2025-10-26 22:10:26', NULL, 'pendente'),
(268, 1, 'AGUARDANDO_PAGAMENTO', 60000.00, 0.00, 14811.50, 74811.50, NULL, '2025-10-28 02:13:08', '2025-10-28 02:13:08', NULL, NULL, 'pendente'),
(270, 1, 'PAGO', 1800000.00, 0.00, 2222.00, 1802222.00, NULL, '2025-10-28 03:43:24', '2025-10-28 03:43:25', '2025-10-28 00:43:25', NULL, 'pendente'),
(271, 1, 'AGUARDANDO_PAGAMENTO', 60000.00, 0.00, 14811.50, 74811.50, NULL, '2025-10-28 19:36:10', '2025-10-28 19:36:10', NULL, NULL, 'pendente'),
(274, 1, 'AGUARDANDO_PAGAMENTO', 22500.00, 0.00, 2222.00, 24722.00, NULL, '2025-10-30 01:38:25', '2025-10-30 01:38:25', NULL, NULL, 'pendente'),
(275, 1, 'PAGO', 22500.00, 0.00, 2222.00, 24722.00, NULL, '2025-10-30 01:38:41', '2025-10-30 01:39:26', '2025-10-29 22:39:26', NULL, 'pendente'),
(276, 1, 'PAGO', 146250.00, 0.00, 2222.00, 148472.00, NULL, '2025-11-05 00:17:14', '2025-11-05 00:17:16', '2025-11-04 21:17:16', NULL, 'pendente'),
(277, 1, 'PAGO', 244000.00, 0.00, 0.00, 244000.00, NULL, '2025-11-05 02:54:52', '2025-11-05 02:54:53', '2025-11-04 23:54:53', NULL, 'pendente'),
(278, 1, 'PAGO', 60000.00, 0.00, 14811.50, 74811.50, NULL, '2025-11-05 03:49:49', '2025-11-05 03:49:50', '2025-11-05 00:49:50', NULL, 'pendente'),
(279, 1, 'PAGO', 138000.00, 0.00, 2222.00, 140222.00, NULL, '2025-11-11 02:20:26', '2025-11-11 02:20:27', '2025-11-10 23:20:27', NULL, 'pendente'),
(280, 19, 'PAGO', 487500.00, 0.00, 3217.50, 490717.50, NULL, '2025-11-12 05:12:13', '2025-11-12 05:12:14', '2025-11-12 02:12:14', NULL, 'pendente'),
(281, 1, 'PAGO', 10125000.00, 0.00, 1006.50, 10126006.50, NULL, '2026-03-16 14:21:00', '2026-03-16 14:21:03', '2026-03-16 11:21:03', NULL, 'pendente');

--
-- Acionadores `pedidos`
--
DELIMITER $$
CREATE TRIGGER `trg_notif_pedidos_status_update` AFTER UPDATE ON `pedidos` FOR EACH ROW BEGIN
  IF (NEW.status <> OLD.status) THEN
    INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id)
    SELECT 
      pi.fazenda_id,
      'PEDIDO_STATUS',
      CONCAT('Pedido #', NEW.id, ' atualizado para ', NEW.status),
      CONCAT('Frete: R$ ', FORMAT(NEW.frete,2,'pt_BR'), 
             ' | Total: R$ ', FORMAT(NEW.total_pedido,2,'pt_BR')),
      JSON_OBJECT('pedido_id', NEW.id, 'status_antigo', OLD.status, 'status_novo', NEW.status),
      'pedidos',
      NEW.id
    FROM pedido_itens pi
    WHERE pi.pedido_id = NEW.id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedido_itens`
--

CREATE TABLE `pedido_itens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `lote_id` bigint(20) UNSIGNED NOT NULL,
  `fazenda_id` bigint(20) UNSIGNED NOT NULL,
  `codigo_lote` varchar(32) NOT NULL,
  `quantidade_cabecas` int(10) UNSIGNED NOT NULL,
  `preco_unitario_cab` decimal(12,2) NOT NULL,
  `valor_total` decimal(12,2) NOT NULL,
  `status` enum('RESERVADO','CONFIRMADO','CANCELADO') NOT NULL DEFAULT 'RESERVADO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pedido_itens`
--

INSERT INTO `pedido_itens` (`id`, `pedido_id`, `lote_id`, `fazenda_id`, `codigo_lote`, `quantidade_cabecas`, `preco_unitario_cab`, `valor_total`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 4, 'LOTE20250904-000001', 200, 1500.00, 300000.00, 'CANCELADO', '2025-09-17 02:35:18', '2025-10-08 05:55:17'),
(2, 1, 8, 6, 'LOTE20250908-000008', 100, 8000.00, 800000.00, 'CANCELADO', '2025-09-17 02:35:18', '2025-10-08 05:55:17'),
(3, 2, 2, 4, 'LOTE20250904-000002', 100, 3000.00, 300000.00, 'CONFIRMADO', '2025-09-17 02:41:12', '2025-09-17 02:42:02'),
(4, 3, 5, 4, 'LOTE20250904-000005', 50, 5000.00, 250000.00, 'CONFIRMADO', '2025-09-27 01:32:07', '2025-09-27 01:32:14'),
(5, 4, 9, 4, 'LOTE20250927-000009', 90, 3000.00, 270000.00, 'RESERVADO', '2025-10-04 01:32:11', '2025-10-04 01:32:11'),
(35, 34, 6, 4, 'LOTE20250908-000006', 98, 6054.00, 593292.00, 'CANCELADO', '2025-10-07 01:22:21', '2025-10-07 01:22:27'),
(36, 35, 6, 4, 'LOTE20250908-000006', 98, 6054.00, 593292.00, 'CONFIRMADO', '2025-10-07 01:22:30', '2025-10-07 01:23:09'),
(37, 36, 9, 4, 'LOTE20250927-000009', 90, 3000.00, 270000.00, 'CONFIRMADO', '2025-10-07 01:27:42', '2025-10-07 01:27:43'),
(38, 37, 12, 13, 'LOTE20251007-000012', 70, 4200.00, 294000.00, 'CONFIRMADO', '2025-10-07 04:24:03', '2025-10-07 04:24:53'),
(39, 38, 14, 4, 'LOTE20251007-000014', 60, 1020.00, 61200.00, 'CONFIRMADO', '2025-10-07 22:27:03', '2025-10-07 22:27:38'),
(40, 39, 10, 4, 'LOTE20251007-000010', 60, 950.00, 57000.00, 'CONFIRMADO', '2025-10-08 03:44:24', '2025-10-08 03:44:27'),
(41, 40, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'CANCELADO', '2025-10-08 05:19:31', '2025-10-08 05:19:37'),
(42, 41, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'CANCELADO', '2025-10-08 05:19:42', '2025-10-08 05:19:46'),
(43, 42, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'CANCELADO', '2025-10-08 05:19:49', '2025-10-08 05:19:51'),
(44, 43, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'RESERVADO', '2025-10-08 05:20:18', '2025-10-08 05:20:18'),
(47, 46, 15, 4, 'LOTE20251007-000015', 60, 1020.00, 61200.00, 'RESERVADO', '2025-10-08 05:23:37', '2025-10-08 05:23:37'),
(134, 133, 4, 4, 'LOTE20250904-000004', 300, 6000.00, 1800000.00, 'RESERVADO', '2025-10-08 05:45:50', '2025-10-08 05:45:50'),
(261, 260, 7, 6, 'LOTE20250908-000007', 65, 6500.00, 422500.00, 'CONFIRMADO', '2025-10-08 06:31:45', '2025-10-08 06:31:47'),
(262, 261, 11, 6, 'LOTE20251007-000011', 40, 1050.00, 42000.00, 'CONFIRMADO', '2025-10-08 06:32:20', '2025-10-08 06:32:28'),
(263, 262, 1, 4, 'LOTE20250904-000001', 200, 1500.00, 300000.00, 'CONFIRMADO', '2025-10-08 06:32:57', '2025-10-08 06:33:02'),
(264, 263, 8, 6, 'LOTE20250908-000008', 100, 8000.00, 800000.00, 'CONFIRMADO', '2025-10-08 17:13:51', '2025-10-08 17:13:52'),
(265, 264, 3, 4, 'LOTE20250904-000003', 450, 5600.00, 2520000.00, 'CONFIRMADO', '2025-10-08 18:58:56', '2025-10-08 18:58:58'),
(266, 265, 15, 4, 'LOTE20251007-000015', 60, 1020.00, 61200.00, 'CANCELADO', '2025-10-26 23:44:28', '2025-10-26 23:44:36'),
(267, 266, 15, 4, 'LOTE20251007-000015', 60, 1020.00, 61200.00, 'CANCELADO', '2025-10-26 23:44:39', '2025-10-26 23:44:46'),
(268, 267, 15, 4, 'LOTE20251007-000015', 60, 1020.00, 61200.00, 'CONFIRMADO', '2025-10-27 01:10:24', '2025-10-27 01:10:26'),
(269, 268, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'RESERVADO', '2025-10-28 02:13:08', '2025-10-28 02:13:08'),
(271, 270, 4, 4, 'LOTE20250904-000004', 300, 6000.00, 1800000.00, 'CONFIRMADO', '2025-10-28 03:43:24', '2025-10-28 03:43:25'),
(272, 271, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'RESERVADO', '2025-10-28 19:36:10', '2025-10-28 19:36:10'),
(275, 274, 17, 4, 'LOTE20251028-000017', 45, 500.00, 22500.00, 'RESERVADO', '2025-10-30 01:38:25', '2025-10-30 01:38:25'),
(276, 275, 17, 4, 'LOTE20251028-000017', 45, 500.00, 22500.00, 'CONFIRMADO', '2025-10-30 01:38:41', '2025-10-30 01:39:26'),
(277, 276, 18, 4, 'LOTE20251029-000018', 90, 1625.00, 146250.00, 'CONFIRMADO', '2025-11-05 00:17:14', '2025-11-05 00:17:16'),
(278, 277, 21, 6, 'LOTE20251029-000021', 80, 3050.00, 244000.00, 'CONFIRMADO', '2025-11-05 02:54:52', '2025-11-05 02:54:53'),
(279, 278, 13, 14, 'LOTE20251007-000013', 20, 3000.00, 60000.00, 'CONFIRMADO', '2025-11-05 03:49:49', '2025-11-05 03:49:50'),
(280, 279, 19, 4, 'LOTE20251029-000019', 60, 2300.00, 138000.00, 'CONFIRMADO', '2025-11-11 02:20:26', '2025-11-11 02:20:27'),
(281, 280, 24, 18, 'LOTE20251112-000024', 65, 7500.00, 487500.00, 'CONFIRMADO', '2025-11-12 05:12:13', '2025-11-12 05:12:14'),
(282, 281, 28, 21, 'LOTE20260316-000028', 135, 75000.00, 10125000.00, 'CONFIRMADO', '2026-03-16 14:21:00', '2026-03-16 14:21:03');

--
-- Acionadores `pedido_itens`
--
DELIMITER $$
CREATE TRIGGER `trg_notif_pedido_item_insert` AFTER INSERT ON `pedido_itens` FOR EACH ROW BEGIN
  INSERT INTO notificacoes (
    usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id
  )
  SELECT 
    pi.fazenda_id AS usuario_id,
    'PEDIDO_NOVO' AS tipo,
    CONCAT('Novo pedido #', pi.pedido_id, ' para o lote ', pi.codigo_lote) AS titulo,
    CONCAT('Quantidade: ', pi.quantidade_cabecas, ' | Valor total: R$ ',
           FORMAT(pi.valor_total,2,'pt_BR')) AS mensagem,
    JSON_OBJECT(
      'pedido_id', pi.pedido_id,
      'pedido_item_id', pi.id,
      'lote_id', pi.lote_id,
      'codigo_lote', pi.codigo_lote
    ) AS dados_json,
    'pedido_itens' AS relacionado_tabela,
    pi.id AS relacionado_id
  FROM pedido_itens pi
  WHERE pi.id = NEW.id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas_frete`
--

CREATE TABLE `propostas_frete` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `solicitacao_id` bigint(20) UNSIGNED NOT NULL,
  `transportadora_id` bigint(20) UNSIGNED NOT NULL,
  `veiculo_id` bigint(20) UNSIGNED NOT NULL,
  `motorista_id` bigint(20) UNSIGNED NOT NULL,
  `valor_proposta` decimal(10,2) NOT NULL,
  `data_estimada_retirada` date NOT NULL,
  `observacoes` text DEFAULT NULL,
  `status` enum('pendente','aceita','recusada') DEFAULT 'pendente',
  `data_proposta` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `rastreamento_transporte`
--

CREATE TABLE `rastreamento_transporte` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `proposta_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('aguardando_coleta','a_caminho_origem','chegou_origem','carregado','em_transito','chegou_destino','descarga_confirmada','concluido') DEFAULT NULL,
  `observacao` text DEFAULT NULL,
  `data_hora` datetime DEFAULT current_timestamp(),
  `localizacao` varchar(255) DEFAULT NULL,
  `evidencia_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `repasses_fazenda`
--

CREATE TABLE `repasses_fazenda` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_item_id` bigint(20) UNSIGNED NOT NULL,
  `fazenda_id` bigint(20) UNSIGNED NOT NULL,
  `pagamento_id` bigint(20) UNSIGNED DEFAULT NULL,
  `metodo` enum('PIX','CARTAO') NOT NULL,
  `status` enum('AGUARDANDO','AGENDADO','PAGO','CANCELADO') NOT NULL DEFAULT 'AGUARDANDO',
  `valor_bruto` decimal(12,2) NOT NULL,
  `taxa_plataforma_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `valor_taxa` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valor_liquido` decimal(12,2) NOT NULL,
  `chave_pix_destino` varchar(77) DEFAULT NULL,
  `previsto_em` datetime DEFAULT NULL,
  `pago_em` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reputacao_resumo`
--

CREATE TABLE `reputacao_resumo` (
  `alvo_tipo` enum('LOTE','TRANSPORTE','FAZENDA','FRIGORIFICO','TRANSPORTADORA') NOT NULL,
  `alvo_id` bigint(20) UNSIGNED NOT NULL,
  `media_geral` decimal(3,2) NOT NULL DEFAULT 0.00,
  `qtd_avaliacoes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `reputacao_resumo`
--

INSERT INTO `reputacao_resumo` (`alvo_tipo`, `alvo_id`, `media_geral`, `qtd_avaliacoes`, `updated_at`) VALUES
('FAZENDA', 4, 5.00, 1, '2025-10-27 23:35:09'),
('TRANSPORTADORA', 7, 5.00, 1, '2025-10-27 23:35:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `reservas_lote`
--

CREATE TABLE `reservas_lote` (
  `lote_id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `expira_em` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `reservas_lote`
--

INSERT INTO `reservas_lote` (`lote_id`, `pedido_id`, `expira_em`, `created_at`) VALUES
(28, 281, '2026-03-16 15:51:00', '2026-03-16 14:21:00');

--
-- Acionadores `reservas_lote`
--
DELIMITER $$
CREATE TRIGGER `trg_notif_reserva_lote_insert` AFTER INSERT ON `reservas_lote` FOR EACH ROW BEGIN
  INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, dados_json, relacionado_tabela, relacionado_id)
  SELECT 
    lb.fazenda_id,
    'LOTE_RESERVADO',
    CONCAT('Lote ', lb.codigo_lote, ' foi reservado'),
    CONCAT('Reserva expira em ', DATE_FORMAT(NEW.expira_em, '%d/%m/%Y %H:%i')),
    JSON_OBJECT('lote_id', lb.id, 'pedido_id', NEW.pedido_id, 'expira_em', NEW.expira_em),
    'reservas_lote',
    NEW.pedido_id
  FROM lote_bois lb
  WHERE lb.id = NEW.lote_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `semireboque`
--

CREATE TABLE `semireboque` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `veiculo_id` bigint(20) UNSIGNED NOT NULL,
  `placa` varchar(10) NOT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `semireboque`
--

INSERT INTO `semireboque` (`id`, `veiculo_id`, `placa`, `modelo`, `created_at`, `updated_at`) VALUES
(1, 12, 'PQR9012', 'Gaiola 3 eixos', '2025-11-12 04:46:03', '2025-11-12 04:46:03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `solicitacoes_frete`
--

CREATE TABLE `solicitacoes_frete` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `transportadora_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('aguardando_propostas','analise_frigorifico','contratado','em_transporte','concluido','cancelado') DEFAULT 'aguardando_propostas',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_limite_propostas` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `suporte_tickets`
--

CREATE TABLE `suporte_tickets` (
  `id` int(11) NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `nome_contato` varchar(255) NOT NULL,
  `email_contato` varchar(255) NOT NULL,
  `assunto` varchar(255) NOT NULL,
  `mensagem` text NOT NULL,
  `status` enum('ABERTO','FECHADO') NOT NULL DEFAULT 'ABERTO',
  `data_envio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `suporte_tickets`
--

INSERT INTO `suporte_tickets` (`id`, `usuario_id`, `nome_contato`, `email_contato`, `assunto`, `mensagem`, `status`, `data_envio`) VALUES
(1, 1, 'Carne Bovina', 'carne@gmail.com', 'teste', 'teststesttstetstst', 'ABERTO', '2025-10-27 23:54:03'),
(2, 1, 'Carne Bovina', 'carne@gmail.com', 'teste', 'teststesttstetstst', 'ABERTO', '2025-10-28 00:00:18'),
(3, 1, 'Carne Bovina', 'carne@gmail.com', 'teste', 'teststesttstetstst', 'ABERTO', '2025-10-28 00:00:47'),
(4, 1, 'Carne Bovina', 'carne@gmail.com', 'teste', 'teststesttstetstst', 'ABERTO', '2025-10-28 00:01:03'),
(5, 1, 'Carne Bovina', 'carne@gmail.com', 'teste', 'teststesttstetstst', 'ABERTO', '2025-10-28 00:03:48'),
(6, 1, 'Carne Bovina', 'carne@gmail.com', 'teste', 'teststesttstetstst', 'ABERTO', '2025-10-28 00:04:09'),
(7, 1, 'Carne Bovina', 'carne@gmail.com', 'teste2', 'hguyfygiugi', 'ABERTO', '2025-10-28 00:39:56'),
(8, 1, 'Carne Bovina', 'carne@gmail.com', 'kmdfk', 'aksmfoasjdsaoda', 'ABERTO', '2025-10-29 18:45:47'),
(9, 1, 'Carne Bovina', 'carne@gmail.com', 'DFSDF', 'SDFSDFSDFE', 'ABERTO', '2025-10-30 01:42:23'),
(10, 18, 'Vila Real', 'vilareal@gmail.com', 'socorro', 'adajndoaisdhioad', 'ABERTO', '2025-11-12 05:32:37'),
(11, 18, 'Vila Real', 'vilareal@gmail.com', 'socorro', 'adajndoaisdhioad', 'ABERTO', '2025-11-12 05:33:51'),
(12, 18, 'Vila Real', 'vilareal@gmail.com', 'gfgsdfsdf', 'sdfsdfsdfsd', 'ABERTO', '2025-11-12 05:35:08'),
(13, 20, 'Uboi', 'uboi@gmail.com', 'ausiasiohdsa', 'sjccsjjsdidiaa8s9oia\r\n', 'ABERTO', '2025-11-12 05:35:22'),
(14, 20, 'Uboi', 'uboi@gmail.com', 'ausiasiohdsa', 'sjccsjjsdidiaa8s9oia\r\n', 'ABERTO', '2025-11-12 05:37:20'),
(15, 20, 'Uboi', 'uboi@gmail.com', 'ausiasiohdsa', 'sjccsjjsdidiaa8s9oia\r\n', 'ABERTO', '2025-11-12 05:39:01'),
(16, 20, 'Uboi', 'uboi@gmail.com', 'ausiasiohdsa', 'sjccsjjsdidiaa8s9oia\r\n', 'ABERTO', '2025-11-12 05:39:07'),
(17, 20, 'Uboi', 'uboi@gmail.com', 'ausiasiohdsa', 'sjccsjjsdidiaa8s9oia\r\n', 'ABERTO', '2025-11-12 05:40:30'),
(18, 19, 'Astra', 'astra@gmail.com', 'çlkjhgfdsa', 'oiukyjyhgdcx', 'ABERTO', '2025-11-12 05:41:24');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportadora`
--

CREATE TABLE `transportadora` (
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `tipo_transportador` enum('PF','PJ') NOT NULL,
  `area_atuacao` varchar(255) DEFAULT NULL,
  `chave_pix` varchar(255) DEFAULT NULL,
  `aprovada` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `transportadora`
--

INSERT INTO `transportadora` (`usuario_id`, `tipo_transportador`, `area_atuacao`, `chave_pix`, `aprovada`) VALUES
(7, 'PF', NULL, NULL, 0),
(9, 'PJ', NULL, NULL, 0),
(20, 'PJ', NULL, NULL, 0),
(22, 'PF', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportadora_motorista`
--

CREATE TABLE `transportadora_motorista` (
  `transportadora_usuario_id` bigint(20) UNSIGNED NOT NULL,
  `motorista_id` bigint(20) UNSIGNED NOT NULL,
  `data_inicio` date NOT NULL DEFAULT curdate(),
  `data_fim` date DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `transportadora_motorista`
--

INSERT INTO `transportadora_motorista` (`transportadora_usuario_id`, `motorista_id`, `data_inicio`, `data_fim`, `principal`, `created_at`) VALUES
(7, 3, '2025-09-27', NULL, 1, '2025-09-27 04:51:48'),
(7, 4, '2025-09-27', NULL, 1, '2025-09-27 05:02:29'),
(7, 5, '2025-09-29', NULL, 1, '2025-09-29 22:16:51'),
(7, 6, '2025-10-08', NULL, 1, '2025-10-08 19:01:13'),
(20, 7, '2025-11-12', NULL, 1, '2025-11-12 04:59:20'),
(20, 8, '2025-11-12', NULL, 1, '2025-11-12 05:01:19'),
(20, 9, '2025-11-12', NULL, 1, '2025-11-12 05:02:46'),
(20, 10, '2025-11-12', NULL, 1, '2025-11-12 05:06:11');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportadora_veiculo`
--

CREATE TABLE `transportadora_veiculo` (
  `transportadora_usuario_id` bigint(20) UNSIGNED NOT NULL,
  `veiculo_id` bigint(20) UNSIGNED NOT NULL,
  `data_inicio` date NOT NULL DEFAULT curdate(),
  `data_fim` date DEFAULT NULL,
  `principal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `transportadora_veiculo`
--

INSERT INTO `transportadora_veiculo` (`transportadora_usuario_id`, `veiculo_id`, `data_inicio`, `data_fim`, `principal`, `created_at`) VALUES
(7, 6, '2025-09-27', NULL, 1, '2025-09-27 05:00:52'),
(7, 7, '2025-09-27', NULL, 1, '2025-09-27 05:16:07'),
(7, 9, '2025-09-29', NULL, 1, '2025-09-29 22:15:04'),
(7, 10, '2025-10-08', NULL, 1, '2025-10-08 19:00:24'),
(9, 8, '2025-09-27', NULL, 1, '2025-09-27 05:18:56'),
(20, 11, '2025-11-12', NULL, 1, '2025-11-12 04:30:32'),
(20, 12, '2025-11-12', NULL, 1, '2025-11-12 04:46:03'),
(20, 13, '2025-11-12', NULL, 1, '2025-11-12 04:47:25'),
(20, 14, '2025-11-12', NULL, 1, '2025-11-12 04:48:43');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportes`
--

CREATE TABLE `transportes` (
  `id` int(11) NOT NULL,
  `pedido_id` bigint(20) UNSIGNED NOT NULL,
  `fazenda_id` bigint(20) UNSIGNED NOT NULL,
  `frigorifico_id` bigint(20) UNSIGNED NOT NULL,
  `transportadora_id` bigint(20) UNSIGNED NOT NULL,
  `motorista_id` bigint(20) UNSIGNED NOT NULL,
  `veiculo_id` bigint(20) UNSIGNED NOT NULL,
  `data_retirada` date NOT NULL,
  `hora_retirada` time NOT NULL,
  `distancia_km` int(11) NOT NULL,
  `valor_transporte` decimal(10,2) DEFAULT NULL,
  `status` enum('AGENDADO','CONFIRMADO','AUTORIZADO','EM_TRANSITO_ORIGEM','CHEGOU_NA_FAZENDA','EM_TRANSITO_DESTINO','CHEGOU_NO_FRIGORIFICO','ENTREGUE','CANCELADO') DEFAULT 'AGENDADO',
  `status_aceite` enum('PENDENTE','ACEITO','RECUSADO') DEFAULT 'PENDENTE',
  `qr_retirada` varchar(100) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mensagem_transportadora` text DEFAULT NULL,
  `mensagem_frigorifico` text DEFAULT NULL,
  `metodo_pagamento_transporte` varchar(50) DEFAULT NULL COMMENT 'Método de pagamento sugerido pela Fazenda',
  `data_prevista_entrega` date DEFAULT NULL COMMENT 'Data prevista de entrega no frigorífico (informada pela transportadora)',
  `data_entrega_real` date DEFAULT NULL COMMENT 'Data real da entrega no frigorífico (confirmada pelo Frigorífico)',
  `hora_entrega_real` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `transportes`
--

INSERT INTO `transportes` (`id`, `pedido_id`, `fazenda_id`, `frigorifico_id`, `transportadora_id`, `motorista_id`, `veiculo_id`, `data_retirada`, `hora_retirada`, `distancia_km`, `valor_transporte`, `status`, `status_aceite`, `qr_retirada`, `criado_em`, `atualizado_em`, `mensagem_transportadora`, `mensagem_frigorifico`, `metodo_pagamento_transporte`, `data_prevista_entrega`, `data_entrega_real`, `hora_entrega_real`) VALUES
(27, 1, 4, 1, 7, 4, 6, '2025-06-25', '06:00:00', 33, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-08 02:41:56', '2025-10-08 02:43:50', NULL, NULL, NULL, NULL, NULL, NULL),
(28, 1, 4, 1, 7, 4, 6, '2025-06-25', '06:00:00', 33, NULL, '', 'RECUSADO', NULL, '2025-10-08 02:42:55', '2025-11-05 04:27:13', 'não quero bjs', NULL, NULL, NULL, NULL, NULL),
(29, 39, 4, 1, 7, 3, 6, '2025-02-27', '12:00:00', 90, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-08 04:06:15', '2025-10-08 04:15:55', NULL, NULL, NULL, NULL, NULL, NULL),
(30, 38, 4, 1, 7, 4, 6, '2026-05-06', '09:00:00', 120, NULL, '', 'RECUSADO', NULL, '2025-10-08 04:48:18', '2025-11-05 04:27:37', 'teste', NULL, NULL, NULL, NULL, NULL),
(31, 36, 4, 1, 7, 5, 6, '2026-05-06', '01:00:00', 600, NULL, '', 'RECUSADO', NULL, '2025-10-08 05:07:31', '2025-11-05 04:28:31', 'teste2\r\n-_-', NULL, NULL, NULL, NULL, NULL),
(32, 36, 4, 1, 7, 3, 6, '2025-06-08', '05:00:00', 520, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-08 05:08:50', '2025-10-08 05:12:00', NULL, NULL, NULL, NULL, NULL, NULL),
(33, 38, 4, 1, 7, 4, 7, '2025-05-09', '07:00:00', 98, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-08 05:16:23', '2025-10-08 05:18:34', NULL, NULL, NULL, NULL, NULL, NULL),
(34, 35, 4, 1, 7, 5, 6, '2025-09-10', '10:00:00', 90, NULL, '', 'RECUSADO', NULL, '2025-10-08 18:53:11', '2025-11-05 04:28:55', 'caro', NULL, NULL, NULL, NULL, NULL),
(35, 35, 4, 1, 7, 5, 6, '2025-05-10', '10:00:00', 90, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-08 18:56:29', '2025-10-08 18:57:54', NULL, NULL, NULL, NULL, NULL, NULL),
(36, 35, 4, 1, 7, 5, 6, '2025-05-10', '10:00:00', 90, NULL, '', 'RECUSADO', NULL, '2025-10-08 18:56:45', '2025-11-05 04:29:38', 'Não quero bjs', NULL, NULL, NULL, NULL, NULL),
(37, 3, 4, 1, 7, 3, 7, '2025-10-27', '09:00:00', 80, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-26 23:52:13', '2025-10-26 23:56:11', NULL, NULL, NULL, NULL, NULL, NULL),
(38, 2, 4, 1, 7, 4, 6, '2025-11-09', '07:00:00', 55, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-27 01:08:27', '2025-10-29 04:42:51', NULL, NULL, NULL, '2025-10-30', NULL, NULL),
(39, 270, 4, 1, 7, 5, 6, '2025-10-30', '15:00:00', 60, NULL, '', 'RECUSADO', NULL, '2025-10-29 03:37:42', '2025-10-29 04:16:29', '29-10-2025 teste', NULL, NULL, NULL, NULL, NULL),
(40, 270, 4, 1, 7, 3, 9, '2025-10-30', '16:00:00', 73, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-29 05:08:11', '2025-10-29 05:19:06', NULL, NULL, 'PIX', NULL, '2025-10-29', NULL),
(41, 267, 4, 1, 7, 6, 7, '2025-10-29', '13:00:00', 120, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-29 05:20:22', '2025-10-29 05:22:48', NULL, NULL, 'CARTAO', '2025-11-02', '2025-10-29', NULL),
(42, 264, 4, 1, 7, 4, 10, '2025-10-29', '15:00:00', 45, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-29 16:57:04', '2025-10-29 17:00:05', NULL, NULL, 'TRANSFERENCIA', '2025-11-01', '2025-10-29', NULL),
(43, 262, 4, 1, 7, 6, 7, '2025-10-29', '09:00:00', 82, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-29 18:47:36', '2025-10-29 18:49:46', NULL, NULL, 'PIX', '2025-11-01', '2025-10-29', NULL),
(44, 275, 4, 1, 7, 5, 6, '2025-10-30', '10:00:00', 84, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-10-30 01:43:50', '2025-10-30 01:48:06', NULL, NULL, 'BOLETO', '2025-11-05', '2025-10-30', NULL),
(45, 276, 4, 1, 7, 5, 6, '2025-11-06', '08:30:00', 660, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-05 03:26:14', '2025-11-05 04:06:25', NULL, NULL, 'TRANSFERENCIA', '2025-11-11', '2025-11-05', NULL),
(46, 276, 4, 1, 7, 5, 6, '2025-11-06', '08:30:00', 660, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-05 03:49:56', '2025-11-05 04:06:27', NULL, NULL, 'TRANSFERENCIA', '2025-11-11', '2025-11-05', NULL),
(47, 276, 4, 1, 7, 5, 6, '2025-11-06', '08:30:00', 660, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-05 03:49:57', '2025-11-05 04:06:29', NULL, NULL, 'TRANSFERENCIA', '2025-11-11', '2025-11-05', NULL),
(48, 277, 6, 1, 7, 4, 9, '2025-11-07', '09:00:00', 85, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-05 03:51:04', '2025-11-05 04:04:55', NULL, NULL, 'A_COMBINAR', '2025-11-16', '2025-11-05', NULL),
(49, 263, 6, 1, 7, 3, 10, '2025-11-09', '16:00:00', 96, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-05 04:32:56', '2025-11-05 04:34:49', NULL, NULL, 'PIX', '2025-11-28', '2025-11-05', NULL),
(50, 261, 6, 1, 7, 6, 9, '2025-11-05', '20:00:00', 63, NULL, 'AGENDADO', 'PENDENTE', NULL, '2025-11-05 20:35:12', '2025-11-05 20:35:12', NULL, NULL, 'BOLETO', NULL, NULL, NULL),
(51, 279, 4, 1, 7, 3, 10, '2025-11-11', '16:00:00', 90, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-11 02:22:32', '2025-11-11 02:34:26', NULL, NULL, 'BOLETO', '2025-11-16', '2025-11-10', '23:33:00'),
(52, 280, 18, 19, 20, 8, 14, '2025-12-11', '14:00:00', 585, NULL, 'ENTREGUE', 'ACEITO', NULL, '2025-11-12 05:23:46', '2025-11-12 05:27:48', NULL, NULL, 'PIX', '2025-11-16', '2025-11-12', '02:27:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tipo_usuario` enum('FAZENDA','FRIGORIFICO','TRANSPORTADORA') NOT NULL,
  `nome_razao` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefone` varchar(30) NOT NULL,
  `senha_hash` varchar(255) DEFAULT NULL,
  `cnpj` char(14) DEFAULT NULL,
  `cpf` char(11) DEFAULT NULL,
  `cep` varchar(20) NOT NULL,
  `cidade` varchar(120) NOT NULL,
  `estado` char(2) NOT NULL,
  `bairro` varchar(120) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(30) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `tipo_usuario`, `nome_razao`, `email`, `telefone`, `senha_hash`, `cnpj`, `cpf`, `cep`, `cidade`, `estado`, `bairro`, `rua`, `numero`, `complemento`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(1, 'FRIGORIFICO', 'Carne Bovina', 'carne@gmail.com', '(14) 99856-5555', '$2y$10$o7NbSj6tDJyDPmvrJjyOzupOtpAlJeFZ.h//YxI7J1eMzgJUUv1ki', '75892365666600', NULL, '18950228', 'Ipaussu', 'SP', 'Sebastiana Cunha Bueno', 'Rua Antônio Marcato', '10', 'blabla', -22.156757, -51.456716, '2025-09-05 00:41:50', '2025-09-10 03:06:56'),
(2, 'FRIGORIFICO', 'Carne Fresca', 'carnefresca@gmail.com', '(11) 69844-4444', '$2y$10$HQ4LEduEkVSpAcf0BU0f4uH0tcbrtEEwG.YD7yN2WqZEREzo7ZPgO', '89456123566669', NULL, '18953050', 'Ipaussu', 'SP', 'Conjunto Habitacional José Ramos Filho', 'Avenida Carmen Alves Magalhães', '56', 'ola', -22.156757, -51.456716, '2025-09-05 01:31:31', '2025-09-05 01:31:31'),
(4, 'FAZENDA', 'fazenda bela vista', 'belavista@gmail.com', '(14) 99856-2356', '$2y$10$ac6kBDbWWoUY6CMKbiF9ve58KSTh4.ZKonXs2MxLA71izaof1ynZ6', '88886665554444', NULL, '18950658', 'Ipaussu', 'SP', 'Parque do Brilhante', 'Rua Maria Encarnação Castilho', '25', 'bla', -25.672470, -50.445676, '2025-09-05 01:33:27', '2025-10-08 06:09:44'),
(6, 'FAZENDA', 'Recanto', 'recanto@gmail.com', '(14) 25836-9856', '$2y$10$yhhRAS.XuwKLc/smKWcJNuP/fTsUQnDUhMmPwGShrYFJCVI57uttq', '78945674185296', NULL, '18950025', 'Ipaussu', 'SP', 'Centro', 'Rua Washington Luiz', '89', 'asdasdasd', -22.156757, -51.456717, '2025-09-09 01:22:06', '2025-09-09 01:22:06'),
(7, 'TRANSPORTADORA', 'TransportadoraBovina', 'transboi@gmail.com', '(14) 78963-8528', '$2y$10$B6UYBhGDOrQmdRiyoredGecMQhrCS/6tWXP/EajBRh/V9fNBFdig6', '', '78974185296', '18950063', 'Ipaussu', 'SP', 'Centro', 'Rua Vitor Samadello', '74', 'asjdajsdpoijdasopdj', -22.156757, -51.456717, '2025-09-10 02:35:23', '2025-10-08 18:39:38'),
(9, 'TRANSPORTADORA', 'Transportes', 'transporte@gmail.com', '(74) 25685-4256', '$2y$10$lAU3L6mOi0FmuZw37j/UdO2SnodTMNhV1bXBWlsUcHnzHZTtcoe4a', '78896543567856', NULL, '18950025', 'Ipaussu', 'SP', 'Centro', 'Rua Washington Luiz', '14', 'akdiphdpasjdp', -22.156757, -51.456717, '2025-09-27 05:18:01', '2025-09-27 05:18:01'),
(13, 'FAZENDA', 'aika', 'aika@gmail.com', '(14) 99894-0708', '$2y$10$4mgxsiMFxOiLDZ0RSR.9J.skkrnrCXnHoO3EmWvG98EGk40BIVnX.', '56984125469788', NULL, '18950029', 'Ipaussu', 'SP', 'Centro', 'Travessa São Thiago', '105', 'olá sabrina carpinteira', -23.155690, -50.455890, '2025-10-07 04:18:44', '2025-10-07 04:23:22'),
(14, 'FAZENDA', 'Natasha', 'romanoff@gmail.com', '(11) 79545-2128', '$2y$10$6WHK2AkEpkXhAzBrpDuly.Q2hBN40vazGfB97ssEEusPcH/sIeq8u', '76545678909876', NULL, '18950000', 'Ipaussu', 'SP', 'marvel', 'vingadores', '4', 'avante', 2.051080, -50.794500, '2025-10-07 04:32:12', '2025-10-07 19:07:43'),
(15, 'FAZENDA', 'fazenda novo horizonte', 'fazenda@gmail.com', '(14) 78956-2894', '$2y$10$N7zoNCGjCbwV1BVZewMLSu/j9pQHDUR5q68tKnQI1sxSLT8vTGmYG', '85285856237796', NULL, '19907310', 'Ourinhos', 'SP', 'Vila Sá', 'Rua Marechal Deodoro', '310', NULL, -22.951192, -49.896765, '2025-10-08 18:49:48', '2025-10-08 18:49:48'),
(16, 'FAZENDA', 'lula mulusco', 'clarinhabertolo2528@gmail.com', '(11) 98632-5741', '$2y$10$.1ZHiULGP/6puil3QKE5FegCJ1CHxqY17QjiedG3ROxwkIHgUjQ9.', '94685297496122', NULL, '18950220', 'Ipaussu', 'SP', 'Sebastiana Cunha Bueno', 'Rua Domingos Pedraci', '67', 'nsoasoidas', -23.065109, -49.624903, '2025-10-27 00:41:59', '2025-11-12 05:45:12'),
(18, 'FAZENDA', 'Vila Real', 'vilareal@gmail.com', '(11) 98461-0641', '$2y$10$oLeZEiBE2MLj13wnYk9S0eSC.nxAeUzFFVKUjcP0lIcslbwpEGOgS', '32158721458721', NULL, '13312901', 'Itu', 'SP', 'Itaim', 'Rodovia Marechal Rondon', NULL, 'km 113,5 - ITU - SP', -23.239690, -47.368841, '2025-11-12 03:34:38', '2025-11-12 04:10:59'),
(19, 'FRIGORIFICO', 'Astra', 'astra@gmail.com', '(44) 36768-100', '$2y$10$Mz5dSiNdq3T23n6Ohg5IEeoaNT.94Ll.Icz2McrAA7hFMWgp/ICaq', '64389483957984', NULL, '87400000', 'Cruzeiro do Oeste', 'PR', '', 'Rua Peabiru, KM 01', '', '', -23.783456, -53.076281, '2025-11-12 03:37:43', '2025-11-12 05:13:38'),
(20, 'TRANSPORTADORA', 'Uboi', 'uboi@gmail.com', '(67) 84099-549', '$2y$10$0G9qLtWmHLgzS.0INPS8V.JVgT7S.2LPwvgDX6ObcCccIAzM5x8Nm', '45709737000116', '', '19600000', 'Rancharia', 'SP', '', 'Av. Pedro Toledo', '1475', '', -22.226900, -50.893000, '2025-11-12 04:01:01', '2025-11-12 05:41:11'),
(21, 'FAZENDA', 'Fazenda Boi Feliz', 'boifeliz@gmail.com', '(43) 99647-5178', '$2y$10$80RUmUkImUtuX.UXDutm3.o5.Fi.6K2TkpUWmoDa39eCGbswxfeWi', '82357460015644', NULL, '19910206', 'Ourinhos', 'SP', 'Jardim das Paineiras', 'Avenida Vitalina Marcusso', NULL, 'Fatec Ourinhos', -22.951774, -49.896555, '2026-03-16 14:17:34', '2026-03-16 14:17:34'),
(22, 'TRANSPORTADORA', 'transportadora feliz', 'transportadorafeliz@gmail.com', '(64) 64654-6465', '$2y$10$zulISdbnORFxcWAjJ.bXRO53sIanJhvt30HIRsNJZu.wBjO3g62nq', NULL, '46546546464', '86390000', 'Cambará', 'PR', 'vila santana', 'luis game', '948', NULL, NULL, NULL, '2026-03-16 14:19:19', '2026-03-16 14:19:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_cartoes`
--

CREATE TABLE `usuario_cartoes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(100) NOT NULL,
  `bandeira` varchar(20) DEFAULT NULL,
  `last4` char(4) DEFAULT NULL,
  `exp_mes` tinyint(3) UNSIGNED DEFAULT NULL,
  `exp_ano` smallint(5) UNSIGNED DEFAULT NULL,
  `titular_nome` varchar(255) DEFAULT NULL,
  `cpf_cnpj_titular` varchar(14) DEFAULT NULL,
  `apelido` varchar(100) DEFAULT NULL,
  `padrao` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_pix`
--

CREATE TABLE `usuario_pix` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `usuario_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('CPF','CNPJ','EMAIL','TELEFONE','ALEATORIA') NOT NULL,
  `chave` varchar(77) NOT NULL,
  `apelido` varchar(100) DEFAULT NULL,
  `padrao` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `veiculo`
--

CREATE TABLE `veiculo` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `placa` varchar(10) NOT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `tipo` enum('BOIADEIRO','CARRETA','TRUCK','CAMINHAO 3/4','VAN','OUTRO') NOT NULL,
  `capacidade_min` int(10) UNSIGNED DEFAULT NULL,
  `capacidade_max` int(10) UNSIGNED NOT NULL,
  `renavam` varchar(20) DEFAULT NULL,
  `crlv_validade` date DEFAULT NULL,
  `ano_fabricacao` smallint(5) UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `veiculo`
--

INSERT INTO `veiculo` (`id`, `placa`, `modelo`, `tipo`, `capacidade_min`, `capacidade_max`, `renavam`, `crlv_validade`, `ano_fabricacao`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'ASD7F78', NULL, 'TRUCK', 10, 80, 'SDF549DF', NULL, 1995, 1, '2025-09-27 03:29:32', '2025-09-27 03:29:32'),
(3, 'ASD7F52', NULL, 'BOIADEIRO', 10, 80, 'SDF549DF', NULL, 1995, 1, '2025-09-27 03:30:23', '2025-09-27 03:30:23'),
(4, 'ESD7F52', NULL, 'CAMINHAO 3/4', 10, 80, 'SDF549DF', NULL, 1995, 1, '2025-09-27 03:32:09', '2025-09-27 03:32:09'),
(5, 'AHH7S52', NULL, 'CARRETA', 10, 80, 'SDF549KH', NULL, 1998, 1, '2025-09-27 03:38:00', '2025-09-27 03:38:00'),
(6, 'EDF4H78', '', 'BOIADEIRO', 20, 95, 'DFG845DF', '2030-06-25', 2005, 1, '2025-09-27 05:00:52', '2025-11-05 02:14:41'),
(7, 'GDF4H52', NULL, 'CARRETA', 10, 50, 'RD5E74', NULL, 2011, 1, '2025-09-27 05:16:07', '2025-09-27 05:16:07'),
(8, 'ESD4R45', NULL, 'TRUCK', 15, 85, 'DSF896SD45F', NULL, 2009, 1, '2025-09-27 05:18:56', '2025-09-27 05:18:56'),
(9, 'EFG5K78', '', 'CARRETA', 20, 100, 'ASDASD845621', '2025-11-03', 2009, 1, '2025-09-29 22:15:04', '2025-11-05 02:17:04'),
(10, 'GBS5F69', NULL, 'BOIADEIRO', 10, 50, 'SADAWD', NULL, 2002, 1, '2025-10-08 19:00:24', '2025-10-08 19:00:24'),
(11, 'ABC1234', 'Mercedes-Benz Axor 2544', 'BOIADEIRO', 35, 50, '01234567890', '2026-11-15', 2023, 1, '2025-11-12 04:30:32', '2025-11-12 04:30:32'),
(12, 'XYZ5678', 'Scania R500 6x4', 'CARRETA', 80, 120, '98765432109', '2027-08-20', 2024, 1, '2025-11-12 04:46:03', '2025-11-12 04:46:03'),
(13, 'DEF7788', 'Agrale 8700 E-Tronic', 'CAMINHAO 3/4', 8, 15, '44556677889', '2026-03-05', 2020, 1, '2025-11-12 04:47:25', '2025-11-12 04:47:25'),
(14, 'GHI4455', 'Volvo VM 270 6x2', 'TRUCK', 45, 70, '77889900112', '2028-01-10', 2022, 1, '2025-11-12 04:48:43', '2025-11-12 04:48:43');

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_transportadora_motoristas_ativos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_transportadora_motoristas_ativos` (
`transportadora_usuario_id` bigint(20) unsigned
,`motorista_id` bigint(20) unsigned
,`nome` varchar(255)
,`cpf` char(11)
,`cnh_numero` varchar(20)
,`cnh_categoria` enum('A','B','C','D','E','AB','AC','AD','AE')
,`data_inicio` date
,`data_fim` date
,`principal` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `vw_transportadora_veiculos_ativos`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `vw_transportadora_veiculos_ativos` (
`transportadora_usuario_id` bigint(20) unsigned
,`veiculo_id` bigint(20) unsigned
,`placa` varchar(10)
,`tipo` enum('BOIADEIRO','CARRETA','TRUCK','CAMINHAO 3/4','VAN','OUTRO')
,`capacidade_min` int(10) unsigned
,`capacidade_max` int(10) unsigned
,`data_inicio` date
,`data_fim` date
,`principal` tinyint(1)
);

-- --------------------------------------------------------

--
-- Estrutura para view `vw_transportadora_motoristas_ativos`
--
DROP TABLE IF EXISTS `vw_transportadora_motoristas_ativos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_transportadora_motoristas_ativos`  AS SELECT `t`.`usuario_id` AS `transportadora_usuario_id`, `m`.`id` AS `motorista_id`, `m`.`nome` AS `nome`, `m`.`cpf` AS `cpf`, `m`.`cnh_numero` AS `cnh_numero`, `m`.`cnh_categoria` AS `cnh_categoria`, `tm`.`data_inicio` AS `data_inicio`, `tm`.`data_fim` AS `data_fim`, `tm`.`principal` AS `principal` FROM ((`transportadora` `t` join `transportadora_motorista` `tm` on(`tm`.`transportadora_usuario_id` = `t`.`usuario_id`)) join `motorista` `m` on(`m`.`id` = `tm`.`motorista_id`)) WHERE `tm`.`data_fim` is null ;

-- --------------------------------------------------------

--
-- Estrutura para view `vw_transportadora_veiculos_ativos`
--
DROP TABLE IF EXISTS `vw_transportadora_veiculos_ativos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_transportadora_veiculos_ativos`  AS SELECT `t`.`usuario_id` AS `transportadora_usuario_id`, `v`.`id` AS `veiculo_id`, `v`.`placa` AS `placa`, `v`.`tipo` AS `tipo`, `v`.`capacidade_min` AS `capacidade_min`, `v`.`capacidade_max` AS `capacidade_max`, `tv`.`data_inicio` AS `data_inicio`, `tv`.`data_fim` AS `data_fim`, `tv`.`principal` AS `principal` FROM ((`transportadora` `t` join `transportadora_veiculo` `tv` on(`tv`.`transportadora_usuario_id` = `t`.`usuario_id`)) join `veiculo` `v` on(`v`.`id` = `tv`.`veiculo_id`)) WHERE `tv`.`data_fim` is null ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_avaliacao_unica` (`pedido_item_id`,`alvo_tipo`,`alvo_id`,`avaliador_tipo`,`avaliador_id`),
  ADD UNIQUE KEY `uq_av_item_alvo_avaliador` (`pedido_item_id`,`alvo_tipo`,`alvo_id`,`avaliador_tipo`,`avaliador_id`),
  ADD KEY `idx_alvo` (`alvo_tipo`,`alvo_id`),
  ADD KEY `idx_avaliador` (`avaliador_tipo`,`avaliador_id`),
  ADD KEY `idx_pedido_item` (`pedido_item_id`),
  ADD KEY `fk_avaliacao_pedido` (`pedido_id`);

--
-- Índices de tabela `avaliacao_metrica`
--
ALTER TABLE `avaliacao_metrica`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_metrica` (`avaliacao_id`,`metrica_codigo`);

--
-- Índices de tabela `avaliacoes_lote`
--
ALTER TABLE `avaliacoes_lote`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_pedido_item_unico` (`pedido_item_id`),
  ADD KEY `frigorifico_id` (`frigorifico_id`),
  ADD KEY `fazenda_id` (`fazenda_id`);

--
-- Índices de tabela `avaliacoes_transporte`
--
ALTER TABLE `avaliacoes_transporte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_avaliacao_transporte` (`transporte_id`);

--
-- Índices de tabela `carrinho_persistente`
--
ALTER TABLE `carrinho_persistente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_frigo_lote` (`frigorifico_id`,`lote_id`),
  ADD KEY `lote_id` (`lote_id`);

--
-- Índices de tabela `fazenda`
--
ALTER TABLE `fazenda`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Índices de tabela `fazenda_imagens`
--
ALTER TABLE `fazenda_imagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fazenda_imagens_usuario` (`usuario_id`);

--
-- Índices de tabela `frigorifico`
--
ALTER TABLE `frigorifico`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Índices de tabela `lote_bois`
--
ALTER TABLE `lote_bois`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_lote` (`codigo_lote`),
  ADD KEY `idx_lote_bois_fazenda_status` (`fazenda_id`,`status`),
  ADD KEY `idx_lote_bois_created` (`created_at`);

--
-- Índices de tabela `motorista`
--
ALTER TABLE `motorista`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_motorista_cpf` (`cpf`),
  ADD UNIQUE KEY `uq_motorista_cnh` (`cnh_numero`);

--
-- Índices de tabela `notificacao_preferencias`
--
ALTER TABLE `notificacao_preferencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_type` (`usuario_id`,`tipo_notificacao`);

--
-- Índices de tabela `notificacao_push_tokens`
--
ALTER TABLE `notificacao_push_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`,`ativo`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_criado` (`usuario_id`,`created_at`),
  ADD KEY `idx_usuario_naolida` (`usuario_id`,`lida_em`),
  ADD KEY `idx_rel` (`relacionado_tabela`,`relacionado_id`);

--
-- Índices de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pag_pedido` (`pedido_id`),
  ADD KEY `idx_pag_status` (`status`);

--
-- Índices de tabela `pagamentos_cartao`
--
ALTER TABLE `pagamentos_cartao`
  ADD PRIMARY KEY (`pagamento_id`);

--
-- Índices de tabela `pagamentos_pix`
--
ALTER TABLE `pagamentos_pix`
  ADD PRIMARY KEY (`pagamento_id`),
  ADD KEY `fk_pp_paga` (`pagador_id`);

--
-- Índices de tabela `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_token_hash` (`token_hash`),
  ADD KEY `idx_user_expires` (`usuario_id`,`expires_at`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ped_frig_status` (`frigorifico_id`,`status`);

--
-- Índices de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pi_lote` (`lote_id`),
  ADD KEY `idx_pi_pedido` (`pedido_id`),
  ADD KEY `idx_pi_fazenda` (`fazenda_id`);

--
-- Índices de tabela `propostas_frete`
--
ALTER TABLE `propostas_frete`
  ADD PRIMARY KEY (`id`),
  ADD KEY `solicitacao_id` (`solicitacao_id`),
  ADD KEY `transportadora_id` (`transportadora_id`),
  ADD KEY `veiculo_id` (`veiculo_id`),
  ADD KEY `motorista_id` (`motorista_id`);

--
-- Índices de tabela `rastreamento_transporte`
--
ALTER TABLE `rastreamento_transporte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proposta_id` (`proposta_id`);

--
-- Índices de tabela `repasses_fazenda`
--
ALTER TABLE `repasses_fazenda`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rep_pi` (`pedido_item_id`),
  ADD KEY `fk_rep_pag` (`pagamento_id`),
  ADD KEY `idx_rep_faz_status` (`fazenda_id`,`status`),
  ADD KEY `idx_rep_previsto` (`previsto_em`);

--
-- Índices de tabela `reputacao_resumo`
--
ALTER TABLE `reputacao_resumo`
  ADD PRIMARY KEY (`alvo_tipo`,`alvo_id`);

--
-- Índices de tabela `reservas_lote`
--
ALTER TABLE `reservas_lote`
  ADD PRIMARY KEY (`lote_id`),
  ADD KEY `fk_res_pedido` (`pedido_id`);

--
-- Índices de tabela `semireboque`
--
ALTER TABLE `semireboque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_placa_veiculo` (`veiculo_id`,`placa`),
  ADD KEY `fk_semireboque_veiculo` (`veiculo_id`),
  ADD KEY `idx_semireboque_placa` (`placa`);

--
-- Índices de tabela `solicitacoes_frete`
--
ALTER TABLE `solicitacoes_frete`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `transportadora_id` (`transportadora_id`);

--
-- Índices de tabela `suporte_tickets`
--
ALTER TABLE `suporte_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `transportadora`
--
ALTER TABLE `transportadora`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Índices de tabela `transportadora_motorista`
--
ALTER TABLE `transportadora_motorista`
  ADD PRIMARY KEY (`transportadora_usuario_id`,`motorista_id`,`data_inicio`),
  ADD KEY `fk_tm_motorista` (`motorista_id`),
  ADD KEY `idx_tm_ativos` (`transportadora_usuario_id`,`data_fim`);

--
-- Índices de tabela `transportadora_veiculo`
--
ALTER TABLE `transportadora_veiculo`
  ADD PRIMARY KEY (`transportadora_usuario_id`,`veiculo_id`,`data_inicio`),
  ADD KEY `fk_tv_veiculo` (`veiculo_id`),
  ADD KEY `idx_tv_ativos` (`transportadora_usuario_id`,`data_fim`);

--
-- Índices de tabela `transportes`
--
ALTER TABLE `transportes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_retirada` (`qr_retirada`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `transportadora_id` (`transportadora_id`,`motorista_id`),
  ADD KEY `transportadora_id_2` (`transportadora_id`,`veiculo_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD UNIQUE KEY `ux_usuarios_email` (`email`),
  ADD UNIQUE KEY `uq_usuarios_cnpj` (`cnpj`),
  ADD UNIQUE KEY `uq_usuarios_cpf` (`cpf`),
  ADD KEY `idx_usuarios_localizacao` (`estado`,`cidade`,`cep`),
  ADD KEY `idx_usuarios_geo` (`latitude`,`longitude`);

--
-- Índices de tabela `usuario_cartoes`
--
ALTER TABLE `usuario_cartoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_uc_user_token` (`usuario_id`,`token`),
  ADD KEY `idx_uc_user` (`usuario_id`);

--
-- Índices de tabela `usuario_pix`
--
ALTER TABLE `usuario_pix`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_upix_user_chave` (`usuario_id`,`chave`),
  ADD KEY `idx_upix_user` (`usuario_id`);

--
-- Índices de tabela `veiculo`
--
ALTER TABLE `veiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_veiculo_placa` (`placa`),
  ADD KEY `idx_veiculo_tipo` (`tipo`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `avaliacao_metrica`
--
ALTER TABLE `avaliacao_metrica`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avaliacoes_lote`
--
ALTER TABLE `avaliacoes_lote`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `avaliacoes_transporte`
--
ALTER TABLE `avaliacoes_transporte`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `carrinho_persistente`
--
ALTER TABLE `carrinho_persistente`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fazenda_imagens`
--
ALTER TABLE `fazenda_imagens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `lote_bois`
--
ALTER TABLE `lote_bois`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de tabela `motorista`
--
ALTER TABLE `motorista`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `notificacao_preferencias`
--
ALTER TABLE `notificacao_preferencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de tabela `notificacao_push_tokens`
--
ALTER TABLE `notificacao_push_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT de tabela `pagamentos`
--
ALTER TABLE `pagamentos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=282;

--
-- AUTO_INCREMENT de tabela `pedido_itens`
--
ALTER TABLE `pedido_itens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT de tabela `propostas_frete`
--
ALTER TABLE `propostas_frete`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `rastreamento_transporte`
--
ALTER TABLE `rastreamento_transporte`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `repasses_fazenda`
--
ALTER TABLE `repasses_fazenda`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `semireboque`
--
ALTER TABLE `semireboque`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `solicitacoes_frete`
--
ALTER TABLE `solicitacoes_frete`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `suporte_tickets`
--
ALTER TABLE `suporte_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `transportes`
--
ALTER TABLE `transportes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de tabela `usuario_cartoes`
--
ALTER TABLE `usuario_cartoes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuario_pix`
--
ALTER TABLE `usuario_pix`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `veiculo`
--
ALTER TABLE `veiculo`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `avaliacao`
--
ALTER TABLE `avaliacao`
  ADD CONSTRAINT `fk_avaliacao_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `fk_avaliacao_pedido_item` FOREIGN KEY (`pedido_item_id`) REFERENCES `pedido_itens` (`id`);

--
-- Restrições para tabelas `avaliacao_metrica`
--
ALTER TABLE `avaliacao_metrica`
  ADD CONSTRAINT `fk_metrica_avaliacao` FOREIGN KEY (`avaliacao_id`) REFERENCES `avaliacao` (`id`);

--
-- Restrições para tabelas `avaliacoes_lote`
--
ALTER TABLE `avaliacoes_lote`
  ADD CONSTRAINT `avaliacoes_lote_ibfk_1` FOREIGN KEY (`pedido_item_id`) REFERENCES `pedido_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `avaliacoes_lote_ibfk_2` FOREIGN KEY (`frigorifico_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `avaliacoes_lote_ibfk_3` FOREIGN KEY (`fazenda_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `avaliacoes_transporte`
--
ALTER TABLE `avaliacoes_transporte`
  ADD CONSTRAINT `fk_avaliacao_transporte` FOREIGN KEY (`transporte_id`) REFERENCES `transportes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `carrinho_persistente`
--
ALTER TABLE `carrinho_persistente`
  ADD CONSTRAINT `carrinho_persistente_ibfk_1` FOREIGN KEY (`frigorifico_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carrinho_persistente_ibfk_2` FOREIGN KEY (`lote_id`) REFERENCES `lote_bois` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `fazenda`
--
ALTER TABLE `fazenda`
  ADD CONSTRAINT `fk_fazenda_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `fazenda_imagens`
--
ALTER TABLE `fazenda_imagens`
  ADD CONSTRAINT `fk_fazenda_imagens_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `frigorifico`
--
ALTER TABLE `frigorifico`
  ADD CONSTRAINT `fk_frigorifico_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `lote_bois`
--
ALTER TABLE `lote_bois`
  ADD CONSTRAINT `fk_lote_bois_fazenda` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`usuario_id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `notificacao_preferencias`
--
ALTER TABLE `notificacao_preferencias`
  ADD CONSTRAINT `notificacao_preferencias_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pagamentos`
--
ALTER TABLE `pagamentos`
  ADD CONSTRAINT `fk_pag_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`);

--
-- Restrições para tabelas `pagamentos_cartao`
--
ALTER TABLE `pagamentos_cartao`
  ADD CONSTRAINT `fk_pc_pag` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos` (`id`);

--
-- Restrições para tabelas `pagamentos_pix`
--
ALTER TABLE `pagamentos_pix`
  ADD CONSTRAINT `fk_pp_pag` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos` (`id`),
  ADD CONSTRAINT `fk_pp_paga` FOREIGN KEY (`pagador_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_prt_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `fk_ped_frig` FOREIGN KEY (`frigorifico_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `pedido_itens`
--
ALTER TABLE `pedido_itens`
  ADD CONSTRAINT `fk_pi_fazenda` FOREIGN KEY (`fazenda_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_pi_lote` FOREIGN KEY (`lote_id`) REFERENCES `lote_bois` (`id`),
  ADD CONSTRAINT `fk_pi_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`);

--
-- Restrições para tabelas `propostas_frete`
--
ALTER TABLE `propostas_frete`
  ADD CONSTRAINT `propostas_frete_ibfk_1` FOREIGN KEY (`solicitacao_id`) REFERENCES `solicitacoes_frete` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `propostas_frete_ibfk_2` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadora` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `propostas_frete_ibfk_3` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `propostas_frete_ibfk_4` FOREIGN KEY (`motorista_id`) REFERENCES `motorista` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `rastreamento_transporte`
--
ALTER TABLE `rastreamento_transporte`
  ADD CONSTRAINT `rastreamento_transporte_ibfk_1` FOREIGN KEY (`proposta_id`) REFERENCES `propostas_frete` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `repasses_fazenda`
--
ALTER TABLE `repasses_fazenda`
  ADD CONSTRAINT `fk_rep_faz` FOREIGN KEY (`fazenda_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_rep_pag` FOREIGN KEY (`pagamento_id`) REFERENCES `pagamentos` (`id`),
  ADD CONSTRAINT `fk_rep_pi` FOREIGN KEY (`pedido_item_id`) REFERENCES `pedido_itens` (`id`);

--
-- Restrições para tabelas `reservas_lote`
--
ALTER TABLE `reservas_lote`
  ADD CONSTRAINT `fk_res_lote` FOREIGN KEY (`lote_id`) REFERENCES `lote_bois` (`id`),
  ADD CONSTRAINT `fk_res_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`);

--
-- Restrições para tabelas `semireboque`
--
ALTER TABLE `semireboque`
  ADD CONSTRAINT `fk_semireboque_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `solicitacoes_frete`
--
ALTER TABLE `solicitacoes_frete`
  ADD CONSTRAINT `solicitacoes_frete_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `solicitacoes_frete_ibfk_2` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadora` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `suporte_tickets`
--
ALTER TABLE `suporte_tickets`
  ADD CONSTRAINT `fk_suporte_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `transportadora`
--
ALTER TABLE `transportadora`
  ADD CONSTRAINT `fk_transportadora_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `transportadora_motorista`
--
ALTER TABLE `transportadora_motorista`
  ADD CONSTRAINT `fk_tm_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motorista` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tm_transportadora` FOREIGN KEY (`transportadora_usuario_id`) REFERENCES `transportadora` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `transportadora_veiculo`
--
ALTER TABLE `transportadora_veiculo`
  ADD CONSTRAINT `fk_tv_transportadora` FOREIGN KEY (`transportadora_usuario_id`) REFERENCES `transportadora` (`usuario_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tv_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculo` (`id`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `transportes`
--
ALTER TABLE `transportes`
  ADD CONSTRAINT `transportes_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `transportes_ibfk_2` FOREIGN KEY (`transportadora_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `transportes_ibfk_3` FOREIGN KEY (`transportadora_id`,`motorista_id`) REFERENCES `transportadora_motorista` (`transportadora_usuario_id`, `motorista_id`),
  ADD CONSTRAINT `transportes_ibfk_4` FOREIGN KEY (`transportadora_id`,`veiculo_id`) REFERENCES `transportadora_veiculo` (`transportadora_usuario_id`, `veiculo_id`);

--
-- Restrições para tabelas `usuario_cartoes`
--
ALTER TABLE `usuario_cartoes`
  ADD CONSTRAINT `fk_uc_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `usuario_pix`
--
ALTER TABLE `usuario_pix`
  ADD CONSTRAINT `fk_upix_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_prt_cleanup` ON SCHEDULE EVERY 1 DAY STARTS '2025-10-26 22:01:07' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM password_reset_tokens
  WHERE (used_at IS NOT NULL AND used_at < NOW() - INTERVAL 30 DAY)
     OR (used_at IS NULL AND expires_at < NOW() - INTERVAL 7 DAY)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
