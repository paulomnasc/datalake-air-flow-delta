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
                <label for="Numero_Contrato">NumeroContrato:</label>
                <input type="text" id="Numero_Contrato" name="Numero_Contrato" value="<?php echo isset($record->Numero_Contrato) ? $record->Numero_Contrato : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Objeto">Objeto:</label>
                <input type="text" id="Objeto" name="Objeto" value="<?php echo isset($record->Objeto) ? $record->Objeto : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Total_Horas_Contratadas">TotalHorasContratadas:</label>
                <input type="number" step="0.01" id="Total_Horas_Contratadas" name="Total_Horas_Contratadas" value="<?php echo isset($record->Total_Horas_Contratadas) ? $record->Total_Horas_Contratadas : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Saldo_Horas">SaldoHoras:</label>
                <input type="number" step="0.01" id="Saldo_Horas" name="Saldo_Horas" value="<?php echo isset($record->Saldo_Horas) ? $record->Saldo_Horas : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Data_Inicio">DataInicio:</label>
                <input type="datetime-local" id="Data_Inicio" name="Data_Inicio" value="<?php echo isset($record->Data_Inicio) ? $record->Data_Inicio : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Data_Fim">DataFim:</label>
                <input type="datetime-local" id="Data_Fim" name="Data_Fim" value="<?php echo isset($record->Data_Fim) ? $record->Data_Fim : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="id_contrato">Contrato:</label>
                <select id="id_contrato" name="id_contrato">
                    <option value="">Selecione um Contrato</option>
                    <?php if(isset($contrato_list)): foreach($contrato_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php if(isset($record->id_contrato) && $record->id_contrato == $opt->id) echo 'selected'; ?>><?php echo $opt->descricao; ?></option>
                    <?php endforeach; endif; ?>
                </select>
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
