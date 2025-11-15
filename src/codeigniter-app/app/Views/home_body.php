<style>
    .btn-primary a {
        color: white !important; /* Força a cor do link para branco */
        text-decoration: none; /* Remove a sublinha do link */
    }

    .btn-primary a:hover {
        color: #f0f0f0 !important; /* Força a cor do link ao passar o mouse para #f0f0f0 */
    }

    .btn-primary .fa-arrow-right {
        color: white !important; /* Força a cor do ícone para branco */
    }

    .btn-primary a:hover .fa-arrow-right {
        color: #f0f0f0 !important; /* Força a cor do ícone ao passar o mouse para #f0f0f0 */
    }

    .carousel {
        width: 40%;  /* Ajuste a largura conforme necessário */
        max-width: 1200px;  /* Largura máxima para manter a proporção */
        height: auto;  
        margin: 20px auto;
    }


        .slick-slide {
            text-align: center;
        }

        .slick-slide img {
            width: 100%;
            border-radius: 8px;
        }


    .carousel-text {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 10px;
            border-radius: 8px;
    }

    .img-config {
        width: 100%;  /* Garante que a imagem ocupe toda a largura disponível */
        height: 400px;  /* Define uma altura fixa */
        object-fit: cover;  /* Mantém as proporções da imagem enquanto preenche o contêiner */
    }

    p{
        display: none;
        float: right;
        font-size: 2rem;
        font-weight: bold;
    }

    /* Força a rolagem horizontal se necessário */
    .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
    }

</style>


    
    <!-- Navbar Start 
                <?php echo anchor("logarUsuarioAnonimo","Experimente agora")  ?>
                <?php echo anchor("politica","Política Privacidade")  ?>
                <?php echo anchor("contactUs","Entre em contato")  ?>
            
    <!- Navbar End -->


    <div>

        
        <h1 style="text-align: center;">Memorize jogando AGORA !!!</h1>


        <div class="carousel">
            <img src="assets/anime/Gif_anuncio_reduced.gif" 
            alt="Animação de países e capitais" 
            width="100%" height="auto" style="max-width: 800px;">
        </div>

        <h3 style="text-align: center;">Como as tabelas resumo podem me ajudar com os meus estudos ?</h3>
        <br>
        
        <div style="font-size: 17px; margin-left: 20px; margin-right: 20px; text-align: justify;">
            
            As tabelas resumo e quadros sinópticos são ferramentas eficazes para ajudar nos estudos, pois organizam e sintetizam informações de forma clara e estruturada. 
            Elas permitem condensar conteúdos complexos em blocos menores e mais compreensíveis, destacando os pontos principais e facilitando a revisão rápida. 
            Utilizando uma tabela, você pode visualizar informações essenciais, como conceitos, definições ou comparações, de forma mais objetiva, sem a necessidade 
            de reler textos longos.

            Elas também ajudam a identificar padrões e relações entre diferentes temas, o que facilita a memorização. Além disso, ao organizar as ideias principais
             em uma tabela, você pode revisar com mais agilidade, o que é útil em períodos de preparação para provas ou apresentações. Essas tabelas podem ser personalizadas para atender a diferentes tipos de conteúdo, como cronologias, fórmulas, eventos históricos ou características de um conceito.

            Em resumo, as tabelas resumo são uma maneira prática de otimizar o tempo de estudo, focando no que é mais relevante e facilitando a retenção das informações.
            <br><br>
                Assista o vídeo, clicando em <?php echo anchor("saibaMais", "saiba mais") ?> para assistir nossos tutoriais. 
                <br>
        </div>

    </div>

    
    <!-- Carrossel -->
    <div class="carousel">

        

        <div>
            <img  class="img-config"  src="<?= base_url('assets/templates/img/course-1.jpg'); ?>"  alt="Estudo">
            <div class="carousel-text">Estudo</div>
        </div>
        <div>
            <img class="img-config" src="<?= base_url('assets/templates/img/cerebro.webp'); ?>" alt="Memória">
            <div class="carousel-text">Memória</div>
        </div>
        <div>
            <img class="img-config" src="<?= base_url('assets/templates/img/course-3.jpg'); ?>" alt="Resultados">
            <div class="carousel-text">Resultados</div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"></script>
    <script>
        $(document).ready(function(){
            $('.carousel').slick({
                autoplay: true,
                autoplaySpeed: 2000,
                dots: true,
                infinite: true,
                speed: 500,
                fade: true,
                cssEase: 'linear'
            });
        });
    </script>
    <!-- Fim carrossel -->

    <div>

    <h2 style="text-align: center;">Por quê otimizar seus estudos com tabelas de resumo (ou quadros sinópticos) ?</h2>
        <br>
        <h3 style="text-align: center;">Motivo 1: Personalização e Flexibilidade</h3>
        <br>
            <div style="font-size: 17px; margin-left: 20px; margin-right: 20px; text-align: justify;">
                    As tabelas de estudo são como mapas do tesouro para o seu aprendizado. 
                Elas te ajudam a navegar por conteúdos complexos de forma rápida e eficiente. 
                Ao criar suas próprias tabelas, você personaliza a organização das informações de acordo com sua forma de aprender, 
                destacando os pontos-chave e as relações entre os conceitos. 
                <br><br>
                É como ter um guia personalizado para cada matéria, facilitando a revisão e a memorização. 
                <br><br>
                Além disso, as tabelas são versáteis e podem ser adaptadas para qualquer disciplina, desde as exatas até as humanas.
                As tabelas de estudo são como mapas do tesouro para o seu aprendizado. Elas te ajudam a navegar por conteúdos complexos 
                de forma rápida e eficiente. 
                <br><br>
                Ao criar suas próprias tabelas, você personaliza a organização das informações de acordo com sua forma de aprender, 
                destacando os pontos-chave e as relações entre os conceitos. 
                É como ter um guia personalizado para cada matéria, facilitando a revisão e a memorização. 
                Além disso, as tabelas são versáteis e podem ser adaptadas para qualquer disciplina, desde as exatas até as humanas.
                <br><br>
                Assista o vídeo , clicando em <?php echo anchor("saibaMais", "saiba mais") ?> para assistir nossos tutoriais. 
                <br>
            </div>
            <br>
            <h3 style="text-align: center;">Motivo 2: Organização e Visualização</h3>
            <br>
            <div style="font-size: 17px; margin-left: 20px; margin-right: 20px; text-align: justify;">
                Imagine transformar um texto denso e confuso em um diagrama colorido e organizado. 
                <br><br>
                As tabelas de estudo fazem exatamente isso! 
                <br><br>
                Elas são a ferramenta perfeita para visualizar as informações de forma clara e concisa, facilitando a compreensão e a memorização. 
                Ao organizar os dados em linhas e colunas, você cria uma estrutura visual que ajuda o seu cérebro a conectar as ideias de forma 
                mais eficiente. É como ter um quebra-cabeça que você mesmo monta, peça por peça.
                <br><br>
                Assista o vídeo , clicando em <?php echo anchor("saibaMais", "saiba mais") ?> para assistir nossos tutoriais. 
                <br>
            </div>
            <br>
            <h3 style="text-align: center;">Motivo 3: Eficiência para Provas e Apresentações</h3>
            <br>
            <div style="font-size: 17px; margin-left: 20px; margin-right: 20px; text-align: justify;">
                Está se preparando para uma prova ou apresentação? 
                <br><br>
                As tabelas de estudo são suas aliadas! 
                Elas te ajudam a otimizar o tempo de revisão, condensando o conteúdo em um formato compacto e fácil de consultar.
                <br><br> 
                Ao invés de reler páginas e páginas de texto, você pode rapidamente encontrar a informação que precisa na sua tabela. 
                <br><br>
                Além disso, as tabelas te ajudam a identificar os pontos mais importantes e a construir argumentos mais sólidos, 
                garantindo um desempenho melhor em suas avaliações.

                <br><br>
                Assista o vídeo , clicando em <?php echo anchor("saibaMais", "saiba mais") ?> para assistir nossos tutoriais. 
                <br>
            </div>


    
    <br>
        <div style="font-size: 17px; margin-left: 20px; margin-right: 20px; text-align: justify;">
            <h2 style="text-align: center;">Exemplos de resumos com quadros sinópticos</h2>
                <br>
                
                <h4>1. Revolução Industrial</h4><?php echo anchor("saibaMais", "Para tutorial clique aqui") ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <tr>
                            <th>Aspecto</th>
                            <th>Primeira Revolução (Séc. XVIII)</th>
                            <th>Segunda Revolução (Séc. XIX)</th>
                            <th>Terceira Revolução (Séc. XX e XXI)</th>
                        </tr>
                        <tr>
                            <td>Principais Inovações</td>
                            <td>Máquina a vapor, tear mecânico</td>
                            <td>Eletricidade, motor a combustão</td>
                            <td>Robótica, informática, IA</td>
                        </tr>
                        <tr>
                            <td>Principais Setores</td>
                            <td>Têxtil, mineração</td>
                            <td>Transporte, siderurgia</td>
                            <td>Tecnologia, automação</td>
                        </tr>
                        <tr>
                            <td>Impacto na Sociedade</td>
                            <td>Urbanização, trabalho fabril</td>
                            <td>Crescimento industrial, migrações</td>
                            <td>Globalização, novas profissões</td>
                        </tr>
                    </table>
                </div>

                <h4>2. Funções da Linguagem</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <tr>
                            <th>Função</th>
                            <th>Características</th>
                            <th>Exemplo</th>
                        </tr>
                        <tr>
                            <td>Referencial</td>
                            <td>Informativa, objetiva</td>
                            <td>Notícia, relatório</td>
                        </tr>
                        <tr>
                            <td>Emotiva</td>
                            <td>Expressa sentimentos</td>
                            <td>Diário, poesia</td>
                        </tr>
                        <tr>
                            <td>Conativa</td>
                            <td>Persuasiva, direcionada ao receptor</td>
                            <td>Propaganda, discurso político</td>
                        </tr>
                        <tr>
                            <td>Fática</td>
                            <td>Testa o canal de comunicação</td>
                            <td>"Alô?", "Está me ouvindo?"</td>
                        </tr>
                        <tr>
                            <td>Poética</td>
                            <td>Estética, criatividade na linguagem</td>
                            <td>Poema, música</td>
                        </tr>
                        <tr>
                            <td>Metalinguística</td>
                            <td>Explica o próprio código</td>
                            <td>Dicionário, gramática</td>
                        </tr>
                    </table>
                </div>
                <h4>3. Classificação dos Seres Vivos</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <tr>
                            <th>Reino</th>
                            <th>Características</th>
                            <th>Exemplos</th>
                        </tr>
                        <tr>
                            <td>Monera</td>
                            <td>Unicelulares, procariontes</td>
                            <td>Bactérias, cianobactérias</td>
                        </tr>
                        <tr>
                            <td>Protista</td>
                            <td>Unicelulares ou pluricelulares, eucariontes</td>
                            <td>Protozoários, algas</td>
                        </tr>
                        <tr>
                            <td>Fungi</td>
                            <td>Heterótrofos, parede celular de quitina</td>
                            <td>Cogumelos, leveduras</td>
                        </tr>
                        <tr>
                            <td>Plantae</td>
                            <td>Autotróficos, parede celular de celulose</td>
                            <td>Árvores, gramíneas</td>
                        </tr>
                        <tr>
                            <td>Animalia</td>
                            <td>Heterótrofos, pluricelulares</td>
                            <td>Mamíferos, aves, répteis</td>
                        </tr>
                    </table>
                </div>
        </div>
    </div>
    <!-- div para o vídeo explicativo a ser produzido -->
    <!--div id="video-container">
        <div id="video-title-bar">Entenda como estudar com quadros sinópticos de memória</div>
        <iframe width="600" height="400" src="https://www.youtube.com/embed/QBU1i0ZUzg4" title="YouTube video" frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen>
        </iframe>
    </div-->

    <!-- fim div para o vídeo explicativo a ser produzido -->

