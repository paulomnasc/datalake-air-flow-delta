# Gerenciamento Automático de Buckets por Usuário

## 📋 Visão Geral

O sistema agora cria automaticamente um bucket individual no MinIO para cada usuário durante o processo de login. Isso permite que cada usuário tenha seu próprio espaço de armazenamento isolado no Data Lake.

## 🔧 Implementação

### Componentes Criados

1. **MinioHelper** (`app/Helpers/MinioHelper.php`)
   - Helper para gerenciamento de buckets no MinIO
   - Funções principais:
     - `createUserBucket($userId)` - Cria bucket no formato `user-{id}`
     - `bucketExists($bucketName)` - Verifica se bucket existe
     - `ensureBucketExists($bucketName)` - Garante existência (cria se necessário)
     - `listBuckets()` - Lista todos os buckets disponíveis

2. **Integração no Login** (`app/Controllers/UsuarioController.php`)
   - Método `logar()` - Login padrão com email/senha
   - Método `logarUsuarioConfirmaEmail()` - Login após confirmação de email
   - Ambos agora criam o bucket automaticamente após autenticação bem-sucedida

3. **AuthController** (`app/Controllers/AuthController.php`)
   - Preparado para OAuth/Google Login
   - Comentários indicando onde adicionar a criação de bucket após integração com banco de dados

## 🎯 Funcionamento

### Fluxo de Login

```
1. Usuário faz login (email/senha ou OAuth)
   ↓
2. Credenciais validadas
   ↓
3. Sessão criada
   ↓
4. MinioHelper::createUserBucket($userId) é chamado
   ↓
5. Sistema verifica se bucket "user-{id}" existe
   ↓
6. Se NÃO existe → Cria novo bucket
   Se SIM existe → Log informativo (bucket já existe)
   ↓
7. Login concluído
```

### Formato do Bucket

- **Padrão de nomenclatura**: `user-{id}`
- **Exemplos**:
  - Usuário ID 1 → Bucket `user-1`
  - Usuário ID 42 → Bucket `user-42`
  - Usuário ID 123 → Bucket `user-123`

### Validações

O `MinioHelper` aplica as regras de nomenclatura S3:
- ✅ Apenas letras minúsculas, números e hífens
- ✅ Deve começar e terminar com letra ou número
- ✅ Entre 3 e 63 caracteres
- ✅ Prefixo `user-` garante conformidade

## 📊 Logging

O sistema registra automaticamente:

```php
// Sucesso
log_message('info', "Bucket do usuário {$usuario->id}: Bucket 'user-42' criado com sucesso.");

// Bucket já existe
log_message('info', "Bucket do usuário {$usuario->id}: Bucket 'user-42' já existe.");

// Erro
log_message('error', "Falha ao criar bucket do usuário {$usuario->id}: Erro ao criar bucket: ...");
```

**Localização dos logs**: `writable/logs/log-{data}.php`

## 🔒 Segurança

### Credenciais MinIO

As credenciais são lidas das variáveis de ambiente configuradas no `docker-compose.yml`:

```yaml
environment:
  - MINIO_ENDPOINT=http://minio:9000
  - MINIO_REGION=us-east-1
  - MINIO_ACCESS_KEY_ID=admin
  - MINIO_SECRET_ACCESS_KEY=admin123
```

### Isolamento de Dados

- Cada usuário tem seu próprio bucket
- Futuras políticas de acesso podem restringir acesso cross-bucket
- Logs de auditoria rastreiam criação de buckets

## 🧪 Testes

### Testar Criação Manual de Bucket

```php
use App\Helpers\MinioHelper;

// Criar bucket para usuário ID 999 (teste)
$result = MinioHelper::createUserBucket(999);

if ($result['success']) {
    echo "Bucket '{$result['bucket_name']}' criado: {$result['message']}";
} else {
    echo "Erro: {$result['message']}";
}
```

### Verificar Buckets Existentes

```php
use App\Helpers\MinioHelper;

$buckets = MinioHelper::listBuckets();
print_r($buckets);
// Exemplo de saída: ['lab01', 'user-1', 'user-2', 'user-42']
```

### Verificar Bucket Específico

```php
use App\Helpers\MinioHelper;

$exists = MinioHelper::bucketExists('user-1');
echo $exists ? 'Bucket existe' : 'Bucket não existe';
```

## 📝 Uso no Sistema

### Acessar Bucket do Usuário Logado

```php
// Em qualquer controller
$userId = $_SESSION['id_usuario_logado'];
$bucketName = "user-" . $userId;

// Agora pode usar o bucket para upload/download de arquivos
// Exemplo com AWS SDK:
$s3Client = new S3Client([...]);
$s3Client->putObject([
    'Bucket' => $bucketName,
    'Key' => 'meu-arquivo.csv',
    'Body' => $fileContent
]);
```

### Query Builder com Bucket do Usuário

```sql
-- No Query Builder, usuário pode consultar seus próprios dados:
SELECT * FROM read_parquet('s3://user-42/bronze/meus_dados.parquet') LIMIT 10;
```

## 🚀 Melhorias Futuras

1. **Políticas de Acesso**
   - Configurar IAM policies no MinIO
   - Restringir acesso cross-bucket

2. **Quotas**
   - Implementar limites de armazenamento por usuário
   - Monitoramento de uso de espaço

3. **Organização Interna**
   - Criar subpastas automáticas: `user-{id}/bronze/`, `user-{id}/silver/`, `user-{id}/gold/`
   - Seguir arquitetura medallion dentro do bucket do usuário

4. **Auditoria**
   - Dashboard de buckets criados
   - Métricas de uso por usuário

## 🐛 Troubleshooting

### Erro: "Bucket name is invalid"

**Causa**: Nome de bucket não segue regras S3  
**Solução**: O helper já valida automaticamente. Verificar se ID do usuário é numérico.

### Erro: "Access Denied"

**Causa**: Credenciais MinIO incorretas  
**Solução**: Verificar variáveis de ambiente no `docker-compose.yml`

### Bucket não é criado (sem erro)

**Causa**: Possível falha silenciosa no S3Client  
**Solução**: Verificar logs em `writable/logs/` para mensagens de erro detalhadas

## 📚 Referências

- [AWS SDK for PHP - S3 Client](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/s3-examples.html)
- [MinIO Documentation](https://min.io/docs/minio/linux/index.html)
- [S3 Bucket Naming Rules](https://docs.aws.amazon.com/AmazonS3/latest/userguide/bucketnamingrules.html)
