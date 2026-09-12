<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
  :root {
    --bet-bg: #0d1117;
    --bet-card-bg: #161b22;
    --bet-card-border: #21262d;
    --bet-primary: #00e676;
    --bet-primary-glow: rgba(0, 230, 118, 0.25);
    --bet-accent: #00b0ff;
    --bet-gold: #ffd600;
    --bet-danger: #ff5252;
    --bet-text-main: #f0f6fc;
    --bet-text-muted: #94a3b8;
  }

  body {
    background-color: var(--bet-bg) !important;
    font-family: 'Inter', sans-serif;
    color: var(--bet-text-main);
  }

  .bet-container {
    max-width: 1350px;
    margin: 30px auto;
    padding: 0 20px 60px 20px;
  }

  /* Header banner */
  .bet-header {
    background: linear-gradient(135deg, #161b22 0%, #1f2937 100%);
    border: 1px solid var(--bet-card-border);
    border-radius: 16px;
    padding: 24px 32px;
    margin-bottom: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
  }

  .bet-title h1 {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 2rem;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .bet-title h1 i {
    color: var(--bet-accent);
    text-shadow: 0 0 15px rgba(0, 176, 255, 0.4);
  }

  .bet-subtitle {
    color: var(--bet-text-muted);
    margin-top: 6px;
    font-size: 0.95rem;
  }

  /* KPI Summary Cards */
  .kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
    margin-bottom: 25px;
  }

  .kpi-card {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: transform 0.2s ease, border-color 0.2s ease;
  }

  .kpi-card:hover {
    transform: translateY(-2px);
    border-color: rgba(56, 189, 248, 0.4);
  }

  .kpi-label {
    font-size: 0.82rem;
    color: var(--bet-text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .kpi-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--bet-text-main);
  }

  /* Filter toolbar */
  .filter-bar {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
  }

  /* Chart Card */
  .chart-card {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
  }

  .chart-container-box {
    position: relative;
    width: 100%;
    height: 420px;
  }

  /* Breakdown Table */
  .table-card {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
    color: var(--bet-text-muted);
    padding: 4px 12px;
    border-radius: 14px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .bet-slide-toggle .slide-btn.active {
    background: var(--bet-primary);
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

<div class="bet-container">
  <!-- Header -->
  <div class="bet-header">
    <div class="bet-title">
      <h1><i class="bi bi-graph-up-arrow"></i> <?= lang('App.perf_analysis_title') ?></h1>
      <div class="bet-subtitle"><?= lang('App.perf_analysis_subtitle') ?></div>
    </div>
    <div>
      <a href="<?= base_url('apostas') ?>" class="btn btn-outline-light rounded-pill px-4 fw-semibold text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i> <?= lang('App.back_to_my_bets') ?>
      </a>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <!-- Period Presets + Date Inputs -->
    <div class="d-flex align-items-center gap-2 flex-wrap" style="font-size: 0.88rem;">
      <span class="text-light fw-semibold d-flex align-items-center gap-1"><i class="bi bi-calendar-range text-info"></i> <?= lang('App.period') ?? 'Período' ?>:</span>
      
      <select id="perfDatePresetSelect" class="form-select form-select-sm bg-dark text-info border-secondary fw-semibold" style="width: auto; cursor: pointer; min-width: 160px;" onchange="setPerfDatePreset(this.value)" title="Atalhos de Período">
        <option value="custom">📅 Personalizado</option>
        <option value="today">⚡ Hoje</option>
        <option value="yesterday">⏪ Ontem</option>
        <option value="7days">🗓️ Últimos 7 dias</option>
        <option value="15days">🗓️ Últimos 15 dias</option>
        <option value="1month">📅 Último mês</option>
        <option value="trimestre">📊 Trimestre</option>
        <option value="semestre">📈 Semestre</option>
        <option value="all" selected>♾️ Todo o período</option>
      </select>

      <input type="date" id="perfStartDateInput" class="form-control form-control-sm bg-dark text-white border-secondary" style="width: 138px;" onchange="onPerfManualDateChange()" title="Data Inicial (De)">
      <span class="text-light-50 small">até</span>
      <input type="date" id="perfEndDateInput" class="form-control form-control-sm bg-dark text-white border-secondary" style="width: 138px;" onchange="onPerfManualDateChange()" title="Data Final (Até)">
      
      <button class="btn btn-sm btn-outline-secondary border-0 text-light-50 p-1" onclick="clearPerfDateFilter()" title="Limpar Filtro de Período"><i class="bi bi-x-circle-fill"></i></button>
    </div>

    <!-- Status Filter + Dynamic Grouping (Eixo X) -->
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="d-flex align-items-center gap-2">
        <span class="text-light fw-semibold d-flex align-items-center gap-1" style="font-size: 0.88rem;"><i class="bi bi-funnel-fill text-primary"></i> <?= lang('App.status') ?>:</span>
        <select id="perfStatusSelect" class="form-select form-select-sm bg-dark text-primary border-secondary fw-semibold" style="width: auto; cursor: pointer; min-width: 175px;" onchange="updatePerformanceDashboard()" title="<?= lang('App.total_bets') ?>">
          <option value="all">♾️ <?= lang('App.status_all_pending') ?></option>
          <option value="concluidas" selected>✅ <?= lang('App.status_concluded') ?></option>
          <option value="Pendente">⏳ <?= lang('App.status_only_pending') ?></option>
          <option value="Ganha">🟢 <?= lang('App.won') ?> / Meio Ganhas</option>
          <option value="Perdida">🔴 <?= lang('App.lost') ?> / Meio Perdidas</option>
          <option value="Cashout">💰 Cashout</option>
          <option value="ANULADA">⚪ <?= lang('App.refunded') ?></option>
        </select>
      </div>

      <!-- Slide Button: Apostas Confirmadas -->
      <div class="d-flex align-items-center gap-2">
        <span class="text-light fw-semibold d-flex align-items-center gap-1" style="font-size: 0.88rem;">
          <i class="bi bi-shield-check text-success"></i> Confirmadas:
        </span>
        <div class="bet-slide-toggle" id="perfConfirmedSlideToggle">
          <button type="button" class="slide-btn" data-val="all" onclick="setPerfConfirmedFilter('all', this)" title="Exibir todas as apostas (confirmadas e não confirmadas)">Todas</button>
          <button type="button" class="slide-btn active" data-val="1" onclick="setPerfConfirmedFilter('1', this)" title="Exibir apenas apostas confirmadas (com débito em conta)">Sim</button>
          <button type="button" class="slide-btn" data-val="0" onclick="setPerfConfirmedFilter('0', this)" title="Exibir apenas apostas não confirmadas">Não</button>
        </div>
      </div>

      <div class="d-flex align-items-center gap-2">
        <span class="text-light fw-semibold d-flex align-items-center gap-1" style="font-size: 0.88rem;"><i class="bi bi-bar-chart-steps text-warning"></i> Agrupar Eixo X:</span>
        <select id="perfGroupSelect" class="form-select form-select-sm bg-dark text-warning border-secondary fw-semibold" style="width: auto; cursor: pointer; min-width: 130px;" onchange="updatePerformanceDashboard()">
          <option value="dia" selected>📅 Por Dia</option>
          <option value="semana">🗓️ Por Semana</option>
          <option value="mes">📆 Por Mês</option>
        </select>
      </div>
    </div>
  </div>

  <!-- KPI Summary Cards -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-cash-coin text-info"></i> <?= lang('App.total_staked') ?></div>
      <div class="kpi-value text-info" id="kpiTotalApostado">R$ 0,00</div>
      <div class="small fw-semibold mt-1" id="kpiPendenteInfo" style="display: none; color: #f59e0b; font-size: 0.76rem;"></div>
    </div>
    
    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-arrow-return-right text-accent"></i> <?= lang('App.return') ?></div>
      <div class="kpi-value text-white" id="kpiTotalRetorno">R$ 0,00</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-piggy-bank-fill" style="color: #00e676;"></i> <?= lang('App.total_profit') ?></div>
      <div class="kpi-value text-success" id="kpiLucroLiquido">R$ 0,00</div>
      <div class="small fw-semibold mt-1" id="kpiLucroHojeInfo" style="display: none; color: #f59e0b; font-size: 0.76rem;"></div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-graph-up text-warning"></i> <?= lang('App.roi') ?></div>
      <div class="kpi-value text-warning" id="kpiRoi">+0,0%</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-check-circle-fill text-success"></i> <?= lang('App.win_rate') ?></div>
      <div class="kpi-value text-white" id="kpiWinRate">0,0%</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-ticket-detailed text-secondary"></i> <?= lang('App.total_bets') ?></div>
      <div class="kpi-value text-white" id="kpiTotalApostas">0</div>
    </div>
  </div>

  <!-- Line Chart Section -->
  <div class="chart-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
        <i class="bi bi-activity text-success"></i> <?= lang('App.bankroll_evolution_curve') ?>
      </h5>
      <span class="badge bg-dark border border-secondary text-light-50 px-3 py-1.5" style="font-size: 0.8rem;">
        <i class="bi bi-info-circle me-1 text-info"></i> Exibição Acumulada no Tempo
      </span>
    </div>

    <div class="chart-container-box">
      <canvas id="performanceChart"></canvas>
    </div>
  </div>

  <!-- Modalities Comparison Chart Section (Cartões vs Handicap Asiático) -->
  <div class="chart-card" id="modalitiesComparisonSection">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div>
        <h5 class="fw-bold mb-1 text-white d-flex align-items-center gap-2">
          <i class="bi bi-intersect text-warning"></i> <?= lang('App.modalities_profit_comparison') ?? 'Comparativo de Lucratividade: Cartões vs Handicap Asiático' ?>
        </h5>
        <div class="small" style="color: #cbd5e1 !important;">
          <i class="bi bi-info-circle text-info"></i> <?= lang('App.modalities_profit_subtitle') ?? 'Acompanhe a curva de evolução e lucratividade separada por modalidade no período' ?>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-dark border border-success text-success px-3 py-1.5" style="font-size: 0.8rem;" id="badgeCardsSummary">
          <i class="bi bi-square-fill me-1" style="color: #00e676;"></i> <?= lang('App.modality_cards') ?? 'Cartões (Under)' ?>
        </span>
        <span class="badge bg-dark border border-warning text-warning px-3 py-1.5" style="font-size: 0.8rem;" id="badgeAhSummary">
          <i class="bi bi-square-fill me-1" style="color: #ff9100;"></i> <?= lang('App.modality_asian_handicap') ?? 'Handicap Asiático (AH)' ?>
        </span>
      </div>
    </div>

    <!-- Mini KPI Diagnosis Cards for Modalities -->
    <div class="row g-3 mb-3">
      <!-- Card Cartões -->
      <div class="col-md-4 col-sm-6">
        <div class="p-3 rounded-3 border h-100" style="background: rgba(0, 230, 118, 0.06); border-color: rgba(0, 230, 118, 0.3) !important;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-success d-flex align-items-center gap-1">
              <i class="bi bi-shield-check"></i> <?= lang('App.modality_cards') ?? 'Cartões (Under)' ?>
            </span>
            <span class="badge bg-success text-dark fw-bold" id="kpiCardsBadge" style="font-size: 0.7rem;">Estável 🟢</span>
          </div>
          <div class="d-flex align-items-baseline gap-2">
            <div class="fw-bold fs-5 text-white" id="kpiCardsLucro">R$ 0,00</div>
            <span class="small fw-semibold" id="kpiCardsRoi">ROI: 0%</span>
          </div>
          <div class="small text-white-50 mt-1" style="color: #cbd5e1 !important;" id="kpiCardsDetails">
            0 bets | Win Rate: 0,0%
          </div>
        </div>
      </div>

      <!-- Card Handicap Asiático -->
      <div class="col-md-4 col-sm-6">
        <div class="p-3 rounded-3 border h-100" id="kpiAhCardBox" style="background: rgba(255, 145, 0, 0.06); border-color: rgba(255, 145, 0, 0.3) !important;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-warning d-flex align-items-center gap-1">
              <i class="bi bi-sliders"></i> <?= lang('App.modality_asian_handicap') ?? 'Handicap Asiático (AH)' ?>
            </span>
            <span class="badge bg-warning text-dark fw-bold" id="kpiAhBadge" style="font-size: 0.7rem;">Volátil ⚡</span>
          </div>
          <div class="d-flex align-items-baseline gap-2">
            <div class="fw-bold fs-5 text-white" id="kpiAhLucro">R$ 0,00</div>
            <span class="small fw-semibold" id="kpiAhRoi">ROI: 0%</span>
          </div>
          <div class="small text-white-50 mt-1" style="color: #cbd5e1 !important;" id="kpiAhDetails">
            0 bets | Win Rate: 0,0%
          </div>
        </div>
      </div>

      <!-- Card Comparativo Diferencial -->
      <div class="col-md-4 col-sm-12">
        <div class="p-3 rounded-3 border h-100" style="background: rgba(56, 189, 248, 0.06); border-color: rgba(56, 189, 248, 0.3) !important;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-info d-flex align-items-center gap-1">
              <i class="bi bi-arrow-left-right"></i> Balanço Diferencial (Impacto)
            </span>
            <span class="badge bg-dark border border-info text-info" id="kpiDeltaBadge" style="font-size: 0.7rem;">Diagnóstico</span>
          </div>
          <div class="d-flex align-items-baseline gap-2">
            <div class="fw-bold fs-5 text-white" id="kpiDeltaLucro">R$ 0,00</div>
            <span class="small text-info fw-semibold" id="kpiDeltaStatus">Vantagem Cartões</span>
          </div>
          <div class="small text-white-50 mt-1" style="color: #cbd5e1 !important;" id="kpiDeltaDetails">
            Impacto líquido comparativo no período selecionado
          </div>
        </div>
      </div>
    </div>

    <!-- Chart Container -->
    <div class="chart-container-box">
      <canvas id="modalidadesProfitChart"></canvas>
    </div>
  </div>

  <!-- Mercado Profit Chart Section -->
  <div class="chart-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-line-fill text-accent"></i> <?= lang('App.net_profit_by_market') ?>
      </h5>
      <span class="badge bg-dark border border-secondary text-light-50 px-3 py-1.5" style="font-size: 0.8rem;">
        <i class="bi bi-funnel-fill me-1 text-warning"></i> Agrupado pelos Mesmos Filtros
      </span>
    </div>

    <div class="chart-container-box" id="mercadoChartBox" style="height: 380px;">
      <canvas id="mercadoProfitChart"></canvas>
    </div>
  </div>

  <!-- League Profitability & Banca Drain Analysis Section -->
  <div class="chart-card" id="leagueProfitSection">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div>
        <h5 class="fw-bold mb-1 text-white d-flex align-items-center gap-2">
          <i class="bi bi-trophy-fill text-warning"></i> <?= lang('App.net_profit_by_league') ?? 'Lucratividade por Liga de Futebol (Ranking Geral)' ?>
        </h5>
        <div class="small" style="color: #cbd5e1 !important;">
          <i class="bi bi-shield-exclamation text-info"></i> <?= lang('App.league_profit_subtitle') ?? 'Identifique as ligas mais rentáveis e os ralos da sua banca para cortes estratégicos' ?>
        </div>
      </div>
      
      <!-- Controls Toolbar -->
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Segment Filter (All / Losses / Profits) -->
        <div class="bet-slide-toggle" id="leagueSegmentToggle">
          <button type="button" class="slide-btn active" data-seg="all" onclick="setLeagueSegmentFilter('all', this)" title="Exibir todas as ligas">Todas</button>
          <button type="button" class="slide-btn" data-seg="loss_only" onclick="setLeagueSegmentFilter('loss_only', this)" title="Exibir apenas ligas com prejuízo acumulado (candidatas a corte)">Prejuízo 🔴</button>
          <button type="button" class="slide-btn" data-seg="profit_only" onclick="setLeagueSegmentFilter('profit_only', this)" title="Exibir apenas ligas lucrativas">Lucrativas 🟢</button>
        </div>

        <!-- Sort Select -->
        <select id="leagueSortSelect" class="form-select form-select-sm bg-dark text-warning border-secondary fw-semibold" style="width: auto; cursor: pointer;" onchange="onLeagueSortChange(this.value)">
          <option value="losses_first" selected>🚨 Maiores Prejuízos Primeiro (Cortes)</option>
          <option value="profit_first">🏆 Mais Lucrativas Primeiro</option>
          <option value="roi">📈 Pior ROI % Primeiro</option>
          <option value="volume">🎫 Maior Volume de Apostas</option>
        </select>
      </div>
    </div>

    <!-- Mini KPI Diagnosis Cards for Leagues -->
    <div class="row g-2 mb-3" id="leagueDiagnosticRow">
      <div class="col-md-4 col-sm-6">
        <div class="p-2 px-3 rounded-3 border h-100" style="background: rgba(255, 82, 82, 0.08); border-color: rgba(255, 82, 82, 0.3) !important;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-danger d-flex align-items-center gap-1">
              <i class="bi bi-exclamation-octagon-fill"></i> <?= lang('App.league_drain_alert') ?? 'Maior Ralo da Banca' ?>
            </span>
            <span class="badge bg-danger text-white" id="badgeDrainAction" style="font-size: 0.65rem;">Corte Recomendado</span>
          </div>
          <div class="fw-bold text-white fs-6 text-truncate" id="kpiWorstLeagueName">-</div>
          <div class="d-flex align-items-center gap-2 small">
            <span class="text-danger fw-bold fs-6" id="kpiWorstLeagueProfit">R$ 0,00</span>
            <span class="text-white-50" style="color: #cbd5e1 !important;" id="kpiWorstLeagueDetails">ROI: 0% | 0 apostas</span>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-sm-6">
        <div class="p-2 px-3 rounded-3 border h-100" style="background: rgba(0, 230, 118, 0.08); border-color: rgba(0, 230, 118, 0.3) !important;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-success d-flex align-items-center gap-1">
              <i class="bi bi-star-fill"></i> <?= lang('App.league_best_alert') ?? 'Liga Mais Lucrativa' ?>
            </span>
            <span class="badge bg-success text-dark fw-bold" style="font-size: 0.65rem;">Top Performance</span>
          </div>
          <div class="fw-bold text-white fs-6 text-truncate" id="kpiBestLeagueName">-</div>
          <div class="d-flex align-items-center gap-2 small">
            <span class="text-success fw-bold fs-6" id="kpiBestLeagueProfit">+R$ 0,00</span>
            <span class="text-white-50" style="color: #cbd5e1 !important;" id="kpiBestLeagueDetails">ROI: +0% | 0 apostas</span>
          </div>
        </div>
      </div>

      <div class="col-md-4 col-sm-12">
        <div class="p-2 px-3 rounded-3 border h-100" style="background: rgba(56, 189, 248, 0.08); border-color: rgba(56, 189, 248, 0.3) !important;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-semibold text-info d-flex align-items-center gap-1">
              <i class="bi bi-pie-chart-fill"></i> Balanço de Ligas
            </span>
            <span class="badge bg-dark border border-secondary text-info" id="kpiLeagueCountBadge" style="font-size: 0.65rem;">0 Ligas</span>
          </div>
          <div class="fw-bold text-white fs-6" id="kpiLeagueBalanceSummary">0 Positivas / 0 Negativas</div>
          <div class="text-white-50 small" style="color: #cbd5e1 !important;" id="kpiLeagueLossTotal">Prejuízo Total Drenado: R$ 0,00</div>
        </div>
      </div>
    </div>

    <!-- Chart Container -->
    <div class="chart-container-box" id="leagueChartBox" style="height: 400px;">
      <canvas id="leagueProfitChart"></canvas>
    </div>

    <!-- Collapsible Table: Detalhamento de Ligas e Recomendações de Corte -->
    <div class="mt-3 text-end">
      <button class="btn btn-sm btn-outline-secondary text-light-50 rounded-pill px-3 py-1" type="button" data-bs-toggle="collapse" data-bs-target="#leagueTableCollapse" aria-expanded="false" aria-controls="leagueTableCollapse">
        <i class="bi bi-list-columns-reverse me-1 text-warning"></i> Alternar Tabela de Auditoria por Liga
      </button>
    </div>

    <div class="collapse mt-3" id="leagueTableCollapse">
      <div class="table-responsive rounded-3 border border-secondary p-2 bg-dark">
        <table class="table table-dark table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
          <thead>
            <tr class="text-white-50 border-secondary">
              <th>Liga</th>
              <th class="text-center">Apostas</th>
              <th class="text-center">V / D / A</th>
              <th class="text-center">Win Rate</th>
              <th>Apostado</th>
              <th>Retorno</th>
              <th>Lucro Líquido</th>
              <th>ROI (%)</th>
              <th class="text-center">Diagnóstico</th>
            </tr>
          </thead>
          <tbody id="leagueTableBody">
            <tr>
              <td colspan="9" class="text-center text-muted py-3">Carregando dados das ligas...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Detailed Table Breakdown -->
  <div class="table-card">
    <h5 class="fw-bold mb-3 text-white d-flex align-items-center gap-2">
      <i class="bi bi-table text-info"></i> Detalhamento por Período
    </h5>
    
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.9rem;">
        <thead>
          <tr class="text-white-50 border-secondary">
            <th>Período</th>
            <th class="text-center"><?= lang('App.qty_bet_simulations') ?></th>
            <th>Simulado Bruto (R$)</th>
            <th>Retorno Bruto (R$)</th>
            <th>Lucro Líquido (R$)</th>
            <th>ROI (%)</th>
          </tr>
        </thead>
        <tbody id="tableBreakdownBody">
          <tr>
            <td colspan="6" class="text-center text-muted py-4">Carregando dados de desempenho...</td>
          </tr>
        </tbody>
        <tfoot id="tableBreakdownFoot"></tfoot>
      </table>
    </div>
  </div>
</div>

<script>
const rawBets = <?= json_encode($apostas ?? []) ?>;

let perfChart = null;
let modalidadesChart = null;
let mercadoChart = null;
let leagueChart = null;

let leagueSortMode = 'losses_first';
let leagueSegmentFilter = 'all';

function setLeagueSegmentFilter(val, btnEl) {
  leagueSegmentFilter = val;
  const container = document.getElementById('leagueSegmentToggle');
  if (container) {
    container.querySelectorAll('.slide-btn').forEach(btn => {
      btn.classList.remove('active', 'active-no');
    });
  }
  if (btnEl) {
    if (val === 'loss_only') {
      btnEl.classList.add('active-no');
    } else {
      btnEl.classList.add('active');
    }
  }
  updatePerformanceDashboard();
}

function onLeagueSortChange(val) {
  leagueSortMode = val;
  updatePerformanceDashboard();
}

let perfConfirmedFilter = '1';

function setPerfConfirmedFilter(val, btnEl) {
  perfConfirmedFilter = val;
  const container = document.getElementById('perfConfirmedSlideToggle');
  if (container) {
    container.querySelectorAll('.slide-btn').forEach(btn => {
      btn.classList.remove('active', 'active-no');
    });
  }
  if (btnEl) {
    if (val === '0') {
      btnEl.classList.add('active-no');
    } else {
      btnEl.classList.add('active');
    }
  }
  updatePerformanceDashboard();
}

function formatDateYYYYMMDD(d) {
  try {
    const formatter = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Sao_Paulo' });
    return formatter.format(d);
  } catch (e) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
}

function getPastDateByMonths(months) {
  const d = new Date();
  const targetMonth = d.getMonth() - months;
  d.setMonth(targetMonth);
  if (d.getMonth() !== ((targetMonth % 12 + 12) % 12)) {
    d.setDate(0);
  }
  return d;
}

function setPerfDatePreset(presetKey) {
  const startEl = document.getElementById('perfStartDateInput');
  const endEl = document.getElementById('perfEndDateInput');
  const selectEl = document.getElementById('perfDatePresetSelect');
  if (!startEl || !endEl) return;

  const now = new Date();
  const todayStr = formatDateYYYYMMDD(now);

  if (presetKey === 'today') {
    startEl.value = todayStr;
    endEl.value = todayStr;
  } else if (presetKey === 'yesterday') {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const yestStr = formatDateYYYYMMDD(yesterday);
    startEl.value = yestStr;
    endEl.value = yestStr;
  } else if (presetKey === '7days') {
    const start = new Date();
    start.setDate(start.getDate() - 6);
    startEl.value = formatDateYYYYMMDD(start);
    endEl.value = todayStr;
  } else if (presetKey === '15days') {
    const start = new Date();
    start.setDate(start.getDate() - 14);
    startEl.value = formatDateYYYYMMDD(start);
    endEl.value = todayStr;
  } else if (presetKey === '1month') {
    const start = getPastDateByMonths(1);
    startEl.value = formatDateYYYYMMDD(start);
    endEl.value = todayStr;
  } else if (presetKey === 'trimestre') {
    const start = getPastDateByMonths(3);
    startEl.value = formatDateYYYYMMDD(start);
    endEl.value = todayStr;
  } else if (presetKey === 'semestre') {
    const start = getPastDateByMonths(6);
    startEl.value = formatDateYYYYMMDD(start);
    endEl.value = todayStr;
  } else if (presetKey === 'all') {
    startEl.value = '';
    endEl.value = '';
  }

  if (selectEl && selectEl.value !== presetKey) {
    selectEl.value = presetKey;
  }

  updatePerformanceDashboard();
}

function clearPerfDateFilter() {
  setPerfDatePreset('all');
}

function onPerfManualDateChange() {
  const startVal = document.getElementById('perfStartDateInput')?.value || '';
  const endVal = document.getElementById('perfEndDateInput')?.value || '';
  const selectEl = document.getElementById('perfDatePresetSelect');

  const now = new Date();
  const todayStr = formatDateYYYYMMDD(now);
  const yesterdayStr = formatDateYYYYMMDD(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1));
  const d7Str = formatDateYYYYMMDD(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6));
  const d15Str = formatDateYYYYMMDD(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 14));
  const m1Str = formatDateYYYYMMDD(getPastDateByMonths(1));
  const m3Str = formatDateYYYYMMDD(getPastDateByMonths(3));
  const m6Str = formatDateYYYYMMDD(getPastDateByMonths(6));

  if (!startVal && !endVal) {
    if (selectEl) selectEl.value = 'all';
  } else if (startVal === todayStr && endVal === todayStr) {
    if (selectEl) selectEl.value = 'today';
  } else if (startVal === yesterdayStr && endVal === yesterdayStr) {
    if (selectEl) selectEl.value = 'yesterday';
  } else if (startVal === d7Str && endVal === todayStr) {
    if (selectEl) selectEl.value = '7days';
  } else if (startVal === d15Str && endVal === todayStr) {
    if (selectEl) selectEl.value = '15days';
  } else if (startVal === m1Str && endVal === todayStr) {
    if (selectEl) selectEl.value = '1month';
  } else if (startVal === m3Str && endVal === todayStr) {
    if (selectEl) selectEl.value = 'trimestre';
  } else if (startVal === m6Str && endVal === todayStr) {
    if (selectEl) selectEl.value = 'semestre';
  } else {
    if (selectEl) selectEl.value = 'custom';
  }

  updatePerformanceDashboard();
}

function getWeekKey(dateStr) {
  const d = new Date(dateStr + 'T00:00:00');
  const target = new Date(d.valueOf());
  const dayNr = (d.getDay() + 6) % 7;
  target.setDate(target.getDate() - dayNr + 3);
  const firstThursday = target.valueOf();
  target.setMonth(0, 1);
  if (target.getDay() !== 4) {
    target.setMonth(0, 1 + ((4 - target.getDay() + 7) % 7));
  }
  const weekNumber = 1 + Math.round((firstThursday - target.valueOf()) / 604800000);
  const year = target.getFullYear();
  return `${year}-W${String(weekNumber).padStart(2, '0')}`;
}

function computeNetProfit(item) {
  const status = item.status || '';
  const valor = parseFloat(item.valor_aposta) || 0;
  const odd = parseFloat(item.odd) || 0;
  const ganho = parseFloat(item.ganhos_potenciais) || 0;
  const cashout = parseFloat(item.cash_out) || 0;

  if (status === 'Ganha') {
    const ret = ganho > 0 ? ganho : (valor * odd);
    return ret - valor;
  } else if (status === 'Meio Ganha') {
    const fullRet = ganho > 0 ? ganho : (valor * odd);
    return (fullRet - valor) / 2;
  } else if (status === 'Cashout') {
    const cashVal = cashout > 0 ? cashout : (ganho > 0 ? ganho : (valor * odd));
    return cashVal - valor;
  } else if (status === 'Perdida') {
    return -valor;
  } else if (status === 'Meio Perdida') {
    return -(valor * 0.5);
  } else if (status === 'ANULADA' || status === 'Anulada' || status === 'Cancelada' || status === 'CANCELADA') {
    return 0;
  }
  return 0;
}

function computeGrossReturn(item) {
  const status = item.status || '';
  const valor = parseFloat(item.valor_aposta) || 0;
  const odd = parseFloat(item.odd) || 0;
  const ganho = parseFloat(item.ganhos_potenciais) || 0;
  const cashout = parseFloat(item.cash_out) || 0;

  if (status === 'Ganha') {
    return ganho > 0 ? ganho : (valor * odd);
  } else if (status === 'Meio Ganha') {
    const fullRet = ganho > 0 ? ganho : (valor * odd);
    return valor + ((fullRet - valor) / 2);
  } else if (status === 'Cashout') {
    return cashout > 0 ? cashout : (ganho > 0 ? ganho : (valor * odd));
  } else if (status === 'Perdida') {
    return 0;
  } else if (status === 'Meio Perdida') {
    return valor * 0.5;
  } else if (status === 'ANULADA' || status === 'Anulada' || status === 'Cancelada' || status === 'CANCELADA') {
    return valor;
  }
  return 0;
}

function getBetDateBRT(bet) {
  if (!bet) return '';
  if (bet.data_brt_dia) {
    return bet.data_brt_dia;
  }
  const rawDateStr = bet.data_hora_jogo_brt || bet.data_hora_jogo || bet.criado_em || '';
  if (!rawDateStr) return '';
  return rawDateStr.trim().substring(0, 10);
}

function updatePerformanceDashboard() {
  const startDate = document.getElementById('perfStartDateInput')?.value || '';
  const endDate = document.getElementById('perfEndDateInput')?.value || '';
  const statusFilter = document.getElementById('perfStatusSelect')?.value || 'concluidas';
  const groupMode = document.getElementById('perfGroupSelect')?.value || 'dia';

  const filteredBets = rawBets.filter(bet => {
    const status = bet.status || 'Pendente';
    
    if (statusFilter === 'concluidas') {
      if (status === 'Pendente') return false;
    } else if (statusFilter === 'Pendente') {
      if (status !== 'Pendente') return false;
    } else if (statusFilter === 'Ganha') {
      if (status !== 'Ganha' && status !== 'Meio Ganha') return false;
    } else if (statusFilter === 'Perdida') {
      if (status !== 'Perdida' && status !== 'Meio Perdida') return false;
    } else if (statusFilter === 'Cashout') {
      if (status !== 'Cashout') return false;
    } else if (statusFilter === 'ANULADA') {
      if (status !== 'ANULADA' && status !== 'Anulada' && status !== 'Cancelada' && status !== 'CANCELADA') return false;
    }

    if (perfConfirmedFilter === '1') {
      const isConfirmed = (bet.confirmada !== undefined && bet.confirmada !== null) ? String(bet.confirmada) === '1' : true;
      const hasDebit = (bet.tem_debito !== undefined && parseInt(bet.tem_debito) > 0);
      if (!isConfirmed && !hasDebit) return false;
    } else if (perfConfirmedFilter === '0') {
      const isConfirmed = (bet.confirmada !== undefined && bet.confirmada !== null) ? String(bet.confirmada) === '1' : true;
      const hasDebit = (bet.tem_debito !== undefined && parseInt(bet.tem_debito) > 0);
      if (isConfirmed || hasDebit) return false;
    }

    const betDate = getBetDateBRT(bet);
    if (!betDate) return true;
    if (startDate && betDate < startDate) return false;
    if (endDate && betDate > endDate) return false;
    return true;
  });

  filteredBets.sort((a, b) => {
    const da = (a.data_hora_jogo_brt || a.data_hora_jogo || a.criado_em || '');
    const db = (b.data_hora_jogo_brt || b.data_hora_jogo || b.criado_em || '');
    return da.localeCompare(db);
  });

  const now = new Date();
  const todayStr = formatDateYYYYMMDD(now);
  const isSingleDayToday = (startDate && endDate && startDate === endDate && startDate === todayStr);

  let totalApostado = 0;
  let totalApostadoLiquidado = 0;
  let totalRetorno = 0;
  let totalLucroLiquido = 0;
  let winCount = 0;
  let decidedCount = 0;
  let settledCount = 0;

  // Rastreamento segregado: períodos concluídos (consolidados) vs em andamento
  let closedApostadoLiquidado = 0;
  let closedRetorno = 0;
  let closedLucroLiquido = 0;
  let closedWinCount = 0;
  let closedDecidedCount = 0;
  let closedBetsCount = 0;

  let openApostado = 0;
  let openPendingBetsCount = 0;
  let openTotalBetsCount = 0;
  let openPartialLucro = 0;

  const buckets = {};
  const mercadoBuckets = {};
  const leagueBuckets = {};
  const modalityBuckets = {
    cartoes: { apostado: 0, retorno: 0, lucro: 0, count: 0, ganhas: 0, perdidas: 0, anuladas: 0, decided: 0 },
    ah: { apostado: 0, retorno: 0, lucro: 0, count: 0, ganhas: 0, perdidas: 0, anuladas: 0, decided: 0 },
    outros: { apostado: 0, retorno: 0, lucro: 0, count: 0, ganhas: 0, perdidas: 0, anuladas: 0, decided: 0 }
  };
  const modalityTimeline = {};

  filteredBets.forEach(bet => {
    const status = bet.status || '';
    const valor = parseFloat(bet.valor_aposta) || 0;
    const netProfit = computeNetProfit(bet);
    const grossReturn = computeGrossReturn(bet);
    const rawDate = getBetDateBRT(bet);
    const isBetToday = (rawDate === todayStr);
    const isBetPending = (status === 'Pendente');

    // Agrupamento por Liga de Futebol
    let leagueName = (bet.league_name || 'Outras Ligas').trim();
    if (!leagueName) leagueName = 'Outras Ligas';

    if (!leagueBuckets[leagueName]) {
      leagueBuckets[leagueName] = {
        apostado: 0,
        retorno: 0,
        lucro: 0,
        count: 0,
        ganhas: 0,
        perdidas: 0,
        anuladas: 0,
        decided: 0
      };
    }
    leagueBuckets[leagueName].apostado += valor;
    leagueBuckets[leagueName].retorno += grossReturn;
    leagueBuckets[leagueName].lucro += netProfit;
    leagueBuckets[leagueName].count += 1;

    if (status === 'Ganha') {
      leagueBuckets[leagueName].ganhas += 1;
      leagueBuckets[leagueName].decided += 1;
    } else if (status === 'Meio Ganha') {
      leagueBuckets[leagueName].ganhas += 0.75;
      leagueBuckets[leagueName].decided += 1;
    } else if (status === 'Perdida') {
      leagueBuckets[leagueName].perdidas += 1;
      leagueBuckets[leagueName].decided += 1;
    } else if (status === 'Meio Perdida') {
      leagueBuckets[leagueName].perdidas += 0.75;
      leagueBuckets[leagueName].decided += 1;
    } else if (status === 'Cashout') {
      if (netProfit > 0) leagueBuckets[leagueName].ganhas += 1;
      else if (netProfit < 0) leagueBuckets[leagueName].perdidas += 1;
      leagueBuckets[leagueName].decided += 1;
    } else if (status === 'ANULADA' || status === 'Anulada' || status === 'Cancelada' || status === 'CANCELADA') {
      leagueBuckets[leagueName].anuladas += 1;
    }

    totalApostado += valor;
    if (status !== 'Pendente') {
      totalApostadoLiquidado += valor;
      settledCount++;
      if (status === 'Ganha') {
        winCount += 1.0;
        decidedCount += 1;
      } else if (status === 'Meio Ganha') {
        winCount += 0.75;
        decidedCount += 1;
      } else if (status === 'Meio Perdida') {
        winCount += 0.25;
        decidedCount += 1;
      } else if (status === 'Perdida') {
        decidedCount += 1;
      } else if (status === 'Cashout') {
        if (netProfit > 0) winCount += 1.0;
        decidedCount += 1;
      }
    }
    totalRetorno += grossReturn;
    totalLucroLiquido += netProfit;

    // Segregação para fechamento consolidado
    if (isBetToday) {
      openApostado += valor;
      openTotalBetsCount++;
      openPartialLucro += netProfit;
      if (isBetPending) {
        openPendingBetsCount++;
      }
    } else if (!isBetPending) {
      closedApostadoLiquidado += valor;
      closedRetorno += grossReturn;
      closedLucroLiquido += netProfit;
      closedBetsCount++;
      if (status === 'Ganha') {
        closedWinCount += 1.0;
        closedDecidedCount += 1;
      } else if (status === 'Meio Ganha') {
        closedWinCount += 0.75;
        closedDecidedCount += 1;
      } else if (status === 'Meio Perdida') {
        closedWinCount += 0.25;
        closedDecidedCount += 1;
      } else if (status === 'Perdida') {
        closedDecidedCount += 1;
      } else if (status === 'Cashout') {
        if (netProfit > 0) closedWinCount += 1.0;
        closedDecidedCount += 1;
      }
    } else {
      openApostado += valor;
      openPendingBetsCount++;
    }

    let rawMercado = (bet.mercado || 'Outros').trim();
    if (!rawMercado) rawMercado = 'Outros';

    let palpite = (bet.palpite || '').trim();
    let finalMercadoKey = rawMercado;

    const isCardMarket = /cart|card/i.test(rawMercado) || /cart|card/i.test(palpite);
    const isHandicapMarket = /handicap|empate anula|dnb/i.test(rawMercado) || /ah|handicap|empate anula|dnb/i.test(palpite);

    if (isCardMarket) {
      const isIndividual = /individual|time/i.test(rawMercado) || /individual|time/i.test(palpite);
      if (!isIndividual) {
        let isUnder = /under|menos/i.test(palpite) || /under|menos/i.test(rawMercado);
        let isOver = /over|mais/i.test(palpite) || /over|mais/i.test(rawMercado);
        let lineMatch = palpite.match(/(\d+(?:\.\d+)?)/) || rawMercado.match(/(\d+(?:\.\d+)?)/);

        if (lineMatch && (isUnder || isOver)) {
          let dirStr = isUnder ? 'Menos de' : 'Mais de';
          let lineVal = lineMatch[1];
          finalMercadoKey = `${dirStr} ${lineVal} Cartões`;
        } else {
          finalMercadoKey = 'Total de Cartões';
        }
      } else {
        if (palpite) {
          finalMercadoKey = `Cartões - Individual - ${palpite}`;
        } else {
          finalMercadoKey = rawMercado;
        }
      }
    } else if (isHandicapMarket) {
      let lineStr = null;
      const combinedText = (palpite + ' ' + rawMercado).toLowerCase();

      if (combinedText.includes('empate anula') || combinedText.includes('dnb') || combinedText.includes('0.0')) {
        lineStr = '0.0';
      } else {
        let lineMatch = palpite.match(/([+-]\d+(?:[\.,]\d+)?)/);
        if (!lineMatch) {
          lineMatch = palpite.match(/(\d+(?:[\.,]\d+)?)\s*ah\b/i);
        }
        if (lineMatch) {
          let rawLine = lineMatch[1].replace(',', '.');
          let val = parseFloat(rawLine);
          if (val === 0) {
            lineStr = '0.0';
          } else if (val > 0) {
            lineStr = rawLine.startsWith('+') ? rawLine : '+' + rawLine;
          } else {
            lineStr = rawLine;
          }
        }
      }

      if (lineStr) {
        finalMercadoKey = `Handicap Asiático ${lineStr}`;
      } else {
        finalMercadoKey = 'Handicap Asiático';
      }
    } else {
      if (palpite && !rawMercado.toLowerCase().includes(palpite.toLowerCase())) {
        if (/mais de|menos de|over|under|[+-]\d|vence|vitória|empate/i.test(palpite)) {
          let cleanP = palpite
            .replace(/^under\s+/i, 'Menos de ')
            .replace(/^over\s+/i, 'Mais de ')
            .trim();
          finalMercadoKey = `${rawMercado} - ${cleanP}`;
        }
      }
    }

    if (!mercadoBuckets[finalMercadoKey]) {
      mercadoBuckets[finalMercadoKey] = { apostado: 0, retorno: 0, lucro: 0, count: 0 };
    }
    mercadoBuckets[finalMercadoKey].apostado += valor;
    mercadoBuckets[finalMercadoKey].retorno += grossReturn;
    mercadoBuckets[finalMercadoKey].lucro += netProfit;
    mercadoBuckets[finalMercadoKey].count += 1;

    let key = rawDate || 'Sem Data';
    if (rawDate) {
      if (groupMode === 'semana') {
        key = getWeekKey(rawDate);
      } else if (groupMode === 'mes') {
        key = rawDate.substring(0, 7);
      }
    }

    if (!buckets[key]) {
      buckets[key] = {
        apostado: 0,
        retorno: 0,
        lucro: 0,
        count: 0,
        pendingCount: 0,
        hasPending: false,
        isToday: (rawDate === todayStr),
        ganhas: 0,
        decided: 0
      };
    }
    buckets[key].apostado += valor;
    buckets[key].retorno += grossReturn;
    buckets[key].lucro += netProfit;
    buckets[key].count += 1;
    if (status === 'Pendente') {
      buckets[key].pendingCount += 1;
      buckets[key].hasPending = true;
    }

    if (status === 'Ganha') {
      buckets[key].ganhas += 1.0;
      buckets[key].decided += 1;
    } else if (status === 'Meio Ganha') {
      buckets[key].ganhas += 0.75;
      buckets[key].decided += 1;
    } else if (status === 'Meio Perdida') {
      buckets[key].ganhas += 0.25;
      buckets[key].decided += 1;
    } else if (status === 'Perdida') {
      buckets[key].decided += 1;
    } else if (status === 'Cashout') {
      if (netProfit > 0) buckets[key].ganhas += 1.0;
      buckets[key].decided += 1;
    }

    let modKey = 'outros';
    if (isCardMarket) {
      modKey = 'cartoes';
    } else if (isHandicapMarket) {
      modKey = 'ah';
    }

    modalityBuckets[modKey].apostado += valor;
    modalityBuckets[modKey].retorno += grossReturn;
    modalityBuckets[modKey].lucro += netProfit;
    modalityBuckets[modKey].count += 1;

    if (status === 'Ganha') {
      modalityBuckets[modKey].ganhas += 1;
      modalityBuckets[modKey].decided += 1;
    } else if (status === 'Meio Ganha') {
      modalityBuckets[modKey].ganhas += 0.75;
      modalityBuckets[modKey].decided += 1;
    } else if (status === 'Meio Perdida') {
      modalityBuckets[modKey].perdidas += 0.75;
      modalityBuckets[modKey].decided += 1;
    } else if (status === 'Perdida') {
      modalityBuckets[modKey].perdidas += 1;
      modalityBuckets[modKey].decided += 1;
    } else if (status === 'Cashout') {
      if (netProfit > 0) modalityBuckets[modKey].ganhas += 1;
      else if (netProfit < 0) modalityBuckets[modKey].perdidas += 1;
      modalityBuckets[modKey].decided += 1;
    } else if (status === 'ANULADA' || status === 'Anulada' || status === 'Cancelada' || status === 'CANCELADA') {
      modalityBuckets[modKey].anuladas += 1;
    }

    if (!modalityTimeline[key]) {
      modalityTimeline[key] = { cartoesLucro: 0, ahLucro: 0, outrosLucro: 0, cartoesCount: 0, ahCount: 0 };
    }
    if (modKey === 'cartoes') {
      modalityTimeline[key].cartoesLucro += netProfit;
      modalityTimeline[key].cartoesCount += 1;
    } else if (modKey === 'ah') {
      modalityTimeline[key].ahLucro += netProfit;
      modalityTimeline[key].ahCount += 1;
    } else {
      modalityTimeline[key].outrosLucro += netProfit;
    }
  });

  const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const formatPct = (v) => (v >= 0 ? '+' : '') + v.toFixed(1).replace('.', ',') + '%';

  // Se o usuário selecionou visualização geral ou multi-dias, os KPIs consolidam estritamente dias concluídos
  const useClosedOnly = (!isSingleDayToday && closedBetsCount > 0);
  const displayApostado = useClosedOnly ? closedApostadoLiquidado : (totalApostadoLiquidado > 0 ? totalApostadoLiquidado : totalApostado);
  const displayRetorno = useClosedOnly ? closedRetorno : totalRetorno;
  const displayLucro = useClosedOnly ? closedLucroLiquido : totalLucroLiquido;
  const displayWinCount = useClosedOnly ? closedWinCount : winCount;
  const displayDecidedCount = useClosedOnly ? closedDecidedCount : decidedCount;
  const displayTotalApostas = useClosedOnly ? closedBetsCount : filteredBets.length;

  const baseInvestida = displayApostado > 0 ? displayApostado : 1;
  const roi = (displayLucro / baseInvestida) * 100;
  const winRate = displayDecidedCount > 0 ? (displayWinCount / displayDecidedCount) * 100 : 0;

  const elTotalApostado = document.getElementById('kpiTotalApostado');
  if (elTotalApostado) {
    elTotalApostado.textContent = formatBrl(displayApostado);
  }
  const elPendenteInfo = document.getElementById('kpiPendenteInfo');
  if (elPendenteInfo) {
    if (openApostado > 0) {
      if (openPendingBetsCount > 0) {
        elPendenteInfo.innerHTML = `<i class="bi bi-hourglass-split text-warning me-1"></i> + ${formatBrl(openApostado)} em andamento hoje (${openPendingBetsCount} jogos pendentes)`;
      } else {
        elPendenteInfo.innerHTML = `<i class="bi bi-hourglass-split text-warning me-1"></i> + ${formatBrl(openApostado)} em andamento hoje (aguardando fechamento noturno)`;
      }
      elPendenteInfo.style.display = 'block';
    } else {
      elPendenteInfo.style.display = 'none';
    }
  }
  document.getElementById('kpiTotalRetorno').textContent = formatBrl(displayRetorno);
  
  const kpiLucroEl = document.getElementById('kpiLucroLiquido');
  kpiLucroEl.textContent = formatBrl(displayLucro);
  kpiLucroEl.className = 'kpi-value ' + (displayLucro >= 0 ? 'text-success' : 'text-danger');

  const elLucroHoje = document.getElementById('kpiLucroHojeInfo');
  if (elLucroHoje) {
    if (useClosedOnly && openTotalBetsCount > 0) {
      elLucroHoje.innerHTML = `<i class="bi bi-hourglass-split me-1"></i>Hoje (em apuração): ${formatBrl(openPartialLucro)} parcial`;
      elLucroHoje.style.display = 'block';
    } else if (isSingleDayToday) {
      elLucroHoje.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>Dia em andamento (aguardando fechamento noturno)`;
      elLucroHoje.style.display = 'block';
    } else {
      elLucroHoje.style.display = 'none';
    }
  }

  const kpiRoiEl = document.getElementById('kpiRoi');
  kpiRoiEl.textContent = formatPct(roi);
  kpiRoiEl.className = 'kpi-value ' + (roi >= 0 ? 'text-success' : 'text-danger');

  document.getElementById('kpiWinRate').textContent = winRate.toFixed(1).replace('.', ',') + '%';
  document.getElementById('kpiTotalApostas').textContent = displayTotalApostas;

  const bucketKeys = Object.keys(buckets).sort();
  const labels = [];
  const cumulativeApostadoData = [];
  const cumulativeLucroData = [];
  const cumulativeCartoesLucro = [];
  const cumulativeAhLucro = [];
  const cumulativeWinRateData = [];

  let runningApostado = 0;
  let runningLucro = 0;
  let runningCartoesLucro = 0;
  let runningAhLucro = 0;
  let runningGanhas = 0;
  let runningDecided = 0;

  bucketKeys.forEach(k => {
    const b = buckets[k];
    const isOpen = (k === todayStr || (groupMode === 'dia' && k >= todayStr) || b.hasPending);

    runningApostado += b.apostado;
    // O lucro acumulado fechado na curva gráfica só soma períodos concluídos
    if (!isOpen || isSingleDayToday) {
      runningLucro += b.lucro;
    }

    const modTime = modalityTimeline[k] || { cartoesLucro: 0, ahLucro: 0 };
    if (!isOpen || isSingleDayToday) {
      runningCartoesLucro += modTime.cartoesLucro;
      runningAhLucro += modTime.ahLucro;
    }
    cumulativeCartoesLucro.push(runningCartoesLucro);
    cumulativeAhLucro.push(runningAhLucro);

    if (!isOpen || isSingleDayToday) {
      runningGanhas += (b.ganhas || 0);
      runningDecided += (b.decided || 0);
    }
    const curWinRate = runningDecided > 0 ? (runningGanhas / runningDecided) * 100 : 0;
    cumulativeWinRateData.push(parseFloat(curWinRate.toFixed(1)));

    let label = k;
    if (groupMode === 'dia' && k.length === 10) {
      const parts = k.split('-');
      label = `${parts[2]}/${parts[1]}`;
    } else if (groupMode === 'mes' && k.length === 7) {
      const parts = k.split('-');
      label = `${parts[1]}/${parts[0]}`;
    }

    labels.push(label);
    cumulativeApostadoData.push(runningApostado);
    cumulativeLucroData.push(runningLucro);
  });

  // Atualização dos Mini KPIs de Diagnóstico por Modalidade
  const cartoesLucro = modalityBuckets.cartoes.lucro;
  const cartoesApostado = modalityBuckets.cartoes.apostado;
  const cartoesRoi = cartoesApostado > 0 ? (cartoesLucro / cartoesApostado) * 100 : 0;
  const cartoesWinRate = modalityBuckets.cartoes.decided > 0 ? (modalityBuckets.cartoes.ganhas / modalityBuckets.cartoes.decided) * 100 : 0;

  const ahLucro = modalityBuckets.ah.lucro;
  const ahApostado = modalityBuckets.ah.apostado;
  const ahRoi = ahApostado > 0 ? (ahLucro / ahApostado) * 100 : 0;
  const ahWinRate = modalityBuckets.ah.decided > 0 ? (modalityBuckets.ah.ganhas / modalityBuckets.ah.decided) * 100 : 0;

  const deltaLucro = cartoesLucro - ahLucro;

  const elCardsLucro = document.getElementById('kpiCardsLucro');
  const elCardsRoi = document.getElementById('kpiCardsRoi');
  const elCardsDetails = document.getElementById('kpiCardsDetails');
  const elBadgeCards = document.getElementById('badgeCardsSummary');
  if (elCardsLucro) {
    elCardsLucro.textContent = formatBrl(cartoesLucro);
    elCardsLucro.className = 'fw-bold fs-5 ' + (cartoesLucro >= 0 ? 'text-success' : 'text-danger');
  }
  if (elCardsRoi) {
    elCardsRoi.textContent = 'ROI: ' + formatPct(cartoesRoi);
    elCardsRoi.className = 'small fw-semibold ' + (cartoesRoi >= 0 ? 'text-success' : 'text-danger');
  }
  if (elCardsDetails) {
    elCardsDetails.textContent = `${modalityBuckets.cartoes.count} bets | Win: ${cartoesWinRate.toFixed(1).replace('.', ',')}%`;
  }
  if (elBadgeCards) {
    elBadgeCards.innerHTML = `<i class="bi bi-square-fill me-1" style="color: #00e676;"></i> Cartões: ${formatBrl(cartoesLucro)}`;
  }

  const elAhLucro = document.getElementById('kpiAhLucro');
  const elAhRoi = document.getElementById('kpiAhRoi');
  const elAhDetails = document.getElementById('kpiAhDetails');
  const elAhBadge = document.getElementById('kpiAhBadge');
  const elBadgeAh = document.getElementById('badgeAhSummary');
  const elAhCardBox = document.getElementById('kpiAhCardBox');

  if (elAhLucro) {
    elAhLucro.textContent = formatBrl(ahLucro);
    elAhLucro.className = 'fw-bold fs-5 ' + (ahLucro >= 0 ? 'text-success' : 'text-danger');
  }
  if (elAhRoi) {
    elAhRoi.textContent = 'ROI: ' + formatPct(ahRoi);
    elAhRoi.className = 'small fw-semibold ' + (ahRoi >= 0 ? 'text-success' : 'text-danger');
  }
  if (elAhDetails) {
    elAhDetails.textContent = `${modalityBuckets.ah.count} bets | Win: ${ahWinRate.toFixed(1).replace('.', ',')}%`;
  }
  if (elAhBadge) {
    if (ahLucro < 0) {
      elAhBadge.textContent = 'Dreno de Lucro 🔴';
      elAhBadge.className = 'badge bg-danger text-white fw-bold';
    } else {
      elAhBadge.textContent = 'Lucrativo 🟢';
      elAhBadge.className = 'badge bg-success text-dark fw-bold';
    }
  }
  if (elAhCardBox) {
    if (ahLucro < 0) {
      elAhCardBox.style.background = 'rgba(255, 82, 82, 0.08)';
      elAhCardBox.style.borderColor = 'rgba(255, 82, 82, 0.3)';
    } else {
      elAhCardBox.style.background = 'rgba(255, 145, 0, 0.06)';
      elAhCardBox.style.borderColor = 'rgba(255, 145, 0, 0.3)';
    }
  }
  if (elBadgeAh) {
    elBadgeAh.innerHTML = `<i class="bi bi-square-fill me-1" style="color: #ff9100;"></i> AH: ${formatBrl(ahLucro)}`;
  }

  const elDeltaLucro = document.getElementById('kpiDeltaLucro');
  const elDeltaStatus = document.getElementById('kpiDeltaStatus');
  const elDeltaDetails = document.getElementById('kpiDeltaDetails');
  if (elDeltaLucro) {
    elDeltaLucro.textContent = (deltaLucro >= 0 ? '+' : '') + formatBrl(deltaLucro);
    elDeltaLucro.className = 'fw-bold fs-5 ' + (deltaLucro >= 0 ? 'text-success' : 'text-danger');
  }
  if (elDeltaStatus) {
    if (deltaLucro > 0) {
      elDeltaStatus.textContent = 'Vantagem Cartões 🛡️';
      elDeltaStatus.className = 'small text-success fw-semibold';
    } else if (deltaLucro < 0) {
      elDeltaStatus.textContent = 'Vantagem AH ⚡';
      elDeltaStatus.className = 'small text-warning fw-semibold';
    } else {
      elDeltaStatus.textContent = 'Equilíbrio ⚖️';
      elDeltaStatus.className = 'small text-info fw-semibold';
    }
  }
  if (elDeltaDetails) {
    elDeltaDetails.textContent = deltaLucro > 0
      ? `Cartões superou AH em ${formatBrl(Math.abs(deltaLucro))} no saldo real`
      : (deltaLucro < 0 ? `AH superou Cartões em ${formatBrl(Math.abs(deltaLucro))} no saldo real` : 'Ambas modalidades com resultado idêntico');
  }

  const mercadoKeys = Object.keys(mercadoBuckets).sort((a, b) => mercadoBuckets[b].lucro - mercadoBuckets[a].lucro);

  const mercadoLabels = [];
  const mercadoLucroData = [];
  const mercadoBgColors = [];
  const mercadoBorderColors = [];
  const mercadoMetaDetails = [];

  mercadoKeys.forEach(key => {
    const b = mercadoBuckets[key];
    mercadoLabels.push(key);
    mercadoLucroData.push(b.lucro);
    if (b.lucro >= 0) {
      mercadoBgColors.push('rgba(0, 230, 118, 0.75)');
      mercadoBorderColors.push('#00e676');
    } else {
      mercadoBgColors.push('rgba(255, 82, 82, 0.75)');
      mercadoBorderColors.push('#ff5252');
    }

    const roi = b.apostado > 0 ? (b.lucro / b.apostado) * 100 : 0;
    mercadoMetaDetails.push({
      apostado: b.apostado,
      retorno: b.retorno,
      lucro: b.lucro,
      count: b.count,
      roi: roi
    });
  });

  // ==========================================
  // DIAGNÓSTICO E RANKING POR LIGA DE FUTEBOL
  // ==========================================
  const allLeagueKeys = Object.keys(leagueBuckets);
  let worstLeague = null;
  let bestLeague = null;
  let totalLossDrained = 0;
  let countProfitLeagues = 0;
  let countLossLeagues = 0;

  allLeagueKeys.forEach(k => {
    const l = leagueBuckets[k];
    if (l.lucro < -0.01) {
      countLossLeagues++;
      totalLossDrained += Math.abs(l.lucro);
      if (!worstLeague || l.lucro < worstLeague.lucro) {
        worstLeague = { name: k, ...l };
      }
    } else if (l.lucro > 0.01) {
      countProfitLeagues++;
      if (!bestLeague || l.lucro > bestLeague.lucro) {
        bestLeague = { name: k, ...l };
      }
    }
  });

  // Atualizar Mini KPIs de Diagnóstico no topo do card de Ligas
  const worstNameEl = document.getElementById('kpiWorstLeagueName');
  const worstProfitEl = document.getElementById('kpiWorstLeagueProfit');
  const worstDetailsEl = document.getElementById('kpiWorstLeagueDetails');
  const worstBadgeEl = document.getElementById('badgeDrainAction');

  if (worstLeague) {
    const worstRoi = worstLeague.apostado > 0 ? (worstLeague.lucro / worstLeague.apostado) * 100 : 0;
    if (worstNameEl) worstNameEl.textContent = worstLeague.name;
    if (worstProfitEl) worstProfitEl.textContent = formatBrl(worstLeague.lucro);
    if (worstDetailsEl) worstDetailsEl.textContent = `ROI: ${formatPct(worstRoi)} | ${Math.round(worstLeague.perdidas)}D de ${worstLeague.count} bets`;
    if (worstBadgeEl) {
      worstBadgeEl.textContent = 'Corte Recomendado ✂️';
      worstBadgeEl.className = 'badge bg-danger text-white';
    }
  } else {
    if (worstNameEl) worstNameEl.textContent = 'Nenhuma Liga com Prejuízo 🎉';
    if (worstProfitEl) worstProfitEl.textContent = 'R$ 0,00';
    if (worstDetailsEl) worstDetailsEl.textContent = 'Banca 100% protegida no período';
    if (worstBadgeEl) {
      worstBadgeEl.textContent = 'Sem Dreno';
      worstBadgeEl.className = 'badge bg-secondary text-white';
    }
  }

  const bestNameEl = document.getElementById('kpiBestLeagueName');
  const bestProfitEl = document.getElementById('kpiBestLeagueProfit');
  const bestDetailsEl = document.getElementById('kpiBestLeagueDetails');

  if (bestLeague) {
    const bestRoi = bestLeague.apostado > 0 ? (bestLeague.lucro / bestLeague.apostado) * 100 : 0;
    const bestWinRate = bestLeague.decided > 0 ? (bestLeague.ganhas / bestLeague.decided) * 100 : 0;
    if (bestNameEl) bestNameEl.textContent = bestLeague.name;
    if (bestProfitEl) bestProfitEl.textContent = formatBrl(bestLeague.lucro);
    if (bestDetailsEl) bestDetailsEl.textContent = `ROI: ${formatPct(bestRoi)} | Win: ${bestWinRate.toFixed(1)}% (${bestLeague.count} bets)`;
  } else {
    if (bestNameEl) bestNameEl.textContent = 'Sem dados suficientes';
    if (bestProfitEl) bestProfitEl.textContent = 'R$ 0,00';
    if (bestDetailsEl) bestDetailsEl.textContent = 'Aguardando liquidação';
  }

  const countBadgeEl = document.getElementById('kpiLeagueCountBadge');
  if (countBadgeEl) countBadgeEl.textContent = `${allLeagueKeys.length} Ligas`;

  const balanceSummaryEl = document.getElementById('kpiLeagueBalanceSummary');
  if (balanceSummaryEl) {
    balanceSummaryEl.innerHTML = `<span class="text-success fw-bold">${countProfitLeagues} Lucrativas</span> / <span class="text-danger fw-bold">${countLossLeagues} Deficitárias</span>`;
  }

  const lossTotalEl = document.getElementById('kpiLeagueLossTotal');
  if (lossTotalEl) {
    lossTotalEl.textContent = `Prejuízo Drenado: ${formatBrl(totalLossDrained)}`;
  }

  // Filtragem e Ordenação das Ligas para o Gráfico
  let activeLeagueKeys = [...allLeagueKeys];
  if (leagueSegmentFilter === 'loss_only') {
    activeLeagueKeys = activeLeagueKeys.filter(k => leagueBuckets[k].lucro < -0.01);
  } else if (leagueSegmentFilter === 'profit_only') {
    activeLeagueKeys = activeLeagueKeys.filter(k => leagueBuckets[k].lucro > 0.01);
  }

  activeLeagueKeys.sort((a, b) => {
    const la = leagueBuckets[a];
    const lb = leagueBuckets[b];
    if (leagueSortMode === 'losses_first') {
      return la.lucro - lb.lucro; // Menor lucro (maior prejuízo) primeiro
    } else if (leagueSortMode === 'profit_first') {
      return lb.lucro - la.lucro; // Maior lucro primeiro
    } else if (leagueSortMode === 'roi') {
      const roiA = la.apostado > 0 ? (la.lucro / la.apostado) * 100 : -9999;
      const roiB = lb.apostado > 0 ? (lb.lucro / lb.apostado) * 100 : -9999;
      return roiA - roiB; // Pior ROI primeiro
    } else if (leagueSortMode === 'volume') {
      return lb.count - la.count;
    }
    return la.lucro - lb.lucro;
  });

  const leagueLabels = [];
  const leagueLucroData = [];
  const leagueBgColors = [];
  const leagueBorderColors = [];
  const leagueMetaDetails = [];

  activeLeagueKeys.forEach(key => {
    const b = leagueBuckets[key];
    leagueLabels.push(key);
    leagueLucroData.push(b.lucro);
    if (b.lucro >= 0) {
      leagueBgColors.push('rgba(0, 230, 118, 0.75)');
      leagueBorderColors.push('#00e676');
    } else {
      leagueBgColors.push('rgba(255, 82, 82, 0.75)');
      leagueBorderColors.push('#ff5252');
    }

    const roi = b.apostado > 0 ? (b.lucro / b.apostado) * 100 : 0;
    const winRate = b.decided > 0 ? (b.ganhas / b.decided) * 100 : 0;
    leagueMetaDetails.push({
      name: key,
      apostado: b.apostado,
      retorno: b.retorno,
      lucro: b.lucro,
      count: b.count,
      ganhas: b.ganhas,
      perdidas: b.perdidas,
      anuladas: b.anuladas,
      roi: roi,
      winRate: winRate
    });
  });

  renderChart(labels, cumulativeApostadoData, cumulativeLucroData, cumulativeWinRateData);
  renderModalidadesChart(labels, cumulativeCartoesLucro, cumulativeAhLucro, modalityTimeline, bucketKeys);
  renderMercadoChart(mercadoLabels, mercadoLucroData, mercadoBgColors, mercadoBorderColors, mercadoMetaDetails);
  renderLeagueProfitChart(leagueLabels, leagueLucroData, leagueBgColors, leagueBorderColors, leagueMetaDetails);
  renderLeagueTableBreakdown(activeLeagueKeys, leagueBuckets);
  renderTableBreakdown(bucketKeys, buckets, groupMode);
}

function renderChart(labels, apostadoData, lucroData, winRateData) {
  const canvas = document.getElementById('performanceChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  if (perfChart) {
    perfChart.destroy();
  }

  perfChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Valor Simulado Bruto Acumulado (R$)',
          data: apostadoData,
          borderColor: '#00b0ff',
          backgroundColor: 'rgba(0, 176, 255, 0.12)',
          borderWidth: 3,
          pointRadius: 4,
          pointBackgroundColor: '#00b0ff',
          tension: 0.3,
          fill: true,
          yAxisID: 'y'
        },
        {
          label: 'Lucro Líquido Real Acumulado (R$)',
          data: lucroData,
          borderColor: '#00e676',
          backgroundColor: 'rgba(0, 230, 118, 0.15)',
          borderWidth: 3.5,
          pointRadius: 5,
          pointBackgroundColor: '#00e676',
          tension: 0.3,
          fill: true,
          yAxisID: 'y'
        },
        {
          label: 'Taxa de Acerto Acumulada (%)',
          data: winRateData,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245, 158, 11, 0.08)',
          borderWidth: 2.5,
          borderDash: [5, 4],
          pointRadius: 4,
          pointBackgroundColor: '#f59e0b',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 1.5,
          pointHoverRadius: 6,
          tension: 0.3,
          fill: false,
          yAxisID: 'y1'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            color: '#f0f6fc',
            font: { family: 'Inter', size: 13, weight: 'bold' }
          }
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                if (context.dataset.yAxisID === 'y1' || (context.dataset.label && context.dataset.label.includes('%'))) {
                  label += context.parsed.y.toFixed(1).replace('.', ',') + '%';
                } else {
                  label += context.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                }
              }
              return label;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#94a3b8', font: { family: 'Inter' } }
        },
        y: {
          type: 'linear',
          display: true,
          position: 'left',
          grid: { color: 'rgba(255, 255, 255, 0.08)' },
          ticks: {
            color: '#94a3b8',
            font: { family: 'Inter' },
            callback: function(value) {
              return 'R$ ' + value.toLocaleString('pt-BR');
            }
          }
        },
        y1: {
          type: 'linear',
          display: true,
          position: 'right',
          grid: { drawOnChartArea: false },
          min: 0,
          max: 100,
          ticks: {
            color: '#f59e0b',
            font: { family: 'Inter', weight: 'bold' },
            callback: function(value) {
              return value + '%';
            }
          }
        }
      }
    }
  });
}

function renderModalidadesChart(labels, cartoesData, ahData, modalityTimeline, rawKeys) {
  const canvas = document.getElementById('modalidadesProfitChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  if (modalidadesChart) {
    modalidadesChart.destroy();
  }

  modalidadesChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Cartões (Under) - Lucro Acumulado (R$)',
          data: cartoesData,
          borderColor: '#00e676',
          backgroundColor: 'rgba(0, 230, 118, 0.12)',
          borderWidth: 3,
          pointRadius: 4,
          pointBackgroundColor: '#00e676',
          pointHoverRadius: 6,
          tension: 0.3,
          fill: true
        },
        {
          label: 'Handicap Asiático (AH) - Lucro Acumulado (R$)',
          data: ahData,
          borderColor: '#ff9100',
          backgroundColor: 'rgba(255, 145, 0, 0.10)',
          borderWidth: 3,
          pointRadius: 4,
          pointBackgroundColor: '#ff9100',
          pointHoverRadius: 6,
          tension: 0.3,
          fill: true
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            color: '#f0f6fc',
            font: { family: 'Inter', size: 13, weight: 'bold' }
          }
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.dataset.label || '';
              if (label) label += ': ';
              if (context.parsed.y !== null) {
                label += context.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
              }
              return label;
            },
            afterBody: function(tooltipItems) {
              if (!tooltipItems || tooltipItems.length === 0) return [];
              const dataIdx = tooltipItems[0].dataIndex;
              const dateKey = rawKeys ? rawKeys[dataIdx] : null;
              const t = (modalityTimeline && dateKey) ? modalityTimeline[dateKey] : null;
              if (!t) return [];
              const lines = [];
              lines.push('──────────────────────');
              lines.push(`Resultado no Ponto (${tooltipItems[0].label}):`);
              lines.push(`• Cartões: ${t.cartoesLucro >= 0 ? '+' : ''}${t.cartoesLucro.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })} (${t.cartoesCount} bets)`);
              lines.push(`• Handicap Asiático: ${t.ahLucro >= 0 ? '+' : ''}${t.ahLucro.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })} (${t.ahCount} bets)`);
              return lines;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#94a3b8', font: { family: 'Inter' } }
        },
        y: {
          grid: { color: 'rgba(255, 255, 255, 0.08)' },
          ticks: {
            color: '#94a3b8',
            font: { family: 'Inter' },
            callback: function(value) {
              return 'R$ ' + value.toLocaleString('pt-BR');
            }
          }
        }
      }
    }
  });
}

function renderMercadoChart(labels, data, bgColors, borderColors, metaDetails) {
  const canvas = document.getElementById('mercadoProfitChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  const containerBox = document.getElementById('mercadoChartBox');
  if (containerBox) {
    const dynamicHeight = Math.max(320, labels.length * 45);
    containerBox.style.height = `${dynamicHeight}px`;
  }

  if (mercadoChart) {
    mercadoChart.destroy();
  }

  mercadoChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Lucro Líquido Real (R$)',
          data: data,
          backgroundColor: bgColors,
          borderColor: borderColors,
          borderWidth: 1.5,
          borderRadius: 6,
          barThickness: labels.length > 8 ? 'flex' : 24,
          maxBarThickness: 32
        }
      ]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(15, 23, 42, 0.95)',
          titleColor: '#f0f6fc',
          bodyColor: '#cbd5e1',
          borderColor: '#334155',
          borderWidth: 1,
          padding: 12,
          displayColors: false,
          callbacks: {
            title: function(context) {
              return 'Mercado: ' + (context[0]?.label || '');
            },
            label: function(context) {
              const idx = context.dataIndex;
              const meta = metaDetails[idx];
              if (!meta) return '';

              const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
              const formatPct = (v) => (v >= 0 ? '+' : '') + v.toFixed(1).replace('.', ',') + '%';

              return [
                `Lucro Líquido: ${formatBrl(meta.lucro)}`,
                `Total Simulado: ${formatBrl(meta.apostado)}`,
                `Total Retorno: ${formatBrl(meta.retorno)}`,
                `ROI: ${formatPct(meta.roi)}`,
                `Qtd Simulações de Apostas: ${meta.count}`
              ];
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255, 255, 255, 0.08)' },
          ticks: {
            color: '#94a3b8',
            font: { family: 'Inter' },
            callback: function(value) {
              return 'R$ ' + value.toLocaleString('pt-BR');
            }
          }
        },
        y: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: {
            color: '#f0f6fc',
            font: { family: 'Inter', weight: '600', size: 12 }
          }
        }
      }
    }
  });
}

function renderLeagueProfitChart(labels, data, bgColors, borderColors, metaDetails) {
  const canvas = document.getElementById('leagueProfitChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  const containerBox = document.getElementById('leagueChartBox');
  if (containerBox) {
    const dynamicHeight = Math.max(340, labels.length * 38);
    containerBox.style.height = `${dynamicHeight}px`;
  }

  if (leagueChart) {
    leagueChart.destroy();
  }

  leagueChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Lucro Líquido Real (R$)',
          data: data,
          backgroundColor: bgColors,
          borderColor: borderColors,
          borderWidth: 1.5,
          borderRadius: 6,
          barThickness: labels.length > 10 ? 'flex' : 24,
          maxBarThickness: 32
        }
      ]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(15, 23, 42, 0.95)',
          titleColor: '#f0f6fc',
          bodyColor: '#cbd5e1',
          borderColor: '#334155',
          borderWidth: 1,
          padding: 12,
          displayColors: false,
          callbacks: {
            title: function(context) {
              return 'Liga: ' + (context[0]?.label || '');
            },
            label: function(context) {
              const idx = context.dataIndex;
              const meta = metaDetails[idx];
              if (!meta) return '';

              const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
              const formatPct = (v) => (v >= 0 ? '+' : '') + v.toFixed(1).replace('.', ',') + '%';

              const lines = [
                `Lucro Líquido: ${formatBrl(meta.lucro)}`,
                `ROI: ${formatPct(meta.roi)}`,
                `Taxa de Acerto (Win Rate): ${meta.winRate.toFixed(1)}%`,
                `Total Simulado: ${formatBrl(meta.apostado)}`,
                `Retorno Total: ${formatBrl(meta.retorno)}`,
                `Desempenho: ${Math.round(meta.ganhas)}V / ${Math.round(meta.perdidas)}D / ${meta.anuladas}A (${meta.count} apostas)`
              ];

              if (meta.lucro < 0) {
                lines.push('⚠️ Sugestão de Banca: Avaliar corte/pausa desta liga');
              } else if (meta.roi > 25) {
                lines.push('⭐ Destaque: Liga de altíssima rentabilidade');
              }
              return lines;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { 
            color: function(context) {
              if (context.tick && context.tick.value === 0) return 'rgba(255, 255, 255, 0.35)';
              return 'rgba(255, 255, 255, 0.08)';
            },
            lineWidth: function(context) {
              if (context.tick && context.tick.value === 0) return 2;
              return 1;
            }
          },
          ticks: {
            color: '#94a3b8',
            font: { family: 'Inter' },
            callback: function(value) {
              return 'R$ ' + value.toLocaleString('pt-BR');
            }
          }
        },
        y: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: {
            color: '#f0f6fc',
            font: { family: 'Inter', weight: '600', size: 12 }
          }
        }
      }
    }
  });
}

function renderLeagueTableBreakdown(keys, buckets) {
  const tbody = document.getElementById('leagueTableBody');
  if (!tbody) return;

  if (keys.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Nenhuma liga encontrada para os filtros selecionados.</td></tr>';
    return;
  }

  const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const formatPct = (v) => (v >= 0 ? '+' : '') + v.toFixed(1).replace('.', ',') + '%';

  let html = '';
  keys.forEach(k => {
    const b = buckets[k];
    const roi = b.apostado > 0 ? (b.lucro / b.apostado) * 100 : 0;
    const winRate = b.decided > 0 ? (b.ganhas / b.decided) * 100 : 0;

    const lucroClass = b.lucro >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
    const roiClass = roi >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';

    let badgeHtml = '';
    if (b.lucro < 0) {
      badgeHtml = '<span class="badge bg-danger text-white px-2 py-1"><i class="bi bi-scissors me-1"></i> Corte Sugerido</span>';
    } else if (roi > 20) {
      badgeHtml = '<span class="badge bg-success text-dark fw-bold px-2 py-1"><i class="bi bi-star-fill me-1"></i> Manter Ativa</span>';
    } else if (b.lucro > 0) {
      badgeHtml = '<span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-check-circle me-1"></i> Rentável</span>';
    } else {
      badgeHtml = '<span class="badge bg-secondary text-white px-2 py-1">Neutro</span>';
    }

    html += `
      <tr>
        <td class="fw-semibold text-white">${k}</td>
        <td class="text-center">${b.count}</td>
        <td class="text-center small">${Math.round(b.ganhas)}V / ${Math.round(b.perdidas)}D / ${b.anuladas}A</td>
        <td class="text-center">${winRate.toFixed(1)}%</td>
        <td>${formatBrl(b.apostado)}</td>
        <td>${formatBrl(b.retorno)}</td>
        <td class="${lucroClass}">${formatBrl(b.lucro)}</td>
        <td class="${roiClass}">${formatPct(roi)}</td>
        <td class="text-center">${badgeHtml}</td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

function renderTableBreakdown(keys, buckets, groupMode) {
  const tbody = document.getElementById('tableBreakdownBody');
  const tfoot = document.getElementById('tableBreakdownFoot');
  if (!tbody) return;

  if (keys.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum dado encontrado para o período selecionado.</td></tr>';
    if (tfoot) tfoot.innerHTML = '';
    return;
  }

  const now = new Date();
  const todayStr = formatDateYYYYMMDD(now);
  const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  let html = '';
  let sumCount = 0;
  let sumApostado = 0;
  let sumRetorno = 0;
  let sumLucro = 0;

  const closedKeys = keys.filter(k => {
    const b = buckets[k];
    const isOpen = (k === todayStr || (groupMode === 'dia' && k >= todayStr) || b.hasPending);
    return !isOpen;
  });

  keys.forEach(k => {
    const b = buckets[k];
    const isOpen = (k === todayStr || (groupMode === 'dia' && k >= todayStr) || b.hasPending);
    let label = k;
    if (groupMode === 'dia' && k.length === 10) {
      const parts = k.split('-');
      label = `${parts[2]}/${parts[1]}/${parts[0]}`;
    } else if (groupMode === 'mes' && k.length === 7) {
      const parts = k.split('-');
      label = `${parts[1]}/${parts[0]}`;
    }

    const bLucro = b.lucro;
    const roi = b.apostado > 0 ? (bLucro / b.apostado) * 100 : 0;
    const lucroClass = bLucro >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
    const roiClass = roi >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';

    if (!isOpen) {
      sumCount += b.count;
      sumApostado += b.apostado;
      sumRetorno += b.retorno;
      sumLucro += bLucro;
    }

    const statusBadge = isOpen
      ? `<span class="badge bg-warning text-dark ms-1" style="font-size:0.68rem;"><i class="bi bi-hourglass-split"></i> Em Andamento</span>`
      : '';
    const lucroBadge = isOpen
      ? `<span class="badge bg-secondary text-warning ms-1" style="font-size:0.65rem;">Parcial</span>`
      : '';

    html += `
      <tr class="${isOpen ? 'bg-opacity-10 bg-warning' : ''}">
        <td class="fw-semibold text-white">${label} ${statusBadge}</td>
        <td class="text-center">${b.count} ${isOpen && b.pendingCount > 0 ? `<small class="text-warning">(${b.pendingCount} pend.)</small>` : ''}</td>
        <td>${formatBrl(b.apostado)}</td>
        <td>${formatBrl(b.retorno)}</td>
        <td class="${lucroClass}">${formatBrl(bLucro)} ${lucroBadge}</td>
        <td class="${roiClass}">${(roi >= 0 ? '+' : '') + roi.toFixed(1).replace('.', ',')}%</td>
      </tr>
    `;
  });

  tbody.innerHTML = html;

  if (tfoot) {
    const n = closedKeys.length > 0 ? closedKeys.length : keys.length;
    const avgCount = sumCount / n;
    const avgApostado = sumApostado / n;
    const avgRetorno = sumRetorno / n;
    const avgLucro = sumLucro / n;
    const avgRoi = avgApostado > 0 ? (avgLucro / avgApostado) * 100 : 0;

    const avgCountStr = avgCount.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
    const avgLucroClass = avgLucro >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
    const avgRoiClass = avgRoi >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';

    tfoot.innerHTML = `
      <tr class="fw-bold border-top border-2 border-secondary" style="background-color: rgba(255, 255, 255, 0.05); font-size: 0.92rem;">
        <td class="text-warning fw-bold">
          <i class="bi bi-calculator me-1"></i> Média Total Consolidada
          ${closedKeys.length < keys.length ? `<div class="text-white-50 fw-normal" style="font-size:0.72rem;">(${closedKeys.length} dias fechados • dias em andamento excluídos da média)</div>` : ''}
        </td>
        <td class="text-center text-white">${avgCountStr}</td>
        <td class="text-white">${formatBrl(avgApostado)}</td>
        <td class="text-white">${formatBrl(avgRetorno)}</td>
        <td class="${avgLucroClass}">${formatBrl(avgLucro)}</td>
        <td class="${avgRoiClass}">${(avgRoi >= 0 ? '+' : '') + avgRoi.toFixed(1).replace('.', ',')}%</td>
      </tr>
    `;
  }
}

document.addEventListener('DOMContentLoaded', function() {
  setPerfDatePreset('all');
});
</script>
