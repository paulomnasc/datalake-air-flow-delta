<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

<style>
.video-player-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.breadcrumb {
    color: #666;
    margin-bottom: 20px;
}

.breadcrumb a {
    color: #4f46e5;
    text-decoration: none;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #4f46e5;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.back-button:hover {
    background: #3730a3;
    transform: translateX(-4px);
}

.video-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-top: 30px;
}

.video-main {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.video-player {
    position: relative;
    padding-bottom: 56.25%; /* 16:9 aspect ratio */
    height: 0;
    overflow: hidden;
}

.video-player iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.video-info {
    padding: 25px;
}

.video-title-main {
    font-size: 28px;
    font-weight: bold;
    color: #333;
    margin: 0 0 15px 0;
}

.video-description-main {
    color: #666;
    line-height: 1.8;
    margin-top: 15px;
}

.tasks-sidebar {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 25px;
    max-height: 800px;
    overflow-y: auto;
}

.tasks-header {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.task-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid #e0e7ff;
    transition: all 0.2s;
}

.task-card.completed {
    background: #d4edda;
    border-left-color: #28a745;
}

.task-card:hover {
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.task-number {
    background: #4f46e5;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.task-card.completed .task-number {
    background: #28a745;
}

.task-title {
    font-size: 16px;
    font-weight: bold;
    color: #333;
    margin: 10px 0 8px 0;
}

.task-description {
    font-size: 14px;
    color: #666;
    line-height: 1.6;
}

.task-meta {
    display: flex;
    gap: 15px;
    margin-top: 12px;
    font-size: 13px;
    color: #666;
}

.task-checkbox {
    margin-top: 15px;
}

.task-checkbox label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: 500;
    color: #333;
}

.task-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.xp-total {
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: #333;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    margin-bottom: 20px;
}

@media (max-width: 1024px) {
    .video-layout {
        grid-template-columns: 1fr;
    }
}

.external-link-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.xp-earned-panel {
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
    text-align: center;
}

.xp-earned-panel h3 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
    font-weight: 600;
}

.xp-progress {
    display: flex;
    align-items: center;
    gap: 15px;
}

.xp-progress-bar {
    flex: 1;
    height: 20px;
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}

.xp-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ffd700, #ffaa00);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    font-weight: bold;
}

.xp-progress-value {
    color: #333;
    font-weight: bold;
    min-width: 80px;
    text-align: right;
}

.video-main.completed-video {
    background: #c8e6c9 !important;
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.2);
}
</style>

<div id="content">
    <div class="video-player-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div class="breadcrumb">
                <a href="<?php echo site_url('cursos'); ?>">Cursos</a> / 
                <a href="<?php echo site_url('curso/' . $course['id']); ?>"><?php echo esc($course['name']); ?></a> /
                <a href="<?php echo site_url('modulo/' . $module['id']); ?>">Módulo <?php echo esc($module['module_number']); ?></a>
            </div>
            <a href="<?php echo site_url('modulo/' . $module['id']); ?>" class="back-button">
                ← Voltar
            </a>
        </div>

        <div class="video-layout">
            <!-- Player Principal -->
            <div class="video-main">
                <div class="video-player">
                    <iframe 
                        id="youtube-player"
                        src="https://www.youtube.com/embed/<?php echo esc($video['youtube_id']); ?>?enablejsapi=1&rel=0" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>

                <div class="video-info">
                    <h1 class="video-title-main"><?php echo esc($video['title']); ?></h1>
                    
                    <div style="display: flex; gap: 20px; color: #666; font-size: 14px; padding: 15px 0; border-bottom: 1px solid #eee;">
                        <?php if($video['duration_seconds']): 
                            $minutes = floor($video['duration_seconds'] / 60);
                            $seconds = $video['duration_seconds'] % 60;
                        ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>⏱️</span>
                                <span><?php echo sprintf('%d:%02d', $minutes, $seconds); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span>✅</span>
                            <span><?php echo count($ucs); ?> tarefas</span>
                        </div>

                        <?php 
                        $totalXp = 0;
                        foreach($ucs as $uc) {
                            $totalXp += $uc['xp_points'];
                        }
                        if($totalXp > 0): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>⭐</span>
                                <span><?php echo $totalXp; ?> XP total</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if($video['description']): ?>
                        <div class="video-description-main">
                            <?php echo nl2br(esc($video['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar de Tarefas -->
            <div class="tasks-sidebar">
                <div class="tasks-header">
                    ✅ Tarefas do Vídeo
                </div>

                <?php 
                // Calcular XPs adquiridas
                $earnedXp = 0;
                $completedCount = 0;
                foreach($ucs as $uc) {
                    if(isset($uc['completed']) && $uc['completed']) {
                        $earnedXp += $uc['xp_points'];
                        $completedCount++;
                    }
                }
                $xpPercentage = $totalXp > 0 ? round(($earnedXp / $totalXp) * 100) : 0;
                $isCompleted = $xpPercentage === 100;
                ?>

                <?php if($totalXp > 0): ?>
                    <div class="xp-earned-panel" id="xp-earned-panel">
                        <h3>⭐ Seus XPs</h3>
                        <div class="xp-progress">
                            <div class="xp-progress-bar">
                                <div class="xp-progress-fill" style="width: <?php echo $xpPercentage; ?>%;">
                                    <?php if($xpPercentage > 5): ?><?php echo $xpPercentage; ?>%<?php endif; ?>
                                </div>
                            </div>
                            <div class="xp-progress-value"><?php echo $earnedXp; ?>/<?php echo $totalXp; ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="xp-total">
                    ⭐ <?php echo $totalXp; ?> XP Disponíveis
                </div>

                <?php if(empty($ucs)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #999;">
                        <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
                        <p>Nenhuma tarefa para este vídeo</p>
                    </div>
                <?php else: ?>
                    <?php foreach($ucs as $uc): 
                        $isCompleted = isset($uc['completed']) && $uc['completed'];
                    ?>
                        <div class="task-card <?php echo $isCompleted ? 'completed' : ''; ?>" data-uc-id="<?php echo $uc['id']; ?>">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="task-number"><?php echo $isCompleted ? '✓' : $uc['task_number']; ?></span>
                                <div style="flex: 1;">
                                    <h3 class="task-title"><?php echo esc($uc['task_title']); ?></h3>
                                </div>
                            </div>

                            <?php if($uc['task_description']): ?>
                                <p class="task-description">
                                    <?php echo nl2br(esc($uc['task_description'])); ?>
                                </p>
                            <?php endif; ?>

                            <div class="task-meta">
                                <?php if($uc['video_checkpoint']): ?>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span>⏱️</span>
                                        <span><?php echo esc($uc['video_checkpoint']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>⭐</span>
                                    <span><?php echo esc($uc['xp_points']); ?> XP</span>
                                </div>
                            </div>

                            <?php if($uc['external_url']): ?>
                                <div style="margin-top: 15px;">
                                    <a href="<?php echo esc($uc['external_url']); ?>" 
                                       target="_blank"
                                       class="external-link-button"
                                       style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s;">
                                        🔗 Acessar Interface Externa
                                        <span style="font-size: 12px;">↗</span>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if(isset($_SESSION['id_usuario_logado'])): ?>
                                <div class="task-checkbox">
                                    <label>
                                        <input 
                                            type="checkbox" 
                                            class="uc-checkbox"
                                            data-uc-id="<?php echo $uc['id']; ?>"
                                            data-xp="<?php echo $uc['xp_points']; ?>"
                                            <?php echo $isCompleted ? 'checked' : ''; ?>>
                                        <span><?php echo $isCompleted ? 'Tarefa Concluída! 🎉' : 'Marcar como concluída'; ?></span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Inicializar painel de XPs e verificar se 100% completo
    updateXpPanel();
    
    // Tracking de progresso de UC
    $('.uc-checkbox').on('change', function() {
        var ucId = $(this).data('uc-id');
        var xpPoints = $(this).data('xp');
        var isCompleted = $(this).is(':checked');
        var $card = $(this).closest('.task-card');

        $.ajax({
            url: '<?php echo site_url('api/uc-progress'); ?>',
            type: 'POST',
            data: {
                uc_definition_id: ucId,
                completed: isCompleted ? 1 : 0,
                video_id: <?php echo $video['id']; ?>
            },
            dataType: 'json',
            success: function(result) {
                console.log('[UCProgress] Resposta do servidor:', result);
                if (result.status === 'success') {
                    if (isCompleted) {
                        $card.addClass('completed');
                        $card.find('.task-number').text('✓');
                        $card.find('.task-checkbox span').text('Tarefa Concluída! 🎉');
                        
                        // Mostrar notificação de XP ganho
                        showXpNotification(xpPoints);
                        
                        // Atualizar painel de XPs e mudar cor se 100%
                        updateXpPanel();
                    } else {
                        $card.removeClass('completed');
                        var taskNumber = $card.find('.uc-checkbox').closest('.task-card').index() + 1;
                        $card.find('.task-number').text(taskNumber);
                        $card.find('.task-checkbox span').text('Marcar como concluída');
                        
                        // Atualizar painel de XPs
                        updateXpPanel();
                    }
                } else {
                    console.error('[UCProgress] Erro do servidor:', result.message);
                    alert('❌ Erro ao atualizar progresso: ' + (result.message || 'Tente novamente.'));
                    // Reverter checkbox
                    $(this).prop('checked', !isCompleted);
                }
            }.bind(this),
            error: function(xhr, status, error) {
                console.error('[UCProgress] Erro na requisição:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    ajaxStatus: status,
                    error: error
                });
                var errorMsg = 'Erro ao atualizar progresso';
                if (xhr.status === 401) {
                    errorMsg = 'Sessão expirada. Por favor, faça login novamente.';
                } else if (xhr.status === 422) {
                    errorMsg = 'Dados inválidos enviados.';
                } else if (xhr.status === 500) {
                    errorMsg = 'Erro no servidor. Tente novamente mais tarde.';
                } else if (status === 'timeout') {
                    errorMsg = 'Requisição expirou. Tente novamente.';
                }
                alert('❌ ' + errorMsg);
                $(this).prop('checked', !isCompleted);
            }.bind(this)
        });
    });

    function updateXpPanel() {
        // Calcular XPs adquiridos
        var totalXp = 0;
        var earnedXp = 0;
        
        $('.uc-checkbox').each(function() {
            var xpPoints = $(this).data('xp');
            totalXp += xpPoints;
            
            if($(this).is(':checked')) {
                earnedXp += xpPoints;
            }
        });
        
        var xpPercentage = totalXp > 0 ? Math.round((earnedXp / totalXp) * 100) : 0;
        
        // Atualizar painel de XPs
        var $panel = $('#xp-earned-panel');
        if($panel.length > 0) {
            $panel.find('.xp-progress-fill').css('width', xpPercentage + '%');
            $panel.find('.xp-progress-fill').html(xpPercentage > 5 ? xpPercentage + '%' : '');
            $panel.find('.xp-progress-value').text(earnedXp + '/' + totalXp);
        }
        
        // Verificar se está 100% completo
        var $videoMain = $('.video-main');
        if(xpPercentage === 100 && totalXp > 0) {
            $videoMain.addClass('completed-video');
        } else {
            $videoMain.removeClass('completed-video');
        }
    }

    function showXpNotification(xp) {
        var notification = $('<div>')
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: 'linear-gradient(135deg, #ffd700 0%, #ffed4e 100%)',
                color: '#333',
                padding: '20px 30px',
                borderRadius: '12px',
                fontSize: '18px',
                fontWeight: 'bold',
                boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
                zIndex: 9999,
                animation: 'slideInRight 0.3s ease-out'
            })
            .html('🎉 +' + xp + ' XP!')
            .appendTo('body');

        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Tracking de progresso de vídeo (YouTube API)
    var player;
    var videoId = <?php echo $video['id']; ?>;
    var videoDuration = <?php echo $video['duration_seconds'] ?? 0; ?>;
    var lastSavedPosition = 0;

    // Carregar YouTube IFrame API
    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    window.onYouTubeIframeAPIReady = function() {
        player = new YT.Player('youtube-player', {
            events: {
                'onStateChange': onPlayerStateChange
            }
        });
    };

    function onPlayerStateChange(event) {
        // Salvar progresso a cada 5 segundos enquanto estiver tocando
        if (event.data == YT.PlayerState.PLAYING) {
            setInterval(function() {
                if (player && player.getCurrentTime) {
                    saveVideoProgress();
                }
            }, 5000);
        }
    }

    function saveVideoProgress() {
        if (!player) return;

        var currentTime = Math.floor(player.getCurrentTime());
        var duration = player.getDuration();
        var percent = Math.floor((currentTime / duration) * 100);

        // Só salvar se houver mudança significativa (mais de 3 segundos)
        if (Math.abs(currentTime - lastSavedPosition) < 3) return;

        lastSavedPosition = currentTime;

        $.ajax({
            url: '<?php echo site_url('api/video-progress'); ?>',
            type: 'POST',
            data: {
                video_id: videoId,
                watched_seconds: currentTime,
                total_seconds: duration,
                percent: percent,
                completed: percent >= 90 ? 1 : 0
            }
        });
    }
});
</script>

<style>
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<?php require VIEWPATH.'/footer.php'; ?>
