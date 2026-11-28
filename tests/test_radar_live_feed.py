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


if __name__ == '__main__':
    unittest.main()
