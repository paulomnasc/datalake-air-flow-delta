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

    <!-- INTRODUÇÃO: ARQUITETURA DE GOVERNANÇA EM 2 ESTÁGIOS (PRÉVIA MATEMÁTICA VS. SÚMULA OFICIAL) -->
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid rgba(56, 189, 248, 0.3) !important; border-left: 5px solid #38bdf8 !important; border-radius: 12px;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="badge bg-info text-dark fw-bold px-2.5 py-1 text-uppercase" style="letter-spacing: 0.5px;">Governança Operacional</span>
                <span class="text-white-50 small font-monospace">Decisão Algorítmica em 2 Estágios: Prévia Matemática vs. Trava de Súmula Oficial</span>
            </div>
            <h4 class="fw-bold text-white mb-2">
                <i class="bi bi-shield-check text-info me-2"></i>Como Funciona a Avaliação de BET e NO-BET no Pipeline
            </h4>
            <p class="text-light mb-3" style="font-size: 0.92rem; line-height: 1.6;">
                O sistema opera através de uma rigorosa <strong>arquitetura de decisão em dois estágios complementares</strong>, separando claramente a análise estatística preliminar da execução financeira definitiva na banca:
            </p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded h-100" style="background: rgba(15, 23, 42, 0.6); border: 1px solid #334155;">
                        <h6 class="fw-bold text-info mb-2 d-flex align-items-center gap-1.5">
                            <i class="bi bi-calculator"></i> Estágio 1: Análise Prévia Matemática (Horas antes)
                        </h6>
                        <p class="text-secondary small mb-0" style="line-height: 1.55;">
                            Ocorre no momento da ingestão da partida na esteira (véspera ou manhã do jogo). O motor de Poisson bivariado, as médias móveis U5J e o Gatekeeper analisam as probabilidades teóricas contra as cotações das bookmakers, atribuindo uma classificação preliminar de <strong>BET (Aprovado)</strong> ou <strong>NO-BET (Abstenção)</strong>. Partidas com anomalias de risco evidentes (ex: <em>Caldeirão da Degola</em>, <em>Mando Soberano</em> ou ausência de margem +EV) já são filtradas preventivamente nesta fase.
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded h-100" style="background: rgba(15, 23, 42, 0.6); border: 1px solid #334155;">
                        <h6 class="fw-bold text-success mb-2 d-flex align-items-center gap-1.5">
                            <i class="bi bi-file-earmark-person-fill"></i> Estágio 2: Trava de Súmula Oficial (T-60m a T-45m)
                        </h6>
                        <p class="text-secondary small mb-0" style="line-height: 1.55;">
                            <strong>A confirmação financeira real depende estritamente da Súmula Oficial (11 Titulares).</strong> Nenhuma ordem é enviada às cegas: no painel web, apostas Premium permanecem bloqueadas com o botão <span class="badge bg-secondary text-light">⏳ Aguardar Súmula</span>; no robô autônomo Cloudbet, o envio fica retido até que a escalação oficial seja confirmada em <code class="text-info">match_lineups_cache</code>. A <strong>IA Sentinela Gemini</strong> audita os titulares, podendo vetar apostas pré-aprovadas caso haja time misto (<span class="text-danger fw-bold">ALERTA_RISCO</span>) ou repescar partidas de NO-BET caso o adversário tenha poupado 5+ titulares (Reset Tático por Súmula).
                        </p>
                    </div>
                </div>
            </div>
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
                <span class="badge bg-secondary">8 DAGs no Pipeline</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle" style="background-color: transparent; min-width: 960px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #334155; font-size: 0.85rem; text-transform: uppercase; color: #94a3b8;">
                            <th class="py-3 px-3" style="width: 175px;">DAG ID</th>
                            <th class="py-3 text-center" style="width: 85px;">Frequência</th>
                            <th class="py-3" style="width: 175px;">Script Executor</th>
                            <th class="py-3 text-center" style="width: 125px;">Mercado</th>
                            <th class="py-3 text-center" style="width: 135px;">Consumo Médio (Tokens/Dia)</th>
                            <th class="py-3 px-3">Papel no Pipeline</th>
                        </tr>
                    </thead>
                    <tbody style="border-color: #334155;">
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-monospace">criar_apostas_cloudbet_dag</span>
                            </td>
                            <td class="text-center"><span class="badge bg-dark text-info border border-secondary">*/30 min</span></td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">criar_apostas_cloudbet_diario.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-info text-dark fw-bold">Handicap Asiático</span></td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle fw-bold d-inline-block">0 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(0,0%)</small></span></td>
                            <td class="px-3 small text-secondary">
                                <strong class="text-white">Robô Autônomo 100%:</strong> Consulta o catálogo Cloudbet via proxy suíço, roda o Gatekeeper e <span class="text-warning">dispara a ordem real/simulada (`place_bet`)</span> com micro-stakes de 0.10 USDT. Zero consumo na API-Sports.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1 font-monospace text-decoration-line-through">criar_apostas_gols_dag</span>
                                <br><span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1"><i class="bi bi-pause-circle me-1"></i>PARALISADA</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-dark text-muted border border-secondary text-decoration-line-through">A cada 3h</span>
                                <br><span class="badge bg-secondary text-light mt-1">Pausada</span>
                            </td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">criar_apostas_gols_diario.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary text-light">Gols Asiáticos</span></td>
                            <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border border-secondary d-inline-block">0 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(Pausada)</small></span></td>
                            <td class="px-3 small text-secondary">
                                <strong class="text-warning">⏸️ DAG Paralisada:</strong> Avaliação legada de gols pré-jogo. A emissão autônoma de palpites e ordens de Gols Asiáticos foi unificada diretamente na <code class="text-info">criar_apostas_cloudbet_dag</code> (robô multimercado em USDT), prevenindo duplicidade de apostas e economizando cotas.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge bg-secondary font-monospace">criar_apostas_handicap_dag</span>
                            </td>
                            <td class="text-center"><span class="badge bg-dark text-info border border-secondary">A cada 3h</span></td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">criar_apostas_handicap_diario.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary text-light">Handicap Asiático</span></td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle d-inline-block">~50 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(0,7%)</small></span></td>
                            <td class="px-3 small text-secondary">
                                Esteira clássica de triagem de Handicap da grade diária com foco em portfólio pré-jogo.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1 font-monospace text-decoration-line-through">criar_apostas_cartoes_dag</span>
                                <br><span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1"><i class="bi bi-pause-circle me-1"></i>PARALISADA</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-dark text-muted border border-secondary text-decoration-line-through">A cada 1h</span>
                                <br><span class="badge bg-secondary text-light mt-1">Pausada</span>
                            </td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">criar_apostas_cartoes_diario.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary text-light">Cartões Under</span></td>
                            <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border border-secondary d-inline-block">0 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(Pausada)</small></span></td>
                            <td class="px-3 small text-secondary">
                                <strong class="text-warning">⏸️ DAG Paralisada:</strong> Esteira pré-jogo de cartões pausada para priorizar a liquidez e volume nas ordens autônomas da Cloudbet em USDT, economizando ~120 tokens/dia da API-Sports.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge font-monospace" style="background: rgba(168, 85, 247, 0.18); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.35);">football_gemini_sentinel_dag</span>
                            </td>
                            <td class="text-center"><span class="badge bg-dark text-info border border-secondary">*/5 min</span></td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">gemini_sentinel.py</strong>
                            </td>
                            <td class="text-center"><span class="badge" style="background: rgba(168, 85, 247, 0.2); color: #e9d5ff;">Auditoria IA / Escalações</span></td>
                            <td class="text-center"><span class="badge d-inline-block" style="background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.4);">~350 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(4,7%)</small></span></td>
                            <td class="px-3 small text-secondary">
                                <strong>Sentinela em Dois Estágios:</strong> Fase 1 (Antecipação Matinal 24h via notícias web de desfalques) e Fase 2 (Repescagem de 11 titulares oficiais T-50m e detecção de drift adverso de mercado).
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 font-monospace">football_trends_live_dag</span>
                            </td>
                            <td class="text-center"><span class="badge bg-dark text-danger border border-secondary">A cada 2 min</span></td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">cloudbet_live_cashout.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-danger text-light fw-bold">Ao Vivo / In-Play</span></td>
                            <td class="text-center"><span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-block">~1.800 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(24,0%)</small></span></td>
                            <td class="px-3 small text-secondary">
                                Monitora os 90 minutos de jogo com o placar ao vivo. Se houver desvio tático ou gatilho de stop-loss, dispara contra-ordem (<strong class="text-warning">Cross-Hedge</strong>) na Cloudbet.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-monospace">processar_apostas_encerradas_dag</span>
                            </td>
                            <td class="text-center"><span class="badge bg-dark text-info border border-secondary">A cada 5 min</span></td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">processar_apostas_encerradas.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-info text-dark fw-bold">Pós-Jogo / FT</span></td>
                            <td class="text-center"><span class="badge bg-info-subtle text-info border border-info-subtle d-inline-block">~280 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(3,7%)</small></span></td>
                            <td class="px-3 small text-secondary">
                                Lê o placar e as súmulas oficiais de jogos encerrados, liquida o resultado e concilia créditos/débitos na conta corrente do usuário.
                            </td>
                        </tr>
                        <tr>
                            <td class="py-3 px-3">
                                <span class="badge font-monospace" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.4);">football_trends_ingestion_dag</span>
                            </td>
                            <td class="text-center"><span class="badge bg-dark text-info border border-secondary">A cada 15 min</span></td>
                            <td class="font-monospace small text-light" style="line-height: 1.35;">
                                <span class="text-secondary small">scripts/</span><br>
                                <strong class="text-info">football_ingest_trends.py</strong>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary text-light">Fixtures & Odds Globais</span></td>
                            <td class="text-center"><span class="badge d-inline-block" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.4);">~2.500 tokens/dia<br><small style="font-size: 0.72rem; opacity: 0.85;">(33,3%)</small></span></td>
                            <td class="px-3 small text-secondary">
                                Descoberta contínua de partidas da grade, atualização de metadados de ligas e ingestão de odds 1X2 das casas de apostas.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot style="background-color: rgba(15, 23, 42, 0.8); border-top: 2px solid #334155;">
                        <tr class="fw-bold text-light">
                            <td colspan="4" class="text-end py-3 px-4 text-uppercase small text-secondary">
                                <i class="bi bi-calculator text-info me-1"></i> Total Médio de Consumo Diário do Pipeline:
                            </td>
                            <td class="py-3 px-4"><span class="badge bg-info text-dark fw-bold fs-6">4.980 tokens/dia</span></td>
                            <td class="px-4 small text-secondary">Soma consolidada de todas as esteiras ativas</td>
                        </tr>
                        <tr class="fw-bold text-light">
                            <td colspan="4" class="text-end py-3 px-4 text-uppercase small text-secondary">
                                <i class="bi bi-shield-check text-success me-1"></i> Cota Disponibilizada na API-Sports (Plano Pro):
                            </td>
                            <td class="py-3 px-4"><span class="badge bg-success text-dark fw-bold fs-6">7.500 tokens/dia</span></td>
                            <td class="px-4 small text-secondary">Limite oficial contratado (Plano Pro ativo)</td>
                        </tr>
                        <tr class="fw-bold text-light">
                            <td colspan="4" class="text-end py-3 px-4 text-uppercase small text-secondary">
                                <i class="bi bi-pie-chart text-primary me-1"></i> Balanço Calculado (Uso vs. Disponível):
                            </td>
                            <td class="py-3 px-4">
                                <span class="badge bg-primary text-light fw-bold fs-6">66,4% em Uso</span>
                            </td>
                            <td class="px-4">
                                <span class="badge bg-success text-dark fw-bold fs-6">33,6% Disponível</span>
                                <span class="text-secondary small ms-2">(2.520 tokens livres como margem de contingência diária)</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- CARD VISUAL DE COTA & PERCENTUAIS -->
            <div class="m-3 p-3 rounded" style="background-color: #0f172a; border: 1px solid #334155;">
                <div class="d-flex justify-content-between align-items-center mb-2 small">
                    <span class="text-light fw-semibold"><i class="bi bi-speedometer2 text-primary me-1"></i> Utilização da Cota API-Sports: <strong>4.980 / 7.500 requisições</strong></span>
                    <span class="text-success fw-bold"><i class="bi bi-shield-lock me-1"></i> Margem de Segurança: 33,6% Livre</span>
                </div>
                <div class="progress" style="height: 10px; background-color: #334155;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 66.4%;" title="66,4% em Uso"></div>
                    <div class="progress-bar bg-success" role="progressbar" style="width: 33.6%;" title="33,6% Disponível"></div>
                </div>
            </div>
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
                        <li class="d-flex align-items-start gap-3">
                            <span class="badge p-2 mt-1 text-white" style="background-color: #8b5cf6;"><i class="bi bi-eye-fill"></i></span>
                            <div>
                                <strong class="text-white">Sentinela de Inteligência Pré-Jogo (Gemini Sentinel):</strong>
                                <span class="d-block text-secondary small">
                                    <code class="text-info">scripts/gemini_sentinel.py</code>: orquestrada pela DAG <code>football_gemini_sentinel_dag</code> a cada 5 min. Opera a Fase 1 (Antecipação Matinal 24h via Google Grounding) e Fase 2 (Repescagem de 11 titulares oficiais T-50m e vigilância contra drift de odds). Se houver desfalque crítico de última hora, autoriza contra-ordem defensiva de hedge na Cloudbet.
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
