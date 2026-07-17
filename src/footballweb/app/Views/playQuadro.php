<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>
<style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        table {
            border-collapse: collapse;
            margin: 20px auto;
            width: 90%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #4CAF50;
            color: white;
        } 

        .fixed {
            background-color: lightgray;
        } 

        .correct {
            background-color: lightgreen;
        }

        .container {
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 2.5vh;
        }

        .box {
            width: 500px;
            height: 50px;
            background-color: lightblue;
            text-align: center;
            line-height: 50px;
            border: 1px solid #000;
        }

        
 

       .content {
            margin-top: 50px; /* Espaço maior para garantir que a tabela fique abaixo da barra de status */
            width: 100%;
        }

        
        
        h4 {
            text-align: center;
        }
    </style>
     

    <div id="content">
        <br>
        <div id="div-tabela"  >
                <h2>Nome Quadro: <?= $descricao ?> </h2>
                <div id="status-bar">Barra de Status Fixa
                    
                </div>

                <div id="div-tabela" class="div-tabela">
                    <table id="tabela">
                        <!-- A tabela será gerada dinamicamente pelo JavaScript -->
                    </table>
                </div>
                
            
            <script>
                //var quadro = JSON.parse('<!?= $quadro ?>');
                var conteudo_arquivo = '<?= $conteudo_arquivo ?>';
                console.log('base64Decode(conteudo_arquivo): ',base64Decode(conteudo_arquivo));
                conteudo_arquivo_decoded = base64Decode(conteudo_arquivo);
                handleText(conteudo_arquivo_decoded);
                //console.log("Arquivo to play: ", conteudo_arquivo); */
                /* var nomeArquivoCSV = "<!?= base_url('uploads/'. $_SESSION['id_usuario_logado']. '/' . $nome_arquivo); ?>";

                if (nomeArquivoCSV) {
                    console.log(nomeArquivoCSV);
                    handleFile(nomeArquivoCSV);
                } */

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
            
            </script>                

        

        </div>
    
    </div>


</div>
<div class="" style="float: right;">
    <button type="button" class="nav-button" onclick="Voltar()">Voltar</button>
</div>
<?php
require VIEWPATH . '/footer.php';
?>