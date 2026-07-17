<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">➕ Adicionar Novo Curso</h4>

        <form id="formAddCourse" style="max-width: 600px; margin: 0 auto;">
            <div class="form-group">
                <label for="course_id">ID do Curso (único)*:</label>
                <input type="text" id="course_id" name="course_id" class="form-control" 
                       placeholder="Ex: curso-001" required 
                       pattern="[a-z0-9\-]+" 
                       title="Apenas letras minúsculas, números e hífen">
                <small>Use apenas letras minúsculas, números e hífen. Ex: curso-datalake-001</small>
            </div>

            <div class="form-group">
                <label for="name">Nome do Curso*:</label>
                <input type="text" id="name" name="name" class="form-control" 
                       placeholder="Ex: Criando um Data Lake do Zero" required maxlength="255">
            </div>

            <div class="form-group">
                <label for="description">Descrição:</label>
                <textarea id="description" name="description" class="form-control" 
                          rows="4" placeholder="Descreva os objetivos do curso..."></textarea>
            </div>

            <div class="form-group">
                <label for="icon_url">URL do Ícone:</label>
                <input type="url" id="icon_url" name="icon_url" class="form-control" 
                       placeholder="https://example.com/icon.png">
            </div>

            <div class="form-group">
                <label for="color">Cor (Hex)*:</label>
                <input type="color" id="color" name="color" class="form-control" value="#4f46e5">
                <small>Escolha uma cor para representar o curso na interface</small>
            </div>

            <div class="form-group">
                <label for="order">Ordem de Exibição:</label>
                <input type="number" id="order" name="order" class="form-control" value="0" min="0">
            </div>

            <div class="form-group">
                <label for="is_active">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> 
                    Curso Ativo (visível para alunos)
                </label>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="add-button">💾 Salvar Curso</button>
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
    $('#formAddCourse').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            course_id: $('#course_id').val(),
            name: $('#name').val(),
            description: $('#description').val(),
            icon_url: $('#icon_url').val(),
            color: $('#color').val(),
            order: $('#order').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?php echo site_url('admin/courses/insert'); ?>',
            type: 'POST',
            data: formData,
            success: function(result) {
                if (result.status === 'success') {
                    alert('✅ Curso criado com sucesso!');
                    window.location.href = '<?php echo site_url('admin/courses'); ?>';
                } else {
                    var errors = result.errors || {};
                    var errorMsg = result.mensagem || 'Erro ao criar curso';
                    
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
                alert('❌ Erro ao salvar curso. Verifique os dados e tente novamente.');
                console.error(err);
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
