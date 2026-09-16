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

    /* Slide Buttons (Toggle Switches) */
    .bet-slide-toggle {
        display: inline-flex;
        align-items: center;
        background: #0d1117;
        border: 1px solid #30363d;
        border-radius: 20px;
        padding: 2px 3px;
        gap: 2px;
    }
    .bet-slide-toggle .slide-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 4px 12px;
        border-radius: 14px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .bet-slide-toggle .slide-btn.active {
        background: #00e676;
        color: #0d1117;
        font-weight: 700;
        box-shadow: 0 0 10px rgba(0, 230, 118, 0.4);
    }
    .bet-slide-toggle .slide-btn.active-no {
        background: #ff5252;
        color: #ffffff;
        font-weight: 700;
        box-shadow: 0 0 10px rgba(255, 82, 82, 0.4);
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

    <!-- Filtros de Pesquisa (Reposicionado no Topo) -->
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

            <!-- Slide Toggle de Apostas Confirmadas + Botões de Ação -->
            <div class="col-12 col-md-4 pt-1">
                <label class="form-label text-white-50 small font-weight-bold d-flex align-items-center gap-1 mb-1">
                    <i class="bi bi-shield-check text-success"></i> Apostas Confirmadas:
                </label>
                <div class="bet-slide-toggle" id="confirmedSlideToggle">
                    <input type="hidden" name="confirmed" id="confirmedFilterInput" value="<?= esc($confirmedFilter ?? '1') ?>">
                    <button type="button" class="slide-btn <?= ($confirmedFilter === 'all') ? 'active' : '' ?>" data-val="all" onclick="setEficienciaConfirmedFilter('all', this)" title="Exibir todas as apostas (confirmadas e não confirmadas)">Todas</button>
                    <button type="button" class="slide-btn <?= ($confirmedFilter === '1' || empty($confirmedFilter)) ? 'active' : '' ?>" data-val="1" onclick="setEficienciaConfirmedFilter('1', this)" title="Exibir apenas apostas confirmadas (com débito em conta)">Sim</button>
                    <button type="button" class="slide-btn <?= ($confirmedFilter === '0') ? 'active-no' : '' ?>" data-val="0" onclick="setEficienciaConfirmedFilter('0', this)" title="Exibir apenas apostas não confirmadas">Não</button>
                </div>
            </div>

            <div class="col-12 col-md-8 d-flex justify-content-end align-items-end gap-2 pt-1">
                <button type="submit" class="btn btn-success fw-bold px-4">
                    <i class="bi bi-filter me-1"></i> Aplicar Filtros
                </button>
                <a href="<?= base_url('apostas/relatorio-eficiencia') ?>" class="btn btn-outline-secondary px-3" title="Limpar Filtros">
                    <i class="bi bi-x-circle me-1"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- Bloco de Reconciliação Contábil & Gestão de Banca Real -->
    <div class="card bg-dark border-secondary shadow-lg mb-4" style="border-radius: 14px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-wallet2 fs-4 text-warning"></i>
                    <h5 class="mb-0 fw-bold text-white">Reconciliação Contábil da Banca (Extrato Oficial da Carteira)</h5>
                </div>
                <span class="badge bg-secondary text-white border border-secondary px-3 py-1">
                    <i class="bi bi-shield-check text-success me-1"></i> Dados 100% Sincronizados com Conta Corrente
                </span>
            </div>
            <div class="row g-3 text-center text-md-start">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="p-3 rounded bg-black bg-opacity-30 border border-secondary border-opacity-50">
                        <div class="small text-white-50 text-uppercase fw-semibold">Saldo Atual Disponível</div>
                        <div class="fs-3 fw-bold text-info mt-1">
                            $ <?= number_format($contaCorrenteStats['saldo_atual'] ?? 0, 2, ',', '.') ?>
                        </div>
                        <div class="small text-white-50 mt-1"><i class="bi bi-bank me-1"></i> Saldo livre na Betano/Banca</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="p-3 rounded bg-black bg-opacity-30 border border-secondary border-opacity-50">
                        <div class="small text-white-50 text-uppercase fw-semibold">Lucro Real das Apostas (Histórico)</div>
                        <div class="fs-3 fw-bold text-success mt-1">
                            +$ <?= number_format($contaCorrenteStats['lucro_apostas'] ?? 0, 2, ',', '.') ?>
                        </div>
                        <div class="small text-success mt-1"><i class="bi bi-arrow-up-circle-fill me-1"></i> Lucro líquido acumulado da IA</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="p-3 rounded bg-black bg-opacity-30 border border-secondary border-opacity-50">
                        <div class="small text-white-50 text-uppercase fw-semibold">Retiradas / Resgates / Ajustes</div>
                        <div class="fs-3 fw-bold text-danger mt-1">
                            -$ <?= number_format($contaCorrenteStats['total_resgates'] ?? 0, 2, ',', '.') ?>
                        </div>
                        <div class="small text-danger mt-1"><i class="bi bi-arrow-down-circle-fill me-1"></i> Saques e equiparações manuais</div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="p-3 rounded bg-black bg-opacity-30 border border-secondary border-opacity-50">
                        <div class="small text-white-50 text-uppercase fw-semibold">Total Depositado Adicionado</div>
                        <div class="fs-3 fw-bold text-warning mt-1">
                            +$ <?= number_format($contaCorrenteStats['total_depositos'] ?? 0, 2, ',', '.') ?>
                        </div>
                        <div class="small text-white-50 mt-1"><i class="bi bi-plus-circle me-1"></i> Aportes e créditos adicionados</div>
                    </div>
                </div>
            </div>
            <div class="mt-3 pt-2 border-top border-secondary border-opacity-25 d-flex align-items-center justify-content-between flex-wrap gap-2 small text-white-50">
                <span>
                    <i class="bi bi-info-circle me-1 text-info"></i>
                    <strong>Fechamento Contábil:</strong>
                    Depósitos ($ <?= number_format($contaCorrenteStats['total_depositos'] ?? 0, 2, ',', '.') ?>)
                    + Lucro Real Apostas (+$ <?= number_format($contaCorrenteStats['lucro_apostas'] ?? 0, 2, ',', '.') ?>)
                    - Resgates/Retiradas (-$ <?= number_format($contaCorrenteStats['total_resgates'] ?? 0, 2, ',', '.') ?>)
                    = <strong>Saldo em Conta ($ <?= number_format($contaCorrenteStats['saldo_atual'] ?? 0, 2, ',', '.') ?>)</strong>
                </span>
                <span class="text-white-50">
                    Volume no período filtrado: <strong>R$ <?= number_format($totalApostadoReal ?? 0, 2, ',', '.') ?></strong> apostados
                    (Resultado: <strong class="<?= ($lucroLiquidoReal ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>"><?= (($lucroLiquidoReal ?? 0) >= 0 ? '+' : '') . 'R$ ' . number_format($lucroLiquidoReal ?? 0, 2, ',', '.') ?></strong>)
                </span>
            </div>
        </div>
    </div>

    <!-- Cards Superiores de KPI (Solid Dark Backgrounds) -->
    <div class="row g-3 mb-4">
        <!-- Total Apostas Analisadas -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass">
                <div class="stat-card-title">Apostas Analisadas</div>
                <div class="stat-value mt-1" style="color: #38bdf8;"><?= number_format($totalAnalisados, 0, ',', '.') ?></div>
                <div class="stat-card-sub mt-2" style="color: #fbbf24;">
                    <i class="bi bi-cash-coin me-1"></i> R$ <?= number_format($totalApostadoReal ?? 0, 2, ',', '.') ?> em apostas
                    <?php if (!empty($openApostado) && $openApostado > 0): ?>
                        <div class="small text-warning mt-1" style="font-size: 0.72rem;">
                            <i class="bi bi-hourglass-split me-1"></i> + R$ <?= number_format($openApostado, 2, ',', '.') ?> em andamento hoje
                        </div>
                    <?php endif; ?>
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

        <!-- Taxa de Abstenção (Gatekeeper) -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass">
                <div class="stat-card-title">Abstenção (Gatekeeper)</div>
                <div class="stat-value mt-1" style="color: #cbd5e1;"><?= $abstentionRate ?>%</div>
                <div class="stat-card-sub mt-2" style="color: #94a3b8;">
                    <i class="bi bi-slash-circle me-1"></i> <?= $noBetCount ?> Sem Entrada (Protegidas)
                </div>
            </div>
        </div>

        <!-- Lucro Líquido & ROI Real -->
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="stat-card-glass" style="border-color: #1d4ed8 !important;">
                <div class="stat-card-title">Lucro Líquido Real</div>
                <div class="stat-value mt-1" style="color: <?= ($lucroLiquidoReal ?? 0) >= 0 ? '#34d399' : '#f87171' ?>;">
                    <?= (($lucroLiquidoReal ?? 0) >= 0 ? '+' : '') . 'R$ ' . number_format($lucroLiquidoReal ?? 0, 2, ',', '.') ?>
                </div>
                <div class="stat-card-sub mt-2" style="color: <?= ($roiPercent ?? 0) >= 0 ? '#34d399' : '#f87171' ?>;">
                    ROI Real: <strong><?= (($roiPercent ?? 0) >= 0 ? '+' : '') . $roiPercent ?>%</strong> (<?= ($lucroPrejuizoUnidades >= 0 ? '+' : '') . number_format($lucroPrejuizoUnidades, 2, ',', '.') ?> u)
                    <?php if (!empty($openApostado) && $openApostado > 0): ?>
                        <div class="small text-white-50 mt-1" style="font-size: 0.72rem;">
                            <i class="bi bi-hourglass-split me-1 text-warning"></i> Hoje: R$ <?= number_format($openPartialLucro ?? 0, 2, ',', '.') ?> parcial
                        </div>
                    <?php endif; ?>
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
                        <th>Partida (Casa x Fora)</th>
                        <th>Liga</th>
                        <th>Mercado / Palpite</th>
                        <th>Resultado Real (FT)</th>
                        <th>Valor Apostado</th>
                        <th>Odd Real</th>
                        <th>Retorno / Lucro</th>
                        <th class="pe-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($palpites)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-white-50">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Nenhuma aposta encontrada para os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($palpites as $p): ?>
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    <div class="fw-bold text-white"><?= date('d/m/Y', strtotime($p->fixture_date)) ?></div>
                                    <div class="small text-white-50"><?= date('H:i', strtotime($p->fixture_date)) ?> hs</div>
                                </td>

                                <!-- Partida (Casa x Fora) -->
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="fw-bold d-flex align-items-center gap-1">
                                            <i class="bi bi-house-door-fill text-primary small"></i>
                                            <span style="color: #60a5fa !important; font-weight: 700;"><?= esc($p->home_team) ?></span>
                                        </div>
                                        <div class="fw-bold d-flex align-items-center gap-1">
                                            <i class="bi bi-airplane-fill text-info small"></i>
                                            <span style="color: #38bdf8 !important; font-weight: 700;"><?= esc($p->away_team) ?></span>
                                        </div>
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
                                                <i class="bi bi-slash-circle me-1"></i> Sem Entrada (Proteção Gatekeeper)
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
                                    </div>
                                    <div class="small text-white-50">
                                        🟨 Cartões: <?= ((int)($p->yellow_cards_home ?? 0) + (int)($p->yellow_cards_away ?? 0) + (int)($p->red_cards_home ?? 0) + (int)($p->red_cards_away ?? 0)) ?>
                                        | 🚩 Escanteios: <?= ((int)($p->corners_home ?? 0) + (int)($p->corners_away ?? 0)) ?>
                                    </div>
                                </td>

                                <!-- Valor Apostado -->
                                <td>
                                    <?php if (strtoupper($p->resultado_status) === 'NO_BET'): ?>
                                        <span class="text-white-50">—</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-white">R$ <?= number_format($p->valor_aposta ?? 10.0, 2, ',', '.') ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Odd Real -->
                                <td>
                                    <?php if (!empty($p->odd_momento) && (float)$p->odd_momento > 1.0 && strtoupper($p->resultado_status) !== 'NO_BET'): ?>
                                        <span class="badge badge-odd-custom">
                                            @<?= number_format($p->odd_momento, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-white-50">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Retorno / Lucro Real -->
                                <td>
                                    <?php
                                        $st = strtoupper($p->resultado_status);
                                        $lucro = (float)($p->lucro_real ?? 0.0);
                                        if ($st === 'GREEN') {
                                            echo '<span class="fw-bold text-success">+R$ ' . number_format($lucro, 2, ',', '.') . '</span>';
                                        } elseif ($st === 'RED') {
                                            echo '<span class="fw-bold text-danger">-R$ ' . number_format(abs($lucro), 2, ',', '.') . '</span>';
                                        } elseif ($st === 'VOID') {
                                            echo '<span class="fw-bold text-info">R$ 0,00 (Reembolso)</span>';
                                        } elseif ($st === 'NO_BET') {
                                            echo '<span class="text-white-50">R$ 0,00</span>';
                                        } else {
                                            echo '<span class="text-warning">Em aberto</span>';
                                        }
                                    ?>
                                </td>

                                <td class="pe-3 text-center">
                                    <?php
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

    function setEficienciaConfirmedFilter(val, btnEl) {
        const inputEl = document.getElementById('confirmedFilterInput');
        if (inputEl) inputEl.value = val;
        const container = document.getElementById('confirmedSlideToggle');
        if (container) {
            container.querySelectorAll('.slide-btn').forEach(btn => btn.classList.remove('active', 'active-no'));
        }
        if (btnEl) {
            if (val === '0') {
                btnEl.classList.add('active-no');
            } else {
                btnEl.classList.add('active');
            }
        }
    }
</script>
