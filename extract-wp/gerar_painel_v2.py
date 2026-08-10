#!/usr/bin/env python3
import csv
import html
import urllib.parse
import os

def main():
    csv_file = "/root/datalake-air-flow-delta/extract-wp/arquivos/todos_membros_whatsapp.csv"
    output_html = "/root/datalake-air-flow-delta/extract-wp/arquivos/links_whatsapp_v2.html"

    test_contacts = [
        {"number": "5561991117028", "display": "+55 61 99111-7028", "grupos": "🧪 Teste Pessoal #1"},
        {"number": "5561991076276", "display": "+55 61 99107-6276", "grupos": "🧪 Teste Pessoal #2"}
    ]

    all_contacts = []
    if os.path.exists(csv_file):
        with open(csv_file, "r", encoding="utf-8-sig") as f:
            reader = csv.DictReader(f)
            for row in reader:
                e164 = row.get("Telefone (E164)", "").strip()
                orig = row.get("Telefone (Original)", "").strip()
                grupos = row.get("Grupos", "").strip()
                if e164:
                    num_only = e164.replace("+", "")
                    all_contacts.append({
                        "number": num_only,
                        "display": orig or e164,
                        "grupos": grupos
                    })

    html_content = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Envios WhatsApp v2</title>
    <style>
        * {{ box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }}
        body {{ background: #f0f2f5; margin: 0; padding: 20px; color: #111b21; }}
        .container {{ max-width: 950px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }}
        .header {{ text-align: center; margin-bottom: 24px; border-bottom: 2px solid #f0f2f5; padding-bottom: 16px; }}
        h1 {{ color: #00a884; margin: 0 0 8px 0; font-size: 26px; }}
        p.subtitle {{ color: #667781; margin: 0; font-size: 14px; }}

        .config-box {{ background: #f0fdf4; border: 1.5px solid #22c55e; border-radius: 10px; padding: 18px; margin-bottom: 24px; }}
        .config-box label {{ font-weight: bold; color: #15803d; display: block; margin-bottom: 8px; font-size: 15px; }}
        textarea {{ width: 100%; height: 95px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; font-size: 14px; resize: vertical; outline: none; transition: border-color 0.2s; }}
        textarea:focus {{ border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }}
        
        .section-title {{ font-size: 18px; font-weight: bold; margin: 24px 0 12px 0; color: #0f172a; display: flex; align-items: center; gap: 8px; }}
        
        .stats {{ display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-weight: 600; font-size: 14px; background: #f8fafc; padding: 10px 16px; border-radius: 8px; }}
        .counter {{ color: #00a884; font-size: 16px; }}
        
        table {{ width: 100%; border-collapse: collapse; margin-bottom: 24px; }}
        th, td {{ padding: 12px 14px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 14px; }}
        th {{ background-color: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }}
        tr:hover {{ background-color: #f8fafc; }}
        
        .btn-send {{ background-color: #25d366; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; font-size: 13px; }}
        .btn-send:hover {{ background-color: #1ebd59; transform: translateY(-1px); }}
        .btn-send.sent {{ background-color: #94a3b8; opacity: 0.75; transform: none; }}
        
        .btn-test {{ background-color: #0284c7; color: white; }}
        .btn-test:hover {{ background-color: #0369a1; }}
        
        .badge {{ background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }}
        .badge-test {{ background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 600; }}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Painel de Envios WhatsApp (v2)</h1>
            <p class="subtitle">Modo de Teste + Lista Completa de Contatos Extraídos</p>
        </div>
        
        <div class="config-box">
            <label for="msgInput">✏️ Digite a mensagem personalizada:</label>
            <textarea id="msgInput" placeholder="Digite sua mensagem de teste aqui...">Olá! Este é um teste do painel de envios do WhatsApp.</textarea>
        </div>

        <!-- SEÇÃO DE TESTES -->
        <div class="section-title">🧪 Números de Teste Pessoal</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Telefone de Teste</th>
                    <th>Identificação</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
"""

    for idx, c in enumerate(test_contacts, 1):
        num = c['number']
        disp = html.escape(c['display'])
        grup = html.escape(c['grupos'])
        html_content += f"""
                <tr>
                    <td><strong>{idx}</strong></td>
                    <td><strong style="color: #0284c7;">{disp}</strong></td>
                    <td><span class="badge badge-test">{grup}</span></td>
                    <td>
                        <a href="https://wa.me/{num}?text=Olá!%20Este%20é%20um%20teste%20do%20painel%20de%20envios%20do%20WhatsApp." 
                           target="_blank" 
                           class="btn-send btn-test" 
                           data-phone="{num}"
                           onclick="markSent(this)">
                           🧪 Testar Envio
                        </a>
                    </td>
                </tr>
"""

    html_content += f"""
            </tbody>
        </table>

        <!-- SEÇÃO DA LISTA COMPLETA -->
        <div class="section-title">👥 Lista Geral de Contatos ({len(all_contacts)} Números Extraídos)</div>
        
        <div class="stats">
            <span>Total de Contatos: <span class="counter">{len(all_contacts)}</span></span>
            <span>Enviados: <span class="counter" id="sentCount">0</span> / {len(all_contacts)}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Telefone</th>
                    <th>Grupo de Origem</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody id="contactsTable">
"""

    for idx, c in enumerate(all_contacts, 1):
        num = c['number']
        disp = html.escape(c['display'])
        grup = html.escape(c['grupos'])
        html_content += f"""
                <tr id="row-{idx}">
                    <td>{idx}</td>
                    <td><strong>{disp}</strong></td>
                    <td><span class="badge">{grup}</span></td>
                    <td>
                        <a href="https://wa.me/{num}?text=Olá!%20Este%20é%20um%20teste%20do%20painel%20de%20envios%20do%20WhatsApp." 
                           target="_blank" 
                           class="btn-send" 
                           data-phone="{num}"
                           onclick="markSent(this)">
                           💬 Enviar Mensagem
                        </a>
                    </td>
                </tr>
"""

    html_content += """
            </tbody>
        </table>
    </div>

    <script>
        let sentCount = 0;
        const msgInput = document.getElementById('msgInput');

        function updateLinks() {
            const text = encodeURIComponent(msgInput.value);
            const links = document.querySelectorAll('.btn-send');
            links.forEach(link => {
                const phone = link.getAttribute('data-phone');
                link.href = `https://wa.me/${phone}?text=${text}`;
            });
        }

        msgInput.addEventListener('input', updateLinks);

        function markSent(btn) {
            if (!btn.classList.contains('sent')) {
                btn.classList.add('sent');
                btn.innerText = '✅ Enviado';
                if (!btn.classList.contains('btn-test')) {
                    sentCount++;
                    document.getElementById('sentCount').innerText = sentCount;
                }
            }
        }
    </script>
</body>
</html>
"""

    with open(output_html, "w", encoding="utf-8") as f:
        f.write(html_content)

    print(f"Painel v2 gerado com sucesso em: {output_html}")

if __name__ == "__main__":
    main()
