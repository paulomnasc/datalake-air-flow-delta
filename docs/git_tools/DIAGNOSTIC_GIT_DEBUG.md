# 🔍 Script de Diagnóstico - Git Validation Rules

Se o Validation Rules Editor não está mostrando os arquivos do GitHub, abra o **DevTools (F12)** e execute os comandos abaixo na aba **Console**.

## 1️⃣ Verificar Conexão Salva

```javascript
// Verifica se a configuração está em localStorage
const config = localStorage.getItem('validationGitConfig');
console.log('=== localStorage.validationGitConfig ===');
if (config) {
    const parsed = JSON.parse(config);
    console.log('✅ ENCONTRADO');
    console.log('Owner:', parsed.owner);
    console.log('Repo:', parsed.repo);
    console.log('Token:', parsed.token ? '✓ Presente' : '✗ Faltando');
    console.log('Branch:', parsed.branch);
} else {
    console.log('❌ NÃO ENCONTRADO - Você precisa conectar ao GitHub primeiro');
}
```

## 2️⃣ Verificar Elementos do DOM

```javascript
// Verifica se os elementos HTML existem
console.log('=== Verificação de Elementos ===');
console.log('gitFileTree:', document.getElementById('gitFileTree') ? '✅ Existe' : '❌ Não existe');
console.log('gitConnected:', document.getElementById('gitConnected') ? '✅ Existe' : '❌ Não existe');
console.log('gitNotConnected:', document.getElementById('gitNotConnected') ? '✅ Existe' : '❌ Não existe');
console.log('gitLoadingStatus:', document.getElementById('gitLoadingStatus') ? '✅ Existe' : '❌ Não existe');
```

## 3️⃣ Testar API de Arquivos

```javascript
// Testa a chamada à API
const config = JSON.parse(localStorage.getItem('validationGitConfig'));
if (config) {
    const url = `/api/git-files?userBucket=lab01&owner=${config.owner}&repo=${config.repo}`;
    console.log('=== Teste de API ===');
    console.log('URL:', url);
    console.log('Fazendo fetch...');
    
    fetch(url)
        .then(r => {
            console.log('Status:', r.status);
            return r.json();
        })
        .then(data => {
            console.log('✅ Resposta OK');
            console.log('Arquivos:', data.files ? data.files.length : 'Nenhum');
            if (data.files) {
                data.files.slice(0, 5).forEach(f => console.log('  -', f.path));
                if (data.files.length > 5) console.log('  ... e mais', data.files.length - 5);
            }
        })
        .catch(err => console.error('❌ Erro:', err.message));
} else {
    console.log('❌ Nenhuma configuração salva');
}
```

## 4️⃣ Verificar Variáveis Globais Git

```javascript
// Verifica se as bibliotecas Git estão carregadas
console.log('=== Verificação de Bibliotecas Git ===');
console.log('window.git:', typeof window.git !== 'undefined' ? '✅ Carregado' : '❌ Não carregado');
console.log('window.LightningFS:', typeof window.LightningFS !== 'undefined' ? '✅ Carregado' : '❌ Não carregado');
console.log('window.gitConfig:', window.gitConfig ? '✅ Presente' : '❌ Não presente');
```

## 5️⃣ Forçar Recarga de Arquivos

```javascript
// Se tudo estiver conectado, tente forçar carregar os arquivos
if (window.gitConfig && window.gitConfig.owner) {
    console.log('=== Forçando Recarga ===');
    console.log('Chamando loadGitFiles()...');
    loadGitFiles(); // Esta função deve estar disponível globalmente
} else {
    console.log('❌ gitConfig não está configurado');
}
```

## 6️⃣ Verificar Console.log Completo

```javascript
// Mostra os últimos 20 logs (aproximadamente)
// Limpe o console (Ctrl+Shift+K) e então:
// 1. Clique em "🔗 GitHub"
// 2. Preencha Username, Token, Repo
// 3. Clique em "✓ Conectar"
// 4. Observe os logs no console
console.log('Observe os logs acima para detalhes da execução');
console.log('Procure por mensagens como:');
console.log('  📂 loadGitFiles() chamado');
console.log('  🌐 Fazendo fetch para: /api/git-files?...');
console.log('  ✅ JSON parseado');
console.log('  🔍 renderGitFileTree chamada');
```

---

## 📋 Checklist de Diagnóstico

Execute cada comando acima e marque o resultado:

- [ ] **Step 1**: `validationGitConfig` encontrado em localStorage?
- [ ] **Step 2**: Elementos DOM existem (`gitFileTree`, `gitConnected`, etc)?
- [ ] **Step 3**: API `/api/git-files` retorna status 200?
- [ ] **Step 4**: `window.git` e `window.LightningFS` carregados?
- [ ] **Step 5**: `loadGitFiles()` executa sem erros?
- [ ] **Step 6**: Console mostra logs esperados?

---

## 🆘 Possíveis Problemas e Soluções

### Problema 1: ❌ localStorage.validationGitConfig não encontrado
**Solução**: Você precisa clicar em "🔗 GitHub" e conectar à sua conta

### Problema 2: ❌ Status 404 na chamada `/api/git-files`
**Solução**: A rota não está registrada. Verifique se o controller ValidationRulesController tem esse método.

### Problema 3: ❌ Status 401 na chamada `/api/git-files`
**Solução**: Token inválido ou expirado. Gere um novo token em https://github.com/settings/tokens/new

### Problema 4: ✅ API retorna dados mas arquivos não aparecem
**Solução**: Há um erro no `renderGitFileTree()`. Procure por erros no console como "renderGitFileTree chamada".

### Problema 5: ❌ window.git não carregado
**Solução**: As bibliotecas CDN não carregaram. Abra DevTools → Network e procure por erros de CORS.

---

## 📊 Resultado Esperado

Se tudo estiver funcionando, você verá no console algo como:

```
🔍 restoreGitFromStorage(DOMContentLoaded) -> EXISTE
✅ gitConfig restaurado: {owner: "seu_user", repo: "validators", ...}
📂 Carregando arquivos Git...
🌐 Fazendo fetch para: /api/git-files?userBucket=lab01&owner=seu_user&repo=validators
✅ Resposta recebida. Status: 200
✅ JSON parseado. Arquivos: 5
🔍 renderGitFileTree chamada com: 5 arquivos
✅ Elemento gitFileTree encontrado
🌳 Construindo árvore de arquivos...
✅ Árvore construída, renderizando...
✅ Árvore renderizada com sucesso
```

E **no sidebar, você verá a lista de arquivos do repositório** com folders e arquivos que você pode clicar para carregar.

---

## 🔧 Debug Avançado

Se ainda assim não funcionar, tente:

### Verificar se há erro CORS

```javascript
// Veja os headers da requisição
fetch('/api/git-files?userBucket=lab01&owner=seu_user&repo=seu_repo', {
    method: 'GET',
    credentials: 'include'
})
.then(r => {
    console.log('Status:', r.status);
    console.log('Headers:', [...r.headers.entries()]);
    return r.json();
})
.then(d => console.log('Data:', d))
.catch(e => console.error('Erro:', e));
```

### Verificar se há erro de parsing JSON

```javascript
fetch('/api/git-files?userBucket=lab01&owner=seu_user&repo=seu_repo')
    .then(r => r.text())  // Pega como texto primeiro
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            console.log('JSON válido:', data);
        } catch (e) {
            console.error('Erro ao fazer parse de JSON:', e);
        }
    });
```

---

## 📞 Compartilhar Resultado

Se encontrar um erro, copie a mensagem completa e compartilhe para investigação mais profunda.
