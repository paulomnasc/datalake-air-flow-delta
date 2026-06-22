<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<!-- Referências do FullCalendar (CDN) -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<div id="content">        
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-chart-line"></i> Painel Ágil e Métricas</h4>
            <a href="<?= route_to('agile.demandas') ?>" class="btn btn-primary"><i class="fas fa-tasks"></i> Ir para Demandas</a>
        </div>

        <!-- Indicadores Rápidos -->
        <div class="row g-3 mb-4">
            <!-- Card 1: Rejeições de Homologação -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-danger-subtle text-danger p-3 me-3 fs-3">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Taxa de Rejeição (PO)</h6>
                            <h4 class="mb-0 font-monospace"><?= number_format($rejeicoesData->taxa_rejeicao ?? 0, 1) ?>%</h4>
                            <small class="text-muted"><?= $rejeicoesData->total_rejeitados ?? 0 ?> rejeições de <?= $rejeicoesData->total_pareceres ?? 0 ?> avaliações</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Total de Cerimônias -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-success-subtle text-success p-3 me-3 fs-3">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Ritos e Cerimônias</h6>
                            <?php 
                            $eventos = json_decode($eventosCalendario ?? '[]');
                            $realizados = 0;
                            foreach ($eventos as $e) {
                                if (strpos($e->className, 'bg-success') !== false) {
                                    $realizados++;
                                }
                            }
                            ?>
                            <h4 class="mb-0 font-monospace"><?= count($eventos) ?> Registradas</h4>
                            <small class="text-muted"><?= $realizados ?> concluídas (com atas descritivas)</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Status das Demandas -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded-circle bg-primary-subtle text-primary p-3 me-3 fs-3">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Demandas em Produção</h6>
                            <?php
                            $db = \Config\Database::connect();
                            $prodCount = $db->query("SELECT COUNT(*) as total FROM agile_demandas WHERE status LIKE 'Atualizado Produção%'")->getRow()->total;
                            $totalCount = $db->query("SELECT COUNT(*) as total FROM agile_demandas")->getRow()->total;
                            ?>
                            <h4 class="mb-0 font-monospace"><?= $prodCount ?> Concluídas</h4>
                            <small class="text-muted">Total de <?= $totalCount ?> demandas registradas no sistema</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seções Principais: Lead Time e Calendário -->
        <div class="row g-4">
            <!-- Gráfico de Lead Time por Raia -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="mb-0 card-title"><i class="fas fa-hourglass-half"></i> Lead Time Médio por Raia (Dias)</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($leadTimeData)): ?>
                            <p class="text-muted text-center py-5">Nenhum dado de tramitação disponível.</p>
                        <?php else: ?>
                            <p class="text-muted small mb-4">Tempo médio gasto pelas demandas desde a criação até a última atualização em cada raia/status do fluxo.</p>
                            
                            <?php foreach ($leadTimeData as $row): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1 small text-dark">
                                        <span><strong><?= htmlspecialchars($row->status) ?></strong></span>
                                        <span><?= number_format($row->media_dias, 1) ?> dias (<?= $row->total_demandas ?> demandas)</span>
                                    </div>
                                    <div class="progress" style="height: 12px;">
                                        <?php 
                                        // Normaliza tamanho da barra baseado em um limite máximo de 30 dias
                                        $percent = min(100, ($row->media_dias / 30) * 100);
                                        $barColor = 'bg-primary';
                                        if ($row->status === 'Homologação') $barColor = 'bg-warning';
                                        if (strpos($row->status, 'Produção') !== false) $barColor = 'bg-success';
                                        if (strpos($row->status, 'SERPRO') !== false) $barColor = 'bg-danger';
                                        ?>
                                        <div class="progress-bar <?= $barColor ?>" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Calendário Mensal dos Ritos -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white py-3">
                        <h5 class="mb-0 card-title"><i class="fas fa-calendar-alt"></i> Calendário Mensal de Ritos</h5>
                    </div>
                    <div class="card-body p-3">
                        <div id="calendar" style="max-height: 500px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana'
            },
            events: <?= $eventosCalendario ?>,
            eventClick: function(info) {
                // Ao clicar no rito, exibe detalhes básicos
                alert(
                    "Cerimônia: " + info.event.title + "\n" +
                    "Agendamento: " + info.event.start.toLocaleString() + "\n\n" +
                    "Ata descritiva:\n" + (info.event.extendedProps.description || "Nenhuma ata registrada.")
                );
            }
        });
        calendar.render();
    }
});
</script>

<?php
require VIEWPATH.'/footer.php';
?>
