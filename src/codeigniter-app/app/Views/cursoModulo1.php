<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container">
        <h4 style="text-align: center;">Módulo 1 - Estrutura do Curso</h4>
        <div class="card mt-4">
            <div class="card-body">
                <ul class="tree">
                    <li>
                        <strong>Módulo 1: Data Quality com MyDataflow</strong>
                        <ul>
                            <li><a href="https://youtu.be/ug2PuC-5vMk" target="_blank" rel="noopener noreferrer">1. Aula 1            : Pré-requisitos do Curso</a></li>
                            <li><a href="https://forms.gle/9AK13338fxS6q5DL6" target="_blank" rel="noopener noreferrer">1.1 Pré-requisito    : Dar feedback para continuar ...</a></li>
                            <li>
                                <a href="https://youtu.be/gKBUjoUaEe0" target="_blank" rel="noopener noreferrer">2. Aula 2            : Implementando validação de campos nulos, normalizar nomes de colunas e data quality score</a>
                                <ul>
                                    <li><strong>Baixar recursos de aula aqui</strong></li>
                                    <li><a href="/assets/curso/A2/DQ%20Custom%20Validators.pptx" download>DQ Custom Validators.pptx</a></li>
                                    <li><a href="/assets/curso/A2/Invoice.json" download>Invoice.json</a></li>
                                    <li><a href="/assets/curso/A2/meu_validador.py" download>meu_validador.py</a></li>
                                </ul>
                            </li>
                            
                        </ul>
                    </li>
                </ul>
                <p class="text-muted mt-3">Outros módulos serão adicionados em breve...</p>
            </div>
        </div>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
<style>
.tree ul {
    list-style-type: none;
    margin-left: 1.5em;
    padding-left: 1em;
    border-left: 2px solid #764ba2;
}
.tree li {
    margin: 0.5em 0;
    position: relative;
}
.tree li:before {
    content: '';
    position: absolute;
    left: -1.2em;
    top: 0.7em;
    width: 1em;
    height: 0.2em;
    background: #764ba2;
}
.tree li strong {
    color: #764ba2;
}
</style>
