<?php

namespace App\Services\Situational;

/**
 * AsianHandicapSituationalEvaluator
 *
 * Avalia o estado situacional para mercados de Handicap Asiático (AH) e Draw No Bet (DNB)
 * baseado na distância matemática e direção do resultado real em relação à linha apostada (R = ΔG + L).
 */
class AsianHandicapSituationalEvaluator implements SituationalEvaluatorInterface
{
    public function evaluate(object $bet, array $matchContext): SituationalResult
    {
        $palpite = (string)($bet->palpite ?? '');
        $homeTeam = (string)($bet->time_casa ?? $matchContext['home_team'] ?? 'Mandante');
        $awayTeam = (string)($bet->time_fora ?? $matchContext['away_team'] ?? 'Visitante');

        $gh = (int)($matchContext['goals_home'] ?? 0);
        $ga = (int)($matchContext['goals_away'] ?? 0);
        $elapsed = (int)($matchContext['elapsed'] ?? 0);
        $status = strtoupper(trim((string)($matchContext['fixture_status'] ?? 'NS')));
        $isLive = (bool)($matchContext['is_live'] ?? false);

        $rcHome = (int)($matchContext['red_cards_home'] ?? 0);
        $rcAway = (int)($matchContext['red_cards_away'] ?? 0);

        // Identifica time apostado
        $isHomeFav = (stripos($palpite, $homeTeam) !== false);
        $diffGols = $isHomeFav ? ($gh - $ga) : ($ga - $gh);
        $myRedCards = $isHomeFav ? $rcHome : $rcAway;
        $oppRedCards = $isHomeFav ? $rcAway : $rcHome;

        // Extrai a linha do palpite (ex: -0.25, 0.0, +0.5, +1.0)
        $lineVal = 0.0;
        if (preg_match('/([+-]?\d+(?:\.\d+)?)/', $palpite, $m)) {
            $lineVal = (float)$m[1];
        }

        $isDnbLine = (abs($lineVal) < 0.01 || stripos($palpite, '0.0') !== false || stripos($palpite, 'dnb') !== false || stripos($palpite, 'empate anula') !== false);

        // Se a partida ainda não iniciou (Pré-Jogo)
        if (!$isLive && in_array($status, ['NS', 'TBD', 'POSTP'])) {
            return new SituationalResult(
                10,
                'verde',
                false,
                '⏳ Aguardando Início',
                'Partida ainda não iniciada. Velocímetro em standby de pré-jogo.',
                $lineVal,
                0.0,
                'PRE_JOGO',
                'Aposta confirmada aguardando o apito inicial.'
            );
        }

        // Saldo e Detalhe Tático
        $saldoStr = ($diffGols > 0 ? "+{$diffGols}" : "{$diffGols}");
        $detalheTatico = "Placar: {$gh}x{$ga} ({$elapsed}') • Saldo: {$saldoStr}";

        // Resultado instantâneo em relação à linha: R = ΔG + L
        $resultadoLinha = $diffGols + $lineVal;

        $gaugePct = 20;
        $faixaCor = 'verde';
        $isCashoutMoment = false;
        $alertaTitulo = '🟢 Em Vantagem';
        $alertaMsg = "Cenário favorável no handicap aos {$elapsed}'.";
        $estadoResultado = 'GREEN_TOTAL';

        // 1. Cenário de PUSH (Reembolso 100% / Capital Protegido)
        if (abs($resultadoLinha) < 0.001) {
            // Em linha 0.0 AH ou linha inteira empatada, capital está 100% intacto
            $estadoResultado = 'PUSH_REEMBOLSO';
            $isCashoutMoment = false;

            if ($elapsed >= 80) {
                $gaugePct = 15;
                $faixaCor = 'verde';
                $alertaTitulo = '🟢 Reembolso Garantido (Push Protegido)';
                $alertaMsg = "Empate aos {$elapsed}'. Na linha 0.0 AH, sua aposta está 100% protegida com reembolso total se o placar mantiver!";
            } else {
                $gaugePct = 20;
                $faixaCor = 'verde';
                $alertaTitulo = '🟢 Empate (Capital 100% Protegido / Devolução)';
                $alertaMsg = "Partida empatada aos {$elapsed}'. Linha 0.0 AH em zona de devolução/reembolso integral sem risco de perda financeira.";
            }

            // Alerta preventivo se o time apostado teve expulsão
            if ($myRedCards > 0 && $elapsed >= 46) {
                $gaugePct = 40;
                $faixaCor = 'amarelo';
                $alertaTitulo = '🟡 Atenção: Expulsão com Placar Empatado';
                $alertaMsg = "Empate aos {$elapsed}', mas o time apostado tem cartão vermelho. Monitorando risco de pressão adversária.";
            }
        }
        // 2. Cenário de GREEN TOTAL (R >= +0.5)
        elseif ($resultadoLinha >= 0.5) {
            $estadoResultado = 'GREEN_TOTAL';
            $isCashoutMoment = false;
            $faixaCor = 'verde';

            if ($diffGols >= 2) {
                $gaugePct = 10;
                $alertaTitulo = '🟢 Vantagem Confortável';
                $alertaMsg = "Equipe liderando com folga no placar ({$gh}x{$ga} aos {$elapsed}').";
            } else {
                if ($elapsed >= 80) {
                    $gaugePct = 15;
                    $alertaTitulo = '🟢 Lucro Iminente (Reta Final)';
                    $alertaMsg = "Vencendo por 1 gol aos {$elapsed}'. Segure até o fim para consolidar o Green total!";
                } else {
                    $gaugePct = 20;
                    $alertaTitulo = '🟢 Em Vantagem';
                    $alertaMsg = "Liderando por 1 gol aos {$elapsed}'. Cenário positivo.";
                }
            }
        }
        // 3. Cenário de MEIO GREEN (R == +0.25)
        elseif (abs($resultadoLinha - 0.25) < 0.001) {
            $estadoResultado = 'MEIO_GREEN';
            $isCashoutMoment = false;
            $faixaCor = 'verde';
            $gaugePct = ($elapsed >= 80) ? 20 : 25;
            $alertaTitulo = '🟢 Meio Green Assegurado';
            $alertaMsg = "Posição favorável no handicap (+0.25 acima da linha). Meio lucro assegurado no momento ({$elapsed}').";
        }
        // 4. Cenário de MEIO RED (R == -0.25, ex: 0x0 em -0.25 AH)
        elseif (abs($resultadoLinha - (-0.25)) < 0.001) {
            $estadoResultado = 'MEIO_RED';

            if ($elapsed <= 45) {
                $gaugePct = 42;
                $faixaCor = 'amarelo';
                $isCashoutMoment = false;
                $alertaTitulo = '🟡 Meio Red Provisório (1º Tempo)';
                $alertaMsg = "Resultado momentâneo a meio gol da meta ({$elapsed}'). Primeiro tempo aberto com amplo tempo para buscar a vitória.";
            } elseif ($elapsed <= 74) {
                $gaugePct = 52;
                $faixaCor = 'amarelo';
                $isCashoutMoment = false;
                $alertaTitulo = '🟡 Atenção ao Ritmo (Buscando Gol)';
                $alertaMsg = "Empate aos {$elapsed}'. Linha -0.25 AH necessita de 1 gol para Green total. Equipe buscando o gol.";
            } else {
                // Reta final (75'+) em -0.25
                $gaugePct = 65;
                $faixaCor = 'cashout';
                $isCashoutMoment = true;
                $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Meio Red Reta Final)';
                $alertaMsg = "Reta final aos {$elapsed}'. Linha -0.25 em meio-red. Avalie fechamento ou hedge para salvar 50% da stake!";
            }
        }
        // 5. Cenário de RED TOTAL (R <= -0.5, ex: perdendo por 1 em 0.0 ou empatando em -0.5)
        else {
            $estadoResultado = 'RED_TOTAL';

            if ($diffGols <= -2) {
                // Desvantagem de 2 ou mais gols (risco crítico)
                $gaugePct = 90;
                $faixaCor = 'vermelho';
                $isCashoutMoment = ($elapsed >= 30);
                $alertaTitulo = '🔴 Em Desvantagem Crítica (2+ Gols)';
                $alertaMsg = "Equipe em desvantagem de 2+ gols no placar ({$gh}x{$ga} aos {$elapsed}'). Posição sob alto risco de perda total.";
            } elseif ($diffGols == -1) {
                // Desvantagem de 1 gol
                if ($elapsed <= 45) {
                    $gaugePct = 55;
                    $faixaCor = 'amarelo';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟡 Desvantagem Precoce (Tempo de Reação)';
                    $alertaMsg = "Desvantagem mínima ({$gh}x{$ga}) aos {$elapsed}'. Primeiro tempo em andamento: ampla margem e tempo no relógio para buscar empate ou virada.";
                } elseif ($elapsed <= 75) {
                    $gaugePct = 70;
                    $faixaCor = 'amarelo';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟡 Buscando Reação';
                    $alertaMsg = "Desvantagem de 1 gol ({$gh}x{$ga}) aos {$elapsed}'. Equipe pressionando pelo resultado.";
                } else {
                    // Reta final (76'+)
                    $gaugePct = 88;
                    $faixaCor = 'vermelho';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Reta Final em Desvantagem)';
                    $alertaMsg = "Desvantagem de 1 gol aos {$elapsed}'. Pouco tempo restante no relógio para reversão. Acionamento de Hedge recomendado.";
                }
            } else {
                // Empate em linha negativa severa (-0.5, -0.75, -1.0)
                if ($elapsed <= 60) {
                    $gaugePct = 48;
                    $faixaCor = 'amarelo';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟡 Jogo Empatado (Buscando Vantagem)';
                    $alertaMsg = "Partida empatada ({$gh}x{$ga}) aos {$elapsed}'. Linha {$lineVal} AH necessita de gol para Green.";
                } elseif ($elapsed <= 75) {
                    $gaugePct = 60;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = false;
                    $alertaTitulo = '🟡 Empate no 2º Tempo';
                    $alertaMsg = "Empate aos {$elapsed}'. Tempo escasseando para cobrir a linha {$lineVal} AH.";
                } else {
                    $gaugePct = 75;
                    $faixaCor = 'cashout';
                    $isCashoutMoment = true;
                    $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Empate Reta Final)';
                    $alertaMsg = "Empate aos {$elapsed}'. Linha de vitória {$lineVal} AH não atingida na reta final. Avalie fechar e proteger a banca!";
                }
            }

            // Se time apostado com vermelho no 2T, eleva severidade
            if ($myRedCards > 0 && $elapsed >= 46) {
                $gaugePct = min(98, $gaugePct + 15);
                $isCashoutMoment = true;
                $alertaTitulo = '⚡ MOMENTO DO CASHOUT (Expulsão em Desvantagem)';
            }
        }

        return new SituationalResult(
            $gaugePct,
            $faixaCor,
            $isCashoutMoment,
            $alertaTitulo,
            $alertaMsg,
            $resultadoLinha,
            (float)$diffGols,
            $estadoResultado,
            $detalheTatico
        );
    }
}
