<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de DocumentoRecebimento</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            
            <div class="form-group">
                <label for="id_os">IdOs:</label>
                <select id="id_os" name="id_os" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_os_list)): foreach($id_os_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_os) && $record->id_os == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_demanda_input">Demanda (Requisito Obrigatório):</label>
                <input type="text" id="id_demanda_input" list="demanda_list" placeholder="Digite parte do título para filtrar..." required autocomplete="off" style="width: 100%; max-width: 100%;" value="<?php echo isset($selected_demanda_title) ? $selected_demanda_title : ''; ?>">
                <datalist id="demanda_list">
                    <?php if(isset($demanda_list)): foreach($demanda_list as $opt): ?>
                        <option data-id="<?php echo $opt->id; ?>" value="<?php echo "Demanda #{$opt->id} - {$opt->titulo}"; ?>"></option>
                    <?php endforeach; endif; ?>
                </datalist>
                <input type="hidden" id="id_demanda" name="id_demanda" value="<?php echo isset($record->id_demanda) ? $record->id_demanda : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Data_Assinatura">DataAssinatura:</label>
                <input type="datetime-local" id="Data_Assinatura" name="Data_Assinatura" value="<?php echo isset($record->Data_Assinatura) ? $record->Data_Assinatura : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="nup_sei">NupSei:</label>
                <input type="text" id="nup_sei" name="nup_sei" value="<?php echo isset($record->nup_sei) ? $record->nup_sei : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="id_tipo_documento">IdTipoDocumento:</label>
                <select id="id_tipo_documento" name="id_tipo_documento" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_tipo_documento_list)): foreach($id_tipo_documento_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_tipo_documento) && $record->id_tipo_documento == $opt->id) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_usuario_fiscal_tecnico) && $record->id_usuario_fiscal_tecnico == $opt->id) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_usuario_fiscal_requisitante) && $record->id_usuario_fiscal_requisitante == $opt->id) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_usuario_gestor) && $record->id_usuario_gestor == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="add-button" type="button" id="saveDocBtn">Atualizar Documento de Recebimento</button>
                <a href="<?php echo site_url('listDocumentoRecebimento'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <hr style="margin: 30px 0;">
        
        <h4 style="text-align: center;">Itens Recebidos (Atesto)</h4>
        <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0;">
                <label for="item_os_select">Item da OS Associado:</label>
                <select id="item_os_select" style="width: 250px;" disabled>
                    <option value="">Carregando itens da OS...</option>
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
                    <th>Verificações</th>
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
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>

        <script>
            let docItems = <?php echo isset($items_json) ? $items_json : '[]'; ?>;
            let currentOsItems = [];
            let editingIndex = -1;
            let checklistOptions = <?php echo isset($checklist_options) ? json_encode($checklist_options) : '[]'; ?>;
            let currentItemIndexForChecklist = -1;

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

                    let checklistCount = item.checklist ? item.checklist.length : 0;

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
                                <span class="badge ${checklistCount > 0 ? 'bg-success' : 'bg-secondary'}">${checklistCount} verificações</span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info text-white" style="padding: 2px 6px;" onclick="openChecklist(${index})" title="Verificar Checklist">📋</button>
                                <button type="button" class="edit-button" onclick="editItem(${index})">✏️</button>
                                <button type="button" class="delete-button" onclick="removeItem(${index})">🗑️</button>
                            </td>
                        </tr>
                    `);
                });
                $('#totalValorDoc').text(formatCurrency(totalDoc));
            }

            function openChecklist(index) {
                currentItemIndexForChecklist = index;
                const item = docItems[index];
                $('#checklist_item_desc').text(item.desc_servico || item.descricao || `Item OS #${item.id_item_os}`);
                
                const tbody = $('#modalChecklistTable tbody');
                tbody.empty();
                
                if (!item.checklist) {
                    item.checklist = [];
                }
                
                checklistOptions.forEach(opt => {
                    const assoc = item.checklist.find(c => c.id_lista_verificacao == opt.id);
                    const isChecked = assoc ? 'checked' : '';
                    const isConforme = assoc && assoc.conforme == 1;
                    
                    const conformeChecked = isConforme || !assoc ? 'checked' : '';
                    const naoConformeChecked = assoc && assoc.conforme == 0 ? 'checked' : '';
                    const disabledClass = assoc ? '' : 'disabled';
                    
                    tbody.append(`
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="checkbox" class="chk-apply" data-id="${opt.id}" ${isChecked}>
                            </td>
                            <td style="vertical-align: middle;">${opt.descricao}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <div class="form-check form-check-inline" style="display: inline-block; margin-right: 10px;">
                                    <input class="form-check-input chk-conforme-radio" type="radio" name="conforme_${opt.id}" id="conf_yes_${opt.id}" value="1" ${conformeChecked} ${disabledClass}>
                                    <label class="form-check-label" for="conf_yes_${opt.id}">Conforme</label>
                                </div>
                                <div class="form-check form-check-inline" style="display: inline-block;">
                                    <input class="form-check-input chk-conforme-radio" type="radio" name="conforme_${opt.id}" id="conf_no_${opt.id}" value="0" ${naoConformeChecked} ${disabledClass}>
                                    <label class="form-check-label" for="conf_no_${opt.id}">Não Conforme</label>
                                </div>
                            </td>
                        </tr>
                    `);
                });
                
                $('.chk-apply').change(function() {
                    const optId = $(this).attr('data-id');
                    const isChecked = $(this).is(':checked');
                    $(`input[name="conforme_${optId}"]`).prop('disabled', !isChecked);
                });
                
                const myModal = new bootstrap.Modal(document.getElementById('checklistModal'));
                myModal.show();
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

            function loadOsItems(idOs, callback) {
                if (idOs) {
                    $.get('<?php echo site_url('api/itens_os/'); ?>' + idOs, function(data) {
                        currentOsItems = data;
                        $('#item_os_select').empty().append('<option value="">Selecione o Item da OS...</option>');
                        data.forEach(function(item) {
                            $('#item_os_select').append(`<option value="${item.id}">Item ${item.numero_item || item.id} - ${item.descricao || ''} (Qtd: ${item.quantidade_horas} - Prof: ${item.profissional_alocado})</option>`);
                        });
                        $('#item_os_select').prop('disabled', false);
                        if (callback) callback();
                    });
                } else {
                    $('#item_os_select').html('<option value="">Primeiro, selecione uma OS no cabeçalho...</option>').prop('disabled', true);
                    if (callback) callback();
            function loadOsDemands(idOs, callback) {
                if (idOs) {
                    $.get('<?php echo site_url('api/demandas_os/'); ?>' + idOs, function(data) {
                        $('#demanda_list').empty();
                        data.forEach(function(demanda) {
                            const title = `Demanda #${demanda.id} - ${demanda.titulo}`;
                            $('#demanda_list').append(`<option data-id="${demanda.id}" value="${title}"></option>`);
                        });
                        $('#id_demanda_input').prop('disabled', false);
                        if (callback) callback();
                    });
                } else {
                    $('#demanda_list').empty();
                    $('#id_demanda_input').val('').prop('disabled', true);
                    $('#id_demanda').val('');
                    if (callback) callback();
                }
            }

            $(document).ready(function() {
                // Evento para atualizar id_demanda ao selecionar da lista
                $('#id_demanda_input').on('input', function() {
                    const val = $(this).val();
                    const option = $('#demanda_list option').filter(function() {
                        return this.value === val;
                    });
                    if (option.length) {
                        const id = option.attr('data-id');
                        $('#id_demanda').val(id);
                    } else {
                        $('#id_demanda').val('');
                    }
                });

                // Carrega os itens da grid inicialmente
                renderItems();

                // Carrega a combo de Itens da OS inicial com base no valor carregado
                const initialOsId = $('#id_os').val();
                loadOsItems(initialOsId);

                // Ao trocar a OS, recarregar itens da combo e repopular a grid
                $('#id_os').change(function() {
                    const idOs = $(this).val();
                    $('#item_os_select').html('<option value="">Carregando...</option>').prop('disabled', true);
                    currentOsItems = [];
                    
                    if (confirm('Atenção: Mudar a Ordem de Serviço irá limpar os Itens Recebidos atuais e importar todos os itens da nova OS. Deseja continuar?')) {
                        docItems = []; // Limpar grid ao trocar OS
                        
                        if (idOs) {
                            // Carregar demandas associadas à nova OS
                            loadOsDemands(idOs);

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
                                    
                                    // Auto-popular a grid
                                    const descServico = item.descricao ? `Item ${item.numero_item} - ${item.descricao}` : `Item OS #${item.id}`;
                                    docItems.push({
                                        id_item_os: item.id,
                                        quantidade_entregue: item.quantidade_horas,
                                        glosa_horas: 0,
                                        observacoes: 'Migrado da OS',
                                        desc_servico: descServico,
                                        profissional: item.profissional_alocado || '',
                                        profissional_alocado: item.profissional_alocado || '',
                                        id_servico: item.id_servico,
                                        numero_item: item.numero_item,
                                        descricao: item.descricao,
                                        sla_dias: item.sla_dias,
                                        remuneracao: item.remuneracao,
                                        valor_item_contrato: item.valor_item_contrato,
                                        valor_remuneracao_item: item.valor_remuneracao_item || 0,
                                        checklist: []
                                    });
                                });
                                
                                $('#item_os_select').prop('disabled', false);
                                renderItems();
                            });
                        } else {
                            $('#item_os_select').html('<option value="">Primeiro, selecione uma OS no cabeçalho...</option>').prop('disabled', true);
                            $('#nup_sei').val('');
                            loadOsDemands('');
                            renderItems();
                        }
                    } else {
                        // Reverter para o valor original
                        $(this).val(initialOsId);
                        $('#item_os_select').prop('disabled', false);
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
                        const existingChecklist = docItems[editingIndex].checklist || [];
                        docItems[editingIndex] = {
                            id_item_os: idItemOs,
                            quantidade_entregue: qtd,
                            glosa_horas: glosa,
                            observacoes: obs,
                            desc_servico: descServico,
                            profissional: profissional,
                            profissional_alocado: profissional,
                            id_servico: osItemObj.id_servico,
                            numero_item: osItemObj.numero_item,
                            descricao: osItemObj.descricao,
                            sla_dias: osItemObj.sla_dias,
                            remuneracao: osItemObj.remuneracao,
                            valor_item_contrato: osItemObj.valor_item_contrato,
                            checklist: existingChecklist
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
                            profissional_alocado: profissional,
                            id_servico: osItemObj.id_servico,
                            numero_item: osItemObj.numero_item,
                            descricao: osItemObj.descricao,
                            sla_dias: osItemObj.sla_dias,
                            remuneracao: osItemObj.remuneracao,
                            valor_item_contrato: osItemObj.valor_item_contrato,
                            checklist: []
                        });
                    }
                    
                    $('#item_os_select').val('');
                    $('#item_qtd').val('');
                    $('#item_glosa').val('0');
                    $('#item_obs').val('');
                    
                    renderItems();
                });

                $('#saveChecklistBtn').click(function() {
                    if (currentItemIndexForChecklist >= 0) {
                        const item = docItems[currentItemIndexForChecklist];
                        item.checklist = [];
                        
                        $('.chk-apply:checked').each(function() {
                            const optId = $(this).attr('data-id');
                            const conforme = $(`input[name="conforme_${optId}"]:checked`).val() == '1' ? 1 : 0;
                            item.checklist.push({
                                id_lista_verificacao: optId,
                                conforme: conforme
                            });
                        });
                        
                        const modalEl = document.getElementById('checklistModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) {
                            modal.hide();
                        }
                        
                        renderItems();
                    }
                });

                $('#saveDocBtn').click(function() {
                    if (!$('#id_demanda').val()) {
                        alert('Por favor, selecione uma demanda válida digitando e escolhendo uma opção da lista.');
                        return;
                    }
                    let formData = $('#updForm').serializeArray();
                    formData.push({name: "items", value: JSON.stringify(docItems)});
                    
                    $.ajax({
                        url: '<?php echo site_url('updateDocumentoRecebimento'); ?>',
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
                            $('#error-message').html('Ocorreu um erro ao atualizar os dados.').show().delay(5000).fadeOut();
                        }
                    });
                });
            });
        </script>
    </div>
</div>

<!-- Modal de Checklist para o Item -->
<div class="modal fade" id="checklistModal" tabindex="-1" aria-labelledby="checklistModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius: 8px;">
      <div class="modal-header" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
        <h5 class="modal-title" id="checklistModalLabel" style="font-weight: 600;">📋 Lista de Verificação (Checklist)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 20px;">
        <p style="font-size: 1.05em; margin-bottom: 15px;"><strong>Item Recebido:</strong> <span id="checklist_item_desc" class="text-primary"></span></p>
        <table class="table table-bordered table-striped" id="modalChecklistTable" style="margin-bottom: 0;">
            <thead class="table-light">
                <tr>
                    <th style="width: 80px; text-align: center;">Aplicar</th>
                    <th>Descrição do Item de Checklist</th>
                    <th style="width: 250px; text-align: center;">Conformidade</th>
                </tr>
            </thead>
            <tbody>
                <!-- Checklists options dynamically rendered here -->
            </tbody>
        </table>
      </div>
      <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn btn-primary" id="saveChecklistBtn">Confirmar Verificação</button>
      </div>
    </div>
  </div>
</div>

<?php require VIEWPATH.'/footer.php'; ?>
