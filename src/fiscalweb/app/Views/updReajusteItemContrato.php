<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de Reajuste Item Contrato</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            
            <div class="form-group">
                <label for="id_contrato">Contrato:</label>
                <select id="id_contrato" required>
                    <option value="">Selecione o Contrato...</option>
                    <?php if(isset($contrato_list)): foreach($contrato_list as $c): ?>
                        <option value="<?php echo $c->id; ?>" <?php echo (isset($selected_id_contrato) && $selected_id_contrato == $c->id) ? 'selected' : ''; ?>>
                            <?php echo 'Contrato #' . $c->id . ' - ' . (isset($c->descricao) ? $c->descricao : '') . ($c->empresa ? ' (' . $c->empresa . ')' : ''); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_item_contrato">Item Contrato:</label>
                <select id="id_item_contrato" name="id_item_contrato" required>
                    <option value="">Selecione o Item do Contrato...</option>
                </select>
            </div>

            <div class="form-group">
                <label for="data_reajuste_item_contrato">Data Reajuste:</label>
                <input type="date" id="data_reajuste_item_contrato" name="data_reajuste_item_contrato" value="<?php echo isset($record->data_reajuste_item_contrato) ? $record->data_reajuste_item_contrato : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="valor_item_contrato">Valor:</label>
                <input type="number" step="0.01" id="valor_item_contrato" name="valor_item_contrato" value="<?php echo isset($record->valor_item_contrato) ? $record->valor_item_contrato : ''; ?>" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listReajusteItemContrato'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            const allItems = <?php echo json_encode($item_contrato_list ?? []); ?>;
            const currentItemContratoId = <?php echo json_encode($record->id_item_contrato ?? null); ?>;

            function populateItems(contratoId, selectedItemId) {
                const itemSelect = $('#id_item_contrato');
                itemSelect.empty();

                if (!contratoId) {
                    itemSelect.append('<option value="">Selecione primeiro o Contrato...</option>');
                    itemSelect.prop('disabled', true);
                    return;
                }

                const filteredItems = allItems.filter(item => item.id_contrato == contratoId);

                if (filteredItems.length === 0) {
                    itemSelect.append('<option value="">Nenhum item encontrado para este contrato</option>');
                    itemSelect.prop('disabled', true);
                } else {
                    itemSelect.append('<option value="">Selecione o Item do Contrato...</option>');
                    filteredItems.forEach(item => {
                        const isSel = selectedItemId && item.id == selectedItemId ? 'selected' : '';
                        const desc = `Item #${item.id} - ${item.Objeto || 'Sem Objeto'} (Nº: ${item.Numero_Contrato || '-'})`;
                        itemSelect.append(`<option value="${item.id}" ${isSel}>${desc}</option>`);
                    });
                    itemSelect.prop('disabled', false);
                }
            }

            $(document).ready(function() {
                const initialContratoId = $('#id_contrato').val();
                if (initialContratoId) {
                    populateItems(initialContratoId, currentItemContratoId);
                }

                $('#id_contrato').on('change', function() {
                    populateItems($(this).val(), null);
                });

                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateReajusteItemContrato'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listReajusteItemContrato'); ?>'; }, 1500);
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
