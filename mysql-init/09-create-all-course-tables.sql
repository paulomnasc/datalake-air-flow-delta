-- Script consolidado para criar todas as tabelas de cursos
-- Executar em ordem para garantir as FKs

USE lista_revisao2;

-- Dropar tabelas existentes (ordem inversa por causa de FKs)
DROP TABLE IF EXISTS uc_progress;
DROP TABLE IF EXISTS video_progress;
DROP TABLE IF EXISTS uc_definition;
DROP TABLE IF EXISTS video;
DROP TABLE IF EXISTS module;
DROP TABLE IF EXISTS course;

-- ==================== COURSE ====================
CREATE TABLE course (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID único do curso',
    name VARCHAR(255) NOT NULL COMMENT 'Nome do curso',
    description TEXT COMMENT 'Descrição e objetivos',
    icon_url VARCHAR(255) COMMENT 'URL do ícone',
    color VARCHAR(7) DEFAULT '#4f46e5' COMMENT 'Cor hexadecimal',
    `order` INT DEFAULT 0 COMMENT 'Ordem de exibição',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Status ativo',
    created_by TINYINT UNSIGNED COMMENT 'FK usuario.id',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_course_id (course_id),
    INDEX idx_active (is_active),
    INDEX idx_order_col (`order`),
    INDEX idx_created_by (created_by),
    
    CONSTRAINT fk_course_created_by FOREIGN KEY (created_by) 
        REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== MODULE ====================
CREATE TABLE module (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id VARCHAR(50) NOT NULL COMMENT 'ID único do módulo',
    course_id INT UNSIGNED NOT NULL COMMENT 'FK course.id',
    name VARCHAR(255) NOT NULL COMMENT 'Nome do módulo',
    description TEXT COMMENT 'Descrição',
    module_number INT UNSIGNED NOT NULL COMMENT 'Número sequencial',
    `order` INT DEFAULT 0 COMMENT 'Ordem de exibição',
    estimated_hours DECIMAL(5,2) COMMENT 'Horas estimadas',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Status ativo',
    created_by TINYINT UNSIGNED COMMENT 'FK usuario.id',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_module_per_course (course_id, module_id),
    INDEX idx_module_id (module_id),
    INDEX idx_course_active (course_id, is_active),
    INDEX idx_order_col (`order`),
    INDEX idx_created_by (created_by),
    
    CONSTRAINT fk_module_course FOREIGN KEY (course_id) 
        REFERENCES course(id) ON DELETE CASCADE,
    CONSTRAINT fk_module_created_by FOREIGN KEY (created_by) 
        REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== VIDEO ====================
CREATE TABLE video (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID único do vídeo',
    module_id INT UNSIGNED NOT NULL COMMENT 'FK module.id',
    youtube_id VARCHAR(100) NOT NULL COMMENT 'ID do vídeo no YouTube',
    title VARCHAR(255) NOT NULL COMMENT 'Título do vídeo',
    description TEXT COMMENT 'Descrição',
    thumbnail_url VARCHAR(255) COMMENT 'URL da thumbnail',
    duration_seconds INT UNSIGNED COMMENT 'Duração em segundos',
    video_order INT DEFAULT 0 COMMENT 'Ordem de exibição',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Status ativo',
    created_by TINYINT UNSIGNED COMMENT 'FK usuario.id',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_video_id (video_id),
    INDEX idx_module_active (module_id, is_active),
    INDEX idx_youtube_id (youtube_id),
    INDEX idx_video_order (video_order),
    INDEX idx_created_by (created_by),
    
    CONSTRAINT fk_video_module FOREIGN KEY (module_id) 
        REFERENCES module(id) ON DELETE CASCADE,
    CONSTRAINT fk_video_created_by FOREIGN KEY (created_by) 
        REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== UC_DEFINITION ====================
CREATE TABLE uc_definition (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uc_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID único da UC',
    video_id INT UNSIGNED NOT NULL COMMENT 'FK video.id',
    task_number INT UNSIGNED NOT NULL COMMENT 'Número sequencial da tarefa',
    task_title VARCHAR(255) NOT NULL COMMENT 'Título da tarefa',
    task_description TEXT COMMENT 'Descrição da tarefa',
    video_checkpoint VARCHAR(20) COMMENT 'Timestamp no vídeo (ex: 02:15)',
    external_url VARCHAR(500) COMMENT 'URL ou rota para interface externa',
    xp_points INT DEFAULT 100 COMMENT 'Pontos XP',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Status ativo',
    `order` INT DEFAULT 0 COMMENT 'Ordem de progressão',
    created_by TINYINT UNSIGNED COMMENT 'FK usuario.id',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_uc_id (uc_id),
    INDEX idx_video_active (video_id, is_active),
    INDEX idx_task_number (task_number),
    INDEX idx_order_col (`order`),
    INDEX idx_created_by (created_by),
    
    CONSTRAINT fk_uc_definition_video FOREIGN KEY (video_id) 
        REFERENCES video(id) ON DELETE CASCADE,
    CONSTRAINT fk_uc_definition_created_by FOREIGN KEY (created_by) 
        REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== VIDEO_PROGRESS ====================
CREATE TABLE video_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL COMMENT 'ID/email do usuário',
    video_id INT UNSIGNED NOT NULL COMMENT 'FK video.id',
    percent DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentual assistido (0-100)',
    watched_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Segundos assistidos',
    total_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Duração total',
    completed TINYINT(1) DEFAULT 0 COMMENT 'Completou 100%',
    last_position_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Última posição (resume)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_video (user_id, video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_video_id (video_id),
    INDEX idx_completed (completed),
    INDEX idx_user_completed (user_id, completed),
    
    CONSTRAINT fk_video_progress_video_id FOREIGN KEY (video_id) 
        REFERENCES video(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== UC_PROGRESS ====================
CREATE TABLE uc_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL COMMENT 'ID/email do usuário',
    uc_definition_id INT UNSIGNED NOT NULL COMMENT 'FK uc_definition.id',
    completed TINYINT(1) DEFAULT 0 COMMENT 'UC completada',
    completed_at DATETIME COMMENT 'Data/hora de conclusão',
    progress_percent DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentual de progresso',
    attempts INT UNSIGNED DEFAULT 0 COMMENT 'Número de tentativas',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_uc (user_id, uc_definition_id),
    INDEX idx_user_id (user_id),
    INDEX idx_uc_definition_id (uc_definition_id),
    INDEX idx_completed (completed),
    INDEX idx_user_completion (user_id, completed),
    INDEX idx_completion_date (completed_at),
    
    CONSTRAINT fk_uc_progress_uc_definition FOREIGN KEY (uc_definition_id) 
        REFERENCES uc_definition(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificar tabelas criadas
SHOW TABLES LIKE '%course%';
SHOW TABLES LIKE '%module%';
SHOW TABLES LIKE '%video%';
SHOW TABLES LIKE 'uc_%';
