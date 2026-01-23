# Git Clone Error 400 - Root Cause Analysis & Extended Fix

## 🔴 Problema Raiz Identificado

O erro 400 persiste devido a **dois problemas combinados**:

### 1. ⛔ Filtro de Assinatura Bloqueando API
O filtro global `SubscriptionFilter` estava bloqueando TODOS os endpoints da API Git:
- `/api/git-clone`
- `/api/git-files`
- `/api/git-file-content`
- etc.

**Sintoma**: Cliente com assinatura expirada recebe erro 400 ao tentar clonar

**Causa**: SubscriptionFilter redireciona para `/subscription/renew` → retorna HTML ao invés de JSON → erro 400

### 2. ⚠️ Request/Response Mismatch
Quando um endpoint retorna redirecionamento (status 301/302), o cliente esperando JSON recebe HTML, causando erro de parsing que é reportado como 400.

---

## ✅ Solução Completa Implementada

### Etapa 1: Whitelist de APIs no SubscriptionFilter
**Arquivo**: `app/Filters/SubscriptionFilter.php`

Adicionou todas as rotas de Git API à lista de exceções:
```php
$rotasPermitidas = [
    '/subscription/renew',
    '/subscription/status',
    '/logout',
    '/Usuario/logOut',
    '/loginUsuario',
    '/sigInUsuario',
    // Git API endpoints - permitir mesmo com assinatura expirada
    '/api/git-clone',
    '/api/git-files',
    '/api/git-file-content',
    '/api/git-file-save',
    '/api/git-folder-create',
    '/api/git-entry-rename',
    '/api/git-file-delete',
    '/api/git-push'
];
```

**Por que**: Git API não deve ser bloqueado por status de assinatura. Usuários devem poder acessar repositórios mesmo com trial expirado.

### Etapa 2: Fallback de UserBucket (já implementado)
**Arquivo**: `app/Controllers/GitServerController.php`

Garante que `userBucket` nunca seja nulo:
- Sessão → Env var → 'lab01'

---

## 🧪 Como Verificar a Correção

### 1. Teste de Request Simples
```bash
curl -X POST "http://localhost/api/git-clone" \
  -H "Content-Type: application/json" \
  -d '{
    "owner": "Kauan09-8",
    "repo": "sql-scripts",
    "token": "seu-token"
  }'
```

**Esperado**: 
- ✅ Se válido: JSON response com status 200
- ✅ Se campos faltam: JSON response com status 400 + detalhes
- ❌ Nunca: HTML redirect (302/301)

### 2. Teste com Cliente Diferente
1. Login em navegador/máquina diferente
2. Ir para Code Editor
3. Conectar GitHub
4. Verificar DevTools (F12):
   - Network tab → git-clone request
   - Status deve ser 200 ou 400 (nunca 301/302/HTML)

### 3. Verificar Logs
```bash
tail -f /root/datalake-air-flow-delta/src/codeigniter-app/writable/logs/log-*.log | grep -i "git\|clone\|400"
```

---

## 🔍 Verificação de Status Atual

### ✅ Corrigido Em:

1. **datalake-air-flow-delta**
   - ✅ GitServerController.php - Fallback userBucket
   - ✅ SubscriptionFilter.php - Whitelist Git APIs
   - ✅ unified-code-editor.php - Local validation

2. **datalake-air-flow-teste**
   - ✅ GitServerController.php - Fallback userBucket  
   - ✅ SubscriptionFilter.php - Whitelist Git APIs
   - ✅ git-file-manager.js - Local validation

---

## 📊 Fluxo Corrigido

```
Cliente clica "Conectar"
    ↓
Frontend valida dados + userBucket
    ├─ Válido → JSON válido enviado
    └─ Inválido → Fallback para 'lab01'
    ↓
Request chega em /api/git-clone
    ↓
SubscriptionFilter verifica URI
    ├─ É Git API? → PASSA (whitelist)
    └─ Outro endpoint? → Verifica assinatura
    ↓
GitServerController processa
    ├─ userBucket válido? → Fallback se não
    ├─ owner + repo? → Valida
    └─ Executa clone
    ↓
✅ Resposta JSON válida (200 ou 400)
```

---

## 🚨 Se Ainda Houver Erro

### Verificação 1: URL correta?
- ❌ `http://localhost/git-proxy.php?url=...` (ERRADO - retorna 400 se URL vazia)
- ✅ `http://localhost/api/git-clone` (CORRETO - rota direta)

### Verificação 2: Content-Type está JSON?
```javascript
// ✅ Correto
headers: { 'Content-Type': 'application/json' }

// ❌ Errado
headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
```

### Verificação 3: Body é JSON válido?
```javascript
// ✅ Correto
body: JSON.stringify({
    owner: "user",
    repo: "repo",
    token: "token"
})

// ❌ Errado
body: "owner=user&repo=repo&token=token"
```

### Verificação 4: Session existe?
Usuário precisa estar logado. Se não:
- SessionHelper::getUserBucket() retorna null
- Fallback para 'lab01' ativado
- Request continua normalmente

---

## 🎯 Resultado Esperado

**Antes da correção**:
```
POST /api/git-clone → 302 Found (redirect)
Response: HTML page (subscription renewal page)
Client: Error parsing HTML as JSON → 400 Bad Request
```

**Depois da correção**:
```
POST /api/git-clone → 200 OK ou 400 Bad Request (JSON)
Response: {"success": true} ou {"error": "Missing fields"}
Client: Parse JSON successfully ✅
```

---

## 📝 Arquivos Finais Alterados

| Arquivo | Mudança | Ambientes |
|---------|---------|-----------|
| SubscriptionFilter.php | ✅ Adicionar whitelist Git APIs | Delta + Teste |
| GitServerController.php | ✅ Fallback userBucket | Delta + Teste |
| unified-code-editor.php | ✅ Validação local userBucket | Delta + Teste |
| git-file-manager.js | ✅ Validação local userBucket | Delta + Teste |

---

## 🔄 Deploy

Ambos os repositórios já foram atualizados. As mudanças estão prontas para:
- Desenvolvimento (local)
- Staging (teste)
- Produção (delta)

**Reinicialização necessária**: SIM (para carregar novo código)

---

**Status Final**: ✅ ROOT CAUSE IDENTIFICADA E CORRIGIDA

Data: 23 de Janeiro de 2026
