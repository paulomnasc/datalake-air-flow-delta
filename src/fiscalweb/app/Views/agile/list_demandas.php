<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Gestão de Demandas (Módulo Ágil)</h4>
            <a href="<?php echo route_to('agile.demanda.add'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nova Demanda
            </a>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= session()->getFlashdata('success') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= session()->getFlashdata('error') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-2 px-3">
                <form method="get" action="<?= route_to('agile.demandas') ?>" id="filter-form" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label for="id_sistema" class="col-form-label form-label-sm fw-bold text-secondary mb-0">
                            <i class="fas fa-filter me-1"></i> Sistema:
                        </label>
                    </div>
                    <div class="col-auto" style="min-width: 200px;">
                        <select name="id_sistema" id="id_sistema" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Todos os Sistemas --</option>
                            <?php if (!empty($sistemas)): ?>
                                <?php foreach ($sistemas as $sistema): ?>
                                    <option value="<?= $sistema->id ?>" <?= (isset($sistema_selecionado) && (string)$sistema_selecionado === (string)$sistema->id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sistema->sigla . ($sistema->nome ? ' - ' . $sistema->nome : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-auto ms-2">
                        <label for="status" class="col-form-label form-label-sm fw-bold text-secondary mb-0">
                            Status:
                        </label>
                    </div>
                    <div class="col-auto" style="min-width: 200px;">
                        <select name="status" id="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">-- Todos os Status --</option>
                            <?php if (!empty($status_list)): ?>
                                <?php foreach ($status_list as $st): ?>
                                    <option value="<?= htmlspecialchars($st) ?>" <?= (isset($status_selecionado) && $status_selecionado === $st) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if (!empty($sistema_selecionado) || !empty($status_selecionado)): ?>
                        <div class="col-auto ms-2">
                            <a href="<?= route_to('agile.demandas') ?>" class="btn btn-outline-secondary btn-sm" title="Limpar Filtro">
                                <i class="fas fa-times me-1"></i> Limpar filtro
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table class="table table-hover align-middle" id="demandas-table">
                    <thead class="table-light">
                        <tr>
                            <th style="display:none;">ID</th>
                            <th>Título</th>
                            <th>Sistema Crítico?</th>
                            <th>Status Atual</th>
                            <th>Criado Em</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($demandas as $demanda): ?>
                        <tr id="row-<?= $demanda->id ?>">
                            <td style="display:none;"><?= $demanda->id ?></td>
                            <td>
                                <?php if (!empty($demanda->sistema_sigla)): ?>
                                    <span class="badge bg-secondary font-monospace" style="vertical-align: middle; margin-right: 4px;"><?= htmlspecialchars($demanda->sistema_sigla) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($demanda->nup_sei)): ?>
                                    <span class="badge bg-info text-dark font-monospace" style="vertical-align: middle; margin-right: 4px;" title="Ordem de Serviço">OS: <?= htmlspecialchars($demanda->nup_sei) ?></span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($demanda->titulo) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars(substr($demanda->descricao ?? '', 0, 80)) ?>...</small>
                            </td>
                            <td>
                                <?php if ($demanda->sistema_critico): ?>
                                    <span class="badge bg-danger">Sim (SERPRO)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Não (Fábrica)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = 'bg-secondary';
                                if ($demanda->status === 'Em Execução') $badgeClass = 'bg-primary';
                                if ($demanda->status === 'Homologação') $badgeClass = 'bg-warning text-dark';
                                if ($demanda->status === 'Atualizado Produção' || $demanda->status === 'Atualizado Produção (Esteira SERPRO)') $badgeClass = 'bg-success';
                                if ($demanda->status === 'Cancelada') $badgeClass = 'bg-danger';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($demanda->status) ?></span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($demanda->criado_em)) ?></td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="<?= route_to('agile.backlog', $demanda->id) ?>" class="btn btn-outline-secondary btn-sm" title="Backlog do Produto">
                                        <i class="fas fa-list-ol"></i> Backlog
                                    </a>
                                    <a href="<?= route_to('agile.kanban', $demanda->id) ?>" class="btn btn-outline-primary btn-sm" title="Quadro Kanban">
                                        <i class="fas fa-columns"></i> Kanban
                                    </a>
                                    <form action="<?php echo route_to('agile.demanda.upd'); ?>" method="post" class="d-inline">
                                        <input type="hidden" name="id" value="<?php echo $demanda->id ?>">
                                        <button class="btn btn-outline-warning btn-sm" type="submit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete('<?= $demanda->id ?>', '<?= htmlspecialchars($demanda->titulo) ?>')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#demandas-table').DataTable({
        language: {
            "sEmptyTable": "Nenhum registro encontrado",
            "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
            "sInfoFiltered": "(Filtrados de _MAX_ registros)",
            "sLengthMenu": "_MENU_ resultados por página",
            "sZeroRecords": "Nenhum registro encontrado",
            "sSearch": "Pesquisar",
            "oPaginate": {
                "sNext": "Próximo",
                "sPrevious": "Anterior"
            }
        }
    });
});

function confirmDelete(id, titulo) {
    if (confirm("Você tem certeza que deseja deletar a demanda: " + titulo + "?")) {
        $.ajax({
            url: '<?= base_url('agile/demanda/delete') ?>/' + id,
            type: 'POST',
            data: {
                _method: 'DELETE'
            },
            success: function(result) {
                if (result.status === 'success') {
                    $('#row-' + id).remove();
                    alert(result.mensagem);
                } else {
                    alert(result.mensagem);
                }
            },
            error: function(err) {
                alert('Erro ao excluir o registro.');
            }
        });
    }
}
</script>

<?php
require VIEWPATH.'/footer.php';
?>
