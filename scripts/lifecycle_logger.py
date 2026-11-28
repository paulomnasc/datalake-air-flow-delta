#!/usr/bin/env python3
"""
Módulo Canônico de Logging Transversal e Rastreamento de Ciclo de Vida
Pipeline FootballWeb

Registra todas as transições de estado, decisões do Gatekeeper, auditorias da IA Sentinela,
mudanças de odds e envio de alertas no Sininho com data/hora e ID da notificação.
"""

import json
from datetime import datetime
from typing import Optional, Dict, Any


def format_match_moment(status: str, elapsed: Optional[int] = None) -> str:
    """
    Formata o momento da partida em linguagem natural padronizada para auditoria cronológica.
    Exemplos:
      - '[Pré-Jogo]'
      - '[0\' · Apito Inicial]'
      - '[28\' · 1º Tempo]'
      - '[Intervalo · HT]'
      - '[45\' · Retorno 2H]'
      - '[73\' · 2º Tempo]'
      - '[90\'+ · Fim de Jogo (FT)]'
    """
    st = str(status or "").strip().upper()
    el = int(elapsed) if elapsed is not None else None

    if st in ("NS", "TBD", "PRE", "NOT_STARTED"):
        return "[Pré-Jogo]"
    elif st in ("1H", "FIRST_HALF", "LIVE_1H"):
        if el is None or el <= 1:
            return "[0' · Apito Inicial]"
        return f"[{el}' · 1º Tempo]"
    elif st in ("HT", "HALFTIME", "INTERVALO"):
        return "[Intervalo · HT]"
    elif st in ("2H", "SECOND_HALF", "LIVE_2H"):
        if el == 45 or el is None or el <= 46:
            return "[45' · Retorno 2H]"
        return f"[{el}' · 2º Tempo]"
    elif st in ("FT", "AET", "PEN", "FINISHED", "ENCERRADO"):
        return "[90'+ · Fim de Jogo (FT)]"
    else:
        if el is not None and el > 0:
            return f"[{el}' · Em Andamento]"
        return f"[{st}]"


def log_lifecycle_event(
    cursor,
    fixture_id: int,
    componente: str,
    tipo_evento: str,
    estado_novo: str,
    estado_anterior: str = None,
    aposta_id: int = None,
    notificacao_id: int = None,
    notificacao_criada_em: datetime = None,
    delta: dict = None,
    motivo: str = "",
    fase_jogo: str = None,
    minuto_jogo: int = None,
    placar_momento: str = None
) -> int:
    """
    Grava um evento de ciclo de vida na tabela pipeline_lifecycle_logs.
    Enriquece delta_json com o momento exato do jogo (fase, minuto e placar).
    Retorna o ID do log gerado ou None em caso de falha.
    """
    if not cursor or not fixture_id:
        return None

    try:
        # Prepara delta com metadados temporais do jogo
        delta_payload = dict(delta) if isinstance(delta, dict) else ({} if delta is None else {"valor": delta})
        
        if fase_jogo or minuto_jogo is not None:
            momento_str = format_match_moment(fase_jogo or ("1H" if minuto_jogo and minuto_jogo <= 45 else "2H"), minuto_jogo)
            delta_payload["fase"] = fase_jogo
            delta_payload["minuto"] = minuto_jogo
            delta_payload["momento_formatado"] = momento_str
        elif "momento_formatado" not in delta_payload and (fase_jogo or minuto_jogo):
            delta_payload["momento_formatado"] = format_match_moment(fase_jogo, minuto_jogo)

        if placar_momento and "placar" not in delta_payload:
            delta_payload["placar"] = placar_momento

        # Prefixa o motivo detalhado com o marcador de momento se ainda não contiver
        motivo_final = (motivo or "").strip()
        momento_tag = delta_payload.get("momento_formatado")
        if momento_tag and not motivo_final.startswith("["):
            motivo_final = f"{momento_tag} {motivo_final}".strip()

        delta_str = json.dumps(delta_payload, ensure_ascii=False) if delta_payload else None
        notif_dt = notificacao_criada_em.strftime('%Y-%m-%d %H:%M:%S') if isinstance(notificacao_criada_em, datetime) else notificacao_criada_em

        sql = """
            INSERT INTO pipeline_lifecycle_logs (
                fixture_id, aposta_id, notificacao_id, notificacao_criada_em,
                componente, tipo_evento, estado_anterior, estado_novo,
                delta_json, motivo_detalhado, criado_em
            ) VALUES (
                %s, %s, %s, %s,
                %s, %s, %s, %s,
                %s, %s, NOW()
            )
        """
        cursor.execute(sql, (
            fixture_id,
            aposta_id,
            notificacao_id,
            notif_dt,
            componente,
            tipo_evento,
            estado_anterior,
            estado_novo,
            delta_str,
            motivo_final
        ))
        log_id = cursor.lastrowid
        return log_id
    except Exception as e:
        print(f"⚠️ [LifecycleLogger] Falha ao registrar log de ciclo de vida (#{fixture_id} | {tipo_evento}): {e}")
        return None


def log_match_milestone(
    cursor,
    fixture_id: int,
    marco: str,
    placar: Optional[str] = None,
    minuto: Optional[int] = None,
    detalhes: str = "",
    home_team: Optional[str] = None,
    away_team: Optional[str] = None
) -> Optional[int]:
    """
    Registra os 4 marcos cronológicos essenciais da partida:
      - 'APITO_INICIAL' (0' - Início do 1º Tempo)
      - 'INTERVALO_INICIO' (Fim do 1º Tempo / Entrada no Intervalo HT)
      - 'INTERVALO_RETORNO' (45' - Início do 2º Tempo / Retorno do Intervalo)
      - 'FIM_DE_JOGO' (90'+ - Apito Final / Encerramento FT)
    """
    h_name = (home_team or "").strip()
    a_name = (away_team or "").strip()
    if not h_name or not a_name:
        try:
            cursor.execute("SELECT home_team, away_team FROM fixtures_trends WHERE fixture_id = %s LIMIT 1", (fixture_id,))
            f_row = cursor.fetchone()
            if f_row:
                h_name = (f_row.get('home_team') or '').strip() if isinstance(f_row, dict) else (f_row[0] or '').strip()
                a_name = (f_row.get('away_team') or '').strip() if isinstance(f_row, dict) else (f_row[1] or '').strip()
        except Exception:
            pass

    match_prefix = f" #{fixture_id} {h_name} vs {a_name}:" if (h_name and a_name) else (f" #{fixture_id}:" if fixture_id else "")
    match_tag = f" #{fixture_id} {h_name} vs {a_name}" if (h_name and a_name) else (f" #{fixture_id}" if fixture_id else "")

    mapa_marcos = {
        "APITO_INICIAL": {
            "componente": "LIVE_MATCH_CLOCK",
            "fase": "1H",
            "minuto": 0,
            "estado_ant": "NS",
            "estado_novo": "1H",
            "desc": f"⏱️ Bola rolando!{match_prefix} Apito inicial da partida."
        },
        "INTERVALO_INICIO": {
            "componente": "LIVE_MATCH_CLOCK",
            "fase": "HT",
            "minuto": 45,
            "estado_ant": "1H",
            "estado_novo": "HT",
            "desc": f"⏸️ Fim do 1º tempo para{match_tag}. Equipes no vestiário (Intervalo)."
        },
        "INTERVALO_RETORNO": {
            "componente": "LIVE_MATCH_CLOCK",
            "fase": "2H",
            "minuto": 45,
            "estado_ant": "HT",
            "estado_novo": "2H",
            "desc": f"▶️ Bola rolando para a etapa final de{match_tag}! Retorno do intervalo."
        },
        "FIM_DE_JOGO": {
            "componente": "LIVE_MATCH_CLOCK",
            "fase": "FT",
            "minuto": 90,
            "estado_ant": "2H",
            "estado_novo": "FT",
            "desc": f"🏁 Apito final!{match_prefix} Partida encerrada."
        }
    }

    config = mapa_marcos.get(marco)
    if not config:
        return None

    # Evita duplicidade do mesmo marco para a mesma partida
    cursor.execute("""
        SELECT id FROM pipeline_lifecycle_logs 
        WHERE fixture_id = %s AND tipo_evento = %s 
        LIMIT 1
    """, (fixture_id, marco))
    if cursor.fetchone():
        return None

    msg = config["desc"]
    if placar:
        msg += f" Placar: {placar}."
    if detalhes:
        msg += f" {detalhes}"

    return log_lifecycle_event(
        cursor=cursor,
        fixture_id=fixture_id,
        componente=config["componente"],
        tipo_evento=marco,
        estado_anterior=config["estado_ant"],
        estado_novo=config["estado_novo"],
        fase_jogo=config["fase"],
        minuto_jogo=minuto if minuto is not None else config["minuto"],
        placar_momento=placar,
        motivo=msg
    )


def get_fixture_timeline(cursor, fixture_id: int, limit: int = 50) -> list:
    """
    Retorna a linha do tempo cronológica de decisões e alertas de uma partida.
    """
    if not cursor or not fixture_id:
        return []

    try:
        sql = """
            SELECT l.*, n.titulo as notif_titulo, n.mensagem as notif_mensagem
            FROM pipeline_lifecycle_logs l
            LEFT JOIN notificacoes_usuario n ON l.notificacao_id = n.id
            WHERE l.fixture_id = %s
            ORDER BY l.id ASC
            LIMIT %s
        """
        cursor.execute(sql, (fixture_id, limit))
        return cursor.fetchall()
    except Exception as e:
        print(f"⚠️ [LifecycleLogger] Erro ao consultar linha do tempo (#{fixture_id}): {e}")
        return []

