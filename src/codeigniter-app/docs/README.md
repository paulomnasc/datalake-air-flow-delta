# 📚 Documentação HTML - Datalake

## 📂 Estrutura

```
docs/
├── index.html                          # 🏠 Home (README linhas 1-36)
├── docs-index.html                     # 📋 Índice completo
├── guide-webapp-config.html            # 🖥️ Guia da Interface Web
├── migracao-duckdb-postgresql.html     # 🔌 Conexão Power BI
├── transformacoes-silver.html          # 🥈 Transformações Silver
└── delta-lake-implementation.html      # 🥇 Delta Lake & Gold
```

## 🚀 Como Usar

### 1. Visualizar Localmente

Abra o arquivo `index.html` no navegador:

```bash
cd docs
firefox index.html   # Linux
open index.html      # Mac
start index.html     # Windows
```

### 2. Servir via HTTP (Nginx/Apache)

Configure o servidor web para apontar para a pasta `docs/`:

**Nginx:**
```nginx
location /docs {
    alias /root/datalake-air-flow-delta/docs;
    index index.html;
}
```

**Apache:**
```apache
Alias /docs /root/datalake-air-flow-delta/docs
<Directory /root/datalake-air-flow-delta/docs>
    Options Indexes FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>
```

### 3. Integrar na Aplicação Web

Adicione link no menu da aplicação CodeIgniter:

```php
// src/codeigniter-app/app/Views/templates/header.php
<nav>
    <ul>
        <li><a href="<?= base_url('home') ?>">Home</a></li>
        <li><a href="<?= base_url('docs/index.html') ?>" target="_blank">📚 Documentação</a></li>
    </ul>
</nav>
```

## 🔄 Regenerar Documentação

Quando atualizar os arquivos `.md`, execute:

```bash
python3 generate_docs.py
```

Isso irá:
- ✅ Ler os arquivos Markdown da raiz
- ✅ Converter para HTML formatado
- ✅ Aplicar navegação sidebar
- ✅ Manter links ativos entre páginas

## 📋 Arquivos Fonte

Os HTMLs são gerados a partir destes Markdown:

| Markdown | HTML | Descrição |
|----------|------|-----------|
| `README.md` (linhas 1-36) | `index.html` | Visão geral da solução |
| `DOCS_INDEX.md` | `docs-index.html` | Índice navegável completo |
| `GUIDE_WEBAPP_CONFIG.md` | `guide-webapp-config.html` | Interface de configuração |
| `MIGRACAO_DUCKDB_POSTGRESQL.md` | `migracao-duckdb-postgresql.html` | Power BI + PostgreSQL |
| `TRANSFORMACOES_SILVER.md` | `transformacoes-silver.html` | Camada Silver |
| `DELTA_LAKE_IMPLEMENTATION.md` | `delta-lake-implementation.html` | Camada Gold Delta |

## 🎨 Características

- ✅ **Sidebar navegável** fixa à esquerda
- ✅ **Responsivo** para desktop
- ✅ **Highlight** da página atual
- ✅ **Links internos** entre documentos
- ✅ **Formatação** de código, tabelas, listas
- ✅ **Badges** coloridos (✅ ❌ ⭐ ⚠️)
- ✅ **Sem dependências** externas (CSS inline)

## 📝 Personalização

Para alterar o estilo, edite o `<style>` em `generate_docs.py`:

```python
# Cores principais
sidebar_bg = '#2c3e50'      # Fundo da sidebar
link_color = '#3498db'       # Cor dos links
heading_color = '#2c3e50'    # Cor dos títulos
```

## 🔗 Navegação

Cada página HTML tem sidebar com:

- 🏠 **Home** → Visão geral
- 📋 **Índice Completo** → Todos os guias
- 🖥️ **Guia Interface Web** → Como usar o formulário
- 🔌 **Conexão Power BI** → Integração BI
- 🥈 **Transformações Silver** → Data Quality
- 🥇 **Delta Lake & Gold** → Features + ML

---

**Gerado automaticamente por:** `generate_docs.py`  
**Data:** Janeiro 2026
