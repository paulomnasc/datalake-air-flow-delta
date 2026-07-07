<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
    
    <div id="content">        
        <div class="container">
            <h4 style="text-align: center;">Listagem de Grupos</h4>

            <input type="text" id="filtro-nome" placeholder="Buscar...">
            <img src="../assets/img/lupa.jpg" >
            
            <form action="<?php echo site_url('addGrupo'); ?>" method="post">
                <button type="submit" class="add-button">Incluir</button>
            </form>

            <table class="data-table" id="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail do Grupo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list as $grupo): ?>
                    <tr id="row-<?php echo $grupo->id; ?>">
                        <td> <?php echo $grupo->id ?> </td>
                        <td> <?php echo $grupo->nome ?> </td>
                        <td> <?php echo $grupo->email ?> </td>
                        <td> 
                            <div class="sidebyside-container" style="display: flex; gap: 5px;">
                                <form action="<?php echo site_url('updGrupo'); ?>" method="post" style="margin:0;">
                                    <input type="hidden" name="id" value="<?php echo $grupo->id ?>">
                                    <button class="edit-button" title="Editar">✏️</button>
                                </form>

                                <a href="<?php echo site_url('grupo/membros/' . $grupo->id); ?>" class="edit-button" style="text-decoration: none; display: flex; align-items: center; justify-content: center; padding: 2px 6px;" title="Gerenciar Membros">👥</a>

                                <form id="deleteForm-<?php echo $grupo->id; ?>" style="margin:0;">
                                    <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $grupo->id; ?>', '<?php echo $grupo->nome; ?>', '<?php echo site_url('deleteGrupo/' . $grupo->id); ?>')">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <script>
                function confirmDelete(id, nome, deleteUrl) {
                    if (confirm("Você tem certeza que deseja deletar o grupo: " + id + " - " + nome + "?\nTodas as associações de usuários a este grupo serão perdidas!")) {
                        $.ajax({
                            url: deleteUrl,
                            type: 'POST',
                            data: {
                                _method: 'DELETE'
                            },
                            success: function(result) {
                                if (result.status === 'success') {
                                    $('#row-' + id).remove();
                                    alert(result.mensagem);
                                } else {
                                    alert(result.mensagem);
                                }
                            },
                            error: function(err) {
                                alert('Erro ao excluir o registro.');
                                console.log(err);
                            }
                        });
                    }
                }
            </script>

            <script>
                $(document).ready(function() {
                    var table = $('#data-table').DataTable({
                        dom: 'lrtip',
                        language: {
                            "sEmptyTable": "Nenhum registro encontrado",
                            "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                            "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
                            "sInfoFiltered": "(Filtrados de _MAX_ registros)",
                            "sLengthMenu": "_MENU_ resultados por página",
                            "sZeroRecords": "Nenhum registro encontrado",
                            "oPaginate": {
                                "sNext": "Próximo",
                                "sPrevious": "Anterior"
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

<?php require VIEWPATH.'/footer.php'; ?>
