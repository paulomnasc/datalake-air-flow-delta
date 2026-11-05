#!/usr/bin/env python3
"""
Módulo Canônico de Logging Transversal e Rastreamento de Ciclo de Vida
Pipeline FootballWeb

Registra todas as transições de estado, decisões do Gatekeeper, auditorias da IA Sentinela,
mudanças de odds e envio de alertas no Sininho com data/hora e ID da notificação.
"""

import json
from datetime import datetime

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
    motivo: str = ""
) -> int:
    """
    Grava um evento de ciclo de vida na tabela pipeline_lifecycle_logs.
    Retorna o ID do log gerado ou None em caso de falha.
    """
    if not cursor or not fixture_id:
        return None

    try:
        delta_str = json.dumps(delta, ensure_ascii=False) if delta is not None else None
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
            motivo or ""
        ))
        log_id = cursor.lastrowid
        return log_id
    except Exception as e:
        print(f"⚠️ [LifecycleLogger] Falha ao registrar log de ciclo de vida (#{fixture_id} | {tipo_evento}): {e}")
        return None


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

