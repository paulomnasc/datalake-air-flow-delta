-- ✅ Tabela de Definição de Unidades de Competência (UC)
-- Define quais tarefas/checkpoints estão disponíveis para cada vídeo
-- Cada UC é um template que será rastreado na tabela uc_progress

CREATE TABLE IF NOT EXISTS uc_definition (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uc_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'ID único da UC (ex: uc-001)',
    video_id INT UNSIGNED NOT NULL COMMENT 'FK para video(id)',
    task_number INT UNSIGNED NOT NULL COMMENT 'Número sequencial da tarefa (1, 2, 3...)',
    task_title VARCHAR(255) NOT NULL COMMENT 'Título da tarefa (ex: Configurar Fonte S3)',
    task_description TEXT COMMENT 'Descrição detalhada da UC',
    video_checkpoint VARCHAR(10) COMMENT 'Timestamp onde esta UC é ensinada (ex: 02:15)',
    xp_points INT UNSIGNED DEFAULT 100 COMMENT 'Pontos ganhos ao completar (+100 XP)',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Se a UC está disponível para alunos',
    `order` INT DEFAULT 0 COMMENT 'Ordem de exibição/progressão das UCs',
    created_by INT UNSIGNED COMMENT 'ID do admin que criou',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_uc_per_video (video_id, uc_id),
    KEY idx_uc_id (uc_id),
    KEY idx_video_active (video_id, is_active),
    KEY idx_task_number (video_id, task_number),
    KEY idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Template/Definição de Unidades de Competência disponíveis por vídeo';

ALTER TABLE uc_definition ADD CONSTRAINT fk_uc_definition_video 
FOREIGN KEY (video_id) REFERENCES video(id) ON DELETE CASCADE;

ALTER TABLE uc_definition ADD CONSTRAINT fk_uc_definition_created_by 
FOREIGN KEY (created_by) REFERENCES usuario(id) ON DELETE SET NULL;
