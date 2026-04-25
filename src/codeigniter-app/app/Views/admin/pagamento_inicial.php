<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

<div id="content">
    <div class="container">
        <h4 style="text-align: center;">Autorizar Pagamento Inicial</h4>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success mt-3 text-center">
                <?= session()->getFlashdata('success'); ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger mt-3 text-center">
                <?= session()->getFlashdata('error'); ?>
            </div>
        <?php endif; ?>
        <input type="text" id="filtro-nome" placeholder="Filtrar por nome ou email" class="form-control mb-3" style="max-width:300px;">
        <table class="data-table table table-bordered" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Data de Registro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= $usuario->id ?></td>
                    <td><?= $usuario->nome ?></td>
                    <td><?= $usuario->email ?></td>
                    <td><?= $usuario->criado_em ?? '-' ?></td>
                    <td>
                        <form method="post" action="<?= base_url('admin/pagamento-inicial/autorizar/'.$usuario->id) ?>">
                            <button type="submit" class="btn btn-success">Autorizar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <script>
            $(document).ready(function() {
                var table = $('#data-table').DataTable({
                    dom: 'lrtip',
                    language: {
                        "sEmptyTable": "Nenhum registro encontrado",
                        "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
                        "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
                        "sInfoFiltered": "(Filtrados de _MAX_ registros)",
                        "sLengthMenu": "_MENU_ resultados por página",
                        "sLoadingRecords": "Carregando...",
                        "sProcessing": "Processando...",
                        "sZeroRecords": "Nenhum registro encontrado",
                        "sSearch": "Pesquisar",
                        "oPaginate": {
                            "sNext": "Próximo",
                            "sPrevious": "Anterior",
                            "sFirst": "Primeiro",
                            "sLast": "Último"
                        }
                    }
                });
                $('#filtro-nome').on('keyup', function() {
                    table.search(this.value).draw();
                });
            });
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
