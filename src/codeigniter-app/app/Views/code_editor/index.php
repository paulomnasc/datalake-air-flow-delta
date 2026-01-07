<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Code Editor - Datalake</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
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
            display: grid;
            grid-template-columns: 280px 1fr;
            height: calc(100vh - 160px);
        }
        
        .sidebar {
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
            padding: 20px;
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
        }
        
        .file-item {
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #475569;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .file-item:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .file-icon {
            font-size: 16px;
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
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>
                <span>💻</span>
                SQL Code Editor
            </h1>
            <div class="status-badge">
                <span class="status-dot"></span>
                <?php echo $duckdbStatus['status'] === 'healthy' ? 'DuckDB Online' : 'DuckDB Offline'; ?>
            </div>
        </header>
        
        <div class="editor-layout">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-section">
                    <h3>📁 Arquivos Parquet</h3>
                    <ul class="file-tree" id="fileTree">
                        <?php if (!empty($parquetFiles)): ?>
                            <?php foreach ($parquetFiles as $file): ?>
                                <li class="file-item" onclick="insertFilePath('<?php echo esc($file); ?>')">
                                    <span class="file-icon">📄</span>
                                    <span><?php echo basename($file); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="file-item" style="cursor: default; color: #94a3b8;">
                                <span>Nenhum arquivo encontrado</span>
                            </li>
                        <?php endif; ?>
                    </ul>
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
            </aside>
            
            <!-- Main Editor Area -->
            <main class="main-editor">
                <div class="toolbar">
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
                
                <div class="results-section">
                    <div id="results"></div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Monaco Editor -->
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
    
    <script>
        let editor;
        const userBucket = '<?php echo esc($userBucket ?? 'user-1'); ?>';
        
        // Configurar Monaco Editor
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' } });
        
        require(['vs/editor/editor.main'], function () {
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
            
            try {
                const response = await fetch('/code-editor/execute', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sql, limit })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayResults(result);
                } else {
                    showError(result.error || 'Erro ao executar query');
                }
            } catch (error) {
                showError('Erro de conexão: ' + error.message);
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
    </script>
</body>
</html>
