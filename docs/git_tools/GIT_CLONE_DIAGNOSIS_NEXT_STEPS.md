# Investigação: Git Clone Error 400 - Próximas Etapas

## 📊 O Que Sabemos

✅ **Confirmado:**
- Git está instalado no servidor (`git version 2.43.0`)
- Diretório `/tmp/git-clone` está criado com permissões corretas
- SubscriptionFilter foi corrigido para whitelist APIs Git
- userBucket tem fallback automático

❓ **Ainda Desconhecido:**
- **O erro exato que ocorre quando git clone executa** - precisa vir dos logs
- Se é erro de autenticação (token inválido)
- Se é erro de conexão de rede
- Se é erro de repositório não encontrado
- Se é erro de permissão de arquivo

---

## 🔍 Como Diagnosticar o Erro Real

### Opção 1: Verificar Logs Após Próxima Tentativa
Quando Kauan tentar clonar novamente, **os novos logs mostrarão o erro exato**:

```bash
# Ver logs com prefixo [GIT CLONE] que adicionamos
tail -f /root/datalake-air-flow-delta/src/codeigniter-app/writable/logs/log-2026-01-23.log | grep GIT
```

Isso mostrará:
- ✅ `[GIT CLONE] Executing: git clone --depth 1 --branch main https://github.com/Kauan09-8/sql-scripts.git /tmp/git-clone/...`
- ✅ `[GIT CLONE] Failed with code 128: fatal: unable to access 'https://...': ...`

### Opção 2: Testar com Token do Kauan (Se Disponível)
Se tivermos o token exato que Kauan usa, podemos reproduzir:

```bash
git clone --depth 1 \
  "https://Kauan09-8:SEUTO KEN@github.com/Kauan09-8/sql-scripts.git" \
  /tmp/test-kauan-repo
```

### Opção 3: Verificar Possíveis Causas

**Se erro for "Authentication failed":**
```
fatal: Authentication failed for 'https://github.com/Kauan09-8/sql-scripts.git/'
```
→ Token expirado, revogado, ou sem permissão

**Se erro for "Repository not found":**
```
fatal: repository 'https://github.com/Kauan09-8/sql-scripts.git/' not found
```
→ Repositório privado e token sem acesso, ou URL errada

**Se erro for "Connection":**
```
fatal: Could not resolve host: github.com
Connection timeout
```
→ Problema de rede/firewall

**Se erro for "Branch not found":**
```
fatal: reference is not a tree: main
```
→ Branch 'main' não existe, talvez seja 'master' ou outro nome

---

## 🛠️ Correções Já Aplicadas

Que ajudarão a diagnosticar melhor:

1. **Validação de Git disponível**
   - Se git não existir, retorna erro claro
   - Antes: falhava silenciosamente

2. **Escaping seguro de shell**
   - Usa `escapeshellarg()` para evitar injeção
   - Antes: poderia ter caracteres especiais causando erro

3. **Sanitização de entrada**
   - Remove caracteres inválidos de owner/repo/branch
   - Antes: caracteres especiais poderiam causar falhas

4. **Detecção de erro expandida**
   - Agora detecta vários tipos de erro (auth, repo not found, network, etc)
   - Retorna mensagem amigável correspondente
   - Antes: sempre retornava mensagem genérica

5. **Logging detalhado**
   - Registra comando exato executado
   - Registra saída completa de erro
   - Antes: sem rastreamento

---

## 📋 Próximo Passo

**Aguardar Kauan tentar clonar novamente com:**
1. Username corrigido (certeza de que está certo)
2. Token válido (não expirado)
3. Repositório acessível (público ou privado com permissão)

Quando fizer, verificar logs com:
```bash
tail -50 /root/datalake-air-flow-delta/src/codeigniter-app/writable/logs/log-2026-01-23.log
```

E procurar por linhas com `[GIT CLONE]` que mostrarão o erro exato.

---

## 🎯 Possível Solução Se For Token

Se o problema for token expirado/inválido, Kauan deve:

1. Ir em: https://github.com/settings/tokens
2. Gerar novo **Personal Access Token (Classic)** com scopes:
   - `repo` (full control)
3. Usar o novo token na próxima tentativa

---

**Status**: Aguardando próxima tentativa do Kauan para ver erro nos logs com as correções aplicadas
**Data**: 23 de Janeiro de 2026
