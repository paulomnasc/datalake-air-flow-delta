-- Migration: Cria tabela para armazenar feedback dos usuarios nos videos
-- Data: 2026-03-22
-- Descricao: Tabela para persistir respostas do formulario de feedback que aparece aos 80% do video
-- Utilizada para coletar dados sobre experiencia do aluno com o laboratorio

USE `lista_revisao2_test`;

CREATE TABLE IF NOT EXISTS video_feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'ID unico do feedback',
    user_id INT UNSIGNED NOT NULL COMMENT 'FK usuario.id - Usuario que respondeu',
    video_id INT UNSIGNED NOT NULL COMMENT 'FK video.id - Video assistido',
    lab_status VARCHAR(100) COMMENT 'Status do laboratorio (consegui_rodar, erro_docker, so_assistindo)',
    value_perception VARCHAR(100) COMMENT 'Percepcao de valor (sim_sentido, nao_sabia, direto_nuvem)',
    open_feedback TEXT COMMENT 'Feedback aberto do usuario - resposta do campo textual',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criacao do registro',
    
    INDEX idx_user_video (user_id, video_id) COMMENT 'Index para buscar feedback unico por usuario/video',
    INDEX idx_video_id (video_id) COMMENT 'Index para listar feedbacks de um video',
    INDEX idx_user_id (user_id) COMMENT 'Index para listar feedbacks de um usuario',
    INDEX idx_created_at (created_at) COMMENT 'Index para ordenacao temporal',
    
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) 
        REFERENCES usuario(id) ON DELETE CASCADE COMMENT 'Cascade: deleta feedback se usuario for removido',
    CONSTRAINT fk_feedback_video FOREIGN KEY (video_id) 
        REFERENCES video(id) ON DELETE CASCADE COMMENT 'Cascade: deleta feedback se video for removido'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Feedback dos usuarios quando atingem 80% do video - Micro-pesquisa de experiencia';

-- Adicionar coluna de atualizacao (opcional)
-- ALTER TABLE video_feedback ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Script para dropar a tabela (se necessario descomentar):
-- DROP TABLE IF EXISTS video_feedback;
