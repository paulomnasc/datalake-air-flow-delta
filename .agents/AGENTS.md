# Regras de Ouro do Pipeline FootballWeb (MANDATÓRIAS E INEGOCIÁVEIS)

Este repositório possui regras estritas de arquitetura e consumo de API para os pipelines de futebol (FootballWeb). Todo assistente AI ou desenvolvedor deve seguir rigorosamente as regras abaixo sem exceção.

---

## 1. Prioridade Absoluta: Cache-First no Banco de Dados (MySQL)
Antes de realizar qualquer requisição HTTP externa para a API-Sports (`v3.football.api-sports.io`) ou executar Web Scraping, o sistema **DEVE SEMPRE** consultar o cache local no MySQL:
- **`fixtures_trends`**: Descoberta de partidas, metadados de times, ligas, árbitros e odds 1X2 já gravadas.
- **`match_statistics_cache`**: Estatísticas consolidadas de cartões amarelos/vermelhos e escanteios de partidas finalizadas (`FT`).
- **`team_moving_averages`**: Médias móveis históricas de gols, cartões e escanteios dos times (válido por 12 horas).
- **`team_last5_cache`**: Histórico recente dos últimos 5 confrontos (válido por 24 horas).
- **`referee_stats`**: Estatísticas e rigor disciplinar de árbitros cadastrados.

**Proibição:** É terminantemente proibido sobrescrever ou reconsultar via API registros que já existam válidos e consolidados no banco de dados.

---

## 2. Surebets e Arbitragem Esportiva DESATIVADAS PERMANENTEMENTE
- **Nunca reativar a busca de Surebets / Arbitragem:**
  - A DAG `sports_arbitrage_dag` deve permanecer desativada (`schedule_interval=None`).
  - É proibido reativar chamadas para *The Odds API*.
  - No script `scripts/football_ingest_trends.py`, o scraping de Oddspedia e Futbol24 para triangulação de Surebets está desativado (`should_run_scraping = False`). As flags devem sempre gravar `is_surebet = 0` e `surebet_profit_pct = 0.0`.

---

## 3. Exceções Dinâmicas Autorizadas (Onde a API externa PODE e DEVE ser consultada)
Apenas os seguintes cenários têm autorização para realizar chamadas externas:

1. **Odds Pré-Jogo da Betano (Bookmaker ID 32):**
   - As cotações da Betano para **Cartões Under** (`scripts/criar_apostas_cartoes_diario.py`) e **Handicap Asiático** (`scripts/criar_apostas_handicap_diario.py`) oscilam dinamicamente no pré-jogo. A consulta periódica na API-Sports é permitida para cálculo de EV e liquidez, utilizando cache em memória por `fixture_id` durante a execução.
2. **Status e Placar de Partidas em Andamento (`1H`, `2H`, `HT`, `LIVE` -> `FT`):**
   - O banco de dados só é cache definitivo para partidas com status terminal (`FT`, `AET`, `PEN`, `CANC`, `PST`). Partidas abertas devem consultar a API para atualizar placares e transicionar para `FT`, viabilizando a liquidação das apostas.
3. **Cartões de Partidas Recém-Finalizadas (`FT`):**
   - Partidas finalizadas onde os cartões ainda não constam no banco (`cards_api_checked_at IS NULL` e `match_statistics_cache` vazio) podem consultar a API para obter a súmula oficial. Uma vez gravadas no banco, tornam-se imutáveis e nunca mais consultam a API.
4. **Enriquecimento de Árbitro a menos de 48h:**
   - Se uma partida ocorre nas próximas 48h e o campo `referee_name` for nulo ou "Árbitro Não Informado", a API pode ser consultada uma única vez para enriquecimento.

---

## 4. Ciclo de Vida da Liquidação de Apostas (Cards no Dashboard)
Qualquer alteração de código deve respeitar a esteira de 3 estados de processamento:
1. `Processamento: ⏳ Pendente`: Partida em andamento ou não iniciada (`NS`, `LIVE`).
2. `Processamento: 🌗 Parcial`: Partida finalizada (`FT`), com gols registrados por `football_trends_ingestion_dag`.
3. `Processamento: ✅ Completo`: Partida finalizada (`FT`), cartões consolidados em `match_statistics_cache` e apostas/palpites liquidados por `processar_apostas_encerradas_dag`.
