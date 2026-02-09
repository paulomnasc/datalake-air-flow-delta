-- Consulta de progresso de vídeos por aluno
-- Mostra quantos segundos/minutos cada aluno assistiu de cada vídeo


SELECT 
    vp.user_id,
    u.nome as aluno,
    vp.video_id,
    v.title as titulo_video,
    vp.watched_seconds,
    ROUND(vp.watched_seconds/60,2) as minutos_assistidos,
    vp.total_seconds,
    ROUND(vp.total_seconds/60,2) as minutos_totais,
    vp.percent as percentual_assistido,
    CASE WHEN vp.completed = 1 THEN '✅ Concluído' ELSE '🔄 Em progresso' END as status
FROM video_progress vp
JOIN usuario u ON u.id = vp.user_id
JOIN video v ON v.id = vp.video_id
ORDER BY u.nome, v.title;