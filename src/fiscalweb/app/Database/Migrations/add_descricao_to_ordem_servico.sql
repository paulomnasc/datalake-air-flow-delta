-- Migration: Adicionar campo descricao na tabela ordem_servico
-- Data: 2026-10-08

ALTER TABLE `ordem_servico` ADD COLUMN `descricao` VARCHAR(255) NOT NULL DEFAULT '' AFTER `nup_sei`;
