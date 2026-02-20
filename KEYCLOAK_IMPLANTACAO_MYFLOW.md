# Histórico de Troubleshooting SSO Keycloak (adicionado por GitHub Copilot)
## Solução definitiva (20/02/2026)

- O backend (PHP/CodeIgniter) deve usar KEYCLOAK_PROVIDER_URL=http://keycloak:8080/realms/MyFlow para comunicação interna.
- O navegador (frontend/redirecionamento) deve usar http://localhost:8181/realms/MyFlow.
- O erro DNS_PROBE_FINISHED_NXDOMAIN ocorre quando o navegador é redirecionado para keycloak:8080, que só existe na rede Docker.
- Ajuste o .env do backend para KEYCLOAK_PROVIDER_URL=http://keycloak:8080/realms/MyFlow.
- Garanta que o setRedirectURL use um endereço acessível pelo navegador (ex: localhost:8181).

## Diagnóstico realizado em 20/02/2026

### Sintoma
Erro persistente ao autenticar via Keycloak OIDC:
```
{
    "status": "error",
    "mensagem": "Erro ao iniciar autenticação Keycloak",
    "detalhe": "Curl error: (7) Failed to connect to localhost port 8181 after 0 ms: Could not connect to server"
}
```

### Passos já realizados
- Separação de variáveis de ambiente para cada app (CodeIgniter/Airflow) no .env
- Ajuste do AuthController para usar KEYCLOAK_CLIENT_ID_CODEIGNITER e KEYCLOAK_CLIENT_SECRET_CODEIGNITER
- Verificação de rede Docker: containers codeigniter-app e keycloak estão na mesma rede e comunicam via keycloak:8080
- Teste de curl do codeigniter-app para keycloak:8080 retornando 200 OK

### Diagnóstico
- O erro indica que, em algum ponto, o backend ainda tenta acessar Keycloak via localhost:8181, não via keycloak:8080.
- Isso pode ocorrer se:
  - O .env não está sendo carregado corretamente pelo PHP/CodeIgniter.
  - O processo PHP-FPM/Apache não está lendo o .env atualizado (precisa reiniciar container).
  - O OpenIDConnectClient está recebendo valor default ('http://localhost:8181/realms/MyFlow') por getenv vazio.

### Próximos passos automáticos
- Adicionar log temporário no AuthController para registrar o valor real de getenv('KEYCLOAK_REALM_URL') e garantir que está correto no ambiente de execução.
# Implantação de Keycloak para Autenticação Centralizada (MyFlow)

## 1. Instalar o Keycloak via Docker Compose

Adicione o serviço Keycloak no seu `docker-compose.yml` principal com o seguinte conteúdo:

```yaml
version: '3.8'
services:
  keycloak:
    image: quay.io/keycloak/keycloak:24.0.1
    command: start-dev
    environment:
      KEYCLOAK_ADMIN: admin
      KEYCLOAK_ADMIN_PASSWORD: kJ#212394
      KC_DB: mysql
      KC_DB_URL_HOST: mysql
      KC_DB_URL_PORT: 3306
      KC_DB_URL_DATABASE: lista_revisao2
      KC_DB_USERNAME: root
      KC_DB_PASSWORD: root
    ports:
      - "8181:8080"
    restart: unless-stopped
```


Execute:
```sh
docker compose up -d keycloak
```


Acesse o Keycloak em: http://localhost:8181

---

## 2. Configurar o Realm "MyFlow"

1. Acesse o painel Keycloak em http://localhost:8181 (usuário: `admin`, senha: `kJ#212394`).
   - Caso deseje alterar a senha do admin:
     1. Faça login com o usuário admin.
     2. No menu lateral, clique em "Users" (Usuários).
     3. Clique sobre o usuário "admin" na lista.
     4. Vá até a aba "Credentials" (Credenciais).
     5. Opção Reset Password, digite a nova senha desejada nos campos "New Password" e "Password Confirmation".
     6. Clique em "Save" (Salvar).
     7. Pronto! A senha do admin foi alterada com sucesso.
## Sugestão de variáveis no .env

Adicione ao seu arquivo `.env` para facilitar manutenção:

```
# Keycloak
KEYCLOAK_ADMIN=admin
KEYCLOAK_ADMIN_PASSWORD=kJ#212394
KEYCLOAK_PORT=8181
KC_DB=mysql
KC_DB_URL_HOST=mysql
KC_DB_URL_PORT=3306
KC_DB_URL_DATABASE=lista_revisao2
KC_DB_USERNAME=root
KC_DB_PASSWORD=root
```
2. Clique em "Add realm" e crie o realm chamado **MyFlow**.
- **Acesse o painel do Keycloak (ex: http://localhost:8181).**
- **Faça login com o usuário admin.**
- **Na lista suspensa com o item 'Keycloak' lateral esquerdo, clique em “Create Realm” (ou “Reino”).**
- **No campo “Name” (Nome), digite MyFlow (ou outro nome desejado).**
- **Clique em “Create” (Criar).**
- **Pronto! Agora você pode cadastrar clients, usuários e grupos dentro desse novo realm.**
---

## 3. Criar Clientes (Aplicações)

Crie um cliente para cada aplicação:
- **WebApp CodeIgniter**
- **Airflow**
- **MinIO**
- **Atlas**



Exemplo para o WebApp:
1. Em "Clients", clique em "Create client".
2. Nome: `codeigniter-app`
3. Em "Client type", selecione: **OpenID Connect** (obrigatório para integração OIDC).
4. Root URL: URL da sua aplicação (ex: http://localhost:8088)
5. Configure os campos principais:
   - **Always display in UI**: On (ativado)
   - **Client authentication**: On (ativado, necessário para apps backend)
   - **Authorization**: Off (desative se não for usar políticas RBAC do Keycloak)
   - **Authentication flow**:
     - **Standard flow**: On (ativado, fluxo Authorization Code)
     - **Direct access grants**: On (ativado, permite login via senha)
     - **Implicit flow**: Off (desative, não recomendado para apps modernas)
     - **Service accounts roles**: Off (desative, exceto se for integração máquina a máquina)
     - **OAuth 2.0 Device Authorization Grant**: Off (desative, exceto se usar login em dispositivos)
     - **OIDC CIBA Grant**: Off (desative, exceto se usar autenticação CIBA)
    
    Principais URLs de configuração:
    - **Root URL**: `http://localhost:8088/`
    - **Home URL**: `http://localhost:8088/dashboard`
    - **Valid redirect URIs**: `http://localhost:8088/*`
    - **Valid post logout redirect URIs**: `http://localhost:8088/`
    - **Web origins**: `http://localhost:8088`
6. Salve e anote o `Client ID` e `Secret`.
   
  **Como obter o Client ID e Secret:**
  1. Após criar o cliente, clique no nome do cliente recém-criado na lista de clientes.
  2. Na tela de configurações do cliente, localize o campo **Client ID** no topo da página e copie o valor.
  3. No menu lateral do cliente, clique em **Credentials** (Credenciais).
  4. O campo **Client secret** estará visível. Clique no botão de copiar ao lado do valor para copiar o segredo.
  5. Guarde ambos (Client ID e Secret) em local seguro, pois serão usados na configuração da sua aplicação.
7. Repita para Airflow, MinIO e Atlas, ajustando o Root URL conforme cada aplicação.

---

## 4. Configurar Usuários e Grupos

1. Em "Users", crie usuários (ex: email e senha).
2. Em "Groups", crie grupos se desejar segmentar permissões.
3. Associe usuários aos grupos conforme necessário.

---

## 5. Configurar WebApp CodeIgniter para OIDC


### Passo a passo para integração OIDC (Keycloak) no CodeIgniter

1. Instale a biblioteca OIDC Client no container PHP:
  ```sh
  docker exec <ID_DO_CONTAINER_PHP> composer require jumbojett/openid-connect-php
  ```
  Substitua `<ID_DO_CONTAINER_PHP>` pelo ID do seu container (exemplo: 4e9b78e56553c6a9d4d1d5feb11beb0488873791b33a066e6aed04cdb08a8ea4).

2. No seu controller de autenticação (ex: `AuthController.php`), adicione:
  ```php
  require_once APPPATH . '../vendor/autoload.php';
  use Jumbojett\OpenIDConnectClient;

  public function keycloakLogin()
  {
     $oidc = new OpenIDConnectClient(
        'http://localhost:8181/realms/MyFlow', // URL do realm Keycloak
        'codeigniter-app', // Client ID
        'CLIENT_SECRET_AQUI' // Client Secret
     );
     $oidc->setRedirectURL(base_url('auth/keycloak-callback'));
     $oidc->addScope('openid email profile');
     $oidc->authenticate();
     $userInfo = $oidc->requestUserInfo();
     // Aqui, crie/autentique o usuário na sua aplicação usando $userInfo
  }

  public function keycloakCallback()
  {
     // O fluxo de callback será tratado automaticamente pelo authenticate()
  }
  ```

3. No arquivo de rotas (`app/Config/Routes.php`), adicione:
  ```php
  $routes->get('/auth/keycloak-login', 'AuthController::keycloakLogin');
  $routes->get('/auth/keycloak-callback', 'AuthController::keycloakCallback');
  ```

4. No formulário de login (`app/Views/frmLogin.php`), adicione um botão:
  ```php
  <a href="<?= base_url('auth/keycloak-login') ?>" class="btn btn-primary btn-block">
     <i class="fab fa-keycdn"></i> Entrar com Keycloak
  </a>
  ```

5. No seu arquivo `.env`, adicione as variáveis:
  ```env
  KEYCLOAK_CLIENT_ID=codeigniter-app
  KEYCLOAK_CLIENT_SECRET=CLIENT_SECRET_AQUI
  KEYCLOAK_REALM_URL=http://localhost:8181/realms/MyFlow
  ```

6. Pronto! Agora sua aplicação está integrada ao Keycloak via OIDC.

---

## 6. Configurar Airflow, MinIO e Atlas para OIDC

- **Airflow**: Use o [Authlib OIDC Provider](https://airflow.apache.org/docs/apache-airflow/stable/security/oidc.html) e configure o client do Keycloak.
- **MinIO**: [MinIO OIDC Guide](https://min.io/docs/minio/linux/integrations/keycloak.html)
- **Atlas**: Consulte a documentação do Atlas para OIDC ou SAML.

---

## 7. Demonstração de Desativação Instantânea

1. No Keycloak, desative um usuário (Users > [usuário] > Status: Disabled).
2. Tente acessar qualquer sistema (WebApp, Airflow, MinIO, Atlas) com esse usuário.
3. O acesso será negado imediatamente, sem delay.

---

## 8. Observações

- Todos os sistemas devem validar o token OIDC do Keycloak a cada requisição protegida.
- O logout centralizado pode ser implementado para encerrar sessões em todos os sistemas.
- Para produção, use banco externo (Postgres) e HTTPS no Keycloak.

---

**Pronto! Agora você tem autenticação centralizada, híbrida e com desativação instantânea de usuários para todos os sistemas.**
