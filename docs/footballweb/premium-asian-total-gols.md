# Esteira Premium de Gols Asiáticos (Asian Total) e Modelagem Quantitativa Bivariada

Este documento estabelece a especificação técnica, fundamentação matemática, esteira de processamento e governança de risco do mercado de **Gols Asiáticos (Asian Total / Over & Under)** na plataforma FootballWeb.

---

## 1. Visão Geral e Princípios de Negócio

O mercado de **Gols Asiáticos (Asian Total)** é amplamente reconhecido como um dos mercados de maior liquidez e eficiência no ecossistema esportivo global. Diferentemente das linhas europeias tradicionais (*Over/Under 2.5* seco), as linhas asiáticas dividem-se em patamares que mitigam a variância e eliminam perdas binárias desnecessárias:

1. **Linhas Inteiras (`2.0`, `3.0`, `4.0`)**: Permitem o reembolso integral (*Push / ANULADA*) quando o total exato de gols iguala o valor da linha;
2. **Linhas de Quarto Inferior (`2.25`, `3.25`)**: Dividem o risco entre uma linha inteira e uma linha meia, proporcionando proteção de **Meio Reembolso / Meio Perdida** no Over e **Meio Ganho** no Under;
3. **Linhas de Quarto Superior (`1.75`, `2.75`)**: Dividem o risco entre uma linha meia e uma linha inteira, viabilizando **Meio Ganho** no Over e **Meio Reembolso / Meio Perdida** no Under;
4. **Linhas Meias (`1.5`, `2.5`, `3.5`)**: Definições puras de ganho ou perda de acordo com o total de gols da partida.

A criação desta esteira automatizada dota o FootballWeb de um terceiro pilar de precificação quantitativa de alto volume (junto a Handicap Asiático e Cartões), operando com rigorosa seleção de **Valor Esperado Positivo (+EV)** e proteção total contra geração de dados sintéticos.

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                 PIPELINE AUTÔNOMO DE GOLS ASIÁTICOS                         │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
        ┌──────────────────────────────┴──────────────────────────────┐
        ▼                                                             ▼
[ 1. Ingestão & Betano ]                                   [ 2. Modelagem xG & U5J ]
 Cotações Reais (API-Sports/Direct)                         Forma Recente dos 5 Jogos
 (Regra 12: Zero Linhas Fictícias)                          Ponderação Mandante/Visitante
        │                                                             │
        └──────────────────────────────┬──────────────────────────────┘
                                       │
                                       ▼
                       [ 3. Matriz de Poisson Bivariada ]
                        P(X=x, Y=y) para x,y em [0..9]
                        Projeção Exata de Total de Gols
                                       │
                                       ▼
                       [ 4. Gatekeeper Asian Total ]
                        • Sweet Spot de Odds: 1.65 a 2.25
                        • Valor Esperado (+EV >= 3.0%)
                        • Probabilidade Efetiva >= 52.0%
                        • Consistência Ofensiva/Defensiva U5J
                                       │
        ┌──────────────────────────────┴──────────────────────────────┐
        ▼                                                             ▼
[ APROVADO ]                                                   [ NO_BET ]
Inserção em `apostas`                                          Abstenção Mandatória
Notificação E-mail/Dashboard                                   Registro de Auditoria
        │
        ▼
[ 5. Sentinela Pré-Jogo (T-45m) ]
Auditoria de Lesões de Artilheiros / Goleiros / Drifts
        │
        ▼
[ 6. Liquidação Pós-Jogo (FT) ]
Ganha | Meio Ganha | ANULADA | Meio Perdida | Perdida
Crédito Idempotente em Conta Corrente
```

---

## 2. Modelagem Matemática Quantitativa

### 2.1 Projeção da Expectativa de Gols (xG) a partir do U5J
Para cada partida, o motor preditivo analisa os últimos 5 confrontos oficiais consolidados de ambas as equipes (`team_last5_cache`):
* **Potencial Ofensivo do Mandante ($xG_H$)**: Média aritmética entre os gols marcados pelo mandante em casa e os gols sofridos pelo visitante fora de casa, ajustada pelo fator de vantagem de mando ($+8\%$);
* **Potencial Ofensivo do Visitante ($xG_A$)**: Média aritmética entre os gols marcados pelo visitante fora e os gols sofridos pelo mandante em casa, com deflator de campo adverso ($-8\%$);
* **Trava de Teto por Amostragem**: Amortecimento de goleadas atípicas ($> 3$ gols) contra equipes sem divisão em copas locais, prevenindo distorções nos parâmetros de intensidade.

### 2.2 Matriz Bivariada de Poisson
A partir dos parâmetros $xG_H$ e $xG_A$, o motor computa a probabilidade de cada placar exato $(x, y)$ nos 90 minutos regulamentares:

$$P(X=x, Y=y) = \frac{e^{-xG_H} (xG_H)^x}{x!} \times \frac{e^{-xG_A} (xG_A)^y}{y!}$$

Com a matriz de probabilidades normalizada, a probabilidade marginal de que a partida termine com exatamente $K = x + y$ gols é obtida somando todos os pares de placar cuja soma resulta em $K$:

$$P(Total = K) = \sum_{x+y=K} P(X=x, Y=y)$$

---

## 3. Matriz Completa de Liquidação e Retornos por Linha

A tabela abaixo sintetiza rigorosamente o comportamento de cada tipo de linha de Gols Asiáticos processada pelo motor `settle_asian_total_bet`:

| Linha | Mercado | 0 Gols | 1 Gol | 2 Gols | 3 Gols | 4+ Gols |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **1.75** | **Over** | Perdida | Perdida | **Meio Ganha** | Ganha | Ganha |
| **1.75** | **Under** | Ganha | Ganha | **Meio Perdida** | Perdida | Perdida |
| **2.00** | **Over** | Perdida | Perdida | **ANULADA (Push)** | Ganha | Ganha |
| **2.00** | **Under** | Ganha | Ganha | **ANULADA (Push)** | Perdida | Perdida |
| **2.25** | **Over** | Perdida | Perdida | **Meio Perdida** | Ganha | Ganha |
| **2.25** | **Under** | Ganha | Ganha | **Meio Ganha** | Perdida | Perdida |
| **2.50** | **Over** | Perdida | Perdida | Perdida | Ganha | Ganha |
| **2.50** | **Under** | Ganha | Ganha | Ganha | Perdida | Perdida |
| **2.75** | **Over** | Perdida | Perdida | Perdida | **Meio Ganha** | Ganha |
| **2.75** | **Under** | Ganha | Ganha | Ganha | **Meio Perdida** | Perdida |
| **3.00** | **Over** | Perdida | Perdida | Perdida | **ANULADA (Push)** | Ganha |
| **3.00** | **Under** | Ganha | Ganha | Ganha | **ANULADA (Push)** | Perdida |

### Fórmulas Financeiras de Retorno por Unidade Apostada ($Stake$):
* **Ganha**: $Retorno = Stake \times Odd$;
* **Meio Ganha**: $Retorno = Stake \times \frac{Odd + 1.0}{2.0}$ (50% de lucro na cotação + 50% de reembolso da aposta);
* **ANULADA (Push)**: $Retorno = Stake \times 1.0$ (100% de reembolso do valor apostado);
* **Meio Perdida**: $Retorno = Stake \times 0.5$ (50% de reembolso do valor apostado e 50% perdido);
* **Perdida**: $Retorno = 0.0$ (100% de perda da stake).

---

## 4. O Gatekeeper de Gols Asiáticos (+EV e Gestão de Risco)

Toda linha disponibilizada na Betano é submetida a uma bateria quádrupla de critérios mandatórios antes de receber autorização de entrada:

1. **Faixa Sweet Spot de Cotações**:
   * Piso de odd: **1.65**;
   * Teto de odd: **2.25**;
   * Evita cotações deprimidas sem margem de segurança e apostas excessivamente esticadas de alta volatilidade.
2. **Valor Esperado Positivo (+EV)**:
   * Requer margem matemática de no mínimo **+3.0% de +EV** em relação à cotação justa teórica deduzida por Poisson.
3. **Probabilidade Efetiva de Sucesso**:
   * Exige probabilidade ponderada de sucesso de no mínimo **52.0%** (onde o meio-ganho recebe peso 0.5 e o push é tratado como evento neutro).
4. **Filtro de Coerência Tática U5J**:
   * **Entradas no Over (ex: $\ge 2.5$)**: Bloqueadas se a expectativa conjunta de gols ($xG_H + xG_A$) for inferior a $2.00$, ou se as equipes demonstrarem seca de gols recente;
   * **Entradas no Under (ex: $\le 2.25$)**: Bloqueadas se o confronto apresentar $xG$ combinado superior a $2.70$, prevenindo exposição contra ataques de alta volumetria.

---

## 5. Módulos e Componentes Arquiteturais

| Arquivo | Função / Responsabilidade |
| :--- | :--- |
| `scripts/asian_total_engine.py` | Motor matemático central: avaliação bivariada de linhas, cálculo de +EV e odd justa, Gatekeeper de Gols e liquidação oficial. |
| `scripts/criar_apostas_gols_diario.py` | Worker de criação diária de apostas: varre partidas abertas das ligas permitidas, consome a Betano, submete ao Gatekeeper e cadastra entradas na tabela `apostas`. |
| `src/dags/criar_apostas_gols_dag.py` | DAG Airflow agendada a cada 3 horas (`0 */3 * * *`), operando de forma autônoma com notificação por e-mail. |
| `scripts/processar_apostas_gols_encerradas.py` | Worker pós-jogo: processa partidas finalizadas (`FT`), calcula retornos e credita idempotentemente na `conta_corrente`. |
| `src/dags/processar_apostas_gols_dag.py` | DAG Airflow de liquidação contínua a cada 3 horas (`0 */3 * * *`). |
| `scripts/processar_apostas_encerradas.py` | Sincronização estrutural no processamento geral das 23:00 com suporte a linhas asiáticas fracionadas e inteiras. |
| `scripts/betano_direct_api.py` | Scraper oficial Betano: parsing nativo de mercados `goals_lines` (Total de Gols Asiático e Over/Under). |
| `scripts/gemini_sentinel.py` | Módulo de auditoria pré-jogo: monitoramento de odds extremas (< 1.50 ou > 3.00) e checagem de lesões de atacantes e goleiros. |
| `scripts/revalidar_fixture_gatekeeper.py` | Endpoint de revalidação sob demanda com isolamento estrito de canais (`stdout` limpo em JSON e logs no `stderr`). |
| `src/footballweb/app/Views/football/dashboard.php` | Visualização UX: modal moderno SweetAlert2 exibindo simultaneamente 1X2, Handicap Asiático, Cartões e Gols Asiáticos. |

---

## 6. Governança e Regras de Ouro Aplicadas

A esteira de Gols Asiáticos cumpre integralmente os princípios inegociáveis do repositório (`AGENTS.md`):

* **Regra 1 (Cache-First no MySQL)**: Histórico U5J e metadados sempre consultados primeiramente no banco local;
* **Regra 6 (Soluções Sistêmicas Globais)**: Motor agnóstico a times ou ligas, aplicando critérios idênticos e padronizados para todas as competições monitoradas;
* **Regra 10 (Proibição de Comandos Git Autônomos)**: Commits e pushes sob estrito controle manual do desenvolvedor;
* **Regra 12 (Proibição de Dados Sintéticos e Odds Fictícias)**: O sistema decreta compulsoriamente `NO_BET` se não houver cotações reais da Betano na API oficial; jamais interpola ou inventa linhas fictícias de gols;
* **Regra 13 (Linguagem Natural Clara)**: Justificativas geradas em formato legível e objetivo para o usuário, sem equações matemáticas brutas;
* **Regra 14 (Registro em Diário de Bordo)**: Todas as intervenções documentadas na Seção 6 do diário de bordo [2026-11-14.md](file:///root/datalake-air-flow-delta/docs/footballweb/diario-bordo/2026-11-14.md);
* **Regra 15 (Visibilidade Obrigatória de Falhas)**: Tratamento de exceções com logs transparentes e mecanismo *fail-fast*;
* **Regra 17 (Blindagem de UX e stdout Puro)**: Separação de logs técnicos (`stderr`) e dados estruturados (`stdout`), eliminando vazamentos de payloads na interface web;
* **Regra 18 (Zero Credenciais Hardcoded)**: Autenticação dinâmica de banco de dados exclusivamente através de `db_config.py` e `.env`.
