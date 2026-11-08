# Prisma da Aposta PREMIUM: O Modelo Conceitual da Tríade de Governança de Risco

Este documento formaliza o **Prisma da Aposta PREMIUM**, o modelo conceitual triangular adotado pela plataforma FootballWeb para qualificar, filtrar e autorizar operações com **alavancagem de 3x a stake padrão**.

---

## 1. Visão Geral e Princípio de Governança

No ecossistema de apostas automatizadas e predições com inteligência artificial, o **Valor Esperado Positivo (+EV) matemático não pode se sobrepor a critérios objetivos de campo**. 

Para proteger o patrimônio da banca contra ilusões estatísticas, distorções de mando de campo e desfalques de última hora, nenhuma aposta é classificada como **PREMIUM (destaque 2 / 3x Stake)** sem a **convergência simultânea de 3 vértices invioláveis**.

```text
                     [ V1: Poder Esportivo ]
                 Melhores Resultados Frente
                      a Grandes Oponentes
                             ▲
                            / \
                           /   \
                          /     \
                         /   👑  \
                        /  PREMIUM \
                       /  (3x Stake) \
                      /               \
                     /─────────────────\
[ V2: Matemática / Valor ]           [ V3: Campo e Notícias ]
   Odd Real >= Odd Justa               Elenco Titular Completo
        (+EV Líquido)                 (Sem Desfalques Críticos)
```

---

## 2. Detalhamento dos Vértices da Tríade

### Vértice 1 (V1): Melhores Resultados Frente a Grandes Oponentes *(Poder Esportivo Real)*
- **Conceito**: A forma recente da equipe nos últimos 5 jogos (U5J) deve ser testada e comprovada contra oponentes de alto calibre (*Strength of Schedule*).
- **Critério Objetivo**:
  - Pontuação de Eficiência Ponderada $U5J \ge 24.0\text{ pts}$ ou dominância comprovada de equipe Tier 1 de elite;
  - Histórico recente contendo confrontos diretos contra gigantes do continente ou clubes do topo da tabela;
  - Expurgo de pontuações infladas: pontos obtidos exclusivamente contra equipes frágeis ou em divisões inferiores não qualificam uma equipe para o selo PREMIUM.

### Vértice 2 (V2): Odd Real $\ge$ Odd Justa *(Valor Matemático e Alinhamento de Mercado)*
- **Conceito**: A cotação oferecida pelas casas oficiais (Betano / Pinnacle) deve conter margem matemática de valor (+EV) e convergência com o favoritismo de mercado.
- **Critério Objetivo**:
  - $Odd_{Real} \ge Odd_{Justa}$ com margem $+EV \ge 5.0\%$ (idealmente entre $+15\%$ e $+35\%$);
  - Faixa saudável de cotação: odds de entrada situadas entre **1.60 e 2.10**;
  - **Alinhamento Soberano do 1X2**: O mercado não pode tratar a equipe como azarão (odds abertas $> 2.40$ ou status de zebra fora de casa desqualificam a aposta imediatamente).
  - **Teto Máximo Universal de Risco: Limite Rígido de -0.75 AH**:
    - O sistema estabelece que o limite máximo absoluto de exposição para qualquer favorito (mesmo em Apostas Premium) é rigorosamente **-0.75 AH**. Linhas mais agressivas (-1.0, -1.25, -1.5 ou superiores) são permanentemente proibidas pelo sistema para blindar a banca contra o imponderável de vitórias simples (1x0, 2x1) e zebras.
    - Na linha **-0.75 AH**, a gestão de banca é blindada:
      - Vitória por 2+ gols: **100% de Lucro (Green Total)**;
      - Vitória simples por 1 gol: **50% de Lucro garantido + 50% de Reembolso da Stake (Meio-Green)**;
      - Empate ou derrota: Perda.
    - Para Apostas Premium (Super-Dominância Tier 1), as linhas autorizadas são **-0.5 AH** e **-0.75 AH**, com piso de odd a **1.50**.

### Vértice 3 (V3): Elenco Principal de Titulares Completo *(Auditoria Cognitiva da Sentinela)*
- **Conceito**: Confirmação fática do que efetivamente entrará em campo nos 90 minutos de jogo, auditada pela IA Sentinela em tempo real.
- **Critério Objetivo**:
  - Espinha dorsal preservada: goleiro titular absoluto, zagueiro líder, camisa 10/articulador e artilheiro principal confirmados;
  - **Rigor para Linhas Ofensivas (-0.5 e -0.75 AH)**: O ataque titular habitual (artilheiro principal e meias criadores) deve estar 100% confirmado na súmula oficial. Qualquer poupança no ataque rebaixa a partida para ALTO RISCO e decreta abstenção preventiva;
  - Ausência de alertas de sinistro (`alerta_sinistro: false`): sem poupança de titulares por foco em outras competições (copas continentais) e sem crise interna de vestiário;
  - Noticiário pré-jogo auditado a menos de 6 horas do apito inicial.

---

## 3. Matriz de Estresse: O que acontece se faltar 1 vértice?

A regra de ouro da governança é a **não-compensação**: a excelência em dois vértices não perdoa a ausência do terceiro.

| Vértices Atendidos | Vértice Ausente | Diagnóstico de Risco no Mundo Real | Decisão do Sistema |
| :--- | :--- | :--- | :--- |
| **V1 + V2** | **Falta V3** *(Desfalques)* | A equipe é superior e a cotação parece justa, mas o artilheiro e o meia de criação foram poupados. | 🚨 **Armadilha de Escalação**<br>*(Veto por Sinistro / `NO_BET`)* |
| **V1 + V3** | **Falta V2** *(Sem Valor)* | O time titular está voando contra gigantes, mas a casa paga migalhas (@ 1.15) ou esmagou a linha. | 🚫 **Aposta sem Margem**<br>*(Sem +EV / Rejeição)* |
| **V2 + V3** | **Falta V1** *(Sem Casca)* | A cotação é atraente e os titulares jogam, mas a pontuação veio apenas contra equipes fracas. | ⚠️ **Ilusão Estatística**<br>*(Caso Trap Game)* |
| **V1 + V2 + V3** | **Nenhum** | Equipe provou força contra gigantes, tem valor matemático real e vai com força máxima confirmada. | 👑 **APOSTA PREMIUM**<br>*(Alavancagem Autorizada: 3x Stake)* |

---

## 4. Estudo de Caso Emblemático: Partida Kortrijk vs KV Mechelen

A partida **Kortrijk vs KV Mechelen** (04/11/2026 - Liga Belga) serviu como marco de validação do Prisma:

- **A Ilusão do Gráfico**: O visitante KV Mechelen exibia **17.9 pts de eficiência U5J** contra apenas **2.8 pts** do mandante Kortrijk (mais de 6 vezes superior).
- **A Checagem do Prisma**:
  1. **Falha no V1 (Contexto de Confronto)**: Os 17.9 pts do Mechelen foram construídos **100% em seu estádio**. Fora de casa, o time não havia vencido nenhum jogo recente.
  2. **Falha no V2 (Mercado e Valor)**: A Betano precificou o Kortrijk como favorito (@ 2.47) e o Mechelen como azarão (@ 2.87). O mandante estava afundado na zona de rebaixamento (17º colocado) jogando a sobrevivência da temporada em casa (*Trava de Sobrevivência do Mandante*).
- **Conclusão**: O confronto configurava uma **Armadilha Clássica de Apostas (*Trap Game*)**. Colocar 3x a stake da banca em um visitante cotado a 2.87 que não vence fora de casa representaria alto risco de ruína. O Gatekeeper agiu com rigor e decretou **`NO_BET`**.

---

## 5. Diretrizes Operacionais no Código

1. **`asian_handicap_engine.py`**:
   - Para receber `destaque = 2` e categoria `Oportunidade PREMIUM (+EV Alavancado)`, a aposta precisa ser aprovada no crivo das travas objetivas de risco do Gatekeeper e chancelada pela auditoria cognitiva da Sentinela com `is_premium: true`.
   - É expressamente proibido promover a PREMIUM apostas de equipes tratadas como azarões pelas casas de apostas ($Odd_{1X2} \ge 2.20$).
2. **`ApostaController.php` / Dashboard**:
   - Apenas apostas que cumpram integralmente a tríade recebem a coroa visual `👑 ⭐ Aposta PREMIUM` e dimensionam o saldo em 3x a stake padrão parametrizada na gestão de banca do usuário.

