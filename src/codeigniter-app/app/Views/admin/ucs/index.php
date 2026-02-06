<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">✅ Gerenciamento de UCs/Tarefas (Admin)</h4>

        <?php if($video): ?>
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin-bottom: 20px;">
                <strong>Vídeo:</strong> <?php echo esc($video['title']); ?> 
                <span style="margin-left: 20px; background: #ff0000; color: white; padding: 3px 10px; border-radius: 3px;">🎬 <?php echo esc($video['video_id']); ?></span>
                <br>
                <small style="color: #666;">
                    <a href="https://www.youtube.com/watch?v=<?php echo esc($video['youtube_id']); ?>" target="_blank" style="color: #ff0000;">
                        ▶️ Assistir no YouTube
                    </a>
                </small>
                <a href="<?php echo site_url('admin/videos'); ?>" style="float: right; color: #2196F3;">← Voltar para Vídeos</a>
            </div>
        <?php endif; ?>

        <input type="text" id="filtro-nome" placeholder="Filtrar por título da tarefa">
        <img src="../assets/img/lupa.jpg">
        
        <form action="<?php echo site_url('admin/ucs/add' . ($video ? '/' . $video['id'] : '')); ?>" method="post">
            <button type="submit" class="add-button">➕ Nova Tarefa</button>
        </form>

        <?php if (!$video): ?>
        <div class="form-group" style="margin: 20px 0;">
            <label for="filter-video">Filtrar por Vídeo:</label>
            <select id="filter-video" class="form-control" style="max-width: 300px;">
                <option value="">-- Todos os Vídeos --</option>
                <?php foreach($videos as $v): ?>
                    <option value="<?php echo $v['id']; ?>"><?php echo esc($v['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>UC ID</th>
                    <th>Nº</th>
                    <th>Título da Tarefa</th>
                    <?php if(!$video): ?>
                    <th>Vídeo</th>
                    <?php endif; ?>
                    <th>Checkpoint</th>
                    <th>XP</th>
                    <th>Ativo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="data-tbody">
                <?php if(empty($ucs)): ?>
                    <tr>
                        <td colspan="<?php echo $video ? '8' : '9'; ?>" style="text-align: center; padding: 40px;">
                            <p style="color: #999; font-size: 16px;">✅ Nenhuma tarefa cadastrada ainda.</p>
                            <p style="color: #666;">Clique em "➕ Nova Tarefa" para começar.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $totalXp = 0;
                    foreach($ucs as $uc): 
                        $totalXp += $uc['xp_points'];
                    ?>
                        <tr data-id="<?php echo $uc['id']; ?>" 
                            <?php if(!$video): ?>data-video-id="<?php echo $uc['video_id']; ?>"<?php endif; ?>>
                            <td><?php echo esc($uc['id']); ?></td>
                            <td><?php echo esc($uc['uc_id']); ?></td>
                            <td>
                                <span style="background: #007bff; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;">
                                    #<?php echo esc($uc['task_number']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo esc($uc['task_title']); ?></strong>
                                <?php if($uc['task_description']): ?>
                                    <br><small style="color: #666;"><?php echo esc(substr($uc['task_description'], 0, 80)) . (strlen($uc['task_description']) > 80 ? '...' : ''); ?></small>
                                <?php endif; ?>
                                <?php if($uc['external_url']): ?>
                                    <br>
                                    <small style="display: inline-flex; align-items: center; gap: 5px; background: #e0e7ff; color: #4f46e5; padding: 3px 8px; border-radius: 3px; margin-top: 5px;">
                                        🔗 Link externo configurado
                                    </small>
                                <?php endif; ?>
                            </td>
                            <?php if(!$video): ?>
                            <td>
                                <?php 
                                // Buscar nome do vídeo
                                $videoTitle = 'N/A';
                                foreach($videos as $v) {
                                    if($v['id'] == $uc['video_id']) {
                                        $videoTitle = $v['title'];
                                        break;
                                    }
                                }
                                echo esc($videoTitle);
                                ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php if($uc['video_checkpoint']): ?>
                                    <span style="background: #ffc107; color: #000; padding: 3px 8px; border-radius: 3px; font-family: monospace;">
                                        ⏱️ <?php echo esc($uc['video_checkpoint']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 3px; font-weight: bold;">
                                    ⭐ <?php echo esc($uc['xp_points']); ?> XP
                                </span>
                            </td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; 
                                             background: <?php echo $uc['is_active'] ? '#d4edda' : '#f8d7da'; ?>; 
                                             color: <?php echo $uc['is_active'] ? '#155724' : '#721c24'; ?>;">
                                    <?php echo $uc['is_active'] ? '✓ Ativo' : '✗ Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <form action="<?php echo site_url('admin/ucs/edit'); ?>" method="post" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $uc['id']; ?>">
                                    <button type="submit" class="edit-button" style="cursor: pointer; padding: 5px 10px; margin-right: 5px;">✏️ Editar</button>
                                </form>
                                <button onclick="deleteUC(<?php echo $uc['id']; ?>, '<?php echo esc(addslashes($uc['task_title'])); ?>')" 
                                        class="delete-button" 
                                        style="cursor: pointer; padding: 5px 10px;">🗑️ Deletar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if($video): ?>
                    <tr style="background: #f0f8ff; font-weight: bold;">
                        <td colspan="5" style="text-align: right; padding: 15px;">
                            <strong>Total de XP deste vídeo:</strong>
                        </td>
                        <td colspan="4" style="text-align: left; padding: 15px;">
                            <span style="background: #28a745; color: white; padding: 5px 15px; border-radius: 3px; font-size: 16px;">
                                ⭐ <?php echo $totalXp; ?> XP
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    // Filtro por nome
    $('#filtro-nome').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#data-tbody tr').each(function() {
            var title = $(this).find('td:eq(3)').text().toLowerCase();
            $(this).toggle(title.indexOf(searchTerm) > -1);
        });
    });

    // Filtro por vídeo (se não estiver filtrado)
    $('#filter-video').on('change', function() {
        var videoId = $(this).val();
        if (videoId === '') {
            $('#data-tbody tr').show();
        } else {
            $('#data-tbody tr').each(function() {
                var rowVideoId = $(this).data('video-id');
                $(this).toggle(rowVideoId == videoId);
            });
        }
    });
});

function deleteUC(id, title) {
    if (!confirm('❌ Tem certeza que deseja deletar a tarefa "' + title + '"?')) {
        return;
    }

    $.ajax({
        url: '<?php echo site_url('admin/ucs/delete'); ?>/' + id,
        type: 'DELETE',
        success: function(result) {
            if (result.status === 'success') {
                alert('✅ Tarefa deletada com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro ao deletar tarefa: ' + (result.mensagem || 'Erro desconhecido'));
            }
        },
        error: function(err) {
            alert('❌ Erro ao deletar tarefa. Tente novamente.');
            console.error(err);
        }
    });
}
</script>

<?php require VIEWPATH.'/footer.php'; ?>
