-- 📚 Tabela de Cursos
-- Estrutura principal de cursos oferecidos na plataforma

CREATE TABLE IF NOT EXISTS course (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID único do curso (ex: curso-001, ml-fundamentals)',
    name VARCHAR(255) NOT NULL COMMENT 'Nome do curso',
    description TEXT COMMENT 'Descrição e objetivos do curso',
    icon_url VARCHAR(255) COMMENT 'URL do ícone/logo do curso',
    color VARCHAR(7) DEFAULT '#4f46e5' COMMENT 'Cor hexadecimal para UI',
    `order` INT DEFAULT 0 COMMENT 'Ordem de exibição',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o curso está disponível',
    created_by INT UNSIGNED COMMENT 'ID do admin que criou (FK usuario)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_course_id (course_id),
    KEY idx_active (is_active),
    KEY idx_order (`order`),
    KEY idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de cursos disponíveis na plataforma';

ALTER TABLE course ADD CONSTRAINT fk_course_created_by 
FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;
