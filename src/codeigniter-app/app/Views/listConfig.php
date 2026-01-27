<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>


        <div id="content">        


        <?php if (session()->getFlashdata('limit-message')): ?>
            <div id="limit-message" class="error-message">
                <?= session()->getFlashdata('limit-message'); ?>
            </div>
        <?php endif; ?>

        <script>
            $(document).ready(function() {
                // Verifica se há uma mensagem de erro e aplica o fade-out
                $('#limit-message').show(); // Espera 5 segundos e depois faz fade-out em 1 segundo
            });
        </script>


            <div class="container">
            <h4 style="text-align: center;">Listagem de Fluxos (Pipelines)</h4>

                <input type="text" id="filtro-nome" placeholder="Filtrar por descrição">
            <img src="../assets/img/lupa.jpg" >
            
            <br><br>
            <div class="form-group">
                <label for="id_pasta" style="display: inline-block;">Pasta:</label>
                <?php if (!empty($pastas)): ?>
                    <select id="id_pasta" name="id_pasta" required>
                    
                        <option value="">Selecione</option>
                        <?php foreach($pastas as $pasta): ?>
                            <option value="<?php echo $pasta->id; ?>" <?php echo $pasta->descricao; ?>>
                                <?php echo $pasta->descricao; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <p>Nenhuma pasta encontrada.</p>
                <?php endif; ?>
            </div>


            <!--?php 
            // Verifica se o perfil do usuário está logado e se ele é "Anonimo"
            // COMENTAR ESSE IF PARA CADASTRAR UM QUADRO PARA O USUARIO ANONIMO QUANDO FOR IMPLANTAR SOLUÇÃO 
            /* if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Anonimo"): 
            ?--> 
                <form action="<?php echo site_url('addConfig'); ?>" method="post">
                    <button type="submit" class="add-button">Incluir</button>
                </form>
            <!--?php
            endif; 
            ?-->

            
                <table class="data-table" id="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>description</th>
                            <th>Ações</th>
                        </tr>
                        
                    </thead>
                    <tbody>
                        

                    </tbody>
                </table>

            </div>
        
    </div>
    

<script>
        var table;
        $(document).ready(function() {
            table = $('#data-table').DataTable({
            dom: 'lrtip', // Oculta a caixa de busca
            columnDefs: [
                {
                    targets: [0], // Índice da coluna que queremos ocultar (4ª coluna, começando do 0)
                    visible: false // Torna a coluna invisível
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


        

    $('#id_pasta').change(function() {
        var pastaId = $(this).val();
        console.log('ID da Pasta Selecionada:', pastaId); // Depuração: Verificar se o evento change está funcionando
        
        carregarConfigs(pastaId,table); // Chama a função para carregar os quadros
    });

        
});

function confirmDelete(id, descricao, deleteUrl, formId) {
        if (confirm("Você tem certeza que deseja deletar este Config: " + id + " - " + descricao + "?")) {
            $.ajax({
                url: deleteUrl,
                type: 'POST',
                data: {
                    _method: 'DELETE' // Simula o método DELETE
                },
                success: function(result) {
                    if (result.status === 'success') {
                        // Recarrega a tabela com a pasta selecionada atualmente
                        var pastaId = $('#id_pasta').val(); // Obtém o ID da pasta selecionada
                        
                        carregarConfigs(pastaId,table);
                        
                        // Mostra a mensagem de sucesso
                        $('#success-message').html('Registro excluído com sucesso.').show().delay(6000).fadeOut();
                    } else {
                        $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut();
                    }
                },
                error: function(err) {
                    $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut();
                    console.log(err); // Trate o erro aqui
                }
            });
        }
    }
    
    function carregarConfigs(pastaId, table) {
        if (pastaId) {
            $.ajax({
                url: '<?= base_url('listarConfig') ?>',
                type: 'GET',
                data: { id_pasta: pastaId },
                success: function(response) {
                    console.log('Tipo de resposta:', typeof response); // Verifica o tipo de resposta
                    console.log('Resposta JSON:', response); // Verifica o conteúdo da resposta

                    // Limpa os dados existentes
                    table.clear();

                    // Verifica se a resposta é JSON
                    if (typeof response !== 'object') {
                        response = JSON.parse(response);
                    }

                    // Adiciona novas linhas
                    response.forEach(function(item) {
                        // HTML da terceira coluna com botões de editar e excluir
                        var actionButtons = `
                            <div class="sidebyside-container">
                                <?php if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Anonimo"){ ?>
                                    <a href="<?= base_url('dashboard?edit='); ?>${item.id}" class="edit-button" title="Editar">✏️</a>
                                <?php } ?>
                                <form id="deleteForm-${item.id}">
                                    <?php if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Anonimo"){
                                            ?>
                                        <button class="delete-button" type="button" onclick="confirmDelete('${item.id}', '${item.descricao}', '<?= site_url('deleteConfig/'); ?>${item.id}', 'deleteForm-${item.id}')">🗑️</button>
                                    <?php
                                        }
                                    ?>
                                
                                </form>
                            </div>
                        `;

                        table.row.add([
                            item.id,
                            item.description,
                            actionButtons // Terceira coluna com os botões
                        ]);
                    });
                    table.draw(); // Redesenha a tabela com os novos dados
                },
                error: function(err) {
                    console.error('Erro:', err);
                }
            });
        } else {
            table.clear().draw(); // Limpa o DataTable se nenhuma pasta for selecionada
        }
    }

</script>



<?php
require VIEWPATH.'/footer.php';
?>


