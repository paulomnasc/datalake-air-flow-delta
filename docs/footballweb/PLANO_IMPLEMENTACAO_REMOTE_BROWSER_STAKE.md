# Plano de Implementação: Remote Browser Isolation (Stake.com) no FootballWeb

## 1. Visão Geral e Objetivo

O objetivo deste projeto é permitir o acesso e navegação segura na plataforma **Stake.com** (e casas correlatas) diretamente através de uma interface web embutida no **FootballWeb**, https://cristalbet.com.br/terminal/stake, garantindo que:

1. **IP e Conectividade**: Toda requisição HTTP/WebSocket seja originada estritamente pelo IP e infraestrutura do servidor (host), e **nunca** pelo IP residencial/local da máquina do usuário.
2. **Locale e Timezone**: O navegador remoto utilize a localidade (*locale*), fuso horário (*timezone*) e configurações regionais configuradas no servidor.
3. **Bypass de Restrições de Iframe**: Contorne as proteções de segurança nativas da Stake (`X-Frame-Options: SAMEORIGIN` e `Content-Security-Policy: frame-ancestors`), transmitindo apenas a tela do navegador remoto via Canvas/WebRTC/noVNC.
4. **Persistência de Sessão**: Cookies, credenciais salvas e sessões de autenticação permaneçam armazenadas em volume persistente no servidor, evitando re-autenticações constantes.
5. **Privacidade e Discrição**: A rota seja oculta de menus convencionais e restrita aos administradores do sistema.

---

## 2. Arquitetura da Solução

```
[ Usuário no Navegador Local ]
              │
              │ HTTPS (Acesso ao FootballWeb)
              ▼
   [ Nginx Gateway (:28443 / :80) ]
              │
              ├──► /terminal/stake  ──► [ FootballWeb (CodeIgniter 4) ]
              │                                (Renderiza View com iframe noVNC/Kasm)
              │
              └──► /remote-browser/ (WebSocket + HTTP)
                               │
                               ▼
            [ Container Docker: Isolated Chromium / noVNC ]
                               │
                               │ HTTPS nativo com IP/Locale do Servidor
                               ▼
                       [ Stake.com ]
```

### Componentes:
1. **Container do Navegador Isolado (`isolated-browser`)**:
   * **Imagem Base Recomendada**: `linuxserver/chromium:latest` ou `kasmweb/chromium:1.15.0`.
   * **Vantagens**: Oferece interface web nativa embutida (via noVNC/KasmVNC com WebSocket), aceleração básica por software, suporte a extensões e perfil de usuário persistente.
   * **Locale**: Configurado explicitamente via variáveis de ambiente (`TZ=America/Sao_Paulo` ou a localidade desejada, `LANG=pt_BR.UTF-8` ou `en_US.UTF-8`).
2. **Proxy Reverso (`nginx-gateway`)**:
   * Encaminha requisições seguras para a porta interna do container de streaming com suporte completo a *WebSocket Upgrade* (`Upgrade $http_upgrade; Connection "upgrade"`).
3. **FootballWeb (CodeIgniter 4)**:
   * **Controller**: `src/footballweb/app/Controllers/TerminalStakeController.php` (com verificação de autenticação de sessão).
   * **View**: `src/footballweb/app/Views/terminal/stake.php` (layout minimalista e imersivo, tela cheia ou card de alta resolução com comandos rápidos de recarga).
   * **Rota**: Rota oculta mapeada em `src/footballweb/app/Config/Routes.php` (ex: `$routes->get('terminal/stake', 'TerminalStakeController::index')`).

---

## 3. Análise de Viabilidade e Recursos

* **Memória RAM Disponível**: O servidor conta com **15 GiB de RAM total**, estando **~6.0 GiB livres/disponíveis** no momento.
* **Consumo Estimado do Navegador Remoto**:
  * Em repouso (1 aba aberta na Stake): **~500 MB a 750 MB**.
  * Em uso ativo intenso: **~900 MB a 1.2 GB**.
* **Dimensionamento Seguro (Docker limits)**:
  * Limite máximo de memória: `1536M` (1.5 GB).
  * Limite de CPU: `1.5 CPUs` (para evitar concorrência com o Airflow Scheduler/Spark).

---

## 4. Etapas Detalhadas de Implementação

### Etapa 1: Definição do Serviço Docker
Adicionar o serviço `remote-browser` ao `docker-compose.yml` (ou compose complementar):
* Definir volume persistente `./data/remote-browser-profile:/config` para gravar cache, cookies e sessão da Stake.
* Conectar o container à rede interna existente `airflow_net`.
* Configurar variáveis de fuso horário, dimensões de tela padrão (ex: `1920x1080`) e limites de memória.

### Etapa 2: Configuração de Rota e Proxy no Nginx Gateway
* Inserir bloco de roteamento interno no `nginx-gateway` para mapear a conexão WebSocket e HTTP do noVNC de forma autenticada ou isolada internamente, evitando expor portas extras diretamente para a internet pública.

### Etapa 3: Criação do Controller e View no FootballWeb
* **Controller**: `TerminalStakeController.php`
  * Valida permissão do usuário.
  * Injeta a URL interna do streaming e parâmetros de tela.
* **View**: `src/footballweb/app/Views/terminal/stake.php`
  * Interface dark integrada com a identidade visual do FootballWeb.
  * Container responsivo em tela cheia com barra de controle superior (botão de tela cheia, limpar clipboard, reconectar WebSocket).

### Etapa 4: Teste de Locale e Geocheck Pré-Stake
* Antes de navegar diretamente para a Stake, o container será testado acessando serviços de verificação de geolocalização e fingerprint (ex: `browserleaks.com/ip`, `whoer.net` ou `ipinfo.io`) de dentro do container remoto para atestar:
  1. O IP exibido é o IP do servidor;
  2. O fuso horário e idioma correspondem aos parâmetros definidos;
  3. Não há vazamento de WebRTC (*WebRTC leak*) apontando para o IP local do usuário.

---

## 5. Matriz de Segurança e Boas Práticas

| Item | Requisito / Medida |
| :--- | :--- |
| **Proteção de Acesso** | Rota restrita por autenticação de sessão do FootballWeb (usuário admin logado). |
| **Exposição de Portas** | A porta do navegador remoto **não deve ser exposta publicamente** no firewall; todo tráfego passa autenticado pelo Nginx e FootballWeb. |
| **Persistência de Sessão** | Volume Docker protegido com permissões restritas para armazenar a sessão do Chromium. |
| **Isolamento de Recursos** | Limite estrito de RAM (`mem_limit: 1.5G`) para garantir que o Airflow, MySQL e Spark continuem com folga operacional. |

---

## 6. Próximos Passos e Protocolo de Execução

Em conformidade com a **Regra de Ouro nº 5** do repositório (*Proibição Estrita de Modificação de Código Sem Consentimento Prévio*):
1. **Revisão pelo Desenvolvedor**: O usuário avalia o plano acima e as decisões de arquitetura.
2. **Autorização Explícita**: Somente após a confirmação e aprovação do usuário, iniciaremos a criação dos arquivos e configurações propostas.
