#!/usr/bin/env python3
"""
tests/test_cloudbet_hedge_fulltime.py
Suíte de testes exaustiva de validação da blindagem anti-red duplo para TODOS os mercados de Cross-Hedge:
1. Handicap Asiático e Empate Anula a Aposta (DNB)
2. Total de Gols (Over / Under)
3. Total de Cartões (Under / Over)
4. Veto compulsório a Mercados Parciais e 'Rest of Match'
"""

import unittest
import os
import sys
from unittest.mock import MagicMock, patch

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(CURRENT_DIR, '..'))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from scripts.cloudbet_live_cashout import find_cloudbet_opposite_market_and_quote


class TestCloudbetHedgeFullTimeShield(unittest.TestCase):

    def setUp(self):
        self.mock_client = MagicMock()
        self.mock_client.dry_run = False

    # =========================================================================
    # SEÇÃO 1: HANDICAP ASIÁTICO & EMPATE ANULA A APOSTA (DNB)
    # =========================================================================

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_01_ah_veto_rest_of_the_match(self, mock_matcher_cls):
        """
        Cenário: Partida aos 76' com placar 0x1. Corretora oferece apenas 'which_team_wins_the_rest_of_the_match'.
        Comportamento Esperado: VETO ABSOLUTO (NO_HEDGE / None). O mercado zera o placar e exige outro gol.
        Evita o Red Duplo sofrido no jogo Slavia Praha x Villarreal.
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {},
            "draw_no_bet": {},
            "match_odds": {},
            "which_team_wins": {
                "away": {
                    "price": 5.50,
                    "market_url": "soccer.which_team_wins_the_rest_of_the_match/away?score=0:1",
                    "min_stake": 0.10
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Slavia Praha +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=0,
            goals_away=1
        )

        self.assertIsNone(opp_url, "Mercado 'which_team_wins_the_rest_of_the_match' DEVE ser sumariamente vetado!")

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_02_ah_veto_handicap_too_aggressive(self, mock_matcher_cls):
        """
        Cenário: Placar 0x1 (Villarreal lidera por 1 gol). Corretora tem apenas linha -1.5 AH para o Villarreal.
        Comportamento Esperado: VETO TOTAL (NO_HEDGE / None). A linha -1.5 exige 2 gols de vantagem;
        se o jogo terminar 0x1, a aposta original perde e o hedge também perde (Red Duplo).
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {
                -1.5: {
                    "away": {
                        "price": 1.95,
                        "market_url": "soccer.asian_handicap/away?handicap=-1.5",
                        "min_stake": 0.10
                    }
                }
            },
            "draw_no_bet": {},
            "match_odds": {}
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Slavia Praha +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=0,
            goals_away=1
        )

        self.assertIsNone(opp_url, "Linha -1.5 AH NÃO pode ser aceita em desvantagem de apenas 1 gol!")

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_03_ah_accept_safe_coverage_minus_half(self, mock_matcher_cls):
        """
        Cenário: Placar 0x1. Corretora oferece -0.5 AH (Vitória Seca FT) para o Villarreal.
        Comportamento Esperado: APROVADO! Se o jogo terminar 0x1, o Villarreal vence por 1 gol e a aposta paga.
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {
                -0.5: {
                    "away": {
                        "price": 1.35,
                        "market_url": "soccer.asian_handicap/away?handicap=-0.5",
                        "min_stake": 0.10
                    }
                }
            },
            "draw_no_bet": {},
            "match_odds": {}
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Slavia Praha +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=0,
            goals_away=1
        )

        self.assertIsNotNone(opp_url)
        self.assertEqual(opp_url, "soccer.asian_handicap/away?handicap=-0.5")
        self.assertEqual(price, 1.35)

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_04_ah_select_draw_no_bet_ft(self, mock_matcher_cls):
        """
        Cenário: Linha de Draw No Bet (0.0 AH FT) disponível para o adversário.
        Comportamento Esperado: APROVADO via Prioridade 0 com mercado 'soccer.draw_no_bet/away'.
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {},
            "draw_no_bet": {
                "away": {
                    "price": 1.45,
                    "market_url": "soccer.draw_no_bet/away",
                    "min_stake": 0.10
                }
            },
            "match_odds": {}
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Slavia Praha +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=0,
            goals_away=1
        )

        self.assertEqual(opp_url, "soccer.draw_no_bet/away")
        self.assertEqual(price, 1.45)

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_05_ah_select_match_odds_ft(self, mock_matcher_cls):
        """
        Cenário: DNB fechado e AH suspenso. Apenas 1X2 Match Odds FT aberto a @ 1.25.
        Comportamento Esperado: APROVADO via Prioridade 4 (Vitória Seca FT de 90 minutos).
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {},
            "draw_no_bet": {},
            "match_odds": {
                "away": {
                    "price": 1.25,
                    "market_url": "soccer.match_odds/away",
                    "min_stake": 0.10
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Slavia Praha +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=0,
            goals_away=1
        )

        self.assertEqual(opp_url, "soccer.match_odds/away")
        self.assertEqual(price, 1.25)
        self.assertIn("1X2 FT", palpite)

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_06_ah_away_bet_losing_selects_home_ft(self, mock_matcher_cls):
        """
        Cenário Inverso: Aposta original foi no Visitante (Villarreal +0.0 AH), mas o Mandante está ganhando por 1x0.
        Comportamento Esperado: Hedge seleciona a vitória do Mandante (Slavia Praha) de forma espelhada.
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {},
            "draw_no_bet": {},
            "match_odds": {
                "home": {
                    "price": 1.30,
                    "market_url": "soccer.match_odds/home",
                    "min_stake": 0.10
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Villarreal +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=1,
            goals_away=0
        )

        self.assertEqual(opp_url, "soccer.match_odds/home")
        self.assertIn("Slavia Praha Vence (1X2 FT)", palpite)

    # =========================================================================
    # SEÇÃO 2: TOTAL DE GOLS (OVER / UNDER)
    # =========================================================================

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_07_goals_over_hedged_with_under_anti_hole_success(self, mock_matcher_cls):
        """
        Cenário: Aposta original MAIS DE 2.5 GOLS. O jogo está 0x0 aos 65'.
        Corretora oferece: Menos de 1.5 @ 1.90 e Menos de 3.0 @ 1.40.
        Comportamento Esperado: Menos de 1.5 é VETADO (criaria buraco de Red Duplo com 2 gols).
        Menos de 3.0 (linha >= 2.5) é APROVADO!
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "99901"}
        mock_matcher.get_event_markets.return_value = {
            "total_goals": {
                1.5: {
                    "under": {"price": 1.90, "market_url": "soccer.total_goals/under?total=1.5", "min_stake": 0.10}
                },
                3.0: {
                    "under": {"price": 1.40, "market_url": "soccer.total_goals/under?total=3.0", "min_stake": 0.10}
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Time A",
            away="Time B",
            palpite="Mais de 2.5 Gols",
            event_id="99901",
            is_live=True,
            goals_home=0,
            goals_away=0
        )

        self.assertEqual(opp_url, "soccer.total_goals/under?total=3.0")
        self.assertEqual(price, 1.40)
        self.assertIn("Menos de 3.0 Gols", palpite)

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_08_goals_over_hedged_with_under_only_hole_line_vetoed(self, mock_matcher_cls):
        """
        Cenário: Aposta original MAIS DE 2.5 GOLS. Corretora oferece APENAS Menos de 1.5.
        Comportamento Esperado: VETO ABSOLUTO (NO_HEDGE / None). Comprar Under 1.5 criaria o buraco mortal:
        se o jogo terminar 2x0, o Over 2.5 perde e o Under 1.5 perde (Red Duplo). O robô DEVE se abster!
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "99901"}
        mock_matcher.get_event_markets.return_value = {
            "total_goals": {
                1.5: {
                    "under": {"price": 1.90, "market_url": "soccer.total_goals/under?total=1.5", "min_stake": 0.10}
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Time A",
            away="Time B",
            palpite="Mais de 2.5 Gols",
            event_id="99901",
            is_live=True,
            goals_home=0,
            goals_away=0
        )

        self.assertIsNone(opp_url, "Linha Under 1.5 DEVE ser vetada para cobertura de Over 2.5 (Buraco de Red Duplo)!")

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_09_goals_under_hedged_with_over_anti_hole_success(self, mock_matcher_cls):
        """
        Cenário: Aposta original MENOS DE 2.5 GOLS. O jogo está acelerado aos 35'.
        Corretora oferece: Mais de 3.5 @ 1.95 e Mais de 2.0 @ 1.35.
        Comportamento Esperado: Mais de 3.5 é VETADO (criaria buraco de Red Duplo com 3 gols).
        Mais de 2.0 (linha <= 2.5) é APROVADO!
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "99902"}
        mock_matcher.get_event_markets.return_value = {
            "total_goals": {
                3.5: {
                    "over": {"price": 1.95, "market_url": "soccer.total_goals/over?total=3.5", "min_stake": 0.10}
                },
                2.0: {
                    "over": {"price": 1.35, "market_url": "soccer.total_goals/over?total=2.0", "min_stake": 0.10}
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Time A",
            away="Time B",
            palpite="Menos de 2.5 Gols",
            event_id="99902",
            is_live=True,
            goals_home=1,
            goals_away=1
        )

        self.assertEqual(opp_url, "soccer.total_goals/over?total=2.0")
        self.assertEqual(price, 1.35)
        self.assertIn("Mais de 2.0 Gols", palpite)

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_10_goals_under_hedged_with_over_only_hole_line_vetoed(self, mock_matcher_cls):
        """
        Cenário: Aposta original MENOS DE 2.5 GOLS. Corretora oferece APENAS Mais de 3.5.
        Comportamento Esperado: VETO ABSOLUTO (NO_HEDGE / None). Evita perda dupla com 3 gols.
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "99902"}
        mock_matcher.get_event_markets.return_value = {
            "total_goals": {
                3.5: {
                    "over": {"price": 1.95, "market_url": "soccer.total_goals/over?total=3.5", "min_stake": 0.10}
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Time A",
            away="Time B",
            palpite="Menos de 2.5 Gols",
            event_id="99902",
            is_live=True,
            goals_home=1,
            goals_away=1
        )

        self.assertIsNone(opp_url, "Linha Over 3.5 DEVE ser vetada para cobertura de Under 2.5!")

    # =========================================================================
    # SEÇÃO 3: TOTAL DE CARTÕES (UNDER / OVER)
    # =========================================================================

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_11_cards_under_hedged_with_over_anti_hole_success(self, mock_matcher_cls):
        """
        Cenário: Aposta original MENOS DE 5.5 CARTÕES. Jogo violento.
        Corretora oferece: Mais de 6.5 Cartões @ 2.00 e Mais de 4.5 Cartões @ 1.40.
        Comportamento Esperado: Mais de 6.5 é VETADO (buraco de Red Duplo em 6 cartões).
        Mais de 4.5 (linha <= 5.5) é APROVADO!
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "99903"}
        mock_matcher.get_event_markets.return_value = {
            "total_bookings": {
                6.5: {
                    "over": {"price": 2.00, "market_url": "soccer.total_bookings/over?total=6.5", "min_stake": 0.10}
                },
                4.5: {
                    "over": {"price": 1.40, "market_url": "soccer.total_bookings/over?total=4.5", "min_stake": 0.10}
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Time A",
            away="Time B",
            palpite="Menos de 5.5 Cartões",
            event_id="99903",
            is_live=True
        )

        self.assertEqual(opp_url, "soccer.total_bookings/over?total=4.5")
        self.assertEqual(price, 1.40)

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_12_cards_under_hedged_with_over_only_hole_line_vetoed(self, mock_matcher_cls):
        """
        Cenário: Aposta original MENOS DE 5.5 CARTÕES. Corretora tem apenas Mais de 6.5.
        Comportamento Esperado: VETO ABSOLUTO (NO_HEDGE / None).
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "99903"}
        mock_matcher.get_event_markets.return_value = {
            "total_bookings": {
                6.5: {
                    "over": {"price": 2.00, "market_url": "soccer.total_bookings/over?total=6.5", "min_stake": 0.10}
                }
            }
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Time A",
            away="Time B",
            palpite="Menos de 5.5 Cartões",
            event_id="99903",
            is_live=True
        )

        self.assertIsNone(opp_url, "Mais de 6.5 cartões DEVE ser vetado quando o Under era 5.5!")

    # =========================================================================
    # SEÇÃO 4: MERCADOS PARCIAIS E SUBMERCADOS PROIBIDOS
    # =========================================================================

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_13_veto_partial_half_markets(self, mock_matcher_cls):
        """
        Cenário: Mercado traz URL de tempo parcial (ex: soccer.1st_half_handicap).
        Comportamento Esperado: Whitelist de FT rejeita a seleção e decreta NO_HEDGE.
        """
        mock_matcher = mock_matcher_cls.return_value
        mock_matcher.find_matching_event.return_value = {"event_id": "37115103"}
        mock_matcher.get_event_markets.return_value = {
            "asian_handicap": {
                0.0: {
                    "away": {
                        "price": 1.90,
                        "market_url": "soccer.1st_half_handicap/away?handicap=0.0",
                        "min_stake": 0.10
                    }
                }
            },
            "draw_no_bet": {},
            "match_odds": {}
        }

        event_id, opp_url, price, min_stake, palpite = find_cloudbet_opposite_market_and_quote(
            client=self.mock_client,
            home="Slavia Praha",
            away="Villarreal",
            palpite="Slavia Praha +0.0 AH",
            event_id="37115103",
            is_live=True,
            goals_home=0,
            goals_away=1
        )

        self.assertIsNone(opp_url, "Mercados de 1st_half devem ser bloqueados pela whitelist!")


if __name__ == '__main__':
    unittest.main()
