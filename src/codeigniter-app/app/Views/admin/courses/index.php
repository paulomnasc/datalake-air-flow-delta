<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">📚 Gerenciamento de Cursos (Admin)</h4>

        <input type="text" id="filtro-nome" placeholder="Filtrar por nome do curso">
        <img src="../assets/img/lupa.jpg">
        
        <form action="<?php echo site_url('admin/courses/add'); ?>" method="post">
            <button type="submit" class="add-button">➕ Novo Curso</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course ID</th>
                    <th style="width: 50px;">Cor</th>
                    <th>Nome</th>
                    <th>Ativo</th>
                    <th>Ordem</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($courses)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">
                        Nenhum curso cadastrado. Clique em "Novo Curso" para começar.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($courses as $course): ?>
                    <tr id="row-<?php echo $course['id']; ?>">
                        <td><?php echo $course['id']; ?></td>
                        <td><code><?php echo esc($course['course_id']); ?></code></td>
                        <td>
                            <div style="width: 30px; height: 30px; background-color: <?php echo esc($course['color'] ?? '#cccccc'); ?>; border: 1px solid #999; border-radius: 4px;"></div>
                        </td>
                        <td><strong><?php echo esc($course['name']); ?></strong></td>
                        <td><?php echo $course['is_active'] ? '✅ Sim' : '❌ Não'; ?></td>
                        <td><?php echo $course['order']; ?></td>
                        <td>
                            <div class="sidebyside-container">
                                <!-- Ver Módulos -->
                                <a href="<?php echo site_url('admin/modules/course/' . $course['id']); ?>" 
                                   class="btn btn-sm btn-info" 
                                   title="Ver Módulos" 
                                   style="padding: 5px 10px; margin-right: 5px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;">
                                    📝 Módulos
                                </a>
                                
                                <!-- Editar Curso -->
                                <form action="<?php echo site_url('admin/courses/edit'); ?>" method="post" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
                                    <button class="edit-button" type="submit" title="Editar Curso">✏️</button>
                                </form>
                                
                                <!-- Deletar Curso -->
                                <form id="deleteForm-<?php echo $course['id']; ?>" style="display: inline;">
                                    <button class="delete-button" type="button" 
                                            onclick="confirmDelete('<?php echo $course['id']; ?>', '<?php echo addslashes($course['name']); ?>', '<?php echo site_url('admin/courses/delete/' . $course['id']); ?>')">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(id, name, deleteUrl) {
    if (confirm("Você tem certeza que deseja deletar o curso: " + name + "?\n\n⚠️ ATENÇÃO: Todos os módulos, vídeos e UCs deste curso também serão deletados!")) {
        $.ajax({
            url: deleteUrl,
            type: 'POST',
            data: {
                _method: 'DELETE'
            },
            success: function(result) {
                if (result.status === 'success') {
                    $('#row-' + id).remove();
                    $('#success-message').html('Curso excluído com sucesso.').show().delay(3000).fadeOut();
                    
                    // Recarrega a página se a tabela ficar vazia
                    if ($('#data-table tbody tr').length === 0) {
                        location.reload();
                    }
                } else {
                    $('#error-message').html('Erro ao excluir o curso: ' + (result.mensagem || 'Erro desconhecido')).show().delay(6000).fadeOut();
                }
            },
            error: function(err) {
                $('#error-message').html('Erro ao excluir o curso.').show().delay(6000).fadeOut();
                console.error(err);
            }
        });
    }
}

// Filtro simples
$(document).ready(function() {
    $('#filtro-nome').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#data-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
