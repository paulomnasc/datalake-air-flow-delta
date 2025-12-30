-- Migration: Suporte para seleção de múltiplas tabelas em uma DAG
-- Data: 2025-12-16
-- Descrição: Adiciona capacidade de uma DAG processar múltiplas tabelas de uma fonte SQL

USE `lista_revisao2`;

-- 1. Adicionar coluna is_multi_table na tabela dag_configurations
ALTER TABLE dag_configurations 
ADD COLUMN is_multi_table BOOLEAN DEFAULT FALSE 
COMMENT 'Indica se esta DAG processa múltiplas tabelas (TRUE) ou single table (FALSE)' 
AFTER id_source_type;

-- 2. Criar tabela dag_table_selections para armazenar seleções de tabelas
CREATE TABLE IF NOT EXISTS dag_table_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_dag_config INT NOT NULL COMMENT 'FK para dag_configurations(id)',
    table_name VARCHAR(255) NOT NULL COMMENT 'Nome da tabela a ser processada',
    is_selected BOOLEAN DEFAULT TRUE COMMENT 'Se a tabela está selecionada para processamento',
    row_count BIGINT NULL COMMENT 'Número estimado de linhas (cache)',
    last_sync TIMESTAMP NULL COMMENT 'Última vez que esta tabela foi processada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    UNIQUE KEY unique_dag_table (id_dag_config, table_name),
    INDEX idx_selected (id_dag_config, is_selected),
    
    CONSTRAINT fk_dag_table_selections_config 
        FOREIGN KEY (id_dag_config) 
        REFERENCES dag_configurations(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci 
COMMENT='Armazena as tabelas selecionadas para cada DAG multi-table';

-- 3. Criar tabela para cache de metadados de tabelas disponíveis
CREATE TABLE IF NOT EXISTS available_source_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    connection_id VARCHAR(128) NOT NULL COMMENT 'ID da conexão (mysql_northwind, etc)',
    database_name VARCHAR(128) NOT NULL COMMENT 'Nome do banco de dados',
    table_name VARCHAR(255) NOT NULL COMMENT 'Nome da tabela',
    table_schema VARCHAR(64) NULL COMMENT 'Schema/Database',
    row_count BIGINT NULL COMMENT 'Número estimado de linhas',
    table_size_mb DECIMAL(10,2) NULL COMMENT 'Tamanho da tabela em MB',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização do cache',
    
    UNIQUE KEY unique_source_table (connection_id, database_name, table_name),
    INDEX idx_connection (connection_id, database_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci 
COMMENT='Cache de tabelas disponíveis nas fontes de dados SQL';

-- 4. Adicionar campo para armazenar configuração de paralelismo
ALTER TABLE dag_configurations 
ADD COLUMN max_parallel_tasks INT DEFAULT 16 
COMMENT 'Número máximo de tasks paralelas para DAGs multi-table' 
AFTER is_multi_table;

-- 5. Adicionar campos para conexão SQL (substituem URI complexa)
ALTER TABLE dag_configurations 
ADD COLUMN sql_connection_id VARCHAR(128) NULL 
COMMENT 'ID da conexão configurada no Airflow (ex: mysql_northwind)' 
AFTER max_parallel_tasks;

ALTER TABLE dag_configurations 
ADD COLUMN sql_host VARCHAR(255) NULL 
COMMENT 'Host do servidor de banco de dados (ex: mysql, localhost, 192.168.1.10)' 
AFTER sql_connection_id;

ALTER TABLE dag_configurations 
ADD COLUMN sql_port INT NULL DEFAULT 3306
COMMENT 'Porta do servidor de banco de dados (ex: 3306 para MySQL, 5432 para PostgreSQL)' 
AFTER sql_host;

ALTER TABLE dag_configurations 
ADD COLUMN sql_database_name VARCHAR(128) NULL 
COMMENT 'Nome do database/schema a conectar (ex: northwind, lista_revisao2)' 
AFTER sql_port;

ALTER TABLE dag_configurations 
ADD COLUMN sql_user VARCHAR(128) NULL 
COMMENT 'Usuário do banco de dados (ex: root, admin)' 
AFTER sql_database_name;

ALTER TABLE dag_configurations 
ADD COLUMN sql_password VARCHAR(255) NULL 
COMMENT 'Senha do banco de dados (armazenada de forma segura)' 
AFTER sql_user;

-- 6. Criar índices para performance
CREATE INDEX idx_dag_multi_table ON dag_configurations(is_multi_table, is_active);

-- 7. Comentários explicativos
ALTER TABLE dag_configurations 
MODIFY COLUMN target_table_name VARCHAR(128) NULL 
COMMENT 'Nome da tabela (NULL para multi-table, específico para single-table)';

-- Inserir dados de exemplo (opcional - descomentar para testar)
-- INSERT INTO available_source_tables (connection_id, database_name, table_name, row_count, table_size_mb) VALUES
-- ('mysql_northwind', 'northwind', 'orders', 830, 0.5),
-- ('mysql_northwind', 'northwind', 'customers', 91, 0.2),
-- ('mysql_northwind', 'northwind', 'products', 77, 0.1),
-- ('mysql_northwind', 'northwind', 'order_details', 2155, 1.2),
-- ('mysql_northwind', 'northwind', 'employees', 9, 0.05);
