#!/usr/bin/env python3
"""
tests/test_cloudbet_hedge_fulltime.py
Suíte de testes de validação da blindagem de mercados de Jogo Completo (FT)
e veto compulsório a mercados parciais e linhas agressivas de Handicap no Cross-Hedge.
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

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_01_veto_rest_of_the_match(self, mock_matcher_cls):
        """
        Cenário: Partida aos 76' com placar 0x1. Corretora oferece apenas 'which_team_wins_the_rest_of_the_match'.
        Comportamento Esperado: Veto Absoluto (NO_HEDGE / None), pois o mercado de 'Rest of Match' zera o placar
        e exige outro gol, causando Red Duplo se o jogo terminar 0x1.
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

        self.assertIsNone(opp_url, "Mercado 'which_team_wins_the_rest_of_the_match' deve ser sumariamente vetado!")

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_02_veto_handicap_too_aggressive(self, mock_matcher_cls):
        """
        Cenário: Placar 0x1 (Villarreal lidera por 1 gol). Corretora tem apenas linha -1.5 AH para o Villarreal.
        Comportamento Esperado: Linha -1.5 AH vetada (exige 2 gols de margem), pois se o jogo terminar 0x1 ela perde.
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

        self.assertIsNone(opp_url, "Linha -1.5 AH não pode ser selecionada quando a vantagem é de apenas 1 gol!")

    @patch('cloudbet_matcher.CloudbetMatcher')
    def test_03_accept_handicap_safe_coverage(self, mock_matcher_cls):
        """
        Cenário: Placar 0x1. Corretora oferece -0.5 AH (Vitória Simples) para o Villarreal.
        Comportamento Esperado: Aprovada! Se o jogo terminar 0x1, o Villarreal vence por 1 gol (0x1) e a aposta paga.
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
    def test_04_select_draw_no_bet_ft(self, mock_matcher_cls):
        """
        Cenário: Linha dedicada de Draw No Bet (0.0 AH) disponível.
        Comportamento Esperado: Prioridade 0 ativada, selecionando DNB FT.
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
    def test_05_select_match_odds_ft(self, mock_matcher_cls):
        """
        Cenário: DNB fechado e AH sem linhas válidas. Apenas 1X2 Match Odds FT aberto a @ 1.25.
        Comportamento Esperado: Prioridade 4 ativada, selecionando Vitória Seca FT da partida inteira.
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
    def test_06_veto_partial_half_markets(self, mock_matcher_cls):
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
