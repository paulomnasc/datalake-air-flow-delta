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

        <!-- Conteúdo aqui -->
         
        <!-- div para o vídeo explicativo a ser produzido -->
        <div id="video-container">
            <div id="video-title-bar">Entenda como estudar com quadros sinópticos de memória</div>
            <iframe width="600" height="400" src="https://www.youtube.com/embed/QBU1i0ZUzg4" title="YouTube video" frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen>
            </iframe>
        </div>

        <div id="video-container">
            <div id="video-title-bar">Criando jogos de memória com imagens</div>
            <iframe width="600" height="400" src="https://www.youtube.com/embed/kJnS6a2vxJk" title="YouTube video" frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen>
            </iframe>
        </div>

    <!-- fim div para o vídeo explicativo a ser produzido -->

    
    </div>


</div>

<?php
require VIEWPATH . '/footer.php';
?>