<?php
/**
 * View: Relatório Analítico de Abstenções (NO_BET) - Gatekeeper
 * Plataforma FootballWeb
 */
?>

<!-- Chart.js 4 CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
  :root {
    --bg-page: #0b1120;
    --card-bg: #131d31;
    --card-border: #1e293b;
    --accent-red: #ef4444;
    --accent-green: #10b981;
    --accent-blue: #38bdf8;
    --accent-amber: #f59e0b;
    --accent-purple: #a855f7;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
  }

  body {
    background-color: var(--bg-page) !important;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
  }

  .abstencao-container {
    max-width: 1440px;
    margin: 25px auto;
    padding: 0 20px 80px 20px;
  }

  /* Header Card */
  .abstencao-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    border: 1px solid rgba(56, 189, 248, 0.2);
    border-radius: 20px;
    padding: 2rem 2.5rem;
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
  }

  .abstencao-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(239, 68, 68, 0.15) 0%, transparent 70%);
    pointer-events: none;
  }

  .abstencao-title {
    font-family: 'Outfit', sans-serif;
    font-size: 2.2rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 0.5rem;
  }

  .abstencao-subtitle {
    color: var(--text-secondary);
    font-size: 0.95rem;
    max-width: 800px;
    line-height: 1.5;
  }

  /* Filter Card */
  .filter-box {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  }

  .filter-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
  }

  .btn-filter-pill {
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 9999px;
    border: 1px solid var(--card-border);
    background: #0f172a;
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .btn-filter-pill:hover {
    border-color: var(--accent-blue);
    color: #ffffff;
    transform: translateY(-2px);
  }

  .btn-filter-pill.active {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    border-color: #38bdf8;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
  }

  .btn-filter-pill.active-amber {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    border-color: #f59e0b;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35);
  }

  .btn-filter-pill.active-emerald {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    border-color: #10b981;
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
  }

  /* Stat Cards */
  .kpi-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
    height: 100%;
  }

  .kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
  }

  .kpi-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
  }

  .kpi-card-blue::after { background: var(--accent-blue); }
  .kpi-card-red::after { background: var(--accent-red); }
  .kpi-card-green::after { background: var(--accent-green); }
  .kpi-card-amber::after { background: var(--accent-amber); }

  .kpi-label {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .kpi-value {
    font-family: 'Outfit', sans-serif;
    font-size: 2.2rem;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 0.25rem;
  }

  .kpi-sub {
    font-size: 0.85rem;
    color: var(--text-secondary);
  }

  /* Chart Cards */
  .chart-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 18px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  }

  .chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--card-border);
  }

  .chart-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
  }

  .chart-box {
    position: relative;
    width: 100%;
    min-height: 280px;
  }

  /* Tables */
  .table-custom {
    width: 100%;
    border-collapse: collapse;
    color: var(--text-primary);
  }

  .table-custom th {
    background-color: #0f172a;
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem 1.25rem;
    border-bottom: 2px solid var(--card-border);
  }

  .table-custom td {
    padding: 1rem 1.25rem;
    background-color: var(--card-bg);
    border-bottom: 1px solid #1e293b;
    vertical-align: middle;
    font-size: 0.88rem;
  }

  .table-custom tr:hover td {
    background-color: #1a243a;
  }

  .progress-bar-container {
    width: 100%;
    height: 6px;
    background-color: #1e293b;
    border-radius: 9999px;
    overflow: hidden;
    margin-top: 6px;
  }

  .progress-bar-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.6s ease;
  }

  .badge-tag {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .input-dark {
    background-color: #0f172a !important;
    border: 1px solid var(--card-border) !important;
    color: #f8fafc !important;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    font-size: 0.85rem;
  }

  .input-dark:focus {
    border-color: var(--accent-blue) !important;
    box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2) !important;
  }

  /* Search input */
  .search-wrapper {
    position: relative;
    max-width: 320px;
  }

  .search-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
  }

  .search-wrapper input {
    padding-left: 36px !important;
  }
</style>

<div class="abstencao-container">

  <!-- 1. Header do Relatório -->
  <div class="abstencao-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <div class="abstencao-title">
          <i class="bi bi-shield-slash-fill" style="color: #ef4444;"></i>
          <span>Auditoria de Abstenções (NO_BET)</span>
        </div>
        <div class="abstencao-subtitle">
          Painel analítico e tabular dos motivos de bloqueio e abstenção mandatória do Gatekeeper nos mercados de 
          <strong>Handicap Asiático</strong> e <strong>Total de Cartões Under</strong>. Avalia o rigor das Regras de Ouro arquiteturais em tempo real.
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger px-3 py-2 fs-6 rounded-pill">
          <i class="bi bi-shield-check me-1"></i> Gatekeeper Ativo
        </span>
      </div>
    </div>
  </div>

  <!-- 2. Barra de Filtros (Modalidade, Período e Liga) -->
  <div class="filter-box">
    <form method="GET" action="<?= base_url('apostas/relatorio-abstencoes') ?>" id="filtroForm" class="row g-3 align-items-end">
      
      <!-- Filtro Modalidade -->
      <div class="col-12 col-lg-5">
        <div class="filter-label"><i class="bi bi-diagram-3-fill text-info me-1"></i> Modalidade / Mercado</div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= base_url('apostas/relatorio-abstencoes?modalidade=todos&periodo=' . esc($periodo) . '&start_date=' . esc($startDate) . '&end_date=' . esc($endDate) . (!empty($ligaFilter) ? '&liga=' . urlencode($ligaFilter) : '')) ?>" 
             class="btn-filter-pill <?= ($modalidade === 'todos') ? 'active' : '' ?>">
            <i class="bi bi-collection-fill"></i> Todos (Consolidado)
          </a>
          <a href="<?= base_url('apostas/relatorio-abstencoes?modalidade=ah&periodo=' . esc($periodo) . '&start_date=' . esc($startDate) . '&end_date=' . esc($endDate) . (!empty($ligaFilter) ? '&liga=' . urlencode($ligaFilter) : '')) ?>" 
             class="btn-filter-pill <?= ($modalidade === 'ah') ? 'active-amber' : '' ?>">
            <i class="bi bi-arrows-expand"></i> Handicap Asiático
          </a>
          <a href="<?= base_url('apostas/relatorio-abstencoes?modalidade=cartao&periodo=' . esc($periodo) . '&start_date=' . esc($startDate) . '&end_date=' . esc($endDate) . (!empty($ligaFilter) ? '&liga=' . urlencode($ligaFilter) : '')) ?>" 
             class="btn-filter-pill <?= ($modalidade === 'cartao') ? 'active-emerald' : '' ?>">
            <i class="bi bi-card-heading"></i> Cartões Under
          </a>
        </div>
        <input type="hidden" name="modalidade" value="<?= esc($modalidade) ?>">
      </div>

      <!-- Filtro Período Rápido -->
      <div class="col-12 col-lg-4">
        <div class="filter-label"><i class="bi bi-calendar-event text-warning me-1"></i> Janela Temporal</div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= base_url('apostas/relatorio-abstencoes?modalidade=' . esc($modalidade) . '&periodo=7d' . (!empty($ligaFilter) ? '&liga=' . urlencode($ligaFilter) : '')) ?>" 
             class="btn-filter-pill <?= ($periodo === '7d') ? 'active' : '' ?>">
            7 Dias
          </a>
          <a href="<?= base_url('apostas/relatorio-abstencoes?modalidade=' . esc($modalidade) . '&periodo=14d' . (!empty($ligaFilter) ? '&liga=' . urlencode($ligaFilter) : '')) ?>" 
             class="btn-filter-pill <?= ($periodo === '14d') ? 'active' : '' ?>">
            14 Dias (Padrão)
          </a>
          <a href="<?= base_url('apostas/relatorio-abstencoes?modalidade=' . esc($modalidade) . '&periodo=30d' . (!empty($ligaFilter) ? '&liga=' . urlencode($ligaFilter) : '')) ?>" 
             class="btn-filter-pill <?= ($periodo === '30d') ? 'active' : '' ?>">
            30 Dias
          </a>
        </div>
        <input type="hidden" name="periodo" id="periodoInput" value="<?= esc($periodo) ?>">
      </div>

      <!-- Filtro Liga -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="filter-label"><i class="bi bi-trophy-fill text-warning me-1"></i> Liga / Competição</div>
        <select name="liga" class="form-select input-dark" onchange="document.getElementById('filtroForm').submit();">
          <option value="">Todas as Ligas</option>
          <?php foreach ($ligas as $l): ?>
            <option value="<?= esc($l->league_name) ?>" <?= ($ligaFilter === $l->league_name) ? 'selected' : '' ?>>
              <?= esc($l->league_name) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Datas Personalizadas (seletor expandível) -->
      <div class="col-12 pt-2 border-top border-secondary border-opacity-25 d-flex flex-wrap align-items-center gap-3">
        <span class="text-secondary small fw-semibold">Personalizar Intervalo:</span>
        <div class="d-flex align-items-center gap-2">
          <label class="text-muted small">De:</label>
          <input type="date" name="start_date" value="<?= esc($startDate) ?>" class="form-control input-dark" style="width: 150px;">
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="text-muted small">Até:</label>
          <input type="date" name="end_date" value="<?= esc($endDate) ?>" class="form-control input-dark" style="width: 150px;">
        </div>
        <button type="submit" onclick="document.getElementById('periodoInput').value='custom';" class="btn btn-sm btn-primary px-3 rounded-pill">
          <i class="bi bi-funnel-fill me-1"></i> Filtrar Datas
        </button>
        <?php if ($periodo !== '14d' || $modalidade !== 'todos' || !empty($ligaFilter)): ?>
          <a href="<?= base_url('apostas/relatorio-abstencoes') ?>" class="btn btn-sm btn-outline-secondary px-3 rounded-pill">
            <i class="bi bi-x-circle me-1"></i> Limpar Filtros
          </a>
        <?php endif; ?>
      </div>

    </form>
  </div>

  <!-- 3. Cards de Resumo Executivo / KPIs -->
  <div class="row g-3 mb-4">
    
    <!-- KPI 1: Partidas Analisadas -->
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="kpi-card kpi-card-blue">
        <div class="kpi-label">
          <span>Partidas Monitoradas</span>
          <i class="bi bi-binoculars-fill text-info fs-5"></i>
        </div>
        <div class="kpi-value text-info"><?= number_format($totalJogosAnalisados, 0, ',', '.') ?></div>
        <div class="kpi-sub">
          <?= date('d/m/Y', strtotime($startDate)) ?> até <?= date('d/m/Y', strtotime($endDate)) ?>
        </div>
      </div>
    </div>

    <!-- KPI 2: Abstenções NO_BET -->
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="kpi-card kpi-card-red">
        <div class="kpi-label">
          <span>Abstenções (NO_BET)</span>
          <i class="bi bi-shield-x text-danger fs-5"></i>
        </div>
        <div class="kpi-value text-danger">
          <?= number_format($totalAbstencoes, 0, ',', '.') ?>
          <span class="fs-6 text-danger fw-bold ms-1">(<?= $taxaAbstencaoGlobal ?>%)</span>
        </div>
        <div class="kpi-sub">
          Cortes mandatórios de proteção de banca
        </div>
      </div>
    </div>

    <!-- KPI 3: Apostas Aprovadas (+EV) -->
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="kpi-card kpi-card-green">
        <div class="kpi-label">
          <span>Aprovadas (+EV)</span>
          <i class="bi bi-check-circle-fill text-success fs-5"></i>
        </div>
        <div class="kpi-value text-success">
          <?= number_format($totalAprovados, 0, ',', '.') ?>
          <span class="fs-6 text-success fw-bold ms-1">(<?= $taxaAprovacaoGlobal ?>%)</span>
        </div>
        <div class="kpi-sub">
          Entradas líquidas com valor esperado positivo
        </div>
      </div>
    </div>

    <!-- KPI 4: Principal Gargalo -->
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="kpi-card kpi-card-amber">
        <div class="kpi-label">
          <span>Principal Gargalo</span>
          <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
        </div>
        <div class="kpi-value text-warning fs-4" style="line-height: 1.2;">
          <?= esc($principalGargalo->nome ?? 'Nenhum') ?>
        </div>
        <div class="kpi-sub">
          <?php if ($principalGargalo): ?>
            <strong class="text-light"><?= $principalGargalo->count ?> jogos</strong> (<?= $principalGargalo->pct_nb ?>% das abstenções)
          <?php else: ?>
            Sem ocorrências no filtro
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Sub-Cards de Breakdown por Modalidade (quando 'todos' está ativo) -->
  <?php if ($modalidade === 'todos'): ?>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
      <div class="p-3 rounded-3" style="background: #1e293b; border: 1px solid #334155;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-bold text-warning"><i class="bi bi-arrows-expand me-1"></i> Handicap Asiático (Subtotal)</span>
          <span class="badge bg-warning text-dark"><?= $taxaAhAbstencao ?>% de Abstenção</span>
        </div>
        <div class="d-flex justify-content-between text-secondary small">
          <span>Monitorados: <strong class="text-light"><?= $totalAhAvaliados ?></strong></span>
          <span>Abstenções (NO_BET): <strong class="text-danger"><?= $totalAhAbstencoes ?></strong></span>
          <span>Aprovados: <strong class="text-success"><?= $totalAhAprovados ?></strong></span>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="p-3 rounded-3" style="background: #1e293b; border: 1px solid #334155;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-bold text-success"><i class="bi bi-card-heading me-1"></i> Total de Cartões Under (Subtotal)</span>
          <span class="badge bg-success"><?= $taxaCardsAbstencao ?>% de Abstenção</span>
        </div>
        <div class="d-flex justify-content-between text-secondary small">
          <span>Avaliados: <strong class="text-light"><?= $totalCardsAvaliados ?></strong></span>
          <span>Abstenções (NO_BET): <strong class="text-danger"><?= $totalCardsAbstencoes ?></strong></span>
          <span>Aprovados: <strong class="text-success"><?= $totalCardsAprovados ?></strong></span>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 4. Seção de Gráficos (Donut + Ranking Horizontal + Evolução Diária) -->
  <div class="row g-3 mb-4">
    
    <!-- Gráfico 1: Donut (Distribuição Percentual) -->
    <div class="col-12 col-lg-5">
      <div class="chart-card h-100">
        <div class="chart-header">
          <h3 class="chart-title">
            <i class="bi bi-pie-chart-fill text-info"></i>
            Distribuição Percentual de Motivos
          </h3>
          <span class="text-secondary small">Top Gargalos</span>
        </div>
        <div class="chart-box d-flex justify-content-center align-items-center" style="height: 320px;">
          <canvas id="chartDonut"></canvas>
        </div>
      </div>
    </div>

    <!-- Gráfico 2: Ranking Horizontal (Volume Absoluto de Jogos) -->
    <div class="col-12 col-lg-7">
      <div class="chart-card h-100">
        <div class="chart-header">
          <h3 class="chart-title">
            <i class="bi bi-bar-chart-steps text-danger"></i>
            Ranking de Jogos Vetados por Motivo
          </h3>
          <span class="text-secondary small">Volume de Partidas</span>
        </div>
        <div class="chart-box" style="height: 320px;">
          <canvas id="chartBarHorizontal"></canvas>
        </div>
      </div>
    </div>

    <!-- Gráfico 3: Timeline Diária (NO_BET vs Aprovados) -->
    <div class="col-12">
      <div class="chart-card">
        <div class="chart-header">
          <h3 class="chart-title">
            <i class="bi bi-graph-up text-success"></i>
            Tendência Temporal Diária (Abstenções vs Aprovadas)
          </h3>
          <span class="text-secondary small">Histórico por Data de Jogo</span>
        </div>
        <div class="chart-box" style="height: 260px;">
          <canvas id="chartTimeline"></canvas>
        </div>
      </div>
    </div>

  </div>

  <!-- 5. Tabela 1: Resumo Executivo Agregado de Motivos -->
  <div class="chart-card mb-4 p-0 overflow-hidden">
    <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h3 class="chart-title fs-5 mb-1">
          <i class="bi bi-table text-warning"></i>
          Resumo Tabular Consolidado dos Motivos de Abstenção
        </h3>
        <span class="text-secondary small">Agrupamento estatístico alinhado às Regras de Ouro e Travas do Gatekeeper</span>
      </div>
      <span class="badge bg-secondary px-3 py-2">
        <?= count($motivosTabela) ?> Categorias Identificadas
      </span>
    </div>

    <div class="table-responsive">
      <table class="table-custom">
        <thead>
          <tr>
            <th style="width: 30%;">Motivo / Categoria da Abstenção</th>
            <th style="width: 15%;">Modalidade</th>
            <th class="text-center" style="width: 10%;">Qtd Jogos</th>
            <th class="text-center" style="width: 12%;">% sobre NO_BET</th>
            <th class="text-center" style="width: 12%;">% sobre Total</th>
            <th style="width: 21%;">Regra de Ouro / Fundamento da IA</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($motivosTabela)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">
                <i class="bi bi-info-circle me-1"></i> Nenhuma abstenção registrada para os filtros selecionados.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($motivosTabela as $m): ?>
              <tr>
                <td>
                  <div class="fw-bold d-flex align-items-center gap-2">
                    <span style="display:inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: <?= esc($m->color) ?>;"></span>
                    <span><?= esc($m->nome) ?></span>
                  </div>
                  <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: <?= $m->pct_nb ?>%; background-color: <?= esc($m->color) ?>;"></div>
                  </div>
                </td>
                <td>
                  <?php if ($m->modalidade_key === 'ah'): ?>
                    <span class="badge-tag bg-warning text-dark"><i class="bi bi-arrows-expand"></i> AH</span>
                  <?php else: ?>
                    <span class="badge-tag bg-success text-white"><i class="bi bi-card-heading"></i> Cartões</span>
                  <?php endif; ?>
                </td>
                <td class="text-center fw-bold fs-6 text-light">
                  <?= number_format($m->count, 0, ',', '.') ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-danger bg-opacity-75 text-white fw-bold px-2 py-1 fs-6">
                    <?= number_format($m->pct_nb, 2, ',', '.') ?>%
                  </span>
                </td>
                <td class="text-center text-secondary small">
                  <?= number_format($m->pct_total, 2, ',', '.') ?>%
                </td>
                <td class="small text-secondary">
                  <?= esc($m->regra_ouro) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 6. Tabela 2: Datagrid Analítico de Partidas Individuais Bloqueadas -->
  <div class="chart-card p-0 overflow-hidden">
    <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h3 class="chart-title fs-5 mb-1">
          <i class="bi bi-list-check text-info"></i>
          Partidas Auditadas com Veto pelo Gatekeeper (NO_BET)
        </h3>
        <span class="text-secondary small">Exibindo até 600 confrontos mais recentes do período selecionado</span>
      </div>
      
      <!-- Busca rápida em tempo real -->
      <div class="search-wrapper">
        <i class="bi bi-search"></i>
        <input type="text" id="tabelaSearch" class="form-control input-dark" placeholder="Buscar por time, liga ou motivo...">
      </div>
    </div>

    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
      <table class="table-custom" id="datagridPartidas">
        <thead style="position: sticky; top: 0; z-index: 10;">
          <tr>
            <th style="width: 12%;">Data / Hora</th>
            <th style="width: 16%;">Liga / Competição</th>
            <th style="width: 26%;">Confronto</th>
            <th style="width: 12%;">Modalidade</th>
            <th style="width: 18%;">Motivo Classificado</th>
            <th style="width: 16%;" class="text-center">Status / Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($partidasLista)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">
                Nenhuma partida com abstenção encontrada no período.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($partidasLista as $idx => $p): ?>
              <tr class="partida-row">
                <td class="text-secondary small text-nowrap">
                  <i class="bi bi-clock me-1"></i>
                  <?= date('d/m/Y H:i', strtotime($p->data)) ?>
                </td>
                <td class="fw-semibold text-light small">
                  <?= esc($p->liga) ?>
                </td>
                <td class="fw-bold text-light">
                  <span><?= esc($p->home_team) ?></span>
                  <span class="text-muted mx-1">x</span>
                  <span><?= esc($p->away_team) ?></span>
                </td>
                <td>
                  <?php if ($p->modalidade_key === 'ah'): ?>
                    <span class="badge-tag bg-warning text-dark"><i class="bi bi-arrows-expand"></i> AH</span>
                  <?php else: ?>
                    <span class="badge-tag bg-success text-white"><i class="bi bi-card-heading"></i> Cartões</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-tag text-white" style="background-color: <?= esc($p->badge_color) ?>;">
                    <i class="bi bi-shield-x"></i> <?= esc($p->motivo_nome) ?>
                  </span>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3" 
                          onclick="abrirModalMotivo(<?= esc($p->fixture_id) ?>, '<?= esc(addslashes($p->times)) ?>', '<?= esc(addslashes($p->motivo_nome)) ?>', '<?= esc(addslashes(preg_replace('/\s+/', ' ', $p->motivo_texto))) ?>')">
                    <i class="bi bi-eye-fill me-1"></i> Ver Justificativa
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal Detalhes do Motivo -->
<div class="modal fade" id="modalMotivoDetalhado" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background-color: #0f172a; border: 1px solid #334155; color: #f8fafc;">
      <div class="modal-header border-secondary border-opacity-25">
        <h5 class="modal-title font-monospace" id="modalFixtureTitle">
          <i class="bi bi-shield-lock-fill text-danger me-2"></i> Justificativa do Gatekeeper (NO_BET)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <span class="text-muted small text-uppercase">Partida / Confronto:</span>
          <h4 class="text-light fw-bold mb-0" id="modalPartidaNome"></h4>
        </div>
        <div class="mb-3">
          <span class="text-muted small text-uppercase">Classificação do Veto:</span>
          <div class="mt-1"><span class="badge bg-danger fs-6" id="modalMotivoTag"></span></div>
        </div>
        <div>
          <span class="text-muted small text-uppercase">Texto Técnico Completo da IA / Logs:</span>
          <pre id="modalMotivoTexto" class="mt-1 p-3 rounded-3" style="background-color: #020617; border: 1px solid #1e293b; color: #38bdf8; font-family: monospace; font-size: 0.85rem; white-space: pre-wrap; word-break: break-word; max-height: 250px; overflow-y: auto;"></pre>
        </div>
      </div>
      <div class="modal-footer border-secondary border-opacity-25">
        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts de Inicialização dos Gráficos e Busca -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Dados dos Gráficos vindos do Controller
  const chartLabels = <?= $chartLabelsJson ?>;
  const chartCounts = <?= $chartCountsJson ?>;
  const chartColors = <?= $chartColorsJson ?>;
  const chartPcts   = <?= $chartPctsJson ?>;

  const timelineDates = <?= $timelineDatesJson ?>;
  const timelineNb    = <?= $timelineNbJson ?>;
  const timelineAp    = <?= $timelineApJson ?>;

  // 2. Gráfico Donut (Distribuição Percentual)
  const ctxDonut = document.getElementById('chartDonut');
  if (ctxDonut && chartLabels.length > 0) {
    new Chart(ctxDonut, {
      type: 'doughnut',
      data: {
        labels: chartLabels,
        datasets: [{
          data: chartCounts,
          backgroundColor: chartColors,
          borderColor: '#131d31',
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              color: '#cbd5e1',
              font: { size: 11, family: 'Inter' },
              padding: 12
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const val = context.raw || 0;
                const pct = chartPcts[context.dataIndex] || 0;
                return ` ${val} jogos (${pct}%)`;
              }
            }
          }
        },
        cutout: '62%'
      }
    });
  }

  // 3. Gráfico de Barras Horizontais (Ranking de Volume)
  const ctxBar = document.getElementById('chartBarHorizontal');
  if (ctxBar && chartLabels.length > 0) {
    new Chart(ctxBar, {
      type: 'bar',
      data: {
        labels: chartLabels,
        datasets: [{
          label: 'Jogos Vetados',
          data: chartCounts,
          backgroundColor: chartColors,
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context) {
                const val = context.raw || 0;
                const pct = chartPcts[context.dataIndex] || 0;
                return ` ${val} partidas (${pct}% do total de abstenções)`;
              }
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(51, 65, 85, 0.4)' },
            ticks: { color: '#94a3b8', font: { size: 10 } }
          },
          y: {
            grid: { display: false },
            ticks: { color: '#e2e8f0', font: { size: 11, weight: 600 } }
          }
        }
      }
    });
  }

  // 4. Gráfico de Linha / Evolução Temporal
  const ctxTimeline = document.getElementById('chartTimeline');
  if (ctxTimeline && timelineDates.length > 0) {
    new Chart(ctxTimeline, {
      type: 'bar',
      data: {
        labels: timelineDates,
        datasets: [
          {
            label: 'Abstenções (NO_BET)',
            data: timelineNb,
            backgroundColor: '#ef4444',
            borderRadius: 4
          },
          {
            label: 'Aprovadas (+EV)',
            data: timelineAp,
            backgroundColor: '#10b981',
            borderRadius: 4
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            labels: { color: '#cbd5e1', font: { size: 12 } }
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(51, 65, 85, 0.3)' },
            ticks: { color: '#94a3b8' }
          },
          y: {
            grid: { color: 'rgba(51, 65, 85, 0.3)' },
            ticks: { color: '#94a3b8' }
          }
        }
      }
    });
  }

  // 5. Busca rápida instantânea na Tabela Analítica
  const searchInput = document.getElementById('tabelaSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#datagridPartidas tbody tr.partida-row');
      rows.forEach(function(r) {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
      });
    });
  }

});

// Função para exibir modal com texto completo do motivo
function abrirModalMotivo(fixtureId, times, motivoNome, motivoTexto) {
  document.getElementById('modalFixtureTitle').innerHTML = `<i class="bi bi-shield-lock-fill text-danger me-2"></i> Justificativa Gatekeeper (Jogo #${fixtureId})`;
  document.getElementById('modalPartidaNome').textContent = times;
  document.getElementById('modalMotivoTag').textContent = motivoNome;
  document.getElementById('modalMotivoTexto').textContent = motivoTexto || 'Justificativa detalhada não informada no registro.';

  const modalEl = document.getElementById('modalMotivoDetalhado');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
</script>
