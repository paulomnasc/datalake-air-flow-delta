# Login Social Google - Guia de Configuração

## Dependências

A dependência `google/apiclient` já está no `composer.json`. Se não estiver instalada:

```bash
composer require google/apiclient
```

## Configuração do .env

Adicione as credenciais do Google OAuth2 ao arquivo `.env`:

```dotenv
# Google OAuth2 Credentials
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET
```

### Onde obter as credenciais:

1. Acesse [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um novo projeto ou selecione um existente
3. Ative a **Google+ API**
4. Vá para **Credenciais** → **Criar Credenciais** → **ID de Cliente OAuth 2.0**
5. Selecione **Aplicação Web**
6. Adicione **URIs de redirecionamento autorizados**:
   - Desenvolvimento: `http://localhost:8080/auth/google-callback`
   - Produção: `https://seu-dominio.com/auth/google-callback`
7. Copie o **Client ID** e **Client Secret** para o `.env`

## Banco de Dados - Migration

Execute a migration para adicionar os campos de OAuth:

```bash
mysql -h <host> -u <usuario> -p <database> < app/Database/Migrations/add_google_oauth_to_usuario.sql
```

Campos adicionados:
- `google_id`: ID único do usuário no Google
- `google_token`: Token de acesso (JSON criptografado)
- `google_refresh_token`: Token de refresh
- `auth_provider`: Tipo de autenticação (google, email)
- `auth_updated_at`: Timestamp da última autenticação

## Fluxo de Autenticação

### 1. Usuário clica em "Entrar com Google"
- Link: `GET /auth/google-login`
- Redireciona para a tela de consentimento do Google

### 2. Google redireciona para callback
- Link: `GET /auth/google-callback?code=...&state=...`
- O `AuthController::googleCallback()` processa o código

### 3. Processamento:
- Valida o código com Google
- Obtém dados do usuário (email, nome, ID Google)
- Procura ou cria usuário no banco
- Inicia sessão
- Cria bucket no MinIO (se disponível)
- Sincroniza com Airflow (se disponível)
- Registra no activity log
- Redireciona para home

## Implementação nas Views

### Login (frmLogin.php)
```php
<a href="<?= route_to('auth.google.login') ?>" class="btn btn-danger">
    <i class="fab fa-google"></i> Entrar com Google
</a>
```

### Signup (signUpUsuario.php)
```php
<a href="<?= route_to('auth.google.login') ?>" class="btn btn-danger">
    <i class="fab fa-google"></i> Registrar com Google
</a>
```

## Casos de Uso

### Primeiro acesso com Google
1. Usuário clica em "Entrar com Google"
2. Autoriza no Google
3. Sistema cria novo usuário com:
   - Nome e email do Google
   - Status: `trial` (30 dias)
   - Perfil: `Teste`
   - Senha aleatória (usuário não usa)
   - `auth_provider`: `google`

### Acesso posterior
1. Usuário clica em "Entrar com Google"
2. Sistema encontra por `google_id`
3. Inicia sessão (sem precisar de senha)

### Usuário híbrido (email + Google)
1. Se email já existe no banco
2. Sistema associa o `google_id` ao usuário existente
3. Próximo acesso pode ser via email ou Google

## Segurança

- **Tokens**: Armazenados no banco (considere criptografia)
- **Refresh Token**: Usado automaticamente se token expirar
- **Sem Exposição de Senha**: Usuários Google não têm senha
- **Session**: Gerenciada normalmente após OAuth
- **Activity Logs**: Todos os logins Google são registrados

## Logs

Logs relacionados ao Google Auth aparecem em:
```
writable/logs/log-*.log
```

Procure por `[GOOGLE_AUTH]` ou `[AIRFLOW_GOOGLE]`

## Resolução de Problemas

### "Google OAuth credentials not configured"
- Verifique `.env`: `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` preenchidos
- Reinicie o container/servidor

### "Código de autorização não recebido"
- Verifique a URI de redirecionamento no Google Cloud Console
- Deve ser exatamente: `{base_url}/auth/google-callback`

### "Erro ao obter token"
- Verificar credenciais no Google Cloud
- Verificar logs em `writable/logs/`

### Usuário criado mas sem perfil
- Será associado ao perfil "Teste" automaticamente
- Se não existir, será criado sem perfil (ajustar conforme necessário)

## Testes

### Teste manual:
1. Acesse `http://seu-dominio/loginUsuario`
2. Clique em "Entrar com Google"
3. Autoriz

ar
4. Verifique se foi redirecionado para home com sessão ativa
5. Consulte `activity_logs`: deve ter registro com `route_alias='auth.google.callback'`

### Verificar usuário criado:
```sql
SELECT id, nome, email, google_id, auth_provider, criado_em
FROM usuario
WHERE auth_provider = 'google'
ORDER BY criado_em DESC
LIMIT 10;
```

## Próximos Passos Opcionais

1. **Logout Social**: Adicionar revogação de token ao fazer logout
2. **Vincular Múltiplos Provedores**: Suportar GitHub, Facebook, etc.
3. **Avatar do Google**: Usar foto de perfil do Google no app
4. **Sync Periódico**: Atualizar dados do usuário via CRON
