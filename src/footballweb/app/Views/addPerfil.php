<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <div class="container">
            <h1>Criar Novo Perfil</h1>
            <form method="post" id="meuFormulario" action="<?php echo route_to('Perfil.insert'); ?>">
                <div class="form-group">
                    <label for="descricao">Descrição:</label>
                    <input type="text" name="descricao" placeholder="Descrição" required>
                </div>
                
                <div class="form-group">
                    <label for="id_funcionalidade">Funcionalidades:</label>
                    <select id="id_funcionalidade" name="id_funcionalidade[]" multiple style="height: 120px;">
                        <?php if(isset($funcionalidades) && !empty($funcionalidades)): ?>
                            <?php foreach($funcionalidades as $funcionalidade): ?>
                                <option value="<?php echo $funcionalidade->id; ?>">
                                    <?php echo $funcionalidade->descricao; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small style="display: block; margin-top: 5px; color: #666;">Segure Ctrl (Windows) ou Cmd (Mac) para selecionar múltiplas funcionalidades</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="save-button" value="Atualizar">Salvar</button>
                    <button type="button" class="back-button" onclick="history.back();">Voltar</button>
                </div>
            </form>

            <!-- <div id="success-message" class="alert alert-success" style="display:none;"></div>
            <div id="error-message" class="alert alert-warning" style="display:none;"></div>
 -->
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
                                window.location.href = "<?php echo route_to('listPerfil'); ?>"; // Redireciona para listPerfil após exibir a mensagem
                            }); // Mostra a mensagem de sucesso
                        } else {
                            $('#error-message').html(result.mensagem).show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                        }
                    },
                    error: function(err) {
                        $('#error-message').html('Erro ao salvar o perfil.').show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                        console.log(err); // Trate o erro aqui
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