<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de Servico</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo $record->id; ?>">
            
            <div class="form-group">
                <label for="id_item_os">IdItemOs:</label>
                <select id="id_item_os" name="id_item_os" required>
                    <option value="">Selecione...</option>
                    <?php foreach($id_item_os_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo ($record->id_item_os == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="descricao">Descricao:</label>
                <input type="text" id="descricao" name="descricao" value="<?php echo $record->descricao; ?>" required>
            </div>

            <div class="form-group">
                <label for="remuneracao">Remuneracao:</label>
                <input type="number" step="0.01" id="remuneracao" name="remuneracao" value="<?php echo $record->remuneracao; ?>" required>
            </div>

            <div class="form-group">
                <label for="base_horas_mes">BaseHorasMes:</label>
                <input type="number" step="0.01" id="base_horas_mes" name="base_horas_mes" value="<?php echo $record->base_horas_mes; ?>" required>
            </div>

            <div class="form-group">
                <label for="base_horas_complexidade">BaseHorasComplexidade:</label>
                <input type="number" step="0.01" id="base_horas_complexidade" name="base_horas_complexidade" value="<?php echo $record->base_horas_complexidade; ?>" required>
            </div>

            <div class="form-group">
                <label for="sla_dias">SlaDias:</label>
                <input type="number" id="sla_dias" name="sla_dias" value="<?php echo $record->sla_dias; ?>" required>
            </div>

            <div class="form-group">
                <label for="estim_max_ano">EstimMaxAno:</label>
                <input type="number" step="0.01" id="estim_max_ano" name="estim_max_ano" value="<?php echo $record->estim_max_ano; ?>" required>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listServico'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateServico'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listServico'); ?>'; }, 1500);
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
