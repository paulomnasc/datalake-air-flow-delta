-- Migration: Cria tabelas para gerenciar URLs customizadas do Groq Crawler
-- Data: 2026-07-08
-- Descricao: Estrutura para categorias (nicho) e relacionamento 1:N com as URLs adicionais para o crawler

USE `lista_revisao2`;

-- Criar tabela crawler_categorias se não existir
CREATE TABLE IF NOT EXISTS `crawler_categorias` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Nome do nicho (ex: varejo farmácia)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criar tabela crawler_urls se não existir
CREATE TABLE IF NOT EXISTS `crawler_urls` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `categoria_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(500) NOT NULL COMMENT 'URL fornecida para enriquecimento da pesquisa',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_crawler_urls_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `crawler_categorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Sementes iniciais se não existirem
INSERT IGNORE INTO `crawler_categorias` (`id`, `nome`) VALUES (1, 'varejo farmácia');
INSERT IGNORE INTO `crawler_urls` (`categoria_id`, `url`) VALUES 
(1, 'https://www.drogaraia.com.br'),
(1, 'https://www.drogasil.com.br');
