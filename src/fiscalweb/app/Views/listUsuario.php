<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
    
    <div id="content">        
        <div class="container mt-4">
            <h4 style="text-align: center;">Listagem de Usuários - FiscalWeb</h4>

            <div class="d-flex justify-content-between mb-3">
                <div>
                    <input type="text" id="filtro-nome" class="form-control" placeholder="Buscar..." style="display:inline-block; width:auto;">
                </div>
                <div>
                    <a href="<?php echo site_url('addUsuario'); ?>" class="btn btn-primary add-button">Incluir Usuário</a>
                </div>
            </div>

            <table class="table table-striped data-table" id="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($list as $Usuario): ?>
                    <tr id="row-<?php echo $Usuario->id ?>">
                        <td> <?php echo $Usuario->id ?> </td>
                        <td> <?php echo $Usuario->nome ?> </td>
                        <td> <?php echo $Usuario->email ?> </td>
                        <td> 
                            <div class="sidebyside-container" style="display: flex; gap: 10px;">
                                <a href="<?php echo site_url('updUsuario/' . $Usuario->id); ?>" class="btn btn-sm btn-info edit-button">✏️ Editar</a>
                                <button class="btn btn-sm btn-danger delete-button" type="button" onclick="confirmDelete('<?php echo $Usuario->id; ?>', '<?php echo $Usuario->nome; ?>')">🗑️ Excluir</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <script>
                function confirmDelete(id, nome) {
                    if (confirm("Você tem certeza que deseja deletar este Usuário: " + id + " - " + nome + "?")) {
                        $.ajax({
                            url: '<?php echo site_url('deleteUsuario/'); ?>' + id,
                            type: 'POST',
                            success: function(result) {
                                if (result.status === 'success') {
                                    $('#row-' + id).remove();
                                    alert('Usuário removido com sucesso!');
                                } else {
                                    alert('Erro: ' + result.mensagem);
                                }
                            },
                            error: function(err) {
                                alert('Erro de comunicação com servidor.');
                            }
                        });
                    }
                }

                $(document).ready(function() {
                    var table = $('#data-table').DataTable({
                        dom: 'lrtip',
                        language: {
                            "sEmptyTable": "Nenhum registro encontrado",
                            "sInfo": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                            "sInfoEmpty": "Mostrando 0 a 0 de 0",
                            "sSearch": "Pesquisar",
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

<?php
require VIEWPATH.'/footer.php';
?>
