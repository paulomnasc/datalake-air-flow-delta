-- Migration for Apostas Table
CREATE TABLE IF NOT EXISTS `apostas` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `fixture_id` INT NULL DEFAULT NULL,
  `time_casa` VARCHAR(100) NOT NULL,
  `time_fora` VARCHAR(100) NOT NULL,
  `mercado` VARCHAR(100) NOT NULL DEFAULT 'Total de Cartões',
  `palpite` VARCHAR(100) NOT NULL,
  `odd` DECIMAL(5,2) NOT NULL,
  `odd_justa` DECIMAL(5,2) NULL DEFAULT NULL,
  `probabilidade_poisson` DECIMAL(5,2) NULL DEFAULT NULL,
  `ev_percentual` DECIMAL(5,2) NULL DEFAULT NULL,
  `status_gatekeeper` VARCHAR(50) NOT NULL DEFAULT 'APROVADO',
  `data_hora_jogo` DATETIME NULL DEFAULT NULL,
  `valor_aposta` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `ganhos_potenciais` DECIMAL(10,2) NOT NULL,
  `cash_out` DECIMAL(10,2) NULL DEFAULT NULL,
  `tipo` VARCHAR(50) NOT NULL DEFAULT 'Simples',
  `status` ENUM('Pendente', 'Ganha', 'Perdida', 'Cashout') NOT NULL DEFAULT 'Pendente',
  `confirmada` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_usuario_id` (`usuario_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_status_gatekeeper` (`status_gatekeeper`),
  CONSTRAINT `fk_apostas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona a coluna confirmada em apostas caso nao exista
SET @dbname = DATABASE();
SET @tablename = "apostas";
SET @columnname = "confirmada";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `apostas` ADD COLUMN `confirmada` TINYINT(1) NOT NULL DEFAULT 1;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

