import re

files = [
    '/root/datalake-air-flow-delta/src/fiscalweb/app/Views/addDocumentoRecebimento.php',
    '/root/datalake-air-flow-delta/src/fiscalweb/app/Views/updDocumentoRecebimento.php'
]

for file in files:
    with open(file, 'r') as f:
        content = f.read()

    # Replace table headers
    content = re.sub(
        r'<th>Item OS / Serviço</th>\s*<th>Profissional</th>\s*<th>Qtd Entregue</th>\s*<th>Glosa</th>\s*<th>Observações</th>\s*<th>Valor Item \(R\$\)</th>\s*<th>Ações</th>',
        '''<th>Qtd Entregue</th>
                    <th>Profissional</th>
                    <th>ID Serviço</th>
                    <th>Nº Item</th>
                    <th>Descrição</th>
                    <th>SLA (Dias)</th>
                    <th>Remuneração (Base)</th>
                    <th>Glosa (Horas)</th>
                    <th>Valor Item (R$)</th>
                    <th>Observações</th>
                    <th>Ações</th>''',
        content
    )

    # Replace renderItems logic
    # Find the block inside docItems.forEach
    render_regex = r'(let valorItem = item\.valor_remuneracao_item \? parseFloat\(item\.valor_remuneracao_item\) : 0;[\s\S]*?<td>\$\{item\.observacoes \|\| \'-\'\}<\/td>[\s\S]*?<td>\$\{formatCurrency\(valorItem\)\}<\/td>)'
    
    new_render = '''let valorItem = 0;
                    if (item.valor_remuneracao_item) {
                        valorItem = parseFloat(item.valor_remuneracao_item);
                    } else if (item.valor_item_contrato && item.remuneracao) {
                        let qtd = parseFloat(item.quantidade_entregue) || 0;
                        let glosa = parseFloat(item.glosa_horas) || 0;
                        valorItem = (qtd - glosa) * parseFloat(item.remuneracao) * parseFloat(item.valor_item_contrato);
                        item.valor_remuneracao_item = valorItem;
                    }
                    totalDoc += valorItem;
                    tbody.append(`
                        <tr>
                            <td>${item.quantidade_entregue}</td>
                            <td>${item.profissional || item.profissional_alocado || '-'}</td>
                            <td>${item.id_servico || '-'}</td>
                            <td>${item.numero_item || '-'}</td>
                            <td>${item.descricao || item.desc_servico || '-'}</td>
                            <td>${item.sla_dias || '-'}</td>
                            <td>${item.remuneracao ? parseFloat(item.remuneracao).toFixed(2).replace('.', ',') : '-'}</td>
                            <td>${item.glosa_horas}</td>
                            <td>${formatCurrency(valorItem)}</td>
                            <td>${item.observacoes || '-'}</td>'''
    
    # We'll just replace the whole tbody.append block to be safe.
    content = re.sub(
        r'let valorItem =.*?<tbody>.*?</tbody>',
        '', # We will do it more surgically
        content,
        flags=re.DOTALL
    )

    with open(file, 'w') as f:
        f.write(content)
