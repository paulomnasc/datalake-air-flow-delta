<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
    
    <div id="content">        
        <div class="container">
            <h4 style="text-align: center;">Membros do Grupo: <?php echo esc($grupo->nome); ?></h4>
            <p style="text-align: center; color: #666;"><?php echo esc($grupo->email); ?></p>

            <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa;">
                <h5>Adicionar Membro ao Grupo</h5>
                <form id="addMembroForm" action="<?php echo route_to('Grupo.adicionarMembro'); ?>" method="post" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <input type="hidden" name="id_grupo" value="<?php echo $grupo->id; ?>">
                    
                    <div style="flex: 1; min-width: 200px;">
                        <label for="nome" style="display:block; font-size:12px; margin-bottom:5px;">Nome do Membro (opcional para usuários existentes):</label>
                        <input type="text" id="nome" name="nome" placeholder="Ex: João Silva" style="width:100%; padding: 6px; border: 1px solid #ccc; border-radius:4px;">
                    </div>
                    
                    <div style="flex: 1; min-width: 250px;">
                        <label for="email" style="display:block; font-size:12px; margin-bottom:5px;">E-mail do Membro:</label>
                        <input type="email" id="email" name="email" placeholder="Ex: joao@empresa.com" required style="width:100%; padding: 6px; border: 1px solid #ccc; border-radius:4px;">
                    </div>

                    <button type="submit" class="add-button" style="height: 38px; margin: 0; padding: 0 20px;">Adicionar</button>
                </form>
                <small style="color: #666; display: block; margin-top: 5px;">Se o e-mail informado for de um novo usuário, o sistema criará a conta automaticamente com uma senha temporária e enviará um e-mail de convite para redefinição.</small>
            </div>

            <table class="data-table" id="data-table">
                <thead>
                    <tr>
                        <th>ID Usuário</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($membros as $membro): ?>
                    <tr id="row-<?php echo $membro->id_rel; ?>">
                        <td> <?php echo $membro->id_usuario ?> </td>
                        <td> <?php echo $membro->nome ?> </td>
                        <td> <?php echo $membro->email ?> </td>
                        <td> 
                            <form id="deleteForm-<?php echo $membro->id_rel; ?>" style="margin:0;">
                                <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $membro->id_rel; ?>', '<?php echo $membro->nome; ?>', '<?php echo site_url('grupo/removerMembro/' . $membro->id_rel); ?>')">Remover</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px;">
                <button type="button" class="back-button" onclick="window.location.href='<?php echo route_to('listGrupo'); ?>';">Voltar aos Grupos</button>
            </div>

            <script>
                function confirmDelete(id, nome, deleteUrl) {
                    if (confirm("Você tem certeza que deseja remover o usuário " + nome + " deste grupo?")) {
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
                                alert('Erro ao remover o usuário.');
                                console.log(err);
                            }
                        });
                    }
                }

                $('#addMembroForm').submit(function(event) {
                    event.preventDefault();
                    var formData = $(this).serialize();
                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: formData,
                        success: function(result) {
                            alert(result.mensagem);
                            if (result.status === 'success') {
                                location.reload();
                            }
                        },
                        error: function(err) {
                            alert('Erro ao adicionar membro.');
                            console.log(err);
                        }
                    });
                });
            </script>

            <script>
                $(document).ready(function() {
                    $('#data-table').DataTable({
                        dom: 'rtip',
                        language: {
                            "sEmptyTable": "Nenhum membro no grupo",
                            "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ membros",
                            "sInfoEmpty": "Mostrando 0 até 0 de 0 membros",
                            "sInfoFiltered": "(Filtrados de _MAX_ membros)",
                            "oPaginate": {
                                "sNext": "Próximo",
                                "sPrevious": "Anterior"
                            }
                        }
                    });
                });
            </script>
        </div>
    </div>

<?php require VIEWPATH.'/footer.php'; ?>
