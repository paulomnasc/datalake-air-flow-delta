-- 📖 Tabela de Módulos (Módulos de Aprendizado)
-- Cada curso tem múltiplos módulos, cada módulo tem múltiplos vídeos

CREATE TABLE IF NOT EXISTS module (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID único do módulo (ex: mod-001, mod-006)',
    course_id INT UNSIGNED NOT NULL COMMENT 'FK para course(id)',
    name VARCHAR(255) NOT NULL COMMENT 'Nome do módulo (ex: Introdução ao Data Lake)',
    description TEXT COMMENT 'Descrição dos objetivos e conteúdo',
    module_number INT UNSIGNED COMMENT 'Número sequencial do módulo (1, 2, 3...)',
    `order` INT DEFAULT 0 COMMENT 'Ordem de exibição dentro do curso',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o módulo está disponível',
    estimated_hours INT UNSIGNED DEFAULT 2 COMMENT 'Horas estimadas para completar',
    created_by tinyint unsigned COMMENT 'ID do admin que criou',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_module_per_course (course_id, module_id),
    KEY idx_module_id (module_id),
    KEY idx_course_active (course_id, is_active),
    KEY idx_order (`order`, course_id),
    KEY idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Módulos de aprendizado dentro de cada curso';

-- ALTER TABLE module ADD CONSTRAINT fk_module_course 
-- FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE;

ALTER TABLE module ADD CONSTRAINT fk_module_created_by 
FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;
