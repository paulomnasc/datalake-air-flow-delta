<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<style>
/* Estilos para abas de conexão */
.connection-tabs {
    display: flex;
    border-bottom: 2px solid #ddd;
    margin-bottom: 20px;
    gap: 5px;
}
.connection-tab {
    padding: 12px 24px;
    cursor: pointer;
    background: #f5f5f5;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    transition: all 0.3s;
    border-radius: 5px 5px 0 0;
}
.connection-tab:hover {
    background: #e8e8e8;
}
.connection-tab.active {
    background: white;
    border-bottom-color: #007bff;
    color: #007bff;
}
.tab-content {
    display: none;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 0 5px 5px 5px;
    background: white;
}
.tab-content.active {
    display: block;
}
</style>

<div id="content">

    <div class="container">
        <h1>Criar Novo Fluxo</h1>

        <!-- form method="post" id="meuFormularioUpload" action="<?php echo route_to('Config.fileUpload'); ?>" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="arquivo">Arquivo Csv:</label>
                <input type="file" id="arquivo" name="arquivo" required>
                <label style="color: red;" > Atenção !!! O arquivo deve ter as colunas separadas pelo caractere vírgula.</label>
            </div>
            <button type="submit" class="save-button" onclick="submitMeuFormularioUpload()" value="Atualizar">Enviar</button -->
            <!-- Div para exibir mensagens de sucesso ou erro -->
            <!-- div id="upload-message" style="display: none; margin-top: 10px;"></div>


        </form -->


        <div id="spreadSheet" class="hot" style="overflow: auto"></div>
        <!-- button id="download" onclick="downloadCSV(hotInstance)"  >Download CSV</button-->
        <!-- Botão para salvar as edições da tabela na sessão >
        <div> 
            <button class="edit-button" id="save" name="save" style="display: none;">💾
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M17 3h-10c-1.104 0-2 .896-2 2v14c0 1.104.896 2 2 2h10c1.104 0 2-.896 2-2v-13l-4-4zm-3 2v4h-4v-4h4zm2 14h-8v-2h8v2zm1-10h-10v-6h4v4h6v2z" fill="white"/>
            </svg>
            </button>

            <div id="save-sheet-message" style="display: none; margin-top: 10px;"></div>
        </div-->

        <script>

                document.addEventListener('DOMContentLoaded', function() {
                    /* // Apenas pegue o valor direto e atribua, o que evita o uso de JSON.parse na string completa
                    let csv = <!-- ?php echo $conteudo_csv_json; ?>;
                    
                    // Verifique se o conteúdo está correto antes de prosseguir com a função de conversão
                    console.log("CSV :", csv);

                    // Desescapar os caracteres unicode 
                    csv = desescaparCaracteresUnicode(csv); 
                    console.log("CSV sem unicode:", csv); 
                    // Verifique se o CSV está correto antes de processá-lo
                    
                    // Agora chame csvToJsonString(csv) e prossiga normalmente
                    var jsonData = csvToJsonString(csv);
                    console.log("Dados formatados em Json:", jsonData);
                    
                    // Verifique se jsonData não está vazio e inicialize o Handsontable
                    var jsArray = CSVToArray(csv);
                    console.log("Dados convertidos para javascript array: ", jsArray);
                    initializeHandsontable(jsArray);
                    */                    
                }); 

                const save = document.getElementById('save');
                const messageDivSheetMessage = document.getElementById('save-sheet-message');

                if (save) {
                    save.addEventListener('click', () => {
                    // Save all cell's data
                    const data = handsontableInstance.getData();

                fetch('<?= base_url('/salvarTabela') ?>', {
                    method: 'POST',
                    mode: 'no-cors',
                    headers: {
                    'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ data }),
                })
                    .then((response) => {
                    return response.json(); // Parse the response as JSON
                    })
                    .then((result) => {
                        // Display success message 
                        messageDivSheetMessage.style.color = 'green'; 
                        messageDivSheetMessage.textContent = 'Planilha salva com sucesso'; 
                        messageDivSheetMessage.style.display = 'block'; 
                        // Hide message after 6 seconds 
                        setTimeout(() => { messageDivSheetMessage.style.display = 'none'; }, 6000); 
                        console.log('Planilha salva com sucesso'); }) 
                    .catch((error) => { 
                        // Display error message 
                        messageDivSheetMessage.style.color = 'red'; 
                        messageDivSheetMessage.textContent = 'Erro: ' + error.message; 
                        messageDivSheetMessage.style.display = 'block'; 
                        // Hide message after 6 seconds 
                        setTimeout(() => { messageDivSheetMessage.style.display = 'none'; }, 6000); 
                        console.error('Error:', error); 
                    });
                    messageDivSheetMessage.style.display = 'block'; 
                });
                } // Fecha o if (save)
            </script>

            </script>
            <!-- FIM Carrega o conteúdo do arquivo da coluna da tabela do sgbd -->    
            

        

        <form method="post" id="meuFormulario" action="<?php echo route_to('Config.insert'); ?>" enctype="multipart/form-data">
            <fieldset>
                <legend>Metadados da DAG</legend>
                <div class="form-group">
                    <label for="id_pasta">Pasta Associada:</label>
                    <select id="id_pasta" name="id_pasta" required> 
                        <option value="">Selecione</option>
                        <?php foreach($pastas as $pasta): ?>
                            <option value="<?php echo $pasta->id; ?>"><?php echo $pasta->descricao; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dag_id">ID da DAG (Único):</label>
                    <input type="text" name="dag_id" id="dag_id" placeholder="Ex: ingestao_clientes_vendas" maxlength="128" required>
                </div>
                <div class="form-group">
                    <label for="owner">Proprietário (Airflow Owner):</label>
                    <input type="text" name="owner" id="owner" placeholder="Ex: equipe_dados" maxlength="64" required value="webapp_user">
                </div>
                <div class="form-group">
                    <label for="schedule_interval">Agendamento (CRON):</label>
                    <input type="text" name="schedule_interval" id="schedule_interval" placeholder="Ex: 0 4 * * * (Todo dia às 4h)" maxlength="64" required value="0 0 * * *">
                </div>
                <div class="form-group">
                    <label for="description">Descrição da DAG:</label>
                    <textarea name="description" id="description" placeholder="Breve descrição para a UI do Airflow"></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>Configuração de Pipeline</legend>

                <div class="form-group">
                    <label for="id_source_type">Tipo da Fonte de Dados:</label>
                    <select class="form-control" id="id_source_type" name="id_source_type" required onchange="toggleSourceInput(this.value)">
                        <option value="">Selecione o Tipo de Fonte</option>
                        <?php foreach ($source_types as $source_type): ?>
                            <option value="<?= esc($source_type['id']) ?>" 
                                    data-description="<?= esc($source_type['description']) ?>"
                                    <?= (isset($source_type_selecionado) && $source_type_selecionado == $source_type['id']) ? 'selected' : '' ?>>
                                <?= esc($source_type['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="source_upload_group" style="display:none;">
                    <!-- Checkbox para ativar upload múltiplo -->
                    <div class="mb-3">
                        <input type="checkbox" id="enable_multi_upload" name="enable_multi_upload" value="1" onchange="toggleMultiUploadMode(this.checked)">
                        <label for="enable_multi_upload">📦 Upload Múltiplo de Arquivos (Batch Processing)</label>
                        <br>
                        <small class="text-muted" style="margin-left: 20px;">
                            <strong>Dica:</strong> Use para processar múltiplos arquivos CSV/JSON simultaneamente ou sequencialmente
                        </small>
                    </div>
                    
                    <!-- Upload Único (padrão) -->
                    <div id="single_upload_section">
                        <label for="source_upload_file">Pasta Selecionada:</label>
                        <input type="file" name="source_file_upload" id="source_upload_file" accept=".csv,.json">
                    </div>
                    
                    <!-- Upload Múltiplo (oculto inicialmente) -->
                    <div id="multi_upload_section" style="display:none;">
                        <label>Arquivos de Origem (CSV/JSON):</label>
                        
                        <!-- Instruções de Uso -->
                        <div class="alert alert-info" style="margin: 10px 0; padding: 10px; background: #e7f3ff; border-left: 3px solid #2196F3;">
                            <strong>📌 Como usar:</strong>
                            <ul style="margin: 5px 0 0 20px; padding: 0;">
                                <li><strong>Arquivos Individuais:</strong> Deixe a opção abaixo desmarcada e selecione múltiplos arquivos (Ctrl+Click ou Shift+Click)</li>
                                <li><strong>Pasta Completa:</strong> Marque a opção abaixo e clique para selecionar uma pasta - todos os arquivos CSV/JSON dentro serão enviados</li>
                            </ul>
                        </div>
                        
                        <!-- Opção de seleção de pasta -->
                        <div class="mb-3">
                            <input type="checkbox" id="select_folder" name="select_folder" value="1" onchange="toggleFolderSelection(this.checked)">
                            <label for="select_folder">📂 Selecionar Pasta Inteira (todos os arquivos dentro da pasta)</label>
                        </div>
                        
                        <!-- Área de Drag & Drop -->
                        <div id="drop-zone" class="upload-area">
                            <div class="upload-icon">📁</div>
                            <p class="upload-text" id="drop-zone-text">
                                Arraste e solte os arquivos aqui<br>
                                <small>ou clique para selecionar múltiplos arquivos CSV/JSON</small>
                            </p>
                            <input 
                                type="file" 
                                id="multiple_files" 
                                name="multiple_files[]" 
                                multiple 
                                accept=".csv,.json" 
                                style="display: none;"
                            >
                        </div>
                        
                        <!-- Lista de Arquivos Selecionados -->
                        <div id="file-list" class="mt-3"></div>
                        
                        <!-- Configurações de Batch -->
                        <div class="mt-3">
                            <h5>Configurações de Processamento em Batch</h5>
                            
                            <div class="form-group">
                                <label>Modo de Processamento:</label>
                                <div>
                                    <input type="radio" id="batch_mode_parallel" name="batch_mode" value="parallel" checked>
                                    <label for="batch_mode_parallel">Paralelo (processa múltiplos arquivos simultaneamente)</label>
                                </div>
                                <div>
                                    <input type="radio" id="batch_mode_sequential" name="batch_mode" value="sequential">
                                    <label for="batch_mode_sequential">Sequencial (processa um arquivo por vez)</label>
                                </div>
                            </div>
                            
                            <div class="form-group" id="max_parallel_files_group">
                                <label for="max_parallel_files">Máximo de Arquivos Paralelos:</label>
                                <input type="number" id="max_parallel_files" name="max_parallel_files" value="4" min="1" max="16">
                                <small class="form-text text-muted">Entre 1 e 16 (padrão: 4)</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="source_path_group" style="display:none;">
                    <label for="source_path">Caminho do Arquivo (MinIO Raw):</label>
                    <input type="text" name="source_file_path" id="source_path" placeholder="Ex: raw/dados_pre_existentes.parquet" maxlength="255">
                </div>

                <div class="form-group" id="source_sql_group" style="display:none;">
                    <h4>Configuração da Conexão SQL</h4>
                    <!-- Abas de Tipo de Conexão -->
                    <div class="connection-tabs">
                        <button type="button" class="connection-tab active" onclick="switchConnectionTab('direct')">
                            🔌 Conexão Direta
                        </button>
                        <button type="button" class="connection-tab" onclick="switchConnectionTab('ssh')">
                            🔐 Conexão via SSH Tunnel
                        </button>
                    </div>
                    
                    <!-- Conteúdo: Conexão Direta -->
                    <div id="tab-direct" class="tab-content active">
                        <p class="help-text">Configure a conexão direta ao banco de dados (sem túnel SSH)</p>
                        
                        <div class="form-group">
                            <label for="sql_connection_id">ID da Conexão Airflow:</label>
                            <input type="text" id="sql_connection_id" name="sql_connection_id" placeholder="Ex: mysql_northwind" value="mysql_northwind">
                            <small class="form-text text-muted">ID da conexão configurada no Airflow (usado para autenticação)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sql_host">Host do Banco de Dados:</label>
                            <input type="text" id="sql_host" name="sql_host" placeholder="Ex: mysql, 192.168.1.10, db.empresa.com">
                            <small class="form-text text-muted">
                                <strong>💡 Dica:</strong> Use "localhost" para o MySQL local do Docker, ou informe IP/hostname externo (ex: 203.0.113.45, rds.amazonaws.com)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sql_port">Porta do Banco de Dados:</label>
                            <input type="number" id="sql_port" name="sql_port" placeholder="Ex: 3306 (MySQL), 5432 (PostgreSQL)" value="3306">
                            <small class="form-text text-muted">Porta do servidor de banco de dados</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sql_database_name">Nome do Banco de Dados:</label>
                            <input type="text" id="sql_database_name" name="sql_database_name" placeholder="Ex: northwind, lista_revisao2">
                            <small class="form-text text-muted">Nome do schema/database a conectar</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sql_user">Usuário do Banco de Dados:</label>
                            <input type="text" id="sql_user" name="sql_user" placeholder="Ex: root, admin, user_readonly">
                            <small class="form-text text-muted">Nome de usuário para autenticação no banco</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sql_password">Senha do Banco de Dados:</label>
                            <input type="password" id="sql_password" name="sql_password" placeholder="Digite a senha">
                            <small class="form-text text-muted">Senha do usuário (será armazenada de forma segura)</small>
                        </div>
                        
                        <!-- Botão Conectar (apenas visível em modo multi-table) -->
                        <div id="sql_connect_section" style="display:none;">
                            <button type="button" id="btn_connect_tables" class="btn btn-primary" onclick="connectAndListTables()" style="margin-top: 10px;">
                                🔌 Conectar e Listar Tabelas
                            </button>
                            <div id="connection_status" style="margin-top: 10px; display: none;"></div>
                        </div>
                    </div>
                    
                    <!-- Conteúdo: Conexão SSH -->
                    <div id="tab-ssh" class="tab-content">
                        <p class="help-text">Configure o túnel SSH para acessar bancos de dados on-premises ou protegidos</p>
                        
                        <fieldset>
                            <legend>🔐 Configuração do Túnel SSH</legend>
                            
                            <div class="form-group">
                                <label for="ssh_host">Host SSH (Jump Server):</label>
                                <input type="text" class="form-control" id="ssh_host" name="ssh_host" placeholder="Ex: 192.168.1.100, ssh.company.com">
                                <small class="form-text text-muted">Servidor SSH que dará acesso ao banco de dados</small>
                            </div>

                            <div class="form-group">
                                <label for="ssh_user">Usuário SSH:</label>
                                <input type="text" class="form-control" id="ssh_user" name="ssh_user" placeholder="Ex: ssh_user, admin">
                                <small class="form-text text-muted">Usuário para autenticação no servidor SSH</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ssh_port">Porta SSH:</label>
                                        <input type="number" class="form-control" id="ssh_port" name="ssh_port" value="22">
                                        <small class="form-text text-muted">Porta do serviço SSH (padrão: 22)</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ssh_local_port">Porta Local do Túnel:</label>
                                        <input type="number" class="form-control" id="ssh_local_port" name="ssh_local_port" value="13306">
                                        <small class="form-text text-muted">Porta local para o tunnel (ex: 13306)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="ssh_key_path">Caminho da Chave Privada SSH:</label>
                                <input type="text" class="form-control" id="ssh_key_path" name="ssh_key_path" placeholder="/home/airflow/.ssh/id_rsa">
                                <small class="form-text text-muted">Caminho da chave privada no servidor Airflow</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="ssh_password">Senha SSH (opcional):</label>
                                <input type="password" class="form-control" id="ssh_password" name="ssh_password" placeholder="Deixe vazio se usar chave">
                                <small class="form-text text-muted">Use senha apenas se não tiver chave privada configurada</small>
                            </div>
                        </fieldset>
                        
                        <fieldset style="margin-top: 20px;">
                            <legend>💾 Configuração do Banco de Dados (via túnel)</legend>
                            
                            <div class="form-group">
                                <label for="sql_host_ssh">Host do Banco de Dados (destino do túnel):</label>
                                <input type="text" class="form-control" id="sql_host_ssh" name="sql_host_ssh" placeholder="Ex: localhost, 127.0.0.1, mysql-server">
                                <small class="form-text text-muted">Host do BD acessível via SSH (geralmente localhost no servidor SSH)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="sql_port_ssh">Porta do Banco de Dados:</label>
                                <input type="number" class="form-control" id="sql_port_ssh" name="sql_port_ssh" value="3306">
                                <small class="form-text text-muted">Porta do BD no servidor remoto</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="sql_database_name_ssh">Nome do Banco de Dados:</label>
                                <input type="text" class="form-control" id="sql_database_name_ssh" name="sql_database_name_ssh" placeholder="Ex: northwind">
                                <small class="form-text text-muted">Nome do schema/database</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="sql_user_ssh">Usuário do Banco de Dados:</label>
                                <input type="text" class="form-control" id="sql_user_ssh" name="sql_user_ssh" placeholder="Ex: root, dbuser">
                                <small class="form-text text-muted">Usuário do banco de dados</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="sql_password_ssh">Senha do Banco de Dados:</label>
                                <input type="password" class="form-control" id="sql_password_ssh" name="sql_password_ssh">
                                <small class="form-text text-muted">Senha do banco de dados</small>
                            </div>
                        </fieldset>
                    </div>
                
                </div>
                
                <!-- Seção Multi-Table Selection (só para fontes SQL) -->
                <div class="multi-table-checkbox" id="multi_table_checkbox_container" style="display: none;">
                    <input type="checkbox" id="is_multi_table" name="is_multi_table" value="1" onchange="toggleMultiTableMode(this.checked)">
                    <label for="is_multi_table">📊 Modo Multi-Tabela (processar múltiplas tabelas em paralelo)</label>
                </div>
                
                <div id="multi-table-section" style="display: none;">
                    <h4>Selecione as Tabelas para Processar</h4>
                    <p class="help-text">As tabelas selecionadas serão processadas em paralelo pela mesma DAG</p>
                    
                    <div id="tables-loading" style="display: none;">
                        <div class="spinner"></div>
                        <p>Carregando tabelas disponíveis...</p>
                    </div>
                    
                    <div id="tables-container"></div>
                    
                    <div id="max_parallel_tasks_group" class="form-group">
                        <label for="max_parallel_tasks">Máximo de Tasks Paralelas:</label>
                        <input type="number" id="max_parallel_tasks" name="max_parallel_tasks" value="16" min="1" max="64">
                        <small class="help-text">Número máximo de tabelas processadas simultaneamente (recomendado: 16)</small>
                    </div>
                </div>
                
                <div class="form-group" id="single-table-field">
                    <label for="target_table_name">Tabela/Destino Final (MinIO Trusted):</label>
                    <input type="text" name="target_table_name" id="target_table_name" placeholder="Ex: clientes_trusted" maxlength="128" required>
                    <small class="form-text text-muted">Apenas para modo single-table</small>
                </div>
            </fieldset>

            <fieldset>
                <legend>Lógica de Transformação</legend>
                <div class="form-group">
                    <label for="python_module_path">Função Python de Transformação:</label>
                    <select name="python_module_path" id="python_module_path" required onchange="validatePipelineSelection()">
                        <option value="">-- Selecione o tipo de pipeline --</option>
                        <optgroup label="⭐ Recomendado para CSV/Parquet">
                            <option value="lib.medallion_pipeline.raw_to_medallion">
                                Pipeline Completo (Bronze + Silver + Gold) - RAW já existe
                            </option>
                        </optgroup>
                        <optgroup label="🔥 Ingestão de Fontes SQL (MySQL, PostgreSQL)">
                            <option value="lib.mysql_ingestion.mysql_to_medallion">
                                ✅ MySQL → Medallion (Ingestão + Bronze + Silver + Gold)
                            </option>
                            <option value="lib.mysql_ingestion.ingest_mysql_to_raw">
                                MySQL → Raw (Apenas ingestão para CSV)
                            </option>
                        </optgroup>
                        <optgroup label="Camadas Individuais">
                            <option value="lib.bronze_layer.raw_to_bronze">
                                Bronze (Raw → Bronze CSV)
                            </option>
                            <option value="lib.silver_layer.bronze_to_silver">
                                Silver (Bronze → Silver Parquet)
                            </option>
                            <option value="lib.gold_layer.silver_to_gold">
                                Gold (Silver → Gold Parquet Otimizado)
                            </option>
                        </optgroup>
                        <optgroup label="Legado">
                            <option value="lib.minio_tasks.transform_data_with_pandas">
                                ⚠️ Função Legada (não recomendado)
                            </option>
                        </optgroup>
                    </select>
                    <small id="pipeline_help_text">Escolha o tipo de processamento: Pipeline Completo (recomendado), Ingestão de fontes (MySQL, etc) ou camadas individuais.</small>
                    <div id="pipeline_warning" style="display: none; color: #d9534f; font-weight: bold; margin-top: 5px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="transform_args">Argumentos Extras da Função (JSON):</label>
                    <textarea style="width: 80%; height: 400px;" name="transform_args" id="transform_args" placeholder="Deve ser um JSON válido (será armazenado no campo JSON da tabela)."></textarea>
                    
                </div>
            </fieldset>
            

            <div class="form-group">
                <label for="is_active">Status da DAG:</label>
                <select id="is_active" name="is_active" required>
                    <option value="1" selected>Ativa (Gerar DAG)</option>
                    <option value="0">Inativa (Não Gerar DAG)</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="save-button" value="Salvar Configuração">Salvar Configuração</button>
                <button type="button" class="back-button" value="Voltar" onclick="Voltar()">Voltar</button>
            </div>

        </form>


    </div>
</div>

<script>
    function submitMeuFormularioUpload() {

        const formMeuFormularioUpload = document.getElementById('meuFormularioUpload');
        const formData = new FormData(formMeuFormularioUpload);
        
        // Prevent default submission behavior
        event.preventDefault();
        
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
                            //fetchCSVAndInitializeHandsontable(result.uploadedFile);
                            console.log("Arquivo enviado com sucesso: ", result.uploadedFile);
                        });

                    }
                } else {
                    console.log("Erro ao enviar o arquivo: ", result.mensagem);
                    messageDiv.innerHTML = result.mensagem;
                    messageDiv.style.color = 'red';
                }
                messageDiv.style.display = 'block';
            },
            error: function(err) {
                const messageDiv = document.getElementById('upload-message');
                messageDiv.innerHTML = 'Erro ao enviar o arquivo.';
                messageDiv.style.color = 'red';
                messageDiv.style.display = 'block';
                console.log(err);
            }
        });
    }



    $('#arquivo').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $('#nome_arquivo').val(fileName);
    });

    $('#meuFormulario').submit(function(event) {
        event.preventDefault();
        
        console.log('📤 Formulário sendo submetido...');

        //Código legado do handsontable desabilitado
        //salvarTabelaNaSessao();

        var formData = new FormData(this);
        
        // 🔧 CORREÇÃO: Se upload múltiplo estiver ativo, adicionar arquivos da variável selectedFiles
        const isMultiUploadEnabled = document.getElementById('enable_multi_upload')?.checked;
        
        console.log('🔍 Debug do formulário:');
        console.log('  - Upload múltiplo habilitado:', isMultiUploadEnabled);
        console.log('  - window.selectedFiles existe:', typeof window.selectedFiles !== 'undefined');
        console.log('  - Qtd de arquivos em selectedFiles:', window.selectedFiles?.length || 0);
        
        if (isMultiUploadEnabled && typeof window.selectedFiles !== 'undefined' && window.selectedFiles.length > 0) {
            console.log('📦 Upload múltiplo ativo - Adicionando arquivos ao FormData');
            console.log('📄 Total de arquivos:', window.selectedFiles.length);
            
            // Remover o campo multiple_files[] existente (pode estar vazio)
            formData.delete('multiple_files[]');
            
            // Adicionar cada arquivo selecionado
            window.selectedFiles.forEach((file, index) => {
                formData.append('multiple_files[]', file);
                console.log(`  ✓ Arquivo ${index + 1}: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`);
            });
            
            console.log('✅ Arquivos adicionados ao FormData com sucesso');
        } else {
            console.log('⚠️ Upload múltiplo não ativo ou sem arquivos selecionados');
        }
        
        // Log do FormData para debug
        console.log('📋 Conteúdo do FormData:');
        for (let pair of formData.entries()) {
            if (pair[1] instanceof File) {
                console.log(`  ${pair[0]}: [File] ${pair[1].name}`);
            } else {
                console.log(`  ${pair[0]}: ${pair[1]}`);
            }
        }
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(result) {
                console.log('Resposta do servidor:', result);
                
                // Suporta tanto 'message' quanto 'mensagem'
                const mensagem = result.mensagem || result.message || 'Operação realizada com sucesso';
                
                if (result.status === 'success' || result.status === 'partial') {
                    $('#success-message').html(mensagem).show().delay(6000).fadeOut(function() {
                        window.location.href = "<?php echo route_to('listConfig'); ?>";
                    });
                } else {
                    $('#error-message').html(mensagem).show().delay(6000).fadeOut();
                }
            },
            error: function(err) {
                console.error('❌ Erro na requisição:', err);
                $('#error-message').html('Erro ao salvar as informações.').show().delay(6000).fadeOut();
                console.log(err);
            }
        });
    });
    
    function Voltar() {
        $.ajax({
            url: '<?= base_url('listConfig'); ?>', // Defina a rota corretamente
            type: 'GET',
            success: function (result) {
                window.location.href = "<?= route_to('listConfig'); ?>";
            },
            error: function (xhr, status, error) {
                console.error('Erro na requisição:', error);
            }
        });
    }


    // Adicionar evento ao botão para imprimir os tipos das colunas 
    /* document.getElementById('printColumnTypes').addEventListener('click', function () { 
        printColumnTypes(hot); 
    }); */

 // Função para alternar entre abas de conexão
    function switchConnectionTab(tabName) {
        // Remover active de todas as abas e conteúdos
        document.querySelectorAll('.connection-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Ativar aba e conteúdo selecionados
        event.target.classList.add('active');
        document.getElementById('tab-' + tabName).classList.add('active');
    }

 // 🛑 A FUNÇÃO NÃO RECEBE MAIS ARGUMENTOS 🛑
    function toggleSourceInput() {
        // Pega o elemento SELECT pelo seu novo ID (id_source_type)
        const selectElement = document.getElementById('id_source_type');

        // Se o elemento não for encontrado (apenas um fallback de segurança)
        if (!selectElement) {
            console.error('Elemento SELECT com ID "id_source_type" não encontrado.');
            return;
        }

        const selectedId = selectElement.value; // ID numérico: 1, 2, 3...
        
        // --- 1. Determinar o Tipo de Fonte (por String) ---
        let sourceDescription = '';
        if (selectedId) {
            // Pega a opção selecionada e extrai o texto (a descrição)
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            sourceDescription = selectedOption.textContent.trim().toLowerCase(); 
        }

        // Grupos de Inputs
        const uploadGroup = document.getElementById('source_upload_group');
        const pathGroup = document.getElementById('source_path_group');
        const sqlGroup = document.getElementById('source_sql_group');
        
        // Inputs
        const uploadInput = document.getElementById('source_upload_file');
        const pathInput = document.getElementById('source_path');
        const sqlHostInput = document.getElementById('sql_host');
        const sqlConnInput = document.getElementById('sql_connection_id');
        const sqlDbInput = document.getElementById('sql_database_name');

        // 2. Limpar e esconder todos por padrão
        [uploadGroup, pathGroup, sqlGroup].forEach(g => g ? g.style.display = 'none' : null);
        [uploadInput, pathInput, sqlHostInput, sqlConnInput, sqlDbInput].forEach(i => {
            if (i) {
                i.removeAttribute('required');
                i.value = ''; 
                i.name = 'temp_field';
            }
        });
        
        let activeInput = null;

        // 3. Lógica Condicional (Baseada na string da descrição)
        if (sourceDescription.includes('csv') || sourceDescription.includes('json')) {
            uploadGroup.style.display = 'block';
            uploadInput.setAttribute('required', 'required');
            activeInput = uploadInput;
            
        } else if (sourceDescription.includes('parquet')) {
            pathGroup.style.display = 'block';
            pathInput.setAttribute('required', 'required');
            activeInput = pathInput;
            
        } else if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('sql')) {
            // Mostrar campos estruturados SQL
            sqlGroup.style.display = 'block';
            sqlHostInput.setAttribute('required', 'required');
            sqlConnInput.setAttribute('required', 'required');
            sqlDbInput.setAttribute('required', 'required');
            
            // O source_filename será montado como "TipoSQL.Host" no backend
            // Mas precisamos enviar os campos individuais
            sqlHostInput.name = 'sql_host';
            activeInput = sqlHostInput;
        }
        
        // 4. Mapeamento para source_filename será feito no backend
        if (activeInput && activeInput.id !== 'sql_host') {
            activeInput.name = 'source_filename'; 
        }
    }
    
    // Função para alternar modo multi-tabela
    function toggleMultiTableMode(isMultiTable) {
        const multiTableSection = document.getElementById('multi-table-section');
        const singleTableField = document.getElementById('single-table-field');
        const targetTableInput = document.getElementById('target_table_name');
        const sqlConnectSection = document.getElementById('sql_connect_section');
        const connectBtn = document.getElementById('btn_connect_tables');
        
        if (isMultiTable) {
            multiTableSection.style.display = 'block';
            singleTableField.style.display = 'none';
            targetTableInput.removeAttribute('required');
            
            // Se for SQL, mostrar botão conectar E auto-selecionar função correta
            const sourceType = document.getElementById('id_source_type');
            if (sourceType && sourceType.selectedIndex >= 0) {
                const selectedOption = sourceType.options[sourceType.selectedIndex];
                const sourceDescription = selectedOption.textContent.trim().toLowerCase();
                
                if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('sql')) {
                    if (sqlConnectSection) sqlConnectSection.style.display = 'block';
                    
                    // Auto-selecionar a função correta para ingestão SQL
                    autoSelectCorrectPipelineFunction();
                }
            }
        } else {
            multiTableSection.style.display = 'none';
            singleTableField.style.display = 'block';
            targetTableInput.setAttribute('required', 'required');
            
            // Ainda mostrar botão conectar se for SQL (para teste de conexão)
            const sourceType = document.getElementById('id_source_type');
            if (sourceType && sourceType.selectedIndex >= 0) {
                const selectedOption = sourceType.options[sourceType.selectedIndex];
                const sourceDescription = selectedOption.textContent.trim().toLowerCase();
                
                if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('sql')) {
                    if (sqlConnectSection) sqlConnectSection.style.display = 'block';
                } else {
                    if (sqlConnectSection) sqlConnectSection.style.display = 'none';
                }
            } else {
                if (sqlConnectSection) sqlConnectSection.style.display = 'none';
            }
        }
    }
    
    // Auto-selecionar função Python correta baseado no tipo de fonte
    function autoSelectCorrectPipelineFunction() {
        const sourceType = document.getElementById('id_source_type');
        const pythonModulePath = document.getElementById('python_module_path');
        const isMultiTable = document.getElementById('is_multi_table').checked;
        
        if (!sourceType || !pythonModulePath) return;
        
        const selectedOption = sourceType.options[sourceType.selectedIndex];
        const sourceDescription = selectedOption.textContent.trim().toLowerCase();
        
        // Se for fonte SQL (MySQL, PostgreSQL, etc)
        if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('sql')) {
            if (isMultiTable) {
                // Para multi-table SQL: DEVE usar mysql_to_medallion (faz ingestão + todas camadas)
                pythonModulePath.value = 'lib.mysql_ingestion.mysql_to_medallion';
                
                // Destacar a opção selecionada
                highlightSelectedPipelineOption();
                
                console.log('✅ Auto-selecionado: lib.mysql_ingestion.mysql_to_medallion (fonte SQL + multi-table)');
            } else {
                // Para single-table SQL: pode ser mysql_to_medallion ou só ingest_mysql_to_raw
                // Deixar usuário escolher, mas sugerir mysql_to_medallion
                if (!pythonModulePath.value || pythonModulePath.value === 'lib.medallion_pipeline.raw_to_medallion') {
                    pythonModulePath.value = 'lib.mysql_ingestion.mysql_to_medallion';
                    console.log('💡 Sugerido: lib.mysql_ingestion.mysql_to_medallion (fonte SQL)');
                }
            }
        }
    }
    
    // Destacar visualmente a opção selecionada
    function highlightSelectedPipelineOption() {
        const pythonModulePath = document.getElementById('python_module_path');
        if (pythonModulePath) {
            pythonModulePath.style.backgroundColor = '#e7f3ff';
            pythonModulePath.style.border = '2px solid #007bff';
            
            setTimeout(() => {
                pythonModulePath.style.backgroundColor = '';
                pythonModulePath.style.border = '';
            }, 2000);
        }
    }
    
    // Validar se a função selecionada é compatível com o tipo de fonte
    function validatePipelineSelection() {
        const sourceType = document.getElementById('id_source_type');
        const pythonModulePath = document.getElementById('python_module_path');
        const warningDiv = document.getElementById('pipeline_warning');
        const isMultiTable = document.getElementById('is_multi_table').checked;
        
        if (!sourceType || !pythonModulePath || !warningDiv) return;
        
        const selectedOption = sourceType.options[sourceType.selectedIndex];
        const sourceDescription = selectedOption ? selectedOption.textContent.trim().toLowerCase() : '';
        const selectedFunction = pythonModulePath.value;
        
        // Limpar warnings anteriores
        warningDiv.style.display = 'none';
        warningDiv.innerHTML = '';
        
        // Validação: Se fonte é SQL
        if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('sql')) {
            
            // ERRO: Selecionou raw_to_medallion para fonte SQL
            if (selectedFunction === 'lib.medallion_pipeline.raw_to_medallion') {
                warningDiv.innerHTML = '⚠️ ATENÇÃO: Esta função espera que dados JÁ EXISTAM na camada RAW. Para fontes SQL, use "MySQL → Medallion" que faz a ingestão primeiro!';
                warningDiv.style.display = 'block';
                return;
            }
            
            // OBRIGATÓRIO: Multi-table SQL DEVE usar mysql_to_medallion
            if (isMultiTable && selectedFunction !== 'lib.mysql_ingestion.mysql_to_medallion') {
                warningDiv.innerHTML = '❌ ERRO: Para processar múltiplas tabelas SQL, você DEVE usar "MySQL → Medallion" (faz ingestão + pipeline completo)';
                warningDiv.style.display = 'block';
                warningDiv.style.backgroundColor = '#fff3cd';
                warningDiv.style.borderColor = '#ffc107';
                pythonModulePath.value = 'lib.mysql_ingestion.mysql_to_medallion';
                highlightSelectedPipelineOption();
                return;
            }
            
            // SUCESSO: Fonte SQL com função correta
            if (selectedFunction === 'lib.mysql_ingestion.mysql_to_medallion' || selectedFunction === 'lib.mysql_ingestion.ingest_mysql_to_raw') {
                warningDiv.innerHTML = '✅ Configuração válida: Função compatível com fonte SQL';
                warningDiv.style.display = 'block';
                warningDiv.style.backgroundColor = '#d4edda';
                warningDiv.style.borderColor = '#28a745';
                
                // Limpar mensagem de sucesso após 3 segundos
                setTimeout(() => {
                    warningDiv.style.display = 'none';
                    warningDiv.innerHTML = '';
                }, 3000);
                return;
            }
        }
        
        // Para CSV/Parquet: validar se usa raw_to_medallion
        if ((sourceDescription.includes('csv') || sourceDescription.includes('parquet')) && 
            selectedFunction === 'lib.medallion_pipeline.raw_to_medallion') {
            warningDiv.innerHTML = '✅ Configuração válida: Função compatível com arquivos CSV/Parquet';
            warningDiv.style.display = 'block';
            warningDiv.style.backgroundColor = '#d4edda';
            warningDiv.style.borderColor = '#28a745';
            
            // Limpar mensagem de sucesso após 3 segundos
            setTimeout(() => {
                warningDiv.style.display = 'none';
                warningDiv.innerHTML = '';
            }, 3000);
            return;
        }
        
        // Tudo OK - limpar qualquer mensagem anterior
        warningDiv.style.display = 'none';
        warningDiv.innerHTML = '';
        console.log('✅ Função selecionada é compatível com o tipo de fonte');
    }
    
    // Função para conectar e listar tabelas
    async function connectAndListTables() {
        const connectionId = document.getElementById('sql_connection_id').value;
        const host = document.getElementById('sql_host').value;
        const port = document.getElementById('sql_port').value || 3306;
        const databaseName = document.getElementById('sql_database_name').value;
        const user = document.getElementById('sql_user').value;
        const password = document.getElementById('sql_password').value;
        
        const statusDiv = document.getElementById('connection_status');
        const loadingDiv = document.getElementById('tables-loading');
        const containerDiv = document.getElementById('tables-container');
        
        // Validação
        if (!connectionId || !host || !databaseName || !user) {
            statusDiv.innerHTML = '<span style="color: red;">❌ Preencha todos os campos obrigatórios (Connection ID, Host, Database, User)</span>';
            statusDiv.style.display = 'block';
            return;
        }
        
        // Mostrar loading
        loadingDiv.style.display = 'block';
        containerDiv.innerHTML = '';
        statusDiv.style.display = 'none';
        
        try {
            const formData = new URLSearchParams();
            formData.append('connection_id', connectionId);
            formData.append('host', host);
            formData.append('port', port);
            formData.append('database_name', databaseName);
            formData.append('user', user);
            formData.append('password', password);
            
            const response = await fetch('<?= base_url('config/getAvailableTables') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            });
            
            const result = await response.json();
            
            loadingDiv.style.display = 'none';
            
            console.log('Resposta do servidor:', result); // Debug
            
            if (result.status === 'success' && result.tables) {
                if (result.tables.length === 0) {
                    containerDiv.innerHTML = '<p style="color: orange;">⚠️ Nenhuma tabela encontrada</p>';
                } else {
                    renderTableCheckboxes(result.tables);
                    statusDiv.innerHTML = `<span style="color: green;">✅ ${result.tables.length} tabelas encontradas</span>`;
                    statusDiv.style.display = 'block';
                }
            } else {
                // Exibe a mensagem de erro completa
                const errorMsg = result.mensagem || result.message || 'Erro desconhecido';
                statusDiv.innerHTML = `<span style="color: red;">❌ ${errorMsg}</span>`;
                statusDiv.style.display = 'block';
                console.error('Erro retornado:', result);
            }
        } catch (error) {
            loadingDiv.style.display = 'none';
            statusDiv.innerHTML = `<span style="color: red;">❌ Erro de requisição: ${error.message}</span>`;
            statusDiv.style.display = 'block';
            console.error('Erro na requisição:', error);
        }
    }
    
    // Renderizar checkboxes de tabelas
    function renderTableCheckboxes(tables) {
        const container = document.getElementById('tables-container');
        
        let html = '<div class="tables-selection">';
        html += '<div style="margin-bottom: 10px;"><button type="button" onclick="selectAllTables(true)" class="btn btn-sm">✓ Selecionar Todas</button> ';
        html += '<button type="button" onclick="selectAllTables(false)" class="btn btn-sm">✗ Desmarcar Todas</button></div>';
        html += '<div class="tables-grid">';
        
        tables.forEach(table => {
            const tableName = table.table_name;
            const rowCount = table.row_count ? `(${table.row_count.toLocaleString()} rows)` : '';
            const tableSize = table.table_size_mb ? `${table.table_size_mb} MB` : '';
            
            html += `
                <div class="table-checkbox-item">
                    <input type="checkbox" id="table_${tableName}" name="selected_tables[]" value="${tableName}" class="table-checkbox">
                    <label for="table_${tableName}">
                        <strong>${tableName}</strong>
                        <small>${rowCount} ${tableSize}</small>
                    </label>
                </div>
            `;
        });
        
        html += '</div></div>';
        container.innerHTML = html;
    }
    
    // Selecionar/Desmarcar todas
    function selectAllTables(select) {
        const checkboxes = document.querySelectorAll('.table-checkbox');
        checkboxes.forEach(cb => cb.checked = select);
    }

    // Configura Listeners
    document.addEventListener('DOMContentLoaded', function() {
        toggleSourceInput(); // Executa na carga da página (para formulários de UPDATE)
        checkMultiTableVisibility(); // Verifica se deve mostrar checkbox multi-tabela
        
        const selectElement = document.getElementById('id_source_type');
        if (selectElement) {
            selectElement.addEventListener('change', function() {
                toggleSourceInput(); // Executa na mudança
                checkMultiTableVisibility(); // Atualiza visibilidade do multi-tabela
            });
        }
    });
    
    // Controla visibilidade do checkbox Multi-Tabela (só para fontes SQL)
    function checkMultiTableVisibility() {
        const selectElement = document.getElementById('id_source_type');
        const multiTableContainer = document.getElementById('multi_table_checkbox_container');
        const sqlConnectSection = document.getElementById('sql_connect_section');
        
        if (!selectElement || !multiTableContainer) return;
        
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const sourceTypeText = selectedOption.text.toUpperCase();
        
        // Mostra apenas se contém "SQL" no nome (MySQL, PostgreSQL, etc.)
        if (sourceTypeText.includes('SQL')) {
            multiTableContainer.style.display = 'block';
            // Mostrar botão conectar para fontes SQL
            if (sqlConnectSection) sqlConnectSection.style.display = 'block';
        } else {
            multiTableContainer.style.display = 'none';
            // Esconder botão conectar para fontes não-SQL
            if (sqlConnectSection) sqlConnectSection.style.display = 'none';
            // Desmarca o checkbox se estava marcado
            const checkbox = document.getElementById('is_multi_table');
            if (checkbox && checkbox.checked) {
                checkbox.checked = false;
                toggleMultiTableMode(false);
            }
        }
    }

</script>

<script>
// Controlar alternância entre upload único e múltiplo
function toggleMultiUploadMode(isMulti) {
    const singleSection = document.getElementById('single_upload_section');
    const multiSection = document.getElementById('multi_upload_section');
    const maxParallelGroup = document.getElementById('max_parallel_files_group');
    
    if (isMulti) {
        singleSection.style.display = 'none';
        multiSection.style.display = 'block';
        // Desabilitar input de arquivo único
        document.getElementById('source_upload_file').disabled = true;
    } else {
        singleSection.style.display = 'block';
        multiSection.style.display = 'none';
        // Habilitar input de arquivo único
        document.getElementById('source_upload_file').disabled = false;
    }
}

// Controlar visibilidade do campo max_parallel baseado no modo
document.querySelectorAll('input[name="batch_mode"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const container = document.getElementById('max_parallel_files_group');
        if (this.value === 'parallel') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    });
});

// Alternar entre seleção de arquivos múltiplos e pasta inteira
function toggleFolderSelection(selectFolder) {
    const fileInput = document.getElementById('multiple_files');
    const dropZoneText = document.getElementById('drop-zone-text');
    
    if (selectFolder) {
        // Habilitar seleção de pasta
        fileInput.setAttribute('webkitdirectory', '');
        fileInput.setAttribute('directory', '');
        fileInput.removeAttribute('accept'); // Pastas não usam accept
        dropZoneText.innerHTML = 'Arraste e solte uma pasta aqui<br><small>ou clique para selecionar uma pasta com arquivos CSV/JSON</small>';
    } else {
        // Habilitar seleção de múltiplos arquivos
        fileInput.removeAttribute('webkitdirectory');
        fileInput.removeAttribute('directory');
        fileInput.setAttribute('accept', '.csv,.json');
        dropZoneText.innerHTML = 'Arraste e solte os arquivos aqui<br><small>ou clique para selecionar múltiplos arquivos CSV/JSON</small>';
    }
}

// Inicializar o handler de upload múltiplo quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se o elemento existe antes de inicializar
    const dropZone = document.getElementById('drop-zone');
    if (dropZone) {
        // Inicializar o MultiFileUpload do multi-upload.js
        // Nota: O multi-upload.js será ativado quando o checkbox for marcado
        console.log('Multi-upload disponível');
    }
});
</script>

<?php
require VIEWPATH . '/footer.php';
?>
