USE `fiscal`;

-- 1. Criar tabela lista_verificacao
CREATE TABLE IF NOT EXISTS `lista_verificacao` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `descricao` VARCHAR(255) NOT NULL,
  UNIQUE (`descricao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. Alteração na tabela documento_recebimento para incluir id_demanda
-- NOTA: Este script é executado via helper PHP que verifica a existência da coluna
-- para evitar erros de execução repetida.
-- ALTER TABLE `documento_recebimento` ADD COLUMN `id_demanda` INT NOT NULL;
-- ALTER TABLE `documento_recebimento` ADD CONSTRAINT `fk_documento_recebimento_demanda` FOREIGN KEY (`id_demanda`) REFERENCES `agile_demandas` (`id`);

-- 3. Criar tabela associativa item_doc_rec_lista_ver
CREATE TABLE IF NOT EXISTS `item_doc_rec_lista_ver` (
  `id_lista_verificacao` INT NOT NULL,
  `id_item_doc_origem` INT NOT NULL,
  `conforme` TINYINT(1) NOT NULL,
  PRIMARY KEY (`id_lista_verificacao`, `id_item_doc_origem`),
  CONSTRAINT `fk_id_lista_verificacao` FOREIGN KEY (`id_lista_verificacao`) REFERENCES `lista_verificacao` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_id_item_doc_origem` FOREIGN KEY (`id_item_doc_origem`) REFERENCES `item_documento_recebimento` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
