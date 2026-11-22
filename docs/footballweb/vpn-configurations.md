# Guia Operacional de Configuração de VPN e IP Dedicado (Suíça)

Este documento consolida o diagnóstico regulatório, a fundamentação técnica e o guia de configuração da **NordVPN com IP Dedicado na Suíça**, viabilizando a navegação segura, estável e contínua em corretoras e plataformas esportivas internacionais (como **PIWI247** e bolsas esportivas), totalmente imune a bloqueios de DNS/IP da Anatel e sem risco de bloqueio de conta por compartilhamento de IPs.

---

## 1. Contexto e Motivação

### 1.1 Bloqueio no Brasil (Anatel e Operadoras)
* **Regulamentação Nacional (Lei 14.790/2023)**: A Secretaria de Prêmios e Apostas do Ministério da Fazenda (SPA/MF) determinou o bloqueio de plataformas de apostas esportivas e brokers internacionais que não solicitaram ou pagaram a outorga federal brasileira.
* **Mecanismo do Bloqueio**: A Anatel emite notificações obrigatórias para as operadoras de telecomunicações brasileiras (Claro, Vivo, TIM, etc.) para aplicar:
  1. **Envenenamento de DNS (*DNS Poisoning / Sinkhole*)**: As operadoras não resolvem domínios offshore como `piwi247.com`.
  2. **Bloqueio de Rota/IP**: O tráfego direto direcionado para os servidores dessas plataformas é descartado na rede nacional.
* **Solução Técnica**: O uso de uma VPN criptografa todo o tráfego do usuário em um túnel fechado (protocolo NordLynx/WireGuard), fazendo com que a operadora brasileira não consiga identificar nem interferir nos sites acessados.

### 1.2 Por que a Opção por IP Dedicado?
* Em VPNs comuns compartilhadas, centenas de pessoas utilizam o mesmo endereço de IP ao mesmo tempo. Se outro usuário fizer mau uso daquele IP, casas de apostas e sistemas antifraude (Cloudflare) colocam o IP em listas de suspeita (*blacklist*), exigindo CAPTCHAs intermináveis ou congelando a conta para auditoria de segurança.
* **Com o IP Dedicado Exclusivo**, o endereço de internet pertence **estritamente e unicamente a você**. Para a casa de apostas, o seu comportamento aparenta ser o de um usuário residencial fixo e legítimo, garantindo máxima confiabilidade para liberação de saques.

### 1.3 Por que a Suíça (Zurique) e não Alemanha ou Canadá?
1. **Alemanha (DE) - Totalmente Proibida**:
   - O servidor da Hetzner opera na Alemanha (`46.224.156.251`), mas a Alemanha possui uma regulação extremamente proibitiva (*GlüStV / GGL*). Quase todas as plataformas internacionais de apostas bloqueiam IPs alemães imediatamente.
2. **Canadá (CA) - O Risco de Ontário (Toronto)**:
   - Embora o Canadá seja historicamente amigável, a província de Ontário (onde fica Toronto) criou uma regulação restrita própria (AGCO/iGO). Servidores automáticos de VPN costumam cair em Toronto, o que gera bloqueio imediato na PIWI247.
3. **Suíça (CH) - O Cenário Ideal**:
   - Jurisdição neutra, forte proteção constitucional à privacidade de dados e total tolerância a corretoras de apostas e brokers internacionais.

---

## 2. Dados e Parâmetros da Configuração Contratada

| Parâmetro | Valor Configurado |
| :--- | :--- |
| **Provedor** | NordVPN |
| **Tipo de Produto** | Dedicated IP (Endereço de IP Exclusivo) |
| **Endereço de IP** | **`84.46.159.223`** |
| **Localização** | **Switzerland (Zurich) 🇨🇭** |
| **Servidor Fixo** | **Switzerland #424** |
| **Vigência do Plano** | Ativo (Renovação em **15 de fevereiro de 2029**) |
| **Protocolo de Conexão** | NordLynx (WireGuard de Alta Velocidade) |

---

## 3. Passo a Passo de Configuração Realizado

### Passo 1: Ativação no Painel Web da NordVPN
1. Acesso à área de clientes da NordVPN em `Home > NordVPN > IP dedicado`.
2. No item **Dedicated IP**, seleção da localização **Switzerland (Zurich)**.
3. Alocação imediata do IP estático exclusivo: `84.46.159.223` no servidor `Switzerland #424`.

### Passo 2: Login e Autenticação no Aplicativo
1. Instalação do aplicativo oficial da NordVPN no computador/dispositivo.
2. Conclusão do login com as credenciais da conta.
3. Validação do onboarding de segurança.

### Passo 3: Definição do IP Dedicado como Localização Padrão
1. Na tela de introdução **"Escolha sua localização favorita"**:
   - Foi selecionada a opção **`IP dedicado (Suíça)`** em vez de "Servidor mais rápido".
   - Confirmado com o botão **`Próximo`**.
2. Com isso, o aplicativo NordVPN passa a conectar automaticamente no servidor `Switzerland #424` (`84.46.159.223`) a cada inicialização, sem necessidade de buscas manuais.

---

## 4. Testes de Integridade e Validação da Conexão

Sempre que iniciar a conexão com a VPN, a validação pode ser confirmada em segundos:

1. **Checagem de IP e Localização**:
   - Acesse [meuip.com.br](https://meuip.com.br) ou [ipleak.net](https://ipleak.net).
   - Confirme se o IP exibido é exatamente: **`84.46.159.223`**.
   - Confirme se o país de origem exibido é: **Suíça / Switzerland (Zurique)**.
2. **Checagem de Vazamento de DNS (DNS Leak)**:
   - No [ipleak.net](https://ipleak.net), verifique a seção *DNS Addresses*. Todos os servidores de DNS devem pertencer à NordVPN / Suíça, sem qualquer menção a provedores brasileiros (Claro, Vivo, TIM).

---

## 5. Diretrizes para Operação na PIWI247

### 5.1 Cadastro e Verificação de Identidade (KYC)
* **Regra de Ouro**: A PIWI247 aceita cidadãos brasileiros nativamente. No momento de preencher o cadastro e enviar os documentos de verificação (**KYC**), envie sua **CNH ou Passaporte brasileiro real** e comprovante de residência no seu nome.
* **Nunca falsifique endereços na Suíça ou no exterior**: A VPN serve exclusivamente para criar a rota de conexão estável e furar o bloqueio de rede da Anatel. Se você tentar cadastrar um endereço suíço falso, sua conta será reprovada no momento do envio de documentos para saque.

### 5.2 Depósitos e Saques Imunes ao Sistema Bancário
* **Moeda Operacional**: Opere sempre com **USDT (Tether)** ou criptomoedas suportadas.
* **Vantagem**: Os depósitos e saques ocorrem diretamente entre a sua carteira privada de cripto (ou exchange) e a PIWI247. Como não há transações em PIX ou transferências bancárias em Reais, a operação é **100% imune a bloqueios judiciais, retenções ou atrasos de bancos brasileiros**.
* **Limite de Saque**: A PIWI247 oferece teto de saque de até **€ 50.000 por 24 horas**, com exigência de giro mínimo (*rollover*) de 1x do valor depositado antes do resgate.

### 5.3 Estabilidade e Segurança da Conta
* Como você possui um IP Dedicado (`84.46.159.223`), **mantenha-se sempre conectado nele**.
* A casa de apostas registrará que todos os seus acessos vêm sempre da mesma máquina e do mesmo IP em Zurique, eliminando completamente gatilhos antifraude por "troca suspeita de localização".

---

## 6. Dinâmica Operacional na PIWI247: Sportsbook vs. PIWIXchange (Cash Out)

A PIWI247 integra duas plataformas de apostas com regras de liquidez e encerramento completamente distintas no menu superior:

### 6.1 Aba "Sports" (Sportsbook Tradicional / Pinnacle)
* **Como funciona**: Apostas tradicionais contra a banca, alimentadas pelas linhas profissionais da Pinnacle.
* **Cash Out**: **NÃO POSSUI botão de Cash Out automático**. A filosofia profissional da Pinnacle baseia-se em cotações brutas máximas com margens mínimas, onde as apostas correm até o encerramento do evento.
* **Como encerrar antecipadamente**: Caso deseje sair de uma posição ao vivo, é necessário realizar uma cobertura manual (*Hedge*), apostando na ponta contrária (ex: se entrou em *Menos de 2.5 Gols* no pré-jogo, efetua uma entrada em *Mais de 2.5 Gols* no ao vivo para travar saldo).
* **Perfil de Uso**: Ideal para entradas estatísticas de Valor Esperado (+EV) pré-jogo onde o objetivo é deixar o evento transcorrer até os 90 minutos.

### 6.2 Aba "Exchange" (PIWIXchange / Bolsa Esportiva Betfair)
* **Como funciona**: Bolsa esportiva *peer-to-peer* (apostador contra apostador) com a mesma infraestrutura e liquidez da Betfair Exchange internacional.
* **Identificação Visual**: As cotações são divididas em duas colunas coloridas clássicas:
  * 🟦 **Azul (Back / A Favor)**: Aposta em favor do evento acontecer (ex: apostar a favor de *Menos de 2.5 Gols*).
  * 🟥 **Rosa / Salmão (Lay / Contra)**: Aposta contra a ocorrência do evento (assumindo o papel de banca).
* **Cash Out Automático**: **SIM, possui botão de Cash Out**.
  * **Dependência de Liquidez**: O cálculo e a aceitação do Cash Out dependem de haver volume financeiro correspondente na ponta oposta negociada na bolsa. Em grandes competições (Champions League, Premier League, Série A), a liquidez é abundante; em torneios obscuros, o botão pode oscilar por falta de contraparte.
  * **Lances Críticos**: Assim como em qualquer bolsa esportiva, em momentos de gol, revisão de VAR ou pênaltis, o mercado é suspenso por breves segundos até a estabilização do placar.

---

## 7. O Dilema Operacional: Criptomoedas (USDT) vs. Casas Reguladas (Betano / PIX)

Frequentemente surge a dúvida sobre operar na Betano utilizando USDT para usufruir do excelente sistema de cash out da plataforma. A realidade regulatória é a seguinte:

1. **Por que a Betano não aceita USDT?**:
   - A Betano (Kaizen Gaming) opera com licença oficial e subordina-se à regulação federal brasileira (**Lei 14.790/2023** e normas do Banco Central).
   - A legislação nacional **proíbe expressamente** o uso de criptomoedas, cartões de crédito e dinheiro em espécie para apostas licenciadas. Todo o fluxo financeiro da Betano é restrito a **Reais (BRL) via PIX** atrelados ao CPF do titular.
2. **Comparativo de Plataformas**:
   - **Betano / Casas Reguladas**: Excelente Cash Out automatizado, porém operam exclusivamente em Reais (PIX) e limitam apostadores lucrativos no longo prazo.
   - **Thunderpick**: Aceita criptomoedas, mas utiliza práticas predatórias de liquidez (desliga o botão de Cash Out unilateralmente quando o jogo atinge o limiar de risco).
   - **PIWI247**: Aceita USDT nativamente, **nunca limita a conta do usuário**, mas exige operação na aba **Exchange** para desfrutar de Cash Out automatizado com liquidez de mercado.

---

## 8. Comparativo Técnico: VPN com IP Dedicado vs. Remote Browser

| Aspecto | VPN com IP Dedicado (Configuração Atual) | Remote Browser (Navegador em Nuvem) |
| :--- | :--- | :--- |
| **Velocidade e Usabilidade** | **Nativa**: Navegador comum do PC com aceleração de hardware e zero lag visual. | **Com Latência**: Transmissão de vídeo da tela (VNC/WebRTC) sujeita a atrasos no clique. |
| **Tipo de IP** | **IP Dedicado Limpo**: Alocado exclusivamente para você na Suíça. | **IP de Data Center**: IPs de nuvem (AWS, DigitalOcean, Hetzner) frequentemente barrados por Cloudflare. |
| **Custo e Complexidade** | **Baixo / Único**: Aplicativo oficial da NordVPN já contratado até 2029. | **Alto / Contínuo**: Custo mensal de servidor VPS + manutenção de containers e proxies. |
| **Aplicação Ideal** | **Apostas manuais, navegação rápida, gestão de banca e saques**. | **Automação de robôs headless / bots de apostas 24/7 via código**. |

---

## 9. Veredito Final de Plataformas Cripto: Por que a Stake.com é a Melhor Opção

Após testes práticos de usabilidade, liquidez e encerramento antecipado de apostas em múltiplas casas de apostas em criptomoedas, o comparativo consolidou o seguinte diagnóstico:

### 9.1 Matriz Comparativa de Plataformas

| Casa de Apostas | Praticidade do Cash Out | Confiabilidade de Saque | Suporte a Cripto (USDT) | Veredito Operacional |
| :--- | :--- | :--- | :--- | :--- |
| **Stake.com** 🏆 | **Excelente (1 clique)** via Sportradar, fluido e intuitivo. | **Máxima**: Saques automáticos via blockchain em minutos. | **Nativo** (USDT, LTC, BTC, etc.). | **RECOMENDADA**: Melhor ecossistema, liquidez global e facilidade de operação. |
| **PIWI247** | **Burocrático**: Exige operação via bolsa esportiva (*Exchange*), pareamento *Back/Lay* e liquidez de terceiros. | **Boa**: Brokers confiáveis, mas sem a agilidade de um clique. | **Nativo** (USDT, USDC). | Excelente para apostas pré-jogo que correm até o fim, mas péssima para Cash Out rápido. |
| **Thunderpick** | **Instável / Má-fé**: Corta unilateralmente o botão de Cash Out quando o jogo entra no limiar de risco. | Média (exige KYC e bloqueia conexões da Suíça). | **Nativo** (USDT, BTC). | **REPROVADA**: Risco severo de retenção de liquidez em momentos decisivos. |
| **Vave** | **Mediano com margem alta**: Cobra deságio abusivo no valor de encerramento da aposta. | Baixa/Média (reclamações de travar saques maiores para "análise manual"). | **Nativo** (USDT, BTC). | **NÃO RECOMENDADA**: Plataforma pequena com odds piores e risco de atrito em saques. |

---

### 9.2 Links Rápidos e Atalhos Oficiais da Stake.com

Para evitar confusão com o menu da plataforma, utilize os links diretos para navegação e saques imediatos:

* 🏠 **Página Principal de Esportes**:  
  [https://stake.com/sports](https://stake.com/sports)
* 💰 **Modal Geral de Saque (Retirada Direta)**:  
  [https://stake.com/?tab=withdraw&modal=wallet](https://stake.com/?tab=withdraw&modal=wallet)
* 💵 **Atalho Direto para Saque em USDT (Tether)**:  
  [https://stake.com/?tab=withdraw&currency=usdt&modal=wallet](https://stake.com/?tab=withdraw&currency=usdt&modal=wallet)
* ⚡ **Atalho Direto para Saque em Litecoin (LTC - Taxas de Centavos)**:  
  [https://stake.com/?tab=withdraw&currency=ltc&modal=wallet](https://stake.com/?tab=withdraw&currency=ltc&modal=wallet)

---

### 9.3 Dicas Operacionais Críticas para Operação na Stake

1. **Localização Exata do Saque na Interface**:
   - O saque da Stake **não fica no menu do perfil/avatar**.
   - Ele localiza-se no topo da tela, no botão azul **`Carteira`** (ao lado do saldo), na aba **`Retirar`** (*Withdraw*). Clicar no link direto acima abre essa tela instantaneamente.
2. **Destravamento de Saque (Verificação Nível 1)**:
   - Para que a aba de saque fique ativa, a Stake exige apenas o **Nível 1 de Verificação**.
   - Esse nível é um formulário cadastral simples (nome, data de nascimento e endereço residencial). **Não exige envio de fotos de documentos** para a faixa padrão de apostas.
3. **Atenção às Redes Cripto (*Pure Coins Only*)**:
   - Ao sacar em **USDT**, certifique-se de selecionar na sua carteira a mesma rede escolhida na Stake (geralmente **TRC-20** ou **Polygon** para taxas mínimas).
   - Ao sacar em **Litecoin (LTC)**, utilize exclusivamente endereços LTC. Nunca misture redes.
4. **Rota Segura para Enviar o Dinheiro ao Brasil (PIX)**:
   - Para manter total conformidade e blindagem contra alertas em exchanges brasileiras (conforme detalhado em `docs/footballweb/apostas-criptomoedas-stake.md`):
     $$\text{Stake.com} \xrightarrow{\text{Saque Cripto}} \text{Carteira Pessoal (Trust Wallet / Exodus)} \xrightarrow{\text{Transferência}} \text{Binance Brasil} \xrightarrow{\text{PIX}} \text{Conta Bancária}$$
   - Esse fluxo garante que o saldo chegue na sua conta bancária via PIX em poucos minutos, com custo de rede desprezível e sem atritos fiscais.

---

*Documentação elaborada e arquivada em 15/11/2026 para governança operacional e segurança de infraestrutura do projeto.*
