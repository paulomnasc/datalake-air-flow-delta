# 📖 Dicionário de Dados: Relatório de Arbitragem de Apostas (Brasileirão Série A e B)

Este documento descreve a estrutura, o significado e os tipos de dados de cada coluna presente no arquivo CSV gerado pela DAG Airflow (`sports_arbitrage_dag`), salvo no bucket S3 `s3://paulomnasc-558/arbitrage/brasileirao_arbitrage_latest.csv`.

---

## 📋 Tabela de Campos do CSV

| Nome da Coluna | Tipo de Dado | Exemplo | Descrição e Função |
| :--- | :---: | :---: | :--- |
| **`Campeonato`** | Texto | `Brasileirão Série A` | Nome da liga/competição analisada (ex: *Brasileirão Série A* ou *Brasileirão Série B*). |
| **`Data_Jogo`** | Texto | `29/07 19:30` | Data e horário agendado para o início da partida no fuso de Brasília (UTC-3). |
| **`Time_Casa`** | Texto | `INTERNACIONAL` | Nome padronizado do time mandante (que joga em casa). |
| **`Time_Visitante`** | Texto | `FLAMENGO` | Nome padronizado do time visitante. |
| **`Casa_Odd_1`** | Texto | `Matchbook` | Nome da casa de apostas que oferece a **maior cotação** para a vitória do time mandante. |
| **`Odd_1`** | Decimal (`%.2f`) | `4.40` | Valor do multiplicador/cotação para a vitória do time da casa na casa indicada em `Casa_Odd_1`. |
| **`Stake_Odd_1_R$`** | Decimal (`%.2f`) | `227.50` | **Valor a apostar (em R$)** na vitória do time da casa na plataforma indicada. |
| **`Casa_Odd_X`** | Texto | `Betfair` | Nome da casa de apostas que oferece a **maior cotação** para o empate. |
| **`Odd_X`** | Decimal (`%.2f`) | `3.75` | Valor do multiplicador/cotação para o empate na casa indicada em `Casa_Odd_X`. |
| **`Stake_Odd_X_R$`** | Decimal (`%.2f`) | `266.93` | **Valor a apostar (em R$)** no empate na plataforma indicada. |
| **`Casa_Odd_2`** | Texto | `Matchbook` | Nome da casa de apostas que oferece a **maior cotação** para a vitória do time visitante. |
| **`Odd_2`** | Decimal (`%.2f`) | `1.98` | Valor do multiplicador/cotação para a vitória do visitante na casa indicada em `Casa_Odd_2`. |
| **`Stake_Odd_2_R$`** | Decimal (`%.2f`) | `505.57` | **Valor a apostar (em R$)** na vitória do time visitante na plataforma indicada. |
| **`Indice_Arbitragem`** | Decimal (`%.4f`) | `0.9990` | Soma das probabilidades implícitas ($S = \frac{1}{\text{Odd}_1} + \frac{1}{\text{Odd}_X} + \frac{1}{\text{Odd}_2}$). Se o valor for **menor que $1.0000$**, indica Surebet. |
| **`Eh_Surebet`** | Texto (Enum) | `SIM` ou `NAO` | Flag rápida: **`SIM`** se for uma oportunidade de lucro garantido sem risco; **`NAO`** caso contrário. |
| **`Lucro_Percentual_%`** | Decimal (`%.2f`) | `0.10` | Percentual de rendimento limpo garantido sobre o valor da banca total apostada. |
| **`Lucro_Estimado_R$`** | Decimal (`%.2f`) | `1.00` | Lucro líquido garantido em Reais ($R\$) obtido em **qualquer um dos 3 resultados possíveis**. |
| **`Banca_Total_R$`** | Decimal (`%.2f`) | `1000.00` | Valor total em Reais ($R\$) utilizado como base para calcular a divisão das 3 apostas (`Stake 1 + Stake X + Stake 2`). |

---

## 📐 Fórmulas Matemáticas Utilizadas

1. **Índice de Arbitragem ($S$):**
   $$S = \frac{1}{\text{Odd}_1} + \frac{1}{\text{Odd}_X} + \frac{1}{\text{Odd}_2}$$

2. **Condição de Surebet:**
   $$\text{Eh\_Surebet} = \text{SIM} \iff S < 1,0000$$

3. **Cálculo de Aposta Individual (Stake Sizing):**
   $$\text{Stake}_i = \frac{\text{Banca Total}}{S \times \text{Odd}_i}$$

4. **Lucro Percentual (%):**
   $$\text{Lucro Percentual} = \left(\frac{1}{S} - 1\right) \times 100$$

---

## 💡 Dicas de Execução

- **Filtro no Excel/Google Sheets:** Ordene a coluna **`Eh_Surebet`** por **`SIM`** ou ordene a coluna **`Lucro_Percentual_%`** de forma decrescente para visualizar prioritariamente os jogos rentáveis.
- **Divisão da Banca:** Respeite a divisão calculada nas colunas **`Stake_Odd_1_R$`**, **`Stake_Odd_X_R$`** e **`Stake_Odd_2_R$`** para garantir o retorno sem risco.
