<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de DocumentoRecebimento</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="id_os">IdOs:</label>
                <select id="id_os" name="id_os" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_os_list)): foreach($id_os_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="Data_Assinatura">DataAssinatura:</label>
                <input type="datetime-local" id="Data_Assinatura" name="Data_Assinatura" required>
            </div>

            <div class="form-group">
                <label for="nup_sei">NupSei:</label>
                <input type="text" id="nup_sei" name="nup_sei" required>
            </div>

            <div class="form-group">
                <label for="id_tipo_documento">IdTipoDocumento:</label>
                <select id="id_tipo_documento" name="id_tipo_documento" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_tipo_documento_list)): foreach($id_tipo_documento_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_usuario_fiscal_tecnico">IdUsuarioFiscalTecnico:</label>
                <select id="id_usuario_fiscal_tecnico" name="id_usuario_fiscal_tecnico" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_usuario_fiscal_tecnico_list)): foreach($id_usuario_fiscal_tecnico_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_usuario_fiscal_requisitante">IdUsuarioFiscalRequisitante:</label>
                <select id="id_usuario_fiscal_requisitante" name="id_usuario_fiscal_requisitante" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_usuario_fiscal_requisitante_list)): foreach($id_usuario_fiscal_requisitante_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_usuario_gestor">IdUsuarioGestor:</label>
                <select id="id_usuario_gestor" name="id_usuario_gestor" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_usuario_gestor_list)): foreach($id_usuario_gestor_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="add-button" type="button" id="saveDocBtn">Salvar Documento de Recebimento</button>
                <a href="<?php echo site_url('listDocumentoRecebimento'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <hr style="margin: 30px 0;">
        
        <h4 style="text-align: center;">Itens Recebidos (Atesto)</h4>
        <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_os_select">Item da OS Associado:</label>
                <select id="item_os_select" style="width: 250px;" disabled>
                    <option value="">Primeiro, selecione uma OS no cabeçalho...</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_qtd">Qtd. Entregue:</label>
                <input type="number" step="0.01" id="item_qtd" style="width: 120px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_glosa">Glosa:</label>
                <input type="number" step="0.01" id="item_glosa" style="width: 120px;" value="0">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_obs">Observações:</label>
                <input type="text" id="item_obs" style="width: 200px;">
            </div>
            <button type="button" class="add-button" id="addItemBtn" style="margin-bottom: 0; padding: 10px 15px;">Adicionar Item</button>
        </div>

        <table class="data-table" id="itemsTable">
            <thead>
                <tr>
                    <th>Qtd Entregue</th>
                    <th>Profissional</th>
                    <th>ID Serviço</th>
                    <th>Nº Item</th>
                    <th>Descrição</th>
                    <th>SLA (Dias)</th>
                    <th>Horas (Base)</th>
                    <th>Glosa (Horas)</th>
                    <th>Horas do Item</th>
                    <th>Observações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Items inseridos via JS aparecerão aqui -->
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" style="text-align: right; font-weight: bold;">Total do Documento:</td>
                    <td id="totalValorDoc" style="font-weight: bold;">0,00</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

        <script>
            let docItems = [];
            let currentOsItems = [];
            let editingIndex = -1;

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderItems() {
                const tbody = $('#itemsTable tbody');
                tbody.empty();
                let totalDoc = 0;
                docItems.forEach((item, index) => {
                    let valorItem = 0;
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
                            <td>${item.glosa_horas || '0'}</td>
                            <td>${formatCurrency(valorItem)}</td>
                            <td>${item.observacoes || '-'}</td>
                            <td>
                                <button type="button" class="edit-button" onclick="editItem(${index})">✏️</button>
                                <button type="button" class="delete-button" onclick="removeItem(${index})">🗑️</button>
                            </td>
                        </tr>
                    `);
                });
                $('#totalValorDoc').text(formatCurrency(totalDoc));
            }

            function editItem(index) {
                editingIndex = index;
                const item = docItems[index];
                $('#item_os_select').val(item.id_item_os);
                $('#item_qtd').val(item.quantidade_entregue);
                $('#item_glosa').val(item.glosa_horas);
                $('#item_obs').val(item.observacoes);
                $('#addItemBtn').text('Atualizar Item').css('background-color', '#ffc107').css('color', '#000');
            }

            function removeItem(index) {
                docItems.splice(index, 1);
                renderItems();
            }

            $(document).ready(function() {
                // Ao trocar a OS, carregar itens e migrar dados
                $('#id_os').change(function() {
                    const idOs = $(this).val();
                    $('#item_os_select').html('<option value="">Carregando...</option>').prop('disabled', true);
                    currentOsItems = [];
                    docItems = []; // Limpar grid ao trocar OS
                    
                    if (idOs) {
                        // Buscar detalhes do Cabeçalho da OS
                        $.get('<?php echo site_url('api/os_details/'); ?>' + idOs, function(osData) {
                            if(osData && osData.nup_sei) {
                                $('#nup_sei').val(osData.nup_sei);
                            }
                        });

                        // Buscar itens e pré-popular a Grid
                        $.get('<?php echo site_url('api/itens_os/'); ?>' + idOs, function(data) {
                            currentOsItems = data;
                            $('#item_os_select').empty().append('<option value="">Selecione o Item da OS...</option>');
                            
                            data.forEach(function(item) {
                                $('#item_os_select').append(`<option value="${item.id}">Item ${item.numero_item || item.id} - ${item.descricao || ''} (Qtd: ${item.quantidade_horas} - Prof: ${item.profissional_alocado})</option>`);
                                
                                // Auto-popular a grid com 100% da quantidade da OS e glosa zerada
                                const descServico = item.descricao ? `Item ${item.numero_item} - ${item.descricao}` : `Item OS #${item.id}`;
                                docItems.push({
                                    id_item_os: item.id,
                                    quantidade_entregue: item.quantidade_horas,
                                    glosa_horas: 0,
                                    observacoes: 'Migrado da OS',
                                    desc_servico: descServico,
                                    profissional: item.profissional_alocado || '',
                                    id_servico: item.id_servico,
                                    numero_item: item.numero_item,
                                    descricao: item.descricao,
                                    sla_dias: item.sla_dias,
                                    remuneracao: item.remuneracao,
                                    valor_item_contrato: item.valor_item_contrato,
                                    valor_remuneracao_item: item.valor_remuneracao_item || 0
                                });
                            });
                            
                            $('#item_os_select').prop('disabled', false);
                            renderItems(); // Mostrar itens na tela
                        });
                    } else {
                        $('#item_os_select').html('<option value="">Primeiro, selecione uma OS no cabeçalho...</option>');
                        $('#nup_sei').val('');
                        renderItems();
                    }
                });

                $('#addItemBtn').click(function() {
                    const idItemOs = $('#item_os_select').val();
                    const qtd = $('#item_qtd').val();
                    const glosa = $('#item_glosa').val() || 0;
                    const obs = $('#item_obs').val();
                    
                    if (!idItemOs || !qtd) {
                        alert('Por favor, selecione o Item da OS e a Quantidade Entregue.');
                        return;
                    }
                    
                    const osItemObj = currentOsItems.find(i => i.id == idItemOs) || {};
                    const descServico = osItemObj.descricao ? `Item ${osItemObj.numero_item} - ${osItemObj.descricao}` : `Item OS #${idItemOs}`;
                    const profissional = osItemObj.profissional_alocado || '';

                    if (editingIndex >= 0) {
                        docItems[editingIndex] = {
                            id_item_os: idItemOs,
                            quantidade_entregue: qtd,
                            glosa_horas: glosa,
                            observacoes: obs,
                            desc_servico: descServico,
                            profissional: profissional,
                            id_servico: osItemObj.id_servico,
                            numero_item: osItemObj.numero_item,
                            descricao: osItemObj.descricao,
                            sla_dias: osItemObj.sla_dias,
                            remuneracao: osItemObj.remuneracao,
                            valor_item_contrato: osItemObj.valor_item_contrato
                        };
                        editingIndex = -1;
                        $('#addItemBtn').text('Adicionar Item').css('background-color', '').css('color', '');
                    } else {
                        docItems.push({
                            id_item_os: idItemOs,
                            quantidade_entregue: qtd,
                            glosa_horas: glosa,
                            observacoes: obs,
                            desc_servico: descServico,
                            profissional: profissional,
                            id_servico: osItemObj.id_servico,
                            numero_item: osItemObj.numero_item,
                            descricao: osItemObj.descricao,
                            sla_dias: osItemObj.sla_dias,
                            remuneracao: osItemObj.remuneracao,
                            valor_item_contrato: osItemObj.valor_item_contrato
                        });
                    }
                    
                    $('#item_os_select').val('');
                    $('#item_qtd').val('');
                    $('#item_glosa').val('0');
                    $('#item_obs').val('');
                    
                    renderItems();
                });

                $('#saveDocBtn').click(function() {
                    let formData = $('#addForm').serializeArray();
                    formData.push({name: "items", value: JSON.stringify(docItems)});
                    
                    $.ajax({
                        url: '<?php echo site_url('insertDocumentoRecebimento'); ?>',
                        type: 'POST',
                        data: $.param(formData),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listDocumentoRecebimento'); ?>'; }, 1500);
                            } else {
                                $('#error-message').html(response.mensagem).show().delay(5000).fadeOut();
                            }
                        },
                        error: function() {
                            $('#error-message').html('Ocorreu um erro ao salvar os dados.').show().delay(5000).fadeOut();
                        }
                    });
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
