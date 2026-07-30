<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';

// Filtra itens por coluna
$colunas = [
    'A Fazer' => [],
    'Em Desenvolvimento' => [],
    'Teste/QA' => [],
    'Impedimento' => [],
    'Pronto' => []
];

foreach ($items as $item) {
    $col = $item->status_kanban ?: 'A Fazer';
    if (isset($colunas[$col])) {
        $colunas[$col][] = $item;
    } else {
        $colunas['A Fazer'][] = $item;
    }
}
?>
<!-- Carrega biblioteca SortableJS para drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div id="content">        
    <div class="container my-4">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="<?= route_to('agile.demandas') ?>" class="btn btn-outline-secondary btn-sm mb-2"><i class="fas fa-arrow-left"></i> Voltar para Demandas</a>
                <h4>Quadro Kanban - <?= htmlspecialchars($demanda->titulo) ?></h4>
                <p class="text-muted mb-0">Rastreamento do fluxo da demanda.</p>
            </div>
            <div>
                <span class="badge bg-secondary p-2 mb-2">Fase Atual: <?= htmlspecialchars($demanda->status) ?></span>
                <br>
                <a href="<?= route_to('agile.backlog', $demanda->id) ?>" class="btn btn-outline-dark"><i class="fas fa-list-ol"></i> Refinar Backlog</a>
            </div>
        </div>

        <!-- Mensagens de Alerta -->
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

        <!-- PAINÉIS DE INTERAÇÃO CONFORME O STATUS ATUAL -->
        <div class="row mb-4">
            <div class="col-12">
                
                <!-- Fase: Triagem, Preparar SERPRO ou Alocar Fábrica (Ainda não em backlog completo) -->
                <?php if (in_array($demanda->status, ['Triagem', 'Preparar Demanda SERPRO', 'Alocar Time Fábricas', 'Refinamento Backlog', 'Sprint Planning', 'Em Execução']) && !$sprintAtiva): ?>
                    <div class="card border-warning shadow-sm">
                        <div class="card-body">
                            <h5 class="text-warning-emphasis"><i class="fas fa-play-circle"></i> Iniciar Planejamento da Sprint</h5>
                            <p class="mb-3">Para iniciar o Ciclo da Sprint (Kanban), é obrigatório ter realizado uma cerimônia de <strong>Sprint Planning</strong> com ata registrada e presença dos participantes.</p>
                            
                            <!-- Formulário de Inicialização da Sprint -->
                            <form action="<?= route_to('agile.sprint.salvar') ?>" method="post" class="row g-3 align-items-end">
                                <input type="hidden" name="id_demanda" value="<?= $demanda->id ?>">
                                <div class="col-md-4">
                                    <label class="form-label">Meta da Sprint</label>
                                    <input type="text" name="meta" class="form-control" placeholder="Ex: Entregar telas de cadastro e validação" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data de Início</label>
                                    <input type="date" name="data_inicio" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Data de Fim</label>
                                    <input type="date" name="data_fim" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-warning w-100"><i class="fas fa-play"></i> Iniciar Sprint</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fase: Em Execução com Sprint Ativa -->
                <?php if ($sprintAtiva && $demanda->status === 'Em Execução'): ?>
                    <div class="card border-primary shadow-sm">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="text-primary-emphasis"><i class="fas fa-running"></i> Sprint Ativa em Execução</h5>
                                <p class="mb-0"><strong>Meta:</strong> <?= htmlspecialchars($sprintAtiva->meta) ?> | <strong>Período:</strong> <?= date('d/m/Y', strtotime($sprintAtiva->data_inicio)) ?> até <?= date('d/m/Y', strtotime($sprintAtiva->data_fim)) ?></p>
                            </div>
                            <div>
                                <form action="<?= route_to('agile.sprint.review') ?>" method="post" onsubmit="return confirm('Deseja realmente encerrar esta Sprint e avançar para Homologação?')">
                                    <input type="hidden" name="id_sprint" value="<?= $sprintAtiva->id ?>">
                                    <input type="hidden" name="id_demanda" value="<?= $demanda->id ?>">
                                    <button type="submit" class="btn btn-outline-danger"><i class="fas fa-stop"></i> Finalizar Sprint (Review)</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fase: Homologação do Produto pelo PO -->
                <?php if ($demanda->status === 'Homologação'): ?>
                    <div class="card border-warning shadow-sm">
                        <div class="card-body">
                            <h5 class="text-warning-emphasis"><i class="fas fa-check-double"></i> Homologação do Produto (Product Owner)</h5>
                            <p class="mb-3">Analise o incremento da Sprint. Se aceito, a demanda seguirá para a liberação da release. Caso rejeitado, retornará para execução com as tarefas resetadas.</p>
                            
                            <form action="<?= route_to('agile.demanda.homologar') ?>" method="post" class="row g-3">
                                <input type="hidden" name="id_demanda" value="<?= $demanda->id ?>">
                                <div class="col-md-3">
                                    <label class="form-label">Parecer de Homologação</label>
                                    <select class="form-select" name="parecer" required>
                                        <option value="">Selecione...</option>
                                        <option value="Favorável">Favorável (Aprovado)</option>
                                        <option value="Rejeitado">Rejeitado (Retornar)</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Observações e Feedback</label>
                                    <input type="text" name="observacoes" class="form-control" placeholder="Escreva considerações ou motivos de rejeição..." required>
                                </div>
                                <div class="col-md-2 align-self-end">
                                    <button type="submit" class="btn btn-warning w-100"><i class="fas fa-paper-plane"></i> Enviar Parecer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fase: Submissão de Release para Produção -->
                <?php if ($demanda->status === 'Submissão Release'): ?>
                    <div class="card border-info shadow-sm">
                        <div class="card-body">
                            <h5 class="text-info-emphasis"><i class="fas fa-upload"></i> Submissão de Release para Produção (Servidor)</h5>
                            <p class="mb-3">Preencha os metadados do deploy. A liberação de release exige homologação de parecer favorável registrado previamente.</p>
                            
                            <form action="<?= route_to('agile.demanda.release') ?>" method="post" class="row g-3">
                                <input type="hidden" name="id_demanda" value="<?= $demanda->id ?>">
                                <div class="col-md-3">
                                    <label class="form-label">Ticket RDM Cistmart</label>
                                    <input type="text" name="ticket_rdm" class="form-control" placeholder="Ex: RDM-2026-987" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Servidor Destino</label>
                                    <input type="text" name="servidor_deploy" class="form-control" placeholder="Ex: SERPRO-PRD-01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Janela de Homologação</label>
                                    <input type="text" name="janela_homologacao" class="form-control" placeholder="Ex: Sábado, das 00:00 às 04:00" required>
                                </div>
                                <div class="col-md-2 align-self-end">
                                    <button type="submit" class="btn btn-info text-white w-100"><i class="fas fa-rocket"></i> Submeter Release</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fase: Comitê de Mudanças (CCM) - Apenas Fluxos Comuns -->
                <?php if ($demanda->status === 'CCM'): ?>
                    <div class="card border-success shadow-sm">
                        <div class="card-body">
                            <h5 class="text-success-emphasis"><i class="fas fa-users-cog"></i> Avaliação do Comitê de Mudanças (CCM)</h5>
                            <p class="mb-3">A release comum foi submetida. Realize a avaliação técnica final para autorizar a atualização no ambiente de produção.</p>
                            
                            <div class="d-flex gap-2">
                                <form action="<?= route_to('agile.demanda.update') ?>" method="post">
                                    <input type="hidden" name="id" value="<?= $demanda->id ?>">
                                    <input type="hidden" name="titulo" value="<?= htmlspecialchars($demanda->titulo) ?>">
                                    <input type="hidden" name="descricao" value="<?= htmlspecialchars($demanda->descricao) ?>">
                                    <input type="hidden" name="sistema_critico" value="<?= $demanda->sistema_critico ?>">
                                    <input type="hidden" name="status" value="Atualizado Produção">
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Autorizar Implantação em Produção</button>
                                </form>
                                <form action="<?= route_to('agile.demanda.update') ?>" method="post">
                                    <input type="hidden" name="id" value="<?= $demanda->id ?>">
                                    <input type="hidden" name="titulo" value="<?= htmlspecialchars($demanda->titulo) ?>">
                                    <input type="hidden" name="descricao" value="<?= htmlspecialchars($demanda->descricao) ?>">
                                    <input type="hidden" name="sistema_critico" value="<?= $demanda->sistema_critico ?>">
                                    <input type="hidden" name="status" value="Em Execução">
                                    <button type="submit" class="btn btn-outline-danger"><i class="fas fa-times"></i> Rejeitar e Retornar ao Ciclo</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fase: Esteira SERPRO - Apenas Fluxos Críticos -->
                <?php if ($demanda->status === 'SERPRO'): ?>
                    <div class="card border-danger shadow-sm">
                        <div class="card-body">
                            <h5 class="text-danger-emphasis"><i class="fas fa-shield-alt"></i> Homologação de Prontidão e Segurança (Esteira SERPRO)</h5>
                            <p class="mb-3">A release de sistema crítico foi submetida. Avalie os testes automatizados e o plano de rollback na esteira ALM.</p>
                            
                            <form action="<?= route_to('agile.demanda.update') ?>" method="post">
                                <input type="hidden" name="id" value="<?= $demanda->id ?>">
                                <input type="hidden" name="titulo" value="<?= htmlspecialchars($demanda->titulo) ?>">
                                <input type="hidden" name="descricao" value="<?= htmlspecialchars($demanda->descricao) ?>">
                                <input type="hidden" name="sistema_critico" value="<?= $demanda->sistema_critico ?>">
                                <input type="hidden" name="status" value="Atualizado Produção (Esteira SERPRO)">
                                <button type="submit" class="btn btn-danger"><i class="fas fa-check-double"></i> Liberar na Esteira SERPRO</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Fase Final: Concluído / Atualizado em Produção -->
                <?php if (str_contains($demanda->status, 'Atualizado Produção')): ?>
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <h5><i class="fas fa-check-circle"></i> Demanda Concluída e Atualizada em Produção</h5>
                            <p class="mb-0">Todos os ciclos ágeis, ritos, homologações e janelas de deploy foram concluídos com sucesso para esta demanda!</p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- QUADRO KANBAN OPERACIONAL (Visível apenas se no status de Execução ou posterior) -->
        <div class="row g-3">
            <?php foreach ($colunas as $colName => $colItems): ?>
                <div class="col-md-2-4 col-sm-6">
                    <div class="card bg-light border-0 shadow-sm h-100">
                        <div class="card-header bg-dark-subtle d-flex justify-content-between align-items-center py-2">
                            <strong class="text-dark-emphasis"><?= $colName ?></strong>
                            <span class="badge bg-secondary rounded-pill"><?= count($colItems) ?></span>
                        </div>
                        <div class="card-body p-2 kanban-column" data-status="<?= $colName ?>" style="min-height: 400px; max-height: 600px; overflow-y: auto;">
                            <?php foreach ($colItems as $item): ?>
                                <div class="card border-0 shadow-sm mb-2 kanban-item-card" data-id="<?= $item->id ?>" style="cursor: grab;">
                                    <div class="card-body p-3">
                                        <h6 class="card-title text-dark mb-1 small"><?= htmlspecialchars($item->titulo) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <span class="badge bg-info text-dark small" style="font-size: 0.75em;"><?= $item->pontuacao ?> SP</span>
                                            <small class="text-muted" style="font-size: 0.75em;"><i class="fas fa-grip-lines"></i></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<style>
/* CSS Grid especial para 5 colunas no Bootstrap 5 */
@media (min-width: 768px) {
    .col-md-2-4 {
        flex: 0 0 20%;
        max-width: 20%;
    }
}
.kanban-column.drag-over {
    background-color: rgba(0,0,0,0.05);
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const columns = document.querySelectorAll('.kanban-column');
    
    columns.forEach(col => {
        Sortable.create(col, {
            group: 'kanban-board',
            animation: 150,
            ghostClass: 'bg-info-subtle',
            onStart: function(evt) {
                // Altera cursor ao arrastar
                evt.item.style.cursor = 'grabbing';
            },
            onEnd: function(evt) {
                evt.item.style.cursor = 'grab';
                
                const itemId = evt.item.getAttribute('data-id');
                const targetStatus = evt.to.getAttribute('data-status');
                
                // Dispara atualização de status via POST AJAX
                $.ajax({
                    url: '<?= route_to('agile.kanban.update_status') ?>',
                    type: 'POST',
                    data: {
                        id: itemId,
                        status: targetStatus
                    },
                    success: function(res) {
                        if (res.status !== 'success') {
                            alert('Erro ao mover item no Kanban.');
                            window.location.reload();
                        }
                    },
                    error: function() {
                        alert('Falha de conexão ao mover o item.');
                        window.location.reload();
                    }
                });
            }
        });
    });
});
</script>

<?php
require VIEWPATH.'/footer.php';
?>
