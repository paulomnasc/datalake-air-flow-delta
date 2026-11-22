# Guia de Liquidez de Mercado e Classificação de Ligas (FootballWeb)

Este documento consolida as diretrizes conceituais e operacionais sobre **Liquidez de Mercado**, a classificação das competições monitoradas no repositório (`ALLOWED_LEAGUES` em `scripts/leagues_config.py`) e os critérios de salvaguarda de banca em torneios de alta volatilidade.

---

## 1. O que é "Liquidez" no Contexto de Apostas e Modelos Preditivos?

No mercado financeiro e nas apostas esportivas, **liquidez** representa o **volume de capital circulante e a facilidade de realizar uma operação sem sofrer com distorções de preço, taxas abusivas ou recusa da casa**.

No pipeline FootballWeb, a liquidez divide-se em três pilares práticos:

### Pilar 1: Limite Máximo de Aposta e Aceitação
* **Alta Liquidez:** Milhões de pessoas e fundos de investimento apostam no mesmo evento (ex: Premier League, Champions League, Brasileirão Série A). As casas aceitam stakes expressivos (R$ 1.000 a R$ 20.000+) sem rejeição de bilhete e sem limitar a conta do apostador.
* **Baixa Liquidez:** As casas operam na defensiva devido a assimetria de informação ou risco de manipulação. Elas reduzem drasticamente o teto de aposta (muitas vezes limitando o bilhete entre R$ 30 e R$ 150).

### Pilar 2: Margem Oculta da Casa (Spread / Juice / Vig)
* **Alta Liquidez:** Pela forte concorrência global, as casas reduzem sua comissão para a faixa de **2% a 4%**. As cotações são justas e próximas da probabilidade real de campo.
* **Baixa Liquidez:** As casas se protegem embutindo margens pesadas de **8% a 15%**. As odds ficam artificialmente esmagadas, deteriorando o Valor Esperado Positivo (+EV) de longo prazo.

### Pilar 3: Disponibilidade e Estabilidade de Linhas (Conexão com o Sweet Spot)
* **Alta Liquidez:** As casas abrem dezenas de linhas de Handicap Asiático, Totais Fracionados e Cartões com **dias de antecedência**. Isso permite ao algoritmo selecionar a linha ideal perfeitamente encaixada no **Sweet Spot seguro de 1.60 a 1.95**.
* **Baixa Liquidez:** As casas não abrem linhas asiáticas com antecedência (frequentemente abrindo apenas 1 a 2 horas antes da partida) ou ofertam apenas linhas extremas e desbalanceadas.

---

## 2. Classificação de Liquidez do Catálogo `ALLOWED_LEAGUES`

Mapeamento completo das ligas ativas cadastradas em `scripts/leagues_config.py` de acordo com a liquidez nas principais casas de apostas (Betano, Bet365, Pinnacle):

### 🟢 Nível 1: Alta Liquidez (Elite Global & Nacional)
Mercados profundos, spreads reduzidos (2% a 4%), limites altos e linhas asiáticas abertas com vários dias de antecedência.

| ID API-Sports | Competição | País / Escopo |
| :---: | :--- | :--- |
| **39** | Premier League | Inglaterra 🏴󠁧󠁢󠁥󠁮󠁧󠁿 |
| **140** | La Liga | Espanha 🇪🇸 |
| **135** | Serie A | Itália 🇮🇹 |
| **78** | Bundesliga | Alemanha 🇩🇪 |
| **61** | Ligue 1 | França 🇫🇷 |
| **2** | UEFA Champions League | Europa 🇪🇺 |
| **3** | UEFA Europa League | Europa 🇪🇺 |
| **531** | UEFA Super Cup | Europa 🇪🇺 |
| **71** | Brasileirão Série A | Brasil 🇧🇷 |
| **13** | Copa Libertadores | América do Sul 🌎 |
| **1** | Copa do Mundo FIFA | Mundo 🌍 |
| **4** | UEFA Euro | Europa 🇪🇺 |
| **9** | Copa América | América do Sul 🌎 |

---

### 🟡 Nível 2: Média / Boa Liquidez (Ligas Comerciais Consolidadas)
Ligas competitivas de excelente padrão comercial. As casas abrem linhas asiáticas e de gols entre **24h e 48h antes da partida**, com limites satisfatórios e margens controladas (4% a 7%).

| ID API-Sports | Competição | País / Escopo |
| :---: | :--- | :--- |
| **72** | Brasileirão Série B | Brasil 🇧🇷 |
| **73** | Copa do Brasil | Brasil 🇧🇷 |
| **88** | Eredivisie | Holanda 🇳🇱 |
| **94** | Primeira Liga | Portugal 🇵🇹 |
| **144** | Pro League | Bélgica 🇧🇪 |
| **179** | Scottish Premiership | Escócia 🏴󠁧󠁢󠁳󠁣󠁴󠁿 *(destaque para Celtic e Rangers)* |
| **203** | Super Lig | Turquia 🇹🇷 |
| **119** | Superliga | Dinamarca 🇩🇰 |
| **218** | Bundesliga | Áustria 🇦🇹 |
| **207** | Super League | Suíça 🇨🇭 |
| **253** | Major League Soccer (MLS) | Estados Unidos 🇺🇸 |
| **262** | Liga MX | México 🇲🇽 |
| **128** | Primera Division | Argentina 🇦🇷 |
| **307** | Saudi Pro League | Arábia Saudita 🇸🇦 |
| **11** | Copa Sudamericana | América do Sul 🌎 |
| **15** | FIFA Club World Cup | Mundo 🌍 |
| **81** | DFB Pokal | Alemanha 🇩🇪 |
| **137** | Coppa Italia | Itália 🇮🇹 |
| **66** | Coupe de France | França 🇫🇷 |

---

### 🔴 Nível 3: Baixa Liquidez (Ligas Periféricas e Marginais)
Mercados mais rasos. As casas impõem limites rigorosos de stake, spreads mais altos (8% a 12%) e tendem a liberar linhas asiáticas e de cartões **apenas poucas horas antes do jogo**.

| ID API-Sports | Competição | País / Escopo |
| :---: | :--- | :--- |
| **268** | Primera Division | Uruguai 🇺🇾 |
| **265** | Primera Division | Chile 🇨🇱 |
| **239** | Primera Division | Colômbia 🇨🇴 |
| **130** | Copa Argentina | Argentina 🇦🇷 *(especialmente fases iniciais)* |
| **113** | Allsvenskan | Suécia 🇸🇪 |
| **103** | Eliteserien | Noruega 🇳🇴 |
| **106** | Ekstraklasa | Polônia 🇵🇱 |
| **345** | Czech First League | Tchéquia 🇨🇿 |
| **197** | Super League | Grécia 🇬🇷 *(exceto clássicos)* |
| **772** | Leagues Cup | América do Norte 🌎 |
| **16** | CONCACAF Champions Cup | CONCACAF 🌎 |
| **17** | AFC Champions League | Ásia 🌏 |
| **541** | Recopa Sudamericana | América do Sul 🌎 |
| **5** | UEFA Nations League | Europa 🇪🇺 *(Ligas C e D)* |

> 📌 **Por que mantemos ligas de Nível 3 em `ALLOWED_LEAGUES`?**  
> Porque, apesar da liquidez menor, essas ligas possuem **ampla base de dados estatísticos confiáveis** e os times de ponta oferecem oportunidades matemáticas reais. O cuidado operacional indispensável é monitorar o **tempo de abertura das odds**: na Betano, cotações completas dessas ligas costumam entrar no ar mais perto do apito inicial.

---

## 3. Estudo de Caso: Por que a CONCACAF Nations League é Problemática e Bloqueada?

A **CONCACAF Nations League** (Liga das Nações da América do Norte, Central e Caribe) **não faz parte** de `ALLOWED_LEAGUES` e é categorizada como de alto risco para esteiras automáticas. Os motivos centrais são:

1. **Abismo Técnico Extremo (Divisões A, B e C):**
   * O torneio mistura potências regionais (EUA, México, Canadá) com seleções de ilhas caribenhas quase semi-profissionais (Anguilla, Turks & Caicos, Sint Maarten, Bahamas).
   * Goleadas anômalas (6x0, 8x1, 10x0) destroem as projeções estatísticas de Poisson e tornam médias históricas imprevisíveis.

2. **Distorção de Odds e Fuga do Sweet Spot (1.60 a 1.95):**
   * As vitórias dos favoritos no mercado 1X2 pagam cotações insignificantes (1.02 a 1.12).
   * Para encontrar retorno, as casas esticam os handicaps para linhas perigosas como `-2.5`, `-3.5` ou `-4.5` gols. Qualquer acomodação do favorito no segundo tempo anula a aposta, violando a política de gestão de risco.

3. **Logística, Clima Tropical e Gramados Castigados:**
   * Viagens longas para a América Central e Caribe envolvem calor sufocante, alta umidade, tempestades sazonais e gramados precários. Favoritos frequentemente jogam no freio de mão para garantir placares magros (1x0 ou 2x0).

4. **Arbitragem e Catimba (Armadilha para Cartões):**
   * A arbitragem na CONCACAF tem histórico de permissividade física excessiva no primeiro tempo, seguida de descontrole com confusões, catimba extrema e expulsões no fim de jogo. Isso inviabiliza a previsibilidade do mercado de **Under Cartões**.

---

## 4. Diretriz Estratégica da Banca Cristal Bet

* **Foco no Sweet Spot:** 1.60 a 1.95 unificado em Gols e Handicap.
* **Alta Assertividade:** Priorização de mercados e ligas com liquidez sólida (Níveis 1 e 2) para garantir execução de ordens, spreads justos e linhas equilibradas.
* **Abstenção Mandatória (`NO_BET`):** Ausência de linhas líquidas nas casas de apostas oficiais aciona abstenção imediata para salvaguardar o patrimônio da banca.
