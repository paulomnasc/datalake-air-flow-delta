#!/usr/bin/env python3
"""
src/fiscalweb/scripts/docx_exporter.py

Converte texto estruturado em Markdown / Bullets hierárquicos em um documento
Word (.docx) profissional e nativo, sem qualquer dependência externa (utiliza apenas
a biblioteca padrão do Python: zipfile, xml.sax.saxutils e io).
"""

import sys
import os
import zipfile
import re
import xml.sax.saxutils

def escape(txt):
    return xml.sax.saxutils.escape(txt)

def parse_runs(text):
    """
    Analisa marcações simples como **negrito** e *itálico* gerando elementos <w:r>.
    """
    tokens = re.split(r'(\*\*.*?\*\*|\*.*?\*)', text)
    runs = []
    
    for token in tokens:
        if not token:
            continue
        if token.startswith('**') and token.endswith('**') and len(token) >= 4:
            content = token[2:-2]
            runs.append(
                f'<w:r><w:rPr><w:b/><w:sz w:val="21"/></w:rPr>'
                f'<w:t xml:space="preserve">{escape(content)}</w:t></w:r>'
            )
        elif token.startswith('*') and token.endswith('*') and len(token) >= 2:
            content = token[1:-1]
            runs.append(
                f'<w:r><w:rPr><w:i/><w:sz w:val="21"/></w:rPr>'
                f'<w:t xml:space="preserve">{escape(content)}</w:t></w:r>'
            )
        else:
            runs.append(
                f'<w:r><w:rPr><w:sz w:val="21"/></w:rPr>'
                f'<w:t xml:space="preserve">{escape(token)}</w:t></w:r>'
            )
    return "".join(runs)

def text_to_docx(raw_text, output_path):
    content_types = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>'''

    rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>'''

    doc_rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>'''

    styles = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
        <w:sz w:val="21"/>
        <w:color w:val="222222"/>
      </w:rPr>
    </w:rPrDefault>
  </w:docDefaults>
</w:styles>'''

    bullet_symbols = ['●', '○', '▪', '▫', '–', '›']

    doc_body = []
    lines = raw_text.splitlines()

    for raw_line in lines:
        line = raw_line.rstrip()
        stripped = line.strip()

        if not stripped:
            doc_body.append('<w:p><w:pPr><w:spacing w:after="80"/></w:pPr></w:p>')
            continue

        # Linha horizontal / divisor
        if stripped in ['---', '***', '___']:
            p = (
                '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="4" w:color="CCCCCC"/>'
                '</w:pBdr><w:spacing w:before="120" w:after="120"/></w:pPr></w:p>'
            )
            doc_body.append(p)
            continue

        # Título Nível 1 (#)
        if stripped.startswith('# '):
            text_content = stripped[2:].strip()
            runs = parse_runs(text_content)
            p = (
                f'<w:p><w:pPr><w:spacing w:before="260" w:after="120"/>'
                f'<w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1565C0"/></w:rPr></w:pPr>'
                f'{runs}</w:p>'
            )
            doc_body.append(p)
            continue

        # Título Nível 2 (##)
        if stripped.startswith('## '):
            text_content = stripped[3:].strip()
            runs = parse_runs(text_content)
            p = (
                f'<w:p><w:pPr><w:spacing w:before="220" w:after="100"/>'
                f'<w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="1E3A8A"/></w:rPr></w:pPr>'
                f'{runs}</w:p>'
            )
            doc_body.append(p)
            continue

        # Título Nível 3 (###)
        if stripped.startswith('### '):
            text_content = stripped[4:].strip()
            runs = parse_runs(text_content)
            p = (
                f'<w:p><w:pPr><w:spacing w:before="180" w:after="80"/>'
                f'<w:rPr><w:b/><w:sz w:val="23"/><w:color w:val="2D3748"/></w:rPr></w:pPr>'
                f'{runs}</w:p>'
            )
            doc_body.append(p)
            continue

        # Título Nível 4 (####)
        if stripped.startswith('#### '):
            text_content = stripped[5:].strip()
            runs = parse_runs(text_content)
            p = (
                f'<w:p><w:pPr><w:spacing w:before="140" w:after="60"/>'
                f'<w:rPr><w:b/><w:sz w:val="22"/><w:color w:val="4A5568"/></w:rPr></w:pPr>'
                f'{runs}</w:p>'
            )
            doc_body.append(p)
            continue

        # Marcador de Bullet Point
        # Detecta marcadores como: '-', '*', '•', '1.', '2.' etc.
        bullet_match = re.match(r'^(\s*)([-*•]|\d+\.)\s+(.*)$', line)
        if bullet_match:
            leading_spaces = len(bullet_match.group(1))
            marker = bullet_match.group(2)
            item_text = bullet_match.group(3)

            # Nível de indentação calculado a cada 2 espaços ou tabulações
            level = min(leading_spaces // 2, 5)
            left_indent = 400 + (level * 360) # em twips (1/20 pt)
            hanging_indent = 240

            # Símbolo do marcador
            if marker.endswith('.'):
                symbol = marker + " "
                sym_color = "1565C0"
            else:
                symbol = bullet_symbols[level] + " "
                sym_color = "1976D2" if level == 0 else ("3182CE" if level == 1 else "4A5568")

            bullet_run = (
                f'<w:r><w:rPr><w:b/><w:color w:val="{sym_color}"/><w:sz w:val="20"/></w:rPr>'
                f'<w:t xml:space="preserve">{escape(symbol)} </w:t></w:r>'
            )
            runs = parse_runs(item_text)

            p = (
                f'<w:p><w:pPr><w:ind w:left="{left_indent}" w:hanging="{hanging_indent}"/>'
                f'<w:spacing w:after="50"/></w:pPr>'
                f'{bullet_run}{runs}</w:p>'
            )
            doc_body.append(p)
            continue

        # Parágrafo Comum
        indent_spaces = len(line) - len(line.lstrip())
        level = min(indent_spaces // 2, 5)
        left_indent = level * 360 if level > 0 else 0

        runs = parse_runs(stripped)
        ind_tag = f'<w:ind w:left="{left_indent}"/>' if left_indent > 0 else ''
        p = f'<w:p><w:pPr>{ind_tag}<w:spacing w:after="80"/></w:pPr>{runs}</w:p>'
        doc_body.append(p)

    doc_xml = f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    {"".join(doc_body)}
    <w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>'''

    with zipfile.ZipFile(output_path, 'w', compression=zipfile.ZIP_DEFLATED) as zf:
        zf.writestr('[Content_Types].xml', content_types)
        zf.writestr('_rels/.rels', rels)
        zf.writestr('word/_rels/document.xml.rels', doc_rels)
        zf.writestr('word/styles.xml', styles)
        zf.writestr('word/document.xml', doc_xml)

def main():
    if len(sys.argv) < 3:
        print("Uso: python3 docx_exporter.py <arquivo_entrada.txt> <arquivo_saida.docx>")
        sys.exit(1)

    input_file = sys.argv[1]
    output_file = sys.argv[2]

    if input_file == '-':
        raw_text = sys.stdin.read()
    else:
        with open(input_file, 'r', encoding='utf-8') as f:
            raw_text = f.read()

    text_to_docx(raw_text, output_file)
    print(f"Sucesso: {output_file} gerado.")

if __name__ == '__main__':
    main()

