# ⚡ Guia Prático de Surebets & Localização de Ligas nas Casas de Apostas

Este documento contém o guia passo a passo para localização de ligas internacionais de futebol nas casas de apostas (como a **Betano**), além da arquitetura e funcionamento do módulo de Surebets da plataforma **FootballWeb**.

---

## 📌 Guia de Localização de Ligas Internacionais (Exemplo: Eliteserien 🇳🇴)

Algumas ligas europeias e internacionais possuem nomes próprios que podem ser difíceis de encontrar à primeira vista nas casas de apostas. 

A **ELITESERIEN** é a **1ª Divisão do Campeonato Norueguês de Futebol**.

### 🔍 Como encontrar a Eliteserien (ou qualquer liga) na Betano:

#### **Método 1: Lupa de Busca Direta (Mais Rápido)**
1. Acesse o site da casa de aposta (ex: [br.betano.com](https://br.betano.com/)).
2. Clique no ícone da **Lupa de Pesquisa 🔍** no menu principal.
3. Digite o nome da liga ou país: **`Eliteserien`** ou **`Noruega`**.
4. Selecione o caminho: **Futebol > Noruega > Eliteserien**.

#### **Método 2: Navegação pelo Menu de Países**
1. No menu lateral da casa de aposta, selecione a modalidade **Futebol**.
2. Navegue até o país **Noruega 🇳🇴** (seção de Ligas Europeias / Países).
3. Selecione a competição **Eliteserien**.
4. Encontre os confrontos dos times principais (ex: *Bodø/Glimt, Molde, Sandefjord, Rosenborg, Viking, KFUM Oslo*).

---

## 🗺️ Tabela de Referência Rápida: Nome da Liga vs País

| Nome da Liga exibido no Card | País / Categoria | Nome Popular / Divisão |
| :--- | :--- | :--- |
| **Eliteserien** | 🇳🇴 Noruega | 1ª Divisão Norueguesa |
| **Brasileirão Série A / B** | 🇧🇷 Brasil | 1ª / 2ª Divisão do Brasil |
| **Premier League** | 🏴󠁧󠁢󠁥󠁮󠁧󠁿 Inglaterra | 1ª Divisão Inglesa |
| **La Liga** | 🇪🇸 Espanha | 1ª Divisão Espanhola |
| **Serie A** | 🇮🇹 Itália | 1ª Divisão Italiana |
| **Bundesliga** | 🇩🇪 Alemanha | 1ª Divisão Alemã |
| **Allsvenskan** | 🇸🇪 Suécia | 1ª Divisão Sueca |
| **Superligaen** | 🇩🇰 Dinamarca | 1ª Divisão Dinamarquesa |

---

## 🧮 Funcionamento Matemático do Módulo de Surebets

Uma **Surebet (Arbitragem)** ocorre quando combinamos as maiores odds de casas de apostas diferentes para cobrir todos os resultados de uma partida (1X2) obtendo lucro garantido, independente de quem vença.

### Fórmula:
$$\text{Índice de Arbitragem } S = \frac{1}{\text{Odd Casa}} + \frac{1}{\text{Odd Empate}} + \frac{1}{\text{Odd Fora}}$$

- Se $S < 1,00$: **Existe Surebet!** O lucro percentual é $\left(\frac{1}{S} - 1\right) \times 100\%$.
- Se $S \ge 1,00$: **Não há Surebet** (Margem da casa ativa).

---

## 🏗️ Arquitetura do Scraper Oddspedia no FootballWeb

- **Scraper Oddspedia:** Extrai cotações agregadas via FlareSolverr (`/src/dags/lib/scrapers.py`).
- **Ingestão:** Grava no MySQL (`fixtures_trends`) e calcula Surebets (`/scripts/football_ingest_trends.py`).
- **Links Diretos:** Ao clicar nos botões de odd no card do jogo no **FootballWeb**, a casa de aposta é aberta diretamente em uma nova aba (`target="_blank"`).
