<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container my-4">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Cadastro de Sistemas</h4>
            <button class="btn btn-primary" onclick="novoSistema()"><i class="fas fa-plus"></i> Novo Sistema</button>
        </div>

        <!-- Mensagens de Alerta -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= session()->getFlashdata('success') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tabela DataTables -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table class="table table-hover align-middle" id="sistemas-table">
                    <thead class="table-light">
                        <tr>
                            <th style="display:none;">ID</th>
                            <th>Sigla</th>
                            <th>Nome do Sistema</th>
                            <th>Descrição</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sistemas as $sistema): ?>
                        <tr id="row-<?= $sistema->id ?>">
                            <td style="display:none;"><?= $sistema->id ?></td>
                            <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($sistema->sigla) ?></span></td>
                            <td><strong><?= htmlspecialchars($sistema->nome) ?></strong></td>
                            <td class="text-muted small"><?= htmlspecialchars($sistema->descricao ?? 'Sem descrição.') ?></td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-warning btn-sm" onclick="editSistema(<?= htmlspecialchars(json_encode($sistema)) ?>)" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $sistema->id ?>, '<?= htmlspecialchars($sistema->sigla) ?>')" title="Excluir">
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

<!-- Modal para Cadastro de Sistema -->
<div class="modal fade" id="sistemaModal" tabindex="-1" aria-labelledby="sistemaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="sistemaModalLabel"><i class="fas fa-desktop"></i> Sistema</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-modal="modal" onclick="$('#sistemaModal').modal('hide')" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="<?= route_to('agile.sistemas.salvar') ?>" method="post">
                    <input type="hidden" name="id" id="sis-id" value="">

                    <div class="mb-3">
                        <label for="sis-sigla" class="form-label">Sigla do Sistema</label>
                        <input type="text" class="form-control" id="sis-sigla" name="sigla" placeholder="Ex: SIGAP" required>
                    </div>

                    <div class="mb-3">
                        <label for="sis-nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="sis-nome" name="nome" placeholder="Ex: Sistema de Gestão de Demandas" required>
                    </div>

                    <div class="mb-3">
                        <label for="sis-desc" class="form-label">Descrição</label>
                        <textarea class="form-control" id="sis-desc" name="descricao" rows="3" placeholder="Descreva brevemente a finalidade do sistema..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" onclick="$('#sistemaModal').modal('hide')">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#sistemas-table').DataTable({
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

function novoSistema() {
    document.getElementById('sis-id').value = '';
    document.getElementById('sis-sigla').value = '';
    document.getElementById('sis-nome').value = '';
    document.getElementById('sis-desc').value = '';
    const modal = new bootstrap.Modal(document.getElementById('sistemaModal'));
    modal.show();
}

function editSistema(sis) {
    document.getElementById('sis-id').value = sis.id;
    document.getElementById('sis-sigla').value = sis.sigla;
    document.getElementById('sis-nome').value = sis.nome;
    document.getElementById('sis-desc').value = sis.descricao || '';
    const modal = new bootstrap.Modal(document.getElementById('sistemaModal'));
    modal.show();
}

function confirmDelete(id, sigla) {
    if (confirm("Você tem certeza que deseja deletar o sistema: " + sigla + "? Todas as demandas associadas terão a referência zerada.")) {
        $.ajax({
            url: '<?= base_url('agile/sistemas/deletar') ?>/' + id,
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
