-- Queries Uteis para Analise de Feedback - Video 5
-- Estas queries ajudam a entender o perfil dos alunos que assistem o laboratorio MinIO

USE `lista_revisao2_test`;

-- =========================================
-- 1. RESUMO GERAL DO FEEDBACK
-- =========================================

-- Contar respostas por tipo de status do laboratorio
SELECT 
    CASE 
        WHEN lab_status = 'consegui_rodar' THEN '✅ Consegui rodar tudo'
        WHEN lab_status = 'erro_docker' THEN '🛠️ Erro no Docker/S3'
        WHEN lab_status = 'so_assistindo' THEN '📺 Só assistindo'
        ELSE 'Não respondeu'
    END as 'Status do Lab',
    COUNT(*) as 'Total de Alunos',
    ROUND((COUNT(*) / (SELECT COUNT(*) FROM video_feedback WHERE video_id = 5) * 100), 1) as 'Percentual %'
FROM video_feedback
WHERE video_id = 5
GROUP BY lab_status
ORDER BY COUNT(*) DESC;

-- =========================================
-- 2. PERCEPCAO DE VALOR DA AULA
-- =========================================

SELECT 
    CASE 
        WHEN value_perception = 'sim_sentido' THEN '✨ Sim, agora faz sentido'
        WHEN value_perception = 'nao_sabia' THEN '💡 Não sabia (nova info)'
        WHEN value_perception = 'direto_nuvem' THEN '☁️ Prefiro direto na nuvem'
        ELSE 'Não respondeu'
    END as 'Percepcao de Valor',
    COUNT(*) as 'Total de Alunos',
    ROUND((COUNT(*) / (SELECT COUNT(*) FROM video_feedback WHERE video_id = 5) * 100), 1) as 'Percentual %'
FROM video_feedback
WHERE video_id = 5
GROUP BY value_perception
ORDER BY COUNT(*) DESC;

-- =========================================
-- 3. TAXA DE SUCESSO DO LABORATORIO
-- =========================================

SELECT 
    'Taxa de Sucesso' as 'Metrica',
    ROUND((SUM(CASE WHEN lab_status = 'consegui_rodar' THEN 1 ELSE 0 END) / COUNT(*) * 100), 1) as 'Percentual %',
    SUM(CASE WHEN lab_status = 'consegui_rodar' THEN 1 ELSE 0 END) as 'Quantidade de Alunos'
FROM video_feedback
WHERE video_id = 5;

-- =========================================
-- 4. CORRELACAO: LAB STATUS X PERCEPCAO DE VALOR
-- =========================================

SELECT 
    CASE 
        WHEN vf.lab_status = 'consegui_rodar' THEN '✅ Consegui rodar'
        WHEN vf.lab_status = 'erro_docker' THEN '🛠️ Erro Docker'
        ELSE '📺 Só assistindo'
    END as 'Status Lab',
    CASE 
        WHEN vf.value_perception = 'sim_sentido' THEN '✨ Faz sentido'
        WHEN vf.value_perception = 'nao_sabia' THEN '💡 Nova info'
        ELSE '☁️ Prefiro nuvem'
    END as 'Percepcao Valor',
    COUNT(*) as 'Alunos',
    ROUND((COUNT(*) / SUM(COUNT(*)) OVER (PARTITION BY vf.lab_status) * 100), 1) as '%'
FROM video_feedback vf
WHERE vf.video_id = 5
GROUP BY vf.lab_status, vf.value_perception
ORDER BY vf.lab_status, COUNT(*) DESC;

-- =========================================
-- 5. LISTAR FEEDBACKS ABERTOS (QUALITATIVO)
-- =========================================

SELECT 
    u.nome as 'Aluno',
    u.email,
    CASE 
        WHEN vf.lab_status = 'consegui_rodar' THEN '✅ Sucesso'
        WHEN vf.lab_status = 'erro_docker' THEN '🛠️ Erro'
        ELSE '📺 Assistindo'
    END as 'Status',
    vf.open_feedback as 'Feedback Aberto',
    vf.created_at as 'Data'
FROM video_feedback vf
JOIN usuario u ON vf.user_id = u.id
WHERE vf.video_id = 5 AND vf.open_feedback IS NOT NULL AND vf.open_feedback != ''
ORDER BY vf.created_at DESC;

-- =========================================
-- 6. ALUNOS COM DIFICULDADES (PRIORIDADE PARA SUPORTE)
-- =========================================

SELECT 
    u.nome as 'Aluno',
    u.email,
    vf.lab_status as 'Problema',
    vf.open_feedback as 'Detalhes',
    vf.created_at as 'Reportado em'
FROM video_feedback vf
JOIN usuario u ON vf.user_id = u.id
WHERE vf.video_id = 5 
  AND (vf.lab_status = 'erro_docker' OR vf.open_feedback LIKE '%erro%' OR vf.open_feedback LIKE '%problema%')
ORDER BY vf.created_at DESC;

-- =========================================
-- 7. TIMELINE DE RESPOSTAS
-- =========================================

SELECT 
    DATE(created_at) as 'Data',
    COUNT(*) as 'Total Responses',
    SUM(CASE WHEN lab_status = 'consegui_rodar' THEN 1 ELSE 0 END) as 'Successo',
    SUM(CASE WHEN lab_status = 'erro_docker' THEN 1 ELSE 0 END) as 'Erros',
    SUM(CASE WHEN lab_status = 'so_assistindo' THEN 1 ELSE 0 END) as 'Assistindo'
FROM video_feedback
WHERE video_id = 5
GROUP BY DATE(created_at)
ORDER BY DATE(created_at) DESC;

-- =========================================
-- 8. USUARIOS UNICOS QUE RESPONDERAM
-- =========================================

SELECT 
    COUNT(DISTINCT user_id) as 'Total de Alunos que Responderam',
    COUNT(DISTINCT CASE WHEN lab_status = 'consegui_rodar' THEN user_id END) as 'Conseguiram Rodar',
    COUNT(DISTINCT CASE WHEN lab_status = 'erro_docker' THEN user_id END) as 'Tiveram Erros',
    COUNT(DISTINCT CASE WHEN lab_status = 'so_assistindo' THEN user_id END) as 'Apenas Assistindo'
FROM video_feedback
WHERE video_id = 5;

-- =========================================
-- 9. EXCLUIR FEEDBACK DE UM USUARIO (se necessario)
-- =========================================

-- DELETE FROM video_feedback WHERE user_id = ? AND video_id = 5;

-- =========================================
-- 10. LIMPAR TODOS OS FEEDBACKS (cautela!)
-- =========================================

-- DELETE FROM video_feedback WHERE video_id = 5;
