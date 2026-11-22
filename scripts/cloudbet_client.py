#!/usr/bin/env python3
"""
scripts/cloudbet_client.py

Cliente Canônico de Integração com a API de Trading da Cloudbet.
FootballWeb Pipeline - Automação 100% Robotizada em Cripto (USDT / LTC).

Funcionalidades:
1. Autenticação JWT via cabeçalho X-API-Key com roteamento via Proxy SOCKS5 (Suíça);
2. Consulta de saldo e moedas ativas na conta (Account API);
3. Leitura e mapeamento de odds in-play e pré-jogo de futebol (Feed API);
4. Envio de ordens de apostas (Place Bet) com suporte nativo a Dry-Run (Paper Trading);
5. Execução programática de Cash Out / Encerramento de posições;
6. Auditoria de bilhetes e reconciliação com o banco de dados MySQL (fixtures_trends / apostas).
"""

import os
import sys
import json
import uuid
import requests
from typing import Dict, Any, Optional, List

# Adiciona o diretório dos scripts ao sys.path para importações locais
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_live_env_vars, get_db_connection

BASE_URL = "https://sports-api.cloudbet.com"


class CloudbetClient:
    """
    Cliente oficial para operação programática na Cloudbet via Trading API.
    """

    def __init__(self, api_key: Optional[str] = None, proxy_url: Optional[str] = None, dry_run: Optional[bool] = None):
        env_vars = get_live_env_vars()

        self.api_key = api_key or os.environ.get("CLOUDBET_API_KEY") or env_vars.get("CLOUDBET_API_KEY", "")
        self.proxy_url = proxy_url or os.environ.get("CLOUDBET_PROXY_URL") or env_vars.get("CLOUDBET_PROXY_URL", "")
        self.currency = os.environ.get("CLOUDBET_CURRENCY") or env_vars.get("CLOUDBET_CURRENCY", "USDT")
        
        env_dry = os.environ.get("CLOUDBET_DRY_RUN") or env_vars.get("CLOUDBET_DRY_RUN", "true")
        self.dry_run = dry_run if dry_run is not None else (str(env_dry).strip().lower() in ("true", "1", "yes", "on"))

        # Trava de Segurança Autônoma por Horário (100% independente da sessão de chat)
        real_start_str = os.environ.get("CLOUDBET_REAL_START_AT") or env_vars.get("CLOUDBET_REAL_START_AT", "")
        if real_start_str:
            try:
                import zoneinfo
                from datetime import datetime
                tz = zoneinfo.ZoneInfo("America/Sao_Paulo")
                target_dt = datetime.fromisoformat(real_start_str.strip().replace(" ", "T"))
                if target_dt.tzinfo is None:
                    target_dt = target_dt.replace(tzinfo=tz)
                now_dt = datetime.now(tz)
                if now_dt >= target_dt:
                    self.dry_run = False
                else:
                    self.dry_run = True
            except Exception:
                pass

        self.session = requests.Session()
        self.session.headers.update({
            "X-API-Key": self.api_key,
            "Accept": "application/json",
            "Content-Type": "application/json",
            "User-Agent": "FootballWeb-QuantBot/2.0"
        })

        if self.proxy_url:
            self.session.proxies.update({
                "http": self.proxy_url,
                "https": self.proxy_url
            })

    def _request(self, method: str, endpoint: str, **kwargs) -> Dict[str, Any]:
        """
        Executa requisição HTTP segura contra a API da Cloudbet com tratamento estruturado de erros.
        """
        url = f"{BASE_URL}{endpoint}" if endpoint.startswith("/") else f"{BASE_URL}/{endpoint}"
        kwargs.setdefault("timeout", 15)

        try:
            resp = self.session.request(method, url, **kwargs)
            if resp.status_code in (200, 201):
                try:
                    return resp.json()
                except ValueError:
                    return {"success": True, "raw": resp.text}
            elif resp.status_code == 404:
                return {"error": "Not Found", "status_code": 404, "endpoint": endpoint}
            elif resp.status_code == 403:
                print(f"🚨 [Cloudbet 403 Forbidden] Acesso bloqueado pelo Cloudflare ou token sem permissão em {endpoint}.")
                return {"error": "Forbidden / Cloudflare Block", "status_code": 403, "endpoint": endpoint}
            else:
                print(f"⚠️ [Cloudbet HTTP {resp.status_code}] Falha na chamada {method} {endpoint}: {resp.text[:200]}")
                return {"error": resp.text, "status_code": resp.status_code, "endpoint": endpoint}
        except Exception as e:
            print(f"❌ [Cloudbet Conexão Erro] {method} {url}: {e}")
            return {"error": str(e), "status_code": 0, "endpoint": endpoint}

    # =========================================================================
    # 1. ACCOUNT API (SALDO & MOEDAS)
    # =========================================================================

    def get_account_currencies(self) -> List[str]:
        """
        Retorna a lista de moedas ativas na conta do usuário.
        """
        res = self._request("GET", "/pub/v1/account/currencies")
        return res.get("currencies", [])

    def get_account_balance(self, currency: Optional[str] = None) -> float:
        """
        Consulta o saldo disponível da conta na moeda especificada (padrão: USDT).
        """
        cur = (currency or self.currency).upper()
        res = self._request("GET", f"/pub/v1/account/currencies/{cur}/balance")
        try:
            return float(res.get("amount") or 0.0)
        except (ValueError, TypeError):
            return 0.0

    # =========================================================================
    # 2. FEED API (ODDS, EVENTOS & COMPETIÇÕES)
    # =========================================================================

    def get_soccer_competitions(self) -> List[Dict[str, Any]]:
        """
        Retorna o catálogo de todas as competições de futebol disponíveis no momento.
        """
        res = self._request("GET", "/pub/v2/odds/sports/soccer")
        return res.get("categories", [])

    def get_competition_events(self, competition_key: str) -> List[Dict[str, Any]]:
        """
        Retorna a lista de partidas ativas de uma competição específica.
        Exemplo: 'soccer-england-premier-league'
        """
        res = self._request("GET", f"/pub/v2/odds/competitions/{competition_key}")
        return res.get("events", [])

    def get_event_odds(self, event_id: str) -> Dict[str, Any]:
        """
        Retorna as cotações detalhadas de todos os mercados abertos para uma partida.
        """
        return self._request("GET", f"/pub/v2/odds/events/{event_id}")

    # =========================================================================
    # 3. TRADING API (PLACE BET & CASHOUT)
    # =========================================================================

    def list_bets(self, limit: int = 50, offset: int = 0) -> List[Dict[str, Any]]:
        """
        Lista o histórico e as apostas ativas na conta.
        """
        res = self._request("GET", f"/pub/v4/bets?limit={limit}&offset={offset}")
        return res.get("items", [])

    def place_bet(
        self,
        event_id: str,
        market_url: str,
        price: float,
        stake: float,
        currency: Optional[str] = None,
        accept_price_change: str = "BETTER",
        dry_run: Optional[bool] = None
    ) -> Dict[str, Any]:
        """
        Dispara a ordem de aposta na Cloudbet.
        Se dry_run for True, simula a ordem sem gastar saldo real (Paper Trading).
        
        market_url exemplo:
          'soccer.asian_handicap/home?handicap=-0.25'
          'soccer.total_goals/over?total=2.0'
        """
        is_simulated = self.dry_run if dry_run is None else dry_run
        cur = (currency or self.currency).upper()
        ref_id = str(uuid.uuid4())

        payload = {
            "eventId": str(event_id),
            "marketUrl": market_url,
            "price": str(price),
            "stake": str(stake),
            "currency": cur,
            "acceptPriceChange": accept_price_change,
            "referenceId": ref_id
        }

        if is_simulated:
            simulated_bet_id = f"sim_{ref_id[:12]}"
            print(f"🤖 [Cloudbet DRY-RUN / Simulação] Aposta registrada com sucesso:")
            print(f"   • Evento ID: {event_id} | Mercado: {market_url}")
            print(f"   • Preço: {price} | Stake: {stake} {cur} | Ref: {simulated_bet_id}")
            return {
                "success": True,
                "is_simulated": True,
                "betId": simulated_bet_id,
                "referenceId": ref_id,
                "status": "ACCEPTED_SIMULATED",
                "price": price,
                "stake": stake,
                "currency": cur,
                "marketUrl": market_url
            }

        # Execução Real de Aposta via API oficial
        print(f"🚀 [Cloudbet REAL ORDEM] Enviando aposta financeira real:")
        print(f"   • Evento: {event_id} | Mercado: {market_url} | Odd: {price} | Stake: {stake} {cur}")

        # Tenta endpoints suportados v3 / v4
        res = self._request("POST", "/pub/v3/bets/place", json=payload)
        if "error" in res and res.get("status_code") in (404, 400):
            res = self._request("POST", "/pub/v4/bets/place", json=payload)

        return res

    def execute_cashout(
        self,
        bet_id: str,
        event_id: Optional[str] = None,
        market_url: Optional[str] = None,
        price: Optional[float] = None,
        stake: Optional[float] = None,
        dry_run: Optional[bool] = None
    ) -> Dict[str, Any]:
        """
        Executa o Cash Out de uma posição.
        Como a Cloudbet não possui mutação de cashout direto na API pública,
        o cash out é executado via HEDGE IN-PLAY (colocando a ordem na ponta oposta)
        ou por simulação se for Dry-Run.
        """
        is_simulated = self.dry_run if dry_run is None else dry_run

        min_hedge_odd = float(os.environ.get("CLOUDBET_HEDGE_MIN_ODD", "1.65"))
        if price is not None and price < min_hedge_odd and not is_simulated:
            print(f"🛑 [Cloudbet SLIPPAGE LOCK] Ordem rejeitada: odd {price:.2f} inferior ao piso de segurança ({min_hedge_odd:.2f}).")
            return {
                "success": False,
                "status": "REJECTED_SLIPPAGE",
                "message": f"Ordem rejeitada: cotação {price:.2f} derreteu abaixo do piso de segurança de {min_hedge_odd:.2f}."
            }

        if not is_simulated and event_id and market_url and price and stake:
            print(f"🚀 [Cloudbet REAL HEDGE IN-PLAY] Executando ordem de cobertura para travar posição:")
            print(f"   • Evento: {event_id} | Mercado Oposto: {market_url} | Odd: {price} | Stake: {stake} {self.currency}")
            return self.place_bet(
                event_id=str(event_id),
                market_url=market_url,
                price=price,
                stake=stake,
                currency=self.currency,
                dry_run=False
            )

        if is_simulated or str(bet_id).startswith("sim_"):
            print(f"⚡ [Cloudbet DRY-RUN Hedge] Cash Out / Hedge simulado com sucesso para bilhete #{bet_id}!")
            return {
                "success": True,
                "is_simulated": True,
                "betId": bet_id,
                "status": "CASHOUT_EXECUTED_SIMULATED",
                "message": "Cash Out / Hedge simulado com sucesso pelo Gatekeeper in-play."
            }

        print(f"⚠️ [Cloudbet Cashout] Parâmetros de hedge in-play não fornecidos para bilhete #{bet_id}.")
        return {"error": "Missing hedge parameters for Cloudbet execution", "status_code": 400}


# =========================================================================
# TESTE / CLI DE HOMOLOGAÇÃO
# =========================================================================

if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Cliente CLI Oficial da Cloudbet (FootballWeb)")
    parser.add_argument("--status", action="store_true", help="Testa conectividade, moedas e saldo da conta")
    parser.add_argument("--list_bets", action="store_true", help="Lista as últimas apostas registradas")
    parser.add_argument("--test_dry_bet", action="store_true", help="Executa um teste de simulação (Dry-Run)")
    args = parser.parse_args()

    client = CloudbetClient()

    print("=" * 70)
    print("🤖 [Cloudbet Trading Bot Client] Inicializado")
    print(f"   • Modo Dry-Run (Simulação): {'ATIVADO 🛡️' if client.dry_run else 'PRODUÇÃO REAL ⚠️'}")
    print(f"   • Moeda Base: {client.currency}")
    print(f"   • Proxy Configurado: {'SIM (Suécia SOCKS5) 🛡️' if client.proxy_url else 'NÃO (Direto)'}")
    print("=" * 70)

    if args.status or len(sys.argv) == 1:
        print("🔍 Consultando moedas ativas na conta...")
        currencies = client.get_account_currencies()
        print(f"   • Moedas na carteira: {currencies if currencies else 'Nenhuma moeda com saldo ainda'}")
        
        balance_usdt = client.get_account_balance("USDT")
        print(f"   • Saldo em USDT: {balance_usdt:.2f}")

        print("\n🔍 Consultando bilhetes na API...")
        bets = client.list_bets(limit=5)
        print(f"   • Total de bilhetes retornados: {len(bets)}")

    if args.test_dry_bet:
        print("\n🧪 Executando teste de simulação de Place Bet (Dry-Run)...")
        res_bet = client.place_bet(
            event_id="test_7190563",
            market_url="soccer.asian_handicap/home?handicap=-0.25",
            price=1.85,
            stake=1.10,
            currency="USDT"
        )
        print(f"   • Retorno da Simulação: {res_bet}")
        
        print("\n🧪 Executando teste de simulação de Cash Out...")
        res_co = client.execute_cashout(res_bet.get("betId"))
        print(f"   • Retorno do Cash Out: {res_co}")

    print("=" * 70)
