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
    function renderStructuredMotivation($rawMotivation, $rawReasoning = '', $fix = null) {
        if (empty($rawMotivation) && empty($rawReasoning) && !$fix) return '';

        // Limpa prefixos redundantes
        $cleanText = preg_replace('/^(🎯\s*Fator Crucial:\s*|💡\s*Motivação:\s*|MOTIVACAO:\s*)/u', '', trim($rawMotivation));

        $topics = [];

        // 1. Tenta quebrar por marcadores de tópicos explícitos (ex: "• ")
        if (strpos($cleanText, '•') !== false) {
            $rawParts = explode('•', $cleanText);
            foreach ($rawParts as $index => $part) {
                $t = trim($part);
                if (empty($t)) {
                    continue;
                }
                // Se for o texto antes da primeira vieta (cabeçalho), limpa a frase introdutória "A indicação a favor..."
                if ($index === 0) {
                    $t = preg_replace('/\n?A indicação a favor.*$/us', '', $t);
                    $t = trim($t);
                    if (empty($t)) {
                        continue;
                    }
                }
                // Garante que o tópico termina de forma limpa sem ponto extra duplo
                $t = preg_replace('/\.\s*\.$/', '.', $t);
                $topics[] = $t;
            }
        }

        // 2. Se não houver '•', tenta quebrar por linhas de texto "\n"
        if (empty($topics)) {
            $lines = explode("\n", $cleanText);
            foreach ($lines as $line) {
                $t = trim(preg_replace('/^(?:\d+[\.\)]|•|-)\s*/u', '', trim($line)));
                if (mb_strlen($t) > 4 && strpos($t, 'A indicação a favor') !== 0 && strpos($t, 'fundamenta-se') === false) {
                    $topics[] = $t;
                }
            }
        }

        // 3. Fallback por frases completas se ainda estiver vazio
        if (empty($topics)) {
            $sentences = preg_split('/(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÀÃÕÇ])/u', $cleanText);
            foreach ($sentences as $s) {
                $s = trim($s);
                if (mb_strlen($s) > 5 && strpos($s, 'A indicação a favor') !== 0) {
                    $topics[] = $s;
                }
            }
        }

        if (empty($topics)) {
            $topics[] = $cleanText;
        }

        // Extração/Cálculo dos dados de Probabilidade 1X2 (%) para a tabela de fecho
        $fullSource = $rawMotivation . ' ' . $rawReasoning;
        $probData = null;
        if (preg_match('/\|\|\s*PROBABILIDADES_1X2:\s*(\{.*?\})/u', $fullSource, $mProb)) {
            $probData = json_decode($mProb[1], true);
        }

        if (!$probData && $fix) {
            $oh = (float)($fix->odd_home ?? 0);
            $od = (float)($fix->odd_draw ?? 3.20);
            $oa = (float)($fix->odd_away ?? 0);
            $lh = max(0.4, (float)($fix->home_avg_goals_scored ?? 1.3));
            $la = max(0.4, (float)($fix->away_avg_goals_scored ?? 1.1));

            $fact = function($n) {
                $f = 1;
                for ($i = 2; $i <= $n; $i++) $f *= $i;
                return $f;
            };

            $ph = $pd = $pa = 0.0;
            for ($hg = 0; $hg < 10; $hg++) {
                for ($ag = 0; $ag < 10; $ag++) {
                    $p_h = (pow($lh, $hg) * exp(-$lh)) / $fact($hg);
                    $p_a = (pow($la, $ag) * exp(-$la)) / $fact($ag);
                    $pj = $p_h * $p_a;
                    if ($hg > $ag) $ph += $pj;
                    elseif ($hg == $ag) $pd += $pj;
                    else $pa += $pj;
                }
            }
            $tot = max(0.0001, $ph + $pd + $pa);
            $platH = round(($ph / $tot) * 100, 1);
            $platD = round(($pd / $tot) * 100, 1);
            $platA = round(($pa / $tot) * 100, 1);

            $bancaH = 45.0; $bancaD = 30.0; $bancaA = 25.0;
            if ($oh > 1.0 && $oa > 1.0) {
                $ih = 1.0 / $oh; $id = 1.0 / $od; $ia = 1.0 / $oa;
                $sinv = max(0.0001, $ih + $id + $ia);
                $bancaH = round(($ih / $sinv) * 100, 1);
                $bancaD = round(($id / $sinv) * 100, 1);
                $bancaA = round(($ia / $sinv) * 100, 1);
            }
            $probData = ['plat_h' => $platH, 'plat_d' => $platD, 'plat_a' => $platA, 'banca_h' => $bancaH, 'banca_d' => $bancaD, 'banca_a' => $bancaA];
        }

        $probTableHtml = '';
        if ($probData) {
            $platH = number_format($probData['plat_h'] ?? 0, 1) . '%';
            $platD = number_format($probData['plat_d'] ?? 0, 1) . '%';
            $platA = number_format($probData['plat_a'] ?? 0, 1) . '%';
            
            $bancaH = number_format($probData['banca_h'] ?? 0, 1) . '%';
            $bancaD = number_format($probData['banca_d'] ?? 0, 1) . '%';
            $bancaA = number_format($probData['banca_a'] ?? 0, 1) . '%';

            $probTableHtml .= '<div style="margin-top: 10px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; padding: 8px 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); overflow-x: auto;">';
            $probTableHtml .= '<div style="font-size: 0.72rem; font-weight: 700; color: #38bdf8; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">';
            $probTableHtml .= '<i class="bi bi-bar-chart-line-fill" style="color: #38bdf8; font-size: 0.80rem;"></i> ' . lang('App.prob_1x2_table_title');
            $probTableHtml .= '</div>';
            $probTableHtml .= '<table style="width: 100%; font-size: 0.68rem; text-align: center; border-collapse: collapse; color: #e2e8f0; table-layout: fixed;">';
            $probTableHtml .= '<thead>';
            $probTableHtml .= '<tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #94a3b8; font-size: 0.66rem; text-transform: uppercase;">';
            $probTableHtml .= '<th style="width: 43%; padding: 3px 2px; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . lang('App.source') . '</th>';
            $probTableHtml .= '<th style="width: 19%; padding: 3px 2px; color: #38bdf8;">' . lang('App.home_win') . '</th>';
            $probTableHtml .= '<th style="width: 19%; padding: 3px 2px; color: #f59e0b;">' . lang('App.draw') . '</th>';
            $probTableHtml .= '<th style="width: 19%; padding: 3px 2px; color: #ef4444;">' . lang('App.away_win') . '</th>';
            $probTableHtml .= '</tr>';
            $probTableHtml .= '</thead>';
            $probTableHtml .= '<tbody>';
            $probTableHtml .= '<tr style="border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 800; color: #38bdf8; background: rgba(56, 189, 248, 0.08);">';
            $probTableHtml .= '<td style="padding: 4px 2px; text-align: left; color: #38bdf8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><i class="bi bi-cpu me-1"></i> ' . lang('App.ai_platform') . '</td>';
            $probTableHtml .= '<td style="padding: 4px 2px;">' . $platH . '</td>';
            $probTableHtml .= '<td style="padding: 4px 2px;">' . $platD . '</td>';
            $probTableHtml .= '<td style="padding: 4px 2px;">' . $platA . '</td>';
            $probTableHtml .= '</tr>';
            $probTableHtml .= '<tr style="color: #cbd5e1;">';
            $probTableHtml .= '<td style="padding: 4px 2px; text-align: left; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><i class="bi bi-bank me-1"></i> ' . lang('App.bookmaker_odds') . '</td>';
            $probTableHtml .= '<td style="padding: 4px 2px;">' . $bancaH . '</td>';
            $probTableHtml .= '<td style="padding: 4px 2px;">' . $bancaD . '</td>';
            $probTableHtml .= '<td style="padding: 4px 2px;">' . $bancaA . '</td>';
            $probTableHtml .= '</tr>';
            $probTableHtml .= '</tbody>';
            $probTableHtml .= '</table>';
            $probTableHtml .= '</div>';
        }

        $html = '<div class="motivation-structured-box" style="margin-top: 8px; padding: 10px 12px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(56, 189, 248, 0.25); border-left: 4px solid #38bdf8; border-radius: 8px;">';
        $html .= '<div style="font-size: 0.76rem; font-weight: 700; color: #38bdf8; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">';
        $html .= '<i class="bi bi-list-check" style="font-size: 0.88rem; color: #38bdf8;"></i> ' . lang('App.detailed_motivation_title');
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
        $html .= $probTableHtml;
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('formatStructuredPredictionText')) {
    function formatStructuredPredictionText($predText) {
        if (empty($predText)) {
            return '';
        }

        $predText = trim($predText);

        if (strpos($predText, 'NO_BET') !== false || (strpos($predText, 'Risco') !== false && strpos($predText, 'Estratégia') === false)) {
            $formatted = str_replace(
                ['Jogo com expectativa muito elevada', 'cartões', 'Nenhuma aposta recomendada'],
                [lang('App.match_high_exp'), lang('App.cards_unit'), lang('App.no_bet_recommended')],
                $predText
            );
            return '<div class="pred-text-box nobet-box" style="padding: 8px 12px; margin-bottom: 8px; background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; border-radius: 6px; font-size: 0.76rem; color: #fca5a5; line-height: 1.4;">'
                . htmlspecialchars($formatted) . '</div>';
        }

        $teamLine = '';
        $valueLine = '';
        $strategyLine = '';

        if (strpos($predText, 'Palpite Por Time:') !== false) {
            $parts = preg_split('/🚩?\s*Palpite Por Time:\s*/u', $predText);
            $predText = trim($parts[0]);
            $teamLine = trim($parts[1] ?? '');
        }

        if (strpos($predText, 'Sugestões de valor:') !== false) {
            $parts = explode('Sugestões de valor:', $predText);
            $strategyLine = trim($parts[0]);
            $valueLine = trim($parts[1] ?? '');
        } else {
            $strategyLine = $predText;
        }

        $strategyLine = preg_replace('/^[\s🛡️]+/u', '', $strategyLine);
        $strategyLine = str_replace(
            ['Estratégia Under', 'Under Strategy', 'Estrategia Under'],
            lang('App.under_strategy'),
            $strategyLine
        );
        $strategyLine = str_replace(['Expectativa:', 'Expectation:'], lang('App.expectation') . ':', $strategyLine);
        $strategyLine = str_replace(['cartões', 'cards', 'tarjetas'], lang('App.cards_unit'), $strategyLine);

        if (!empty($valueLine)) {
            $valueLine = str_replace(
                ['1ª Opção', '1st Option', '1ª Opción'],
                lang('App.option_1'),
                $valueLine
            );
            $valueLine = str_replace(
                ['2ª Opção', '2nd Option', '2ª Opción'],
                lang('App.option_2'),
                $valueLine
            );
            $valueLine = str_replace(
                ['Odd Justa', 'Fair Odds', 'Cuota Justa'],
                lang('App.fair_odd'),
                $valueLine
            );
        }

        if (!empty($teamLine)) {
            $teamLine = str_replace(
                ['Mandante Risco Elevado', 'Mandante Under', 'Visitante Risco Elevado', 'Visitante Under', 'Mandante', 'Visitante'],
                [lang('App.home_high_risk'), lang('App.home_under'), lang('App.away_high_risk'), lang('App.away_under'), lang('App.home_label'), lang('App.away_label')],
                $teamLine
            );
        }

        $html = '<div class="pred-text-structured-box" style="margin-bottom: 10px; padding: 10px 12px; background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(56, 189, 248, 0.2); border-radius: 8px; font-size: 0.76rem; color: #cbd5e1; line-height: 1.55;">';

        if (!empty($strategyLine)) {
            $html .= '<div style="font-weight: 700; color: #38bdf8; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">';
            $html .= $strategyLine;
            $html .= '</div>';
        }

        if (!empty($valueLine)) {
            $html .= '<div style="margin-top: 6px; padding: 6px 10px; background: rgba(244, 124, 32, 0.08); border-left: 3px solid #f47c20; border-radius: 4px; margin-bottom: 6px;">';
            $html .= '<span style="color: #f47c20; font-weight: 700;">💡 ' . lang('App.value_suggestions') . ':</span> ';
            $html .= '<span style="color: #e2e8f0;">' . htmlspecialchars($valueLine) . '</span>';
            $html .= '</div>';
        }

        if (!empty($teamLine)) {
            $html .= '<div style="margin-top: 6px; padding: 6px 10px; background: rgba(167, 139, 250, 0.08); border-left: 3px solid #a78bfa; border-radius: 4px;">';
            $html .= '<span style="color: #a78bfa; font-weight: 700;">🚩 ' . lang('App.cards_per_team') . ':</span> ';
            $html .= '<span style="color: #e2e8f0;">' . htmlspecialchars($teamLine) . '</span>';
            $html .= '</div>';
        }

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
    71   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    72   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    73   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    74   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    75   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    76   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
    94   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true],
    95   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => false],
    96   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => false],
    39   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true],
    40   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
    41   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
    42   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
    45   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
    48   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
    140  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true],
    141  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => false],
    143  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => false],
    135  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true],
    136  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => false],
    137  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => false],
    78   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true],
    79   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => false],
    81   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => false],
    61   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => true],
    62   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => false],
    66   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => false],
    88   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true],
    89   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => false],
    90   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => false],
    262  => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true],
    263  => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => false],
    128  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true],
    129  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => false],
    130  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => false],
    253  => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true],
    254  => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => false],
    113  => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => true],
    114  => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => false],
    103  => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => true],
    104  => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => false],
    244  => ['country' => 'Finlândia', 'flag' => '🇫🇮', 'popular' => false],
    283  => ['country' => 'Romênia', 'flag' => '🇷🇴', 'popular' => false],
    286  => ['country' => 'Sérvia', 'flag' => '🇷🇸', 'popular' => false],
    281  => ['country' => 'Peru', 'flag' => '🇵🇪', 'popular' => false],
    242  => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
    917  => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
    268  => ['country' => 'Uruguai', 'flag' => '🇺🇾', 'popular' => false],
    265  => ['country' => 'Chile', 'flag' => '🇨🇱', 'popular' => false],
    239  => ['country' => 'Colômbia', 'flag' => '🇨🇴', 'popular' => false],
    169  => ['country' => 'China', 'flag' => '🇨🇳', 'popular' => false],
    292  => ['country' => 'Coreia do Sul', 'flag' => '🇰🇷', 'popular' => false],
    98   => ['country' => 'Japão', 'flag' => '🇯🇵', 'popular' => false],
    307  => ['country' => 'Arábia Saudita', 'flag' => '🇸🇦', 'popular' => false],
    203  => ['country' => 'Turquia', 'flag' => '🇹🇷', 'popular' => false],
    207  => ['country' => 'Suíça', 'flag' => '🇨🇭', 'popular' => false],
    144  => ['country' => 'Bélgica', 'flag' => '🇧🇪', 'popular' => false],
    119  => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
    121  => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
    218  => ['country' => 'Áustria', 'flag' => '🇦🇹', 'popular' => false],
    197  => ['country' => 'Grécia', 'flag' => '🇬🇷', 'popular' => false],
    106  => ['country' => 'Polônia', 'flag' => '🇵🇱', 'popular' => false],
    345  => ['country' => 'República Tcheca', 'flag' => '🇨🇿', 'popular' => false],
    501  => ['country' => 'Paraguai', 'flag' => '🇵🇾', 'popular' => false],

    // Competições Internacionais -> INTERNACIONAL
    1    => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false],
    2    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    3    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    4    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    5    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    9    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
    10   => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => true],
    11   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
    13   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
    15   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    17   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    18   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    531  => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
    667  => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false],
    772  => ['country' => 'INTERNACIONAL', 'flag' => '🌎', 'popular' => false],
    848  => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    1028 => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
];

if (!function_exists('formatLeagueDisplayName')) {
    function formatLeagueDisplayName($leagueId, $leagueName) {
        $lId = (int)$leagueId;
        $lName = trim($leagueName ?? '');
        $lLower = strtolower($lName);

        // Mapeamento por League ID oficial API-Sports
        if ($lId === 71) return 'Brasileirão Série A';
        if ($lId === 72) return 'Brasileirão Série B';
        if ($lId === 75) return 'Brasileirão Série C';
        if ($lId === 76 || $lId === 74) return 'Brasileirão Série D';
        if ($lId === 73) return 'Copa do Brasil';

        if ($lId === 135) return 'Serie A Italiana';
        if ($lId === 136) return 'Serie B Italiana';
        if ($lId === 137) return 'Coppa Italia';

        if ($lId === 242 || $lId === 917) return 'Liga Pro (Equador)';
        if ($lId === 239) return 'Primera A (Colômbia)';
        if ($lId === 265) return 'Primera División (Chile)';
        if ($lId === 268) return 'Primera División (Uruguai)';
        if ($lId === 281) return 'Primera División (Peru)';
        if ($lId === 501) return 'Copa Paraguay';
        if ($lId === 128) return 'Liga Profesional Argentina';
        if ($lId === 129) return 'Primera Nacional (Argentina)';
        if ($lId === 130) return 'Copa Argentina';

        if ($lId === 94) return 'Primeira Liga (Portugal)';
        if ($lId === 95) return 'Segunda Liga (Portugal)';

        if ($lId === 61) return 'Ligue 1 (França)';
        if ($lId === 62) return 'Ligue 2 (França)';

        if ($lId === 140) return 'La Liga (Espanha)';
        if ($lId === 141) return 'Segunda División (Espanha)';

        if ($lId === 39) return 'Premier League (Inglaterra)';
        if ($lId === 40) return 'Championship (Inglaterra)';

        // Fallbacks por nome de liga caso o ID não seja um dos acima
        if (strpos($lLower, 'copa do brasil') !== false || strpos($lLower, 'copa brasil') !== false) {
            return 'Copa do Brasil';
        }
        if (strpos($lLower, 'brasileirão') !== false || strpos($lLower, 'brasileirao') !== false) {
            if (strpos($lLower, 'b') !== false) return 'Brasileirão Série B';
            if (strpos($lLower, 'c') !== false) return 'Brasileirão Série C';
            if (strpos($lLower, 'd') !== false) return 'Brasileirão Série D';
            return 'Brasileirão Série A';
        }

        return $lName;
    }
}

if (!function_exists('resolveLeagueCountryAndFlag')) {
    function resolveLeagueCountryAndFlag($leagueId, $leagueName, $leagueMap) {
        $lId = (int)$leagueId;
        if (isset($leagueMap[$lId])) {
            return [
                'country' => $leagueMap[$lId]['country'],
                'flag'    => $leagueMap[$lId]['flag'],
                'popular' => $leagueMap[$lId]['popular'] ?? false
            ];
        }

        $lNameLower = strtolower($leagueName ?? '');

        // 1. Checa competições CONMEBOL por palavra-chave (Populares)
        if (
            strpos($lNameLower, 'libertadores') !== false ||
            strpos($lNameLower, 'sudamericana') !== false ||
            strpos($lNameLower, 'recopa') !== false ||
            strpos($lNameLower, 'conmebol') !== false ||
            strpos($lNameLower, 'copa america') !== false
        ) {
            return ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true];
        }

        // 2. Checa competições internacionais por palavra-chave
        if (
            strpos($lNameLower, 'champions league') !== false ||
            strpos($lNameLower, 'europa league') !== false ||
            strpos($lNameLower, 'conference league') !== false ||
            strpos($lNameLower, 'world cup') !== false ||
            strpos($lNameLower, 'copa do mundo') !== false ||
            strpos($lNameLower, 'friendlies') !== false ||
            strpos($lNameLower, 'amistoso') !== false ||
            strpos($lNameLower, 'leagues cup') !== false ||
            strpos($lNameLower, 'nations league') !== false ||
            strpos($lNameLower, 'euro') !== false ||
            strpos($lNameLower, 'concacaf') !== false ||
            strpos($lNameLower, 'afc') !== false ||
            strpos($lNameLower, 'caf') !== false ||
            strpos($lNameLower, 'uefa') !== false ||
            strpos($lNameLower, 'internacional') !== false ||
            strpos($lNameLower, 'international') !== false
        ) {
            return ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false];
        }

        // 3. Checa países por palavra-chave exclusivas (sem ambiguidade de 'serie a/b/c/d' soltos)
        if (
            strpos($lNameLower, 'brasil') !== false || strpos($lNameLower, 'brasileirão') !== false ||
            strpos($lNameLower, 'brasileirao') !== false || strpos($lNameLower, 'copa do brasil') !== false ||
            strpos($lNameLower, 'paulista') !== false || strpos($lNameLower, 'carioca') !== false ||
            strpos($lNameLower, 'gaúcho') !== false || strpos($lNameLower, 'gaucho') !== false ||
            strpos($lNameLower, 'mineiro') !== false || strpos($lNameLower, 'baiano') !== false ||
            strpos($lNameLower, 'pernambucano') !== false || strpos($lNameLower, 'cearense') !== false ||
            strpos($lNameLower, 'paranaense') !== false || strpos($lNameLower, 'catarinense') !== false
        ) {
            return ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'primeira') !== false || strpos($lNameLower, 'portugal') !== false) {
            return ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true];
        }
        if (strpos($lNameLower, 'england') !== false || strpos($lNameLower, 'premier league') !== false || strpos($lNameLower, 'championship') !== false || strpos($lNameLower, 'league one') !== false || strpos($lNameLower, 'league two') !== false) {
            return ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true];
        }
        if (strpos($lNameLower, 'espanha') !== false || strpos($lNameLower, 'spain') !== false || strpos($lNameLower, 'la liga') !== false || strpos($lNameLower, 'segunda divisi') !== false) {
            return ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true];
        }
        if (strpos($lNameLower, 'itália') !== false || strpos($lNameLower, 'italia') !== false || strpos($lNameLower, 'coppa italia') !== false) {
            return ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true];
        }
        if (strpos($lNameLower, 'bundesliga') !== false || strpos($lNameLower, 'alemanha') !== false || strpos($lNameLower, 'germany') !== false) {
            return ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true];
        }
        if (strpos($lNameLower, 'frança') !== false || strpos($lNameLower, 'france') !== false || strpos($lNameLower, 'ligue 1') !== false || strpos($lNameLower, 'ligue 2') !== false) {
            return ['country' => 'França', 'flag' => '🇫🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'eredivisie') !== false || strpos($lNameLower, 'holanda') !== false || strpos($lNameLower, 'eerste divisie') !== false) {
            return ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true];
        }
        if (strpos($lNameLower, 'argentina') !== false || strpos($lNameLower, 'liga profesional') !== false || strpos($lNameLower, 'primera nacional') !== false) {
            return ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'mls') !== false || strpos($lNameLower, 'major league') !== false || strpos($lNameLower, 'usa') !== false) {
            return ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true];
        }
        if (strpos($lNameLower, 'méxico') !== false || strpos($lNameLower, 'mexico') !== false || strpos($lNameLower, 'liga mx') !== false) {
            return ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true];
        }
        if (strpos($lNameLower, 'allsvenskan') !== false || strpos($lNameLower, 'suécia') !== false || strpos($lNameLower, 'superettan') !== false) {
            return ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => true];
        }
        if (strpos($lNameLower, 'eliteserien') !== false || strpos($lNameLower, 'noruega') !== false) {
            return ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => true];
        }
        if (strpos($lNameLower, 'veikkausliiga') !== false || strpos($lNameLower, 'finlândia') !== false) {
            return ['country' => 'Finlândia', 'flag' => '🇫🇮', 'popular' => false];
        }
        if (strpos($lNameLower, 'ekstraklasa') !== false || strpos($lNameLower, 'polônia') !== false) {
            return ['country' => 'Polônia', 'flag' => '🇵🇱', 'popular' => false];
        }
        if (strpos($lNameLower, 'superliga') !== false || strpos($lNameLower, 'dinamarca') !== false) {
            return ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false];
        }
        if (strpos($lNameLower, 'jupiler') !== false || strpos($lNameLower, 'bélgica') !== false) {
            return ['country' => 'Bélgica', 'flag' => '🇧🇪', 'popular' => false];
        }
        if (strpos($lNameLower, 'japan') !== false || strpos($lNameLower, 'j1') !== false || strpos($lNameLower, 'japão') !== false) {
            return ['country' => 'Japão', 'flag' => '🇯🇵', 'popular' => false];
        }
        if (strpos($lNameLower, 'k league') !== false || strpos($lNameLower, 'coreia') !== false) {
            return ['country' => 'Coreia do Sul', 'flag' => '🇰🇷', 'popular' => false];
        }
        if (strpos($lNameLower, 'pro league') !== false || strpos($lNameLower, 'saudi') !== false || strpos($lNameLower, 'arábia') !== false) {
            return ['country' => 'Arábia Saudita', 'flag' => '🇸🇦', 'popular' => false];
        }
        if (strpos($lNameLower, 'süper lig') !== false || strpos($lNameLower, 'turquia') !== false) {
            return ['country' => 'Turquia', 'flag' => '🇹🇷', 'popular' => false];
        }
        if (strpos($lNameLower, 'chile') !== false) {
            return ['country' => 'Chile', 'flag' => '🇨🇱', 'popular' => false];
        }
        if (strpos($lNameLower, 'uruguai') !== false || strpos($lNameLower, 'uruguay') !== false) {
            return ['country' => 'Uruguai', 'flag' => '🇺🇾', 'popular' => false];
        }
        if (strpos($lNameLower, 'ecuador') !== false || strpos($lNameLower, 'equador') !== false) {
            return ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false];
        }
        if (strpos($lNameLower, 'colômbia') !== false || strpos($lNameLower, 'colombia') !== false) {
            return ['country' => 'Colômbia', 'flag' => '🇨🇴', 'popular' => false];
        }
        if (strpos($lNameLower, 'peru') !== false) {
            return ['country' => 'Peru', 'flag' => '🇵🇪', 'popular' => false];
        }
        if (strpos($lNameLower, 'paraguay') !== false || strpos($lNameLower, 'paraguai') !== false) {
            return ['country' => 'Paraguai', 'flag' => '🇵🇾', 'popular' => false];
        }
        if (strpos($lNameLower, 'romênia') !== false || strpos($lNameLower, 'romenia') !== false || strpos($lNameLower, 'liga i') !== false) {
            return ['country' => 'Romênia', 'flag' => '🇷🇴', 'popular' => false];
        }
        if (strpos($lNameLower, 'sérvia') !== false || strpos($lNameLower, 'servia') !== false) {
            return ['country' => 'Sérvia', 'flag' => '🇷🇸', 'popular' => false];
        }
        if (strpos($lNameLower, 'china') !== false) {
            return ['country' => 'China', 'flag' => '🇨🇳', 'popular' => false];
        }
        if (strpos($lNameLower, 'suíça') !== false || strpos($lNameLower, 'suica') !== false) {
            return ['country' => 'Suíça', 'flag' => '🇨🇭', 'popular' => false];
        }
        if (strpos($lNameLower, 'áustria') !== false || strpos($lNameLower, 'austria') !== false) {
            return ['country' => 'Áustria', 'flag' => '🇦🇹', 'popular' => false];
        }
        if (strpos($lNameLower, 'grécia') !== false || strpos($lNameLower, 'grecia') !== false) {
            return ['country' => 'Grécia', 'flag' => '🇬🇷', 'popular' => false];
        }
        if (strpos($lNameLower, 'tcheca') !== false || strpos($lNameLower, 'czech') !== false) {
            return ['country' => 'República Tcheca', 'flag' => '🇨🇿', 'popular' => false];
        }

        // Fallback default: INTERNACIONAL se não houver país identificado
        return ['country' => 'INTERNACIONAL', 'flag' => '🌐', 'popular' => false];
    }
}

// Organiza as partidas e ligas por país/região
$groupedLeagues = [];
$popularLeagues = [];

foreach ($fixtures as $fix) {
    $leagueId = (int)($fix->league_id ?? 0);
    $rawLeagueName = $fix->league_name ?? '';
    
    $displayName = formatLeagueDisplayName($leagueId, $rawLeagueName);
    $fix->display_league_name = $displayName;
    
    $leagueInfo = resolveLeagueCountryAndFlag($leagueId, $displayName, $leagueMap);
    $country = $leagueInfo['country'];
    $flag = $leagueInfo['flag'];
    $isPopular = $leagueInfo['popular'];
    
    // Agrupa ligas por país
    if (!isset($groupedLeagues[$country])) {
        $groupedLeagues[$country] = [
            'flag' => $flag,
            'leagues' => []
        ];
    }
    if (!in_array($displayName, $groupedLeagues[$country]['leagues'])) {
        $groupedLeagues[$country]['leagues'][] = $displayName;
    }
    
    // Populares
    if ($isPopular && !in_array($displayName, $popularLeagues)) {
        $popularLeagues[] = $displayName;
    }
}
ksort($groupedLeagues, SORT_NATURAL | SORT_FLAG_CASE); // Ordena países alfabeticamente
foreach ($groupedLeagues as $cName => &$cData) {
    sort($cData['leagues'], SORT_NATURAL | SORT_FLAG_CASE); // Ordena as ligas de cada país alfabeticamente
}
unset($cData);

sort($popularLeagues, SORT_NATURAL | SORT_FLAG_CASE); // Ordena ligas populares alfabeticamente

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

        $u45 = calculate_poisson_php($xc, 4.5)['under'];
        $u55 = calculate_poisson_php($xc, 5.5)['under'];
        $u65 = calculate_poisson_php($xc, 6.5)['under'];
        $u75 = calculate_poisson_php($xc, 7.5)['under'];
        $u85 = calculate_poisson_php($xc, 8.5)['under'];

        if ($isNoBet || $xc > 6.50) {
            return [
                'market'        => lang('App.entry_not_recommended'),
                'line_tag'      => 'NO BET 🚫',
                'badge_bg'      => 'background: rgba(239, 68, 68, 0.25); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5);',
                'box_border'    => '#ef4444',
                'region'        => lang('App.cards_expectation'),
                'region_short'  => lang('App.cards_exp_short') . ': ' . number_format($xc, 2),
                'foul_style'    => lang('App.teams') . ' (' . number_format($combinedAvg, 1) . ' c/j)',
                'foul_short'    => lang('App.teams') . ' (' . number_format($combinedAvg, 1) . ')',
                'referee'       => lang('App.referee') . ' (' . number_format($refAvg, 1) . ' c/j)',
                'referee_short' => lang('App.referee') . ' (' . number_format($refAvg, 1) . ')',
                'rationale'     => sprintf(lang('App.rat_no_bet_gatekeeper'), number_format($xc, 2))
            ];
        }

        if ($xc <= 3.30 && $u45 >= 75.0) {
            $lineTag = 'UNDER 4.5 🛡️';
            $ratStr = sprintf(lang('App.rat_approved_margin'), number_format($xc, 2), '4.5', $u45, '5.5', $u55);
        } elseif ($xc <= 4.20 && $u55 >= 60.0) {
            $lineTag = 'UNDER 5.5 🛡️';
            $ratStr = sprintf(lang('App.rat_approved_margin'), number_format($xc, 2), '5.5', $u55, '6.5', $u65);
        } elseif ($xc <= 5.80 && $u65 >= 60.0) {
            $lineTag = 'UNDER 6.5 🛡️';
            $ratStr = sprintf(lang('App.rat_approved_margin'), number_format($xc, 2), '6.5', $u65, '7.5', $u75);
        } elseif ($xc <= 6.50 && $u75 >= 60.0) {
            $lineTag = 'UNDER 7.5 🛡️';
            $ratStr = sprintf(lang('App.rat_approved_margin'), number_format($xc, 2), '7.5', $u75, '8.5', $u85);
        } else {
            return [
                'market'        => lang('App.entry_not_recommended'),
                'line_tag'      => 'NO BET 🚫',
                'badge_bg'      => 'background: rgba(239, 68, 68, 0.25); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.5);',
                'box_border'    => '#ef4444',
                'region'        => lang('App.cards_expectation'),
                'region_short'  => lang('App.cards_exp_short') . ': ' . number_format($xc, 2),
                'foul_style'    => lang('App.teams') . ' (' . number_format($combinedAvg, 1) . ' c/j)',
                'foul_short'    => lang('App.teams') . ' (' . number_format($combinedAvg, 1) . ')',
                'referee'       => lang('App.referee') . ' (' . number_format($refAvg, 1) . ' c/j)',
                'referee_short' => lang('App.referee') . ' (' . number_format($refAvg, 1) . ')',
                'rationale'     => sprintf(lang('App.rat_no_bet_margin'), number_format($xc, 2))
            ];
        }

        return [
            'market'        => lang('App.under_cards'),
            'line_tag'      => $lineTag,
            'badge_bg'      => 'background: rgba(16, 185, 129, 0.25); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.5);',
            'box_border'    => '#10b981',
            'region'        => lang('App.cards_expectation'),
            'region_short'  => lang('App.cards_exp_short') . ': ' . number_format($xc, 2),
            'foul_style'    => lang('App.teams') . ' (' . number_format($combinedAvg, 1) . ' c/j)',
            'foul_short'    => lang('App.teams') . ' (' . number_format($combinedAvg, 1) . ')',
            'referee'       => lang('App.referee') . ' (' . number_format($refAvg, 1) . ' c/j)',
            'referee_short' => lang('App.referee') . ' (' . number_format($refAvg, 1) . ')',
            'rationale'     => $ratStr
        ];
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
            </div>
        </div>

        <!-- Seção de Vídeo em Destaque / Tutorial -->
        <section class="bet-video-section mb-4 p-3 p-md-4 rounded" style="background: linear-gradient(135deg, rgba(23, 34, 48, 0.9) 0%, rgba(15, 23, 36, 0.95) 100%); border: 1px solid rgba(244, 124, 32, 0.35); box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 class="mb-0 text-white font-weight-bold d-flex align-items-center gap-2" style="font-size: 1.15rem;">
                    <i class="bi bi-play-circle-fill" style="color: #f47c20; font-size: 1.3rem;"></i> 
                    <?= lang('App.demo_video_title') ?>
                </h2>
                <a href="https://youtu.be/_Hhg3B1MldQ" target="_blank" rel="noopener noreferrer" class="btn btn-sm text-white font-weight-bold d-inline-flex align-items-center gap-1" style="background: #ff0000; border-radius: 8px; padding: 6px 14px; font-size: 0.88rem; text-decoration: none;">
                    <i class="bi bi-youtube"></i> <?= lang('App.watch_on_youtube') ?> <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                </a>
            </div>
            <div style="max-width: 33.333%; min-width: 280px; margin: 0 auto;">
                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <iframe src="https://www.youtube.com/embed/_Hhg3B1MldQ" 
                            title="<?= lang('App.demo_video_title') ?>" 
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
                    <i class="bi bi-info-circle"></i> <?= lang('App.youtube_age_disclaimer') ?>
                </small>
            </div>
        </section>

        <!-- Bloco SEO Server-Side Rendered (SSR) com data e hora atual -->
        <section class="bet-seo-header mb-4 p-3 rounded" style="background: rgba(23, 34, 48, 0.6); border: 1px solid rgba(255, 255, 255, 0.05);">
            <h1 style="font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>⚽ <?= lang('App.football_trends_heading') ?></span>
                <?php 
                  $dtNowBrt = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                  $nowFormatted = $dtNowBrt->format('d/m/Y H:i');
                ?>
                <span class="badge" style="background: rgba(0, 230, 118, 0.15); border: 1px solid rgba(0, 230, 118, 0.3); color: #00e676; font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;" title="Data e hora atual no fuso horário de Brasília (America/Sao_Paulo)">
                    <i class="bi bi-clock-history" style="color: #00e676;"></i> <?= $nowFormatted ?>
                </span>
            </h1>
            <p class="mb-0" style="font-size: 0.92rem; line-height: 1.6; color: #ffffff;">
                <?= sprintf(lang('App.seo_description'), $formattedDateHeader) ?>
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
                                <!-- Botão de Atualização Manual de Jogos e Odds posicionado acima de '< Ontem' (Oculto) -->
                                <div class="mb-2" style="display: none !important;">
                                    <button type="button" class="btn-update-betano d-inline-flex align-items-center gap-2" onclick="triggerIngestion('<?= $targetDate ?>')" style="padding: 7px 16px; font-size: 0.85rem; font-weight: 700; border-radius: 8px; box-shadow: 0 4px 12px rgba(244, 124, 32, 0.25);">
                                        <i class="bi bi-arrow-repeat" style="font-size: 1rem;"></i> <?= lang('App.update_games_odds') ?>
                                    </button>
                                </div>

                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <?php
                                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                                    $today = date('Y-m-d');
                                    $tomorrow = date('Y-m-d', strtotime('+1 day'));
                                    $next3days = date('Y-m-d', strtotime('+3 days'));
                                    $next7days = date('Y-m-d', strtotime('+7 days'));
                                    
                                    $showFinishedQuery = '&show_finished=' . ($showFinished ? '1' : '0');
                                    $showPostponedQuery = '&show_postponed=' . (!empty($showPostponed) ? '1' : '0');
                                    $onlyLiveQuery = !empty($onlyLive) ? '&only_live=1' : '';
                                    $onlyResenhaQuery = !empty($onlyResenha) ? '&only_resenha=1' : '';
                                    $searchQuery = !empty($search) ? '&search=' . urlencode($search) : '';
                                    $commonParams = $showFinishedQuery . $showPostponedQuery . $onlyLiveQuery . $onlyResenhaQuery . $searchQuery;
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
                                        ⚡ <?= lang('App.next_3_days') ?>
                                    </a>
                                    <a href="?start_date=<?= $today ?>&end_date=<?= $next7days ?><?= $commonParams ?>" class="bet-date-btn <?= ($startDate === $today && $endDate === $next7days) ? 'active' : '' ?>" style="border-color: rgba(0, 230, 118, 0.4); color: #00e676;">
                                        🚀 <?= lang('App.next_7_days') ?>
                                    </a>
                                    <div class="d-flex align-items-center gap-1" style="background: rgba(255, 255, 255, 0.04); padding: 4px 8px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.08);">
                                        <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;"><?= lang('App.from_date') ?>:</span>
                                        <input type="date" name="start_date" class="bet-date-input" value="<?= $startDate ?>" onchange="document.getElementById('filterForm').submit()">
                                        <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; margin-left: 4px;"><?= lang('App.to_date') ?>:</span>
                                        <input type="date" name="end_date" class="bet-date-input" value="<?= $endDate ?>" onchange="document.getElementById('filterForm').submit()">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Toggle switches column -->
                            <div class="col-xl-6 col-lg-5 col-md-12 d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #aeb9c4; font-weight: 600;"><?= lang('App.show_finished_games') ?></span>
                                    <label class="bet-switch">
                                        <input type="hidden" name="show_finished" value="0">
                                        <input type="checkbox" id="showFinishedToggle" name="show_finished" value="1" <?= $showFinished ? 'checked' : '' ?> onchange="toggleShowFinishedFilter(this)">
                                        <span class="bet-slider round"></span>
                                    </label>
                                    <span id="showFinishedToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= $showFinished ? '#f47c20' : '#8a99a8' ?>;">
                                        <?= $showFinished ? lang('App.yes') : lang('App.no') ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #aeb9c4; font-weight: 600;"><?= lang('App.show_postponed') ?></span>
                                    <label class="bet-switch">
                                        <input type="hidden" name="show_postponed" value="0">
                                        <input type="checkbox" id="showPostponedToggle" name="show_postponed" value="1" <?= !empty($showPostponed) ? 'checked' : '' ?> onchange="toggleShowPostponedFilter(this)">
                                        <span class="bet-slider round"></span>
                                    </label>
                                    <span id="showPostponedToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($showPostponed) ? '#f59e0b' : '#8a99a8' ?>;">
                                        <?= !empty($showPostponed) ? lang('App.yes') : lang('App.no') ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.25);">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #34d399; font-weight: 600;">
                                        <i class="bi bi-shield-fill-check"></i> <?= lang('App.safe_bets') ?>
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlySafeToggle" name="only_safe" value="1" <?= !empty($onlySafe) ? 'checked' : '' ?> onchange="toggleSafeBetsFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlySafeToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($onlySafe) ? '#10b981' : '#8a99a8' ?>;">
                                        <?= !empty($onlySafe) ? lang('App.yes') : lang('App.no') ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(0, 230, 118, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(0, 230, 118, 0.3);">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #00e676; font-weight: 600;">
                                        ⚡ <?= lang('App.surebets') ?>
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlySurebetToggle" name="only_surebet" value="1" <?= !empty($onlySurebet) ? 'checked' : '' ?> onchange="toggleSurebetsFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlySurebetToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($onlySurebet) ? '#00e676' : '#8a99a8' ?>;">
                                        <?= !empty($onlySurebet) ? lang('App.yes') : lang('App.no') ?>
                                    </span>
                                </div>
                                 <div class="d-flex align-items-center gap-2" style="background: rgba(192, 132, 252, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(192, 132, 252, 0.3);" title="Exibir apenas partidas que possuem apostas cadastradas">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #c084fc; font-weight: 600;">
                                        🃏 <?= lang('App.with_bets') ?>
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlyHasBetToggle" name="only_has_bet" value="1" onchange="toggleHasBetFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlyHasBetToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: #8a99a8;">
                                        <?= lang('App.no') ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(0, 230, 118, 0.08); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(0, 230, 118, 0.25);" title="Exibir apenas partidas que possuem resenha e análise editorial">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #00e676; font-weight: 600;">
                                        <i class="bi bi-chat-quote-fill"></i> <?= lang('App.with_review') ?>
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlyResenhaToggle" name="only_resenha" value="1" <?= !empty($onlyResenha) ? 'checked' : '' ?> onchange="toggleResenhaFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlyResenhaToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($onlyResenha) ? '#00e676' : '#8a99a8' ?>;">
                                        <?= !empty($onlyResenha) ? lang('App.yes') : lang('App.no') ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="background: rgba(239, 68, 68, 0.1); padding: 4px 10px; border-radius: 20px; border: 1px solid rgba(239, 68, 68, 0.3);" title="Exibir apenas partidas em andamento (Ao Vivo)">
                                    <span class="bet-toggle-label" style="font-size: 0.85rem; color: #f87171; font-weight: 600;">
                                        🔴 <?= lang('App.live_matches') ?>
                                    </span>
                                    <label class="bet-switch">
                                        <input type="checkbox" id="onlyLiveToggle" name="only_live" value="1" <?= !empty($onlyLive) ? 'checked' : '' ?> onchange="toggleLiveBetsFilter(this)">
                                        <span class="bet-slider round" style="background-color: #1e293b;"></span>
                                    </label>
                                    <span id="onlyLiveToggleStatus" class="bet-toggle-status" style="font-size: 0.85rem; font-weight: 700; color: <?= !empty($onlyLive) ? '#ef4444' : '#8a99a8' ?>;">
                                        <?= !empty($onlyLive) ? lang('App.yes') : lang('App.no') ?>
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

                <!-- Abas estilo Betano: Destaques vs Todas as Partidas + Contador Recalculado de Jogos -->
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="bet-tabs m-0">
                        <div class="bet-tab active" id="tab-competicoes" onclick="switchMainTab('competicoes')"><?= lang('App.competitions') ?></div>
                        <div class="bet-tab" id="tab-destaques" onclick="switchMainTab('destaques')"><?= lang('App.highlights') ?></div>
                    </div>

                    <div class="bet-total-matches-badge d-flex align-items-center gap-2" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 12px; padding: 7px 16px; backdrop-filter: blur(8px); box-shadow: 0 4px 12px rgba(0,0,0,0.2);" title="<?= lang('App.tooltip_total_games_period') ?>">
                        <i class="bi bi-controller" style="color: #f47c20; font-size: 1.1rem;"></i>
                        <span style="font-size: 0.85rem; color: #94a3b8; font-weight: 600;"><?= lang('App.total_games') ?>:</span>
                        <strong id="totalMatchesCount" style="color: #00e676; font-weight: 800; font-size: 1rem; min-width: 30px; text-align: right;">
                            <?= count($fixtures) ?>
                        </strong>
                    </div>
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
                        <?php $cardIndex = 0; ?>
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
                            $u75 = calculate_poisson_php($xc, 7.5)['under'];

                            if ($isFinished && $totalLiveCards <= 5 && $xc <= 6.50) {
                                $prob = 100.0;
                                $probDisplay = '100% (' . lang('App.won_green') . ' 🟢)';
                                $class = 'safe';
                            } elseif ($isNoBetFix || $xc > 6.50) {
                                $prob = 0.0;
                                $probDisplay = 'NO BET (' . lang('App.risk_no_bet') . ' 🚫)';
                                $class = 'nobet';
                            } elseif ($xc <= 3.30 && $u45 >= 75.0) {
                                $prob = $u45;
                                $probDisplay = 'Under 4.5: ' . number_format($prob, 2) . '%';
                                $class = 'safe';
                            } elseif ($xc <= 4.20 && $u55 >= 60.0) {
                                $prob = $u55;
                                $probDisplay = 'Under 5.5: ' . number_format($prob, 2) . '%';
                                $class = 'safe';
                            } elseif ($xc <= 5.80 && $u65 >= 60.0) {
                                $prob = $u65;
                                $probDisplay = 'Under 6.5: ' . number_format($prob, 2) . '%';
                                $class = 'safe';
                            } elseif ($xc <= 6.50 && $u75 >= 60.0) {
                                $prob = $u75;
                                $probDisplay = 'Under 7.5: ' . number_format($prob, 2) . '%';
                                $class = 'moderate';
                            } else {
                                $prob = 0.0;
                                $probDisplay = 'NO BET (' . lang('App.risk_no_bet') . ' 🚫)';
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
                                
                                $finishedStatuses = ['FT', 'AET', 'PEN', '120', '90', 'FINISHED', 'MATCH FINISHED', 'FULL TIME', 'FIN', 'FINAL', 'FT_PEN'];
                                $statusClean = strtoupper($fix->status);
                                
                                if (in_array($statusClean, ['PST', 'POSTPONED', 'CANCELLED'])) {
                                    $elapsedText = 'ADIADO';
                                    $elapsedClass = 'pst';
                                } elseif (in_array($statusClean, $finishedStatuses) || $diffMins > 115) {
                                    $elapsedText = lang('App.finished');
                                    $elapsedClass = '';
                                } elseif ($statusClean === 'HT') {
                                    if ($diffMins >= 65) {
                                        $elapsedText = $diffMins . "'";
                                        $elapsedClass = 'live';
                                    } else {
                                        $elapsedText = lang('App.halftime');
                                        $elapsedClass = 'live';
                                    }
                                } elseif ($diffMins < 0) {
                                    $elapsedText = lang('App.not_started');
                                } else {
                                    $elapsedText = $diffMins . "'";
                                    $elapsedClass = 'live';
                                }
                            } catch (\Exception $e) {
                                $elapsedText = '-';
                            }
                            ?>
                            <?php
                            $requiresCredits = \App\Helpers\SubscriptionHelper::leagueRequiresCredits($fix->league_name);
                            $isCardLocked = $requiresCredits && (!$userLoggedIn || !$isGoogleUser || $userGrokCredits <= 0);
                            
                            // Sempre mantém o primeiro card desbloqueado para degustação (visitantes / sem tokens)
                            if ($cardIndex === 0) {
                                $isCardLocked = false;
                            }
                            $cardIndex++;
                            
                            $isLiveMatch = in_array(strtoupper($fix->status ?? ''), ['1H', '2H', 'HT', 'ET', 'P', 'LIVE', 'BT']) && (!isset($diffMins) || $diffMins <= 115);
                            $statusClean = strtoupper($fix->status ?? '');
                            $finishedStatusesList = ['FT', 'AET', 'PEN', '120', '90', 'FINISHED', 'MATCH FINISHED', 'FULL TIME', 'FIN', 'FINAL', 'FT_PEN'];

                            if (in_array($statusClean, ['PST', 'POSTPONED', 'CANCELLED'])) {
                                $elapsedClass = 'pst';
                                $elapsedDisplay = '⚠️ ' . lang('App.postponed');
                            } elseif (in_array($statusClean, $finishedStatusesList) || (isset($diffMins) && $diffMins > 115)) {
                                $elapsedClass = '';
                                $elapsedDisplay = lang('App.finished');
                            } elseif ($isLiveMatch) {
                                $elapsedClass = 'live';
                                if ($statusClean === 'HT') {
                                    $minDisplay = (isset($diffMins) && $diffMins >= 65) ? $diffMins . "'" : lang('App.halftime');
                                } else {
                                    $minDisplay = !empty($fix->elapsed) ? $fix->elapsed . "'" : (isset($diffMins) && $diffMins >= 0 ? $diffMins . "'" : lang('App.live'));
                                }
                                $elapsedDisplay = '<span class="live-pulse-dot"></span> ' . $minDisplay;
                            } elseif ($statusClean === 'NS') {
                                $elapsedClass = '';
                                $elapsedDisplay = lang('App.pre_match');
                            } else {
                                $elapsedClass = (isset($diffMins) && $diffMins >= 0) ? 'live' : '';
                                $elapsedDisplay = (isset($diffMins) && $diffMins >= 0) ? $diffMins . "'" : lang('App.pre_match');
                            }

                            $betanoTimeText = lang('App.pre_match');
                            if (in_array($statusClean, ['PST', 'POSTPONED', 'CANCELLED', 'CANC'])) {
                                $betanoTimeText = '⚠️ ' . lang('App.postponed');
                            } elseif (in_array($statusClean, $finishedStatusesList) || (isset($diffMins) && $diffMins > 115)) {
                                $betanoTimeText = lang('App.finished');
                            } elseif ($isLiveMatch) {
                                if ($statusClean === 'HT') {
                                    $betanoTimeText = (isset($diffMins) && $diffMins >= 65) ? $diffMins . "'" : lang('App.halftime');
                                } else {
                                    $betanoTimeText = !empty($fix->elapsed) ? $fix->elapsed . "'" : (isset($diffMins) && $diffMins >= 0 ? $diffMins . "'" : lang('App.live'));
                                }
                            } elseif ($statusClean === 'NS') {
                                $betanoTimeText = lang('App.pre_match');
                            } else {
                                $betanoTimeText = (isset($diffMins) && $diffMins >= 0) ? $diffMins . "'" : lang('App.pre_match');
                            }
                            
                            $displayLeague = $fix->display_league_name ?? formatLeagueDisplayName($fix->league_id ?? 0, $fix->league_name ?? '');
                            $leagueInfo = resolveLeagueCountryAndFlag($fix->league_id ?? 0, $displayLeague, $leagueMap ?? []);
                            $cName = $leagueInfo['country'];
                            $cFlag = $leagueInfo['flag'];
                            ?>
                             <?php
                             $isFixtureInUserBets = in_array((int)$fix->fixture_id, $userBetFixtureIds ?? []);
                             $isFixtureInAnyBets  = in_array((int)$fix->fixture_id, $allBetFixtureIds ?? []);
                             $hasAposta = $isFixtureInUserBets || $isFixtureInAnyBets;

                             $isPostponedCard = in_array($statusClean, ['PST', 'CANCELLED', 'POSTPONED', 'CANC']);
                             $isFinishedCard = in_array($statusClean, $finishedStatusesList) || ($fix->goals_home !== null && !$isLiveMatch && !$isPostponedCard) || (isset($diffMins) && $diffMins > 115 && !$isPostponedCard);
                             ?>
                             <div class="bet-card" id="card-<?= $fix->fixture_id ?>" data-fixture-id="<?= $fix->fixture_id ?>" data-league="<?= htmlspecialchars($displayLeague, ENT_QUOTES) ?>" data-prob="<?= $prob ?>" data-is-safe="<?= (($class === 'safe' || $class === 'high') && strpos($fix->prediction_text ?? '', 'NO_BET') === false) ? '1' : '0' ?>" data-is-surebet="<?= !empty($fix->is_surebet) ? '1' : '0' ?>" data-has-aposta="<?= $hasAposta ? '1' : '0' ?>" data-is-live="<?= $isLiveMatch ? '1' : '0' ?>" data-is-finished="<?= $isFinishedCard ? '1' : '0' ?>" data-is-postponed="<?= $isPostponedCard ? '1' : '0' ?>" data-has-resenha="<?= (!empty($fix->futbol24_tip) || !empty($fix->futbol24_analysis)) ? '1' : '0' ?>" data-home-team="<?= htmlspecialchars($fix->home_team ?? '', ENT_QUOTES) ?>" data-away-team="<?= htmlspecialchars($fix->away_team ?? '', ENT_QUOTES) ?>" data-teams="<?= htmlspecialchars($cName . ' ' . ($fix->home_team ?? '') . ' ' . ($fix->away_team ?? '') . ' ' . $displayLeague . ' ' . ($fix->referee_name ?? '') . ' ' . ($fix->prediction_text ?? '') . ' ' . ($fix->ah_suggestion ?? ''), ENT_QUOTES) ?>" style="position: relative;">
                                <div class="<?= $isCardLocked ? 'bet-card-locked' : '' ?>" style="display: flex; flex-direction: column; height: 100%; justify-content: space-between;">
                                    <div>
                                    <!-- Header -->
                                    <div class="bet-card-header">
                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; max-width: 70%;">
                                            <span class="bet-league-badge" title="<?= htmlspecialchars((!empty($cName) ? $cName . ' - ' : '') . $displayLeague) ?>">
                                                <?= !empty($cFlag) ? $cFlag . ' ' : '' ?><?= !empty($cName) ? htmlspecialchars($cName) . ' • ' : '' ?><?= htmlspecialchars($displayLeague) ?>
                                            </span>
                                            <?php if ($hasAposta): ?>
                                                <a href="<?= base_url('apostas?action=edit&fixture_id=' . $fix->fixture_id) ?>" 
                                                   class="bet-card-playing-card-badge has-bet" 
                                                   title="<?= $isFixtureInUserBets ? lang('App.click_to_edit') : lang('App.has_bet') ?>">
                                                    <span class="playing-card-symbol">🂠</span>
                                                    <span><?= $isFixtureInUserBets ? lang('App.your_bet') : lang('App.has_bet') ?></span>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= base_url('apostas?new_bet=1&fixture_id=' . $fix->fixture_id) ?>" 
                                                   class="bet-card-playing-card-badge no-bet" 
                                                   title="<?= lang('App.click_to_add') ?>">
                                                    <span class="playing-card-symbol" style="opacity: 0.5;">🂠</span>
                                                    <span><?= lang('App.no_bet') ?></span>
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
                                             <span class="bet-elapsed-time <?= $elapsedClass ?>" data-fixture-elapsed="<?= $fix->fixture_id ?>" data-start-utc="<?= $fix->fixture_date ?>" data-status="<?= $statusClean ?>" data-elapsed="<?= htmlspecialchars($fix->elapsed ?? '', ENT_QUOTES) ?>">
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
                                                 <span data-betano-time="<?= $fix->fixture_id ?>"><?= $betanoTimeText ?></span>
                                             </div>

                                             <!-- Nomes dos Times e Placar -->
                                             <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; font-weight: 700; font-size: 0.95rem; margin-bottom: 6px;">
                                                 <div style="flex: 1; text-align: right; overflow: hidden; display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                                     <i class="bi bi-house-door-fill" style="color: #38bdf8; font-size: 0.85rem; flex-shrink: 0;" title="<?= lang('App.home_team_tooltip') ?>"></i>
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
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="<?= lang('App.yellow_cards') ?>">
                                                     <span style="background: #eab308; width: 10px; height: 13px; display: inline-block; border-radius: 2px;"></span>
                                                     <span data-betano-cards="<?= $fix->fixture_id ?>"><?= ($fix->yellow_cards_home ?? 0) ?>-<?= ($fix->yellow_cards_away ?? 0) ?></span>
                                                 </div>
                                                 <?php $hasRedCards = ((int)($fix->red_cards_home ?? 0) + (int)($fix->red_cards_away ?? 0)) > 0; ?>
                                                 <div style="display: flex; align-items: center; gap: 4px; <?= $hasRedCards ? '' : 'display: none;' ?>" title="<?= lang('App.red_cards') ?>" data-betano-redcards-container="<?= $fix->fixture_id ?>">
                                                     <span style="background: #ef4444; width: 10px; height: 13px; display: inline-block; border-radius: 2px;"></span>
                                                     <span data-betano-redcards="<?= $fix->fixture_id ?>"><?= ($fix->red_cards_home ?? 0) ?>-<?= ($fix->red_cards_away ?? 0) ?></span>
                                                 </div>
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="<?= lang('App.corners') ?>">
                                                     <span style="font-size: 0.85rem;">🚩</span>
                                                     <span data-betano-corners="<?= $fix->fixture_id ?>"><?= ($fix->corners_home ?? 0) ?>-<?= ($fix->corners_away ?? 0) ?></span>
                                                 </div>
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="<?= lang('App.total_shots') ?>">
                                                     <span style="font-size: 0.85rem;">👟</span>
                                                     <span data-betano-shots="<?= $fix->fixture_id ?>"><?= ($fix->shots_home ?? 0) ?>-<?= ($fix->shots_away ?? 0) ?></span>
                                                 </div>
                                                 <div style="display: flex; align-items: center; gap: 4px;" title="<?= lang('App.expected_goals') ?>">
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
                                                <span class="bet-team-name"><?= htmlspecialchars($fix->home_team) ?> <i class="bi bi-house-door-fill" style="color: #38bdf8; font-size: 0.8rem; margin-left: 4px;" title="<?= lang('App.home_team_tooltip') ?>"></i><?php if (!empty($fix->home_rank)): ?><span class="badge" style="font-size: 0.68rem; background-color: #1e293b; color: #38bdf8; border: 1px solid #334155; margin-left: 6px;" title="<?= sprintf(lang('App.table_rank_tooltip'), $fix->home_rank, $fix->home_ppg ?? 0) ?>">#<?= $fix->home_rank ?></span><?php endif; ?></span>
                                                <div class="bet-card-badge-container" data-cards-container-home="<?= $fix->fixture_id ?>">
                                                    <?php if (isset($fix->yellow_cards_home) && $fix->yellow_cards_home !== null && $fix->yellow_cards_home > 0): ?>
                                                        <span class="bet-card-badge-item yellow" title="<?= lang('App.yellow_cards') ?>"><i class="bi bi-file-square-fill"></i> <?= $fix->yellow_cards_home ?></span>
                                                    <?php endif; ?>
                                                    <?php if (isset($fix->red_cards_home) && $fix->red_cards_home !== null && $fix->red_cards_home > 0): ?>
                                                        <span class="bet-card-badge-item red" title="<?= lang('App.red_cards') ?>"><i class="bi bi-file-square-fill"></i> <?= $fix->red_cards_home ?></span>
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
                                                        <span class="val"><?= (isset($fix->home_clean_sheets_pct) && $fix->home_clean_sheets_pct !== null && $fix->home_clean_sheets_pct !== '') ? round($fix->home_clean_sheets_pct) . '%' : lang('App.not_found') ?></span>
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
                                                <span class="bet-team-name"><?= htmlspecialchars($fix->away_team) ?><?php if (!empty($fix->away_rank)): ?><span class="badge" style="font-size: 0.68rem; background-color: #1e293b; color: #38bdf8; border: 1px solid #334155; margin-left: 6px;" title="<?= sprintf(lang('App.table_rank_tooltip'), $fix->away_rank, $fix->away_ppg ?? 0) ?>">#<?= $fix->away_rank ?></span><?php endif; ?></span>
                                                <div class="bet-card-badge-container" data-cards-container-away="<?= $fix->fixture_id ?>">
                                                    <?php if (isset($fix->yellow_cards_away) && $fix->yellow_cards_away !== null && $fix->yellow_cards_away > 0): ?>
                                                        <span class="bet-card-badge-item yellow" title="<?= lang('App.yellow_cards') ?>"><i class="bi bi-file-square-fill"></i> <?= $fix->yellow_cards_away ?></span>
                                                    <?php endif; ?>
                                                    <?php if (isset($fix->red_cards_away) && $fix->red_cards_away !== null && $fix->red_cards_away > 0): ?>
                                                        <span class="bet-card-badge-item red" title="<?= lang('App.red_cards') ?>"><i class="bi bi-file-square-fill"></i> <?= $fix->red_cards_away ?></span>
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
                                                        <span class="val"><?= (isset($fix->away_clean_sheets_pct) && $fix->away_clean_sheets_pct !== null && $fix->away_clean_sheets_pct !== '') ? round($fix->away_clean_sheets_pct) . '%' : lang('App.not_found') ?></span>
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
                                                     <i class="bi bi-graph-up-arrow" style="color: #00e676;"></i> <?= lang('App.odds_1x2') ?> (<?= $oddSourceLabel ?>)
                                                 </span>
                                                 <div style="display: flex; align-items: center; gap: 8px;">
                                                     <span style="font-size: 0.70rem; color: #38bdf8; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.2); padding: 2px 7px; border-radius: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;" title="<?= lang('App.odds_updated_tooltip') ?>">
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
                                                 <a href="<?= $urlHome ?>" target="_blank" rel="noopener noreferrer" class="oddspedia-link-box" style="text-decoration: none; display: block; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px; padding: 6px 4px; transition: all 0.2s ease;" title="<?= sprintf(lang('App.bet_on_home_new_tab'), htmlspecialchars($fix->casa_odd_home ?? lang('App.odds_home'))) ?>">
                                                     <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                                         <span><?= lang('App.odds_home') ?> (<?= htmlspecialchars($fix->casa_odd_home ?? '1') ?>)</span>
                                                         <i class="bi bi-box-arrow-up-right" style="font-size: 0.6rem; color: #38bdf8;"></i>
                                                     </div>
                                                     <div style="font-size: 0.95rem; font-weight: 800; color: #38bdf8;">
                                                         <?= number_format($fix->odd_home, 2) ?>
                                                     </div>
                                                 </a>
                                                 <!-- Empate X -->
                                                 <a href="<?= $urlDraw ?>" target="_blank" rel="noopener noreferrer" class="oddspedia-link-box" style="text-decoration: none; display: block; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px; padding: 6px 4px; transition: all 0.2s ease;" title="<?= sprintf(lang('App.bet_on_draw_new_tab'), htmlspecialchars($fix->casa_odd_draw ?? lang('App.odds_draw'))) ?>">
                                                     <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                                         <span><?= lang('App.odds_draw') ?> (<?= htmlspecialchars($fix->casa_odd_draw ?? 'X') ?>)</span>
                                                         <i class="bi bi-box-arrow-up-right" style="font-size: 0.6rem; color: #facc15;"></i>
                                                     </div>
                                                     <div style="font-size: 0.95rem; font-weight: 800; color: #facc15;">
                                                         <?= number_format($fix->odd_draw, 2) ?>
                                                     </div>
                                                 </a>
                                                 <!-- Fora 2 -->
                                         <a href="<?= $urlAway ?>" target="_blank" rel="noopener noreferrer" class="oddspedia-link-box" style="text-decoration: none; display: block; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px; padding: 6px 4px; transition: all 0.2s ease;" title="<?= sprintf(lang('App.bet_on_away_new_tab'), htmlspecialchars($fix->casa_odd_away ?? lang('App.odds_away'))) ?>">
                                                     <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 600; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: flex; align-items: center; justify-content: center; gap: 3px;">
                                                         <span><?= lang('App.odds_away') ?> (<?= htmlspecialchars($fix->casa_odd_away ?? '2') ?>)</span>
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

                                        if (!empty($raw_reasoning)) {
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
                                                if (strpos($raw_reasoning, '|| U5J_DATA:') !== false) {
                                                    $u_parts = explode('|| U5J_DATA:', $raw_reasoning);
                                                    $u5j_data = json_decode(trim($u_parts[1]), true);
                                                }
                                            }
                                        }

                                        if (!empty($motivation) && strpos($motivation, 'Fator Crucial') === false) {
                                            $motivation = "🎯 " . lang('App.crucial_factor') . ": " . $motivation;
                                        }

                                        if (!empty($fix->ah_suggestion)) {
                                            if (empty($nl_explanation)) {
                                                $sugText = $fix->ah_suggestion;
                                                $homeTeam = $fix->home_team;
                                                $awayTeam = $fix->away_team;
                                                if (strpos($sugText, '0.0') !== false || strpos($sugText, 'Empate Anula') !== false || strpos($sugText, '+00') !== false || strpos($sugText, '+ 00') !== false) {
                                                    $teamFav = (strpos(strtolower($sugText), strtolower($awayTeam)) !== false) ? $awayTeam : $homeTeam;
                                                    $teamOpp = ($teamFav === $homeTeam) ? $awayTeam : $homeTeam;
                                                    $nl_explanation = sprintf(lang('App.nl_exp_00'), $teamFav, $teamOpp);
                                                } elseif (strpos($sugText, '-0.25') !== false) {
                                                    $teamFav = (strpos(strtolower($sugText), strtolower($awayTeam)) !== false) ? $awayTeam : $homeTeam;
                                                    $teamOpp = ($teamFav === $homeTeam) ? $awayTeam : $homeTeam;
                                                    $nl_explanation = sprintf(lang('App.nl_exp_minus025'), $teamFav, $teamOpp);
                                                } elseif (strpos($sugText, '+0.25') !== false) {
                                                    $teamFav = (strpos(strtolower($sugText), strtolower($awayTeam)) !== false) ? $awayTeam : $homeTeam;
                                                    $teamOpp = ($teamFav === $homeTeam) ? $awayTeam : $homeTeam;
                                                    $nl_explanation = sprintf(lang('App.nl_exp_plus025'), $teamFav, $teamOpp);
                                                } else {
                                                    $nl_explanation = lang('App.nl_exp_generic');
                                                }
                                            }
                                        }

                                        if (empty($u5j_data)) {
                                            $u5j_data = [
                                                'home' => ['text' => lang('App.not_found'), 'matches' => []],
                                                'away' => ['text' => lang('App.not_found'), 'matches' => []]
                                            ];
                                        }

                                        $ahSugClean = strtolower(trim($fix->ah_suggestion ?? ''));
                                        $isAhBlocked = empty($ahSugClean) 
                                            || stripos($ahSugClean, 'sem entrada') !== false 
                                            || stripos($ahSugClean, 'abstenção') !== false 
                                            || stripos($ahSugClean, 'abstencao') !== false 
                                            || stripos($ahSugClean, 'bloquead') !== false 
                                            || stripos($ahSugClean, 'indisponível') !== false 
                                            || stripos($ahSugClean, 'indisponivel') !== false;

                                        if ($isAhBlocked) {
                                            if (!empty($fix->odd_home) && !empty($fix->odd_draw) && !empty($fix->odd_away)) {
                                                $nl_explanation = sprintf(
                                                    lang('App.ai_abstain_with_odds'),
                                                    htmlspecialchars($fix->home_team),
                                                    htmlspecialchars($fix->away_team),
                                                    lang('App.odds_home'),
                                                    number_format($fix->odd_home, 2),
                                                    lang('App.odds_draw'),
                                                    number_format($fix->odd_draw, 2),
                                                    lang('App.odds_away'),
                                                    number_format($fix->odd_away, 2)
                                                );
                                            } else {
                                                $nl_explanation = sprintf(
                                                    lang('App.ai_abstain_no_odds'),
                                                    htmlspecialchars($fix->home_team),
                                                    htmlspecialchars($fix->away_team)
                                                );
                                            }
                                        }
                                    ?>

                                    <!-- Barra de Badges Interativos para Alternar Seções Retráteis -->
                                    <div class="bet-badge-toggle-bar">
                                        <button type="button" 
                                                id="btn-cards-<?= $fix->fixture_id ?>" 
                                                class="bet-toggle-badge yellow" 
                                                onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'cards')">
                                            <i class="bi bi-card-amber"></i> <?= lang('App.cards') ?> (<?= $prob ?>%) <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                        </button>
                                        <?php 
                                            $ahSugClean = strtolower(trim($fix->ah_suggestion ?? ''));
                                            $isAhBlocked = empty($ahSugClean) 
                                                || stripos($ahSugClean, 'sem entrada') !== false 
                                                || stripos($ahSugClean, 'abstenção') !== false 
                                                || stripos($ahSugClean, 'abstencao') !== false 
                                                || stripos($ahSugClean, 'bloquead') !== false 
                                                || stripos($ahSugClean, 'indisponível') !== false 
                                                || stripos($ahSugClean, 'indisponivel') !== false;
                                        ?>
                                        <?php if ($isAhBlocked): ?>
                                            <button type="button" 
                                                    id="btn-ah-<?= $fix->fixture_id ?>" 
                                                    class="bet-toggle-badge red" 
                                                    style="background: rgba(239, 68, 68, 0.18) !important; border: 1px solid #ef4444 !important; color: #f87171 !important;"
                                                    onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'ah')">
                                                <i class="bi bi-slash-circle-fill me-1"></i> 🚫 <?= lang('App.ah_blocked_ai_abstain') ?> <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                            </button>
                                        <?php elseif (!empty($fix->ah_suggestion)): ?>
                                            <button type="button" 
                                                    id="btn-ah-<?= $fix->fixture_id ?>" 
                                                    class="bet-toggle-badge blue" 
                                                    onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'ah')">
                                                <i class="bi bi-shield-shaded"></i> <?= lang('App.handicap_ah') ?>: <?= htmlspecialchars($fix->ah_suggestion) ?> <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($fix->futbol24_tip) || !empty($fix->futbol24_analysis)): ?>
                                            <button type="button" 
                                                    id="btn-futbol24-<?= $fix->fixture_id ?>" 
                                                    class="bet-toggle-badge green" 
                                                    onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'futbol24')">
                                                <i class="bi bi-chat-quote-fill"></i> <?= lang('App.review') ?> <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" 
                                                id="btn-stats-<?= $fix->fixture_id ?>" 
                                                class="bet-toggle-badge purple" 
                                                onclick="toggleCardSection('<?= $fix->fixture_id ?>', 'stats')">
                                            <i class="bi bi-bar-chart-line-fill"></i> <?= lang('App.detailed_stats') ?> <i class="bi bi-chevron-down ms-1 icon-arrow"></i>
                                        </button>
                                    </div>

                                    <!-- Seção Retrátil 1: Mercado de Cartões & Árbitro -->
                                    <div id="sec-cards-<?= $fix->fixture_id ?>" class="bet-card-section">
                                        <div class="bet-prob-container" style="margin-bottom: 8px;">
                                            <div class="bet-prob-value-row">
                                                <span class="bet-prob-label"><?= lang('App.cards_trend_poisson') ?></span>
                                                <span class="bet-prob-value <?= $class ?>" data-prob-value="<?= $fix->fixture_id ?>"><?= $probDisplay ?></span>
                                            </div>
                                            <div class="bet-progress-track">
                                                <div class="bet-progress-fill <?= $class ?>" data-prob-fill="<?= $fix->fixture_id ?>" style="width: <?= $prob ?>%"></div>
                                            </div>
                                        </div>

                                        <?= formatStructuredPredictionText($fix->prediction_text) ?>

                                        <div class="bet-decision-tree-box" style="padding: 8px 10px; background: rgba(15, 23, 42, 0.85); border-radius: 8px; border-left: 4px solid <?= $decision['box_border'] ?? '#f47c20' ?>; font-size: 0.78rem; color: #cbd5e1;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
                                                <span style="font-weight: 700; color: #f47c20; display: flex; align-items: center; gap: 5px; font-size: 0.8rem;">
                                                    <i class="bi bi-card-amber"></i> <?= lang('App.cards_market_decision_tree') ?>:
                                                </span>
                                                <span class="badge" style="<?= $decision['badge_bg'] ?> font-weight: 700; font-size: 0.74rem; padding: 3px 7px; border-radius: 4px;">
                                                    <?= $decision['line_tag'] ?>
                                                </span>
                                            </div>
                                            
                                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-bottom: 6px; font-size: 0.72rem; text-align: center; background: rgba(30, 41, 59, 0.6); padding: 5px; border-radius: 6px;">
                                                <div style="padding: 2px;">
                                                    <span style="display: block; color: #94a3b8; font-size: 0.67rem;">🌎 <?= lang('App.expectation') ?></span>
                                                    <strong style="color: #e2e8f0; font-size: 0.72rem;"><?= $decision['region_short'] ?></strong>
                                                </div>
                                                <div style="padding: 2px; border-left: 1px solid rgba(255,255,255,0.08); border-right: 1px solid rgba(255,255,255,0.08);">
                                                    <span style="display: block; color: #94a3b8; font-size: 0.67rem;">🟨 <?= lang('App.teams') ?></span>
                                                    <strong style="color: #fbbf24; font-size: 0.72rem;"><?= $decision['foul_short'] ?></strong>
                                                </div>
                                                <div style="padding: 2px;">
                                                    <span style="display: block; color: #94a3b8; font-size: 0.67rem;">⚖️ <?= lang('App.referee') ?></span>
                                                    <strong style="color: #38bdf8; font-size: 0.72rem;"><?= $decision['referee_short'] ?></strong>
                                                </div>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #e2e8f0; line-height: 1.35; background: rgba(30, 41, 59, 0.7); padding: 6px 8px; border-radius: 4px; border: 1px solid rgba(244, 124, 32, 0.2);">
                                                💡 <strong><?= lang('App.suggestion') ?>:</strong> <?= $decision['rationale'] ?>
                                            </div>
                                            <?php if (!empty($fix->prediction_text) && strpos($fix->prediction_text, 'Palpite Por Time:') !== false): ?>
                                                <?php
                                                $teamCardsMatch = [];
                                                preg_match('/Palpite Por Time:\s*(.+)$/i', $fix->prediction_text, $teamCardsMatch);
                                                $teamCardsStr = $teamCardsMatch[1] ?? '';
                                                ?>
                                                <?php if (!empty($teamCardsStr)): ?>
                                                    <div style="margin-top: 6px; font-size: 0.74rem; color: #e2e8f0; line-height: 1.35; background: rgba(15, 23, 42, 0.6); padding: 6px 8px; border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3);">
                                                        🚩 <strong><?= lang('App.cards_by_team') ?>:</strong> <?= htmlspecialchars($teamCardsStr) ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Seção Retrátil 2: Handicap Asiático -->
                                    <?php if ($isAhBlocked): ?>
                                        <div id="sec-ah-<?= $fix->fixture_id ?>" class="bet-card-section">
                                            <div class="asian-handicap-widget-box" style="padding: 12px 14px; background: rgba(239, 68, 68, 0.08); border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.4); border-left: 5px solid #ef4444; font-size: 0.78rem; color: #fca5a5;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                                                    <span style="font-weight: 800; color: #f87171; display: flex; align-items: center; gap: 6px; font-size: 0.86rem; text-transform: uppercase; letter-spacing: 0.3px;">
                                                        <i class="bi bi-shield-x me-1"></i> 🚫 <?= lang('App.bet_blocked_risk_management') ?>
                                                    </span>
                                                    <span class="badge" style="background: rgba(239, 68, 68, 0.25); border: 1px solid #ef4444; color: #fca5a5; font-weight: 700; font-size: 0.76rem; padding: 4px 8px; border-radius: 6px;">
                                                        ⚪ <?= lang('App.no_entry_abstention') ?>
                                                    </span>
                                                </div>

                                                <div style="margin-top: 8px; padding: 10px 12px; background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; font-size: 0.76rem; color: #e2e8f0; line-height: 1.45;">
                                                    <div style="font-weight: 700; color: #f87171; margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                                                        <i class="bi bi-exclamation-triangle-fill"></i> <?= lang('App.reason_ai_abstention') ?>:
                                                    </div>
                                                    <div style="white-space: pre-line; font-size: 0.74rem; color: #cbd5e1;">
                                                        <?= htmlspecialchars($nl_explanation) ?>
                                                    </div>
                                                </div>

                                                <div style="margin-top: 8px; padding: 6px 8px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px;">
                                                    <div style="font-size: 0.72rem; font-weight: 700; color: #fbbf24; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
                                                        <span><i class="bi bi-clock-history me-1"></i> <?= lang('App.u5j_history') ?></span>
                                                        <span style="font-size: 0.65rem; color: #94a3b8; font-weight: normal;"><?= htmlspecialchars($fix->home_team) ?> vs <?= htmlspecialchars($fix->away_team) ?></span>
                                                    </div>
                                                    <div class="table-responsive" style="margin: 0; padding: 0;">
                                                        <table class="table table-sm table-borderless text-white mb-0" style="font-size: 0.68rem;">
                                                            <thead>
                                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #94a3b8;">
                                                                    <th style="padding: 2px 4px;"><?= lang('App.team') ?></th>
                                                                    <th style="padding: 2px 4px; text-align: center;"><?= lang('App.form') ?></th>
                                                                    <th style="padding: 2px 4px;"><?= lang('App.recent_matches') ?></th>
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
                                                                             <?php if (empty($u5j_data['home']['matches'])): ?>
                                                                                 <span class="text-muted" style="font-size: 0.65rem;"><?= lang('App.no_recent_history') ?></span>
                                                                             <?php else: ?>
                                                                                 <?php foreach ($u5j_data['home']['matches'] as $m): ?>
                                                                                     <?php $badgeBg = ($m['result'] === 'V') ? '#10b981' : (($m['result'] === 'E') ? '#f59e0b' : '#ef4444'); ?>
                                                                                     <span class="badge" style="background: <?= $badgeBg ?>; font-weight: 600; font-size: 0.62rem; padding: 2px 4px;" title="<?= htmlspecialchars(($m['is_home'] ? 'vs ' : '@ ') . $m['opponent']) ?>">
                                                                                         <?= $m['result'] ?> (<?= htmlspecialchars($m['score']) ?>)
                                                                                     </span>
                                                                                 <?php endforeach; ?>
                                                                             <?php endif; ?>
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
                                                                             <?php if (empty($u5j_data['away']['matches'])): ?>
                                                                                 <span class="text-muted" style="font-size: 0.65rem;"><?= lang('App.no_recent_history') ?></span>
                                                                             <?php else: ?>
                                                                                 <?php foreach ($u5j_data['away']['matches'] as $m): ?>
                                                                                     <?php $badgeBg = ($m['result'] === 'V') ? '#10b981' : (($m['result'] === 'E') ? '#f59e0b' : '#ef4444'); ?>
                                                                                     <span class="badge" style="background: <?= $badgeBg ?>; font-weight: 600; font-size: 0.62rem; padding: 2px 4px;" title="<?= htmlspecialchars(($m['is_home'] ? 'vs ' : '@ ') . $m['opponent']) ?>">
                                                                                         <?= $m['result'] ?> (<?= htmlspecialchars($m['score']) ?>)
                                                                                     </span>
                                                                                 <?php endforeach; ?>
                                                                             <?php endif; ?>
                                                                         </div>
                                                                     </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding-top: 6px; border-top: 1px solid rgba(239, 68, 68, 0.2);">
                                                    <span style="font-size: 0.72rem; color: #fda4af; display: flex; align-items: center; gap: 4px;">
                                                        <i class="bi bi-info-circle-fill"></i> <?= lang('App.airflow_risk_protection_note') ?>
                                                    </span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger disabled" style="font-size: 0.72rem; font-weight: 700; opacity: 0.65; cursor: not-allowed;" disabled>
                                                        🚫 <?= lang('App.bet_prevented_risk') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif (!empty($fix->ah_suggestion)): ?>
                                        <div id="sec-ah-<?= $fix->fixture_id ?>" class="bet-card-section">
                                            <div class="asian-handicap-widget-box" style="padding: 8px 10px; background: rgba(15, 23, 42, 0.9); border-radius: 8px; border-left: 4px solid #38bdf8; font-size: 0.78rem; color: #cbd5e1;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 4px;">
                                                    <span style="font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 6px; font-size: 0.82rem;">
                                                        <i class="bi bi-shield-shaded"></i> <?= lang('App.goals_market_handicap') ?>:
                                                    </span>
                                                    <span class="badge" style="background: rgba(56, 189, 248, 0.18); border: 1px solid #38bdf8; color: #38bdf8; font-weight: 700; font-size: 0.76rem; padding: 3px 8px; border-radius: 6px;">
                                                        🎯 <?= htmlspecialchars($fix->ah_suggestion) ?> (<?= number_format($fix->ah_confidence ?? 65, 1) ?>%)
                                                    </span>
                                                </div>

                                                <div style="margin-top: 6px; padding: 6px 10px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 6px; font-size: 0.74rem; color: #e2e8f0; line-height: 1.4;">
                                                    <div style="font-weight: 700; color: #10b981; margin-bottom: 4px; display: flex; align-items: center; gap: 4px;">
                                                        <i class="bi bi-chat-left-text-fill"></i> <?= lang('App.natural_language_explanation') ?>:
                                                    </div>
                                                    <div style="white-space: pre-line; font-size: 0.72rem;">
                                                        <?= htmlspecialchars($nl_explanation) ?>
                                                    </div>
                                                </div>

                                                <div style="margin-top: 8px; padding: 6px 8px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 6px;">
                                                    <div style="font-size: 0.72rem; font-weight: 700; color: #fbbf24; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
                                                        <span><i class="bi bi-clock-history me-1"></i> <?= lang('App.u5j_history') ?></span>
                                                        <span style="font-size: 0.65rem; color: #94a3b8; font-weight: normal;"><?= htmlspecialchars($fix->home_team) ?> vs <?= htmlspecialchars($fix->away_team) ?></span>
                                                    </div>
                                                    <div class="table-responsive" style="margin: 0; padding: 0;">
                                                        <table class="table table-sm table-borderless text-white mb-0" style="font-size: 0.68rem;">
                                                            <thead>
                                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #94a3b8;">
                                                                    <th style="padding: 2px 4px;"><?= lang('App.team') ?></th>
                                                                    <th style="padding: 2px 4px; text-align: center;"><?= lang('App.form') ?></th>
                                                                    <th style="padding: 2px 4px;"><?= lang('App.recent_matches') ?></th>
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
                                                                             <?php if (empty($u5j_data['home']['matches'])): ?>
                                                                                 <span class="text-muted" style="font-size: 0.65rem;"><?= lang('App.no_recent_history') ?></span>
                                                                             <?php else: ?>
                                                                                 <?php foreach ($u5j_data['home']['matches'] as $m): ?>
                                                                                     <?php $badgeBg = ($m['result'] === 'V') ? '#10b981' : (($m['result'] === 'E') ? '#f59e0b' : '#ef4444'); ?>
                                                                                     <span class="badge" style="background: <?= $badgeBg ?>; font-weight: 600; font-size: 0.62rem; padding: 2px 4px;" title="<?= htmlspecialchars(($m['is_home'] ? 'vs ' : '@ ') . $m['opponent']) ?>">
                                                                                         <?= $m['result'] ?> (<?= htmlspecialchars($m['score']) ?>)
                                                                                     </span>
                                                                                 <?php endforeach; ?>
                                                                             <?php endif; ?>
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
                                                                             <?php if (empty($u5j_data['away']['matches'])): ?>
                                                                                 <span class="text-muted" style="font-size: 0.65rem;"><?= lang('App.no_recent_history') ?></span>
                                                                             <?php else: ?>
                                                                                 <?php foreach ($u5j_data['away']['matches'] as $m): ?>
                                                                                     <?php $badgeBg = ($m['result'] === 'V') ? '#10b981' : (($m['result'] === 'E') ? '#f59e0b' : '#ef4444'); ?>
                                                                                     <span class="badge" style="background: <?= $badgeBg ?>; font-weight: 600; font-size: 0.62rem; padding: 2px 4px;" title="<?= htmlspecialchars(($m['is_home'] ? 'vs ' : '@ ') . $m['opponent']) ?>">
                                                                                         <?= $m['result'] ?> (<?= htmlspecialchars($m['score']) ?>)
                                                                                     </span>
                                                                                 <?php endforeach; ?>
                                                                             <?php endif; ?>
                                                                         </div>
                                                                     </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <?php if (!empty($motivation)): ?>
                                                    <?= renderStructuredMotivation($motivation, $raw_reasoning, $fix) ?>
                                                <?php endif; ?>

                                                <?php if (!empty($calc_details)): ?>
                                                    <div style="margin-top: 6px;">
                                                        <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 0.68rem; padding: 2px 6px; border-color: rgba(56, 189, 248, 0.4); color: #38bdf8;" onclick="$('#ah-calc-<?= $fix->fixture_id ?>').slideToggle(200);">
                                                            📐 <?= lang('App.view_detailed_calculation') ?> <i class="bi bi-chevron-down ms-1"></i>
                                                        </button>
                                                        <div id="ah-calc-<?= $fix->fixture_id ?>" style="display: none; margin-top: 6px; padding: 6px 8px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 6px; font-size: 0.7rem; color: #cbd5e1;">
                                                            <div style="font-weight: 700; color: #38bdf8; margin-bottom: 4px;">
                                                                🔍 <?= lang('App.calculation_memory_step_by_step') ?>:
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
                                                        <i class="bi bi-chat-quote-fill"></i> <?= lang('App.review_analysis_f24') ?>
                                                    </span>
                                                    <?php if (!empty($fix->futbol24_url)): ?>
                                                        <a href="<?= htmlspecialchars($fix->futbol24_url) ?>" target="_blank" rel="noopener" style="color: #38bdf8; font-size: 0.7rem; text-decoration: none;">
                                                            <?= lang('App.view_on_futbol24') ?> <i class="bi bi-box-arrow-up-right"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($fix->futbol24_tip)): ?>
                                                    <div style="margin-bottom: 4px; color: #f8fafc; font-weight: 600;">
                                                        📌 <strong><?= lang('App.recommended_tip') ?>:</strong> <?= htmlspecialchars($fix->futbol24_tip) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($fix->futbol24_analysis)): ?>
                                                    <div style="margin-top: 4px;">
                                                        <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 0.68rem; padding: 2px 6px; border-color: rgba(16, 185, 129, 0.4); color: #10b981;" onclick="$('#f24-analysis-<?= $fix->fixture_id ?>').slideToggle(200);">
                                                            📖 <?= lang('App.read_editorial_analysis') ?> <i class="bi bi-chevron-down ms-1"></i>
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
                                                <span style="display: block; color: #94a3b8; font-size: 0.68rem;"><?= lang('App.exp_total_goals') ?></span>
                                                <strong style="color: #10b981; font-size: 0.88rem;">
                                                    <?= number_format(($fix->home_avg_goals_scored ?? 0) + ($fix->away_avg_goals_scored ?? 0), 2) ?>
                                                </strong>
                                            </div>
                                            <div style="background: rgba(30, 41, 59, 0.8); padding: 6px 4px; border-radius: 6px;">
                                                <span style="display: block; color: #94a3b8; font-size: 0.68rem;"><?= lang('App.proj_corners') ?></span>
                                                <strong style="color: #38bdf8; font-size: 0.88rem;">
                                                    <?= number_format(($fix->home_avg_corners ?? 0) + ($fix->away_avg_corners ?? 0), 1) ?>
                                                </strong>
                                            </div>
                                            <div style="background: rgba(30, 41, 59, 0.8); padding: 6px 4px; border-radius: 6px;">
                                                <span style="display: block; color: #94a3b8; font-size: 0.68rem;"><?= lang('App.proj_cards') ?></span>
                                                <strong style="color: #fbbf24; font-size: 0.88rem;">
                                                    <?= number_format(($fix->home_avg_cards ?? 0) + ($fix->away_avg_cards ?? 0), 1) ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <table style="width: 100%; border-collapse: collapse; text-align: center; font-size: 0.76rem;">
                                            <thead>
                                                <tr style="color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                                    <th style="text-align: left; padding: 4px; width: 40%;"><?= htmlspecialchars($fix->home_team) ?></th>
                                                    <th style="padding: 4px; width: 20%;"><?= lang('App.metric') ?></th>
                                                    <th style="text-align: right; padding: 4px; width: 40%;"><?= htmlspecialchars($fix->away_team) ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <td style="text-align: left; padding: 4px; color: #38bdf8;"><?= number_format($fix->home_avg_goals_scored ?? 0, 1) ?> / <?= number_format($fix->home_avg_goals_conceded ?? 0, 1) ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;"><?= lang('App.goals') ?></td>
                                                    <td style="text-align: right; padding: 4px; color: #38bdf8;"><?= number_format($fix->away_avg_goals_scored ?? 0, 1) ?> / <?= number_format($fix->away_avg_goals_conceded ?? 0, 1) ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <td style="text-align: left; padding: 4px; color: #10b981;"><?= (isset($fix->home_clean_sheets_pct) && $fix->home_clean_sheets_pct !== null && $fix->home_clean_sheets_pct !== '') ? round($fix->home_clean_sheets_pct) . '%' : 'N/A' ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;"><?= lang('App.clean_sheets') ?></td>
                                                    <td style="text-align: right; padding: 4px; color: #10b981;"><?= (isset($fix->away_clean_sheets_pct) && $fix->away_clean_sheets_pct !== null && $fix->away_clean_sheets_pct !== '') ? round($fix->away_clean_sheets_pct) . '%' : 'N/A' ?></td>
                                                </tr>
                                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <td style="text-align: left; padding: 4px; color: #a78bfa;"><?= number_format($fix->home_avg_corners ?? 0, 1) ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;"><?= lang('App.corners') ?></td>
                                                    <td style="text-align: right; padding: 4px; color: #a78bfa;"><?= number_format($fix->away_avg_corners ?? 0, 1) ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align: left; padding: 4px; color: #fbbf24;"><?= number_format($fix->home_avg_cards ?? 0, 1) ?></td>
                                                    <td style="padding: 4px; color: #94a3b8;"><?= lang('App.cards') ?></td>
                                                    <td style="text-align: right; padding: 4px; color: #fbbf24;"><?= number_format($fix->away_avg_cards ?? 0, 1) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <?php
                                            $expGolsTotal = ($fix->home_avg_goals_scored ?? 0) + ($fix->away_avg_goals_scored ?? 0);
                                            $expCantosTotal = ($fix->home_avg_corners ?? 0) + ($fix->away_avg_corners ?? 0);
                                            $expCartoesTotal = ($fix->home_avg_cards ?? 0) + ($fix->away_avg_cards ?? 0);

                                            if ($expGolsTotal >= 3.2) {
                                                $insightGols = sprintf(lang('App.insight_goals_high'), number_format($expGolsTotal, 2));
                                            } elseif ($expGolsTotal >= 2.5) {
                                                $insightGols = sprintf(lang('App.insight_goals_moderate'), number_format($expGolsTotal, 2));
                                            } else {
                                                $insightGols = sprintf(lang('App.insight_goals_low'), number_format($expGolsTotal, 2));
                                            }

                                            if ($expCantosTotal >= 11.0) {
                                                $insightCantos = sprintf(lang('App.insight_corners_high'), round($expCantosTotal));
                                            } else {
                                                $insightCantos = sprintf(lang('App.insight_corners_normal'), round($expCantosTotal));
                                            }

                                            if (($fix->away_avg_cards ?? 0) >= 3.0) {
                                                $insightCartoes = sprintf(lang('App.insight_cards_away'), round($expCartoesTotal), htmlspecialchars($fix->away_team), number_format($fix->away_avg_cards ?? 0, 1));
                                            } elseif (($fix->home_avg_cards ?? 0) >= 3.0) {
                                                $insightCartoes = sprintf(lang('App.insight_cards_home'), round($expCartoesTotal), htmlspecialchars($fix->home_team), number_format($fix->home_avg_cards ?? 0, 1));
                                            } else {
                                                $insightCartoes = sprintf(lang('App.insight_cards_combined'), number_format($expCartoesTotal, 1));
                                            }
                                        ?>

                                        <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed rgba(255, 255, 255, 0.15); font-size: 0.74rem;">
                                            <span style="color: #f47c20; font-weight: 700; display: block; margin-bottom: 4px;"><i class="bi bi-lightbulb-fill"></i> <?= lang('App.insights_trends') ?></span>
                                            <ul style="padding-left: 15px; margin-bottom: 0; color: #cbd5e1; line-height: 1.4;">
                                                <li style="margin-bottom: 3px;">⚽ <strong><?= lang('App.goals') ?>:</strong> <?= $insightGols ?></li>
                                                <li style="margin-bottom: 3px;">🚩 <strong><?= lang('App.corners') ?>:</strong> <?= $insightCantos ?></li>
                                                <li style="margin-bottom: 3px;">🟨 <strong><?= lang('App.cards') ?>:</strong> <?= $insightCartoes ?></li>
                                                <li>🌳 <strong><?= lang('App.decision_tree') ?>:</strong> <strong><?= $decision['market'] ?></strong> (<?= lang('App.region') ?>: <?= $decision['region_short'] ?> | <?= lang('App.teams') ?>: <?= $decision['foul_short'] ?> | <?= lang('App.referee') ?>: <?= $decision['referee_short'] ?>) — <?= $decision['rationale'] ?></li>
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
                                            $cardPalpite = 'Menos de 7.5';
                                            if (!empty($fix->prediction_text) && preg_match('/1ª\s*Opção:\s*Under\s*(\d+(?:\.\d+)?)/i', $fix->prediction_text, $mPalpite)) {
                                                $cardPalpite = 'Menos de ' . $mPalpite[1];
                                            } elseif (!empty($fix->prediction_text) && preg_match('/Under\s*(\d+\.\d+|\d+)/i', $fix->prediction_text, $mPalpite)) {
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
                                           title="<?= lang('App.title_bet_cards') ?>">
                                            <i class="bi bi-card-amber"></i> <?= lang('App.cards') ?>
                                        </a>

                                        <a href="<?= base_url('apostas?new_bet=1&fixture_id=' . $fix->fixture_id . '&mercado=handicap&palpite=' . urlencode($ahPalpiteClean)) ?>" 
                                           class="bet-stats-btn" 
                                           style="border-color: rgba(56, 189, 248, 0.4); color: #38bdf8; text-decoration: none; padding: 4px 8px; font-size: 0.75rem;" 
                                           title="<?= lang('App.title_bet_handicap') ?>">
                                            <i class="bi bi-shield-shaded"></i> <?= lang('App.handicap_ah') ?>
                                        </a>

                                        <!-- Botão Estatísticas à esquerda de Grok AI -->
                                        <button type="button" 
                                                class="bet-stats-btn" 
                                                data-tooltip="<?= $userHasBalance ? lang('App.tooltip_view_detailed_stats') : lang('App.tooltip_detailed_stats_credits') ?>"
                                                onclick="toggleDetailedStats('<?= $fix->fixture_id ?>', <?= $userHasBalance ? 'true' : 'false' ?>)">
                                            <?php if ($userHasBalance): ?>
                                                <i class="bi bi-bar-chart-line-fill"></i> <?= lang('App.stats') ?>
                                            <?php else: ?>
                                                <i class="bi bi-bar-chart-line"></i> <?= lang('App.stats') ?> <i class="bi bi-lock-fill" style="font-size: 0.7rem; color: #f47c20;"></i>
                                            <?php endif; ?>
                                        </button>

                                        <!-- Botão Conversar com Grok AI -->
                                        <button type="button" class="bet-ai-btn" title="<?= lang('App.chat_with_grok_ai') ?>" onclick="event.stopPropagation(); openAiChat(
                                            <?= $jsAttr($fix->home_team) ?>,
                                            <?= $jsAttr($fix->away_team) ?>,
                                            <?= $jsAttr($fix->league_name) ?>,
                                            <?= $jsAttr($fix->referee_name ?? '') ?>,
                                            <?= $jsAttr($fix->prediction_text ?? '') ?>,
                                            <?= $jsAttr($prob) ?>,
                                            <?= $jsAttr($fix->home_avg_goals_scored ?? '') ?>,
                                            <?= $jsAttr($fix->home_avg_goals_conceded ?? '') ?>,
                                            <?= $jsAttr((isset($fix->home_clean_sheets_pct) && $fix->home_clean_sheets_pct !== null && $fix->home_clean_sheets_pct !== '') ? round($fix->home_clean_sheets_pct) . '%' : lang('App.not_found')) ?>,
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
            const status = (el.getAttribute('data-status') || '').toUpperCase();
            const officialElapsed = el.getAttribute('data-elapsed');
            const fixtureId = el.getAttribute('data-fixture-elapsed');
            const bTimeEl = fixtureId ? document.querySelector(`[data-betano-time="${fixtureId}"]`) : null;
            
            if (!startDateStr) return;
            
            // Converte "YYYY-MM-DD HH:MM:SS" (UTC) para ISO UTC ("YYYY-MM-DDTHH:MM:SSZ")
            const utcDateStr = startDateStr.replace(' ', 'T') + 'Z';
            const startDate = new Date(utcDateStr);
            const startUtc = startDate.getTime();
            
            const diffMs = nowUtc - startUtc;
            const diffMins = Math.floor(diffMs / 60000);
            
            let text = '';
            let showPulse = false;
            
            const finishedStatuses = ['FT', 'AET', 'PEN', '120', '90', 'FINISHED', 'MATCH FINISHED', 'FULL TIME', 'FIN', 'FINAL', 'FT_PEN'];
            const postponedStatuses = ['PST', 'POSTPONED', 'CANCELLED', 'CANC'];
            const liveStatusesList = ['1H', '2H', 'HT', 'ET', 'P', 'LIVE', 'BT'];
            
            if (postponedStatuses.includes(status)) {
                text = '⚠️ ADIADO';
                el.classList.remove('live');
                el.classList.add('pst');
            } else if (finishedStatuses.includes(status) || diffMins > 115) {
                text = 'Encerrado';
                el.classList.remove('live');
            } else if (status === 'HT') {
                if (diffMins >= 65) {
                    text = (officialElapsed && officialElapsed !== 'null' && officialElapsed !== '') ? officialElapsed + "'" : Math.max(45, diffMins - 15) + "'";
                    showPulse = true;
                    el.classList.add('live');
                } else {
                    text = 'Intervalo';
                    showPulse = true;
                    el.classList.add('live');
                }
            } else if (liveStatusesList.includes(status)) {
                showPulse = true;
                el.classList.add('live');
                if (officialElapsed && officialElapsed !== 'null' && officialElapsed !== '') {
                    text = officialElapsed + "'";
                } else if (status === '2H') {
                    text = Math.min(90, Math.max(45, diffMins - 15)) + "'";
                } else {
                    text = Math.min(45, Math.max(0, diffMins)) + "'";
                }
            } else if (diffMins < 0 || status === 'NS') {
                text = 'Pré-jogo';
                el.classList.remove('live');
            } else {
                text = diffMins + "'";
                el.classList.add('live');
            }
            
            if (showPulse) {
                el.innerHTML = `<span class="live-pulse-dot"></span> ${text}`;
            } else {
                el.innerText = text;
            }

            if (bTimeEl) {
                bTimeEl.textContent = text;
            }
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

    let currentShowFinishedFilter = <?= $showFinished ? 'true' : 'false' ?>;
    let currentShowPostponedFilter = <?= !empty($showPostponed) ? 'true' : 'false' ?>;
    let currentOnlySafeFilter = <?= !empty($onlySafe) ? 'true' : 'false' ?>;
    let currentOnlySurebetFilter = <?= !empty($onlySurebet) ? 'true' : 'false' ?>;
    let currentOnlyHasBetFilter = false;
    let currentOnlyLiveFilter = <?= !empty($onlyLive) ? 'true' : 'false' ?>;
    let currentOnlyResenhaFilter = <?= !empty($onlyResenha) ? 'true' : 'false' ?>;

    function toggleShowFinishedFilter(checkbox) {
        currentShowFinishedFilter = checkbox.checked;
        const statusSpan = document.getElementById('showFinishedToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentShowFinishedFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentShowFinishedFilter ? '#f47c20' : '#8a99a8';
        }
        applyFilters();
    }

    function toggleShowPostponedFilter(checkbox) {
        currentShowPostponedFilter = checkbox.checked;
        const statusSpan = document.getElementById('showPostponedToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentShowPostponedFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentShowPostponedFilter ? '#f59e0b' : '#8a99a8';
        }
        applyFilters();
    }

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

    function toggleLiveBetsFilter(checkbox) {
        currentOnlyLiveFilter = checkbox.checked;
        const statusSpan = document.getElementById('onlyLiveToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentOnlyLiveFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentOnlyLiveFilter ? '#ef4444' : '#8a99a8';
        }
        applyFilters();
    }

    function toggleResenhaFilter(checkbox) {
        currentOnlyResenhaFilter = checkbox.checked;
        const statusSpan = document.getElementById('onlyResenhaToggleStatus');
        if (statusSpan) {
            statusSpan.innerText = currentOnlyResenhaFilter ? 'Sim' : 'Não';
            statusSpan.style.color = currentOnlyResenhaFilter ? '#00e676' : '#8a99a8';
        }
        applyFilters();
    }

    // Aplica os filtros combinados (Liga + Aba de Destaques + Busca por Texto + Jogos Encerrados + Adiados + Apenas Apostas Seguras + Surebets + Com Aposta + Em Andamento + Com Resenha)
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
            const isLive = card.getAttribute('data-is-live') === '1';
            const isFinished = card.getAttribute('data-is-finished') === '1';
            const isPostponed = card.getAttribute('data-is-postponed') === '1';
            const hasResenha = card.getAttribute('data-has-resenha') === '1';
            
            const matchLeague = (currentLeagueFilter === 'all' || cardLeague === currentLeagueFilter);
            const matchTab = (currentTabFilter === 'competicoes' || cardProb >= 70.0);
            const matchText = (searchNormalized === '' || cardTeamsNormalized.includes(searchNormalized));
            const matchSafe = (!currentOnlySafeFilter || isSafe);
            const matchSurebet = (!currentOnlySurebetFilter || isSurebet);
            const matchHasBet = (!currentOnlyHasBetFilter || hasAposta);
            const matchLive = (!currentOnlyLiveFilter || isLive);
            const matchFinished = (currentShowFinishedFilter || !isFinished);
            const matchPostponed = (currentShowPostponedFilter || !isPostponed);
            const matchResenha = (!currentOnlyResenhaFilter || hasResenha);
            
            if (matchLeague && matchTab && matchText && matchSafe && matchSurebet && matchHasBet && matchLive && matchFinished && matchPostponed && matchResenha) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        
        // Atualiza o selo com o quantitativo de partidas visíveis vs total do período
        const totalBadge = document.getElementById('totalMatchesCount');
        if (totalBadge) {
            if (visibleCount !== cards.length) {
                totalBadge.innerHTML = `${visibleCount} <span style="font-size:0.75rem; color:#94a3b8; font-weight:600;">/ ${cards.length}</span>`;
            } else {
                totalBadge.textContent = cards.length;
            }
        }

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
                            const statusUpper = (fix.status || '').toUpperCase();
                            const startDateStr = elapsedEl ? elapsedEl.getAttribute('data-start-utc') : null;
                            let diffMinsLive = null;
                            if (startDateStr) {
                                const startUtc = new Date(startDateStr.replace(' ', 'T') + 'Z').getTime();
                                diffMinsLive = Math.floor((Date.now() - startUtc) / 60000);
                            }
                            
                            let isMatchFinishedNow = ['FT', 'AET', 'PEN', '120', '90', 'FINISHED', 'MATCH FINISHED', 'FULL TIME', 'FIN', 'FINAL', 'FT_PEN'].includes(statusUpper);
                            if (diffMinsLive !== null && diffMinsLive > 115 && !['PST', 'POSTPONED', 'CANCELLED', 'CANC'].includes(statusUpper)) {
                                isMatchFinishedNow = true;
                            }
                            let isMatchLiveNow = liveStatuses.includes(statusUpper) && !isMatchFinishedNow;

                            card.setAttribute('data-is-live', isMatchLiveNow ? '1' : '0');
                            if (isMatchFinishedNow) {
                                card.setAttribute('data-is-finished', '1');
                            }

                            if (isMatchFinishedNow) {
                                if (elapsedEl) {
                                    elapsedEl.classList.remove('live');
                                    elapsedEl.textContent = 'Encerrado';
                                }
                                if (bTimeEl) bTimeEl.textContent = 'Encerrado';
                            } else if (isMatchLiveNow) {
                                let minText = fix.elapsed ? fix.elapsed + "'" : (statusUpper === 'HT' ? ((diffMinsLive !== null && diffMinsLive >= 65) ? diffMinsLive + "'" : 'Int') : 'Ao Vivo');
                                if (elapsedEl) {
                                    elapsedEl.classList.add('live');
                                    elapsedEl.innerHTML = `<span class="live-pulse-dot"></span> ${minText}`;
                                }
                                if (bTimeEl) bTimeEl.textContent = minText;
                            } else if (['PST', 'POSTPONED', 'CANCELLED', 'CANC'].includes(statusUpper)) {
                                if (elapsedEl) {
                                    elapsedEl.classList.remove('live');
                                    elapsedEl.classList.add('pst');
                                    elapsedEl.textContent = '⚠️ ADIADO';
                                }
                                if (bTimeEl) bTimeEl.textContent = '⚠️ ADIADO';
                            } else if (statusUpper === 'NS') {
                                if (elapsedEl) {
                                    elapsedEl.classList.remove('live');
                                    elapsedEl.textContent = 'Pré-jogo';
                                }
                                if (bTimeEl) bTimeEl.textContent = 'Pré-jogo';
                            }
                        }
                    });
                    if (currentOnlyLiveFilter || !currentShowFinishedFilter) {
                        applyFilters();
                    }
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

    // Alternar exibição das seções retráteis dos cards por badges (Comportamento Accordion)
    function toggleCardSection(fixtureId, sectionType) {
        const targetSec = $('#sec-' + sectionType + '-' + fixtureId);
        const targetBtn = $('#btn-' + sectionType + '-' + fixtureId);
        const allTypes = ['cards', 'ah', 'futbol24', 'stats'];
        const isOpening = !targetSec.is(':visible');

        if (isOpening) {
            // Retrair todas as outras seções do mesmo card
            allTypes.forEach(function(type) {
                if (type !== sectionType) {
                    const otherSec = $('#sec-' + type + '-' + fixtureId);
                    const otherBtn = $('#btn-' + type + '-' + fixtureId);
                    if (otherSec.length && (otherSec.is(':visible') || otherSec.is(':animated'))) {
                        otherSec.slideUp(180);
                        otherBtn.removeClass('active');
                        otherBtn.find('.icon-arrow').removeClass('bi-chevron-up').addClass('bi-chevron-down');
                    }
                }
            });

            // Expandir a seção clicada
            targetSec.slideDown(200, function() {
                targetBtn.addClass('active');
                targetBtn.find('.icon-arrow').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            });
        } else {
            // Retrair a seção atual se já estiver aberta
            targetSec.slideUp(200, function() {
                targetBtn.removeClass('active');
                targetBtn.find('.icon-arrow').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            });
        }
    }
</script>

<?php
require VIEWPATH.'/footer.php';
?>
