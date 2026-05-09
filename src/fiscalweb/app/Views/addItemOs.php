<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de ItemOs</h4>
        
        <form id="addForm">
            
            <div class="form-group">
                <label for="Quantidade_Horas">QuantidadeHoras:</label>
                <input type="number" step="0.01" id="Quantidade_Horas" name="Quantidade_Horas" required>
            </div>

            <div class="form-group">
                <label for="Profissional_Alocado">ProfissionalAlocado:</label>
                <input type="text" id="Profissional_Alocado" name="Profissional_Alocado" required>
            </div>

            <div class="form-group">
                <label for="id_os">IdOS:</label>
                <select id="id_os" name="id_os" required>
                    <option value="">Selecione a Ordem de Serviço...</option>
                    <?php if(isset($id_os_list)): foreach($id_os_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nup_sei) ? $opt->nup_sei : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_atividade_macro">Macro Serviço:</label>
                <select id="id_atividade_macro" name="id_atividade_macro" required>
                    <option value="">Selecione o Macro Serviço...</option>
                    <?php if(isset($id_atividade_macro_list)): foreach($id_atividade_macro_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="id_servico">IdServico:</label>
                <select id="id_servico" name="id_servico" required>
                    <option value="">Selecione...</option>
                    <?php if(isset($id_servico_list)): foreach($id_servico_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Salvar</button>
                <a href="<?php echo site_url('listItemOs'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                // Filter services based on selected macro
                $('#id_atividade_macro').on('change', function() {
                    var macroId = $(this).val();
                    var servicoSelect = $('#id_servico');
                    servicoSelect.empty().append('<option value="">Selecione...</option>');
                    
                    if (macroId) {
                        $.ajax({
                            url: '<?php echo site_url('getServicoByMacro'); ?>/' + macroId,
                            type: 'GET',
                            success: function(data) {
                                $.each(data, function(index, item) {
                                    servicoSelect.append('<option value="' + item.id + '">' + item.descricao + '</option>');
                                });
                            },
                            error: function() {
                                alert('Erro ao carregar serviços.');
                            }
                        });
                    }
                });

                $('#addForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('insertItemOs'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() { window.location.href = '<?php echo site_url('listItemOs'); ?>'; }, 1500);
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
