-- Migration: Adicionar campos is_custom e owner_user_id à tabela funcion_configuration
-- Data: 2026-02-02
-- Descrição: Adiciona suporte para funções customizadas por usuário (sincronizado com produção)

ALTER TABLE `funcion_configuration` ADD COLUMN IF NOT EXISTS `is_custom` BOOLEAN NOT NULL DEFAULT 0 AFTER `ativo`;
ALTER TABLE `funcion_configuration` ADD COLUMN IF NOT EXISTS `owner_user_id` TINYINT UNSIGNED NULL AFTER `is_custom`;

-- Criar índices para melhor performance
ALTER TABLE `funcion_configuration` ADD KEY `idx_is_custom` (`is_custom`);
ALTER TABLE `funcion_configuration` ADD KEY `idx_owner` (`owner_user_id`);

-- Adicionar UNIQUEs (como em produção)
ALTER TABLE `funcion_configuration` ADD UNIQUE KEY `uk_owner_modulo` (`owner_user_id`, `modulo_python`);
ALTER TABLE `funcion_configuration` ADD UNIQUE KEY `uk_owner_nome` (`owner_user_id`, `nome`);

-- Adicionar constraint com ON DELETE CASCADE (conforme produção)
-- Nota: Se a constraint já existe, remover primeiro antes de re-adicionar
ALTER TABLE `funcion_configuration` DROP FOREIGN KEY IF EXISTS `fk_funcion_owner`;
ALTER TABLE `funcion_configuration` ADD CONSTRAINT `fk_funcion_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE;
