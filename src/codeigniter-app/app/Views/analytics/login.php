<?php
if (!defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white text-center py-4 rounded-top-4">
                    <h2 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow"></i> Metabase Analytics</h2>
                    <p class="mb-0 mt-2 opacity-75">Acesso Seguro ao seu Data Warehouse</p>
                </div>
                <div class="card-body p-5 bg-light">
                    <?php if (isset($noOlapWarning) && $noOlapWarning): ?>
                        <!-- AVISO DE SCHEMA OLAP AUSENTE -->
                        <div class="alert alert-warning border-0 rounded-3 shadow-sm d-flex align-items-start mb-4">
                            <i class="bi bi-exclamation-triangle-fill fs-4 text-warning me-3 mt-1"></i>
                            <div>
                                <h6 class="alert-heading fw-bold mb-1">Aviso: Data Warehouse não inicializado</h6>
                                <p class="mb-0 text-muted" style="font-size: 0.9rem;">
                                    Você ainda não executou o processo do <strong>dbt run</strong> para gerar seus dados analíticos pessoais. 
                                    Suas tabelas de análise individuais não estarão visíveis no Metabase.
                                </p>
                            </div>
                        </div>

                        <p class="text-muted text-center mb-4">
                            No entanto, você pode prosseguir normalmente para o Metabase para visualizar os dashboards e bases de dados que foram compartilhados com você utilizando suas credenciais de acesso existentes.
                        </p>
                    <?php else: ?>
                        <p class="text-muted text-center mb-4">
                            Por motivos de conformidade da versão Open Source, o acesso ao Metabase requer login manual. Copie suas credenciais exclusivas abaixo para entrar no painel:
                        </p>

                        <!-- EMAIL FIELD -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary">E-mail de Acesso</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope-fill text-primary"></i></span>
                                <input type="text" class="form-control bg-white border-start-0" id="metabaseEmail" value="<?= esc($email) ?>" readonly>
                                <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('metabaseEmail', 'btnCopyEmail')">
                                    <span id="btnCopyEmail"><i class="bi bi-clipboard"></i> Copiar</span>
                                </button>
                            </div>
                        </div>

                        <!-- PASSWORD FIELD -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary">Senha Determinística</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock-fill text-primary"></i></span>
                                <input type="password" class="form-control bg-white border-start-0 border-end-0" id="metabasePassword" value="<?= esc($password) ?>" readonly>
                                <button class="btn btn-outline-secondary border-start-0 border-end-0 bg-white text-muted" type="button" onclick="togglePasswordVisibility()">
                                    <i id="eyeIcon" class="bi bi-eye-fill"></i>
                                </button>
                                <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('metabasePassword', 'btnCopyPassword')">
                                    <span id="btnCopyPassword"><i class="bi bi-clipboard"></i> Copiar</span>
                                </button>
                            </div>
                        </div>

                        <!-- INFORMATION BOX -->
                        <div class="alert alert-info border-0 rounded-3 shadow-sm d-flex align-items-start mb-4">
                            <i class="bi bi-info-circle-fill fs-4 text-primary me-3 mt-1"></i>
                            <div>
                                <h6 class="alert-heading fw-bold mb-1">Como funciona?</h6>
                                <p class="mb-0 text-muted" style="font-size: 0.9rem;">
                                    Seu usuário e permissões no Metabase estão isolados no banco de dados analítico PostgreSQL (<code>postgres-bi</code>). A senha é gerada de forma criptográfica e sincronizada com a plataforma.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ACTION BUTTON -->
                    <div class="d-grid mt-4">
                        <a href="<?= esc($siteUrl) ?>" target="_blank" class="btn btn-primary btn-lg py-3 rounded-3 shadow fw-bold transition-all hover-scale">
                            <i class="bi bi-box-arrow-up-right me-2"></i> Abrir o Metabase
                        </a>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="<?= route_to('dashboard') ?>" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(inputId, buttonId) {
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);
    
    input.select();
    input.setSelectionRange(0, 99999); // Para dispositivos móveis
    navigator.clipboard.writeText(input.value).then(() => {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check-lg"></i> Copiado!';
        button.classList.remove('btn-outline-primary');
        button.classList.add('btn-success', 'text-white');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success', 'text-white');
            button.classList.add('btn-outline-primary');
        }, 2000);
    });
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('metabasePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('bi-eye-fill');
        eyeIcon.classList.add('bi-eye-slash-fill');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('bi-eye-slash-fill');
        eyeIcon.classList.add('bi-eye-fill');
    }
}
</script>

<style>
.hover-scale {
    transition: all 0.2s ease-in-out;
}
.hover-scale:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(25, 118, 210, 0.2) !important;
}
</style>

<?php require VIEWPATH . '/footer.php'; ?>
