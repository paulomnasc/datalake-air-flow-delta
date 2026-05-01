<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">🎬 Gerenciamento de Vídeos (Admin)</h4>

        <?php if($module): ?>
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin-bottom: 20px;">
                <strong>Módulo:</strong> <?php echo esc($module['name']); ?> 
                <span style="margin-left: 20px; background: #2196F3; color: white; padding: 3px 10px; border-radius: 3px;"><?php echo esc($module['module_id']); ?></span>
                <a href="<?php echo site_url('admin/modules'); ?>" style="float: right; color: #2196F3;">← Voltar para Módulos</a>
            </div>
        <?php endif; ?>

        <input type="text" id="filtro-nome" placeholder="Filtrar por título do vídeo">
        <img src="../assets/img/lupa.jpg">
        
        <form action="<?php echo site_url('admin/videos/add' . ($module ? '/' . $module['id'] : '')); ?>" method="post">
            <button type="submit" class="add-button">➕ Novo Vídeo</button>
        </form>

        <?php if (!$module): ?>
        <div class="form-group" style="margin: 20px 0;">
            <label for="filter-module">Filtrar por Módulo:</label>
            <select id="filter-module" class="form-control" style="max-width: 300px;">
                <option value="">-- Todos os Módulos --</option>
                <?php foreach($modules as $m): ?>
                    <option value="<?php echo $m['id']; ?>"><?php echo esc($m['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Thumbnail</th>
                    <th>Video ID</th>
                    <th>Título</th>
                    <?php if(!$module): ?>
                    <th>Módulo</th>
                    <?php endif; ?>
                    <th>YouTube ID</th>
                    <th>Duração</th>
                    <th>Ativo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="data-tbody">
                <?php if(empty($videos)): ?>
                    <tr>
                        <td colspan="<?php echo $module ? '8' : '9'; ?>" style="text-align: center; padding: 40px;">
                            <p style="color: #999; font-size: 16px;">📹 Nenhum vídeo cadastrado ainda.</p>
                            <p style="color: #666;">Clique em "➕ Novo Vídeo" para começar.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($videos as $video): ?>
                        <tr data-id="<?php echo $video['id']; ?>" 
                            <?php if(!$module): ?>data-module-id="<?php echo $video['module_id']; ?>"<?php endif; ?>>
                            <td><?php echo esc($video['id']); ?></td>
                            <td>
                                <?php 
                                $thumbnailUrl = $video['thumbnail_url'] ?? 'https://img.youtube.com/vi/' . $video['youtube_id'] . '/default.jpg';
                                ?>
                                <img src="<?php echo esc($thumbnailUrl); ?>" 
                                     alt="Thumbnail" 
                                     style="max-width: 80px; border-radius: 4px;">
                            </td>
                            <td><?php echo esc($video['video_id']); ?></td>
                            <td><?php echo esc($video['title']); ?></td>
                            <?php if(!$module): ?>
                            <td>
                                <?php 
                                // Buscar nome do módulo
                                $moduleName = 'N/A';
                                foreach($modules as $m) {
                                    if($m['id'] == $video['module_id']) {
                                        $moduleName = $m['name'];
                                        break;
                                    }
                                }
                                echo esc($moduleName);
                                ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="https://www.youtube.com/watch?v=<?php echo esc($video['youtube_id']); ?>" 
                                   target="_blank" 
                                   style="color: #ff0000; text-decoration: none;">
                                    <?php echo esc($video['youtube_id']); ?> ▶️
                                </a>
                            </td>
                            <td>
                                <?php 
                                if($video['duration_seconds']) {
                                    $minutes = floor($video['duration_seconds'] / 60);
                                    $seconds = $video['duration_seconds'] % 60;
                                    echo sprintf('%d:%02d', $minutes, $seconds);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; 
                                             background: <?php echo $video['is_active'] ? '#d4edda' : '#f8d7da'; ?>; 
                                             color: <?php echo $video['is_active'] ? '#155724' : '#721c24'; ?>;">
                                    <?php echo $video['is_active'] ? '✓ Ativo' : '✗ Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <form action="<?php echo site_url('admin/videos/edit'); ?>" method="post" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $video['id']; ?>">
                                    <button type="submit" class="edit-button" style="cursor: pointer; padding: 5px 10px; margin-right: 5px;">✏️ Editar</button>
                                </form>
                                <button onclick="deleteVideo(<?php echo $video['id']; ?>, '<?php echo esc(addslashes($video['title'])); ?>')" 
                                        class="delete-button" 
                                        style="cursor: pointer; padding: 5px 10px;">🗑️ Deletar</button>
                                
                                <?php if($module): ?>
                                <form action="<?php echo site_url('admin/ucs/video/' . $video['id']); ?>" method="get" style="display: inline; margin-left: 5px;">
                                    <button type="submit" class="add-button" style="cursor: pointer; padding: 5px 10px; background: #28a745;">✅ UCs/Tarefas</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
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

    // Filtro por módulo (se não estiver filtrado)
    $('#filter-module').on('change', function() {
        var moduleId = $(this).val();
        if (moduleId === '') {
            $('#data-tbody tr').show();
        } else {
            $('#data-tbody tr').each(function() {
                var rowModuleId = $(this).data('module-id');
                $(this).toggle(rowModuleId == moduleId);
            });
        }
    });
});

function deleteVideo(id, title) {
    if (!confirm('❌ Tem certeza que deseja deletar o vídeo "' + title + '"?\n\n⚠️ ATENÇÃO: Isso também deletará todas as UCs/Tarefas associadas a este vídeo!')) {
        return;
    }

    $.ajax({
        url: '<?php echo site_url('admin/videos/delete'); ?>/' + id,
        type: 'DELETE',
        success: function(result) {
            if (result.status === 'success') {
                alert('✅ Vídeo deletado com sucesso!');
                location.reload();
            } else {
                alert('❌ Erro ao deletar vídeo: ' + (result.mensagem || 'Erro desconhecido'));
            }
        },
        error: function(err) {
            alert('❌ Erro ao deletar vídeo. Tente novamente.');
            console.error(err);
        }
    });
}
</script>

<?php require VIEWPATH.'/footer.php'; ?>
