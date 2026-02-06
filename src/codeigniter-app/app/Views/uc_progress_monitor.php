<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<style>
    .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; }
    .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 12px; }
    .task-active { background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.5); box-shadow: 0 0 15px rgba(99, 102, 241, 0.1); }
    .task-completed { opacity: 0.6; }
    .task-pending { opacity: 0.4; }
    .progress-bar-fill { transition: width 0.7s ease; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }

    body { margin: 0; background: #000814; }
    #content, #main-content { margin: 0; padding: 0 !important; }
    #head-bar, nav.navbar, .sidebyside-container { display: none; }
        
        /* Terminal Styles */
        #terminal { background: #000; color: #22c55e; padding: 12px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 12px; overflow-y: auto; max-height: 100%; line-height: 1.5; }
        .terminal-output { white-space: pre-wrap; word-wrap: break-word; }
        .terminal-input { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
        .terminal-prompt { color: #0ea5e9; font-weight: bold; }
        .terminal-cmd { color: #f1f5f9; flex: 1; background: transparent; border: none; outline: none; font-family: 'Courier New', monospace; font-size: 12px; color: #22c55e; }
        .terminal-cursor { display: inline-block; width: 8px; height: 14px; background: #22c55e; margin-left: 2px; animation: blink 1s infinite; }
        @keyframes blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
        #video-player { position: absolute; inset: 0; }
        #video-player iframe { width: 100%; height: 100%; }
</style>

<main style="display: flex; height: calc(100vh - 60px); gap: 0; background: #000814; color: #e2e8f0;">
    <div style="flex: 1; display: flex; flex-direction: column;">
        <!-- Header/Toolbar -->
        <header style="background: linear-gradient(to right, #0f172a, #1e293b); padding: 16px 24px; border-bottom: 1px solid #334155; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="background: #4f46e5; padding: 6px; border-radius: 8px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                    </svg>
                </div>
                <span style="font-weight: bold; letter-spacing: -0.025em;">DATAFLOW <span style="color: #818cf8; font-size: 12px;">PRO</span></span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 24px;">
                <div style="display: flex; align-items: center; gap: 8px; padding: 4px 12px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 9999px;">
                    <svg width="14" height="14" fill="#f59e0b" stroke="none" viewBox="0 0 24 24">
                        <path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z" />
                    </svg>
                    <span id="xp-display" style="font-size: 14px; font-weight: bold; color: #f59e0b;">450 XP</span>
                </div>
                <button onclick="toggleSidebar()" style="padding: 8px; background: transparent; border: none; cursor: pointer; color: #94a3b8; border-radius: 8px;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Workspace Central -->
        <div style="flex: 1; padding: 24px; overflow: auto;">
            <div style="max-width: 1024px; margin: 0 auto;">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
                    <div>
                        <h1 style="font-size: 24px; font-weight: bold; margin: 0;">Workspace: Pipeline Alpha</h1>
                        <p style="color: #94a3b8; margin: 4px 0 0 0;">Ambiente de laboratório para engenharia de dados</p>
                    </div>
                    <div style="font-size: 12px; color: #64748b; background: #0f172a; padding: 4px 12px; border-radius: 4px; border: 1px solid #1e293b;">
                        STATUS: AGUARDANDO AÇÃO
                    </div>
                </div>

                <!-- Terminal/Console INTERATIVO - Google Cloud Shell -->
                <div style="background: #000; border-radius: 12px; border: 1px solid #1e293b; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); margin-top: 24px; display: flex; flex-direction: column;">
                    <div style="background: #0f172a; padding: 8px 16px; border-bottom: 1px solid #1e293b; display: flex; align-items: center; gap: 8px; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <svg width="14" height="14" fill="none" stroke="#64748b" stroke-width="2" viewBox="0 0 24 24">
                                <polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>
                            </svg>
                            <span style="font-size: 12px; font-family: monospace; color: #94a3b8;">gcloud shell — my-dataflow-project</span>
                        </div>
                        <button onclick="clearTerminal()" style="background: #334155; color: #94a3b8; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer;">Clear</button>
                    </div>
                    <div id="terminal" style="padding: 12px; font-family: 'Courier New', monospace; font-size: 12px; height: 280px; overflow-y: auto; color: #22c55e; flex: 1;">
                        <div style="color: #94a3b8;">Welcome to Google Cloud Shell! Type 'help' for commands.</div>
                        <div style="margin-top: 8px;"><span style="color: #0ea5e9; font-weight: bold;">user@cloudshell</span>:<span style="color: #f59e0b;">~/datalake-air-flow</span>$ </div>
                    </div>
                    <div style="padding: 8px 12px; background: #000; border-top: 1px solid #1e293b; display: flex; align-items: center; gap: 6px;">
                        <span style="color: #0ea5e9; font-family: monospace; font-size: 12px; font-weight: bold;">$</span>
                        <input id="terminal-input" type="text" placeholder="Type command and press Enter..." style="flex: 1; background: #000; border: none; outline: none; font-family: 'Courier New', monospace; font-size: 12px; color: #22c55e;" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BARRA LATERAL DE TREINAMENTO -->
    <aside id="uc-sidebar" style="width: 384px; background: #0f172a; border-left: 1px solid #1e293b; transition: all 0.3s; display: flex; flex-direction: column; position: relative;">
        <div style="padding: 24px; border-bottom: 1px solid #1e293b;">
            <div style="display: flex; align-items: center; gap: 8px; color: #818cf8; margin-bottom: 8px;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                <span style="font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em;">Guia de Aprendizado</span>
            </div>
            <h2 style="font-size: 18px; font-weight: bold; line-height: 1.2; margin: 0 0 16px 0;">Módulo 6: Criando um Data Lake do Zero</h2>
            
            <div style="margin-top: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                    <span style="color: #94a3b8;">Progresso do Módulo</span>
                    <span id="progress-display" style="color: #818cf8; font-weight: bold;">35%</span>
                </div>
                <div style="height: 8px; background: #1e293b; border-radius: 9999px; overflow: hidden;">
                    <div id="progress-bar" class="progress-bar-fill" style="height: 100%; background: #4f46e5; width: 35%;"></div>
                </div>
            </div>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 24px;">
            <!-- Player de Vídeo -->
            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000; border-radius: 8px; border: 1px solid #334155; margin-bottom: 32px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);">
                <div id="video-player"></div>
                <div id="video-overlay" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(30, 41, 59, 0.5); transition: all 0.2s;">
                    <svg width="32" height="32" fill="white" stroke="none" viewBox="0 0 24 24" style="opacity: 0.8;">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
                <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: #334155;">
                    <div id="video-progress-bar" style="height: 100%; background: #dc2626; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="video-timestamp" style="position: absolute; top: 8px; right: 8px; background: rgba(0, 0, 0, 0.6); padding: 4px 8px; border-radius: 4px; font-size: 10px; font-family: monospace;">
                    02:15
                </div>
            </div>

            <!-- Checklist de Tasks -->
            <div>
                <h3 style="font-size: 14px; font-weight: 600; color: #94a3b8; display: flex; align-items: center; gap: 8px; margin: 0 0 16px 0;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    TAREFAS DE EXECUÇÃO
                </h3>
                
                <div id="task-list" style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Tasks serão inseridas via JS -->
                </div>
            </div>

            <div id="completion-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 0 15px rgba(79, 70, 229, 0.2); margin-top: 32px; display: none;">
                <svg width="40" height="40" fill="none" stroke="#fbbf24" stroke-width="2" viewBox="0 0 24 24" style="margin: 0 auto 8px;">
                    <circle cx="12" cy="8" r="7"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/>
                </svg>
                <h3 style="font-weight: bold; font-size: 18px; line-height: 1.2; margin: 0 0 8px 0;">Módulo Concluído!</h3>
                <p style="font-size: 12px; opacity: 0.9; margin: 0 0 16px 0;">Você dominou a fundação do Data Lake.</p>
                <button style="width: 100%; background: white; color: #4f46e5; padding: 8px; border-radius: 8px; font-weight: bold; font-size: 14px; border: none; cursor: pointer;">
                    Emitir Certificado
                </button>
            </div>
        </div>

        <div style="padding: 16px; background: #020617; border-top: 1px solid #1e293b;">
            <button style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 12px; color: #64748b; background: transparent; border: none; cursor: pointer; padding: 8px;">
                <span>Dúvidas com o Wallace?</span>
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </button>
        </div>
    </aside>
</main>

<script>
// Estado da aplicação
const appState = {
    sidebarOpen: true,
    activeTask: 0,
    progress: 35,
    points: 450,
    isCompleted: false,
    completedTaskIds: [],
    videoId: '6073YAGEq08',
    userContext: {
        userId: '<?= session()->get('user_id') ?? 'guest' ?>',
        courseId: 'curso-001',
        moduleId: 'mod-006'
    }
};

const STORAGE_KEY = 'uc-progress-monitor-state';
const API_URL = '<?= base_url('api/uc-progress') ?>';

const tasks = [
    { id: 0, title: "Configurar Fonte S3", description: "Conecte o bucket de staging para iniciar o Lake.", videoTimestamp: "02:15", points: 100 },
    { id: 1, title: "Validar Schema", description: "Execute o comando de validação no console ao lado.", videoTimestamp: "05:40", points: 150 },
    { id: 2, title: "Deploy da Camada Silver", description: "Finalize a transformação dos dados brutos.", videoTimestamp: "12:10", points: 200 }
];

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    loadState();
    renderTasks();
    updateUI();
    initYouTubePlayer();
});

function loadState() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            Object.assign(appState, parsed);
        } catch (e) {
            localStorage.removeItem(STORAGE_KEY);
        }
    }
}

function saveState() {
    const payload = {
        ...appState,
        completedTaskIds: tasks.filter((_, idx) => idx < appState.activeTask).map(t => t.id),
        timestamp: new Date().toISOString()
    };
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    
    // Sync com backend (debounced)
    if (window.syncTimeout) clearTimeout(window.syncTimeout);
    window.syncTimeout = setTimeout(() => {
        fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).catch(() => {});
    }, 500);
}

function toggleSidebar() {
    appState.sidebarOpen = !appState.sidebarOpen;
    document.getElementById('uc-sidebar').style.width = appState.sidebarOpen ? '384px' : '0';
}

function executeCurrentTask() {
    if (appState.activeTask >= tasks.length) return;
    
    const task = tasks[appState.activeTask];
    appState.points += task.points;
    
    // Simular output no terminal
    const terminalDynamic = document.getElementById('terminal-dynamic');
    terminalDynamic.innerHTML = `<p style="color: #22c55e; margin: 4px 0;">[SUCCESS] Tarefa "${task.title}" concluída!</p>`;
    
    appState.activeTask++;
    appState.progress = Math.round((appState.activeTask / tasks.length) * 100);
    
    if (appState.activeTask >= tasks.length) {
        appState.isCompleted = true;
        appState.progress = 100;
    }
    
    updateUI();
    saveState();
}

function updateUI() {
    document.getElementById('xp-display').textContent = appState.points + ' XP';
    document.getElementById('progress-display').textContent = appState.progress + '%';
    document.getElementById('progress-bar').style.width = appState.progress + '%';
    document.getElementById('task-number').textContent = appState.activeTask + 1;
    
    if (appState.isCompleted) {
        document.getElementById('completion-card').style.display = 'block';
        document.getElementById('execute-task-btn').style.display = 'none';
    }
    
    renderTasks();
}

function renderTasks() {
    const container = document.getElementById('task-list');
    container.innerHTML = tasks.map((task, idx) => {
        const isCurrent = idx === appState.activeTask;
        const isCompleted = idx < appState.activeTask;
        const isPending = idx > appState.activeTask;
        
        let className = '';
        if (isCurrent) className = 'task-active';
        else if (isCompleted) className = 'task-completed';
        else className = 'task-pending';
        
        return `
            <div class="${className}" style="padding: 16px; border-radius: 12px; border: 1px solid #1e293b; transition: all 0.3s;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <span style="font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 4px; ${isCurrent ? 'background: #4f46e5; color: white;' : 'background: #334155; color: #cbd5e1;'}">
                        PASSO ${idx + 1}
                    </span>
                    ${isCompleted ? '<svg width="16" height="16" fill="none" stroke="#22c55e" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' : ''}
                </div>
                <h4 style="font-weight: bold; font-size: 14px; color: ${isCurrent ? 'white' : '#94a3b8'}; margin: 0 0 4px 0;">
                    ${task.title}
                </h4>
                <p style="font-size: 12px; color: #64748b; margin: 0;">${task.description}</p>
                ${isCurrent ? `
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(79, 70, 229, 0.2); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 10px; color: #818cf8; font-family: monospace; font-style: italic;">Assista até ${task.videoTimestamp}</span>
                        <span style="font-size: 10px; font-weight: bold; color: #f59e0b;">+${task.points} XP</span>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

// YouTube Player
var player;
function initYouTubePlayer() {
    const tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    const firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
}

window.onYouTubeIframeAPIReady = function() {
    player = new YT.Player('video-player', {
        height: '100%',
        width: '100%',
        videoId: appState.videoId,
        playerVars: { 'rel': 0, 'modestbranding': 1 },
        events: {
            'onStateChange': onPlayerStateChange
        }
    });
};

function onPlayerStateChange(event) {
    if (event.data == YT.PlayerState.PLAYING) {
        document.getElementById('video-overlay').style.display = 'none';
    }
}

// ===================== TERMINAL (Google Cloud Shell) =====================
const terminalCommands = {
    'help': 'Available commands: git pull, git status, npm install, docker-compose up, ls, cat, clear',
    'git pull': 'From https://github.com/seu-user/datalake-air-flow\n   abc1234..def5678  main       -> origin/main\nAlready up to date.',
    'git status': 'On branch main\nYour branch is up to date with \'origin/main\'.\nnothing to commit, working tree clean',
    'npm install': 'npm WARN deprecated some-package@1.0.0\nadded 256 packages from 423 contributors in 12.34s',
    'docker-compose up': 'Creating network "datalake-air-flow_airflow_net" with driver "bridge"\nCreating mysql-dev        ... done\nCreating postgres-dev     ... done\nCreating codeigniter-app  ... done\nAttaching to containers...',
    'ls': 'app/              backup.sql         docker-compose.yml  docker-compose.override.yml\nassets/           docs/              Dockerfile         .gitignore\nsrc/              DEPLOY_GUIDE.md    .env                vendor/',
    'cat .env': 'CI_ENVIRONMENT=development\nAPP_DEBUG=true\nAPP_URL=http://localhost:8088\nDB_HOST=mysql\nDB_USER=root\nDB_PASS=root\nDB_NAME=lista_revisao2',
    'pwd': '/home/user/datalake-air-flow',
    'php spark migrate': '[2026-02-06 14:30:00] Running Migrations:\n  - Migration_2026_02_06_000001_CreateVideoProgressTable\n  - Migration_2026_02_06_000002_CreateUcProgressTable\n✓ All migrations completed successfully'
};

function processTerminalCommand(cmd) {
    const terminal = document.getElementById('terminal');
    const input = document.getElementById('terminal-input');
    
    const commandLower = cmd.toLowerCase().trim();
    let output = terminalCommands[commandLower] || `Command not found: ${cmd}. Type 'help' for available commands.`;
    
    // Adicionar comando
    const cmdLine = document.createElement('div');
    cmdLine.style.marginTop = '8px';
    cmdLine.innerHTML = `<span style="color: #0ea5e9; font-weight: bold;">user@cloudshell</span>:<span style="color: #f59e0b;">~/datalake-air-flow</span>$ <span style="color: #22c55e;">${cmd}</span>`;
    terminal.appendChild(cmdLine);
    
    // Simular delay de execução
    setTimeout(() => {
        const outputLine = document.createElement('div');
        outputLine.style.color = '#f1f5f9';
        outputLine.style.marginTop = '4px';
        outputLine.style.whiteSpace = 'pre-wrap';
        outputLine.textContent = output;
        terminal.appendChild(outputLine);
        
        // Adicionar novo prompt
        const newPrompt = document.createElement('div');
        newPrompt.style.marginTop = '8px';
        newPrompt.innerHTML = `<span style="color: #0ea5e9; font-weight: bold;">user@cloudshell</span>:<span style="color: #f59e0b;">~/datalake-air-flow</span>$ `;
        terminal.appendChild(newPrompt);
        
        // Scroll para o fundo
        terminal.scrollTop = terminal.scrollHeight;
        input.value = '';
        input.focus();
    }, 500);
}

function clearTerminal() {
    const terminal = document.getElementById('terminal');
    terminal.innerHTML = '<div style="color: #94a3b8;">Terminal cleared.</div><div style="margin-top: 8px;"><span style="color: #0ea5e9; font-weight: bold;">user@cloudshell</span>:<span style="color: #f59e0b;">~/datalake-air-flow</span>$ </div>';
}

// Capturar Enter no terminal
document.addEventListener('DOMContentLoaded', function() {
    const terminalInput = document.getElementById('terminal-input');
    if (terminalInput) {
        terminalInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const cmd = this.value.trim();
                if (cmd) {
                    processTerminalCommand(cmd);
                }
            }
        });
    }
});

</script>

<?php require VIEWPATH.'/footer.php'; ?>
