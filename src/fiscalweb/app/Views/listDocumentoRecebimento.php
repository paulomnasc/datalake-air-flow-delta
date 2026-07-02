<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container-menor">
        <h4 style="text-align: center;">Listagem de DocumentoRecebimento</h4>
        
        <input type="text" id="filtro-id_os" placeholder="Filtrar">
        <img src="../assets/img/lupa.jpg" >
        
        <form action="<?php echo site_url('addDocumentoRecebimento'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <div class="table-responsive" style="overflow-x: auto; width: 100%; margin-top: 15px;">
        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Contrato</th>
                    <th>Ordem de Serviço (OS)</th>
                    <th>Nup Sei</th>
                    <th>Tipo Documento</th>
                    <th>Data Assinatura</th>
                    <th>Fiscal Técnico</th>
                    <th>Fiscal Requisitante</th>
                    <th>Gestor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    <td> <?php echo esc($item->Numero_Contrato ?? 'Nenhum') ?> </td>
                    <td> <?php echo esc($item->os_nup_sei ?? 'Nenhum') ?> </td>
                    <td> <?php echo esc($item->nup_sei ?? '') ?> </td>
                    <td> <?php echo esc($item->tipo_documento_descricao ?? '') ?> </td>
                    <td> <?php echo !empty($item->Data_Assinatura) ? date('d/m/Y', strtotime($item->Data_Assinatura)) : '' ?> </td>
                    <td> <?php echo esc($item->fiscal_tecnico_nome ?? '') ?> </td>
                    <td> <?php echo esc($item->fiscal_requisitante_nome ?? '') ?> </td>
                    <td> <?php echo esc($item->gestor_nome ?? '') ?> </td>
                    <td> 
                        <div class="sidebyside-container">
                            <form action="<?php echo site_url('updDocumentoRecebimento'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $item->id ?>">
                                <button class="edit-button" type="submit">✏️</button>
                            </form>
                            <form id="deleteForm-<?php echo $item->id; ?>">
                                <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $item->id; ?>', '<?php echo site_url('deleteDocumentoRecebimento/' . $item->id); ?>', 'deleteForm-<?php echo $item->id; ?>')">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

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

                $('#filtro-id_os').on('keyup', function() {
                    table.search(this.value).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
