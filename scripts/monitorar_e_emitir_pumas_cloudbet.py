#!/usr/bin/env python3
"""
Monitor e Emissor Pontual da Aposta #4977 (Fixture #1551043)
Partida: U.N.A.M. - Pumas x Monterrey (Liga MX)
Palpite: U.N.A.M. - Pumas +0.5 AH (Stake: 1.00 USDT)

Varre continuamente a Cloudbet Trading API a cada 30 segundos.
Assim que a mesa de apostas reabrir o mercado (SELECTION_ENABLED com price > 1.30),
dispara a ordem financeira real e persiste o resultado no banco de dados.
"""

import sys
import os
sys.path.insert(0, '/root/datalake-air-flow-delta')
sys.path.insert(0, '/root/datalake-air-flow-delta/scripts')

import time
import json
import uuid
from datetime import datetime
import zoneinfo
import pymysql.cursors

from scripts.db_config import get_db_connection
from scripts.cloudbet_client import CloudbetClient

EVENT_ID = "37185795"
TARGET_MARKET_URL = "soccer.asian_handicap/home?handicap=0.5"
ALT_MARKET_URL = "soccer.double_chance/home_draw"
APOSTA_ID = 4977
FIXTURE_ID = 1551043
STAKE = 1.00
CURRENCY = "USDT"

def check_and_place_bet():
    tz = zoneinfo.ZoneInfo("America/Sao_Paulo")
    client = CloudbetClient()
    
    print(f"[{datetime.now(tz).strftime('%Y-%m-%d %H:%M:%S')}] 🔍 Iniciando monitoramento da Cloudbet para Pumas x Monterrey (Evento #{EVENT_ID})...")
    
    iteration = 0
    while True:
        iteration += 1
        now = datetime.now(tz)
        now_str = now.strftime('%H:%M:%S')
        
        # Trava de horário limite (22:00 BRT - início da partida)
        if now.hour >= 22:
            print(f"[{now_str}] ⚠️ Horário de início do jogo atingido (22:00). Encerrando monitoramento.")
            break
            
        try:
            event_data = client.get_event_odds(EVENT_ID)
            if not event_data or "markets" not in event_data:
                print(f"[{now_str}] (Ciclo #{iteration}) Evento não retornou dados de mercado. Aguardando...")
                time.sleep(30)
                continue
                
            markets = event_data.get("markets", {})
            
            # 1. Tentar Asian Handicap +0.5 (home?handicap=0.5)
            ah_market = markets.get("soccer.asian_handicap", {})
            target_selection = None
            market_url_to_use = None
            
            for sub in ah_market.get("submarkets", {}).values():
                for sel in sub.get("selections", []):
                    if sel.get("outcome") == "home" and sel.get("params") == "handicap=0.5":
                        target_selection = sel
                        market_url_to_use = sel.get("marketUrl") or TARGET_MARKET_URL
                        break
                if target_selection:
                    break
                    
            # 2. Se AH não encontrado ou desabilitado, verificar Double Chance (home_draw) como contingência
            if not target_selection or target_selection.get("status") != "SELECTION_ENABLED":
                dc_market = markets.get("soccer.double_chance", {})
                for sub in dc_market.get("submarkets", {}).values():
                    for sel in sub.get("selections", []):
                        if sel.get("outcome") == "home_draw" and sel.get("status") == "SELECTION_ENABLED":
                            # Só usa Dupla Chance se estiver habilitada e AH desabilitado
                            if sel.get("price", 0) > 1.30:
                                target_selection = sel
                                market_url_to_use = sel.get("marketUrl") or ALT_MARKET_URL
                                break
                    if target_selection and target_selection.get("status") == "SELECTION_ENABLED":
                        break
                        
            status = target_selection.get("status") if target_selection else "NOT_FOUND"
            price = float(target_selection.get("price", 0)) if target_selection else 0.0
            
            if status == "SELECTION_ENABLED" and price >= 1.30:
                print(f"\n🎯 [{now_str}] LINHA LIBERADA PELA CLOUDBET!")
                print(f"   • Mercado: {market_url_to_use}")
                print(f"   • Cotação Atual: @ {price:.2f}")
                print(f"   • Stake: {STAKE:.2f} {CURRENCY}")
                print(f"   • Disparando ordem financeira real...")
                
                # Execução Real de Aposta via Cloudbet Trading API
                order_res = client.place_bet(
                    event_id=EVENT_ID,
                    market_url=market_url_to_use,
                    price=price,
                    stake=STAKE,
                    currency=CURRENCY,
                    accept_price_change="BETTER",
                    dry_run=False  # ORDEM REAL
                )
                
                print(f"📡 Resposta da Cloudbet API: {order_res}")
                
                bet_id = order_res.get("betId") or order_res.get("id") or order_res.get("referenceId")
                
                if order_res.get("success") or order_res.get("status") in ("ACCEPTED", "PENDING", "ACCEPTED_SIMULATED") or bet_id:
                    print(f"✅ ORDEM EFETIVADA COM SUCESSO! Bet ID: {bet_id}")
                    
                    # Atualização no Banco de Dados MySQL
                    conn = get_db_connection()
                    cursor = conn.cursor(pymysql.cursors.DictCursor)
                    try:
                        conn.begin()
                        
                        # Atualiza registro em apostas
                        cloudbet_meta = {
                            "bet_id": str(bet_id),
                            "event_id": str(EVENT_ID),
                            "market_url": market_url_to_use,
                            "price": price,
                            "stake": STAKE,
                            "currency": CURRENCY,
                            "is_simulated": False,
                            "placed_at": now.strftime('%Y-%m-%d %H:%M:%S')
                        }
                        audit_tag = f"|| CLOUDBET: {json.dumps(cloudbet_meta)} || "
                        
                        cursor.execute("""
                            UPDATE apostas 
                            SET tipo = 'Robô Cloudbet (Real)',
                                confirmada = 1,
                                odd = %s,
                                ganhos_potenciais = %s,
                                resultado_detalhado = CONCAT(%s, resultado_detalhado),
                                updated_at = NOW()
                            WHERE id = %s
                        """, (price, round(STAKE * price, 2), audit_tag, APOSTA_ID))
                        
                        # Atualiza saldo em conta_corrente e usuario
                        cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = 558")
                        u_row = cursor.fetchone()
                        saldo_atual = float(u_row['saldo_conta_corrente'] or 20.56)
                        novo_saldo = round(saldo_atual - STAKE, 4)
                        
                        cursor.execute("""
                            INSERT INTO conta_corrente (
                                usuario_id, aposta_id, tipo, moeda_codigo, descricao,
                                valor, saldo_anterior, saldo_posterior, criado_em
                            ) VALUES (
                                558, %s, 'DEBITO_APOSTA', %s,
                                'Débito Aposta #4977 (Cloudbet Real: U.N.A.M. - Pumas x Monterrey - Pumas +0.5 AH)',
                                %s, %s, %s, NOW()
                            )
                        """, (APOSTA_ID, CURRENCY, -STAKE, saldo_atual, novo_saldo))
                        
                        cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = 558", (novo_saldo,))
                        
                        conn.commit()
                        print(f"💾 Banco de dados atualizado: Saldo ajustado para {novo_saldo:.2f} USDT e aposta #4977 confirmada como real.")
                    except Exception as db_err:
                        conn.rollback()
                        print(f"❌ Erro ao atualizar banco de dados: {db_err}")
                    finally:
                        conn.close()
                    
                    return True
                else:
                    print(f"❌ Falha ao emitir aposta: {order_res.get('message', 'Erro desconhecido')}")
            else:
                print(f"[{now_str}] (Ciclo #{iteration}) Linha Pumas +0.5 AH: {status} (price: {price:.2f}). Aguardando abertura da mesa...")
                
        except Exception as e:
            print(f"[{now_str}] (Ciclo #{iteration}) Exceção ao consultar API: {e}")
            
        time.sleep(30)

if __name__ == "__main__":
    check_and_place_bet()
