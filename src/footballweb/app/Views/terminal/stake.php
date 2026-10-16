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
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .terminal-actions {
        display: flex;
        align-items: center;
        gap: 8px;
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
        <!-- Barra de Controle do Terminal Remoto -->
        <div class="terminal-toolbar">
            <div class="terminal-title">
                <i class="bi bi-shield-lock-fill text-primary" style="font-size: 1.2rem;"></i>
                <span>Navegador Remoto Isolado (Stake.com)</span>
                <span class="terminal-badge-secure">
                    <i class="bi bi-geo-alt-fill"></i> IP do Servidor (Hetzner)
                </span>
                <span class="terminal-badge-secure" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-color: rgba(59, 130, 246, 0.3);">
                    <i class="bi bi-clock-history"></i> Fuso: America/Sao_Paulo
                </span>
            </div>

            <div class="terminal-actions">
                <a href="<?= site_url('football-trends') ?>" class="terminal-btn">
                    <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
                </a>
                <button type="button" class="terminal-btn" onclick="reloadTerminalFrame()" title="Recarregar tela do stream">
                    <i class="bi bi-arrow-clockwise"></i> Recarregar
                </button>
                <button type="button" class="terminal-btn" onclick="toggleTerminalFullScreen()" title="Alternar modo tela cheia">
                    <i class="bi bi-arrows-fullscreen"></i> Tela Cheia
                </button>
                <a href="<?= esc($browserUrl) ?>" target="_blank" rel="noopener noreferrer" class="terminal-btn terminal-btn-primary" title="Abrir stream em aba dedicada">
                    <i class="bi bi-box-arrow-up-right"></i> Aba Externa
                </a>
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
        </div>
    </div>
</div>

<script>
    function reloadTerminalFrame() {
        const frame = document.getElementById('remoteBrowserFrame');
        if (frame) {
            frame.src = frame.src;
        }
    }

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
</script>

<?php
require VIEWPATH . '/footer.php';
?>
