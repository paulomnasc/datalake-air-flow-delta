<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
    
    <div id="content">        
            <div class="container">
            <h4 style="text-align: center;">Listagem de Usuários</h4>

                <input type="text" id="filtro-nome" placeholder="Buscar...">
            <img src="../assets/img/lupa.jpg" >
            
            <form action="<?php echo site_url('addUsuario'); ?>" method="post">
                <button type="submit" class="add-button">Incluir</button>
            </form>

                <table class="data-table" id="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Perfil Sistema</th>
                            <th>Perfil Comport</th>
                            <th>Pagamento Inicial</th>
                            <th>Status Assinatura</th>
                            <th>Vencimento</th>
                            <th>Ações</th>
                        </tr>
                        
                    </thead>
                    <tbody>
                        
                        <?php foreach($list as $Usuario): ?>
                        <tr>
                            <td> <?php echo $Usuario->id ?> </td>
                            <td> <?php echo $Usuario->nome ?> </td>
                            <td><?php echo isset($Usuario->perfis_descricao) ? $Usuario->perfis_descricao : 'N/A'; ?></td>
                            <td><?php echo isset($Usuario->perfil_comportamental) ? $Usuario->perfil_comportamental : 'Desinteressado'; ?></td>
                            <td><?php echo isset($Usuario->pagamento_inicial) ? $Usuario->pagamento_inicial : 'N/A'; ?></td>
                            <td><?php echo isset($Usuario->status_assinatura) ? $Usuario->status_assinatura : 'N/A'; ?></td>
                            <td><?php echo isset($Usuario->data_vencimento_assinatura) ? $Usuario->data_vencimento_assinatura : '-'; ?></td>
                            <td> 
                                <div class="sidebyside-container">
                                    <form action="<?php echo site_url('updUsuario'); ?>" method="post">
                                        <input type="hidden" name="id" id="id" value="<?php echo $Usuario->id ?>">
                                        <button class="edit-button" onclick="">✏️</button>
                                    </form>
                                    <form id="deleteForm-<?php echo $Usuario->id; ?>">
                                        <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $Usuario->id; ?>', '<?php echo $Usuario->nome; ?>', '<?php echo site_url('deleteUsuario/' . $Usuario->id); ?>', 'deleteForm-<?php echo $Usuario->id; ?>')">🗑️</button>
                                    </form>
                                </div>

                                <script>
                                    function confirmDelete(id, nome, deleteUrl, formId) {
                                        if (confirm("Você tem certeza que deseja deletar este Usuario: " + id + " - " + nome + "?")) {
                                            $.ajax({
                                                url: deleteUrl,
                                                type: 'POST',
                                                data: {
                                                    _method: 'DELETE' // Simula o método DELETE
                                                },
                                                success: function(result) {
                                                    if (result.status === 'success') {
                                                        $('#row-' + id).remove(); // Remove a linha da tabela
                                                        $('#success-message').html('Registro excluído com sucesso.').show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                        
                                                    } else {
                                                        $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                        
                                                    }
                                                    // Recarrega a div de perfis
                                                    $('#main-content').load('<?php echo route_to('listUsuario'); ?> #main-content');
                                                },
                                                error: function(err) {
                                                    $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                        
                                                    console.log(err); // Trate o erro aqui
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
                        dom: 'lrtip', // Oculta a caixa de busca
                        columnDefs: [
                            {
                                targets: [0], // Índice da coluna que queremos ocultar (4ª coluna, começando do 0)
                                visible: true // Torna a coluna invisível
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

        
    </div>

<?php
require VIEWPATH.'/footer.php';
?>


