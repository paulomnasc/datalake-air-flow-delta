-- 📺 Tabela de Progresso em Vídeos
-- Rastreia quanto cada aluno assistiu de cada vídeo

CREATE TABLE IF NOT EXISTS video_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL COMMENT 'ID do usuário',
    video_id INT UNSIGNED NOT NULL COMMENT 'FK para video(id)',
    percent DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentual do vídeo assistido (0-100)',
    watched_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Segundos assistidos',
    total_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Duração total do vídeo em segundos',
    completed TINYINT(1) DEFAULT 0 COMMENT 'Se assistiu 100% do vídeo',
    last_position_seconds INT UNSIGNED DEFAULT 0 COMMENT 'Última posição assistida (para retomar)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_video (user_id, video_id),
    KEY idx_user_id (user_id),
    KEY idx_video_id (video_id),
    KEY idx_completed (completed),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rastreamento de progresso de alunos assistindo vídeos';

ALTER TABLE video_progress ADD CONSTRAINT fk_video_progress_video 
FOREIGN KEY (video_id) REFERENCES video(id) ON DELETE CASCADE;
