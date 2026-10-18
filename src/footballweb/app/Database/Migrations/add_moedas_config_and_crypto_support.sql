-- Migration: Suporte a Multi-Moedas e Criptomoedas (Stake / Betano)
-- Criação da tabela de configurações de moedas e adaptação das tabelas de apostas e metas

CREATE TABLE IF NOT EXISTS `moedas_config` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(10) NOT NULL UNIQUE,
  `nome` VARCHAR(50) NOT NULL,
  `simbolo` VARCHAR(10) NOT NULL,
  `tipo` ENUM('FIAT', 'CRYPTO') NOT NULL DEFAULT 'CRYPTO',
  `casas_decimais` INT NOT NULL DEFAULT 2,
  `saque_minimo` DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000,
  `taxa_saque_fixa` DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000,
  `taxa_rede_estimada` DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000,
  `taxa_spot_percentual` DECIMAL(5, 2) NOT NULL DEFAULT 0.10,
  `cotacao_brl` DECIMAL(18, 4) NOT NULL DEFAULT 1.0000,
  `cotacao_atualizada_em` DATETIME NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_moeda_codigo` (`codigo`),
  INDEX `idx_moeda_default` (`is_default`),
  INDEX `idx_moeda_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carga inicial de moedas operacionais (Baseada na tabela da Stake e Binance Brasil)
INSERT INTO `moedas_config` 
(`codigo`, `nome`, `simbolo`, `tipo`, `casas_decimais`, `saque_minimo`, `taxa_saque_fixa`, `taxa_rede_estimada`, `taxa_spot_percentual`, `cotacao_brl`, `cotacao_atualizada_em`, `is_default`, `ativo`)
VALUES 
('BRL', 'Real Brasileiro', 'R$', 'FIAT', 2, 0.00000000, 0.00000000, 0.00000000, 0.00, 1.0000, NOW(), 1, 1),
('LTC', 'Litecoin', 'Ł', 'CRYPTO', 8, 0.01857329, 0.00013800, 0.00023000, 0.10, 430.0000, NOW(), 0, 1),
('BTC', 'Bitcoin', '₿', 'CRYPTO', 8, 0.00002504, 0.00001571, 0.00001000, 0.10, 385000.0000, NOW(), 0, 1),
('ETH', 'Ethereum', 'Ξ', 'CRYPTO', 8, 0.00100000, 0.00067447, 0.00030000, 0.10, 15500.0000, NOW(), 0, 1),
('USDT', 'Tether USD', 'USDT', 'CRYPTO', 2, 2.50000000, 1.00000000, 0.00000000, 0.10, 5.6000, NOW(), 0, 1)
ON DUPLICATE KEY UPDATE 
  `saque_minimo` = VALUES(`saque_minimo`),
  `taxa_saque_fixa` = VALUES(`taxa_saque_fixa`),
  `taxa_rede_estimada` = VALUES(`taxa_rede_estimada`),
  `taxa_spot_percentual` = VALUES(`taxa_spot_percentual`),
  `cotacao_brl` = VALUES(`cotacao_brl`);

-- Atualização das colunas na tabela apostas
SET @dbname = DATABASE();
SET @tablename = "apostas";

-- 1. Coluna moeda_codigo
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = "moeda_codigo") > 0,
  "SELECT 1",
  "ALTER TABLE `apostas` ADD COLUMN `moeda_codigo` VARCHAR(10) NOT NULL DEFAULT 'BRL' AFTER `usuario_id`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Coluna cotacao_brl_snapshot
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = "cotacao_brl_snapshot") > 0,
  "SELECT 1",
  "ALTER TABLE `apostas` ADD COLUMN `cotacao_brl_snapshot` DECIMAL(18, 4) NULL DEFAULT 1.0000 AFTER `ganhos_potenciais`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Coluna taxa_movimentacao_estimada_brl
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = "taxa_movimentacao_estimada_brl") > 0,
  "SELECT 1",
  "ALTER TABLE `apostas` ADD COLUMN `taxa_movimentacao_estimada_brl` DECIMAL(10, 2) NULL DEFAULT 0.00 AFTER `cotacao_brl_snapshot`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Expansão de precisão numérica para Criptomoedas em apostas
ALTER TABLE `apostas` 
  MODIFY COLUMN `valor_aposta` DECIMAL(18, 8) NOT NULL DEFAULT 10.00000000,
  MODIFY COLUMN `ganhos_potenciais` DECIMAL(18, 8) NOT NULL,
  MODIFY COLUMN `cash_out` DECIMAL(18, 8) NULL DEFAULT NULL;

-- 5. Expansão de precisão numérica e moeda em metas_diarias_config
SET @tablename_meta = "metas_diarias_config";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename_meta AND COLUMN_NAME = "moeda_codigo") > 0,
  "SELECT 1",
  "ALTER TABLE `metas_diarias_config` ADD COLUMN `moeda_codigo` VARCHAR(10) NOT NULL DEFAULT 'BRL' AFTER `usuario_id`;"
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `metas_diarias_config` 
  MODIFY COLUMN `stake_padrao` DECIMAL(18, 8) NOT NULL DEFAULT 10.00000000,
  MODIFY COLUMN `lucro_alvo` DECIMAL(18, 8) NOT NULL DEFAULT 7.50000000,
  MODIFY COLUMN `stop_loss_diario` DECIMAL(18, 8) NOT NULL DEFAULT -30.00000000;
