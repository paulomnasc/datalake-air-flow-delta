<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <div class="container">
            <h1>Recriando uma nova senha</h1>
            <form method="post" id="meuFormulario" action="<?= route_to('Usuario.salvaRecriaSenha'); ?>">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="email" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" placeholder="senha" required>
                </div>
                <div class="form-group">
                    <label for="senha">Confirmar senha:</label>
                    <input type="password" id="repete-senha" name="repete_senha" placeholder="senha" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="save-button" value="Atualizar" onclick="return validatePasswords()">Atualizar</button>
                </div>
            </form>

            <!-- div id="success-message" style="display:none;"></div>
            <div id="error-message" style="display:none;"></div-->

            <script>
                function validatePasswords() {
                    var senha = document.getElementById("senha").value;
                    var repeteSenha = document.getElementById("repete-senha").value;
                    if (senha !== repeteSenha) {
                        alert('As senhas não coincidem.');
                        return false;
                    }
                    return true;
                }

                $('#meuFormulario').submit(function(event) {
                     // Para verificar se o evento é capturado
                    event.preventDefault();
                    if (!validatePasswords()) {
                        return false;
                    }
                    var formData = $(this).serialize();
                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: formData,
                        success: function(result) {
                            console.log(result); // Log para verificar a resposta
                            if (result.status === 'success') {
                                $('#success-message').html(result.mensagem).show().delay(6000).fadeOut(function() {
                                    window.location.href = "<?= route_to('Usuario.login'); ?>"; // Redireciona para a URL fornecida
                                });
                            } else {
                                $('#error-message').html(result.mensagem).show().delay(6000).fadeOut();
                            }
                        },
                        error: function(err) {
                            
                            console.log('Error status: ' + err.status); 
                            console.log('Error text: ' + err.statusText); 
                            console.log('Error response: ' + err.responseText);
                            console.log(err.responseText); // Log para verificar o erro
                            alert(err.responseText);
                            $('#error-message').html('Erro ao atualizar o registro: ' + err.responseText).show().delay(6000).fadeOut();
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