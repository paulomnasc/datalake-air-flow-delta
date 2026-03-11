

<style>
.admin-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
}

.dashboard-header {
    margin-bottom: 40px;
}

.dashboard-header h1 {
    font-size: 36px;
    font-weight: 700;
    color: #333;
    margin: 0 0 10px 0;
}

.dashboard-header p {
    color: #666;
    font-size: 16px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stat-card.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stat-card.success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.stat-card.warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.stat-card.info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.stat-value {
    font-size: 36px;
    font-weight: bold;
    margin: 8px 0;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
    font-weight: 500;
}

.stat-secondary {
    font-size: 18px;
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,0.2);
}

.content-section {
    background: white;
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 32px;
}

.section-title {
    font-size: 24px;
    font-weight: 600;
    color: #333;
    margin: 0 0 24px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.activity-list {
    max-height: 500px;
    overflow-y: auto;
}

.activity-item {
    padding: 16px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: #f8f9fa;
}

.activity-user {
    flex: 1;
}

.activity-user-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.activity-user-email {
    font-size: 13px;
    color: #666;
}

.activity-stats {
    text-align: right;
}

.activity-count {
    font-weight: 600;
    color: #667eea;
    margin-bottom: 4px;
}

.activity-time {
    font-size: 12px;
    color: #999;
}

.ranking-table {
    width: 100%;
    border-collapse: collapse;
}

.ranking-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #666;
    font-size: 13px;
    text-transform: uppercase;
    border-bottom: 2px solid #dee2e6;
}

.ranking-table td {
    padding: 16px 12px;
    border-bottom: 1px solid #eee;
}

.ranking-table tr:hover {
    background: #f8f9fa;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-weight: bold;
    color: white;
}

.rank-1 { background: #ffd700; }
.rank-2 { background: #c0c0c0; }
.rank-3 { background: #cd7f32; }
.rank-other { background: #667eea; }

.progress-bar-container {
    width: 100%;
    height: 8px;
    background: #e0e7ff;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    transition: width 0.3s ease;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
}
</style>

<script>
// Refresh automático a cada 10 segundos
setTimeout(function() {
    window.location.reload();
}, 40000);
</script>

<div id="content">
    <div class="admin-dashboard">
        <div class="dashboard-header">
            <h1>📊 Dashboard Administrativo</h1>
            <p>Visão geral do sistema e progresso dos alunos</p>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">👥</div>
                <div class="stat-value" title="Contagem de registros na tabela 'usuario'.">
                    <?php echo number_format($total_users); ?>
                </div>
                <div class="stat-label">
                    Total de Usuários
                    <span title="Fórmula: SELECT COUNT(*) FROM usuario">🛈</span>
                </div>
                <div class="stat-secondary">
                    🔥 <?php echo $active_users_last_7_days; ?> ativos nos últimos 7 dias
                    <span title="Contagem de usuários com atividade nos últimos 7 dias.">🛈</span>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">📈</div>
                <div class="stat-value" title="Usuários com pelo menos um fluxo (dag_configuration).">
                    <?php echo $users_with_flows; ?>
                </div>
                <div class="stat-label">
                    Usuários com Fluxos
                    <span title="Fórmula: SELECT COUNT(DISTINCT u.id) FROM usuario u INNER JOIN pasta p ON p.id_usuario = u.id INNER JOIN dag_configurations d ON d.id_pasta = p.id">🛈</span>
                </div>
                <div class="stat-secondary">
                    <?php echo $percent_users_with_flows; ?>% do total
                    <span title="Fórmula: (Usuários com Fluxos / Total de Usuários) × 100">🛈</span>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">🎓</div>
                <div class="stat-value" title="Percentual de tarefas concluídas.">
                    <?php echo $course_progress_percent; ?>%
                </div>
                <div class="stat-label">
                    Progresso Geral dos Cursos
                    <span title="Fórmula: (Tarefas Concluídas / Total de Tarefas) × 100">🛈</span>
                </div>
                <div class="stat-secondary">
                    ✅ <?php echo number_format($completed_tasks_count); ?>/<?php echo number_format($total_tasks); ?> tarefas
                    <span title="Tarefas concluídas e total de tarefas ativas.">🛈</span>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-icon">⭐</div>
                <div class="stat-value" title="Média de XP ganho por aluno (trial ou active)">
                    <?php echo number_format($total_xp_earned); ?>
                </div>
                <div class="stat-label">
                    XP Médio Ganho por Aluno
                    <span title="Fórmula: AVG(SUM(xp_points) WHERE tarefa concluída por aluno)">🛈</span>
                </div>
                <div class="stat-secondary">
                    De <?php echo number_format($total_xp_available); ?> XP disponíveis
                    <span title="Soma dos pontos XP de todas tarefas ativas.">🛈</span>
                </div>
            </div>
        </div>

        <!-- Estatísticas de Curso -->
        <div class="content-section">
            <h2 class="section-title">📚 Estatísticas dos Cursos</h2>
            <div class="stats-grid">
                <div style="text-align: center;">
                    <div style="font-size: 32px; color: #667eea; font-weight: bold;"><?php echo $total_courses; ?></div>
                    <div style="color: #666; margin-top: 8px;">Cursos Ativos</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 32px; color: #667eea; font-weight: bold;"><?php echo $total_modules; ?></div>
                    <div style="color: #666; margin-top: 8px;">Módulos</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 32px; color: #667eea; font-weight: bold;"><?php echo $total_videos; ?></div>
                    <div style="color: #666; margin-top: 8px;">Vídeos</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 32px; color: #667eea; font-weight: bold;"><?php echo $total_students; ?></div>
                    <div style="color: #666; margin-top: 8px;">Alunos Totais</div>
                </div>
            </div>
        </div>

        <!-- Ranking de Alunos -->
        <!-- Alunos que retornaram após cadastro -->
        <div class="content-section">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <h2 class="section-title" style="margin-bottom: 0;">🔄 Alunos que Retornaram Após Cadastro</h2>
                <a href="<?php echo site_url('admin/downloadReturningStudentsCsv'); ?>" class="btn btn-sm btn-primary" style="margin-left: 16px; background: #667eea; color: #fff; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-weight: 500;">⬇️ Download CSV</a>
            </div>
            <!-- DataTables CSS/JS -->
            <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
            <?php if (!empty($returning_students)): ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="ranking-table" id="returningStudentsTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Aluno</th>
                                <th>Email</th>
                                <th style="text-align: center;">Retornos</th>
                                <th style="text-align: right;">Último Retorno</th>
                                <th style="text-align: right;">Criado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returning_students as $index => $student): 
                                $rank = $index + 1;
                                $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-other";
                            ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></span>
                                    </td>
                                    <td style="font-weight: 600;\"><?php echo esc($student->user_name); ?></td>
                                    <td style="color: #666;\"><?php echo esc($student->email); ?></td>
                                    <td style="text-align: center;\">
                                        <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 12px; font-weight: 600;\">
                                            <?php echo $student->return_count; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;\">
                                        <span style="font-size: 14px; color: #667eea;\">
                                            <?php 
                                                if (!empty($student->last_return)) {
                                                    $lastReturn = new DateTime($student->last_return);
                                                    echo $lastReturn->format('d/m/Y H:i');
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;\">
                                        <span style="font-size: 14px; color: #999;\">
                                            <?php 
                                                if (!empty($student->criado_em)) {
                                                    $created = new DateTime($student->criado_em);
                                                    echo $created->format('d/m/Y H:i');
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <script>
                    $(document).ready(function() {
                        $('#returningStudentsTable').DataTable({
                            language: {
                                "sEmptyTable": "Nenhum registro encontrado",
                                "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                                "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
                                "sInfoFiltered": "(Filtrados de _MAX_ registros)",
                                "sInfoPostFix": "",
                                "sInfoThousands": ".",
                                "sLengthMenu": "_MENU_ resultados por página",
                                "sLoadingRecords": "Carregando...",
                                "sProcessing": "Processando...",
                                "sZeroRecords": "Nenhum registro encontrado",
                                "sSearch": "Pesquisar",
                                "oPaginate": {
                                    "sNext": "Próximo",
                                    "sPrevious": "Anterior",
                                    "sFirst": "Primeiro",
                                    "sLast": "Último"
                                },
                                "oAria": {
                                    "sSortAscending": ": Ordenar colunas de forma ascendente",
                                    "sSortDescending": ": Ordenar colunas de forma descendente"
                                }
                            }
                        });
                    });
                    </script>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🔄</div>
                    <p>Nenhum aluno retornou após cadastro ainda</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Detalhes de Progresso por Aluno (SOLICITADO) -->
        <div class="content-section">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <h2 class="section-title" style="margin-bottom: 0;">📊 Detalhes de Progresso por Aluno</h2>
                <a href="<?php echo site_url('admin/downloadStudentProgressCsv'); ?>" class="btn btn-sm btn-success" style="background: #28a745; color: #fff; padding: 8px 18px; border-radius: 6px; text-decoration: none; font-weight: 500;">⬇️ Exportar CSV</a>
            </div>
            
            <?php if (!empty($student_progress)): ?>
                <div class="table-responsive">
                    <table class="ranking-table" id="studentProgressTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Aluno</th>
                                <th>Progresso Vídeos</th>
                                <th>Progresso Tarefas</th>
                                <th>Último Vídeo/Módulo</th>
                                <th>Última URI</th>
                                <th style="text-align: right;">Último Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_progress as $index => $student): 
                                $rank = $index + 1;
                                $videoPercent = round($student->video_progress, 1);
                                $taskPercent = $student->total_tasks_available > 0 
                                    ? round(($student->tasks_completed / $student->total_tasks_available) * 100, 1) 
                                    : 0;
                            ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-other" style="width: 28px; height: 28px; font-size: 12px;"><?php echo $rank; ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #333;"><?php echo esc($student->user_name); ?></div>
                                        <div style="font-size: 12px; color: #999;"><?php echo esc($student->email); ?></div>
                                    </td>
                                    <td style="width: 250px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="progress-bar-container" style="flex: 1; height: 6px;">
                                                <div class="progress-bar-fill" style="width: <?php echo $videoPercent; ?>%; background: linear-gradient(90deg, #4facfe, #00f2fe);"></div>
                                            </div>
                                            <span style="font-weight: 600; font-size: 13px; min-width: 45px;"><?php echo $videoPercent; ?>%</span>
                                        </div>
                                    </td>
                                    <td style="width: 250px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="progress-bar-container" style="flex: 1; height: 6px;">
                                                <div class="progress-bar-fill" style="width: <?php echo $taskPercent; ?>%; background: linear-gradient(90deg, #11998e, #38ef7d);"></div>
                                            </div>
                                            <span style="font-weight: 600; font-size: 13px; min-width: 45px;"><?php echo $student->tasks_completed; ?>/<?php echo $student->total_tasks_available; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: #555; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc($student->last_content); ?>">
                                            <?php echo $student->last_content ? esc($student->last_content) : '<i style="color:#ccc;">Nenhum</i>'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; color: #666; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc($student->last_uri); ?>">
                                            <?php echo $student->last_uri ? esc($student->last_uri) : '<i style="color:#ccc;">-</i>'; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <span style="font-size: 13px; color: #666;">
                                            <?php 
                                                if (!empty($student->last_login)) {
                                                    $lastLogin = new DateTime($student->last_login);
                                                    echo $lastLogin->format('d/m/Y H:i');
                                                } else {
                                                    echo '<i style="color:#ccc;">Nunca</i>';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <script>
                $(document).ready(function() {
                    if (!$.fn.DataTable.isDataTable('#studentProgressTable')) {
                        $('#studentProgressTable').DataTable({
                            order: [[2, 'desc'], [3, 'desc']], // Ordenar por progresso de vídeo depois tarefas
                            language: {
                                "sEmptyTable": "Nenhum aluno encontrado",
                                "sInfo": "Mostrando _START_ até _END_ de _TOTAL_ alunos",
                                "sSearch": "Buscar aluno:",
                                "oPaginate": { "sNext": "Próximo", "sPrevious": "Anterior" }
                            }
                        });
                    }
                });
                </script>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <p>Nenhum dado de progresso disponível</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ranking de Alunos -->
        <div class="content-section">
            <h2 class="section-title">🏆 Top 10 Alunos por XP</h2>
            <?php if (!empty($top_students)): ?>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Aluno</th>
                            <th>Email</th>
                            <th style="text-align: center;">Tarefas</th>
                            <th style="text-align: right;">XP Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_students as $index => $student): 
                            $rank = $index + 1;
                            $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-other";
                        ?>
                            <tr>
                                <td>
                                    <span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></span>
                                </td>
                                <td style="font-weight: 600;"><?php echo esc($student->nome); ?></td>
                                <td style="color: #666;"><?php echo esc($student->email); ?></td>
                                <td style="text-align: center;">
                                    <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 12px; font-weight: 600;">
                                        <?php echo $student->tasks_completed; ?> ✓
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <span style="font-size: 18px; font-weight: bold; color: #ffd700;">
                                        <?php echo number_format($student->total_xp); ?> XP
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <p>Nenhum aluno com XP ainda</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Atividades Recentes -->
        <div class="content-section">
            <h2 class="section-title">🔥 Alunos Ativos (Últimos 7 Dias)</h2>
            <!-- Gráfico de barras dos últimos 7 dias -->
            <canvas id="activeUsersChart" height="80" style="margin-bottom:32px;"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                // Gera os dados para o gráfico a partir do PHP
                <?php
                // Inicializa array de datas e contagem
                $days = [];
                $counts = [];
                $dateMap = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = (new DateTime("-$i days"))->format('Y-m-d');
                    $days[] = (new DateTime("-$i days"))->format('d/m');
                    $dateMap[$date] = 0;
                }
                // Conta quantos usuários únicos tiveram atividade em cada dia
                if (!empty($recent_activities)) {
                    foreach ($recent_activities as $activity) {
                        $last = (new DateTime($activity->last_activity))->format('Y-m-d');
                        if (isset($dateMap[$last])) {
                            $dateMap[$last]++;
                        }
                    }
                }
                foreach ($dateMap as $count) {
                    $counts[] = $count;
                }
                ?>
                const ctx = document.getElementById('activeUsersChart').getContext('2d');
                const activeUsersChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($days); ?>,
                        datasets: [{
                            label: 'Alunos ativos por dia',
                            data: <?php echo json_encode($counts); ?>,
                            backgroundColor: 'rgba(102, 126, 234, 0.7)',
                            borderColor: 'rgba(102, 126, 234, 1)',
                            borderWidth: 2,
                            borderRadius: 6,
                            maxBarThickness: 40
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.parsed.y + ' aluno(s) ativo(s)';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1, precision: 0 }
                            }
                        }
                    }
                });
            </script>
            <!-- Lista de atividades recentes -->
            <?php if (!empty($recent_activities)): ?>
                <div class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-user">
                                <div class="activity-user-name"><?php echo esc($activity->user_name); ?></div>
                                <div class="activity-user-email"><?php echo esc($activity->email); ?></div>
                                <div class="activity-user-created">
                                    <span style="font-size:12px;color:#999;">Criado em: 
                                        <?php 
                                            if (!empty($activity->criado_em)) {
                                                $created = new DateTime($activity->criado_em);
                                                echo $created->format('d/m/Y H:i');
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <div class="activity-stats">
                                <div class="activity-count"><?php echo $activity->activity_count; ?> ações</div>
                                <div class="activity-time">
                                    <?php 
                                        $lastActivity = new DateTime($activity->last_activity);
                                        $now = new DateTime();
                                        $diff = $now->diff($lastActivity);
                                        if ($diff->d > 0) {
                                            echo $diff->d . ' dia' . ($diff->d > 1 ? 's' : '') . ' atrás';
                                        } elseif ($diff->h > 0) {
                                            echo $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
                                        } elseif ($diff->i > 0) {
                                            echo $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
                                        } else {
                                            echo 'Agora mesmo';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💤</div>
                    <p>Nenhuma atividade nos últimos 7 dias</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Progresso dos Cursos -->
        <div class="content-section">
            <h2 class="section-title">📚 Progresso por Curso</h2>
            <?php if (!empty($courses_progress)): ?>
                <?php foreach ($courses_progress as $course): 
                    $progressPercent = isset($course->media_progresso) 
                        ? round($course->media_progresso, 2) 
                        : 0;
                ?>
                    <div style="margin-bottom: 32px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div>
                                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #333;">
                                    <?php echo esc($course->course_name); ?>
                                </h3>
                                <p style="margin: 4px 0 0 0; color: #666; font-size: 14px;">
                                    <?php echo $course->module_count; ?> módulos • 
                                    <?php echo $course->video_count; ?> vídeos • 
                                    <?php echo $course->task_count; ?> tarefas
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 24px; font-weight: bold; color: #667eea;">
                                    <?php echo $progressPercent; ?>%
                                </div>
                                <div style="font-size: 13px; color: #999;">
                                    <?php echo number_format($course->earned_xp); ?>/<?php echo number_format($course->total_xp); ?> XP
                                </div>
                            </div>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <p>Nenhum curso cadastrado ainda</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


