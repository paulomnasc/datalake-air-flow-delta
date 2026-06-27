-- Migration: Criar tabela contrato e associar a item_contrato
-- Data: 2026-06-23

CREATE TABLE IF NOT EXISTS `contrato` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `descricao` VARCHAR(255) NOT NULL,
  `empresa` VARCHAR(255) NOT NULL,
  UNIQUE (`descricao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Adiciona a coluna id_contrato na tabela item_contrato
ALTER TABLE `item_contrato` ADD COLUMN `id_contrato` INT NULL;

-- Adiciona a chave estrangeira
ALTER TABLE `item_contrato` 
  ADD CONSTRAINT `fk_item_contrato_contrato` 
  FOREIGN KEY (`id_contrato`) 
  REFERENCES `contrato` (`id`) 
  ON DELETE SET NULL 
  ON UPDATE CASCADE;
