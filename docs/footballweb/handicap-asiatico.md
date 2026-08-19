# ⚽ Guia Prático do Handicap Asiático & Módulo FootballWeb

Este documento contém a explicação completa, tabelas de referência, exemplos práticos e a arquitetura técnica do módulo de **Handicap Asiático** implementado na plataforma **FootballWeb**.

---

## 📌 O que é o Handicap Asiático?

O **Handicap Asiático (AH)** é uma das modalidades de aposta mais populares no futebol profissional. Sua principal característica é **eliminar a possibilidade de empate seco (1X2)**, concedendo uma vantagem (+) ou desvantagem (-) fictícia de gols a uma das equipes antes do apito inicial.

Além de simplificar a decisão entre duas opções (Mandante ou Visitante), o Handicap Asiático introduz o conceito de **reembolso total ou parcial** do valor apostado, dependendo da margem exata de gols da partida.

---

## 💡 Entendendo os Sinais: Negativo (-) vs Positivo (+)

- **Handicap Negativo (ex: -0.25, -0.50, -0.75, -1.00):**
  - Atribuído ao **time favorito**.
  - O time começa o jogo "devendo" gols e precisa vencer por uma margem suficiente para cobrir a desvantagem.

- **Handicap Positivo (ex: +0.25, +0.50, +0.75, +1.00):**
  - Atribuído ao **time zebra (azarão)**.
  - O time começa o jogo "com vantagem" de gols.

---

## 📊 Tabela de Referência Completa das Linhas de Handicap Asiático

| Linha de Handicap | Se o seu time Vence | Se a partida Empata | Se o seu time Perde |
| :--- | :--- | :--- | :--- |
| **0.0 (Empate Anula / DNB)** | 🟢 **Ganha 100%** | 🟡 **Reembolso 100%** | 🔴 **Perde 100%** |
| **-0.25 (-0.0, -0.50)** | 🟢 **Ganha 100%** | 🟡 **Perde 50%** *(Recebe 50% da Stake de volta)* | 🔴 **Perde 100%** |
| **-0.50 (Vitória Simples)** | 🟢 **Ganha 100%** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-0.75 (-0.50, -1.00)** | 🟢 Vence por 2+ gols: **Ganha 100%**<br>🟡 Vence por 1 gol: **Ganha 50% do Lucro** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-1.00** | 🟢 Vence por 2+ gols: **Ganha 100%**<br>🟡 Vence por 1 gol: **Reembolso 100%** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-1.25 (-1.00, -1.50)** | 🟢 Vence por 2+ gols: **Ganha 100%**<br>🟡 Vence por 1 gol: **Perde 50%** *(Recebe 50% da Stake de volta)* | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-1.50** | 🟢 Vence por 2+ gols: **Ganha 100%** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-1.75 (-1.50, -2.00)** | 🟢 Vence por 3+ gols: **Ganha 100%**<br>🟡 Vence por 2 gols: **Ganha 50% do Lucro** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-2.00** | 🟢 Vence por 3+ gols: **Ganha 100%**<br>🟡 Vence por 2 gols: **Reembolso 100%** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **+0.25 (+0.0, +0.50)** | 🟢 **Ganha 100%** | 🟢 **Ganha 50% do Lucro** + 100% Aposta | 🔴 **Perde 100%** |
| **+0.50 (Dupla Chance)** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 **Perde 100%** |
| **+0.75 (+0.50, +1.00)** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 Perde por 2+ gols: **Perde 100%**<br>🟡 Perde por 1 gol: **Perde 50%** *(Recebe 50% da Stake de volta)* |
| **+1.00** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 Perde por 2+ gols: **Perde 100%**<br>🟡 Perde por 1 gol: **Reembolso 100%** |
| **+1.25 (+1.00, +1.50)** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 Perde por 2+ gols: **Perde 100%**<br>🟡 Perde por 1 gol: **Ganha 50% do Lucro** + 100% Aposta |
| **+1.50** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🟢 Perde por 1 gol: **Ganha 100%**<br>🔴 Perde por 2+ gols: **Perde 100%** |
| **+1.75 (+1.50, +2.00)** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🟢 Perde por 1 gol: **Ganha 100%**<br>🟡 Perde por 2 gols: **Perde 50%** *(Recebe 50% da Stake de volta)*<br>🔴 Perde por 3+ gols: **Perde 100%** |
| **+2.00** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🟢 Perde por 1 gol: **Ganha 100%**<br>🟡 Perde por 2 gols: **Reembolso 100%**<br>🔴 Perde por 3+ gols: **Perde 100%** |

> 💡 **Entendendo a porcentagem no Reembolso Parcial / Meio Loss:**
> * **"Perde 50% (Recebe 50% de volta)":** Significa que você perde **50% do VALOR APOSTADO (Stake)**. A **odd não é multiplicada** na metade perdida. Exemplo: se apostar **R$ 100,00**, você perde R$ 50,00 e o saldo de **R$ 50,00 (50% da Stake)** é devolvido à sua conta.
> * **"Ganha 50% do Lucro (Meio Win)":** Significa que você recebe **100% da sua Stake de volta + 50% do Lucro Líquido** que ganharia com a Odd. Exemplo: R$ 100,00 em Odd 2.00 $\rightarrow$ Lucro total seria R$ 100,00. No meio win você recebe **R$ 100 (Stake) + R$ 50 (50% do Lucro) = R$ 150,00 de retorno total**.

### 💡 Diferença Fundamental entre +0.25 AH vs -0.25 AH vs No Bet no Empate

Uma dúvida frequente na gestão de apostas é a distinção entre as linhas de **Quarter-Ball (+0.25 AH e -0.25 AH)**, o **Handicap 0.0 (DNB / Empate Anula)** e a opção de **No Bet (Abstenção)** em cenários onde o jogo termina empatado (ex: placar de 2 x 2).

> ⚠️ **Atenção aos Sinais e Direcionamento da Aposta:**
> * 🔴 **`-0.25 AH` (Mandante/Favorito):** Você aposta na vitória do favorito. Se a partida empatar (ex: 2x2), você sofre **Meia Perda (Half Loss)**: **50% da stake é perdoada/estornada** de volta para sua conta e **50% é perdida**.
> * 🟢 **`+0.25 AH` (Visitante/Azarão):** Você aposta que o visitante não perde. Se a partida empatar, você obtém **Meio Green (Half Win)**: **100% da stake é devolvida + 50% do lucro da odd**.
> * 🟡 **`0.0 AH` (Empate Anula / DNB):** Se a partida empatar, há **Reembolso Total (100% da stake estornada)**, sem lucro e sem prejuízo.
> * ⚪ **`No Bet` (Abstenção):** Entrada não realizada devido ao alto risco ou incerteza estatística.

#### 📊 Estudo Comparativo de uma aposta de R$ 100,00 em um jogo empatado em 2 a 2:

| Opção de Entrada | Tese da Aposta | Comportamento no Empate (2 x 2) | Estorno da Stake | Retorno Total | Lucro / Prejuízo Líquido |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`-0.25 AH` (Favorito)** | Vitória do Favorito | 🟠 **Meia Perda (Half Loss)** | **R$ 50,00** (50% estornada) | **R$ 50,00** | 🔴 **- R$ 50,00** (Perde 50%) |
| **`+0.25 AH` (Azarão)** | Não-Derrota do Azarão | 🟢 **Meio Green (Half Win)** | **R$ 100,00** (100% devolvida) | **R$ 150,00** *(odd 2.0)* | 🟢 **+ R$ 50,00** (50% do lucro) |
| **`0.0 AH` (DNB)** | Vitória com Cobertura | 🟡 **Reembolso Total** | **R$ 100,00** (100% estornada) | **R$ 100,00** | 🟡 **R$ 0,00** (Zero Lucro/Perda) |
| **`No Bet` (Abstenção)** | Ficar de Fora | ⚪ **Ficou de Fora** | **R$ 100,00** (Stake preservada) | **R$ 100,00** | ⚪ **R$ 0,00** (Zero Lucro/Perda) |

#### 🎯 Por que escolher `-0.25 AH` em vez de `+0.25 AH` ou `No Bet`?
1. **Frente ao `-0.5 AH` (Vitória Simples):** O `-0.25 AH` mantém a tese de vitória do favorito, mas reduz em 50% o prejuízo de um empate inesperado (como um 2 x 2 no final da partida).
2. **Frente ao `+0.25 AH`:** O `+0.25 AH` é o lado oposto (da zebra). Escolhe-se o `-0.25 AH` quando o modelo projeta probabilidade consideravelmente superior de vitória do mandante do que de surpresa do visitante.
3. **Frente ao `No Bet`:** Quando o modelo enxerga valor na vitória do mandante mas quer blindagem contra o empate, utilizar o `-0.25 AH` é matematicamente superior ao `No Bet`, pois mantém o ganho total no caso de vitória sem expor 100% da stake ao risco do empate.

---

## ⚡ Handicap Asiático Ao Vivo (In-Play) vs Pré-Jogo: A Regra do Placar Zerado (0-0)

Uma dúvida comum no Handicap Asiático diz respeito ao funcionamento de apostas feitas em jogos em andamento (**apostas ao vivo**).

### 🔴 Regra Geral do Handicap Asiático Ao Vivo: O "Placar Zerado"
Na maioria das casas de apostas (ex: Bet365, Pinnacle, Betano), ao realizar uma aposta em **Handicap Asiático Ao Vivo**:
* **O placar da partida é ficticiosamente "resetado" para 0 x 0** no momento exato em que a aposta é realizada.
* A aposta considera **apenas os gols marcados no restante da partida** (após o momento da entrada).

#### 📌 Estudo de Caso Prático:
* **Placar no momento da aposta:** Time A 1 x 0 Time B.
* **Sua Aposta Ao Vivo:** `Time A 0.0`.
* **O que aconteceu depois:** O Time B marca um gol e empata a partida $\rightarrow$ Placar final real: **1 x 1**.
* **Contagem pós-aposta:**
  * Gols do Time A após a aposta: **0**
  * Gols do Time B após a aposta: **1**
  * Placar do período apostado: **0 x 1** (Vitória do Time B no restante do jogo).
* **Resultado da Aposta:** 🔴 **RED (Aposta Perdida)**. Para a aposta `Time A 0.0` ser reembolsada ou ganha, o Time A precisaria ter empatado ou vencido o trecho restante da partida (marcando a mesma quantidade ou mais gols que o Time B a partir do momento da entrada).

---

### 🟡 Comparativo: Handicap Asiático Ao Vivo vs Empate Anula (DNB / Placar Cheio)

| Mercado Selecionado Ao Vivo | Placar Considerado | Placar Pós-Aposta (Entrada no 1x0 $\rightarrow$ Final 1x1) | Resultado da Aposta `Time A 0.0` |
| :--- | :--- | :--- | :--- |
| **Handicap Asiático Ao Vivo** *(Padrão 0-0)* | Reseta para 0 x 0 na entrada | 0 x 1 (Time B venceu a parcial) | 🔴 **RED** (Perdeu 100%) |
| **Empate Anula (DNB) / Placar Integral** | Placar Real da Partida (1 x 1) | 1 x 1 (Empate geral do jogo) | 🟡 **REEMBOLSO** (Devolvido 100%) |

> 💡 **Dica Importante ao Apostar Ao Vivo:**
> Sempre verifique a nomenclatura do mercado na casa de apostas. Se o mercado indicar **`Handicap Asiático (0-0)`** ou exibir o placar atual entre parênteses como **`Handicap Asiático (1-0)`**, a contagem de gols inicia em 0x0 a partir daquele instante.

---

## ⚽ Exemplos Práticos de Apostas

### Exemplo 1: *Ceará vs Ponte Preta* — Aposta: **Ceará -0.25 AH**
- **Se o Ceará vencer (1x0, 2x0, 2x1):** Você **Ganha 100%** da aposta 🟢.
- **Se o jogo terminar empatado (0x0, 1x1):** Você **Perde 50%** da aposta e recupera os outros 50% 🟡.
- **Se a Ponte Preta vencer:** Você **Perde 100%** da aposta 🔴.

### Exemplo 2: *Operário-PR vs São Bernardo* — Aposta: **Handicap 0.0 (Empate Anula)**
- **Se o Operário-PR vencer:** Você **Ganha 100%** da aposta 🟢.
- **Se o jogo terminar empatado:** Seu dinheiro é **100% Devolvido (Reembolso)** 🟡.
- **Se o São Bernardo vencer:** Você **Perde 100%** da aposta 🔴.

---

### 🎯 Caso Concreto Real: *Operário-PR vs São Bernardo* na Betano

Este caso real ilustra como utilizar o card de análises do **FootballWeb** como suporte de decisão diretamente na interface de apostas da **Betano**:

#### 1. Diagnóstico Gerado pelo Card do FootballWeb:
- **Sugestão de Aposta:** `🎯 Operário-PR 0.0 (Empate Anula)`
- **Nível de Confiança:** `66.00%`
- **Explicação em Linguagem Natural:**
  - 🟢 **Vitória do Operário-PR:** Você GANHA 100% da aposta (Lucro Total).
  - 🟡 **Empate:** 100% do valor apostado é DEVOLVIDO (Reembolso).
  - 🔴 **Vitória do São Bernardo:** Aposta PERDIDA.
- **Tabela Retrospectiva U5J:**
  - Operário-PR: 1 pt (0V-1E-4D)
  - São Bernardo: 10 pts (3V-1E-1D)
- **Motivação do Palpite:** *🎯 **Fator Crucial: Alerta de Crise e Sequência Negativa do Mandante (0V-1E-4D em U5J).** Este palpite foi gerado devido à severa má fase do Operário-PR em casa. Em contrapartida, o São Bernardo atravessa um momento muito mais consistente (3V-1E-1D em U5J). O algoritmo identificou alto risco no mandante e transferiu a vantagem para o visitante São Bernardo com cobertura no empate.*

#### 2. Cotações Reais Extraídas do Painel do Handicap Asiático da Betano:
- `Operário-PR 0.0`: **Odd 1.40**
- `Operário-PR -0.25`: **Odd 1.67**
- `Operário-PR -0.5`: **Odd 1.93**
- `São Bernardo +0.25`: **Odd 2.18**
- `São Bernardo 0.0`: **Odd 2.87**

#### 3. Tomada de Decisão & Execução das Estratégias:

- **Estratégia 1: Entrada Principal Conservadora (Alinhada 100% ao FootballWeb)**
  - **Seleção na Betano:** Clique na opção **`Operário-PR 0.0`** (Odd **`1.40`**).
  - **Comportamento da Aposta:**
    - 🟢 **Vitória do Operário-PR:** Lucro 100% (Odd 1.40).
    - 🟡 **Empate:** Reembolso 100% do dinheiro investido.
    - 🔴 **Vitória do São Bernardo:** Aposta perdida.

- **Estratégia 2: Entrada Alternativa de Alto Valor (Zebra com Proteção de Vantagem)**
  - **Seleção na Betano:** Clique na opção **`São Bernardo +0.25`** (Odd **`2.18`**).
  - **Comportamento da Aposta:**
    - 🟢 **Vitória do São Bernardo:** Lucro 100% com cotação alta (**Odd 2.18**).
    - 🟢 **Empate:** Lucro de 50% (Meio Ganho) e devolução do valor inicial.
    - 🔴 **Vitória do Operário-PR:** Aposta perdida.

---

### 🎯 Caso Concreto Real 2: *Ceará vs Ponte Preta* na Betano (Favoritismo Elevado)

Este caso exemplifica a tomada de decisão quando há **forte favoritismo do time da casa** no **FootballWeb**:

#### 1. Cotações Reais Extraídas do Painel da Betano:
- `Ceará -0.75`: **Odd 1.52**
- `Ceará -1.0`: **Odd 1.67**
- `Ceará -1.25`: **Odd 1.95**
- `Ceará -1.5`: **Odd 2.22**
- `Ponte Preta +1.0`: **Odd 2.15**
- `Ponte Preta +1.25`: **Odd 1.83**

#### 2. Análise Estratégica das Linhas de Aposta:

- **Estratégia 1: Entrada Equilibrada com Reembolso no Placar Mínimo (`Ceará -1.0 AH` @ Odd 1.67)**
  - 🟢 **Ceará vence por 2 ou mais gols (2x0, 3x0, 3x1):** Aposta **Ganha 100%** (Lucro total).
  - 🟡 **Ceará vence por 1 gol exato (1x0, 2x1):** **Reembolso 100%** (Dinheiro totalmente devolvido sem prejuízo).
  - 🔴 **Empate ou Vitória da Ponte Preta:** Aposta perdida.

- **Estratégia 2: Entrada Conservadora com Meio Ganho (`Ceará -0.75 AH` @ Odd 1.52)**
  - 🟢 **Ceará vence por 2 ou mais gols:** Aposta **Ganha 100%**.
  - 🟡 **Ceará vence por 1 gol exato:** **Ganha 50% do Lucro (Meio Ganho)** + 100% do valor apostado de volta.
  - 🔴 **Empate ou Vitória da Ponte Preta:** Aposta perdida.

- **Estratégia 3: Busca por Lucro Máximo (`Ceará -1.5 AH` @ Odd 2.22)**
  - 🟢 **Ceará vence por 2 ou mais gols:** Aposta **Ganha 100%** com cotação alta (**Odd 2.22**).
  - 🔴 **Ceará vence por apenas 1 gol (1x0, 2x1), empata ou perde:** Aposta perdida.

---

## 🏗️ Arquitetura Técnica do Módulo no FootballWeb

O módulo no FootballWeb projeta as linhas de Handicap Asiático combinando a **Expectativa de Gols ($xG$)**, **Fator Mando de Campo (Casa/Fora)**, **Forma dos Últimos 5 Jogos ($V$-$E$-$D$)**, **Proteção Defensiva (Clean Sheets %)** e o **Fator de Crise/Streak** das equipes.

### 1. Cálculo dos Gols Esperados Ajustados ($\lambda_{\text{adj}}$)

$$\lambda_{\text{casa, base}} = \frac{\text{Gols Pró Casa} + \text{Gols Sofridos Fora}}{2}$$

$$\lambda_{\text{fora, base}} = \frac{\text{Gols Pró Fora} + \text{Gols Sofridos Casa}}{2}$$

#### Multiplicadores & Ponderações:
- **Fator Mando de Campo ($F_{\text{mando}}$):** 
  - Mandante em Casa: $F_{\text{mando, casa}} = 1.10$ (+10% bônus realista de mando)
  - Visitante Fora: $F_{\text{mando, fora}} = 0.93$ (-7% ajuste de visitante)
- **Fator Odds de Mercado ($F_{\text{market}}$):**
  - Incorporação dinâmica das probabilidades implícitas ($P = 1 / \text{Odd}$) com amplitude expandida de **0.70 a 1.30** (-30% a +30%).
- **Trava de Alinhamento com o Mercado (Market Preference Guard):**
  - Sempre que as odds do mercado indicarem favoritismo ao visitante ($\text{Odd}_{\text{visitante}} < \text{Odd}_{\text{mandante}}$), o modelo ativa o favoritismo do visitante e cancela bônus artificiais de mando quando o visitante possui momento superior/igual, prevenindo palpites contraditórios a favor do mandante.
- **Fator Forma dos Últimos 5 Jogos ($F_{\text{last5}}$):**
  - Pontuação $P = 3V + 1E$ nos últimos 5 jogos.
  - $P \ge 12$ pts (4V+): $F_{\text{last5}} = 1.25$ (+25% excelente forma)
  - $P \ge 9$ pts (3V): $F_{\text{last5}} = 1.15$ (+15% boa forma)
  - $P \le 2$ pts (0V/1V): $F_{\text{last5}} = 0.65$ (-35% má fase)
  - $P \le 4$ pts: $F_{\text{last5}} = 0.78$ (-22% oscilação)
- **Fator Clean Sheet ($CS_{\text{fator}}$):** Pondera a consistência em manter a meta limpa:
  $$CS_{\text{fator}} = 1.0 + (\text{CleanSheet}_{\%} - 30\%) \times 0.005$$
- **Fator de Forma Recente / Streak ($F_{\text{streak}}$):**
  - Sequência $\ge 4$ derrotas consecutivas: Penalidade de **-30%** no $xG$ ($F_{\text{streak}} = 0.70$).
  - Sequência de 3 derrotas consecutivas: Penalidade de **-20%** no $xG$ ($F_{\text{streak}} = 0.80$).
  - Sequência invicta / vitoriosa (3+ vitórias): Bônus de **+20%** no $xG$ ($F_{\text{streak}} = 1.20$).

$$\lambda_{\text{casa, adj}} = \lambda_{\text{casa, base}} \times F_{\text{mando, casa}} \times F_{\text{last5, casa}} \times CS_{\text{fator, casa}} \times F_{\text{streak, casa}} \times F_{\text{market, casa}}$$

$$\lambda_{\text{fora, adj}} = \lambda_{\text{fora, base}} \times F_{\text{mando, fora}} \times F_{\text{last5, fora}} \times CS_{\text{fator, fora}} \times F_{\text{streak, fora}} \times F_{\text{market, fora}}$$

$$\Delta G = \lambda_{\text{casa, adj}} - \lambda_{\text{fora, adj}}$$

---

### 2. Memória de Cálculo Transparente na UX

No widget visual **`🛡️ Mercado de Gols (Handicap Asiático)`** no `dashboard.php`, o sistema inclui um painel expansível **`📐 Ver Memória de Cálculo Detalhada`** que exibe o passo a passo de todas as variáveis multiplicativas aplicadas tanto para o mandante quanto para o visitante.

---

### 3. Regras de Intervenção de Risco & Gatekeeper em Crise Estrita

- 🛑 **Gatilho Estrito de Crise:** A Trava de Crise (com potencial inversão de palpite para o visitante) é acionada **APENAS** se a equipe mandante tiver **3 ou mais DERROTAS recentes E zero vitórias em U5J** ($D \ge 3 \text{ e } V = 0$ em U5J, ex: `0V-1E-4D`). **Empates isolados não geram crise.**
- 🛡️ **Amortecimento para Favoritos ($xG_{\text{base}} \ge 1.50$):** Equipes com forte produção ofensiva em casa (ex: **Ceará**, **Palmeiras**, **Flamengo**) têm suas oscilações amortecidas pelo mando de campo (+10%), mantendo a indicação no mandante (`Ceará -0.5 AH` ou `Ceará -0.75 AH`) sem falsa sinalização de risco.

---

### 4. Mapeamento de Sugestão de Mercado:
- $\Delta G \ge +1.30 \rightarrow$ **Mandante -1.0 AH**
- $+0.65 \le \Delta G < +1.30 \rightarrow$ **Mandante -0.5 AH**
- $+0.20 \le \Delta G < +0.65 \rightarrow$ **Mandante -0.25 AH**
- $-0.19 \le \Delta G \le +0.19 \rightarrow$ **Handicap 0.0 (Empate Anula)** *(sem alertas de crise)*
- $-0.65 \le \Delta G < -0.20 \rightarrow$ **Visitante +0.25 AH**
- $-1.30 \le \Delta G < -0.65 \rightarrow$ **Visitante +0.5 AH**
- $\Delta G < -1.30 \rightarrow$ **Visitante -1.0 AH**

---

### 5. Estudo de Caso & Aprendizado: *Operário-PR vs São Bernardo (1 x 3)*
- **O que aconteceu:** O modelo legado sugeriu `Operário-PR 0.0` por considerar apenas a média simples em casa. O Operário vinha de má fase recente e concedeu 3 gols.
- **Como o modelo ajustado responde agora:** Com o novo algoritmo, os fatores $F_{\text{mando}}$, $F_{\text{last5}}$ (0V-1E-4D) e a baixa taxa de clean sheet ativam a trava de crise, alterando a sugestão para `São Bernardo -0.5 AH` com o aviso: *`⚠️ Alerta de Crise: Severa má fase do Operário-PR (U5J: 0V-1E-4D). Favoritismo direto para o visitante São Bernardo.`* e exibindo toda a memória de cálculo expandida.

---

### 6. Componentes no Código
- **MySQL (`fixtures_trends`):** Armazena `ah_suggestion`, `ah_confidence` e `ah_reasoning`.
- **Ingestão (`scripts/football_ingest_trends.py`):** Executa o cálculo ponderado em `calculate_asian_handicap_suggestion()` e recupera os últimos 5 jogos com `fetch_team_last5_form()`.
- **Interface (`app/Views/football/dashboard.php`):** Renderiza o widget visual **`🛡️ Mercado de Gols (Handicap Asiático)`** e o painel de memória de cálculo detalhada.
