<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

    <div id="content">
        <div class="container">
            <h1>Editar Grupo</h1>
            <form method="post" id="grupoForm" action="<?php echo route_to('Grupo.update'); ?>">
                
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="form-group">
                    <label for="nome">Nome do Grupo:</label>
                    <input type="text" id="nome" name="nome" value="<?php echo esc($nome); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail do Grupo:</label>
                    <input type="email" id="email" name="email" value="<?php echo esc($email); ?>" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="save-button">Atualizar</button>
                    <button type="button" class="back-button" onclick="history.back();">Voltar</button>
                </div>

            </form>

            <script>
                $('#grupoForm').submit(function(event) {
                    event.preventDefault();
                    var formData = $(this).serialize();
                    $.ajax({
                        url: $(this).attr('action'),
                        type: 'POST',
                        data: formData,
                        success: function(result) {
                            if (result.status === 'success') {
                                alert(result.mensagem);
                                window.location.href = "<?php echo route_to('listGrupo'); ?>";
                            } else {
                                alert(result.mensagem);
                            }
                        },
                        error: function(err) {
                            alert('Erro ao atualizar o registro.');
                            console.log(err);
                        }
                    });
                });
            </script>
        </div>
    </div>

<?php require VIEWPATH.'/footer.php'; ?>
