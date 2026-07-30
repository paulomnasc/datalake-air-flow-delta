/**
 * Multi-Upload Functionality
 * Gerenciamento de upload múltiplo com drag & drop
 * Versão: 2.0 - Suporte a pastas
 */

console.log('🔧 Multi-upload.js carregado - Versão 2.0 com suporte a pastas');

// Controle de arquivos selecionados (GLOBAL)
window.selectedFiles = [];

// Inicialização do Drag & Drop
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('multiple_files');
    const fileList = document.getElementById('file-list');
    
    // Verificar se os elementos existem na página
    if (!dropZone || !fileInput || !fileList) {
        return; // Página não tem upload múltiplo
    }

    // Prevenir comportamento padrão
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight no drag over
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });

    // Handle drop
    dropZone.addEventListener('drop', handleDrop, false);
    fileInput.addEventListener('change', handleFileSelect, false);
    
    // Click na área de drop ativa o input
    dropZone.addEventListener('click', function(e) {
        console.log('🖱️ Clique na área de drop detectado');
        console.log('📂 Atributos do input:', {
            webkitdirectory: fileInput.hasAttribute('webkitdirectory'),
            directory: fileInput.hasAttribute('directory'),
            multiple: fileInput.hasAttribute('multiple'),
            accept: fileInput.getAttribute('accept')
        });
        fileInput.click();
    });

    async function handleDrop(e) {
        console.log('📥 Drop detectado');
        const dt = e.dataTransfer;
        const items = dt.items;
        
        // Verificar se está no modo de seleção de pasta
        const selectFolderCheckbox = document.getElementById('select_folder');
        const isFolderMode = selectFolderCheckbox && selectFolderCheckbox.checked;
        
        console.log('📂 Modo pasta ativo:', isFolderMode);
        console.log('📋 Items no drop:', items ? items.length : 'N/A');
        console.log('📄 Files no drop:', dt.files ? dt.files.length : 'N/A');
        
        if (isFolderMode && items) {
            // Mostrar loading
            fileList.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Processando pasta e lendo arquivos...</div>';
            
            try {
                console.log('🔄 Iniciando processamento de items...');
                // Modo pasta: processar items com getAsEntry
                const files = await getAllFilesFromItems(items);
                console.log('✅ Arquivos encontrados:', files.length);
                handleFiles(files);
            } catch (error) {
                console.error('❌ Erro ao processar pasta:', error);
                alert('❌ Erro ao processar a pasta. Tente novamente.');
                fileList.innerHTML = '';
            }
        } else {
            // Modo arquivos: usar dt.files diretamente
            console.log('📁 Usando modo de arquivos individuais');
            const files = dt.files;
            handleFiles(files);
        }
    }
    
    async function getAllFilesFromItems(items) {
        const files = [];
        
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            
            if (item.kind === 'file') {
                const entry = item.webkitGetAsEntry();
                
                if (entry) {
                    if (entry.isDirectory) {
                        // É uma pasta
                        const dirFiles = await readDirectory(entry);
                        files.push(...dirFiles);
                    } else {
                        // É um arquivo
                        const file = item.getAsFile();
                        if (file) files.push(file);
                    }
                }
            }
        }
        
        return files;
    }
    
    function readDirectory(directoryEntry) {
        return new Promise((resolve, reject) => {
            const dirReader = directoryEntry.createReader();
            const allFiles = [];
            
            function readEntries() {
                dirReader.readEntries(async (entries) => {
                    if (entries.length === 0) {
                        resolve(allFiles);
                        return;
                    }
                    
                    for (const entry of entries) {
                        if (entry.isFile) {
                            const file = await getFile(entry);
                            // Filtrar apenas CSV e JSON
                            if (file && (file.name.endsWith('.csv') || file.name.endsWith('.json'))) {
                                allFiles.push(file);
                            }
                        } else if (entry.isDirectory) {
                            // Recursivo para subpastas
                            const subFiles = await readDirectory(entry);
                            allFiles.push(...subFiles);
                        }
                    }
                    
                    readEntries(); // Continue lendo (API retorna em lotes)
                }, reject);
            }
            
            readEntries();
        });
    }
    
    function getFile(fileEntry) {
        return new Promise((resolve) => {
            fileEntry.file(resolve, () => resolve(null));
        });
    }

    function handleFileSelect(e) {
        console.log('📂 Seleção de arquivo/pasta via input');
        const files = e.target.files;
        console.log('📄 Arquivos selecionados:', files.length);
        
        // Log dos arquivos
        for (let i = 0; i < Math.min(5, files.length); i++) {
            console.log(`  ${i + 1}. ${files[i].name} (${files[i].webkitRelativePath || 'sem path'})`);
        }
        if (files.length > 5) {
            console.log(`  ... e mais ${files.length - 5} arquivo(s)`);
        }
        
        handleFiles(files);
    }

    function handleFiles(files) {
        console.log('🔄 handleFiles chamado com:', files ? files.length : 0, 'arquivo(s)');
        
        // Verificar se recebemos um array ou FileList
        if (!files || files.length === 0) {
            alert('ℹ️ Nenhum arquivo válido encontrado.\n' +
                  'Certifique-se de que a pasta contém arquivos CSV ou JSON.');
            return;
        }
        
        window.selectedFiles = Array.isArray(files) ? files : [...files];
        console.log('📦 window.selectedFiles atualizado:', window.selectedFiles.length, 'arquivo(s)');
        
        // Mostrar feedback se muitos arquivos foram encontrados
        if (window.selectedFiles.length > 50) {
            if (!confirm(`Foram encontrados ${window.selectedFiles.length} arquivos.\n` +
                        'Deseja continuar com o upload?')) {
                window.selectedFiles = [];
                fileList.innerHTML = '';
                return;
            }
        }
        
        // Validar extensões
        const validExtensions = ['.csv', '.json'];
        const invalidFiles = window.selectedFiles.filter(file => {
            const ext = '.' + file.name.split('.').pop().toLowerCase();
            return !validExtensions.includes(ext);
        });

        if (invalidFiles.length > 0) {
            alert('❌ Arquivos com extensão inválida detectados!\n' +
                  'Apenas CSV e JSON são aceitos.\n' +
                  'Arquivos inválidos serão ignorados.');
            window.selectedFiles = window.selectedFiles.filter(file => {
                const ext = '.' + file.name.split('.').pop().toLowerCase();
                return validExtensions.includes(ext);
            });
        }
        
        // Verificar se sobraram arquivos após filtragem
        if (window.selectedFiles.length === 0) {
            alert('⚠️ Nenhum arquivo válido (CSV ou JSON) foi encontrado.');
            fileList.innerHTML = '';
            return;
        }

        // Validar se todos têm o mesmo formato
        if (window.selectedFiles.length > 0) {
            const extensions = window.selectedFiles.map(f => 
                '.' + f.name.split('.').pop().toLowerCase()
            );
            const uniqueExts = [...new Set(extensions)];
            
            if (uniqueExts.length > 1) {
                alert('⚠️ Todos os arquivos devem ter o mesmo formato!\n' +
                      'Detectados: ' + uniqueExts.join(', '));
                window.selectedFiles = [];
                fileList.innerHTML = '';
                return;
            }
        }

        console.log('✅ Validação concluída. Exibindo lista de arquivos...');
        displayFileList();
    }

    function displayFileList() {
        fileList.innerHTML = '';
        
        if (window.selectedFiles.length === 0) {
            fileList.innerHTML = '<p class="text-muted">Nenhum arquivo selecionado</p>';
            return;
        }

        const totalSize = window.selectedFiles.reduce((sum, file) => sum + file.size, 0);
        const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);

        let html = `
            <div class="alert alert-info">
                <strong>${window.selectedFiles.length} arquivo(s) selecionado(s)</strong> 
                (${totalSizeMB} MB total)
            </div>
            <ul class="list-group">
        `;

        window.selectedFiles.forEach((file, index) => {
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            const ext = file.name.split('.').pop().toUpperCase();
            
            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge badge-primary">${ext}</span>
                        <strong>${file.name}</strong>
                        <small class="text-muted">(${sizeMB} MB)</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" 
                            onclick="removeFile(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </li>
            `;
        });

        html += '</ul>';
        fileList.innerHTML = html;
    }

    // Função global para remover arquivo
    window.removeFile = function(index) {
        console.log('🗑️ Removendo arquivo índice:', index);
        window.selectedFiles.splice(index, 1);
        console.log('📦 Arquivos restantes:', window.selectedFiles.length);
        displayFileList();
    };

    // Controlar visibilidade do campo de paralelismo
    const parallelRadio = document.getElementById('parallel_mode');
    const sequentialRadio = document.getElementById('sequential_mode');
    const parallelConfig = document.getElementById('parallel-config');

    if (parallelRadio && sequentialRadio && parallelConfig) {
        parallelRadio.addEventListener('change', function() {
            if (this.checked) {
                parallelConfig.style.display = 'block';
            }
        });

        sequentialRadio.addEventListener('change', function() {
            if (this.checked) {
                parallelConfig.style.display = 'none';
            }
        });
    }
});

// Validação antes do submit
document.addEventListener('DOMContentLoaded', function() {
    const dagForm = document.getElementById('dag-form');
    
    if (!dagForm) return;

    dagForm.addEventListener('submit', function(e) {
        const activeTab = document.querySelector('.nav-link.active');
        
        if (!activeTab) return true;
        
        const activeHref = activeTab.getAttribute('href');
        
        if (activeHref === '#multi-upload' && selectedFiles.length === 0) {
            e.preventDefault();
            alert('⚠️ Selecione ao menos um arquivo para upload!');
            return false;
        }
        
        // Adicionar confirmação para muitos arquivos
        if (selectedFiles.length > 10) {
            const confirm = window.confirm(
                `Você está prestes a enviar ${selectedFiles.length} arquivos.\n` +
                'Isso pode levar algum tempo. Continuar?'
            );
            if (!confirm) {
                e.preventDefault();
                return false;
            }
        }
    });
});

/**
 * Upload com progress bar
 */
function uploadWithProgress(files, url, onProgress, onComplete) {
    const formData = new FormData();
    files.forEach(file => formData.append('multiple_files[]', file));
    
    const xhr = new XMLHttpRequest();
    
    // Progress listener
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            if (onProgress) onProgress(percentComplete);
        }
    });
    
    // Complete listener
    xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (onComplete) onComplete(response);
        } else {
            console.error('Upload falhou:', xhr.statusText);
            alert('Erro no upload. Por favor, tente novamente.');
        }
    });
    
    // Error listener
    xhr.addEventListener('error', () => {
        console.error('Erro de rede no upload');
        alert('Erro de rede. Verifique sua conexão e tente novamente.');
    });
    
    xhr.open('POST', url);
    xhr.send(formData);
}

/**
 * Atualizar progress bar
 */
function updateProgressBar(percent) {
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        progressBar.style.width = percent + '%';
        progressBar.textContent = Math.round(percent) + '%';
    }
}

/**
 * Mostrar resumo do upload
 */
function showUploadSummary(response) {
    const modal = document.getElementById('upload-summary-modal');
    
    if (!modal) {
        console.log('Upload completo:', response);
        return;
    }
    
    // Preencher dados do modal
    document.getElementById('summary-total').textContent = response.uploaded_files?.length || 0;
    document.getElementById('summary-batch-id').textContent = response.batch_id || 'N/A';
    document.getElementById('summary-mode').textContent = response.batch_mode === 'parallel' ? 'Paralelo' : 'Sequencial';
    document.getElementById('summary-dag-id').textContent = response.dag_id || 'N/A';
    
    // Lista de arquivos
    const fileListEl = document.getElementById('summary-file-list');
    fileListEl.innerHTML = '';
    
    if (response.uploaded_files) {
        response.uploaded_files.forEach(file => {
            const li = document.createElement('li');
            li.className = 'list-group-item';
            li.innerHTML = `
                <i class="fas fa-check-circle text-success"></i>
                ${file.name}
                <small class="text-muted">(${(file.size / 1024 / 1024).toFixed(2)} MB)</small>
            `;
            fileListEl.appendChild(li);
        });
    }
    
    // Erros (se houver)
    const errorsDiv = document.getElementById('summary-errors');
    const errorList = document.getElementById('summary-error-list');
    
    if (response.errors && response.errors.length > 0) {
        errorsDiv.style.display = 'block';
        errorList.innerHTML = '';
        
        response.errors.forEach(error => {
            const li = document.createElement('li');
            li.textContent = `${error.file}: ${error.error}`;
            errorList.appendChild(li);
        });
    } else {
        errorsDiv.style.display = 'none';
    }
    
    // Mostrar modal (Bootstrap 4/5)
    if (typeof bootstrap !== 'undefined') {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else if (typeof $ !== 'undefined') {
        $(modal).modal('show');
    }
}
