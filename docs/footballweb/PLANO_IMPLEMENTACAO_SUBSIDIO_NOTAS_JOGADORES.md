# Plano de Implementação: Subsídio por Notas de Rendimento de Jogadores (Player Ratings)

**Data de Elaboração:** 27 de Novembro de 2026  
**Módulo:** IA Sentinela & Governança de Súmulas Oficiais (FootballWeb)  
**Objetivo:** Eliminar em definitivo os falsos positivos de "time misto" e reversões táticas indevidas, fornecendo subsídio estatístico e qualitativo de desempenho individual dos atletas (Player Ratings / Notas de Rendimento) em qualquer liga do mundo.

---

## 1. Diagnóstico do Problema e Motivação

### 1.1 A Falha das Heurísticas Cegas Determinísticas
Atualmente, o pipeline utiliza contadores numéricos brutos para julgar desfalques e rodízio de elenco:
```python
if rot_h >= 5 or desf_h >= 4:
    time_desfalcado = home
elif rot_a >= 5 or desf_a >= 4:
    time_desfalcado = away
```
Essa lógica determinística falha por duas razões críticas:
1. **Contagem Cega do Departamento Médico (`desfalques >= 4`)**: Em grandes clubes europeus (como o Chelsea contra o Nottingham Forest), ter 4 ou 5 atletas listados no DM é a rotina da temporada (reservas, atletas em transição ou garotos da base). Tratar qualquer lista com 4 lesionados como "time misto" gera **falsos positivos grotescos**, mesmo quando a espinha dorsal de gala está 100% titular em campo (Cole Palmer, Moisés Caicedo, Pedro Neto, etc.).
2. **O Desafio das Ligas Periféricas e Jovens da Base**: Em ligas periféricas (Primera A Colombiana, Liga Saudita, Série B brasileira, ligas nórdicas) ou quando garotos recém-promovidos da base são escalados, a IA não pode depender apenas de "fama midiática". É indispensável ter uma métrica objetiva de rendimento técnico em campo (**nota média de desempenho**).

---

## 2. Fontes de Dados e Viabilidade Técnica

### 2.1 Fonte Primária: API-Sports (`v3.football.api-sports.io`)
A API-Sports já utilizada pelo projeto possui suporte nativo e oficial para notas e estatísticas individuais detalhadas por atleta:

1. **Endpoint `/fixtures/players?fixture={fixture_id}`**:
   - **Momento**: Partidas encerradas (`FT`).
   - **Retorno**: Estatísticas completas de todos os jogadores da partida:
     - `games.rating`: Nota decimal oficial do atleta no jogo (ex: `"7.4"`, `"6.9"`, `"6.6"`);
     - `games.minutes`: Minutos jogados na partida;
     - `games.position`: Posição tática em campo (`G`, `D`, `M`, `F`);
     - `games.captain` e `games.substitute`: Indicador de capitão e se começou no banco;
     - Ações de jogo: chutes, passes certos, desarmes, duelos vencidos e faltas.
2. **Endpoint `/players?team={team_id}&season={season}`**:
   - **Momento**: Carga periódica mensal ou sob demanda por time.
   - **Retorno**: O elenco completo (25 a 35 jogadores) com a média ponderada da temporada (`statistics[0].games.rating`, `statistics[0].games.lineups`, `statistics[0].goals.total`).

### 2.2 Fonte de Contingência Gratuita (Zero Cota): Sofascore Public Endpoints
- **Endpoint**: `https://api.sofascore.com/api/v1/event/{event_id}/lineups`
- **Vantagem**: Fornece exatamente as notas de rendimento consolidadas (as mesmas notas verdes/amarelas da interface pública do Sofascore) sem consumir 1 única chamada da cota da API-Sports.
- **Uso**: Contingência para partidas onde a cota da API-Sports estiver próxima do limite diário.

### 2.3 Grounding Cognitivo via Gemini Pro
- Para atletas estreantes da base sem amostragem prévia no banco, o Gemini Pro com Google Search Grounding avalia o histórico recente nas categorias de base ou última equipe para emitir o parecer contextual.

---

## 3. Análise de Impacto na Cota da API-Sports & Conformidade com Cache-First (Regra 1)

### 3.1 Status da Assinatura Atual
- **Plano**: `Pro`
- **Cota Diária**: **7.500 requisições/dia**
- **Validade**: Até 02/08/2027

### 3.2 Por que o Pré-Jogo Terá IMPACTO ZERO (0 Requisições Adicionais)
O FootballWeb opera sob a **Regra de Ouro 1 (Cache-First no MySQL)**. O enriquecimento da súmula no pré-jogo não fará requisições externas para notas:
1. As notas e médias dos atletas são pré-calculadas e armazenadas na tabela local `player_ratings_cache`.
2. No momento em que a súmula oficial é publicada (T-75 a T-15 minutos):
   - A rotina `gemini_sentinel.py` já efetua a busca padrão da escalação oficial (`/fixtures/lineups`);
   - Em seguida, **consulta exclusivamente o banco de dados local MySQL** via `SELECT rating_media, minutos_jogados FROM player_ratings_cache WHERE player_id IN (...)`;
   - **Resultado no Pré-Jogo**: **0 (ZERO) requisições adicionais à API-Sports**.

### 3.3 Consumo no Pós-Jogo (Ingestão Incremental)
- Apenas as partidas finalizadas (`FT`) das ligas monitoradas ativas no dia são enriquecidas com `/fixtures/players`.
- Em média, o sistema monitora entre **15 e 25 partidas por dia**.
- **Consumo Adicional**: ~20 chamadas/dia.
- **Impacto Percentual**: **0,26% da cota diária de 7.500 requisições** (impacto totalmente desprezível).

### 3.4 Travas de Salvaguarda de Cota (Fail-Safe)
1. **Trava de 80% (6.000 requisições)**: Se a cota diária atingir 80%, a atualização de ratings pós-jogo é automaticamente congelada até a virada do dia (00:00 UTC), preservando as notas já consolidadas no MySQL.
2. **Imutabilidade**: Partidas passadas já gravadas nunca são reconsultadas.

---

## 4. Modelagem Estrutural do Banco de Dados (MySQL)

Criação da tabela canônica de notas e performance de jogadores:

```sql
CREATE TABLE IF NOT EXISTS `player_ratings_cache` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT NOT NULL,
  `team_id` INT NOT NULL,
  `league_id` INT NOT NULL,
  `season` INT NOT NULL,
  `player_name` VARCHAR(150) NOT NULL,
  `position` ENUM('G', 'D', 'M', 'F') NOT NULL DEFAULT 'M',
  `rating_media` DECIMAL(3,2) NOT NULL DEFAULT 6.50,
  `rating_ultimos5` DECIMAL(3,2) NULL DEFAULT NULL,
  `minutos_jogados` INT NOT NULL DEFAULT 0,
  `jogos_titular` INT NOT NULL DEFAULT 0,
  `jogos_reserva` INT NOT NULL DEFAULT 0,
  `gols` INT NOT NULL DEFAULT 0,
  `assistencias` INT NOT NULL DEFAULT 0,
  `is_estreante_base` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_player_team_season` (`player_id`, `team_id`, `season`),
  KEY `idx_team_season` (`team_id`, `season`),
  KEY `idx_rating` (`rating_media`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Algoritmo de Avaliação da Força do Elenco pela Sentinela

### 5.1 Métricas Calculadas na Entrada da Súmula
Para cada time (Mandante e Visitante), a Sentinela calcula:
1. **Nota Média dos 11 Titulares**:
   $$\text{Rating XI} = \frac{1}{11} \sum_{i=1}^{11} \text{rating\_media}_i$$
2. **Presença dos Pilares de Alto Rendimento (Espinha Dorsal)**:
   - Atletas com $\text{rating\_media} \ge 7.00$;
   - Goleiro titular habitual (com mais minutos na temporada);
   - Artilheiro principal da equipe.
3. **Detecção de Estreantes / Atletas Vulneráveis**:
   - Atletas com menos de 90 minutos na temporada ou com nota inferior a $6.20$.

### 5.2 Matriz de Decisão da Sentinela (Substituição Definitiva de `rotacao >= 5`)

| Indicador Técnico | Condição Qualitativa | Diagnóstico da Sentinela | Ação no Pipeline |
| :--- | :--- | :--- | :--- |
| **Força Máxima / Elite** | $\text{Rating XI} \ge 6.80$, Goleiro Titular presente e pilares em campo | `CONFIRMADO: Titulares Confirmados (Força Máxima)` | ✅ **Sinal Verde Tático**. Proibição absoluta de alertas de time misto ou virada de palpite. |
| **Rotação Segura / Estável** | $6.50 \le \text{Rating XI} < 6.80$, pilares preservados, 2 ou 3 trocas normais | `CONFIRMADO: Equipe Competitiva (Rotação Normal)` | ✅ **Sinal Verde Tático**. Mantém palpite original. |
| **Queda Setorial Específica** | Ausência pontual de 1 jogador de nota alta, mas reposição com nota $\ge 6.60$ | `CONFIRMADO: Desfalque Pontual com Reposição de Nível` | ℹ️ Alerta consultivo leve sem cancelamento de aposta. |
| **Desfalque Estrutural Real** | Goleiro titular ausente COM reserva de nota $< 6.20$ **OU** perda simultânea de 2 pilares com $\text{Rating XI} < 6.30$ | `ALERTA_RISCO: Desfalque Estrutural no Elenco` | 🚫 **NO_BET Mandatório**. Bloqueia ou cancela aposta na equipe desfalcada para proteger a banca. |
| **Desmonte Total do Adversário** | O adversário poupou a espinha dorsal e seu $\text{Rating XI}$ caiu mais de $0.80$ pontos (ex: final de copa) | `OPORTUNIDADE: Desmonte Estrutural do Adversário` | ⚡ **Reset Tático por Súmula Autorizado** (se houver +EV comprovado). |

---

## 6. Passo a Passo da Implementação no Código

### Fase 1: Camada de Dados (MySQL)
1. Criar a tabela `player_ratings_cache` no MySQL do ambiente FootballWeb.
2. Adicionar métodos de leitura atômica em `scripts/db_config.py`.

### Fase 2: Ingestão Incremental Pós-Jogo (Airflow DAG)
1. No script `scripts/processar_apostas_encerradas.py`, após apurar o placar e cartões de partidas `FT`, capturar `/fixtures/players` e atualizar os ratings em `player_ratings_cache`.
2. Adicionar trava de cota diária ($< 80\%$) antes de disparar o enriquecimento.

### Fase 3: Reformulação da Sentinela (`scripts/gemini_sentinel.py`)
1. **Extirpação**: Remover as linhas determinísticas `if rot_h >= 5 or desf_h >= 4:` de `revisar_apostas_com_sumula_oficial` e de `coletar_sumula_e_desfalques_apisports`.
2. **Cruzamento de Ratings**: Na leitura de `match_lineups_cache`, enriquecer cada um dos 11 titulares com sua nota da tabela `player_ratings_cache`.
3. **Cálculo da Média**: Gerar o relatório de médias dos setores (Defesa, Meio e Ataque).
4. **Prompt Cognitivo da IA**: Injetar as notas dos atletas no prompt do Gemini, permitindo que a IA veja a pontuação real de cada jogador (ex: *Igor Jesus 7.4 vs Caicedo 6.9*) tanto na Premier League quanto no futebol colombiano ou saudita.

### Fase 4: Sincronização Web (`ApostaController.php` e Views)
1. Exibir na badge retrátil da aposta o resumo da nota média dos titulares (ex: `⭐ Média dos Titulares: 6.9`).
2. Manter a UX limpa e de alto contraste em conformidade com as Regras 17 e 23.

---

## 7. Garantias Operacionais

1. **Zero Falsos Positivos por Lista de Lesionados**: Nunca mais uma equipe como o Chelsea será rotulada como "time misto" apenas porque possui 4 atletas reservas no departamento médico.
2. **Equidade Global entre Ligas**: Jogadores promissores de ligas secundárias com notas altas ($\ge 7.0$) serão devidamente valorizados pelo modelo, acabando com o viés de fama.
3. **Proteção Total da Cota da API-Sports**: Custo diário inferior a 25 requisições adicionais, com proteção por cache permanente no MySQL (Regra 1).
4. **Governança Estrita**: Nenhuma aposta terá seu palpite revertido sem comprovação matemática de queda de nota e desfalque real na espinha dorsal.
