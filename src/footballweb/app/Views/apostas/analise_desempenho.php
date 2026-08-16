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
</style>

<div class="bet-container">
  <!-- Header -->
  <div class="bet-header">
    <div class="bet-title">
      <h1><i class="bi bi-graph-up-arrow"></i> Análise de Desempenho</h1>
      <div class="bet-subtitle">Acompanhe a evolução acumulada do seu capital investido e saldo líquido real de apostas</div>
    </div>
    <div>
      <a href="<?= base_url('apostas') ?>" class="btn btn-outline-light rounded-pill px-4 fw-semibold text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i> Voltar para Minhas Apostas
      </a>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <!-- Period Presets + Date Inputs -->
    <div class="d-flex align-items-center gap-2 flex-wrap" style="font-size: 0.88rem;">
      <span class="text-light fw-semibold d-flex align-items-center gap-1"><i class="bi bi-calendar-range text-info"></i> Período:</span>
      
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
        <span class="text-light fw-semibold d-flex align-items-center gap-1" style="font-size: 0.88rem;"><i class="bi bi-funnel-fill text-primary"></i> Status:</span>
        <select id="perfStatusSelect" class="form-select form-select-sm bg-dark text-primary border-secondary fw-semibold" style="width: auto; cursor: pointer; min-width: 175px;" onchange="updatePerformanceDashboard()" title="Filtro de Status das Apostas">
          <option value="concluidas" selected>✅ Concluídas (Encerradas)</option>
          <option value="all">♾️ Todas (Inc. Pendentes)</option>
          <option value="Pendente">⏳ Apenas Pendentes</option>
          <option value="Ganha">🟢 Ganhas / Meio Ganhas</option>
          <option value="Perdida">🔴 Perdidas / Meio Perdidas</option>
          <option value="Cashout">💰 Cashout</option>
          <option value="ANULADA">⚪ Anuladas</option>
        </select>
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
      <div class="kpi-label"><i class="bi bi-cash-coin text-info"></i> Total Apostado Bruto</div>
      <div class="kpi-value text-info" id="kpiTotalApostado">R$ 0,00</div>
    </div>
    
    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-arrow-return-right text-accent"></i> Retorno Bruto</div>
      <div class="kpi-value text-white" id="kpiTotalRetorno">R$ 0,00</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-piggy-bank-fill" style="color: #00e676;"></i> Lucro Líquido Real</div>
      <div class="kpi-value text-success" id="kpiLucroLiquido">R$ 0,00</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-graph-up text-warning"></i> Rendimento / ROI %</div>
      <div class="kpi-value text-warning" id="kpiRoi">+0,0%</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-check-circle-fill text-success"></i> Taxa de Acerto</div>
      <div class="kpi-value text-white" id="kpiWinRate">0,0%</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-label"><i class="bi bi-ticket-detailed text-secondary"></i> Total de Apostas</div>
      <div class="kpi-value text-white" id="kpiTotalApostas">0</div>
    </div>
  </div>

  <!-- Line Chart Section -->
  <div class="chart-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
        <i class="bi bi-activity text-success"></i> Curva de Evolução da Banca & Apostas
      </h5>
      <span class="badge bg-dark border border-secondary text-light-50 px-3 py-1.5" style="font-size: 0.8rem;">
        <i class="bi bi-info-circle me-1 text-info"></i> Exibição Acumulada no Tempo
      </span>
    </div>

    <div class="chart-container-box">
      <canvas id="performanceChart"></canvas>
    </div>
  </div>

  <!-- Mercado Profit Chart Section -->
  <div class="chart-card">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h5 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
        <i class="bi bi-bar-chart-line-fill text-accent"></i> Lucro Líquido por Mercado de Aposta
      </h5>
      <span class="badge bg-dark border border-secondary text-light-50 px-3 py-1.5" style="font-size: 0.8rem;">
        <i class="bi bi-funnel-fill me-1 text-warning"></i> Agrupado pelos Mesmos Filtros
      </span>
    </div>

    <div class="chart-container-box" id="mercadoChartBox" style="height: 380px;">
      <canvas id="mercadoProfitChart"></canvas>
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
            <th class="text-center">Qtd Apostas</th>
            <th>Apostado Bruto (R$)</th>
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
      </table>
    </div>
  </div>
</div>

<script>
const rawBets = <?= json_encode($apostas ?? []) ?>;

let perfChart = null;
let mercadoChart = null;

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
  } else if (status === 'ANULADA') {
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
  } else if (status === 'ANULADA') {
    return valor;
  }
  return 0;
}

function getBetDateBRT(bet) {
  if (bet && bet.data_brt_dia) {
    return bet.data_brt_dia;
  }
  const rawDateStr = (bet && (bet.data_hora_jogo || bet.criado_em)) || '';
  if (!rawDateStr) return '';

  let formattedStr = rawDateStr.trim();
  if (formattedStr.length === 19 && !formattedStr.includes('T') && !formattedStr.includes('Z')) {
    formattedStr = formattedStr.replace(' ', 'T') + 'Z';
  } else if (!formattedStr.endsWith('Z') && !formattedStr.includes('+')) {
    formattedStr += 'Z';
  }

  try {
    const d = new Date(formattedStr);
    if (isNaN(d.getTime())) return rawDateStr.substring(0, 10);
    const formatter = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Sao_Paulo' });
    return formatter.format(d);
  } catch (e) {
    return rawDateStr.substring(0, 10);
  }
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
      if (status !== 'ANULADA') return false;
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

  let totalApostado = 0;
  let totalApostadoLiquidado = 0;
  let totalRetorno = 0;
  let totalLucroLiquido = 0;
  let winCount = 0;
  let decidedCount = 0;
  let settledCount = 0;

  const buckets = {};
  const mercadoBuckets = {};

  filteredBets.forEach(bet => {
    const status = bet.status || '';
    const valor = parseFloat(bet.valor_aposta) || 0;
    const netProfit = computeNetProfit(bet);
    const grossReturn = computeGrossReturn(bet);

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

    let rawMercado = (bet.mercado || 'Outros').trim();
    if (!rawMercado) rawMercado = 'Outros';

    if (!mercadoBuckets[rawMercado]) {
      mercadoBuckets[rawMercado] = { apostado: 0, retorno: 0, lucro: 0, count: 0 };
    }
    mercadoBuckets[rawMercado].apostado += valor;
    mercadoBuckets[rawMercado].retorno += grossReturn;
    mercadoBuckets[rawMercado].lucro += netProfit;
    mercadoBuckets[rawMercado].count += 1;

    const rawDate = getBetDateBRT(bet);
    let key = rawDate || 'Sem Data';
    if (rawDate) {
      if (groupMode === 'semana') {
        key = getWeekKey(rawDate);
      } else if (groupMode === 'mes') {
        key = rawDate.substring(0, 7);
      }
    }

    if (!buckets[key]) {
      buckets[key] = { apostado: 0, retorno: 0, lucro: 0, count: 0 };
    }
    buckets[key].apostado += valor;
    buckets[key].retorno += grossReturn;
    buckets[key].lucro += netProfit;
    buckets[key].count += 1;
  });

  const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const formatPct = (v) => (v >= 0 ? '+' : '') + v.toFixed(1).replace('.', ',') + '%';

  const baseInvestida = (totalApostadoLiquidado > 0) ? totalApostadoLiquidado : totalApostado;
  const roi = baseInvestida > 0 ? (totalLucroLiquido / baseInvestida) * 100 : 0;
  const winRate = decidedCount > 0 ? (winCount / decidedCount) * 100 : 0;

  document.getElementById('kpiTotalApostado').textContent = formatBrl(totalApostado);
  document.getElementById('kpiTotalRetorno').textContent = formatBrl(totalRetorno);
  
  const kpiLucroEl = document.getElementById('kpiLucroLiquido');
  kpiLucroEl.textContent = formatBrl(totalLucroLiquido);
  kpiLucroEl.className = 'kpi-value ' + (totalLucroLiquido >= 0 ? 'text-success' : 'text-danger');

  const kpiRoiEl = document.getElementById('kpiRoi');
  kpiRoiEl.textContent = formatPct(roi);
  kpiRoiEl.className = 'kpi-value ' + (roi >= 0 ? 'text-success' : 'text-danger');

  document.getElementById('kpiWinRate').textContent = winRate.toFixed(1).replace('.', ',') + '%';
  document.getElementById('kpiTotalApostas').textContent = filteredBets.length;

  const bucketKeys = Object.keys(buckets).sort();
  const labels = [];
  const cumulativeApostadoData = [];
  const cumulativeLucroData = [];

  let runningApostado = 0;
  let runningLucro = 0;

  bucketKeys.forEach(k => {
    runningApostado += buckets[k].apostado;
    runningLucro += buckets[k].lucro;

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

  renderChart(labels, cumulativeApostadoData, cumulativeLucroData);
  renderMercadoChart(mercadoLabels, mercadoLucroData, mercadoBgColors, mercadoBorderColors, mercadoMetaDetails);
  renderTableBreakdown(bucketKeys, buckets, groupMode);
}

function renderChart(labels, apostadoData, lucroData) {
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
          label: 'Valor Apostado Bruto Acumulado (R$)',
          data: apostadoData,
          borderColor: '#00b0ff',
          backgroundColor: 'rgba(0, 176, 255, 0.12)',
          borderWidth: 3,
          pointRadius: 4,
          pointBackgroundColor: '#00b0ff',
          tension: 0.3,
          fill: true
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
              if (label) {
                label += ': ';
              }
              if (context.parsed.y !== null) {
                label += context.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
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
                `Total Apostado: ${formatBrl(meta.apostado)}`,
                `Total Retorno: ${formatBrl(meta.retorno)}`,
                `ROI: ${formatPct(meta.roi)}`,
                `Qtd Apostas: ${meta.count}`
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

function renderTableBreakdown(keys, buckets, groupMode) {
  const tbody = document.getElementById('tableBreakdownBody');
  if (!tbody) return;

  if (keys.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum dado encontrado para o período selecionado.</td></tr>';
    return;
  }

  const formatBrl = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  let html = '';
  keys.forEach(k => {
    const b = buckets[k];
    let label = k;
    if (groupMode === 'dia' && k.length === 10) {
      const parts = k.split('-');
      label = `${parts[2]}/${parts[1]}/${parts[0]}`;
    } else if (groupMode === 'mes' && k.length === 7) {
      const parts = k.split('-');
      label = `${parts[1]}/${parts[0]}`;
    }

    const roi = b.apostado > 0 ? (b.lucro / b.apostado) * 100 : 0;
    const lucroClass = b.lucro >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';
    const roiClass = roi >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold';

    html += `
      <tr>
        <td class="fw-semibold text-white">${label}</td>
        <td class="text-center">${b.count}</td>
        <td>${formatBrl(b.apostado)}</td>
        <td>${formatBrl(b.retorno)}</td>
        <td class="${lucroClass}">${formatBrl(b.lucro)}</td>
        <td class="${roiClass}">${(roi >= 0 ? '+' : '') + roi.toFixed(1).replace('.', ',')}%</td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
  setPerfDatePreset('all');
});
</script>
