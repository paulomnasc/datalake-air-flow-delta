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
    color: var(--text-muted);
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
    color: var(--text-muted);
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
    color: var(--text-muted);
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

  @media print {
    .header-actions { display: none !important; }
    body { background-color: #ffffff !important; color: #000000 !important; }
    .report-header, .rank-card, .summary-card { background: #ffffff !important; border: 1px solid #ccc !important; color: #000 !important; }
    .report-title h1, .pick-name, .metric-value, .summary-value { color: #000000 !important; }
  }
</style>

<div class="report-container">

  <!-- Header -->
  <div class="report-header">
    <div class="report-title">
      <h1><i class="bi bi-trophy-fill"></i> Rank Top 5 Mercado + Palpite</h1>
      <div class="report-subtitle">Relatório das combinações de maior taxa de vitória e lucro acumulado no sistema.</div>
    </div>

    <div class="header-actions">
      <button class="btn-report-action" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> Imprimir / Exportar PDF
      </button>
      <a href="<?= base_url('apostas') ?>" class="btn-report-action">
        <i class="bi bi-arrow-left"></i> Minhas Apostas
      </a>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="summary-grid">
    <div class="summary-card">
      <div class="summary-icon green">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div>
        <div class="summary-label">Apostas Ganhas</div>
        <div class="summary-value"><?= $statSummary['total_ganhas'] ?? 0 ?> / <?= $statSummary['total_apostas'] ?? 0 ?></div>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-icon gold">
        <i class="bi bi-cash-coin"></i>
      </div>
      <div>
        <div class="summary-label">Retorno das Vitórias</div>
        <div class="summary-value">R$ <?= number_format($statSummary['retorno_ganhas'] ?? 0, 2, ',', '.') ?></div>
      </div>
    </div>

    <div class="summary-card">
      <div class="summary-icon blue">
        <i class="bi bi-pie-chart-fill"></i>
      </div>
      <div>
        <div class="summary-label">Taxa de Eficiência</div>
        <div class="summary-value">
          <?php 
            $tot = (int)($statSummary['total_apostas'] ?? 0);
            $gan = (int)($statSummary['total_ganhas'] ?? 0);
            $rate = ($tot > 0) ? round(($gan / $tot) * 100, 1) : 0;
            echo $rate . '%';
          ?>
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
        <i class="bi bi-info-circle me-2"></i> Você ainda não possui apostas com status <strong>Ganha</strong> para compor seu ranking pessoal.
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
              <div class="market-tag"><i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($item['mercado']) ?></div>
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
        <i class="bi bi-info-circle me-2"></i> Nenhuma aposta ganha registrada na plataforma até o momento.
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
              <div class="market-tag"><i class="bi bi-tag-fill me-1"></i> <?= htmlspecialchars($item['mercado']) ?></div>
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
