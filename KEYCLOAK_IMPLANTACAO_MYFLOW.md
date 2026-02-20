# Implantação de Keycloak para Autenticação Centralizada (MyFlow)

## 1. Instalar o Keycloak via Docker Compose

Crie um arquivo `docker-compose-keycloak.yml` com o seguinte conteúdo:

```yaml
version: '3.8'
services:
  keycloak:
    image: quay.io/keycloak/keycloak:24.0.1
    command: start-dev
    environment:
      KEYCLOAK_ADMIN: admin
      KEYCLOAK_ADMIN_PASSWORD: admin
      KC_DB: h2
    ports:
      - "8081:8080"
    restart: unless-stopped
```

Execute:
```sh
docker compose -f docker-compose-keycloak.yml up -d
```

Acesse o Keycloak em: http://localhost:8081

---

## 2. Configurar o Realm "MyFlow"

1. Acesse o painel Keycloak (usuário: `admin`, senha: `admin`).
2. Clique em "Add realm" e crie o realm chamado **MyFlow**.

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
3. Client type: `OpenID Connect`
4. Root URL: URL da sua aplicação (ex: http://localhost:8000)
5. Salve e anote o `Client ID` e `Secret`.
6. Repita para Airflow, MinIO e Atlas.

---

## 4. Configurar Usuários e Grupos

1. Em "Users", crie usuários (ex: email e senha).
2. Em "Groups", crie grupos se desejar segmentar permissões.
3. Associe usuários aos grupos conforme necessário.

---

## 5. Configurar WebApp CodeIgniter para OIDC

1. Instale uma biblioteca OIDC Client para PHP, como `jumbojett/openid-connect-php`:
   ```sh
   composer require jumbojett/openid-connect-php
   ```
2. Configure o client OIDC no seu app:
   ```php
   $oidc = new OpenIDConnectClient(
       'http://localhost:8081/realms/MyFlow',
       'codeigniter-app',
       'CLIENT_SECRET_AQUI'
   );
   $oidc->authenticate();
   $userInfo = $oidc->requestUserInfo();
   ```
3. Proteja as rotas do seu app para exigir autenticação OIDC.

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
