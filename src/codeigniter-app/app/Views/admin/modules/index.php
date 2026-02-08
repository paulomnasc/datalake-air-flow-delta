<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">📝 Gerenciamento de Módulos (Admin)</h4>

        <?php if($course): ?>
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin-bottom: 20px;">
                <strong>Curso:</strong> <?php echo esc($course['name']); ?> 
                <span style="margin-left: 20px; background: <?php echo esc($course['color']); ?>; color: white; padding: 3px 10px; border-radius: 3px;"><?php echo esc($course['course_id']); ?></span>
                <a href="<?php echo site_url('admin/courses'); ?>" style="float: right; color: #2196F3;">← Voltar para Cursos</a>
            </div>
        <?php endif; ?>

        <input type="text" id="filtro-nome" placeholder="Filtrar por nome do módulo">
        <img src="../assets/img/lupa.jpg">
        
        <form action="<?php echo site_url('admin/modules/add' . ($course ? '/' . $course['id'] : '')); ?>" method="post">
            <button type="submit" class="add-button">➕ Novo Módulo</button>
        </form>

        <?php if (!$course): ?>
        <div class="form-group" style="margin: 20px 0;">
            <label for="filter-course">Filtrar por Curso:</label>
            <select id="filter-course" class="form-control" style="max-width: 300px;">
                <option value="">-- Todos os Cursos --</option>
                <?php foreach($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo esc($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Module ID</th>
                    <th>Nº</th>
                    <th>Nome</th>
                    <?php if(!$course): ?>
                    <th>Curso</th>
                    <?php endif; ?>
                    <th>Horas Estimadas</th>
                    <th>Ativo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($modules)): ?>
                <tr>
                    <td colspan="<?php echo $course ? 7 : 8; ?>" style="text-align: center; padding: 20px;">
                        Nenhum módulo cadastrado.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach($modules as $module): ?>
                    <tr id="row-<?php echo $module['id']; ?>">
                        <td><?php echo $module['id']; ?></td>
                        <td><code><?php echo esc($module['module_id']); ?></code></td>
                        <td><?php echo $module['module_number']; ?></td>
                        <td><strong><?php echo esc($module['name']); ?></strong></td>
                        <?php if(!$course): ?>
                        <td>
                            <?php 
                            $moduleCourse = array_values(array_filter($courses, function($c) use ($module) {
                                return $c['id'] == $module['course_id'];
                            }))[0] ?? null;
                            echo $moduleCourse ? esc($moduleCourse['name']) : 'N/A';
                            ?>
                        </td>
                        <?php endif; ?>
                        <td><?php echo $module['estimated_hours'] ?? '-'; ?>h</td>
                        <td><?php echo $module['is_active'] ? '✅' : '❌'; ?></td>
                        <td>
                            <div class="sidebyside-container">
                                <a href="<?php echo site_url('admin/videos/module/' . $module['id']); ?>" 
                                   class="btn btn-sm btn-info" title="Ver Vídeos"
                                   style="padding: 5px 10px; margin-right: 5px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;">
                                    🎥 Vídeos
                                </a>
                                <form action="<?php echo site_url('admin/modules/edit'); ?>" method="post" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $module['id']; ?>">
                                    <button class="edit-button" type="submit" title="Editar">✏️</button>
                                </form>
                                <form id="deleteForm-<?php echo $module['id']; ?>" style="display: inline;">
                                    <button class="delete-button" type="button" 
                                            onclick="confirmDeleteModule('<?php echo $module['id']; ?>', '<?php echo addslashes($module['name']); ?>', '<?php echo site_url('admin/modules/delete/' . $module['id']); ?>')">
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
function confirmDeleteModule(id, name, deleteUrl) {
    if (confirm("Deletar módulo: " + name + "?\n\n⚠️ Todos os vídeos e UCs serão deletados!")) {
        $.ajax({
            url: deleteUrl,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function(result) {
                if (result.status === 'success') {
                    $('#row-' + id).remove();
                    $('#success-message').html('Módulo excluído.').show().delay(3000).fadeOut();
                } else {
                    alert('Erro: ' + (result.mensagem || 'Erro desconhecido'));
                }
            },
            error: function(err) {
                alert('Erro ao excluir módulo.');
                console.error(err);
            }
        });
    }
}

$(document).ready(function() {
    $('#filtro-nome').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#data-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    $('#filter-course').on('change', function() {
        var courseId = $(this).val();
        if (courseId) {
            window.location.href = '<?php echo site_url('admin/modules/course/'); ?>' + courseId;
        } else {
            window.location.href = '<?php echo site_url('admin/modules'); ?>';
        }
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>
