# 🧪 Página de Teste: Git Sidebar Component

## 🎯 URL de Acesso

```
http://seu-servidor/test-git-sidebar
```

## ✅ O que foi criado:

1. **View**: `/src/codeigniter-app/app/Views/code_editor/test-git-sidebar.php`
2. **Rota**: `GET /test-git-sidebar` → `CodeEditorController::testGitSidebar()`
3. **Controller**: Método `testGitSidebar()` adicionado ao `CodeEditorController.php`

## 🎨 Features da Página de Teste:

### Layout
- **Sidebar (esquerda)**: Componente `git-sidebar.php` com filtro `.py`
- **Main (direita)**: Área de exibição do conteúdo selecionado

### Informações Exibidas
- Filtro de arquivo atual (`.py`)
- User bucket
- localStorage key (`gitConfig`)
- Status do evento (aguardando/carregado)

### Funcionalidades
1. **Conectar GitHub** (sidebar)
2. **Selecionar arquivo .py** (sidebar)
3. **Ver conteúdo** (área principal)
4. **Limpar editor** (botão)

## 📋 Como Testar:

### 1. Acessar a página
```
http://localhost:28080/test-git-sidebar
```

### 2. Conectar ao GitHub
- Preencher username
- Preencher token
- Preencher repo (ex: `usuario/meu-repo`)
- Clicar "✓ Conectar"

### 3. Selecionar Arquivo
- Navegar na árvore de arquivos
- Clicar em um arquivo `.py`
- **Validação**: Se clicar em `.sql` ou outro, mostra erro

### 4. Verificar Console (F12)
```javascript
// Logs esperados:
🧪 Página de teste carregada
📂 Filtro de arquivo: .py
✅ Evento git-file-selected recebido: {...}
📝 Conteúdo carregado: ...
```

### 5. Verificar Evento
- Status muda para: ✅ nome_arquivo.py carregado
- Conteúdo aparece na área principal
- Botão "🗑️ Limpar" funciona

## 🔍 O que Validar:

### ✅ Checklist de Testes

- [ ] Sidebar carrega corretamente
- [ ] Formulário de conexão GitHub aparece
- [ ] Consegue conectar ao GitHub
- [ ] Árvore de arquivos carrega
- [ ] Filtro `.py` funciona (rejeita outros tipos)
- [ ] Evento `git-file-selected` é emitido
- [ ] Conteúdo do arquivo aparece na tela
- [ ] Botão limpar funciona
- [ ] Console mostra logs corretos
- [ ] localStorage `gitConfig` é salvo
- [ ] Reload da página mantém conexão

## 🐛 Troubleshooting

### Erro: "Sidebar não carrega"
```bash
# Verificar se componente existe:
ls src/codeigniter-app/app/Views/components/git-sidebar.php
```

### Erro: "git-file-manager.js não encontrado"
```bash
# Verificar arquivo:
ls src/codeigniter-app/assets/js/git-file-manager.js
```

### Erro: "Evento não dispara"
```javascript
// No console, verificar:
console.log(window.gitSidebarFileFilter); // Deve ser '.py'

// Verificar se listener está ativo:
window.addEventListener('git-file-selected', (e) => {
    console.log('TESTE:', e.detail);
});
```

### Erro: "Filtro não funciona"
```javascript
// Verificar se variável está definida:
console.log(window.gitSidebarFileFilter); // '.py'

// Forçar teste:
window.dispatchEvent(new CustomEvent('git-file-selected', {
    detail: { filepath: 'test.py', content: 'print("hello")' }
}));
```

## 📊 Resultados Esperados

### Sucesso ✅
- Sidebar com botões e formulários corretos
- Conexão GitHub funciona
- Arquivos `.py` podem ser selecionados
- Arquivos não-`.py` são bloqueados com alert
- Conteúdo aparece na área principal
- Console sem erros

### Falha ❌
- Sidebar vazia ou com erro
- Não consegue conectar GitHub
- Todos os arquivos são aceitos (filtro não funciona)
- Evento não dispara
- Conteúdo não aparece
- Erros no console

## 🚀 Próximos Passos

Se todos os testes passarem:
1. Integrar em `code-editor.php` (filtro `.parquet` ou `.sql`)
2. Integrar em `validation-rules-editor.php` (filtro `.py`)
3. Remover código duplicado das páginas
4. Deletar página de teste (opcional)

---

**Boa sorte nos testes!** 🎯
