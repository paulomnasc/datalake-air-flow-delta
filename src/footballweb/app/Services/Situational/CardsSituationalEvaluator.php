<?php

namespace App\Services\Situational;

/**
 * CardsSituationalEvaluator
 *
 * Avalia o estado situacional para mercados de Cartões (Total e Individual, Under e Over).
 */
class CardsSituationalEvaluator implements SituationalEvaluatorInterface
{
    public function evaluate(object $bet, array $matchContext): SituationalResult
    {
        $palpite = (string)($bet->palpite ?? '');
        $mercado = (string)($bet->mercado ?? '');
        $homeTeam = (string)($bet->time_casa ?? $matchContext['home_team'] ?? 'Mandante');
        $awayTeam = (string)($bet->time_fora ?? $matchContext['away_team'] ?? 'Visitante');

        $elapsed = (int)($matchContext['elapsed'] ?? 0);
        $status = strtoupper(trim((string)($matchContext['fixture_status'] ?? 'NS')));
        $isLive = (bool)($matchContext['is_live'] ?? false);

        $yh = (int)($matchContext['yellow_cards_home'] ?? 0);
        $ya = (int)($matchContext['yellow_cards_away'] ?? 0);
        $rh = (int)($matchContext['red_cards_home'] ?? 0);
        $ra = (int)($matchContext['red_cards_away'] ?? 0);

        // Extrai a linha de cartões (ex: 2.5, 3.5, 4.5)
        $lineVal = 4.5;
        if (preg_match('/(\d+(?:\.\d+)?)/', $palpite, $m)) {
            $lineVal = (float)$m[1];
        }

        $isUnder = (stripos($palpite, 'menos') !== false || stripos($palpite, 'under') !== false);
        $isIndividual = (stripos($mercado, 'individual') !== false || stripos($palpite, 'individual') !== false);

        // Se individual, identifica qual time
        $hasHome = ($homeTeam !== '' && stripos($palpite, $homeTeam) !== false);
        $hasAway = ($awayTeam !== '' && stripos($palpite, $awayTeam) !== false);

        // Se for mercado individual mas nenhum dos times foi explicitamente citado no palpite
        // OU se a linha de cartões for >= 5.0 (patamar clássico de jogo completo, nunca individual):
        // avalia obrigatoriamente como Total do Jogo!
        if ($isIndividual && ($hasHome || $hasAway) && $lineVal < 5.0) {
            $isHome = $hasHome;
            $yellows = $isHome ? $yh : $ya;
            $reds = $isHome ? $rh : $ra;
            $teamName = $isHome ? $homeTeam : $awayTeam;
        } else {
            $yellows = $yh + $ya;
            $reds = $rh + $ra;
            $teamName = 'Total do Jogo';
        }

        // Cartões contabilizados (amarelos + vermelhos)
        $totalCards = $yellows + $reds;
        if ($teamName === 'Total do Jogo') {
            $detalheTatico = "Total no Jogo: {$totalCards} cartões ({$yh}+{$ya} Am, {$rh}+{$ra} Vm - {$elapsed}')";
        } else {
            $detalheTatico = "{$teamName}: {$yellows} Amarelos, {$reds} Vermelhos ({$elapsed}')";
        }

        // Se a partida ainda não iniciou (Pré-Jogo)
        if (!$isLive && in_array($status, ['NS', 'TBD', 'POSTP'])) {
            return new SituationalResult(
                10,
                'verde',
                false,
                '⏳ Aguardando Início',
                'Partida ainda não iniciada. Velocímetro em standby de pré-jogo.',
                $lineVal,
                (float)$totalCards,
                'PRE_JOGO',
                'Aposta confirmada aguardando o apito inicial.'
            );
        }

        $distanciaLinha = $lineVal - $totalCards;

        if ($isUnder) {
            // MERCADO UNDER CARTÕES (Risco aumenta com mais cartões)
            $sobraCards = $lineVal - $totalCards;

            if ($totalCards > $lineVal) {
                // Limite estourado -> Red Total
                return new SituationalResult(
                    95,
                    'vermelho',
                    false,
                    '🔴 Limite de Cartões Estourado',
                    "{$totalCards} cartões aos {$elapsed}'. Limite de {$lineVal} cartões ultrapassado.",
                    $distanciaLinha,
                    (float)$totalCards,
                    'RED_TOTAL',
                    $detalheTatico
                );
            }

            if ($sobraCards <= 0.5) {
                // A exatamente 1 cartão da perda
                if ($elapsed >= 82) {
                    $gaugePct = 35;
                    $faixaCor = 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟢 Segure até o Fim (Reta Final)';
                    $alertaMsg = "{$totalCards} cartões aos {$elapsed}'. Jogo na reta final: deixe o relógio correr para assegurar o Green!";
                } else {
                    $gaugePct = 60;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT';
                    $alertaMsg = "{$totalCards} cartões aplicados aos {$elapsed}'. A 1 cartão do red! Avalie cashout preventivo ou hedge!";
                }
            } elseif ($elapsed <= 45 && $totalCards >= ($lineVal * 0.60)) {
                // Mais de 60% dos cartões já no 1T
                $gaugePct = 62;
                $faixaCor = 'cashout';
                $isCashoutMoment = true;
                $alertaTitulo = '⚡ MOMENTO DO CASHOUT (1T Violento)';
                $alertaMsg = "{$totalCards} cartões já no 1º tempo aos {$elapsed}' (mais de 60% do limite de {$lineVal}). Clima tenso. Avalie cashout!";
            } else {
                $gaugePct = max(10, round(35 - ($elapsed / 90) * 25));
                $faixaCor = 'verde';
                $isCashoutMoment = false;
                $alertaTitulo = '🟢 Sob Controle';
                $alertaMsg = "{$totalCards} cartões aos {$elapsed}'. Restam {$sobraCards} cartões de folga.";
            }

            return new SituationalResult(
                (int)$gaugePct,
                $faixaCor,
                $isCashoutMoment,
                $alertaTitulo,
                $alertaMsg,
                $distanciaLinha,
                (float)$totalCards,
                'UNDER_CONTROLE',
                $detalheTatico
            );
        } else {
            // MERCADO OVER CARTÕES (Risco diminui com mais cartões)
            if ($totalCards >= ceil($lineVal)) {
                return new SituationalResult(
                    10,
                    'verde',
                    false,
                    '🟢 Meta de Cartões Batida (Green)',
                    "Já saíram {$totalCards} cartões aos {$elapsed}'. Aposta liquidada com vitória!",
                    $distanciaLinha,
                    (float)$totalCards,
                    'GREEN_TOTAL',
                    $detalheTatico
                );
            }

            $faltamCards = ceil($lineVal) - $totalCards;
            if ($elapsed >= 80) {
                $gaugePct = 80;
                $faixaCor = 'vermelho';
                $isCashoutMoment = true;
                $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Falta de Cartões)';
                $alertaMsg = "Reta final aos {$elapsed}'. Faltam {$faltamCards} cartões para o green. Pouco tempo disponível.";
            } else {
                $gaugePct = max(20, min(65, round(60 - ($totalCards / $lineVal) * 40)));
                $faixaCor = ($gaugePct >= 50) ? 'amarelo' : 'verde';
                $isCashoutMoment = false;
                $alertaTitulo = ($gaugePct >= 50) ? '🟡 Buscando Cartões' : '🟢 Ritmo Disciplinar Ativo';
                $alertaMsg = "{$totalCards} cartões aplicados aos {$elapsed}'. Faltam {$faltamCards} para a meta.";
            }

            return new SituationalResult(
                (int)$gaugePct,
                $faixaCor,
                $isCashoutMoment,
                $alertaTitulo,
                $alertaMsg,
                $distanciaLinha,
                (float)$totalCards,
                'OVER_BUSCANDO',
                $detalheTatico
            );
        }
    }
}
