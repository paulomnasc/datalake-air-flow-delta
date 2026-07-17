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
                <!-- Botão Próxima aula -->
                <div style="margin-top: 28px; display: flex; justify-content: flex-end; gap: 15px;">
                <?php if($next_video): ?>
                    <a href="<?php echo site_url('video/' . $next_video['id']); ?>" class="btn-proxima-aula" style="background: #4f46e5; color: #fff; padding: 12px 28px; border-radius: 6px; font-weight: bold; text-decoration: none; font-size: 18px;">Próxima aula &rarr;</a>
                <?php else: ?>
                    <button class="btn-proxima-aula" style="background: #ccc; color: #fff; padding: 12px 28px; border-radius: 6px; font-weight: bold; font-size: 18px;" disabled>Última aula</button>
                    <a href="<?php echo site_url('modulo/' . $module['id']); ?>" class="btn-proxima-aula" style="background: #10b981; color: #fff; padding: 12px 28px; border-radius: 6px; font-weight: bold; text-decoration: none; font-size: 18px;">Voltar ao Módulo &uarr;</a>
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
    var feedbackShown = false; // Flag para modal de feedback

    // Carregar YouTube IFrame API
    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    window.onYouTubeIframeAPIReady = function() {
        player = new YT.Player('youtube-player', {
            events: {
                'onStateChange': onPlayerStateChange,
                'onPlaybackRateChange': checkVideoProgressForFeedback
            }
        });
    };

    function onPlayerStateChange(event) {
        // Salvar progresso a cada 5 segundos enquanto estiver tocando
        if (event.data == YT.PlayerState.PLAYING) {
            setInterval(function() {
                if (player && player.getCurrentTime) {
                    saveVideoProgress();
                    checkVideoProgressForFeedback(); // Verificar feedback
                }
            }, 2000); // Reduzido para 2s para detectar 80% mais rápido
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
    
    // Função para verificar progresso e mostrar feedback
    function checkVideoProgressForFeedback() {
        if (!player || feedbackShown) return;
        
        try {
            var currentTime = player.getCurrentTime();
            var duration = player.getDuration();
            var percent = (currentTime / duration) * 100;
            
            // Disparar modal ao atingir 80%
            if (percent >= 80) {
                feedbackShown = true;
                showFeedbackModal();
            }
        } catch (e) {
            console.log('Erro ao verificar progresso:', e);
        }
    }
    
    // Funções do modal de feedback
    window.showFeedbackModal = function() {
        document.getElementById('feedback-modal').style.display = 'flex';
    };
    
    window.closeFeedbackModal = function() {
        document.getElementById('feedback-modal').style.display = 'none';
    };
    
    window.submitFeedback = function(event) {
        event.preventDefault();
        
        var labStatus = document.querySelector('input[name="lab_status"]:checked').value;
        var valuePerception = document.querySelector('input[name="value_perception"]:checked').value;
        var openFeedback = document.getElementById('feedback_text').value;
        
        if (!labStatus || !valuePerception) {
            alert('Por favor, responda todas as perguntas obrigatórias.');
            return;
        }
        
        var submitBtn = event.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
        
        $.ajax({
            url: '<?php echo site_url('api/video-feedback'); ?>',
            type: 'POST',
            data: {
                video_id: videoId,
                lab_status: labStatus,
                value_perception: valuePerception,
                open_feedback: openFeedback
            },
            success: function(data) {
                if (data.status === 'success') {
                    alert('✅ Obrigado pelo seu feedback! Vamos usar isso para melhorar.');
                    closeFeedbackModal();
                } else {
                    alert('Erro ao enviar feedback. Tente novamente.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Enviar Feedback';
                }
            },
            error: function(error) {
                console.error('Erro:', error);
                alert('Erro ao enviar feedback. Tente novamente.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar Feedback';
            }
        });
    };
});
</script>

<!-- MODAL DE FEEDBACK 80% DO VÍDEO -->
<div id="feedback-modal" class="feedback-modal" style="display: none;">
    <div class="feedback-modal-content">
        <button type="button" class="feedback-modal-close" onclick="closeFeedbackModal()">✕</button>
        
        <h2 style="margin-top: 0; color: #333; font-size: 24px;">⚡ Rápido! Como está sendo sua experiência?</h2>
        <p style="color: #666; margin-bottom: 25px;">Você já passou pela parte difícil! Ajude-nos a entender melhor sua jornada.</p>
        
        <form id="feedback-form" onsubmit="submitFeedback(event)">
            <!-- Status do Lab -->
            <div class="feedback-section">
                <h3 style="color: #333; margin-bottom: 15px;">Status do Lab:</h3>
                <div class="feedback-option">
                    <input type="radio" id="lab_consegui" name="lab_status" value="consegui_rodar" required>
                    <label for="lab_consegui">Consegui rodar tudo! 🚀</label>
                </div>
                <div class="feedback-option">
                    <input type="radio" id="lab_erro" name="lab_status" value="erro_docker">
                    <label for="lab_erro">Estou com erro no Docker/S3. 🛠️</label>
                </div>
                <div class="feedback-option">
                    <input type="radio" id="lab_assistindo" name="lab_status" value="so_assistindo">
                    <label for="lab_assistindo">Só estou assistindo a teoria por enquanto. 📺</label>
                </div>
            </div>
            
            <!-- Percepção de Valor -->
            <div class="feedback-section" style="margin-top: 25px;">
                <h3 style="color: #333; margin-bottom: 10px;">Percepção de Valor</h3>
                <p style="color: #666; font-size: 14px; margin-bottom: 15px;"><strong>"Você sabia que esse laboratório simula o funcionamento do AWS Glue e Azure Data Factory?"</strong></p>
                
                <div class="feedback-option">
                    <input type="radio" id="valor_sim" name="value_perception" value="sim_sentido" required>
                    <label for="valor_sim">Sim, agora faz mais sentido!</label>
                </div>
                <div class="feedback-option">
                    <input type="radio" id="valor_nao" name="value_perception" value="nao_sabia">
                    <label for="valor_nao">Não sabia, achei que era só ferramenta local.</label>
                </div>
                <div class="feedback-option">
                    <input type="radio" id="valor_nuvem" name="value_perception" value="direto_nuvem">
                    <label for="valor_nuvem">Prefiro aprender direto na nuvem.</label>
                </div>
            </div>
            
            <!-- Campo Aberto -->
            <div class="feedback-section" style="margin-top: 25px;">
                <label for="feedback_text" style="display: block; color: #333; font-weight: 600; margin-bottom: 10px;">
                    Campo Aberto (Opcional):
                </label>
                <p style="color: #666; font-size: 14px; margin-bottom: 10px;">"O que falta para você prosseguir agora?"</p>
                <textarea 
                    id="feedback_text" 
                    name="open_feedback" 
                    placeholder="Sua resposta aqui..." 
                    style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; resize: vertical; min-height: 100px;">
                </textarea>
            </div>
            
            <!-- Botões -->
            <div style="display: flex; gap: 12px; margin-top: 25px; justify-content: flex-end;">
                <button type="button" class="feedback-btn-cancel" onclick="closeFeedbackModal()">Pular</button>
                <button type="submit" class="feedback-btn-submit">Enviar Feedback</button>
            </div>
        </form>
    </div>
</div>

<style>
/* MODAL DE FEEDBACK */
.feedback-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.feedback-modal-content {
    background: white;
    border-radius: 12px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: slideInUp 0.3s ease;
    position: relative;
}

@keyframes slideInUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.feedback-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 5px 10px;
    transition: color 0.2s;
}

.feedback-modal-close:hover {
    color: #333;
}

.feedback-section {
    margin-bottom: 20px;
}

.feedback-option {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.feedback-option input[type="radio"] {
    margin-right: 12px;
    cursor: pointer;
    width: 18px;
    height: 18px;
    accent-color: #4f46e5;
}

.feedback-option label {
    cursor: pointer;
    color: #333;
    margin: 0;
    font-size: 15px;
}

.feedback-option input[type="radio"]:hover + label {
    color: #4f46e5;
}

.feedback-btn-cancel {
    padding: 10px 24px;
    border: 1px solid #ddd;
    background: white;
    color: #666;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.feedback-btn-cancel:hover {
    background: #f5f5f5;
    border-color: #999;
}

.feedback-btn-submit {
    padding: 10px 24px;
    background: #4f46e5;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.feedback-btn-submit:hover {
    background: #3730a3;
    transform: translateY(-2px);
}

.feedback-btn-submit:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

/* Scroll customizado para modal */
.feedback-modal-content::-webkit-scrollbar {
    width: 8px;
}

.feedback-modal-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.feedback-modal-content::-webkit-scrollbar-thumb {
    background: #4f46e5;
    border-radius: 10px;
}

.feedback-modal-content::-webkit-scrollbar-thumb:hover {
    background: #3730a3;
}
</style>

<?php require VIEWPATH.'/footer.php'; ?>
