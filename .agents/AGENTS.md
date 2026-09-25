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

---

## 8. Inicialização Prévia Obrigatória de Variáveis Numéricas Locais
- Toda e qualquer variável numérica local dentro de uma função ou método (especialmente contadores, acumuladores, somatórios ou ponderadores como `tier1_cnt`, `total_points`, etc.) **DEVE OBRIGATORIAMENTE ser declarada e inicializada explicitamente (ex: `0` ou `0.0`) antes do início de qualquer loop (`for`, `while`) ou condicional**.
- É expressamente proibido declarar ou inicializar variáveis contadoras apenas dentro do corpo de laços ou dentro de ramificações condicionais, evitando falhas de escopo em tempo de execução como `UnboundLocalError`.

---

## 9. Proibição Absoluta de Fallbacks Artificiais em Variáveis Estatísticas
- **Nunca atribuir valores fictícios ou arbitrários como fallback:** Quando dados estatísticos indispensáveis para a precificação de um modelo não forem encontrados no banco de dados (ex: histórico U5J, média de cartões do árbitro, médias móveis do time), **É ESTRITAMENTE PROIBIDO** atribuir valores mágicos ou artificiais (como `0.0`, `5.0` ou médias inventadas) apenas para permitir a continuidade do fluxo.
- **Diretriz Operacional**:
  - Em caso de ausência ou inconsistência de métricas estatísticas essenciais, o sistema deve:
    1. Imprimir explicitamente o erro no console/log identificando o time, ID e o dado ausente.
    2. Interromper o cálculo da partida (`NO_BET`) e **NÃO gerar a aposta**, garantindo a integridade matemática do portfólio.

---

## 10. Proibição Absoluta de Comandos Git (`git commit` e `git push`)
- O assistente/agente **NUNCA DEVE** executar de forma autônoma comandos de versionamento como `git commit`, `git push`, `git merge`, `git rebase` ou equivalentes.
- **Diretriz Operacional**:
  - Todas as operações de controle de versão (criação de commits, push para branches remotas, tags ou merges) são de **responsabilidade e controle exclusivo do usuário desenvolvedor**.
  - O assistente só tem permissão para executar comandos `git commit` ou `git push` se o usuário solicitar de forma textual, direta e explícita nessa instrução específica (ex: *"faça o commit e push disso agora"*).

---

## 11. Proteção Reforçada dos Motores de Apostas (`cards_engine.py` e `asian_handicap_engine.py`) e Sincronização Obrigatória com PHP
- **Núcleo Crítico Intocável sem Autorização Justificada:** Os arquivos `scripts/cards_engine.py` e `scripts/asian_handicap_engine.py` são os motores centrais matemáticos e estatísticos (Poisson, Gatekeeper, EV e liquidez) de todo o sistema.
- **Fluxo Obrigatório Pré-Edição:** O assistente/agente está terminantemente proibido de alterar qualquer linha desses dois arquivos sem antes:
  1. **Apresentar Justificativa Matemática e de Negócio:** Explicar detalhadamente o motivo da alteração, a anomalia ou necessidade identificada e a comprovação matemática de que não se trata de *overfitting* ou reação a uma zebra pontual (respeitando rigorosamente as Regras 6 e 7).
  2. **Mapear Impacto Sistêmico:** Demonstrar o impacto nos pipelines consumidores (`criar_apostas_handicap_diario.py`, `criar_apostas_cartoes_diario.py`, `football_ingest_trends.py` e `ApostaController.php`).
  3. **Apresentar o Diff Completo Proposto.**
  4. **Aguardar a Autorização Explícita do Usuário:** Somente aplicar a edição após o usuário ler a justificativa e responder expressamente autorizando a alteração.
- **Sincronização Obrigatória com `ApostaController.php`:**
  - Se você alterar uma regra matemática no `asian_handicap_engine.py` ou `cards_engine.py` (por exemplo, um novo piso de odd ou threshold de probabilidade), as apostas automáticas do Airflow seguirão o Python, mas apostas criadas manualmente pela web usarão o `ApostaController.php`. Portanto, se a regra de validação do Gatekeeper mudar no Python, o método PHP correspondente (`evaluateGatekeeper` em `src/footballweb/app/Controllers/ApostaController.php`) **DEVE OBRIGATORIAMENTE ser alinhado** para manter consistência e integridade total entre a esteira autônoma e as apostas manuais.

---

## 12. Proibição Absoluta de Geração de Dados Sintéticos e Odds Fictícias
- **Apenas Dados Reais de Mercado e de Campo:** É terminantemente proibido gerar, simular, interpolar ou inventar linhas de apostas, cotações/odds sintéticas (ex: tags ou métodos como `POISSON_SYNTHETIC`, `build_fallback_lines_from_odds` que inventem odds sem lastro em bookmaker real) ou quaisquer métricas estatísticas simuladas nos processamentos de qualquer algoritmo ou motor do sistema (`asian_handicap_engine.py`, `football_ingest_trends.py`, `cards_engine.py`, etc.).
- **Diretriz Operacional e Abstenção Mandatória:**
  - A esteira de ingestão de tendências e os motores preditivos **não mais gerarão palpites de handicap se não houver linhas reais das casas de apostas**, ou seja, se não houver retorno de cotações reais da **API-Football** nem da **The Odds API** (fallback de contingência quando a cota estiver esgotada).
  - Se as cotações reais das casas de apostas oficiais não estiverem disponíveis ou ativas no momento da execução, o sistema **NUNCA DEVE inventar, interpolar ou deduzir odds sintéticas a partir do 1X2**.
  - Em vez de sintetizar linhas e odds inexistentes, o sistema deve registrar a ausência de liquidez de mercado e decretar **`NO_BET` (Abstenção Mandatória por Ausência de Cotações Reais de Casas de Apostas na API-Football / The Odds API)**.
  - Toda aposta simulada, sugerida ou registrada na plataforma deve obrigatoriamente possuir 100% de correspondência com cotações reais, líquidas e comprovadas nas bookmakers oficiais.

---

## 13. Comunicação em Linguagem Natural Clara (Proibição de Fórmulas e Expressões Matemáticas Brutas)
- **Foco em Clareza e Negócio:** Toda explicação, análise de partidas, diagnóstico de anomalias ou relatório apresentado ao usuário deve ser expresso em **linguagem natural clara, direta e objetiva**.
- **Proibição de Fórmulas Matemáticas Brutas:** É expressamente proibido responder com fórmulas matemáticas em LaTeX, blocos de equações ou sequências aritméticas brutas (como cadeias de multiplicações de decimais ou notações acadêmicas). O assistente deve sempre traduzir o raciocínio em termos práticos de futebol, explicando o conceito por trás dos números (ex: "o efeito acumulado de vários redutores derrubou excessivamente a expectativa de gols do time mandante").

---

## 14. Registro Obrigatório em Diário de Bordo para Qualquer Alteração em Motores de Regras e Critérios de Palpites/Apostas
- **Documentação Mandatória e Imediata:** Sempre que for realizada qualquer criação, alteração, refatoração, calibração de pesos, adição/remoção de filtros ou ajuste nos critérios de avaliação e geração de palpites e apostas nos motores de **Handicap Asiático (AH)** ou de **Cartões** (em arquivos como `scripts/asian_handicap_engine.py`, `scripts/cards_engine.py`, `scripts/football_ingest_trends.py`, `scripts/criar_apostas_handicap_diario.py`, `scripts/criar_apostas_cartoes_diario.py`, `src/footballweb/app/Controllers/ApostaController.php` ou correlatos), o assistente/desenvolvedor **DEVE OBRIGATORIAMENTE registrar e detalhar minuciosamente a intervenção no Diário de Bordo**.
- **Localização e Nomenclatura Padrão:**
  - Diretório oficial: `docs/footballweb/diario-bordo/`.
  - Padrão do nome do arquivo: **`yyyy-mm-dd.md`** (ano-mês-dia, ex: `2026-09-16.md`). Se o arquivo da data corrente já existir, a documentação deve ser adicionada como uma nova seção temática estruturada no mesmo documento.
- **Conteúdo Mínimo Obrigatório no Diário de Bordo:**
  1. **Motivação e Diagnóstico Técnico:** Identificação do problema, anomalia, requisito de calibração ou desvio de performance que motivou a mudança.
  2. **Arquivos e Trechos Modificados:** Relação completa de scripts, classes e métodos alterados.
  3. **Impacto Prático e Regras de Negócio:** Comparativo detalhado em linguagem clara explicando o comportamento anterior vs. o novo comportamento esperado do motor e da gestão de risco da banca.
  4. **Validação e Testes:** Registro das checagens de sintaxe, simulações ou testes executados que comprovam a estabilidade sistêmica da alteração.

---

## 15. Proibição Absoluta de Silenciamento de Exceções de Banco de Dados e Camada Model (Visibilidade Obrigatória de Falhas)
- **Tolerância Zero a `except: pass` e Supressão Oculta de Erros:**
  - É expressamente proibido silenciar, mascarar ou capturar genericamente exceções provenientes da camada de banco de dados (MySQL/Postgres) e da camada Model/DAO sem registrar detalhadamente a falha no console e nos logs do sistema.
  - O uso de blocos como `except Exception: pass`, `except: continue` sem log, ou `catch (\Throwable $e) {}` vazios em rotinas de inserção, atualização ou exclusão de dados é **terminantemente proibido**.
- **Obrigatoriedade de Contexto Completo no Registro de Falhas:**
  - Qualquer erro relacional ou estrutural (violação de chave estrangeira `FK 1452`, chave duplicada `1062`, deadlock, timeout de conexão ou falha de constraints) deve obrigatoriamente imprimir no log:
    1. A operação em execução (`INSERT`, `UPDATE`, `DELETE`);
    2. A tabela e as entidades afetadas (ex: `fixture_id`, `team_id`, `referee_name`);
    3. O código numérico e a mensagem literal emitida pelo banco de dados;
    4. O impacto direto na esteira (ex: "abortando enriquecimento da partida por falha de integridade").
- **Proteção da Banca contra Estados Corrompidos (Fail-Fast):**
  - O sistema nunca deve prosseguir como se uma gravação tivesse sido realizada com sucesso quando a camada de banco rejeitou a operação.
  - Se um dado indispensável não puder ser persistido devido a uma falha de modelo, a aposta ou predição correspondente deve ser imediatamente suspensa (`NO_BET` / Abstenção por Falha Relacional), evitando que apostas financeiras reais sejam emitidas sobre premissas estatísticas incompletas ou ausentes.

---

## 16. Preservação Obrigatória e Integridade Estrutural do Histórico Recente (Payload U5J) em Cards e Apostas
- **Obrigatoriedade e Imutabilidade do Payload `|| U5J_DATA:`:**
  - O histórico dos últimos 5 jogos (U5J) é a espinha dorsal analítica e visual do dashboard e dos motores preditivos. É terminantemente proibido gravar ou atualizar a coluna `fixtures_trends.ah_reasoning` ou `apostas.resultado_detalhado` sem que o bloco estruturado `|| U5J_DATA: {"home": {...}, "away": {...}}` esteja devidamente anexado e íntegro.
- **Proibição Absoluta de Sobrescrita Destrutiva em Atualizações e Cancelamentos:**
  - Toda e qualquer rotina que atualize o texto de raciocínio de uma partida (seja por cancelamento de aposta, abstenção da IA, recálculo de odds Betano ou ingestão de dados em `scripts/football_ingest_trends.py`, `scripts/asian_handicap_engine.py`, `scripts/criar_apostas_handicap_diario.py`, etc.) **DEVE OBRIGATORIAMENTE utilizar a função canônica `compose_compound_ah_reasoning`** para preservar ou recompor o payload `|| U5J_DATA:`.
  - É expressamente proibido executar `UPDATE fixtures_trends SET ah_reasoning = ...` com mensagens parciais (ex: apenas "Aposta Cancelada" ou "Sem odds Betano") que descartem ou trunquem o bloco `|| U5J_DATA:`.
- **Garantia de Amostragem Multi-Competições e Independência de Liga no Cache (`team_last5_cache`):**
  - O histórico de 5 jogos recentes de um time reflete sua forma esportiva recente geral (multi-competições). É expressamente proibido descartar ou desconsiderar o cache de um time em `team_last5_cache` pelo fato da partida do dia pertencer a uma liga diferente (ex: Santos e Vasco disputando a Série A com partidas recentes de Copa do Brasil registradas no cache). A busca por `team_id` deve prevalecer de forma soberana e agnóstica à liga da partida.
- **Fail-Safe de Recomposição Imediata:**
  - Se, durante qualquer processamento, uma partida for identificada com ausência do bloco `|| U5J_DATA:` ou histórico corrompido, a esteira deve obrigatoriamente acionar a recomposição via `get_team_u5j_from_db` / `team_last5_cache` e API oficial antes de persistir o registro, impedindo que cards sem U5J sejam exibidos na plataforma.

---

## 17. Blindagem e Higienização Visual da UX (Proibição Absoluta de Vazamento de Memória de Cálculo, Fórmulas e Payloads Técnicos)
- **Experiência do Usuário (UX) Limpa e Focada no Negócio:**
  - A interface com o usuário final (cards do dashboard, telas de apostas, modais e notificações) deve apresentar exclusivamente análises, diagnósticos e motivações em **linguagem natural clara, elegante e compreensível**, respeitando a Regra 13.
- **Proibição Absoluta de Vazamento de Payloads e Delimitadores Internos:**
  - É expressamente proibido exibir na interface visual qualquer trecho contendo:
    1. Delimitadores estruturados de backend (ex: `|| MEMÓRIA DE CÁLCULO ||`, `|| U5J_DATA:`, `|| PROBABILIDADES_1X2:`, `|| EXPLICACAO:`, `|| MOTIVACAO:`);
    2. JSONs crus ou fragmentos de dicionários serializados (ex: `{"home": {"v": 0, "e": 1...}}`);
    3. Fórmulas matemáticas brutas, lambdas de Poisson, probabilidades fracionárias brutas ou sequências aritméticas de cálculo.
- **Isolamento Estrito de Camadas (Auditoria no Backend vs. Clareza no Frontend):**
  - Toda a complexidade técnica, memória de cálculo detalhada e payloads JSON estruturados pertencem **exclusivamente à camada de banco de dados** (colunas de auditoria no MySQL) para alimentar os algoritmos da IA, motores analíticos e históricos preditivos.
  - A camada de apresentação (views e controllers PHP) deve obrigatoriamente higienizar todo texto antes de exibi-lo em tela, extraindo estritamente a síntese em linguagem natural (ex: o bloco `REASON:`) e suprimindo de forma irrestrita qualquer bloco que contenha `|| MEMÓRIA DE CÁLCULO` ou tags técnicas internas.


