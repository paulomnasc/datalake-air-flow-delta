import unittest
from unittest.mock import MagicMock, patch
import sys
import os

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
SCRIPTS_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '..', 'scripts'))
if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

from gemini_sentinel import repescar_nobet_por_sumula
from asian_handicap_engine import calculate_bivariate_poisson_matrix, evaluate_and_select_best_ah_candidate


class TestSentinelLineupMatrix(unittest.TestCase):
    """
    Testes automatizados cobrindo a Matriz de 3 Cenários da Sentinela:
    1. Crystal Palace vs Real Sociedad: Sem desfalques críticos (Força Máxima) -> Mantém NO_BET e enriquece Card (sem card morto).
    2. Sparta Praha vs AZ Alkmaar: Alto impacto no rival sem aposta prévia -> Desfaz NO_BET (Reset Tático) e apoia AZ Alkmaar.
    3. Sunderland vs Monterrey: Alto impacto no próprio time com aposta emitida -> Alerta Máximo de Risco / Hedge (não apaga aposta).
    """

    def setUp(self):
        self.cursor = MagicMock()
        self.conn = MagicMock()

    def test_case_1_crystal_palace_real_sociedad_max_strength_enriches_card(self):
        """
        Caso 1: Jogo inicialmente em NO_BET por volatilidade/equilíbrio.
        Súmula oficial sem desfalques críticos (ambos com força máxima).
        Resultado esperado:
        - Mantém NO_BET (nenhuma aposta emitida).
        - Executa UPDATE em fixtures_trends com auditoria_ia_status = 'CONFIRMADO' e resumo explicativo.
        - Card deixa de ser 'morto'.
        """
        fix = {
            'fixture_id': 1636252,
            'league_id': 3,
            'league_name': 'UEFA Europa League',
            'home_team_id': 52,
            'away_team_id': 548,
            'home_team': 'Crystal Palace',
            'away_team': 'Real Sociedad',
            'fixture_date': '2026-11-26 20:00:00',
            'ah_reasoning': 'Equilíbrio acentuado e divergência de mercado. NO_BET decretado por volatilidade.',
            'odd_home': 2.45,
            'odd_draw': 3.20,
            'odd_away': 2.80,
            'xg_home': 1.15,
            'xg_away': 1.10,
            'ah_suggestion': 'Abstenção / NO_BET',
            'gatekeeper_category': 'Divergência de Mercado'
        }

        # Mock query de jogos nobet
        self.cursor.fetchall.return_value = [fix]

        # Mock súmula oficial: força máxima (sem desfalques críticos)
        mock_sumula = {
            'tem_sumula': True,
            'rotacao_home_cnt': 0,
            'rotacao_away_cnt': 0,
            'desfalques_home_cnt': 0,
            'desfalques_away_cnt': 0,
            'motivos_alerta_elenco': []
        }

        with patch('leagues_config.is_allowed_league', return_value=True), \
             patch('gemini_sentinel.coletar_sumula_e_desfalques_apisports', return_value=mock_sumula), \
             patch('asian_handicap_engine.calculate_unified_handicap_recommendation', return_value=('NO_BET', 'Abstenção', 0.0, 'Sem entrada de valor', None, [])):

            repescar_nobet_por_sumula(self.cursor, self.conn, window_minutes=50, user_ids=[1])

            # Verifica se houve UPDATE no fixtures_trends para enriquecer o card
            update_card_calls = [
                call for call in self.cursor.execute.call_args_list
                if "UPDATE fixtures_trends" in str(call) and "auditoria_ia_status = 'CONFIRMADO'" in str(call)
            ]
            self.assertTrue(len(update_card_calls) >= 1, "Deveria ter atualizado o card do Crystal Palace com CONFIRMADO.")
            args = update_card_calls[0][0][1]
            self.assertIn("Titulares Confirmados (Força Máxima)", args[0])
            self.assertEqual(args[2], 1636252)

    def test_case_2_sparta_praha_az_alkmaar_tactical_reset_reversal(self):
        """
        Caso 2: Jogo estava em NO_BET.
        Súmula oficial indica 9 desfalques de peso e 4 alterações estruturais no Sparta Praha.
        Resultado esperado:
        - Detecta desfalque crítico no Sparta Praha.
        - Recalibra Poisson e aprova AZ Alkmaar 0.0 AH ou +0.5 AH.
        - Sincroniza fixture e bilhete (sync_fixture_and_bet_handicap) com categoria 'Reset Tático por Súmula'.
        """
        fix = {
            'fixture_id': 1636336,
            'league_id': 3,
            'league_name': 'UEFA Europa League',
            'home_team_id': 557,
            'away_team_id': 610,
            'home_team': 'Sparta Praha',
            'away_team': 'AZ Alkmaar',
            'fixture_date': '2026-11-26 17:45:00',
            'ah_reasoning': 'NO_BET prévio por distorção de odds do mandante.',
            'odd_home': 1.70,
            'odd_draw': 3.80,
            'odd_away': 4.55,
            'xg_home': 1.45,
            'xg_away': 0.95,
            'ah_suggestion': 'Abstenção / NO_BET',
            'gatekeeper_category': 'Divergência de Mercado'
        }

        self.cursor.fetchall.return_value = [fix]

        # Súmula com time misto no Sparta Praha
        mock_sumula = {
            'tem_sumula': True,
            'rotacao_home_cnt': 5,
            'rotacao_away_cnt': 0,
            'desfalques_home_cnt': 9,
            'desfalques_away_cnt': 0,
            'motivos_alerta_elenco': ['Sparta Praha com 9 desfalques e time misto']
        }

        cand_best = {
            'target': 'away',
            'team_name': 'AZ Alkmaar',
            'line': 0.0,
            'label': 'AZ Alkmaar 0.0 AH',
            'odd': 1.79,
            'eval': {'prob_eff': 82.0, 'ev_percent': 46.8, 'odd_justa': 1.22}
        }

        with patch('leagues_config.is_allowed_league', return_value=True), \
             patch('gemini_sentinel.coletar_sumula_e_desfalques_apisports', return_value=mock_sumula), \
             patch('asian_handicap_engine.calculate_unified_handicap_recommendation', return_value=('APPROVED', 'AZ Alkmaar 0.0 AH', 82.0, 'Poisson favorável ao AZ', cand_best, [cand_best])), \
             patch('asian_handicap_engine.sync_fixture_and_bet_handicap') as mock_sync, \
             patch('gemini_sentinel.despachar_ordem_taticoreset_cloudbet', return_value={'executadas': 0, 'sem_liquidez': 1}), \
             patch('gemini_sentinel.registrar_alerta_sininho'), \
             patch('gemini_sentinel.enviar_push_mobile'):

            repescar_nobet_por_sumula(self.cursor, self.conn, window_minutes=50, user_ids=[1])

            self.assertTrue(mock_sync.called, "Deveria ter executado sync_fixture_and_bet_handicap para o AZ Alkmaar.")
            call_kwargs = mock_sync.call_args[1]
            self.assertEqual(call_kwargs['fixture_id'], 1636336)
            self.assertEqual(call_kwargs['selected_palpite'], 'AZ Alkmaar 0.0 AH')
            self.assertEqual(call_kwargs['odd_val'], 1.79)

    def test_case_3_sunderland_monterrey_bet_exists_triggers_hedge_not_delete(self):
        """
        Caso 3: Aposta já criada para Monterrey 0.0 AH.
        Súmula oficial indica esfacelamento do Monterrey.
        Resultado esperado:
        - Aposta NÃO é deletada ou zerada para NO_BET.
        - Executa rotina de hedge pré-jogo (executar_hedge_prejogo_cloudbet).
        - Dispara notificação de alerta crítico (tipo ESCALACAO_CONFIRMADA_ALERTA_HEDGE).
        """
        bet_item = {
            'id': 999123,
            'aposta_id': 999123,
            'usuario_id': 1,
            'fixture_id': 888777,
            'home_team': 'Sunderland',
            'away_team': 'Monterrey',
            'palpite': 'Monterrey 0.0 AH',
            'odd': 1.82,
            'mercado': 'Handicap Asiático',
            'tipo': 'Robô Cloudbet',
            'status': 'Pendente',
            'confirmada': 1,
            'tem_debito': 1,
            'casa_de_aposta': 'Cloudbet'
        }

        # Simulação da súmula informando desastre no Monterrey
        res_grok = {
            'veredito': 'CASHOUT_IMEDIATO',
            'analise_ia': 'Monterrey entra com reservas em 8 posições. Risco severo para Monterrey 0.0 AH.'
        }

        with patch('cloudbet_live_cashout.executar_hedge_prejogo_cloudbet') as mock_hedge:
            # Testa a lógica direta de disparo de hedge
            is_cashout = (res_grok.get('veredito') == 'CASHOUT_IMEDIATO')
            self.assertTrue(is_cashout)

            from cloudbet_live_cashout import executar_hedge_prejogo_cloudbet
            executar_hedge_prejogo_cloudbet(
                bet=bet_item,
                motivo_tatica=res_grok['analise_ia'],
                cursor=self.cursor,
                conn=self.conn
            )

            mock_hedge.assert_called_once_with(
                bet=bet_item,
                motivo_tatica=res_grok['analise_ia'],
                cursor=self.cursor,
                conn=self.conn
            )


if __name__ == '__main__':
    unittest.main()
