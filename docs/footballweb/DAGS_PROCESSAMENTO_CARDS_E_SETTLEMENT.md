# Fluxo de DAGs do Airflow para Processamento e Liquidação de Cards

Este documento descreve o funcionamento e a responsabilidade das DAGs do Airflow na atualização dos status de processamento dos cards no Dashboard de Futebol (`dashboard.php`) e no Dashboard de Apostas (`index.php`).

---

## 🔄 Fluxo de Transição dos Status de Processamento

Os cards exibem o estado do processamento de pós-jogo em 3 etapas principais:

```
[ Jogo Ao Vivo / Agendado (NS/1H/2H/HT) ]
                 │
                 ▼  football_trends_ingestion_dag
[ Processamento: ⏳ Pendente ] 
                 │ (Status 'FT' + Gols gravados)
                 ▼
[ Processamento: 🌗 Parcial ] 
                 │ (Cartões checados + Apostas/Palpites liquidados)
                 ▼  processar_apostas_encerradas_dag
[ Processamento: ✅ Completo ]
```

1. **`Processamento: ⏳ Pendente`**: Partida em andamento (`1H`, `2H`, `HT`, `LIVE`) ou ainda não iniciada (`NS`).
2. **`Processamento: 🌗 Parcial`**: Partida finalizada (`FT`), com o placar de gols atualizado, porém aguardando a confirmação das estatísticas detalhadas de cartões e escanteios via API.
3. **`Processamento: ✅ Completo`**: Partida finalizada (`FT`), com todas as estatísticas oficiais de cartões gravadas (`cards_api_checked_at`) e todas as apostas dos usuários e palpites da IA auditados e liquidados.

---

## 📌 DAGs Responsáveis

### 1. `processar_apostas_encerradas_dag` (Processamento Completo & Liquidação)
* **Arquivo DAG**: [`src/dags/processar_apostas_encerradas_dag.py`](file:///root/datalake-air-flow-delta/src/dags/processar_apostas_encerradas_dag.py)
* **Script Executado**: [`scripts/processar_apostas_encerradas.py`](file:///root/datalake-air-flow-delta/scripts/processar_apostas_encerradas.py)
* **Frequência de Execução**: `schedule_interval='0 */3 * * *'` (a cada 3 horas)
* **Função no Sistema**:
  * Consulta os eventos oficiais e estatísticas reais de cartões amarelos e vermelhos na API-Sports (`fetch_real_fixture_cards_api`).
  * Atualiza o campo `cards_api_checked_at` e a contagem de cartões em `fixtures_trends`.
  * Avalia e liquida as apostas cadastradas pelos usuários em `apostas` (`Ganha`, `Perdida`, `ANULADA`).
  * Atualiza a auditoria dos palpites da IA em `palpites_gerados` (`GREEN`, `RED`, `VOID`, `NO_BET`).
  * **Transiciona o card de `Processamento: 🌗 Parcial` para `Processamento: ✅ Completo`.**

---

### 2. `football_trends_ingestion_dag` (Ingestão de Placares e Tendências)
* **Arquivo DAG**: [`src/dags/football_trends_dag.py`](file:///root/datalake-air-flow-delta/src/dags/football_trends_dag.py)
* **Script Executado**: [`scripts/football_ingest_trends.py`](file:///root/datalake-air-flow-delta/scripts/football_ingest_trends.py)
* **Frequência de Execução**: `schedule_interval='0 */3 * * *'` (a cada 3 horas)
* **Função no Sistema**:
  * Atualiza os placares ao vivo e finais (`goals_home` e `goals_away`) em `fixtures_trends`.
  * Atualiza o status da partida de `1H`/`LIVE` para `FT`.
  * **Transiciona o card de `Processamento: ⏳ Pendente` para `Processamento: 🌗 Parcial`.**

---

## 🛡️ Regras de Decisão do Gatekeeper (Cards e Apostas)

Todas as recomendações de apostas e cards analíticos passam obrigatoriamente pelos motores centrais do Gatekeeper ([`scripts/asian_handicap_engine.py`](file:///root/datalake-air-flow-delta/scripts/asian_handicap_engine.py) e [`scripts/cards_engine.py`](file:///root/datalake-air-flow-delta/scripts/cards_engine.py)). O Gatekeeper opera com formato padronizado oficial e critérios matemáticos rigorosos.

### 1. Formato Padronizado de Decisão do Gatekeeper
Tanto as justificativas nos cards (`fixtures_trends`) quanto os detalhes das apostas (`apostas.resultado_detalhado`) utilizam rigorosamente o template estruturado:

```text
STATUS GK: [APROVADO | NO_BET]
SUGGESTION: [Linha Recomendada | Sem Entrada (Abstenção)]
REASON: 🛡️ [Gatekeeper {MERCADO} {STATUS} / {CATEGORIA_DO_VETO}] {Explicação analítica com métricas de U5J, Poisson e Árbitro}
```

---

### 2. Regras do Mercado de Cartões (Cards Engine)

#### A. Relação Inversa entre Eficiência U5J e Atrito Disciplinar
O sistema avalia a pontuação ponderada nos últimos 5 jogos (U5J) de cada time ($V_{T1}=+5.0$, $V=+3.0$, $E_{T1}=+2.0$, $E=+1.0$, $D_{T1}=0.0$, $D=-1.0$):
* **Crítica ($\le 0.0$ pts)**: Colapso/sequência de derrotas $\rightarrow$ propensão máxima a faltas de atrito e frustração.
* **Baixa ($0.0 < \text{pts} \le 3.0$)**: Má fase e pressão $\rightarrow$ atraso nos botes e faltas táticas frequentes para conter transições.
* **Atrito Combinado Mútuo ($\le 3.0$ pts em ambos)**: "Jogo tenso / 6 pontos" $\rightarrow$ majoração de **$+20\%$ a $+25\%$** na expectativa de cartões ($xC$).
* **Alta Eficiência ($\ge 7.0$ pts em ambos)**: Equipes fluidas e dominantes $\rightarrow$ atenuação de **$-6\%$** no $xC$.

#### B. Majoração em Mata-Mata Eliminatório (Oitavas de Final em diante)
* Identificado sistemicamente pelo campo `fixtures_trends.league_round` (*Round of 16*, *Oitavas*, *Quarter-finals*, *Quartas*, *Semi-finals*, *Final*).
* Partidas de eliminação direta recebem multiplicador de **$+18\%$** no $xC$ (`knockout_mult = 1.18x`), refletindo catimba, nervosismo e faltas táticas obrigatórias.

#### C. Travas de Veto Mandatório do Gatekeeper Under Cartões
1. **Trava de Atrito U5J e Mata-Mata**: Se o confronto for eliminatório a partir das oitavas ou ambas as equipes estiverem com U5J $\le 3.0$ pts (ou negativo), **as linhas secas de Under 3.5 e Under 4.5 são terminantemente vetadas** pelo risco extremo de estouro de cartões.
2. **Trava de Piso do Árbitro**: Veta a linha Under se a média histórica do árbitro for $\ge (\text{linha} - 0.30)$ ou $\ge 4.20$ cartões/jogo.
3. **Piso Financeiro**: Odd mínima de $1.50$ (ou $1.65$ para Under 5.5) na Betano com $+EV > 0.0\%$ e Probabilidade Poisson $\ge 60.0\%$.

---

### 3. Regras do Mercado de Handicap Asiático (AH Engine)
* **Odds 1X2 Reais da Betano**: Capturadas em pré-jogo com prioridade máxima (Bookmaker ID 32) para definição do favorito real do mercado, mantendo a Bet365/mercado como contingência secundária.
* **Proteção contra Favorito em Queda**: Veta terminantemente apostas DNB ($0.0$ AH) ou handicaps secos em favoritos com curva estagnada ou descendente de eficiência U5J.
* **Matriz de Poisson Bivariada**: Exige $+EV \ge 5.0\%$, Probabilidade Efetiva $\ge 48.0\%$ e Odd Betano dentro da margem de valor sobre a odd justa.

---

## ⏰ Agendamento e Economia de Cota

As DAGs estão configuradas no Airflow com intervalo de 3 horas (`0 */3 * * *`) e executam sob a política **Cache-First no MySQL (Regra de Ouro nº 1)**, consultando a API externa exclusivamente nas exceções autorizadas (atualização de odds dinâmicas pré-jogo da Betano, súmulas de cartões para jogos recém-terminados e enriquecimento de árbitro a menos de 48h).
