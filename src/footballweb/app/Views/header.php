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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

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

                <?php
                    $moedaModelHeader = new \App\Models\MoedaConfigModel();
                    $moedaPadraoHeader = $moedaModelHeader->getDefaultMoeda();
                ?>
                <?php if ($moedaPadraoHeader): ?>
                <!-- Indicador da Moeda Padrão do Sistema (Entre Idioma e Usuário) -->
                <a href="<?= base_url('moedas') ?>" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1 text-nowrap me-2 shadow-sm" style="padding: 6px 12px; font-weight: 600; background: rgba(30, 41, 59, 0.7); border-color: rgba(255,255,255,0.25); text-decoration: none;" title="Moeda Padrão do Sistema: <?= esc($moedaPadraoHeader->nome) ?><?= $moedaPadraoHeader->tipo === 'CRYPTO' ? ' (Cotação Binance: R$ ' . number_format($moedaPadraoHeader->cotacao_brl, 2, ',', '.') . ')' : '' ?> - Clique para configurar">
                    <?php if ($moedaPadraoHeader->simbolo !== $moedaPadraoHeader->codigo): ?>
                        <span style="color: #38bdf8; font-weight: 700;"><?= esc($moedaPadraoHeader->simbolo) ?></span>
                    <?php endif; ?>
                    <span style="letter-spacing: 0.5px; font-weight: 600;"><?= esc($moedaPadraoHeader->codigo) ?></span>
                    <?php if ($moedaPadraoHeader->tipo === 'CRYPTO'): ?>
                        <span class="badge" style="background: rgba(56, 189, 248, 0.25); color: #38bdf8; font-size: 0.68rem; padding: 2px 5px; margin-left: 2px; border: 1px solid rgba(56, 189, 248, 0.4);">
                            R$ <?= number_format($moedaPadraoHeader->cotacao_brl, 2, ',', '.') ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

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
                <li class="nav-item">
                    <a href="<?= base_url('moedas') ?>" class="nav-link px-3 px-lg-3 font-weight-bold d-flex align-items-center gap-1" style="color: #f59e0b;" title="Configuração de Criptomoedas & Taxas Operacionais">
                        <i class="bi bi-coin" style="font-size: 19px;"></i>
                        <span>Criptomoedas</span>
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
                <!-- Botão Gatilho da Sidebar de Notificações & Alertas -->
                <div class="nav-item me-2 position-relative" id="dropdown-notificacoes-wrapper">
                    <a class="nav-link px-2 d-flex align-items-center position-relative text-dark" href="#" id="btn-toggle-sidebar-notif" role="button" title="Notificações & Alertas de Apostas">
                        <i class="bi bi-bell-fill" style="font-size: 20px; color: #f59e0b;"></i>
                        <span id="badge-notificacoes" class="position-absolute top-1 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; font-size: 0.68rem; padding: 0.25em 0.5em;">
                            0
                        </span>
                    </a>
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

    <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
    <!-- Barra Lateral Dock Retrátil Permanente no Canto Direito -->
    <div id="sidebar-notif-dock" class="sidebar-notif-dock" title="Central de Notificações & Alertas" onclick="if(window.openSidebarNotif) window.openSidebarNotif();">
        <button type="button" class="btn btn-sm btn-dock-toggle text-light p-0" id="btn-dock-toggle-notif" title="Expandir Notificações" aria-label="Expandir Notificações">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="dock-bell-wrapper position-relative">
            <i class="bi bi-bell-fill text-warning fs-5"></i>
            <span id="badge-notificacoes-dock" class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle" style="display: none; font-size: 0.62rem; padding: 0.2em 0.45em;">
                0
            </span>
        </div>
        <div class="dock-vertical-text">
            <span>NOTIFICAÇÕES</span>
        </div>
    </div>

    <!-- Sidebar Retrátil e Pinável de Notificações Fixada à Direita -->
    <aside id="sidebar-notificacoes" class="sidebar-notificacoes-drawer" aria-label="Central de Alertas e Notificações">
        <!-- Topo da Sidebar: Título e Controles de Janela -->
        <div class="sidebar-notif-header px-3 py-2 d-flex justify-content-between align-items-center" style="background: #0f172a; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary p-0" id="btn-collapse-sidebar-notif" title="Recolher para barra lateral" style="border-radius: 6px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.18);">
                    <i class="bi bi-list fs-5 text-light"></i>
                </button>
                <i class="bi bi-bell-fill text-warning fs-5"></i>
                <span class="fw-bold text-light" style="font-size: 0.95rem; letter-spacing: 0.3px;">Notificações</span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary p-0" id="btn-pin-sidebar-notif" title="Fixar barra lateral aberta" style="border-radius: 6px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.18);">
                    <i class="bi bi-pin-angle fs-6 text-light" id="icon-pin-sidebar"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary p-0" id="btn-close-sidebar-notif" title="Recolher barra lateral" style="border-radius: 6px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.18);">
                    <i class="bi bi-x-lg fs-6 text-light"></i>
                </button>
            </div>
        </div>

        <!-- Barra de Ações Rápidas -->
        <div class="sidebar-notif-toolbar px-3 py-2 d-flex justify-content-between align-items-center" style="background: #162032; border-bottom: 1px solid rgba(255,255,255,0.08);">
            <button type="button" class="btn btn-sm btn-link text-info text-decoration-none p-0 d-flex align-items-center gap-1" id="btn-marcar-todas-lidas" style="font-size: 0.76rem; font-weight: 500;" title="Marcar todos os alertas como lidos">
                <i class="bi bi-check2-all"></i> Marcar lidos
            </button>
            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0 d-flex align-items-center gap-1" id="btn-limpar-historico-notif" style="font-size: 0.76rem; font-weight: 500;" title="Limpar todo o histórico de alertas">
                <i class="bi bi-trash3"></i> Limpar histórico
            </button>
        </div>

        <!-- Caixa de Pesquisa de Notificações em Tempo Real -->
        <div class="sidebar-notif-search px-3 py-2" style="background: #111c2e; border-bottom: 1px solid rgba(255,255,255,0.08);">
            <div class="position-relative">
                <i class="bi bi-search position-absolute text-muted" style="left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; pointer-events: none;"></i>
                <input type="text" id="input-busca-notificacoes" class="form-control form-control-sm text-light ps-4 pe-4" placeholder="Buscar por time, título ou texto..." style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 6px; font-size: 0.8rem;" autocomplete="off">
                <button type="button" id="btn-limpar-busca-notif" class="btn btn-link position-absolute text-secondary p-0" style="right: 8px; top: 50%; transform: translateY(-50%); display: none; text-decoration: none;" title="Limpar busca">
                    <i class="bi bi-x-circle-fill" style="font-size: 0.85rem;"></i>
                </button>
            </div>
            <div id="busca-notif-feedback" class="mt-1 d-flex justify-content-between align-items-center text-muted" style="font-size: 0.70rem; display: none !important;">
                <span id="busca-notif-contador"></span>
                <span class="text-secondary opacity-75">ESC limpa</span>
            </div>
        </div>
        <div id="lista-notificacoes-container" class="sidebar-notif-body flex-grow-1" style="overflow-y: auto;">
            <div class="text-center text-muted p-4" id="notif-empty-state" style="font-size: 0.88rem;">
                <i class="bi bi-bell-slash fs-4 d-block mb-2 text-secondary"></i>
                Nenhuma notificação recente.
            </div>
        </div>
        <div class="sidebar-notif-footer p-2 text-center border-top border-secondary" style="background: #0f172a;">
            <a href="<?= base_url('apostas?filtro_status=Cancelada') ?>" class="text-decoration-none text-info small">
                Ver todas as apostas canceladas <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </aside>

    <!-- Backdrop escuro para quando a sidebar estiver aberta e NÃO pinada -->
    <div id="sidebar-notif-backdrop" class="sidebar-notif-backdrop" style="display: none;"></div>
    <?php endif; ?>

    <!-- Script Marked.js para renderização de tabelas e formatação Markdown do Grok -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
    .grok-preview-body {
        color: #e2e8f0;
        font-size: 0.90rem;
        line-height: 1.6;
    }
    .grok-preview-body h1, .grok-preview-body h2, .grok-preview-body h3, .grok-preview-body h4 {
        color: #93c5fd;
        margin-top: 1.1rem;
        margin-bottom: 0.6rem;
        font-weight: 700;
    }
    .grok-preview-body h3 {
        font-size: 1.05rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        padding-bottom: 4px;
        color: #67e8f9;
    }
    .grok-preview-body table {
        width: 100%;
        margin-top: 0.8rem;
        margin-bottom: 1.2rem;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: rgba(15, 23, 42, 0.7);
    }
    .grok-preview-body th {
        background: #1e293b;
        color: #38bdf8;
        padding: 9px 12px;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid rgba(56, 189, 248, 0.4);
    }
    .grok-preview-body td {
        padding: 9px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        font-size: 0.86rem;
    }
    .grok-preview-body tr:last-child td {
        border-bottom: none;
    }
    .grok-preview-body tr:hover td {
        background: rgba(255, 255, 255, 0.04);
    }
    .grok-preview-body ul, .grok-preview-body ol {
        padding-left: 1.3rem;
        margin-bottom: 0.8rem;
    }
    .grok-preview-body li {
        margin-bottom: 0.35rem;
    }
    .grok-preview-body strong {
        color: #f8fafc;
    }
    .grok-preview-body hr {
        border-color: rgba(255, 255, 255, 0.15);
        margin: 1.2rem 0;
    }
    .grok-tab-btn.active {
        background: rgba(99, 102, 241, 0.35) !important;
        border-color: rgba(99, 102, 241, 0.6) !important;
        color: #ffffff !important;
        font-weight: 600;
    }
    </style>

    <!-- Modal de Análise de Escalação com Grok AI -->
    <div class="modal fade" id="modalGrokAnaliseNotif" tabindex="-1" aria-labelledby="modalGrokAnaliseNotifLabel" aria-hidden="true" style="z-index: 10500;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: #0f172a; color: #f1f5f9; border: 1px solid rgba(99, 102, 241, 0.35); box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                <div class="modal-header border-secondary" style="background: #1e1b4b;">
                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                        <i class="bi bi-robot fs-5 text-indigo"></i>
                        <h5 class="modal-title mb-0" id="modalGrokAnaliseNotifLabel" style="font-size: 1rem; color: #a5b4fc;">
                            <span id="grok-modal-title">Análise de Escalação Oficial com Grok AI</span>
                        </h5>
                        <span id="grok-cache-badge" class="badge bg-success ms-2" style="display: none; font-size: 0.70rem; padding: 4px 8px;">
                            <i class="bi bi-lightning-charge-fill"></i> Cache MySQL
                        </span>
                        <span id="grok-live-badge" class="badge bg-primary ms-2" style="display: none; font-size: 0.70rem; padding: 4px 8px;">
                            <i class="bi bi-globe"></i> Grok Cloud
                        </span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                    <div id="grok-loading-state" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-light fw-bold mb-1" style="font-size: 0.95rem;">🤖 O Grok AI está auditando a súmula oficial e os desfalques...</p>
                        <p class="text-muted small">Avaliando formações, titulares mantidos e integridade da espinha dorsal</p>
                    </div>
                    <div id="grok-error-state" class="alert alert-danger" style="display: none;"></div>
                    <div id="grok-content-area" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary">
                            <div class="d-flex align-items-center gap-1" style="background: rgba(255,255,255,0.06); padding: 3px; border-radius: 6px;">
                                <button type="button" id="tab-btn-grok-preview" class="btn btn-xs grok-tab-btn active px-3 py-1" style="border-radius: 5px; font-size: 0.78rem; border: 1px solid transparent; color: #cbd5e1;">
                                    <i class="bi bi-eye text-info me-1"></i> Visualização Formatada
                                </button>
                                <button type="button" id="tab-btn-grok-raw" class="btn btn-xs grok-tab-btn px-3 py-1" style="border-radius: 5px; font-size: 0.78rem; border: 1px solid transparent; color: #cbd5e1;">
                                    <i class="bi bi-code-slash text-secondary me-1"></i> Texto Puro (Markdown)
                                </button>
                            </div>
                            <button type="button" id="btn-copiar-analise-grok" class="btn btn-sm btn-outline-light" style="font-size: 0.75rem;">
                                <i class="bi bi-clipboard"></i> Copiar Análise
                            </button>
                        </div>
                        <!-- Painel 1: Visualização Formatada (HTML via Marked) -->
                        <div id="grok-pane-preview" class="grok-preview-body p-3 rounded" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);"></div>
                        <!-- Painel 2: Texto Puro (Markdown Original) -->
                        <div id="grok-pane-raw" class="p-3 rounded" style="display: none; background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.1); font-size: 0.83rem; line-height: 1.6; white-space: pre-wrap; color: #e2e8f0; font-family: monospace;"></div>
                    </div>
                </div>
                <div class="modal-footer border-secondary" style="background: #0f172a;">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensagens de sucesso e erro centralizadas na tela -->
    <div id="success-message" class="alert alert-success" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:300px; max-width:600px; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
    <div id="error-message" class="alert alert-warning" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:300px; max-width:600px; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>

    <!-- Toast Pop-ups Flutuantes em Stack Vertical: Stop Loss Pinado no Topo + 1 Notificação Recente Logo Abaixo -->
    <div id="toast-container-stack" class="toast-popup-container" style="display: none;" role="alert" aria-live="assertive" aria-atomic="true">
        <!-- Card 1: Stop Loss Pinado no Topo -->
        <div id="toast-stoploss-popup" class="toast-popup-card shadow-lg mb-2" style="display: none; border: 2px solid #ef4444; background: linear-gradient(135deg, #450a0a 0%, #1e1b4b 60%, #0f172a 100%);">
            <div class="toast-popup-header">
                <span class="badge bg-danger text-white d-flex align-items-center gap-1 pulse-badge-anim" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="bi bi-pin-angle-fill"></i> 📌 PINADO | STOP LOSS META VIGENTE
                </span>
                <button type="button" class="btn-close btn-close-white ms-auto" id="btn-fechar-toast-stoploss" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="toast-popup-body">
                <h6 id="toast-stoploss-titulo" class="fw-bold text-danger mb-1" style="font-size: 0.92rem;">⚠️ Stop Loss Meta Vigente Atingido!</h6>
                <p id="toast-stoploss-mensagem" class="text-light mb-2" style="font-size: 0.82rem; line-height: 1.35; color: #fecaca !important;">
                    Atenção: O limite de segurança da meta vigente foi atingido. Recomendado pausar novas apostas neste ciclo para salvaguarda de banca.
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
                    <i class="bi bi-exclamation-triangle-fill"></i> ALERTA (ABSTENÇÃO IA)
                </span>
                <button type="button" class="btn-close btn-close-white ms-auto" id="btn-fechar-toast" aria-label="Close" style="font-size: 0.75rem;"></button>
            </div>
            <div class="toast-popup-body">
                <h6 id="toast-notif-titulo" class="fw-bold text-warning mb-1" style="font-size: 0.92rem;">⚠️ Aposta Cancelada pela IA</h6>
                <p id="toast-notif-mensagem" class="text-light mb-3" style="font-size: 0.82rem; line-height: 1.35; color: #cbd5e1 !important;">
                    O Gatekeeper ativou Abstenção da IA. Se já realizou a entrada na casa de aposta, efetue o Cash Out imediato!
                </p>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <a href="#" id="toast-notif-link" class="btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow" style="border-radius: 8px; font-size: 0.82rem; padding: 6px 12px;">
                        <i class="bi bi-box-arrow-up-right"></i> Ver Aposta & Fazer Cash Out
                    </a>
                    <button type="button" id="btn-copiar-toast-notif" class="btn btn-sm btn-outline-light text-nowrap d-flex align-items-center gap-1 shadow-sm" title="Copiar texto do alerta" style="border-radius: 8px; font-size: 0.80rem; padding: 6px 12px; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3);">
                        <i class="bi bi-clipboard"></i> Copiar
                    </button>
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
    .btn-marcar-lida-item:hover {
        background: rgba(16, 185, 129, 0.35) !important;
        color: #6ee7b7 !important;
        transform: scale(1.08);
    }
    .btn-excluir-notif:hover {
        background: rgba(239, 68, 68, 0.35) !important;
        color: #fca5a5 !important;
        transform: scale(1.08);
    }

    /* Blindagem Global contra Overflow Horizontal e Proteção da Navbar */
    html, body {
        overflow-x: hidden !important;
        max-width: 100vw !important;
    }
    .navbar.sticky-top {
        width: 100% !important;
        max-width: 100vw !important;
        box-sizing: border-box !important;
    }

    /* Barra Lateral Dock Retrátil Permanente no Canto Direito */
    .sidebar-notif-dock {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 44px;
        height: 100vh;
        background: #0f172a;
        border-left: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: -2px 0 12px rgba(0, 0, 0, 0.4);
        z-index: 1060;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        padding-top: 18px;
        gap: 16px;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        user-select: none;
    }
    .sidebar-notif-dock:hover {
        background: #162032;
        border-left-color: rgba(56, 189, 248, 0.45);
        box-shadow: -4px 0 18px rgba(0, 0, 0, 0.6);
    }
    .sidebar-notif-dock .btn-dock-toggle {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: #f8fafc;
        transition: all 0.2s ease;
    }
    .sidebar-notif-dock:hover .btn-dock-toggle {
        background: rgba(56, 189, 248, 0.2);
        border-color: #38bdf8;
        color: #38bdf8 !important;
        transform: scale(1.05);
    }
    .sidebar-notif-dock .dock-bell-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
    }
    .sidebar-notif-dock .dock-vertical-text {
        margin-top: auto;
        margin-bottom: 24px;
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        font-size: 0.66rem;
        letter-spacing: 1.5px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.38);
        text-transform: uppercase;
        pointer-events: none;
        transition: color 0.2s ease;
    }
    .sidebar-notif-dock:hover .dock-vertical-text {
        color: #38bdf8;
    }

    /* Oculta suavemente a barra dock quando a sidebar expandida estiver aberta */
    body.sidebar-notif-open .sidebar-notif-dock {
        transform: translateX(100%);
        pointer-events: none;
        opacity: 0;
    }

    /* Sidebar Retrátil e Pinável de Notificações Fixada à Direita */
    .sidebar-notificacoes-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 400px;
        max-width: 92vw;
        height: 100vh;
        background: #1e293b;
        color: #f8fafc;
        box-shadow: -6px 0 30px rgba(0, 0, 0, 0.5);
        z-index: 1070;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .sidebar-notificacoes-drawer.open {
        transform: translateX(0);
    }
    .sidebar-notif-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(2px);
        z-index: 1065;
        opacity: 0;
        transition: opacity 0.25s ease;
        pointer-events: none;
    }
    .sidebar-notif-backdrop.show {
        opacity: 1;
        pointer-events: auto;
    }

    /* Botões do Topo e Toolbar da Sidebar */
    .sidebar-notif-header button:hover {
        background: rgba(255, 255, 255, 0.15) !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
    }
    .sidebar-notif-toolbar button:hover {
        filter: brightness(1.2);
    }

    /* Ajuste Dinâmico e Responsivo do Conteúdo Principal */
    @media (min-width: 992px) {
        #main-content {
            margin-right: 44px;
            transition: margin-right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        body.sidebar-notif-open #main-content,
        body.sidebar-notif-pinned #main-content {
            margin-right: 400px !important;
            transition: margin-right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
    }
    @media (max-width: 991px) {
        .sidebar-notif-dock {
            width: 38px;
            padding-top: 14px;
            gap: 14px;
        }
        .sidebar-notif-dock .dock-vertical-text {
            display: none;
        }
        #main-content {
            margin-right: 38px;
        }
    }
    body.sidebar-notif-pinned .sidebar-notificacoes-drawer {
        box-shadow: -2px 0 16px rgba(0, 0, 0, 0.4);
    }
    body.sidebar-notif-pinned .sidebar-notif-backdrop {
        display: none !important;
    }
    </style>

    <script>
    (function() {
        const notifApiUrl = '<?= base_url('notificacoes/nao-lidas') ?>';
        const markReadUrl = '<?= base_url('notificacoes/marcar-lida') ?>';
        const markAllReadUrl = '<?= base_url('notificacoes/marcar-todas-lidas') ?>';
        const unpinUrl = '<?= base_url('notificacoes/despinar') ?>';
        const deleteNotifUrl = '<?= base_url('notificacoes/excluir') ?>';
        const clearHistoryUrl = '<?= base_url('notificacoes/limpar-historico') ?>';
        const analisarGrokUrl = '<?= base_url('notificacoes/analisar-grok') ?>';

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

        function fallbackCopiarTexto(texto, callback) {
            const ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            ta.style.top = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                document.execCommand('copy');
                if (callback) callback();
            } catch(e) {}
            document.body.removeChild(ta);
        }

        function copiarTextoParaClipboard(texto, btnElement) {
            if (!texto) return;
            function feedbackOk() {
                if (!btnElement) return;
                const icon = btnElement.querySelector('i');
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                btnElement.classList.add('border-success');
                setTimeout(() => {
                    btnElement.innerHTML = originalHtml;
                    btnElement.classList.remove('border-success');
                }, 2000);
            }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(texto).then(feedbackOk).catch(() => {
                    fallbackCopiarTexto(texto, feedbackOk);
                });
            } else {
                fallbackCopiarTexto(texto, feedbackOk);
            }
        }

        function escapeHtmlNotif(str) {
            if (!str) return '';
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function abrirModalAnaliseGrok(notifId, titulo, mensagem) {
            const modalEl = document.getElementById('modalGrokAnaliseNotif');
            if (!modalEl) return;

            const titleEl = document.getElementById('grok-modal-title');
            const loadingEl = document.getElementById('grok-loading-state');
            const errorEl = document.getElementById('grok-error-state');
            const contentArea = document.getElementById('grok-content-area');
            const previewEl = document.getElementById('grok-pane-preview');
            const rawEl = document.getElementById('grok-pane-raw');
            const cacheBadge = document.getElementById('grok-cache-badge');
            const liveBadge = document.getElementById('grok-live-badge');

            if (titleEl) titleEl.textContent = '🤖 Grok AI: ' + titulo;
            if (loadingEl) loadingEl.style.display = 'block';
            if (errorEl) errorEl.style.display = 'none';
            if (contentArea) contentArea.style.display = 'none';
            if (cacheBadge) cacheBadge.style.display = 'none';
            if (liveBadge) liveBadge.style.display = 'none';

            ativarAbaGrok('preview');

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalBs = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalBs.show();
            } else if (typeof $ !== 'undefined' && $(modalEl).modal) {
                $(modalEl).modal('show');
            }

            fetch(analisarGrokUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    notificacao_id: notifId || 0,
                    titulo: titulo || '',
                    mensagem: mensagem || ''
                })
            })
            .then(r => r.json())
            .then(data => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (data && data.success && data.analise) {
                    if (data.cached) {
                        if (cacheBadge) {
                            cacheBadge.innerHTML = '<i class="bi bi-lightning-charge-fill"></i> Cache MySQL (0 chamadas)';
                            cacheBadge.style.display = 'inline-block';
                        }
                    } else {
                        if (liveBadge) {
                            liveBadge.innerHTML = '<i class="bi bi-globe"></i> Grok Cloud (Nova consulta)';
                            liveBadge.style.display = 'inline-block';
                        }
                    }

                    if (previewEl) {
                        if (typeof marked !== 'undefined' && marked.parse) {
                            previewEl.innerHTML = marked.parse(data.analise);
                        } else {
                            previewEl.textContent = data.analise;
                        }
                    }

                    if (rawEl) {
                        rawEl.textContent = data.analise;
                    }

                    if (contentArea) contentArea.style.display = 'block';
                } else {
                    if (errorEl) {
                        errorEl.textContent = (data && data.message) ? data.message : 'Não foi possível obter a análise da IA no momento.';
                        errorEl.style.display = 'block';
                    }
                }
            })
            .catch(err => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (errorEl) {
                    errorEl.textContent = 'Falha de conexão com o servidor: ' + err.message;
                    errorEl.style.display = 'block';
                }
            });
        }

        function ativarAbaGrok(aba) {
            const btnPreview = document.getElementById('tab-btn-grok-preview');
            const btnRaw = document.getElementById('tab-btn-grok-raw');
            const panePreview = document.getElementById('grok-pane-preview');
            const paneRaw = document.getElementById('grok-pane-raw');

            if (aba === 'raw') {
                if (btnRaw) btnRaw.classList.add('active');
                if (btnPreview) btnPreview.classList.remove('active');
                if (paneRaw) paneRaw.style.display = 'block';
                if (panePreview) panePreview.style.display = 'none';
            } else {
                if (btnPreview) btnPreview.classList.add('active');
                if (btnRaw) btnRaw.classList.remove('active');
                if (panePreview) panePreview.style.display = 'block';
                if (paneRaw) paneRaw.style.display = 'none';
            }
        }

        function aplicarFiltroBuscaNotificacoes() {
            const input = document.getElementById('input-busca-notificacoes');
            const clearBtn = document.getElementById('btn-limpar-busca-notif');
            const feedbackEl = document.getElementById('busca-notif-feedback');
            const contadorEl = document.getElementById('busca-notif-contador');
            const container = document.getElementById('lista-notificacoes-container');
            if (!container) return;

            const items = container.querySelectorAll('.notif-item');
            const emptyInitial = container.querySelector('#notif-empty-state');

            // Remove empty state de busca anterior
            const prevSearchEmpty = container.querySelector('.notif-search-empty-state');
            if (prevSearchEmpty) prevSearchEmpty.remove();

            if (!items.length) {
                if (feedbackEl) feedbackEl.style.setProperty('display', 'none', 'important');
                if (clearBtn) clearBtn.style.display = 'none';
                return;
            }

            const rawQuery = input ? input.value.trim() : '';
            const queryNorm = rawQuery.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

            if (clearBtn) {
                clearBtn.style.display = rawQuery ? 'inline-block' : 'none';
            }

            if (!queryNorm) {
                items.forEach(el => el.style.display = '');
                if (feedbackEl) feedbackEl.style.setProperty('display', 'none', 'important');
                if (emptyInitial) emptyInitial.style.display = 'none';
                return;
            }

            let matchesCount = 0;
            items.forEach(el => {
                const textNorm = (el.textContent || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
                if (textNorm.includes(queryNorm)) {
                    el.style.display = '';
                    matchesCount++;
                } else {
                    el.style.display = 'none';
                }
            });

            if (feedbackEl && contadorEl) {
                feedbackEl.style.setProperty('display', 'flex', 'important');
                contadorEl.textContent = matchesCount === 1 
                    ? '1 alerta encontrado' 
                    : `${matchesCount} de ${items.length} alertas encontrados`;
            }

            if (matchesCount === 0) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'text-center text-muted p-4 notif-search-empty-state';
                emptyDiv.style.fontSize = '0.84rem';
                emptyDiv.innerHTML = `
                    <i class="bi bi-search fs-4 d-block mb-2 text-secondary opacity-50"></i>
                    Nenhum alerta encontrado para "<strong>${escapeHtmlNotif(rawQuery)}</strong>".
                `;
                container.appendChild(emptyDiv);
            }
        }

        function checkNotificacoes() {
            fetch(notifApiUrl, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) return;

                updateNotifBadges(data.total_nao_lidas || 0);

                // Renderizar dropdown (Stop Loss sempre pinado no topo se pinada = 1)
                const container = document.getElementById('lista-notificacoes-container');
                if (container && data.notificacoes && data.notificacoes.length > 0) {
                    let html = '';
                    data.notificacoes.forEach(n => {
                        const isUnread = (parseInt(n.lida) === 0);
                        const isStopLoss = (n.tipo === 'STOP_LOSS_DIARIO');
                        const isPinada = (parseInt(n.pinada || 0) === 1);
                        const timeStr = n.criado_em ? n.criado_em.substring(5, 16).replace('-', '/') : '';

                        // Alertas de apostas, sentinela e in-play sempre direcionam canonicamente para /apostas com scroll e highlight
                        let linkHref = '#';
                        const isStopLoss = (n.tipo === 'STOP_LOSS_DIARIO');
                        const isGrokQuota = (n.tipo === 'GROK_CREDITOS_ESGOTADOS');

                        if (isStopLoss || isGrokQuota) {
                            linkHref = n.link ? (n.link.startsWith('http') ? n.link : '<?= base_url() ?>' + (n.link.startsWith('/') ? n.link.substring(1) : n.link)) : '#';
                        } else {
                            const fixIdParam = n.fixture_id ? 'fixture_id=' + n.fixture_id : '';
                            const destIdParam = n.aposta_id ? 'destaque_id=' + n.aposta_id : '';
                            const queryParts = [fixIdParam, destIdParam].filter(Boolean).join('&');
                            if (queryParts) {
                                linkHref = '<?= base_url('apostas') ?>?' + queryParts;
                            } else if (n.link && !n.link.includes('trends')) {
                                linkHref = n.link.startsWith('http') ? n.link : '<?= base_url() ?>' + (n.link.startsWith('/') ? n.link.substring(1) : n.link);
                            } else {
                                linkHref = '<?= base_url('apostas') ?>';
                            }
                        }

                        if (isStopLoss || isGrokQuota) {
                            const iconClass = isGrokQuota ? 'bi-robot' : (isPinada ? 'bi-pin-angle-fill' : 'bi-shield-exclamation');
                            const alertBadge = isGrokQuota ? '<span class="badge bg-warning text-dark" style="font-size: 0.65rem; padding: 2px 6px;">IA PAUSADA</span>' : (isPinada ? '<span class="badge bg-danger" style="font-size: 0.65rem; padding: 2px 6px;">📌 PINADO</span>' : '');
                            html += `
                                <div class="notif-item ${isPinada ? 'pinada' : ''} ${isUnread ? 'nao-lida' : ''}" data-notif-id="${n.id}" data-href="${linkHref}" style="border-left: 3px solid #ef4444; background: rgba(239, 68, 68, 0.08);">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <a href="${linkHref}" class="text-decoration-none d-flex align-items-center gap-1 flex-grow-1" style="color: inherit;">
                                            <strong style="font-size: 0.85rem; color: #ef4444;" class="d-flex align-items-center gap-1">
                                                <i class="bi ${iconClass} text-danger"></i> ${n.titulo}
                                            </strong>
                                        </a>
                                        <div class="d-flex align-items-center gap-1">
                                            <button type="button" class="btn btn-xs btn-copiar-notif" data-msg="${encodeURIComponent(n.mensagem)}" title="Copiar texto do alerta" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; padding: 2px 6px; color: #cbd5e1; font-size: 0.72rem; line-height: 1;">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                            ${isUnread ? `
                                                <button type="button" class="btn btn-xs btn-marcar-lida-item" data-notif-id="${n.id}" title="Marcar este alerta como lido" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 4px; padding: 2px 6px; color: #34d399; font-size: 0.72rem; line-height: 1;">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            ` : `
                                                <span class="text-secondary d-inline-flex align-items-center" title="Alerta lido" style="font-size: 0.72rem; padding: 2px 4px; opacity: 0.5;">
                                                    <i class="bi bi-check2-all text-info"></i>
                                                </span>
                                            `}
                                            ${alertBadge}
                                            ${isPinada && isStopLoss ? `
                                                <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1 btn-despinar-item" data-notif-id="${n.id}" title="Despinar alerta para não reexibir" style="font-size: 0.70rem; border-radius: 4px; line-height: 1.2; background: rgba(239,68,68,0.15);">
                                                    <i class="bi bi-pin-angle"></i> Despinar
                                                </button>
                                            ` : `
                                                <span class="notif-time">${timeStr}</span>
                                            `}
                                            <button type="button" class="btn btn-xs btn-excluir-notif" data-notif-id="${n.id}" title="Excluir este alerta da lista" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 4px; padding: 2px 6px; color: #f87171; font-size: 0.72rem; line-height: 1;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <a href="${linkHref}" class="text-decoration-none d-block" style="font-size: 0.78rem; color: #fecaca; line-height: 1.3;">
                                        ${n.mensagem}
                                    </a>
                                </div>
                            `;
                        } else {
                            const isItemAprovada = (n.tipo === 'APOSTA_CARTAO_APROVADA' || n.tipo === 'APOSTA_CRIADA' || n.tipo === 'NOVA_OPORTUNIDADE_AH' || n.tipo === 'OPORTUNIDADE_AO_VIVO');
                            const isLineup = (n.tipo === 'ESCALACAO_CONFIRMADA_ALERTA' || n.tipo === 'ESCALACAO_CONFIRMADA_FORCA_MAXIMA' || (n.titulo && (n.titulo.includes('Escalação') || n.titulo.includes('Súmula') || n.titulo.includes('Titulares'))) || (n.mensagem && (n.mensagem.includes('ESCALAÇÕES') || n.mensagem.includes('Titulares:'))));
                            const iconHtml = (n.tipo === 'OPORTUNIDADE_AO_VIVO') ? '<i class="bi bi-lightning-charge-fill text-warning me-1"></i>' : (isItemAprovada ? '<i class="bi bi-bullseye text-success me-1"></i>' : '');
                            const titleColor = isUnread ? (isItemAprovada ? '#4ade80' : '#f87171') : '#f1f5f9';
                            html += `
                                <div class="notif-item ${isUnread ? 'nao-lida' : ''}" data-notif-id="${n.id}" data-href="${linkHref}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <a href="${linkHref}" class="text-decoration-none d-flex align-items-center flex-grow-1 me-2" style="color: inherit;">
                                            <strong style="font-size: 0.85rem; color: ${titleColor};">
                                                ${iconHtml}${n.titulo}
                                            </strong>
                                        </a>
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="notif-time me-1">${timeStr}</span>
                                            ${isLineup ? `
                                                <button type="button" class="btn btn-xs btn-analisar-grok-notif" data-notif-id="${n.id}" data-titulo="${encodeURIComponent(n.titulo)}" data-msg="${encodeURIComponent(n.mensagem)}" title="Analisar escalação com Grok AI" style="background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.45); border-radius: 4px; padding: 2px 6px; color: #a5b4fc; font-size: 0.72rem; line-height: 1;">
                                                    <i class="bi bi-robot"></i>
                                                </button>
                                            ` : ''}
                                            <button type="button" class="btn btn-xs btn-copiar-notif" data-msg="${encodeURIComponent(n.mensagem)}" title="Copiar texto do alerta" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; padding: 2px 6px; color: #cbd5e1; font-size: 0.72rem; line-height: 1;">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                            ${isUnread ? `
                                                <button type="button" class="btn btn-xs btn-marcar-lida-item" data-notif-id="${n.id}" title="Marcar este alerta como lido" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 4px; padding: 2px 6px; color: #34d399; font-size: 0.72rem; line-height: 1;">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            ` : `
                                                <span class="text-secondary d-inline-flex align-items-center" title="Alerta lido" style="font-size: 0.72rem; padding: 2px 4px; opacity: 0.5;">
                                                    <i class="bi bi-check2-all text-info"></i>
                                                </span>
                                            `}
                                            <button type="button" class="btn btn-xs btn-excluir-notif" data-notif-id="${n.id}" title="Excluir este alerta da lista" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 4px; padding: 2px 6px; color: #f87171; font-size: 0.72rem; line-height: 1;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <a href="${linkHref}" class="text-decoration-none d-block" style="font-size: 0.78rem; color: #cbd5e1; line-height: 1.3;">
                                        ${n.mensagem}
                                    </a>
                                </div>
                            `;
                        }
                    });
                    container.innerHTML = html;

                    // Marcar alerta individual como lido ao clicar no botão de check
                    container.querySelectorAll('.btn-marcar-lida-item').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const nid = this.getAttribute('data-notif-id');
                            if (!nid) return;

                            // Dispara marcação no backend
                            fetch(markReadUrl + '/' + nid, {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });

                            // Atualização visual instantânea do card do alerta
                            const itemEl = this.closest('.notif-item');
                            if (itemEl) {
                                itemEl.classList.remove('nao-lida');
                                const titleEl = itemEl.querySelector('strong');
                                if (titleEl) titleEl.style.color = '#f1f5f9';
                            }

                            // Transforma o botão no ícone de lido
                            const parentDiv = this.parentElement;
                            this.remove();
                            if (parentDiv) {
                                const checkSpan = document.createElement('span');
                                checkSpan.className = 'text-secondary d-inline-flex align-items-center';
                                checkSpan.title = 'Alerta lido';
                                checkSpan.style.cssText = 'font-size: 0.72rem; padding: 2px 4px; opacity: 0.5;';
                                checkSpan.innerHTML = '<i class="bi bi-check2-all text-info"></i>';
                                parentDiv.appendChild(checkSpan);
                            }

                            // Decrementa o badge numérico no sininho e na dock bar
                            const badge = document.getElementById('badge-notificacoes');
                            let count = parseInt(badge ? (badge.textContent || '0') : '0', 10);
                            updateNotifBadges(Math.max(0, count - 1));
                        });
                    });

                    // Excluir alerta individual da lista
                    container.querySelectorAll('.btn-excluir-notif').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const nid = this.getAttribute('data-notif-id');
                            if (!nid) return;

                            const itemEl = this.closest('.notif-item');
                            if (itemEl) {
                                itemEl.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                                itemEl.style.opacity = '0';
                                itemEl.style.transform = 'translateX(20px)';
                                setTimeout(() => {
                                    const wasUnread = itemEl.classList.contains('nao-lida');
                                    itemEl.remove();

                                    // Se era não lido, decrementa badge
                                    if (wasUnread) {
                                        const badge = document.getElementById('badge-notificacoes');
                                        let count = parseInt(badge ? (badge.textContent || '0') : '0', 10);
                                        updateNotifBadges(Math.max(0, count - 1));
                                    }

                                    // Se lista ficou vazia, exibe empty state
                                    if (!container.querySelector('.notif-item')) {
                                        container.innerHTML = `
                                            <div class="text-center text-muted p-4" id="notif-empty-state" style="font-size: 0.88rem;">
                                                <i class="bi bi-bell-slash fs-4 d-block mb-2 text-secondary"></i>
                                                Nenhuma notificação recente.
                                            </div>
                                        `;
                                    }
                                }, 250);
                            }

                            // Dispara exclusão no backend
                            fetch(deleteNotifUrl + '/' + nid, {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                        });
                    });

                    // Despinar no clique do botão despinar dentro do dropdown
                    container.querySelectorAll('.btn-despinar-item').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const nid = this.getAttribute('data-notif-id');
                            despinarAlerta(nid);
                        });
                    });

                    // Analisar escalação com Grok AI ao clicar no botão do robô
                    container.querySelectorAll('.btn-analisar-grok-notif').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const notifId = this.getAttribute('data-notif-id') || '';
                            let titulo = 'Escalação';
                            let mensagem = '';
                            try { titulo = decodeURIComponent(this.getAttribute('data-titulo') || 'Escalação'); } catch(err) { titulo = this.getAttribute('data-titulo') || 'Escalação'; }
                            try { mensagem = decodeURIComponent(this.getAttribute('data-msg') || ''); } catch(err) { mensagem = this.getAttribute('data-msg') || ''; }
                            abrirModalAnaliseGrok(notifId, titulo, mensagem);
                        });
                    });

                    // Copiar texto do alerta ao clicar no botão de cópia
                    container.querySelectorAll('.btn-copiar-notif').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const rawMsg = this.getAttribute('data-msg');
                            let textToCopy = '';
                            if (rawMsg) {
                                try { textToCopy = decodeURIComponent(rawMsg); } catch(err) { textToCopy = rawMsg; }
                            }
                            if (!textToCopy) {
                                const itemEl = this.closest('.notif-item');
                                const msgEl = itemEl ? (itemEl.querySelector('.notif-mensagem-texto') || itemEl.querySelector('a:last-child')) : null;
                                textToCopy = msgEl ? msgEl.textContent.trim() : '';
                            }
                            copiarTextoParaClipboard(textToCopy, this);
                        });
                    });

                    // Evento de clique para marcar lida e direcionar para tela de apostas com scroll e highlight
                    container.querySelectorAll('.notif-item').forEach(el => {
                        el.addEventListener('click', function(e) {
                            if (e.target.closest('.btn-despinar-item') || e.target.closest('.btn-copiar-notif') || e.target.closest('.btn-analisar-grok-notif') || e.target.closest('.btn-marcar-lida-item') || e.target.closest('.btn-excluir-notif')) return;

                            const nid = this.getAttribute('data-notif-id');
                            if (nid) {
                                fetch(markReadUrl + '/' + nid, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                            }

                            const href = this.getAttribute('data-href') || this.getAttribute('href');
                            if (!href || href === '#') return;

                            try {
                                const parsedUrl = new URL(href, window.location.origin);
                                const destaqueId = parsedUrl.searchParams.get('destaque_id');
                                const fixtureId = parsedUrl.searchParams.get('fixture_id');
                                const dataJogo = parsedUrl.searchParams.get('data_jogo');

                                // Se já estiver na tela de apostas (destacarApostaPorId existe), aplica scroll e highlight suave
                                if (typeof window.destacarApostaPorId === 'function' && (destaqueId || fixtureId)) {
                                    e.preventDefault();
                                    window.history.pushState({}, '', href);
                                    window.destacarApostaPorId(destaqueId, dataJogo, fixtureId);

                                    // Fecha a barra lateral se não estiver pinada
                                    if (!isSidebarPinned()) {
                                        closeSidebarNotif();
                                    }
                                    return;
                                }
                            } catch(err) {}

                            // Se estiver em outra página, navega diretamente para /apostas
                            e.preventDefault();
                            window.location.href = href;
                        });
                    });

                    // Reaplica filtro ativo de busca (se houver termo digitado no input)
                    aplicarFiltroBuscaNotificacoes();
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

            const isAprovada = (notif.tipo === 'APOSTA_CARTAO_APROVADA' || notif.tipo === 'APOSTA_CRIADA' || notif.tipo === 'NOVA_OPORTUNIDADE_AH' || notif.tipo === 'OPORTUNIDADE_AO_VIVO');
            const isSinalVerde = (notif.tipo === 'SINAL_VERDE_HORA_APOSTAR' || notif.tipo === 'SINAL_VERDE_CONFIRMADA');
            const isAlertaSentinela = (notif.tipo === 'ALERTA_SENTINELA_IA');

            if (badgeEl) {
                if (notif.tipo === 'OPORTUNIDADE_AO_VIVO') {
                    badgeEl.className = 'badge bg-warning text-dark d-flex align-items-center gap-1 pulse-badge-anim fw-bold';
                    badgeEl.innerHTML = '<i class="bi bi-lightning-charge-fill"></i> ⚡ OPORTUNIDADE AO VIVO (IN-PLAY)';
                } else if (notif.tipo === 'SINAL_VERDE_HORA_APOSTAR') {
                    badgeEl.className = 'badge bg-success d-flex align-items-center gap-1 pulse-badge-anim fw-bold';
                    badgeEl.innerHTML = '<i class="bi bi-clock-history"></i> 🟢 HORA DE APOSTAR (SINAL VERDE)';
                } else if (notif.tipo === 'SINAL_VERDE_CONFIRMADA') {
                    badgeEl.className = 'badge bg-success d-flex align-items-center gap-1 pulse-badge-anim fw-bold';
                    badgeEl.innerHTML = '<i class="bi bi-shield-check"></i> 🟢 FORÇA MÁXIMA CONFIRMADA';
                } else if (isAlertaSentinela) {
                    badgeEl.className = 'badge bg-warning text-dark d-flex align-items-center gap-1 pulse-badge-anim fw-bold';
                    badgeEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ⚠️ ALERTA PRÉ-JOGO (SENTINELA IA)';
                } else if (notif.tipo === 'NOVA_OPORTUNIDADE_AH') {
                    badgeEl.className = 'badge bg-success d-flex align-items-center gap-1 pulse-badge-anim';
                    badgeEl.innerHTML = '<i class="bi bi-lightbulb-fill"></i> 💡 SUGESTÃO (HANDICAP)';
                } else if (notif.tipo === 'APOSTA_CARTAO_APROVADA') {
                    badgeEl.className = 'badge bg-success d-flex align-items-center gap-1 pulse-badge-anim';
                    badgeEl.innerHTML = '<i class="bi bi-lightbulb-fill"></i> 💡 SUGESTÃO +EV (CARTÕES)';
                } else if (isAprovada) {
                    badgeEl.className = 'badge bg-success d-flex align-items-center gap-1 pulse-badge-anim';
                    badgeEl.innerHTML = '<i class="bi bi-lightbulb-fill"></i> 💡 SUGESTÃO GERADA (IA)';
                } else {
                    badgeEl.className = 'badge bg-danger d-flex align-items-center gap-1 pulse-badge-anim';
                    badgeEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> ALERTA (ABSTENÇÃO IA)';
                }
            }

            if (linkEl) {
                if (notif.tipo === 'SINAL_VERDE_HORA_APOSTAR') {
                    linkEl.className = 'btn btn-sm btn-success fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-check2-circle"></i> Ver Aposta & Fazer Entrada';
                } else if (notif.tipo === 'SINAL_VERDE_CONFIRMADA') {
                    linkEl.className = 'btn btn-sm btn-success fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Ver Aposta Confirmada';
                } else if (isAlertaSentinela) {
                    linkEl.className = 'btn btn-sm btn-warning text-dark fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Ver Aposta & Avaliar Risco';
                } else if (isAprovada) {
                    linkEl.className = 'btn btn-sm btn-success fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Ver Sugestão';
                } else {
                    linkEl.className = 'btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1 w-100 justify-content-center shadow';
                    linkEl.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Ver Aposta & Fazer Cash Out';
                }
            }

            let linkHref = defaultLink;
            if (notif.fixture_id || notif.aposta_id) {
                const fixIdParam = notif.fixture_id ? 'fixture_id=' + notif.fixture_id : '';
                const destIdParam = notif.aposta_id ? 'destaque_id=' + notif.aposta_id : '';
                const queryParts = [fixIdParam, destIdParam].filter(Boolean).join('&');
                linkHref = '<?= base_url('apostas') ?>?' + queryParts;
            } else if (notif.link && !notif.link.includes('trends')) {
                linkHref = notif.link.startsWith('http') ? notif.link : '<?= base_url() ?>' + (notif.link.startsWith('/') ? notif.link.substring(1) : notif.link);
            }
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

                if (linkHref) {
                    try {
                        const parsedUrl = new URL(linkHref, window.location.origin);
                        const destaqueId = parsedUrl.searchParams.get('destaque_id');
                        const fixtureId = parsedUrl.searchParams.get('fixture_id');
                        const dataJogo = parsedUrl.searchParams.get('data_jogo');

                        if (typeof window.destacarApostaPorId === 'function' && (destaqueId || fixtureId)) {
                            e.preventDefault();
                            window.history.pushState({}, '', linkHref);
                            window.destacarApostaPorId(destaqueId, dataJogo, fixtureId);
                            return;
                        }
                    } catch(err) {}

                    e.preventDefault();
                    window.location.href = linkHref;
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

            const btnCopiarToast = document.getElementById('btn-copiar-toast-notif');
            if (btnCopiarToast) {
                btnCopiarToast.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    copiarTextoParaClipboard(notif.mensagem, btnCopiarToast);
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

        function updateNotifBadges(total) {
            const badgeNav = document.getElementById('badge-notificacoes');
            const badgeDock = document.getElementById('badge-notificacoes-dock');
            const num = parseInt(total || 0, 10);
            const textVal = num > 99 ? '99+' : (num > 0 ? num : '0');
            const displayVal = num > 0 ? 'inline-block' : 'none';
            if (badgeNav) {
                badgeNav.textContent = textVal;
                badgeNav.style.display = displayVal;
            }
            if (badgeDock) {
                badgeDock.textContent = textVal;
                badgeDock.style.display = displayVal;
            }
        }

        function isSidebarOpen() {
            const sb = document.getElementById('sidebar-notificacoes');
            return sb && sb.classList.contains('open');
        }

        function isSidebarPinned() {
            return document.body.classList.contains('sidebar-notif-pinned');
        }

        function openSidebarNotif() {
            const sb = document.getElementById('sidebar-notificacoes');
            const backdrop = document.getElementById('sidebar-notif-backdrop');
            if (!sb) return;

            sb.classList.add('open');
            document.body.classList.add('sidebar-notif-open');
            try { localStorage.setItem('sidebar_notif_expanded', 'true'); } catch(e) {}

            if (!isSidebarPinned() && backdrop && window.innerWidth < 992) {
                backdrop.style.display = 'block';
                void backdrop.offsetWidth;
                backdrop.classList.add('show');
            }
        }

        function closeSidebarNotif() {
            const sb = document.getElementById('sidebar-notificacoes');
            const backdrop = document.getElementById('sidebar-notif-backdrop');
            if (!sb) return;

            sb.classList.remove('open');
            document.body.classList.remove('sidebar-notif-open');
            try { localStorage.setItem('sidebar_notif_expanded', 'false'); } catch(e) {}

            if (isSidebarPinned()) {
                setSidebarPinned(false);
            }
            if (backdrop) {
                backdrop.classList.remove('show');
                setTimeout(function() {
                    if (!isSidebarOpen() && backdrop) backdrop.style.display = 'none';
                }, 250);
            }
        }

        function toggleSidebarNotif() {
            if (isSidebarOpen()) {
                closeSidebarNotif();
            } else {
                openSidebarNotif();
            }
        }

        function setSidebarPinned(pinned) {
            const pinBtn = document.getElementById('btn-pin-sidebar-notif');
            const pinIcon = document.getElementById('icon-pin-sidebar');
            const backdrop = document.getElementById('sidebar-notif-backdrop');

            if (pinned) {
                document.body.classList.add('sidebar-notif-pinned');
                try { localStorage.setItem('sidebar_notif_pinned', 'true'); } catch(e) {}
                if (pinBtn) {
                    pinBtn.classList.remove('btn-outline-secondary');
                    pinBtn.classList.add('btn-warning', 'text-dark');
                    pinBtn.setAttribute('title', 'Desafixar barra lateral');
                }
                if (pinIcon) {
                    pinIcon.className = 'bi bi-pin-fill';
                }
                if (backdrop) {
                    backdrop.classList.remove('show');
                    backdrop.style.display = 'none';
                }
                openSidebarNotif();
            } else {
                document.body.classList.remove('sidebar-notif-pinned');
                try { localStorage.setItem('sidebar_notif_pinned', 'false'); } catch(e) {}
                if (pinBtn) {
                    pinBtn.classList.remove('btn-warning', 'text-dark');
                    pinBtn.classList.add('btn-outline-secondary');
                    pinBtn.setAttribute('title', 'Fixar barra lateral aberta');
                }
                if (pinIcon) {
                    pinIcon.className = 'bi bi-pin-angle';
                }
                if (isSidebarOpen() && backdrop && window.innerWidth < 992) {
                    backdrop.style.display = 'block';
                    void backdrop.offsetWidth;
                    backdrop.classList.add('show');
                }
            }
        }

        function togglePinSidebar() {
            setSidebarPinned(!isSidebarPinned());
        }

        // Exporta para o escopo global para que gatilhos inline e outros módulos possam acionar
        window.toggleSidebarNotif = toggleSidebarNotif;
        window.openSidebarNotif = openSidebarNotif;
        window.closeSidebarNotif = closeSidebarNotif;
        window.setSidebarPinned = setSidebarPinned;

        document.addEventListener('DOMContentLoaded', function() {
            // Gatilho de abertura pela barra lateral dock (retraída)
            const dockBar = document.getElementById('sidebar-notif-dock');
            if (dockBar) {
                dockBar.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSidebarNotif();
                });
            }

            const dockToggleBtn = document.getElementById('btn-dock-toggle-notif');
            if (dockToggleBtn) {
                dockToggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSidebarNotif();
                });
            }

            // Gatilho de abertura do sino no menu
            const toggleBtn = document.getElementById('btn-toggle-sidebar-notif');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebarNotif();
                });
            }

            // Botão recolher para barra lateral no topo da sidebar
            const collapseBtn = document.getElementById('btn-collapse-sidebar-notif');
            if (collapseBtn) {
                collapseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebarNotif();
                });
            }

            // Botão fechar sidebar tradicional
            const closeBtn = document.getElementById('btn-close-sidebar-notif');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebarNotif();
                });
            }

            // Botão fixar (pin) sidebar
            const pinBtn = document.getElementById('btn-pin-sidebar-notif');
            if (pinBtn) {
                pinBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    togglePinSidebar();
                });
            }

            // Clique no backdrop fecha a sidebar se não pinada
            const backdrop = document.getElementById('sidebar-notif-backdrop');
            if (backdrop) {
                backdrop.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!isSidebarPinned()) {
                        closeSidebarNotif();
                    }
                });
            }

            // Tecla Esc fecha a sidebar se não pinada
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isSidebarOpen() && !isSidebarPinned()) {
                    closeSidebarNotif();
                }
            });

            // Restaura estado expandido / pinado persistido do usuário
            try {
                const wasPinned = localStorage.getItem('sidebar_notif_pinned') === 'true';
                const wasExpanded = localStorage.getItem('sidebar_notif_expanded') === 'true';
                if (wasPinned) {
                    setSidebarPinned(true);
                } else if (wasExpanded && window.innerWidth >= 992) {
                    openSidebarNotif();
                }
            } catch(e) {}

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
                        updateNotifBadges(0);
                        document.querySelectorAll('.notif-item.nao-lida').forEach(el => {
                            el.classList.remove('nao-lida');
                        });
                    });
                });
            }

            const btnLimpar = document.getElementById('btn-limpar-historico-notif');
            if (btnLimpar) {
                btnLimpar.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!confirm('Deseja realmente limpar todo o histórico de alertas?')) return;

                    fetch(clearHistoryUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(() => {
                        updateNotifBadges(0);
                        const container = document.getElementById('lista-notificacoes-container');
                        if (container) {
                            container.innerHTML = `
                                <div class="text-center text-muted p-4" id="notif-empty-state" style="font-size: 0.88rem;">
                                    <i class="bi bi-bell-slash fs-4 d-block mb-2 text-secondary"></i>
                                    Nenhuma notificação recente.
                                </div>
                            `;
                        }
                    });
                });
            }

            // Eventos da Caixa de Pesquisa de Notificações em Tempo Real
            const searchInput = document.getElementById('input-busca-notificacoes');
            const clearSearchBtn = document.getElementById('btn-limpar-busca-notif');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    aplicarFiltroBuscaNotificacoes();
                });
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && this.value.trim() !== '') {
                        e.preventDefault();
                        e.stopPropagation();
                        this.value = '';
                        aplicarFiltroBuscaNotificacoes();
                    }
                });
            }

            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (searchInput) {
                        searchInput.value = '';
                        searchInput.focus();
                    }
                    aplicarFiltroBuscaNotificacoes();
                });
            }

            // Alternância de Abas (Visualização Formatada vs Texto Puro) no modal do Grok
            const tabPreview = document.getElementById('tab-btn-grok-preview');
            const tabRaw = document.getElementById('tab-btn-grok-raw');
            if (tabPreview) {
                tabPreview.addEventListener('click', function(e) {
                    e.preventDefault();
                    ativarAbaGrok('preview');
                });
            }
            if (tabRaw) {
                tabRaw.addEventListener('click', function(e) {
                    e.preventDefault();
                    ativarAbaGrok('raw');
                });
            }

            // Botão copiar dentro do modal de análise do Grok
            const btnCopiarGrok = document.getElementById('btn-copiar-analise-grok');
            if (btnCopiarGrok) {
                btnCopiarGrok.addEventListener('click', function(e) {
                    e.preventDefault();
                    const rawEl = document.getElementById('grok-pane-raw');
                    const previewEl = document.getElementById('grok-pane-preview');
                    const texto = (rawEl && rawEl.textContent) ? rawEl.textContent : (previewEl ? previewEl.innerText : '');
                    copiarTextoParaClipboard(texto, this);
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

