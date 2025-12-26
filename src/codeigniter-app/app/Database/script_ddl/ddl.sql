CREATE DATABASE IF NOT EXISTS `lista_revisao2` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `lista_revisao2`;

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
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela associativa usuario_perfil
CREATE TABLE IF NOT EXISTS `usuario_perfil` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` tinyint unsigned NOT NULL,
  `id_perfil` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_perfil_unique` (`id_usuario`, `id_perfil`),
  KEY `fk_usuario_perfil_usuario_idx` (`id_usuario`),
  KEY `fk_usuario_perfil_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_perfil_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela funcionalidade
CREATE TABLE IF NOT EXISTS `funcionalidade` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL UNIQUE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Criação da tabela associativa perfil_funcionalidade
CREATE TABLE IF NOT EXISTS `perfil_funcionalidade` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_perfil` int NOT NULL,
  `id_funcionalidade` int NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perfil_funcionalidade_unique` (`id_perfil`, `id_funcionalidade`),
  KEY `fk_perfil_funcionalidade_perfil_idx` (`id_perfil`),
  KEY `fk_perfil_funcionalidade_funcionalidade_idx` (`id_funcionalidade`),
  CONSTRAINT `fk_perfil_funcionalidade_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perfil_funcionalidade_funcionalidade` FOREIGN KEY (`id_funcionalidade`) REFERENCES `funcionalidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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

CREATE TABLE source_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(100) NOT NULL UNIQUE COMMENT 'Descrição do tipo de fonte (Ex: MySQL, CSV, API REST)'
);

-- Dados de Exemplo (para começar a testar)
INSERT INTO source_types (description) VALUES
('CSV (MinIO/S3)'),
('JSON (MinIO/S3)'),
('MySQL'),
('PostgreSQL'),
('API REST');

-- DROP TABLE IF EXISTS dag_configurations; -- Use esta linha se precisar recriar do zero

CREATE TABLE dag_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- 1. Chave Estrangeira (FK) para a tabela PASTA
    id_pasta INT UNSIGNED NOT NULL COMMENT 'ID da pasta associada, FK para a tabela pasta(id)',
    
    -- 2. Metadata da DAG
    dag_id VARCHAR(128) NOT NULL UNIQUE COMMENT 'O nome único da DAG no Airflow',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Indica se a DAG deve ser gerada',
    owner VARCHAR(64) DEFAULT 'webapp_user' COMMENT 'Proprietário da DAG no Airflow',
    schedule_interval VARCHAR(64) DEFAULT '0 0 * * *' COMMENT 'Agendamento no formato cron',
    description TEXT COMMENT 'Descrição da DAG',
    
    -- 3. Parâmetros da Tarefa
    -- 🛑 NOVO CAMPO FK para source_types 🛑
    id_source_type INT UNSIGNED NOT NULL COMMENT 'ID do tipo de fonte de dados (FK para source_types)',
    source_filename VARCHAR(512) COMMENT 'Caminho do arquivo ou URI de conexão',
    target_table_name VARCHAR(128) NOT NULL COMMENT 'Nome da tabela/destino final',
    
    -- 4. Parâmetros de Processamento
    python_module_path VARCHAR(255) COMMENT 'Caminho do módulo Python a ser chamado',
    transform_args JSON COMMENT 'Parâmetros extras para a função Python (JSON)',

    -- 🛑 CAMPOS SSH TUNNELING 🛑
    ssh_host VARCHAR(255) NULL COMMENT 'FQDN ou IP do Jump Server',
    ssh_port INT DEFAULT 22 NULL COMMENT 'Porta SSH para conexão, padrão 22',
    ssh_user VARCHAR(100) NULL COMMENT 'Usuário SSH para autenticação',
    ssh_key_path VARCHAR(255) NULL COMMENT 'Caminho da chave privada SSH no servidor Airflow',
    ssh_local_port INT DEFAULT 13306 NULL COMMENT 'Porta local que será usada para o túnel',

    -- 5. Controle e Auditoria
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices para performance
    INDEX idx_dag_active (is_active),
    INDEX idx_owner (owner),
    
    -- 6. Declaração das Chaves Estrangeiras

    -- FK para PASTA
    CONSTRAINT fk_dagconfig_pasta
        FOREIGN KEY (id_pasta) 
        REFERENCES pasta(id)
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
        
    -- 🛑 FK para SOURCE_TYPES 🛑
    CONSTRAINT fk_source_type
        FOREIGN KEY (id_source_type) 
        REFERENCES source_types(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Adicione a coluna start_date como DATE, permitindo NULL
ALTER TABLE dag_configurations
ADD COLUMN start_date DATE NULL AFTER transform_args; 


