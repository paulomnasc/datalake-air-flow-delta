<?php

namespace App\Services\Situational;

/**
 * SituationalEvaluatorFactory (Abstract Factory)
 *
 * Instancia o avaliador situacional adequado com base no mercado da aposta.
 */
class SituationalEvaluatorFactory
{
    private static array $evaluators = [];

    /**
     * Retorna a instância do avaliador correto para o mercado
     */
    public static function create(string $mercado, string $palpite = ''): SituationalEvaluatorInterface
    {
        $mLower = strtolower($mercado);
        $pLower = strtolower($palpite);

        if (strpos($mLower, 'cart') !== false || strpos($pLower, 'cart') !== false) {
            $key = 'cards';
            if (!isset(self::$evaluators[$key])) {
                self::$evaluators[$key] = new CardsSituationalEvaluator();
            }
            return self::$evaluators[$key];
        }

        if (strpos($mLower, 'gol') !== false || strpos($mLower, 'total') !== false || strpos($pLower, 'mais de') !== false || strpos($pLower, 'menos de') !== false) {
            $key = 'goals';
            if (!isset(self::$evaluators[$key])) {
                self::$evaluators[$key] = new GoalsSituationalEvaluator();
            }
            return self::$evaluators[$key];
        }

        // Padrão: Handicap Asiático / 1X2 / DNB
        $key = 'asian_handicap';
        if (!isset(self::$evaluators[$key])) {
            self::$evaluators[$key] = new AsianHandicapSituationalEvaluator();
        }
        return self::$evaluators[$key];
    }
}
