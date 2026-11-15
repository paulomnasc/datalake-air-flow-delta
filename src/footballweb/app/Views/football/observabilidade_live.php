<?= $this->include('header') ?>

<div class="container-fluid px-3 px-lg-4 py-3" style="background-color: #0b1120; min-height: 92vh; color: #e2e8f0;">

    <!-- TOPO: TÍTULO, SUBTÍTULO E CONTROLE DE ATUALIZAÇÃO -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 pb-3 mb-3 border-bottom border-secondary border-opacity-25">
        <div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-info fs-3"></i>
                <h1 class="h4 mb-0 fw-bold text-white d-flex align-items-center gap-2">
                    Observabilidade Ao Vivo
                    <span class="badge bg-danger rounded-pill pulse-live-indicator" style="font-size: 0.65rem; padding: 4px 8px; letter-spacing: 0.5px;">PLAY IN LIVE</span>
                </h1>
            </div>
            <p class="text-white small mb-0 mt-1">
                Monitoramento tático em tempo real com indicador velocímetro e detecção instantânea do momento ideal de Cash Out.
            </p>
        </div>

        <div class="d-flex align-items-center gap-3 w-100 w-md-auto justify-content-between justify-content-md-end">
            <!-- TIMER DE 3 MINUTOS -->
            <div class="bg-dark bg-opacity-75 px-3 py-1.5 rounded-3 border border-secondary border-opacity-50 text-end" style="min-width: 190px;">
                <div class="d-flex justify-content-between align-items-center small text-white mb-1">
                    <span><i class="bi bi-clock-history text-info me-1"></i>Varredura:</span>
                    <strong class="text-white font-monospace" id="countdown-text">03:00</strong>
                </div>
                <div class="progress" style="height: 4px; background: rgba(255,255,255,0.1);">
                    <div id="countdown-bar" class="progress-bar bg-info" role="progressbar" style="width: 100%; transition: width 1s linear;"></div>
                </div>
            </div>

            <button id="btn-refresh-manual" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1.5 px-3 py-2 fw-semibold" style="border-radius: 8px;">
                <i class="bi bi-arrow-repeat" id="icon-refresh"></i>
                <span>Atualizar Agora</span>
            </button>
        </div>
    </div>

    <!-- BARRA DE GESTÃO DO CICLO DE BANCA (MARCO ZERO: 09/11/2026 21:09 • $15.67 USDT) -->
    <?php if (!empty($metricasCiclo) && !empty($metricasCiclo['has_ciclo'])): ?>
    <div class="p-3 rounded-3 mb-3 border border-info border-opacity-30" style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(30, 41, 59, 0.8) 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-info bg-opacity-20 text-info" style="width: 44px; height: 44px; font-size: 1.3rem;">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <strong class="text-white font-monospace fs-6"><?= htmlspecialchars($metricasCiclo['nome_ciclo']) ?></strong>
                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 font-monospace" style="font-size: 0.68rem;">ATIVO</span>
                    </div>
                    <span class="text-white-50 small d-block font-monospace" style="font-size: 0.75rem;">
                        Ponto de Corte: <span class="text-white"><?= $metricasCiclo['data_inicio_fmt'] ?></span> • Aporte Base: <strong class="text-info"><?= $metricasCiclo['deposito_inicial_fmt'] ?> <?= $metricasCiclo['moeda'] ?></strong>
                    </span>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="text-end">
                    <span class="text-white-50 small d-block font-monospace" style="font-size: 0.72rem;">SALDO DISPONÍVEL</span>
                    <strong class="text-white fs-5 font-monospace" id="ciclo-saldo-disp"><?= $metricasCiclo['saldo_disponivel_fmt'] ?></strong>
                    <span class="text-info small font-monospace"><?= $metricasCiclo['moeda'] ?></span>
                </div>

                <div class="text-end border-start border-secondary border-opacity-25 ps-3">
                    <span class="text-white-50 small d-block font-monospace" style="font-size: 0.72rem;">EM CAMPO (AO VIVO)</span>
                    <strong class="text-warning fs-5 font-monospace" id="ciclo-capital-campo"><?= $metricasCiclo['capital_em_campo_fmt'] ?></strong>
                    <span class="text-warning small font-monospace"><?= $metricasCiclo['moeda'] ?></span>
                </div>

                <div class="text-end border-start border-secondary border-opacity-25 ps-3">
                    <span class="text-white-50 small d-block font-monospace" style="font-size: 0.72rem;">PATRIMÔNIO TOTAL</span>
                    <strong class="text-success fs-5 font-monospace" id="ciclo-patrimonio-total"><?= $metricasCiclo['patrimonio_total_fmt'] ?></strong>
                    <span class="text-success small font-monospace"><?= $metricasCiclo['moeda'] ?></span>
                </div>

                <div class="text-end border-start border-secondary border-opacity-25 ps-3">
                    <span class="text-white-50 small d-block font-monospace" style="font-size: 0.72rem;">EVOLUÇÃO DO CICLO</span>
                    <span class="badge <?= $metricasCiclo['crescimento_banca_pct'] >= 0 ? 'bg-success' : 'bg-danger' ?> fs-6 font-monospace px-2.5 py-1" id="ciclo-crescimento-badge">
                        <?= ($metricasCiclo['crescimento_banca_pct'] >= 0 ? '+' : '') . number_format($metricasCiclo['crescimento_banca_pct'], 1) ?>%
                    </span>
                </div>

                <div class="ps-2">
                    <a href="<?= base_url('conta-corrente/extrato?ciclo=1') ?>" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1 font-monospace" style="border-radius: 6px; font-size: 0.78rem;">
                        <i class="bi bi-graph-up"></i> Extrato do Ciclo
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- CARDS DE MÉTRICAS CONSOLIDADAS (KPIs) -->
    <div class="row g-2 mb-4">
        <!-- 1. Apostas em Jogo -->
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 h-100" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(56, 189, 248, 0.25);">
                <span class="text-white small d-block mb-1 font-monospace">BILHETES MONITORADOS</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-4 fw-bold text-white" id="kpi-total-jogos"><?= $totalEmJogo ?></span>
                    <span class="text-info small">em andamento</span>
                </div>
            </div>
        </div>

        <!-- 2. Capital Exposto -->
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 h-100" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(245, 158, 11, 0.25);">
                <span class="text-white small d-block mb-1 font-monospace">CAPITAL EM CAMPO</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-4 fw-bold text-warning" id="kpi-total-stake"><?= number_format($totalStakeExposta, 2) ?></span>
                    <span class="text-warning-50 small"><?= htmlspecialchars($moedaPadrao) ?></span>
                </div>
            </div>
        </div>

        <!-- 3. Retorno Projetado -->
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 h-100" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(16, 185, 129, 0.25);">
                <span class="text-white small d-block mb-1 font-monospace">RETORNO PROJETADO</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-4 fw-bold text-success" id="kpi-total-retorno"><?= number_format($totalRetornoProj, 2) ?></span>
                    <span class="text-success-50 small"><?= htmlspecialchars($moedaPadrao) ?></span>
                </div>
            </div>
        </div>

        <!-- 4. Alertas de Cash Out -->
        <div class="col-6 col-md-3">
            <div class="p-3 rounded-3 h-100 <?= $totalAlertasCashout > 0 ? 'pulse-cashout-box' : '' ?>" style="background: rgba(15, 23, 42, 0.75); border: 1px solid <?= $totalAlertasCashout > 0 ? '#ef4444' : 'rgba(239, 68, 68, 0.25)' ?>;">
                <span class="text-white small d-block mb-1 font-monospace">MOMENTO DO CASHOUT</span>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-4 fw-bold <?= $totalAlertasCashout > 0 ? 'text-danger' : 'text-white' ?>" id="kpi-total-cashout"><?= $totalAlertasCashout ?></span>
                    <span class="small <?= $totalAlertasCashout > 0 ? 'text-danger fw-bold' : 'text-white' ?>">
                        <?= $totalAlertasCashout > 0 ? '⚡ AÇÃO REQUERIDA' : 'nenhum alerta ativo' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ABAS DE FILTRO RÁPIDO -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-light active filter-btn" data-filter="all">
                Todos (<span id="count-all"><?= count($cards) ?></span>)
            </button>
            <button type="button" class="btn btn-outline-danger filter-btn" data-filter="cashout">
                ⚡ Cash Out (<span id="count-cashout"><?= $totalAlertasCashout ?></span>)
            </button>
            <button type="button" class="btn btn-outline-success filter-btn" data-filter="live">
                Ao Vivo (<span id="count-live"><?= count(array_filter($cards, fn($c) => !empty($c['is_live']))) ?></span>)
            </button>
            <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="prejogo">
                Pré-Jogo (<span id="count-prejogo"><?= count(array_filter($cards, fn($c) => empty($c['is_live']))) ?></span>)
            </button>
        </div>

        <div class="small text-white font-monospace d-flex align-items-center gap-2">
            <span>Última sincronização: <span id="last-sync-time" class="text-white"><?= date('H:i:s') ?></span></span>
        </div>
    </div>

    <!-- GRID DE CARDS COM VELOCÍMETROS -->
    <div class="row g-3" id="cards-container">
        <?php if (empty($cards)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 rounded-4 bg-dark bg-opacity-50 border border-secondary border-opacity-25 max-w-lg mx-auto">
                    <i class="bi bi-shield-check text-info display-4 mb-3 d-block"></i>
                    <h4 class="text-white fw-bold">Nenhum bilhete em andamento no momento</h4>
                    <p class="text-white small mb-0">
                        Assim que partidas com apostas ativas iniciarem, os velocímetros de risco e os alertas de Cash Out aparecerão automaticamente aqui.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($cards as $c): ?>
                <?php 
                    $isCashout = !empty($c['is_cashout_moment']);
                    $cardBorderClass = $isCashout ? 'border-cashout-pulse' : ($c['faixa_cor'] === 'vermelho' ? 'border-danger' : ($c['faixa_cor'] === 'amarelo' ? 'border-warning' : 'border-success'));
                    $filterCategory = $isCashout ? 'cashout' : (!empty($c['is_live']) ? 'live' : 'prejogo');
                ?>
                <div class="col-12 col-md-6 col-xl-4 card-item" data-category="<?= $filterCategory ?>" data-card-id="<?= $c['aposta_id'] ?>">
                    <div class="card h-100 bg-dark text-white rounded-3 shadow-sm position-relative overflow-hidden <?= $cardBorderClass ?>" 
                         style="background: linear-gradient(180deg, #131b2e 0%, #0d1322 100%) !important; border-width: 2px !important;">

                        <!-- BANNER HIGHLIGHT SE FOR MOMENTO DO CASHOUT -->
                        <?php if ($isCashout): ?>
                            <div class="bg-danger bg-gradient text-white text-center py-1 px-2 fw-bold small text-uppercase tracking-wider d-flex align-items-center justify-content-center gap-1.5 shadow-sm">
                                <i class="bi bi-lightning-charge-fill animate-bounce"></i>
                                <span>⚡ MOMENTO DO CASHOUT: ENCERRE A APOSTA</span>
                                <i class="bi bi-lightning-charge-fill animate-bounce"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            
                            <!-- CABEÇALHO DO JOGO: LIGA E STATUS TEMPO REAL -->
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary border-opacity-25">
                                <span class="text-white small d-flex align-items-center gap-1 font-monospace" style="font-size: 0.72rem;">
                                    <i class="bi bi-trophy text-info"></i>
                                    <?= htmlspecialchars($c['league_name']) ?>
                                </span>
                                <span class="badge <?= $c['status_badge'] ?> rounded-pill font-monospace" style="font-size: 0.70rem; padding: 4px 8px;">
                                    <?= htmlspecialchars($c['status_label']) ?>
                                </span>
                            </div>

                            <!-- PLACAR PRINCIPAL -->
                            <div class="text-center py-1 mb-2">
                                <div class="row align-items-center g-0">
                                    <div class="col-5 text-end">
                                        <strong class="d-block text-white text-truncate" title="<?= htmlspecialchars($c['time_casa']) ?>" style="font-size: 0.95rem;">
                                            <?= htmlspecialchars($c['time_casa']) ?>
                                        </strong>
                                    </div>
                                    <div class="col-2 text-center">
                                        <span class="badge bg-dark border border-secondary px-2 py-1 fs-6 font-monospace fw-bold text-info">
                                            <?= htmlspecialchars($c['placar']) ?>
                                        </span>
                                    </div>
                                    <div class="col-5 text-start">
                                        <strong class="d-block text-white text-truncate" title="<?= htmlspecialchars($c['time_fora']) ?>" style="font-size: 0.95rem;">
                                            <?= htmlspecialchars($c['time_fora']) ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            <!-- RESUMO DA APOSTA -->
                            <div class="p-2.5 rounded-3 mb-3" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.06);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-white small" style="font-size: 0.75rem;"><?= htmlspecialchars($c['mercado']) ?>:</span>
                                    <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 font-monospace" style="font-size: 0.72rem;">
                                        Odd <?= number_format($c['odd'], 2) ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-white small" style="font-size: 0.88rem;"><?= htmlspecialchars($c['palpite']) ?></strong>
                                    <span class="small font-monospace text-white">
                                        Stake: <strong class="text-white"><?= number_format($c['valor_aposta'], 2) ?></strong> <?= htmlspecialchars($c['moeda']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- ÁREA DO VELOCÍMETRO GAUGE -->
                            <div class="text-center py-2 position-relative gauge-wrapper">
                                <!-- SVG DO VELOCÍMETRO -->
                                <svg viewBox="0 0 180 115" width="190" height="115" class="gauge-svg">
                                    <!-- Arco Verde (0% a 40%) -->
                                    <path d="M 20 95 A 70 70 0 0 1 68.4 28.4" fill="none" stroke="#10b981" stroke-width="14" stroke-linecap="round"/>
                                    <!-- Arco Amarelo (40% a 75%) -->
                                    <path d="M 68.4 28.4 A 70 70 0 0 1 139.5 45.5" fill="none" stroke="#f59e0b" stroke-width="14"/>
                                    <!-- Arco Vermelho (75% a 100%) -->
                                    <path d="M 139.5 45.5 A 70 70 0 0 1 160 95" fill="none" stroke="#ef4444" stroke-width="14" stroke-linecap="round"/>
                                    
                                    <!-- Marcador Especial de Cash Out no Meio do Amarelo (60%) -->
                                    <line x1="111.6" y1="28.4" x2="114.7" y2="18.9" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                                    <text x="103" y="14" fill="#f59e0b" font-size="7.5" font-weight="bold" font-family="monospace">CASHOUT</text>

                                    <!-- Agulha / Ponteiro com Rotação Baseada em gauge_pct -->
                                    <?php 
                                        $angle = -90 + ($c['gauge_pct'] * 1.8); 
                                    ?>
                                    <g transform="rotate(<?= $angle ?>, 90, 95)" class="gauge-needle-group">
                                        <!-- Haste da Agulha -->
                                        <line x1="90" y1="95" x2="90" y2="38" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" filter="drop-shadow(0 2px 4px rgba(0,0,0,0.5))"/>
                                        <line x1="90" y1="44" x2="90" y2="38" stroke="<?= $isCashout ? '#ef4444' : '#38bdf8' ?>" stroke-width="4.5" stroke-linecap="round"/>
                                    </g>

                                    <!-- Pivô Central -->
                                    <circle cx="90" cy="95" r="7" fill="#1e293b" stroke="#ffffff" stroke-width="2.5"/>
                                    <circle cx="90" cy="95" r="3" fill="<?= $isCashout ? '#ef4444' : '#38bdf8' ?>"/>
                                </svg>

                                <!-- BADGE DO NÍVEL DO VELOCÍMETRO -->
                                <div class="mt-1">
                                    <?php if ($isCashout): ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-1.5 fs-7 font-monospace fw-bold pulse-badge shadow">
                                            ⚡ MOMENTO DO CASHOUT (<?= $c['gauge_pct'] ?>%)
                                        </span>
                                    <?php elseif ($c['faixa_cor'] === 'vermelho'): ?>
                                        <span class="badge bg-danger bg-opacity-75 rounded-pill px-3 py-1 font-monospace" style="font-size: 0.72rem;">
                                            🔴 RISCO CRÍTICO (<?= $c['gauge_pct'] ?>%)
                                        </span>
                                    <?php elseif ($c['faixa_cor'] === 'amarelo'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 font-monospace fw-bold" style="font-size: 0.72rem;">
                                            🟡 ATENÇÃO AO RITMO (<?= $c['gauge_pct'] ?>%)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 rounded-pill px-3 py-1 font-monospace" style="font-size: 0.72rem;">
                                            🟢 ZONA SEGURA (<?= $c['gauge_pct'] ?>%)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- MENSAGEM DINÂMICA DO ALERTA (COR DO TEXTO IGUALADA AO STATUS) -->
                            <?php 
                                $alertColorClass = $isCashout ? 'text-danger' : ($c['faixa_cor'] === 'vermelho' ? 'text-danger' : ($c['faixa_cor'] === 'amarelo' ? 'text-warning' : 'text-success'));
                                $alertBoxBorder = $isCashout ? 'border-danger' : ($c['faixa_cor'] === 'vermelho' ? 'border-danger' : ($c['faixa_cor'] === 'amarelo' ? 'border-warning' : 'border-success'));
                            ?>
                            <div class="p-2 px-2.5 mb-2 rounded-3 border <?= $alertBoxBorder ?>" style="font-size: 0.76rem; background: rgba(15, 23, 42, 0.75);">
                                <div class="fw-bold mb-0.5 <?= $alertColorClass ?>">
                                    <?= htmlspecialchars($c['alerta_titulo']) ?>
                                </div>
                                <div class="lh-sm <?= $alertColorClass ?>" style="opacity: 0.9;">
                                    <?= htmlspecialchars($c['alerta_mensagem']) ?>
                                </div>
                            </div>

                            <!-- RODAPÉ TÁTICO: MÉTRICAS COMPLEMENTARES E LINK DE AUDITORIA -->
                            <div class="pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center small text-white">
                                <span style="font-size: 0.72rem;">
                                    <?= htmlspecialchars($c['detalhe_tatico']) ?>
                                </span>
                                
                                <button type="button" class="btn btn-link btn-sm text-info p-0 text-decoration-none d-flex align-items-center gap-1 btn-ver-auditoria" 
                                        data-fixture-id="<?= $c['fixture_id'] ?>" style="font-size: 0.72rem;">
                                    <i class="bi bi-search"></i>
                                    <span>Auditoria</span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL CANÔNICO DE AUDITORIA PRÉ-JOGO (REUTILIZANDO CARD ORIGEM) -->
<div class="modal fade" id="modalAuditoriaOrigem" tabindex="-1" aria-labelledby="modalAuditoriaOrigemLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary py-2.5">
                <h5 class="modal-title fs-6 fw-bold text-info" id="modalAuditoriaOrigemLabel">
                    <i class="bi bi-shield-check me-1.5"></i>Auditoria Pré-Jogo & Parâmetros do Gatekeeper
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" id="modalAuditoriaOrigemConteudo">
                <div class="text-center py-5">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-white small mt-2">Carregando auditoria completa da partida...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ANIMAÇÕES VISUAIS DO VELOCÍMETRO E DO CASHOUT */
@keyframes pulseLive {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.96); }
    100% { opacity: 1; transform: scale(1); }
}

.pulse-live {
    animation: pulseLive 2s infinite ease-in-out;
}

.pulse-live-indicator {
    animation: pulseLive 1.5s infinite ease-in-out;
}

@keyframes pulseCashoutBorder {
    0% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        border-color: #ef4444;
    }
    70% {
        box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
        border-color: #f59e0b;
    }
    100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        border-color: #ef4444;
    }
}

.border-cashout-pulse {
    animation: pulseCashoutBorder 2s infinite ease-in-out;
}

.pulse-cashout-box {
    animation: pulseCashoutBorder 2s infinite ease-in-out;
}

.pulse-badge {
    animation: pulseLive 1.2s infinite ease-in-out;
}

.gauge-svg {
    filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.4));
}

.gauge-needle-group {
    transition: transform 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const COUNTDOWN_TOTAL_SECONDS = 180; // 3 minutos
    let secondsLeft = COUNTDOWN_TOTAL_SECONDS;
    let refreshInterval = null;
    let countdownInterval = null;

    const countdownText = document.getElementById('countdown-text');
    const countdownBar = document.getElementById('countdown-bar');
    const btnRefreshManual = document.getElementById('btn-refresh-manual');
    const iconRefresh = document.getElementById('icon-refresh');
    const lastSyncTime = document.getElementById('last-sync-time');
    const cardsContainer = document.getElementById('cards-container');

    // Formata segundos em MM:SS
    function formatTime(sec) {
        const m = Math.floor(sec / 60).toString().padStart(2, '0');
        const s = (sec % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    // Atualiza barra regressiva
    function updateCountdownUI() {
        if (countdownText) countdownText.textContent = formatTime(secondsLeft);
        if (countdownBar) {
            const pct = (secondsLeft / COUNTDOWN_TOTAL_SECONDS) * 100;
            countdownBar.style.width = pct + '%';
            if (pct <= 20) {
                countdownBar.className = 'progress-bar bg-danger';
            } else if (pct <= 50) {
                countdownBar.className = 'progress-bar bg-warning';
            } else {
                countdownBar.className = 'progress-bar bg-info';
            }
        }
    }

    // Inicia relógio regressivo
    function startCountdown() {
        clearInterval(countdownInterval);
        secondsLeft = COUNTDOWN_TOTAL_SECONDS;
        updateCountdownUI();

        countdownInterval = setInterval(() => {
            secondsLeft--;
            if (secondsLeft <= 0) {
                secondsLeft = COUNTDOWN_TOTAL_SECONDS;
                executarSincronizacaoLive();
            }
            updateCountdownUI();
        }, 1000);
    }

    // Requisição AJAX que busca os dados de observabilidade ao vivo
    function executarSincronizacaoLive() {
        if (iconRefresh) iconRefresh.classList.add('spin-animation');

        fetch('<?= base_url('observabilidade/dados') ?>')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Atualiza KPIs
                    document.getElementById('kpi-total-jogos').textContent = data.totalEmJogo;
                    document.getElementById('kpi-total-stake').textContent = data.totalStakeExposta;
                    document.getElementById('kpi-total-retorno').textContent = data.totalRetornoProj;
                    document.getElementById('kpi-total-cashout').textContent = data.totalAlertasCashout;
                    
                    if (lastSyncTime) lastSyncTime.textContent = data.timestamp;

                    // Atualiza Métricas do Ciclo de Banca se presente
                    if (data.metricasCiclo && data.metricasCiclo.has_ciclo) {
                        const mc = data.metricasCiclo;
                        const elSaldo = document.getElementById('ciclo-saldo-disp');
                        const elCampo = document.getElementById('ciclo-capital-campo');
                        const elPatrimonio = document.getElementById('ciclo-patrimonio-total');
                        const elBadge = document.getElementById('ciclo-crescimento-badge');
                        if (elSaldo) elSaldo.textContent = mc.saldo_disponivel_fmt;
                        if (elCampo) elCampo.textContent = mc.capital_em_campo_fmt;
                        if (elPatrimonio) elPatrimonio.textContent = mc.patrimonio_total_fmt;
                        if (elBadge) {
                            const sign = mc.crescimento_banca_pct >= 0 ? '+' : '';
                            elBadge.textContent = `${sign}${mc.crescimento_banca_pct.toFixed(1)}%`;
                            elBadge.className = `badge ${mc.crescimento_banca_pct >= 0 ? 'bg-success' : 'bg-danger'} fs-6 font-monospace px-2.5 py-1`;
                        }
                    }

                    // Re-renderiza Cards no DOM
                    renderizarCards(data.cards);
                }
            })
            .catch(err => {
                console.error('[Observabilidade] Falha ao sincronizar dados ao vivo:', err);
            })
            .finally(() => {
                if (iconRefresh) iconRefresh.classList.remove('spin-animation');
                startCountdown(); // Reinicia o timer de 3 minutos
            });
    }

    // Renderizador dinâmico de cards com velocímetros
    function renderizarCards(cards) {
        if (!cardsContainer) return;

        if (!cards || cards.length === 0) {
            cardsContainer.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="p-5 rounded-4 bg-dark bg-opacity-50 border border-secondary border-opacity-25 max-w-lg mx-auto">
                        <i class="bi bi-shield-check text-info display-4 mb-3 d-block"></i>
                        <h4 class="text-white fw-bold">Nenhum bilhete em andamento no momento</h4>
                        <p class="text-white small mb-0">
                            Assim que partidas com apostas ativas iniciarem, os velocímetros de risco e os alertas de Cash Out aparecerão automaticamente aqui.
                        </p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';
        cards.forEach(c => {
            const isCashout = c.is_cashout_moment;
            const borderClass = isCashout ? 'border-cashout-pulse' : (c.faixa_cor === 'vermelho' ? 'border-danger' : (c.faixa_cor === 'amarelo' ? 'border-warning' : 'border-success'));
            const filterCat = isCashout ? 'cashout' : (c.is_live ? 'live' : 'prejogo');
            const angle = -90 + (c.gauge_pct * 1.8);

            let badgeHtml = '';
            if (isCashout) {
                badgeHtml = `<span class="badge bg-danger rounded-pill px-3 py-1.5 fs-7 font-monospace fw-bold pulse-badge shadow">⚡ MOMENTO DO CASHOUT (${c.gauge_pct}%)</span>`;
            } else if (c.faixa_cor === 'vermelho') {
                badgeHtml = `<span class="badge bg-danger bg-opacity-75 rounded-pill px-3 py-1 font-monospace" style="font-size: 0.72rem;">🔴 RISCO CRÍTICO (${c.gauge_pct}%)</span>`;
            } else if (c.faixa_cor === 'amarelo') {
                badgeHtml = `<span class="badge bg-warning text-dark rounded-pill px-3 py-1 font-monospace fw-bold" style="font-size: 0.72rem;">🟡 ATENÇÃO AO RITMO (${c.gauge_pct}%)</span>`;
            } else {
                badgeHtml = `<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 rounded-pill px-3 py-1 font-monospace" style="font-size: 0.72rem;">🟢 ZONA SEGURA (${c.gauge_pct}%)</span>`;
            }

            const alertBorderClass = isCashout ? 'border-danger' : (c.faixa_cor === 'vermelho' ? 'border-danger' : (c.faixa_cor === 'amarelo' ? 'border-warning' : 'border-success'));
            const alertTextClass = isCashout ? 'text-danger' : (c.faixa_cor === 'amarelo' ? 'text-warning' : (c.faixa_cor === 'vermelho' ? 'text-danger' : 'text-success'));

            html += `
                <div class="col-12 col-md-6 col-xl-4 card-item" data-category="${filterCat}" data-card-id="${c.aposta_id}">
                    <div class="card h-100 bg-dark text-white rounded-3 shadow-sm position-relative overflow-hidden ${borderClass}" 
                         style="background: linear-gradient(180deg, #131b2e 0%, #0d1322 100%) !important; border-width: 2px !important;">

                        ${isCashout ? `
                            <div class="bg-danger bg-gradient text-white text-center py-1 px-2 fw-bold small text-uppercase tracking-wider d-flex align-items-center justify-content-center gap-1.5 shadow-sm">
                                <i class="bi bi-lightning-charge-fill animate-bounce"></i>
                                <span>⚡ MOMENTO DO CASHOUT: ENCERRE A APOSTA</span>
                                <i class="bi bi-lightning-charge-fill animate-bounce"></i>
                            </div>
                        ` : ''}

                        <div class="card-body p-3 d-flex flex-column justify-content-between">
                            
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary border-opacity-25">
                                <span class="text-white small d-flex align-items-center gap-1 font-monospace" style="font-size: 0.72rem;">
                                    <i class="bi bi-trophy text-info"></i>
                                    ${escapeHtml(c.league_name)}
                                </span>
                                <span class="badge ${c.status_badge} rounded-pill font-monospace" style="font-size: 0.70rem; padding: 4px 8px;">
                                    ${escapeHtml(c.status_label)}
                                </span>
                            </div>

                            <div class="text-center py-1 mb-2">
                                <div class="row align-items-center g-0">
                                    <div class="col-5 text-end">
                                        <strong class="d-block text-white text-truncate" style="font-size: 0.95rem;">
                                            ${escapeHtml(c.time_casa)}
                                        </strong>
                                    </div>
                                    <div class="col-2 text-center">
                                        <span class="badge bg-dark border border-secondary px-2 py-1 fs-6 font-monospace fw-bold text-info">
                                            ${escapeHtml(c.placar)}
                                        </span>
                                    </div>
                                    <div class="col-5 text-start">
                                        <strong class="d-block text-white text-truncate" style="font-size: 0.95rem;">
                                            ${escapeHtml(c.time_fora)}
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            <div class="p-2.5 rounded-3 mb-3" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.06);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-white small" style="font-size: 0.75rem;">${escapeHtml(c.mercado)}:</span>
                                    <span class="badge bg-info bg-opacity-25 text-info border border-info border-opacity-50 font-monospace" style="font-size: 0.72rem;">
                                        Odd ${parseFloat(c.odd).toFixed(2)}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-white small" style="font-size: 0.88rem;">${escapeHtml(c.palpite)}</strong>
                                    <span class="small font-monospace text-white">
                                        Stake: <strong class="text-white">${parseFloat(c.valor_aposta).toFixed(2)}</strong> ${escapeHtml(c.moeda)}
                                    </span>
                                </div>
                            </div>

                            <div class="text-center py-2 position-relative gauge-wrapper">
                                <svg viewBox="0 0 180 115" width="190" height="115" class="gauge-svg">
                                    <path d="M 20 95 A 70 70 0 0 1 68.4 28.4" fill="none" stroke="#10b981" stroke-width="14" stroke-linecap="round"/>
                                    <path d="M 68.4 28.4 A 70 70 0 0 1 139.5 45.5" fill="none" stroke="#f59e0b" stroke-width="14"/>
                                    <path d="M 139.5 45.5 A 70 70 0 0 1 160 95" fill="none" stroke="#ef4444" stroke-width="14" stroke-linecap="round"/>
                                    
                                    <!-- Marcador Especial de Cash Out no Meio do Amarelo (60%) -->
                                    <line x1="111.6" y1="28.4" x2="114.7" y2="18.9" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                                    <text x="103" y="14" fill="#f59e0b" font-size="7.5" font-weight="bold" font-family="monospace">CASHOUT</text>

                                    <g transform="rotate(${angle}, 90, 95)" class="gauge-needle-group">
                                        <line x1="90" y1="95" x2="90" y2="38" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>
                                        <line x1="90" y1="44" x2="90" y2="38" stroke="${isCashout ? '#ef4444' : '#38bdf8'}" stroke-width="4.5" stroke-linecap="round"/>
                                    </g>

                                    <circle cx="90" cy="95" r="7" fill="#1e293b" stroke="#ffffff" stroke-width="2.5"/>
                                    <circle cx="90" cy="95" r="3" fill="${isCashout ? '#ef4444' : '#38bdf8'}"/>
                                </svg>

                                <div class="mt-1">
                                    ${badgeHtml}
                                </div>
                            </div>

                            <div class="p-2 px-2.5 mb-2 rounded-3 border ${alertBorderClass}" style="font-size: 0.76rem; background: rgba(15, 23, 42, 0.75);">
                                <div class="fw-bold mb-0.5 ${alertTextClass}">
                                    ${escapeHtml(c.alerta_titulo)}
                                </div>
                                <div class="lh-sm ${alertTextClass}" style="opacity: 0.9;">
                                    ${escapeHtml(c.alerta_mensagem)}
                                </div>
                            </div>

                            <div class="pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center small text-white">
                                <span style="font-size: 0.72rem;">
                                    ${escapeHtml(c.detalhe_tatico)}
                                </span>
                                
                                <button type="button" class="btn btn-link btn-sm text-info p-0 text-decoration-none d-flex align-items-center gap-1 btn-ver-auditoria" 
                                        data-fixture-id="${c.fixture_id}" style="font-size: 0.72rem;">
                                    <i class="bi bi-search"></i>
                                    <span>Auditoria</span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            `;
        });

        cardsContainer.innerHTML = html;
        aplicarFiltroAtivo();
        vincularBotoesAuditoria();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Filtros de Categoria (Todos, Cash Out, Ao Vivo, Pré-Jogo)
    let filtroAtivo = 'all';
    const filterButtons = document.querySelectorAll('.filter-btn');

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filtroAtivo = this.getAttribute('data-filter');
            aplicarFiltroAtivo();
        });
    });

    function aplicarFiltroAtivo() {
        const items = document.querySelectorAll('.card-item');
        items.forEach(it => {
            const cat = it.getAttribute('data-category');
            if (filtroAtivo === 'all') {
                it.style.display = '';
            } else if (filtroAtivo === 'cashout') {
                it.style.display = (cat === 'cashout') ? '' : 'none';
            } else if (filtroAtivo === 'live') {
                it.style.display = (cat === 'live' || cat === 'cashout') ? '' : 'none';
            } else if (filtroAtivo === 'prejogo') {
                it.style.display = (cat === 'prejogo') ? '' : 'none';
            }
        });
    }

    // Modal de Auditoria Pré-Jogo (Reaproveitamento Canônico do Card Origem)
    function vincularBotoesAuditoria() {
        document.querySelectorAll('.btn-ver-auditoria').forEach(btn => {
            btn.onclick = function () {
                const fixId = this.getAttribute('data-fixture-id');
                const modalEl = document.getElementById('modalAuditoriaOrigem');
                const conteudoEl = document.getElementById('modalAuditoriaOrigemConteudo');

                if (!modalEl || !conteudoEl) return;

                conteudoEl.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-white small mt-2">Carregando auditoria completa da partida #${fixId}...</p>
                    </div>
                `;

                const modal = new bootstrap.Modal(modalEl);
                modal.show();

                fetch(`<?= base_url('apostas/card-origem') ?>/${fixId}`)
                    .then(res => res.text())
                    .then(html => {
                        conteudoEl.innerHTML = html;
                    })
                    .catch(err => {
                        conteudoEl.innerHTML = `<div class="alert alert-danger">Falha ao carregar auditoria: ${err.message}</div>`;
                    });
            };
        });
    }

    // Clique manual no botão de atualizar
    if (btnRefreshManual) {
        btnRefreshManual.addEventListener('click', function () {
            executarSincronizacaoLive();
        });
    }

    // Inicialização
    startCountdown();
    vincularBotoesAuditoria();
});
</script>

<?= $this->include('footer') ?>
