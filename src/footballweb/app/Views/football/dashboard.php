<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';

// Formatação amigável da data
setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil');
$dateObj = DateTime::createFromFormat('Y-m-d', $targetDate);
$formattedDateHeader = $dateObj ? strftime('%d de %B de %Y', $dateObj->getTimestamp()) : $targetDate;
?>

<!-- Estilos Customizados para o Football Trends (Design Premium) -->
<style>
    /* Google Fonts & Root Design System */
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    .ft-container {
        font-family: 'Outfit', sans-serif;
        background: linear-gradient(135deg, #0b0f19 0%, #111827 100%);
        color: #f3f4f6;
        min-height: 100vh;
        padding: 40px 20px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
        margin: 20px 0;
    }

    /* Hero Section */
    .ft-hero {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(17, 24, 39, 0.8) 100%);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 20px;
        padding: 40px 30px;
        margin-bottom: 40px;
        text-align: center;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(10px);
    }

    .ft-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, transparent 60%);
        pointer-events: none;
    }

    .ft-title-badge {
        background: rgba(16, 185, 129, 0.2);
        color: #34d399;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 6px 16px;
        border-radius: 30px;
        display: inline-block;
        margin-bottom: 15px;
        text-transform: uppercase;
        letter-spacing: 1px;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .ft-title {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(90deg, #34d399 0%, #059669 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 10px;
        letter-spacing: -1px;
    }

    .ft-subtitle {
        font-size: 1.1rem;
        color: #9ca3af;
        max-width: 600px;
        margin: 0 auto 30px auto;
        line-height: 1.6;
    }

    /* Controles e Filtros */
    .ft-controls-row {
        background: rgba(31, 41, 55, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 30px;
        backdrop-filter: blur(5px);
    }

    .date-navigator {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .date-btn {
        background: #1f2937;
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #d1d5db;
        padding: 10px 18px;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none !important;
    }

    .date-btn:hover {
        background: #374151;
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .date-btn.active {
        background: #10b981;
        border-color: #10b981;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
    }

    .date-picker-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .date-input {
        background: #111827;
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        padding: 10px 16px;
        border-radius: 12px;
        outline: none;
        transition: all 0.3s;
        font-family: inherit;
    }

    .date-input:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .search-box-wrapper {
        position: relative;
        flex: 1;
        min-width: 250px;
    }

    .search-input {
        background: #111827;
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        padding: 11px 16px 11px 45px;
        border-radius: 12px;
        width: 100%;
        outline: none;
        transition: all 0.3s;
        font-family: inherit;
    }

    .search-input:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
    }

    .btn-update-api {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }

    .btn-update-api:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
    }

    .btn-update-api:disabled {
        background: #4b5563;
        box-shadow: none;
        cursor: not-allowed;
    }

    /* Liga Pills */
    .leagues-wrapper {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 30px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .league-pill {
        background: rgba(55, 65, 81, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        color: #9ca3af;
        padding: 8px 16px;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }

    .league-pill:hover {
        background: rgba(55, 65, 81, 0.8);
        color: #ffffff;
    }

    .league-pill.active {
        background: rgba(16, 185, 129, 0.15);
        border-color: #10b981;
        color: #34d399;
    }

    /* Partidas Grid */
    .fixtures-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 24px;
    }

    .fixture-card {
        background: rgba(31, 41, 55, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 24px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .fixture-card:hover {
        transform: translateY(-5px);
        border-color: rgba(16, 185, 129, 0.3);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4), 0 0 15px rgba(16, 185, 129, 0.1);
        background: rgba(31, 41, 55, 0.6);
    }

    .card-header-ft {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .league-badge {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #9ca3af;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 8px;
        max-width: 70%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .time-badge {
        color: #34d399;
        font-weight: 700;
        font-size: 0.85rem;
        background: rgba(16, 185, 129, 0.1);
        padding: 4px 10px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .teams-section {
        margin-bottom: 20px;
    }

    .team-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .team-row:last-child {
        margin-bottom: 0;
    }

    .team-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
    }

    .team-row:last-child .team-dot {
        background: #3b82f6;
    }

    .team-name {
        font-weight: 600;
        font-size: 1.15rem;
        color: #f3f4f6;
    }

    .divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.05);
        margin: 15px 0;
    }

    /* Prediction Section */
    .prediction-box {
        margin-bottom: 15px;
    }

    .pred-prob-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .pred-prob-label {
        font-size: 0.85rem;
        color: #9ca3af;
        font-weight: 500;
    }

    .pred-prob-value {
        font-weight: 700;
        font-size: 1rem;
    }

    .pred-prob-value.high { color: #f87171; }
    .pred-prob-value.medium { color: #fbbf24; }
    .pred-prob-value.low { color: #34d399; }

    .progress-track {
        height: 6px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 10px;
    }

    .progress-fill.high { background: linear-gradient(90deg, #ef4444, #b91c1c); }
    .progress-fill.medium { background: linear-gradient(90deg, #f59e0b, #d97706); }
    .progress-fill.low { background: linear-gradient(90deg, #10b981, #047857); }

    .prediction-text {
        font-size: 0.88rem;
        color: #d1d5db;
        line-height: 1.5;
        background: rgba(255, 255, 255, 0.02);
        padding: 10px;
        border-radius: 8px;
        border-left: 3px solid #10b981;
    }

    .prediction-text.high { border-left-color: #ef4444; }
    .prediction-text.medium { border-left-color: #f59e0b; }
    .prediction-text.low { border-left-color: #10b981; }

    /* Referee area */
    .referee-area {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
    }

    .referee-badge {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        color: #60a5fa;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 30px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
    }

    .referee-badge:hover {
        background: rgba(59, 130, 246, 0.25);
        color: #ffffff;
        transform: scale(1.05);
    }

    .status-badge {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
    }

    .status-badge.ns { background: rgba(156, 163, 175, 0.1); color: #9ca3af; }
    .status-badge.live { background: rgba(239, 68, 68, 0.15); color: #f87171; animation: blink 1.5s infinite; }
    .status-badge.ft { background: rgba(16, 185, 129, 0.1); color: #34d399; }

    @keyframes blink {
        0% { opacity: 0.6; }
        50% { opacity: 1; }
        100% { opacity: 0.6; }
    }

    /* Modal Referee */
    .ref-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(8px);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .ref-modal.show {
        opacity: 1;
    }

    .ref-modal-content {
        background: #1f2937;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        width: 90vw;
        max-width: 480px;
        padding: 30px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: translateY(20px);
        transition: transform 0.3s ease;
        position: relative;
    }

    .ref-modal.show .ref-modal-content {
        transform: translateY(0);
    }

    .btn-close-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.05);
        border: none;
        color: #9ca3af;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-close-modal:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
    }

    .ref-modal-title {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 6px;
        color: #ffffff;
    }

    .ref-modal-subtitle {
        color: #9ca3af;
        font-size: 0.9rem;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .rigor-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .rigor-badge.rigoroso { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
    .rigor-badge.moderado { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .rigor-badge.permissivo { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 20px;
    }

    .stat-card-ft {
        background: rgba(17, 24, 39, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 16px;
        text-align: center;
    }

    .stat-card-title {
        font-size: 0.8rem;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .stat-card-val {
        font-size: 1.8rem;
        font-weight: 700;
        color: #ffffff;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: rgba(31, 41, 55, 0.2);
        border: 1px dashed rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        grid-column: 1 / -1;
    }

    .empty-icon {
        font-size: 3.5rem;
        color: #4b5563;
        margin-bottom: 15px;
    }

    /* Ingest status bar */
    .ingest-status-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.85);
        z-index: 999999;
        align-items: center;
        justify-content: center;
        color: white;
        flex-direction: column;
        gap: 15px;
    }

    .loader-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid rgba(16, 185, 129, 0.2);
        border-top-color: #10b981;
        border-radius: 50%;
        animation: spin 1s infinite linear;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="container-fluid">
    <div class="ft-container">

        <!-- Hero Section -->
        <div class="ft-hero">
            <span class="ft-title-badge">⚽ Futebol & Estatísticas</span>
            <h1 class="ft-title">Football Trends</h1>
            <p class="ft-subtitle">
                Análise de probabilidade de cartões baseada no perfil e nível de rigor da arbitragem escalada para cada confronto das principais ligas.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn-update-api" onclick="triggerIngestion('<?= $targetDate ?>')">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Atualizar Dados (API)</span>
                </button>
            </div>
        </div>

        <!-- Controles de Data e Busca -->
        <div class="ft-controls-row">
            <div class="row align-items-center g-3">
                <div class="col-lg-6 col-md-12">
                    <div class="date-navigator">
                        <?php
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $today = date('Y-m-d');
                        $tomorrow = date('Y-m-d', strtotime('+1 day'));
                        ?>
                        <a href="?date=<?= $yesterday ?>" class="date-btn <?= $targetDate === $yesterday ? 'active' : '' ?>">
                            <i class="bi bi-chevron-left"></i> Ontem
                        </a>
                        <a href="?date=<?= $today ?>" class="date-btn <?= $targetDate === $today ? 'active' : '' ?>">
                            Hoje
                        </a>
                        <a href="?date=<?= $tomorrow ?>" class="date-btn <?= $targetDate === $tomorrow ? 'active' : '' ?>">
                            Amanhã <i class="bi bi-chevron-right"></i>
                        </a>
                        <div class="date-picker-wrapper ms-lg-2">
                            <form method="get" id="dateForm" class="d-flex align-items-center">
                                <input type="date" name="date" class="date-input" value="<?= $targetDate ?>" onchange="document.getElementById('dateForm').submit()">
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12">
                    <form method="get" class="d-flex gap-2">
                        <input type="hidden" name="date" value="<?= $targetDate ?>">
                        <div class="search-box-wrapper">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" name="search" class="search-input" placeholder="Buscar por times, liga ou árbitro..." value="<?= htmlspecialchars($search ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-secondary rounded-3" style="padding: 10px 20px;">Filtrar</button>
                        <?php if(!empty($search)): ?>
                            <a href="?date=<?= $targetDate ?>" class="btn btn-outline-danger d-flex align-items-center justify-content-center" style="border-radius: 12px;">Limpar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Abas das Ligas Disponíveis -->
        <?php if (!empty($leagues)): ?>
            <div class="leagues-wrapper">
                <span class="league-pill active" onclick="filterByLeague('all')">Todas as Ligas</span>
                <?php foreach ($leagues as $league): ?>
                    <span class="league-pill" onclick="filterByLeague('<?= htmlspecialchars($league) ?>')"><?= htmlspecialchars($league) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Grid de Partidas -->
        <div class="fixtures-grid" id="fixturesGrid">
            <?php if (empty($fixtures)): ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x empty-icon"></i>
                    <h3>Nenhuma partida cadastrada para esta data</h3>
                    <p class="text-muted">
                        Clique em <strong>Atualizar Dados (API)</strong> para obter dados em tempo real da API-Football.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($fixtures as $fix): ?>
                    <?php
                    // Cores baseadas na probabilidade
                    $prob = (float)$fix->over_cards_probability;
                    if ($prob >= 70.0) {
                        $class = 'high';
                    } elseif ($prob >= 50.0) {
                        $class = 'medium';
                    } else {
                        $class = 'low';
                    }

                    // Formata data e hora
                    $timeStr = '';
                    try {
                        $dt = new DateTime($fix->fixture_date);
                        $timeStr = $dt->format('H:i');
                    } catch (\Exception $e) {}
                    ?>
                    <div class="fixture-card" data-league="<?= htmlspecialchars($fix->league_name) ?>">
                        <div>
                            <!-- Header da Partida -->
                            <div class="card-header-ft">
                                <span class="league-badge" title="<?= htmlspecialchars($fix->league_name) ?>">
                                    <?= htmlspecialchars($fix->league_name) ?>
                                </span>
                                <span class="time-badge">
                                    <i class="bi bi-clock"></i> <?= $timeStr ?>
                                </span>
                            </div>

                            <!-- Confronto -->
                            <div class="teams-section">
                                <div class="team-row">
                                    <span class="team-dot"></span>
                                    <span class="team-name"><?= htmlspecialchars($fix->home_team) ?></span>
                                </div>
                                <div class="team-row">
                                    <span class="team-dot"></span>
                                    <span class="team-name"><?= htmlspecialchars($fix->away_team) ?></span>
                                </div>
                            </div>

                            <div class="divider"></div>

                            <!-- Probabilidades -->
                            <div class="prediction-box">
                                <div class="pred-prob-row">
                                    <span class="pred-prob-label">Over 4.5 Amarelos</span>
                                    <span class="pred-prob-value <?= $class ?>"><?= $prob ?>%</span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill <?= $class ?>" style="width: <?= $prob ?>%"></div>
                                </div>
                            </div>

                            <!-- Análise do Árbitro -->
                            <p class="prediction-text <?= $class ?>">
                                <?= htmlspecialchars($fix->prediction_text) ?>
                            </p>
                        </div>

                        <!-- Rodapé com Árbitro e Status -->
                        <div class="referee-area">
                            <?php if (!empty($fix->referee_name)): ?>
                                <span class="referee-badge" 
                                      onclick="showRefereeDetails(
                                          '<?= htmlspecialchars($fix->referee_name) ?>',
                                          '<?= $fix->rigor_level ?? 'Moderado' ?>',
                                          '<?= $fix->average_yellow_cards ?? '0.00' ?>',
                                          '<?= $fix->average_red_cards ?? '0.00' ?>',
                                          '<?= $fix->average_fouls ?? '0.00' ?>',
                                          '<?= $fix->total_games ?? '0' ?>'
                                      )">
                                    <i class="bi bi-bookmark-star"></i> <?= htmlspecialchars($fix->referee_name) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.85rem;"><i class="bi bi-person-x"></i> Sem Árbitro</span>
                            <?php endif; ?>

                            <?php
                            $statusClean = strtoupper($fix->status);
                            if ($statusClean === 'NS') {
                                $statusLabel = 'A Iniciar';
                                $statusClass = 'ns';
                            } elseif (in_array($statusClean, ['1H', '2H', 'HT', 'ET'])) {
                                $statusLabel = 'Ao Vivo';
                                $statusClass = 'live';
                            } else {
                                $statusLabel = 'Encerrado';
                                $statusClass = 'ft';
                            }
                            ?>
                            <span class="status-badge <?= $statusClass ?>">
                                <?= $statusLabel ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal de Detalhes do Árbitro -->
<div class="ref-modal" id="refereeModal">
    <div class="ref-modal-content">
        <button class="btn-close-modal" onclick="closeRefereeModal()">X</button>
        <h3 class="ref-modal-title" id="modalRefName">Anderson Daronco</h3>
        <div class="ref-modal-subtitle">
            <span>Rigor de Arbitragem:</span>
            <span class="rigor-badge" id="modalRefRigor">Rigoroso</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card-ft">
                <div class="stat-card-title">Média Amarelos</div>
                <div class="stat-card-val" id="modalRefYellow">5.20</div>
            </div>
            <div class="stat-card-ft">
                <div class="stat-card-title">Média Vermelhos</div>
                <div class="stat-card-val" id="modalRefRed">0.24</div>
            </div>
            <div class="stat-card-ft">
                <div class="stat-card-title">Média Faltas</div>
                <div class="stat-card-val" id="modalRefFouls">24.50</div>
            </div>
            <div class="stat-card-ft">
                <div class="stat-card-title">Total Jogos</div>
                <div class="stat-card-val" id="modalRefGames">120</div>
            </div>
        </div>
    </div>
</div>

<!-- Overlay de Progresso de Ingestão -->
<div class="ingest-status-overlay" id="ingestOverlay">
    <div class="loader-spinner"></div>
    <h3 style="font-weight: 600; margin-top: 10px;">Invocando API de Ingestão...</h3>
    <p class="text-muted" style="font-size: 0.95rem;">Por favor, aguarde enquanto o Apache Airflow processa os dados.</p>
</div>

<!-- Scripts de Interação -->
<script>
    // Filtro dinâmico por Ligas
    function filterByLeague(leagueName) {
        // Atualiza estilo das abas
        const pills = document.querySelectorAll('.league-pill');
        pills.forEach(p => p.classList.remove('active'));

        // Define a aba ativa
        event.currentTarget.classList.add('active');

        // Filtra os cards
        const cards = document.querySelectorAll('.fixture-card');
        cards.forEach(card => {
            if (leagueName === 'all' || card.getAttribute('data-league') === leagueName) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Modal do Árbitro
    function showRefereeDetails(name, rigor, yellows, reds, fouls, games) {
        document.getElementById('modalRefName').innerText = name;
        
        const rigorBadge = document.getElementById('modalRefRigor');
        rigorBadge.className = 'rigor-badge ' + rigor.toLowerCase();
        
        // Define o ícone de acordo com o rigor
        let icon = '';
        if (rigor.toLowerCase() === 'rigoroso') icon = '🔥 ';
        else if (rigor.toLowerCase() === 'moderado') icon = '⚖️ ';
        else icon = '❄️ ';
        
        rigorBadge.innerText = icon + rigor;

        document.getElementById('modalRefYellow').innerText = parseFloat(yellows).toFixed(2);
        document.getElementById('modalRefRed').innerText = parseFloat(reds).toFixed(2);
        document.getElementById('modalRefFouls').innerText = parseFloat(fouls).toFixed(2);
        document.getElementById('modalRefGames').innerText = games;

        const modal = document.getElementById('refereeModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function closeRefereeModal() {
        const modal = document.getElementById('refereeModal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
    }

    // Fechar modal ao clicar fora dele
    window.onclick = function(event) {
        const modal = document.getElementById('refereeModal');
        if (event.target === modal) {
            closeRefereeModal();
        }
    }

    // Trigger de Ingestão via Airflow
    function triggerIngestion(date) {
        const overlay = document.getElementById('ingestOverlay');
        overlay.style.display = 'flex';

        const formData = new FormData();
        formData.append('date', date);

        fetch('<?= base_url('football-trends/ingest') ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Sucesso! Ingestão agendada no Airflow. Os dados serão atualizados em instantes.');
                // Recarrega a página após 3 segundos para dar tempo de atualizar o banco
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                overlay.style.display = 'none';
                alert('❌ Erro: ' + (data.message || 'Falha ao acionar a API do Airflow.'));
            }
        })
        .catch(error => {
            overlay.style.display = 'none';
            console.error('Error triggering ingestion:', error);
            alert('❌ Erro de comunicação com o servidor.');
        });
    }
</script>

<?php
require VIEWPATH.'/footer.php';
?>
