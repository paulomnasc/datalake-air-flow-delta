<?php
if (!function_exists('formatBrtDate')) {
    function formatBrtDate($utcDateStr, $format = 'd/m H:i') {
        if (empty($utcDateStr)) return 'Hoje';
        try {
            $dt = new DateTime($utcDateStr, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            return $dt->format($format);
        } catch (\Exception $e) {
            return date($format, strtotime($utcDateStr));
        }
    }
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
    max-width: 1300px;
    margin: 30px auto;
    padding: 0 20px 60px 20px;
  }

  /* Header banner */
  .bet-header {
    background: linear-gradient(135deg, #161b22 0%, #1f2937 100%);
    border: 1px solid var(--bet-card-border);
    border-radius: 16px;
    padding: 28px 36px;
    margin-bottom: 30px;
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
    font-size: 2.2rem;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .bet-title h1 i {
    color: var(--bet-primary);
    text-shadow: 0 0 15px var(--bet-primary-glow);
  }

  .bet-subtitle {
    color: var(--bet-text-muted);
    margin-top: 6px;
    font-size: 0.95rem;
  }

  .token-badge {
    background: rgba(0, 230, 118, 0.12);
    border: 1px solid rgba(0, 230, 118, 0.3);
    color: var(--bet-primary);
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .token-badge-locked {
    background: rgba(255, 82, 82, 0.12);
    border: 1px solid rgba(255, 82, 82, 0.3);
    color: var(--bet-danger);
  }

  /* Cards Resumo */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }

  .stat-card {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 14px;
    padding: 20px 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  }

  .stat-label {
    color: var(--bet-text-muted);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 600;
    margin-bottom: 8px;
  }

  .stat-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: #ffffff;
  }

  .stat-value.primary { color: var(--bet-primary); }
  .stat-value.accent  { color: var(--bet-accent); }
  .stat-value.gold    { color: var(--bet-gold); }

  /* Toolbar */
  .bet-toolbar {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 14px;
    padding: 16px 24px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
  }

  .bet-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .filter-btn {
    background: #21262d;
    border: 1px solid #30363d;
    color: var(--bet-text-muted);
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .filter-btn.active, .filter-btn:hover {
    background: var(--bet-primary);
    color: #000000;
    border-color: var(--bet-primary);
    font-weight: 700;
  }

  .search-box {
    position: relative;
    min-width: 240px;
  }

  .search-box input {
    background: #0d1117;
    border: 1px solid #30363d;
    color: #ffffff;
    padding: 8px 16px 8px 38px;
    border-radius: 30px;
    font-size: 0.9rem;
    width: 100%;
  }

  .search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--bet-text-muted);
  }

  .btn-new-bet {
    background: linear-gradient(135deg, #00e676 0%, #00b0ff 100%);
    color: #000000;
    font-weight: 700;
    font-family: 'Outfit', sans-serif;
    padding: 10px 24px;
    border-radius: 30px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px var(--bet-primary-glow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .btn-new-bet:hover {
    transform: scale(1.03);
    box-shadow: 0 6px 20px rgba(0, 230, 118, 0.4);
    color: #000000;
  }

  /* Bet Cards Grid */
  .bets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
  }

  .bet-card-item {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 16px;
    overflow: hidden;
    transition: transform 0.2s ease, border-color 0.2s ease;
  }

  /* Custom Searchable Dropdown Combobox */
  .custom-combobox-wrapper {
    position: relative;
  }
  .custom-combobox-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1060;
    max-height: 240px;
    overflow-y: auto;
    background-color: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
    margin-top: 4px;
  }
  .custom-combobox-item {
    padding: 10px 14px;
    font-size: 0.88rem;
    color: #c9d1d9;
    cursor: pointer;
    border-bottom: 1px solid #21262d;
    transition: background-color 0.15s ease, color 0.15s ease;
  }
  .custom-combobox-item:last-child {
    border-bottom: none;
  }
  .custom-combobox-item:hover, .custom-combobox-item.highlighted {
    background-color: #21262d;
    color: var(--bet-primary, #00e676);
  }
  .custom-combobox-item.selected {
    background-color: rgba(0, 230, 118, 0.15);
    color: #00e676;
    font-weight: 600;
  }
  .custom-combobox-empty {
    padding: 12px 14px;
    font-size: 0.85rem;
    color: #8b949e;
    text-align: center;
  }
  
  .bet-card-item {
    display: flex;
    flex-direction: column;
  }

  /* Slide View Toggle Switcher */
  .view-toggle-pill {
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 30px;
    padding: 3px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
  }
  .view-toggle-btn {
    background: transparent;
    border: none;
    color: var(--bet-text-muted);
    padding: 6px 14px;
    border-radius: 25px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .view-toggle-btn.active {
    background: var(--bet-primary);
    color: #000000;
    font-weight: 700;
    box-shadow: 0 2px 10px rgba(0, 230, 118, 0.35);
  }

  /* Modo Lista (List View Layout) */
  .bets-grid.list-view {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .bets-grid.list-view .bet-card-item {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-radius: 12px;
    flex-wrap: wrap;
    gap: 20px;
  }
  .bets-grid.list-view .bet-card-header {
    background: transparent;
    padding: 0;
    border-bottom: none;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    min-width: 200px;
  }
  .bets-grid.list-view .match-time {
    align-items: flex-start !important;
    text-align: left !important;
    margin-left: 0 !important;
  }
  .bets-grid.list-view .bet-card-body {
    padding: 0;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
    min-width: 320px;
  }
  .bets-grid.list-view .market-info {
    margin-bottom: 0;
    flex: 1;
    min-width: 180px;
  }
  .bets-grid.list-view .values-grid {
    margin-bottom: 0;
    grid-template-columns: auto auto;
    gap: 16px;
  }
  .bets-grid.list-view .bet-card-footer {
    background: transparent;
    padding: 0;
    border-top: none;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-left: auto;
  }
  .bets-grid.list-view .actions-primary {
    display: flex;
    gap: 8px;
  }
  .bets-grid.list-view .actions-secondary {
    margin-top: 0;
    border-top: none;
    padding-top: 0;
  }

  .bet-card-item:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 230, 118, 0.4);
  }

  .bet-card-header {
    background: #1c2128;
    padding: 16px 20px;
    border-bottom: 1px solid var(--bet-card-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .match-teams {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 1.1rem;
    color: #ffffff;
  }

  .match-time {
    font-size: 0.8rem;
    color: var(--bet-text-muted);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
  }

  .bet-card-body {
    padding: 20px;
    flex: 1;
  }

  .market-info {
    background: #0d1117;
    border: 1px solid #21262d;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .market-name {
    font-size: 0.85rem;
    color: var(--bet-text-muted);
    font-weight: 500;
  }

  .palpite-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #ffffff;
    margin-top: 2px;
  }

  .odd-badge {
    background: var(--bet-primary);
    color: #000000;
    font-weight: 800;
    font-size: 1.1rem;
    padding: 6px 14px;
    border-radius: 8px;
    font-family: 'Outfit', sans-serif;
  }

  .values-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
  }

  .val-box {
    background: #1c2128;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #282e38;
  }

  .val-box.highlight {
    background: rgba(0, 230, 118, 0.06);
    border-color: rgba(0, 230, 118, 0.2);
  }

  .val-title {
    font-size: 0.75rem;
    color: var(--bet-text-muted);
    text-transform: uppercase;
    font-weight: 600;
  }

  .val-amount {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 1.25rem;
    color: #ffffff;
    margin-top: 2px;
  }

  .val-amount.primary { color: var(--bet-primary); }

  .status-tag {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
  }

  .status-Pendente { background: rgba(255, 214, 0, 0.15); color: var(--bet-gold); border: 1px solid rgba(255, 214, 0, 0.3); }
  .status-Ganha    { background: rgba(0, 230, 118, 0.15); color: var(--bet-primary); border: 1px solid rgba(0, 230, 118, 0.3); }
  .status-Meio-Ganha, .status-Meio_Ganha { background: rgba(76, 175, 80, 0.2); color: #81c784; border: 1px solid rgba(76, 175, 80, 0.4); }
  .status-Meio-Perdida, .status-Meio_Perdida { background: rgba(255, 152, 0, 0.2); color: #ffb74d; border: 1px solid rgba(255, 152, 0, 0.4); }
  .status-Perdida  { background: rgba(255, 82, 82, 0.15); color: var(--bet-danger); border: 1px solid rgba(255, 82, 82, 0.3); }
  .status-ANULADA, .status-Anulada { background: rgba(148, 163, 184, 0.2); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.4); }
  .status-Cashout  { background: rgba(0, 176, 255, 0.15); color: var(--bet-accent); border: 1px solid rgba(0, 176, 255, 0.3); }

  .bet-card-footer {
    padding: 16px 20px;
    background: #14181f;
    border-top: 1px solid var(--bet-card-border);
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .actions-primary {
    display: flex;
    gap: 10px;
  }

  .btn-cashout {
    flex: 1;
    background: linear-gradient(135deg, #ff9100 0%, #ff3d00 100%);
    color: #ffffff;
    font-weight: 800;
    font-family: 'Outfit', sans-serif;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s, opacity 0.2s;
    font-size: 0.95rem;
  }

  .btn-cashout:hover {
    transform: scale(1.02);
    opacity: 0.95;
  }

  .btn-reapostar {
    background: #21262d;
    color: #ffffff;
    border: 1px solid #30363d;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    font-size: 0.85rem;
  }

  .btn-reapostar:hover {
    background: #30363d;
  }

  .actions-secondary {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .btn-icon-link {
    background: transparent;
    border: none;
    color: var(--bet-text-muted);
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: color 0.2s;
  }

  .btn-icon-link:hover {
    color: #ffffff;
  }

  .btn-icon-link.danger:hover {
    color: var(--bet-danger);
  }

  /* Locked overlay CSS */
  .locked-container {
    position: relative;
  }

  .blur-overlay {
    filter: blur(6px);
    pointer-events: none;
    user-select: none;
    opacity: 0.4;
  }

  .lock-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(13, 17, 23, 0.85);
    backdrop-filter: blur(10px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .lock-modal-card {
    background: #161b22;
    border: 1px solid rgba(255, 82, 82, 0.4);
    border-radius: 24px;
    padding: 40px;
    max-width: 540px;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 30px rgba(255, 82, 82, 0.15);
  }

  .lock-icon-circle {
    width: 80px;
    height: 80px;
    background: rgba(255, 82, 82, 0.1);
    border: 2px solid var(--bet-danger);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px auto;
    color: var(--bet-danger);
    font-size: 2.2rem;
  }

  .lock-modal-card h2 {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.8rem;
    color: #ffffff;
    margin-bottom: 12px;
  }

  .lock-modal-card p {
    color: var(--bet-text-muted);
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 28px;
  }

  .btn-recharge {
    background: linear-gradient(135deg, #00e676 0%, #00b0ff 100%);
    color: #000000;
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
    padding: 14px 32px;
    border-radius: 50px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 6px 20px var(--bet-primary-glow);
    transition: transform 0.2s, box-shadow 0.2s;
  }

  .btn-recharge:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 230, 118, 0.4);
    color: #000000;
  }

  /* Modal Form Styling */
  .modal-dark .modal-dialog {
    max-width: 580px;
    width: 92%;
  }

  .modal-dark .modal-content {
    background-color: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    color: var(--bet-text-main);
    border-radius: 16px;
  }

  .modal-dark .modal-header {
    border-bottom: 1px solid var(--bet-card-border);
  }

  .modal-dark .modal-footer {
    border-top: 1px solid var(--bet-card-border);
  }

  .modal-dark .form-control, .modal-dark .form-select {
    background-color: #0d1117;
    border: 1px solid #30363d;
    color: #ffffff;
  }

  .modal-dark .form-control:focus, .modal-dark .form-select:focus {
    border-color: var(--bet-primary);
    box-shadow: 0 0 0 0.25rem var(--bet-primary-glow);
  }
</style>

<!-- MODAL BLOQUEIO SE NÃO TIVER TOKENS -->
<?php if (!$hasTokens): ?>
  <div class="lock-modal-backdrop">
    <div class="lock-modal-card">
      <div class="lock-icon-circle">
        <i class="bi bi-lock-fill"></i>
      </div>
      <h2><?= lang('App.access_restricted_title') ?></h2>
      <p>
        <?= lang('App.access_restricted_msg') ?>
        <br><br>
        Seu saldo atual: <strong class="text-danger">0 Tokens</strong>. Recarreague agora mesmo para liberar o painel completo de palpites e acompanhamento.
      </p>
      <a href="<?= base_url('subscription/buyGrokCredits') ?>" class="btn-recharge">
        <i class="bi bi-lightning-charge-fill"></i> Recarregar Tokens de Consulta (R$ 10,00)
      </a>
    </div>
  </div>
<?php endif; ?>

<div class="bet-container <?= !$hasTokens ? 'locked-container blur-overlay' : '' ?>">

  <!-- Header -->
  <div class="bet-header">
    <div class="bet-title">
      <h1><i class="bi bi-ticket-detailed-fill"></i> <?= lang('App.my_bets') ?></h1>
      <div class="bet-subtitle"><?= lang('App.bets_subtitle') ?></div>
    </div>
    <div>
      <?php if ($hasTokens): ?>
        <div class="token-badge">
          <i class="bi bi-coin"></i> <?= sprintf(lang('App.tokens_available'), $userCredits) ?>
        </div>
      <?php else: ?>
        <div class="token-badge token-badge-locked">
          <i class="bi bi-exclamation-triangle-fill"></i> <?= lang('App.tokens_insufficient') ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- DAG 23:00 Notification & Manual Trigger Bar -->
  <div style="background: rgba(0, 176, 255, 0.08); border: 1px solid rgba(0, 176, 255, 0.25); border-radius: 14px; padding: 16px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    <div class="d-flex align-items-center gap-3">
      <div style="width: 42px; height: 42px; background: rgba(0, 176, 255, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--bet-accent); font-size: 1.3rem;">
        <i class="bi bi-clock-history"></i>
      </div>
      <div>
        <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;"><?= lang('App.daily_processing_title') ?></div>
        <div style="font-size: 0.82rem; color: var(--bet-text-muted);"><?= lang('App.daily_processing_desc') ?></div>
      </div>
    </div>
    <button class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold d-flex align-items-center gap-2" onclick="triggerProcessarDAG()">
      <i class="bi bi-arrow-clockwise"></i> <?= lang('App.trigger_dag_now') ?>
    </button>
  </div>

  <!-- Cards Resumo Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-cash-stack"></i> <?= lang('App.total_staked') ?></div>
      <div class="stat-value" id="topTotalApostado">R$ <?= number_format($resumo['total_apostado'] ?? 0, 2, ',', '.') ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-trophy"></i> <?= lang('App.gross_return') ?></div>
      <div class="stat-value primary" id="topRetornoBruto">R$ <?= number_format($resumo['ganhos_totais'] ?? 0, 2, ',', '.') ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-calculator"></i> <?= lang('App.net_balance') ?></div>
      <?php $saldoTop = (float)($resumo['saldo_liquido'] ?? 0); ?>
      <div class="stat-value <?= ($saldoTop > 0) ? 'primary' : (($saldoTop < 0) ? 'text-danger' : 'gold') ?>" id="topSaldoLiquido">
        <?= ($saldoTop > 0 ? '+' : '') ?>R$ <?= number_format($saldoTop, 2, ',', '.') ?>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-list-check"></i> <?= lang('App.total_bets') ?></div>
      <div class="stat-value gold" id="topTotalApostas"><?= $resumo['total_apostas'] ?? 0 ?></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="bet-toolbar">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <!-- Status Filters -->
      <div class="bet-filters">
        <?php
          $totalApostasCount = count($apostas);
          $calcPctBadge = function($label, $count) use ($totalApostasCount) {
            if ($totalApostasCount <= 0) {
              $pctStr = '0%';
            } else {
              $pct = ($count / $totalApostasCount) * 100;
              $pctStr = ($pct == (int)$pct) ? number_format($pct, 0) . '%' : number_format($pct, 1, ',', '.') . '%';
            }
            return "{$label} ({$count} - {$pctStr})";
          };
        ?>
        <button class="filter-btn active" id="btnFilterAll" onclick="filterBets('all', this)"><?= $calcPctBadge(lang('App.all_statuses'), $totalApostasCount) ?></button>
        <button class="filter-btn" id="btnFilterPendente" onclick="filterBets('Pendente', this)"><?= $calcPctBadge(lang('App.pending'), $resumo['pendentes'] ?? 0) ?></button>
        <button class="filter-btn" id="btnFilterGanha" onclick="filterBets('Ganha', this)"><?= $calcPctBadge(lang('App.won'), $resumo['ganhas'] ?? 0) ?></button>
        <button class="filter-btn" id="btnFilterMeioGanha" onclick="filterBets('Meio Ganha', this)"><?= $calcPctBadge(lang('App.half_won'), $resumo['meio_ganhas'] ?? 0) ?></button>
        <button class="filter-btn" id="btnFilterAnulada" onclick="filterBets('ANULADA', this)"><?= $calcPctBadge(lang('App.refunded'), $resumo['anuladas'] ?? 0) ?></button>
        <button class="filter-btn" id="btnFilterMeioPerdida" onclick="filterBets('Meio Perdida', this)"><?= $calcPctBadge(lang('App.half_lost'), $resumo['meio_perdidas'] ?? 0) ?></button>
        <button class="filter-btn" id="btnFilterPerdida" onclick="filterBets('Perdida', this)"><?= $calcPctBadge(lang('App.lost'), $resumo['perdidas'] ?? 0) ?></button>
        <button class="filter-btn" id="btnFilterCashout" onclick="filterBets('Cashout', this)"><?= $calcPctBadge('Cashout', $resumo['cashouts'] ?? 0) ?></button>
      </div>

      <!-- Filtro por Mercado de Simulações de Apostas -->
      <div class="d-flex align-items-center gap-2 bg-dark px-3 py-1.5 rounded-3 border border-secondary" style="font-size: 0.85rem;">
        <span class="text-light fw-semibold d-flex align-items-center gap-1"><i class="bi bi-shop text-primary"></i> <?= lang('App.market') ?>:</span>
        <select id="betMarketFilterSelect" class="form-select form-select-sm bg-dark text-white border-secondary fw-semibold" onchange="applyBetFilters()" style="width: auto; cursor: pointer; min-width: 180px;">
          <option value="all" selected><?= lang('App.all_markets') ?></option>
          <option value="handicap">⚽ <?= lang('App.goals_market_handicap') ?></option>
          <option value="cartoes">🟨 <?= lang('App.cards_trend_poisson') ?></option>
        </select>
      </div>

      <!-- Filtro de Período (Atalhos + Datas) -->
      <div class="d-flex align-items-center gap-2 bg-dark px-3 py-1.5 rounded-3 border border-secondary flex-wrap" style="font-size: 0.85rem;">
        <span class="text-light fw-semibold d-flex align-items-center gap-1"><i class="bi bi-calendar-range text-info"></i> <?= lang('App.period') ?>:</span>
        <select id="betDatePresetSelect" class="form-select form-select-sm bg-dark text-info border-secondary fw-semibold" style="width: auto; cursor: pointer; min-width: 155px;" onchange="setBetDatePreset(this.value)" title="<?= lang('App.period_shortcut') ?>">
          <option value="custom">📅 <?= lang('App.custom') ?></option>
          <option value="today">⚡ <?= lang('App.today') ?></option>
          <option value="yesterday">⏪ <?= lang('App.yesterday') ?></option>
          <option value="7days">🗓️ <?= lang('App.last_7_days') ?></option>
          <option value="15days">🗓️ <?= lang('App.last_15_days') ?></option>
          <option value="1month">📅 <?= lang('App.last_month') ?></option>
          <option value="trimestre">📊 <?= lang('App.quarter') ?></option>
          <option value="semestre">📈 <?= lang('App.semester') ?></option>
          <option value="all" selected>♾️ <?= lang('App.all_period') ?></option>
        </select>
        <input type="date" id="betStartDateInput" class="form-control form-control-sm bg-dark text-white border-secondary" style="width: 135px;" onchange="onManualDateChange()" title="<?= lang('App.start_date') ?>">
        <span class="text-light-50 small"><?= lang('App.to') ?></span>
        <input type="date" id="betEndDateInput" class="form-control form-control-sm bg-dark text-white border-secondary" style="width: 135px;" onchange="onManualDateChange()" title="<?= lang('App.end_date') ?>">
        <button class="btn btn-sm btn-outline-secondary border-0 text-light-50 p-1" onclick="clearDateFilter()" title="<?= lang('App.clear') ?>"><i class="bi bi-x-circle-fill"></i></button>
      </div>

      <!-- Resumo Financeiro Calculado (Filtro por Período / Seleção) -->
      <div id="calculatedSummaryWidget" class="d-flex align-items-center gap-3 bg-dark px-3 py-1.5 rounded-3 border border-secondary flex-wrap" style="font-size: 0.82rem; background: rgba(15, 23, 42, 0.9) !important; border-color: rgba(56, 189, 248, 0.35) !important;">
        <div class="d-flex align-items-center gap-1" title="Soma do valor investido nas simulações de apostas do período/filtro">
          <i class="bi bi-cash-coin text-warning"></i>
          <span class="text-light-50"><?= lang('App.total_staked') ?>:</span>
          <strong id="calcTotalApostado" class="text-white">R$ 0,00</strong>
        </div>

        <div class="d-flex align-items-center gap-1" title="Soma dos ganhos em simulações de apostas vencidas ou cashouts (Retorno Bruto)">
          <i class="bi bi-graph-up-arrow text-success"></i>
          <span class="text-light-50"><?= lang('App.total_won_label') ?>:</span>
          <strong id="calcTotalGanho" class="text-success">R$ 0,00</strong>
        </div>

        <div class="d-flex align-items-center gap-1" title="Soma do ganho obtido sobre as odds ((Simulação × Odd) - Simulação)">
          <i class="bi bi-piggy-bank-fill" style="color: #00e676;"></i>
          <span class="text-light-50"><?= lang('App.odds_gain_label') ?>:</span>
          <strong id="calcTotalLucro" style="color: #00e676; font-weight: 700;">R$ 0,00</strong>
        </div>

        <div class="d-flex align-items-center gap-1" title="Soma dos valores perdidos nas simulações de apostas do período/filtro">
          <i class="bi bi-graph-down-arrow text-danger"></i>
          <span class="text-light-50"><?= lang('App.total_loss_label') ?>:</span>
          <strong id="calcTotalPerda" class="text-danger">R$ 0,00</strong>
        </div>

        <div class="d-flex align-items-center gap-1 border-start border-secondary ps-2 ms-1" title="Fórmula: Saldo Líquido = Total Retorno Bruto - Total Apostado (Simulações Liquidadas)">
          <i class="bi bi-calculator text-info"></i>
          <span class="text-light-50"><?= lang('App.net_balance') ?>:</span>
          <strong id="calcSaldoLiquido" class="text-info fw-bold">R$ 0,00</strong>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-3 flex-wrap">
      <!-- Botão Slide para alternar entre Lista e Cards -->
      <div class="view-toggle-pill" title="Alternar Modo de Exibição">
        <button type="button" class="view-toggle-btn active" id="btnViewList" onclick="setViewMode('list')" title="Exibir em Lista">
          <i class="bi bi-list-ul"></i> <?= lang('App.list') ?>
        </button>
        <button type="button" class="view-toggle-btn" id="btnViewGrid" onclick="setViewMode('grid')" title="Exibir em Cards">
          <i class="bi bi-grid-fill"></i> <?= lang('App.cards') ?>
        </button>
      </div>

      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="betSearchInput" placeholder="<?= lang('App.search_bet_placeholder') ?>" onkeyup="applyBetFilters()">
      </div>
      
      <a href="<?= base_url('apostas/relatorio-top5') ?>" target="_blank" class="btn btn-outline-warning rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-2" style="border-width: 2px; text-decoration: none;">
        <i class="bi bi-trophy-fill text-warning"></i> <?= lang('App.top5_report') ?>
      </a>

      <a href="<?= base_url('apostas/relatorio-ia-perdas') ?>" class="btn btn-outline-danger rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-2" style="border-width: 2px; text-decoration: none;">
        <i class="bi bi-shield-x text-danger"></i> <?= lang('App.ia_loss_report') ?>
      </a>

      <a href="<?= base_url('apostas/analise-desempenho') ?>" target="_blank" class="btn btn-outline-info rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-2" style="border-width: 2px; text-decoration: none;" title="Abrir Análise de Desempenho em nova aba">
        <i class="bi bi-graph-up-arrow text-info"></i> <?= lang('App.perf_analysis_title') ?>
      </a>

      <button class="btn-new-bet" data-bs-toggle="modal" data-bs-target="#newBetModal">
        <i class="bi bi-plus-lg"></i> <?= lang('App.new_bet') ?>
      </button>
    </div>
  </div>

  <!-- Bet Cards / List Container -->
  <div class="bets-grid list-view" id="betsContainer">
    <?php if (empty($apostas)): ?>
      <div class="w-100 text-center py-5" style="grid-column: 1 / -1; color: var(--bet-text-muted);">
        <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
        <h5><?= lang('App.no_bets_registered_yet') ?></h5>
        <p><?= lang('App.click_new_bet_above') ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($apostas as $aposta): ?>
        <?php 
          $itemDate = !empty($aposta->data_brt_dia) ? $aposta->data_brt_dia : (!empty($aposta->data_hora_jogo) ? formatBrtDate($aposta->data_hora_jogo, 'Y-m-d') : formatBrtDate($aposta->criado_em, 'Y-m-d'));
          $itemCreatedDate = !empty($aposta->criado_em) ? formatBrtDate($aposta->criado_em, 'Y-m-d') : $itemDate;
          $displayMatchTime = !empty($aposta->data_hora_jogo) ? formatBrtDate($aposta->data_hora_jogo, 'd/m \à\s H:i') : 'Hoje';
          $displayCreatedTime = !empty($aposta->criado_em) ? formatBrtDate($aposta->criado_em, 'd/m/Y \à\s H:i') : null;
        ?>
        <div class="bet-card-item" id="aposta-card-<?= $aposta->id ?>" data-status="<?= htmlspecialchars($aposta->status) ?>" data-mercado="<?= htmlspecialchars($aposta->mercado) ?>" data-palpite="<?= htmlspecialchars($aposta->palpite) ?>" data-date="<?= $itemDate ?>" data-created-date="<?= $itemCreatedDate ?>" data-valor="<?= (float)($aposta->valor_aposta ?? 0) ?>" data-odd="<?= (float)($aposta->odd ?? 0) ?>" data-ganho="<?= (float)($aposta->ganhos_potenciais ?? 0) ?>" data-cashout="<?= (float)($aposta->cash_out ?? 0) ?>" data-search="<?= strtolower(htmlspecialchars($aposta->time_casa . ' ' . $aposta->time_fora . ' ' . $aposta->mercado . ' ' . $aposta->palpite)) ?>">
          
          <div class="bet-card-header">
            <div class="match-teams d-flex align-items-center gap-2 flex-wrap">
              <span><?= htmlspecialchars($aposta->time_casa) ?> <span style="color: var(--bet-primary); margin: 0 4px;">vs</span> <?= htmlspecialchars($aposta->time_fora) ?></span>
              
              <?php 
                $placarExibir = null;
                if (isset($aposta->goals_home) && isset($aposta->goals_away) && $aposta->goals_home !== null && $aposta->goals_away !== null) {
                  $placarExibir = $aposta->goals_home . ' x ' . $aposta->goals_away;
                } elseif (!empty($aposta->resultado_detalhado) && preg_match('/Placar:\s*(\d+\s*x\s*\d+|\d+x\d+)/i', $aposta->resultado_detalhado, $mPlac)) {
                  $placarExibir = str_replace('x', ' x ', str_replace(' ', '', $mPlac[1]));
                }
              ?>

              <?php if (!empty($placarExibir)): ?>
                <span class="badge bg-warning text-dark border border-warning px-2.5 py-1 font-monospace fw-bold" style="font-size: 0.81rem; letter-spacing: 0.5px;" title="Placar Final da Partida (FT)">
                  ⚽ Placar: <?= htmlspecialchars($placarExibir) ?>
                </span>
              <?php endif; ?>

              <?php if (!empty($placarExibir)): ?>
                <span class="badge bg-warning text-dark border border-warning px-2.5 py-1 font-monospace fw-bold" style="font-size: 0.81rem; letter-spacing: 0.5px;" title="Placar Final da Partida (FT)">
                  ⚽ Placar: <?= htmlspecialchars($placarExibir) ?>
                </span>
              <?php endif; ?>

              <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-2 py-1" style="font-size: 0.75rem;" title="<?= lang('App.bet_simulation_registered') ?>">
                🂠 <?= lang('App.bet_simulation_registered') ?>
              </span>
              <?php if (!empty($aposta->fixture_id)): ?>
                <a href="<?= base_url('football-trends?fixture_id=' . $aposta->fixture_id) ?>#card-<?= $aposta->fixture_id ?>" 
                   class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 text-decoration-none px-2 py-1" 
                   style="font-size: 0.75rem; transition: all 0.2s ease;" 
                   title="<?= lang('App.origin_card') ?>">
                  <i class="bi bi-box-arrow-up-right me-1"></i> <?= lang('App.origin_card') ?>
                </a>
              <?php endif; ?>
            </div>
            <div class="match-time flex-shrink-0 d-flex flex-column align-items-end text-end ms-auto" style="font-size: 0.78rem; gap: 2px;">
              <div title="<?= lang('App.match') ?>" style="color: var(--bet-text-muted);">
                <i class="bi bi-calendar-event me-1"></i> <?= lang('App.match') ?>: <strong><?= $displayMatchTime ?></strong>
              </div>
              <?php if (!empty($displayCreatedTime)): ?>
                <div title="<?= lang('App.created') ?>" style="color: var(--bet-accent); font-weight: 500;">
                  <i class="bi bi-clock-history me-1"></i> <?= lang('App.created') ?>: <strong><?= $displayCreatedTime ?></strong>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="bet-card-body">
            <div class="market-info">
              <div>
                <div class="market-name"><?= htmlspecialchars($aposta->mercado) ?></div>
                <div class="palpite-name"><?= htmlspecialchars($aposta->palpite) ?></div>
              </div>
              <div class="odd-badge"><?= number_format($aposta->odd, 2) ?></div>
            </div>

            <div class="values-grid">
              <div class="val-box">
                <div class="val-title"><?= lang('App.bet_simulation_label') ?></div>
                <div class="val-amount">R$ <?= number_format($aposta->valor_aposta, 2, ',', '.') ?></div>
              </div>
              <div class="val-box highlight">
                <div class="val-title"><?= lang('App.potential_earnings_label') ?></div>
                <div class="val-amount primary">R$ <?= number_format($aposta->ganhos_potenciais, 2, ',', '.') ?></div>
              </div>
            </div>

            <?php 
              $detalhadoExibir = $aposta->resultado_detalhado ?? '';
              if (empty($detalhadoExibir) && !empty($placarExibir) && $aposta->status !== 'Pendente') {
                $detalhadoExibir = "FT | Placar: {$placarExibir} | Status: {$aposta->status}";
              }
              $statusLabelMap = [
                'Pendente'     => lang('App.pending'),
                'Ganha'        => lang('App.won'),
                'Meio Ganha'   => lang('App.half_won'),
                'ANULADA'      => lang('App.refunded'),
                'Meio Perdida' => lang('App.half_lost'),
                'Perdida'      => lang('App.lost'),
                'Cashout'      => 'Cashout',
              ];
              $displayStatusLabel = mb_strtoupper($statusLabelMap[$aposta->status] ?? $aposta->status, 'UTF-8');
            ?>

            <?php if (!empty($detalhadoExibir)): ?>
              <div style="background: rgba(255,255,255,0.04); border: 1px dashed rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; font-size: 0.78rem; color: #e2e8f0; display: flex; align-items: center; gap: 6px;">
                <i class="bi bi-info-circle-fill text-info"></i>
                <span><?= htmlspecialchars($detalhadoExibir) ?></span>
              </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <span class="status-tag status-<?= str_replace(' ', '-', $aposta->status) ?>"><?= htmlspecialchars($displayStatusLabel) ?></span>
              <div class="d-flex align-items-center gap-3 flex-wrap">
                <span style="font-size: 0.8rem; color: var(--bet-text-muted); font-weight: 600; text-transform: uppercase;">
                  <?= lang('App.type') ?>: <?= htmlspecialchars($aposta->tipo) ?>
                </span>
                <?php if (!empty($displayCreatedTime)): ?>
                  <span style="font-size: 0.78rem; color: var(--bet-accent); font-weight: 500;" title="<?= lang('App.created_at') ?>">
                    <i class="bi bi-clock-history me-1"></i> <?= lang('App.created_at') ?>: <?= $displayCreatedTime ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <?php if (!empty($aposta->status_gatekeeper) && $aposta->status_gatekeeper !== 'NAO_ANALISADO'): ?>
              <div class="mt-2 d-flex align-items-center gap-2 flex-wrap" style="font-size: 0.78rem;">
                <?php if ($aposta->status_gatekeeper === 'APROVADO'): ?>
                  <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 px-2 py-1">
                    <i class="bi bi-shield-check me-1"></i> Gatekeeper: <?= lang('App.ev_approved') ?>
                  </span>
                <?php elseif ($aposta->status_gatekeeper === 'NO_BET'): ?>
                  <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 px-2 py-1">
                    <i class="bi bi-shield-x me-1"></i> Gatekeeper: NO_BET
                  </span>
                <?php endif; ?>

                <?php if (!empty($aposta->odd_justa)): ?>
                  <span class="badge px-2.5 py-1 fw-bold" style="background-color: #00b0ff; color: #000000;" title="<?= lang('App.fair_odd') ?>">
                    <i class="bi bi-calculator-fill me-1"></i> <?= lang('App.fair_odd') ?>: <?= number_format($aposta->odd_justa, 2) ?>
                  </span>
                <?php endif; ?>

                <?php if (!empty($aposta->ev_percentual)): ?>
                  <span class="badge <?= $aposta->ev_percentual > 0 ? 'bg-success text-white' : 'bg-danger text-white' ?> px-2 py-1">
                    EV: <?= ($aposta->ev_percentual > 0 ? '+' : '') . number_format($aposta->ev_percentual, 1) ?>%
                  </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="bet-card-footer">
            <div class="actions-primary">
              <button class="btn-cashout" onclick="handleCashout(<?= $aposta->id ?>, <?= $aposta->cash_out ?? $aposta->valor_aposta ?>)">
                CASH OUT R$ <?= number_format($aposta->cash_out ?? $aposta->valor_aposta, 2, ',', '.') ?>
              </button>
              <button class="btn-reapostar" onclick="handleReapostar(<?= $aposta->id ?>)" title="<?= lang('App.rebet') ?>">
                <i class="bi bi-arrow-repeat"></i> <?= lang('App.rebet') ?>
              </button>
            </div>

            <div class="actions-secondary">
              <?php if (!empty($aposta->fixture_id)): ?>
                <a href="<?= base_url('football-trends?fixture_id=' . $aposta->fixture_id) ?>#card-<?= $aposta->fixture_id ?>" 
                   class="btn-icon-link text-warning fw-semibold text-decoration-none" 
                   title="<?= lang('App.origin_card') ?>">
                  <i class="bi bi-box-arrow-up-right me-1"></i> <?= lang('App.origin_card') ?>
                </a>
              <?php else: ?>
                <a href="<?= base_url('football-trends?search=' . urlencode($aposta->time_casa)) ?>" 
                   class="btn-icon-link text-muted text-decoration-none" 
                   title="<?= lang('App.origin_card') ?>">
                  <i class="bi bi-search me-1"></i> <?= lang('App.origin_card') ?>
                </a>
              <?php endif; ?>

              <button class="btn-icon-link" onclick="shareBet('<?= htmlspecialchars(addslashes($aposta->time_casa), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($aposta->time_fora), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($aposta->palpite), ENT_QUOTES, 'UTF-8') ?>', '<?= number_format($aposta->odd, 2) ?>')">
                <i class="bi bi-share"></i> <?= lang('App.share') ?>
              </button>

              <div class="d-flex gap-2">
                <button type="button" class="btn-icon-link" 
                        data-id="<?= $aposta->id ?>" 
                        data-home="<?= htmlspecialchars($aposta->time_casa ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                        data-away="<?= htmlspecialchars($aposta->time_fora ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                        data-mercado="<?= htmlspecialchars($aposta->mercado ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                        data-palpite="<?= htmlspecialchars($aposta->palpite ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                        data-odd="<?= number_format((float)($aposta->odd ?? 1.0), 2, '.', '') ?>" 
                        data-valor="<?= number_format((float)($aposta->valor_aposta ?? 10.0), 2, '.', '') ?>" 
                        data-tipo="<?= htmlspecialchars($aposta->tipo ?? 'Simples', ENT_QUOTES, 'UTF-8') ?>" 
                        data-status="<?= htmlspecialchars($aposta->status ?? 'Pendente', ENT_QUOTES, 'UTF-8') ?>" 
                        onclick="handleOpenEditModal(this)" 
                        title="<?= lang('App.edit') ?>">
                  <i class="bi bi-pencil"></i> <?= lang('App.edit') ?>
                </button>
                <button class="btn-icon-link danger" onclick="handleDelete(<?= $aposta->id ?>)" title="<?= lang('App.delete') ?>">
                  <i class="bi bi-trash"></i> <?= lang('App.delete') ?>
                </button>
              </div>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<!-- MODAL CRIAR APOSTA -->
<div class="modal fade modal-dark" id="newBetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-plus-circle text-success me-2"></i> <?= lang('App.add_new_bet_simulation') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="newBetForm" onsubmit="submitNewBet(event)">
        <div class="modal-body">
          
          <!-- Tarja Vermelha Chamativa de Alerta de Risco por Abstenção/Bloqueio da IA -->
          <div id="xgWarningBanner" class="alert alert-danger d-flex align-items-center gap-3 mb-3" style="display: none; background: rgba(239, 68, 68, 0.18); border: 2px solid #ef4444; color: #fca5a5; border-radius: 12px; padding: 14px 16px; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.25);">
            <i class="bi bi-slash-circle-fill fs-2 text-danger flex-shrink-0"></i>
            <div>
              <strong style="color: #f87171; font-size: 0.95rem; display: block; margin-bottom: 4px;">🚫 ENTRADA BLOQUEADA: ABSTENÇÃO DA IA POR GESTÃO DE RISCO</strong>
              <div style="font-size: 0.82rem; color: #fca5a5; line-height: 1.45;">
                Esta partida foi classificada como <strong style="color: #ffffff;">Sem Entrada (Abstenção)</strong> pela inteligência estatística por ausência de dados seguros de gols/xG.<br>
                <span style="color: #ef4444; font-weight: 800; text-transform: uppercase; display: block; margin-top: 6px; font-size: 0.82rem; letter-spacing: 0.3px;">
                  💡 GESTÃO DE RISCO: Para proteger sua banca, a criação de simulações de apostas no mercado de Handicap Asiático para este jogo está desabilitada pela automação.
                </span>
              </div>
            </div>
          </div>
          
          <div class="mb-3 custom-combobox-wrapper" id="fixtureComboboxContainer">
            <label class="form-label text-muted small fw-bold"><?= lang('App.link_to_match_optional') ?></label>
            <div class="input-group">
              <span class="input-group-text bg-dark border-secondary text-muted"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control bg-dark text-white border-secondary" id="fixtureSearchInput" placeholder="Digite para filtrar partidas (ex: Time, Liga, Data)..." autocomplete="off" oninput="filterFixtureSelect(this.value)" onfocus="openFixtureDropdown()" onclick="openFixtureDropdown()">
              <button class="btn btn-outline-secondary border-secondary text-muted" type="button" onclick="clearFixtureSelection()" title="<?= lang('App.clear') ?>"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <div id="fixtureDropdownList" class="custom-combobox-dropdown" style="display: none;"></div>

            <select class="form-select" id="fixtureSelect" onchange="autofillFixture(this)" style="display: none;">
              <option value="">-- Selecione ou digite manualmente abaixo --</option>
              <?php foreach ($fixtures as $fix): ?>
                <option value="<?= $fix->fixture_id ?>" 
                        data-home="<?= htmlspecialchars($fix->home_team) ?>" 
                        data-away="<?= htmlspecialchars($fix->away_team) ?>" 
                        data-date="<?= $fix->fixture_date ?>" 
                        data-palpite-cards="<?= htmlspecialchars($fix->suggested_palpite_cards ?? 'Menos de 5.5') ?>"
                        data-palpite-ah="<?= htmlspecialchars($fix->suggested_palpite_ah ?? 'Handicap 0.0 (Empate Anula)') ?>"
                        data-palpite="<?= htmlspecialchars($fix->suggested_palpite_cards ?? 'Menos de 5.5') ?>"
                        data-ah-suggestion="<?= htmlspecialchars($fix->ah_suggestion ?? '') ?>"
                        data-ah-confidence="<?= number_format($fix->ah_confidence_val ?? 0, 1) ?>"
                        data-ah-max-score="<?= ($fix->is_max_ah_score ?? false) ? '1' : '0' ?>"
                        data-xg-home="<?= number_format($fix->xg_home ?? 0, 2) ?>"
                        data-xg-away="<?= number_format($fix->xg_away ?? 0, 2) ?>">
                  <?= date('d/m H:i', strtotime($fix->fixture_date)) ?> | <?= htmlspecialchars($fix->home_team) ?><?= (!empty($fix->home_rank) ? ' (#' . $fix->home_rank . ')' : '') ?> vs <?= htmlspecialchars($fix->away_team) ?><?= (!empty($fix->away_rank) ? ' (#' . $fix->away_rank . ')' : '') ?> (<?= htmlspecialchars($fix->league_name) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div id="fixtureCountBadge" class="form-text text-muted small mt-1"></div>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label text-white"><?= lang('App.home_team') ?> *</label>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="timeCasaInput" readonly required placeholder="Ex: Mirassol" style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
            <div class="col-6 mb-3">
              <label class="form-label text-white"><?= lang('App.away_team') ?> *</label>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="timeForaInput" readonly required placeholder="Ex: Grêmio" style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label text-white"><?= lang('App.bet_simulation_market') ?> *</label>
              <select class="form-select text-white fw-bold bg-dark border-secondary" id="mercadoTypeSelect" onchange="onMercadoTypeChange(this)" style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important;">
                <option value="Total de Cartões" selected>🟨 Cartões (Partida Completa)</option>
                <option value="Cartões - Individual">🟨 Cartões - Individual</option>
                <option value="Handicap Asiático">⚽ Handicap Asiático</option>
                <option value="Escanteios">🚩 Escanteios</option>
                <option value="Resultado Final (1X2)">⚽ Resultado Final (1X2)</option>
                <option value="Ambas Marcam (BTTS)">⚽ Ambas Marcam (BTTS)</option>
              </select>
              <input type="hidden" id="mercadoInput" name="mercado" value="Total de Cartões">
            </div>
            <div class="col-6 mb-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="d-flex align-items-center gap-2">
                  <label class="form-label text-white mb-0"><?= lang('App.tip_label') ?> *</label>
                  <div class="form-check form-switch m-0 d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                    <input class="form-check-input" type="checkbox" role="switch" id="toggleUnlockPalpite" onchange="togglePalpiteLock(this.checked)" style="cursor: pointer; width: 28px; height: 16px;">
                    <label class="form-check-label text-info fw-bold" for="toggleUnlockPalpite" style="cursor: pointer; user-select: none;">
                      <i class="bi bi-unlock-fill me-1"></i><?= lang('App.edit') ?>
                    </label>
                  </div>
                </div>
                <span id="maxScoreBadge" class="badge text-white fw-bold" style="display: none; font-size: 0.72rem; background: linear-gradient(135deg, #f59e0b, #ef4444) !important; padding: 3px 8px; border-radius: 6px; box-shadow: 0 0 10px rgba(245, 158, 11, 0.5); border: 1px solid #fbbf24;">
                  <i class="bi bi-lightning-charge-fill me-1"></i>Max score reached
                </span>
              </div>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="palpiteInput" readonly required placeholder="Ex: Menos de 6.5 ou Time Casa - Menos de 2.5 cartões" oninput="updatePalpiteExplanation()" style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
              <div id="palpiteExplanationBox" style="display:none; font-size: 0.75rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; padding: 6px 10px; margin-top: 6px; color: #e2e8f0;"></div>
            </div>
          </div>

          <!-- Seletor de Time para o Mercado de Cartões -->
          <div class="row mb-3" id="cardTeamTargetRow" style="display: flex;">
            <div class="col-12">
              <label class="form-label text-warning mb-1" style="font-size: 0.8rem; font-weight: 600;">
                <i class="bi bi-card-amber me-1"></i>Alvo dos Cartões (Seleção Individual do Time):
              </label>
              <div class="btn-group w-100" role="group" aria-label="Seleção de Cartões por Time">
                <input type="radio" class="btn-check" name="cardTeamTarget" id="cardTargetJogo" value="jogo" checked onchange="onCardTargetChange('jogo')">
                <label class="btn btn-outline-warning btn-sm fw-bold" for="cardTargetJogo">
                  🌐 Partida Completa (Jogo)
                </label>

                <input type="radio" class="btn-check" name="cardTeamTarget" id="cardTargetCasa" value="casa" onchange="onCardTargetChange('casa')">
                <label class="btn btn-outline-warning btn-sm fw-bold" for="cardTargetCasa" id="cardTargetCasaLabel">
                  🏠 <?= lang('App.home_team') ?>
                </label>

                <input type="radio" class="btn-check" name="cardTeamTarget" id="cardTargetFora" value="fora" onchange="onCardTargetChange('fora')">
                <label class="btn btn-outline-warning btn-sm fw-bold" for="cardTargetFora" id="cardTargetForaLabel">
                  ✈️ <?= lang('App.away_team') ?>
                </label>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-4 mb-3">
              <label class="form-label text-white">Odd *</label>
              <input type="number" step="0.01" min="1.01" class="form-control" id="oddInput" required placeholder="1.50" oninput="calcGanhos()">
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.stake_amount') ?> *</label>
              <input type="number" step="0.01" class="form-control" id="valorInput" required placeholder="10.00" value="10.00" oninput="calcGanhos()">
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.potential_earnings') ?></label>
              <input type="text" class="form-control text-success fw-bold" id="ganhosDisplay" readonly placeholder="R$ 14,70">
            </div>
          </div>

          <div class="row">
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.cashout_value') ?></label>
              <input type="number" step="0.01" class="form-control text-white fw-bold bg-dark border-secondary" id="cashoutInput" readonly placeholder="10.00" value="10.00" style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.type') ?></label>
              <select class="form-select" id="tipoSelect">
                <option value="Simples" selected>Simples</option>
                <option value="Múltipla">Múltipla</option>
                <option value="Criar Aposta"><?= lang('App.add_new_bet_simulation') ?></option>
              </select>
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.status') ?></label>
              <select class="form-select" id="statusSelect">
                <option value="Pendente" selected><?= lang('App.pending') ?></option>
                <option value="Ganha"><?= lang('App.won') ?></option>
                <option value="Meio Ganha"><?= lang('App.half_won') ?></option>
                <option value="ANULADA"><?= lang('App.refunded') ?></option>
                <option value="Meio Perdida"><?= lang('App.half_lost') ?></option>
                <option value="Perdida"><?= lang('App.lost') ?></option>
                <option value="Cashout">Cashout</option>
              </select>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
          <button type="submit" class="btn btn-success fw-bold px-4"><?= lang('App.save_bet_simulation') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR APOSTA -->
<div class="modal fade modal-dark" id="editBetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-weight-bold" style="font-family: 'Outfit', sans-serif;"><i class="bi bi-pencil-square text-info me-2"></i> <?= lang('App.edit_bet_simulation') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editBetForm" onsubmit="submitEditBet(event)">
        <input type="hidden" id="editIdInput">
        <div class="modal-body">

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label text-white"><?= lang('App.home_team') ?> *</label>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="editTimeCasaInput" readonly required style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
            <div class="col-6 mb-3">
              <label class="form-label text-white"><?= lang('App.away_team') ?> *</label>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="editTimeForaInput" readonly required style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
          </div>

          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label text-white"><?= lang('App.market') ?> *</label>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="editMercadoInput" readonly required style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
            <div class="col-6 mb-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <label class="form-label text-white mb-0"><?= lang('App.tip_label') ?> *</label>
                <div class="form-check form-switch m-0 d-flex align-items-center gap-1" style="font-size: 0.72rem;">
                  <input class="form-check-input" type="checkbox" role="switch" id="toggleUnlockEditPalpite" onchange="toggleEditPalpiteLock(this.checked)" style="cursor: pointer; width: 28px; height: 16px;">
                  <label class="form-check-label text-info fw-bold" for="toggleUnlockEditPalpite" style="cursor: pointer; user-select: none;">
                    <i class="bi bi-unlock-fill me-1"></i><?= lang('App.edit') ?>
                  </label>
                </div>
              </div>
              <input type="text" class="form-control text-white fw-bold bg-dark border-secondary" id="editPalpiteInput" readonly required style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
          </div>

          <div class="row">
            <div class="col-4 mb-3">
              <label class="form-label text-white">Odd *</label>
              <input type="number" step="0.01" min="1.01" class="form-control" id="editOddInput" required oninput="calcEditGanhos()">
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.stake_amount') ?> *</label>
              <input type="number" step="0.01" class="form-control" id="editValorInput" required oninput="calcEditGanhos()">
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.potential_earnings') ?></label>
              <input type="text" class="form-control text-success fw-bold" id="editGanhosDisplay" readonly>
            </div>
          </div>

          <div class="row">
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.cashout_value') ?></label>
              <input type="number" step="0.01" class="form-control text-white fw-bold bg-dark border-secondary" id="editCashoutInput" readonly style="background-color: rgba(30, 41, 59, 0.85) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.2) !important; cursor: not-allowed;">
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.type') ?></label>
              <select class="form-select" id="editTipoSelect">
                <option value="Simples">Simples</option>
                <option value="Múltipla">Múltipla</option>
                <option value="Criar Aposta"><?= lang('App.add_new_bet_simulation') ?></option>
              </select>
            </div>
            <div class="col-4 mb-3">
              <label class="form-label text-white"><?= lang('App.status') ?></label>
              <select class="form-select" id="editStatusSelect">
                <option value="Pendente"><?= lang('App.pending') ?></option>
                <option value="Ganha"><?= lang('App.won') ?></option>
                <option value="Meio Ganha"><?= lang('App.half_won') ?></option>
                <option value="ANULADA"><?= lang('App.refunded') ?></option>
                <option value="Meio Perdida"><?= lang('App.half_lost') ?></option>
                <option value="Perdida"><?= lang('App.lost') ?></option>
                <option value="Cashout">Cashout</option>
              </select>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= lang('App.cancel') ?></button>
          <button type="submit" class="btn btn-info fw-bold px-4 text-white"><?= lang('App.update_bet_simulation') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  let allFixtureOptions = [];

  function initFixtureOptions() {
    const select = document.getElementById('fixtureSelect');
    if (!select || allFixtureOptions.length > 0) return;
    
    allFixtureOptions = [];
    for (let i = 0; i < select.options.length; i++) {
      const opt = select.options[i];
      if (opt.value === '') continue;
      allFixtureOptions.push({
        value: opt.value,
        text: opt.text,
        home: opt.getAttribute('data-home') || '',
        away: opt.getAttribute('data-away') || '',
        date: opt.getAttribute('data-date') || '',
        palpiteCards: opt.getAttribute('data-palpite-cards') || opt.getAttribute('data-palpite') || '',
        palpiteAH: opt.getAttribute('data-palpite-ah') || '',
        palpite: opt.getAttribute('data-palpite') || '',
        ahConfidence: parseFloat(opt.getAttribute('data-ah-confidence') || '0'),
        isMaxScore: opt.getAttribute('data-ah-max-score') === '1'
      });
    }
  }

  let currentViewMode = 'list';

  function setViewMode(mode) {
    currentViewMode = mode;
    const container = document.getElementById('betsContainer');
    const btnList = document.getElementById('btnViewList');
    const btnGrid = document.getElementById('btnViewGrid');

    if (!container) return;

    if (mode === 'list') {
      container.classList.add('list-view');
      container.classList.remove('grid-view');
      if (btnList) btnList.classList.add('active');
      if (btnGrid) btnGrid.classList.remove('active');
    } else {
      container.classList.remove('list-view');
      container.classList.add('grid-view');
      if (btnGrid) btnGrid.classList.add('active');
      if (btnList) btnList.classList.remove('active');
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    initFixtureOptions();
    setViewMode('list'); // Padrão em Lista no load
    
    // Selecionar data de hoje por padrão, mas com fallback se não houver apostas no dia de hoje
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('fixture_id')) {
      setTodayDateFilter();
      const visibleCount = document.querySelectorAll('.bet-card-item[style*="display: flex"]').length;
      const totalCards = document.querySelectorAll('.bet-card-item').length;
      if (visibleCount === 0 && totalCards > 0) {
        clearDateFilter();
      }
    } else {
      applyBetFilters();
    }
    
    const newBetModalEl = document.getElementById('newBetModal');
    if (newBetModalEl) {
      newBetModalEl.addEventListener('show.bs.modal', function() {
        initFixtureOptions();
        clearFixtureSelection();
      });
    }



    // Fechar dropdown ao clicar fora do container
    document.addEventListener('click', function(e) {
      const container = document.getElementById('fixtureComboboxContainer');
      if (container && !container.contains(e.target)) {
        closeFixtureDropdown();
      }
    });
  });

  function openFixtureDropdown() {
    initFixtureOptions();
    const dropdown = document.getElementById('fixtureDropdownList');
    if (dropdown) {
      dropdown.style.display = 'block';
      const searchInput = document.getElementById('fixtureSearchInput');
      filterFixtureSelect(searchInput ? searchInput.value : '');
    }
  }

  function closeFixtureDropdown() {
    const dropdown = document.getElementById('fixtureDropdownList');
    if (dropdown) {
      dropdown.style.display = 'none';
    }
  }

  function filterFixtureSelect(query) {
    initFixtureOptions();
    const dropdown = document.getElementById('fixtureDropdownList');
    if (!dropdown) return;

    dropdown.style.display = 'block';

    const term = (query || '').toLowerCase().trim();
    const currentVal = document.getElementById('fixtureSelect').value;

    dropdown.innerHTML = '';

    let matchCount = 0;
    allFixtureOptions.forEach((optData) => {
      const textMatch = optData.text.toLowerCase().includes(term);
      const homeMatch = optData.home.toLowerCase().includes(term);
      const awayMatch = optData.away.toLowerCase().includes(term);

      if (!term || textMatch || homeMatch || awayMatch) {
        matchCount++;
        const itemEl = document.createElement('div');
        itemEl.className = 'custom-combobox-item' + (optData.value === currentVal ? ' selected' : '');
        itemEl.innerHTML = `<i class="bi bi-calendar-event me-1 text-success"></i> ${escapeHtml(optData.text)}`;
        itemEl.onclick = function() {
          selectFixtureOption(optData);
        };
        dropdown.appendChild(itemEl);
      }
    });

    if (matchCount === 0) {
      const emptyEl = document.createElement('div');
      emptyEl.className = 'custom-combobox-empty';
      emptyEl.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i> Nenhuma partida encontrada para "${escapeHtml(query)}"`;
      dropdown.appendChild(emptyEl);
    }

    const badge = document.getElementById('fixtureCountBadge');
    if (badge) {
      if (term) {
        badge.textContent = `${matchCount} partida(s) encontrada(s)`;
        badge.className = matchCount > 0 ? 'form-text text-success small mt-1' : 'form-text text-danger small mt-1';
      } else {
        badge.textContent = `${allFixtureOptions.length} partidas disponíveis. Digite para filtrar.`;
        badge.className = 'form-text text-muted small mt-1';
      }
    }
  }

  function updateCardTargetLabels(homeTeam, awayTeam) {
    const homeName = homeTeam || document.getElementById('timeCasaInput')?.value || 'Time Casa';
    const awayName = awayTeam || document.getElementById('timeForaInput')?.value || 'Time Fora';

    const casaLabel = document.getElementById('cardTargetCasaLabel');
    const foraLabel = document.getElementById('cardTargetForaLabel');

    if (casaLabel) casaLabel.innerHTML = `🏠 ${escapeHtml(homeName)}`;
    if (foraLabel) foraLabel.innerHTML = `✈️ ${escapeHtml(awayName)}`;
  }

  function onCardTargetChange(target) {
    const homeTeam = document.getElementById('timeCasaInput')?.value || 'Time Casa';
    const awayTeam = document.getElementById('timeForaInput')?.value || 'Time Fora';
    const inputMercado = document.getElementById('mercadoInput');
    const mercadoSelect = document.getElementById('mercadoTypeSelect');
    const palpiteEl = document.getElementById('palpiteInput');

    updateCardTargetLabels(homeTeam, awayTeam);

    if (target === 'casa') {
      const mercVal = 'Cartões - Individual';
      if (inputMercado) inputMercado.value = mercVal;
      if (mercadoSelect) mercadoSelect.value = 'Cartões - Individual';
      if (palpiteEl) palpiteEl.value = `${homeTeam} - Menos de 2.5 cartões`;
    } else if (target === 'fora') {
      const mercVal = 'Cartões - Individual';
      if (inputMercado) inputMercado.value = mercVal;
      if (mercadoSelect) mercadoSelect.value = 'Cartões - Individual';
      if (palpiteEl) palpiteEl.value = `${awayTeam} - Menos de 2.5 cartões`;
    } else {
      if (inputMercado) inputMercado.value = 'Total de Cartões';
      if (mercadoSelect) mercadoSelect.value = 'Total de Cartões';

      const fixSelect = document.getElementById('fixtureSelect');
      let palpiteCards = 'Menos de 6.5 cartões';
      if (fixSelect && fixSelect.selectedIndex > 0) {
        const opt = fixSelect.options[fixSelect.selectedIndex];
        palpiteCards = opt.getAttribute('data-palpite-cards') || opt.getAttribute('data-palpite') || 'Menos de 6.5 cartões';
      }
      if (palpiteEl) palpiteEl.value = palpiteCards;
    }

    checkPalpiteEditableRule();
    updatePalpiteExplanation();
  }

  function selectFixtureOption(optData) {
    const select = document.getElementById('fixtureSelect');
    const searchInput = document.getElementById('fixtureSearchInput');

    if (select) select.value = optData.value;
    if (searchInput) searchInput.value = optData.text;

    document.getElementById('timeCasaInput').value = optData.home;
    document.getElementById('timeForaInput').value = optData.away;
    
    updateCardTargetLabels(optData.home, optData.away);

    const activeTarget = document.querySelector('input[name="cardTeamTarget"]:checked')?.value || 'jogo';
    if (activeTarget !== 'jogo') {
      onCardTargetChange(activeTarget);
    } else {
      const currentMercado = document.getElementById('mercadoTypeSelect')?.value || 'Total de Cartões';
      if (currentMercado === 'Handicap Asiático' && optData.palpiteAH) {
        document.getElementById('palpiteInput').value = formatHandicapPalpiteJs(optData.palpiteAH, optData.home);
      } else if (optData.palpiteCards || optData.palpite) {
        document.getElementById('palpiteInput').value = optData.palpiteCards || optData.palpite;
      }
    }

    closeFixtureDropdown();
    checkPalpiteEditableRule();
    updatePalpiteExplanation();
  }

  function clearFixtureSelection() {
    const select = document.getElementById('fixtureSelect');
    const searchInput = document.getElementById('fixtureSearchInput');
    const badge = document.getElementById('fixtureCountBadge');

    if (select) select.value = '';
    if (searchInput) searchInput.value = '';
    if (badge) badge.textContent = '';
    closeFixtureDropdown();
    checkPalpiteEditableRule();
  }

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return (text || '').replace(/[&<>"']/g, m => map[m]);
  }

  function calcGanhos() {
    const odd = parseFloat(document.getElementById('oddInput').value) || 0;
    const valInput = document.getElementById('valorInput');
    const valRaw = valInput ? valInput.value : '0';
    const val = parseFloat(valRaw) || 0;
    const res = odd * val;
    document.getElementById('ganhosDisplay').value = 'R$ ' + res.toFixed(2).replace('.', ',');
    const cashoutEl = document.getElementById('cashoutInput');
    if (cashoutEl) cashoutEl.value = valRaw;
  }

  function calcEditGanhos() {
    const odd = parseFloat(document.getElementById('editOddInput').value) || 0;
    const valInput = document.getElementById('editValorInput');
    const valRaw = valInput ? valInput.value : '0';
    const val = parseFloat(valRaw) || 0;
    const res = odd * val;
    document.getElementById('editGanhosDisplay').value = 'R$ ' + res.toFixed(2).replace('.', ',');
    const editCashoutEl = document.getElementById('editCashoutInput');
    if (editCashoutEl) editCashoutEl.value = valRaw;
  }

  function updatePalpiteExplanation() {
    const val = document.getElementById('palpiteInput')?.value || '';
    const tc = document.getElementById('timeCasaInput')?.value || 'Mandante';
    const tf = document.getElementById('timeForaInput')?.value || 'Visitante';
    const box = document.getElementById('palpiteExplanationBox');
    if (!box) return;

    if (!val) {
      box.style.display = 'none';
      return;
    }

    let text = '';
    if (val.includes('0.0') || val.includes('Empate Anula') || val.includes('+00') || val.includes('+ 00') || val.includes('Anula')) {
      const isAway = val.toLowerCase().includes(tf.toLowerCase());
      const fav = isAway ? tf : tc;
      const opp = isAway ? tc : tf;
      text = `🟢 Vitória do ${fav}: Aposta Ganha (100% Lucro).\n⚪ Empate: Aposta ANULADA (100% Devolvido / Reembolso - Valor Computado igual Apostado).\n🔴 Vitória do ${opp}: Aposta Perdida.`;
    } else if (val.includes('-0.25')) {
      const isAway = val.toLowerCase().includes(tf.toLowerCase());
      const fav = isAway ? tf : tc;
      const opp = isAway ? tc : tf;
      text = `🟢 Vitória do ${fav}: Aposta Ganha.\n🟡 Empate: Perde 50% e recupera 50%.\n🔴 Vitória do ${opp}: Aposta Perdida.`;
    } else if (val.includes('+0.25')) {
      const isAway = val.toLowerCase().includes(tf.toLowerCase());
      const fav = isAway ? tf : tc;
      const opp = isAway ? tc : tf;
      text = `🟢 Vitória do ${fav}: Aposta Ganha.\n🟢 Empate: Ganha 50% do Lucro + 100% da aposta de volta.\n🔴 Vitória do ${opp}: Aposta Perdida.`;
    } else if (val.includes('-0.5')) {
      const isAway = val.toLowerCase().includes(tf.toLowerCase());
      const fav = isAway ? tf : tc;
      const opp = isAway ? tc : tf;
      text = `🟢 Vitória do ${fav}: Aposta Ganha (Vitória Simples).\n🔴 Empate ou Vitória do ${opp}: Aposta Perdida.`;
    } else if (val.includes('+0.5')) {
      const isAway = val.toLowerCase().includes(tf.toLowerCase());
      const fav = isAway ? tf : tc;
      const opp = isAway ? tc : tf;
      text = `🟢 Vitória do ${fav} ou Empate: Aposta Ganha (Dupla Chance).\n🔴 Vitória do ${opp}: Aposta Perdida.`;
    }

    if (text) {
      box.style.display = 'block';
      box.innerHTML = `<strong><i class="bi bi-chat-left-text-fill text-success me-1"></i> Como funciona:</strong><br><span style="white-space: pre-line;">${text}</span>`;
    } else {
      box.style.display = 'none';
    }
  }

  function formatHandicapPalpiteJs(palpiteRaw, teamDefault) {
    if (!palpiteRaw) return teamDefault ? `${teamDefault} 0.0 (Empate Anula)` : '0.0 (Empate Anula)';
    if (palpiteRaw.includes('0.0 (Empate Anula)') || palpiteRaw.includes('0,0 (Empate Anula)')) {
      return palpiteRaw;
    }
    const match = palpiteRaw.match(/^(.*?)\s*([+-]?\d+[\.,]\d+|[+-]?\d+)/);
    if (match && match[1] && match[1].trim().length > 0) {
      return `${match[1].trim()} 0.0 (Empate Anula)`;
    }
    return teamDefault ? `${teamDefault} 0.0 (Empate Anula)` : `${palpiteRaw} 0.0 (Empate Anula)`;
  }

  function togglePalpiteLock(unlocked) {
    const palpiteInput = document.getElementById('palpiteInput');
    const toggleCheckbox = document.getElementById('toggleUnlockPalpite');
    if (toggleCheckbox) toggleCheckbox.checked = unlocked;
    if (!palpiteInput) return;
    if (unlocked) {
      palpiteInput.readOnly = false;
      palpiteInput.style.setProperty('background-color', 'rgba(15, 23, 42, 0.95)', 'important');
      palpiteInput.style.setProperty('color', '#ffffff', 'important');
      palpiteInput.style.setProperty('border', '1px solid #38bdf8', 'important');
      palpiteInput.style.setProperty('cursor', 'text', 'important');
    } else {
      palpiteInput.readOnly = true;
      palpiteInput.style.setProperty('background-color', 'rgba(30, 41, 59, 0.85)', 'important');
      palpiteInput.style.setProperty('color', '#ffffff', 'important');
      palpiteInput.style.setProperty('border', '1px solid rgba(255, 255, 255, 0.2)', 'important');
      palpiteInput.style.setProperty('cursor', 'not-allowed', 'important');
    }
  }

  function toggleEditPalpiteLock(unlocked) {
    const editPalpiteInput = document.getElementById('editPalpiteInput');
    const toggleCheckbox = document.getElementById('toggleUnlockEditPalpite');
    if (toggleCheckbox) toggleCheckbox.checked = unlocked;
    if (!editPalpiteInput) return;
    if (unlocked) {
      editPalpiteInput.readOnly = false;
      editPalpiteInput.style.setProperty('background-color', 'rgba(15, 23, 42, 0.95)', 'important');
      editPalpiteInput.style.setProperty('color', '#ffffff', 'important');
      editPalpiteInput.style.setProperty('border', '1px solid #38bdf8', 'important');
      editPalpiteInput.style.setProperty('cursor', 'text', 'important');
    } else {
      editPalpiteInput.readOnly = true;
      editPalpiteInput.style.setProperty('background-color', 'rgba(30, 41, 59, 0.85)', 'important');
      editPalpiteInput.style.setProperty('color', '#ffffff', 'important');
      editPalpiteInput.style.setProperty('border', '1px solid rgba(255, 255, 255, 0.2)', 'important');
      editPalpiteInput.style.setProperty('cursor', 'not-allowed', 'important');
    }
  }

  function checkPalpiteEditableRule() {
    checkMaxScoreBadge();
  }

  function checkMaxScoreBadge() {
    const selectEl = document.getElementById('fixtureSelect');
    if (!selectEl) return;
    const selectedIdx = selectEl.selectedIndex;
    if (selectedIdx < 0) return;
    const opt = selectEl.options[selectedIdx];
    if (!opt) return;

    const currentMercado = document.getElementById('mercadoInput')?.value || '';
    const isHandicap = (currentMercado === 'Handicap Asiático');

    let isMaxScore = false;

    if (opt.hasAttribute('data-ah-max-score')) {
      const maxAttr = opt.getAttribute('data-ah-max-score');
      const confAttr = parseFloat(opt.getAttribute('data-ah-confidence') || '0');
      isMaxScore = (maxAttr === '1' || confAttr >= 78.0);
    } else if (isHandicap) {
      const palpiteVal = document.getElementById('palpiteInput')?.value || '';
      if (palpiteVal) isMaxScore = true;
    }

    const badge = document.getElementById('maxScoreBadge');
    const isUserUnlocked = document.getElementById('toggleUnlockPalpite')?.checked;

    if (isUserUnlocked || (isHandicap && isMaxScore)) {
      togglePalpiteLock(true);
      if (badge) {
        badge.style.display = (isHandicap && isMaxScore) ? 'inline-flex' : 'none';
      }
    } else {
      togglePalpiteLock(false);
      if (badge) {
        badge.style.display = 'none';
      }
    }
  }

  function onMercadoTypeChange(selectEl) {
    const val = selectEl.value;
    const inputMercado = document.getElementById('mercadoInput');
    if (inputMercado) inputMercado.value = val;

    const targetRow = document.getElementById('cardTeamTargetRow');
    const isCards = (val === 'Total de Cartões' || val.includes('Cartões') || val.includes('cartoe'));

    if (targetRow) {
      targetRow.style.display = isCards ? 'flex' : 'none';
    }

    if (val === 'Cartões - Individual' || val === 'Cartões - Time Casa' || val === 'Cartões - Time Fora') {
      const activeTarget = document.querySelector('input[name="cardTeamTarget"]:checked')?.value || 'casa';
      const targetToUse = (activeTarget === 'jogo') ? 'casa' : activeTarget;
      const r = document.getElementById(targetToUse === 'fora' ? 'cardTargetFora' : 'cardTargetCasa');
      if (r) r.checked = true;
      onCardTargetChange(targetToUse);
      return;
    } else if (val === 'Total de Cartões') {
      const r = document.getElementById('cardTargetJogo');
      if (r) r.checked = true;
      onCardTargetChange('jogo');
      return;
    }

    const fixSelect = document.getElementById('fixtureSelect');
    if (fixSelect && fixSelect.selectedIndex > 0) {
      const opt = fixSelect.options[fixSelect.selectedIndex];
      if (val === 'Handicap Asiático') {
        const palpiteAH = formatHandicapPalpiteJs(opt.getAttribute('data-palpite-ah'), opt.getAttribute('data-home'));
        if (palpiteAH) document.getElementById('palpiteInput').value = palpiteAH;
      }
    } else if (val === 'Handicap Asiático') {
      const homeTeam = document.getElementById('timeCasaInput')?.value || '';
      document.getElementById('palpiteInput').value = formatHandicapPalpiteJs(document.getElementById('palpiteInput')?.value, homeTeam);
    }
    checkPalpiteEditableRule();
    updatePalpiteExplanation();
  }

  function autofillFixture(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (opt && opt.value) {
      const homeTeam = opt.getAttribute('data-home') || '';
      document.getElementById('timeCasaInput').value = homeTeam;
      document.getElementById('timeForaInput').value = opt.getAttribute('data-away') || '';
      
      const currentMercado = document.getElementById('mercadoTypeSelect')?.value || 'Total de Cartões';
      if (currentMercado === 'Handicap Asiático') {
        const palpiteAH = formatHandicapPalpiteJs(opt.getAttribute('data-palpite-ah'), homeTeam);
        if (palpiteAH) document.getElementById('palpiteInput').value = palpiteAH;
      } else {
        const palpiteCards = opt.getAttribute('data-palpite-cards') || opt.getAttribute('data-palpite');
        if (palpiteCards) document.getElementById('palpiteInput').value = palpiteCards;
      }
    }
    checkPalpiteEditableRule();
    updatePalpiteExplanation();
    checkXgWarning();
  }

  function checkXgWarning() {
    const fixSelect = document.getElementById('fixtureSelect');
    const mercadoSelect = document.getElementById('mercadoTypeSelect');
    const banner = document.getElementById('xgWarningBanner');
    if (!banner) return;

    const selectedOpt = fixSelect && fixSelect.selectedIndex >= 0 ? fixSelect.options[fixSelect.selectedIndex] : null;
    const mercado = mercadoSelect ? mercadoSelect.value : '';

    if (selectedOpt && selectedOpt.value && (mercado === 'Handicap Asiático' || mercado.toLowerCase().includes('handicap'))) {
      const ahSug = (selectedOpt.getAttribute('data-ah-suggestion') || '').toLowerCase();
      const isBlocked = ahSug.includes('sem entrada') || ahSug.includes('abstenção') || ahSug.includes('abstencao') || ahSug.includes('bloquead') || ahSug.includes('indisponível') || ahSug.includes('indisponivel');
      if (isBlocked) {
        banner.style.display = 'flex';
        return;
      }
    }
    banner.style.display = 'none';
  }

  let currentStatusFilter = 'all';

  function filterBets(status, btn) {
    if (btn) {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    }
    currentStatusFilter = status;
    applyBetFilters();
  }

  function searchBets() {
    applyBetFilters();
  }

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

  function setBetDatePreset(presetKey) {
    const startEl = document.getElementById('betStartDateInput');
    const endEl = document.getElementById('betEndDateInput');
    const selectEl = document.getElementById('betDatePresetSelect');
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

    applyBetFilters();
  }

  function setTodayDateFilter() {
    setBetDatePreset('today');
  }

  function clearDateFilter() {
    setBetDatePreset('all');
  }

  function onManualDateChange() {
    const startVal = document.getElementById('betStartDateInput')?.value || '';
    const endVal = document.getElementById('betEndDateInput')?.value || '';
    const selectEl = document.getElementById('betDatePresetSelect');

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

    applyBetFilters();
  }

  function applyBetFilters() {
    const status = currentStatusFilter;
    const selectedMarket = document.getElementById('betMarketFilterSelect')?.value || 'all';
    const term = (document.getElementById('betSearchInput')?.value || '').toLowerCase().trim();
    const startDate = document.getElementById('betStartDateInput')?.value || '';
    const endDate = document.getElementById('betEndDateInput')?.value || '';

    const cards = document.querySelectorAll('.bet-card-item');
    let visibleCount = 0;

    const counts = {
      all: 0,
      Pendente: 0,
      Ganha: 0,
      'Meio Ganha': 0,
      ANULADA: 0,
      'Meio Perdida': 0,
      Perdida: 0,
      Cashout: 0
    };

    cards.forEach(card => {
      const cardStatus = card.getAttribute('data-status') || '';
      const cardMercado = (card.getAttribute('data-mercado') || '').toLowerCase();
      const cardPalpite = (card.getAttribute('data-palpite') || '').toLowerCase();
      const cardSearch = card.getAttribute('data-search') || '';
      const cardDate = card.getAttribute('data-date') || ''; // 'YYYY-MM-DD'
      const cardCreated = card.getAttribute('data-created-date') || cardDate;

      const itemDate = cardDate || cardCreated;

      const searchMatch = (!term || cardSearch.includes(term));
      
      let dateMatch = true;
      if (startDate && itemDate < startDate) {
        dateMatch = false;
      }
      if (endDate && itemDate > endDate) {
        dateMatch = false;
      }

      let marketMatch = true;
      if (selectedMarket === 'handicap') {
        marketMatch = cardMercado.includes('handicap') || 
                      cardMercado.includes('empate anula') || 
                      cardMercado.includes('dnb') || 
                      cardPalpite.includes('ah') || 
                      cardPalpite.includes('handicap');
      } else if (selectedMarket === 'cartoes') {
        marketMatch = cardMercado.includes('cartõ') || 
                      cardMercado.includes('carto') || 
                      cardMercado.includes('card') || 
                      cardPalpite.includes('cartõ') || 
                      cardPalpite.includes('carto') || 
                      cardPalpite.includes('under');
      }

      if (searchMatch && dateMatch && marketMatch) {
        counts.all++;
        if (counts.hasOwnProperty(cardStatus)) {
          counts[cardStatus]++;
        }
      }

      const statusMatch = (status === 'all' || cardStatus === status);

      if (statusMatch && searchMatch && dateMatch && marketMatch) {
        card.style.display = 'flex';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    updateTabBadges(counts);

    let emptyNotice = document.getElementById('noFilteredBetsNotice');
    if (visibleCount === 0 && cards.length > 0) {
      if (!emptyNotice) {
        emptyNotice = document.createElement('div');
        emptyNotice.id = 'noFilteredBetsNotice';
        emptyNotice.className = 'w-100 text-center py-5';
        emptyNotice.style.gridColumn = '1 / -1';
        emptyNotice.style.color = 'var(--bet-text-muted)';
        emptyNotice.innerHTML = `
          <i class="bi bi-funnel" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
          <h5>Nenhuma aposta encontrada</h5>
          <p>Nenhuma aposta corresponde aos filtros aplicados (Status, Período de datas ou Busca).</p>
          <button class="btn btn-sm btn-outline-success mt-2" onclick="resetAllBetFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i> Resetar Filtros</button>
        `;
        const container = document.getElementById('betsContainer');
        if (container) container.appendChild(emptyNotice);
      } else {
        emptyNotice.style.display = 'block';
      }
    } else if (emptyNotice) {
      emptyNotice.style.display = 'none';
    }

    updateCalculatedSummary();
  }

  function updateTabBadges(counts) {
    const total = counts.all || 0;

    function formatBadge(label, count) {
      if (total <= 0) {
        return `${label} (${count} - 0%)`;
      }
      const pct = (count / total) * 100;
      const pctStr = (pct % 1 === 0) ? pct.toFixed(0) + '%' : pct.toFixed(1).replace('.', ',') + '%';
      return `${label} (${count} - ${pctStr})`;
    }

    const btnAll = document.getElementById('btnFilterAll');
    const btnPendente = document.getElementById('btnFilterPendente');
    const btnGanha = document.getElementById('btnFilterGanha');
    const btnMeioGanha = document.getElementById('btnFilterMeioGanha');
    const btnAnulada = document.getElementById('btnFilterAnulada');
    const btnMeioPerdida = document.getElementById('btnFilterMeioPerdida');
    const btnPerdida = document.getElementById('btnFilterPerdida');
    const btnCashout = document.getElementById('btnFilterCashout');

    if (btnAll) btnAll.textContent = formatBadge(<?= json_encode(lang('App.all_statuses')) ?>, total);
    if (btnPendente) btnPendente.textContent = formatBadge(<?= json_encode(lang('App.pending')) ?>, counts['Pendente'] || 0);
    if (btnGanha) btnGanha.textContent = formatBadge(<?= json_encode(lang('App.won')) ?>, counts['Ganha'] || 0);
    if (btnMeioGanha) btnMeioGanha.textContent = formatBadge(<?= json_encode(lang('App.half_won')) ?>, counts['Meio Ganha'] || 0);
    if (btnAnulada) btnAnulada.textContent = formatBadge(<?= json_encode(lang('App.refunded')) ?>, counts['ANULADA'] || 0);
    if (btnMeioPerdida) btnMeioPerdida.textContent = formatBadge(<?= json_encode(lang('App.half_lost')) ?>, counts['Meio Perdida'] || 0);
    if (btnPerdida) btnPerdida.textContent = formatBadge(<?= json_encode(lang('App.lost')) ?>, counts['Perdida'] || 0);
    if (btnCashout) btnCashout.textContent = formatBadge('Cashout', counts['Cashout'] || 0);
  }

  function updateCalculatedSummary() {
    const cards = document.querySelectorAll('.bet-card-item');
    let totalApostado = 0;
    let totalApostadoLiquidado = 0;
    let totalRetorno = 0;
    let totalLucro = 0;
    let totalPerda = 0;
    let visibleCount = 0;

    cards.forEach(card => {
      if (card.style.display !== 'none') {
        visibleCount++;
        const status = card.getAttribute('data-status') || '';
        const valor = parseFloat(card.getAttribute('data-valor') || '0') || 0;
        const odd = parseFloat(card.getAttribute('data-odd') || '0') || 0;
        const ganho = parseFloat(card.getAttribute('data-ganho') || '0') || 0;
        const cashout = parseFloat(card.getAttribute('data-cashout') || '0') || 0;

        totalApostado += valor;

        if (status !== 'Pendente') {
          totalApostadoLiquidado += valor;
        }

        if (status === 'Ganha') {
          const ret = ganho > 0 ? ganho : (valor * odd);
          totalRetorno += ret;
          const lucroItem = (ret - valor);
          if (lucroItem > 0) totalLucro += lucroItem;
        } else if (status === 'Meio Ganha') {
          const fullRet = ganho > 0 ? ganho : (valor * odd);
          const ret = valor + ((fullRet - valor) / 2);
          totalRetorno += ret;
          const lucroItem = ((fullRet - valor) / 2);
          if (lucroItem > 0) totalLucro += lucroItem;
        } else if (status === 'Cashout') {
          const cashVal = cashout > 0 ? cashout : (ganho > 0 ? ganho : (valor * odd));
          totalRetorno += cashVal;
          if (cashVal > valor) {
            totalLucro += (cashVal - valor);
          }
        } else if (status === 'Perdida') {
          totalPerda += valor;
        } else if (status === 'Meio Perdida') {
          totalRetorno += (valor * 0.5);
          totalPerda += (valor * 0.5);
        } else if (status === 'ANULADA') {
          totalRetorno += valor;
        }
      }
    });

    // Saldo Líquido Real = Retorno Total Bruto - Total Apostado (Apostas Liquidadas)
    const saldoLiquido = totalRetorno - totalApostadoLiquidado;

    const formatBrl = (val) => val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const elApostado = document.getElementById('calcTotalApostado');
    const elGanho = document.getElementById('calcTotalGanho');
    const elLucro = document.getElementById('calcTotalLucro');
    const elPerda = document.getElementById('calcTotalPerda');
    const elSaldo = document.getElementById('calcSaldoLiquido');

    if (elApostado) elApostado.textContent = formatBrl(totalApostado);
    if (elGanho) elGanho.textContent = formatBrl(totalRetorno);
    if (elLucro) elLucro.textContent = formatBrl(totalLucro);
    if (elPerda) elPerda.textContent = formatBrl(totalPerda);
    if (elSaldo) {
      const prefix = saldoLiquido > 0 ? '+' : '';
      elSaldo.textContent = prefix + formatBrl(saldoLiquido);
      if (saldoLiquido > 0) {
        elSaldo.className = 'text-success fw-bold';
      } else if (saldoLiquido < 0) {
        elSaldo.className = 'text-danger fw-bold';
      } else {
        elSaldo.className = 'text-info fw-bold';
      }
    }

    // Atualiza Top Cards (Resumo Superior)
    const topApostado = document.getElementById('topTotalApostado');
    const topGanho = document.getElementById('topRetornoBruto');
    const topSaldo = document.getElementById('topSaldoLiquido');
    const topApostas = document.getElementById('topTotalApostas');

    if (topApostado) topApostado.textContent = formatBrl(totalApostado);
    if (topGanho) topGanho.textContent = formatBrl(totalRetorno);
    if (topApostas) topApostas.textContent = visibleCount;
    if (topSaldo) {
      const prefix = saldoLiquido > 0 ? '+' : '';
      topSaldo.textContent = prefix + formatBrl(saldoLiquido);
      if (saldoLiquido > 0) {
        topSaldo.className = 'stat-value primary';
      } else if (saldoLiquido < 0) {
        topSaldo.className = 'stat-value text-danger';
      } else {
        topSaldo.className = 'stat-value gold';
      }
    }
  }

  function resetAllBetFilters() {
    currentStatusFilter = 'all';
    const firstBtn = document.querySelector('.filter-btn');
    if (firstBtn) {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      firstBtn.classList.add('active');
    }
    const marketEl = document.getElementById('betMarketFilterSelect');
    if (marketEl) marketEl.value = 'all';
    const searchEl = document.getElementById('betSearchInput');
    if (searchEl) searchEl.value = '';
    clearDateFilter();
  }

  let isSubmittingNewBet = false;
  function submitNewBet(e, confirmRisco = false) {
    if (e && e.preventDefault) e.preventDefault();
    if (isSubmittingNewBet && !confirmRisco) return;

    const oddVal = parseFloat(document.getElementById('oddInput').value) || 0;
    if (oddVal <= 1.0) {
      alert('❌ A Odd informada é inválida. Informe um valor maior que 1.00.');
      return;
    }

    const submitBtn = document.querySelector('#newBetForm button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.origText = submitBtn.dataset.origText || submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processando...';
    }
    isSubmittingNewBet = true;

    const resetSubmitState = () => {
      isSubmittingNewBet = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.dataset.origText || 'Salvar Aposta';
      }
    };

    const formData = new FormData();
    formData.append('time_casa', document.getElementById('timeCasaInput').value);
    formData.append('time_fora', document.getElementById('timeForaInput').value);
    formData.append('mercado', document.getElementById('mercadoInput').value);
    formData.append('palpite', document.getElementById('palpiteInput').value);
    formData.append('odd', document.getElementById('oddInput').value);
    formData.append('valor_aposta', document.getElementById('valorInput').value);
    formData.append('cash_out', document.getElementById('cashoutInput').value);
    formData.append('tipo', document.getElementById('tipoSelect').value);
    formData.append('status', document.getElementById('statusSelect').value);
    formData.append('fixture_id', document.getElementById('fixtureSelect').value);
    if (confirmRisco) {
      formData.append('confirmar_risco', '1');
    }

    fetch('/apostas/store', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert('✓ ' + data.message);
        window.location.href = window.location.pathname;
      } else if (data.require_confirmation || data.is_warning) {
        resetSubmitState();
        const msgClean = (data.message || '').replace(/^⚠️\s*/, '');
        setTimeout(() => {
          if (confirm(msgClean)) {
            submitNewBet(null, true);
          }
        }, 50);
      } else {
        resetSubmitState();
        alert('❌ ' + data.message);
      }
    })
    .catch(err => {
      resetSubmitState();
      console.error(err);
      alert('Erro na requisição.');
    });
  }

  function showModalSafely(modalEl) {
    if (!modalEl) return;

    // Remove eventuais backdrops residuais travados
    const oldBackdrops = document.querySelectorAll('.modal-backdrop');
    oldBackdrops.forEach(b => b.remove());

    // 1. Tenta API oficial do Bootstrap 5 (getOrCreateInstance)
    try {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modalObj = bootstrap.Modal.getOrCreateInstance(modalEl);
        if (modalObj) {
          modalObj.show();
          return;
        }
      }
    } catch (e) {
      console.warn('Bootstrap 5 JS Modal error:', e);
    }

    // 2. Tenta jQuery Bootstrap (4/3)
    try {
      if (typeof $ !== 'undefined' && typeof $(modalEl).modal === 'function') {
        $(modalEl).modal('show');
        return;
      }
    } catch (e) {
      console.warn('jQuery Modal error:', e);
    }

    // 3. Fallback CSS / DOM puro
    modalEl.style.display = 'block';
    modalEl.style.opacity = '1';
    modalEl.classList.add('show');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');

    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.id = 'manual-backdrop-' + modalEl.id;
    document.body.appendChild(backdrop);
  }

  function hideModalSafely(modalEl) {
    if (!modalEl) return;
    try {
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modalObj = bootstrap.Modal.getInstance(modalEl);
        if (modalObj) {
          modalObj.hide();
        }
      }
    } catch (e) {}

    try {
      if (typeof $ !== 'undefined' && typeof $(modalEl).modal === 'function') {
        $(modalEl).modal('hide');
      }
    } catch (e) {}

    modalEl.style.display = 'none';
    modalEl.classList.remove('show');
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(b => b.remove());
  }

  function handleOpenEditModal(btn) {
    if (!btn) return;
    try {
      const getAttr = (attr) => btn.getAttribute(attr) || '';
      const id = getAttr('data-id');
      const home = getAttr('data-home');
      const away = getAttr('data-away');
      const mercado = getAttr('data-mercado');
      const palpite = getAttr('data-palpite');
      const odd = getAttr('data-odd');
      const valor = getAttr('data-valor');
      const tipo = getAttr('data-tipo') || 'Simples';
      const status = getAttr('data-status') || 'Pendente';

      const setVal = (elemId, val) => {
        const el = document.getElementById(elemId);
        if (el) el.value = val;
      };

      setVal('editIdInput', id);
      setVal('editTimeCasaInput', home);
      setVal('editTimeForaInput', away);
      setVal('editMercadoInput', mercado);
      setVal('editPalpiteInput', palpite);
      setVal('editOddInput', odd);
      setVal('editValorInput', valor);
      setVal('editCashoutInput', valor);
      setVal('editTipoSelect', tipo);
      setVal('editStatusSelect', status);

      if (typeof calcEditGanhos === 'function') {
        calcEditGanhos();
      }
    } catch (err) {
      console.error('Error populating edit modal:', err);
    }

    showModalSafely(document.getElementById('editBetModal'));
  }

  function populateEditModal(btn) {
    handleOpenEditModal(btn);
  }

  function openEditModal(aposta) {
    if (!aposta) return;
    document.getElementById('editIdInput').value = aposta.id || '';
    document.getElementById('editTimeCasaInput').value = aposta.time_casa || '';
    document.getElementById('editTimeForaInput').value = aposta.time_fora || '';
    document.getElementById('editMercadoInput').value = aposta.mercado || '';
    document.getElementById('editPalpiteInput').value = aposta.palpite || '';
    document.getElementById('editOddInput').value = aposta.odd || '';
    const val = aposta.valor_aposta || '10.00';
    document.getElementById('editValorInput').value = val;
    document.getElementById('editCashoutInput').value = val;
    document.getElementById('editTipoSelect').value = aposta.tipo || 'Simples';
    document.getElementById('editStatusSelect').value = aposta.status || 'Pendente';
    toggleEditPalpiteLock(false);
    calcEditGanhos();

    showModalSafely(document.getElementById('editBetModal'));
  }

  let isSubmittingEditBet = false;
  function submitEditBet(e, confirmRisco = false) {
    if (e && e.preventDefault) e.preventDefault();
    if (isSubmittingEditBet && !confirmRisco) return;

    const oddVal = parseFloat(document.getElementById('editOddInput').value) || 0;
    if (oddVal <= 1.0) {
      alert('❌ A Odd informada é inválida. Informe um valor maior que 1.00.');
      return;
    }

    const submitBtn = document.querySelector('#editBetForm button[type="submit"]');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.origText = submitBtn.dataset.origText || submitBtn.innerHTML;
      submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Atualizando...';
    }
    isSubmittingEditBet = true;

    const resetEditState = () => {
      isSubmittingEditBet = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.dataset.origText || 'Atualizar Aposta';
      }
    };

    const id = document.getElementById('editIdInput').value;
    const formData = new FormData();
    formData.append('id', id);
    formData.append('time_casa', document.getElementById('editTimeCasaInput').value);
    formData.append('time_fora', document.getElementById('editTimeForaInput').value);
    formData.append('mercado', document.getElementById('editMercadoInput').value);
    formData.append('palpite', document.getElementById('editPalpiteInput').value);
    formData.append('odd', document.getElementById('editOddInput').value);
    formData.append('valor_aposta', document.getElementById('editValorInput').value);
    formData.append('cash_out', document.getElementById('editCashoutInput').value);
    formData.append('tipo', document.getElementById('editTipoSelect').value);
    formData.append('status', document.getElementById('editStatusSelect').value);
    if (confirmRisco) {
      formData.append('confirmar_risco', '1');
    }

    fetch('/apostas/update/' + id, {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        hideModalSafely(document.getElementById('editBetModal'));
        alert('✓ ' + data.message);
        window.location.replace('/apostas');
      } else if (data.require_confirmation || data.is_warning) {
        resetEditState();
        const msgClean = (data.message || '').replace(/^⚠️\s*/, '');
        setTimeout(() => {
          if (confirm(msgClean)) {
            submitEditBet(null, true);
          }
        }, 50);
      } else {
        resetEditState();
        alert('❌ ' + data.message);
      }
    })
    .catch(err => {
      resetEditState();
      console.error(err);
      alert('Erro na atualização.');
    });
  }

  function handleCashout(id, valor) {
    if (!confirm('Deseja realizar o Cash Out nesta aposta no valor de R$ ' + parseFloat(valor).toFixed(2).replace('.', ',') + '?')) return;

    const formData = new FormData();
    formData.append('id', id);
    formData.append('valor_cashout', valor);

    fetch('/apostas/cashout/' + id, {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        hideModalSafely(document.getElementById('editBetModal'));
        alert('✓ ' + data.message);
        window.location.replace('/apostas');
      } else {
        alert('❌ ' + data.message);
      }
    });
  }

  const activeReapostas = {};
  function handleReapostar(id, confirmRisco = false) {
    if (activeReapostas[id]) return;

    if (!confirmRisco) {
      if (!confirm('Deseja reapostar (duplicar) este palpite?')) return;
    }

    activeReapostas[id] = true;

    const formData = new FormData();
    formData.append('id', id);
    if (confirmRisco) {
      formData.append('confirmar_risco', '1');
    }

    fetch('/apostas/reapostar/' + id, {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert('✓ ' + data.message);
        window.location.replace('/apostas');
      } else if (data.require_confirmation || data.is_warning) {
        delete activeReapostas[id];
        const msgClean = (data.message || '').replace(/^⚠️\s*/, '');
        setTimeout(() => {
          if (confirm(msgClean)) {
            handleReapostar(id, true);
          }
        }, 50);
      } else {
        delete activeReapostas[id];
        alert('❌ ' + data.message);
      }
    })
    .catch(err => {
      delete activeReapostas[id];
      console.error(err);
      alert('Erro ao reapostar.');
    });
  }

  function handleDelete(id) {
    if (!confirm('Tem certeza que deseja excluir esta aposta?')) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('/apostas/delete/' + id, {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        hideModalSafely(document.getElementById('editBetModal'));
        alert('✓ ' + data.message);
        window.location.replace('/apostas');
      } else {
        alert('❌ ' + data.message);
      }
    });
  }

  function shareBet(timeCasa, timeFora, palpite, odd) {
    const text = `🎯 Meu Palpite em CristalBet:\n⚽ ${timeCasa} vs ${timeFora}\n📊 Mercado: ${palpite} @ Odd ${odd}\n\nAcompanhe e crie suas apostas no CristalBet!`;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(() => {
        alert('✓ Palpite copiado para a área de transferência!\n\n' + text);
      });
    } else {
      alert(text);
    }
  }

  function triggerProcessarDAG() {
    if (!confirm('Deseja disparar manualmente a auditoria e verificação das 23:00 hs para liquidar apostas pendentes do dia?')) return;

    fetch('/apostas/processar', {
      method: 'POST'
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert('✓ ' + data.message + '\n\n' + (data.output || ''));
        window.location.replace('/apostas');
      } else {
        alert('❌ ' + data.message);
      }
    })
    .catch(err => {
      console.error(err);
      alert('Erro ao executar o processamento.');
    });
  }

  // Auto-abrir modal e preencher dados quando direcionado do card do FootballWeb
  document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const isNewBet = urlParams.get('new_bet') === '1' || urlParams.get('action') === 'new';
    const actionParam = urlParams.get('action');
    const fixId = urlParams.get('fixture_id');
    const mercadoParam = urlParams.get('mercado');
    const palpiteParam = urlParams.get('palpite');

    // Limpa a URL no histórico do navegador após ler os parâmetros para evitar reaberturas acidentais após reloads manuais
    if (window.history && window.history.replaceState && (fixId || isNewBet)) {
      window.history.replaceState({}, document.title, window.location.pathname);
    }

    const userApostas = <?= json_encode($apostas ?? []) ?>;

    // Se fixture_id foi fornecido e NÃO foi explicitamente solicitado cadastrar nova aposta (new_bet=1)
    if (fixId && !isNewBet) {
      const existingBet = userApostas.find(a => String(a.fixture_id) === String(fixId));
      if (existingBet) {
        // Carrega e abre modal de EDIÇÃO com os dados completos salvos da aposta
        openEditModal(existingBet);

        // Destaca a aposta na listagem
        const cardItem = document.getElementById('aposta-card-' + existingBet.id);
        if (cardItem) {
          cardItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
          cardItem.style.boxShadow = '0 0 20px rgba(0, 230, 118, 0.6)';
        }
        return;
      }
    }

    // Caso seja uma nova aposta (new_bet=1 OU sem aposta cadastrada para a fixture):
    if (isNewBet || (fixId && actionParam !== 'edit')) {
      const modalEl = document.getElementById('newBetModal');
      if (modalEl) {
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();

        if (mercadoParam) {
          const mercadoSelect = document.getElementById('mercadoTypeSelect');
          const mercadoInput = document.getElementById('mercadoInput');
          if (mercadoParam.toLowerCase() === 'handicap' || mercadoParam.toLowerCase() === 'handicap asiático') {
            if (mercadoSelect) mercadoSelect.value = 'Handicap Asiático';
            if (mercadoInput) mercadoInput.value = 'Handicap Asiático';
          } else if (mercadoParam.toLowerCase() === 'cartoes' || mercadoParam.toLowerCase() === 'cartões') {
            if (mercadoSelect) mercadoSelect.value = 'Total de Cartões';
            if (mercadoInput) mercadoInput.value = 'Total de Cartões';
          }
        }

        if (fixId) {
          const selectEl = document.getElementById('fixtureSelect');
          if (selectEl) {
            selectEl.value = fixId;
            autofillFixture(selectEl);
          }
        }

        const mercSelectEl = document.getElementById('mercadoTypeSelect');
        if (mercSelectEl) {
          mercSelectEl.addEventListener('change', checkXgWarning);
        }
        checkXgWarning();

        if (palpiteParam) {
          const palpiteInput = document.getElementById('palpiteInput');
          if (palpiteInput) {
            const currentMerc = (document.getElementById('mercadoTypeSelect')?.value || '').toLowerCase();
            if (currentMerc.includes('handicap') || (mercadoParam && mercadoParam.toLowerCase().includes('handicap'))) {
              palpiteInput.value = formatHandicapPalpiteJs(palpiteParam, document.getElementById('timeCasaInput')?.value);
            } else {
              palpiteInput.value = palpiteParam;
            }
          }
        }
        checkPalpiteEditableRule();
      }
    }

    applyBetFilters();
  });
</script>
