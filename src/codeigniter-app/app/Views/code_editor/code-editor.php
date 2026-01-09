<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<style>
        .code-editor-container {
            max-width: 100%;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: visible;
        }
        
        .code-editor-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .code-editor-header h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4ade80;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .editor-layout {
            display: flex;
            flex-direction: column;
            min-height: 600px;
            height: auto;
            width: 100%;
            position: relative;
        }
        
        /* Sidebar retrátil com overlay */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
            padding: 20px;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 2000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .sidebar-overlay-bg {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1999;
            display: none;
        }
        
        .sidebar-overlay-bg.active {
            display: block;
        }
        
        .sidebar-close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e2e8f0;
            border: none;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            color: #475569;
            transition: all 0.2s;
        }
        
        .sidebar-close-btn:hover {
            background: #cbd5e1;
            color: #1e293b;
        }
        
        .sidebar-toggle-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .sidebar-toggle-btn:hover {
            background: #5568d3;
        }
        
        .sidebar-section {
            margin-bottom: 24px;
        }
        
        .sidebar-section h3 {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        
        .file-tree {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 13px;
        }
        
        .tree-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            cursor: pointer;
            color: #475569;
            transition: all 0.15s;
            border-radius: 4px;
            user-select: none;
        }
        
        .tree-item:hover {
            background: #e2e8f0;
        }
        
        .tree-item.folder {
            font-weight: 500;
            color: #1e293b;
        }
        
        .tree-item.file {
            color: #64748b;
        }
        
        .tree-item.file:hover {
            background: #ede9fe;
            color: #667eea;
        }
        
        .tree-item .icon {
            width: 16px;
            height: 16px;
            margin-right: 6px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .tree-item .expand-icon {
            width: 12px;
            margin-right: 4px;
            flex-shrink: 0;
            transition: transform 0.2s;
            color: #94a3b8;
        }
        
        .tree-item .expand-icon.expanded {
            transform: rotate(90deg);
        }
        
        .tree-children {
            margin-left: 16px;
            display: none;
        }
        
        .tree-children.expanded {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .tree-item .label {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .main-editor {
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        
        .toolbar {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 20px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .limit-control {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
        }
        
        .limit-control input {
            width: 80px;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }
        
        #editor-container {
            flex: 1;
            min-height: 300px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .results-section {
            flex: 1;
            overflow: auto;
            padding: 20px;
            background: #f8fafc;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .results-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .results-stats {
            font-size: 13px;
            color: #64748b;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .results-table th {
            background: #f1f5f9;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .results-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #334155;
        }
        
        .results-table tr:hover {
            background: #f8fafc;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .success-message {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: #64748b;
            gap: 12px;
        }
        
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Sidebar Tabs */
        .sidebar-tabs {
            display: flex;
            gap: 8px;
            padding: 12px;
            border-bottom: 1px solid #334155;
            background: #0f172a;
        }
        
        .sidebar-tab {
            flex: 1;
            padding: 8px 12px;
            border: none;
            background: #1e293b;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .sidebar-tab:hover {
            background: #334155;
            color: #cbd5e1;
        }
        
        .sidebar-tab.active {
            background: #667eea;
            color: white;
        }
        
        .sidebar-tab-content {
            display: none;
            padding: 12px;
            overflow-y: auto;
            max-height: calc(100vh - 250px);
        }
        
        .sidebar-tab-content.active {
            display: block;
        }
        
        /* Git Changes List */
        #gitChanges li, #gitHistory li {
            padding: 8px;
            background: #1e293b;
            border-radius: 4px;
            margin-bottom: 6px;
            font-size: 12px;
            border-left: 3px solid #667eea;
        }
        
        #gitChanges li .file-status {
            display: inline-block;
            margin-right: 6px;
            font-weight: bold;
        }
        
        #gitHistory li {
            background: #0f172a;
            border-left: 3px solid #10b981;
            padding: 10px;
        }
        
        #gitHistory li .commit-hash {
            display: block;
            font-family: monospace;
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
</style>

<div id="content">
    <div class="container">
        <div class="code-editor-container">
            <div class="code-editor-header">
            <h1>
                <span>💻</span>
                SQL Code Editor
            </h1>
            <div class="status-badge" id="statusBadge">
                <span class="status-dot"></span>
                🔴 DuckDB Offline
            </div>
    </div>
        
        <div class="editor-layout">
            <!-- Overlay background -->
            <div id="sidebarOverlayBg" class="sidebar-overlay-bg"></div>
            
            <!-- Sidebar retrátil -->
            <aside id="editorSidebar" class="sidebar">
                <button class="sidebar-close-btn" onclick="toggleEditorSidebar()">×</button>
                
                <!-- Tabs de navegação -->
                <div class="sidebar-tabs">
                    <button class="sidebar-tab active" onclick="switchSidebarTab('files')" data-tab="files">
                        📁 Arquivos
                    </button>
                    <button class="sidebar-tab" onclick="switchSidebarTab('git')" data-tab="git">
                        🔗 Git
                    </button>
                </div>
                
                <!-- Tab: Arquivos Parquet -->
                <div id="tab-files" class="sidebar-tab-content active">
                    <div class="sidebar-section">
                        <h3>📁 Arquivos Parquet</h3>
                        <ul class="file-tree" id="fileTree"></ul>
                    </div>
                    
                    <div class="sidebar-section">
                        <h3>📊 Exemplos</h3>
                        <ul class="file-tree">
                            <li class="file-item" onclick="loadExample('select')">
                                <span class="file-icon">✨</span>
                                <span>SELECT básico</span>
                            </li>
                            <li class="file-item" onclick="loadExample('join')">
                                <span class="file-icon">🔗</span>
                                <span>JOIN tables</span>
                            </li>
                            <li class="file-item" onclick="loadExample('aggregate')">
                                <span class="file-icon">📈</span>
                                <span>Agregações</span>
                            </li>
                            <li class="file-item" onclick="loadExample('window')">
                                <span class="file-icon">🪟</span>
                                <span>Window Functions</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- Tab: Git with isomorphic-git -->
                <div id="tab-git" class="sidebar-tab-content">
                    <div class="sidebar-section">
                        <h3>🔗 GitHub</h3>
                        <div id="gitLoadingStatus" style="padding: 12px; background: #1e293b; border-radius: 6px; font-size: 11px; color: #94a3b8; margin-bottom: 12px; display: none;">
                            ⏳ Carregando isomorphic-git...
                        </div>
                        
                        <div id="gitNotConnected" style="padding: 16px;">
                            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 12px;">Conecte seu GitHub para versionar scripts SQL</p>
                            <input type="text" id="githubUsername" placeholder="GitHub Username" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 8px;">
                            <input type="password" id="githubToken" placeholder="Personal Access Token" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
                            <p style="font-size: 10px; color: #64748b; margin-bottom: 8px; line-height: 1.4;">
                                💡 Gere em: <a href="https://github.com/settings/tokens/new" target="_blank" style="color: #667eea; text-decoration: none;">GitHub Settings → Developer settings → Personal access tokens → Generate new token</a><br>
                                Marque o scope: <strong style="color: #94a3b8;">repo</strong>
                            </p>
                            <label style="display: flex; align-items: center; font-size: 11px; color: #94a3b8; margin-bottom: 8px; cursor: pointer;">
                                <input type="checkbox" id="disableCorsProxy" style="margin-right: 6px;">
                                Desabilitar CORS Proxy (tentar conexão direta)
                            </label>
                            <details style="margin-bottom: 8px;">
                                <summary style="font-size: 11px; color: #94a3b8; cursor: pointer;">Modo offline (sem CDN)</summary>
                                <div style="font-size: 10px; color: #64748b; margin-top: 6px; line-height: 1.5;">
                                    Se os CDNs estiverem bloqueados, você pode usar arquivos locais. Qualquer uma das opções abaixo funciona:<br>
                                    • ESM (preferível):<br>
                                    - <strong style="color:#94a3b8;">/assets/vendor/isomorphic-git/index.js</strong> ou <strong style="color:#94a3b8;">/public/assets/vendor/isomorphic-git/index.js</strong><br>
                                    - <strong style="color:#94a3b8;">/assets/vendor/isomorphic-git/http-web.js</strong> ou <strong style="color:#94a3b8;">/public/assets/vendor/isomorphic-git/http-web.js</strong><br>
                                    - <strong style="color:#94a3b8;">/assets/vendor/lightning-fs/index.js</strong> ou <strong style="color:#94a3b8;">/public/assets/vendor/lightning-fs/index.js</strong><br>
                                    • UMD (alternativa):<br>
                                    - <strong style="color:#94a3b8;">/assets/vendor/isomorphic-git/bundle.umd.min.js</strong> ou <strong style="color:#94a3b8;">/public/assets/vendor/isomorphic-git/bundle.umd.min.js</strong><br>
                                    - <strong style="color:#94a3b8;">/assets/vendor/lightning-fs/lightning-fs.min.js</strong> ou <strong style="color:#94a3b8;">/public/assets/vendor/lightning-fs/lightning-fs.min.js</strong><br>
                                    O loader tenta primeiro os caminhos locais (/assets e /public/assets) antes dos CDNs.
                                </div>
                            </details>
                            <input type="text" id="repoURL" placeholder="Repo: user/sql-scripts" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 8px;">
                            <button class="btn btn-primary" onclick="connectGitHub()" style="width: 100%;">
                                ✓ Conectar
                            </button>
                        </div>
                        
                        <div id="gitConnected" style="display: none;">
                            <div id="repoInfo" style="padding: 10px; background: #1e293b; border-radius: 6px; font-size: 11px; margin-bottom: 12px; color: #cbd5e1;"></div>
                            
                            <!-- Seção: Arquivo Atual -->
                            <div style="margin-bottom: 12px;">
                                <h3 style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">📝 Arquivo Atual</h3>
                                <div id="currentFileInfo" style="padding: 8px; background: #0f172a; border-radius: 4px; font-size: 11px; color: #64748b; margin-bottom: 8px;">
                                    Nenhum arquivo aberto
                                </div>
                                <button class="btn btn-primary" onclick="saveGitFile()" style="width: 100%; margin-bottom: 4px;">
                                    💾 Salvar
                                </button>
                                <button class="btn btn-secondary" onclick="deleteGitFile()" style="width: 100%; margin-bottom: 8px; background: #dc2626; color: white;">
                                    🗑️ Deletar
                                </button>
                            </div>
                            
                            <!-- Seção: Criar Novo Arquivo -->
                            <div style="margin-bottom: 12px;">
                                <h3 style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">➕ Criar Novo Arquivo</h3>
                                <input type="text" id="newFileName" placeholder="exemplo.sql" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
                                <button class="btn btn-primary" onclick="createNewGitFile()" style="width: 100%;">
                                    ✨ Criar do Editor
                                </button>
                            </div>
                            
                            <!-- Seção: Arquivos do Repositório -->
                            <div style="margin-bottom: 12px;">
                                <h3 style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">📄 Arquivos do Repositório</h3>
                                <ul class="file-tree" id="gitFileTree"></ul>
                            </div>
                            
                            <!-- Seção: Commit & Push -->
                            <div style="margin-bottom: 12px;">
                                <h3 style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">📤 Sincronizar GitHub</h3>
                                <textarea id="commitMsg" placeholder="Descrever mudanças..." style="width: 100%; height: 60px; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; resize: none; margin-bottom: 4px;"></textarea>
                                <button class="btn btn-primary" onclick="gitAddCommitPush()" style="width: 100%; margin-bottom: 8px;">
                                    🚀 Commit & Push
                                </button>
                            </div>
                            
                            <div id="gitStatus" style="padding: 10px; background: #0f172a; border-radius: 6px; font-size: 11px; color: #64748b; margin-bottom: 8px; max-height: 150px; overflow-y: auto;"></div>
                            
                            <button class="btn btn-secondary" onclick="disconnectGitHub()" style="width: 100%;">
                                🔓 Desconectar
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
            
            <!-- Main Editor Area -->
            <main class="main-editor">
                <div class="toolbar">
                    <button class="sidebar-toggle-btn" onclick="toggleEditorSidebar()">
                        📁 Arquivos
                    </button>
                    <button class="btn btn-primary" onclick="executeQuery()" id="executeBtn">
                        ▶️ Executar
                    </button>
                    <button class="btn btn-secondary" onclick="formatSQL()">
                        🎨 Formatar
                    </button>
                    <button class="btn btn-secondary" onclick="clearEditor()">
                        🗑️ Limpar
                    </button>
                    
                    <div class="limit-control">
                        <label>Limite:</label>
                        <input type="number" id="limitInput" value="1000" min="1" max="10000">
                    </div>
                </div>
                
                <div id="editor-container"></div>
                
                <!-- Markdown Preview Panel (hidden by default) -->
                <div id="markdown-preview" style="display: none; padding: 20px; background: #fff; border-top: 1px solid #e2e8f0; overflow-y: auto; max-height: 400px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 16px; font-weight: 600; color: #1e293b;">📖 Preview Markdown</h3>
                        <button onclick="toggleMarkdownPreview()" style="background: #e2e8f0; border: none; padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">Fechar</button>
                    </div>
                    <div id="markdown-content" style="line-height: 1.6; color: #334155;"></div>
                </div>
                
                <div class="results-section">
                    <div id="results"></div>
                </div>
            </main>
        </div>
        </div>
    </div>
</div>
    
    <!-- Monaco Editor -->
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
    <!-- Marked.js for Markdown rendering -->
    <script src="https://cdn.jsdelivr.net/npm/marked@11.0.0/marked.min.js"></script>
    
    <script>
        let editor;
        const userBucket = '<?php echo esc($userBucket ?? 'user-1'); ?>';
        
        // Toggle sidebar retrátil
        function toggleEditorSidebar() {
            const sidebar = document.getElementById('editorSidebar');
            const overlay = document.getElementById('sidebarOverlayBg');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        // Fechar sidebar ao clicar no overlay
        document.getElementById('sidebarOverlayBg').addEventListener('click', function() {
            toggleEditorSidebar();
        });
        
        // Carregar status do DuckDB dinamicamente
        async function loadDuckDBStatus() {
            try {
                const response = await fetch('/code-editor/status');
                const data = await response.json();
                
                const badge = document.getElementById('statusBadge');
                if (data.healthy) {
                    badge.innerHTML = '<span class="status-dot"></span>🟢 DuckDB Online';
                } else {
                    badge.innerHTML = '<span class="status-dot"></span>🔴 DuckDB Offline';
                }
            } catch (e) {
                const badge = document.getElementById('statusBadge');
                badge.innerHTML = '<span class="status-dot"></span>🔴 DuckDB Offline';
            }
        }
        
        // Carregar arquivos Parquet
        async function loadParquetFiles() {
            try {
                const response = await fetch('/code-editor/files', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: '' })
                });
                const data = await response.json();
                
                const fileTree = document.getElementById('fileTree');
                fileTree.innerHTML = '';
                
                if (data.files && data.files.length > 0) {
                    const tree = buildFileTree(data.files.map(f => f[0]));
                    renderTree(tree, fileTree, 0);
                } else {
                    fileTree.innerHTML = '<div style="color: #94a3b8; font-size: 13px; padding: 8px;">Nenhum arquivo encontrado</div>';
                }
            } catch (e) {
                document.getElementById('fileTree').innerHTML = '<div style="color: #ef4444; font-size: 13px; padding: 8px;">Erro ao carregar arquivos</div>';
            }
        }
        
        function buildFileTree(paths) {
            const root = { children: {}, isFile: false };
            
            paths.forEach(path => {
                const cleanPath = path.replace(/^s3:\/\//, '');
                const parts = cleanPath.split('/');
                let current = root;
                
                parts.forEach((part, index) => {
                    if (!part) return;
                    
                    if (!current.children[part]) {
                        current.children[part] = {
                            name: part,
                            fullPath: path,
                            isFile: index === parts.length - 1,
                            children: {},
                            expanded: index < 2
                        };
                    }
                    current = current.children[part];
                });
            });
            
            return root;
        }
        
        function renderTree(node, container, level = 0) {
            const entries = Object.values(node.children).sort((a, b) => {
                if (a.isFile !== b.isFile) return a.isFile ? 1 : -1;
                return a.name.localeCompare(b.name);
            });
            
            entries.forEach(entry => {
                const item = document.createElement('div');
                
                if (entry.isFile) {
                    item.className = 'tree-item file';
                    item.innerHTML = `
                        <span class="icon">📄</span>
                        <span class="label" title="${entry.name}">${entry.name}</span>
                    `;
                    item.onclick = () => insertQueryFromFile(entry.fullPath);
                } else {
                    const hasChildren = Object.keys(entry.children).length > 0;
                    const childrenContainer = document.createElement('div');
                    childrenContainer.className = `tree-children ${entry.expanded ? 'expanded' : ''}`;
                    
                    item.className = 'tree-item folder';
                    item.innerHTML = `
                        <span class="expand-icon ${entry.expanded ? 'expanded' : ''}">${hasChildren ? '▶' : ''}</span>
                        <span class="icon">${entry.expanded ? '📂' : '📁'}</span>
                        <span class="label" title="${entry.name}">${entry.name}</span>
                    `;
                    
                    if (hasChildren) {
                        item.onclick = (e) => {
                            e.stopPropagation();
                            toggleFolder(item, childrenContainer, entry);
                        };
                    }
                    
                    container.appendChild(item);
                    
                    if (hasChildren) {
                        renderTree(entry, childrenContainer, level + 1);
                        container.appendChild(childrenContainer);
                    }
                    return;
                }
                
                container.appendChild(item);
            });
        }
        
        function toggleFolder(folderItem, childrenContainer, entry) {
            const expandIcon = folderItem.querySelector('.expand-icon');
            const icon = folderItem.querySelector('.icon');
            const isExpanded = childrenContainer.classList.toggle('expanded');
            
            if (isExpanded) {
                expandIcon.classList.add('expanded');
                icon.textContent = '📂';
                entry.expanded = true;
            } else {
                expandIcon.classList.remove('expanded');
                icon.textContent = '📁';
                entry.expanded = false;
            }
        }
        
        function insertQueryFromFile(filePath) {
            // Validar extensão do arquivo
            const supportedExtensions = ['.parquet', '.csv', '.json', '.jsonl', '.tsv'];
            const fileExtension = filePath.substring(filePath.lastIndexOf('.')).toLowerCase();
            
            if (!supportedExtensions.includes(fileExtension)) {
                alert(`❌ Arquivo não compatível!\n\nArquivo: ${filePath.split('/').pop()}\nExtensão: ${fileExtension}\n\nFormatos suportados:\n✓ .parquet (recomendado)\n✓ .csv\n✓ .json / .jsonl\n✓ .tsv`);
                return;
            }
            
            // Determinar função de leitura baseada na extensão
            let readFunction = 'read_parquet';
            if (fileExtension === '.csv' || fileExtension === '.tsv') {
                readFunction = 'read_csv';
            } else if (fileExtension === '.json' || fileExtension === '.jsonl') {
                readFunction = 'read_json';
            }
            
            const sql = `SELECT * FROM ${readFunction}('${filePath}') LIMIT 10`;
            editor.setValue(sql);
            editor.focus();
            toggleEditorSidebar(); // Fechar sidebar
        }
        
        // Suppress Chrome extension message errors - estes não afetam a funcionalidade
        window.addEventListener('unhandledrejection', function(event) {
            if (event.reason?.message?.includes('message channel closed')) {
                console.warn('⚠️ Chrome extension message channel error (ignorado):', event.reason.message);
                event.preventDefault(); // Previne que o erro quebre a app
            }
        });
        
        // Carregar status ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadDuckDBStatus();
        });
        
        // Configurar Monaco Editor
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' } });
        
        require(['vs/editor/editor.main'], function () {
            try {
                editor = monaco.editor.create(document.getElementById('editor-container'), {
                    value: `-- Bem-vindo ao SQL Code Editor
-- Execute queries SQL diretamente nos seus arquivos Parquet

SELECT * 
FROM read_parquet('s3://${userBucket}/bronze/seus_dados.parquet') 
LIMIT 10;`,
                    language: 'sql',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: true },
                    fontSize: 14,
                    lineNumbers: 'on',
                    roundedSelection: true,
                    scrollBeyondLastLine: false,
                    readOnly: false,
                    cursorStyle: 'line',
                    suggestOnTriggerCharacters: true,
                    quickSuggestions: true,
                    wordBasedSuggestions: true,
                    tabSize: 2,
                    insertSpaces: true,
                    formatOnPaste: true,
                    formatOnType: true,
                });
                
                // Atalho Ctrl+Enter para executar
                editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, executeQuery);
                
                // Carregar arquivos após editor pronto
                loadParquetFiles();
                
                // SQL Keywords para autocomplete
                monaco.languages.registerCompletionItemProvider('sql', {
                    provideCompletionItems: function(model, position) {
                        const suggestions = [
                            {
                                label: 'read_parquet',
                                kind: monaco.languages.CompletionItemKind.Function,
                                insertText: "read_parquet('s3://${userBucket}/bronze/${1:file}.parquet')",
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                                documentation: 'Lê arquivo Parquet do S3'
                            },
                            {
                                label: 'SELECT * FROM',
                                kind: monaco.languages.CompletionItemKind.Snippet,
                                insertText: "SELECT * FROM \${1:table} LIMIT \${2:10};",
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                                documentation: 'SELECT básico'
                            }
                        ];
                        return { suggestions: suggestions };
                    }
                });
                
                console.log('✓ Monaco Editor inicializado com sucesso');
            } catch (monError) {
                console.error('❌ Erro ao inicializar Monaco:', monError);
                console.warn('💡 Continuando sem Monaco Editor - form ainda funcional');
            }
        }, function(err) {
            console.error('❌ Erro ao carregar Monaco libraries:', err);
        });
        
        // Executar query
        async function executeQuery() {
            const sql = editor.getValue().trim();
            
            if (!sql) {
                showError('Digite uma query SQL antes de executar');
                return;
            }
            
            const limit = parseInt(document.getElementById('limitInput').value) || 1000;
            const resultsDiv = document.getElementById('results');
            const executeBtn = document.getElementById('executeBtn');
            
            executeBtn.disabled = true;
            resultsDiv.innerHTML = '<div class="loading"><div class="spinner"></div> Executando query...</div>';
            
            // Controller para cancelar requisição após timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
            }, 45000); // 45 segundos
            
            try {
                const response = await fetch('/code-editor/execute', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ sql, limit }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    let errorMsg = `Erro HTTP ${response.status}`;
                    try {
                        const errorJson = JSON.parse(errorText);
                        errorMsg = errorJson.error || errorJson.message || errorMsg;
                    } catch (e) {
                        errorMsg = errorText || errorMsg;
                    }
                    showError(errorMsg);
                    return;
                }
                
                const result = await response.json();
                
                if (result.success) {
                    displayResults(result);
                } else {
                    // Melhorar mensagem de erro para arquivo não encontrado
                    let errorMsg = result.error || 'Erro ao executar query';
                    
                    // Detectar erros comuns
                    if (errorMsg.includes('No files found') || errorMsg.includes('does not exist') || errorMsg.includes('NoSuchKey')) {
                        errorMsg = '❌ Arquivo não encontrado no MinIO. Verifique se o caminho está correto e se o arquivo existe.';
                    } else if (errorMsg.includes('Invalid Input Error') || errorMsg.includes('not a valid Parquet') || errorMsg.includes('Parquet file')) {
                        errorMsg = '❌ Arquivo inválido ou corrompido. Certifique-se de que é um arquivo Parquet válido.';
                    } else if (errorMsg.includes('Permission denied') || errorMsg.includes('Access Denied')) {
                        errorMsg = '❌ Sem permissão para acessar este arquivo.';
                    } else if (errorMsg.includes('timeout')) {
                        errorMsg = '❌ Timeout: Query demorou muito (>30s). Verifique se o arquivo existe e está acessível, ou se está corrompido.';
                    }
                    
                    showError(errorMsg);
                }
            } catch (error) {
                clearTimeout(timeoutId);
                
                if (error.name === 'AbortError') {
                    showError('❌ Timeout: Query foi cancelada após 45 segundos. Verifique o caminho do arquivo ou se ele existe.');
                } else if (error.message.includes('timeout') || error.message.includes('timed out')) {
                    showError('❌ Timeout na conexão. Verifique se o servidor está acessível.');
                } else {
                    showError('Erro de conexão: ' + error.message);
                }
            } finally {
                executeBtn.disabled = false;
            }
        }
        
        // Exibir resultados
        function displayResults(result) {
            const resultsDiv = document.getElementById('results');
            
            if (!result.data || result.data.length === 0) {
                resultsDiv.innerHTML = `
                    <div class="success-message">
                        ✅ Query executada com sucesso! Nenhum resultado retornado.
                    </div>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>Sem resultados</p>
                    </div>
                `;
                return;
            }
            
            const columns = result.columns || Object.keys(result.data[0]);
            const rowCount = result.data.length;
            const executionTime = result.execution_time_ms || 0;
            
            let html = `
                <div class="results-header">
                    <h3>📊 Resultados</h3>
                    <div class="results-stats">
                        ${rowCount} linha(s) · ${executionTime}ms
                    </div>
                </div>
                <table class="results-table">
                    <thead>
                        <tr>
                            ${columns.map(col => `<th>${col}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            result.data.forEach(row => {
                html += '<tr>';
                columns.forEach(col => {
                    const value = row[col];
                    const displayValue = value === null ? '<i style="color: #94a3b8;">null</i>' : value;
                    html += `<td>${displayValue}</td>`;
                });
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            resultsDiv.innerHTML = html;
        }
        
        // Exibir erro
        function showError(message) {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = `
                <div class="error-message">
                    ❌ <strong>Erro:</strong> ${message}
                </div>
            `;
        }
        
        // Formatar SQL
        function formatSQL() {
            const sql = editor.getValue();
            editor.getAction('editor.action.formatDocument').run();
        }
        
        // Limpar editor
        function clearEditor() {
            if (confirm('Deseja limpar todo o conteúdo do editor?')) {
                editor.setValue('');
                document.getElementById('results').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">✨</div>
                        <p>Execute uma query para ver os resultados</p>
                    </div>
                `;
            }
        }
        
        // Inserir caminho do arquivo
        function insertFilePath(filePath) {
            const sql = `SELECT * FROM read_parquet('${filePath}') LIMIT 100;`;
            editor.setValue(sql);
            editor.focus();
        }
        
        // Carregar exemplos
        function loadExample(type) {
            const examples = {
                select: `-- SELECT básico
SELECT * 
FROM read_parquet('s3://${userBucket}/bronze/seus_dados.parquet')
LIMIT 100;`,
                
                join: `-- JOIN entre tabelas
SELECT 
    a.id,
    a.nome,
    b.valor
FROM read_parquet('s3://${userBucket}/bronze/tabela_a.parquet') a
JOIN read_parquet('s3://${userBucket}/bronze/tabela_b.parquet') b
ON a.id = b.id
LIMIT 100;`,
                
                aggregate: `-- Agregações
SELECT 
    categoria,
    COUNT(*) as total,
    AVG(valor) as media,
    SUM(valor) as soma
FROM read_parquet('s3://${userBucket}/bronze/vendas.parquet')
GROUP BY categoria
ORDER BY total DESC;`,
                
                window: `-- Window Functions
SELECT 
    nome,
    departamento,
    salario,
    ROW_NUMBER() OVER (PARTITION BY departamento ORDER BY salario DESC) as rank
FROM read_parquet('s3://${userBucket}/bronze/funcionarios.parquet')
ORDER BY departamento, rank;`
            };
            
            if (examples[type]) {
                editor.setValue(examples[type]);
                editor.focus();
            }
        }
        
        // Inicializar com estado vazio
        document.getElementById('results').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">✨</div>
                <p>Execute uma query para ver os resultados</p>
            </div>
        `;
        
        
        // ===== GIT (isomorphic-git) =====
        
        // Aguardar Monaco carregar, depois carregar Git via <script> tags sequencialmente
        (function bootGit() {
            const loadingStatus = document.getElementById('gitLoadingStatus');
            function setLoading(msg) { if (loadingStatus) { loadingStatus.style.display = 'block'; loadingStatus.textContent = msg; } }
            function clearLoading() { if (loadingStatus) loadingStatus.style.display = 'none'; }
            
            setLoading('⏳ Carregando bibliotecas Git...');
            
            // Aguardar um pouco para Monaco terminar de carregar seus próprios módulos AMD
            setTimeout(() => {
                console.log('Iniciando carregamento de Git...');
                
                // Salvar define original
                const origDefine = window.define;
                delete window.define; // Remover completamente
                delete window.require;
                
                // Criar ambiente mínimo para UMD
                window.module = { exports: {} };
                window.exports = {};
                
                // Carregar LightningFS via <script>
                const lfsScript = document.createElement('script');
                lfsScript.src = 'https://cdn.jsdelivr.net/npm/@isomorphic-git/lightning-fs@4.6.0/dist/lightning-fs.min.js';
                lfsScript.onerror = () => {
                    setLoading('❌ Falha LightningFS');
                    window.define = origDefine;
                };
                lfsScript.onload = () => {
                    console.log('✓ LightningFS carregado');
                    console.log('window.LightningFS:', typeof window.LightningFS);
                    console.log('window.module.exports:', window.module?.exports);
                    
                    // Copiar de module.exports para global
                    if (window.module?.exports && !window.LightningFS) {
                        window.LightningFS = window.module.exports;
                        console.log('✓ window.LightningFS atribuído de module.exports');
                    }
                    
                    // RESETAR module.exports para próximo script
                    window.module.exports = {};
                    
                    // Restaurar define temporariamente para isomorphic-git
                    // (pode precisar passar para Monaco para funcionar)
                    if (origDefine) window.define = origDefine;
                    
                    // Carregar isomorphic-git
                    const gitScript = document.createElement('script');
                    gitScript.src = 'https://cdn.jsdelivr.net/npm/isomorphic-git@1.25.7/index.umd.min.js';
                    gitScript.onerror = () => {
                        setLoading('❌ Falha isomorphic-git');
                        window.define = origDefine;
                    };
                    gitScript.onload = () => {
                        console.log('✓ isomorphic-git carregado');
                        console.log('window.git:', typeof window.git);
                        console.log('window.module.exports:', window.module?.exports);
                        
                        // Copiar de module.exports para global
                        if (window.module?.exports && !window.git) {
                            window.git = window.module.exports;
                            console.log('✓ window.git atribuído de module.exports');
                        }
                        
                        // Restaurar define original
                        if (origDefine) window.define = origDefine;
                        
                        // Criar HTTP client inline imediatamente
                        createHttpClientImmediate();
                    };
                    document.head.appendChild(gitScript);
                };
                document.head.appendChild(lfsScript);
            }, 2000); // Aguardar 2 segundos para Monaco terminar
        })();

        // Função para criar HTTP client inline - chamada após git estar pronto
        function createHttpClientImmediate() {
            console.log('🔧 Criando HTTP client inline...');
            
            if (!window.git) {
                console.error('❌ window.git não está disponível');
                return;
            }
            
            window.git.http = {
                // Assinatura compatível com isomorphic-git: recebe um objeto com url/method/headers/body
                request: async ({ url, method = 'GET', headers = {}, body } = {}) => {
                    if (!url) {
                        throw new Error('HTTP client: url não informado');
                    }
                    console.log(`📤 HTTP ${method} ${url.substring(0, 100)}${url.length > 100 ? '...' : ''}`);
                    
                    // Fallback de auth se não veio do isomorphic-git (usa gitConfig global)
                    let authHeader = headers['authorization'] || headers['Authorization'];
                    
                    if (!authHeader && window.gitConfig && window.gitConfig.token) {
                        // Para GitHub, username deve ser o owner/username do GitHub, não email
                        const ghUsername = window.gitConfig.owner || window.gitConfig.username;
                        authHeader = 'Basic ' + btoa(`${ghUsername}:${window.gitConfig.token}`);
                    }

                    const mergedHeaders = {
                        'User-Agent': 'isomorphic-git/1.25.7',
                        ...headers,
                        ...(authHeader ? { authorization: authHeader } : {})
                    };
                    // Garantir Authorization sempre em minúsculas e visível no log
                    if (mergedHeaders.Authorization && !mergedHeaders.authorization) {
                        mergedHeaders.authorization = mergedHeaders.Authorization;
                        delete mergedHeaders.Authorization;
                    }

                    // NÃO fazer proxy aqui - deixar o isomorphic-git gerenciar corsProxy
                    // O isomorphic-git vai adicionar o corsProxy na URL quando necessário
                    const targetUrl = url;

                    // Remover cabeçalhos proibidos pelo navegador
                    delete mergedHeaders.host;
                    delete mergedHeaders.origin;
                    
                    const fetchOpts = {
                        method,
                        headers: mergedHeaders,
                        credentials: 'omit',
                        mode: 'cors'
                    };
                    
                    if (body) {
                        if (typeof body === 'string') {
                            fetchOpts.body = body;
                        } else if (body instanceof Uint8Array) {
                            fetchOpts.body = body;
                        } else if (Array.isArray(body)) {
                            fetchOpts.body = body.join('');
                        }
                    }
                    
                    try {
                        const res = await fetch(targetUrl, fetchOpts);
                        const resHeaders = {};
                        for (const [key, value] of res.headers.entries()) {
                            resHeaders[key.toLowerCase()] = value;
                        }
                        // Para respostas binárias, usar arrayBuffer; senão, usar text
                        const bodyBuffer = await res.arrayBuffer();
                        const bodyUint8 = new Uint8Array(bodyBuffer);
                        
                        console.log(`📥 HTTP ${res.status} ${res.statusText} (${bodyBuffer.byteLength} bytes)`);
                        
                        // Log detalhado para TODAS as requisições Git para debug completo
                        console.log('🔍 DEBUG response detalhada:');
                        console.log('  URL:', url.substring(0, 120));
                        console.log('  Method:', method);
                        console.log('  Status:', res.status, '(' + typeof res.status + ')');
                        console.log('  StatusText:', res.statusText, '(' + typeof res.statusText + ')');
                        console.log('  Content-Type:', resHeaders['content-type']);
                        console.log('  Content-Length:', resHeaders['content-length']);
                        console.log('  Body length:', bodyUint8.length);
                        console.log('  Body is Uint8Array:', bodyUint8 instanceof Uint8Array);
                        
                        if (bodyUint8.length <= 200) {
                            const decoded = new TextDecoder().decode(bodyUint8);
                            console.log('  Body completo:', decoded);
                            console.log('  Body hex:', Array.from(bodyUint8.slice(0, 50)).map(x => x.toString(16).padStart(2, '0')).join(' '));
                        } else {
                            console.log('  Body primeiros 200 bytes:', new TextDecoder().decode(bodyUint8.slice(0, 200)));
                            console.log('  Body primeiros 50 bytes (hex):', Array.from(bodyUint8.slice(0, 50)).map(x => x.toString(16).padStart(2, '0')).join(' '));
                        }
                        
                        if (res.status >= 400) {
                            const bodyText = new TextDecoder().decode(bodyUint8);
                            console.error(`❌ ERRO ${res.status} em ${method} ${url.substring(0, 80)}`);
                            console.error('Resposta:', bodyText.substring(0, 300));
                            console.error('Headers:', Object.entries(resHeaders).slice(0, 5));
                        }
                        
                        // VALIDAR que status e statusText não sejam undefined
                        const validStatus = typeof res.status === 'number' ? res.status : 500;
                        const validStatusText = res.statusText || 'Unknown';
                        
                        if (typeof res.status !== 'number') {
                            console.error('⚠️ AVISO: res.status não é number!', res.status, typeof res.status);
                        }
                        
                        return {
                            url,
                            method,
                            headers: resHeaders,
                            body: [bodyUint8],
                            status: validStatus,
                            statusText: validStatusText
                        };
                    } catch (err) {
                        console.error(`❌ Erro FETCH em ${method} ${url.substring(0, 80)}:`, err.message);
                        console.error('Erro completo:', err);
                        // Retornar objeto no formato esperado para o isomorphic-git levantar HttpError com status 0
                        return {
                            url,
                            method,
                            headers: {},
                            body: [new Uint8Array(0)],
                            status: 0,
                            statusText: err.message || 'Fetch failed'
                        };
                    }
                }
            };
            
            console.log('✓ HTTP client criado');
            console.log('git.http.request:', typeof window.git.http.request);
            // Limpar indicador de carregamento com fallback se escopo local não estiver disponível
            if (typeof clearLoading === 'function') {
                clearLoading();
            } else {
                const el = document.getElementById('gitLoadingStatus');
                if (el) el.style.display = 'none';
            }
            initGitAfterLoad();
        }

        
        let gitConfig = null;
        let fs, pfs, git;
        
        // Inicializar após carregamento bem-sucedido dos scripts
        function initGitAfterLoad() {
            // Verificar variáveis globais possíveis
            console.log('Verificando variáveis Git disponíveis...');
            console.log('window.git:', typeof window.git);
            console.log('window.LightningFS:', typeof window.LightningFS);
            console.log('window.FS:', typeof window.FS);

            // Restaurar gitConfig do localStorage para window (necessário para Authorization)
            try {
                const stored = localStorage.getItem('gitConfig');
                if (stored) {
                    gitConfig = JSON.parse(stored);
                    window.gitConfig = gitConfig;
                }
            } catch (e) {
                console.warn('Não foi possível restaurar gitConfig do localStorage', e);
            }
            
            // isomorphic-git pode expor como 'git' ou exportar diretamente
            git = window.git;
            
            if (!git) {
                console.error('❌ window.git não encontrado');
                // Listar todas as variáveis globais que contêm 'git' ou 'lightning'
                const gitVars = Object.keys(window).filter(k => 
                    k.toLowerCase().includes('git') || 
                    k.toLowerCase().includes('lightning') ||
                    k.toLowerCase().includes('fs')
                );
                console.log('Variáveis relacionadas:', gitVars);
                return;
            }
            
            if (!window.LightningFS) {
                console.error('❌ window.LightningFS não encontrado');
                return;
            }
            
            // Inicializar filesystem agora
            initFS();
            
            console.log('✓ Git inicializado com sucesso');
        }
        
        function initFS() {
            if (fs && pfs) {
                console.log('✓ Filesystem já inicializado');
                return;
            }
            
            if (!window.LightningFS) {
                console.error('❌ LightningFS não disponível');
                return;
            }
            
            try {
                // Criar nova instância no contexto da página principal (não do iframe)
                fs = new window.LightningFS('code-editor-fs', { wipe: false });
                pfs = fs.promises;
                console.log('✓ Filesystem inicializado (LightningFS)');
                console.log('fs:', fs);
                console.log('pfs:', pfs);
            } catch (e) {
                console.error('❌ Erro ao inicializar filesystem:', e);
                console.error('Stack:', e.stack);
            }
        }
        
        // Verificar se Git está pronto para uso
        function isGitReady() {
            return typeof window.git !== 'undefined' && typeof window.LightningFS !== 'undefined';
        }
        
        // Trocar abas da sidebar
        function switchSidebarTab(tabName) {
            document.querySelectorAll('.sidebar-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.sidebar-tab-content').forEach(content => content.classList.remove('active'));
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }
        
        // Conectar e clonar o repositório
        let connectAttempts = 0;
        async function connectGitHub() {
            const status = document.getElementById('gitStatus');
            
            if (!isGitReady()) {
                connectAttempts++;
                if (connectAttempts > 10) {
                    alert('❌ Erro ao carregar isomorphic-git. Recarregue a página.\n\nVerifique o console (F12) para detalhes.');
                    console.error('isomorphic-git não carregou após 10 tentativas');
                    console.log('window.git:', typeof window.git);
                    console.log('window.LightningFS:', typeof window.LightningFS);
                    connectAttempts = 0;
                    return;
                }
                status.innerText = 'Aguardando carregamento do isomorphic-git... (' + connectAttempts + '/10)';
                setTimeout(connectGitHub, 800);
                return;
            }
            connectAttempts = 0;
            git = window.git;
            
            // Garantir que filesystem está inicializado
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
            localStorage.setItem('gitConfig', JSON.stringify(gitConfig));
            
            status.innerText = 'Clonando repositório...';
            
            try {
                // Chamada server-side para clonar no MinIO
                const cloneResponse = await fetch('/api/git-clone', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        username: gitConfig.username || gitConfig.owner,
                        // token opcional para repositórios públicos
                        token: gitConfig.token || undefined,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        branch: gitConfig.branch || 'main'
                    })
                });

                if (!cloneResponse.ok) {
                    const errorData = await cloneResponse.json();
                    const errMsg = errorData.message || errorData.error || 'Clone failed on server';
                    const debugInfo = errorData.debug ? ' [DEBUG: ' + JSON.stringify(errorData.debug) + ']' : '';
                    throw new Error(errMsg + debugInfo);
                }

                const cloneResult = await cloneResponse.json();
                console.log('✅ Clone server-side concluído:', cloneResult);
                
                status.innerText = 'Carregando arquivos...';
                
                // Listar arquivos do repositório
                const filesResponse = await fetch(`/api/git-files?userBucket=${encodeURIComponent(userBucket)}&owner=${owner}&repo=${repo}`);
                if (!filesResponse.ok) {
                    let errText = await filesResponse.text();
                    try {
                        const errJson = JSON.parse(errText);
                        errText = errJson.message || errJson.error || JSON.stringify(errJson);
                    } catch (e) {}
                    throw new Error('Failed to load files: ' + errText);
                }
                
                const filesResult = await filesResponse.json();
                console.log('✅ Arquivos carregados:', filesResult);
                
                // Clone bem-sucedido - atualizar UI PRIMEIRO
                status.innerText = '✓ Clone concluído com sucesso (persistido no MinIO)';
                console.log('✓ Clone bem-sucedido via servidor (persistido no MinIO)');
                
                document.getElementById('gitNotConnected').style.display = 'none';
                document.getElementById('gitConnected').style.display = 'block';
                document.getElementById('repoInfo').innerHTML = `Conectado a <strong>${owner}/${repo}</strong>`;
                
                // Renderizar árvore de arquivos DEPOIS (elemento precisa estar visível)
                setTimeout(() => {
                    renderGitFileTree(filesResult.files || []);
                }, 100);
                
            } catch (e) {
                // Clone falhou - reverter UI e limpar config
                gitConfig = null;
                localStorage.removeItem('gitConfig');
                status.innerText = 'Erro ao clonar: ' + (e?.message || 'Falha desconhecida');
                alert('Erro ao clonar: ' + (e?.message || 'Falha desconhecida'));
                console.error('Clone error:', e);
                console.error('Stack trace:', e?.stack);
                
                // Garantir que UI volta ao estado inicial
                document.getElementById('gitNotConnected').style.display = 'block';
                document.getElementById('gitConnected').style.display = 'none';
            }
        }
        
        function disconnectGitHub() {
            gitConfig = null;
            localStorage.removeItem('gitConfig');
            document.getElementById('gitNotConnected').style.display = 'block';
            document.getElementById('gitConnected').style.display = 'none';
            document.getElementById('githubToken').value = '';
            document.getElementById('repoURL').value = '';
            document.getElementById('commitMsg').value = '';
            document.getElementById('gitStatus').innerText = '';
            // Limpar árvore de arquivos
            const gitFileTree = document.getElementById('gitFileTree');
            if (gitFileTree) gitFileTree.innerHTML = '';
        }
        
        // Recarregar lista de arquivos do Git
        async function loadGitFiles() {
            if (!gitConfig) {
                console.error('Git não configurado');
                return;
            }
            
            try {
                const response = await fetch(`/api/git-files?userBucket=${encodeURIComponent(userBucket)}&owner=${gitConfig.owner}&repo=${gitConfig.repo}`);
                if (!response.ok) {
                    let errText = await response.text();
                    try {
                        const errJson = JSON.parse(errText);
                        errText = errJson.message || errJson.error || JSON.stringify(errJson);
                    } catch (e) {}
                    throw new Error('Failed to load files: ' + errText);
                }
                
                const result = await response.json();
                console.log('✅ Arquivos recarregados:', result);
                
                renderGitFileTree(result.files || []);
            } catch (error) {
                console.error('Erro ao recarregar arquivos:', error);
            }
        }
        
        // Renderizar árvore de arquivos do Git
        function renderGitFileTree(files) {
            console.log('🔍 renderGitFileTree chamada com:', files);
            const gitFileTree = document.getElementById('gitFileTree');
            console.log('🔍 Elemento gitFileTree:', gitFileTree);
            
            if (!gitFileTree) {
                console.error('❌ Elemento gitFileTree não encontrado no DOM');
                return;
            }
            
            gitFileTree.innerHTML = '';
            
            if (!files || files.length === 0) {
                console.warn('⚠️ Nenhum arquivo para renderizar');
                gitFileTree.innerHTML = '<li style="color: #94a3b8; padding: 8px;">Nenhum arquivo</li>';
                return;
            }
            
            console.log(`✅ Renderizando ${files.length} arquivo(s)`);
            files.forEach(file => {
                const li = document.createElement('li');
                li.className = 'tree-item file';
                li.innerHTML = `
                    <span class="icon">📄</span>
                    <span class="label">${file.name}</span>
                `;
                li.onclick = () => loadGitFileContent(file);
                gitFileTree.appendChild(li);
                console.log(`  📄 Arquivo adicionado: ${file.name}`);
            });
            console.log('✅ Árvore de arquivos renderizada com sucesso');
        }
        
        // Carregar conteúdo do arquivo no Monaco Editor
        let currentGitFile = null;
        async function loadGitFileContent(file) {
            if (!gitConfig) {
                alert('Repositório não conectado');
                return;
            }
            
            try {
                const response = await fetch(
                    `/api/git-file-content?userBucket=${encodeURIComponent(userBucket)}&owner=${gitConfig.owner}&repo=${gitConfig.repo}&file=${encodeURIComponent(file.path)}`
                );
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao carregar arquivo');
                }
                
                const result = await response.json();
                
                // Detectar linguagem pela extensão
                const ext = file.name.split('.').pop().toLowerCase();
                const langMap = {
                    'js': 'javascript', 'ts': 'typescript', 'py': 'python',
                    'sql': 'sql', 'json': 'json', 'md': 'markdown',
                    'html': 'html', 'css': 'css', 'yaml': 'yaml', 'yml': 'yaml',
                    'sh': 'shell', 'txt': 'plaintext'
                };
                const language = langMap[ext] || 'plaintext';
                
                // Atualizar Monaco Editor
                if (editor) {
                    monaco.editor.setModelLanguage(editor.getModel(), language);
                    editor.setValue(result.content || '');
                    currentGitFile = file;
                    
                    // Atualizar display de arquivo atual
                    document.getElementById('currentFileInfo').innerHTML = `📄 ${file.name}`;
                    
                    // Se for Markdown, mostrar preview
                    if (language === 'markdown') {
                        showMarkdownPreview(result.content);
                    } else {
                        hideMarkdownPreview();
                    }
                    
                    console.log(`✅ Arquivo carregado: ${file.name} (${language})`);
                }
            } catch (error) {
                console.error('Erro ao carregar arquivo:', error);
                alert('Erro ao carregar arquivo: ' + error.message);
            }
        }
        
        // Salvar arquivo editado de volta ao MinIO
        async function saveGitFile() {
            if (!gitConfig || !currentGitFile) {
                alert('Nenhum arquivo aberto para salvar');
                return;
            }
            
            if (!editor) {
                alert('Editor não inicializado');
                return;
            }
            
            const content = editor.getValue();
            const status = document.getElementById('gitStatus');
            
            try {
                status.innerText = 'Salvando arquivo...';
                
                const response = await fetch('/api/git-file-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        file: currentGitFile.path,
                        content: content
                    })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao salvar');
                }
                
                const result = await response.json();
                status.innerText = `✓ ${currentGitFile.name} salvo com sucesso no MinIO`;
                console.log('✅ Arquivo salvo:', result);
                
                setTimeout(() => {
                    status.innerText = '';
                }, 3000);
            } catch (error) {
                status.innerText = 'Erro ao salvar: ' + error.message;
                console.error('Erro ao salvar arquivo:', error);
                alert('Erro ao salvar: ' + error.message);
            }
        }
        
        // Criar novo arquivo a partir do conteúdo do editor
        async function createNewGitFile() {
            if (!gitConfig) {
                alert('Conecte o GitHub primeiro');
                return;
            }
            
            const fileName = document.getElementById('newFileName').value.trim();
            if (!fileName) {
                alert('Informe o nome do arquivo');
                return;
            }
            
            if (!editor) {
                alert('Editor não inicializado');
                return;
            }
            
            const content = editor.getValue();
            if (!content.trim()) {
                if (!confirm('O editor está vazio. Criar arquivo vazio?')) {
                    return;
                }
            }
            
            const status = document.getElementById('gitStatus');
            
            try {
                status.innerText = `Criando ${fileName}...`;
                
                const response = await fetch('/api/git-file-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        file: fileName,
                        content: content
                    })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao criar arquivo');
                }
                
                const result = await response.json();
                status.innerText = `✓ ${fileName} criado com sucesso`;
                console.log('✅ Arquivo criado:', result);
                
                // Limpar input e recarregar lista de arquivos
                document.getElementById('newFileName').value = '';
                
                // Recarregar lista de arquivos
                await loadGitFiles();
                
                setTimeout(() => {
                    status.innerText = '';
                }, 3000);
            } catch (error) {
                status.innerText = 'Erro ao criar: ' + error.message;
                console.error('Erro ao criar arquivo:', error);
                alert('Erro ao criar: ' + error.message);
            }
        }
        
        // Deletar arquivo atual
        async function deleteGitFile() {
            if (!gitConfig || !currentGitFile) {
                alert('Nenhum arquivo aberto para deletar');
                return;
            }
            
            if (!confirm(`Tem certeza que deseja deletar "${currentGitFile.name}"? Esta ação não pode ser desfeita.`)) {
                return;
            }
            
            const status = document.getElementById('gitStatus');
            
            try {
                status.innerText = `Deletando ${currentGitFile.name}...`;
                
                const response = await fetch('/api/git-file-delete', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        file: currentGitFile.path
                    })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao deletar');
                }
                
                const result = await response.json();
                status.innerText = `✓ ${currentGitFile.name} deletado com sucesso`;
                console.log('✅ Arquivo deletado:', result);
                
                // Limpar editor e info
                if (editor) {
                    editor.setValue('');
                }
                document.getElementById('currentFileInfo').innerHTML = 'Nenhum arquivo aberto';
                currentGitFile = null;
                
                // Recarregar lista de arquivos
                await loadGitFiles();
                
                setTimeout(() => {
                    status.innerText = '';
                }, 3000);
            } catch (error) {
                status.innerText = 'Erro ao deletar: ' + error.message;
                console.error('Erro ao deletar arquivo:', error);
                alert('Erro ao deletar: ' + error.message);
            }
        }
        
        // Mostrar preview de Markdown
        function showMarkdownPreview(markdownContent) {
            const previewPanel = document.getElementById('markdown-preview');
            const contentDiv = document.getElementById('markdown-content');
            
            if (typeof marked !== 'undefined') {
                contentDiv.innerHTML = marked.parse(markdownContent || '');
                previewPanel.style.display = 'block';
                
                // Atualizar preview quando editor mudar
                if (editor) {
                    editor.onDidChangeModelContent(() => {
                        const currentContent = editor.getValue();
                        contentDiv.innerHTML = marked.parse(currentContent);
                    });
                }
            } else {
                console.warn('Marked.js não carregado');
            }
        }
        
        // Esconder preview de Markdown
        function hideMarkdownPreview() {
            const previewPanel = document.getElementById('markdown-preview');
            previewPanel.style.display = 'none';
        }
        
        // Toggle preview de Markdown
        function toggleMarkdownPreview() {
            const previewPanel = document.getElementById('markdown-preview');
            if (previewPanel.style.display === 'none') {
                if (currentGitFile && currentGitFile.name.endsWith('.md')) {
                    showMarkdownPreview(editor.getValue());
                } else {
                    alert('Abra um arquivo .md para ver o preview');
                }
            } else {
                hideMarkdownPreview();
            }
        }
        
        // Add, commit e push no repositório clonado (server-side via MinIO)
        async function gitAddCommitPush() {
            if (!gitConfig) {
                alert('Conecte o GitHub primeiro');
                return;
            }
            
            const commitMsg = document.getElementById('commitMsg').value.trim();
            if (!commitMsg) {
                alert('Informe uma mensagem de commit');
                return;
            }
            
            const status = document.getElementById('gitStatus');
            status.innerText = 'Preparando push para GitHub...';
            
            try {
                const response = await fetch('/api/git-push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        token: gitConfig.token,
                        commitMsg: commitMsg
                    })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || error.message || 'Push failed');
                }
                
                const result = await response.json();
                status.innerText = `✓ Push realizado! ${result.downloadedFiles} arquivos sincronizados`;
                console.log('✅ Push concluído:', result);
                
                // Limpar mensagem de commit
                document.getElementById('commitMsg').value = '';
                
                setTimeout(() => {
                    status.innerText = '';
                }, 5000);
                
            } catch (error) {
                status.innerText = 'Erro no push: ' + error.message;
                console.error('Erro ao fazer push:', error);
                alert('Erro ao fazer push: ' + error.message);
            }
        }
        
        // Restaurar conexão ao carregar
        window.addEventListener('load', function() {
            const saved = localStorage.getItem('gitConfig');
            if (saved) {
                gitConfig = JSON.parse(saved);
                if (!gitConfig.username) {
                    gitConfig.username = gitConfig.owner || '';
                    localStorage.setItem('gitConfig', JSON.stringify(gitConfig));
                }
                document.getElementById('gitNotConnected').style.display = 'none';
                document.getElementById('gitConnected').style.display = 'block';
                document.getElementById('repoInfo').innerHTML = `Conectado a <strong>${gitConfig.owner}/${gitConfig.repo}</strong>`;
                document.getElementById('githubUsername').value = gitConfig.username || '';
                document.getElementById('githubToken').value = gitConfig.token || '';
                document.getElementById('repoURL').value = `${gitConfig.owner}/${gitConfig.repo}`;
            }
        });
    </script>

<?php
require VIEWPATH . '/footer.php';
?>
