-- Migration: Ampliação de precisão decimal e inclusão de moeda_codigo na conta corrente
-- Data: 19/10/2026
-- Objetivo: Permitir que o extrato e controle de conta corrente operem nativamente com moedas cripto (ex: LTC com 8 decimais) ou fiduciárias (BRL).

ALTER TABLE `conta_corrente` 
  MODIFY COLUMN `valor` DECIMAL(18, 8) NOT NULL,
  MODIFY COLUMN `saldo_anterior` DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000,
  MODIFY COLUMN `saldo_posterior` DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000;

-- Adiciona a coluna moeda_codigo se não existir
SET @dbname = DATABASE();
SET @tablename = "conta_corrente";
SET @columnname = "moeda_codigo";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `conta_corrente` ADD COLUMN `moeda_codigo` VARCHAR(10) NOT NULL DEFAULT 'BRL' AFTER `tipo`, ADD INDEX `idx_cc_moeda` (`moeda_codigo`);"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Ampliação de precisão do saldo no usuário
ALTER TABLE `usuario` 
  MODIFY COLUMN `saldo_conta_corrente` DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000;
