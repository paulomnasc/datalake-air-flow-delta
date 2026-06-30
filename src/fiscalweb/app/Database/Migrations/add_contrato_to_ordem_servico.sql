-- Migration: Associar ordem_servico a um unico contrato (N:1)
-- Data: 2026-06-30

ALTER TABLE `ordem_servico` ADD COLUMN `id_contrato` INT NULL;

ALTER TABLE `ordem_servico` 
  ADD CONSTRAINT `fk_ordem_servico_contrato` 
  FOREIGN KEY (`id_contrato`) 
  REFERENCES `contrato` (`id`) 
  ON DELETE SET NULL 
  ON UPDATE CASCADE;
