<?php
// Garante timezone dinâmico baseado na sessão ou cookie do cliente (fallback America/Sao_Paulo)
$userTz = $_SESSION['user_timezone'] ?? $_COOKIE['user_timezone'] ?? 'America/Sao_Paulo';
if (!in_array($userTz, \DateTimeZone::listIdentifiers())) {
    $userTz = 'America/Sao_Paulo';
}
date_default_timezone_set($userTz);

if (!function_exists('getBookmakerUrl')) {
    require_once APPPATH . 'Helpers/BookmakerHelper.php';
}
?>
<!-- Modal de Termos de Uso -->
<div id="termsModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:90vw; max-width:600px; max-height:80vh; border-radius:8px; overflow:hidden; display:flex; flex-direction:column;">
        <div style="padding:16px; border-bottom:1px solid #eee; font-weight:bold;"><?= lang('App.terms_of_use') ?></div>
        <div id="termsContent" style="flex:1; overflow-y:auto; padding:16px; font-size:0.95em; background:#fafafa; color:#111;"></div>
        <div style="padding:16px; border-top:1px solid #eee; display:flex; flex-direction:column; gap:8px;">
            <label style="display:flex; align-items:center; gap:8px; color:#111;">
                <input type="checkbox" id="agreeCheckbox" disabled />
                <?= lang('App.read_and_agree') ?>
            </label>
            <button id="proceedBtn" disabled style="padding:8px 16px; border:none; background:#1976d2; color:#fff; border-radius:4px; cursor:pointer;"><?= lang('App.proceed') ?></button>
            <button id="closeModalBtn" style="padding:6px 12px; border:none; background:#eee; color:#333; border-radius:4px; cursor:pointer;"><?= lang('App.cancel') ?></button>
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
$perfilUsuario = trim((string) (session()->get('perfil_usuario_logado') ?? ($_SESSION['perfil_usuario_logado'] ?? '')));
$isAdmin = !empty(session()->get('is_admin')) || !empty($_SESSION['is_admin']) || in_array(strtolower($perfilUsuario), ['admin', 'administrador'], true);
$isVisitor = strcasecmp($perfilUsuario, 'Visitante') === 0;

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
    <?php if (empty($_SESSION['is_admin'])): ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-KD72GH3P');</script>
        <!-- End Google Tag Manager -->
        
        <!-- Google Analytics 4 - DEVE SER O PRIMEIRO SCRIPT -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= env('GA4_CRISTALBET_ID', 'G-KZDFH80BXW') ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                // Tag cristalbet.com.br
                gtag('config', '<?= env('GA4_CRISTALBET_ID', 'G-KZDFH80BXW') ?>', {
                    'cookie_flags': 'SameSite=None;Secure'
                });
                // Tag myflow.estudotabelas.com.br
                gtag('config', 'G-P312EQG53Y', {
                    'cookie_flags': 'SameSite=None;Secure'
                });
                // Tag estudotabelas.com.br
                gtag('config', 'G-SSKK91YY74', {
                    'cookie_flags': 'SameSite=None;Secure'
                });
        </script>
        <!-- FIM Google Analytics 4 -->
    <?php endif; ?>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Detecção automática de Timezone do cliente para UX global -->
    <script>
        (function() {
            try {
                var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                if (tz) {
                    var cookies = document.cookie.split(';');
                    var currentTz = null;
                    for (var i = 0; i < cookies.length; i++) {
                        var c = cookies[i].trim();
                        if (c.indexOf('user_timezone=') === 0) {
                            currentTz = decodeURIComponent(c.substring('user_timezone='.length));
                            break;
                        }
                    }
                    if (currentTz !== tz) {
                        document.cookie = 'user_timezone=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; SameSite=Lax';
                    }
                }
            } catch(e) {}
        })();
    </script>
    <meta name="google-site-verification" content="SN_1k1RhCAE6F7CIT8Zlp2mKiGUKH4rM1ji7BXAcsJs" />
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2926761252260319"
     crossorigin="anonymous"></script>
    <!-- SEO & Meta Tags Dinâmicas CristalBet -->
    <?php
        if (empty($metaTags) && empty(session()->get('metaTags'))) {
            $defaultSeo = new \App\Libraries\SeoHelper();
            $metaTagsHtml = $defaultSeo->generateMetaTags();
        } else {
            $metaTagsHtml = $metaTags ?? session()->get('metaTags');
        }
    ?>
    <?= $metaTagsHtml ?>

    <!-- Favicon CristalBet -->
    <link rel="icon" href="<?= base_url('assets/favicon-cristalbet.ico'); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?= base_url('assets/favicon-cristalbet.ico'); ?>" type="image/x-icon">

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
    overflow-y: auto;
    max-height: 100vh;
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
        background: url('<?= base_url("assets/img/header-banner.jpg"); ?>?v=<?= time() ?>') center/cover no-repeat !important;
        border: none;
        border-radius: 0;
        width: 100%;
        height: 80px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 24px;
        position: relative;
        z-index: 1030; /* Fica acima da navbar sticky (z-index 1020) */
    }

    #head-bar .logo-container {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        pointer-events: none;
        z-index: 1;
    }

    #head-bar .logo-container img {
        height: 40px;
        width: auto;
        object-fit: contain;
        display: block;
        margin-bottom: 4px;
    }

    #head-bar .logo-container .subtitle {
        color: #ffffff;
        font-size: 2.8rem;
        font-weight: 800;
        letter-spacing: 3px;
        margin: 0;
        text-transform: uppercase;
        text-shadow: 0 2px 12px rgba(0, 0, 0, 0.9), 0 0 25px rgba(0, 255, 128, 0.6);
        font-family: 'Outfit', 'Nunito', sans-serif;
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        #head-bar .logo-container .subtitle {
            font-size: 1.6rem;
            letter-spacing: 1px;
        }
    }

    #youtubeBtn {
        background: #FF0000;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        width: auto;
        max-width: 220px;
        min-width: 120px;
        white-space: nowrap;
    }

    #youtubeBtn:hover {
        background: #cc0000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255,0,0,0.3);
    }

    #youtubeBtn i {
        font-size: 20px;
    }

    .header-buttons {
        position: absolute;
        right: 24px;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 1031;
    }

    .header-buttons .dropdown-menu {
        z-index: 1040 !important;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2) !important;
        border: 1px solid rgba(0,0,0,0.1);
        margin-top: 6px !important;
    }

  </style>

  <!-- FIM ESTILO DA SIDEBAR -->

    
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KD72GH3P"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

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
            <div class="logo-container">
                <div style="display: flex; flex-direction: column; justify-content: center;">
                    <p class="subtitle">Cristal Bet</p>
                </div>
            </div>
            
            <div class="header-buttons">
                <?php
                    $currentLang = session()->get('lang') ?? service('request')->getLocale() ?? 'pt-BR';
                    $langLabel = strtoupper(substr($currentLang, 0, 2));
                    if ($currentLang === 'pt-BR') { $langFlag = '🇧🇷'; }
                    elseif ($currentLang === 'es') { $langFlag = '🇪🇸'; }
                    else { $langFlag = '🇺🇸'; }
                ?>
                <!-- Seletor de Idioma (Dropdown) -->
                <div class="dropdown me-2">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-1" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px; font-weight: 600;">
                        <span><?= $langFlag ?></span>
                        <span><?= $langLabel ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="langDropdown">
                        <li>
                            <a class="dropdown-item <?= ($currentLang === 'pt-BR' || $currentLang === 'pt') ? 'active' : '' ?>" href="<?= base_url('lang/pt-BR') ?>">
                                🇧🇷 <?= lang('App.lang_pt') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($currentLang === 'en') ? 'active' : '' ?>" href="<?= base_url('lang/en') ?>">
                                🇺🇸 <?= lang('App.lang_en') ?>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= ($currentLang === 'es') ? 'active' : '' ?>" href="<?= base_url('lang/es') ?>">
                                🇪🇸 <?= lang('App.lang_es') ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Botão para abrir a sidebar -->
                <button id="openSidebarBtn" class="btn btn-light">
                    <i class="bi bi-person-circle"></i>
                    <span class="ms-2"><?php echo isset($_SESSION['nome_usuario_logado']) ? $_SESSION['nome_usuario_logado'] : lang('App.enter'); ?></span>
                </button>
            </div>
        </div>
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar-overlay">

            <div class="right">

                <button id="closeSidebarBtn" class="">X</button>

            </div>
    
            <ul class="list-unstyled">
                <li>
                    <p class="text-white"><?= lang('App.hello') ?> <?php echo isset($_SESSION['nome_usuario_logado']) ? $_SESSION['nome_usuario_logado'] : lang('App.visitor'); ?></p>
                </li>

                <!-- Seção 'Seu usuário no Airflow' removida -->
            </ul>

            <ul class="list-unstyled">
                <li>
                    <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                        <?php echo anchor(route_to('Usuario.logOut'), lang('App.exit'), ['class' => 'nav-link px-2 px-lg-2']); ?>
                    <?php else: ?>
                        <?php echo anchor("loginUsuario", lang('App.enter'), ['class' => 'nav-link px-2 px-lg-2']) ?>
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
    openSidebarBtn.addEventListener('click', (event) => {
        event.preventDefault();
        sidebar.classList.add('active');
        overlayBackground.classList.add('active');
    });

    // Fechar a sidebar
    closeSidebarBtn.addEventListener('click', (event) => {
        event.preventDefault();
        sidebar.classList.remove('active');
        overlayBackground.classList.remove('active');
    });

    // Fechar a sidebar ao clicar no fundo
    overlayBackground.addEventListener('click', (event) => {
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
        if (!text) {
            alert('Erro: Username vazio');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                alert('✓ Usuário copiado: ' + text);
            }).catch((err) => {
                console.error('Erro ao copiar:', err);
                copyToClipboardFallback(text);
            });
        } else {
            copyToClipboardFallback(text);
        }
    }

    function copyAirflowPassword() {
        const btn = document.querySelector('button[onclick="copyAirflowPassword()"]');
        if (!btn) {
            alert('Erro: Botão de copiar senha não encontrado');
            return;
        }
        const senha = btn.getAttribute('data-password');
        if (!senha) {
            alert('Erro: Senha vazia');
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(senha).then(() => {
                alert('✓ Senha copiada: ' + senha);
            }).catch((err) => {
                console.error('Erro ao copiar:', err);
                copyToClipboardFallback(senha);
            });
        } else {
            copyToClipboardFallback(senha);
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
                <li class="nav-item visually-hidden">
                    <input type="hidden" id="perfil_usuario_logado" value="<?php echo isset($_SESSION['perfil_usuario_logado']) ? $_SESSION['perfil_usuario_logado'] : 'N/A'; ?>" readonly>
                </li>
                <li class="nav-item">
                    <a href="/" class="nav-link px-2 px-lg-2" title="<?= lang('App.home') ?>">
                        <i class="fas fa-home" style="font-size: 24px; vertical-align: middle;"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('apostas') ?>" class="nav-link px-3 px-lg-3 font-weight-bold text-success d-flex align-items-center gap-1" title="<?= lang('App.my_bets') ?>">
                        <i class="bi bi-ticket-detailed-fill" style="font-size: 20px;"></i>
                        <span><?= lang('App.my_bets') ?></span>
                    </a>
                </li>
                <?php 
                $userNameHeader = $_SESSION['nome_usuario_logado'] ?? session()->get('nome_usuario_logado') ?? ($user->nome ?? '');
                if (\App\Helpers\SessionHelper::isPauloNascimento($userNameHeader)): 
                ?>
                <li class="nav-item">
                    <a href="<?= base_url('apostas/extrato') ?>" class="nav-link px-3 px-lg-3 font-weight-bold text-warning d-flex align-items-center gap-1" title="Extrato & Conta Corrente">
                        <i class="bi bi-wallet2" style="font-size: 19px;"></i>
                        <span>Extrato & Conta Corrente</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('metas') ?>" class="nav-link px-3 px-lg-3 font-weight-bold d-flex align-items-center gap-1" style="color: #38bdf8;" title="Metas Diárias & Ciclos Rotativos">
                        <i class="bi bi-bullseye" style="font-size: 19px;"></i>
                        <span>Metas Diárias</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="<?= base_url('apostas/relatorio-eficiencia') ?>" class="nav-link px-3 px-lg-3 font-weight-bold text-info d-flex align-items-center gap-1" title="<?= lang('App.tips_efficiency') ?>">
                        <i class="bi bi-graph-up-arrow" style="font-size: 18px;"></i>
                        <span><?= lang('App.tips_efficiency') ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('apostas/relatorio-abstencoes') ?>" class="nav-link px-3 px-lg-3 font-weight-bold text-danger d-flex align-items-center gap-1" title="Auditoria de Abstenções (NO_BET)">
                        <i class="bi bi-shield-slash" style="font-size: 18px;"></i>
                        <span>Abstenções (NO_BET)</span>
                    </a>
                </li>
            </ul>

            <div id="itens-menu-outros" class="navbar-nav ms-auto p-4 p-lg-0 align-items-center">
                <!-- Seletor de Idioma mobile / navbar fallback -->
                <div class="nav-item dropdown d-lg-none my-2">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" id="navbarLangDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-globe"></i> <span><?= lang('App.language') ?> (<?= strtoupper(substr($currentLang, 0, 2)) ?>)</span>
                    </a>
                    <ul class="dropdown-menu shadow-sm" aria-labelledby="navbarLangDropdown">
                        <li><a class="dropdown-item" href="<?= base_url('lang/pt-BR') ?>">🇧🇷 <?= lang('App.lang_pt') ?></a></li>
                        <li><a class="dropdown-item" href="<?= base_url('lang/en') ?>">🇺🇸 <?= lang('App.lang_en') ?></a></li>
                        <li><a class="dropdown-item" href="<?= base_url('lang/es') ?>">🇪🇸 <?= lang('App.lang_es') ?></a></li>
                    </ul>
                </div>

                <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                <!-- Central de Notificações do Usuário (Sino & Dropdown) -->
                <div class="nav-item dropdown me-2 position-relative" id="dropdown-notificacoes-wrapper">
                    <a class="nav-link px-2 d-flex align-items-center position-relative text-dark" href="#" id="notificacoesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações & Alertas de Apostas">
                        <i class="bi bi-bell-fill" style="font-size: 20px; color: #f59e0b;"></i>
                        <span id="badge-notificacoes" class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; font-size: 0.68rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg p-0 border-0" aria-labelledby="notificacoesDropdown" style="width: 360px; max-width: 90vw; border-radius: 12px; overflow: hidden; background: #1e293b; color: #f8fafc; z-index: 1060;">
                        <div class="p-3 d-flex justify-content-between align-items-center border-bottom border-secondary" style="background: #0f172a;">
                            <span class="fw-bold d-flex align-items-center gap-2 text-light" style="font-size: 0.95rem;">
                                <i class="bi bi-bell-fill text-warning"></i> Notificações
                            </span>
                            <button type="button" class="btn btn-sm btn-link text-info text-decoration-none p-0" id="btn-marcar-todas-lidas" style="font-size: 0.78rem;">
                                Marcar lidas
                            </button>
                        </div>
                        <div id="lista-notificacoes-container" style="max-height: 380px; overflow-y: auto;">
                            <div class="text-center text-muted p-4" id="notif-empty-state" style="font-size: 0.88rem;">
                                <i class="bi bi-bell-slash fs-4 d-block mb-2 text-secondary"></i>
                                Nenhuma notificação recente.
                            </div>
                        </div>
                        <div class="p-2 text-center border-top border-secondary" style="background: #0f172a;">
                            <a href="<?= base_url('apostas?filtro_status=Cancelada') ?>" class="text-decoration-none text-info small">
                                Ver todas as apostas canceladas <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php echo anchor("contactUs", lang('App.contact_us'), ['class' => 'nav-link px-2 px-lg-2 text-nowrap'])  ?>
                <?php echo anchor("reportError", lang('App.report_error'), ['class' => 'nav-link px-2 px-lg-2 text-nowrap'])  ?>

                <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                    <?php echo anchor(route_to('Usuario.logOut'), lang('App.exit'), ['class' => 'nav-link px-2 px-lg-2']); ?>
                <?php else: ?>
                    <?php echo anchor("loginUsuario", lang('App.enter'), ['class' => 'nav-link px-2 px-lg-2']) ?>
                <?php endif; ?>
            </div>

        </div>
    
    </nav>
    <!-- fecha sidebar -->

    <!-- Mensagens de sucesso e erro centralizadas na tela -->
    <div id="success-message" class="alert alert-success" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:300px; max-width:600px; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
    <div id="error-message" class="alert alert-warning" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:300px; max-width:600px; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>

    <!-- Toast Pop-ups Flutuantes em Stack Vertical: Stop Loss Pinado no Topo + 1 Notificação Recente Logo Abaixo -->
    <div id="toast-container-stack" class="toast-popup-container" style="display: none;" role="alert" aria-live="assertive" aria-atomic="true">
        <!-- Card 1: Stop Loss Pinado no Topo -->
        <div id="toast-stoploss-popup" class="toast-popup-card shadow-lg mb-2" style="display: none; border: 2px solid #ef4444; background: linear-gradient(135deg, #450a0a 0%, #1e1b4b 60%, #0f172a 100%);">
            <div class="toast-popup-header">
                <span class="badge bg-danger text-white d-flex align-items-center gap-1 pulse-badge-anim" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="bi bi-pin-angle-fill"></i> 📌 PINADO | STOP LOSS DIÁRIO
                </span>
                <button type="button" class="btn-close btn-close-white ms-auto" id="btn-fechar-toast-stoploss" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="toast-popup-body">
                <h6 id="toast-stoploss-titulo" class="fw-bold text-danger mb-1" style="font-size: 0.92rem;">⚠️ Stop Loss Diário Atingido!</h6>
                <p id="toast-stoploss-mensagem" class="text-light mb-2" style="font-size: 0.82rem; line-height: 1.35; color: #fecaca !important;">
                    Atenção: O limite de segurança diário foi atingido. Recomendado pausar novas apostas hoje para salvaguarda de banca.
                </p>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <a href="<?= base_url('metas') ?>" id="toast-stoploss-link" class="btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow" style="border-radius: 8px; font-size: 0.82rem; padding: 6px 12px;">
                        <i class="bi bi-sliders"></i> Ver Painel de Metas & Proteger Banca
                    </a>
                    <button type="button" id="btn-despinar-toast-stoploss" class="btn btn-sm btn-outline-light text-nowrap d-flex align-items-center gap-1 shadow-sm" title="Despinar e não reexibir este alerta" style="border-radius: 8px; font-size: 0.80rem; padding: 6px 12px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3);">
                        <i class="bi bi-pin-angle"></i> Despinar
                    </button>
                </div>
            </div>
        </div>


        <!-- Card 2: Notificação Recente (Logo Abaixo do Stop Loss) -->
        <div id="toast-notificacao-popup" class="toast-popup-card shadow-lg" style="display: none;">
            <div class="toast-popup-header">
                <span id="toast-notif-badge" class="badge bg-danger d-flex align-items-center gap-1 pulse-badge-anim" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="bi bi-exclamation-triangle-fill"></i> ALERTA BETANO (ABSTENÇÃO IA)
                </span>
                <button type="button" class="btn-close btn-close-white ms-auto" id="btn-fechar-toast" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="toast-popup-body">
                <h6 id="toast-notif-titulo" class="fw-bold text-warning mb-1" style="font-size: 0.92rem;">⚠️ Aposta Cancelada pela IA</h6>
                <p id="toast-notif-mensagem" class="text-light mb-3" style="font-size: 0.82rem; line-height: 1.35; color: #cbd5e1 !important;">
                    O Gatekeeper ativou Abstenção da IA. Se já realizou o bilhete na Betano, efetue o Cash Out imediato!
                </p>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <a href="#" id="toast-notif-link" class="btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow" style="border-radius: 8px; font-size: 0.82rem; padding: 6px 12px;">
                        <i class="bi bi-box-arrow-up-right"></i> Ver Aposta & Fazer Cash Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <style>
    .toast-popup-container {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 10999;
        max-width: 420px;
        width: calc(100vw - 48px);
        display: flex;
        flex-direction: column;
        gap: 10px;
        animation: slideInRightToast 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes slideInRightToast {
        from { transform: translateX(120%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .toast-popup-card {
        background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
        border: 2px solid #ef4444;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
    }
    .toast-popup-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .pulse-badge-anim {
        animation: pulseBadge 1.5s infinite;
    }
    @keyframes pulseBadge {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.85; transform: scale(1.05); }
        100% { opacity: 1; transform: scale(1); }
    }
    .notif-item {
        padding: 12px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        transition: background-color 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        display: block;
        color: #f8fafc;
    }
    .notif-item:hover {
        background-color: rgba(255, 255, 255, 0.08);
        color: #ffffff;
    }
    .notif-item.nao-lida {
        background-color: rgba(239, 68, 68, 0.15);
        border-left: 3px solid #ef4444;
    }
    .notif-item.pinada {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.25) 0%, rgba(15, 23, 42, 0.95) 100%) !important;
        border-left: 4px solid #ef4444 !important;
        border-bottom: 1px solid rgba(239, 68, 68, 0.4) !important;
    }
    .notif-item .notif-time {
        font-size: 0.72rem;
        color: #94a3b8;
    }
    </style>

    <script>
    (function() {
        const notifApiUrl = '<?= base_url('notificacoes/nao-lidas') ?>';
        const markReadUrl = '<?= base_url('notificacoes/marcar-lida') ?>';
        const markAllReadUrl = '<?= base_url('notificacoes/marcar-todas-lidas') ?>';
        const unpinUrl = '<?= base_url('notificacoes/despinar') ?>';

        let popupsExibidos = new Set();
        try {
            const saved = sessionStorage.getItem('popups_notificacoes_exibidos');
            if (saved) {
                JSON.parse(saved).forEach(id => popupsExibidos.add(id));
            }
        } catch(e) {}

        function salvarPopupsExibidos() {
            try {
                sessionStorage.setItem('popups_notificacoes_exibidos', JSON.stringify(Array.from(popupsExibidos)));
            } catch(e) {}
        }

        function despinarAlerta(id) {
            if (!id) return;
            try {
                localStorage.setItem('notif_despinada_' + id, '1');
            } catch(e) {}
            popupsExibidos.add('stoploss_' + id);
            salvarPopupsExibidos();

            const slToast = document.getElementById('toast-stoploss-popup');
            if (slToast) slToast.style.display = 'none';
            const stackContainer = document.getElementById('toast-container-stack');
            const normEl = document.getElementById('toast-notificacao-popup');
            if (stackContainer && (!normEl || normEl.style.display === 'none')) {
                stackContainer.style.display = 'none';
            }

            fetch(unpinUrl + '/' + id, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(() => {
                checkNotificacoes();
            })
            .catch(() => {});
        }

        function checkNotificacoes() {
            fetch(notifApiUrl, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) return;

                const badge = document.getElementById('badge-notificacoes');
                const total = data.total_nao_lidas || 0;

                if (badge) {
                    if (total > 0) {
                        badge.textContent = total > 99 ? '99+' : total;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }

                // Renderizar dropdown (Stop Loss sempre pinado no topo se pinada = 1)
                const container = document.getElementById('lista-notificacoes-container');
                if (container && data.notificacoes && data.notificacoes.length > 0) {
                    let html = '';
                    data.notificacoes.forEach(n => {
                        const isUnread = (parseInt(n.lida) === 0);
                        const isStopLoss = (n.tipo === 'STOP_LOSS_DIARIO');
                        const isPinada = (parseInt(n.pinada || 0) === 1);
                        const linkHref = n.link ? (n.link.startsWith('http') ? n.link : '<?= base_url() ?>' + (n.link.startsWith('/') ? n.link.substring(1) : n.link)) : '#';
                        const timeStr = n.criado_em ? n.criado_em.substring(5, 16).replace('-', '/') : '';

                        if (isStopLoss) {
                            html += `
                                <div class="notif-item ${isPinada ? 'pinada' : ''} ${isUnread ? 'nao-lida' : ''}" data-notif-id="${n.id}" data-href="${linkHref}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <a href="${linkHref}" class="text-decoration-none d-flex align-items-center gap-1 flex-grow-1" style="color: inherit;">
                                            <strong style="font-size: 0.85rem; color: #ef4444;" class="d-flex align-items-center gap-1">
                                                <i class="bi ${isPinada ? 'bi-pin-angle-fill' : 'bi-shield-exclamation'} text-danger"></i> ${n.titulo}
                                            </strong>
                                        </a>
                                        <div class="d-flex align-items-center gap-1">
                                            ${isPinada ? `
                                                <span class="badge bg-danger" style="font-size: 0.65rem; padding: 2px 6px;">📌 PINADO</span>
                                                <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 btn-despinar-item" data-notif-id="${n.id}" title="Despinar alerta para não reexibir" style="font-size: 0.70rem; border-radius: 4px; line-height: 1.2; background: rgba(239,68,68,0.15);">
                                                    <i class="bi bi-pin-angle"></i> Despinar
                                                </button>
                                            ` : `
                                                <span class="notif-time">${timeStr}</span>
                                            `}
                                        </div>
                                    </div>
                                    <a href="${linkHref}" class="text-decoration-none d-block" style="font-size: 0.78rem; color: #fecaca; line-height: 1.3;">
                                        ${n.mensagem}
                                    </a>
                                </div>
                            `;
                        } else {
                            html += `
                                <a href="${linkHref}" class="notif-item ${isUnread ? 'nao-lida' : ''}" data-notif-id="${n.id}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong style="font-size: 0.85rem; color: ${isUnread ? '#f87171' : '#f1f5f9'};">
                                            ${n.titulo}
                                        </strong>
                                        <span class="notif-time">${timeStr}</span>
                                    </div>
                                    <div style="font-size: 0.78rem; color: #cbd5e1; line-height: 1.3;">
                                        ${n.mensagem}
                                    </div>
                                </a>
                            `;
                        }
                    });
                    container.innerHTML = html;

                    // Despinar no clique do botão despinar dentro do dropdown
                    container.querySelectorAll('.btn-despinar-item').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const nid = this.getAttribute('data-notif-id');
                            despinarAlerta(nid);
                        });
                    });

                    // Evento de clique para marcar lida e direcionar dinamicamente se já estiver em /apostas
                    container.querySelectorAll('.notif-item').forEach(el => {
                        el.addEventListener('click', function(e) {
                            if (e.target.closest('.btn-despinar-item')) return;

                            const nid = this.getAttribute('data-notif-id');
                            if (nid) {
                                fetch(markReadUrl + '/' + nid, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                            }

                            const href = this.getAttribute('href') || this.getAttribute('data-href');
                            if (href && typeof window.destacarApostaPorId === 'function') {
                                try {
                                    const parsedUrl = new URL(href, window.location.origin);
                                    const destaqueId = parsedUrl.searchParams.get('destaque_id');
                                    const dataJogo = parsedUrl.searchParams.get('data_jogo');
                                    if (destaqueId) {
                                        e.preventDefault();
                                        window.history.pushState({}, '', href);
                                        window.destacarApostaPorId(destaqueId, dataJogo);

                                        // Fecha o dropdown de notificações
                                        const notifDropdownEl = document.getElementById('notificacoesDropdown');
                                        if (notifDropdownEl && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                                            const bsDrop = bootstrap.Dropdown.getInstance(notifDropdownEl);
                                            if (bsDrop) bsDrop.hide();
                                        }
                                    }
                                } catch(err) {}
                            }
                        });
                    });
                }

                // Disparo dos Pop-ups Toast Flutuantes na tela:
                // 1) Stop Loss Diário (Pinado no topo da stack, apenas se pinada = 1 e não lida)
                // 2) Exatamente 1 Notificação Recente adicional logo abaixo
                const stopLossNotif = data.stop_loss_notif || (data.notificacoes || []).find(n => n.tipo === 'STOP_LOSS_DIARIO' && parseInt(n.pinada || 0) === 1 && parseInt(n.lida || 0) === 0);
                const outrasNaoLidas = (data.notificacoes || []).filter(n => n.tipo !== 'STOP_LOSS_DIARIO' && parseInt(n.lida) === 0);

                let showSl = false;
                let showOutra = false;

                if (stopLossNotif) {
                    const isDespinadaLocal = (localStorage.getItem('notif_despinada_' + stopLossNotif.id) === '1');
                    const keySl = 'stoploss_' + stopLossNotif.id;
                    if (!isDespinadaLocal && !popupsExibidos.has(keySl)) {
                        exibirToastStopLoss(stopLossNotif);
                        showSl = true;
                    }
                }

                if (outrasNaoLidas.length > 0) {
                    const maisRecente = outrasNaoLidas[0];
                    const keyOutra = 'notif_' + maisRecente.id;
                    if (!popupsExibidos.has(keyOutra)) {
                        exibirToastPopup(maisRecente);
                        showOutra = true;
                    }
                }

                const stackContainer = document.getElementById('toast-container-stack');
                if (stackContainer) {
                    const slEl = document.getElementById('toast-stoploss-popup');
                    const normEl = document.getElementById('toast-notificacao-popup');
                    const hasVisible = (slEl && slEl.style.display !== 'none') || (normEl && normEl.style.display !== 'none');
                    stackContainer.style.display = hasVisible ? 'flex' : 'none';
                }
            })
            .catch(err => {});
        }

        function exibirToastStopLoss(notif) {
            const slToast = document.getElementById('toast-stoploss-popup');
            const slTitulo = document.getElementById('toast-stoploss-titulo');
            const slMsg = document.getElementById('toast-stoploss-mensagem');
            const slLink = document.getElementById('toast-stoploss-link');
            const stackContainer = document.getElementById('toast-container-stack');
            if (!slToast || !slTitulo || !slMsg) return;

            slTitulo.textContent = notif.titulo;
            slMsg.textContent = notif.mensagem;

            if (slLink && notif.link) {
                slLink.href = notif.link.startsWith('http') ? notif.link : '<?= base_url() ?>' + (notif.link.startsWith('/') ? notif.link.substring(1) : notif.link);
            }

            const btnCloseSl = document.getElementById('btn-fechar-toast-stoploss');
            if (btnCloseSl) {
                btnCloseSl.onclick = function() {
                    popupsExibidos.add('stoploss_' + notif.id);
                    salvarPopupsExibidos();
                    slToast.style.display = 'none';
                    const normEl = document.getElementById('toast-notificacao-popup');
                    if (!normEl || normEl.style.display === 'none') {
                        if (stackContainer) stackContainer.style.display = 'none';
                    }
                };
            }

            const btnDespinarSl = document.getElementById('btn-despinar-toast-stoploss');
            if (btnDespinarSl) {
                btnDespinarSl.onclick = function() {
                    despinarAlerta(notif.id);
                };
            }

            slToast.style.display = 'block';
            if (stackContainer) stackContainer.style.display = 'flex';
        }

        function exibirToastPopup(notif) {
            const toastEl = document.getElementById('toast-notificacao-popup');
            const badgeEl = document.getElementById('toast-notif-badge');
            const tituloEl = document.getElementById('toast-notif-titulo');
            const msgEl = document.getElementById('toast-notif-mensagem');
            const linkEl = document.getElementById('toast-notif-link');
            const stackContainer = document.getElementById('toast-container-stack');
            if (!toastEl || !tituloEl || !msgEl || !linkEl) return;

            tituloEl.textContent = notif.titulo;
            msgEl.textContent = notif.mensagem;

            const isAprovada = (notif.tipo === 'APOSTA_CARTAO_APROVADA' || notif.tipo === 'APOSTA_CRIADA');

            if (badgeEl) {
                if (isAprovada) {
                    badgeEl.className = 'badge bg-success d-flex align-items-center gap-1 pulse-badge-anim';
                    badgeEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> 🎯 OPORTUNIDADE +EV (CARTÕES)';
                } else {
                    badgeEl.className = 'badge bg-danger d-flex align-items-center gap-1 pulse-badge-anim';
                    badgeEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ALERTA BETANO (ABSTENÇÃO IA)';
                }
            }

            if (linkEl) {
                if (isAprovada) {
                    linkEl.className = 'btn btn-sm btn-success fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Ver Simulação Aprovada';
                } else {
                    linkEl.className = 'btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Ver Aposta & Fazer Cash Out';
                }
            }

            const defaultLink = isAprovada ? '<?= base_url('apostas') ?>' : '<?= base_url('apostas?filtro_status=Cancelada') ?>';
            const linkHref = notif.link ? (notif.link.startsWith('http') ? notif.link : '<?= base_url() ?>' + (notif.link.startsWith('/') ? notif.link.substring(1) : notif.link)) : defaultLink;
            linkEl.href = linkHref;

            linkEl.onclick = function(e) {
                fetch(markReadUrl + '/' + notif.id, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                popupsExibidos.add('notif_' + notif.id);
                salvarPopupsExibidos();
                toastEl.style.display = 'none';
                const slEl = document.getElementById('toast-stoploss-popup');
                if (!slEl || slEl.style.display === 'none') {
                    if (stackContainer) stackContainer.style.display = 'none';
                }

                if (linkHref && typeof window.destacarApostaPorId === 'function') {
                    try {
                        const parsedUrl = new URL(linkHref, window.location.origin);
                        const destaqueId = parsedUrl.searchParams.get('destaque_id');
                        const dataJogo = parsedUrl.searchParams.get('data_jogo');
                        if (destaqueId) {
                            e.preventDefault();
                            window.history.pushState({}, '', linkHref);
                            window.destacarApostaPorId(destaqueId, dataJogo);
                        }
                    } catch(err) {}
                }
            };

            const btnClose = document.getElementById('btn-fechar-toast');
            if (btnClose) {
                btnClose.onclick = function() {
                    popupsExibidos.add('notif_' + notif.id);
                    salvarPopupsExibidos();
                    toastEl.style.display = 'none';
                    const slEl = document.getElementById('toast-stoploss-popup');
                    if (!slEl || slEl.style.display === 'none') {
                        if (stackContainer) stackContainer.style.display = 'none';
                    }
                };
            }

            toastEl.style.display = 'block';
            if (stackContainer) stackContainer.style.display = 'flex';

            // Auto-ocultar a notificação secundária após 18 segundos se não interagido
            setTimeout(function() {
                if (toastEl.style.display === 'block') {
                    toastEl.style.display = 'none';
                    const slEl = document.getElementById('toast-stoploss-popup');
                    if (!slEl || slEl.style.display === 'none') {
                        if (stackContainer) stackContainer.style.display = 'none';
                    }
                }
            }, 18000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const btnAll = document.getElementById('btn-marcar-todas-lidas');
            if (btnAll) {
                btnAll.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    fetch(markAllReadUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(() => {
                        const badge = document.getElementById('badge-notificacoes');
                        if (badge) badge.style.display = 'none';
                        document.querySelectorAll('.notif-item.nao-lida').forEach(el => {
                            el.classList.remove('nao-lida');
                        });
                    });
                });
            }

            // Iniciar checagem
            checkNotificacoes();
            setInterval(checkNotificacoes, 30000);
        });
    })();
    </script>

    <div class="sidebyside-container">


    
    

    

    <form id="submitSaibaMais" method="POST" action="<?php echo route_to('saibaMais'); ?>" style="display: none;">

            <button id="btn-saiba-mais" type="submit" class="nav-button" ><?= lang('App.saiba_mais') ?>
                <i class="fas fa-info-circle" style="margin-left: 5px;"></i>
            </button>
        
    </form>

    <!--form id="submitDonate" method="POST" action="<!?php echo route_to('donate'); ?>">

        <button type="submit" class="nav-button">Doe $
            <i class="fas fa-money"></i>
            
        </button>

    </form-->

    
</div>

