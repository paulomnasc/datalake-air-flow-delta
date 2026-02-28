CREATE OR REPLACE VIEW vw_usuario_curso_progresso AS
SELECT 
    u.id AS usuario_id,
    u.nome AS nome_aluno,
    u.email,
    c.id AS curso_id,
    c.name AS nome_curso,
    c.description AS descricao_curso,
    COUNT(DISTINCT m.id) AS total_modulos,
    COUNT(DISTINCT v.id) AS total_videos,
    COUNT(DISTINCT uc.id) AS total_tarefas,
    COALESCE(SUM(uc.xp_points), 0) AS xp_total_disponivel,
    COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) AS tarefas_concluidas,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) AS xp_ganho,
    CASE 
        WHEN COUNT(DISTINCT uc.id) = 0 THEN 0
        ELSE ROUND(
            (COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END)::numeric 
             / COUNT(DISTINCT uc.id)::numeric) * 100, 
            2
        )
    END AS percentual_conclusao,
    CASE 
        WHEN COUNT(DISTINCT uc.id) = 0 THEN 'Sem tarefas'
        WHEN COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END) = COUNT(DISTINCT uc.id) THEN '✅ Completo'
        ELSE '🔄 Em progresso'
    END AS status
FROM usuario u
LEFT JOIN course c ON 1=1
LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
WHERE c.is_active = 1 
  AND u.id NOT IN (146, 176, 201)
GROUP BY 
    u.id, u.nome, u.email, 
    c.id, c.name, c.description
ORDER BY u.nome, c.name;