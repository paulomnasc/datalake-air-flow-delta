# Guia Completo - Editor de Código Unificado

## 📋 Visão Geral

O **Editor de Código Unificado** (`unified-code-editor.php`) consolida duas funcionalidades anteriormente separadas em uma única interface com abas:

1. **SQL Editor** (originário de `code-editor.php`)
2. **Validations Tab** (originário de `validation-rules-editor.php`)

Esta abordagem oferece:
- ✅ Experiência unificada para SQL e Python
- ✅ Git-sidebar compartilhado entre abas
- ✅ Transição suave entre edição de consultas e validadores
- ✅ Sem duplicação de componentes

---

## 🎯 Funcionalidades

### Aba SQL Editor

**Para quê:** Escrever, testar e executar queries SQL

**Toolbars disponíveis:**
- 🎯 **Execute** - Executar query contra banco de dados
- 🎨 **Format** - Formatar código SQL (indentação, keywords)
- 🗑️ **Clear** - Limpar editor
- 💾 **Download CSV** - Exportar resultados

**Exemplo de uso:**
```sql
SELECT 
    customer_id,
    COUNT(*) as transaction_count
FROM gold.customers
GROUP BY customer_id
LIMIT 100;
```

---

### Aba Validations

**Para quê:** Criar, testar e fazer deploy de validadores Python

**Estrutura do validador:**
```python
class MeuValidador:
    def __call__(self, source_filename: str, target_table_name: str, **context):
        # Executa pipeline Medallion
        pipeline_result = raw_to_medallion(source_filename, target_table_name, **context)
        
        # Aplica validações customizadas
        self.custom_validations(pipeline_result, target_table_name, **context)
        
        return pipeline_result

def validate(df):
    """Função dummy para compatibilidade com webapp"""
    return df
```

**Toolbars disponíveis:**
- ▶️ **Test** - Validar sintaxe Python
- 💾 **Save** - Salvar no Git
- 🚀 **Deploy** - Sincronizar para Airflow
- 📋 **Templates** - Inserir templates predefinidos

---

## 🔄 Fluxo de Trabalho Completo

### Para SQL:

```
1. Aba SQL Editor
   ↓
2. Escrever query SQL
   ↓
3. Clicar Execute (ou Ctrl+Enter)
   ↓
4. Ver resultados na grid
   ↓
5. (Opcional) Download CSV
```

### Para Validações:

```
1. Aba Validations
   ↓
2. (a) Criar novo arquivo Python ou
      (b) Selecionar arquivo do Git-sidebar
   ↓
3. Escrever código validador
   ↓
4. Clicar Test (verificar sintaxe)
   ↓
5. Clicar Save (commit no Git)
   ↓
6. Clicar Deploy (sincronizar para Airflow)
   ↓
7. Verificar em Airflow Web UI (http://localhost:28083)
```

---

## 📂 Arquivos Envolvidos

### Frontend (Views)

| Arquivo | Propósito |
|---------|-----------|
| `unified-code-editor.php` | View principal com abas SQL + Validations |
| `git-sidebar.php` | Componente compartilhado de Git |
| `git-file-manager.js` | API JavaScript para Git |

**Localização:** `/src/codeigniter-app/app/Views/code_editor/`

### Backend (Controllers)

| Arquivo | Rotas | Propósito |
|---------|-------|----------|
| `CodeEditorController.php` | `GET /unified-code-editor` | Carrega view unificada |
| `CodeQueryController.php` | `POST /api/execute-query` | Executa queries SQL |
| `ValidationRulesController.php` | `POST /api/validation-deploy` | Deploy de validadores |

**Localização:** `/src/codeigniter-app/app/Controllers/`

### JavaScript

| Arquivo | Funções Principais |
|---------|-------------------|
| `git-file-manager.js` | `loadGitFileContent()`, `saveGitFile()` |
| `unified-code-editor.php` (inline) | `switchMainTab()`, `deployValidator()`, `saveValidation()` |

---

## 🚀 Como Usar - Guia Passo a Passo

### Acessar o Editor

```
URL: http://localhost:28088/unified-code-editor
```

### Cenário 1: Criar um novo validador

```
1. Vá para Aba: Validations
2. Clique em: + Novo
3. Digite nome: meu_validador.py
4. Escolha Template: Empty / Null Check / Duplicate Check / Type Check
5. Escreva seu código Python
6. Teste: ▶️ Test
7. Salve: 💾 Save
8. Implante: 🚀 Deploy
```

**Resposta esperada:**
```json
{
    "success": true,
    "message": "✅ meu_validador.py salvo em /datalake-root!",
    "file_path": "/datalake-root/meu_validador.py",
    "next_step": "Execute: cd /datalake-root && bash sync_validators_to_airflow.sh meu_validador.py"
}
```

### Cenário 2: Editar validador existente

```
1. Vá para Aba: Validations
2. Clique no arquivo no Git-sidebar (left panel)
3. Arquivo carrega no editor
4. Faça suas edições
5. Clique Save
6. Clique Deploy
```

### Cenário 3: Executar uma query SQL

```
1. Vá para Aba: SQL Editor
2. Cole ou escreva sua query
3. Clique Execute (ou Ctrl+Enter)
4. Resultados aparecem em grid abaixo
5. (Opcional) Clique Download CSV
```

---

## 🧪 Templates de Validadores

### Template 1: Empty (Vazio)
Arquivo mínimo com função `validate()` para testes.

### Template 2: Null Check (Verificação de Nulos)
Valida e trata valores nulos em colunas específicas.

```python
def validate(df):
    """Verificar e tratar valores nulos"""
    for col in ['billingpostalcode', 'customer_id']:
        df[col] = df[col].fillna('None')
    return df
```

### Template 3: Duplicate Check (Verificação de Duplicatas)
Identifica e remove registros duplicados.

```python
def validate(df):
    """Remover registros duplicados"""
    original_count = len(df)
    df = df.drop_duplicates()
    removed = original_count - len(df)
    print(f"Removidos {removed} registros duplicados")
    return df
```

### Template 4: Type Check (Verificação de Tipos)
Converte e valida tipos de dados.

```python
def validate(df):
    """Converter tipos de dados"""
    df['customer_id'] = df['customer_id'].astype(int)
    df['amount'] = df['amount'].astype(float)
    return df
```

---

## 🔧 APIs Backend

### POST /api/git-file-save

**Salvar arquivo no Git**

```bash
curl -X POST http://localhost:28088/api/git-file-save \
  -H "Content-Type: application/json" \
  -d '{
    "path": "validators/meu_validador.py",
    "content": "def validate(df): return df",
    "message": "Adicionar novo validador"
  }'
```

### POST /api/git-file-content

**Carregar arquivo do Git**

```bash
curl -X POST http://localhost:28088/api/git-file-content \
  -H "Content-Type: application/json" \
  -d '{"path": "validators/meu_validador.py"}'
```

### POST /api/validation-deploy

**Fazer deploy de validador**

```bash
curl -X POST http://localhost:28088/api/validation-deploy \
  -H "Content-Type: application/json" \
  -d '{
    "filename": "meu_validador.py",
    "content": "def validate(df): return df"
  }'
```

**Resposta:**
```json
{
    "success": true,
    "message": "✅ meu_validador.py salvo em /datalake-root!",
    "file_path": "/datalake-root/meu_validador.py",
    "next_step": "Execute: cd /datalake-root && bash sync_validators_to_airflow.sh meu_validador.py"
}
```

---

## 📊 Verificar Deploy no Airflow

### Método 1: Airflow Web UI
```
1. Acesse: http://localhost:28083
2. Login: admin / admin
3. Procure por sua DAG (ex: meu_validador)
4. Verifique status
```

### Método 2: Terminal
```bash
# Listar arquivos em /opt/airflow/dags/
docker exec airflow-scheduler ls -la /opt/airflow/dags/ | grep seu_arquivo.py

# Verificar logs do Airflow
docker exec airflow-scheduler airflow dags list | grep seu_validador
```

### Método 3: Verificar arquivo salvo
```bash
# Arquivo salvo em /datalake-root
ls -la /root/datalake-air-flow-delta/seu_arquivo.py
```

---

## 🐛 Troubleshooting

### Problema: Deploy retorna erro "Permission denied"

**Solução:**
```bash
docker exec -u root codeigniter-app chmod 666 /datalake-root/*.py
docker exec -u root codeigniter-app chown www-data:www-data /datalake-root/*.py
```

### Problema: Arquivo não aparece em /opt/airflow/dags/

**Solução:**
1. Verifique se o script `sync_validators_to_airflow.sh` existe
2. Execute manualmente no host:
```bash
cd /root/datalake-air-flow-delta
bash sync_validators_to_airflow.sh seu_arquivo.py
```

### Problema: Aba Validations não carrega

**Solução:**
1. Abra console do navegador (F12)
2. Procure por erros em JavaScript
3. Verifique se Monaco Editor CDN está acessível
4. Recarregue página (Ctrl+F5)

### Problema: Git-sidebar vazio

**Solução:**
1. Verifique se repositório Git está inicializado:
```bash
cd /root/datalake-air-flow-delta
git status
```
2. Se não existir, crie repositório:
```bash
git init
git config user.email "dev@example.com"
git config user.name "Developer"
```

---

## 📝 Notas Técnicas

### Arquitetura JavaScript

```javascript
// Variáveis globais
var editor;           // Monaco SQL Editor
var editorValidation; // Monaco Python Editor (lazy loaded)
var currentTab;       // 'sql' ou 'validation'
var currentGitFile;   // { path, name }

// Funções principais
switchMainTab(tab)         // Trocar aba
initValidationEditor()     // Inicializar editor Python
testValidation()          // Testar sintaxe
saveValidation()          // Salvar no Git
deployValidator()         // Deploy para Airflow
loadGitFileContent(path)  // Carregar arquivo Git
```

### Fluxo de Git-sidebar

```
Git-sidebar (único para ambas abas)
    ↓
Evento: git-file-selected
    ↓
switch (currentTab)
    ├─ 'sql' → loadGitFileContent() → editorSQL
    └─ 'validation' → loadGitFileContent() → editorValidation
```

### Lazy Loading do Editor Python

O `editorValidation` é criado **apenas** quando o usuário clica na aba Validations, reduzindo overhead inicial.

---

## 🔐 Segurança

### Sanitização de Nomes de Arquivo
```php
$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
```
Apenas caracteres alfanuméricos, ponto e underscore são permitidos.

### Validação de Entrada
- Arquivo não pode estar vazio
- Caminho deve ser `/datalake-root/`
- Apenas extensão `.py` para validadores

### Permissões de Arquivo
```
/datalake-root/  → drwxrwxrwx (777)
*.py files        → -rw-rw-rw- (666)
Owner: www-data:www-data
```

---

## 📚 Referências

- **Monaco Editor:** https://microsoft.github.io/monaco-editor/
- **isomorphic-git:** https://isomorphic-git.org/
- **CodeIgniter 4:** https://codeigniter.com/user_guide/
- **Airflow:** https://airflow.apache.org/

---

## ✅ Checklist de Implementação

- [x] View unificada (`unified-code-editor.php`) criada
- [x] Abas SQL + Validations implementadas
- [x] Git-sidebar compartilhado entre abas
- [x] Roteamento de arquivos para editor correto
- [x] Templates de validadores implementados
- [x] Botões Test, Save, Deploy funcionando
- [x] Deploy API corrigida (permissões)
- [x] Documentação completa

---

**Última atualização:** 16 de janeiro de 2026  
**Versão:** 1.0  
**Status:** ✅ Produção
