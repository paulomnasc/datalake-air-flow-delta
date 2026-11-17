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


def get_groq_api_key() -> str:
    """
    Recupera a chave de API para o Groq (VISION_API_KEY) a partir das variáveis de ambiente.
    """
    env_vars = get_live_env_vars()
    api_key = os.environ.get("VISION_API_KEY") or env_vars.get("VISION_API_KEY", "")
    return api_key.strip()


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

    text_lower = analise_text.lower()

    # Isola o bloco do tópico 4 se presente
    secao_4 = ""
    if "4️⃣" in analise_text or "veredito" in text_lower:
        partes = re.split(r"4️⃣|veredito\s*e\s*gest[ãa]o\s*de\s*banca", analise_text, flags=re.IGNORECASE)
        if len(partes) > 1:
            secao_4 = partes[1].lower()

    target_text = secao_4 if secao_4 else text_lower

    # Checa indicação explícita de CASHOUT
    if "🔴" in target_text or "alerta de cashout" in target_text or "cashout imediato" in target_text:
        return "CASHOUT_IMEDIATO"
    elif "🟡" in target_text or "atenção" in target_text or "atencao" in target_text or "volatilidade" in target_text:
        return "ATENCAO"
    elif "🟢" in target_text or "manter aposta" in target_text or "manter" in target_text:
        return "MANTER_APOSTA"

    # Fallback seguro: se não encontrar no bloco 4, checa no texto completo
    if "alerta de cashout imediato" in text_lower or "🔴 alerta de cashout" in text_lower:
        return "CASHOUT_IMEDIATO"
    elif "🟡 atenção" in text_lower:
        return "ATENCAO"

    return "MANTER_APOSTA"


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

        dados_aposta_str = (
            f"• Mercado: {mercado}\n"
            f"• Palpite/Linha: {palpite}\n"
            f"• Cotação (Odd): {odd}\n"
            f"• Casa de Aposta: {casa}\n"
            f"• Partida: {home_team} x {away_team}"
        )

        system_prompt = (
            "Você é o Grok AI, Analista Tático Oficial e Auditor de Gestão de Risco do FootballWeb.\n"
            "Sua função é auditar a Súmula Oficial de Escalações e Desfalques Pré-Jogo confrontando-a DIRETAMENTE com a aposta realizada pelo usuário (especialmente Linhas de Handicap Asiático, Mercado e Odds).\n\n"
            "Estruture sua resposta estritamente nos seguintes tópicos de forma direta e concisa:\n"
            "1️⃣ Formação Tática e Titulares: se as equipes estão com força máxima ou se há rodízio/time misto.\n"
            "2️⃣ Goleiro e Espinha Dorsal: se os goleiros titulares e a espinha dorsal (zaga, criação, ataque) estão mantidos.\n"
            "3️⃣ Análise Crítica da Linha da Aposta (Handicap / Mercado): cruze o poderio dos 11 titulares confirmados com a linha exata da aposta do usuário. Explique com clareza se a escalação confirmada favorece ou destrói a probabilidade da linha (ex: DNB 0.0 AH, linhas negativas -AH ou positivas +AH).\n"
            "4️⃣ Veredito e Gestão de Banca (Ação Recomendada):\n"
            "   - 🟢 MANTER APOSTA (Cenário favorável à linha escolhida)\n"
            "   - 🟡 ATENÇÃO / VOLATILIDADE (Cenário equilibrado / jogo truncado)\n"
            "   - 🔴 ALERTA DE CASHOUT IMEDIATO (Desequilíbrio grave contra a linha / Risco iminente de perda integral da stake).\n\n"
            "Responda em formato limpo, direto, com formatação rica em markdown, linguagem tática de futebol e foco absoluto na proteção do capital da banca."
        )

        user_prompt = (
            f"Título do Alerta: {titulo}\n\n"
            f"🎯 DADOS DA APOSTA DO USUÁRIO EM RISCO:\n{dados_aposta_str}\n\n"
            f"📋 DETALHES DA SÚMULA E ESCALAÇÃO OFICIAL:\n{sumula_texto}\n\n"
            "Por favor, emita seu diagnóstico tático completo, correlacionando a escalação oficial com a linha da aposta do usuário e fornecendo a recomendação clara de ação (inclusive sobre CASHOUT)."
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
        resp = requests.post(
            "https://api.groq.com/openai/v1/chat/completions",
            headers=headers,
            json=payload,
            timeout=20
        )
        status_code = resp.status_code

        if status_code != 200:
            print(f"⚠️ [Grok IA Erro] HTTP {status_code}: {resp.text[:200]}")
            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": f"Erro na consulta Grok AI (HTTP {status_code})",
                "is_cashout_recommended": False
            }

        data_json = resp.json()
        choices = data_json.get("choices", [])
        if not choices:
            return {
                "success": False,
                "cached": False,
                "veredito": "MANTER_APOSTA",
                "analise_ia": "Resposta vazia do Grok AI.",
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
