<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">Listagem de OrdemServico</h4>
        
        <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 5px;">
                <input type="text" id="filtro-geral" placeholder="Filtrar...">
                <img src="../assets/img/lupa.jpg" >
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <label for="filtro-sistema" style="font-weight: 600; margin: 0;">Sistema:</label>
                <select id="filtro-sistema" style="padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 200px; background-color: #fff;">
                    <option value="">Todos os Sistemas</option>
                    <?php if(isset($sistemas_list)): foreach($sistemas_list as $sys): ?>
                        <option value="<?php echo esc($sys->descricao); ?>"><?php echo esc($sys->descricao); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </div>
        
        <form action="<?php echo site_url('addOrdemServico'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Contrato</th>
                    <th>Sistema</th>
                    <th>NupSei</th><th>DataEmissao</th><th>DataAceite</th><th>Valor Total (R$)</th><th>Status</th>
                    <th>Clone</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    <td> <?php echo esc($item->Numero_Contrato ?? 'Nenhum') ?> </td>
                    <td> <?php echo esc($item->Nome_Sistema ?? 'Nenhum') ?> </td>
                    <td> <?php echo $item->nup_sei ?> </td><td> <?php echo $item->Data_Emissao ?> </td><td> <?php echo $item->Data_Aceite ?> </td><td> R$ <?php echo number_format($item->valor_total ?? 0, 2, ',', '.'); ?> </td><td> <?php echo esc($item->status ?? 'Rascunho') ?> </td>
                    <td>
                        <form action="<?php echo site_url('cloneOrdemServico/' . $item->id); ?>" method="post">
                            <button class="clone-button" type="submit" title="Clonar (Duplicar como Rascunho)">📋</button>
                        </form>
                    </td>
                    <td> 
                        <div class="sidebyside-container">
                            <form action="<?php echo site_url('updOrdemServico'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $item->id ?>">
                                <button class="edit-button" type="submit">✏️</button>
                            </form>
                            <form id="deleteForm-<?php echo $item->id; ?>">
                                <?php 
                                    $statusNorm = strtolower(trim($item->status ?? 'Rascunho'));
                                    $canDelete = in_array($statusNorm, ['rascunho', 'aguardando assinatura'], true);
                                ?>
                                <button class="delete-button" type="button" 
                                    <?php if ($canDelete): ?>
                                        onclick="confirmDelete('<?php echo $item->id; ?>', '<?php echo site_url('deleteOrdemServico/' . $item->id); ?>', 'deleteForm-<?php echo $item->id; ?>')"
                                    <?php else: ?>
                                        disabled title="Apenas OS nos status Rascunho ou Aguardando assinatura podem ser excluídas" style="opacity: 0.4; cursor: not-allowed;"
                                    <?php endif; ?>>🗑️</button>
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
                                $('#error-message').html(result.mensagem || 'Erro ao excluir o registro.').show().delay(6000).fadeOut();
                            }
                        },
                        error: function(err) {
                            var msg = (err.responseJSON && err.responseJSON.mensagem) ? err.responseJSON.mensagem : 'Erro ao excluir o registro.';
                            $('#error-message').html(msg).show().delay(6000).fadeOut();
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

                $('#filtro-geral').on('keyup', function() {
                    table.search(this.value).draw();
                });

                $('#filtro-sistema').on('change', function() {
                    var val = $.fn.dataTable.util.escapeRegex($(this).val());
                    table.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
