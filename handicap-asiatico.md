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
| **-0.25 (-0.0, -0.50)** | 🟢 **Ganha 100%** | 🟡 **Perde 50%** (Recebe 50% de volta) | 🔴 **Perde 100%** |
| **-0.50 (Vitória Simples)** | 🟢 **Ganha 100%** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-0.75 (-0.50, -1.00)** | 🟢 Vence por 2+ gols: **Ganha 100%**<br>🟡 Vence por 1 gol: **Ganha 50% do Lucro** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **-1.00** | 🟢 Vence por 2+ gols: **Ganha 100%**<br>🟡 Vence por 1 gol: **Reembolso 100%** | 🔴 **Perde 100%** | 🔴 **Perde 100%** |
| **+0.25 (+0.0, +0.50)** | 🟢 **Ganha 100%** | 🟢 **Ganha 50% do Lucro** + 100% Aposta | 🔴 **Perde 100%** |
| **+0.50 (Dupla Chance)** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 **Perde 100%** |
| **+0.75 (+0.50, +1.00)** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 Perde por 2+ gols: **Perde 100%**<br>🟡 Perde por 1 gol: **Perde 50%** |
| **+1.00** | 🟢 **Ganha 100%** | 🟢 **Ganha 100%** | 🔴 Perde por 2+ gols: **Perde 100%**<br>🟡 Perde por 1 gol: **Reembolso 100%** |

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

## 🏗️ Arquitetura Técnica do Módulo no FootballWeb

O módulo no FootballWeb projeta as linhas de Handicap Asiático combinando a **Expectativa de Gols ($xG$)** e o **Saldo de Mando de Campo** das equipes.

### 1. Cálculo dos Gols Esperados ($\lambda$)

$$\lambda_{\text{casa}} = \frac{\text{Gols Pró Casa} + \text{Gols Sofridos Fora}}{2}$$

$$\lambda_{\text{fora}} = \frac{\text{Gols Pró Fora} + \text{Gols Sofridos Casa}}{2}$$

$$\Delta G = \lambda_{\text{casa}} - \lambda_{\text{fora}}$$

### 2. Mapeamento de Sugestão de Mercado:
- $\Delta G \ge +1.30 \rightarrow$ **Mandante -1.0 AH**
- $+0.65 \le \Delta G < +1.30 \rightarrow$ **Mandante -0.5 AH**
- $+0.20 \le \Delta G < +0.65 \rightarrow$ **Mandante -0.25 AH**
- $-0.19 \le \Delta G \le +0.19 \rightarrow$ **Handicap 0.0 (Empate Anula)**
- $-0.65 \le \Delta G < -0.20 \rightarrow$ **Visitante +0.25 AH**
- $-1.30 \le \Delta G < -0.65 \rightarrow$ **Visitante +0.5 AH**
- $\Delta G < -1.30 \rightarrow$ **Visitante -1.0 AH**

### 3. Componentes no Código
- **MySQL (`fixtures_trends`):** Armazena `ah_suggestion`, `ah_confidence` e `ah_reasoning`.
- **Ingestão (`scripts/football_ingest_trends.py`):** Executa o cálculo determinístico de Poisson e Expected Goals para todas as partidas.
- **Interface (`app/Views/football/dashboard.php`):** Renderiza o widget visual **`🛡️ Mercado de Gols (Handicap Asiático)`** nos cards de partidas.
