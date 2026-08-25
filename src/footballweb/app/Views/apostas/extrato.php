<?php
$dataInicioVal = $data_inicio ?? '';
$dataFimVal    = $data_fim ?? '';
$tipoFiltroVal = $tipo_filtro ?? '';

$saldoAtual = (float)($extrato['saldo_atual'] ?? 0);
$totalCreditos = (float)($extrato['total_creditos_adicionados'] ?? 0);
$totalDebitos = (float)($extrato['total_debitado_apostas'] ?? 0);
$totalRetornos = (float)($extrato['total_retorno_apostas'] ?? 0);
$lucroLiquido = (float)($extrato['lucro_liquido_apostas'] ?? 0);
$transacoes = $extrato['transacoes'] ?? [];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
:root {
  --cc-bg-dark: #0f172a;
  --cc-card-bg: rgba(30, 41, 59, 0.7);
  --cc-border: rgba(255, 255, 255, 0.1);
  --cc-text-primary: #f8fafc;
  --cc-text-secondary: #94a3b8;
  --cc-green: #10b981;
  --cc-red: #ef4444;
  --cc-blue: #3b82f6;
  --cc-gold: #f59e0b;
  --cc-purple: #8b5cf6;
}

.extrato-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem 1rem;
  color: var(--cc-text-primary);
}

.extrato-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 2rem;
}

.extrato-title-box h1 {
  font-size: 1.875rem;
  font-weight: 700;
  margin: 0;
  background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.extrato-title-box p {
  color: var(--cc-text-secondary);
  margin: 0.25rem 0 0 0;
  font-size: 0.95rem;
}

.btn-add-credit {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  font-weight: 600;
  padding: 0.75rem 1.5rem;
  border-radius: 0.75rem;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
  transition: all 0.2s ease;
  cursor: pointer;
  text-decoration: none;
}

.btn-add-credit:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
  color: white;
}

/* Cards KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.kpi-card {
  background: var(--cc-card-bg);
  backdrop-filter: blur(12px);
  border: 1px solid var(--cc-border);
  border-radius: 1rem;
  padding: 1.25rem;
  position: relative;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
}

.kpi-card.saldo::before { background: var(--cc-green); }
.kpi-card.creditos::before { background: var(--cc-blue); }
.kpi-card.debitos::before { background: var(--cc-red); }
.kpi-card.retornos::before { background: var(--cc-gold); }
.kpi-card.lucro::before { background: var(--cc-purple); }

.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.kpi-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--cc-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.kpi-icon {
  width: 36px;
  height: 36px;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.kpi-card.saldo .kpi-icon { background: rgba(16, 185, 129, 0.15); color: var(--cc-green); }
.kpi-card.creditos .kpi-icon { background: rgba(59, 130, 246, 0.15); color: var(--cc-blue); }
.kpi-card.debitos .kpi-icon { background: rgba(239, 68, 68, 0.15); color: var(--cc-red); }
.kpi-card.retornos .kpi-icon { background: rgba(245, 158, 11, 0.15); color: var(--cc-gold); }
.kpi-card.lucro .kpi-icon { background: rgba(139, 92, 246, 0.15); color: var(--cc-purple); }

.kpi-value {
  font-size: 1.6rem;
  font-weight: 700;
  letter-spacing: -0.5px;
}

/* Filter Box */
.filter-card {
  background: var(--cc-card-bg);
  border: 1px solid var(--cc-border);
  border-radius: 1rem;
  padding: 1.25rem;
  margin-bottom: 2rem;
}

.filter-form {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  flex: 1;
  min-width: 180px;
}

.filter-group label {
  font-size: 0.825rem;
  font-weight: 600;
  color: var(--cc-text-secondary);
}

.filter-input {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--cc-border);
  border-radius: 0.5rem;
  padding: 0.6rem 0.85rem;
  color: white;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.2s;
}

.filter-input:focus {
  border-color: #60a5fa;
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-filter {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 0.6rem 1.25rem;
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-clear {
  background: rgba(255, 255, 255, 0.1);
  color: var(--cc-text-secondary);
  border: none;
  padding: 0.6rem 1.25rem;
  border-radius: 0.5rem;
  font-weight: 600;
  text-decoration: none;
}

/* Chart Box */
.chart-card {
  background: var(--cc-card-bg);
  border: 1px solid var(--cc-border);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.chart-card-header {
  margin-bottom: 1.25rem;
}

.chart-card-header h2 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  color: white;
}

.chart-container-box {
  position: relative;
  height: 380px;
  width: 100%;
}

/* Table Box */
.table-card {
  background: var(--cc-card-bg);
  border: 1px solid var(--cc-border);
  border-radius: 1rem;
  padding: 1.5rem;
  overflow: hidden;
}

.table-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.table-card-header h2 {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  color: white;
}

.extrato-table-wrapper {
  overflow-x: auto;
}

.extrato-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.extrato-table th {
  background: rgba(15, 23, 42, 0.8);
  padding: 0.9rem 1rem;
  font-size: 0.825rem;
  font-weight: 600;
  color: var(--cc-text-secondary);
  text-transform: uppercase;
  border-bottom: 1px solid var(--cc-border);
}

.extrato-table td {
  padding: 1rem;
  font-size: 0.9rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  vertical-align: middle;
}

.extrato-table tr:hover {
  background: rgba(255, 255, 255, 0.03);
}

/* Badges */
.badge-tipo {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.65rem;
  border-radius: 0.375rem;
  font-size: 0.775rem;
  font-weight: 600;
}

.badge-credito-adicionado {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  border: 1px solid rgba(59, 130, 246, 0.3);
}

.badge-debito-aposta {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.badge-credito-retorno {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-estorno {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.val-positivo {
  color: #34d399;
  font-weight: 700;
}

.val-negativo {
  color: #f87171;
  font-weight: 700;
}

/* Modal styling */
.cc-modal-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(5px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
}

.cc-modal-card {
  background: #1e293b;
  border: 1px solid var(--cc-border);
  border-radius: 1rem;
  width: 90%;
  max-width: 480px;
  padding: 1.75rem;
  box-shadow: 0 20px 40px rgba(0,0,0,0.5);
  color: white;
}

.cc-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
}

.cc-modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
}

.cc-modal-close {
  background: transparent;
  border: none;
  color: var(--cc-text-secondary);
  font-size: 1.5rem;
  cursor: pointer;
}
</style>

<div class="extrato-container">

  <!-- Header Section -->
  <div class="extrato-header">
    <div class="extrato-title-box">
      <h1><i class="fas fa-wallet"></i> Extrato da Conta Corrente</h1>
      <p>Acompanhe em tempo real seus créditos adicionados, débitos de apostas e a evolução financeira do seu saldo.</p>
    </div>
    <button type="button" class="btn-add-credit" onclick="openAddCreditModal()">
      <i class="fas fa-plus-circle"></i> Adicionar Crédito
    </button>
  </div>

  <!-- KPI Summary Cards Grid -->
  <div class="kpi-grid">
    <div class="kpi-card saldo">
      <div class="kpi-header">
        <span class="kpi-label">Saldo Atual</span>
        <div class="kpi-icon"><i class="fas fa-coins"></i></div>
      </div>
      <div class="kpi-value" style="color: <?= $saldoAtual >= 0 ? '#34d399' : '#f87171' ?>;">
        R$ <?= number_format($saldoAtual, 2, ',', '.') ?>
      </div>
    </div>

    <div class="kpi-card creditos">
      <div class="kpi-header">
        <span class="kpi-label">Créditos Adicionados</span>
        <div class="kpi-icon"><i class="fas fa-arrow-down-left"></i></div>
      </div>
      <div class="kpi-value" style="color: #60a5fa;">
        R$ <?= number_format($totalCreditos, 2, ',', '.') ?>
      </div>
    </div>

    <div class="kpi-card debitos">
      <div class="kpi-header">
        <span class="kpi-label">Total Debitado</span>
        <div class="kpi-icon"><i class="fas fa-arrow-up-right"></i></div>
      </div>
      <div class="kpi-value" style="color: #f87171;">
        R$ <?= number_format($totalDebitos, 2, ',', '.') ?>
      </div>
    </div>

    <div class="kpi-card retornos">
      <div class="kpi-header">
        <span class="kpi-label">Retorno de Apostas</span>
        <div class="kpi-icon"><i class="fas fa-trophy"></i></div>
      </div>
      <div class="kpi-value" style="color: #fbbf24;">
        R$ <?= number_format($totalRetornos, 2, ',', '.') ?>
      </div>
    </div>

    <div class="kpi-card lucro">
      <div class="kpi-header">
        <span class="kpi-label">Resultado Líquido</span>
        <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
      </div>
      <div class="kpi-value" style="color: <?= $lucroLiquido >= 0 ? '#a78bfa' : '#f87171' ?>;">
        <?= $lucroLiquido >= 0 ? '+' : '' ?>R$ <?= number_format($lucroLiquido, 2, ',', '.') ?>
      </div>
    </div>
  </div>

  <!-- Filter Card -->
  <div class="filter-card">
    <form method="GET" action="<?= site_url('/apostas/extrato') ?>" class="filter-form">
      <div class="filter-group">
        <label for="data_inicio">Data Início</label>
        <input type="date" id="data_inicio" name="data_inicio" value="<?= esc($dataInicioVal) ?>" class="filter-input">
      </div>
      <div class="filter-group">
        <label for="data_fim">Data Fim</label>
        <input type="date" id="data_fim" name="data_fim" value="<?= esc($dataFimVal) ?>" class="filter-input">
      </div>
      <div class="filter-group">
        <label for="tipo">Tipo de Movimentação</label>
        <select id="tipo" name="tipo" class="filter-input">
          <option value="">Todas as Movimentações</option>
          <option value="CREDITO_ADICIONADO" <?= $tipoFiltroVal === 'CREDITO_ADICIONADO' ? 'selected' : '' ?>>Crédito Adicionado (Aporte)</option>
          <option value="DEBITO_APOSTA" <?= $tipoFiltroVal === 'DEBITO_APOSTA' ? 'selected' : '' ?>>Débito de Aposta</option>
          <option value="CREDITO_RETORNO_APOSTA" <?= $tipoFiltroVal === 'CREDITO_RETORNO_APOSTA' ? 'selected' : '' ?>>Retorno de Aposta</option>
          <option value="ESTORNO_APOSTA" <?= $tipoFiltroVal === 'ESTORNO_APOSTA' ? 'selected' : '' ?>>Estorno de Aposta</option>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="<?= site_url('/apostas/extrato') ?>" class="btn-clear"><i class="fas fa-redo"></i> Limpar</a>
      </div>
    </form>
  </div>

  <!-- Financial Evolution Chart Section -->
  <div class="chart-card">
    <div class="chart-card-header">
      <h2><i class="fas fa-chart-area" style="color: #60a5fa;"></i> Evolução Financeira da Conta Corrente</h2>
    </div>
    <div class="chart-container-box">
      <canvas id="evolucaoFinanceiraChart"></canvas>
    </div>
  </div>

  <!-- Extrato Table Section -->
  <div class="table-card">
    <div class="table-card-header">
      <h2><i class="fas fa-list-alt" style="color: #10b981;"></i> Histórico de Transações</h2>
      <span style="color: var(--cc-text-secondary); font-size: 0.9rem;">
        Total de registros: <strong><?= count($transacoes) ?></strong>
      </span>
    </div>

    <div class="extrato-table-wrapper">
      <table class="extrato-table">
        <thead>
          <tr>
            <th># ID</th>
            <th>Data / Hora</th>
            <th>Tipo</th>
            <th>Descrição / Referência</th>
            <th>Valor</th>
            <th>Saldo Resultante</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transacoes)): ?>
            <tr>
              <td colspan="6" style="text-align: center; padding: 2rem; color: var(--cc-text-secondary);">
                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                Nenhuma movimentação registrada na conta corrente até o momento.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($transacoes as $t): ?>
              <?php
                $val = (float)$t->valor;
                $isPos = $val > 0;
                $tipoClass = '';
                $tipoLabel = '';
                $tipoIcon = '';

                switch ($t->tipo) {
                  case 'CREDITO_ADICIONADO':
                    $tipoClass = 'badge-credito-adicionado';
                    $tipoLabel = 'Crédito Adicionado';
                    $tipoIcon = 'fa-plus';
                    break;
                  case 'DEBITO_APOSTA':
                    $tipoClass = 'badge-debito-aposta';
                    $tipoLabel = 'Débito Aposta';
                    $tipoIcon = 'fa-minus';
                    break;
                  case 'CREDITO_RETORNO_APOSTA':
                    $tipoClass = 'badge-credito-retorno';
                    $tipoLabel = 'Retorno Aposta';
                    $tipoIcon = 'fa-trophy';
                    break;
                  case 'ESTORNO_APOSTA':
                    $tipoClass = 'badge-estorno';
                    $tipoLabel = 'Estorno';
                    $tipoIcon = 'fa-undo';
                    break;
                  default:
                    $tipoClass = 'badge-credito-adicionado';
                    $tipoLabel = $t->tipo;
                    $tipoIcon = 'fa-exchange-alt';
                    break;
                }
              ?>
              <tr>
                <td>#<?= esc($t->id) ?></td>
                <td><?= date('d/m/Y H:i:s', strtotime($t->criado_em)) ?></td>
                <td>
                  <span class="badge-tipo <?= $tipoClass ?>">
                    <i class="fas <?= $tipoIcon ?>"></i> <?= esc($tipoLabel) ?>
                  </span>
                </td>
                <td><?= esc($t->descricao) ?></td>
                <td class="<?= $isPos ? 'val-positivo' : 'val-negativo' ?>">
                  <?= $isPos ? '+' : '' ?>R$ <?= number_format($val, 2, ',', '.') ?>
                </td>
                <td style="font-weight: 600;">
                  R$ <?= number_format((float)$t->saldo_posterior, 2, ',', '.') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal Adicionar Crédito -->
<div id="addCreditModal" class="cc-modal-overlay">
  <div class="cc-modal-card">
    <div class="cc-modal-header">
      <h3><i class="fas fa-plus-circle" style="color: #10b981;"></i> Adicionar Crédito no Saldo</h3>
      <button class="cc-modal-close" onclick="closeAddCreditModal()">&times;</button>
    </div>
    <form id="formAddCredit" onsubmit="submitAddCredit(event)">
      <div class="filter-group" style="margin-bottom: 1rem;">
        <label for="valor_credito">Valor do Crédito (R$)</label>
        <input type="number" step="0.01" min="0.01" id="valor_credito" name="valor" class="filter-input" placeholder="Ex: 100.00" required>
      </div>
      <div class="filter-group" style="margin-bottom: 1.5rem;">
        <label for="descricao_credito">Descrição / Identificador (Opcional)</label>
        <input type="text" id="descricao_credito" name="descricao" class="filter-input" placeholder="Ex: Depósito inicial, Aporte semanal">
      </div>
      <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
        <button type="button" class="btn-clear" onclick="closeAddCreditModal()">Cancelar</button>
        <button type="submit" class="btn-add-credit"><i class="fas fa-check"></i> Confirmar Crédito</button>
      </div>
    </form>
  </div>
</div>

<script>
// Inicializar Gráfico de Evolução Financeira
const graficoRawData = <?= json_encode($grafico) ?>;

document.addEventListener('DOMContentLoaded', function() {
  renderEvolucaoChart(graficoRawData);
});

let evolucaoChartInstance = null;

function renderEvolucaoChart(data) {
  const ctx = document.getElementById('evolucaoFinanceiraChart').getContext('2d');
  
  if (evolucaoChartInstance) {
    evolucaoChartInstance.destroy();
  }

  const labels = data.labels || [];
  const saldoData = data.saldo_evolucao || [];
  const creditosData = data.creditos_adicionados_acum || [];
  const retornosData = data.retornos_apostas_acum || [];

  evolucaoChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Saldo da Conta Corrente (R$)',
          data: saldoData,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          fill: true,
          tension: 0.3,
          borderWidth: 3,
          pointRadius: 4,
          pointHoverRadius: 6
        },
        {
          label: 'Créditos Adicionados (Acumulado R$)',
          data: creditosData,
          borderColor: '#3b82f6',
          backgroundColor: 'transparent',
          borderDash: [5, 5],
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 3
        },
        {
          label: 'Retorno de Apostas Ganhas (Acumulado R$)',
          data: retornosData,
          borderColor: '#f59e0b',
          backgroundColor: 'transparent',
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 3
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          labels: {
            color: '#94a3b8',
            font: { family: "'Inter', sans-serif", size: 12 }
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
                label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
              }
              return label;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: { color: '#94a3b8', maxRotation: 45 }
        },
        y: {
          grid: { color: 'rgba(255, 255, 255, 0.05)' },
          ticks: {
            color: '#94a3b8',
            callback: function(value) {
              return 'R$ ' + value.toFixed(2).replace('.', ',');
            }
          }
        }
      }
    }
  });
}

function openAddCreditModal() {
  document.getElementById('addCreditModal').style.display = 'flex';
}

function closeAddCreditModal() {
  document.getElementById('addCreditModal').style.display = 'none';
}

function submitAddCredit(e) {
  e.preventDefault();
  const valor = document.getElementById('valor_credito').value;
  const descricao = document.getElementById('descricao_credito').value;

  const formData = new FormData();
  formData.append('valor', valor);
  formData.append('descricao', descricao);

  fetch('<?= site_url('/conta-corrente/adicionar-credito') ?>', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('✅ Crédito de R$ ' + parseFloat(valor).toFixed(2) + ' adicionado com sucesso!');
      window.location.reload();
    } else {
      alert('❌ Erro ao adicionar crédito: ' + (data.message || 'Tente novamente.'));
    }
  })
  .catch(err => {
    console.error(err);
    alert('Erro de conexão ao adicionar crédito.');
  });
}
</script>
