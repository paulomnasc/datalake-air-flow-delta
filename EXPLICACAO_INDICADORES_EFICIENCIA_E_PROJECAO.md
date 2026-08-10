# Guia de Indicadores de Eficiência, Gestão de Escala e Projeção +EV

Este documento descreve detalhadamente os conceitos matemáticos, o impacto do manejo da escala (stake) e o funcionamento das estatísticas de eficiência e projeção de longo prazo integradas no relatório do sistema **footballweb**.

---

## 1. Indicador de Eficiência (ROI / Yield Aferido)

O **ROI (Return on Investment / Yield Aferido)** mede a rentabilidade real obtida sobre todo o capital investido nas apostas encerradas do período filtrado.

$$\text{ROI (\%)} = \left( \frac{\text{Lucro Líquido}}{\text{Total Investido}} \right) \times 100$$

Onde:
* **Lucro Líquido:** $\text{Retorno Bruto dos Acertos} - \text{Total Investido nas Apostas Encerradas}$.
* **Total Investido:** Soma do valor financeiro de todas as apostas no período.

---

## 2. O Impacto do Valor de Escala (Stake)

A escala (valor apostado por bilhete) **não altera a probabilidade do evento esportivo**, mas **interfere diretamente no resultado financeiro final e na segurança da banca**.

### 2.1. Risco da Escala Variável Sem Critério
Apostar valores desproporcionais entre bilhetes (ex: R$ 10,00 em umas e R$ 500,00 em outras) cria um peso desequilibrado na carteira:

* **Exemplo de Escala Desequilibrada:**
  * 9 Apostas de R$ 10,00 ganhas a Odd 1.30 $\rightarrow$ Lucro: **+ R$ 27,00** (Taxa de Acerto: **90%**).
  * 1 Aposta de R$ 500,00 perdida $\rightarrow$ Prejuízo: **- R$ 500,00**.
  * **Resultado Final:** **Prejuízo de R$ 473,00** (mesmo com 90% de acerto).

### 2.2. Vantagem da Escala Fixa ou Proporcional
Ao manter uma **Stake Média padronizada** (ex: 1% a 2% da banca), o risco é diluído e a Taxa de Acerto passa a refletir fielmente a rentabilidade financeira.

---

## 3. Estudo de Caso Prático: 4 Apostas de R$ 100,00 com ROI de 75%

Considerando o cenário em que um usuário realiza **4 apostas de R$ 100,00** (Investimento Total de **R$ 400,00**) e obtém um **ROI de 75%**:

### 3.1. Demonstração Financeira
* **Investimento Total:** R$ 400,00 (4 x R$ 100,00)
* **Lucro Líquido Aferido:** $\text{R\$ } 400,00 \times 0,75 = \mathbf{+\text{R\$ } 300,00}$
* **Retorno Bruto Total:** $\text{R\$ } 400,00 + \text{R\$ } 300,00 = \mathbf{\text{R\$ } 700,00}$

### 3.2. Exemplos de Comportamento na Prática
* **Cenário A (100% Win Rate com Odd Média 1.75):**
  * 4 acertos de R$ 100,00 a Odd 1.75 = R$ 700,00 bruto $\rightarrow$ **Lucro Líquido: R$ 300,00**.
* **Cenário B (75% Win Rate com Odd Média 2.33):**
  * 3 acertos de R$ 100,00 a Odd 2.333 = R$ 700,00 bruto $\rightarrow$ **Lucro Líquido: R$ 300,00**.

---

## 4. Fórmula das Projeções Futuras de Longo Prazo (+EV)

A projeção de longo prazo estima o retorno financeiro em múltiplos volumes futuros de apostas, assumindo que a eficiência estatística ($ROI$) e o padrão de escala ($S_{\text{média}}$) do período filtrado sejam mantidos.

### 4.1. Cálculo da Stake Média Real ($S_{\text{média}}$)
$$S_{\text{média}} = \frac{\text{Total Investido nas Apostas Encerradas}}{\text{Total de Apostas Encerradas}}$$

### 4.2. Lucro Esperado por Aposta ($L_{\text{esperado}}$)
$$L_{\text{esperado}} = S_{\text{média}} \times \left( \frac{\text{ROI}}{100} \right)$$

### 4.3. Tabela de Projeção em Múltiplos Horizontes (Exemplo de Stake R$ 100,00 e ROI 75%)
| Horizon de Volume | Fórmula de Projeção | Lucro Projetado Acumulado |
| :--- | :--- | :--- |
| **Próximas 100 Apostas** | $100 \times \text{R\$ } 75,00$ | **+ R$ 7.500,00** |
| **Próximas 500 Apostas** | $500 \times \text{R\$ } 75,00$ | **+ R$ 37.500,00** |
| **Próximas 1.000 Apostas** | $1.000 \times \text{R\$ } 75,00$ | **+ R$ 75.000,00** |

---

## 5. Relação Break-Even (Ponto Nulo de Lucro)

O ponto de equilíbrio (**Break-Even Rate**) define a taxa mínima de acerto necessária para não ter prejuízo, calculada em função da Odd Média Ponderada:

$$\text{Break-Even Rate (\%)} = \left( \frac{1}{\text{Odd Média}} \right) \times 100$$

$$\text{Margem de Edge (\%)} = \text{Taxa de Acerto Real (\%)} - \text{Break-Even Rate (\%)}$$

* **Edge Positivo ($\Delta > 0$):** O apostador está superando a margem da banca e gerando lucro sustentável.
* **Edge Negativo ($\Delta < 0$):** A taxa de acerto atual é insuficiente para cobrir o valor das odds apostadas.

### 5.1. Conceito Prático de Manutenção de ROI Positivo

Para garantir lucro sustentável ou manter o ROI positivo em qualquer cenário de odds, a **Taxa de Acerto Real deve ser estritamente superior à Taxa de Break-Even**.

* **Taxa de Acerto Real > Break-Even Rate:** ROI Positivo / Lucro Líquido (+)
* **Taxa de Acerto Real = Break-Even Rate:** Ponto Nulo / Lucro Zero (R$ 0,00)
* **Taxa de Acerto Real < Break-Even Rate:** ROI Negativo / Prejuízo (-)

### 5.2. Estudo de Caso: Odd Média 1.26 e Limiar de 80% de Acerto

Ao operar com uma **Odd Média de 1.26**:

$$\text{Break-Even Rate} = \left( \frac{1}{1.26} \right) \times 100 \approx 79,4\%$$

Neste cenário:
1. **Ponto Nulo (79,4%):** É necessário acertar no mínimo 79,4% dos jogos para não perder capital.
2. **Meta de Lucro (80%+):** Manter uma taxa de acerto de **80% ou mais** garante que você permaneça na zona de **ROI Positivo**, obtendo um **Edge (Margem de Eficiência)** positivo sobre a banca.

### 5.3. O Desafio da Proporção de Recuperação (Risco em Odds Baixas)

Em odds reduzidas (como 1.26), a relação risco/retorno exige um controle rigoroso de consistência:

* **Lucro por Vitória (Stake R$ 5,00):** $\text{R\$ } 5,00 \times 0,26 = \mathbf{+\text{R\$ } 1,30}$
* **Perda por Derrota (Stake R$ 5,00):** $\text{R\$ } 5,00 \times 1,00 = \mathbf{-\text{R\$ } 5,00}$
* **Proporção de Recuperação:** 1 *Red* (derrota) consome o lucro de **~3,85 apostas ganhas** ($\text{R\$ } 5,00 / \text{R\$ } 1,30 \approx 3,85$).

> **Conclusão:** Para que uma estratégia em odds de 1.26 permaneça com ROI positivo, o apostador precisa manter a taxa de acertos **acima de 79,4% (idealmente 80%+)**, compensando o peso desproporcional de eventuais derrotas.

