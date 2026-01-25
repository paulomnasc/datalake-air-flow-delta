<!-- Modal de Termos de Uso -->
<div id="termsModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:90vw; max-width:600px; max-height:80vh; border-radius:8px; overflow:hidden; display:flex; flex-direction:column;">
        <div style="padding:16px; border-bottom:1px solid #eee; font-weight:bold;">Termos de Uso</div>
        <div id="termsContent" style="flex:1; overflow-y:auto; padding:16px; font-size:0.95em; background:#fafafa; color:#111;"></div>
        <div style="padding:16px; border-top:1px solid #eee; display:flex; flex-direction:column; gap:8px;">
            <label style="display:flex; align-items:center; gap:8px; color:#111;">
                <input type="checkbox" id="agreeCheckbox" disabled />
                Li e concordo com os termos
            </label>
            <button id="proceedBtn" disabled style="padding:8px 16px; border:none; background:#1976d2; color:#fff; border-radius:4px; cursor:pointer;">Prosseguir</button>
            <button id="closeModalBtn" style="padding:6px 12px; border:none; background:#eee; color:#333; border-radius:4px; cursor:pointer;">Cancelar</button>
        </div>
    </div>
</div>

<!-- Aviso de Vencimento de Assinatura -->
<?php if (isset($_SESSION['subscription_show_warning']) && $_SESSION['subscription_show_warning']): ?>
    <?php
        $diasRestantes = $_SESSION['subscription_days_remaining'] ?? 0;
        $statusAssinatura = $_SESSION['subscription_status'] ?? 'trial';
        $mensagemAviso = \App\Helpers\SubscriptionHelper::obterMensagemAviso($diasRestantes, $statusAssinatura);
        $classeAlerta = \App\Helpers\SubscriptionHelper::obterClasseAlerta($diasRestantes);
    ?>
    <div id="subscription-warning" class="alert <?= $classeAlerta ?>" 
         style="position: fixed; top: 20px; right: 20px; z-index: 9998; max-width: 400px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); animation: slideIn 0.5s ease;">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="float: right;"></button>
        <strong>⏰ Atenção!</strong>
        <p style="margin: 8px 0;"><?= htmlspecialchars($mensagemAviso, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?= base_url('subscription/renew') ?>" class="btn btn-sm <?= ($diasRestantes <= 2) ? 'btn-danger' : 'btn-warning' ?>" style="margin-top: 8px;">
            🔄 Renovar Agora
        </a>
    </div>
    <style>
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
<?php endif; ?>

<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/695ad75f79755a198313a178/1je5dijbn';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->

<script>
function openTermsModal(termsText, onProceed) {
    const modal = document.getElementById('termsModal');
    const content = document.getElementById('termsContent');
    const checkbox = document.getElementById('agreeCheckbox');
    const proceedBtn = document.getElementById('proceedBtn');
    const closeBtn = document.getElementById('closeModalBtn');

    content.innerHTML = termsText.replace(/\n/g, '<br>');
    content.scrollTop = 0;
    checkbox.checked = false;
    checkbox.disabled = true;
    proceedBtn.disabled = true;

    content.onscroll = function() {
        if (content.scrollTop + content.clientHeight >= content.scrollHeight - 2) {
            checkbox.disabled = false;
        }
    };

    checkbox.onchange = function() {
        proceedBtn.disabled = !checkbox.checked;
    };

    proceedBtn.onclick = function() {
        modal.style.display = 'none';
        if (typeof onProceed === 'function') onProceed();
    };

    closeBtn.onclick = function() {
        modal.style.display = 'none';
    };

    modal.style.display = 'flex';
}

document.addEventListener('DOMContentLoaded', function() {
    // Seleciona todos os links/botões relevantes por texto
    const termsLinks = Array.from(document.querySelectorAll('a,button')).filter(el => {
        const txt = (el.textContent || '').trim().toLowerCase();
        return (
            txt === 'experimentar' ||
            txt === 'registre-se' ||
            txt === 'inscrever-se' ||
            el.classList.contains('terms-link')
        );
    });
    const termsText = `Termos de Adesão: MyFlow Lab - Founder's Club 🚀\n\nOlá, Fundador(a)!\nVocê está sendo convidado(a) para participar da fase de nascimento do MyFlow Lab. Este é o nosso ambiente de experimentação onde você terá acesso às ferramentas e infraestruturas de dados que utilizo em meus tutoriais.\n\nAo clicar em \"De Acordo\", você aceita as seguintes condições de participação:\n\n1. O Período de Experiência (Free Pass)\nVocê terá 30 dias de acesso gratuito e irrestrito ao Lab a partir de hoje. Não solicitaremos dados de pagamento ou cartão de crédito para iniciar este período.\n\n2. Transição para Assinatura (Founder's Rate)\nPróximo ao término do seu período de 30 dias, o sistema exibirá notificações automáticas dentro da plataforma informando sobre a expiração do acesso.\nOpção de Continuidade: Para manter seus fluxos ativos e continuar utilizando o Lab, você poderá optar por assinar o plano mensal de USD 7,00 (sete dólares americanos).\nValor Vitalício: Como membro fundador, este valor será travado para você. Caso decida não assinar ao final dos 30 dias, seu acesso será suspenso, mas seus dados e fluxos permanecerão salvos por um período de cortesia para que você não perca seu trabalho.\n\n3. O que está incluído\nAcesso ao Lab: Infraestrutura pronta para execução de fluxos de dados.\nBlueprint Library: Modelos prontos baseados nos vídeos do canal.\nPrioridade de Feedback: Canal direto para sugerir novas funcionalidades.\n\n4. Sua Colaboração (O papel do Fundador)\nComo este é um ambiente em fase Beta, você concorda que:\nO sistema pode passar por atualizações e manutenções programadas.\nO seu feedback sobre a experiência de uso é fundamental para a evolução da ferramenta.\nRecomendamos manter backups externos de lógicas críticas, pois o ambiente é experimental.\n\n5. Uso Responsável\nO acesso é individual e voltado para aprendizado e desenvolvimento profissional. O uso abusivo de recursos computacionais fora dos padrões de aprendizado poderá resultar em suspensão temporária da conta.\n\nAo clicar abaixo, declaro que li e concordo com os termos, iniciando agora meu período de 30 dias de acesso ao MyFlow Lab.\n\n`;
    termsLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Só intercepta se for para sigInUsuario ou for um desses botões
            const txt = (link.textContent || '').trim().toLowerCase();
            if (
                (link.tagName === 'A' && link.getAttribute('href') && link.getAttribute('href').includes('sigInUsuario')) ||
                txt === 'experimentar' ||
                txt === 'registre-se' ||
                txt === 'inscrever-se' ||
                link.classList.contains('terms-link')
            ) {
                e.preventDefault();
                openTermsModal(termsText, function() {
                    if (link.tagName === 'A' && link.getAttribute('href')) {
                        window.location.href = link.getAttribute('href');
                    } else {
                        window.location.href = '/sigInUsuario';
                    }
                });
            }
        });
    });
});
</script>
<?php
// Carrega funcionalidades do usuário se não foram passadas pela view
// Isso garante compatibilidade com controllers que usam view() direto
if (!isset($userHasBucketsAccess) || !isset($userHasPipelinesAccess)) {
    // Importa o helper
    if (!function_exists('loadUserFunctionalities')) {
        require_once APPPATH . 'Helpers/FunctionalityHelper.php';
    }
    
    // Carrega as funcionalidades
    loadUserFunctionalities();
    
    // Obtém os valores globais
    $userHasBucketsAccess = isset($GLOBALS['userHasBucketsAccess']) ? $GLOBALS['userHasBucketsAccess'] : false;
    $userHasPipelinesAccess = isset($GLOBALS['userHasPipelinesAccess']) ? $GLOBALS['userHasPipelinesAccess'] : false;
}

// Calcula username sugerido para Airflow (prefixo do email + id) e lista de roles
$airflowUsername = '';
$airflowRoles = [];
if (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] == 1) {
    $userId = $_SESSION['id_usuario_logado'] ?? null;
    $userEmail = $_SESSION['email_usuario_logado'] ?? '';
    if ($userId !== null) {
        $airflowUsername = \App\Helpers\AirflowHelper::buildUsernameFromEmail($userEmail, (int) $userId);
        // Padrão de roles no Airflow: sempre 'Viewer' + role específica do dono (username)
        $airflowRoles = ['Viewer'];
        if (!empty($airflowUsername)) {
            $airflowRoles[] = $airflowUsername;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Google Analytics 4 - DEVE SER O PRIMEIRO SCRIPT -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-P312EQG53Y"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-P312EQG53Y', {
        'cookie_flags': 'SameSite=None;Secure'
      });
    </script>
    <!-- FIM Google Analytics 4 -->
    
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
    <!-- JavaScript Libraries - CARREGAR JQUERY PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <script src="<?= base_url("assets/templates/lib/easing/easing.min.js"); ?>"></script>
    <script src="<?= base_url("assets/templates/lib/waypoints/waypoints.min.js"); ?>"></script>
    <script src="<?= base_url("assets/templates/lib/owlcarousel/owl.carousel.min.js"); ?>"></script>

    <!-- Template Javascript -->
    <script src="<?= base_url("assets/templates/js/main.js"); ?>"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>

    <!--  ----------  INICIO BOOTSTRAP  --------------------------------------------------------------------------  -->
    
    <!-- CSS do Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- JS do Bootstrap 5 (inclui Popper.js automaticamente) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


    <!--  ----------  FIM BOOTSTRAP  --------------------------------------------------------------------------  -->

    <!-- Multi-Upload e Multi-Table CSS -->
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/css/multi-table-selection.css'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= base_url('assets/css/multi-upload.css'); ?>">

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

    <?php if (isset($_SESSION['ga4_login_event'])): ?>
    <!-- Disparo de evento GA4: Login -->
    <script>
      if (typeof gtag === 'function') {
        gtag('event', 'login', {
          'method': '<?= htmlspecialchars($_SESSION['ga4_login_event']['method'], ENT_QUOTES, 'UTF-8'); ?>',
          'user_id': '<?= htmlspecialchars($_SESSION['ga4_login_event']['user_id'], ENT_QUOTES, 'UTF-8'); ?>'
        });
        console.log('✅ GA4: Login event sent - Method: <?= htmlspecialchars($_SESSION['ga4_login_event']['method'], ENT_QUOTES, 'UTF-8'); ?>');
      } else {
        console.error('❌ GA4: gtag function not available');
      }
    </script>
    <?php 
      unset($_SESSION['ga4_login_event']); // Limpa para não reenviar
    endif; 
    ?>

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

            <?php if (!empty($airflowUsername)): ?>
            <li>
                <div class="bg-light text-dark p-2 rounded mb-2">
                    <small>Seu usuário no Airflow (senha = mesma da WebApp)</small>
                    <div class="d-flex align-items-center">
                        <span id="airflow-username-text" class="fw-bold me-2"><?= htmlspecialchars($airflowUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyAirflowUsername()">Copiar</button>
                    </div>
                    <?php if (!empty($airflowRoles)): ?>
                    <div class="mt-2">
                        <small class="text-muted">Roles no Airflow:</small>
                        <?php foreach ($airflowRoles as $role): ?>
                            <span class="badge bg-secondary me-1"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </li>
            <?php endif; ?>

            <ul class="list-unstyled">
                
                <li>
                    <?php if (!isset($_SESSION['nome_usuario_logado']) || empty($_SESSION['nome_usuario_logado'])): ?>
                        
                        <?php echo anchor("sigInUsuario","Experimentar", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                        
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
                    <hr class="text-white" style="margin: 10px 0;">
                </li>

                <li>
                    <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                        <a href="/docs/index.html" class="nav-link px-4 px-lg-5" target="_blank" style="color: #87ceeb;">
                            📚 Documentação
                        </a>
                    <?php else: ?>
                        <?php echo anchor("sigInUsuario", "📚 Documentação", ['class' => 'nav-link px-4 px-lg-5 terms-link', 'style' => 'color: #87ceeb;']); ?>
                    <?php endif; ?>
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

    function copyAirflowUsername() {
        const el = document.getElementById('airflow-username-text');
        if (!el) {
            console.error('Elemento airflow-username-text não encontrado');
            alert('Erro: Usuário Airflow não encontrado');
            return;
        }
        
        const text = (el.textContent || el.innerText || '').trim();
        console.log('Texto a copiar:', text);
        
        if (!text) {
            alert('Erro: Username vazio');
            return;
        }

        // Tentar usar a Clipboard API (preferível)
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                alert('✓ Usuário copiado: ' + text);
            }).catch((err) => {
                console.error('Erro ao copiar:', err);
                // Fallback para o método antigo
                copyToClipboardFallback(text);
            });
        } else {
            // Fallback para navegadores antigos
            copyToClipboardFallback(text);
        }
    }
    
    function copyToClipboardFallback(text) {
        try {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('✓ Usuário copiado: ' + text);
        } catch (err) {
            console.error('Erro no fallback:', err);
            alert('Não foi possível copiar automaticamente.\n\nUsuário: ' + text + '\n\nCopie manualmente (Ctrl+C)');
        }
    }
</script>
<!-- FIM DA SIDEBAR ------------------------------------------------------------------------ -->




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
                    
                    <?php echo anchor("sigInUsuario","Experimentar", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                    

                <?php endif; ?>


                <!-- Dropdown -->
                <?php
                        // Verifica se o perfil do usuário está logado e se ele NÃO é "Anonimo"
                            if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Visitante"): 
                ?> 
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbardrop" data-bs-toggle="dropdown">
                        CONFIGURAÇÕES
                    </a>
                    <div class="dropdown-menu">
                        <?php
                        // Verifica se o perfil do usuário está logado e se ele NÃO é "Anonimo"
                            if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Anonimo"): 
                        ?>
                            <?php echo anchor("listPasta", "Pastas", ['class' => 'nav-link px-4 px-lg-5']) ?>
                            <?php echo anchor("listConfig", "Fluxos", ['class' => 'nav-link px-4 px-lg-5']) ?>
                        <?php elseif (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] === "Anonimo"): ?>
                            <?php echo anchor("listConfig", "Fluxos", ['class' => 'nav-link px-4 px-lg-5']) ?>
                        <?php endif; ?>
                    </div>
                </li>
                <!-- Fim Dropdown -->
                <?php endif; ?>

                <?php 
                // Verifica se os serviços estão bloqueados por assinatura expirada
                $servicesBlocked = isset($_SESSION['subscription_services_blocked']) && $_SESSION['subscription_services_blocked'];
                ?>

                <li class="nav-item dropdown <?= $servicesBlocked ? 'disabled' : '' ?>">
                    <?php if ($servicesBlocked): ?>
                        <a class="nav-link dropdown-toggle" href="#" style="opacity: 0.5; cursor: not-allowed;" 
                           title="Renovar assinatura para acessar os serviços" onclick="event.preventDefault(); alert('⚠️ Assinatura expirada!\n\nPara acessar os serviços, renove sua assinatura.');">
                            SERVIÇOS 🔒
                        </a>
                    <?php else: ?>
                        <a class="nav-link dropdown-toggle" href="#" id="servicesDrop" data-bs-toggle="dropdown">
                            SERVIÇOS
                        </a>
                        <div class="dropdown-menu">
                            <?php if (isset($userHasPipelinesAccess) && $userHasPipelinesAccess): ?>
                                <?php $airflowExternalUrl = getenv('AIRFLOW_EXTERNAL_URL') ?: 'http://localhost:8080'; ?>
                                <a class="dropdown-item" href="<?= htmlspecialchars($airflowExternalUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Pipelines ELT</a>
                            <?php endif; ?>
                            <!-- ?php if (isset($userHasBucketsAccess) && $userHasBucketsAccess): ?>
                                <a class="dropdown-item" href="http://localhost:9001" target="_blank" rel="noopener noreferrer">Buckets S3</a-->
                            <!-- ?php endif; ?-->
                            <?php if (isset($_SESSION['perfil_usuario_logado']) && $_SESSION['perfil_usuario_logado'] != "Visitante"): ?>
                                <!-- a class="dropdown-item" href="<! ?= base_url('query-builder') ?>">🦆 Query Builder Parquet</a-->
                                <a class="dropdown-item" href="<?= base_url('code-editor') ?>">💻 SQL Editor + Customizações</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>

                <?php echo anchor("politica","Política Privacidade", ['class' => 'nav-link px-4 px-lg-5'])  ?>

                <?php echo anchor("tdu","Termos de uso", ['class' => 'nav-link px-4 px-lg-5'])  ?>
                
                <?php echo anchor("contactUs","Entre em contato", ['class' => 'nav-link px-4 px-lg-5'])  ?>

                <?php echo anchor("sigInUsuario", "Registre-se", ['class' => 'nav-link px-4 px-lg-5']) ?>
                
            </div>

            </ul>

        </div>
    
    </nav>
    <!-- fecha sidebar -->

    <!-- Mensagens de sucesso e erro centralizadas na tela -->
    <div id="success-message" class="alert alert-success" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:300px; max-width:600px; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
    <div id="error-message" class="alert alert-warning" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:300px; max-width:600px; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>

    <div class="sidebyside-container">


    
    

    

    <form id="submitSaibaMais" method="POST" action="<?php echo route_to('saibaMais'); ?>" style="display: none;">

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

