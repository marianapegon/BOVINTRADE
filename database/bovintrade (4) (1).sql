-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16-Jun-2025 às 08:07
-- Versão do servidor: 10.3.16-MariaDB
-- versão do PHP: 7.3.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
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
-- Estrutura da tabela `avaliacao`
--

CREATE TABLE `avaliacao` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `tipo` enum('QUALIDADE_LOTE','PROCESSO_PAGAMENTO') NOT NULL,
  `nota` tinyint(4) NOT NULL
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `compra`
--

CREATE TABLE `compra` (
  `id` int(11) NOT NULL,
  `frigorifico_id` int(11) NOT NULL,
  `fazenda_id` int(11) DEFAULT NULL,
  `status` enum('CARRINHO','AGUARDANDO_PAGAMENTO','PAGO','CANCELADO','CONCLUIDO') NOT NULL DEFAULT 'CARRINHO',
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `compra_item`
--

CREATE TABLE `compra_item` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `lote_id` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `preco_unitario` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `conta_bancaria`
--

CREATE TABLE `conta_bancaria` (
  `id` int(11) NOT NULL,
  `fazenda_id` int(11) NOT NULL,
  `banco` varchar(100) NOT NULL,
  `agencia` varchar(20) NOT NULL,
  `conta` varchar(20) NOT NULL,
  `tipo_conta` enum('CORRENTE','POUPANCA') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `fazenda`
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Extraindo dados da tabela `fazenda`
--

INSERT INTO `fazenda` (`id`, `nome`, `cnpj`, `email`, `telefone`, `senha`, `sistema_criacao`, `cep`, `cidade`, `estado`, `bairro`, `rua`, `numero`, `complemento`, `nome_responsavel`, `cpf_responsavel`, `cargo_responsavel`, `latitude`, `longitude`, `tipo`) VALUES
(1, 'Fazenda primavera', '46.379.400/0001-50', 'fazenda@gmail.com', '(14)99855-2222', '1234', 'Semi-confinamento', '18653047', 'Cianorte', 'PR', 'Antonio Nunes', 'Pedro Silva Cabral', '965', 'nada', 'Jose', '222.333.444-55', 'dono', -12.45712, -15.45698, 'FAZENDA');

-- --------------------------------------------------------

--
-- Estrutura da tabela `forma_pagamento`
--

CREATE TABLE `forma_pagamento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Extraindo dados da tabela `forma_pagamento`
--

INSERT INTO `forma_pagamento` (`id`, `nome`) VALUES
(3, 'CARTAO'),
(1, 'PIX'),
(2, 'TRANSFERENCIA');

-- --------------------------------------------------------

--
-- Estrutura da tabela `frigorifico`
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `lote_boi`
--

CREATE TABLE `lote_boi` (
  `id` int(11) NOT NULL,
  `raca` varchar(255) DEFAULT NULL,
  `quantidade` int(11) NOT NULL,
  `peso_medio` double NOT NULL,
  `preco` double NOT NULL,
  `alimentacao` varchar(255) DEFAULT NULL,
  `historico_vacinacao` varchar(255) DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `fazenda_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `nota_fiscal`
--

CREATE TABLE `nota_fiscal` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `numero` varchar(50) NOT NULL,
  `arquivo_url` varchar(255) NOT NULL,
  `emitida_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacao`
--

CREATE TABLE `notificacao` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `conteudo` text NOT NULL,
  `url_referencia` varchar(255) DEFAULT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `criada_em` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `notificacao_preferencia`
--

CREATE TABLE `notificacao_preferencia` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `habilitada` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `pagamento`
--

CREATE TABLE `pagamento` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `forma_id` int(11) NOT NULL,
  `status` enum('PENDENTE','CONFIRMADO','CANCELADO','FALHA') NOT NULL DEFAULT 'PENDENTE',
  `valor` decimal(12,2) NOT NULL,
  `dados_pix` text DEFAULT NULL,
  `dados_transferencia` text DEFAULT NULL,
  `dados_cartao` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `pix_key`
--

CREATE TABLE `pix_key` (
  `id` int(11) NOT NULL,
  `fazenda_id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `tipo_chave` enum('CPF','CNPJ','EMAIL','TELEFONE','ALEATORIA') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `retirada_validacao`
--

CREATE TABLE `retirada_validacao` (
  `id` int(11) NOT NULL,
  `transporte_id` int(11) NOT NULL,
  `entregador_nome` varchar(255) NOT NULL,
  `retirado_por` varchar(255) NOT NULL,
  `data_hora` datetime NOT NULL,
  `documento_url` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `transportadora`
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
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `transporte`
--

CREATE TABLE `transporte` (
  `id` int(11) NOT NULL,
  `data_hora` datetime DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `distancia_km` int(11) DEFAULT NULL,
  `fazenda_id` int(11) DEFAULT NULL,
  `frigorifico_id` int(11) DEFAULT NULL,
  `transportadora_id` int(11) DEFAULT NULL,
  `compra_id` int(11) DEFAULT NULL,
  `data_prevista` date DEFAULT NULL,
  `lote_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Estrutura da tabela `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `senha` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `compra`
--
ALTER TABLE `compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `frigorifico_id` (`frigorifico_id`),
  ADD KEY `fazenda_id` (`fazenda_id`);

--
-- Índices para tabela `compra_item`
--
ALTER TABLE `compra_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compra_id` (`compra_id`),
  ADD KEY `lote_id` (`lote_id`);

--
-- Índices para tabela `conta_bancaria`
--
ALTER TABLE `conta_bancaria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fazenda_id` (`fazenda_id`);

--
-- Índices para tabela `fazenda`
--
ALTER TABLE `fazenda`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `forma_pagamento`
--
ALTER TABLE `forma_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices para tabela `frigorifico`
--
ALTER TABLE `frigorifico`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `lote_boi`
--
ALTER TABLE `lote_boi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fazenda_id` (`fazenda_id`);

--
-- Índices para tabela `nota_fiscal`
--
ALTER TABLE `nota_fiscal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compra_id` (`compra_id`);

--
-- Índices para tabela `notificacao`
--
ALTER TABLE `notificacao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices para tabela `notificacao_preferencia`
--
ALTER TABLE `notificacao_preferencia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Índices para tabela `pagamento`
--
ALTER TABLE `pagamento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compra_id` (`compra_id`),
  ADD KEY `forma_id` (`forma_id`);

--
-- Índices para tabela `pix_key`
--
ALTER TABLE `pix_key`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fazenda_id` (`fazenda_id`);

--
-- Índices para tabela `retirada_validacao`
--
ALTER TABLE `retirada_validacao`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transporte_id` (`transporte_id`);

--
-- Índices para tabela `transportadora`
--
ALTER TABLE `transportadora`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `transporte`
--
ALTER TABLE `transporte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fazenda_id` (`fazenda_id`),
  ADD KEY `frigorifico_id` (`frigorifico_id`),
  ADD KEY `lote_id` (`lote_id`),
  ADD KEY `compra_id` (`compra_id`),
  ADD KEY `FKffbypawexarx1yskqp2idx2yg` (`transportadora_id`);

--
-- Índices para tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacao`
--
ALTER TABLE `avaliacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compra`
--
ALTER TABLE `compra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `compra_item`
--
ALTER TABLE `compra_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `conta_bancaria`
--
ALTER TABLE `conta_bancaria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `fazenda`
--
ALTER TABLE `fazenda`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `forma_pagamento`
--
ALTER TABLE `forma_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `frigorifico`
--
ALTER TABLE `frigorifico`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `lote_boi`
--
ALTER TABLE `lote_boi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `nota_fiscal`
--
ALTER TABLE `nota_fiscal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacao`
--
ALTER TABLE `notificacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notificacao_preferencia`
--
ALTER TABLE `notificacao_preferencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pagamento`
--
ALTER TABLE `pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pix_key`
--
ALTER TABLE `pix_key`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `retirada_validacao`
--
ALTER TABLE `retirada_validacao`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `transportadora`
--
ALTER TABLE `transportadora`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `compra`
--
ALTER TABLE `compra`
  ADD CONSTRAINT `compra_ibfk_1` FOREIGN KEY (`frigorifico_id`) REFERENCES `frigorifico` (`id`),
  ADD CONSTRAINT `compra_ibfk_2` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`);

--
-- Limitadores para a tabela `compra_item`
--
ALTER TABLE `compra_item`
  ADD CONSTRAINT `compra_item_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `compra_item_ibfk_2` FOREIGN KEY (`lote_id`) REFERENCES `lote_boi` (`id`);

--
-- Limitadores para a tabela `conta_bancaria`
--
ALTER TABLE `conta_bancaria`
  ADD CONSTRAINT `conta_bancaria_ibfk_1` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`);

--
-- Limitadores para a tabela `lote_boi`
--
ALTER TABLE `lote_boi`
  ADD CONSTRAINT `lote_boi_ibfk_1` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `nota_fiscal`
--
ALTER TABLE `nota_fiscal`
  ADD CONSTRAINT `nota_fiscal_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`);

--
-- Limitadores para a tabela `notificacao`
--
ALTER TABLE `notificacao`
  ADD CONSTRAINT `notificacao_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`);

--
-- Limitadores para a tabela `notificacao_preferencia`
--
ALTER TABLE `notificacao_preferencia`
  ADD CONSTRAINT `notificacao_preferencia_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`);

--
-- Limitadores para a tabela `pagamento`
--
ALTER TABLE `pagamento`
  ADD CONSTRAINT `pagamento_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`),
  ADD CONSTRAINT `pagamento_ibfk_2` FOREIGN KEY (`forma_id`) REFERENCES `forma_pagamento` (`id`);

--
-- Limitadores para a tabela `pix_key`
--
ALTER TABLE `pix_key`
  ADD CONSTRAINT `pix_key_ibfk_1` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`);

--
-- Limitadores para a tabela `retirada_validacao`
--
ALTER TABLE `retirada_validacao`
  ADD CONSTRAINT `retirada_validacao_ibfk_1` FOREIGN KEY (`transporte_id`) REFERENCES `transporte` (`id`);

--
-- Limitadores para a tabela `transporte`
--
ALTER TABLE `transporte`
  ADD CONSTRAINT `FKffbypawexarx1yskqp2idx2yg` FOREIGN KEY (`transportadora_id`) REFERENCES `usuario` (`id`),
  ADD CONSTRAINT `transporte_ibfk_1` FOREIGN KEY (`fazenda_id`) REFERENCES `fazenda` (`id`),
  ADD CONSTRAINT `transporte_ibfk_2` FOREIGN KEY (`frigorifico_id`) REFERENCES `frigorifico` (`id`),
  ADD CONSTRAINT `transporte_ibfk_3` FOREIGN KEY (`transportadora_id`) REFERENCES `transportadora` (`id`),
  ADD CONSTRAINT `transporte_ibfk_4` FOREIGN KEY (`lote_id`) REFERENCES `lote_boi` (`id`),
  ADD CONSTRAINT `transporte_ibfk_5` FOREIGN KEY (`compra_id`) REFERENCES `compra` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
