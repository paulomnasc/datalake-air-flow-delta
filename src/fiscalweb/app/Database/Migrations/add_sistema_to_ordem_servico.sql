-- Migration: Associar ordem_servico a um unico sistema (N:1)
-- Data: 2026-08-18

ALTER TABLE `ordem_servico` ADD COLUMN `id_sistema` INT NULL AFTER `id_contrato`;

ALTER TABLE `ordem_servico` 
  ADD CONSTRAINT `fk_ordem_servico_sistema` 
  FOREIGN KEY (`id_sistema`) 
  REFERENCES `agile_sistemas` (`id`) 
  ON DELETE SET NULL 
  ON UPDATE CASCADE;
