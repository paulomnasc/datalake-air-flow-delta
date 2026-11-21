<?php

namespace App\Services\Situational;

/**
 * GoalsSituationalEvaluator
 *
 * Avalia o estado situacional para mercados de Total de Gols (Over e Under, fracionados e inteiros).
 */
class GoalsSituationalEvaluator implements SituationalEvaluatorInterface
{
    public function evaluate(object $bet, array $matchContext): SituationalResult
    {
        $palpite = (string)($bet->palpite ?? '');
        $gh = (int)($matchContext['goals_home'] ?? 0);
        $ga = (int)($matchContext['goals_away'] ?? 0);
        $elapsed = (int)($matchContext['elapsed'] ?? 0);
        $status = strtoupper(trim((string)($matchContext['fixture_status'] ?? 'NS')));
        $isLive = (bool)($matchContext['is_live'] ?? false);

        $totalGols = $gh + $ga;

        // Extrai a linha de gols (ex: 2.5, 2.0, 3.5)
        $lineVal = 2.5;
        if (preg_match('/(\d+(?:\.\d+)?)/', $palpite, $m)) {
            $lineVal = (float)$m[1];
        }

        $isLineInteger = (floor($lineVal) == $lineVal);
        $isUnder = (stripos($palpite, 'menos') !== false || stripos($palpite, 'under') !== false);
        $detalheTatico = "Placar: {$gh}x{$ga} ({$elapsed}') • Total: {$totalGols} gols";

        // Se a partida ainda não iniciou (Pré-Jogo)
        if (!$isLive && in_array($status, ['NS', 'TBD', 'POSTP'])) {
            return new SituationalResult(
                10,
                'verde',
                false,
                '⏳ Aguardando Início',
                'Partida ainda não iniciada. Velocímetro em standby de pré-jogo.',
                $lineVal,
                (float)$totalGols,
                'PRE_JOGO',
                'Aposta confirmada aguardando o apito inicial.'
            );
        }

        $distanciaLinha = $lineVal - $totalGols;

        if ($isUnder) {
            // MERCADO UNDER GOLS
            $sobraGols = $lineVal - $totalGols;

            if ($totalGols > $lineVal) {
                // Estourou a linha -> Red Total
                return new SituationalResult(
                    95,
                    'vermelho',
                    false,
                    '🔴 Limite de Gols Estourado',
                    "{$totalGols} gols marcados aos {$elapsed}'. Limite de {$lineVal} gols ultrapassado.",
                    $distanciaLinha,
                    (float)$totalGols,
                    'RED_TOTAL',
                    $detalheTatico
                );
            }

            if ($isLineInteger && $totalGols == (int)$lineVal) {
                // Linha inteira empatada (ex: 2 gols em Menos de 2.0) -> PUSH / REEMBOLSO GARANTIDO
                $gaugePct = ($elapsed >= 80) ? 15 : 25;
                return new SituationalResult(
                    $gaugePct,
                    'verde',
                    false,
                    '🟢 Reembolso Garantido (Push Protegido)',
                    "Total de {$totalGols} gols aos {$elapsed}'. Linha inteira empatada: stake 100% protegida se não saírem mais gols!",
                    $distanciaLinha,
                    (float)$totalGols,
                    'PUSH_REEMBOLSO',
                    $detalheTatico
                );
            }

            if ($sobraGols <= 0.5) {
                // A 1 gol do estouro
                if ($elapsed >= 82) {
                    $gaugePct = 35;
                    $faixaCor = 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟢 Segure até o Fim (Reta Final)';
                    $alertaMsg = "{$totalGols} gols aos {$elapsed}'. Reta final: deixe o relógio correr para consolidar o Green!";
                } else {
                    $gaugePct = 60;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT';
                    $alertaMsg = "{$totalGols} gols marcados aos {$elapsed}'. Apenas 1 gol de margem de segurança. Avalie cashout preventivo!";
                }
            } else {
                $gaugePct = max(10, round(35 - ($elapsed / 90) * 25));
                $faixaCor = 'verde';
                $isCashoutMoment = false;
                $alertaTitulo = '🟢 Sob Controle';
                $alertaMsg = "{$totalGols} gol(s) marcado(s) aos {$elapsed}'. Restam {$sobraGols} gols de proteção até o apito final.";
            }

            return new SituationalResult(
                (int)$gaugePct,
                $faixaCor,
                $isCashoutMoment,
                $alertaTitulo,
                $alertaMsg,
                $distanciaLinha,
                (float)$totalGols,
                'UNDER_CONTROLE',
                $detalheTatico
            );
        } else {
            // MERCADO OVER GOLS
            if ($isLineInteger && $totalGols == (int)$lineVal) {
                // Push garantido na linha inteira
                return new SituationalResult(
                    15,
                    'verde',
                    false,
                    '🟢 Reembolso Garantido (Push Protegido)',
                    "Total de {$totalGols} gols aos {$elapsed}'. Linha inteira empatada: stake 100% protegida com reembolso se não saírem mais gols!",
                    $distanciaLinha,
                    (float)$totalGols,
                    'PUSH_REEMBOLSO',
                    $detalheTatico
                );
            }

            if (($isLineInteger && $totalGols > (int)$lineVal) || (!$isLineInteger && $totalGols >= ceil($lineVal))) {
                // Meta batida
                return new SituationalResult(
                    5,
                    'verde',
                    false,
                    '🟢 Meta Batida (Green Total)',
                    "Já saíram {$totalGols} gols aos {$elapsed}'! Aposta liquidada com vitória!",
                    $distanciaLinha,
                    (float)$totalGols,
                    'GREEN_TOTAL',
                    $detalheTatico
                );
            }

            if ($totalGols == ($isLineInteger ? ((int)$lineVal - 1) : floor($lineVal))) {
                // Falta 1 gol
                if ($elapsed <= 45) {
                    $gaugePct = 20;
                    $faixaCor = 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟢 Ritmo Ofensivo (Gol Precoce)';
                    $alertaMsg = "Já saiu {$totalGols} gol aos {$elapsed}'! Ritmo acelerado com amplo tempo restante para buscar a meta.";
                } elseif ($elapsed <= 75) {
                    $gaugePct = 35;
                    $faixaCor = 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟢 A 1 Gol da Meta';
                    $alertaMsg = "Partida com {$totalGols} gols aos {$elapsed}'. Falta apenas 1 gol para o green!";
                } else {
                    $gaugePct = 60;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT';
                    $alertaMsg = "Reta final de jogo ({$elapsed}'). Falta 1 gol para a meta. Avalie cashout para travar retorno parcial!";
                }
            } else {
                if ($elapsed >= 70) {
                    $gaugePct = 85;
                    $faixaCor = 'vermelho';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Ritmo Lento)';
                    $alertaMsg = "Apenas {$totalGols} gols aos {$elapsed}'. Tempo escasseando para cobrir a linha Over {$lineVal}.";
                } else {
                    $gaugePct = max(25, min(70, round(65 - ($totalGols / $lineVal) * 40)));
                    $faixaCor = ($gaugePct >= 50) ? 'amarelo' : 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = ($gaugePct >= 50) ? '🟡 Buscando Gols' : '🟢 Jogo Aberto';
                    $alertaMsg = "Partida com {$totalGols} gols aos {$elapsed}'. Buscando meta Over {$lineVal}.";
                }
            }

            return new SituationalResult(
                (int)$gaugePct,
                $faixaCor,
                $isCashoutMoment,
                $alertaTitulo,
                $alertaMsg,
                $distanciaLinha,
                (float)$totalGols,
                'OVER_BUSCANDO',
                $detalheTatico
            );
        }
    }
}
