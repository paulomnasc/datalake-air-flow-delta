<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de ItemOs</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="quantidade_horas">QuantidadeHoras:</label>
                <input type="number" step="0.01" id="quantidade_horas" name="quantidade_horas" required>
            </div>

            <div class="form-group">
                <label for="profissional_alocado">ProfissionalAlocado:</label>
                <input type="text" id="profissional_alocado" name="profissional_alocado" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Salvar</button>
                <a href="<?php echo site_url('listItemOs'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#addForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('insertItemOs'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listItemOs'); ?>'; }, 1500);
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
