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
  - O cálculo e triangulação de Surebets está permanentemente desativado: as flags em `fixtures_trends` devem sempre gravar `is_surebet = 0` e `surebet_profit_pct = 0.0`.
  - A *The Odds API* é autorizada estritamente como contingência para enriquecimento de odds 1X2 quando a cota diária da API-Sports estiver esgotada (Regra 3, item 5), nunca para arbitragem/surebets.

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
5. **Fallback de Odds via The Odds API APENAS com Cota Diária da API-Sports Esgotada:**
   - No script `scripts/football_ingest_trends.py`, a **The Odds API** é acionada como contingência estritamente se a cota diária de requisições da API-Sports for excedida e ainda houver partidas sem odds no banco de dados. Se a API-Sports tiver cota disponível, a chamada para The Odds API deve ser dispensada. As flags de arbitragem continuam zeradas (`is_surebet = 0`). O Futbol24 é mantido exclusivamente para enriquecimento de textos jornalísticos e prévias editoriais (`futbol24_tip`).

---

## 4. Ciclo de Vida da Liquidação de Apostas (Cards no Dashboard)
Qualquer alteração de código deve respeitar a esteira de 3 estados de processamento:
1. `Processamento: ⏳ Pendente`: Partida em andamento ou não iniciada (`NS`, `LIVE`).
2. `Processamento: 🌗 Parcial`: Partida finalizada (`FT`), com gols registrados por `football_trends_ingestion_dag`.
3. `Processamento: ✅ Completo`: Partida finalizada (`FT`), cartões consolidados em `match_statistics_cache` e apostas/palpites liquidados por `processar_apostas_encerradas_dag`.

---

## 5. Proibição Estrita de Modificação de Código Sem Consentimento Prévio
- O assistente/agente **NUNCA DEVE** criar, editar, refatorar ou deletar qualquer arquivo de código-fonte, scripts (`.py`, `.php`, `.sh`, etc.), DAGs ou arquivos de configuração sem autorização prévia e explícita do usuário.
- **Fluxo Obrigatório**: Antes de qualquer modificação, o assistente deve:
  1. Analisar o problema ou requisito;
  2. Explicar a abordagem técnica e detalhar exatamente quais arquivos e trechos serão alterados (ou apresentar o plano/diff proposto);
  3. Solicitar e aguardar a confirmação/consentimento explícito do usuário antes de invocar ferramentas de escrita ou edição de arquivos de código.

---

## 6. Soluções Estruturais e Sistêmicas Globais (Proibição de Soluções Pontuais)
- Toda e qualquer correção de bugs, modelos preditivos, esteiras de ingestão, consolidação de estatísticas ou regras de negócio **DEVE SER ESTRUTURAL E SISTÊMICA**, válida e aplicada de forma homogênea para **todos os jogos, times e ligas** monitoradas pelo sistema.
- **Proibição Absoluta de Patches Pontuais**:
  - É expressamente proibido implementar soluções pontuais, gambiarras com *hardcoding* de IDs de times específicos, ou regras ad-hoc que resolvam apenas a partida mencionada pelo usuário.
  - Toda partida ou exemplo apontado pelo usuário deve ser tratado como um **caso de teste representativo** de uma falha de arquitetura mais ampla; a solução deve obrigatoriamente consertar a causa raiz em nível de pipeline para que todos os jogos presentes e futuros sejam processados corretamente.

---

## 7. Distinção Obrigatória: Falha Algorítmica vs. Zebra Clássica (Variância Esportiva e Prevenção de Overfitting)
- **Proibição de Alterar Código por Causa de Zebras Esportivas:**
  - O futebol possui variância inerente nos 90 minutos. Em modelos de Valor Esperado Positivo (+EV), apostas com 80% a 90% de cobertura projetada **perderão entre 10% e 20% das vezes** devido a imponderáveis de campo (gols fortuitos, bolas paradas, falhas individuais pontuais, eficácia atípica de finalizações do azarão).
  - Tentar criar filtros ad-hoc ou endurecer travas no Gatekeeper para "evitar" uma perda pontual onde todos os fundamentos pré-jogo eram sólidos é um erro clássico e destrutivo de **overfitting** (ajuste excessivo). Isso destrói o volume de apostas e a lucratividade matemática da esteira no longo prazo.

- **Estudo de Caso Emblemático de Zebra (NÃO ALTERAR CÓDIGO):**
  - **Partida**: *Boyacá Chicó 2 x 1 Independiente Medellín* (12/09/2026 - Primera A Colombiana).
  - **Entrada Selecionada**: `Independiente Medellín -0.25 AH` @ 1.52 (EV +39.6%).
  - **Fundamentos Pré-Jogo Perfeitos**:
    - **Odds 1X2 Globais**: Chicó @ 4.35 vs Medellín @ 1.93 (mercado precificava probabilidade do azarão abaixo de 22%).
    - **Forma Recente (U5J)**: Medellín com 4V-0E-1D (11.0 pts de eficiência), vindo de vitórias contundentes fora de casa, contra 1V-2E-2D (4.0 pts de eficiência) do Chicó.
    - **Gestão de Risco**: O Gatekeeper foi prudente ao selecionar a linha de cobertura `-0.25 AH` (meio-reembolso no empate) em vez do ML seco (-0.5).
  - **Diretriz Operacional**: A aposta foi matematicamente e conceitualmente impecável no pré-jogo. A vitória do Boyacá Chicó foi estritamente uma **zebra clássica (azarão venceu)**. É expressamente proibido criar regras restritivas ou alterar os pesos do Gatekeeper para tentar filtrar esse tipo de partida.


