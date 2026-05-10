<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">Listagem de Servico</h4>
        
        <input type="text" id="filtro-remuneracao" placeholder="Filtrar">
        <img src="../assets/img/lupa.jpg" >
        
        <form action="<?php echo site_url('addServico'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Remuneracao</th><th>BaseHorasMes</th><th>BaseHorasComplexidade</th><th>SlaDias</th><th>EstimMaxAno</th><th>IdAtividadeMacro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    <td> <?php echo $item->remuneracao ?> </td><td> <?php echo $item->base_horas_mes ?> </td><td> <?php echo $item->base_horas_complexidade ?> </td><td> <?php echo $item->sla_dias ?> </td><td> <?php echo $item->estim_max_ano ?> </td>
            <td>
                <select name="id_atividade_macro" id="id_atividade_macro-<?php echo $item->id ?>">
                    <option value="">Selecione...</option>
                    <?php if(isset($id_atividade_macro_list)): foreach($id_atividade_macro_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php if($opt->id == $item->id_atividade_macro) echo 'selected'; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </td>

                    <td> 
                        <div class="sidebyside-container">
                            <form action="<?php echo site_url('updServico'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $item->id ?>">
                                <button class="edit-button" type="submit">✏️</button>
                            </form>
                            <form id="deleteForm-<?php echo $item->id; ?>">
                                <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $item->id; ?>', '<?php echo site_url('deleteServico/' . $item->id); ?>', 'deleteForm-<?php echo $item->id; ?>')">🗑️</button>
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

                $('#filtro-remuneracao').on('keyup', function() {
                    table.search(this.value).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
