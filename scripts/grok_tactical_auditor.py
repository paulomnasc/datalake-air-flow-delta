#!/usr/bin/env python3
"""
scripts/grok_tactical_auditor.py

Módulo Canônico de Auditoria Tática Pré-Jogo com Grok AI (Groq API).
FootballWeb Pipeline - Automação 100% Robotizada.

Responsabilidades:
1. Auditar a Súmula Oficial de Escalações e Desfalques Pré-Jogo (T-45m a T-15m);
2. Confrontar a formação tática, goleiros e espinha dorsal diretamente com a linha de aposta (Handicap/Mercado/Odds);
3. Emitir o parecer tático idêntico ao "Robozinho" do Sininho (ApostaController.php);
4. Classificar o veredito tático de forma determinística:
   - MANTER_APOSTA (🟢)
   - ATENCAO (🟡)
   - CASHOUT_IMEDIATO (🔴)
5. Gravar a análise em cache na tabela notificacoes_usuario (coluna analise_ia);
6. Acionar ou recomendar o Hedge Pré-Jogo na Cloudbet apenas em caso de CASHOUT_IMEDIATO.

Regras de Ouro:
- Regra 1: Cache-First no MySQL.
- Regra 8: Inicialização prévia mandatória de variáveis numéricas locais.
- Regra 18: Zero credenciais hardcoded (uso dinâmico de VISION_API_KEY).
"""

import os
import sys
import re
import json
import time
import requests
from typing import Dict, Any, Optional, Tuple

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars

# Variáveis globais para monitoramento de quotas e controle de spam de alertas
_last_quota_alert_time = 0.0
_circuit_breaker_active = False


def _disparar_notificacoes_quota(titulo: str, mensagem: str, prioridade: int, cursor = None, conn = None):
    """
    Envia notificação simultânea no celular (ntfy push) e no navegador (notificacoes_usuario / Sininho).
    """
    # 1. Push Mobile Celular via ntfy
    try:
        from cloudbet_live_cashout import enviar_push_cashout
        enviar_push_cashout(titulo=titulo, mensagem=mensagem, prioridade=prioridade)
    except Exception as e_p:
        print(f"⚠️ [Push Quota Mobile Falha]: {e_p}")

    # 2. Sininho / Navegador Web
    try:
        owns = False
        if cursor is None:
            conn = get_db_connection()
            cursor = conn.cursor()
            owns = True

        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, fixture_id, aposta_id, tipo, titulo, mensagem, link, lida, criado_em
            ) VALUES (
                558, NULL, NULL, 'ALERTA_QUOTA_GROK', %s, %s, '/apostas', 0, NOW()
            )
        """, (titulo, mensagem))
        if conn:
            conn.commit()

        if owns and conn:
            cursor.close()
            conn.close()
        print(f"🔔 [Notificação Web Sininho] Alerta de quota registrado no navegador do usuário #558.")
    except Exception as e_w:
        print(f"⚠️ [Notificação Web Quota Falha]: {e_w}")


def monitorar_e_alertar_quotas_groq(
    headers: Dict[str, Any],
    cursor = None,
    conn = None
) -> Dict[str, Any]:
    """
    Inspeciona os headers de Rate Limit retornados pelo Groq em tempo real:
      - x-ratelimit-remaining-requests
      - x-ratelimit-limit-requests
      - x-ratelimit-remaining-tokens
      - x-ratelimit-limit-tokens
      - x-ratelimit-reset-requests
    Dispara alertas antecipados no celular (ntfy) e no navegador (notificacoes_usuario):
      - Nível Amarelo: <= 20% das requisições ou tokens restantes (aviso preventivo)
      - Nível Vermelho (Circuit Breaker): <= 5% das requisições ou tokens restantes (trava preventiva)
    """
    global _last_quota_alert_time, _circuit_breaker_active
    now = time.time()

    rem_req_str = headers.get("x-ratelimit-remaining-requests")
    lim_req_str = headers.get("x-ratelimit-limit-requests")
    rem_tok_str = headers.get("x-ratelimit-remaining-tokens")
    lim_tok_str = headers.get("x-ratelimit-limit-tokens")
    reset_str = headers.get("x-ratelimit-reset-requests") or "poucos segundos"

    if not rem_req_str or not lim_req_str:
        return {"circuit_breaker": False, "level": "OK"}

    try:
        rem_req = int(rem_req_str)
        lim_req = int(lim_req_str)
        rem_tok = int(rem_tok_str) if rem_tok_str else 8000
        lim_tok = int(lim_tok_str) if lim_tok_str else 8000
    except (ValueError, TypeError):
        return {"circuit_breaker": False, "level": "OK"}

    pct_req = (rem_req / lim_req) * 100.0 if lim_req > 0 else 100.0
    pct_tok = (rem_tok / lim_tok) * 100.0 if lim_tok > 0 else 100.0
    menor_pct = min(pct_req, pct_tok)

    # 1. Nível Vermelho Crítico (<= 5%): Ativa Circuit Breaker para evitar estouro da API
    if menor_pct <= 5.0 or rem_req <= 10:
        _circuit_breaker_active = True
        print(f"🚨 [GROK QUOTA CRÍTICA] Quota em {menor_pct:.1f}% ({rem_req}/{lim_req} reqs). Circuit Breaker ATIVADO!")

        if now - _last_quota_alert_time > 600:
            _last_quota_alert_time = now
            tit = "🚨 Quota Grok IA Crítica (5%) - Trava de Proteção Ativada"
            msg = (
                f"Urgente: Quota do Grok IA atingiu {menor_pct:.1f}% ({rem_req}/{lim_req} requisições restantes). "
                f"O robô pausou novas consultas automaticamente para evitar erro de estouro (HTTP 429). "
                f"Renovação prevista em: {reset_str}."
            )
            _disparar_notificacoes_quota(tit, msg, prioridade=5, cursor=cursor, conn=conn)

        return {"circuit_breaker": True, "level": "CRITICAL", "pct": menor_pct, "reset": reset_str}

    # 2. Nível Amarelo de Atenção (<= 20%): Alerta Preventivo com Antecedência no celular e navegador
    elif menor_pct <= 20.0 or rem_req <= 50:
        _circuit_breaker_active = False
        print(f"⚠️ [GROK QUOTA ALERTA] Quota em {menor_pct:.1f}% ({rem_req}/{lim_req} reqs). Enviando aviso preventivo...")

        if now - _last_quota_alert_time > 900:  # Cooldown 15 minutos
            _last_quota_alert_time = now
            tit = f"⚠️ Alerta Preventivo: Quotas Grok IA em {menor_pct:.0f}%"
            msg = (
                f"Atenção: A quota da API Grok está se esgotando! Restam {rem_req}/{lim_req} requisições "
                f"e {rem_tok}/{lim_tok} tokens. Renovação automática em: {reset_str}. "
                f"O sistema continua operando normalmente e avisará se atingir o limiar crítico."
            )
            _disparar_notificacoes_quota(tit, msg, prioridade=4, cursor=cursor, conn=conn)

        return {"circuit_breaker": False, "level": "WARNING", "pct": menor_pct, "reset": reset_str}

    else:
        _circuit_breaker_active = False
        return {"circuit_breaker": False, "level": "OK", "pct": menor_pct, "reset": reset_str}


def get_groq_api_key() -> str:
    """
    Recupera a chave de API para o Groq (VISION_API_KEY) a partir das variáveis de ambiente.
    """
    env_vars = get_live_env_vars()
    api_key = os.environ.get("VISION_API_KEY") or env_vars.get("VISION_API_KEY", "")
    return api_key.strip()


def get_gemini_api_key() -> str:
    """
    Recupera a chave de API para o Google Gemini (GEMINI_API_KEY) a partir das variáveis de ambiente.
    """
    env_vars = get_live_env_vars()
    api_key = os.environ.get("GEMINI_API_KEY") or env_vars.get("GEMINI_API_KEY", "")
    return api_key.strip()


def get_gemini_model() -> str:
    """
    Recupera o modelo canônico do Gemini configurado nas variáveis de ambiente.
    """
    env_vars = get_live_env_vars()
    model = os.environ.get("GEMINI_MODEL") or env_vars.get("GEMINI_MODEL", "gemini-3.1-flash-lite")
    return model.strip()


def consultar_gemini_fallback(
    system_prompt: str,
    user_prompt: str,
    timeout: int = 25
) -> Optional[str]:
    """
    Aciona a API do Google Gemini (gemini-3.1-flash-lite Free Tier) como fallback secundário
    quando a API do Grok estiver no limiar crítico (Circuit Breaker) ou retornar HTTP 429/erro.
    """
    gemini_key = get_gemini_api_key()
    if not gemini_key:
        print("⚠️ [Gemini Fallback] Chave GEMINI_API_KEY não configurada no ambiente.")
        return None

    model_name = get_gemini_model()
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model_name}:generateContent?key={gemini_key}"

    headers = {
        "Content-Type": "application/json",
        "User-Agent": "FootballWeb-GeminiFallback/1.0"
    }

    combined_prompt = f"{system_prompt}\n\n---\n\n{user_prompt}"
    payload = {
        "contents": [
            {
                "role": "user",
                "parts": [{"text": combined_prompt}]
            }
        ],
        "generationConfig": {
            "temperature": 0.2,
            "maxOutputTokens": 2048
        }
    }

    try:
        print(f"🔄 [Gemini Fallback] Consultando Google Gemini ({model_name})...")
        session = requests.Session()
        session.trust_env = False  # Evita bloqueios/hangs de resolução de proxy do ambiente
        resp = session.post(url, headers=headers, json=payload, timeout=timeout)
        if resp.status_code == 200:
            data = resp.json()
            candidates = data.get("candidates", [])
            if candidates:
                parts = candidates[0].get("content", {}).get("parts", [])
                text_parts = [p.get("text", "") for p in parts if "text" in p]
                full_text = "".join(text_parts).strip()
                if full_text:
                    print(f"✅ [Gemini Fallback Sucesso] Resposta recebida ({len(full_text)} caracteres).")
                    return full_text
        else:
            print(f"⚠️ [Gemini Fallback Erro] HTTP {resp.status_code}: {resp.text[:200]}")
    except Exception as e_gem:
        print(f"⚠️ [Gemini Fallback Exceção]: {e_gem}")

    return None


def parse_grok_verdict(analise_text: str) -> str:
    """
    Extrai o veredito oficial do texto retornado pelo Grok AI.
    Valores possíveis:
      - 'CASHOUT_IMEDIATO'
      - 'ATENCAO'
      - 'MANTER_APOSTA'
    """
    if not analise_text:
        return "MANTER_APOSTA"

    # 1. Procura tag canônica explícita se houver
    m_tag = re.search(r'VEREDITO_FINAL:\s*\[?(🔴|🟡|🟢)?\s*(CASHOUT_IMEDIATO|MANTER_APOSTA|ATENCAO)', analise_text, re.IGNORECASE)
    if m_tag:
        v = m_tag.group(2).upper()
        if 'CASHOUT' in v:
            return 'CASHOUT_IMEDIATO'
        if 'ATENCAO' in v:
            return 'ATENCAO'
        return 'MANTER_APOSTA'

    # 2. Isola a linha de recomendação final explícita (ex: Recomendação final: Mantenha a aposta)
    m_rec = re.search(r'recomend[aã]o\s*final:?\s*([^\n\.\!]+)', analise_text, re.IGNORECASE)
    if m_rec:
        rec_str = m_rec.group(1).lower()
        if any(k in rec_str for k in ['cash', 'hedge', 'encerra', '🔴']):
            return 'CASHOUT_IMEDIATO'
        if any(k in rec_str for k in ['manter', 'mantenha', '🟢']):
            return 'MANTER_APOSTA'
        if any(k in rec_str for k in ['atenç', 'atenc', '🟡', 'volatilidade']):
            return 'ATENCAO'

    # 3. Se não achou tag nem linha de recomendação final, analisa a seção 4 isolada
    secao_4 = ""
    if "4️⃣" in analise_text or "veredito" in analise_text.lower():
        partes = re.split(r"4️⃣|veredito\s*e\s*gest[ãa]o\s*de\s*banca", analise_text, flags=re.IGNORECASE)
        if len(partes) > 1:
            secao_4 = partes[1]

    target_text = secao_4 if secao_4 else analise_text

    # Analisa linha por linha para descartar tabelas de opções ou frases com "não recomendado"
    linhas = target_text.split('\n')
    for l in linhas:
        l_low = l.lower()
        # Ignora linhas da tabela de opções conceituais ou negativas
        if any(k in l_low for k in ['não recomendado', 'nao recomendado', 'a menos que', 'caso o mercado']):
            continue
        if '| 🔴' in l or '| 🟡' in l or '| 🟢' in l:
            continue
        if '🔴' in l or 'alerta de cashout imediato' in l_low or 'cashout imediato' in l_low:
            return 'CASHOUT_IMEDIATO'

    if '🟢' in target_text or 'manter aposta' in target_text.lower() or 'mantenha a aposta' in target_text.lower():
        return 'MANTER_APOSTA'
    if '🟡' in target_text or 'atenção' in target_text.lower() or 'atencao' in target_text.lower():
        return 'ATENCAO'

    return 'MANTER_APOSTA'


def auditar_escalacao_com_grok(
    aposta_dict: Dict[str, Any],
    sumula_texto: str,
    titulo: Optional[str] = None,
    cursor = None,
    conn = None
) -> Dict[str, Any]:
    """
    Executa a auditoria tática com Grok AI para uma aposta diante da súmula oficial.
    Regra 8: inicialização prévia de variáveis locais.
    """
    total_tentativas = 0
    status_code = 0
    aposta_id = aposta_dict.get("id") or aposta_dict.get("aposta_id")
    fixture_id = aposta_dict.get("fixture_id")

    home_team = aposta_dict.get("home_team") or aposta_dict.get("time_casa", "Mandante")
    away_team = aposta_dict.get("away_team") or aposta_dict.get("time_fora", "Visitante")
    mercado = aposta_dict.get("mercado", "N/D")
    palpite = aposta_dict.get("palpite", "N/D")
    odd = aposta_dict.get("odd", "N/D")
    casa = aposta_dict.get("casa_de_aposta", "Cloudbet")

    if not titulo:
        titulo = f"📋 Escalação Oficial: {home_team} x {away_team}"

    # 1. Protocolo Cache-First (Regra 1): Checa se já existe análise pronta no MySQL
    owns_conn = False
    if cursor is None:
        conn = get_db_connection()
        cursor = conn.cursor()
        owns_conn = True

    try:
        if aposta_id:
            cursor.execute("""
                SELECT id, analise_ia, titulo 
                FROM notificacoes_usuario 
                WHERE aposta_id = %s 
                  AND analise_ia IS NOT NULL 
                  AND analise_ia != ''
                ORDER BY id DESC LIMIT 1
            """, (aposta_id,))
            notif_cached = cursor.fetchone()
            if notif_cached and notif_cached.get("analise_ia"):
                analise_cached = notif_cached["analise_ia"]
                veredito_cached = parse_grok_verdict(analise_cached)
                print(f"⚡ [Grok IA Cache-First] Parecer já existente para Aposta #{aposta_id} -> Veredito: {veredito_cached}")
                return {
                    "success": True,
                    "cached": True,
                    "veredito": veredito_cached,
                    "analise_ia": analise_cached,
                    "is_cashout_recommended": (veredito_cached == "CASHOUT_IMEDIATO")
                }

        # 2. Prepara o Prompt Tático Canônico (idêntico ao PHP ApostaController.php)
        api_key = get_groq_api_key()
        if not api_key:
            print("❌ [Grok IA Auditor] Chave VISION_API_KEY ausente no ambiente.")
            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": "Chave de IA VISION_API_KEY não configurada.",
                "is_cashout_recommended": False
            }

        odd_justa = aposta_dict.get("odd_justa")
        ev_pct = aposta_dict.get("ev_percentual")
        prob_poisson = aposta_dict.get("probabilidade_poisson")
        gk_cat = aposta_dict.get("aposta_gatekeeper_category") or aposta_dict.get("gatekeeper_category") or "N/D"
        odd_h = aposta_dict.get("odd_home")
        odd_d = aposta_dict.get("odd_draw")
        odd_a = aposta_dict.get("odd_away")

        ev_str = f"+{float(ev_pct):.1f}%" if ev_pct is not None else "N/D"
        odd_justa_str = f"{float(odd_justa):.2f}" if odd_justa is not None else "N/D"
        prob_str = f"{float(prob_poisson):.1f}%" if prob_poisson is not None else "N/D"
        odds_1x2_str = f"Casa @ {float(odd_h):.2f} | Empate @ {float(odd_d):.2f} | Fora @ {float(odd_a):.2f}" if (odd_h and odd_d and odd_a) else "N/D"

        st_fx = str(aposta_dict.get("fixture_status") or aposta_dict.get("status") or "NS").strip().upper()
        g_h = aposta_dict.get("goals_home")
        g_a = aposta_dict.get("goals_away")
        elap = aposta_dict.get("elapsed")
        if st_fx in ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY') or (g_h is not None and g_a is not None and (int(g_h or 0) > 0 or int(g_a or 0) > 0)):
            situacao_str = f"Em Andamento ({st_fx} aos {elap or 0}' | Placar ao Vivo: {home_team} {g_h or 0} x {g_a or 0} {away_team})"
        else:
            situacao_str = "Pré-Jogo (Ainda não iniciada)"

        dados_aposta_str = (
            f"• Situação da Partida: {situacao_str}\n"
            f"• Partida: {home_team} x {away_team}\n"
            f"• Mercado: {mercado}\n"
            f"• Palpite / Linha Selecionada: {palpite}\n"
            f"• Cotação Contratada (Odd da Casa): {odd}\n"
            f"• Cotação Justa do Modelo (Fair Odd): {odd_justa_str}\n"
            f"• Probabilidade Projetada (Poisson / Gatekeeper): {prob_str}\n"
            f"• Valor Esperado Inicial (+EV): {ev_str}\n"
            f"• Categoria do Gatekeeper: {gk_cat}\n"
            f"• Odds 1X2 Oficiais de Mercado: {odds_1x2_str}\n"
            f"• Casa de Aposta: {casa}"
        )

        system_prompt = (
            "Você é o Grok AI, Analista Tático Oficial e Auditor de Gestão de Risco do FootballWeb.\n"
            "Sua função é auditar a Súmula Oficial de Escalações e Desfalques Pré-Jogo confrontando-a DIRETAMENTE com a aposta realizada pelo usuário (especialmente Linhas de Handicap Asiático, Mercado, Cotações e Valor Esperado +EV).\n\n"
            "DIRETRIZ MANDATÓRIA DE FORÇA MÁXIMA VS TIME MISTO:\n"
            "- A presença de 11 jogadores sem cartões ou lesões no vestiário NÃO SIGNIFICA FORÇA MÁXIMA.\n"
            "- Se uma equipe escalou reservas, garotos da base, goleiro reserva ou realizou 4 ou mais alterações em relação aos seus titulares habituais (ex: poupando suas estrelas para copas/descanso), você está TERMINANTEMENTE PROIBIDO de classificar como 'Força máxima'. Você DEVE classificar como 'Time Misto / Rodízio de Elenco' ou 'Time Reserva', destacando os titulares poupados.\n"
            "- SE A PARTIDA JÁ ESTIVER EM ANDAMENTO (Placar ao Vivo informado): leve em consideração o placar real de jogo. Se a equipe apoiada estiver vencendo ou cumprindo a linha com vantagem no placar, a aposta está em situação favorável de campo; NUNCA recomende cashout com frases como 'antes do apito inicial' para partidas já iniciadas.\n\n"
            "Estrutura da resposta (estritamente nestes tópicos de forma direta e concisa):\n"
            "1️⃣ Formação Tática e Titulares: se as equipes estão com força máxima real ou se há time reserva/rodízio/misto.\n"
            "2️⃣ Goleiro e Espinha Dorsal: se os goleiros titulares habituais e a espinha dorsal (zaga, criação, ataque) estão mantidos.\n"
            "3️⃣ Revisão Crítica da Aposta e do Valor Esperado (+EV):\n"
            "   - Cruze o poderio dos 11 titulares confirmados e eventuais desfalques com a linha da aposta, a odd contratada e o +EV original.\n"
            "   - Explique se a escalação confirmada PRESERVA ou AMPLIA a margem de +EV da entrada, ou se, pelo contrário, as ausências derrubaram a probabilidade a ponto de inverter a relação matemática para Valor Esperado Negativo (-EV).\n"
            "   - Se a probabilidade real de campo despencou por desfalques graves na equipe apoiada, a tese da aposta ruiu e o +EV foi destruído.\n"
            "4️⃣ Veredito e Gestão de Banca (Ação Recomendada):\n"
            "   - 🟢 MANTER APOSTA (Cenário favorável à linha escolhida; +EV preservado ou expandido)\n"
            "   - 🟡 ATENÇÃO / VOLATILIDADE (Cenário equilibrado / jogo truncado; margem de +EV estreita)\n"
            "   - 🔴 ALERTA DE CASHOUT IMEDIATO (Desfalques críticos no time da aposta / +EV destruído e transformado em -EV / Risco iminente de perda integral que exige hedge pré-jogo imediato).\n\n"
            "Ao final do tópico 4, você DEVE OBRIGATORIAMENTE incluir a linha de veredito canônico:\n"
            "VEREDITO_FINAL: [🟢 MANTER_APOSTA] ou [🟡 ATENCAO] ou [🔴 CASHOUT_IMEDIATO]\n\n"
            "Responda em formato limpo, direto, com formatação rica em markdown, linguagem tática de futebol e foco absoluto na proteção do capital da banca."
        )

        user_prompt = (
            f"Título do Alerta: {titulo}\n\n"
            f"🎯 DADOS DA APOSTA DO USUÁRIO EM RISCO E VALOR ESPERADO (+EV):\n{dados_aposta_str}\n\n"
            f"📋 DETALHES DA SÚMULA E ESCALAÇÃO OFICIAL:\n{sumula_texto}\n\n"
            "Por favor, emita seu diagnóstico tático completo, revisando a aposta e a sustentação do Valor Esperado (+EV) diante da escalação confirmada, fornecendo a recomendação clara de ação (inclusive sobre CASHOUT/HEDGE)."
        )

        # 3. Dispara a requisição HTTP para a API do Groq
        model_name = os.environ.get("TEXT_API_MODEL", "openai/gpt-oss-120b")
        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
            "User-Agent": "FootballWeb-GrokAuditor/1.0"
        }
        payload = {
            "model": model_name,
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt}
            ],
            "temperature": 0.2,
            "max_tokens": 2048
        }

        total_tentativas += 1

        # Trava preventiva se o Circuit Breaker de Quota estiver ativado
        global _circuit_breaker_active
        if _circuit_breaker_active:
            print("🛑 [Circuit Breaker Quota Grok Ativo] Acionando contingência imediata com Google Gemini...")
            gemini_text = consultar_gemini_fallback(system_prompt, user_prompt)
            if gemini_text:
                veredito_gemini = parse_grok_verdict(gemini_text)
                is_cashout_gemini = (veredito_gemini == "CASHOUT_IMEDIATO")
                print(f"🧠 [Gemini Fallback Concluído via Circuit Breaker] Aposta #{aposta_id} -> Veredito: {veredito_gemini}")
                if aposta_id:
                    cursor.execute("""
                        UPDATE notificacoes_usuario
                        SET analise_ia = %s
                        WHERE aposta_id = %s
                    """, (gemini_text, aposta_id))
                    if conn:
                        conn.commit()
                return {
                    "success": True,
                    "cached": False,
                    "provider": "gemini-fallback",
                    "veredito": veredito_gemini,
                    "analise_ia": gemini_text,
                    "is_cashout_recommended": is_cashout_gemini
                }

            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": "Consulta Grok pausada preventivamente pelo monitor de quotas (Circuit Breaker ativo) e contingência Gemini indisponível.",
                "is_cashout_recommended": False
            }

        resp = requests.post(
            "https://api.groq.com/openai/v1/chat/completions",
            headers=headers,
            json=payload,
            timeout=20
        )
        status_code = resp.status_code

        # Inspeciona os cabeçalhos de rate limit e alerta celular/navegador com antecedência
        quota_status = monitorar_e_alertar_quotas_groq(resp.headers, cursor=cursor, conn=conn)

        if status_code == 429:
            _circuit_breaker_active = True
            tit_429 = "🚨 Quota Grok IA Esgotada (HTTP 429)"
            msg_429 = "O limite da API Grok foi atingido. Ativando contingência com Google Gemini Flash-Lite."
            _disparar_notificacoes_quota(tit_429, msg_429, prioridade=5, cursor=cursor, conn=conn)

            print("🔄 [HTTP 429 Grok] Comutando imediatamente para contingência Google Gemini...")
            gemini_text = consultar_gemini_fallback(system_prompt, user_prompt)
            if gemini_text:
                veredito_gemini = parse_grok_verdict(gemini_text)
                is_cashout_gemini = (veredito_gemini == "CASHOUT_IMEDIATO")
                if aposta_id:
                    cursor.execute("""
                        UPDATE notificacoes_usuario
                        SET analise_ia = %s
                        WHERE aposta_id = %s
                    """, (gemini_text, aposta_id))
                    if conn:
                        conn.commit()
                return {
                    "success": True,
                    "cached": False,
                    "provider": "gemini-fallback",
                    "veredito": veredito_gemini,
                    "analise_ia": gemini_text,
                    "is_cashout_recommended": is_cashout_gemini
                }

            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": "Limite de quota da API Grok atingido (HTTP 429) e contingência Gemini indisponível.",
                "is_cashout_recommended": False
            }

        if status_code != 200:
            print(f"⚠️ [Grok IA Erro] HTTP {status_code}: {resp.text[:200]}")
            print("🔄 [Grok Erro] Tentando contingência com Google Gemini...")
            gemini_text = consultar_gemini_fallback(system_prompt, user_prompt)
            if gemini_text:
                veredito_gemini = parse_grok_verdict(gemini_text)
                is_cashout_gemini = (veredito_gemini == "CASHOUT_IMEDIATO")
                if aposta_id:
                    cursor.execute("""
                        UPDATE notificacoes_usuario
                        SET analise_ia = %s
                        WHERE aposta_id = %s
                    """, (gemini_text, aposta_id))
                    if conn:
                        conn.commit()
                return {
                    "success": True,
                    "cached": False,
                    "provider": "gemini-fallback",
                    "veredito": veredito_gemini,
                    "analise_ia": gemini_text,
                    "is_cashout_recommended": is_cashout_gemini
                }

            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": f"Erro na consulta Grok AI (HTTP {status_code}) e contingência Gemini indisponível.",
                "is_cashout_recommended": False
            }

        data_json = resp.json()
        choices = data_json.get("choices", [])
        if not choices:
            print("⚠️ [Grok IA Vazio] Sem escolhas retornadas. Tentando contingência com Google Gemini...")
            gemini_text = consultar_gemini_fallback(system_prompt, user_prompt)
            if gemini_text:
                veredito_gemini = parse_grok_verdict(gemini_text)
                is_cashout_gemini = (veredito_gemini == "CASHOUT_IMEDIATO")
                if aposta_id:
                    cursor.execute("""
                        UPDATE notificacoes_usuario
                        SET analise_ia = %s
                        WHERE aposta_id = %s
                    """, (gemini_text, aposta_id))
                    if conn:
                        conn.commit()
                return {
                    "success": True,
                    "cached": False,
                    "provider": "gemini-fallback",
                    "veredito": veredito_gemini,
                    "analise_ia": gemini_text,
                    "is_cashout_recommended": is_cashout_gemini
                }

            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": "Resposta vazia do Grok AI e contingência Gemini indisponível.",
                "is_cashout_recommended": False
            }

        conteudo_ia = choices[0].get("message", {}).get("content", "").strip()
        veredito = parse_grok_verdict(conteudo_ia)
        is_cashout = (veredito == "CASHOUT_IMEDIATO")

        print(f"🧠 [Grok IA Parecer Concluído] Aposta #{aposta_id} ({home_team} vs {away_team}) -> Veredito: {veredito}")

        # 4. Grava no Sininho / Cache para o usuário
        if aposta_id:
            cursor.execute("""
                UPDATE notificacoes_usuario
                SET analise_ia = %s
                WHERE aposta_id = %s
            """, (conteudo_ia, aposta_id))
            if conn:
                conn.commit()

        return {
            "success": True,
            "cached": False,
            "veredito": veredito,
            "analise_ia": conteudo_ia,
            "is_cashout_recommended": is_cashout
        }

    except Exception as e:
        print(f"❌ [Grok IA Exceção] Falha ao auditar escalação: {e}")
        try:
            print("🔄 [Exceção Grok] Tentando contingência com Google Gemini...")
            gemini_text = consultar_gemini_fallback(system_prompt, user_prompt)
            if gemini_text:
                veredito_gemini = parse_grok_verdict(gemini_text)
                is_cashout_gemini = (veredito_gemini == "CASHOUT_IMEDIATO")
                if aposta_id and cursor:
                    cursor.execute("""
                        UPDATE notificacoes_usuario
                        SET analise_ia = %s
                        WHERE aposta_id = %s
                    """, (gemini_text, aposta_id))
                    if conn:
                        conn.commit()
                return {
                    "success": True,
                    "cached": False,
                    "provider": "gemini-fallback",
                    "veredito": veredito_gemini,
                    "analise_ia": gemini_text,
                    "is_cashout_recommended": is_cashout_gemini
                }
        except Exception as e_inner:
            print(f"❌ [Gemini Fallback Exceção]: {e_inner}")

        return {
            "success": False,
            "cached": False,
            "veredito": "MANTER_APOSTA",
            "analise_ia": f"Exceção interna: {str(e)}",
            "is_cashout_recommended": False
        }
    finally:
        if owns_conn and conn:
            cursor.close()
            conn.close()


if __name__ == "__main__":
    # Teste rápido de integração
    sample_aposta = {
        "id": 999999,
        "home_team": "Real Madrid",
        "away_team": "Barcelona",
        "mercado": "Handicap Asiático",
        "palpite": "Real Madrid -0.25",
        "odd": 1.95,
        "casa_de_aposta": "Cloudbet",
        "fixture_id": 12345
    }
    sample_sumula = (
        "Real Madrid: Courtois; Carvajal, Militão, Rüdiger, Mendy; Tchouaméni, Valverde, Bellingham; Rodrygo, Mbappé, Vinicius Jr.\n"
        "Barcelona: Ter Stegen; Koundé, Cubarsí, Martínez, Balde; Casadó, Pedri, Olmo; Yamal, Lewandowski, Raphinha."
    )
    print("Testando auditoria Grok IA...")
    res = auditar_escalacao_com_grok(sample_aposta, sample_sumula)
    print("Resultado:", res["veredito"])
    print("Trecho:", res["analise_ia"][:300])
