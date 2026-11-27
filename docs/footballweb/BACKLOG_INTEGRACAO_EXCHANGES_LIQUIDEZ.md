# Backlog Arquitetural: Integração de Bookmakers Globais e Exchanges de Liquidez Contínua

Este documento formaliza o planejamento estratégico e a memória técnica para desacoplar o sistema FootballWeb da dependência exclusiva da Trading API da Cloudbet, viabilizando fontes de liquidez que não praticam bloqueio preventivo de linhas principais (*SELECTION_DISABLED* no pré-jogo).

---

## 1. Motivação e Diagnóstico Técnico
- **Problema Observado na Cloudbet**:
  - A Cloudbet adota uma política de risco ultra-defensiva para proteger seus livros em criptoativos. Em grande parte das partidas com alta liquidez (ex.: rodadas da AFC Champions League, Serie A Italiana, Premier League), as seleções de **Handicap Asiático** (`soccer.asian_handicap`), **Total de Gols** (`soccer.total_goals`) e **1X2** (`soccer.match_odds`) permanecem com status `SELECTION_DISABLED` e `price = 0` durante praticamente toda a janela pré-jogo.
  - Apenas mercados secundários de margem alta (escanteios, autores de gol, handicaps parciais de 1T) ou o submódulo de **In-Play Sniper** (15' a 35' do 1T) conseguem capturar ordens.
- **Impacto no Negócio**:
  - Queda substancial no volume operacional de apostas de valor esperado positivo (+EV) no pré-jogo.

---

## 2. Opções Estratégicas Mapeadas

### Opção 1 (Estratégia Imediata Ativa): Sniper In-Play Cloudbet
- **Foco**: Concentrar a captura nos minutos em que a Cloudbet é forçada pelo mercado a reabrir suas cotações ao vivo (janela dos 15' aos 35' do 1T em placar 0x0).
- **Status**: Implementado e operacional em `scripts/criar_apostas_cloudbet_diario.py`.

### Opção 3 (Backlog Estratégico de Longo Prazo): Provedores Alternativos de Liquidez Contínua

Para eliminar em definitivo o gargalo de bookmakers conservadores, estão mapeadas três arquiteturas complementares:

#### 1. Betfair Exchange API-NG (via Broker / OrbitX)
- **Modelo**: Bolsa de Apostas (Peer-to-Peer / Exchange).
- **Vantagens**:
  - Não existe "torneira fechada": as odds são formadas pelos próprios apostadores e formadores de mercado (*market makers*).
  - Linhas de Over/Under e Asian Handicap abertas 24h antes e ativas até os acréscimos.
  - Zero risco de banimento de conta ou limitação por ganhar (+EV).
- **Requisitos de Implementação**:
  - Conexão REST/JSON com a API-NG da Betfair ou credenciais de API Broker OrbitX/Piwi247.

#### 2. SX Bet (Smart Contract Betting Exchange em Arbitrum / Polygon)
- **Modelo**: Web3 Betting Exchange descentralizada com settlement on-chain em USDC/USDT.
- **Vantagens**:
  - Integração 100% nativa via API aberta (`@sx-bet/api`).
  - Sem restrição geográfica por VPN e sem congelamento arbitrário de linhas.
  - Liquidez permanente fornecida por formadores de mercado algorítmicos.

#### 3. Integração com Corretoras Asiáticas (Pinnacle / PS3838 / Asianconnect)
- **Modelo**: Bookmaker de Margem Reduzida e Altos Limites.
- **Vantagens**:
  - Referência global de formação de preço (a odd mais eficiente do planeta).
  - Linhas asiáticas abertas ininterruptamente com liquidez líquida.
- **Requisitos**:
  - Acesso via API de broker ou feed parceiro.

---

## 3. Roteiro de Implementação Futura
1. **Fase 1**: Módulo de Abstração Multicorretoras (`scripts/broker_interface.py`).
2. **Fase 2**: Implementação do adaptador para Betfair Exchange / SX Bet.
3. **Fase 3**: Roteador inteligente de melhor preço (dispara a ordem na corretora que estiver com a melhor cotação e com a linha habilitada no momento).

---

## 4. Agrupamento de Painéis na UX e Novo Relatório de Taxa de Hit & Liquidez das Ordens

> **Adicionado em**: 2026-11-27  
> **Objetivo de UX**: Desafogar a barra de navegação (Navbar) agrupando itens sob um menu dropdown **"Painéis & Gestão"** e criar o relatório visual de auditoria de liquidez da Cloudbet.

### 4.1 O Desafio de UX na Navbar Atual
* **Situação Atual**: A barra de navegação superior (`src/footballweb/app/Views/header.php`) acumula links diretos individuais:
  - *Home*, *Minhas Apostas*, *Ao Vivo*, *Extrato & Conta Corrente*, *Metas Diárias*, *Criptomoedas*, *Eficiência dos Palpites*, *Abstenções (NO_BET)* e *Docs*.
* **Impacto**: O menu quebra em resoluções intermediárias e polui a interface visual.

### 4.2 Proposta de Reestruturação da Navbar (Dropdown Unificado)

A barra principal será simplificada para conter apenas as rotas-mestre, transferindo todos os relatórios analíticos para um menu dropdown:

```text
BARRA PRINCIPAL:
[ 🏠 Home ]  [ 🎫 Minhas Apostas ]  [ ⚡ Ao Vivo (LIVE) ]  [ 📊 Painéis & Gestão ▾ ]  [ ❓ Docs ]

ESTRUTURA DO DROPDOWN "📊 Painéis & Gestão":
├── 💰 GESTÃO FINANCEIRA & BANCA
│   ├── 💳 Extrato & Conta Corrente       ➔ /apostas/extrato
│   ├── 🎯 Metas Diárias & Ciclos         ➔ /metas
│   └── 🪙 Configuração de Criptomoedas   ➔ /moedas
│
├── 📈 INTELIGÊNCIA & DESEMPENHO ESPORTIVO
│   ├── 🚀 Análise de Desempenho Geral    ➔ /apostas/analise-desempenho
│   ├── 🎯 Eficiência dos Palpites (+EV)  ➔ /apostas/relatorio-eficiencia
│   └── 🛡️ Auditoria de Abstenções (NO_BET) ➔ /apostas/relatorio-abstencoes
│
└── 🤖 OPERAÇÃO & LIQUIDEZ CLOUDBET (NOVO)
    └── ⚡ Taxa de Hit & Liquidez das Ordens ➔ /apostas/relatorio-liquidez-cloudbet
```

### 4.3 Especificação da Tela: Relatório de Taxa de Hit & Liquidez Cloudbet

Esta nova interface (`/apostas/relatorio-liquidez-cloudbet`) trará transparência total sobre como a casa opera contra nossos algoritmos:

#### A) KPIs de Liquidez e Conversão de Ordens
1. **Total de Ordens Submetidas**: Volume de tentativas de envio pré-jogo e in-play.
2. **Taxa de Hit de Liquidez (Conversão de Ordens)**: Percentual de bilhetes efetivamente comprados na Cloudbet (~12% a 15% no pré-jogo).
3. **Taxa de Hit Fail por Linhas Suspensas / Fechadas**: Percentual de ordens barradas pela corretora (~42%).
4. **Taxa de Hit Fail por Odd Derretida**: Percentual de ordens canceladas por queda rápida de cotação (~42%).
5. **Taxa de Resgate In-Play**: Quantidade de entradas que falharam no pré-jogo por falta de liquidez mas foram convertidas com sucesso ao vivo até os 45' do 1º tempo.

#### B) Painel de Assertividade Esportiva por Esteira (Cloudbet)
- **Handicap Asiático & DNB**: Total, Green, Red, Push, Taxa de Acerto (62,5%), Lucro Líquido (+4,84 USDT) e ROI (+59,8%).
- **Gols Asiáticos (Over/Under)**: Total, Green, Red, Taxa de Acerto (60,0%), Lucro Líquido (+0,60 USDT) e ROI (+6,0%).
- **Total de Cartões (Under)**: Total, Green, Red, Taxa de Acerto (44,4%), Lucro Líquido (-0,82 USDT) e ROI (-4,6%).

#### C) Tabela Analítica de Eventos e Bloqueios
Alimentada por `pipeline_lifecycle_logs`, detalhando:
- Data/Hora;
- Partida e Competição (ex: *Sparta Praha vs AZ Alkmaar*);
- Mercado e Linha (ex: `AZ Alkmaar 0.0 AH @ 1.79`);
- Status da Execução:
  - 🟢 *Comprada*;
  - 🟡 *Aguardando Reabertura no Ao Vivo*;
  - 🔴 *Mercado Fechado / Sem Liquidez*;
  - 📉 *Odd Derretida*;
- Parecer Técnico do Robô.

### 4.4 Roteiro de Implementação
1. **Frontend (Navbar)**: Atualizar `src/footballweb/app/Views/header.php` para encapsular os relatórios no dropdown Bootstrap 5.
2. **Rotas**: Adicionar rota `$routes->get('/apostas/relatorio-liquidez-cloudbet', 'ApostaController::relatorioLiquidezCloudbet');` em `src/footballweb/app/Config/Routes.php`.
3. **Controller**: Criar o método `relatorioLiquidezCloudbet` em `src/footballweb/app/Controllers/ApostaController.php` consultando `apostas` e `pipeline_lifecycle_logs`.
4. **View**: Criar `src/footballweb/app/Views/apostas/relatorio_liquidez_cloudbet.php` com tema escuro elegante, cards de KPI e tabela filtrável.

