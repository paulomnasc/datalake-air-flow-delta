#!/usr/bin/env python3
"""
scripts/atualizar_cotacoes_cripto.py

Rotina de Atualização Automática de Cotações de Criptomoedas (Airflow DAG / CLI)
Consulta a API pública da Binance Brasil para os pares BRL (ex: LTC/BRL, BTC/BRL, ETH/BRL, USDT/BRL)
e atualiza a coluna 'cotacao_brl' na tabela 'moedas_config' do MySQL.

Regras de Ouro:
- Regra nº 18: Carrega credenciais exclusivamente via db_config.py / .env
- Regra nº 15: Tolerância zero a exceções silenciadas; visibilidade completa de erros
- Regra nº 9: Proibição de fallbacks artificiais inventados
"""

import os
import sys
import requests
from datetime import datetime

# Adiciona o diretório de scripts ao path de importação
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection


def obter_cotacao_binance(codigo_moeda: str, timeout: int = 10) -> float:
    """
    Consulta o preço spot atual de uma criptomoeda em Reais (BRL) na API pública da Binance.
    Endpoint oficial: https://api.binance.com/api/v3/ticker/price?symbol={SYMBOL}BRL
    """
    symbol = f"{codigo_moeda.upper()}BRL"
    url = f"https://api.binance.com/api/v3/ticker/price?symbol={symbol}"

    try:
        response = requests.get(url, timeout=timeout)
        if response.status_code != 200:
            raise ValueError(
                f"❌ [ERRO API BINANCE] Status HTTP {response.status_code} para o par {symbol}. "
                f"Resposta: {response.text}"
            )

        data = response.json()
        price_str = data.get("price")
        if not price_str:
            raise ValueError(
                f"❌ [ERRO API BINANCE] Campo 'price' ausente no retorno para {symbol}: {data}"
            )

        preco = float(price_str)
        if preco <= 0.0:
            raise ValueError(
                f"❌ [ERRO API BINANCE] Preço retornado inválido ou não-positivo ({preco}) para {symbol}."
            )

        return preco

    except requests.exceptions.RequestException as e_req:
        raise ConnectionError(
            f"❌ [FALHA DE REDE/TIMEOUT] Não foi possível consultar a cotação de {symbol} na Binance: {e_req}"
        )


def atualizar_cotacoes_cripto() -> dict:
    """
    Executa o ciclo de atualização de cotações para todas as criptomoedas ativas no banco de dados.
    """
    print("=" * 75)
    print(f"🔄 [Cripto Sync] Iniciando sincronização de cotações com Binance às {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}...")
    print("=" * 75)

    conn = get_db_connection()
    resultados = {
        "atualizadas": 0,
        "erros": 0,
        "detalhes": []
    }

    try:
        with conn.cursor() as cursor:
            # 1. Consulta criptomoedas cadastradas e ativas
            cursor.execute("""
                SELECT id, codigo, nome, simbolo, cotacao_brl, is_default
                FROM moedas_config
                WHERE tipo = 'CRYPTO' AND ativo = 1
                ORDER BY id ASC
            """)
            criptos = cursor.fetchall()

            if not criptos:
                print("ℹ️ Nenhuma criptomoeda ativa encontrada na tabela moedas_config.")
                return resultados

            print(f"📋 Encontradas {len(criptos)} criptomoeda(s) ativas para atualização:")

            for c in criptos:
                moeda_id = c["id"]
                codigo = c["codigo"]
                nome = c["nome"]
                cotacao_anterior = float(c["cotacao_brl"] or 0.0)

                try:
                    # 2. Consulta API pública da Binance
                    nova_cotacao = obter_cotacao_binance(codigo)

                    # 3. Calcula variação
                    variacao_pct = 0.0
                    if cotacao_anterior > 0:
                        variacao_pct = ((nova_cotacao - cotacao_anterior) / cotacao_anterior) * 100.0

                    # 4. Atualiza no banco de dados
                    cursor.execute("""
                        UPDATE moedas_config
                        SET cotacao_brl = %s, updated_at = NOW()
                        WHERE id = %s
                    """, (nova_cotacao, moeda_id))

                    conn.commit()

                    sinal = "+" if variacao_pct > 0 else ""
                    print(
                        f"  ✅ [{codigo}] {nome}: R$ {cotacao_anterior:,.2f} ➔ R$ {nova_cotacao:,.2f} "
                        f"({sinal}{variacao_pct:.2f}%)"
                    )

                    resultados["atualizadas"] += 1
                    resultados["detalhes"].append({
                        "codigo": codigo,
                        "anterior": cotacao_anterior,
                        "nova": nova_cotacao,
                        "variacao_pct": variacao_pct,
                        "status": "SUCESSO"
                    })

                except Exception as err_moeda:
                    print(f"  ❌ [{codigo}] Erro ao atualizar cotação: {err_moeda}")
                    resultados["erros"] += 1
                    resultados["detalhes"].append({
                        "codigo": codigo,
                        "anterior": cotacao_anterior,
                        "erro": str(err_moeda),
                        "status": "ERRO"
                    })

    finally:
        conn.close()

    print("=" * 75)
    print(
        f"🏁 [Cripto Sync] Concluído: {resultados['atualizadas']} atualizada(s), "
        f"{resultados['erros']} erro(s)."
    )
    print("=" * 75)

    if resultados["erros"] > 0 and resultados["atualizadas"] == 0:
        raise RuntimeError(
            f"❌ [FALHA CRÍTICA] Todas as tentativas de atualização de cotação falharam ({resultados['erros']} erros)."
        )

    return resultados


if __name__ == "__main__":
    try:
        res = atualizar_cotacoes_cripto()
        sys.exit(0 if res["erros"] == 0 else 1)
    except Exception as e_main:
        print(f"❌ [FALHA FATAL NA EXECUÇÃO]: {e_main}")
        sys.exit(1)
