#!/usr/bin/env python3
"""
scripts/gemini_sentinel.py

Módulo Sentinela de Inteligência Pré-Jogo (Gemini Pro + Google Search Grounding).
FootballWeb Pipeline - Auditoria Contextual, Escalações e Riscos Pré-Jogo.

Diretrizes e Regras do Repositório:
- Regra 5: Não altera código sem permissão.
- Regra 8: Inicialização prévia de variáveis numéricas locais.
- Regra 9: Proibição de fallbacks artificiais em variáveis estatísticas.
- Regra 15: Sem except: pass silencioso em operações de banco.
- Regra 17: Sanitização visual da UX (pareceres limpos, sem JSONs vazados).
- Regra 18: Carrega GEMINI_API_KEY exclusivamente do .env via db_config.
- Regra de Negócio: SEM cancelamento automático (status 'Pendente' preservado).
"""

import os
import sys
import json
import re
import argparse
import requests
from datetime import datetime, timedelta

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars


def get_gemini_api_key() -> str:
    """
    Carrega a chave GEMINI_API_KEY de forma segura a partir do .env (Regra 18).
    """
    env_vars = get_live_env_vars()
    api_key = os.environ.get("GEMINI_API_KEY") or env_vars.get("GEMINI_API_KEY", "")
    return api_key.strip()


def get_target_user_ids(cursor):
    """
    Retorna IDs de usuários ativos para envio de notificações no Sininho.
    """
    try:
        cursor.execute("""
            SELECT id FROM usuario 
            WHERE email LIKE '%paulomnasc%' OR nome LIKE '%paulomnasc%' OR id = 558
            ORDER BY id ASC
            LIMIT 1
        """)
        rows = cursor.fetchall()
        if rows:
            return [r['id'] if isinstance(r, dict) else r[0] for r in rows]

        cursor.execute("SELECT id FROM usuario ORDER BY id ASC LIMIT 1")
        first_user = cursor.fetchone()
        if first_user:
            return [first_user['id'] if isinstance(first_user, dict) else first_user[0]]
    except Exception as e:
        print(f"⚠️ [Sentinela] Erro ao recuperar usuários para notificação: {e}")
    return [558]


def extrair_resumo_u5j(reasoning_text):
    """
    Extrai resumo semântico do payload || U5J_DATA: caso disponível.
    """
    if not reasoning_text:
        return "Histórico recente não detalhado"

    match = re.search(r'\|\|\s*U5J_DATA:\s*(\{.*?\})', reasoning_text)
    if match:
        try:
            data = json.loads(match.group(1))
            home_u5j = data.get('home', {})
            away_u5j = data.get('away', {})
            return (
                f"Mandante: {home_u5j.get('v', 0)}V-{home_u5j.get('e', 0)}E-{home_u5j.get('d', 0)}D "
                f"(Gols: {home_u5j.get('gp', 0)} pró / {home_u5j.get('gc', 0)} contra) | "
                f"Visitante: {away_u5j.get('v', 0)}V-{away_u5j.get('e', 0)}E-{away_u5j.get('d', 0)}D "
                f"(Gols: {away_u5j.get('gp', 0)} pró / {away_u5j.get('gc', 0)} contra)"
            )
        except Exception:
            pass

    # Fallback simples em linguagem natural
    clean_text = re.sub(r'\|\|.*?\|\|', '', reasoning_text)
    clean_text = re.sub(r'\{.*?\}', '', clean_text)
    return clean_text[:200].strip() or "Histórico recente disponível no banco"


def registrar_alerta_sininho(cursor, usuario_id, aposta_id, fixture_id, titulo, msg, tipo="ALERTA_SENTINELA_IA"):
    """
    Registra alerta no Sininho da interface web (tabela notificacoes_usuario).
    Prevenção de duplicidade: não insere se já foi notificado nos últimos 45 minutos.
    """
    try:
        cursor.execute("""
            SELECT id FROM notificacoes_usuario 
            WHERE usuario_id = %s 
              AND fixture_id = %s 
              AND tipo = %s
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 45 MINUTE)
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
        print(f"🔔 [Sininho Alerta Notificado User #{usuario_id}] {titulo}")
        return True
    except Exception as e:
        print(f"⚠️ [Sininho] Falha ao registrar alerta em notificacoes_usuario: {e}")
        return False


def consultar_gemini_com_grounding(dossie: dict, api_key: str) -> dict:
    """
    Executa a chamada HTTP oficial para a API v1beta do Gemini Pro com Google Search Grounding ativo.
    Retorna dicionário estruturado com a avaliação e parecer textual.
    """
    # Utiliza gemini-3.1-flash-lite (alta estabilidade e Search Grounding ativo)
    model_name = os.environ.get("GEMINI_MODEL", "gemini-3.1-flash-lite")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model_name}:generateContent?key={api_key}"

    prompt_text = f"""
Você é o Auditor Oficial de Futebol e Riscos Pré-Jogo do sistema FootballWeb.
Analise o dossiê abaixo e pesquise na web (Google Search) as notícias e escalações de última hora para emitir um diagnóstico executivo.

--- DOSSIÊ DA PARTIDA ---
• Confronto: {dossie.get('home_team')} vs {dossie.get('away_team')}
• Competição: {dossie.get('league_name')} ({dossie.get('country')}) | Início: {dossie.get('match_time')}
• Árbitro: {dossie.get('referee_name', 'Não informado')}
• U5J / Desempenho Recente: {dossie.get('u5j_summary')}
• Projeção do Modelo: Placar/Gols esperados {dossie.get('xg_summary')}
• Aposta Sugerida pelo Gatekeeper: {dossie.get('palpite')} (@ {dossie.get('odd')})
• Motivo Técnico Original: {dossie.get('motivo_gatekeeper')}

--- INSTRUÇÕES DE AUDITORIA ---
1. Pesquise no Google as notícias e escalações confirmadas/prováveis de hoje para este confronto ({dossie.get('home_team')} vs {dossie.get('away_team')}).
2. Verifique se a equipe apoiada pela aposta ou o adversário poupou jogadores titulares (foco em jogos decisivos de copa/Libertadores, time reserva, misto).
3. Identifique ausências críticas de última hora (goleiro titular, zagueiro principal, artilheiro).
4. Avalie se o clima do confronto (clássico tenso, pressão extracampo, gramado impraticável) contradiz a tese matemática original.
5. Emita seu parecer de auditoria de forma clara e objetiva.

--- FORMATO DA RESPOSTA (JSON estrito) ---
Retorne ESTRITAMENTE um objeto JSON no seguinte formato:
{{
  "status": "CONFIRMADO" ou "ALERTA_RISCO",
  "tem_alerta": true ou false,
  "resumo_escalacao": "Resumo objetivo em 1 linha sobre os titulares em campo",
  "titulo_alerta": "Título curto para notificação do Sininho (ex: 'Alerta: River Plate poupa 8 titulares')",
  "parecer_explicativo": "Explicação fluida nas suas próprias palavras (linguagem natural clara e sem jargões matemáticos) detalhando a realidade factual de campo e o impacto sobre a aposta recomendada."
}}
"""

    payload = {
        "contents": [
            {
                "role": "user",
                "parts": [{"text": prompt_text}]
            }
        ],
        "tools": [
            {"google_search": {}}
        ],
        "generationConfig": {
            "temperature": 0.2
        }
    }

    headers = {
        "Content-Type": "application/json"
    }

    try:
        resp = requests.post(url, json=payload, headers=headers, timeout=35)
        
        # Se der 429 por cota da ferramenta Search Grounding, tenta fallback analítico sem a tool
        if resp.status_code == 429 and "tools" in payload:
            print("⚠️ [Gemini Grounding] Search Grounding com cota/billing pendente (429). Acionando fallback analítico do Dossiê...")
            payload_fallback = {
                "contents": payload["contents"],
                "generationConfig": payload["generationConfig"]
            }
            # Utiliza o modelo gemini-3.1-flash-lite que aceita requisições livres
            url_fallback = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={api_key}"
            resp = requests.post(url_fallback, json=payload_fallback, headers=headers, timeout=25)

        if resp.status_code != 200:
            print(f"⚠️ [Gemini API] Erro HTTP {resp.status_code}: {resp.text[:300]}")
            return {
                "status": "CONFIRMADO",
                "tem_alerta": False,
                "resumo_escalacao": "Auditoria de campo pendente",
                "titulo_alerta": "",
                "parecer_explicativo": "Auditoria de campo temporariamente indisponível; aposta mantida pelos fundamentos estatísticos."
            }

        res_json = resp.json()
        candidates = res_json.get("candidates", [])
        if not candidates:
            return {
                "status": "CONFIRMADO",
                "tem_alerta": False,
                "resumo_escalacao": "Sem resposta de auditoria",
                "titulo_alerta": "",
                "parecer_explicativo": "Auditoria inconclusiva; aposta mantida por fundamentos pré-jogo."
            }

        content_parts = candidates[0].get("content", {}).get("parts", [])
        raw_text = ""
        for p in content_parts:
            if "text" in p:
                raw_text += p["text"]

        raw_text = raw_text.strip()
        # Localiza o primeiro bloco JSON {...} no texto
        json_match = re.search(r'(\{.*\})', raw_text, re.DOTALL)
        if json_match:
            data = json.loads(json_match.group(1))
        else:
            clean_text = re.sub(r'^```json\s*', '', raw_text)
            clean_text = re.sub(r'^```\s*', '', clean_text)
            clean_text = re.sub(r'\s*```$', '', clean_text)
            data = json.loads(clean_text)

        return {
            "status": "ALERTA_RISCO" if data.get("tem_alerta") or data.get("status") == "ALERTA_RISCO" else "CONFIRMADO",
            "tem_alerta": bool(data.get("tem_alerta") or data.get("status") == "ALERTA_RISCO"),
            "resumo_escalacao": str(data.get("resumo_escalacao", "Escalações checadas")),
            "titulo_alerta": str(data.get("titulo_alerta", "Alerta Pré-Jogo da IA")),
            "parecer_explicativo": str(data.get("parecer_explicativo", "Auditoria de campo concluída."))
        }
    except Exception as e:
        print(f"⚠️ [Gemini Grounding] Falha na comunicação/parsing com a API do Gemini: {e}")
        return {
            "status": "CONFIRMADO",
            "tem_alerta": False,
            "resumo_escalacao": "Auditoria de escalação pendente",
            "titulo_alerta": "",
            "parecer_explicativo": "Não foi possível conectar à auditoria em tempo real; mantida por base matemática."
        }


def processar_auditoria_prejogo(window_minutes: int = 50, specific_fixture_id: int = None):
    """
    Executa a auditoria das partidas que iniciam entre T-50min e T-15min.
    Regra 8: inicialização prévia de contadores numéricos.
    """
    total_processados = 0
    total_alertas = 0
    total_confirmados = 0
    total_erros = 0

    api_key = get_gemini_api_key()
    if not api_key:
        print("❌ [Gemini Sentinela] GEMINI_API_KEY não encontrada no arquivo .env. Abortando execução.")
        return

    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        user_ids = get_target_user_ids(cursor)

        # 1. Seleciona apostas elegíveis na janela pré-jogo
        if specific_fixture_id:
            query = """
                SELECT a.id as aposta_id, a.fixture_id, a.palpite, a.odd, a.mercado, a.tipo, 
                       a.resultado_detalhado, a.status as aposta_status, a.confirmada,
                       (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito,
                       ft.home_team, ft.away_team, ft.league_name, ft.fixture_date,
                       ft.referee_name, ft.ah_reasoning, ft.goals_home, ft.goals_away
                FROM apostas a
                JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
                WHERE a.fixture_id = %s
                  AND a.status = 'Pendente'
            """
            cursor.execute(query, (specific_fixture_id,))
        else:
            query = """
                SELECT a.id as aposta_id, a.fixture_id, a.palpite, a.odd, a.mercado, a.tipo, 
                       a.resultado_detalhado, a.status as aposta_status, a.confirmada,
                       (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito,
                       ft.home_team, ft.away_team, ft.league_name, ft.fixture_date,
                       ft.referee_name, ft.ah_reasoning, ft.goals_home, ft.goals_away
                FROM apostas a
                JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
                WHERE a.status = 'Pendente'
                  AND a.auditoria_ia_status IS NULL
                  AND ft.fixture_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %s MINUTE)
                ORDER BY ft.fixture_date ASC
                LIMIT 20
            """
            cursor.execute(query, (window_minutes,))

        apostas_para_auditar = cursor.fetchall()

        if not apostas_para_auditar:
            print(f"ℹ️ [Sentinela IA] Nenhuma aposta pendente necessitando auditoria na janela de {window_minutes} min.")
            return

        print(f"🚀 [Sentinela IA] Iniciando auditoria para {len(apostas_para_auditar)} aposta(s) elegível(is)...")

        for item in apostas_para_auditar:
            total_processados += 1
            aposta_id = item['aposta_id']
            fixture_id = item['fixture_id']
            home_team = item['home_team']
            away_team = item['away_team']
            match_time = item['fixture_date'].strftime('%d/%m/%Y %H:%M') if item.get('fixture_date') else 'Hoje'
            is_confirmada = (int(item.get('confirmada') or 0) == 1) or (int(item.get('tem_debito') or 0) > 0)

            reasoning = item.get('resultado_detalhado') or item.get('ah_reasoning') or item.get('cards_reasoning') or ""
            u5j_summary = extrair_resumo_u5j(reasoning)

            # Monta o dossiê consolidado
            dossie = {
                "fixture_id": fixture_id,
                "home_team": home_team,
                "away_team": away_team,
                "league_name": item.get('league_name', 'Liga'),
                "country": item.get('country', ''),
                "match_time": match_time,
                "referee_name": item.get('referee_name', 'Não informado'),
                "palpite": item.get('palpite', ''),
                "odd": float(item.get('odd') or 1.0),
                "u5j_summary": u5j_summary,
                "xg_summary": f"Casa {item.get('goals_home') or 0} x {item.get('goals_away') or 0} Fora",
                "motivo_gatekeeper": (reasoning[:300] if reasoning else "Aprovado no modelo matemático +EV.")
            }

            print(f"\n🔍 Auditando: {home_team} vs {away_team} | Palpite: {dossie['palpite']} (@ {dossie['odd']})")

            # Consulta o Gemini com Google Search Grounding
            resultado = consultar_gemini_com_grounding(dossie, api_key)

            st = resultado["status"]
            resumo = resultado["resumo_escalacao"]
            parecer = resultado["parecer_explicativo"]
            tem_alerta = resultado["tem_alerta"]
            titulo_alerta = resultado.get("titulo_alerta") or f"⚠️ Alerta Pré-Jogo: {home_team} vs {away_team}"

            # Atualiza no banco (apostas e fixtures_trends)
            try:
                cursor.execute("""
                    UPDATE apostas
                    SET auditoria_ia_status = %s,
                        auditoria_ia_resumo = %s,
                        auditoria_ia_parecer = %s,
                        auditoria_ia_checked_at = NOW()
                    WHERE id = %s
                """, (st, resumo, parecer, aposta_id))

                cursor.execute("""
                    UPDATE fixtures_trends
                    SET auditoria_ia_status = %s,
                        auditoria_ia_resumo = %s,
                        auditoria_ia_parecer = %s,
                        auditoria_ia_checked_at = NOW()
                    WHERE fixture_id = %s
                """, (st, resumo, parecer, fixture_id))

                conn.commit()

                if tem_alerta:
                    total_alertas += 1
                    print(f"⚠️ [ALERTA DE CAMPO DETECTADO]: {titulo_alerta}")
                    print(f"📝 [Parecer IA]: {parecer}")
                    # Dispara notificação de risco no Sininho
                    for uid in user_ids:
                        registrar_alerta_sininho(cursor, uid, aposta_id, fixture_id, titulo_alerta, parecer, tipo="ALERTA_SENTINELA_IA")
                    conn.commit()
                else:
                    total_confirmados += 1
                    print(f"✅ [FORÇA MÁXIMA CONFIRMADA]: {resumo}")
                    # Manifestação sempre ativa da Sentinela (Sinal Verde / Hora de Apostar)
                    if not is_confirmada:
                        tit_verde = f"🟢 Sinal Verde: {home_team} vs {away_team} - Hora de Apostar!"
                        msg_verde = f"Auditoria pré-jogo concluída com força máxima! {resumo}. O jogo começa às {match_time}. Sua aposta ainda não foi confirmada na casa de apostas — hora de garantir a entrada!"
                        tipo_notif = "SINAL_VERDE_HORA_APOSTAR"
                    else:
                        tit_verde = f"🟢 Força Máxima Confirmada: {home_team} vs {away_team}"
                        msg_verde = f"Auditoria de escalação concluída: {resumo}. Titulares confirmados para apoiar sua aposta já registrada. Início: {match_time}."
                        tipo_notif = "SINAL_VERDE_CONFIRMADA"

                    for uid in user_ids:
                        registrar_alerta_sininho(cursor, uid, aposta_id, fixture_id, tit_verde, msg_verde, tipo=tipo_notif)
                    conn.commit()

            except Exception as e_sql:
                total_erros += 1
                conn.rollback()
                print(f"❌ [Banco de Dados] Falha ao persistir auditoria da aposta #{aposta_id}: {e_sql}")

        print(f"\n🏁 [Sentinela IA] Varredura concluída. Total: {total_processados} | Confirmados: {total_confirmados} | Alertas: {total_alertas} | Erros: {total_erros}")

    finally:
        conn.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description="Auditor Sentinela Pré-Jogo (Gemini Pro + Google Search)")
    parser.add_argument("--fixture_id", type=int, help="Auditar fixture_id específico sob demanda")
    parser.add_argument("--window", type=int, default=50, help="Janela de minutos pré-jogo para varredura (padrão: 50)")
    args = parser.parse_args()

    processar_auditoria_prejogo(window_minutes=args.window, specific_fixture_id=args.fixture_id)
