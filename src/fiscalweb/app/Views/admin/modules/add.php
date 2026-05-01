<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">➕ Adicionar Novo Módulo</h4>

        <?php if(isset($selected_course_id) && $selected_course_id): ?>
            <?php 
            $selectedCourse = null;
            foreach($courses as $c) {
                if($c['id'] == $selected_course_id) {
                    $selectedCourse = $c;
                    break;
                }
            }
            ?>
            <?php if($selectedCourse): ?>
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px auto; max-width: 600px;">
                <strong>Adicionando módulo ao curso:</strong> <?php echo esc($selectedCourse['name']); ?>
                <span style="margin-left: 10px; background: <?php echo esc($selectedCourse['color']); ?>; color: white; padding: 3px 10px; border-radius: 3px;">
                    <?php echo esc($selectedCourse['course_id']); ?>
                </span>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <form id="formAddModule" style="max-width: 600px; margin: 0 auto;">
            <div class="form-group">
                <label for="course_id">Curso*:</label>
                <select id="course_id" name="course_id" class="form-control" required>
                    <option value="">-- Selecione um Curso --</option>
                    <?php foreach($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>" 
                                <?php echo (isset($selected_course_id) && $selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                            <?php echo esc($course['name']); ?> (<?php echo esc($course['course_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="module_id">ID do Módulo (único)*:</label>
                <input type="text" id="module_id" name="module_id" class="form-control" 
                       placeholder="Ex: mod-001" required 
                       pattern="[a-z0-9\-]+" 
                       title="Apenas letras minúsculas, números e hífen">
                <small>Use apenas letras minúsculas, números e hífen. Ex: mod-fundamentos-001</small>
            </div>

            <div class="form-group">
                <label for="name">Nome do Módulo*:</label>
                <input type="text" id="name" name="name" class="form-control" 
                       placeholder="Ex: Fundamentos de Data Lake" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="description">Descrição:</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="4" placeholder="Descreva os objetivos do módulo..."></textarea>
            </div>

            <div class="form-group">
                <label for="module_number">Número do Módulo*:</label>
                <input type="number" id="module_number" name="module_number" class="form-control" 
                       placeholder="Ex: 1" required min="1">
                <small>Número sequencial do módulo dentro do curso</small>
            </div>

            <div class="form-group">
                <label for="estimated_hours">Horas Estimadas:</label>
                <input type="number" id="estimated_hours" name="estimated_hours" class="form-control" 
                       placeholder="Ex: 4.5" step="0.1" min="0">
                <small>Tempo estimado em horas para completar o módulo</small>
            </div>

            <div class="form-group">
                <label for="order">Ordem de Exibição:</label>
                <input type="number" id="order" name="order" class="form-control" value="0" min="0">
            </div>

            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> 
                    Módulo Ativo (visível para alunos)
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="add-button">💾 Salvar Módulo</button>
                <a href="<?php echo site_url('admin/modules' . (isset($selected_course_id) && $selected_course_id ? '/course/' . $selected_course_id : '')); ?>" 
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
    $('#formAddModule').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            module_id: $('#module_id').val(),
            course_id: $('#course_id').val(),
            name: $('#name').val(),
            description: $('#description').val(),
            module_number: $('#module_number').val(),
            order: $('#order').val(),
            estimated_hours: $('#estimated_hours').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?php echo site_url('admin/modules/insert'); ?>',
            type: 'POST',
            data: formData,
            success: function(result) {
                if (result.status === 'success') {
                    alert('✅ Módulo criado com sucesso!');
                    var courseId = $('#course_id').val();
                    window.location.href = '<?php echo site_url('admin/modules'); ?>' + (courseId ? '/course/' + courseId : '');
                } else {
                    var errors = result.errors || {};
                    var errorMsg = result.mensagem || 'Erro ao criar módulo';
                    
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
                alert('❌ Erro ao salvar módulo. Verifique os dados e tente novamente.');
                console.error(err);
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
