<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de ItemOs</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            
            <div class="form-group">
                <label for="Quantidade_Horas">QuantidadeHoras:</label>
                <input type="number" step="0.01" id="Quantidade_Horas" name="Quantidade_Horas" value="<?php echo isset($record->Quantidade_Horas) ? $record->Quantidade_Horas : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="Profissional_Alocado">ProfissionalAlocado:</label>
                <input type="text" id="Profissional_Alocado" name="Profissional_Alocado" value="<?php echo isset($record->Profissional_Alocado) ? $record->Profissional_Alocado : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="id_os">IdOS:</label>
                <select id="id_os" name="id_os" required>
                    <option value="">Selecione a Ordem de Serviço...</option>
                    <?php if(isset($id_os_list)): foreach($id_os_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_os) && $record->id_os == $opt->id) ? 'selected' : ''; ?>>
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
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->id_servico) && $record->id_servico == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('listItemOs'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {
                // Load initial macro based on selected service
                var initialServicoId = $('#id_servico').val();
                if (initialServicoId) {
                    $.ajax({
                        url: '<?php echo site_url('getMacroByServico'); ?>/' + initialServicoId,
                        type: 'GET',
                        success: function(data) {
                            if (data.id_atividade_macro) {
                                $('#id_atividade_macro').val(data.id_atividade_macro);
                            }
                        }
                    });
                }

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
                                    var selected = (item.id == initialServicoId) ? 'selected' : '';
                                    servicoSelect.append('<option value="' + item.id + '" ' + selected + '>' + item.descricao + '</option>');
                                });
                            },
                            error: function() {
                                alert('Erro ao carregar serviços.');
                            }
                        });
                    }
                });

                $('#updForm').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: '<?php echo site_url('updateItemOs'); ?>',
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
