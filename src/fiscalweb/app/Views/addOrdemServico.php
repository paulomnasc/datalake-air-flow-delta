<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de OrdemServico</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="Horas_Alocadas">HorasAlocadas:</label>
                <input type="number" step="0.01" id="Horas_Alocadas" name="Horas_Alocadas" required>
            </div>

            <div class="form-group">
                <label for="nup_sei">NupSei:</label>
                <input type="text" id="nup_sei" name="nup_sei" required>
            </div>

            <div class="form-group">
                <label for="Data_Emissao">DataEmissao:</label>
                <input type="datetime-local" id="Data_Emissao" name="Data_Emissao" required>
            </div>

            <div class="form-group">
                <label for="Data_Aceite">DataAceite:</label>
                <input type="datetime-local" id="Data_Aceite" name="Data_Aceite" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="button" id="saveOrdemServicoBtn">Salvar Ordem de Serviço</button>
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
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Items inseridos via JS aparecerão aqui -->
            </tbody>
        </table>

        <script>
            let osItems = [];
            let currentServicos = [];

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }

            function renderItems() {
                const tbody = $('#itemsTable tbody');
                tbody.empty();
                osItems.forEach((item, index) => {
                    tbody.append(`
                        <tr>
                            <td>${item.quantidade_horas}</td>
                            <td>${item.profissional_alocado}</td>
                            <td>${item.id_servico}</td>
                            <td>${item.numero_item || '-'}</td>
                            <td>${item.descricao || '-'}</td>
                            <td>${item.sla_dias || '-'}</td>
                            <td>${item.remuneracao ? formatCurrency(item.remuneracao) : '-'}</td>
                            <td>
                                <button type="button" class="delete-button" onclick="removeItem(${index})">🗑️</button>
                            </td>
                        </tr>
                    `);
                });
            }

            function removeItem(index) {
                osItems.splice(index, 1);
                renderItems();
            }

            $(document).ready(function() {
                // Filtros em Cascata
                $('#item_catalogo').change(function() {
                    const val = $(this).val();
                    $('#item_area').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_macro').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    
                    if(val) {
                        $.get('<?php echo site_url('api/areas/'); ?>' + val, function(data) {
                            data.forEach(function(item) {
                                $('#item_area').append(`<option value="${item.id}">${item.descricao}</option>`);
                            });
                            $('#item_area').prop('disabled', false);
                        });
                    }
                });

                $('#item_area').change(function() {
                    const val = $(this).val();
                    $('#item_macro').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    
                    if(val) {
                        $.get('<?php echo site_url('api/atividades/'); ?>' + val, function(data) {
                            data.forEach(function(item) {
                                $('#item_macro').append(`<option value="${item.id}">${item.descricao}</option>`);
                            });
                            $('#item_macro').prop('disabled', false);
                        });
                    }
                });

                $('#item_macro').change(function() {
                    const val = $(this).val();
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    currentServicos = [];
                    
                    if(val) {
                        $.get('<?php echo site_url('api/servicos/'); ?>' + val, function(data) {
                            currentServicos = data;
                            data.forEach(function(item) {
                                $('#item_servico').append(`<option value="${item.id}">${item.descricao}</option>`);
                            });
                            $('#item_servico').prop('disabled', false);
                        });
                    }
                });

                $('#addItemBtn').click(function() {
                    const qtd = $('#item_qtd').val();
                    const prof = $('#item_prof').val() || 'Nenhum';
                    const servicoId = $('#item_servico').val();
                    
                    if (!qtd || !servicoId) {
                        alert('Por favor preencha Horas e Serviço.');
                        return;
                    }
                    
                    const servicoObj = currentServicos.find(s => s.id == servicoId) || {};
                    
                    osItems.push({
                        quantidade_horas: qtd,
                        profissional_alocado: prof,
                        id_servico: servicoId,
                        numero_item: servicoObj.numero_item,
                        descricao: servicoObj.descricao,
                        sla_dias: servicoObj.sla_dias,
                        remuneracao: servicoObj.remuneracao
                    });
                    
                    $('#item_qtd').val('');
                    $('#item_prof').val('');
                    $('#item_servico').val('');
                    
                    renderItems();
                });

                $('#saveOrdemServicoBtn').click(function() {
                    let formData = $('#addForm').serializeArray();
                    formData.push({name: "items", value: JSON.stringify(osItems)});
                    
                    $.ajax({
                        url: '<?php echo site_url('insertOrdemServico'); ?>',
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
