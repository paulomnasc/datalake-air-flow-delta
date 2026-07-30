<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>
<style>
.login-container-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(85vh - 100px);
    padding: 40px 15px;
}

#card-body-login {
    width: 100%;
    max-width: 420px;
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    padding: 35px 30px;
    margin: 0 auto;
    border: 1px solid #e5e7eb;
}

.login-box-msg {
    font-size: 1.15rem;
    font-weight: 700;
    color: #1f2937;
    text-align: center;
    margin-bottom: 25px;
}

.btn-primary-block {
    width: 100%;
    padding: 10px;
    font-weight: 600;
    border-radius: 6px;
}

.auth-links a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.92rem;
    transition: color 0.2s;
}

.auth-links a:hover {
    color: #1d4ed8;
    text-decoration: underline;
}
</style>

<div class="login-container-wrapper">
    <div id="card-body-login">

        <p class="login-box-msg"><?= lang('App.login_title') ?></p>

        <form method="post" id="meuFormulario" action="<?php echo route_to('Usuario.logar'); ?>">
            <input type="hidden" name="table" value="usuario">
            
            <div class="input-group mb-3">
                <input type="email" id="email" name="email" class="form-control" placeholder="<?= lang('App.email') ?>" required>
                <div class="input-group-append">
                    <div class="input-group-text h-100">
                        <span class="fas fa-envelope"></span>
                    </div>
                </div>
            </div>

            <div class="input-group mb-3">
                <input type="password" id="senha" name="senha" class="form-control" placeholder="<?= lang('App.password') ?>" required>
                <div class="input-group-append">
                    <div class="input-group-text h-100">
                        <span class="fas fa-lock"></span>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-block btn-primary-block"><?= lang('App.enter') ?></button>
                </div>
            </div>
        </form>

        <div style="margin-top: 24px; text-align: center;">
            <p style="color: #9ca3af; margin-bottom: 14px; font-size: 0.85rem; font-weight: 600;">— <?= lang('App.or') ?> —</p>
            <a href="<?= route_to('auth.google.login') ?>" class="btn btn-danger btn-block w-100 py-2 d-flex align-items-center justify-content-center gap-2" style="background-color: #ea4335; border-color: #ea4335; color: white; text-decoration: none; font-weight: 600; border-radius: 6px;">
                <i class="fab fa-google"></i> <?= lang('App.login_google') ?>
            </a>
        </div>

        <hr style="margin: 24px 0; border-color: #f3f4f6;">

        <div class="auth-links text-center">
            <p class="mb-2">
                <?php echo anchor("recriaSenha", lang('App.forgot_password')) ?>
            </p>
            <p class="mb-0">
                <?php echo anchor("sigInUsuario", lang('App.register_new_member')) ?>
            </p>
        </div>

    </div>
</div>

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
                        window.location.href = "<?php echo base_url('dashboard'); ?>";
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
</script>

<?php
require VIEWPATH . '/footer.php';
?>