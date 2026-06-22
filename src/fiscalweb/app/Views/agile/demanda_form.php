<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
$isEdit = isset($demanda);
?>
<div id="content">        
    <div class="container my-4" style="max-width: 800px;">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 card-title"><i class="fas fa-file-invoice"></i> <?= $isEdit ? 'Editar Demanda' : 'Nova Demanda (Gatelink)' ?></h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= $isEdit ? route_to('agile.demanda.update') : route_to('agile.demanda.insert') ?>" method="post">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="id" value="<?= $demanda->id ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="titulo" class="form-label">Título da Demanda</label>
                        <input type="text" class="form-control form-control-lg" id="titulo" name="titulo" value="<?= $isEdit ? htmlspecialchars($demanda->titulo) : '' ?>" placeholder="Ex: Módulo de Relatórios de Impostos" required>
                    </div>

                    <div class="mb-4">
                        <label for="descricao" class="form-label">Descrição / Objetivo da Demanda</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4" placeholder="Descreva os requisitos gerais e objetivos..." required><?= $isEdit ? htmlspecialchars($demanda->descricao ?? '') : '' ?></textarea>
                    </div>

                    <!-- Gatelink Questionnaire (Mandatory business rule) -->
                    <div class="card bg-light border-warning mb-4">
                        <div class="card-body">
                            <h6 class="text-warning-emphasis mb-2"><i class="fas fa-exclamation-triangle"></i> Questionário Obrigatório de Criticidade (Gatelink)</h6>
                            <p class="text-muted small">Determine o direcionamento do fluxo com base no perfil do sistema.</p>
                            
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" id="sistema_critico" name="sistema_critico" value="1" <?= ($isEdit && $demanda->sistema_critico) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sistema_critico">
                                    A demanda pertence a um <strong>Sistema Crítico</strong>?
                                </label>
                            </div>
                            <div class="mt-2 text-muted small" id="gatelink-help">
                                <span class="d-block" id="help-sim" style="display:none !important;"><i class="fas fa-arrow-right"></i> <strong>SIM:</strong> Direcionado à raia <strong>COSIS (SERPRO)</strong> com ciclo ALM correspondente.</span>
                                <span class="d-block" id="help-nao"><i class="fas fa-arrow-right"></i> <strong>NÃO:</strong> Direcionado à raia <strong>Alocar Time Fábricas</strong> acionando squads parceiras.</span>
                            </div>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="mb-4">
                            <label for="status" class="form-label">Status da Demanda</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Triagem" <?= $demanda->status === 'Triagem' ? 'selected' : '' ?>>Triagem</option>
                                <option value="Preparar Demanda SERPRO" <?= $demanda->status === 'Preparar Demanda SERPRO' ? 'selected' : '' ?>>Preparar Demanda SERPRO</option>
                                <option value="Alocar Time Fábricas" <?= $demanda->status === 'Alocar Time Fábricas' ? 'selected' : '' ?>>Alocar Time Fábricas</option>
                                <option value="Refinamento Backlog" <?= $demanda->status === 'Refinamento Backlog' ? 'selected' : '' ?>>Refinamento Backlog</option>
                                <option value="Sprint Planning" <?= $demanda->status === 'Sprint Planning' ? 'selected' : '' ?>>Sprint Planning</option>
                                <option value="Em Execução" <?= $demanda->status === 'Em Execução' ? 'selected' : '' ?>>Em Execução</option>
                                <option value="Homologação" <?= $demanda->status === 'Homologação' ? 'selected' : '' ?>>Homologação</option>
                                <option value="Submissão Release" <?= $demanda->status === 'Submissão Release' ? 'selected' : '' ?>>Submissão Release</option>
                                <option value="CCM" <?= $demanda->status === 'CCM' ? 'selected' : '' ?>>CCM</option>
                                <option value="SERPRO" <?= $demanda->status === 'SERPRO' ? 'selected' : '' ?>>SERPRO</option>
                                <option value="Atualizado Produção" <?= $demanda->status === 'Atualizado Produção' ? 'selected' : '' ?>>Atualizado Produção</option>
                                <option value="Atualizado Produção (Esteira SERPRO)" <?= $demanda->status === 'Atualizado Produção (Esteira SERPRO)' ? 'selected' : '' ?>>Atualizado Produção (Esteira SERPRO)</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= route_to('agile.demandas') ?>" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Demanda</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function toggleGatelinkHelp() {
        if ($('#sistema_critico').is(':checked')) {
            $('#help-sim').attr('style', 'display:block !important;');
            $('#help-nao').attr('style', 'display:none !important;');
        } else {
            $('#help-sim').attr('style', 'display:none !important;');
            $('#help-nao').attr('style', 'display:block !important;');
        }
    }

    $('#sistema_critico').on('change', toggleGatelinkHelp);
    toggleGatelinkHelp(); // Executa inicial
});
</script>

<?php
require VIEWPATH.'/footer.php';
?>
