<div class="container-fluid py-4 px-3 px-lg-4" style="background-color: #0b1120; min-height: 100vh; color: #ffffff;">
    <!-- CABEÇALHO DA PÁGINA -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between pb-4 mb-4 border-bottom border-secondary border-opacity-25 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary bg-opacity-20 text-white border border-primary border-opacity-50 px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.78rem;">
                    <i class="bi bi-robot me-1 text-info"></i> Cloudbet Autonomous Robot
                </span>
                <span class="badge bg-success bg-opacity-20 text-white border border-success border-opacity-50 px-2.5 py-1 rounded-pill fw-semibold" style="font-size: 0.78rem;">
                    <i class="bi bi-activity me-1 text-success"></i> Auditoria de Liquidez & Taxa de Hit
                </span>
            </div>
            <h1 class="h3 fw-bold text-white mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
                Taxa de Hit & Auditoria de Liquidez das Ordens
            </h1>
            <p class="text-white text-opacity-75 small mb-0">
                Monitoramento transparente de conversão de compras, bloqueios de mercado (suspensão de handicap pela corretora) e taxa de acerto esportivo (+EV) por esteira.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('observabilidade') ?>" class="btn btn-outline-info btn-sm rounded-pill px-3 d-flex align-items-center gap-1 shadow-sm text-white border-info">
                <i class="bi bi-speedometer2 text-info"></i> Painel Ao Vivo
            </a>
            <a href="<?= base_url('apostas') ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 d-flex align-items-center gap-1 shadow-sm text-white border-light">
                <i class="bi bi-ticket-detailed text-white"></i> Minhas Apostas
            </a>
        </div>
    </div>

    <!-- CARDS DE KPI DE LIQUIDEZ E CONVERSÃO DE ORDENS -->
    <div class="row g-3 mb-4">
        <!-- KPI 1: Hit de Liquidez -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid rgba(16, 185, 129, 0.4) !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-white text-opacity-90 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Hit de Liquidez (Ordens)</span>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(16, 185, 129, 0.25); width: 36px; height: 36px;">
                            <i class="bi bi-check-circle-fill text-success fs-6"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h2 class="h2 fw-bold text-white mb-0"><?= number_format($kpis['taxa_hit_liquidez'], 1, ',', '.') ?>%</h2>
                        <span class="small text-white text-opacity-75">(<?= $kpis['ordens_compradas'] ?> de <?= $kpis['total_tentativas'] ?> ordens)</span>
                    </div>
                    <div class="progress mt-2" style="height: 6px; background-color: rgba(255, 255, 255, 0.15);">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $kpis['taxa_hit_liquidez']) ?>%" aria-valuenow="<?= $kpis['taxa_hit_liquidez'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-white text-opacity-75 mt-2" style="font-size: 0.72rem;">
                        Bilhetes efetivamente aceitos e comprados na corretora.
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Hit Fail por Mercado Suspenso -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid rgba(239, 68, 68, 0.4) !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-white text-opacity-90 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Mercado Fechado / Suspenso</span>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(239, 68, 68, 0.25); width: 36px; height: 36px;">
                            <i class="bi bi-lock-fill text-danger fs-6"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h2 class="h2 fw-bold text-white mb-0"><?= number_format($kpis['taxa_fail_suspensao'], 1, ',', '.') ?>%</h2>
                        <span class="small text-white text-opacity-75">(<?= $kpis['ordens_suspensas'] ?> bloqueios)</span>
                    </div>
                    <div class="progress mt-2" style="height: 6px; background-color: rgba(255, 255, 255, 0.15);">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= min(100, $kpis['taxa_fail_suspensao']) ?>%" aria-valuenow="<?= $kpis['taxa_fail_suspensao'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-white text-opacity-75 mt-2" style="font-size: 0.72rem;">
                        Cloudbet trancou/desativou linhas de Handicap no pré-jogo.
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Hit Fail por Odd Derretida -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid rgba(245, 158, 11, 0.4) !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-white text-opacity-90 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Odd Derretida (&lt; 1.65)</span>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(245, 158, 11, 0.25); width: 36px; height: 36px;">
                            <i class="bi bi-graph-down-arrow text-warning fs-6"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h2 class="h2 fw-bold text-white mb-0"><?= number_format($kpis['taxa_fail_derretida'], 1, ',', '.') ?>%</h2>
                        <span class="small text-white text-opacity-75">(<?= $kpis['ordens_derretidas'] ?> cancelamentos)</span>
                    </div>
                    <div class="progress mt-2" style="height: 6px; background-color: rgba(255, 255, 255, 0.15);">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, $kpis['taxa_fail_derretida']) ?>%" aria-valuenow="<?= $kpis['taxa_fail_derretida'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-white text-opacity-75 mt-2" style="font-size: 0.72rem;">
                        Trava de Slippage barrou ordens sem margem matemática de valor.
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Desempenho Geral Consolidado -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, rgba(56, 189, 248, 0.15) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid rgba(56, 189, 248, 0.4) !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-white text-opacity-90 small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Lucro Líquido & ROI Cloudbet</span>
                        <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(56, 189, 248, 0.25); width: 36px; height: 36px;">
                            <i class="bi bi-cash-coin text-info fs-6"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h2 class="h2 fw-bold text-white mb-0">
                            <?= $kpis['lucro_geral'] >= 0 ? '+' : '' ?><?= number_format($kpis['lucro_geral'], 2, ',', '.') ?> USDT
                        </h2>
                        <span class="badge <?= $kpis['roi_geral'] >= 0 ? 'bg-success' : 'bg-danger' ?> rounded-pill text-white fw-bold" style="font-size: 0.75rem;">
                            <?= $kpis['roi_geral'] >= 0 ? '+' : '' ?><?= number_format($kpis['roi_geral'], 1, ',', '.') ?>% ROI
                        </span>
                    </div>
                    <div class="small text-white text-opacity-75 mt-2" style="font-size: 0.72rem;">
                        Taxa de Acerto Esportivo Geral: <strong class="text-white"><?= number_format($kpis['taxa_acerto_geral'], 1, ',', '.') ?>%</strong> (<?= $kpis['total_apostas'] ?> apostas FT).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEÇÃO 2: DESEMPENHO E HIT RATE POR ESTEIRA OPERACIONAL -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: #0f172a; border: 1px solid rgba(255, 255, 255, 0.12) !important; border-radius: 12px;">
        <div class="card-header bg-transparent py-3 border-bottom border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
            <h5 class="card-title text-white mb-0 d-flex align-items-center gap-2" style="font-size: 1rem;">
                <i class="bi bi-pie-chart-fill text-info"></i>
                Performance Esportiva e Rentabilidade por Esteira (Cloudbet)
            </h5>
            <span class="badge bg-secondary bg-opacity-50 text-white px-2 py-1 rounded-pill" style="font-size: 0.75rem;">
                Liquidações FT Oficiais
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle" style="background-color: transparent;">
                    <thead>
                        <tr class="text-white text-opacity-90 small text-uppercase" style="border-bottom: 1px solid rgba(255, 255, 255, 0.15); font-size: 0.75rem;">
                            <th class="ps-3 py-3 text-white">Esteira / Mercado</th>
                            <th class="text-center py-3 text-white">Apostas FT</th>
                            <th class="text-center py-3 text-white">Hits (Green)</th>
                            <th class="text-center py-3 text-white">Fails (Red)</th>
                            <th class="text-center py-3 text-white">Push / Reemb.</th>
                            <th class="text-center py-3 text-white">Taxa de Hit</th>
                            <th class="text-end py-3 text-white">Apostado</th>
                            <th class="text-end py-3 text-white">Retorno</th>
                            <th class="text-end py-3 text-white">Lucro Líquido</th>
                            <th class="text-center pe-3 py-3 text-white">ROI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($esteiras as $est): ?>
                            <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                                <td class="ps-3 py-3 fw-bold text-white">
                                    <?php if (str_contains($est['nome'], 'Handicap')): ?>
                                        <i class="bi bi-shield-check text-success me-1"></i>
                                    <?php elseif (str_contains($est['nome'], 'Gol')): ?>
                                        <i class="bi bi-futbol text-info me-1"></i>
                                    <?php else: ?>
                                        <i class="bi bi-square-fill text-warning me-1"></i>
                                    <?php endif; ?>
                                    <span class="text-white"><?= esc($est['nome']) ?></span>
                                </td>
                                <td class="text-center fw-bold text-white"><?= $est['finalizadas'] ?></td>
                                <td class="text-center text-success fw-bold"><?= $est['green'] ?></td>
                                <td class="text-center text-danger fw-bold"><?= $est['red'] ?></td>
                                <td class="text-center text-white text-opacity-75"><?= $est['push'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $est['hit_rate'] >= 60.0 ? 'bg-success' : ($est['hit_rate'] >= 50.0 ? 'bg-primary' : 'bg-warning text-dark') ?> rounded-pill px-2.5 py-1 text-white fw-bold">
                                        <?= number_format($est['hit_rate'], 1, ',', '.') ?>%
                                    </span>
                                </td>
                                <td class="text-end text-white text-opacity-90"><?= number_format($est['apostado'], 2, ',', '.') ?> USDT</td>
                                <td class="text-end text-white fw-semibold"><?= number_format($est['retorno'], 2, ',', '.') ?> USDT</td>
                                <td class="text-end fw-bold <?= $est['lucro'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $est['lucro'] >= 0 ? '+' : '' ?><?= number_format($est['lucro'], 2, ',', '.') ?> USDT
                                </td>
                                <td class="text-center pe-3">
                                    <span class="badge <?= $est['roi'] >= 0 ? 'bg-success' : 'bg-danger' ?> rounded-pill px-2.5 py-1 fw-bold text-white">
                                        <?= $est['roi'] >= 0 ? '+' : '' ?><?= number_format($est['roi'], 1, ',', '.') ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SEÇÃO 3: TABELA DE AUDITORIA DE EVENTOS E BLOQUEIOS DE LIQUIDEZ (LOGS) -->
    <div class="card border-0 shadow-sm" style="background-color: #0f172a; border: 1px solid rgba(255, 255, 255, 0.12) !important; border-radius: 12px;">
        <div class="card-header bg-transparent py-3 border-bottom border-secondary border-opacity-25 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
            <div>
                <h5 class="card-title text-white mb-0 d-flex align-items-center gap-2" style="font-size: 1rem;">
                    <i class="bi bi-clock-history text-warning"></i>
                    Auditoria em Tempo Real: Eventos de Execução, Bloqueios e Fila In-Play
                </h5>
                <small class="text-white text-opacity-75">Rastreamento das últimas tentativas de envio para a Cloudbet capturadas pelo pipeline.</small>
            </div>
            <span class="badge bg-primary bg-opacity-30 text-white px-3 py-1.5 rounded-pill border border-primary border-opacity-50 fw-semibold" style="font-size: 0.75rem;">
                Últimos <?= count($logs_detalhe) ?> Eventos
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-dark table-hover mb-0 align-middle" style="background-color: transparent;">
                    <thead class="sticky-top" style="background-color: #0f172a; z-index: 10;">
                        <tr class="text-white text-opacity-90 small text-uppercase" style="border-bottom: 1px solid rgba(255, 255, 255, 0.15); font-size: 0.72rem;">
                            <th class="ps-3 py-2.5 text-white">Data/Hora</th>
                            <th class="py-2.5 text-white">Partida & Competição</th>
                            <th class="py-2.5 text-white">Mercado / Palpite</th>
                            <th class="text-center py-2.5 text-white">Momento</th>
                            <th class="text-center py-2.5 text-white">Status da Ordem</th>
                            <th class="pe-3 py-2.5 text-white" style="min-width: 280px;">Diagnóstico do Robô</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs_detalhe)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-white text-opacity-75 py-4">Nenhum evento de liquidez registrado até o momento.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs_detalhe as $log): ?>
                                <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 0.85rem;">
                                    <td class="ps-3 py-2.5 text-white text-opacity-80 text-nowrap" style="font-size: 0.78rem;">
                                        <?= date('d/m H:i:s', strtotime($log['criado_em'])) ?>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-bold text-white"><?= esc($log['home_team'] ?? 'Fixture #' . $log['fixture_id']) ?> x <?= esc($log['away_team'] ?? '') ?></div>
                                        <div class="text-white text-opacity-75" style="font-size: 0.72rem;"><?= esc($log['league_name'] ?? 'Competição N/D') ?></div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-semibold text-info"><?= esc($log['palpite'] ?? 'Ordem de Cobertura') ?></div>
                                        <?php if (!empty($log['odd'])): ?>
                                            <div class="text-white text-opacity-75" style="font-size: 0.72rem;">Odd: @ <?= number_format((float)$log['odd'], 2, ',', '.') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center py-2.5 text-nowrap">
                                        <?php if (!empty($log['minuto_jogo'])): ?>
                                            <span class="badge bg-secondary bg-opacity-50 text-white px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                                                <?= $log['minuto_jogo'] ?>' <?= !empty($log['placar_momento']) ? '(' . $log['placar_momento'] . ')' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-50 text-white px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Pré-Jogo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center py-2.5 text-nowrap">
                                        <?php if (in_array($log['tipo_evento'], ['APOSTA_CRIADA', 'HEDGE_ORDEM_REAL_EXECUTADA', 'ORDEM_CLOUDBET_RESET_SUMULA'])): ?>
                                            <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 px-2.5 py-1 rounded-pill fw-bold" style="font-size: 0.75rem;">
                                                <i class="bi bi-check-circle-fill text-success me-1"></i> Comprada (Hit)
                                            </span>
                                        <?php elseif (in_array($log['tipo_evento'], ['HEDGE_ABORTADO_SEM_LIQUIDEZ', 'HEDGE_PREJOGO_ABORTADO_SEM_MERCADO', 'HEDGE_ABORTADO_MERCADO_SUSPENSO'])): ?>
                                            <span class="badge bg-danger bg-opacity-25 text-white border border-danger border-opacity-50 px-2.5 py-1 rounded-pill fw-bold" style="font-size: 0.75rem;">
                                                <i class="bi bi-lock-fill text-danger me-1"></i> Linha Fechada
                                            </span>
                                        <?php elseif ($log['tipo_evento'] === 'HEDGE_ABORTADO_ODD_DERRETIDA'): ?>
                                            <span class="badge bg-warning bg-opacity-25 text-white border border-warning border-opacity-50 px-2.5 py-1 rounded-pill fw-bold" style="font-size: 0.75rem;">
                                                <i class="bi bi-graph-down-arrow text-warning me-1"></i> Odd Derretida
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-info bg-opacity-25 text-white border border-info border-opacity-50 px-2.5 py-1 rounded-pill fw-bold" style="font-size: 0.75rem;">
                                                <i class="bi bi-hourglass-split text-info me-1"></i> Fila In-Play
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3 py-2.5 text-white text-opacity-90 small" style="font-size: 0.78rem;">
                                        <?= esc($log['motivo']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
