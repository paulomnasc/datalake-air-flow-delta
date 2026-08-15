# Fix: Git Clone Error 400 - userBucket Issue

## Problema Relatado
Erro ao clonar repositório GitHub em cliente diferente:
```
Erro ao clonar: Git clone failed
api/git-clone:1  Failed to load resource: the server responded with a status of 400 ()
Clone error: Error: Git clone failed
```

## Causa Raiz
O erro 400 ocorria porque:

1. **userBucket inválido ou nulo**: Em alguns clientes/navegadores, o `userBucket` obtido da sessão PHP estava retornando `null` ou vazio
2. **Mensagem de erro genérica**: O backend retornava apenas "Missing required fields" sem especificar qual campo estava faltando
3. **Falta de fallback**: Não havia fallback automático para um bucket padrão

## Solução Implementada

### 1. Backend - GitServerController.php
**Arquivo**: `/app/Controllers/GitServerController.php`

**Mudanças**:
- Adicionar fallback automático quando `userBucket` não é fornecido:
  - Tenta obter da requisição
  - Se não encontrar, tenta obter da sessão via `SessionHelper::getUserBucket()`
  - Se ainda vazio, usa bucket padrão: `'lab01'` ou variável de ambiente `DEFAULT_USER_BUCKET`

- Melhorar mensagens de erro:
  - Agora especifica exatamente quais campos estão faltando
  - Retorna array `missingFields` para o frontend processar

```php
// If userBucket is not provided, try to get it from session
if (!$userBucket) {
    $userBucket = SessionHelper::getUserBucket();
}

// Fallback to default bucket if still not available
if (!$userBucket) {
    $userBucket = getenv('DEFAULT_USER_BUCKET') ?: 'lab01';
}

// Validate required fields with specific error messages
$missingFields = [];
if (!$owner) $missingFields[] = 'owner';
if (!$repo) $missingFields[] = 'repo';

if (!empty($missingFields)) {
    return $this->response->setStatusCode(400)->setJSON([
        'error' => 'Missing required fields',
        'message' => 'Required fields missing: ' . implode(', ', $missingFields),
        'missingFields' => $missingFields
    ]);
}
```

### 2. Frontend - unified-code-editor.php
**Arquivo**: `/app/Views/code_editor/unified-code-editor.php`

**Mudanças**:
- Validação local de `userBucket` antes de enviar:
  - Verifica se é válido (não null, não string vazia)
  - Usa fallback automático para 'lab01' se inválido
  - Log de aviso no console quando fallback é usado

- Tratamento melhorado de erros:
  - Decodifica JSON seguramente (com try/catch)
  - Exibe informações de campos faltantes
  - Inclui status HTTP na mensagem

```javascript
// Ensure userBucket is valid
let safeBucket = userBucket;
if (!safeBucket || typeof safeBucket !== 'string' || safeBucket.trim() === '') {
    safeBucket = 'lab01'; // Fallback to default
    console.warn('⚠️ userBucket inválido, usando fallback:', safeBucket);
}

// Error handling
if (!cloneResponse.ok) {
    let errorData = {};
    try {
        errorData = await cloneResponse.json();
    } catch (e) {
        errorData = { error: `HTTP ${cloneResponse.status}` };
    }
    
    // Build comprehensive error message
    let errMsg = errorData.message || errorData.error || 'Clone failed on server';
    const missingFields = errorData.missingFields ? '\nMissing: ' + errorData.missingFields.join(', ') : '';
    const debugInfo = errorData.debug ? '\n[DEBUG: ' + JSON.stringify(errorData.debug) + ']' : '';
    throw new Error(`Git clone error (${cloneResponse.status}): ${errMsg}${missingFields}${debugInfo}`);
}
```

### 3. Frontend - git-file-manager.js
**Arquivos**: 
- `/assets/js/git-file-manager.js`
- `/public/assets/js/git-file-manager.js`

**Mudanças**: Mesmas melhorias aplicadas ao arquivo de gerenciamento de git

## Arquivos Modificados
1. ✅ `/app/Controllers/GitServerController.php` - Backend validation
2. ✅ `/app/Views/code_editor/unified-code-editor.php` - Frontend validation
3. ✅ `/assets/js/git-file-manager.js` - Git manager script
4. ✅ `/public/assets/js/git-file-manager.js` - Public git manager script (se existir)

## Como Testar

### Para cliente com erro:
1. Fazer login no sistema
2. Abrir o Code Editor
3. Na aba Git, tentar conectar a um repositório
4. Se `userBucket` estiver inválido, deve agora:
   - Receber fallback automático para 'lab01'
   - Ver mensagem de aviso no console (F12)
   - Clone deve prosseguir normalmente

### Debug no console do navegador (F12):
```javascript
// Verificar userBucket
console.log('userBucket:', userBucket);

// Verificar gitConfig armazenado
console.log('gitConfig:', localStorage.getItem('gitConfig'));

// Procurar por avisos de fallback
// ⚠️ userBucket inválido, usando fallback: lab01
```

## Rollback (se necessário)
Se precisar reverter as mudanças:
```bash
git checkout HEAD~1 -- src/codeigniter-app/app/Controllers/GitServerController.php
git checkout HEAD~1 -- src/codeigniter-app/app/Views/code_editor/unified-code-editor.php
git checkout HEAD~1 -- src/codeigniter-app/assets/js/git-file-manager.js
```

## Notas Adicionais

### Fallback de bucket padrão
Se `userBucket` continuar faltando em todas as ocasiões, você pode configurar:
- Variável de ambiente: `DEFAULT_USER_BUCKET=seu-bucket-padrao`
- Ou hardcoded no código: Mude `'lab01'` para seu bucket padrão

### Próximos passos sugeridos
1. Monitorar logs para verificar quantas vezes o fallback é acionado
2. Se frequente, investigar por que a sessão não está disponível
3. Considerar adicionar middleware de autenticação mais robusto

## Referências
- Endpoint: `POST /api/git-clone`
- Controlador: `GitServerController::cloneRepository()`
- SessionHelper: `\App\Helpers\SessionHelper::getUserBucket()`
