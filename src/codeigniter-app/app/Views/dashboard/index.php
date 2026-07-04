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

            <?php if (session()->getFlashdata('error_analytics')): ?>
                <div class="alert alert-warning alert-dismissible fade show shadow-sm p-4 mb-4" role="alert" style="border-radius: 12px; border-left: 5px solid #ff9800; background-color: #fffde7; color: #5d4037;">
                    <div class="d-flex align-items-start">
                        <span style="font-size: 2.2rem; margin-right: 1.5rem; line-height: 1;">🚧</span>
                        <div>
                            <h5 class="alert-heading mb-2" style="font-weight: 700; color: #e65100;">Seu ambiente de Analytics está quase pronto!</h5>
                            <p class="mb-0" style="font-size: 1.05rem; line-height: 1.6;"><?= session()->getFlashdata('error_analytics') ?></p>
                            <hr style="border-top: 1px solid #ffe082; margin: 1rem 0;">
                            <p class="mb-0 small text-muted"><i class="bi bi-info-circle"></i> O Metabase é ativado automaticamente após a materialização de pelo menos um modelo analítico de produção via <strong>dbt Run (Prod)</strong>.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <script>
                    // Auto dismiss após 25 segundos (delay bem alto)
                    setTimeout(function() {
                        var alertEl = document.querySelector('.alert-dismissible');
                        if (alertEl) {
                            var bsAlert = new bootstrap.Alert(alertEl);
                            bsAlert.close();
                        }
                    }, 25000);
                </script>
            <?php endif; ?>

            <!-- Alpine.js App -->
            <div x-data="dashboardApp()" x-init="init()">

                <!-- DASHBOARD VIEW -->
                <div x-show="currentView === 'dashboard'">
                    
                    <!-- Quick Actions (Início Rápido) -->
                    <div class="row g-4 mb-5">
                        <div class="col-12">
                            <h4 class="mb-3">⚡ Início Rápido</h4>
                        </div>
                        <div class="col-md-3">
                            <div class="quick-action h-100" id="novo-pipeline-action">
                                <div class="quick-action-icon">🎯</div>
                                <h5>Novo Pipeline</h5>
                                <p class="text-muted mb-0">Assistente passo a passo</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <?php $airflowExternalUrl = getenv('AIRFLOW_EXTERNAL_URL') ?: 'http://localhost:8080'; ?>
                            <div class="quick-action h-100" onclick="window.open('<?= htmlspecialchars($airflowExternalUrl, ENT_QUOTES, 'UTF-8'); ?>', '_blank', 'noopener,noreferrer')" title="AIRFLOW - Pipelines ELT">
                                <div class="quick-action-icon d-flex align-items-center justify-content-center" style="min-height: 4.5rem;">
                                    <img src="<?= base_url('assets/img/airflow-logo.png') ?>" alt="Airflow" style="height: 4rem; object-fit: contain; transform: scale(2.2); transform-origin: center;">
                                </div>
                                <h5>Gerenciar Airflow</h5>
                                <p class="text-muted mb-0">AIRFLOW - Pipelines ELT</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="quick-action h-100" onclick="window.location.href='<?= route_to('listConfig') ?>'">
                                <div class="quick-action-icon">📊</div>
                                <h5>Ver Pipelines</h5>
                                <p class="text-muted mb-0">Gerenciar existentes</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="quick-action h-100" onclick="window.location.href='<?= route_to('analytics.access') ?>'">
                                <div class="quick-action-icon">📈</div>
                                <h5>Meu Analytics</h5>
                                <p class="text-muted mb-0">Dashboards no Metabase</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Section -->
                    <div class="hero-section">
                        <h1>Bem-vindo ao MyDataFlow</h1>
                        <div style="display: flex; justify-content: center; margin: 32px 0;">
                            <div style="background: #181c2a; border-radius: 12px; box-shadow: 0 4px 24px #0002; padding: 16px; max-width: 100vw;">
                                <?php if ($isProfessionalMode): ?>
                                    <iframe width="480" height="270" style="max-width:100%; border-radius:8px; border: none;" src="https://www.youtube.com/embed/MvKik17uWR4" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                <?php else: ?>
                                    <iframe width="480" height="270" style="max-width:100%; border-radius:8px; border: none;" src="https://www.youtube.com/embed/_bPDHAEtnXw?si=D2DB11aZ1UvostEX" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Botão YouTube -->
                        <!-- ?php if (isset($_SESSION['nome_usuario_logado']) && !$isProfessionalMode): ?>
                        <a id="youtubeBtn" href="/cursos">
                            <i class="fab fa-youtube"></i>
                            <span>Videoaulas</span>
                        </a>
                        <!-- ?php endif; ? -->
                        <p>Crie pipelines de dados poderosos sem escrever código</p>
                        <!-- Botão Criar Novo Pipeline ocultado por solicitação
                        <button class="btn btn-light btn-lg mt-3" @click="currentView = 'wizard'" style="display:none;">
                            <i class="bi bi-plus-circle"></i> Criar Novo Pipeline
                        </button>
                        -->
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var btn = document.querySelector('.btn.btn-light.btn-lg.mt-3');
                            if (btn) {
                                btn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    window.location.href = '/wizard/create-pipeline';
                                });
                            }
                        });
                        </script>
                    </div>

                    </div>

                    <?php if (false): // SEÇÃO COMENTADA — Alunos que Retornaram Após Cadastro ?>
                    <?php if (!empty($returning_students)): ?>
                    <div class="card shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                        <div class="card-header d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1rem 1.5rem;">
                            <h5 class="mb-0 text-white">🔄 Alunos que Retornaram Após Cadastro</h5>
                            <a href="<?php echo site_url('admin/downloadReturningStudentsCsv'); ?>" class="btn btn-sm btn-light" style="font-weight: 500;">
                                ⬇️ Download CSV
                            </a>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Aluno</th>
                                        <th>Email</th>
                                        <th class="text-center">Retornos</th>
                                        <th class="text-end">Último Retorno</th>
                                        <th class="text-end">Criado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returning_students as $index => $student):
                                        $rank = $index + 1;
                                        $rankColors = [1 => '#ffd700', 2 => '#c0c0c0', 3 => '#cd7f32'];
                                        $rankColor = $rankColors[$rank] ?? '#667eea';
                                    ?>
                                        <tr>
                                            <td>
                                                <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:<?php echo $rankColor; ?>;color:white;font-weight:bold;font-size:14px;">
                                                    <?php echo $rank; ?>
                                                </span>
                                            </td>
                                            <td style="font-weight: 600;"><?php echo esc($student->user_name); ?></td>
                                            <td class="text-muted"><?php echo esc($student->email); ?></td>
                                            <td class="text-center">
                                                <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:14px;padding:4px 12px;border-radius:12px;font-weight:600;">
                                                    <?php echo $student->return_count; ?>
                                                </span>
                                            </td>
                                            <td class="text-end" style="color:#667eea;font-size:14px;">
                                                <?php
                                                    if (!empty($student->last_return)) {
                                                        $lastReturn = new DateTime($student->last_return);
                                                        echo $lastReturn->format('d/m/Y H:i');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-end text-muted" style="font-size:14px;">
                                                <?php
                                                    if (!empty($student->criado_em)) {
                                                        $created = new DateTime($student->criado_em);
                                                        echo $created->format('d/m/Y H:i');
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card shadow-sm mb-4 text-center p-5" style="border-radius: 12px; color:#999;">
                        <div style="font-size:64px;margin-bottom:16px;">🔄</div>
                        <p>Nenhum aluno retornou após cadastro ainda</p>
                    </div>
                    <?php endif; ?>
                    <?php endif; // FIM SEÇÃO COMENTADA ?>

                    <!-- Top 10 Alunos por XP -->
                    <?php if (!$isProfessionalMode): ?>
                    <?php if (!empty($top_students)): ?>
                    <div class="card shadow-sm mb-4" style="border-radius: 12px; overflow: hidden;">
                        <div class="card-header d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); padding: 1rem 1.5rem;">
                            <h5 class="mb-0 text-white">🏆 Top 10 Alunos por XP - Entre para nossa comunidade !!!</h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Aluno</th>
                                        <th class="text-center">Tarefas</th>
                                        <th class="text-end">XP Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_students as $index => $student):
                                        $rank = $index + 1;
                                        $rankColors = [1 => '#ffd700', 2 => '#c0c0c0', 3 => '#cd7f32'];
                                        $rankColor = $rankColors[$rank] ?? '#667eea';
                                    ?>
                                        <tr>
                                            <td>
                                                <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:<?php echo $rankColor; ?>;color:white;font-weight:bold;font-size:14px;">
                                                    <?php echo $rank; ?>
                                                </span>
                                            </td>
                                            <td style="font-weight: 600;"><?php echo esc($student->nome); ?></td>
                                            <td class="text-center">
                                                <span class="badge" style="background:#e8f5e9;color:#2e7d32;font-size:14px;padding:4px 12px;border-radius:12px;font-weight:600;">
                                                    <?php echo $student->tasks_completed; ?> ✓
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span style="font-size:16px;font-weight:bold;color:#fda085;">
                                                    <?php echo number_format($student->total_xp); ?> XP
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card shadow-sm mb-4 text-center p-5" style="border-radius: 12px; color:#999;">
                        <div style="font-size:64px;margin-bottom:16px;">📊</div>
                        <p>Nenhum aluno com XP ainda</p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

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


                    <!-- Templates Section -->
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                var novoPipeline = document.getElementById('novo-pipeline-action');
                                                if (novoPipeline) {
                                                    novoPipeline.addEventListener('click', function(e) {
                                                        e.preventDefault();
                                                        window.location.href = '/wizard/create-pipeline';
                                                    });
                                                }
                                            });
                                        </script>
                    <!--div id="templates-section">
                        <h4 class="mb-4">📚 Templates Populares</h4>
                        <div class="row g-4">
                            <template x-for="template in templates" :key="template.id">
                                <div class="col-md-6 col-lg-3">
                                    <div class="card template-card h-100">
                                        <div class="card-body" @click="useTemplate(template.id)" style="cursor: pointer;">
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
                                        <div x-show="template.hasDownload" class="card-footer bg-light" @click.stop>
                                            <a :href="template.downloadUrl" class="btn btn-sm btn-outline-success w-100" download="Invoice.json">
                                                <i class="bi bi-download"></i> Baixar Exemplo (Invoice.json)
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div-->

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
                                                                    action="<?= !empty($edit_data) ? base_url('updateConfig') : base_url('insertConfig') ?>" 
                                                                    enctype="multipart/form-data"
                                                                    novalidate
                                                                    @submit.prevent="submitWizard($event)">
                                                                <!-- Garantir campos SQL sempre presentes no POST -->
                                                                <input type="hidden" name="sql_host" value="">
                                                                <input type="hidden" name="sql_port" value="">
                                                                <input type="hidden" name="sql_database_name" value="">
                                                                <input type="hidden" name="sql_user" value="">
                                                                <input type="hidden" name="sql_password" value="">
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
                                                   required>
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
                                            <select name="id_pasta" class="form-select" x-model="wizardData.folder" required>
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
                                                    <?php
                                                        $sourceFiles = explode(',', $edit_data['source_filename']);
                                                        echo '<ul style="margin-bottom:0;">';
                                                        foreach ($sourceFiles as $file) {
                                                            echo '<li><code>' . htmlspecialchars(trim($file)) . '</code></li>';
                                                        }
                                                        echo '</ul>';
                                                    ?>
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
                                            <select name="id_source_type" class="form-select" x-model="wizardData.sourceType" @change="handleSourceTypeChange()" required>
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

                                        <!-- Campos específicos para API REST -->
                                        <div class="form-section mt-3" x-show="getSourceTypeName(wizardData.sourceType).toLowerCase().includes('api')">
                                                                                        <h6>Configuração da API REST</h6>
                                                                                        <div class="alert alert-info" style="font-size:1em;">
                                                                                                <strong>Exemplo de preenchimento para <b>transform_args</b>:</strong>
                                                                                                <pre style="background:#f8f9fa; border-radius:6px; padding:12px; margin-top:10px; font-size:0.98em;">
{
    "api_endpoint": "https://api.exemplo.com/endpoint",
    "api_method": "GET",
    "api_headers": {
        "Authorization": "Bearer <sua API_KEY aqui>",
        "Content-Type": "application/json"
    },
    "api_params": {
        "param1": "valor1",
        "param2": "valor2"
    },
    "api_payload": {
        "campo": "valor"
    },
    "api_auth": "Bearer <seu token aqui>"
}
                                                                                                </pre>
                                                                                                <ul style="margin-bottom:0;">
                                                                                                        <li>Preencha <b>api_endpoint</b> com a URL da sua API.</li>
                                                                                                        <li>Em <b>api_headers</b>, coloque suas chaves, tokens ou outros headers necessários.</li>
                                                                                                        <li>Use <b>&lt;sua API_KEY aqui&gt;</b> ou <b>&lt;seu token aqui&gt;</b> para indicar onde inserir suas credenciais.</li>
                                                                                                        <li>Os campos <b>api_params</b> e <b>api_payload</b> são opcionais e dependem da sua API.</li>
                                                                                                </ul>
                                                                                                <span class="text-muted">Copie e personalize o exemplo acima no campo <b>transform_args</b> abaixo.</span>
                                                                                        </div>
                                                                                </div>

                                        <div class="form-section">
                                            <label class="form-label">Argumentos JSON</label>
                                            <div id="monaco-transform-args-container" style="height: 220px; border: 1px solid #ced4da; border-radius: 0.375rem; margin-bottom: 0.5rem;"></div>
                                            <input type="hidden" name="transform_args" x-model="wizardData.transformArgs" id="transform_args_hidden" />
                                            <small class="text-muted">Deve ser um JSON válido. Use {} se não precisar de argumentos extras. Veja o exemplo acima para APIs REST.</small>
                                            <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
                                            <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                if (document.getElementById('monaco-transform-args-container')) {
                                                    require.config({ paths: { 'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' } });
                                                    require(['vs/editor/editor.main'], function () {
                                                        if (window.monacoTransformArgsEditor) {
                                                            window.monacoTransformArgsEditor.dispose();
                                                        }
                                                        var initialValue = document.getElementById('transform_args_hidden').value || '{}';
                                                        // Sempre formatar o JSON ao carregar
                                                        try {
                                                            if (initialValue && initialValue.trim() !== '') {
                                                                initialValue = JSON.stringify(JSON.parse(initialValue), null, 2);
                                                            }
                                                        } catch (e) {
                                                            // Se não for JSON válido, mantém como está
                                                        }
                                                        window.monacoTransformArgsEditor = monaco.editor.create(document.getElementById('monaco-transform-args-container'), {
                                                            value: initialValue,
                                                            language: 'json',
                                                            theme: 'vs',
                                                            fontSize: 15,
                                                            minimap: { enabled: false },
                                                            automaticLayout: true,
                                                            scrollBeyondLastLine: false,
                                                            wordWrap: 'on',
                                                            formatOnPaste: true,
                                                            formatOnType: true,
                                                        });
                                                        window.monacoTransformArgsEditor.onDidChangeModelContent(function () {
                                                            const value = window.monacoTransformArgsEditor.getValue();
                                                            document.getElementById('transform_args_hidden').value = value;
                                                            if (window.Alpine && window.Alpine.store && window.Alpine.store('wizardData')) {
                                                                window.Alpine.store('wizardData').transformArgs = value;
                                                            }
                                                        });
                                                        window.monacoTransformArgsEditor.onDidBlurEditorWidget(function () {
                                                            try {
                                                                const val = window.monacoTransformArgsEditor.getValue();
                                                                if (val.trim()) {
                                                                    const formatted = JSON.stringify(JSON.parse(val), null, 2);
                                                                    window.monacoTransformArgsEditor.setValue(formatted);
                                                                    document.getElementById('transform_args_hidden').value = formatted;
                                                                    if (window.Alpine && window.Alpine.store && window.Alpine.store('wizardData')) {
                                                                        window.Alpine.store('wizardData').transformArgs = formatted;
                                                                    }
                                                                }
                                                            } catch (e) {}
                                                        });
                                                    });
                                                }
                                            });
                                            </script>
                                                                                <!-- label class="form-label">Argumentos JSON 2</label>
                                                                                <div id="monaco-transform-args-container" style="height: 220px; border: 1px solid #ced4da; border-radius: 0.375rem; margin-bottom: 0.5rem;"></div>
                                                                                <input type="hidden" name="transform_args" x-model="wizardData.transformArgs" id="transform_args_hidden" />
                                                                                <small class="text-muted">Deve ser um JSON válido. Use {} se não precisar de argumentos extras. Veja o exemplo acima para APIs REST.</small>
                                                                                <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
                                                                                <script>
                                                                                document.addEventListener('DOMContentLoaded', function() {
                                                                                    if (document.getElementById('monaco-transform-args-container')) {
                                                                                        require(['vs/editor/editor.main'], function () {/* ...existing code... */});
                                                                                    }
                                                                                });
                                                                                </script -->
                                                                                <!-- SQL Connection UI for SQL sources (apenas para SQL) -->
                                                                                <div x-show="getSourceTypeName(wizardData.sourceType).toLowerCase().includes('sql')" class="mt-3">
                                                                                    <h6>Configuração SQL</h6>
                                                                                    <div class="row g-3">
                                                                                        <div class="col-md-6">
                                                                                            <label class="form-label">ID da Conexão Airflow</label>
                                                                                            <input type="text" name="sql_connection_id" class="form-control" placeholder="Ex: mysql_northwind" x-model="wizardData.sqlConnectionId">
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label class="form-label">Host</label>
                                                                                            <input type="text" name="sql_host" class="form-control" placeholder="localhost" x-model="wizardData.dbHost">
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label class="form-label">Porta</label>
                                                                                            <input type="number" name="sql_port" class="form-control" placeholder="3306" x-model="wizardData.dbPort">
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label class="form-label">Database</label>
                                                                                            <input type="text" name="sql_database_name" class="form-control" placeholder="nome_banco" x-model="wizardData.dbDatabase">
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label class="form-label">Usuário</label>
                                                                                            <input type="text" name="sql_user" class="form-control" placeholder="usuario" x-model="wizardData.dbUser">
                                                                                        </div>
                                                                                        <div class="col-md-12">
                                                                                            <label class="form-label">Senha</label>
                                                                                            <input type="password" name="sql_password" class="form-control" x-model="wizardData.dbPassword">
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="mt-3">
                                                                                        <button type="button" class="btn btn-primary" @click="connectAndListTables(wizardData)">Conectar e Listar Tabelas</button>
                                                                                        <div id="connection_status" style="display:none; margin-top:10px;"></div>
                                                                                        <div id="tables-loading" style="display:none; margin-top:10px;">Carregando tabelas...</div>
                                                                                        <div id="tables-container" style="margin-top:10px;"></div>
                                                                                    </div>
                                                                                </div>

                                            <template x-if="getSourceTypeName(wizardData.sourceType).toLowerCase().includes('api')">
                                                <div>
                                                    <label class="form-label">Nome da Tabela Destino *</label>
                                                    <input type="text" 
                                                           name="target_table_name" 
                                                           class="form-control" 
                                                           placeholder="Ex: cotacao_voos, api_resultados, etc"
                                                           x-model="wizardData.targetTableName" 
                                                           pattern="[a-zA-Z0-9_]+"
                                                           required>
                                                    <small class="text-muted">
                                                        <i class="bi bi-info-circle"></i> 
                                                        Nome usado para organizar os dados em Bronze/Silver/Gold. Use apenas letras, números e underscore (_)
                                                    </small>
                                                </div>
                                            </template>
                                            <template x-if="!getSourceTypeName(wizardData.sourceType).toLowerCase().includes('api')">
                                                <div>
                                                    <label class="form-label">Nome da Tabela Destino *</label>
                                                    <input type="text" 
                                                           name="target_table_name" 
                                                           class="form-control" 
                                                           placeholder="Ex: clientes, vendas, produtos"
                                                           x-model="wizardData.targetTableName" 
                                                           pattern="[a-zA-Z0-9_]+"
                                                           required>
                                                    <small class="text-muted">
                                                        <i class="bi bi-info-circle"></i> 
                                                        Nome usado para organizar os dados em Bronze/Silver/Gold. Use apenas letras, números e underscore (_)
                                                    </small>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Upload de Arquivo CSV/JSON (apenas para fontes arquivo) -->
                                        <div x-show="wizardData.showFileUpload && !getSourceTypeName(wizardData.sourceType).toLowerCase().includes('sql') && !getSourceTypeName(wizardData.sourceType).toLowerCase().includes('api')" class="mt-3">
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
                                        <!-- Removido bloco duplicado de Configuração MySQL -->
                                    </div>

                                    <!-- Step 3: Transformações -->
                                    <div x-show="wizardData.currentStep === 3">
                                        <h4 class="mb-4">Lógica de Transformação</h4>
                                        
                                        <div class="form-section">
                                            <label class="form-label">Função Python de Transformação: *</label>
                                            <select name="python_module_path" 
                                                    class="form-select" 
                                                    x-model="wizardData.pythonFunction"
                                                    required>
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

                                        <!--div class="form-section">
                                            <label class="form-label">Argumentos Extras da Função (JSON)</label>
                                            <textarea name="transform_args" 
                                                      class="form-control font-monospace" 
                                                      rows="8" 
                                                      x-model="wizardData.transformArgs"
                                                      placeholder='{"chave": "valor", "exemplo": true}'></textarea>
                                            <small class="text-muted">Deve ser um JSON válido. Use {} se não precisar de argumentos extras.</small>
                                        </div-->
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
                                                    
                                                    <dt class="col-sm-3">Tabela Destino:</dt>
                                                    <dd class="col-sm-9" x-text="wizardData.targetTableName || 'Não informada'"></dd>
                                                    
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
                    setTimeout(function() {
                        const alert = messageDiv.querySelector('.alert');
                        if (alert) {
                            alert.classList.remove('show');
                            setTimeout(function() { messageDiv.remove(); }, 300);
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
                        name: 'JSON para Parquet',
                        description: 'Converte arquivos JSON para formato Parquet otimizado',
                        icon: '📄',
                        difficulty: 'Fácil',
                        steps: 3,
                        hasDownload: true,
                        downloadUrl: '<?= base_url("assets/templates/json/Invoice.json") ?>'
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
                // Templates pré-configurados com dados prontos
                templateConfigs: {
                    1: { // JSON para Parquet - Pipeline Completo Bronze → Gold
                        pipelineName: 'pipeline_json_completo',
                        description: 'Pipeline completo: JSON → Bronze → Silver → Gold (Parquet otimizado)',
                        folder: '',
                        sourceType: '7', // JSON
                        pythonFunction: 'lib.medallion_pipeline.raw_to_medallion',
                        transformArgs: '{}',
                        scheduleType: 'scheduled',
                        frequency: '0 2 * * *', // 2h da manhã
                        isActive: '1',
                        requiresFile: true,
                        fileHint: 'Baixe o arquivo Invoice.json de exemplo e faça upload para criar o pipeline completo'
                    },
                    2: { // MySQL - Pipeline Completo Bronze → Gold
                        pipelineName: 'pipeline_mysql_completo',
                        description: 'Pipeline completo: MySQL → Bronze → Silver → Gold (Delta Lake)',
                        folder: '',
                        sourceType: '8', // MySQL
                        pythonFunction: 'lib.mysql_ingestion.mysql_to_medallion',
                        transformArgs: '{}',
                        scheduleType: 'scheduled',
                        frequency: '0 3 * * *',
                        isActive: '1'
                    },
                    3: { // API REST - Pipeline Completo Bronze → Gold
                        pipelineName: 'pipeline_api_completo',
                        description: 'Pipeline completo: API REST → Bronze → Silver → Gold',
                        folder: '',
                        sourceType: '7', // JSON
                        pythonFunction: 'lib.medallion_pipeline.raw_to_medallion',
                        transformArgs: '{"api_endpoint": "https://api.exemplo.com/dados"}',
                        scheduleType: 'scheduled',
                        frequency: '*/30 * * * *', // A cada 30 minutos
                        isActive: '1'
                    },
                    4: { // CSV - Pipeline Completo Bronze → Gold
                        pipelineName: 'pipeline_csv_completo',
                        description: 'Pipeline completo: CSV → Bronze → Silver → Gold (ETL completo)',
                        folder: '',
                        sourceType: '6', // CSV
                        pythonFunction: 'lib.medallion_pipeline.raw_to_medallion',
                        transformArgs: '{}',
                        scheduleType: 'scheduled',
                        frequency: '0 1 * * *', // 1h da manhã
                        isActive: '1',
                        requiresFile: true,
                        fileHint: 'Selecione arquivo(s) CSV para demonstrar o pipeline completo Bronze → Gold'
                    }
                },
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
                    targetTableName: '',
                    showFileUpload: false,
                    multiUpload: false,
                    selectFolder: false,
                    selectedFiles: [],
                    dbType: '',
                    pythonFunction: '',
                    transformArgs: '{}',
                    scheduleType: 'manual',
                    frequency: '',
                    // Campos MySQL
                    dbHost: '',
                    dbPort: '',
                    dbDatabase: '',
                    dbUser: '',
                    dbPassword: ''
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
                    this.wizardData.targetTableName = editData.target_table_name || '';
                    this.wizardData.pythonFunction = editData.python_module_path || '';
                    this.wizardData.transformArgs = editData.transform_args || '{}';
                    this.wizardData.scheduleType = editData.schedule_interval === '@manual' || editData.schedule_interval === null ? 'manual' : 'scheduled';
                    this.wizardData.frequency = editData.schedule_interval || '0 0 * * *';
                    // Preencher campos MySQL se existirem
                    this.wizardData.dbHost = editData.sql_host || editData.db_host || '';
                    this.wizardData.dbPort = editData.sql_port || editData.db_port || '';
                    this.wizardData.dbDatabase = editData.sql_database_name || editData.db_database || '';
                    this.wizardData.dbUser = editData.sql_user || editData.db_user || '';
                    this.wizardData.dbPassword = editData.sql_password || editData.db_password || '';
                    this.wizardData.sqlConnectionId = editData.sql_connection_id || '';
                    
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
                    const templateConfig = this.templateConfigs[templateId];
                    
                    if (!templateConfig) {
                        this.showMessage('Template não encontrado!', 'error');
                        return;
                    }
                    
                    console.log('📋 Carregando template:', templateId, templateConfig);
                    
                    // Resetar todos os dados do wizard
                    this.wizardData.pipelineName = '';
                    this.wizardData.description = '';
                    this.wizardData.folder = '';
                    this.wizardData.sourceType = '';
                    this.wizardData.targetTableName = '';
                    this.wizardData.pythonFunction = '';
                    this.wizardData.transformArgs = '{}';
                    this.wizardData.scheduleType = 'manual';
                    this.wizardData.frequency = '0 0 * * *';
                    this.wizardData.selectedFiles = [];
                    this.wizardData.multiUpload = false;
                    this.wizardData.selectFolder = false;
                    this.wizardData.showFileUpload = false;
                    
                    // Gerar nome único com timestamp para evitar duplicação
                    const timestamp = new Date().getTime();
                    const uniqueName = templateConfig.pipelineName + '_' + timestamp;
                    
                    // Preencher wizard com dados do template
                    // REMOVIDO: Preenchimento automático de dag_id e description
                    // this.wizardData.pipelineName = uniqueName;
                    // this.wizardData.description = templateConfig.description || '';
                    this.wizardData.folder = templateConfig.folder || '';
                    this.wizardData.sourceType = templateConfig.sourceType || '';
                    this.wizardData.pythonFunction = templateConfig.pythonFunction || '';
                    this.wizardData.transformArgs = templateConfig.transformArgs || '{}';
                    this.wizardData.scheduleType = templateConfig.scheduleType || 'manual';
                    this.wizardData.frequency = templateConfig.frequency || '';
                    
                    // Ativar visualização de upload se necessário
                    if (templateConfig.requiresFile) {
                        this.wizardData.showFileUpload = true;
                    }
                    
                    // Mudar para wizard
                    this.currentView = 'wizard';
                    this.wizardData.currentStep = 1;
                    
                    // Mostrar instrução
                    setTimeout(() => {
                        const hint = templateConfig.fileHint || 'Template carregado! Navegue pelos passos para revisar e configurar o pipeline.';
                        this.showMessage(hint, 'info');
                    }, 300);
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

                    // Sincronizar campos SQL do wizardData para os inputs do form (garante que o backend sempre receba)
                    const sqlFields = [
                        { name: 'sql_host', value: this.wizardData.dbHost },
                        { name: 'sql_port', value: this.wizardData.dbPort },
                        { name: 'sql_database_name', value: this.wizardData.dbDatabase },
                        { name: 'sql_user', value: this.wizardData.dbUser },
                        { name: 'sql_password', value: this.wizardData.dbPassword },
                        { name: 'sql_connection_id', value: this.wizardData.sqlConnectionId }
                    ];
                    sqlFields.forEach(f => {
                        if (form.querySelector(`[name='${f.name}']`)) {
                            form.querySelector(`[name='${f.name}']`).value = f.value || '';
                        }
                        formData.set(f.name, f.value || '');
                    });

                    // --- PATCH: Garantir selected_tables[] sempre como array ---
                    // Se multi-table está ativado, garantir envio correto
                    const isMultiTable = document.getElementById('is_multi_table')?.checked;
                    if (isMultiTable) {
                        // Limpa qualquer selected_tables[] existente
                        formData.delete('selected_tables[]');
                        // Busca todos os checkboxes marcados
                        const checked = document.querySelectorAll('input[name="selected_tables[]"]:checked');
                        checked.forEach(cb => {
                            formData.append('selected_tables[]', cb.value);
                        });
                        // Log para debug
                        console.log('✅ selected_tables[] enviado:', Array.from(checked).map(cb => cb.value));
                    }

                    // Corrigir: para API REST, garantir que source_filename NÃO seja enviado
                    const sourceTypeName = this.getSourceTypeName(this.wizardData.sourceType).toLowerCase();
                    if (sourceTypeName.includes('api')) {
                        // Remove qualquer valor do campo source_filename (garante que não será enviado)
                        formData.delete('source_filename');
                    }
                    
                    console.log('📤 Enviando pipeline...');
                    
                    // Validar campos obrigatórios ANTES de enviar
                    let debugMsg = '';
                    if (!formData.get('dag_id') || formData.get('dag_id').trim() === '') {
                        debugMsg = 'Nome do Pipeline é obrigatório!';
                        this.showMessage(debugMsg, 'error');
                        this.wizardData.currentStep = 1;
                        window.__lastWizardSubmitError = debugMsg;
                        return;
                    }
                    if (!formData.get('id_pasta') || formData.get('id_pasta').trim() === '') {
                        debugMsg = 'Pasta/Workspace é obrigatório!';
                        this.showMessage(debugMsg, 'error');
                        this.wizardData.currentStep = 1;
                        window.__lastWizardSubmitError = debugMsg;
                        return;
                    }
                    if (!formData.get('id_source_type') || formData.get('id_source_type').trim() === '') {
                        debugMsg = 'Tipo de Fonte é obrigatório!';
                        this.showMessage(debugMsg, 'error');
                        this.wizardData.currentStep = 2;
                        window.__lastWizardSubmitError = debugMsg;
                        return;
                    }
                    if (!formData.get('target_table_name') || formData.get('target_table_name').trim() === '') {
                        debugMsg = 'Nome da Tabela Destino é obrigatório!';
                        this.showMessage(debugMsg, 'error');
                        this.wizardData.currentStep = 2;
                        window.__lastWizardSubmitError = debugMsg;
                        return;
                    }
                    if (!formData.get('python_module_path') || formData.get('python_module_path').trim() === '') {
                        debugMsg = 'Função Python de Transformação é obrigatória!';
                        this.showMessage(debugMsg, 'error');
                        this.wizardData.currentStep = 3;
                        window.__lastWizardSubmitError = debugMsg;
                        return;
                    }
                    
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
                            const msg = `O tamanho total dos arquivos (${totalMB} MB) excede o limite de ${maxSizeMB} MB. Reduza a quantidade de arquivos ou processe em lotes menores.`;
                            this.showMessage(msg, 'error');
                            window.__lastWizardSubmitError = msg;
                            throw new Error(msg);
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
                    
                    // VERIFICAÇÃO CRÍTICA
                    if (!formData.get('target_table_name')) {
                        console.error('❌ ERRO: target_table_name NÃO está no FormData!');
                        console.log('wizardData.targetTableName:', this.wizardData.targetTableName);
                    } else {
                        console.log('✅ target_table_name confirmado:', formData.get('target_table_name'));
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
                            const msg = 'O tamanho total dos arquivos excede o limite do servidor (nginx). Reduza a quantidade de arquivos ou processe em lotes menores.';
                            this.showMessage(msg, 'error');
                            window.__lastWizardSubmitError = msg;
                            throw new Error(msg);
                        }
                        // Verificar se a resposta é JSON antes de parsear
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            // Se não for JSON, tentar ler como texto para debug
                            return response.text().then(text => {
                                const msg = `❌ Resposta não é JSON: ${text.substring(0, 500)}`;
                                this.showMessage(msg, 'error');
                                window.__lastWizardSubmitError = msg;
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
                        let mensagem = result.mensagem || result.message || 'Operação realizada com sucesso';
                        if (result.status === 'success' || result.status === 'partial') {
                            // Se houver arquivos salvos, exibir lista (snake_case)
                            if (result.uploaded_files && Array.isArray(result.uploaded_files) && result.uploaded_files.length > 0) {
                                mensagem += '<br><b>Arquivos salvos no bucket:</b><ul style="margin-top:8px">';
                                result.uploaded_files.forEach(f => {
                                    if (typeof f === 'string') {
                                        mensagem += `<li><code>${f}</code></li>`;
                                    } else if (f.s3_key) {
                                        mensagem += `<li><code>${f.s3_key}</code></li>`;
                                    } else if (f.source_path) {
                                        mensagem += `<li><code>${f.source_path}</code></li>`;
                                    } else {
                                        mensagem += `<li><code>${JSON.stringify(f)}</code></li>`;
                                    }
                                });
                                mensagem += '</ul>';
                            }
                            // Armazenar mensagem de sucesso na sessão via localStorage temporariamente
                            localStorage.setItem('dashboard_message', JSON.stringify({
                                type: 'success',
                                text: mensagem
                            }));
                            // Redirecionar para o dashboard
                            setTimeout(() => {
                                window.location.href = '<?= base_url('dashboard') ?>';
                            }, 2000);
                        } else {
                            // Mostrar erro
                            const errorMsg = result.mensagem || mensagem;
                            if (errorMsg.includes('Já existe um pipeline') || 
                                (errorMsg.includes('Duplicate entry') && errorMsg.includes('dag_id'))) {
                                this.showMessage(
                                    'Nome do pipeline já está em uso. Por favor, volte ao Passo 1 e escolha outro nome.',
                                    'error'
                                );
                            } else {
                                this.showMessage(errorMsg, 'error');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('❌ Erro na requisição:', error);
                        
                        // Verificar se é erro de duplicação de dag_id
                        const errorMsg = error.message || 'Erro ao salvar as informações. Por favor, tente novamente.';
                        
                        if (errorMsg.includes('Já existe um pipeline') || 
                            (errorMsg.includes('Duplicate entry') && errorMsg.includes('dag_id'))) {
                            this.showMessage(
                                'Nome do pipeline já está em uso. Por favor, volte ao Passo 1 e escolha outro nome.',
                                'error'
                            );
                        } else {
                            this.showMessage(errorMsg, 'error');
                        }
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

                    const alertTypes = {
                        'success': { class: 'alert-success', icon: 'check-circle', title: 'Sucesso!' },
                        'error': { class: 'alert-danger', icon: 'exclamation-triangle', title: 'Erro!' },
                        'info': { class: 'alert-info', icon: 'info-circle', title: 'Informação' }
                    };
                    
                    const config = alertTypes[type] || alertTypes['info'];
                    
                    messageDiv.innerHTML = `
                        <div class="alert ${config.class} alert-dismissible fade show" role="alert">
                            <i class="bi bi-${config.icon}"></i>
                            <strong>${config.title}</strong> ${text}
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

    <script>
        function connectAndListTables(wizardData) {
            const connectionId = wizardData.sqlConnectionId;
            const host = wizardData.dbHost;
            const port = wizardData.dbPort || 3306;
            const databaseName = wizardData.dbDatabase;
            const user = wizardData.dbUser;
            const password = wizardData.dbPassword;
            const statusDiv = document.getElementById('connection_status');
            const loadingDiv = document.getElementById('tables-loading');
            const containerDiv = document.getElementById('tables-container');
            if (!connectionId || !host || !databaseName || !user) {
                statusDiv.innerHTML = '<span style="color: red;">❌ Preencha todos os campos obrigatórios (Connection ID, Host, Database, User)</span>';
                statusDiv.style.display = 'block';
                return;
            }
            loadingDiv.style.display = 'block';
            containerDiv.innerHTML = '';
            statusDiv.style.display = 'none';
            const formData = new URLSearchParams();
            formData.append('connection_id', connectionId);
            formData.append('host', host);
            formData.append('port', port);
            formData.append('database_name', databaseName);
            formData.append('user', user);
            formData.append('password', password);
            fetch('<?= base_url('config/getAvailableTables') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString()
            })
            .then(response => response.json())
            .then(result => {
                loadingDiv.style.display = 'none';
                if (result.status === 'success' && result.tables) {
                    if (result.tables.length === 0) {
                        containerDiv.innerHTML = '<p style="color: orange;">⚠️ Nenhuma tabela encontrada</p>';
                    } else {
                        renderTableCheckboxes(result.tables);
                        statusDiv.innerHTML = `<span style="color: green;">✅ ${result.tables.length} tabelas encontradas</span>`;
                        statusDiv.style.display = 'block';
                    }
                } else {
                    const errorMsg = result.mensagem || result.message || 'Erro desconhecido';
                    statusDiv.innerHTML = `<span style="color: red;">❌ ${errorMsg}</span>`;
                    statusDiv.style.display = 'block';
                }
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                statusDiv.innerHTML = `<span style="color: red;">❌ Erro de requisição: ${error.message}</span>`;
                statusDiv.style.display = 'block';
            });
        }
        function renderTableCheckboxes(tables) {
            const container = document.getElementById('tables-container');
            let html = '<div class="tables-selection">';
            html += '<div style="margin-bottom: 10px;"><button type="button" onclick="selectAllTables(true)" class="btn btn-sm">✓ Selecionar Todas</button> ';
            html += '<button type="button" onclick="selectAllTables(false)" class="btn btn-sm">✗ Desmarcar Todas</button></div>';
            html += '<div class="tables-grid">';
            tables.forEach(table => {
                const tableName = table.table_name;
                const rowCount = table.row_count ? `(${table.row_count.toLocaleString()} rows)` : '';
                const tableSize = table.table_size_mb ? `${table.table_size_mb} MB` : '';
                html += `<div class="table-checkbox-item"><input type="checkbox" id="table_${tableName}" name="selected_tables[]" value="${tableName}" class="table-checkbox"><label for="table_${tableName}"><strong>${tableName}</strong> <small>${rowCount} ${tableSize}</small></label></div>`;
            });
            html += '</div></div>';
            container.innerHTML = html;
        }
        function selectAllTables(select) {
            const checkboxes = document.querySelectorAll('.table-checkbox');
            checkboxes.forEach(cb => cb.checked = select);
        }
    </script>
<?php require VIEWPATH . '/footer.php'; ?>
