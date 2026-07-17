<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de ItemContrato</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="gestor_substituto">GestorSubstituto:</label>
                <input type="text" id="gestor_substituto" name="gestor_substituto" required>
            </div>

            <div class="form-group">
                <label for="Numero_Contrato">NumeroContrato:</label>
                <input type="text" id="Numero_Contrato" name="Numero_Contrato" required>
            </div>

            <div class="form-group">
                <label for="Objeto">Objeto:</label>
                <input type="text" id="Objeto" name="Objeto" required>
            </div>

            <div class="form-group">
                <label for="Total_Horas_Contratadas">TotalHorasContratadas:</label>
                <input type="number" step="0.01" id="Total_Horas_Contratadas" name="Total_Horas_Contratadas" required>
            </div>

            <div class="form-group">
                <label for="Saldo_Horas">SaldoHoras:</label>
                <input type="number" step="0.01" id="Saldo_Horas" name="Saldo_Horas" required>
            </div>

            <div class="form-group">
                <label for="Data_Inicio">DataInicio:</label>
                <input type="datetime-local" id="Data_Inicio" name="Data_Inicio" required>
            </div>

            <div class="form-group">
                <label for="Data_Fim">DataFim:</label>
                <input type="datetime-local" id="Data_Fim" name="Data_Fim" required>
            </div>

            <div class="form-group">
                <label for="id_contrato">Contrato:</label>
                <select id="id_contrato" name="id_contrato">
                    <option value="">Selecione um Contrato</option>
                    <?php if(isset($contrato_list)): foreach($contrato_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>"><?php echo $opt->descricao; ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_metrica">Métrica:</label>
                <select id="id_metrica" name="id_metrica" required>
                    <option value="">Selecione uma Métrica</option>
                    <?php if(isset($metrica_list)): foreach($metrica_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>"><?php echo $opt->descricao; ?> (<?php echo $opt->sigla; ?>)</option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Salvar</button>
                <a href="<?php echo site_url('listItemContrato'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#addForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('insertItemContrato'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listItemContrato'); ?>'; }, 1500);
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
