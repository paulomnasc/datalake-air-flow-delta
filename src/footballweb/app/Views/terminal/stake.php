<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<style>
    .terminal-container {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 120px);
        min-height: 680px;
        background: #0f172a;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
        margin-bottom: 20px;
        position: relative;
    }
    .terminal-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 16px;
        background: #1e293b;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        flex-wrap: wrap;
        gap: 10px;
    }
    .terminal-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        font-size: 0.95rem;
        color: #f8fafc;
    }
    .terminal-badge-secure {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.3);
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .terminal-badge-standby {
        background: rgba(245, 158, 11, 0.15) !important;
        color: #fbbf24 !important;
        border-color: rgba(245, 158, 11, 0.35) !important;
    }
    .terminal-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .terminal-btn {
        background: #334155;
        color: #e2e8f0;
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.82rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .terminal-btn:hover {
        background: #475569;
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.2);
    }
    .terminal-btn-success {
        background: #059669;
        color: #ffffff;
        border-color: #10b981;
    }
    .terminal-btn-success:hover {
        background: #047857;
        color: #ffffff;
    }
    .terminal-btn-danger {
        background: #dc2626;
        color: #ffffff;
        border-color: #ef4444;
    }
    .terminal-btn-danger:hover {
        background: #b91c1c;
        color: #ffffff;
    }
    .terminal-btn-primary {
        background: #2563eb;
        color: #ffffff;
        border-color: #3b82f6;
    }
    .terminal-btn-primary:hover {
        background: #1d4ed8;
        color: #ffffff;
    }
    .terminal-viewport {
        flex: 1;
        width: 100%;
        height: 100%;
        position: relative;
        background: #020617;
    }
    .terminal-iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }
    /* Overlay elegante de Standby / Segurança */
    .terminal-standby-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.88);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 20;
        padding: 20px;
        transition: opacity 0.3s ease;
    }
    .standby-card {
        background: #1e293b;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 16px;
        padding: 32px;
        max-width: 520px;
        width: 100%;
        text-align: center;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.6), 0 10px 10px -5px rgba(0, 0, 0, 0.4);
    }
    .standby-card h4 {
        color: #f8fafc;
        margin-top: 16px;
        margin-bottom: 8px;
        font-weight: 700;
    }
    .standby-card p {
        color: #94a3b8;
        font-size: 0.92rem;
        line-height: 1.5;
        margin-bottom: 24px;
    }
    .standby-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    /* Toast de feedback */
    .terminal-toast {
        position: absolute;
        top: 60px;
        right: 20px;
        z-index: 50;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        display: none;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5);
    }
    /* Barra de Navegação e Atalhos */
    .terminal-navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 16px;
        background: #0f172a;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        gap: 12px;
        flex-wrap: wrap;
    }
    .terminal-nav-controls {
        flex: 1;
        min-width: 280px;
        max-width: 540px;
    }
    .terminal-url-group {
        display: flex;
        align-items: center;
        background: #1e293b;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .terminal-url-group:focus-within {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    }
    .url-addon {
        padding: 6px 10px;
        color: #94a3b8;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
    }
    .terminal-url-input {
        flex: 1;
        background: transparent;
        border: none;
        color: #f8fafc;
        font-size: 0.85rem;
        padding: 6px 4px;
        outline: none;
        font-family: inherit;
    }
    .terminal-url-input::placeholder {
        color: #64748b;
    }
    .terminal-url-submit {
        background: #2563eb;
        color: #ffffff;
        border: none;
        padding: 6px 14px;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: background 0.2s ease;
    }
    .terminal-url-submit:hover {
        background: #1d4ed8;
    }
    .terminal-bookmarks {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .bookmarks-label {
        font-size: 0.78rem;
        color: #94a3b8;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .bookmark-chip {
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #cbd5e1;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.78rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .bookmark-chip:hover {
        background: #334155;
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.25);
        transform: translateY(-1px);
    }
    /* Modo Tela Cheia */
    .terminal-container:fullscreen {
        height: 100vh;
        border-radius: 0;
        border: none;
        margin: 0;
    }
    .terminal-container:-webkit-full-screen {
        height: 100vh;
        border-radius: 0;
        border: none;
        margin: 0;
    }
</style>

<div class="container-fluid px-3 pt-3">
    <div class="terminal-container" id="terminalStakeContainer">
        <!-- Toast de Status / Notificação -->
        <div id="terminalToast" class="terminal-toast bg-dark text-white border border-secondary">
            <span id="toastIcon"><i class="bi bi-info-circle"></i></span>
            <span id="toastMessage">Operação em andamento...</span>
        </div>

        <!-- Barra de Controle do Terminal Remoto -->
        <div class="terminal-toolbar">
            <div class="terminal-title">
                <i class="bi bi-shield-lock-fill text-primary" style="font-size: 1.25rem;"></i>
                <span>Terminal Remoto Seguro (Cloudbet / Stake)</span>

                <!-- Status Dinâmico -->
                <span id="browserStatusBadge" class="terminal-badge-secure">
                    <i class="bi bi-circle-fill" style="font-size: 0.55rem;"></i>
                    <span id="browserStatusText">Verificando...</span>
                </span>

                <span class="terminal-badge-secure" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
                    <i class="bi bi-geo-alt-fill"></i> IP Hetzner
                </span>
            </div>

            <div class="terminal-actions">
                <!-- Controle de Segurança: Iniciar / Encerrar -->
                <button type="button" id="btnStartBrowser" class="terminal-btn terminal-btn-success" onclick="startRemoteBrowser()" title="Iniciar navegador remoto">
                    <i class="bi bi-play-circle-fill"></i> Iniciar Navegador
                </button>
                <button type="button" id="btnStopBrowser" class="terminal-btn terminal-btn-danger" onclick="stopRemoteBrowser()" title="Encerrar sessão do navegador imediatamente por segurança">
                    <i class="bi bi-shield-slash-fill"></i> Encerrar (Segurança)
                </button>
                <button type="button" class="terminal-btn" onclick="restartRemoteBrowser()" title="Reiniciar processo do Chromium">
                    <i class="bi bi-arrow-repeat"></i> Reiniciar
                </button>

                <!-- Utilidades de Tela e Janela -->
                <button type="button" class="terminal-btn" onclick="reloadTerminalFrame()" title="Recarregar tela do stream">
                    <i class="bi bi-arrow-clockwise"></i> Recarregar Stream
                </button>
                <button type="button" class="terminal-btn" onclick="toggleTerminalFullScreen()" title="Alternar modo tela cheia">
                    <i class="bi bi-arrows-fullscreen"></i> Tela Cheia
                </button>
                <a href="<?= esc($browserUrl) ?>" target="_blank" rel="noopener noreferrer" class="terminal-btn terminal-btn-primary" title="Abrir stream em aba dedicada">
                    <i class="bi bi-box-arrow-up-right"></i> Aba Externa
                </a>
                <a href="<?= site_url('football-trends') ?>" class="terminal-btn">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Barra de Navegação de Endereços e Atalhos Rápidos -->
        <div class="terminal-navbar">
            <div class="terminal-nav-controls">
                <div class="terminal-url-group">
                    <span class="url-addon"><i class="bi bi-globe2"></i></span>
                    <input 
                        type="text" 
                        id="terminalUrlInput" 
                        class="terminal-url-input" 
                        placeholder="Digite a URL (ex: cloudbet.com)..." 
                        value="https://www.cloudbet.com/" 
                        onkeydown="if(event.key === 'Enter') navigateToUrl()"
                        autocomplete="off"
                        spellcheck="false">
                    <button type="button" class="terminal-url-submit" onclick="navigateToUrl()" title="Navegar para este endereço">
                        <i class="bi bi-arrow-right-short" style="font-size: 1.1rem;"></i> Ir
                    </button>
                </div>
            </div>

            <div class="terminal-bookmarks">
                <span class="bookmarks-label"><i class="bi bi-bookmark-star-fill text-warning"></i> Atalhos:</span>
                <button type="button" class="bookmark-chip" onclick="quickNavigate('https://www.cloudbet.com/')" title="Navegar para Cloudbet">
                    <i class="bi bi-lightning-charge-fill text-primary"></i> Cloudbet
                </button>
                <button type="button" class="bookmark-chip" onclick="quickNavigate('https://stake.com/')" title="Navegar para Stake.com">
                    <i class="bi bi-suit-spade-fill text-danger"></i> Stake
                </button>
                <button type="button" class="bookmark-chip" onclick="quickNavigate('https://whoer.net/')" title="Testar IP e anonimato no Whoer">
                    <i class="bi bi-shield-check text-info"></i> Checar IP (Whoer)
                </button>
                <button type="button" class="bookmark-chip" onclick="quickNavigate('https://browserleaks.com/ip')" title="Verificar vazamentos de IP/WebRTC">
                    <i class="bi bi-fingerprint text-success"></i> BrowserLeaks
                </button>
            </div>
        </div>

        <!-- Área de Exibição do Stream Chromium -->
        <div class="terminal-viewport">
            <iframe 
                id="remoteBrowserFrame"
                class="terminal-iframe" 
                src="<?= esc($browserUrl) ?>" 
                allow="clipboard-read; clipboard-write; fullscreen"
                loading="lazy">
            </iframe>

            <!-- Tela de Standby / Bloqueio de Segurança -->
            <div id="standbyOverlay" class="terminal-standby-overlay" style="display: none;">
                <div class="standby-card">
                    <div style="width: 64px; height: 64px; margin: 0 auto; background: rgba(245, 158, 11, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <i class="bi bi-shield-check" style="font-size: 2rem; color: #fbbf24;"></i>
                    </div>
                    <h4>Navegador em Standby por Segurança</h4>
                    <p>O navegador foi encerrado para proteger suas credenciais e sessões de apostas. Quando quiser operar novamente, basta clicar no botão abaixo ou escolher um atalho na barra superior.</p>
                    <div class="standby-actions">
                        <button type="button" class="terminal-btn terminal-btn-success" style="padding: 10px 20px; font-size: 0.95rem; font-weight: 600;" onclick="startRemoteBrowser()">
                            <i class="bi bi-play-circle-fill"></i> Iniciar Navegador Seguro
                        </button>
                    </div>
                    <small class="d-block text-muted mt-3" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle"></i> O IP de navegação é estritamente originado pelo servidor remoto Hetzner.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let isCheckingStatus = false;

    // Exibe notificação temporária no topo do terminal
    function showToast(message, type = 'info') {
        const toast = document.getElementById('terminalToast');
        const msgEl = document.getElementById('toastMessage');
        const iconEl = document.getElementById('toastIcon');
        if (!toast || !msgEl) return;

        msgEl.textContent = message;
        if (type === 'success') {
            toast.className = 'terminal-toast bg-success text-white border border-light';
            iconEl.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
        } else if (type === 'danger') {
            toast.className = 'terminal-toast bg-danger text-white border border-light';
            iconEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';
        } else {
            toast.className = 'terminal-toast bg-dark text-white border border-secondary';
            iconEl.innerHTML = '<i class="bi bi-info-circle"></i>';
        }
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 4000);
    }

    // Consulta status do Chromium no backend
    function checkBrowserStatus() {
        if (isCheckingStatus) return;
        isCheckingStatus = true;

        fetch('/terminal/stake/status')
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('browserStatusBadge');
                const text = document.getElementById('browserStatusText');
                const overlay = document.getElementById('standbyOverlay');
                const btnStart = document.getElementById('btnStartBrowser');
                const btnStop = document.getElementById('btnStopBrowser');

                if (data.isRunning) {
                    badge.className = 'terminal-badge-secure';
                    text.textContent = 'Navegador Ativo';
                    overlay.style.display = 'none';
                    if (btnStart) btnStart.style.display = 'none';
                    if (btnStop) btnStop.style.display = 'inline-flex';
                } else {
                    badge.className = 'terminal-badge-secure terminal-badge-standby';
                    text.textContent = 'Sessão Encerrada';
                    overlay.style.display = 'flex';
                    if (btnStart) btnStart.style.display = 'inline-flex';
                    if (btnStop) btnStop.style.display = 'none';
                }
            })
            .catch(err => {
                console.warn('Erro ao checar status do navegador remoto:', err);
            })
            .finally(() => {
                isCheckingStatus = false;
            });
    }

    // Inicia o navegador Chromium
    function startRemoteBrowser() {
        showToast('Iniciando navegador isolado...', 'info');
        
        // Oculta o overlay imediatamente para desimpedir a visão
        const overlay = document.getElementById('standbyOverlay');
        if (overlay) overlay.style.display = 'none';

        const badge = document.getElementById('browserStatusBadge');
        const text = document.getElementById('browserStatusText');
        if (badge) badge.className = 'terminal-badge-secure';
        if (text) text.textContent = 'Navegador Ativo';

        const btnStart = document.getElementById('btnStartBrowser');
        const btnStop = document.getElementById('btnStopBrowser');
        if (btnStart) btnStart.style.display = 'none';
        if (btnStop) btnStop.style.display = 'inline-flex';

        const urlInput = document.getElementById('terminalUrlInput');
        const formData = new FormData();
        if (urlInput && urlInput.value.trim()) {
            formData.append('url', urlInput.value.trim());
        }

        fetch('/terminal/stake/start', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showToast(data.message || 'Navegador iniciado!', 'success');
                setTimeout(() => {
                    checkBrowserStatus();
                    reloadTerminalFrame();
                }, 1000);
            })
            .catch(err => {
                showToast('Falha ao iniciar navegador: ' + err.message, 'danger');
            });
    }

    // Encerra o navegador Chromium por segurança
    function stopRemoteBrowser() {
        if (!confirm('Deseja realmente encerrar a sessão do navegador agora por segurança?')) {
            return;
        }
        showToast('Encerrando sessão por segurança...', 'info');

        const overlay = document.getElementById('standbyOverlay');
        if (overlay) overlay.style.display = 'flex';

        const badge = document.getElementById('browserStatusBadge');
        const text = document.getElementById('browserStatusText');
        if (badge) badge.className = 'terminal-badge-secure terminal-badge-standby';
        if (text) text.textContent = 'Sessão Encerrada';

        const btnStart = document.getElementById('btnStartBrowser');
        const btnStop = document.getElementById('btnStopBrowser');
        if (btnStart) btnStart.style.display = 'inline-flex';
        if (btnStop) btnStop.style.display = 'none';

        fetch('/terminal/stake/stop', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                showToast(data.message || 'Navegador finalizado com segurança.', 'success');
                setTimeout(() => {
                    checkBrowserStatus();
                }, 400);
            })
            .catch(err => {
                showToast('Falha ao encerrar: ' + err.message, 'danger');
            });
    }

    // Reinicia o navegador
    function restartRemoteBrowser() {
        showToast('Reiniciando navegador...', 'info');

        const urlInput = document.getElementById('terminalUrlInput');
        const formData = new FormData();
        if (urlInput && urlInput.value.trim()) {
            formData.append('url', urlInput.value.trim());
        }

        fetch('/terminal/stake/restart', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showToast(data.message || 'Navegador reiniciado!', 'success');
                setTimeout(() => {
                    checkBrowserStatus();
                    reloadTerminalFrame();
                }, 1000);
            })
            .catch(err => {
                showToast('Falha ao reiniciar: ' + err.message, 'danger');
            });
    }

    // Recarrega o frame de streaming
    function reloadTerminalFrame() {
        const frame = document.getElementById('remoteBrowserFrame');
        if (frame) {
            frame.src = frame.src;
        }
    }

    // Alterna tela cheia
    function toggleTerminalFullScreen() {
        const elem = document.getElementById('terminalStakeContainer');
        if (!document.fullscreenElement) {
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    // Navega para a URL informada na barra de endereços
    function navigateToUrl() {
        const input = document.getElementById('terminalUrlInput');
        if (!input) return;
        const url = input.value.trim();
        if (!url) {
            showToast('Informe uma URL para navegar', 'danger');
            return;
        }

        showToast('Navegando para ' + url + '...', 'info');

        const formData = new FormData();
        formData.append('url', url);

        fetch('/terminal/stake/navigate', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Página solicitada com sucesso!', 'success');
                if (data.url) input.value = data.url;

                // Garante exibição do stream e oculta overlay
                const overlay = document.getElementById('standbyOverlay');
                if (overlay) overlay.style.display = 'none';

                const badge = document.getElementById('browserStatusBadge');
                const text = document.getElementById('browserStatusText');
                if (badge) badge.className = 'terminal-badge-secure';
                if (text) text.textContent = 'Navegador Ativo';

                const btnStart = document.getElementById('btnStartBrowser');
                const btnStop = document.getElementById('btnStopBrowser');
                if (btnStart) btnStart.style.display = 'none';
                if (btnStop) btnStop.style.display = 'inline-flex';

                setTimeout(() => {
                    checkBrowserStatus();
                }, 1000);
            } else {
                showToast('Falha na navegação: ' + (data.message || 'Erro desconhecido'), 'danger');
            }
        })
        .catch(err => {
            showToast('Erro ao comunicar com o navegador: ' + err.message, 'danger');
        });
    }

    // Atalho rápido de navegação
    function quickNavigate(targetUrl) {
        const input = document.getElementById('terminalUrlInput');
        if (input) input.value = targetUrl;
        navigateToUrl();
    }

    // Inicialização ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        checkBrowserStatus();
        // Monitora o status a cada 12 segundos
        setInterval(checkBrowserStatus, 12000);
    });
</script>

<?php
require VIEWPATH . '/footer.php';
?>

