# Prisma da Aposta QUASE PREMIUM: Governança para Entradas Selecionadas (1x Stake)

Este documento formaliza o **Prisma da Aposta Quase Premium** (ou *Aposta Selecionada / Forte +EV*), o modelo conceitual triangular da plataforma FootballWeb que define a régua de corte operacional para autorizar apostas na esteira padrão (**1x a stake parametrizada**).

---

## 1. Visão Geral e Princípio de Governança

Enquanto o **Prisma PREMIUM (3x Stake)** é o topo absoluto da pirâmide (reservado a cenários de dominância de elite com convergência máxima), a **Aposta Quase Premium (1x Stake)** representa o motor de volume seguro da banca profissional.

Para que uma aposta seja gerada, notificada e acompanhada no sistema como oportunidade ativa (avançando no ciclo de vida até o status de aguardando súmula oficial), ela **DEVE OBRIGATORIAMENTE preencher os 3 vértices do Prisma Quase Premium**. 

Qualquer partida que não atenda integralmente a esta tríade é compulsoriamente classificada como **`NO_BET` (Sem Entrada / Abstenção Mandatória)**, impedindo que apostas ativas ou notificações sejam disparadas aos apostadores.

```text
                  [ V1: Consistência Contextual ]
                 Desempenho Comprovado no Cenário
                    (Mando de Campo Validado)
                              ▲
                             / \
                            /   \
                           /     \
                          /   🛡️  \
                         /  QUASE  \
                        /  PREMIUM  \
                       / (1x Stake)  \
                      /               \
                     /─────────────────\
[ V2: Cobertura de Empate ]          [ V3: Espinha Dorsal ]
 Linha 0.0 AH (DNB) ou +/-0.25 AH      Goleiro + Artilheiro Titulares
  Odd Saudável (1.65 a 2.05)           (Núcleo Central sem Desfalque)
```

---

## 2. Os Três Vértices do Prisma Quase Premium

### Vértice 1 (V1): Consistência Contextual *(Mando de Campo Validado)*
- **Conceito**: Diferente do nível Premium, o time não precisa ter derrotado gigantes continentais (Tier 1), mas sua força estatística recente deve ser legítima no cenário em que atuará hoje.
- **Critérios Objetivos**:
  - Eficiência ponderada recente satisfatória ($U5J \ge 12.0\text{ pts}$);
  - **Dominância Relativa Mínima de 30% no U5J**: A equipe candidata deve obrigatoriamente possuir no mínimo **+30% de eficiência ponderada em relação ao adversário** ($Ef_{candidato} \ge 1.30 \times Ef_{adversario}$). Confrontos com distância percentual inferior a 30% (como 11.2 pts vs 9.0 pts, onde a diferença é de apenas 24% e 2.2 pts brutos) caracterizam equilíbrio esportivo e são desqualificados do selo Quase Premium;
  - **Validação Específica de Mando/Fora**: Se a equipe atua como **visitante**, é mandatório que tenha somado pontos e marcado gols fora de seus domínios nos jogos recentes. Pontuações infladas 100% em casa não qualificam um time para apostas como visitante;
  - **Ausência de Desespero Assimétrico**: O mandante não pode estar na zona de rebaixamento jogando a sobrevivência da temporada (*Trava de Sobrevivência do Mandante*).

### Vértice 2 (V2): Margem de Valor com Cobertura de Empate *(Gestão de Risco)*
- **Conceito**: Como o favoritismo não é de um gigante incontestável, a aposta exige linhas com margem de segurança contra empates imprevistos.
- **Critérios Objetivos**:
  - Linhas com proteção obrigatória: **`0.0 AH` (Empate Anula / DNB)** ou **`+0.25 AH / -0.25 AH`** (onde o empate preserva ou devolve metade do capital);
  - Margem matemática $+EV$ comprovada em cotações reais das bookmakers oficiais (Betano / Pinnacle);
  - Faixa saudável de odd de entrada: entre **1.65 e 2.05**;
  - **Alinhamento do 1X2**: O mercado não pode tratar a equipe como azarão evidente (odds abertas $> 2.40$ ou inversão injustificada desqualificam a aposta).

### Vértice 3 (V3): Espinha Dorsal Preservada *(Auditoria Sentinela no Núcleo)*
- **Conceito**: Diferente do nível Premium (que exige 100% dos 11 titulares intactos), o Quase Premium tolera ausências pontuais secundárias (como laterais ou volantes reservas), desde que o eixo decisivo esteja assegurado.
- **Critérios Objetivos**:
  - Presença confirmada do **Goleiro Titular Absoluto** e do **Artilheiro Principal / Camisa 9**;
  - Ausência de alertas de sinistro (`alerta_sinistro: false`): sem poupança em massa de titulares por foco em outras competições e sem crise de vestiário;
  - Motivação competitiva confirmada pela auditoria de notícias da Sentinela.

---

## 3. Matriz Comparativa: Da Abstenção ao Alavancado

| Categoria | Vértice 1 (Poder Esportivo) | Vértice 2 (Matemática e Mercado) | Vértice 3 (Campo e Escalação) | Status no Sistema | Notificação & Ação da Banca |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **👑 PREMIUM** | Dominância contra gigantes ($U5J \ge 24\text{ pts}$) | Favorito claro no 1X2 + Linha agressiva com $+EV$ | 100% do time titular completo confirmado | Aposta Ativa ➔ Aguardando Súmula Oficial | 🔔 Notificação Premium<br>💰 **3x Stake Padrão** |
| **🛡️ QUASE PREMIUM** | Consistência contextual ($U5J \ge 12\text{ pts}$, mando validado) | Linha com **cobertura de empate (0.0 ou $\pm0.25$ AH)** @ 1.65–2.05 | Eixo central (Goleiro + Artilheiro) confirmado | Aposta Ativa ➔ Aguardando Súmula Oficial | 🔔 Notificação Padrão<br>💵 **1x Stake Padrão** |
| **⛔ NO_BET (Abstenção)** | Ilusão estatística (só ganha em casa) ou Z4 desesperado | Mercado trata como zebra (@ 2.87) ou sem cobertura | Desfalque no gol, artilheiro fora ou time alternativo | **Sem Entrada (Abstenção)** | 🔕 **Sem Apostas / Sem Notificação** |

---

## 4. O Caso Kortrijk vs KV Mechelen sob o Crivo do Quase Premium

A partida **Kortrijk vs KV Mechelen** (04/11/2026 - Liga Belga) ilustra perfeitamente o corte do modelo:

1. **Tentativa de Enquadramento no Quase Premium**:
   - O gráfico indicava 17.9 pts (Mechelen) vs 2.8 pts (Kortrijk).
2. **Submissão aos Vértices**:
   - **V1 (Falhou)**: O Mechelen jogava fora, mas seus pontos eram 100% obtidos como mandante; o Kortrijk lutava pela vida no Z4 em seu estádio.
   - **V2 (Falhou)**: A Betano cotava o Mechelen a 2.87 (o mercado considerava o Kortrijk favorito a 2.47).
3. **Decisão do Sistema**:
   - Reprovado em dois vértices. O sistema **não gera aposta ativa**, não envia notificação de oportunidade e decreta **`NO_BET`**.

---

## 5. Ciclo de Vida dos Estados e Governança nos Pipelines

O fluxo operacional entre as DAGs do Airflow respeita rigorosamente esta esteira:

```text
[ Descoberta de Partidas ] (football_trends_ingestion_dag)
           │
           ▼
[ Avaliação dos Prismas ] (Gatekeeper & asian_handicap_engine.py)
   ├─ NÃO ATENDE (Falha em V1, V2 ou V3) ──► NO_BET (Abstenção) ──► Card informativo (Sem Aposta / Sem Notificação)
   │
   └─ ATENDE AO PRISMA (Premium ou Quase Premium)
           │
           ▼
[ Criação de Aposta Ativa ] (criar_apostas_handicap_diario_dag)
   ├─ Stake 3x (Se Premium) ou Stake 1x (Se Quase Premium)
   ├─ Notificação em Tempo Real (Sino + Toast + E-mail)
   │
   ▼
[ Validação Pré-Jogo ] (gemini_sentinel_dag)
   ├─ Se surgir desfalque crítico ──► Cancela aposta e notifica estorno
   │
   ▼
[ Partida em Andamento / Concluída ] (Status: Aguardando Súmula Oficial)
   │
   ▼
[ Liquidação Oficial ] (processar_apostas_encerradas_dag)
   └─ Consulta súmula definitiva ➔ Liquidação financeira (Ganha / Perdida / Anulada)
```

### Regras Mandatórias de Implementação Técnica
1. **Filtro Estrito de Entrada**: Nenhuma linha ou palpite que não atinja os requisitos mínimos do Prisma Quase Premium pode receber status de aposta pendente/ativa no banco de dados.
2. **Notificações Exclusivas**: As tabelas `notificacoes_usuario` e disparos de e-mail devem ser acionadas estritamente para apostas chanceladas pelos Prismas Premium ou Quase Premium.
3. **Imutabilidade e Transparência**: O motivo da classificação ou descarte deve constar de forma clara e limpa na coluna `fixtures_trends.ah_reasoning`, sempre preservando o histórico U5J (`|| U5J_DATA:`).

