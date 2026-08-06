<?php

/**
 * Script para reavaliar todas as apostas no banco de dados
 * com o novo Gatekeeper Dinâmico (+EV e Teto de Segurança Flexível).
 */

$host = '127.0.0.1';
$user = 'root';
$pass = 'YM11rMrT32xH0E6N';
$dbname = 'footballweb';

$pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// 1. Obter Média de Odds Vencedoras (Under Cartões)
$stmtAvg = $pdo->query("
    SELECT AVG(odd) as avg_odd, COUNT(*) as total_vitorias 
    FROM apostas 
    WHERE status = 'Ganha' 
      AND (mercado LIKE '%cartõ%' OR mercado LIKE '%card%') 
      AND (palpite LIKE '%Menos%' OR palpite LIKE '%under%')
");
$rowAvg = $stmtAvg->fetch();
$avgWinningOdd = ($rowAvg && $rowAvg['avg_odd'] && (int)$rowAvg['total_vitorias'] > 0) 
    ? round((float)$rowAvg['avg_odd'], 2) 
    : 1.60;

$maxAllowedOdd = round(max(2.00, $avgWinningOdd + 0.35), 2);

echo "=== GESTÃO DO GATEKEEPER DINÂMICO ===\n";
echo "Média Histórica de Odds Vencedoras (Under Cartões): {$avgWinningOdd}\n";
echo "Teto Dinâmico de Segurança Calculado: {$maxAllowedOdd}\n\n";

function factorial($n) {
    if ($n <= 1) return 1;
    $res = 1;
    for ($i = 2; $i <= $n; $i++) {
        $res *= $i;
    }
    return $res;
}

// 2. Buscar todas as apostas para reavaliação
$stmtApostas = $pdo->query("SELECT * FROM apostas ORDER BY id ASC");
$apostas = $stmtApostas->fetchAll();

$aprovadosCount = 0;
$noBetCount = 0;
$naoAnalisadoCount = 0;

$updateStmt = $pdo->prepare("
    UPDATE apostas 
    SET status_gatekeeper = :status_gk,
        odd_justa = :odd_justa,
        probabilidade_poisson = :prob_poisson,
        ev_percentual = :ev_perc
    WHERE id = :id
");

foreach ($apostas as $aposta) {
    $id = (int)$aposta['id'];
    $palpite = $aposta['palpite'];
    $mercado = $aposta['mercado'];
    $odd = (float)$aposta['odd'];
    $fixtureId = $aposta['fixture_id'] ? (int)$aposta['fixture_id'] : null;
    $timeCasa = $aposta['time_casa'];
    $timeFora = $aposta['time_fora'];

    $isOver = (stripos($palpite, 'over') !== false || stripos($palpite, 'mais') !== false);
    $isCartoes = (stripos($mercado, 'cartõ') !== false || stripos($mercado, 'card') !== false);

    $oddJusta = null;
    $probPoisson = null;
    $evPercentual = null;
    $statusGatekeeper = 'NAO_ANALISADO';

    if ($isOver || ($isCartoes && $isOver)) {
        $statusGatekeeper = 'NO_BET';
    } elseif ($isCartoes) {
        $fixture = null;
        if ($fixtureId) {
            $stmtFix = $pdo->prepare("SELECT prediction_text FROM fixtures_trends WHERE fixture_id = :fid LIMIT 1");
            $stmtFix->execute(['fid' => $fixtureId]);
            $fixture = $stmtFix->fetch();
        }

        if (!$fixture && !empty($timeCasa) && !empty($timeFora)) {
            $stmtFix = $pdo->prepare("
                SELECT prediction_text 
                FROM fixtures_trends 
                WHERE (home_team LIKE :tc1 OR away_team LIKE :tc2)
                  AND (home_team LIKE :tf1 OR away_team LIKE :tf2)
                ORDER BY fixture_date DESC 
                LIMIT 1
            ");
            $stmtFix->execute([
                'tc1' => "%{$timeCasa}%",
                'tc2' => "%{$timeCasa}%",
                'tf1' => "%{$timeFora}%",
                'tf2' => "%{$timeFora}%"
            ]);
            $fixture = $stmtFix->fetch();
        }

        if ($fixture && !empty($fixture['prediction_text'])) {
            preg_match('/xC(?::|\s+elevado)?\s*\(?(\d+\.\d+|\d+)/i', $fixture['prediction_text'], $matchesXc);
            $xc = !empty($matchesXc[1]) ? (float)$matchesXc[1] : null;

            if ($xc !== null && $xc > 0) {
                preg_match('/(\d+\.\d+|\d+)/', $palpite, $matchesLine);
                $line = !empty($matchesLine[1]) ? (float)$matchesLine[1] : 5.5;

                $kMax = (int)floor($line);
                $probUnderCdf = 0.0;
                for ($k = 0; $k <= $kMax; $k++) {
                    $probUnderCdf += (exp(-$xc) * pow($xc, $k)) / factorial($k);
                }

                $probPoisson = round(min(100.0, max(0.0, $probUnderCdf * 100.0)), 2);

                if ($probPoisson > 0) {
                    $oddJusta = round(100.0 / $probPoisson, 2);
                    $evPercentual = round((($probPoisson / 100.0) * $odd - 1.0) * 100.0, 2);
                }

                if ($odd > $maxAllowedOdd) {
                    $statusGatekeeper = 'NO_BET';
                } elseif ($evPercentual !== null && $evPercentual >= 0 && $probPoisson >= 50.0) {
                    $statusGatekeeper = 'APROVADO';
                } else {
                    $statusGatekeeper = 'NO_BET';
                }
            }
        }
    }

    if ($statusGatekeeper === 'APROVADO') $aprovadosCount++;
    elseif ($statusGatekeeper === 'NO_BET') $noBetCount++;
    else $naoAnalisadoCount++;

    $updateStmt->execute([
        'status_gk' => $statusGatekeeper,
        'odd_justa' => $oddJusta,
        'prob_poisson' => $probPoisson,
        'ev_perc' => $evPercentual,
        'id' => $id
    ]);
}

echo "Reavaliação concluída com sucesso!\n";
echo "Total de Apostas Processadas: " . count($apostas) . "\n";
echo "Status APROVADO (+EV): {$aprovadosCount}\n";
echo "Status NO_BET: {$noBetCount}\n";
echo "Status NAO_ANALISADO: {$naoAnalisadoCount}\n";

