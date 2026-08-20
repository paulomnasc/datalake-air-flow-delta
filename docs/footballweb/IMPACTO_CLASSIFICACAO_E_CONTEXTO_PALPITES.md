# Impacto da Classificação e Contexto do Campeonato no Sistema de Palpites FootballWeb

Este documento detalha o embasamento teórico, estatístico e comportamental sobre como a **classificação dos times (standings)**, o **momento da competição (rodada)** e os **incentivos motivacionais** alteram a postura das equipes em campo e influenciam diretamente os resultados dos confrontos nos mercados de **Match Odds (1X2)**, **Handicap Asiático (AH)**, **Cartões** e **Escanteios**.

---

## 1. Visão Geral & Teoria Estatística

A posição crua de um time na tabela (ex: 3º lugar ou 15º lugar) é um indicador básico de força acumulada. No entanto, para fins de **modelagem quantitativa de apostas (EV+)**, o valor preditivo da classificação reside na **matriz de incentivos** de cada confronto.

### Posição Crua vs. Feature Engineering

| Métrica Crua | Risco se Usada Isolada | Transformação Recomendada no FootballWeb |
| :--- | :--- | :--- |
| Posição Ordinal (1º, 2º...) | Instável nas primeiras 8 rodadas (amostra pequena) | **Pontos Por Jogo (PPG)** (`pontos / jogos`) |
| Saldo de Gols Acumulado | Pode ocultar sorte/azar (*xG* desalinhado) | **Expectativa de Gols (*xG*) vs Gols Reais** |
| Distância de Posição | 10º para 12º pode ter 1 ponto ou 10 pontos | **Distância em Pontos para Zonas Alvo (G4/Z4)** |
| Posição Geral | Oculta discrepâncias de mando de campo | **Classificação Mandante vs Visitante Separada** |

---

## 2. Cenários Táticos & Comportamentais de Jogo

Abaixo estão 5 cenários clássicos onde a situação dos times no campeonato altera a postura tática e o volume estatístico do jogo:

### 🟢 Cenário 1: "Desespero do Z4 vs Férias no Meio da Tabela" (Rodadas 30 a 38)
* **Confronto Exemplo**: *Time A (18º no Z4, 33 pts)* vs *Time B (11º colocado, 46 pts)*.
* **Situação das Equipes**:
  * O **Time A** precisa da vitória para não ser rebaixado.
  * O **Time B** não tem risco de rebaixamento e não alcança a zona de classificação internacional (cumprindo tabela).
* **Mudança Comportamental**:
  * O Time A joga com intensidade física máxima, pressing alto inicial e faltas táticas duras para interromper contra-ataques.
  * O Time B joga sem "sangue nos olhos", evitando divididas ríspidas (receio de lesões antes das férias) e desacelerando o jogo.
* **Impacto nos Mercados**:
  * 🟨 **Cartões**: Alta propensão a *Over Cartões* e faltas cometidas pelo time desesperado.
  * ⚽ **Resultado/Handicap**: O azarão em 18º apresenta um aproveitamento muito superior à sua média histórica recente.

---

### 🟢 Cenário 2: "Confronto de Comadres / Empate de Conveniência" (Rodada Final - 38ª)
* **Confronto Exemplo**: *Time A (3º lugar, 65 pts)* vs *Time B (4º lugar, 64 pts)*.
* **Situação das Equipes**:
  * O 5º colocado possui 63 pts. Se a partida terminar **empatada**, ambos os times chegam a 66 e 65 pts, garantindo os dois no G4 (Libertadores/Champions). Se um perder, pode ser ultrapassado.
* **Mudança Comportamental**:
  * Se o jogo estiver empatado aos 20 minutos do 2º tempo, ambos os treinadores ordenam retenção de posse passiva. Os times trocam passes laterais na zaga sem arriscar chutes.
* **Impacto nos Mercados**:
  * ⚖️ **Empate (1X2)**: A probabilidade de empate dispara drasticamente em relação às odds iniciais do mercado.
  * 📉 **Escanteios/Gols**: *Under Gols* e queda brusca na contagem de escanteios no 2º tempo.

---

### 🟢 Cenário 3: "A Armadilha do Foco na Copa" (Rodadas Intermediárias)
* **Confronto Exemplo**: *Time A (4º lugar no campeonato)* vs *Time B (15º lugar)*.
* **Situação das Equipes**:
  * O **Time A** jogará 3 dias depois o jogo de volta da semifinal da Libertadores/Champions League.
  * O **Time B** está 100% focado no campeonato nacional.
* **Mudança Comportamental**:
  * O treinador do Time A escala um time misto/reserva. Mesmo se titulares entrarem no 2º tempo, jogarão com ritmo controlado para evitar desgaste.
* **Impacto nos Mercados**:
  * 🛑 **Handicap Asiático**: A posição do Time A (4º) gera um **falso favoritismo**. O valor preditivo (EV+) migra para o visitante (+0.5 ou +1.0 AH Time B).

---

### 🟢 Cenário 4: "O Derby da Sobrevivência / Jogo de 6 Pontos" (Rodada 30+)
* **Confronto Exemplo**: *Time A (17º lugar, 35 pts)* vs *Time B (16º lugar, 36 pts)*.
* **Situação das Equipes**:
  * Confronto direto na borda da zona de rebaixamento.
* **Mudança Comportamental**:
  * Tensão extrema, reclamações constantes com o árbitro, paralisações frequentes para VAR e "cera" de tempo.
* **Impacto nos Mercados**:
  * 🟨 **Cartões**: Explosão no volume de cartões amarelos e alta probabilidade de **Cartão Vermelho**.
  * ⏱️ **Acréscimos**: Partidas com 8 a 12 minutos de acréscimo por tempo, influenciando escanteios e gols tardios.

---

### 🟢 Cenário 5: "A Busca Desesperada por Saldo de Gols" (Rodada Penúltima)
* **Confronto Exemplo**: *Líder (1º lugar, 78 pts)* vs *Lanterninha (20º lugar - rebaixado)*.
* **Situação das Equipes**:
  * O Líder empata em pontos com o 2º colocado e a disputa do título será decidida pelo **Saldo de Gols**.
* **Mudança Comportamental**:
  * Mesmo vencendo por 3x0 aos 80 minutos, o líder mantém substituições ofensivas para buscar o 5x0 ou 6x0.
* **Impacto nos Mercados**:
  * ⚽ **Over Gols / Cantos**: Mantém alta taxa de finalizações e escanteios até o apito final.

---

## 3. Matriz de Decisão por Mercado no FootballWeb

| Fator de Tabela / Contexto | Indicador no Banco (`fixtures_trends`) | Ajuste Algorítmico Recomendado |
| :--- | :--- | :--- |
| **Confronto Z4 na reta final** | `home_zone` ou `away_zone` = 'Relegation' + `standings_motivation_score >= 3.5` | ⬆️ Aumentar expectativa de cartões em **+1.5 xC** |
| **Discrepância de PPG** | `abs(home_ppg - away_ppg) >= 1.00` | ⬆️ Ajustar linha de Handicap Asiático a favor do maior PPG |
| **Derby de Proximidade (<= 3 posições)** | `abs(home_rank - away_rank) <= 3` + Rodada > 25 | ⬆️ Elevar índice de rigor e tensão na partida |
| **Aproveitamento Mandante/Visitante** | `home_rank` vs `away_rank` | 🛡️ Aplicar trava de segurança em favoritos fora de casa se `away_ppg < 1.0` |

---

## 4. Estrutura de Ingestão e Dados no Sistema

No **FootballWeb**, esses dados são ingeridos automaticamente da **API-Football (`/standings`)** e armazenados no MySQL:

* **Tabela MySQL**: [`fixtures_trends`](file:///root/datalake-air-flow-delta/mysql-init/14-create_football_tables.sql)
  * `home_rank` / `away_rank`: Posições na tabela.
  * `home_ppg` / `away_ppg`: Pontos por jogo.
  * `home_zone` / `away_zone`: Zonas da tabela (Ex: Libertadores, Rebaixamento).
  * `standings_motivation_score`: Fator composto de motivação (0.00 a 10.00).
* **Script de Ingestão**: [`scripts/football_ingest_trends.py`](file:///root/datalake-air-flow-delta/scripts/football_ingest_trends.py) (`enrich_fixtures_standings`)
* **Interface Web**: Exibição das pílulas `#rank` nos cards do [`dashboard.php`](file:///root/datalake-air-flow-delta/src/footballweb/app/Views/football/dashboard.php) e no dropdown de apostas em [`apostas/index.php`](file:///root/datalake-air-flow-delta/src/footballweb/app/Views/apostas/index.php).
