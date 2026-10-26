#!/usr/bin/env python3
"""
scripts/test_gemini_grounding.py
Testa a conectividade da API Gemini com Google Search Grounding.
"""

import sys
import os

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from gemini_sentinel import consultar_gemini_com_grounding, get_gemini_api_key

def main():
    api_key = get_gemini_api_key()
    if not api_key:
        print("❌ GEMINI_API_KEY não encontrada.")
        sys.exit(1)

    print(f"🔑 Chave detectada com sucesso (prefixo {api_key[:6]}...). Testando chamada ao Gemini com Google Search Grounding...")
    
    dossie_teste = {
        "fixture_id": 999999,
        "home_team": "Flamengo",
        "away_team": "Palmeiras",
        "league_name": "Brasileirão Série A",
        "country": "Brasil",
        "match_time": "Hoje 20:00",
        "referee_name": "Wilton Pereira Sampaio",
        "palpite": "Flamengo -0.25 AH",
        "odd": 1.95,
        "u5j_summary": "Flamengo: 3V-1E-1D | Palmeiras: 3V-2E-0D",
        "xg_summary": "Casa 1.65 x 1.20 Fora",
        "motivo_gatekeeper": "Modelo Poisson identificou valor esperado no Flamengo jogando no Maracanã."
    }

    resultado = consultar_gemini_com_grounding(dossie_teste, api_key)
    print("\n--- RESPOSTA DA SENTINELA GEMINI ---")
    print(f"Status: {resultado.get('status')}")
    print(f"Tem Alerta: {resultado.get('tem_alerta')}")
    print(f"Resumo Escalação: {resultado.get('resumo_escalacao')}")
    print(f"Título Alerta: {resultado.get('titulo_alerta')}")
    print(f"Parecer Explicativo: {resultado.get('parecer_explicativo')}")
    print("-------------------------------------")
    print("✅ Teste de conexão e auditoria concluído com êxito!")

if __name__ == '__main__':
    main()
