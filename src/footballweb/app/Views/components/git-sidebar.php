<?php
/**
 * Git Sidebar Component - Reutilizável
 * 
 * Parâmetros aceitos:
 * - $fileFilter: string - Extensão de arquivo permitida (ex: '.py', '.parquet', '.sql')
 * - $userBucket: string - Bucket do usuário
 */

$fileFilter = $fileFilter ?? '.sql';
$userBucket = $userBucket ?? 'lab01';
?>

<!-- Tab: Git with isomorphic-git -->
<style>
    #gitFileTree .tree-item.selected {
        background: #111827;
        border: 1px solid #334155;
        border-radius: 4px;
    }
    
    #gitFileTree .tree-item.drag-over {
        background: #1e3a5f;
        border: 2px dashed #667eea;
    }
    
    #gitFileTree .tree-item.dragging {
        opacity: 0.5;
    }
    
    #gitFileTree .tree-item {
        cursor: grab;
    }
    
    #gitFileTree .tree-item:active {
        cursor: grabbing;
    }
</style>
<div id="tab-git" class="sidebar-tab-content">
    <div class="sidebar-section">
        <h3>🔗 GitHub</h3>
        <div id="gitLoadingStatus" style="padding: 12px; background: #1e293b; border-radius: 6px; font-size: 11px; color: #94a3b8; margin-bottom: 12px; display: none;">
            ⏳ Carregando isomorphic-git...
        </div>
        
        <div id="gitNotConnected" style="padding: 16px;">
            <p style="font-size: 12px; color: #94a3b8; margin-bottom: 12px;">Conecte seu GitHub para versionar arquivos</p>
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
            <input type="text" id="repoURL" placeholder="Repo: user/repo-name" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 8px;">
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
                <input type="text" id="newFileName" placeholder="exemplo<?php echo htmlspecialchars($fileFilter); ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
                <button class="btn btn-primary" onclick="createNewGitFile()" style="width: 100%;">
                    ✨ Criar do Editor
                </button>
            </div>

            <!-- Seção: Criar Pasta -->
            <div style="margin-bottom: 12px;">
                <h3 style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">📁 Criar Pasta</h3>
                <input type="text" id="newFolderName" placeholder="nova-pasta" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
                <button class="btn btn-primary" onclick="createGitFolder()" style="width: 100%;">
                    📁 Criar Pasta
                </button>
                <small style="display: block; margin-top: 4px; color: #94a3b8;">Usa a pasta selecionada ou cria na raiz.</small>
            </div>

            <!-- Seção: Renomear -->
            <div style="margin-bottom: 12px;">
                <h3 style="font-size: 12px; color: #94a3b8; margin-bottom: 8px;">✏️ Renomear</h3>
                <input type="text" id="renameItemName" placeholder="novo-nome" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #475569; background: #1e293b; color: #e2e8f0; font-size: 12px; margin-bottom: 4px;">
                <button class="btn btn-secondary" onclick="renameGitEntry()" style="width: 100%;">
                    ✏️ Renomear Selecionado
                </button>
                <small id="renameTargetInfo" style="display: block; margin-top: 4px; color: #94a3b8;">Selecione um arquivo ou pasta.</small>
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

<script>
// Configuração do filtro de arquivo para esta sidebar
window.gitSidebarFileFilter = '<?php echo addslashes($fileFilter); ?>';

// Sobrescrever onGitFileSelected para emitir evento CustomEvent
if (typeof window.onGitFileSelected === 'undefined') {
    window.onGitFileSelected = function(fileNode) {
        console.log('📄 Arquivo selecionado na sidebar:', fileNode);
        
        // Filtrar por extensão
        if (window.gitSidebarFileFilter && !fileNode.fullPath.endsWith(window.gitSidebarFileFilter)) {
            alert(`❌ Apenas arquivos ${window.gitSidebarFileFilter} são permitidos nesta página`);
            return;
        }
        
        // Carregar conteúdo do arquivo
        loadGitFileContentAndEmit(fileNode);
    };
}

// Carregar conteúdo e emitir evento
async function loadGitFileContentAndEmit(fileNode) {
    try {
        const response = await fetch('/api/git-file-content', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userBucket: userBucket || '<?php echo htmlspecialchars($userBucket); ?>',
                filePath: fileNode.fullPath
            })
        });
        
        if (!response.ok) {
            throw new Error(`Erro ao carregar arquivo: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Emitir evento CustomEvent para a página consumir
            window.dispatchEvent(new CustomEvent('git-file-selected', {
                detail: {
                    filepath: fileNode.fullPath,
                    filename: fileNode.name,
                    content: data.content || '',
                    fileNode: fileNode
                }
            }));
            
            console.log('✅ Evento git-file-selected emitido:', fileNode.name);
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    } catch (error) {
        console.error('❌ Erro ao carregar arquivo Git:', error);
        alert(`❌ Erro ao carregar arquivo: ${error.message}`);
    }
}
</script>
