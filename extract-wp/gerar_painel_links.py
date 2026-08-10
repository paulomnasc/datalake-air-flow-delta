#!/usr/bin/env python3
import csv
import html
import urllib.parse
import os

def main():
    csv_file = "/root/datalake-air-flow-delta/extract-wp/arquivos/todos_membros_whatsapp.csv"
    output_html = "/root/datalake-air-flow-delta/extract-wp/arquivos/links_whatsapp.html"

    if not os.path.exists(csv_file):
        print(f"Erro: Arquivo {csv_file} não encontrado.")
        return

    contacts = []
    with open(csv_file, "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for row in reader:
            e164 = row.get("Telefone (E164)", "").strip()
            orig = row.get("Telefone (Original)", "").strip()
            grupos = row.get("Grupos", "").strip()
            if e164:
                # Remove + for wa.me URL
                num_only = e164.replace("+", "")
                contacts.append({
                    "number": num_only,
                    "display": orig or e164,
                    "grupos": grupos
                })

    html_content = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Envios WhatsApp (Links Diretos)</title>
    <style>
        * {{ box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }}
        body {{ background: #f0f2f5; margin: 0; padding: 20px; color: #111b21; }}
        .container {{ max-width: 900px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }}
        h1 {{ color: #00a884; margin-top: 0; font-size: 24px; text-align: center; }}
        p.subtitle {{ text-align: center; color: #667781; margin-bottom: 24px; }}
        .config-box {{ background: #e7fce3; border: 1px solid #00a884; border-radius: 8px; padding: 16px; margin-bottom: 24px; }}
        .config-box label {{ font-weight: bold; color: #008069; display: block; margin-bottom: 8px; }}
        textarea {{ width: 100%; height: 90px; border: 1px solid #ccc; border-radius: 6px; padding: 10px; font-size: 14px; resize: vertical; }}
        .stats {{ display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-weight: bold; }}
        .counter {{ color: #008069; }}
        table {{ width: 100%; border-collapse: collapse; margin-top: 10px; }}
        th, td {{ padding: 12px; text-align: left; border-bottom: 1px solid #e9edef; font-size: 14px; }}
        th {{ background-color: #f7f8fa; color: #54656f; }}
        tr:hover {{ background-color: #f5f6f8; }}
        .btn-send {{ background-color: #25d366; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; transition: background 0.2s; }}
        .btn-send:hover {{ background-color: #1ebd59; }}
        .btn-send.sent {{ background-color: #8696a0; opacity: 0.7; }}
        .badge {{ background: #e9edef; color: #54656f; padding: 3px 8px; border-radius: 12px; font-size: 12px; }}
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Painel de Envio Direto (WhatsApp)</h1>
        <p class="subtitle">Disparo semi-manual via link oficial <code>https://wa.me/</code> sem risco de bloqueio</p>
        
        <div class="config-box">
            <label for="msgInput">✏️ Digite a mensagem padrão que será enviada:</label>
            <textarea id="msgInput" placeholder="Ex: Olá! Tudo bem? Estou entrando em contato sobre os palpites de futebol...">Olá! Tudo bem?</textarea>
        </div>

        <div class="stats">
            <span>Contatos carregados: <span class="counter" id="totalCount">{len(contacts)}</span></span>
            <span>Enviados: <span class="counter" id="sentCount">0</span> / {len(contacts)}</span>
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

    for idx, c in enumerate(contacts, 1):
        num = c['number']
        disp = html.escape(c['display'])
        grup = html.escape(c['grupos'])
        html_content += f"""
                <tr id="row-{idx}">
                    <td>{idx}</td>
                    <td><strong>{disp}</strong></td>
                    <td><span class="badge">{grup}</span></td>
                    <td>
                        <a href="https://wa.me/{num}?text=Olá!%20Tudo%20bem?" 
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
                sentCount++;
                document.getElementById('sentCount').innerText = sentCount;
            }
        }
    </script>
</body>
</html>
"""

    with open(output_html, "w", encoding="utf-8") as f:
        f.write(html_content)

    print(f"Painel HTML gerado com sucesso em: {output_html}")

if __name__ == "__main__":
    main()
