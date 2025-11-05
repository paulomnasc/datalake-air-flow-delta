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
            <label for="dag_id">ID da DAG (Único):</label>
            <input type="text" name="dag_id" id="dag_id" placeholder="Ex: ingestao_clientes_vendas" maxlength="128" required
            value="<?php echo $dag_id ?>">
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
    // Função para retornar à lista de configurações
    function Voltar() {
        window.location.href = "<?= route_to('listConfig'); ?>"; 
    }

    // -----------------------------------------------------------
    // 🛑 LÓGICA DE EXIBIÇÃO/OCULTAÇÃO DE CAMPOS (toggleSourceInput)
    // -----------------------------------------------------------
    function toggleSourceInput() {
        console.log("Função toggleSourceInput executada."); 
        
        const selectElement = document.getElementById('id_source_type');
        if (!selectElement) return;

        // 1. OBTÉM A DESCRIÇÃO DO OPTION ATUALMENTE SELECIONADO
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        
        // Se não houver opção selecionada (ex: a primeira é 'Selecione'), a descrição é vazia
        const sourceDescription = selectedOption ? 
                                (selectedOption.getAttribute('data-description') || '').toLowerCase() : 
                                '';
        
        console.log("Descrição da Fonte Capturada:", sourceDescription); 

        // Referências aos grupos de campos
        const currentFileGroup = document.getElementById('current_source_file_group');
        const uploadGroup = document.getElementById('source_upload_group');
        const pathGroup = document.getElementById('source_path_group');
        const uriGroup = document.getElementById('source_uri_group');

        // Referências aos inputs
        const uploadInput = document.getElementById('source_file_upload');
        const pathInput = document.getElementById('source_file_path');
        const uriInput = document.getElementById('source_db_uri');

        // 2. Resetar todos os displays e desativar 'required' e 'name'
        
        // 🛑 CORREÇÃO APLICADA AQUI: Filtra elementos nulos antes de iterar
        [currentFileGroup, uploadGroup, pathGroup, uriGroup].filter(g => g !== null).forEach(g => {
            g.style.display = 'none';
        });
        
        // 🛑 CORREÇÃO APLICADA AQUI: Filtra elementos nulos antes de iterar
        [uploadInput, pathInput, uriInput].filter(i => i !== null).forEach(i => {
            i.removeAttribute('required');
            // Remove o 'name' de todos, apenas o ativo deve ter 'source_filename'
            i.name = i.id; 
        });
        
        let activeInput = null;

        // 3. Lógica de exibição baseada na descrição (agora minúscula)
        if (sourceDescription.includes('upload') || sourceDescription.includes('csv') || sourceDescription.includes('json')) {
            // Arquivo de Upload (CSV/JSON)
            if (currentFileGroup) currentFileGroup.style.display = 'block'; 
            if (uploadGroup) uploadGroup.style.display = 'block'; // Adiciona check null
            
            if (uploadInput) {
                uploadInput.removeAttribute('required'); // Opcional na edição, mantendo o original
                activeInput = uploadInput;
            }
            
        } else if (sourceDescription.includes('parquet') || sourceDescription.includes('path')) {
            // Caminho no MinIO
            if (pathGroup) pathGroup.style.display = 'block'; // Adiciona check null
            
            if (pathInput) {
                pathInput.setAttribute('required', 'required');
                activeInput = pathInput;
            }
            
        } else if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('database') || sourceDescription.includes('uri')) {
            // URI de Conexão de Banco de Dados
            if (uriGroup) uriGroup.style.display = 'block'; // Adiciona check null
            
            if (uriInput) {
                uriInput.setAttribute('required', 'required');
                activeInput = uriInput;
            }
        }
        
        // 4. Mapeamento Crucial: O input ATIVO recebe o nome 'source_filename'
        if (activeInput) {
            activeInput.name = 'source_filename'; 
        }
    }

    // -----------------------------------------------------------
    // CONFIGURAÇÃO DOS LISTENERS
    // -----------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function() {
        const selectElement = document.getElementById('id_source_type');
        
        if (selectElement) {
            // 1. Execução IMEDIATA no carregamento (Modo Edição)
            toggleSourceInput(); 

            // 2. Execução na mudança de seleção
            selectElement.addEventListener('change', toggleSourceInput); 
        }

        // Lógica AJAX de submissão do formulário...
        $('#meuFormulario').submit(function(event) {
            event.preventDefault();

            var formData = new FormData(this); 

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false, 
                contentType: false, 
                success: function(result) {
                    if (result.status === 'success') {
                        $('#success-message').html(result.mensagem).show().delay(6000).fadeOut(function() {
                            window.location.href = "<?php echo route_to('listConfig'); ?>"; 
                        });
                    } else {
                        $('#error-message').html(result.mensagem).show().delay(6000).fadeOut();
                    }
                },
                error: function(err) {
                    $('#error-message').html('Erro ao salvar o quadro.').show().delay(6000).fadeOut();
                    console.log(err);
                }
            });
        });
    });
    </script>

<?php
require VIEWPATH . '/footer.php';
?>