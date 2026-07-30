<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">➕ Adicionar Novo Vídeo</h4>

        <?php if(isset($selected_module_id) && $selected_module_id): ?>
            <?php 
            $selectedModule = null;
            foreach($modules as $m) {
                if($m['id'] == $selected_module_id) {
                    $selectedModule = $m;
                    break;
                }
            }
            ?>
            <?php if($selectedModule): ?>
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px auto; max-width: 600px;">
                <strong>Adicionando vídeo ao módulo:</strong> <?php echo esc($selectedModule['name']); ?>
                <span style="margin-left: 10px; background: #2196F3; color: white; padding: 3px 10px; border-radius: 3px;">
                    <?php echo esc($selectedModule['module_id']); ?>
                </span>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <form id="formAddVideo" style="max-width: 600px; margin: 0 auto;">
            <div class="form-group">
                <label for="module_id">Módulo*:</label>
                <select id="module_id" name="module_id" class="form-control" required>
                    <option value="">-- Selecione um Módulo --</option>
                    <?php foreach($modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>" 
                                <?php echo (isset($selected_module_id) && $selected_module_id == $module['id']) ? 'selected' : ''; ?>>
                            <?php echo esc($module['name']); ?> (<?php echo esc($module['module_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="video_id">ID do Vídeo (único)*:</label>
                <input type="text" id="video_id" name="video_id" class="form-control" 
                       placeholder="Ex: vid-001" required 
                       pattern="[a-z0-9\-]+" 
                       title="Apenas letras minúsculas, números e hífen">
                <small>Use apenas letras minúsculas, números e hífen. Ex: vid-fundamentos-001</small>
            </div>

            <div class="form-group">
                <label for="youtube_id">YouTube Video ID*:</label>
                <input type="text" id="youtube_id" name="youtube_id" class="form-control" 
                       placeholder="Ex: dQw4w9WgXcQ" required 
                       pattern="[a-zA-Z0-9_\-]{11}" 
                       title="ID do YouTube com 11 caracteres">
                <small>O ID de 11 caracteres que aparece após "v=" na URL do YouTube. Ex: https://youtube.com/watch?v=<strong>dQw4w9WgXcQ</strong></small>
                <div id="youtube-preview" style="margin-top: 10px; display: none;">
                    <img id="youtube-thumbnail" src="" alt="Thumbnail" style="max-width: 200px; border-radius: 4px;">
                </div>
            </div>

            <div class="form-group">
                <label for="title">Título do Vídeo*:</label>
                <input type="text" id="title" name="title" class="form-control" 
                       placeholder="Ex: Introdução ao Data Lake" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="description">Descrição:</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="4" placeholder="Descreva o conteúdo do vídeo..."></textarea>
            </div>

            <div class="form-group">
                <label for="thumbnail_url">URL da Thumbnail (opcional):</label>
                <input type="url" id="thumbnail_url" name="thumbnail_url" class="form-control" 
                       placeholder="Deixe em branco para usar a thumbnail do YouTube">
                <small>Se vazio, será usado automaticamente: https://img.youtube.com/vi/{youtube_id}/maxresdefault.jpg</small>
            </div>

            <div class="form-group">
                <label for="duration">Duração do Vídeo:</label>
                <input type="text" id="duration" name="duration" class="form-control" 
                       placeholder="Ex: 7:25 (7 minutos e 25 segundos)" 
                       pattern="[0-9]{1,2}:[0-5][0-9]" 
                       title="Formato MM:SS">
                <input type="hidden" id="duration_seconds" name="duration_seconds">
                <small>Formato: MM:SS (minutos:segundos). Ex: 7:25 para 7min25s</small>
            </div>

            <div class="form-group">
                <label for="video_order">Ordem de Exibição:</label>
                <input type="number" id="video_order" name="video_order" class="form-control" value="0" min="0">
            </div>

            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> 
                    Vídeo Ativo (visível para alunos)
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="add-button">💾 Salvar Vídeo</button>
                <a href="<?php echo site_url('admin/videos' . (isset($selected_module_id) && $selected_module_id ? '/module/' . $selected_module_id : '')); ?>" 
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
            $('#youtube-preview').show();
            
            // Auto-preencher thumbnail_url se estiver vazio
            if (!$('#thumbnail_url').val()) {
                $('#thumbnail_url').val(thumbnailUrl);
            }
        } else {
            $('#youtube-preview').hide();
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

    $('#formAddVideo').on('submit', function(e) {
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
            url: '<?php echo site_url('admin/videos/insert'); ?>',
            type: 'POST',
            data: formData,
            success: function(result) {
                if (result.status === 'success') {
                    alert('✅ Vídeo criado com sucesso!');
                    var moduleId = $('#module_id').val();
                    window.location.href = '<?php echo site_url('admin/videos'); ?>' + (moduleId ? '/module/' + moduleId : '');
                } else {
                    var errors = result.errors || {};
                    var errorMsg = result.mensagem || 'Erro ao criar vídeo';
                    
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
                alert('❌ Erro ao salvar vídeo. Verifique os dados e tente novamente.');
                console.error(err);
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
