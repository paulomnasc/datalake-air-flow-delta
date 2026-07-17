<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';

// Converter segundos para MM:SS
$duration_display = '';
if (isset($video['duration_seconds']) && $video['duration_seconds'] > 0) {
    $minutes = floor($video['duration_seconds'] / 60);
    $seconds = $video['duration_seconds'] % 60;
    $duration_display = sprintf('%d:%02d', $minutes, $seconds);
}
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">✏️ Editar Vídeo</h4>

        <form id="formEditVideo" style="max-width: 600px; margin: 0 auto;">
            <input type="hidden" id="id" name="id" value="<?php echo esc($video['id']); ?>">
            
            <div class="form-group">
                <label for="module_id">Módulo*:</label>
                <select id="module_id" name="module_id" class="form-control" required>
                    <option value="">-- Selecione um Módulo --</option>
                    <?php foreach($modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>" 
                                <?php echo ($video['module_id'] == $module['id']) ? 'selected' : ''; ?>>
                            <?php echo esc($module['name']); ?> (<?php echo esc($module['module_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="video_id">ID do Vídeo (único)*:</label>
                <input type="text" id="video_id" name="video_id" class="form-control" 
                       value="<?php echo esc($video['video_id']); ?>" required 
                       pattern="[a-z0-9\-]+" 
                       title="Apenas letras minúsculas, números e hífen">
                <small>Use apenas letras minúsculas, números e hífen</small>
            </div>

            <div class="form-group">
                <label for="youtube_id">YouTube Video ID*:</label>
                <input type="text" id="youtube_id" name="youtube_id" class="form-control" 
                       value="<?php echo esc($video['youtube_id']); ?>" required 
                       pattern="[a-zA-Z0-9_\-]{11}" 
                       title="ID do YouTube com 11 caracteres">
                <small>O ID de 11 caracteres que aparece após "v=" na URL do YouTube</small>
                <div id="youtube-preview" style="margin-top: 10px;">
                    <img id="youtube-thumbnail" 
                         src="<?php echo esc($video['thumbnail_url'] ?? 'https://img.youtube.com/vi/' . $video['youtube_id'] . '/maxresdefault.jpg'); ?>" 
                         alt="Thumbnail" 
                         style="max-width: 200px; border-radius: 4px;">
                </div>
            </div>

            <div class="form-group">
                <label for="title">Título do Vídeo*:</label>
                <input type="text" id="title" name="title" class="form-control" 
                       value="<?php echo esc($video['title']); ?>" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="description">Descrição:</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="4"><?php echo esc($video['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="thumbnail_url">URL da Thumbnail (opcional):</label>
                <input type="url" id="thumbnail_url" name="thumbnail_url" class="form-control" 
                       value="<?php echo esc($video['thumbnail_url'] ?? ''); ?>"
                       placeholder="Deixe em branco para usar a thumbnail do YouTube">
                <small>Se vazio, será usado automaticamente: https://img.youtube.com/vi/{youtube_id}/maxresdefault.jpg</small>
            </div>

            <div class="form-group">
                <label for="duration">Duração do Vídeo:</label>
                <input type="text" id="duration" name="duration" class="form-control" 
                       value="<?php echo esc($duration_display); ?>"
                       placeholder="Ex: 7:25 (7 minutos e 25 segundos)" 
                       pattern="[0-9]{1,2}:[0-5][0-9]" 
                       title="Formato MM:SS">
                <input type="hidden" id="duration_seconds" name="duration_seconds" 
                       value="<?php echo esc($video['duration_seconds'] ?? ''); ?>">
                <small>Formato: MM:SS (minutos:segundos). Ex: 7:25 para 7min25s</small>
            </div>

            <div class="form-group">
                <label for="video_order">Ordem de Exibição:</label>
                <input type="number" id="video_order" name="video_order" class="form-control" 
                       value="<?php echo esc($video['video_order'] ?? 0); ?>" min="0">
            </div>

            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" 
                           <?php echo ($video['is_active'] ?? 1) ? 'checked' : ''; ?>> 
                    Vídeo Ativo (visível para alunos)
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="add-button">💾 Atualizar Vídeo</button>
                <a href="<?php echo site_url('admin/videos/module/' . $video['module_id']); ?>" 
                   class="btn btn-secondary" 
                   style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Preview da thumbnail do YouTube
    $('#youtube_id').on('input', function() {
        var youtubeId = $(this).val().trim();
        if (youtubeId.length === 11) {
            var thumbnailUrl = 'https://img.youtube.com/vi/' + youtubeId + '/maxresdefault.jpg';
            $('#youtube-thumbnail').attr('src', thumbnailUrl);
            
            // Auto-preencher thumbnail_url se estiver vazio
            if (!$('#thumbnail_url').val()) {
                $('#thumbnail_url').val(thumbnailUrl);
            }
        }
    });

    // Converter MM:SS para segundos
    $('#duration').on('blur', function() {
        var duration = $(this).val().trim();
        if (duration.match(/^[0-9]{1,2}:[0-5][0-9]$/)) {
            var parts = duration.split(':');
            var minutes = parseInt(parts[0]);
            var seconds = parseInt(parts[1]);
            var totalSeconds = (minutes * 60) + seconds;
            $('#duration_seconds').val(totalSeconds);
        }
    });

    $('#formEditVideo').on('submit', function(e) {
        e.preventDefault();
        
        // Garantir que duration_seconds foi calculado
        if ($('#duration').val() && !$('#duration_seconds').val()) {
            var duration = $('#duration').val().trim();
            if (duration.match(/^[0-9]{1,2}:[0-5][0-9]$/)) {
                var parts = duration.split(':');
                var totalSeconds = (parseInt(parts[0]) * 60) + parseInt(parts[1]);
                $('#duration_seconds').val(totalSeconds);
            }
        }
        
        var formData = {
            id: $('#id').val(),
            video_id: $('#video_id').val(),
            module_id: $('#module_id').val(),
            youtube_id: $('#youtube_id').val(),
            title: $('#title').val(),
            description: $('#description').val(),
            thumbnail_url: $('#thumbnail_url').val(),
            duration_seconds: $('#duration_seconds').val(),
            video_order: $('#video_order').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?php echo site_url('admin/videos/update'); ?>',
            type: 'POST',
            data: formData,
            success: function(result) {
                if (result.status === 'success') {
                    alert('✅ Vídeo atualizado com sucesso!');
                    var moduleId = $('#module_id').val();
                    window.location.href = '<?php echo site_url('admin/videos'); ?>' + (moduleId ? '/module/' + moduleId : '');
                } else {
                    var errors = result.errors || {};
                    var errorMsg = result.mensagem || 'Erro ao atualizar vídeo';
                    
                    if (Object.keys(errors).length > 0) {
                        errorMsg += ':\n';
                        for (var field in errors) {
                            errorMsg += '- ' + errors[field] + '\n';
                        }
                    }
                    
                    alert('❌ ' + errorMsg);
                }
            },
            error: function(err) {
                alert('❌ Erro ao atualizar vídeo. Verifique os dados e tente novamente.');
                console.error(err);
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
