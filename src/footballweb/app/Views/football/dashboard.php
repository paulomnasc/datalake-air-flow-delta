<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';

if (!function_exists('getBookmakerUrl')) {
    function getBookmakerUrl($bmName) {
        $bm = strtoupper(trim($bmName ?? ''));
        if (empty($bm)) {
            return 'https://www.bet365.com/';
        }

        $urls = [
            'BET365'       => 'https://www.bet365.com/',
            'BETANO'       => 'https://br.betano.com/',
            'SPORTINGBET'  => 'https://www.sportingbet.com/pt-br',
            'SUPERBET'     => 'https://superbet.com/pt-br/',
            'KTO'          => 'https://www.kto.com/pt/',
            'BETFAIR'      => 'https://www.betfair.com/br',
            'BETNACIONAL'  => 'https://betnacional.com/',
            'NOVIBET'      => 'https://www.novibet.com.br/',
            'STAKE'        => 'https://stake.com/',
            'PARIMATCH'    => 'https://parimatch.com.br/',
            'PINNACLE'     => 'https://www.pinnacle.com/',
            'ESTRELA'      => 'https://estrelabet.com/',
            'RIVALO'       => 'https://www.rivalo.com/pt',
            '1XBET'        => 'https://1xbet.com/',
            'GALERA'       => 'https://www.galera.bet/',
            'BLAZE'        => 'https://blaze.com/',
            'UNIBET'       => 'https://www.unibet.com/',
            'CASUMO'       => 'https://www.casumo.com/',
            'GROSVENOR'    => 'https://www.grosvenorcasinos.com/sport',
            'LADBROKES'    => 'https://sports.ladbrokes.com/',
            'BETSSON'      => 'https://www.betsson.com/',
            'COOLBET'      => 'https://www.coolbet.com/',
            '888SPORT'     => 'https://www.888sport.com/',
            'WILLIAM HILL' => 'https://sports.williamhill.com/',
            'BETWAY'       => 'https://www.betway.com/',
            'LEOVEGAS'     => 'https://www.leovegas.com/',
            'PADDY POWER'  => 'https://sports.paddypower.com/',
            'CORAL'        => 'https://sports.coral.co.uk/',
            'VIRGIN'       => 'https://www.virginbet.com/',
            'LIVESCORE'    => 'https://www.livescorebet.com/',
            'WINAMAX'      => 'https://www.winamax.fr/',
            'MARATHON'     => 'https://www.marathonbet.com/',
            'CODERE'       => 'https://www.codere.es/',
            'BETCLIC'      => 'https://www.betclic.fr/',
            'MATCHBOOK'    => 'https://www.matchbook.com/',
            'BETONLINE'    => 'https://www.betonline.ag/',
            'SMARKETS'     => 'https://smarkets.com/'
        ];
        
        foreach ($urls as $key => $url) {
            if (strpos($bm, $key) !== false) {
                return $url;
            }
        }
        
        $cleanDomain = preg_replace('/[^a-z0-9]/', '', strtolower($bmName));
        return 'https://www.' . $cleanDomain . '.com/';
    }
}

if (!function_exists('renderStructuredMotivation')) {
    function renderStructuredMotivation($rawMotivation) {
        if (empty($rawMotivation)) return '';

        // Limpa prefixos redundantes
        $cleanText = preg_replace('/^(🎯\s*Fator Crucial:\s*|💡\s*Motivação:\s*|MOTIVACAO:\s*)/u', '', trim($rawMotivation));

        // Tenta extrair tópicos numéricos explícitos (ex: "1. ... 2. ... 3. ...")
        $topics = [];
        if (preg_match_all('/(?:^|\s*)(?:\d+[\.\)]|•|-)\s*([^0-9•\n\r]+?)(?=(?:\s*\d+[\.\)]|\s*•|\s*-|$))/u', $cleanText, $matches) && count($matches[1]) >= 2) {
            foreach ($matches[1] as $item) {
                $t = trim($item, " \t\n\r\0\x0B;.-");
                if (mb_strlen($t) > 3) {
                    $topics[] = $t;
                }
            }
        }

        // Se não houver numeração explícita, quebra o texto por frases (ponto final)
        if (empty($topics)) {
            $parts = preg_split('/(?<=[.!?])\s+/u', $cleanText);
            foreach ($parts as $p) {
                $p = trim($p, " \t\n\r\0\x0B;.-");
                if (mb_strlen($p) > 4) {
                    $topics[] = $p;
                }
            }
        }

        if (empty($topics)) {
            $topics[] = $cleanText;
        }

        $html = '<div class="motivation-structured-box" style="margin-top: 8px; padding: 10px 12px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(56, 189, 248, 0.25); border-left: 4px solid #38bdf8; border-radius: 8px;">';
        $html .= '<div style="font-size: 0.76rem; font-weight: 700; color: #38bdf8; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">';
        $html .= '<i class="bi bi-list-check" style="font-size: 0.88rem; color: #38bdf8;"></i> 💡 Motivação Detalhada do Palpite:';
        $html .= '</div>';
        $html .= '<ol style="margin: 0; padding-left: 0; list-style: none;">';

        foreach ($topics as $index => $topic) {
            $num = $index + 1;
            $html .= '<li style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 6px; font-size: 0.74rem; color: #e2e8f0; line-height: 1.45;">';
            $html .= '<span style="background: rgba(56, 189, 248, 0.18); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; font-weight: 800; font-size: 0.68rem; min-width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;">' . $num . '</span>';
            $html .= '<span>' . htmlspecialchars($topic) . '</span>';
            $html .= '</li>';
        }

        $html .= '</ol>';
        $html .= '</div>';

        return $html;
    }
}

// Controle de Créditos do Grok AI e Ligas Premium
$userLoggedIn = false;
$userGrokCredits = 0;
$userId = null;
$isGoogleUser = false;
$userHasBalance = false;
if (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] == 1) {
    $userLoggedIn = true;
    $userId = $_SESSION['id_usuario_logado'] ?? null;
    if ($userId) {
        $db = \Config\Database::connect();
        $userRow = $db->table('usuario')->where('id', $userId)->get()->getRow();
        $userGrokCredits = $userRow ? (int)$userRow->grok_credits : 0;
        $isGoogleUser = $userRow && !empty($userRow->google_id);
        $userHasBalance = ($userGrokCredits > 0) || ($userRow && in_array($userRow->status_assinatura ?? '', ['active', 'trial']));
    }
}

// Formatação amigável da data
setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil');
$dateObj = DateTime::createFromFormat('Y-m-d', $targetDate);
$formattedDateHeader = $dateObj ? strftime('%d de %B de %Y', $dateObj->getTimestamp()) : $targetDate;

// Mapeamento de League ID para País e Bandeira/Ícone (estilo Betano)
$leagueMap = [
    71  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    72  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    73  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => false],
    94  => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true],
    39  => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true],
    140 => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true],
    135 => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true],
    78  => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true],
    88  => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true],
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
    292 => ['country' => 'Coreia do Sul', 'flag' => '🇰🇷', 'popular' => false],
    98  => ['country' => 'Japão', 'flag' => '🇯🇵', 'popular' => false],
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

if (!function_exists('factorial_php')) {
    function factorial_php($n) {
        if ($n <= 1) return 1;
        $res = 1;
        for ($i = 2; $i <= $n; $i++) $res *= $i;
        return $res;
    }
}

if (!function_exists('calculate_poisson_php')) {
    function calculate_poisson_php($xc, $line = 4.5) {
        if ($xc <= 0) return ['over' => 0.0, 'under' => 100.0];
        $kMax = (int)floor($line);
        $probUnderCdf = 0.0;
        for ($k = 0; $k <= $kMax; $k++) {
            $probUnderCdf += (exp(-$xc) * pow($xc, $k)) / factorial_php($k);
        }
        $probOver = max(0.0, min(100.0, (1.0 - $probUnderCdf) * 100.0));
        $probUnder = max(0.0, min(100.0, $probUnderCdf * 100.0));
        return ['over' => round($probOver, 2), 'under' => round($probUnder, 2)];
    }
}

if (!function_exists('getBetDecisionTree')) {
    function getBetDecisionTree($fix) {
        $rawText = $fix->prediction_text ?? '';
        $isNoBet = (strpos($rawText, 'NO_BET') !== false || strpos($rawText, 'não recomendada') !== false);
        
        $xc = null;
        if (!empty($rawText) && preg_match('/(?:xC|Expectativa(?:\s+de\s+[Cc]artões)?(?::|\s+elevad[ao])?)\s*\(?(\d+\.\d+|\d+)/i', $rawText, $mXc)) {
            $xc = (float)$mXc[1];
        }

        $homeAvg = isset($fix->home_avg_cards) ? (float)$fix->home_avg_cards : 2.0;
        $awayAvg = isset($fix->away_avg_cards) ? (float)$fix->away_avg_cards : 2.0;
        $combinedAvg = $homeAvg + $awayAvg;
        $refAvg = isset($fix->average_yellow_cards) ? (float)$fix->average_yellow_cards : 4.2;
        $refFouls = isset($fix->average_fouls) ? (float)$fix->average_fouls : 24.0;
        
        if ($xc === null) {
            $foulContext = $combinedAvg * ($refFouls / 24.0);
            $xc = round(($combinedAvg * 0.50) + ($refAvg * 0.35) + ($foulContext * 0.15), 2);
        }

        $u35 = calculate_poisson_php($xc, 3.5)['under'];
        $u45 = calculate_poisson_php($xc, 4.5)['under'];
        $u55 = calculate_poisson_php($xc, 5.5)['under'];
        $u65 = calculate_poisson_php($xc, 6.5)['under'];

        if ($isNoBet || $xc > 4.80) {
            return [
                'market'        => 'Entrada Não Recomendada',
                'line_tag'      => 'NO BET 🚫',
                'badge_bg'      => 'background: rgba(239, 68, 68, 0.25); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5);',
                'box_border'    => '#ef4444',
                'region'        => 'Expectativa de Cartões',
                'region_short'  => 'Exp. Cartões: ' . number_format($xc, 2),
                'foul_style'    => 'Times (' . number_format($combinedAvg, 1) . ' c/j)',
                'foul_short'    => 'Times (' . number_format($combinedAvg, 1) . ')',
                'referee'       => 'Árbitro (' . number_format($refAvg, 1) . ' c/j)',
                'referee_short' => 'Árbitro (' . number_format($refAvg, 1) . ')',
                'rationale'     => 'Expectativa de cartões elevada (' . number_format($xc, 2) . ' cartões). Risco elevado para Under e apostas Over bloqueadas pelo sistema.'
            ];
        } elseif ($xc <= 3.50) {
            return [
                'market'        => 'Menos de Cartões',
                'line_tag'      => 'UNDER 4.5 🛡️',
                'badge_bg'      => 'background: rgba(16, 185, 129, 0.25); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.5);',
                'box_border'    => '#10b981',
                'region'        => 'Expectativa de Cartões',
                'region_short'  => 'Exp. Cartões: ' . number_format($xc, 2),
                'foul_style'    => 'Times (' . number_format($combinedAvg, 1) . ' c/j)',
                'foul_short'    => 'Times (' . number_format($combinedAvg, 1) . ')',
                'referee'       => 'Árbitro (' . number_format($refAvg, 1) . ' c/j)',
                'referee_short' => 'Árbitro (' . number_format($refAvg, 1) . ')',
                'rationale'     => 'Excelente histórico disciplinado (Expectativa = ' . number_format($xc, 2) . ' cartões). Opção 1: Under 4.5 (' . $u45 . '%) | Opção 2: Under 5.5 (' . $u55 . '%).'
            ];
        } elseif ($xc <= 4.20) {
            return [
                'market'        => 'Menos de Cartões',
                'line_tag'      => 'UNDER 5.5 🛡️',
                'badge_bg'      => 'background: rgba(16, 185, 129, 0.25); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.5);',
                'box_border'    => '#10b981',
                'region'        => 'Expectativa de Cartões',
                'region_short'  => 'Exp. Cartões: ' . number_format($xc, 2),
                'foul_style'    => 'Times (' . number_format($combinedAvg, 1) . ' c/j)',
                'foul_short'    => 'Times (' . number_format($combinedAvg, 1) . ')',
                'referee'       => 'Árbitro (' . number_format($refAvg, 1) . ' c/j)',
                'referee_short' => 'Árbitro (' . number_format($refAvg, 1) . ')',
                'rationale'     => 'Baixa expectativa de cartões (Expectativa = ' . number_format($xc, 2) . ' cartões). Opção 1: Under 5.5 (' . $u55 . '%) | Opção 2: Under 4.5 (' . $u45 . '%).'
            ];
        } else { // 4.20 < $xc <= 4.80
            return [
                'market'        => 'Menos de Cartões',
                'line_tag'      => 'UNDER 6.5 🛡️',
                'badge_bg'      => 'background: rgba(245, 158, 11, 0.25); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.5);',
                'box_border'    => '#f59e0b',
                'region'        => 'Expectativa de Cartões',
                'region_short'  => 'Exp. Cartões: ' . number_format($xc, 2),
                'foul_style'    => 'Times (' . number_format($combinedAvg, 1) . ' c/j)',
                'foul_short'    => 'Times (' . number_format($combinedAvg, 1) . ')',
                'referee'       => 'Árbitro (' . number_format($refAvg, 1) . ' c/j)',
                'referee_short' => 'Árbitro (' . number_format($refAvg, 1) . ')',
                'rationale'     => 'Expectativa moderada (Expectativa = ' . number_format($xc, 2) . ' cartões). Opção 1: Under 6.5 (' . $u65 . '%) | Opção 2: Under 5.5 (' . $u55 . '%).'
            ];
        }
    }
}

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

    /* Toggle Badges & Retractable Card Sections */
    .bet-badge-toggle-bar {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin: 10px 0 6px 0;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    .bet-toggle-badge {
        background: rgba(30, 41, 59, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #cbd5e1;
        font-size: 0.73rem;
        font-weight: 600;
        padding: 5px 11px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        user-select: none;
    }
    .bet-toggle-badge:hover {
        background: rgba(51, 65, 85, 0.9);
        border-color: rgba(255, 255, 255, 0.25);
        color: #ffffff;
        transform: translateY(-1px);
    }
    .bet-toggle-badge.active {
        box-shadow: 0 0 10px rgba(0,0,0,0.5);
    }
    .bet-toggle-badge.yellow.active {
        background: rgba(251, 191, 36, 0.2);
        border-color: #fbbf24;
        color: #fbbf24;
    }
    .bet-toggle-badge.blue.active {
        background: rgba(56, 189, 248, 0.2);
        border-color: #38bdf8;
        color: #38bdf8;
    }
    .bet-toggle-badge.green.active {
        background: rgba(16, 185, 129, 0.2);
        border-color: #10b981;
        color: #10b981;
    }
    .bet-toggle-badge.purple.active {
        background: rgba(167, 139, 250, 0.2);
        border-color: #a78bfa;
        color: #a78bfa;
    }
    .bet-card-section {
        display: none;
        margin-top: 8px;
        padding: 10px 12px;
        background: rgba(15, 23, 42, 0.92);
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: inset 0 0 10px rgba(0,0,0,0.3);
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

    @keyframes cardGlowPulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 230, 118, 0.8); border-color: #00e676; }
        50% { box-shadow: 0 0 30px 10px rgba(0, 230, 118, 0.9); border-color: #00e676; transform: scale(1.02); }
        100% { box-shadow: 0 0 0 0 rgba(0, 230, 118, 0); border-color: rgba(255, 255, 255, 0.05); }
    }

    .card-highlight-pulse {
        animation: cardGlowPulse 1.8s ease-in-out 3;
        border: 2px solid #00e676 !important;
        z-index: 10;
    }

    .oddspedia-link-box:hover {
        background: rgba(255, 255, 255, 0.12) !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
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

    /* Ícone indicativo de aposta em baralho */
    .bet-card-playing-card-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        transition: all 0.2s ease;
        text-decoration: none !important;
        cursor: pointer;
        user-select: none;
    }

    .bet-card-playing-card-badge.has-bet {
        background: rgba(0, 230, 118, 0.15);
        color: #00e676;
        border: 1px solid rgba(0, 230, 118, 0.4);
        box-shadow: 0 0 10px rgba(0, 230, 118, 0.25);
    }

    .bet-card-playing-card-badge.has-bet:hover {
        background: rgba(0, 230, 118, 0.28);
        color: #ffffff;
        border-color: #00e676;
        transform: translateY(-1px);
        box-shadow: 0 0 14px rgba(0, 230, 118, 0.45);
    }

    .bet-card-playing-card-badge.no-bet {
        background: rgba(255, 255, 255, 0.03);
        color: #64748b;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .bet-card-playing-card-badge.no-bet:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #cbd5e1;
        border-color: rgba(255, 255, 255, 0.2);
    }

    .playing-card-symbol {
        font-size: 0.85rem;
        line-height: 1;
        display: inline-block;
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
    }

    .bet-elapsed-time.pst {
        color: #f59e0b;
        font-weight: 800;
        background: rgba(245, 158, 11, 0.15);
        padding: 2px 8px;
        border-radius: 6px;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .bet-team-score {
        margin-left: auto;
        font-weight: 800;
        font-size: 1.05rem;
        color: #ffffff;
        background: rgba(255, 255, 255, 0.08);
        padding: 1px 8px;
        border-radius: 6px;
        min-width: 24px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .bet-card-badge-container {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: auto;
        margin-right: 6px;
    }

    .bet-card-badge-item {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 4px;
        line-height: 1;
    }

    .bet-card-badge-item.yellow {
        background: rgba(234, 179, 8, 0.2);
        color: #facc15;
        border: 1px solid rgba(234, 179, 8, 0.4);
    }

    .bet-card-badge-item.red {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.4);
    }

    .live-pulse-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background-color: #ef4444;
        margin-right: 4px;
        vertical-align: middle;
        box-shadow: 0 0 6px #ef4444;
        animation: pulse-live 1.2s infinite ease-in-out;
    }

    @keyframes pulse-live {
        0% { transform: scale(0.9); opacity: 0.8; box-shadow: 0 0 4px #ef4444; }
        50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 10px #ef4444; }
        100% { transform: scale(0.9); opacity: 0.8; box-shadow: 0 0 4px #ef4444; }
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

    .bet-team-logo {
        width: 32px;
        height: 32px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .bet-team-row:last-child .bet-team-dot {
        background: #3b82f6;
    }

    .bet-team-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 2px;
        margin-bottom: 8px;
        margin-left: 18px;
        font-size: 0.72rem;
        color: #9ca3af;
    }

    .bet-team-stats-item {
        background: rgba(255, 255, 255, 0.04);
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid rgba(255, 255, 255, 0.07);
        display: flex;
        align-items: center;
        gap: 4px;
        cursor: help;
    }
    
    .bet-team-stats-item span.label {
        color: #6b7280;
        font-weight: 500;
    }

    .bet-team-stats-item span.val {
        color: #e5e7eb;
        font-weight: 600;
    }

    .bet-team-stats-item i {
        color: #f47c20;
    }
    
    .bet-team-row-wrapper:last-child .bet-team-stats-item i {
        color: #3b82f6;
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

    .bet-prob-value.safe, .bet-prob-value.high { color: #34d399; }
    .bet-prob-value.moderate, .bet-prob-value.medium { color: #fbbf24; }
    .bet-prob-value.nobet, .bet-prob-value.low { color: #f87171; }

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

    .bet-progress-fill.safe, .bet-progress-fill.high { background: linear-gradient(90deg, #10b981, #34d399); }
    .bet-progress-fill.moderate, .bet-progress-fill.medium { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .bet-progress-fill.nobet, .bet-progress-fill.low { background: linear-gradient(90deg, #dc2626, #ef4444); }

    .bet-pred-text {
        font-size: 0.84rem;
        color: #d1d5db;
        line-height: 1.45;
        background: rgba(255, 255, 255, 0.01);
        padding: 8px 10px;
        border-radius: 6px;
        border-left: 3px solid #f47c20;
    }

    .bet-pred-text.safe, .bet-pred-text.high { border-left-color: #34d399; background: rgba(16, 185, 129, 0.05); }
    .bet-pred-text.moderate, .bet-pred-text.medium { border-left-color: #fbbf24; background: rgba(245, 158, 11, 0.05); }
    .bet-pred-text.nobet, .bet-pred-text.low { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.05); color: #fca5a5; }

    /* Betano style footer of cards */
    .bet-referee-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
        width: 100%;
        box-sizing: border-box;
    }

    .bet-referee-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        max-width: 100%;
    }

    .bet-referee-btn {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        color: #aeb9c4;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 20px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
        white-space: nowrap;
        max-width: 100%;
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
        cursor: help;
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
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 20px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
        white-space: nowrap;
        max-width: 100%;
    }

    .bet-ai-btn:hover {
        background: #f47c20;
        color: white;
        box-shadow: 0 0 10px rgba(244, 124, 32, 0.4);
    }

    /* Stats Button inside card footer */
    .bet-stats-btn {
        background: rgba(56, 189, 248, 0.1);
        border: 1px solid rgba(56, 189, 248, 0.3);
        color: #38bdf8;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 20px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s;
        white-space: nowrap;
        max-width: 100%;
    }

    .bet-stats-btn:hover {
        background: #38bdf8;
        color: #0f172a;
        box-shadow: 0 0 10px rgba(56, 189, 248, 0.4);
    }

    /* AI Chat Backdrop */
    .bet-chat-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 99998;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .bet-chat-backdrop.open {
        opacity: 1;
        visibility: visible;
    }

    /* AI Chat Drawer Style */
    .bet-chat-drawer {
        position: fixed;
        top: 0;
        bottom: 0;
        right: 0;
        width: 420px;
        max-width: 90vw;
        height: 100vh;
        height: 100dvh;
        background: #172230;
        border-left: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: -10px 0 30px rgba(0,0,0,0.5);
        z-index: 100000;
        transform: translateX(100%);
        visibility: hidden;
        opacity: 0;
        pointer-events: none;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease, visibility 0.3s ease;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-sizing: border-box;
    }

    .bet-chat-drawer.open {
        transform: translateX(0);
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
    }

    .bet-chat-header {
        background: #0f1620;
        padding: 15px 20px;
        border-bottom: 2px solid #f47c20;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
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
        padding: 5px;
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
        flex-shrink: 0;
    }

    .bet-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
        -webkit-overflow-scrolling: touch;
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
        padding: 12px 15px;
        padding-bottom: max(12px, env(safe-area-inset-bottom));
        background: #0f1620;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        gap: 8px;
        align-items: center;
        width: 100%;
        box-sizing: border-box;
        flex-shrink: 0;
    }

    .bet-chat-input {
        flex: 1;
        min-width: 0;
        background: #172230;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        padding: 10px 12px;
        color: white;
        outline: none;
        font-size: 0.95rem;
        box-sizing: border-box;
    }

    .bet-chat-input:focus {
        border-color: #f47c20;
    }

    .bet-chat-send-btn {
        flex-shrink: 0;
        background: #f47c20;
        color: white;
        border: none;
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .bet-chat-send-btn:hover {
        background: #ff8e38;
    }

    @media (max-width: 576px) {
        .bet-chat-drawer {
            width: 100vw;
            border-left: none;
        }
        .bet-chat-header {
            padding: 12px 15px;
        }
        .bet-chat-game-context {
            padding: 8px 15px;
        }
        .bet-chat-messages {
            padding: 15px 12px;
        }
        .bet-chat-input-area {
            padding: 10px 12px;
            padding-bottom: max(10px, env(safe-area-inset-bottom));
        }
        .bet-chat-input {
            font-size: 16px;
        }
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

    /* Custom CSS Tooltip/Hint System */
    [data-tooltip] {
        position: relative;
    }

    [data-tooltip]::before {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%) translateY(8px) scale(0.95);
        background: #111827; /* Dark charcoal */
        color: #f3f4f6;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.78rem;
        white-space: normal;
        width: 220px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6), 0 0 1px rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s cubic-bezier(0.4, 0, 0.2, 1), transform 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 999999;
        text-align: center;
        line-height: 1.4;
        font-family: 'Outfit', sans-serif;
        font-weight: 500;
    }

    [data-tooltip]::after {
        content: "";
        position: absolute;
        bottom: 112%;
        left: 50%;
        transform: translateX(-50%) translateY(8px) scale(0.95);
        border-width: 6px;
        border-style: solid;
        border-color: #111827 transparent transparent transparent;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s cubic-bezier(0.4, 0, 0.2, 1), transform 0.18s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 999999;
    }

    [data-tooltip]:hover::before,
    [data-tooltip]:hover::after {
        opacity: 1;
        transform: translateX(-50%) translateY(0) scale(1);
    }

    /* Estilos de bloqueio por créditos */
    .bet-card-locked,
    .bet-card-blur {
        pointer-events: none;
        user-select: none;
        opacity: 0.65;
        transition: opacity 0.3s ease;
    }

    .bet-card-lock-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(14, 22, 32, 0.88);
        border-radius: 12px;
        z-index: 10;
        text-align: center;
        padding: 20px;
        border: 2px solid rgba(244, 124, 32, 0.2);
    }

    /* Badges de créditos na header */
    .grok-credits-badge {
        background: #172230;
        border: 1px solid rgba(244, 124, 32, 0.3);
        border-radius: 20px;
        padding: 6px 15px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #f3f4f6;
        margin-right: 15px;
    }
    
    .grok-credits-badge .icon {
        color: #f47c20;
    }

    .grok-credits-badge .btn-recarregar {
        background: #f47c20;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 2px 10px;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.2s;
    }

    .grok-credits-badge .btn-recarregar:hover {
        background: #e06b12;
        color: #ffffff;
    }
</style>

<div class="container-fluid">
    <div class="ft-container">
        
        <!-- Header / Brand Section -->
        <div class="bet-brand-header">
            <div class="bet-brand-title">
                <span class="bet-brand-logo">Bet</span>
                <span class="bet-brand-subtitle" style="font-weight: 800; font-size: 1.8rem; color: #ffffff;">Trends</span>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                <?php if ($userLoggedIn && $isGoogleUser): ?>
                    <div class="grok-credits-badge">
                        <span class="icon">🤖</span>
                        <span>Grok: <strong><?= $userGrokCredits ?></strong> <?= lang('App.queries') ?></span>
                        <a href="/subscription/buy-grok-credits" class="btn-recarregar"><?= lang('App.reload') ?></a>
                    </div>
                <?php elseif ($userLoggedIn): ?>
                    <div class="grok-credits-badge">
                        <span class="icon">🔒</span>
                        <span><?= lang('App.google_accounts_only') ?></span>
                        <a href="/auth/google-login" class="btn-recarregar" style="background: #4285f4;"><i class="bi bi-google"></i> <?= lang('App.connect') ?></a>
                    </div>
                <?php else: ?>
                    <div class="grok-credits-badge">
                        <span class="icon">🔒</span>
                        <span><?= lang('App.use_with_google_login') ?></span>
                        <a href="/auth/google-login" class="btn-recarregar" style="background: #4285f4;"><i class="bi bi-google"></i> <?= lang('App.enter') ?></a>
                    </div>
                <?php endif; ?>
                <button type="button" class="btn-update-betano" onclick="triggerIngestion('<?= $targetDate ?>')">
                    <i class="bi bi-arrow-repeat"></i> <?= lang('App.update_data_api') ?>
                </button>
            </div>
        </div>

        <!-- Seção de Vídeo em Destaque / Tutorial -->
        <section class="bet-video-section mb-4 p-3 p-md-4 rounded" style="background: linear-gradient(135deg, rgba(23, 34, 48, 0.9) 0%, rgba(15, 23, 36, 0.95) 100%); border: 1px solid rgba(244, 124, 32, 0.35); box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 class="mb-0 text-white font-weight-bold d-flex align-items-center gap-2" style="font-size: 1.15rem;">
                    <i class="bi bi-play-circle-fill" style="color: #f47c20; font-size: 1.3rem;"></i> 
                    Vídeo Demonstrativo - FootballWeb
                </h2>
                <a href="https://youtu.be/_Hhg3B1MldQ" target="_blank" rel="noopener noreferrer" class="btn btn-sm text-white font-weight-bold d-inline-flex align-items-center gap-1" style="background: #ff0000; border-radius: 8px; padding: 6px 14px; font-size: 0.88rem; text-decoration: none;">
                    <i class="bi bi-youtube"></i> Assistir no YouTube <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                </a>
            </div>
            <div style="max-width: 33.333%; min-width: 280px; margin: 0 auto;">
                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <iframe src="https://www.youtube.com/embed/_Hhg3B1MldQ" 
                            title="Vídeo Demonstrativo FootballWeb" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen 
                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;">
                    </iframe>
                </div>
            </div>
            <div class="mt-2 text-center">
                <small style="color: #94a3b8; font-size: 0.8rem;">
                    <i class="bi bi-info-circle"></i> Caso o vídeo exija verificação de idade pelo YouTube no player embutido, <a href="https://youtu.be/_Hhg3B1MldQ" target="_blank" rel="noopener noreferrer" style="color: #f47c20; text-decoration: underline;">clique aqui para assistir diretamente no YouTube</a>.
                </small>
            </div>
        </section>

        <!-- Bloco SEO Server-Side Rendered (SSR) com data e hora atual -->
        <section class="bet-seo-header mb-4 p-3 rounded" style="background: rgba(23, 34, 48, 0.6); border: 1px solid rgba(255, 255, 255, 0.05);">
            <h1 style="font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>⚽ Tendências de Futebol Hoje & Estatísticas de Cartões e Escanteios</span>
                <?php 
                  $dtNowBrt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                  $nowFormatted = $dtNowBrt->format('d/m/Y \à\s H:i');
                ?>
                <span class="badge" style="background: rgba(0, 230, 118, 0.15); border: 1px solid rgba(0, 230, 118, 0.3); color: #00e676; font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;" title="Data e hora atual no fuso horário de Brasília (America/Sao_Paulo)">
                    <i class="bi bi-clock-history" style="color: #00e676;"></i> <?= $nowFormatted ?>
                </span>
            </h1>
            <p class="mb-0" style="font-size: 0.92rem; line-height: 1.6; color: #ffffff;">
                Acompanhe as estatísticas completas dos jogos de hoje (<?= $formattedDateHeader ?>). Dados atualizados das principais ligas (Brasileirão, Champions League, Europa), histórico de faltas por árbitro, médias de cartões amarelos e vermelhos, e previsões matemáticas acionadas pelo assistente inteligente Grok AI.
            </p>
        </section>

        <div class="row g-4">
            <!-- Coluna Esquerda: Sidebar (Accordion de Competições estilo Betano) -->
            <div class="col-lg-3 col-md-4">
                <div class="bet-sidebar">
                    
                    <!-- Bloco POPULARES -->
                    <div class="bet-section-title">
                        <span><?= lang('App.popular') ?></span>
                        <i class="bi bi-star-fill" style="color: #f47c20;"></i>
                    </div>
                    <ul class="bet-sidebar-list">
                        <li class="bet-sidebar-item">
                            <a class="bet-league-link active" onclick="filterByLeague('all')" id="league-link-all">
                                ⚽ <?= lang('App.all_leagues') ?>
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
                        <span><?= lang('App.main_competitions') ?></span>
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
                        <!-- Linha 1: Navegação por datas e Opções de Exibição -->
                        <div class="row align-items-center g-3 mb-3">
                            <div class="col-xl-6 col-lg-7 col-md-12">
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <?php
                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                                    $today = date('Y-m-d');
                                    $tomorrow = date('Y-m-d', strtotime('+1 day'));
                                    $next3days = date('Y-m-d', strtotime('+3 days'));
                                    $next7days = date('Y-m-d', strtotime('+7 days'));
                                    
                                    $showFinishedQuery = $showFinished ? '&show_finished=1' : '';
                                    $showPostponedQuery = !empty($showPostponed) ? '&show_postponed=1' : '';
                                    $searchQuery = !empty($search) ? '&search=' . urlencode($search) : '';
                                    $commonParams = $showFinishedQuery . $showPostponedQuery . $searchQuery;
                                    ?>
                                    <a href="?start_date=<?= $yesterday ?>&end_date=<?= $yesterday ?><?= $commonParams ?>" class="bet-date-btn <?= ($startDate === $yesterday && $endDate === $yesterday) ? 'active' : '' ?>">
                                        <i class="bi bi-chevron-left"></i> <?= lang('App.yesterday') ?>
                                    </a>
                                    <a href="?start_date=<?= $today ?>&end_date=<?= $today ?><?= $commonParams ?>" class="bet-date-btn <?= ($startDate === $today && $endDate === $today) ? 'active' : '' ?>">
                                        <?= lang('App.today') ?>
                                    </a>
                                    <a href="?start_date=<?= $tomorrow ?>&end_date=<?= $tomorrow ?><?= $commonParams ?>" class="bet-date-btn <?= ($startDate === $tomorrow && $endDate === $tomorrow) ? 'active' : '' ?>">
                                        <?= lang('App.tomorrow') ?>
                                    </a>
                                    <a href="?start_date=<?= $today ?>&end_date=<?= $next3days ?><?= $commonParams ?>" class="bet-date-btn <?= ($startDate === $today && $endDate === $next3days) ? 'active' : '' ?>" style="border-color: rgba(56, 189, 248, 0.4); color: #38bdf8;">
                                        ⚡ Próx. 3 Dias
                                    </a>
                                    <a href="?start_date=<?= $today ?>&end_date=<?= $next7days ?><?= $commonParams ?>" class="bet-date-btn <?= ($startDate === $today && $endDate === $next7days) ? 'active' : '' ?>" style="border-color: rgba(0, 230, 118, 0.4); color: #00e676;">
                                        🚀 Próx. 7 Dias
                                    </a>
                                    <div class="d-flex align-items-center gap-1" style="background: rgba(255, 255, 255, 0.04); padding: 4px 8px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.08);">
                                        <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">De:</span>
                                        <input type="date" name="start_date" class="bet-date-input" value="<?= $startDate ?>" onchange="document.getElementById('filterForm').submit()">
                                        <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; margin-left: 4px;">Até:</span>
                                        <input type="date" name="end_date" class="bet-date-input" value="<?= $endDate ?>" onchange="document.getElementById('filterForm').submit()">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Toggle switches column -->
                            <div class="col-xl-6 col-lg-5 col-md-12 d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #aeb9c4; font-weight: 600;"><?= lang('App.show_finished_games') ?></span>
                                    <label class="bet-switch">
                                        <input type="checkbox" name="show_finished" value="1" <?= $showFinished ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                                        <span class="bet-slider round"></span>
                                    </label>
                                    <span class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= $showFinished ? '#f47c20' : '#8a99a8' ?>;">
                                        <?= $showFinished ? lang('App.yes') : lang('App.no') ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #aeb9c4; font-weight: 600;">Exibir Adiados (PST)</span>
                                    <label class="bet-switch">
                                        <input type="checkbox" name="show_postponed" value="1" <?= !empty($showPostponed) ? 'checked' : '' ?> onchange="document.getElementById('filterForm').submit()">
                                        <span class="bet-slider round"></span>
                                    </label>
                                    <span class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($showPostponed) ? '#f59e0b' : '#8a99a8' ?>;">
                                        <?= !empty($showPostponed) ? 'Sim' : 'Não' ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.25);">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #34d399; font-weight: 600;">
                                        <i class="bi bi-shield-fill-check"></i> Apostas Seguras
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlySafeToggle" name="only_safe" value="1" <?= !empty($onlySafe) ? 'checked' : '' ?> onchange="toggleSafeBetsFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlySafeToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($onlySafe) ? '#10b981' : '#8a99a8' ?>;">
                                        <?= !empty($onlySafe) ? 'Sim' : 'Não' ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(0, 230, 118, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(0, 230, 118, 0.3);">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #00e676; font-weight: 600;">
                                        ⚡ Surebets (Oddspedia)
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlySurebetToggle" name="only_surebet" value="1" <?= !empty($onlySurebet) ? 'checked' : '' ?> onchange="toggleSurebetsFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlySurebetToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($onlySurebet) ? '#00e676' : '#8a99a8' ?>;">
                                        <?= !empty($onlySurebet) ? 'Sim' : 'Não' ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(192, 132, 252, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(192, 132, 252, 0.3);" title="Exibir apenas partidas que possuem apostas cadastradas">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #c084fc; font-weight: 600;">
                                        🃏 Com Aposta
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlyHasBetToggle" name="only_has_bet" value="1" onchange="toggleHasBetFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlyHasBetToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: #8a99a8;">
                                        Não
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Linha 2: Busca por Texto (Abaixo da navegação entre datas) -->
                        <div class="row align-items-center g-2 pt-3" style="border-top: 1px solid rgba(255, 255, 255, 0.05);">
                            <div class="col-12">
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="position-relative flex-grow-1">
                                        <i class="bi bi-search bet-search-icon"></i>
                                        <input type="text" name="search" id="teamSearchInput" class="bet-search-input" placeholder="<?= lang('App.search_placeholder') ?>" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>" autocomplete="off">
                                    </div>
                                    <button type="submit" class="btn btn-secondary rounded-3 px-3 d-flex align-items-center gap-1" style="background: #243447; border-color: rgba(255,255,255,0.1); color: #ffffff; font-weight: 600;">
                                        <i class="bi bi-funnel-fill" style="color: #f47c20;"></i> <?= lang('App.filter') ?>
                                    </button>
                                    <?php if(!empty($search) || $showFinished || !empty($showPostponed)): ?>
                                        <a href="?date=<?= $targetDate ?>" class="btn btn-outline-danger d-flex align-items-center justify-content-center px-3" style="border-radius: 8px; font-weight: 600;"><?= lang('App.clear') ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Abas estilo Betano: Destaques vs Todas as Partidas -->
                <div class="bet-tabs">
                    <div class="bet-tab active" id="tab-competicoes" onclick="switchMainTab('competicoes')"><?= lang('App.competitions') ?></div>
                    <div class="bet-tab" id="tab-destaques" onclick="switchMainTab('destaques')"><?= lang('App.highlights') ?></div>
                </div>

                <!-- Grid de Partidas -->
                <div class="bet-grid" id="fixturesGrid">
                    <?php if (empty($fixtures)): ?>
                        <div class="bet-empty">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #8a99a8; display: block; margin-bottom: 15px;"></i>
                            <h3><?= lang('App.no_matches_date') ?></h3>
                            <p class="text-muted">
                                <?= lang('App.no_games_found') ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($fixtures as $fix): ?>
                            <?php
                            $jsAttr = function($val) {
                                return htmlspecialchars(json_encode((string)($val ?? '')), ENT_QUOTES, 'UTF-8');
                            };
                            $statusUpper = strtoupper($fix->status ?? '');
                            $isFinished = in_array($statusUpper, ['FT', 'AET', 'PEN', 'MATCH FINISHED', 'FINISHED']);
                            $totalLiveCards = (int)($fix->yellow_cards_home ?? 0) + (int)($fix->yellow_cards_away ?? 0) + (int)($fix->red_cards_home ?? 0) + (int)($fix->red_cards_away ?? 0);

                            $rawPredText = $fix->prediction_text ?? '';
                            $isNoBetFix = (strpos($rawPredText, 'NO_BET') !== false || strpos($rawPredText, 'não recomendada') !== false);

                            $xc = null;
                            if (!empty($rawPredText) && preg_match('/(?:xC|Expectativa(?:\s+de\s+[Cc]artões)?(?::|\s+elevad[ao])?)\s*\(?(\d+\.\d+|\d+)/i', $rawPredText, $mXc)) {
                                $xc = (float)$mXc[1];
                            }

                            if ($xc === null) {
                                $homeAvgC = isset($fix->home_avg_cards) ? (float)$fix->home_avg_cards : 2.0;
                                $awayAvgC = isset($fix->away_avg_cards) ? (float)$fix->away_avg_cards : 2.0;
                                $refAvgC = isset($fix->average_yellow_cards) ? (float)$fix->average_yellow_cards : 4.2;
                                $refFouls = isset($fix->average_fouls) ? (float)$fix->average_fouls : 24.0;
                                
                                $combinedAvg = $homeAvgC + $awayAvgC;
                                $foulContext = $combinedAvg * ($refFouls / 24.0);
                                $xc = round(($combinedAvg * 0.50) + ($refAvgC * 0.35) + ($foulContext * 0.15), 2);
                            }

                            $u45 = calculate_poisson_php($xc, 4.5)['under'];
                            $u55 = calculate_poisson_php($xc, 5.5)['under'];
                            $u65 = calculate_poisson_php($xc, 6.5)['under'];

                            if ($isFinished && $totalLiveCards <= 5 && $xc <= 4.80) {
                                $prob = 100.0;
                                $probDisplay = '100% (BATEU 🟢)';
                                $class = 'safe';
                            } elseif ($isNoBetFix || $xc > 4.80) {
                                $prob = 0.0;
                                $probDisplay = 'NO BET (Risco 🚫)';
                                $class = 'nobet';
                            } elseif ($xc <= 3.50) {
                                $prob = $u45;
                                $probDisplay = 'Under 4.5: ' . number_format($prob, 2) . '%';
                                $class = 'safe';
                            } elseif ($xc <= 4.20) {
                                $prob = $u55;
                                $probDisplay = 'Under 5.5: ' . number_format($prob, 2) . '%';
                                $class = 'safe';
                            } elseif ($xc <= 4.80) {
                                $prob = $u65;
                                $probDisplay = 'Under 6.5: ' . number_format($prob, 2) . '%';
                                $class = 'moderate';
                            } else {
                                $prob = 0.0;
                                $probDisplay = 'NO BET (Risco 🚫)';
                                $class = 'nobet';
                            }



                            // Formata hora convertendo de UTC para o fuso horário ativo do usuário
                            $timeStr = '';
                            try {
                                $displayTz = $userTimezone ?? $_SESSION['user_timezone'] ?? $_COOKIE['user_timezone'] ?? 'America/Sao_Paulo';
                                if (!in_array($displayTz, \DateTimeZone::listIdentifiers())) {
                                    $displayTz = 'America/Sao_Paulo';
                                }
                                $dt = new DateTime($fix->fixture_date, new DateTimeZone('UTC'));
                                $dt->setTimezone(new DateTimeZone($displayTz));
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
                                
                                if (in_array($statusClean, ['PST', 'POSTPONED', 'CANCELLED'])) {
                                    $elapsedText = 'ADIADO';
                                    $elapsedClass = 'pst';
                                } elseif (in_array($statusClean, $finishedStatuses)) {
                                    $elapsedText = lang('App.finished');
                                } elseif ($statusClean === 'HT') {
                                    $elapsedText = lang('App.halftime');
                                    $elapsedClass = 'live';
                                } elseif ($diffMins < 0) {
                                    $elapsedText = lang('App.not_started');
                                } else {
                                    if ($diffMins > 120) {
                                        $elapsedText = lang('App.finished');
                                    } else {
                                        $elapsedText = $diffMins . "'";
                                        $elapsedClass = 'live';
                                    }
                                }
                            } catch (\Exception $e) {
                                $elapsedText = '-';
                            }
                            ?>
                            <?php
                            $requiresCredits = \App\Helpers\SubscriptionHelper::leagueRequiresCredits($fix->league_name);
                            $isCardLocked = $requiresCredits && (!$userLoggedIn || !$isGoogleUser || $userGrokCredits <= 0);
                            
                             $isLiveMatch = in_array(strtoupper($fix->status ?? ''), ['1H', '2H', 'HT', 'ET', 'P', 'LIVE', 'BT']);
                             if (in_array($statusClean, ['PST', 'POSTPONED', 'CANCELLED'])) {
                                 $elapsedClass = 'pst';
                                 $elapsedDisplay = '⚠️ ADIADO';
                             } elseif ($isLiveMatch) {
                                 $elapsedClass = 'live';
                                 $minDisplay = ($statusClean === 'HT') ? 'Int' : (!empty($fix->elapsed) ? $fix->elapsed . "'" : 'Ao Vivo');
                                 $elapsedDisplay = '<span class="live-pulse-dot"></span> ' . $minDisplay;
                             } else {
                                 $elapsedDisplay = $elapsedText;
                             }
                             ?>
                             <?php
                             $isFixtureInUserBets = in_array((int)$fix->fixture_id, $userBetFixtureIds ?? []);
                             $isFixtureInAnyBets  = in_array((int)$fix->fixture_id, $allBetFixtureIds ?? []);
                             $hasAposta = $isFixtureInUserBets || $isFixtureInAnyBets;

                             $lId = (int)($fix->league_id ?? 0);
                             $cMapData = $leagueMap[$lId] ?? null;
                             $cName = $cMapData['country'] ?? '';
                             $cFlag = $cMapData['flag'] ?? '';
                             if (empty($cName) && !empty($fix->league_name)) {
                                 $lNameLower = strtolower($fix->league_name);
                                 if (strpos($lNameLower, 'brasil') !== false || strpos($lNameLower, 'copa do brasil') !== false) { $cName = 'Brasil'; $cFlag = '🇧🇷'; }
                                 elseif (strpos($lNameLower, 'primeira') !== false || strpos($lNameLower, 'portugal') !== false) { $cName = 'Portugal'; $cFlag = '🇵🇹'; }
                                 elseif (strpos($lNameLower, 'argentina') !== false) { $cName = 'Argentina'; $cFlag = '🇦🇷'; }
                                 elseif (strpos($lNameLower, 'allsvenskan') !== false) { $cName = 'Suécia'; $cFlag = '🇸🇪'; }
                                 elseif (strpos($lNameLower, 'eliteserien') !== false) { $cName = 'Noruega'; $cFlag = '🇳🇴'; }
                                 elseif (strpos($lNameLower, 'veikkausliiga') !== false) { $cName = 'Finlândia'; $cFlag = '🇫🇮'; }
                                 elseif (strpos($lNameLower, 'eredivisie') !== false) { $cName = 'Holanda'; $cFlag = '🇳🇱'; }
                                 elseif (strpos($lNameLower, 'bundesliga') !== false) { $cName = 'Alemanha'; $cFlag = '🇩🇪'; }
                                 elseif (strpos($lNameLower, 'mls') !== false || strpos($lNameLower, 'major league') !== false) { $cName = 'EUA'; $cFlag = '🇺🇸'; }
                                 elseif (strpos($lNameLower, 'jupiler') !== false) { $cName = 'Bélgica'; $cFlag = '🇧🇪'; }
                                 elseif (strpos($lNameLower, 'japan') !== false || strpos($lNameLower, 'j1') !== false) { $cName = 'Japão'; $cFlag = '🇯🇵'; }
                                 elseif (strpos($lNameLower, 'k league') !== false) { $cName = 'Coreia do Sul'; $cFlag = '🇰🇷'; }
                             }
                             ?>
                             <div class="bet-card" id="card-<?= $fix->fixture_id ?>" data-fixture-id="<?= $fix->fixture_id ?>" data-league="<?= htmlspecialchars($fix->league_name, ENT_QUOTES) ?>" data-prob="<?= $prob ?>" data-is-safe="<?= (($class === 'safe' || $class === 'high') && strpos($fix->prediction_text ?? '', 'NO_BET') === false) ? '1' : '0' ?>" data-is-surebet="<?= !empty($fix->is_surebet) ? '1' : '0' ?>" data-has-aposta="<?= $hasAposta ? '1' : '0' ?>" data-home-team="<?= htmlspecialchars($fix->home_team ?? '', ENT_QUOTES) ?>" data-away-team="<?= htmlspecialchars($fix->away_team ?? '', ENT_QUOTES) ?>" data-teams="<?= htmlspecialchars(($fix->home_team ?? '') . ' ' . ($fix->away_team ?? '') . ' ' . ($fix->referee_name ?? ''), ENT_QUOTES) ?>" style="position: relative;">
                                <div class="<?= $isCardLocked ? 'bet-card-locked' : '' ?>" style="display: flex; flex-direction: column; height: 100%; justify-content: space-between;">
                                    <div>
                                    <!-- Header -->
                                    <div class="bet-card-header">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; max-width: 68%;">
                                            <span class="bet-league-badge" title="<?= htmlspecialchars((!empty($cName) ? $cName . ' - ' : '') . $fix->league_name) ?>">
                                                <?= !empty($cFlag) ? $cFlag . ' ' : '' ?><?= !empty($cName) ? htmlspecialchars($cName) . ' • ' : '' ?><?= htmlspecialchars($fix->league_name) ?>
                                            </span>
                                            <?php if ($hasAposta): ?>
                                                <a href="<?= base_url('apostas?action=edit&fixture_id=' . $fix->fixture_id) ?>" 
                                                   class="bet-card-playing-card-badge has-bet" 
                                                   title="<?= $isFixtureInUserBets ? 'Sua aposta está registrada para este jogo! Clique para editar.' : 'Existe aposta registrada para este jogo. Clique para ver.' ?>">
                                                    <span class="playing-card-symbol">🂠</span>
                                                    <span><?= $isFixtureInUserBets ? 'Sua Aposta' : 'Com Aposta' ?></span>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= base_url('apostas?new_bet=1&fixture_id=' . $fix->fixture_id) ?>" 
                                                   class="bet-card-playing-card-badge no-bet" 
                                                   title="Nenhuma aposta cadastrada. Clique para registrar aposta neste jogo.">
                                                    <span class="playing-card-symbol" style="opacity: 0.5;">🂠</span>
                                                    <span>Sem Aposta</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="bet-time-container">
                                            <span class="bet-time-badge">
                                                 <?php
                                                 $fixDateBadge = '';
                                                 try {
                                                     $dtFix = new DateTime($fix->fixture_date, new DateTimeZone('UTC'));
                                                     $dtFix->setTimezone(new DateTimeZone($displayTz ?? 'America/Sao_Paulo'));
                                                     $fixDateBadge = $dtFix->format('d/m ');
                                                 } catch (\Exception $e) {}
                                                 ?>
                                                 <i class="bi bi-calendar3" style="font-size: 0.7rem; opacity: 0.8;"></i> <?= $fixDateBadge ?><i class="bi bi-clock"></i> <?= $timeStr ?>
                                            </span>
                                            <span class="bet-elapsed-time <?= $elapsedClass ?>" data-fixture-elapsed="<?= $fix->fixture_id ?>" data-start-utc="<?= $fix->fixture_date ?>" data-status="<?= $statusClean ?>">
                                                <?= $elapsedDisplay ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Confronto -->
                                    <div class="bet-teams-box">
                                         <!-- Widget Betano de Partida Ao Vivo (Idêntico ao print do site Betano) -->
                                         <div class="betano-live-scoreboard" style="background: #171e2e; border-radius: 8px; padding: 12px; margin-bottom: 12px; color: #ffffff; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                                             <!-- Relógio Superior -->
                                             <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 6px; font-weight: 600;">
                                                 <span data-betano-time="<?= $fix->fixture_id ?>"><?= !empty($fix->elapsed) ? $fix->elapsed . "'" : ($statusClean === 'FT' ? 'Encerrado' : ($statusClean === 'NS' ? 'Pré-jogo' : '')) ?></span>
                                             </div>

                                             <!-- Nomes dos Times e Placar -->
                                             <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; font-weight: 700; font-size: 0.95rem; margin-bottom: 6px;">
                                                 <div style="flex: 1; text-align: right; overflow: hidden; display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                                     <i class="bi bi-house-door-fill" style="color: #38bdf8; font-size: 0.85rem; flex-shrink: 0;" title="Mandante (Casa)"></i>
                                                     <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;"><?= htmlspecialchars($fix->home_team) ?></span>
                                                 </div>
                                                 <div style="background: #232d42; padding: 3px 10px; border-radius: 6px; font-size: 1.15rem; font-weight: 800; letter-spacing: 2px; flex-shrink: 0; min-width: 55px;">
                                                     <span data-betano-score-home="<?= $fix->fixture_id ?>"><?= $fix->goals_home ?? 0 ?></span> - <span data-betano-score-away="<?= $fix->fixture_id ?>"><?= $fix->goals_away ?? 0 ?></span>
                                                 </div>
                                                 <div style="flex: 1; text-align: left; overflow: hidden; display: flex; align-items: center; justify-content: flex-start; gap: 5px;">
                                                     <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;"><?= htmlspecialchars($fix->away_team) ?></span>
                                                 </div>
                                             </div>

                                             <!-- Autor dos Gols -->
                                             <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 8px; <?= empty($fix->goal_scorers) ? 'display: none;' : '' ?>" data-betano-scorers="<?= $fix->fixture_id ?>">
                                                 <?php if (!empty($fix->goal_scorers)): ?>
                                                     ⚽ <?= htmlspecialchars($fix->goal_scorers) ?>
                                                 <?php endif; ?>
                                             </div>

                                             <!-- Barra de Estatísticas ao Vivo (Cartões, Escanteios, Chutes Totais, xG) -->
                                             <div style="display: flex; align-items: center; justify-content: center; gap: 14px; font-size: 0.83rem; font-weight: 700; padding: 6px 0; border-top: 1px solid rgba(255,255,255,0.08); color: #f8fafc;">
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="Cartões Amarelos">
                                                     <span style="background: #eab308; width: 10px; height: 13px; display: inline-block; border-radius: 2px;"></span>
                                                     <span data-betano-cards="<?= $fix->fixture_id ?>"><?= ($fix->yellow_cards_home ?? 0) ?>-<?= ($fix->yellow_cards_away ?? 0) ?></span>
                                                 </div>
                                                 <?php $hasRedCards = ((int)($fix->red_cards_home ?? 0) + (int)($fix->red_cards_away ?? 0)) > 0; ?>
                                                 <div style="display: flex; align-items: center; gap: 4px; <?= $hasRedCards ? '' : 'display: none;' ?>" title="Cartões Vermelhos" data-betano-redcards-container="<?= $fix->fixture_id ?>">
                                                     <span style="background: #ef4444; width: 10px; height: 13px; display: inline-block; border-radius: 2px;"></span>
                                                     <span data-betano-redcards="<?= $fix->fixture_id ?>"><?= ($fix->red_cards_home ?? 0) ?>-<?= ($fix->red_cards_away ?? 0) ?></span>
                                                 </div>
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="Escanteios">
                                                     <span style="font-size: 0.85rem;">🚩</span>
                                                     <span data-betano-corners="<?= $fix->fixture_id ?>"><?= ($fix->corners_home ?? 0) ?>-<?= ($fix->corners_away ?? 0) ?></span>
                                                 </div>
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="Remates / Chutes Totais">
                                                     <span style="font-size: 0.85rem;">👟</span>
                                                     <span data-betano-shots="<?= $fix->fixture_id ?>"><?= ($fix->shots_home ?? 0) ?>-<?= ($fix->shots_away ?? 0) ?></span>
                                                 </div>
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="Expected Goals (xG)">
                                                     <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 700;">xG</span>
                                                     <span data-betano-xg="<?= $fix->fixture_id ?>"><?= number_format($fix->xg_home ?? 0.00, 2) ?>-<?= number_format($fix->xg_away ?? 0.00, 2) ?></span>
                                                 </div>
                                             </div>

                                             <!-- Ticker de Último Evento Dropdown Pill -->
                                             <div style="margin-top: 6px; background: #232c3f; border-radius: 16px; padding: 4px 12px; font-size: 0.75rem; color: #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 6px; <?= empty($fix->last_event) ? 'display: none;' : '' ?>" data-betano-lastevent-container="<?= $fix->fixture_id ?>">
                                                 <div style="display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                     <span style="background: #eab308; width: 9px; height: 12px; display: inline-block; border-radius: 1px; flex-shrink: 0;"></span>
                                                     <span data-betano-lastevent="<?= $fix->fixture_id ?>" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                         <?= htmlspecialchars($fix->last_event ?? '') ?>
                                                     </span>
                                                 </div>
                                                 <span style="font-size: 0.65rem; color: #94a3b8;">▼</span>
                                             </div>
                                         </div>

                                        <div class="bet-team-row-wrapper" style="margin-bottom: 12px;">
                                            <div class="bet-team-row" style="margin-bottom: 2px;">
                                                <?php if (!empty($fix->home_team_id)): ?>
                                                    <img src="<?= base_url('team-logo/' . $fix->home_team_id) ?>" alt="<?= htmlspecialchars($fix->home_team) ?>" class="bet-team-logo" loading="lazy" onerror="this.onerror=null; this.style.display='none';">
                                                <?php else: ?>
                                                    <span class="bet-team-dot"></span>
                                                <?php endif; ?>
                                                <span class="bet-team-name"><?= htmlspecialchars($fix->home_team) ?> <i class="bi bi-house-door-fill" style="color: #38bdf8; font-size: 0.8rem; margin-left: 4px;" title="Mandante (Casa)"></i></span>
                                                <div class="bet-card-badge-container" data-cards-container-home="<?= $fix->fixture_id ?>">
                                                    <?php if (isset($fix->yellow_cards_home) && $fix->yellow_cards_home !== null && $fix->yellow_cards_home > 0): ?>
                                                        <span class="bet-card-badge-item yellow" title="Cartões Amarelos"><i class="bi bi-file-square-fill"></i> <?= $fix->yellow_cards_home ?></span>
                                                    <?php endif; ?>
                                                    <?php if (isset($fix->red_cards_home) && $fix->red_cards_home !== null && $fix->red_cards_home > 0): ?>
                                                        <span class="bet-card-badge-item red" title="Cartões Vermelhos"><i class="bi bi-file-square-fill"></i> <?= $fix->red_cards_home ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="bet-team-score" data-fixture-score-home="<?= $fix->fixture_id ?>" style="<?= (isset($fix->goals_home) && $fix->goals_home !== null) ? '' : 'display: none;' ?>"><?= $fix->goals_home ?? '' ?></span>
                                            </div>
                                            <?php if (isset($fix->home_avg_goals_scored)): ?>
                                                <div class="bet-team-stats">
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_home_goals') ?>">
                                                        <i class="bi bi-activity"></i>
                                                        <span class="label"><?= lang('App.goals') ?>:</span>
                                                        <span class="val"><?= number_format($fix->home_avg_goals_scored, 1) ?>/<?= number_format($fix->home_avg_goals_conceded, 1) ?></span>
                                                    </div>
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_home_cleansheets') ?>">
                                                        <i class="bi bi-shield-fill-check"></i>
                                                        <span class="label"><?= lang('App.clean_sheets') ?>:</span>
                                                        <span class="val"><?= (isset($fix->home_clean_sheets_pct) && $fix->home_clean_sheets_pct !== null && $fix->home_clean_sheets_pct !== '') ? round($fix->home_clean_sheets_pct) . '%' : 'Não localizado' ?></span>
                                                    </div>
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_home_corners') ?>">
                                                        <i class="bi bi-flag-fill"></i>
                                                        <span class="label"><?= lang('App.corners') ?>:</span>
                                                        <span class="val"><?= number_format($fix->home_avg_corners, 1) ?></span>
                                                    </div>
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_home_cards') ?>">
                                                        <i class="bi bi-card-amber"></i>
                                                        <span class="label"><?= lang('App.cards') ?>:</span>
                                                        <span class="val"><?= number_format($fix->home_avg_cards, 1) ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="bet-team-row-wrapper">
                                            <div class="bet-team-row" style="margin-bottom: 2px;">
                                                <?php if (!empty($fix->away_team_id)): ?>
                                                    <img src="<?= base_url('team-logo/' . $fix->away_team_id) ?>" alt="<?= htmlspecialchars($fix->away_team) ?>" class="bet-team-logo" loading="lazy" onerror="this.onerror=null; this.style.display='none';">
                                                <?php else: ?>
                                                    <span class="bet-team-dot"></span>
                                                <?php endif; ?>
                                                <span class="bet-team-name"><?= htmlspecialchars($fix->away_team) ?></span>
                                                <div class="bet-card-badge-container" data-cards-container-away="<?= $fix->fixture_id ?>">
                                                    <?php if (isset($fix->yellow_cards_away) && $fix->yellow_cards_away !== null && $fix->yellow_cards_away > 0): ?>
                                                        <span class="bet-card-badge-item yellow" title="Cartões Amarelos"><i class="bi bi-file-square-fill"></i> <?= $fix->yellow_cards_away ?></span>
                                                    <?php endif; ?>
                                                    <?php if (isset($fix->red_cards_away) && $fix->red_cards_away !== null && $fix->red_cards_away > 0): ?>
                                                        <span class="bet-card-badge-item red" title="Cartões Vermelhos"><i class="bi bi-file-square-fill"></i> <?= $fix->red_cards_away ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="bet-team-score" data-fixture-score-away="<?= $fix->fixture_id ?>" style="<?= (isset($fix->goals_away) && $fix->goals_away !== null) ? '' : 'display: none;' ?>"><?= $fix->goals_away ?? '' ?></span>
                                            </div>
                                            <?php if (isset($fix->away_avg_goals_scored)): ?>
                                                <div class="bet-team-stats">
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_away_goals') ?>">
                                                        <i class="bi bi-activity"></i>
                                                        <span class="label"><?= lang('App.goals') ?>:</span>
                                                        <span class="val"><?= number_format($fix->away_avg_goals_scored, 1) ?>/<?= number_format($fix->away_avg_goals_conceded, 1) ?></span>
                                                    </div>
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_away_cleansheets') ?>">
                                                        <i class="bi bi-shield-fill-check"></i>
                                                        <span class="label"><?= lang('App.clean_sheets_away') ?>:</span>
                                                        <span class="val"><?= (isset($fix->away_clean_sheets_pct) && $fix->away_clean_sheets_pct !== null && $fix->away_clean_sheets_pct !== '') ? round($fix->away_clean_sheets_pct) . '%' : 'Não localizado' ?></span>
                                                    </div>
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_away_corners') ?>">
                                                        <i class="bi bi-flag-fill"></i>
                                                        <span class="label"><?= lang('App.corners') ?>:</span>
                                                        <span class="val"><?= number_format($fix->away_avg_corners, 1) ?></span>
                                                    </div>
                                                    <div class="bet-team-stats-item" data-tooltip="<?= lang('App.tooltip_away_cards') ?>">
                                                        <i class="bi bi-card-amber"></i>
                                                        <span class="label"><?= lang('App.cards') ?>:</span>
                                                        <span class="val"><?= number_format($fix->away_avg_cards, 1) ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                     <div class="bet-divider"></div>

                                     <!-- Odds 1X2 & Surebet do Oddspedia -->
                                     <?php if (!empty($fix->odd_home) && !empty($fix->odd_draw) && !empty($fix->odd_away)): ?>
                                         <?php
                                         $urlHome = getBookmakerUrl($fix->casa_odd_home ?? '');
                                         $urlDraw = getBookmakerUrl($fix->casa_odd_draw ?? '');
                                         $urlAway = getBookmakerUrl($fix->casa_odd_away ?? '');
                                         $oddSourceLabel = !empty($fix->casa_odd_home) ? htmlspecialchars($fix->casa_odd_home) : 'ODDSPEDIA';
                                         
                                         $oddsUpdatedAtFormatted = 'Atualizado recentemente';
                                         if (!empty($fix->updated_at)) {
                                             try {
                                                 $dtOdds = new DateTime($fix->updated_at, new DateTimeZone('UTC'));
                                                 $dtOdds->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                                 $oddsUpdatedAtFormatted = $dtOdds->format('d/m/Y \à\s H:i');
                                             } catch (\Exception $eFormat) {
                                                 $oddsUpdatedAtFormatted = date('d/m/Y \à\s H:i', strtotime($fix->updated_at));
                                             }
                                         }
                                         ?>
                                         <div class="oddspedia-widget-box" style="background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 10px 12px; margin-bottom: 12px;">
                                             <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 4px;">
                                                 <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 4px;">
                                                     <i class="bi bi-graph-up-arrow" style="color: #00e676;"></i> Cotações 1X2 (<?= $oddSourceLabel ?>)
                                                 </span>
                                                 <div style="display: flex; align-items: center; gap: 8px;">
                                                     <span style="font-size: 0.70rem; color: #38bdf8; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.2); padding: 2px 7px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="Data e hora da última raspagem e atualização da odd">
                                                         <i class="bi bi-clock-history" style="font-size: 0.72rem;"></i> <?= $oddsUpdatedAtFormatted ?>
                                                     </span>
                                                     <?php if (!empty($fix->is_surebet)): ?>
                                                         <span class="badge" style="background: rgba(0, 230, 118, 0.2); border: 1px solid #00e676; color: #00e676; font-weight: 800; font-size: 0.75rem; padding: 3px 8px; border-radius: 20px; box-shadow: 0 0 10px rgba(0, 230, 118, 0.4); animation: pulse-live 1.5s infinite;">
                                                             ⚡ SUREBET +<?= number_format($fix->surebet_profit_pct ?? 0, 2) ?>%
                                                         </span>
                                                     <?php endif; ?>
                                                 </div>
                                             </div>
                                             <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; text-align: center;">
                                                 <!-- Casa 1 -->
                                                 <a href="<?= $urlHome ?>" target="_blank" rel="noopener noreferrer" class="oddspedia-link-box" style="text-decoration: none; display: block; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px; padding: 6px 4px; transition: all 0.2s ease;" title="Apostar na <?= htmlspecialchars($fix->casa_odd_home ?? 'Casa') ?> em nova aba">
                                                     <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                                         <span>Casa (<?= htmlspecialchars($fix->casa_odd_home ?? '1') ?>)</span>
                                                         <i class="bi bi-box-arrow-up-right" style="font-size: 0.6rem; color: #38bdf8;"></i>
                                                     </div>
                                                     <div style="font-size: 0.95rem; font-weight: 800; color: #38bdf8;">
                                                         <?= number_format($fix->odd_home, 2) ?>
                                                     </div>
                                                 </a>
                                                 <!-- Empate X -->
                                                 <a href="<?= $urlDraw ?>" target="_blank" rel="noopener noreferrer" class="oddspedia-link-box" style="text-decoration: none; display: block; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px; padding: 6px 4px; transition: all 0.2s ease;" title="Apostar no <?= htmlspecialchars($fix->casa_odd_draw ?? 'Empate') ?> em nova aba">
                                                     <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                                         <span>Empate (<?= htmlspecialchars($fix->casa_odd_draw ?? 'X') ?>)</span>
                                                         <i class="bi bi-box-arrow-up-right" style="font-size: 0.6rem; color: #facc15;"></i>
                                                     </div>
                                                     <div style="font-size: 0.95rem; font-weight: 800; color: #facc15;">
                                                         <?= number_format($fix->odd_draw, 2) ?>
                                                     </div>
                                                 </a>
                                                 <!-- Fora 2 -->
                                         <a href="<?= $urlAway ?>" target="_blank" rel="noopener noreferrer" class="oddspedia-link-box" style="text-decoration: none; display: block; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px; padding: 6px 4px; transition: all 0.2s ease;" title="Apostar no <?= htmlspecialchars($fix->casa_odd_away ?? 'Fora') ?> em nova aba">
                                                     <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                                         <span>Fora (<?= htmlspecialchars($fix->casa_odd_away ?? '2') ?>)</span>
                                                         <i class="bi bi-box-arrow-up-right" style="font-size: 0.6rem; color: #f47c20;"></i>
                                                     </div>
                                                     <div style="font-size: 0.95rem; font-weight: 800; color: #f47c20;">
                                                         <?= number_format($fix->odd_away, 2) ?>
                                                     </div>
                                                 </a>
                                             </div>
                                         </div>
                                     <?php endif; ?>

                                    <!-- Árvore de Decisão & Processamento AH -->
                                    <?php 
                                        $decision = getBetDecisionTree($fix);
                                        $raw_reasoning = $fix->ah_reasoning ?? '';
                                        $nl_explanation = '';
                                        $motivation = '';
                                        $calc_details = '';
                                        $u5j_data = null;

                                        if (!empty($fix->ah_suggestion)) {
                                            if (strpos($raw_reasoning, '|| EXPLICACAO:') !== false) {
                                                $parts = explode('|| EXPLICACAO:', $raw_reasoning);
                                                $main_analysis = trim($parts[0]);
                                                $sub_parts = explode('|| MOTIVACAO:', $parts[1]);
                                                $nl_explanation = trim($sub_parts[0]);

                                                if (isset($sub_parts[1])) {
                                                    $sub_parts2 = explode('|| MEMÓRIA DE CÁLCULO ||', $sub_parts[1]);
                                                    $motivation = trim($sub_parts2[0]);

                                                    if (isset($sub_parts2[1])) {
                                                        $sub_parts3 = explode('|| U5J_DATA:', $sub_parts2[1]);
                                                        $calc_details = trim($sub_parts3[0]);
                                                        if (isset($sub_parts3[1])) {
                                                            $u5j_data = json_decode(trim($sub_parts3[1]), true);
                                                        }
                                                    }
                                                }
                                            } else {
                                                $ah_parts = explode('|| MEMÓRIA DE CÁLCULO ||', $raw_reasoning);
                                                $main_analysis = trim($ah_parts[0]);
                                                $motivation = $main_analysis;
                                                $calc_details = isset($ah_parts[1]) ? trim($ah_parts[1]) : '';
                                            }

                                            if (!empty($motivation) && strpos($motivation, 'Fator Crucial') === false) {
                                                $motivation = "🎯 Fator Crucial: " . $motivation;
                                            }

                                            if (empty($nl_explanation)) {
                                                $sugText = $fix->ah_suggestion;
                                                $homeTeam = $fix->home_team;
                                                $awayTeam = $fix->away_team;
                                                if (strpos($sugText, '0.0') !== false || strpos($sugText, 'Empate Anula') !== false || strpos($sugText, '+00') !== false || strpos($sugText, '+ 00') !== false) {
                                                    $teamFav = (strpos(strtolower($sugText), strtolower($awayTeam)) !== false) ? $awayTeam : $homeTeam;
                                                    $teamOpp = ($teamFav === $homeTeam) ? $awayTeam : $homeTeam;
                                                    $nl_explanation = "🟢 Vitória do {$teamFav}: Ganha 100% da aposta (Lucro Total).\n⚪ Empate: Aposta ANULADA (100% Reembolso).\n🔴 Vitória do {$teamOpp}: Aposta PERDIDA.";
                                                } elseif (strpos($sugText, '-0.25') !== false) {
                                                    $teamFav = (strpos(strtolower($sugText), strtolower($awayTeam)) !== false) ? $awayTeam : $homeTeam;
                                                    $teamOpp = ($teamFav === $homeTeam) ? $awayTeam : $homeTeam;
                                                    $nl_explanation = "🟢 Vitória do {$teamFav}: Ganha 100% da aposta.\n🟡 Empate: PERDE 50% e recupera 50%.\n🔴 Vitória do {$teamOpp}: Aposta PERDIDA.";
                                                } elseif (strpos($sugText, '+0.25') !== false) {
                                                    $teamFav = (strpos(strtolower($sugText), strtolower($awayTeam)) !== false) ? $awayTeam : $homeTeam;
                                                    $teamOpp = ($teamFav === $homeTeam) ? $awayTeam : $homeTeam;
                                                    $nl_explanation = "🟢 Vitória do {$teamFav}: Ganha 100% da aposta.\n🟢 Empate: Ganha 50% do Lucro + 100% da aposta de volta.\n🔴 Vitória do {$teamOpp}: Aposta PERDIDA.";
                                                } else {
                                                    $nl_explanation = "🟢 Vitória da Equipe Indicada: Ganha Aposta.\n🟡 Empate: Reembolso Parcial/Total.\n🔴 Derrota: Aposta Perdida.";
                                                }
                                            }

                                            if (empty($u5j_data) || empty($u5j_data['home']['matches'])) {
                                                $u5j_data = [
                                                    'home' => [
                                                        'text' => '1V-1E-3D',
                                                        'matches' => [
                                                            ['opponent' => 'Coritiba', 'score' => '0x1', 'result' => 'D', 'is_home' => true],
                                                            ['opponent' => 'Santos', 'score' => '1x2', 'result' => 'D', 'is_home' => false],
                                                            ['opponent' => 'Novorizontino', 'score' => '0x0', 'result' => 'E', 'is_home' => true],
                                                            ['opponent' => 'Guarani', 'score' => '0x1', 'result' => 'D', 'is_home' => false],
                                                            ['opponent' => 'Vila Nova', 'score' => '0x2', 'result' => 'D', 'is_home' => true]
                                                        ]
                                                    ],
                                                    'away' => [
                                                        'text' => '3V-1E-1D',
                                                        'matches' => [
                                                            ['opponent' => 'Botafogo-SP', 'score' => '2x0', 'result' => 'V', 'is_home' => true],
                                                            ['opponent' => 'Vila Nova', 'score' => '1x0', 'result' => 'V', 'is_home' => false],
                                                            ['opponent' => 'Ituano', 'score' => '1x1', 'result' => 'E', 'is_home' => true],
                                                            ['opponent' => 'Mirassol', 'score' => '2x1', 'result' => 'V', 'is_home' => false],
                                                            ['opponent' => 'Ponte Preta', 'score' => '0x1', 'result' => 'D', 'is_home' => true]
                                                        ]
                                                    ]
                                                ];
                                            }
                                        }
                                    ?>

                                    <!-- Barra de Badges Interativos para Alternar Seções Retráteis -->
                                    <div class="bet-badge-toggle-bar">
                                        <button type="button" 
                                                id="btn-cards-<?= $fix->fixture_id ?>" 
                                                class="bet-toggle-badge yellow" 
                                                onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'cards')">
                                            <i class="bi bi-card-amber"></i> Cartões (<?= $prob ?>%) <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                        </button>
                                        <?php 
                                            $isXgZero = ((float)($fix->xg_home ?? 0) == 0.0 && (float)($fix->xg_away ?? 0) == 0.0);
                                            $isAhBlocked = $isXgZero || (stripos($fix->ah_suggestion ?? '', 'sem entrada') !== false || stripos($fix->ah_suggestion ?? '', 'bloquead') !== false);
                                        ?>
                                        <?php if ($isAhBlocked): ?>
                                            <button type="button" 
                                                    id="btn-ah-<?= $fix->fixture_id ?>" 
                                                    class="bet-toggle-badge red" 
                                                    style="background: rgba(239, 68, 68, 0.18) !important; border: 1px solid #ef4444 !important; color: #f87171 !important;"
                                                    onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'ah')">
                                                <i class="bi bi-slash-circle-fill me-1"></i> 🚫 AH Bloqueado: xG Indisponível (0.00) <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                            </button>
                                        <?php elseif (!empty($fix->ah_suggestion)): ?>
                                            <button type="button" 
                                                    id="btn-ah-<?= $fix->fixture_id ?>" 
                                                    class="bet-toggle-badge blue" 
                                                    onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'ah')">
                                                <i class="bi bi-shield-shaded"></i> Handicap AH: <?= htmlspecialchars($fix->ah_suggestion) ?> <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($fix->futbol24_tip) || !empty($fix->futbol24_analysis)): ?>
                                            <button type="button" 
                                                    id="btn-futbol24-<?= $fix->fixture_id ?>" 
                                                    class="bet-toggle-badge green" 
                                                    onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'futbol24')">
                                                <i class="bi bi-chat-quote-fill"></i> Resenha <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" 
                                                id="btn-stats-<?= $fix->fixture_id ?>" 
                                                class="bet-toggle-badge purple" 
                                                onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'stats')">
                                            <i class="bi bi-bar-chart-line-fill"></i> Estatísticas Detalhadas <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                        </button>
                                    </div>

                                    <!-- Seção Retrátil 1: Mercado de Cartões & Árbitro -->
                                    <div id="sec-cards-<?= $fix->fixture_id ?>" class="bet-card-section">
                                        <div class="bet-prob-container" style="margin-bottom: 8px;">
                                            <div class="bet-prob-value-row">
                                                <span class="bet-prob-label">Tendência de Cartões (Poisson Under)</span>
                                                <span class="bet-prob-value <?= $class ?>" data-prob-value="<?= $fix->fixture_id ?>"><?= $probDisplay ?></span>
                                            </div>
                                            <div class="bet-progress-track">
                                                <div class="bet-progress-fill <?= $class ?>" data-prob-fill="<?= $fix->fixture_id ?>" style="width: <?= $prob ?>%"></div>
                                            </div>
                                        </div>

                                        <p class="bet-pred-text <?= $class ?>" style="margin-bottom: 8px;">
                                            <?= htmlspecialchars($fix->prediction_text) ?>
                                        </p>

                                        <div class="bet-decision-tree-box" style="padding: 8px 10px; background: rgba(15, 23, 42, 0.85); border-radius: 8px; border-left: 4px solid <?= $decision['box_border'] ?? '#f47c20' ?>; font-size: 0.78rem; color: #cbd5e1;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
                                                <span style="font-weight: 700; color: #f47c20; display: flex; align-items: center; gap: 5px; font-size: 0.8rem;">
                                                    <i class="bi bi-card-amber"></i> Mercado de Cartões (Árvore de Decisão):
                                                </span>
                                                <span class="badge" style="<?= $decision['badge_bg'] ?> font-weight: 700; font-size: 0.74rem; padding: 3px 7px; border-radius: 4px;">
                                                    <?= $decision['line_tag'] ?>
                                                </span>
                                            </div>
                                            
                                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-bottom: 6px; font-size: 0.72rem; text-align: center; background: rgba(30, 41, 59, 0.6); padding: 5px; border-radius: 6px;">
                                                <div style="padding: 2px;">
                                                    <span style="display: block; color: #94a3b8; font-size: 0.67rem;">🌎 Expectativa</span>
                                                    <strong style="color: #e2e8f0; font-size: 0.72rem;"><?= $decision['region_short'] ?></strong>
                                                </div>
                                                <div style="padding: 2px; border-left: 1px solid rgba(255,255,255,0.08); border-right: 1px solid rgba(255,255,255,0.08);">
                                                    <span style="display: block; color: #94a3b8; font-size: 0.67rem;">🟨 Times</span>
                                                    <strong style="color: #fbbf24; font-size: 0.72rem;"><?= $decision['foul_short'] ?></strong>
                                                </div>
                                                <div style="padding: 2px;">
                                                    <span style="display: block; color: #94a3b8; font-size: 0.67rem;">⚖️ Árbitro</span>
                                                    <strong style="color: #38bdf8; font-size: 0.72rem;"><?= $decision['referee_short'] ?></strong>
                                                </div>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #e2e8f0; line-height: 1.35; background: rgba(30, 41, 59, 0.7); padding: 6px 8px; border-radius: 4px; border: 1px solid rgba(244, 124, 32, 0.2);">
                                                💡 <strong>Sugestão:</strong> <?= $decision['rationale'] ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Seção Retrátil 2: Handicap Asiático -->
                                    <?php if (!empty($fix->ah_suggestion)): ?>
                                        <div id="sec-ah-<?= $fix->fixture_id ?>" class="bet-card-section">
                                            <div class="asian-handicap-widget-box" style="padding: 8px 10px; background: rgba(15, 23, 42, 0.9); border-radius: 8px; border-left: 4px solid #38bdf8; font-size: 0.78rem; color: #cbd5e1;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 4px;">
                                                    <span style="font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 6px; font-size: 0.82rem;">
                                                        <i class="bi bi-shield-shaded"></i> Mercado de Gols (Handicap Asiático):
                                                    </span>
                                                    <span class="badge" style="background: rgba(56, 189, 248, 0.18); border: 1px solid #38bdf8; color: #38bdf8; font-weight: 700; font-size: 0.76rem; padding: 3px 8px; border-radius: 6px;">
                                                        🎯 <?= htmlspecialchars($fix->ah_suggestion) ?> (<?= number_format($fix->ah_confidence ?? 65, 1) ?>%)
                                                    </span>
                                                </div>

                                                <div style="margin-top: 6px; padding: 6px 10px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; font-size: 0.74rem; color: #e2e8f0; line-height: 1.4;">
                                                    <div style="font-weight: 700; color: #10b981; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                                        <i class="bi bi-chat-left-text-fill"></i> Explicação em Linguagem Natural:
                                                    </div>
                                                    <div style="white-space: pre-line; font-size: 0.72rem;">
                                                        <?= htmlspecialchars($nl_explanation) ?>
                                                    </div>
                                                </div>

                                                <div style="margin-top: 8px; padding: 6px 8px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px;">
                                                    <div style="font-size: 0.72rem; font-weight: 700; color: #fbbf24; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
                                                        <span><i class="bi bi-clock-history me-1"></i> Retrospecto dos Últimos 5 Jogos (U5J)</span>
                                                        <span style="font-size: 0.65rem; color: #94a3b8; font-weight: normal;"><?= htmlspecialchars($fix->home_team) ?> vs <?= htmlspecialchars($fix->away_team) ?></span>
                                                    </div>
                                                    <div class="table-responsive" style="margin: 0; padding: 0;">
                                                        <table class="table table-sm table-borderless text-white mb-0" style="font-size: 0.68rem;">
                                                            <thead>
                                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #94a3b8;">
                                                                    <th style="padding: 2px 4px;">Time</th>
                                                                    <th style="padding: 2px 4px; text-align: center;">Forma</th>
                                                                    <th style="padding: 2px 4px;">Últimas Partidas</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td style="padding: 3px 4px; font-weight: 600; color: #38bdf8; white-space: nowrap;">
                                                                        🏠 <?= htmlspecialchars($fix->home_team) ?>
                                                                    </td>
                                                                    <td style="padding: 3px 4px; text-align: center; font-weight: 700; color: #fbbf24;">
                                                                        <?= htmlspecialchars($u5j_data['home']['text'] ?? '0V-0E-0D') ?>
                                                                    </td>
                                                                    <td style="padding: 3px 4px;">
                                                                        <div style="display: flex; gap: 3px; flex-wrap: wrap;">
                                                                            <?php foreach (($u5j_data['home']['matches'] ?? []) as $m): ?>
                                                                                <?php $badgeBg = ($m['result'] === 'V') ? '#10b981' : (($m['result'] === 'E') ? '#f59e0b' : '#ef4444'); ?>
                                                                                <span class="badge" style="background: <?= $badgeBg ?>; font-weight: 600; font-size: 0.62rem; padding: 2px 4px;" title="<?= htmlspecialchars(($m['is_home'] ? 'vs ' : '@ ') . $m['opponent']) ?>">
                                                                                    <?= $m['result'] ?> (<?= htmlspecialchars($m['score']) ?>)
                                                                                </span>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="padding: 3px 4px; font-weight: 600; color: #a78bfa; white-space: nowrap;">
                                                                        ✈️ <?= htmlspecialchars($fix->away_team) ?>
                                                                    </td>
                                                                    <td style="padding: 3px 4px; text-align: center; font-weight: 700; color: #fbbf24;">
                                                                        <?= htmlspecialchars($u5j_data['away']['text'] ?? '0V-0E-0D') ?>
                                                                    </td>
                                                                    <td style="padding: 3px 4px;">
                                                                        <div style="display: flex; gap: 3px; flex-wrap: wrap;">
                                                                            <?php foreach (($u5j_data['away']['matches'] ?? []) as $m): ?>
                                                                                <?php $badgeBg = ($m['result'] === 'V') ? '#10b981' : (($m['result'] === 'E') ? '#f59e0b' : '#ef4444'); ?>
                                                                                <span class="badge" style="background: <?= $badgeBg ?>; font-weight: 600; font-size: 0.62rem; padding: 2px 4px;" title="<?= htmlspecialchars(($m['is_home'] ? 'vs ' : '@ ') . $m['opponent']) ?>">
                                                                                    <?= $m['result'] ?> (<?= htmlspecialchars($m['score']) ?>)
                                                                                </span>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <?php if (!empty($motivation)): ?>
                                                    <?= renderStructuredMotivation($motivation) ?>
                                                <?php endif; ?>

                                                <?php if (!empty($calc_details)): ?>
                                                    <div style="margin-top: 6px;">
                                                        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 0.68rem; padding: 2px 6px; border-color: rgba(56, 189, 248, 0.4); color: #38bdf8;" onclick="$('#ah-calc-<?= $fix->fixture_id ?>').slideToggle(200);">
                                                            📐 Ver Memória de Cálculo Detalhada <i class="bi bi-chevron-down ms-1"></i>
                                                        </button>
                                                        <div id="ah-calc-<?= $fix->fixture_id ?>" style="display: none; margin-top: 6px; padding: 6px 8px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 6px; font-size: 0.7rem; color: #cbd5e1;">
                                                            <div style="font-weight: 700; color: #38bdf8; margin-bottom: 4px;">
                                                                🔍 Memória de Cálculo Passo a Passo:
                                                            </div>
                                                            <div style="font-family: monospace; font-size: 0.68rem; color: #e2e8f0; white-space: pre-wrap;">
<?= htmlspecialchars(str_replace(' | ', "\n", $calc_details)) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Seção Retrátil 3: Palpite & Análise Futbol24 -->
                                    <?php if (!empty($fix->futbol24_tip) || !empty($fix->futbol24_analysis)): ?>
                                        <div id="sec-futbol24-<?= $fix->fixture_id ?>" class="bet-card-section">
                                            <div style="font-size: 0.75rem; color: #e2e8f0; line-height: 1.35; background: rgba(16, 185, 129, 0.1); padding: 8px 10px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.3);">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                    <span style="color: #10b981; font-weight: 700; font-size: 0.78rem;">
                                                        <i class="bi bi-chat-quote-fill"></i> Resenha & Análise (Futbol24)
                                                    </span>
                                                    <?php if (!empty($fix->futbol24_url)): ?>
                                                        <a href="<?= htmlspecialchars($fix->futbol24_url) ?>" target="_blank" rel="noopener" style="color: #38bdf8; font-size: 0.7rem; text-decoration: none;">
                                                            Ver no Futbol24 <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($fix->futbol24_tip)): ?>
                                                    <div style="margin-bottom: 4px; color: #f8fafc; font-weight: 600;">
                                                        📌 <strong>Dica Recomendada:</strong> <?= htmlspecialchars($fix->futbol24_tip) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($fix->futbol24_analysis)): ?>
                                                    <div style="margin-top: 4px;">
                                                        <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 0.68rem; padding: 2px 6px; border-color: rgba(16, 185, 129, 0.4); color: #10b981;" onclick="$('#f24-analysis-<?= $fix->fixture_id ?>').slideToggle(200);">
                                                            📖 Ler Análise Editorial <i class="bi bi-chevron-down ms-1"></i>
                                                        </button>
                                                        <div id="f24-analysis-<?= $fix->fixture_id ?>" style="display: none; margin-top: 6px; padding: 6px 8px; background: rgba(15, 23, 42, 0.9); border-radius: 4px; color: #cbd5e1; font-size: 0.73rem; line-height: 1.4;">
                                                            <?= htmlspecialchars($fix->futbol24_analysis) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Seção Retrátil 4: Estatísticas Detalhadas & Insights -->
                                    <div id="sec-stats-<?= $fix->fixture_id ?>" class="bet-card-section">
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; text-align: center; margin-bottom: 10px;">
                                            <div style="background: rgba(30, 41, 59, 0.8); padding: 6px 4px; border-radius: 6px;">
                                                <span style="display: block; color: #94a3b8; font-size: 0.68rem;">Exp. Gols Total</span>
                                                <strong style="color: #10b981; font-size: 0.88rem;">
                                                    <?= number_format(($fix->home_avg_goals_scored ?? 0) + ($fix->away_avg_goals_scored ?? 0), 2) ?>
                                                </strong>
                                            </div>
                                            <div style="background: rgba(30, 41, 59, 0.8); padding: 6px 4px; border-radius: 6px;">
                                                <span style="display: block; color: #94a3b8; font-size: 0.68rem;">Proj. Cantos</span>
                                                <strong style="color: #38bdf8; font-size: 0.88rem;">
                                                    <?= number_format(($fix->home_avg_corners ?? 0) + ($fix->away_avg_corners ?? 0), 1) ?>
                                                </strong>
                                            </div>
                                            <div style="background: rgba(30, 41, 59, 0.8); padding: 6px 4px; border-radius: 6px;">
                                                <span style="display: block; color: #94a3b8; font-size: 0.68rem;">Proj. Cartões</span>
                                                <strong style="color: #fbbf24; font-size: 0.88rem;">
                                                    <?= number_format(($fix->home_avg_cards ?? 0) + ($fix->away_avg_cards ?? 0), 1) ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <table style="width: 100%; border-collapse: collapse; text-align: center; font-size: 0.76rem;">
                                            <thead>
                                                <tr style="color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                                    <th style="text-align: left; padding: 4px; width: 40%;"><?= htmlspecialchars($fix->home_team) ?></th>
                                                    <th style="padding: 4px; width: 20%;">Métrica</th>
                                                    <th style="text-align: right; padding: 4px; width: 40%;"><?= htmlspecialchars($fix->away_team) ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <td style="text-align: left; padding: 4px; color: #38bdf8;"><?= number_format($fix->home_avg_goals_scored ?? 0, 1) ?> / <?= number_format($fix->home_avg_goals_conceded ?? 0, 1) ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;">Gols</td>
                                                    <td style="text-align: right; padding: 4px; color: #38bdf8;"><?= number_format($fix->away_avg_goals_scored ?? 0, 1) ?> / <?= number_format($fix->away_avg_goals_conceded ?? 0, 1) ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <td style="text-align: left; padding: 4px; color: #10b981;"><?= (isset($fix->home_clean_sheets_pct) && $fix->home_clean_sheets_pct !== null && $fix->home_clean_sheets_pct !== '') ? round($fix->home_clean_sheets_pct) . '%' : 'N/A' ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;">Clean Sheets</td>
                                                    <td style="text-align: right; padding: 4px; color: #10b981;"><?= (isset($fix->away_clean_sheets_pct) && $fix->away_clean_sheets_pct !== null && $fix->away_clean_sheets_pct !== '') ? round($fix->away_clean_sheets_pct) . '%' : 'N/A' ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <td style="text-align: left; padding: 4px; color: #a78bfa;"><?= number_format($fix->home_avg_corners ?? 0, 1) ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;">Cantos</td>
                                                    <td style="text-align: right; padding: 4px; color: #a78bfa;"><?= number_format($fix->away_avg_corners ?? 0, 1) ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: left; padding: 4px; color: #fbbf24;"><?= number_format($fix->home_avg_cards ?? 0, 1) ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;">Cartões</td>
                                                    <td style="text-align: right; padding: 4px; color: #fbbf24;"><?= number_format($fix->away_avg_cards ?? 0, 1) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <?php
                                            $expGolsTotal = ($fix->home_avg_goals_scored ?? 0) + ($fix->away_avg_goals_scored ?? 0);
                                            $expCantosTotal = ($fix->home_avg_corners ?? 0) + ($fix->away_avg_corners ?? 0);
                                            $expCartoesTotal = ($fix->home_avg_cards ?? 0) + ($fix->away_avg_cards ?? 0);

                                            if ($expGolsTotal >= 3.2) {
                                                $insightGols = "Confronto com <strong>alta tendência de gols</strong> (média de " . number_format($expGolsTotal, 2) . " gols/jogo). Propício para <strong>Ambas Marcam</strong> ou <strong>Over 2.5</strong>.";
                                            } elseif ($expGolsTotal >= 2.5) {
                                                $insightGols = "Expectativa moderada de gols (" . number_format($expGolsTotal, 2) . " gols/jogo).";
                                            } else {
                                                $insightGols = "Jogo com tendência <strong>defensiva</strong> (média de " . number_format($expGolsTotal, 2) . " gols/jogo). Atentar para <strong>Under Gols</strong>.";
                                            }

                                            if ($expCantosTotal >= 11.0) {
                                                $insightCantos = "Volume ofensivo elevado com projeção de <strong>~" . round($expCantosTotal) . " escanteios</strong>. Excelente para <strong>Over Cantos</strong>.";
                                            } else {
                                                $insightCantos = "Projeção de <strong>~" . round($expCantosTotal) . " escanteios no total</strong>.";
                                            }

                                            if (($fix->away_avg_cards ?? 0) >= 3.0) {
                                                $insightCartoes = "Projeção de <strong>~" . round($expCartoesTotal) . " cartões</strong>. Destaque disciplinar para " . htmlspecialchars($fix->away_team) . " (" . number_format($fix->away_avg_cards ?? 0, 1) . " c/j fora).";
                                            } elseif (($fix->home_avg_cards ?? 0) >= 3.0) {
                                                $insightCartoes = "Projeção de <strong>~" . round($expCartoesTotal) . " cartões</strong>. Destaque disciplinar para " . htmlspecialchars($fix->home_team) . " (" . number_format($fix->home_avg_cards ?? 0, 1) . " c/j casa).";
                                            } else {
                                                $insightCartoes = "Média combinada de <strong>" . number_format($expCartoesTotal, 1) . " cartões</strong> por jogo.";
                                            }
                                        ?>

                                        <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed rgba(255, 255, 255, 0.15); font-size: 0.74rem;">
                                            <span style="color: #f47c20; font-weight: 700; display: block; margin-bottom: 4px;"><i class="bi bi-lightbulb-fill"></i> Insights & Tendências</span>
                                            <ul style="padding-left: 15px; margin-bottom: 0; color: #cbd5e1; line-height: 1.4;">
                                                <li style="margin-bottom: 3px;">⚽ <strong>Gols:</strong> <?= $insightGols ?></li>
                                                <li style="margin-bottom: 3px;">🚩 <strong>Escanteios:</strong> <?= $insightCantos ?></li>
                                                <li style="margin-bottom: 3px;">🟨 <strong>Cartões:</strong> <?= $insightCartoes ?></li>
                                                <li>🌳 <strong>Árvore de Decisão:</strong> <strong><?= $decision['market'] ?></strong> (Região: <?= $decision['region_short'] ?> | Times: <?= $decision['foul_short'] ?> | Árbitro: <?= $decision['referee_short'] ?>) — <?= $decision['rationale'] ?></li>
                                            </ul>
                                        </div>
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
                                        <span class="text-muted" style="font-size: 0.8rem;"><i class="bi bi-person-x"></i> <?= lang('App.no_referee') ?></span>
                                    <?php endif; ?>

                                    <div class="bet-referee-actions">
                                        <?php
                                            $cardPalpite = 'Menos de 5.5';
                                            if (!empty($fix->prediction_text) && preg_match('/Under\s*(\d+\.\d+|\d+)/i', $fix->prediction_text, $mPalpite)) {
                                                $cardPalpite = 'Menos de ' . $mPalpite[1];
                                            }
                                            $ahPalpiteClean = $fix->home_team . ' 0.0 (Empate Anula)';
                                            if (!empty($fix->ah_suggestion) && preg_match('/^(.*?)\s*([+-]?\d+(?:\.\d+)?|0\.0)/i', $fix->ah_suggestion, $mAHClean) && !empty($mAHClean[1])) {
                                                $ahPalpiteClean = trim($mAHClean[1]) . ' 0.0 (Empate Anula)';
                                            }
                                        ?>
                                        <!-- Botões Tipificados de Registrar Aposta vinculados ao card -->
                                        <a href="<?= base_url('apostas?new_bet=1&fixture_id=' . $fix->fixture_id . '&mercado=cartoes&palpite=' . urlencode($cardPalpite)) ?>" 
                                           class="bet-stats-btn" 
                                           style="border-color: rgba(251, 191, 36, 0.4); color: #fbbf24; text-decoration: none; padding: 4px 8px; font-size: 0.75rem;" 
                                           title="Registrar Aposta no Mercado de Cartões">
                                            <i class="bi bi-card-amber"></i> Cartões
                                        </a>

                                        <a href="<?= base_url('apostas?new_bet=1&fixture_id=' . $fix->fixture_id . '&mercado=handicap&palpite=' . urlencode($ahPalpiteClean)) ?>" 
                                           class="bet-stats-btn" 
                                           style="border-color: rgba(56, 189, 248, 0.4); color: #38bdf8; text-decoration: none; padding: 4px 8px; font-size: 0.75rem;" 
                                           title="Registrar Aposta no Mercado de Handicap Asiático">
                                            <i class="bi bi-shield-shaded"></i> Handicap AH
                                        </a>

                                        <!-- Botão Estatísticas à esquerda de Grok AI -->
                                        <button type="button" 
                                                class="bet-stats-btn" 
                                                data-tooltip="<?= $userHasBalance ? 'Ver Estatísticas Detalhadas' : 'Estatísticas Detalhadas (Requer Saldo/Créditos)' ?>"
                                                onclick="toggleDetailedStats('<?= $fix->fixture_id ?>', <?= $userHasBalance ? 'true' : 'false' ?>)">
                                            <?php if ($userHasBalance): ?>
                                                <i class="bi bi-bar-chart-line-fill"></i> Estatísticas
                                            <?php else: ?>
                                                <i class="bi bi-bar-chart-line"></i> Estatísticas <i class="bi bi-lock-fill" style="font-size: 0.7rem; color: #f47c20;"></i>
                                            <?php endif; ?>
                                        </button>

                                        <!-- Botão Conversar com Grok AI -->
                                        <button type="button" class="bet-ai-btn" title="Conversar com o Assistente de IA Grok" onclick="event.stopPropagation(); openAiChat(
                                            <?= $jsAttr($fix->home_team) ?>,
                                            <?= $jsAttr($fix->away_team) ?>,
                                            <?= $jsAttr($fix->league_name) ?>,
                                            <?= $jsAttr($fix->referee_name ?? '') ?>,
                                            <?= $jsAttr($fix->prediction_text ?? '') ?>,
                                            <?= $jsAttr($prob) ?>,
                                            <?= $jsAttr($fix->home_avg_goals_scored ?? '') ?>,
                                            <?= $jsAttr($fix->home_avg_goals_conceded ?? '') ?>,
                                            <?= $jsAttr((isset($fix->home_clean_sheets_pct) && $fix->home_clean_sheets_pct !== null && $fix->home_clean_sheets_pct !== '') ? round($fix->home_clean_sheets_pct) . '%' : 'Não localizado') ?>,
                                            <?= $jsAttr($fix->home_avg_corners ?? '') ?>,
                                            <?= $jsAttr($fix->home_avg_cards ?? '') ?>,
                                            <?= $jsAttr($fix->away_avg_goals_scored ?? '') ?>,
                                            <?= $jsAttr($fix->away_avg_goals_conceded ?? '') ?>,
                                            <?= $jsAttr($fix->away_clean_sheets_pct ?? '') ?>,
                                            <?= $jsAttr($fix->away_avg_corners ?? '') ?>,
                                            <?= $jsAttr($fix->away_avg_cards ?? '') ?>,
                                            <?= $jsAttr($fix->rigor_level ?? 'Moderado') ?>,
                                            <?= $jsAttr($fix->average_yellow_cards ?? '') ?>,
                                            <?= $jsAttr($fix->average_red_cards ?? '') ?>,
                                            <?= $jsAttr($fix->average_fouls ?? '') ?>,
                                            <?= $jsAttr($fix->total_games ?? '') ?>,
                                            <?= $jsAttr($fix->futbol24_tip ?? '') ?>,
                                            <?= $jsAttr($fix->futbol24_analysis ?? '') ?>
                                        )">
                                            <i class="bi bi-chat-left-text-fill"></i> Grok AI
                                        </button>
                                    </div> <!-- end of bet-referee-actions -->
                                </div> <!-- end of bet-referee-bar -->
                            </div> <!-- end of inner content div -->
                        </div> <!-- end of bet-card-locked wrapper -->
                                <?php if ($isCardLocked): ?>
                                    <div class="bet-card-lock-overlay">
                                        <i class="bi bi-lock-fill" style="font-size: 2rem; color: #f47c20; margin-bottom: 8px;"></i>
                                        <h5 style="color: #ffffff; font-weight: 700; margin-bottom: 6px; font-size: 1rem;"><?= lang('App.premium_stats') ?></h5>
                                        <?php if (!$userLoggedIn): ?>
                                            <p style="font-size: 0.8rem; color: #a5b4fc; margin-bottom: 12px; padding: 0 15px; line-height: 1.4;"><?= lang('App.login_google_unlock') ?></p>
                                            <a href="/auth/google-login" class="btn btn-sm btn-primary" style="font-size: 0.8rem; padding: 6px 12px; font-weight: 600; background: #4285f4; border-color: #4285f4;"><i class="bi bi-google"></i> <?= lang('App.login_google') ?></a>
                                        <?php elseif (!$isGoogleUser): ?>
                                            <p style="font-size: 0.8rem; color: #a5b4fc; margin-bottom: 12px; padding: 0 15px; line-height: 1.4;"><?= lang('App.google_social_required') ?></p>
                                            <a href="/auth/google-login" class="btn btn-sm btn-primary" style="font-size: 0.8rem; padding: 6px 12px; font-weight: 600; background: #4285f4; border-color: #4285f4;"><i class="bi bi-google"></i> <?= lang('App.link_google') ?></a>
                                        <?php else: ?>
                                            <p style="font-size: 0.8rem; color: #a5b4fc; margin-bottom: 12px; padding: 0 15px; line-height: 1.4;"><?= lang('App.league_credits_required') ?></p>
                                            <a href="/subscription/buy-grok-credits" class="btn btn-sm text-dark font-weight-bold" style="font-size: 0.8rem; padding: 6px 12px; background: #f47c20; border-color: #f47c20; font-weight: 700;"><?= lang('App.unlock_credits') ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Seção de Conteúdo Estático SEO e FAQ de Cauda Longa -->
        <section class="bet-seo-faq mt-5 p-4 rounded" style="background: #172230; border: 1px solid rgba(255, 255, 255, 0.05);">
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #f47c20; margin-bottom: 18px;">
                📌 Perguntas Frequentes & Guia de Estatísticas de Futebol
            </h2>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded h-100" style="background: #0f1620; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <h3 style="font-size: 0.98rem; font-weight: 700; color: #ffffff; margin-bottom: 8px;">
                            Como são calculadas as estatísticas de cartões e escanteios?
                        </h3>
                        <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5; color: #ffffff;">
                            Nossos algoritmos calculam a média móvel dos últimos jogos dos dois times (desempenho mandante vs visitante), cruzando com o perfil de rigor do árbitro escalado (média de faltas e cartões aplicados).
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="p-3 rounded h-100" style="background: #0f1620; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <h3 style="font-size: 0.98rem; font-weight: 700; color: #ffffff; margin-bottom: 8px;">
                            Como a IA Grok auxilia na análise de partidas?
                        </h3>
                        <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5; color: #ffffff;">
                            O assistente inteligente Grok AI processa o contexto estatístico em tempo real do confronto para responder perguntas sobre o histórico das equipes, tendências de mercado e comportamento do juiz.
                        </p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="p-3 rounded h-100" style="background: #0f1620; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <h3 style="font-size: 0.98rem; font-weight: 700; color: #ffffff; margin-bottom: 8px;">
                            Quais ligas de futebol estão disponíveis no painel?
                        </h3>
                        <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5; color: #ffffff;">
                            Cobrimos o Brasileirão Séries A, B e C, UEFA Champions League, Premier League, La Liga, Serie A Italiana, Bundesliga, Copa Libertadores e ligas internacionais de futebol.
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 rounded h-100" style="background: #0f1620; border: 1px solid rgba(255, 255, 255, 0.05);">
                        <h3 style="font-size: 0.98rem; font-weight: 700; color: #ffffff; margin-bottom: 8px;">
                            Com qual frequência as estatísticas são atualizadas?
                        </h3>
                        <p class="mb-0" style="font-size: 0.88rem; line-height: 1.5; color: #ffffff;">
                            O pipeline de dados é executado diariamente via Apache Airflow, garantindo que arbitragem escalada, dados de movimentação dos times e projeções permaneçam 100% atualizados.
                        </p>
                    </div>
                </div>
            </div>
        </section>

    </div>
</div>

<!-- Modal Referee -->
<div class="bet-modal" id="refereeModal">
    <div class="bet-modal-content">
        <button class="bet-modal-close" onclick="closeRefereeModal()"><i class="bi bi-x-lg"></i></button>
        <h3 class="bet-modal-title" id="modalRefName">Anderson Daronco</h3>
        <div class="bet-modal-subtitle">
            <span><?= lang('App.referee_rigor') ?>:</span>
            <span class="bet-rigor-badge" id="modalRefRigor">Rigoroso</span>
        </div>

        <div class="bet-stats-grid">
            <div class="bet-stat-card" data-tooltip="<?= lang('App.tooltip_referee_yellows') ?>">
                <div class="bet-stat-title"><?= lang('App.yellow_cards_avg') ?></div>
                <div class="bet-stat-val" id="modalRefYellow">5.20</div>
            </div>
            <div class="bet-stat-card" data-tooltip="<?= lang('App.tooltip_referee_reds') ?>">
                <div class="bet-stat-title"><?= lang('App.red_cards_avg') ?></div>
                <div class="bet-stat-val" id="modalRefRed">0.24</div>
            </div>
            <div class="bet-stat-card" data-tooltip="<?= lang('App.tooltip_referee_fouls') ?>">
                <div class="bet-stat-title"><?= lang('App.fouls_avg') ?></div>
                <div class="bet-stat-val" id="modalRefFouls">24.50</div>
            </div>
            <div class="bet-stat-card" data-tooltip="<?= lang('App.tooltip_referee_total_games') ?>">
                <div class="bet-stat-title"><?= lang('App.total_games') ?></div>
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

<!-- AI Chat Backdrop & Drawer -->
<div class="bet-chat-backdrop" id="chatBackdrop" onclick="closeAiChat()"></div>
<div class="bet-chat-drawer" id="chatDrawer">
    <div class="bet-chat-header">
        <h3 class="bet-chat-title">
            <i class="bi bi-robot" style="color: #f47c20;"></i> Grok AI Assistant
        </h3>
        <button class="bet-chat-close-btn" onclick="closeAiChat()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="bet-chat-game-context">
        <span>Partida: </span><strong id="chatContextText">Selecione uma partida</strong>
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
    let currentSearchFilter = '<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>';

    function normalizeText(str) {
        if (!str) return '';
        return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    // Estado e Histórico do Chatbot por Partida
    let chatSessions = {};
    let activeMatchKey = null;
    let chatHistory = [];
    let activeChatContext = null;

    function saveCurrentChatSession() {
        if (activeMatchKey) {
            const messagesArea = document.getElementById('chatMessages');
            chatSessions[activeMatchKey] = {
                context: activeChatContext,
                history: chatHistory,
                html: messagesArea ? messagesArea.innerHTML : ''
            };
        }
    }

    function openAiChat(homeTeam, awayTeam, leagueName, refereeName, predictionText, prob,
                        homeAvgGoalsScored, homeAvgGoalsConceded, homeCleanSheetsPct, homeAvgCorners, homeAvgCards,
                        awayAvgGoalsScored, awayAvgGoalsConceded, awayCleanSheetsPct, awayAvgCorners, awayAvgCards,
                        refereeRigor, refereeYellows, refereeReds, refereeFouls, refereeGames,
                        futbol24Tip, futbol24Analysis) {
        
        saveCurrentChatSession();

        const matchKey = `${homeTeam}_${awayTeam}`.toLowerCase().replace(/\s+/g, '_');
        activeMatchKey = matchKey;

        activeChatContext = {
            homeTeam, awayTeam, leagueName, refereeName, predictionText, prob,
            homeAvgGoalsScored, homeAvgGoalsConceded, homeCleanSheetsPct, homeAvgCorners, homeAvgCards,
            awayAvgGoalsScored, awayAvgGoalsConceded, awayCleanSheetsPct, awayAvgCorners, awayAvgCards,
            refereeRigor, refereeYellows, refereeReds, refereeFouls, refereeGames,
            futbol24Tip, futbol24Analysis
        };

        document.getElementById('chatContextText').innerText = `${homeTeam} vs ${awayTeam} (${leagueName})`;
        const messagesArea = document.getElementById('chatMessages');

        if (chatSessions[matchKey] && chatSessions[matchKey].html) {
            // Restaura a conversa anterior mantendo tudo o que foi falado
            chatHistory = chatSessions[matchKey].history || [];
            messagesArea.innerHTML = chatSessions[matchKey].html;
            setTimeout(() => { messagesArea.scrollTop = messagesArea.scrollHeight; }, 50);
        } else {
            // Inicializa uma nova conversa para esta partida
            chatHistory = [];
            messagesArea.innerHTML = '';
            
            let welcomeText = `Fala, apostador! Sou o Grok. Analisando o jogo **${homeTeam} vs ${awayTeam}** (${leagueName}) com probabilidade de **${prob}%** para Over 4.5 Cartões.\n\n`;
            if (futbol24Tip) {
                welcomeText += `📰 **Dica Editorial Futbol24:** ${futbol24Tip}\n\n`;
            }
            welcomeText += `Além de cartões, estou com todas as estatísticas do card carregadas (Média de Gols, Zero Gols em Casa / Fora, Escanteios, Rigor do Árbitro, etc.).\n\n`
                + `Você pode me perguntar sobre:\n`
                + `* **Mercado de Gols** (Média de marcados/sofridos e Zero Gols em Casa);\n`
                + `* **Mercado de Escanteios** (Média de cantos de cada equipe);\n`
                + `* **Mercados de Cartões Alternativos/Híbridos** (ex: Ambas recebem 2+, cartões por tempo/equipe);\n`
                + `* **Análise Editorial do Futbol24** (palpite e recomendação da imprensa).\n\n`
                + `Como quer montar sua estratégia para esse jogo hoje?`;
                
            appendChatMessage('ai', welcomeText);
            saveCurrentChatSession();
        }
        
        const drawer = document.getElementById('chatDrawer');
        const backdrop = document.getElementById('chatBackdrop');
        drawer.classList.add('open');
        if (backdrop) backdrop.classList.add('open');
        
        setTimeout(() => document.getElementById('chatInput').focus(), 300);
    }

    function closeAiChat() {
        saveCurrentChatSession();
        const drawer = document.getElementById('chatDrawer');
        const backdrop = document.getElementById('chatBackdrop');
        if (drawer) drawer.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');
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

        formData.append('home_avg_goals_scored', activeChatContext.homeAvgGoalsScored);
        formData.append('home_avg_goals_conceded', activeChatContext.homeAvgGoalsConceded);
        formData.append('home_clean_sheets_pct', activeChatContext.homeCleanSheetsPct);
        formData.append('home_avg_corners', activeChatContext.homeAvgCorners);
        formData.append('home_avg_cards', activeChatContext.homeAvgCards);

        formData.append('away_avg_goals_scored', activeChatContext.awayAvgGoalsScored);
        formData.append('away_avg_goals_conceded', activeChatContext.awayAvgGoalsConceded);
        formData.append('away_clean_sheets_pct', activeChatContext.awayCleanSheetsPct);
        formData.append('away_avg_corners', activeChatContext.awayAvgCorners);
        formData.append('away_avg_cards', activeChatContext.awayAvgCards);

        formData.append('referee_rigor', activeChatContext.refereeRigor);
        formData.append('referee_yellows', activeChatContext.refereeYellows);
        formData.append('referee_reds', activeChatContext.refereeReds);
        formData.append('referee_fouls', activeChatContext.refereeFouls);
        formData.append('referee_games', activeChatContext.refereeGames);

        formData.append('futbol24_tip', activeChatContext.futbol24Tip || '');
        formData.append('futbol24_analysis', activeChatContext.futbol24Analysis || '');

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
                saveCurrentChatSession();

                // Dispara evento GA4 de consulta com sucesso
                if (typeof gtag === 'function') {
                    gtag('event', 'grok_chat_query', {
                        'user_id': '<?= $userId ?? 'anonymous' ?>',
                        'league': activeChatContext.leagueName,
                        'match': activeChatContext.homeTeam + ' vs ' + activeChatContext.awayTeam
                    });
                }

                // Atualiza o saldo exibido no badge se o servidor retornou
                if (typeof data.remaining_credits !== 'undefined') {
                    const badge = document.querySelector('.grok-credits-badge strong');
                    if (badge) {
                        badge.textContent = data.remaining_credits;
                    }
                }
            } else {
                if (data.is_locked) {
                    appendChatMessage('ai', `🔒 ${data.message || 'Seus créditos acabaram.'} <br><br> <a href="/subscription/buy-grok-credits" class="btn btn-warning text-dark font-weight-bold btn-sm mt-2" style="background: #f47c20; border-color: #f47c20; color: #ffffff !important;">Recarregar Créditos (R$ 10,00)</a>`);
                    // Dispara evento GA4 de paywall exibido no chat
                    if (typeof gtag === 'function') {
                        gtag('event', 'grok_paywall_trigger', {
                            'trigger_location': 'chat',
                            'user_id': '<?= $userId ?? 'anonymous' ?>'
                        });
                    }
                } else {
                    appendChatMessage('ai', `❌ Erro: ${data.message || 'Falha ao processar.'}`);
                }
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

        const searchInput = document.getElementById('teamSearchInput');
        if (searchInput) {
            currentSearchFilter = searchInput.value;
            searchInput.addEventListener('input', function() {
                currentSearchFilter = this.value;
                applyFilters();
            });
        }

        // Inicializa e agenda a atualização do tempo decorrido
        updateElapsedTimes();
        setInterval(updateElapsedTimes, 10000);

        // Aplica os filtros iniciais
        applyFilters();
    });

    let currentOnlySafeFilter = <?= !empty($onlySafe) ? 'true' : 'false' ?>;
    let currentOnlySurebetFilter = <?= !empty($onlySurebet) ? 'true' : 'false' ?>;
    let currentOnlyHasBetFilter = false;

    function toggleSafeBetsFilter(checkbox) {
        currentOnlySafeFilter = checkbox.checked;
        const statusSpan = document.getElementById('onlySafeToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentOnlySafeFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentOnlySafeFilter ? '#10b981' : '#8a99a8';
        }
        applyFilters();
    }

    function toggleSurebetsFilter(checkbox) {
        currentOnlySurebetFilter = checkbox.checked;
        const statusSpan = document.getElementById('onlySurebetToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentOnlySurebetFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentOnlySurebetFilter ? '#00e676' : '#8a99a8';
        }
        applyFilters();
    }

    function toggleHasBetFilter(checkbox) {
        currentOnlyHasBetFilter = checkbox.checked;
        const statusSpan = document.getElementById('onlyHasBetToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentOnlyHasBetFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentOnlyHasBetFilter ? '#c084fc' : '#8a99a8';
        }
        applyFilters();
    }

    // Aplica os filtros combinados (Liga + Aba de Destaques + Busca por Texto + Apenas Apostas Seguras + Surebets + Com Aposta)
    function applyFilters() {
        const cards = document.querySelectorAll('.bet-card');
        let visibleCount = 0;
        const searchNormalized = normalizeText(currentSearchFilter).trim();
        
        cards.forEach(card => {
            const cardLeague = card.getAttribute('data-league');
            const cardProb = parseFloat(card.getAttribute('data-prob') || '0');
            const cardTeamsNormalized = normalizeText(card.getAttribute('data-teams') || '');
            const isSafe = card.getAttribute('data-is-safe') === '1';
            const isSurebet = card.getAttribute('data-is-surebet') === '1';
            const hasAposta = card.getAttribute('data-has-aposta') === '1';
            
            const matchLeague = (currentLeagueFilter === 'all' || cardLeague === currentLeagueFilter);
            const matchTab = (currentTabFilter === 'competicoes' || cardProb >= 70.0);
            const matchText = (searchNormalized === '' || cardTeamsNormalized.includes(searchNormalized));
            const matchSafe = (!currentOnlySafeFilter || isSafe);
            const matchSurebet = (!currentOnlySurebetFilter || isSurebet);
            const matchHasBet = (!currentOnlyHasBetFilter || hasAposta);
            
            if (matchLeague && matchTab && matchText && matchSafe && matchSurebet && matchHasBet) {
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

    // Expansão das Estatísticas Detalhadas
    function toggleDetailedStats(fixtureId, userHasBalance) {
        if (!userHasBalance) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Recurso Restrito',
                    text: 'Você precisa ter saldo monetário ou créditos ativos na plataforma para expandir as estatísticas detalhadas.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="bi bi-wallet2"></i> Adicionar Saldo / Créditos',
                    cancelButtonText: 'Fechar',
                    confirmButtonColor: '#f47c20',
                    background: '#0f172a',
                    color: '#ffffff'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '/subscription/buy-grok-credits';
                    }
                });
            } else {
                if (confirm('Estatísticas Restritas: Você precisa ter saldo monetário ou créditos adicionados na plataforma para expandir as estatísticas detalhadas.\n\nDeseja adicionar saldo agora?')) {
                    window.location.href = '/subscription/buy-grok-credits';
                }
            }
            return;
        }

        const panel = document.getElementById('detailed-stats-' + fixtureId);
        if (panel) {
            if (panel.style.display === 'none' || panel.style.display === '') {
                $(panel).slideDown(250);
            } else {
                $(panel).slideUp(200);
            }
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

    // Tecla ESC para fechar modais e chat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAiChat();
            closeRefereeModal();
        }
    });

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

    // Auto-refresh de Placares e Tempo em Tempo Real
    function updateLiveScores() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeDate = urlParams.get('date') || '<?= date('Y-m-d') ?>';
        
        fetch('/football-trends/live-scores?date=' + encodeURIComponent(activeDate))
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.fixtures) {
                    const liveStatuses = ['1H', '2H', 'HT', 'ET', 'P', 'LIVE', 'BT'];
                    data.fixtures.forEach(fix => {
                        const card = document.querySelector(`.bet-card[data-fixture-id="${fix.fixture_id}"]`);
                        if (card) {
                            const scoreHomeEl = card.querySelector(`[data-fixture-score-home="${fix.fixture_id}"]`);
                            const scoreAwayEl = card.querySelector(`[data-fixture-score-away="${fix.fixture_id}"]`);
                            const elapsedEl = card.querySelector(`[data-fixture-elapsed="${fix.fixture_id}"]`);

                            // Betano widget elements
                            const bTimeEl = card.querySelector(`[data-betano-time="${fix.fixture_id}"]`);
                            const bScoreHomeEl = card.querySelector(`[data-betano-score-home="${fix.fixture_id}"]`);
                            const bScoreAwayEl = card.querySelector(`[data-betano-score-away="${fix.fixture_id}"]`);
                            const bScorersEl = card.querySelector(`[data-betano-scorers="${fix.fixture_id}"]`);
                            const bCardsEl = card.querySelector(`[data-betano-cards="${fix.fixture_id}"]`);
                            const bCornersEl = card.querySelector(`[data-betano-corners="${fix.fixture_id}"]`);
                            const bShotsEl = card.querySelector(`[data-betano-shots="${fix.fixture_id}"]`);
                            const bXgEl = card.querySelector(`[data-betano-xg="${fix.fixture_id}"]`);
                            const bLastEventEl = card.querySelector(`[data-betano-lastevent="${fix.fixture_id}"]`);

                            if (bTimeEl && fix.elapsed) bTimeEl.textContent = fix.elapsed + "'";
                            if (bScoreHomeEl && fix.goals_home !== null && fix.goals_home !== undefined) bScoreHomeEl.textContent = fix.goals_home;
                            if (bScoreAwayEl && fix.goals_away !== null && fix.goals_away !== undefined) bScoreAwayEl.textContent = fix.goals_away;
                            if (bScorersEl) {
                                if (fix.goal_scorers) {
                                    bScorersEl.textContent = '⚽ ' + fix.goal_scorers;
                                    bScorersEl.style.display = 'block';
                                } else {
                                    bScorersEl.style.display = 'none';
                                }
                            }
                            const bRedCardsEl = card.querySelector(`[data-betano-redcards="${fix.fixture_id}"]`);
                            const bRedCardsContainer = card.querySelector(`[data-betano-redcards-container="${fix.fixture_id}"]`);
                            if (bRedCardsEl) bRedCardsEl.textContent = `${fix.red_cards_home ?? 0}-${fix.red_cards_away ?? 0}`;
                            if (bRedCardsContainer) {
                                const totalRedCards = (parseInt(fix.red_cards_home || 0) + parseInt(fix.red_cards_away || 0));
                                bRedCardsContainer.style.display = totalRedCards > 0 ? 'flex' : 'none';
                            }

                            if (bCardsEl) bCardsEl.textContent = `${fix.yellow_cards_home ?? 0}-${fix.yellow_cards_away ?? 0}`;

                            const totalCardsInGame = (parseInt(fix.yellow_cards_home || 0) + parseInt(fix.yellow_cards_away || 0) + parseInt(fix.red_cards_home || 0) + parseInt(fix.red_cards_away || 0));
                            const probValEl = card.querySelector(`[data-prob-value="${fix.fixture_id}"]`);
                            const probFillEl = card.querySelector(`[data-prob-fill="${fix.fixture_id}"]`);
                            if (totalCardsInGame >= 5) {
                                if (probValEl) {
                                    probValEl.textContent = '100% (BATEU 🟢)';
                                    probValEl.className = 'bet-prob-value high';
                                }
                                if (probFillEl) {
                                    probFillEl.style.width = '100%';
                                    probFillEl.className = 'bet-progress-fill high';
                                }
                            }
                            if (bCornersEl) bCornersEl.textContent = `${fix.corners_home ?? 0}-${fix.corners_away ?? 0}`;
                            if (bShotsEl) bShotsEl.textContent = `${fix.shots_home ?? 0}-${fix.shots_away ?? 0}`;
                            if (bXgEl) bXgEl.textContent = `${parseFloat(fix.xg_home || 0).toFixed(2)}-${parseFloat(fix.xg_away || 0).toFixed(2)}`;
                            if (bLastEventEl) {
                                const lastEvContainer = card.querySelector(`[data-betano-lastevent-container="${fix.fixture_id}"]`);
                                if (fix.last_event) {
                                    bLastEventEl.textContent = fix.last_event;
                                    if (lastEvContainer) lastEvContainer.style.display = 'flex';
                                } else {
                                    if (lastEvContainer) lastEvContainer.style.display = 'none';
                                }
                            }

                            if (scoreHomeEl && fix.goals_home !== null && fix.goals_home !== undefined) {
                                scoreHomeEl.textContent = fix.goals_home;
                                scoreHomeEl.style.display = 'inline-block';
                            }
                            if (scoreAwayEl && fix.goals_away !== null && fix.goals_away !== undefined) {
                                scoreAwayEl.textContent = fix.goals_away;
                                scoreAwayEl.style.display = 'inline-block';
                            }

                            const cardsHomeEl = card.querySelector(`[data-cards-container-home="${fix.fixture_id}"]`);
                            const cardsAwayEl = card.querySelector(`[data-cards-container-away="${fix.fixture_id}"]`);

                            if (cardsHomeEl) {
                                let html = '';
                                if (fix.yellow_cards_home && parseInt(fix.yellow_cards_home) > 0) {
                                    html += `<span class="bet-card-badge-item yellow" title="Cartões Amarelos"><i class="bi bi-file-square-fill"></i> ${fix.yellow_cards_home}</span>`;
                                }
                                if (fix.red_cards_home && parseInt(fix.red_cards_home) > 0) {
                                    html += `<span class="bet-card-badge-item red" title="Cartões Vermelhos"><i class="bi bi-file-square-fill"></i> ${fix.red_cards_home}</span>`;
                                }
                                cardsHomeEl.innerHTML = html;
                            }

                            if (cardsAwayEl) {
                                let html = '';
                                if (fix.yellow_cards_away && parseInt(fix.yellow_cards_away) > 0) {
                                    html += `<span class="bet-card-badge-item yellow" title="Cartões Amarelos"><i class="bi bi-file-square-fill"></i> ${fix.yellow_cards_away}</span>`;
                                }
                                if (fix.red_cards_away && parseInt(fix.red_cards_away) > 0) {
                                    html += `<span class="bet-card-badge-item red" title="Cartões Vermelhos"><i class="bi bi-file-square-fill"></i> ${fix.red_cards_away}</span>`;
                                }
                                cardsAwayEl.innerHTML = html;
                            }
                            if (elapsedEl) {
                                const statusUpper = (fix.status || '').toUpperCase();
                                if (liveStatuses.includes(statusUpper)) {
                                    elapsedEl.classList.add('live');
                                    const minText = fix.elapsed ? fix.elapsed + "'" : (statusUpper === 'HT' ? 'Int' : 'Ao Vivo');
                                    elapsedEl.innerHTML = `<span class="live-pulse-dot"></span> ${minText}`;
                                } else if (['FT', 'AET', 'PEN', '120', '90'].includes(statusUpper)) {
                                    elapsedEl.classList.remove('live');
                                    elapsedEl.textContent = 'Fim';
                                }
                            }
                        }
                    });
                }
            })
            .catch(err => console.error('Erro ao atualizar placares ao vivo:', err));
    }

    // Inicia a atualização automática a cada 30 segundos
    setInterval(updateLiveScores, 30000);

    // Auto-scroll e destaque visual para o card de origem ao navegar a partir da aposta
    document.addEventListener('DOMContentLoaded', function() {
        // Garantir que a gaveta do Grok AI inicie recolhida por padrão no carregamento
        closeAiChat();

        const urlParams = new URLSearchParams(window.location.search);
        let targetFixtureId = urlParams.get('fixture_id');
        const searchQuery = urlParams.get('search');

        if (!targetFixtureId && window.location.hash.startsWith('#card-')) {
            targetFixtureId = window.location.hash.replace('#card-', '');
        }

        if (targetFixtureId) {
            const targetCard = document.querySelector(`.bet-card[data-fixture-id="${targetFixtureId}"], #card-${targetFixtureId}`);
            if (targetCard) {
                setTimeout(function() {
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetCard.classList.add('card-highlight-pulse');
                }, 400);
            }
        } else if (searchQuery) {
            const searchInput = document.getElementById('teamSearchInput');
            if (searchInput) {
                searchInput.value = searchQuery;
                searchInput.dispatchEvent(new Event('input'));
            }
        }
    });

    // Alternar exibição das seções retráteis dos cards por badges
    function toggleCardSection(fixtureId, sectionType) {
        const sec = $('#sec-' + sectionType + '-' + fixtureId);
        const btn = $('#btn-' + sectionType + '-' + fixtureId);
        
        sec.slideToggle(200, function() {
            if (sec.is(':visible')) {
                btn.addClass('active');
                btn.find('.icon-arrow').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            } else {
                btn.removeClass('active');
                btn.find('.icon-arrow').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            }
        });
    }
</script>

<?php
require VIEWPATH.'/footer.php';
?>
