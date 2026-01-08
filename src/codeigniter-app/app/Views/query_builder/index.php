<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<style>
        .query-builder-container {
            max-width: 100%;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .query-builder-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .query-builder-header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-badge.healthy::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            display: block;
        }
        
        .status-badge.offline::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            display: block;
        }
        
        .query-builder-main {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 0;
            min-height: 600px;
            overflow: hidden;
            width: 100%;
        }
        
        .query-builder-aside {
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 20px;
            max-height: 600px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .query-builder-aside h2 {
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        
        .query-builder-section {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow: hidden;
            width: 100%;
            min-width: 0;
        }
        
        .file-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow-y: auto;
            flex: 1;
            padding-right: 8px;
            margin-right: -8px;
        }
        
        .file-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .file-list::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 10px;
        }
        
        .file-list::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        
        .file-list::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
        
        .tree-item {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            cursor: pointer;
            font-size: 13px;
            color: #475569;
            transition: all 0.15s;
            border-radius: 4px;
            user-select: none;
        }
        
        .tree-item:hover {
            background: #f1f5f9;
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
            display: block;
        }
        
        .tree-item .label {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .editor-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex-shrink: 0;
        }
        
        label {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        #sqlEditor {
            width: 100%;
            height: 250px;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            resize: vertical;
            transition: border-color 0.2s;
        }
        
        #sqlEditor:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        button:hover {
            background: #5568d3;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        button:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }
        
        button.secondary {
            background: #64748b;
        }
        
        button.secondary:hover {
            background: #475569;
        }
        
        .limit-input {
            width: 100px;
        }
        
        #results {
            flex: 1;
            display: flex;
            flex-direction: column;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            min-width: 0;
            width: 100%;
        }
        
        .results-header {
            background: #f1f5f9;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #1e293b;
        }
        
        .results-content {
            flex: 1;
            overflow: auto;
            background: white;
            display: block;
            width: 100%;
        }
        
        .results-content::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        
        .results-content::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 10px;
        }
        
        .results-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        
        .results-content::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: white;
            display: table;
            table-layout: auto;
        }
        
        thead {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-bottom: 2px solid #667eea;
            z-index: 10;
        }
        
        th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            min-width: 80px;
        }
        
        td {
            padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
            word-wrap: break-word;
            word-break: break-word;
            max-width: 300px;
        }
        
        tbody tr {
            transition: background-color 0.15s ease;
        }
        
        tbody tr:hover {
            background: #f0f4ff;
        }
        
        tbody tr:nth-child(even) {
            background: #fafbfc;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: #667eea;
            font-weight: 600;
        }
        
        .spinner {
            border: 3px solid #e2e8f0;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error {
            padding: 16px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            color: #991b1b;
            display: flex;
            gap: 10px;
        }
        
        .error::before {
            content: '⚠️';
        }
        
        .success {
            padding: 8px 12px;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            border-radius: 4px;
            color: #166534;
            font-size: 13px;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: #94a3b8;
            text-align: center;
        }
        
        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }
        
        @media (max-width: 1024px) {
            .query-builder-main {
                grid-template-columns: 1fr;
            }
            
            .query-builder-aside {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
        }
    </style>

    <div class="query-builder-container">
        <div class="query-builder-header">
            <div>
                <h1>
                    🦆 Query Builder
                    <span style="font-size: 18px; color: rgba(255,255,255,0.7);">DuckDB SQL em Parquet</span>
                </h1>
                <?php if (isset($userBucket)): ?>
                <div style="font-size: 13px; margin-top: 8px; color: rgba(255,255,255,0.8);">
                    📦 Seu bucket: <code style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($userBucket) ?></code>
                </div>
                <?php endif; ?>
            </div>
            <div class="status-badge" id="statusBadge">
                Verificando...
            </div>
        </div>
        
        <div class="query-builder-main">
            <div class="query-builder-aside">
                <h2>📁 Arquivos Parquet</h2>
                <div class="file-list" id="fileList">
                    <p style="color: #94a3b8; font-size: 13px;">Carregando...</p>
                </div>
            </div>
            
            <div class="query-builder-section">
                <div class="editor-wrapper">
                    <label for="sqlEditor">📝 SQL Query</label>
                    <textarea 
                        id="sqlEditor" 
                        placeholder="Digite sua query SQL aqui..."
                    ></textarea>
                    
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                        <div style="flex: 1;">
                            <div style="font-size: 11px; color: #64748b; margin-bottom: 4px; font-weight: 600;">💡 EXEMPLO DE QUERY:</div>
                            <code id="exampleQuery" style="font-size: 12px; color: #475569; font-family: 'Courier New', monospace; display: block; word-break: break-all;">
                                <?php 
                                $userBucket = $userBucket ?? 'lab01';
                                echo "SELECT * FROM read_parquet('s3://{$userBucket}/bronze/seus_dados.parquet') LIMIT 10";
                                ?>
                            </code>
                        </div>
                        <button 
                            onclick="copyExample()" 
                            style="padding: 6px 12px; font-size: 12px; white-space: nowrap; background: #667eea; display: none;"
                            title="Copiar exemplo para editor">
                            📋 Copiar
                        </button>
                    </div>
                </div>
                
                <div class="controls">
                    <button id="executeBtn" onclick="executeQuery()">▶️ Executar</button>
                    <button class="secondary" onclick="clearEditor()">Limpar</button>
                    <label style="margin-left: auto;">
                        Limite de resultados:
                        <input type="number" id="limitInput" class="limit-input" value="1000" min="1" max="10000">
                    </label>
                </div>
                
                <div id="results">
                    <div class="results-header">📊 Resultados</div>
                    <div class="results-content" id="resultsContent">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2v20M2 12h20"/>
                            </svg>
                            <p>Execute uma query para ver resultados</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const API_BASE = '/query-builder';
        
        // Carrega status da API ao iniciar
        document.addEventListener('DOMContentLoaded', () => {
            loadDuckDBStatus();
            loadParquetFiles();
        });
        
        async function loadDuckDBStatus() {
            try {
                const response = await fetch(`${API_BASE}/status`);
                const data = await response.json();
                
                const badge = document.getElementById('statusBadge');
                if (data.healthy) {
                    badge.className = 'status-badge healthy';
                    badge.textContent = '🟢 DuckDB Online';
                } else {
                    badge.className = 'status-badge offline';
                    badge.textContent = '🔴 DuckDB Offline';
                }
            } catch (e) {
                document.getElementById('statusBadge').className = 'status-badge offline';
                document.getElementById('statusBadge').textContent = '🔴 DuckDB Offline';
            }
        }
        
        async function loadParquetFiles() {
            try {
                const response = await fetch(`${API_BASE}/parquet-files`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        path: '<?= $userS3Path ?? "" ?>'
                    })
                });
                const data = await response.json();
                
                const fileList = document.getElementById('fileList');
                fileList.innerHTML = '';
                
                if (data.files && data.files.length > 0) {
                    const tree = buildFileTree(data.files.map(f => f[0]));
                    renderTree(tree, fileList, 0);
                } else {
                    fileList.innerHTML = '<p style="color: #94a3b8; font-size: 13px;">Nenhum arquivo encontrado</p>';
                }
            } catch (e) {
                document.getElementById('fileList').innerHTML = '<p style="color: #ef4444; font-size: 13px;">Erro ao carregar arquivos</p>';
            }
        }
        
        function buildFileTree(paths) {
            const root = { children: {}, isFile: false };
            
            paths.forEach(path => {
                // Remove s3:// prefix if present
                const cleanPath = path.replace(/^s3:\/\//, '');
                const parts = cleanPath.split('/');
                let current = root;
                
                parts.forEach((part, index) => {
                    if (!part) return; // Skip empty parts
                    
                    if (!current.children[part]) {
                        current.children[part] = {
                            name: part,
                            fullPath: path,
                            isFile: index === parts.length - 1,
                            children: {},
                            expanded: index < 2 // Auto-expand first 2 levels
                        };
                    }
                    current = current.children[part];
                });
            });
            
            return root;
        }
        
        function renderTree(node, container, level = 0) {
            const entries = Object.values(node.children).sort((a, b) => {
                // Folders first, then files
                if (a.isFile !== b.isFile) return a.isFile ? 1 : -1;
                return a.name.localeCompare(b.name);
            });
            
            entries.forEach(entry => {
                const item = document.createElement('div');
                
                if (entry.isFile) {
                    // File item
                    item.className = 'tree-item file';
                    item.innerHTML = `
                        <span class="icon">📄</span>
                        <span class="label" title="${entry.name}">${entry.name}</span>
                    `;
                    item.onclick = () => insertQuery(entry.fullPath);
                } else {
                    // Folder item
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
        
        function insertQuery(filePath) {
            const editor = document.getElementById('sqlEditor');
            editor.value = `SELECT * FROM read_parquet('${filePath}') LIMIT 10`;
            editor.focus();
        }
        
        async function executeQuery() {
            const sql = document.getElementById('sqlEditor').value;
            const limit = parseInt(document.getElementById('limitInput').value) || 1000;
            
            if (!sql.trim()) {
                showError('Escreva uma query SQL');
                return;
            }
            
            const btn = document.getElementById('executeBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Executando...';
            
            try {
                const response = await fetch(`${API_BASE}/execute`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sql, limit })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayResults(data.data, data.columns, data.rows_affected);
                } else {
                    showError(`Erro: ${data.error}`);
                }
            } catch (e) {
                showError(`Erro na conexão: ${e.message}`);
            } finally {
                btn.disabled = false;
                btn.textContent = '▶️ Executar';
            }
        }
        
        function displayResults(rows, columns, rowsAffected) {
            const content = document.getElementById('resultsContent');
            
            if (rows.length === 0) {
                content.innerHTML = '<div class="empty-state"><p>Nenhum resultado encontrado</p></div>';
                return;
            }
            
            let html = `<div class="success">✅ ${rowsAffected} linhas retornadas</div>`;
            html += '<table><thead><tr>';
            
            columns.forEach(col => {
                html += `<th>${col}</th>`;
            });
            
            html += '</tr></thead><tbody>';
            
            rows.forEach(row => {
                html += '<tr>';
                columns.forEach(col => {
                    const value = row[col] ?? '';
                    html += `<td>${escapeHtml(String(value))}</td>`;
                });
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            content.innerHTML = html;
        }
        
        function showError(message) {
            const content = document.getElementById('resultsContent');
            content.innerHTML = `<div class="error">${message}</div>`;
        }
        
        function clearEditor() {
            document.getElementById('sqlEditor').value = '';
            document.getElementById('resultsContent').innerHTML = '<div class="empty-state"><p>Execute uma query para ver resultados</p></div>';
        }
        
        function copyExample() {
            const exampleText = document.getElementById('exampleQuery').textContent;
            const editor = document.getElementById('sqlEditor');
            
            // Copia para o editor
            editor.value = exampleText;
            editor.focus();
            
            // Feedback visual no botão
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '✅ Copiado!';
            btn.style.background = '#10b981';
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.background = '#667eea';
            }, 2000);
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>

<?php
require VIEWPATH . '/footer.php';
?>
