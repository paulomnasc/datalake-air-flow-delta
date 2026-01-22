<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>
<style>


#card-body-login {
    max-width: 800px;
    width: 400px;
    background-color: white; /* Adicione um fundo para visualização */
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Adiciona sombra */
}

</style>

    <div id="content" class="container-medium">

        <div id="lte" >

            <div id="card-body-login">

                <p class="login-box-msg">Entre para iniciar seus estudos</p>

                <form method="post" id="meuFormulario" action="<?php echo route_to('Usuario.logar'); ?>">
                    <input type="hidden" name="table" value="usuario">
                    
                    <div class="input-group mb-3">
                    <input type="email" id="email" name="email" class="form-control" placeholder="Email" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                    </div>
                    <div class="input-group mb-3">
                    <input type="password" id="senha" name="senha" class="form-control" placeholder="Senha" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                        </div>
                    </div>
                    </div>
                    <div class="row">
                        <!-- div class="col-8">
                            <div class="icheck-primary">
                            <input type="checkbox" id="remember">
                            <label for="remember">
                                Remember Me
                            </label>
                            </div>
                        </div -->
                        <!-- /.col -->
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                        </div>
                        <!-- /.col -->
                    </div>
                </form>

                <div style="margin-top: 20px; text-align: center;">
                    <p style="color: #666; margin-bottom: 10px;">— OU —</p>
                    <a href="<?= route_to('auth.google.login') ?>" class="btn btn-danger btn-block" style="background-color: #ea4335; border-color: #ea4335; color: white; text-decoration: none;">
                        <i class="fab fa-google"></i> Entrar com Google
                    </a>
                </div>

                <!-- div class="social-auth-links text-center mb-3">

                    <!-- Funcionalidade do Google Auth 
                    <div id="g_id_onload"
                        data-client_id="88249765816-a2bvvo2l4qtjsv1dj4lqmfniknodli0h.apps.googleusercontent.com"
                        data-context="signin"
                        data-ux_mode="popup"
                        data-callback="handleCredentialResponse"
                        data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin" data-type="standard"></div>
                    <!-- FIM Funcionalidade do Google Auth -->
                    
                    <!-- p>- OR -</p>
                        <a href="#" class="btn btn-block btn-primary">
                            <i class="fab fa-facebook mr-2"></i> Entrar usando Facebook
                        </a-
                    <a href="#" class="btn btn-block btn-danger">
                    <i class="fab fa-google-plus mr-2"></i> Entrar usando Google+
                    </a>
                </div>
                <!-- /.social-auth-links -->
                <br>
                <p class="mb-1">
                    <?php echo anchor("recriaSenha","Esqueci minha senha")  ?>
                </p>
                <br>
                <p class="mb-0">
                    <?php echo anchor("sigInUsuario","Registre-se como novo membro")  ?>
                </p>
                </div>
                <!-- /.login-card-body -->
            </div>
        </div>
        <!-- /.login-box -->
 
        
    </div>


<script>
    function handleCredentialResponse(response) {
        console.log("Encoded JWT ID token: " + response.credential);
        
        // Enviar o token para o servidor para autenticação ou criar sessão
        fetch('/login-google', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: response.credential })
        }).then(response => response.json())
            .then(data => {
            if (data.success) {
                // Redireciona o usuário para a página de dashboard
                window.location.href = "/";
            } else {
                // Mostra uma mensagem de erro
                console.error("Login falhou");
            }
            });
        }
</script>

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
                                window.location.href = "<?php echo route_to('listPasta'); ?>"; // Redireciona para listPasta após exibir a mensagem
                            }); // Mostra a mensagem de sucesso
                        } else {
                            $('#error-message').html(result.mensagem).show().delay(6000).fadeOut(); // Mostra a mensagem de erro
                        }
                    },
                    error: function(err) {
                        $('#error-message').html('Erro ao atualizar o registro.').show().delay(6000).fadeOut(); // Mostra a mensagem de erro
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