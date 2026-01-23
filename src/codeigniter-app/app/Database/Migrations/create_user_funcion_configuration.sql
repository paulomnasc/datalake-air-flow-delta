-- Migration: Criar tabela de configuração de funções Python por usuário
-- Data: 2026-01-23
-- Descrição: Tabela associativa entre usuários e funções Python de transformação disponíveis
-- Esta tabela permite que cada usuário tenha uma lista personalizada de funções Python disponíveis

-- Criar tabela principal de funções disponíveis
CREATE TABLE IF NOT EXISTS `funcion_configuration` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(128) NOT NULL UNIQUE,
  `modulo_python` VARCHAR(255) NOT NULL UNIQUE,
  `descricao` TEXT NULL,
  `grupo` VARCHAR(64) NULL COMMENT 'Ex: Recomendado, Ingestão, Camadas Individuais, Legado',
  `ordem` INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição no select',
  `ativo` BOOLEAN NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modulo_python` (`modulo_python`),
  KEY `idx_grupo` (`grupo`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
