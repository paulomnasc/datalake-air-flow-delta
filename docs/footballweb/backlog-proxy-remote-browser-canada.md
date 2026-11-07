# Backlog: Configuração de Proxy Canadá no Remote-Browser para Operação na Stake.com

Este documento consolida o diagnóstico técnico, a fundamentação regulatória e o passo a passo exato para ativação de um proxy com IP do **Canadá** no navegador remoto isolado (*Remote Browser* / `/terminal/stake`), viabilizando apostas e saques na **Stake.com** sem bloqueios territoriais nem retenções de fundos.

---

## 1. Contexto e Motivação

1. **Bloqueio no Brasil (DNS Anatel)**:
   - A Secretaria de Prêmios e Apostas (SPA/MF) e a Anatel impuseram bloqueio de DNS (*DNS Poisoning / Sinkhole*) nas operadoras nacionais (Claro, Vivo, TIM) para domínios de apostas sem licença federal brasileira (`.bet.br`).
   - O acesso direto à Stake.com internacional (.com) a partir de conexões residenciais brasileiras é barrado ou redirecionado para a versão nacional restrita.

2. **Restrição no Servidor Atual (Alemanha / Hetzner)**:
   - O servidor Hetzner opera com IP alemão (`46.224.156.251` - Nuremberg, Alemanha).
   - A Alemanha possui regulamentação restritiva severa (*GlüStV / GGL*).
   - A **Stake.com internacional lista a Alemanha como "Jurisdição Proibida" (*Prohibited Jurisdiction*)**:
     - Conectar ou solicitar saques a partir de IPs da Alemanha aciona verificações de conformidade (*KYC compulsório*) e traz **alto risco de retenção de saldo ou congelamento de saques**.

3. **Por que o Canadá (CA)?**:
   - O **Canadá** é uma das jurisdições mais amigáveis (*bet-friendly*) da Stake.com global:
     - Não sofre restrição de acesso;
     - Possui catálogo asiático e props de futebol 100% completos;
     - Saques em criptomoedas (especialmente **Litecoin - LTC**) são processados de forma instantânea e automatizada pela blockchain sem questionamentos territoriais.

---

## 2. Diagnóstico dos Testes Realizados

- **IP do Servidor**: `46.224.156.251` (AS24940 Hetzner Online GmbH - Nuremberg, DE).
- **Acesso direto via curl**: Retorna `HTTP/2 403` com bloqueio e desafio Cloudflare (`cf-mitigated: challenge`).
- **Análise da cota gratuita da Webshare.io**:
  - Os 10 proxies atribuídos aleatoriamente no plano gratuito pertenciam a:
    - 🇺🇸 Estados Unidos (US) — *Jurisdição proibida mais severa da Stake*;
    - 🇬🇧 Reino Unido (GB) — *Jurisdição proibida (UKGC)*;
    - 🇪🇸 Espanha (ES) — *Jurisdição proibida (DGOJ)*;
    - 🇩🇪 Alemanha (DE) — *Jurisdição proibida (GGL)*;
    - 🇵🇱 Polônia (PL) — *Jurisdição proibida (Monopólio estatal)*.
  - **Conclusão**: Nenhum proxy da amostra gratuita é tolerado pela Stake. A seleção explícita da localização **Canadá (CA)** é obrigatória.

---

## 3. Especificação do Plano Recomendado (Webshare.io)

Para garantir máxima estabilidade e evitar desafios de bot da Cloudflare:

| Componente | Opção Recomendada | Custo Estimado | Benefício Técnico |
| :--- | :--- | :--- | :--- |
| **Plano Base** | Proxy Server (5 a 10 proxies) | ~ $ 2,99 / mês | Permite selecionar a localização fixa: **Canada (CA)**. |
| **Add-on** | **Static Residential** *(Opcional / Recomendado)* | + $ 1,50 / mês | IP residencial estático de operadoras domésticas canadenses (Bell, Rogers). Máximo *Trust Score*, elimina suspeitas de datacenter perante a Cloudflare e a Stake. |
| **Total Mensal** | — | **~ $ 4,49 / mês (~ R$ 25)** | Custo irrisório perto do lucro da banca (+EV). |

---

## 4. O que Já Está Pronto e Configurado no Código do Sistema

Todas as alterações estruturais na plataforma já foram implementadas e validadas:

1. **Documentação de Saque (`docs/footballweb/apostas-criptomoedas-stake.md`)**:
   - Seção 8 documentada com diretrizes oficiais do suporte da Stake;
   - Regra de "Moedas Puras" (*pure coins only*): nunca misturar redes LTC, BTC e BCH;
   - Link pronto parametrizado para saque em LTC: `https://stake.com/?tab=withdraw&currency=ltc&modal=wallet`.
2. **Atalho Rápido na Interface (`src/footballweb/app/Views/terminal/stake.php`)**:
   - Botão **"Saque Stake"** com ícone dourado inserido na barra de navegação do terminal (`/terminal/stake`).
3. **Políticas de Navegação do Chromium (`data/remote-browser-policies/policies.json`)**:
   - `stake.com` e `[*.]stake.com` liberadas em `ClipboardAllowedForUrls` e `PopupsAllowedForUrls`, garantindo copiar e colar o endereço da carteira sem bloqueio do navegador.

---

## 5. Checklist de Retomada (Passo a Passo Rápido para Ativação)

Quando decidir contratar o proxy para operar na Stake:

### Passo 1: Finalizar Contratação na Webshare.io
1. Acesse o painel da [Webshare.io](https://www.webshare.io).
2. Confirme o e-mail da conta (clicar em *"Resend email"* caso não tenha chegado) para deslimitar a banda.
3. No menu de planos, selecione:
   - **Country / Location**: `Canada` (Canadá);
   - Marque a opção `[x] Static Residential` ($ 1.50/mês).
4. Conclua a assinatura.

### Passo 2: Configurar Autenticação por IP (Elimina Senha no Navegador)
1. No painel da Webshare, vá em **IP Authorization** (ou no seletor *Authentication Method*).
2. Adicione o IP fixo do servidor Hetzner:
   ```text
   46.224.156.251
   ```
3. Salve. Isso autoriza o servidor a conectar sem exigir credenciais de usuário/senha no Chromium.

### Passo 3: Obter os Dados de Conexão
Na aba **Proxy List** da Webshare, anote:
- **Proxy Address / Host**: (ex: `p.webshare.io` ou o IP numérico canadense fornecido);
- **Porta**: (ex: `9999` para HTTP ou `1080` para SOCKS5).

### Passo 4: Configuração no Servidor FootballWeb
Basta solicitar ao assistente ou configurar as variáveis no arquivo `src/footballweb/.env`:
```env
REMOTE_BROWSER_PROXY_ENABLED=true
REMOTE_BROWSER_PROXY_HOST=p.webshare.io
REMOTE_BROWSER_PROXY_PORT=9999
REMOTE_BROWSER_PROXY_TYPE=http  # ou socks5
```
E aplicar a injeção da flag no comando de inicialização do Chromium em `src/footballweb/app/Controllers/TerminalStakeController.php`:
```php
--proxy-server="http://p.webshare.io:9999"
```

### Passo 5: Teste e Validação Final
1. Acessar `/terminal/stake` no FootballWeb.
2. Clicar no atalho **"BrowserLeaks"** ou **"Checar IP (Whoer)"** na barra superior.
3. Confirmar que a localização exibida é **Canada** com 0% de vazamento de WebRTC e sem alertas de proxy/datacenter.
4. Clicar em **"Saque Stake"** para operar na Stake.com com total liberdade e liquidez em Litecoin (LTC).

