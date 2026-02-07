<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">✏️ Editar Curso</h4>

        <form id="formEditCourse" style="max-width: 600px; margin: 0 auto;">
            <input type="hidden" id="id" name="id" value="<?php echo esc($course['id']); ?>">
            
            <div class="form-group">
                <label for="course_id">ID do Curso (único)*:</label>
                  <input type="text" id="course_id" name="course_id" class="form-control" 
                      value="<?php echo esc($course['course_id']); ?>" required 
                      pattern="[a-z0-9\-]+" 
                      title="Apenas letras minúsculas, números e hífen" readonly>
                  <small>Este campo não pode ser alterado ao editar. Use apenas letras minúsculas, números e hífen ao criar.</small>
            </div>

            <div class="form-group">
                <label for="name">Nome do Curso*:</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?php echo esc($course['name']); ?>" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="description">Descrição:</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="4"><?php echo esc($course['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="icon_url">URL do Ícone:</label>
                <input type="url" id="icon_url" name="icon_url" class="form-control" 
                       value="<?php echo esc($course['icon_url'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="color">Cor (Hex)*:</label>
                <input type="color" id="color" name="color" class="form-control" 
                       value="<?php echo esc($course['color'] ?? '#4f46e5'); ?>">
            </div>

            <div class="form-group">
                <label for="order">Ordem de Exibição:</label>
                <input type="number" id="order" name="order" class="form-control" 
                       value="<?php echo esc($course['order'] ?? 0); ?>" min="0">
            </div>

            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" 
                           <?php echo ($course['is_active'] ?? 1) ? 'checked' : ''; ?>> 
                    Curso Ativo (visível para alunos)
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="add-button">💾 Atualizar Curso</button>
                <a href="<?php echo site_url('admin/courses'); ?>" class="btn btn-secondary" 
                   style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;">
                    ❌ Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#formEditCourse').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            id: $('#id').val(),
            course_id: $('#course_id').val(),
            name: $('#name').val(),
            description: $('#description').val(),
            icon_url: $('#icon_url').val(),
            color: $('#color').val(),
            order: $('#order').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?php echo site_url('admin/courses/update'); ?>',
            type: 'POST',
            data: formData,
            success: function(result) {
                if (result.status === 'success') {
                    alert('✅ Curso atualizado com sucesso!');
                    window.location.href = '<?php echo site_url('admin/courses'); ?>';
                } else {
                    var errors = result.errors || {};
                    var errorMsg = result.mensagem || 'Erro ao atualizar curso';
                    
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
                alert('❌ Erro ao atualizar curso. Verifique os dados e tente novamente.');
                console.error(err);
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
