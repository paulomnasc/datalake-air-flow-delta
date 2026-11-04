<?php
/**
 * View Parcial: Card de Origem da Aposta para Exibição em Modal Sobreposto (Overlay Modal View)
 * 
 * Renderiza os fundamentos completos da partida gravados em fixtures_trends:
 * - Metadados, Ligas, Bandeira, Data/Hora, Placar e Status;
 * - Odds 1X2 e probabilidade com barra de progresso;
 * - Sugestão de Handicap Asiático com Timeline U5J e Parecer Tático da IA;
 * - Sugestão de Cartões e Rigor do Árbitro;
 * - Médias Móveis de Gols, Escanteios e Cartões;
 * - Resenha / Análise Editorial (Futbol24).
 */

if (!function_exists('renderU5JTimelineTableModal')) {
    function renderU5JTimelineTableModal($u5j_data, $fix) {
        if (empty($u5j_data)) return '';

        $teams = [
            'home' => [
                'icon' => '🏠',
                'name' => $fix->home_team ?? 'Mandante',
                'color' => '#38bdf8',
                'bg_header' => 'rgba(56, 189, 248, 0.12)',
                'border_color' => 'rgba(56, 189, 248, 0.25)',
                'data' => $u5j_data['home'] ?? []
            ],
            'away' => [
                'icon' => '✈️',
                'name' => $fix->away_team ?? 'Visitante',
                'color' => '#a78bfa',
                'bg_header' => 'rgba(167, 139, 250, 0.12)',
                'border_color' => 'rgba(167, 139, 250, 0.25)',
                'data' => $u5j_data['away'] ?? []
            ]
        ];

        ob_start();
        ?>
        <div style="margin-top: 10px; padding: 10px; background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px;">
            <div style="font-size: 0.74rem; font-weight: 700; color: #fbbf24; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                <span><i class="bi bi-clock-history me-1"></i> Linha do Tempo Recente (Últimos 5 Confrontos - U5J)</span>
                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: normal;">
                    <i class="bi bi-arrow-right text-warning me-1"></i>Antigo ➔ Mais Recente
                </span>
            </div>

            <?php foreach ($teams as $tKey => $tInfo): ?>
                <?php 
                    $tData = $tInfo['data'];
                    $matches = $tData['matches'] ?? [];
                    $formText = $tData['text'] ?? '0V-0E-0D';
                    $cleanFormText = trim(preg_replace('/\s*\(.*?\)$/i', '', $formText));
                    $ptsEff = !empty($tData['pts_efficiency']) ? (float)$tData['pts_efficiency'] : null;
                    $trendLabel = $tData['trend_label'] ?? '';
                ?>
                <div style="margin-bottom: 8px; padding: 6px 8px; background: rgba(30, 41, 59, 0.6); border-radius: 6px; border-left: 3px solid <?= $tInfo['color'] ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 4px;">
                        <span style="font-weight: 700; color: <?= $tInfo['color'] ?>; font-size: 0.75rem;">
                            <?= $tInfo['icon'] ?> <?= htmlspecialchars($tInfo['name']) ?>
                        </span>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span class="badge" style="background: rgba(255,255,255,0.06); color: #e2e8f0; font-size: 0.68rem; border: 1px solid rgba(255,255,255,0.15);">
                                Forma: <?= htmlspecialchars($cleanFormText) ?>
                            </span>
                            <?php if ($ptsEff !== null): ?>
                                <span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #facc15; font-size: 0.68rem; border: 1px solid rgba(234, 179, 8, 0.4);">
                                    <?= number_format($ptsEff, 1) ?> pts ef.
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($trendLabel)): ?>
                                <span style="font-size: 0.68rem; color: #94a3b8;"><?= htmlspecialchars($trendLabel) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($matches)): ?>
                        <div style="display: flex; gap: 4px; overflow-x: auto; padding-bottom: 2px;">
                            <?php foreach ($matches as $m): ?>
                                <?php 
                                    $res = strtoupper($m['result'] ?? 'E');
                                    $resBg = ($res === 'V') ? '#10b981' : (($res === 'D') ? '#ef4444' : '#64748b');
                                    $opp = $m['opponent'] ?? 'Adv';
                                    $score = $m['score'] ?? '-';
                                    $isHome = !empty($m['is_home']);
                                    $isT1 = !empty($m['is_tier_1']);
                                ?>
                                <div style="flex: 1; min-width: 58px; background: rgba(15, 23, 42, 0.7); border: 1px solid <?= $isT1 ? 'rgba(250, 204, 21, 0.5)' : 'rgba(255, 255, 255, 0.08)' ?>; border-radius: 4px; padding: 3px 4px; text-align: center; font-size: 0.66rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                        <span style="font-size: 0.60rem; color: #94a3b8;"><?= $isHome ? 'C' : 'F' ?></span>
                                        <span class="badge" style="background: <?= $resBg ?>; color: #ffffff; padding: 1px 3px; font-size: 0.58rem; font-weight: 700; border-radius: 3px;"><?= $res ?></span>
                                    </div>
                                    <div style="font-weight: 700; color: #ffffff; font-size: 0.68rem;"><?= htmlspecialchars($score) ?></div>
                                    <div style="font-size: 0.60rem; color: #cbd5e1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($opp) ?><?= $isT1 ? ' (Tier 1)' : '' ?>">
                                        <?= $isT1 ? '⭐ ' : '' ?><?= htmlspecialchars($opp) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Resolução de dados do Fixture
$displayTz = $userTimezone ?? 'America/Sao_Paulo';
$timeStr = '--:--';
$dateStr = '--/--/----';
try {
    $dt = new DateTime($fix->fixture_date, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone($displayTz));
    $timeStr = $dt->format('H:i');
    $dateStr = $dt->format('d/m/Y');
} catch (\Exception $e) {}

$displayLeague = $fix->league_name ?? 'Liga';
$prob = !empty($fix->win_probability) ? (float)$fix->win_probability : 50.0;
$class = ($prob >= 70) ? 'high' : (($prob >= 55) ? 'safe' : 'risky');

// Extração de U5J
$u5j_data = null;
if (!empty($fix->ah_reasoning) && preg_match('/U5J_DATA:\s*(\{.*?\})\s*(?:\|\||$)/s', $fix->ah_reasoning, $mU5)) {
    $u5j_data = json_decode($mU5[1], true);
}

// Extração de Motivação e Explicação
$nl_explanation = '';
if (!empty($fix->ah_reasoning) && preg_match('/EXPLICACAO:\s*([^\|]+)/u', $fix->ah_reasoning, $mExp)) {
    $nl_explanation = trim($mExp[1]);
} elseif (!empty($fix->ah_reasoning) && preg_match('/REASON:\s*([^\|]+)/u', $fix->ah_reasoning, $mRea)) {
    $nl_explanation = trim($mRea[1]);
}

$motivation = '';
if (!empty($fix->ah_reasoning) && preg_match('/MOTIVACAO:\s*([^\|]+)/u', $fix->ah_reasoning, $mMot)) {
    $motivation = trim($mMot[1]);
}

$isCardPremium = (!empty($fix->destaque) && (int)$fix->destaque === 2) || 
                 (stripos($fix->gatekeeper_category ?? '', 'PREMIUM') !== false) ||
                 (stripos($fix->ah_reasoning ?? '', 'PREMIUM') !== false);
?>

<div class="card-origem-modal-wrapper" style="color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <style>
    .card-origem-modal-wrapper .stat-badge-box {
      background: rgba(30, 41, 59, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 8px;
      padding: 8px 10px;
      text-align: center;
    }
    .card-origem-modal-wrapper .stat-badge-title {
      font-size: 0.68rem;
      color: #94a3b8;
      display: block;
      margin-bottom: 2px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .card-origem-modal-wrapper .stat-badge-val {
      font-size: 0.95rem;
      font-weight: 800;
      font-family: monospace;
    }
    .card-origem-modal-wrapper .prob-bar-container {
      background: rgba(15, 23, 42, 0.8);
      height: 8px;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 4px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .card-origem-modal-wrapper .prob-bar-fill {
      height: 100%;
      border-radius: 4px;
      transition: width 0.5s ease;
    }
    .card-origem-modal-wrapper .prob-bar-fill.high { background: linear-gradient(90deg, #10b981, #059669); }
    .card-origem-modal-wrapper .prob-bar-fill.safe { background: linear-gradient(90deg, #38bdf8, #0284c7); }
    .card-origem-modal-wrapper .prob-bar-fill.risky { background: linear-gradient(90deg, #f59e0b, #d97706); }
  </style>

  <!-- Cabeçalho da Partida -->
  <div class="p-3 mb-3 rounded-3" style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%); border: 1px solid rgba(56, 189, 248, 0.3); box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: rgba(56, 189, 248, 0.2); border: 1px solid rgba(56, 189, 248, 0.4); color: #38bdf8; font-size: 0.75rem; font-weight: 700; padding: 4px 8px;">
          <?= htmlspecialchars($displayLeague) ?>
        </span>
        <?php if ($isCardPremium): ?>
          <span class="badge border px-2 py-1 fw-bold d-inline-flex align-items-center gap-1 shadow-sm" 
                style="font-size: 0.74rem; background: linear-gradient(135deg, rgba(245, 158, 11, 0.45) 0%, rgba(217, 119, 6, 0.35) 100%) !important; color: #fbbf24 !important; border-color: #f59e0b !important; letter-spacing: 0.3px;">
            <span>👑</span> ⭐ Aposta PREMIUM
          </span>
        <?php elseif (!empty($fix->destaque)): ?>
          <span class="badge border px-2 py-1 fw-bold d-inline-flex align-items-center gap-1 shadow-sm" 
                style="font-size: 0.74rem; background: rgba(234, 179, 8, 0.25) !important; color: #facc15 !important; border-color: rgba(250, 204, 21, 0.6) !important;">
            <span>⭐</span> Tier 1 Dominante
          </span>
        <?php endif; ?>
      </div>

      <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #cbd5e1; font-size: 0.72rem;">
          <i class="bi bi-calendar3 me-1"></i> <?= $dateStr ?> • <i class="bi bi-clock me-1"></i> <?= $timeStr ?>
        </span>
        <span class="badge" style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; font-size: 0.72rem; font-weight: 700;">
          Status: <?= htmlspecialchars($fix->status ?? 'NS') ?>
        </span>
      </div>
    </div>

    <!-- Times e Placar com Escudos -->
    <div class="row align-items-center text-center my-3 g-2">
      <div class="col-5 text-end">
        <div class="d-flex align-items-center justify-content-end gap-2">
          <div>
            <strong class="text-white fs-6 d-block"><?= htmlspecialchars($fix->home_team) ?></strong>
            <span class="text-white-50 small" style="font-size: 0.70rem;">Mandante</span>
          </div>
          <?php if (!empty($fix->home_team_id)): ?>
            <img src="<?= base_url('team-logo/' . $fix->home_team_id) ?>" alt="" style="width: 32px; height: 32px; object-fit: contain; flex-shrink: 0;" onerror="this.style.display='none';">
          <?php endif; ?>
        </div>
      </div>
      <div class="col-2 text-center">
        <?php if (!empty($fix->home_score) || !empty($fix->away_score) || in_array($fix->status, ['FT', '1H', '2H', 'HT', 'LIVE'], true)): ?>
          <span class="badge bg-warning text-dark font-monospace fw-bold fs-6 px-2 py-1">
            <?= $fix->home_score ?? 0 ?> x <?= $fix->away_score ?? 0 ?>
          </span>
        <?php else: ?>
          <span class="badge bg-secondary text-white font-monospace px-2 py-1" style="font-size: 0.8rem;">VS</span>
        <?php endif; ?>
      </div>
      <div class="col-5 text-start">
        <div class="d-flex align-items-center justify-content-start gap-2">
          <?php if (!empty($fix->away_team_id)): ?>
            <img src="<?= base_url('team-logo/' . $fix->away_team_id) ?>" alt="" style="width: 32px; height: 32px; object-fit: contain; flex-shrink: 0;" onerror="this.style.display='none';">
          <?php endif; ?>
          <div>
            <strong class="text-white fs-6 d-block"><?= htmlspecialchars($fix->away_team) ?></strong>
            <span class="text-white-50 small" style="font-size: 0.70rem;">Visitante</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Widget Eficiência U5J (Twin Progress Bars) -->
    <?php 
      $mEffHome = null;
      $mEffAway = null;
      if (!empty($u5j_data['home'])) {
        $mEffHome = (isset($u5j_data['home']['pts_efficiency']) && is_numeric($u5j_data['home']['pts_efficiency']))
          ? floatval($u5j_data['home']['pts_efficiency']) 
          : floatval($u5j_data['home']['pts'] ?? 0);
      }
      if (!empty($u5j_data['away'])) {
        $mEffAway = (isset($u5j_data['away']['pts_efficiency']) && is_numeric($u5j_data['away']['pts_efficiency']))
          ? floatval($u5j_data['away']['pts_efficiency']) 
          : floatval($u5j_data['away']['pts'] ?? 0);
      }
    ?>
    <?php if ($mEffHome !== null || $mEffAway !== null): ?>
      <?php
        $mValHome = max(0.0, floatval($mEffHome ?? 0.0));
        $mValAway = max(0.0, floatval($mEffAway ?? 0.0));
        $mMaxRef = max(35.0, max($mValHome, $mValAway));
        $mBarPctHome = min(100, max(6, round(($mValHome / $mMaxRef) * 100)));
        $mBarPctAway = min(100, max(6, round(($mValAway / $mMaxRef) * 100)));
      ?>
      <div class="p-2 mb-3 rounded-2" style="background: rgba(15, 23, 42, 0.75); border: 1px solid rgba(255, 255, 255, 0.08);">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span style="font-size: 0.70rem; font-weight: 700; color: #fbbf24; display: flex; align-items: center; gap: 4px;">
            <i class="bi bi-speedometer2"></i> Eficiência U5J
          </span>
          <span style="font-size: 0.62rem; color: #94a3b8;">Força Relativa Recente</span>
        </div>
        <!-- Mandante -->
        <div class="d-flex align-items-center gap-2 mb-1">
          <span style="font-size: 0.68rem; color: #38bdf8; font-weight: 700; width: 75px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($fix->home_team) ?>">
            🏠 <?= htmlspecialchars(mb_substr($fix->home_team, 0, 9)) ?>
          </span>
          <div style="flex: 1; height: 6px; background: rgba(255, 255, 255, 0.08); border-radius: 3px; overflow: hidden;">
            <div style="width: <?= $mBarPctHome ?>%; height: 100%; background: linear-gradient(90deg, #0284c7, #38bdf8); border-radius: 3px;"></div>
          </div>
          <span style="font-size: 0.74rem; font-weight: 800; color: #38bdf8; min-width: 58px; text-align: right; font-family: monospace;">
            <?= number_format($mValHome, 1) ?> <span style="font-size: 0.58rem; font-weight: 400; color: #94a3b8;">pts</span>
          </span>
        </div>
        <!-- Visitante -->
        <div class="d-flex align-items-center gap-2">
          <span style="font-size: 0.68rem; color: #a78bfa; font-weight: 700; width: 75px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($fix->away_team) ?>">
            ✈️ <?= htmlspecialchars(mb_substr($fix->away_team, 0, 9)) ?>
          </span>
          <div style="flex: 1; height: 6px; background: rgba(255, 255, 255, 0.08); border-radius: 3px; overflow: hidden;">
            <div style="width: <?= $mBarPctAway ?>%; height: 100%; background: linear-gradient(90deg, #7c3aed, #a78bfa); border-radius: 3px;"></div>
          </div>
          <span style="font-size: 0.74rem; font-weight: 800; color: #a78bfa; min-width: 58px; text-align: right; font-family: monospace;">
            <?= number_format($mValAway, 1) ?> <span style="font-size: 0.58rem; font-weight: 400; color: #94a3b8;">pts</span>
          </span>
        </div>
      </div>
    <?php endif; ?>

    <!-- Cotações 1X2 e Probabilidade -->
    <div class="p-2 rounded-2" style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255, 255, 255, 0.08);">
      <div class="d-flex justify-content-between align-items-center font-monospace small px-2">
        <span style="color: #38bdf8;">1 (Casa): <strong><?= number_format((float)($fix->odd_home ?? 0), 2) ?></strong></span>
        <span style="color: #94a3b8;">X (Empate): <strong><?= number_format((float)($fix->odd_draw ?? 0), 2) ?></strong></span>
        <span style="color: #a78bfa;">2 (Fora): <strong><?= number_format((float)($fix->odd_away ?? 0), 2) ?></strong></span>
      </div>
      <div class="mt-2 px-1">
        <div class="d-flex justify-content-between align-items-center" style="font-size: 0.70rem; color: #94a3b8;">
          <span>Confiança / Probabilidade Projetada</span>
          <strong class="text-info"><?= number_format($prob, 1) ?>%</strong>
        </div>
        <div class="prob-bar-container">
          <div class="prob-bar-fill <?= $class ?>" style="width: <?= min(100, max(10, $prob)) ?>%;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sugestão do Mercado de Handicap Asiático -->
  <?php if (!empty($fix->ah_suggestion)): ?>
    <div class="p-3 mb-3 rounded-3" style="background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(16, 185, 129, 0.35); border-left: 5px solid #10b981;">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-shield-shaded text-success fs-5"></i>
          <div>
            <strong class="text-white d-block" style="font-size: 0.88rem;">Handicap Asiático Recomendado</strong>
            <span class="text-success small fw-semibold">🎯 Sugestão: <?= htmlspecialchars($fix->ah_suggestion) ?></span>
          </div>
        </div>
        <?php if (!empty($fix->gatekeeper_category)): ?>
          <span class="badge" style="background: rgba(16, 185, 129, 0.18); border: 1px solid #10b981; color: #34d399; font-size: 0.72rem; font-weight: 700; padding: 4px 8px;">
            <?= htmlspecialchars($fix->gatekeeper_category) ?>
          </span>
        <?php endif; ?>
      </div>

      <?php if (!empty($nl_explanation)): ?>
        <div class="p-2.5 rounded-2 mb-2" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); font-size: 0.76rem; color: #e2e8f0; line-height: 1.45;">
          <div style="font-weight: 700; color: #34d399; margin-bottom: 2px;">
            <i class="bi bi-chat-left-text-fill me-1"></i> Explicação em Linguagem Natural:
          </div>
          <div><?= htmlspecialchars($nl_explanation) ?></div>
        </div>
      <?php endif; ?>

      <!-- Linha do Tempo U5J -->
      <?= renderU5JTimelineTableModal($u5j_data, $fix) ?>

      <?php if (!empty($motivation)): ?>
        <div class="p-2.5 mt-2 rounded-2" style="background: rgba(56, 189, 248, 0.06); border: 1px solid rgba(56, 189, 248, 0.2); font-size: 0.74rem; color: #cbd5e1; line-height: 1.4;">
          <strong class="text-info d-block mb-1"><i class="bi bi-lightbulb-fill me-1"></i> Motivação Tática da Seleção:</strong>
          <div><?= htmlspecialchars($motivation) ?></div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Sugestão do Mercado de Cartões & Árbitro -->
  <div class="row g-2 mb-3">
    <!-- Cartões -->
    <div class="col-12 col-md-6">
      <div class="p-3 h-100 rounded-3" style="background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(245, 158, 11, 0.3); border-left: 4px solid #f59e0b;">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="bi bi-file-square-fill text-warning"></i>
          <strong class="text-white" style="font-size: 0.84rem;">Mercado de Cartões</strong>
        </div>
        <div class="mb-2">
          <span class="text-white-50 small d-block" style="font-size: 0.70rem;">Palpite Projetado:</span>
          <strong class="text-warning" style="font-size: 0.88rem;"><?= htmlspecialchars($fix->prediction_text ?? 'Sem palpite de cartões') ?></strong>
        </div>
        <div class="d-flex gap-2">
          <div class="stat-badge-box flex-fill">
            <span class="stat-badge-title">Gols Esperados</span>
            <span class="stat-badge-val text-success">
              <?= number_format(((float)($fix->home_avg_goals_scored ?? 0) + (float)($fix->away_avg_goals_scored ?? 0)), 2) ?>
            </span>
          </div>
          <div class="stat-badge-box flex-fill">
            <span class="stat-badge-title">Escanteios</span>
            <span class="stat-badge-val text-info">
              <?= number_format(((float)($fix->home_avg_corners ?? 0) + (float)($fix->away_avg_corners ?? 0)), 1) ?>
            </span>
          </div>
          <div class="stat-badge-box flex-fill">
            <span class="stat-badge-title">Cartões Total</span>
            <span class="stat-badge-val text-warning">
              <?= number_format(((float)($fix->home_avg_cards ?? 0) + (float)($fix->away_avg_cards ?? 0)), 1) ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Árbitro -->
    <div class="col-12 col-md-6">
      <div class="p-3 h-100 rounded-3" style="background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(129, 140, 248, 0.3); border-left: 4px solid #818cf8;">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-1.5">
            <i class="bi bi-person-badge-fill text-primary"></i>
            <strong class="text-white" style="font-size: 0.84rem;">Árbitro da Partida</strong>
          </div>
          <?php if (!empty($fix->rigor_level)): ?>
            <span class="badge" style="background: rgba(129, 140, 248, 0.2); border: 1px solid #818cf8; color: #a5b4fc; font-size: 0.68rem;">
              <?= htmlspecialchars($fix->rigor_level) ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="mb-2">
          <span class="text-white-50 small d-block" style="font-size: 0.70rem;">Nome:</span>
          <strong class="text-white" style="font-size: 0.85rem;"><?= htmlspecialchars($fix->referee_name ?? 'Árbitro Não Informado') ?></strong>
        </div>
        <div class="d-flex gap-2">
          <div class="stat-badge-box flex-fill">
            <span class="stat-badge-title">Amarelos/J</span>
            <span class="stat-badge-val text-warning"><?= number_format((float)($fix->average_yellow_cards ?? 0), 2) ?></span>
          </div>
          <div class="stat-badge-box flex-fill">
            <span class="stat-badge-title">Vermelhos/J</span>
            <span class="stat-badge-val text-danger"><?= number_format((float)($fix->average_red_cards ?? 0), 2) ?></span>
          </div>
          <div class="stat-badge-box flex-fill">
            <span class="stat-badge-title">Faltas/J</span>
            <span class="stat-badge-val text-info"><?= number_format((float)($fix->average_fouls ?? 0), 1) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Resenha e Análise Editorial (Futbol24) se houver -->
  <?php if (!empty($fix->futbol24_tip) || !empty($fix->futbol24_analysis)): ?>
    <div class="p-3 mb-2 rounded-3" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px;">
      <div class="d-flex align-items-center gap-1.5 mb-2">
        <i class="bi bi-chat-quote-fill text-success"></i>
        <strong class="text-success" style="font-size: 0.82rem;">Resenha Editorial Esportiva</strong>
      </div>
      <?php if (!empty($fix->futbol24_tip)): ?>
        <div class="mb-2" style="font-size: 0.76rem; color: #f8fafc;">
          📌 <strong>Dica Editorial:</strong> <?= htmlspecialchars($fix->futbol24_tip) ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($fix->futbol24_analysis)): ?>
        <div style="font-size: 0.74rem; color: #cbd5e1; line-height: 1.45; white-space: pre-line; background: rgba(15, 23, 42, 0.6); padding: 8px 10px; border-radius: 6px;">
          <?= htmlspecialchars($fix->futbol24_analysis) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

