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
            

            <form method="post" id="meuFormulario" action="<?php echo route_to('Config.update'); ?>" enctype="multipart/form-data">

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

                <div class="form-group" id="source_sql_group" style="display:none;">
                    <h4>Configuração da Conexão SQL</h4>
                    <p class="help-text">Configure a conexão com o banco de dados</p>
                    
                    <div class="form-group">
                        <label for="sql_connection_id">ID da Conexão Airflow:</label>
                        <input type="text" id="sql_connection_id" name="sql_connection_id" placeholder="Ex: mysql_northwind" 
                            value="<?php echo $sql_connection_id ?? 'mysql_northwind' ?>">
                        <small class="form-text text-muted">ID da conexão configurada no Airflow (usado para autenticação)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sql_host">Host do Banco de Dados:</label>
                        <input type="text" id="sql_host" name="sql_host" placeholder="Ex: mysql, localhost, 192.168.1.10"
                            value="<?php echo $sql_host ?? '' ?>">
                        <small class="form-text text-muted">Hostname ou IP do servidor de banco de dados</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sql_port">Porta do Banco de Dados:</label>
                        <input type="number" id="sql_port" name="sql_port" placeholder="Ex: 3306 (MySQL), 5432 (PostgreSQL)"
                            value="<?php echo $sql_port ?? 3306 ?>">
                        <small class="form-text text-muted">Porta do servidor de banco de dados</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sql_database_name">Nome do Banco de Dados:</label>
                        <input type="text" id="sql_database_name" name="sql_database_name" placeholder="Ex: northwind, lista_revisao2"
                            value="<?php echo $sql_database_name ?? '' ?>">
                        <small class="form-text text-muted">Nome do schema/database a conectar</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sql_user">Usuário do Banco de Dados:</label>
                        <input type="text" id="sql_user" name="sql_user" placeholder="Ex: root, admin, user_readonly"
                            value="<?php echo $sql_user ?? '' ?>">
                        <small class="form-text text-muted">Nome de usuário para autenticação no banco</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sql_password">Senha do Banco de Dados:</label>
                        <input type="password" id="sql_password" name="sql_password" placeholder="Digite a senha"
                            value="<?php echo $sql_password ?? '' ?>">
                        <small class="form-text text-muted">Senha do usuário (será armazenada de forma segura)</small>
                    </div>
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
                    <select name="python_module_path" id="python_module_path" required>
                        <option value="">-- Selecione o tipo de pipeline --</option>
                        <optgroup label="⭐ Recomendado">
                            <option value="lib.medallion_pipeline.raw_to_medallion" 
                                <?php echo ($python_module_path === 'lib.medallion_pipeline.raw_to_medallion') ? 'selected' : ''; ?>>
                                Pipeline Completo (Bronze + Silver + Gold)
                            </option>
                        </optgroup>
                        <optgroup label="Ingestão de Fontes">
                            <option value="lib.mysql_ingestion.mysql_to_medallion"
                                <?php echo ($python_module_path === 'lib.mysql_ingestion.mysql_to_medallion') ? 'selected' : ''; ?>>
                                MySQL → Medallion (Ingestão + Bronze + Silver + Gold)
                            </option>
                            <option value="lib.mysql_ingestion.ingest_mysql_to_raw"
                                <?php echo ($python_module_path === 'lib.mysql_ingestion.ingest_mysql_to_raw') ? 'selected' : ''; ?>>
                                MySQL → Raw (Apenas ingestão para CSV)
                            </option>
                        </optgroup>
                        <optgroup label="Camadas Individuais">
                            <option value="lib.bronze_layer.raw_to_bronze"
                                <?php echo ($python_module_path === 'lib.bronze_layer.raw_to_bronze') ? 'selected' : ''; ?>>
                                Bronze (Raw → Bronze CSV)
                            </option>
                            <option value="lib.silver_layer.bronze_to_silver"
                                <?php echo ($python_module_path === 'lib.silver_layer.bronze_to_silver') ? 'selected' : ''; ?>>
                                Silver (Bronze → Silver Parquet)
                            </option>
                            <option value="lib.gold_layer.silver_to_gold"
                                <?php echo ($python_module_path === 'lib.gold_layer.silver_to_gold') ? 'selected' : ''; ?>>
                                Gold (Silver → Gold Parquet Otimizado)
                            </option>
                        </optgroup>
                        <optgroup label="Legado">
                            <option value="lib.minio_tasks.transform_data_with_pandas"
                                <?php echo ($python_module_path === 'lib.minio_tasks.transform_data_with_pandas') ? 'selected' : ''; ?>>
                                ⚠️ Função Legada (não recomendado)
                            </option>
                        </optgroup>
                    </select>
                    <small>Escolha o tipo de processamento: Pipeline Completo (recomendado), Ingestão de fontes (MySQL, etc) ou camadas individuais.</small>
                </div>
                
                    <div class="form-group">
                        <label for="transform_args">Argumentos Extras da Função (JSON):</label>
                        <textarea style="width: 80%; height: 400px;" name="transform_args" id="transform_args" 
                        placeholder="Deve ser um JSON válido (será armazenado no campo JSON da tabela)."><?php echo $transform_args ?? '' ?></textarea>

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
        const sqlGroup = document.getElementById('source_sql_group');

        // Referências aos inputs
        const uploadInput = document.getElementById('source_file_upload');
        const pathInput = document.getElementById('source_file_path');
        const sqlHostInput = document.getElementById('sql_host');
        const sqlConnInput = document.getElementById('sql_connection_id');
        const sqlDbInput = document.getElementById('sql_database_name');

        // 2. Resetar todos os displays e desativar 'required' e 'name'
        [currentFileGroup, uploadGroup, pathGroup, sqlGroup].forEach(g => g ? g.style.display = 'none' : null);
        [uploadInput, pathInput, sqlHostInput].forEach(i => {
            if (i) {
                i.removeAttribute('required');
                i.value = i.value || ''; // Mantém valor existente
                i.name = 'temp_field';
            }
        });
        
        let activeInput = null;

        // 3. Lógica de exibição baseada na descrição
        if (sourceDescription.includes('upload') || sourceDescription.includes('csv') || sourceDescription.includes('json')) {
            // Arquivo de Upload (CSV/JSON)
            if (currentFileGroup) currentFileGroup.style.display = 'block'; 
            if (uploadGroup) uploadGroup.style.display = 'block';
            if (uploadInput) {
                uploadInput.removeAttribute('required'); // Opcional na edição
                activeInput = uploadInput;
            }
            
        } else if (sourceDescription.includes('parquet') || sourceDescription.includes('path')) {
            // Caminho no MinIO
            pathGroup.style.display = 'block';
            pathInput.setAttribute('required', 'required');
            activeInput = pathInput;
            
        } else if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('sql')) {
            // Campos estruturados SQL
            sqlGroup.style.display = 'block';
            sqlHostInput.setAttribute('required', 'required');
            sqlConnInput.setAttribute('required', 'required');
            sqlDbInput.setAttribute('required', 'required');
            
            sqlHostInput.name = 'sql_host';
            activeInput = sqlHostInput;
        }
        
        // 4. Mapeamento: O input ATIVO recebe o nome 'source_filename' (exceto SQL)
        if (activeInput && activeInput.id !== 'sql_host') {
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