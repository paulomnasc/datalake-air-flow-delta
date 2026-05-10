<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de AvaliacaoQualidadeSla</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            
            <div class="form-group">
                <label for="id_documento_recebimento">IdDocumentoRecebimento:</label>
                <select id="id_documento_recebimento" name="id_documento_recebimento" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_documento_recebimento_list)): foreach($id_documento_recebimento_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_documento_recebimento) && $record->id_documento_recebimento == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="Nota_INS1_Pontualidade">NotaIns1Pontualidade:</label>
                <input type="number" step="0.01" id="Nota_INS1_Pontualidade" name="Nota_INS1_Pontualidade" value="<?php echo isset($record->Nota_INS1_Pontualidade) ? $record->Nota_INS1_Pontualidade : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Nota_INS2_Qualidade">NotaIns2Qualidade:</label>
                <input type="number" step="0.01" id="Nota_INS2_Qualidade" name="Nota_INS2_Qualidade" value="<?php echo isset($record->Nota_INS2_Qualidade) ? $record->Nota_INS2_Qualidade : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Percentual_Glosa">PercentualGlosa:</label>
                <input type="number" step="0.01" id="Percentual_Glosa" name="Percentual_Glosa" value="<?php echo isset($record->Percentual_Glosa) ? $record->Percentual_Glosa : ''; ?>" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listAvaliacaoQualidadeSla'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateAvaliacaoQualidadeSla'); ?>',
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
