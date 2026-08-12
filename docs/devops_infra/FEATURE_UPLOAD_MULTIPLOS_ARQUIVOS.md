# 🚀 Feature: Upload de Múltiplos Arquivos CSV/JSON

## 📋 Visão Geral

Implementação de funcionalidade para upload em lote de múltiplos arquivos CSV/JSON na WebApp CodeIgniter, permitindo ingestão massiva de dados em uma única operação.

---

## 🎯 Objetivos

- ✅ Permitir upload de múltiplos arquivos simultaneamente
- ✅ Suportar upload de pasta/diretório compactado (ZIP)
- ✅ Processar arquivos em paralelo via Airflow
- ✅ Validar todos os arquivos antes de iniciar processamento
- ✅ Fornecer feedback visual de progresso

---

## 🏗️ Arquitetura da Solução

### Fluxo de Dados

```
┌─────────────────┐
│   Frontend      │
│  (Formulário)   │
│  - Multi Upload │
│  - Drag & Drop  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   Controller    │
│  (CodeIgniter)  │
│  - Validação    │
│  - Upload S3    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│           MinIO                 │
│      raw/dag_id/                │
│  - 20251220143022_file1.csv     │
│  - 20251220143022_file2.csv     │
│  - 20251220143022_file3.json    │
└────────┬────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────┐
│           Airflow DAG                    │
│         Dynamic Tasks                    │
│  - Task 1 (process file1.csv)            │
│      └─► Bronze → Silver → Gold          │
│  - Task 2 (process file2.csv)            │
│      └─► Bronze → Silver → Gold          │
│  - Task 3 (process file3.json)           │
│      └─► Bronze → Silver → Gold          │
└──────────────────────────────────────────┘
```

---

## 💻 Implementação Frontend

### 1. HTML - Campo de Upload Múltiplo

**Arquivo**: `src/codeigniter-app/app/Views/dags/create.php`

```html
<!-- Seção de Upload de Arquivos -->
<div class="form-group" id="upload-section">
    <label for="file_upload">Upload de Arquivos</label>
    
    <!-- Tabs para escolher modo -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#single-upload">
                📄 Arquivo Único
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#multi-upload">
                📁 Múltiplos Arquivos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#zip-upload">
                📦 Pasta Compactada (ZIP)
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Upload Único (Existente) -->
        <div id="single-upload" class="tab-pane fade show active">
            <input type="file" 
                   class="form-control-file" 
                   name="single_file" 
                   id="single_file"
                   accept=".csv,.json,.parquet">
            <small class="form-text text-muted">
                Formatos aceitos: CSV, JSON, Parquet
            </small>
        </div>

        <!-- Upload Múltiplo (NOVO) -->
        <div id="multi-upload" class="tab-pane fade">
            <div class="upload-area" id="drop-zone">
                <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                <p>Arraste arquivos aqui ou clique para selecionar</p>
                <input type="file" 
                       class="form-control-file d-none" 
                       name="multiple_files[]" 
                       id="multiple_files"
                       accept=".csv,.json"
                       multiple>
                <button type="button" class="btn btn-primary mt-2" 
                        onclick="document.getElementById('multiple_files').click()">
                    Selecionar Arquivos
                </button>
            </div>
            
            <!-- Lista de arquivos selecionados -->
            <div id="file-list" class="mt-3"></div>
            
            <small class="form-text text-muted">
                ⚠️ Todos os arquivos devem ter o mesmo formato (CSV ou JSON)
            </small>
        </div>

        <!-- Upload ZIP (NOVO) -->
        <div id="zip-upload" class="tab-pane fade">
            <input type="file" 
                   class="form-control-file" 
                   name="zip_file" 
                   id="zip_file"
                   accept=".zip">
            <small class="form-text text-muted">
                Arquivo ZIP contendo múltiplos CSV ou JSON
            </small>
        </div>
    </div>
</div>

<!-- Opção de Processamento -->
<div class="form-group" id="batch-options">
    <label>Modo de Processamento</label>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="batch_mode" 
               id="parallel_mode" value="parallel" checked>
        <label class="form-check-label" for="parallel_mode">
            ⚡ Paralelo - Processa todos os arquivos simultaneamente (mais rápido)
        </label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="batch_mode" 
               id="sequential_mode" value="sequential">
        <label class="form-check-label" for="sequential_mode">
            📋 Sequencial - Processa um arquivo por vez (mais seguro)
        </label>
    </div>
    
    <!-- Configuração de Paralelismo -->
    <div id="parallel-config" class="mt-2">
        <label for="max_parallel">Máximo de Arquivos Paralelos</label>
        <input type="number" class="form-control" id="max_parallel" 
               name="max_parallel" value="4" min="1" max="16">
        <small class="form-text text-muted">
            Número máximo de arquivos processados simultaneamente
        </small>
    </div>
</div>
```

### 2. JavaScript - Drag & Drop e Validação

**Arquivo**: `src/codeigniter-app/public/assets/js/multi-upload.js`

```javascript
// Controle de arquivos selecionados
let selectedFiles = [];

// Inicialização do Drag & Drop
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('multiple_files');
    const fileList = document.getElementById('file-list');

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

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    function handleFileSelect(e) {
        const files = e.target.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        selectedFiles = [...files];
        
        // Validar extensões
        const validExtensions = ['.csv', '.json'];
        const invalidFiles = selectedFiles.filter(file => {
            const ext = '.' + file.name.split('.').pop().toLowerCase();
            return !validExtensions.includes(ext);
        });

        if (invalidFiles.length > 0) {
            alert('❌ Arquivos com extensão inválida detectados!\n' +
                  'Apenas CSV e JSON são aceitos.');
            selectedFiles = selectedFiles.filter(file => {
                const ext = '.' + file.name.split('.').pop().toLowerCase();
                return validExtensions.includes(ext);
            });
        }

        // Validar se todos têm o mesmo formato
        if (selectedFiles.length > 0) {
            const extensions = selectedFiles.map(f => 
                '.' + f.name.split('.').pop().toLowerCase()
            );
            const uniqueExts = [...new Set(extensions)];
            
            if (uniqueExts.length > 1) {
                alert('⚠️ Todos os arquivos devem ter o mesmo formato!\n' +
                      'Detectados: ' + uniqueExts.join(', '));
                selectedFiles = [];
                fileList.innerHTML = '';
                return;
            }
        }

        displayFileList();
    }

    function displayFileList() {
        fileList.innerHTML = '';
        
        if (selectedFiles.length === 0) {
            fileList.innerHTML = '<p class="text-muted">Nenhum arquivo selecionado</p>';
            return;
        }

        const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
        const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);

        let html = `
            <div class="alert alert-info">
                <strong>${selectedFiles.length} arquivo(s) selecionado(s)</strong> 
                (${totalSizeMB} MB total)
            </div>
            <ul class="list-group">
        `;

        selectedFiles.forEach((file, index) => {
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
        selectedFiles.splice(index, 1);
        displayFileList();
    };
});

// Validação antes do submit
document.getElementById('dag-form').addEventListener('submit', function(e) {
    const activeTab = document.querySelector('.nav-link.active').getAttribute('href');
    
    if (activeTab === '#multi-upload' && selectedFiles.length === 0) {
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
```

### 3. CSS - Estilização

**Arquivo**: `src/codeigniter-app/public/assets/css/multi-upload.css`

```css
/* Área de Drop */
.upload-area {
    border: 2px dashed #ccc;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    background-color: #f9f9f9;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover {
    border-color: #007bff;
    background-color: #e7f3ff;
}

.upload-area.drag-over {
    border-color: #28a745;
    background-color: #d4edda;
    transform: scale(1.02);
}

.upload-area i {
    color: #007bff;
}

/* Lista de arquivos */
#file-list .list-group-item {
    transition: background-color 0.2s;
}

#file-list .list-group-item:hover {
    background-color: #f8f9fa;
}

/* Progress bar (para upload futuro) */
.upload-progress {
    margin-top: 10px;
}

.upload-progress .progress {
    height: 25px;
}

.upload-progress .progress-bar {
    font-weight: bold;
}
```

---

## 🔧 Implementação Backend (CodeIgniter)

### 1. Controller - Processamento de Upload

**Arquivo**: `src/codeigniter-app/app/Controllers/DAGController.php`

```php
<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class DAGController extends Controller
{
    protected $s3Client;
    protected $bucket = 'lab01';

    public function __construct()
    {
        // Inicializar S3 Client (MinIO)
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'endpoint' => 'http://minio:9000',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => 'admin',
                'secret' => 'admin123',
            ],
        ]);
    }

    /**
     * Processar upload múltiplo de arquivos
     */
    public function uploadMultipleFiles()
    {
        $validation = \Config\Services::validation();
        
        // Configurar regras de validação
        $validation->setRules([
            'dag_id' => 'required|alpha_dash',
            'batch_mode' => 'required|in_list[parallel,sequential]',
            'max_parallel' => 'permit_empty|numeric|greater_than[0]|less_than_equal_to[16]'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'status' => 'error',
                'errors' => $validation->getErrors()
            ]);
        }

        $dagId = $this->request->getPost('dag_id');
        $batchMode = $this->request->getPost('batch_mode');
        $maxParallel = $this->request->getPost('max_parallel') ?? 4;
        
        // Determinar modo de upload
        $uploadMode = $this->detectUploadMode();
        
        try {
            switch ($uploadMode) {
                case 'single':
                    $result = $this->handleSingleUpload($dagId);
                    break;
                    
                case 'multiple':
                    $result = $this->handleMultipleUpload($dagId, $batchMode, $maxParallel);
                    break;
                    
                case 'zip':
                    $result = $this->handleZipUpload($dagId, $batchMode, $maxParallel);
                    break;
                    
                default:
                    throw new \Exception('Modo de upload não identificado');
            }
            
            return $this->response->setJSON($result);
            
        } catch (\Exception $e) {
            log_message('error', 'Upload falhou: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Erro no upload: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Detectar modo de upload baseado nos arquivos enviados
     */
    private function detectUploadMode(): string
    {
        if ($this->request->getFile('zip_file')->isValid()) {
            return 'zip';
        }
        
        $multipleFiles = $this->request->getFileMultiple('multiple_files');
        if (!empty($multipleFiles) && $multipleFiles[0]->isValid()) {
            return 'multiple';
        }
        
        if ($this->request->getFile('single_file')->isValid()) {
            return 'single';
        }
        
        throw new \Exception('Nenhum arquivo válido foi enviado');
    }

    /**
     * Upload único (funcionalidade existente)
     */
    private function handleSingleUpload(string $dagId): array
    {
        $file = $this->request->getFile('single_file');
        
        if (!$file->isValid()) {
            throw new \Exception('Arquivo inválido');
        }
        
        $timestamp = date('YmdHis');
        $hash = substr(md5($file->getName() . time()), 0, 8);
        $s3Key = "raw/{$dagId}/{$timestamp}_{$hash}_{$file->getName()}";
        
        $this->uploadToS3($file, $s3Key);
        
        return [
            'status' => 'success',
            'message' => 'Arquivo enviado com sucesso',
            'file' => $s3Key,
            'mode' => 'single'
        ];
    }

    /**
     * Upload múltiplo de arquivos
     */
    private function handleMultipleUpload(string $dagId, string $batchMode, int $maxParallel): array
    {
        $files = $this->request->getFileMultiple('multiple_files');
        
        if (empty($files) || !$files[0]->isValid()) {
            throw new \Exception('Nenhum arquivo válido foi enviado');
        }
        
        // Validar extensões
        $this->validateFileExtensions($files);
        
        // Gerar timestamp único para identificar o batch
        $batchId = uniqid('batch_', true);  // Apenas para tracking no YAML
        $timestamp = date('YmdHis');
        
        $uploadedFiles = [];
        $errors = [];
        
        foreach ($files as $index => $file) {
            try {
                $fileName = $file->getName();
                // Usar mesmo timestamp para todos os arquivos do batch
                $s3Key = "raw/{$dagId}/{$timestamp}_{$fileName}";
                
                $this->uploadToS3($file, $s3Key);
                
                $uploadedFiles[] = [
                    'name' => $fileName,
                    's3_key' => $s3Key,
                    'size' => $file->getSize()
                ];
                
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $file->getName(),
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Criar configuração YAML para a DAG
        $yamlConfig = $this->generateBatchYAML($dagId, $batchId, $uploadedFiles, $batchMode, $maxParallel);
        $this->saveYAMLConfig($dagId, $yamlConfig);
        
        return [
            'status' => count($errors) > 0 ? 'partial' : 'success',
            'message' => sprintf(
                '%d de %d arquivo(s) enviado(s) com sucesso',
                count($uploadedFiles),
                count($files)
            ),
            'batch_id' => $batchId,
            'uploaded_files' => $uploadedFiles,
            'errors' => $errors,
            'mode' => 'multiple',
            'batch_mode' => $batchMode,
            'dag_id' => $dagId
        ];
    }

    /**
     * Upload de arquivo ZIP
     */
    private function handleZipUpload(string $dagId, string $batchMode, int $maxParallel): array
    {
        $zipFile = $this->request->getFile('zip_file');
        
        if (!$zipFile->isValid()) {
            throw new \Exception('Arquivo ZIP inválido');
        }
        
        // Criar diretório temporário
        $tempDir = WRITEPATH . 'uploads/temp/' . uniqid();
        mkdir($tempDir, 0777, true);
        
        try {
            // Extrair ZIP
            $zip = new \ZipArchive();
            if ($zip->open($zipFile->getTempName()) === TRUE) {
                $zip->extractTo($tempDir);
                $zip->close();
            } else {
                throw new \Exception('Não foi possível extrair o arquivo ZIP');
            }
            
            // Listar arquivos extraídos
            $extractedFiles = $this->scanDirectory($tempDir, ['.csv', '.json']);
            
            if (empty($extractedFiles)) {
                throw new \Exception('Nenhum arquivo CSV ou JSON encontrado no ZIP');
            }
            
            // Gerar ID único para o batch (tracking) e timestamp comum
            $batchId = uniqid('batch_zip_', true);  // Apenas para tracking no YAML
            $timestamp = date('YmdHis');
            
            $uploadedFiles = [];
            $errors = [];
            
            foreach ($extractedFiles as $filePath) {
                try {
                    $fileName = basename($filePath);
                    // Usar mesmo timestamp para todos os arquivos do ZIP
                    $s3Key = "raw/{$dagId}/{$timestamp}_{$fileName}";
                    
                    // Upload direto do arquivo local
                    $this->s3Client->putObject([
                        'Bucket' => $this->bucket,
                        'Key'    => $s3Key,
                        'SourceFile' => $filePath
                    ]);
                    
                    $uploadedFiles[] = [
                        'name' => $fileName,
                        's3_key' => $s3Key,
                        'size' => filesize($filePath)
                    ];
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'file' => basename($filePath),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Criar configuração YAML
            $yamlConfig = $this->generateBatchYAML($dagId, $batchId, $uploadedFiles, $batchMode, $maxParallel);
            $this->saveYAMLConfig($dagId, $yamlConfig);
            
            return [
                'status' => count($errors) > 0 ? 'partial' : 'success',
                'message' => sprintf(
                    '%d de %d arquivo(s) do ZIP enviado(s) com sucesso',
                    count($uploadedFiles),
                    count($extractedFiles)
                ),
                'batch_id' => $batchId,
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'mode' => 'zip',
                'batch_mode' => $batchMode,
                'dag_id' => $dagId
            ];
            
        } finally {
            // Limpar diretório temporário
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * Validar extensões de arquivos
     */
    private function validateFileExtensions(array $files): void
    {
        $allowedExtensions = ['csv', 'json'];
        $extensions = [];
        
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            
            if (!in_array($ext, $allowedExtensions)) {
                throw new \Exception(
                    "Extensão '{$ext}' não permitida no arquivo '{$file->getName()}'. " .
                    "Apenas CSV e JSON são aceitos."
                );
            }
            
            $extensions[] = $ext;
        }
        
        // Verificar se todos têm a mesma extensão
        $uniqueExtensions = array_unique($extensions);
        if (count($uniqueExtensions) > 1) {
            throw new \Exception(
                'Todos os arquivos devem ter o mesmo formato. ' .
                'Detectados: ' . implode(', ', $uniqueExtensions)
            );
        }
    }

    /**
     * Upload para S3/MinIO
     */
    private function uploadToS3($file, string $s3Key): void
    {
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $s3Key,
                'Body'   => fopen($file->getTempName(), 'rb'),
                'ContentType' => $file->getMimeType()
            ]);
        } catch (AwsException $e) {
            throw new \Exception('Erro no upload para MinIO: ' . $e->getMessage());
        }
    }

    /**
     * Gerar configuração YAML para processamento em batch
     */
    private function generateBatchYAML(
        string $dagId, 
        string $batchId, 
        array $files, 
        string $batchMode, 
        int $maxParallel
    ): array {
        return [
            'dag_id' => $dagId,
            'batch_id' => $batchId,
            'batch_mode' => $batchMode,
            'max_parallel_tasks' => $maxParallel,
            'total_files' => count($files),
            'files' => array_map(function($file) {
                return [
                    'source_path' => $file['s3_key'],
                    'file_name' => $file['name'],
                    'size_bytes' => $file['size']
                ];
            }, $files),
            'pipeline_function' => 'lib.medallion_pipeline.batch_raw_to_medallion',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Salvar configuração YAML
     */
    private function saveYAMLConfig(string $dagId, array $config): void
    {
        $yamlPath = APPPATH . '../dags/configs/' . $dagId . '.yml';
        
        // Criar diretório se não existir
        $dir = dirname($yamlPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Converter para YAML e salvar
        $yaml = yaml_emit($config);
        file_put_contents($yamlPath, $yaml);
    }

    /**
     * Escanear diretório recursivamente
     */
    private function scanDirectory(string $dir, array $extensions): array
    {
        $files = [];
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );
        
        foreach ($items as $item) {
            if ($item->isFile()) {
                $ext = '.' . strtolower($item->getExtension());
                if (in_array($ext, $extensions)) {
                    $files[] = $item->getPathname();
                }
            }
        }
        
        return $files;
    }

    /**
     * Deletar diretório recursivamente
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        
        rmdir($dir);
    }
}
```

---

## 🔄 Implementação Airflow - DAG Dinâmica

### Arquivo: `src/dags/lib/medallion_pipeline.py`

```python
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.utils.task_group import TaskGroup
from datetime import datetime, timedelta
import yaml
import os

def batch_raw_to_medallion(batch_id, files, max_parallel, **context):
    """
    Processar múltiplos arquivos em batch
    """
    from concurrent.futures import ThreadPoolExecutor, as_completed
    
    results = []
    errors = []
    
    def process_single_file(file_info):
        """Processar um único arquivo"""
        try:
            source_path = file_info['source_path']
            file_name = file_info['file_name']
            
            # Chamar pipeline existente para arquivo único
            from lib.medallion_pipeline import raw_to_medallion
            
            result = raw_to_medallion(
                source_path=source_path,
                target_table=os.path.splitext(file_name)[0],
                **context
            )
            
            return {
                'status': 'success',
                'file': file_name,
                'result': result
            }
            
        except Exception as e:
            return {
                'status': 'error',
                'file': file_info['file_name'],
                'error': str(e)
            }
    
    # Processar em paralelo
    with ThreadPoolExecutor(max_workers=max_parallel) as executor:
        futures = {
            executor.submit(process_single_file, file_info): file_info 
            for file_info in files
        }
        
        for future in as_completed(futures):
            result = future.result()
            
            if result['status'] == 'success':
                results.append(result)
            else:
                errors.append(result)
    
    # Retornar resumo
    return {
        'batch_id': batch_id,
        'total_files': len(files),
        'successful': len(results),
        'failed': len(errors),
        'results': results,
        'errors': errors
    }


def create_batch_dag(dag_id, config_path):
    """
    Criar DAG dinâmica para processamento em batch
    """
    # Carregar configuração
    with open(config_path, 'r') as f:
        config = yaml.safe_load(f)
    
    batch_mode = config.get('batch_mode', 'parallel')
    
    default_args = {
        'owner': 'airflow',
        'depends_on_past': False,
        'start_date': datetime(2024, 1, 1),
        'email_on_failure': False,
        'email_on_retry': False,
        'retries': 1,
        'retry_delay': timedelta(minutes=5),
    }
    
    dag = DAG(
        dag_id=dag_id,
        default_args=default_args,
        description=f'Batch processing: {config["batch_id"]}',
        schedule_interval=None,  # Manual trigger
        catchup=False,
        tags=['batch', 'multi-file', config['batch_mode']]
    )
    
    with dag:
        if batch_mode == 'parallel':
            # Processar tudo em paralelo
            process_batch = PythonOperator(
                task_id='process_all_files',
                python_callable=batch_raw_to_medallion,
                op_kwargs={
                    'batch_id': config['batch_id'],
                    'files': config['files'],
                    'max_parallel': config.get('max_parallel_tasks', 4)
                }
            )
            
        else:  # sequential
            # Criar task para cada arquivo
            with TaskGroup(group_id='process_files') as file_group:
                for idx, file_info in enumerate(config['files']):
                    task = PythonOperator(
                        task_id=f'process_file_{idx}_{file_info["file_name"]}',
                        python_callable=lambda fi=file_info, **ctx: 
                            batch_raw_to_medallion(
                                batch_id=config['batch_id'],
                                files=[fi],
                                max_parallel=1,
                                **ctx
                            ),
                    )
                    
                    # Encadear tasks sequencialmente
                    if idx > 0:
                        prev_task = f'process_file_{idx-1}_{config["files"][idx-1]["file_name"]}'
                        dag.get_task(f'process_files.{prev_task}') >> task
    
    return dag
```

---

## ✅ Validações e Tratamento de Erros

### 1. Validações Frontend
- ✅ Extensões permitidas (apenas CSV/JSON)
- ✅ Tamanho máximo por arquivo (configurável)
- ✅ Tamanho total do batch
- ✅ Uniformidade de formato (todos CSV ou todos JSON)
- ✅ Número máximo de arquivos (limite configura)

### 2. Validações Backend
- ✅ Verificação de vírus (opcional: ClamAV)
- ✅ Validação de estrutura do arquivo
- ✅ Verificação de duplicatas
- ✅ Validação de esquema (colunas esperadas)

### 3. Tratamento de Erros
```php
// Exemplo de tratamento robusto
try {
    $result = $this->handleMultipleUpload($dagId, $batchMode, $maxParallel);
    
    // Log de sucesso
    log_message('info', sprintf(
        'Batch upload completed: %d files uploaded successfully',
        count($result['uploaded_files'])
    ));
    
} catch (\Exception $e) {
    // Log de erro detalhado
    log_message('error', sprintf(
        'Batch upload failed for DAG %s: %s',
        $dagId,
        $e->getMessage()
    ));
    
    // Rollback parcial se necessário
    $this->rollbackPartialUpload($batchId);
    
    throw $e;
}
```

---

## 📊 Monitoramento e Feedback

### 1. Progress Bar Durante Upload

```javascript
// Adicionar ao multi-upload.js
function uploadWithProgress(files) {
    const formData = new FormData();
    files.forEach(file => formData.append('multiple_files[]', file));
    
    const xhr = new XMLHttpRequest();
    
    // Progress listener
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            updateProgressBar(percentComplete);
        }
    });
    
    // Complete listener
    xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            showUploadSummary(response);
        }
    });
    
    xhr.open('POST', '/dags/upload-multiple');
    xhr.send(formData);
}

function updateProgressBar(percent) {
    const progressBar = document.querySelector('.progress-bar');
    progressBar.style.width = percent + '%';
    progressBar.textContent = Math.round(percent) + '%';
}
```

### 2. Resumo Pós-Upload

```html
<!-- Modal de Resumo -->
<div class="modal fade" id="upload-summary-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✅ Upload Concluído</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <strong id="summary-total"></strong> arquivo(s) enviado(s) com sucesso
                </div>
                
                <h6>Arquivos Processados:</h6>
                <ul id="summary-file-list" class="list-group mb-3"></ul>
                
                <div id="summary-errors" class="alert alert-danger" style="display:none;">
                    <h6>Erros:</h6>
                    <ul id="summary-error-list"></ul>
                </div>
                
                <hr>
                
                <p><strong>ID do Batch:</strong> <code id="summary-batch-id"></code></p>
                <p><strong>Modo de Processamento:</strong> <span id="summary-mode"></span></p>
                <p><strong>DAG Criada:</strong> <code id="summary-dag-id"></code></p>
            </div>
            <div class="modal-footer">
                <a href="/airflow" class="btn btn-primary" target="_blank">
                    Abrir Airflow
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## 🧪 Testes

### 1. Testes Unitários (PHPUnit)

```php
// tests/Controllers/DAGControllerTest.php
class DAGControllerTest extends \CodeIgniter\Test\CIUnitTestCase
{
    public function testMultipleUploadValidation()
    {
        $files = [
            new \CodeIgniter\HTTP\Files\UploadedFile(
                'test1.csv',
                'text/csv',
                null,
                null,
                UPLOAD_ERR_OK
            ),
            new \CodeIgniter\HTTP\Files\UploadedFile(
                'test2.csv',
                'text/csv',
                null,
                null,
                UPLOAD_ERR_OK
            )
        ];
        
        $result = $this->controller->handleMultipleUpload(
            'test_dag',
            'parallel',
            4
        );
        
        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['uploaded_files']);
    }
    
    public function testMixedExtensionsRejection()
    {
        $files = [
            new \CodeIgniter\HTTP\Files\UploadedFile('test1.csv', 'text/csv'),
            new \CodeIgniter\HTTP\Files\UploadedFile('test2.json', 'application/json')
        ];
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('mesmo formato');
        
        $this->controller->validateFileExtensions($files);
    }
}
```

### 2. Testes de Integração

```python
# tests/test_batch_processing.py
import pytest
from airflow.models import DagBag

def test_batch_dag_creation():
    """Testar criação de DAG dinâmica"""
    config = {
        'dag_id': 'test_batch',
        'batch_id': 'batch_123',
        'batch_mode': 'parallel',
        'files': [
            {'source_path': 'raw/test/file1.csv', 'file_name': 'file1.csv'},
            {'source_path': 'raw/test/file2.csv', 'file_name': 'file2.csv'}
        ]
    }
    
    dag = create_batch_dag('test_batch', config)
    
    assert dag is not None
    assert 'process_all_files' in dag.task_ids
    
def test_parallel_processing():
    """Testar processamento paralelo"""
    # Implementar teste de paralelização
    pass
```

---

## 🚀 Roadmap de Implementação

### Fase 1: MVP (2-3 semanas)
- [ ] Frontend básico com upload múltiplo
- [ ] Backend processar array de arquivos
- [ ] Upload para MinIO em batch
- [ ] Geração de YAML para batch

### Fase 2: Melhorias (1-2 semanas)
- [ ] Drag & Drop
- [ ] Progress bar
- [ ] Validações avançadas
- [ ] Modal de resumo

### Fase 3: Features Avançadas (2-3 semanas)
- [ ] Upload de ZIP
- [ ] Processamento paralelo vs sequencial configurável
- [ ] Dashboard de monitoramento
- [ ] Retry automático de arquivos com falha

### Fase 4: Otimizações (1 semana)
- [ ] Compressão de arquivos
- [ ] Chunked upload para arquivos grandes
- [ ] Cache de validações
- [ ] Testes automatizados completos

---

## 📝 Notas de Implementação

### Estrutura de Armazenamento

**Por que não usar subpasta `batch_id/`?**

A solução mantém a estrutura plana `raw/{dag_id}/{timestamp}_{filename}` ao invés de criar uma subpasta por batch (`raw/{dag_id}/{batch_id}/`) pelos seguintes motivos:

1. **Consistência**: Mantém coerência total com uploads únicos existentes
2. **Simplicidade**: Menos níveis de diretório = mais fácil de gerenciar e depurar
3. **Identificação**: O timestamp comum já identifica arquivos do mesmo batch
4. **Rastreamento**: O `batch_id` é mantido apenas no arquivo YAML de configuração para tracking e auditoria

**Exemplo prático**:
```
raw/dag_vendas/
├── 20251220143022_vendas_janeiro.csv   # Batch 1
├── 20251220143022_vendas_fevereiro.csv # Batch 1 (mesmo timestamp)
├── 20251220143022_vendas_marco.csv     # Batch 1 (mesmo timestamp)
└── 20251220150000_vendas_abril.csv     # Batch 2 (outro upload)
```

O YAML mantém a associação:
```yaml
batch_id: batch_67890abc123
files:
  - raw/dag_vendas/20251220143022_vendas_janeiro.csv
  - raw/dag_vendas/20251220143022_vendas_fevereiro.csv
  - raw/dag_vendas/20251220143022_vendas_marco.csv
```

### Considerações de Performance
- **Upload**: Para arquivos > 100MB, considerar chunked upload
- **Processamento**: Limitar paralelismo baseado em recursos disponíveis
- **Storage**: Implementar limpeza automática de arquivos temporários

### Segurança
- Validar MIME type além da extensão
- Implementar rate limiting para uploads
- Escanear arquivos em busca de malware (ClamAV)
- Sanitizar nomes de arquivo

### Escalabilidade
- Para > 100 arquivos, considerar processamento assíncrono via Celery
- Implementar fila de processamento (Redis/RabbitMQ)
- Usar AWS S3 Multipart Upload para arquivos grandes

---

**Documento criado em**: 20/12/2025  
**Versão**: 1.0  
**Status**: Proposta Técnica
