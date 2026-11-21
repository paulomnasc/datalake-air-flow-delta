<?php

namespace App\Services\Situational;

/**
 * SituationalEvaluatorInterface - Contrato para avaliadores situacionais por mercado
 */
interface SituationalEvaluatorInterface
{
    /**
     * Avalia a situação do jogo em tempo real em relação à linha da aposta.
     *
     * @param object $bet Dados da aposta (id, palpite, mercado, odd, time_casa, time_fora, etc.)
     * @param array $matchContext Contexto do jogo ao vivo (placar, elapsed, status, cartões, estatísticas)
     * @return SituationalResult
     */
    public function evaluate(object $bet, array $matchContext): SituationalResult;
}
