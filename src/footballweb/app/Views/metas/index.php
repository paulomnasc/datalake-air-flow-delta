<?php
$dataRefStr = $dataRef ?? date('Y-m-d');
$meta       = $metaConfig ?? (object)[];
$prog       = $progressoHoje ?? [];
$ciclo      = $cicloAtivo ?? [];
$historico  = $historicoDias ?? [];
$historicoC = $historicoCiclos ?? [];

$stakePadrao = (float)($meta->stake_padrao ?? 10.00);
$alvoApostas = (int)($meta->total_apostas_alvo ?? 10);
$oddAlvo     = (float)($meta->odd_media_alvo ?? 1.75);
$targetG     = (int)($meta->target_greens ?? 5);
$targetP     = (int)($meta->target_pushes ?? 2);
$maxR        = (int)($meta->max_reds ?? 3);
$lucroAlvo   = (float)($meta->lucro_alvo ?? 7.50);
$stopLoss    = (float)($meta->stop_loss_diario ?? -30.00);

$totalCad     = (int)($prog['total_apostas_cadastradas'] ?? 0);
$totalLiq     = (int)($prog['total_apostas_liquidadas'] ?? 0);
$greens       = (float)($prog['greens_count'] ?? 0.0);
$pushes       = (int)($prog['pushes_count'] ?? 0);
$reds         = (float)($prog['reds_count'] ?? 0.0);
$pendentes    = (int)($prog['pendentes_count'] ?? 0);
$oddReal      = (float)($prog['odd_media_real'] ?? 0.00);
$lucroReal    = (float)($prog['lucro_liquido'] ?? 0.00);
$roiReal      = (float)($prog['roi_pct'] ?? 0.00);
$statusDia    = $prog['status_dia'] ?? 'EM_ANDAMENTO';
$progApostas  = (float)($prog['progresso_apostas_pct'] ?? 0.00);
$progLucro    = (float)($prog['progresso_lucro_pct'] ?? 0.00);

$fmtScore = function($v) {
    $num = (float)$v;
    return (fmod($num, 1.0) == 0.0) ? (string)(int)$num : number_format($num, 1, ',', '.');
};
?>

<style>
:root {
  --meta-bg-dark: #0f172a;
  --meta-card-bg: rgba(30, 41, 59, 0.7);
  --meta-border: rgba(255, 255, 255, 0.15);
  --meta-text-primary: #ffffff;
  --meta-text-secondary: #ffffff;
  --meta-green: #10b981;
  --meta-red: #ef4444;
  --meta-blue: #3b82f6;
  --meta-gold: #f59e0b;
  --meta-purple: #8b5cf6;
}

.meta-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem 1rem;
  color: #ffffff;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.meta-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.meta-title-box h1 {
  font-size: 1.875rem;
  font-weight: 800;
  margin: 0;
  background: linear-gradient(135deg, #10b981 0%, #3b82f6 50%, #8b5cf6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.meta-title-box p {
  color: #ffffff;
  margin: 0.35rem 0 0 0;
  font-size: 0.95rem;
}

.meta-controls {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.date-selector-wrapper {
  display: flex;
  align-items: center;
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid var(--meta-border);
  border-radius: 0.75rem;
  padding: 0.25rem 0.5rem;
}

.date-input {
  background: transparent;
  border: none;
  color: white;
  padding: 0.4rem 0.6rem;
  font-size: 0.9rem;
  outline: none;
}

.btn-nav-date {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid var(--meta-border);
  color: #ffffff;
  border-radius: 0.5rem;
  padding: 0.4rem 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
}
.btn-nav-date:hover {
  background: rgba(255, 255, 255, 0.2);
  color: white;
}

.btn-config {
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  color: white;
  font-weight: 600;
  padding: 0.55rem 1.25rem;
  border-radius: 0.75rem;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}
.btn-config:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
}

.btn-refresh-cache {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid var(--meta-border);
  color: white;
  border-radius: 0.75rem;
  padding: 0.55rem 1rem;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-refresh-cache:hover {
  background: rgba(255, 255, 255, 0.2);
}

/* Grid Principal */
.meta-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 1024px) {
  .meta-grid {
    grid-template-columns: 1fr;
  }
}

.meta-card {
  background: var(--meta-card-bg);
  backdrop-filter: blur(12px);
  border: 1px solid var(--meta-border);
  border-radius: 1.25rem;
  padding: 1.75rem;
  position: relative;
  overflow: hidden;
}

.meta-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.meta-card-title {
  font-size: 1.2rem;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-badge {
  padding: 0.35rem 0.85rem;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.status-meta-batida { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
.status-superavitario { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); }
.status-em-andamento { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
.status-stop-loss { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
.status-deficitario { background: rgba(249, 115, 22, 0.2); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.4); }
.status-neutro { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.4); }
.status-sem-apostas { background: rgba(100, 116, 139, 0.2); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.3); }

/* Métricas em destaque */
.metrics-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

@media (max-width: 640px) {
  .metrics-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

.metric-box {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--meta-border);
  border-radius: 1rem;
  padding: 1rem;
  text-align: center;
}

.metric-label {
  font-size: 0.75rem;
  color: #ffffff;
  text-transform: uppercase;
  font-weight: 700;
  margin-bottom: 0.35rem;
  letter-spacing: 0.03em;
}

.metric-val {
  font-size: 1.4rem;
  font-weight: 800;
  color: #ffffff;
}

.val-green { color: var(--meta-green); }
.val-red { color: var(--meta-red); }
.val-blue { color: var(--meta-blue); }
.val-gold { color: var(--meta-gold); }

/* Progress Bars */
.progress-group {
  margin-bottom: 1.25rem;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  margin-bottom: 0.4rem;
  color: #ffffff;
  font-weight: 600;
}

.progress-bar-bg {
  background: rgba(255, 255, 255, 0.08);
  border-radius: 9999px;
  height: 10px;
  overflow: hidden;
  position: relative;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 9999px;
  transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.fill-green { background: linear-gradient(90deg, #10b981, #059669); }
.fill-blue { background: linear-gradient(90deg, #3b82f6, #2563eb); }
.fill-gold { background: linear-gradient(90deg, #f59e0b, #d97706); }

/* Slots do Ciclo Rotativo */
.cycle-slots-grid {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  gap: 0.5rem;
  margin: 1.5rem 0;
}

@media (max-width: 640px) {
  .cycle-slots-grid {
    grid-template-columns: repeat(5, 1fr);
  }
}

.cycle-slot {
  aspect-ratio: 1;
  background: rgba(15, 23, 42, 0.8);
  border: 1px dashed var(--meta-border);
  border-radius: 0.75rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.2rem;
  padding: 0.25rem 0.15rem;
  font-size: 0.8rem;
  font-weight: 700;
  transition: all 0.2s ease;
  position: relative;
}

.slot-date {
  font-size: 0.68rem;
  font-weight: 700;
  color: #ffffff;
  letter-spacing: -0.01em;
  line-height: 1;
}

.slot-symbol {
  font-size: 0.95rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.slot-green {
  background: rgba(160, 230, 200, 0.15);
  border: 1px solid var(--meta-green);
  color: var(--meta-green);
}
.slot-push {
  background: rgba(148, 163, 184, 0.2);
  border: 1px solid #ffffff;
  color: #ffffff;
}
.slot-red {
  background: rgba(239, 68, 68, 0.2);
  border: 1px solid var(--meta-red);
  color: var(--meta-red);
}
.slot-cashout {
  background: rgba(168, 85, 247, 0.2);
  border: 1px solid #c084fc;
  color: #d8b4fe;
}
.slot-pending {
  background: rgba(245, 158, 11, 0.2);
  border: 1px solid var(--meta-gold);
  color: var(--meta-gold);
}
.slot-empty {
  color: #94a3b8;
}

.btn-nav-cycle {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid var(--meta-border);
  color: #ffffff;
  padding: 0.35rem 0.65rem;
  border-radius: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: all 0.2s ease;
}
.btn-nav-cycle:hover {
  background: rgba(255, 255, 255, 0.15);
  color: white;
}
.btn-nav-cycle.disabled {
  opacity: 0.3;
  pointer-events: none;
}

/* Abas do Histórico */
.history-tabs-container {
  display: flex;
  gap: 0.5rem;
  border-bottom: 1px solid var(--meta-border);
  margin-bottom: 1.25rem;
}

.history-tab-btn {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: #ffffff;
  padding: 0.6rem 1.1rem;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s ease;
}

.history-tab-btn:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.05);
}

.history-tab-btn.active {
  color: #60a5fa;
  border-bottom-color: #60a5fa;
  background: rgba(59, 130, 246, 0.12);
  border-radius: 0.5rem 0.5rem 0 0;
}

/* Histórico dos Dias */
.history-section {
  background: var(--meta-card-bg);
  backdrop-filter: blur(12px);
  border: 1px solid var(--meta-border);
  border-radius: 1.25rem;
  padding: 1.75rem;
}

.history-table-wrapper {
  overflow-x: auto;
  margin-top: 1rem;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.history-table th {
  padding: 0.75rem 1rem;
  background: rgba(15, 23, 42, 0.85);
  color: #ffffff;
  font-size: 0.82rem;
  text-transform: uppercase;
  font-weight: 700;
  border-bottom: 1px solid var(--meta-border);
  letter-spacing: 0.02em;
}

.history-table td {
  padding: 0.9rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  font-size: 0.9rem;
  color: #ffffff;
}

.history-table tr:hover {
  background: rgba(255, 255, 255, 0.02);
}

.history-table tfoot tr.history-table-total-row {
  background: rgba(15, 23, 42, 0.95);
  border-top: 2px solid var(--meta-border);
  font-weight: 700;
}

.history-table tfoot td {
  padding: 0.9rem 1rem;
  font-size: 0.95rem;
  border-bottom: none;
}

/* Modal Styling */
.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(6px);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.modal-overlay.active {
  display: flex;
}

.modal-content {
  background: #1e293b;
  border: 1px solid var(--meta-border);
  border-radius: 1.25rem;
  width: 100%;
  max-width: 580px;
  padding: 2rem;
  color: white;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.3rem;
}
.btn-close-modal {
  background: transparent;
  border: none;
  color: #ffffff;
  font-size: 1.5rem;
  cursor: pointer;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}
.form-group {
  margin-bottom: 1rem;
}
.form-group label {
  display: block;
  font-size: 0.85rem;
  color: #ffffff;
  margin-bottom: 0.35rem;
  font-weight: 600;
}
.form-control-meta {
  width: 100%;
  background: #0f172a;
  border: 1px solid var(--meta-border);
  border-radius: 0.5rem;
  padding: 0.6rem 0.8rem;
  color: white;
  font-size: 0.95rem;
  box-sizing: border-box;
}
.form-control-meta:focus {
  border-color: #6366f1;
  outline: none;
}
</style>

<div class="meta-container">
  
  <!-- Cabeçalho -->
  <div class="meta-header">
    <div class="meta-title-box">
      <h1>🎯 Gestão de Metas Diárias & Ciclos Rotativos</h1>
      <p>Acompanhamento de metas de longo prazo com proporção de 70% a 80% de cobertura e gestão de banca</p>
    </div>

    <div class="meta-controls">
      <!-- Seletor de Data -->
      <div class="date-selector-wrapper">
        <a href="<?= site_url('/metas?data=' . date('Y-m-d', strtotime($dataRefStr . ' -1 day'))) ?>" class="btn-nav-date" title="Dia Anterior">◀</a>
        <input type="date" id="inputDataRef" value="<?= htmlspecialchars($dataRefStr) ?>" class="date-input" onchange="window.location.href='<?= site_url('/metas?data=') ?>' + this.value">
        <a href="<?= site_url('/metas?data=' . date('Y-m-d', strtotime($dataRefStr . ' +1 day'))) ?>" class="btn-nav-date" title="Próximo Dia">▶</a>
      </div>

      <?php if (!$isHoje): ?>
        <a href="<?= site_url('/metas') ?>" class="btn-nav-date" style="font-weight: 600; color: #60a5fa;">📅 Ir para Hoje</a>
      <?php endif; ?>

      <button type="button" class="btn-refresh-cache" onclick="recalcularCache('<?= $dataRefStr ?>')" title="Forçar recálculo estatístico de hoje">
        <i class="fas fa-sync-alt"></i> Atualizar Cache
      </button>

      <button type="button" class="btn-config" onclick="abrirModalConfig()">
        <i class="fas fa-cog"></i> Configurar Meta
      </button>
    </div>
  </div>

  <!-- Grid Superior: Meta Diária vs Ciclo Rotativo -->
  <div class="meta-grid">
    
    <!-- CARD 1: Meta do Dia Selecionado -->
    <div class="meta-card">
      <div class="meta-card-header">
        <div>
          <h2 class="meta-card-title">
            <span>📅 Desempenho Diário</span>
            <small style="font-size: 0.85rem; color: #ffffff; font-weight: 600;">(<?= date('d/m/Y', strtotime($dataRefStr)) ?><?= $isHoje ? ' - Hoje' : '' ?>)</small>
          </h2>
          <div style="font-size: 0.85rem; color: #ffffff; margin-top: 0.25rem;">
            Meta Padrão: <?= $alvoApostas ?> apostas de R$ <?= number_format($stakePadrao, 2, ',', '.') ?> @ <?= number_format($oddAlvo, 2) ?>
          </div>
        </div>

        <div>
          <?php
            $badgeClass = 'status-em-andamento';
            $badgeText = '⏳ Em Andamento';
            if ($statusDia === 'META_BATIDA') {
                $badgeClass = 'status-meta-batida';
                $badgeText = '🎯 Meta Batida!';
            } elseif ($statusDia === 'SUPERAVITARIO') {
                $badgeClass = 'status-superavitario';
                $badgeText = '🚀 Superavitário';
            } elseif ($statusDia === 'STOP_LOSS_ATINGIDO') {
                $badgeClass = 'status-stop-loss';
                $badgeText = '⚠️ Stop Loss';
            } elseif ($statusDia === 'DEFICITARIO') {
                $badgeClass = 'status-deficitario';
                $badgeText = '📉 Deficitário';
            } elseif ($statusDia === 'NEUTRO') {
                $badgeClass = 'status-neutro';
                $badgeText = '🛡️ Neutro / Protegido';
            } elseif ($statusDia === 'SEM_APOSTAS') {
                $badgeClass = 'status-sem-apostas';
                $badgeText = '💤 Sem Apostas';
            }
          ?>
          <span class="status-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
        </div>
      </div>

      <!-- Métricas do Dia -->
      <div class="metrics-row">
        <div class="metric-box">
          <div class="metric-label">Apostas do Dia</div>
          <div class="metric-val"><?= $totalCad ?> <span style="font-size: 0.85rem; color: #ffffff; font-weight: 600;">/ <?= $alvoApostas ?></span></div>
        </div>
        <div class="metric-box">
          <div class="metric-label">Lucro Líquido</div>
          <div class="metric-val <?= $lucroReal > 0 ? 'val-green' : ($lucroReal < 0 ? 'val-red' : '') ?>">
            <?= ($lucroReal > 0 ? '+' : '') ?>R$ <?= number_format($lucroReal, 2, ',', '.') ?>
          </div>
        </div>
        <div class="metric-box">
          <div class="metric-label">ROI do Dia</div>
          <div class="metric-val <?= $roiReal > 0 ? 'val-green' : ($roiReal < 0 ? 'val-red' : '') ?>">
            <?= ($roiReal > 0 ? '+' : '') ?><?= number_format($roiReal, 1, ',', '.') ?>%
          </div>
        </div>
        <div class="metric-box">
          <div class="metric-label">Odd Média Real</div>
          <div class="metric-val val-blue"><?= $oddReal > 0 ? number_format($oddReal, 2) : '-' ?></div>
        </div>
      </div>

      <!-- Detalhamento de Desfechos do Dia -->
      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 1.5rem; text-align: center;">
        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 0.75rem; padding: 0.6rem;">
          <div style="font-size: 0.7rem; color: #34d399; font-weight: 700;">🟢 GREENS</div>
          <div style="font-size: 1.15rem; font-weight: 800; color: #10b981;"><?= $fmtScore($greens) ?> <small style="font-size: 0.75rem; color: #ffffff; opacity: 0.9;">(Alvo: <?= $targetG ?>)</small></div>
        </div>
        <div style="background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 0.75rem; padding: 0.6rem;">
          <div style="font-size: 0.7rem; color: #ffffff; font-weight: 700;">⚪ REEMBOLSOS</div>
          <div style="font-size: 1.15rem; font-weight: 800; color: #ffffff;"><?= $pushes ?> <small style="font-size: 0.75rem; color: #ffffff; opacity: 0.9;">(Alvo: <?= $targetP ?>)</small></div>
        </div>
        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 0.75rem; padding: 0.6rem;">
          <div style="font-size: 0.7rem; color: #f87171; font-weight: 700;">🔴 REDS</div>
          <div style="font-size: 1.15rem; font-weight: 800; color: #ef4444;"><?= $fmtScore($reds) ?> <small style="font-size: 0.75rem; color: #ffffff; opacity: 0.9;">(Máx: <?= $maxR ?>)</small></div>
        </div>
        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 0.75rem; padding: 0.6rem;">
          <div style="font-size: 0.7rem; color: #fbbf24; font-weight: 700;">⏳ PENDENTES</div>
          <div style="font-size: 1.15rem; font-weight: 800; color: #f59e0b;"><?= $pendentes ?></div>
        </div>
      </div>

      <!-- Barra de Progresso de Lucro -->
      <div class="progress-group">
        <div class="progress-header">
          <span>Progresso da Meta Financeira (Alvo: +R$ <?= number_format($lucroAlvo, 2, ',', '.') ?>)</span>
          <span style="font-weight: 700; color: white;"><?= number_format($progLucro, 1) ?>%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill fill-green" style="width: <?= $progLucro ?>%;"></div>
        </div>
      </div>

      <!-- Barra de Progresso de Volume -->
      <div class="progress-group" style="margin-bottom: 0;">
        <div class="progress-header">
          <span>Apostas Realizadas no Dia (<?= $totalCad ?> / <?= $alvoApostas ?>)</span>
          <span style="font-weight: 700; color: white;"><?= number_format($progApostas, 1) ?>%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill fill-blue" style="width: <?= $progApostas ?>%;"></div>
        </div>
      </div>

    </div>

    <!-- CARD 2: Ciclo Sequencial Fechado (Bloco Fixo de 10 Apostas) -->
    <div class="meta-card" id="cardCiclo">
      <div class="meta-card-header">
        <div>
          <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <h2 class="meta-card-title" style="margin: 0;">
              <span>🔄 Ciclo Sequencial #<?= ($ciclo['numero_ciclo'] ?? 1) ?></span>
              <small style="font-size: 0.85rem; color: #ffffff; font-weight: 600;">(Bloco de <?= ($ciclo['tamanho_ciclo'] ?? 10) ?> Apostas)</small>
            </h2>
            <?php if (!empty($ciclo['is_ativo'])): ?>
              <span class="status-badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); font-size: 0.7rem; padding: 0.2rem 0.6rem;">
                🟢 Ativo
              </span>
            <?php else: ?>
              <span class="status-badge" style="background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.4); font-size: 0.7rem; padding: 0.2rem 0.6rem;">
                🏁 Fechado
              </span>
            <?php endif; ?>
          </div>
          <div style="font-size: 0.85rem; color: #ffffff; margin-top: 0.25rem;">
            Blocos fixos sequenciais auditáveis: nenhum red ou green é descartado por janela deslizante.
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <?php
            $numC = (int)($ciclo['numero_ciclo'] ?? 1);
            $totalC = (int)($ciclo['total_ciclos'] ?? 1);
          ?>
          <a href="<?= site_url('/metas?data=' . $dataRefStr . '&ciclo=' . ($numC - 1)) ?>#cardCiclo" 
             class="btn-nav-cycle <?= ($numC <= 1) ? 'disabled' : '' ?>" title="Ciclo Anterior">◀</a>

          <span class="status-badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.4);">
            Slot <?= ($ciclo['total_no_ciclo'] ?? 0) ?> / <?= ($ciclo['tamanho_ciclo'] ?? 10) ?>
          </span>

          <a href="<?= site_url('/metas?data=' . $dataRefStr . '&ciclo=' . ($numC + 1)) ?>#cardCiclo" 
             class="btn-nav-cycle <?= ($numC >= $totalC) ? 'disabled' : '' ?>" title="Próximo Ciclo">▶</a>
        </div>
      </div>

      <!-- Grade Visual dos 10 Slots do Ciclo (Ordem Cronológica de 1 a 10) -->
      <div class="cycle-slots-grid">
        <?php
          $tamCiclo = (int)($ciclo['tamanho_ciclo'] ?? 10);
          $apostasCiclo = $ciclo['apostas'] ?? [];

          for ($i = 0; $i < $tamCiclo; $i++):
              $ap = $apostasCiclo[$i] ?? null;
              $slotClass = 'slot-empty';
              $slotIcon = ($i + 1);
              $slotDate = '';
              $slotTitle = "Slot #".($i+1)." vazio";

              if ($ap) {
                  $rawDate = !empty($ap->data_hora_jogo) ? $ap->data_hora_jogo : ($ap->criado_em ?? null);
                  if ($rawDate) {
                      try {
                          $dt = new \DateTime($rawDate, new \DateTimeZone('UTC'));
                          $dt->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                          $slotDate = $dt->format('d/m');
                      } catch (\Throwable $e) {
                          $slotDate = date('d/m', strtotime($rawDate));
                      }
                  }

                  $st = $ap->status;
                  if (in_array($st, ['Ganha', 'Meio Ganha'])) {
                      $slotClass = 'slot-green';
                      $slotIcon = '✓';
                      $slotTitle = "#{$ap->id} [{$slotDate}]: {$ap->time_casa} x {$ap->time_fora} ({$st})";
                  } elseif (in_array($st, ['ANULADA', 'Anulada'])) {
                      $slotClass = 'slot-push';
                      $slotIcon = '⚪';
                      $slotTitle = "#{$ap->id} [{$slotDate}]: Reembolso / Push";
                  } elseif (in_array($st, ['Perdida', 'Meio Perdida'])) {
                      $slotClass = 'slot-red';
                      $slotIcon = '✕';
                      $slotTitle = "#{$ap->id} [{$slotDate}]: {$st}";
                  } elseif ($st === 'Cashout') {
                      $slotClass = 'slot-cashout';
                      $slotIcon = '💰';
                      $valCash = !empty($ap->cash_out) ? 'R$ ' . number_format((float)$ap->cash_out, 2, ',', '.') : 'Encerrada';
                      $slotTitle = "#{$ap->id} [{$slotDate}]: Cashout ({$valCash})";
                  } else {
                      $slotClass = 'slot-pending';
                      $slotIcon = '⏳';
                      $slotTitle = "#{$ap->id} [{$slotDate}]: Pendente";
                  }
              }
        ?>
          <div class="cycle-slot <?= $slotClass ?>" title="<?= htmlspecialchars($slotTitle) ?>">
            <?php if (!empty($slotDate)): ?>
              <span class="slot-date"><?= $slotDate ?></span>
            <?php endif; ?>
            <span class="slot-symbol"><?= $slotIcon ?></span>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Métricas do Ciclo Ativo -->
      <div class="metrics-row" style="margin-bottom: 1.25rem;">
        <div class="metric-box">
          <div class="metric-label">Progresso Ciclo</div>
          <div class="metric-val"><?= ($ciclo['total_no_ciclo'] ?? 0) ?>/<?= $tamCiclo ?></div>
        </div>
        <div class="metric-box">
          <div class="metric-label">Saldo Acumulado</div>
          <div class="metric-val <?= ($ciclo['lucro_liquido'] ?? 0) > 0 ? 'val-green' : (($ciclo['lucro_liquido'] ?? 0) < 0 ? 'val-red' : '') ?>">
            <?= (($ciclo['lucro_liquido'] ?? 0) > 0 ? '+' : '') ?>R$ <?= number_format($ciclo['lucro_liquido'] ?? 0, 2, ',', '.') ?>
          </div>
        </div>
        <div class="metric-box">
          <div class="metric-label">ROI do Ciclo</div>
          <div class="metric-val <?= ($ciclo['roi_pct'] ?? 0) > 0 ? 'val-green' : (($ciclo['roi_pct'] ?? 0) < 0 ? 'val-red' : '') ?>">
            <?= (($ciclo['roi_pct'] ?? 0) > 0 ? '+' : '') ?><?= number_format($ciclo['roi_pct'] ?? 0, 1, ',', '.') ?>%
          </div>
        </div>
        <div class="metric-box">
          <div class="metric-label">Odd Média Ciclo</div>
          <div class="metric-val val-blue"><?= number_format($ciclo['odd_media'] ?? 0, 2) ?></div>
        </div>
      </div>

      <div style="background: rgba(15, 23, 42, 0.6); border: 1px solid var(--meta-border); border-radius: 0.75rem; padding: 0.85rem; font-size: 0.85rem; color: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <div>
          <span>Balanço: </span>
          <strong style="color: #10b981;"><?= $fmtScore($ciclo['greens'] ?? 0) ?>V</strong> - 
          <strong style="color: #ffffff;"><?= $ciclo['pushes'] ?? 0 ?>E</strong> - 
          <strong style="color: #ef4444;"><?= $fmtScore($ciclo['reds'] ?? 0) ?>D</strong>
          <?php if (($ciclo['cashouts'] ?? 0) > 0): ?>
            - <strong style="color: #c084fc;"><?= $ciclo['cashouts'] ?> Cashout</strong>
          <?php endif; ?>
          <?php if (($ciclo['pendentes'] ?? 0) > 0): ?>
            <span style="color: #f59e0b;">(<?= $ciclo['pendentes'] ?> em jogo)</span>
          <?php endif; ?>
        </div>
        <div>
          Meta do Ciclo: <strong style="color: #34d399;">+R$ <?= number_format($ciclo['lucro_alvo'] ?? 7.50, 2, ',', '.') ?></strong>
        </div>
      </div>

    </div>

  </div>

  <!-- SEÇÃO INFERIOR: Histórico com Abas (Diário vs Ciclos Sequenciais) -->
  <div class="history-section">
    <div class="history-tabs-container">
      <button type="button" class="history-tab-btn active" id="tabBtnDias" onclick="switchHistoryTab('dias')">
        <span>📅 Fechamento Diário</span>
        <small style="color: #ffffff; opacity: 0.9;">(Últimos 30 Dias)</small>
      </button>
      <button type="button" class="history-tab-btn" id="tabBtnCiclos" onclick="switchHistoryTab('ciclos')">
        <span>🔄 Ciclos Fechados</span>
        <small style="color: #ffffff; opacity: 0.9;">(Blocos Fixos de <?= $alvoApostas ?>)</small>
      </button>
    </div>

    <!-- CONTEÚDO TAB 1: Fechamento Diário -->
    <div id="tabContentDias">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 0.5rem; color: #ffffff;">
          <span>📊 Histórico de Fechamento Diário</span>
        </h3>
        <span style="font-size: 0.85rem; color: #ffffff;">
          ⚡ Leitura instantânea cacheada no MySQL
        </span>
      </div>

    <div class="history-table-wrapper">
      <table class="history-table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Apostas</th>
            <th>Greens</th>
            <th>Reembolsos</th>
            <th>Reds</th>
            <th>Odd Média</th>
            <th>Total Investido</th>
            <th>Lucro Líquido</th>
            <th>ROI</th>
            <th>Status do Dia</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($historico)): ?>
            <?php 
              $totCadastradas = 0;
              $totGreens = 0;
              $totPushes = 0;
              $totReds = 0;
              $totInvestido = 0.0;
              $totLucro = 0.0;
              $sumOddWeight = 0.0;
              $countOddWeight = 0;
            ?>
            <?php foreach ($historico as $h): 
                $hLucro = (float)($h['lucro_liquido'] ?? 0);
                $hRoi   = (float)($h['roi_pct'] ?? 0);
                $hSt    = $h['status_dia'] ?? 'EM_ANDAMENTO';
                $cadDia = (int)($h['total_apostas_cadastradas'] ?? 0);
                $oddDia = (float)($h['odd_media_real'] ?? 0);

                $totCadastradas += $cadDia;
                $totGreens      += (float)($h['greens_count'] ?? 0.0);
                $totPushes      += (int)($h['pushes_count'] ?? 0);
                $totReds        += (float)($h['reds_count'] ?? 0.0);
                $totInvestido   += (float)($h['total_apostado'] ?? 0);
                $totLucro       += $hLucro;
                if ($oddDia > 0 && $cadDia > 0) {
                    $sumOddWeight += ($oddDia * $cadDia);
                    $countOddWeight += $cadDia;
                }
            ?>
              <tr>
                <td>
                  <strong><?= date('d/m/Y', strtotime($h['data_referencia'])) ?></strong>
                  <?php if ($h['data_referencia'] === date('Y-m-d')): ?>
                    <span style="font-size: 0.75rem; color: #60a5fa; margin-left: 0.25rem;">(Hoje)</span>
                  <?php endif; ?>
                </td>
                <td><?= $h['total_apostas_cadastradas'] ?> / <?= $alvoApostas ?></td>
                <td style="color: #10b981; font-weight: 700;"><?= $fmtScore($h['greens_count'] ?? 0) ?></td>
                <td style="color: #ffffff; font-weight: 600;"><?= $fmtScore($h['pushes_count'] ?? 0) ?></td>
                <td style="color: #ef4444; font-weight: 700;"><?= $fmtScore($h['reds_count'] ?? 0) ?></td>
                <td><?= number_format((float)($h['odd_media_real'] ?? 0), 2) ?></td>
                <td>R$ <?= number_format((float)($h['total_apostado'] ?? 0), 2, ',', '.') ?></td>
                <td style="font-weight: 700;" class="<?= $hLucro > 0 ? 'val-green' : ($hLucro < 0 ? 'val-red' : '') ?>">
                  <?= ($hLucro > 0 ? '+' : '') ?>R$ <?= number_format($hLucro, 2, ',', '.') ?>
                </td>
                <td style="font-weight: 700;" class="<?= $hRoi > 0 ? 'val-green' : ($hRoi < 0 ? 'val-red' : '') ?>">
                  <?= ($hRoi > 0 ? '+' : '') ?><?= number_format($hRoi, 1, ',', '.') ?>%
                </td>
                <td>
                  <?php if ($hSt === 'META_BATIDA'): ?>
                    <span class="status-badge status-meta-batida">🎯 Batida</span>
                  <?php elseif ($hSt === 'SUPERAVITARIO'): ?>
                    <span class="status-badge status-superavitario">🚀 Lucro</span>
                  <?php elseif ($hSt === 'STOP_LOSS_ATINGIDO'): ?>
                    <span class="status-badge status-stop-loss">⚠️ Stop</span>
                  <?php elseif ($hSt === 'DEFICITARIO'): ?>
                    <span class="status-badge status-deficitario">📉 Déficit</span>
                  <?php elseif ($hSt === 'NEUTRO'): ?>
                    <span class="status-badge status-neutro">🛡️ Neutro</span>
                  <?php elseif ($hSt === 'SEM_APOSTAS'): ?>
                    <span class="status-badge status-sem-apostas">💤 Vazio</span>
                  <?php else: ?>
                    <span class="status-badge status-em-andamento">⏳ Em curso</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="<?= site_url('/metas?data=' . $h['data_referencia']) ?>" class="btn-nav-date" style="padding: 0.2rem 0.6rem; font-size: 0.75rem;" title="Ver detalhes do dia">
                    Ver Dia
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="11" style="text-align: center; color: #ffffff; padding: 2rem;">
                Nenhum histórico diário cacheado ainda. Ao liquidar as apostas, o cache será preenchido automaticamente!
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($historico)): ?>
          <?php 
            $totOddMedia = $countOddWeight > 0 ? ($sumOddWeight / $countOddWeight) : 0.0;
            $totRoi = $totInvestido > 0 ? (($totLucro / $totInvestido) * 100.0) : 0.0;
          ?>
          <tfoot>
            <tr class="history-table-total-row">
              <td><strong>TOTAL</strong></td>
              <td><strong><?= $totCadastradas ?></strong></td>
              <td style="color: #10b981; font-weight: 700;"><?= $fmtScore($totGreens) ?></td>
              <td style="color: #ffffff; font-weight: 700;"><?= $fmtScore($totPushes) ?></td>
              <td style="color: #ef4444; font-weight: 700;"><?= $fmtScore($totReds) ?></td>
              <td><strong><?= $totOddMedia > 0 ? number_format($totOddMedia, 2) : '-' ?></strong></td>
              <td><strong>R$ <?= number_format($totInvestido, 2, ',', '.') ?></strong></td>
              <td style="font-weight: 700;" class="<?= $totLucro > 0 ? 'val-green' : ($totLucro < 0 ? 'val-red' : '') ?>">
                <?= ($totLucro > 0 ? '+' : '') ?>R$ <?= number_format($totLucro, 2, ',', '.') ?>
              </td>
              <td style="font-weight: 700;" class="<?= $totRoi > 0 ? 'val-green' : ($totRoi < 0 ? 'val-red' : '') ?>">
                <?= ($totRoi > 0 ? '+' : '') ?><?= number_format($totRoi, 1, ',', '.') ?>%
              </td>
              <td>
                <span class="status-badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.4); font-size: 0.72rem; padding: 0.25rem 0.6rem;">
                  TOTAL GERAL
                </span>
              </td>
              <td></td>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>
    </div>
    <!-- FIM TAB 1: Fechamento Diário -->

    <!-- CONTEÚDO TAB 2: Histórico de Ciclos Sequenciais Fechados -->
    <div id="tabContentCiclos" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 0.5rem; color: #ffffff;">
          <span>📦 Histórico de Ciclos Sequenciais Fechados</span>
        </h3>
        <span style="font-size: 0.85rem; color: #ffffff;">
          Auditabilidade total: cada aposta pertence a um único bloco fixo
        </span>
      </div>

      <div class="history-table-wrapper">
        <table class="history-table">
          <thead>
            <tr>
              <th>Ciclo</th>
              <th>Período</th>
              <th>Apostas</th>
              <th>Greens</th>
              <th>Reembolsos</th>
              <th>Reds</th>
              <th>Cashouts</th>
              <th>Lucro Líquido</th>
              <th>ROI</th>
              <th>Status do Ciclo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($historicoC)): ?>
              <?php foreach ($historicoC as $hc): 
                  $cLucro = (float)($hc['lucro_liquido'] ?? 0);
                  $cRoi   = (float)($hc['roi_pct'] ?? 0);
                  $cSt    = $hc['status'] ?? 'EM_ANDAMENTO';
                  $isAtiv = ((int)($hc['numero_ciclo'] ?? 0) === (int)($ciclo['ciclo_ativo_num'] ?? 0));
                  
                  $dtIniFmt = !empty($hc['data_inicio']) ? date('d/m', strtotime($hc['data_inicio'])) : '-';
                  $dtFimFmt = !empty($hc['data_fim']) ? date('d/m', strtotime($hc['data_fim'])) : '-';
                  $periodoStr = ($dtIniFmt === $dtFimFmt) ? $dtIniFmt : "{$dtIniFmt} a {$dtFimFmt}";
              ?>
                <tr style="<?= $isAtiv ? 'background: rgba(99, 102, 241, 0.12);' : '' ?>">
                  <td>
                    <strong>Ciclo #<?= $hc['numero_ciclo'] ?></strong>
                    <?php if ($isAtiv): ?>
                      <span style="font-size: 0.7rem; color: #34d399; margin-left: 0.25rem;">(Ativo)</span>
                    <?php endif; ?>
                  </td>
                  <td><?= $periodoStr ?></td>
                  <td><?= $hc['total_apostas'] ?> / <?= $hc['tamanho_ciclo'] ?></td>
                  <td style="color: #10b981; font-weight: 700;"><?= $fmtScore($hc['greens'] ?? 0) ?></td>
                  <td style="color: #ffffff; font-weight: 600;"><?= $fmtScore($hc['pushes'] ?? 0) ?></td>
                  <td style="color: #ef4444; font-weight: 700;"><?= $fmtScore($hc['reds'] ?? 0) ?></td>
                  <td style="color: #c084fc; font-weight: 600;"><?= $hc['cashouts'] ?></td>
                  <td style="font-weight: 700;" class="<?= $cLucro > 0 ? 'val-green' : ($cLucro < 0 ? 'val-red' : '') ?>">
                    <?= ($cLucro > 0 ? '+' : '') ?>R$ <?= number_format($cLucro, 2, ',', '.') ?>
                  </td>
                  <td style="font-weight: 700;" class="<?= $cRoi > 0 ? 'val-green' : ($cRoi < 0 ? 'val-red' : '') ?>">
                    <?= ($cRoi > 0 ? '+' : '') ?><?= number_format($cRoi, 1, ',', '.') ?>%
                  </td>
                  <td>
                    <?php if ($cSt === 'META_BATIDA'): ?>
                      <span class="status-badge status-meta-batida">🎯 Batida</span>
                    <?php elseif ($cSt === 'SUPERAVITARIO'): ?>
                      <span class="status-badge status-superavitario">🚀 Lucro</span>
                    <?php elseif ($cSt === 'STOP_LOSS_ATINGIDO'): ?>
                      <span class="status-badge status-stop-loss">⚠️ Stop</span>
                    <?php elseif ($cSt === 'DEFICITARIO'): ?>
                      <span class="status-badge status-deficitario">📉 Déficit</span>
                    <?php elseif ($cSt === 'NEUTRO'): ?>
                      <span class="status-badge status-neutro">🛡️ Neutro</span>
                    <?php else: ?>
                      <span class="status-badge status-em-andamento">⏳ Em curso</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= site_url('/metas?data=' . $dataRefStr . '&ciclo=' . $hc['numero_ciclo']) ?>#cardCiclo" 
                       class="btn-nav-date" style="padding: 0.2rem 0.6rem; font-size: 0.75rem;" title="Inspecionar este ciclo">
                      Inspecionar
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="11" style="text-align: center; color: #ffffff; padding: 2rem;">
                  Nenhum ciclo fechado registrado até o momento.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- FIM TAB 2: Ciclos Fechados -->

</div>

<!-- MODAL DE CONFIGURAÇÃO DE META -->
<div class="modal-overlay" id="modalConfig">
  <div class="modal-content">
    <div class="modal-header">
      <h3>⚙️ Configuração de Parâmetros de Meta</h3>
      <button type="button" class="btn-close-modal" onclick="fecharModalConfig()">&times;</button>
    </div>

    <form id="formMetaConfig" onsubmit="salvarConfig(event)">
      <div class="form-group">
        <label>Título / Identificador da Meta</label>
        <input type="text" name="titulo" class="form-control-meta" value="<?= htmlspecialchars($meta->titulo ?? 'Meta Padrão - Ciclo 10 Apostas R$ 10') ?>" required>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label>Stake Padrão por Aposta (R$)</label>
          <input type="number" step="0.50" name="stake_padrao" class="form-control-meta" value="<?= $stakePadrao ?>" required>
        </div>
        <div class="form-group">
          <label>Total de Apostas Alvo (Ciclo)</label>
          <input type="number" name="total_apostas_alvo" class="form-control-meta" value="<?= $alvoApostas ?>" required>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label>Odd Média Alvo</label>
          <input type="number" step="0.01" name="odd_media_alvo" class="form-control-meta" value="<?= $oddAlvo ?>" required>
        </div>
        <div class="form-group">
          <label>Meta de Lucro Líquido (R$)</label>
          <input type="number" step="0.50" name="lucro_alvo" class="form-control-meta" value="<?= $lucroAlvo ?>" required>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label>Alvo de Greens (Vitórias)</label>
          <input type="number" name="target_greens" class="form-control-meta" value="<?= $targetG ?>" required>
        </div>
        <div class="form-group">
          <label>Alvo de Reembolsos (Push)</label>
          <input type="number" name="target_pushes" class="form-control-meta" value="<?= $targetP ?>" required>
        </div>
      </div>

      <div class="form-row-2">
        <div class="form-group">
          <label>Máximo de Reds Tolerados</label>
          <input type="number" name="max_reds" class="form-control-meta" value="<?= $maxR ?>" required>
        </div>
        <div class="form-group">
          <label>Stop Loss Diário (R$)</label>
          <input type="number" step="0.50" name="stop_loss_diario" class="form-control-meta" value="<?= $stopLoss ?>" required>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
        <button type="button" class="btn-nav-date" onclick="fecharModalConfig()">Cancelar</button>
        <button type="submit" class="btn-config" id="btnSalvarMeta">
          <i class="fas fa-save"></i> Salvar Parâmetros
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirModalConfig() {
  document.getElementById('modalConfig').classList.add('active');
}

function fecharModalConfig() {
  document.getElementById('modalConfig').classList.remove('active');
}

function recalcularCache(dataRef) {
  const btn = event.currentTarget;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando...';
  btn.disabled = true;

  fetch('<?= site_url('/metas/recalcular-dia') ?>', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'data=' + encodeURIComponent(dataRef)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.location.reload();
    } else {
      alert('Erro ao atualizar cache: ' + (data.message || 'Erro desconhecido'));
      btn.innerHTML = originalHtml;
      btn.disabled = false;
    }
  })
  .catch(err => {
    alert('Falha na requisição: ' + err);
    btn.innerHTML = originalHtml;
    btn.disabled = false;
  });
}

function salvarConfig(e) {
  e.preventDefault();
  const form = document.getElementById('formMetaConfig');
  const formData = new FormData(form);
  const btn = document.getElementById('btnSalvarMeta');
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
  btn.disabled = true;

  fetch('<?= site_url('/metas/salvar-config') ?>', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      fecharModalConfig();
      window.location.reload();
    } else {
      alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido'));
      btn.innerHTML = '<i class="fas fa-save"></i> Salvar Parâmetros';
      btn.disabled = false;
    }
  })
  .catch(err => {
    alert('Erro de conexão: ' + err);
    btn.innerHTML = '<i class="fas fa-save"></i> Salvar Parâmetros';
    btn.disabled = false;
  });
}
function switchHistoryTab(tabName) {
  const btnDias = document.getElementById('tabBtnDias');
  const btnCiclos = document.getElementById('tabBtnCiclos');
  const contentDias = document.getElementById('tabContentDias');
  const contentCiclos = document.getElementById('tabContentCiclos');

  if (tabName === 'ciclos') {
    btnDias.classList.remove('active');
    btnCiclos.classList.add('active');
    contentDias.style.display = 'none';
    contentCiclos.style.display = 'block';
  } else {
    btnCiclos.classList.remove('active');
    btnDias.classList.add('active');
    contentCiclos.style.display = 'none';
    contentDias.style.display = 'block';
  }
}

// Se o usuário navegou com ?ciclo=, ativa a aba de ciclos automaticamente
<?php if (!empty($_GET['ciclo'])): ?>
  document.addEventListener('DOMContentLoaded', function() {
    switchHistoryTab('ciclos');
  });
<?php endif; ?>
</script>
