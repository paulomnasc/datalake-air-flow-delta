CREATE DATABASE IF NOT EXISTS `smart_tables` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `smart_tables`;

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
  `id_perfil` int NOT NULL,
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`),
  KEY `fk_usuario_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Criação da tabela pasta
CREATE TABLE IF NOT EXISTS `pasta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `id_usuario` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pasta_usuario_FK` (`id_usuario`),
  CONSTRAINT `pasta_usuario_FK` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='';

-- Criação da tabela quadro
CREATE TABLE IF NOT EXISTS `quadro` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `id_pasta` int unsigned NOT NULL,
  `arquivo` blob,
  `nome_arquivo` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quadro_pasta_FK` (`id_pasta`),
  CONSTRAINT `quadro_pasta_FK` FOREIGN KEY (`id_pasta`) REFERENCES `pasta` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Definição da tabela email_tokens
CREATE TABLE IF NOT EXISTS `email_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(32) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE 'quadro' MODIFY COLUMN 'arquivo' varchar(255) NULL;
ALTER TABLE 'quadro' ADD 'conteudo_arquivo' BLOB NULL;
