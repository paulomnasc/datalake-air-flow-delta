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
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listOrdemServico'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateOrdemServico'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
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
