/**
 * Git File Manager - Gerenciador centralizado de Git para Editors
 * Reutilizável em múltiplos editores (Code Editor, Validation Rules Editor, etc)
 * 
 * Configuração necessária em cada página:
 * 1. gitConfigKey = 'gitConfig' ou 'validationGitConfig'
 * 2. userBucket = 'lab01' (definido pelo PHP)
 * 3. Elementos HTML necessários:
 *    - gitFileTree, gitConnected, gitNotConnected, repoInfo
 *    - githubUsername, githubToken, repoURL, gitStatus
 */

// ============================================
// VARIÁVEIS GLOBAIS (configuráveis)
// ============================================
let gitConfig = null;
let gitConfigKey = 'gitConfig'; // Sobrescrito em cada página
let pfs = null;
let git = null;
let userBucket = 'lab01'; // Sobrescrito pelo PHP


// ============================================
// INICIALIZAÇÃO DO FILESYSTEM E GIT
// ============================================
function initFS() {
    try {
        if (!window.LightningFS) {
            console.error('❌ LightningFS não carregado');
            return false;
        }
        pfs = new LightningFS('git-fs');
        console.log('✓ Filesystem inicializado (LightningFS)');
        return true;
    } catch (e) {
        console.error('❌ Erro ao inicializar filesystem:', e);
        return false;
    }
}

function isGitReady() {
    return window.git && window.LightningFS && pfs;
}

async function ensureGitReady() {
    console.log('Iniciando carregamento de Git...');
    
    // Tentar carregar LightningFS se ainda não estiver pronto
    if (!window.LightningFS) {
        console.log('Aguardando LightningFS...');
        return false;
    }
    
    console.log('✓ LightningFS carregado');
    window.LightningFS = LightningFS;
    
    if (!window.git) {
        console.log('Aguardando isomorphic-git...');
        return false;
    }
    
    console.log('✓ isomorphic-git carregado');
    window.git = git;
    
    if (!pfs) {
        if (!initFS()) return false;
    }
    
    // Criar HTTP client inline
    if (!window.git.http) {
        console.log('🔧 Criando HTTP client inline...');
        window.git.http = {
            async request(url, options = {}) {
                const response = await fetch(url, {
                    method: options.method || 'GET',
                    headers: options.headers || {},
                    body: options.body,
                });
                return {
                    url: response.url,
                    method: options.method || 'GET',
                    headers: response.headers,
                    body: [new Uint8Array(await response.arrayBuffer())],
                    statusCode: response.status,
                    statusMessage: response.statusText,
                };
            }
        };
        console.log('✓ HTTP client criado');
    }
    
    console.log('Verificando variáveis Git disponíveis...');
    console.log('window.git:', typeof window.git);
    console.log('window.LightningFS:', typeof window.LightningFS);
    
    // Inicializar Git
    console.log('✓ Filesystem inicializado (LightningFS)');
    git = window.git;
    
    try {
        const result = await git.init({
            fs: pfs,
            dir: '/',
            defaultBranch: 'main'
        });
        console.log('✓ Git inicializado com sucesso');
        return true;
    } catch (e) {
        console.error('❌ Erro ao inicializar Git:', e);
        return false;
    }
}


// ============================================
// RESTAURAR CONFIGURAÇÃO DO LOCALSTORAGE
// ============================================
function restoreGitFromStorage(trigger = 'unknown') {
    try {
        const stored = localStorage.getItem(gitConfigKey);
        console.log(`🔍 restoreGitFromStorage(${trigger}) ->`, stored ? 'EXISTE' : 'NULL');
        if (!stored) {
            console.warn(`⚠️ restoreGitFromStorage(${trigger}): ${gitConfigKey} não encontrado no localStorage`);
            return;
        }

        const config = JSON.parse(stored);
        if (!config || !config.owner) {
            console.warn(`⚠️ restoreGitFromStorage(${trigger}): config inválido`, config);
            return;
        }

        gitConfig = config;
        window.gitConfig = config;
        console.log(`✅ gitConfig restaurado (${trigger}):`, config);

        const gitFileTree = document.getElementById('gitFileTree');
        const gitConnected = document.getElementById('gitConnected');
        const gitNotConnected = document.getElementById('gitNotConnected');
        const repoInfo = document.getElementById('repoInfo');
        const gitUserInput = document.getElementById('githubUsername');
        const gitTokenInput = document.getElementById('githubToken');
        const gitRepoInput = document.getElementById('repoURL');

        if (gitNotConnected) gitNotConnected.style.display = 'none';
        if (gitConnected) gitConnected.style.display = 'block';
        if (repoInfo) repoInfo.innerHTML = `Conectado a <strong>${config.owner}/${config.repo}</strong>`;
        if (gitUserInput) gitUserInput.value = config.username || config.owner || '';
        if (gitTokenInput) gitTokenInput.value = config.token || '';
        if (gitRepoInput) gitRepoInput.value = `${config.owner}/${config.repo}`;

        // Carregar arquivos se árvore estiver vazia OU se trigger for SPA (switchSidebarTab)
        if (gitFileTree) {
            const isEmpty = !gitFileTree.children || gitFileTree.children.length === 0;
            const isSpaNavigation = trigger === 'switchSidebarTab';
            if (isEmpty || isSpaNavigation) {
                console.log(`📂 Carregando arquivos Git (restore via ${trigger}, isEmpty=${isEmpty}, isSPA=${isSpaNavigation})...`);
                loadGitFiles();
            }
        }
    } catch (e) {
        console.error(`❌ Erro em restoreGitFromStorage(${trigger}):`, e);
    }
}


// ============================================
// CONECTAR AO GITHUB
// ============================================
let connectAttempts = 0;
async function connectGitHub() {
    const status = document.getElementById('gitStatus');
    
    if (!isGitReady()) {
        connectAttempts++;
        if (connectAttempts > 10) {
            alert('❌ Erro ao carregar isomorphic-git. Recarregue a página.');
            connectAttempts = 0;
            return;
        }
        status.innerText = 'Aguardando carregamento... (' + connectAttempts + '/10)';
        setTimeout(connectGitHub, 800);
        return;
    }
    connectAttempts = 0;
    git = window.git;
    
    if (!pfs) {
        initFS();
        if (!pfs) {
            alert('❌ Erro ao inicializar filesystem');
            return;
        }
    }
    
    const token = document.getElementById('githubToken').value.trim();
    const repoURL = document.getElementById('repoURL').value.trim();
    const username = document.getElementById('githubUsername').value.trim();
    
    if (!repoURL.includes('/')) {
        alert('Informe o repo no formato user/repo');
        return;
    }
    
    const [owner, repo] = repoURL.split('/');
    gitConfig = { owner, repo, token, username, branch: 'main' };
    window.gitConfig = gitConfig;
    console.log('💾 Salvando gitConfig no localStorage:', gitConfig);
    localStorage.setItem(gitConfigKey, JSON.stringify(gitConfig));
    console.log('✅ Salvo com sucesso. Verificando:', localStorage.getItem(gitConfigKey));
    
    status.innerText = 'Clonando repositório...';
    
    try {
        // Ensure userBucket is valid
        let safeBucket = userBucket;
        if (!safeBucket || typeof safeBucket !== 'string' || safeBucket.trim() === '') {
            safeBucket = 'lab01'; // Fallback to default
            console.warn('⚠️ userBucket inválido, usando fallback:', safeBucket);
        }
        
        // Chamada server-side para clonar no MinIO
        const cloneResponse = await fetch('/api/git-clone', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userBucket: safeBucket,
                username: gitConfig.username || gitConfig.owner,
                token: gitConfig.token || undefined,
                owner: gitConfig.owner,
                repo: gitConfig.repo,
                branch: gitConfig.branch || 'main'
            })
        });

        if (!cloneResponse.ok) {
            let errorData = {};
            try {
                errorData = await cloneResponse.json();
            } catch (e) {
                errorData = { error: `HTTP ${cloneResponse.status}` };
            }
            
            // Build comprehensive error message
            let errMsg = errorData.message || errorData.error || 'Clone failed on server';
            const missingFields = errorData.missingFields ? '\nMissing: ' + errorData.missingFields.join(', ') : '';
            const debugInfo = errorData.debug ? '\n[DEBUG: ' + JSON.stringify(errorData.debug) + ']' : '';
            throw new Error(`Git clone error (${cloneResponse.status}): ${errMsg}${missingFields}${debugInfo}`);
        }

        const cloneResult = await cloneResponse.json();
        console.log('✅ Clone concluído:', cloneResult);
        
        status.innerText = 'Carregando arquivos...';
        console.log(`🌐 Buscando arquivos: /api/git-files?userBucket=${userBucket}&owner=${gitConfig.owner}&repo=${gitConfig.repo}`);
        
        const filesResponse = await fetch(`/api/git-files?userBucket=${encodeURIComponent(userBucket)}&owner=${gitConfig.owner}&repo=${gitConfig.repo}`);
        if (!filesResponse.ok) {
            const errorText = await filesResponse.text();
            console.error('❌ Erro ao carregar arquivos. Status:', filesResponse.status, 'Response:', errorText);
            throw new Error('Failed to load files: ' + errorText);
        }
        
        const filesResult = await filesResponse.json();
        console.log('✅ Arquivos carregados. Quantidade:', filesResult.files ? filesResult.files.length : 0);
        
        status.innerText = '✓ Clone concluído com sucesso';
        
        document.getElementById('gitNotConnected').style.display = 'none';
        document.getElementById('gitConnected').style.display = 'block';
        document.getElementById('repoInfo').innerHTML = `Conectado a <strong>${owner}/${repo}</strong>`;
        
        // Aumentar delay para garantir que UI está renderizada
        setTimeout(() => {
            console.log('🎨 Renderizando file tree após UI estar pronta');
            renderGitFileTree(filesResult.files || []);
        }, 300);
        
    } catch (e) {
        gitConfig = null;
        localStorage.removeItem(gitConfigKey);
        status.innerText = 'Erro: ' + e.message;
        alert('Erro ao clonar: ' + e.message);
        document.getElementById('gitNotConnected').style.display = 'block';
        document.getElementById('gitConnected').style.display = 'none';
    }
}

function disconnectGitHub() {
    gitConfig = null;
    localStorage.removeItem(gitConfigKey);
    document.getElementById('gitNotConnected').style.display = 'block';
    document.getElementById('gitConnected').style.display = 'none';
    document.getElementById('githubToken').value = '';
    document.getElementById('repoURL').value = '';
    document.getElementById('githubUsername').value = '';
    document.getElementById('gitFileTree').innerHTML = '';
}


// ============================================
// CARREGAR ARQUIVOS DO GIT
// ============================================
async function loadGitFiles() {
    console.log('📂 loadGitFiles() chamado');
    console.log('   gitConfig:', gitConfig);
    console.log('   userBucket:', userBucket);
    
    if (!gitConfig) {
        console.error('❌ Git não configurado');
        return;
    }
    
    try {
        const url = `/api/git-files?userBucket=${encodeURIComponent(userBucket)}&owner=${gitConfig.owner}&repo=${gitConfig.repo}`;
        console.log(`🌐 Fazendo fetch para: ${url}`);
        
        const response = await fetch(url);
        console.log('✅ Resposta recebida. Status:', response.status);
        
        if (!response.ok) {
            const errText = await response.text();
            console.error('❌ Erro na resposta:', response.status, errText);
            throw new Error(`HTTP ${response.status}: ${errText}`);
        }
        
        const result = await response.json();
        console.log('✅ JSON parseado. Arquivos:', result.files ? result.files.length : 0);
        renderGitFileTree(result.files || []);
    } catch (error) {
        console.error('❌ Erro em loadGitFiles:', error.message);
        console.error('   Stack:', error.stack);
    }
}


// ============================================
// CONSTRUIR ÁRVORE DE ARQUIVOS
// ============================================
function buildGitFileTree(files) {
    const root = { children: {}, isFile: false };
    files.forEach(file => {
        const normalizedPath = file.path.replace(/\\/g, '/');
        const parts = normalizedPath.split('/').filter(Boolean);

        // Ignorar arquivo .gitkeep, mas manter a pasta como nó de pasta
        let isGitkeepPlaceholder = false;
        if (parts.length > 0 && parts[parts.length - 1] === '.gitkeep') {
            parts.pop();
            if (parts.length === 0) return; // nada a criar
            isGitkeepPlaceholder = true;
        }

        let current = root;
        const accumulated = [];
        
        parts.forEach((part, index) => {
            accumulated.push(part);
            const pathSoFar = accumulated.join('/');
            const isLast = (index === parts.length - 1);
            const isFile = isGitkeepPlaceholder ? false : (isLast && !normalizedPath.endsWith('/'));
            
            if (!current.children[part]) {
                current.children[part] = {
                    name: part,
                    path: pathSoFar,
                    fullPath: pathSoFar,
                    isFile: isFile,
                    fileData: isFile ? file : null,
                    children: {},
                    expanded: index < 2
                };
            } else {
                if (!isLast) {
                    current.children[part].isFile = false;
                    current.children[part].fileData = null;
                }
            }
            current = current.children[part];
        });
    });
    return root;
}

function renderGitTree(node, element, level = 0) {
    const entries = Object.values(node.children).sort((a, b) => {
        if (a.isFile !== b.isFile) return a.isFile ? 1 : -1;
        return a.name.localeCompare(b.name);
    });

    entries.forEach(entry => {
        const div = document.createElement('div');
        div.style.paddingLeft = (level * 15) + 'px';
        div.style.cursor = 'pointer';
        div.style.padding = '4px';
        div.style.borderRadius = '3px';
        div.style.userSelect = 'none';

        if (entry.isFile) {
            // Filtrar em tempo de renderização (se houver restrição configurada)
            if (window.allowedExtensions) {
                const allowed = window.allowedExtensions;
                const name = entry.name.toLowerCase();
                const isAllowed = allowed.some(ext => name.endsWith(ext));
                if (!isAllowed) {
                    return; // Omitir do DOM
                }
            }

            div.innerHTML = `<span style="color: #94a3b8;">📄 ${entry.name}</span>`;
            div.title = entry.fullPath || entry.path;
            div.onclick = (e) => {
                e.stopPropagation();
                console.log('📄 Arquivo selecionado:', entry);
                if (window.onGitFileSelected) {
                    window.onGitFileSelected(entry);
                }
            };
            div.onmouseover = () => {
                div.style.backgroundColor = 'rgba(148, 163, 184, 0.1)';
            };
            div.onmouseout = () => {
                div.style.backgroundColor = 'transparent';
            };
            element.appendChild(div);
        } else {
            // Renderizar pasta
            const childrenContainer = document.createElement('div');
            
            // Renderizar filhos recursivamente primeiro
            renderGitTree(entry, childrenContainer, level + 1);
            
            const hasRenderedChildren = childrenContainer.children.length > 0;
            const isExpanded = entry.expanded || false;
            
            childrenContainer.style.display = isExpanded ? 'block' : 'none';
            
            div.innerHTML = `<span class="expand-icon" style="color: #cbd5e1;">${hasRenderedChildren ? (isExpanded ? '▼' : '▶') : ''} 📁 ${entry.name}</span>`;
            div.style.fontWeight = '500';
            div.onclick = (e) => {
                e.stopPropagation();
                entry.expanded = !entry.expanded;
                childrenContainer.style.display = entry.expanded ? 'block' : 'none';
                const icon = div.querySelector('.expand-icon');
                if (icon) {
                    icon.innerHTML = `${hasRenderedChildren ? (entry.expanded ? '▼' : '▶') : ''} 📁 ${entry.name}`;
                }
            };
            div.onmouseover = () => {
                div.style.backgroundColor = 'rgba(148, 163, 184, 0.1)';
            };
            div.onmouseout = () => {
                div.style.backgroundColor = 'transparent';
            };
            
            element.appendChild(div);
            element.appendChild(childrenContainer);
        }
    });
}

function renderGitFileTree(files) {
    console.log('🔍 renderGitFileTree chamada com:', files ? files.length + ' arquivos' : 'sem arquivos');
    const gitFileTree = document.getElementById('gitFileTree');
    
    if (!gitFileTree) {
        console.error('❌ CRÍTICO: Elemento gitFileTree não encontrado no DOM!');
        console.log('   Elementos disponíveis com "Tree":', document.querySelectorAll('[id*="Tree"]'));
        return;
    }
    
    console.log('✅ Elemento gitFileTree encontrado');
    gitFileTree.innerHTML = '';
    
    if (!files || files.length === 0) {
        console.warn('⚠️ Nenhum arquivo para renderizar');
        gitFileTree.innerHTML = '<div style="color: #94a3b8; font-size: 12px;">Sem arquivos</div>';
        return;
    }
    
    try {
        console.log('🌳 Construindo árvore de arquivos...');
        const tree = buildGitFileTree(files);
        console.log('✅ Árvore construída, renderizando...');
        renderGitTree(tree, gitFileTree, 0);
        console.log('✅ Árvore renderizada com sucesso');
    } catch (error) {
        console.error('❌ Erro ao renderizar árvore:', error);
        gitFileTree.innerHTML = '<div style="color: #dc2626; font-size: 12px;">❌ Erro ao renderizar arquivos</div>';
    }
}

async function gitPull() {
    const status = document.getElementById('gitStatus');
    if (!gitConfig) {
        alert('Git não configurado. Conecte primeiro.');
        return;
    }
    
    status.innerText = 'Sincronizando do GitHub (Git Pull)...';
    
    try {
        let safeBucket = userBucket;
        if (!safeBucket || typeof safeBucket !== 'string' || safeBucket.trim() === '') {
            safeBucket = 'lab01';
        }
        
        const response = await fetch('/api/git-clone', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userBucket: safeBucket,
                username: gitConfig.username || gitConfig.owner,
                token: gitConfig.token || undefined,
                owner: gitConfig.owner,
                repo: gitConfig.repo,
                branch: gitConfig.branch || 'main'
            })
        });

        if (!response.ok) {
            let errorData = {};
            try {
                errorData = await response.json();
            } catch (e) {
                errorData = { error: `HTTP ${response.status}` };
            }
            throw new Error(errorData.message || errorData.error || 'Erro ao sincronizar do servidor');
        }

        const result = await response.json();
        status.innerText = `✓ Repositório atualizado! ${result.uploadedCount} arquivos sincronizados.`;
        
        // Recarregar os arquivos na UI
        await loadGitFiles();
        
    } catch (e) {
        console.error('❌ Erro no git pull:', e);
        status.innerText = 'Erro ao atualizar: ' + e.message;
        alert('Erro ao atualizar: ' + e.message);
    }
}


// ============================================
// INICIALIZAR LISTENERS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('⏱️ DOMContentLoaded - git-file-manager.js carregado');
    console.log('🔍 gitConfigKey:', gitConfigKey);
    console.log('🔍 userBucket:', userBucket);
    
    const saved = localStorage.getItem(gitConfigKey);
    console.log('🔍 DOMContentLoaded - localStorage.%s:', gitConfigKey, saved ? '✅ ENCONTRADO' : '❌ NULL');
    if (saved) try { const cfg = JSON.parse(saved); console.log('   owner:', cfg.owner, 'repo:', cfg.repo); } catch(e) {}
    
    restoreGitFromStorage('DOMContentLoaded');
    
    // Fallback periódico curto para cenários em que DOM atrasar
    let tries = 0;
    const intervalId = setInterval(() => {
        tries++;
        restoreGitFromStorage(`interval-${tries}`);
        if (tries >= 5) clearInterval(intervalId);
    }, 800);
});

// Tentar restaurar quando window load
window.addEventListener('load', function() {
    console.log('⏱️ window.load - tentando restaurar Git');
    restoreGitFromStorage('window-load');
});

// ============================================
// FUNÇÕES GLOBAIS DE UI (compartilhadas)
// ============================================

// Toggle sidebar do Validation Rules Editor
window.toggleValidationSidebar = function() {
    const sidebar = document.getElementById('validationSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
};

// Toggle sidebar do Code Editor
window.toggleEditorSidebar = function() {
    const sidebar = document.getElementById('editorSidebar');
    const overlay = document.getElementById('sidebarOverlayBg');
    if (!sidebar) return;
    const willOpen = !sidebar.classList.contains('active');
    sidebar.classList.toggle('active', willOpen);
    if (overlay) overlay.classList.toggle('active', willOpen);
};

// Trocar abas da sidebar (Code Editor)
window.switchSidebarTab = function(tabName) {
    document.querySelectorAll('.sidebar-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.sidebar-tab-content').forEach(content => content.classList.remove('active'));
    const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
    const tabContent = document.getElementById(`tab-${tabName}`);
    if (tabButton) tabButton.classList.add('active');
    if (tabContent) tabContent.classList.add('active');

    // Ao abrir a aba Git, restaurar estado salvo
    if (tabName === 'git' && typeof restoreGitFromStorage === 'function') {
        console.log('🔀 switchSidebarTab(git) ativado');
        restoreGitFromStorage('switchSidebarTab');
    }
};

console.log('✅ git-file-manager.js carregado e pronto');
