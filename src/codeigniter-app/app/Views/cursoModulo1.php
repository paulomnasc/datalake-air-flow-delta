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
                        <strong>Introdução: Setup pré-curso no MyDataflow</strong>
                        <ul>
                            <li><a href="https://youtu.be/ug2PuC-5vMk" target="_blank" rel="noopener noreferrer">1. Aula 1            : Pré-requisitos do Curso</a></li>
                            <li><a href="https://forms.gle/9AK13338fxS6q5DL6" target="_blank" rel="noopener noreferrer">1.1 Pré-requisito    : Dar feedback para continuar ...</a></li>
                            
                        </ul>
                    </li>
                    <!-- Bloco 1: Fundamentos e Visão Arquitetural -->
                    <li>
                        <strong>Bloco 1: Fundamentos e Visão Arquitetural</strong>
                        <ul>
                            <li>
                                <strong>Aula 1: Ecossistema de Dados</strong><br>
                                <em>Foco:</em> Fundamentos, fluxo de informação e transformação de dados em ativo estratégico.<br>
                                <a href="https://youtu.be/6073YAGEq08" target="_blank">Link do vídeo</a>
                            </li>
                            <li>
                                <strong>Aula 3: O que é um Pipeline de Dados?</strong><br>
                                <em>Foco:</em> Desmistificação de conceitos e visão de baixo nível de um pipeline profissional.<br>
                                <a href="https://youtu.be/FO0rdnvunog" target="_blank">Link do vídeo</a>
                            </li>
                            <li>
                                <strong>Aula 4: Modelo de Stack para Delta Lake</strong><br>
                                <em>Foco:</em> Apresentação da arquitetura modelo que será construída no curso.<br>
                                <a href="https://youtu.be/uAHGwQIO3D8" target="_blank">Link do vídeo</a>
                            </li>
                        </ul>
                    </li>
                    <!-- Bloco 2: Infraestrutura e Setup do Ambiente -->
                    <li>
                        <strong>Bloco 2: Infraestrutura e Setup do Ambiente</strong>
                        <ul>
                            <li>
                                <strong>Aula 5: O Repositório de Dados (S3 com MinIO)</strong><br>
                                <em>Foco:</em> Criação de buckets usando MinIO (tecnologia AWS compatível) em ambiente local.<br>
                                <a href="https://drive.google.com/drive/folders/1RlIuwcfEtp_f16RRE6v7d-YHe0qPmo-X?usp=drive_link" target="_blank">Pasta de recursos</a> | <a href="https://youtu.be/xhekI4XH8V4" target="_blank">Link do vídeo</a>
                            </li>
                            <li>
                                <strong>Aula 6: Como Criar um Data Lake do Zero (Passo a Passo)</strong><br>
                                <em>Foco:</em> Implementação prática via Docker Compose e uso do repositório mini-datalake-stack.<br>
                                <a href="https://github.com/paulomnasc/mini-datalake-stack" target="_blank">Repositório do projeto</a> | <a href="https://youtu.be/jKkWjblc9oY" target="_blank">Link do vídeo</a>
                            </li>
                        </ul>
                    </li>
                    <!-- Bloco 3: Orquestração e Fluxo de Trabalho (Workflows) -->
                    <li>
                        <strong>Bloco 3: Orquestração e Fluxo de Trabalho (Workflows)</strong>
                        <ul>
                            <li>
                                <strong>Aula 7: Airflow Mão na Massa (Parte 2)</strong><br>
                                <em>Foco:</em> Funcionamento interno do Airflow, console web e integração entre componentes.<br>
                                <a href="https://youtu.be/ImJ8PG32-L8" target="_blank">Link do vídeo</a>
                            </li>
                            <li>
                                <strong>Aula 8: Data Lake do Zero + Entendendo o MyFlow Lab (Merge)</strong><br>
                                <em>Foco:</em> Execução de pipeline ELT real e uso do ambiente de laboratório para testes de fluxos.<br>
                                <a href="https://youtu.be/Er5N20_eTXE" target="_blank">Link do vídeo</a>
                            </li>
                        </ul>
                    </li>
                    <!-- Bloco 4: Engenharia Avançada e Qualidade -->
                    <li>
                        <strong>Bloco 4: Engenharia Avançada e Qualidade</strong>
                        <ul>
                            <li>
                                <strong>Aula 9: Implementando Data Quality (Mod2 Internal)</strong><br>
                                <em>Foco:</em> Criação de classes customizadas de validação para a arquitetura medalhão (Camada Silver).<br>
                                <a href="https://youtu.be/gKBUjoUaEe0" target="_blank">Link do vídeo</a>
                                <ul>
                                    <li><strong>Baixar recursos de aula aqui</strong></li>
                                    <li><a href="/assets/curso/A2/DQ%20Custom%20Validators.pptx" download="DQ Custom Validators.pptx">DQ Custom Validators.pptx</a></li>
                                    <li><a href="/assets/curso/A2/Invoice.json" download="Invoice.json">Invoice.json</a></li>
                                    <li><a href="/assets/curso/A2/meu_validador.py" download="meu_validador.py">meu_validador.py</a></li>
                                </ul>
                            </li>
                            <li>
                                <strong>Aula 10: Em construção - Criar um vídeo com explicação de conectar o PBI no Delta Lake</strong>
                            </li>
                        </ul>
                    </li>
                    <!-- Resumo da construção do conhecimento do aluno -->
                    <li>
                        <strong>Resumo da construção do conhecimento do aluno</strong>
                        <ul>
                            <li>Armazenamento: MinIO (S3)</li>
                            <li>Formato de Tabela: Delta Lake</li>
                            <li>Orquestração: Apache Airflow</li>
                            <li>Infra: Docker / Docker Compose</li>
                            <li>Qualidade: Python Custom Classes</li>
                            <li>Visualização (Sugestão Aula 10): Power BI conectado ao Delta Lake</li>
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
