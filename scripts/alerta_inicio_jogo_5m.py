#!/usr/bin/env python3
"""
Sentinela de Início Iminente (Alerta T-5 Minutos Pré-Jogo)
FootballWeb Pipeline - Monitoramento Iminente de Início de Partida

Executado a cada 3 minutos pela DAG 'football_alerta_5min_dag':
1. Consulta no MySQL partidas em aberto com apito inicial previsto entre 1 e 6 minutos;
2. Verifica se a partida possui aposta ativa/monitorada no sistema;
3. Previne duplicidades consultando se o alerta T-5m já foi emitido;
4. Dispara alerta prioritário no Sininho da web (notificacoes_usuario) e Push Mobile via ntfy;
5. Custo de API = R$ 0,00 (Operação 100% Cache-First no MySQL).
"""

import os
import sys
import requests
import pymysql.cursors
from datetime import datetime, timedelta

# Configura caminhos canônicos
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection


def get_live_env_vars() -> dict:
    """
    Carrega variáveis do .env dinamicamente (Regra 18).
    """
    env_vars = {}
    env_path = os.path.abspath(os.path.join(CURRENT_DIR, "../src/footballweb/.env"))
    if not os.path.exists(env_path):
        env_path = os.path.abspath(os.path.join(CURRENT_DIR, ".env"))

    if os.path.exists(env_path):
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    key, val = line.split("=", 1)
                    env_vars[key.strip()] = val.strip().strip("'").strip('"')
    return env_vars


def enviar_push_mobile(titulo: str, mensagem: str, fixture_id: int = None, prioridade: int = 5, tags: list = None) -> bool:
    """
    Dispara notificação push instantânea para o smartphone Android via ntfy.sh (Alta Prioridade).
    """
    try:
        env_vars = get_live_env_vars()
        enabled = os.environ.get("NTFY_ENABLED") or env_vars.get("NTFY_ENABLED", "true")
        if str(enabled).strip().lower() not in ("true", "1", "yes", "on"):
            return False

        topic = os.environ.get("NTFY_TOPIC") or env_vars.get("NTFY_TOPIC", "footbalweb_sentinela")
        if not topic:
            return False

        server = os.environ.get("NTFY_SERVER") or env_vars.get("NTFY_SERVER", "https://ntfy.sh")
        server = server.rstrip("/")

        base_url = os.environ.get("NTFY_BASE_URL") or env_vars.get("NTFY_BASE_URL", "https://cristalbet.com.br")
        click_url = f"{base_url.rstrip('/')}/apostas?fixture_id={fixture_id}" if fixture_id else f"{base_url.rstrip('/')}/apostas"

        payload = {
            "topic": topic,
            "title": titulo,
            "message": mensagem,
            "priority": prioridade,
            "tags": tags or ["alarm_clock", "whistle", "soccer"],
            "click": click_url
        }

        resp = requests.post(
            f"{server}",
            json=payload,
            headers={"Content-Type": "application/json; charset=utf-8"},
            timeout=8
        )
        if resp.status_code in (200, 201):
            print(f"📱 [Push Mobile T-5m Entregue] -> {titulo}")
            return True
        else:
            print(f"⚠️ [Push Mobile T-5m Falhou] Status HTTP {resp.status_code}: {resp.text}")
            return False
    except Exception as e:
        print(f"⚠️ [Push Mobile T-5m Erro de Conexão]: {e}")
        return False


def get_target_user_ids(cursor):
    """
    Retorna IDs de usuários ativos para notificação no Sininho.
    """
    try:
        cursor.execute("SELECT id FROM usuarios WHERE status = 'ativo'")
        rows = cursor.fetchall()
        if rows:
            return [r[0] if isinstance(r, (list, tuple)) else r['id'] for r in rows]
    except Exception:
        pass
    return [558]


def registrar_alerta_sininho(cursor, usuario_id: int, aposta_id: int, fixture_id: int, titulo: str, msg: str, tipo="ALERTA_PREJOGO_5M"):
    """
    Registra o alerta T-5m no Sininho da interface web (notificacoes_usuario).
    Prevenção de duplicidade: não insere se já foi emitido para esta partida.
    """
    try:
        cursor.execute("""
            SELECT id FROM notificacoes_usuario 
            WHERE usuario_id = %s 
              AND fixture_id = %s 
              AND tipo = %s
            LIMIT 1
        """, (usuario_id, fixture_id, tipo))
        if cursor.fetchone():
            return False

        link = f"/apostas?fixture_id={fixture_id}"
        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, pinada, criado_em
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, 0, 1, NOW()
            )
        """, (usuario_id, aposta_id, fixture_id, tipo, titulo, msg, link))
        notif_id = cursor.lastrowid
        print(f"🔔 [Sininho T-5m Notificado User #{usuario_id}] ID #{notif_id} -> {titulo}")

        try:
            from lifecycle_logger import log_lifecycle_event
            log_lifecycle_event(
                cursor=cursor,
                fixture_id=fixture_id,
                aposta_id=aposta_id,
                notificacao_id=notif_id,
                notificacao_criada_em=datetime.now(),
                componente='SENTINELA_5MIN',
                tipo_evento=tipo,
                estado_anterior=None,
                estado_novo=tipo,
                delta={'titulo': titulo, 'link': link},
                motivo=msg[:500]
            )
        except Exception:
            pass

        return True
    except Exception as e:
        print(f"⚠️ [Sininho T-5m] Falha ao registrar alerta em notificacoes_usuario: {e}")
        return False


def processar_alertas_5_minutos():
    """
    Varredura rápida no MySQL em busca de jogos iniciando nos próximos 1 a 6 minutos.
    """
    # Inicialização explícita de contadores (Regra de Ouro 8)
    total_jogos_encontrados = 0
    total_alertas_enviados = 0

    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    try:
        user_ids = get_target_user_ids(cursor)

        # Busca partidas na janela de T-6m a T-1m em relação ao fixture_date (armazenado em UTC)
        # e que possuam aposta monitorada no sistema com status pré-jogo (NS, TBD)
        query = """
            SELECT a.id as aposta_id, a.fixture_id, a.palpite, a.odd, a.status as aposta_status,
                   a.auditoria_ia_resumo, a.auditoria_ia_status,
                   ft.home_team, ft.away_team, ft.league_name, ft.fixture_date, ft.status as fixture_status
            FROM apostas a
            JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
            WHERE a.status IN ('Pendente', 'Não Confirmada')
              AND ft.status IN ('NS', 'TBD', 'POSTP')
              AND ft.fixture_date BETWEEN UTC_TIMESTAMP() 
                                      AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL 6 MINUTE)
            ORDER BY ft.fixture_date ASC
        """
        cursor.execute(query)
        partidas_iminentes = cursor.fetchall()

        if not partidas_iminentes:
            print("⏱️ [Sentinela 5m] Nenhuma partida iniciando nos próximos 5 minutos.")
            return

        print(f"⏰ [Sentinela 5m] Encontrada(s) {len(partidas_iminentes)} partida(s) a menos de 5 minutos do início!")

        for item in partidas_iminentes:
            total_jogos_encontrados += 1
            aposta_id = item['aposta_id']
            fixture_id = item['fixture_id']
            home_team = item['home_team']
            away_team = item['away_team']
            palpite = item['palpite']
            odd = float(item['odd'] or 1.0)
            ia_resumo = item.get('auditoria_ia_resumo') or 'Escalações auditadas no pré-jogo'

            # Converte fixture_date UTC para horário de Brasília (UTC - 3)
            dt_raw = item['fixture_date']
            dt_utc = dt_raw if isinstance(dt_raw, datetime) else datetime.fromisoformat(str(dt_raw))
            dt_local = dt_utc - timedelta(hours=3)
            horario_str = dt_local.strftime('%H:%M')

            # Prevenção de duplicidade: checa se o alerta T-5m já foi emitido para esta aposta
            cursor.execute("""
                SELECT id FROM notificacoes_usuario 
                WHERE fixture_id = %s 
                  AND tipo = 'ALERTA_PREJOGO_5M'
                LIMIT 1
            """, (fixture_id,))
            if cursor.fetchone():
                print(f"ℹ️ [Sentinela 5m] Alerta T-5m já emitido para #{fixture_id} ({home_team} vs {away_team}). Silêncio operacional.")
                continue

            titulo = f"⏰ Jogo Inicia em 5 Minutos! {home_team} vs {away_team}"
            mensagem = (
                f"⚡ A bola vai rolar às {horario_str} (Horário de Brasília) para {home_team} x {away_team}!\n\n"
                f"🎯 Seu Palpite: {palpite} (@ {odd:.2f})\n"
                f"📋 Situação de Campo: {ia_resumo}\n\n"
                f"⚠️ O mercado pré-jogo está se encerrando. Verifique suas posições antes do apito inicial."
            )

            alerta_enviado = False
            for uid in user_ids:
                if registrar_alerta_sininho(cursor, uid, aposta_id, fixture_id, titulo, mensagem, tipo="ALERTA_PREJOGO_5M"):
                    alerta_enviado = True

            conn.commit()

            if alerta_enviado:
                total_alertas_enviados += 1
                enviar_push_mobile(
                    titulo=titulo,
                    mensagem=mensagem,
                    fixture_id=fixture_id,
                    prioridade=5,
                    tags=["alarm_clock", "whistle", "soccer"]
                )

        print(f"🏁 [Sentinela 5m] Processamento finalizado. Jogos monitorados: {total_jogos_encontrados} | Alertas emitidos: {total_alertas_enviados}")

    except Exception as e:
        print(f"❌ [Sentinela 5m] Erro durante a varredura: {e}")
    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    processar_alertas_5_minutos()
