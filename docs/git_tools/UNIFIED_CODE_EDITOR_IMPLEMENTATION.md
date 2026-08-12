# Unified Code Editor - Implementação Completa ✅

## Resumo da Mudança

Consolidação de duas views separadas (`code-editor.php` e `validation-rules-editor.php`) em uma única view unificada (`unified-code-editor.php`) com sistema de abas.

### Arquivos Criados

1. **`/app/Views/code_editor/unified-code-editor.php`** (727 linhas)
   - View unificada com sidebar Git única
   - Dois tab panels: SQL Editor | Validation Rules
   - Estilos consolidados de ambas as views
   - Inclui Git loader 2000ms delay

2. **`/public/assets/js/unified-editor.js`** (690 linhas)
   - Gerenciador de estado central
   - Dois editores Monaco (SQL + Python)
   - Lazy loading do editor Python
   - Git integration via CustomEvent
   - Handlers para SQL (executeQuery, formatSQL, downloadCSV)
   - Handlers para Validation (testValidation, saveValidation, deployValidator)

### Arquivos Modificados

1. **`/app/Config/Routes.php`**
   - Rota `/code-editor` → `CodeEditorController::unified()` (novo)
   - Rota `/validation-rules-editor` → redirect para `/code-editor` (redireciona para unified)
   - Adicionada rota `/api/query-sql` → `CodeEditorController::querySQL()` (novo)
   - Mantidas rotas de API existentes (Git, validation rules)

2. **`/app/Controllers/CodeEditorController.php`**
   - Método `unified()` → carrega unified-code-editor.php
   - Método `index()` → redireciona para `/code-editor` (compatibilidade)
   - Método `querySQL()` → wrapper para execute() do QueryBuilderController
   - Mantém herança de QueryBuilderController (execute, listTables, getSchema, etc)

3. **`/app/Views/header.php`**
   - Menu atualizado: ambos links apontam para `/code-editor`
   - Novo rótulo: "💻 SQL Editor + Validações"
   - Mantém dropdown na seção de Ferramentas

## Arquitetura

### Fluxo de Operação

```
Usuário acessa /code-editor
    ↓
unified-code-editor.php carregada (CodeEditorController::unified())
    ├── Sidebar Git única (git-sidebar.php)
    ├── Tab 1: SQL Editor
    │   ├── Monaco SQL editor
    │   ├── Toolbar (Execute, Format, Clear, Download CSV)
    │   └── Results section (tabelas, gráficos, etc)
    │
    └── Tab 2: Validation Rules
        ├── Monaco Python editor
        ├── Left: Rules list + Templates
        ├── Right: Editor + Test results
        └── Toolbar (Test, Save, Deploy)

Git file selection:
    Clique em arquivo na sidebar
        ↓
    CustomEvent 'git-file-selected' disparado
        ↓
    unified-editor.js escuta evento
        ↓
    Detecta extensão (.py → validation | .sql/.parquet → sql)
        ↓
    Ativa tab correto e carrega conteúdo no editor
```

### Estado Global

```javascript
currentTab = 'sql' | 'validation'
editorSQL = Monaco editor instance (SQL)
editorPython = Monaco editor instance (Python) - lazy loaded
currentGitFile = filepath do arquivo aberto no Git
gitConfig = { owner, repo, token, username, branch }
userBucket = 'lab01' (ou outro bucket do usuário)
```

### localStorage

Um único localStorage key:
- `gitConfig` - armazena credenciais GitHub para ambos editores

(Eliminado o conflito anterior com `validationGitConfig`)

## Funcionalidades

### SQL Editor Tab

✅ Execute queries com DuckDB  
✅ Format SQL via Monaco  
✅ Clear editor  
✅ Download resultados como CSV  
✅ Git file selection automática  
✅ Limit control (1-10000 linhas)  

### Validation Rules Tab

✅ Test validação Python (sintaxe básica)  
✅ Save arquivo para GitHub  
✅ Deploy para Airflow  
✅ Rules list com grid responsivo  
✅ Templates (empty, nullCheck, typeCheck, rangeCheck)  
✅ Git file selection automática  

### Git Sidebar (Shared)

✅ Uma única sidebar (não duplicada)  
✅ Funciona em ambas tabs  
✅ Carrega .sql, .parquet, .py files  
✅ CustomEvent 'git-file-selected' integrado  
✅ Compatível com git-file-manager.js global  

## Vantagens da Consolidação

| Aspecto | Antes | Depois |
|---------|-------|--------|
| Views | 2 (code-editor.php + validation-rules-editor.php) | 1 (unified-code-editor.php) |
| localStorage keys | 2 (gitConfig + validationGitConfig) | 1 (gitConfig) |
| Sidebar Git | 2 instâncias | 1 instância reutilizada |
| JavaScript global scope | Conflitos (pfs, git duplicados) | Limpo (um único git-file-manager.js) |
| CSS | ~1500 linhas (duplicado) | ~650 linhas (consolidado) |
| Maintenance | Corrigir bug em 2 lugares | Corrigir em 1 lugar |
| User Experience | Menu com 2 links diferentes | 1 link com abas internas |

## Testes a Executar

### 1. Carregamento da View

```bash
# Acesse
http://localhost/code-editor

# Esperado:
- Página carrega sem erros JS
- Tab "SQL Editor" ativa por default
- Tab "Validation Rules" acessível
- Sidebar Git visível (fechado por default)
```

### 2. SQL Editor

```bash
# 1. Clique "📁 Arquivos"
# 2. Selecione arquivo .parquet ou .sql no Git
# Esperado:
  - Sidebar abre
  - Conteúdo carrega no editor SQL
  - SQL syntax highlighting

# 3. Clique "▶️ Executar"
# 4. Cole query exemplo:
  SELECT * FROM read_parquet('s3://lab01/bronze/seu-arquivo.parquet') LIMIT 100;
# Esperado:
  - Query executa
  - Resultados aparecem em tabela
  - Botão "📄 Baixar CSV" ativa
```

### 3. Validation Rules

```bash
# 1. Clique tab "🛡️ Validation Rules"
# Esperado:
  - Editor Python carrega (lazy load)
  - Template padrão preenchido
  - Rules list carrega

# 2. Clique em rule existente (ou template)
# Esperado:
  - Código aparece no editor

# 3. Clique "▶️ Testar"
# Esperado:
  - Validação rápida: função validate() existe?
  - Resultado positivo ou erro

# 4. Clique "🔗 GitHub" (sidebar)
# 5. Conecte credenciais
# 6. Selecione arquivo .py
# Esperado:
  - Arquivo carrega no editor Python
  - Conteúdo Python válido

# 7. Clique "💾 Salvar"
# Esperado:
  - Salvo no GitHub com novo nome

# 8. Clique "🚀 Implantar"
# Esperado:
  - Deploy modal aparece
  - Sincronização para /opt/airflow/dags/
```

### 4. Git Integration

```bash
# 1. Na aba SQL Editor, clique "📁 Arquivos"
# 2. Conecte GitHub
# 3. Selecione arquivo .sql
# Esperado:
  - Ativa tab SQL
  - Carrega conteúdo no editor SQL

# 4. Mude para aba Validation Rules
# 5. Selecione arquivo .py
# Esperado:
  - Ativa tab Validation
  - Carrega conteúdo no editor Python

# 6. Volte para aba SQL
# 7. Selecione outro arquivo .parquet
# Esperado:
  - Permanece em SQL
  - Carrega novo conteúdo
```

### 5. localStorage

```javascript
// No console do browser:
localStorage.getItem('gitConfig')
// Esperado: { owner, repo, token, username, branch } (um único, não duplicate)
```

## Rollback (se necessário)

Se houver problemas:

1. Restaurar rotas para apontar às views antigas:
```php
$routes->get('/code-editor', 'CodeEditorController::index');
$routes->get('/validation-rules-editor', 'ValidationRulesController::index');
```

2. Manter `unified-code-editor.php` como backup

3. Manter `unified-editor.js` como backup

## Notas de Implementação

- **Lazy loading**: Editor Python apenas inicializado quando tab ativado (performance)
- **Reuse Git**: Uma única sidebar, sem duplicação de lógica de conexão
- **Backward compatibility**: Links antigos ainda funcionam (redirecionam)
- **API compatibility**: Todos endpoints existentes mantidos
- **No breaking changes**: Controllers herdados mantêm métodos originais

## Próximas Melhorias (Future)

- [ ] Tab persistence: salvar última tab ativa em sessionStorage
- [ ] Dark mode: adicionar tema escuro
- [ ] Split view: SQL e Python lado a lado (opcional)
- [ ] History: salvar últimas 10 queries/validações
- [ ] Shortcuts: Ctrl+Enter para executar/testar
- [ ] Themes: syntax highlighting themes customizáveis
