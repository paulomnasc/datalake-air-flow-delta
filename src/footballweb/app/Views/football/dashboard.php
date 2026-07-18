<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';

// Formatação amigável da data
setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil');
$dateObj = DateTime::createFromFormat('Y-m-d', $targetDate);
$formattedDateHeader = $dateObj ? strftime('%d de %B de %Y', $dateObj->getTimestamp()) : $targetDate;

// Mapeamento de League ID para País e Bandeira/Ícone (estilo Betano)
$leagueMap = [
    71  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    72  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    73  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => false],
    39  => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true],
    140 => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true],
    135 => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true],
    78  => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true],
    262 => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true],
    128 => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true],
    253 => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true],
    113 => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => true],
    103 => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => true],
    244 => ['country' => 'Finlândia', 'flag' => '🇫🇮', 'popular' => false],
    283 => ['country' => 'Romênia', 'flag' => '🇷🇴', 'popular' => false],
    286 => ['country' => 'Sérvia', 'flag' => '🇷🇸', 'popular' => false],
    281 => ['country' => 'Peru', 'flag' => '🇵🇪', 'popular' => false],
    242 => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
    268 => ['country' => 'Uruguai', 'flag' => '🇺🇾', 'popular' => false],
    265 => ['country' => 'Chile', 'flag' => '🇨🇱', 'popular' => false],
    239 => ['country' => 'Colômbia', 'flag' => '🇨🇴', 'popular' => false],
    169 => ['country' => 'China', 'flag' => '🇨🇳', 'popular' => false],
    307 => ['country' => 'Arábia Saudita', 'flag' => '🇸🇦', 'popular' => false],
    203 => ['country' => 'Turquia', 'flag' => '🇹🇷', 'popular' => false],
    207 => ['country' => 'Suíça', 'flag' => '🇨🇭', 'popular' => false],
    144 => ['country' => 'Bélgica', 'flag' => '🇧🇪', 'popular' => false],
    119 => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
    218 => ['country' => 'Áustria', 'flag' => '🇦🇹', 'popular' => false],
    197 => ['country' => 'Grécia', 'flag' => '🇬🇷', 'popular' => false],
    2   => ['country' => 'Copas Continentais', 'flag' => '🏆', 'popular' => false],
    13  => ['country' => 'Copas Continentais', 'flag' => '🏆', 'popular' => false],
    3   => ['country' => 'Copas Continentais', 'flag' => '🏆', 'popular' => false],
    11  => ['country' => 'Copas Continentais', 'flag' => '🏆', 'popular' => false],
    1   => ['country' => 'Mundo', 'flag' => '🌍', 'popular' => false]
];

// Organiza as partidas e ligas por país/região
$groupedLeagues = [];
$popularLeagues = [];

foreach ($fixtures as $fix) {
    $leagueId = (int)$fix->league_id;
    $leagueName = $fix->league_name;
    
    // Fallback caso não esteja no mapeamento
    $country = 'Outros';
    $flag = '🏳️';
    $isPopular = false;
    
    if (isset($leagueMap[$leagueId])) {
        $country = $leagueMap[$leagueId]['country'];
        $flag = $leagueMap[$leagueId]['flag'];
        $isPopular = $leagueMap[$leagueId]['popular'];
    }
    
    // Agrupa ligas por país
    if (!isset($groupedLeagues[$country])) {
        $groupedLeagues[$country] = [
            'flag' => $flag,
            'leagues' => []
        ];
    }
    if (!in_array($leagueName, $groupedLeagues[$country]['leagues'])) {
        $groupedLeagues[$country]['leagues'][] = $leagueName;
    }
    
    // Populares
    if ($isPopular && !in_array($leagueName, $popularLeagues)) {
        $popularLeagues[] = $leagueName;
    }
}
ksort($groupedLeagues); // Ordena países alfabeticamente
?>

<style>
    /* Google Fonts & Root Design System */
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    .ft-container {
        font-family: 'Outfit', sans-serif;
        background: #0e1620;
        color: #f3f4f6;
        min-height: 100vh;
        padding: 30px 15px;
        border-radius: 16px;
        margin: 20px 0;
        box-shadow: 0 15px 45px rgba(0,0,0,0.5);
    }

    /* Betano Branding Header */
    .bet-brand-header {
        border-bottom: 3px solid #f47c20;
        padding-bottom: 15px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .bet-brand-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .bet-brand-logo {
        background: #f47c20;
        color: white;
        font-weight: 900;
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 1.4rem;
        letter-spacing: -1px;
        text-transform: uppercase;
        display: inline-block;
        box-shadow: 0 4px 10px rgba(244, 124, 32, 0.4);
    }

    .bet-brand-subtitle {
        font-size: 1.5rem;
        font-weight: 800;
        color: #ffffff;
        margin: 0;
    }

    /* Sidebar Columns */
    .bet-sidebar {
        background: #172230;
        border-radius: 12px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.05);
        margin-bottom: 20px;
        max-height: 85vh;
        overflow-y: auto;
    }

    .bet-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .bet-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }

    .bet-section-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: #8a99a8;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .bet-sidebar-list {
        list-style: none;
        padding: 0;
        margin: 0 0 25px 0;
    }

    .bet-sidebar-item {
        margin-bottom: 6px;
    }

    .bet-league-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 8px;
        color: #aeb9c4;
        text-decoration: none !important;
        font-size: 0.92rem;
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
        border-left: 3px solid transparent;
    }

    .bet-league-link:hover {
        background: rgba(255, 255, 255, 0.04);
        color: #ffffff;
    }

    .bet-league-link.active {
        background: rgba(244, 124, 32, 0.15);
        color: #ffffff;
        border-left-color: #f47c20;
        font-weight: 600;
    }

    /* Accordion Countries */
    .bet-country-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border-radius: 8px;
        color: #d1d5db;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
    }

    .bet-country-header:hover {
        background: rgba(255, 255, 255, 0.04);
        color: #ffffff;
    }

    .bet-country-header.active {
        background: rgba(255, 255, 255, 0.02);
        color: #ffffff;
    }

    .bet-country-chevron {
        font-size: 0.8rem;
        color: #8a99a8;
        transition: transform 0.2s;
    }

    .bet-country-header.active .bet-country-chevron {
        transform: rotate(180deg);
    }

    .bet-country-content {
        display: none;
        padding-left: 15px;
        margin-top: 4px;
        margin-bottom: 8px;
    }

    /* Main Area Controls */
    .bet-controls-row {
        background: #172230;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Date buttons */
    .bet-date-btn {
        background: #0f1620;
        border: 1px solid rgba(255, 255, 255, 0.05);
        color: #aeb9c4;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .bet-date-btn:hover {
        background: #1d2b3c;
        color: #ffffff;
    }

    .bet-date-btn.active {
        background: #f47c20;
        border-color: #f47c20;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(244, 124, 32, 0.3);
    }

    .bet-date-input {
        background: #0f1620;
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
        outline: none;
        transition: all 0.2s;
    }

    .bet-date-input:focus {
        border-color: #f47c20;
    }

    /* Search field */
    .bet-search-input {
        background: #0f1620;
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: white;
        padding: 8px 12px 8px 36px;
        border-radius: 8px;
        width: 100%;
        outline: none;
        transition: all 0.2s;
    }

    .bet-search-input:focus {
        border-color: #f47c20;
    }

    .bet-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #8a99a8;
    }

    /* Toggle Switch */
    .bet-switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
        margin: 0;
    }

    .bet-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .bet-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #0f1620;
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: .3s;
    }

    .bet-slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: #8a99a8;
        transition: .3s;
    }

    input:checked + .bet-slider {
        background-color: rgba(244, 124, 32, 0.2);
        border-color: #f47c20;
    }

    input:checked + .bet-slider:before {
        background-color: #f47c20;
        transform: translateX(22px);
    }

    .bet-slider.round {
        border-radius: 34px;
    }

    .bet-slider.round:before {
        border-radius: 50%;
    }

    /* Betano Tabs navigation */
    .bet-tabs {
        display: flex;
        border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        margin-bottom: 25px;
        gap: 20px;
    }

    .bet-tab {
        padding: 12px 10px;
        font-weight: 700;
        color: #8a99a8;
        cursor: pointer;
        font-size: 1.05rem;
        transition: all 0.2s;
        border-bottom: 3px solid transparent;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bet-tab:hover {
        color: #ffffff;
    }

    .bet-tab.active {
        color: #ffffff;
        border-bottom-color: #f47c20;
    }

    /* Cards Grid & redone Betano cards */
    .bet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }

    .bet-card {
        background: #172230;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
        padding: 20px;
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }

    .bet-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.5), 0 0 15px rgba(244, 124, 32, 0.1);
        border-color: rgba(244, 124, 32, 0.2);
    }

    .bet-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .bet-league-badge {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.06);
        color: #aeb9c4;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        max-width: 70%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .bet-time-badge {
        color: #f47c20;
        font-weight: 700;
        font-size: 0.8rem;
        background: rgba(244, 124, 32, 0.08);
        padding: 3px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .bet-time-container {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
    }

    .bet-elapsed-time {
        font-size: 0.72rem;
        font-weight: 600;
        color: #aeb9c4;
        text-align: right;
    }

    .bet-elapsed-time.live {
        color: #f87171;
        font-weight: 700;
        animation: bet-blink 1.5s infinite;
    }

    .bet-teams-box {
        margin-bottom: 15px;
    }

    .bet-team-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 6px;
    }

    .bet-team-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #f47c20;
    }

    .bet-team-row:last-child .bet-team-dot {
        background: #3b82f6;
    }

    .bet-team-name {
        font-weight: 700;
        font-size: 1.1rem;
        color: #ffffff;
    }

    .bet-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.05);
        margin: 12px 0;
    }

    /* Betano style progress & probability */
    .bet-prob-container {
        margin-bottom: 12px;
    }

    .bet-prob-label {
        font-size: 0.8rem;
        color: #8a99a8;
        font-weight: 600;
    }

    .bet-prob-value-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }

    .bet-prob-value {
        font-weight: 800;
        font-size: 1.1rem;
    }

    .bet-prob-value.high { color: #f47c20; }
    .bet-prob-value.medium { color: #fbbf24; }
    .bet-prob-value.low { color: #10b981; }

    .bet-progress-track {
        height: 5px;
        background: rgba(255, 255, 255, 0.06);
        border-radius: 4px;
        overflow: hidden;
    }

    .bet-progress-fill {
        height: 100%;
        border-radius: 4px;
    }

    .bet-progress-fill.high { background: #f47c20; }
    .bet-progress-fill.medium { background: #fbbf24; }
    .bet-progress-fill.low { background: #10b981; }

    .bet-pred-text {
        font-size: 0.84rem;
        color: #d1d5db;
        line-height: 1.45;
        background: rgba(255, 255, 255, 0.01);
        padding: 8px 10px;
        border-radius: 6px;
        border-left: 3px solid #f47c20;
    }

    .bet-pred-text.high { border-left-color: #f47c20; }
    .bet-pred-text.medium { border-left-color: #fbbf24; }
    .bet-pred-text.low { border-left-color: #10b981; }

    /* Betano style footer of cards */
    .bet-referee-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
    }

    .bet-referee-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        color: #aeb9c4;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }

    .bet-referee-btn:hover {
        background: rgba(244, 124, 32, 0.1);
        border-color: rgba(244, 124, 32, 0.3);
        color: #ffffff;
    }

    .bet-status {
        font-size: 0.72rem;
        font-weight: 800;
        padding: 3px 6px;
        border-radius: 4px;
        text-transform: uppercase;
    }

    .bet-status.ns { background: rgba(156, 163, 175, 0.08); color: #9ca3af; }
    .bet-status.live { background: rgba(239, 68, 68, 0.12); color: #f87171; animation: bet-blink 1.5s infinite; }
    .bet-status.ft { background: rgba(16, 185, 129, 0.08); color: #34d399; }

    @keyframes bet-blink {
        0% { opacity: 0.6; }
        50% { opacity: 1; }
        100% { opacity: 0.6; }
    }

    /* Modal Referee Styles */
    .bet-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .bet-modal.show {
        opacity: 1;
    }

    .bet-modal-content {
        background: #172230;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        width: 90vw;
        max-width: 440px;
        padding: 25px;
        position: relative;
        transform: translateY(20px);
        transition: transform 0.3s ease;
    }

    .bet-modal.show .bet-modal-content {
        transform: translateY(0);
    }

    .bet-modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        color: #8a99a8;
        font-size: 1.2rem;
        cursor: pointer;
    }

    .bet-modal-close:hover {
        color: white;
    }

    .bet-modal-title {
        font-size: 1.4rem;
        font-weight: 800;
        color: white;
        margin-bottom: 4px;
    }

    .bet-modal-subtitle {
        color: #8a99a8;
        font-size: 0.88rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .bet-rigor-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .bet-rigor-badge.rigoroso { background: rgba(244, 124, 32, 0.15); color: #f47c20; }
    .bet-rigor-badge.moderado { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
    .bet-rigor-badge.permissivo { background: rgba(16, 185, 129, 0.15); color: #34d399; }

    .bet-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .bet-stat-card {
        background: #0f1620;
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: 10px;
        padding: 12px;
        text-align: center;
    }

    .bet-stat-title {
        font-size: 0.72rem;
        color: #8a99a8;
        text-transform: uppercase;
        margin-bottom: 4px;
        font-weight: 700;
    }

    .bet-stat-val {
        font-size: 1.6rem;
        font-weight: 800;
        color: white;
    }

    /* API Trigger Overlay */
    .bet-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(14, 22, 32, 0.95);
        z-index: 999999;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: white;
        gap: 15px;
    }

    .bet-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(244, 124, 32, 0.2);
        border-top-color: #f47c20;
        border-radius: 50%;
        animation: bet-spin 1s infinite linear;
    }

    @keyframes bet-spin {
        100% { transform: rotate(360deg); }
    }

    .bet-empty {
        text-align: center;
        padding: 50px 20px;
        background: #172230;
        border: 1px dashed rgba(255,255,255,0.08);
        border-radius: 12px;
        grid-column: 1 / -1;
    }

    .btn-update-betano {
        background: #f47c20;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 700;
        transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(244, 124, 32, 0.3);
    }

    .btn-update-betano:hover {
        background: #ff8e38;
        transform: translateY(-2px);
    }

    /* Chevron rotation for accordion */
    .rotate-180 {
        transform: rotate(180deg);
    }

    /* AI Chat Button inside card */
    .bet-ai-btn {
        background: rgba(244, 124, 32, 0.1);
        border: 1px solid rgba(244, 124, 32, 0.25);
        color: #f47c20;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 5px 10px;
        border-radius: 20px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
    }

    .bet-ai-btn:hover {
        background: #f47c20;
        color: white;
        box-shadow: 0 0 10px rgba(244, 124, 32, 0.4);
    }

    /* AI Chat Drawer Style */
    .bet-chat-drawer {
        position: fixed;
        top: 0;
        right: -400px;
        width: 400px;
        height: 100vh;
        background: #172230;
        border-left: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: -10px 0 30px rgba(0,0,0,0.5);
        z-index: 100000;
        transition: right 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .bet-chat-drawer.open {
        right: 0;
    }

    .bet-chat-header {
        background: #0f1620;
        padding: 20px;
        border-bottom: 2px solid #f47c20;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .bet-chat-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .bet-chat-close-btn {
        background: none;
        border: none;
        color: #8a99a8;
        font-size: 1.2rem;
        cursor: pointer;
    }

    .bet-chat-close-btn:hover {
        color: white;
    }

    .bet-chat-game-context {
        background: rgba(14, 22, 32, 0.5);
        padding: 10px 20px;
        font-size: 0.8rem;
        color: #8a99a8;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .bet-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .bet-chat-msg {
        max-width: 85%;
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 0.88rem;
        line-height: 1.4;
    }

    .bet-chat-msg.ai {
        align-self: flex-start;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        color: #e5e7eb;
        border-top-left-radius: 2px;
    }

    .bet-chat-msg.user {
        align-self: flex-end;
        background: #f47c20;
        color: white;
        border-top-right-radius: 2px;
    }

    .bet-chat-input-area {
        padding: 15px 20px;
        background: #0f1620;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        gap: 10px;
    }

    .bet-chat-input {
        flex: 1;
        background: #172230;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 8px 12px;
        color: white;
        outline: none;
        font-size: 0.88rem;
    }

    .bet-chat-input:focus {
        border-color: #f47c20;
    }

    .bet-chat-send-btn {
        background: #f47c20;
        color: white;
        border: none;
        border-radius: 8px;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .bet-chat-send-btn:hover {
        background: #ff8e38;
    }

    /* Typing indicators */
    .typing-loader {
        display: flex;
        gap: 4px;
        padding: 4px 6px;
    }

    .typing-dot {
        width: 6px;
        height: 6px;
        background: #8a99a8;
        border-radius: 50%;
        animation: typing-blink 1.4s infinite both;
    }

    .typing-dot:nth-child(2) { animation-delay: .2s; }
    .typing-dot:nth-child(3) { animation-delay: .4s; }

    @keyframes typing-blink {
        0% { opacity: .2; }
        20% { opacity: 1; }
        100% { opacity: .2; }
    }
</style>

<div class="container-fluid">
    <div class="ft-container">
        
        <!-- Header / Brand Section -->
        <div class="bet-brand-header">
            <div class="bet-brand-title">
                <span class="bet-brand-logo">Bet</span>
                <h1 class="bet-brand-subtitle">Trends</h1>
            </div>
            <div>
                <button type="button" class="btn-update-betano" onclick="triggerIngestion('<?= $targetDate ?>')">
                    <i class="bi bi-arrow-repeat"></i> Atualizar Dados (API)
                </button>
            </div>
        </div>

        <div class="row g-4">
            <!-- Coluna Esquerda: Sidebar (Accordion de Competições estilo Betano) -->
            <div class="col-lg-3 col-md-4">
                <div class="bet-sidebar">
                    
                    <!-- Bloco POPULARES -->
                    <div class="bet-section-title">
                        <span>Populares</span>
                        <i class="bi bi-star-fill" style="color: #f47c20;"></i>
                    </div>
                    <ul class="bet-sidebar-list">
                        <li class="bet-sidebar-item">
                            <a class="bet-league-link active" onclick="filterByLeague('all')" id="league-link-all">
                                ⚽ Todas as Ligas
                            </a>
                        </li>
                        <?php foreach ($popularLeagues as $popLeague): ?>
                            <li class="bet-sidebar-item">
                                <a class="bet-league-link" onclick="filterByLeague('<?= htmlspecialchars($popLeague) ?>')" data-league-name="<?= htmlspecialchars($popLeague) ?>">
                                    ⭐ <?= htmlspecialchars($popLeague) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Bloco COMPETIÇÕES PRINCIPAIS -->
                    <div class="bet-section-title">
                        <span>Competições Principais</span>
                        <i class="bi bi-trophy-fill" style="color: #8a99a8;"></i>
                    </div>
                    
                    <div class="bet-accordion" id="betCountryAccordion">
                        <?php $countryIdx = 0; ?>
                        <?php foreach ($groupedLeagues as $countryName => $cData): ?>
                            <?php $countryIdx++; ?>
                            <div class="mb-2">
                                <div class="bet-country-header" onclick="toggleCountryAccordion('country-<?= $countryIdx ?>')">
                                    <span class="d-flex align-items-center gap-2">
                                        <span><?= $cData['flag'] ?></span>
                                        <span><?= htmlspecialchars($countryName) ?></span>
                                    </span>
                                    <i class="bi bi-chevron-down bet-country-chevron" id="chevron-country-<?= $countryIdx ?>"></i>
                                </div>
                                <div class="bet-country-content" id="content-country-<?= $countryIdx ?>">
                                    <ul class="bet-sidebar-list" style="margin-bottom: 0;">
                                        <?php foreach ($cData['leagues'] as $lName): ?>
                                            <li class="bet-sidebar-item">
                                                <a class="bet-league-link" onclick="filterByLeague('<?= htmlspecialchars($lName) ?>')" data-league-name="<?= htmlspecialchars($lName) ?>">
                                                    🔹 <?= htmlspecialchars($lName) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Conteúdo Principal -->
            <div class="col-lg-9 col-md-8">
                
                <!-- Controles de Data e Pesquisa -->
                <div class="bet-controls-row">
                    <form method="get" id="filterForm" class="m-0">
                        <div class="row align-items-center g-3">
                            <div class="col-lg-5 col-md-12">
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <?php
                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                                    $today = date('Y-m-d');
                                    $tomorrow = date('Y-m-d', strtotime('+1 day'));
                                    $showFinishedQuery = $showFinished ? '&show_finished=1' : '';
                                    $searchQuery = !empty($search) ? '&search=' . urlencode($search) : '';
                                    ?>
                                    <a href="?date=<?= $yesterday ?><?= $showFinishedQuery ?><?= $searchQuery ?>" class="bet-date-btn <?= $targetDate === $yesterday ? 'active' : '' ?>">
                                        <i class="bi bi-chevron-left"></i> Ontem
                                    </a>
                                    <a href="?date=<?= $today ?><?= $showFinishedQuery ?><?= $searchQuery ?>" class="bet-date-btn <?= $targetDate === $today ? 'active' : '' ?>">
                                        Hoje
                                    </a>
                                    <a href="?date=<?= $tomorrow ?><?= $showFinishedQuery ?><?= $searchQuery ?>" class="bet-date-btn <?= $targetDate === $tomorrow ? 'active' : '' ?>">
                                        Amanhã <i class="bi bi-chevron-right"></i>
                                    </a>
                                    <div class="position-relative d-inline-block">
                                        <input type="date" name="date" class="bet-date-input" value="<?= $targetDate ?>" onchange="document.getElementById('filterForm').submit()">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Toggle switch column in between -->
                            <div class="col-lg-3 col-md-6 d-flex align-items-center justify-content-lg-center">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bet-toggle-label" style="font-size: 0.9rem; color: #aeb9c4; font-weight: 600;">Ver Encerrados?</span>
                                    <label class="bet-switch">
                                        <input type="checkbox" name="show_finished" value="1" <?= $showFinished ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                                        <span class="bet-slider round"></span>
                                    </label>
                                    <span class="bet-toggle-status" style="font-size: 0.9rem; font-weight: 700; color: <?= $showFinished ? '#f47c20' : '#8a99a8' ?>;">
                                        <?= $showFinished ? 'Sim' : 'Não' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Search column -->
                            <div class="col-lg-4 col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="position-relative flex-grow-1">
                                        <i class="bi bi-search bet-search-icon"></i>
                                        <input type="text" name="search" class="bet-search-input" placeholder="Buscar times, liga ou árbitro..." value="<?= htmlspecialchars($search ?? '') ?>">
                                    </div>
                                    <button type="submit" class="btn btn-secondary rounded-3 px-3">Filtrar</button>
                                    <?php if(!empty($search) || $showFinished): ?>
                                        <a href="?date=<?= $targetDate ?>" class="btn btn-outline-danger d-flex align-items-center justify-content-center px-3" style="border-radius: 8px;">Limpar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Abas estilo Betano: Destaques vs Todas as Partidas -->
                <div class="bet-tabs">
                    <div class="bet-tab active" id="tab-competicoes" onclick="switchMainTab('competicoes')">Competições</div>
                    <div class="bet-tab" id="tab-destaques" onclick="switchMainTab('destaques')">Destaques (Probabilidade 🔥)</div>
                </div>

                <!-- Grid de Partidas -->
                <div class="bet-grid" id="fixturesGrid">
                    <?php if (empty($fixtures)): ?>
                        <div class="bet-empty">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #8a99a8; display: block; margin-bottom: 15px;"></i>
                            <h3>Nenhuma partida disponível para esta data</h3>
                            <p class="text-muted">
                                Clique em <strong>Atualizar Dados (API)</strong> no topo para sincronizar os confrontos ativos da API-Football.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fixtures as $fix): ?>
                            <?php
                            $prob = (float)$fix->over_cards_probability;
                            if ($prob >= 70.0) {
                                $class = 'high';
                            } elseif ($prob >= 50.0) {
                                $class = 'medium';
                            } else {
                                $class = 'low';
                            }

                            // Formata hora convertendo de UTC para America/Sao_Paulo
                            $timeStr = '';
                            try {
                                $dt = new DateTime($fix->fixture_date, new DateTimeZone('UTC'));
                                $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                $timeStr = $dt->format('H:i');
                            } catch (\Exception $e) {}

                            // Calcula o tempo decorrido do jogo em PHP (inicial)
                            $elapsedText = '';
                            $elapsedClass = '';
                            try {
                                $now = new DateTime('now', new DateTimeZone('UTC'));
                                $start = new DateTime($fix->fixture_date, new DateTimeZone('UTC'));
                                $diffMins = floor(($now->getTimestamp() - $start->getTimestamp()) / 60);
                                
                                $finishedStatuses = ['FT', 'AET', 'PEN', '120', '90'];
                                $statusClean = strtoupper($fix->status);
                                
                                if (in_array($statusClean, $finishedStatuses)) {
                                    $elapsedText = 'Encerrado';
                                } elseif ($statusClean === 'HT') {
                                    $elapsedText = 'Intervalo';
                                    $elapsedClass = 'live';
                                } elseif ($diffMins < 0) {
                                    $elapsedText = 'Não iniciado';
                                } else {
                                    if ($diffMins > 120) {
                                        $elapsedText = 'Encerrado';
                                    } else {
                                        $elapsedText = $diffMins . "'";
                                        $elapsedClass = 'live';
                                    }
                                }
                            } catch (\Exception $e) {
                                $elapsedText = '-';
                            }
                            ?>
                            <div class="bet-card" data-league="<?= htmlspecialchars($fix->league_name) ?>" data-prob="<?= $prob ?>">
                                <div>
                                    <!-- Header -->
                                    <div class="bet-card-header">
                                        <span class="bet-league-badge" title="<?= htmlspecialchars($fix->league_name) ?>">
                                            <?= htmlspecialchars($fix->league_name) ?>
                                        </span>
                                        <div class="bet-time-container">
                                            <span class="bet-time-badge">
                                                <i class="bi bi-clock"></i> <?= $timeStr ?>
                                            </span>
                                            <span class="bet-elapsed-time <?= $elapsedClass ?>" data-start-utc="<?= $fix->fixture_date ?>" data-status="<?= $statusClean ?>">
                                                <?= $elapsedText ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Confronto -->
                                    <div class="bet-teams-box">
                                        <div class="bet-team-row">
                                            <span class="bet-team-dot"></span>
                                            <span class="bet-team-name"><?= htmlspecialchars($fix->home_team) ?></span>
                                        </div>
                                        <div class="bet-team-row">
                                            <span class="bet-team-dot"></span>
                                            <span class="bet-team-name"><?= htmlspecialchars($fix->away_team) ?></span>
                                        </div>
                                    </div>

                                    <div class="bet-divider"></div>

                                    <!-- Probabilidade de Cartões -->
                                    <div class="bet-prob-container">
                                        <div class="bet-prob-value-row">
                                            <span class="bet-prob-label">Mais de 4.5 Cartões</span>
                                            <span class="bet-prob-value <?= $class ?>"><?= $prob ?>%</span>
                                        </div>
                                        <div class="bet-progress-track">
                                            <div class="bet-progress-fill <?= $class ?>" style="width: <?= $prob ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Análise -->
                                    <p class="bet-pred-text <?= $class ?>">
                                        <?= htmlspecialchars($fix->prediction_text) ?>
                                    </p>
                                </div>

                                <!-- Rodapé com Árbitro e Status -->
                                <div class="bet-referee-bar">
                                    <?php if (!empty($fix->referee_name)): ?>
                                        <span class="bet-referee-btn" onclick="showRefereeDetails(
                                            '<?= htmlspecialchars($fix->referee_name) ?>',
                                            '<?= $fix->rigor_level ?? 'Moderado' ?>',
                                            '<?= $fix->average_yellow_cards ?? '0.00' ?>',
                                            '<?= $fix->average_red_cards ?? '0.00' ?>',
                                            '<?= $fix->average_fouls ?? '0.00' ?>',
                                            '<?= $fix->total_games ?? '0' ?>'
                                        )">
                                            <i class="bi bi-person-fill"></i> <?= htmlspecialchars($fix->referee_name) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.8rem;"><i class="bi bi-person-x"></i> Sem Árbitro</span>
                                    <?php endif; ?>

                                    <!-- Botão Conversar com Grok AI -->
                                    <button class="bet-ai-btn" title="Conversar com o Assistente de IA Grok" onclick="openAiChat(
                                        '<?= htmlspecialchars($fix->home_team) ?>',
                                        '<?= htmlspecialchars($fix->away_team) ?>',
                                        '<?= htmlspecialchars($fix->league_name) ?>',
                                        '<?= htmlspecialchars($fix->referee_name ?? '') ?>',
                                        '<?= htmlspecialchars($fix->prediction_text) ?>',
                                        '<?= $prob ?>'
                                    )">
                                        <i class="bi bi-chat-left-text-fill"></i> Grok AI
                                    </button>

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
                                    <span class="bet-status <?= $statusClass ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- Modal Referee -->
<div class="bet-modal" id="refereeModal">
    <div class="bet-modal-content">
        <button class="bet-modal-close" onclick="closeRefereeModal()"><i class="bi bi-x-lg"></i></button>
        <h3 class="bet-modal-title" id="modalRefName">Anderson Daronco</h3>
        <div class="bet-modal-subtitle">
            <span>Rigor de Arbitragem:</span>
            <span class="bet-rigor-badge" id="modalRefRigor">Rigoroso</span>
        </div>

        <div class="bet-stats-grid">
            <div class="bet-stat-card">
                <div class="bet-stat-title">Média Amarelos</div>
                <div class="bet-stat-val" id="modalRefYellow">5.20</div>
            </div>
            <div class="bet-stat-card">
                <div class="bet-stat-title">Média Vermelhos</div>
                <div class="bet-stat-val" id="modalRefRed">0.24</div>
            </div>
            <div class="bet-stat-card">
                <div class="bet-stat-title">Média Faltas</div>
                <div class="bet-stat-val" id="modalRefFouls">24.50</div>
            </div>
            <div class="bet-stat-card">
                <div class="bet-stat-title">Total Jogos</div>
                <div class="bet-stat-val" id="modalRefGames">120</div>
            </div>
        </div>
    </div>
</div>

<!-- Overlay Ingest -->
<div class="bet-overlay" id="ingestOverlay">
    <div class="bet-spinner"></div>
    <h4 style="font-weight: 700; margin: 10px 0 0 0;">Invocando API de Ingestão...</h4>
    <p class="text-muted" style="font-size: 0.9rem; margin: 0;">O Apache Airflow está processando a chamada. Por favor, aguarde.</p>
</div>

<!-- AI Chat Drawer -->
<div class="bet-chat-drawer" id="chatDrawer">
    <div class="bet-chat-header">
        <h3 class="bet-chat-title">
            <i class="bi bi-robot" style="color: #f47c20;"></i> Grok AI Assistant
        </h3>
        <button class="bet-chat-close-btn" onclick="closeAiChat()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="bet-chat-game-context">
        <span>Partida: </span><strong id="chatContextText">Pumas vs Pachuca</strong>
    </div>
    <div class="bet-chat-messages" id="chatMessages">
        <!-- Messages will go here -->
    </div>
    <div class="bet-chat-input-area">
        <input type="text" class="bet-chat-input" id="chatInput" placeholder="Pergunte ao Grok sobre mercados e estatísticas...">
        <button class="bet-chat-send-btn" onclick="sendUserChatMessage()"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<!-- Scripts de Interação -->
<script>
    let currentLeagueFilter = 'all';
    let currentTabFilter = 'competicoes';

    // Estado e Histórico do Chatbot
    let chatHistory = [];
    let activeChatContext = null;

    function openAiChat(homeTeam, awayTeam, leagueName, refereeName, predictionText, prob) {
        activeChatContext = { homeTeam, awayTeam, leagueName, refereeName, predictionText, prob };
        chatHistory = []; // Limpa o histórico de sessões anteriores
        
        document.getElementById('chatContextText').innerText = `${homeTeam} vs ${awayTeam} (${leagueName})`;
        
        const messagesArea = document.getElementById('chatMessages');
        messagesArea.innerHTML = '';
        
        const welcomeText = `Fala, apostador! Sou o Grok. Analisando o jogo **${homeTeam} vs ${awayTeam}** (${leagueName}) com probabilidade de **${prob}%** para Over 4.5 Cartões.\n\n`
            + `Se o mercado tradicional de **Total de Cartões (Mais de 4.5)** estiver fechado ou limitado na Betano para este jogo, recomendo buscar opções como:\n`
            + `* **Ambas as equipes receberão 2 ou mais cartões** (opção muito segura quando as estatísticas de cartões são altas);\n`
            + `* **Total de Cartões por Equipe** (Mais de 1.5 ou 2.5 cartões para um dos times);\n`
            + `* **Total de Cartões no 1º Tempo**.\n\n`
            + `Em que posso te ajudar a interpretar as estatísticas desse confronto?`;
            
        appendChatMessage('ai', welcomeText);
        
        const drawer = document.getElementById('chatDrawer');
        drawer.classList.add('open');
        
        setTimeout(() => document.getElementById('chatInput').focus(), 300);
    }

    function closeAiChat() {
        const drawer = document.getElementById('chatDrawer');
        drawer.classList.remove('open');
        activeChatContext = null;
        chatHistory = [];
    }

    function appendChatMessage(role, text) {
        const messagesArea = document.getElementById('chatMessages');
        const msgDiv = document.createElement('div');
        msgDiv.className = `bet-chat-msg ${role}`;
        
        // Formata negritos e quebras de linha
        let formattedText = text.replace(/\*\*(.*?)\*\"/g, '<strong>$1</strong>'); // Replaces **text**
        // fallback in case of standard formatting
        formattedText = formattedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formattedText = formattedText.replace(/\n/g, '<br>');
        
        msgDiv.innerHTML = formattedText;
        messagesArea.appendChild(msgDiv);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function sendUserChatMessage() {
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        if (!text || !activeChatContext) return;
        
        input.value = '';
        input.disabled = true;
        
        appendChatMessage('user', text);
        
        // Typing indicator
        const messagesArea = document.getElementById('chatMessages');
        const loaderDiv = document.createElement('div');
        loaderDiv.className = 'bet-chat-msg ai';
        loaderDiv.id = 'chatTypingLoader';
        loaderDiv.innerHTML = `<div class="typing-loader"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>`;
        messagesArea.appendChild(loaderDiv);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        const formData = new FormData();
        formData.append('home_team', activeChatContext.homeTeam);
        formData.append('away_team', activeChatContext.awayTeam);
        formData.append('league_name', activeChatContext.leagueName);
        formData.append('referee_name', activeChatContext.refereeName);
        formData.append('prediction_text', activeChatContext.predictionText);
        formData.append('over_cards_probability', activeChatContext.prob);
        formData.append('message', text);
        formData.append('history', JSON.stringify(chatHistory));
        
        fetch('/football-trends/ask-ai', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const loader = document.getElementById('chatTypingLoader');
            if (loader) loader.remove();
            
            input.disabled = false;
            input.focus();
            
            if (data.success) {
                const aiResponse = data.response;
                appendChatMessage('ai', aiResponse);
                
                chatHistory.push({ role: 'user', content: text });
                chatHistory.push({ role: 'assistant', content: aiResponse });
            } else {
                appendChatMessage('ai', `❌ Erro: ${data.message || 'Falha ao processar.'}`);
            }
        })
        .catch(error => {
            const loader = document.getElementById('chatTypingLoader');
            if (loader) loader.remove();
            
            input.disabled = false;
            input.focus();
            console.error('Chat error:', error);
            appendChatMessage('ai', '❌ Erro de comunicação com o servidor.');
        });
    }

    function updateElapsedTimes() {
        const now = new Date();
        const nowUtc = now.getTime();
        
        document.querySelectorAll('.bet-elapsed-time').forEach(el => {
            const startDateStr = el.getAttribute('data-start-utc');
            const status = el.getAttribute('data-status');
            
            if (!startDateStr) return;
            
            // Converte "YYYY-MM-DD HH:MM:SS" (UTC) para ISO UTC ("YYYY-MM-DDTHH:MM:SSZ")
            const utcDateStr = startDateStr.replace(' ', 'T') + 'Z';
            const startDate = new Date(utcDateStr);
            const startUtc = startDate.getTime();
            
            const diffMs = nowUtc - startUtc;
            const diffMins = Math.floor(diffMs / 60000);
            
            let text = '';
            
            const finishedStatuses = ['FT', 'AET', 'PEN', '120', '90'];
            
            if (finishedStatuses.includes(status)) {
                text = 'Encerrado';
                el.classList.remove('live');
            } else if (status === 'HT') {
                text = 'Intervalo';
                el.classList.add('live');
            } else if (diffMins < 0) {
                text = 'Não iniciado';
                el.classList.remove('live');
            } else {
                if (diffMins > 120) {
                    text = 'Encerrado';
                    el.classList.remove('live');
                } else {
                    text = diffMins + "'";
                    el.classList.add('live');
                }
            }
            
            el.innerText = text;
        });
    }

    // Configura o teclado para enviar com Enter e inicializa tempos decorridos
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('chatInput');
        if (input) {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendUserChatMessage();
                }
            });
        }

        // Inicializa e agenda a atualização do tempo decorrido
        updateElapsedTimes();
        setInterval(updateElapsedTimes, 10000);
    });

    // Aplica os filtros combinados (Liga + Aba de Destaques)
    function applyFilters() {
        const cards = document.querySelectorAll('.bet-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const cardLeague = card.getAttribute('data-league');
            const cardProb = parseFloat(card.getAttribute('data-prob') || '0');
            
            const matchLeague = (currentLeagueFilter === 'all' || cardLeague === currentLeagueFilter);
            const matchTab = (currentTabFilter === 'competicoes' || cardProb >= 70.0);
            
            if (matchLeague && matchTab) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Trata o empty state se não houver cards visíveis
        const emptyState = document.querySelector('.bet-empty');
        if (emptyState) {
            if (visibleCount === 0) {
                emptyState.style.display = 'block';
            } else {
                emptyState.style.display = 'none';
            }
        }
    }

    // Filtro por Ligas
    function filterByLeague(leagueName) {
        currentLeagueFilter = leagueName;
        
        // Atualiza classes ativas na barra lateral
        const links = document.querySelectorAll('.bet-league-link');
        links.forEach(link => {
            link.classList.remove('active');
            const linkLeague = link.getAttribute('data-league-name');
            if (leagueName === 'all' && link.id === 'league-link-all') {
                link.classList.add('active');
            } else if (linkLeague === leagueName) {
                link.classList.add('active');
            }
        });
        
        applyFilters();
    }

    // Filtro pelas Abas (Competições vs Destaques)
    function switchMainTab(tabName) {
        currentTabFilter = tabName;
        
        const tabComp = document.getElementById('tab-competicoes');
        const tabDest = document.getElementById('tab-destaques');
        
        if (tabName === 'competicoes') {
            tabComp.classList.add('active');
            tabDest.classList.remove('active');
        } else {
            tabComp.classList.remove('active');
            tabDest.classList.add('active');
        }
        
        applyFilters();
    }

    // Controle de expansão do accordion de países
    function toggleCountryAccordion(id) {
        const content = document.getElementById('content-' + id);
        const chevron = document.getElementById('chevron-' + id);
        
        if (content.style.display === 'block') {
            content.style.display = 'none';
            chevron.classList.remove('rotate-180');
        } else {
            content.style.display = 'block';
            chevron.classList.add('rotate-180');
        }
    }

    // Modal do Árbitro
    function showRefereeDetails(name, rigor, yellows, reds, fouls, games) {
        document.getElementById('modalRefName').innerText = name;
        
        const rigorBadge = document.getElementById('modalRefRigor');
        rigorBadge.className = 'bet-rigor-badge ' + rigor.toLowerCase();
        
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
        
        const drawer = document.getElementById('chatDrawer');
        if (drawer && drawer.classList.contains('open') && !drawer.contains(event.target) && !event.target.closest('.bet-ai-btn')) {
            closeAiChat();
        }
    }

    // Trigger de Ingestão via Airflow
    function triggerIngestion(date) {
        const overlay = document.getElementById('ingestOverlay');
        overlay.style.display = 'flex';

        const formData = new FormData();
        formData.append('date', date);

        fetch('/football-trends/ingest', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Sucesso! Ingestão agendada no Airflow. Os dados serão atualizados em instantes.');
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
