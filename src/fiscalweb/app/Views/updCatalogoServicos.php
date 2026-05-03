<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de CatalogoServicos</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo $record->id; ?>">
            
            <div class="form-group">
                <label for="id_area_atuacao">IdAreaAtuacao:</label>
                <select id="id_area_atuacao" name="id_area_atuacao" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_area_atuacao_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo ($record->id_area_atuacao == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="cod_item_unificado">CodItemUnificado:</label>
                <input type="text" id="cod_item_unificado" name="cod_item_unificado" value="<?php echo $record->cod_item_unificado; ?>" required>
            </div>

            <div class="form-group">
                <label for="descricao">Descricao:</label>
                <input type="text" id="descricao" name="descricao" value="<?php echo $record->descricao; ?>" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listCatalogoServicos'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateCatalogoServicos'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listCatalogoServicos'); ?>'; }, 1500);
                            } else {
                                $('#error-message').html(response.mensagem).show().delay(5000).fadeOut();
                            }
                        },
                        error: function() {
                            $('#error-message').html('Ocorreu um erro ao salvar os dados.').show().delay(5000).fadeOut();
                        }
                    });
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
