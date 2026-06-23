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
