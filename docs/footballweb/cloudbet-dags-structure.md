# Arquitetura de DAGs e Motores de Gatekeeper (Pipeline Cloudbet / FootballWeb)

Este documento descreve detalhadamente a arquitetura de dados, agendamento de DAGs no Apache Airflow, governança de risco e integração de APIs para o pipeline autônomo em criptomoeda (USDT / LTC) do FootballWeb na Cloudbet.

---

## 1. Visão Geral da Arquitetura Cripto 100% Robotizada

O pipeline do FootballWeb opera uma esteira quantitativa com integração ponta a ponta na **Cloudbet** através de proxy suíço e consumo direto de API REST (`api.cloudbet.com`):

```
+-------------------------------------------------------------------------------------------------+
|                                 ECOSSISTEMA FOOTBALLWEB                                         |
+-------------------------------------------------------------------------------------------------+
|                                                                                                 |
|   1. DESCOBERTA & ODÍOMETRIA        2. FILTRO QUANTITATIVO (+EV)        3. EXECUÇÃO & HEDGE     |
|   +-----------------------+         +--------------------------+        +---------------------+ |
|   | API-Sports / MySQL    | ------> | asian_handicap_engine    | -----> | cloudbet_client.py  | |
|   | fixtures_trends       |         | Poisson Bivariado        |        | POST /bets/place    | |
|   +-----------------------+         | Redutores U5J / Pedigree |        | (Micro-stakes USDT) | |
|               |                     +--------------------------+        +---------------------+ |
|               |                                  |                                 |            |
|               v                                  v                                 v            |
|   +-----------------------+         +--------------------------+        +---------------------+ |
|   | Cloudbet Matcher      |         | BetTier Governance       |        | Live Sentinel &     | |
|   | Reconciliador Textual |         | PREMIUM & QUASE_PREMIUM  |        | Cross-Hedge In-Play | |
|   | Feed Live / Pre-Game  |         | (Descarta Regular)       |        | /pub/v3/bets/place  | |
|   +-----------------------+         +--------------------------+        +---------------------+ |
|                                                                                                 |
|                                4. CONCILIAÇÃO & LIQUIDAÇÃO                                      |
|                                +--------------------------+                                     |
|                                | processar_apostas_encerr |                                     |
|                                | Súmula Oficial FT        |                                     |
|                                | Void / Push Linha Inteira|                                     |
|                                | Crédito Conta Corrente   |                                     |
|                                +--------------------------+                                     |
+-------------------------------------------------------------------------------------------------+
```

---

## 2. Catálogo de DAGs do Airflow: Papéis e Frequências

| DAG ID | Frequência | Script Chamado | Mercado | Papel Operacional |
| :--- | :--- | :--- | :--- | :--- |
| **`criar_apostas_cloudbet_dag`** | `*/30 * * * *` (30 min) | `scripts/criar_apostas_cloudbet_diario.py` | Handicap Asiático | **Robô 100% Autônomo**: Consulta o catálogo oficial da Cloudbet via proxy suíço, roda o Gatekeeper e **emite a ordem real/simulada diretamente na corretora** via API (`place_bet`) com micro-stakes de 0.10 USDT. |
| **`criar_apostas_gols_dag`** | `0 */3 * * *` (3 horas) | `scripts/criar_apostas_gols_diario.py` | Gols Asiáticos | Avalia partidas abertas para o mercado de totais asiáticos (linhas inteiras 2.0 e 3.0). Emite ordens registradas no portfólio em USDT chanceladas pelo Gatekeeper. |
| **`criar_apostas_handicap_dag`** | `0 */3 * * *` (3 horas) | `scripts/criar_apostas_handicap_diario.py` | Handicap Asiático | Avalia a grade diária pelo motor clássico de Handicap. Histórica esteira de portfólio pré-jogo. |
| **`criar_apostas_cartoes_dag`** | `0 * * * *` (1 hora) | `scripts/criar_apostas_cartoes_diario.py` | Total de Cartões Under | Monitora árbitros rigorosos ou complacentes para entradas táticas em cartões nas ligas de elite e futebol sul-americano (+EV $\ge$ 5.0%). |
| **`football_trends_live_dag`** | `*/2 * * * *` (2 min) | `scripts/cloudbet_live_cashout.py` | Ao Vivo / In-Play | Acompanha os 90 minutos de jogo com o placar ao vivo. Se houver desvio tático ou gatilho de stop-loss, dispara uma contra-ordem (**Cross-Hedge**) na Cloudbet. |
| **`processar_apostas_encerradas_dag`** | `*/5 * * * *` (5 min) | `scripts/processar_apostas_encerradas.py` | Pós-Jogo (FT) | Lê o placar final e súmulas de partidas finalizadas (`FT`), liquida as apostas (Ganha, Perdida, Anulada/Void) e concilia os créditos e débitos na tabela `conta_corrente`. |

---

## 3. Estrutura Modular dos Motores de Gatekeeper

A aprovação de qualquer palpite ou aposta passa por uma cadeia estrita de filtros matemáticos, estatísticos e de governança:

### 3.1. Motor Matemático Central
* **Arquivo**: `scripts/asian_handicap_engine.py`
* **`calculate_unified_handicap_recommendation()`**: Função mestre que consome exclusivamente cotações reais das casas de apostas oficiais (`allow_api_fetch=False`).
* **`evaluate_and_select_best_ah_candidate()`**: Núcleo preditivo que:
  1. Constrói a matriz bivariada de Poisson (`calculate_bivariate_poisson_matrix`).
  2. Calcula a probabilidade efetiva e odd justa de cada seleção.
  3. Compara a odd justa projetada contra a odd real de mercado para apurar o **Valor Esperado Positivo (+EV)**.
  4. Aplica redutores de risco acumulados para confrontos fora de casa, variância de U5J e momentos de instabilidade.

### 3.2. Módulo de Governança e Tiers de Risco
* **Arquivo**: `scripts/bet_tier.py` (`BetTier`)
* **Classes de Entrada**:
  * `BetTier.PREMIUM` (Destaque 2): Probabilidade $\ge 70\%$, +EV $\ge 8\%$, U5J e pedigree consolidados. Multiplicador de stake $3.0\times$.
  * `BetTier.QUASE_PREMIUM` (Destaque 1): Probabilidade $\ge 60\%$, +EV $\ge 4\%$. Multiplicador de stake $1.0\times$.
  * `BetTier.REGULAR` (Destaque 0): Descartada automaticamente pelo robô da Cloudbet (`scripts/criar_apostas_cloudbet_diario.py` linhas 195-199).

### 3.3. Calibração de Pedigree e Amostragem Recente
* **`scripts/leagues_config.py`**: Aplica bônus tático ponderado para clubes de elite (`is_tier_1_elite_club`), ajustando as lambdas de Poisson.
* **`scripts/football_ingest_trends.py`**: Garante a amostragem obrigatória de 5 jogos recentes (`fetch_team_last5_form`) preservando o payload estruturado `|| U5J_DATA:` exigido pela Regra de Ouro 16.

### 3.4. Sincronização Web (Regra de Ouro 11)
* **Arquivo**: `src/footballweb/app/Controllers/ApostaController.php`
* O método **`evaluateGatekeeper()`** reproduz a mesma lógica estatística do Python para garantir que apostas simuladas ou avaliadas manualmente na interface web obedeçam rigorosamente às mesmas travas do Airflow.

---

## 4. Regras Canônicas de Liquidação e Gestão Financeira

1. **Reembolso Integral (Void / Push) em Linhas Inteiras (.0)**:
   - Em mercados de Gols Asiáticos (`Menos de 2.0`, `Mais de 3.0`) ou Handicap Asiático (`0.0 DNB`, `-1.0 AH`), o empate exato entre a linha e o resultado de campo encerra a aposta como **ANULADA**, com devolução integral de 100% da stake investida para o saldo em USDT.
2. **Ciclo de Vida Transacional**:
   - `Pendente`: Jogo não iniciado ou em andamento. Débito lançado em `conta_corrente`.
   - `Ganha`: Partida encerrada (`FT`). Crédito do retorno integral (`valor_aposta * odd`).
   - `Perdida`: Partida encerrada (`FT`). Débito consolidado.
   - `ANULADA`: Partida cancelada, adiada ou com linha inteira empatada. Estorno de 100% da stake.
3. **Cross-Hedge In-Play (Contra-Aposta)**:
   - Executado quando a IA detecta desvantagem crítica de campo. Consulta o feed ao vivo (`live=true` / `TRADING_LIVE`) via `cloudbet_matcher.py` e emite uma contra-ordem na Cloudbet para travar o prejuízo da banca.
