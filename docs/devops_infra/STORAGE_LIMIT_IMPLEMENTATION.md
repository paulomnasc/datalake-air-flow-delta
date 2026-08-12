# Implementação de Limite de Armazenamento por Usuário no MinIO

## Resumo
Esta implementação adiciona um controle de quota de armazenamento por usuário no MinIO, impedindo que usuários excedam um limite configurável de espaço de armazenamento ao fazer upload de arquivos para o sistema de medalhão (Data Lake).

## Arquivos Modificados

### 1. `.env` - Variável de Configuração
**Arquivo:** `/src/codeigniter-app/.env`

Adicionada a variável de ambiente `MINIO_USER_STORAGE_LIMIT` que define o limite de armazenamento por usuário em bytes.

```bash
# Limite de armazenamento por usuário no MinIO (em bytes)
# Padrão: 1073741824 = 1GB
# Exemplos: 
#   - 104857600 = 100MB
#   - 524288000 = 500MB
#   - 1073741824 = 1GB
#   - 5368709120 = 5GB
#   - 10737418240 = 10GB
MINIO_USER_STORAGE_LIMIT = 1073741824
```

**Valor padrão:** 1GB (1073741824 bytes)

### 2. MinioHelper.php - Funções de Verificação
**Arquivo:** `/src/codeigniter-app/app/Helpers/MinioHelper.php`

Adicionados três novos métodos estáticos:

#### 2.1. `getBucketStorageUsage(string $bucketName): int`
Calcula o tamanho total em bytes de todos os objetos armazenados em um bucket específico.

- **Parâmetros:**
  - `$bucketName`: Nome do bucket a verificar
- **Retorna:** Tamanho total em bytes
- **Funcionalidade:**
  - Verifica se o bucket existe
  - Usa paginação para listar todos os objetos
  - Soma o tamanho de cada objeto
  - Retorna 0 em caso de erro ou bucket não existente

#### 2.2. `checkStorageLimit(string $bucketName, int $newFileSize = 0): array`
Verifica se o usuário ainda tem espaço disponível para upload de novos arquivos.

- **Parâmetros:**
  - `$bucketName`: Nome do bucket do usuário
  - `$newFileSize`: Tamanho do(s) novo(s) arquivo(s) a ser(em) enviado(s)
- **Retorna:** Array com informações detalhadas:
  ```php
  [
      'allowed' => bool,          // Se o upload é permitido
      'current_usage' => int,     // Uso atual em bytes
      'limit' => int,             // Limite configurado em bytes
      'available' => int,         // Espaço disponível em bytes
      'new_file_size' => int,     // Tamanho dos novos arquivos
      'future_usage' => int,      // Uso futuro se upload for realizado
      'message' => string         // Mensagem descritiva
  ]
  ```

#### 2.3. `formatBytes(int $bytes, int $precision = 2): string`
Formata bytes em formato legível (B, KB, MB, GB, TB).

- **Parâmetros:**
  - `$bytes`: Tamanho em bytes
  - `$precision`: Casas decimais (padrão: 2)
- **Retorna:** String formatada (ex: "1.5 GB")

### 3. ConfigController.php - Validação de Upload
**Arquivo:** `/src/codeigniter-app/app/Controllers/ConfigController.php`

Adicionada verificação de limite de armazenamento em **3 métodos**:

#### 3.1. Método `insert()` - Upload Único (linha ~437)
Antes de fazer upload de um arquivo único (CSV/JSON):
```php
// VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO
$fileSize = $uploadedFile->getSize();
$storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucket, $fileSize);

if (!$storageCheck['allowed']) {
    log_message('warning', "Upload bloqueado por limite de armazenamento: {$storageCheck['message']}");
    return $this->response->setJSON([
        'status' => 'error',
        'mensagem' => $storageCheck['message']
    ]);
}
```

#### 3.2. Método `uploadMultipleFiles()` - Upload Múltiplo (linha ~1153)
Antes de fazer upload de múltiplos arquivos:
```php
// VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO PARA UPLOAD MÚLTIPLO
// Calcular tamanho total de todos os arquivos
$totalFilesSize = 0;
foreach ($files as $file) {
    $totalFilesSize += $file->getSize();
}

$storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucketInUse, $totalFilesSize);

if (!$storageCheck['allowed']) {
    log_message('warning', "Upload múltiplo bloqueado por limite de armazenamento: {$storageCheck['message']}");
    throw new \Exception(
        "Limite de armazenamento excedido! " . 
        "Tamanho total dos arquivos: " . \App\Helpers\MinioHelper::formatBytes($totalFilesSize) . ". " .
        $storageCheck['message']
    );
}
```

#### 3.3. Método `updateMultipleFiles()` - Atualização com Upload Múltiplo (linha ~1649)
Antes de fazer upload de múltiplos arquivos em uma atualização:
```php
// VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO PARA UPDATE MÚLTIPLO
// Calcular tamanho total de todos os novos arquivos
$totalFilesSize = 0;
foreach ($files as $file) {
    $totalFilesSize += $file->getSize();
}

$storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucketInUse, $totalFilesSize);

if (!$storageCheck['allowed']) {
    log_message('warning', "Update múltiplo bloqueado por limite de armazenamento: {$storageCheck['message']}");
    throw new \Exception(
        "Limite de armazenamento excedido! " . 
        "Tamanho total dos novos arquivos: " . \App\Helpers\MinioHelper::formatBytes($totalFilesSize) . ". " .
        $storageCheck['message']
    );
}
```

## Fluxo de Funcionamento

### 1. Configuração
O administrador do sistema define o limite de armazenamento no arquivo `.env` através da variável `MINIO_USER_STORAGE_LIMIT`.

### 2. Upload de Arquivo(s)
Quando um usuário tenta fazer upload de arquivos:

1. O sistema captura o(s) arquivo(s) enviado(s)
2. Calcula o tamanho total dos novos arquivos
3. Identifica o bucket do usuário
4. Chama `MinioHelper::checkStorageLimit()` passando:
   - Nome do bucket do usuário
   - Tamanho total dos novos arquivos

### 3. Verificação de Limite
O método `checkStorageLimit()`:

1. Lê o limite configurado no `.env` (ou usa 1GB como padrão)
2. Chama `getBucketStorageUsage()` para calcular uso atual
3. Calcula o uso futuro (atual + novos arquivos)
4. Compara com o limite configurado
5. Retorna se o upload é permitido + informações detalhadas

### 4. Decisão
- ✅ **Permitido:** Upload prossegue normalmente
- ❌ **Bloqueado:** Retorna erro com mensagem informativa

## Mensagens de Retorno

### Quando o upload é permitido:
```
Upload permitido. Uso atual: 450.5 MB / 1 GB (45.0%). Espaço disponível: 573.5 MB
```

### Quando o limite é excedido:
```
Limite de armazenamento excedido! Uso atual: 950.2 MB / 1 GB (95.0%). 
Espaço necessário: 150.3 MB, mas apenas 73.8 MB disponível.
```

## Logs
Todos os eventos são registrados no log do CodeIgniter:

- **Info:** Verificações bem-sucedidas
- **Warning:** Uploads bloqueados por limite
- **Error:** Erros ao calcular uso de armazenamento

## Configuração Recomendada

### Desenvolvimento
```bash
MINIO_USER_STORAGE_LIMIT = 524288000  # 500MB
```

### Produção (Pequeno Porte)
```bash
MINIO_USER_STORAGE_LIMIT = 5368709120  # 5GB
```

### Produção (Médio/Grande Porte)
```bash
MINIO_USER_STORAGE_LIMIT = 21474836480  # 20GB
```

## Isolamento por Usuário
A verificação funciona com base no **bucket do usuário**, garantindo que:

- Cada usuário tem seu próprio bucket (padrão: `{username-prefix}-{id}`)
- A quota é individual por usuário
- Usuários diferentes não afetam a quota uns dos outros

## Compatibilidade

### MinIO
- ✅ Compatível com MinIO (S3 API)
- ✅ Usa paginação para listar objetos
- ✅ Funciona com buckets de qualquer tamanho

### CodeIgniter
- ✅ Framework: CodeIgniter 4
- ✅ AWS SDK for PHP (aws/aws-sdk-php)

## Testes Recomendados

### 1. Teste de Upload Único
1. Configurar limite baixo (ex: 100MB)
2. Fazer upload de arquivo pequeno (deve funcionar)
3. Fazer upload até quase atingir o limite
4. Tentar upload que exceda o limite (deve ser bloqueado)

### 2. Teste de Upload Múltiplo
1. Selecionar múltiplos arquivos cuja soma exceda o limite
2. Verificar se o sistema bloqueia corretamente
3. Verificar mensagem de erro informativa

### 3. Teste de Isolamento
1. Criar dois usuários
2. Cada um deve ter sua própria quota
3. Upload de um não deve afetar o outro

## Manutenção

### Aumentar Limite Global
Edite o `.env` e ajuste `MINIO_USER_STORAGE_LIMIT`:
```bash
MINIO_USER_STORAGE_LIMIT = 10737418240  # 10GB
```

### Monitorar Uso
Os logs do CodeIgniter registram:
- Uso atual de cada usuário
- Tentativas bloqueadas
- Mensagens de erro

### Resolver Problemas
Se a verificação não funcionar:

1. Verificar se `MINIO_USER_STORAGE_LIMIT` está definido no `.env`
2. Verificar logs do CodeIgniter (`writable/logs/`)
3. Confirmar que o bucket do usuário existe
4. Verificar credenciais do MinIO

## Melhorias Futuras Sugeridas

1. **Dashboard de Uso:** Criar página mostrando uso de cada usuário
2. **Quotas Personalizadas:** Permitir limites diferentes por usuário/grupo
3. **Notificações:** Alertar usuário quando atingir 80% do limite
4. **Limpeza Automática:** Remover arquivos antigos automaticamente
5. **Cache:** Armazenar uso atual em cache para melhor performance
6. **API de Monitoramento:** Endpoint para consultar uso de armazenamento

## Suporte

Para questões ou problemas:
1. Verificar logs em `writable/logs/`
2. Consultar este documento
3. Revisar código em `MinioHelper.php` e `ConfigController.php`

---

**Data de Implementação:** Dezembro 2025  
**Versão:** 1.0  
**Status:** ✅ Implementado e Funcional
