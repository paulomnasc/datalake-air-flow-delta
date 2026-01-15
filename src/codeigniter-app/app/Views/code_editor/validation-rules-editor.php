<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';

$userBucket = $userBucket ?? 'lab01';
?>

<!-- ================================================ -->
<!-- Git File Manager - Centralizado (reutilizável) -->
<!-- ================================================ -->
<script src="/assets/js/git-file-manager.js"></script>
<script>
    // Configurar para code-editor
    gitConfigKey = 'gitConfig';
    userBucket = '<?php echo $userBucket ?? 'lab01'; ?>';
</script>

<style>
    /* Layout com Sidebar */
    .validation-layout {
        display: flex;
        gap: 0;
        margin-top: 20px;
        position: relative;
        height: calc(100vh - 200px);
    }
    
    .validation-sidebar {
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
    
    .validation-sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        height: 100%;
        width: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1999;
        display: none;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    .validation-container {
        max-width: 1400px;
        margin: 20px auto;
        background: #1e293b;
        border-radius: 12px;
        padding: 24px;
        color: #e2e8f0;
        flex: 1;
    }
    
    .validation-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .validation-header h1 {
        margin: 0;
        font-size: 24px;
        color: white;
    }
    
    .sidebar-toggle-btn {
        background: #10b981;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }
    
    .sidebar-toggle-btn:hover {
        background: #059669;
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
    }
    
    .tree-children {
        display: none;
        margin-left: 12px;
    }
    
    .tree-children.expanded {
        display: block;
    }
    
    .btn {
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: #10b981;
        color: white;
    }
    
    .btn-primary:hover {
        background: #059669;
    }
    
    .btn-secondary {
        background: #475569;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #334155;
    }
    
    .btn-success {
        background: #f59e0b;
        color: white;
    }
    
    .btn-success:hover {
        background: #d97706;
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* ===== DEPLOY NOTIFICATION MODAL ===== */
    .deploy-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        z-index: 3000;
        align-items: center;
        justify-content: center;
    }
    
    .deploy-modal-overlay.active {
        display: flex;
    }
    
    .deploy-modal {
        background: #1e293b;
        border-radius: 12px;
        padding: 32px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.9);
        color: #e2e8f0;
        text-align: center;
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .deploy-modal.success {
        border: 2px solid #10b981;
    }
    
    .deploy-modal.error {
        border: 2px solid #ef4444;
    }
    
    .deploy-modal.loading {
        border: 2px solid #f59e0b;
    }
    
    .deploy-modal-icon {
        font-size: 48px;
        margin-bottom: 16px;
        animation: iconBounce 1s ease infinite;
    }
    
    .deploy-modal.success .deploy-modal-icon {
        animation: none;
    }
    
    .deploy-modal.error .deploy-modal-icon {
        animation: none;
    }
    
    @keyframes iconBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    
    .deploy-modal h2 {
        font-size: 24px;
        margin: 16px 0;
        font-weight: 600;
    }
    
    .deploy-modal-message {
        font-size: 14px;
        color: #94a3b8;
        margin: 16px 0;
        line-height: 1.6;
        white-space: pre-wrap;
        text-align: left;
        max-height: 200px;
        overflow-y: auto;
        background: #0f172a;
        padding: 12px;
        border-radius: 6px;
    }
    
    .deploy-modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        justify-content: center;
    }
    
    .deploy-modal-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .deploy-modal-btn.success {
        background: #10b981;
        color: white;
    }
    
    .deploy-modal-btn.success:hover {
        background: #059669;
    }
    
    .deploy-modal-btn.secondary {
        background: #475569;
        color: white;
    }
    
    .deploy-modal-btn.secondary:hover {
        background: #334155;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #e2e8f0;
        border-top: 3px solid #f59e0b;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .rules-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .rule-card {
        background: #0f172a;
        border: 2px solid #334155;
        border-radius: 8px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .rule-card:hover {
        border-color: #10b981;
        background: #1e293b;
    }
    
    .rule-card h3 {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #10b981;
    }
    
    .rule-card p {
        margin: 0;
        font-size: 12px;
        color: #94a3b8;
    }
    
    .editor-section {
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .editor-section h2 {
        margin: 0 0 16px 0;
        font-size: 18px;
        color: #e2e8f0;
    }
    
    .alert {
        padding: 8px;
        border-radius: 4px;
        margin-bottom: 8px;
        font-size: 12px;
    }
    
    .alert-success {
        background: #10b981;
        color: white;
    }
    
    .alert-danger {
        background: #dc2626;
        color: white;
    }
    
    #editor-container {
        height: 400px;
        border: 1px solid #334155;
        border-radius: 6px;
        margin-bottom: 16px;
    }
    
    .results-section {
        margin-top: 24px;
    }
</style>

<!-- Sidebar Git retrátil -->
<aside class="validation-sidebar" id="validationSidebar">
    <button class="sidebar-close-btn" onclick="toggleValidationSidebar()">×</button>
    
    <div class="sidebar-section">
        <h3>🔗 GitHub</h3>
        
        <div id="gitLoadingStatus" style="padding: 12px; background: #1e293b; border-radius: 6px; font-size: 11px; color: #94a3b8; margin-bottom: 12px; display: none;">
            ⏳ Carregando isomorphic-git...
        </div>
        
        <div id="gitNotConnected" style="padding: 16px;">
            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 12px;">Conecte seu GitHub para versionar validadores Python</p>
            <input type="text" id="githubUsername" placeholder="GitHub Username" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 8px;">
            <input type="password" id="githubToken" placeholder="Personal Access Token" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
            <p style="font-size: 10px; color: #64748b; margin-bottom: 8px; line-height: 1.4;">
                💡 Gere em: <a href="https://github.com/settings/tokens/new" target="_blank" style="color: #667eea; text-decoration: none;">GitHub Settings → Developer settings → Personal access tokens</a><br>
                Marque o scope: <strong style="color: #94a3b8;">repo</strong>
            </p>
            <input type="text" id="repoURL" placeholder="Repo: user/validators" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 8px;">
            <button class="btn btn-primary" onclick="connectGitHub()" style="width: 100%;">
                ✓ Conectar
            </button>
        </div>
        
        <div id="gitConnected" style="display: none;">
            <div id="repoInfo" style="padding: 10px; background: #1e293b; border-radius: 6px; font-size: 11px; margin-bottom: 12px; color: #cbd5e1;"></div>
            
            <!-- Mensagens de sucesso/erro -->
            <div id="git-success-message" class="alert alert-success" style="display:none; font-size: 12px; padding: 8px; margin-bottom: 8px;"></div>
            <div id="git-error-message" class="alert alert-danger" style="display:none; font-size: 12px; padding: 8px; margin-bottom: 8px;"></div>
            
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
                <input type="text" id="newFileName" placeholder="validador.py" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
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
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleValidationSidebar()"></div>

<div class="validation-layout">
    <div class="validation-container">
        <div class="validation-header">
            <h1>🛡️ Validações Customizadas - Medallion</h1>
            <button class="sidebar-toggle-btn" onclick="toggleValidationSidebar()">
                🔗 GitHub
            </button>
        </div>

        <div class="row">
            <!-- Coluna esquerda: Lista de regras -->
            <div class="col-md-4">
                <div class="editor-section">
                    <h2>📋 Regras Existentes</h2>
                    <div class="rules-list" id="rulesList">
                        <div class="rule-card" onclick="showRuleTemplate('empty')">
                            <h3>+ Nova Regra</h3>
                            <p>Criar nova regra de validação</p>
                        </div>
                    </div>
                </div>
                
                <!-- Templates -->
                <div class="editor-section">
                    <h2>📚 Templates Disponíveis</h2>
                    <div id="templatesList">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>

            <!-- Coluna direita: Editor -->
            <div class="col-md-8">
                <div class="editor-section">
                    <h2>✏️ Editor Python</h2>
                    <div id="editor-container"></div>
                    
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button class="btn btn-primary" onclick="testValidation()">
                            ▶️ Testar
                        </button>
                        <button class="btn btn-primary" onclick="saveValidation()">
                            💾 Salvar
                        </button>
                        <button class="btn btn-success" onclick="deployValidator()" title="Sincronizar para Airflow">
                            🚀 Implantar
                        </button>
                        <button class="btn btn-secondary" onclick="clearEditor()">
                            🗑️ Limpar
                        </button>
                    </div>
                </div>
                
                <div class="editor-section">
                    <h2>📊 Resultado do Teste</h2>
                    <div id="testResults" style="padding: 16px; background: #0f172a; border-radius: 6px; min-height: 100px; font-size: 12px; color: #94a3b8;">
                        Teste uma validação para ver os resultados aqui
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Deploy Notification Modal -->
<div class="deploy-modal-overlay" id="deployModalOverlay">
    <div class="deploy-modal" id="deployModal">
        <div class="deploy-modal-icon" id="deployModalIcon">⏳</div>
        <h2 id="deployModalTitle">Implantando...</h2>
        <div class="deploy-modal-message" id="deployModalMessage">Sincronizando arquivo para Airflow...</div>
        <div class="deploy-modal-buttons" id="deployModalButtons">
            <!-- Buttons dinamically added -->
        </div>
    </div>
</div>

<!-- Monaco Editor -->
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>

<script>
    let editor;
    // Reuse global userBucket from git-file-manager.js to avoid redeclaration errors
    userBucket = '<?php echo $userBucket; ?>';
    
    // ===== SIDEBAR TOGGLE =====
    function toggleValidationSidebar() {
        const sidebar = document.getElementById('validationSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
    
    // ===== MONACO EDITOR =====
    require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' } });
    
    require(['vs/editor/editor.main'], function () {
        editor = monaco.editor.create(document.getElementById('editor-container'), {
            value: `# Validador Customizado\n# Implemente a função: validate(df: DataFrame) -> DataFrame\n\ndef validate(df):\n    """Valide dados no medallion"""\n    # Sua lógica aqui\n    return df\n`,
            language: 'python',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            fontSize: 14,
            lineNumbers: 'on',
            roundedSelection: true,
            scrollBeyondLastLine: false,
            readOnly: false,
            cursorStyle: 'line',
            tabSize: 4,
            insertSpaces: true,
            formatOnPaste: true,
            formatOnType: true,
        });
        
        // Atalho Ctrl+Enter para testar
        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, testValidation);
        
            // Restaurar estado Git ao carregar editor
            restoreGitFromStorage('monaco-ready');
    });

        // Restaurar Git ao carregar a página (mesma lógica do code-editor)
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔍 DOMContentLoaded - restauração Git (validation)');
            restoreGitFromStorage('DOMContentLoaded');
            // Fallback curto se DOM atrasar
            let tries = 0;
            const intervalId = setInterval(() => {
                tries++;
                restoreGitFromStorage(`interval-${tries}`);
                if (tries >= 5) clearInterval(intervalId);
            }, 800);
            // Carregar listas específicas da página
            loadTemplates();
            loadRulesList();
        });

        window.addEventListener('load', function() {
            console.log('🔍 window.load - restauração Git (validation)');
            restoreGitFromStorage('window-load');
        });
    
    // ===== TEMPLATES =====
    const templates = {
        empty: { name: 'Regra Vazia', code: `def validate(df):\n    return df\n` },
        null_check: {
            name: 'Verificar Nulos',
            code: `def validate(df):\n    """Remove registros com valores nulos em colunas críticas"""\n    return df.dropna(subset=['critical_column'])\n`
        },
        duplicate_check: {
            name: 'Remover Duplicatas',
            code: `def validate(df):\n    """Remove registros duplicados"""\n    return df.drop_duplicates()\n`
        },
        type_check: {
            name: 'Validar Tipos',
            code: `def validate(df):\n    """Valida tipos de dados"""\n    try:\n        df['amount'] = df['amount'].astype('float64')\n        return df\n    except:\n        return df.iloc[0:0]  # Retorna vazio se falhar\n`
        }
    };
    
    function loadTemplates() {
        const list = document.getElementById('templatesList');
        list.innerHTML = '';
        Object.entries(templates).forEach(([key, tmpl]) => {
            const item = document.createElement('div');
            item.className = 'rule-card';
            item.onclick = () => showRuleTemplate(key);
            item.innerHTML = `<h3>${tmpl.name}</h3><p>Clique para usar este template</p>`;
            list.appendChild(item);
        });
    }
    
    function showRuleTemplate(templateKey) {
        const template = templates[templateKey];
        if (template && editor) {
            editor.setValue(template.code);
            editor.focus();
        }
    }
    
    function clearEditor() {
        if (editor) {
            editor.setValue('def validate(df):\n    return df\n');
        }
    }
    
    async function testValidation() {
        const code = editor?.getValue() || '';
        const resultsDiv = document.getElementById('testResults');
        
        if (!code.trim()) {
            resultsDiv.innerHTML = '<div class="alert alert-danger">❌ Editor vazio</div>';
            return;
        }
        
        // Validação básica de sintaxe Python
        resultsDiv.innerHTML = '<div style="color: #94a3b8;">⏳ Validando sintaxe...</div>';
        
        try {
            // Verificar se contém a função validate
            if (!code.includes('def validate')) {
                resultsDiv.innerHTML = '<div class="alert alert-danger">❌ Função "def validate(df)" não encontrada</div>';
                return;
            }
            
            // Verificar indentação básica
            const lines = code.split('\n');
            let hasError = false;
            let errorMsg = '';
            
            // Validações básicas
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                // Pular linhas vazias e comentários
                if (!line.trim() || line.trim().startsWith('#')) continue;
                
                // Verificar se há caracteres inválidos
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
            
            // Sucesso
            resultsDiv.innerHTML = `<div class="alert alert-success">✓ Validação OK!
            <br><small>
            • Função validate() encontrada
            <br>• Sintaxe básica válida
            <br>• Pronto para salvar e usar no Medallion
            </small></div>`;
            
        } catch (error) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">❌ Erro na validação: ${error.message}</div>`;
        }
    }
    
    async function saveValidation() {
        // Se temos arquivo aberto no Git, salvar lá
        if (currentGitFile && gitConfig) {
            await saveGitFile();
            return;
        }
        
        // Caso contrário, criar novo arquivo no Git
        const code = editor?.getValue() || '';
        
        if (!code.trim()) {
            alert('❌ Editor vazio');
            return;
        }
        
        if (!gitConfig) {
            alert('❌ Conecte GitHub primeiro');
            return;
        }
        
        // Solicitar nome do arquivo
        const fileName = prompt('Nome do arquivo (ex: validador.py):', 'validador.py');
        if (!fileName) return;
        
        try {
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
                throw new Error(error.error || 'Falha ao salvar');
            }
            
            showGitMessage(`✓ ${fileName} salvo com sucesso`, 'success');
            await loadGitFiles();
        } catch (error) {
            showGitMessage(`❌ Erro ao salvar: ${error.message}`, 'error');
        }
    }
    
    async function loadRulesList() {
        try {
            const response = await fetch(`/validation-rules/list?userBucket=${userBucket}`);
            const result = await response.json();
            
            const rulesList = document.getElementById('rulesList');
            rulesList.innerHTML = '<div class="rule-card" onclick="showRuleTemplate(\'empty\'"><h3>+ Nova Regra</h3><p>Criar nova regra</p></div>';
            
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
    
    function showGitMessage(msg, type) {
        const successDiv = document.getElementById('git-success-message');
        const errorDiv = document.getElementById('git-error-message');
        
        if (type === 'success') {
            successDiv.textContent = msg;
            successDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            setTimeout(() => { successDiv.style.display = 'none'; }, 3000);
        } else if (type === 'error') {
            errorDiv.textContent = msg;
            errorDiv.style.display = 'block';
            successDiv.style.display = 'none';
            setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
        }
    }
    
    /**
     * Deploy do validador para o Airflow
     * Sincroniza o arquivo atual do Git para /opt/airflow/dags/
     */
    async function deployValidator() {
        const code = editor?.getValue() || '';
        
        if (!code.trim()) {
            showDeployModal('error', '❌ Editor vazio', 'Escreva código no editor antes de fazer deploy');
            return;
        }
        
        // Validar currentGitFile
        if (!currentGitFile || typeof currentGitFile !== 'string' || !currentGitFile.trim()) {
            showDeployModal('error', '❌ Nenhum arquivo aberto', 'Abra ou crie um arquivo no Git primeiro');
            return;
        }
        
        // Obter nome do arquivo com segurança
        let filename = '';
        try {
            filename = currentGitFile.trim().split('/').pop();
            if (!filename) {
                throw new Error('Nome de arquivo inválido');
            }
        } catch (e) {
            showDeployModal('error', '❌ Erro ao processar arquivo', 'O nome do arquivo é inválido: ' + String(currentGitFile));
            return;
        }
        
        // Mostrar confirmar
        if (!confirm(`Sincronizar "${filename}" para Airflow?\n\nIsso copiará o arquivo para /opt/airflow/dags/ e reiniciará o detector de DAGs.`)) {
            return;
        }
        
        try {
            // Mostrar modal de loading
            showDeployModal('loading', '⏳ Implantando...', `Sincronizando "${filename}" para Airflow...`);
            
            // Desabilitar botão durante deploy
            const deployBtn = event.target;
            const originalText = deployBtn.innerHTML;
            deployBtn.disabled = true;
            deployBtn.innerHTML = '⏳ Implantando...';
            
            const response = await fetch('/api/validation-deploy', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    filename: filename
                })
            });
            
            const result = await response.json();
            
            // Reabilitar botão
            deployBtn.disabled = false;
            deployBtn.innerHTML = originalText;
            
            if (result.success) {
                showDeployModal(
                    'success',
                    '✅ Sucesso!',
                    `${result.message}\n\n${result.next_step}`,
                    filename
                );
            } else {
                showDeployModal(
                    'error',
                    '❌ Erro ao sincronizar',
                    `${result.error}\n\n${result.details || 'Verifique os logs para mais informações.'}`,
                    filename
                );
            }
            
        } catch (error) {
            console.error('Deploy error:', error);
            showDeployModal('error', '❌ Erro ao sincronizar', 'Erro: ' + error.message);
            const deployBtn = event.target;
            if (deployBtn) {
                deployBtn.disabled = false;
                deployBtn.innerHTML = '🚀 Implantar';
            }
        }
    }
    
    /**
     * Exibe modal de feedback de deploy
     */
    function showDeployModal(type, title, message, filename = null) {
        const overlay = document.getElementById('deployModalOverlay');
        const modal = document.getElementById('deployModal');
        const icon = document.getElementById('deployModalIcon');
        const titleEl = document.getElementById('deployModalTitle');
        const messageEl = document.getElementById('deployModalMessage');
        const buttonsEl = document.getElementById('deployModalButtons');
        
        // Remover classes de tipo anterior
        modal.classList.remove('success', 'error', 'loading');
        modal.classList.add(type);
        
        // Atualizar ícone
        if (type === 'loading') {
            icon.innerHTML = '<div class="loading-spinner"></div>';
        } else if (type === 'success') {
            icon.innerHTML = '✅';
        } else if (type === 'error') {
            icon.innerHTML = '❌';
        }
        
        // Atualizar título e mensagem
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Adicionar botões
        buttonsEl.innerHTML = '';
        
        if (type === 'loading') {
            // Sem botões durante loading
        } else if (type === 'success') {
            const btnOk = document.createElement('button');
            btnOk.className = 'deploy-modal-btn success';
            btnOk.textContent = '✓ Ok';
            btnOk.onclick = () => {
                overlay.classList.remove('active');
                // Se houver filename, recarregar arquivos do Git
                if (filename) {
                    setTimeout(() => loadGitFiles(), 500);
                }
            };
            buttonsEl.appendChild(btnOk);
        } else if (type === 'error') {
            const btnClose = document.createElement('button');
            btnClose.className = 'deploy-modal-btn secondary';
            btnClose.textContent = 'Fechar';
            btnClose.onclick = () => overlay.classList.remove('active');
            buttonsEl.appendChild(btnClose);
        }
        
        // Mostrar modal
        overlay.classList.add('active');
    }
    
    /**
     * Fechar modal ao clicar fora
     */
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('deployModalOverlay');
        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        }
    });

    
    // ===== GIT (isomorphic-git) - MIGRADO DE code-editor.php =====
    
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
            delete window.define;
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
                if (window.module?.exports && !window.LightningFS) {
                    window.LightningFS = window.module.exports;
                    console.log('✓ window.LightningFS atribuído');
                }
                window.module.exports = {};
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
                    if (window.module?.exports && !window.git) {
                        window.git = window.module.exports;
                        console.log('✓ window.git atribuído');
                    }
                    if (origDefine) window.define = origDefine;
                    createHttpClientImmediate();
                };
                document.head.appendChild(gitScript);
            };
            document.head.appendChild(lfsScript);
        }, 2000);
    })();

    // Função para criar HTTP client inline - chamada após git estar pronto
    function createHttpClientImmediate() {
        console.log('🔧 Criando HTTP client inline...');
        
        if (!window.git) {
            console.error('❌ window.git não está disponível');
            return;
        }
        
        window.git.http = {
            request: async ({ url, method = 'GET', headers = {}, body } = {}) => {
                if (!url) throw new Error('HTTP client: url não informado');
                console.log(`📤 HTTP ${method} ${url.substring(0, 100)}`);
                
                let authHeader = headers['authorization'] || headers['Authorization'];
                if (!authHeader && window.gitConfig && window.gitConfig.token) {
                    const ghUsername = window.gitConfig.owner || window.gitConfig.username;
                    authHeader = 'Basic ' + btoa(`${ghUsername}:${window.gitConfig.token}`);
                }

                const mergedHeaders = {
                    'User-Agent': 'isomorphic-git/1.25.7',
                    ...headers,
                    ...(authHeader ? { authorization: authHeader } : {})
                };
                
                if (mergedHeaders.Authorization && !mergedHeaders.authorization) {
                    mergedHeaders.authorization = mergedHeaders.Authorization;
                    delete mergedHeaders.Authorization;
                }

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
                    const res = await fetch(url, fetchOpts);
                    const resHeaders = {};
                    for (const [key, value] of res.headers.entries()) {
                        resHeaders[key.toLowerCase()] = value;
                    }
                    const bodyBuffer = await res.arrayBuffer();
                    const bodyUint8 = new Uint8Array(bodyBuffer);
                    
                    console.log(`📥 HTTP ${res.status} ${res.statusText}`);
                    
                    if (res.status >= 400) {
                        const bodyText = new TextDecoder().decode(bodyUint8);
                        console.error(`❌ ERRO ${res.status}:`, bodyText.substring(0, 300));
                    }
                    
                    const validStatus = typeof res.status === 'number' ? res.status : 500;
                    const validStatusText = res.statusText || 'Unknown';
                    
                    return {
                        url,
                        method,
                        headers: resHeaders,
                        body: [bodyUint8],
                        status: validStatus,
                        statusText: validStatusText
                    };
                } catch (err) {
                    console.error(`❌ Erro FETCH:`, err.message);
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
        const el = document.getElementById('gitLoadingStatus');
        if (el) el.style.display = 'none';
    }

    // Funções Git são importadas do git-file-manager.js centralizado
    // connectGitHub, disconnectGitHub, loadGitFiles, renderGitFileTree, etc estão lá
    
    let currentGitFile = null;
    
    function renderGitTree(node, container, level = 0) {
        const entries = Object.values(node.children).sort((a, b) => {
            if (a.isFile !== b.isFile) return a.isFile ? 1 : -1;
            return a.name.localeCompare(b.name);
        });
        
        entries.forEach(entry => {
            const item = document.createElement('div');
            if (entry.isFile) {
                item.className = 'tree-item file';
                item.innerHTML = `<span class="icon">📄</span><span style="overflow: hidden; text-overflow: ellipsis;">${entry.name}</span>`;
                item.onclick = () => loadGitFileContent(entry.fileData);
            } else {
                const hasChildren = Object.keys(entry.children).length > 0;
                const childrenContainer = document.createElement('div');
                childrenContainer.className = `tree-children ${entry.expanded ? 'expanded' : ''}`;
                
                item.className = 'tree-item folder';
                item.innerHTML = `<span style="margin-right:4px;">${entry.expanded ? '▼' : '▶'}</span><span class="icon">${entry.expanded ? '📂' : '📁'}</span><span>${entry.name}</span>`;
                
                if (hasChildren) {
                    item.onclick = (e) => {
                        e.stopPropagation();
                        const isExp = childrenContainer.classList.toggle('expanded');
                        entry.expanded = isExp;
                        item.querySelector('span').textContent = isExp ? '▼' : '▶';
                        item.querySelector('.icon').textContent = isExp ? '📂' : '📁';
                    };
                }
                
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
    
    async function loadGitFileContent(file) {
        if (!gitConfig) {
            alert('Repositório não conectado');
            return;
        }
        
        try {
            const response = await fetch(`/api/git-file-content?userBucket=${encodeURIComponent(userBucket)}&owner=${gitConfig.owner}&repo=${gitConfig.repo}&file=${encodeURIComponent(file.path)}`);
            if (!response.ok) throw new Error('Falha ao carregar');
            const result = await response.json();
            
            if (editor) {
                monaco.editor.setModelLanguage(editor.getModel(), 'python');
                editor.setValue(result.content || '');
                currentGitFile = file.path;
                document.getElementById('currentFileInfo').innerHTML = `📄 ${file.name}`;
                console.log(`✅ Arquivo carregado: ${file.name}`);
            }
        } catch (error) {
            alert('Erro: ' + error.message);
        }
    }
    
    async function saveGitFile() {
        if (!gitConfig || !currentGitFile) {
            alert('Nenhum arquivo aberto');
            return;
        }
        if (!editor) {
            alert('Editor não inicializado');
            return;
        }
        
        const content = editor.getValue();
        const status = document.getElementById('gitStatus');
        
        try {
            status.innerText = 'Salvando...';
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
            
            if (!response.ok) throw new Error('Falha');
            status.innerText = '';
            showGitMessage(`✓ ${currentGitFile.name} salvo`, 'success');
        } catch (error) {
            status.innerText = '';
            showGitMessage(`❌ Erro: ${error.message}`, 'error');
        }
    }
    
    async function createNewGitFile() {
        if (!gitConfig) {
            alert('Conecte GitHub primeiro');
            return;
        }
        
        const fileName = document.getElementById('newFileName').value.trim();
        if (!fileName) {
            alert('Informe nome do arquivo');
            return;
        }
        if (!editor) {
            alert('Editor não inicializado');
            return;
        }
        
        const content = editor.getValue();
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
            
            if (!response.ok) throw new Error('Falha');
            status.innerText = '';
            showGitMessage(`✓ ${fileName} criado`, 'success');
            document.getElementById('newFileName').value = '';
            await loadGitFiles();
        } catch (error) {
            status.innerText = '';
            showGitMessage(`❌ Erro: ${error.message}`, 'error');
        }
    }
    
    async function deleteGitFile() {
        if (!gitConfig || !currentGitFile) {
            alert('Nenhum arquivo para deletar');
            return;
        }
        
        if (!confirm(`Deletar "${currentGitFile.name}"?`)) return;
        
        const status = document.getElementById('gitStatus');
        
        try {
            status.innerText = `Deletando...`;
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
            
            if (!response.ok) throw new Error('Falha');
            status.innerText = '';
            showGitMessage(`✓ Deletado`, 'success');
            if (editor) editor.setValue('');
            document.getElementById('currentFileInfo').innerHTML = 'Nenhum arquivo';
            currentGitFile = null;
            await loadGitFiles();
        } catch (error) {
            status.innerText = '';
            showGitMessage(`❌ Erro: ${error.message}`, 'error');
        }
    }
    
    async function gitAddCommitPush() {
        if (!gitConfig) {
            alert('Conecte GitHub primeiro');
            return;
        }
        
        const commitMsg = document.getElementById('commitMsg').value.trim();
        if (!commitMsg) {
            alert('Informe mensagem de commit');
            return;
        }
        
        const status = document.getElementById('gitStatus');
        status.innerText = 'Preparando push...';
        
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
                throw new Error(error.error || 'Push failed');
            }
            
            const result = await response.json();
            status.innerText = `✓ Push OK! ${result.downloadedFiles} arquivos sincronizados`;
            document.getElementById('commitMsg').value = '';
            setTimeout(() => { status.innerText = ''; }, 5000);
        } catch (error) {
            status.innerText = 'Erro: ' + error.message;
        }
    }
    
</script>

<?php require VIEWPATH . '/footer.php'; ?>
