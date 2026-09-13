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
                        <h2 class="fw-bold mb-1 text-white"><?= lang('App.tips_efficiency') ?></h2>
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
                <div class="stat-card-title"><?= lang('App.total_games') ?></div>
                <div class="stat-value mt-1" style="color: #38bdf8;"><?= number_format($totalAnalisados, 0, ',', '.') ?></div>
                <div class="stat-card-sub mt-2" style="color: #fbbf24;">
                    <i class="bi bi-bullseye me-1"></i> <?= $entradasRecomendadas ?> Recomendadas (<?= $selectionRate ?>%)
                </div>
            </div>
        </div>

        <!-- Taxa de Win (Green) -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #166534 !important;">
                <div class="stat-card-title"><?= lang('App.win_rate') ?></div>
                <div class="stat-value mt-1" style="color: #4ade80;"><?= $winRate ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #4ade80;">
                    <i class="bi bi-check-circle-fill me-1"></i> <?= $greenCount ?> <?= lang('App.won') ?> / <?= $voidCount ?> Void
                </div>
            </div>
        </div>

        <!-- Taxa Real de Perda (Red) com Meta Regra 7 -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #991b1b !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="stat-card-title"><?= lang('App.lost') ?> (Reds)</div>
                    <?php if ($regra7Status === 'DENTRO_META'): ?>
                        <span class="badge bg-success text-white" style="font-size: 0.65rem;" title="Dentro da meta da Regra 7 do AGENTS.md (10% a 20% de Reds)">
                            <i class="bi bi-shield-check"></i> Regra 7 OK
                        </span>
                    <?php elseif ($regra7Status === 'EXCELENTE'): ?>
                        <span class="badge bg-primary text-white" style="font-size: 0.65rem;" title="Desempenho excelente abaixo de 10% de Reds">
                            <i class="bi bi-stars"></i> Regra 7 Top
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger text-white" style="font-size: 0.65rem;" title="Alerta: Red Rate acima de 20% das apostas decididas">
                            <i class="bi bi-exclamation-triangle-fill"></i> Alerta Regra 7
                        </span>
                    <?php endif; ?>
                </div>
                <div class="stat-value mt-1" style="color: #f87171;"><?= $redRate ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #fca5a5;">
                    <i class="bi bi-x-circle-fill me-1"></i> <?= $redCount ?> Reds (<?= $redRateTotal ?>% do total)
                </div>
            </div>
        </div>

        <!-- Cobertura Real vs. Projetada Poisson -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #0284c7 !important;">
                <div class="stat-card-title">Cobertura Real vs Proj.</div>
                <div class="stat-value mt-1" style="color: #38bdf8;"><?= $coberturaReal ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #93c5fd;" title="Cobertura real entregue (Greens + Voids) vs Projetada por Poisson">
                    Proj: <strong><?= $coberturaProjetada ?>%</strong>
                    <span class="ms-1 badge <?= $gapCobertura >= 0 ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size: 0.65rem;">
                        <?= ($gapCobertura >= 0 ? '+' : '') . $gapCobertura ?> pp
                    </span>
                </div>
            </div>
        </div>

        <!-- Taxa de Abstenção (No-Bet) -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass">
                <div class="stat-card-title">Abstenção (No-Bet)</div>
                <div class="stat-value mt-1" style="color: #cbd5e1;"><?= $abstentionRate ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #94a3b8;">
                    <i class="bi bi-slash-circle me-1"></i> <?= $noBetCount ?> Sem Entrada (Risco)
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

    <!-- Widget Comparativo de Segmentação de Risco -->
    <div class="row g-3 mb-4">
        <?php if (!empty($segmentacao)): ?>
            <?php foreach ($segmentacao as $k => $seg): ?>
                <div class="col-12 col-md-4">
                    <div class="stat-card-glass h-100" style="border-top: 3px solid <?= $seg['color'] ?> !important;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-bold d-flex align-items-center gap-2" style="color: <?= $seg['color'] ?>;">
                                <i class="bi <?= $seg['icon'] ?> fs-5"></i>
                                <span><?= esc($seg['label']) ?></span>
                            </div>
                            <span class="badge bg-dark border border-secondary text-white-50 small">
                                <?= $seg['total'] ?> entradas
                            </span>
                        </div>
                        <div class="row g-2 text-center my-1">
                            <div class="col-4">
                                <div class="small text-white-50">Win Rate</div>
                                <div class="fw-bold fs-5 text-success"><?= $seg['winRate'] ?>%</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-white-50">Red Rate</div>
                                <div class="fw-bold fs-5 text-danger"><?= $seg['redRate'] ?>%</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-white-50">Cobertura</div>
                                <div class="fw-bold fs-5 text-info"><?= $seg['cobertura'] ?>%</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top border-secondary border-opacity-25 small text-white-50">
                            <span>🟩 <?= $seg['green'] ?>G / 🟥 <?= $seg['red'] ?>R / 🟦 <?= $seg['void'] ?>V</span>
                            <span class="fw-bold" style="color: <?= $seg['lucro'] >= 0 ? '#34d399' : '#f87171' ?>;">
                                ROI: <?= ($seg['roi'] >= 0 ? '+' : '') . $seg['roi'] ?>% (<?= ($seg['lucro'] >= 0 ? '+' : '') . $seg['lucro'] ?> u)
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Filtros de Pesquisa -->
    <div class="filter-card p-3 p-md-4 mb-4">
        <form method="GET" action="<?= base_url('apostas/relatorio-eficiencia') ?>" class="row g-3 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label text-white-50 small font-weight-bold">Atalho de Período</label>
                <select id="preset_eficiencia" class="form-select bg-dark text-info border-secondary fw-semibold" onchange="applyEficienciaPreset(this.value)">
                    <option value="custom">📅 Personalizado</option>
                    <option value="today">⚡ Hoje</option>
                    <option value="yesterday">⏪ Ontem</option>
                    <option value="7days">🗓️ Últimos 7 dias</option>
                    <option value="15days">🗓️ Últimos 15 dias</option>
                    <option value="1month">📅 Último mês</option>
                    <option value="trimestre">📊 Trimestre</option>
                    <option value="semestre">📈 Semestre</option>
                    <option value="all">♾️ Todo o período</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label text-white-50 small font-weight-bold">Data Início</label>
                <input type="date" name="start_date" id="eficiencia_start_date" class="form-control bg-dark text-white border-secondary" value="<?= esc($startDate) ?>">
            </div>
            
            <div class="col-12 col-md-2">
                <label class="form-label text-white-50 small font-weight-bold">Data Fim</label>
                <input type="date" name="end_date" id="eficiencia_end_date" class="form-control bg-dark text-white border-secondary" value="<?= esc($endDate) ?>">
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
                <label class="form-label text-white-50 small font-weight-bold">Mercado / Palpite</label>
                <select name="market" class="form-select bg-dark text-white border-secondary">
                    <option value="">Todos os Mercados</option>
                    <option value="AH_DEFENSIVE" <?= ($marketFilter === 'AH_DEFENSIVE') ? 'selected' : '' ?>>🛡️ Linhas Defensivas (+AH / 0.0)</option>
                    <option value="AH_AGGRESSIVE" <?= ($marketFilter === 'AH_AGGRESSIVE' || $marketFilter === 'AH_MINUS' || $marketFilter === '-AH') ? 'selected' : '' ?>>⚔️ Linhas Agressivas (-AH)</option>
                    <option value="AH_MINUS_025" <?= ($marketFilter === 'AH_MINUS_025') ? 'selected' : '' ?>>🎯 Linha -0.25 AH (Favorito Fase)</option>
                    <option value="AH_DNB" <?= ($marketFilter === 'AH_DNB') ? 'selected' : '' ?>>⚖️ Linha 0.0 AH (DNB / Empate Anula)</option>
                    <option value="AH_PLUS" <?= ($marketFilter === 'AH_PLUS' || $marketFilter === '+AH') ? 'selected' : '' ?>>📈 Linha +AH (Vantagem Azarão)</option>
                    <option value="UNDER" <?= ($marketFilter === 'UNDER') ? 'selected' : '' ?>>🟨 Under Cartões (Menos de)</option>
                    <option value="OVER" <?= ($marketFilter === 'OVER') ? 'selected' : '' ?>>🟨 Over Cartões (Mais de)</option>
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

            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                <button type="submit" class="btn btn-success fw-bold px-4">
                    <i class="bi bi-filter me-1"></i> Aplicar Filtros
                </button>
                <a href="<?= base_url('apostas/relatorio-eficiencia') ?>" class="btn btn-outline-secondary px-3" title="Limpar Filtros">
                    <i class="bi bi-x-circle me-1"></i> Limpar
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
                                    <?php
                                        $det = (string)($p->detalhe_resultado ?? '');
                                        $isBlockedXg = (stripos($det, 'bloqueada') !== false || stripos($det, 'xg = 0') !== false || stripos($det, 'xg indispon') !== false);
                                    ?>
                                    <?php if ($isBlockedXg || strtoupper($p->resultado_status) === 'NO_BET'): ?>
                                        <?php if ($isBlockedXg): ?>
                                            <span class="badge bg-warning text-dark border border-warning px-2 py-1 mb-1 d-inline-block shadow-sm">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ AH Alerta: xG 0.00
                                            </span>
                                            <div class="fw-bold text-warning"><?= esc($p->linha_sugerida) ?></div>
                                            <div class="small text-white-50" title="<?= esc($det) ?>">
                                                ⚠️ Estatística xG indisponível na API
                                            </div>
                                        <?php else: ?>
                                            <span class="text-white-50 fst-italic">
                                                <i class="bi bi-dash-circle me-1"></i> Sem Entrada (Abstenção)
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (stripos($p->linha_sugerida, '0.0') !== false || stripos($p->linha_sugerida, 'empate anula') !== false): ?>
                                            <span class="badge bg-info text-dark border border-info px-2 py-1 mb-1 d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-shield-check"></i> 🛡️ Proteção de Empate
                                            </span>
                                        <?php endif; ?>
                                        <div class="fw-bold text-warning d-flex align-items-center flex-wrap gap-1">
                                            <span><?= esc($p->linha_sugerida) ?></span>
                                            <?php if (!empty($p->prob_projetada) && $p->prob_projetada > 0): ?>
                                                <span class="badge bg-dark text-info border border-info px-1 py-0 small" style="font-size: 0.7rem;" title="Cobertura efetiva estimada por Poisson no pré-jogo">
                                                    <i class="bi bi-bullseye"></i> <?= $p->prob_projetada ?>% Cobertura
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
    function exportDatagridToCsv() {
        const table = document.getElementById('datagridEficiencia');
        if (!table) {
            alert('Nenhum registro para exportar.');
            return;
        }

        const rows = [];
        const trs = table.querySelectorAll('tr');
        if (trs.length <= 1) {
            alert('Nenhum registro para exportar.');
            return;
        }

        trs.forEach(tr => {
            const row = [];
            const cols = tr.querySelectorAll('th, td');
            cols.forEach(col => {
                let text = col.innerText.replace(/\r?\n|\r/g, ' ').replace(/\s+/g, ' ').trim();
                row.push(`"${text.replace(/"/g, '""')}"`);
            });
            if (row.length > 0) {
                rows.push(row.join(';'));
            }
        });

        const csvContent = '\uFEFF' + rows.join('\r\n');
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

    function applyEficienciaPreset(presetKey) {
        const startEl = document.getElementById('eficiencia_start_date');
        const endEl = document.getElementById('eficiencia_end_date');
        if (!startEl || !endEl) return;

        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const todayStr = `${year}-${month}-${day}`;

        function getPastDate(m) {
            const d = new Date();
            const tm = d.getMonth() - m;
            d.setMonth(tm);
            if (d.getMonth() !== ((tm % 12 + 12) % 12)) d.setDate(0);
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        }

        if (presetKey === 'today') {
            startEl.value = todayStr; endEl.value = todayStr;
        } else if (presetKey === 'yesterday') {
            const y = new Date(); y.setDate(y.getDate() - 1);
            const yStr = `${y.getFullYear()}-${String(y.getMonth()+1).padStart(2,'0')}-${String(y.getDate()).padStart(2,'0')}`;
            startEl.value = yStr; endEl.value = yStr;
        } else if (presetKey === '7days') {
            const s = new Date(); s.setDate(s.getDate() - 6);
            startEl.value = `${s.getFullYear()}-${String(s.getMonth()+1).padStart(2,'0')}-${String(s.getDate()).padStart(2,'0')}`;
            endEl.value = todayStr;
        } else if (presetKey === '15days') {
            const s = new Date(); s.setDate(s.getDate() - 14);
            startEl.value = `${s.getFullYear()}-${String(s.getMonth()+1).padStart(2,'0')}-${String(s.getDate()).padStart(2,'0')}`;
            endEl.value = todayStr;
        } else if (presetKey === '1month') {
            startEl.value = getPastDate(1); endEl.value = todayStr;
        } else if (presetKey === 'trimestre') {
            startEl.value = getPastDate(3); endEl.value = todayStr;
        } else if (presetKey === 'semestre') {
            startEl.value = getPastDate(6); endEl.value = todayStr;
        } else if (presetKey === 'all') {
            startEl.value = ''; endEl.value = '';
        }
    }
</script>
