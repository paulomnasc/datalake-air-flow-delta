# 💻 Code Editor com Monaco

## Visão Geral

Interface avançada de edição SQL com **Monaco Editor** (mesmo editor do VS Code) integrado ao DuckDB para execução de queries em arquivos Parquet.

## 🎯 Características

### Editor Monaco
- ✨ **Syntax Highlighting** completo para SQL
- 🌙 **Tema Dark** (vs-dark) profissional
- 📝 **Autocomplete** inteligente com snippets
- ⌨️ **Atalhos de teclado** (Ctrl+Enter para executar)
- 🎨 **Formatação automática** de código
- 🔍 **Minimap** de navegação
- 📋 **Multi-cursor** e edição avançada

### Funcionalidades
- 📊 Execução de queries SQL em arquivos Parquet
- 📁 Sidebar com lista de arquivos disponíveis
- 🔗 Clique em arquivo para inserir no editor
- 📚 Exemplos prontos (SELECT, JOIN, agregações, window functions)
- ⚡ Resultados em tabela formatada
- 🔢 Controle de limite de resultados
- 🛡️ Validações de segurança (acesso por usuário)

## 📍 Acesso

```
URL: /code-editor
Rota: CodeEditorController::index
```

## 🏗️ Arquitetura

### Controller
```
/src/codeigniter-app/app/Controllers/CodeEditorController.php
```

Métodos:
- `index()` - Renderiza interface
- `execute()` - Executa query SQL
- `listTables()` - Lista tabelas disponíveis
- `getSchema()` - Obtém schema de tabela
- `listParquetFiles()` - Lista arquivos Parquet do usuário

### View
```
/src/codeigniter-app/app/Views/code_editor/index.php
```

### Rotas
```php
// Code Editor - Monaco Editor with DuckDB
$routes->get('/code-editor', 'CodeEditorController::index');
$routes->post('/code-editor/execute', 'CodeEditorController::execute');
$routes->post('/code-editor/tables', 'CodeEditorController::listTables');
$routes->post('/code-editor/schema', 'CodeEditorController::getSchema');
$routes->post('/code-editor/files', 'CodeEditorController::listParquetFiles');
```

## 🎨 Layout

```
┌─────────────────────────────────────────────────────────┐
│  💻 SQL Code Editor              🟢 DuckDB Online      │
├──────────┬──────────────────────────────────────────────┤
│          │  ▶️ Executar  🎨 Formatar  🗑️ Limpar         │
│ 📁 Files │──────────────────────────────────────────────│
│          │                                              │
│ file1.p  │  SELECT *                                    │
│ file2.p  │  FROM read_parquet('s3://...')               │
│ file3.p  │  LIMIT 100;                                  │
│          │                                              │
│ 📊 Exem. │──────────────────────────────────────────────│
│          │  📊 Resultados                               │
│ SELECT   │  ┌──────┬──────┬──────┐                      │
│ JOIN     │  │ col1 │ col2 │ col3 │                      │
│ Agregaç. │  ├──────┼──────┼──────┤                      │
│ Window   │  │ val1 │ val2 │ val3 │                      │
│          │  └──────┴──────┴──────┘                      │
└──────────┴──────────────────────────────────────────────┘
```

## 🔧 Configuração Monaco

### CDN
```html
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs/loader.js"></script>
```

### Inicialização
```javascript
require.config({ 
    paths: { 
        vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs' 
    } 
});

require(['vs/editor/editor.main'], function () {
    editor = monaco.editor.create(document.getElementById('container'), {
        value: 'SELECT * FROM ...',
        language: 'sql',
        theme: 'vs-dark',
        automaticLayout: true,
        minimap: { enabled: true },
        fontSize: 14,
        // ... outras opções
    });
});
```

## ⌨️ Atalhos

| Atalho | Ação |
|--------|------|
| `Ctrl+Enter` | Executar query |
| `Ctrl+Space` | Autocomplete |
| `Alt+Shift+F` | Formatar documento |
| `Ctrl+F` | Buscar |
| `Ctrl+H` | Substituir |
| `Ctrl+/` | Comentar linha |

## 📝 Autocomplete Customizado

### Snippets SQL
```javascript
monaco.languages.registerCompletionItemProvider('sql', {
    provideCompletionItems: function(model, position) {
        return {
            suggestions: [
                {
                    label: 'read_parquet',
                    insertText: "read_parquet('s3://${bucket}/${file}.parquet')",
                    kind: monaco.languages.CompletionItemKind.Function
                },
                // ... mais snippets
            ]
        };
    }
});
```

## 🔒 Segurança

### Validações Implementadas

1. **Comandos Bloqueados**: DROP, DELETE, TRUNCATE, ALTER, CREATE, INSERT, UPDATE
2. **Isolamento de Buckets**: Usuários só acessam seus próprios buckets
3. **Validação de Paths**: Impede acesso a caminhos não autorizados
4. **Limite de Resultados**: Máximo de 10.000 linhas

### Exemplo de Validação
```php
private function _sanitizeSql(string $sql): string
{
    $dangerous = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE'];
    
    foreach ($dangerous as $cmd) {
        if (stripos($sql, $cmd) !== false) {
            throw new \RuntimeException("Comando {$cmd} não permitido");
        }
    }
    
    return $sql;
}
```

## 📊 Exemplos de Queries

### SELECT Básico
```sql
SELECT * 
FROM read_parquet('s3://user-1/bronze/dados.parquet')
LIMIT 100;
```

### JOIN
```sql
SELECT 
    a.id,
    a.nome,
    b.valor
FROM read_parquet('s3://user-1/bronze/tabela_a.parquet') a
JOIN read_parquet('s3://user-1/bronze/tabela_b.parquet') b
ON a.id = b.id
LIMIT 100;
```

### Agregações
```sql
SELECT 
    categoria,
    COUNT(*) as total,
    AVG(valor) as media,
    SUM(valor) as soma
FROM read_parquet('s3://user-1/bronze/vendas.parquet')
GROUP BY categoria
ORDER BY total DESC;
```

### Window Functions
```sql
SELECT 
    nome,
    departamento,
    salario,
    ROW_NUMBER() OVER (PARTITION BY departamento ORDER BY salario DESC) as rank
FROM read_parquet('s3://user-1/bronze/funcionarios.parquet')
ORDER BY departamento, rank;
```

## 🆚 Comparação com Query Builder

| Recurso | Query Builder | Code Editor |
|---------|---------------|-------------|
| Editor | Textarea simples | Monaco (VS Code) |
| Syntax Highlight | Não | ✅ Sim |
| Autocomplete | Não | ✅ Sim |
| Minimap | Não | ✅ Sim |
| Atalhos | Limitados | ✅ Completos |
| Formatação | Manual | ✅ Automática |
| Multi-cursor | Não | ✅ Sim |
| Tema Dark | Não | ✅ Sim |
| Uso | Queries rápidas | Edição profissional |

## 🚀 Como Usar

### 1. Acessar Interface
Faça login e acesse o menu **Ferramentas → Code Editor (Monaco)**

### 2. Escrever Query
Digite ou use os exemplos da sidebar

### 3. Executar
- Clique no botão **▶️ Executar**
- Ou pressione **Ctrl+Enter**

### 4. Ver Resultados
Resultados aparecem automaticamente abaixo do editor

## 🎯 Casos de Uso

### Análise Exploratória
```sql
-- Ver estrutura do arquivo
SELECT * FROM read_parquet('s3://bucket/file.parquet') LIMIT 5;

-- Contar registros
SELECT COUNT(*) FROM read_parquet('s3://bucket/file.parquet');

-- Ver colunas únicas
SELECT DISTINCT coluna FROM read_parquet('s3://bucket/file.parquet');
```

### Desenvolvimento de Queries
- Escrever queries complexas com autocomplete
- Formatar automaticamente para melhor legibilidade
- Testar e refinar antes de usar em DAGs

### Validação de Dados
- Verificar qualidade após ETL
- Comparar camadas (bronze vs silver vs gold)
- Auditar transformações

## 📦 Dependências

- **Monaco Editor**: v0.45.0 (via CDN)
- **DuckDB API**: Backend para execução
- **CodeIgniter 4**: Framework PHP
- **SessionHelper**: Controle de acesso por usuário
- **DuckDBHelper**: Interface com DuckDB

## 🔗 Links Úteis

- [Monaco Editor](https://microsoft.github.io/monaco-editor/)
- [Monaco API Reference](https://microsoft.github.io/monaco-editor/api/index.html)
- [DuckDB SQL Reference](https://duckdb.org/docs/sql/introduction)
- [Query Builder Original](../query_builder/index.php)

## 🐛 Troubleshooting

### Monaco não carrega
- Verifique conexão CDN
- Limpe cache do navegador
- Verifique console do navegador

### Queries não executam
- Verifique status DuckDB (badge no header)
- Verifique permissões de bucket
- Veja logs do CodeIgniter

### Arquivos não aparecem
- Verifique se usuário está logado
- Confirme existência de arquivos Parquet
- Verifique permissões MinIO/S3

## 📝 Notas de Desenvolvimento

### Performance
- Monaco é carregado via CDN (cache do navegador)
- Queries limitadas a 10.000 resultados
- Renderização de tabelas otimizada

### Futuras Melhorias
- [ ] Salvar queries favoritas
- [ ] Histórico de execuções
- [ ] Export de resultados (CSV, JSON)
- [ ] Visualização de planos de execução
- [ ] Temas customizáveis
- [ ] Múltiplas abas de edição

## 🤝 Contribuindo

Para adicionar novos snippets ao autocomplete:

```javascript
// Em code_editor/index.php
monaco.languages.registerCompletionItemProvider('sql', {
    provideCompletionItems: function(model, position) {
        return {
            suggestions: [
                {
                    label: 'meu_snippet',
                    kind: monaco.languages.CompletionItemKind.Snippet,
                    insertText: 'codigo_aqui',
                    documentation: 'Descrição do snippet'
                }
            ]
        };
    }
});
```

---

**Versão**: 1.0.0  
**Data**: Janeiro 2026  
**Autor**: Sistema Datalake
