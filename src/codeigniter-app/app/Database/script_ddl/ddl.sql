CREATE DATABASE IF NOT EXISTS `smart_tables` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `smart_tables`;

-- Criação da tabela perfil
CREATE TABLE IF NOT EXISTS `perfil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Definição da tabela usuario
CREATE TABLE IF NOT EXISTS `usuario` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `id_perfil` int NOT NULL,
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`),
  KEY `fk_usuario_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Criação da tabela pasta
CREATE TABLE IF NOT EXISTS `pasta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `id_usuario` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pasta_usuario_FK` (`id_usuario`),
  CONSTRAINT `pasta_usuario_FK` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='';


-- Definição da tabela email_tokens
CREATE TABLE IF NOT EXISTS `email_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(32) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- DDL (Data Definition Language) para a tabela de configurações de DAGs
-- No MySQL: CREATE DATABASE IF NOT EXISTS dag_factory_db; USE dag_factory_db;

CREATE TABLE dag_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- 1. Chave Estrangeira (FK) para a tabela PASTA
    pasta_id INT UNSIGNED NOT NULL COMMENT 'ID da pasta associada, FK para a tabela pasta(id)',
    
    -- 2. Metadata da DAG
    dag_id VARCHAR(128) NOT NULL UNIQUE COMMENT 'O nome único da DAG no Airflow (ex: ingestao_clientes_vendas)',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Indica se a DAG deve ser gerada (True) ou ignorada (False)',
    owner VARCHAR(64) DEFAULT 'webapp_user' COMMENT 'Proprietário da DAG no Airflow',
    schedule_interval VARCHAR(64) DEFAULT '0 0 * * *' COMMENT 'Agendamento no formato cron (ex: 0 4 * * *)',
    description TEXT COMMENT 'Descrição da DAG para a UI do Airflow',
    
    -- 3. Parâmetros da Tarefa
    source_type VARCHAR(50) NOT NULL COMMENT 'Tipo de fonte de dados (ex: csv, json, database)',
    source_filename VARCHAR(512) COMMENT 'Caminho do arquivo (MinIO) ou URI de conexão (DB)',
    target_table_name VARCHAR(128) NOT NULL COMMENT 'Nome da tabela/destino final na camada Trusted/Refined',
    
    -- 4. Parâmetros de Processamento
    python_module_path VARCHAR(255) COMMENT 'Caminho do módulo Python a ser chamado (ex: lib.minio_tasks.transform_data)',
    transform_args JSON COMMENT 'Parâmetros extras para a função Python (JSON: ex: {"columns_to_drop": ["col1", "col2"]})',

    -- 5. Controle e Auditoria
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_dag_active (is_active),
    INDEX idx_owner (owner),
    
    -- Declaração da Chave Estrangeira com CONSTRAINT explícita
    CONSTRAINT fk_dagconfig_pasta
        FOREIGN KEY (pasta_id) 
        REFERENCES pasta(id)
        -- Regras de Integridade Referencial:
        -- ON DELETE RESTRICT: Impede a exclusão de uma pasta se ela ainda tiver DAGs associadas.
        -- ON UPDATE CASCADE: Atualiza o pasta_id nesta tabela se o id for alterado na tabela pasta.
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Supondo que sua tabela de configurações se chame 'config_data'
-- Altere o nome da tabela conforme o seu projeto

ALTER TABLE config_data
ADD COLUMN ssh_host VARCHAR(255) NULL COMMENT 'FQDN ou IP do Jump Server',
ADD COLUMN ssh_port INT DEFAULT 22 NULL COMMENT 'Porta SSH para conexão, padrão 22',
ADD COLUMN ssh_user VARCHAR(100) NULL COMMENT 'Usuário SSH para autenticação',
ADD COLUMN ssh_key_path VARCHAR(255) NULL COMMENT 'Caminho local da chave privada SSH (ex: /path/to/id_rsa)',
ADD COLUMN ssh_local_port INT DEFAULT 13306 NULL COMMENT 'Porta local que será usada para o túnel (ex: 13306)';

