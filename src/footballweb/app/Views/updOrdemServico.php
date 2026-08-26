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
            
            <div class="form-group" style="margin-top: 15px;">
                <label for="id_contrato">Contrato:</label>
                <select id="id_contrato" name="id_contrato" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Selecione...</option>
                    <?php if(isset($contratos_list)): foreach($contratos_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_contrato) && $record->id_contrato == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo esc($opt->descricao); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

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

            <div class="form-group" style="margin-top: 15px;">
                <label for="nota_empenho">Nota de Empenho:</label>
                <input type="text" id="nota_empenho" name="nota_empenho" value="<?php echo isset($record->nota_empenho) ? htmlspecialchars($record->nota_empenho) : ''; ?>" placeholder="Alfanumérico" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" <?php echo (isset($record->status) && $record->status !== 'Rascunho') ? 'required' : ''; ?>>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label for="status">Status:</label>
                <?php 
                $currentStatus = isset($record->status) ? $record->status : 'Rascunho';
                if ($currentStatus === 'Aguardando assinatura'): 
                ?>
                    <select id="status" name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff;">
                        <option value="Aguardando assinatura" selected>Aguardando assinatura</option>
                        <option value="Execução">Execução</option>
                    </select>
                <?php elseif ($currentStatus === 'Execução'): ?>
                    <select id="status" name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff;">
                        <option value="Execução" selected>Execução</option>
                        <option value="Cancelada">Cancelada</option>
                    </select>
                <?php else: ?>
                    <input type="text" id="status_display" value="<?php echo htmlspecialchars($currentStatus); ?>" readonly disabled style="width: 100%; padding: 8px; background-color: #e9ecef; border: 1px solid #ddd; border-radius: 4px;">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($currentStatus); ?>">
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label for="realizada_estimativa">Realizada Estimativa?</label>
                <select id="realizada_estimativa" name="realizada_estimativa" class="form-control" required style="width: 100%; padding: 8px;">
                    <option value="">Selecione...</option>
                    <option value="Sim" <?php echo (isset($record->realizada_estimativa) && $record->realizada_estimativa == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                    <option value="Não" <?php echo (isset($record->realizada_estimativa) && $record->realizada_estimativa == 'Não') ? 'selected' : ''; ?>>Não</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label for="metodologia_estimativa">Metodologia da Estimativa (segundo o contrato):</label>
                <textarea id="metodologia_estimativa" name="metodologia_estimativa" class="form-control" rows="10" maxlength="1000" style="width: 100%; resize: vertical; padding: 8px;" required placeholder="Descreva a metodologia utilizada na estimativa em até 10 linhas..."><?php echo isset($record->metodologia_estimativa) ? htmlspecialchars($record->metodologia_estimativa) : ''; ?></textarea>
            </div>

            <div class="button-group">
                <button class="add-button" type="button" id="saveOrdemServicoBtn">Atualizar Ordem de Serviço</button>
                <?php if (isset($record->status) && $record->status === 'Recebido definitivo'): ?>
                    <button class="add-button" type="button" id="concluirOsBtn" style="background-color: #28a745; <?php echo (isset($trp_exists) && $trp_exists && isset($trd_exists) && $trd_exists) ? '' : 'background-color: #6c757d; cursor: not-allowed;'; ?>" <?php echo (isset($trp_exists) && $trp_exists && isset($trd_exists) && $trd_exists) ? '' : 'disabled title="Necessário ter TRD e TRP cadastrados"'; ?>>Concluir Ordem de Serviço</button>
                <?php endif; ?>
                <a href="<?php echo site_url('listOrdemServico'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
            <?php if (isset($record->status) && $record->status === 'Recebido definitivo' && !(isset($trp_exists) && $trp_exists && isset($trd_exists) && $trd_exists)): ?>
                <p style="color: #dc3545; font-size: 0.9rem; margin-top: 10px; text-align: center;">Nota: A OS só pode ser concluída quando possuir documentos TRD e TRP cadastrados.</p>
            <?php endif; ?>
        </form>

        <hr style="margin: 30px 0;">
        
        <h4 style="text-align: center;">Itens da Ordem de Serviço</h4>
        <div class="card p-3 mb-4 shadow-sm" style="background-color: #fdfdfd; border: 1px solid #e3e3e3; border-radius: 8px;">
            <div class="row g-3">
                <div class="col-12 col-sm-3 col-md-2">
                    <div class="form-group mb-0">
                        <label for="item_qtd" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; display: block;">Quantidade:</label>
                        <input type="number" step="0.01" id="item_qtd" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div class="col-12 col-sm-9 col-md-10">
                    <div class="form-group mb-0">
                        <label for="item_prof" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; display: block;">Profissional:</label>
                        <input type="text" id="item_prof" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="item_catalogo" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; display: block;">Catálogo:</label>
                        <select id="item_catalogo" class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff;" disabled>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="item_area" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; display: block;">Área:</label>
                        <select id="item_area" class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff;" disabled>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="item_macro" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; display: block;">Macro:</label>
                        <select id="item_macro" class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff;" disabled>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group mb-0">
                        <label for="item_servico" style="font-weight: 600; font-size: 0.95rem; margin-bottom: 5px; display: block;">Serviço:</label>
                        <select id="item_servico" class="form-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: #fff;" disabled>
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 text-end mt-3">
                    <button type="button" class="add-button" id="addItemBtn" style="margin: 0; padding: 10px 20px; display: inline-block; width: auto; font-weight: 600;">Adicionar Item</button>
                </div>
            </div>
        </div>

        <table class="data-table" id="itemsTable">
            <thead>
                <tr>
                    <th>Profissional</th>
                    <th>ID Serviço</th>
                    <th>Nº Item</th>
                    <th>Descrição</th>
                    <th>SLA (Dias)</th>
                    <th>Base (Métrica)</th>
                    <th>Quantidade</th>
                    <th>Valor do Item</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Items inseridos via JS aparecerão aqui -->
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" style="text-align: right; font-weight: bold;">Total da Ordem de Serviço:</td>
                    <td id="totalValorOS" style="font-weight: bold;">0,00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <script>
            let osItems = <?php echo isset($items_json) ? $items_json : '[]'; ?>;
            let currentServicos = [];

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderItems() {
                const tbody = $('#itemsTable tbody');
                tbody.empty();
                let totalOs = 0;
                osItems.forEach((item, index) => {
                    let valorItem = item.valor_remuneracao_item ? parseFloat(item.valor_remuneracao_item) : 0;
                    totalOs += valorItem;
                    let sigla = item.sigla_metrica ? item.sigla_metrica.toUpperCase() : 'H';
                    tbody.append(`
                        <tr>
                            <td>${item.profissional_alocado}</td>
                            <td>${item.id_servico}</td>
                            <td>${item.numero_item || '-'}</td>
                            <td>${item.descricao || '-'}</td>
                            <td>${item.sla_dias || '-'}</td>
                            <td>${item.remuneracao ? parseFloat(item.remuneracao).toFixed(2).replace('.', ',') + ' ' + sigla : '-'}</td>
                            <td>${item.quantidade_horas} ${sigla}</td>
                            <td>R$ ${formatCurrency(valorItem)}</td>
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
                    await carregarCatalogos($('#id_contrato').val());
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
            function carregarCatalogos(id_contrato) {
                return new Promise((resolve) => {
                    $('#item_catalogo').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_area').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_macro').html('<option value="">Selecione...</option>').prop('disabled', true);
                    $('#item_servico').html('<option value="">Selecione...</option>').prop('disabled', true);
                    if(!id_contrato) return resolve();
                    
                    $.get('<?php echo site_url('api/catalogos/'); ?>' + id_contrato, function(data) {
                        data.forEach(function(item) {
                            $('#item_catalogo').append(`<option value="${item.id}">${item.descricao}</option>`);
                        });
                        $('#item_catalogo').prop('disabled', false);
                        resolve();
                    });
                });
            }

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

                // Inicializar os catálogos do contrato selecionado
                let initialContratoId = $('#id_contrato').val();
                if (initialContratoId) {
                    carregarCatalogos(initialContratoId);
                }

                // Monitoramento de alteração do Contrato
                let currentContratoId = $('#id_contrato').val();
                $('#id_contrato').change(function() {
                    let newContratoId = $(this).val();
                    /* COMENTADO TEMPORARIAMENTE
                    if (osItems.length > 0) {
                        if (confirm('Alterar o contrato removerá todos os itens atuais da Ordem de Serviço. Deseja continuar?')) {
                            osItems = [];
                            renderItems();
                            carregarCatalogos(newContratoId);
                            currentContratoId = newContratoId;
                        } else {
                            $(this).val(currentContratoId);
                        }
                    } else {
                        carregarCatalogos(newContratoId);
                        currentContratoId = newContratoId;
                    }
                    */
                    carregarCatalogos(newContratoId);
                    currentContratoId = newContratoId;
                });

                // Filtros em Cascata usando as funções acima
                $('#item_catalogo').change(function() { carregarAreas($(this).val()); });
                $('#item_area').change(function() { carregarMacros($(this).val()); });
                $('#item_macro').change(function() { carregarServicos($(this).val()); });

                $('#addItemBtn').click(function() {
                    if ($('#realizada_estimativa').val() !== 'Sim') {
                        $('#error-message').html('Operação não permitida: A adição de itens só é possível se houver uma estimativa realizada (Realizada Estimativa = Sim).').show().delay(5000).fadeOut();
                        $('html, body').animate({ scrollTop: 0 }, 'fast');
                        return;
                    }

                    const qtd = $('#item_qtd').val();
                    const prof = $('#item_prof').val() || 'Nenhum';
                    const servicoId = $('#item_servico').val();
                    
                    if (editingIndex >= 0) {
                        if (!qtd) {
                            alert('Por favor preencha a Quantidade.');
                            return;
                        }
                        
                        let servicoObj = osItems[editingIndex]; // Mantém o atual
                        let finalServicoId = osItems[editingIndex].id_servico;
                        
                        if (servicoId) {
                            // Se selecionou um novo serviço na combo, atualiza
                            servicoObj = currentServicos.find(s => s.id == servicoId) || {};
                            finalServicoId = servicoId;
                        }

                        const valContrato = servicoObj.valor_item_contrato ? parseFloat(servicoObj.valor_item_contrato) : 0;
                        const remun = servicoObj.remuneracao ? parseFloat(servicoObj.remuneracao) : 0;
                        const baseHoras = servicoObj.base_horas_complexidade ? parseFloat(servicoObj.base_horas_complexidade) : 0;
                        const sigla = servicoObj.sigla_metrica ? servicoObj.sigla_metrica.toUpperCase() : 'H';
                        let valorCalculado = 0;
                        if (sigla === 'PROF') {
                            valorCalculado = parseFloat(qtd) * baseHoras;
                        } else if (sigla === 'PF') {
                            valorCalculado = parseFloat(qtd) * valContrato;
                        } else {
                            valorCalculado = parseFloat(qtd) * remun * valContrato;
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
                            remuneracao: servicoObj.remuneracao,
                            base_horas_complexidade: servicoObj.base_horas_complexidade,
                            valor_remuneracao_item: valorCalculado,
                            sigla_metrica: sigla
                        };
                        
                        editingIndex = -1;
                        $('#addItemBtn').text('Adicionar Item').css('background-color', '').css('color', '');
                        
                    } else {
                        if (!qtd || !servicoId) {
                            alert('Por favor preencha Quantidade e Serviço.');
                            return;
                        }
                        
                        const servicoObj = currentServicos.find(s => s.id == servicoId) || {};
                        const valContrato = servicoObj.valor_item_contrato ? parseFloat(servicoObj.valor_item_contrato) : 0;
                        const remun = servicoObj.remuneracao ? parseFloat(servicoObj.remuneracao) : 0;
                        const baseHoras = servicoObj.base_horas_complexidade ? parseFloat(servicoObj.base_horas_complexidade) : 0;
                        const sigla = servicoObj.sigla_metrica ? servicoObj.sigla_metrica.toUpperCase() : 'H';
                        let valorCalculado = 0;
                        if (sigla === 'PROF') {
                            valorCalculado = parseFloat(qtd) * baseHoras;
                        } else if (sigla === 'PF') {
                            valorCalculado = parseFloat(qtd) * valContrato;
                        } else {
                            valorCalculado = parseFloat(qtd) * remun * valContrato;
                        }
                        
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
                            remuneracao: servicoObj.remuneracao,
                            base_horas_complexidade: servicoObj.base_horas_complexidade,
                            valor_remuneracao_item: valorCalculado,
                            sigla_metrica: sigla
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
                    if (!$('#updForm')[0].checkValidity()) {
                        $('#updForm')[0].reportValidity();
                        return;
                    }
                    
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

                $('#concluirOsBtn').click(function() {
                    if (confirm('Deseja realmente concluir esta Ordem de Serviço?')) {
                        $.ajax({
                            url: '<?php echo site_url('concluirOrdemServico/' . (isset($record->id) ? $record->id : 0)); ?>',
                            type: 'POST',
                            success: function(response) {
                                if (response.status === 'success') {
                                    alert(response.mensagem);
                                    window.location.href = '<?php echo site_url('listOrdemServico'); ?>';
                                } else {
                                    alert(response.mensagem);
                                }
                            },
                            error: function() {
                                alert('Ocorreu um erro ao concluir a Ordem de Serviço.');
                            }
                        });
                    }
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
