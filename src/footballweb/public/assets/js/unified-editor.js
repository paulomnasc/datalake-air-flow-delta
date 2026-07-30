/**
 * Unified Code Editor
 * Gerencia dois editores Monaco (SQL + Python) em uma única view
 * Integra Git, deploy e validações
 */

// ===== GLOBAL STATE =====
let currentTab = 'sql';  // sql | validation
let editorSQL = null;
let editorPython = null;
let currentGitFile = null;
let gitConfig = null;

const userBucket = document.currentScript?.getAttribute('data-bucket') || 'lab01';

// Editor configuration
const editorConfig = {
    sql: {
        language: 'sql',
        containerId: 'editor-container-sql',
        editor: null
    },
    validation: {
        language: 'python',
        containerId: 'editor-container-validation',
        editor: null
    }
};

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', () => {
    console.log('🔧 Unified Editor DOMContentLoaded');
    
    // Initialize SQL Editor (eager load)
    initSQLEditor();
    
    // Setup tab switching
    setupTabSwitching();
    
    // Listen to Git file selection events
    window.addEventListener('git-file-selected', onGitFileSelected);
    
    // Restore Git config
    restoreGitFromStorage('DOMContentLoaded-unified');
    
    // Show SQL header by default
    showHeader('sql');
});

// ===== TAB SWITCHING =====
function switchMainTab(tabName) {
    if (currentTab === tabName) return;
    
    // Hide all tabs
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    document.querySelectorAll('.editor-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    const tabPanel = document.getElementById(`${tabName}-panel`);
    const tabBtn = document.querySelector(`[data-tab="${tabName}"]`);
    
    if (tabPanel) tabPanel.classList.add('active');
    if (tabBtn) tabBtn.classList.add('active');
    
    currentTab = tabName;
    
    // Switch header
    showHeader(tabName);
    
    // Lazy load Python editor
    if (tabName === 'validation' && !editorPython) {
        initPythonEditor();
    }
    
    // Trigger resize for Monaco
    setTimeout(() => {
        if (editorSQL) editorSQL.layout();
        if (editorPython) editorPython.layout();
    }, 100);
    
    console.log(`📑 Switched to ${tabName} tab`);
}

function setupTabSwitching() {
    document.querySelectorAll('.editor-tab').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const tab = e.target.getAttribute('data-tab');
            if (tab) switchMainTab(tab);
        });
    });
}

function showHeader(tabName) {
    document.getElementById('sqlHeader').style.display = tabName === 'sql' ? 'block' : 'none';
    document.getElementById('validationHeader').style.display = tabName === 'validation' ? 'block' : 'none';
}

// ===== EDITOR INITIALIZATION =====
function initSQLEditor() {
    const container = document.getElementById('editor-container-sql');
    if (!container) {
        console.error('❌ SQL editor container not found');
        return;
    }
    
    require(['vs/editor/editor.main'], () => {
        editorSQL = monaco.editor.create(container, {
            value: '-- SELECT * FROM read_parquet(\'s3://your-bucket/data.parquet\') LIMIT 100;',
            language: 'sql',
            theme: 'vs',
            fontSize: 14,
            fontFamily: 'Fira Code, monospace',
            minimap: { enabled: true },
            wordWrap: 'on',
            autoIndent: 'keep',
            formatOnPaste: true,
            tabSize: 4,
            insertSpaces: true,
            automaticLayout: true
        });
        
        console.log('✅ SQL Editor initialized');
        
        // Initial empty state
        document.getElementById('results').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">✨</div>
                <p>Execute uma query para ver os resultados</p>
            </div>
        `;
    });
}

function initPythonEditor() {
    const container = document.getElementById('editor-container-validation');
    if (!container) {
        console.error('❌ Python editor container not found');
        return;
    }
    
    require(['vs/editor/editor.main'], () => {
        editorPython = monaco.editor.create(container, {
            value: 'def validate(df):\n    """Validação customizada"""\n    return df',
            language: 'python',
            theme: 'vs',
            fontSize: 14,
            fontFamily: 'Fira Code, monospace',
            minimap: { enabled: true },
            wordWrap: 'on',
            autoIndent: 'keep',
            formatOnPaste: true,
            tabSize: 4,
            insertSpaces: true,
            automaticLayout: true
        });
        
        console.log('✅ Python Editor initialized');
        
        // Load rules list
        loadRulesList();
    });
}

// ===== GIT INTEGRATION =====
function onGitFileSelected(e) {
    const { filepath, content } = e.detail;
    
    console.log(`📂 Git file selected: ${filepath}`);
    
    // Determine which editor based on file extension
    if (filepath.endsWith('.py')) {
        switchMainTab('validation');
        if (!editorPython) initPythonEditor();
        setTimeout(() => {
            if (editorPython) {
                editorPython.setValue(content);
                currentGitFile = filepath;
                document.getElementById('currentFileInfo').innerHTML = `📄 ${filepath.split('/').pop()}`;
            }
        }, 100);
    } else if (filepath.endsWith('.sql') || filepath.endsWith('.parquet')) {
        switchMainTab('sql');
        if (editorSQL) {
            editorSQL.setValue(content);
            currentGitFile = filepath;
        }
    }
}

function toggleEditorSidebar() {
    const sidebar = document.getElementById('editorSidebar');
    const overlay = document.getElementById('sidebarOverlayBg');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// ===== SQL OPERATIONS =====
async function executeQuery() {
    if (!editorSQL) return;
    
    const query = editorSQL.getValue().trim();
    if (!query) {
        document.getElementById('results').innerHTML = `<div class="error-message">❌ Query vazia</div>`;
        return;
    }
    
    const limit = parseInt(document.getElementById('limitInput').value) || 1000;
    const resultsDiv = document.getElementById('results');
    
    resultsDiv.innerHTML = '<div class="loading"><div class="spinner"></div> Executando query...</div>';
    
    try {
        const response = await fetch('/api/query-sql', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userBucket,
                query,
                limit
            })
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Query failed');
        }
        
        const result = await response.json();
        
        if (result.error) {
            resultsDiv.innerHTML = `<div class="error-message">❌ ${result.error}</div>`;
            document.getElementById('downloadCsvBtn').disabled = true;
            return;
        }
        
        // Display results table
        if (result.data && result.data.length > 0) {
            const columns = Object.keys(result.data[0]);
            let html = `<div class="results-header">
                <h3>📊 Resultados</h3>
                <div class="results-stats">${result.data.length} linha(s)</div>
            </div>`;
            html += '<table class="results-table"><thead><tr>';
            columns.forEach(col => {
                html += `<th>${col}</th>`;
            });
            html += '</tr></thead><tbody>';
            result.data.forEach(row => {
                html += '<tr>';
                columns.forEach(col => {
                    html += `<td>${row[col] !== null ? row[col] : '—'}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            resultsDiv.innerHTML = html;
            
            // Enable download
            document.getElementById('downloadCsvBtn').disabled = false;
            window.lastQueryData = result.data;
        } else {
            resultsDiv.innerHTML = `<div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>Nenhum resultado</p>
            </div>`;
            document.getElementById('downloadCsvBtn').disabled = true;
        }
    } catch (error) {
        resultsDiv.innerHTML = `<div class="error-message">❌ ${error.message}</div>`;
        console.error('Query error:', error);
    }
}

function formatSQL() {
    if (!editorSQL) return;
    editorSQL.getAction('editor.action.formatDocument').run();
}

function downloadCSV() {
    if (!window.lastQueryData) return;
    
    const data = window.lastQueryData;
    if (data.length === 0) return;
    
    const columns = Object.keys(data[0]);
    const csv = [
        columns.map(c => `"${c}"`).join(','),
        ...data.map(row => columns.map(col => {
            const val = row[col];
            if (val === null) return '';
            if (typeof val === 'string') return `"${val.replace(/"/g, '""')}"`;
            return val;
        }).join(','))
    ].join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `query-results-${Date.now()}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    console.log(`✅ CSV baixado: query-results-${Date.now()}.csv (${data.length} linhas)`);
}

function clearEditor(tab) {
    if (tab === 'sql' && editorSQL) {
        editorSQL.setValue('');
    } else if (tab === 'validation' && editorPython) {
        editorPython.setValue('');
    }
}

// ===== VALIDATION OPERATIONS =====
async function testValidation() {
    if (!editorPython) return;
    
    const code = editorPython.getValue();
    const resultsDiv = document.getElementById('testResults');
    
    if (!code.trim()) {
        resultsDiv.innerHTML = '❌ Editor vazio';
        return;
    }
    
    try {
        // Basic validation
        if (!code.includes('def validate')) {
            resultsDiv.innerHTML = '<div class="alert alert-danger">❌ Função "def validate(df)" não encontrada</div>';
            return;
        }
        
        const lines = code.split('\n');
        let hasError = false;
        let errorMsg = '';
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            if (!line.trim() || line.trim().startsWith('#')) continue;
            
            if (line.includes('<<') || line.includes('>>')) {
                hasError = true;
                errorMsg = `Caracteres inválidos na linha ${i + 1}`;
                break;
            }
        }
        
        if (hasError) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">❌ ${errorMsg}</div>`;
            return;
        }
        
        resultsDiv.innerHTML = `<div class="alert alert-success">✓ Validação OK!<br><small>
            • Função validate() encontrada<br>
            • Sintaxe básica válida<br>
            • Pronto para salvar e usar no Medallion
        </small></div>`;
        
    } catch (error) {
        resultsDiv.innerHTML = `<div class="alert alert-danger">❌ Erro: ${error.message}</div>`;
    }
}

async function saveValidation() {
    const code = editorPython?.getValue() || '';
    
    if (!code.trim()) {
        alert('❌ Editor vazio');
        return;
    }
    
    if (!gitConfig) {
        alert('❌ Conecte GitHub primeiro');
        return;
    }
    
    const fileName = prompt('Nome do arquivo (ex: validador.py):', 'validador.py');
    if (!fileName) return;
    
    try {
        const response = await fetch('/api/git-file-save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userBucket,
                owner: gitConfig.owner,
                repo: gitConfig.repo,
                file: fileName,
                content: code
            })
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Falha ao salvar');
        }
        
        alert(`✓ ${fileName} salvo com sucesso`);
        await loadGitFiles();
    } catch (error) {
        alert(`❌ Erro ao salvar: ${error.message}`);
    }
}

async function deployValidator() {
    const code = editorPython?.getValue() || '';
    
    if (!code.trim()) {
        showDeployModal('error', '❌ Editor vazio', 'Escreva código no editor antes de fazer deploy');
        return;
    }
    
    const fileInfoElement = document.getElementById('currentFileInfo');
    const fileInfoText = fileInfoElement?.innerHTML || '';
    
    if (!fileInfoText || fileInfoText.includes('Nenhum')) {
        showDeployModal('error', '❌ Nenhum arquivo aberto', 'Abra ou crie um arquivo no Git primeiro');
        return;
    }
    
    let filename = '';
    try {
        filename = fileInfoText.replace(/📄\s*/g, '').trim();
        if (!filename) throw new Error('Nome inválido');
    } catch (e) {
        showDeployModal('error', '❌ Erro ao processar arquivo', 'Não foi possível obter o nome do arquivo');
        return;
    }
    
    if (!confirm(`Sincronizar "${filename}" para Airflow?\n\nIsso copiará o arquivo para /opt/airflow/dags/ e reiniciará o detector de DAGs.`)) {
        return;
    }
    
    try {
        showDeployModal('loading', '⏳ Implantando...', `Sincronizando "${filename}" para Airflow...`);
        
        const response = await fetch('/api/validation-deploy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename,
                content: code
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showDeployModal('success', '✅ Sucesso!', `${result.message}\n\n${result.next_step}`);
        } else {
            showDeployModal('error', '❌ Erro', result.message || 'Deploy falhou');
        }
    } catch (error) {
        showDeployModal('error', '❌ Erro', error.message);
    }
}

function showDeployModal(type, title, message) {
    const overlay = document.getElementById('deployModalOverlay');
    const modal = document.getElementById('deployModal');
    const icon = document.getElementById('deployModalIcon');
    const titleEl = document.getElementById('deployModalTitle');
    const messageEl = document.getElementById('deployModalMessage');
    const buttonsEl = document.getElementById('deployModalButtons');
    
    // Clear modal classes
    modal.className = 'deploy-modal';
    modal.classList.add(type);
    
    // Update content
    icon.textContent = type === 'success' ? '✅' : type === 'error' ? '❌' : '⏳';
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // Setup buttons
    buttonsEl.innerHTML = '';
    if (type !== 'loading') {
        const btn = document.createElement('button');
        btn.className = `deploy-modal-btn ${type === 'success' ? 'success' : 'secondary'}`;
        btn.textContent = 'Fechar';
        btn.onclick = () => overlay.classList.remove('active');
        buttonsEl.appendChild(btn);
    }
    
    // Show modal
    overlay.classList.add('active');
    
    // Auto-close success after 5s
    if (type === 'success') {
        setTimeout(() => overlay.classList.remove('active'), 5000);
    }
}

// ===== VALIDATION RULES =====
async function loadRulesList() {
    try {
        const response = await fetch(`/validation-rules/list?userBucket=${userBucket}`);
        const result = await response.json();
        
        const rulesList = document.getElementById('rulesList');
        rulesList.innerHTML = '<div class="rule-card" onclick="showRuleTemplate(\'empty\')"><h3>+ Nova Regra</h3><p>Criar nova regra</p></div>';
        
        if (result.rules && result.rules.length > 0) {
            result.rules.forEach(rule => {
                const card = document.createElement('div');
                card.className = 'rule-card';
                card.onclick = () => showRuleTemplate(rule.name);
                card.innerHTML = `<h3>${rule.name}</h3><p>${rule.description || 'Regra customizada'}</p>`;
                rulesList.appendChild(card);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar regras:', error);
    }
}

function showRuleTemplate(templateName) {
    if (!editorPython) initPythonEditor();
    
    const templates = {
        empty: 'def validate(df):\n    """Validação customizada"""\n    return df\n',
        nullCheck: 'def validate(df):\n    """Validar campos nulos"""\n    return df[df.isnull().sum() == 0]\n',
        typeCheck: 'def validate(df):\n    """Validar tipos de dados"""\n    # Implementar validação\n    return df\n',
        rangeCheck: 'def validate(df):\n    """Validar range de valores"""\n    # Implementar validação\n    return df\n'
    };
    
    const code = templates[templateName] || templates.empty;
    editorPython.setValue(code);
    switchMainTab('validation');
}

// ===== SIDEBAR & GIT =====
function switchSidebarTab(tabName) {
    document.querySelectorAll('.sidebar-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.sidebar-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`tab-${tabName}`).classList.add('active');
}

// Stub: Git functions come from git-file-manager.js global scope
function restoreGitFromStorage(trigger) {
    // This will be called from git-file-manager.js
    // Just log for debugging
    console.log(`🔍 restoreGitFromStorage(${trigger}) called from unified-editor`);
}

function loadGitFiles() {
    // This is defined in git-file-manager.js
    if (window.loadGitFiles) {
        window.loadGitFiles();
    }
}

// ===== UTILITY =====
window.addEventListener('resize', () => {
    if (editorSQL) editorSQL.layout();
    if (editorPython) editorPython.layout();
});

console.log('✅ unified-editor.js carregado');
