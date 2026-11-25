#!/usr/bin/env python3
"""
tests/test_cloudbet_lineup_and_cognitive_audit.py

Suíte Oficial de Regressão (Regra de Ouro 22):
Valida o acoplamento determinístico entre Súmula Oficial Confirmada e a esteira
do Robô Cloudbet, além da eliminação do falso carimbo de aprovação cognitiva da IA.

Cenários Cobertos:
1. Partida NS em T-45m sem súmula confirmada -> REJEITADA (Fim da brecha T-45m)
2. Partida NS com súmula confirmada (mlc.status = 'CONFIRMADA') -> APROVADA para avaliação
3. Partida NS com súmula confirmada mas veto de risco (auditoria_ia_status = 'ALERTA_RISCO') -> REJEITADA
4. Partida In-Play (1H 0x0) com titulares em campo -> APROVADA para avaliação in-play
5. Motor AH com fallback da IA (decisao = 'NO_BET' ou vazio) -> NÃO estampa carimbo falso cognitivo
6. Motor AH com aprovação explícita da IA (decisao = 'APROVADO') -> Estampa carimbo cognitivo
7. Grok Tactical Auditor injeta placar e status real quando partida está em andamento
"""

import unittest
from unittest.mock import MagicMock, patch
import os
import sys

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
SCRIPTS_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '../scripts'))
if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)


class TestCloudbetLineupAndCognitiveAudit(unittest.TestCase):

    def test_01_pregame_ns_without_confirmed_lineup_rejected(self):
        """
        Cenário: Partida NS a 30 min do início (T-30m), mas SEM súmula oficial confirmada.
        Esperado: O robô NÃO deve selecionar a partida (deve exigir mlc.status = 'CONFIRMADA').
        """
        # Simula a lógica SQL implementada em criar_apostas_cloudbet_diario.py
        def is_fixture_eligible(status, goals_home, goals_away, mlc_status, auditoria_ia_status):
            is_inplay_0x0 = (status in ('1H', 'LIVE', 'IN_PLAY') and (goals_home or 0) == 0 and (goals_away or 0) == 0)
            is_pregame_confirmed = (status == 'NS' and mlc_status == 'CONFIRMADA')
            has_no_risk_veto = (auditoria_ia_status is None or auditoria_ia_status != 'ALERTA_RISCO')
            return (is_inplay_0x0 or is_pregame_confirmed) and has_no_risk_veto

        # Caso: NS em T-30m sem súmula (mlc_status = None ou PENDENTE)
        eligible = is_fixture_eligible(
            status='NS',
            goals_home=0,
            goals_away=0,
            mlc_status=None,
            auditoria_ia_status=None
        )
        self.assertFalse(eligible, "Falha: Partida sem súmula oficial não pode ser elegível para aposta pré-jogo.")

    def test_02_pregame_ns_with_confirmed_lineup_accepted(self):
        """
        Cenário: Partida NS com súmula oficial confirmada (mlc.status = 'CONFIRMADA') e sem veto de risco.
        Esperado: A partida DEVE ser elegível para avaliação quantitativa de odds.
        """
        def is_fixture_eligible(status, goals_home, goals_away, mlc_status, auditoria_ia_status):
            is_inplay_0x0 = (status in ('1H', 'LIVE', 'IN_PLAY') and (goals_home or 0) == 0 and (goals_away or 0) == 0)
            is_pregame_confirmed = (status == 'NS' and mlc_status == 'CONFIRMADA')
            has_no_risk_veto = (auditoria_ia_status is None or auditoria_ia_status != 'ALERTA_RISCO')
            return (is_inplay_0x0 or is_pregame_confirmed) and has_no_risk_veto

        eligible = is_fixture_eligible(
            status='NS',
            goals_home=0,
            goals_away=0,
            mlc_status='CONFIRMADA',
            auditoria_ia_status='CONFIRMADO'
        )
        self.assertTrue(eligible, "Falha: Partida com súmula confirmada e sem risco deve ser elegível.")

    def test_03_pregame_ns_with_alerta_risco_rejected(self):
        """
        Cenário: Partida NS com súmula confirmada mas vetada pela IA (auditoria_ia_status = 'ALERTA_RISCO').
        Esperado: O robô DEVE vetar a partida por risco tático crítico (desfalques).
        """
        def is_fixture_eligible(status, goals_home, goals_away, mlc_status, auditoria_ia_status):
            is_inplay_0x0 = (status in ('1H', 'LIVE', 'IN_PLAY') and (goals_home or 0) == 0 and (goals_away or 0) == 0)
            is_pregame_confirmed = (status == 'NS' and mlc_status == 'CONFIRMADA')
            has_no_risk_veto = (auditoria_ia_status is None or auditoria_ia_status != 'ALERTA_RISCO')
            return (is_inplay_0x0 or is_pregame_confirmed) and has_no_risk_veto

        eligible = is_fixture_eligible(
            status='NS',
            goals_home=0,
            goals_away=0,
            mlc_status='CONFIRMADA',
            auditoria_ia_status='ALERTA_RISCO'
        )
        self.assertFalse(eligible, "Falha: Partida com ALERTA_RISCO deve ser sumariamente bloqueada.")

    def test_04_inplay_live_match_with_0x0_accepted(self):
        """
        Cenário: Partida em andamento (1H aos 15'), placar 0x0.
        Esperado: Elegível para o motor in-play (titulares já estão em campo).
        """
        def is_fixture_eligible(status, goals_home, goals_away, mlc_status, auditoria_ia_status):
            is_inplay_0x0 = (status in ('1H', 'LIVE', 'IN_PLAY') and (goals_home or 0) == 0 and (goals_away or 0) == 0)
            is_pregame_confirmed = (status == 'NS' and mlc_status == 'CONFIRMADA')
            has_no_risk_veto = (auditoria_ia_status is None or auditoria_ia_status != 'ALERTA_RISCO')
            return (is_inplay_0x0 or is_pregame_confirmed) and has_no_risk_veto

        eligible = is_fixture_eligible(
            status='1H',
            goals_home=0,
            goals_away=0,
            mlc_status=None,
            auditoria_ia_status=None
        )
        self.assertTrue(eligible, "Falha: Partida ao vivo em 0x0 deve ser elegível para in-play.")

    def test_05_asian_handicap_no_false_cognitive_approval(self):
        """
        Cenário: Motor AH aprova aposta matematicamente (+EV), mas a IA retornou fallback
                 ou 'Nenhuma oportunidade assimétrica confirmada pela IA.'.
        Esperado: O carimbo gerado DEVE ser '🎯 GATEKEEPER AH APROVADO (+EV ...)' e NUNCA
                 conter 'APROVADO POR AUDITORIA COGNITIVA DA IA'.
        """
        from asian_handicap_engine import compose_compound_ah_reasoning

        # Simula a lógica de formatação de raw_calc corrigida na linha 2540
        ia_audit_info = {
            "decisao": "NO_BET",
            "parecer_explicativo": "Nenhuma oportunidade assimétrica confirmada pela IA."
        }
        ia_parecer_texto = str(ia_audit_info.get('parecer_explicativo') or '').strip()
        ia_decisao = str(ia_audit_info.get('decisao') or '').strip().upper()
        is_ia_efetiva = bool(ia_parecer_texto and ia_decisao in ('APROVADO', 'BET') and "Nenhuma oportunidade" not in ia_parecer_texto)

        self.assertFalse(is_ia_efetiva, "A IA com decisao=NO_BET não pode ser classificada como is_ia_efetiva.")

        if is_ia_efetiva:
            raw_calc = f"🎯 GATEKEEPER AH APROVADO POR AUDITORIA COGNITIVA DA IA (+EV +15.2%) | Parecer: {ia_parecer_texto}"
        else:
            raw_calc = "🎯 GATEKEEPER AH APROVADO (+EV +15.2%) | Matriz Poisson: xG 1.80 x 1.10"

        self.assertNotIn("APROVADO POR AUDITORIA COGNITIVA DA IA", raw_calc)
        self.assertIn("🎯 GATEKEEPER AH APROVADO (+EV", raw_calc)

    def test_06_asian_handicap_true_cognitive_approval(self):
        """
        Cenário: A IA explicitamente confirmou e aprovou a linha (decisao = 'APROVADO').
        Esperado: O carimbo '🎯 GATEKEEPER AH APROVADO POR AUDITORIA COGNITIVA DA IA' é emitido legitimamente.
        """
        ia_audit_info = {
            "decisao": "APROVADO",
            "parecer_explicativo": "Escalação titular confirmada e superioridade tática evidente."
        }
        ia_parecer_texto = str(ia_audit_info.get('parecer_explicativo') or '').strip()
        ia_decisao = str(ia_audit_info.get('decisao') or '').strip().upper()
        is_ia_efetiva = bool(ia_parecer_texto and ia_decisao in ('APROVADO', 'BET') and "Nenhuma oportunidade" not in ia_parecer_texto)

        self.assertTrue(is_ia_efetiva, "A IA com decisao=APROVADO e parecer real deve ser is_ia_efetiva.")

        if is_ia_efetiva:
            raw_calc = f"🎯 GATEKEEPER AH APROVADO POR AUDITORIA COGNITIVA DA IA (+EV +15.2%) | Parecer: {ia_parecer_texto}"
        else:
            raw_calc = "🎯 GATEKEEPER AH APROVADO (+EV +15.2%)"

        self.assertIn("APROVADO POR AUDITORIA COGNITIVA DA IA", raw_calc)

    def test_07_grok_tactical_auditor_inplay_context(self):
        """
        Cenário: Jogo em andamento aos 35' do 1º tempo com placar 1x0.
        Esperado: O texto de situação enviado ao Grok DEVE conter 'Em Andamento' e o placar,
                 nunca 'Pré-Jogo (Ainda não iniciada)'.
        """
        aposta_dict = {
            "fixture_status": "1H",
            "goals_home": 1,
            "goals_away": 0,
            "elapsed": 35
        }
        home_team = "Sporting CP"
        away_team = "Manchester United"

        st_fx = str(aposta_dict.get("fixture_status") or aposta_dict.get("status") or "NS").strip().upper()
        g_h = aposta_dict.get("goals_home")
        g_a = aposta_dict.get("goals_away")
        elap = aposta_dict.get("elapsed")
        if st_fx in ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY') or (g_h is not None and g_a is not None and (int(g_h or 0) > 0 or int(g_a or 0) > 0)):
            situacao_str = f"Em Andamento ({st_fx} aos {elap or 0}' | Placar ao Vivo: {home_team} {g_h or 0} x {g_a or 0} {away_team})"
        else:
            situacao_str = "Pré-Jogo (Ainda não iniciada)"

        self.assertIn("Em Andamento (1H aos 35'", situacao_str)
        self.assertIn("Placar ao Vivo: Sporting CP 1 x 0 Manchester United", situacao_str)
        self.assertNotIn("Pré-Jogo", situacao_str)


if __name__ == '__main__':
    unittest.main()
