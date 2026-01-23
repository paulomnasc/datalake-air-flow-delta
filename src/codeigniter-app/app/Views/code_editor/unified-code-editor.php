<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<!-- ================================================ -->
<!-- Git File Manager - Centralizado (reutilizável) -->
<!-- ================================================ -->
<script src="/assets/js/git-file-manager.js"></script>
<style>
        .sidebar-overlay-bg {
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
        
        .sidebar {
            width: 320px;
            background: #0f172a;
            border-right: 1px solid #334155;
            padding: 0;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform 0.2s ease, width 0.2s ease, padding 0.2s ease;
            transform: translateX(0);
            z-index: 2000;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* Fechada no desktop: some e libera espaço para o editor */
        .sidebar:not(.active) {
            width: 0;
            padding: 0;
            border-right: none;
            transform: translateX(0);
        }

        /* Modo desktop mantém sidebar fixa à esquerda */
        @media (min-width: 993px) {
            .sidebar {
                position: relative;
            }
        }

        /* Modo mobile: usa slide sem colapsar largura */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: 85vw;
                transform: translateX(-100%);
            }

            .sidebar:not(.active) {
                width: 85vw;
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }
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
            color: #cbd5e1;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        
        .file-tree {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 13px;
            color: #e2e8f0;
        }

        .file-tree .file-item {
            color: #e2e8f0;
            padding: 6px 8px;
            border-radius: 4px;
        }

        .file-tree .file-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        
        .tree-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            cursor: pointer;
            color: #e2e8f0;
            transition: all 0.15s;
            border-radius: 4px;
            user-select: none;
        }
        
        .tree-item:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        
        .tree-item.folder {
            font-weight: 500;
            color: #cbd5e1;
        }
        
        .tree-item.file {
            color: #e2e8f0;
        }
        
        .tree-item.file:hover {
            background: rgba(102, 126, 234, 0.2);
            color: #ffffff;
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
            flex: 1;
            overflow: hidden;
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
            height: 200px;
            min-height: 150px;
            max-height: 600px;
            resize: vertical;
            overflow: auto;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }
        
        /* Estilo para o handle de resize do editor */
        #editor-container::after {
            content: '⋮';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            background: #e2e8f0;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 4px 4px 0 0;
            font-size: 14px;
            cursor: ns-resize;
            pointer-events: none;
        }
        
        .results-section {
            flex: 1;
            overflow: auto;
            padding: 20px;
            background: #f8fafc;
        }
        
        /* Estilos customizados para DataTables */
        .dataTables_wrapper {
            padding: 0 !important;
        }
        
        .dataTables_length,
        .dataTables_filter {
            margin-bottom: 12px;
        }
        
        .dataTables_length label,
        .dataTables_filter label {
            font-size: 13px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .dataTables_length select,
        .dataTables_filter input {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            margin: 0 8px;
        }
        
        .dataTables_info {
            font-size: 13px;
            color: #64748b;
            padding-top: 12px;
        }
        
        .dataTables_paginate {
            padding-top: 12px;
        }
        
        .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin: 0 2px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: white;
            color: #475569;
            cursor: pointer;
            font-size: 13px;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .dataTables_paginate .paginate_button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .editor-layout {
            display: flex;
            gap: 0;
            background: #0b1224;
            min-height: calc(100vh - 140px);
            position: relative;
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
                Code Editor
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
            <aside id="editorSidebar" class="sidebar active">
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
                <!-- Tab: Git with isomorphic-git (componente reutilizável) -->
                <?php
                    $fileFilter = '.parquet';
                    include VIEWPATH . '/components/git-sidebar.php';
                ?>
            </aside>
            
            <!-- Main Editor Area -->
            <main class="main-editor">
                <!-- Tab Navigation -->
                <div class="editor-tabs" style="display: flex; gap: 4px; padding: 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <button class="editor-tab active" onclick="switchMainTab('sql')" data-tab="sql" style="flex: 0 1 auto; padding: 10px 20px; border: 1px solid #cbd5e1; background: #667eea; color: white; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                        💻 SQL Editor
                    </button>
                    <button class="editor-tab" onclick="switchMainTab('validation')" data-tab="validation" style="flex: 0 1 auto; padding: 10px 20px; border: 1px solid #cbd5e1; background: #e2e8f0; color: #475569; font-size: 14px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: all 0.2s;">
                        🛡️ Validações
                    </button>
                </div>

                <!-- SQL TAB PANEL -->
                <div id="sql-panel" class="tab-panel active" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
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
                    <button class="btn btn-secondary" id="downloadCsvBtn" onclick="downloadCSV()" disabled style="opacity: 0.5;">
                        📄 Baixar CSV
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
                </div>
                <!-- FIM SQL TAB PANEL -->

                <!-- VALIDATION TAB PANEL -->
                <div id="validation-panel" class="tab-panel" style="display: none; flex-direction: column; flex: 1; overflow: hidden;">
                    <div class="toolbar">
                        <button class="sidebar-toggle-btn" onclick="toggleEditorSidebar()">
                            📁 GitHub
                        </button>
                        <button class="btn btn-primary" onclick="testValidation()">
                            ▶️ Testar
                        </button>
                        <button class="btn btn-primary" onclick="runPythonScript()">
                            ⚡ Executar
                        </button>
                        <button class="btn btn-primary" onclick="saveValidation()">
                            💾 Salvar
                        </button>
                        <button class="btn btn-success" onclick="deployValidator()" style="background: #10b981;">
                            🚀 Deploy
                        </button>
                    </div>

                    <div style="display: flex; gap: 0; flex: 1; overflow: hidden;">
                        <div style="flex: 0 0 300px; border-right: 1px solid #e2e8f0; overflow-y: auto; padding: 16px; background: #f8fafc;">
                            <h2 style="margin: 0 0 16px 0; font-size: 18px;">📚 Templates</h2>
                            <div id="templatesList"></div>
                        </div>

                        <div style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                            <div style="padding: 16px; border-bottom: 1px solid #e2e8f0;">
                                <h2 style="margin-bottom: 12px; font-size: 18px;">✏️ Editor Python</h2>
                                <div id="editor-validation" style="height: 400px; border: 1px solid #e2e8f0; border-radius: 6px;"></div>
                            </div>
                            
                            <div style="padding: 16px; background: #f8fafc; flex: 1; overflow: auto;">
                                <h2 style="margin-bottom: 12px; font-size: 18px;">📊 Resultado</h2>
                                <div id="testResults" style="padding: 16px; background: #fff; border-radius: 6px; min-height: 100px; font-size: 12px; color: #64748b;">
                                    Teste uma validação para ver os resultados
                                </div>
                                <div id="editor-validation-output" style="height: 240px; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 12px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- FIM VALIDATION TAB PANEL -->
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
        let editorValidation;
        let currentTab = 'sql';
        // Reuse global userBucket from git-file-manager.js to avoid redeclaration
        userBucket = '<?php echo esc($userBucket ?? 'user-1'); ?>';
        let currentResults = null;
        
        // Console Monaco para saída Python
        let editorOutput;
        
        // Switch between tabs
        function switchMainTab(tab) {
            console.log('📌 Switching to tab:', tab);
            currentTab = tab;
            
            // Update buttons
            document.querySelectorAll('.editor-tab').forEach(btn => {
                if (btn.dataset.tab === tab) {
                    btn.style.background = tab === 'sql' ? '#667eea' : '#10b981';
                    btn.style.color = 'white';
                } else {
                    btn.style.background = '#e2e8f0';
                    btn.style.color = '#475569';
                }
            });
            
            // Update panels
            document.getElementById('sql-panel').style.display = tab === 'sql' ? 'flex' : 'none';
            document.getElementById('validation-panel').style.display = tab === 'validation' ? 'flex' : 'none';
            
            // Inicializar editor de validação imediatamente
            if (tab === 'validation' && !editorValidation) {
                console.log('⏳ Inicializando editor de validação...');
                // Aguardar um pouco para o DOM estar pronto
                setTimeout(() => {
                    initValidationEditor();
                }, 50);
            }
        }
        

        // toggleEditorSidebar() agora é global via git-file-manager.js
        
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
                console.warn('⚠️ Erro ao carregar arquivos Parquet (não crítico):', e.message);
                const fileTree = document.getElementById('fileTree');
                if (fileTree) {
                    fileTree.innerHTML = '<div style="color: #ef4444; font-size: 13px; padding: 8px;">Erro ao carregar arquivos</div>';
                }
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
        
        // ========================================
        // VERSION CONTROL - CODE EDITOR SCRIPT
        // v2.5.1 - Git Persistence Fix - 14/01/2026 19:15
        // ========================================
        console.log('🔧 CODE EDITOR SCRIPT v2.5.1 - Git Persistence Fix carregado');
        console.log('⏱️ Timestamp:', new Date().toLocaleTimeString());
        console.log('📦 localStorage.gitConfig inicial:', localStorage.getItem('gitConfig') ? '✅ EXISTE' : '❌ NULL');
        
        // Suppress Chrome extension message errors - estes não afetam a funcionalidade
        window.addEventListener('unhandledrejection', function(event) {
            if (event.reason?.message?.includes('message channel closed')) {
                console.warn('⚠️ Chrome extension message channel error (ignorado):', event.reason.message);
                event.preventDefault(); // Previne que o erro quebre a app
            }
        });
        
        // Rastrear limpeza de localStorage ao sair da página
        window.addEventListener('beforeunload', function() {
            const gitCfg = localStorage.getItem('gitConfig');
            console.log('👋 beforeunload: localStorage.gitConfig existe?', gitCfg ? '✅ SIM' : '❌ NÃO');
            if (gitCfg) console.log('   Conteúdo salvo:', JSON.parse(gitCfg).owner + '/' + JSON.parse(gitCfg).repo);
        });
        
        // Função única para restaurar estado Git salvo no localStorage
        function restoreGitFromStorage(trigger = 'unknown') {
            try {
                const stored = localStorage.getItem('gitConfig');
                console.log(`🔍 restoreGitFromStorage(${trigger}) ->`, stored ? 'EXISTE' : 'NULL');
                if (!stored) {
                    console.warn(`⚠️ restoreGitFromStorage(${trigger}): gitConfig não encontrado no localStorage`);
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

        // Log imediato quando script carrega
        console.log('⏱️ script DOMContentLoaded iniciado às', new Date().toLocaleTimeString());
        
        // Carregar status ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            console.log('⏱️ DOMContentLoaded event disparado às', new Date().toLocaleTimeString());
            loadDuckDBStatus();
            // Debug inicial
            const saved = localStorage.getItem('gitConfig');
            console.log('🔍 DOMContentLoaded - localStorage.gitConfig:', saved ? '✅ ENCONTRADO' : '❌ NULL');
            if (saved) try { const cfg = JSON.parse(saved); console.log('   owner:', cfg.owner, 'repo:', cfg.repo); } catch(e) {}
            // Tenta restauração imediata
            restoreGitFromStorage('DOMContentLoaded');
            // Fallback periódico curto para cenários em que DOM atrasar
            let tries = 0;
            const intervalId = setInterval(() => {
                tries++;
                restoreGitFromStorage(`interval-${tries}`);
                if (tries >= 5) clearInterval(intervalId);
            }, 800);
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
                
                // Quando um arquivo do Git é selecionado via componente
                window.addEventListener('git-file-selected', (e) => {
                    console.log('📄 git-file-selected event:', e.detail);
                    const { filepath, filename, content } = e.detail || {};
                    if (!filepath || !filename) {
                        console.warn('⚠️ Arquivo sem filepath ou filename');
                        return;
                    }

                    const ext = filename.split('.').pop().toLowerCase();
                    const langMap = {
                        'js': 'javascript', 'ts': 'typescript', 'py': 'python',
                        'sql': 'sql', 'json': 'json', 'md': 'markdown',
                        'html': 'html', 'css': 'css', 'yaml': 'yaml', 'yml': 'yaml',
                        'sh': 'shell', 'txt': 'plaintext', 'parquet': 'sql'
                    };
                    const language = langMap[ext] || 'plaintext';

                    console.log('📌 currentTab:', currentTab, '| editorValidation:', editorValidation ? 'EXISTS' : 'NULL');

                    // Carregar no editor apropriado baseado na aba ativa
                    if (currentTab === 'sql' && editor) {
                        console.log('→ Carregando em editor SQL');
                        monaco.editor.setModelLanguage(editor.getModel(), language);
                        editor.setValue(content || '');
                        
                        if (language === 'markdown') {
                            showMarkdownPreview(content || '');
                        } else {
                            hideMarkdownPreview();
                        }
                    } else if (currentTab === 'validation') {
                        console.log('→ Carregando em editor Validação');
                        // Se editor não existe, inicializar agora
                        if (!editorValidation) {
                            console.log('⏳ EditorValidation não existe, inicializando...');
                            initValidationEditor();
                            // Tentar novamente após inicialização
                            setTimeout(() => {
                                if (editorValidation) {
                                    console.log('✓ EditorValidation criado, carregando conteúdo');
                                    monaco.editor.setModelLanguage(editorValidation.getModel(), language);
                                    editorValidation.setValue(content || '');
                                } else {
                                    console.error('❌ EditorValidation ainda é null após inicialização');
                                }
                            }, 100);
                        } else {
                            console.log('✓ EditorValidation existe, carregando conteúdo');
                            monaco.editor.setModelLanguage(editorValidation.getModel(), language);
                            editorValidation.setValue(content || '');
                        }
                    }
                    
                    currentGitFile = { path: filepath, name: filename };
                    const currentInfo = document.getElementById('currentFileInfo');
                    if (currentInfo) currentInfo.innerHTML = `📄 ${filename}`;

                    const status = document.getElementById('gitStatus');
                    if (status) status.innerText = `✓ ${filename} carregado do Git`;
                });

                console.log('✓ Monaco Editor inicializado com sucesso');
            } catch (monError) {
                console.error('❌ Erro ao inicializar Monaco:', monError);
                console.warn('💡 Continuando sem Monaco Editor - form ainda funcional');
            }
        }, function(err) {
            console.error('❌ Erro ao carregar Monaco libraries:', err);
        });
        
        // ===== VALIDATION EDITOR =====
        function initValidationEditor() {
            if (editorValidation) {
                console.log('✓ EditorValidation já existe');
                // Criar console se ainda não existir
                const outEl = document.getElementById('editor-validation-output');
                if (outEl && !editorOutput && window.monaco) {
                    editorOutput = monaco.editor.create(outEl, {
                        value: 'Console pronto.\n',
                        language: 'plaintext',
                        theme: 'vs-dark',
                        automaticLayout: true,
                        readOnly: true,
                        minimap: { enabled: false },
                        fontSize: 13
                    });
                }
                return;
            }
            
            try {
                console.log('🔧 Inicializando editorValidation...');
                const container = document.getElementById('editor-validation');
                if (!container) {
                    console.error('❌ Container editor-validation não encontrado!');
                    return;
                }
                
                editorValidation = monaco.editor.create(container, {
                    value: `def validate(df):
    """Valide dados no medallion"""
    return df
`,
                    language: 'python',
                    theme: 'vs-dark',
                    automaticLayout: true,
                    minimap: { enabled: false },
                    fontSize: 14,
                    tabSize: 4,
                });

                // Criar o console Monaco para output
                const outEl = document.getElementById('editor-validation-output');
                if (outEl && window.monaco) {
                    editorOutput = monaco.editor.create(outEl, {
                        value: 'Console pronto.\n',
                        language: 'plaintext',
                        theme: 'vs-dark',
                        automaticLayout: true,
                        readOnly: true,
                        minimap: { enabled: false },
                        fontSize: 13
                    });
                }
                
                console.log('✓ EditorValidation criado com sucesso');
                loadTemplates();
            } catch (err) {
                console.error('❌ Erro ao criar editorValidation:', err);
            }
        }
        
        const templates = {
            empty: { 
                name: 'Regra Vazia', 
                code: `def validate(df):
    return df
` 
            },
            custom_simple: {
                name: '⭐ Custom Simples (Recomendado)',
                code: `from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd
import logging

log = logging.getLogger(__name__)

class MeuValidador(RawToMedallionPipeline):
    """Validador customizado - PARA DEPLOY"""
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """Validar dados após Silver"""
        try:
            log.info(f"🔍 Processando: {silver_key}")
            
            # Download
            local_file = self.hook.download_file(
                key=silver_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            
            # Ler
            df = pd.read_parquet(local_file)
            log.info(f"📊 {len(df)} registros")
            
            # Validações
            df = self._apply_validations(df)
            
            # Salvar
            df.to_parquet(local_file, index=False)
            self.hook.load_file(
                filename=local_file,
                key=silver_key,
                bucket_name=self.bucket,
                replace=True
            )
            
            return silver_key
        except Exception as e:
            log.error(f"❌ ERRO: {e}", exc_info=True)
            raise
    
    def _apply_validations(self, df):
        """Suas validações aqui"""
        # Exemplo: remover colunas nulas
        cols_to_drop = [col for col in df.columns if df[col].isnull().all()]
        if cols_to_drop:
            log.info(f"Removendo {len(cols_to_drop)} colunas nulas")
            df = df.drop(columns=cols_to_drop)
        return df
`
            },
            null_check: {
                name: 'Verificar Nulos',
                code: `def validate(df):
    """Remove registros com valores nulos em colunas críticas"""
    return df.dropna(subset=['critical_column'])
`
            },
            duplicate_check: {
                name: 'Remover Duplicatas',
                code: `def validate(df):
    """Remove registros duplicados"""
    return df.drop_duplicates()
`
            },
            type_check: {
                name: 'Validar Tipos',
                code: `def validate(df):
    """Valida tipos de dados"""
    try:
        df['amount'] = df['amount'].astype('float64')
        return df
    except:
        return df.iloc[0:0]  # Retorna vazio se falhar
`
            }
        };
        
        function loadTemplates() {
            const list = document.getElementById('templatesList');
            if (!list) return;
            list.innerHTML = '';
            Object.entries(templates).forEach(([key, tmpl]) => {
                const div = document.createElement('div');
                div.style.cssText = 'background: #e0e7ff; padding: 12px; border-radius: 6px; cursor: pointer; margin-bottom: 8px;';
                div.innerHTML = `<h3 style="margin: 0 0 4px 0; font-size: 14px; color: #10b981;">${tmpl.name}</h3>`;
                div.onclick = () => {
                    if (editorValidation) editorValidation.setValue(tmpl.code);
                };
                list.appendChild(div);
            });
        }
        
        async function testValidation() {
            const code = editorValidation?.getValue() || '';
            const resultsDiv = document.getElementById('testResults');
            
            if (!code.trim()) {
                resultsDiv.innerHTML = '<div style="color: #dc2626;">❌ Editor vazio</div>';
                return;
            }
            
            // Aceitar tanto função validate() quanto classe customizada
            const hasFunction = code.includes('def validate');
            const hasClass = /class\s+[A-Z][A-Za-z0-9_]*\s*[\(:]/.test(code);
            
            if (!hasFunction && !hasClass) {
                resultsDiv.innerHTML = '<div style="color: #dc2626;">❌ Nenhuma função ou classe encontrada<br><br>Exemplos válidos:<br>def validate(df):<br>class MeuValidador(...):</div>';
                return;
            }
            
            resultsDiv.innerHTML = '<div style="color: #10b981;">✓ Sintaxe OK! Pronto para deploy</div>';
        }
        
        async function saveValidation() {
            if (!editorValidation) {
                alert('❌ Editor de validação não inicializado');
                return;
            }
            
            const code = editorValidation.getValue();
            
            if (!code.trim()) {
                alert('❌ Editor vazio');
                return;
            }
            
            // Se há arquivo aberto do Git, salvar direto
            if (currentGitFile && gitConfig) {
                const status = document.getElementById('gitStatus');
                
                try {
                    if (status) status.innerText = 'Salvando...';
                    
                    const response = await fetch('/api/git-file-save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            userBucket: userBucket,
                            owner: gitConfig.owner,
                            repo: gitConfig.repo,
                            file: currentGitFile.path,
                            content: code
                        })
                    });
                    
                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(error.error || 'Falha ao salvar');
                    }
                    
                    if (status) status.innerText = '';
                    alert(`✅ ${currentGitFile.name} salvo com sucesso!`);
                    console.log('✅ Arquivo validação salvo no Git');
                    
                } catch (error) {
                    if (status) status.innerText = '';
                    alert('❌ Erro ao salvar: ' + error.message);
                    console.error('Erro ao salvar validação:', error);
                }
                return;
            }
            
            // Se não há arquivo aberto, solicitar nome do arquivo
            const fileName = prompt('Nome do arquivo (ex: validador.py):', 'validador.py');
            if (!fileName) return;
            
            if (!gitConfig) {
                alert('❌ Conecte ao GitHub primeiro');
                return;
            }
            
            const status = document.getElementById('gitStatus');
            
            try {
                if (status) status.innerText = `Criando ${fileName}...`;
                
                const response = await fetch('/api/git-file-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        file: fileName,
                        content: code
                    })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao criar');
                }
                
                if (status) status.innerText = '';
                alert(`✅ ${fileName} criado com sucesso!`);
                console.log('✅ Novo arquivo validação criado');
                
                // Recarregar lista de arquivos
                if (typeof loadGitFiles === 'function') {
                    await loadGitFiles();
                }
                
            } catch (error) {
                if (status) status.innerText = '';
                alert('❌ Erro ao criar: ' + error.message);
                console.error('Erro ao criar validação:', error);
            }
        }
        
        async function runPythonScript() {
            const code = editorValidation?.getValue() || '';
            const resultsDiv = document.getElementById('testResults');

            if (!code.trim()) {
                resultsDiv.innerHTML = '<div style="color: #dc2626;">❌ Editor vazio</div>';
                return;
            }

            try {
                resultsDiv.innerHTML = '<div style="color: #f59e0b;">⏳ Enviando para servidor...</div>';
                if (editorOutput) editorOutput.setValue('Executando no servidor...\n');

                // Envia código Python para executar no backend com DuckDB
                const response = await fetch('/code-editor/execute-python', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ 
                        code: code,
                        userBucket: userBucket
                    })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    let errorMsg = `Erro HTTP ${response.status}`;
                    try {
                        const errorJson = JSON.parse(errorText);
                        errorMsg = errorJson.error || errorJson.message || errorMsg;
                    } catch (e) {
                        errorMsg = errorText || errorMsg;
                    }
                    
                    resultsDiv.innerHTML = `<div style="color: #dc2626;">❌ ${errorMsg}</div>`;
                    if (editorOutput) editorOutput.setValue(`Erro: ${errorMsg}`);
                    return;
                }

                const result = await response.json();

                let outputText = '';
                if (result.stderr && String(result.stderr).trim()) {
                    outputText += 'STDERR:\n' + String(result.stderr).trim() + '\n\n';
                }
                outputText += 'STDOUT:\n' + String(result.stdout || '').trim() + '\n';
                if (result.result !== undefined && result.result !== null) {
                    outputText += '\n\nResultado da função:\n' + JSON.stringify(result.result, null, 2);
                }

                if (editorOutput) editorOutput.setValue(outputText || '');
                if (result.success) {
                    resultsDiv.innerHTML = '<div style="color: #10b981;">✅ Execução concluída</div>';
                } else {
                    let errorMsg = result.error || 'Erro ao executar';
                    resultsDiv.innerHTML = `<div style="color: #dc2626;">❌ ${errorMsg}</div>`;
                }
            } catch (e) {
                const msg = '❌ Erro ao executar: ' + e.message;
                resultsDiv.innerHTML = `<div style="color: #dc2626;">${msg}</div>`;
                if (editorOutput) editorOutput.setValue(msg + '\nVeja o console para detalhes.');
                console.error('Erro ao executar Python:', e);
            }
        }
        
        async function deployValidator() {
            const code = editorValidation?.getValue() || '';
            
            if (!code.trim()) {
                alert('❌ Editor vazio - Escreva código antes de fazer deploy');
                return;
            }
            
            // Extrair nome da classe do código (aceita vários formatos)
            const classMatch = code.match(/class\s+([A-Z][A-Za-z0-9_]*)\s*[\(:]/);
            if (!classMatch) {
                alert('❌ Não foi possível identificar o nome da classe.\n\nExemplos válidos:\nclass MeuValidador(BaseClass):\nclass MeuValidador:\nclass MeuValidador(RawToMedallionPipeline):');
                return;
            }
            
            const className = classMatch[1];
            
            // Verificar se há arquivo aberto
            let filename = '';
            if (currentGitFile && currentGitFile.name) {
                filename = currentGitFile.name;
            } else {
                filename = prompt('Nome do arquivo para deploy (ex: validador.py):', 'validador.py');
                if (!filename) return;
            }
            
            // Extrair nome do módulo (sem .py)
            const moduleName = filename.replace(/\.py$/, '');
            const modulePath = `lib.validadores.${moduleName}.${className}`;
            
            // Confirmar deploy
            const confirmMsg = `🚀 Deploy Custom Function?\n\n` +
                               `Arquivo: ${filename}\n` +
                               `Classe: ${className}\n` +
                               `Módulo: ${modulePath}\n\n` +
                               `Isso irá:\n` +
                               `1. Salvar o arquivo no Airflow\n` +
                               `2. Registrar como função custom\n` +
                               `3. Adicionar ao seu select de funções\n\n` +
                               `Confirmar?`;
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            try {
                const resultsDiv = document.getElementById('testResults');
                if (resultsDiv) {
                    resultsDiv.innerHTML = '<div style="color: #f59e0b;">⏳ Fazendo deploy...</div>';
                }
                
                // 1. Deploy do arquivo Python para Airflow
                const deployResponse = await fetch('/api/validation-deploy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        filename: filename,
                        content: code
                    })
                });
                
                const deployResult = await deployResponse.json();
                
                if (!deployResult.success) {
                    throw new Error(deployResult.error || 'Falha ao fazer deploy do arquivo');
                }
                
                console.log('✅ Arquivo salvo no Airflow:', deployResult);
                
                // 2. Registrar como custom function no banco
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                                  document.querySelector('input[name="csrf_test_name"]')?.value || '';
                
                const registerResponse = await fetch('/validation/deploy-custom', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        module_path: modulePath,
                        nome: className,
                        descricao: `Custom function: ${className}`
                    })
                });
                
                const registerResult = await registerResponse.json();
                
                if (!registerResult.success) {
                    throw new Error(registerResult.message || 'Falha ao registrar custom function');
                }
                
                console.log('✅ Custom function registrada:', registerResult);
                
                // 3. Sucesso!
                const successMsg = `✅ Deploy realizado com sucesso!\n\n` +
                                   `📄 Arquivo: ${filename}\n` +
                                   `🔧 Função: ${className}\n` +
                                   `📦 Módulo: ${modulePath}\n\n` +
                                   `Para usar:\n` +
                                   `1. Vá em "Configurações"\n` +
                                   `2. Escolha "${className}" no select\n` +
                                   `3. Configure seu DAG\n\n` +
                                   `A função já está disponível! 🎉`;
                
                alert(successMsg);
                
                if (resultsDiv) {
                    resultsDiv.innerHTML = `<div style="color: #10b981;">✅ Deploy concluído! Função "${className}" registrada.</div>`;
                }
                
            } catch (error) {
                console.error('Deploy error:', error);
                alert('❌ Erro ao fazer deploy: ' + error.message);
                const resultsDiv = document.getElementById('testResults');
                if (resultsDiv) {
                    resultsDiv.innerHTML = `<div style="color: #dc2626;">❌ Erro: ${error.message}</div>`;
                }
            }
        }
        
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
            const csvBtn = document.getElementById('downloadCsvBtn');
            
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
                currentResults = null;
                csvBtn.disabled = true;
                csvBtn.style.opacity = '0.5';
                return;
            }
            
            // Armazenar resultados para download CSV
            currentResults = result;
            csvBtn.disabled = false;
            csvBtn.style.opacity = '1';
            
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
                <table id="sql-results-table" class="results-table display" style="width:100%">
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
            
            // Inicializar DataTables com paginação de 10 registros
            setTimeout(() => {
                if ($.fn.DataTable.isDataTable('#sql-results-table')) {
                    $('#sql-results-table').DataTable().destroy();
                }
                $('#sql-results-table').DataTable({
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    language: {
                        lengthMenu: "Mostrar _MENU_ registros por página",
                        zeroRecords: "Nenhum registro encontrado",
                        info: "Mostrando página _PAGE_ de _PAGES_",
                        infoEmpty: "Nenhum registro disponível",
                        infoFiltered: "(filtrado de _MAX_ registros no total)",
                        search: "Buscar:",
                        paginate: {
                            first: "Primeira",
                            last: "Última",
                            next: "Próxima",
                            previous: "Anterior"
                        }
                    },
                    ordering: true,
                    searching: true,
                    dom: 'lfrtip'
                });
            }, 100);
        }
        
        // Exibir erro
        function showError(message) {
            const resultsDiv = document.getElementById('results');
            const csvBtn = document.getElementById('downloadCsvBtn');
            
            // Destruir DataTable se existir
            if ($.fn.DataTable.isDataTable('#sql-results-table')) {
                $('#sql-results-table').DataTable().destroy();
            }
            
            resultsDiv.innerHTML = `
                <div class="error-message">
                    ❌ <strong>Erro:</strong> ${message}
                </div>
            `;
            
            currentResults = null;
            csvBtn.disabled = true;
            csvBtn.style.opacity = '0.5';
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
                
                // Destruir DataTable se existir
                if ($.fn.DataTable.isDataTable('#sql-results-table')) {
                    $('#sql-results-table').DataTable().destroy();
                }
                
                document.getElementById('results').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">✨</div>
                        <p>Execute uma query para ver os resultados</p>
                    </div>
                `;
                
                // Limpar resultados e desabilitar botão CSV
                currentResults = null;
                const csvBtn = document.getElementById('downloadCsvBtn');
                if (csvBtn) {
                    csvBtn.disabled = true;
                    csvBtn.style.opacity = '0.5';
                }
            }
        }
        
        // Baixar resultados em CSV
        function downloadCSV() {
            if (!currentResults || !currentResults.data || currentResults.data.length === 0) {
                alert('⚠️ Nenhum resultado disponível para download');
                return;
            }
            
            const columns = currentResults.columns || Object.keys(currentResults.data[0]);
            const rows = currentResults.data;
            
            // Cabeçalho CSV
            let csv = columns.map(col => `"${col}"`).join(',') + '\n';
            
            // Linhas de dados
            rows.forEach(row => {
                const values = columns.map(col => {
                    const value = row[col];
                    if (value === null || value === undefined) {
                        return '';
                    }
                    // Escapar aspas duplas e envolver em aspas se houver vírgulas/quebras
                    const stringValue = String(value).replace(/"/g, '""');
                    if (stringValue.includes(',') || stringValue.includes('\n') || stringValue.includes('"')) {
                        return `"${stringValue}"`;
                    }
                    return stringValue;
                });
                csv += values.join(',') + '\n';
            });
            
            // Criar blob e download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            // Nome arquivo com timestamp
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
            const filename = `query_results_${timestamp}.csv`;
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            console.log(`✅ CSV baixado: ${filename} (${rows.length} linhas)`);
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

        
        // gitConfig, pfs e git já estão declarados globalmente em git-file-manager.js
        let fs; // Apenas fs é local para code-editor
        
        // Inicializar após carregamento bem-sucedido dos scripts
        function initGitAfterLoad() {
            // Verificar variáveis globais possíveis
            console.log('Verificando variáveis Git disponíveis...');
            console.log('window.git:', typeof window.git);
            console.log('window.LightningFS:', typeof window.LightningFS);
            console.log('window.FS:', typeof window.FS);
            
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
            restoreGitFromStorage('initGitAfterLoad');
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
        
        // switchSidebarTab() agora é global via git-file-manager.js
        
        // Conectar e clonar o repositório
        // connectAttempts já está declarado globalmente em git-file-manager.js
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
            console.log('💾 Salvando gitConfig no localStorage:', gitConfig);
            localStorage.setItem('gitConfig', JSON.stringify(gitConfig));
            console.log('✅ Salvo com sucesso. Verificando:', localStorage.getItem('gitConfig'));
            
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
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        userBucket: safeBucket,
                        username: gitConfig.username || gitConfig.owner,
                        // token opcional para repositórios públicos
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
                    const missingFields = errorData.missingFields ? '\n\nMissing: ' + errorData.missingFields.join(', ') : '';
                    const debugInfo = errorData.debug ? '\n[DEBUG: ' + JSON.stringify(errorData.debug) + ']' : '';
                    throw new Error(`Git clone error (${cloneResponse.status}): ${errMsg}${missingFields}${debugInfo}`);
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
                
                // Clone bem-sucedido - SALVAR gitConfig no localStorage IMEDIATAMENTE
                console.log('💾 Salvando gitConfig no localStorage:', gitConfig);
                localStorage.setItem('gitConfig', JSON.stringify(gitConfig));
                console.log('✅ Salvo com sucesso. Verificando:', localStorage.getItem('gitConfig'));
                
                // Atualizar UI DEPOIS
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
            console.log('📂 loadGitFiles() chamado às', new Date().toLocaleTimeString());
            console.log('   gitConfig:', gitConfig);
            console.log('   userBucket:', userBucket);
            
            if (!gitConfig) {
                console.error('❌ Git não configurado');
                return;
            }
            
            try {
                console.log(`🌐 Buscando arquivos: /api/git-files?userBucket=${userBucket}&owner=${gitConfig.owner}&repo=${gitConfig.repo}`);
                const response = await fetch(`/api/git-files?userBucket=${encodeURIComponent(userBucket)}&owner=${gitConfig.owner}&repo=${gitConfig.repo}`);
                
                console.log('✅ Resposta recebida. Status:', response.status);
                
                if (!response.ok) {
                    let errText = await response.text();
                    try {
                        const errJson = JSON.parse(errText);
                        errText = errJson.message || errJson.error || JSON.stringify(errJson);
                    } catch (e) {}
                    throw new Error('Failed to load files: ' + errText);
                }
                
                const result = await response.json();
                console.log('✅ Arquivos recarregados. Quantidade:', result.files ? result.files.length : 0, 'Dados:', result);
                
                renderGitFileTree(result.files || []);
            } catch (error) {
                console.error('❌ Erro ao recarregar arquivos:', error);
                console.error('Stack:', error.stack);
            }
        }
        
        // Renderizar árvore de arquivos do Git
        function buildGitFileTree(files) {
            const root = { children: {}, isFile: false };
            
            files.forEach(file => {
                const parts = file.path.split('/').filter(Boolean);

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
                    const isFile = isGitkeepPlaceholder ? false : (index === parts.length - 1);
                    
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
                    }
                    current = current.children[part];
                });
            });
            
            return root;
        }
        
        function renderGitTree(node, container, level = 0) {
            const entries = Object.values(node.children).sort((a, b) => {
                if (a.isFile !== b.isFile) return a.isFile ? 1 : -1;
                return a.name.localeCompare(b.name);
            });
            
            entries.forEach(entry => {
                const item = document.createElement('div');
                item.dataset.path = entry.fullPath || entry.path || entry.name;
                item.dataset.type = entry.isFile ? 'file' : 'folder';
                
                if (entry.isFile) {
                    item.className = 'tree-item file';
                    item.draggable = true;
                    item.innerHTML = `
                        <span class="icon">📄</span>
                        <span class="label" title="${entry.name}">${entry.name}</span>
                    `;
                    item.onclick = () => {
                        setSelectedGitNode(entry, item);
                        loadGitFileContent(entry.fileData);
                    };
                    
                    // Drag handlers
                    item.addEventListener('dragstart', (e) => {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', JSON.stringify({
                            path: entry.fullPath || entry.path,
                            name: entry.name,
                            isFile: true
                        }));
                        item.classList.add('dragging');
                    });
                    
                    item.addEventListener('dragend', () => {
                        item.classList.remove('dragging');
                    });
                } else {
                    const hasChildren = Object.keys(entry.children).length > 0;
                    const childrenContainer = document.createElement('div');
                    childrenContainer.className = `tree-children ${entry.expanded ? 'expanded' : ''}`;
                    
                    item.className = 'tree-item folder';
                    item.draggable = true;
                    item.innerHTML = `
                        <span class="expand-icon ${entry.expanded ? 'expanded' : ''}">${hasChildren ? '▶' : ''}</span>
                        <span class="icon">${entry.expanded ? '📂' : '📁'}</span>
                        <span class="label" title="${entry.name}">${entry.name}</span>
                    `;
                    
                    item.onclick = (e) => {
                        e.stopPropagation();
                        setSelectedGitNode(entry, item);
                        
                        // Limpar editor quando pasta é selecionada
                        if (editor) {
                            editor.setValue('');
                        }
                        currentGitFile = null;
                        const currentInfo = document.getElementById('currentFileInfo');
                        if (currentInfo) {
                            currentInfo.innerHTML = `📁 ${entry.name} (pasta)`;
                        }
                        
                        if (hasChildren) {
                            toggleGitFolder(item, childrenContainer, entry);
                        }
                    };
                    
                    // Drag handlers for folder
                    item.addEventListener('dragstart', (e) => {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', JSON.stringify({
                            path: entry.fullPath || entry.path,
                            name: entry.name,
                            isFile: false
                        }));
                        item.classList.add('dragging');
                    });
                    
                    item.addEventListener('dragend', () => {
                        item.classList.remove('dragging');
                    });
                    
                    // Drop handlers - allow dropping files/folders into this folder
                    item.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        item.classList.add('drag-over');
                    });
                    
                    item.addEventListener('dragleave', () => {
                        item.classList.remove('drag-over');
                    });
                    
                    item.addEventListener('drop', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        item.classList.remove('drag-over');
                        
                        const draggedData = JSON.parse(e.dataTransfer.getData('text/plain'));
                        const targetFolderPath = entry.fullPath || entry.path;
                        
                        if (draggedData.path === targetFolderPath) {
                            return; // Can't drop into itself
                        }
                        
                        // Check if trying to drop parent into child
                        if (targetFolderPath.startsWith(draggedData.path + '/')) {
                            alert('❌ Não pode mover uma pasta para dentro de si mesma');
                            return;
                        }
                        
                        moveGitEntry(draggedData.path, targetFolderPath, draggedData.name, draggedData.isFile);
                    });
                    
                    container.appendChild(item);
                    
                    if (hasChildren) {
                        renderGitTree(entry, childrenContainer, level + 1);
                        container.appendChild(childrenContainer);
                    }
                    return;
                }
                
                container.appendChild(item);
            });
        }
        
        function toggleGitFolder(folderItem, childrenContainer, entry) {
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
        
        function renderGitFileTree(files) {
            console.log('🔍 renderGitFileTree chamada com:', files);
            const gitFileTree = document.getElementById('gitFileTree');
            console.log('🔍 Elemento gitFileTree:', gitFileTree);
            
            if (!gitFileTree) {
                console.error('❌ Elemento gitFileTree não encontrado no DOM');
                return;
            }
            
            gitFileTree.innerHTML = '';
            setSelectedGitNode(null, null);
            
            if (!files || files.length === 0) {
                console.warn('⚠️ Nenhum arquivo para renderizar');
                gitFileTree.innerHTML = '<div style="color: #94a3b8; font-size: 13px; padding: 8px;">Nenhum arquivo encontrado</div>';
                return;
            }
            
            console.log(`✅ Renderizando ${files.length} arquivo(s)`);
            const tree = buildGitFileTree(files);
            renderGitTree(tree, gitFileTree, 0);
            console.log('✅ Árvore de arquivos renderizada com sucesso');
        }
        
        // Carregar conteúdo do arquivo no Monaco Editor
        let currentGitFile = null;
        let selectedGitNode = null;
        let selectedGitNodeElement = null;

        function setSelectedGitNode(entry, element) {
            if (selectedGitNodeElement) {
                selectedGitNodeElement.classList.remove('selected');
            }
            selectedGitNode = entry;
            selectedGitNodeElement = element;
            if (element) {
                element.classList.add('selected');
            }
            const renameInfo = document.getElementById('renameTargetInfo');
            if (renameInfo) {
                if (entry) {
                    const label = entry.isFile ? 'Arquivo' : 'Pasta';
                    const target = entry.fullPath || entry.path || entry.name;
                    renameInfo.textContent = `${label}: ${target}`;
                } else {
                    renameInfo.textContent = 'Selecione um arquivo ou pasta.';
                }
            }

            const renameInput = document.getElementById('renameItemName');
            if (renameInput) {
                renameInput.value = entry ? entry.name : '';
            }
        }

        function normalizeGitPath(path) {
            if (!path) return '';
            return path
                .replace(/\\/g, '/')
                .replace(/\/+/g, '/')
                .replace(/^\/+/, '')
                .replace(/\/+$/, '');
        }

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
                
                // Atualizar Monaco Editor conforme aba ativa
                if (currentTab === 'sql' && editor) {
                    monaco.editor.setModelLanguage(editor.getModel(), language);
                    editor.setValue(result.content || '');
                } else {
                    // Validação: garantir editorValidation inicializado
                    if (!editorValidation) {
                        initValidationEditor();
                        await new Promise(res => setTimeout(res, 100));
                    }
                    if (editorValidation) {
                        monaco.editor.setModelLanguage(editorValidation.getModel(), language);
                        editorValidation.setValue(result.content || '');
                    }
                }
                
                currentGitFile = file;
                const currentInfo = document.getElementById('currentFileInfo');
                if (currentInfo) currentInfo.innerHTML = `📄 ${file.name}`;
                
                if (currentTab === 'sql') {
                    if (language === 'markdown') {
                        showMarkdownPreview(result.content);
                    } else {
                        hideMarkdownPreview();
                    }
                }
                
                console.log(`✅ Arquivo carregado: ${file.name} (${language}) -> tab: ${currentTab}`);
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
                status.innerText = '';
                console.log('✅ Arquivo salvo:', result);
                
                // Exibir mensagem de sucesso com fade
                const successMsg = document.getElementById('git-success-message');
                successMsg.innerHTML = `✓ ${currentGitFile.name} salvo com sucesso no MinIO`;
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                        successMsg.style.opacity = '1';
                    }, 500);
                }, 3000);
            } catch (error) {
                status.innerText = '';
                console.error('Erro ao salvar arquivo:', error);
                
                // Exibir mensagem de erro com fade
                const errorMsg = document.getElementById('git-error-message');
                errorMsg.innerHTML = `❌ Erro ao salvar: ${error.message}`;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                        errorMsg.style.opacity = '1';
                    }, 500);
                }, 4000);
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
                status.innerText = '';
                console.log('✅ Arquivo criado:', result);
                
                // Exibir mensagem de sucesso com fade
                const successMsg = document.getElementById('git-success-message');
                successMsg.innerHTML = `✓ ${fileName} criado com sucesso`;
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                        successMsg.style.opacity = '1';
                    }, 500);
                }, 3000);
                
                // Limpar input e recarregar lista de arquivos
                document.getElementById('newFileName').value = '';
                
                // Recarregar lista de arquivos
                await loadGitFiles();
            } catch (error) {
                status.innerText = '';
                console.error('Erro ao criar arquivo:', error);
                
                // Exibir mensagem de erro com fade
                const errorMsg = document.getElementById('git-error-message');
                errorMsg.innerHTML = `❌ Erro ao criar: ${error.message}`;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                        errorMsg.style.opacity = '1';
                    }, 500);
                }, 4000);
            }
        }
        
        async function createGitFolder() {
            if (!gitConfig) {
                alert('Conecte o GitHub primeiro');
                return;
            }

            const folderInput = document.getElementById('newFolderName');
            const folderName = folderInput ? folderInput.value.trim() : '';
            if (!folderName) {
                alert('Informe o nome da pasta');
                return;
            }

            const parentPath = selectedGitNode && !selectedGitNode.isFile
                ? normalizeGitPath(selectedGitNode.fullPath || selectedGitNode.path)
                : '';
            const targetPath = normalizeGitPath(parentPath ? `${parentPath}/${folderName}` : folderName);
            if (!targetPath) {
                alert('Caminho da pasta inválido');
                return;
            }

            const status = document.getElementById('gitStatus');

            try {
                status.innerText = `Criando pasta ${targetPath}...`;
                const response = await fetch('/api/git-folder-create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        path: targetPath
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao criar pasta');
                }

                const result = await response.json();
                status.innerText = '';
                console.log('✅ Pasta criada:', result);

                const successMsg = document.getElementById('git-success-message');
                successMsg.innerHTML = `✓ Pasta ${targetPath} criada com sucesso`;
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                        successMsg.style.opacity = '1';
                    }, 500);
                }, 3000);

                if (folderInput) folderInput.value = '';
                await loadGitFiles();
            } catch (error) {
                status.innerText = '';
                console.error('Erro ao criar pasta:', error);

                const errorMsg = document.getElementById('git-error-message');
                errorMsg.innerHTML = `❌ Erro ao criar pasta: ${error.message}`;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                        errorMsg.style.opacity = '1';
                    }, 500);
                }, 4000);
            }
        }

        async function moveGitEntry(sourcePath, targetFolderPath, itemName, isFile) {
            if (!gitConfig) {
                alert('Conecte o GitHub primeiro');
                return;
            }

            const normalizedSource = normalizeGitPath(sourcePath);
            const normalizedTarget = normalizeGitPath(targetFolderPath);
            const newPath = normalizeGitPath(`${normalizedTarget}/${itemName}`);

            if (normalizedSource === newPath) {
                return; // Same location
            }

            const status = document.getElementById('gitStatus');

            try {
                status.innerText = `Movendo ${itemName}...`;
                const response = await fetch('/api/git-entry-rename', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        oldPath: normalizedSource,
                        newPath: newPath,
                        isFile: isFile
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao mover');
                }

                const result = await response.json();
                status.innerText = '';
                console.log('✅ Item movido:', result);

                const successMsg = document.getElementById('git-success-message');
                successMsg.innerHTML = `✓ ${itemName} movido para ${normalizedTarget}`;
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                        successMsg.style.opacity = '1';
                    }, 500);
                }, 3000);

                if (currentGitFile && normalizeGitPath(currentGitFile.path) === normalizedSource) {
                    currentGitFile.path = newPath;
                    currentGitFile.name = itemName;
                    const currentInfo = document.getElementById('currentFileInfo');
                    if (currentInfo) currentInfo.innerHTML = `📄 ${itemName}`;
                }

                setSelectedGitNode(null, null);
                await loadGitFiles();
            } catch (error) {
                status.innerText = '';
                console.error('Erro ao mover:', error);

                const errorMsg = document.getElementById('git-error-message');
                errorMsg.innerHTML = `❌ Erro ao mover: ${error.message}`;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                        errorMsg.style.opacity = '1';
                    }, 500);
                }, 4000);
            }
        }

        async function renameGitEntry() {
            if (!gitConfig) {
                alert('Conecte o GitHub primeiro');
                return;
            }

            if (!selectedGitNode) {
                alert('Selecione um arquivo ou pasta para renomear');
                return;
            }

            const renameInput = document.getElementById('renameItemName');
            const newName = renameInput ? renameInput.value.trim() : '';
            if (!newName) {
                alert('Informe o novo nome');
                return;
            }

            const currentPath = normalizeGitPath(selectedGitNode.fullPath || selectedGitNode.path || (selectedGitNode.fileData ? selectedGitNode.fileData.path : ''));
            if (!currentPath) {
                alert('Caminho selecionado inválido');
                return;
            }

            const parentPath = currentPath.includes('/') ? currentPath.substring(0, currentPath.lastIndexOf('/')) : '';
            const newPath = normalizeGitPath(parentPath ? `${parentPath}/${newName}` : newName);
            if (!newPath) {
                alert('Novo caminho inválido');
                return;
            }

            const status = document.getElementById('gitStatus');

            try {
                status.innerText = `Renomeando ${currentPath}...`;
                const response = await fetch('/api/git-entry-rename', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        userBucket: userBucket,
                        owner: gitConfig.owner,
                        repo: gitConfig.repo,
                        oldPath: currentPath,
                        newPath: newPath,
                        isFile: !!selectedGitNode.isFile
                    })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Falha ao renomear');
                }

                const result = await response.json();
                status.innerText = '';
                console.log('✅ Item renomeado:', result);

                const successMsg = document.getElementById('git-success-message');
                successMsg.innerHTML = `✓ ${currentPath} renomeado para ${newPath}`;
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                        successMsg.style.opacity = '1';
                    }, 500);
                }, 3000);

                if (currentGitFile && normalizeGitPath(currentGitFile.path) === currentPath) {
                    currentGitFile.path = newPath;
                    currentGitFile.name = newName;
                    const currentInfo = document.getElementById('currentFileInfo');
                    if (currentInfo) currentInfo.innerHTML = `📄 ${newName}`;
                }

                if (renameInput) renameInput.value = '';
                setSelectedGitNode(null, null);
                await loadGitFiles();
            } catch (error) {
                status.innerText = '';
                console.error('Erro ao renomear:', error);

                const errorMsg = document.getElementById('git-error-message');
                errorMsg.innerHTML = `❌ Erro ao renomear: ${error.message}`;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                        errorMsg.style.opacity = '1';
                    }, 500);
                }, 4000);
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
                status.innerText = '';
                console.log('✅ Arquivo deletado:', result);
                
                // Exibir mensagem de sucesso com fade
                const successMsg = document.getElementById('git-success-message');
                successMsg.innerHTML = `✓ ${currentGitFile.name} deletado com sucesso`;
                successMsg.style.display = 'block';
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                        successMsg.style.opacity = '1';
                    }, 500);
                }, 3000);
                
                // Limpar editor e info
                if (editor) {
                    editor.setValue('');
                }
                document.getElementById('currentFileInfo').innerHTML = 'Nenhum arquivo aberto';
                currentGitFile = null;
                
                // Recarregar lista de arquivos
                await loadGitFiles();
            } catch (error) {
                status.innerText = '';
                console.error('Erro ao deletar arquivo:', error);
                
                // Exibir mensagem de erro com fade
                const errorMsg = document.getElementById('git-error-message');
                errorMsg.innerHTML = `❌ Erro ao deletar: ${error.message}`;
                errorMsg.style.display = 'block';
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    errorMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        errorMsg.style.display = 'none';
                        errorMsg.style.opacity = '1';
                    }, 500);
                }, 4000);
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
            console.log('🔍 window-load event - localStorage.gitConfig:', saved ? '✅ ENCONTRADO' : '❌ NULL');
            restoreGitFromStorage('window-load');
        });
    </script>

<?php
require VIEWPATH . '/footer.php';
?>
