# 🛡️ Regras de Ouro: Ingestão, Cache e Processamento (FootballWeb)

Este documento estabelece os padrões arquiteturais definitivos para o ecossistema **FootballWeb** no repositório `datalake-air-flow-delta`. Todas as DAGs do Airflow, scripts Python e futuros desenvolvimentos devem obrigatoriamente aderir a estes princípios.

---

## 🏛️ 1. Princípio Arquitetural: Cache-First Mandatório

Nenhuma chamada externa para a API-Sports (`v3.football.api-sports.io`) ou rotinas de Web Scraping deve ser executada se os dados já existirem no banco de dados local MySQL (`footballweb`).

### Mapeamento de Tabelas de Cache

| Entidade de Dados | Tabela MySQL | Política de Cache | Ação se Existir no Banco |
| :--- | :--- | :--- | :--- |
| **Metadados de Partidas** | `fixtures_trends` | Permanente para datas futuras e passadas finalizadas (`FT`) | **Reutilizar dados locais.** Pular chamada a `/fixtures?date=`. |
| **Súmulas e Cartões Pós-Jogo** | `match_statistics_cache` | Permanente após consolidação da partida | **Retornar imediatamente do cache.** Pular chamadas a `/fixtures/statistics` e `/fixtures/events`. |
| **Médias Móveis dos Times** | `team_moving_averages` | TTL de 12 horas | **Reutilizar.** Não consultar `/teams/statistics` na API. |
| **Histórico Recente (Últimos 5)** | `team_last5_cache` / `fixtures_trends` | Prioridade em `fixtures_trends` (`FT`) e TTL de 24 horas em `team_last5_cache` | **Reutilizar.** Não consultar `/teams/statistics` ou scrapers. |
| **Estatísticas de Árbitros** | `referee_stats` | Permanente / Incremental | **Reutilizar.** Usar médias salvas no banco. |
| **Classificação das Ligas** | `fixtures_trends` (`home_rank`, `away_rank`) | Rodada / 24 horas | **Reutilizar.** Pular chamada a `/standings`. |

---

## 🚫 2. Descontinuação de Surebets e Arbitragem

A busca por arbitragem esportiva (Surebets) foi **descontinuada permanentemente** para economizar cota e foco operacional:
1. **DAG de Arbitragem**: [`src/dags/sports_arbitrage_dag.py`](file:///root/datalake-air-flow-delta/src/dags/sports_arbitrage_dag.py) deve permanecer desativada (`schedule_interval=None`).
2. **The Odds API**: Nenhuma chamada para `api.the-odds-api.com` deve ser executada.
3. **Web Scraping de Odds**: O scraping de Oddspedia e Futbol24 para triangulação de Surebets está desativado (`should_run_scraping = False`).
4. **Campos no Banco**: `fixtures_trends.is_surebet` e `fixtures_trends.surebet_profit_pct` devem ser sempre gravados como `0` e `0.0`.

---

## ⚡ 3. Exceções Dinâmicas Autorizadas

Apenas 4 cenários específicos possuem autorização para efetuar chamadas a provedores externos:

### A. Odds Betano em Pré-Jogo (Bookmaker ID 32)
* **Scripts**: [`scripts/criar_apostas_cartoes_diario.py`](file:///root/datalake-air-flow-delta/scripts/criar_apostas_cartoes_diario.py) e [`scripts/criar_apostas_handicap_diario.py`](file:///root/datalake-air-flow-delta/scripts/criar_apostas_handicap_diario.py).
* **Regra**: As cotações da Betano variam dinamicamente até o início do jogo. Consultas pré-jogo na API-Sports (`bookmaker=32`) são permitidas para validação de +EV e confirmação de linhas disponíveis, utilizando cache em memória por partida para evitar requisições repetidas.

### B. Status e Placar de Partidas em Andamento
* **Scripts**: [`scripts/football_ingest_trends.py`](file:///root/datalake-air-flow-delta/scripts/football_ingest_trends.py).
* **Regra**: Partidas com status aberto (`NS`, `1H`, `2H`, `HT`, `LIVE`) devem ser atualizadas para capturar o encerramento (`FT`) e o placar final oficial (`goals_home`, `goals_away`), viabilizando a liquidação das apostas.

### C. Cartões de Jogos Recém-Finalizados
* **Scripts**: [`scripts/processar_apostas_encerradas.py`](file:///root/datalake-air-flow-delta/scripts/processar_apostas_encerradas.py) e `sync_pending_past_fixtures`.
* **Regra**: Imediatamente após o encerramento (`FT`), a API pode demorar alguns minutos para consolidar os cartões. A consulta à API é permitida enquanto os cartões forem nulos. **Uma vez obtidos e gravados em `match_statistics_cache`, tornam-se imutáveis.**

### D. Enriquecimento de Árbitro a menos de 48h
* **Scripts**: [`scripts/football_ingest_trends.py`](file:///root/datalake-air-flow-delta/scripts/football_ingest_trends.py).
* **Regra**: Se a partida for ocorrer em menos de 48h e `referee_name` ainda for nulo ou não informado, permite-se uma consulta de enriquecimento.

---

## 🔄 4. Esteira de Processamento de Cards e Apostas

Qualquer intervenção de código deve preservar o ciclo de transição de status dos cards no Dashboard:

```
[ Jogo Ao Vivo / Agendado (NS/1H/2H/HT) ]
                 │
                 ▼  football_trends_ingestion_dag
[ Processamento: ⏳ Pendente ] 
                 │ (Status 'FT' + Gols gravados + score_processed_at)
                 ▼
[ Processamento: 🌗 Parcial ] 
                 │ (Cartões checados + Apostas/Palpites liquidados)
                 ▼  processar_apostas_encerradas_dag
[ Processamento: ✅ Completo ]
```

---

## 🤖 5. Aplicação Automática pelo Antigravity

As regras deste documento estão espelhadas no arquivo de configuração do Antigravity:
* [`.agents/AGENTS.md`](file:///root/datalake-air-flow-delta/.agents/AGENTS.md)

Este arquivo é lido compulsoriamente no início de **toda e qualquer sessão do agente**, possuindo precedência absoluta sobre qualquer instrução padrão.

---

## 📚 Documentação Complementar
* [Fluxo de Obtenção de Dados (API-Football, The Odds API e Futbol24)](file:///root/datalake-air-flow-delta/docs/footballweb/FLUXO_OBTENCAO_DADOS_INGESTAO.md)
