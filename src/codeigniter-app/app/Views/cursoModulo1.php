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
                        <strong>Módulo 1: Introdução ao Dataflow</strong>
                        <ul>
                            <li>Aula 1: Apresentação do Curso</li>
                            <li>Aula 2: O que é Data Lake?</li>
                            <li>Aula 3: Arquitetura do Projeto</li>
                            <li>Aula 4: Primeiros Passos no Ambiente</li>
                            <li>Aula 5: Exercício Prático</li>
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
