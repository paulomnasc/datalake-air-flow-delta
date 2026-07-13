#!/usr/bin/env python3
# Copyright (C) 2026 Paulo Nascimento - Este programa é um software livre licenciado sob a GNU Affero General Public License v3.
"""
Gerador de documentação HTML a partir de arquivos Markdown
"""
import os
import re
import unicodedata
from pathlib import Path

# Configurações
PROJECT_ROOT = Path("/root/datalake-air-flow-delta")
DOCS_DIR = PROJECT_ROOT / "docs"
DOCS_DIR_APP = PROJECT_ROOT / "src" / "codeigniter-app" / "docs"
MD_FILES = {
    "DOCS_INDEX.md": "docs-index.html",
    "GUIDE_WEBAPP_CONFIG.md": "guide-webapp-config.html",
    "MIGRACAO_DUCKDB_POSTGRESQL.md": "migracao-duckdb-postgresql.html",
    "PowerBI_Conexao_DuckDB_ODBC.md": "powerbi-conexao-duckdb-odbc.html",
    "TRANSFORMACOES_SILVER.md": "transformacoes-silver.html",
    "DELTA_LAKE_IMPLEMENTATION.md": "delta-lake-implementation.html",
    "DELTA_SHARING_OPERATIONAL.md": "delta-sharing-operational.html",
    "GUIDE_INSTAGRAM_N8N.md": "guide-instagram-n8n.html",
}

# Template HTML base
HTML_TEMPLATE = """<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title} - Documentação Datalake</title>
    <style>
        :root {{
            --sidebar-width: 280px;
        }}
        * {{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }}

        body {{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }}

        .container {{
            min-height: 100vh;
        }}

        .sidebar {{
            width: var(--sidebar-width);
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            transform: translateX(0);
            z-index: 1000;
        }}

        .sidebar.collapsed {{
            transform: translateX(-100%);
        }}

        .sidebar h2 {{
            font-size: 1.4rem;
            margin-bottom: 20px;
            color: #3498db;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }}

        .sidebar nav ul {{
            list-style: none;
        }}

        .sidebar nav ul li {{
            margin-bottom: 8px;
        }}

        .sidebar nav ul li a {{
            color: #ecf0f1;
            text-decoration: none;
            display: block;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background 0.3s;
        }}

        .sidebar nav ul li a:hover {{
            background: #34495e;
            color: #3498db;
        }}

        .sidebar nav ul li a.active {{
            background: #3498db;
            color: white;
        }}

        .content {{
            margin-left: var(--sidebar-width);
            padding: 40px;
            background: white;
            max-width: 1400px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }}

        .content.collapsed {{
            margin-left: 0;
        }}

        .sidebar-overlay {{
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.45);
            z-index: 900;
            display: none;
        }}

        .sidebar-overlay.active {{
            display: block;
        }}

        .sidebar-toggle {{
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1100;
            background: #3498db;
            color: #fff;
            border: none;
            padding: 10px 14px;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            font-size: 0.95rem;
            transition: left 0.3s ease;
        }}

        @media (min-width: 1100px) {{
            .sidebar-toggle {{
                left: calc(var(--sidebar-width) + 16px);
            }}
        }}

        .content h1 {{
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2.5rem;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }}

        .content h2 {{
            color: #34495e;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.8rem;
            border-bottom: 2px solid #ddd;
            padding-bottom: 5px;
        }}

        .content h3 {{
            color: #555;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }}

        .content h4 {{
            color: #666;
            margin-top: 15px;
            margin-bottom: 8px;
            font-size: 1.2rem;
        }}

        .content p {{
            margin-bottom: 15px;
            color: #555;
        }}

        .content ul {{
            margin-bottom: 15px;
            margin-left: 20px;
        }}

        .content ul li {{
            margin-bottom: 8px;
        }}

        .content ol {{
            margin-bottom: 15px;
            margin-left: 20px;
        }}

        .content ol li {{
            margin-bottom: 8px;
        }}

        .content a {{
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }}

        .content a:hover {{
            text-decoration: underline;
        }}

        .badge {{
            display: inline-block;
            padding: 3px 8px;
            background: #3498db;
            color: white;
            border-radius: 3px;
            font-size: 0.85rem;
            margin-left: 5px;
        }}

        .badge.star {{
            background: #f39c12;
        }}

        .badge.warning {{
            background: #e74c3c;
        }}

        .badge.success {{
            background: #27ae60;
        }}

        code {{
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #c7254e;
            word-break: break-word;
            overflow-wrap: break-word;
        }}

        pre {{
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            font-size: 0.9rem;
        }}

        pre code {{
            background: none;
            padding: 0;
            color: #333;
            font-size: 0.85rem;
            word-break: normal;
            overflow-wrap: normal;
            white-space: pre;
        }}

        table {{
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            word-break: break-word;
            overflow-wrap: break-word;
        }}

        table th {{
            background: #3498db;
            color: white;
            padding: 10px;
            text-align: left;
            border: 1px solid #2980b9;
        }}

        table td {{
            padding: 10px;
            border: 1px solid #ddd;
        }}

        table tr:nth-child(even) {{
            background: #f9f9f9;
        }}

        blockquote {{
            border-left: 4px solid #3498db;
            padding-left: 15px;
            margin: 15px 0;
            color: #555;
            font-style: italic;
        }}

        hr {{
            border: none;
            border-top: 1px solid #ddd;
            margin: 30px 0;
        }}

        .alert {{
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }}

        .alert-info {{
            background: #d9edf7;
            border-left: 4px solid #31708f;
            color: #31708f;
        }}

        .alert-warning {{
            background: #fcf8e3;
            border-left: 4px solid #8a6d3b;
            color: #8a6d3b;
        }}

        .alert-success {{
            background: #dff0d8;
            border-left: 4px solid #3c763d;
            color: #3c763d;
        }}

        .alert-danger {{
            background: #f2dede;
            border-left: 4px solid #a94442;
            color: #a94442;
        }}

        .masked-value {{
            font-family: 'Courier New', monospace;
            user-select: none;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 3px;
            background: #f4f4f4;
            transition: all 0.3s ease;
        }}

        .masked-value:hover {{
            background: #e0e0e0;
            text-decoration: underline;
        }}

        .masked-value.revealed {{
            background: #ffffcc;
        }}
    </style>
    <script>
        // Mascara valores sensíveis (passwords, tokens, secrets)
        function maskSensitiveValues() {{
            const sensitivePatterns = [
                {{'regex': /password\s*=\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'password'}},
                {{'regex': /passwd\s*=\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'password'}},
                {{'regex': /pwd\s*=\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'password'}},
                {{'regex': /secret(?:_key|_access|_key_id)?\s*=\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'secret'}},
                {{'regex': /api[_-]?key\s*=\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'apikey'}},
                {{'regex': /token\s*=\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'token'}},
                {{'regex': /authorization\s*:\s*['\"]?([^'\"\\s;]+)['\"]?/gi, 'label': 'auth'}},
                {{'regex': /bearer\s+([\\S]+)/gi, 'label': 'token'}}
            ];

            const codeBlocks = document.querySelectorAll('code, pre');
            
            codeBlocks.forEach(block => {{
                let content = block.innerHTML;
                
                sensitivePatterns.forEach(pattern => {{
                    content = content.replace(pattern.regex, (match, value) => {{
                        if (!value) return match;
                        
                        const prefix = match.substring(0, match.indexOf(value));
                        const masked = '*'.repeat(Math.min(value.length, 12));
                        const tooltip = `Clique para revelar o valor`;
                        
                        return `${{prefix}}<span class="masked-value" title="${{tooltip}}" data-original="${{value}}" onclick="toggleMask(event)">${{masked}}</span>`;
                    }});
                }});
                
                block.innerHTML = content;
            }});
        }}

        // Toggle visibilidade de valores mascarados
        function toggleMask(event) {{
            event.stopPropagation();
            const span = event.target;
            
            if (span.classList.contains('revealed')) {{
                const original = span.getAttribute('data-original');
                span.textContent = '*'.repeat(Math.min(original.length, 12));
                span.classList.remove('revealed');
            }} else {{
                const original = span.getAttribute('data-original');
                span.textContent = original;
                span.classList.add('revealed');
            }}
        }}

        function setSidebarState(isOpen) {{
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleBtn = document.getElementById('toggleSidebar');
            if (!sidebar || !content || !toggleBtn) return;

            sidebar.classList.toggle('collapsed', !isOpen);
            content.classList.toggle('collapsed', !isOpen);

            const useOverlay = window.innerWidth < 1100;
            if (overlay) {{
                overlay.classList.toggle('active', isOpen && useOverlay);
            }}

            toggleBtn.textContent = isOpen ? '✕' : '☰';
            
            // Ajusta posição do botão em telas maiores
            if (window.innerWidth >= 1100) {{
                toggleBtn.style.left = isOpen ? 'calc(var(--sidebar-width) + 16px)' : '16px';
            }} else {{
                toggleBtn.style.left = '16px';
            }}
        }}

        function initSidebarToggle() {{
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleBtn = document.getElementById('toggleSidebar');
            if (!sidebar || !content || !toggleBtn) return;

            const initialOpen = window.innerWidth >= 1100;
            setSidebarState(initialOpen);

            toggleBtn.addEventListener('click', function() {{
                const isOpen = !sidebar.classList.contains('collapsed');
                setSidebarState(!isOpen);
            }});

            if (overlay) {{
                overlay.addEventListener('click', function() {{
                    setSidebarState(false);
                }});
            }}

            window.addEventListener('resize', function() {{
                const shouldBeOpen = window.innerWidth >= 1100;
                setSidebarState(shouldBeOpen);
            }});
        }}

        // Executar quando documento carregar
        document.addEventListener('DOMContentLoaded', function() {{
            maskSensitiveValues();
            initSidebarToggle();
        }});
    </script>
</head>
<body>
    <button id="toggleSidebar" class="sidebar-toggle" aria-label="Alternar menu">☰</button>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>📚 Documentação</h2>
            <nav>
                <ul>
                    <li><a href="index.html" {index_active}>🏠 Home</a></li>
                    <li><a href="docs-index.html" {docs_index_active}>📋 Índice Completo</a></li>
                    <li><a href="guide-webapp-config.html" {guide_active}>🖥️ Guia Interface Web</a></li>
                    <li><a href="powerbi-conexao-duckdb-odbc.html" {migracao_active}>🔌 Conexão Power BI</a></li>
                    <li><a href="transformacoes-silver.html" {silver_active}>🥈 Transformações Silver</a></li>
                    <li><a href="delta-lake-implementation.html" {delta_active}>🥇 Delta Lake & Gold</a></li>
                    <li><a href="delta-sharing-operational.html" {sharing_active}>🤝 Delta Sharing Gold</a></li>
                    <li><a href="git-dbt-analytics.html" {git_active}>📊 Git & dbt Analytics</a></li>
                    <li><a href="dbt-quality-reports.html" {dbt_reports_active}>📊 Relatórios dbt</a></li>
                    <li><a href="metabase-analytics.html" {metabase_active}>📈 Metabase Analytics</a></li>
                    <li><a href="MyDataFloConfigurandoAPI.html" {api_active}>🛠️ Configurando API (Exemplo)</a></li>
                    <li><a href="guide-instagram-n8n.html" {instagram_active}>📸 Automação Instagram n8n</a></li>
                    {sidebar_extra}
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
{content}
        </main>
    </div>
</body>
</html>
"""


def slugify(text: str) -> str:
    """Gera um slug ASCII seguro para anchors a partir de um título."""
    normalized = unicodedata.normalize('NFKD', text)
    ascii_text = normalized.encode('ascii', 'ignore').decode('ascii')
    ascii_text = ascii_text.lower()
    ascii_text = re.sub(r'[^\w\s-]', '', ascii_text)
    ascii_text = re.sub(r'[\s_]+', '-', ascii_text).strip('-')
    ascii_text = re.sub(r'-{2,}', '-', ascii_text)
    return ascii_text or 'section'


def convert_markdown_table_to_html(markdown_table):
    """Converte tabela Markdown para tabela HTML"""
    lines = [line.strip() for line in markdown_table.split('\n') if line.strip()]
    
    if len(lines) < 3:
        return markdown_table
    
    # Verifica se é uma tabela válida (linha de separador)
    if not re.match(r'^\|?[\s\-|:]+\|?$', lines[1]):
        return markdown_table
    
    html_table = '<table>\n'
    
    # Header
    header_cells = [cell.strip() for cell in lines[0].split('|') if cell.strip()]
    html_table += '<thead><tr>\n'
    for cell in header_cells:
        html_table += f'<th>{cell}</th>\n'
    html_table += '</tr></thead>\n'
    
    # Body
    html_table += '<tbody>\n'
    for line in lines[2:]:
        if not line.strip() or re.match(r'^[\s\-|:]+$', line):
            continue
        cells = [cell.strip() for cell in line.split('|') if cell.strip()]
        if cells:
            html_table += '<tr>\n'
            for cell in cells:
                html_table += f'<td>{cell}</td>\n'
            html_table += '</tr>\n'
    html_table += '</tbody>\n</table>'
    
    return html_table


def md_to_html(md_content):
    """Converte Markdown básico para HTML"""
    html = md_content
    
    # Converte tabelas Markdown antes de outras conversões
    table_pattern = r'^\|.+\|$\n^\|[\s\-|:]+\|$(?:\n^\|.+\|$)+' 
    tables = re.finditer(table_pattern, html, re.MULTILINE)
    table_matches = list(tables)
    for match in reversed(table_matches):
        table_md = match.group(0)
        table_html = convert_markdown_table_to_html(table_md)
        html = html[:match.start()] + table_html + html[match.end():]
    
    # Headers com anchors (slugify)
    def heading_replacer(level):
        pattern = r'^' + ('#' * level) + r'\s+(.*?)$'

        def repl(match):
            title = match.group(1).strip()
            anchor = slugify(title)
            return f'<h{level} id="{anchor}">{title}</h{level}>'

        return pattern, repl

    for level in [4, 3, 2, 1]:
        pattern, repl = heading_replacer(level)
        html = re.sub(pattern, repl, html, flags=re.MULTILINE)
    
    # Code blocks
    html = re.sub(r'```(\w+)?\n(.*?)\n```', r'<pre><code>\2</code></pre>', html, flags=re.DOTALL)
    
    # Inline code
    html = re.sub(r'`([^`]+)`', r'<code>\1</code>', html)
    
    # Bold
    html = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', html)
    
    # Links (Markdown -> HTML) com mapeamento de .md -> .html
    def replace_link(match):
        text = match.group(1)
        href = match.group(2)

        # Quebra em base + anchor, se houver
        anchor = ''
        base_href = href
        if '#' in href:
            base_href, anchor = href.split('#', 1)
            anchor = slugify(anchor) if anchor else ''
            anchor = f'#{anchor}' if anchor else ''

        # Mapear arquivos .md para os HTML gerados
        md_to_html_map = {
            'README.md': 'index.html',
            'DOCS_INDEX.md': 'docs-index.html',
            'GUIDE_WEBAPP_CONFIG.md': 'guide-webapp-config.html',
            'MIGRACAO_DUCKDB_POSTGRESQL.md': 'migracao-duckdb-postgresql.html',
            'PowerBI_Conexao_DuckDB_ODBC.md': 'powerbi-conexao-duckdb-odbc.html',
            'TRANSFORMACOES_SILVER.md': 'transformacoes-silver.html',
            'DELTA_LAKE_IMPLEMENTATION.md': 'delta-lake-implementation.html',
            'DELTA_SHARING_OPERATIONAL.md': 'delta-sharing-operational.html',
        }

        # Se for um .md conhecido, troca para .html correspondente
        if base_href.endswith('.md'):
            clean = base_href.replace('./', '')
            if clean in md_to_html_map:
                base_href = md_to_html_map[clean]
            else:
                base_href = clean[:-3] + '.html'

        # Se o link era só um anchor (#section)
        if base_href == '' and anchor:
            return f'<a href="{anchor}">{text}</a>'

        return f'<a href="{base_href}{anchor}">{text}</a>'

    # Converter imagens Markdown para HTML antes da conversão de links normais
    html = re.sub(r'!\[([^\]]*)\]\(([^\)]+)\)', r'<img src="\2" alt="\1" style="max-width: 100%; height: auto; margin: 15px 0; border-radius: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">', html)

    html = re.sub(r'\[([^\]]+)\]\(([^\)]*)\)', replace_link, html)
    
    # Lists
    lines = html.split('\n')
    in_ul = False
    in_ol = False
    result = []
    
    for line in lines:
        # Unordered list
        if re.match(r'^\s*[-*]\s+', line):
            if not in_ul:
                result.append('<ul>')
                in_ul = True
            item = re.sub(r'^\s*[-*]\s+', '', line)
            result.append(f'<li>{item}</li>')
        # Ordered list
        elif re.match(r'^\s*\d+\.\s+', line):
            if not in_ol:
                result.append('<ol>')
                in_ol = True
            item = re.sub(r'^\s*\d+\.\s+', '', line)
            result.append(f'<li>{item}</li>')
        else:
            if in_ul:
                result.append('</ul>')
                in_ul = False
            if in_ol:
                result.append('</ol>')
                in_ol = False
            
            # Paragraphs
            if line.strip() and not line.startswith('<'):
                result.append(f'<p>{line}</p>')
            else:
                result.append(line)
    
    if in_ul:
        result.append('</ul>')
    if in_ol:
        result.append('</ol>')
    
    # HR
    html = '\n'.join(result)
    html = re.sub(r'^---$', '<hr>', html, flags=re.MULTILINE)
    
    # Badges
    html = html.replace('⭐', '<span class="badge star">⭐</span>')
    html = html.replace('✅', '<span class="badge success">✅</span>')
    html = html.replace('❌', '<span class="badge warning">❌</span>')
    html = html.replace('⚠️', '<span class="badge warning">⚠️</span>')
    
    return html


# Substituições de credenciais sensíveis por *******
SENSITIVE_REPLACEMENTS = {
    "dbtSharingToken2026Secure": "*******",
    "admin123": "*******",
    "minio123": "*******",
    "minioadmin": "*******",
    "pbi_password": "*******",
    "58NOR7zPJBtUBvA7": "*******"
}


def mask_secrets_statically(text):
    """Substitui senhas e tokens expostos por ******* de forma estática"""
    result = text
    # Dicionário ordenado por tamanho decrescente da chave para evitar substituições parciais
    replacements = sorted(SENSITIVE_REPLACEMENTS.items(), key=lambda x: len(x[0]), reverse=True)
    for original, masked in replacements:
        result = result.replace(original, masked)
    return result


def save_html_files(html_file, title, html_content, active_flags):
    """Auxiliar para salvar a versão externa (mascarada) e interna (com link extra)"""
    all_flags = {
        'index_active': '',
        'docs_index_active': '',
        'guide_active': '',
        'migracao_active': '',
        'silver_active': '',
        'delta_active': '',
        'sharing_active': '',
        'git_active': '',
        'dbt_reports_active': '',
        'metabase_active': '',
        'api_active': '',
        'instagram_active': '',
        'credenciais_active': '',
    }
    all_flags.update(active_flags)
    
    # 1. Aplicar mascaramento estático para a versão externa
    masked_content = mask_secrets_statically(html_content)
    
    # 2. Versão externa (docs/) - sem link de credenciais na sidebar (apenas para arquivos públicos)
    if html_file != 'credenciais.html':
        external_flags = all_flags.copy()
        external_flags['sidebar_extra'] = ''
        
        final_html_external = HTML_TEMPLATE.format(
            title=title,
            content=masked_content,
            **external_flags
        )
        
        output_path = DOCS_DIR / html_file
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write(final_html_external)
        
    # 3. Versão interna (src/codeigniter-app/docs/) - com link de credenciais
    internal_flags = all_flags.copy()
    cred_active = 'class="active"' if html_file == 'credenciais.html' else ''
    internal_flags['sidebar_extra'] = f'<li><a href="credenciais.html" {cred_active}>🔑 Credenciais Internas</a></li>'
    
    content_to_write = html_content if html_file == 'credenciais.html' else masked_content
    
    final_html_internal = HTML_TEMPLATE.format(
        title=title,
        content=content_to_write,
        **internal_flags
    )
    
    output_path_app = DOCS_DIR_APP / html_file
    with open(output_path_app, 'w', encoding='utf-8') as f:
        f.write(final_html_internal)
        
    print(f"✅ {html_file} criado (externo e interno)")


def generate_credentials_html():
    """Gera o arquivo de credenciais internas no formato Markdown e salva como HTML"""
    md_content = """# 🔑 Credenciais e Segredos do Datalake

Esta página centraliza todas as credenciais e segredos configurados e utilizados na infraestrutura e nos pipelines do MyDataFlow.

> [!WARNING]
> **Acesso Confidencial**: Estas credenciais são de uso exclusivamente interno. Não as compartilhe nem as exponha em ambientes públicos ou commits do Git.

---

## 🔌 Delta Sharing Server

O Delta Sharing permite que analistas externos leiam dados da camada Gold de forma controlada.

- **Endpoint de Conexão**: `https://myflow.estudotabela.com.br/delta-sharing`
- **Bearer Token**: `dbtSharingToken2026Secure`

---

## 🗂️ MinIO / S3 Object Storage

O MinIO armazena todas as camadas do Data Lake (Raw, Bronze, Silver, Gold).

### Credenciais Administrativas (Console e Servidores)
- **Host / Endpoint**: `http://minio:9000` / `https://myflow.estudotabela.com.br:29000`
- **Access Key**: `admin`
- **Secret Key**: `admin123`

### Credenciais da Aplicação (Camada Silver)
- **Access Key**: `minio`
- **Secret Key**: `minio123`

### Credenciais Spark Local (Camada Gold / Spark SQL Thrift)
- **Access Key**: `minioadmin`
- **Secret Key**: `minioadmin`

---

## 🐘 Banco de Dados PostgreSQL (BI / Analytics)

O PostgreSQL armazena dados de modelagem analítica e de BI.

- **Database**: `datalake_bi`
- **Usuário**: `pbi_user`
- **Senha**: `pbi_password`
- **Porta Externa**: `25433` / `5433`

---

## ✈️ APIs de Terceiros (Exemplos)

### Amadeus Flights API (Integração Kiwi / Voos)
- **Client ID**: `EAVO8QrhAjfmbdM5tuXLsFgRY2uJQsK6`
- **Client Secret**: `58NOR7zPJBtUBvA7`
- **Auth Endpoint**: `https://test.api.amadeus.com/v1/security/oauth2/token`

---

## ⚙️ Variáveis de Ambiente da Aplicação (.env)

Outros segredos estão definidos no arquivo `.env` na raiz do CodeIgniter:
- **Senha Root MySQL**: `YM11rMrT32xH0E6N`
- **Chave de Criptografia CI4**: `hex2bin:3682f0d41371c328b887285ed623b8c65d49d1f9174d6d6a512cc9f2c4afd05b`
- **SMTP Production Password**: `kJ#212394`
- **Google Client Secret (OAuth2)**: `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`
- **Hotmart Hottok**: `"P86JGEKszx0x6ZdpZC9AEEkoH0s9uL33784478"`
- **Metabase Admin Password**: `kJ#212394`
- **Metabase JWT Shared Secret**: `myflow_metabase_secret_key_sso_123456`
"""

    html_content = md_to_html(md_content)
    active_flags = {
        'credenciais_active': 'class="active"'
    }
    
    save_html_files('credenciais.html', "Credenciais Internas", html_content, active_flags)


def generate_index_html():
    """Gera o index.html especificamente a partir das linhas 1-36 do README.md"""
    readme_path = PROJECT_ROOT / "README.md"
    
    if not readme_path.exists():
        print(f"⚠️  README.md não encontrado")
        return
    
    # Ler apenas as primeiras 36 linhas do README
    with open(readme_path, 'r', encoding='utf-8') as f:
        lines = f.readlines()[:36]
    
    md_content = ''.join(lines)
    
    # Converter para HTML
    html_content = md_to_html(md_content)
    
    active_flags = {
        'index_active': 'class="active"',
    }
    
    save_html_files('index.html', "Home - Solução Híbrida Datalake", html_content, active_flags)


def generate_html_from_md(md_file, html_file):
    """Gera arquivo HTML a partir de Markdown"""
    md_path = PROJECT_ROOT / md_file
    
    if not md_path.exists():
        print(f"⚠️  Arquivo não encontrado: {md_file}")
        return
    
    # Ler conteúdo
    with open(md_path, 'r', encoding='utf-8') as f:
        md_content = f.read()
    
    # Converter para HTML
    html_content = md_to_html(md_content)
    
    # Extrair título (primeira linha com #)
    title_match = re.search(r'^#\s+(.+?)$', md_content, re.MULTILINE)
    title = title_match.group(1) if title_match else html_file.replace('.html', '')
    
    # Determinar qual link está ativo
    active_flags = {}
    
    if 'docs-index' in html_file:
        active_flags['docs_index_active'] = 'class="active"'
    elif 'guide-webapp' in html_file:
        active_flags['guide_active'] = 'class="active"'
    elif 'powerbi-conexao' in html_file or 'migracao' in html_file:
        active_flags['migracao_active'] = 'class="active"'
    elif 'transformacoes-silver' in html_file:
        active_flags['silver_active'] = 'class="active"'
    elif 'delta-lake' in html_file:
        active_flags['delta_active'] = 'class="active"'
    elif 'delta-sharing-operational' in html_file:
        active_flags['sharing_active'] = 'class="active"'
    elif 'guide-instagram-n8n' in html_file:
        active_flags['instagram_active'] = 'class="active"'
    
    save_html_files(html_file, title, html_content, active_flags)


def main():
    """Gera todos os arquivos HTML"""
    print("\n" + "="*70)
    print("🔧 GERADOR DE DOCUMENTAÇÃO HTML")
    print("="*70 + "\n")
    
    # Criar diretórios se não existirem
    DOCS_DIR.mkdir(exist_ok=True)
    DOCS_DIR_APP.mkdir(exist_ok=True)
    
    # Gerar index.html primeiro
    generate_index_html()
    
    # Gerar HTMLs dos demais markdown
    for md_file, html_file in MD_FILES.items():
        generate_html_from_md(md_file, html_file)
        
    # Gerar o arquivo único de credenciais internas
    generate_credentials_html()
    
    print("\n" + "="*70)
    print(f"✅ Arquivos HTML gerados em: {DOCS_DIR} e {DOCS_DIR_APP}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
