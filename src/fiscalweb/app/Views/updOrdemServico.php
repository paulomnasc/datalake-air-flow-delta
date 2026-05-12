<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de OrdemServico</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            
            <div class="form-group">
                <label for="Horas_Alocadas">HorasAlocadas:</label>
                <input type="number" step="0.01" id="Horas_Alocadas" name="Horas_Alocadas" value="<?php echo isset($record->Horas_Alocadas) ? $record->Horas_Alocadas : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="nup_sei">NupSei:</label>
                <input type="text" id="nup_sei" name="nup_sei" value="<?php echo isset($record->nup_sei) ? $record->nup_sei : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Data_Emissao">DataEmissao:</label>
                <input type="datetime-local" id="Data_Emissao" name="Data_Emissao" value="<?php echo isset($record->Data_Emissao) ? $record->Data_Emissao : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Data_Aceite">DataAceite:</label>
                <input type="datetime-local" id="Data_Aceite" name="Data_Aceite" value="<?php echo isset($record->Data_Aceite) ? $record->Data_Aceite : ''; ?>" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="button" id="saveOrdemServicoBtn">Atualizar Ordem de Serviço</button>
                <a href="<?php echo site_url('listOrdemServico'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <hr style="margin: 30px 0;">
        
        <h4 style="text-align: center;">Itens da Ordem de Serviço</h4>
        <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_qtd">Horas:</label>
                <input type="number" step="0.01" id="item_qtd" style="width: 100px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_prof">Profissional:</label>
                <input type="text" id="item_prof" style="width: 200px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_catalogo">Catálogo:</label>
                <select id="item_catalogo" style="width: 120px;">
                    <option value="">Selecione...</option>
                    <?php if(isset($catalogos_list)): foreach($catalogos_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo $opt->descricao; ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_area">Área:</label>
                <select id="item_area" style="width: 120px;" disabled>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_macro">Macro:</label>
                <select id="item_macro" style="width: 120px;" disabled>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_servico">Serviço:</label>
                <select id="item_servico" style="width: 120px;" disabled>
                    <option value="">Selecione...</option>
                </select>
            </div>
            <button type="button" class="add-button" id="addItemBtn" style="margin-bottom: 0; padding: 10px 15px;">Adicionar Item</button>
        </div>

        <table class="data-table" id="itemsTable">
            <thead>
                <tr>
                    <th>Horas</th>
                    <th>Profissional</th>
                    <th>ID Serviço</th>
                    <th>Nº Item</th>
                    <th>Descrição</th>
                    <th>SLA (Dias)</th>
                    <th>Remuneração</th>
                    <th>Valor Item (R$)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Items inseridos via JS aparecerão aqui -->
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" style="text-align: right; font-weight: bold;">Total da Ordem de Serviço:</td>
                    <td id="totalValorOS" style="font-weight: bold;">R$ 0,00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <script>
            let osItems = <?php echo isset($items_json) ? $items_json : '[]'; ?>;
            let currentServicos = [];

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }

            function renderItems() {
                const tbody = $('#itemsTable tbody');
                tbody.empty();
                let totalOs = 0;
                osItems.forEach((item, index) => {
                    let valorItem = item.valor_remuneracao_item ? parseFloat(item.valor_remuneracao_item) : 0;
                    totalOs += valorItem;
                    tbody.append(`
                        <tr>
                            <td>${item.quantidade_horas}</td>
                            <td>${item.profissional_alocado}</td>
                            <td>${item.id_servico}</td>
                            <td>${item.numero_item || '-'}</td>
                            <td>${item.descricao || '-'}</td>
                            <td>${item.sla_dias || '-'}</td>
                            <td>${item.remuneracao ? formatCurrency(item.remuneracao) : '-'}</td>
                            <td>${formatCurrency(valorItem)}</td>
                            <td>
                                <button type="button" class="edit-button" onclick="editItem(${index})">✏️</button>
                                <button type="button" class="delete-button" onclick="removeItem(${index})">🗑️</button>
                            </td>
                        </tr>
                    `);
                });
                $('#totalValorOS').text(formatCurrency(totalOs));
            }

            let editingIndex = -1;

            async function editItem(index) {
                editingIndex = index;
                const item = osItems[index];
                $('#item_qtd').val(item.quantidade_horas);
                $('#item_prof').val(item.profissional_alocado);
                
                // Mudar o botão
                $('#addItemBtn').text('Atualizar Item').css('background-color', '#ffc107').css('color', '#000');
                
                // Carregar cascata se os IDs estiverem disponíveis
                if (item.id_catalogo) {
                    $('#item_catalogo').val(item.id_catalogo);
                    await carregarAreas(item.id_catalogo);
                    $('#item_area').val(item.id_area);
                    await carregarMacros(item.id_area);
                    $('#item_macro').val(item.id_macro);
                    await carregarServicos(item.id_macro);
                    $('#item_servico').val(item.id_servico);
                }
            }

            function removeItem(index) {
                osItems.splice(index, 1);
                renderItems();
            }

            // Funções Promisificadas para Cascata
            function carregarAreas(id_catalogo) {
                return new Promise((resolve) => {
                    $('#item_area').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_macro').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    if(!id_catalogo) return resolve();
                    
                    $.get('<?php echo site_url('api/areas/'); ?>' + id_catalogo, function(data) {
                        data.forEach(function(item) {
                            $('#item_area').append(`<option value="${item.id}">${item.descricao}</option>`);
                        });
                        $('#item_area').prop('disabled', false);
                        resolve();
                    });
                });
            }

            function carregarMacros(id_area) {
                return new Promise((resolve) => {
                    $('#item_macro').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    if(!id_area) return resolve();
                    
                    $.get('<?php echo site_url('api/atividades/'); ?>' + id_area, function(data) {
                        data.forEach(function(item) {
                            $('#item_macro').append(`<option value="${item.id}">${item.descricao}</option>`);
                        });
                        $('#item_macro').prop('disabled', false);
                        resolve();
                    });
                });
            }

            function carregarServicos(id_macro) {
                return new Promise((resolve) => {
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    currentServicos = [];
                    if(!id_macro) return resolve();
                    
                    $.get('<?php echo site_url('api/servicos/'); ?>' + id_macro, function(data) {
                        currentServicos = data;
                        data.forEach(function(item) {
                            $('#item_servico').append(`<option value="${item.id}">${item.descricao}</option>`);
                        });
                        $('#item_servico').prop('disabled', false);
                        resolve();
                    });
                });
            }

            $(document).ready(function() {
                renderItems(); // Render initial items

                // Filtros em Cascata usando as funções acima
                $('#item_catalogo').change(function() { carregarAreas($(this).val()); });
                $('#item_area').change(function() { carregarMacros($(this).val()); });
                $('#item_macro').change(function() { carregarServicos($(this).val()); });

                $('#addItemBtn').click(function() {
                    const qtd = $('#item_qtd').val();
                    const prof = $('#item_prof').val() || 'Nenhum';
                    const servicoId = $('#item_servico').val();
                    
                    if (editingIndex >= 0) {
                        if (!qtd) {
                            alert('Por favor preencha Horas.');
                            return;
                        }
                        
                        let servicoObj = osItems[editingIndex]; // Mantém o atual
                        let finalServicoId = osItems[editingIndex].id_servico;
                        
                        if (servicoId) {
                            // Se selecionou um novo serviço na combo, atualiza
                            servicoObj = currentServicos.find(s => s.id == servicoId) || {};
                            finalServicoId = servicoId;
                        }
                        
                        osItems[editingIndex] = {
                            ...osItems[editingIndex],
                            quantidade_horas: qtd,
                            profissional_alocado: prof,
                            id_servico: finalServicoId,
                            id_catalogo: $('#item_catalogo').val() || osItems[editingIndex].id_catalogo,
                            id_area: $('#item_area').val() || osItems[editingIndex].id_area,
                            id_macro: $('#item_macro').val() || osItems[editingIndex].id_macro,
                            numero_item: servicoObj.numero_item,
                            descricao: servicoObj.descricao,
                            sla_dias: servicoObj.sla_dias,
                            remuneracao: servicoObj.remuneracao
                        };
                        
                        editingIndex = -1;
                        $('#addItemBtn').text('Adicionar Item').css('background-color', '').css('color', '');
                        
                    } else {
                        if (!qtd || !servicoId) {
                            alert('Por favor preencha Horas e Serviço.');
                            return;
                        }
                        
                        const servicoObj = currentServicos.find(s => s.id == servicoId) || {};
                        
                        osItems.push({
                            quantidade_horas: qtd,
                            profissional_alocado: prof,
                            id_servico: servicoId,
                            id_catalogo: $('#item_catalogo').val(),
                            id_area: $('#item_area').val(),
                            id_macro: $('#item_macro').val(),
                            numero_item: servicoObj.numero_item,
                            descricao: servicoObj.descricao,
                            sla_dias: servicoObj.sla_dias,
                            remuneracao: servicoObj.remuneracao
                        });
                    }
                    
                    $('#item_qtd').val('');
                    $('#item_prof').val('');
                    
                    // Reset combos
                    $('#item_catalogo').val('');
                    $('#item_area').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_macro').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    
                    renderItems();
                });

                $('#saveOrdemServicoBtn').click(function() {
                    let formData = $('#updForm').serializeArray();
                    formData.push({name: "items", value: JSON.stringify(osItems)});
                    
                    $.ajax({
                        url: '<?php echo site_url('updateOrdemServico'); ?>',
                        type: 'POST',
                        data: $.param(formData),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listOrdemServico'); ?>'; }, 1500);
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
