<?php
define('FCPATH', '/var/www/html/public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

class CI_Bootstrapper extends CodeIgniter\Boot {
    public static function setup($paths) {
        static::definePathConstants($paths);
        if (! defined('APP_NAMESPACE')) {
            static::loadConstants();
        }
        static::checkMissingExtensions();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadEnvironmentBootstrap($paths);
        static::loadCommonFunctions();
        static::loadAutoloader();
        static::setExceptionHandler();
        static::initializeKint();
        static::autoloadHelpers();
        return static::initializeCodeIgniter();
    }
}

$app = CI_Bootstrapper::setup($paths);

$controller = new \App\Controllers\ApostaController();
$reflector = new ReflectionClass($controller);
$method = $reflector->getMethod('evaluateGatekeeper');
$method->setAccessible(true);

$db = \Config\Database::connect();

echo "=========================================================\n";
echo "TESTE 1: BARCELONA VS FEYENOORD (Tier 1 Super-Favorito)\n";
echo "=========================================================\n";
$fixtureBarca = $db->table('fixtures_trends')->like('home_team', 'Barcelona')->orderBy('fixture_date', 'DESC')->get()->getRow();
$fixtureIdBarca = $fixtureBarca ? (int)$fixtureBarca->fixture_id : 1635628;
$resBarca = $method->invoke($controller, $fixtureIdBarca, 'Barcelona', 'Feyenoord', 'Handicap Asiático', 'Barcelona -1.0 AH', 1.55);
echo "Status Gatekeeper (Barcelona -1.0 AH): " . $resBarca['statusGatekeeper'] . "\n";
echo "Odd Justa: " . $resBarca['oddJusta'] . " | Prob: " . $resBarca['probPoisson'] . "% | EV: " . $resBarca['evPercentual'] . "%\n";
echo "Msg: " . $resBarca['gatekeeperMsg'] . "\n\n";

echo "=========================================================\n";
echo "TESTE 2: TWENTE VS TELSTAR (Time Não-Tier 1 / Favorito Médio)\n";
echo "=========================================================\n";
$fixtureTwente = $db->table('fixtures_trends')->like('home_team', 'Twente')->orderBy('fixture_date', 'DESC')->get()->getRow();
$fixtureIdTwente = $fixtureTwente ? (int)$fixtureTwente->fixture_id : 1552142;
$resTwente = $method->invoke($controller, $fixtureIdTwente, 'Twente', 'Telstar', 'Handicap Asiático', 'Twente -1.0 AH', 1.83);
echo "Status Gatekeeper (Twente -1.0 AH): " . $resTwente['statusGatekeeper'] . "\n";
echo "Msg: " . $resTwente['gatekeeperMsg'] . "\n\n";

echo "=========================================================\n";
echo "TESTE 3: BOCA JUNIORS VS SÃO PAULO (Zebra +AH em Mando Consagrado)\n";
echo "=========================================================\n";
$fixtureBoca = $db->table('fixtures_trends')->like('home_team', 'Boca')->orderBy('fixture_date', 'DESC')->get()->getRow();
$fixtureIdBoca = $fixtureBoca ? (int)$fixtureBoca->fixture_id : 1629830;
$resBoca = $method->invoke($controller, $fixtureIdBoca, 'Boca Juniors', 'São Paulo', 'Handicap Asiático', 'São Paulo +0.50 AH', 1.95);
echo "Status Gatekeeper (São Paulo +0.50 AH): " . $resBoca['statusGatekeeper'] . "\n";
echo "Msg: " . $resBoca['gatekeeperMsg'] . "\n\n";

echo "=========================================================\n";
echo "TESTE 4: VALIDAÇÃO DE PROTEÇÃO DE HOMÔNIMOS POR TEAM_ID\n";
echo "=========================================================\n";
$mTier1 = $reflector->getMethod('isTier1EliteClub');
$mTier1->setAccessible(true);
$testsHomonyms = [
    ['id' => 529, 'name' => 'Barcelona', 'expected' => true, 'desc' => 'Barcelona (Espanha)'],
    ['id' => 1152, 'name' => 'Barcelona SC', 'expected' => false, 'desc' => 'Barcelona SC (Equador)'],
    ['id' => 40, 'name' => 'Liverpool', 'expected' => true, 'desc' => 'Liverpool (Inglaterra)'],
    ['id' => 2358, 'name' => 'Liverpool Montevideo', 'expected' => false, 'desc' => 'Liverpool Montevideo (Uruguai)'],
    ['id' => 415, 'name' => 'Twente', 'expected' => false, 'desc' => 'Twente (Holanda)'],
    ['id' => 451, 'name' => 'Boca Juniors', 'expected' => true, 'desc' => 'Boca Juniors (Argentina)'],
    ['id' => 127, 'name' => 'Flamengo', 'expected' => true, 'desc' => 'Flamengo (Brasil)'],
];

foreach ($testsHomonyms as $t) {
    $res = $mTier1->invoke($controller, $t['id'], $t['name']);
    $pass = ($res === $t['expected']) ? "✅ PASS" : "❌ FAIL";
    echo "{$pass}: {$t['desc']} (ID {$t['id']}) -> " . ($res ? 'TIER 1' : 'NÃO TIER 1') . "\n";
}

echo "\n=========================================================\n";
echo "TESTE 5: AL-FATEH VS AL DIRIYAH (Trava de Time em Crise 0V)\n";
echo "=========================================================\n";
$resFateh = $method->invoke($controller, 1603024, 'Al-Fateh', 'Al Diriyah', 'Handicap Asiático', 'Al-Fateh +0.5 AH', 1.95);
echo "Status Gatekeeper (Al-Fateh +0.5 AH): " . $resFateh['statusGatekeeper'] . "\n";
echo "Msg: " . $resFateh['gatekeeperMsg'] . "\n\n";


