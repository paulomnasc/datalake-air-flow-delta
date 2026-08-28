<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <!-- Report Error Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h6 class="section-title bg-white text-center text-primary px-3"><?= lang('App.report_error') ?></h6>
                </div>
                <div class="row g-4 justify-content-center">
                    <div class="col-lg-5 col-md-6 wow fadeInUp" data-wow-delay="0.1s">
                        <h5><?= lang('App.report_error') ?></h5>
                        <div class="d-flex align-items-center mb-3">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 bg-primary text-white rounded-circle" style="width: 50px; height: 50px;">
                                <i class="fa fa-envelope-open" style="font-size: 20px;"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="text-primary mb-0"><?= lang('App.email') ?></h5>
                                <p class="mb-0 text-muted">admin@estudotabela.com.br</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 col-md-12 wow fadeInUp" data-wow-delay="0.5s">

                    <form method="post" id="formReportError" action="<?= route_to('sendReportErrorEmail'); ?>">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="<?= lang('App.email') ?>" value="<?= esc($_SESSION['email_usuario_logado'] ?? $_SESSION['usuario_email'] ?? '') ?>" required>
                                    <label for="email"><?= lang('App.email') ?></label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="subject" name="assunto" placeholder="Assunto" required>
                                    <label for="subject">Assunto</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" placeholder="Descrição" id="mensagem" name="mensagem" style="height: 160px" required></textarea>
                                    <label for="mensagem">Descrição</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100 py-3">
                                    <i class="fa fa-paper-plane me-2"></i><?= lang('App.report_error') ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    </div>
                </div>
            </div>
        </div>
        <!-- Report Error End -->

    </div>

</div>

<script>
    $('#formReportError').submit(function(event) {
        event.preventDefault();
        var formData = $(this).serialize();
        var submitBtn = $(this).find('button[type="submit"]');
        var originalBtnHtml = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-2"></i>Enviando...');
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            success: function(result) {
                submitBtn.prop('disabled', false).html(originalBtnHtml);
                if (result.status === 'success') {
                    $('#success-message').html(result.mensagem).show().delay(6000).fadeOut();
                    $('#formReportError')[0].reset();
                } else {
                    $('#error-message').html(result.mensagem).show().delay(6000).fadeOut();
                }
            },
            error: function(err) {
                submitBtn.prop('disabled', false).html(originalBtnHtml);
                console.log('Error status: ' + err.status); 
                console.log('Error response: ' + err.responseText); 
                var errorMsg = 'Erro ao enviar relatório de erro.';
                if (err.responseJSON && err.responseJSON.mensagem) {
                    errorMsg = err.responseJSON.mensagem;
                }
                $('#error-message').html(errorMsg).show().delay(6000).fadeOut();
            }
        });
    });
</script>

<?php
require VIEWPATH . '/footer.php';
?>
