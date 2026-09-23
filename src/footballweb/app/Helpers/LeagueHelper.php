<?php

namespace App\Helpers;

class LeagueHelper
{
    /**
     * Mapeamento direto de League ID para País, Bandeira Emoji e Popularidade.
     */
    public static array $leagueMap = [
        // Brasil
        71   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        72   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        73   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        74   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        75   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        76   => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        642  => ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true],
        // Portugal
        94   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true],
        95   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => false],
        96   => ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => false],
        // Inglaterra
        39   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true],
        40   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        41   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        42   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        45   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        48   => ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => false],
        // Espanha
        140  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true],
        141  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => false],
        143  => ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => false],
        // Itália
        135  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true],
        136  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => false],
        137  => ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => false],
        // Alemanha
        78   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true],
        79   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => false],
        81   => ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => false],
        // França
        61   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => true],
        62   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => false],
        66   => ['country' => 'França', 'flag' => '🇫🇷', 'popular' => false],
        // Holanda
        88   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true],
        89   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => false],
        90   => ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => false],
        // México
        262  => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true],
        263  => ['country' => 'México', 'flag' => '🇲🇽', 'popular' => false],
        // Argentina
        128  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true],
        129  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => false],
        130  => ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => false],
        // EUA
        253  => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true],
        254  => ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => false],
        // Canadá
        479  => ['country' => 'Canadá', 'flag' => '🇨🇦', 'popular' => true],
        259  => ['country' => 'Canadá', 'flag' => '🇨🇦', 'popular' => false],
        // Suécia / Noruega / Finlândia / Romênia / Sérvia / Peru / Equador / Uruguai / Chile / Colômbia
        113  => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => true],
        114  => ['country' => 'Suécia', 'flag' => '🇸🇪', 'popular' => false],
        103  => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => true],
        104  => ['country' => 'Noruega', 'flag' => '🇳🇴', 'popular' => false],
        244  => ['country' => 'Finlândia', 'flag' => '🇫🇮', 'popular' => false],
        283  => ['country' => 'Romênia', 'flag' => '🇷🇴', 'popular' => false],
        286  => ['country' => 'Sérvia', 'flag' => '🇷🇸', 'popular' => false],
        281  => ['country' => 'Peru', 'flag' => '🇵🇪', 'popular' => false],
        242  => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
        917  => ['country' => 'Equador', 'flag' => '🇪🇨', 'popular' => false],
        268  => ['country' => 'Uruguai', 'flag' => '🇺🇾', 'popular' => false],
        265  => ['country' => 'Chile', 'flag' => '🇨🇱', 'popular' => false],
        239  => ['country' => 'Colômbia', 'flag' => '🇨🇴', 'popular' => false],
        169  => ['country' => 'China', 'flag' => '🇨🇳', 'popular' => false],
        292  => ['country' => 'Coreia do Sul', 'flag' => '🇰🇷', 'popular' => false],
        98   => ['country' => 'Japão', 'flag' => '🇯🇵', 'popular' => false],
        307  => ['country' => 'Arábia Saudita', 'flag' => '🇸🇦', 'popular' => false],
        203  => ['country' => 'Turquia', 'flag' => '🇹🇷', 'popular' => false],
        207  => ['country' => 'Suíça', 'flag' => '🇨🇭', 'popular' => false],
        144  => ['country' => 'Bélgica', 'flag' => '🇧🇪', 'popular' => false],
        119  => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
        121  => ['country' => 'Dinamarca', 'flag' => '🇩🇰', 'popular' => false],
        218  => ['country' => 'Áustria', 'flag' => '🇦🇹', 'popular' => false],
        197  => ['country' => 'Grécia', 'flag' => '🇬🇷', 'popular' => false],
        106  => ['country' => 'Polônia', 'flag' => '🇵🇱', 'popular' => false],
        345  => ['country' => 'República Tcheca', 'flag' => '🇨🇿', 'popular' => false],
        501  => ['country' => 'Paraguai', 'flag' => '🇵🇾', 'popular' => false],
        // Competições Internacionais
        1    => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false],
        2    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        3    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        4    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        5    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        9    => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        10   => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => true],
        11   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        13   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        15   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        16   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        17   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        18   => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        531  => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        541  => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true],
        667  => ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false],
        772  => ['country' => 'INTERNACIONAL', 'flag' => '🌎', 'popular' => false],
        848  => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
        1028 => ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => false],
    ];

    /**
     * Resolve o país, bandeira e popularidade de uma liga por ID ou Nome.
     */
    public static function resolveCountryAndFlag(?int $leagueId, ?string $leagueName): array
    {
        $lId = (int)($leagueId ?? 0);

        if ($lId > 0 && isset(self::$leagueMap[$lId])) {
            return [
                'country' => self::$leagueMap[$lId]['country'],
                'flag'    => self::$leagueMap[$lId]['flag'],
                'popular' => self::$leagueMap[$lId]['popular'] ?? false
            ];
        }

        $lNameLower = strtolower($leagueName ?? '');

        if (empty($lNameLower)) {
            return ['country' => 'Outro', 'flag' => '🌐', 'popular' => false];
        }

        // 1. CONMEBOL / Sul-Americana
        if (
            strpos($lNameLower, 'libertadores') !== false ||
            strpos($lNameLower, 'sudamericana') !== false ||
            strpos($lNameLower, 'recopa') !== false ||
            strpos($lNameLower, 'conmebol') !== false ||
            strpos($lNameLower, 'copa america') !== false
        ) {
            return ['country' => 'INTERNACIONAL', 'flag' => '🏆', 'popular' => true];
        }

        // 2. Torneios Internacionais
        if (
            strpos($lNameLower, 'champions league') !== false ||
            strpos($lNameLower, 'europa league') !== false ||
            strpos($lNameLower, 'conference league') !== false ||
            strpos($lNameLower, 'world cup') !== false ||
            strpos($lNameLower, 'copa do mundo') !== false ||
            strpos($lNameLower, 'friendlies') !== false ||
            strpos($lNameLower, 'amistoso') !== false ||
            strpos($lNameLower, 'leagues cup') !== false ||
            strpos($lNameLower, 'nations league') !== false ||
            strpos($lNameLower, 'euro') !== false ||
            strpos($lNameLower, 'concacaf') !== false ||
            strpos($lNameLower, 'afc') !== false ||
            strpos($lNameLower, 'caf') !== false ||
            strpos($lNameLower, 'uefa') !== false ||
            strpos($lNameLower, 'internacional') !== false ||
            strpos($lNameLower, 'international') !== false
        ) {
            return ['country' => 'INTERNACIONAL', 'flag' => '🌍', 'popular' => false];
        }

        // 3. Países por palavras-chave
        if (
            strpos($lNameLower, 'brasil') !== false || strpos($lNameLower, 'brasileirão') !== false ||
            strpos($lNameLower, 'brasileirao') !== false || strpos($lNameLower, 'copa do brasil') !== false ||
            strpos($lNameLower, 'paulista') !== false || strpos($lNameLower, 'carioca') !== false ||
            strpos($lNameLower, 'gaúcho') !== false || strpos($lNameLower, 'gaucho') !== false ||
            strpos($lNameLower, 'mineiro') !== false || strpos($lNameLower, 'baiano') !== false ||
            strpos($lNameLower, 'pernambucano') !== false || strpos($lNameLower, 'cearense') !== false ||
            strpos($lNameLower, 'paranaense') !== false || strpos($lNameLower, 'catarinense') !== false
        ) {
            return ['country' => 'Brasil', 'flag' => '🇧🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'primeira') !== false || strpos($lNameLower, 'portugal') !== false) {
            return ['country' => 'Portugal', 'flag' => '🇵🇹', 'popular' => true];
        }
        if (strpos($lNameLower, 'england') !== false || strpos($lNameLower, 'premier league') !== false || strpos($lNameLower, 'championship') !== false || strpos($lNameLower, 'league one') !== false || strpos($lNameLower, 'league two') !== false) {
            return ['country' => 'Inglaterra', 'flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'popular' => true];
        }
        if (strpos($lNameLower, 'espanha') !== false || strpos($lNameLower, 'spain') !== false || strpos($lNameLower, 'la liga') !== false || strpos($lNameLower, 'segunda divisi') !== false) {
            return ['country' => 'Espanha', 'flag' => '🇪🇸', 'popular' => true];
        }
        if (strpos($lNameLower, 'itália') !== false || strpos($lNameLower, 'italia') !== false || strpos($lNameLower, 'coppa italia') !== false || strpos($lNameLower, 'serie a') !== false || strpos($lNameLower, 'serie b') !== false) {
            return ['country' => 'Itália', 'flag' => '🇮🇹', 'popular' => true];
        }
        if (strpos($lNameLower, 'bundesliga') !== false || strpos($lNameLower, 'alemanha') !== false || strpos($lNameLower, 'germany') !== false) {
            return ['country' => 'Alemanha', 'flag' => '🇩🇪', 'popular' => true];
        }
        if (strpos($lNameLower, 'frança') !== false || strpos($lNameLower, 'france') !== false || strpos($lNameLower, 'ligue 1') !== false || strpos($lNameLower, 'ligue 2') !== false) {
            return ['country' => 'França', 'flag' => '🇫🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'eredivisie') !== false || strpos($lNameLower, 'holanda') !== false || strpos($lNameLower, 'eerste divisie') !== false) {
            return ['country' => 'Holanda', 'flag' => '🇳🇱', 'popular' => true];
        }
        if (strpos($lNameLower, 'argentina') !== false || strpos($lNameLower, 'liga profesional') !== false || strpos($lNameLower, 'primera nacional') !== false) {
            return ['country' => 'Argentina', 'flag' => '🇦🇷', 'popular' => true];
        }
        if (strpos($lNameLower, 'mls') !== false || strpos($lNameLower, 'major league') !== false || strpos($lNameLower, 'usa') !== false) {
            return ['country' => 'EUA', 'flag' => '🇺🇸', 'popular' => true];
        }
        if (strpos($lNameLower, 'canada') !== false || strpos($lNameLower, 'canadá') !== false || strpos($lNameLower, 'canadian') !== false) {
            return ['country' => 'Canadá', 'flag' => '🇨🇦', 'popular' => true];
        }
        if (strpos($lNameLower, 'méxico') !== false || strpos($lNameLower, 'mexico') !== false || strpos($lNameLower, 'liga mx') !== false) {
            return ['country' => 'México', 'flag' => '🇲🇽', 'popular' => true];
        }

        return ['country' => 'Outro', 'flag' => '🌐', 'popular' => false];
    }

    /**
     * Verifica se o clube pertence ao grupo Tier 1 de Elite Mundial/Continental.
     * Base canônica sincronizada com scripts/leagues_config.py e ApostaController.
     */
    public static function isTier1EliteClub(?int $teamId = null, ?string $teamName = null): bool
    {
        static $tier1Clubs = [
            // Espanha
            529  => "Barcelona",
            541  => "Real Madrid",
            530  => "Atlético Madrid",
            // Inglaterra
            50   => "Manchester City",
            40   => "Liverpool",
            42   => "Arsenal",
            49   => "Chelsea",
            47   => "Tottenham",
            33   => "Manchester United",
            // Alemanha
            157  => "Bayern Munich",
            165  => "Borussia Dortmund",
            168  => "Bayer Leverkusen",
            173  => "RB Leipzig",
            // França
            85   => "Paris Saint Germain",
            91   => "Monaco",
            80   => "Lyon",
            81   => "Marseille",
            // Itália
            505  => "Inter",
            489  => "AC Milan",
            496  => "Juventus",
            492  => "Napoli",
            497  => "AS Roma",
            487  => "Lazio",
            // Portugal
            211  => "Benfica",
            212  => "FC Porto",
            228  => "Sporting CP",
            // Holanda
            194  => "Ajax",
            197  => "PSV Eindhoven",
            209  => "Feyenoord",
            // Escócia
            247  => "Celtic",
            257  => "Rangers",
            // Turquia
            645  => "Galatasaray",
            611  => "Fenerbahçe",
            549  => "Beşiktaş",
            // Grécia & Bélgica & Suíça & Dinamarca
            553  => "Olympiakos Piraeus",
            569  => "Club Brugge KV",
            554  => "Anderlecht",
            565  => "BSC Young Boys",
            400  => "FC Copenhagen",
            // Áustria
            571  => "Red Bull Salzburg",
            637  => "Sturm Graz",
            781  => "Rapid Vienna",
            // Tchéquia
            560  => "Slavia Praha",
            628  => "Sparta Praha",
            567  => "Plzen",
            // Romênia & Polônia & Suécia
            559  => "FCSB",
            2246 => "CFR 1907 Cluj",
            339  => "Legia Warszawa",
            347  => "Lech Poznan",
            375  => "Malmo FF",
            // Noruega & Finlândia
            327  => "Bodo/Glimt",
            329  => "Molde",
            331  => "Rosenborg",
            649  => "HJK Helsinki",
            // Arábia Saudita
            2932 => "Al-Hilal Saudi FC",
            2939 => "Al-Nassr",
            2938 => "Al-Ittihad FC",
            2929 => "Al-Ahli Jeddah",
            // Brasil (G-12)
            127  => "Flamengo",
            121  => "Palmeiras",
            1062 => "Atlético Mineiro",
            126  => "Sao Paulo",
            131  => "Corinthians",
            130  => "Gremio",
            119  => "Internacional",
            124  => "Fluminense",
            120  => "Botafogo",
            135  => "Cruzeiro",
            133  => "Vasco DA Gama",
            128  => "Santos",
            // Argentina
            451  => "Boca Juniors",
            435  => "River Plate",
            436  => "Racing Club",
            453  => "Independiente",
            460  => "San Lorenzo",
            450  => "Estudiantes L.P.",
            438  => "Velez Sarsfield",
            // Uruguai
            2348 => "Penarol",
            2356 => "Club Nacional",
            // Colômbia
            1137 => "Atletico Nacional",
            1125 => "Millonarios",
            1139 => "Santa Fe",
            1135 => "Junior",
            1138 => "America de Cali",
            // Chile
            2315 => "Colo Colo",
            2323 => "Universidad de Chile",
            2994 => "U. Catolica",
            // Equador
            1158 => "LDU de Quito",
            1153 => "Independiente del Valle",
            1152 => "Barcelona SC",
            1148 => "Emelec",
            // Peru
            2540 => "Universitario",
            2553 => "Alianza Lima",
            2546 => "Sporting Cristal",
            // Paraguai
            1182 => "Olimpia",
            1176 => "Cerro Porteno",
            1179 => "Libertad Asuncion",
            // México
            2287 => "Club America",
            2279 => "Tigres UANL",
            2282 => "Monterrey",
            2278 => "Guadalajara Chivas",
            2295 => "Cruz Azul",
            2286 => "U.N.A.M. - Pumas",
            2281 => "Toluca",
            2292 => "CF Pachuca"
        ];

        // 1. Validação por ID oficial (100% determinística)
        if ($teamId !== null && $teamId > 0) {
            return isset($tier1Clubs[(int)$teamId]);
        }

        // 2. Fallback secundário por nome se o ID não for informado
        if (empty($teamName)) {
            return false;
        }

        $raw = mb_strtolower(trim($teamName));
        $norm = iconv('UTF-8', 'ASCII//TRANSLIT', $raw);
        if ($norm === false) {
            $norm = $raw;
        }

        $disqualifiedHomonyms = [
            'guayaquil', 'sc', 'montevideo', 'sarandi', 'gijon', 'turku', 'limeira',
            'kansas', 'san jose', 'khalsa', 'miami', 'bogota', 'escaldes', 'intercity'
        ];
        foreach ($disqualifiedHomonyms as $dh) {
            if (strpos($norm, $dh) !== false && strpos($norm, 'manchester city') === false) {
                return false;
            }
        }

        foreach ($tier1Clubs as $id => $name) {
            $cNorm = iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower(trim($name)));
            if ($norm === $cNorm || strpos($norm, " {$cNorm} ") !== false) {
                return true;
            }
            if (strlen($cNorm) >= 6 && strpos($norm, $cNorm) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('resolveLeagueCountryAndFlag')) {
    function resolveLeagueCountryAndFlag($leagueId, $leagueName, $leagueMap = null) {
        return \App\Helpers\LeagueHelper::resolveCountryAndFlag((int)$leagueId, (string)$leagueName);
    }
}

if (!function_exists('isTier1EliteClub')) {
    function isTier1EliteClub($teamId = null, $teamName = null) {
        return \App\Helpers\LeagueHelper::isTier1EliteClub($teamId !== null ? (int)$teamId : null, $teamName);
    }
}
