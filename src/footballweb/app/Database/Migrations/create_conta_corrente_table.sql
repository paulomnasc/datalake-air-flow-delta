-- Migration for Conta Corrente (Current Account Ledger)
CREATE TABLE IF NOT EXISTS `conta_corrente` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `aposta_id` INT UNSIGNED NULL DEFAULT NULL,
  `tipo` ENUM('CREDITO_ADICIONADO', 'DEBITO_APOSTA', 'CREDITO_RETORNO_APOSTA', 'ESTORNO_APOSTA') NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `saldo_anterior` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `saldo_posterior` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cc_usuario` (`usuario_id`),
  INDEX `idx_cc_aposta` (`aposta_id`),
  INDEX `idx_cc_tipo` (`tipo`),
  INDEX `idx_cc_criado` (`criado_em`),
  CONSTRAINT `fk_cc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona a coluna saldo_conta_corrente em usuario caso nao exista
SET @dbname = DATABASE();
SET @tablename = "usuario";
SET @columnname = "saldo_conta_corrente";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `usuario` ADD COLUMN `saldo_conta_corrente` DECIMAL(10,2) NOT NULL DEFAULT 0.00;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
