<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de AvaliacaoQualidadeSla</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="id_documento_recebimento">IdDocumentoRecebimento:</label>
                <select id="id_documento_recebimento" name="id_documento_recebimento" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_documento_recebimento_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="nota_ins1_pontualidade">NotaIns1Pontualidade:</label>
                <input type="text" id="nota_ins1_pontualidade" name="nota_ins1_pontualidade" required>
            </div>

            <div class="form-group">
                <label for="nota_ins2_qualidade">NotaIns2Qualidade:</label>
                <input type="text" id="nota_ins2_qualidade" name="nota_ins2_qualidade" required>
            </div>

            <div class="form-group">
                <label for="percentual_glosa">PercentualGlosa:</label>
                <input type="text" id="percentual_glosa" name="percentual_glosa" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Salvar</button>
                <a href="<?php echo site_url('listAvaliacaoQualidadeSla'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#addForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('insertAvaliacaoQualidadeSla'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listAvaliacaoQualidadeSla'); ?>'; }, 1500);
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
