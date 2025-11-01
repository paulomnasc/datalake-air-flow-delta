<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>
<style>

        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .donation-container {
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ccc;
            background-color: #fff;
            max-width: 400px;
        }
        
        .thank-you {
            font-size: 1.5em;
            margin-bottom: 20px;
        }
    

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

        /* .box {
            width: 500px;
            height: 50px;
            background-color: lightblue;
            text-align: center;
            line-height: 50px;
            border: 1px solid #000;
        } */

        .donation-container img {
            width: 300px;
            height: 300px;
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
        <div class="thank-you">
            Obrigado por considerar uma doação! 🙏
        </div>

        <div class="donation-container">
            <h2>Doar R$ 10</h2>
            <img src="<?= base_url('assets/img/qrcode-pix-10.png'); ?>" alt="QR Code para doação de R$ 10">
        </div>

        <div class="donation-container">
            <h2>Doar R$ 20</h2>
            <img src="<?= base_url('assets/img/qrcode-pix-20.png'); ?>" alt="QR Code para doação de R$ 20">
        </div>

    
    </div>




<?php
require VIEWPATH . '/footer.php';
?>