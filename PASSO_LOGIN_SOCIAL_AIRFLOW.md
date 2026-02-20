# Como Ativar Login Social Google no Airflow (Passo a Passo)

## 1. Problema Identificado
Ao criar um usuário no sistema via login social Google, o usuário não é criado automaticamente no Airflow, pois o Airflow não suporta login social nativamente pela API.

## 2. Solução Elegante
A forma mais elegante e segura é ativar o login social Google diretamente na interface do Airflow, usando OAuth2/OIDC.

## 3. Como Editar o `webserver_config.py` no Docker
1. Descubra o nome ou ID do container Airflow:
   ```bash
   docker ps
   ```
2. Acesse o shell do container:
   ```bash
   docker exec -it NOME_OU_ID_DO_CONTAINER bash
   ```
3. Localize ou crie o arquivo:
   ```bash
   /opt/airflow/webserver_config.py
   ```
4. Edite com `vi` ou `nano`:
   ```bash
   vi /opt/airflow/webserver_config.py
   # ou
   nano /opt/airflow/webserver_config.py
   ```
5. Salve e reinicie o serviço webserver:
   ```bash
   docker restart NOME_OU_ID_DO_CONTAINER
   # ou dentro do container
   airflow webserver restart
   ```

## 4. Exemplo de Configuração para Login Google
Cole o conteúdo abaixo em `/opt/airflow/webserver_config.py` e ajuste os valores de client_id e client_secret conforme o app criado no Google Cloud Console:

```python
from flask_appbuilder.security.manager import AUTH_OAUTH

AUTH_TYPE = AUTH_OAUTH
OAUTH_PROVIDERS = [{
    'name': 'google',
    'icon': 'fa-google',
    'token_key': 'access_token',
    'remote_app': {
        'client_id': 'SUA_CLIENT_ID',
        'client_secret': 'SEU_CLIENT_SECRET',
        'api_base_url': 'https://www.googleapis.com/oauth2/v2/',
        'client_kwargs': {
            'scope': 'email profile'
        },
        'access_token_url': 'https://accounts.google.com/o/oauth2/token',
        'authorize_url': 'https://accounts.google.com/o/oauth2/auth',
    }
}]
AUTH_USER_REGISTRATION = True
AUTH_USER_REGISTRATION_ROLE = "Public"  # ou "Viewer", "User", etc.
```

## 5. Observações
- Com essa configuração, qualquer usuário com conta Google pode acessar o Airflow sem necessidade de cadastro manual.
- O Airflow criará o usuário automaticamente ao primeiro login.
- Ajuste as roles conforme sua política de acesso.

## 6. Dúvidas Frequentes
- **Preciso de instruções para criar o app OAuth no Google Cloud Console?**
  - Sim: acesse https://console.cloud.google.com/apis/credentials, crie um novo OAuth Client ID, configure os URIs de redirecionamento conforme o endereço do seu Airflow.

---

*Gerado automaticamente por GitHub Copilot em 20/02/2026.*
