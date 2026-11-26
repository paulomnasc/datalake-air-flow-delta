import unittest
from unittest.mock import MagicMock
import sys
import os

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
SCRIPTS_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '..', 'scripts'))
if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

from asian_handicap_engine import (
    calculate_bivariate_poisson_matrix,
    evaluate_and_select_best_ah_candidate,
    calculate_unified_handicap_recommendation
)
from criar_apostas_cloudbet_diario import get_tactical_reset_context_for_fixture


class TestTacticalResetResilience(unittest.TestCase):

    def test_get_tactical_reset_context_extraction(self):
        cursor = MagicMock()
        cursor.fetchone.return_value = {
            'tipo_evento': 'REVERSAO_TATICA_SUMULA',
            'motivo_detalhado': (
                "Sparta Praha x AZ Alkmaar [Reset Tático por Súmula]: A súmula oficial e o mercado geraram uma nova "
                "oportunidade de valor! Entrada sugerida: AZ Alkmaar 0.0 AH @ 1.79 (Probabilidade: 82.0%, EV: +46.8%). "
                "Sparta Praha com time misto (4 alterações nos 11 titulares) e 9 desfalques | ⚠️ Ordem Cloudbet NÃO enviada."
            ),
            'criado_em': '2026-11-26 16:36:13'
        }

        ctx = get_tactical_reset_context_for_fixture(cursor, 1636336, 'Sparta Praha', 'AZ Alkmaar')
        self.assertIsNotNone(ctx)
        self.assertEqual(ctx['time_desfalcado'], 'Sparta Praha')
        self.assertEqual(ctx['tipo'], 'SUMULA')

    def test_sweet_spot_expansion_for_tactical_reset(self):
        # xG ajustado por súmula
        poisson = calculate_bivariate_poisson_matrix(0.39, 0.57)

        lines = [
            {'line': 0.5, 'odd': 2.15, 'is_away': True, 'palpite_str': 'AZ Alkmaar +0.5 AH', 'target_team': 'AZ Alkmaar', 'market_url': 'test_url'},
            {'line': -0.5, 'odd': 1.70, 'is_away': False, 'palpite_str': 'Sparta Praha -0.5 AH', 'target_team': 'Sparta Praha', 'market_url': 'test_url_2'}
        ]

        tactical_ctx = {'time_desfalcado': 'Sparta Praha', 'tipo': 'SUMULA', 'motivo': 'Sparta com 9 desfalques'}

        u5j_h = {'v': 2, 'e': 1, 'd': 2, 'pts': 7, 'matches': [{'result': 'V'}, {'result': 'E'}, {'result': 'D'}, {'result': 'D'}, {'result': 'V'}]}
        u5j_a = {'v': 3, 'e': 1, 'd': 1, 'pts': 10, 'matches': [{'result': 'V'}, {'result': 'V'}, {'result': 'E'}, {'result': 'D'}, {'result': 'V'}]}

        best, approved = evaluate_and_select_best_ah_candidate(
            poisson, lines, 'Sparta Praha', 'AZ Alkmaar', 1.70, 4.55,
            min_ev=5.0, min_prob=50.0,
            home_last5=u5j_h,
            away_last5=u5j_a,
            desfalques_criticos=tactical_ctx
        )

        self.assertIsNotNone(best)
        self.assertEqual(best['palpite_str'], 'AZ Alkmaar +0.5 AH')
        self.assertEqual(best['odd'], 2.15)
        self.assertEqual(best.get('gatekeeper_category'), 'Reset Tático por Súmula')
        self.assertGreater(best['eval']['ev_percent'], 50.0)

    def test_no_ceiling_for_positive_lines_with_severe_desfalques(self):
        # Odds infladas acima de 2.25 (ex: @ 2.50) em linhas de cobertura positiva (+0.5 AH)
        # devem ser aceitas normalmente sob desfalques confirmados (teto ilimitado)
        poisson = calculate_bivariate_poisson_matrix(0.39, 0.57)

        lines = [
            {'line': 0.5, 'odd': 2.50, 'is_away': True, 'palpite_str': 'AZ Alkmaar +0.5 AH', 'target_team': 'AZ Alkmaar', 'market_url': 'test_url_inflada'},
        ]

        tactical_ctx = {'time_desfalcado': 'Sparta Praha', 'tipo': 'SUMULA', 'motivo': 'Sparta com 9 desfalques'}
        u5j_h = {'v': 2, 'e': 1, 'd': 2, 'pts': 7, 'matches': [{'result': 'V'}, {'result': 'E'}, {'result': 'D'}, {'result': 'D'}, {'result': 'V'}]}
        u5j_a = {'v': 3, 'e': 1, 'd': 1, 'pts': 10, 'matches': [{'result': 'V'}, {'result': 'V'}, {'result': 'E'}, {'result': 'D'}, {'result': 'V'}]}

        best, approved = evaluate_and_select_best_ah_candidate(
            poisson, lines, 'Sparta Praha', 'AZ Alkmaar', 1.70, 4.55,
            min_ev=5.0, min_prob=50.0,
            home_last5=u5j_h,
            away_last5=u5j_a,
            desfalques_criticos=tactical_ctx
        )

        self.assertIsNotNone(best)
        self.assertEqual(best['palpite_str'], 'AZ Alkmaar +0.5 AH')
        self.assertEqual(best['odd'], 2.50)
        self.assertEqual(best.get('gatekeeper_category'), 'Reset Tático por Súmula')
        self.assertGreater(best['eval']['ev_percent'], 80.0)

    def test_standard_match_preserves_195_ceiling(self):
        # Partida comum sem desfalques críticos deve manter teto de 1.95 para evitar apostas esticadas
        poisson = calculate_bivariate_poisson_matrix(1.5, 1.2)
        lines = [
            {'line': 0.5, 'odd': 2.15, 'is_away': True, 'palpite_str': 'Time Fora +0.5 AH', 'target_team': 'Time Fora', 'market_url': 'test_url'},
        ]
        u5j = {'v': 3, 'e': 1, 'd': 1, 'pts': 10, 'matches': [{'result': 'V'}, {'result': 'V'}, {'result': 'E'}, {'result': 'D'}, {'result': 'V'}]}

        best, approved = evaluate_and_select_best_ah_candidate(
            poisson, lines, 'Time Casa', 'Time Fora', 1.90, 3.80,
            min_ev=5.0, min_prob=50.0,
            home_last5=u5j,
            away_last5=u5j,
            desfalques_criticos=None
        )

        self.assertIsNone(best)
        self.assertEqual(len(approved), 0)


if __name__ == '__main__':
    unittest.main()
