<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content">

    <div class="container">
        <h1>Criar Novo Config</h1>

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
                    <label for="source_upload_file">Arquivo de Origem (CSV/JSON):</label>
                    <input type="file" name="source_file_upload" id="source_upload_file" accept=".csv,.json">
                </div>
                
                <div class="form-group" id="source_path_group" style="display:none;">
                    <label for="source_path">Caminho do Arquivo (MinIO Raw):</label>
                    <input type="text" name="source_file_path" id="source_path" placeholder="Ex: raw/dados_pre_existentes.parquet" maxlength="255">
                </div>

                <div class="form-group" id="source_uri_group" style="display:none;">
                    <label for="source_uri">URL de Conexão de Banco de Dados (SQLAlchemy URI):</label>
                    <input type="text" name="source_db_uri" id="source_uri" placeholder="Ex: mysql+mysqlconnector://user:pass@host:port/database" maxlength="512">
                    <fieldset>
                        <legend>Conexão SSH (Bases On-Premises)</legend>
                        
                        <p>Preencha apenas se a base de dados SQL for acessada via Jump Server/Túnel SSH.</p>

                        <div class="form-group">
                            <label for="ssh_host">Host SSH (Jump Server):</label>
                            <input type="text" class="form-control" id="ssh_host" name="ssh_host" placeholder="Ex: 192.168.1.100">
                        </div>

                        <div class="form-group">
                            <label for="ssh_user">Usuário SSH:</label>
                            <input type="text" class="form-control" id="ssh_user" name="ssh_user" placeholder="Ex: ssh_user">
                        </div>

                        <div class="form-row">
                            <div class="col">
                                <label for="ssh_port">Porta SSH:</label>
                                <input type="number" class="form-control" id="ssh_port" name="ssh_port" value="22">
                            </div>
                            
                            <div class="col">
                                <label for="ssh_local_port">Porta Local do Túnel:</label>
                                <input type="number" class="form-control" id="ssh_local_port" name="ssh_local_port" value="13306">
                                <small class="form-text text-muted">Esta porta será usada para o túnel local.</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="ssh_key_path">Caminho da Chave Privada SSH:</label>
                            <input type="text" class="form-control" id="ssh_key_path" name="ssh_key_path" placeholder="/home/airflow/.ssh/id_rsa">
                            <small class="form-text text-muted">Caminho da chave no servidor que executará a DAG.</small>
                        </div>
                        
                    </fieldset>
                
                </div>
                
                <div class="form-group">
                    <label for="target_table_name">Tabela/Destino Final (MinIO Trusted):</label>
                    <input type="text" name="target_table_name" id="target_table_name" placeholder="Ex: clientes_trusted" maxlength="128" required>
                </div>
            </fieldset>

            <fieldset>
                <legend>Lógica de Transformação</legend>
                <div class="form-group">
                    <label for="python_module_path">Função Python de Transformação:</label>
                    <input type="text" name="python_module_path" id="python_module_path" 
                        placeholder="Ex: lib.minio_tasks.transform_data_with_pandas" maxlength="255" required 
                        value="lib.minio_tasks.transform_data_with_pandas">
                    <small>Caminho completo do módulo e função a ser executada pelo PythonOperator.</small>
                </div>
                
                <div class="form-group">
                    <label for="transform_args">Argumentos Extras da Função (JSON):</label>
                    <input type="text" name="transform_args" id="transform_args" 
                        placeholder="Ex: {'columns_to_drop': ['col1', 'col2']}" 
                        value="{}">
                    <small>Deve ser um JSON válido (será armazenado no campo JSON da tabela).</small>
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

        //Código legado do handsontable desabilitado
        //salvarTabelaNaSessao();

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
        const uriGroup = document.getElementById('source_uri_group');
        
        // Inputs
        const uploadInput = document.getElementById('source_upload_file');
        const pathInput = document.getElementById('source_path');
        const uriInput = document.getElementById('source_uri');

        // 2. Limpar e esconder todos por padrão
        // Se nada estiver selecionado, a função apenas limpa
        [uploadGroup, pathGroup, uriGroup].forEach(g => g.style.display = 'none');
        [uploadInput, pathInput, uriInput].forEach(i => {
            i.removeAttribute('required');
            i.value = ''; 
            i.name = 'temp_field'; // Renomeia para não submeter
        });
        
        let activeInput = null;

        // 3. Lógica Condicional (Baseada na string da descrição)
        if (sourceDescription.includes('csv') || sourceDescription.includes('json')) {
            // Mostrar UPLOAD (para CSV/JSON, assumindo que são uploads/MinIO)
            uploadGroup.style.display = 'block';
            uploadInput.setAttribute('required', 'required');
            activeInput = uploadInput;
            
        } else if (sourceDescription.includes('parquet')) {
            // Mostrar CAMINHO/PATH
            pathGroup.style.display = 'block';
            pathInput.setAttribute('required', 'required');
            activeInput = pathInput;
            
        } else if (sourceDescription.includes('mysql') || sourceDescription.includes('postgresql') || sourceDescription.includes('database')) {
            // Mostrar URI ou Campos de Conexão DB
            uriGroup.style.display = 'block';
            uriInput.setAttribute('required', 'required');
            activeInput = uriInput;
        }
        
        // 4. Mapeamento Crucial: O input ATIVO recebe o nome 'source_filename'
        if (activeInput) {
            activeInput.name = 'source_filename'; 
        }
    }

    // 5. Configura Listeners
    document.addEventListener('DOMContentLoaded', function() {
        toggleSourceInput(); // Executa na carga da página (para formulários de UPDATE)
        
        const selectElement = document.getElementById('id_source_type');
        if (selectElement) {
            selectElement.addEventListener('change', toggleSourceInput); // Executa na mudança
        }
    });

</script>

<?php
require VIEWPATH . '/footer.php';
?>
