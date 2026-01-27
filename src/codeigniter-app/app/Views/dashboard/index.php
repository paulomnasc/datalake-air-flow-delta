<?php
if (!defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';

// Define o owner com o padrão prefixo-idusuario (username do Airflow)
$ownerUsername = \App\Helpers\AirflowHelper::buildUsernameFromEmail(
    \App\Helpers\SessionHelper::getUserEmail(),
    (int) \App\Helpers\SessionHelper::getUserId()
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyDataFlow - Dashboard</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --primary-color: #1976d2;
            --secondary-color: #dc004e;
            --success-color: #2e7d32;
            --background: #f5f5f5;
        }

        body {
            background: var(--background);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Dashboard Stats */
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-left: 4px solid var(--primary-color);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .hero-section h1 {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .hero-section p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        /* Quick Actions */
        .quick-action {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .quick-action:hover {
            border-color: var(--primary-color);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.2);
        }
        .quick-action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Template Cards */
        .template-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            border: 2px solid #e0e0e0;
        }
        .template-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            border-color: var(--primary-color);
        }
        .template-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .badge-difficulty {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
        }

        /* Wizard Steps */
        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        .wizard-step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        .wizard-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        .wizard-step.active .wizard-step-circle {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.2);
        }
        .wizard-step.completed .wizard-step-circle {
            background: var(--success-color);
            color: white;
        }
        .wizard-step-label {
            font-size: 0.85rem;
            color: #666;
        }
        .wizard-step.active .wizard-step-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>

    <div id="content">
        <div class="container-fluid" style="max-width: 1400px; margin: 0 auto; padding: 2rem;">

            <!-- Alpine.js App -->
            <div x-data="dashboardApp()" x-init="init()">

                <!-- DASHBOARD VIEW -->
                <div x-show="currentView === 'dashboard'">
                    <!-- Hero Section -->
                    <div class="hero-section">
                        <h1>🚀 Bem-vindo ao MyDataFlow</h1>
                        <p>Crie pipelines de dados poderosos sem escrever código</p>
                        <button class="btn btn-light btn-lg mt-3" @click="currentView = 'wizard'">
                            <i class="bi bi-plus-circle"></i> Criar Novo Pipeline
                        </button>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="stat-number"><?= $stats['pipelines']['total'] ?></div>
                                    <div class="stat-label">Pipelines Criados</div>
                                    <small class="text-success">
                                        <i class="bi bi-check-circle"></i> <?= $stats['pipelines']['active'] ?> ativos
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card" style="border-left-color: #2e7d32;">
                                <div class="card-body">
                                    <div class="stat-number" style="color: #2e7d32;"><?= $stats['datasources']['total'] ?></div>
                                    <div class="stat-label">Fontes de Dados</div>
                                    <small class="text-muted">
                                        <i class="bi bi-plug"></i> <?= $stats['datasources']['connected'] ?> conectadas
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card" style="border-left-color: #f57c00;">
                                <div class="card-body">
                                    <div class="stat-number" style="color: #f57c00;"><?= $stats['executions']['today'] ?></div>
                                    <div class="stat-label">Execuções Hoje</div>
                                    <small class="text-muted">
                                        <i class="bi bi-clock-history"></i> <?= $stats['executions']['total'] ?> total
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card" style="border-left-color: #dc004e;">
                                <div class="card-body">
                                    <div class="stat-number" style="color: <?= $stats['executions']['success_rate'] >= 80 ? '#2e7d32' : '#dc004e' ?>">
                                        <?= $stats['executions']['success_rate'] ?>%
                                    </div>
                                    <div class="stat-label">Taxa de Sucesso</div>
                                    <small class="<?= $stats['pipelines']['failed'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                        <i class="bi bi-exclamation-triangle"></i> <?= $stats['pipelines']['failed'] ?> com falha
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row g-4 mb-5">
                        <div class="col-12">
                            <h4 class="mb-3">⚡ Início Rápido</h4>
                        </div>
                        <div class="col-md-4">
                            <div class="quick-action" @click="currentView = 'wizard'">
                                <div class="quick-action-icon">🎯</div>
                                <h5>Novo Pipeline</h5>
                                <p class="text-muted mb-0">Assistente passo a passo</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="quick-action" @click="scrollToTemplates()">
                                <div class="quick-action-icon">📋</div>
                                <h5>Usar Template</h5>
                                <p class="text-muted mb-0">Comece com exemplos prontos</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="quick-action" onclick="window.location.href='<?= route_to('listConfig') ?>'">
                                <div class="quick-action-icon">📊</div>
                                <h5>Ver Pipelines</h5>
                                <p class="text-muted mb-0">Gerenciar existentes</p>
                            </div>
                        </div>
                    </div>

                    <!-- Templates Section -->
                    <div id="templates-section">
                        <h4 class="mb-4">📚 Templates Populares</h4>
                        <div class="row g-4">
                            <template x-for="template in templates" :key="template.id">
                                <div class="col-md-6 col-lg-3">
                                    <div class="card template-card h-100" @click="useTemplate(template.id)">
                                        <div class="card-body">
                                            <div class="template-icon" x-text="template.icon"></div>
                                            <h6 class="card-title" x-text="template.name"></h6>
                                            <p class="card-text text-muted small" x-text="template.description"></p>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="badge bg-primary badge-difficulty" x-text="template.difficulty"></span>
                                                <small class="text-muted">
                                                    <i class="bi bi-list-check"></i>
                                                    <span x-text="template.steps"></span> passos
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- WIZARD VIEW -->
                <div x-show="currentView === 'wizard'" style="display: none;">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="bi bi-magic"></i> 
                                <?= !empty($edit_data) ? 'Editar Pipeline' : 'Assistente de Criação de Pipeline' ?>
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            
                            <!-- Wizard Steps Progress -->
                            <div class="wizard-steps">
                                <template x-for="step in wizardSteps" :key="step.value">
                                    <div class="wizard-step" 
                                         :class="{'active': wizardData.currentStep === step.value, 'completed': wizardData.currentStep > step.value}">
                                        <div class="wizard-step-circle">
                                            <span x-show="wizardData.currentStep < step.value" x-text="step.value"></span>
                                            <i x-show="wizardData.currentStep >= step.value" class="bi bi-check"></i>
                                        </div>
                                        <div class="wizard-step-label" x-text="step.label"></div>
                                    </div>
                                </template>
                            </div>

                            <!-- Wizard Content Form -->
                            <form id="wizardForm" 
                                  method="POST" 
                                  action="<?= !empty($edit_data) ? base_url('updateConfig') : base_url('dashboard/createPipeline') ?>" 
                                  enctype="multipart/form-data"
                                  @submit.prevent="submitWizard($event)">
                                <?= csrf_field() ?>
                                
                                <?php if (!empty($edit_data)): ?>
                                    <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">
                                <?php endif; ?>
                                
                                <input type="hidden" name="owner" value="<?= $ownerUsername ?>">

                                <div class="wizard-content">
                                    <!-- Step 1: Informações Básicas -->
                                    <div x-show="wizardData.currentStep === 1">
                                        <h4 class="mb-4">Informações Básicas</h4>
                                        
                                        <div class="form-section">
                                            <label class="form-label">Nome do Pipeline *</label>
                                            <input type="text" 
                                                   name="dag_id" 
                                                   class="form-control" 
                                                   placeholder="Ex: pipeline_vendas_diario"
                                                   x-model="wizardData.pipelineName" 
                                                   x-bind:required="wizardData.currentStep === 1">
                                            <small class="text-muted">Use apenas letras, números e underscore (_)</small>
                                        </div>

                                        <div class="form-section">
                                            <label class="form-label">Descrição</label>
                                            <textarea name="description" 
                                                      class="form-control" 
                                                      rows="3" 
                                                      placeholder="Descreva o objetivo deste pipeline..."
                                                      x-model="wizardData.description"></textarea>
                                        </div>

                                        <div class="form-section">
                                            <label class="form-label">Pasta/Workspace *</label>
                                            <select name="id_pasta" class="form-select" x-model="wizardData.folder" x-bind:required="wizardData.currentStep === 1">
                                                <option value="">Selecione uma pasta...</option>
                                                <?php if (!empty($pastas)): ?>
                                                    <?php foreach ($pastas as $pasta): ?>
                                                        <option value="<?= is_object($pasta) ? $pasta->id : $pasta['id'] ?>"><?= htmlspecialchars(is_object($pasta) ? $pasta->descricao : $pasta['descricao']) ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <small class="text-muted">Organize seus pipelines em pastas</small>
                                        </div>
                                    </div>

                                    <!-- Step 2: Fonte de Dados -->
                                    <div x-show="wizardData.currentStep === 2">
                                        <h4 class="mb-4">Fonte de Dados</h4>
                                        
                                        <?php if (!empty($edit_data) && !empty($edit_data['source_filename'])): ?>
                                            <!-- Informação de arquivo original em modo edição -->
                                            <div class="alert alert-info mb-3">
                                                <h6 class="mb-2"><i class="bi bi-info-circle"></i> Arquivo(s) Original(is):</h6>
                                                <div class="small">
                                                    <strong>Caminho:</strong> 
                                                    <code><?= htmlspecialchars($edit_data['source_filename']) ?></code>
                                                </div>
                                                <div class="small mt-2 text-muted">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                    <strong>Nota:</strong> Para alterar os arquivos, faça upload de novo(s) arquivo(s) abaixo. 
                                                    Os arquivos originais serão substituídos.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="form-section">
                                            <label class="form-label">Tipo de Fonte *</label>
                                            <select name="id_source_type" class="form-select" x-model="wizardData.sourceType" @change="handleSourceTypeChange()" x-bind:required="wizardData.currentStep === 2">
                                                <option value="">Selecione o tipo...</option>
                                                <?php if (!empty($source_types)): ?>
                                                    <?php foreach ($source_types as $type): ?>
                                                        <option value="<?= is_object($type) ? $type->id : $type['id'] ?>" 
                                                                data-description="<?= htmlspecialchars(is_object($type) ? $type->description : $type['description']) ?>">
                                                            <?= htmlspecialchars(is_object($type) ? $type->description : $type['description']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <!-- Upload de Arquivo CSV/JSON -->
                                        <div x-show="wizardData.showFileUpload" class="mt-3">
                                            <!-- Checkbox para ativar upload múltiplo -->
                                            <div class="mb-3 d-flex align-items-start gap-2">
                                                <input type="checkbox" id="enable_multi_upload" x-model="wizardData.multiUpload" class="form-check-input mt-1">
                                                <label for="enable_multi_upload" class="form-check-label">📦 Upload Múltiplo de Arquivos (Batch Processing)</label>
                                            </div>
                                            <small class="text-muted d-block ms-4 mb-3">
                                                <strong>Dica:</strong> Use para processar múltiplos arquivos CSV/JSON simultaneamente ou sequencialmente
                                            </small>
                                            
                                            <!-- Upload Único (padrão) -->
                                            <div x-show="!wizardData.multiUpload" id="single_upload_section">
                                                <label class="form-label">Arquivo Selecionado:</label>
                                                <input type="file" 
                                                       name="source_filename" 
                                                       class="form-control" 
                                                       accept=".csv,.json" 
                                                       x-bind:required="wizardData.showFileUpload && !wizardData.multiUpload">
                                            </div>
                                            
                                            <!-- Upload Múltiplo -->
                                            <div x-show="wizardData.multiUpload" id="multi_upload_section">
                                                <label class="form-label">Arquivos de Origem (CSV/JSON):</label>
                                                
                                                <!-- Instruções -->
                                                <div class="alert alert-info mb-3">
                                                    <strong>📌 Como usar:</strong>
                                                    <ul class="mb-0 mt-2">
                                                        <li><strong>Arquivos Individuais:</strong> Deixe a opção abaixo desmarcada e selecione múltiplos arquivos (Ctrl+Click)</li>
                                                        <li><strong>Pasta Completa:</strong> Marque a opção abaixo para selecionar uma pasta inteira</li>
                                                        <li><strong>Limite:</strong> O tamanho total não deve exceder 10 MB. Para arquivos maiores, processe em lotes menores.</li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Opção de seleção de pasta -->
                                                <div class="mb-3 d-flex align-items-start gap-2">
                                                    <input type="checkbox" id="select_folder" x-model="wizardData.selectFolder" class="form-check-input mt-1">
                                                    <label for="select_folder" class="form-check-label">📂 Selecionar Pasta Inteira</label>
                                                </div>
                                                
                                                <!-- Área de Upload -->
                                                <div class="border rounded p-4 text-center bg-light" style="cursor: pointer;" @click="$refs.multiFileInput.click()">
                                                    <div style="font-size: 3rem;">📁</div>
                                                    <p class="mb-0" x-text="wizardData.selectFolder ? 'Clique para selecionar uma pasta' : 'Clique para selecionar múltiplos arquivos'"></p>
                                                    <small class="text-muted">Arquivos CSV ou JSON</small>
                                                    <input 
                                                        type="file" 
                                                        x-ref="multiFileInput"
                                                        name="multiple_files[]" 
                                                        class="form-control"
                                                        style="display: none;"
                                                        @change="handleFileSelection($event)"
                                                        :multiple="!wizardData.selectFolder"
                                                        :accept="wizardData.selectFolder ? '' : '.csv,.json'"
                                                        :webkitdirectory="wizardData.selectFolder || undefined"
                                                        :directory="wizardData.selectFolder || undefined">
                                                </div>
                                                
                                                <!-- Lista de Arquivos Selecionados -->
                                                <div x-show="wizardData.selectedFiles.length > 0" class="mt-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="mb-0">Arquivos Selecionados (<span x-text="wizardData.selectedFiles.length"></span>):</h6>
                                                        <span class="badge bg-info" x-text="'Total: ' + formatFileSize(wizardData.selectedFiles.reduce((sum, f) => sum + f.size, 0))"></span>
                                                    </div>
                                                    <ul class="list-group">
                                                        <template x-for="(file, index) in wizardData.selectedFiles" :key="index">
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <span x-text="file.name"></span>
                                                                <span class="badge bg-secondary" x-text="formatFileSize(file.size)"></span>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                    
                                                    <!-- Aviso de limite -->
                                                    <div class="alert alert-warning mt-2 mb-0" x-show="wizardData.selectedFiles.reduce((sum, f) => sum + f.size, 0) > 10 * 1024 * 1024">
                                                        <small>
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            <strong>Atenção:</strong> O tamanho total excede 10 MB. O upload pode falhar devido a limites do servidor.
                                                            Considere processar em lotes menores.
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                <!-- Configurações de Batch -->
                                                <div class="mt-3">
                                                    <h6>Configurações de Processamento em Batch</h6>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Modo de Processamento:</label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="batch_mode" value="parallel" checked>
                                                            <label class="form-check-label">Paralelo (múltiplos arquivos simultaneamente)</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="batch_mode" value="sequential">
                                                            <label class="form-check-label">Sequencial (um arquivo por vez)</label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Máximo de Arquivos Paralelos:</label>
                                                        <input type="number" class="form-control" name="max_parallel_files" value="4" min="1" max="16">
                                                        <small class="text-muted">Entre 1 e 16 (padrão: 4)</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Configuração MySQL -->
                                        <div x-show="wizardData.sourceType === '3'" class="mt-3">
                                            <h6>Configuração MySQL</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Host</label>
                                                    <input type="text" name="db_host" class="form-control" placeholder="localhost">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Porta</label>
                                                    <input type="number" name="db_port" class="form-control" placeholder="3306">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Database</label>
                                                    <input type="text" name="db_database" class="form-control" placeholder="nome_banco">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Usuário</label>
                                                    <input type="text" name="db_user" class="form-control" placeholder="usuario">
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label">Senha</label>
                                                    <input type="password" name="db_password" class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 3: Transformações -->
                                    <div x-show="wizardData.currentStep === 3">
                                        <h4 class="mb-4">Lógica de Transformação</h4>
                                        
                                        <div class="form-section">
                                            <label class="form-label">Função Python de Transformação: *</label>
                                            <select name="python_module_path" 
                                                    class="form-select" 
                                                    x-model="wizardData.pythonFunction" 
                                                    x-bind:required="wizardData.currentStep === 3">
                                                <option value="">-- Selecione o tipo de pipeline --</option>
                                                <?php if (!empty($funcoes_python)): ?>
                                                    <?php foreach ($funcoes_python as $grupo => $funcoes): ?>
                                                        <optgroup label="<?= htmlspecialchars($grupo) ?>">
                                                            <?php foreach ($funcoes as $funcao): ?>
                                                                <option value="<?= htmlspecialchars(is_object($funcao) ? $funcao->modulo_python : $funcao['modulo_python']) ?>">
                                                                    <?= htmlspecialchars(is_object($funcao) ? $funcao->nome : $funcao['nome']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </optgroup>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <small class="text-muted d-block mt-2">
                                                Escolha o tipo de processamento: Pipeline Completo (recomendado), Ingestão de fontes (MySQL, etc) ou camadas individuais.
                                            </small>
                                        </div>

                                        <div class="form-section">
                                            <label class="form-label">Argumentos Extras da Função (JSON)</label>
                                            <textarea name="transform_args" 
                                                      class="form-control font-monospace" 
                                                      rows="8" 
                                                      x-model="wizardData.transformArgs"
                                                      placeholder='{"chave": "valor", "exemplo": true}'></textarea>
                                            <small class="text-muted">Deve ser um JSON válido. Use {} se não precisar de argumentos extras.</small>
                                        </div>
                                    </div>

                                    <!-- Step 4: Agendamento -->
                                    <div x-show="wizardData.currentStep === 4">
                                        <h4 class="mb-4">Agendamento</h4>
                                        
                                        <div class="form-section">
                                            <label class="form-label">Frequência de Execução</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="schedule" 
                                                       x-model="wizardData.scheduleType" value="manual" checked>
                                                <label class="form-check-label">
                                                    <strong>Manual</strong> - Executar sob demanda
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="schedule" 
                                                       x-model="wizardData.scheduleType" value="scheduled">
                                                <label class="form-check-label">
                                                    <strong>Agendado</strong> - Executar automaticamente
                                                </label>
                                            </div>
                                        </div>

                                        <div x-show="wizardData.scheduleType === 'scheduled'" class="mt-3">
                                            <div class="form-section">
                                                <label class="form-label">Frequência</label>
                                                <select class="form-select" x-model="wizardData.frequency" name="schedule_interval">
                                                    <option value="">Selecione...</option>
                                                    <option value="@hourly">A cada hora</option>
                                                    <option value="@daily">Diariamente</option>
                                                    <option value="@weekly">Semanalmente</option>
                                                    <option value="@monthly">Mensalmente</option>
                                                    <option value="custom">Personalizado (cron)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-section">
                                            <label class="form-label">Status da DAG</label>
                                            <select name="is_active" class="form-select">
                                                <option value="1" selected>Ativa (Gerar DAG)</option>
                                                <option value="0">Inativa (Não Gerar DAG)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Step 5: Revisar -->
                                    <div x-show="wizardData.currentStep === 5">
                                        <h4 class="mb-4">Revisar Pipeline</h4>
                                        
                                        <div class="card mb-3">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">Resumo da Configuração</h6>
                                            </div>
                                            <div class="card-body">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-3">Nome:</dt>
                                                    <dd class="col-sm-9" x-text="wizardData.pipelineName || 'Não informado'"></dd>
                                                    
                                                    <dt class="col-sm-3">Descrição:</dt>
                                                    <dd class="col-sm-9" x-text="wizardData.description || 'Nenhuma'"></dd>
                                                    
                                                    <dt class="col-sm-3">Pasta:</dt>
                                                    <dd class="col-sm-9" x-text="getPastaName(wizardData.folder) || 'Não selecionada'"></dd>
                                                    
                                                    <dt class="col-sm-3">Tipo de Fonte:</dt>
                                                    <dd class="col-sm-9" x-text="getSourceTypeName(wizardData.sourceType) || 'Não configurada'"></dd>
                                                    
                                                    <dt class="col-sm-3">Função Python:</dt>
                                                    <dd class="col-sm-9" x-text="wizardData.pythonFunction || 'Não selecionada'"></dd>
                                                    
                                                    <dt class="col-sm-3">Agendamento:</dt>
                                                    <dd class="col-sm-9" x-text="wizardData.scheduleType === 'scheduled' ? 'Agendado - ' + wizardData.frequency : 'Manual'"></dd>
                                                </dl>
                                            </div>
                                        </div>

                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i>
                                            <strong>Tudo pronto!</strong> Seu pipeline está configurado e pronto para ser criado.
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Buttons -->
                                <div class="d-flex justify-content-between mt-4 pt-4 border-top">
                                    <button type="button" 
                                            class="btn btn-outline-secondary" 
                                            @click="isEditMode ? window.location.href='<?= base_url('listConfig') ?>' : currentView = 'dashboard'"
                                            x-show="wizardData.currentStep === 1">
                                        <i class="bi bi-x-circle"></i> 
                                        <span x-text="isEditMode ? 'Voltar para Listagem' : 'Cancelar'"></span>
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-secondary" 
                                            @click="previousStep()"
                                            x-show="wizardData.currentStep > 1">
                                        <i class="bi bi-arrow-left"></i> Voltar
                                    </button>
                                    
                                    <div class="ms-auto">
                                        <button type="button" 
                                                class="btn btn-primary" 
                                                @click="nextStep()"
                                                x-show="wizardData.currentStep < 5">
                                            Próximo <i class="bi bi-arrow-right"></i>
                                        </button>
                                        <button type="submit" 
                                                class="btn btn-success" 
                                                x-show="wizardData.currentStep === 5">
                                            <i class="bi bi-check-circle"></i> 
                                            <?= !empty($edit_data) ? 'Atualizar Pipeline' : 'Criar Pipeline' ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Mensagens de Feedback -->
    <script>
        // Verificar se há mensagem armazenada após redirecionamento
        document.addEventListener('DOMContentLoaded', function() {
            const storedMessage = localStorage.getItem('dashboard_message');
            if (storedMessage) {
                try {
                    const msg = JSON.parse(storedMessage);
                    localStorage.removeItem('dashboard_message');
                    
                    // Criar div de mensagem
                    const messageDiv = document.createElement('div');
                    messageDiv.id = 'dashboard-message';
                    messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
                    
                    const alertClass = msg.type === 'success' ? 'alert-success' : 'alert-danger';
                    const icon = msg.type === 'success' ? 'check-circle' : 'exclamation-triangle';
                    
                    messageDiv.innerHTML = `
                        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                            <i class="bi bi-${icon}"></i>
                            <strong>${msg.type === 'success' ? 'Sucesso!' : 'Erro!'}</strong> ${msg.text}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    
                    document.body.appendChild(messageDiv);
                    
                    // Fade out após 6 segundos
                    setTimeout(() => {
                        const alert = messageDiv.querySelector('.alert');
                        if (alert) {
                            alert.classList.remove('show');
                            setTimeout(() => messageDiv.remove(), 300);
                        }
                    }, 6000);
                } catch (e) {
                    console.error('Erro ao processar mensagem:', e);
                    localStorage.removeItem('dashboard_message');
                }
            }
        });
    </script>

    <!-- Alpine.js App Logic -->
    <script>
        function dashboardApp() {
            return {
                currentView: 'dashboard',
                isEditMode: <?= !empty($edit_data) ? 'true' : 'false' ?>,
                templates: [
                    {
                        id: 1,
                        name: 'CSV para Parquet',
                        description: 'Converte arquivos CSV para formato Parquet otimizado',
                        icon: '📄',
                        difficulty: 'Fácil',
                        steps: 3
                    },
                    {
                        id: 2,
                        name: 'MySQL para Delta Lake',
                        description: 'Ingere dados de tabelas MySQL para camada Bronze',
                        icon: '🗄️',
                        difficulty: 'Médio',
                        steps: 5
                    },
                    {
                        id: 3,
                        name: 'API REST para Bronze',
                        description: 'Consome API REST e armazena dados brutos',
                        icon: '🌐',
                        difficulty: 'Médio',
                        steps: 4
                    },
                    {
                        id: 4,
                        name: 'ETL Completo Bronze → Gold',
                        description: 'Pipeline completo com todas as camadas do Data Lake',
                        icon: '⭐',
                        difficulty: 'Avançado',
                        steps: 7
                    }
                ],
                wizardSteps: [
                    { label: 'Informações Básicas', value: 1 },
                    { label: 'Fonte de Dados', value: 2 },
                    { label: 'Transformações', value: 3 },
                    { label: 'Agendamento', value: 4 },
                    { label: 'Revisar', value: 5 }
                ],
                wizardData: {
                    currentStep: 1,
                    pipelineName: '',
                    description: '',
                    folder: '',
                    sourceType: '',
                    showFileUpload: false,
                    multiUpload: false,
                    selectFolder: false,
                    selectedFiles: [],
                    dbType: '',
                    pythonFunction: '',
                    transformArgs: '{}',
                    scheduleType: 'manual',
                    frequency: ''
                },

                init() {
                    console.log('✅ Dashboard UX carregado com sucesso!');
                    console.log('Funções Python carregadas:', <?= json_encode($funcoes_python ?? []) ?>);
                    
                    // Se houver dados de edição, preencher o wizard e abrir em modo edição
                    <?php if (!empty($edit_data)): ?>
                    const editData = <?= json_encode($edit_data) ?>;
                    console.log('📝 Modo edição ativado:', editData);
                    
                    // Preencher campos do wizard
                    this.wizardData.pipelineName = editData.dag_id || '';
                    this.wizardData.description = editData.description || '';
                    this.wizardData.folder = editData.id_pasta ? editData.id_pasta.toString() : '';
                    this.wizardData.sourceType = editData.id_source_type ? editData.id_source_type.toString() : '';
                    this.wizardData.pythonFunction = editData.python_module_path || '';
                    this.wizardData.transformArgs = editData.transform_args || '{}';
                    this.wizardData.scheduleType = editData.schedule_interval === '@manual' || editData.schedule_interval === null ? 'manual' : 'scheduled';
                    this.wizardData.frequency = editData.schedule_interval || '0 0 * * *';
                    
                    // Mudar para view do wizard
                    this.currentView = 'wizard';
                    <?php endif; ?>
                    
                    // Watcher para resetar arquivos quando trocar entre pasta/arquivos
                    this.$watch('wizardData.selectFolder', (value) => {
                        console.log('🔄 Modo de seleção alterado para:', value ? 'Pasta' : 'Múltiplos Arquivos');
                        // Limpar seleção anterior
                        this.wizardData.selectedFiles = [];
                        // Resetar input
                        if (this.$refs.multiFileInput) {
                            this.$refs.multiFileInput.value = '';
                        }
                    });
                    
                    // Watcher para resetar ao desativar multi-upload
                    this.$watch('wizardData.multiUpload', (value) => {
                        if (!value) {
                            this.wizardData.selectedFiles = [];
                            this.wizardData.selectFolder = false;
                        }
                    });
                },

                handleSourceTypeChange() {
                    const select = document.querySelector('select[name="id_source_type"]');
                    if (!select || !select.selectedOptions[0]) return;
                    
                    const description = select.selectedOptions[0].getAttribute('data-description') || '';
                    const descLower = description.toLowerCase();
                    
                    // Mostrar upload se for CSV ou JSON
                    this.wizardData.showFileUpload = descLower.includes('csv') || descLower.includes('json');
                    
                    // Reset upload settings
                    this.wizardData.multiUpload = false;
                    this.wizardData.selectFolder = false;
                    this.wizardData.selectedFiles = [];
                },

                handleFileSelection(event) {
                    const files = Array.from(event.target.files);
                    this.wizardData.selectedFiles = files;
                    console.log(`📦 ${files.length} arquivo(s) selecionado(s):`, files.map(f => f.name));
                },

                formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                },

                useTemplate(templateId) {
                    alert(`✨ Template ${templateId} selecionado!\n\nFuncionalidade em desenvolvimento.`);
                    this.currentView = 'wizard';
                    this.wizardData.currentStep = 1;
                },

                scrollToTemplates() {
                    document.getElementById('templates-section').scrollIntoView({ behavior: 'smooth' });
                },

                nextStep() {
                    if (this.wizardData.currentStep < 5) {
                        this.wizardData.currentStep++;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                previousStep() {
                    if (this.wizardData.currentStep > 1) {
                        this.wizardData.currentStep--;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },

                getPastaName(pastaId) {
                    const pastas = <?= json_encode($pastas) ?>;
                    const pasta = pastas.find(p => p.id == pastaId);
                    return pasta ? pasta.descricao : 'Não selecionada';
                },

                getSourceTypeName(sourceTypeId) {
                    const sourceTypes = <?= json_encode($source_types) ?>;
                    const source = sourceTypes.find(s => s.id == sourceTypeId);
                    return source ? source.description : 'Não configurada';
                },

                submitWizard(event) {
                    const form = event.target;
                    const formData = new FormData(form);
                    
                    console.log('📤 Enviando pipeline...');
                    
                    // Garantir valores padrão para campos opcionais
                    if (!formData.get('schedule_interval')) {
                        formData.set('schedule_interval', '0 0 * * *');
                    }
                    if (!formData.get('transform_args')) {
                        formData.set('transform_args', '{}');
                    }
                    if (!formData.get('is_active')) {
                        formData.set('is_active', '1');
                    }

                    // Se houver arquivos selecionados via multi-upload, adicionar ao FormData
                    if (this.wizardData.multiUpload && this.wizardData.selectedFiles.length > 0) {
                        console.log('📦 Adicionando arquivos ao FormData:', this.wizardData.selectedFiles.length);
                        
                        // Calcular tamanho total dos arquivos
                        const totalSize = this.wizardData.selectedFiles.reduce((sum, file) => sum + file.size, 0);
                        const totalMB = (totalSize / (1024 * 1024)).toFixed(2);
                        
                        console.log(`📊 Tamanho total: ${totalMB} MB`);
                        
                        // Verificar limite (nginx geralmente 10MB, mas pode ser menor)
                        const maxSizeMB = 10; // Ajustar conforme configuração do nginx
                        if (totalSize > maxSizeMB * 1024 * 1024) {
                            this.showMessage(
                                `O tamanho total dos arquivos (${totalMB} MB) excede o limite de ${maxSizeMB} MB. ` +
                                `Reduza a quantidade de arquivos ou processe em lotes menores.`,
                                'error'
                            );
                            return;
                        }
                        
                        // Remover campos de upload único e limpar array múltiplo
                        formData.delete('source_filename');
                        formData.delete('multiple_files[]');
                        
                        // Adicionar cada arquivo
                        this.wizardData.selectedFiles.forEach((file, index) => {
                            formData.append('multiple_files[]', file);
                            console.log(`  ✓ Arquivo ${index + 1}: ${file.name} (${this.formatFileSize(file.size)})`);
                        });
                        
                        // Marcar checkbox de multi-upload como selecionado
                        formData.set('enable_multi_upload', '1');
                    } else {
                        // Se não for multi-upload, remover campos de multi-upload
                        formData.delete('multiple_files[]');
                        console.log('📄 Upload único - arquivo:', formData.get('source_filename')?.name || 'Nenhum');
                    }

                    // Log do FormData para debug
                    console.log('📋 Conteúdo do FormData:');
                    for (let pair of formData.entries()) {
                        if (pair[1] instanceof File) {
                            console.log(`  ${pair[0]}: [File] ${pair[1].name} (${this.formatFileSize(pair[1].size)})`);
                        } else {
                            console.log(`  ${pair[0]}: ${pair[1]}`);
                        }
                    }
                    
                    console.log('🌐 URL de destino:', form.action);

                    // Enviar via AJAX
                    fetch(form.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('📥 Resposta HTTP:', response.status, response.statusText);
                        
                        // Tratamento específico para erro 413 (Request Entity Too Large)
                        if (response.status === 413) {
                            throw new Error('O tamanho total dos arquivos excede o limite do servidor (nginx). Reduza a quantidade de arquivos ou processe em lotes menores.');
                        }
                        
                        // Verificar se a resposta é JSON antes de parsear
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            // Se não for JSON, tentar ler como texto para debug
                            return response.text().then(text => {
                                console.error('❌ Resposta não é JSON:', text.substring(0, 500));
                                
                                // Mensagem mais específica baseada no status
                                if (response.status >= 500) {
                                    throw new Error('Erro interno do servidor. Verifique os logs ou tente novamente mais tarde.');
                                } else if (response.status === 404) {
                                    throw new Error('Rota não encontrada. Verifique a configuração do sistema.');
                                } else {
                                    throw new Error(`Servidor retornou erro ${response.status}. Verifique o console para mais detalhes.`);
                                }
                            });
                        }
                        
                        return response.json();
                    })
                    .then(result => {
                        console.log('✅ Resposta do servidor:', result);
                        
                        const mensagem = result.mensagem || result.message || 'Operação realizada com sucesso';
                        
                        if (result.status === 'success' || result.status === 'partial') {
                            // Armazenar mensagem de sucesso na sessão via localStorage temporariamente
                            localStorage.setItem('dashboard_message', JSON.stringify({
                                type: 'success',
                                text: mensagem
                            }));
                            
                            // Redirecionar para o dashboard
                            setTimeout(() => {
                                window.location.href = '<?= base_url('dashboard') ?>';
                            }, 500);
                        } else {
                            // Mostrar erro
                            this.showMessage(mensagem, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('❌ Erro na requisição:', error);
                        this.showMessage(error.message || 'Erro ao salvar as informações. Por favor, tente novamente.', 'error');
                    });
                },

                showMessage(text, type) {
                    // Criar div de mensagem se não existir
                    let messageDiv = document.getElementById('dashboard-message');
                    if (!messageDiv) {
                        messageDiv = document.createElement('div');
                        messageDiv.id = 'dashboard-message';
                        messageDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
                        document.body.appendChild(messageDiv);
                    }

                    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                    const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
                    
                    messageDiv.innerHTML = `
                        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                            <i class="bi bi-${icon}"></i>
                            <strong>${type === 'success' ? 'Sucesso!' : 'Erro!'}</strong> ${text}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    
                    // Fade out após 6 segundos
                    setTimeout(() => {
                        const alert = messageDiv.querySelector('.alert');
                        if (alert) {
                            alert.classList.remove('show');
                            setTimeout(() => messageDiv.innerHTML = '', 300);
                        }
                    }, 6000);
                }
            }
        }
    </script>

</body>
</html>

<?php require VIEWPATH . '/footer.php'; ?>
