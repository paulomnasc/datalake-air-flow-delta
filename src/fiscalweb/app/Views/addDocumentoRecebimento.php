<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de DocumentoRecebimento</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="id_os">IdOs:</label>
                <select id="id_os" name="id_os" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_os_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="data_assinatura">Data Assinatura:</label>
                <input type="date" id="data_assinatura" name="data_assinatura" required>
            </div>

            <div class="form-group">
                <label for="nup_sei">NUP/SEI:</label>
                <input type="text" id="nup_sei" name="nup_sei" required>
            </div>

            <div class="form-group">
                <label for="id_tipo_documento">IdTipoDocumento:</label>
                <select id="id_tipo_documento" name="id_tipo_documento" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_tipo_documento_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_usuario_fiscal_tecnico">IdUsuarioFiscalTecnico:</label>
                <select id="id_usuario_fiscal_tecnico" name="id_usuario_fiscal_tecnico" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_usuario_fiscal_tecnico_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_usuario_fiscal_requisitante">IdUsuarioFiscalRequisitante:</label>
                <select id="id_usuario_fiscal_requisitante" name="id_usuario_fiscal_requisitante" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_usuario_fiscal_requisitante_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_usuario_gestor">IdUsuarioGestor:</label>
                <select id="id_usuario_gestor" name="id_usuario_gestor" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_usuario_gestor_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Salvar</button>
                <a href="<?php echo site_url('listDocumentoRecebimento'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#addForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('insertDocumentoRecebimento'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listDocumentoRecebimento'); ?>'; }, 1500);
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
