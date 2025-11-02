<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content">

    <div class="container">
        <h1>Editar Quadro</h1>
        <!-- updQuadro.php -->
            
            
            <form method="post" id="meuFormularioUpload" action="<?php echo route_to('Quadro.fileUpload'); ?>" enctype="multipart/form-data">
                
            
                <div class="form-group">
                    <label for="arquivo">Arquivo Csv:</label>
                    <input type="file" id="arquivo" name="arquivo" required>
                    <label style="color: red;" > Atenção !!! O arquivo deve ter as colunas separadas pelo caractere vírgula.</label>
                </div>
                <button type="submit" class="save-button" onclick="submitMeuFormularioUpload()" value="Atualizar">Enviar</button>
                <!-- Div para exibir mensagens de sucesso ou erro -->
                <div id="upload-message" style="display: none; margin-top: 10px;"></div>
            </form>

            <div id="spreadSheet" class="hot" style="overflow: auto"></div>
            <!-- button id="download" onclick="downloadCSV(hotInstance)"  >Download CSV</button-->
            <!-- Botão para salvar as edições da tabela na sessão -->
            <div> 
                <button class="edit-button" id="save" name="save" style="display: none;">💾
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17 3h-10c-1.104 0-2 .896-2 2v14c0 1.104.896 2 2 2h10c1.104 0 2-.896 2-2v-13l-4-4zm-3 2v4h-4v-4h4zm2 14h-8v-2h8v2zm1-10h-10v-6h4v4h6v2z" fill="white"/>
                </svg>
                </button>

                <div id="save-sheet-message" style="display: none; margin-top: 10px;"></div>
            </div>
            <script>


                document.addEventListener('DOMContentLoaded', function() {
                    // Apenas pegue o valor direto e atribua, o que evita o uso de JSON.parse na string completa
                    let csv = <?php echo $conteudo_csv_json; ?>;
                    
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
                                        
                });

                const save = document.getElementById('save');
                const messageDivSheetMessage = document.getElementById('save-sheet-message');

                save.addEventListener('click', () => {
                // Save all cell's data
                const data = handsontableInstance.getData();

                console.log('handsontableInstance.getData()', data);
                console.log('JSON - ', JSON.stringify({ data }));

                fetch('<?= base_url('/salvarTabela') ?>', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                    'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ data }),
                })
                    .then((response) => {
                    return response.json(); // Parse the response as JSON
                    //console.log('R: ', response.json())
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

            <!-- FIM Carrega o conteúdo do arquivo da coluna da tabela do sgbd -->    
            
            

            <form method="post" id="meuFormulario" action="<?php echo route_to('Quadro.update'); ?>">

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
                    <label for="descricao">descricao:</label>
                    <input type="text" name="descricao" placeholder="descrição" value='<?php echo $descricao; ?>' required>
                </div>

                <div class="form-group">
                    <input type="hidden" id="nome_arquivo" name="nome_arquivo" value="<?php echo $nome_arquivo ?>" readonly required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="save-button" value="Atualizar">Atualizar</button>
                    <button type="button" class="back-button" onclick="Voltar()">Voltar</button>
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