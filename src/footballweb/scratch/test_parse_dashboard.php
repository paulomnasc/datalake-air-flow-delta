<?php
$pdo = new PDO('mysql:host=mysql;dbname=footballweb;charset=utf8mb4', 'root', 'YM11rMrT32xH0E6N');
$fixtures = [1635628, 1552142, 1629830, 1603024];

foreach ($fixtures as $fid) {
    $stmt = $pdo->prepare('SELECT fixture_id, home_team, away_team, ah_suggestion, ah_confidence, ah_reasoning FROM fixtures_trends WHERE fixture_id = ?');
    $stmt->execute([$fid]);
    $fix = $stmt->fetch(PDO::FETCH_OBJ);
    if (!$fix) continue;

    echo "=====================================================\n";
    echo "Fixture: {$fix->fixture_id} - {$fix->home_team} vs {$fix->away_team}\n";
    echo "ah_suggestion: {$fix->ah_suggestion}\n";
    echo "ah_confidence: {$fix->ah_confidence}\n";

    $ahSugClean = strtolower(trim($fix->ah_suggestion ?? ''));
    $isAhBlocked = empty($ahSugClean) 
        || stripos($ahSugClean, 'sem entrada') !== false 
        || stripos($ahSugClean, 'abstenção') !== false 
        || stripos($ahSugClean, 'abstencao') !== false 
        || stripos($ahSugClean, 'bloquead') !== false 
        || stripos($ahSugClean, 'indisponível') !== false 
        || stripos($ahSugClean, 'indisponivel') !== false;

    echo "Dashboard Badge: " . ($isAhBlocked ? '🚫 Vermelho (AH Bloqueado / Abstenção)' : '🛡️ Azul (' . $fix->ah_suggestion . ')') . "\n";

    $u5j_data = null;
    if (!empty($fix->ah_reasoning) && strpos($fix->ah_reasoning, 'U5J_DATA:') !== false) {
        $parts = explode('U5J_DATA:', $fix->ah_reasoning);
        $u5j_data = json_decode(trim($parts[1]), true);
    }
    echo "U5J Data present?: " . ($u5j_data !== null ? "SIM (Home: {$u5j_data['home']['text']}, Away: {$u5j_data['away']['text']})" : "NAO") . "\n";
}
