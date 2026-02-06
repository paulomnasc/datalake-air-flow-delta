-- 🎥 Tabela de Vídeos
-- Cada módulo tem múltiplos vídeos

CREATE TABLE IF NOT EXISTS video (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID único do vídeo (ex: vid-001, vid-6073YAGEq08)',
    module_id INT UNSIGNED NOT NULL COMMENT 'FK para module(id)',
    youtube_id VARCHAR(50) NOT NULL COMMENT 'ID do vídeo no YouTube (ex: 6073YAGEq08)',
    title VARCHAR(255) NOT NULL COMMENT 'Título do vídeo',
    description TEXT COMMENT 'Descrição e resumo do conteúdo',
    thumbnail_url VARCHAR(255) COMMENT 'URL da thumbnail (pode ser YouTube default)',
    duration_seconds INT UNSIGNED COMMENT 'Duração em segundos (ex: 425)',
    video_order INT DEFAULT 0 COMMENT 'Ordem de exibição dentro do módulo',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Se o vídeo está disponível',
    created_by INT UNSIGNED COMMENT 'ID do admin que criou',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_video_id (video_id),
    KEY idx_module_active (module_id, is_active),
    KEY idx_youtube_id (youtube_id),
    KEY idx_order (video_order, module_id),
    KEY idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vídeos de aprendizado dentro de cada módulo';

ALTER TABLE video ADD CONSTRAINT fk_video_module 
FOREIGN KEY (module_id) REFERENCES module(id) ON DELETE CASCADE;

ALTER TABLE video ADD CONSTRAINT fk_video_created_by 
FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;
