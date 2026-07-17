<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <div class="container">
            <h1>Criar Nova Funcionalidade</h1>
            <form method="post" id="meuFormulario" action="<?php echo route_to('Funcionalidade.insert'); ?>">
                <div class="form-group">
                    <label for="descricao">Descrição:</label>
                    <input type="text" name="descricao" placeholder="Descrição da funcionalidade" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="save-button" value="Salvar">Salvar</button>
                    <button type="button" class="back-button" onclick="history.back();">Voltar</button>
                </div>
            </form>

 <script>
            $('#meuFormulario').submit(function(event) {
                event.preventDefault();
                var formData = $(this).serialize();
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: formData,
                    success: function(result) {
                        if (result.status === 'success') {
                            $('#success-message').html(result.mensagem).show().delay(6000).fadeOut(function() {
                                window.location.href = "<?php echo route_to('listFuncionalidade'); ?>";
                            });
                        } else {
                            $('#error-message').html(result.mensagem).show().delay(6000).fadeOut();
                        }
                    },
                    error: function(err) {
                        $('#error-message').html('Erro ao salvar a funcionalidade.').show().delay(6000).fadeOut();
                        console.log(err);
                    }
                });
            });
            </script>
        </div>
    </div>


</div>

<?php
require VIEWPATH . '/footer.php';
?>
