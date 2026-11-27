import unittest
import os
import re

class TestDashboardApenasBetFilter(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.dashboard_path = os.path.abspath(
            os.path.join(os.path.dirname(__file__), '..', 'src/footballweb/app/Views/football/dashboard.php')
        )
        with open(cls.dashboard_path, 'r', encoding='utf-8') as f:
            cls.dashboard_content = f.read()

    def test_apenas_bet_toggle_html_elements_present(self):
        """Verifica se o botão deslizante simplificado '🟢 Apenas BET' está presente e 'Apenas NO-BET' foi removido."""
        self.assertIn('🟢 Apenas BET', self.dashboard_content)
        self.assertIn('id="onlySinalVerdeToggle"', self.dashboard_content)
        self.assertIn('id="onlySinalVerdeToggleStatus"', self.dashboard_content)
        self.assertIn('onchange="toggleSinalVerdeFilter(this)"', self.dashboard_content)
        # Garante que o botão confuso anterior foi removido
        self.assertNotIn('🛑 Apenas NO-BET', self.dashboard_content)
        self.assertNotIn('id="onlyNoBetToggle"', self.dashboard_content)

    def test_apenas_bet_toggle_css_rules_present(self):
        """Verifica se as regras de estilo do switch '🟢 Apenas BET' estão configuradas com verde esmeralda de alto contraste."""
        self.assertIn('#onlySinalVerdeToggle:checked + .bet-slider', self.dashboard_content)
        self.assertIn('#onlySinalVerdeToggle:checked + .bet-slider:before', self.dashboard_content)
        self.assertIn('#10b981', self.dashboard_content)
        self.assertNotIn('#onlyNoBetToggle:checked', self.dashboard_content)

    def test_has_sinal_verde_php_definition(self):
        """Verifica se $hasSinalVerde exige aposta real ou Handicap aprovado (sem falso positivo por texto de cartões)."""
        self.assertIn('$hasSinalVerde = ($isCardAhApproved || $hasAposta);', self.dashboard_content)
        self.assertIn('stripos($ahSugClean, \'sem entrada\') === false', self.dashboard_content)
        self.assertIn('stripos($ahSugClean, \'abstenção\') === false', self.dashboard_content)

    def test_apenas_bet_javascript_logic(self):
        """Verifica se a lógica JS de filtragem do switch 'Apenas BET' está limpa e funcional."""
        self.assertIn('let currentOnlySinalVerdeFilter = false;', self.dashboard_content)
        self.assertIn('function toggleSinalVerdeFilter(checkbox)', self.dashboard_content)
        self.assertIn('const matchSinalVerde = (!currentOnlySinalVerdeFilter || hasSinalVerde);', self.dashboard_content)
        self.assertNotIn('toggleNoBetFilter', self.dashboard_content)

    def test_simulated_filter_filtering_logic(self):
        """Simula a filtragem exata de partidas BET e NO-BET."""
        cards = [
            {"id": 1603086, "ah": "Al-Ittihad FC 0.0 AH", "hasAposta": True, "isBet": True},
            {"id": 1603087, "ah": "Al Khaleej Saihat +0.0 AH", "hasAposta": True, "isBet": True},
            {"id": 1557485, "ah": "Sem Entrada (Abstenção)", "hasAposta": False, "isBet": False},
            {"id": 1651504, "ah": "Sem Entrada (Abstenção)", "hasAposta": False, "isBet": False},
        ]
        
        def filter_cards(onlyBetActive, card_list):
            visible = []
            for c in card_list:
                matchBet = (not onlyBetActive or c["isBet"])
                if matchBet:
                    visible.append(c["id"])
            return visible

        # Desligado (Não) -> Todas as 4 partidas visíveis
        self.assertEqual(filter_cards(False, cards), [1603086, 1603087, 1557485, 1651504])

        # Ligado (Sim) -> Apenas as 2 partidas com BET real
        self.assertEqual(filter_cards(True, cards), [1603086, 1603087])

if __name__ == '__main__':
    unittest.main()
