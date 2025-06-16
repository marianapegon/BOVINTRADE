-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 10/06/2025 às 16:46
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `bovintrade`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `fazenda`
--

CREATE TABLE `fazenda` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `cnpj` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(255) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `sistema_criacao` varchar(255) DEFAULT NULL,
  `cep` varchar(255) DEFAULT NULL,
  `cidade` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `bairro` varchar(255) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(255) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `nome_responsavel` varchar(255) DEFAULT NULL,
  `cpf_responsavel` varchar(255) DEFAULT NULL,
  `cargo_responsavel` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `fazenda`
--

INSERT INTO `fazenda` (`id`, `nome`, `cnpj`, `email`, `telefone`, `senha`, `sistema_criacao`, `cep`, `cidade`, `estado`, `bairro`, `rua`, `numero`, `complemento`, `nome_responsavel`, `cpf_responsavel`, `cargo_responsavel`, `latitude`, `longitude`, `tipo`) VALUES
(1, 'Fazenda feliz', '6523589', 'fazenda@gmail.com', '58465184', '1234', 'Fechado', '1561984', 'milagre', 'rj', 'limoeiro', 'smurfs', '29', 'sla', 'vindisel', '84651245651', 'gerente', -12.45712, -15.45698, 'FAZENDA'),
(2, 'Fazenda primavera', '46.379.400/0001-50', 'fazenda2@gmail.com', '1433256984', '12345', 'Pastagem', '18653047', 'Cianorte', 'PR', 'Antonio Nunes', 'Pedro Silva Cabral', '965', 'nada', 'José Bezerra', '69358412356', 'Dono', -12.45712, -15.45698, 'FAZENDA'),
(3, 'oiDeussoueudnv', '56.236.643/0001-93', 'hannahmontana@gmail.com', '43996475190', '123', 'kkk', '86390-000', 'Ourinhos', 'São Paulo', 'vila Santana', 'rua Luiz Gama', '948', 'kk', 'oiDeussoueudnv', '56465', 'lkl', 32531, NULL, 'FAZENDA');

-- --------------------------------------------------------

--
-- Estrutura para tabela `frigorifico`
--

CREATE TABLE `frigorifico` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `cnpj` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(255) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `cep` varchar(255) DEFAULT NULL,
  `cidade` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `bairro` varchar(255) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(255) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `nome_responsavel` varchar(255) DEFAULT NULL,
  `cpf_responsavel` varchar(255) DEFAULT NULL,
  `cargo_responsavel` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `frigorifico`
--

INSERT INTO `frigorifico` (`id`, `nome`, `cnpj`, `email`, `telefone`, `senha`, `cep`, `cidade`, `estado`, `bairro`, `rua`, `numero`, `complemento`, `nome_responsavel`, `cpf_responsavel`, `cargo_responsavel`, `latitude`, `longitude`, `tipo`) VALUES
(1, 'boi feliz', '6523589', 'frigorifico@gmail.com', '58465184', '1234', '1561984', 'milagre', 'rj', 'limoeiro', 'monicão', '29', 'sla', 'cebola', '84651245651', 'gerente', -12.45712, -15.45698, 'FRIGORIFICO');

-- --------------------------------------------------------

--
-- Estrutura para tabela `lote_boi`
--

CREATE TABLE `lote_boi` (
  `id` int(11) NOT NULL,
  `raca` varchar(255) DEFAULT NULL,
  `quantidade` int(11) NOT NULL,
  `peso_medio` double NOT NULL,
  `preco` double NOT NULL,
  `alimentacao` varchar(255) DEFAULT NULL,
  `historico_vacinacao` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `fazenda_id` int(11) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `lote_boi`
--

INSERT INTO `lote_boi` (`id`, `raca`, `quantidade`, `peso_medio`, `preco`, `alimentacao`, `historico_vacinacao`, `status`, `fazenda_id`, `descricao`) VALUES
(1, 'malhada', 25, 650, 2000, 'confinamento', 'andiadn', 'DISPONIVEL', 1, 'Lote de bois saudáveis, bem alimentados'),
(2, 'malhada', 50, 670, 2500, 'pastagem', '10-10-dsjdnis', 'DISPONIVEL', 1, 'Lote criado em pastagem natural'),
(3, 'nelore', 38, 520, 3600, 'sistema-integrado', '2-11-sndsin', 'DISPONIVEL', 2, 'Lote de alta qualidade, sistema integrado'),
(4, 'nelore', 42, 700, 3600, 'confinamento', 'gdfgdf', 'DISPONIVEL', 2, 'Lote robusto, ideal para abate'),
(5, 'bovina', 4, 66, 77, 'sistema-integrado', '4', NULL, 3, '4'),
(6, 'cRISTIANO RONALDO', 100, 600, 100, 'pastagem', 'OK', NULL, 3, 'A');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transportadora`
--

CREATE TABLE `transportadora` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `cnpj` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(255) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `cep` varchar(255) DEFAULT NULL,
  `cidade` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `bairro` varchar(255) DEFAULT NULL,
  `rua` varchar(255) DEFAULT NULL,
  `numero` varchar(255) DEFAULT NULL,
  `complemento` varchar(255) DEFAULT NULL,
  `nome_responsavel` varchar(255) DEFAULT NULL,
  `cpf_responsavel` varchar(255) DEFAULT NULL,
  `cargo_responsavel` varchar(255) DEFAULT NULL,
  `cnh_motorista` varchar(255) DEFAULT NULL,
  `placa_veiculo` varchar(255) DEFAULT NULL,
  `tipo_veiculo` varchar(255) DEFAULT NULL,
  `capacidade_transporte` int(11) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `transportadora`
--

INSERT INTO `transportadora` (`id`, `nome`, `cnpj`, `email`, `telefone`, `senha`, `cep`, `cidade`, `estado`, `bairro`, `rua`, `numero`, `complemento`, `nome_responsavel`, `cpf_responsavel`, `cargo_responsavel`, `cnh_motorista`, `placa_veiculo`, `tipo_veiculo`, `capacidade_transporte`, `tipo`) VALUES
(1, 'TransBoi', '6523589', 'Transportadora@gmail.com', '58465184', '1234', '1561984', 'milagre', 'rj', 'limoeiro', 'monicão', '29', 'sla', 'cebola', '84651245651', 'gerente', '165489465156', 'KHT-874', 'caminhao grande', 10000, 'TRANSPORTADORA');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transporte`
--

CREATE TABLE `transporte` (
  `id` int(11) NOT NULL,
  `data_hora` datetime DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `distancia_km` int(11) DEFAULT NULL,
  `fazenda_id` int(11) DEFAULT NULL,
  `frigorifico_id` int(11) DEFAULT NULL,
  `transportadora_id` int(11) DEFAULT NULL,
  `data_prevista` date DEFAULT NULL,
  `lote_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `fazenda`
--
ALTER TABLE `fazenda`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `frigorifico`
--
ALTER TABLE `frigorifico`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `lote_boi`
--
ALTER TABLE `lote_boi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fazenda_id` (`fazenda_id`);

--
-- Índices de tabela `transportadora`
--
ALTER TABLE `transportadora`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `transporte`
--
ALTER TABLE `transporte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fazenda_id` (`fazenda_id`),
  ADD KEY `frigorifico_id` (`frigorifico_id`),
  ADD KEY `lote_id` (`lote_id`),
  ADD KEY `FKffbypawexarx1yskqp2idx2yg` (`transportadora_id`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `fazenda`
--
ALTER TABLE `fazenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `frigorifico`
--
ALTER TABLE `frigorifico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `lote_boi`
--
ALTER TABLE `lote_boi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `transportadora`
--
ALTER TABLE `transportadora`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `transporte`
--
ALTER TABLE `transporte`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `lote_boi`
--
ALTER TABLE `lote_boi`
  ADD CONSTRAINT `lote_boi_ibfk_1` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `transporte`
--
ALTER TABLE `transporte`
  ADD CONSTRAINT `FKffbypawexarx1yskqp2idx2yg` FOREIGN KEY (`transportadora_id`) REFERENCES `usuario` (`id`),
  ADD CONSTRAINT `transporte_ibfk_1` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`),
  ADD CONSTRAINT `transporte_ibfk_2` FOREIGN KEY (`frigorifico_id`) REFERENCES `frigorifico` (`id`),
  ADD CONSTRAINT `transporte_ibfk_3` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadora` (`id`),
  ADD CONSTRAINT `transporte_ibfk_4` FOREIGN KEY (`lote_id`) REFERENCES `lote_boi` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
