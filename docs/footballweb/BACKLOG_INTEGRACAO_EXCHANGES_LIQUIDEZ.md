# Backlog Arquitetural: Integração de Bookmakers Globais e Exchanges de Liquidez Contínua

Este documento formaliza o planejamento estratégico e a memória técnica para desacoplar o sistema FootballWeb da dependência exclusiva da Trading API da Cloudbet, viabilizando fontes de liquidez que não praticam bloqueio preventivo de linhas principais (*SELECTION_DISABLED* no pré-jogo).

---

## 1. Motivação e Diagnóstico Técnico
- **Problema Observado na Cloudbet**:
  - A Cloudbet adota uma política de risco ultra-defensiva para proteger seus livros em criptoativos. Em grande parte das partidas com alta liquidez (ex.: rodadas da AFC Champions League, Serie A Italiana, Premier League), as seleções de **Handicap Asiático** (`soccer.asian_handicap`), **Total de Gols** (`soccer.total_goals`) e **1X2** (`soccer.match_odds`) permanecem com status `SELECTION_DISABLED` e `price = 0` durante praticamente toda a janela pré-jogo.
  - Apenas mercados secundários de margem alta (escanteios, autores de gol, handicaps parciais de 1T) ou o submódulo de **In-Play Sniper** (15' a 35' do 1T) conseguem capturar ordens.
- **Impacto no Negócio**:
  - Queda substancial no volume operacional de apostas de valor esperado positivo (+EV) no pré-jogo.

---

## 2. Opções Estratégicas Mapeadas

### Opção 1 (Estratégia Imediata Ativa): Sniper In-Play Cloudbet
- **Foco**: Concentrar a captura nos minutos em que a Cloudbet é forçada pelo mercado a reabrir suas cotações ao vivo (janela dos 15' aos 35' do 1T em placar 0x0).
- **Status**: Implementado e operacional em `scripts/criar_apostas_cloudbet_diario.py`.

### Opção 3 (Backlog Estratégico de Longo Prazo): Provedores Alternativos de Liquidez Contínua

Para eliminar em definitivo o gargalo de bookmakers conservadores, estão mapeadas três arquiteturas complementares:

#### 1. Betfair Exchange API-NG (via Broker / OrbitX)
- **Modelo**: Bolsa de Apostas (Peer-to-Peer / Exchange).
- **Vantagens**:
  - Não existe "torneira fechada": as odds são formadas pelos próprios apostadores e formadores de mercado (*market makers*).
  - Linhas de Over/Under e Asian Handicap abertas 24h antes e ativas até os acréscimos.
  - Zero risco de banimento de conta ou limitação por ganhar (+EV).
- **Requisitos de Implementação**:
  - Conexão REST/JSON com a API-NG da Betfair ou credenciais de API Broker OrbitX/Piwi247.

#### 2. SX Bet (Smart Contract Betting Exchange em Arbitrum / Polygon)
- **Modelo**: Web3 Betting Exchange descentralizada com settlement on-chain em USDC/USDT.
- **Vantagens**:
  - Integração 100% nativa via API aberta (`@sx-bet/api`).
  - Sem restrição geográfica por VPN e sem congelamento arbitrário de linhas.
  - Liquidez permanente fornecida por formadores de mercado algorítmicos.

#### 3. Integração com Corretoras Asiáticas (Pinnacle / PS3838 / Asianconnect)
- **Modelo**: Bookmaker de Margem Reduzida e Altos Limites.
- **Vantagens**:
  - Referência global de formação de preço (a odd mais eficiente do planeta).
  - Linhas asiáticas abertas ininterruptamente com liquidez líquida.
- **Requisitos**:
  - Acesso via API de broker ou feed parceiro.

---

## 3. Roteiro de Implementação Futura
1. **Fase 1**: Módulo de Abstração Multicorretoras (`scripts/broker_interface.py`).
2. **Fase 2**: Implementação do adaptador para Betfair Exchange / SX Bet.
3. **Fase 3**: Roteador inteligente de melhor preço (dispara a ordem na corretora que estiver com a melhor cotação e com a linha habilitada no momento).
