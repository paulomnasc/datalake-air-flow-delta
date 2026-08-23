# 📐 Calibragem Estatística do Modelo de Predição de Cartões (FootballWeb)

**Data de Implementação:** 23 de Agosto de 2026  
**Módulo:** FootballWeb Engine (`scripts/football_ingest_trends.py`)  
**Escopo:** Ajuste de Sobredispersão em Poisson, Coeficientes Regionais por Liga e Trava Rígida de Árbitro Severo.

---

## 📌 1. Motivação da Calibragem

A auditoria realizada no [Diário de Bordo de 22/08/2026](file:///root/datalake-air-flow-delta/docs/footballweb/diario-bordo/2026-08-22.md) identificou falhas no modelo estatístico tradicional de Poisson pura:
1. **Falsa Sensação de Segurança:** O modelo atribuía probabilidades de até $99.5\%$ de Under (Odd Justa $1.01$), ignorando a sobredispersão disciplinar.
2. **Desconsideração do Sarrafo da Arbitragem Por Região:** Jogos da América Latina acumulam mais faltas táticas e atrito que jogos europeus.
3. **Exposição a Árbitros Rígidos:** Partidas conduzidas por árbitros com média acima de $5.5$ cartões/jogo estouravam as linhas de Under.

---

## 🧮 2. Fórmulas e Ajustes Matemáticos Implementados

### 2.1 Multiplicadores Disciplinares Regionais Por Liga ($\lambda_{league}$)
A expectativa combinada de cartões dos times passa a ser ajustada pela região geográfica da competição:

$$\text{TeamCards}_{adj} = \text{TeamCards}_{combined} \times \lambda_{league}$$

| Região | Ligas Cobertas | Multiplicador ($\lambda_{league}$) | Fator Sobredispersão ($\phi$) |
| :--- | :--- | :---: | :---: |
| **América Latina (LATAM)** | Brasil (Séries A, B, C, Copa do Brasil), Chile, Argentina, Colômbia, Uruguai, Peru, Equador, México (Liga MX), Costa Rica, Honduras, Libertadores, Sudamericana. | **$1.18\times$** | **$1.28$** |
| **Europa (UEFA)** | Premier League, La Liga, Serie A Itália, Bundesliga, Ligue 1, Liga Portugal, Eredivisie, Champions League, Europa League, Conference League. | **$0.82\times$** | **$1.10$** |
| **Outras Ligas / Default** | Demais ligas nacionais. | **$1.00\times$** | **$1.15$** |

---

### 2.2 Distribuição de Poisson Ajustada por Sobredispersão ($\phi$)
Para corrigir a premissa irreal de Média = Variância da Poisson tradicional, introduziu-se a intensidade efetiva $\lambda_{eff}$:

$$\lambda_{eff} = xC \times \sqrt{\phi}$$

$$P(X \le k) = \sum_{j=0}^{k} \frac{e^{-\lambda_{eff}} \cdot (\lambda_{eff})^j}{j!}$$

#### 🛑 Teto de Segurança de Probabilidade
A probabilidade de Under calculada é **limitada ao teto máximo de $90.0\%$**, garantindo que nenhuma Odd Justa calculada seja inferior a **$1.11$**:

$$\text{ProbUnder}_{final} = \min(90.0\%, \text{ProbUnder}_{cdf} \times 100)$$

$$\text{OddJusta}_{Under} = \frac{100.0}{\text{ProbUnder}_{final}}$$

---

### 2.3 Trava Rígida de Árbitro Severo ($> 5.50$ Cartões)
Quando a média histórica do árbitro escalado for superior a $5.50$ cartões por jogo, a predição é automaticamente convertida em `NO_BET`:

$$\text{Se } \text{Yellows}_{ref} > 5.50 \implies \text{Status} = \text{NO\_BET (Trava de Árbitro Severo)}$$

---

## 🧪 3. Validação do Modelo Recalibrado (Backtest 22/08/2026)

Com a nova calibragem aplicada, o comportamento do sistema nos cenários que geraram RED no dia 22/08/2026 mudou significativamente:

| Partida | Mercado Original | Odd Justa Antiga | Odd Justa Nova | Status Recalibrado |
| :--- | :--- | :---: | :---: | :--- |
| **Audax Italiano x Unión La Calera** (Chile) | Under 5.5 | 1.01 ($99\%$) | **1.22 ($82\%$)** | Exigiu odd de mercado $>1.40$ para considerar entrada. |
| **Internacional x Atlético-MG** (Brasil) | Under 5.5 | 1.01 ($99\%$) | **1.20 ($83\%$)** | Ajustado por $\lambda_{latam} = 1.18$. |
| **U. Católica x Ñublense** (Chile) | Under 5.5 | 1.00 ($99\%$) | **NO_BET / 1.35** | Bloqueado por sobredispersão alta e risco disciplinar. |
| **Everton x Crystal Palace** (Inglaterra) | Over 1.5 por Time | -- | **Bloqueado / NO_BET** | Ajustado por $\lambda_{europe} = 0.82$. |

---

### ✍️ Registro de Versão
- **Versão:** 2.1 - Engine Calibrado com Sobredispersão e Regionalização  
- **Arquivo de Origem:** [`scripts/football_ingest_trends.py`](file:///root/datalake-air-flow-delta/scripts/football_ingest_trends.py)
