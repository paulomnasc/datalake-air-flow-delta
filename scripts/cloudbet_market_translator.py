#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
scripts/cloudbet_market_translator.py

Tabela Hash Canônica e De-Para Universal de Mercados e Seleções da Cloudbet.
Converte URLs técnicas de mercado (market_url) e seleções brutas em nomenclaturas
padronizadas, legíveis e semanticamente ricas para o FootballWeb.

Regra 8: inicialização prévia de variáveis locais.
Regra 12: dados estritamente reais de mercado sem arbitração sintética.
"""

import re
from typing import Tuple, Dict, Any, Optional


class CloudbetMarketTranslator:
    """
    Motor Canônico de Tradução De-Para de Mercados da Cloudbet.
    """

    # 1. Tabela Hash Canônica de Famílias de Mercado
    MARKET_FAMILY_MAP: Dict[str, str] = {
        "soccer.draw_no_bet": "Empate Anula a Aposta (DNB)",
        "soccer.asian_handicap": "Handicap Asiático",
        "soccer.total_goals": "Total de Gols",
        "soccer.total_bookings": "Total de Cartões",
        "soccer.total_booking_points": "Total de Cartões",
        "soccer.cards": "Total de Cartões",
        "soccer.both_teams_to_score": "Ambas Marcam",
        "soccer.match_odds": "Resultado Final (1X2)",
        "soccer.double_chance": "Dupla Hipótese",
        "soccer.which_team_wins_the_rest_of_the_match": "Resto da Partida (In-Play)",
        "soccer.total_corners": "Total de Escanteios",
        "soccer.corner_handicap": "Handicap de Escanteios",
        "soccer.correct_score": "Placar Exato",
        "soccer.half_time_full_time": "Intervalo / Final do Jogo (HT/FT)",
    }

    @classmethod
    def translate(
        cls,
        market_url: str,
        home_team: str = "Mandante",
        away_team: str = "Visitante",
        is_live: bool = False
    ) -> Tuple[str, str]:
        """
        Traduz uma marketUrl da Cloudbet para (nome_mercado, nome_palpite).
        Exemplos:
          - 'soccer.draw_no_bet/away' -> ('Empate Anula a Aposta (DNB)', 'FC Midtjylland (DNB)')
          - 'soccer.total_goals/over?total=2' -> ('Gols Asiáticos (In-Play)', 'Mais de 2.0 Gols Asiáticos')
          - 'soccer.total_booking_points/under?total=55.5' -> ('Total de Cartões', 'Menos de 5.5 Cartões')
          - 'soccer.asian_handicap/away?handicap=-0.25' -> ('Handicap Asiático', 'FC Midtjylland -0.25 AH')
        """
        url_raw = str(market_url or "").strip()
        url_lower = url_raw.lower()
        ht = str(home_team or "Mandante").strip()
        at = str(away_team or "Visitante").strip()

        # Extrai a chave base da família (antes da barra '/')
        base_family = url_raw.split("/")[0].strip() if "/" in url_raw else url_raw.split("?")[0].strip()
        base_family_lower = base_family.lower()

        # Obtém o nome canônico do mercado
        mercado_nome = cls.MARKET_FAMILY_MAP.get(base_family_lower, "Especiais Cloudbet")

        # -------------------------------------------------------------
        # 1. DRAW NO BET (DNB / Empate Anula)
        # -------------------------------------------------------------
        if "draw_no_bet" in url_lower or "dnb" in url_lower:
            mercado_nome = "Empate Anula a Aposta (DNB)"
            if "/away" in url_lower or "=away" in url_lower:
                return mercado_nome, f"{at} (DNB)"
            elif "/home" in url_lower or "=home" in url_lower:
                return mercado_nome, f"{ht} (DNB)"
            return mercado_nome, f"DNB {at if 'away' in url_lower else ht}"

        # -------------------------------------------------------------
        # 2. TOTAL DE GOLS (Over / Under, Inteiros e Fracionários)
        # -------------------------------------------------------------
        if "total_goals" in url_lower or "goal" in url_lower:
            m_t = re.search(r'total=(\d+(?:\.\d+)?)', url_raw)
            tot_val = float(m_t.group(1)) if m_t else 2.5
            is_ov = ("/over" in url_lower or "outcome=over" in url_lower or "mais" in url_lower)

            if is_live or "in-play" in url_lower:
                mercado_nome = "Gols Asiáticos (In-Play)"
                prefix = "Mais de" if is_ov else "Menos de"
                return mercado_nome, f"{prefix} {tot_val:g} Gols (In-Play)"
            else:
                is_asian = (round(tot_val % 0.5, 2) != 0.0 or round(tot_val % 1.0, 2) == 0.0)
                mercado_nome = "Gols Asiáticos" if is_asian else "Total de Gols"
                prefix = "Mais de" if is_ov else "Menos de"
                suffix = " Gols Asiáticos" if is_asian else " Gols"
                return mercado_nome, f"{prefix} {tot_val:g}{suffix}"

        # -------------------------------------------------------------
        # 3. HANDICAP ASIÁTICO
        # -------------------------------------------------------------
        if "asian_handicap" in url_lower or "handicap" in url_lower:
            mercado_nome = "Handicap Asiático"
            m_h = re.search(r'handicap=([+-]?\d+(?:\.\d+)?)', url_raw)
            h_val = float(m_h.group(1)) if m_h else 0.0
            is_away = ("/away" in url_lower or "outcome=away" in url_lower)
            team_sel = at if is_away else ht
            if abs(h_val) < 0.001:
                return mercado_nome, f"{team_sel} 0.0 AH"
            return mercado_nome, f"{team_sel} {h_val:+.2f} AH"

        # -------------------------------------------------------------
        # 4. CARTÕES E BOOKING POINTS
        # -------------------------------------------------------------
        if "booking" in url_lower or "cards" in url_lower or "cart" in url_lower:
            mercado_nome = "Total de Cartões"
            m_c = re.search(r'total=(\d+(?:\.\d+)?)', url_raw)
            raw_c = float(m_c.group(1)) if m_c else 5.5
            # Normalização: Booking Points (55.5 pts -> 5.5 cartões, 65.5 pts -> 6.5 cartões)
            tot_cards = round(raw_c / 10.0, 1) if raw_c > 15.0 else raw_c
            is_ov = ("/over" in url_lower or "outcome=over" in url_lower or "mais" in url_lower)
            prefix = "Mais de" if is_ov else "Menos de"
            return mercado_nome, f"{prefix} {tot_cards:g} Cartões"

        # -------------------------------------------------------------
        # 5. AMBAS MARCAM (BTTS)
        # -------------------------------------------------------------
        if "both_teams_to_score" in url_lower or "btts" in url_lower:
            mercado_nome = "Ambas Marcam"
            is_yes = ("/yes" in url_lower or "outcome=yes" in url_lower or "sim" in url_lower)
            return mercado_nome, f"Ambas Equipes Marcam: {'Sim' if is_yes else 'Não'}"

        # -------------------------------------------------------------
        # 6. RESULTADO FINAL (1X2 / Match Odds)
        # -------------------------------------------------------------
        if "match_odds" in url_lower or "/1x2" in url_lower:
            mercado_nome = "Resultado Final (1X2)"
            if "/away" in url_lower or "=away" in url_lower or "/2" in url_lower:
                return mercado_nome, f"{at} Vence"
            elif "/draw" in url_lower or "=draw" in url_lower or "/x" in url_lower:
                return mercado_nome, "Empate"
            return mercado_nome, f"{ht} Vence"

        # -------------------------------------------------------------
        # 7. DUPLA HIPÓTESE (Double Chance)
        # -------------------------------------------------------------
        if "double_chance" in url_lower:
            mercado_nome = "Dupla Hipótese"
            if "home_draw" in url_lower or "1x" in url_lower:
                return mercado_nome, f"{ht} ou Empate (1X)"
            elif "away_draw" in url_lower or "x2" in url_lower:
                return mercado_nome, f"{at} ou Empate (X2)"
            elif "home_away" in url_lower or "12" in url_lower:
                return mercado_nome, f"{ht} ou {at} (12)"

        # -------------------------------------------------------------
        # 8. RESTO DA PARTIDA (In-Play Which Team Wins)
        # -------------------------------------------------------------
        if "which_team_wins_the_rest_of_the_match" in url_lower:
            mercado_nome = "Resto da Partida (In-Play)"
            if "/away" in url_lower:
                return mercado_nome, f"{at} Vence o Resto da Partida"
            elif "/draw" in url_lower:
                return mercado_nome, "Empate no Resto da Partida"
            return mercado_nome, f"{ht} Vence o Resto da Partida"

        # Fallback Seguro e Legível
        return mercado_nome, url_raw
