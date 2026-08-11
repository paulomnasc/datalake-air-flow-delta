<style>
    .eficiencia-header {
        background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%);
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        color: #ffffff;
        margin-bottom: 2rem;
    }
    
    .stat-card-glass {
        background-color: #1e293b !important;
        border: 1px solid #334155 !important;
        border-radius: 14px;
        padding: 1.25rem;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .stat-card-glass:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    }
    
    .stat-card-title {
        color: #94a3b8 !important;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .stat-card-sub {
        color: #cbd5e1 !important;
        font-size: 0.8rem;
    }
    
    .stat-value {
        font-size: 1.85rem;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    
    .badge-status-green {
        background-color: #166534 !important;
        color: #ffffff !important;
        font-weight: 700;
        padding: 0.45em 0.85em;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .badge-status-red {
        background-color: #991b1b !important;
        color: #ffffff !important;
        font-weight: 700;
        padding: 0.45em 0.85em;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .badge-status-void {
        background-color: #0284c7 !important;
        color: #ffffff !important;
        font-weight: 700;
        padding: 0.45em 0.85em;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .badge-status-nobet {
        background-color: #475569 !important;
        color: #ffffff !important;
        font-weight: 700;
        padding: 0.45em 0.85em;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .filter-card {
        background-color: #1e293b !important;
        border: 1px solid #334155 !important;
        border-radius: 12px;
        color: #f8fafc !important;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
    }

    /* Datagrid Table Overrides - Garante fundo escuro e texto de alto contraste em todas as células */
    #datagridEficiencia,
    #datagridEficiencia table,
    #datagridEficiencia tbody,
    #datagridEficiencia tr,
    #datagridEficiencia td {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        border-bottom: 1px solid #1e293b !important;
    }

    #datagridEficiencia th {
        background-color: #1e293b !important;
        color: #cbd5e1 !important;
        font-weight: 700 !important;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        border-bottom: 2px solid #334155 !important;
    }

    .badge-odd-custom {
        background-color: #1e293b !important;
        color: #fbbf24 !important;
        border: 1px solid #475569 !important;
        font-weight: 700 !important;
        font-size: 0.85rem !important;
        padding: 0.35em 0.65em !important;
        border-radius: 6px;
    }
</style>

<div class="container-fluid py-4 px-lg-4">
    <!-- Header Principal -->
    <div class="eficiencia-header">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle bg-success bg-opacity-20 text-success">
                        <i class="bi bi-graph-up-arrow fs-2"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1 text-white">Relatório de Eficiência de Palpites</h2>
                        <p class="text-white-50 mb-0">
                            Acurácia e transparência dos palpites da IA comparados ao resultado real de <strong>jogos encerrados (FT)</strong>.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <span class="badge bg-info text-dark font-weight-bold px-3 py-2 fs-6 rounded-pill border border-info shadow-sm">
                    <i class="bi bi-shield-check me-1"></i> Apenas Jogos Encerrados (FT)
                </span>
            </div>
        </div>
    </div>

    <!-- Cards Superiores de KPI (Solid Dark Backgrounds) -->
    <div class="row g-3 mb-4">
        <!-- Total Jogos Analisados -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass">
                <div class="stat-card-title">Jogos Analisados</div>
                <div class="stat-value mt-1" style="color: #38bdf8;"><?= number_format($totalAnalisados, 0, ',', '.') ?></div>
                <div class="stat-card-sub mt-2">
                    <i class="bi bi-check-all me-1"></i> Encerrados (FT)
                </div>
            </div>
        </div>

        <!-- Entradas Recomendadas -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass">
                <div class="stat-card-title">Recomendadas</div>
                <div class="stat-value mt-1" style="color: #f59e0b;"><?= number_format($entradasRecomendadas, 0, ',', '.') ?></div>
                <div class="stat-card-sub mt-2" style="color: #fbbf24;">
                    Taxa Seleção: <strong><?= $selectionRate ?>%</strong>
                </div>
            </div>
        </div>

        <!-- Taxa de Win (Green) -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #166534 !important;">
                <div class="stat-card-title">Taxa de Win (Green)</div>
                <div class="stat-value mt-1" style="color: #4ade80;"><?= $winRate ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #4ade80;">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= $greenCount ?> Apostas Ganhas
                </div>
            </div>
        </div>

        <!-- Taxa de Perda (Red) -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #991b1b !important;">
                <div class="stat-card-title">Taxa de Perda (Red)</div>
                <div class="stat-value mt-1" style="color: #f87171;"><?= $redRate ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #f87171;">
                    <i class="bi bi-x-circle-fill me-1"></i> <?= $redCount ?> Apostas Perdidas
                </div>
            </div>
        </div>

        <!-- Taxa de Abstenção (No-Bet) -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass">
                <div class="stat-card-title">Abstenção (No-Bet)</div>
                <div class="stat-value mt-1" style="color: #cbd5e1;"><?= $abstentionRate ?>%</div>
                <div class="stat-card-sub mt-2">
                    <i class="bi bi-slash-circle me-1"></i> <?= $noBetCount ?> Sem Entrada
                </div>
            </div>
        </div>

        <!-- ROI Teórico -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #1d4ed8 !important;">
                <div class="stat-card-title">ROI Teórico</div>
                <div class="stat-value mt-1" style="color: <?= $lucroPrejuizoUnidades >= 0 ? '#34d399' : '#f87171' ?>;">
                    <?= ($lucroPrejuizoUnidades >= 0 ? '+' : '') . number_format($lucroPrejuizoUnidades, 2, ',', '.') ?> u
                </div>
                <div class="stat-card-sub mt-2" style="color: <?= $roiPercent >= 0 ? '#34d399' : '#f87171' ?>;">
                    Rendimento: <strong><?= ($roiPercent >= 0 ? '+' : '') . $roiPercent ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros de Pesquisa -->
    <div class="filter-card p-3 p-md-4 mb-4">
        <form method="GET" action="<?= base_url('apostas/relatorio-eficiencia') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label text-white-50 small font-weight-bold">Data Início</label>
                <input type="date" name="start_date" class="form-control bg-dark text-white border-secondary" value="<?= esc($startDate) ?>">
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label text-white-50 small font-weight-bold">Data Fim</label>
                <input type="date" name="end_date" class="form-control bg-dark text-white border-secondary" value="<?= esc($endDate) ?>">
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label text-white-50 small font-weight-bold">Liga / Campeonato</label>
                <select name="league" class="form-select bg-dark text-white border-secondary">
                    <option value="">Todas as Ligas</option>
                    <?php foreach ($ligas as $l): ?>
                        <option value="<?= esc($l->league_name) ?>" <?= ($leagueFilter === $l->league_name) ? 'selected' : '' ?>>
                            <?= esc($l->league_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label text-white-50 small font-weight-bold">Status do Palpite</label>
                <select name="status" class="form-select bg-dark text-white border-secondary">
                    <option value="">Todos os Status</option>
                    <option value="GREEN" <?= ($statusFilter === 'GREEN') ? 'selected' : '' ?>>🟩 GREEN (Ganha)</option>
                    <option value="RED" <?= ($statusFilter === 'RED') ? 'selected' : '' ?>>🟥 RED (Perdida)</option>
                    <option value="VOID" <?= ($statusFilter === 'VOID') ? 'selected' : '' ?>>🟦 VOID (Anulada)</option>
                    <option value="NO_BET" <?= ($statusFilter === 'NO_BET') ? 'selected' : '' ?>>⚪ NO-BET (Abstenção)</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-success w-100 fw-bold">
                    <i class="bi bi-filter me-1"></i> Filtrar
                </button>
                <a href="<?= base_url('apostas/relatorio-eficiencia') ?>" class="btn btn-outline-secondary" title="Limpar Filtros">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela Principal de Jogos Encerrados -->
    <div class="card bg-dark text-white border-secondary shadow-lg">
        <div class="card-header bg-dark border-secondary py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-white d-flex align-items-center gap-2">
                <i class="bi bi-list-stars text-success"></i>
                Histórico de Eficiência dos Jogos Encerrados
            </h5>
            <div class="d-flex align-items-center gap-2">
                <button onclick="exportDatagridToCsv()" class="btn btn-outline-success btn-sm fw-bold d-flex align-items-center gap-1">
                    <i class="bi bi-file-earmark-spreadsheet-fill"></i> Exportar CSV
                </button>
                <span class="badge bg-secondary text-white fw-bold">
                    Exibindo <?= count($palpites) ?> registros
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0" id="datagridEficiencia">
                <thead>
                    <tr>
                        <th class="ps-3">Data / Hora</th>
                        <th>Time Esquerda (Casa)</th>
                        <th>Time Direita (Fora)</th>
                        <th>Liga</th>
                        <th>Mercado / Sugestão</th>
                        <th>Resultado Real (FT)</th>
                        <th>Odd</th>
                        <th class="pe-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($palpites)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-white-50">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhum jogo encerrado encontrado para os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($palpites as $p): ?>
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    <div class="fw-bold text-white"><?= date('d/m/Y', strtotime($p->fixture_date)) ?></div>
                                    <div class="small text-white-50"><?= date('H:i', strtotime($p->fixture_date)) ?> hs</div>
                                </td>

                                <!-- Time Esquerda (Casa / Mandante) -->
                                <td>
                                    <div class="fw-bold text-white d-flex align-items-center gap-2">
                                        <i class="bi bi-house-door-fill text-primary"></i>
                                        <span style="color: #60a5fa !important; font-weight: 700;"><?= esc($p->home_team) ?></span>
                                    </div>
                                </td>

                                <!-- Time Direita (Fora / Visitante) -->
                                <td>
                                    <div class="fw-bold text-white d-flex align-items-center gap-2">
                                        <i class="bi bi-airplane-fill text-info"></i>
                                        <span style="color: #38bdf8 !important; font-weight: 700;"><?= esc($p->away_team) ?></span>
                                    </div>
                                </td>

                                <!-- Liga -->
                                <td>
                                    <span class="badge bg-secondary text-white border border-secondary">
                                        <?= esc($p->league_name) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if (strtoupper($p->resultado_status) === 'NO_BET'): ?>
                                        <span class="text-white-50 fst-italic">
                                            <i class="bi bi-dash-circle me-1"></i> Sem Entrada (Abstenção)
                                        </span>
                                    <?php else: ?>
                                        <div class="fw-bold text-warning"><?= esc($p->linha_sugerida) ?></div>
                                        <div class="small text-white-50"><?= esc($p->mercado) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-1 mb-1 flex-wrap">
                                        <span class="badge bg-primary text-white px-2 py-1 fs-6" title="Gols Mandante: <?= esc($p->home_team) ?>">
                                            <?= esc($p->goals_home ?? 0) ?>
                                        </span>
                                        <span class="text-white-50 font-weight-bold">x</span>
                                        <span class="badge bg-info text-dark font-weight-bold px-2 py-1 fs-6" title="Gols Visitante: <?= esc($p->away_team) ?>">
                                            <?= esc($p->goals_away ?? 0) ?>
                                        </span>
                                        <span class="small text-white-50 ms-1 text-nowrap">
                                            (<strong><?= esc($p->home_team) ?></strong> <?= esc($p->goals_home ?? 0) ?> - <?= esc($p->goals_away ?? 0) ?> <strong><?= esc($p->away_team) ?></strong>)
                                        </span>
                                    </div>
                                    <div class="small text-white-50">
                                        🟨 Cartões: <?= ((int)($p->yellow_cards_home ?? 0) + (int)($p->yellow_cards_away ?? 0) + (int)($p->red_cards_home ?? 0) + (int)($p->red_cards_away ?? 0)) ?>
                                        | 🚩 Escanteios: <?= ((int)($p->corners_home ?? 0) + (int)($p->corners_away ?? 0)) ?>
                                    </div>
                                </td>

                                <td>
                                    <?php if (!empty($p->odd_momento) && (float)$p->odd_momento > 1.0): ?>
                                        <span class="badge badge-odd-custom">
                                            @<?= number_format($p->odd_momento, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-white-50">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="pe-3 text-center">
                                    <?php
                                        $st = strtoupper($p->resultado_status);
                                        if ($st === 'GREEN') {
                                            echo '<span class="badge badge-status-green"><i class="bi bi-check-lg me-1"></i> GREEN</span>';
                                        } elseif ($st === 'RED') {
                                            echo '<span class="badge badge-status-red"><i class="bi bi-x-lg me-1"></i> RED</span>';
                                        } elseif ($st === 'VOID') {
                                            echo '<span class="badge badge-status-void"><i class="bi bi-arrow-counterclockwise me-1"></i> VOID</span>';
                                        } elseif ($st === 'NO_BET') {
                                            echo '<span class="badge badge-status-nobet"><i class="bi bi-slash-circle me-1"></i> NO-BET</span>';
                                        } else {
                                            echo '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> PENDING</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const rawPalpitesData = <?= json_encode(array_map(function($p) {
        $totCards = ((int)($p->yellow_cards_home ?? 0) + (int)($p->yellow_cards_away ?? 0) + (int)($p->red_cards_home ?? 0) + (int)($p->red_cards_away ?? 0));
        $totCorners = ((int)($p->corners_home ?? 0) + (int)($p->corners_away ?? 0));
        return [
            'data' => date('d/m/Y', strtotime($p->fixture_date)),
            'hora' => date('H:i', strtotime($p->fixture_date)),
            'mandante' => $p->home_team,
            'visitante' => $p->away_team,
            'liga' => $p->league_name,
            'mercado' => $p->mercado,
            'sugestao' => $p->linha_sugerida,
            'placar' => ($p->goals_home ?? 0) . ' x ' . ($p->goals_away ?? 0),
            'cartoes' => $totCards,
            'escanteios' => $totCorners,
            'odd' => !empty($p->odd_momento) ? number_format($p->odd_momento, 2, '.', '') : '',
            'status' => strtoupper($p->resultado_status)
        ];
    }, $palpites)) ?>;

    function exportDatagridToCsv() {
        if (!rawPalpitesData || rawPalpitesData.length === 0) {
            alert('Nenhum registro para exportar.');
            return;
        }

        const headers = ['Data', 'Hora', 'Mandante', 'Visitante', 'Liga', 'Mercado', 'Sugestao', 'Placar_FT', 'Cartoes', 'Escanteios', 'Odd', 'Status'];
        const rows = [headers];

        rawPalpitesData.forEach(item => {
            rows.push([
                `"${item.data}"`,
                `"${item.hora}"`,
                `"${item.mandante.replace(/"/g, '""')}"`,
                `"${item.visitante.replace(/"/g, '""')}"`,
                `"${item.liga.replace(/"/g, '""')}"`,
                `"${item.mercado.replace(/"/g, '""')}"`,
                `"${item.sugestao.replace(/"/g, '""')}"`,
                `"${item.placar}"`,
                item.cartoes,
                item.escanteios,
                item.odd,
                `"${item.status}"`
            ]);
        });

        const csvContent = '\uFEFF' + rows.map(e => e.join(';')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const today = new Date().toISOString().slice(0, 10);
        link.setAttribute('href', url);
        link.setAttribute('download', `eficiencia_palpites_${today}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
