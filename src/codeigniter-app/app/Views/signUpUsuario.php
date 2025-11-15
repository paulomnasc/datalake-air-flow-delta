<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <div class="container">
            <h1>Registrar Usuario</h1>
            <form method="post" id="meuFormulario" action="<?php echo route_to('Usuario.insertSigIn'); ?>">
                  

                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" placeholder="nome" required>
                </div>


                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="email" required>
                </div>


                <div class="form-group">
                    <label for="id_perfil">Perfil:</label>
                    <!-- <select id="id_perfil" name="id_perfil" required <!?php echo !empty($descricao_perfil_selecionado) ? 'disabled' : ''; ?>> -->
                    <select id="id_perfil" name="id_perfil" required>
                    
                        <option value="">Selecione</option>
                        <?php foreach($perfis as $perfil): ?>
                            <option value="<?php echo $perfil->id; ?>" <?php echo (!empty($descricao_perfil_selecionado) && $perfil->descricao == $descricao_perfil_selecionado) ? 'selected' : ''; ?>>
                                <?php echo $perfil->descricao; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>



                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" placeholder="senha" required>
                </div>

                <div class="form-group">
                    <label for="senha">Confirmar senha:</label>
                    <input type="password" id="repete-senha" name="senha" placeholder="senha" required>
                </div>
                    
                <div class="form-actions">
                    <button type="submit" class="save-button" value="Atualizar" onclick="return validatePasswords()">Atualizar</button>
                    <!-- button type="button" class="back-button" onclick="history.back();">Voltar</button -->
                </div>

                <div id="signup-success-message" class="alert alert-success" style="display:none;"></div>

            </form>

        <script>
        function validatePasswords() {
            
            var senha = document.getElementById("senha").value;
            var repeteSenha = document.getElementById("repete-senha").value;

            if (senha !== repeteSenha) {
                alert("As senhas não coincidem");
                document.getElementById("error-message").innerHTML = "As senhas não coincidem";
                return false;
            }
            return true;
        }
        </script>
        <script>
            $('#meuFormulario').submit(function(event) {
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
                        if (result.status === 'success') {
                            $('#signup-success-message').html(result.mensagem).show().delay(13000).fadeOut(function() {
                                if (result.redirect) {
                                    window.location.href = result.redirect; // Redireciona para a URL fornecida
                                }
                            });
                        } else {
                            console.log(result.mensagem);
                            $('#error-message').html(result.mensagem).show().delay(6000).fadeOut();
                        }
                    },
                    error: function(err) {
                        $('#error-message').html('Erro ao atualizar o registro.').show().delay(6000).fadeOut();
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