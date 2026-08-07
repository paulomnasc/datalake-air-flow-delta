# Memória do Projeto & Recomendações de Pipeline

## Ingestão de Odds & Surebets do Oddspedia (FootballWeb)
- A raspagem e enriquecimento de odds do Oddspedia na tabela `fixtures_trends` é executada pelo script `scripts/football_ingest_trends.py` chamando `scrape_oddspedia_odds()`.
- **Recomendação de Frequência de Execução Intra-day:**
  - Caso o usuário solicite atualização em tempo real das odds e Surebets durante os dias de jogos, alterar o `schedule_interval` da DAG `football_trends_ingestion_dag` em `src/dags/football_trends_dag.py`:
    - A cada 30 minutos: `schedule_interval='*/30 * * * *'`
    - A cada 1 hora: `schedule_interval='0 * * * *'`
