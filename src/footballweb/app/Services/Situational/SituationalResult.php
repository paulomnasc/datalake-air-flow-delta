<?php

namespace App\Services\Situational;

/**
 * SituationalResult - DTO Imutável com o resultado da avaliação situacional
 * Representa o estado instantâneo do jogo em relação à linha da aposta.
 */
class SituationalResult
{
    private int $gaugePct;
    private string $faixaCor;
    private bool $isCashoutMoment;
    private string $alertaTitulo;
    private string $alertaMsg;
    private float $distanciaLinha;
    private float $saldo;
    private string $estadoResultado;
    private string $detalheTatico;

    public function __construct(
        int $gaugePct,
        string $faixaCor,
        bool $isCashoutMoment,
        string $alertaTitulo,
        string $alertaMsg,
        float $distanciaLinha = 0.0,
        float $saldo = 0.0,
        string $estadoResultado = 'NEUTRO',
        string $detalheTatico = ''
    ) {
        $this->gaugePct = max(0, min(100, $gaugePct));
        $this->faixaCor = $faixaCor;
        $this->isCashoutMoment = $isCashoutMoment;
        $this->alertaTitulo = $alertaTitulo;
        $this->alertaMsg = $alertaMsg;
        $this->distanciaLinha = $distanciaLinha;
        $this->saldo = $saldo;
        $this->estadoResultado = $estadoResultado;
        $this->detalheTatico = $detalheTatico;
    }

    public function getGaugePct(): int { return $this->gaugePct; }
    public function getFaixaCor(): string { return $this->faixaCor; }
    public function isCashoutMoment(): bool { return $this->isCashoutMoment; }
    public function getAlertaTitulo(): string { return $this->alertaTitulo; }
    public function getAlertaMsg(): string { return $this->alertaMsg; }
    public function getDistanciaLinha(): float { return $this->distanciaLinha; }
    public function getSaldo(): float { return $this->saldo; }
    public function getEstadoResultado(): string { return $this->estadoResultado; }
    public function getDetalheTatico(): string { return $this->detalheTatico; }

    public function toArray(): array
    {
        return [
            'gauge_pct'          => $this->gaugePct,
            'faixa_cor'          => $this->faixaCor,
            'is_cashout_moment'  => $this->isCashoutMoment,
            'alerta_titulo'      => $this->alertaTitulo,
            'alerta_msg'         => $this->alertaMsg,
            'distancia_linha'    => $this->distanciaLinha,
            'saldo'              => $this->saldo,
            'estado_resultado'   => $this->estadoResultado,
            'detalhe_tatico'     => $this->detalheTatico,
        ];
    }
}
