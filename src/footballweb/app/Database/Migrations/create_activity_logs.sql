-- Migration: Criar tabela de logs de atividades de usuários
-- Data: 2026-01-20
-- Descrição: Registra rotas acessadas por usuários autenticados e eventos de login

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `method` VARCHAR(10) NOT NULL,
  `uri` VARCHAR(512) NOT NULL,
  `controller` VARCHAR(255) NULL,
  `action` VARCHAR(128) NULL,
  `route_alias` VARCHAR(128) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `session_id` VARCHAR(128) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_route_alias` (`route_alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
