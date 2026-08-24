<?php
/**
 * View: Relatório de Diagnóstico de Apostas Perdidas com Groq AI
 * Apenas acessível para usuários autenticados via login Google e com saldo de créditos.
 */
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Marked.js para renderização limpa do Markdown retornado pelo Groq AI -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
  :root {
    --bet-bg: #0d1117;
    --bet-card-bg: #161b22;
    --bet-card-border: #21262d;
    --bet-primary: #00e676;
    --bet-primary-glow: rgba(0, 230, 118, 0.25);
    --bet-accent: #00b0ff;
    --bet-gold: #ffd600;
    --bet-danger: #ff5252;
    --bet-purple: #a78bfa;
    --bet-text-main: #f0f6fc;
    --bet-text-muted: #94a3b8;
  }

  body {
    background-color: var(--bet-bg) !important;
    font-family: 'Inter', sans-serif;
    color: var(--bet-text-main);
  }

  .bet-container {
    max-width: 1300px;
    margin: 30px auto;
    padding: 0 20px 60px 20px;
  }

  /* Header banner */
  .bet-header {
    background: linear-gradient(135deg, #161b22 0%, #1f2937 100%);
    border: 1px solid var(--bet-card-border);
    border-radius: 16px;
    padding: 28px 36px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
  }

  .bet-title h1 {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 2.1rem;
    color: #ffffff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .bet-title h1 i {
    color: var(--bet-danger);
    text-shadow: 0 0 15px rgba(255, 82, 82, 0.4);
  }

  .bet-subtitle {
    color: var(--bet-text-muted);
    margin-top: 6px;
    font-size: 0.95rem;
  }

  .credit-badge {
    background: rgba(0, 176, 255, 0.12);
    border: 1px solid rgba(0, 176, 255, 0.3);
    color: var(--bet-accent);
    padding: 8px 18px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  /* Grid de Métricas */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
  }

  .stat-card {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 14px;
    padding: 20px 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  }

  .stat-label {
    color: var(--bet-text-muted);
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 600;
    margin-bottom: 8px;
  }

  .stat-value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.8rem;
    font-weight: 700;
    color: #ffffff;
  }

  .stat-value.danger { color: var(--bet-danger); }
  .stat-value.accent { color: var(--bet-accent); }
  .stat-value.gold   { color: var(--bet-gold); }
  .stat-value.purple { color: var(--bet-purple); }

  /* Toolbar de Filtro de Datas e Ações */
  .bet-toolbar {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 14px;
    padding: 18px 24px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
  }

  .date-filter-form {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .date-filter-form input[type="date"] {
    background: #0d1117;
    border: 1px solid #30363d;
    color: #ffffff;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 0.88rem;
  }

  .btn-filter {
    background: #21262d;
    border: 1px solid #30363d;
    color: var(--bet-text-main);
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .btn-filter:hover {
    background: #30363d;
    color: var(--bet-primary);
  }

  .btn-consolidated-ai {
    background: linear-gradient(135deg, #ff5252 0%, #a78bfa 100%);
    color: #ffffff;
    font-weight: 700;
    font-family: 'Outfit', sans-serif;
    padding: 10px 22px;
    border-radius: 30px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(255, 82, 82, 0.3);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
  }

  .btn-consolidated-ai:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 82, 82, 0.5);
    color: #ffffff;
  }

  /* Match Loss Card */
  .loss-card {
    background: var(--bet-card-bg);
    border: 1px solid var(--bet-card-border);
    border-radius: 14px;
    margin-bottom: 24px;
    overflow: hidden;
    transition: border-color 0.2s ease;
  }

  .loss-card:hover {
    border-color: rgba(255, 82, 82, 0.4);
  }

  .loss-card-header {
    background: rgba(33, 38, 45, 0.6);
    padding: 14px 20px;
    border-bottom: 1px solid var(--bet-card-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .loss-card-body {
    padding: 20px;
  }

  .badge-status-red {
    background: rgba(255, 82, 82, 0.15);
    border: 1px solid var(--bet-danger);
    color: var(--bet-danger);
    font-weight: 700;
    font-size: 0.78rem;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
  }

  .badge-status-half-red {
    background: rgba(250, 204, 21, 0.15);
    border: 1px solid #facc15;
    color: #facc15;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
  }

  .target-section-box {
    background: rgba(15, 23, 42, 0.9);
    border-left: 4px solid var(--bet-accent);
    border-radius: 8px;
    padding: 14px 16px;
    margin-top: 14px;
    font-size: 0.82rem;
  }

  .ai-response-box {
    background: rgba(13, 17, 23, 0.95);
    border: 1px solid rgba(167, 139, 250, 0.3);
    border-radius: 10px;
    padding: 18px;
    margin-top: 16px;
    font-size: 0.88rem;
    line-height: 1.6;
    color: #e2e8f0;
  }

  .ai-response-box h1, .ai-response-box h2, .ai-response-box h3 {
    font-family: 'Outfit', sans-serif;
    color: var(--bet-accent);
    margin-top: 14px;
    margin-bottom: 8px;
    font-size: 1.1rem;
  }

  .ai-response-box ul, .ai-response-box ol {
    padding-left: 20px;
    margin-bottom: 10px;
  }

  .ai-response-box strong {
    color: var(--bet-gold);
  }

  .btn-ai-analyze {
    background: linear-gradient(135deg, #00b0ff 0%, #a78bfa 100%);
    color: #000000;
    font-weight: 700;
    font-family: 'Outfit', sans-serif;
    padding: 8px 18px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.82rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .btn-ai-analyze:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 15px rgba(0, 176, 255, 0.4);
    color: #000000;
  }
</style>

<div class="bet-container">
  <!-- Header Banner -->
  <div class="bet-header">
    <div class="bet-title">
      <h1><i class="bi bi-shield-x"></i> Diagnóstico de Simulações de Apostas Perdidas (IA)</h1>
      <div class="bet-subtitle">Exame crítico dos palpites encerrados em red confrontados com a seção temática do Card e refinamento de critérios</div>
    </div>
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <a href="<?= base_url('apostas') ?>" class="btn btn-outline-light btn-sm" style="border-radius: 20px; padding: 6px 16px;">
        <i class="bi bi-arrow-left"></i> Voltar à Gestão de Simulações de Apostas
      </a>
      <span class="credit-badge" title="Seu saldo atual de créditos Groq AI">
        <i class="bi bi-cpu-fill"></i> Créditos Groq: <strong id="lbl-user-credits"><?= $credits ?></strong>
      </span>
    </div>
  </div>

  <!-- Métricas Resumidas do Período -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Simulações de Apostas Perdidas</div>
      <div class="stat-value danger"><?= $totPerdidas ?></div>
      <div style="font-size: 0.75rem; color: var(--bet-text-muted); margin-top: 4px;">No período de <?= date('d/m/Y', strtotime($startDate)) ?> a <?= date('d/m/Y', strtotime($endDate)) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Montante Investido em Reds</div>
      <div class="stat-value gold">R$ <?= number_format($totInvestidoPerdas, 2, ',', '.') ?></div>
      <div style="font-size: 0.75rem; color: var(--bet-text-muted); margin-top: 4px;">Soma das stakes das simulações de apostas perdedoras</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Prejuízo Efetivo Acumulado</div>
      <div class="stat-value danger">R$ <?= number_format($prejuizoTotal, 2, ',', '.') ?></div>
      <div style="font-size: 0.75rem; color: var(--bet-text-muted); margin-top: 4px;">Considerando reembolsos parciais</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Mercado Mais Afetado</div>
      <div class="stat-value purple">
        <?php
        arsort($mercadosBreakdown);
        $topMerc = key($mercadosBreakdown);
        $labelsMerc = ['cartoes' => 'Cartões', 'handicap' => 'Handicap AH', 'gols' => 'Gols/BTTS', 'outros' => 'Outros'];
        echo $labelsMerc[$topMerc] ?? 'Diversos';
        ?>
      </div>
      <div style="font-size: 0.75rem; color: var(--bet-text-muted); margin-top: 4px;">
        Cartões: <?= $mercadosBreakdown['cartoes'] ?> | AH: <?= $mercadosBreakdown['handicap'] ?> | Gols: <?= $mercadosBreakdown['gols'] ?>
      </div>
    </div>
  </div>

  <!-- Toolbar de Filtro por Data e Botão Consolidado -->
  <div class="bet-toolbar">
    <form method="GET" action="<?= base_url('apostas/relatorio-ia-perdas') ?>" class="date-filter-form">
      <label style="font-size: 0.85rem; font-weight: 600; color: var(--bet-text-muted);">Período:</label>
      <select id="preset_ia_perdas" class="form-select form-select-sm bg-dark text-info border-secondary fw-semibold" style="width: auto; cursor: pointer; min-width: 140px;" onchange="applyIAPerdasPreset(this.value)">
        <option value="custom">📅 Personalizado</option>
        <option value="today">⚡ Hoje</option>
        <option value="yesterday">⏪ Ontem</option>
        <option value="7days">🗓️ Últimos 7 dias</option>
        <option value="15days">🗓️ Últimos 15 dias</option>
        <option value="1month">📅 Último mês</option>
        <option value="trimestre">📊 Trimestre</option>
        <option value="semestre">📈 Semestre</option>
        <option value="all">♾️ Todo o período</option>
      </select>
      <input type="date" name="start_date" id="ia_start_date" value="<?= htmlspecialchars($startDate) ?>" required>
      <span style="color: var(--bet-text-muted);">até</span>
      <input type="date" name="end_date" id="ia_end_date" value="<?= htmlspecialchars($endDate) ?>" required>
      <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill me-1"></i> Filtrar</button>
    </form>

    <?php if (!empty($apostasPerdidas)): ?>
      <button type="button" class="btn-consolidated-ai" onclick="executarAnaliseConsolidada('<?= htmlspecialchars($startDate) ?>', '<?= htmlspecialchars($endDate) ?>')">
        <i class="bi bi-cpu-fill"></i> Diagnóstico Global do Período (Groq IA)
      </button>
    <?php endif; ?>
  </div>

  <!-- Container de Resposta da Análise Consolidada -->
  <div id="box-consolidated-result" class="ai-response-box" style="display: none; margin-bottom: 30px; border-color: var(--bet-purple);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h3 style="margin: 0; color: var(--bet-purple);"><i class="bi bi-clipboard2-pulse-fill me-2"></i> Diagnóstico Consolidado do Período (Groq AI)</h3>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#box-consolidated-result').slideUp(200);">Fechar</button>
    </div>
    <div id="content-consolidated-markdown"></div>
  </div>

  <!-- Listagem de Cards de Apostas Perdidas -->
  <?php if (empty($apostasPerdidas)): ?>
    <div class="text-center py-5" style="background: var(--bet-card-bg); border-radius: 14px; border: 1px solid var(--bet-card-border);">
      <i class="bi bi-emoji-smile" style="font-size: 3rem; color: var(--bet-primary);"></i>
      <h3 class="mt-3 text-white">Nenhuma simulação de aposta perdida encontrada neste período!</h3>
      <p class="text-muted">Altere o intervalo de datas acima para consultar outros períodos.</p>
    </div>
  <?php else: ?>
    <?php foreach ($apostasPerdidas as $ap): ?>
      <?php
      $mercadoLower = strtolower(($ap->mercado ?? '') . ' ' . ($ap->palpite ?? ''));
      $tipoSecao = 'outros';
      if (strpos($mercadoLower, 'cart') !== false || strpos($mercadoLower, 'card') !== false || strpos($mercadoLower, 'amarelo') !== false) {
          $tipoSecao = 'cartoes';
      } elseif (strpos($mercadoLower, 'handicap') !== false || strpos($mercadoLower, 'ah') !== false || strpos($mercadoLower, 'empate anula') !== false || strpos($mercadoLower, 'dnb') !== false) {
          $tipoSecao = 'handicap';
      } elseif (strpos($mercadoLower, 'gol') !== false || strpos($mercadoLower, 'goal') !== false || strpos($mercadoLower, 'ambas') !== false || strpos($mercadoLower, 'btts') !== false) {
          $tipoSecao = 'gols';
      }
      ?>

      <div class="loss-card" id="card-loss-<?= $ap->id ?>">
        <!-- Loss Card Header -->
        <div class="loss-card-header">
          <div>
            <span class="badge bg-secondary me-2"><?= htmlspecialchars($ap->league_name ?? 'Futebol') ?></span>
            <strong style="font-size: 1.05rem; color: #ffffff;"><?= htmlspecialchars($ap->time_casa) ?> x <?= htmlspecialchars($ap->time_fora) ?></strong>
            <span style="font-size: 0.8rem; color: var(--bet-text-muted); margin-left: 10px;" title="Data e Hora da Partida">
              <i class="bi bi-calendar3 me-1"></i>Jogo: <?= date('d/m/Y H:i', strtotime($ap->data_hora_jogo)) ?>
            </span>
            <?php if (!empty($ap->criado_em)): ?>
              <span style="font-size: 0.8rem; color: var(--bet-accent); margin-left: 10px;" title="Data e Hora de Criação do Registro">
                <i class="bi bi-clock-history me-1"></i>Criado em: <?= date('d/m/Y H:i', strtotime($ap->criado_em)) ?>
              </span>
            <?php endif; ?>
          </div>
          <div>
            <span class="<?= ($ap->status === 'Meio Perdida') ? 'badge-status-half-red' : 'badge-status-red' ?>">
              <i class="bi bi-x-circle-fill me-1"></i><?= htmlspecialchars($ap->status) ?>
            </span>
          </div>
        </div>

        <!-- Loss Card Body -->
        <div class="loss-card-body">
          <div class="row g-3">
            <!-- Coluna 1: Dados da Simulação de Aposta Efetuada -->
            <div class="col-md-5" style="border-right: 1px solid var(--bet-card-border);">
              <h6 style="color: var(--bet-gold); font-size: 0.85rem; text-transform: uppercase; font-weight: 700;">
                <i class="bi bi-ticket-perforated-fill me-1"></i> Simulação de Aposta Efetuada
              </h6>
              <div style="background: rgba(30, 41, 59, 0.5); padding: 12px; border-radius: 8px; font-size: 0.85rem;">
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Mercado:</span>
                  <strong class="text-white"><?= htmlspecialchars($ap->mercado) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Palpite Simulado:</span>
                  <strong style="color: var(--bet-accent); font-size: 0.95rem;"><?= htmlspecialchars($ap->palpite) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Odd Simulada:</span>
                  <span class="badge bg-dark text-warning font-monospace"><?= number_format($ap->odd, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                  <span class="text-muted">Valor Simulado:</span>
                  <span class="text-white font-weight-bold">R$ <?= number_format($ap->valor_aposta, 2, ',', '.') ?></span>
                </div>
                <?php if (!empty($ap->resultado_detalhado)): ?>
                  <div class="mt-2 pt-2 border-top border-secondary text-danger" style="font-size: 0.78rem;">
                    <strong>Resultado Registrado:</strong> <?= htmlspecialchars($ap->resultado_detalhado) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Coluna 2: Seção Temática Relevante do Card do Jogo -->
            <div class="col-md-7">
              <h6 style="color: var(--bet-accent); font-size: 0.85rem; text-transform: uppercase; font-weight: 700;">
                <i class="bi bi-layers-fill me-1"></i> Seção do Card Confrontada (<?= strtoupper($tipoSecao) ?>)
              </h6>
              
              <div class="target-section-box">
                <?php if ($tipoSecao === 'cartoes'): ?>
                  <div class="d-flex justify-content-between mb-2">
                    <span><strong>Projeção $xC$ (Expectativa):</strong> <?= htmlspecialchars($ap->prediction_text ?? 'N/A') ?></span>
                    <span class="text-warning">Poisson: <?= htmlspecialchars($ap->over_cards_probability ?? 0) ?>%</span>
                  </div>
                  <div class="row text-center g-2 my-2" style="background: rgba(30, 41, 59, 0.7); padding: 6px; border-radius: 6px;">
                    <div class="col-4">
                      <small class="text-muted d-block">Méd. Cards Casa</small>
                      <strong class="text-warning"><?= number_format($ap->home_avg_cards ?? 0, 1) ?></strong>
                    </div>
                    <div class="col-4">
                      <small class="text-muted d-block">Árbitro Escalado</small>
                      <strong class="text-info"><?= htmlspecialchars($ap->referee_name ?? 'N/A') ?></strong>
                    </div>
                    <div class="col-4">
                      <small class="text-muted d-block">Méd. Cards Fora</small>
                      <strong class="text-warning"><?= number_format($ap->away_avg_cards ?? 0, 1) ?></strong>
                    </div>
                  </div>
                  <div class="text-muted" style="font-size: 0.75rem;">
                    <strong>Realidade do Jogo (Cartões Efetivos):</strong> <?= ($ap->yellow_cards_home ?? 0) ?> Amarelos / <?= ($ap->red_cards_home ?? 0) ?> Vermelhos (Casa) x <?= ($ap->yellow_cards_away ?? 0) ?> Amarelos / <?= ($ap->red_cards_away ?? 0) ?> Vermelhos (Fora)
                  </div>

                <?php elseif ($tipoSecao === 'handicap' || $tipoSecao === 'gols'): ?>
                  <div class="d-flex justify-content-between mb-2">
                    <span><strong>Sugestão Handicap AH:</strong> <?= htmlspecialchars($ap->ah_suggestion ?? 'N/A') ?></span>
                    <span class="text-info">Confiança: <?= number_format($ap->ah_confidence ?? 65, 1) ?>%</span>
                  </div>
                  <div class="row text-center g-2 my-2" style="background: rgba(30, 41, 59, 0.7); padding: 6px; border-radius: 6px;">
                    <div class="col-4">
                      <small class="text-muted d-block">Gols Casa (Pró/Contra)</small>
                      <strong class="text-white"><?= number_format($ap->home_avg_goals_scored ?? 0, 1) ?> / <?= number_format($ap->home_avg_goals_conceded ?? 0, 1) ?></strong>
                    </div>
                    <div class="col-4">
                      <small class="text-muted d-block">Placar Real do Jogo</small>
                      <strong class="text-warning font-monospace" style="font-size: 0.95rem;"><?= ($ap->ft_goals_home ?? $ap->goals_home ?? 0) ?> x <?= ($ap->ft_goals_away ?? $ap->goals_away ?? 0) ?></strong>
                    </div>
                    <div class="col-4">
                      <small class="text-muted d-block">Gols Fora (Pró/Contra)</small>
                      <strong class="text-white"><?= number_format($ap->away_avg_goals_scored ?? 0, 1) ?> / <?= number_format($ap->away_avg_goals_conceded ?? 0, 1) ?></strong>
                    </div>
                  </div>
                  <div class="text-muted" style="font-size: 0.75rem;">
                    <strong>Expected Goals ($xG$ Real):</strong> <?= number_format($ap->xg_home ?? 0, 2) ?> (Casa) x <?= number_format($ap->xg_away ?? 0, 2) ?> (Fora)
                  </div>

                <?php else: ?>
                  <div class="mb-2">
                    <strong>Dica Futbol24:</strong> <?= htmlspecialchars($ap->futbol24_tip ?? 'N/A') ?>
                  </div>
                  <div class="text-muted mb-2" style="font-size: 0.75rem;">
                    <?= htmlspecialchars($ap->futbol24_analysis ?? 'Sem análise editorial prévia.') ?>
                  </div>
                  <div class="text-white" style="font-size: 0.78rem;">
                    <strong>Estatísticas Reais:</strong> Placar <?= ($ap->goals_home ?? 0) ?>x<?= ($ap->goals_away ?? 0) ?> | Chutes: <?= ($ap->shots_home ?? 0) ?>-<?= ($ap->shots_away ?? 0) ?> | Escanteios: <?= ($ap->corners_home ?? 0) ?>-<?= ($ap->corners_away ?? 0) ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Botão Trigger de Análise por IA Groq -->
              <div class="mt-3 text-end">
                <button type="button" class="btn-ai-analyze" onclick="executarAnaliseIndividual(<?= $ap->id ?>, false)">
                  <i class="bi bi-cpu-fill"></i> <?= !empty($ap->analise_ia_perda) ? 'Ver / Atualizar Análise IA' : 'Analisar Perda com Groq IA' ?>
                </button>
              </div>
            </div>
          </div>

          <!-- Container da Análise Individual da IA (Carregamento AJAX) -->
          <div id="ai-result-box-<?= $ap->id ?>" class="ai-response-box" style="<?= empty($ap->analise_ia_perda) ? 'display: none;' : '' ?>">
            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary">
              <span class="text-info font-weight-bold" style="font-family: 'Outfit', sans-serif;">
                <i class="bi bi-robot me-1"></i> Análise Crítica Groq AI (Llama 3.3 70B)
                <?php if (!empty($ap->analise_ia_data)): ?>
                  <small class="text-muted ms-2">(Gerada em <?= date('d/m/Y H:i', strtotime($ap->analise_ia_data)) ?>)</small>
                <?php endif; ?>
              </span>
              <button type="button" class="btn btn-sm btn-outline-info" style="font-size: 0.7rem;" onclick="executarAnaliseIndividual(<?= $ap->id ?>, true)" title="Reanalisar gastando 1 crédito Groq">
                <i class="bi bi-arrow-repeat me-1"></i> Reanalisar
              </button>
            </div>
            <div id="ai-result-content-<?= $ap->id ?>">
              <?php if (!empty($ap->analise_ia_perda)): ?>
                <!-- Renderiza o Markdown salvo inicial se existir -->
                <script>
                  document.addEventListener('DOMContentLoaded', function() {
                    const rawMd = <?= json_encode($ap->analise_ia_perda) ?>;
                    document.getElementById('ai-result-content-<?= $ap->id ?>').innerHTML = marked.parse(rawMd);
                  });
                </script>
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
  // Função para executar a análise individual de cada aposta com Groq AI
  function executarAnaliseIndividual(apostaId, force) {
    const box = document.getElementById('ai-result-box-' + apostaId);
    const content = document.getElementById('ai-result-content-' + apostaId);

    box.style.display = 'block';
    content.innerHTML = '<div class="text-center py-4 text-info"><div class="spinner-border spinner-border-sm me-2" role="status"></div> O Groq AI está confrontando o palpite com o Card e analisando o motivo da perda...</div>';

    $.ajax({
      url: '<?= base_url('apostas/analisar-perda-ia') ?>',
      type: 'POST',
      data: {
        aposta_id: apostaId,
        force: force ? '1' : '0'
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          content.innerHTML = marked.parse(res.analise);
          if (res.credits_left !== undefined) {
            document.getElementById('lbl-user-credits').innerText = res.credits_left;
          }
        } else {
          content.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> ' + res.message + '</div>';
        }
      },
      error: function() {
        content.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Erro de comunicação com o servidor. Tente novamente.</div>';
      }
    });
  }

  // Função para executar a análise consolidada do período com Groq AI
  function executarAnaliseConsolidada(startDate, endDate) {
    const box = document.getElementById('box-consolidated-result');
    const content = document.getElementById('content-consolidated-markdown');

    box.style.display = 'block';
    content.innerHTML = '<div class="text-center py-4 text-purple"><div class="spinner-border spinner-border-sm me-2" role="status"></div> O Groq AI está examinando todas as apostas perdidas do período e gerando o relatório consolidado...</div>';

    $('html, body').animate({ scrollTop: $(box).offset().top - 100 }, 400);

    $.ajax({
      url: '<?= base_url('apostas/analisar-perdas-consolidado-ia') ?>',
      type: 'POST',
      data: {
        start_date: startDate,
        end_date: endDate
      },
      dataType: 'json',
      success: function(res) {
        if (res.success) {
          content.innerHTML = marked.parse(res.analise);
          if (res.credits_left !== undefined) {
            document.getElementById('lbl-user-credits').innerText = res.credits_left;
          }
        } else {
          content.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> ' + res.message + '</div>';
        }
      },
      error: function() {
        content.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Erro de comunicação com o servidor ao gerar análise consolidada.</div>';
      }
    });
  }

  function applyIAPerdasPreset(presetKey) {
    const startEl = document.getElementById('ia_start_date');
    const endEl = document.getElementById('ia_end_date');
    if (!startEl || !endEl) return;

    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const todayStr = `${year}-${month}-${day}`;

    function getPastDate(m) {
      const d = new Date();
      const tm = d.getMonth() - m;
      d.setMonth(tm);
      if (d.getMonth() !== ((tm % 12 + 12) % 12)) d.setDate(0);
      return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    }

    if (presetKey === 'today') {
      startEl.value = todayStr; endEl.value = todayStr;
    } else if (presetKey === 'yesterday') {
      const y = new Date(); y.setDate(y.getDate() - 1);
      const yStr = `${y.getFullYear()}-${String(y.getMonth()+1).padStart(2,'0')}-${String(y.getDate()).padStart(2,'0')}`;
      startEl.value = yStr; endEl.value = yStr;
    } else if (presetKey === '7days') {
      const s = new Date(); s.setDate(s.getDate() - 6);
      startEl.value = `${s.getFullYear()}-${String(s.getMonth()+1).padStart(2,'0')}-${String(s.getDate()).padStart(2,'0')}`;
      endEl.value = todayStr;
    } else if (presetKey === '15days') {
      const s = new Date(); s.setDate(s.getDate() - 14);
      startEl.value = `${s.getFullYear()}-${String(s.getMonth()+1).padStart(2,'0')}-${String(s.getDate()).padStart(2,'0')}`;
      endEl.value = todayStr;
    } else if (presetKey === '1month') {
      startEl.value = getPastDate(1); endEl.value = todayStr;
    } else if (presetKey === 'trimestre') {
      startEl.value = getPastDate(3); endEl.value = todayStr;
    } else if (presetKey === 'semestre') {
      startEl.value = getPastDate(6); endEl.value = todayStr;
    } else if (presetKey === 'all') {
      startEl.value = ''; endEl.value = '';
    }
  }
</script>
