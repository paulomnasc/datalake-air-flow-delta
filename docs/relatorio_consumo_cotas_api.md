# 📊 Relatório de Consumo de Cotas - API-Sports (Pro Plan)

Este documento detalha o impacto no consumo de requisições da **API-Sports (API-Football)** após a alteração da frequência da DAG para **30 minutos**, a análise do estouro de cota do dia e as travas de proteção implementadas no código.

---

## 📈 Resumo do Plano e Limites

* **Plano Ativo**: `PRO PLAN`
* **Cota Diária Disponível**: **7.500 requisições / dia** (reseta diariamente às 00:00 UTC / 21:00 BRT)

---

## 🚨 Análise do Estouro de Cota Diária e Causa Raiz (Post-Mortem)

Apesar da estimativa inicial prever ~400 requisições/dia considerando apenas chamadas em lote (`/odds?date=...` e `/fixtures?date=...`), a cota diária de 7.500 requisições foi atingida devido a **chamadas individuais por fixture dentro de loops a cada 30 minutos**:

1. **Requisições de Estatísticas e Eventos por Partida Encerrada/Ao Vivo**:
   * O script realizava chamadas individuais para `/fixtures/statistics?fixture={id}` e `/fixtures/events?fixture={id}` em partidas que já estavam com status `FT` (Encerradas), sem verificar se as estatísticas já constavam no banco MySQL.
   * Em dias com ~50 jogos finalizados ou em andamento, isso gerava **100 requisições por execução**. Multiplicado por 48 execuções diárias = **~4.800 requisições/dia**.

2. **Fallback de Odds Individuais por Fixture (`/odds?fixture={id}`)**:
   * Quando uma partida não retornava no lote global de odds por data, o código efetuava uma requisição HTTP individual `/odds?fixture={id}` por partida. Com 30+ partidas sem odds no lote global, isso somava **~1.500 a 2.500 requisições/dia**.

3. **Total Acumulado**:
   * **~4.800 (stats/eventos) + ~2.400 (odds individuais) + ~500 (fixtures/datas) = > 7.700 requisições/dia**, ultrapassando o limite diário de 7.500 do plano PRO.

---

## 🛡️ Trava de Proteção Estrita Implementada no Código

Para eliminar permanentemente o consumo excessivo sem perder dados, aplicamos as seguintes mudanças em `scripts/football_ingest_trends.py`:

1. **Cache Local no MySQL para Partidas Encerradas (`FT`)**:
   * Partidas encerradas com estatísticas (`xg_home`, `corners_home`, etc.) já salvas no MySQL **não fazem nenhuma chamada HTTP** para `/statistics` ou `/events`.

2. **Eliminação de Chamadas de Odds Individuais em Loop**:
   * O fallback por fixture individual (`/odds?fixture=X`) foi **desativado**. A busca de odds consome estritamente 1 a 3 chamadas em lote por data (`/odds?date=YYYY-MM-DD`).
   * Partidas ausentes no lote oficial utilizam automaticamente o scraper gratuito de Oddspedia/Futbol24.

3. **Redução Projetada pós-Correção**:
   * **Consumo Total com Travas**: **~180 a 250 requisições / dia** (~3,3% da cota do Plano Pro), garantindo **96,7% de margem livre diária** de segurança.
