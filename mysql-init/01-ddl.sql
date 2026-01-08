-- Database is created by docker-entrypoint with MYSQL_DATABASE=lista_revisao2_test
USE `lista_revisao2_test`;


-- lista_revisao2.available_source_tables definição

CREATE TABLE `available_source_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `connection_id` varchar(128) NOT NULL COMMENT 'ID da conexão (mysql_northwind, etc)',
  `database_name` varchar(128) NOT NULL COMMENT 'Nome do banco de dados',
  `table_name` varchar(255) NOT NULL COMMENT 'Nome da tabela',
  `table_schema` varchar(64) DEFAULT NULL COMMENT 'Schema/Database',
  `row_count` bigint DEFAULT NULL COMMENT 'Número estimado de linhas',
  `table_size_mb` decimal(10,2) DEFAULT NULL COMMENT 'Tamanho da tabela em MB',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização do cache',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_source_table` (`connection_id`,`database_name`,`table_name`),
  KEY `idx_connection` (`connection_id`,`database_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cache de tabelas disponíveis nas fontes de dados SQL';


-- lista_revisao2.email_tokens definição

CREATE TABLE `email_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(32) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.funcionalidade definição

CREATE TABLE `funcionalidade` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `descricao` (`descricao`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.perfil definição

CREATE TABLE `perfil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.source_types definição

CREATE TABLE `source_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `description` varchar(100) NOT NULL COMMENT 'DescriÃ§Ã£o do tipo de fonte (Ex: MySQL, CSV, API REST)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `description` (`description`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.usuario definição

CREATE TABLE `usuario` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.pasta definição

CREATE TABLE `pasta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(100) NOT NULL,
  `id_usuario` tinyint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pasta_usuario_FK` (`id_usuario`),
  CONSTRAINT `pasta_usuario_FK` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.perfil_funcionalidade definição

CREATE TABLE `perfil_funcionalidade` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_perfil` int NOT NULL,
  `id_funcionalidade` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perfil_funcionalidade_unique` (`id_perfil`,`id_funcionalidade`),
  KEY `fk_perfil_funcionalidade_perfil_idx` (`id_perfil`),
  KEY `fk_perfil_funcionalidade_funcionalidade_idx` (`id_funcionalidade`),
  CONSTRAINT `fk_perfil_funcionalidade_funcionalidade` FOREIGN KEY (`id_funcionalidade`) REFERENCES `funcionalidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_perfil_funcionalidade_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.usuario_perfil definição

CREATE TABLE `usuario_perfil` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` tinyint unsigned NOT NULL,
  `id_perfil` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_perfil_unique` (`id_usuario`,`id_perfil`),
  KEY `fk_usuario_perfil_usuario_idx` (`id_usuario`),
  KEY `fk_usuario_perfil_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.dag_configurations definição

CREATE TABLE `dag_configurations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pasta` int unsigned NOT NULL COMMENT 'ID da pasta associada, FK para a tabela pasta(id)',
  `dag_id` varchar(128) NOT NULL COMMENT 'O nome Ãºnico da DAG no Airflow',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Indica se a DAG deve ser gerada',
  `owner` varchar(64) DEFAULT 'webapp_user' COMMENT 'ProprietÃ¡rio da DAG no Airflow',
  `schedule_interval` varchar(64) DEFAULT '0 0 * * *' COMMENT 'Agendamento no formato cron',
  `description` text COMMENT 'DescriÃ§Ã£o da DAG',
  `id_source_type` int unsigned NOT NULL COMMENT 'ID do tipo de fonte de dados (FK para source_types)',
  `is_multi_table` tinyint(1) DEFAULT '0' COMMENT 'Indica se esta DAG processa múltiplas tabelas (TRUE) ou single table (FALSE)',
  `max_parallel_tasks` int DEFAULT '16' COMMENT 'Número máximo de tasks paralelas para DAGs multi-table',
  `sql_connection_id` varchar(128) DEFAULT NULL COMMENT 'ID da conexão configurada no Airflow (ex: mysql_northwind)',
  `sql_host` varchar(255) DEFAULT NULL COMMENT 'Host do servidor de banco de dados (ex: mysql, localhost, 192.168.1.10)',
  `sql_port` int DEFAULT '3306' COMMENT 'Porta do servidor de banco de dados (ex: 3306 para MySQL, 5432 para PostgreSQL)',
  `sql_database_name` varchar(128) DEFAULT NULL COMMENT 'Nome do database/schema a conectar (ex: northwind, lista_revisao2)',
  `sql_user` varchar(128) DEFAULT NULL COMMENT 'Usuário do banco de dados (ex: root, admin)',
  `sql_password` varchar(255) DEFAULT NULL COMMENT 'Senha do banco de dados (armazenada de forma segura)',
  `source_filename` varchar(512) DEFAULT NULL COMMENT 'Caminho do arquivo ou URI de conexÃ£o',
  `target_table_name` varchar(128) DEFAULT NULL COMMENT 'Nome da tabela (NULL para multi-table, específico para single-table)',
  `python_module_path` varchar(255) DEFAULT NULL COMMENT 'Caminho do mÃ³dulo Python a ser chamado',
  `transform_args` json DEFAULT NULL COMMENT 'ParÃ¢metros extras para a funÃ§Ã£o Python (JSON)',
  `start_date` date DEFAULT NULL,
  `ssh_host` varchar(255) DEFAULT NULL COMMENT 'FQDN ou IP do Jump Server',
  `ssh_port` int DEFAULT '22' COMMENT 'Porta SSH para conexÃ£o, padrÃ£o 22',
  `ssh_user` varchar(100) DEFAULT NULL COMMENT 'UsuÃ¡rio SSH para autenticaÃ§Ã£o',
  `ssh_key_path` varchar(255) DEFAULT NULL COMMENT 'Caminho da chave privada SSH no servidor Airflow',
  `ssh_local_port` int DEFAULT '13306' COMMENT 'Porta local que serÃ¡ usada para o tÃºnel',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dag_id` (`dag_id`),
  KEY `idx_dag_active` (`is_active`),
  KEY `idx_owner` (`owner`),
  KEY `fk_dagconfig_pasta` (`id_pasta`),
  KEY `fk_source_type` (`id_source_type`),
  KEY `idx_dag_multi_table` (`is_multi_table`,`is_active`),
  CONSTRAINT `fk_dagconfig_pasta` FOREIGN KEY (`id_pasta`) REFERENCES `pasta` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_source_type` FOREIGN KEY (`id_source_type`) REFERENCES `source_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- lista_revisao2.dag_table_selections definição

CREATE TABLE `dag_table_selections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_dag_config` int NOT NULL COMMENT 'FK para dag_configurations(id)',
  `table_name` varchar(255) NOT NULL COMMENT 'Nome da tabela a ser processada',
  `is_selected` tinyint(1) DEFAULT '1' COMMENT 'Se a tabela está selecionada para processamento',
  `row_count` bigint DEFAULT NULL COMMENT 'Número estimado de linhas (cache)',
  `last_sync` timestamp NULL DEFAULT NULL COMMENT 'Última vez que esta tabela foi processada',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dag_table` (`id_dag_config`,`table_name`),
  KEY `idx_selected` (`id_dag_config`,`is_selected`),
  CONSTRAINT `fk_dag_table_selections_config` FOREIGN KEY (`id_dag_config`) REFERENCES `dag_configurations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Armazena as tabelas selecionadas para cada DAG multi-table';