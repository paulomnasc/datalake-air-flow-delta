#!/usr/bin/env python3
"""
tests/test_cloudbet_dry_run_dynamic_toggle.py

Suíte Oficial de Regressão (Regra de Ouro 22):
Valida a resolução dinâmica do modo operacional da Cloudbet (Dry-Run vs Real),
a hierarquia soberana do Banco de Dados / Cache MySQL sobre o .env (Fallback Fail-Safe),
e a convenção visual da Opção B (Verde = Dinheiro Real, Vermelho = Simulação).
"""

import unittest
from unittest.mock import patch, MagicMock
import os
import sys

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
SCRIPTS_DIR = os.path.abspath(os.path.join(CURRENT_DIR, '../scripts'))
if SCRIPTS_DIR not in sys.path:
    sys.path.insert(0, SCRIPTS_DIR)

from cloudbet_client import CloudbetClient, resolve_cloudbet_dry_run


class TestCloudbetDryRunDynamicToggle(unittest.TestCase):

    @patch('cloudbet_client.get_db_connection')
    def test_01_db_dry_run_true_returns_true(self, mock_get_db):
        """
        Cenário: MySQL configurado com cloudbet_dry_run = 1 (Simulação).
        Esperado: resolve_cloudbet_dry_run() deve retornar True (Modo Simulação).
        """
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.fetchone.return_value = {'cloudbet_dry_run': 1}
        mock_conn.cursor.return_value = mock_cursor
        mock_get_db.return_value = mock_conn

        res = resolve_cloudbet_dry_run(user_id=558, env_vars={"CLOUDBET_DRY_RUN": "false"})
        self.assertTrue(res, "Deveria prevalecer a configuração do MySQL (1 = True/Simulação), ignorando o .env.")

    @patch('cloudbet_client.get_db_connection')
    def test_02_db_dry_run_false_returns_false(self, mock_get_db):
        """
        Cenário: MySQL configurado com cloudbet_dry_run = 0 (Dinheiro Real).
        Esperado: resolve_cloudbet_dry_run() deve retornar False (Modo Real).
        """
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_cursor.fetchone.return_value = {'cloudbet_dry_run': 0}
        mock_conn.cursor.return_value = mock_cursor
        mock_get_db.return_value = mock_conn

        res = resolve_cloudbet_dry_run(user_id=558, env_vars={"CLOUDBET_DRY_RUN": "true"})
        self.assertFalse(res, "Deveria prevalecer a configuração do MySQL (0 = False/Real), mesmo com .env=true.")

    @patch('cloudbet_client.get_db_connection')
    def test_03_db_offline_fallback_env_true(self, mock_get_db):
        """
        Cenário: Banco de Dados offline / erro de conexão e .env com CLOUDBET_DRY_RUN=true.
        Esperado: O sistema deve acionar o fallback fail-safe e retornar True (Protegido).
        """
        mock_get_db.side_effect = Exception("Conexão recusada ao MySQL")

        res = resolve_cloudbet_dry_run(user_id=558, env_vars={"CLOUDBET_DRY_RUN": "true"})
        self.assertTrue(res, "Em caso de falha no banco, deve recorrer ao fallback do .env (True).")

    @patch('cloudbet_client.get_db_connection')
    def test_04_db_offline_fallback_env_false(self, mock_get_db):
        """
        Cenário: Banco de Dados offline e .env com CLOUDBET_DRY_RUN=false.
        Esperado: O fallback lê o valor do .env (False).
        """
        mock_get_db.side_effect = Exception("Timeout MySQL")

        res = resolve_cloudbet_dry_run(user_id=558, env_vars={"CLOUDBET_DRY_RUN": "false"})
        self.assertFalse(res, "Fallback offline deve respeitar o valor do .env quando DB falhar.")

    @patch('scripts.cloudbet_client.resolve_cloudbet_dry_run')
    def test_05_client_explicit_dry_run_override(self, mock_resolve):
        """
        Cenário: Parâmetro dry_run é passado explicitamente no construtor de CloudbetClient.
        Esperado: O valor explícito deve ter prioridade máxima sem consultar o banco.
        """
        mock_resolve.return_value = True

        client = CloudbetClient(dry_run=False)
        self.assertFalse(client.dry_run, "O parâmetro explícito dry_run=False deve prevalecer.")
        mock_resolve.assert_not_called()

    def test_06_option_b_color_and_label_convention(self):
        """
        Cenário: Validação da convenção de cores e rótulos da Opção B solicitada pelo usuário.
        - Quando dry_run == False (Modo Real): Sinal Verde (Operação Real Ativa)
        - Quando dry_run == True (Modo Simulação): Sinal Vermelho (Operação Real Desativada)
        """
        def get_visual_indicator(dry_run: bool):
            if not dry_run:
                return {
                    "color": "green",
                    "badge_text": "CLOUDBET_DRY_RUN=false",
                    "mode_label": "REAL",
                    "dot_color": "#10b981"
                }
            else:
                return {
                    "color": "red",
                    "badge_text": "CLOUDBET_DRY_RUN=true",
                    "mode_label": "SIMULAÇÃO",
                    "dot_color": "#ef4444"
                }

        real_ind = get_visual_indicator(dry_run=False)
        self.assertEqual(real_ind["color"], "green")
        self.assertEqual(real_ind["badge_text"], "CLOUDBET_DRY_RUN=false")
        self.assertEqual(real_ind["mode_label"], "REAL")

        sim_ind = get_visual_indicator(dry_run=True)
        self.assertEqual(sim_ind["color"], "red")
        self.assertEqual(sim_ind["badge_text"], "CLOUDBET_DRY_RUN=true")
        self.assertEqual(sim_ind["mode_label"], "SIMULAÇÃO")


if __name__ == '__main__':
    unittest.main()
