<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="SN_1k1RhCAE6F7CIT8Zlp2mKiGUKH4rM1ji7BXAcsJs" />
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2926761252260319"
     crossorigin="anonymous"></script>
    <!-- meta name="description" 
    content="Descubra jogos interativos para estudo, incluindo jogos de memória e técnicas
     para memorização de conteúdos. 
     Aprenda com tabelas resumo que tornam o estudo mais fácil e divertido!"
    -->

    <?= session()->get('metaTags') ?? '' ?>
    <!-- title>Tabelas Resumo</title-->
    
    
    

    <!-- link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css"-->
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/css/styles.css'); ?>">

    <!-- Referências do Handsontable (Spreadsheet)  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>

    <script src="<?= base_url('assets/js/script.js'); ?>"></script>

    <script src="<?= base_url('assets/js/scriptLoadTables.js'); ?>"></script>

    <script src="<?= base_url('assets/js/handsontable.js'); ?>"></script>
    

    <!-- Funcionalidade do google auth -->
    <!-- script defer src="https://accounts.google.com/gsi/client" async defer></script-->

    <!-- meta content="" name="keywords">
    <meta content="" name="description"-->

    <!-- Favicon -->
    
    <link href="<?= base_url('assets/templates/img/favicon.ico'); ?>" rel="icon">

    <!-- Google Web Fonts -->
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- "<!?= base_url("assets/templates/css/style.css"); ?>" -->
    <!-- Libraries Stylesheet -->
    <link href="<?= base_url('assets/templates/lib/animate/animate.min.css'); ?>" rel="stylesheet">
    <link href="<?= base_url('assets/templates/lib/owlcarousel/assets/owl.carousel.min.css'); ?>" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="<?= base_url("assets/templates/css/style.css"); ?>" rel="stylesheet">

    <!-- Carrossel -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css">
    
        
    <script src="<?= base_url("assets/templates/lib/wow/wow.min.js"); ?>"></script>
    <script src="<?= base_url("assets/templates/lib/easing/easing.min.js"); ?>"></script>
    <script src="<?= base_url("assets/templates/lib/waypoints/waypoints.min.js"); ?>"></script>
    <script src="<?= base_url("assets/templates/lib/owlcarousel/owl.carousel.min.js"); ?>">></script>

    <!-- Template Javascript -->
    <script src="<?= base_url("assets/templates/js/main.js"); ?>">></script>


    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>

    <!--  ----------  INICIO BOOTSTRAP  --------------------------------------------------------------------------  -->
    
    <!-- CSS do Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- JS do Bootstrap 5 (inclui Popper.js automaticamente) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!--  ----------  FIM BOOTSTRAP  --------------------------------------------------------------------------  -->

    <!-- INÍCIO ESTILO DA SIDEBAR -->

    <style>
    /* Estilo da Sidebar */
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 250px;
      background-color: #343a40; /* Cor do fundo */
      color: white;
      padding: 15px;
      transform: translateX(-100%); /* Inicialmente oculta */
      transition: transform 0.3s ease; /* Animação suave */
      z-index: 1050; /* Sobrepõe o conteúdo */
    }
    .sidebar-overlay.active {
      transform: translateX(0); /* Mostra a sidebar */
    }
    /* Estilo do fundo de overlay */
    .overlay-background {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 100%;
      background: rgba(0, 0, 0, 0.5); /* Fundo semitransparente */
      z-index: 1049; /* Atrás da sidebar */
      display: none; /* Oculto por padrão */
    }
    .overlay-background.active {
      display: block; /* Mostra o fundo */
    }

    #head-bar {
        background-image: url("/assets/img/head bg.png" );
        background-color: #cccccc;
        background-size: 100% 155px;
        background-repeat: no-repeat;
        border: 1px solid #333; /* Borda com cor sólida */
        border-radius: 5px; /* Opcional: cantos arredondados */
        width: 100%; /* Largura total do navegador */
        height: 155px; /* Altura fixa */
    }

  </style>

  <!-- FIM ESTILO DA SIDEBAR -->

    
</head>
<body>

    <!-- INÍCIO DA SIDEBAR ------------------------------------------------------------------------ -->
        
        <div id="head-bar" class="left">
            <!-- Botão para abrir a sidebar -->
            <button id="openSidebarBtn" class="btn btn-primary m-3">
                <i class="bi bi-person"></i>
                <!--?php echo isset($_SESSION['nome_usuario_logado']) ? $_SESSION['nome_usuario_logado'] : 'Visitante'; ?-->
            </button>
            <img src="<?= base_url("/assets/img/logo.png" ); ?>" alt="" style="height: 130px; width:150px; float: right;" > </img>
        </div>
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar-overlay">

            <div class="right">

                <button id="closeSidebarBtn" class="">X</button>

            </div>
    
            <li>
                <p  class="text-white">Olá <?php echo isset($_SESSION['nome_usuario_logado']) ? $_SESSION['nome_usuario_logado'] : 'Visitante'; ?></p>
            </li>

            <ul class="list-unstyled">
                
                <li>
                    <?php if (!isset($_SESSION['nome_usuario_logado']) || empty($_SESSION['nome_usuario_logado'])): ?>
                        
                        <?php echo anchor("logarUsuarioAnonimo","Experimentar", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                        
                        <?php echo anchor("loginUsuario","Entrar", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                        
                        <!-- ?php echo anchor("sigInUsuario","Assinar")  ?-->    

                    <?php endif; ?>
                </li>


                <!-- SE O USUÁRIO ESTÁ LOGADO E É O ADMINISTRADOR -->

                <li>

                    <?php 
                        // Verifica se o perfil do usuário está logado e se ele é "Admin"
                        if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] === "Admin"): 
                    ?>
                    <li><p class="text-white">Administrar</p></li>
                        <?php echo anchor("listPerfil", "Perfis", ['class' => 'nav-link px-4 px-lg-5']) ?>
                        <?php echo anchor("listUsuario", "Usuários", ['class' => 'nav-link px-4 px-lg-5']) ?>
                    <?php
                    endif; 
                    ?>

                </li>    

                <li>

                    <?php if (isset($_SESSION['nome_usuario_logado']) || !empty($_SESSION['nome_usuario_logado'])): ?>
                        <?php echo anchor(route_to('Usuario.logOut'), "Sair", ['class' => 'nav-link px-4 px-lg-5']); ?>
                    <?php endif; ?>


                </li>
                

                
            </ul>

        </div>

    <!-- Fundo de overlay -->
    <div id="overlayBackground" class="overlay-background"></div>


    <script>

    // Referências aos elementos
    const sidebar = document.getElementById('sidebar');
    const overlayBackground = document.getElementById('overlayBackground');
    const openSidebarBtn = document.getElementById('openSidebarBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');

    // Abrir a sidebar
    openSidebarBtn.addEventListener('click', () => {
        event.preventDefault();
        sidebar.classList.add('active');
        overlayBackground.classList.add('active');
    });

    // Fechar a sidebar
    closeSidebarBtn.addEventListener('click', () => {
        event.preventDefault();
        sidebar.classList.remove('active');
        overlayBackground.classList.remove('active');
    });

    // Fechar a sidebar ao clicar no fundo
    overlayBackground.addEventListener('click', () => {
        event.preventDefault();
        sidebar.classList.remove('active');
        overlayBackground.classList.remove('active');
    });
</script>
<!-- FIM DA SIDEBAR ------------------------------------------------------------------------ -->




<div id="success-message" class="alert alert-success" style="display:none;"></div>
<div id="error-message" class="alert alert-warning" style="display:none;"></div>

<div id="content">

<div id="main-content">

    <!-- button class="nav-button" onclick="toggleSidebar()">☰</button-->

    <!-- side bar -->
    <nav class="navbar navbar-expand-lg bg-white navbar-light shadow sticky-top p-0">
        
        <button type="button" class="navbar-toggler me-4" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav">
            <div>
                
                <input type="hidden" id="perfil_usuario_logado" value="<?php echo isset($_SESSION['perfil_usuario_logado']) ? $_SESSION['perfil_usuario_logado'] : 'N/A'; ?>" readonly>
            </div>    

            <?php echo anchor("/", "INÍCIO", ['class' => 'nav-link px-4 px-lg-5']) ?>


            <div id="itens-menu-outros" class="navbar-nav ms-auto p-4 p-lg-0">
                
            
                <?php if (!isset($_SESSION['nome_usuario_logado']) || empty($_SESSION['nome_usuario_logado'])): ?>
                    
                    <?php echo anchor("logarUsuarioAnonimo","Experimentar", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                    

                <?php endif; ?>


                <!-- Dropdown -->
                <?php
                        // Verifica se o perfil do usuário está logado e se ele NÃO é "Anonimo"
                            if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Visitante"): 
                ?> 
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbardrop" data-bs-toggle="dropdown">
                        AÇÕES JOGO
                    </a>
                    <div class="dropdown-menu">
                        <?php
                        // Verifica se o perfil do usuário está logado e se ele NÃO é "Anonimo"
                            if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Anonimo"): 
                        ?>
                            <?php echo anchor("listPasta", "Pastas", ['class' => 'nav-link px-4 px-lg-5']) ?>
                            <?php echo anchor("listConfig", "Configs", ['class' => 'nav-link px-4 px-lg-5']) ?>
                        <?php elseif (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] === "Anonimo"): ?>
                            <?php echo anchor("listConfig", "Configs", ['class' => 'nav-link px-4 px-lg-5']) ?>
                        <?php endif; ?>
                    </div>
                </li>
                <!-- Fim Dropdown -->
                <?php endif; ?>

                <?php echo anchor("politica","Política Privacidade", ['class' => 'nav-link px-4 px-lg-5'])  ?>

                <?php echo anchor("tdu","Termos de uso", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                
                <?php echo anchor("contactUs","Entre em contato", ['class' => 'nav-link px-4 px-lg-5'])  ?>

                <?php echo anchor("sigInUsuario", "Registre-se", ['class' => 'nav-link px-4 px-lg-5']) ?>
                
            </div>

            </ul>

        </div>
    
    </nav>
    <!-- fecha sidebar -->


    <div class="sidebyside-container">


    
    

    

    <form id="submitSaibaMais" method="POST" action="<?php echo route_to('saibaMais'); ?>">

            <button id="btn-saiba-mais" type="submit" class="nav-button" >Saiba mais
                <i class="fas fa-info-circle" style="margin-left: 5px;"></i>
            </button>
        
    </form>

    <!--form id="submitDonate" method="POST" action="<!?php echo route_to('donate'); ?>">

        <button type="submit" class="nav-button">Doe $
            <i class="fas fa-money"></i>
            
        </button>

    </form-->

    
</div>

