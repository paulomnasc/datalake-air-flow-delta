<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">Listagem de Servico</h4>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="text" id="filtro-remuneracao" placeholder="Filtrar por texto..." style="padding: 8px; width: 300px;">
            
            <select id="filtro-macro" style="padding: 8px; width: 300px;">
                <option value="">Todas as Atividades Macro</option>
                <?php if(isset($id_atividade_macro_list)): foreach($id_atividade_macro_list as $opt): ?>
                    <option value="<?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>">
                        <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        
        <form action="<?php echo site_url('addServico'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Atividade Macro</th>
                    <th>Serviço (Descrição)</th>
                    <th>Nº Item</th>
                    <th>Remuneração</th>
                    <th>Base Horas Mês</th>
                    <th>Base Horas Cmplx</th>
                    <th>SLA Dias</th>
                    <th>Estim Max Ano</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    <td> <?php echo isset($item->desc_macro) ? $item->desc_macro : $item->id_atividade_macro; ?> </td>
                    <td> <?php echo isset($item->descricao) ? $item->descricao : ''; ?> </td>
                    <td> <?php echo isset($item->numero_item) ? $item->numero_item : ''; ?> </td>
                    <td> R$ <?php echo number_format((float)$item->remuneracao, 2, ',', '.') ?> </td>
                    <td> <?php echo $item->base_horas_mes ?> </td>
                    <td> <?php echo $item->base_horas_complexidade ?> </td>
                    <td> <?php echo $item->sla_dias ?> </td>
                    <td> R$ <?php echo number_format((float)$item->estim_max_ano, 2, ',', '.') ?> </td>
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

                $('#filtro-macro').on('change', function() {
                    var val = $.fn.dataTable.util.escapeRegex($(this).val());
                    table.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
