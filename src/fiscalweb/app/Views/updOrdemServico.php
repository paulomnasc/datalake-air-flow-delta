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
            <input type="hidden" name="id" value="<?php echo $record->id; ?>">
            
            <div class="form-group">
                <label for="horas_alocadas">HorasAlocadas:</label>
                <input type="text" id="horas_alocadas" name="horas_alocadas" value="<?php echo $record->horas_alocadas; ?>" required>
            </div>

            <div class="form-group">
                <label for="nup_sei">NupSei:</label>
                <input type="text" id="nup_sei" name="nup_sei" value="<?php echo $record->nup_sei; ?>" required>
            </div>

            <div class="form-group">
                <label for="data_emissao">DataEmissao:</label>
                <input type="text" id="data_emissao" name="data_emissao" value="<?php echo $record->data_emissao; ?>" required>
            </div>

            <div class="form-group">
                <label for="data_aceite">DataAceite:</label>
                <input type="text" id="data_aceite" name="data_aceite" value="<?php echo $record->data_aceite; ?>" required>
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
