-- 📋 Tabela de Progresso em Unidades de Competência (UC)
-- Armazena o progresso do aluno nas tarefas/checkpoints de cada módulo

CREATE TABLE IF NOT EXISTS uc_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    uc_definition_id INT UNSIGNED NOT NULL COMMENT 'FK para uc_definition(id)',
    completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL COMMENT 'Data/hora da conclusão',
    progress_percent DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Progresso percentual (0-100)',
    attempts INT UNSIGNED DEFAULT 0 COMMENT 'Número de tentativas',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para queries rápidas
    UNIQUE KEY unique_uc_progress (user_id, uc_definition_id),
    KEY idx_user_id (user_id),
    KEY idx_uc_definition_id (uc_definition_id),
    KEY idx_completed (completed),
    KEY idx_completed_at (completed_at),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE uc_progress ADD CONSTRAINT fk_uc_progress_definition 
FOREIGN KEY (uc_definition_id) REFERENCES uc_definition(id) ON DELETE CASCADE;

-- Índice composto para queries por aluno
CREATE INDEX idx_user_created ON uc_progress(user_id, created_at DESC);

-- Índice para ordenar por data de conclusão
CREATE INDEX idx_completed_at ON uc_progress(user_id, completed_at DESC);

-- Comentário da tabela
ALTER TABLE uc_progress COMMENT='Rastreamento de progresso de alunos em Unidades de Competência. Cada linha é instância de um aluno completando uma UC específica.';

