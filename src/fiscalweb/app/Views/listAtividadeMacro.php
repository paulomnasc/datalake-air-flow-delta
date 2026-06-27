<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">Listagem de AtividadeMacro</h4>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center;">
            <input type="text" id="filtro-id_area_atuacao" placeholder="Filtrar..." style="padding: 8px; width: 300px;">
            <img src="../assets/img/lupa.jpg" style="height: 30px;">
            
            <select id="filtro-area-atuacao" style="padding: 8px; width: 300px;">
                <option value="">Todas as Áreas de Atuação</option>
                <?php if(isset($id_area_atuacao_list)): foreach($id_area_atuacao_list as $opt): ?>
                    <option value="<?php echo $opt->id; ?>">
                        <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        
        <form action="<?php echo site_url('addAtividadeMacro'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>IdAreaAtuacao</th><th>Descricao</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    
            <td data-filter="<?php echo $item->id_area_atuacao; ?>">
                <select name="id_area_atuacao" id="id_area_atuacao-<?php echo $item->id ?>">
                    <option value="">Selecione...</option>
                    <?php if(isset($id_area_atuacao_list)): foreach($id_area_atuacao_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php if($opt->id == $item->id_area_atuacao) echo 'selected'; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </td>
<td> <?php echo $item->descricao ?> </td>
                    <td> 
                        <div class="sidebyside-container">
                            <form action="<?php echo site_url('updAtividadeMacro'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $item->id ?>">
                                <button class="edit-button" type="submit">✏️</button>
                            </form>
                            <form id="deleteForm-<?php echo $item->id; ?>">
                                <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $item->id; ?>', '<?php echo site_url('deleteAtividadeMacro/' . $item->id); ?>', 'deleteForm-<?php echo $item->id; ?>')">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
            function confirmDelete(id, deleteUrl, formId) {
                if (confirm("Você tem certeza que deseja deletar este registro?")) {
                    $.ajax({
                        url: deleteUrl,
                        type: 'POST',
                        data: { _method: 'DELETE' },
                        success: function(result) {
                            if (result.status === 'success') {
                                $('#row-' + id).remove();
                                $('#success-message').html(result.mensagem).show().delay(6000).fadeOut();
                            } else {
                                $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut();
                            }
                        },
                        error: function(err) {
                            $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut();
                            console.log(err);
                        }
                    });
                }
            }

            $(document).ready(function() {
                var table = $('#data-table').DataTable({
                    dom: 'lrtip',
                    columnDefs: [{ targets: [0], visible: false }],
                    language: { "sEmptyTable": "Nenhum registro encontrado" }
                });

                $('#filtro-id_area_atuacao').on('keyup', function() {
                    table.search(this.value).draw();
                });

                $('#filtro-area-atuacao').on('change', function() {
                    var val = $(this).val();
                    table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
