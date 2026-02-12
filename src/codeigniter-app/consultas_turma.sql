-- ==========================================
-- CONSULTAS PARA ACOMPANHAR PROGRESSO DOS ALUNOS NOS CURSOS
-- ==========================================

-- ALUNOS QUE RETORNARAM PROGRESSO PARA A PLATAFORMA APÓS O CADASTRO
SELECT 
    u.id AS user_id,
    u.nome AS user_name,
    u.email,
    u.criado_em,
    COUNT(ac.id) AS return_count,
    MAX(ac.created_at) AS last_return
FROM activity_logs ac
INNER JOIN usuario u ON u.id = ac.user_id
WHERE ac.user_id NOT IN (146, 176)
  -- AND ac.route_alias = 'Usuario.logar'
  AND DATE_FORMAT(u.criado_em, '%Y-%m-%d') < DATE_FORMAT(ac.created_at, '%Y-%m-%d')
GROUP BY u.id
ORDER BY return_count DESC, last_return DESC;

-- 1. VISÃO GERAL: PROGRESSO DE TODOS OS ALUNOS POR CURSO
SELECT 
    u.id as usuario_id,
    u.nome as nome_aluno,
    u.email,
    c.id as curso_id,
    c.name as nome_curso,
    c.description as descricao_curso,
    COUNT(DISTINCT m.id) as total_modulos,
    COUNT(DISTINCT v.id) as total_videos,
    COUNT(DISTINCT uc.id) as total_tarefas,
    COALESCE(SUM(uc.xp_points), 0) as xp_total_disponivel,
    COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) as tarefas_concluidas,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as xp_ganho,
    CASE 
        WHEN COUNT(DISTINCT uc.id) = 0 THEN 0
        ELSE ROUND((COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END) / COUNT(DISTINCT uc.id)) * 100, 2)
    END as percentual_conclusao,
    CASE 
        WHEN COUNT(DISTINCT uc.id) = 0 THEN 'Sem tarefas'
        WHEN COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END) = COUNT(DISTINCT uc.id) THEN '✅ Completo'
        ELSE '🔄 Em progresso'
    END as status
FROM usuario u
LEFT JOIN course c ON 1=1
LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
WHERE c.is_active = 1
GROUP BY u.id, c.id
ORDER BY u.nome, c.name;

-- 2. PROGRESSO DETALHADO POR ALUNO
SELECT 
    u.id as usuario_id,
    u.name as nome_aluno,
    u.email,
    c.name as curso,
    m.module_number as numero_modulo,
    m.name as nome_modulo,
    v.title as titulo_video,
    uc.task_number as numero_tarefa,
    uc.task_title as titulo_tarefa,
    uc.xp_points as xp_disponivel,
    COALESCE(up.completed, 0) as tarefa_concluida,
    COALESCE(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END, 0) as xp_ganho,
    up.completed_at as data_conclusao
FROM usuario u
JOIN course c ON 1=1
JOIN module m ON m.course_id = c.id AND m.is_active = 1
JOIN video v ON v.module_id = m.id AND v.is_active = 1
JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
WHERE u.deleted_at IS NULL AND c.is_active = 1
ORDER BY u.name, c.name, m.module_number, v.video_order, uc.task_number;

-- 3. RESUMO POR ALUNO (TODOS OS CURSOS)
SELECT 
    u.id as usuario_id,
    u.name as nome_aluno,
    u.email,
    COUNT(DISTINCT c.id) as cursos_inscritos,
    COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) as total_tarefas_concluidas,
    COALESCE(COUNT(DISTINCT uc.id), 0) as total_tarefas_disponiveis,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as xp_total_ganho,
    COALESCE(SUM(uc.xp_points), 0) as xp_total_possivel,
    CASE 
        WHEN COUNT(DISTINCT uc.id) = 0 THEN 0
        ELSE ROUND((COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) / COUNT(DISTINCT uc.id)) * 100, 2)
    END as percentual_geral
FROM usuario u
LEFT JOIN course c ON 1=1 AND c.is_active = 1
LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
WHERE u.deleted_at IS NULL
GROUP BY u.id
ORDER BY xp_total_ganho DESC, nome_aluno;

-- 4. TAREFAS PENDENTES POR ALUNOS (O QUE AINDA NÃO FIZERAM)
SELECT 
    u.name as aluno,
    c.name as curso,
    m.name as modulo,
    v.title as video,
    uc.task_title as tarefa,
    uc.xp_points as xp_disponiveis
FROM usuario u
CROSS JOIN course c
JOIN module m ON m.course_id = c.id AND m.is_active = 1
JOIN video v ON v.module_id = m.id AND v.is_active = 1
JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
WHERE u.deleted_at IS NULL 
  AND c.is_active = 1
  AND (up.id IS NULL OR up.completed = 0)
ORDER BY u.name, c.name, m.module_number, v.video_order, uc.task_number;

-- 5. RANKING DE ALUNOS POR XP GANHO
SELECT 
    ROW_NUMBER() OVER (ORDER BY COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) DESC) as ranking,
    u.id as usuario_id,
    u.name as aluno,
    u.email,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as xp_total,
    COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) as tarefas_concluidas,
    COUNT(DISTINCT c.id) as cursos_iniciados
FROM usuario u
LEFT JOIN course c ON 1=1 AND c.is_active = 1
LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id AND up.completed = 1
WHERE u.deleted_at IS NULL
GROUP BY u.id
ORDER BY xp_total DESC;

-- 6. CURSOS COM MAIS ALUNOS E PROGRESSO
SELECT 
    c.name as curso,
    COUNT(DISTINCT u.id) as total_alunos,
    COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN u.id END), 0) as alunos_com_progresso,
    ROUND((COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN u.id END), 0) / COUNT(DISTINCT u.id)) * 100, 2) as percentual_alunos_ativos,
    COUNT(DISTINCT m.id) as modulos,
    COUNT(DISTINCT v.id) as videos,
    COUNT(DISTINCT uc.id) as tarefas,
    COALESCE(SUM(uc.xp_points), 0) as xp_total_disponivel,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as xp_total_ganho
FROM course c
LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.uc_definition_id = uc.id
LEFT JOIN usuario u ON u.id = up.user_id AND u.deleted_at IS NULL
WHERE c.is_active = 1
GROUP BY c.id
ORDER BY total_alunos DESC;

-- 7. PROGRESSO EM TEMPO REAL (ÚLTIMAS TAREFAS CONCLUÍDAS)
SELECT 
    u.name as aluno,
    c.name as curso,
    v.title as video,
    uc.task_title as tarefa,
    uc.xp_points as xp_ganho,
    up.completed_at as data_conclusao,
    TIMESTAMPDIFF(HOUR, up.completed_at, NOW()) as horas_atras
FROM uc_progress up
JOIN usuario u ON u.id = up.user_id
JOIN uc_definition uc ON uc.id = up.uc_definition_id
JOIN video v ON v.id = uc.video_id
JOIN module m ON m.id = v.module_id
JOIN course c ON c.id = m.course_id
WHERE up.completed = 1 AND up.completed_at IS NOT NULL
ORDER BY up.completed_at DESC
LIMIT 50;

-- 8. DESEMPENHO POR MÓDULO
SELECT 
    c.name as curso,
    m.module_number as numero_modulo,
    m.name as nome_modulo,
    COUNT(DISTINCT u.id) as alunos_visitaram,
    COUNT(DISTINCT uc.id) as total_tarefas,
    COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) as tarefas_concluidas,
    ROUND((COALESCE(COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END), 0) / COUNT(DISTINCT uc.id)) * 100, 2) as percentual_conclusao,
    COALESCE(SUM(uc.xp_points), 0) as xp_disponivel,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as xp_ganho
FROM course c
JOIN module m ON m.course_id = c.id AND m.is_active = 1
JOIN video v ON v.module_id = m.id AND v.is_active = 1
JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
LEFT JOIN uc_progress up ON up.uc_definition_id = uc.id
LEFT JOIN usuario u ON u.id = up.user_id
WHERE c.is_active = 1
GROUP BY c.id, m.id
ORDER BY c.name, m.module_number;

-- 9. ALUNOS INATIVOS (SEM PROGRESSO RECENTE)
SELECT 
    u.id as usuario_id,
    u.name as aluno,
    u.email,
    MAX(up.completed_at) as ultima_atividade,
    TIMESTAMPDIFF(DAY, MAX(up.completed_at), NOW()) as dias_inativo,
    COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as xp_total_ganho
FROM usuario u
LEFT JOIN uc_progress up ON up.user_id = u.id
LEFT JOIN uc_definition uc ON uc.id = up.uc_definition_id
WHERE u.deleted_at IS NULL
GROUP BY u.id
HAVING MAX(up.completed_at) IS NULL OR MAX(up.completed_at) < DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY dias_inativo DESC;

-- 10. RESUMO EXECUTIVO
SELECT 
    'Estatísticas Gerais' as metrica,
    'Total de Alunos' as descricao,
    COUNT(DISTINCT u.id) as valor
FROM usuario u
WHERE u.deleted_at IS NULL
UNION ALL
SELECT 'Estatísticas Gerais', 'Total de Cursos Ativos', COUNT(*) FROM course WHERE is_active = 1
UNION ALL
SELECT 'Estatísticas Gerais', 'Total de Módulos', COUNT(*) FROM module WHERE is_active = 1
UNION ALL
SELECT 'Estatísticas Gerais', 'Total de Vídeos', COUNT(*) FROM video WHERE is_active = 1
UNION ALL
SELECT 'Estatísticas Gerais', 'Total de Tarefas', COUNT(*) FROM uc_definition WHERE is_active = 1
UNION ALL
SELECT 'Estatísticas Gerais', 'Total de Tarefas Concluídas', COUNT(*) FROM uc_progress WHERE completed = 1
UNION ALL
SELECT 'Estatísticas Gerais', 'XP Total Ganho por Alunos', COALESCE(SUM(uc.xp_points), 0)
FROM uc_progress up 
JOIN uc_definition uc ON uc.id = up.uc_definition_id 
WHERE up.completed = 1;
