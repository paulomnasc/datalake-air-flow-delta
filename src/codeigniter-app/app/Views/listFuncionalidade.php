<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
        <div id="content">        
            <div class="container">
            <h4 style="text-align: center;">Listagem de Funcionalidades</h4>

                <input type="text" id="filtro-nome" placeholder="Filtrar por descrição">
            <img src="../assets/img/lupa.jpg" >
            
            <form action="<?php echo site_url('addFuncionalidade'); ?>" method="post">
                <button type="submit" class="add-button">Incluir</button>
            </form>

                <table class="data-table" id="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                        
                    </thead>
                    <tbody>
                        
                        <?php foreach($list as $funcionalidade): ?>
                        <tr>
                            <td> <?php echo $funcionalidade->id ?> </td>
                            <td> <?php echo $funcionalidade->descricao ?> </td>
                            <td> 
                                <div class="sidebyside-container">
                                    <form action="<?php echo site_url('updFuncionalidade'); ?>" method="post">
                                        <input type="hidden" name="id" id="id" value="<?php echo $funcionalidade->id ?>">
                                        <button class="edit-button" onclick="">✏️</button>
                                    </form>
                                    <form id="deleteForm-<?php echo $funcionalidade->id; ?>">
                                        <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $funcionalidade->id; ?>', '<?php echo $funcionalidade->descricao; ?>', '<?php echo site_url('deleteFuncionalidade/' . $funcionalidade->id); ?>', 'deleteForm-<?php echo $funcionalidade->id; ?>')">🗑️</button>
                                    </form>
                                </div>

                                <script>
                                    function confirmDelete(id, descricao, deleteUrl, formId) {
                                        if (confirm("Você tem certeza que deseja deletar esta funcionalidade: " + id + " - " + descricao + "?")) {
                                            $.ajax({
                                                url: deleteUrl,
                                                type: 'POST',
                                                data: {
                                                    _method: 'DELETE'
                                                },
                                                success: function(result) {
                                                    if (result.status === 'success') {
                                                        $('#row-' + id).remove();
                                                        $('#success-message').html('Funcionalidade excluída com sucesso.').show().delay(6000).fadeOut();
                        
                                                    } else {
                                                        $('#error-message').html('Erro ao excluir a funcionalidade.').show().delay(6000).fadeOut();
                        
                                                    }
                                                    $('#main-content').load('<?php echo route_to('listFuncionalidade'); ?> #main-content');
                                                },
                                                error: function(err) {
                                                    $('#error-message').html('Erro ao excluir a funcionalidade.').show().delay(6000).fadeOut();
                        
                                                    console.log(err);
                                                }
                                            });
                                        }
                                    }
                                    </script>



                            </td>
                        </tr>

                        <?php endforeach; ?>


                    </tbody>
                </table>


                <script>

                    $(document).ready(function() {
                        var table = $('#data-table').DataTable({
                        dom: 'lrtip',
                        columnDefs: [
                            {
                                targets: [0],
                                visible: false
                            }
                        ],
                        language: {
                            "sEmptyTable": "Nenhum registro encontrado",
                            "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                            "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
                            "sInfoFiltered": "(Filtrados de _MAX_ registros)",
                            "sInfoPostFix": "",
                            "sInfoThousands": ".",
                            "sLengthMenu": "_MENU_ resultados por página",
                            "sLoadingRecords": "Carregando...",
                            "sProcessing": "Processando...",
                            "sZeroRecords": "Nenhum registro encontrado",
                            "sSearch": "Pesquisar",
                            "oPaginate": {
                                "sNext": "Próximo",
                                "sPrevious": "Anterior",
                                "sFirst": "Primeiro",
                                "sLast": "Último"
                            },
                            "oAria": {
                                "sSortAscending": ": Ordenar colunas de forma ascendente",
                                "sSortDescending": ": Ordenar colunas de forma descendente"
                            },
                            "select": {
                                "rows": {
                                    "_": "Selecionado %d linhas",
                                    "0": "Nenhuma linha selecionada",
                                    "1": "Selecionado 1 linha"
                                }
                            },
                            "buttons": {
                                "copy": "Copiar",
                                "csv": "CSV",
                                "excel": "Excel",
                                "pdf": "PDF",
                                "print": "Imprimir",
                                "colvis": "Colunas",
                                "copyTitle": "Cópia para área de transferência",
                                "copySuccess": {
                                    "_": "%d linhas copiadas",
                                    "1": "1 linha copiada"
                                }
                            }
                        }
                    });

                    $('#filtro-nome').on('keyup', function() {
                        table.search(this.value).draw();
                    });
                });
            </script>


</div>

<?php
require VIEWPATH.'/footer.php';
?>
