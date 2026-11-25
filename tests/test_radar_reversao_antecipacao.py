#!/usr/bin/env python3
"""
tests/test_radar_reversao_antecipacao.py
Suíte de testes de validação das travas de antecipação (>12h) no Radar de Reversão de Gols Asiáticos.
"""

import unittest
from datetime import datetime, timedelta, timezone
import os
import sys

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(CURRENT_DIR, '..'))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

from scripts.radar_reversao_nobet_sweetspot import check_antecipation_risk_goals
from scripts.asian_handicap_engine import BetTier


class TestRadarReversaoAntecipacao(unittest.TestCase):

    def setUp(self):
        self.now = datetime.now(timezone.utc).replace(tzinfo=None)

    def test_01_chapecoense_palmeiras_descompromisso_fim_temporada(self):
        """Caso 1: Chapecoense x Palmeiras (Reta final de campeonato com rebaixado vs garantido) -> DEVE REJEITAR"""
        fix = {
            'fixture_id': 1492472,
            'home_team': 'Chapecoense',
            'away_team': 'Palmeiras',
            'fixture_date': self.now + timedelta(hours=28),
            'league_name': 'Serie A',
            'league_round': 'Regular Season - 37',
            'home_rank': 20,
            'away_rank': 2,
            'home_zone': 'Relegation - Serie B',
            'away_zone': 'Promotion - Copa Libertadores (Group Stage)',
            'odd_home': 3.30,
            'odd_away': 2.22
        }
        best_cand = {
            'line': 3.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.90
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertFalse(ok)
        self.assertIn("Reta final de campeonato", reason)
        self.assertIn("descompromisso tático", reason)

    def test_02_sunderland_jagiellonia_antecipacao_valida(self):
        """Caso 2: Sunderland x Jagiellonia (Competição continental D+1 com linha inteira e PREMIUM) -> DEVE APROVAR"""
        fix = {
            'fixture_id': 1636343,
            'home_team': 'Sunderland',
            'away_team': 'Jagiellonia',
            'fixture_date': self.now + timedelta(hours=30),
            'league_name': 'UEFA Europa League',
            'league_round': 'League Stage - 5',
            'home_rank': 11,
            'away_rank': 29,
            'home_zone': 'Promotion - Europa League (Play Offs: 1/16-finals)',
            'away_zone': 'Mid-Table',
            'odd_home': 1.25,
            'odd_away': 12.00
        }
        best_cand = {
            'line': 3.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.91
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertTrue(ok)
        self.assertEqual(reason, "")

    def test_03_sabah_barcelona_superfavorito_under_proibido(self):
        """Caso 3: Sabah Baku x Barcelona (Superfavorito 1.06 tentando Under) -> DEVE REJEITAR"""
        fix = {
            'fixture_id': 1635718,
            'home_team': 'Sabah Baku',
            'away_team': 'Barcelona',
            'fixture_date': self.now + timedelta(hours=24),
            'league_name': 'UEFA Champions League',
            'league_round': 'League Stage - 5',
            'home_rank': 31,
            'away_rank': 5,
            'home_zone': 'Mid-Table',
            'away_zone': 'Promotion - Champions League (Play Offs: 1/8-finals)',
            'odd_home': 29.00,
            'odd_away': 1.06
        }
        best_cand = {
            'line': 3.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.95
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertFalse(ok)
        self.assertIn("Superfavorito", reason)
        self.assertIn("Trava Anti-Goleada", reason)

    def test_04_sabah_barcelona_superfavorito_over_antecipado_sem_sumula(self):
        """Caso 3b: Sabah Baku x Barcelona (Superfavorito 1.06 tentando Over antecipado) -> DEVE REJEITAR"""
        fix = {
            'fixture_id': 1635718,
            'home_team': 'Sabah Baku',
            'away_team': 'Barcelona',
            'fixture_date': self.now + timedelta(hours=24),
            'league_name': 'UEFA Champions League',
            'league_round': 'League Stage - 5',
            'home_rank': 31,
            'away_rank': 5,
            'home_zone': 'Mid-Table',
            'away_zone': 'Promotion - Champions League (Play Offs: 1/8-finals)',
            'odd_home': 29.00,
            'odd_away': 1.06
        }
        best_cand = {
            'line': 3.0,
            'type': 'Over',
            'tier': BetTier.PREMIUM,
            'odd': 1.85
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertFalse(ok)
        self.assertIn("Superfavorito", reason)
        self.assertIn("Entrada de Over antecipada vetada", reason)

    def test_05_sporting_man_united_quase_premium_rejeitado(self):
        """Caso 4: Sporting x Man United com selo Quase Premium em D+1 -> DEVE REJEITAR (exige PREMIUM)"""
        fix = {
            'fixture_id': 1635735,
            'home_team': 'Sporting CP',
            'away_team': 'Manchester United',
            'fixture_date': self.now + timedelta(hours=26),
            'league_name': 'UEFA Champions League',
            'league_round': 'League Stage - 5',
            'home_rank': 10,
            'away_rank': 17,
            'home_zone': 'Playoffs',
            'away_zone': 'Playoffs',
            'odd_home': 2.70,
            'odd_away': 2.57
        }
        best_cand = {
            'line': 3.0,
            'type': 'Under',
            'tier': BetTier.QUASE_PREMIUM,
            'odd': 1.80
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertFalse(ok)
        self.assertIn("requer estritamente selo PREMIUM", reason)

    def test_06_sporting_man_united_linha_fracionada_rejeitada(self):
        """Caso 4b: Sporting x Man United com linha 2.5 em D+1 -> DEVE REJEITAR (exige inteira 2.0 ou 3.0)"""
        fix = {
            'fixture_id': 1635735,
            'home_team': 'Sporting CP',
            'away_team': 'Manchester United',
            'fixture_date': self.now + timedelta(hours=26),
            'league_name': 'UEFA Champions League',
            'league_round': 'League Stage - 5',
            'home_rank': 10,
            'away_rank': 17,
            'odd_home': 2.70,
            'odd_away': 2.57
        }
        best_cand = {
            'line': 2.5,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.85
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertFalse(ok)
        self.assertIn("Linha fracionada", reason)

    def test_07_psg_roma_premium_linha_inteira_aprovado(self):
        """Caso 5: PSG x Roma com linha 3.0 e Tier PREMIUM -> DEVE APROVAR"""
        fix = {
            'fixture_id': 1635703,
            'home_team': 'Paris Saint Germain',
            'away_team': 'AS Roma',
            'fixture_date': self.now + timedelta(hours=26),
            'league_name': 'UEFA Champions League',
            'league_round': 'League Stage - 5',
            'home_rank': 1,
            'away_rank': 20,
            'home_zone': 'Promotion - Champions League (Play Offs: 1/8-finals)',
            'away_zone': 'Promotion - Champions League (Play Offs: 1/16-finals)',
            'odd_home': 1.52,
            'odd_away': 6.20
        }
        best_cand = {
            'line': 3.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.90
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertTrue(ok)
        self.assertEqual(reason, "")

    def test_08_club_brugge_liverpool_aprovado(self):
        """Caso 6: Club Brugge x Liverpool com linha 3.0 e Tier PREMIUM -> DEVE APROVAR"""
        fix = {
            'fixture_id': 1635642,
            'home_team': 'Club Brugge KV',
            'away_team': 'Liverpool',
            'fixture_date': self.now + timedelta(hours=26),
            'league_name': 'UEFA Champions League',
            'league_round': 'League Stage - 5',
            'home_rank': 9,
            'away_rank': 8,
            'home_zone': 'Promotion - Champions League (Play Offs: 1/16-finals)',
            'away_zone': 'Promotion - Champions League (Play Offs: 1/8-finals)',
            'odd_home': 4.60,
            'odd_away': 1.67
        }
        best_cand = {
            'line': 3.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.88
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertTrue(ok)
        self.assertEqual(reason, "")

    def test_09_atletico_mg_corinthians_reta_final_com_pressao_z4_aprovado(self):
        """Caso 7: Atlético-MG x Corinthians (Rodada 36, Corinthians 16º a 1 pt do Z-4) -> DEVE APROVAR (não é descompromissado!)"""
        fix = {
            'fixture_id': 1492461,
            'home_team': 'Atletico-MG',
            'away_team': 'Corinthians',
            'fixture_date': self.now + timedelta(hours=29),
            'league_name': 'Serie A',
            'league_round': 'Regular Season - 36',
            'home_rank': 7,
            'away_rank': 16,
            'home_zone': 'Promotion - Copa Sudamericana (Group Stage)',
            'away_zone': 'Mid-Table', # Corinthians não está matematicamente rebaixado nem o Galo é campeão!
            'odd_home': 2.55,
            'odd_away': 2.85
        }
        best_cand = {
            'line': 2.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.75
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertTrue(ok)
        self.assertEqual(reason, "")

    def test_10_boca_juniors_mata_mata_rejeitado(self):
        """Caso 8: Boca Juniors x Independiente Rivadavia (Oitavas de final mata-mata) -> DEVE REJEITAR"""
        fix = {
            'fixture_id': 1649565,
            'home_team': 'Boca Juniors',
            'away_team': 'Independ. Rivadavia',
            'fixture_date': self.now + timedelta(hours=30),
            'league_name': 'Liga Profesional Argentina',
            'league_round': 'Round of 16',
            'home_rank': 1,
            'away_rank': 4,
            'odd_home': 1.65,
            'odd_away': 6.20
        }
        best_cand = {
            'line': 2.0,
            'type': 'Under',
            'tier': BetTier.PREMIUM,
            'odd': 1.80
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertFalse(ok)
        self.assertIn("Fase eliminatória de copa", reason)

    def test_11_jogo_no_mesmo_dia_abaixo_12h_ignora_trava_antecipacao(self):
        """Caso 9: Jogo a 3 horas do início (D+0 / Pré-jogo) -> trava de antecipação não se aplica"""
        fix = {
            'fixture_id': 999999,
            'home_team': 'Time A',
            'away_team': 'Time B',
            'fixture_date': self.now + timedelta(hours=3),
            'league_name': 'Liga X',
            'league_round': 'Rodada 10',
            'odd_home': 1.90,
            'odd_away': 3.50
        }
        best_cand = {
            'line': 2.5, # Fracionada autorizada em pré-jogo
            'type': 'Under',
            'tier': BetTier.QUASE_PREMIUM, # Quase premium autorizado em pré-jogo
            'odd': 1.80
        }
        ok, reason = check_antecipation_risk_goals(fix, best_cand)
        self.assertTrue(ok)
        self.assertEqual(reason, "")


if __name__ == '__main__':
    unittest.main()
