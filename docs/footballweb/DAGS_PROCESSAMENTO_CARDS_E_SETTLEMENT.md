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

## ⏰ Agendamento e Economia de Cota

Ambas as DAGs estão configuradas no Airflow com o intervalo de 3 horas (`0 */3 * * *`) para equilibrar a atualização contínua do dashboard com a preservação da cota de requisições diárias da API-Sports.
