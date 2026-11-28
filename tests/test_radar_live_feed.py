#!/usr/bin/env python3
"""
tests/test_radar_live_feed.py

Teste de Regressão e Validação Sistêmica do Radar In-Play (Opção 1: Abas na Sidebar).
Valida:
1. Instrumentação de telemetria no script criar_apostas_cloudbet_diario.py (log_lifecycle_event com componente='CLOUDBET_RADAR');
2. Endpoint /notificacoes/radar-feed registrado em Routes.php e método radarFeed em ApostaController.php;
3. Estrutura HTML/CSS/JS das abas e container do Radar em header.php (Regras 13, 17, 22 e 23).
"""

import unittest
import os
import re
from unittest.mock import MagicMock, patch

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(CURRENT_DIR, ".."))

class TestRadarLiveFeed(unittest.TestCase):

    def test_cloudbet_inplay_telemetry_instrumented(self):
        """Verifica se criar_apostas_cloudbet_diario.py possui a instrumentação de telemetria do Radar."""
        script_path = os.path.join(PROJECT_ROOT, "scripts", "criar_apostas_cloudbet_diario.py")
        self.assertTrue(os.path.exists(script_path), f"Arquivo não encontrado: {script_path}")

        with open(script_path, "r", encoding="utf-8") as f:
            content = f.read()

        self.assertIn("CLOUDBET_RADAR", content, "Componente CLOUDBET_RADAR ausente no script Cloudbet")
        self.assertIn("VARREDURA_INPLAY", content, "Evento VARREDURA_INPLAY ausente no script Cloudbet")
        self.assertIn("LINHAS_FECHADAS", content, "Evento LINHAS_FECHADAS ausente no script Cloudbet")
        self.assertIn("MATCH_ENCONTRADO", content, "Evento MATCH_ENCONTRADO ausente no script Cloudbet")
        self.assertIn("ODDS_1X2", content, "Evento ODDS_1X2 ausente no script Cloudbet")
        self.assertIn("AH_REPROVADO", content, "Evento AH_REPROVADO ausente no script Cloudbet")
        self.assertIn("GOLS_STANDBY", content, "Evento GOLS_STANDBY ausente no script Cloudbet")
        self.assertIn("CARTOES_STANDBY", content, "Evento CARTOES_STANDBY ausente no script Cloudbet")
        self.assertIn("ORDEM_EMITIDA", content, "Evento ORDEM_EMITIDA ausente no script Cloudbet")

    def test_routes_contains_radar_feed(self):
        """Verifica se a rota /notificacoes/radar-feed está devidamente registrada."""
        routes_path = os.path.join(PROJECT_ROOT, "src", "footballweb", "app", "Config", "Routes.php")
        self.assertTrue(os.path.exists(routes_path), f"Arquivo não encontrado: {routes_path}")

        with open(routes_path, "r", encoding="utf-8") as f:
            content = f.read()

        self.assertIn("/notificacoes/radar-feed", content, "Rota /notificacoes/radar-feed ausente em Routes.php")
        self.assertIn("ApostaController::radarFeed", content, "Mapeamento para ApostaController::radarFeed ausente")

    def test_aposta_controller_has_radar_feed_method(self):
        """Verifica se o método radarFeed existe e consulta pipeline_lifecycle_logs."""
        controller_path = os.path.join(PROJECT_ROOT, "src", "footballweb", "app", "Controllers", "ApostaController.php")
        self.assertTrue(os.path.exists(controller_path), f"Arquivo não encontrado: {controller_path}")

        with open(controller_path, "r", encoding="utf-8") as f:
            content = f.read()

        self.assertIn("public function radarFeed()", content, "Método radarFeed() ausente em ApostaController.php")
        self.assertIn("pipeline_lifecycle_logs", content, "Consulta à tabela pipeline_lifecycle_logs ausente")
        self.assertIn("CLOUDBET_RADAR", content, "Filtro pelo componente CLOUDBET_RADAR ausente")
        self.assertIn("IA_SENTINELA", content, "Filtro pelo componente IA_SENTINELA ausente no radarFeed")
        self.assertIn("SENTINELA_5MIN", content, "Filtro pelo componente SENTINELA_5MIN ausente no radarFeed")

    def test_header_view_has_radar_tabs_and_container(self):
        """Verifica se header.php contém a interface com abas, pulse dot e container do radar."""
        header_path = os.path.join(PROJECT_ROOT, "src", "footballweb", "app", "Views", "header.php")
        self.assertTrue(os.path.exists(header_path), f"Arquivo não encontrado: {header_path}")

        with open(header_path, "r", encoding="utf-8") as f:
            content = f.read()

        self.assertIn("tab-btn-notificacoes", content, "Botão da aba Notificações ausente")
        self.assertIn("tab-btn-radar", content, "Botão da aba Radar In-Play ausente")
        self.assertIn("lista-radar-container", content, "Container #lista-radar-container ausente")
        self.assertIn("switchSidebarTab", content, "Função switchSidebarTab ausente no script do header")
        self.assertIn("loadRadarFeed", content, "Função loadRadarFeed ausente no script do header")
        self.assertIn("pulse-radar-dot", content, "Classe visual pulse-radar-dot ausente")

    def test_match_milestone_contains_team_names(self):
        """Verifica se log_match_milestone inclui o nome dos times no motivo_detalhado."""
        import sys
        sys.path.insert(0, os.path.join(PROJECT_ROOT, "scripts"))
        from lifecycle_logger import log_match_milestone

        mock_cursor = MagicMock()
        mock_cursor.fetchone.return_value = None  # sem duplicatas
        mock_cursor.lastrowid = 1234

        log_id = log_match_milestone(
            cursor=mock_cursor,
            fixture_id=1649500,
            marco="APITO_INICIAL",
            placar="0x0",
            minuto=0,
            home_team="Columbus Crew",
            away_team="Inter Miami"
        )

        self.assertEqual(log_id, 1234)
        mock_cursor.execute.assert_called()
        # Verificar se a query de INSERT continha os nomes dos times
        insert_args = [call_args for call_args in mock_cursor.execute.call_args_list if "INSERT INTO" in str(call_args)]
        self.assertTrue(len(insert_args) > 0, "INSERT INTO pipeline_lifecycle_logs não foi chamado")
        params = insert_args[0][0][1]
        motivo_gravado = params[9]  # motivo_detalhado
        self.assertIn("Columbus Crew", motivo_gravado)
        self.assertIn("Inter Miami", motivo_gravado)
        self.assertIn("#1649500", motivo_gravado)

    def test_ensure_inplay_handicap_governance_pregame_confirmed(self):
        """Verifica se partida já chancelada com súmula e IA no pré-jogo é autorizada imediatamente."""
        from scripts.criar_apostas_cloudbet_diario import ensure_inplay_handicap_governance
        mock_cursor = MagicMock()
        mock_conn = MagicMock()
        mock_cursor.fetchone.return_value = {
            'status': 'CONFIRMADA',
            'titulares_home': 'Jogador 1, Jogador 2',
            'titulares_away': 'Jogador 3, Jogador 4'
        }
        fix = {
            'fixture_id': 1649500,
            'auditoria_ia_status': 'CONFIRMADO',
            'status_gatekeeper': 'APROVADO',
            'ah_suggestion': 'Columbus Crew +0.5 AH',
            'home_team': 'Columbus Crew',
            'away_team': 'Inter Miami'
        }
        ok, fav, motivo = ensure_inplay_handicap_governance(mock_cursor, mock_conn, fix, 'Columbus Crew', 'Inter Miami')
        self.assertTrue(ok)
        self.assertEqual(fav, 'Columbus Crew')
        self.assertIn('Súmula Oficial Confirmada', motivo)

    def test_ensure_inplay_handicap_governance_missing_lineup(self):
        """Verifica se partida sem súmula oficial publicada é barrada com NO_BET."""
        from scripts.criar_apostas_cloudbet_diario import ensure_inplay_handicap_governance
        mock_cursor = MagicMock()
        mock_conn = MagicMock()
        mock_cursor.fetchone.return_value = None  # Sem cache

        with patch('scripts.criar_apostas_cloudbet_diario.coletar_sumula_e_desfalques_apisports') as mock_sumula:
            mock_sumula.return_value = {'tem_sumula': False}
            fix = {
                'fixture_id': 1649500,
                'auditoria_ia_status': None,
                'status_gatekeeper': 'NO_BET',
                'ah_suggestion': 'Sem Entrada (Abstenção)',
                'home_team': 'Columbus Crew',
                'away_team': 'Inter Miami'
            }
            ok, fav, motivo = ensure_inplay_handicap_governance(mock_cursor, mock_conn, fix, 'Columbus Crew', 'Inter Miami')
            self.assertFalse(ok)
            self.assertIn('Ausência de Súmula Oficial Confirmada', motivo)


if __name__ == '__main__':
    unittest.main()
