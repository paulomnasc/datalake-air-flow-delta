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
            <label for="owner">Proprietário (Airflow Owner):</label>
            <input type="text" name="owner" id="owner" placeholder="Ex: equipe_dados" maxlength="64" required 
                   value="<?php echo $owner ?>">
        </div>
        <div class="form-group">
            <label for="schedule_interval">Agendamento (CRON):</label>
            <input type="text" name="schedule_interval" id="schedule_interval" placeholder="Ex: 0 4 * * * (Todo dia às 4h)" maxlength="64" required 
                   value="<?php echo $schedule_interval ?>">
        </div>
        <div class="form-group">
            <label for="description">Descrição da DAG:</label>
            <!-- 3. Preenchimento de valor na textarea -->
            <textarea name="description" id="description" placeholder="Breve descrição para a UI do Airflow"><?php echo $description ?? '' ?></textarea>
        </div>
        </fieldset>

        <fieldset>
            <legend>Configuração de Pipeline</legend>

                <div class="form-group">
                    <label for="id_source_type">Tipo da Fonte de Dados:</label>
                    <select class="form-control" id="id_source_type" name="id_source_type" required onchange="toggleSourceInput(this.value)">
                        <option value="">Selecione o Tipo de Fonte</option>
                        <?php foreach ($source_types as $source_type): ?>
                            <option value="<?php echo $source_type['id'] ?>" 
                                    data-description="<?php echo $source_type['description'] ?>"
                                    <?= ($id_source_type_selecionado == $source_type['id']) ? 'selected' : '' ?>>
                                <?php echo $source_type['description'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">A mudança do tipo de fonte pode exigir re-upload ou novo caminho.</small>
                </div>
            
            <!--
                Em um formulário de edição:
                - Se for um tipo de upload (CSV/JSON), você deve mostrar o NOME do arquivo ATUALMENTE salvo.
                - O campo 'file' só deve ser preenchido se o usuário quiser SUBSTITUIR o arquivo existente.
            -->
            
            <!-- Campo Oculto para Manter o Caminho Original se Nenhum Novo Arquivo for Enviado -->
                <input type="hidden" name="source_filename_original" value="<?php echo $source_filename ?? '' ?>">

                <div class="form-group" id="current_source_file_group" style="display:none;">
                    <p>Arquivo Atual: <span id="current_source_filename"><?php echo $source_filename ?? 'N/A' ?></span></p>
                </div>

                <!-- O campo de upload (source_file_upload) agora é OPCIONAL na edição -->
                <div class="form-group" id="source_upload_group" style="display:none;">
                    <label for="source_file_upload">Arquivo de Origem (CSV/JSON):</label>
                    <input type="file" name="source_file_upload" id="source_file_upload" accept=".csv,.json">
                    <small class="text-muted">Envie um arquivo **apenas** se quiser substituí-lo.</small>
                </div>
                
                <div class="form-group" id="source_path_group" style="display:none;">
                    <label for="source_file_path">Caminho do Arquivo (MinIO Raw):</label>
                    <input type="text" name="source_file_path" id="source_file_path" placeholder="Ex: raw/dados_pre_existentes.parquet" maxlength="255"
                        value="<?php echo $source_filename ?? '' ?>">
                </div>

                <div class="form-group" id="source_uri_group" style="display:none;">
                    <label for="source_db_uri">URL de Conexão de Banco de Dados (SQLAlchemy URI):</label>
                    <input type="text" name="source_db_uri" id="source_db_uri" placeholder="Ex: mysql+mysqlconnector://user:pass@host:port/database" maxlength="512"
                        value="<?php echo $source_filename ?? '' ?>">
                    
                    <fieldset>
                        <legend>Conexão SSH (Bases On-Premises)</legend>
                        
                        <p>Preencha apenas se a base de dados SQL for acessada via Jump Server/Túnel SSH.</p>

                        <div class="form-group">
                            <label for="ssh_host">Host SSH (Jump Server):</label>
                            <input type="text" class="form-control" id="ssh_host" name="ssh_host" placeholder="Ex: 192.168.1.100"
                                value="<?php echo $ssh_host ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="ssh_user">Usuário SSH:</label>
                            <input type="text" class="form-control" id="ssh_user" name="ssh_user" placeholder="Ex: ssh_user"
                                value="<?php echo $ssh_user ?? '' ?>">
                        </div>

                        <div class="form-row">
                            <div class="col">
                                <label for="ssh_port">Porta SSH:</label>
                                <input type="number" class="form-control" id="ssh_port" name="ssh_port" 
                                    value="<?php echo $ssh_port ?? 22 ?>">
                            </div>
                            
                            <div class="col">
                                <label for="ssh_local_port">Porta Local do Túnel:</label>
                                <input type="number" class="form-control" id="ssh_local_port" name="ssh_local_port" 
                                    value="<?php echo $ssh_local_port ?? 13306 ?>">
                                <small class="form-text text-muted">Esta porta será usada para o túnel local.</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="ssh_key_path">Caminho da Chave Privada SSH:</label>
                            <input type="text" class="form-control" id="ssh_key_path" name="ssh_key_path" placeholder="/home/airflow/.ssh/id_rsa"
                                value="<?php echo $ssh_key_path ?? '' ?>">
                            <small class="form-text text-muted">Caminho da chave no servidor que executará a DAG.</small>
                        </div>
                        
                    </fieldset>
                
                </div>
                
                <div class="form-group">
                    <label for="target_table_name">Tabela/Destino Final (MinIO Trusted):</label>
                    <input type="text" name="target_table_name" id="target_table_name" placeholder="Ex: clientes_trusted" maxlength="128" required
                        value="<?php echo $target_table_name ?>">
                </div>
            </fieldset>

            <fieldset>
                <legend>Lógica de Transformação</legend>
                <div class="form-group">
                    <label for="python_module_path">Função Python de Transformação:</label>
                    <input type="text" name="python_module_path" id="python_module_path" 
                        placeholder="Ex: lib.minio_tasks.transform_data_with_pandas" maxlength="255" required 
                        value="<?php echo $python_module_path ?>">
                    <small>Caminho completo do módulo e função a ser executada pelo PythonOperator.</small>
                </div>
                
                <div class="form-group">
                    <label for="transform_args">Argumentos Extras da Função (JSON):</label>
                    <input type="text" name="transform_args" id="transform_args" 
                        placeholder="Ex: {'columns_to_drop': ['col1', 'col2']}" 
                        value="<?php echo $transform_args ?>">
                    <small>Deve ser um JSON válido (será armazenado no campo JSON da tabela).</small>
                </div>
            </fieldset>
            
            <div class="form-group">
                <label for="is_active">Status da DAG:</label>
                <select id="is_active" name="is_active" required>
                    <!-- 3. Preenchimento de valor na select -->
                    <option value="1" <?= ($is_active == 1) ? 'selected' : '' ?>>Ativa (Gerar DAG)</option>
                    <option value="0" <?= ($is_active == 0) ? 'selected' : '' ?>>Inativa (Não Gerar DAG)</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="save-button">Salvar Edições</button>
                <button type="button" class="back-button" onclick="window.history.back()">Voltar</button>
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

        //salvarTabelaNaSessao();
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