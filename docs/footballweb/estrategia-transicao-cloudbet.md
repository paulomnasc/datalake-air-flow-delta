# Estratégia de Transição e Operação Real na Cloudbet (100% Robot)

Este documento estabelece o protocolo técnico e operacional passo a passo para a transição segura do ambiente de simulação (*Paper Trading* com `CLOUDBET_DRY_RUN=true`) para a execução financeira real com micro-stakes (`CLOUDBET_DRY_RUN=false`), garantindo integridade de saldo, consistência de dados e ausência de falhas em produção.

---

## 1. Visão Geral da Arquitetura

O ecossistema de trading esportivo quantitativo foi totalmente desacoplado da Betano e opera de forma autônoma na **Cloudbet** através de quatro componentes canônicos:

```
[ Algoritmo / Gatekeeper (+EV) ]
               │
               ▼ (Chancela: PREMIUM ou QUASE PREMIUM)
[ scripts/cloudbet_matcher.py ] ──(Lê Odds Oficiais via Feed API)
               │
               ▼
[ scripts/criar_apostas_cloudbet_diario.py ]
               │
       ┌───────┴────────────────────────┐
       ▼ (Dry-Run: true)                ▼ (Dry-Run: false)
 [ Simulação Segura sim_xxxx ]    [ Ordem Oficial POST /pub/v3/bets/place ]
       │                                │
       └───────┬────────────────────────┘
               ▼
[ Gravação no MySQL (apostas) ] ──(Preserva integralmente || U5J_DATA:)
               │
               ▼
[ scripts/cloudbet_live_cashout.py ] ──(In-Play: Reta Final 76'+ / Seca 72'+)
               │
               ▼
[ Alerta Push Instantâneo ntfy.sh ] ──(1 Toque no Smartphone para Encerramento)
```

### Componentes em Produção
1. **[scripts/cloudbet_client.py](file:///root/datalake-air-flow-delta/scripts/cloudbet_client.py)**: Cliente oficial de trading. Gerencia a autenticação JWT (`X-API-Key`), o roteamento seguro pelo proxy SOCKS5 da Suíça (bypassa o bloqueio geográfico de Nuremberg), consultas de saldo, catálogo de eventos, envio de ordens (*Place Bet*) e bilhetes.
2. **[scripts/cloudbet_matcher.py](file:///root/datalake-air-flow-delta/scripts/cloudbet_matcher.py)**: Reconciliador de eventos. Cruza partidas do MySQL (`fixtures_trends`) com eventos da Cloudbet através de similaridade fonética e extrai cotações reais e líquidas de Handicap Asiático, Total de Gols e 1X2.
3. **[scripts/criar_apostas_cloudbet_diario.py](file:///root/datalake-air-flow-delta/scripts/criar_apostas_cloudbet_diario.py)**: Criador autônomo de apostas. Aplica os modelos de Poisson e U5J, filtra exclusivamente entradas **PREMIUM** e **QUASE PREMIUM**, emite a ordem e grava a aposta no MySQL na moeda `USDT`.
4. **[scripts/cloudbet_live_cashout.py](file:///root/datalake-air-flow-delta/scripts/cloudbet_live_cashout.py)**: Monitor heurístico in-play. Avalia o velocímetro de risco em tempo real e dispara alertas push no smartphone com link direto para encerramento antecipado.

---

## 2. Diferenciação de Identificadores (Fake vs Real)

Para garantir que o sistema não quebre ao alternar para o modo real:

| Parâmetro | Modo Simulação (`CLOUDBET_DRY_RUN=true`) | Modo Real (`CLOUDBET_DRY_RUN=false`) |
| :--- | :--- | :--- |
| **Envio para a Cloudbet** | **Não envia**. Ordem interceptada antes da rede. | Dispara requisição HTTP `POST /pub/v3/bets/place`. |
| **Identificador do Bilhete** | Gera identificador com prefixo de simulação: `sim_uuid` | Captura o **`referenceId` oficial (UUID)** emitido pela Cloudbet. |
| **Débito de Saldo** | **Zero**. Nenhum centavo é movimentado. | Debita exatamente a stake enviada (ex: `0.10 USDT`). |
| **Auditoria no Banco** | Coluna `resultado_detalhado` grava `{"is_simulated": true}` | Coluna `resultado_detalhado` grava `{"is_simulated": false, "referenceId": "..."}` |
| **Histórico na Cloudbet** | Não consta em *My Bets* na Cloudbet. | Consta ativo no histórico oficial da sua conta Cloudbet. |

> [!IMPORTANT]
> O código do cliente e do criador de apostas já foi testado contra a API de produção da Cloudbet. Ao detectar `CLOUDBET_DRY_RUN=false`, o sistema descarta qualquer lógica de simulação e utiliza estritamente o `referenceId` da transação confirmada pela Cloudbet.

---

## 3. Mecanismo Canônico de Hedge Real na Linha Oposta (Proibição Absoluta de Fatores Sintéticos)

Em cumprimento rigoroso à **Regra de Ouro 12 do AGENTS.md** (proibição de dados sintéticos, cotações fictícias e valores arbitrados), o sistema **NUNCA utiliza multiplicadores sintéticos** (como `fator_cashout = 0.40` ou estimativas inventadas).

A Cloudbet não possui um botão de `/cashout` na sua API pública. Portanto, a saída e neutralização ocorrem da forma profissional e matematicamente comprovada utilizada por fundos quantitativos: **o Hedge Real In-Play e Pré-Jogo através de uma segunda ordem real na ponta contrária**.

### Arquitetura Contábil dos 2 Bilhetes Reais:
Quando o gatilho de risco é ativado, o sistema **NÃO altera magicamente o bilhete original para um status sintético de Cashout**. Ele opera com total transparência contábil:

```
[ Aposta Original #A (ex: Dinamarca 0.0 AH @ 1.94) ] ──▶ Débito: -1.10 USDT
                        │
                        ▼ (Gatilho In-Play aos 90' ou Alerta Vermelho Pré-Jogo)
[ Ordem de Hedge Real #B (ex: Noruega 0.0 AH @ 1.94) ] ──▶ Débito: -1.10 USDT (via place_bet na API)
                        │
                        ▼ (Apito Final / FT: Dinamarca 2 x 3 Noruega)
┌───────────────────────┴────────────────────────┐
▼                                                ▼
Aposta Original #A: PERDIDA (Red)          Aposta de Hedge #B: GANHA (GREEN!)
(Nenhum crédito adicional)                 (Crédito Real: +2.13 USDT na conta_corrente)
```

### Como Funciona o Ciclo de Execução:
1. **Identificação de Risco**: O monitor [cloudbet_live_cashout.py](file:///root/datalake-air-flow-delta/scripts/cloudbet_live_cashout.py) avalia a partida a cada 3 minutos (via DAG `football_trends_live_dag`);
2. **Inversão Dinâmica de Mercado (`derive_opposite_market_url`)**:
   - `soccer.total_goals/over?total=2.5` ➔ Inverte para `soccer.total_goals/under?total=2.5`
   - `soccer.asian_handicap/home?handicap=-0.25` ➔ Inverte para `soccer.asian_handicap/away?handicap=-0.25`
3. **Consulta de Cotação Real no Livro de Ofertas (`get_live_opposite_quote`)**:
   - O robô consulta a odd e liquidez ativas da ponta contrária na Cloudbet naquele segundo;
4. **Disparo da Ordem de Hedge via Trading API**:
   - O robô chama `POST /pub/v3/bets/place` comprando a ponta oposta pelo proxy suíço;
5. **Criação do Segundo Bilhete Real no Banco de Dados (`apostas`)**:
   - Insere um novo registro com `tipo = 'Hedge Real'`, `palpite = 'Norway 0.0 AH'`, odd real do bookmaker e stake da cobertura;
   - Registra a transação de `DEBITO_APOSTA` na tabela `conta_corrente`;
   - O bilhete original armazena o vínculo cruzado `|| HEDGE_COBERTO_POR: #ID_HEDGE`;
6. **Liquidação Oficial no Apito Final (FT)**:
   - Ambas as apostas são liquidadas pelas regras oficiais do esporte pela DAG `processar_apostas_encerradas_dag`;
   - A aposta vencedora tem seu retorno financeiro (`CREDITO_RETORNO_APOSTA`) creditado integralmente no saldo do usuário;
   - **Zero fatores sintéticos, zero números inventados e 100% de integridade com a banca real.**

### Governança Multi-Casas (Cloudbet vs. Betano / Outras):
* **Na Cloudbet:** O Hedge é 100% autônomo, programático e executado via API.
* **Na Betano / Casas sem Trading API:** O robô **apenas dispara notificação push de alta prioridade via ntfy** com link direto da partida para que o operador decida. **É expressamente proibido arbitrar valores sintéticos ou alterar saldo no banco para casas sem API.**

---

## 4. Árbitro Tático Soberano Pré-Jogo: Grok IA & Blindagem contra Falsos Positivos

### O Princípio da Antecipação de Cotações (+EV)
No modelo quantitativo, aguardar a súmula oficial a 45 minutos do apito inicial para tentar comprar apostas destrói o retorno: o mercado profissional já absorve as notícias, as cotações derretem (*steam moves*) e quase tudo é reprovado pelo Gatekeeper por odd esmagada, gerando abstenção em massa (`NO_BET`).

Por isso, o robô **compra a aposta com antecedência (até 24h antes)**, cravando a cotação no ápice do valor esperado (ex: @ 1.95 antes de derreter para @ 1.62).

### O Papel do Grok IA como Árbitro de Súmula (T-45m a T-15m)
Para evitar que rotações normais de elenco gerem falsos positivos (como contadores estáticos de desfalques fariam), a esteira pré-jogo aciona o **Grok IA (`scripts/grok_tactical_auditor.py`)** assim que a súmula oficial de 11 titulares é disponibilizada pela API-Sports.

O Grok IA cruza a formação, goleiros e espinha dorsal **diretamente com a linha de Handicap apostada** e emite 3 vereditos padronizados:

```
Súmula Oficial Publicada (T-45 min)
              │
              ▼
    [ Grok IA Auditor ] (Prompt Canônico do Robozinho + openai/gpt-oss-120b)
              │
    ┌─────────┴───────────────────────────────────────┐
    ▼                                                 ▼
🟢 MANTER APOSTA ou 🟡 ATENÇÃO               🔴 ALERTA DE CASHOUT IMEDIATO
    │                                                 │
    ▼                                                 ▼
Preserva a aposta comprada no topo          Aciona Hedge Pré-Jogo na Cloudbet
(Zero falso positivo / Segue pro jogo)      (Compra linha contrária via Trading API)
```

* **Cache Automático no Sininho:** A análise tática completa em Markdown é gravada na coluna `notificacoes_usuario.analise_ia`. Quando o operador clica no botão do "Robozinho" na plataforma web, o relatório tático do Grok já abre instantaneamente na tela (`cached: true`), sem nenhum tempo de espera.

---

## 5. Roteiro Passo a Passo para Viabilizar a Virada (`CLOUDBET_DRY_RUN=false`)

O desenvolvedor/apostador deve seguir rigorosamente a sequência abaixo para ligar a operação real com risco mínimo:

### Passo 1: Aporte Mínimo de Teste na Conta Cloudbet
* Conectar a **NordVPN no navegador no IP dedicado de Zurique/Suíça** (`84.46.159.223`) antes de acessar `cloudbet.com`;
* Acessar a conta e gerar o endereço de depósito;
* Depositar um valor reduzido para testes: **5 a 10 USDT** (ou o equivalente em **Litecoin - LTC**, que possui taxas de transferência inferiores a R$ 0,10);
* Validar a chegada do saldo no terminal do servidor Hetzner:
  ```bash
  python3 scripts/cloudbet_client.py --status
  ```
  *O comando deve exibir o saldo em USDT disponível na conta.*

---

### Passo 2: Verificação da Calibração de Micro-Stake (0.10 USDT)
A Cloudbet aceita entradas a partir de **0.10 USDT** (`minStake: 0.1`). A calibração já foi efetuada e deve ser mantida:
* **No Banco de Dados**:
  ```sql
  SELECT id, usuario_id, moeda_codigo, stake_padrao 
  FROM metas_diarias_config 
  WHERE usuario_id = 558 AND is_ativa = 1;
  -- Deve retornar: stake_padrao = 0.10000000 | moeda_codigo = USDT
  ```
* **No Arquivo `src/footballweb/.env`**:
  ```ini
  CLOUDBET_CURRENCY=USDT
  CLOUDBET_DEFAULT_STAKE=0.10
  ```

---

### Passo 3: Execução de 1 Aposta Real de Teste Controlado (Canary Test)
Antes de ligar o robô contínuo, disparar **uma única aposta real** de 0.10 USDT via linha de comando para validar todo o circuito:

```bash
python3 scripts/criar_apostas_cloudbet_diario.py --fixture_id <ID_PARTIDA_ABERTA> --real
```

**Critérios de Sucesso desta Etapa:**
1. O terminal exibe: `🚀 [Cloudbet REAL ORDEM] Enviando aposta financeira real...`;
2. A Cloudbet responde com HTTP 200/201 e retorna o `referenceId` UUID;
3. A tabela `apostas` armazena o registro com `casa_de_aposta = 'Cloudbet'` e `moeda_codigo = 'USDT'`;
4. No site da Cloudbet (menu *My Bets*), o bilhete aparece ativo com stake de 0.10 USDT;
5. O saldo da Cloudbet cai exatamente 0.10 USDT.

---

### Passo 4: Auditoria no Painel de Observabilidade Live
* Acessar o painel web em `https://cristalbet.com.br/apostas/observabilidade`;
* Confirmar que a partida e o bilhete da Cloudbet aparecem listados nos cards ao vivo com:
  * Badge da casa: `Cloudbet`;
  * Moeda: `USDT`;
  * Stake: `0.10`;
  * Velocímetro de risco em tempo real.

---

### Passo 5: Virada de Chave Definitiva no `.env`
Após a validação bem-sucedida do Canary Test no Passo 3 e 4:
1. Abrir o arquivo canônico de variáveis de ambiente:
   ```bash
   nano src/footballweb/.env
   ```
2. Alterar a flag operacional:
   ```ini
   CLOUDBET_DRY_RUN=false
   ```
3. Salvar o arquivo. A partir deste instante, todas as apostas geradas automaticamente pelo robô serão executadas financeiramente na Cloudbet.

---

## 6. Procedimentos de Emergência e Salvaguardas (Kill Switch)

### Como Reverter Instantaneamente para Simulação
Se for detectada qualquer anomalia de mercado, latência de rede ou comportamento inesperado, o operador pode retornar para o modo de simulação em menos de 5 segundos:
```bash
sed -i 's/CLOUDBET_DRY_RUN=false/CLOUDBET_DRY_RUN=true/g' src/footballweb/.env
```
Isso congela imediatamente as chamadas de ordens financeiras e faz o robô voltar a operar em modo *Paper Trading*.

### Proteção contra Saldo Insuficiente
Se o saldo na carteira da Cloudbet for inferior a `0.10 USDT`, a Cloudbet rejeita a transação nativamente com `PENDING_ACCEPTANCE -> REJECTED`. O robô não insiste repetidamente, registrando a abstenção no log.

### Proteção de IP e Proxy Fail-Fast
Toda e qualquer chamada destinada aos domínios da Cloudbet é roteada obrigatoriamente através do proxy SOCKS5 da Suíça configurado em `CLOUDBET_PROXY_URL`. Se o proxy cair ou falhar na autenticação, a requisição sofre timeout e aborta, **nunca vazando o IP direto do servidor de Nuremberg**, protegendo a conta contra bloqueios regulatórios alemães.
