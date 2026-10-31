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


def get_apisports_key() -> str:
    """
    Carrega a chave da API-Sports de forma segura a partir do .env (Regra 18).
    """
    env_vars = get_live_env_vars()
    key = (
        os.environ.get("FOOTBALL_API_KEY")
        or env_vars.get("FOOTBALL_API_KEY")
        or os.environ.get("API_SPORTS_KEY")
        or env_vars.get("API_SPORTS_KEY")
        or os.environ.get("APISPORTS_KEY")
        or env_vars.get("APISPORTS_KEY", "")
    )
    if not key:
        root_env = os.path.abspath(os.path.join(os.path.dirname(__file__), "../.env"))
        if os.path.exists(root_env):
            with open(root_env, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if line.startswith("FOOTBALL_API_KEY="):
                        key = line.split("=", 1)[1].strip().strip("'").strip('"')
                        break
    return key.strip()


def get_groq_api_key() -> str:
    """
    Carrega a chave da API Groq (VISION_API_KEY) de forma segura a partir do .env (Regra 18)
    para contingência de inferência quando a API do Gemini estiver indisponível ou bloqueada.
    """
    env_vars = get_live_env_vars()
    key = os.environ.get("VISION_API_KEY") or env_vars.get("VISION_API_KEY", "")
    return key.strip()


def coletar_sumula_e_desfalques_apisports(
    cursor,
    fixture_id: int,
    home_team_id: int,
    away_team_id: int,
    home_team: str,
    away_team: str,
    palpite: str
) -> dict:
    """
    Consulta a súmula oficial de escalações (/fixtures/lineups) e a lista de desfalques (/injuries)
    na API-Sports na janela pré-jogo, cruzando os 11 titulares escalados hoje com a última partida
    oficial de cada equipe para detectar objetivamente:
    - Escalação de goleiro reserva;
    - Rodízio pesado / time misto ou reserva (>= 5 alterações nos titulares);
    - Acúmulo de desfalques por lesão ou suspensão.
    """
    # Inicialização explícita de variáveis numéricas locais (Regra 8)
    rotacao_home_cnt = 0
    rotacao_away_cnt = 0
    desfalques_home_cnt = 0
    desfalques_away_cnt = 0

    api_key = get_apisports_key()
    if not api_key:
        return {
            "lineups_disponiveis": False,
            "texto_sumula": "Chave API-Sports ausente para consulta de súmula.",
            "alerta_estrutural_elenco": False,
            "motivos_alerta_elenco": [],
            "resumo_curto": "Súmula não consultada"
        }

    headers = {"x-apisports-key": api_key}
    base_url = "https://v3.football.api-sports.io"

    lineups_data = []
    injuries_data = []

    try:
        r_lin = requests.get(f"{base_url}/fixtures/lineups", params={"fixture": fixture_id}, headers=headers, timeout=12)
        if r_lin.status_code == 200:
            lineups_data = r_lin.json().get("response", [])
    except Exception as e_lin:
        print(f"⚠️ [Sentinela Súmula] Falha ao consultar /fixtures/lineups para #{fixture_id}: {e_lin}")

    try:
        r_inj = requests.get(f"{base_url}/injuries", params={"fixture": fixture_id}, headers=headers, timeout=12)
        if r_inj.status_code == 200:
            injuries_data = r_inj.json().get("response", [])
    except Exception as e_inj:
        print(f"⚠️ [Sentinela Desfalques] Falha ao consultar /injuries para #{fixture_id}: {e_inj}")

    # Agrupa desfalques oficiais por equipe
    inj_home_list = []
    inj_away_list = []
    for inj in injuries_data:
        t_id = (inj.get("team") or {}).get("id")
        t_name = (inj.get("team") or {}).get("name", "")
        p_name = (inj.get("player") or {}).get("name", "Jogador")
        p_reason = (inj.get("player") or {}).get("reason") or (inj.get("player") or {}).get("type", "Ausente")
        desc_inj = f"{p_name} ({p_reason})"
        if (home_team_id and t_id == home_team_id) or (home_team.lower() in t_name.lower()):
            if desc_inj not in inj_home_list:
                inj_home_list.append(desc_inj)
        elif (away_team_id and t_id == away_team_id) or (away_team.lower() in t_name.lower()):
            if desc_inj not in inj_away_list:
                inj_away_list.append(desc_inj)

    desfalques_home_cnt = len(inj_home_list)
    desfalques_away_cnt = len(inj_away_list)

    if not lineups_data:
        texto_inj = (
            f"Desfalques registrados ({home_team}): {', '.join(inj_home_list) if inj_home_list else 'Nenhum listado'}.\n"
            f"Desfalques registrados ({away_team}): {', '.join(inj_away_list) if inj_away_list else 'Nenhum listado'}."
        )
        return {
            "lineups_disponiveis": False,
            "texto_sumula": f"Súmula oficial (11 titulares) ainda não publicada pela federação.\n{texto_inj}",
            "alerta_estrutural_elenco": False,
            "motivos_alerta_elenco": [],
            "resumo_curto": "Aguardando divulgação da súmula oficial"
        }

    # Identifica qual é o time apoiado na aposta (se for Handicap Asiático)
    palpite_lower = (palpite or "").lower()
    is_under_cards = ("menos" in palpite_lower) or ("under" in palpite_lower)
    apoiado_is_away = (away_team.lower() in palpite_lower) if not is_under_cards else None

    blocos_sumula = []
    motivos_alerta = []
    resumos_equipes = []

    for team_entry in lineups_data:
        t_info = team_entry.get("team") or {}
        t_id = t_info.get("id")
        t_name = t_info.get("name", "Equipe")
        coach_name = (team_entry.get("coach") or {}).get("name", "Não informado")
        formation = team_entry.get("formation", "N/D")
        start_xi = team_entry.get("startXI") or []
        subs = team_entry.get("substitutes") or []

        titulares_nomes = []
        titulares_ids = set()
        gk_atual_nome = "N/D"
        gk_atual_id = None

        for item_p in start_xi:
            p_obj = item_p.get("player") or {}
            pid = p_obj.get("id")
            pname = p_obj.get("name", "")
            ppos = p_obj.get("pos", "")
            if pid:
                titulares_ids.add(pid)
            titulares_nomes.append(f"{pname} ({ppos})")
            if ppos == "G" and gk_atual_id is None:
                gk_atual_id = pid
                gk_atual_nome = pname

        reservas_nomes = [
            f"{(s.get('player') or {}).get('name', '')} ({(s.get('player') or {}).get('pos', '')})"
            for s in subs[:12]
        ]

        # Compara com a escalação titular do último jogo encerrado da mesma equipe no banco
        mudancas_titulares = 0
        gk_anterior_nome = None
        gk_mudou = False
        titulares_ausentes_hoje = []

        if t_id and cursor:
            try:
                cursor.execute("""
                    SELECT fixture_id FROM fixtures_trends
                    WHERE (home_team_id = %s OR away_team_id = %s)
                      AND status IN ('FT', 'AET', 'PEN')
                      AND fixture_id != %s
                    ORDER BY fixture_date DESC
                    LIMIT 1
                """, (t_id, t_id, fixture_id))
                prev_row = cursor.fetchone()
                prev_fid = (prev_row["fixture_id"] if isinstance(prev_row, dict) else prev_row[0]) if prev_row else None

                if prev_fid:
                    r_prev = requests.get(
                        f"{base_url}/fixtures/lineups",
                        params={"fixture": prev_fid, "team": t_id},
                        headers=headers,
                        timeout=10
                    )
                    if r_prev.status_code == 200:
                        prev_resp = r_prev.json().get("response", [])
                        if prev_resp:
                            prev_xi = prev_resp[0].get("startXI") or []
                            prev_ids = set()
                            for pp in prev_xi:
                                pobj = pp.get("player") or {}
                                ppid = pobj.get("id")
                                ppname = pobj.get("name", "")
                                pppos = pobj.get("pos", "")
                                if ppid:
                                    prev_ids.add(ppid)
                                    if ppid not in titulares_ids:
                                        titulares_ausentes_hoje.append(f"{ppname} ({pppos})")
                                if pppos == "G":
                                    gk_anterior_nome = ppname
                                    if gk_atual_id and ppid and gk_atual_id != ppid:
                                        gk_mudou = True
                            if prev_ids and titulares_ids:
                                mantidos = len(titulares_ids.intersection(prev_ids))
                                mudancas_titulares = max(0, 11 - mantidos)
            except Exception as e_prev:
                print(f"⚠️ [Sentinela Comparativo Lineup] Erro ao comparar escalação anterior do time {t_name}: {e_prev}")

        is_home_entry = (home_team_id and t_id == home_team_id) or (home_team.lower() in t_name.lower())
        if is_home_entry:
            rotacao_home_cnt = mudancas_titulares
        else:
            rotacao_away_cnt = mudancas_titulares

        is_time_apoiado = (
            (apoiado_is_away is False and is_home_entry)
            or (apoiado_is_away is True and not is_home_entry)
        )

        # Regras objetivas de alerta estrutural de elenco na equipe apoiada
        if is_time_apoiado:
            if gk_mudou and gk_anterior_nome:
                motivos_alerta.append(
                    f"Goleiro titular ausente em {t_name}: {gk_atual_nome} escalado na vaga de {gk_anterior_nome}"
                )
            if mudancas_titulares >= 5:
                motivos_alerta.append(
                    f"Rodízio pesado / time misto detectado em {t_name}: {mudancas_titulares} alterações nos 11 titulares ({', '.join(titulares_ausentes_hoje[:5])} fora do XI inicial)"
                )
            inj_list_team = inj_home_list if is_home_entry else inj_away_list
            if len(inj_list_team) >= 4:
                motivos_alerta.append(
                    f"{t_name} com {len(inj_list_team)} baixas/desfalques oficiais ({', '.join(inj_list_team[:5])})"
                )

        resumos_equipes.append(
            f"{t_name} ({formation}, Téc: {coach_name}, GK: {gk_atual_nome}"
            + (f", {mudancas_titulares} mud." if mudancas_titulares > 0 else "")
            + ")"
        )

        bloco = (
            f"• {t_name} | Esquema: {formation} | Técnico: {coach_name}\n"
            f"  - 11 Titulares: {', '.join(titulares_nomes)}\n"
            f"  - Banco de Reservas: {', '.join(reservas_nomes[:10])}\n"
        )
        if mudancas_titulares > 0:
            bloco += f"  - Comparativo Jogo Anterior: {mudancas_titulares} mudança(s) nos 11 titulares. Saíram do XI: {', '.join(titulares_ausentes_hoje)}.\n"
        if gk_mudou and gk_anterior_nome:
            bloco += f"  - ALERTA DE GOLEIRO: Goleiro alterado de {gk_anterior_nome} para {gk_atual_nome}.\n"
        blocos_sumula.append(bloco)

    blocos_sumula.append(
        f"• Baixas e Suspensões Oficiais ({home_team}): {', '.join(inj_home_list) if inj_home_list else 'Nenhuma reportada'}\n"
        f"• Baixas e Suspensões Oficiais ({away_team}): {', '.join(inj_away_list) if inj_away_list else 'Nenhuma reportada'}"
    )

    return {
        "lineups_disponiveis": True,
        "texto_sumula": "\n".join(blocos_sumula),
        "alerta_estrutural_elenco": len(motivos_alerta) > 0,
        "motivos_alerta_elenco": motivos_alerta,
        "rotacao_home_cnt": rotacao_home_cnt,
        "rotacao_away_cnt": rotacao_away_cnt,
        "desfalques_home_cnt": desfalques_home_cnt,
        "desfalques_away_cnt": desfalques_away_cnt,
        "resumo_curto": " | ".join(resumos_equipes)
    }


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


def enviar_push_mobile(titulo: str, mensagem: str, fixture_id: int = None, prioridade: int = 3, tags: list = None) -> bool:
    """
    Dispara notificação push instantânea para o smartphone Android via ntfy.sh.
    Parâmetros carregados dinamicamente de src/footballweb/.env (Regra 18).
    Fail-safe: falhas no envio mobile nunca interrompem o fluxo principal do pipeline.
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
            "tags": tags or ["soccer"],
            "click": click_url
        }

        resp = requests.post(
            f"{server}",
            data=json.dumps(payload),
            headers={"Content-Type": "application/json; charset=utf-8"},
            timeout=8
        )
        if resp.status_code == 200:
            print(f"📲 [Push Mobile ntfy] Notificação enviada para o tópico '{topic}': {titulo}")
            return True
        else:
            print(f"⚠️ [Push Mobile ntfy] Servidor retornou HTTP {resp.status_code}: {resp.text}")
            return False
    except Exception as e:
        print(f"⚠️ [Push Mobile ntfy] Falha ao disparar push notification: {e}")
        return False


def testar_push_mobile() -> bool:
    """
    Envia mensagem fake de teste forçada para validação imediata do app ntfy no smartphone.
    """
    agora_gmt3 = datetime.now()
    hora_str = agora_gmt3.strftime("%d/%m/%Y %H:%M")
    print(f"🚀 [Teste NTFY] Disparando notificação fake de teste às {hora_str} (GMT -3)...")
    sucesso = enviar_push_mobile(
        titulo="🟢 Sinal Verde: Teste Sentinela - Hora de Apostar!",
        mensagem=f"Esta é uma mensagem de teste forçada da Sentinela Pré-Jogo (Gemini IA) enviada às {hora_str} (GMT -3). Se você recebeu esta notificação, seu aplicativo ntfy está 100% configurado e operacional!",
        fixture_id=1550174,
        prioridade=4,  # High
        tags=["white_check_mark", "green_circle", "soccer"]
    )
    if sucesso:
        print("✅ [Teste NTFY] Push fake entregue com sucesso aos servidores da ntfy.sh!")
    else:
        print("❌ [Teste NTFY] Falha no disparo do push de teste.")
    return sucesso



def _consultar_groq_fallback(prompt_text: str) -> str:
    """
    Contingência automática via API Groq (openai/gpt-oss-120b) caso a chave do Gemini
    retorne erro HTTP 401/403/429. Garante que a análise tática da súmula nunca falhe.
    """
    groq_key = get_groq_api_key()
    if not groq_key:
        return ""

    env_vars = get_live_env_vars()
    model_groq = os.environ.get("TEXT_API_MODEL") or env_vars.get("TEXT_API_MODEL", "openai/gpt-oss-120b")
    try:
        resp = requests.post(
            "https://api.groq.com/openai/v1/chat/completions",
            headers={
                "Authorization": f"Bearer {groq_key}",
                "Content-Type": "application/json"
            },
            json={
                "model": model_groq,
                "messages": [
                    {
                        "role": "system",
                        "content": "Você é o Auditor Oficial de Futebol e Riscos Pré-Jogo do sistema FootballWeb. Responda estritamente em JSON válido."
                    },
                    {"role": "user", "content": prompt_text}
                ],
                "temperature": 0.2,
                "response_format": {"type": "json_object"}
            },
            timeout=25
        )
        if resp.status_code == 200:
            choices = resp.json().get("choices", [])
            if choices:
                return choices[0].get("message", {}).get("content", "")
        else:
            print(f"⚠️ [Groq Fallback] HTTP {resp.status_code}: {resp.text[:200]}")
    except Exception as e_g:
        print(f"⚠️ [Groq Fallback] Erro na chamada de contingência: {e_g}")
    return ""


def consultar_gemini_com_grounding(dossie: dict, api_key: str) -> dict:
    """
    Executa a chamada HTTP oficial para a API v1beta do Gemini Pro com Google Search Grounding ativo,
    injetando a Súmula Oficial de Escalações (/fixtures/lineups) e Desfalques (/injuries) da API-Sports.
    Possui contingência automática para Groq (openai/gpt-oss-120b) caso o Gemini apresente erro HTTP.
    """
    model_name = os.environ.get("GEMINI_MODEL", "gemini-3.1-flash-lite")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model_name}:generateContent?key={api_key}"

    sumula_info = dossie.get("sumula_oficial") or {}
    texto_sumula = sumula_info.get("texto_sumula", "Súmula não informada.")
    alerta_estrutural = bool(sumula_info.get("alerta_estrutural_elenco"))
    motivos_elenco = sumula_info.get("motivos_alerta_elenco") or []

    prompt_text = f"""
Você é o Auditor Oficial de Futebol e Riscos Pré-Jogo do sistema FootballWeb.
Analise o dossiê completo abaixo — incluindo a SÚMULA OFICIAL DE ESCALAÇÕES E DESFALQUES extraída da API-Sports — para emitir um diagnóstico tático executivo sobre a viabilidade da aposta.

--- DOSSIÊ DA PARTIDA ---
• Confronto: {dossie.get('home_team')} vs {dossie.get('away_team')}
• Competição: {dossie.get('league_name')} ({dossie.get('country')}) | Início: {dossie.get('match_time')}
• Árbitro: {dossie.get('referee_name', 'Não informado')}
• U5J / Desempenho Recente: {dossie.get('u5j_summary')}
• Projeção do Modelo: Placar/Gols esperados {dossie.get('xg_summary')}
• Aposta Sugerida pelo Gatekeeper: {dossie.get('palpite')} (@ {dossie.get('odd')})
• Motivo Técnico Original: {dossie.get('motivo_gatekeeper')}

--- SÚMULA OFICIAL DAS ESCALAÇÕES E DESFALQUES (API-SPORTS) ---
{texto_sumula}
• Alertas Estruturais de Elenco Detectados: {', '.join(motivos_elenco) if motivos_elenco else 'Nenhum desvio crítico automático'}

--- INSTRUÇÕES DE AUDITORIA ---
1. Avalie rigorosamente os 11 titulares escalados na súmula oficial e a lista de lesionados/suspensos.
2. Defina com precisão o GRAU DE RISCO da partida para a aposta sugerida:
   - "ALTO": Se houver ausência do goleiro titular, time misto/reserva (4 ou mais mudanças nos titulares), desfalque do artilheiro principal ou capitão/zagueiro xerife na equipe apoiada, ou se a súmula oficial com os 11 titulares ainda estiver indisponível no pré-jogo com desfalques importantes.
   - "MEDIO": Se houver 1 ou 2 desfalques pontuais de linha (ex: volante ou lateral), mas com goleiro, artilheiro e a espinha dorsal mantidos.
   - "BAIXO": Força máxima confirmada (10 a 11 titulares mantidos); eventuais desfalques apenas de reservas ou atletas de composição de elenco.
3. Se o grau de risco for "ALTO" ou "MEDIO", classifique como status "ALERTA_RISCO" (tem_alerta: true). Se for "BAIXO", classifique como "CONFIRMADO" (tem_alerta: false).
4. Emita seu parecer em linguagem natural clara, direta e sem fórmulas matemáticas, citando nominalmente os jogadores escalados ou ausentes que fundamentam a conclusão.

--- FORMATO DA RESPOSTA (JSON estrito) ---
Retorne ESTRITAMENTE um objeto JSON no seguinte formato:
{{
  "status": "CONFIRMADO" ou "ALERTA_RISCO",
  "grau_risco": "ALTO", "MEDIO" ou "BAIXO",
  "tem_alerta": true ou false,
  "resumo_escalacao": "Resumo objetivo em 1 linha sobre os titulares escalados e eventuais baixas",
  "titulo_alerta": "Título curto para notificação do Sininho (ex: 'Alerta: Al-Ahli com time reserva e goleiro suplente')",
  "parecer_explicativo": "Explicação fluida nas suas próprias palavras detalhando os titulares escalados, os desfalques e o impacto real sobre a aposta recomendada."
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

    headers = {"Content-Type": "application/json"}
    raw_text = ""

    global _GEMINI_KEY_BLOCKED
    if "_GEMINI_KEY_BLOCKED" not in globals():
        _GEMINI_KEY_BLOCKED = False

    try:
        if api_key and not _GEMINI_KEY_BLOCKED:
            resp = requests.post(url, json=payload, headers=headers, timeout=15)
            if resp.status_code == 429:
                # Apenas se for 429 (cota da tool Search Grounding), tenta sem a tool
                payload_no_tools = {
                    "contents": payload["contents"],
                    "generationConfig": payload["generationConfig"]
                }
                url_fb = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key={api_key}"
                resp = requests.post(url_fb, json=payload_no_tools, headers=headers, timeout=12)
            elif resp.status_code in (401, 403):
                _GEMINI_KEY_BLOCKED = True

            if resp.status_code == 200:
                res_json = resp.json()
                candidates = res_json.get("candidates", [])
                if candidates:
                    for p in candidates[0].get("content", {}).get("parts", []):
                        if "text" in p:
                            raw_text += p["text"]
            else:
                print(f"⚠️ [Gemini API] HTTP {resp.status_code}. Acionando contingência de IA (Groq) com a Súmula Oficial...")

        if not raw_text.strip():
            raw_text = _consultar_groq_fallback(prompt_text)

        if not raw_text.strip():
            # Se ambas as IAs falharem, NUNCA emite falso "CONFIRMADO" sem avaliar a súmula!
            h_t = dossie.get("home_team", "Mandante")
            a_t = dossie.get("away_team", "Visitante")
            if alerta_estrutural:
                return {
                    "status": "ALERTA_RISCO",
                    "grau_risco": "ALTO",
                    "badge_risco": "🔴 Risco Alto",
                    "tem_alerta": True,
                    "resumo_escalacao": sumula_info.get("resumo_curto", "Alerta estrutural na súmula oficial"),
                    "titulo_alerta": f"🔴 Alerta [Risco Alto]: {h_t} vs {a_t}",
                    "parecer_explicativo": f"{h_t} x {a_t} [🔴 Risco Alto]: Auditoria da súmula oficial detectou alteração crítica na escalação: " + "; ".join(motivos_elenco) + "."
                }
            return {
                "status": None,
                "grau_risco": "DESCONHECIDO",
                "badge_risco": "⚪ Indefinido",
                "tem_alerta": False,
                "resumo_escalacao": "Auditoria de IA indisponível no momento",
                "titulo_alerta": "",
                "parecer_explicativo": "Não foi possível concluir a inferência de IA neste ciclo; nova tentativa será realizada no próximo ciclo."
            }

        raw_text = raw_text.strip()
        json_match = re.search(r'(\{.*\})', raw_text, re.DOTALL)
        if json_match:
            data = json.loads(json_match.group(1))
        else:
            clean_text = re.sub(r'^```json\s*', '', raw_text)
            clean_text = re.sub(r'^```\s*', '', clean_text)
            clean_text = re.sub(r'\s*```$', '', clean_text)
            data = json.loads(clean_text)

        raw_grau = str(data.get("grau_risco") or "").strip().upper()
        if alerta_estrutural:
            grau_risco = "ALTO"
        elif raw_grau in ("ALTO", "HIGH"):
            grau_risco = "ALTO"
        elif raw_grau in ("MEDIO", "MÉDIO", "MEDIUM"):
            grau_risco = "MEDIO"
        elif raw_grau in ("BAIXO", "LOW"):
            grau_risco = "BAIXO"
        else:
            grau_risco = "ALTO" if (data.get("tem_alerta") or data.get("status") == "ALERTA_RISCO") else "BAIXO"

        badge_risco = "🔴 Risco Alto" if grau_risco == "ALTO" else ("🟡 Risco Médio" if grau_risco == "MEDIO" else "🟢 Risco Baixo")

        tem_alerta_final = bool(grau_risco in ("ALTO", "MEDIO") or data.get("tem_alerta") or data.get("status") == "ALERTA_RISCO" or alerta_estrutural)
        status_final = "ALERTA_RISCO" if tem_alerta_final else "CONFIRMADO"
        parecer_final = str(data.get("parecer_explicativo", "Auditoria de campo concluída.")).strip()
        if alerta_estrutural and motivos_elenco and not data.get("tem_alerta"):
            parecer_final = f"[Alerta de Súmula: {'; '.join(motivos_elenco)}] {parecer_final}"

        # Assegura que a explicação inicie com o confronto e a badge de risco (Time A x Time B [🔴 Risco Alto]: ...)
        h_t = dossie.get("home_team")
        a_t = dossie.get("away_team")
        if h_t and a_t:
            prefixo_jogo_badge = f"{h_t} x {a_t} [{badge_risco}]: "
            # Limpa prefixos parciais anteriores se houver
            parecer_final = re.sub(r'^' + re.escape(h_t) + r'\s*x\s*' + re.escape(a_t) + r'(?:\s*\[[^\]]+\])?:\s*', '', parecer_final).strip()
            parecer_final = f"{prefixo_jogo_badge}{parecer_final}"

        if grau_risco == "ALTO":
            titulo_final = f"🔴 Alerta [Risco Alto]: {h_t} vs {a_t}"
        elif grau_risco == "MEDIO":
            titulo_final = f"🟡 Alerta [Risco Médio]: {h_t} vs {a_t}"
        else:
            titulo_final = f"🟢 Sinal Verde [Risco Baixo]: {h_t} vs {a_t}"

        return {
            "status": status_final,
            "grau_risco": grau_risco,
            "badge_risco": badge_risco,
            "tem_alerta": tem_alerta_final,
            "resumo_escalacao": str(data.get("resumo_escalacao") or sumula_info.get("resumo_curto") or "Escalações checadas"),
            "titulo_alerta": titulo_final,
            "parecer_explicativo": parecer_final
        }
    except Exception as e:
        print(f"⚠️ [Sentinela IA] Falha no processamento da inferência: {e}")
        h_t = dossie.get("home_team", "Mandante")
        a_t = dossie.get("away_team", "Visitante")
        if alerta_estrutural:
            return {
                "status": "ALERTA_RISCO",
                "grau_risco": "ALTO",
                "badge_risco": "🔴 Risco Alto",
                "tem_alerta": True,
                "resumo_escalacao": sumula_info.get("resumo_curto", "Alerta na súmula oficial"),
                "titulo_alerta": f"🔴 Alerta [Risco Alto]: {h_t} vs {a_t}",
                "parecer_explicativo": f"{h_t} x {a_t} [🔴 Risco Alto]: A súmula oficial indicou alteração crítica: " + "; ".join(motivos_elenco) + "."
            }
        return {
            "status": None,
            "grau_risco": "DESCONHECIDO",
            "badge_risco": "⚪ Indefinido",
            "tem_alerta": False,
            "resumo_escalacao": "Auditoria pendente",
            "titulo_alerta": "",
            "parecer_explicativo": ""
        }


def auditar_e_recalibrar_linha_handicap(cursor, item: dict, user_ids: list) -> dict:
    """
    Audita e recalibra linhas de Handicap Asiático antes da auditoria do Gemini:
    1. Consulta cotações AH em tempo real na Betano (API direta).
    2. Identifica se houve distorção / inversão de favoritismo no mercado (ex: steam move).
    3. Se aposta confirmada (dinheiro real em conta): NUNCA altera a aposta, gera alerta para cashout.
    4. Se aposta pendente (não confirmada):
       - Submete as novas linhas ao Gatekeeper (calculate_unified_handicap_recommendation).
       - Se APROVADO: recalibra e atualiza a aposta pendente via sync_fixture_and_bet_handicap,
         permitindo que o Gemini audite a nova linha e confirme o Sinal Verde.
       - Se REPROVADO (NO_BET): veta o Sinal Verde e decreta ALERTA_RISCO / DESVIO_DE_LINHA.
    """
    # Inicialização explícita de variáveis numéricas locais (Regra 8)
    target_line = 0.0
    curr_odd = 0.0
    opp_odd = 0.0
    odd_abertura = float(item.get('odd') or 1.0)

    mercado = str(item.get('mercado') or '').lower()
    palpite_atual = str(item.get('palpite') or '').strip()
    is_handicap = ('handicap' in mercado) or ('ah' in palpite_atual.lower()) or (re.search(r'[+-]?\d+(?:\.\d+)?\s*ah', palpite_atual.lower()) is not None)

    if not is_handicap:
        return {'bloquear_sinal_verde': False, 'alerta_risco': False, 'linha_recalibrada': False}

    fixture_id = item['fixture_id']
    home_team = item['home_team']
    away_team = item['away_team']
    is_confirmada = (int(item.get('confirmada') or 0) == 1) or (int(item.get('tem_debito') or 0) > 0)

    try:
        from asian_handicap_engine import (
            fetch_all_betano_ah_lines,
            calculate_unified_handicap_recommendation,
            sync_fixture_and_bet_handicap
        )
    except ImportError as e_imp:
        print(f"⚠️ [Sentinela Recalibragem] Falha ao importar asian_handicap_engine: {e_imp}")
        return {'bloquear_sinal_verde': False, 'alerta_risco': False, 'linha_recalibrada': False}

    lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
    if not lines:
        print(f"ℹ️ [Sentinela Linhas AH] Sem cotações Betano em tempo real para {home_team} vs {away_team}. Mantendo linha original.")
        return {'bloquear_sinal_verde': False, 'alerta_risco': False, 'linha_recalibrada': False}

    is_away = away_team.lower() in palpite_atual.lower()
    m_line = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite_atual)
    target_line = float(m_line.group(1)) if m_line else 0.0

    supported_team = away_team if is_away else home_team
    opp_team = home_team if is_away else away_team

    for l in lines:
        l_line = float(l.get('line', 99))
        if abs(l_line - target_line) < 0.01:
            if l.get('is_away') == is_away:
                curr_odd = float(l.get('odd') or 0.0)
            else:
                opp_odd = float(l.get('odd') or 0.0)

    houve_distorcao = False
    motivo_distorcao = ""

    # Cenário A: Inversão de favoritismo na linha DNB (0.0 AH)
    if abs(target_line) < 0.01 and opp_odd > 1.0 and opp_odd < curr_odd and curr_odd >= 1.90:
        houve_distorcao = True
        motivo_distorcao = f"Inversão de favoritismo na linha 0.0: odd de {supported_team} subiu para {curr_odd:.2f} enquanto {opp_team} virou favorito a {opp_odd:.2f}."
    # Cenário B: Movimentação reversa expressiva de mercado (Steam Move contra o time apoiado)
    elif curr_odd >= (odd_abertura + 0.15) and curr_odd > 1.0:
        houve_distorcao = True
        motivo_distorcao = f"Movimentação reversa de mercado: odd de {supported_team} subiu de {odd_abertura:.2f} para {curr_odd:.2f} (pressão vendedora contra a tese)."
    # Cenário C: Derretimento abaixo do piso viável
    elif 1.0 < curr_odd < 1.45:
        houve_distorcao = True
        motivo_distorcao = f"Derretimento de cotação: odd caiu para {curr_odd:.2f}, abaixo do piso rentável do Gatekeeper (1.50)."
    # Cenário D: Linha retirada / indisponível
    elif curr_odd <= 1.0 and len(lines) > 0:
        houve_distorcao = True
        motivo_distorcao = f"Linha {palpite_atual} retirada ou sem liquidez na casa no pré-jogo."

    if not houve_distorcao:
        return {'bloquear_sinal_verde': False, 'alerta_risco': False, 'linha_recalibrada': False}

    print(f"⚠️ [Distorção de Linha AH Detectada] {home_team} vs {away_team}: {motivo_distorcao}")

    # Tratamento 1: Aposta Já Confirmada com Dinheiro Real
    if is_confirmada:
        tit_alerta = f"⚠️ Alerta de Mercado: Linha desfavorável em {home_team} vs {away_team}"
        par_alerta = (
            f"{home_team} x {away_team}: {motivo_distorcao} Sua aposta #{item['aposta_id']} ({palpite_atual} @ {odd_abertura:.2f}) "
            f"já foi confirmada anteriormente. Por segurança, a aposta foi mantida intacta. "
            f"Avalie a conveniência de cashout ou cobertura na sua casa de apostas."
        )
        return {
            'bloquear_sinal_verde': True,
            'alerta_risco': True,
            'titulo_alerta': tit_alerta,
            'parecer_alerta': par_alerta,
            'linha_recalibrada': False
        }

    # Tratamento 2: Aposta Pendente (Não Confirmada) -> Reavaliação Oficial no Gatekeeper
    try:
        cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
        fix_dict = cursor.fetchone()
        if not fix_dict:
            fix_dict = {
                'fixture_id': fixture_id,
                'home_team': home_team,
                'away_team': away_team,
                'ah_reasoning': item.get('resultado_detalhado') or item.get('ah_reasoning')
            }

        status_gk, palpite_sug, confianca, reasoning_gk, best_cand, approved_list = calculate_unified_handicap_recommendation(
            fixture_dict=fix_dict,
            betano_lines=lines,
            allow_api_fetch=False,
            cursor=cursor
        )

        if status_gk == 'APROVADO' and best_cand:
            novo_palpite = best_cand['palpite']
            nova_odd = float(best_cand['odd'])

            # Atualiza no banco sincronizando card e aposta
            sync_fixture_and_bet_handicap(
                cursor=cursor,
                fixture_id=fixture_id,
                home_team=home_team,
                away_team=away_team,
                fixture_date=item.get('fixture_date'),
                selected_palpite=novo_palpite,
                odd_val=nova_odd,
                odd_justa=float(best_cand.get('odd_justa', 1.80)),
                prob_poisson=float(best_cand.get('probabilidade', 60.0)),
                ev_perc=float(best_cand.get('ev', 5.0)),
                detalhe_calculo=reasoning_gk,
                user_ids=user_ids,
                confirmada_val=0,
                destaque_val=1,
                best_cand=best_cand
            )
            cursor.connection.commit()

            print(f"🎯 [Gatekeeper Recalibrado com Sucesso] Aposta #{item['aposta_id']} ajustada para '{novo_palpite}' (@ {nova_odd:.2f})")

            return {
                'bloquear_sinal_verde': False,
                'alerta_risco': False,
                'linha_recalibrada': True,
                'novo_palpite': novo_palpite,
                'nova_odd': nova_odd,
                'novo_reasoning': reasoning_gk,
                'motivo_recalibragem': motivo_distorcao
            }
        else:
            # Gatekeeper reprovou todas as linhas disponíveis no mercado atual
            print(f"🛑 [Gatekeeper Reprovou Linhas Vivas] {home_team} vs {away_team}: nenhuma linha aprovada no mercado atual.")
            tit_alerta = f"⚠️ Alerta: Distorção de Linha AH em {home_team} vs {away_team}"
            par_alerta = (
                f"{home_team} x {away_team}: {motivo_distorcao} O Gatekeeper reavaliou todas as linhas de handicap ao vivo na Betano "
                f"e reprovou novas entradas por ausência de valor seguro (+EV). "
                f"Recomendado NÃO apostar nesta partida."
            )
            return {
                'bloquear_sinal_verde': True,
                'alerta_risco': True,
                'titulo_alerta': tit_alerta,
                'parecer_alerta': par_alerta,
                'linha_recalibrada': False
            }
    except Exception as e_gk:
        print(f"❌ [Sentinela Recalibragem] Falha durante recálculo do Gatekeeper: {e_gk}")
        return {'bloquear_sinal_verde': False, 'alerta_risco': False, 'linha_recalibrada': False}


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
    groq_key = get_groq_api_key()
    if not api_key and not groq_key:
        print("❌ [Sentinela IA] Nenhuma chave de IA (GEMINI_API_KEY / VISION_API_KEY) encontrada no .env. Abortando execução.")
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
                       ft.home_team_id, ft.away_team_id, ft.home_team, ft.away_team, ft.league_name, ft.fixture_date,
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
                       ft.home_team_id, ft.away_team_id, ft.home_team, ft.away_team, ft.league_name, ft.fixture_date,
                       ft.referee_name, ft.ah_reasoning, ft.goals_home, ft.goals_away
                FROM apostas a
                JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
                WHERE a.status = 'Pendente'
                  AND a.auditoria_ia_status IS NULL
                  AND ft.fixture_date BETWEEN DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
                                          AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL %s MINUTE)
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
            if item.get('fixture_date'):
                # fixtures_trends.fixture_date está armazenado em UTC; converte para GMT -3 (Horário de Brasília)
                dt_raw = item['fixture_date']
                dt_utc = dt_raw if isinstance(dt_raw, datetime) else datetime.fromisoformat(str(dt_raw))
                dt_local = dt_utc - timedelta(hours=3)
                match_time = dt_local.strftime('%d/%m/%Y %H:%M')
            else:
                match_time = 'Hoje'
            is_confirmada = (int(item.get('confirmada') or 0) == 1) or (int(item.get('tem_debito') or 0) > 0)

            reasoning = item.get('resultado_detalhado') or item.get('ah_reasoning') or item.get('cards_reasoning') or ""
            u5j_summary = extrair_resumo_u5j(reasoning)

            # Recalibragem de Linhas AH e Salvaguarda contra Distorção de Mercado
            recalib = auditar_e_recalibrar_linha_handicap(cursor, item, user_ids)
            linha_recalibrada = recalib.get('linha_recalibrada', False)
            bloquear_sinal_verde = recalib.get('bloquear_sinal_verde', False)

            if recalib.get('alerta_risco'):
                # Gatekeeper reprovou novas linhas ou aposta confirmada com linha desfavorável
                st = 'ALERTA_RISCO'
                resumo = "Distorção de mercado: Gatekeeper reprovou novas linhas"
                parecer = recalib['parecer_alerta']
                tem_alerta = True
                titulo_alerta = recalib['titulo_alerta']
            else:
                if linha_recalibrada:
                    item['palpite'] = recalib['novo_palpite']
                    item['odd'] = recalib['nova_odd']
                    if recalib.get('novo_reasoning'):
                        reasoning = recalib['novo_reasoning']
                        u5j_summary = extrair_resumo_u5j(reasoning)

                motivo_gk = (reasoning[:300] if reasoning else "Aprovado no modelo matemático +EV.")
                if linha_recalibrada:
                    motivo_gk += f" [Linha recalculada pelo Gatekeeper no pré-jogo devido a: {recalib.get('motivo_recalibragem')}]"

                # Coleta a súmula oficial das escalações (/fixtures/lineups) e desfalques (/injuries) na API-Sports
                sumula_oficial = coletar_sumula_e_desfalques_apisports(
                    cursor=cursor,
                    fixture_id=fixture_id,
                    home_team_id=item.get('home_team_id'),
                    away_team_id=item.get('away_team_id'),
                    home_team=home_team,
                    away_team=away_team,
                    palpite=item.get('palpite', '')
                )

                # Monta o dossiê consolidado com a súmula oficial e desfalques
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
                    "motivo_gatekeeper": motivo_gk,
                    "sumula_oficial": sumula_oficial
                }

                print(f"\n🔍 Auditando: {home_team} vs {away_team} | Palpite: {dossie['palpite']} (@ {dossie['odd']})")

                # Consulta o Gemini com Google Search Grounding (com fallback para Groq + Súmula API-Sports)
                resultado = consultar_gemini_com_grounding(dossie, api_key)

                st = resultado["status"]
                if st is None:
                    print(f"⏸️ [Sentinela IA] Inferência indisponível para #{fixture_id}; não gravando falso CONFIRMADO.")
                    continue
                resumo = resultado["resumo_escalacao"]
                parecer = resultado["parecer_explicativo"]
                grau_risco = resultado.get("grau_risco", "ALTO" if st == "ALERTA_RISCO" else "BAIXO")
                badge_risco = resultado.get("badge_risco", "🔴 Risco Alto" if grau_risco == "ALTO" else ("🟡 Risco Médio" if grau_risco == "MEDIO" else "🟢 Risco Baixo"))

                tem_alerta = resultado["tem_alerta"] or bloquear_sinal_verde
                titulo_alerta = resultado.get("titulo_alerta") or f"{'🔴' if grau_risco == 'ALTO' else '🟡'} Alerta [{badge_risco}]: {home_team} vs {away_team}"

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
                    alerta_enviado = False
                    for uid in user_ids:
                        if registrar_alerta_sininho(cursor, uid, aposta_id, fixture_id, titulo_alerta, parecer, tipo="ALERTA_SENTINELA_IA"):
                            alerta_enviado = True
                    conn.commit()

                    # Dispara push notification para o smartphone via ntfy (alta prioridade)
                    tag_color = "red_circle" if grau_risco == "ALTO" else "yellow_circle"
                    if alerta_enviado:
                        enviar_push_mobile(
                            titulo=titulo_alerta,
                            mensagem=parecer,
                            fixture_id=fixture_id,
                            prioridade=4,  # High
                            tags=[tag_color, "warning", "soccer"]
                        )
                else:
                    total_confirmados += 1
                    print(f"✅ [FORÇA MÁXIMA CONFIRMADA]: {resumo}")
                    # Manifestação sempre ativa da Sentinela (Sinal Verde / Hora de Apostar)
                    if not is_confirmada:
                        if linha_recalibrada:
                            tit_verde = f"🟢 Sinal Verde [Risco Baixo]: Linha Ajustada para {item['palpite']} (@ {item['odd']:.2f}) - Hora de Apostar!"
                            msg_verde = f"{home_team} x {away_team} [🟢 Risco Baixo]: Linha recalibrada com sucesso pelo Gatekeeper no pré-jogo! Auditoria de escalações confirmada com força máxima: {resumo}. O jogo começa às {match_time}. Hora de garantir a entrada na sua bookmaker!"
                        else:
                            tit_verde = f"🟢 Sinal Verde [Risco Baixo]: {home_team} vs {away_team} - Hora de Apostar!"
                            msg_verde = f"{home_team} x {away_team} [🟢 Risco Baixo]: Auditoria pré-jogo concluída com força máxima! {resumo}. O jogo começa às {match_time}. Sua aposta ainda não foi confirmada na casa de apostas — hora de garantir a entrada!"
                        tipo_notif = "SINAL_VERDE_HORA_APOSTAR"
                    else:
                        tit_verde = f"🟢 Força Máxima Confirmada [Risco Baixo]: {home_team} vs {away_team}"
                        msg_verde = f"{home_team} x {away_team} [🟢 Risco Baixo]: Auditoria de escalação concluída: {resumo}. Titulares confirmados para apoiar sua aposta já registrada. Início: {match_time}."
                        tipo_notif = "SINAL_VERDE_CONFIRMADA"

                    verde_enviado = False
                    for uid in user_ids:
                        if registrar_alerta_sininho(cursor, uid, aposta_id, fixture_id, tit_verde, msg_verde, tipo=tipo_notif):
                            verde_enviado = True
                    conn.commit()

                    # Dispara push notification para o smartphone via ntfy (prioridade padrão)
                    if verde_enviado:
                        enviar_push_mobile(
                            titulo=tit_verde,
                            mensagem=msg_verde,
                            fixture_id=fixture_id,
                            prioridade=4,  # High (desperta notificação ativa com som no celular)
                            tags=["white_check_mark", "green_circle", "soccer"]
                        )

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
    parser.add_argument("--test-ntfy", action="store_true", help="Disparar push fake de teste forçado para validação no app ntfy")
    args = parser.parse_args()

    if args.test_ntfy:
        testar_push_mobile()
    else:
        processar_auditoria_prejogo(window_minutes=args.window, specific_fixture_id=args.fixture_id)

