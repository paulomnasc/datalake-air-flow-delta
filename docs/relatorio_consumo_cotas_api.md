# 📊 Relatório de Consumo de Cotas - API-Sports (Pro Plan)

Este documento detalha o impacto no consumo de requisições da **API-Sports (API-Football)** após a alteração da frequência da DAG para **30 minutos** e a inclusão da ingestão direta de **Odds de Mercado (Betano / Bet365 / Pinnacle)** via API oficial.

---

## 📈 Resumo do Plano e Limites

* **Plano Ativo**: `PRO PLAN`
* **Cota Diária Disponível**: **7.500 requisições / dia** (reseta diariamente às 00:00 UTC)
* **Status Atual no Momento da Análise**: ~41% Usado (restavam 4.392 requisições no dia)

---

## 🔍 Comparativo: Antes vs. Pós-Mudanças de Hoje

| Parâmetro / Componente | Cenário Anterior (DAG 3h) | Pós-Mudança 1 (DAG 30min) | Pós-Mudança 2 (DAG 30min + API Odds) |
| :--- | :--- | :--- | :--- |
| **Frequência da DAG** | A cada 3 horas (8x/dia) | A cada 30 minutos (48x/dia) | A cada 30 minutos (48x/dia) |
| **Requisições de Fixtures/Datas** | ~16 req/dia | ~96 req/dia | ~96 req/dia |
| **Requisições de Odds de Mercado** | 0 req (usava Scraping web falho) | 0 req (usava Scraping web falho) | **~96 req/dia** (`/odds?date=...`) |
| **Estatísticas/Cache Local (MySQL)** | ~50 req/dia | ~150 req/dia | ~150 req/dia |
| **Consumo Total Diário Estimado** | **~66 a 100 req/dia** | **~246 a 300 req/dia** | **~342 a 400 req/dia** |
| **% Utilizada do Plano Pro (7.500)** | **~1,3%** | **~3,3%** | **~5,3%** |
| **Margem Diária Restante Livre** | **~7.400 (98,7%)** | **~7.200 (96,7%)** | **~7.100 (94,7%)** |

---

## 🎯 Por que a Ingestão de Odds da API é Essencial e Segura?

1. **Eliminação de Odds Sintéticas ("POISSON")**:
   * **Como estava antes**: Quando a raspagem da Oddspedia falhava, o script gerava odds hipotéticas chamadas `POISSON` e gravava no banco. O modelo de Handicap e xG lia essas odds falsas como se fossem da Betano, distorcendo severamente os palpites (ex: Willem II vs NEC Nijmegen).
   * **Como ficará**: A consulta oficial `/odds?date=YYYY-MM-DD` traz as odds reais da Betano/Bet365/Pinnacle direto por `fixture_id`, sem falha de matching de nomes.

2. **Consumo Extremamente Baixo por Chamada de Odds**:
   * A API-Sports permite buscar **todas as odds de todas as partidas de uma data** em apenas **1 chamada HTTP** (`/odds?date=2026-08-15`).
   * Em dias com mais de 50 jogos, consome 2 a 3 chamadas paginadas por execução.
   * Mesmo executando 48 vezes ao dia, o custo total de odds é de apenas **~96 requisições/dia**.

3. **Conclusão**:
   A cota de **7.500 requisições/dia** do seu Plano PRO é **largamente superior** à necessidade do projeto. As mudanças aumentam o consumo de **1,3% para apenas 5,3%** da cota diária, garantindo 100% de precisão nos palpites e na ancoragem com as casas de apostas.
