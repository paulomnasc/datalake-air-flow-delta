#!/usr/bin/env python3
"""
scripts/player_ratings_manager.py

Módulo Canônico de Gestão de Notas de Rendimento de Jogadores (Player Ratings)
FootballWeb Pipeline - Governança Qualitativa de Súmulas e Escalações Oficiais.

Diretrizes:
- Regra 1: Cache-First no MySQL (player_ratings_cache). Pré-jogo com ZERO chamadas de API.
- Regra 12: Proibição de dados sintéticos ou multiplicadores artificiais.
- Salvaguarda de Cota: Bloqueio automático de enriquecimento externo caso o consumo diário da API-Sports atinja 80% (6.000 requisições).
"""

import os
import json
import requests
from typing import Dict, List, Tuple, Optional
from datetime import datetime


def get_apisports_key() -> str:
    """Obtém a chave da API-Sports de forma segura a partir do módulo canônico (Regra 18)."""
    try:
        from scripts.gemini_sentinel import get_apisports_key as _gk
        return _gk()
    except Exception:
        pass
    try:
        from gemini_sentinel import get_apisports_key as _gk
        return _gk()
    except Exception:
        pass
    return os.environ.get("APISPORTS_API_KEY") or os.environ.get("API_SPORTS_KEY") or ""


def is_apisports_quota_safe(max_usage_pct: float = 0.80) -> bool:
    """
    Verifica se a cota diária da API-Sports está segura (< 80%).
    Retorna True se seguro, False se próximo do esgotamento.
    """
    api_key = get_apisports_key()
    if not api_key:
        return False
    try:
        r = requests.get(
            "https://v3.football.api-sports.io/status",
            headers={"x-apisports-key": api_key},
            timeout=4
        )
        if r.status_code == 200:
            data = r.json()
            reqs = (data.get("response") or {}).get("requests") or {}
            current = int(reqs.get("current") or 0)
            limit_day = int(reqs.get("limit_day") or 7500)
            if limit_day > 0 and (current / limit_day) >= max_usage_pct:
                print(f"🛑 [Salvaguarda Cota API-Sports] Consumo diário em {current}/{limit_day} ({current/limit_day*100:.1f}%). Enriquecimento pausado.")
                return False
            return True
    except Exception as e:
        print(f"⚠️ [Check Cota API-Sports] Falha ao verificar status: {e}")
    return True


def enrich_team_player_ratings_from_api(cursor, conn, team_id: int, season: int = None, force: bool = False) -> int:
    """
    Enriquece a tabela player_ratings_cache com as notas reais e estatísticas de todos os atletas de um time na temporada.
    Consulta a API-Sports (/players?team=X&season=Y) quando os atletas do time ainda não possuem notas registradas.
    Possui paginação automática e fallback para temporada anterior caso o elenco tenha pouca amostragem.
    Respeita estritamente a salvaguarda de cota da API (Regra 3, item 1).
    """
    if not team_id:
        return 0
    if not force and not is_apisports_quota_safe(0.80):
        return 0

    api_key = get_apisports_key()
    if not api_key:
        return 0

    if not season:
        season = datetime.now().year

    total_inserted = 0
    headers = {"x-apisports-key": api_key}

    def _fetch_and_save_season_players(s_year: int) -> int:
        nonlocal total_inserted
        current_page = 1
        total_pages = 1
        inserted_this_season = 0

        while current_page <= total_pages and current_page <= 3:
            url = f"https://v3.football.api-sports.io/players?team={team_id}&season={s_year}&page={current_page}"
            try:
                r = requests.get(url, headers=headers, timeout=12)
                if r.status_code != 200:
                    break
                data = r.json()
                paging = data.get("paging") or {}
                total_pages = int(paging.get("total") or 1)
                players_list = data.get("response") or []
                if not players_list:
                    break

                for item in players_list:
                    p_obj = item.get("player") or {}
                    p_id = int(p_obj.get("id") or 0)
                    p_name = p_obj.get("name") or "Atleta"
                    
                    stats_list = item.get("statistics") or []
                    if not stats_list:
                        continue

                    for stats in stats_list:
                        games = stats.get("games") or {}
                        raw_rating = games.get("rating")
                        if not raw_rating:
                            continue

                        try:
                            match_rating = round(float(raw_rating), 2)
                        except (ValueError, TypeError):
                            continue

                        league_id = int((stats.get("league") or {}).get("id") or 0)
                        minutes = int(games.get("minutes") or 0)
                        lineups_cnt = int(games.get("lineups") or 0)
                        apps_cnt = int(games.get("appearences") or 0)
                        subs_cnt = max(0, apps_cnt - lineups_cnt)
                        pos = str(games.get("position") or "M")[:1].upper()
                        if pos not in ("G", "D", "M", "F"):
                            pos = "M"

                        goals = int((stats.get("goals") or {}).get("total") or 0)
                        assists = int((stats.get("goals") or {}).get("assists") or 0)

                        cursor.execute("""
                            INSERT INTO player_ratings_cache (
                                player_id, team_id, league_id, season, player_name, position,
                                rating_media, rating_ultimos5, minutos_jogados, jogos_titular, jogos_reserva,
                                gols, assistencias, is_estreante_base, updated_at
                            ) VALUES (
                                %s, %s, %s, %s, %s, %s,
                                %s, %s, %s, %s, %s,
                                %s, %s, 0, NOW()
                            )
                            ON DUPLICATE KEY UPDATE
                                rating_ultimos5 = VALUES(rating_media),
                                rating_media = VALUES(rating_media),
                                minutos_jogados = VALUES(minutos_jogados),
                                jogos_titular = VALUES(jogos_titular),
                                jogos_reserva = VALUES(jogos_reserva),
                                gols = VALUES(gols),
                                assistencias = VALUES(assistencias),
                                updated_at = NOW()
                        """, (
                            p_id, team_id, league_id, s_year, p_name, pos,
                            match_rating, match_rating, minutes, lineups_cnt, subs_cnt,
                            goals, assists
                        ))
                        inserted_this_season += 1
                        total_inserted += 1

                current_page += 1
            except Exception as e_page:
                print(f"⚠️ [EnrichTeamPlayerRatings Erro Time #{team_id} Pág {current_page}]: {e_page}")
                break

        return inserted_this_season

    try:
        inserted = _fetch_and_save_season_players(season)
        # Se a temporada atual retornou menos de 10 atletas com notas, consulta a temporada anterior para complementar
        if inserted < 10 and season == datetime.now().year and is_apisports_quota_safe(0.80):
            _fetch_and_save_season_players(season - 1)

        if conn and total_inserted > 0:
            conn.commit()

        return total_inserted
    except Exception as e:
        print(f"⚠️ [EnrichTeamPlayerRatings Erro Time #{team_id}]: {e}")
        return 0


def get_players_ratings_map(cursor, player_ids: List[int], season: int = None) -> Dict[int, dict]:
    """
    Consulta 100% local no MySQL (Cache-First) para obter as notas de rendimento dos atletas.
    Retorna: {player_id: {'rating_media': float, 'minutos_jogados': int, 'jogos_titular': int, ...}}
    """
    if not player_ids:
        return {}

    placeholders = ",".join(["%s"] * len(player_ids))
    params = list(player_ids)
    sql = f"""
        SELECT player_id, team_id, player_name, position, rating_media, rating_ultimos5,
               minutos_jogados, jogos_titular, jogos_reserva, gols, assistencias, is_estreante_base
        FROM player_ratings_cache
        WHERE player_id IN ({placeholders})
    """
    if season:
        sql += " AND season = %s"
        params.append(season)

    try:
        cursor.execute(sql, tuple(params))
        rows = cursor.fetchall()
        ratings_map = {}
        for r in rows:
            p_id = int(r["player_id"])
            ratings_map[p_id] = {
                "player_id": p_id,
                "player_name": r.get("player_name", ""),
                "position": r.get("position", "M"),
                "rating_media": float(r.get("rating_media") or 6.50),
                "rating_ultimos5": float(r["rating_ultimos5"]) if r.get("rating_ultimos5") is not None else None,
                "minutos_jogados": int(r.get("minutos_jogados") or 0),
                "jogos_titular": int(r.get("jogos_titular") or 0),
                "gols": int(r.get("gols") or 0),
                "assistencias": int(r.get("assistencias") or 0),
                "is_estreante_base": bool(r.get("is_estreante_base", 0))
            }
        return ratings_map
    except Exception as e:
        print(f"⚠️ [PlayerRatingsCache MySQL Erro]: {e}")
        return {}


def calculate_lineup_strength(start_xi_players: List[dict], ratings_map: Dict[int, dict]) -> dict:
    """
    Avalia a força técnica real dos 11 titulares com base nas notas individuais de rendimento.
    
    Retorna:
    {
        'rating_medio_xi': float,
        'pilares_altos': list,       # Atletas com rating >= 6.90
        'pilares_medios': list,      # Atletas com rating entre 6.50 e 6.89
        'estreantes_vulneraveis': list, # Atletas com rating < 6.20 ou estreantes sem amostragem
        'goleiro_info': dict,
        'setor_defesa_avg': float,
        'setor_meio_avg': float,
        'setor_ataque_avg': float,
        'is_forca_maxima': bool,
        'is_queda_estrutural': bool,
        'resumo_qualitativo': str
    }
    """
    if not start_xi_players:
        return {
            "rating_medio_xi": 6.50,
            "pilares_altos": [],
            "pilares_medios": [],
            "estreantes_vulneraveis": [],
            "goleiro_info": {},
            "setor_defesa_avg": 6.50,
            "setor_meio_avg": 6.50,
            "setor_ataque_avg": 6.50,
            "is_forca_maxima": True,
            "is_queda_estrutural": False,
            "resumo_qualitativo": "Súmula sem dados detalhados de atletas."
        }

    total_rating = 0.0
    cnt = 0
    pilares_altos = []
    pilares_medios = []
    estreantes_vulneraveis = []
    goleiro_info = {}

    def_ratings = []
    mid_ratings = []
    att_ratings = []

    for item in start_xi_players:
        p_obj = item.get("player") or {}
        p_id = int(p_obj.get("id") or 0)
        p_name = p_obj.get("name") or "Atleta"
        p_pos = str(p_obj.get("pos") or "M").upper()

        cached = ratings_map.get(p_id)
        if cached:
            rating = cached["rating_media"]
            minutos = cached["minutos_jogados"]
            is_untested = (minutos == 0 and cached.get("jogos_titular", 0) == 0 and cached.get("jogos_reserva", 0) == 0)
            total_rating += rating
            cnt += 1
        else:
            # Atleta sem histórico prévio no banco local:
            # Regra 9 e Regra 12: NÃO atribuir nota sintética ou mágica (sem fallback 6.50)!
            rating = None
            minutos = 0
            is_untested = True

        p_info = {
            "id": p_id,
            "name": p_name,
            "pos": p_pos,
            "rating": rating,
            "minutos": minutos,
            "is_untested": is_untested
        }

        if rating is not None:
            if p_pos == "G":
                goleiro_info = p_info
                def_ratings.append(rating)
            elif p_pos == "D":
                def_ratings.append(rating)
            elif p_pos == "M":
                mid_ratings.append(rating)
            elif p_pos == "F":
                att_ratings.append(rating)

            if rating >= 6.80:
                pilares_altos.append(p_info)
            elif rating >= 6.40:
                pilares_medios.append(p_info)
            elif rating < 6.00:
                estreantes_vulneraveis.append(p_info)
        else:
            estreantes_vulneraveis.append(p_info)

    rating_medio_xi = round(total_rating / max(1, cnt), 2) if cnt > 0 else None
    def_avg = round(sum(def_ratings) / max(1, len(def_ratings)), 2)
    mid_avg = round(sum(mid_ratings) / max(1, len(mid_ratings)), 2)
    att_avg = round(sum(att_ratings) / max(1, len(att_ratings)), 2)

    # Critérios Qualitativos de Decisão:
    # 1. Força Máxima: Média dos titulares >= 6.70 e no máximo 1 atleta em observação/baixa nota
    is_forca_maxima = (rating_medio_xi >= 6.70 and len(estreantes_vulneraveis) <= 1)
    
    # 2. Queda Estrutural: Média dos titulares < 6.30 OU 3 ou mais novatos vulneráveis sem pilares altos
    is_queda_estrutural = (rating_medio_xi < 6.30 or (len(estreantes_vulneraveis) >= 3 and len(pilares_altos) == 0))

    if is_forca_maxima:
        resumo = f"Força Máxima Confirmada (Nota Média dos 11: {rating_medio_xi:.2f} | {len(pilares_altos)} pilares de alto rendimento)"
    elif is_queda_estrutural:
        nomes_vulneraveis = ", ".join([p["name"] for p in estreantes_vulneraveis[:3]])
        resumo = f"Desfalque Estrutural no Elenco (Nota Média dos 11: {rating_medio_xi:.2f} | Queda técnica com {nomes_vulneraveis})"
    else:
        resumo = f"Elenco Competitivo Estável (Nota Média dos 11: {rating_medio_xi:.2f} | Rotação tática padrão)"

    return {
        "rating_medio_xi": rating_medio_xi,
        "pilares_altos": pilares_altos,
        "pilares_medios": pilares_medios,
        "estreantes_vulneraveis": estreantes_vulneraveis,
        "goleiro_info": goleiro_info,
        "setor_defesa_avg": def_avg,
        "setor_meio_avg": mid_avg,
        "setor_ataque_avg": att_avg,
        "is_forca_maxima": is_forca_maxima,
        "is_queda_estrutural": is_queda_estrutural,
        "resumo_qualitativo": resumo
    }


def enrich_fixture_player_ratings_from_api(cursor, conn, fixture_id: int, force: bool = False) -> int:
    """
    Enriquece a tabela player_ratings_cache com as notas reais da partida terminada (FT).
    Executada no pós-jogo / settlement, consumindo 1 chamada da API com salvaguarda de cota.
    """
    if not force and not is_apisports_quota_safe(0.80):
        return 0

    api_key = get_apisports_key()
    if not api_key:
        return 0

    url = f"https://v3.football.api-sports.io/fixtures/players?fixture={fixture_id}"
    headers = {"x-apisports-key": api_key}

    try:
        r = requests.get(url, headers=headers, timeout=12)
        if r.status_code != 200:
            return 0
        data = r.json()
        teams_data = data.get("response") or []
        if not teams_data:
            return 0

        # Obter league_id e calcular season a partir da fixture_date
        cursor.execute("SELECT league_id, fixture_date FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
        fix_row = cursor.fetchone()
        league_id = int(fix_row["league_id"]) if fix_row and fix_row.get("league_id") else 0
        if fix_row and fix_row.get("fixture_date"):
            f_dt = fix_row["fixture_date"]
            season = f_dt.year if hasattr(f_dt, "year") else datetime.now().year
        else:
            season = datetime.now().year

        players_updated = 0

        for t in teams_data:
            team_id = int(t["team"]["id"])
            for p in t.get("players", []):
                p_id = int(p["player"]["id"])
                p_name = p["player"]["name"]
                stats = p.get("statistics", [{}])[0]

                games = stats.get("games") or {}
                raw_rating = games.get("rating")
                if not raw_rating:
                    continue

                try:
                    match_rating = float(raw_rating)
                except ValueError:
                    continue

                minutes = int(games.get("minutes") or 0)
                is_sub = bool(games.get("substitute"))
                pos = str(games.get("position") or "M")[:1].upper()
                if pos not in ("G", "D", "M", "F"):
                    pos = "M"

                goals = int((stats.get("goals") or {}).get("total") or 0)
                assists = int((stats.get("goals") or {}).get("assists") or 0)

                # Upsert com média móvel ponderada
                cursor.execute("""
                    INSERT INTO player_ratings_cache (
                        player_id, team_id, league_id, season, player_name, position,
                        rating_media, rating_ultimos5, minutos_jogados, jogos_titular, jogos_reserva,
                        gols, assistencias, is_estreante_base, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, %s, %s,
                        %s, %s, %s, %s, %s,
                        %s, %s, 0, NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        rating_ultimos5 = VALUES(rating_media),
                        rating_media = ROUND((rating_media * (jogos_titular + jogos_reserva) + %s) / (jogos_titular + jogos_reserva + 1), 2),
                        minutos_jogados = minutos_jogados + VALUES(minutos_jogados),
                        jogos_titular = jogos_titular + VALUES(jogos_titular),
                        jogos_reserva = jogos_reserva + VALUES(jogos_reserva),
                        gols = gols + VALUES(gols),
                        assistencias = assistencias + VALUES(assistencias),
                        is_estreante_base = 0,
                        updated_at = NOW()
                """, (
                    p_id, team_id, league_id, season, p_name, pos,
                    match_rating, match_rating, minutes, 0 if is_sub else 1, 1 if is_sub else 0,
                    goals, assists,
                    match_rating
                ))
                players_updated += 1

        conn.commit()
        return players_updated

    except Exception as e:
        print(f"⚠️ [EnrichPlayerRatings Erro #{fixture_id}]: {e}")
        return 0
