-- Migration: Add validity dates and total months to contrato
-- Data: 2026-07-01

ALTER TABLE `contrato`
  ADD COLUMN `data_inicio_vigencia` DATE NULL,
  ADD COLUMN `data_fim_vigencia` DATE NULL,
  ADD COLUMN `qtd_meses_total` INT NULL;
