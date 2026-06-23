<?php
// Garante timezone correto para todas as funções de data/hora neste arquivo
date_default_timezone_set('America/Sao_Paulo');
?>
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
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-P312EQG53Y"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 0;
        width: 100%;
        height: 80px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 24px;
    }

    #head-bar .logo-container {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    #head-bar .logo-container img {
        height: 40px; /* Ajuste a altura conforme necessário */
        width: auto;
        object-fit: contain;
        display: block;
        margin-bottom: 4px;
    }

    #head-bar .logo-container .subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 13px;
        margin: 0;
        font-weight: 400;
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
        display: flex;
        align-items: center;
        gap: 12px;
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
                    <p class="subtitle">Plataforma Minha Fiscalização</p>
                </div>
            </div>
            
            <div class="header-buttons">
                <!-- Exibe o timezone atual do servidor -->
                <span id="server-timezone" style="margin-right: 16px; font-size: 0.95em; color: #333; background: #f3f3f3; border-radius: 4px; padding: 4px 10px; display: flex; align-items: center; gap: 4px;">
                    <i class="bi bi-clock-history" style="font-size: 1.1em;"></i>
                    <?php
                        $tz = new DateTimeZone('America/Sao_Paulo');
                        $dt = new DateTime('now', $tz);
                        echo $dt->format('H:i') . 'h';
                        //echo 'America/Sao_Paulo · ' . $dt->format('H:i') . 'h';
                    ?>
                </span>
                <!-- Botão para abrir a sidebar -->
                <button id="openSidebarBtn" class="btn btn-light">
                    <i class="bi bi-person-circle"></i>
                    <span class="ms-2"><?php echo isset($_SESSION['nome_usuario_logado']) ? $_SESSION['nome_usuario_logado'] : 'Logar'; ?></span>
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
                    <p class="text-white">Olá <?php echo isset($_SESSION['nome_usuario_logado']) ? $_SESSION['nome_usuario_logado'] : 'Visitante'; ?></p>
                </li>

                <?php if (!empty($airflowUsername)): ?>
                <li>
                <div class="bg-light text-dark p-2 rounded mb-2">
                    <small>Seu usuário no Airflow</small>
                    <div class="d-flex align-items-center mb-1">
                        <span id="airflow-username-text" class="fw-bold me-2"><?= htmlspecialchars($airflowUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyAirflowUsername()">Copiar usuário</button>
                    </div>
                    <div class="d-flex align-items-center">
                        <?php
                            $senhaUsuario = '';
                            if (isset($_SESSION['id_usuario_logado'])) {
                                $usuarioModel = new \App\Models\UsuarioModel();
                                $usuario = $usuarioModel->find($_SESSION['id_usuario_logado']);
                                if ($usuario && isset($usuario->senha)) {
                                    $senhaUsuario = $usuario->senha;
                                }
                            }
                        ?>
                        <span id="airflow-password-text" class="fw-bold me-2">*****</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyAirflowPassword()" data-password="<?= htmlspecialchars($senhaUsuario, ENT_QUOTES, 'UTF-8'); ?>">Copiar senha</button>
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
            </ul>

            <ul class="list-unstyled">
                <li>
                    <?php if (!isset($_SESSION['nome_usuario_logado']) || empty($_SESSION['nome_usuario_logado'])): ?>
                        
                        <?php echo anchor("sigInUsuario","Experimentar", ['class' => 'nav-link px-2 px-lg-2'])  ?>
                        
                        <?php echo anchor("loginUsuario","Entrar", ['class' => 'nav-link px-2 px-lg-2'])  ?>
                        
                        <!-- ?php echo anchor("sigInUsuario","Assinar")  ?-->    

                    <?php endif; ?>
                </li>


                <!-- SE O USUÁRIO ESTÁ LOGADO -->
                <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                    <!-- 
                    // Verifica se o usuário tem perfil Admin ou a flag de admin está ativa
                    // if ($isAdmin): 
                    -->
                    <li><hr class="text-white" style="margin: 10px 0;"></li>
                    <li><p class="text-white">🗄️ Menus do Sistema</p></li>
                    
                    <li>
                        <div class="dropdown">
                            <a class="nav-link px-2 px-lg-2 dropdown-toggle" href="#" data-bs-toggle="dropdown" style="color: white;">
                                🔍 FISCALIZAÇÃO
                            </a>
                            <div class="dropdown-menu">
                                <h6 class="dropdown-header text-primary fw-bold">📝 Cadastros</h6>
                                <a class="dropdown-item" href="<?= base_url('listAreaAtuacao') ?>">🏢 Áreas de Atuação</a>
                                <a class="dropdown-item" href="<?= base_url('listAtividadeMacro') ?>">📊 Atividades Macro</a>
                                <a class="dropdown-item" href="<?= base_url('listAvaliacaoQualidadeSla') ?>">⭐ Avaliação SLA</a>
                                <a class="dropdown-item" href="<?= base_url('listCatalogoServicos') ?>">📚 Catálogo de Serviços</a>
                                <a class="dropdown-item" href="<?= base_url('listContrato') ?>">📜 Contratos</a>
                                <a class="dropdown-item" href="<?= base_url('listItemContrato') ?>">📝 Itens de Contrato</a>
                                <a class="dropdown-item" href="<?= base_url('listReajusteItemContrato') ?>">📈 Reajustes de Contrato</a>
                                <a class="dropdown-item" href="<?= base_url('listItemOs') ?>">🔧 Itens OS</a>
                                <a class="dropdown-item" href="<?= base_url('listPerfil') ?>">🔐 Perfis</a>
                                <a class="dropdown-item" href="<?= base_url('listStatus') ?>">📊 Status</a>
                                <a class="dropdown-item" href="<?= base_url('listStatusRecebimento') ?>">✅ Status Recebimento</a>
                                <a class="dropdown-item" href="<?= base_url('listTipoDocumento') ?>">📑 Tipos de Documento</a>
                                <a class="dropdown-item" href="<?= base_url('listUsuario') ?>">👤 Usuários</a>
                                
                                <div class="dropdown-divider"></div>
                                <h6 class="dropdown-header text-primary fw-bold">⚡ Execução</h6>
                                <a class="dropdown-item" href="<?= base_url('listServico') ?>">🛠️ Serviços</a>
                                <a class="dropdown-item" href="<?= base_url('listOrdemServico') ?>">📋 Ordens de Serviço (OS)</a>
                                <a class="dropdown-item" href="<?= base_url('listDocumentoRecebimento') ?>">📄 Docs Recebimento</a>
                            </div>
                        </div>
                    </li>

                    <li>
                        <div class="dropdown">
                            <a class="nav-link px-2 px-lg-2 dropdown-toggle" href="#" data-bs-toggle="dropdown" style="color: white;">
                                🚀 Gestão Ágil
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="<?= base_url('agile/dashboard') ?>">📊 Painel Ágil</a>
                                <a class="dropdown-item" href="<?= base_url('agile/demandas') ?>">📋 Demandas</a>
                                <a class="dropdown-item" href="<?= base_url('agile/sistemas') ?>">🖥️ Sistemas</a>
                                <a class="dropdown-item" href="<?= base_url('docs/index.html') ?>" target="_blank">📖 Manual do Usuário</a>
                            </div>
                        </div>
                    </li>

                    <li>
                        <div class="dropdown">
                            <a class="nav-link px-2 px-lg-2 dropdown-toggle" href="#" data-bs-toggle="dropdown" style="color: white;">
                                📊 Relatórios
                            </a>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#">Em breve...</a>
                            </div>
                        </div>
                    </li>
                    
                    
                    
                    <li><hr class="text-white" style="margin: 10px 0;"></li>    
                    <li><p class="text-white">📊 Admin - Dashboard</p></li>
                    <li>
                        <?php echo anchor("admin/dashboard", "📈 Dashboard Geral", ['class' => 'nav-link px-2 px-lg-2']) ?>
                    </li>
                    <li>
                        <?php echo anchor("admin/pagamento-inicial", "💵 Confirmar Pagamento Inicial", ['class' => 'nav-link px-2 px-lg-2']) ?>
                    </li>

                    
                    
                    
                    <li><hr class="text-white" style="margin: 10px 0;"></li>
                    <li><p class="text-white">👥 Admin - Sistema</p></li>
                    <li>
                        <?php echo anchor("listPerfil", "🔐 Perfis", ['class' => 'nav-link px-2 px-lg-2']) ?>
                    </li>
                    <li>
                        <?php echo anchor("listUsuario", "👤 Usuários", ['class' => 'nav-link px-2 px-lg-2']) ?>
                    </li>
                    
                    <li><hr class="text-white" style="margin: 10px 0;"></li>
                    
                <?php // endif; ?>
                <?php endif; ?>

                <li>
                    <hr class="text-white" style="margin: 10px 0;">
                </li>

                <li>
                    <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                        <a href="<?= base_url('docs/index.html') ?>" class="nav-link px-2 px-lg-2" target="_blank" style="color: #87ceeb;">
                            📚 Documentação
                        </a>
                    <?php else: ?>
                        <?php echo anchor("sigInUsuario", "📚 Documentação", ['class' => 'nav-link px-2 px-lg-2 terms-link', 'style' => 'color: #87ceeb;']); ?>
                    <?php endif; ?>
                </li>

                <li>

                    <?php if (isset($_SESSION['nome_usuario_logado']) || !empty($_SESSION['nome_usuario_logado'])): ?>
                        <?php echo anchor(route_to('Usuario.logOut'), "Sair", ['class' => 'nav-link px-2 px-lg-2']); ?>
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
                    <a href="/" class="nav-link px-2 px-lg-2" title="Início">
                        <i class="fas fa-home" style="font-size: 28px; vertical-align: middle;"></i>
                    </a>
                </li>
            </ul>

            <div id="itens-menu-outros" class="navbar-nav ms-auto p-4 p-lg-0">
                
            
                <?php if (!isset($_SESSION['nome_usuario_logado']) || empty($_SESSION['nome_usuario_logado'])): ?>
                    
                    <?php echo anchor("sigInUsuario","Experimentar", ['class' => 'nav-link px-2 px-lg-2'])  ?>
                    

                <?php endif; ?>


                <?php 
                // Verifica se os serviços estão bloqueados por assinatura expirada
                $servicesBlocked = isset($_SESSION['subscription_services_blocked']) && $_SESSION['subscription_services_blocked'];
                ?>

                <!--
                <?php if (isset($_SESSION['nome_usuario_logado']) && !empty($_SESSION['nome_usuario_logado'])): ?>
                    <?php echo anchor("cursos", "CURSOS", ['class' => 'nav-link px-2 px-lg-2']) ?>
                <?php endif; ?>
                -->
                
                <!-- <a href="https://github.com/paulomnasc/mydataflow-forum/discussions" class="nav-link px-2 px-lg-2" target="_blank" rel="noopener noreferrer">FORUM</a> -->
                
                
                <li class="nav-item dropdown <?= $servicesBlocked ? 'disabled' : '' ?>">


                    

                    <?php if ($servicesBlocked): ?>
                        <a class="nav-link dropdown-toggle" href="#" style="opacity: 0.5; cursor: not-allowed;" 
                           title="Renovar assinatura para acessar os serviços" onclick="event.preventDefault(); alert('⚠️ Assinatura expirada!\n\nPara acessar os serviços, renove sua assinatura.')">
                            SERVIÇOS 🔒
                        </a>
                    <?php else: ?>
                        <!-- <a class="nav-link dropdown-toggle" href="#" id="servicesDrop" data-bs-toggle="dropdown">
                            SERVIÇOS
                        </a>
                        <div class="dropdown-menu">
                            <?php if (isset($userHasPipelinesAccess) && $userHasPipelinesAccess): ?>
                                <?php $airflowExternalUrl = getenv('AIRFLOW_EXTERNAL_URL') ?: 'http://localhost:8080'; ?>
                                <a class="dropdown-item" href="<?= htmlspecialchars($airflowExternalUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                    <svg role="img" xmlns="http://www.w3.org/2000/svg" width="1.1em" height="1.1em" viewBox="0 0 256 256" style="vertical-align: middle; margin-right: 4px; pointer-events: none;">
                                        <title>Apache Airflow</title>
                                        <path fill="#017cee" d="m4.127 254.974l122.568-125.639a2.265 2.265 0 0 0 .274-2.896c-7.453-10.406-21.207-12.21-26.303-19.203c-15.098-20.711-18.929-32.434-25.417-31.708a1.98 1.98 0 0 0-1.178.622l-44.276 45.388C4.322 147.628.661 205.137 0 253.295a2.4 2.4 0 0 0 4.127 1.679"/>
                                        <path fill="#00ad46" d="M254.974 251.873L129.335 129.296a2.266 2.266 0 0 0-2.9-.274c-10.406 7.457-12.21 21.207-19.203 26.303c-20.712 15.098-32.435 18.93-31.709 25.417c.066.451.286.866.622 1.174l45.389 44.276c26.09 25.473 83.598 29.134 131.757 29.795a2.401 2.401 0 0 0 1.683-4.114"/>
                                        <path fill="#04d659" d="M121.534 226.205c-14.263-13.915-20.872-41.44 6.462-98.2c-44.437 19.859-60.008 45.962-52.35 53.437z"/>
                                        <path fill="#00c7d4" d="M251.869 1.03L129.305 126.67a2.26 2.26 0 0 0-.274 2.895c7.457 10.406 21.202 12.21 26.303 19.203c15.098 20.712 18.933 32.435 25.417 31.709c.453-.065.87-.285 1.178-.622l44.276-45.389C251.678 108.376 255.339 50.868 256 2.71a2.405 2.405 0 0 0-4.131-1.678"/>
                                        <path fill="#11e1ee" d="M226.226 134.466c-13.915 14.263-41.44 20.873-98.204-6.462c19.859 44.437 45.963 60.009 53.437 52.351z"/>
                                        <path fill="#e43921" d="m1.018 4.131l125.638 122.565c.772.78 1.992.896 2.896.273c10.406-7.457 12.21-21.207 19.203-26.303c20.712-15.098 32.435-18.929 31.709-25.417a2 2 0 0 0-.622-1.178l-45.389-44.276C108.363 4.322 50.855.661 2.696 0a2.4 2.4 0 0 0-1.678 4.131"/>
                                        <path fill="#ff7557" d="M134.475 29.8c14.263 13.915 20.872 41.44-6.462 98.204c44.437-19.859 60.008-45.967 52.35-53.437z"/>
                                        <path fill="#0cb6ff" d="M29.795 121.543C43.71 107.28 71.235 100.67 128 128.004c-19.86-44.436-45.963-60.008-53.438-52.35z"/>
                                        <circle cx="128.017" cy="127.983" r="5.479" fill="#4a4848"/>
                                    </svg>
                                    AIRFLOW - Pipelines ELT
                                </a> -->
                            <?php endif; ?>
                            <!-- ?php if (isset($userHasBucketsAccess) && $userHasBucketsAccess): ?>
                                <a class="dropdown-item" href="http://localhost:9001" target="_blank" rel="noopener noreferrer">Buckets S3</a-->
                            <!-- ?php endif; ?-->
                            <!-- 
                            <?php if (!$isVisitor && $perfilUsuario !== ''): ?>
                                <a class="dropdown-item" href="<?= base_url('code-editor') ?>">💻 SQL Editor + Customizações Python</a>
                            <?php endif; ?>
                            --> 
                        </div>
                    <?php endif; ?>
                </li>

                <!-- Dropdown -->
                <?php
                    // Verifica se o perfil do usuário está logado e se ele NÃO é "Visitante"
                    if (!$isVisitor && $perfilUsuario !== ''): 
                ?>
                    <!--
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle px-2 px-lg-2" data-bs-toggle="dropdown">CRIAR</a>
                        <div class="dropdown-menu">
                            <?php echo anchor("listPasta", "Pastas", ['class' => 'dropdown-item']) ?>
                            <a href="<?= base_url('listConfig') ?>" class="dropdown-item">Pipelines</a>
                        </div>
                    </div>
                    -->
                    
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle px-2 px-lg-2" data-bs-toggle="dropdown">FISCALIZAÇÃO</a>
                        <div class="dropdown-menu">
                            <h6 class="dropdown-header text-primary fw-bold">CADASTROS</h6>
                            <a href="<?= base_url('listTipoDocumento') ?>" class="dropdown-item">Tipos de Documento</a>
                            <a href="<?= base_url('listStatus') ?>" class="dropdown-item">Status</a>
                            <a href="<?= base_url('listStatusRecebimento') ?>" class="dropdown-item">Status Recebimento</a>
                            <a href="<?= base_url('listItemOs') ?>" class="dropdown-item">Itens OS</a>
                            <a href="<?= base_url('listAtividadeMacro') ?>" class="dropdown-item">Atividades Macro</a>
                            <a href="<?= base_url('listAreaAtuacao') ?>" class="dropdown-item">Áreas de Atuação</a>
                            <a href="<?= base_url('listCatalogoServicos') ?>" class="dropdown-item">Catálogo de Serviços</a>
                            <a href="<?= base_url('listContrato') ?>" class="dropdown-item">Contratos</a>
                            <a href="<?= base_url('listItemContrato') ?>" class="dropdown-item">Itens de Contrato</a>
                            <a href="<?= base_url('listReajusteItemContrato') ?>" class="dropdown-item">Reajustes de Contrato</a>
                            <a href="<?= base_url('listAvaliacaoQualidadeSla') ?>" class="dropdown-item">Avaliação SLA</a>
                            
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header text-primary fw-bold">EXECUÇÃO</h6>
                            <a href="<?= base_url('listServico') ?>" class="dropdown-item">Serviços</a>
                            <a href="<?= base_url('listOrdemServico') ?>" class="dropdown-item">Ordens de Serviço (OS)</a>
                            <a href="<?= base_url('listDocumentoRecebimento') ?>" class="dropdown-item">Docs Recebimento</a>
                        </div>
                    </div>

                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle px-2 px-lg-2" data-bs-toggle="dropdown">GESTÃO ÁGIL</a>
                        <div class="dropdown-menu">
                            <a href="<?= base_url('agile/dashboard') ?>" class="dropdown-item">Painel Ágil</a>
                            <a href="<?= base_url('agile/demandas') ?>" class="dropdown-item">Demandas</a>
                            <a href="<?= base_url('agile/sistemas') ?>" class="dropdown-item">Sistemas</a>
                            <a href="<?= base_url('docs/index.html') ?>" class="dropdown-item" target="_blank">📖 Manual do Usuário</a>
                        </div>
                    </div>

                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle px-2 px-lg-2" data-bs-toggle="dropdown">RELATÓRIOS</a>
                        <div class="dropdown-menu">
                            <a href="#" class="dropdown-item">Em breve...</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php echo anchor("politica","Política Privacidade", ['class' => 'nav-link px-2 px-lg-2'])  ?>

                <?php echo anchor("tdu","Termos de uso", ['class' => 'nav-link px-2 px-lg-2'])  ?>
                
                <?php echo anchor("contactUs","Entre em contato", ['class' => 'nav-link px-2 px-lg-2 text-nowrap'])  ?>

                <?php if (!isset($_SESSION['nome_usuario_logado']) || empty($_SESSION['nome_usuario_logado']) || $isVisitor): ?>
                    <?php echo anchor("sigInUsuario", "Registre-se", ['class' => 'nav-link px-2 px-lg-2']) ?>
                <?php endif; ?>

                
            </div>

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

