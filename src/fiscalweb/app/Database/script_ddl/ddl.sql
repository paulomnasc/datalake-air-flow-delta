CREATE DATABASE IF NOT EXISTS `fiscal` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `fiscal`;

-- Criação da tabela perfil
CREATE TABLE IF NOT EXISTS `perfil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Definição da tabela usuario
CREATE TABLE IF NOT EXISTS `usuario` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela associativa usuario_perfil
CREATE TABLE IF NOT EXISTS `usuario_perfil` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` tinyint unsigned NOT NULL,
  `id_perfil` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_perfil_unique` (`id_usuario`, `id_perfil`),
  KEY `fk_usuario_perfil_usuario_idx` (`id_usuario`),
  KEY `fk_usuario_perfil_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_perfil_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela tipo_documento (Reordenado para evitar erros de chave estrangeira)
CREATE TABLE IF NOT EXISTS `tipo_documento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela status
CREATE TABLE IF NOT EXISTS `status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `status_unique` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela status_recebimento
CREATE TABLE IF NOT EXISTS `status_recebimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela ordem_servico
CREATE TABLE IF NOT EXISTS `ordem_servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `horas_alocadas` float NOT NULL,
  `nup_sei` varchar(255) NOT NULL,
  `data_emissao` datetime NOT NULL,
  `data_aceite` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela item_os
CREATE TABLE IF NOT EXISTS `item_os` (
  `id` int NOT NULL AUTO_INCREMENT,
  `quantidade_horas` float NOT NULL,
  `profissional_alocado` varchar(255) NOT NULL DEFAULT 'Nenhum',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela servico
CREATE TABLE IF NOT EXISTS `servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_item_os` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `remuneracao` float NOT NULL,
  `base_horas_mes` float NOT NULL,
  `base_horas_complexidade` float NOT NULL,
  `sla_dias` int NOT NULL,
  `estim_max_ano` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_servico_item_os_idx` (`id_item_os`),
  CONSTRAINT `fk_servico_item_os` FOREIGN KEY (`id_item_os`) REFERENCES `item_os` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela atividade_macro
CREATE TABLE IF NOT EXISTS `atividade_macro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_servico` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_atividade_macro_servico_idx` (`id_servico`),
  CONSTRAINT `fk_atividade_macro_servico` FOREIGN KEY (`id_servico`) REFERENCES `servico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela area_atuacao
CREATE TABLE IF NOT EXISTS `area_atuacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_atividade_macro` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_area_atuacao_atividade_macro_idx` (`id_atividade_macro`),
  CONSTRAINT `fk_area_atuacao_atividade_macro` FOREIGN KEY (`id_atividade_macro`) REFERENCES `atividade_macro` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela catalogo_servicos
CREATE TABLE IF NOT EXISTS `catalogo_servicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_area_atuacao` int DEFAULT NULL,
  `cod_item_unificado` varchar(255) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_catalogo_servicos_area_atuacao_idx` (`id_area_atuacao`),
  CONSTRAINT `fk_catalogo_servicos_area_atuacao` FOREIGN KEY (`id_area_atuacao`) REFERENCES `area_atuacao` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela item_contrato
CREATE TABLE IF NOT EXISTS `item_contrato` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_catalogo_servicos` int DEFAULT NULL,
  `gestor_titular` varchar(255) NOT NULL,
  `gestor_substituto` varchar(255) NOT NULL,
  `numero_contrato` varchar(255) NOT NULL,
  `objeto` varchar(255) NOT NULL,
  `total_horas_contratadas` float NOT NULL,
  `saldo_horas` float NOT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_item_contrato_catalogo_servicos_idx` (`id_catalogo_servicos`),
  CONSTRAINT `fk_item_contrato_catalogo_servicos` FOREIGN KEY (`id_catalogo_servicos`) REFERENCES `catalogo_servicos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela documento_recebimento
CREATE TABLE IF NOT EXISTS `documento_recebimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_os` int DEFAULT NULL,
  `data_assinatura` datetime DEFAULT NULL,
  `nup_sei` varchar(255) NOT NULL,
  `id_tipo_documento` int DEFAULT NULL,
  `id_usuario_fiscal_tecnico` int unsigned DEFAULT NULL,
  `id_usuario_fiscal_requisitante` int unsigned DEFAULT NULL,
  `id_usuario_gestor` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_documento_recebimento_os_idx` (`id_os`),
  KEY `fk_documento_recebimento_tipo_documento_idx` (`id_tipo_documento`),
  KEY `fk_documento_recebimento_fiscal_tecnico_idx` (`id_usuario_fiscal_tecnico`),
  KEY `fk_documento_recebimento_fiscal_req_idx` (`id_usuario_fiscal_requisitante`),
  KEY `fk_documento_recebimento_gestor_idx` (`id_usuario_gestor`),
  CONSTRAINT `fk_documento_recebimento_os` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_documento_recebimento_tipo_documento` FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipo_documento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_documento_recebimento_fiscal_tecnico` FOREIGN KEY (`id_usuario_fiscal_tecnico`) REFERENCES `usuario` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_documento_recebimento_fiscal_req` FOREIGN KEY (`id_usuario_fiscal_requisitante`) REFERENCES `usuario` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_documento_recebimento_gestor` FOREIGN KEY (`id_usuario_gestor`) REFERENCES `usuario` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela avaliacao_qualidade_sla
CREATE TABLE IF NOT EXISTS `avaliacao_qualidade_sla` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_documento_recebimento` int DEFAULT NULL,
  `nota_ins1_pontualidade` float NOT NULL,
  `nota_ins2_qualidade` float NOT NULL,
  `percentual_glosa` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_avaliacao_sla_documento_recebimento_idx` (`id_documento_recebimento`),
  CONSTRAINT `fk_avaliacao_sla_documento_recebimento` FOREIGN KEY (`id_documento_recebimento`) REFERENCES `documento_recebimento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Tabelas de associação com chave composta
CREATE TABLE IF NOT EXISTS `os_item_contrato` (
  `id_item_contrato` int NOT NULL,
  `id_os` int NOT NULL,
  PRIMARY KEY (`id_item_contrato`, `id_os`),
  KEY `fk_os_item_contrato_os_idx` (`id_os`),
  CONSTRAINT `fk_os_item_contrato_item_contrato` FOREIGN KEY (`id_item_contrato`) REFERENCES `item_contrato` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_os_item_contrato_os` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `os_item_os` (
  `id_os` int NOT NULL,
  `id_item_os` int NOT NULL,
  PRIMARY KEY (`id_os`, `id_item_os`),
  KEY `fk_os_item_os_item_os_idx` (`id_item_os`),
  CONSTRAINT `fk_os_item_os_os` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_os_item_os_item_os` FOREIGN KEY (`id_item_os`) REFERENCES `item_os` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `os_status_recebimento` (
  `id_os` int NOT NULL,
  `id_status_recebimento` int NOT NULL,
  PRIMARY KEY (`id_os`, `id_status_recebimento`),
  KEY `fk_os_status_recebimento_status_idx` (`id_status_recebimento`),
  CONSTRAINT `fk_os_status_recebimento_os` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_os_status_recebimento_status` FOREIGN KEY (`id_status_recebimento`) REFERENCES `status_recebimento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `usuario_os` (
  `id_os` int NOT NULL,
  `id_usuario` int unsigned NOT NULL,
  PRIMARY KEY (`id_os`, `id_usuario`),
  KEY `fk_usuario_os_usuario_idx` (`id_usuario`),
  CONSTRAINT `fk_usuario_os_os` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_os_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `usuario_recebimento` (
  `id_recebimento` int NOT NULL,
  `id_usuario` int unsigned NOT NULL,
  PRIMARY KEY (`id_recebimento`, `id_usuario`),
  KEY `fk_usuario_recebimento_usuario_idx` (`id_usuario`),
  CONSTRAINT `fk_usuario_recebimento_recebimento` FOREIGN KEY (`id_recebimento`) REFERENCES `documento_recebimento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_recebimento_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
