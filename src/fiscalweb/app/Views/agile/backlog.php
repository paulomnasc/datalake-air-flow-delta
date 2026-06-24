<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>
<!-- Carrega biblioteca SortableJS para drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div id="content">        
    <div class="container my-4">
        <!-- Navegação de volta e resumo da demanda -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="<?= route_to('agile.demandas') ?>" class="btn btn-outline-secondary btn-sm mb-2"><i class="fas fa-arrow-left"></i> Voltar para Demandas</a>
                <h4>Backlog do Produto - <?= htmlspecialchars($demanda->titulo) ?></h4>
                <p class="text-muted mb-0"><?= htmlspecialchars($demanda->descricao) ?></p>
            </div>
            <div>
                <span class="badge bg-secondary p-2 mb-2">Fase Atual: <?= htmlspecialchars($demanda->status) ?></span>
                <br>
                <a href="<?= route_to('agile.kanban', $demanda->id) ?>" class="btn btn-primary"><i class="fas fa-columns"></i> Ir para o Kanban</a>
            </div>
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

        <div class="row">
            <!-- Coluna da Esquerda: Itens do Backlog -->
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 card-title"><i class="fas fa-stream"></i> Histórias de Usuário / Requisitos</h5>
                        <small class="text-white-50"><i class="fas fa-arrows-alt-v"></i> Arraste e solte para reordenar</small>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="backlog-list">
                            <?php if (empty($items)): ?>
                                <li class="list-group-item text-center py-5 text-muted" id="no-items-placeholder">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-white-50"></i>
                                    <p class="mb-0">Nenhum item cadastrado no Backlog. Comece adicionando um item ao lado!</p>
                                </li>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-3 item-backlog-row" data-id="<?= $item->id ?>">
                                        <div class="d-flex align-items-center">
                                            <!-- Handle de arrastar -->
                                            <div class="drag-handle me-3 text-muted" style="cursor: grab;">
                                                <i class="fas fa-grip-vertical fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 text-dark"><?= htmlspecialchars($item->titulo) ?></h6>
                                                <div class="text-muted small">
                                                    <strong>Critérios de Aceite:</strong> <?= htmlspecialchars($item->criterios_aceite ?? 'Nenhum definido.') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge bg-info text-dark font-monospace fs-6" title="Story Points">
                                                <?= $item->pontuacao ?> SP
                                            </span>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-secondary btn-sm" onclick="editItem(<?= $item->id ?>, '<?= htmlspecialchars($item->titulo, ENT_QUOTES) ?>', '<?= htmlspecialchars($item->criterios_aceite ?? '', ENT_QUOTES) ?>', <?= $item->pontuacao ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteItem(<?= $item->id ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Coluna da Direita: Adicionar/Editar Item e Cerimônias Rápidas -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0 card-title" id="form-title"><i class="fas fa-plus"></i> Adicionar Item</h5>
                    </div>
                    <div class="card-body p-4">
                        <form id="backlog-form" action="<?= route_to('agile.backlog.salvar_item') ?>" method="post">
                            <input type="hidden" name="id" id="item-id" value="">
                            <input type="hidden" name="id_demanda" value="<?= $demanda->id ?>">

                            <div class="mb-3">
                                <label for="item-titulo" class="form-label">Título da História / Requisito</label>
                                <input type="text" class="form-control" id="item-titulo" name="titulo" placeholder="Ex: Como usuário, quero redefinir senha..." required>
                            </div>

                            <div class="mb-3">
                                <label for="item-criterios" class="form-label">Critérios de Aceite</label>
                                <textarea class="form-control" id="item-criterios" name="criterios_aceite" rows="3" placeholder="Ex: Enviar link por email com validade de 24h..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="item-pontuacao" class="form-label">Pontuação (Story Points)</label>
                                <input type="number" class="form-control" id="item-pontuacao" name="pontuacao" min="0" placeholder="Ex: 5" value="0">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="btn-submit">Salvar Item</button>
                                <button type="button" class="btn btn-light" id="btn-cancel" style="display:none;" onclick="cancelEdit()">Cancelar Edição</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Histórico de Cerimônias e Ritos da Demanda -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-secondary text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 card-title"><i class="fas fa-history"></i> Cerimônias e Ritos</h5>
                        <button class="btn btn-light btn-sm" onclick="novaCerimonia()"><i class="fas fa-calendar-plus"></i> Agendar</button>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (empty($cerimonias)): ?>
                                <li class="list-group-item text-center py-4 text-muted">
                                    Nenhuma cerimônia registrada para esta demanda.
                                </li>
                            <?php else: ?>
                                <?php foreach ($cerimonias as $c): ?>
                                    <li class="list-group-item py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= htmlspecialchars($c->tipo_cerimonia) ?></strong>
                                                <div class="small text-muted">Agendado: <?= date('d/m/Y H:i', strtotime($c->data_hora_agendada)) ?></div>
                                                <?php if ($c->data_hora_realizada): ?>
                                                    <div class="small text-success"><i class="fas fa-check-circle"></i> Realizado: <?= date('d/m/Y H:i', strtotime($c->data_hora_realizada)) ?></div>
                                                <?php else: ?>
                                                    <div class="small text-warning"><i class="fas fa-clock"></i> Pendente de ata</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-secondary btn-sm" onclick="editarCerimonia(<?= htmlspecialchars(json_encode($c)) ?>)" title="Editar/Registrar Ata"><i class="fas fa-signature"></i></button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deletarCerimonia(<?= $c->id ?>)" title="Excluir Cerimônia"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Registro de Cerimônias -->
<div class="modal fade" id="cerimoniaModal" tabindex="-1" aria-labelledby="cerimoniaModalLabel" aria-hidden="true">
    <style>
        .mb-3-cerimonia {
            margin-bottom: 1rem !important;
            display: block !important;
        }
    </style>
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="cerimoniaModalLabel"><i class="fas fa-calendar-check"></i> Agendamento e Registro de Cerimônia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="<?= route_to('agile.cerimonia.salvar') ?>" method="post">
                    <input type="hidden" name="id" id="cer-id" value="">
                    <input type="hidden" name="id_demanda" value="<?= $demanda->id ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3-cerimonia">
                            <label for="tipo_cerimonia" class="form-label">Tipo de Cerimônia</label>
                            <select class="form-select" id="cer-tipo" name="tipo_cerimonia" required>
                                <option value="">Selecione...</option>
                                <option value="Kick-Off">Kick-Off</option>
                                <option value="Refinamento">Refinamento</option>
                                <option value="Sprint Planning">Sprint Planning</option>
                                <option value="Daily">Daily</option>
                                <option value="Sprint Review">Sprint Review</option>
                                <option value="Retrospectiva">Retrospectiva</option>
                                <option value="Homologação">Homologação</option>
                                <option value="Reunião Alinhamento CCM">Reunião Alinhamento CCM</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3-cerimonia">
                            <label for="data_hora_agendada" class="form-label">Data/Hora Agendada</label>
                            <input type="datetime-local" class="form-control" id="cer-agendada" name="data_hora_agendada" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3-cerimonia">
                            <label for="data_hora_realizada" class="form-label" id="lbl-realizada">Data/Hora Realizada (Apenas se já concluída)</label>
                            <input type="datetime-local" class="form-control" id="cer-realizada" name="data_hora_realizada">
                        </div>
                        <div class="col-md-6 mb-3-cerimonia">
                            <label for="link_gravacao" class="form-label">Link da Gravação (Conferência)</label>
                            <input type="url" class="form-control" id="cer-link" name="link_gravacao" placeholder="Ex: https://teams.microsoft.com/...">
                        </div>
                    </div>

                    <div class="mb-3-cerimonia">
                        <label class="form-label font-weight-bold">Participantes Presentes (Confirmação de Presença)</label>
                        <ul class="list-group" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($usuarios as $u): ?>
                                <li class="list-group-item d-flex align-items-center py-2 px-3" style="gap: 10px !important;">
                                    <input class="check-participante" type="checkbox" name="participantes[]" value="<?= $u->id ?>" id="part-<?= $u->id ?>" style="width: 18px !important; height: 18px !important; min-width: 18px !important; min-height: 18px !important; margin: 0 !important; padding: 0 !important; cursor: pointer !important; -webkit-appearance: checkbox !important; appearance: checkbox !important; display: inline-block !important; visibility: visible !important; opacity: 1 !important; flex-shrink: 0 !important;">
                                    <label class="mb-0 w-100" for="part-<?= $u->id ?>" style="cursor: pointer; user-select: none; font-size: 14px; font-weight: normal; color: #333; display: inline-block !important;">
                                        <?= htmlspecialchars($u->nome) ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="mb-3-cerimonia">
                        <label for="ata_descritiva" class="form-label">Ata Descritiva / Deliberações e Acordos</label>
                        <textarea class="form-control" id="cer-ata" name="ata_descritiva" rows="4" placeholder="Escreva os detalhes e deliberações acordadas..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar Drag and Drop no Backlog
document.addEventListener("DOMContentLoaded", function() {
    const list = document.getElementById('backlog-list');
    if (list && list.children.length > 1) {
        Sortable.create(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function() {
                // Monta array de IDs na nova ordem
                const order = Array.from(list.children).map(row => row.getAttribute('data-id'));
                // AJAX para salvar ordem
                $.ajax({
                    url: '<?= route_to('agile.backlog.salvar_ordem') ?>',
                    type: 'POST',
                    data: { ordem: order },
                    success: function(res) {
                        if (res.status !== 'success') {
                            alert('Erro ao salvar nova ordenação.');
                        }
                    }
                });
            }
        });
    }
});

// Funções do Form do Backlog
function editItem(id, titulo, criterios, pontuacao) {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Editar Item';
    document.getElementById('item-id').value = id;
    document.getElementById('item-titulo').value = titulo;
    document.getElementById('item-criterios').value = criterios;
    document.getElementById('item-pontuacao').value = pontuacao;
    document.getElementById('btn-submit').innerHTML = 'Atualizar Item';
    document.getElementById('btn-cancel').style.display = 'block';
}

function cancelEdit() {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus"></i> Adicionar Item';
    document.getElementById('item-id').value = '';
    document.getElementById('backlog-form').reset();
    document.getElementById('btn-submit').innerHTML = 'Salvar Item';
    document.getElementById('btn-cancel').style.display = 'none';
}

function deleteItem(id) {
    if (confirm('Deseja realmente remover esta história de usuário do backlog?')) {
        $.ajax({
            url: '<?= base_url('agile/backlog/deletar-item') ?>/' + id,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function(res) {
                if (res.status === 'success') {
                    window.location.reload();
                } else {
                    alert('Erro ao excluir item.');
                }
            }
        });
    }
}

// Funções da Cerimônia
function toggleRealizadaRequired() {
    const ataVal = document.getElementById('cer-ata').value.trim();
    const realizadaInput = document.getElementById('cer-realizada');
    const label = document.getElementById('lbl-realizada');
    
    if (ataVal !== '') {
        realizadaInput.setAttribute('required', 'required');
        label.innerHTML = 'Data/Hora Realizada <span class="text-danger">*</span>';
    } else {
        realizadaInput.removeAttribute('required');
        label.innerHTML = 'Data/Hora Realizada (Apenas se já concluída)';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const ataTextarea = document.getElementById('cer-ata');
    if (ataTextarea) {
        ataTextarea.addEventListener('input', toggleRealizadaRequired);
    }
});

function novaCerimonia() {
    document.getElementById('cer-id').value = '';
    document.getElementById('cer-tipo').value = '';
    document.getElementById('cer-agendada').value = '';
    document.getElementById('cer-realizada').value = '';
    document.getElementById('cer-link').value = '';
    document.getElementById('cer-ata').value = '';
    $('.check-participante').prop('checked', false);
    
    toggleRealizadaRequired();
    
    const myModal = new bootstrap.Modal(document.getElementById('cerimoniaModal'));
    myModal.show();
}

function editarCerimonia(c) {
    document.getElementById('cer-id').value = c.id;
    document.getElementById('cer-tipo').value = c.tipo_cerimonia;
    
    // Formata datas
    if (c.data_hora_agendada) {
        document.getElementById('cer-agendada').value = c.data_hora_agendada.replace(' ', 'T').substring(0, 16);
    }
    if (c.data_hora_realizada) {
        document.getElementById('cer-realizada').value = c.data_hora_realizada.replace(' ', 'T').substring(0, 16);
    } else {
        document.getElementById('cer-realizada').value = '';
    }

    document.getElementById('cer-link').value = c.link_gravacao || '';
    document.getElementById('cer-ata').value = c.ata_descritiva || '';

    // Seleciona participantes
    $('.check-participante').prop('checked', false);
    try {
        const parts = JSON.parse(c.participantes_presentes) || [];
        parts.forEach(pid => {
            $('#part-' + pid).prop('checked', true);
        });
    } catch(e) {}

    toggleRealizadaRequired();

    const myModal = new bootstrap.Modal(document.getElementById('cerimoniaModal'));
    myModal.show();
}

function deletarCerimonia(id) {
    if (confirm('Deseja realmente remover esta cerimônia/ata?')) {
        $.ajax({
            url: '<?= base_url('agile/cerimonia/deletar') ?>/' + id,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function(res) {
                if (res.status === 'success') {
                    window.location.reload();
                } else {
                    alert(res.mensagem || 'Erro ao excluir a cerimônia.');
                }
            },
            error: function() {
                alert('Falha de conexão ao excluir a cerimônia.');
            }
        });
    }
}
</script>

<?php
require VIEWPATH.'/footer.php';
?>
