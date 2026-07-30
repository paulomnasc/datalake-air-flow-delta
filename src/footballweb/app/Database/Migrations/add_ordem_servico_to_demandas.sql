-- Migration: Associar ordem_servico a agile_demandas (1:N)
-- Data: 2026-06-23

ALTER TABLE `agile_demandas` ADD COLUMN `id_ordem_servico` INT NOT NULL;

ALTER TABLE `agile_demandas` 
  ADD CONSTRAINT `fk_agile_demandas_ordem_servico` 
  FOREIGN KEY (`id_ordem_servico`) 
  REFERENCES `ordem_servico` (`id`) 
  ON DELETE CASCADE 
  ON UPDATE CASCADE;
