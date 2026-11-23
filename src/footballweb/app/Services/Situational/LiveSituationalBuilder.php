<?php

namespace App\Services\Situational;

/**
 * LiveSituationalBuilder (Builder)
 *
 * Constrói o contexto completo da partida e orquestra a avaliação situacional,
 * retornando a estrutura padronizada para o dashboard, controller e API.
 */
class LiveSituationalBuilder
{
    private ?object $betRow = null;
    private array $customContext = [];

    public static function create(): self
    {
        return new self();
    }

    public function withBetRow(object $row): self
    {
        $this->betRow = $row;
        return $this;
    }

    public function withContext(array $context): self
    {
        $this->customContext = array_merge($this->customContext, $context);
        return $this;
    }

    /**
     * Executa o build da avaliação situacional completa
     */
    public function build(): array
    {
        if (!$this->betRow) {
            throw new \InvalidArgumentException("BetRow não fornecido para LiveSituationalBuilder.");
        }

        $row = $this->betRow;

        $gh = isset($row->goals_home) && $row->goals_home !== null ? (int)$row->goals_home : 0;
        $ga = isset($row->goals_away) && $row->goals_away !== null ? (int)$row->goals_away : 0;
        $elapsed = isset($row->elapsed) && $row->elapsed !== null ? (int)$row->elapsed : 0;
        $status = strtoupper(trim((string)($row->fixture_status ?? 'NS')));
        $isLive = in_array($status, ['1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED']);

        // Compensação dinâmica contínua para eliminar lag entre ciclos de sincronização (com timezone America/Sao_Paulo)
        $refUpdated = !empty($row->fixture_updated_at) ? $row->fixture_updated_at : ($row->updated_at ?? null);
        if ($isLive && in_array($status, ['1H', '2H', 'LIVE']) && !empty($refUpdated)) {
            try {
                $lastSync = new \DateTime($refUpdated, new \DateTimeZone('America/Sao_Paulo'));
                $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                $diffSeconds = $now->getTimestamp() - $lastSync->getTimestamp();
                if ($diffSeconds > 75 && $diffSeconds < 2700) {
                    $minutosExtras = (int)floor($diffSeconds / 60);
                    $novoElapsed = $elapsed + $minutosExtras;
                    if ($status === '1H') {
                        $elapsed = min(48, $novoElapsed);
                    } elseif ($status === '2H' || $status === 'LIVE') {
                        $elapsed = min(98, $novoElapsed);
                    }
                }
            } catch (\Exception $e) {}
        }

        $ycH = (int)($row->yellow_cards_home ?? 0);
        $ycA = (int)($row->yellow_cards_away ?? 0);
        $rcH = (int)($row->red_cards_home ?? 0);
        $rcA = (int)($row->red_cards_away ?? 0);
        $totalCards = $ycH + $ycA + (($rcH + $rcA) * 2);

        $cornersH = (int)($row->corners_home ?? 0);
        $cornersA = (int)($row->corners_away ?? 0);

        // Status visual do jogo
        $statusLabel = 'Pré-Jogo';
        $statusBadge = 'bg-secondary';
        if ($isLive) {
            if ($status === 'HT') {
                $statusLabel = 'Intervalo (HT)';
                $statusBadge = 'bg-warning text-dark';
            } elseif ($status === '1H') {
                $statusLabel = "1º Tempo • {$elapsed}'";
                $statusBadge = 'bg-success';
            } elseif ($status === '2H') {
                $statusLabel = "2º Tempo • {$elapsed}'";
                $statusBadge = 'bg-danger pulse-live';
            } else {
                $statusLabel = "Ao Vivo • {$elapsed}'";
                $statusBadge = 'bg-danger pulse-live';
            }
        } elseif (in_array($status, ['FT', 'AET', 'PEN'])) {
            $statusLabel = 'Encerrado (FT)';
            $statusBadge = 'bg-dark';
        }

        // Formatação de Data e Hora da Partida (Convertido de UTC para America/Sao_Paulo)
        $rawDate = !empty($row->fixture_date) ? $row->fixture_date : ($row->data_hora_jogo ?? null);
        $dataHoraJogoFmt = 'Data a Definir';
        if (!empty($rawDate)) {
            try {
                $dt = new \DateTime($rawDate, new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                $dataHoraJogoFmt = $dt->format('d/m/Y • H:i');
            } catch (\Exception $e) {
                $dataHoraJogoFmt = date('d/m/Y • H:i', strtotime($rawDate));
            }
        }

        $matchContext = array_merge([
            'goals_home'         => $gh,
            'goals_away'         => $ga,
            'elapsed'            => $elapsed,
            'fixture_status'     => $status,
            'is_live'            => $isLive,
            'yellow_cards_home'  => $ycH,
            'yellow_cards_away'  => $ycA,
            'red_cards_home'     => $rcH,
            'red_cards_away'     => $rcA,
            'corners_home'       => $cornersH,
            'corners_away'       => $cornersA,
            'shots_home'         => (int)($row->shots_home ?? 0),
            'shots_away'         => (int)($row->shots_away ?? 0),
            'home_team'          => (string)($row->time_casa ?? $row->home_team ?? 'Mandante'),
            'away_team'          => (string)($row->time_fora ?? $row->away_team ?? 'Visitante'),
        ], $this->customContext);

        $mercado = (string)($row->mercado ?? 'Handicap Asiático');
        $palpite = (string)($row->palpite ?? '');

        // Obtém o avaliador adequado via Abstract Factory
        $evaluator = SituationalEvaluatorFactory::create($mercado, $palpite);
        $situational = $evaluator->evaluate($row, $matchContext);

        // Formatação financeira e de apresentação
        $odd = (float)($row->odd ?? 1.0);
        $stake = (float)($row->valor_aposta ?? 0.0);
        $retorno = round($stake * $odd, 2);
        $moeda = (string)($row->moeda_codigo ?? 'USDT');

        return [
            'aposta_id'          => (int)$row->id,
            'fixture_id'         => (int)($row->fixture_id ?? 0),
            'home_team_id'       => (int)($row->home_team_id ?? 0),
            'away_team_id'       => (int)($row->away_team_id ?? 0),
            'league_id'          => (int)($row->league_id ?? 0),
            'data_hora_jogo_fmt' => $dataHoraJogoFmt,
            'time_casa'          => (string)($row->time_casa ?? 'Mandante'),
            'time_fora'          => (string)($row->time_fora ?? 'Visitante'),
            'league_name'        => (string)($row->league_name ?? 'Competição'),
            'mercado'            => $mercado,
            'palpite'            => $palpite,
            'odd'                => $odd,
            'valor_aposta'       => $stake,
            'moeda'              => $moeda,
            'retorno_potencial'  => $retorno,
            'placar'             => "{$gh} x {$ga}",
            'goals_home'         => $gh,
            'goals_away'         => $ga,
            'elapsed'            => $elapsed,
            'fixture_status'     => $status,
            'status_label'       => $statusLabel,
            'status_badge'       => $statusBadge,
            'is_live'            => $isLive,
            'gauge_pct'          => $situational->getGaugePct(),
            'faixa_cor'          => $situational->getFaixaCor(),
            'is_cashout_moment'  => $situational->isCashoutMoment(),
            'alerta_titulo'      => $situational->getAlertaTitulo(),
            'alerta_msg'         => $situational->getAlertaMsg(),
            'alerta_mensagem'    => $situational->getAlertaMsg(),
            'detalhe_tatico'     => $situational->getDetalheTatico(),
            'total_cards'        => $totalCards,
            'corners_total'      => $cornersH + $cornersA,
            'xg_home'            => (float)($row->xg_home ?? 0.0),
            'xg_away'            => (float)($row->xg_away ?? 0.0),
            'estado_resultado'   => $situational->getEstadoResultado(),
            'distancia_linha'    => $situational->getDistanciaLinha(),
            'saldo'              => $situational->getSaldo(),
            'updated_at'         => date('H:i:s'),
        ];
    }
}
