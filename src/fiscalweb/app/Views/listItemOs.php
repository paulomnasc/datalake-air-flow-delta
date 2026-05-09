<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">Listagem de ItemOs</h4>
        
        <input type="text" id="filtro-Quantidade_Horas" placeholder="Filtrar">
        <img src="../assets/img/lupa.jpg" >
        
        <form action="<?php echo site_url('addItemOs'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>QuantidadeHoras</th><th>ProfissionalAlocado</th><th>IdOS</th><th>IdServico</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    <td> <?php echo $item->Quantidade_Horas ?> </td><td> <?php echo $item->Profissional_Alocado ?> </td>
                    <td>
                        <?php
                            $osLabel = '-';
                            if (!empty($item->id_os) && isset($id_os_list)) {
                                foreach ($id_os_list as $opt) {
                                    if ($opt->id == $item->id_os) {
                                        $osLabel = isset($opt->descricao) ? $opt->descricao : (isset($opt->nup_sei) ? $opt->nup_sei : $opt->id);
                                        break;
                                    }
                                }
                            }
                            echo $osLabel;
                        ?>
                    </td>
            <td>
                <select name="id_servico" id="id_servico-<?php echo $item->id ?>">
                    <option value="">Selecione...</option>
                    <?php if(isset($id_servico_list)): foreach($id_servico_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php if($opt->id == $item->id_servico) echo 'selected'; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </td>

                    <td> 
                        <div class="sidebyside-container">
                            <form action="<?php echo site_url('updItemOs'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $item->id ?>">
                                <button class="edit-button" type="submit">✏️</button>
                            </form>
                            <form id="deleteForm-<?php echo $item->id; ?>">
                                <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $item->id; ?>', '<?php echo site_url('deleteItemOs/' . $item->id); ?>', 'deleteForm-<?php echo $item->id; ?>')">🗑️</button>
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

                $('#filtro-Quantidade_Horas').on('keyup', function() {
                    table.search(this.value).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
