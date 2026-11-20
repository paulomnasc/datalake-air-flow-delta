<div class="container-fluid py-4 px-md-5" style="background-color: #0f172a; min-height: 90vh; color: #f1f5f9;">
    <!-- CABEÇALHO DA DOCUMENTAÇÃO -->
    <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-4 border-bottom" style="border-color: #334155 !important;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary px-2.5 py-1 text-uppercase" style="letter-spacing: 0.5px;">Documentação Oficial</span>
                <span class="badge bg-success px-2.5 py-1 text-uppercase" style="letter-spacing: 0.5px;">Cloudbet 100% Robot</span>
                <span class="badge bg-info px-2.5 py-1 text-uppercase" style="letter-spacing: 0.5px;">USDT Cripto</span>
            </div>
            <h2 class="fw-bold text-white mb-0">
                <i class="bi bi-diagram-3-fill text-info me-2"></i>Arquitetura do Pipeline & DAGs Cloudbet
            </h2>
            <p class="text-secondary mb-0 small mt-1">
                Estrutura de dados, esteiras do Apache Airflow, governança matemática de Gatekeeper (+EV) e gestão de ordens em criptomoeda.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-3 mt-md-0">
            <a href="<?= base_url('observabilidade') ?>" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-speedometer2"></i> Painel Ao Vivo
            </a>
            <a href="<?= base_url('apostas') ?>" class="btn btn-outline-success btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-ticket-detailed"></i> Minhas Apostas
            </a>
        </div>
    </div>

    <!-- CARDS DE DESTAQUE QUANTITATIVO -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid #334155 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Criação de Ordens</span>
                        <i class="bi bi-robot text-primary fs-4"></i>
                    </div>
                    <div class="fs-4 fw-bold text-white">100% Autônoma</div>
                    <p class="text-secondary small mb-0 mt-1">Cotações em tempo real via Cloudbet API e execução robotizada (`place_bet`).</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid #334155 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Crivo Gatekeeper</span>
                        <i class="bi bi-shield-check text-success fs-4"></i>
                    </div>
                    <div class="fs-4 fw-bold text-success">+EV Matemático</div>
                    <p class="text-secondary small mb-0 mt-1">Distribuição Poisson bivariada com exigência estrita de Tiers PREMIUM / QUASE_PREMIUM.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid #334155 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">In-Play & Cross-Hedge</span>
                        <i class="bi bi-arrow-left-right text-warning fs-4"></i>
                    </div>
                    <div class="fs-4 fw-bold text-warning">Feed Ao Vivo</div>
                    <p class="text-secondary small mb-0 mt-1">Reconciliador dinâmico de eventos `TRADING_LIVE` e contra-apostas defensivas.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border: 1px solid #334155 !important; border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary small fw-bold text-uppercase">Liquidação Justa</span>
                        <i class="bi bi-arrow-counterclockwise text-info fs-4"></i>
                    </div>
                    <div class="fs-4 fw-bold text-info">Void / Push</div>
                    <p class="text-secondary small mb-0 mt-1">Reembolso integral em linhas inteiras (.0) no empate exato do resultado.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SEÇÃO 1: CATÁLOGO DE DAGS DO AIRFLOW -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: #1e293b; border: 1px solid #334155 !important; border-radius: 12px;">
        <div class="card-header bg-transparent py-3 border-bottom" style="border-color: #334155 !important;">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-bold text-white d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i> 1. Catálogo de DAGs do Apache Airflow
                </h5>
                <span class="badge bg-secondary">6 DAGs no Pipeline</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle" style="background-color: transparent;">
                    <thead>
                        <tr style="border-bottom: 2px solid #334155; font-size: 0.85rem; text-transform: uppercase; color: #94a3b8;">
                            <th class="py-3 px-4">DAG ID</th>
                            <th class="py-3">Frequência</th>
                            <th class="py-3">Script Executor</th>
                            <th class="py-3">Mercado</th>
                            <th class="py-3 px-4">Papel no Pipeline</th>
                        </tr>
                    </thead>
                    <tbody style="border-color: #334155;">
                        <tr>
                            <td class="py-3 px-4">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-monospace">criar_apostas_cloudbet_dag</span>
                            </td>
                            <td><span class="badge bg-dark text-info border border-secondary">*/30 min</span></td>
                            <td class="font-monospace small text-light">scripts/criar_apostas_cloudbet_diario.py</td>
                            <td><span class="badge bg-info text-dark fw-bold">Handicap Asiático</span></td>
                            <td class="px-4 small text-secondary">
                                <strong class="text-white">Robô Autônomo 100%:</strong> Consulta o catálogo Cloudbet via proxy suíço, roda o Gatekeeper e <span class="text-warning">dispara a ordem real/simulada (`place_bet`)</span> com micro-stakes de 0.10 USDT.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-monospace">criar_apostas_gols_dag</span>
                            </td>
                            <td><span class="badge bg-dark text-info border border-secondary">A cada 3 horas</span></td>
                            <td class="font-monospace small text-light">scripts/criar_apostas_gols_diario.py</td>
                            <td><span class="badge bg-success text-dark fw-bold">Gols Asiáticos</span></td>
                            <td class="px-4 small text-secondary">
                                Avalia partidas abertas para o mercado de totais asiáticos (com amortecimento em linhas inteiras 2.0 e 3.0), chancelando apostas no portfólio em USDT.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4">
                                <span class="badge bg-secondary font-monospace">criar_apostas_handicap_dag</span>
                            </td>
                            <td><span class="badge bg-dark text-info border border-secondary">A cada 3 horas</span></td>
                            <td class="font-monospace small text-light">scripts/criar_apostas_handicap_diario.py</td>
                            <td><span class="badge bg-secondary text-light">Handicap Asiático</span></td>
                            <td class="px-4 small text-secondary">
                                Esteira clássica de triagem de Handicap da grade diária com foco em portfólio pré-jogo.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4">
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 font-monospace">criar_apostas_cartoes_dag</span>
                            </td>
                            <td><span class="badge bg-dark text-info border border-secondary">A cada 1 hora</span></td>
                            <td class="font-monospace small text-light">scripts/criar_apostas_cartoes_diario.py</td>
                            <td><span class="badge bg-warning text-dark fw-bold">Cartões Under</span></td>
                            <td class="px-4 small text-secondary">
                                Monitora árbitros cadastrados e estatísticas disciplinares consolidadas para entradas táticas em Under 6.5 e Over 3.5 nas ligas de elite (+EV $\ge$ 5.0%).
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4">
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 font-monospace">football_trends_live_dag</span>
                            </td>
                            <td><span class="badge bg-dark text-danger border border-secondary">A cada 2 min</span></td>
                            <td class="font-monospace small text-light">scripts/cloudbet_live_cashout.py</td>
                            <td><span class="badge bg-danger text-light fw-bold">Ao Vivo / In-Play</span></td>
                            <td class="px-4 small text-secondary">
                                Monitora os 90 minutos de jogo com o placar ao vivo. Se houver desvio tático ou gatilho de stop-loss, dispara contra-ordem (<strong class="text-warning">Cross-Hedge</strong>) na Cloudbet.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-4">
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-monospace">processar_apostas_encerradas_dag</span>
                            </td>
                            <td><span class="badge bg-dark text-info border border-secondary">A cada 5 min</span></td>
                            <td class="font-monospace small text-light">scripts/processar_apostas_encerradas.py</td>
                            <td><span class="badge bg-info text-dark fw-bold">Pós-Jogo / FT</span></td>
                            <td class="px-4 small text-secondary">
                                Lê o placar e as súmulas oficiais de jogos encerrados, liquida o resultado e concilia créditos/débitos na conta corrente do usuário.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SEÇÃO 2: ARQUITETURA MODULAR DOS GATEKEEPERS -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100 border-0 shadow-sm" style="background-color: #1e293b; border: 1px solid #334155 !important; border-radius: 12px;">
                <div class="card-header bg-transparent py-3 border-bottom" style="border-color: #334155 !important;">
                    <h5 class="mb-0 fw-bold text-white d-flex align-items-center gap-2">
                        <i class="bi bi-cpu text-info"></i> 2. Arquitetura Modular do Gatekeeper
                    </h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-secondary small">
                        O robô autônomo da Cloudbet (<code class="text-info">criar_apostas_cloudbet_diario.py</code>) não usa fórmulas soltas no código: ele integra uma cadeia de módulos matemáticos especializados:
                    </p>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                        <li class="d-flex align-items-start gap-3">
                            <span class="badge bg-primary p-2 mt-1"><i class="bi bi-calculator"></i></span>
                            <div>
                                <strong class="text-white">Motor Matemático Central:</strong>
                                <span class="d-block text-secondary small">
                                    <code class="text-info">scripts/asian_handicap_engine.py</code>: executa <code class="text-light">calculate_unified_handicap_recommendation()</code> e <code class="text-light">evaluate_and_select_best_ah_candidate()</code> calculando a matriz bivariada de Poisson, odd justa e Valor Esperado (+EV).
                                </span>
                            </div>
                        </li>
                        <li class="d-flex align-items-start gap-3">
                            <span class="badge bg-warning p-2 mt-1 text-dark"><i class="bi bi-award-fill"></i></span>
                            <div>
                                <strong class="text-white">Governança e Tiers de Risco:</strong>
                                <span class="d-block text-secondary small">
                                    <code class="text-info">scripts/bet_tier.py</code> (Enum <code class="text-light">BetTier</code>): define as classes de entrada. O robô da Cloudbet descarta qualquer aposta Regular, criando ordens <strong>exclusivamente para Tiers PREMIUM e QUASE_PREMIUM</strong>.
                                </span>
                            </div>
                        </li>
                        <li class="d-flex align-items-start gap-3">
                            <span class="badge bg-success p-2 mt-1"><i class="bi bi-trophy"></i></span>
                            <div>
                                <strong class="text-white">Calibração de Pedigree de Elite:</strong>
                                <span class="d-block text-secondary small">
                                    <code class="text-info">scripts/leagues_config.py</code>: fornece <code class="text-light">is_tier_1_elite_club()</code> e <code class="text-light">get_team_pedigree_bonus()</code> para ponderar o peso histórico e estabilidade de elenco de gigantes europeus e sul-americanos.
                                </span>
                            </div>
                        </li>
                        <li class="d-flex align-items-start gap-3">
                            <span class="badge bg-info p-2 mt-1 text-dark"><i class="bi bi-graph-up"></i></span>
                            <div>
                                <strong class="text-white">Amostragem U5J Obrigatória:</strong>
                                <span class="d-block text-secondary small">
                                    <code class="text-info">scripts/football_ingest_trends.py</code>: valida os 5 jogos recentes de ambas as equipes (<code class="text-light">fetch_team_last5_form</code>) e preserva o bloco auditável <code class="text-warning">|| U5J_DATA:</code> (Regra de Ouro 16).
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 border-0 shadow-sm" style="background-color: #1e293b; border: 1px solid #334155 !important; border-radius: 12px;">
                <div class="card-header bg-transparent py-3 border-bottom" style="border-color: #334155 !important;">
                    <h5 class="mb-0 fw-bold text-white d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock text-success"></i> 3. Regras de Ouro e Integridade
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-dark border-secondary p-3 mb-3" style="background-color: #0b1120;">
                        <div class="fw-bold text-warning mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Regra 11: Sincronização Web Obrigatória</div>
                        <p class="small text-secondary mb-0">
                            Qualquer ajuste no motor de Poisson em Python possui método espelho obrigatório em <code class="text-light">ApostaController::evaluateGatekeeper</code> no PHP, assegurando que apostas criadas pela web sigam rigorosamente as mesmas diretrizes do Airflow.
                        </p>
                    </div>
                    <div class="alert alert-dark border-secondary p-3 mb-3" style="background-color: #0b1120;">
                        <div class="fw-bold text-info mb-1"><i class="bi bi-database-check me-1"></i> Regra 1: Cache-First no Banco de Dados</div>
                        <p class="small text-secondary mb-0">
                            Partidas consolidadas (`FT`), estatísticas de cartões e médias móveis são estritamente lidas do cache MySQL local, poupando requisições e garantindo idempotência.
                        </p>
                    </div>
                    <div class="alert alert-dark border-secondary p-3 mb-0" style="background-color: #0b1120;">
                        <div class="fw-bold text-success mb-1"><i class="bi bi-slash-circle me-1"></i> Regra 12: Proibição de Odds Sintéticas</div>
                        <p class="small text-secondary mb-0">
                            É expressamente proibido interpolar ou simular odds artificiais. Se a bookmaker oficial não tiver linhas ativas, o Gatekeeper decreta <strong class="text-white">NO_BET</strong> com transparência.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SEÇÃO 3: FLUXO DE CICLO DE VIDA DA APOSTA -->
    <div class="card border-0 shadow-sm" style="background-color: #1e293b; border: 1px solid #334155 !important; border-radius: 12px;">
        <div class="card-header bg-transparent py-3 border-bottom" style="border-color: #334155 !important;">
            <h5 class="mb-0 fw-bold text-white d-flex align-items-center gap-2">
                <i class="bi bi-arrow-repeat text-warning"></i> 4. Ciclo de Vida da Aposta (Da Originação à Liquidação)
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 text-center">
                <div class="col-md-3">
                    <div class="p-3 rounded h-100" style="background-color: #0b1120; border: 1px solid #334155;">
                        <div class="badge bg-primary mb-2">Etapa 1: Pré-Jogo</div>
                        <div class="fw-bold text-white mb-1">Criação & Execução</div>
                        <p class="small text-secondary mb-0">
                            `criar_apostas_cloudbet_dag` consulta o book de ofertas, valida +EV pelo Gatekeeper e emite a ordem via API em USDT.
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded h-100" style="background-color: #0b1120; border: 1px solid #334155;">
                        <div class="badge bg-warning text-dark mb-2">Etapa 2: In-Play</div>
                        <div class="fw-bold text-white mb-1">Sentinela & Cross-Hedge</div>
                        <p class="small text-secondary mb-0">
                            `football_trends_live_dag` acompanha a evolução do placar. Em cenário adverso grave, dispara contra-ordem de cobertura.
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded h-100" style="background-color: #0b1120; border: 1px solid #334155;">
                        <div class="badge bg-info text-dark mb-2">Etapa 3: Pós-Jogo (FT)</div>
                        <div class="fw-bold text-white mb-1">Súmula Oficial</div>
                        <p class="small text-secondary mb-0">
                            `processar_apostas_encerradas_dag` audita o placar. Linhas inteiras (.0) no empate exato recebem estorno integral (Void).
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded h-100" style="background-color: #0b1120; border: 1px solid #334155;">
                        <div class="badge bg-success mb-2">Etapa 4: Contábil</div>
                        <div class="fw-bold text-white mb-1">Crédito em Conta</div>
                        <p class="small text-secondary mb-0">
                            Ganhos ou devoluções de void são imediatamente creditados na `conta_corrente`, atualizando o saldo disponível da banca.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
