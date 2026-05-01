<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';

$userBucket = $userBucket ?? 'lab01';
$fileFilter = '.py'; // Testar com .py
?>

<!-- Git File Manager - Centralizado -->
<script src="/assets/js/git-file-manager.js"></script>
<script>
    gitConfigKey = 'gitConfig';
    userBucket = '<?php echo $userBucket; ?>';
</script>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #0f172a;
        color: #e2e8f0;
    }
    
    .test-container {
        display: flex;
        height: 100vh;
    }
    
    .test-sidebar {
        width: 320px;
        background: #1e293b;
        border-right: 1px solid #334155;
        overflow-y: auto;
        flex-shrink: 0;
    }
    
    .test-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: #0f172a;
    }
    
    .test-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        text-align: center;
    }
    
    .test-header h1 {
        font-size: 24px;
        margin-bottom: 8px;
    }
    
    .test-header p {
        font-size: 14px;
        opacity: 0.9;
    }
    
    .test-info {
        background: #1e293b;
        padding: 16px;
        border-bottom: 1px solid #334155;
    }
    
    .test-info .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 13px;
    }
    
    .test-info .label {
        color: #94a3b8;
        font-weight: 600;
    }
    
    .test-info .value {
        color: #e2e8f0;
        font-family: 'Monaco', 'Courier New', monospace;
    }
    
    .test-editor {
        flex: 1;
        background: #0f172a;
        padding: 20px;
        overflow: auto;
    }
    
    .editor-placeholder {
        background: #1e293b;
        border: 2px dashed #475569;
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        color: #64748b;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    
    .editor-content {
        background: #1e293b;
        border-radius: 8px;
        padding: 20px;
        font-family: 'Monaco', 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
        color: #e2e8f0;
        white-space: pre-wrap;
        word-wrap: break-word;
        max-height: 100%;
        overflow: auto;
    }
    
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: #667eea;
        color: white;
    }
    
    .btn-primary:hover {
        background: #5568d3;
    }
    
    .btn-secondary {
        background: #e2e8f0;
        color: #475569;
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
    
    .alert {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 12px;
        font-size: 13px;
    }
    
    .alert-success {
        background: #10b981;
        color: white;
    }
    
    .alert-danger {
        background: #ef4444;
        color: white;
    }
</style>

<div class="test-container">
    <!-- Sidebar com componente Git -->
    <aside class="test-sidebar">
        <?php 
        // Incluir componente com filtro .py
        include VIEWPATH . '/components/git-sidebar.php'; 
        ?>
    </aside>
    
    <!-- Área principal de teste -->
    <main class="test-main">
        <div class="test-header">
            <h1>🧪 Teste: Git Sidebar Component</h1>
            <p>Página de teste isolada para validar o componente reutilizável</p>
        </div>
        
        <div class="test-info">
            <div class="info-row">
                <span class="label">Filtro de Arquivo:</span>
                <span class="value"><?php echo htmlspecialchars($fileFilter); ?></span>
            </div>
            <div class="info-row">
                <span class="label">User Bucket:</span>
                <span class="value"><?php echo htmlspecialchars($userBucket); ?></span>
            </div>
            <div class="info-row">
                <span class="label">localStorage Key:</span>
                <span class="value">gitConfig</span>
            </div>
            <div class="info-row">
                <span class="label">Status do Evento:</span>
                <span class="value" id="eventStatus">Aguardando seleção...</span>
            </div>
        </div>
        
        <div class="test-editor" id="editorArea">
            <div class="editor-placeholder">
                <div style="font-size: 48px; margin-bottom: 16px;">📄</div>
                <h2 style="font-size: 18px; margin-bottom: 8px; color: #94a3b8;">Nenhum arquivo selecionado</h2>
                <p style="font-size: 14px;">Conecte ao GitHub na sidebar e selecione um arquivo <?php echo htmlspecialchars($fileFilter); ?></p>
            </div>
        </div>
    </main>
</div>

<script>
console.log('🧪 Página de teste carregada');
console.log('📂 Filtro de arquivo:', window.gitSidebarFileFilter);

// Escutar evento de seleção de arquivo
window.addEventListener('git-file-selected', (e) => {
    console.log('✅ Evento git-file-selected recebido:', e.detail);
    
    const { filepath, filename, content } = e.detail;
    
    // Atualizar status
    document.getElementById('eventStatus').textContent = `✅ ${filename} carregado`;
    document.getElementById('eventStatus').style.color = '#10b981';
    
    // Mostrar conteúdo no editor
    const editorArea = document.getElementById('editorArea');
    editorArea.innerHTML = `
        <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 16px; color: #e2e8f0; margin-bottom: 4px;">📄 ${filename}</h3>
                <p style="font-size: 12px; color: #64748b;">${filepath}</p>
            </div>
            <button class="btn btn-secondary" onclick="clearEditor()">🗑️ Limpar</button>
        </div>
        <div class="editor-content">${escapeHtml(content)}</div>
    `;
    
    // Log para debug
    console.log('📝 Conteúdo carregado:', content.substring(0, 200) + '...');
});

// Limpar editor
function clearEditor() {
    document.getElementById('editorArea').innerHTML = `
        <div class="editor-placeholder">
            <div style="font-size: 48px; margin-bottom: 16px;">📄</div>
            <h2 style="font-size: 18px; margin-bottom: 8px; color: #94a3b8;">Nenhum arquivo selecionado</h2>
            <p style="font-size: 14px;">Conecte ao GitHub na sidebar e selecione um arquivo <?php echo htmlspecialchars($fileFilter); ?></p>
        </div>
    `;
    document.getElementById('eventStatus').textContent = 'Aguardando seleção...';
    document.getElementById('eventStatus').style.color = '#e2e8f0';
}

// Escapar HTML para exibição segura
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Log de debug
console.log('🎯 Listeners configurados');
console.log('📡 Aguardando evento: git-file-selected');
</script>

<?php require VIEWPATH . '/footer.php'; ?>
