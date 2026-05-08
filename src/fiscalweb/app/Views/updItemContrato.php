<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de ItemContrato</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            
            <div class="form-group">
                <label for="gestor_substituto">GestorSubstituto:</label>
                <input type="text" id="gestor_substituto" name="gestor_substituto" value="<?php echo isset($record->gestor_substituto) ? $record->gestor_substituto : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="numero_contrato">NumeroContrato:</label>
                <input type="text" id="numero_contrato" name="numero_contrato" value="<?php echo isset($record->numero_contrato) ? $record->numero_contrato : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="objeto">Objeto:</label>
                <input type="text" id="objeto" name="objeto" value="<?php echo isset($record->objeto) ? $record->objeto : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="total_horas_contratadas">TotalHorasContratadas:</label>
                <input type="number" step="0.01" id="total_horas_contratadas" name="total_horas_contratadas" value="<?php echo isset($record->total_horas_contratadas) ? $record->total_horas_contratadas : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="saldo_horas">SaldoHoras:</label>
                <input type="number" step="0.01" id="saldo_horas" name="saldo_horas" value="<?php echo isset($record->saldo_horas) ? $record->saldo_horas : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="data_inicio">DataInicio:</label>
                <input type="datetime-local" id="data_inicio" name="data_inicio" value="<?php echo isset($record->data_inicio) ? $record->data_inicio : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="data_fim">DataFim:</label>
                <input type="datetime-local" id="data_fim" name="data_fim" value="<?php echo isset($record->data_fim) ? $record->data_fim : ''; ?>" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listItemContrato'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateItemContrato'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listItemContrato'); ?>'; }, 1500);
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
