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
        <br>
        <br>
        <div>
        
            <h2>1. Aceitação dos Termos</h2>
        <p>Ao acessar este site, você concorda em cumprir estes Termos de Uso, bem como todas as leis e regulamentos aplicáveis. Caso não concorde com algum desses termos, você está proibido de utilizar ou acessar este site. Os materiais contidos neste site são protegidos pelas leis de direitos autorais e marcas comerciais aplicáveis.</p>

        <h2>2. Licença de Uso</h2>
        <p>É concedida permissão para baixar temporariamente uma cópia dos materiais (informações ou software) neste site, exclusivamente para visualização pessoal e não comercial. Esta concessão de licença não implica transferência de titularidade e está sujeita às seguintes restrições:</p>
        <ul>
            <li>Não é permitido modificar ou copiar os materiais;</li>
            <li>Não é permitido utilizar os materiais para qualquer finalidade comercial ou para exibição pública (comercial ou não comercial);</li>
            <li>Não é permitido tentar descompilar ou realizar engenharia reversa de qualquer software contido no site;</li>
            <li>Não é permitido remover quaisquer direitos autorais ou outras notificações de propriedade dos materiais;</li>
            <li>Não é permitido transferir os materiais para outra pessoa ou espelhá-los em qualquer outro servidor.</li>
        </ul>
        <p>Esta licença será automaticamente rescindida caso você viole alguma dessas restrições e poderá ser encerrada a qualquer momento pelo detentor dos direitos. Ao encerrar o uso dos materiais ou ao término desta licença, você deve excluir todos os materiais baixados em sua posse, seja em formato eletrônico ou impresso.</p>

        <h2>3. Isenção de Responsabilidade</h2>
        <p>Os materiais disponibilizados neste site são fornecidos "no estado em que se encontram", sem garantias expressas ou implícitas. O site isenta-se de quaisquer garantias, incluindo, sem limitação, garantias implícitas de comercialização, adequação a um propósito específico ou não violação de direitos de propriedade intelectual ou outros direitos.</p>

        <h2>4. Limitações de Responsabilidade</h2>
        <p>Em nenhuma circunstância o site ou seus fornecedores serão responsáveis por quaisquer danos, incluindo, mas não se limitando a, perda de dados, lucros cessantes ou interrupção de negócios, decorrentes do uso ou da impossibilidade de uso dos materiais neste site, mesmo que um representante autorizado tenha sido notificado da possibilidade de tais danos.</p>

        <h2>5. Precisão das Informações</h2>
        <p>Os materiais apresentados neste site podem conter erros técnicos, tipográficos ou fotográficos. O site não garante que qualquer material seja preciso, completo ou atualizado. Alterações podem ser realizadas a qualquer momento, sem aviso prévio. No entanto, não há compromisso em atualizar os materiais.</p>

        <h2>6. Links para Terceiros</h2>
        <p>O site não revisa todos os links incluídos em sua plataforma e não se responsabiliza pelo conteúdo de sites vinculados. A inclusão de um link não implica endosso por parte do site. O uso de qualquer site vinculado é por conta e risco do usuário.</p>

        <h2>7. Modificações nos Termos de Uso</h2>
        <p>O site pode revisar estes Termos de Uso a qualquer momento, sem aviso prévio. Ao continuar utilizando o site, você concorda em se submeter à versão mais recente dos Termos de Uso.</p>

        <h2>8. Lei Aplicável</h2>
        <p>Estes Termos de Uso são regidos e interpretados de acordo com as leis vigentes no território aplicável, e qualquer litígio será submetido à jurisdição exclusiva dos tribunais competentes na referida localidade.

        </p>
    </div>


</div>

<?php
require VIEWPATH . '/footer.php';
?>