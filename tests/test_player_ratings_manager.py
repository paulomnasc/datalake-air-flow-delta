import unittest
import sys
import os

# Adiciona o diretório scripts ao sys.path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'scripts'))

from player_ratings_manager import calculate_lineup_strength

class TestPlayerRatingsManager(unittest.TestCase):
    def test_forca_maxima_titulares(self):
        """Verifica se time titular com notas altas é classificado como Força Máxima."""
        start_xi = [
            {"player": {"id": 1, "name": "Goleiro", "pos": "G"}},
            {"player": {"id": 2, "name": "LatD", "pos": "D"}},
            {"player": {"id": 3, "name": "Zag1", "pos": "D"}},
            {"player": {"id": 4, "name": "Zag2", "pos": "D"}},
            {"player": {"id": 5, "name": "LatE", "pos": "D"}},
            {"player": {"id": 6, "name": "Mei1", "pos": "M"}},
            {"player": {"id": 7, "name": "Mei2", "pos": "M"}},
            {"player": {"id": 8, "name": "Mei3", "pos": "M"}},
            {"player": {"id": 9, "name": "Ata1", "pos": "F"}},
            {"player": {"id": 10, "name": "Ata2", "pos": "F"}},
            {"player": {"id": 11, "name": "Ata3", "pos": "F"}},
        ]
        ratings_map = {
            1: {"rating_media": 6.8, "rating_ultimos5": 6.9, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            2: {"rating_media": 6.9, "rating_ultimos5": 7.0, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            3: {"rating_media": 7.1, "rating_ultimos5": 7.2, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            4: {"rating_media": 7.0, "rating_ultimos5": 7.1, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            5: {"rating_media": 6.8, "rating_ultimos5": 6.8, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            6: {"rating_media": 7.2, "rating_ultimos5": 7.4, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            7: {"rating_media": 7.3, "rating_ultimos5": 7.5, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            8: {"rating_media": 6.9, "rating_ultimos5": 7.0, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            9: {"rating_media": 7.4, "rating_ultimos5": 7.6, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            10: {"rating_media": 7.1, "rating_ultimos5": 7.2, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            11: {"rating_media": 6.8, "rating_ultimos5": 6.8, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
        }
        res = calculate_lineup_strength(start_xi, ratings_map)
        self.assertTrue(res["is_forca_maxima"])
        self.assertFalse(res["is_queda_estrutural"])
        self.assertGreaterEqual(res["rating_medio_xi"], 6.70)
        self.assertIn("Força Máxima", res["resumo_qualitativo"])

    def test_queda_estrutural_time_reserva(self):
        """Verifica se time com muitos jovens/reservas com notas baixas dispara Queda Estrutural."""
        start_xi = [
            {"player": {"id": i, "name": f"P{i}", "pos": "M"}} for i in range(1, 12)
        ]
        ratings_map = {
            # 6 jogadores com notas baixas e 5 estreantes sem histórico
            1: {"rating_media": 5.9, "rating_ultimos5": 5.8, "jogos_titular": 2, "minutos_jogados": 120, "is_estreante_base": 0},
            2: {"rating_media": 6.0, "rating_ultimos5": 5.9, "jogos_titular": 1, "minutos_jogados": 80, "is_estreante_base": 0},
            3: {"rating_media": 6.1, "rating_ultimos5": 6.0, "jogos_titular": 3, "minutos_jogados": 150, "is_estreante_base": 0},
            4: {"rating_media": 5.8, "rating_ultimos5": 5.7, "jogos_titular": 1, "minutos_jogados": 60, "is_estreante_base": 0},
            5: {"rating_media": 6.2, "rating_ultimos5": 6.1, "jogos_titular": 2, "minutos_jogados": 90, "is_estreante_base": 0},
            6: {"rating_media": 5.9, "rating_ultimos5": 5.8, "jogos_titular": 1, "minutos_jogados": 45, "is_estreante_base": 0},
        }
        res = calculate_lineup_strength(start_xi, ratings_map)
        self.assertFalse(res["is_forca_maxima"])
        self.assertTrue(res["is_queda_estrutural"])
        self.assertLess(res["rating_medio_xi"], 6.30)
        self.assertGreaterEqual(len(res["estreantes_vulneraveis"]), 3)
        self.assertIn("Desfalque Estrutural", res["resumo_qualitativo"])

    def test_prevencao_falso_positivo_chelsea(self):
        """Caso real: Chelsea x Nottingham Forest. Chelsea fez 3 trocas e tinha 5 lesões no DM,
        mas escalou Palmer (6.6), Caicedo (6.9), Neto, Lavia etc. NÃO deve disparar Queda Estrutural."""
        start_xi = [
            {"player": {"id": 101, "name": "Robert Sanchez", "pos": "G"}},
            {"player": {"id": 102, "name": "Malo Gusto", "pos": "D"}},
            {"player": {"id": 103, "name": "Wesley Fofana", "pos": "D"}},
            {"player": {"id": 104, "name": "Levi Colwill", "pos": "D"}},
            {"player": {"id": 105, "name": "Marc Cucurella", "pos": "D"}},
            {"player": {"id": 106, "name": "Moises Caicedo", "pos": "M"}},
            {"player": {"id": 107, "name": "Romeo Lavia", "pos": "M"}},
            {"player": {"id": 108, "name": "Cole Palmer", "pos": "M"}},
            {"player": {"id": 109, "name": "Pedro Neto", "pos": "F"}},
            {"player": {"id": 110, "name": "Noni Madueke", "pos": "F"}},
            {"player": {"id": 111, "name": "Nicolas Jackson", "pos": "F"}},
        ]
        ratings_map = {
            101: {"rating_media": 6.5, "rating_ultimos5": 6.4, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            102: {"rating_media": 6.5, "rating_ultimos5": 6.6, "jogos_titular": 8, "minutos_jogados": 720, "is_estreante_base": 0},
            103: {"rating_media": 6.6, "rating_ultimos5": 6.5, "jogos_titular": 9, "minutos_jogados": 810, "is_estreante_base": 0},
            104: {"rating_media": 6.7, "rating_ultimos5": 6.8, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            105: {"rating_media": 6.6, "rating_ultimos5": 6.5, "jogos_titular": 9, "minutos_jogados": 800, "is_estreante_base": 0},
            106: {"rating_media": 6.9, "rating_ultimos5": 7.0, "jogos_titular": 10, "minutos_jogados": 900, "is_estreante_base": 0},
            107: {"rating_media": 6.4, "rating_ultimos5": 6.5, "jogos_titular": 5, "minutos_jogados": 420, "is_estreante_base": 0},
            108: {"rating_media": 6.6, "rating_ultimos5": 6.7, "jogos_titular": 10, "minutos_jogados": 850, "is_estreante_base": 0},
            109: {"rating_media": 6.5, "rating_ultimos5": 6.4, "jogos_titular": 7, "minutos_jogados": 600, "is_estreante_base": 0},
            110: {"rating_media": 6.4, "rating_ultimos5": 6.3, "jogos_titular": 8, "minutos_jogados": 650, "is_estreante_base": 0},
            111: {"rating_media": 6.5, "rating_ultimos5": 6.6, "jogos_titular": 9, "minutos_jogados": 780, "is_estreante_base": 0},
        }
        res = calculate_lineup_strength(start_xi, ratings_map)
        self.assertFalse(res["is_queda_estrutural"], "Chelsea titular NÃO deve sofrer queda estrutural.")
        self.assertGreaterEqual(res["rating_medio_xi"], 6.30)
        self.assertEqual(len(res["estreantes_vulneraveis"]), 0)
        self.assertIn("Elenco Competitivo Estável", res["resumo_qualitativo"])

    def test_resilient_import_in_isolated_scripts_context(self):
        """Verifica se o módulo suporta importação direta sem depender de 'scripts.' no namespace."""
        import importlib
        try:
            mod = importlib.import_module("player_ratings_manager")
            self.assertTrue(hasattr(mod, "get_players_ratings_map"))
            self.assertTrue(hasattr(mod, "calculate_lineup_strength"))
            self.assertTrue(hasattr(mod, "enrich_fixture_player_ratings_from_api"))
        except ImportError as e:
            self.fail(f"Falha ao importar player_ratings_manager diretamente: {e}")

if __name__ == '__main__':
    unittest.main()
