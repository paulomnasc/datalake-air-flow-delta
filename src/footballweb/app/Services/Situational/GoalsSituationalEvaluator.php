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

            // Se o placar atingiu 80% do limite de gols OU sobra <= 0.5 (a 1 gol de estourar a linha)
            $progressoUnder = ($lineVal > 0) ? ($totalGols / $lineVal) : 0.0;
            if ($sobraGols <= 0.5 || $progressoUnder >= 0.80) {
                // A 1 gol do estouro ou >= 80% do limite
                if ($elapsed >= 80) {
                    $gaugePct = 35;
                    $faixaCor = 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟢 Segure até o Fim (Reta Final)';
                    $alertaMsg = "{$totalGols} gols aos {$elapsed}'. Reta final: deixe o relógio correr para consolidar o Green!";
                } elseif ($elapsed >= 46 && $elapsed <= 79) {
                    // Janela Nobre de Cash Out (46' a 79' - Meio de Jogo)
                    $gaugePct = 60;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ JANELA NOBRE DE CASHOUT (A 1 Gol do Limite)';
                    $alertaMsg = "{$totalGols} gols marcados aos {$elapsed}' (próximo a 80% do limite de {$lineVal} gols). Momento ideal de agir na Cloudbet para resgate de capital!";
                } else {
                    // 1º tempo com risco elevado
                    $gaugePct = 65;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = ($totalGols >= 2);
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
            $progressoOver = ($lineVal > 0) ? ($totalGols / $lineVal) : 0.0;

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
                // Falta 1 gol para a meta
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
                // Placar abaixo de 75% da meta de Over (ex: 0x0 em Over 2.0 / 2.5)
                if ($elapsed >= 46 && $elapsed <= 79 && $progressoOver < 0.75) {
                    // Janela Nobre de Cash Out (46' a 79' - Meio de Jogo)
                    $gaugePct = ($elapsed >= 65) ? 85 : 70;
                    $faixaCor = ($elapsed >= 65) ? 'vermelho' : 'cashout';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ JANELA NOBRE DE CASHOUT (Ritmo Lento)';
                    $alertaMsg = "Apenas {$totalGols} gol(s) aos {$elapsed}' (abaixo de 75% do Over {$lineVal}). Momento ideal de agir na Cloudbet para resgate de capital com boas cotações!";
                } elseif ($elapsed >= 80) {
                    $gaugePct = 90;
                    $faixaCor = 'vermelho';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Reta Final)';
                    $alertaMsg = "Apenas {$totalGols} gols aos {$elapsed}'. Tempo escasseando para cobrir a linha Over {$lineVal}.";
                } elseif ($elapsed >= 35) {
                    // 35' a 45' do 1T
                    $gaugePct = max(35, min(55, round(55 - $progressoOver * 30)));
                    $faixaCor = ($gaugePct >= 50) ? 'amarelo' : 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = ($gaugePct >= 50) ? '🟡 Atenção ao Ritmo' : '🟢 Buscando Gols';
                    $alertaMsg = "Partida com {$totalGols} gols aos {$elapsed}'. Buscando meta Over {$lineVal}.";
                } else {
                    // Início da partida (0' a 34'): 0x0 ou ritmo inicial é comum
                    $tempoFator = $elapsed / 35.0;
                    $baseRisco = 15.0 + ($tempoFator * 15.0);
                    $gaugePct = max(10, min(35, round($baseRisco - ($progressoOver * 20.0))));
                    $faixaCor = 'verde';
                    $isCashoutMoment = false;
                    $alertaTitulo = ($totalGols == 0) ? '🟢 Início de Jogo' : '🟢 Gol Precoce';
                    $alertaMsg = "Partida com {$totalGols} gols aos {$elapsed}'. Amplo tempo restante para buscar a meta Over {$lineVal}.";
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
