import unittest
from unittest.mock import MagicMock, patch
import sys
import os

# Adiciona o diretório scripts ao sys.path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', 'scripts'))

import cards_engine

class TestCardsEngineRefereeEnrichment(unittest.TestCase):
    def test_sys_module_imported_in_cards_engine(self):
        """Verifica se o módulo sys está devidamente importado no escopo global de cards_engine."""
        self.assertIn("sys", cards_engine.__dict__, "Módulo 'sys' deve estar importado no escopo de cards_engine.")
        self.assertIs(cards_engine.sys, sys, "Referência de 'sys' em cards_engine deve corresponder ao módulo sys nativo.")

    @patch("cards_engine.os.path.exists")
    def test_enrich_referees_fallback_futbol24_executa_sem_name_error(self, mock_path_exists):
        """
        Verifica se a contingência Futbol24 executa com sucesso sem disparar NameError: name 'sys' is not defined
        e persiste corretamente o árbitro enriquecido em referee_stats e fixtures_trends.
        """
        mock_path_exists.return_value = False

        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value = mock_cursor

        # Simula resposta do SELECT inicial no banco
        mock_cursor.fetchone.return_value = {
            "referee_name": None,
            "referee_api_checked_at": None,
            "home_team": "Flamengo",
            "away_team": "Palmeiras",
            "league_id": 71
        }

        target_fixtures = [
            {
                "fixture_id": 999901,
                "home_team": "Flamengo",
                "away_team": "Palmeiras",
                "league_name": "Brasileirão Série A",
                "league_id": 71,
                "fixture_date": "2026-11-28 16:00:00",
                "referee_name": None
            }
        ]

        # Simula o scraper retornando árbitro para a fixture
        mock_scraper = MagicMock(return_value={999901: "Wilton Pereira Sampaio"})

        mock_lib = MagicMock()
        mock_scrapers = MagicMock()
        mock_scrapers.scrape_futbol24_referees_for_fixtures = mock_scraper
        mock_lib.scrapers = mock_scrapers

        with patch.dict("sys.modules", {"lib": mock_lib, "lib.scrapers": mock_scrapers}):
            with patch("requests.get") as mock_api_get:
                # API-Sports retorna sem árbitro para acionar o fallback Futbol24
                mock_api_get.return_value.status_code = 200
                mock_api_get.return_value.json.return_value = {
                    "response": [
                        {
                            "fixture": {
                                "id": 999901,
                                "referee": None
                            }
                        }
                    ]
                }

                enriched = cards_engine.enrich_missing_referees_batch(
                    cursor=mock_cursor,
                    conn=mock_conn,
                    target_fixtures=target_fixtures
                )

        # Asserções de negócio e estabilidade
        self.assertIn(999901, enriched, "Fixture 999901 deve constar no dicionário de árbitros enriquecidos.")
        self.assertEqual(enriched[999901], "Wilton Pereira Sampaio")

        # Verifica se gravou no banco (referee_stats e fixtures_trends) e comitou
        self.assertTrue(mock_cursor.execute.called, "Cursor deve ter sido chamado para persistir dados.")
        self.assertTrue(mock_conn.commit.called, "Conexão deve ter efetuado commit das atualizações.")

    def test_enrich_referees_fallback_graceful_on_scraper_error(self):
        """Verifica se exceções na consulta ao Futbol24 são capturadas com graceful degradation sem quebrar o pipeline."""
        mock_conn = MagicMock()
        mock_cursor = MagicMock()
        mock_conn.cursor.return_value = mock_cursor

        mock_cursor.fetchone.return_value = {
            "referee_name": None,
            "referee_api_checked_at": None,
            "home_team": "Flamengo",
            "away_team": "Palmeiras",
            "league_id": 71
        }

        target_fixtures = [
            {"fixture_id": 999902, "league_id": 71, "home_team": "Flamengo", "away_team": "Palmeiras", "referee_name": None}
        ]

        mock_lib = MagicMock()
        mock_scrapers = MagicMock()
        mock_scrapers.scrape_futbol24_referees_for_fixtures.side_effect = Exception("Futbol24 connection error")
        mock_lib.scrapers = mock_scrapers

        with patch.dict("sys.modules", {"lib": mock_lib, "lib.scrapers": mock_scrapers}):
            with patch("requests.get") as mock_api_get:
                mock_api_get.return_value.status_code = 200
                mock_api_get.return_value.json.return_value = {"response": []}

                try:
                    enriched = cards_engine.enrich_missing_referees_batch(
                        cursor=mock_cursor,
                        conn=mock_conn,
                        target_fixtures=target_fixtures
                    )
                    self.assertIsInstance(enriched, dict)
                    self.assertNotIn(999902, enriched)
                except Exception as exc:
                    self.fail(f"Pipeline não deve quebrar com exceção não tratada: {exc}")

if __name__ == "__main__":
    unittest.main()
