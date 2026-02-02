-- Migration: Criar tabelas de logs de atividades e configurações de funções Python
-- Data: 2026-01-20 a 2026-01-23
-- Descrição: Adiciona suporte para rastreamento de atividades de usuários e gerenciamento de funções Python por usuário

-- Criar tabela de logs de atividades de usuários
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

-- Criar tabela principal de funções disponíveis (conforme DDL em produção)
CREATE TABLE IF NOT EXISTS `funcion_configuration` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo_python` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `grupo` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ex: Recomendado, Ingestão, Camadas Individuais, Legado',
  `ordem` int NOT NULL DEFAULT '0' COMMENT 'Ordem de exibição no select',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `is_custom` tinyint(1) NOT NULL DEFAULT '0',
  `owner_user_id` tinyint unsigned DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modulo_python` (`modulo_python`),
  UNIQUE KEY `uk_owner_modulo` (`owner_user_id`, `modulo_python`),
  UNIQUE KEY `uk_owner_nome` (`owner_user_id`, `nome`),
  KEY `idx_grupo` (`grupo`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_is_custom` (`is_custom`),
  KEY `idx_owner` (`owner_user_id`),
  CONSTRAINT `fk_funcion_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de associação usuário-função
CREATE TABLE IF NOT EXISTS `user_funcion_configuration` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` TINYINT UNSIGNED NOT NULL,
  `funcion_configuration_id` INT UNSIGNED NOT NULL,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_funcion` (`usuario_id`, `funcion_configuration_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_funcion_id` (`funcion_configuration_id`),
  CONSTRAINT `fk_user_funcion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_funcion_funcion` FOREIGN KEY (`funcion_configuration_id`) REFERENCES `funcion_configuration` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir as funções padrão que já existem no código
INSERT INTO `funcion_configuration` (`nome`, `modulo_python`, `descricao`, `grupo`, `ordem`, `ativo`) VALUES
('Pipeline Completo (Bronze + Silver + Gold)', 'lib.medallion_pipeline.raw_to_medallion', 'Pipeline Completo (Bronze + Silver + Gold) - RAW já existe', 'Recomendado', 1, 1),
('MySQL → Medallion', 'lib.mysql_ingestion.mysql_to_medallion', 'MySQL → Medallion (Ingestão + Bronze + Silver + Gold)', 'Ingestão de Fontes SQL', 2, 1),
('MySQL → Raw', 'lib.mysql_ingestion.ingest_mysql_to_raw', 'MySQL → Raw (Apenas ingestão para CSV)', 'Ingestão de Fontes SQL', 3, 1),
('Bronze (Raw → Bronze CSV)', 'lib.bronze_layer.raw_to_bronze', 'Bronze (Raw → Bronze CSV)', 'Camadas Individuais', 4, 1),
('Silver (Bronze → Silver Parquet)', 'lib.silver_layer.bronze_to_silver', 'Silver (Bronze → Silver Parquet)', 'Camadas Individuais', 5, 1),
('Gold (Silver → Gold Parquet Otimizado)', 'lib.gold_layer.silver_to_gold', 'Gold (Silver → Gold Parquet Otimizado)', 'Camadas Individuais', 6, 1),
('Função Legada', 'lib.minio_tasks.transform_data_with_pandas', 'Função Legada (não recomendado)', 'Legado', 7, 0);
