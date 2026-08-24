<?php
/**
 * View: Relatório Rank Top 5 - Mercado + Palpite Vencedores
 */
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --bg-dark: #0d1117;
    --card-bg: #161b22;
    --card-border: #21262d;
    --accent-gold: #ffd600;
    --accent-silver: #c0c0c0;
    --accent-bronze: #cd7f32;
    --accent-green: #00e676;
    --accent-blue: #00b0ff;
    --text-main: #f0f6fc;
    --text-muted: #8b949e;
  }

  body {
    background-color: var(--bg-dark) !important;
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
  }

  .report-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px 80px 20px;
  }

  /* Header Card */
  .report-header {
    background: linear-gradient(135deg, #161b22 0%, #1f2937 100%);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    padding: 32px 40px;
    margin-bottom: 30px;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.45);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
  }

  .report-title h1 {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 2.3rem;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .report-title h1 i {
    color: var(--accent-gold);
    text-shadow: 0 0 20px rgba(255, 214, 0, 0.4);
  }

  .report-subtitle {
    color: #ffffff;
    margin-top: 8px;
    font-size: 1rem;
  }

  .header-actions {
    display: flex;
    gap: 12px;
  }

  .btn-report-action {
    background: #21262d;
    border: 1px solid #30363d;
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
  }

  .btn-report-action:hover {
    background: var(--accent-blue);
    color: #000000;
    font-weight: 700;
    border-color: var(--accent-blue);
  }

  /* Stat Summary Grid */
  .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 35px;
  }

  .summary-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    padding: 22px 26px;
    display: flex;
    align-items: center;
    gap: 18px;
  }

  .summary-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
  }

  .summary-icon.green { background: rgba(0, 230, 118, 0.15); color: var(--accent-green); }
  .summary-icon.gold { background: rgba(255, 214, 0, 0.15); color: var(--accent-gold); }
  .summary-icon.blue { background: rgba(0, 176, 255, 0.15); color: var(--accent-blue); }

  .summary-label {
    font-size: 0.85rem;
    color: #ffffff;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
  }

  .summary-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: #ffffff;
    margin-top: 2px;
  }

  /* Rank Section */
  .rank-section-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.4rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .rank-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    transition: transform 0.2s ease, border-color 0.2s ease;
  }

  .rank-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.2);
  }

  .rank-card.rank-1 {
    border: 1px solid rgba(255, 214, 0, 0.4);
    background: linear-gradient(135deg, #161b22 0%, rgba(255, 214, 0, 0.05) 100%);
  }

  .rank-card.rank-2 {
    border: 1px solid rgba(192, 192, 192, 0.3);
  }

  .rank-card.rank-3 {
    border: 1px solid rgba(205, 127, 50, 0.3);
  }

  .rank-badge {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Outfit', sans-serif;
    font-size: 1.3rem;
    font-weight: 800;
  }

  .rank-badge.pos-1 { background: linear-gradient(135deg, #ffd600 0%, #ffab00 100%); color: #000000; box-shadow: 0 4px 15px rgba(255, 214, 0, 0.3); }
  .rank-badge.pos-2 { background: linear-gradient(135deg, #e0e0e0 0%, #9e9e9e 100%); color: #000000; }
  .rank-badge.pos-3 { background: linear-gradient(135deg, #d7ccc8 0%, #a1887f 100%); color: #000000; }
  .rank-badge.pos-other { background: #21262d; color: var(--text-muted); border: 1px solid #30363d; }

  .combination-info {
    flex: 1;
    min-width: 250px;
  }

  .market-tag {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 700;
    color: var(--accent-blue);
    margin-bottom: 4px;
  }

  .pick-name {
    font-family: 'Outfit', sans-serif;
    font-size: 1.35rem;
    font-weight: 700;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .metrics-group {
    display: flex;
    gap: 28px;
    align-items: center;
    flex-wrap: wrap;
  }

  .metric-box {
    text-align: center;
  }

  .metric-title {
    font-size: 0.78rem;
    color: #ffffff;
    text-transform: uppercase;
    font-weight: 600;
  }

  .metric-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: #ffffff;
    margin-top: 2px;
  }

  .metric-value.profit { color: var(--accent-green); }

  /* Filter Card */
  .filter-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 25px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  }
  .filter-card-title {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 1.1rem;
    color: #ffffff;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
  }
  .filter-input {
    background-color: #0d1117 !important;
    border: 1px solid #30363d !important;
    color: #f0f6fc !important;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 0.9rem;
  }
  .filter-input:focus {
    border-color: var(--accent-blue) !important;
    box-shadow: 0 0 0 2px rgba(0, 176, 255, 0.25) !important;
  }
  .filter-btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 8px 18px;
    font-size: 0.9rem;
  }
  .filter-badge-period {
    background: rgba(0, 176, 255, 0.15);
    color: var(--accent-blue);
    border: 1px solid rgba(0, 176, 255, 0.3);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
  }
  .quick-btn {
    background: #21262d;
    border: 1px solid #30363d;
    color: #ffffff;
    font-size: 0.8rem;
    border-radius: 6px;
    padding: 4px 10px;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .quick-btn:hover {
    background: #30363d;
    color: #ffffff;
  }

  /* Performance & Projection Panel */
  .performance-panel {
    background: linear-gradient(145deg, #161b22 0%, #1c2128 100%);
    border: 1px solid #30363d;
    border-radius: 18px;
    padding: 24px 28px;
    margin-bottom: 35px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
  }
  .performance-panel-title {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 1.25rem;
    color: #ffffff;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
  }
  .metric-pill-box {
    background: rgba(13, 17, 23, 0.7);
    border: 1px solid #21262d;
    border-radius: 12px;
    padding: 16px 20px;
    height: 100%;
  }
  .metric-pill-label {
    font-size: 0.8rem;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
  }
  .metric-pill-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.4rem;
    font-weight: 800;
    margin-top: 4px;
  }
  .metric-pill-sub {
    font-size: 0.8rem;
    color: #cbd5e1;
    margin-top: 4px;
  }
  .projection-card {
    background: linear-gradient(135deg, rgba(0, 230, 118, 0.05) 0%, rgba(0, 176, 255, 0.05) 100%);
    border: 1px solid rgba(0, 230, 118, 0.2);
    border-radius: 14px;
    padding: 18px 20px;
    text-align: center;
    transition: transform 0.2s ease, border-color 0.2s ease;
  }
  .projection-card:hover {
    transform: translateY(-3px);
    border-color: var(--accent-green);
  }
  .projection-card.negative {
    background: linear-gradient(135deg, rgba(255, 23, 68, 0.05) 0%, rgba(255, 82, 82, 0.05) 100%);
    border-color: rgba(255, 23, 68, 0.2);
  }
  .projection-card.negative:hover {
    border-color: #ff1744;
  }
  .projection-volume {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #ffffff;
    letter-spacing: 0.5px;
  }
  .projection-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    margin-top: 6px;
  }

  .modal-dark .modal-content {
    background-color: var(--card-bg);
    border: 1px solid var(--card-border);
    color: var(--text-main);
    border-radius: 16px;
  }
  .modal-dark .modal-header {
    border-bottom: 1px solid var(--card-border);
  }
  .modal-dark .modal-footer {
    border-top: 1px solid var(--card-border);
  }
  .modal-dark .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
  }
  .modal-explanation-box {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
  }

  @media print {
    .header-actions, .filter-card, .performance-panel { display: none !important; }
    body { background-color: #ffffff !important; color: #000000 !important; }
    .report-header, .rank-card, .summary-card { background: #ffffff !important; border: 1px solid #ccc !important; color: #000 !important; }
    .report-title h1, .pick-name, .metric-value, .summary-value { color: #000000 !important; }
  }
</style>

<div class="report-container">

  <!-- Header -->
  <div class="report-header">
    <div class="report-title">
      <h1><i class="bi bi-trophy-fill"></i> <?= lang('App.top5_report') ?></h1>
      <div class="report-subtitle"><?= lang('App.top5_subtitle') ?></div>
    </div>

    <div class="header-actions">
      <button class="btn-report-action" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> <?= lang('App.print_export_pdf') ?>
      </button>
      <a href="<?= base_url('apostas') ?>" class="btn-report-action">
        <i class="bi bi-arrow-left"></i> <?= lang('App.back_to_my_bets') ?>
      </a>
    </div>
  </div>

  <!-- Filter Card -->
  <div class="filter-card">
    <div class="filter-card-title">
      <div>
        <i class="bi bi-calendar-range text-info me-2"></i> <?= lang('App.filter_by_period') ?>
      </div>
      <div>
        <?php if (!empty($dataInicio) || !empty($dataFim)): ?>
          <span class="filter-badge-period">
            <i class="bi bi-funnel-fill me-1"></i>
            <?= lang('App.period') ?>: <?= !empty($dataInicio) ? date('d/m/Y', strtotime($dataInicio)) : 'Início' ?> 
            <?= lang('App.to') ?> <?= !empty($dataFim) ? date('d/m/Y', strtotime($dataFim)) : 'Hoje' ?>
          </span>
        <?php else: ?>
          <span class="filter-badge-period text-muted" style="background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #8b949e;">
            <i class="bi bi-globe me-1"></i> <?= lang('App.all_history') ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <form method="GET" action="<?= base_url('apostas/relatorio-top5') ?>" id="filterForm">
      <div class="row g-3 align-items-end">
        <div class="col-md-3 col-sm-6">
          <label class="form-label text-white small fw-semibold mb-1"><?= lang('App.period_shortcut') ?></label>
          <select id="preset_top5" class="form-select filter-input bg-dark text-info border-secondary fw-semibold" onchange="applyTop5Preset(this.value)">
            <option value="custom">📅 <?= lang('App.custom') ?></option>
            <option value="today">⚡ <?= lang('App.today') ?></option>
            <option value="yesterday">⏪ <?= lang('App.yesterday') ?></option>
            <option value="7days">🗓️ <?= lang('App.last_7_days') ?></option>
            <option value="15days">🗓️ <?= lang('App.last_15_days') ?></option>
            <option value="1month">📅 <?= lang('App.last_month') ?></option>
            <option value="trimestre">📊 <?= lang('App.quarter') ?></option>
            <option value="semestre">📈 <?= lang('App.semester') ?></option>
            <option value="all">♾️ <?= lang('App.all_period') ?></option>
          </select>
        </div>

        <div class="col-md-3 col-sm-6">
          <label class="form-label text-white small fw-semibold mb-1"><?= lang('App.start_date') ?></label>
          <input type="date" name="data_inicio" id="data_inicio" class="form-control filter-input" value="<?= esc($dataInicio ?? '') ?>">
        </div>

        <div class="col-md-3 col-sm-6">
          <label class="form-label text-white small fw-semibold mb-1"><?= lang('App.end_date') ?></label>
          <input type="date" name="data_fim" id="data_fim" class="form-control filter-input" value="<?= esc($dataFim ?? '') ?>">
        </div>

        <div class="col-md-6 col-sm-12 d-flex align-items-end gap-2 flex-wrap">
          <button type="submit" class="btn btn-primary filter-btn">
            <i class="bi bi-search me-1"></i> <?= lang('App.filter_by_period') ?>
          </button>

          <?php if (!empty($dataInicio) || !empty($dataFim)): ?>
            <a href="<?= base_url('apostas/relatorio-top5') ?>" class="btn btn-outline-secondary filter-btn filter-btn-clear">
              <i class="bi bi-x-circle me-1"></i> <?= lang('App.clear') ?>
            </a>
          <?php endif; ?>

          <button type="button" class="btn btn-outline-info filter-btn" data-bs-toggle="modal" data-bs-target="#modalExplicacaoIndicadores">
            <i class="bi bi-info-circle me-1"></i> <?= lang('App.show_indicator_explanation') ?>
          </button>

          <div class="ms-auto d-flex gap-1 align-items-center flex-wrap">
            <span class="text-white small me-1"><?= lang('App.shortcuts') ?>:</span>
            <button type="button" class="quick-btn" onclick="setPeriodToday()"><?= lang('App.today') ?></button>
            <button type="button" class="quick-btn" onclick="setPeriodQuick(7)">7D</button>
            <button type="button" class="quick-btn" onclick="setPeriodQuick(30)">30D</button>
            <button type="button" class="quick-btn" onclick="setPeriodMonth()"><?= lang('App.this_month') ?></button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Summary Cards -->
  <div class="summary-grid">
    <div class="summary-card">
      <div class="summary-icon green">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div>
        <div class="summary-label"><?= lang('App.settled_bet_simulations') ?></div>
        <div class="summary-value"><?= $statSummary['total_ganhas'] ?? 0 ?>G / <?= $statSummary['total_perdidas'] ?? 0 ?>P (<?= $statSummary['total_encerradas'] ?? 0 ?>)</div>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-icon <?= ($statSummary['lucro_liquido'] ?? 0) >= 0 ? 'gold' : 'red' ?>" style="<?= ($statSummary['lucro_liquido'] ?? 0) < 0 ? 'background: rgba(255,23,68,0.15); color: #ff1744;' : '' ?>">
        <i class="bi bi-cash-coin"></i>
      </div>
      <div>
        <div class="summary-label"><?= lang('App.net_profit_measured') ?></div>
        <div class="summary-value" style="color: <?= ($statSummary['lucro_liquido'] ?? 0) >= 0 ? 'var(--accent-green)' : '#ff1744' ?>">
          <?= ($statSummary['lucro_liquido'] ?? 0) >= 0 ? '+' : '' ?>R$ <?= number_format($statSummary['lucro_liquido'] ?? 0, 2, ',', '.') ?>
        </div>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-icon blue">
        <i class="bi bi-graph-up"></i>
      </div>
      <div>
        <div class="summary-label"><?= lang('App.roi_yield_measured') ?></div>
        <div class="summary-value" style="color: <?= ($statSummary['roi_percentual'] ?? 0) >= 0 ? 'var(--accent-green)' : '#ff1744' ?>">
          <?= ($statSummary['roi_percentual'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($statSummary['roi_percentual'] ?? 0, 2, ',', '.') ?>%
        </div>
      </div>
    </div>
  </div>

  <!-- Painel de Performance & Projeção +EV de Longo Prazo -->
  <div class="performance-panel">
    <div class="performance-panel-title">
      <div>
        <i class="bi bi-cpu-fill text-warning me-2"></i> <?= lang('App.long_term_projection') ?>
      </div>
      <span class="badge bg-secondary bg-opacity-25 border border-secondary text-light px-3 py-2 fw-semibold" style="font-size: 0.8rem;">
        <i class="bi bi-shield-check text-success me-1"></i> <?= lang('App.based_on_selected_period') ?>
      </span>
    </div>

    <!-- Banner Especial: Range Ideal de Odds do Gatekeeper (+EV) -->
    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="p-3 rounded-3" style="background: linear-gradient(135deg, rgba(0,230,118,0.08) 0%, rgba(0,176,255,0.08) 100%); border: 1px solid rgba(0,230,118,0.25);">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background: rgba(0,230,118,0.15); border: 1px solid var(--accent-green);">
                <i class="bi bi-bullseye text-success fs-4"></i>
              </div>
              <div>
                <div class="fw-bold text-white fs-5" style="font-family: 'Outfit', sans-serif;">
                  Range Ideal de Odds Recomendado (Gatekeeper +EV)
                </div>
                <div class="text-white small" style="color: #ffffff !important;">
                  Entenda o significado dos limites operacionais calculados para as modalidades ativas (Cartões e Handicap Asiático)
                </div>
              </div>
            </div>
            <div>
              <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold">
                <i class="bi bi-check-circle-fill me-1"></i> Modelo Estatístico Poisson + Histórico Real
              </span>
            </div>
          </div>

          <div class="row g-3">
            <!-- Box 1: Range Operacional -->
            <div class="col-md-4">
              <div class="p-3 rounded-3 h-100" style="background: #161b22; border: 1px solid #30363d;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="text-white fw-bold" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="bi bi-shield-check text-success me-1"></i> Range Operacional (+EV)
                  </span>
                  <span class="badge bg-success-subtle text-success" style="font-size: 0.65rem;">Zona Verde</span>
                </div>
                <div class="fw-bold text-success fs-4 my-1">
                  <?= number_format($statSummary['gk_odd_minima'] ?? 1.25, 2) ?> a <?= number_format($statSummary['gk_teto_maximo'] ?? 2.04, 2) ?>
                </div>
                <div class="text-white" style="font-size: 0.78rem; line-height: 1.35; color: #ffffff !important;">
                  <strong class="text-white">O que significa:</strong> Intervalo seguro onde a odd da casa paga acima do risco real estimado pela distribuição de Poisson.
                </div>
              </div>
            </div>

            <!-- Box 2: Média Vencedora -->
            <div class="col-md-4">
              <div class="p-3 rounded-3 h-100" style="background: #161b22; border: 1px solid #30363d;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="text-white fw-bold" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="bi bi-graph-up-arrow text-warning me-1"></i> Média Vencedora Histórica
                  </span>
                  <span class="badge bg-warning-subtle text-warning" style="font-size: 0.65rem;">Ponto de Referência</span>
                </div>
                <div class="fw-bold text-warning fs-4 my-1">
                  <?= number_format($statSummary['gk_odd_media_vencedora'] ?? 1.69, 2) ?>
                </div>
                <div class="text-white" style="font-size: 0.78rem; line-height: 1.35; color: #ffffff !important;">
                  <strong class="text-white">O que significa:</strong> Odd média real de todas as simulações de apostas vencedoras (Green) registradas no histórico geral (Cartões e Handicap Asiático).
                </div>
              </div>
            </div>

            <!-- Box 3: Teto de Segurança -->
            <div class="col-md-4">
              <div class="p-3 rounded-3 h-100" style="background: #161b22; border: 1px solid #30363d;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="text-white fw-bold" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="bi bi-slash-circle text-info me-1"></i> Teto Máximo de Segurança
                  </span>
                  <span class="badge bg-info-subtle text-info" style="font-size: 0.65rem;">Limite Flexível</span>
                </div>
                <div class="fw-bold text-info fs-4 my-1">
                  <?= number_format($statSummary['gk_teto_maximo'] ?? 2.04, 2) ?>
                </div>
                <div class="text-white" style="font-size: 0.78rem; line-height: 1.35; color: #ffffff !important;">
                  <strong class="text-white">O que significa:</strong> Limite dinâmico máximo (Média + 0,35) para barrar odds infladas e armadilhas da banca.
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Linha 1: Métricas de Eficiência e Break-Even -->
    <div class="row g-3 mb-4">
      <div class="col-md-3 col-sm-6">
        <div class="metric-pill-box">
          <div class="metric-pill-label">Taxa de Acerto Real</div>
          <div class="metric-pill-value text-info">
            <?= number_format($statSummary['win_rate'] ?? 0, 1, ',', '.') ?>%
          </div>
          <div class="metric-pill-sub">
            <?= $statSummary['total_ganhas'] ?? 0 ?> acerto(s) em <?= $statSummary['total_decididas'] ?? ($statSummary['total_encerradas'] ?? 0) ?> jogos decididos
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6">
        <div class="metric-pill-box">
          <div class="metric-pill-label">Break-Even (Ponto Nulo)</div>
          <div class="metric-pill-value text-warning">
            <?= number_format($statSummary['break_even_rate'] ?? 0, 1, ',', '.') ?>%
          </div>
          <div class="metric-pill-sub">
            Mínimo exigido para Odd Média <?= number_format($statSummary['odd_media'] ?? 1.0, 2) ?>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6">
        <div class="metric-pill-box">
          <div class="metric-pill-label">Margem de Eficiência (Edge)</div>
          <div class="metric-pill-value" style="color: <?= ($statSummary['edge_percentual'] ?? 0) >= 0 ? 'var(--accent-green)' : '#ff1744' ?>">
            <?= ($statSummary['edge_percentual'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($statSummary['edge_percentual'] ?? 0, 1, ',', '.') ?>%
          </div>
          <div class="metric-pill-sub">
            <?= ($statSummary['edge_percentual'] ?? 0) >= 0 ? 'Vantagem sobre a banca' : 'Abaixo do break-even' ?>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6">
        <div class="metric-pill-box">
          <div class="metric-pill-label">Stake Média por Simulação de Aposta</div>
          <div class="metric-pill-value text-white">
            R$ <?= number_format($statSummary['stake_media'] ?? 0, 2, ',', '.') ?>
          </div>
          <div class="metric-pill-sub">
            Total investido: R$ <?= number_format($statSummary['total_investido'] ?? 0, 2, ',', '.') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Linha 2: Projeção Futura em Múltiplos Volumes -->
    <div class="pt-2">
      <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="fw-bold text-white small text-uppercase" style="letter-spacing: 0.5px;">
          <i class="bi bi-rocket-takeoff-fill text-primary me-2"></i> Lucro Acumulado Projetado (+EV Futuro)
        </div>
        <div class="text-muted small">
          Lucro esperado por simulação de aposta: <strong class="<?= ($statSummary['roi_percentual'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= ($statSummary['roi_percentual'] ?? 0) >= 0 ? '+' : '' ?>R$ <?= number_format(($statSummary['stake_media'] ?? 0) * (($statSummary['roi_percentual'] ?? 0) / 100), 2, ',', '.') ?>
          </strong>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="projection-card <?= ($statSummary['projecao_100'] ?? 0) < 0 ? 'negative' : '' ?>">
            <div class="projection-volume"><i class="bi bi-layers-fill me-1"></i> Próximas 100 Simulações de Apostas</div>
            <div class="projection-value" style="color: <?= ($statSummary['projecao_100'] ?? 0) >= 0 ? 'var(--accent-green)' : '#ff1744' ?>">
              <?= ($statSummary['projecao_100'] ?? 0) >= 0 ? '+' : '' ?>R$ <?= number_format($statSummary['projecao_100'] ?? 0, 2, ',', '.') ?>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="projection-card <?= ($statSummary['projecao_500'] ?? 0) < 0 ? 'negative' : '' ?>">
            <div class="projection-volume"><i class="bi bi-stack me-1"></i> Próximas 500 Simulações de Apostas</div>
            <div class="projection-value" style="color: <?= ($statSummary['projecao_500'] ?? 0) >= 0 ? 'var(--accent-green)' : '#ff1744' ?>">
              <?= ($statSummary['projecao_500'] ?? 0) >= 0 ? '+' : '' ?>R$ <?= number_format($statSummary['projecao_500'] ?? 0, 2, ',', '.') ?>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="projection-card <?= ($statSummary['projecao_1000'] ?? 0) < 0 ? 'negative' : '' ?>">
            <div class="projection-volume"><i class="bi bi-award-fill me-1"></i> Próximas 1.000 Simulações de Apostas</div>
            <div class="projection-value" style="color: <?= ($statSummary['projecao_1000'] ?? 0) >= 0 ? 'var(--accent-green)' : '#ff1744' ?>">
              <?= ($statSummary['projecao_1000'] ?? 0) >= 0 ? '+' : '' ?>R$ <?= number_format($statSummary['projecao_1000'] ?? 0, 2, ',', '.') ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- User Top 5 Ranking -->
  <div class="mb-5">
    <div class="rank-section-title">
      <i class="bi bi-person-badge text-warning"></i> Seu Ranking Top 5 (Pessoal)
    </div>

    <?php if (empty($top5Usuario)): ?>
      <div class="rank-card text-center py-4 text-muted w-100">
        <i class="bi bi-info-circle me-2"></i> Você ainda não possui simulações de apostas com status <strong>Ganha</strong> para compor seu ranking pessoal.
      </div>
    <?php else: ?>
      <?php foreach ($top5Usuario as $index => $item): ?>
        <?php 
          $pos = $index + 1;
          $rankClass = ($pos == 1) ? 'rank-1' : (($pos == 2) ? 'rank-2' : (($pos == 3) ? 'rank-3' : ''));
          $posClass  = ($pos <= 3) ? "pos-{$pos}" : 'pos-other';
        ?>
        <div class="rank-card <?= $rankClass ?>">
          <div class="d-flex align-items-center gap-3">
            <div class="rank-badge <?= $posClass ?>">
              <?php if ($pos == 1): ?>
                <i class="bi bi-crown-fill" title="Campeão #1"></i>
              <?php else: ?>
                #<?= $pos ?>
              <?php endif; ?>
            </div>

            <div class="combination-info">
              <div class="market-tag">
                <i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($item['mercado']) ?>
                <?php 
                  $oddItem = (float)($item['odd_media'] ?? 0);
                  $oddMin = (float)($statSummary['gk_odd_minima'] ?? 1.25);
                  $oddMax = (float)($statSummary['gk_teto_maximo'] ?? 2.04);
                  if ($oddItem >= $oddMin && $oddItem <= $oddMax):
                ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle ms-2" style="font-size: 0.72rem;">
                    <i class="bi bi-check-circle-fill me-1"></i> Range +EV
                  </span>
                <?php endif; ?>
              </div>
              <div class="pick-name">
                <?= htmlspecialchars($item['palpite']) ?>
              </div>
            </div>
          </div>

          <div class="metrics-group">
            <div class="metric-box">
              <div class="metric-title">Vitórias</div>
              <div class="metric-value"><?= $item['total_vitorias'] ?>x</div>
            </div>

            <div class="metric-box">
              <div class="metric-title">Odd Média</div>
              <div class="metric-value"><?= number_format($item['odd_media'], 2) ?></div>
            </div>

            <div class="metric-box">
              <div class="metric-title">Total Investido</div>
              <div class="metric-value">R$ <?= number_format($item['total_apostado'], 2, ',', '.') ?></div>
            </div>

            <div class="metric-box">
              <div class="metric-title">Lucro Líquido</div>
              <div class="metric-value profit">R$ <?= number_format($item['lucro_liquido'], 2, ',', '.') ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Global Platform Top 5 Ranking -->
  <div>
    <div class="rank-section-title">
      <i class="bi bi-globe2 text-info"></i> Ranking Top 5 Global da Plataforma
    </div>

    <?php if (empty($top5Geral)): ?>
      <div class="rank-card text-center py-4 text-muted w-100">
        <i class="bi bi-info-circle me-2"></i> Nenhuma simulação de aposta ganha registrada na plataforma até o momento.
      </div>
    <?php else: ?>
      <?php foreach ($top5Geral as $index => $item): ?>
        <?php 
          $pos = $index + 1;
          $rankClass = ($pos == 1) ? 'rank-1' : (($pos == 2) ? 'rank-2' : (($pos == 3) ? 'rank-3' : ''));
          $posClass  = ($pos <= 3) ? "pos-{$pos}" : 'pos-other';
        ?>
        <div class="rank-card <?= $rankClass ?>">
          <div class="d-flex align-items-center gap-3">
            <div class="rank-badge <?= $posClass ?>">
              <?php if ($pos == 1): ?>
                <i class="bi bi-crown-fill" title="Campeão #1 Global"></i>
              <?php else: ?>
                #<?= $pos ?>
              <?php endif; ?>
            </div>

            <div class="combination-info">
              <div class="market-tag">
                <i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($item['mercado']) ?>
                <?php 
                  $oddItem = (float)($item['odd_media'] ?? 0);
                  $oddMin = (float)($statSummary['gk_odd_minima'] ?? 1.25);
                  $oddMax = (float)($statSummary['gk_teto_maximo'] ?? 2.04);
                  if ($oddItem >= $oddMin && $oddItem <= $oddMax):
                ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle ms-2" style="font-size: 0.72rem;">
                    <i class="bi bi-check-circle-fill me-1"></i> Range +EV
                  </span>
                <?php endif; ?>
              </div>
              <div class="pick-name">
                <?= htmlspecialchars($item['palpite']) ?>
              </div>
            </div>
          </div>

          <div class="metrics-group">
            <div class="metric-box">
              <div class="metric-title">Total Vitórias</div>
              <div class="metric-value"><?= $item['total_vitorias'] ?>x</div>
            </div>

            <div class="metric-box">
              <div class="metric-title">Odd Média</div>
              <div class="metric-value"><?= number_format($item['odd_media'], 2) ?></div>
            </div>

            <div class="metric-box">
              <div class="metric-title">Retorno Total</div>
              <div class="metric-value profit">R$ <?= number_format($item['retorno_total'], 2, ',', '.') ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
function formatDateLocal(d) {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function setPeriodToday() {
  const now = new Date();
  const todayStr = formatDateLocal(now);

  document.getElementById('data_inicio').value = todayStr;
  document.getElementById('data_fim').value = todayStr;
  document.getElementById('filterForm').submit();
}

function setPeriodQuick(days) {
  const endDate = new Date();
  const startDate = new Date();
  startDate.setDate(endDate.getDate() - days);
  
  document.getElementById('data_inicio').value = formatDateLocal(startDate);
  document.getElementById('data_fim').value = formatDateLocal(endDate);
  document.getElementById('filterForm').submit();
}

function setPeriodMonth() {
  const now = new Date();
  const startDate = new Date(now.getFullYear(), now.getMonth(), 1);
  const endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  
  document.getElementById('data_inicio').value = formatDateLocal(startDate);
  document.getElementById('data_fim').value = formatDateLocal(endDate);
  document.getElementById('filterForm').submit();
}
</script>

<!-- Modal de Explicação de Indicadores -->
<div class="modal fade modal-dark" id="modalExplicacaoIndicadores" tabindex="-1" aria-labelledby="modalExplicacaoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" id="modalExplicacaoLabel" style="font-family: 'Outfit', sans-serif;">
          <i class="bi bi-book-half text-info me-2"></i> Guia de Indicadores de Eficiência e Projeção +EV
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        
        <!-- Bloco 1: ROI / Yield -->
        <div class="modal-explanation-box">
          <h6 class="text-warning fw-bold mb-2">
            <i class="bi bi-graph-up me-1"></i> 1. ROI / Yield Aferido
          </h6>
          <p class="small text-muted mb-2">
            Mede a rentabilidade real obtida sobre todo o capital investido nas simulações de apostas encerradas do período filtrado.
          </p>
          <div class="p-2 rounded bg-dark border border-secondary text-center font-monospace small mb-2 text-info">
            ROI (%) = (Lucro Líquido / Total Investido) × 100
          </div>
        </div>

        <!-- Bloco 2: Break-Even & Edge -->
        <div class="modal-explanation-box">
          <h6 class="text-warning fw-bold mb-2">
            <i class="bi bi-cpu-fill me-1"></i> 2. Break-Even (Ponto Nulo) e Margem de Edge
          </h6>
          <p class="small text-muted mb-2">
            O <strong>Break-Even Rate</strong> é a taxa mínima de acerto exigida para não ter prejuízo, calculada em função da Odd Média Ponderada.
          </p>
          <div class="p-2 rounded bg-dark border border-secondary text-center font-monospace small mb-2 text-warning">
            Break-Even (%) = (1 / Odd Média) × 100
          </div>
          <div class="p-2 rounded bg-dark border border-secondary text-center font-monospace small mb-2 text-success">
            Edge (%) = Taxa de Acerto Real (%) - Break-Even Rate (%)
          </div>
          <ul class="small text-muted mb-0 ps-3">
            <li><strong class="text-success">Edge Positivo:</strong> Taxa de acerto superior ao ponto nulo = Lucro Sustentável (+EV).</li>
            <li><strong class="text-danger">Edge Negativo:</strong> Taxa de acerto abaixo do ponto nulo = Prejuízo no longo prazo.</li>
          </ul>
        </div>

        <!-- Bloco 3: Conceito de 80% e Odd 1.26 -->
        <div class="modal-explanation-box">
          <h6 class="text-warning fw-bold mb-2">
            <i class="bi bi-shield-check me-1"></i> 3. Manutenção de ROI Positivo & Estudo de Caso (Odd 1.26)
          </h6>
          <p class="small text-muted mb-2">
            Para manter o ROI positivo, sua <strong>Taxa de Acerto Real deve ser estritamente superior à taxa de Break-Even</strong>.
          </p>
          
          <div class="alert alert-dark border-info small mb-0">
            <strong class="text-info"><i class="bi bi-lightbulb-fill me-1"></i> Exemplo Prático (Odd Média 1.26):</strong><br>
            • <strong>Ponto Nulo (79,4%):</strong> Break-Even = <code>(1 / 1.26) × 100 ≈ 79,4%</code>.<br>
            • <strong>Meta de 80%+ de Acertos:</strong> Mantendo 80% ou mais de acerto, você garante permanência na zona de <strong>ROI Positivo</strong>.<br>
            • <strong>Proporção de Risco (1 Red vs Green):</strong> Com stake de R$ 5,00, cada vitória rende +R$ 1,30 e cada derrota custa -R$ 5,00. Assim, <strong>1 derrota exige ~3,85 vitórias seguidas</strong> só para ser recuperada.
          </div>
        </div>

        <!-- Bloco 4: Projeções +EV -->
        <div class="modal-explanation-box mb-0">
          <h6 class="text-warning fw-bold mb-2">
            <i class="bi bi-rocket-takeoff-fill me-1"></i> 4. Projeções de Longo Prazo (+EV)
          </h6>
          <p class="small text-muted mb-2">
            Estima o acumulado financeiro futuro em 100, 500 e 1.000 simulações de apostas com base no Lucro Esperado por Simulação de Aposta (<em>Stake Média × ROI / 100</em>).
          </p>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
function formatDateYYYYMMDD(d) {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
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

function applyTop5Preset(presetKey) {
  const startEl = document.getElementById('data_inicio');
  const endEl = document.getElementById('data_fim');
  if (!startEl || !endEl) return;

  const now = new Date();
  const todayStr = formatDateYYYYMMDD(now);

  if (presetKey === 'today') {
    startEl.value = todayStr;
    endEl.value = todayStr;
  } else if (presetKey === 'yesterday') {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    startEl.value = formatDateYYYYMMDD(yesterday);
    endEl.value = formatDateYYYYMMDD(yesterday);
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
    startEl.value = formatDateYYYYMMDD(getPastDateByMonths(1));
    endEl.value = todayStr;
  } else if (presetKey === 'trimestre') {
    startEl.value = formatDateYYYYMMDD(getPastDateByMonths(3));
    endEl.value = todayStr;
  } else if (presetKey === 'semestre') {
    startEl.value = formatDateYYYYMMDD(getPastDateByMonths(6));
    endEl.value = todayStr;
  } else if (presetKey === 'all') {
    startEl.value = '';
    endEl.value = '';
  }
}
</script>
