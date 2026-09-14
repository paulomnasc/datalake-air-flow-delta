# Walkthrough: Endurecimento do Gatekeeper de Handicap Asiático (Opção A)

O Gatekeeper de Handicap Asiático foi reestruturado e fortalecido estruturalmente para eliminar as fontes de sangria da banca (meio-reds por 1 gol de diferença, apostas agressivas com odds esticadas e cortes de probabilidade permissivos), alinhando seu padrão de rigor ao modelo de Cartões (86.7% de acerto).

---

## 🛠️ Modificações Realizadas

Arquivo modificado: [scripts/asian_handicap_engine.py](file:///root/datalake-air-flow-delta/scripts/asian_handicap_engine.py)

### 1. Eliminação Sistemática das Linhas `+0.75 AH` para Azarões
- **Antes**: `standard_allowed_lines = {0.0, 0.5, 0.75, 1.0, 1.25, 1.5}`. O sistema aceitava entradas de `+0.75 AH` para zebras, resultando em sequências de *meio-red* (-50% da stake) toda vez que a equipe perdia por 1 gol (1x0, 2x1).
- **Depois**: `standard_allowed_lines = {0.0, 0.5, 1.0, 1.25, 1.5}`.
  - Para vantagem defensiva, o sistema agora aceita estritamente `+0.5 AH` (Dupla Chance - empate garante 100% de lucro) ou `+1.0 AH` (onde a derrota por 1 gol é **100% Reembolsada / PUSH**).

### 2. Teto de Cotação e Restrição de Queda para Favoritos (`-0.25 AH`)
- **Antes**: Odds de até `2.25` eram toleradas em `-0.25 AH`, permitindo apostas em times que a casa de aposta precificava com probabilidade real de vitória inferior a 45% (ex: Luzern @ 2.25, Lask Linz @ 2.25).
- **Depois**:
  - **Teto rígido de cotação**: `c_odd <= 1.85`. Se a Betano paga mais de 1.85 no `-0.25 AH`, o favoritismo é considerado frágil e a entrada é descartada.
  - **Trava de Momento**: Proibição terminante de `-0.25 AH` se a equipe favorita estiver em queda de rendimento (`cand_trend == 'CURVA_DESCENDENTE'`).

### 3. Elevação do Sarrafo de Probabilidade Efetiva (`min_prob`)
- **Antes**: `min_prob = 48.0%` (aceitava apostas em limiar de cara-ou-coroa).
- **Depois**:
  - `min_prob` padrão elevado para **`58.0%`**.
  - Para linhas `-0.25 AH`, a probabilidade mínima foi elevada de 48.0% para **`55.0%`**.
  - Teto de odds em linhas defensivas reduzido de 2.35 para 2.10.

### 4. Inicialização Prévia de Variáveis (Regra de Ouro 8)
- Todas as variáveis numéricas locais contadoras, acumuladoras e de controle (`cand_pts`, `opp_pts`, `cand_v`, `cand_pts_eff`, `score`, `ev`, `prob_eff`, etc.) foram formalmente declaradas e inicializadas explicitamente antes de qualquer laço ou condicional.

### 5. Trava de Duelo de Crises e Proibição de Apoiar Azarão em Declínio
- **Trava de Duelo de Crises (Match Level)**: Se ambas as equipes apresentarem aproveitamento precário no U5J ($\le 3.0$ pts de eficiência ou $\le 1$ vitória recente cada), a partida é sumariamente bloqueada com `NO_BET`, eliminando o risco de "duelos de cegos" com alta aleatoriedade.
- **Trava de Azarão em Declínio (+AH)**: É terminantemente proibido apoiar equipe em handicap positivo (`+0.5 AH`, `+1.0 AH`) se ela estiver em `CURVA_DESCENDENTE` ou tiver aproveitamento precário ($\le 1$ vitória e $\le 4$ pts ou eficiência $\le 3.0$ pts).

---

## 🧪 Validação e Testes

1. **Compilação Python**:
   - `python3 -m py_compile /root/datalake-air-flow-delta/scripts/asian_handicap_engine.py` $\rightarrow$ Sucesso (Exit code 0).
   - `python3 -m py_compile /root/datalake-air-flow-delta/scripts/criar_apostas_handicap_diario.py` $\rightarrow$ Sucesso (Exit code 0).
   - `python3 -m py_compile /root/datalake-air-flow-delta/scripts/football_ingest_trends.py` $\rightarrow$ Sucesso (Exit code 0).

2. **Reprocessamento de Botafogo-SP vs Goiás (`#1520873` / Aposta `#4199`)**:
   - Executado reprocessamento via `checar_odds_ah_fixture.py`.
   - A aposta `#4199` foi **cancelada/estornada** no sistema com status `NO_BET`.
   - O card em `fixtures_trends` foi atualizado para:
     `🛡️ [Gatekeeper AH NO_BET / Duelo de Crises] Partida Botafogo SP vs Goias -> Ambas as equipes em momento técnico desfavorável no U5J (Botafogo SP -3.0 pts vs Goias 0.8 pts). Confronto de alta imprevisibilidade e desorganização tática. Abstenção mandatória.`
   - Notificação no dashboard enviada ao usuário.

3. **Verificação no Container Docker do Airflow**:
   - Confirmado que os containers do Airflow (`airflow-scheduler` e `airflow-worker`) enxergam imediatamente as novas regras através do bind mount em `/usr/local/bin/scripts`.

---

## 📌 Versionamento e Commits Realizados

- **Branch**: `evo-sharing`
- **Status do Repositório**: Limpo (`working tree clean`, sincronizado com `origin/evo-sharing`)

### 🏆 Último Commit Realizado
- **Hash**: `ce126246afbfa7f4a8b77f7320acda22ff7489c4`
- **Data**: Sun Sep 13 22:28:26 2026 -0300
- **Autor**: Paulo <paulomnasc@gmail.com>
- **Mensagem**: `feat: add rule 8 logic to accurately detect real decline and stable trends`
- **Arquivos**:
  - `scripts/asian_handicap_engine.py` (+37, -11)
  - `scripts/football_ingest_trends.py` (+27, -1)
- **Descrição**: Refinamento da detecção de declínio real (`is_real_decline`), mitigação de derrotas contra equipes Tier 1, ajuste da curva estável para times quase invictos e declaração prévia de variáveis locais conforme a Regra de Ouro 8.

### 📜 Histórico Recente do Ciclo
1. `ce126246afbfa7f4a8b77f7320acda22ff7489c4` - `feat: add rule 8 logic to accurately detect real decline and stable trends`
2. `6e10ba04f3edd9678ca999195c4de89bfe43594b` - `docs: prohibit agents from executing autonomous git commands`
3. `ea9893d7c001ffe27c4813767cbb5ed587c2fc9e` - `fix(ah_engine): add duel of crisis guard and prohibit backing underdogs in decline`
4. `ce8073ecccf19abb5e70514329a99226e20bcb40` - `refactor: exclude 0.75 handicap line, increase minimum probability threshold, and tighten negative handicap limits`
5. `e197efc88716f5e4cc4779e49953f8e63b98eb6f` - `feat: restrict away favorites in Asian handicap, enforce variable initialization, and prevent card bets on missing U5J data`
