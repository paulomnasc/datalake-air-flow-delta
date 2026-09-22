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

### 4. Dicionário Operacional de Categorias de Abstenção do Gatekeeper (NO_BET)

Cada partida sem aposta recomendada recebe obrigatoriamente uma categorização formal (`gatekeeper_category`) gravada no banco de dados e nos cards do dashboard. Abaixo está a fundamentação prática no futebol, critérios matemáticos/táticos e diretriz operacional de cada motivo de bloqueio:

#### 🅰️ Categorias de Abstenção do Handicap Asiático (AH Engine)

---

##### 1. Mando de Campo Soberano (Divergência de Mercado / Anti-Fake Dog)
* **Conceito Prático no Futebol (A "Emboscada de Mercado")**:
  * Ocorre quando um time visitante vem em boa fase recente (ex: 3 a 4 vitórias no U5J), enquanto o mandante oscilou nos últimos jogos.
  * Modelos estatísticos puramente numéricos tendem a cair na armadilha de apoiar o visitante como um "falso azarão em alta".
  * No entanto, o mercado e as casas de apostas precificam o **mandante com cotações menores (favorito do confronto)**, reconhecendo fatores de campo que o U5J isolado não captura: força da torcida, pressão do estádio, clima, altitude, histórico de confronto direto ou adaptação tática.
* **Critérios Objetivos de Aplicação**:
  1. Aposta candidata apoia o visitante em linha curta de vitória ou empate anula (`0.0 AH` ou `-0.25 AH`).
  2. A cotação 1X2 do mandante é menor que a do visitante com margem mínima relevante (diferença $\ge 0.15$ entre as odds).
  3. O visitante não é clube de elite internacional (Tier 1) com favoritismo esmagador (odd $< 1.65$).
* **Resumo Operacional**:
  * O sistema respeita a sabedoria das bancas e não aposta contra o estádio local. Como o mandante também não possui consistência recente no U5J para justificar entrada a seu favor, o Gatekeeper decreta **Abstenção Total (`NO_BET`)**, protegendo a banca contra emboscadas.

---

##### 2. Sobrevivência do Mandante (Caldeirão da Degola)
* **Conceito Prático no Futebol (O Fator Urgência de Sobrevivência)**:
  * Confronto em que o mandante está afundado na zona de rebaixamento (Z-4) ou flertando perigosamente com a degola, recebendo um visitante que figura na parte de cima da tabela.
  * Em jogos parelhos, a urgência extrema de pontuação para evitar o rebaixamento, a mobilização da comissão técnica e a pressão das arquibancadas transformam o estádio em um ambiente inóspito, neutralizando a superioridade técnica teórica do visitante.
* **Critérios Objetivos de Aplicação**:
  1. Aposta candidata avaliada no visitante em linha curta (`\le 0.0` AH).
  2. O mandante está no Z-4 ou atende aos gatilhos de risco: 12ª posição ou pior com aproveitamento frágil ($\le 1.25$ pontos por jogo) ou abismo de classificação ($\ge 6$ posições atrás do visitante).
  3. A partida possui odds competitivas (Visitante com odd 1X2 $\ge 2.00$, sem dominância Tier 1 esmagadora).
* **Resumo Operacional**:
  * Não se aposta a favor de visitante em estádio onde o mandante joga a vida da temporada. A imprevisibilidade e a agressividade do mandante tornam o risco inaceitável.

---

##### 3. Duelo de Crises (Alta Imprevisibilidade Técnica)
* **Conceito Prático no Futebol (Confronto Desorganizado)**:
  * Partida entre duas equipes que atravessam um momento recente deplorável, com ambas em sequência prolongada de derrotas, trocas de comando ou colapso tático.
  * Jogos dessa natureza costumam ser truncados, marcados por falhas individuais bizarras, gols fortuitos ou empates sem qualidade, impossibilitando qualquer modelagem preditiva consistente.
* **Critérios Objetivos de Aplicação**:
  1. Ambas as equipes possuem pontuação de eficiência recente precária no U5J ($\le 3.0$ pontos de eficiência ponderada em cada lado).
  2. Ambas possuem histórico de $\le 1$ vitória nos últimos 5 confrontos e somatório bruto de pontos $\le 4$.
* **Resumo Operacional**:
  * Bloqueio compulsório por impossibilidade matemática de apontar superioridade tática. O sistema recusa emitir palpites em duelos de desesperados.

---

##### 4. Queda de Rendimento Recente (Favorito em Declínio de Forma)
* **Conceito Prático no Futebol (O Peso do Momento Psicológico)**:
  * O time apontado como favorito nominal pelas casas chega em curva claramente descendente nos últimos confrontos (quedas de produção, derrotas consecutivas ou desgaste físico acentuado).
  * Apostar a favor de uma equipe em queda livre apenas pelo peso da sua camisa é uma das principais causas de quebra de bancas no longo prazo.
* **Critérios Objetivos de Aplicação**:
  1. A equipe indicada como favorita pela linha de aposta possui diagnóstico de momento recente em `CURVA_DESCENDENTE` ou `CURVA_ESTAGNADA`.
  2. O adversário encontra-se em `CURVA_ASCENDENTE` ou em estabilidade superior.
  3. A linha de aposta exige vitória ou empate anula (`\le 0.0` AH).
* **Resumo Operacional**:
  * Veto mandatório. O Gatekeeper só autoriza apoio a favoritos que estejam confirmando seu poder de fogo em campo no momento presente.

---

##### 5. Odds de Mercado Indisponíveis (Conformidade com a Regra 12)
* **Conceito Prático no Futebol (Ausência de Lastro Real de Cotação)**:
  * Partidas (comuns em divisões secundárias ou fases preliminares de copas) onde as casas de apostas ainda não abriram cotações 1X2 oficiais no mercado.
* **Critérios Objetivos de Aplicação**:
  1. Retorno de cotações 1X2 ausente ou nulo na API oficial.
  2. Respeito irrestrito à **Regra de Ouro nº 12** do repositório: proibição absoluta de inventar ou deduzir odds sintéticas/artificiais.
* **Resumo Operacional**:
  * Abstenção imediata por ausência de dados comprovados de mercado. O sistema só simula ou sugere palpites respaldados por bookmakers reais.

---

##### 6. Aguardando Abertura de Mercado (Linhas de Handicap Asiático Inexistentes)
* **Conceito Prático no Futebol (Liquidez Inexistente na Linha)**:
  * A partida possui odds gerais de 1X2 disponíveis, mas as casas (notadamente a Betano / bookmakers monitoradas) ainda não liberaram as linhas específicas de Handicap Asiático (`0.0`, `-0.25`, etc.) para o jogo.
* **Critérios Objetivos de Aplicação**:
  1. A varredura de mercados específicos de handicap retorna lista vazia de opções líquidas.
  2. Aplicação do princípio de abstenção mandante da Regra 12.
* **Resumo Operacional**:
  * Preservação do card com status de abstenção até que a bookmaker abra a liquidez real de handicap pré-jogo.

---

##### 7. Amostragem Insuficiente (Conformidade com a Regra 9)
* **Conceito Prático no Futebol (Início de Temporada ou Equipes Sem Histórico)**:
  * Confrontos entre times de divisões amadoras, estreantes de copas ou seleções com calendário esparso onde não há registros de 5 partidas oficiais anteriores consolidadas no banco de dados.
* **Critérios Objetivos de Aplicação**:
  1. Base de dados registra menos de 5 partidas válidas (`count < 5`) para uma ou ambas as equipes.
  2. Respeito à **Regra de Ouro nº 9**: proibição rigorosa de adotar médias fictícias ou "valores mágicos" (ex: 5.0 ou 0.0) para contornar a ausência de dados.
* **Resumo Operacional**:
  * O sistema interrompe o processamento (`NO_BET`) e declara amostragem insuficiente, assegurando que nenhum palpite financeiro seja gerado sobre premissas inexistentes.

---

##### 8. Falta de Valor Esperado (+EV)
* **Conceito Prático no Futebol (Preço Injusto das Bancas)**:
  * Ocorre quando a probabilidade matemática real calculada pelo modelo de Poisson (baseada em força de ataque, defesa e mando) não é remunerada adequadamente pela odd ofertada pela casa de apostas.
* **Critérios Objetivos de Aplicação**:
  1. A rentabilidade projetada $+EV$ é inferior ao piso operacional mínimo estabelecido de $+5.0\%$.
  2. A odd oferecida pela casa é menor ou igual à *Odd Justa* calculada pelo modelo.
* **Resumo Operacional**:
  * Mesmo que um time tenha probabilidade de vencer, apostar por um valor inferior ao risco real corrói a banca no longo prazo. Abstenção mandatória por falta de margem de lucro matemático.

---

##### 9. Odd Abaixo do Piso Mínimo (Cotação Esmagada)
* **Conceito Prático no Futebol (Risco Alto para Retorno Insignificante)**:
  * Acontece quando a cotação disponível na casa de apostas é tão baixa (ex: 1.20 a 1.45) que qualquer imprevisto de campo (como uma expulsão precoce ou um pênalti fortuito) geraria um prejuízo gigantesco que exigiria várias vitórias consecutivas para ser recuperado.
* **Critérios Objetivos de Aplicação**:
  1. Odd da melhor linha aprovada encontra-se abaixo do piso de sustentabilidade de $1.50$ (ou limiar estrito pré-definido).
* **Resumo Operacional**:
  * O sistema recusa cotações desidratadas que violem a relação saudável de retorno sobre o capital arriscado.

---

##### 10. Equilíbrio Excessivo ou Inconsistência
* **Conceito Prático no Futebol (Duelo Neutro Sem Margem de Segurança)**:
  * Jogo entre duas equipes com forças exatamente espelhadas, retrospectos semelhantes e sem qualquer vantagem competitiva nítida para nenhum dos lados, onde nenhuma linha de handicap ofereceu proteção matematicamente favorável.
* **Critérios Objetivos de Aplicação**:
  1. Diferença de eficiência U5J e médias de gols próximas de zero.
  2. Todas as linhas disponíveis exigem exposição excessiva sem retorno compensatório.
* **Resumo Operacional**:
  * Abstenção prudente por alta aleatoriedade do resultado.

---

#### 🅱️ Categorias de Abstenção do Mercado de Cartões (Cards Engine - Under)

---

##### 1. Sem Árbitro Confirmado (Sem Juiz = Sem Aposta)
* **Conceito Prático no Futebol**:
  * O rigor disciplinar do árbitro escalado é responsável por mais de 45% da variância do total de cartões em uma partida. Operar no mercado de cartões sem saber quem apitará o jogo é equivalente a apostar no escuro.
* **Critérios Objetivos de Aplicação**:
  1. Campo `referee_name` vazio, nulo ou identificado como "Não Informado", "Desconhecido" ou "TBD".
* **Resumo Operacional**:
  * Trava de ouro inegociável: nenhuma aposta de cartões é criada ou confirmada sem a escala oficial do árbitro persistida no banco de dados.

---

##### 2. Linha de Cartões Não Autorizada
* **Conceito Prático no Futebol (Proteção Contra Estouro de Cartões)**:
  * Análises históricas e de sinistralidade comprovaram que linhas curtas de cartões (`Under 3.5`, `Under 4.5` e `Under 5.5`) possuem altíssima volatilidade em jogos competitivos.
* **Critérios Objetivos de Aplicação**:
  1. Qualquer linha de aposta menor que 6.49 (como Under 5.5, 4.5 ou 3.5).
  2. O portfólio opera com foco estrito em linhas elásticas e seguras (`Under 6.5`, `Under 7.5` e `Under 8.5`).
* **Resumo Operacional**:
  * Veto preventivo imediato de qualquer linha curta para blindar a taxa de acerto contra partidas tensas.

---

##### 3. Liga com Alta Taxa de Reds (Sinistralidade Histórica)
* **Conceito Prático no Futebol**:
  * Campeonatos com histórico cultural de violência excessiva, arbitragens descontroladas ou rivalidades inflamadas que geram índice desproporcional de cartões vermelhos diretos (superando 10% das partidas).
* **Critérios Objetivos de Aplicação**:
  1. Partida pertencente a uma das ligas com taxa histórica de sinistralidade comprovadamente superior a 10% no modelo (ex: ligas sul-americanas de atrito extremo ou copas com descontrole disciplinar mapeadas em `excludedCardsLeagueIds`).
* **Resumo Operacional**:
  * Abstenção preventiva para proteger o portfólio contra o risco sistêmico de cartões vermelhos.

---

##### 4. Caldeirão da Degola / Sobrevivência (Tensão Disciplinar Extrema)
* **Conceito Prático no Futebol**:
  * Quando um mandante joga desesperado contra o rebaixamento, o jogo invariavelmente descamba para catimba, paralisações, reclamações agressivas e faltas violentas de contenção, tornando o cenário propício para uma chuva de cartões.
* **Critérios Objetivos de Aplicação**:
  1. Mandante enquadrado em situação de perigo de rebaixamento ou abismo de classificação em jogo equilibrado.
* **Resumo Operacional**:
  * Bloqueio imediato da aposta em Under Cartões devido à probabilidade de escalada disciplinar em campo.

---

##### 5. Expectativa Excessiva de Cartões (xC Incompatível)
* **Conceito Prático no Futebol**:
  * A soma da média de cartões do árbitro com a média disciplinar das duas equipes projeta uma expectativa de cartões ($xC$) que colide perigosamente com o teto da linha oferecida.
* **Critérios Objetivos de Aplicação**:
  1. Média do árbitro superior a 4.20 cartões por partida ou próxima da linha menos 0.30 cartões.
  2. Expectativa combinada ($xC$) excedendo os limites de tolerância matemática do modelo de Poisson.
* **Resumo Operacional**:
  * O sistema recusa o Under quando os números de campo apontam tendência de partida quente e com muitas penalizações.

---

##### 6. Probabilidade Insuficiente ou Falta de +EV
* **Conceito Prático no Futebol**:
  * A probabilidade de o jogo terminar com menos cartões que a linha estipulada não atinge a zona de conforto estatístico (mínimo de 60.0% na distribuição de Poisson) ou a casa de apostas oferece uma cotação esmagada que não paga o risco.
* **Critérios Objetivos de Aplicação**:
  1. Probabilidade calculada $< 60.0\%$.
  2. Retorno esperado $+EV \le 0.0\%$.
* **Resumo Operacional**:
  * Abstenção mandatória por falta de sustentabilidade matemática da operação.

---

## ⏰ Agendamento e Economia de Cota

As DAGs estão configuradas no Airflow com intervalo de 3 horas (`0 */3 * * *`) e executam sob a política **Cache-First no MySQL (Regra de Ouro nº 1)**, consultando a API externa exclusivamente nas exceções autorizadas (atualização de odds dinâmicas pré-jogo da Betano, súmulas de cartões para jogos recém-terminados e enriquecimento de árbitro a menos de 48h).
