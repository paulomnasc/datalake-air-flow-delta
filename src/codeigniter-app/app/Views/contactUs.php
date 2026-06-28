<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">

        <!-- Contact Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h6 class="section-title bg-white text-center text-primary px-3">Entre em contato</h6>
                    <h1 class="mb-5">Contacte para qualquer pergunta</h1>
                </div>
                <div class="row g-4">
                    <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.1s">
                        <h5>Entre em contato</h5>
                        <p class="mb-4">
                            Seu contato é muito importante  para nós. Para agilizar crie um assunto claro e conciso explicitando sua necessidade que 
                            entraremos em contato com a maior brevidade possível. 

                            <div class="d-flex align-items-center mb-3">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 bg-primary" style="width: 50px; height: 50px;">
                                <i class="fa fa-map-marker-alt text-white"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="text-primary">Office</h5>
                                <p class="mb-0">Brasilia, DF, BR</p>
                            </div>
                        </div>
                        <!-- div class="d-flex align-items-center mb-3">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 bg-primary" style="width: 50px; height: 50px;">
                                <i class="fa fa-phone-alt text-white"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="text-primary">Mobile</h5>
                                <p class="mb-0">+012 345 67890</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 bg-primary" style="width: 50px; height: 50px;">
                                <i class="fa fa-envelope-open text-white"></i>
                            </div>
                            
                        </div-->
                    </div>
                    <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.3s">
                        <iframe class="position-relative rounded w-100 h-100"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d306650.9956758128!2d-48.110136400000006!3d-15.7801487!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x935a3b5b97a76b13%3A0xa489c8b2b8cf7fd5!2sBras%C3%ADlia%2C%20DF%2C%20Brasil!5e0!3m2!1spt-BR!2sus!4v1698062265430!5m2!1spt-BR!2sus"
                            frameborder="0" style="min-height: 300px; border:0;" allowfullscreen="" aria-hidden="false"
                            tabindex="0"></iframe>
                    </div>
                    <div class="col-lg-4 col-md-12 wow fadeInUp" data-wow-delay="0.5s">

                    <form method="post" id="meuFormulario" action="<?= route_to('email'); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Your Email" required>
                                    <label for="email">Seu Email</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="subject" name="assunto" placeholder="Subject" required>
                                    <label for="subject">Assunto</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" placeholder="Leave a message here" id="mensagem" name="mensagem" style="height: 150px" required></textarea>
                                    <label for="mensagem">Mensagem</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100 py-3">Enviar</button>
                            </div>
                        </div>
                    </form>

                    </div>
                </div>
            </div>
        </div>
        <!-- Contact End -->

    
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
                $('#error-message').html('Erro ao enviar e-mail: ' + err.responseText).show().delay(6000).fadeOut();
            }
        });
    });
</script>

<?php
require VIEWPATH . '/footer.php';
?>