# Backlog de Mudanças: Otimização de Cotas da API-Sports e Agendamento de DAGs

> **Data de Criação**: 2026-10-17  
> **Objetivo**: Mapeamento completo dos gargalos de cota diária (7.500 req/dia), análise profunda de riscos e impactos colaterais, alternativas graduais e perguntas estratégicas para alinhamento antes de qualquer alteração de código.

---

## 1. O Problema e os Dados Reais de Consumo

### O Cenário Atual
* **Cota Total**: 7.500 requisições/dia na API-Sports.
* **O Ponto de Falha**: Por volta das **17h40 às 18h00 (Horário de Brasília)**, a cota atinge 100% de uso (7.500 requisições). O consumo despenca bruscamente para zero devido ao bloqueio da API.
* **O Efeito Colateral Crítico**: O sistema fica "cego" exatamente no horário nobre das partidas do futebol brasileiro e sul-americano (Série A, Copa do Brasil, Libertadores, Sul-Americana, Liga Argentina), impedindo a atualização de placares, monitoramento in-play e liquidação noturna.

### O Diagnóstico da Madrugada (00:00 às 08:00 BRT)
A análise dos logs por hora revelou que **mais de 3.000 requisições (~40% da cota)** são consumidas enquanto praticamente não há partidas das principais ligas em andamento:
* **00h00 às 02h00**: ~250 a 380 req/hora.
* **03h00 (Pico Máximo)**: **~820 requisições em uma única execução** da `football_teams_performance_dag`.
* **04h00 às 08h00**: ~270 a 490 req/hora.

---

## 2. Itens do Backlog para Discussão e Decisão

---

### Item 1: Otimização da `football_teams_performance_dag` (O Pico das 03:00)

* **O que faz hoje**:
  * Executa diariamente às **03:00 da manhã** (`0 3 * * *`).
  * O script `scripts/football_ingest_teams_performance.py` busca todos os times que jogam nos **próximos 7 dias** (`seven_days_later`).
  * Para cada time com `updated_at > 12 horas`, tenta até 3 temporadas na API (`/teams/statistics`), busca `/fixtures?team={id}&last=8` e faz requisições individuais de `/fixtures/statistics?fixture={id}` para cada jogo recente sem cache local.
  * Multiplicado por dezenas de times, dispara **800+ requisições em minutos**.

* **Mudança Proposta Originalmente**:
  1. Alterar o agendamento da DAG para as **08:00 da manhã** (`0 8 * * *`).
  2. Reduzir a janela de busca de times de 7 dias para **48 horas** (`INTERVAL 2 DAY`).
  3. Aumentar a tolerância de cache válido de 12h para **24 horas** para times com amostragem consolidada (`matches_count >= 5`).

* **Análise de Riscos e Impactos Colaterais**:
  1. **Impacto na Visualização Antecipada do Dashboard**:
     * *Risco*: Se o script só calcular médias de times com jogos nas próximas 48 horas, partidas agendadas para daqui a 3 a 7 dias não terão as colunas de médias preenchidas no dashboard do FootballWeb até entrarem na janela de 48h.
     * *Ponto para debater*: Os operadores do sistema olham os cards de jogos com 3 a 7 dias de antecedência precisando das médias já calculadas, ou a operação foca estritamente nos jogos de hoje e de amanhã?
  2. **Horário de Execução (08:00 vs Jogos Matinais)**:
     * *Risco*: Partidas da Ásia, Austrália ou ligas europeias que começam às 08:30 ou 09:00 poderiam competir com o processamento matinal das 08:00 se a esteira demorar.
  3. **E se o time tiver jogo 2 vezes na mesma semana?**:
     * Com cache de 24 horas, o time tem suas médias preservadas perfeitamente, sendo recalculado no dia da partida seguinte.

* **Alternativas Graduais**:
  * **Opção A (Conservadora)**: Manter a busca em 7 dias, mas **adicionar uma trava estrita de banco**: se o time já possui médias gravadas em `team_moving_averages` com 5+ jogos e **não jogou nenhuma nova partida desde a última atualização**, **NUNCA chamar a API-Sports**. (Economia massiva sem alterar o horizonte de 7 dias).
  * **Opção B (Intermediária)**: Reduzir a janela de busca para **4 dias** em vez de 2 dias.
  * **Opção C (Horário)**: Rodar às **07:00** em vez de 08:00, dando 1h30 de folga antes dos primeiros jogos europeus.

---

### Item 2: Ingestão Principal de Fixtures e Odds (`football_trends_dag`)

* **O que faz hoje**:
  * Executa a cada 3 horas, ininterruptamente, 24 horas por dia (`0 */3 * * *` = 00h, 03h, 06h, 09h, 12h, 15h, 18h, 21h).
  * A cada rodada, varre a API-Sports em busca de fixtures, status, árbitros e odds pré-jogo.

* **Mudança Proposta Originalmente**:
  * Alterar para `0 8,11,14,17,20,23 * * *` (pausando nas rodadas de 00h, 03h e 06h).

* **Análise de Riscos e Impactos Colaterais**:
  1. **Partidas Noturnas das Américas (MLS, Liga MX, Copa Sul-Americana)**:
     * Partidas na Costa Oeste dos EUA ou no México frequentemente terminam entre **01:30 e 03:00 (horário de Brasília)**.
     * Se pausarmos às 23:00 e só retomarmos às 08:00, o status dessas partidas permanecerá como `LIVE` ou `2H` durante toda a madrugada, e a transição para `FT` só ocorrerá às 08:00 da manhã.
  2. **Ligas da Ásia e Oceania (J-League, K-League, A-League)**:
     * Se o sistema monitorar ligas dessas regiões, os jogos ocorrem exatamente durante a madrugada brasileira.
     * *Ponto para debater*: Essas ligas asiáticas/oceânicas são operadas pela banca ou o foco é estritamente Brasil, Américas e Europa?

* **Alternativas Graduais**:
  * **Opção A (Pausa Parcial)**: Em vez de silêncio total de 9 horas, manter apenas **uma execução intermediária na madrugada às 02:00** (`0 2,8,11,14,17,20,23 * * *`). Isso fecha todos os jogos das Américas e descansa entre 02:30 e 08:00.
  * **Opção B (Modo Leve Noturno)**: Execução na madrugada que **apenas atualiza o status de partidas abertas para `FT`**, sem varrer odds nem fixtures de dias futuros.

---

### Item 3: DAGs de Criação Horária de Apostas (`criar_apostas_cartoes_dag` e `criar_apostas_handicap_dag`)

* **O que fazem hoje**:
  * Executam de hora em hora, 24 vezes ao dia (`0 * * * *`).
  * Verificam jogos em aberto, calculam projeções, consultam cotações pré-jogo e aprovam apostas no Gatekeeper.

* **Mudança Proposta Originalmente**:
  * Restringir a execução para `0 8-23 * * *` (de hora em hora entre 08:00 e 23:00 BRT).

* **Análise de Riscos e Impactos Colaterais**:
  1. **Abertura de Linhas na Betano na Madrugada**:
     * As casas de apostas (Betano) costumam ajustar ou abrir certas linhas de handicap e cartões na madrugada que antecede as rodadas europeias de sábado e domingo.
     * Se a DAG não rodar de madrugada, essas apostas serão avaliadas pontualmente às **08:00 da manhã**.
     * *Risco de Mercado*: Às 08:00, a odd ainda estará disponível ou o mercado pode ter derretido o Sweet Spot (ex: linha ter caído de 1.85 para 1.70)?
  2. **E-mails de Notificação na Madrugada**:
     * Hoje, se uma aposta for gerada às 04:00 da manhã, o sistema dispara e-mail de alerta nesse horário. Pausar a criação na madrugada concentra os alertas nos horários comerciais/ativos do operador.

* **Alternativas Graduais**:
  * **Opção A**: Rodar na faixa das **07:00 às 23:00** (`0 7-23 * * *`), permitindo pegar as linhas logo no início da manhã antes dos primeiros jogos.
  * **Opção B**: Manter rodando de hora em hora, mas **garantir que só faça chamadas na API se houver partidas iniciando nas próximas 6 horas**. Se todos os jogos em aberto forem para dali a mais de 12 horas, abortar sem gastar requisições.

---

### Item 4: Liquidação de Apostas (`processar_apostas_encerradas_dag`)

* **O que faz hoje**:
  * Executa em `0 */3 * * *` (00h, 03h, 06h, 09h, 12h, 15h, 18h, 21h).
  * Liquida apostas de jogos encerrados (`FT`) buscando cartões e estatísticas.

* **Mudança Proposta Originalmente**:
  * Alterar para `15 1,8,11,14,17,20,23 * * *` (executando às **01:15 da manhã** e depois a cada 3h das 08:15 às 23:15).

* **Análise de Riscos e Impactos Colaterais**:
  1. **Precisão da Rodada Noturna Brasileira**:
     * Jogos da Série A que começam às 21h30 terminam por volta das 23h30. A súmula e estatísticas de cartões costumam levar de 30 a 60 minutos para constar na API.
     * Rodar à **01:15** é um timing excelente para pegar esses jogos já consolidados e fechar o saldo do dia.
  2. **Partidas que terminam após as 01:15**:
     * Jogos de Libertadores ou partidas no México que terminem às 01h30 só seriam liquidadas às 08:15.
     * *Impacto*: As apostas dessas partidas amanhecem em estado `Pendente` ou `Parcial` e só mudam para `Completo (GREEN/RED)` às 08:15. Nenhum dado é perdido, apenas adiado o fechamento visual.

---

## 3. Matriz Comparativa de Estratégias

| Abordagem | Impacto na Cota | Risco Operacional | Complexidade | Recomendação |
|---|---|---|---|---|
| **Estratégia 1: Ajuste Radical em Todas as DAGs** | Economia máxima (~3.500 req/dia) | Médio (mudança simultânea em 6 componentes) | Baixa (alteração de cron) | Requer validação das ligas ativas da madrugada |
| **Estratégia 2: Cirúrgica no Maior Gargalo (Script de Performance)** | Economia alta (~1.500 a 2.000 req/dia) | Quase nulo (não mexe nos horários das DAGs de apostas) | Média (otimização de cache SQL) | **Altamente recomendada como Passo 1** |
| **Estratégia 3: Janela Híbrida com Fechamento Noturno às 02h** | Economia muito boa (~2.500 req/dia) | Muito baixo (cobre 100% dos jogos noturnos das Américas) | Baixa | **Excelente equilíbrio para as DAGs** |

---

## 4. Roteiro Sugerido para a Discussão de Amanhã

1. **Definição de Ligas Relevantes**:
   * Confirmar se a operação possui interesse em ligas da madrugada (Ásia/Oceania) ou se o foco financeiro da banca é estritamente Américas e Europa.
2. **Horizonte de Antecedência**:
   * Definir se o dashboard precisa de médias calculadas para jogos com mais de 3 dias de antecedência, ou se D+0 e D+1 são suficientes para a tomada de decisão.
3. **Plano de Implementação Gradual (Passo a Passo)**:
   * **Fase 1**: Aplicar apenas a blindagem de cache inteligente no `football_ingest_teams_performance.py` (evitando requisições repetidas na API para times que não jogaram desde o último cálculo).
   * **Fase 2**: Ajustar o cron apenas da `football_teams_performance_dag` (das 03h para as 07h30 ou 08h00).
   * **Fase 3**: Monitorar o gráfico de consumo por 24 horas para verificar a folga obtida na cota.
   * **Fase 4**: Ajustar as demais DAGs de acordo com a necessidade residual.
