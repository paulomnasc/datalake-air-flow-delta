# ⚽ Documentação Técnica: Processo de Criação de Palpites de Handicap Asiático (AH)

Esta documentação detalha a arquitetura, formulação matemática, regras estatísticas e ciclo operacional empregados na geração, validação e recomendação de palpites e apostas no mercado de **Handicap Asiático (Asian Handicap - AH)** na plataforma **FootballWeb**.

---

## 1. Visão Geral e Princípios Fundamentais

Historicamente, muitos sistemas de apostas operavam baseando-se em regras puramente heurísticas (por exemplo, "se a diferença de gols esperados for maior que 0.5, sugere -0.5"). No entanto, apostar em linhas fixas sem avaliar a cotação real da casa gera expectativa matemática negativa no longo prazo.

Para blindar a banca e espelhar a mesma disciplina matemática consagrada no mercado de Cartões, o processo de criação de palpites de Handicap Asiático adota quatro princípios inegociáveis:

1. **Modelagem Bivariada de Poisson**: Não se assume um resultado fixo; calcula-se a distribuição conjunta de probabilidade para todos os placares prováveis ($0 \times 0$ até $8 \times 8$).
2. **Varredura Completa de Linhas da Betano (Bookmaker ID 32)**: O sistema consulta via API todas as linhas ativas oferecidas pela Betano para o confronto (Bet ID 4: Asian Handicap), em vez de impor uma linha arbitrária.
3. **Dedução Analítica da Odd Justa (*Fair Odd*)**: Cada linha é precificada probabilisticamente, ponderando com precisão os cenários de *Green*, *Meio-Green*, *Push/Anulada*, *Meio-Red* e *Red*.
4. **Gatekeeper com Abstenção Mandatória (`NO_BET`)**: O sistema só recomenda ou cria aposta se a cotação oferecida pela Betano possuir **Valor Esperado Positivo real ($+EV\% \ge 5.0\%$)** e probabilidade de cobertura $\ge 48\%$. Se nenhuma linha da Betano apresentar margem matemática lucrativa, o sistema declara `NO_BET` e não arrisca a banca.

---

## 2. Fluxograma Operacional do Pipeline

```mermaid
flowchart TD
    A["Início: Pré-Jogo (fixtures_trends)"] --> B["1. Extração de Força: xG Home & xG Away<br/>Médias Móveis dos Times"]
    B --> C["2. Construção da Matriz Bivariada de Poisson<br/>P(i, j) para i, j ∈ [0..8]"]
    C --> D["3. Chamada API-Sports (Bookmaker 32 - Betano)<br/>Captura todas as linhas ativas de AH (Bet ID 4)"]
    
    D --> E{"Betano tem mercado de AH aberto?"}
    E -- Não --> F["🛡️ NO_BET: Mercado Indisponível na Betano"]
    E -- Sim --> G["4. Avaliação Analítica Linha a Linha<br/>P_win, P_half_win, P_push, P_half_loss, P_loss"]
    
    G --> H["5. Dedução da Odd Justa e EV%:<br/>Odd Justa analítica<br/>EV% = (Odd_Betano / Odd_Justa - 1) * 100"]
    H --> I{"Alguma linha atende:<br/>• EV% >= +5.0%<br/>• Prob Efetiva >= 48%<br/>• 1.40 <= Odd <= 2.30?"}
    
    I -- Sim --> J["🟢 APROVADO PELO GATEKEEPER<br/>Seleciona Linha de Maior EV%"]
    J --> K["Registra Aposta em apostas_diarias<br/>status_gatekeeper = 'APROVADO'<br/>Preenche odd_justa, probabilidade_poisson, ev_percentual"]
    
    I -- Não --> L["🛡️ NO_BET: Abstenção Mandatória<br/>Nenhuma linha tem +EV% suficiente"]
    L --> M["Cancela apostas pendentes anteriores da partida<br/>Registra motivo no log do Airflow"]
```

---

## 3. Formulação Matemática

### 3.1. Matriz Bivariada de Poisson (Expectativa de Gols)

A probabilidade conjunta de a equipe mandante marcar $i$ gols e a visitante marcar $j$ gols é calculada pelo produto das distribuições de Poisson independentes calibradas pelas expectativas de gols ($\lambda_{\text{home}} = xG_{\text{home}}$ e $\lambda_{\text{away}} = xG_{\text{away}}$):

$$P(G_{\text{home}} = i, G_{\text{away}} = j) = \frac{e^{-\lambda_{\text{home}}} \lambda_{\text{home}}^i}{i!} \times \frac{e^{-\lambda_{\text{away}}} \lambda_{\text{away}}^j}{j!}$$

A matriz é calculada para $i \in [0, 8]$ e $j \in [0, 8]$, cobrindo mais de $99,8\%$ da massa de probabilidade de qualquer partida de futebol.

---

### 3.2. Classificação dos Desfechos do Handicap Asiático

Para cada linha asiática $L$ avaliada (ex: $-0.25$, $-0.50$, $0.0$, $+0.25$, etc.) aplicada à equipe selecionada, define-se a diferença de gols ajustada para cada placar $(i, j)$:

$$\text{Ajuste} = (G_{\text{equipe}} - G_{\text{adversário}}) + L$$

Cada célula da matriz de Poisson tem sua probabilidade somada a um dos 5 compartimentos de desfecho:

| Diferença Ajustada ($\text{Ajuste}$) | Desfecho | Payoff Financeiro (Stake = $S$, Odd = $O$) | Probabilidade Acumulada |
| :--- | :--- | :--- | :--- |
| $\text{Ajuste} \ge +0.50$ | **Vitória Plena (Green)** | $+ (O - 1) \times S$ | $P_{\text{win}}$ |
| $\text{Ajuste} = +0.25$ | **Meio-Ganho (Half Win)** | $+ \frac{O - 1}{2} \times S$ | $P_{\text{half\_win}}$ |
| $\text{Ajuste} = 0.00$ | **Anulada / Devolvida (Push)** | $0$ (Stake devolvida integralmente) | $P_{\text{push}}$ |
| $\text{Ajuste} = -0.25$ | **Meio-Perda (Half Loss)** | $- 0.5 \times S$ (Metade da stake perdida) | $P_{\text{half\_loss}}$ |
| $\text{Ajuste} \le -0.50$ | **Derrota Plena (Red)** | $- 1.0 \times S$ (Stake perdida integralmente) | $P_{\text{loss}}$ |

---

### 3.3. Dedução Exata da Odd Justa (*Fair Odd*)

A **Odd Justa** ($O_{\text{justa}}$) é a cotação exata em que o Valor Esperado ($\mathbb{E}$) é nulo ($\mathbb{E}[\text{Payoff}] = 0$):

$$\mathbb{E}[\text{Payoff}] = P_{\text{win}} \cdot (O - 1) + P_{\text{half\_win}} \cdot \frac{O - 1}{2} + P_{\text{push}} \cdot 0 - P_{\text{half\_loss}} \cdot 0.5 - P_{\text{loss}} \cdot 1 = 0$$

Sabendo que a soma total das probabilidades é unitária ($P_{\text{win}} + P_{\text{half\_win}} + P_{\text{push}} + P_{\text{half\_loss}} + P_{\text{loss}} = 1.0$), substituímos $P_{\text{loss}}$:

$$P_{\text{loss}} = 1.0 - P_{\text{win}} - P_{\text{half\_win}} - P_{\text{push}} - P_{\text{half\_loss}}$$

Substituindo e isolando $O_{\text{justa}}$, chegamos à equação canônica fechada:

$$O_{\text{justa}} = \frac{1.0 - \left(\frac{P_{\text{half\_win}}}{2} + P_{\text{push}} + 0.5 \times P_{\text{half\_loss}}\right)}{P_{\text{win}} + \frac{P_{\text{half\_win}}}{2}}$$

---

### 3.4. Probabilidade Efetiva e Valor Esperado ($+EV\%$)

Com a Odd Justa analítica calculada:

1. **Probabilidade Efetiva de Cobertura ($P_{\text{eff}}$)**:
   $$P_{\text{eff}} = \frac{100.0}{O_{\text{justa}}}$$

2. **Valor Esperado Percentual ($+EV\%$) contra a Odd Real da Betano ($O_{\text{betano}}$)**:
   $$+EV\% = \left(\frac{O_{\text{betano}}}{O_{\text{justa}}} - 1.0\right) \times 100$$

---

## 4. Consulta e Varredura da Matriz de Linhas Betano

### 4.1. Por que consultar todas as linhas da Betano?
No mercado de Handicap Asiático, as casas de apostas abrem múltiplas linhas secundárias com diferentes equilíbrios de risco/retorno para o mesmo jogo:
* Exemplo: `Mandante -0.75` @ 2.10, `Mandante -0.50` @ 1.82, `Mandante -0.25` @ 1.58, `Mandante 0.0` @ 1.35.

O script [`scripts/criar_apostas_handicap_diario.py`](file:///root/datalake-air-flow-delta/scripts/criar_apostas_handicap_diario.py) executa a função `fetch_all_betano_ah_lines(fixture_id)`:
1. Requisita o endpoint `/odds?fixture={id}&bookmaker=32` da API-Sports.
2. Filtra especificamente as apostas com `id: 4` (*Asian Handicap*).
3. Normaliza todas as linhas (ex: `Home -0.5`, `Away +0.25`, etc.) e extrai suas cotações ativas.

### 4.2. Janela Anti-Empate Padrão (Linhas Defensivas e de Cobertura)
Para confrontos normais e equilibrados, para blindar a banca contra empates tardios aos 90 minutos (como ocorria com `-0.25` resultando em meio-red ou `-0.50` em red integral), o sistema restringe a seleção padrão **estritamente às linhas onde o empate garante reembolso ou vitória**:
* **Janela Padrão Permitida**: `{0.0 (DNB), +0.50, +0.75, +1.00, +1.25, +1.50}`.
* **Linhas Negativas Comuns Banidas**: Linhas como `-0.25`, `-0.50`, `-0.75` são proibidas pelo Gatekeeper para partidas regulares.
* **Faixa de Odd Segura Padrão**: $1.30 \le O_{\text{betano}} \le 2.35$.
* **Crivo Mínimo de Probabilidade**: Probabilidade efetiva de cobertura $P_{\text{eff}} \ge 48.0\%$.

---

### 4.3. Exceção Canônica de Super-Favoritos Tier 1 (`TIER_1_ELITE_CLUBS`)

Clubes de elite mundial com abismo técnico sobre adversários frágeis tornam a linha defensiva `0.0 AH` inútil (com cotações irrisórias de 1.02 a 1.05) e possuem volume ofensivo para cumprir handicaps esticados. Para esses cenários, o pipeline implementa a **Exceção de Super-Favoritos Tier 1**.

#### 1. Catálogo Canônico Global (`scripts/leagues_config.py`)
Indexado pelo `team_id` oficial numérico da API-Sports / Banco de Dados (complexidade $O(1)$, imutável e à prova de homônimos textuais como Barcelona SC de Guayaquil vs FC Barcelona):
* **Espanha**: Real Madrid (`541`), Barcelona (`529`), Atlético Madrid (`530`).
* **Inglaterra**: Manchester City (`50`), Liverpool (`40`), Arsenal (`42`), Chelsea (`49`).
* **Alemanha**: Bayern Munich (`157`), Borussia Dortmund (`165`), Bayer Leverkusen (`168`).
* **França**: Paris Saint Germain (`85`).
* **Itália**: Inter (`505`), AC Milan (`489`), Juventus (`496`), Napoli (`492`).
* **Portugal**: Benfica (`211`), FC Porto (`212`), Sporting CP (`228`).
* **Holanda**: Ajax (`194`), PSV Eindhoven (`197`).
* **Brasil**: Flamengo (`127`), Palmeiras (`121`), Atlético Mineiro (`1062`).
* **Argentina**: Boca Juniors (`451`), River Plate (`435`).

#### 2. Gatilhos Matemáticos de Ativação da Exceção
A exceção só é ativada se **todos** os seguintes requisitos forem satisfeitos simultaneamente:
1. `is_tier_1_elite_club(team_id, team_name) == True`.
2. Cotação 1X2 esmagadora do favorito: $Odd_{\text{1X2}} \le 1.22$.
3. Assimetria extrema de mercado (Ratio de odds): $\frac{Odd_{\text{adversário}}}{Odd_{\text{favorito}}} \ge 8.0\times$.
4. Expectativa ofensiva elevada: $\lambda_{\text{favorito}} \ge 2.10$ xG e saldo projetado $\Delta G \ge +1.10$.

#### 3. Regras Específicas para Super-Favoritos Tier 1
* **Bloqueio Mandatório da Linha `0.0 AH` (DNB)**: Cotação sem valor esperado matemático.
* **Janela Negativa Exclusiva Autorizada**: $\{-1.0, -1.25, -1.5, -1.75, -2.0\}$ (foco prioritário em `-1.0` e `-1.5` AH).
* **Faixa de Odd Segura Calibrada**: $1.40 \le O_{\text{betano}} \le 2.25$.
* **Crivo Reforçado de Cobertura**: Probabilidade efetiva mínima exigida $P_{\text{eff}} \ge 52.0\%$ (contra os 48.0% padrão).
* **Isenção da Trava de Copas**: Super-favoritos com esses critérios permanecem autorizados mesmo em partidas eliminatórias mata-mata.

---

### 4.4. Travas Sistêmicas de Proteção de Banca (Gatekeeper Global)

Para evitar falsos positivos gerados por distorções momentâneas ou dados incompletos, o Gatekeeper impõe quatro travas estruturais globais:

1. **Trava de Mando Consagrado (Anti-Zebra em Caldeirões)**:
   * Se o mandante for favorito sólido ($Odd_{\text{Home}} \le 2.00$ e $Odd_{\text{Away}} \ge 3.80$ ou ratio de odds $\ge 2.0\times$), o sistema **bloqueia sumariamente qualquer entrada em handicap positivo ($+AH$) a favor da zebra visitante**.
2. **Trava de Time em Crise Severa (Anti-Zebra em Queda Livre)**:
   * Bloqueia qualquer linha a favor de equipe sem nenhuma vitória nos últimos 5 jogos ($0V$ no histórico recente U5J) em situação de desvantagem de mercado ($Odd \ge 2.20$ ou contra adversário com $Odd \le 2.10$).
3. **Trava de Coerência de Inversão de Handicap**:
   * A equipe favorita nas odds de mercado 1X2 nunca pode receber handicap positivo ($> 0.0$).
4. **Trava de Copas Eliminatórias (Cup Tournament Guard)**:
   * Em partidas eliminatórias de copas mata-mata (`is_cup`), bloqueia linhas a favor do visitante favorito para mitigar o risco imprevisível de times mistos/reservas (com isenção exclusiva para Super-Favoritos Tier 1 qualificados).
5. **Amostragem Completa Mandatória de 5 Jogos (U5J)**:
   * Exige rigorosamente que **ambas as equipes** tenham pelo menos 5 partidas consolidadas no histórico recente. Se qualquer uma possuir $< 5$ jogos (ex: início de temporada), o sistema decreta `NO_BET: Amostragem Insuficiente`.

---

### 4.5. Algoritmo de Seleção e Ranqueamento da Melhor Linha

Para cada linha capturada da Betano:
1. O modelo valida se a linha pertence à janela permitida (padrão ou exceção Tier 1).
2. Valida as travas de proteção (Mando Consagrado, Time em Crise, Trava de Copas, Amostragem U5J).
3. Avalia a linha contra a matriz bivariada de Poisson da partida, deduzindo a Odd Justa analítica e o $+EV\%$.
4. Descarta linhas fora da faixa de odds seguras ou abaixo do limiar de probabilidade efetiva ($48.0\%$ padrão / $52.0\%$ Tier 1).
5. Dentre as linhas aprovadas com $+EV\% \ge 5.0\%$, calcula o **Score de Valor**:
   $$\text{Score} = +EV\% \times \left(\frac{P_{\text{eff}}}{100.0}\right)$$
6. Seleciona a linha de maior pontuação. Se nenhuma linha for aprovada, declara abstenção obrigatória (`NO_BET`).

---

## 5. Política de Abstenção Mandatória (`NO_BET`)

Se a Betano precificar todas as linhas com margens pesadas (vig alto) de modo que nenhuma linha alcance $+EV\% \ge +5.0\%$ e os crivos de probabilidade e travas de segurança, o pipeline adota a conduta de **Abstenção Mandatória**:

* **Nenhuma aposta é criada** para o jogo.
* Se já existia uma aposta preliminar registrada em estado `Pendente`, o sistema atualiza seu status para `CANCELADA_GATEKEEPER`, evitando que ela permaneça aberta.
* Um log detalhado com a tag `🛡️ [Gatekeeper NO_BET]` é gravado no console do Apache Airflow e registrado no campo `ah_reasoning`.

### Exemplo Real de Proteção (07/09/2026)
* **Confronto**: Barracas Central vs Argentinos JRS
* **Linha Antiga (Heurística sem Poisson)**: Argentinos JRS -0.25 @ Odd 1.31
* **Resultado Real**: 0 a 0 (Meio-Red / Prejuízo de banca)
* **Avaliação pelo Novo Gatekeeper de Poisson**:
  * Odd Justa Analítica: `1.286`
  * Odd Real Betano: `1.310`
  * $+EV\%$ Calculado: `+1.87%`
  * **Decisão do Gatekeeper**: **`NO_BET` (Reprovado: $EV < 5.0\%$)**
* **Benefício**: A banca não realizou essa entrada, eliminando o prejuízo ocorrido.

---

## 6. Persistência de Dados e Auditoria

Todas as métricas analíticas calculadas são persistidas nas tabelas do banco de dados MySQL para permitir auditoria total e rastreabilidade:

### Tabela `apostas` / `apostas_diarias`
* `odd_justa` (`DECIMAL(5,2)`): Odd calculada matematicamente pela distribuição bivariada de Poisson.
* `probabilidade_poisson` (`DECIMAL(5,2)`): Probabilidade efetiva de cobertura da linha ($P_{\text{eff}} = \frac{100}{O_{\text{justa}}}$).
* `ev_percentual` (`DECIMAL(5,2)`): Margem de valor esperado da aposta frente à Betano.
* `status_gatekeeper` (`VARCHAR(30)`): `'APROVADO'` ou `'NO_BET'`.
* `criterios_atendidos` (`TEXT`): Resumo técnico dos critérios (ex: `Odd Real: 1.85 | Odd Justa: 1.62 | EV: +14.2% | Prob: 61.7%`).

### Tabela `fixtures_trends`
* `ah_pick` (`VARCHAR(100)`): Sugestão da linha (ex: `Flamengo -1.0 AH` ou `Palmeiras +0.5 AH`). Nulo caso seja `NO_BET`.
* `ah_fair_odd` (`DECIMAL(5,2)`): Odd justa calculada.
* `ah_ev_percent` (`DECIMAL(5,2)`): $+EV\%$ estimado.
* `ah_reasoning` (`TEXT`): Memória de cálculo completa registrando os $\lambda$ de ataque/defesa, matriz e justificativa estatística.

---

## 7. Scripts e DAGs Relacionadas

| Componente | Caminho | Função |
| :--- | :--- | :--- |
| **Catálogo de Ligas e Elite** | [`scripts/leagues_config.py`](file:///root/datalake-air-flow-delta/scripts/leagues_config.py) | Centraliza o escopo de ligas autorizadas e o catálogo `TIER_1_ELITE_CLUBS` por `team_id`. |
| **Engine de Handicap Asiático** | [`scripts/asian_handicap_engine.py`](file:///root/datalake-air-flow-delta/scripts/asian_handicap_engine.py) | Engine unificada: cálculo de Poisson, simulação e varredura Betano, Gatekeeper e regras de Tier 1. |
| **DAG de Criação** | [`src/dags/criar_apostas_handicap_dag.py`](file:///root/datalake-air-flow-delta/src/dags/criar_apostas_handicap_dag.py) | Orquestra a execução da criação horária de apostas de AH. |
| **Script de Criação** | [`scripts/criar_apostas_handicap_diario.py`](file:///root/datalake-air-flow-delta/scripts/criar_apostas_handicap_diario.py) | Realiza a varredura da API Betano, chamada à engine e cadastro das apostas aprovadas. |
| **Script de Ingestão** | [`scripts/football_ingest_trends.py`](file:///root/datalake-air-flow-delta/scripts/football_ingest_trends.py) | Atualiza tendências, xG e calcula sugestão preliminar de AH em `fixtures_trends`. |
| **DAG de Liquidação** | [`src/dags/processar_apostas_handicap_dag.py`](file:///root/datalake-air-flow-delta/src/dags/processar_apostas_handicap_dag.py) | Audita e liquida apostas de jogos finalizados (`FT`). |
| **Script de Liquidação** | [`scripts/processar_apostas_handicap_encerradas.py`](file:///root/datalake-air-flow-delta/scripts/processar_apostas_handicap_encerradas.py) | Avalia o placar final e liquida em: Ganha, Meio-Ganha, Anulada, Meio-Perdida ou Perdida. |
| **Controller Web** | [`src/footballweb/app/Controllers/ApostaController.php`](file:///root/datalake-air-flow-delta/src/footballweb/app/Controllers/ApostaController.php) | Gatekeeper em tempo real para visualização e criação manual de apostas no dashboard. |
| **View de Perdas** | [`src/footballweb/app/Views/apostas/relatorio_ia_perdas.php`](file:///root/datalake-air-flow-delta/src/footballweb/app/Views/apostas/relatorio_ia_perdas.php) | Dashboard de auditoria pós-jogo com métricas do Gatekeeper e placar real. |
