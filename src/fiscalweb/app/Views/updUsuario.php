<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

<div id="content" class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h4>Editar Usuário</h4>
        </div>
        <div class="card-body">
            <form id="updUsuarioForm">
                <input type="hidden" name="id" value="<?= $usuario->id ?>">
                
                <div class="form-group mb-3">
                    <label for="nome">Nome Completo</label>
                    <input type="text" class="form-control" id="nome" name="nome" value="<?= $usuario->nome ?>" required>
                </div>
                <div class="form-group mb-3">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= $usuario->email ?>" required>
                </div>
                <div class="form-group mb-4">
                    <label for="senha">Senha <small class="text-muted">(Deixe em branco para manter a atual)</small></label>
                    <input type="password" class="form-control" id="senha" name="senha">
                </div>
                
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                <a href="<?= site_url('usuario') ?>" class="btn btn-secondary">Cancelar</a>
            </form>
            <div id="message" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#updUsuarioForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '<?= site_url('updateUsuario/' . $usuario->id) ?>',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if(response.status === 'success') {
                    $('#message').html('<div class="alert alert-success">' + response.mensagem + '</div>');
                    setTimeout(function() {
                        window.location.href = '<?= site_url('usuario') ?>';
                    }, 1500);
                } else {
                    $('#message').html('<div class="alert alert-danger">' + response.mensagem + '</div>');
                }
            },
            error: function() {
                $('#message').html('<div class="alert alert-danger">Erro de comunicação com o servidor.</div>');
            }
        });
    });
});
</script>

<?php require VIEWPATH.'/footer.php'; ?>