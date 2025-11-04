<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content">

    <div class="container">
        <h1>Editar Configuração de DAG</h1>
        <!-- updQuadro.php -->
            
            
            
        <div id="form-container">
            

            <form method="post" id="meuFormulario" action="<?php echo route_to('Config.update'); ?>">

                <div class="form-group">
                        <input type="hidden" name="id" placeholder="ID" value="<?php echo $id ?>" required readonly>
                </div>

                <div class="form-group">
                    <label for="id_pasta">Pasta:</label>
                    <select id="id_pasta" name="id_pasta" required>
                    
                        <option value="">Selecione</option>
                        <?php foreach($pastas as $pasta): ?>
                            <option value="<?php echo $pasta->id; ?>" <?php echo ($pasta->id == $id_pasta_selecionado) ? 'selected' : ''; ?>>
                                <?php echo $pasta->descricao; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                <label for="owner">Owner:</label>
                <input type="text" class="form-control" id="owner" name="owner" 
                       value="<?= esc($configData['owner'] ?? 'webapp_user') ?>">
                </div>

                <div class="form-group">
                    <label for="schedule_interval">Schedule Interval (Cron):</label>
                    <input type="text" class="form-control" id="schedule_interval" name="schedule_interval" 
                        value="<?= esc($configData['schedule_interval'] ?? '0 0 * * *') ?>">
                </div>

                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                        value="1" <?= (isset($configData['is_active']) && $configData['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">DAG Ativa</label>
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição:</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= esc($configData['description'] ?? '') ?></textarea>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend>Conexão SSH (Bases On-Premises)</legend>
                
                <p>Preencha apenas se a base de dados SQL for acessada via Jump Server/Túnel SSH.</p>

                <div class="form-group">
                    <label for="ssh_host">Host SSH (Jump Server):</label>
                    <input type="text" class="form-control" id="ssh_host" name="ssh_host" 
                        value="<?= esc($configData['ssh_host'] ?? '') ?>" placeholder="Ex: 192.168.1.100">
                </div>

                <div class="form-group">
                    <label for="ssh_user">Usuário SSH:</label>
                    <input type="text" class="form-control" id="ssh_user" name="ssh_user" 
                        value="<?= esc($configData['ssh_user'] ?? '') ?>" placeholder="Ex: ssh_user">
                </div>

                <div class="form-row">
                    <div class="col">
                        <label for="ssh_port">Porta SSH:</label>
                        <input type="number" class="form-control" id="ssh_port" name="ssh_port" 
                            value="<?= esc($configData['ssh_port'] ?? 22) ?>">
                    </div>
                    
                    <div class="col">
                        <label for="ssh_local_port">Porta Local do Túnel:</label>
                        <input type="number" class="form-control" id="ssh_local_port" name="ssh_local_port" 
                            value="<?= esc($configData['ssh_local_port'] ?? 13306) ?>">
                        <small class="form-text text-muted">Esta porta será usada para o túnel local.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ssh_key_path">Caminho da Chave Privada SSH:</label>
                    <input type="text" class="form-control" id="ssh_key_path" name="ssh_key_path" 
                        value="<?= esc($configData['ssh_key_path'] ?? '') ?>" 
                        placeholder="/home/airflow/.ssh/id_rsa">
                    <small class="form-text text-muted">Insira o caminho *absoluto* da chave no servidor que executa a DAG.</small>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend>Configuração da Fonte de Dados</legend>
                <div class="form-group">
                    <label for="db_host">Host do Banco de Dados:</label>
                    <input type="text" class="form-control" id="db_host" name="db_host" 
                        value="<?= esc($configData['db_host'] ?? '') ?>">
                </div>
            
            </fieldset>

                <div class="form-actions">
                    <button type="submit" class="save-button" value="Salvar Configuração">Salvar Configuração</button>
                    <button type="button" class="back-button" value="Voltar" onclick="Voltar()">Voltar</button>
                </div>

                
            </form>


        </div>
    </div>


</div>


<script>


function Voltar() {
    $.ajax({
        url: '<?= base_url('listQuadro'); ?>', // Defina a rota corretamente
        type: 'GET',
        success: function (result) {
            window.location.href = "<?= route_to('listQuadro'); ?>";
        },
        error: function (xhr, status, error) {
            console.error('Erro na requisição:', error);
        }
    });
}

function submitMeuFormularioUpload() {

    const formMeuFormularioUpload = document.getElementById('meuFormularioUpload');
    const formData = new FormData(formMeuFormularioUpload);

    // Prevent default submission behavior
    event.preventDefault();
    
    //Limpa o conteúdo ta tabela que veio no load do edit
    // Chama a função para limpar os dados do Handsontable
    container = document.getElementById('spreadSheet');
    //A linha está dando erro em     const colHeaders = Object.keys(data[0]); no handsontable.js
    clearHandsontable(container); 

    $.ajax({
        url: formMeuFormularioUpload.action,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(result) {
            // Exibe a mensagem na div de feedback
            const messageDiv = document.getElementById('upload-message');
            if (result.status === 'success') {
                messageDiv.innerHTML = result.mensagem;
                messageDiv.style.color = 'green';
                // Verifique se o arquivo foi carregado com sucesso e leia o conteúdo do CSV
                if (result.uploadedFile) {
                    $(document).ready(function() {
                        fetchCSVAndInitializeHandsontable(result.uploadedFile);
                    });

                }
            } else {
                messageDiv.innerHTML = result.mensagem;
                messageDiv.style.color = 'red';
            }
            messageDiv.style.display = 'block';
        },
        error: function(err) {
            const messageDiv = document.getElementById('upload-message');
            messageDiv.innerHTML = 'Erro ao enviar o arquivo.' + err;
            messageDiv.style.color = 'red';
            messageDiv.style.display = 'block';
            console.log(err.responseText);
        }
    });
    }



    $('#arquivo').on('change', function() {
    var fileName = $(this).val().split('\\').pop();
    $('#nome_arquivo').val(fileName);
    });

    $('#meuFormulario').submit(function(event) {
        event.preventDefault();

        salvarTabelaNaSessao();
        // Cria um FormData a partir do formulário
        var formData = new FormData(this); 

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false, // Impede o jQuery de transformar o FormData em uma string
            contentType: false, // Desabilita o cabeçalho padrão de content-type
            success: function(result) {
                if (result.status === 'success') {
                    $('#success-message').html(result.mensagem).show().delay(6000).fadeOut(function() {
                        window.location.href = "<?php echo route_to('listQuadro'); ?>"; // Redireciona para listQuadro após exibir a mensagem
                    });
                } else {
                    $('#error-message').html(result.mensagem).show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                }
            },
            error: function(err) {
                $('#error-message').html('Erro ao salvar o quadro.').show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                console.log(err); // Trate o erro aqui
            }
            });
        });
        /*
        function setarSessao(sessionVar, sessionValue) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "SessaoController.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    console.log(xhr.responseText); // Exibe a resposta do servidor
                }
            };

            const data = `session_var=${encodeURIComponent(sessionVar)}&session_value=${encodeURIComponent(sessionValue)}`;
            xhr.send(data);
        }

        // Exemplo de uso: Define a variável de sessão 'user' com o valor 'JohnDoe'
        setSessionVariable('user', 'JohnDoe');
        */

        
</script>

<?php
require VIEWPATH . '/footer.php';
?>