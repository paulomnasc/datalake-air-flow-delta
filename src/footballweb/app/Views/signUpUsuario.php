<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <div class="container">
            <h1><?= lang('App.register_title') ?></h1>

            <!-- Google Registration Button Highlighted -->
            <div style="margin-bottom: 30px; text-align: center;">
                <a href="<?= route_to('auth.google.login') ?>" class="btn btn-danger" style="background-color: #ea4335; border-color: #ea4335; color: white; text-decoration: none; padding: 14px 32px; display: inline-block; border-radius: 6px; font-size: 1.2em; font-weight: bold; box-shadow: 0 2px 8px rgba(234,67,53,0.15);">
                    <i class="fab fa-google" style="font-size: 1.4em; vertical-align: middle;"></i> <?= lang('App.register_google') ?>
                </a>
                <p style="color: #222; margin-top: 12px; font-weight: 500;"><?= lang('App.quick_google_register') ?></p>
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <p style="color: #666; margin-bottom: 10px;">— <?= lang('App.or') ?> —</p>
                <!-- Removed Google button from here -->
            </div>
            <form method="post" id="meuFormulario" action="<?php echo route_to('Usuario.insertSigIn'); ?>">
                <!-- ...existing code... -->
                <div class="form-group">
                    <label for="nome"><?= lang('App.name') ?>:</label>
                    <input type="text" id="nome" name="nome" placeholder="<?= lang('App.name') ?>" required>
                </div>

                <div class="form-group">
                    <label for="email"><?= lang('App.email') ?>:</label>
                    <input type="email" id="email" name="email" placeholder="<?= lang('App.email') ?>" required>
                </div>

                <div class="form-group">
                    <label for="id_perfil"><?= lang('App.profiles') ?>:</label>
                    <select id="id_perfil" name="id_perfil[]" multiple required style="height: 120px;">
                        <?php foreach($perfis as $perfil): ?>
                            <option value="<?php echo $perfil->id; ?>" <?php echo (!empty($descricao_perfil_selecionado) && $perfil->descricao == $descricao_perfil_selecionado) ? 'selected' : ''; ?>>
                                <?php echo $perfil->descricao; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 5px; color: #666;"><?= lang('App.select_multiple_profiles_hint') ?></small>
                </div>

                <div class="form-group">
                    <label for="senha"><?= lang('App.password') ?>:</label>
                    <input type="password" id="senha" name="senha" placeholder="<?= lang('App.password') ?>" required>
                </div>

                <div class="form-group">
                    <label for="senha"><?= lang('App.confirm_password') ?>:</label>
                    <input type="password" id="repete-senha" name="senha" placeholder="<?= lang('App.confirm_password') ?>" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="save-button" value="Atualizar"><?= lang('App.register') ?></button>
                    <!-- button type="button" class="back-button" onclick="history.back();">Voltar</button -->
                </div>
                <div id="signup-success-message" class="alert alert-success" style="display:none;"></div>
                <div id="error-message" class="alert alert-danger" style="display:none; text-align:center; max-width: 500px; margin: 30px auto 0 auto; font-size: 1.1em;"></div>
            </form>

            

        <script>
        function validateSignUpForm() {
            var errors = [];
            var nome = document.getElementById("nome").value.trim();
            var email = document.getElementById("email").value.trim();
            var senha = document.getElementById("senha").value;
            var repeteSenha = document.getElementById("repete-senha").value;
            var perfis = document.getElementById("id_perfil");
            var selectedPerfis = Array.from(perfis.selectedOptions).map(opt => opt.value);

            if (!nome) errors.push("• O campo <b>Nome</b> é obrigatório.");
            if (!email) errors.push("• O campo <b>Email</b> é obrigatório.");
            if (!senha) errors.push("• O campo <b>Senha</b> é obrigatório.");
            if (!repeteSenha) errors.push("• O campo <b>Confirmar senha</b> é obrigatório.");
            if (senha && repeteSenha && senha !== repeteSenha) errors.push("• As senhas não coincidem.");
            if (selectedPerfis.length === 0) errors.push("• Selecione pelo menos um <b>Perfil</b>.");

            return errors;
        }

        $(document).ready(function() {
            $('#meuFormulario').submit(function(event) {
                event.preventDefault();
                var errors = validateSignUpForm();
                if (errors.length > 0) {
                    var html = '<ul style="text-align:left; margin:0 auto; display:inline-block;">' + errors.map(function(e){return '<li>'+e+'</li>';}).join('') + '</ul>';
                    $('#error-message').html(html).fadeIn();
                    $('html,body').animate({scrollTop: $('#error-message').offset().top - 100}, 300);
                    setTimeout(function(){ $('#error-message').fadeOut(); }, 8000);
                    return false;
                } else {
                    $('#error-message').hide();
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
                                    window.location.href = result.redirect;
                                }
                            });
                        } else {
                            $('#error-message').html(result.mensagem).show().delay(6000).fadeOut();
                        }
                    },
                    error: function(err) {
                        $('#error-message').html('Erro ao atualizar o registro.').show().delay(6000).fadeOut();
                        console.log(err);
                    }
                });
            });
        });
        </script>

        </div>
    </div>


</div>

<?php
require VIEWPATH . '/footer.php';
?>