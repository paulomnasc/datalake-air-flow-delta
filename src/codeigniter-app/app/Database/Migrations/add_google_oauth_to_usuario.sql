-- Migration: Adicionar campos de autenticação OAuth do Google na tabela usuario
-- Data: 2026-01-21
-- Descrição: Adiciona campos para armazenar ID do Google, token e informações de autenticação social

ALTER TABLE `usuario`
ADD COLUMN `google_id` VARCHAR(255) NULL UNIQUE COMMENT 'ID único do usuário no Google',
ADD COLUMN `google_token` LONGTEXT NULL COMMENT 'Token de acesso do Google (criptografado)',
ADD COLUMN `google_refresh_token` LONGTEXT NULL COMMENT 'Refresh token do Google (criptografado)',
ADD COLUMN `auth_provider` VARCHAR(50) NULL COMMENT 'Provedor de autenticação: google, email, etc',
ADD COLUMN `auth_updated_at` TIMESTAMP NULL COMMENT 'Data da última atualização de autenticação',
ADD INDEX `idx_google_id` (`google_id`),
ADD INDEX `idx_auth_provider` (`auth_provider`);
