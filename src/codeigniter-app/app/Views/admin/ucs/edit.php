<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">✏️ Editar UC/Tarefa</h4>

        <form id="formEditUC" style="max-width: 600px; margin: 0 auto;">
            <input type="hidden" id="id" name="id" value="<?php echo esc($uc['id']); ?>">
            
            <div class="form-group">
                <label for="video_id">Vídeo*:</label>
                <select id="video_id" name="video_id" class="form-control" required>
                    <option value="">-- Selecione um Vídeo --</option>
                    <?php foreach($videos as $video): ?>
                        <option value="<?php echo $video['id']; ?>" 
                                <?php echo ($uc['video_id'] == $video['id']) ? 'selected' : ''; ?>>
                            <?php echo esc($video['title']); ?> (<?php echo esc($video['video_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="uc_id">ID da UC (único)*:</label>
                <input type="text" id="uc_id" name="uc_id" class="form-control" 
                       value="<?php echo esc($uc['uc_id']); ?>" required 
                       pattern="[a-z0-9\-]+" 
                       title="Apenas letras minúsculas, números e hífen">
                <small>Use apenas letras minúsculas, números e hífen</small>
            </div>

            <div class="form-group">
                <label for="task_number">Número da Tarefa*:</label>
                <input type="number" id="task_number" name="task_number" class="form-control" 
                       value="<?php echo esc($uc['task_number']); ?>" required min="1">
                <small>Número sequencial da tarefa dentro do vídeo</small>
            </div>

            <div class="form-group">
                <label for="task_title">Título da Tarefa*:</label>
                <input type="text" id="task_title" name="task_title" class="form-control" 
                       value="<?php echo esc($uc['task_title']); ?>" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="task_description">Descrição da Tarefa:</label>
                <textarea id="task_description" name="task_description" class="form-control" 
                          rows="4"><?php echo esc($uc['task_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="video_checkpoint">Checkpoint no Vídeo (opcional):</label>
                <input type="text" id="video_checkpoint" name="video_checkpoint" class="form-control" 
                       value="<?php echo esc($uc['video_checkpoint'] ?? ''); ?>"
                       placeholder="Ex: 7:25 (7 minutos e 25 segundos)" 
                       pattern="[0-9]{1,2}:[0-5][0-9]" 
                       title="Formato MM:SS">
                <small>Momento do vídeo onde a tarefa aparece. Formato: MM:SS (ex: 7:25)</small>
            </div>

            <div class="form-group">
                <label for="external_url">🔗 URL ou Rota Externa (opcional):</label>
                <input type="url" id="external_url" name="external_url" class="form-control" 
                       value="<?php echo esc($uc['external_url'] ?? ''); ?>"
                       placeholder="Ex: https://example.com/quiz ou /dashboard">
                <small>Se preenchido, a tarefa exibirá um botão que redireciona para esta URL. Pode ser uma URL completa (https://...) ou uma rota interna (/pagina)</small>
            </div>

            <div class="form-group">
                <label for="xp_points">Pontos de XP*:</label>
                <input type="number" id="xp_points" name="xp_points" class="form-control" 
                       value="<?php echo esc($uc['xp_points'] ?? 100); ?>" required min="0">
                <small>Pontos de experiência que o aluno ganha ao completar esta tarefa</small>
            </div>

            <div class="form-group">
                <label for="order">Ordem de Exibição:</label>
                <input type="number" id="order" name="order" class="form-control" 
                       value="<?php echo esc($uc['order'] ?? 0); ?>" min="0">
            </div>

            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" 
                           <?php echo ($uc['is_active'] ?? 1) ? 'checked' : ''; ?>> 
                    Tarefa Ativa (visível para alunos)
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="add-button">💾 Atualizar Tarefa</button>
                <a href="<?php echo site_url('admin/ucs/video/' . $uc['video_id']); ?>" 
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
    $('#formEditUC').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            id: $('#id').val(),
            uc_id: $('#uc_id').val(),
            video_id: $('#video_id').val(),
            task_number: $('#task_number').val(),
            task_title: $('#task_title').val(),
            task_description: $('#task_description').val(),
            video_checkpoint: $('#video_checkpoint').val(),
            external_url: $('#external_url').val(),
            xp_points: $('#xp_points').val(),
            order: $('#order').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?php echo site_url('admin/ucs/update'); ?>',
            type: 'POST',
            data: formData,
            success: function(result) {
                if (result.status === 'success') {
                    alert('✅ Tarefa atualizada com sucesso!');
                    var videoId = $('#video_id').val();
                    window.location.href = '<?php echo site_url('admin/ucs'); ?>' + (videoId ? '/video/' + videoId : '');
                } else {
                    var errors = result.errors || {};
                    var errorMsg = result.mensagem || 'Erro ao atualizar tarefa';
                    
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
                alert('❌ Erro ao atualizar tarefa. Verifique os dados e tente novamente.');
                console.error(err);
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
