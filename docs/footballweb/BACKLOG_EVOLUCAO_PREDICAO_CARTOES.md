# Backlog de Evolução: Sistema de Predição e Gatekeeper de Cartões

> **Data de Criação:** 2026-08-21  
> **Status:** Ativo / Em acompanhamento  
> **Objetivo:** Registrar melhorias mapeadas para o sistema de palpites e validação financeira de apostas em cartões (Estratégia Under).

---

## 📌 Contexto & Problema Mapeado
Identificou-se uma alta taxa de abstenção em apostas de cartões pré-jogo. As casas de apostas (ex: Betano, Superbet) oferecem predominantemente a linha **Under 4.5 Cartões** no mercado principal pré-jogo, enquanto a trava rígida do Gatekeeper exigia linhas de **Under 5.5+** e os cards recomendavam apenas Under 6.5 ou 7.5.

---

## 📋 Lista de Iniciativas & Priorização

### 🟢 FASE 1: Implementação Imediata (Em Andamento / Aprovada)

#### 1. Flexibilização Condicional de `Under 4.5` no Gatekeeper
- **Descrição:** Permitir a aprovação de apostas na linha `Under 4.5` quando os critérios de alta segurança forem atingidos.
- **Regras:**
  - Expectativa de cartões da partida $xC \le 3.30$.
  - Probabilidade de Poisson acumulada $P(\text{Under 4.5}) \ge 75\%$.
  - Valor Esperado positivo ($EV > 0$).
- **Arquivos impactados:** `src/footballweb/app/Controllers/ApostaController.php`, `scripts/football_ingest_trends.py`.

#### 2. Normalização Neutra para Árbitros sem Histórico Real
- **Descrição:** Substituir o gerador MD5 com sorteio aleatório (`3.20` a `6.20` cartões) por uma média neutra padronizada (ex: `4.20` amarelos, `0.20` vermelhos, `24.0` faltas) quando a API não informar o árbitro ou quando o árbitro for novo.
- **Impacto:** Evita inflar artificialmente o $xC$ e forçar sugestões de linhas desnecessariamente distantes (Under 6.5/7.5).
- **Arquivos impactados:** `scripts/football_ingest_trends.py`.

#### 3. Módulo Bidirecional Over/Under no Gatekeeper de Cartões
- **Descrição:** Expandir o algoritmo para emitir e aprovar sugestões de `Over Cartões` (`Mais de 4.5` ou `Mais de 5.5`) quando a expectativa de cartões $xC \ge 5.30$ ou a média de amarelos do árbitro $> 5.20$.
- **Regras de Aprovação:**
  - Probabilidade de Poisson do Over $P(\text{Over X.5}) \ge 60.0\%$.
  - Eliminação da trava de `NO_BET` para partidas violentas/quentes.
- **Impacto:** Contorna o bloqueio da Betano em linhas de Under 6.5/7.5 e reduz a taxa de abstenção.
- **Arquivos impactados:** `scripts/football_ingest_trends.py`, `scripts/criar_apostas_cartoes_diario.py`, `src/footballweb/scratch/reavaliar_gatekeeper_banco.py`.

---

### 🟡 FASE 2: Backlog de Médio Prazo (Aguardando Desenvolvimento)

#### 3. Mercados de Under Cartões por Equipe (Mandante / Visitante)
- **Descrição:** Expandir o algoritmo para calcular a probabilidade de cartões individuais por equipe (ex: `Mandante Under 2.5 Cartões` ou `Visitante Under 2.5 Cartões`).
- **Justificativa:** As casas de apostas oferecem com frequência a linha de 2.5 cartões por equipe no pré-jogo com excelentes odds (1.45 - 1.70), abrindo um leque relevante de entradas de alto valor quando um dos times for extremamente disciplinado.

#### 4. Mercado de Under 2.5 Cartões no 1º Tempo (HT)
- **Descrição:** Desenvolver o módulo de projeção de cartões para o 1º Tempo ($xC_{\text{HT}}$).
- **Fundamento Estatístico:** Cerca de 30% a 35% dos cartões de uma partida ocorrem no 1º tempo. Em jogos com $xC = 4.8$, o $xC_{\text{HT}}$ fica próximo a 1.6 cartões, tornando a probabilidade de $P(\text{Under 2.5 HT}) > 78\%$.

#### 5. Ingestão de Histórico Real de Árbitros (Scraper / API Integration)
- **Descrição:** Criar rotina automatizada de ingestão de dados reais de arbitragem (via API-Football / Oddspedia / CBF) para popular a tabela `referee_stats` com a média real de cartões e faltas por árbitro e competição.

---

*Este documento deve ser consultado e atualizado a cada nova funcionalidade iniciada no sistema de cartões.*
