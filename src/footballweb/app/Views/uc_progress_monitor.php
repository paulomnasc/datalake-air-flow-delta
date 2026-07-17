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
        <div style="flex: 1; padding: 24px; overflow: auto; display: flex; align-items: center; justify-content: center;">
            <div style="max-width: 800px; width: 100%; text-align: center;">
                <h1 style="font-size: 32px; font-weight: bold; margin: 0 0 16px 0; color: #818cf8;">Monitor de Progresso UC</h1>
                <p style="color: #94a3b8; font-size: 18px; margin: 0;">Acompanhe seu aprendizado com vídeos e tarefas práticas</p>
                <div style="margin-top: 32px; padding: 24px; background: #0f172a; border: 1px solid #1e293b; border-radius: 12px;">
                    <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin: 0;">👈 Assista ao vídeo e complete as tarefas na barra lateral para ganhar XP e avançar no módulo.</p>
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



</script>

<?php require VIEWPATH.'/footer.php'; ?>
